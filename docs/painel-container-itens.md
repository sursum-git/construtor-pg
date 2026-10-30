# Manutenção e alocação de itens do container

Publicado em17/10/2026: painel **ciw.container-painel/op1001/1.0.6**, com14abas, e **ciw.container-itens/op1002/1.0.1**. Usa o frontend existente do construtor e APIs Progress. Os JSONs desta entrega conservam o sufixo `candidate`, mas a publicação foi confirmada pelo Builder e pelo runtime; não reimportá-los por causa do nome.

A aba **Itens / Alocação** usa o contexto obrigatório do container. **Incluir** aloca um item de um pedido de importação já existente; **Alterar** mantém fixos o container e o item de compra. O cadastro global de pedidos não faz parte desta etapa. A seleção é global, como no Scriptcase: não há restrição nova por igualdade de empresa ou fornecedor.

O saldo mostrado serve para orientação. A API relê a quantidade comprada e todas as alocações, de todos os containers, sob bloqueio do item de compra. Uma nova alocação ou aumento acima do saldo falha. Um item de compra já alocado ao mesmo container deve ser alterado, evitando duplicidade. A quantidade confirmada no embarque pode superar a alocada, conforme o tratamento financeiro legado.

São editáveis quantidade alocada, confirmada e recebida; recebimento do documento no prazo; observação; tipo de cálculo e percentual de custo; flags e percentuais de ICMS/IPI. A exclusão bloqueia descontos, snapshots de custo e vínculos comerciais do item.

## Rotinas reutilizáveis

| Programa | Responsabilidade |
|---|---|
| `ContainersItensCrud.p` | Fronteira autenticada, buffers e políticas fixos. |
| `ContainersItensCrudService.p` | Locks, saldo, validação, persistência e auditoria confirmada após commit raiz. |
| `ContainersItensRuntime.p` | Composição dos serviços fiscais e financeiros na mesma transação. |
| `ContainersItensFinanceiroService.p` | Rateios e movimentos dos tipos4/5/6/10 acionados pela manutenção/alocação. |
| `ContainersItensOptionsService.p` | Opções de compra e saldo somente leitura, incluindo itens atuais para edição. |

O runtime reutiliza `ContainersCabecalhoRules`/`ContainersImpostosService` para sincronização fiscal e despesas calculadas, e `ContainersPagamentoTotalsService` para conversão dos títulos. Coleta, cálculo de custo e geração/fechamento de versões continuam nos serviços existentes. Não há fórmulas de custo copiadas no formulário ou wrapper.

Na alocação, são estornados os rateios de pedido dos títulos vinculados ao container e recalculado o rateio por item. Na alteração, recalculam-se rateios do container e diferenças de embarque. Na desalocação, recalculam-se remanescentes, estorna-se o pagamento do item e refaz-se o rateio do pedido quando elegível. Uma falha fiscal ou financeira desfaz o conjunto.

Preservou-se um ramo relevante do legado: se a quantidade confirmada voltar a zero ou ficar igual à alocada, o código original não estorna automaticamente movimentos anteriores do tipo10. O serviço ABL mantém essa regra e devolve aviso no envelope interno. O PHP nativo devolve o registro e ainda não apresenta esse aviso na tela; a retenção foi comprovada pelos mesmos IDs dos movimentos. Mudá-la requer uma decisão de negócio separada.

## Cadastro e Logs

Fonte de API: **`motor_progress_container_itens`**. Leitura e detalhe usam `/SursumPrograms/containers-painel`, recurso `itens`. Inclusão/alteração/exclusão usam `/SursumPrograms/containers-itens`, recurso `container-itens`; as opções usam a mesma rota, recurso `itens-disponiveis`, operação `read`. Os valores de edição ficam em `values.<campo>`; o contexto do container é validado pelo PHP e novamente pelo Progress. O navegador não seleciona usuário, política, banco ou programa.

**Logs** do registro mostra o item e os movimentos financeiros relacionados ao seu identificador. **Logs Exclusão**, no cabeçalho da aba Itens, permite localizar o snapshot anterior depois da desalocação. Esse botão filtra exclusões da tela Itens em todos os containers permitidos; o contrato nativo atual não o restringe ao container aberto. Os Logs do painel incluem também mestre e efeitos fiscais/financeiros do container. A autoria vem da sessão autenticada do construtor; os valores anteriores e atuais vêm dos registros persistidos dentro da transação. Chamadas aninhadas retornam apenas mudanças pendentes; não anunciam commit que ainda pertence a outro programa.

Os movimentos físicos legados mantêm `transacao_id=0`, o padrão já existente; esse campo não recebe o identificador PostgreSQL do construtor. A consulta de usuário, transação e anterior/atual usa as classes de transação/log já existentes no construtor e o contrato fechado de auditoria Progress.

Inventário de origem: [itens/alocação](containers-itens-alocacao-inventario.md) e [rateios financeiros](containers-itens-rateios-financeiros-inventario.md). Fontes copiados em `sources/scriptcase/containers/`; servidor original `192.168.0.170:/var/www/clients/client1/web2/web/ciw`.

## Consulta dos logs

1. Na aba **Itens / Alocação**, abra as ações da linha e escolha **Visualizar** ou **Alterar**.
2. Use **Logs** no formulário. Abra o evento `container_itens_progress.api.update` para ver **Alterações**, usuário e colunas **Anterior / Atual**. Os eventos técnicos de requisição não possuem necessariamente comparação.
3. Após desalocar, use **Logs Exclusão** no cabeçalho **Itens do Container**. Abra `container_itens_progress.api.delete`; o registro anterior permanece e o atual aparece como **Ausente**.

## Recálculo fiscal sem custo unitário

A composição usa o recurso somente leitura **cotacoes-fiscais**, modo privado **fiscal_inputs**, no mesmo Collector/Calculator de custos. Ele resolve as mesmas prioridades e projeções, sem exigir um item para custo unitário. Isso permite desalocar o último item e trabalhar com quantidade zero sem copiar fórmulas nem inventar item fictício/cotação. A regra existente que impede rateio de IPI por item quando a base é zero permanece; o teste de zero usa flags fiscais desativadas. Os modos normais de custo conservam suas validações.

## Validação desta entrega

OpenEdge12.2 no compilador192.168.0.42:73fontes,36suítes e2502verificações em cada cultura AMERICAN/EUROPEAN. Publicado e validado no PASOE **sursum-api/med**; PHP477testes/2761asserções e metadados9testes/271asserções passaram. Foram conferidos contexto obrigatório, isolamento, saldo, duplicidade, rollback, composição e auditoria confirmada após commit.

Teste real limitado aos registros novos autorizados: container2147000101, pedido2147000200, item de compra2147000201, alocação3262, marcaSC_TEST_ITENS_20261017/ERPZTSC171026. Inclusão2/0, alteração3/4→3/5, zero0/0 e retorno3/5, rejeição de excesso/duplicidade e exclusão do último item passaram. Logs confirmaram Administrador/admin e dois movimentos tipo10 deUSD2 cada. Interface confirmou vínculo fixo, saldo7,00, Logs por item e snapshot anterior em Logs Exclusão. Esses registros são exclusivamente de teste; os IDs consumidos não devem ser restaurados nas sequências.

A validação real não associou títulos nem fechou custos; os ramos de rateio4/5/6 foram cobertos nas suítes temp-table/composição. Cadastro global de pedidos, associações de pagamentos/notas, distribuição manual e arquivos anexos continuam fora desta entrega.

Limpeza final confirmada por inspeção12.2 somente leitura:14contadores de registros do teste zerados e2393containers, igual ao inventário inicial. Transação7450 conserva o item excluído; transação7501 conserva a exclusão do master. Evidências privadas: `.runtime/container-items-20261017/items-final-cleanup-verification.json`, `items-confirmed-logs.json` e capturas `ui-item-before-after.jpg`/`ui-item-deletion.jpg`. Não repetir as mutações nem remover os guards de tentativas.
