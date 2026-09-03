# Review package

Base: 43df232f
Head: 5351eb25

5351eb25 Adiciona geracao master detail no builder
 backend/src/Builder/ProgramBuilderService.php      | 286 ++++++++++++++++++++-
 .../ProgramBuilderServiceMasterDetailTest.php      | 202 +++++++++++++++
 2 files changed, 482 insertions(+), 6 deletions(-)
diff --git a/backend/src/Builder/ProgramBuilderService.php b/backend/src/Builder/ProgramBuilderService.php
index ee3dcd27..b6e02137 100644
--- a/backend/src/Builder/ProgramBuilderService.php
+++ b/backend/src/Builder/ProgramBuilderService.php
@@ -3343,22 +3343,22 @@ class ProgramBuilderService
         $baseProgramCode = $this->normalizeOptionalCode((string) ($payload['baseProgramCode'] ?? ''));
         $baseProgramVersionId = $this->normalizePositiveInt($payload['baseProgramVersionId'] ?? null);
         $upgradeFrozen = ($payload['upgradeFrozen'] ?? false) === true;
         $frozenReason = trim((string) ($payload['frozenReason'] ?? '')) ?: null;
         $publicationPolicy = $this->normalizePublicationPolicy($payload['publicationPolicy'] ?? null);
         $reportSourceType = strtolower(trim((string) ((is_array($payload['reportConfig'] ?? null) ? $payload['reportConfig'] : [])['sourceType'] ?? 'operational')));
 
         if ($programCode === '' || $programTitle === '' || $moduleCode === '' || $screenId === '') {
             throw new RuntimeHttpException('PROGRAM_BUILDER_REQUIRED_FIELDS', 'Informe codigo, titulo, modulo e screenId.', 422);
         }
-        if (!in_array($pageType, ['crud', 'custom', 'analytics', 'report', 'special_document', 'regulated_document'], true)) {
-            throw new RuntimeHttpException('PROGRAM_BUILDER_PAGE_TYPE_NOT_SUPPORTED', 'Nesta etapa o construtor visual suporta programas CRUD, analytics, report, special_document, regulated_document e custom.', 422, [
+        if (!in_array($pageType, ['crud', 'custom', 'analytics', 'report', 'special_document', 'regulated_document', 'master_detail'], true)) {
+            throw new RuntimeHttpException('PROGRAM_BUILDER_PAGE_TYPE_NOT_SUPPORTED', 'Nesta etapa o construtor visual suporta programas CRUD, master_detail, analytics, report, special_document, regulated_document e custom.', 422, [
                 'pageType' => $pageType,
             ]);
         }
         if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
             throw new RuntimeHttpException('PROGRAM_VERSION_INVALID', 'Use versao no formato semantico x.y.z.', 422, [
                 'version' => $version,
             ]);
         }
         if ($programOrigin === 'standard' && $ownerScope !== 'system') {
             throw new RuntimeHttpException('PROGRAM_STANDARD_SCOPE_INVALID', 'Programa padrao precisa pertencer ao escopo do sistema.', 422, [
@@ -3413,21 +3413,21 @@ class ProgramBuilderService
         if ($sequenceNumber < $module->getNumberStart() || $sequenceNumber > $module->getNumberEnd()) {
             throw new RuntimeHttpException('PROGRAM_CODE_OUT_OF_MODULE_RANGE', 'O sequencial do programa esta fora da faixa do modulo.', 422, [
                 'programCode' => $programCode,
                 'module' => $moduleCode,
                 'range' => [$module->getNumberStart(), $module->getNumberEnd()],
             ]);
         }
 
         $entity = $overrideEntity;
         if (
-            in_array($pageType, ['crud', 'analytics'], true)
+            in_array($pageType, ['crud', 'analytics', 'master_detail'], true)
             || ($pageType === 'report' && $reportSourceType !== 'analytic')
             || ($pageType === 'special_document' && $reportSourceType !== 'analytic')
             || ($pageType === 'regulated_document' && $reportSourceType !== 'analytic')
         ) {
             if ($builderEntityCode === '') {
                 throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_REQUIRED', 'Informe a entidade base para o programa.', 422);
             }
             if ($entity === null) {
                 $entity = $this->entities->findOneBy(['code' => $builderEntityCode]);
             }
@@ -3447,20 +3447,26 @@ class ProgramBuilderService
                     'builderEntityCode' => $builderEntityCode,
                     'entityType' => $entity->getEntityType(),
                 ]);
             }
             if ($pageType === 'crud' && !in_array($entity->getEntityType(), ['persistence', 'api'], true)) {
                 throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_TYPE_NOT_SUPPORTED', 'Nesta etapa o gerador de programa suporta apenas entidades persistence e api.', 422, [
                     'builderEntityCode' => $builderEntityCode,
                     'entityType' => $entity->getEntityType(),
                 ]);
             }
+            if ($pageType === 'master_detail' && $entity->getEntityType() !== 'persistence') {
+                throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_TYPE_NOT_SUPPORTED', 'Mestre-detalhe aceita apenas entidade mestre persistence.', 422, [
+                    'builderEntityCode' => $builderEntityCode,
+                    'entityType' => $entity->getEntityType(),
+                ]);
+            }
         }
 
         if ($pageType === 'custom') {
             if (!in_array($customMode, ['iframe', 'htmlUrl'], true)) {
                 throw new RuntimeHttpException('PROGRAM_BUILDER_CUSTOM_MODE_INVALID', 'Modo custom invalido.', 422, [
                     'customMode' => $customMode,
                 ]);
             }
             if ($customEntryUrl === '') {
                 throw new RuntimeHttpException('PROGRAM_BUILDER_CUSTOM_ENTRY_REQUIRED', 'Informe a URL/entrypoint manual do programa custom.', 422);
@@ -3492,45 +3498,171 @@ class ProgramBuilderService
             'programCode' => $programCode,
             'programTitle' => $programTitle,
             'module' => $moduleCode,
             'pageType' => $pageType,
             'builderEntityCode' => $builderEntityCode,
             'screenId' => $screenId,
             'version' => $version,
             'subtitle' => trim((string) ($payload['subtitle'] ?? '')) ?: null,
             'icon' => trim((string) ($payload['icon'] ?? '')) ?: null,
             'permissionPrefix' => $this->safePermissionPrefix((string) ($payload['permissionPrefix'] ?? $programCode)),
-            'allowCreate' => $pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'create') ? (bool) ($payload['allowCreate'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowCreate'] ?? true))) : false,
-            'allowUpdate' => $pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'update') ? (bool) ($payload['allowUpdate'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowUpdate'] ?? true))) : false,
-            'allowDelete' => $pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'delete') ? (bool) ($payload['allowDelete'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowDelete'] ?? true))) : false,
+            'allowCreate' => $pageType === 'master_detail' ? (bool) ($payload['allowCreate'] ?? true) : ($pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'create') ? (bool) ($payload['allowCreate'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowCreate'] ?? true))) : false),
+            'allowUpdate' => $pageType === 'master_detail' ? (bool) ($payload['allowUpdate'] ?? true) : ($pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'update') ? (bool) ($payload['allowUpdate'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowUpdate'] ?? true))) : false),
+            'allowDelete' => $pageType === 'master_detail' ? (bool) ($payload['allowDelete'] ?? true) : ($pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'delete') ? (bool) ($payload['allowDelete'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowDelete'] ?? true))) : false),
             'changeSummary' => trim((string) ($payload['changeSummary'] ?? '')) ?: null,
             'programOrigin' => $programOrigin,
             'ownerScope' => $ownerScope,
             'customizationPolicy' => $customizationPolicy,
             'subscriberId' => $subscriberId !== '' ? $subscriberId : null,
             'baseProgramCode' => $baseProgramCode !== '' ? $baseProgramCode : null,
             'baseProgramVersionId' => $baseProgramVersionId,
             'upgradeFrozen' => $upgradeFrozen,
             'frozenReason' => $frozenReason,
             'publicationPolicy' => $publicationPolicy,
             'analyticsConfig' => $pageType === 'analytics' && $entity instanceof BuilderEntity ? $this->normalizeAnalyticsBuilderConfig($payload['analyticsConfig'] ?? null, $entity) : null,
             'reportConfig' => $pageType === 'report' ? $this->normalizeReportBuilderConfig($payload['reportConfig'] ?? null, $entity instanceof BuilderEntity ? $entity : null) : null,
             'specialDocumentConfig' => $pageType === 'special_document' ? $this->normalizeSpecialDocumentBuilderConfig($payload['specialDocumentConfig'] ?? null, $entity instanceof BuilderEntity ? $entity : null) : null,
             'regulatedDocumentConfig' => $pageType === 'regulated_document' ? $this->normalizeRegulatedDocumentBuilderConfig($payload['regulatedDocumentConfig'] ?? null, $entity instanceof BuilderEntity ? $entity : null) : null,
+            'masterDetailConfig' => $pageType === 'master_detail' && $entity instanceof BuilderEntity ? $this->normalizeMasterDetailBuilderConfig(is_array($payload['masterDetailConfig'] ?? null) ? $payload['masterDetailConfig'] : [], $entity) : null,
             'customMode' => $pageType === 'custom' ? $customMode : null,
             'customEntryUrl' => $pageType === 'custom' ? $customEntryUrl : null,
             'customFrameTitle' => $pageType === 'custom' ? ($customFrameTitle !== '' ? $customFrameTitle : $programTitle) : null,
             '_module' => $module,
             '_entity' => $entity,
         ];
     }
 
+    private function normalizeMasterDetailBuilderConfig(array $value, BuilderEntity $master): array
+    {
+        $masterEntityCode = $this->safeCode((string) ($value['masterEntityCode'] ?? $master->getCode()));
+        if ($masterEntityCode !== $master->getCode()) {
+            throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_MASTER_INVALID', 'A entidade mestre informada nao corresponde a entidade base do programa.', 422, [
+                'masterEntityCode' => $masterEntityCode,
+            ]);
+        }
+
+        $createFlowInput = is_array($value['createFlow'] ?? null) ? $value['createFlow'] : [];
+        $createFlowMode = trim((string) ($createFlowInput['mode'] ?? 'parentFirst'));
+        if (!in_array($createFlowMode, ['parentFirst', 'draftWithChildren'], true)) {
+            $createFlowMode = 'parentFirst';
+        }
+        $createGraphEndpointId = trim((string) ($createFlowInput['endpointId'] ?? ''));
+        if ($createGraphEndpointId !== '' && !preg_match('/^[A-Za-z0-9_.:-]+$/', $createGraphEndpointId)) {
+            throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_CREATE_GRAPH_REQUIRED', 'O fluxo conjunto exige endpointId seguro para createGraph.', 422, [
+                'mode' => $createFlowMode,
+            ]);
+        }
+        if ($createFlowMode === 'draftWithChildren' && $createGraphEndpointId === '') {
+            throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_CREATE_GRAPH_REQUIRED', 'O fluxo conjunto exige endpointId para createGraph.', 422, [
+                'mode' => $createFlowMode,
+            ]);
+        }
+
+        $details = [];
+        $detailCodes = [];
+        foreach ((array) ($value['details'] ?? []) as $item) {
+            if (!is_array($item)) {
+                continue;
+            }
+            $detailCode = $this->safeCode((string) ($item['entityCode'] ?? ''));
+            if ($detailCode === '' || isset($detailCodes[$detailCode])) {
+                throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_DETAIL_DUPLICATE', 'Cada entidade filha deve aparecer uma unica vez no mestre-detalhe.', 422, [
+                    'entityCode' => $detailCode,
+                ]);
+            }
+            $detailCodes[$detailCode] = true;
+            $detail = $this->entities->findOneBy(['code' => $detailCode]);
+            if (!$detail || $detail->getEntityType() !== 'persistence') {
+                throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_DETAIL_NOT_FOUND', 'Entidade filha persistence nao encontrada.', 422, [
+                    'entityCode' => $detailCode,
+                ]);
+            }
+
+            $availableFields = [];
+            foreach ($detail->getFields() as $field) {
+                $availableFields[$field->getCode()] = $field;
+            }
+            $parentField = $this->safeCode((string) ($item['parentField'] ?? ''));
+            if ($parentField === '' || !isset($availableFields[$parentField])) {
+                throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_PARENT_FIELD_INVALID', 'A entidade filha deve informar um campo de vinculo existente para a entidade mestre.', 422, [
+                    'entityCode' => $detailCode,
+                    'parentField' => $parentField,
+                ]);
+            }
+
+            $displayFields = [];
+            foreach ((array) ($item['displayFields'] ?? []) as $fieldCode) {
+                $fieldCode = $this->safeCode((string) $fieldCode);
+                if ($fieldCode === '' || !isset($availableFields[$fieldCode])) {
+                    throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID', 'Campo de exibicao ou total da filha nao encontrado.', 422, [
+                        'entityCode' => $detailCode,
+                        'field' => $fieldCode,
+                    ]);
+                }
+                $displayFields[$fieldCode] = true;
+            }
+            if (!$displayFields) {
+                foreach ($availableFields as $fieldCode => $field) {
+                    if ($fieldCode !== $parentField && !$field->isPrimaryKey()) {
+                        $displayFields[$fieldCode] = true;
+                    }
+                }
+            }
+
+            $totals = [];
+            foreach ((array) ($item['totals'] ?? []) as $total) {
+                $totalField = $this->safeCode((string) (is_array($total) ? ($total['field'] ?? '') : ''));
+                if ($totalField === '' || !isset($availableFields[$totalField])) {
+                    throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID', 'Campo de exibicao ou total da filha nao encontrado.', 422, [
+                        'entityCode' => $detailCode,
+                        'field' => $totalField,
+                    ]);
+                }
+                $totalType = strtolower(trim((string) ($total['type'] ?? $this->normalizeFieldType($availableFields[$totalField]->getDataType()))));
+                if (!in_array($totalType, ['currency', 'number', 'integer', 'decimal'], true)) {
+                    throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID', 'Total da filha exige campo numerico.', 422, [
+                        'entityCode' => $detailCode,
+                        'field' => $totalField,
+                    ]);
+                }
+                $totals[] = [
+                    'field' => $totalField,
+                    'label' => trim((string) ($total['label'] ?? $availableFields[$totalField]->getLabel())),
+                    'type' => $totalType,
+                ];
+            }
+
+            $details[] = [
+                'entityCode' => $detailCode,
+                'title' => trim((string) ($item['title'] ?? $detail->getName())) ?: $detail->getName(),
+                'singularTitle' => trim((string) ($item['singularTitle'] ?? '')),
+                'parentField' => $parentField,
+                'displayFields' => array_keys($displayFields),
+                'totals' => $totals,
+            ];
+        }
+        if (!$details) {
+            throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID', 'Informe ao menos uma entidade filha valida.', 422, [
+                'field' => 'details',
+            ]);
+        }
+
+        return [
+            'masterEntityCode' => $master->getCode(),
+            'createFlow' => array_filter([
+                'mode' => $createFlowMode,
+                'endpointId' => $createGraphEndpointId !== '' ? $createGraphEndpointId : null,
+            ], static fn (mixed $item): bool => $item !== null),
+            'details' => $details,
+        ];
+    }
+
     private function normalizeReportBuilderConfig(mixed $value, ?BuilderEntity $entity): array
     {
         $config = is_array($value) ? $value : [];
         $sourceType = strtolower(trim((string) ($config['sourceType'] ?? 'operational')));
         if (!in_array($sourceType, ['operational', 'analytic'], true)) {
             $sourceType = 'operational';
         }
 
         $documentKind = strtolower(trim((string) ($config['documentKind'] ?? 'management')));
         $groupField = $this->safeCode((string) ($config['groupField'] ?? ''));
@@ -4068,20 +4200,21 @@ class ProgramBuilderService
             'parameters' => $parameters,
             'defaultSort' => $defaultSort,
             'chartCategoryField' => $this->safeCode((string) ($value['chartCategoryField'] ?? '')),
             'chartValueField' => $this->safeCode((string) ($value['chartValueField'] ?? '')),
         ];
     }
 
     private function generateProgramDefinition(array $config): array
     {
         $definition = match ($config['pageType']) {
+            'master_detail' => $this->generateMasterDetailDefinition($config),
             'custom' => $this->generateCustomDefinition($config),
             'analytics' => $this->generateAnalyticsDefinition($config),
             'report' => $this->generateReportDefinition($config),
             'special_document' => $this->generateSpecialDocumentDefinition($config),
             'regulated_document' => $this->generateRegulatedDocumentDefinition($config),
             default => $this->generateCrudDefinition($config),
         };
 
         $definition['program'] = is_array($definition['program'] ?? null) ? $definition['program'] : [];
         $definition['program']['programOrigin'] = $config['programOrigin'];
@@ -4138,25 +4271,166 @@ class ProgramBuilderService
             'programCode' => $version->getProgramCode(),
             'programVersion' => $version->getVersion(),
             'builderEntityCode' => $version->getBuilderEntityCode(),
             'pageType' => $version->getPageType(),
             'permissions' => $definition['permissions'] ?? [],
             'dataModel' => $definition['dataModel'] ?? [],
             'analytics' => $definition['analytics'] ?? [],
             'report' => $definition['report'] ?? [],
             'specialDocument' => $definition['specialDocument'] ?? [],
             'regulatedDocument' => $definition['regulatedDocument'] ?? [],
+            'master' => $definition['master'] ?? [],
+            'details' => $definition['details'] ?? [],
+            'createFlow' => $definition['createFlow'] ?? [],
         ];
 
         return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
     }
 
+    private function generateMasterDetailDefinition(array $config): array
+    {
+        $master = $config['_entity'];
+        if (!$master instanceof BuilderEntity) {
+            throw new \LogicException('Programa mestre-detalhe exige entidade mestre.');
+        }
+        $masterDetailConfig = $this->normalizeMasterDetailBuilderConfig(
+            is_array($config['masterDetailConfig'] ?? null) ? $config['masterDetailConfig'] : [],
+            $master
+        );
+        $permissionPrefix = (string) ($config['permissionPrefix'] ?? '');
+        $readPermission = $permissionPrefix !== '' ? $permissionPrefix . '.read' : true;
+        $masterSection = $this->buildMasterDetailSection($master);
+        $details = [];
+        foreach ($masterDetailConfig['details'] as $detailConfig) {
+            $detail = $this->entities->findOneBy(['code' => $detailConfig['entityCode']]);
+            if (!$detail instanceof BuilderEntity) {
+                throw new \LogicException('Entidade filha normalizada nao encontrada.');
+            }
+            $details[] = $this->buildMasterDetailSection($detail, $detailConfig);
+        }
+
+        return [
+            'schemaVersion' => '1.0',
+            'pageType' => 'master_detail',
+            'screenId' => $config['screenId'],
+            'program' => [
+                'id' => $config['programCode'],
+                'module' => $config['module'] ?? 'cadastros',
+                'entity' => $master->getCode(),
+                'title' => $config['programTitle'],
+                'version' => $config['version'],
+                'subtitle' => $config['subtitle'] ?? null,
+                'icon' => $config['icon'] ?? null,
+                'permission' => $readPermission,
+            ],
+            'permissions' => [
+                'read' => $readPermission,
+                'create' => ($config['allowCreate'] ?? true) ? ($permissionPrefix !== '' ? $permissionPrefix . '.create' : true) : false,
+                'edit' => ($config['allowUpdate'] ?? true) ? ($permissionPrefix !== '' ? $permissionPrefix . '.edit' : true) : false,
+                'delete' => ($config['allowDelete'] ?? true) ? ($permissionPrefix !== '' ? $permissionPrefix . '.delete' : true) : false,
+            ],
+            'master' => $masterSection,
+            'details' => $details,
+            'createFlow' => $masterDetailConfig['createFlow'],
+        ];
+    }
+
+    private function buildMasterDetailSection(BuilderEntity $entity, array $detailConfig = []): array
+    {
+        $fieldsByCode = [];
+        $primaryKey = 'id';
+        foreach ($entity->getFields() as $field) {
+            $fieldsByCode[$field->getCode()] = $field;
+            if ($field->isPrimaryKey()) {
+                $primaryKey = $field->getCode();
+            }
+        }
+        $isDetail = $detailConfig !== [];
+        $parentField = $isDetail ? (string) $detailConfig['parentField'] : null;
+        $visibleFields = $isDetail ? array_fill_keys($detailConfig['displayFields'], true) : [];
+        if (!$isDetail) {
+            foreach ($fieldsByCode as $fieldCode => $field) {
+                if (!$field->isPrimaryKey()) {
+                    $visibleFields[$fieldCode] = true;
+                }
+            }
+        }
+
+        $fields = [];
+        $columns = [];
+        foreach ($fieldsByCode as $fieldCode => $field) {
+            $isPrimaryKey = $fieldCode === $primaryKey;
+            $isParentField = $fieldCode === $parentField;
+            if ($isDetail && !$isPrimaryKey && !$isParentField && !isset($visibleFields[$fieldCode])) {
+                continue;
+            }
+            $fieldConfig = [
+                'id' => $fieldCode,
+                'label' => $field->getLabel(),
+                'type' => $this->normalizeFieldType($field->getDataType()),
+                'required' => $field->isRequired(),
+            ];
+            if ($isPrimaryKey) {
+                $fieldConfig['readonlyOnEdit'] = true;
+                $fieldConfig['hidden'] = true;
+            }
+            if ($isParentField) {
+                $fieldConfig['hidden'] = true;
+            }
+            $options = $field->getOptions();
+            if (($options['readonly'] ?? false) === true || ($options['virtual'] ?? false) === true) {
+                $fieldConfig['readonly'] = true;
+            }
+            if (is_array($options['options'] ?? null)) {
+                $fieldConfig['options'] = $options['options'];
+            }
+            $fields[] = $fieldConfig;
+
+            if (isset($visibleFields[$fieldCode]) && count($columns) < 6) {
+                $columns[] = [
+                    'field' => $fieldCode,
+                    'title' => $field->getLabel(),
+                    'width' => in_array($fieldConfig['type'], ['datetime', 'text'], true) ? 180 : 130,
+                    'align' => in_array($fieldConfig['type'], ['number', 'integer', 'currency'], true) ? 'right' : 'left',
+                ];
+            }
+        }
+
+        $sectionId = $isDetail ? $entity->getCode() : 'master';
+        $api = [];
+        foreach (['read', 'get', 'create', 'update', 'delete'] as $operation) {
+            $api[$operation] = [
+                'endpointId' => $isDetail ? 'detail.' . $sectionId . '.' . $operation : 'master.' . $operation,
+                'method' => 'POST',
+            ];
+        }
+        $section = [
+            'id' => $sectionId,
+            'entity' => $entity->getCode(),
+            'title' => $isDetail ? $detailConfig['title'] : $entity->getName(),
+            'singularTitle' => $isDetail && $detailConfig['singularTitle'] !== '' ? $detailConfig['singularTitle'] : mb_strtolower($entity->getName()),
+            'idField' => $primaryKey,
+            'api' => $api,
+            'query' => ['sort' => [['field' => $primaryKey, 'dir' => 'asc']]],
+            'fields' => $fields,
+            'grid' => ['columns' => $columns],
+        ];
+        if ($isDetail) {
+            $section['parentField'] = $parentField;
+            $section['totals'] = $detailConfig['totals'];
+        } else {
+            $section['displayField'] = array_key_first($visibleFields) ?? $primaryKey;
+        }
+
+        return $section;
+    }
+
     private function generateCrudDefinition(array $config): array
     {
         $entity = $config['_entity'];
         $apiEntity = $entity instanceof BuilderEntity && $entity->getEntityType() === 'api';
         $fields = [];
         $filterFields = [];
         $gridColumns = [];
         $formFields = [];
         $primaryKey = 'id';
         $position = 0;
diff --git a/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php b/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
new file mode 100644
index 00000000..dd7d93d6
--- /dev/null
+++ b/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
@@ -0,0 +1,202 @@
+<?php
+
+namespace App\Tests\Builder;
+
+use App\Builder\ProgramBuilderService;
+use App\Entity\BuilderEntity;
+use App\Entity\BuilderField;
+use App\Odoo\OdooClient;
+use App\Repository\BuilderApiSourceRepository;
+use App\Repository\BuilderEditorLockRepository;
+use App\Repository\BuilderEntityRepository;
+use App\Repository\BuilderEntityVersionRepository;
+use App\Repository\BuilderFieldRepository;
+use App\Repository\BuilderModuleRepository;
+use App\Repository\BuilderProgramVersionRepository;
+use App\Repository\ProgramRepository;
+use App\Repository\RuntimeEndpointRepository;
+use App\Repository\ScreenDefinitionRepository;
+use App\Runtime\ProgramGovernanceService;
+use App\Runtime\ProgramOverlayService;
+use App\Runtime\PermissionResolver;
+use App\Runtime\RuntimeEnvironmentIdentityResolver;
+use App\Runtime\RuntimeEventService;
+use App\Runtime\RuntimeHttpException;
+use App\Runtime\RuntimeNotificationService;
+use App\Runtime\RuntimeSessionGuard;
+use App\Runtime\StructuralIntegrityService;
+use Doctrine\ORM\EntityManagerInterface;
+use PHPUnit\Framework\Attributes\DataProvider;
+use PHPUnit\Framework\TestCase;
+
+class ProgramBuilderServiceMasterDetailTest extends TestCase
+{
+    public function testGenerateMasterDetailDefinitionBuildsGraph(): void
+    {
+        $definition = $this->invokePrivateMixed($this->service(), 'generateMasterDetailDefinition', [[
+            'pageType' => 'master_detail',
+            'programCode' => 'vd0101',
+            'programTitle' => 'Pedido de venda',
+            'screenId' => 'vendas.pedidos',
+            'module' => 'vendas',
+            'permissionPrefix' => 'vendas.pedido',
+            'version' => '1.0.0',
+            '_entity' => $this->pedidoEntity(),
+            'masterDetailConfig' => $this->validMasterDetailConfig(),
+        ]]);
+
+        self::assertSame('master_detail', $definition['pageType']);
+        self::assertSame('pedido_venda', $definition['master']['entity']);
+        self::assertSame('pedido_id', $definition['details'][0]['parentField']);
+        self::assertSame('createGraph', $definition['createFlow']['endpointId']);
+        self::assertArrayNotHasKey('url', $definition['createFlow']);
+    }
+
+    #[DataProvider('invalidMasterDetailConfigs')]
+    public function testNormalizeMasterDetailConfigRejectsInvalidReferences(array $config, string $errorCode): void
+    {
+        try {
+            $this->invokePrivateMixed($this->service(), 'normalizeMasterDetailBuilderConfig', [$config, $this->pedidoEntity()]);
+            self::fail('A configuracao mestre-detalhe invalida deveria ser rejeitada.');
+        } catch (RuntimeHttpException $error) {
+            self::assertSame($errorCode, $error->getErrorCode());
+            self::assertSame(422, $error->getStatusCode());
+            self::assertNotSame([], $error->getDetails());
+        }
+    }
+
+    public static function invalidMasterDetailConfigs(): iterable
+    {
+        $valid = self::baseMasterDetailConfig();
+
+        $duplicate = $valid;
+        $duplicate['details'][] = $valid['details'][0];
+        yield 'filha repetida' => [$duplicate, 'PROGRAM_BUILDER_MASTER_DETAIL_DETAIL_DUPLICATE'];
+
+        $invalidParent = $valid;
+        $invalidParent['details'][0]['parentField'] = 'pedido_inexistente_id';
+        yield 'fk inexistente' => [$invalidParent, 'PROGRAM_BUILDER_MASTER_DETAIL_PARENT_FIELD_INVALID'];
+
+        $invalidField = $valid;
+        $invalidField['details'][0]['displayFields'] = ['produto_inexistente'];
+        yield 'campo ou total invalido' => [$invalidField, 'PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID'];
+
+        $withoutGraph = $valid;
+        $withoutGraph['createFlow'] = ['mode' => 'draftWithChildren'];
+        yield 'fluxo conjunto sem endpoint' => [$withoutGraph, 'PROGRAM_BUILDER_MASTER_DETAIL_CREATE_GRAPH_REQUIRED'];
+    }
+
+    private function service(): ProgramBuilderService
+    {
+        $entities = $this->createStub(BuilderEntityRepository::class);
+        $entities->method('findOneBy')->willReturnCallback(function (array $criteria): ?BuilderEntity {
+            return match ($criteria['code'] ?? null) {
+                'pedido_venda' => $this->pedidoEntity(),
+                'pedido_item' => $this->pedidoItemEntity(),
+                default => null,
+            };
+        });
+
+        return new ProgramBuilderService(
+            $entities,
+            $this->createStub(BuilderApiSourceRepository::class),
+            $this->createStub(BuilderEditorLockRepository::class),
+            $this->createStub(BuilderModuleRepository::class),
+            $this->createStub(BuilderFieldRepository::class),
+            $this->createStub(BuilderEntityVersionRepository::class),
+            $this->createStub(BuilderProgramVersionRepository::class),
+            $this->createStub(ProgramRepository::class),
+            $this->createStub(ScreenDefinitionRepository::class),
+            $this->createStub(RuntimeEndpointRepository::class),
+            $this->createStub(EntityManagerInterface::class),
+            $this->createStub(StructuralIntegrityService::class),
+            $this->createStub(ProgramGovernanceService::class),
+            $this->createStub(ProgramOverlayService::class),
+            $this->createStub(RuntimeNotificationService::class),
+            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
+            $this->createStub(PermissionResolver::class),
+            $this->createStub(RuntimeSessionGuard::class),
+            $this->createStub(OdooClient::class),
+            $this->createStub(RuntimeEventService::class),
+        );
+    }
+
+    private function validMasterDetailConfig(): array
+    {
+        return self::baseMasterDetailConfig();
+    }
+
+    private static function baseMasterDetailConfig(): array
+    {
+        return [
+            'masterEntityCode' => 'pedido_venda',
+            'createFlow' => [
+                'mode' => 'draftWithChildren',
+                'endpointId' => 'createGraph',
+            ],
+            'details' => [[
+                'entityCode' => 'pedido_item',
+                'title' => 'Itens',
+                'parentField' => 'pedido_id',
+                'displayFields' => ['produto', 'quantidade', 'valor_total'],
+                'totals' => [[
+                    'field' => 'valor_total',
+                    'label' => 'Total dos itens',
+                    'type' => 'currency',
+                ]],
+            ]],
+        ];
+    }
+
+    private function pedidoEntity(): BuilderEntity
+    {
+        return $this->entity('pedido_venda', 'Pedido de venda', [
+            $this->field('id', 'ID', 'integer', 1, true),
+            $this->field('numero', 'Numero', 'string', 2),
+            $this->field('cliente', 'Cliente', 'string', 3),
+        ]);
+    }
+
+    private function pedidoItemEntity(): BuilderEntity
+    {
+        return $this->entity('pedido_item', 'Item do pedido', [
+            $this->field('id', 'ID', 'integer', 1, true),
+            $this->field('pedido_id', 'Pedido', 'integer', 2),
+            $this->field('produto', 'Produto', 'string', 3),
+            $this->field('quantidade', 'Quantidade', 'decimal', 4),
+            $this->field('valor_total', 'Valor total', 'currency', 5),
+        ]);
+    }
+
+    private function entity(string $code, string $name, array $fields): BuilderEntity
+    {
+        $entity = (new BuilderEntity())
+            ->setCode($code)
+            ->setName($name)
+            ->setEntityType('persistence')
+            ->setTableName('t_' . $code);
+        foreach ($fields as $field) {
+            $entity->addField($field);
+        }
+
+        return $entity;
+    }
+
+    private function field(string $code, string $label, string $type, int $position, bool $primaryKey = false): BuilderField
+    {
+        return (new BuilderField())
+            ->setCode($code)
+            ->setLabel($label)
+            ->setDataType($type)
+            ->setPosition($position)
+            ->setPrimaryKey($primaryKey);
+    }
+
+    private function invokePrivateMixed(object $target, string $method, array $arguments): mixed
+    {
+        $reflection = new \ReflectionMethod($target, $method);
+        $reflection->setAccessible(true);
+
+        return $reflection->invokeArgs($target, $arguments);
+    }
+}

