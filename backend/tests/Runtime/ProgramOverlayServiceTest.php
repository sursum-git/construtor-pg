<?php

namespace App\Tests\Runtime;

use App\Entity\BuilderProgramOverlay;
use App\Entity\BuilderProgramOverlayVersion;
use App\Entity\BuilderProgramVersion;
use App\Repository\BuilderProgramOverlayRepository;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Runtime\ProgramOverlayService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProgramOverlayServiceTest extends TestCase
{
    public function testPreviewRebaseReturnsWarningWhenBaseAndOverlayTouchSameSection(): void
    {
        $overlay = (new BuilderProgramOverlay())
            ->setProgramCode('cd0001')
            ->setSubscriberId('tenant-a')
            ->setCustomizationKind('customer_overlay')
            ->setBaseProgramVersionId(10)
            ->setStatus('published');
        $this->setEntityId($overlay, 9);

        $overlayVersion = (new BuilderProgramOverlayVersion())
            ->setOverlay($overlay)
            ->setVersionNumber(2)
            ->setStatus('published')
            ->setSnapshot([
                'definitionOverrides' => [
                    'program' => [
                        'title' => 'Clientes Overlay',
                    ],
                ],
            ])
            ->setResolvedDefinition([
                'pageType' => 'crud',
                'program' => ['title' => 'Clientes Overlay'],
            ]);

        $oldBase = (new BuilderProgramVersion())
            ->setProgramCode('cd0001')
            ->setVersion('1.0.0')
            ->setGeneratedDefinition([
                'pageType' => 'crud',
                'program' => ['title' => 'Clientes'],
            ]);
        $newBase = (new BuilderProgramVersion())
            ->setProgramCode('cd0001')
            ->setVersion('1.1.0')
            ->setGeneratedDefinition([
                'pageType' => 'crud',
                'program' => ['title' => 'Clientes v2'],
            ]);
        $this->setEntityId($newBase, 11);

        $overlays = $this->createMock(BuilderProgramOverlayRepository::class);
        $overlays->expects(self::once())
            ->method('find')
            ->with(9)
            ->willReturn($overlay);

        $overlayVersions = $this->createMock(BuilderProgramOverlayVersionRepository::class);
        $overlayVersions->expects(self::once())
            ->method('findLatestByOverlayId')
            ->with(9)
            ->willReturn($overlayVersion);

        $programVersions = $this->createMock(BuilderProgramVersionRepository::class);
        $programVersions->expects(self::once())
            ->method('findPublishedByProgramCode')
            ->with('cd0001')
            ->willReturn($newBase);
        $programVersions->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($oldBase);

        $integrity = $this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::once())->method('assertOverlay')->with($overlay);
        $integrity->expects(self::once())->method('assertOverlayVersion')->with($overlayVersion);

        $service = new ProgramOverlayService(
            $overlays,
            $overlayVersions,
            $programVersions,
            $this->createStub(EntityManagerInterface::class),
            $integrity,
        );

        $result = $service->previewRebase(9);

        self::assertSame('warning', $result['status']);
        self::assertSame(['program'], $result['conflicts']);
        self::assertSame('1.1.0', $result['targetBaseVersion']);
        self::assertSame('1.0.0', $result['currentBaseVersion']);
        self::assertSame(['title' => 'Clientes Overlay'], $result['definitionOverrides']['program'] ?? null);
        self::assertSame(['title' => 'Clientes v2'], $result['targetBaseDefinition']['program'] ?? null);
        self::assertCount(1, $result['sections']);
        self::assertTrue($result['sections'][0]['conflict']);
    }

    public function testRebaseBlocksFrozenCustomerCustom(): void
    {
        $overlay = (new BuilderProgramOverlay())
            ->setProgramCode('cd0001')
            ->setSubscriberId('tenant-a')
            ->setCustomizationKind('customer_custom')
            ->setBaseProgramVersionId(10)
            ->setUpgradeFrozen(true)
            ->setStatus('published');

        $overlayVersion = (new BuilderProgramOverlayVersion())
            ->setOverlay($overlay)
            ->setStatus('published')
            ->setResolvedDefinition(['pageType' => 'crud']);
        $this->setEntityId($overlayVersion, 33);

        $publishedBase = (new BuilderProgramVersion())
            ->setProgramCode('cd0001')
            ->setVersion('1.1.0')
            ->setGeneratedDefinition(['pageType' => 'crud']);
        $this->setEntityId($publishedBase, 11);

        $oldBase = (new BuilderProgramVersion())
            ->setProgramCode('cd0001')
            ->setVersion('1.0.0')
            ->setGeneratedDefinition(['pageType' => 'crud']);

        $overlayVersions = $this->createMock(BuilderProgramOverlayVersionRepository::class);
        $overlayVersions->expects(self::once())
            ->method('find')
            ->with(33)
            ->willReturn($overlayVersion);

        $programVersions = $this->createMock(BuilderProgramVersionRepository::class);
        $programVersions->expects(self::once())
            ->method('findPublishedByProgramCode')
            ->willReturn($publishedBase);
        $programVersions->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($oldBase);

        $integrity = $this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::once())->method('assertOverlay')->with($overlay);
        $integrity->expects(self::once())->method('assertOverlayVersion')->with($overlayVersion);

        $service = new ProgramOverlayService(
            $this->createStub(BuilderProgramOverlayRepository::class),
            $overlayVersions,
            $programVersions,
            $this->createStub(EntityManagerInterface::class),
            $integrity,
        );

        try {
            $service->rebase(33);
            self::fail('Expected blocked rebase.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('PROGRAM_OVERLAY_REBASE_BLOCKED', $error->getErrorCode());
        }
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
