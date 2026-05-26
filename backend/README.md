# Backend Construtor PG

Backend Symfony + API Platform + PostgreSQL para o runtime do frontend por metadados.

## Objetivo

- Entregar JSON de tela por `screenId`.
- Executar APIs fechadas por `endpointId` ou `actionId`.
- Persistir metadados do construtor, programas, entidades, campos, layouts e mapeamentos.
- Preparar a geração híbrida de classes Doctrine, ApiResource, migrations e handlers.

## Configuração

Configure em `.env.local`:

```dotenv
DATABASE_URL="postgresql://usuario:senha@127.0.0.1:5432/construtor_pg?serverVersion=16&charset=utf8"
PARAM_DATABASE_URL="postgresql://usuario:senha@127.0.0.1:5432/param?serverVersion=16&charset=utf8"
APP_TEST_DATABASE_URL="postgresql://usuario:senha@127.0.0.1:5432/construtor_pg_test?serverVersion=16&charset=utf8"
AUTH_REQUIRED=0
```

Use `AUTH_REQUIRED=1` quando quiser obrigar login em todos os endpoints runtime.

## Comandos

```powershell
php bin/console doctrine:migrations:migrate
php bin/console app:seed-runtime-metadata
php bin/console app:param:copy
php bin/console messenger:consume async -vv
```

`app:param:copy` usa `pg_dump` e `pg_restore`; os binários do PostgreSQL precisam estar no `PATH`.

## Central SaaS

O sistema central e identificado por `APP_SYSTEM_ROLE=saas_central` ou `APP_CENTRAL_CONTROL_ENABLED=1`.

Telas administrativas principais:

- `admin.assinante-ambientes`: provisionamento de assinantes;
- `admin.instalacao-licencas`: licencas de instalacao;
- `admin.instalacao-tokens`: tokens internos SaaS;
- `admin.central-operacoes`: painel operacional de licencas, tokens, artefatos, chaves, auditoria, saude dos assinantes e notificacoes derivadas;
- `admin.atualizacoes`: releases e aplicacao controlada;
- `admin.atualizacoes-assinantes`: historico por assinante.

Endpoints da operacao central:

- `GET /api/admin/central-operations/dashboard`;
- `POST /api/admin/central-operations/license-action`;
- `POST /api/admin/central-operations/token-action`.

Na ativacao por e-mail, a central bloqueia excesso de tentativas por requisicao com:

- `APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS`;
- `APP_INSTALLER_ACTIVATION_BLOCK_MINUTES`.

## Jobs

A decisao de usar fila fica nos metadados do backend:

- `builder_entity.metadata.jobs` para comportamento padrao da entidade;
- `runtime_endpoint.config.jobs` para sobrescrever por endpoint/programa;
- `mode="async"` agenda no worker;
- sem job configurado, a acao continua processando na propria chamada.

Para acoes manuais, use um endpoint com `handler="runtime.job.enqueue"` e `config.jobs`.

Os tipos sao fechados e precisam ter handler PHP registrado, por exemplo `cliente.email_confirmation` e `cliente.whatsapp_welcome`.

## Parametros

O modulo simples de parametros usa quatro cadastros via API Platform:

- `system_option_list`: lista de opcoes;
- `system_option`: opcoes da lista, com `code` e `description`;
- `system_parameter`: parametro, tipo de dado, valor padrao e lista vinculada quando usar `option` ou `multi_option`;
- `system_parameter_value`: valor vigente do parametro por periodo e `establishmentCode`.

Tipos suportados: `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `json`, `option` e `multi_option`.
O seed cria `subscriber.enabled=false` como parametro booleano global.

## Preferencias de usuario

As preferencias do grid ficam no PostgreSQL, separadas por tenant, usuario e `screenId`:

- `user_grid_layout_preference`: colunas visiveis, ordem, largura, congelamento e estado do grid;
- `user_sort_preference`: ordenacoes salvas;
- `user_filter_preference`: filtros salvos;
- `user_group_preference`: agrupamentos salvos;
- `user_mobile_grid_template_preference`: campos e posicoes do template mobile.

O runtime retorna essas preferencias em `crud.userLayout`.
O frontend chama os endpoints fechados `saveLayout`, `restoreLayout`, `saveSort`, `deleteSort`, `saveGroup`, `deleteGroup`, `saveFilter`, `deleteFilter`, `saveMobileTemplate` e `deleteMobileTemplate`.

Escopos:

- `scope="tenant"` salva somente no assinante atual;
- `scope="global"` ou `applyToAllTenants=true` salva como preferencia global do usuario, usando `tenant_id="__global__"`.

Na leitura, a precedencia e: usuario no assinante atual, usuario global, padrao do programa e padrao do sistema.

## Autenticacao

Endpoints:

- `GET /api/auth/providers`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `POST /api/auth/remember`
- `GET /api/auth/session`
- `GET /api/auth/oauth/{provider}/start`
- `GET /api/auth/oauth/{provider}/callback`

Provedores configurados em `auth_provider_config`:

- `local`: senha gravada em `auth_user`;
- `ldap`: autentica em servidor LDAP quando habilitado e configurado;
- `sso`: aceita identidade enviada por headers confiaveis de proxy/SSO;
- `oauth`/`oidc`: fluxo de autorizacao e callback com URLs configuradas.

No login por usuario/senha, o usuario nao escolhe o tipo de acesso.
O backend localiza `auth_user` por tenant/usuario e usa `auth_user.authSource` para decidir se autentica por `local`, `ldap` ou `sso`.
Se houver provedores `oauth`/`oidc` habilitados, eles aparecem como botoes externos na tela de login.

O seed cria o provedor `local` habilitado e um usuario tecnico:

- usuario: `admin`
- senha inicial: `admin123`
- tenant: `default`

Troque essa senha antes de usar fora do ambiente local.
O login retorna um token Bearer e um `sessionId`; o frontend grava esses dados em `localStorage` e envia nas chamadas runtime.
O token fica salvo no banco apenas como hash em `runtime_user_session.sessionProperties.authTokenHash`.
Quando `remember=true`, o backend tambem cria um token persistente em `auth_remember_token`, salvo apenas como hash e com validade de 30 dias.
Ao abrir `production/login.html`, o frontend usa `/api/auth/remember` para criar uma nova sessao runtime sem pedir senha.
O logout e a derrubada administrativa de usuario revogam esse token persistente.

## Minimo para CRUD generico

Ter somente a classe Doctrine ou somente a tabela no PostgreSQL nao basta para o runtime do frontend.
Para o backend montar JSON e executar `handler="entity.crud"`, precisam existir estes cadastros:

- tabela fisica no PostgreSQL;
- `builder_entity` com `code`, `entityType="persistence"` e `tableName`;
- `builder_field` com a chave primaria e os campos usados pela tela;
- `builder_field.dataType` para cada campo;
- `builder_field.options.columnName` quando campo e coluna tiverem nomes diferentes;
- `screen_definition` com o JSON da tela para o `screenId`;
- `runtime_endpoint` com `screenId`, `endpointId`, `enabled=true`, `handler="entity.crud"` e `config.entityCode/config.operation`.

Situacao de entidade e opcional. Quando usada:

- `builder_entity.situationEnabled=true`;
- `builder_entity.situationFieldCode` aponta para um campo cadastrado em `builder_field`;
- `builder_entity_situation` cadastra os valores permitidos, como `EM_DIGITACAO`, `COMPLETO`, `PENDENTE_APROVACAO` e `APROVADO`;
- `builder_entity_situation_transition` cadastra de/para, `actionId`, regras fechadas e efeitos seguros.
- `builder_entity.metadata.rules` cadastra regras configuradas da entidade, com ordem, fase, continuidade apos erro, tipo `requiredWhen` ou `class_method`, parametros JSON e log automatico em `runtime_transaction_log`.
- `builder_entity.metadata.uniqueKeys` cadastra chaves unicas compostas; `builder_field.options.unique` continua cobrindo chave unica de um unico campo.
- `builder_field.options.readonly/writable` permite marcar campo nao editavel no CRUD gerado.
- `builder_field.options.foreignKey` agora tambem pode carregar `dependencyType`, `onDelete` e `onUpdate`.

O runtime generico valida se a situacao existe, aplica a situacao inicial no `create` quando o campo vier vazio, bloqueia transicoes nao cadastradas quando houver fluxo definido e grava log `*.situation.transition`.

Quando faltar essa configuracao, o runtime responde com erro fechado:

```json
{
  "error": {
    "code": "ENTITY_METADATA_NOT_CONFIGURED",
    "message": "Entidade nao configurada no construtor.",
    "details": {
      "minimumRequired": {}
    }
  }
}
```

Outras falhas de configuracao tambem sao fechadas: `ENTITY_TABLE_NOT_CONFIGURED`, `ENTITY_TABLE_NOT_FOUND`, `ENTITY_FIELDS_NOT_CONFIGURED`, `ENTITY_FIELDS_NOT_USABLE`, `ENTITY_PRIMARY_KEY_NOT_FOUND`, `SCREEN_NOT_FOUND` e `RUNTIME_ENDPOINT_NOT_FOUND`.

## Sessao runtime

`runtime_user_session` e a fonte principal de identidade da sessao no runtime.
Ela guarda usuario, entrada, `sessionId` logico, `phpSessionId`, dados de dispositivo, propriedades da sessao e snapshot de permissoes em JSON.

`runtime_transaction` e `runtime_transaction_log` nao guardam mais usuario diretamente.
A auditoria resolve o usuario pela sessao vinculada na transacao.

Ao derrubar usuario, o backend marca a sessao como `revoked`, grava `sessionProperties.phpSessionKillRequested=true`, libera locks e envia mensagem `force_logout`.
Na proxima chamada da propria sessao, o runtime retorna `SESSION_REVOKED` e invalida a sessao PHP atual quando o `phpSessionId` coincidir.

## Endpoints runtime

- `GET /api/runtime/screens/{screenId}`
- `POST /api/runtime/screens/{screenId}/endpoints/{endpointId}`
- `GET /api/runtime/screens/{screenId}/documents/{documentId}`

O API Platform expõe os cadastros administrativos em `/api`.
