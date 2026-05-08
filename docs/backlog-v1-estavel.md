# Backlog v1 estavel

Registro para fechar a primeira versao estavel do motor CRUD dinamico.

## Seguranca da definicao de telas

- Implementado no frontend: modo `security.mode="production"` para bloquear JSON direto e `definitionUrl` livre.
- Implementado no frontend: motor pode iniciar por `screenId`, por exemplo `cadastros.clientes`.
- Implementado no frontend: entradas separadas em `production/`, sem mock e sem JSON local de `examples/`.
- Implementado no backend: autentica usuario, valida tenant/sessao e filtra o JSON autorizado por grupos/permissoes da sessao.
- A demo pode continuar usando `definitionUrl` e JSON local.
- Implementado no frontend: a configuracao de producao pode desabilitar `definition` direto e `definitionUrl` livre.

## Endpoints e acoes

- Implementado no frontend: URL livre no JSON pode ser bloqueada em producao.
- Implementado no frontend: `endpointId` e `actionId` podem ser resolvidos por gateway runtime.
- Implementado no backend: valida se a acao pertence a tela solicitada via `runtime_endpoint`.
- Implementado no backend: valida permissao por endpoint em `runtime_endpoint.permission`, incluindo consultar, criar, editar, excluir, acoes em massa, preferencias, ajuda lida e administracao.
- Implementado no frontend: o endpoint de novidades lidas aceita identificador controlado, por exemplo `help.markAsRead`.

## Validacao no backend

- O backend nao deve confiar no JSON recebido do frontend.
- Validar campos retornados no grid.
- Validar campos editaveis no formulario.
- Validar filtros permitidos.
- Validar ordenacao e agrupamento permitidos.
- Validar acoes em massa e lista de ids.
- Garantir isolamento por tenant.
- Retornar `403` quando o usuario nao tiver permissao.

## Validacao no frontend

- Manter o JSON como metadado declarativo.
- Nao executar JavaScript vindo do JSON.
- Bloquear `eval`, `Function`, script inline, template livre e HTML bruto inseguro.
- Validar campos, permissoes, formulas, URLs e referencias antes de renderizar.
- Nao colocar tokens, senhas, segredos ou regras sensiveis no JSON.

## Arquitetura recomendada

- Frontend solicita uma tela por `screenId`.
- Backend devolve a definicao ja filtrada e autorizada.
- Frontend renderiza dinamicamente.
- Todas as chamadas de dados passam por uma camada runtime controlada pelo backend.
- O JSON define experiencia visual, mas nao autoriza acesso a dados.
