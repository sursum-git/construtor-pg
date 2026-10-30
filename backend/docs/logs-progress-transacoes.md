# Logs Progress na transação runtime

O cliente PHP de fontes `responseContract: sursum-programs` associa a execução PASOE à transação runtime já aberta pelo dispatcher. Não cria transação de negócio distribuída, tabela nova ou endpoint de consulta no navegador.

## Contrato confiável

O handler Progress devolve `X-Sursum-Execution-Id` no formato `EXE-AAAAMMDD-NNNNNN-NNNNNN` e `X-Sursum-Log-Count`, com a quantidade de mensagens manuais enfileiradas antes do flush. O PHP usa somente esses headers de resposta; IDs e URLs presentes no payload/resposta de negócio não selecionam a execução. Headers duplicados ou IDs inválidos são ignorados.

Sempre que o ID é válido, a transação recebe o evento `api.progress.execution`, com `executionId` e `logCount`. Se a contagem é zero, não há HTTP adicional. Sem transação runtime aberta, o cliente conserva o comportamento independente anterior.

Com contagem positiva, o PHP consulta `GET /SursumDynamicQuery/executions/{executionId}/events?limit=500` na mesma origem e no prefixo da URL cadastrada de `/SursumPrograms/{programa}`. A consulta reutiliza os headers autenticados do servidor, não segue redirecionamentos e tem timeout de até cinco segundos. A coleta usa o transporte interno e não gera outra correlação recursivamente.

## Persistência e limites

Somente `log.debug`, `log.info`, `log.warning` e `log.error` da execução exata são importados como `progress.log.*` em `runtime_transaction_log`. Mensagem tem limite de 4.000 caracteres; valores conhecidos de headers sensíveis são redigidos antes do corte, incluindo o componente isolado de Bearer/Basic e a senha Basic decodificada. Metadados contêm somente `executionId`, `eventId`, `occurredAt` original (ISO ou formato europeu Progress, inclusive milissegundos com vírgula) e, quando disponíveis, `currentUser`, `pasoeAgent`, `pasoeInstance` e `pasoePid`, além da rastreabilidade normal da transação PHP. O contexto JSON livre, payloads e credenciais não são copiados.

A coleta acontece antes de interpretar o envelope de erro de negócio. Assim, mensagens de uma operação recusada continuam vinculadas à transação PHP que termina em falha. Erro, resposta inválida ou coleta incompleta acrescenta `api.progress.logs.unavailable` sem registrar URL, headers, corpo ou mensagem técnica do upstream e sem mudar o resultado do negócio. O diagnóstico usa somente `reason` enumerado e códigos/contagens numéricos (`httpStatus`, `jsonError`, `curlCode`, `receivedEvents`, `importedLogs`), quando disponíveis; por exemplo, JSON com UTF-8 inválido registra `response_invalid` / `jsonError: 5`, sem tentar inferir outra codificação.

Até 500 eventos do monitor são consultados por chamada; eventos de ciclo de vida não são importados. A fila pode ser maior que essa janela. O programador continua responsável por não escrever segredos nas mensagens ABL.

Na inclusão individual de entidade API, `RuntimeApiEntityActionService` atualiza `record_id` com a chave primária realmente devolvida pela API, usando o `createResponse.itemPath` cadastrado. Não utiliza como confirmação o ID enviado pelo navegador nem o fallback local da resposta.

## Verificação

`backend/tests/Runtime/RuntimeProgressLogBridgeTest.php` exercita um servidor HTTP isolado, o cliente real e o serviço real de transações com persistência capturada. Cobre sucesso, erro de negócio, ausência de chamada extra quando a contagem é zero, falha/coleta incompleta, contrato genérico, ID/URL recusados, identidade allowlist, redação antes do corte e vínculo da chave retornada na inclusão.

O probe de integração do workspace de conversão, `tools/construtor-local/probe-progress-log-bridge.php`, usa o container Symfony real e recebe configuração privada por STDIN. Aceita somente a fixture temporária protegida `/SursumPrograms/program-log-verificar`; sua existência não implica que esse endpoint esteja publicado permanentemente.
