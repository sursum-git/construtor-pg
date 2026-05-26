# Manual de instalacao

Este manual consolida o fluxo atual de instalacao do Construtor PG.

## Visao geral

A instalacao nova nao deve ser liberada apenas por variavel manual no `.env`.
O fluxo atual exige um executavel compilado, ativacao central, precheck do ambiente e uma sessao local curta. So depois disso a pagina `production/install.html` consegue executar `/api/install/run`.

Existem dois perfis de instalacao:

- `system_builder`: Construtor de Sistemas.
- `subscriber`: Assinante.

O perfil fica compilado no binario. O instalador de assinante nao oferece opcao para instalar o construtor.

## Artefatos principais

- instaladores Go: `installer/`
- pagina real de instalacao: `production/install.html`
- pagina demo: `examples/pages/system-install.html`
- API local: `GET /api/install/status`, `POST /api/install/precheck`, `POST /api/install/run`
- central de ativacao inicial: `POST /api/installer/activation/request`, `POST /api/installer/activation/confirm`, `POST /api/installer/activation/service`, `GET /health`
- Docker simples Linux: `Dockerfile`, `compose.yaml`, `docker/nginx/default.conf`, `docker/supervisor/construtor-pg.conf`, `docker/entrypoint.sh`
- Docker producao separado: `Dockerfile.runtime`, `compose.production.yaml`, `docker/nginx/production.conf`, `docker/php/entrypoint.sh`

## Binarios

Gerar no Windows:

```powershell
cd C:\construtor-pg\installer
.\build.ps1
```

Gerar no Linux:

```bash
cd /srv/construtor-pg/installer
./build.sh
```

Saida esperada em `installer/dist/`:

- `construtor-builder-installer-linux`
- `construtor-subscriber-installer-linux`
- `construtor-builder-installer.exe`
- `construtor-subscriber-installer.exe`

`installer/dist/` e gerado localmente e nao entra no Git.

## Ativacao

O instalador coleta ou recebe:

- codigo do assinante;
- perfil compilado;
- modo desejado: `docker`, `native` ou `saas`;
- fingerprint do host;
- URL da central de ativacao.

Fluxo normal:

1. o executavel chama `POST /api/installer/activation/request`;
2. a central valida o assinante, perfil e modo;
3. a central envia um codigo para o e-mail cadastrado;
4. o usuario informa o codigo no executavel;
5. o executavel chama `POST /api/installer/activation/confirm`;
6. a central devolve `activationProof`, sessao curta e manifesto autorizado;
7. o executavel grava a sessao local de instalacao;
8. a pagina `production/install.html` passa a mostrar a ativacao e libera as etapas finais.

No SaaS, o orquestrador usa `POST /api/installer/activation/service` com token interno e nao pede confirmacao manual por e-mail.

## Cadastro central de licencas

A central agora pode autorizar instalacoes por tabela, sem depender apenas de JSON no `.env`.

Tabela:

- `installer_activation_license`

Tela administrativa:

- `production/app.html?screenId=admin.instalacao-licencas`

Campos principais:

- `subscriber_code`: codigo informado ao instalador;
- `subscriber_name`: nome operacional do assinante;
- `activation_email`: e-mail que recebe o codigo de confirmacao;
- `status`: `active`, `suspended` ou `revoked`;
- `allowed_profiles`: lista JSON com `system_builder` e/ou `subscriber`;
- `allowed_modes`: lista JSON com `docker`, `native` e/ou `saas-docker`;
- `max_activations`: limite de sessoes emitidas; `0` significa sem limite;
- `activation_count`: quantidade ja emitida;
- `expires_at`: validade da licenca;
- `metadata.activationHistory`: historico resumido das ultimas ativacoes emitidas.

Quando a tabela existir, a central procura primeiro a licenca nesse cadastro. Se a tabela ainda nao existir ou nao houver cadastro correspondente, o fallback antigo por `APP_INSTALLER_ACTIVATION_SUBSCRIBERS` continua funcionando para ambientes em transicao.

A licenca tambem aceita controles opcionais em `metadata`: `maxHosts`, `allowedFingerprints`, `revokedFingerprints` e `fingerprints` atualizado automaticamente pela central.

A central bloqueia:

- licenca inativa, suspensa ou revogada;
- e-mail vazio;
- licenca expirada;
- perfil nao permitido;
- modo nao permitido;
- limite de ativacoes atingido.

Configuracao minima da central:

```dotenv
APP_INSTALLER_ACTIVATION_SUBSCRIBERS='{"cliente-x":{"email":"responsavel@cliente.com"}}'
APP_INSTALLER_ACTIVATION_SIGNING_KEY=trocar-chave-central
APP_INSTALLER_ACTIVATION_FROM=no-reply@seudominio.com
APP_INSTALLER_ACTIVATION_SERVICE_TOKEN=trocar-token-interno
MAILER_DSN=null://null
```

Com cadastro em banco, `APP_INSTALLER_ACTIVATION_SUBSCRIBERS` passa a ser apenas fallback operacional.

## Tokens internos SaaS

O fluxo Docker SaaS pode usar tokens cadastrados na tabela `installer_activation_service_token`, publicada em `production/app.html?screenId=admin.instalacao-tokens`. O campo `token_hash` aceita `password_hash` ou SHA-256 hexadecimal do token. A central valida status, validade, perfis, modos, `metadata.allowedSubscribers` e `metadata.revokedFingerprints`.

Configuracao minima do ambiente local instalado:

```dotenv
APP_INSTALLATION_SESSION_REQUIRED=1
APP_INSTALLATION_SESSION_SIGNING_KEY=mesma-chave-da-sessao
APP_INSTALLATION_SESSION_FILE=/srv/app-state/install/activation-session.json
```

## Assinatura de artefatos

Quando a central tiver `APP_INSTALLER_ARTIFACT_SIGNING_KEY`, ela devolve assinaturas HMAC para manifesto, Compose e pacote. O executavel valida antes de baixar usando `CONSTRUTOR_INSTALLER_ARTIFACT_SIGNING_KEY` no host.

Apos sucesso da instalacao, o backend grava em `.env.local`:

- `APP_INSTALLATION_TYPE`
- `APP_ACTIVATION_SUBSCRIBER_CODE`
- `APP_ACTIVATION_PROOF_HASH`
- `APP_SYSTEM_INSTALLED=1`
- `APP_INSTALLER_PASSWORD_HASH`

## Precheck obrigatorio

`ERRO` bloqueia a instalacao. `AVISO` permite continuar e fica registrado.

Linux Docker valida:

- Ubuntu 24.04;
- arquitetura suportada;
- Docker e Docker Compose;
- permissao para usar Docker;
- portas;
- disco;
- internet, DNS e registry;
- relogio do host.

Linux nativo valida:

- Ubuntu 24.04;
- PHP 8.4;
- extensoes PHP necessarias;
- Composer;
- `psql`, `pg_dump` e `pg_restore`;
- PostgreSQL acessivel;
- `systemd`;
- permissoes, portas, disco, internet e relogio.

Windows e apenas teste sem Docker. Valida:

- Windows compativel;
- PHP 8.4;
- extensoes PHP;
- Composer;
- PostgreSQL client;
- porta, permissoes, internet e navegador.

Comando de precheck:

```bash
./construtor-subscriber-installer-linux --precheck --mode=docker --subscriber-code=cliente-x --activation-url=https://central.exemplo
```

## Cenario 1: Linux Docker on-premise

Uso recomendado para producao on-premise.

Passos:

1. gerar ou baixar o instalador do perfil correto;
2. executar o precheck;
3. executar a ativacao;
4. o instalador baixa manifesto/Compose/imagens autorizadas;
5. o instalador sobe `app` e `database`;
6. o instalador grava a sessao local;
7. abrir `http://host:porta/production/install.html`;
8. informar senha do instalador, admin inicial e dados operacionais;
9. executar a instalacao pela tela;
10. confirmar que `APP_SYSTEM_INSTALLED=1` foi gravado.

Exemplo:

```bash
./construtor-subscriber-installer-linux --mode=docker --subscriber-code=cliente-x --activation-url=https://central.exemplo
```

Stack atual:

- `app`: Nginx, PHP-FPM e Supervisor no mesmo container;
- `database`: PostgreSQL 16;
- volumes persistentes para banco, `.env.local`, estado de ativacao e arquivos compartilhados.

O container nao instala o sistema automaticamente ao iniciar. A instalacao continua sendo feita pela pagina local liberada pelo executavel.

Stack separada de producao:

- `nginx`: publica HTML/CSS/JS e encaminha `/api/*`;
- `php`: PHP-FPM da aplicacao;
- `worker`: consumidor Messenger, ativado por `APP_WORKER_ENABLED=1`;
- `database`: PostgreSQL 16.

Uso:

```bash
docker compose -f compose.production.yaml up -d --build
```

Por padrao, o worker fica inativo ate o ambiente estar instalado. Para ativar o consumo da fila depois das migrations:

```dotenv
APP_WORKER_ENABLED=1
```

Se a porta `8080` ja estiver ocupada, suba com outra porta:

```powershell
$env:APP_HTTP_PORT="18080"
docker compose up -d
```

Validacao rapida:

```powershell
docker compose build
$env:APP_HTTP_PORT="18080"
docker compose up -d
curl.exe -i http://127.0.0.1:18080/health
curl.exe -i http://127.0.0.1:18080/api/install/status
docker compose down
```

## Cenario 2: Linux sem Docker on-premise

Uso para ambientes que nao podem rodar container.

Passos:

1. executar o instalador `*-linux` com `--mode=native`;
2. validar PHP, Composer, PostgreSQL client e servicos locais;
3. baixar o pacote assinado;
4. preparar `.env.local`;
5. preparar pasta de estado;
6. preparar servico local;
7. abrir `production/install.html`;
8. concluir senha do instalador, admin inicial, assinante, migrations, seed e catalogo.

Exemplo:

```bash
./construtor-subscriber-installer-linux --mode=native --subscriber-code=cliente-x --activation-url=https://central.exemplo
```

## Cenario 3: Windows teste

Windows nao e alvo de producao. Serve para testar o fluxo sem Docker.

Exemplo:

```powershell
.\construtor-subscriber-installer.exe --mode=native --subscriber-code=cliente-x --activation-url=https://central.exemplo
```

O instalador valida dependencias locais, baixa pacote de teste, sobe servidor local simples e usa ambiente dev/teste.

## Cenario 4: Docker SaaS

No SaaS, a validacao manual por e-mail nao e usada. O orquestrador central usa token interno:

1. resolve o assinante e perfil permitido;
2. chama a central com `APP_INSTALLER_ACTIVATION_SERVICE_TOKEN`;
3. recebe manifesto autorizado;
4. baixa imagem/manifesto;
5. sobe a stack do assinante;
6. cria sessao curta para liberar a pagina ou endpoint de instalacao provisionada.

## Tela web de instalacao

A tela `production/install.html` nao pede mais token de liberacao.
Ela mostra:

- perfil autorizado;
- codigo do assinante;
- modo de instalacao;
- estado da ativacao;
- validade da sessao local.

A tela continua responsavel por:

- senha do instalador;
- admin inicial;
- dados do assinante;
- migrations;
- seed;
- publicacao do catalogo padrao;
- integridade estrutural.

`/api/install/run` sempre valida a sessao local criada pelo executavel. Sem sessao valida, a API responde com bloqueio e nao executa instalacao.

## Reinstalacao

Reinstalar exige:

1. nova ativacao pelo executavel;
2. senha do instalador ja cadastrada;
3. confirmacao explicita na tela;
4. politica de backup: backup validado, pular com justificativa ou ambiente descartavel/teste;
5. nova execucao controlada de `/api/install/run`.

Em banco marcado como `prod`, a opcao de ambiente descartavel/teste e bloqueada.

O objetivo e evitar que alguem reinstale o ambiente apenas alterando arquivos locais.

## Validacoes usadas neste ciclo

Na maquina atual foram validados:

```powershell
php backend/bin/console lint:container
php backend/vendor/bin/phpunit tests\Install\InstallationActivationServiceTest.php
node --check src\install\system-install.js
node --check src\install\system-install-demo.js
cd installer
go test .\...
.\build.ps1
docker compose build
$env:APP_HTTP_PORT="18080"; docker compose up -d
curl.exe -i http://127.0.0.1:18080/health
curl.exe -i http://127.0.0.1:18080/api/install/status
docker compose down
```

Resultado esperado:

- `lint:container` sem erro;
- PHPUnit do instalador com sucesso;
- JS sem erro de sintaxe;
- Go build gerando quatro binarios;
- Docker subindo `app` e `database`;
- `/health` retornando `200`;
- `/api/install/status` retornando `200`, mesmo que a ativacao ainda esteja bloqueada.
