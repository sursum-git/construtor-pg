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
