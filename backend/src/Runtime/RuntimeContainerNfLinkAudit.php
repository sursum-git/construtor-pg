<?php
declare(strict_types=1);

namespace App\Runtime;

final class RuntimeContainerNfLinkAudit
{
    public static function inspect(array $body, array $request, string $operation, string $actor): array
    {
        $invalid = static fn (): array => [
            'before' => [], 'after' => [], 'events' => [],
            'metadata' => ['available' => false, 'reason' => 'nf_link_audit_invalid'],
        ];
        $audit = $body['audit'] ?? null;
        $cid = $request['container_id'] ?? null;
        if (($body['committed'] ?? null) !== true || ($body['success'] ?? null) !== true
            || isset($body['error']) || !is_int($cid) || $cid < 1
            || !in_array($operation, ['associar', 'desassociar'], true)
            || !is_array($audit) || ($audit['version'] ?? null) !== 1
            || ($audit['source'] ?? null) !== 'progress-transaction'
            || ($audit['resource'] ?? null) !== 'container_processes'
            || ($audit['operation'] ?? null) !== $operation
            || ($audit['recordId'] ?? null) !== $cid || ($audit['container_id'] ?? null) !== $cid
            || ($audit['actor']['login'] ?? null) !== $actor
            || !is_array($audit['changes'] ?? null) || !array_is_list($audit['changes'])
            || count($audit['changes']) !== count($request['nfs'] ?? [])) {
            return $invalid();
        }
        // NF linking must not change the container itself.
        if (!is_array($audit['before'] ?? null) || $audit['before'] !== ($audit['after'] ?? null)
            || ($audit['before']['container_id'] ?? null) !== $cid) {
            return $invalid();
        }
        $expected = [];
        foreach ($request['nfs'] as $note) {
            $snapshot = ['container_id' => $cid] + $note;
            $identity = self::identity($snapshot);
            if (isset($expected[$identity])) {
                return $invalid();
            }
            $expected[$identity] = $snapshot;
        }
        $events = [];
        foreach ($audit['changes'] as $change) {
            $id = $change['recordId'] ?? null;
            $expectedOperation = $operation === 'associar' ? 'create' : 'delete';
            $row = $expectedOperation === 'create' ? ($change['after'] ?? null) : ($change['before'] ?? null);
            $empty = $expectedOperation === 'create' ? ($change['before'] ?? null) : ($change['after'] ?? null);
            if (($change['resource'] ?? null) !== 'nfs_container'
                || ($change['operation'] ?? null) !== $expectedOperation || $empty !== []
                || !is_string($id) || !isset($expected[$id]) || !is_array($row)
                || count($row) !== 6 || array_diff_key($row, $expected[$id])) {
                return $invalid();
            }
            foreach ($expected[$id] as $field => $value) {
                if (($row[$field] ?? null) !== $value) {
                    return $invalid();
                }
            }
            unset($expected[$id]);
            $events[] = [
                'before' => $change['before'], 'after' => $change['after'],
                'metadata' => ['available' => true, 'version' => 1, 'source' => 'progress-transaction',
                    'resource' => 'nfs_container', 'operation' => $expectedOperation,
                    'recordId' => $id, 'containerId' => $cid, 'actor' => ['login' => $actor]],
            ];
        }
        if ($expected) {
            return $invalid();
        }
        return [
            'before' => $audit['before'], 'after' => $audit['after'], 'events' => $events,
            'metadata' => ['available' => true, 'version' => 1, 'source' => 'progress-transaction',
                'resource' => 'container_processes', 'operation' => $operation, 'recordId' => $cid,
                'containerId' => $cid, 'actor' => ['login' => $actor]],
        ];
    }

    private static function identity(array $row): string
    {
        return strtoupper(hash('sha256', implode(chr(1), [
            $row['container_id'], $row['documento'], $row['serie'],
            $row['estab'], $row['nat_operacao'], $row['cod_emitente'],
        ])));
    }
}
