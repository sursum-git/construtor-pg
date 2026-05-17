# Estado local persistido

Este arquivo resume o que hoje fica em `localStorage`, com foco operacional.

## Principios

- persistir apenas contexto de uso e conveniencia;
- nao persistir permissao efetiva, decisao de backend ou dado sensivel em claro fora do necessario;
- limpar contexto operacional no logout;
- limpar o mesmo contexto tambem em `SESSION_REVOKED`, `SESSION_EXPIRED` e `force_logout`;
- separar:
  - `lembrar usuario`
  - `manter sessao`
- usar envelope versionado para estados JSON persistidos, com fallback para chaves antigas.

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
- ultimo painel contextual reaberto quando aplicavel

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
- no selecionado do editor visual
- no selecionado do preview estrutural

### Auditoria de governanca

Por contexto de programa:

- `program-governance-audit-filters:<programCode>`

## Limpeza

No logout/limpeza de sessao, alem das chaves de autenticacao, tambem devem ser limpos prefixos operacionais:

- `homeEngine.`
- `importExportAdmin.`
- `program-governance-audit-filters:`

Essa mesma limpeza agora e reutilizada em:

- logout explicito;
- token invalido;
- sessao revogada;
- logout forcado por mensagem runtime.

## O que nao deve persistir

- grant ativo como autorizacao confiavel;
- aprovacao final como fonte de verdade;
- resultado de validacao de integridade;
- definicao runtime sensivel;
- senha em claro.

## Referencia de suporte

Quando limpar estado local:

- usuario trocou de sessao e a UI continua mostrando contexto antigo;
- logout forcado encerrou a sessao no backend;
- demo local ficou com selecao antiga que nao faz mais sentido.

Quando tende a ser bug real:

- estado salvo nao reaparece apos reload no mesmo `screenId`;
- logout nao remove chaves operacionais;
- contexto de outro assinante reaparece depois de trocar a sessao.
