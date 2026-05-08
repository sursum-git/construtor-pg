<?php

namespace App\Command;

use App\Admin\AdminCrudDefinitionFactory;
use App\Entity\AuthProviderConfig;
use App\Entity\AuthSubscriber;
use App\Entity\AuthUser;
use App\Entity\AuthUserSubscriber;
use App\Entity\BuilderEntity;
use App\Entity\BuilderEntitySituation;
use App\Entity\BuilderEntitySituationTransition;
use App\Entity\BuilderField;
use App\Entity\Cliente;
use App\Entity\Program;
use App\Entity\RuntimeEndpoint;
use App\Entity\RuntimeLockPolicy;
use App\Entity\ScreenDefinition;
use App\Entity\SystemParameter;
use App\Entity\SystemParameterValue;
use App\Repository\AuthProviderConfigRepository;
use App\Repository\AuthSubscriberRepository;
use App\Repository\AuthUserRepository;
use App\Repository\AuthUserSubscriberRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntitySituationRepository;
use App\Repository\BuilderEntitySituationTransitionRepository;
use App\Repository\BuilderFieldRepository;
use App\Repository\ClienteRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\RuntimeLockPolicyRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\SystemParameterValueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'app:seed-runtime-metadata', description: 'Carrega metadados iniciais e massa de clientes para o runtime.')]
class SeedRuntimeMetadataCommand extends Command
{
    private const CLIENT_ENDPOINTS = [
        'read' => 'entity.crud',
        'get' => 'entity.crud',
        'create' => 'entity.crud',
        'update' => 'entity.crud',
        'delete' => 'entity.crud',
        'validateStatusCliente' => 'cliente.validateStatusCliente',
        'loadCidadesByUf' => 'cliente.loadCidadesByUf',
        'statusHistory' => 'cliente.statusHistory',
        'stepHistory' => 'cliente.stepHistory',
        'printClienteExcel' => 'cliente.printClienteExcel',
        'printClientePdf' => 'cliente.printClientePdf',
        'printClienteCsv' => 'cliente.printClienteCsv',
        'checkCredit' => 'cliente.checkCredit',
        'sendWelcome' => 'cliente.sendWelcome',
        'sendWhatsapp' => 'runtime.job.enqueue',
        'bulkActivate' => 'cliente.bulkActivate',
        'bulkInactivate' => 'cliente.bulkInactivate',
        'bulkDelete' => 'cliente.bulkDelete',
        'saveLayout' => 'layout.save',
        'restoreLayout' => 'layout.restore',
        'saveSort' => 'layout.saveSort',
        'deleteSort' => 'layout.deleteSort',
        'saveGroup' => 'layout.saveGroup',
        'deleteGroup' => 'layout.deleteGroup',
        'saveFilter' => 'layout.saveFilter',
        'deleteFilter' => 'layout.deleteFilter',
        'saveMobileTemplate' => 'layout.saveMobileTemplate',
        'deleteMobileTemplate' => 'layout.deleteMobileTemplate',
        'help.markAsRead' => 'help.markAsRead',
    ];

    private const HOME_ENDPOINTS = [
        'home.chat.contacts' => 'home.chat.contacts',
        'home.chat.history' => 'home.chat.history',
        'home.chat.send' => 'home.chat.send',
        'home.support.onlineUsers' => 'home.support.onlineUsers',
        'home.support.history' => 'home.support.history',
        'home.support.send' => 'home.support.send',
        'home.support.createRequest' => 'home.support.createRequest',
        'home.support.requestStatus' => 'home.support.requestStatus',
        'home.aiChat.history' => 'home.aiChat.history',
        'home.aiChat.send' => 'home.aiChat.send',
        'home.alerts.list' => 'home.alerts.list',
        'home.requests.list' => 'home.requests.list',
        'home.subscriber.change' => 'home.subscriber.change',
    ];

    private const JOB_ENDPOINTS = [
        'read' => 'entity.crud',
        'get' => 'entity.crud',
        'saveLayout' => 'layout.save',
        'restoreLayout' => 'layout.restore',
        'saveSort' => 'layout.saveSort',
        'deleteSort' => 'layout.deleteSort',
        'saveGroup' => 'layout.saveGroup',
        'deleteGroup' => 'layout.deleteGroup',
        'saveFilter' => 'layout.saveFilter',
        'deleteFilter' => 'layout.deleteFilter',
        'saveMobileTemplate' => 'layout.saveMobileTemplate',
        'deleteMobileTemplate' => 'layout.deleteMobileTemplate',
    ];

    private const SYSTEM_ENDPOINTS = [
        'runtime.lock.acquire' => 'runtime.lock.acquire',
        'runtime.lock.heartbeat' => 'runtime.lock.heartbeat',
        'runtime.lock.release' => 'runtime.lock.release',
        'runtime.messages.poll' => 'runtime.messages.poll',
        'runtime.messages.ack' => 'runtime.messages.ack',
        'runtime.admin.forceLogout' => 'runtime.admin.forceLogout',
    ];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProgramRepository $programs,
        private readonly BuilderEntityRepository $builderEntities,
        private readonly BuilderEntitySituationRepository $builderSituations,
        private readonly BuilderEntitySituationTransitionRepository $builderSituationTransitions,
        private readonly BuilderFieldRepository $builderFields,
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEndpointRepository $endpoints,
        private readonly RuntimeLockPolicyRepository $lockPolicies,
        private readonly ClienteRepository $clientes,
        private readonly AuthProviderConfigRepository $authProviders,
        private readonly AuthUserRepository $authUsers,
        private readonly AuthSubscriberRepository $authSubscribers,
        private readonly AuthUserSubscriberRepository $authUserSubscribers,
        private readonly SystemParameterRepository $systemParameters,
        private readonly SystemParameterValueRepository $systemParameterValues,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = dirname($this->kernel->getProjectDir());

        $clientesDefinition = $this->readJson($projectRoot . '/examples/clientes.crud.json');
        $jobsDefinition = $this->readJson($projectRoot . '/examples/runtime-jobs.crud.json');
        $homeDefinition = $this->readJson($projectRoot . '/examples/home.home.json');

        $clientesDefinition['screenId'] = 'cadastros.clientes';
        $clientesDefinition['program']['screenId'] = 'cadastros.clientes';
        $jobsDefinition['screenId'] = 'admin.jobs';
        $jobsDefinition['program']['screenId'] = 'admin.jobs';
        $homeDefinition['screenId'] = 'home';
        $homeDefinition['app']['id'] = 'home';
        $adminScreens = AdminCrudDefinitionFactory::screens();
        $this->attachRuntimeJobsProgramToHome($homeDefinition);
        $this->attachAdminProgramsToHome($homeDefinition, $adminScreens);

        foreach (($homeDefinition['programs'] ?? []) as $index => $program) {
            if (($program['id'] ?? '') === 'clientes-crud') {
                $homeDefinition['programs'][$index]['screenId'] = 'cadastros.clientes';
                unset($homeDefinition['programs'][$index]['definitionUrl'], $homeDefinition['programs'][$index]['openUrl']);
            }
        }

        $this->upsertProgram('cadastros.clientes', 'Clientes', 'cadastros', 'crud', 'cadastros.clientes');
        $this->upsertProgram('runtime-jobs', 'Jobs Assincronos', 'administracao', 'crud', 'admin.jobs');
        $this->upsertProgram('home', 'Home', 'global', 'home', 'home');
        foreach ($adminScreens as $screen) {
            $this->upsertProgram((string) $screen['programId'], (string) $screen['title'], 'administracao', 'crud', (string) $screen['screenId']);
        }
        $this->upsertBuilderEntityFromDefinition($clientesDefinition);
        $this->upsertRuntimeJobBuilderEntityFromDefinition($jobsDefinition);
        foreach ($adminScreens as $screen) {
            $this->upsertAdminBuilderEntityFromDefinition($screen);
        }
        $this->upsertScreen('cadastros.clientes', 'crud', $clientesDefinition);
        $this->upsertScreen('admin.jobs', 'crud', $jobsDefinition);
        foreach ($adminScreens as $screen) {
            $this->upsertScreen((string) $screen['screenId'], 'crud', $screen['definition']);
        }
        $this->upsertScreen('home', 'home', $homeDefinition);
        $this->upsertEndpoints('cadastros.clientes', array_merge(self::CLIENT_ENDPOINTS, self::SYSTEM_ENDPOINTS));
        $this->upsertEndpoints('admin.jobs', array_merge(self::JOB_ENDPOINTS, self::SYSTEM_ENDPOINTS));
        foreach ($adminScreens as $screen) {
            $this->upsertEndpoints((string) $screen['screenId'], $this->adminEndpointHandlers($screen));
        }
        $this->upsertEndpoints('home', array_merge(self::HOME_ENDPOINTS, self::SYSTEM_ENDPOINTS));
        $this->upsertDefaultLockPolicies();
        $this->upsertAuthDefaults();
        $this->upsertSubscriberDefaults();
        $this->upsertSystemParameters();
        $this->seedClientes();
        $this->seedClienteTelefones();

        $this->entityManager->flush();

        $io->success('Metadados e dados iniciais carregados.');
        return Command::SUCCESS;
    }

    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Arquivo JSON não encontrado: ' . $path);
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new \RuntimeException('JSON inválido: ' . $path);
        }

        return $payload;
    }

    private function upsertProgram(string $code, string $title, string $module, string $type, string $screenId): void
    {
        $program = $this->programs->findOneBy(['code' => $code]) ?? new Program();
        $program
            ->setCode($code)
            ->setTitle($title)
            ->setModule($module)
            ->setProgramType($type)
            ->setScreenId($screenId)
            ->setStatus('published');

        $this->entityManager->persist($program);
    }

    private function upsertBuilderEntityFromDefinition(array $definition): void
    {
        $entity = $this->builderEntities->findOneBy(['code' => 'cliente']) ?? new BuilderEntity();
        $situationConfig = is_array($definition['crud']['form']['situation'] ?? null) ? $definition['crud']['form']['situation'] : [];
        $entity
            ->setCode('cliente')
            ->setName('Cliente')
            ->setEntityType('persistence')
            ->setTableName('cliente')
            ->setStatus('published')
            ->setSituationEnabled(($situationConfig['enabled'] ?? false) !== false)
            ->setSituationFieldCode((string) ($situationConfig['field'] ?? 'status'))
            ->setMetadata([
                'screenId' => 'cadastros.clientes',
                'primaryKey' => $definition['dataModel']['primaryKey'] ?? 'id',
                'situation' => [
                    'enabled' => ($situationConfig['enabled'] ?? false) !== false,
                    'field' => (string) ($situationConfig['field'] ?? 'status'),
                    'initial' => 'ATIVO',
                ],
                'audit' => [
                    'doctrineClass' => Cliente::class,
                ],
                'rules' => [
                    [
                        'type' => 'requiredWhen',
                        'field' => 'observacao',
                        'when' => [
                            'field' => 'status',
                            'equals' => 'INATIVO',
                        ],
                        'message' => 'Observacao e obrigatoria para cliente inativo.',
                    ],
                ],
                'jobs' => [
                    [
                        'id' => 'cliente-email-confirmation',
                        'type' => 'cliente.email_confirmation',
                        'trigger' => 'after_success',
                        'mode' => 'async',
                        'enabled' => true,
                        'operations' => ['create'],
                        'when' => [
                            'source' => 'after',
                            'field' => 'email',
                            'operator' => 'isEmail',
                        ],
                        'payload' => [
                            'clienteId' => 'after.id',
                            'nome' => 'after.nome',
                            'email' => 'after.email',
                        ],
                        'queuedMessage' => 'E-mail de confirmacao agendado.',
                    ],
                ],
            ]);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->upsertClienteSituations($entity, $situationConfig);

        $position = 0;
        $configuredCodes = [];
        foreach (($definition['dataModel']['fields'] ?? []) as $code => $config) {
            $configuredCodes[(string) $code] = true;
            $field = $this->builderFields->findOneBy([
                'builderEntity' => $entity,
                'code' => $code,
            ]) ?? new BuilderField();
            $field
                ->setBuilderEntity($entity)
                ->setCode((string) $code)
                ->setLabel((string) ($config['label'] ?? $code))
                ->setDataType((string) ($config['type'] ?? 'string'))
                ->setDatabaseType($this->guessDatabaseType((string) ($config['type'] ?? 'string')))
                ->setRequired((bool) (($config['validation']['required'] ?? false) || ($config['nullable'] ?? true) === false))
                ->setPrimaryKey($code === ($definition['dataModel']['primaryKey'] ?? 'id'))
                ->setPosition($position++)
                ->setOptions($config);

            $this->entityManager->persist($field);
        }
        foreach ($entity->getFields() as $existingField) {
            if (!isset($configuredCodes[$existingField->getCode()])) {
                $this->entityManager->remove($existingField);
            }
        }
    }

    private function upsertClienteSituations(BuilderEntity $entity, array $situationConfig): void
    {
        $steps = is_array($situationConfig['steps'] ?? null) ? $situationConfig['steps'] : [];
        if (!$steps) {
            $steps = [
                ['value' => 'ATIVO', 'text' => 'Ativo', 'description' => 'Cliente liberado para operacao.'],
                ['value' => 'INATIVO', 'text' => 'Inativo', 'description' => 'Cliente bloqueado ou pausado.'],
            ];
        }

        foreach ($steps as $position => $step) {
            if (!is_array($step) || empty($step['value'])) {
                continue;
            }
            $code = (string) $step['value'];
            $situation = $this->builderSituations->findOneBy([
                'builderEntity' => $entity,
                'code' => $code,
            ]) ?? new BuilderEntitySituation();
            $situation
                ->setBuilderEntity($entity)
                ->setCode($code)
                ->setLabel((string) ($step['text'] ?? $code))
                ->setDescription(isset($step['description']) ? (string) $step['description'] : null)
                ->setPosition((int) $position)
                ->setInitial($code === 'ATIVO' || $position === 0)
                ->setFinal($code === 'INATIVO')
                ->setEnabled(true)
                ->setMetadata([
                    'source' => 'crud.form.situation.steps',
                ]);
            $this->entityManager->persist($situation);
        }

        $this->upsertSituationTransition($entity, null, 'ATIVO', 'create', 'Criar cliente ativo', 0, [], [
            [
                'action' => 'showMessage',
                'type' => 'success',
                'message' => 'Cliente criado como ativo.',
            ],
        ]);
        $this->upsertSituationTransition($entity, null, 'INATIVO', 'create', 'Criar cliente inativo', 1, [
            'requiredFields' => [
                [
                    'field' => 'observacao',
                    'message' => 'Informe a observacao para criar cliente inativo.',
                ],
            ],
        ]);
        $this->upsertSituationTransition($entity, 'ATIVO', 'INATIVO', 'update', 'Inativar cliente', 2, [
            'requiredFields' => [
                [
                    'field' => 'observacao',
                    'message' => 'Informe a observacao para inativar o cliente.',
                ],
            ],
        ]);
        $this->upsertSituationTransition($entity, 'INATIVO', 'ATIVO', 'update', 'Reativar cliente', 3, [], [
            [
                'action' => 'showMessage',
                'type' => 'success',
                'message' => 'Cliente reativado.',
            ],
        ]);
    }

    private function upsertSituationTransition(
        BuilderEntity $entity,
        ?string $from,
        string $to,
        string $actionId,
        string $label,
        int $position,
        array $guardConfig = [],
        array $effects = [],
    ): void {
        $transition = $this->builderSituationTransitions->findOneBy([
            'builderEntity' => $entity,
            'fromCode' => $from,
            'toCode' => $to,
            'actionId' => $actionId,
        ]) ?? new BuilderEntitySituationTransition();
        $transition
            ->setBuilderEntity($entity)
            ->setFromCode($from)
            ->setToCode($to)
            ->setActionId($actionId)
            ->setLabel($label)
            ->setPosition($position)
            ->setEnabled(true)
            ->setGuardConfig($guardConfig)
            ->setEffects($effects)
            ->setMetadata([
                'source' => 'seed-runtime-metadata',
            ]);

        $this->entityManager->persist($transition);
    }

    private function upsertRuntimeJobBuilderEntityFromDefinition(array $definition): void
    {
        $entity = $this->builderEntities->findOneBy(['code' => 'runtime_async_job']) ?? new BuilderEntity();
        $entity
            ->setCode('runtime_async_job')
            ->setName('Job Assincrono')
            ->setEntityType('persistence')
            ->setTableName('runtime_async_job')
            ->setStatus('published')
            ->setMetadata([
                'screenId' => 'admin.jobs',
                'primaryKey' => $definition['dataModel']['primaryKey'] ?? 'id',
                'audit' => [
                    'enabled' => false,
                ],
            ]);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $position = 0;
        $configuredCodes = [];
        foreach (($definition['dataModel']['fields'] ?? []) as $code => $config) {
            $configuredCodes[(string) $code] = true;
            $field = $this->builderFields->findOneBy([
                'builderEntity' => $entity,
                'code' => $code,
            ]) ?? new BuilderField();
            $field
                ->setBuilderEntity($entity)
                ->setCode((string) $code)
                ->setLabel((string) ($config['label'] ?? $code))
                ->setDataType((string) ($config['type'] ?? 'string'))
                ->setDatabaseType($this->guessDatabaseType((string) ($config['type'] ?? 'string')))
                ->setRequired((bool) (($config['validation']['required'] ?? false) || ($config['nullable'] ?? true) === false))
                ->setPrimaryKey($code === ($definition['dataModel']['primaryKey'] ?? 'id'))
                ->setPosition($position++)
                ->setOptions(array_merge($config, [
                    'writable' => false,
                    'editable' => false,
                    'audit' => false,
                ]));

            $this->entityManager->persist($field);
        }
        foreach ($entity->getFields() as $existingField) {
            if (!isset($configuredCodes[$existingField->getCode()])) {
                $this->entityManager->remove($existingField);
            }
        }
    }

    private function upsertAdminBuilderEntityFromDefinition(array $screen): void
    {
        $definition = $screen['definition'];
        $entityCode = (string) $screen['entityCode'];
        $entity = $this->builderEntities->findOneBy(['code' => $entityCode]) ?? new BuilderEntity();
        $entity
            ->setCode($entityCode)
            ->setName((string) $screen['entityName'])
            ->setEntityType('persistence')
            ->setTableName((string) $screen['tableName'])
            ->setStatus('published')
            ->setSituationEnabled(false)
            ->setSituationFieldCode(null)
            ->setMetadata([
                'screenId' => $screen['screenId'],
                'primaryKey' => $definition['dataModel']['primaryKey'] ?? 'id',
                'audit' => [
                    'enabled' => false,
                ],
            ]);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $position = 0;
        $configuredCodes = [];
        foreach (($definition['dataModel']['fields'] ?? []) as $code => $config) {
            $configuredCodes[(string) $code] = true;
            $field = $this->builderFields->findOneBy([
                'builderEntity' => $entity,
                'code' => $code,
            ]) ?? new BuilderField();
            $field
                ->setBuilderEntity($entity)
                ->setCode((string) $code)
                ->setLabel((string) ($config['label'] ?? $code))
                ->setDataType((string) ($config['type'] ?? 'string'))
                ->setDatabaseType($this->guessDatabaseType((string) ($config['type'] ?? 'string')))
                ->setRequired((bool) (($config['validation']['required'] ?? false) || ($config['nullable'] ?? true) === false))
                ->setPrimaryKey($code === ($definition['dataModel']['primaryKey'] ?? 'id'))
                ->setPosition($position++)
                ->setOptions($config);

            $this->entityManager->persist($field);
        }
        foreach ($entity->getFields() as $existingField) {
            if (!isset($configuredCodes[$existingField->getCode()])) {
                $this->entityManager->remove($existingField);
            }
        }
    }

    private function guessDatabaseType(string $type): string
    {
        return match ($type) {
            'integer' => 'integer',
            'decimal', 'number' => 'numeric',
            'date' => 'date',
            'datetime' => 'timestamp',
            'json' => 'json',
            'text' => 'text',
            default => 'varchar',
        };
    }

    private function attachRuntimeJobsProgramToHome(array &$homeDefinition): void
    {
        $navigation = is_array($homeDefinition['navigation'] ?? null) ? $homeDefinition['navigation'] : [];
        $modules = is_array($navigation['modules'] ?? null) ? $navigation['modules'] : [];
        if (!$this->containsById($modules, 'administracao')) {
            $modules[] = [
                'id' => 'administracao',
                'title' => 'Administracao',
            ];
        }

        $groups = is_array($navigation['groups'] ?? null) ? $navigation['groups'] : [];
        $groupIndex = null;
        foreach ($groups as $index => $group) {
            if (($group['id'] ?? '') === 'runtime') {
                $groupIndex = $index;
                break;
            }
        }
        if ($groupIndex === null) {
            $groups[] = [
                'id' => 'runtime',
                'title' => 'Runtime',
                'moduleId' => 'administracao',
                'items' => [],
            ];
            $groupIndex = count($groups) - 1;
        }
        $items = is_array($groups[$groupIndex]['items'] ?? null) ? $groups[$groupIndex]['items'] : [];
        if (!$this->containsByProgramId($items, 'runtime-jobs')) {
            $items[] = [
                'programId' => 'runtime-jobs',
                'title' => 'Jobs Assincronos',
            ];
        }
        $groups[$groupIndex]['items'] = $items;
        $navigation['modules'] = $modules;
        $navigation['groups'] = $groups;
        $homeDefinition['navigation'] = $navigation;

        $programs = is_array($homeDefinition['programs'] ?? null) ? $homeDefinition['programs'] : [];
        if (!$this->containsById($programs, 'runtime-jobs')) {
            $programs[] = [
                'id' => 'runtime-jobs',
                'title' => 'Jobs Assincronos',
                'subtitle' => 'Consulta das acoes executadas por fila',
                'type' => 'crud',
                'icon' => 'gear',
                'permission' => 'runtime.jobs.read',
                'screenId' => 'admin.jobs',
            ];
        }
        $homeDefinition['programs'] = $programs;
    }

    private function attachAdminProgramsToHome(array &$homeDefinition, array $screens): void
    {
        $navigation = is_array($homeDefinition['navigation'] ?? null) ? $homeDefinition['navigation'] : [];
        $modules = is_array($navigation['modules'] ?? null) ? $navigation['modules'] : [];
        if (!$this->containsById($modules, 'administracao')) {
            $modules[] = [
                'id' => 'administracao',
                'title' => 'Administracao',
            ];
        }

        $groups = is_array($navigation['groups'] ?? null) ? $navigation['groups'] : [];
        $groupIndex = null;
        foreach ($groups as $index => $group) {
            if (($group['id'] ?? '') === 'admin-runtime') {
                $groupIndex = $index;
                break;
            }
        }
        if ($groupIndex === null) {
            $groups[] = [
                'id' => 'admin-runtime',
                'title' => 'Administracao',
                'moduleId' => 'administracao',
                'items' => [],
            ];
            $groupIndex = count($groups) - 1;
        }

        $items = is_array($groups[$groupIndex]['items'] ?? null) ? $groups[$groupIndex]['items'] : [];
        foreach ($screens as $screen) {
            if (!$this->containsByProgramId($items, (string) $screen['programId'])) {
                $items[] = [
                    'programId' => (string) $screen['programId'],
                    'title' => (string) $screen['title'],
                ];
            }
        }
        $groups[$groupIndex]['items'] = $items;
        $navigation['modules'] = $modules;
        $navigation['groups'] = $groups;
        $homeDefinition['navigation'] = $navigation;

        $programs = is_array($homeDefinition['programs'] ?? null) ? $homeDefinition['programs'] : [];
        foreach ($screens as $screen) {
            if ($this->containsById($programs, (string) $screen['programId'])) {
                continue;
            }
            $programs[] = [
                'id' => (string) $screen['programId'],
                'title' => (string) $screen['title'],
                'subtitle' => (string) (($screen['definition']['program']['subtitle'] ?? '') ?: 'Administracao do runtime'),
                'type' => 'crud',
                'icon' => 'gear',
                'permission' => 'admin.read',
                'screenId' => (string) $screen['screenId'],
            ];
        }
        $homeDefinition['programs'] = $programs;
    }

    private function containsById(array $items, string $id): bool
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    private function containsByProgramId(array $items, string $programId): bool
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['programId'] ?? '') === $programId) {
                return true;
            }
        }

        return false;
    }

    private function upsertScreen(string $screenId, string $pageType, array $definition): void
    {
        $screen = $this->screens->findOneBy(['screenId' => $screenId]) ?? new ScreenDefinition();
        $screen
            ->setScreenId($screenId)
            ->setPageType($pageType)
            ->setSchemaVersion((string) ($definition['schemaVersion'] ?? '1.0'))
            ->setDefinition($definition)
            ->setStatus('published')
            ->setVersion('1.0.0');

        $this->entityManager->persist($screen);
    }

    private function upsertEndpoints(string $screenId, array $handlers): void
    {
        foreach ($handlers as $endpointId => $handler) {
            $endpoint = $this->endpoints->findOneBy([
                'screenId' => $screenId,
                'endpointId' => $endpointId,
            ]) ?? new RuntimeEndpoint();
            $endpoint
                ->setScreenId($screenId)
                ->setEndpointId((string) $endpointId)
                ->setHandler((string) $handler)
                ->setPermission($this->endpointPermission((string) $screenId, (string) $endpointId, (string) $handler))
                ->setEnabled(true)
                ->setConfig($this->endpointConfig((string) $screenId, (string) $endpointId, (string) $handler));

            $this->entityManager->persist($endpoint);
        }
    }

    private function endpointPermission(string $screenId, string $endpointId, string $handler): ?string
    {
        if (str_starts_with($endpointId, 'runtime.messages.') || str_starts_with($endpointId, 'runtime.lock.')) {
            return null;
        }
        if ($endpointId === 'runtime.admin.forceLogout') {
            return 'admin.sessions.revoke';
        }
        if (str_starts_with($handler, 'layout.')) {
            return 'user.preferences';
        }
        if ($handler === 'help.markAsRead') {
            return 'user.preferences';
        }
        if (str_starts_with($handler, 'home.')) {
            return 'home.read';
        }
        if ($screenId === 'cadastros.clientes') {
            return match ($endpointId) {
                'read', 'get', 'statusHistory', 'stepHistory', 'printClienteExcel', 'printClientePdf', 'printClienteCsv', 'loadCidadesByUf' => 'clientes.read',
                'create' => 'clientes.create',
                'update', 'validateStatusCliente', 'checkCredit', 'sendWelcome', 'sendWhatsapp', 'bulkActivate', 'bulkInactivate' => 'clientes.edit',
                'delete', 'bulkDelete' => 'clientes.delete',
                default => null,
            };
        }
        if ($screenId === 'admin.jobs') {
            return in_array($endpointId, ['read', 'get'], true) ? 'runtime.jobs.read' : null;
        }
        if ($this->adminScreen($screenId)) {
            return match ($endpointId) {
                'read', 'get' => 'admin.read',
                'create', 'update', 'delete' => 'admin.write',
                default => null,
            };
        }

        return null;
    }

    private function endpointConfig(string $screenId, string $endpointId, string $handler): array
    {
        if ($screenId === 'cadastros.clientes' && $handler === 'entity.crud') {
            return [
                'entityCode' => 'cliente',
                'operation' => $endpointId,
                'actionId' => $endpointId,
                'programId' => 'clientes-crud',
            ];
        }
        if ($screenId === 'cadastros.clientes' && $endpointId === 'sendWhatsapp') {
            return [
                'entityCode' => 'cliente',
                'actionId' => 'sendWhatsapp',
                'programId' => 'clientes-crud',
                'jobs' => [
                    [
                        'id' => 'cliente-whatsapp-welcome',
                        'type' => 'cliente.whatsapp_welcome',
                        'mode' => 'async',
                        'enabled' => true,
                        'required' => [
                            [
                                'path' => 'values.telefone',
                                'field' => 'telefone',
                                'message' => 'Informe o telefone do cliente para enviar WhatsApp.',
                            ],
                        ],
                        'payload' => [
                            'clienteId' => 'record.id',
                            'nome' => 'record.nome',
                            'telefone' => 'values.telefone',
                        ],
                        'queuedMessage' => 'WhatsApp agendado.',
                    ],
                ],
            ];
        }
        if ($screenId === 'admin.jobs' && $handler === 'entity.crud') {
            return [
                'entityCode' => 'runtime_async_job',
                'operation' => $endpointId,
                'actionId' => $endpointId,
                'programId' => 'runtime-jobs',
            ];
        }
        $adminScreen = $this->adminScreen($screenId);
        if ($adminScreen && $handler === 'entity.crud') {
            return [
                'entityCode' => (string) $adminScreen['entityCode'],
                'operation' => $endpointId,
                'actionId' => $endpointId,
                'programId' => (string) $adminScreen['programId'],
            ];
        }

        return [];
    }

    private function adminEndpointHandlers(array $screen): array
    {
        $api = $screen['definition']['dataSource']['api'] ?? [];
        $handlers = [];
        foreach (array_keys($api) as $endpointId) {
            $handlers[$endpointId] = match ($endpointId) {
                'read', 'get', 'create', 'update', 'delete' => 'entity.crud',
                'saveLayout' => 'layout.save',
                'restoreLayout' => 'layout.restore',
                'saveSort' => 'layout.saveSort',
                'deleteSort' => 'layout.deleteSort',
                'saveGroup' => 'layout.saveGroup',
                'deleteGroup' => 'layout.deleteGroup',
                'saveFilter' => 'layout.saveFilter',
                'deleteFilter' => 'layout.deleteFilter',
                'saveMobileTemplate' => 'layout.saveMobileTemplate',
                'deleteMobileTemplate' => 'layout.deleteMobileTemplate',
                'runtime.lock.acquire' => 'runtime.lock.acquire',
                'runtime.lock.heartbeat' => 'runtime.lock.heartbeat',
                'runtime.lock.release' => 'runtime.lock.release',
                'runtime.messages.poll' => 'runtime.messages.poll',
                'runtime.messages.ack' => 'runtime.messages.ack',
                'runtime.admin.forceLogout' => 'runtime.admin.forceLogout',
                default => 'entity.crud',
            };
        }

        return $handlers;
    }

    private function adminScreen(string $screenId): ?array
    {
        $screens = AdminCrudDefinitionFactory::screens();

        return $screens[$screenId] ?? null;
    }

    private function seedClientes(): void
    {
        if ($this->clientes->count([]) > 0) {
            return;
        }

        $rows = [
            ['Ana Comércio LTDA', 'ana@empresa.test', 'ATIVO', 'PJ', 'CE', 'Fortaleza', 'Ana Comércio LTDA', '12.345.678/0001-90', 12500.75, 18],
            ['Bruno Silva', 'bruno@email.test', 'ATIVO', 'PF', 'SP', 'Campinas', null, null, 3200.00, 4],
            ['Carla Serviços', 'carla@servicos.test', 'INATIVO', 'PJ', 'RJ', 'Niterói', 'Carla Serviços SA', '98.765.432/0001-10', 75200.30, 42],
            ['Delta Distribuidora', 'contato@delta.test', 'ATIVO', 'PJ', 'CE', 'Sobral', 'Delta Distribuidora LTDA', '11.111.111/0001-11', 44100.00, 31],
        ];

        foreach ($rows as $row) {
            $cliente = (new Cliente())
                ->setNome($row[0])
                ->setEmail($row[1])
                ->setStatus($row[2])
                ->setTipoPessoa($row[3])
                ->setUf($row[4])
                ->setCidade($row[5])
                ->setRazaoSocial($row[6])
                ->setCnpj($row[7])
                ->setValorTotal($row[8])
                ->setQtdePedidos($row[9]);
            $this->entityManager->persist($cliente);
        }
    }

    private function seedClienteTelefones(): void
    {
        $telefones = [
            'ana@empresa.test' => '(85) 98888-1001',
            'bruno@email.test' => '(11) 97777-2002',
            'carla@servicos.test' => '(21) 96666-3003',
            'contato@delta.test' => '(85) 95555-4004',
        ];

        foreach ($telefones as $email => $telefone) {
            $cliente = $this->clientes->findOneBy(['email' => $email]);
            if ($cliente && $cliente->getTelefone() === null) {
                $cliente->setTelefone($telefone);
                $this->entityManager->persist($cliente);
            }
        }
    }

    private function upsertDefaultLockPolicies(): void
    {
        $this->upsertLockPolicy('cliente', null, null, 'block', 'block', 300, 60);
        $this->upsertLockPolicy('cliente', 'clientes-crud', 'update', 'block', 'block', 300, 60);
        $this->upsertLockPolicy('cliente', 'clientes-crud', 'delete', 'block', 'block', 300, 60);
    }

    private function upsertAuthDefaults(): void
    {
        $this->upsertAuthProvider('local', 'local', 'Usuario e senha', true, 10, [
            'description' => 'Autenticacao por senha gravada em auth_user.',
        ]);
        $this->upsertAuthProvider('ldap', 'ldap', 'LDAP', false, 20, [
            'host' => '',
            'port' => 389,
            'baseDn' => '',
            'usernameAttribute' => 'uid',
            'displayNameAttribute' => 'displayName',
            'emailAttribute' => 'mail',
            'groupsAttribute' => 'memberOf',
        ]);
        $this->upsertAuthProvider('sso', 'sso', 'SSO corporativo', false, 30, [
            'userHeader' => 'X-Forwarded-User',
            'nameHeader' => 'X-Forwarded-Name',
            'emailHeader' => 'X-Forwarded-Email',
            'groupsHeader' => 'X-Forwarded-Groups',
        ]);
        $this->upsertAuthProvider('oauth', 'oauth', 'OAuth/OIDC', false, 40, [
            'authorizationUrl' => '',
            'tokenUrl' => '',
            'userInfoUrl' => '',
            'clientId' => '',
            'scope' => 'openid profile email',
            'userIdClaim' => 'sub',
            'usernameClaim' => 'preferred_username',
            'nameClaim' => 'name',
            'emailClaim' => 'email',
            'groupsClaim' => 'groups',
        ]);

        $admin = $this->authUsers->findOneByTenantAndUsername('default', 'admin') ?? new AuthUser();
        $admin
            ->setTenantId('default')
            ->setUsername('admin')
            ->setDisplayName('Administrador')
            ->setEmail('admin@example.com')
            ->setStatus('active')
            ->setGroups(['admin'])
            ->setPermissions(['*'])
            ->setAuthSource('local')
            ->setForcePasswordChange(false);
        if (!$admin->getPasswordHash()) {
            $admin->setPasswordHash(password_hash('admin123', PASSWORD_DEFAULT));
        }
        $this->entityManager->persist($admin);
    }

    private function upsertSubscriberDefaults(): void
    {
        $this->upsertSubscriber('default', 'Principal', null, true);
        $this->upsertSubscriber('empresa-a', 'Empresa A', '00.000.000/0001-00', false);
        $this->upsertSubscriber('empresa-b', 'Empresa B', '11.111.111/0001-11', false);

        $this->upsertUserSubscriber('default', 'admin', 'default', true);
        $this->upsertUserSubscriber('default', 'admin', 'empresa-a', false);
        $this->upsertUserSubscriber('default', 'admin', 'empresa-b', false);
    }

    private function upsertSubscriber(string $code, string $name, ?string $document, bool $principal): void
    {
        $subscriber = $this->authSubscribers->findOneBy(['code' => $code]) ?? new AuthSubscriber();
        $subscriber
            ->setCode($code)
            ->setName($name)
            ->setDocument($document)
            ->setPrincipal($principal)
            ->setEnabled(true)
            ->setMetadata([
                'source' => 'seed-runtime-metadata',
            ]);

        $this->entityManager->persist($subscriber);
    }

    private function upsertUserSubscriber(string $userTenantId, string $username, string $subscriberCode, bool $default): void
    {
        $access = $this->authUserSubscribers->findOneBy([
            'userTenantId' => $userTenantId,
            'username' => mb_strtolower($username),
            'subscriberCode' => $subscriberCode,
        ]) ?? new AuthUserSubscriber();
        $access
            ->setUserTenantId($userTenantId)
            ->setUsername($username)
            ->setSubscriberCode($subscriberCode)
            ->setDefaultSubscriber($default)
            ->setEnabled(true)
            ->setPermissionOverrides([])
            ->setMetadata([
                'source' => 'seed-runtime-metadata',
            ]);

        $this->entityManager->persist($access);
    }

    private function upsertAuthProvider(string $code, string $type, string $name, bool $enabled, int $priority, array $config): void
    {
        $provider = $this->authProviders->findOneBy(['code' => $code]) ?? new AuthProviderConfig();
        $provider
            ->setCode($code)
            ->setType($type)
            ->setName($name)
            ->setEnabled($enabled)
            ->setPriority($priority)
            ->setConfig($config);

        $this->entityManager->persist($provider);
    }

    private function upsertSystemParameters(): void
    {
        $parameter = $this->systemParameters->findOneBy(['code' => 'subscriber.enabled']) ?? new SystemParameter();
        $parameter
            ->setCode('subscriber.enabled')
            ->setName('Habilitar conceito de assinante')
            ->setDescription('Controla se o login deve selecionar assinante/tenant apos autenticacao.')
            ->setDataType('boolean')
            ->setOptionList(null)
            ->setRequired(true)
            ->setDefaultValue(false)
            ->setEnabled(true);

        $this->entityManager->persist($parameter);
        $this->entityManager->flush();

        $value = $this->systemParameterValues->findOneBy([
            'parameter' => $parameter,
            'establishmentCode' => null,
        ]) ?? $this->systemParameterValues->findOneBy([
            'parameter' => $parameter,
            'establishmentCode' => '',
        ]) ?? new SystemParameterValue();
        $value
            ->setParameter($parameter)
            ->setEstablishmentCode(null)
            ->setStartsAt(new \DateTimeImmutable('2000-01-01 00:00:00'))
            ->setEndsAt(null)
            ->setValue(false)
            ->setEnabled(true);

        $this->entityManager->persist($value);
    }

    private function upsertLockPolicy(
        ?string $entityCode,
        ?string $programId,
        ?string $actionId,
        string $mode,
        string $stalePolicy,
        int $ttl,
        int $heartbeat,
    ): void {
        $policy = $this->lockPolicies->findOneBy([
            'tenantId' => null,
            'programId' => $programId,
            'entityCode' => $entityCode,
            'actionId' => $actionId,
        ]) ?? new RuntimeLockPolicy();

        $policy
            ->setTenantId(null)
            ->setProgramId($programId)
            ->setEntityCode($entityCode)
            ->setActionId($actionId)
            ->setMode($mode)
            ->setStalePolicy($stalePolicy)
            ->setLockTtlSeconds($ttl)
            ->setHeartbeatIntervalSeconds($heartbeat)
            ->setEnabled(true)
            ->setConditionConfig([]);

        $this->entityManager->persist($policy);
    }
}
