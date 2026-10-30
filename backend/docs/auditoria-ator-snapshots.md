# Ator autenticado e snapshots de auditoria

## Ator

`RuntimeTransactionService` captura o ator da sessão autenticada ativa ao abrir uma transação normal ou operacional. `request_context.actor` e `metadata.actor` recebem o marcador reservado `source: authenticated-session`, `version: 1`, acompanhado somente de `userId`, `username` e `displayName`, quando disponíveis. Os dados vêm do `PermissionResolver` depois de confirmar a sessão pelo `AuthenticatedSessionResolver`; fallback de identidade por headers/demo não é prova de autenticação.

O ator enviado pelo payload/contexto ou pelo chamador do logger não sobrescreve o ator capturado. Senhas, tokens, permissões e demais dados do usuário não fazem parte desse objeto. Sem sessão autenticada, não é inventado um ator.

As consultas `read/get` existentes de `runtime_transaction` e `runtime_transaction_log` acrescentam `actor_user_id` e `actor_user_name` como campos calculados para apresentação. O nome usa `displayName`, depois `username` e por último o próprio `userId` já registrado. Logs usam `metadata.actor`; quando ausente, a consulta busca o ator da transação pai em um único lote de até 500 IDs e exige o `tenant_id` atual. A consulta exige o marcador reservado tanto no ator do evento quanto no ator da transação pai. Um histórico sem ator ou com objeto `actor` antigo sem origem verificada permanece sem usuário; o usuário que abriu a consulta não é atribuído ao evento antigo. Esses campos não são colunas físicas e não devem gerar filtros/ordenação SQL.

## Snapshot confirmado pelo Progress

No CRUD individual de fonte `responseContract: sursum-programs`, o corpo da resposta pode conter este contrato opcional, separado do registro devolvido em `data`:

```json
{
  "version": 1,
  "source": "progress-transaction",
  "resource": "despesas",
  "operation": "update",
  "recordId": 71,
  "before": {"despesa_id": 71, "descricao": "Antes", "perc_calculo": 0},
  "after": {"despesa_id": 71, "descricao": "Depois", "perc_calculo": 0}
}
```

O exemplo acima é o valor de `body.audit`. O snapshot anterior deve ser lido pelo ABL na mesma transação e com o lock usado pela escrita; o atual deve representar o estado final, incluindo coleções. O PHP não faz GET antes de gravar. O ABL só deve enviar `audit` depois do sucesso da transação de negócio.

O PHP confere versão, origem, operação, recurso e chave primária. Para `despesas` e `empresas_despesa`, o recurso deve coincidir com o `bodyTemplate.resource` cadastrado no endpoint, nunca com uma escolha do navegador. Os legados `empresas` e `tipos_anexo` não aceitam essa propriedade no request: o PHP reconhece somente os vínculos `empresas_progress`/`empresa_id`/`motor_progress_empresas` e `tipos_anexo_progress`/`tipo_anexo_id`/`motor_progress_tipos_anexo`, respectivamente, combinados com POST para a rota cadastrada `/SursumPrograms/empresas` ou `/SursumPrograms/tipos-anexo` (também com prefixo `/med/web`). O `bodyTemplate` desses legados deve conter somente a operação correspondente. A origem cadastrada vem de `metadata.apiBinding.sourceCode`; a resposta não escolhe endpoint nem recurso confiável.

O `recordId` deve ser inteiro positivo int32 e coincidir com a chave dos snapshots e o ID da operação. Inclusão exige `before: {}`; exclusão exige `after: {}`. Na inclusão, a identidade vem da resposta do servidor e não da chave enviada pelo cliente. Em update/delete, o ID validado é associado à transação PHP antes da chamada externa, inclusive quando vem somente da chave primária dentro de `record`; isso permite consultar as falhas do registro sem confirmar escrita nem snapshot de sucesso.

A auditoria mapeia apenas os campos conhecidos efetivamente presentes nos snapshots. Campos omitidos não se tornam null. Tipos primitivos e coleções conhecidas são validados; null e zero são preservados. Campos de senha, token, credenciais e outros segredos são excluídos. O contexto livre do contrato não é copiado.

Snapshots válidos preenchem `before_data`, `after_data` e `diff_data` no evento CRUD já existente. `metadata.audit` contém `available: true`, `version`, `source`, `resource`, `operation` e `recordId`. Uma API sem snapshot ou sem contrato Sursum registra os três conjuntos vazios e `metadata.audit.available: false`, com `reason: not_provided` ou `unsupported_contract`; o payload novo não é usado como estado anterior. Um audit opcional inválido registra também o aviso seguro `<entity>.api.audit.unavailable` e `reason: invalid`, preservando o retorno de negócio já confirmado.

Nos dois legados, cada snapshot não vazio deve ser completo, sem propriedades desconhecidas: Empresas usa `empresa_id`, `nome`, `cod_emp_ems2`, `cod_emp_ems5`; Tipos de anexo usa `tipo_anexo_id`, `descricao`. Tipos, limites int32 e textos de até 50/100 caracteres são conferidos, preservando null. O cadastro dos campos deve corresponder a esses nomes, caminhos JSON e tipos. O contrato parcial histórico de Despesas continua compatível.

## Lote de tipos de anexo

Após commit integral, `body.audit` pode conter:

```json
{
  "version": 1,
  "source": "progress-transaction",
  "resource": "tipos_anexo",
  "operation": "batch",
  "changes": [
    {"clientId": "row-2", "operation": "update", "recordId": 2,
     "before": {"tipo_anexo_id": 2, "descricao": "Anterior real"},
     "after": {"tipo_anexo_id": 2, "descricao": "Atual real"}}
  ]
}
```

O envelope e suas alterações têm propriedades fechadas. O PHP confere todas as linhas antes de emitir qualquer evento confirmado: correspondência unívoca com o request e com `body.results` por `clientId`, operação e ID; IDs int32 positivos, distintos no conjunto; snapshots completos, com PK correspondente; inclusão sem anterior e exclusão sem atual. `results` mantém seu formato `clientId`, `operation`, `id`. A ordem da resposta pode variar; os eventos seguem a ordem do request. O campo `original` do request serve somente à concorrência e nunca é tratado como anterior confirmado.

Cada alteração válida produz o evento CRUD existente `<entidade>.api.create/update/delete`, com before/after/diff e `metadata.audit` confirmado, incluindo `recordId`, mais `metadata.clientId`. O resumo `<entidade>.api.batch` não contém before/after do input. Audit ausente ou inválido mantém o negócio salvo, gera somente o resumo sem comparação e sinaliza `metadata.audit.available: false` com o motivo. Uma falha de negócio produz `<entidade>.api.batch.failed`, sem snapshots e com `reason: not_committed`. Nenhum evento parcial é emitido quando uma única linha de audit ou results é inválida.

A transação PHP do lote não recebe um `record_id` arbitrário: ela corresponde ao conjunto. Logs no cabeçalho da grade Tipos de anexo continuam consultando a tela; os registros envolvidos estão identificados nos snapshots e em `metadata.audit.recordId` dos eventos. Uma futura consulta de transações por qualquer ID do conjunto exige um vínculo explícito adicional; o filtro atual por `runtime_transaction.record_id` atende somente operações individuais. Logs manuais Progress continuam sendo mensagens; não ganham estados anterior/atual inventados. Dados históricos não são reescritos.

O renderer genérico de comparação e o resumo automático identificam cada snapshot confirmado pelo cabeçalho `Registro <ID> — Inclusão/Alteração/Exclusão`. O ID vem somente de `metadata.audit` confirmado, inteiro positivo int32, e os rótulos de operação são fechados; todo texto usa DOM seguro. O resumo de batch confirmado informa que as alterações estão nos eventos de cada registro, sem sugerir ausência de comparação junto às tabelas. Lote sem confirmação continua mostrando indisponibilidade. Esse enriquecimento visual não muda schema, definições assinadas nem atores.

## Consulta de exclusões e de eventos por registro

O cabeçalho padrão dos cadastros chama-se **Logs Exclusão** (`mode: deletions`) e abre somente os eventos de exclusão bem-sucedida da tela em `admin.logs-transacoes`. O formulário normal mantém Logs (`mode: transactions`), exige um registro existente e abre as transações desse registro. Grade em lote usa `mode: recordEvents`, com o mesmo requisito de registro e o destino de eventos filtrado por registro. Cada atalho verifica a permissão de leitura do destino correspondente. Documentos externos customizados são preservados; atalhos internos padrão já salvos são atualizados e ficam desativados quando o destino não é autorizado. Esse enriquecimento ocorre depois da integridade, sem alterar JSON assinado.

Os três campos de contexto são virtuais e readonly: `audit_screen_id` (string, até 160 caracteres de identificador), `audit_operation` (somente `delete`) e `audit_record_id` (inteiro positivo int32 ou sua representação decimal canônica). São aceitos somente em `filters[]` flat, com `operator: eq`, na entidade `runtime_transaction_log`; operação/registro exigem também tela. Nomes duplicados, valores vazios/inválidos e uso dos três campos em outra entidade, árvore Kendo, sort ou group retornam 422 `AUDIT_LOG_SCOPE_INVALID`. Campos de negócio com outros nomes `audit_*` continuam normais. Em logs, nomes de contexto `audit_*` desconhecidos também são recusados, evitando consultas amplas por erro de digitação.

O filtro SQL usa `EXISTS` com `runtime_transaction.id = runtime_transaction_log.transaction_id`, tela e tenant atual do pai. Exclusões exigem transação `succeeded` e sufixo exato de evento CRUD `.delete`, tanto API quanto nativo. Eventos `read/get/create/update`, `.delete.failed`, avisos e resumo de lote não entram. Ausência do snapshot opcional em `.api.delete` legado não desfaz a confirmação de exclusão; a comparação desse evento continua indisponível no viewer.

No escopo por registro, um `metadata.audit` confirmado (`available: true`, versão 1, origem Progress) usa exclusivamente o `recordId` tipado do evento. Sem esse marcador, operações tradicionais podem usar `runtime_transaction.record_id`; lotes não usam esse fallback. Assim um ID de pai não inclui outro item confirmado do lote. Exclusão e registro podem combinar-se por AND. O mesmo WHERE vale para COUNT e página; não há pós-filtro em memória. `read/get` de logs também exigem sempre o tenant do pai, inclusive sem os filtros virtuais. Não há coluna física nova, migração nem reescrita de histórico.

## Verificação

- `RuntimeAuditActorTest`: sessão real via PermissionResolver, rejeição de ator forjado, execução operacional e ausência de autenticação.
- `RuntimeAuditActorQueryTest`: SQLite real, consultas read/get, ator do evento, fallback do pai dentro do assinante e histórico sem usuário.
- `RuntimeApiAuditSnapshotTest`: snapshots confirmados, create/update/delete, null/zero/diff, campos omitidos, exclusão de segredos, contratos inválidos e compatibilidade sem snapshot, sem GET adicional.
- `RuntimeApiEntityBatchTest`: lote misto, procedência de cada snapshot, correlação request/results/audit em ordem livre, recusa integral de confirmação malformada, retorno de negócio preservado e rollback sem estados inventados.
- `RuntimeAuditLogScopeTest`: SQLite em memória com SQL real; exclusão de lote misto, legado/nativo, falhas, tela/tenant, recordId por evento, COUNT/paginação, combinação com filtros normais e entradas inválidas recusadas.
- `ScreenDefinitionLogsTest`: navegação por destino autorizado, contexto virtual válido, inclusão sem registro desativada, defaults salvos e contratos customizados preservados.
