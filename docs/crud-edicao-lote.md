# CRUD com edicao em lote

O modo `form` continua sendo o padrao. Para habilitar uma grade com varias linhas em rascunho, configure no Program Builder:

- Programa: **Edicao do CRUD → Grade editavel em lote**, persistido como `programDraft.gridEditMode="batch"`.
- Entidade API: **Operacao de lote transacional**, persistida como `entityDraft.apiBatchOperationCode`.
- Cadastro da fonte: uma operacao `type="batch"`, metodo `POST`, cujo servidor executa uma transacao unica.
- Permissoes independentes do programa: incluir, alterar e excluir. Selecionar uma operacao batch nao habilita automaticamente a exclusao.

O builder gera `crud.grid.editing.mode="batch"` e `dataSource.api.batch={"endpointId":"batch","method":"POST"}`. A normalizacao existente disponibiliza `grid` e `api` ao CrudEngine. URLs e segredos externos continuam restritos ao backend. Os campos editaveis devem informar `editable=true`; a chave primaria e campos readonly usam `editable=false`. As regras `validation.required`, `validation.maxLength`, `validation.min` e `validation.max` tambem sao metadados.

A grade usa o editor incell real do Kendo. **Adicionar linha** cria somente um rascunho local. **Excluir linha** marca uma linha existente e permite desfazer a marca; uma inclusao ainda nao salva e removida localmente. **Salvar lote** valida o editor e todas as mudancas, verifica `create/edit/delete` e envia no maximo100 alteracoes em uma chamada. A chave primaria e readonly e inteiros usam formato `n0`.

O corpo possui `changes` com `operation` (`create`, `update` ou `delete`) e `clientId` unico e estavel na tentativa. Inclusoes enviam apenas `data`; alteracoes enviam `id`, `data` e `original`; exclusoes enviam `id` e `original`. `data` e `original` contem somente campos editaveis da entidade; o ID nunca entra nesses objetos. `original` e o snapshot da leitura, inclusive valores nulos, para verificacao de concorrencia no servidor.

O frontend nao grava linha por linha. Erros do servidor (por exemplo, `Linha 2: alteracao concorrente`) aparecem pela mensagem segura Kendo e preservam o lote, os valores originais e as marcas de exclusao. Depois de sucesso o rascunho e descartado e a consulta e recarregada. Se essa releitura falhar, uma mensagem informa que a gravacao ocorreu e orienta usar Atualizar; o lote confirmado nao pode ser reenviado.

**Cancelar lote** pede confirmacao em janela Kendo e restaura toda a leitura local. Com rascunho pendente, filtros, paginacao, ordenacao, agrupamento, atualizacao, troca de leiaute e abertura de registro sao bloqueados ate salvar/cancelar. A troca de programa usa o contrato `CrudEngine.hasUnsavedChanges/confirmDiscardChanges` ja consumido pelo shell. Links que substituem a pagina usam confirmacao Kendo. Fechamento/recarregamento da aba usa a protecao `beforeunload` do proprio navegador, cujo dialogo nao pode ser substituido por janela da pagina. Durante a gravacao, botoes e nova edicao ficam bloqueados, e o shell recusa sair ate a resposta.

No mobile, o modo batch mantem a grade editavel em vez do template de cartoes. A mudanca de breakpoint nao reconstroi a tela enquanto houver rascunho.

Validacao automatizada: `node tests/frontend/crud-grid-batch-smoke.mjs`, com Kendo local e Edge instalado. Cobre snapshot/PK, lote unico, erro/cancelamento, validacao de celula/campos/limite, permissoes, toolbar, guardas da engine, duplo envio e falha de releitura apos commit. O teste usa transporte em memoria exclusivo do teste e nao altera dados de negocio. `node tests/frontend/program-builder-batch-smoke.mjs` confere os controles Kendo reais, a carga/exportacao de modo e binding e o reset para `form` em novo rascunho.
