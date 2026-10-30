# Logs no CRUD existente

O runtime enriquece a resposta de uma tela CRUD depois de verificar sua integridade. Em telas de negócio autorizadas, o cabeçalho oferece Logs Exclusão e o formulário de registros existentes oferece Logs na janela Kendo existente. O JSON assinado e os registros publicados não são modificados; programas existentes não precisam de republicação.

No cabeçalho, Logs Exclusão consulta exclusivamente os eventos de exclusão da tela. No formulário de registro existente, Logs consulta as transações daquela tela e chave primária. Na inclusão, o botão fica desabilitado mesmo com chave preenchida; zero e chave ausente não abrem histórico. Em edição em lote, cada linha persistida oferece Logs para os eventos daquele registro; linhas novas ou marcadas para exclusão ficam desabilitadas. Abrir a consulta preserva as alterações pendentes e não salva nem cancela o lote. Documentos customizados das telas de negócio são preservados. O default do gerador {"enabled":false} sem destino recebe a configuração automática.

## Abrir alterações e eventos

admin.transacoes e admin.logs-transacoes não exibem botão Logs no cabeçalho nem no formulário. No detalhe de uma transação, a primeira aba Alterações e eventos monta o CRUD admin.logs-transacoes no próprio DOM, pelo recurso linkedPage existente, filtrado por transaction_id=id. A grade de transações oferece também o link Eventos na linha. A aba e o link só são acrescentados quando o usuário pode ler a tela de eventos; os endpoints mantêm suas verificações de autorização.

Ao entrar nos eventos de uma transação, o painel Alterações da transação aparece acima da grade com seu identificador, usuário registrado e tabela Campo | Anterior | Atual. Não é necessário localizar ou selecionar um evento. O formulário individual continua disponível para detalhes.

O painel usa crud.auditSummary, com configuração fechada de contextField, field, auditDiff, actorNameField, actorIdField e maxRows. Ele só consulta quando há um único filtro eq de transação. Reutiliza o endpoint read autorizado da própria tela, com esse filtro e todos os contextos fixos de navegação, ordenação publicada e limite de até 500 registros. A paginação visível da grade não restringe essa consulta de resumo. Mais eventos que o limite geram aviso de comparação incompleta, sem concluir ausência de alterações.

Estados visíveis distintos: carregando; transação ainda não selecionada; consulta sem alteração de dados; ausência de diferenças registradas; comparação histórica indisponível; resultado limitado; e falha ao carregar os eventos. Falha de consulta nunca é apresentada como transação sem alteração. Respostas atrasadas de outro filtro não substituem o painel atual.

## Usuário e comparação por campo

As definições administrativas incluem actor_user_name (Usuário) e actor_user_id (Código do usuário) no início da grade/formulário. Os valores vêm do backend. A interface mantém autoria ausente vazia e não usa a sessão atual para reconstruir histórico. Esses campos são readonly e não oferecem ordenação, filtro ou agrupamento SQL.

O renderer genérico renderAs="audit-diff" usa o campo diff_data existente. auditDiff referencia beforeField/afterField (JSON) e o par opcional eventTypeField/metadataField (string/JSON). A configuração administrativa fornece os quatro campos. O painel automático e o formulário reutilizam esse mesmo render seguro.

Cada linha vem de uma entrada válida de diff_data no formato {campo:{before:valor,after:valor}}. before_data/after_data apenas distinguem ausência de null. O render não calcula diferenças de payloads. Operações de consulta read/get/list/options mostram “Esta transação não registrou alteração de dados. Operação de consulta.” Eventos .api. de escrita só apresentam tabela com metadata.audit.available=true, version=1 e source=progress-transaction. Históricos/batch sem essa confirmação mostram comparação indisponível. Comparações nativas continuam usando diff_data. Nenhum registro histórico é reescrito.

Valores null, false, zero, texto vazio e coleções ficam distintos. Inclusão/exclusão exibem Ausente no lado sem o campo. before_data/after_data e metadata brutos permanecem na aba Dados. Nomes/valores entram no DOM apenas como texto; chaves sensíveis são protegidas, inclusive em objetos e arrays aninhados. Não há HTML ou código livre no JSON.

## Contrato e exemplos

- [crud-logs-transacoes.json](examples/crud-logs-transacoes.json): recorte da configuração runtime de ciw.empresas; combinar com a definição completa existente.
- [crud-batch-logs.json](examples/crud-batch-logs.json): recorte runtime de Logs por linha persistida em edição em lote.
- [crud-audit-diff.json](examples/crud-audit-diff.json): metadados de comparação e resumo automático.

Os modos fechados reutilizam ../production/app.html e seus contextos fixos:

| Modo | Tela de destino | Contextos |
| --- | --- | --- |
| transactions | admin.transacoes | screen_id e record_id |
| deletions | admin.logs-transacoes | audit_screen_id e audit_operation=delete |
| recordEvents | admin.logs-transacoes | audit_screen_id e audit_record_id |

Os parâmetros usam o prefixo context__, preservado ao Limpar filtros. transactions/recordEvents exigem registro persistido; deletions aceita requiresRecord:false para o cabeçalho. O servidor valida o escopo e a permissão em cada leitura. documentId é o identificador fixo que habilita o botão. O modo transactionEvents continua disponível para contratos declarados anteriores, mas o fluxo administrativo atual usa linkedPage e Eventos. A política de URLs livres em produção permanece igual. Navegação é contexto e não concede autorização.

Transações começam por started_at desc; eventos, por created_at desc, conforme o catálogo administrativo. Filtros filter__ são resolvidos pelo campo configurado antes de alimentar o editor Kendo; filtros sem editor e operadores distintos do mesmo campo são preservados. O link Eventos usa context__transaction_id opcional, persistente durante a navegação. A aba linkedPage encaminha seus filtros EQ como contextValues: somente contextFields declarados pela página filha tornam-se fixos e sobrevivem ao Limpar. Números são encaminhados como texto e campos string preservam IDs opacos, incluindo espaços. O resumo compõe os mesmos contextos da grade para não exibir outros itens do lote ou operações fora do escopo. Sem transação selecionada, orienta “Abra um evento para visualizar os valores anteriores e atuais.”

## Validação

Os testes usam as definições administrativas completas emitidas por AdminCrudDefinitionFactory/ScreenDefinitionService/sanitizer e o validador real de produção:

- backend/tests/Runtime/ScreenDefinitionLogsTest.php: enriquecimento sem mutação, permissões, retirada de Logs recursivo e linkedPage.
- tests/frontend/crud-audit-summary-smoke.mjs: comparação automática no primeiro linkedPage, sem iframe novo, usuário/transação, estados de consulta/legado/limite/erro e ciclo de ações reais443→Cancelar→503→443 no mesmo engine. Confirma também que Visualizar de uma linha filha não dispara ações do pai.
- tests/frontend/crud-audit-diff-smoke.mjs: criação/atualização/exclusão, proveniência, valores especiais, XSS e configuração inválida.
- tests/frontend/crud-transaction-logs-smoke.mjs: janela existente, exclusões do cabeçalho, registro obrigatório, contexto string/opaco e compatibilidade de documentos customizados.
- tests/frontend/crud-batch-logs-smoke.mjs: metadados de negócio emitidos pelo PHP e Logs por linha persistida, com rascunhos/exclusões pendentes preservados.
- tests/frontend/crud-audit-summary-smoke.mjs também verifica Limpar preservando o escopo da grade/resumo e linkedPage, incluindo chave numérica para campo string.

O menu de ações de cada grade usa namespace e verificação de propriedade próprios. Montar ou destruir uma grade linkedPage não substitui/remove os handlers da grade pai; menus fora da grade e ações inline só são processados pela instância proprietária. Após atualizar esse JavaScript em uma sessão aberta, recarregar a página/iframe para substituir os handlers já registrados.
