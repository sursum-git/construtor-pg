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
        $value = is_array($payload) ? ($payload['eventType'] ?? $payload['notificationType'] ?? $row['cod_tipo'] ?? '') : ($row['cod_tipo'] ?? '');
        return strtoupper(str_replace([' ', '-'], '_', trim((string) $value)));
    }

    /** @return array{sent:int, failures:int} */
    public function summary(): array
    {
        if ($this->connection instanceof \PDO) {
            $rows = $this->connection->query('SELECT cod_tipo, json_evento FROM notificacoes WHERE dt_hr_evento >= CURRENT_DATE')->fetchAll(\PDO::FETCH_ASSOC);
            return $this->summarize($rows);
        }
        return $this->summarize(($this->rows)());
    }

    /** @param array<int, array<string, mixed>> $rows @return array{sent:int, failures:int} */
    private function summarize(array $rows): array
    {
        $summary = ['sent' => 0, 'failures' => 0];
        foreach ($rows as $row) {
            $event = self::normalizeEvent($row);
            if ($event === 'SEND') $summary['sent']++;
            if (in_array($event, self::FAILURE_EVENTS, true)) $summary['failures']++;
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
        if ($types) { $where[] = 'UPPER(cod_tipo) IN (' . implode(',', array_fill(0, count($types), '?')) . ')'; $params = $types; }
        $sql = 'SELECT id, cod_tipo, dt_hr_evento, mensagem_id_email, mensagem_id_aws, remetente, destinatario, titulo FROM notificacoes WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sort . ' DESC LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize);
        $statement = $this->connection->prepare($sql); $statement->execute($params);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
