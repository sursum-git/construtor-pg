# Edição de entidade API em lote

O modo fechado `programDraft.gridEditMode` aceita `form` (padrão) ou `batch`. O lote é permitido em programa CRUD com entidade API e operação POST própria. A entidade seleciona `apiBatchOperationCode` no cadastro de fontes; a operação tem `type: "batch"`, `method: "POST"`, `itemsPath: "data"` e, no contrato Sursum, `bodyTemplate: {"operation":"batch"}`. O vínculo salvo contém o snapshot `apiSource.batchEndpoint`/`batchResponse`, com proteção de segredos em authHeaders, nos headers/bodyTemplate de todos os endpoints CRUD/batch e na lista operations, inclusive templates aninhados. A restauração usa o código da operação. Respostas de snapshots históricos também são mascaradas, sem alterar o conteúdo assinado armazenado. Após editar a fonte, salvar novamente o vínculo da entidade.

O programa guarda o modo em `builderConfig` e o exporta junto do rascunho. No modo batch, os campos runtime recebem editable explícito e validation.required/maxLength derivados da entidade. A definição runtime publica `crud.grid.editing.mode: "batch"` e `dataSource.api.batch: {"endpointId":"batch","method":"POST"}`. URLs e credenciais permanecem no backend. A publicação passa pela governança normal e assina o endpoint gerado, usando o handler existente `entity.api.crud`, com `operation: "batch"`. Nesse modo as rotas individuais de escrita são desativadas ao publicar.

## Contrato da chamada

O corpo enviado pelo frontend possui apenas `changes`:

```json
{
  "changes": [
    {"operation":"create","clientId":"new-1","data":{"descricao":"Novo tipo"}},
    {"operation":"update","clientId":"row-2","id":2,"data":{"descricao":"Descrição nova"},"original":{"descricao":"Descrição anterior"}},
    {"operation":"delete","clientId":"row-3","id":3,"original":{"descricao":null}}
  ]
}
```

A lista aceita entre 1 e 100 alterações. `clientId` é único no lote e aceita 1–160 caracteres ASCII em `[A-Za-z0-9_.:-]`. Alteração/exclusão exigem ID inteiro positivo int32, sem repetir o registro no lote, e `original` com todos os campos editáveis. Inclusão não recebe ID nem `original`. Exclusão não recebe `data`. Campos desconhecidos, PK, campos readonly, tipos incompatíveis e textos acima do comprimento dos metadados são recusados antes da chamada externa. Valores originais podem ser null mesmo quando o novo valor é obrigatório. Tanto `data` como `original` usam os `writePath` da entidade.

O endpoint exige sessão autenticada ativa, mesmo quando o ambiente aceita demonstração sem login. Cada linha precisa da permissão `prefix.create`, `prefix.edit` ou `prefix.delete` e do respectivo `allowCreate`, `allowUpdate` ou `allowDelete` da tela publicada. O endpoint também exige leitura da tela. Entidade, operação, programa e ação vêm da configuração assinada do endpoint; parâmetros enviados pelo navegador não selecionam outro destino ou usuário.

O PHP valida o lote inteiro e envia **uma chamada externa** com `operation: "batch"` e todas as alterações. Templates estáticos não podem acrescentar mudanças não validadas. O cliente existente propaga a identidade da sessão no contrato `sursum-programs` e traduz erros de negócio envelopados em HTTP 200. A transação e a concorrência de registros pertencem à API ABL; o PHP não divide o lote em chamadas CRUD nem oferece rollback distribuído.

A API retorna `success: true`, `data: [lista atualizada]` e, opcionalmente, `results: [{clientId,operation,id}]`. O PHP mapeia os itens para os campos runtime e entrega `data`/`total` e resultados opcionais. Não dispara consultas adicionais de lookup durante a gravação. O frontend pode recarregar a consulta depois do sucesso.

A auditoria PHP conserva a transação runtime e registra `entidade.api.batch` ou `entidade.api.batch.failed`, com quantidade de alterações e duração. O estado anterior documentado no lote é o snapshot enviado pelo navegador, usado para conferir concorrência na ABL; não representa uma leitura independente pelo PHP. Em falha externa, a exceção mantém a mensagem de linha da API e a transação PHP registra o erro; a interface conserva o rascunho.

## Verificação

`RuntimeApiEntityBatchTest` cobre chamada única para lote misto, mapping, autenticação, autorização por operação, flags da tela, estrutura estrita, original, IDs/PK/campos, erro transacional e proteção contra alterações adicionadas pelo template. `ProgramBuilderBatchTest` cobre snapshot/segredos, modo fechado/default, persistência/exportação, definição runtime e endpoint assinado. São testes isolados, sem migrations, seed ou acesso a dados operacionais. O teste do erro externo não prova rollback dos registros Progress; isso requer os testes ABL/PASOE da integração.

Validação em 15/10/2026: PHPUnit completo com **238 testes / 1183 asserções**, incluindo 36 testes batch / 267 asserções; `lint:container --env=dev` e sintaxe PHP passaram. Esta validação não cadastra fonte nem publica tela de negócio.
