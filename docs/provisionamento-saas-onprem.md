# Provisionamento SaaS e on-premise

Este fluxo nao altera a estrutura atual do sistema. Ele so automatiza passos que hoje seriam manuais.

Manual detalhado da instalacao:

- [manual-instalacao.md](manual-instalacao.md)

## O que entrou

Comandos novos no backend:

- `php backend/bin/console app:install:bootstrap`
- `php backend/bin/console app:subscriber:create`
- `php backend/bin/console app:runtime:publish-defaults`

Instalador inicial via navegador:

- pagina de producao: [C:\construtor-pg\production\install.html](C:/construtor-pg/production/install.html)
- API: `GET /api/install/status`, `POST /api/install/precheck` e `POST /api/install/run`
- pagina local de demonstracao: [C:\construtor-pg\examples\pages\system-install.html](C:/construtor-pg/examples/pages/system-install.html)
- antes de executar a pagina real, rode o instalador compilado do perfil correto:
  - `construtor-builder-installer-*` para Construtor de Sistemas;
  - `construtor-subscriber-installer-*` para Assinante.
- o instalador compilado valida o codigo do assinante na central, confirma o codigo enviado ao e-mail cadastrado e grava a sessao local em `backend/var/install/activation-session.json`.
- a API `/api/install/run` recusa instalacao sem sessao de ativacao valida.
- o instalador executa somente comandos fechados, sem comando livre vindo da tela.
- na primeira instalacao, a senha do instalador informada na tela e salva como hash em `APP_INSTALLER_PASSWORD_HASH`.
- depois do sucesso, a API grava `APP_SYSTEM_INSTALLED=1` em `backend/.env.local`.
- para reinstalar, a tela exige a senha do instalador e uma confirmacao explicita de reinstalacao.
- para reinstalar tambem e obrigatorio executar nova ativacao pelo instalador compilado.
- quando a opcao de salvar configuracao estiver marcada, a API atualiza tambem as demais chaves permitidas em `backend/.env.local`.
- depois de salvar `.env.local`, reinicie o processo web para que o backend passe a usar as novas variaveis.

Docker Linux de producao:

- `Dockerfile`
- `compose.yaml`
- `docker/nginx/default.conf`
- `docker/supervisor/construtor-pg.conf`
- `docker/entrypoint.sh`
- servicos:
  - `app`: Nginx, PHP-FPM e Supervisor no mesmo container;
  - `database`: PostgreSQL 16;
- volumes persistentes para banco, `.env.local`, estado de ativacao e arquivos compartilhados;
- o container nao instala automaticamente ao iniciar;
- o worker fica parado por padrao e so consome fila quando `APP_WORKER_ENABLED=1`;
- se `8080` estiver ocupada, usar `APP_HTTP_PORT=<porta>` antes de `docker compose up -d`.

Variaveis da ativacao local:

- `APP_INSTALLATION_SESSION_REQUIRED=1`
- `APP_INSTALLATION_SESSION_SIGNING_KEY=<chave compartilhada com a central>`
- `APP_INSTALLATION_SESSION_FILE=<arquivo da sessao criada pelo executavel>`
- depois do sucesso, o backend grava:
  - `APP_INSTALLATION_TYPE`
  - `APP_ACTIVATION_SUBSCRIBER_CODE`
  - `APP_ACTIVATION_PROOF_HASH`

Executaveis de instalacao:

- fonte: `installer/`
- tecnologia: Go
- binarios previstos:
  - `construtor-builder-installer-linux`
  - `construtor-subscriber-installer-linux`
  - `construtor-builder-installer.exe`
  - `construtor-subscriber-installer.exe`
- Windows e apenas para teste sem Docker.
- Linux cobre Docker on-premise, nativo on-premise e Docker SaaS por token interno.
- modo de precheck:

```bash
./construtor-subscriber-installer-linux --precheck --mode=docker --subscriber-code=cliente-x --activation-url=https://central.exemplo
```

Contrato HTTP esperado da central:

- `POST /api/installer/activation/request`
- `POST /api/installer/activation/confirm`
- `POST /api/installer/activation/service`
- `GET /health`

O proprio backend entrega uma primeira central de ativacao por esses endpoints. Configuracao minima:

- `APP_INSTALLER_ACTIVATION_SUBSCRIBERS='{"cliente-x":{"email":"responsavel@cliente.com"}}'`
- `APP_INSTALLER_ACTIVATION_SIGNING_KEY=<chave-para-assinar-sessoes>`
- `APP_INSTALLER_ACTIVATION_FROM=no-reply@seudominio.com`
- `APP_INSTALLER_ACTIVATION_SERVICE_TOKEN=<token-interno-saas>`

A resposta final da central deve devolver `activationProof`, `profile`, `subscriberCode`, `mode`, `sessionId`, `issuedAt` e `expiresAt`; opcionalmente `manifestUrl`, `dockerComposeUrl` e `packageUrl`.

Licencas de instalacao:

- tabela: `installer_activation_license`;
- tela: `production/app.html?screenId=admin.instalacao-licencas`;
- controla e-mail de ativacao, perfis permitidos, modos permitidos, validade, status, limite de ativacoes e historico resumido;
- a central consulta a tabela primeiro e usa `APP_INSTALLER_ACTIVATION_SUBSCRIBERS` apenas como fallback de transicao.

Scripts operacionais:

- [C:\construtor-pg\scripts\install-onprem.ps1](C:/construtor-pg/scripts/install-onprem.ps1)
- [C:\construtor-pg\scripts\install-onprem.sh](C:/construtor-pg/scripts/install-onprem.sh)
- [C:\construtor-pg\scripts\provision-saas-subscriber.ps1](C:/construtor-pg/scripts/provision-saas-subscriber.ps1)
- [C:\construtor-pg\scripts\provision-saas-subscriber.sh](C:/construtor-pg/scripts/provision-saas-subscriber.sh)

Atualizacao operacional:

- `php backend/bin/console app:update:check`
- `php backend/bin/console app:update:apply <versao>`
- `php backend/bin/console app:update:run-pending`
- `php backend/bin/console app:update:saas-cycle`
- `php backend/bin/console app:update:rollout-plan <versao>`
- `php backend/bin/console app:update:publish-artifacts [versao]`
- `screenId=admin.atualizacoes`
- pagina de producao: [C:\construtor-pg\production\admin\system-updates.html](C:/construtor-pg/production/admin/system-updates.html)
- pagina local: [C:\construtor-pg\examples\pages\admin-system-updates.html](C:/construtor-pg/examples/pages/admin-system-updates.html)
- [C:\construtor-pg\scripts\update-onprem.sh](C:/construtor-pg/scripts/update-onprem.sh)
- [C:\construtor-pg\scripts\update-onprem.ps1](C:/construtor-pg/scripts/update-onprem.ps1)
- o manifesto remoto pode ser configurado por `APP_UPDATE_MANIFEST_URL`;
- a validacao de assinatura do manifesto usa `APP_UPDATE_MANIFEST_SIGNING_KEY`;
- a validacao de assinatura do pacote usa `APP_UPDATE_PACKAGE_SIGNING_KEY`;
- a publicacao oficial dos artefatos usa:
  - `APP_UPDATE_DISTRIBUTION_DIR`
  - `APP_UPDATE_PUBLIC_BASE_URL`
- a distribuicao externa dos artefatos pode ser feita logo apos a publicacao oficial por:
  - `APP_UPDATE_DISTRIBUTION_PUSH_URL`
  - `APP_UPDATE_DISTRIBUTION_PUSH_TOKEN`
  - `APP_UPDATE_DISTRIBUTION_PUSH_SIGNING_KEY`
  - `APP_UPDATE_DISTRIBUTION_PUSH_TIMEOUT`
- o rollout externo do SaaS pode ser integrado por:
  - `APP_UPDATE_ORCHESTRATOR_URL`
  - `APP_UPDATE_ORCHESTRATOR_TOKEN`
  - `APP_UPDATE_ORCHESTRATOR_SIGNING_KEY`
  - `APP_UPDATE_ORCHESTRATOR_TIMEOUT`
- as telas administrativas de provisionamento e atualizacao ficam apenas no sistema central SaaS, identificado por `APP_SYSTEM_ROLE=saas_central` ou `APP_CENTRAL_CONTROL_ENABLED=1`;
- o sistema do assinante fica apenas com o necessario para verificacao/aplicacao local, como `GET /api/runtime/system-updates/summary` e o runner on-premise;
- existe a tela `screenId=admin.atualizacoes-assinantes` para consultar, por assinante, o historico do que foi aplicado pelo sistema central.
- a consulta por assinante agora tambem aceita filtros por status, categoria e periodo, com exportacao JSON/CSV do recorte atual.
- existe tambem o download/validacao do pacote por release, com registro local em `var/system-updates/<versao>/`.
- existe tambem a publicacao oficial de manifesto e pacote assinados em `var/system-updates/distribution/<versao-ou-catalog>/`.
- quando o destino externo estiver configurado, a mesma operacao despacha `manifest.json`, `SHA256SUMS`, `publication.json` e os pacotes para o endpoint externo assinado, deixando o app desacoplado do provedor final.
- existe tambem o despacho do rollout do SaaS para orquestrador externo por HTTP assinado; o app nao executa Docker diretamente.
- o repositorio agora tambem entrega o receptor externo desse webhook em:
  - `scripts/orchestrator/system-update-orchestrator.php`
  - `scripts/orchestrator/run-system-update-orchestrator.sh`
  - `scripts/orchestrator/system-update-orchestrator.config.sample.json`

Tela administrativa:

- `screenId=admin.assinante-ambientes`
- pagina de producao: [C:\construtor-pg\production\admin\subscriber-provisioning.html](C:/construtor-pg/production/admin/subscriber-provisioning.html)
- pagina local: [C:\construtor-pg\examples\pages\admin-subscriber-provisioning.html](C:/construtor-pg/examples/pages/admin-subscriber-provisioning.html)

## Modelos de deployment do assinante

O cadastro do assinante agora formaliza o modo de deployment sem mudar a arquitetura atual:

- `shared_program_shared_db`
- `shared_program_dedicated_db`
- `dedicated_stack`
- `onprem_remote`

Regras importantes:

- o ambiente principal continua isolado;
- o ambiente runtime do assinante pode ser diferente do principal;
- no modo `shared_program_shared_db`, varios assinantes podem apontar para o mesmo ambiente runtime;
- esse ambiente compartilhado nao substitui o ambiente principal isolado.
- o cadastro administrativo agora tambem registra `updateChannel` por assinante (`stable`, `pilot`, `canary`, `lts`);
- a tela central agora tambem expõe:
  - auditoria dos ambientes runtime compartilhados;
  - matriz operacional por assinante;
  - catalogo das entidades persistentes globais x filtradas por assinante;
  - riscos de isolamento quando a tabela persistente nao estiver claramente marcada.

## Isolamento por assinante nas entidades persistentes

O construtor agora formaliza dois comportamentos para entidade persistente:

- `subscriberIsolation.mode=none`
- `subscriberIsolation.mode=subscriber_column`

Regras:

- `subscriber_column` exige coluna fisica do assinante na entidade;
- `none` agora exige confirmacao explicita da tabela como global compartilhada;
- o runtime CRUD injeta o assinante no `create`, filtra `read/get` e limita `update/delete` quando a entidade usa `subscriber_column`;
- tabelas globais continuam permitidas para catalogos compartilhados, como estado, cidade e referencias publicas.

Job assíncrono:

- `subscriber.environment.provision`

## Fluxo SaaS

1. cadastrar ou atualizar o assinante na tela administrativa;
2. clicar em `Criar ambiente`;
3. a tela enfileira o job `subscriber.environment.provision`;
4. a UI acompanha o status do job por SSE quando disponivel e cai para polling quando necessario;
5. ao concluir, o detalhamento do job mostra o resumo final e o rastro do script executado.

Capacidades operacionais atuais da tela:

- validacao previa de conflitos antes do enfileiramento;
- checklist de prerequisitos operacionais;
- painel de progresso por etapa;
- retry parcial a partir de `prepare_env`, `start_database`, `bootstrap_app`, `create_subscriber` ou `publish_defaults`;
- relatorio final do provisionamento com etapas reaproveitadas ou falhas;
- pacote on-premise com checksum SHA-256 e assinatura opcional.

Pre-requisito operacional:

- manter o worker de jobs ativo no ambiente onde a tela administrativa estiver rodando.

## Fluxo recomendado

### 1. Bootstrap da aplicacao

Executa, na ordem:

1. criacao opcional do banco configurado;
2. migrations;
3. seed de metadados runtime;
4. validacao do catalogo padrao;
5. monitor de integridade estrutural.

Exemplo:

```powershell
php backend/bin/console app:install:bootstrap --create-database --database-environment=prod --database-identity=onprem:cliente-x
```

## 2. Criacao de assinante

Cria ou atualiza o assinante e opcionalmente prepara o administrador inicial.

Exemplo:

```powershell
php backend/bin/console app:subscriber:create --code=cliente-x --name="Cliente X" --document="00.000.000/0001-00" --admin-username=admin --admin-password="Senha@123"
```

Opcoes principais:

- `--principal`
- `--disabled`
- `--user-tenant-id`
- `--admin-display-name`
- `--admin-email`
- `--no-admin-default`
- `--force-password-change`

## 3. Publicacao/validacao base

Valida se o runtime atual tem pelo menos o catalogo essencial publicado.

Exemplo:

```powershell
php backend/bin/console app:runtime:publish-defaults --fail-on-missing
```

Se quiser reaplicar o seed antes da validacao:

```powershell
php backend/bin/console app:runtime:publish-defaults --refresh --fail-on-missing
```

## SaaS com base dedicada

Script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\provision-saas-subscriber.ps1 -SubscriberCode cliente-x -SubscriberName "Cliente X" -AdminPassword "Senha@123"
```

O script:

1. monta `DATABASE_URL` para a base do assinante;
2. grava `backend/.env.local`;
3. sobe o Postgres do `backend/compose.yaml` com `docker compose -p construtor-pg-<assinante>`;
4. roda bootstrap;
5. cria o assinante;
6. valida o catalogo padrao.

## On-premise

O cenario on-premise agora pode ser atendido de duas formas:

1. instalador compilado com Docker;
2. instalador compilado sem Docker;
3. execucao direta do script de instalacao no repositorio, apenas para ambiente controlado;
4. download do pacote zip pela tela administrativa.

Para producao on-premise nova, prefira o instalador compilado. A pagina `production/install.html` continua responsavel pelas etapas finais, mas so fica liberada depois da sessao local de ativacao.

O pacote zip e gerado por:

- `GET /api/admin/subscriber-provisioning/onprem-package`

Ele entrega um arquivo com:

- codigo-fonte necessario do projeto;
- `install.sh` na raiz;
- `scripts/install-onprem.sh`;
- `.env.template`;
- `README-INSTALACAO.txt`.

Metadados devolvidos para a tela antes do download:

- `fileName`
- `size`
- `sha256`
- `signature` opcional
- `generatedAt`

O alvo previsto e:

- Linux Ubuntu 24.04

O `install.sh` da raiz valida o ambiente e delega a instalacao completa para `scripts/install-onprem.sh`.

### Execucao direta no repositorio

Script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\install-onprem.ps1 -InstanceCode cliente-x -DatabaseIdentity "onprem:cliente-x" -AdminPassword "Senha@123"
```

O script:

1. grava `backend/.env.local`;
2. sobe o Postgres do `backend/compose.yaml`;
3. executa bootstrap;
4. cria o assinante principal e o admin inicial;
5. valida o catalogo padrao.

## Limites desta automacao

O provisionador:

- nao muda login;
- nao muda runtime multi-tenant;
- nao muda governanca;
- nao muda versionamento;
- nao cria uma arquitetura nova de container da aplicacao.

Ele so automatiza:

- configuracao de ambiente;
- migrations;
- seeds;
- criacao de assinante;
- validacao do catalogo padrao.

## Atualizacao do ambiente

O mesmo grupo operacional agora cobre a leitura e aplicacao de releases do sistema, sem alterar a arquitetura atual.

Regras fechadas:

- `security_critical` pode ser autoenfileirada conforme a politica do ambiente.
- `required_structural` bloqueia atualizacoes posteriores enquanto a cadeia obrigatoria nao for aplicada.
- cada release pode declarar:
  - `version`
  - `requiresVersionMin`
  - `requiresAppliedUpdates[]`
  - `replaces[]`
  - `category`
  - `autoApply`
  - `breakingLevel`
  - `steps`
- a politica de aplicacao da release agora precisa ficar explicita no contrato:
  - `metadata.requiresBackup`
  - `metadata.requiresMaintenanceMode`
  - `autoApplySaas`
  - `autoApplyOnPrem`
  - `requiresSubscriberConsent`
  - `blocksNextUpdates`
  - `internetRequired`
- o manifesto tambem pode declarar:
  - `channels[]`
  - `metadata.changelog`
  - `metadata.rollbackStep`
  - `metadata.rollbackSteps[]`
  - `metadata.requiresBackup`
  - `metadata.requiresMaintenanceMode`
- a avaliacao da cadeia obrigatoria considera o assinante alvo no sistema central SaaS; uma release aplicada em outro assinante nao libera a cadeia deste assinante.
- `replaces[]` cobre supersedencia: quando uma release aplicada substitui outra, a dependência anterior passa a ser considerada satisfeita para a cadeia.
- a politica operacional padrao por categoria fica assim:

| Categoria | Backup | Manutencao | Auto SaaS | Auto on-prem | Exige anuencia | Bloqueia proximas |
| --- | --- | --- | --- | --- | --- | --- |
| `security_critical` | nao | nao | sim | sim | nao | sim |
| `required_structural` | sim | sim | sim | nao | sim | sim |
| `optional_visual` | nao | nao | nao | nao | sim | nao |
| `recommended` | nao | nao | nao | nao | sim | nao |

- quando uma release fugir dessa matriz, o manifesto deve declarar:
  - `metadata.applicationPolicyOverride=true`
  - `metadata.applicationPolicyOverrideJustification`
- o manifesto agora passa por validacao de coerencia antes de persistir ou publicar artefatos:
  - dependencia para version inexistente;
  - `replaces[]` para version inexistente;
  - auto-referencia;
  - dependencia para versao nao anterior;
  - ciclos em `requiresAppliedUpdates[]`.
- releases com `requiresSubscriberConsent=true` exigem anuencia formal antes da aplicacao normal.
- no SaaS, releases opcionais podem depender de ativacao explicita por assinante antes do apply.
- no SaaS, releases criticas e estruturais agora tambem podem declarar:
  - `metadata.saasRolloutWindow.startAt`
  - `metadata.saasRolloutWindow.durationMinutes`
  - `metadata.saasRolloutWindow.freezeNewSessions`
  - `metadata.saasRolloutBatches[]`
- quando houver batches, a tela `admin.atualizacoes` pode despachar rollout progressivo por lote/canario sem perder o historico por assinante.
- o estado temporario de bloqueio de entrada do tenant durante rollout SaaS critico fica em `APP_SAAS_ROLLOUT_STATE_FILE`, escrito pelo orquestrador externo e lido pela Home local.
- no on-premise, o comportamento critico agora separa:
  - modo de acao: `APP_UPDATE_ONPREM_CRITICAL_MODE=auto|prompt_admin|download_only`
  - politica de acesso: `APP_UPDATE_ONPREM_CRITICAL_ACCESS_POLICY=warn|block`
- `APP_UPDATE_ONPREM_CRITICAL_POLICY` continua aceito como legado:
  - `warn|block` para acesso
  - ou `auto|prompt_admin|download_only` em ambientes antigos que ainda usem a variavel unica
- atualizacoes de programas padrao respeitam a politica atual de customizacao:
  - `standard`: atualiza pelo pacote da release;
  - `customer_overlay`: apenas gera impacto e fluxo de rebase, sem sobrescrita direta;
  - `customer_custom`: permanece congelado e nao sofre substituicao automatica.
- quando a release declarar `programUpdates`, o updater agora classifica:
  - programa padrao novo como instalacao controlada;
  - versao padrao nova como upgrade da base;
  - base ja na meta como validacao;
  - base acima da meta como ambiente adiantado.
- depois de `migrate`, `seed_runtime_metadata` e `publish_runtime_defaults`, a propria execucao valida se o programa padrao ficou publicado na versao alvo; se nao ficou, a release falha.
- quando o impacto do overlay vier limpo (`rebase_ok`), a aplicacao da release ja cria um draft de rebase sobre a base publicada e registra isso no historico por assinante; conflito leve fica em revisao e conflito bloqueante continua fora da automacao.
- manifesto remoto sem confianca nao deve seguir como release aplicavel; a verificacao pode usar `APP_UPDATE_MANIFEST_SIGNING_KEY` com `hmac-sha256`.
- antes do apply, o updater agora executa pre-check de compatibilidade para cadeia, canal, anuencia, ativacao opcional por tenant, pacote, backup, maintenance mode, janela de rollout, customizacao e orquestracao esperada.

### Operacao administrativa

A tela `admin.atualizacoes` agora tambem cobre:

- resumo explicito da cadeia da release;
- changelog estruturado;
- simulacao por release/assinante/lote;
- pre-check por release;
- rollback formal;
- dashboards de atraso e alertas operacionais.

A tela `admin.atualizacoes-assinantes` agora tambem cobre:

- timeline resumida por assinante;
- detalhe tecnico da execucao;
- resumo do pipeline de overlays;
- exportacao JSON/CSV do recorte filtrado.

### Runner on-premise

Depois da instalacao inicial, o pacote on-premise tambem entrega `update.sh`, que delega para `scripts/update-onprem.sh`.

Exemplo:

```bash
./update.sh --manifest-source="https://servidor.exemplo/manifest.json" --fail-on-pending-critical
```

O runner:

1. valida Ubuntu 24.04;
2. valida Docker e Docker Compose;
3. verifica a versao instalada e consulta o manifesto;
4. baixa o pacote critico quando o modo for `download_only`;
5. executa backup opcional quando configurado;
6. aplica releases autoaplicaveis ou com anuencia ja registrada;
7. opcionalmente atualiza a stack local com `docker compose pull` e `docker compose up -d --force-recreate`;
8. revalida integridade estrutural ao final.

Quando `APP_UPDATE_ONPREM_CRITICAL_POLICY=block`, a Home passa a tratar release critica pendente como bloqueante e o runner assume `--fail-on-pending-critical` por padrao.

Quando `APP_UPDATE_ONPREM_CRITICAL_MODE=download_only`, o runner nao aplica a release: ele baixa o primeiro pacote critico pendente e encerra a rotina.

Opcoes operacionais adicionais do runner:

- `--backup-command="<comando>"`
- `--compose-workdir=<diretorio>`
- `--compose-file=<arquivo-compose>`
- `--compose-project-name=<projeto>`
- `--compose-services=app,worker`
- `--skip-container-rollout`

Essas opcoes existem para o cenario em que a aplicacao decide a atualizacao, mas o host ainda precisa recriar containers ou atualizar servicos depois dos steps de migration, seed e publicacao.

### Ciclo central do SaaS

O sistema central agora tambem tem um comando proprio para rodar sem UI:

```powershell
php backend/bin/console app:update:saas-cycle
```

Esse ciclo:

1. consulta o manifesto remoto;
2. registra e valida a release localmente;
3. detecta a proxima release autoaplicavel;
4. cria o job administrativo;
5. deixa o worker aplicar os steps em ordem;
6. registra a execucao e, quando necessario, despacha o rollout para o orquestrador externo.

### Runtime local no on-premise

Ao abrir o sistema autenticado, a Home consulta:

- `GET /api/runtime/system-updates/summary`

Quando o deployment for `onprem` e a politica estiver em `block`, o resumo passa a devolver:

- `accessMode=blocked`
- `criticalPolicy=block`
- `criticalActionRequired=true`
- `runtimeRunPendingEndpoint=/api/runtime/system-updates/run-pending`

O endpoint local:

- `POST /api/runtime/system-updates/run-pending`
- `POST /api/runtime/system-updates/download-pending-critical`

executa apenas a esteira local de updates pendentes e reavalia o resumo runtime sem depender da tela central SaaS.

### Plano de rollout SaaS

Para ambientes SaaS controlados por orquestrador externo:

```powershell
php backend/bin/console app:update:rollout-plan 1.0.2
```

### Receptor real do webhook de rollout

O app central continua apenas despachando a solicitacao. A execucao fisica fica neste receptor externo.

Suba o servico no host do SaaS:

```bash
cp scripts/orchestrator/system-update-orchestrator.config.sample.json scripts/orchestrator/system-update-orchestrator.config.json
APP_UPDATE_ORCHESTRATOR_CONFIG=/srv/construtor-pg/scripts/orchestrator/system-update-orchestrator.config.json \
APP_UPDATE_ORCHESTRATOR_TOKEN=trocar-token \
APP_UPDATE_ORCHESTRATOR_SIGNING_KEY=trocar-chave \
HOST=0.0.0.0 PORT=8095 \
./scripts/orchestrator/run-system-update-orchestrator.sh
```

O receptor:

1. valida `Authorization: Bearer ...`;
2. valida `X-Construtor-Signature`;
3. resolve o assinante alvo na configuracao local;
4. executa `docker compose pull` e `docker compose up -d --force-recreate`;
5. roda comandos opcionais de backup e manutencao;
6. grava log em `var/orchestrator-update/<data>/...json`.

Configuracoes principais:

- `APP_UPDATE_ORCHESTRATOR_CONFIG`
- `APP_UPDATE_ORCHESTRATOR_TOKEN`
- `APP_UPDATE_ORCHESTRATOR_SIGNING_KEY`
- `HOST`
- `PORT`

Cada assinante pode declarar no JSON:

- `projectName`
- `composeFile`
- `workdir`
- `rolloutStateFile`
- `services`
- `backupCommand`
- `maintenanceEnterCommand`
- `maintenanceExitCommand`
- `preCommands`
- `postCommands`

O plano informa backup, janela de manutencao, acao sugerida do orquestrador e impacto em overlays/variantes congeladas.

## Pos-validacao recomendada

Depois do provisionamento:

```powershell
php backend/bin/console app:integrity:monitor --fail-on-invalid
php backend/bin/console app:governance:monitor --fail-on-alert
```

## Seed de metadados

Para publicar a tela administrativa em outros ambientes:

```powershell
php backend/bin/console app:seed-runtime-metadata --no-interaction
```
