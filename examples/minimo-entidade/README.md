# Minimo para publicar uma entidade no runtime

Exemplo preenchido para a entidade `produto`.

Arquivos:

- `01-create-table-produto.sql`: tabela fisica PostgreSQL.
- `02-builder-entity.json`: cadastro da entidade no construtor.
- `03-builder-fields.json`: campos da entidade.
- `04-screen-definition.json`: JSON da tela entregue ao frontend.
- `05-runtime-endpoints.json`: endpoints fechados usados pelo runtime.
- `06-test-runtime.http`: chamadas minimas para testar.
- `07-builder-situations.json`: situacoes e transicoes opcionais da entidade.

Fluxo esperado:

1. Criar a tabela fisica.
2. Cadastrar `builder_entity`.
3. Cadastrar os `builder_field`.
4. Cadastrar `screen_definition`.
5. Cadastrar `runtime_endpoint`.
6. Se a entidade tiver situacao, cadastrar `builder_entity_situation` e `builder_entity_situation_transition`.
7. Testar `production/app.html?screenId=cadastros.produtos`.

So criar a classe Doctrine ou a tabela nao basta para o frontend dinamico.
O runtime precisa desses metadados para saber campos permitidos, tela autorizada e endpoints seguros.
Situacoes sao opcionais, mas quando habilitadas o campo indicado em `builder_entity.situationFieldCode` deve existir em `builder_field`.
