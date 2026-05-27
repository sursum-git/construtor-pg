# Continuidade para novas sessoes Codex

Use este arquivo para retomar o trabalho em outra sessao.

## Antes de mexer

1. Leia `CONTEXTO_PROJETO.md`.
2. Leia `docs/arquitetura-crud-engine.md`.
3. Leia `docs/padroes-ui-kendo.md`.
4. Confira `git status --short`.
5. Para alteracoes na pagina inicial, leia `docs/arquitetura-home-engine.md`.
6. Para alteracoes em demo, exemplos ou mocks, execute a skill `construtor-pg-demo-production-parity` e atualize `docs/paridade-demo-producao.md`.
7. Nao reverta alteracoes existentes sem pedido explicito do usuario.
8. Quando o usuario pedir pendencias operacionais ou o que falta fazer fora do codigo, consultar `docs/pendencias-operacionais.md`.

## Provisionamento operacional

- existe agora uma camada de automacao, sem alterar o modelo atual de tenant/runtime/programas:
  - `php backend/bin/console app:install:bootstrap`
  - `php backend/bin/console app:subscriber:create`
  - `php backend/bin/console app:runtime:publish-defaults`
  - `scripts/install-onprem.ps1`
  - `scripts/install-onprem.sh`
  - `scripts/provision-saas-subscriber.ps1`
  - `scripts/provision-saas-subscriber.sh`
- a documentacao operacional desta frente fica em `docs/provisionamento-saas-onprem.md`.
- o manual detalhado da instalacao fica em `docs/manual-instalacao.md`.
- essa automacao so encadeia:
  - criacao opcional do banco;
  - migrations;
  - seed runtime;
  - validacao do catalogo padrao;
  - criacao/atualizacao do assinante e do admin inicial.
- o cadastro do assinante agora tambem guarda:
  - `deploymentMode`
  - `runtimeEnvironmentCode`
  - `primaryEnvironmentCode`
- no modo `shared_program_shared_db`, varios assinantes podem apontar para o mesmo ambiente runtime, sem misturar o ambiente principal isolado.
- a tela `admin.assinante-ambientes` agora tambem mostra:
  - auditoria de ambientes runtime compartilhados;
  - matriz operacional por assinante;
  - catalogo administrativo de tabelas globais x filtradas por assinante;
  - canal de update por assinante.
  - validacao previa de conflitos e checklist de prerequisitos;
  - progresso por etapa do job de provisionamento;
  - retry parcial a partir de uma etapa especifica;
  - relatorio final do job e checksum SHA-256 do pacote on-premise.
- existe agora uma tela administrativa propria:
  - `production/app.html?screenId=admin.assinante-ambientes`
  - pagina local: `examples/pages/admin-subscriber-provisioning.html`
  - backend:
    - `GET /api/admin/subscriber-provisioning/bootstrap`
    - `POST /api/admin/subscriber-provisioning/subscribers`
  - `POST /api/admin/subscriber-provisioning/provision`
  - `POST /api/admin/subscriber-provisioning/precheck`
  - `GET /api/admin/subscriber-provisioning/jobs`
  - `GET /api/admin/subscriber-provisioning/jobs/{jobId}`
  - `POST /api/admin/subscriber-provisioning/jobs/{jobId}/retry`
  - `GET /api/admin/subscriber-provisioning/jobs/{jobId}/events`
  - `GET /api/admin/subscriber-provisioning/onprem-package`
  - o provisionamento SaaS roda no job `subscriber.environment.provision`.
  - existe tambem a instalacao inicial por executaveis Go:
    - `installer/cmd/system-builder` gera o perfil `system_builder`;
    - `installer/cmd/subscriber` gera o perfil `subscriber`;
    - `installer/build.ps1` e `installer/build.sh` geram Linux e Windows;
    - os binarios ficam em `installer/dist/`, que nao entra no Git;
    - Windows e somente teste sem Docker.
  - a central de ativacao agora pode usar a tabela `installer_activation_license`:
    - tela `admin.instalacao-licencas`;
    - controla e-mail de confirmacao, perfis, modos, status, validade e limite de ativacoes;
    - registra historico resumido em `metadata.activationHistory`;
    - se a tabela nao existir ou nao houver cadastro, ainda existe fallback por `APP_INSTALLER_ACTIVATION_SUBSCRIBERS`.
  - a central de ativacao agora tambem pode usar a tabela `installer_activation_service_token`:
    - tela `admin.instalacao-tokens`;
    - `token_hash` aceita `password_hash` ou SHA-256 hexadecimal;
    - controla perfis, modos, validade e historico de uso.
  - licencas podem limitar hosts por `metadata.maxHosts`, `metadata.allowedFingerprints` e `metadata.revokedFingerprints`; a central atualiza `metadata.fingerprints`.
  - existe tambem a tela `admin.central-operacoes`, publicada apenas no sistema central, para consolidar painel operacional, auditoria, revogacao, tentativas/bloqueio, chaves, artefatos, saude dos assinantes e notificacoes derivadas.
  - a confirmacao por e-mail da ativacao bloqueia tentativas repetidas por requisicao com `APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS` e `APP_INSTALLER_ACTIVATION_BLOCK_MINUTES`.
  - a reinstalacao exige politica de backup: backup validado, pular com justificativa ou ambiente descartavel/teste.
  - artefatos do instalador podem ser assinados por `APP_INSTALLER_ARTIFACT_SIGNING_KEY` e validados no executavel por `CONSTRUTOR_INSTALLER_ARTIFACT_SIGNING_KEY`.
  - a stack Docker Linux fica em `Dockerfile`, `compose.yaml` e `docker/`:
    - `app` contem Nginx, PHP-FPM e Supervisor;
    - `database` usa PostgreSQL 16;
    - o worker fica inativo por padrao e exige `APP_WORKER_ENABLED=1`;
    - a instalacao nao roda no boot do container, apenas pela pagina `production/install.html` liberada pelo executavel.
  - a stack separada de producao fica em `Dockerfile.runtime`, `compose.production.yaml`, `docker/nginx/production.conf` e `docker/php/entrypoint.sh`, com `nginx`, `php`, `worker` e `database`.
  - quando validar Docker local, se a porta `8080` estiver ocupada por outro servico, usar `APP_HTTP_PORT=18080`.
  - existe agora tambem a frente de atualizacao operacional:
  - manifesto inicial em `backend/config/system-updates/manifest.json`;
  - comandos:
    - `php backend/bin/console app:update:check`
    - `php backend/bin/console app:update:download <versao>`
    - `php backend/bin/console app:update:apply <versao>`
    - `php backend/bin/console app:update:run-pending`
    - `php backend/bin/console app:update:simulate <versao>`
    - `php backend/bin/console app:update:rollback <versao>`
    - `php backend/bin/console app:update:rollout-plan <versao>`
    - `php backend/bin/console app:update:dispatch-rollout <versao>`
    - `php backend/bin/console app:update:publish-artifacts [versao]`
  - tela administrativa:
    - `production/app.html?screenId=admin.atualizacoes`
    - pagina local: `examples/pages/admin-system-updates.html`
  - endpoints:
    - `GET /api/admin/system-updates/bootstrap`
    - `POST /api/admin/system-updates/check`
    - `POST /api/admin/system-updates/apply`
    - `GET /api/admin/system-updates/simulate`
    - `POST /api/admin/system-updates/rollback`
    - `POST /api/admin/system-updates/publish-artifacts`
    - `GET /api/admin/system-updates/jobs/{jobId}`
    - `GET /api/admin/system-updates/jobs/{jobId}/events`
    - `GET /api/runtime/system-updates/summary`
    - `POST /api/runtime/system-updates/run-pending`
  - job envolvido:
    - `system.update.apply`
  - compatibilizacao com programas:
    - `standard` pode receber a release;
    - `customer_overlay` so entra em analise de impacto/rebase;
    - `customer_custom` continua congelado e nao sofre sobrescrita.
  - existe agora anuencia formal por release:
    - `POST /api/admin/system-updates/consent`
  - existe tambem exportacao do plano de rollout SaaS:
    - `GET /api/admin/system-updates/rollout-plan?version=...`
  - e o runner on-premise:
    - `scripts/update-onprem.sh`
    - `scripts/update-onprem.ps1`
  - o sistema central SaaS agora e identificado por `APP_SYSTEM_ROLE=saas_central` ou `APP_CENTRAL_CONTROL_ENABLED=1`;
  - `admin.assinante-ambientes`, `admin.atualizacoes`, `admin.atualizacoes-assinantes` e `admin.central-operacoes` devem ficar apenas nesse sistema central;
  - existe a consulta focada:
    - `production/app.html?screenId=admin.atualizacoes-assinantes`
    - pagina local: `examples/pages/admin-system-update-subscriber-log.html`
  - a esteira da release agora tambem cria draft de rebase para overlay limpo (`rebase_ok`), mantendo `review_required` para conflito leve e bloqueio total para conflito critico;
  - `programUpdates` agora tambem passam por pipeline proprio de programa padrao:
    - programa padrao novo => `installed`;
    - versao padrao nova => `updated`;
    - base ja na meta => `verified`;
    - base acima da meta => `ahead_of_target`;
    - se o publish padrao nao levar o programa ate a versao esperada, a release falha;
- o construtor de entidades persistentes agora tambem aceita `subscriberIsolation.mode=none|subscriber_column`, aplicando filtro automatico do assinante atual no runtime CRUD quando a entidade usar coluna de assinante;
- para entidade persistente sem filtro por assinante, o builder agora exige confirmacao explicita de tabela global compartilhada;
  - a consulta por assinante agora tambem usa `GET /api/admin/system-updates/executions` com filtros por status, categoria e periodo, alem de exportacao local JSON/CSV;
  - releases opcionais no SaaS agora podem exigir ativacao por assinante antes do apply:
    - `POST /api/admin/system-updates/tenant-activation`
  - o manifesto remoto pode ser configurado por `APP_UPDATE_MANIFEST_URL`;
  - a verificacao do manifesto usa `APP_UPDATE_MANIFEST_SIGNING_KEY`;
  - a verificacao do pacote usa `APP_UPDATE_PACKAGE_SIGNING_KEY`;
  - cada release do manifesto agora pode declarar `version`, `requiresVersionMin`, `requiresAppliedUpdates[]`, `replaces[]`, `category`, `autoApply`, `breakingLevel` e `steps`;
  - a cadeia obrigatoria do updater agora e resolvida por assinante-alvo no sistema central; nao usar o historico global do ambiente para decidir dependencias de um assinante especifico;
  - a politica da release agora tambem deve ficar explicita no contrato:
    - `metadata.requiresBackup`
    - `metadata.requiresMaintenanceMode`
    - `autoApplySaas`
    - `autoApplyOnPrem`
    - `requiresSubscriberConsent`
    - `blocksNextUpdates`
    - `internetRequired`
  - o updater agora tambem aplica defaults por categoria e exige `metadata.applicationPolicyOverride` com justificativa quando a release fugir da matriz padrao;
  - a persistencia/publicacao do manifesto agora tambem valida coerencia da cadeia, incluindo dependencia ausente, `replaces[]` invalido e ciclo em `requiresAppliedUpdates[]`;
  - o updater agora tambem valida canais por release (`stable`, `pilot`, `canary`, `lts`), changelog estruturado, `steps` conhecidos e `rollbackStep/rollbackSteps` coerentes;
  - `admin.atualizacoes` agora expõe pre-check de compatibilidade antes do apply, simulacao administrativa da release, rollback formal, dashboards de atraso e alertas operacionais;
  - `admin.atualizacoes-assinantes` agora tambem mostra timeline resumida por assinante com eventos de check, download, anuencia, ativacao, apply, rollout, falha e rollback;
  - o endurecimento do on-premise ao abrir o sistema agora separa:
    - `APP_UPDATE_ONPREM_CRITICAL_MODE=auto|prompt_admin|download_only`
    - `APP_UPDATE_ONPREM_CRITICAL_ACCESS_POLICY=warn|block`
  - `APP_UPDATE_ONPREM_CRITICAL_POLICY` continua aceito como legado;
  - a publicacao oficial dos artefatos usa:
    - `APP_UPDATE_DISTRIBUTION_DIR`
    - `APP_UPDATE_PUBLIC_BASE_URL`
  - existe agora tambem o comando:
    - `php backend/bin/console app:update:saas-cycle`
  - esse comando existe para o sistema central detectar a release sem UI, enfileirar o job administrativo e deixar o worker aplicar os steps em ordem;
  - quando a release nao declarar `steps`, o updater agora assume uma esteira padrao por categoria (`migrate`, `seed_runtime_metadata`, `publish_runtime_defaults`, `integrity_monitor` ou o subconjunto correspondente).
  - a distribuicao externa dos artefatos pode ser despachada por:
    - `APP_UPDATE_DISTRIBUTION_PUSH_URL`
    - `APP_UPDATE_DISTRIBUTION_PUSH_TOKEN`
    - `APP_UPDATE_DISTRIBUTION_PUSH_SIGNING_KEY`
    - `APP_UPDATE_DISTRIBUTION_PUSH_TIMEOUT`
  - o rollout externo do SaaS pode ser integrado por:
    - `APP_UPDATE_ORCHESTRATOR_URL`
    - `APP_UPDATE_ORCHESTRATOR_TOKEN`
    - `APP_UPDATE_ORCHESTRATOR_SIGNING_KEY`
    - `APP_UPDATE_ORCHESTRATOR_TIMEOUT`
  - o receptor externo real fica em:
    - `scripts/orchestrator/system-update-orchestrator.php`
    - `scripts/orchestrator/run-system-update-orchestrator.sh`
    - `scripts/orchestrator/system-update-orchestrator.config.sample.json`
  - a maturidade operacional do rollout SaaS agora inclui:
    - janela agendada por `metadata.saasRolloutWindow`;
    - batches/canario por `metadata.saasRolloutBatches`;
    - despacho progressivo por lote a partir de `admin.atualizacoes`;
    - auditoria de rollout em `admin.atualizacoes-assinantes`;
    - bloqueio temporario de entrada do tenant via estado local em `APP_SAAS_ROLLOUT_STATE_FILE`, escrito pelo orquestrador externo.

## Estado atual do program-builder

- o clique direto na arvore lateral ficou deterministico apos corrigir o layout do painel de navegacao:
  - a area de acoes estava crescendo sobre a area da arvore;
  - agora a arvore tem altura util propria e as acoes ficam limitadas com scroll.
- existe importacao de tabelas PostgreSQL existentes pelo proprio editor:
  - listar tabelas importaveis;
  - analisar tabela;
  - carregar a modelagem na UI;
  - importar entidade e gerar rascunho CRUD.
- existe importacao de SQL/DDL PostgreSQL pelo proprio editor:
  - colar um `CREATE TABLE`;
  - aceitar `COMMENT ON TABLE` e `COMMENT ON COLUMN`;
  - bloquear comandos fora desse escopo;
  - gerar `entityDraft` e `programDraft` sem executar o script no schema real.
- existe tambem importacao de JSON externo pelo proprio editor:
  - colar `entityDraft + programDraft`;
  - validar sintaxe no frontend;
  - validar contrato no backend;
  - carregar a modelagem normalizada para revisao sem salvar.
- validacao real feita nesta sessao:
  - clique direto na arvore abrindo `cliente`;
  - `database/tables`, `database/inspect` e `database/import` no backend real;
  - importacao de `public.condition` gerando entidade e rascunho CRUD;
  - validacao UI do painel de importacao com screenshot em `tmp/program-builder-import-ui.png`.
- o construtor agora tambem aceita `pageType=custom`:
  - programa manual sem entidade base;
  - `custom.mode = iframe | htmlUrl`;
  - `custom.entryUrl` relativo ao proprio sistema;
  - publicacao no runtime sem endpoints CRUD;
  - `production/app.html` detecta `crud`, `process` e `custom` pelo `screenId`.
- existe endpoint publico protegido por chave para orientar IA externa:
  - `GET /api/public/program-builder/external-context`;
  - cabecalho `X-Builder-Public-Key`;
  - parametros `ai.builder.public_context_enabled` e `ai.builder.public_context_key`.
- existe assistente interno de IA no appbar do construtor:
  - `GET/POST /api/admin/program-builder/ai/settings`;
  - `POST /api/admin/program-builder/ai/session`;
  - `POST /api/admin/program-builder/ai/message`;
  - `POST /api/admin/program-builder/ai/transcribe`;
  - `POST /api/admin/program-builder/ai/finalize-draft`;
  - a conversa agora fica persistida em `runtime_ai_session` e `runtime_ai_message`, com `sessionId`, tenant, usuario, assinante, validade, rascunho atual e diagnosticos;
  - cada chamada continua exigindo token valido; `sessionId` apenas identifica a conversa e precisa pertencer ao mesmo usuario/tenant/assinante;
  - a sessao grava `catalog_hash` e `catalog_version`, permitindo enviar o catalogo completo de capacidades apenas quando necessario;
  - `finalize-draft` usa o rascunho persistido da sessao e nao depende so do payload enviado pelo navegador;
  - regras sugeridas pela IA ficam restritas ao formato declarativo; classe/metodo, PHP, JavaScript ou SQL executavel viram diagnostico de pendencia tecnica;
  - provider `mock` validado ponta a ponta;
  - token e chave publica mascarados no CRUD administrativo de parametros;
  - screenshots atuais:
    - `tmp/program-builder-ai-settings.png`
    - `tmp/program-builder-ai-window.png`
    - `tmp/program-builder-ai-flow.png`
    - `tmp/program-builder-ai-applied.png`
- existe `entityType=api` no construtor:
  - modelagem de `apiSource` no `builder_entity.metadata`;
  - cadastro reutilizavel de metadados da API em `builder_api_source`;
  - importacao de contrato por `POST /api/admin/program-builder/api-sources/import-openapi`;
  - vinculo da entidade por `apiSourceCode`, `apiListOperationCode`, `apiDetailOperationCode`, `apiCreateOperationCode`, `apiUpdateOperationCode` e `apiDeleteOperationCode`;
  - campos com `jsonPath` e flags de exibir em grid/form/filtro;
  - geracao de CRUD somente leitura e tambem CRUD basico para APIs JSON previsiveis;
  - runtime separado por `entity.api.crud`;
  - sem tabela fisica e sem lock de escrita;
  - exemplos de demo em `consulta-api-readonly` e `consulta-api-crud` usando `DemoMockHttpClient`.
- existe tambem suporte a Odoo dentro do cadastro de APIs:
  - `providerType=odoo` em `builder_api_source`;
  - configuracao de `XML-RPC` ou `JSON-RPC` em `metadata.odoo`;
  - endpoints administrativos:
    - `POST /api/admin/program-builder/api-sources/odoo/test-connection`
    - `POST /api/admin/program-builder/api-sources/odoo/model-metadata`
  - operacoes sinteticas `odoo_list` e `odoo_detail`;
  - carga automatica de campos do modelo via `fields_get`;
  - runtime separado por `entity.api.odoo.readonly`;
  - publicacao de tela CRUD em modo consulta, com `create/edit/delete=false`.
- a governanca do construtor ganhou dashboard operacional real no dialogo:
  - resumo de requests, grants, aprovacoes e bundles de teste;
  - leitura do backend por `GET /api/admin/program-builder/governance/dashboard`;
  - `CrudHttpClient` agora serializa `data` em `GET` para suportar esse fluxo sem URL montada manualmente.
- a tela `admin.integridade` ficou alinhada entre demo e producao:
  - mock local passou a expandir `extraApi` em `definition.api`;
  - a reassinatura usa `endpointId=runtime.admin.integrity.resign` em vez de URL livre;
  - existe smoke local `npm run test:admin-integridade`;
  - existe smoke real de producao `npm run test:admin-integridade-production`.
- a retencao de historico de governanca agora pode ser lida por parametros:
  - `governance.retention.change_requests_days`
  - `governance.retention.grants_days`
  - `governance.retention.approvals_days`
  - `governance.retention.test_executions_days`
  - `governance.retention.notifications_days`
- o dialogo de governanca do `program-builder` agora tambem consegue:
  - reutilizar request recente;
  - congelar, reativar ou revogar grant recente;
  - reaproveitar bundle recente;
  - ajustar a retencao da governanca pelo proprio painel.
- endpoints novos dessa frente:
  - `GET /api/admin/program-builder/governance/retention`
  - `POST /api/admin/program-builder/governance/retention`
- a reassinatura estrutural agora grava trilha adicional no `metadata.auditTrail` com:
  - motivo;
  - usuario;
  - horario;
  - status antes/depois;
  - hash anterior.
- a integridade estrutural passou a cobrir tambem:
  - `builder_api_source`
  - `builder_module`
  - `builder_entity_situation`
  - `builder_entity_situation_transition`
  - `runtime_lock_policy`
  - `system_option_list`
  - `system_option`
  - `system_parameter`
  - `system_parameter_value`
  - `auth_provider_config`
  - `auth_subscriber`
  - `auth_user_subscriber`
  - `system_literal_translation`
  - `import_export_mapping`
  - `import_export_mapping_version`
  - `import_export_schedule`
- o preview de rebase de overlay agora tem:
  - filtro por secao/caminho;
  - filtro por classificacao do conflito;
  - navegacao rapida por secao.
- o preview de rebase agora tambem detalha caminhos por campo dentro de cada secao, com classificacao e comparacao entre base nova, overlay e resultado rebaseado.
- existe agora uma tela administrativa dedicada de governanca:
  - `production/app.html?screenId=admin.programa-governanca`;
  - pagina local de smoke: `examples/pages/admin-program-governance.html`;
  - cobre requests, grants, bundles, aprovacoes, retencao e rebase por programa.
- existem tambem entradas focadas para operacao direta:
  - `production/app.html?screenId=admin.programa-grants-operacao`;
  - `production/app.html?screenId=admin.programa-aprovacoes-operacao`;
  - `production/app.html?screenId=admin.programa-retencao-operacao`;
  - `production/app.html?screenId=admin.programa-retencao-historico-operacao`;
  - `production/app.html?screenId=admin.programa-auditoria-operacao`;
  - `production/app.html?screenId=admin.programa-operacoes-operacao`;
  - `production/app.html?screenId=admin.programa-overlays-operacao`;
  - `production/app.html?screenId=admin.programa-overlay-versoes-operacao`;
  - paginas locais:
    - `examples/pages/admin-program-grants.html`
    - `examples/pages/admin-program-approvals.html`
    - `examples/pages/admin-program-retention.html`
    - `examples/pages/admin-program-retention-history.html`
    - `examples/pages/admin-program-audit.html`
    - `examples/pages/admin-program-operations.html`
    - `examples/pages/admin-program-overlays.html`
    - `examples/pages/admin-program-overlay-versions.html`
  - usadas por notificacoes/contexto e validadas no smoke de governanca.
- a retencao da governanca agora tambem persiste historico operacional em `program_governance_retention_run`:
  - modo `preview|apply`;
  - origem `ui|cli`;
  - usuario;
  - ambiente/base;
  - total de registros, delta por tabela, `executionGroup`, relacao entre preview/aplicacao e payload resumido da execucao.
  - agora tambem exibe comparativo antes/depois por `pairedRun`, incluindo total anterior e delta por tabela.
  - existe entrada focada para esse historico, com agrupamento por execucao e exportacao do item selecionado.
- existe endpoint dedicado para auditoria:
  - `GET /api/admin/program-builder/governance/audit`;
  - filtros suportados:
    - `programCode`
    - `builderProgramVersionId`
    - `eventType`
    - `userId`
    - `dateFrom`
    - `dateTo`
- existe comando operacional novo:
  - `php backend/bin/console app:governance:monitor`
  - `php backend/bin/console app:governance:monitor --fail-on-alert`
  - `php backend/bin/console app:governance:operations`
  - objetivo:
    - revisar grants congelados/revogados;
    - identificar overlays bloqueados;
    - detectar publicacoes padrao travadas por aprovacao pendente;
    - consolidar notificacoes administrativas idempotentes;
    - opcionalmente acoplar preview/aplicacao da limpeza operacional.
- existe agora tambem a UI focada `admin.programa-operacoes-operacao`:
  - consulta snapshot operacional por programa;
  - roda monitor dedicado;
  - roda operacao unificada;
  - mostra contagens de integridade, alertas e limpeza.
- o smoke de governanca agora valida tambem:
  - revogacao e nova liberacao de grant antes do publish governado;
  - o modo focado de retencao na tela administrativa dedicada;
  - o modo focado de overlays com abertura do preview de rebase;
  - o modo focado de versoes de overlay com carga, comparacao e publish.
- existe tambem o E2E `npm run test:program-governance-full`, que percorre builder governado, publish, rebase, retencao e auditoria em sequencia.
- a Home agora consegue abrir notificacoes com contexto seguro:
  - `screenId` ou `programId`;
  - `navigation.query` serializado na URL interna;
  - filtros aplicados na tela administrativa de destino;
  - grants e aprovacoes podem abrir direto nas entradas focadas de operacao.
- a Home agora tambem persiste por `screenId`:
  - modulo atual;
  - texto da busca lateral;
  - filtro "somente favoritos".
- a Home agora tambem restaura:
  - programa aberto;
  - estado expandido/recolhido da lateral.
- a Home agora tambem pode reabrir automaticamente o ultimo painel contextual do appbar quando ele for `notifications` ou `jobs`.
- a limpeza de contexto local agora foi centralizada em `CrudUtils.clearRuntimeSessionContext(...)`, usada por login, Home, `CrudHttpClient` e `CrudEngine` para logout, token invalido e sessao revogada.
- a auditoria dedicada agora salva localmente o ultimo filtro aplicado por tipo/usuario/data e reaplica esse recorte ao reabrir a tela focada.
- o filtro salvo da auditoria agora usa chave por `programCode`, evitando misturar contexto de programas diferentes.
- o preview de rebase agora tambem devolve diff final consolidado:
  - `finalDiffEntries`
  - `finalDiffDefinition`
  - usado tanto no builder quanto na tela dedicada de governanca.
- a Home agora tambem salva o filtro local da central de notificacoes por `screenId`:
  - severidade;
  - categoria;
  - exige acao;
  - somente nao lidas;
  - com botao `Limpar filtros`.
- `admin.integracoes` agora tambem:
  - filtra execucoes por `mappingCode`, `mode` e `status`;
  - mostra detalhe da execucao selecionada;
  - exporta a execucao selecionada em JSON.
- `admin.integracoes` agora tambem restaura:
  - aba ativa;
  - mapping atualmente aberto;
  - filtros do historico persistido;
  - ultima execucao selecionada.
- `admin.integracoes` agora tambem restaura:
  - versao selecionada do mapping;
  - agendamento selecionado.
- `admin.integracoes` agora tambem restaura:
  - no selecionado do editor visual;
  - e mostra comparativo simples entre preview e execucao quando os dois resultados existem.
- `admin.integracoes` agora tambem restaura:
  - no selecionado do preview estrutural.
- o login web agora tambem:
  - preenche o ultimo usuario usado;
  - limpa sessao local por botao proprio;
  - ignora `rememberToken` expirado antes do auto-login.
- os estados locais JSON da Home, Integracoes e auditoria agora usam envelope versionado salvo pelo `CrudUtils`, com fallback para formato antigo.
- existem smokes novos desta frente:
  - `npm run test:admin-program-audit`
  - `npm run test:program-governance-full`
  - `npm run test:home-notifications`
  - `npm run test:login-demo`
- existe tambem o E2E `npm run test:context-resume-full`, cobrindo login, Home, sessao revogada, Integracoes com reload e limpeza final da sessao local.
- os smokes `npm run test:home-notifications` e `npm run test:admin-integracoes` agora tambem validam persistencia de estado apos `reload`.
- existe tambem o guia [docs/estado-local-persistido.md](C:/construtor-pg/docs/estado-local-persistido.md) para revisar o que persiste, por chave e por contexto, e o que deve ser limpo no logout.
- validacao real mais recente desta frente:
  - mock Odoo local em `tmp/mock-odoo-router.php`;
  - `XML-RPC` e `JSON-RPC` com sucesso no teste de conexao;
  - falha de autenticacao devolvendo `ODOO_AUTH_FAILED`;
  - leitura de metadados do modelo `res.partner` com 8 campos;
  - entidade `odoo_api_587379` publicada a partir da fonte `odoo_mock_587379`;
  - tela `odoo.api.587379` publicada em modo readonly;
  - runtime validado em `read` e `get`;
  - validacao frontend do `program-builder` e do `production/app.html` via proxy local em `tmp/dev-proxy.js`;
  - screenshots:
    - `tmp/odoo-builder-ui.png`
    - `tmp/odoo-runtime-ui.png`
- existe um catalogo inicial de importacao/exportacao entre entidades e arquivos:
  - endpoints administrativos:
    - `GET /api/admin/import-export-mappings`
    - `GET /api/admin/import-export-mappings/{code}`
    - `POST /api/admin/import-export-mappings`
    - `POST /api/admin/import-export-mappings/preview`
    - `POST /api/admin/import-export-mappings/execute`
  - formatos de arquivo suportados:
    - `csv`
    - `xml`
    - `txt_layout`
  - `txt_layout` agora aceita:
    - `lineMode="fixed"` para leiaute posicional;
    - `lineMode="delimited"` para leiaute por separador, usando apenas a ordem dos campos.
    - estrutura hierarquica por `recordLayouts[]`, com `nodeType=record|group|totalizer`, `children[]`, `linkBy[]` e agregacoes `count/sum`.
  - fluxos validados nesta sessao:
    - `api_crud_mock_produto -> produto_builder`
    - `cliente -> api_crud_mock_produto`
    - `cliente -> csv`
    - `cliente -> txt_layout` fixo
    - `cliente -> txt_layout` delimitado
    - `cliente -> txt_layout` hierarquico com filho e totalizador
  - exemplo documental novo:
    - `examples/pages/import-export-mappings.html`
  - existe agora tambem uma tela administrativa real:
    - `screenId=admin.integracoes`
    - `production/admin/import-export-mappings.html`
    - cadastro, preview e execucao manual do mapping;
    - editor visual com `TreeView` para TXT/XML;
    - inspetor de no;
    - preview estrutural lado a lado;
    - historico persistido de execucoes;
    - historico de versoes do proprio mapping;
    - aba de agendamentos com execucao manual dos vencidos.
  - exportacao XML atual:
    - colunas simples por `sourcePath -> targetName`;
    - ou estrutura rica por `xmlLayouts[]`;
    - `rootName`, `itemName`, `prettyPrint`, `encodingLabel`, `namespaces[]` e `rootAttributes[]`;
    - filhos repetitivos com `sourceAlias`;
    - vinculo pai/filho com `linkBy`;
    - sem template livre e sem script.
  - importacao XML atual:
    - origem `type=file` com `fileFormat=xml`;
    - leitura por `recordPath`;
    - suporte a `namespaces[]`;
    - mapeamento declarativo por `fields[].targetField + xpath`.
  - persistencia operacional nova:
    - `import_export_execution`;
    - `import_export_mapping_version`;
    - `import_export_schedule`.
  - execucao agendada:
    - endpoint administrativo `POST /api/admin/import-export-mappings/schedules/run-due`;
    - comando `php backend/bin/console app:import-export:run-schedules`.
- existe agora tambem uma pagina documental de manual por programa:
  - `examples/pages/manual-programas.html`
  - ela usa:
  - `TreeView` para indice dos programas;
  - `TabStrip` para separar visao funcional, operacional e referencias;
  - secao adicional de arquitetura do sistema, motores de renderizacao e escopo suportado hoje;
  - filtro por nome, modulo ou `screenId`.
- existe agora tambem a frente de governanca/rastreabilidade/versionamento:
  - ownership do programa em `programOrigin`, `ownerScope` e `customizationPolicy`;
  - overlays por assinante em `builder_program_overlay` e `builder_program_overlay_version`;
  - solicitacoes e grants em `program_change_request` e `program_change_grant`;
  - bundles de testes e aprovacoes em `program_test_execution` e `program_publication_approval`;
  - locks de autoria reaproveitando `builder_editor_lock` com `grantId` e `lockCategory`;
  - rastreabilidade ampliada em `runtime_transaction` e `runtime_transaction_log` com `programVersion`, `builderProgramVersionId`, `builderEntityVersionId`, `screenDefinitionVersion`, `schemaFingerprint`, `databaseIdentity`, `databaseEnvironment`, `customizationKind`, `grantId`, `requestCode`, `approvalId` e `testExecutionBundleId`;
  - integridade estrutural em `system_record_integrity`, com assinatura por HMAC para `builder_program`, `builder_program_version`, `builder_entity`, `builder_entity_version`, `screen_definition`, `runtime_endpoint`, overlays e versoes de overlay;
  - cobertura estrutural ampliada tambem para `builder_field`;
  - monitor administrativo `admin.integridade` para acompanhar ultimo status, horario, erro da verificacao estrutural e disparar reassinatura controlada;
  - comandos operacionais `app:integrity:check`, `app:integrity:monitor` e `app:integrity:resign`;
  - politica inicial de retencao e limpeza por `app:governance:cleanup-history`;
- `Program Builder` com dialogs proprios para solicitar alteracao, liberar/congelar/revogar grant, registrar bundle de testes, aprovar publicacao e pedir preview/execucao de rebase do overlay, com diff visual das secoes da base antiga, base nova, overrides e definicao rebaseada;
- o rebase do overlay agora aceita resolucao assistida por item:
  - `rebased` para manter o merge sugerido;
  - `overlay` para preservar o valor customizado;
  - `base` para usar o valor da nova base;
- conflitos bloqueantes nao seguem mais por confirmacao manual;
- conflitos leves exigem confirmacao explicita;
- escolhas `overlay` em conflitos leves agora sao rejeitadas por politica;
- o preview agora devolve `policyDecision`, `policySummary`, `runtimeImpactSummary` e `finalResolutionSummary`.
- tela dedicada `admin.programa-governanca` para operar o mesmo fluxo fora do editor, inclusive retencao com preview/execucao e rebase assistido;
- entrada focada `admin.programa-overlays-operacao` para listar overlays do programa, revisar congelamento e abrir o preview do rebase com o ID correto;
  - gate guiado no proprio editor para apontar pendencias de grant, lock, bundle e aprovacao antes do publish.
- regras praticas desta fase:
  - assinante nao cria nem converte programa para `standard`;
  - publicar programa `standard/system` exige grant ativo, lock ativo, bundle de testes aprovado e aprovacao final ativa;
  - grant congelado ou revogado derruba lock de autoria na proxima verificacao de heartbeat do editor;
  - politica de `publicationPolicy.allowedDatabaseEnvironments` pode bloquear publicacao em ambiente de banco nao autorizado;
  - o runtime tenta resolver primeiro `customer_custom`, depois `customer_overlay`, depois `standard`;
  - as telas administrativas novas sao:
    - `admin.programa-solicitacoes`
    - `admin.programa-grants`
    - `admin.programa-testes`
    - `admin.programa-aprovacoes`
    - `admin.integridade`
    - `admin.programa-overlays`
    - `admin.programa-overlay-versoes`
- o `CrudUtils` agora possui um resolvedor central de literais pt-BR:
  - suporta `titleKey/titleParams` e `messageKey/messageParams` no contrato de validacao;
  - mantem fallback para `title` e `message`;
  - centraliza textos padrao de confirmacao, bloqueio e inconsistencias sem quebrar payloads antigos.
- para regras por classe/metodo, o padrao oficial agora e:
  - usar constantes de chave na classe;
  - montar mensagens com `RuntimeBusinessRuleContext::messageItem(...)`;
  - interromper validacao com `RuntimeBusinessRuleContext::throwValidation(...)`;
  - evitar texto literal hardcoded, deixando `message` apenas como fallback.
- existe agora tambem um catalogo administrativo de literais:
  - tela `admin.literais`;
  - entidade `system_literal_translation`;
  - rota runtime `GET /api/runtime/literals/{locale}`;
  - o frontend carrega esse bundle pela configuracao `config.literals` e faz merge sobre o dicionario pt-BR embutido.
- o login e o program-builder agora tambem conseguem consumir o bundle runtime de literais:
  - login com `LoginLiterals`;
  - construtor com `ProgramBuilderLiterals`;
  - ambos preservam fallback local quando o bundle nao estiver disponivel.
- existe agora tambem um modulo real de notificacoes runtime:
  - telas `admin.notificacoes` e `admin.notificacao-destinatarios`;
  - tabelas `runtime_notification` e `runtime_notification_recipient`;
  - envio por `target_user_ids` e `target_groups`;
  - rastreio de `pending`, `delivered` e `read` por destinatario;
  - endpoints da Home:
    - `home.notifications.list`
    - `home.notifications.ack`
  - a Home mostra as notificacoes no appbar e permite:
    - filtro por severidade;
    - filtro de exigencia de acao;
    - somente nao lidas;
    - `Marcar como lida` individual;
    - `Marcar todas` por filtro.
  - a demo mock agora tambem preserva o estado de leitura por destinatario ao recarregar os dados administrativos, em vez de recriar tudo como pendente.
- o backend da Home agora tambem cobre chat e atendimento:
  - `home.chat.contacts`, `home.chat.history` e `home.chat.send`;
  - `home.chat.events`;
  - `home.support.onlineUsers`, `home.support.history`, `home.support.send`, `home.support.createRequest` e `home.support.requestStatus`;
  - `home.support.events`;
  - historico de chat e suporte em `runtime_user_message`;
  - solicitacoes de suporte em `runtime_support_request`;
  - atendentes online resolvidos por sessoes ativas e grupos `support` ou `support.<setor>`.
  - o polling global de `runtime.messages` agora ignora `chat` e `support_chat`, para nao transformar conversa em toast/runtime message da shell.
  - envio continua por `POST`; recepcao em tempo quase real agora usa SSE proprio por conversa/atendimento, com fallback natural para carregamento sob demanda quando o stream nao abre.
  - a mesma rota runtime agora tambem atende `GET /api/runtime/screens/{screenId}/endpoints/{endpointId}` para listas/status e `GET ...?stream=1` para os streams `home.chat.events` e `home.support.events`.
- validacao real mais recente desta frente:
  - migration `Version20260512100000` aplicada;
  - `POST /api/admin/program-builder/api-sources/import-openapi` validado contra OpenAPI local servido em `http://127.0.0.1:8765/tmp/openapi-api-source-test.json`;
  - cadastro `api_produtos_ext` salvo no backend real;
  - entidade `api_meta_teste_001` salva apontando para `api_produtos_ext`;
  - `POST /api/admin/program-builder/preview` devolveu CRUD `readonly` com `create/edit/delete=false`;
  - CRUD completo por tela runtime validado em `api.crud.mock.produtos` com create, update e delete reais via API externa mockada.
  - `screenId=admin.integracoes` publicado via `custom` para a tela administrativa real de import/export.
  - SSE real de chat validado com `home.chat.send` + `home.chat.events?stream=1`, entregando a mensagem nova na conversa.
  - SSE real de atendimento validado com `home.support.createRequest`, `home.support.requestStatus` e `home.support.events?stream=1`, entregando presenca online e status do protocolo.
  - smoke local novo da tela administrativa de integracoes:
    - `npm run test:admin-integracoes`
    - pagina local: `examples/pages/import-export-admin-demo.html`
    - cobre TreeView TXT, preview estrutural, execucao e XML hierarquico.
- validacao real mais recente da frente de governanca/rastreabilidade/versionamento:
  - `php backend/vendor/bin/phpunit backend/tests/Runtime/ProgramGovernanceServiceTest.php backend/tests/Runtime/ProgramCustomizationResolverTest.php backend/tests/Runtime/RuntimeTransactionServiceTraceabilityTest.php backend/tests/Runtime/RuntimeEntityDefinitionResolverTest.php backend/tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php`
    - `OK (8 tests, 72 assertions)`
  - `php backend/bin/console doctrine:migrations:migrate --no-interaction`
  - `php backend/bin/console app:seed-runtime-metadata`
  - `php backend/bin/console lint:container`
  - `php backend/bin/console app:integrity:monitor --fail-on-invalid`
    - `Registros verificados: 678`
    - `Registros invalidos: 0`
  - `npm run test:program-builder-governance`
    - fluxo local validado em `file:///C:/construtor-pg/examples/pages/program-builder-governance.html`
    - cobre solicitacao, grant, bundle de testes, aprovacao, publish governado e preview de rebase do overlay
  - `npm run test:admin-integridade`
    - fluxo local validado em `file:///C:/construtor-pg/examples/pages/admin-integridade.html`
    - cobre abertura da tela, visualizacao do registro invalido, reassinatura pela UI e persistencia do novo status no mock administrativo
  - `npm run test:admin-program-governance`
    - fluxo local validado em `file:///C:/construtor-pg/examples/pages/admin-program-governance.html`
    - cobre request, grant, retencao, preview de rebase e os modos focados de grants/aprovacoes

## Comandos uteis

Validar sintaxe JS:

```powershell
node --check src\examples\examples-catalog.js
node --check src\examples\examples-index.js
node --check src\examples\examples-page.js
node --check src\crud-engine\CrudEngine.js
node --check src\crud-engine\CrudKendoGridRenderer.js
node --check src\crud-engine\CrudKendoFormRenderer.js
node --check src\home-engine\HomeEngine.js
node --check src\home-engine\HomeDefinitionLoader.js
node --check src\home-engine\HomeDefinitionValidator.js
node --check src\process-engine\ProcessEngine.js
node --check src\process-engine\ProcessDefinitionLoader.js
node --check src\process-engine\ProcessDefinitionValidator.js
```

Validar exemplos pelo catalogo:

```powershell
@'
global.window = global;
global.localStorage = {
  store: {},
  getItem(key) { return this.store[key] || null; },
  setItem(key, value) { this.store[key] = String(value); },
  removeItem(key) { delete this.store[key]; }
};
require('./src/crud-engine/CrudUtils.js');
require('./src/page-engine/PageDefinitionNormalizer.js');
require('./src/crud-engine/CrudDefinitionLoader.js');
require('./src/crud-engine/CrudDefinitionValidator.js');
require('./src/home-engine/HomeDefinitionValidator.js');
require('./src/process-engine/ProcessDefinitionLoader.js');
require('./src/process-engine/ProcessDefinitionValidator.js');
require('./src/demo/demo-embedded-data.js');
require('./src/demo/process-embedded-data.js');
require('./src/demo/home-embedded-data.js');
require('./src/demo/DemoMockHttpClient.js');
require('./src/examples/examples-catalog.js');

(async function() {
  const catalog = global.CrudExamplesCatalog;
  const crudValidator = new global.CrudDefinitionValidator();
  const homeValidator = new global.HomeDefinitionValidator();
  const processValidator = new global.ProcessDefinitionValidator();
  const normalizer = new global.PageDefinitionNormalizer();
  const errors = [];

  for (const item of catalog.list()) {
    try {
      const config = catalog.buildConfig(item.id);
      const policy = global.CrudUtils.normalizeSecurityPolicy(config, {});
      if (item.engine === 'home') {
        homeValidator.validate(catalog.buildHomeDefinition(item.id), { securityPolicy: policy });
        continue;
      }
      if (item.engine === 'process') {
        processValidator.validate(catalog.buildProcessDefinition(item.id), { securityPolicy: policy });
        continue;
      }
      if (item.loadByScreenId) {
        const http = new global.DemoMockHttpClient({ storageSuffix: 'validation-' + item.id });
        const raw = await new global.CrudDefinitionLoader({ httpClient: http }).load({
          screenId: item.screenId || item.id,
          securityPolicy: policy
        });
        crudValidator.validate(normalizer.normalize(raw), { securityPolicy: policy });
        continue;
      }
      crudValidator.validate(catalog.buildDefinition(item.id), { securityPolicy: policy });
    } catch (error) {
      errors.push({ id: item.id, error: error && error.details || error && error.payload || error && error.message || String(error) });
    }
  }

  if (errors.length) {
    console.log(JSON.stringify(errors, null, 2));
    process.exit(1);
  }
  console.log('validated examples: ' + catalog.list().length);
  console.log('property options: ' + catalog.getPropertyOptions().length);
})();
'@ | node -
```

Validar no browser:

- Usar Playwright/Chromium headless quando possivel.
- URLs principais:
  - `file:///C:/construtor-pg/index.html`
  - `file:///C:/construtor-pg/login.html`
  - `file:///C:/construtor-pg/home.html`
  - `file:///C:/construtor-pg/exemplos.html`
  - `file:///C:/construtor-pg/theme-builder.html`
  - `file:///C:/construtor-pg/examples/pages/consulta-basica.html`
  - `file:///C:/construtor-pg/examples/pages/processamento-parametros.html`
  - `http://127.0.0.1:8765/program-builder.html`
  - `file:///C:/construtor-pg/production/app.html?screenId=cadastros.clientes`
  - `file:///C:/construtor-pg/production/app.html?screenId=admin.jobs`
  - `file:///C:/construtor-pg/production/app.html?screenId=admin.parametros`
  - `file:///C:/construtor-pg/production/app.html?screenId=admin.sessoes`
  - `file:///C:/construtor-pg/production/app.html?screenId=admin.transacoes`
- `file:///C:/construtor-pg/production/home.html?screenId=home`
- `http://127.0.0.1:8765/production/program-builder.html`

Validar o MVP desktop WPF:

- instalar SDK do .NET 8 ou superior;
- abrir `desktop-wpf/ConstrutorPg.BuilderDesktop.sln`;
- executar o projeto `ConstrutorPg.BuilderDesktop`.

## Quando implementar funcionalidade nova

Atualizar tambem:

- `src/examples/examples-catalog.js`;
- `src/examples/examples-index.js`, se a pagina principal precisar mostrar algo novo;
- `src/examples/examples-page.js`, se as paginas individuais precisarem suportar algo novo;
- `src/styles/examples-guide.css`, se houver mudanca visual nos exemplos;
- `docs/padroes-ui-kendo.md`, se virar padrao;
- `docs/backlog-v1-estavel.md`, se for algo para depois.

Se a funcionalidade entrar primeiro na demo, registrar em `docs/paridade-demo-producao.md` se ela ja e compativel com producao, se ficou pendente, se nao se aplica ou se depende de backend.

## Regras de seguranca

- Nao aceitar `template`, `eval` ou JavaScript livre vindo do JSON.
- JSON pode escolher opcoes fechadas, campos e URLs, mas nao deve injetar comportamento arbitrario.
- Permissao visual no frontend nao substitui validacao no backend.
- Em producao, backend deve validar tenant, usuario, registro, permissao e transicao.
- Para primeira versao de producao, usar `config.security.mode="production"`.
- Em producao, preferir `screenId` e bloquear `definition` direto/`definitionUrl` livre.
- Em producao, preferir `endpointId`/`actionId` e bloquear URLs livres de API no JSON.
- Entradas de producao ficam em `production/` e nao devem carregar mock ou JSON local de `examples/`.
- `CrudHttpClient({ allowLocalFallback: false })` deve ser usado nas entradas de producao.

## Estado atual de funcionalidades

Implementado ate agora, em nivel demo/frontend:

- CRUD de clientes com mock HTTP em memoria.
- Modelo visual de login em `login.html`, com appbar, logo, manter logado, esqueci a senha, selecao simulada de assinante e imagem lateral opcional.
- Kendo Grid com paginacao, ordenacao, filtros, acoes de linha e exportacao.
- Filtro em janela, filtros salvos, filtros aplicados e edicao de filtro aplicado.
- Leiautes, ordenacoes e agrupamentos salvos no mock.
- Agrupamento com contagem/soma.
- Congelamento de colunas opcional para desktop.
- Mobile com modo colunas e modo template/card seguro.
- Formulario popup com abas, etapas, situacao, eventos seguros, aviso de concorrencia, logs, impressao e outras acoes.
- Botoes e eventos do formulario enviam os valores atuais em `values`; o backend runtime normaliza somente campos permitidos da entidade.
- Consistencias do backend usam contrato `validation` + `effects`, com modal Kendo, marcacao de campo e confirmacao por token quando necessario.
- Backend possui caminho inicial de CRUD generico por `builder_entity`/`builder_field`, regras PHP registradas e subscriber Doctrine como fallback de auditoria.
- Entidades podem ter situacao por `builder_entity_situation` e transicoes por `builder_entity_situation_transition`; o runtime valida situacao inicial, transicoes e regras fechadas por transicao.
- O CRUD generico trata cadastro incompleto com erros fechados: se so existir classe/tabela e faltar metadado do construtor, retorna `ENTITY_METADATA_NOT_CONFIGURED` com `details.minimumRequired`.
- Backend possui fila async inicial com Symfony Messenger/Doctrine em PostgreSQL, rastreamento em `runtime_async_job` e job fechado `cliente.email_confirmation`.
- A decisao de enfileirar fica no backend por `builder_entity.metadata.jobs` ou `runtime_endpoint.config.jobs`; `mode="async"` usa worker, sem job configurado a acao segue na chamada normal.
- Acoes manuais podem usar `handler="runtime.job.enqueue"`; exemplo atual: `sendWhatsapp` agenda `cliente.whatsapp_welcome`.
- Tela `admin.jobs` consulta os jobs assincronos pelo runtime generico.
- Backend runtime ja publica `screenId=processamento.relatorio-clientes`, com endpoints `process` e `status`, job `clientes.processamento` e documento seguro `resultado`.
- Telas administrativas runtime criadas pelo seed: `admin.parametros`, `admin.parametro-valores`, `admin.listas-opcoes`, `admin.opcoes`, `admin.sessoes`, `admin.transacoes` e `admin.logs-transacoes`.
- Tema claro/escuro por configuracao global.
- Pagina inicial por JSON com Kendo TreeView lateral, appbar e chamada de programas por `iframe`, `crud` e `html` sanitizado.
- Pagina inicial por JSON com Kendo TreeView lateral, appbar e chamada de programas por `iframe`, `crud`, `process` e `html` sanitizado.
- Motor de processamento por parametros em `src/process-engine`, com endpoint de inicio, acompanhamento por SSE/polling, retorno em mensagem, grid, relatorio ou job em segundo plano.
- A definicao de processamento dos exemplos e da demo ja usa `endpointId` para `process` e `status`, sem URL livre de API.
- Appbar da Home pode exibir jobs concluidos e abrir a tela "Meus Jobs".
- Assinante/tenant corrente opcional no cabecalho global da pagina inicial via `currentSubscriber`.
- Troca opcional de assinante pelo badge do cabecalho global, com destaque para `Principal`, lista de assinantes e endpoint seguro configuravel; em producao, usar `endpointId` ou `actionId`.
- Chat opcional no appbar da pagina inicial, usando Kendo Chat com ComboBox de usuarios e endpoints configurados no JSON.
- Atendimento opcional no appbar da pagina inicial, com selecao de setor, chat quando houver atendente online no setor, solicitacao com setor travado quando nao houver disponibilidade e contexto do programa corrente nas chamadas.
- Chat de IA opcional no appbar da pagina inicial, usando Kendo Chat sem selecao de usuario e endpoints configurados no JSON.
- Alertas e solicitacoes opcionais no appbar da pagina inicial, com botoes compactos e janelas Kendo alimentadas por endpoints configurados no JSON.
- A Home agora tambem pode expor uma central de notificacoes no appbar, com badge proprio e lista agregada de `alerts`, `requests` e `jobs` quando nao houver endpoint dedicado.
- Pagina de exemplos com PanelBar e aba de configuracao por exemplo.
- Theme builder com preview.
- Guia PDF para orientar outra IA a padronizar projeto Kendo/PHP/Symfony.
- Camada inicial de seguranca de producao: carregamento por `screenId`, bloqueio opcional de JSON/URL livre, gateway runtime por `endpointId/actionId` e exemplo `seguranca-producao`.
- Entradas separadas de producao em `production/app.html` e `production/home.html`, com CSP em meta tag, sem inicializacao inline e sem fallback local no HTTP client.
- Backend runtime Symfony/API Platform com auditoria, semaforo configuravel, heartbeat, mensagens runtime por SSE com polling fallback, derrubada de sessao e protecao contra perda de dados no frontend.
- Construtor visual em `program-builder.html` e `production/program-builder.html`, agora cobrindo cadastro de modulo estrutural com abreviacao e faixa numerica inicial/final, modelagem de entidade, criacao de tabela fisica, preview backend, rascunho, publicacao, duplicacao, historico em `builder_program_version`, historico estrutural em `builder_entity_version`, campo `custom_code` com assistente declarativo e cadastro visual de regras de negocio por entidade.
- O `program-builder` tambem foi reorganizado em layout de editor com `Splitter`, arvore lateral de navegacao, filtros rapidos por tipo/estado, badges por no, abas centrais e painel lateral de preview/historicos/diagnostico, ainda em Kendo/jQuery e sem mudar a stack web.
- O `program-builder` agora tambem possui assistente interno de IA com `kendoChat`, configuracao segura por parametros administrativos, sessao persistente no backend, entrada por texto/audio e carga do rascunho apenas apos validacao backend.
- O editor web agora inclui painel contextual de propriedades, visao lateral de relacionamentos, comparativo entre revisoes/versoes, reordenacao visual de campos/regras/chaves por drag-and-drop, validacao incremental por item com destaque visual e lock de edicao persistente em `builder_editor_lock`.
- A sincronizacao fisica do construtor agora cria tabela, adiciona coluna, renomeia tabela/coluna, ajusta tipo/default/null/precision-scale, pode excluir colunas removidas quando a opcao estiver marcada e suporta rollback por revisao salva da entidade.
- O runtime generico agora suporta cadastro mestre versionado em `runtime_entity_record_version`, referencia automatica de versao atual em campos transacionais e leitura de snapshot historico por campo virtual.
- O `program-builder.html` agora tem assistente visual para esse padrao, gerando `*_version_id` e campos `*_historico` a partir de uma entidade mestre versionada.
- O runtime generico agora tambem suporta codificacao customizada no backend, com sequencia em `runtime_custom_code_sequence`, padrao declarativo e metodo estatico restrito a `App\Runtime\CustomCode\*`.
- O `custom_code` tambem pode abrir uma tela auxiliar segura por `screenId`, usando `ProcessEngine` e retorno fechado `result.type="properties"` para montar as propriedades do codigo antes do salvar.
- O retorno `result.type="properties"` tambem pode trazer `previewCode` e `previewTitle`; nesse caso o formulario abre uma confirmacao Kendo mostrando a previsao do codigo antes de aplicar as propriedades.
- O backend runtime agora tambem publica `screenId=assistente.codificacao.produto-pdm`, com endpoint `process`, handler `process.customCode.pdm` e previsao real do codigo PDM antes da confirmacao no formulario.
- O runtime generico agora executa regras configuradas em `builder_entity.metadata.rules`, com ordem, fase, `continueOnError`, tipo declarativo `requiredWhen` ou `class_method`, parametros JSON e log automatico em `runtime_transaction_log`.
- O construtor agora tambem salva `uniqueKeys` em `builder_entity.metadata`, permite campo `readonly`, FK com `dependencyType/onDelete/onUpdate` e valida nomes fisicos de tabela/coluna conforme o padrao Genesis-ERP, preservando nomes antigos quando nao foram renomeados.
- O construtor agora tambem cadastra modulos estruturais em `builder_module`, controla faixa numerica sem sobreposicao e sugere nomes de tabela a partir da classificacao estrutural (`main`, `composition`, `specific_relation`, `aggregation`, `recursive`, `multi_level`, `view`).
- O codigo do programa agora e informado manualmente e validado pelo modulo escolhido: usa a abreviacao do modulo seguida de 4 digitos dentro da faixa numerica do modulo, por exemplo `cd0101`.
- Permissoes reais no backend por tela e endpoint: `screen_definition.security`, `runtime_endpoint.permission`, grupos/permissoes da sessao e filtro do JSON autorizado da Home.
- `runtime_user_session` e a fonte principal da identidade da sessao; `runtime_transaction` e `runtime_transaction_log` nao guardam mais usuario diretamente.
- Autenticacao inicial no backend com `auth_user`, `auth_provider_config`, login por senha local, estruturas fechadas para LDAP, SSO e OAuth/OIDC, token Bearer vinculado a `runtime_user_session` e pagina `production/login.html`. O tipo de acesso do login por senha vem de `auth_user.authSource`; OAuth/OIDC aparece como botao externo quando habilitado. O "manter logado" usa `auth_remember_token` e `/api/auth/remember`.
- Login com assinante usa `subscriber.enabled`; quando habilitado, o backend resolve `auth_subscriber`/`auth_user_subscriber` depois da senha e conclui a selecao em `/api/auth/select-subscriber`. A Home recebe `currentSubscriber` pela sessao selecionada.
- Usuario administrador escolhe apos o login se entra na area principal ou administrativa; a area administrativa abre a Home com `initialProgramId=admin-parametros`.
- Recuperacao de senha usa `/api/auth/password/request-reset` e `/api/auth/password/reset`, com tokens hash em `auth_password_reset_token`; no ambiente dev o token pode retornar na resposta e o `MAILER_DSN=null://null` apenas prepara/loga o envio.
- Modulo simples de parametros com `system_parameter`, `system_parameter_value`, `system_option_list`, `system_option` e resolver tipado; seed cria `subscriber.enabled=false`.
- Worker da fila: `php bin\console messenger:consume async -vv`.
- O servidor estatico `node scripts/serve-static.js` agora tambem pode encaminhar `/api/*` para `CRUD_ENGINE_API_PROXY`, facilitando validar `program-builder.html` contra backend real.
- Se o construtor responder `PROGRAM_BUILDER_STORAGE_NOT_READY`, aplicar as migrations `Version20260509093000`, `Version20260510113000`, `Version20260510143000`, `Version20260510170000`, `Version20260510190000` e `Version20260510200000` antes de testar a interface.
- O rollback estrutural da entidade depende do snapshot salvo em `builder_entity_version`; ele cobre metadados, rename de tabela, rename de coluna, defaults e constraints gerenciadas pelo construtor.
- Validacao real feita para historico mestre/transacional:
  - `produto_hist` marcado como versionado;
  - `pedido_item_hist` com `produto_version_id` preenchido automaticamente a partir de `produto_id`;
  - `produto_nome_historico` como campo virtual lendo `nome` do snapshot;
  - item antigo continuou exibindo `Produto A` e item novo exibiu `Produto A Atualizado` apos alteracao do cadastro mestre.
- Existe um MVP desktop separado em `desktop-wpf/`, focado em arvore, propriedades contextuais e preview JSON em memoria. Nesta maquina nao ha SDK do .NET instalado, entao a estrutura foi preparada, mas o build local precisa ser validado em ambiente com SDK 8.

## Pontos conhecidos para atencao

- Persistencias de layout/filtro/ordenacao/agrupamento/template mobile existem no backend em tabelas dedicadas; a busca usa precedencia por assinante atual, preferencia global do usuario, padrao do programa e padrao do sistema. A demo ainda simula parte disso em `localStorage`.
- `AUTH_REQUIRED=0` mantem compatibilidade local sem login obrigatorio. Para exigir autenticacao no runtime, configurar `AUTH_REQUIRED=1`.
- LDAP, SSO e OAuth/OIDC ja possuem provedores fechados, mas dependem de cadastro real em `auth_provider_config` e infraestrutura externa.
- Envio real de e-mail depende de configurar `MAILER_DSN`; no ambiente atual o envio fica preparado/logado com `null://null`.
- `subscriber.enabled` fica `false` por padrao. Para testar selecao de assinante, altere o valor vigente em `system_parameter_value` para `true` e depois volte para `false` se quiser manter o fluxo local direto.
- Antes de uma versao estavel, revisar `docs/backlog-v1-estavel.md`.
- O remote `origin` ja esta configurado e os pushes recentes foram concluidos em `origin/master`.
