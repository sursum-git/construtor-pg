<?php

namespace App\Command;

use App\Admin\AdminCrudDefinitionFactory;
use App\Entity\AuthProviderConfig;
use App\Entity\AuthSubscriber;
use App\Entity\AuthUser;
use App\Entity\AuthUserSubscriber;
use App\Entity\BuilderEntity;
use App\Entity\BuilderModule;
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
use App\Entity\SystemLiteralTranslation;
use App\Repository\AuthProviderConfigRepository;
use App\Repository\AuthSubscriberRepository;
use App\Repository\AuthUserRepository;
use App\Repository\AuthUserSubscriberRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderModuleRepository;
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
use App\Repository\SystemLiteralTranslationRepository;
use App\Runtime\CentralControlResolver;
use App\Runtime\StructuralIntegrityService;
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
        'home.chat.events' => 'home.chat.events',
        'home.support.onlineUsers' => 'home.support.onlineUsers',
        'home.support.history' => 'home.support.history',
        'home.support.send' => 'home.support.send',
        'home.support.createRequest' => 'home.support.createRequest',
        'home.support.requestStatus' => 'home.support.requestStatus',
        'home.support.events' => 'home.support.events',
        'home.aiChat.history' => 'home.aiChat.history',
        'home.aiChat.send' => 'home.aiChat.send',
        'home.notifications.list' => 'home.notifications.list',
        'home.notifications.ack' => 'home.notifications.ack',
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
        'runtime.admin.integrity.resign' => 'runtime.admin.integrity.resign',
    ];

    private const PROCESS_ENDPOINTS = [
        'process' => 'process.clientes.start',
        'status' => 'process.clientes.status',
    ];

    private const CUSTOM_CODE_PDM_ENDPOINTS = [
        'process' => 'process.customCode.pdm',
    ];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProgramRepository $programs,
        private readonly BuilderEntityRepository $builderEntities,
        private readonly BuilderModuleRepository $builderModules,
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
        private readonly SystemLiteralTranslationRepository $systemLiteralTranslations,
        private readonly CentralControlResolver $central,
        private readonly StructuralIntegrityService $integrity,
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
        $processDefinition = $this->readJson($projectRoot . '/examples/processamento-relatorio.process.json');
        $customCodePdmDefinition = $this->readJson($projectRoot . '/examples/codificacao-assistente-pdm.process.json');
        $importExportAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.integracoes',
            'program' => [
                'id' => 'admin-integracoes',
                'title' => 'Integracoes',
                'subtitle' => 'Mapeamentos administrativos de importacao e exportacao',
                'version' => '1.0.0',
                'screenId' => 'admin.integracoes',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/import-export-mappings.html',
                'frameTitle' => 'Integracoes administrativas',
            ],
        ];
        $programGovernanceAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-governanca',
            'program' => [
                'id' => 'admin-programa-governanca',
                'title' => 'Governanca de programas',
                'subtitle' => 'Solicitacoes, grants, testes, aprovacoes e rebase de overlays',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-governanca',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-governance.html',
                'frameTitle' => 'Governanca de programas',
            ],
        ];
        $programGrantsAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-grants-operacao',
            'program' => [
                'id' => 'admin-programa-grants-operacao',
                'title' => 'Grants de programas',
                'subtitle' => 'Operacao focada em liberacao, congelamento e revogacao',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-grants-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-grants.html',
                'frameTitle' => 'Grants de programas',
            ],
        ];
        $programApprovalsAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-aprovacoes-operacao',
            'program' => [
                'id' => 'admin-programa-aprovacoes-operacao',
                'title' => 'Aprovacoes de publicacao',
                'subtitle' => 'Operacao focada em bundle e aprovacao final',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-aprovacoes-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-approvals.html',
                'frameTitle' => 'Aprovacoes de publicacao',
            ],
        ];
        $programRetentionAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-retencao-operacao',
            'program' => [
                'id' => 'admin-programa-retencao-operacao',
                'title' => 'Retencao da governanca',
                'subtitle' => 'Operacao focada em politica de retencao e limpeza',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-retencao-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-retention.html',
                'frameTitle' => 'Retencao da governanca',
            ],
        ];
        $programRetentionHistoryAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-retencao-historico-operacao',
            'program' => [
                'id' => 'admin-programa-retencao-historico-operacao',
                'title' => 'Historico da retencao',
                'subtitle' => 'Operacao focada em historico persistido da retencao',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-retencao-historico-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-retention-history.html',
                'frameTitle' => 'Historico da retencao',
            ],
        ];
        $programAuditAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-auditoria-operacao',
            'program' => [
                'id' => 'admin-programa-auditoria-operacao',
                'title' => 'Auditoria da governanca',
                'subtitle' => 'Operacao focada em timeline, sinais operacionais e historico por programa',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-auditoria-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-audit.html',
                'frameTitle' => 'Auditoria da governanca',
            ],
        ];
        $programOperationsAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-operacoes-operacao',
            'program' => [
                'id' => 'admin-programa-operacoes-operacao',
                'title' => 'Operacoes da governanca',
                'subtitle' => 'Operacao administrativa unificada de monitoramento, integridade e retencao',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-operacoes-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-operations.html',
                'frameTitle' => 'Operacoes da governanca',
            ],
        ];
        $programOverlaysAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-overlays-operacao',
            'program' => [
                'id' => 'admin-programa-overlays-operacao',
                'title' => 'Overlays de programas',
                'subtitle' => 'Operacao focada em overlays, assinantes e rebase',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-overlays-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-overlays.html',
                'frameTitle' => 'Overlays de programas',
            ],
        ];
        $programOverlayVersionsAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.programa-overlay-versoes-operacao',
            'program' => [
                'id' => 'admin-programa-overlay-versoes-operacao',
                'title' => 'Versoes de overlay',
                'subtitle' => 'Operacao focada em historico, comparacao e publish de overlay',
                'version' => '1.0.0',
                'screenId' => 'admin.programa-overlay-versoes-operacao',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/program-overlay-versions.html',
                'frameTitle' => 'Versoes de overlay',
            ],
        ];
        $subscriberProvisioningAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.assinante-ambientes',
            'program' => [
                'id' => 'admin-assinante-ambientes',
                'title' => 'Provisionamento de assinantes',
                'subtitle' => 'Cadastro do assinante, provisionamento SaaS e pacote on-premise',
                'version' => '1.0.0',
                'screenId' => 'admin.assinante-ambientes',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/subscriber-provisioning.html',
                'frameTitle' => 'Provisionamento de assinantes',
            ],
        ];
        $systemUpdatesAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.atualizacoes',
            'program' => [
                'id' => 'admin-atualizacoes',
                'title' => 'Atualizacoes do sistema',
                'subtitle' => 'Catalogo de releases, aplicacao e impacto em programas padrao/customizados',
                'version' => '1.0.0',
                'screenId' => 'admin.atualizacoes',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/system-updates.html',
                'frameTitle' => 'Atualizacoes do sistema',
            ],
        ];
        $systemUpdateSubscriberLogAdminDefinition = [
            'pageType' => 'custom',
            'screenId' => 'admin.atualizacoes-assinantes',
            'program' => [
                'id' => 'admin-atualizacoes-assinantes',
                'title' => 'Atualizacoes por assinante',
                'subtitle' => 'Consulta central do que foi aplicado em cada assinante SaaS',
                'version' => '1.0.0',
                'screenId' => 'admin.atualizacoes-assinantes',
            ],
            'custom' => [
                'mode' => 'iframe',
                'entryUrl' => 'admin/system-update-subscriber-log.html',
                'frameTitle' => 'Atualizacoes por assinante',
            ],
        ];

        $clientesDefinition['screenId'] = 'cadastros.clientes';
        $clientesDefinition['program']['screenId'] = 'cadastros.clientes';
        $jobsDefinition['screenId'] = 'admin.jobs';
        $jobsDefinition['program']['screenId'] = 'admin.jobs';
        $processDefinition['screenId'] = 'processamento.relatorio-clientes';
        $processDefinition['program']['screenId'] = 'processamento.relatorio-clientes';
        $customCodePdmDefinition['screenId'] = 'assistente.codificacao.produto-pdm';
        $customCodePdmDefinition['program']['screenId'] = 'assistente.codificacao.produto-pdm';
        $homeDefinition['screenId'] = 'home';
        $homeDefinition['app']['id'] = 'home';
        $adminScreens = AdminCrudDefinitionFactory::screens();
        $this->attachRuntimeJobsProgramToHome($homeDefinition);
        $this->attachAdminProgramsToHome($homeDefinition, $adminScreens);
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-integracoes', 'Integracoes', 'Administracao de integracoes', 'admin.integracoes');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-governanca', 'Governanca de programas', 'Governanca de alteracao, grant, testes e aprovacao', 'admin.programa-governanca');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-grants-operacao', 'Grants de programas', 'Operacao focada em grants', 'admin.programa-grants-operacao');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-aprovacoes-operacao', 'Aprovacoes de publicacao', 'Operacao focada em aprovacoes', 'admin.programa-aprovacoes-operacao');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-retencao-operacao', 'Retencao da governanca', 'Operacao focada em retencao da governanca', 'admin.programa-retencao-operacao');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-retencao-historico-operacao', 'Historico da retencao', 'Operacao focada em historico da retencao', 'admin.programa-retencao-historico-operacao');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-auditoria-operacao', 'Auditoria da governanca', 'Operacao focada em timeline e historico operacional', 'admin.programa-auditoria-operacao');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-operacoes-operacao', 'Operacoes da governanca', 'Operacao administrativa unificada', 'admin.programa-operacoes-operacao');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-overlays-operacao', 'Overlays de programas', 'Operacao focada em overlays e rebase', 'admin.programa-overlays-operacao');
        $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-programa-overlay-versoes-operacao', 'Versoes de overlay', 'Operacao focada em versoes de overlay', 'admin.programa-overlay-versoes-operacao');
        if ($this->central->isCentralControl()) {
            $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-assinante-ambientes', 'Provisionamento de assinantes', 'Criacao do assinante, SaaS e pacote on-premise', 'admin.assinante-ambientes');
            $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-atualizacoes', 'Atualizacoes do sistema', 'Releases, anuencia e aplicacao de atualizacoes', 'admin.atualizacoes');
            $this->attachCustomAdminProgramToHome($homeDefinition, 'admin-atualizacoes-assinantes', 'Atualizacoes por assinante', 'Consulta central do historico aplicado em cada assinante', 'admin.atualizacoes-assinantes');
        }

        foreach (($homeDefinition['programs'] ?? []) as $index => $program) {
            if (($program['id'] ?? '') === 'clientes-crud') {
                $homeDefinition['programs'][$index]['screenId'] = 'cadastros.clientes';
                unset($homeDefinition['programs'][$index]['definitionUrl'], $homeDefinition['programs'][$index]['openUrl']);
            }
        }

        $this->upsertProgram('cadastros.clientes', 'Clientes', 'cadastros', 'crud', 'cadastros.clientes');
        $this->upsertProgram('runtime-jobs', 'Jobs Assincronos', 'administracao', 'crud', 'admin.jobs');
        $this->upsertProgram('admin-integracoes', 'Integracoes', 'administracao', 'custom', 'admin.integracoes');
        $this->upsertProgram('admin-programa-governanca', 'Governanca de programas', 'administracao', 'custom', 'admin.programa-governanca');
        $this->upsertProgram('admin-programa-grants-operacao', 'Grants de programas', 'administracao', 'custom', 'admin.programa-grants-operacao');
        $this->upsertProgram('admin-programa-aprovacoes-operacao', 'Aprovacoes de publicacao', 'administracao', 'custom', 'admin.programa-aprovacoes-operacao');
        $this->upsertProgram('admin-programa-retencao-operacao', 'Retencao da governanca', 'administracao', 'custom', 'admin.programa-retencao-operacao');
        $this->upsertProgram('admin-programa-retencao-historico-operacao', 'Historico da retencao', 'administracao', 'custom', 'admin.programa-retencao-historico-operacao');
        $this->upsertProgram('admin-programa-auditoria-operacao', 'Auditoria da governanca', 'administracao', 'custom', 'admin.programa-auditoria-operacao');
        $this->upsertProgram('admin-programa-operacoes-operacao', 'Operacoes da governanca', 'administracao', 'custom', 'admin.programa-operacoes-operacao');
        $this->upsertProgram('admin-programa-overlays-operacao', 'Overlays de programas', 'administracao', 'custom', 'admin.programa-overlays-operacao');
        $this->upsertProgram('admin-programa-overlay-versoes-operacao', 'Versoes de overlay', 'administracao', 'custom', 'admin.programa-overlay-versoes-operacao');
        $this->upsertProgram('admin-assinante-ambientes', 'Provisionamento de assinantes', 'administracao', 'custom', 'admin.assinante-ambientes');
        $this->upsertProgram('admin-atualizacoes', 'Atualizacoes do sistema', 'administracao', 'custom', 'admin.atualizacoes');
        $this->upsertProgram('admin-atualizacoes-assinantes', 'Atualizacoes por assinante', 'administracao', 'custom', 'admin.atualizacoes-assinantes');
        $this->upsertProgram('processamento-clientes', 'Processamento de Clientes', 'operacional', 'process', 'processamento.relatorio-clientes');
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
        $this->upsertScreen('admin.integracoes', 'custom', $importExportAdminDefinition);
        $this->upsertScreen('admin.programa-governanca', 'custom', $programGovernanceAdminDefinition);
        $this->upsertScreen('admin.programa-grants-operacao', 'custom', $programGrantsAdminDefinition);
        $this->upsertScreen('admin.programa-aprovacoes-operacao', 'custom', $programApprovalsAdminDefinition);
        $this->upsertScreen('admin.programa-retencao-operacao', 'custom', $programRetentionAdminDefinition);
        $this->upsertScreen('admin.programa-retencao-historico-operacao', 'custom', $programRetentionHistoryAdminDefinition);
        $this->upsertScreen('admin.programa-auditoria-operacao', 'custom', $programAuditAdminDefinition);
        $this->upsertScreen('admin.programa-operacoes-operacao', 'custom', $programOperationsAdminDefinition);
        $this->upsertScreen('admin.programa-overlays-operacao', 'custom', $programOverlaysAdminDefinition);
        $this->upsertScreen('admin.programa-overlay-versoes-operacao', 'custom', $programOverlayVersionsAdminDefinition);
        $this->upsertScreen('admin.assinante-ambientes', 'custom', $subscriberProvisioningAdminDefinition);
        $this->upsertScreen('admin.atualizacoes', 'custom', $systemUpdatesAdminDefinition);
        $this->upsertScreen('admin.atualizacoes-assinantes', 'custom', $systemUpdateSubscriberLogAdminDefinition);
        $this->upsertScreen('processamento.relatorio-clientes', 'process', $processDefinition);
        $this->upsertScreen('assistente.codificacao.produto-pdm', 'process', $customCodePdmDefinition);
        foreach ($adminScreens as $screen) {
            $this->upsertScreen((string) $screen['screenId'], 'crud', $screen['definition']);
        }
        $this->upsertScreen('home', 'home', $homeDefinition);
        $this->upsertEndpoints('cadastros.clientes', array_merge(self::CLIENT_ENDPOINTS, self::SYSTEM_ENDPOINTS));
        $this->upsertEndpoints('admin.jobs', array_merge(self::JOB_ENDPOINTS, self::SYSTEM_ENDPOINTS));
        $this->upsertEndpoints('processamento.relatorio-clientes', self::PROCESS_ENDPOINTS);
        $this->upsertEndpoints('assistente.codificacao.produto-pdm', self::CUSTOM_CODE_PDM_ENDPOINTS);
        foreach ($adminScreens as $screen) {
            $this->upsertEndpoints((string) $screen['screenId'], $this->adminEndpointHandlers($screen));
        }
        $this->upsertEndpoints('home', array_merge(self::HOME_ENDPOINTS, self::SYSTEM_ENDPOINTS));
        $this->upsertDefaultLockPolicies();
        $this->upsertAuthDefaults();
        $this->upsertSubscriberDefaults();
        $this->upsertSystemParameters();
        $this->upsertLiteralTranslations();
        $this->upsertBuilderModuleDefaults();
        $this->seedClientes();
        $this->seedClienteTelefones();

        $this->entityManager->flush();
        $this->integrity->backfillAll();
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
            ->setStatus('published')
            ->setProgramOrigin('standard')
            ->setOwnerScope('system')
            ->setCustomizationPolicy('overlay_only')
            ->setSubscriberId(null)
            ->setBaseProgramCode(null)
            ->setBaseProgramVersionId(null)
            ->setUpgradeFrozen(false)
            ->setFrozenReason(null);

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

    private function attachCustomAdminProgramToHome(array &$homeDefinition, string $programId, string $title, string $subtitle, string $screenId): void
    {
        $navigation = is_array($homeDefinition['navigation'] ?? null) ? $homeDefinition['navigation'] : [];
        $groups = is_array($navigation['groups'] ?? null) ? $navigation['groups'] : [];
        foreach ($groups as $index => $group) {
            if (($group['id'] ?? '') !== 'admin-runtime') {
                continue;
            }
            $items = is_array($group['items'] ?? null) ? $group['items'] : [];
            if (!$this->containsByProgramId($items, $programId)) {
                $items[] = [
                    'programId' => $programId,
                    'title' => $title,
                ];
            }
            $groups[$index]['items'] = $items;
            break;
        }
        $navigation['groups'] = $groups;
        $homeDefinition['navigation'] = $navigation;

        $programs = is_array($homeDefinition['programs'] ?? null) ? $homeDefinition['programs'] : [];
        if (!$this->containsById($programs, $programId)) {
            $programs[] = [
                'id' => $programId,
                'title' => $title,
                'subtitle' => $subtitle,
                'type' => 'custom',
                'icon' => 'gear',
                'permission' => 'admin.read',
                'screenId' => $screenId,
            ];
        }
        $homeDefinition['programs'] = $programs;
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
        if ($endpointId === 'runtime.admin.integrity.resign') {
            return 'admin.write';
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
        if ($screenId === 'processamento.relatorio-clientes') {
            return 'processamento.read';
        }
        if ($screenId === 'assistente.codificacao.produto-pdm') {
            return 'processamento.read';
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
        if ($screenId === 'processamento.relatorio-clientes') {
            return [
                'entityCode' => 'cliente',
                'actionId' => $endpointId,
                'programId' => 'processamento-clientes',
                'permissionPrefix' => 'processamento',
            ];
        }
        if ($screenId === 'assistente.codificacao.produto-pdm') {
            return [
                'actionId' => $endpointId,
                'programId' => 'assistente-codificacao-produto-pdm',
                'permissionPrefix' => 'processamento',
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
                'runtime.admin.integrity.resign' => 'runtime.admin.integrity.resign',
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
        $subscriberEnabled = $this->upsertSystemParameterDefinition(
            'subscriber.enabled',
            'Habilitar conceito de assinante',
            'Controla se o login deve selecionar assinante/tenant apos autenticacao.',
            'boolean',
            true,
            false
        );
        $this->upsertGlobalSystemParameterValue($subscriberEnabled, false);

        $publicContextEnabled = $this->upsertSystemParameterDefinition(
            'ai.builder.public_context_enabled',
            'Habilitar contexto publico do construtor',
            'Controla se o endpoint publico protegido por chave pode expor o contrato do builder para uso externo.',
            'boolean',
            true,
            false
        );
        $this->upsertGlobalSystemParameterValue($publicContextEnabled, false);

        $this->upsertSystemParameterDefinition(
            'ai.builder.public_context_key',
            'Chave publica do construtor',
            'Chave estatica exigida no cabecalho X-Builder-Public-Key para acessar o contexto publico do builder.',
            'string',
            false,
            null
        );

        $aiEnabled = $this->upsertSystemParameterDefinition(
            'ai.builder.enabled',
            'Habilitar assistente IA do construtor',
            'Controla se o construtor pode usar o assistente interno por texto e audio.',
            'boolean',
            true,
            false
        );
        $this->upsertGlobalSystemParameterValue($aiEnabled, false);

        $aiProvider = $this->upsertSystemParameterDefinition(
            'ai.builder.provider',
            'Provedor do assistente IA do construtor',
            'Provedor usado pelo construtor. Valores suportados: mock e openai_compatible.',
            'string',
            true,
            'mock'
        );
        $this->upsertGlobalSystemParameterValue($aiProvider, 'mock');

        $aiAgentName = $this->upsertSystemParameterDefinition(
            'ai.builder.agent_name',
            'Nome do agente IA do construtor',
            'Nome exibido no chat do construtor.',
            'string',
            false,
            'Assistente do construtor'
        );
        $this->upsertGlobalSystemParameterValue($aiAgentName, 'Assistente do construtor');

        $aiBaseUrl = $this->upsertSystemParameterDefinition(
            'ai.builder.base_url',
            'Base URL do provedor IA do construtor',
            'URL base do provedor compatível com OpenAI para o assistente do construtor.',
            'string',
            false,
            'https://api.openai.com/v1'
        );
        $this->upsertGlobalSystemParameterValue($aiBaseUrl, 'https://api.openai.com/v1');

        $aiModel = $this->upsertSystemParameterDefinition(
            'ai.builder.model',
            'Modelo do assistente IA do construtor',
            'Modelo principal usado para gerar rascunhos CRUD no construtor.',
            'string',
            false,
            ''
        );
        $this->upsertGlobalSystemParameterValue($aiModel, null);

        $this->upsertSystemParameterDefinition(
            'ai.builder.api_token',
            'Token do assistente IA do construtor',
            'Token secreto do provedor do assistente IA do construtor.',
            'string',
            false,
            null
        );

        $aiTranscriptionEnabled = $this->upsertSystemParameterDefinition(
            'ai.builder.transcription_enabled',
            'Habilitar transcricao por audio no construtor',
            'Controla se o assistente do construtor pode enviar audio para transcricao no backend.',
            'boolean',
            true,
            false
        );
        $this->upsertGlobalSystemParameterValue($aiTranscriptionEnabled, false);

        $aiTranscriptionModel = $this->upsertSystemParameterDefinition(
            'ai.builder.transcription_model',
            'Modelo de transcricao do assistente IA do construtor',
            'Modelo usado para converter audio em texto no backend do construtor.',
            'string',
            false,
            ''
        );
        $this->upsertGlobalSystemParameterValue($aiTranscriptionModel, null);

        $governanceRetentionChangeRequests = $this->upsertSystemParameterDefinition(
            'governance.retention.change_requests_days',
            'Retencao de solicitacoes de governanca',
            'Quantidade de dias para manter solicitacoes de alteracao antes da limpeza automatica.',
            'integer',
            true,
            180
        );
        $this->upsertGlobalSystemParameterValue($governanceRetentionChangeRequests, 180);

        $governanceRetentionGrants = $this->upsertSystemParameterDefinition(
            'governance.retention.grants_days',
            'Retencao de grants de governanca',
            'Quantidade de dias para manter grants de alteracao/publicacao antes da limpeza automatica.',
            'integer',
            true,
            180
        );
        $this->upsertGlobalSystemParameterValue($governanceRetentionGrants, 180);

        $governanceRetentionApprovals = $this->upsertSystemParameterDefinition(
            'governance.retention.approvals_days',
            'Retencao de aprovacoes de governanca',
            'Quantidade de dias para manter aprovacoes finais antes da limpeza automatica.',
            'integer',
            true,
            365
        );
        $this->upsertGlobalSystemParameterValue($governanceRetentionApprovals, 365);

        $governanceRetentionTests = $this->upsertSystemParameterDefinition(
            'governance.retention.test_executions_days',
            'Retencao de bundles de teste',
            'Quantidade de dias para manter execucoes de testes governados antes da limpeza automatica.',
            'integer',
            true,
            365
        );
        $this->upsertGlobalSystemParameterValue($governanceRetentionTests, 365);

        $governanceRetentionNotifications = $this->upsertSystemParameterDefinition(
            'governance.retention.notifications_days',
            'Retencao de notificacoes administrativas de governanca',
            'Quantidade de dias para manter notificacoes administrativas antes da limpeza automatica.',
            'integer',
            true,
            30
        );
        $this->upsertGlobalSystemParameterValue($governanceRetentionNotifications, 30);
    }

    private function upsertLiteralTranslations(): void
    {
        foreach ($this->defaultLiteralTranslations() as $item) {
            $row = $this->systemLiteralTranslations->findOneBy([
                'code' => $item['code'],
                'locale' => $item['locale'],
            ]) ?? new SystemLiteralTranslation();
            $row
                ->setCode($item['code'])
                ->setLocale($item['locale'])
                ->setContext($item['context'])
                ->setDescription($item['description'])
                ->setText($item['text'])
                ->setEnabled(true);
            $this->entityManager->persist($row);
        }
    }

    /**
     * @return list<array{code: string, locale: string, context: ?string, description: ?string, text: string}>
     */
    private function defaultLiteralTranslations(): array
    {
        return [
            ['code' => 'literal.button.continue', 'locale' => 'pt-BR', 'context' => 'crud', 'description' => 'Botao de continuar em confirmacoes.', 'text' => 'Continuar'],
            ['code' => 'literal.button.cancel', 'locale' => 'pt-BR', 'context' => 'crud', 'description' => 'Botao de cancelar em janelas.', 'text' => 'Cancelar'],
            ['code' => 'literal.button.close', 'locale' => 'pt-BR', 'context' => 'crud', 'description' => 'Botao padrao de fechar janela.', 'text' => 'Fechar'],
            ['code' => 'literal.button.confirm', 'locale' => 'pt-BR', 'context' => 'crud', 'description' => 'Botao padrao de confirmacao.', 'text' => 'Confirmar'],
            ['code' => 'literal.button.understood', 'locale' => 'pt-BR', 'context' => 'crud', 'description' => 'Botao padrao de entendimento/bloqueio.', 'text' => 'Entendi'],
            ['code' => 'literal.title.confirm', 'locale' => 'pt-BR', 'context' => 'crud', 'description' => 'Titulo padrao de janela de confirmacao.', 'text' => 'Confirmar'],
            ['code' => 'literal.title.warning', 'locale' => 'pt-BR', 'context' => 'crud', 'description' => 'Titulo padrao de janela de aviso.', 'text' => 'Aviso'],
            ['code' => 'validation.title.consistency_warning', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Titulo de aviso de consistencia.', 'text' => 'Aviso de consistencia'],
            ['code' => 'validation.title.inconsistencies', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Titulo padrao para inconsistencias.', 'text' => 'Inconsistencias encontradas'],
            ['code' => 'validation.title.invalid_situation', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Titulo para situacao invalida.', 'text' => 'Situacao invalida'],
            ['code' => 'validation.title.situation_not_allowed', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Titulo para transicao nao permitida.', 'text' => 'Situacao nao permitida'],
            ['code' => 'validation.title.situation_blocked', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Titulo para mudanca de situacao bloqueada.', 'text' => 'Mudanca de situacao bloqueada'],
            ['code' => 'validation.message.confirm_default', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem padrao de confirmacao.', 'text' => 'Deseja continuar?'],
            ['code' => 'validation.message.form_inconsistencies', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem padrao para inconsistencias de formulario.', 'text' => 'Existem inconsistencias no formulario.'],
            ['code' => 'validation.message.no_allowed_fields', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem quando nao ha campos permitidos para gravacao.', 'text' => 'Nenhum campo permitido foi informado.'],
            ['code' => 'validation.message.field_required', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem para campo obrigatorio.', 'text' => '{fieldLabel} e obrigatorio.'],
            ['code' => 'validation.message.field_min_length', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem para tamanho minimo de campo.', 'text' => '{fieldLabel} precisa ter ao menos {min} caracteres.'],
            ['code' => 'validation.message.field_required_for_situation', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem para campo obrigatorio por situacao.', 'text' => '{fieldLabel} e obrigatorio para esta mudanca de situacao.'],
            ['code' => 'validation.message.inactive_customer_note_required', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem para obrigatoriedade de observacao ao inativar cliente.', 'text' => 'Informe uma observacao ao inativar o cliente.'],
            ['code' => 'validation.message.json_invalid', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem para JSON invalido.', 'text' => '{fieldLabel} precisa conter um JSON valido.'],
            ['code' => 'validation.message.situation_not_registered', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem quando a situacao nao esta cadastrada.', 'text' => 'A situacao informada nao esta cadastrada para esta entidade.'],
            ['code' => 'validation.message.situation_transition_not_allowed', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem detalhada para transicao nao permitida.', 'text' => 'Nao e permitido mudar a situacao de {from} para {to} nesta acao.'],
            ['code' => 'validation.message.situation_transition_blocked', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem curta para transicao nao permitida.', 'text' => 'Transicao de situacao nao permitida.'],
            ['code' => 'validation.message.situation_rules_pending', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem de regras pendentes por situacao.', 'text' => 'Existem regras pendentes para mudar a situacao.'],
            ['code' => 'validation.message.version_reference_not_found', 'locale' => 'pt-BR', 'context' => 'validation', 'description' => 'Mensagem para referencia historica nao encontrada.', 'text' => 'Nao foi encontrada uma versao historica valida para {fieldLabel}.'],
            ['code' => 'runtime.message.operation_blocked', 'locale' => 'pt-BR', 'context' => 'runtime', 'description' => 'Mensagem padrao para operacao bloqueada.', 'text' => 'Operacao bloqueada.'],
        ];
    }

    private function upsertSystemParameterDefinition(
        string $code,
        string $name,
        string $description,
        string $dataType,
        bool $required,
        mixed $defaultValue
    ): SystemParameter {
        $parameter = $this->systemParameters->findOneBy(['code' => $code]) ?? new SystemParameter();
        $parameter
            ->setCode($code)
            ->setName($name)
            ->setDescription($description)
            ->setDataType($dataType)
            ->setOptionList(null)
            ->setRequired($required)
            ->setDefaultValue($defaultValue)
            ->setEnabled(true);

        $this->entityManager->persist($parameter);
        $this->entityManager->flush();

        return $parameter;
    }

    private function upsertGlobalSystemParameterValue(SystemParameter $parameter, mixed $value): void
    {
        $parameterValue = $this->systemParameterValues->findOneBy([
            'parameter' => $parameter,
            'establishmentCode' => null,
        ]) ?? $this->systemParameterValues->findOneBy([
            'parameter' => $parameter,
            'establishmentCode' => '',
        ]) ?? new SystemParameterValue();
        $parameterValue
            ->setParameter($parameter)
            ->setEstablishmentCode(null)
            ->setStartsAt(new \DateTimeImmutable('2000-01-01 00:00:00'))
            ->setEndsAt(null)
            ->setValue($value)
            ->setEnabled(true);

        $this->entityManager->persist($parameterValue);
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

    private function upsertBuilderModuleDefaults(): void
    {
        $this->upsertBuilderModule('cadastros', 'Cadastros', 'cd', 1, 999);
        $this->upsertBuilderModule('operacional', 'Operacional', 'op', 1000, 1999);
        $this->upsertBuilderModule('administracao', 'Administracao', 'ad', 2000, 2999);
    }

    private function upsertBuilderModule(string $code, string $name, string $abbreviation, int $start, int $end): void
    {
        $module = $this->builderModules->findOneBy(['code' => $code]) ?? new BuilderModule();
        $module
            ->setCode($code)
            ->setName($name)
            ->setAbbreviation($abbreviation)
            ->setNumberStart($start)
            ->setNumberEnd($end)
            ->setEnabled(true)
            ->setMetadata([
                'source' => 'seed-runtime-metadata',
            ]);

        $this->entityManager->persist($module);
    }
}
