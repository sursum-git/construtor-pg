# Contexto do projeto

Este projeto e um motor frontend dinamico para telas CRUD usando JSON, Kendo UI for jQuery e jQuery.

Objetivo principal:

- Renderizar telas CRUD a partir de uma definicao JSON.
- Renderizar telas de processamento por parametros a partir de uma definicao JSON.
- Manter um padrao visual e comportamental reutilizavel.
- Servir como base futura para migrar sistemas existentes para telas configuradas por metadados.

Decisoes fechadas:

- Aplicacao separada na raiz do projeto.
- Frontend em HTML simples, sem build.
- Kendo local em `kendo/`.
- jQuery local em `vendor/jquery/jquery-4.0.0.min.js`.
- Tema atual principal: `kendo/styles/default-urban.css`.
- Existe backend inicial em `backend/` com Symfony, API Platform, PostgreSQL, runtime por metadados, auditoria, fila, sessao, autenticacao, permissoes reais por tela/acao, selecao de assinante, manter logado e escolha de area para administrador.
- O runtime agora tambem registra rastreabilidade estrutural nas transacoes, incluindo `programVersion`, `builderProgramVersionId`, `builderEntityVersionId`, `screenDefinitionVersion`, `schemaFingerprint`, `databaseIdentity`, `databaseEnvironment`, `customizationKind`, `grantId`, `requestCode`, `approvalId` e `testExecutionBundleId` quando aplicavel.
- Existem telas administrativas runtime para parametros, literais/traducoes, notificacoes, destinatarios de notificacoes, integracoes, sessoes, transacoes, logs de transacoes e jobs.
- As demos ainda podem usar dados mockados em memoria por `DemoMockHttpClient`.
- A pasta `kendo/` nao deve ser alterada.

Paginas principais:

- `home.html`: demo de pagina inicial por JSON, com appbar, seletor de sistema/modulo, Kendo TreeView lateral e abertura de programas.
- a Home agora tambem pode exibir central de notificacoes no appbar, com endpoint proprio ou agregacao de `alerts`, `requests` e `jobs`. Quando houver endpoint dedicado, o backend ja suporta notificacoes por usuario/grupo, marcacao de leitura por destinatario e navegacao contextual direta para a tela de destino com filtros/querystring segura.
- a Home tambem ja possui backend real para chat entre usuarios e atendimento: contatos por sessoes ativas, historico de conversa em `runtime_user_message`, atendentes online por grupos `support`/`support.<setor>`, solicitacoes persistidas em `runtime_support_request` e canal SSE proprio para novas mensagens, presenca e atualizacao de protocolo. O envio continua por `POST`; consultas de lista/status usam `GET` na mesma rota runtime (`/api/runtime/screens/{screenId}/endpoints/{endpointId}`) e o stream usa `?stream=1`.
- a central de notificacoes da Home agora tambem salva o ultimo recorte local por `screenId`, incluindo severidade, categoria, exigencia de acao e somente nao lidas, com botao dedicado para limpar filtros e reaplicar o estado ao reabrir a janela.
- a Home agora tambem preserva o contexto local da navegacao lateral por `screenId`, incluindo modulo atual, texto de busca e filtro de favoritos.
- a Home agora tambem restaura o ultimo programa aberto e o estado expandido/recolhido do menu lateral, respeitando o mesmo `screenId`.
- a Home agora tambem pode reabrir automaticamente a janela contextual de notificacoes ou jobs quando esse for o ultimo painel salvo do appbar.
- a Home, o CRUD runtime e o login agora reutilizam a mesma limpeza de contexto local para logout, token invalido, `SESSION_REVOKED`, `SESSION_EXPIRED` e `force_logout`, preservando apenas o ultimo usuario quando fizer sentido.
- `login.html`: demo visual de login com appbar, logo, lembrar acesso, selecao simulada de assinante, escolha de area para administrador e recuperacao de senha. O login agora tambem sabe carregar o bundle runtime de literais por locale, com fallback pt-BR embutido.
- o login web agora tambem limpa sessao local por botao proprio, preenche o ultimo usuario usado e ignora `rememberToken` expirado antes de tentar auto-login.
- `index.html`: demo principal de clientes.
- `exemplos.html`: indice central de exemplos.
- `theme-builder.html`: pagina de teste/geracao visual de temas.
- `program-builder.html`: interface visual para cadastrar modulos estruturais com abreviacao e faixa numerica inicial/final, modelar entidades `persistence`, `query`, `io` e agora `api`, criar tabela fisica quando fizer sentido, versionar a estrutura da entidade, configurar cadastro mestre versionado, usar assistente para referencias historicas, definir campo de codificacao customizada, cadastrar regras de negocio declarativas ou por classe/metodo, configurar chaves unicas compostas, marcar campos nao editaveis, dependencias/FKs com acoes e validar nomenclatura de tabela e campo conforme padrao Genesis-ERP, gerando programas CRUD ou custom a partir do catalogo runtime, com preview, historico de versoes, rollback e publicacao. Para `entityType=api`, o projeto agora cobre consulta externa em JSON/array, grid + formulario de visualizacao, cadastro reutilizavel de metadados da API com importacao OpenAPI/Swagger e um primeiro modo CRUD para APIs JSON previsiveis, com `create/update/delete` declarativos, sem tabela fisica, sem lock de escrita e sem JavaScript livre. Tambem existe suporte inicial a Odoo como provedor especifico dentro do cadastro de APIs, em modo somente leitura, com configuracao de `XML-RPC` e `JSON-RPC`, teste de conexao, leitura de metadados do modelo por `fields_get`, carga automatica dos campos na entidade API e publicacao de tela CRUD em modo consulta. A tela agora usa layout mais proximo de editor com arvore lateral, filtros rapidos por tipo/estado, badges por no, abas centrais e painel lateral de preview, propriedades, relacionamentos, comparativo e diagnostico, alem de reordenacao visual por arrastar e soltar, validacao incremental por item, lock de edicao por entidade/modulo/programa, importacao de tabelas PostgreSQL existentes para entidade + rascunho CRUD, importacao de SQL/DDL PostgreSQL com `CREATE TABLE` sem executar no schema real, importacao de JSON externo validado pelo backend antes de carregar a modelagem, assistente de integracao para gerar esqueleto de mapping a partir da entidade atual e assistente interno de IA com `kendoChat`, configuracao segura por parametros administrativos, entrada por texto/audio, carga do rascunho validado para revisao manual e consumo do bundle runtime de literais na propria interface.
- o construtor agora tambem registra ownership e politica de customizacao do programa (`standard`, `customer_overlay`, `customer_custom`), aplica gate de governanca para programa padrao com grant + lock + bundle de testes + aprovacao final, possui dialogs dedicados para governanca e rebase assistido de overlay, mostra checklist guiado antes do publish, exibe dashboard operacional com requests/grants/aprovacoes/testes/overlays/integridade, permite operar requests, grants e bundles no proprio dialogo, ajustar a retencao da governanca com preview/execucao e exportacao do relatorio, persiste historico da retencao em `program_governance_retention_run` com delta por tabela, `executionGroup`, relacao entre preview/aplicacao, `pairedRun`, comparativo antes/depois e agrupamento por execucao, aplica politica de ambiente para publicacao e prepara a resolucao runtime entre base padrao, overlay e variante especifica por assinante. O rebase agora bloqueia conflitos criticos, exige confirmacao explicita para conflitos leves, rejeita a escolha `overlay` em conflitos leves e devolve `policyDecision`, `policySummary`, `runtimeImpactSummary`, `finalResolutionSummary`, `finalDiffEntries` e `finalDiffDefinition`. Existe tambem a tela dedicada `admin.programa-governanca` para operar requests, grants, bundles, aprovacoes, retencao e rebase fora do editor, a entrada `admin.programa-auditoria-operacao` para focar timeline, filtros de data/tipo/usuario com persistencia local por programa, resumo por tipo/usuario/modo de retencao, sinais operacionais e acesso rapido ao contexto relacionado, e as entradas focadas `admin.programa-grants-operacao`, `admin.programa-aprovacoes-operacao`, `admin.programa-retencao-operacao`, `admin.programa-retencao-historico-operacao`, `admin.programa-operacoes-operacao`, `admin.programa-overlays-operacao` e `admin.programa-overlay-versoes-operacao` para operacao direta por notificacao/contexto.
- existe agora a tela administrativa `admin.integridade`, baseada em `system_record_integrity`, para monitorar o ultimo status de verificacao das assinaturas estruturais, disparar reassinatura controlada pela UI administrativa por `endpointId` seguro e operar em conjunto com os comandos `app:integrity:check`, `app:integrity:monitor` e `app:integrity:resign`.
- existe tambem a politica inicial de retencao de historico de governanca, com comando `app:governance:cleanup-history` para avaliar/aplicar limpeza de requests, grants, aprovacoes, bundles de teste e notificacoes administrativas antigas. A retencao agora pode ser parametrizada por `admin.parametros` e tambem ajustada no dialogo de governanca do `program-builder`.
- existe tambem o comando `app:governance:monitor`, que revisa grants congelados/revogados, overlays bloqueados, publicacoes padrao travadas por aprovacao pendente e integridade invalida, emitindo notificacoes administrativas idempotentes para a triagem operacional. O comando aceita `--fail-on-alert` para esteiras que precisem falhar quando houver pendencias relevantes. Para rodadas operacionais unificadas existe ainda `app:governance:operations`, combinando integridade, monitoramento e limpeza opcional da retencao, com leitura do ultimo snapshot pela UI focada de operacoes.
- existe agora tambem uma camada de provisionamento operacional, sem mudar a arquitetura atual do produto:
  - `php backend/bin/console app:install:bootstrap`
  - `php backend/bin/console app:subscriber:create`
  - `php backend/bin/console app:runtime:publish-defaults`
  - `php backend/bin/console app:update:check`
  - `php backend/bin/console app:update:apply <versao>`
  - `scripts/install-onprem.ps1`
  - `scripts/install-onprem.sh`
  - `scripts/provision-saas-subscriber.ps1`
  - `scripts/provision-saas-subscriber.sh`
  - o objetivo e automatizar banco, migrations, seed, validacao do catalogo padrao e criacao do assinante/admin inicial.
  - existe tambem a tela `admin.assinante-ambientes`, que salva o assinante, enfileira o provisionamento SaaS em job runtime, acompanha o status por SSE/polling e gera o pacote zip on-premise com `install.sh` para Ubuntu 24.04.
  - a instalacao inicial agora tambem pode ser preparada por executaveis Go separados por perfil (`Construtor de Sistemas` e `Assinante`), com precheck, ativacao central por codigo enviado ao e-mail cadastrado, sessao local obrigatoria antes de liberar `/api/install/run` e perfil compilado no binario.
  - a central de ativacao agora tambem possui cadastro persistente de licencas em `installer_activation_license`, publicado como `admin.instalacao-licencas`, com e-mail de ativacao, perfis/modos permitidos, validade, status, limite de ativacoes e historico resumido.
  - a central de ativacao agora tambem possui tokens internos em `installer_activation_service_token`, publicados como `admin.instalacao-tokens`, para provisionamento Docker SaaS sem confirmacao manual por e-mail.
  - licencas de instalacao podem limitar hosts por fingerprint, revogar fingerprints e registrar fingerprints usados em `metadata`.
  - existe tambem a tela central `admin.central-operacoes`, que consolida painel operacional, auditoria, revogacao de licencas/tokens/fingerprints, politica de tentativas, status de chaves, artefatos, saude dos assinantes e notificacoes derivadas.
  - a confirmacao por e-mail da ativacao agora registra tentativas invalidas e bloqueia temporariamente a requisicao conforme `APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS` e `APP_INSTALLER_ACTIVATION_BLOCK_MINUTES`.
  - artefatos do instalador podem ser assinados por HMAC e o executavel valida a assinatura antes de baixar manifesto, Compose ou pacote.
  - existe stack Docker Linux simples em `Dockerfile`, `compose.yaml` e `docker/`, com `app` (Nginx, PHP-FPM e Supervisor) e `database` (PostgreSQL 16). Existe tambem stack separada de producao em `compose.production.yaml`, com `nginx`, `php`, `worker` e `database`. O container nao instala automaticamente ao iniciar; a instalacao continua pela pagina local liberada pelo executavel.
  - a mesma tela agora tambem executa validacao previa de conflitos, checklist de prerequisitos, mostra progresso por etapa do job, permite retry parcial a partir de uma etapa especifica e exibe checksum SHA-256/assinatura opcional do pacote on-premise.
  - o cadastro do assinante agora tambem formaliza o modo de deployment (`shared_program_shared_db`, `shared_program_dedicated_db`, `dedicated_stack`, `onprem_remote`), separando ambiente principal isolado do ambiente runtime usado pelo assinante; no modo compartilhado, varios assinantes podem apontar para o mesmo ambiente runtime.
  - a mesma tela agora tambem expoe canal de update por assinante, auditoria dos ambientes runtime compartilhados, matriz operacional por assinante e catalogo administrativo das entidades persistentes globais x filtradas por assinante.
  - existe tambem a tela `admin.atualizacoes`, que le o manifesto de releases, avalia dependencias, aplica atualizacoes por job e mostra o impacto em programas padrao e customizados.
- provisionamento e gestao administrativa de atualizacoes ficam apenas no sistema central SaaS, identificado por `APP_SYSTEM_ROLE=saas_central` ou `APP_CENTRAL_CONTROL_ENABLED=1`.
- existe tambem a tela `admin.atualizacoes-assinantes`, que facilita consultar no sistema central o historico do que foi aplicado em cada assinante.
  - a frente agora tambem cobre anuencia formal por release, plano de rollout SaaS exportavel e runner on-premise (`update-onprem.sh|ps1` / `update.sh` no pacote).
  - a regra principal de versionamento da atualizacao agora aceita `requiresVersionMin`, `requiresAppliedUpdates[]`, `replaces[]`, `autoApply`, `breakingLevel` e `steps`, com cadeia obrigatoria resolvida por assinante-alvo no sistema central SaaS.
  - a politica da release agora tambem fica explicita no contrato do updater:
    - `metadata.requiresBackup`
    - `metadata.requiresMaintenanceMode`
    - `autoApplySaas`
    - `autoApplyOnPrem`
    - `requiresSubscriberConsent`
    - `blocksNextUpdates`
    - `internetRequired`
  - a politica tambem passou a aceitar defaults por categoria e override explicito com justificativa, alem de snapshot persistido no historico da execucao.
  - o manifesto tambem passa por validacao de coerencia antes de persistir ou publicar artefatos oficiais, bloqueando dependencias inexistentes, auto-referencias, versoes nao anteriores e ciclos.
  - o updater agora tambem suporta download e validacao do pacote da release, usando manifesto remoto em `APP_UPDATE_MANIFEST_URL`, assinatura do manifesto por `APP_UPDATE_MANIFEST_SIGNING_KEY` e assinatura do pacote por `APP_UPDATE_PACKAGE_SIGNING_KEY`.
  - existe tambem a publicacao oficial de manifesto e pacotes assinados por `app:update:publish-artifacts [versao]`, usando `APP_UPDATE_DISTRIBUTION_DIR` e `APP_UPDATE_PUBLIC_BASE_URL`.
  - a mesma publicacao agora tambem pode despachar os artefatos para destino externo real por `APP_UPDATE_DISTRIBUTION_PUSH_URL`, `APP_UPDATE_DISTRIBUTION_PUSH_TOKEN`, `APP_UPDATE_DISTRIBUTION_PUSH_SIGNING_KEY` e `APP_UPDATE_DISTRIBUTION_PUSH_TIMEOUT`.
  - o rollout externo do SaaS agora pode ser despachado por HTTP assinado usando `APP_UPDATE_ORCHESTRATOR_URL`, `APP_UPDATE_ORCHESTRATOR_TOKEN`, `APP_UPDATE_ORCHESTRATOR_SIGNING_KEY` e `APP_UPDATE_ORCHESTRATOR_TIMEOUT`.
  - existe agora tambem um receptor externo real do webhook em `scripts/orchestrator/system-update-orchestrator.php`, com config por assinante, validacao de token/assinatura, log proprio e execucao de `docker compose pull/up` por target.
  - o updater SaaS agora tambem suporta janela agendada de rollout por release, batches/canario por assinante, despacho progressivo por lote, auditoria operacional do rollout e bloqueio temporario de entrada do tenant durante rollout critico usando estado local em `APP_SAAS_ROLLOUT_STATE_FILE`.
  - no on-premise, a abertura da Home pode endurecer atualizacao critica pendente por `APP_UPDATE_ONPREM_CRITICAL_POLICY=warn|block`, com bloqueio modal e endpoint runtime local `POST /api/runtime/system-updates/run-pending`.
  - no on-premise, a politica critica agora tambem separa modo de acao (`APP_UPDATE_ONPREM_CRITICAL_MODE=auto|prompt_admin|download_only`) da politica de acesso (`APP_UPDATE_ONPREM_CRITICAL_ACCESS_POLICY=warn|block`), com endpoint local adicional `POST /api/runtime/system-updates/download-pending-critical`.
  - a politica de atualizacao respeita o modelo atual de programas:
    - `standard`: pode receber a nova release;
    - `customer_overlay`: entra em analise de rebase/compatibilidade, sem sobrescrita automatica;
    - `customer_custom`: permanece congelado e so recebe sinalizacao de impacto.
  - quando a release declarar `programUpdates`, o updater agora tambem classifica cada programa padrao como instalacao nova, upgrade da base padrao, validacao da versao atual ou ambiente acima da meta declarada.
  - depois de `migrate`, `seed_runtime_metadata` e `publish_runtime_defaults`, a aplicacao da release valida se o programa padrao realmente ficou publicado na versao alvo; se nao ficou, a execucao falha.
  - quando o impacto do overlay vier como `rebase_ok`, a propria release ja cria rascunho de rebase sobre a base publicada; conflitos leves ficam em revisao e conflitos bloqueantes continuam sem automacao.
  - a consulta central por assinante agora tambem aceita filtro por status, categoria e periodo, com resumo do recorte e exportacao JSON/CSV.
  - updates opcionais no SaaS agora podem depender de ativacao explicita por assinante, registrada no sistema central antes da aplicacao.
  - a mesma frente agora tambem possui changelog estruturado por release, politica por canal (`stable`, `pilot`, `canary`, `lts`), pre-check de compatibilidade antes do apply, simulacao administrativa da release, rollback formal da release e catalogo validado de steps/rollback steps.
  - a tela `admin.atualizacoes` agora mostra resumo explicito da cadeia (`requiresVersionMin`, `requiresAppliedUpdates[]`, `replaces[]`, `breakingLevel`, `steps`), pre-check por release, simulacao e dashboards de atraso/alerta operacional.
  - a tela `admin.atualizacoes-assinantes` agora tambem exibe timeline resumida por assinante para checagem, download, anuencia, ativacao, apply, rollout, falha e rollback.
  - existe agora tambem o comando `app:update:saas-cycle`, pensado para o sistema central detectar releases sem depender da UI, criar o job administrativo e deixar o worker aplicar os steps em ordem.
  - quando a release nao declarar `steps`, o updater agora assume uma esteira padrao por categoria para garantir migrations, seed/publicacao default e verificacao de integridade.
- os registros estruturais principais do builder/runtime agora podem ser protegidos por assinatura de integridade em `system_record_integrity`, com checagem no backend para detectar alteracao fora do fluxo oficial. A cobertura inclui programa, versao, entidade, campos de entidade (`builder_field`), revisao da entidade, situacoes e transicoes da entidade, tela, endpoint, overlay, versao de overlay, `builder_api_source`, `builder_module`, `runtime_lock_policy`, `system_parameter`, `system_parameter_value`, `system_option_list`, `system_option`, `import_export_mapping`, `import_export_mapping_version`, `import_export_schedule`, `auth_provider_config`, `auth_subscriber`, `auth_user_subscriber` e `system_literal_translation`. A reassinatura controlada registra `auditTrail` com motivo, usuario, horario, hash anterior e status antes/depois.
- o construtor agora tambem permite marcar entidades persistentes com `subscriberIsolation.mode=none|subscriber_column`, para suportar tabelas globais e tabelas filtradas por assinante no proprio runtime CRUD.
- para `entityType=persistence`, `subscriberIsolation.mode=none` agora exige confirmacao explicita da tabela como global compartilhada; sem isso o builder bloqueia o salvamento.
- o frontend CRUD agora tambem possui catalogo interno de literais pt-BR para mensagens operacionais e de validacao, com suporte a `titleKey/titleParams` e `messageKey/messageParams` retornados pelo backend, preservando fallback para textos legados.
- `import_export_mapping`: catalogo inicial de integracoes entre entidades e arquivos, com preview, execucao manual, historico persistido, versionamento do proprio mapping e agendamento basico. A primeira entrega suporta origem em entidade `persistence`, `api` generica, `api` Odoo readonly e arquivo XML declarativo; destino em entidade local, API generica JSON previsivel, `csv`, `xml` e `txt_layout`. No TXT agora existem tres formas de estruturar os registros: posicional fixo (`lineMode="fixed"`), por separador (`lineMode="delimited"`) e arvore hierarquica com `nodeType=record|group|totalizer`, adequada para leiautes com pai, filho e totalizadores. Em XML, a engine agora tambem aceita raiz com namespaces/atributos, nos hierarquicos em `xmlLayouts[]`, atributos por no, filhos repetitivos por `sourceAlias`, vinculo pai/filho por `linkBy` e importacao por `recordPath + fields[].xpath`.
- `desktop-wpf/`: MVP separado em WPF para validar uma experiencia desktop de arvore de objetos, propriedades contextuais e preview JSON, sem alterar o fluxo web atual.
- `examples/pages/*.html`: paginas isoladas por variacao de configuracao.
- `examples/pages/manual-programas.html`: manual operacional e funcional navegavel por programa, com indice em `TreeView`.
- `examples/pages/admin-integridade.html`: pagina local de smoke da tela administrativa de integridade estrutural.
- `examples/pages/admin-program-governance.html`: pagina local de smoke da tela dedicada de governanca de programas.
- `examples/pages/admin-program-grants.html`: pagina local de smoke da operacao focada em grants.
- `examples/pages/admin-program-approvals.html`: pagina local de smoke da operacao focada em aprovacoes.
- `examples/pages/admin-program-retention.html`: pagina local de smoke da operacao focada em retencao.
- `examples/pages/admin-program-audit.html`: pagina local de smoke da operacao focada em auditoria da governanca.
- `examples/pages/admin-program-overlays.html`: pagina local de smoke da operacao focada em overlays e rebase por assinante.
- `examples/pages/admin-program-overlay-versions.html`: pagina local de smoke da operacao focada em historico, comparacao e publicacao de versoes de overlay.
- `examples/pages/admin-subscriber-provisioning.html`: pagina local de smoke do provisionamento de assinantes.
- `examples/pages/program-builder-governance.html`: pagina local de smoke do fluxo governado do construtor, com solicitacao, grant, bundle de testes, aprovacao, publish e rebase de overlay.
- `production/app.html`: entrada de producao para CRUD por `screenId`.
- `production/app.html`: entrada de producao por `screenId`, cobrindo `crud`, `process` e `custom`.
- `production/home.html`: entrada de producao para Home por `screenId`.
- `production/login.html`: entrada de producao para login, manter logado, selecao de assinante, escolha de area para administrador e recuperacao de senha.
- `production/program-builder.html`: entrada da interface visual do construtor ligada ao backend real.
- `production/app.html?screenId=admin.literais`: tela administrativa para manter literais e traducoes por locale, carregadas pelo frontend via bundle runtime com fallback para o dicionario pt-BR embutido.
- `production/app.html?screenId=admin.notificacoes`: tela administrativa para cadastrar notificacoes runtime por usuario ou grupo.
- `production/app.html?screenId=admin.notificacao-destinatarios`: tela administrativa para acompanhar entrega e leitura por destinatario.
- `production/app.html?screenId=admin.integracoes`: tela administrativa para cadastro, preview e execucao manual de importacao/exportacao. A tela agora possui editor visual com `TreeView` para TXT/XML, inspetor de no, preview estrutural lado a lado, historico persistido de execucoes, historico de versoes do mapping e aba de agendamentos.
- `production/app.html?screenId=admin.integracoes`: a mesma tela agora tambem filtra o historico persistido por mapping/modo/status, mostra detalhe da execucao selecionada e permite exportar o payload da execucao em JSON pela propria UI.
- `production/app.html?screenId=admin.integracoes`: a tela agora tambem restaura aba ativa, mapping em edicao, filtros do historico persistido e a ultima execucao selecionada ao recarregar a pagina.
- `production/app.html?screenId=admin.integracoes`: a restauracao operacional agora inclui tambem a ultima versao selecionada do mapping e o agendamento selecionado.
- `production/app.html?screenId=admin.integracoes`: a tela agora tambem preserva a selecao do no do editor visual entre recargas e mostra um comparativo simples entre preview e execucao quando ambos existem.
- `production/app.html?screenId=admin.integracoes`: a tela agora tambem preserva a selecao do no do preview estrutural entre recargas.
- `production/app.html?screenId=admin.programa-solicitacoes`: tela administrativa para solicitacoes formais de alteracao em programas padrao.
- `production/app.html?screenId=admin.programa-grants`: tela administrativa para grants temporarios de edicao/publicacao.
- `production/app.html?screenId=admin.programa-testes`: tela administrativa para bundles e execucoes de roteiros obrigatorios.
- `production/app.html?screenId=admin.programa-aprovacoes`: tela administrativa para aprovacao final de publicacao.
- `production/app.html?screenId=admin.programa-governanca`: tela dedicada para operar requests, grants, bundles, aprovacoes, retencao e rebase por programa.
- `production/app.html?screenId=admin.programa-auditoria-operacao`: entrada focada para timeline, sinais operacionais e historico detalhado da governanca.
- `production/app.html?screenId=admin.programa-grants-operacao`: entrada focada para grants, usada por notificacoes administrativas e operacao direta.
- `production/app.html?screenId=admin.programa-aprovacoes-operacao`: entrada focada para aprovacoes, usada por notificacoes administrativas e operacao direta.
- `production/app.html?screenId=admin.programa-retencao-operacao`: entrada focada para retencao, usada por notificacoes/contexto e ajuste rapido da politica.
- `production/app.html?screenId=admin.programa-retencao-historico-operacao`: entrada focada para historico persistido da retencao, com comparativo preview x aplicacao por `executionGroup`.
- `production/app.html?screenId=admin.programa-overlays-operacao`: entrada focada para overlays, usada para revisar assinantes, congelamento e rebase.
- `production/app.html?screenId=admin.programa-operacoes-operacao`: entrada focada para operacoes unificadas de governanca, com monitoramento, snapshot e disparo manual da rotina consolidada.
- `production/app.html?screenId=admin.programa-overlay-versoes-operacao`: entrada focada para historico, comparacao e publish das versoes do overlay.
- `production/app.html?screenId=admin.programa-overlays`: tela administrativa para overlays e variantes por assinante.
- `production/app.html?screenId=admin.programa-overlay-versoes`: tela administrativa para o historico versionado dessas customizacoes.

Arquivos de configuracao e dados:

- `examples/home.home.json`: JSON completo da demo da pagina inicial.
- `examples/clientes.crud.json`: JSON completo da demo.
- `examples/processamento-relatorio.process.json`: JSON completo da demo de processamento por parametros.
- `public/metadata/schemas/home-definition-v1.schema.json`: schema inicial da home.
- `public/metadata/schemas/crud-definition-v1.schema.json`: schema inicial.
- `public/metadata/schemas/process-definition-v1.schema.json`: schema inicial de processamento.
- `public/config/crud-engine.production.config.json`: configuracao segura para primeira versao de producao.
- `src/demo/demo-embedded-data.js`: dados e configuracao embutidos para uso via `file://`.
- `src/demo/DemoMockHttpClient.js`: mock HTTP em memoria.

Documentos importantes:

- `especificacao-crud-engine-v1.md`: especificacao inicial maior.
- `docs/arquitetura-home-engine.md`: arquitetura da pagina inicial por JSON.
- `docs/backlog-v1-estavel.md`: memoria de pontos para estabilizacao futura.
- `docs/roteiro-teste-web.md`: roteiro funcional para validar as telas web em demo e producao local.
- `docs/desktop-builder-mvp-wpf.md`: escopo e limites do MVP desktop em WPF.
- `docs/paridade-demo-producao.md`: controle do que mudou na demo e precisa, ou nao, ser levado para producao.
- `docs/estado-local-persistido.md`: guia operacional do que fica salvo localmente, por contexto, e o que deve ser limpo no logout.
- `docs/provisionamento-saas-onprem.md`: guia operacional do provisionamento SaaS e on-premise sem alterar a estrutura atual.
- `docs/manual-instalacao.md`: manual detalhado da instalacao por executavel Go, ativacao, precheck, Docker, Linux nativo, Windows teste e SaaS.
- `docs/checklist-producao-instalacao.md`: checklist dos passos finais para central real, cadastros, artefatos, testes externos, distribuicao e endurecimento.
- `docs/instalacao-central-real.env.example`: modelo de variaveis da central real.
- `scripts/installer/`: scripts para gerar token SaaS, validar configuracao da central e publicar artefatos assinados.
- `docs/roteiro-validacao-funcional-analista.md`: passo a passo para analista funcional validar o sistema partindo do login e chegando ate a criacao/publicacao de um programa novo.
- os estados JSON persistidos localmente agora usam envelope versionado, com compatibilidade de leitura para chaves antigas.
- `docs/guia-ia-padrao-kendo-grids-formularios.pdf`: guia para IA padronizar outro projeto Kendo/PHP/Symfony.
- `docs/guia-ia-padrao-kendo-grids-formularios.html`: fonte do PDF.

Estado do Git:

- Branch usada nesta linha de trabalho: `master`.
- O repositorio ja possui `origin` configurado e os pushes recentes foram executados por `origin/master`.

Como iniciar uma nova sessao:

1. Leia este arquivo.
2. Leia `docs/arquitetura-crud-engine.md`.
3. Leia `docs/padroes-ui-kendo.md`.
4. Se for alterar codigo, leia `docs/continuidade-codex.md`.
5. Se for alterar a pagina inicial, leia `docs/arquitetura-home-engine.md`.
6. Se for alterar demo, exemplos ou mocks, use a skill `construtor-pg-demo-production-parity` e leia `docs/paridade-demo-producao.md`.
7. Depois leia apenas os arquivos especificos da funcionalidade.
