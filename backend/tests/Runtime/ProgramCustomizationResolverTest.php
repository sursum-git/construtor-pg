<?php

namespace App\Tests\Runtime;

use App\Entity\BuilderProgramOverlay;
use App\Entity\BuilderProgramOverlayVersion;
use App\Entity\Program;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\ProgramRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramCustomizationResolver;
use App\Runtime\StructuralIntegrityService;
use PHPUnit\Framework\TestCase;

class ProgramCustomizationResolverTest extends TestCase
{
    public function testResolveReturnsPublishedCustomerVariant(): void
    {
        $program = (new Program())
            ->setCode('cd0001')
            ->setScreenId('cadastros.clientes')
            ->setStatus('published');

        $overlay = (new BuilderProgramOverlay())
            ->setProgramCode('cd0001')
            ->setSubscriberId('tenant-a')
            ->setCustomizationKind('customer_custom')
            ->setBaseProgramVersionId(33)
            ->setStatus('published');

        $version = (new BuilderProgramOverlayVersion())
            ->setOverlay($overlay)
            ->setStatus('published')
            ->setResolvedDefinition([
                'pageType' => 'crud',
                'program' => [
                    'title' => 'Clientes Customizados',
                ],
            ]);

        $programs = $this->createMock(ProgramRepository::class);
        $programs->expects(self::once())
            ->method('findOneBy')
            ->with(['screenId' => 'cadastros.clientes', 'status' => 'published'])
            ->willReturn($program);

        $overlays = $this->createMock(BuilderProgramOverlayVersionRepository::class);
        $overlays->expects(self::once())
            ->method('findPublishedVariant')
            ->with('cd0001', 'tenant-a')
            ->willReturn($version);

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');

        $integrity = $this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::once())->method('assertOverlay')->with($overlay);
        $integrity->expects(self::once())->method('assertOverlayVersion')->with($version);

        $resolver = new ProgramCustomizationResolver($programs, $overlays, $permissions, $integrity);
        $definition = $resolver->resolve('cadastros.clientes', ['pageType' => 'crud']);

        self::assertIsArray($definition);
        self::assertSame('cadastros.clientes', $definition['screenId']);
        self::assertSame('customer_custom', $definition['program']['customizationKind']);
        self::assertSame('tenant-a', $definition['program']['subscriberId']);
        self::assertSame(33, $definition['program']['baseProgramVersionId']);
    }
}
