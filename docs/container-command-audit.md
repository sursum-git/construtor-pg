# Auditoria dos comandos do painel de containers

Integração de código de 16/10/2026 no construtor canônico. Não publica fontes ABL, rotas, metadados ou telas; habilitação e testes reais são uma etapa separada.

O PHP reutiliza RuntimeTransactionService/RuntimeTransactionLog. O autor continua vindo da sessão autenticada capturada na transação; `audit.actor` da resposta é validado estruturalmente e descartado. Nenhuma identidade do navegador ou do Progress substitui a autoria PHP.

## Fontes aceitas

Somente `responseContract=sursum-programs`, POST e bodyTemplate estático com exatamente `resource` e `operation`. Caminho permitido com prefixo `/SursumPrograms/` ou `/med/web/SursumPrograms/`.

| Entidade | Fonte registrada | Rota | Resource estático | Operações |
|---|---|---|---|---|
| container_painel_progress | motor_progress_container_cabecalho | containers-cabecalho | containers | create/update/delete |
| container_despesas_progress | motor_progress_container_despesas | containers-despesas | container-despesas | create/update/delete |
| container_processos_progress | motor_progress_container_processos | containers-processos | versoes / custos / impostos | gerar / fechar / sincronizar, respectivamente |

CRUD individual continua nativo. Este delta não habilita batch de despesas: o serviço ABL possui batch_update/batch_delete, incompatíveis com o envelope genérico de lote existente.

## Contrato confirmado

`success:true` e audit com version1/source progress-transaction. Cabeçalho: resource containers, recordId=container_id, before/after mestre e changes. Despesas: resource container-despesas, recordId da despesa, container_id, before/after despesa, master={before,after}, changes. Processos: resource container_processes, operation gerar/fechar/sincronizar, recordId=container_id, before/after mestre, changes. `actor` e `warnings` são opcionais; campos extras de audit/change/snapshot são recusados.

Cada change possui exatamente resource, operation create/update/delete, recordId, before e after. Cada snapshot não vazio tem chave e escopo do mesmo container; pp_container_permissao/saldo usam o campo físico nr_container. Os tipos/campos permitidos estão na constante SCHEMAS de RuntimeContainerAudit, derivados dos dicionários físicos revisados. Nos vínculos genéricos, zero/null somente nos campos físicos opcionais item_container_id de despesas_container, anexos_container e cta_corren_fornec_imp. PKs e vínculos de versão/item de custo continuam validados.

PKs compostas dos quatro auxiliares de versão são strings JSON array com os campos do índice primário, na ordem física: despesas [versao_id,despesa_id], NFs [versao_id,documento,serie,estab,nat_operacao,cod_emitente], pagamentos [versao_id,num_id_tit_ap], descontos [versao_id,desconto_importacao_id]. Exclusões de nfs_container e pp_container_permissao/saldo usam strings JSON object com campos nomeados exatos. Valores e tipos precisam coincidir com o snapshot.

Ledger cta_corren_custo_distr segue os vínculos físicos: tipo3 exige item_container_id=0 e item_container_custo_id inteiro positivo; item/versão são resolvidos somente se a FK apontar item_container_custos confirmado no mesmo envelope e no mesmo container. Sem essa prova, ou com vínculos mistos, todo o audit fica indisponível. Tipo4 exige item direto positivo e snapshot=0; não inventa versão. O ledger comprovado participa das consultas por item/versão. `request_id` é aceito somente no audit de processos, como string de até128 caracteres; outras propriedades extras continuam recusadas. `changes[].operation` precisa ser string antes de entrar em métodos tipados, evitando TypeError após commit.

Na exclusão do cabeçalho, os mesmos quatro auxiliares de versão são aceitos exclusivamente como `delete` e somente quando o comando raiz também é `delete`. Cada `versao_id` precisa ter um evento `delete` de `versoes_item_custo_container` validado no mesmo envelope e no mesmo container. A prova independe da ordem dos eventos; antes/depois, campos, tipos e PKs compostas continuam fechados. Falta da versão confirmada, FK divergente, outro container ou tentativa de criar/alterar auxiliar invalidam todo o audit sem importar snapshots parciais. Essa extensão PHP acompanha o novo contrato de limpeza ABL; não implementa a exclusão de negócio.

Todo o envelope é validado antes de emitir qualquer snapshot confirmado. Relações de item/versão vêm dos próprios campos físicos; item_custos recebe o vínculo por item_container_custos da mesma transação confirmada. Efeitos duplicados de mestre/despesa são coalescidos somente quando before/after coincidem exatamente. Não há inferência de before/after a partir do request.

Auditoria ausente ou inválida mantém o resultado já confirmado e devolve aviso para não repetir a operação. Processos confirmados com apresentação inválida também recebem mensagem segura, sem transformar o commit em erro/retry. Falhas anteriores à confirmação não importam snapshots. Mensagens de processo possuem containerContext de origem runtime-validated-context, construído após validar a fonte/rota/contexto; serve para localizar falhas, sem afirmar commit.

## Consulta e interface

ScreenDefinitionService injeta Logs por registro, condicionado ao acesso a admin.logs-transacoes, nas entidades conhecidas do painel. O ProcessEngine reutiliza a janela Kendo de Logs existente e o contexto container_id inteiro validado; o botão funciona mesmo com hideHeader e abrir/clicar Logs não executa processo. Telas administrativas mantêm a proteção contra Logs recursivo.

A consulta de logs aplica o tenant da transação pai antes de contar/paginar. ciw.container-painel/ciw.containers usam containerId; despesas/anexos usam seu recordId; versões usam versionId; itens/custos usam itemContainerId. Só recursos conhecidos e metadados confirmados pelo importador participam das relações. Processos recusados ou com audit indisponível podem aparecer pelo containerContext validado, com snapshots vazios. As quatro páginas já integradas continuam com os contratos anteriores.

## Verificação

TDD observado para importação, rejeição integral de snapshots falsos, contexto readonly das escritas, PK composta, aviso pós-commit, relações item/versão, consulta por container/filho/tenant e botão Kendo. Suíte PHP completa: 462 testes/2674 asserções nesta revisão. Smokes: container-process-logs, api-process-context, crud-transaction-logs e crud-audit-diff passaram. Nenhum comando de negócio foi executado por estes testes.

`git diff --check` nos quatro fontes alterados por esta integração passou; o check global aponta whitespace anterior em CONTEXTO_PROJETO.md e docs/continuidade-codex.md, preservado.
