# Provisionamento SaaS e on-premise

Este fluxo nao altera a estrutura atual do sistema. Ele so automatiza passos que hoje seriam manuais.

## O que entrou

Comandos novos no backend:

- `php backend/bin/console app:install:bootstrap`
- `php backend/bin/console app:subscriber:create`
- `php backend/bin/console app:runtime:publish-defaults`

Scripts operacionais:

- [C:\construtor-pg\scripts\install-onprem.ps1](C:/construtor-pg/scripts/install-onprem.ps1)
- [C:\construtor-pg\scripts\install-onprem.sh](C:/construtor-pg/scripts/install-onprem.sh)
- [C:\construtor-pg\scripts\provision-saas-subscriber.ps1](C:/construtor-pg/scripts/provision-saas-subscriber.ps1)
- [C:\construtor-pg\scripts\provision-saas-subscriber.sh](C:/construtor-pg/scripts/provision-saas-subscriber.sh)

Atualizacao operacional:

- `php backend/bin/console app:update:check`
- `php backend/bin/console app:update:apply <versao>`
- `php backend/bin/console app:update:run-pending`
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

Job assíncrono:

- `subscriber.environment.provision`

## Fluxo SaaS

1. cadastrar ou atualizar o assinante na tela administrativa;
2. clicar em `Criar ambiente`;
3. a tela enfileira o job `subscriber.environment.provision`;
4. a UI acompanha o status do job por SSE quando disponivel e cai para polling quando necessario;
5. ao concluir, o detalhamento do job mostra o resumo final e o rastro do script executado.

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

1. execucao direta do script de instalacao no repositorio;
2. download do pacote zip pela tela administrativa.

O pacote zip e gerado por:

- `GET /api/admin/subscriber-provisioning/onprem-package`

Ele entrega um arquivo com:

- codigo-fonte necessario do projeto;
- `install.sh` na raiz;
- `scripts/install-onprem.sh`;
- `.env.template`;
- `README-INSTALACAO.txt`.

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
- a avaliacao da cadeia obrigatoria considera o assinante alvo no sistema central SaaS; uma release aplicada em outro assinante nao libera a cadeia deste assinante.
- `replaces[]` cobre supersedencia: quando uma release aplicada substitui outra, a dependência anterior passa a ser considerada satisfeita para a cadeia.
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
- quando o impacto do overlay vier limpo (`rebase_ok`), a aplicacao da release ja cria um draft de rebase sobre a base publicada e registra isso no historico por assinante; conflito leve fica em revisao e conflito bloqueante continua fora da automacao.
- manifesto remoto sem confianca nao deve seguir como release aplicavel; a verificacao pode usar `APP_UPDATE_MANIFEST_SIGNING_KEY` com `hmac-sha256`.

### Runner on-premise

Depois da instalacao inicial, o pacote on-premise tambem entrega `update.sh`, que delega para `scripts/update-onprem.sh`.

Exemplo:

```bash
./update.sh --manifest-source="https://servidor.exemplo/manifest.json" --fail-on-pending-critical
```

O runner:

1. valida Ubuntu 24.04;
2. consulta o manifesto;
3. aplica releases autoaplicaveis ou com anuencia ja registrada;
4. revalida integridade estrutural ao final.

Quando `APP_UPDATE_ONPREM_CRITICAL_POLICY=block`, a Home passa a tratar release critica pendente como bloqueante e o runner assume `--fail-on-pending-critical` por padrao.

Quando `APP_UPDATE_ONPREM_CRITICAL_MODE=download_only`, o runner nao aplica a release: ele baixa o primeiro pacote critico pendente e encerra a rotina.

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
