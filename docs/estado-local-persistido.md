# Estado local persistido

Este arquivo resume o que hoje fica em `localStorage`, com foco operacional.

## Principios

- persistir apenas contexto de uso e conveniencia;
- nao persistir permissao efetiva, decisao de backend ou dado sensivel em claro fora do necessario;
- limpar contexto operacional no logout;
- separar:
  - `lembrar usuario`
  - `manter sessao`

## Chaves principais

### Autenticacao e sessao

- `crudEngine.authToken`
- `crudEngine.runtimeTenantId`
- `crudEngine.runtimeSessionId`
- `crudEngine.currentSubscriber`
- `crudEngine.availableSubscribers`
- `crudEngine.runtimeUserId`
- `crudEngine.runtimeUserName`
- `crudEngine.runtimeUserGroups`
- `crudEngine.runtimeUserPermissions`
- `crudEngine.accessArea`
- `crudEngine.rememberToken`
- `crudEngine.rememberTokenExpiresAt`
- `crudEngine.lastUsername`

Regra:

- logout limpa sessao e contexto operacional;
- `lastUsername` pode ser preservado no logout real;
- o botao `Limpar sessao local` limpa tudo, inclusive `lastUsername`.

### Home

Por `screenId`:

- `homeEngine.<screenId>.notificationFilters`
- `homeEngine.<screenId>.navigationState`

Hoje `navigationState` guarda:

- modulo atual
- texto da busca lateral
- filtro de favoritos
- programa atual
- estado expandido/recolhido da lateral

Favoritos do usuario:

- `homeEngine.favoritePrograms.<appId>.<userId>`

### Admin Integracoes

Por `screenId`:

- `importExportAdmin.<screenId>.state`

Hoje guarda:

- aba ativa
- mapping atual
- filtros do historico persistido
- execucao selecionada
- versao selecionada do mapping
- agendamento selecionado

### Auditoria de governanca

Por contexto de programa:

- `program-governance-audit-filters:<programCode>`

## Limpeza

No logout/limpeza de sessao, alem das chaves de autenticacao, tambem devem ser limpos prefixos operacionais:

- `homeEngine.`
- `importExportAdmin.`
- `program-governance-audit-filters:`

## O que nao deve persistir

- grant ativo como autorizacao confiavel;
- aprovacao final como fonte de verdade;
- resultado de validacao de integridade;
- definicao runtime sensivel;
- senha em claro.
