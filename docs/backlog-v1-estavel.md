# Backlog v1 estavel

Registro para fechar a primeira versao estavel do motor CRUD dinamico.

## Seguranca da definicao de telas

- Em producao, o frontend nao deve carregar JSON livre informado pelo usuario.
- O motor deve iniciar por `screenId`, por exemplo `cadastros.clientes`.
- O backend deve autenticar o usuario, validar tenant e permissoes, e somente entao devolver o JSON autorizado da tela.
- A demo pode continuar usando `definitionUrl` e JSON local.
- A versao de producao deve poder desabilitar `definition` direto e `definitionUrl` livre.

## Endpoints e acoes

- Evitar URL livre no JSON em producao.
- Preferir `endpointId` ou `actionId`, resolvidos pelo backend.
- O backend deve validar se a acao pertence a tela solicitada.
- O backend deve validar permissao por acao: consultar, criar, editar, excluir, exportar, acoes em massa, salvar layout, salvar filtros, ajuda lida e logs.
- O endpoint de novidades lidas deve evoluir de URL direta para identificador controlado, por exemplo `help.markAsRead`.

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

