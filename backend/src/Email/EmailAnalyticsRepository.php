<?php

namespace App\Email;

final class EmailAnalyticsRepository
{
    private const FAILURE_EVENTS = ['BOUNCE', 'REJECT', 'RENDERING_FAILURE'];
    private const SORT_FIELDS = ['dt_hr_evento', 'cod_tipo', 'remetente', 'destinatario', 'titulo', 'id'];

    /** @var \Closure(): array<int, array<string, mixed>> */
    private \Closure $rows;

    public function __construct(callable $rows)
    {
        $this->rows = \Closure::fromCallable($rows);
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
        $summary = ['sent' => 0, 'failures' => 0];
        foreach (($this->rows)() as $row) {
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
        return ($this->rows)();
    }
}
