<?php

namespace App\Email;

final class EmailAnalyticsRepository
{
    private const FAILURE_EVENTS = ['BOUNCE', 'REJECT', 'RENDERING_FAILURE'];
    private const SORT_FIELDS = ['dt_hr_evento', 'cod_tipo', 'remetente', 'destinatario', 'titulo', 'id'];

    /** @var \Closure(): array<int, array<string, mixed>> */
    private \Closure $rows;
    private ?\PDO $connection = null;

    public function __construct(callable $rows)
    {
        $this->rows = \Closure::fromCallable($rows);
    }

    public static function fromEnvironment(): self
    {
        $url = (string) (getenv('EMAIL_ANALYTICS_DATABASE_URL') ?: '');
        if ($url === '') throw new \RuntimeException('EMAIL_ANALYTICS_DATABASE_URL não foi configurada.');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'postgresql') throw new \RuntimeException('A conexão de e-mails deve usar PostgreSQL.');
        $repository = new self(static fn (): array => []);
        $repository->connection = new \PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $parts['host'] ?? '', $parts['port'] ?? 5432, ltrim((string) ($parts['path'] ?? ''), '/')), urldecode((string) ($parts['user'] ?? '')), urldecode((string) ($parts['pass'] ?? '')), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        return $repository;
    }

    /** @param array<string, mixed> $row */
    public static function normalizeEvent(array $row): string
    {
        $payload = is_string($row['json_evento'] ?? null) ? json_decode($row['json_evento'], true) : null;
        $message = is_array($payload) ? ($payload['Message'] ?? $payload) : [];
        if (is_string($message)) $message = json_decode($message, true);
        $value = is_array($message) ? ($message['eventType'] ?? $message['notificationType'] ?? $payload['eventType'] ?? $payload['notificationType'] ?? $row['cod_tipo'] ?? '') : ($row['cod_tipo'] ?? '');
        return strtoupper(str_replace([' ', '-'], '_', trim((string) $value)));
    }

    /** @param array<string, mixed> $row @return array{status:string, reason:string, occurred_at:string} */
    public static function eventDetail(array $row): array
    {
        $payload = is_string($row['json_evento'] ?? null) ? json_decode($row['json_evento'], true) : null;
        $message = is_array($payload) ? ($payload['Message'] ?? $payload) : [];
        if (is_string($message)) $message = json_decode($message, true);
        if (!is_array($message)) $message = [];
        $type = strtoupper((string) ($message['notificationType'] ?? self::normalizeEvent($row)));

        if ($type === 'BOUNCE') {
            $bounce = is_array($message['bounce'] ?? null) ? $message['bounce'] : [];
            $reason = trim(implode(' / ', array_filter([(string) ($bounce['bounceType'] ?? ''), (string) ($bounce['bounceSubType'] ?? '')])));
            return ['status' => 'Falha na entrega', 'reason' => $reason ?: 'Sem motivo informado pela AWS.', 'occurred_at' => (string) ($bounce['timestamp'] ?? '')];
        }
        if ($type === 'DELIVERY') {
            $delivery = is_array($message['delivery'] ?? null) ? $message['delivery'] : [];
            return ['status' => 'Entregue', 'reason' => (string) ($delivery['smtpResponse'] ?? 'Entrega confirmada pela AWS.'), 'occurred_at' => (string) ($delivery['timestamp'] ?? '')];
        }
        return ['status' => $type ?: 'Evento recebido', 'reason' => 'Sem detalhe adicional para este evento.', 'occurred_at' => ''];
    }

    /** @return array{sent:int, delivery:int, failures:int, delay:int, complaint:int, open:int, click:int} */
    public function summary(): array
    {
        if ($this->connection instanceof \PDO) {
            $event = "UPPER(REPLACE(REPLACE(COALESCE((json_evento::jsonb->>'Message')::jsonb->>'eventType', (json_evento::jsonb->>'Message')::jsonb->>'notificationType', json_evento::jsonb->>'eventType', json_evento::jsonb->>'notificationType', cod_tipo), ' ', '_'), '-', '_'))";
            $statement = $this->connection->query("SELECT COUNT(*) FILTER (WHERE {$event} IN ('SEND', 'DELIVERY', 'BOUNCE', 'REJECT', 'RENDERING_FAILURE', 'DELIVERY_DELAY')) AS sent, COUNT(*) FILTER (WHERE {$event} = 'DELIVERY') AS delivery, COUNT(*) FILTER (WHERE {$event} IN ('BOUNCE', 'REJECT', 'RENDERING_FAILURE')) AS failures, COUNT(*) FILTER (WHERE {$event} = 'DELIVERY_DELAY') AS delay, COUNT(*) FILTER (WHERE {$event} = 'COMPLAINT') AS complaint, COUNT(*) FILTER (WHERE {$event} = 'OPEN') AS open, COUNT(*) FILTER (WHERE {$event} = 'CLICK') AS click FROM notificacoes WHERE dt_hr_evento >= CURRENT_DATE");
            $summary = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];
            return array_map('intval', ['sent' => $summary['sent'] ?? 0, 'delivery' => $summary['delivery'] ?? 0, 'failures' => $summary['failures'] ?? 0, 'delay' => $summary['delay'] ?? 0, 'complaint' => $summary['complaint'] ?? 0, 'open' => $summary['open'] ?? 0, 'click' => $summary['click'] ?? 0]);
        }
        return $this->summarize(($this->rows)());
    }

    /** @param array<int, array<string, mixed>> $rows @return array{sent:int, delivery:int, failures:int, delay:int, complaint:int, open:int, click:int} */
    private function summarize(array $rows): array
    {
        $summary = ['sent' => 0, 'delivery' => 0, 'failures' => 0, 'delay' => 0, 'complaint' => 0, 'open' => 0, 'click' => 0];
        foreach ($rows as $row) {
            $event = self::normalizeEvent($row);
            if (in_array($event, ['SEND', 'DELIVERY', 'BOUNCE', 'REJECT', 'RENDERING_FAILURE', 'DELIVERY_DELAY'], true)) $summary['sent']++;
            if ($event === 'DELIVERY') $summary['delivery']++;
            if (in_array($event, self::FAILURE_EVENTS, true)) $summary['failures']++;
            if ($event === 'DELIVERY_DELAY') $summary['delay']++;
            if ($event === 'COMPLAINT') $summary['complaint']++;
            if ($event === 'OPEN') $summary['open']++;
            if ($event === 'CLICK') $summary['click']++;
        }
        return $summary;
    }

    /** @param array<string, mixed> $criteria @return array<int, array<string, mixed>> */
    public function search(array $criteria): array
    {
        $sort = (string) ($criteria['sort'] ?? 'dt_hr_evento');
        if (!in_array($sort, self::SORT_FIELDS, true)) throw new \InvalidArgumentException('Campo de ordenação inválido.');
        if (!$this->connection instanceof \PDO) return ($this->rows)();
        $types = array_values(array_filter(array_map('strtoupper', (array) ($criteria['types'] ?? []))));
        $page = max(1, (int) ($criteria['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($criteria['pageSize'] ?? 20)));
        $where = ['dt_hr_evento >= CURRENT_DATE'];
        $params = [];
        $event = "UPPER(REPLACE(REPLACE(COALESCE((json_evento::jsonb->>'Message')::jsonb->>'eventType', (json_evento::jsonb->>'Message')::jsonb->>'notificationType', json_evento::jsonb->>'eventType', json_evento::jsonb->>'notificationType', cod_tipo), ' ', '_'), '-', '_'))";
        if ($types) { $where[] = $event . ' IN (' . implode(',', array_fill(0, count($types), '?')) . ')'; $params = $types; }
        $sql = 'SELECT COUNT(*) OVER() AS total_records, id, cod_tipo, dt_hr_evento, mensagem_id_email, mensagem_id_aws, remetente, destinatario, titulo, json_evento FROM notificacoes WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sort . ' DESC LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize);
        $statement = $this->connection->prepare($sql); $statement->execute($params);
        return array_map(static function (array $row): array {
            $detail = self::eventDetail($row);
            unset($row['json_evento']);
            return $row + ['detail_status' => $detail['status'], 'detail_reason' => $detail['reason'], 'detail_occurred_at' => $detail['occurred_at']];
        }, $statement->fetchAll(\PDO::FETCH_ASSOC));
    }
}
