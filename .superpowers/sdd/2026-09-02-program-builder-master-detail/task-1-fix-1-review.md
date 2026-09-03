# Scoped re-review package

Base: 5351eb25
Head: 91d60b52

Open findings:
- P1: total de campo textual aceito quando payload força currency.
- P2: ausência de caso de total inexistente/textual.

91d60b52 Valida totais master detail pelo catalogo
 backend/src/Builder/ProgramBuilderService.php            |  2 +-
 .../Builder/ProgramBuilderServiceMasterDetailTest.php    | 16 ++++++++++++++++
 2 files changed, 17 insertions(+), 1 deletion(-)
diff --git a/backend/src/Builder/ProgramBuilderService.php b/backend/src/Builder/ProgramBuilderService.php
index b6e02137..c04d637d 100644
--- a/backend/src/Builder/ProgramBuilderService.php
+++ b/backend/src/Builder/ProgramBuilderService.php
@@ -3610,21 +3610,21 @@ class ProgramBuilderService
 
             $totals = [];
             foreach ((array) ($item['totals'] ?? []) as $total) {
                 $totalField = $this->safeCode((string) (is_array($total) ? ($total['field'] ?? '') : ''));
                 if ($totalField === '' || !isset($availableFields[$totalField])) {
                     throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID', 'Campo de exibicao ou total da filha nao encontrado.', 422, [
                         'entityCode' => $detailCode,
                         'field' => $totalField,
                     ]);
                 }
-                $totalType = strtolower(trim((string) ($total['type'] ?? $this->normalizeFieldType($availableFields[$totalField]->getDataType()))));
+                $totalType = $this->normalizeFieldType($availableFields[$totalField]->getDataType());
                 if (!in_array($totalType, ['currency', 'number', 'integer', 'decimal'], true)) {
                     throw new RuntimeHttpException('PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID', 'Total da filha exige campo numerico.', 422, [
                         'entityCode' => $detailCode,
                         'field' => $totalField,
                     ]);
                 }
                 $totals[] = [
                     'field' => $totalField,
                     'label' => trim((string) ($total['label'] ?? $availableFields[$totalField]->getLabel())),
                     'type' => $totalType,
diff --git a/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php b/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
index dd7d93d6..906d6925 100644
--- a/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
+++ b/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
@@ -74,20 +74,36 @@ class ProgramBuilderServiceMasterDetailTest extends TestCase
         yield 'filha repetida' => [$duplicate, 'PROGRAM_BUILDER_MASTER_DETAIL_DETAIL_DUPLICATE'];
 
         $invalidParent = $valid;
         $invalidParent['details'][0]['parentField'] = 'pedido_inexistente_id';
         yield 'fk inexistente' => [$invalidParent, 'PROGRAM_BUILDER_MASTER_DETAIL_PARENT_FIELD_INVALID'];
 
         $invalidField = $valid;
         $invalidField['details'][0]['displayFields'] = ['produto_inexistente'];
         yield 'campo ou total invalido' => [$invalidField, 'PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID'];
 
+        $invalidTotalField = $valid;
+        $invalidTotalField['details'][0]['totals'] = [[
+            'field' => 'total_inexistente',
+            'label' => 'Total',
+            'type' => 'currency',
+        ]];
+        yield 'campo total inexistente' => [$invalidTotalField, 'PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID'];
+
+        $textTotal = $valid;
+        $textTotal['details'][0]['totals'] = [[
+            'field' => 'produto',
+            'label' => 'Total de produto',
+            'type' => 'currency',
+        ]];
+        yield 'campo textual nao pode ser total currency' => [$textTotal, 'PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID'];
+
         $withoutGraph = $valid;
         $withoutGraph['createFlow'] = ['mode' => 'draftWithChildren'];
         yield 'fluxo conjunto sem endpoint' => [$withoutGraph, 'PROGRAM_BUILDER_MASTER_DETAIL_CREATE_GRAPH_REQUIRED'];
     }
 
     private function service(): ProgramBuilderService
     {
         $entities = $this->createStub(BuilderEntityRepository::class);
         $entities->method('findOneBy')->willReturnCallback(function (array $criteria): ?BuilderEntity {
             return match ($criteria['code'] ?? null) {

