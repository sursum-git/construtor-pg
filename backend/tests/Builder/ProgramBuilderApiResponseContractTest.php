<?php
namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderApiSource;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

final class ProgramBuilderApiResponseContractTest extends TestCase
{
    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionClass(ProgramBuilderService::class);
        return $reflection->getMethod($method)->invoke($reflection->newInstanceWithoutConstructor(), ...$arguments);
    }

    public function testInlineSourceKeepsExplicitSursumContract(): void
    {
        $source = $this->invoke('normalizeApiSource', [
            'responseContract' => 'sursum-programs',
            'listEndpoint' => ['url' => 'https://progress.example/empresas', 'method' => 'POST'],
            'listResponse' => ['itemsPath' => 'data'],
        ]);
        self::assertSame('sursum-programs', $source['responseContract']);
    }

    public function testRegistryAndEntityBindingPreserveContract(): void
    {
        $metadata = $this->invoke('normalizeApiSourceRegistryPayload', [
            'code' => 'motor_progress_empresas', 'name' => 'Empresas Progress',
            'baseUrl' => 'https://progress.example', 'responseContract' => 'sursum-programs',
            'operations' => [['code' => 'read', 'name' => 'Consultar', 'type' => 'list',
                'path' => '/empresas', 'method' => 'POST', 'itemsPath' => 'data',
                'bodyTemplate' => ['operation' => 'read']]],
        ]);
        self::assertSame('sursum-programs', $metadata['responseContract']);
        $source = (new BuilderApiSource())->setCode('motor_progress_empresas')
            ->setBaseUrl('https://progress.example')->setMetadata($metadata);
        $binding = $this->invoke('resolveApiSourceBinding', $source, 'read', '');
        self::assertSame('sursum-programs', $binding['responseContract']);
        self::assertSame(['operation' => 'read'], $binding['listEndpoint']['bodyTemplate']);
    }

    public function testMissingContractDefaultsToGeneric(): void
    {
        self::assertSame('generic', $this->invoke('normalizeApiResponseContract', null));
    }

    public function testUnknownContractIsRejected(): void
    {
        $this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeApiResponseContract', 'javascript');
    }

    public function testStructuredContractValueIsRejected(): void
    {
        $this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeApiResponseContract', ['sursum-programs']);
    }

    public function testCatalogMasksAndRestoresIntegrationTokenWithoutChangingStaticTypes(): void
    {
        $operations = [['code' => 'read', 'bodyTemplate' => ['operation' => 'read',
            '_integrationToken' => 'test-only-secret', 'take' => 20, 'enabled' => false]]];
        $masked = $this->invoke('maskApiOperationSecrets', $operations);
        self::assertSame('********', $masked[0]['bodyTemplate']['_integrationToken']);
        self::assertSame(20, $masked[0]['bodyTemplate']['take']);
        self::assertFalse($masked[0]['bodyTemplate']['enabled']);
        $restored = $this->invoke('restoreMaskedApiOperationSecrets', $masked, $operations);
        self::assertSame('test-only-secret', $restored[0]['bodyTemplate']['_integrationToken']);
    }

    public function testEntitySourceMasksTokensInEveryCrudEndpoint(): void
    {
        $source = [];
        foreach (['listEndpoint', 'detailEndpoint', 'createEndpoint', 'updateEndpoint', 'deleteEndpoint'] as $endpoint) {
            $source[$endpoint] = ['bodyTemplate' => ['_integrationToken' => 'test-only-secret', 'operation' => 'read']];
        }
        $masked = $this->invoke('maskApiSourceSecrets', $source);
        foreach (array_keys($source) as $endpoint) {
            self::assertSame('********', $masked[$endpoint]['bodyTemplate']['_integrationToken']);
        }
        $restored = $this->invoke('restoreMaskedApiSourceSecrets', $masked, $source);
        self::assertSame('test-only-secret', $restored['createEndpoint']['bodyTemplate']['_integrationToken']);
    }
}
