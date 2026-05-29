<?php

namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramCustomizationResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSpecialDocumentService;
use App\Runtime\StructuralIntegrityService;
use PHPUnit\Framework\TestCase;

class RuntimeSpecialDocumentServiceTest extends TestCase
{
    public function testRenderReturnsControlledPlaceholder(): void
    {
        $service = $this->createService();

        $result = $service->render('documentos.especiais-base', []);

        self::assertSame('special_document', $service->schema('documentos.especiais-base')['pageType']);
        self::assertSame('danfe', $result['documentKind']);
        self::assertSame('native_stub', $result['renderEngine']);
        self::assertNotEmpty($result['sections']);
    }

    public function testExportReturnsPdfPayload(): void
    {
        $service = $this->createService();

        $result = $service->export('documentos.especiais-base', ['format' => 'pdf']);

        self::assertSame('pdf', $result['format']);
        self::assertStringStartsWith('%PDF', (string) base64_decode((string) $result['contentBase64']));
    }

    public function testUnsafeMetadataIsRejected(): void
    {
        $service = $this->createService([
            'specialDocument' => [
                'layout' => [
                    'notes' => '<script>alert(1)</script>',
                ],
            ],
        ]);

        $this->expectException(RuntimeHttpException::class);
        $service->render('documentos.especiais-base', []);
    }

    private function createService(array $definitionPatch = []): RuntimeSpecialDocumentService
    {
        $screen = (new ScreenDefinition())
            ->setScreenId('documentos.especiais-base')
            ->setPageType('special_document')
            ->setStatus('published')
            ->setDefinition(array_replace_recursive([
                'pageType' => 'special_document',
                'screenId' => 'documentos.especiais-base',
                'program' => [
                    'id' => 'documento-especial-base',
                    'title' => 'Documento especial base',
                    'subtitle' => 'Contrato separado',
                ],
                'specialDocument' => [
                    'classification' => [
                        'documentProfile' => 'special',
                        'documentKind' => 'danfe',
                    ],
                    'renderEngine' => 'native_stub',
                    'source' => [
                        'type' => 'operational',
                        'entityCode' => 'cliente',
                    ],
                    'layout' => [
                        'title' => 'Documento especial base',
                        'subtitle' => 'Placeholder',
                        'notes' => 'Sem layout livre',
                    ],
                    'outputs' => [
                        'html' => true,
                        'pdf' => true,
                    ],
                ],
            ], $definitionPatch));

        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');
        $permissions->method('getUserId')->willReturn('tester');
        $permissions->method('getSessionId')->willReturn('sess-special');

        $integrity = $this->createStub(StructuralIntegrityService::class);
        $customizations = $this->createStub(ProgramCustomizationResolver::class);
        $customizations->method('resolve')->willReturn(null);

        return new RuntimeSpecialDocumentService(
            $screens,
            $permissions,
            $integrity,
            $customizations,
            null,
        );
    }
}
