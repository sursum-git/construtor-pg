# Review package

Base: 91d60b52
Head: 5798ccae

5798ccae Publica programas master detail pelo builder
 .../task-2-report.md                               |  73 +++++++++++
 .../ProgramBuilderServiceMasterDetailTest.php      | 141 +++++++++++++++++++++
 2 files changed, 214 insertions(+)
diff --git a/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-2-report.md b/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-2-report.md
new file mode 100644
index 00000000..32706eae
--- /dev/null
+++ b/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-2-report.md
@@ -0,0 +1,73 @@
+# Task 2 — Persistir e publicar a definicao mestre-detalhe
+
+## Resultado
+
+- O teste integrado `testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition` cobre o rascunho e a publicacao de `pageType=master_detail`.
+- Ele confirma que `builderEntityCode` permanece `pedido_venda`, que o bloco normalizado fica em `builderConfig.masterDetailConfig`, que a definicao gerada e a `screen_definition` publicada mantem `pageType=master_detail` e `screenId=vendas.pedidos`.
+- A cobertura tambem prova que o fingerprint publicado muda se `master`, `details` ou `createFlow` forem removidos da definicao.
+
+## Arquivos
+
+- `backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php`
+- `.superpowers/sdd/2026-09-02-program-builder-master-detail/task-2-report.md`
+
+O codigo de producao necessario ja estava no commit aprovado da Task 1: ele normalizou `masterDetailConfig`, aceitou `master_detail` com mestre `persistence`, gerou a definicao e incluiu `master`, `details` e `createFlow` no fingerprint. A persistencia em `saveDraft()` e a publicacao em `publishVersion()` usam o caminho generico existente, que preserva `builderConfig`, `pageType`, `builderEntityCode` e a definicao. Nao foi introduzida duplicacao desse contrato.
+
+## Evidencia RED/GREEN
+
+### RED
+
+Como a Task 1 ja havia deixado o comportamento de producao disponivel, o teste de persistencia/publicacao ficou verde ao ser exercitado corretamente. Para comprovar que a nova cobertura protege o comportamento, foi aplicada e revertida uma mutacao local e temporaria em `saveDraft()`:
+
+```text
+->setBuilderConfig([])
+```
+
+Execucao:
+
+```text
+php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition
+
+FAILURES!
+Failed asserting that null is identical to 'pedido_venda'.
+Tests: 1, Assertions: 3, Failures: 1
+```
+
+A mutacao nao foi mantida. Ela demonstra que a regressao de nao persistir `masterDetailConfig` e detectada pelo teste.
+
+### GREEN
+
+Com o codigo restaurado:
+
+```text
+php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition
+OK (1 test, 13 assertions)
+
+php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php
+OK (13 tests, 76 assertions)
+```
+
+Tambem executados:
+
+```text
+php -l src/Builder/ProgramBuilderService.php
+php -l tests/Builder/ProgramBuilderServiceMasterDetailTest.php
+git diff --check
+```
+
+Sem erros de sintaxe ou espacos em branco invalidados pelo diff.
+
+## Self-review
+
+- O teste usa entidades, modulos e versoes reais do dominio; os dublês ficam somente nos repositorios e na persistencia externa.
+- A expectativa de configuracao e literal (`pedido_venda`) e falha se o bloco for descartado no salvamento.
+- A publicacao verifica o `Program` e a `ScreenDefinition` criados, nao apenas chamadas de mocks.
+- O fingerprint e confrontado com uma definicao sem os tres blocos mestre-detalhe, evitando um teste meramente de presenca.
+- Nao houve URL livre, SQL, JavaScript, template, `eval`, `Function` ou dialogo nativo.
+- Nao houve alteracao em UI, exemplos, mocks, demos, producao HTML ou `kendo/`; por isso a paridade demo/producao nao se aplica.
+- A revisao foi feita localmente porque o requisito desta Task proibe subagentes.
+
+## Preocupacoes
+
+- Esta Task valida a persistencia e a publicacao do contrato. Ela nao amplia o despachante de `production/app.html`, os endpoints runtime nem a interface do Program Builder, conforme o escopo definido.
+- O worktree possui artefatos de orquestracao em `.superpowers/`, que devem ser incluidos junto com o teste e este relatorio no commit da Task.
diff --git a/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php b/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
index 906d6925..900e3fcc 100644
--- a/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
+++ b/backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
@@ -1,17 +1,22 @@
 <?php
 
 namespace App\Tests\Builder;
 
 use App\Builder\ProgramBuilderService;
 use App\Entity\BuilderEntity;
+use App\Entity\BuilderEntityVersion;
 use App\Entity\BuilderField;
+use App\Entity\BuilderModule;
+use App\Entity\BuilderProgramVersion;
+use App\Entity\Program;
+use App\Entity\ScreenDefinition;
 use App\Odoo\OdooClient;
 use App\Repository\BuilderApiSourceRepository;
 use App\Repository\BuilderEditorLockRepository;
 use App\Repository\BuilderEntityRepository;
 use App\Repository\BuilderEntityVersionRepository;
 use App\Repository\BuilderFieldRepository;
 use App\Repository\BuilderModuleRepository;
 use App\Repository\BuilderProgramVersionRepository;
 use App\Repository\ProgramRepository;
 use App\Repository\RuntimeEndpointRepository;
@@ -24,20 +29,134 @@ use App\Runtime\RuntimeEventService;
 use App\Runtime\RuntimeHttpException;
 use App\Runtime\RuntimeNotificationService;
 use App\Runtime\RuntimeSessionGuard;
 use App\Runtime\StructuralIntegrityService;
 use Doctrine\ORM\EntityManagerInterface;
 use PHPUnit\Framework\Attributes\DataProvider;
 use PHPUnit\Framework\TestCase;
 
 class ProgramBuilderServiceMasterDetailTest extends TestCase
 {
+    public function testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition(): void
+    {
+        $module = (new BuilderModule())
+            ->setCode('vendas')
+            ->setName('Vendas')
+            ->setAbbreviation('vd')
+            ->setNumberStart(100)
+            ->setNumberEnd(199);
+        $master = $this->pedidoEntity();
+        $detail = $this->pedidoItemEntity();
+        $masterVersion = (new BuilderEntityVersion())->setBuilderEntityCode('pedido_venda');
+        $this->setEntityId($masterVersion, 700);
+        $savedVersion = null;
+        $publishedProgram = null;
+        $publishedScreen = null;
+
+        $entities = $this->createStub(BuilderEntityRepository::class);
+        $entities->method('findOneBy')->willReturnCallback(static function (array $criteria) use ($master, $detail): ?BuilderEntity {
+            return match ($criteria['code'] ?? null) {
+                'pedido_venda' => $master,
+                'pedido_item' => $detail,
+                default => null,
+            };
+        });
+
+        $modules = $this->createStub(BuilderModuleRepository::class);
+        $modules->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?BuilderModule => $criteria === ['code' => 'vendas'] ? $module : null);
+
+        $versions = $this->createStub(BuilderProgramVersionRepository::class);
+        $versions->method('findOneBy')->willReturn(null);
+        $versions->method('find')->willReturnCallback(static function (int $id) use (&$savedVersion): ?BuilderProgramVersion {
+            return $id === 701 ? $savedVersion : null;
+        });
+        $versions->method('findByProgramCodeOrdered')->willReturnCallback(static function (string $programCode) use (&$savedVersion): array {
+            return $programCode === 'vd0101' && $savedVersion ? [$savedVersion] : [];
+        });
+
+        $programs = $this->createStub(ProgramRepository::class);
+        $programs->method('findOneBy')->willReturnCallback(static function () use (&$publishedProgram): ?Program {
+            return $publishedProgram;
+        });
+        $screens = $this->createStub(ScreenDefinitionRepository::class);
+        $screens->method('findOneBy')->willReturn(null);
+        $entityVersions = $this->createStub(BuilderEntityVersionRepository::class);
+        $entityVersions->method('findByEntityCodeOrdered')->willReturn([$masterVersion]);
+
+        $entityManager = $this->createStub(EntityManagerInterface::class);
+        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$savedVersion, &$publishedProgram, &$publishedScreen): void {
+            if ($entity instanceof BuilderProgramVersion && $entity->getId() === null) {
+                $this->setEntityId($entity, 701);
+                $savedVersion = $entity;
+            }
+            if ($entity instanceof Program && $entity->getId() === null) {
+                $this->setEntityId($entity, 702);
+                $publishedProgram = $entity;
+            }
+            if ($entity instanceof ScreenDefinition && $entity->getId() === null) {
+                $this->setEntityId($entity, 703);
+                $publishedScreen = $entity;
+            }
+        });
+
+        $permissions = $this->createStub(PermissionResolver::class);
+        $permissions->method('hasPermission')->willReturn(true);
+
+        $service = new ProgramBuilderService(
+            $entities,
+            $this->createStub(BuilderApiSourceRepository::class),
+            $this->createStub(BuilderEditorLockRepository::class),
+            $modules,
+            $this->createStub(BuilderFieldRepository::class),
+            $entityVersions,
+            $versions,
+            $programs,
+            $screens,
+            $this->createStub(RuntimeEndpointRepository::class),
+            $entityManager,
+            $this->createStub(StructuralIntegrityService::class),
+            $this->createStub(ProgramGovernanceService::class),
+            $this->createStub(ProgramOverlayService::class),
+            $this->createStub(RuntimeNotificationService::class),
+            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
+            $permissions,
+            $this->createStub(RuntimeSessionGuard::class),
+            $this->createStub(OdooClient::class),
+            $this->createStub(RuntimeEventService::class),
+        );
+
+        $draft = $service->saveDraft($this->validProgramPayload());
+
+        self::assertSame('master_detail', $draft['pageType']);
+        self::assertSame('pedido_venda', $draft['builderEntityCode']);
+        self::assertSame('pedido_venda', $draft['builderConfig']['masterDetailConfig']['masterEntityCode']);
+        self::assertSame('master_detail', $draft['generatedDefinition']['pageType']);
+
+        $published = $service->publishVersion(701);
+
+        self::assertSame('vd0101', $published['program']['code']);
+        self::assertSame('vendas.pedidos', $published['program']['screenId']);
+        self::assertSame('master_detail', $published['program']['programType']);
+        self::assertInstanceOf(ScreenDefinition::class, $publishedScreen);
+        self::assertSame('master_detail', $publishedScreen->getPageType());
+        self::assertSame('vendas.pedidos', $publishedScreen->getScreenId());
+        self::assertSame('master_detail', $publishedScreen->getDefinition()['pageType']);
+        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $publishedScreen->getDefinition()['runtime']['traceability']['schemaFingerprint']);
+
+        $definitionWithoutMasterDetail = $publishedScreen->getDefinition();
+        unset($definitionWithoutMasterDetail['master'], $definitionWithoutMasterDetail['details'], $definitionWithoutMasterDetail['createFlow']);
+        self::assertNotSame(
+            $publishedScreen->getDefinition()['runtime']['traceability']['schemaFingerprint'],
+            $this->invokePrivateMixed($service, 'programSchemaFingerprint', [$savedVersion, $definitionWithoutMasterDetail])
+        );
+    }
+
     public function testGenerateMasterDetailDefinitionBuildsGraph(): void
     {
         $definition = $this->invokePrivateMixed($this->service(), 'generateMasterDetailDefinition', [[
             'pageType' => 'master_detail',
             'programCode' => 'vd0101',
             'programTitle' => 'Pedido de venda',
             'screenId' => 'vendas.pedidos',
             'module' => 'vendas',
             'permissionPrefix' => 'vendas.pedido',
             'version' => '1.0.0',
@@ -135,20 +254,35 @@ class ProgramBuilderServiceMasterDetailTest extends TestCase
             $this->createStub(OdooClient::class),
             $this->createStub(RuntimeEventService::class),
         );
     }
 
     private function validMasterDetailConfig(): array
     {
         return self::baseMasterDetailConfig();
     }
 
+    private function validProgramPayload(): array
+    {
+        return [
+            'programCode' => 'vd0101',
+            'programTitle' => 'Pedido de venda',
+            'module' => 'vendas',
+            'pageType' => 'master_detail',
+            'builderEntityCode' => 'pedido_venda',
+            'screenId' => 'vendas.pedidos',
+            'version' => '1.0.0',
+            'permissionPrefix' => 'vendas.pedido',
+            'masterDetailConfig' => $this->validMasterDetailConfig(),
+        ];
+    }
+
     private static function baseMasterDetailConfig(): array
     {
         return [
             'masterEntityCode' => 'pedido_venda',
             'createFlow' => [
                 'mode' => 'draftWithChildren',
                 'endpointId' => 'createGraph',
             ],
             'details' => [[
                 'entityCode' => 'pedido_item',
@@ -208,11 +342,18 @@ class ProgramBuilderServiceMasterDetailTest extends TestCase
             ->setPrimaryKey($primaryKey);
     }
 
     private function invokePrivateMixed(object $target, string $method, array $arguments): mixed
     {
         $reflection = new \ReflectionMethod($target, $method);
         $reflection->setAccessible(true);
 
         return $reflection->invokeArgs($target, $arguments);
     }
+
+    private function setEntityId(object $entity, int $id): void
+    {
+        $reflection = new \ReflectionProperty($entity, 'id');
+        $reflection->setAccessible(true);
+        $reflection->setValue($entity, $id);
+    }
 }

