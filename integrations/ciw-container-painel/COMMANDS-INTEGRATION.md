# Manutenção e processos — contratos publicados

Estado atual: oito JSONs publicados e validados no Builder oficial. Painel1.0.4/14abas; Despesas1.0.1. Processos op1015–op1017, todos1.0.0. Os nomes candidate.json preservam rastreabilidade da preparação; não repetir cadastros. Guia/evidências em docs/painel-container-comandos.md e context/RETOMADA_2026-10-16.md.

| Arquivo candidato | Finalidade |
|---|---|
| `cabecalho-api-source-candidate.json` | Nova fonte `motor_progress_container_cabecalho`: read/get delegam à API atual `/containers-painel`, recurso `painel`; create/update/delete delegam a `/containers-cabecalho`, recurso `containers` |
| `despesas-api-source-candidate.json` | Nova fonte `motor_progress_container_despesas`: read/get delegam a `/containers-painel`, recurso `despesas`; CRUD individual delega a `/containers-despesas`, recurso **`container-despesas`** |
| `processos-api-source-candidate.json` | Nova fonte `motor_progress_container_processos`: três comandos custom POST com opt-in `allowProcess`; read/get auxiliares permanecem na API de leitura do painel |
| `painel-1.0.4-builder-draft-candidate.json` | Atualizar entidade **existente** `container_painel_progress` e programa `op1001`; habilitar update/delete e acrescentar três abas ProcessEngine, total14 |
| `despesas-1.0.1-builder-draft-candidate.json` | Atualizar entidade **existente** `container_despesas_progress` e programa `op1003`; CRUD individual nativo em formulário, preservando consultas e colunas atuais |
| `processo-gerar-versao-builder-draft-candidate.json` | Novo `op1015`, publicado; `ciw.container-gerar-versao` |
| `processo-fechar-custos-builder-draft-candidate.json` | Novo `op1016`, publicado; `ciw.container-fechar-custos` |
| `processo-sincronizar-impostos-builder-draft-candidate.json` | Novo `op1017`, publicado; `ciw.container-sincronizar-impostos` |

Os três processos compartilham a entidade API nova `container_processos_progress`, PK `container_id`. Registrá-la **uma vez**. Bootstrap confirmou1015–1017 livres na publicação;1012 ocupado foi preservado. Para novas telas, conferir novamente códigos livres. O Builder exige operação de lista na fonte de qualquer entidade API: por isso a fonte de processos inclui `painel_read/get` seguros, nunca uma mutação disfarçada de leitura. Nenhuma tabela física é criada. As três fontes API de leitura atuais ficam preservadas.

## Cabeçalho

Somente os29 campos de `../ContainersCabecalhoCrudService.md` tornam-se editáveis; PK, flags de versão, timestamps, fornecedor derivado e demais campos conservam readonly. O teste compara a lista diretamente com o contrato do serviço. As onze abas originais, modo `embedded`, retorno para consulta e contexto obrigatório/fixo são preservados.

Os29campos editáveis têm `apiWritePath=values.<campo>`; a leitura mantém `apiJsonPath=<campo>`. O corpo transacional é `{operation:"update",resource:"containers",container_id:331,values:{narrativa:"REVIEW"}}`, com PK/contexto na raiz. Campos de apresentação readonly não são encaminhados. Isso usa o mapeamento de caminhos existente no runtime PHP, sem adapter ou alteração no core.

`allowCreate=false` é deliberado: o serviço exige ID manual na raiz da requisição, e o fluxo genérico de inclusão não possui esse contrato alinhado. A fonte descreve a operação create para completude do contrato API; **nenhum endpoint de inclusão da tela deve ser publicado**. A API ABL pode ser testada diretamente com registro novo autorizado e ID manual; isso não habilita a inclusão pela UI. Update/delete só podem ser publicados após o provider real, sua política privada e auditoria estarem validados. O backend mantém lock, sincronização fiscal, invalidação de versões, dependências e rollback; nenhum cálculo aparece no JSON.

## Despesas

Editáveis: `despesa_id,vl_prev,vl_base_imp,vl_negociado,log_manual,perc_calc`. PK e `container_id` são readonly; distribuição, item, memória, valores calculados e referências técnicas permanecem protegidos. O contexto segue obrigatório, `filter/createDefault/readonly`. As regras de manual/percentual, vínculo de empresa e proteção da despesa de distribuição são aplicadas pelo serviço central e podem recusar combinações inválidas; o formulário apresenta a mensagem da API.

Somente esses6campos usam `apiWritePath=values.<campo>`, com leitura flat preservada. Create/update enviam `values` aninhado e `container_id` na raiz; update mantém também `despesa_container_id` na raiz. O runtime pode encaminhar o registro completo na exclusão: todos os campos editáveis ficam dentro de `values`, que o wrapper de delete remove antes de chamar o serviço fechado; não sobram campos editáveis livres na raiz. Os parâmetros de processo mantêm seus caminhos na raiz e não recebem esse prefixo.

Escolheu-se **CRUD individual** com `gridEditMode=form`. O serviço ABL oferece lotes homogêneos `batch_update`/`batch_delete` (campos distintos, sem batch_create), enquanto o batch nativo do Builder usa lote misto create/update/delete com `clientId`. Não configurar `apiBatchOperationCode` nem anunciar paridade do formulário rápido sem um adapter transacional fechado e sua validação. As seis regras individuais reutilizam a sincronização central já existente.

## Processos e contexto

Todos chamam a rota fixa `/SursumPrograms/containers-processos`, com `requestContextFields:["container_id"]`, fonte `sursum-programs`, `type=custom`, `method=POST`, `allowProcess=true`. Cada programa gera o segundo opt-in no endpoint assinado `run`, com permissão própria `<prefixo>.process`; nenhum privilégio compartilhado é inventado.

| Operação cadastrada | Template estático | Parâmetros |
|---|---|---|
| `gerar_versao` | `resource:versoes,operation:gerar` | `tipo_versao_id`, `descricao`, `expected_latest_version_id` |
| `fechar_custos` | `resource:custos,operation:fechar` | `versao_id`, `expected_latest_version_id`, `log_priorizar_custo_previsto` |
| `sincronizar_impostos` | `resource:impostos,operation:sincronizar` | Nenhum, além do contexto |

IDs são int32 positivos; `expected_latest_version_id` admite0 somente ao gerar sem versão existente. `descricao` não vazia, máximo8000; o formulário não deduz a última versão nem escolhe um ID arbitrário. A consulta Versões existente fornece o ID para conferência do usuário; um lookup dinâmico futuro exige contrato específico. Não há `request_id` editável, identidade, empresa, URL, tabela ou código externo no programa.

As três novas abas usam `linkedPage.engine=process`, filtro EQ do `container_id` do pai e `requireRecord=true`; destino declara o mesmo contexto readonly/required. Abrir a aba apenas monta parâmetros. Clicar Processar exige confirmação Kendo declarativa com o efeito de negócio; cancelar não chama API. A resposta esperada é `data:{type:"message",message:"..."}`, sem URL externa ou código executável. Custos e versões usam serviços centrais separados; nenhum cálculo é repetido nos metadados.

## Logs

Não adicionar `logs` a `formOptions`: o objeto Builder é fechado e rejeita essa chave. O PHP integra configuração confiável via `ScreenDefinitionService`, condicionada à permissão administrativa existente. Painel consulta eventos de seu container, incluindo recursos filhos/processos; Despesas usa o ID físico da despesa; processos usam `container_id` fixo para o destino canônico `admin.logs-transacoes`, escopo `ciw.container-painel`. Abertura de Logs não executa processo nem salva formulário. A integração PHP deve validar os caminhos/fontes fechados, importar somente snapshots confirmados e manter os IDs próprios de cada filho.

## Fluxo oficial e validação

Plano privado: `.runtime/container-command-metadata-20261016/builder-publication-plan-candidate.json`. Inclui `scope=container-commands-20261016`, ordem, expectedVersion (`1.0.0` Despesas, `1.0.3` Painel), permissões esperadas por item, hashes dos oito JSON, `createEntity=true` somente no primeiro processo e evidências obrigatórias. Publisher restrito preparado pelo root: `tools/construtor-local/publish-container-commands.cjs`; o modo `--prepare` confere bootstrap/identidades e preenche guards do plano sem publicar. **Não executar o publisher readonly antigo diretamente**, pois ele exige permissões false e só salva entidade quando create=true. As entidades existentes precisam ser salvas pela API oficial antes do novo draft, preservando IDs de campos/metadados alheios; nunca gravar JSON assinado ou SQL de metadados. Publicar filhos/processos primeiro e painel por último evita links a programas ausentes.

Segredo continua como `__RUNTIME_SECRET__` nos fontes e deve ser resolvido apenas em memória pelo fluxo privado. O plano não contém credencial nem uma evidência de aprovação inventada; seu array `evidence` fica vazio até a integração reunir as verificações reais. Sem compilação12.2, PASOE `sursum-api/med`, auditoria/contexto PHP, teste de abertura sem execução, confirmação/permissões e CRUD autorizado com limpeza, estes candidatos não habilitam recursos no produto.

Validação estrutural local usa os normalizadores e geradores **canônicos reais** do Builder com repositórios em memória, sem banco/HTTP/publicação:

```powershell
Set-Location D:/opencode/construtor-pg/backend
php vendor/bin/phpunit --do-not-cache-result D:/opencode/conversao-sc/integration-src/container-panel/metadata/tests/ContainerCommandMetadataTest.php
```

Resultado atual desta preparação: **8 testes /392 asserções**. Cobrem os8JSON, contexto, geração real dos5programas,29campos do cabeçalho,6de despesas,14abas, preservação das11abas/colunas, templates/rotas fixos, permissões próprias, dupla habilitação, confirmação e ausência de batch incompatível. Também comprovam parâmetros required nos três processos e preservação de parâmetro opcional readonly/default; não foi necessária alteração no gerador canônico.

Correção TDD do envelope de escrita: os métodos canônicos reais `resolveDefinition`, `buildWritePayload` e `mergeRequestPayload`, com entidades normalizadas dos candidatos, reproduziram2falhas em update por ausência de `values`. Após alterar apenas os35`apiWritePath` editáveis, os mesmos testes passaram, incluindo create/update/delete com registro completo, leitura flat, PK/contexto na raiz e parâmetros de processo sem prefixo. Logs RED/GREEN: `.runtime/container-command-metadata-20261016/values-envelope-{red,green}-tests.log`. Os oito hashes foram atualizados no plano e a preparação anterior foi invalidada; executar novamente `--prepare` antes de qualquer publicação. Não houve alteração de core/ABL nem publicação nesta correção. Estes testes não equivalem a teste de escrita ou publicação real.

