# Associação de pagamentos do container

Estado confirmado em19/10/2026: **ciw.container-pagamentos/op1006/1.0.2** e **ciw.container-painel/op1001/1.0.7**, com14abas, publicados pelos endpoints oficiais do Builder desde17/10. ABL publicado no PASOE **sursum-api/med**. Associação, rejeição de duplicidade, desvinculação, movimentos financeiros e logs persistidos foram validados com um adiantamento no container novo2147000102. A fixture foi totalmente removida; os dois títulos consultados e todos os seus valores permaneceram intactos. O ramo de escrita apósDI não foi executado neste cenário.

## Fluxo nativo

A aba Pagamentos usa o formulário e a grade existentes. **Incluir** seleciona um único título por `num_id_tit_ap`, em opções remotas que unem adiantamentos e títulos apósDI. A modalidade aparece no rótulo; a política é derivada no servidor. Não há edição de valor, empresa, fornecedor ou modalidade, nem operação update. **Excluir** desvincula o título pelo identificador opaco já existente, preservando os títulos financeiros originais.

O contexto `container_id` é obrigatório e validado pelo PHP e pelo Progress. A chave `pagamento_key` conserva o formato `p_` seguido de64dígitos hexadecimais, calculada a partir de container, estabelecimento e título. Não substituir a PK pelo inteiro do título: o estabelecimento integra a identidade. A exclusão resolve a associação física pela chave; não depende de o título ainda satisfazer a elegibilidade de inclusão.

As opções são apenas orientação. O comando repete a mesma consulta central de elegibilidade sob mutex exclusivo do título, valida proprietário global e recusa ambiguidades ou duplicidade. O navegador não fornece usuário, banco, nomes de programa, políticas ou callbacks. A associação compõe rateio financeiro e sincronização fiscal; a desvinculação reutiliza o ramo financeiro correspondente. Uma falha desfaz toda a transação.

## Metadados e APIs

Fonte **motor_progress_container_pagamentos**, entidade **container_pagamentos_progress**. As cinco operações registradas são POST, com `requestContextFields:["container_id"]`:

| Operação | Rota / recurso | Contrato |
|---|---|---|
| pagamentos_read | /SursumPrograms/containers-financeiro / pagamentos | read, data array |
| pagamentos_get | mesma rota / pagamentos | get, data objeto |
| pagamentos_create | /SursumPrograms/containers-pagamentos / container-pagamentos | create, values.num_id_tit_ap |
| pagamentos_delete | mesma rota / container-pagamentos | delete, pagamento_key |
| pagamentos_disponiveis | mesma rota / pagamentos-disponiveis | read, opções contextuais |

O JSON de Pagamentos preserva os22campos e permite somente create/delete. Apenas `num_id_tit_ap` é editável na inclusão, com dropdown remoto e readonlyOnEdit. O Painel1.0.7 muda somente versão e `linkedPage.hideHeader=false` na aba Pagamentos sobre1.0.6; mantém14abas. Os nomes `candidate` dos arquivos representam sua origem preparada; os recibos oficiais confirmam a publicação. Não repetir creates ou importar drafts antigos.

Arquivos rastreáveis em `D:/opencode/construtor-pg/integrations/ciw-container-painel/`: `pagamentos-api-source-candidate.json`, `pagamentos-1.0.2-builder-draft-candidate.json`, `painel-1.0.7-builder-draft-candidate.json`; teste `tests/ContainerPaymentsMetadataTest.php` (8testes/198asserções).

**Logs** usa a identidade da associação e o recurso virtual fechado `container_payment_links`, com snapshots anterior/atual e efeitos filhos vinculados pela chave. **Logs Exclusão**, no cabeçalho nativo de Pagamentos, consulta exclusões dessa tela em todos os containers permitidos. O botão atual não possui restrição adicional ao CID aberto. A autoria vem da sessão autenticada. Serviços aninhados retornam mudanças pendentes; auditoria confirmada só é emitida após commit raiz. A consulta nativa de exclusões foi validada pela API com o vínculo removido. O PHP representa imagem ausente como array vazio `[]`; imagem existente conserva todos os campos.

## Rotinas reutilizáveis e inventário

| Fonte de produção | Responsabilidade |
|---|---|
| ContainersPagamentosCrud.p | Fronteira autenticada, recursos e buffers fixos. |
| ContainersPagamentosCrudService.p | Validação, locks, identidade, persistência e commit/audit. |
| ContainersPagamentosRuntime.p | Consulta central de elegibilidade e composição financeiro/fiscal. |
| ContainersPainelFinanceReadService.p | Consulta financeira e pagamentos disponíveis, sem duplicar regras no wrapper. |
| ContainersItensFinanceiroService.p | Rateios e movimentos financeiros reutilizados por itens e pagamentos. |
| ContainersItensRuntime.p | Adaptador para os participantes compartilhados. |
| ContainersItensCrudService.p | Participante de itens atualizado para validar o envelope compartilhado. |
| ContainerPaymentIdentity.i | Identidade única da associação. |
| ContainerMaintenanceEffects.i | Validação e composição dos efeitos físicos autorizados. |

Os sete programas têm `.p/.r` publicados; com as duas includes, são **16artefatos**, mais uma rota aditiva. Fontes em `integration-src/container-panel/`; canônico em `D:/opencode/motor-progress/v2/sursum-api/{programs,includes}`. Não houve alteração de schema, trigger ou autenticação. A extração do envelope de manutenção evita regras repetidas entre os serviços. Fórmulas de custos/versões continuam nos serviços separados existentes.

Testes novos: `ContainerMaintenanceEffectsTest.p`, `ContainersPagamentosCrudTest.p`, `ContainersPagamentosBoundaryTest.p`, `ContainersPagamentosCompositionTest.p`, `ContainersPagamentosBridgeTest.p`. Regressões alteradas: `ContainersItensCrudTest.p`, `ContainersItensFinanceiroTest.p`, `ContainersPainelFinanceReadTest.p`. Fontes dos oito testes sincronizadas em `D:/opencode/motor-progress/v2/tests/abl/container-panel`.

O Bridge roda em AVM separado **sem conexão de banco**, com verificação anterior à execução; usa Runtime/Finance/Totals reais e tabelas temporárias para provar que não chega a sequência real. As demais suítes executáveis usam TT ou bootstrap somente leitura; nenhum teste deste lote autorizou escrita de negócio.

Origem legada consultada estaticamente: `cons_tit_ap_associar_container_grid.class.php:561–563,20922–21023`, `cons_tit_ap_associar_container_apos_di_grid.class.php:21058–21141` e `cons_tit_ap_associados_container_grid.class.php:10573–10597,21517–21521`, nas cópias de `sources/scriptcase/containers/`. Nenhum PHP legado foi executado.

## Evidências e pendências

Compilação real OpenEdge12.2/.42: **81fontes,40suítes conectadas e1Bridge sem banco por cultura,2719checks em AMERICAN e EUROPEAN**. Focais: helper37, itens158, financeiro82, financeiroRO231, pagamentosCRUD27, composição5, fronteira4 e Bridge5 por cultura. PHP completo informado pelo root:509testes/2989asserções.

Publicação ABL confirmada por `.runtime/container-payments-publish-20261017/active-manifest.json`; atualização somente das sessões med ociosas23/24 confirmada no recibo privado de refresh. PASOE:26fronteiras de autenticação/validação e leitura passaram, sem escrita; opções retornaram50títulos (35adiantamentos/15apósDI). Duas associações antigas foram consultadas, com isolamento read/get confirmado. Builder: recibo `container-payments-publication.json` registra versões39/40 e aprovações38/39. `runtime-read-evidence.json` confirma metadados oficiais, duas filhas com cabeçalhos, permissões create/delete semedit, opções/contexto e isolamento.

Evidências privadas ficam em `.runtime/container-payments-20261017/`; não copiar conteúdo de títulos ou credenciais para documentação. Plano de compilação e fontes congeladas em `.runtime/container-payments-compiled-20261017-v1/`. Sincronização canônica: `.runtime/container-payments-canonical-20261017/plan.json` e `verification.json`, com16artefatos publicados+8testes, hashes e backups. O plano valida os arquivos antes da substituição e interrompe se houver mudança concorrente. Não reexecutar preparação para apagar o baseline de uma sincronização já concluída.

## Teste real autorizado e concluído em19/10/2026

O usuário autorizou a leitura de dois títulos, recibos exclusivamente privados e escritas somente na fixture nova2147000102, com limpeza. Foram guardadas imagens físicas completas de ambos os títulos `ems5.tit_ap` e de todas as linhas globais `ems5.val_tit_ap`. Nenhuma dessas tabelas foi alterada.

O fluxo publicado foi exercitado pelo runtime PHP como **admin / Administrador**. A associação do adiantamento gerou a transação **12698**, um vínculo e um movimento financeiro tipo5/ação1, com valor e sinal conferidos contra a rotina central. A duplicidade retornou **409 CONTAINER_PAYMENT_EXISTS**, sem novo vínculo ou movimento. A desvinculação gerou a transação **12730**, removeu o vínculo e gravou o movimento compensatório tipo5/ação3. As auditorias confirmaram autoria, imagem de9campos do vínculo, imagem de50campos do container e imagens físicas dos efeitos financeiros. O filtro nativo **Logs Exclusão** retornou a exclusão com os dados anteriores.

A alocação e o container foram removidos pelas APIs publicadas, comparando antes as imagens físicas próprias e o marcador. A limpeza privada removeu por PK e imagem exata somente os dois movimentos próprios e o pedido/item novos. A probe independente final, compilada e executada em OpenEdge12.2, confirmou **15contadores zerados**, nenhum vínculo ou movimento global dos dois títulos, e igualdade integral das duas imagens originais e dos conjuntos de valores. O `verify-clean` confirmou também GET404 do container. Os logs de auditoria foram preservados; os registros de negócio de teste foram removidos. Não repetir a fixture nem apagar os recibos de tentativa para liberar execução.

Provas privadas em `.runtime/container-payments-20261017/`: `payments-associated-verified.json`, `payments-duplicate-verified.json`, `payments-disassociated-verified.json`, `payments-real-deletion-scope-logs.json`, `payments-fixture-cleanup.json`, `payments-physical-final.json`, `payment-physical-final-confirmation.json` e `payments-final-cleanup-verification.json`. Autorização em `.runtime/container-payments-20261019/user-authorization.json`. Ferramentas privadas de setup/cleanup e recibos originais em `.runtime/container-payments-20261019-fixture/v2/`; quatro fontes compilados no12.2/.42 e37checks TT por cultura sem bancos conectados. O harness possui10testes puros aprovados; as revisões de proteção dos dois títulos e de comparação pré-exclusão foram concluídas.

Esta etapa não alterou nem republicou código de produção ou metadados, não mudou schema, triggers, autenticação ou permissões, não reiniciou o PASOE e não executou fechamento, notas ou estoque. O segundo título apósDI foi somente lido e preservado; sua escrita continua sem teste real neste cenário. A verificação visual com a sessão do navegador não foi repetida em19/10; contratos e logs foram verificados pela API. Preservar LoginCIW1A e op1012/IOL de outros chats.
