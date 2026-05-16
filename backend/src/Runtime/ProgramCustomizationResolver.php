<?php

namespace App\Runtime;

use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\ProgramRepository;

class ProgramCustomizationResolver
{
    public function __construct(
        private readonly ProgramRepository $programs,
        private readonly BuilderProgramOverlayVersionRepository $overlayVersions,
        private readonly PermissionResolver $permissions,
        private readonly StructuralIntegrityService $integrity,
    ) {
    }

    public function resolve(string $screenId, array $baseDefinition): ?array
    {
        $program = $this->programs->findOneBy(['screenId' => $screenId, 'status' => 'published']);
        if (!$program) {
            return null;
        }

        $variant = $this->overlayVersions->findPublishedVariant($program->getCode(), $this->permissions->getTenantId());
        if (!$variant) {
            return null;
        }
        $this->integrity->assertOverlay($variant->getOverlay());
        $this->integrity->assertOverlayVersion($variant);

        $definition = $variant->getResolvedDefinition();
        if (!is_array($definition) || !$definition) {
            return null;
        }

        $definition['screenId'] = $screenId;
        $definition['program'] = is_array($definition['program'] ?? null) ? $definition['program'] : [];
        $definition['program']['customizationKind'] = $variant->getOverlay()->getCustomizationKind();
        $definition['program']['subscriberId'] = $variant->getOverlay()->getSubscriberId();
        $definition['program']['baseProgramCode'] = $variant->getOverlay()->getProgramCode();
        $definition['program']['baseProgramVersionId'] = $variant->getOverlay()->getBaseProgramVersionId();

        return $definition;
    }
}
