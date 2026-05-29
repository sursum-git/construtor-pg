<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeRegulatedDocumentStore;
use PHPUnit\Framework\TestCase;

class RuntimeRegulatedDocumentStoreTest extends TestCase
{
    public function testCleanupExpiredDataClearsPayloadArtifactAndEvents(): void
    {
        $store = new RuntimeRegulatedDocumentStore('sqlite:///:memory:', true, true);
        $store->initializeSchema();
        $store->saveRecord([
            'issueId' => 'rdoc-test-1',
            'tenantId' => 'tenant-a',
            'userId' => 'tester',
            'screenId' => 'documentos.regulados-fiscal-base',
            'documentId' => 'documento-regulado-fiscal-base',
            'track' => 'fiscal',
            'documentType' => 'invoice_control',
            'complianceProfile' => 'near_homologated',
            'state' => 'issued',
            'hash' => 'sha256:' . str_repeat('a', 64),
            'canonicalPayload' => ['rows' => [['id' => 1]]],
            'artifact' => [
                'format' => 'pdf',
                'fileName' => 'doc.pdf',
                'contentType' => 'application/pdf',
                'contentBase64' => base64_encode('%PDF-1.4'),
            ],
            'metadata' => [
                'retention' => [
                    'keepPayload' => true,
                    'keepArtifact' => true,
                    'storeDays' => 1,
                ],
            ],
            'createdAt' => new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
            'updatedAt' => new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
            'issuedAt' => new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        ]);
        $store->appendEvent('rdoc-test-1', 'issue', ['state' => 'issued']);

        $preview = $store->cleanupExpiredData(false, new \DateTimeImmutable('2026-01-03T10:00:00+00:00'));
        self::assertSame(1, $preview['payloadsCleared']);
        self::assertSame(1, $preview['artifactsCleared']);
        self::assertSame(1, $preview['eventsDeleted']);

        $applied = $store->cleanupExpiredData(true, new \DateTimeImmutable('2026-01-03T10:00:00+00:00'));
        self::assertSame(1, $applied['payloadsCleared']);
        self::assertSame(1, $applied['artifactsCleared']);
        self::assertSame(1, $applied['eventsDeleted']);

        $record = $store->findByIssueId('rdoc-test-1');
        self::assertSame([], $record['canonicalPayload']);
        self::assertFalse(isset($record['artifact']['contentBase64']));
        self::assertSame([], $store->fetchEvents('rdoc-test-1'));
    }

    public function testCollectObservabilitySummaryAggregatesStorageStats(): void
    {
        $store = new RuntimeRegulatedDocumentStore('sqlite:///:memory:', true, true);
        $store->initializeSchema();
        $store->saveRecord([
            'issueId' => 'rdoc-test-2',
            'tenantId' => 'tenant-a',
            'userId' => 'tester',
            'screenId' => 'documentos.regulados-fiscal-base',
            'documentId' => 'documento-regulado-fiscal-base',
            'track' => 'fiscal',
            'documentType' => 'invoice_control',
            'complianceProfile' => 'near_homologated',
            'state' => 'verified',
            'hash' => 'sha256:' . str_repeat('b', 64),
            'canonicalPayload' => ['rows' => [['id' => 2]]],
            'artifact' => [
                'format' => 'html',
                'fileName' => 'doc.html',
                'contentType' => 'text/html',
                'contentBase64' => base64_encode('<html></html>'),
            ],
            'createdAt' => new \DateTimeImmutable('2026-01-05T10:00:00+00:00'),
            'updatedAt' => new \DateTimeImmutable('2026-01-05T10:00:00+00:00'),
        ]);

        $summary = $store->collectObservabilitySummary();
        self::assertSame(1, $summary['total']);
        self::assertSame(1, $summary['withHash']);
        self::assertSame(1, $summary['withArtifact']);
        self::assertSame(1, $summary['withCanonicalPayload']);
        self::assertSame(1, $summary['verified']);
        self::assertSame(1, $summary['byTrack']['fiscal']);
    }
}
