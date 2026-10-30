# Cabeçalho do Painel Container — contrato transacional

Fonte preparada em `ContainersCabecalhoCrudService.p`; não há rota de escrita habilitada nem provider de negócio publicado por este artefato. O provider `ContainersCabecalhoRulesFixture.p` existe somente para testes com temp-tables e não deve ser publicado.

## ABI e requisição

Parâmetros, nesta ordem: INPUT HANDLE `phContainer` (espec.pp-container), `phFornecedor` (EMS5.fornecedor), `phEmpresas` (espec.empresas), `phRuleProvider` (procedure persistente confiável); INPUT JsonObject `poPolicy`, `poRequest`; OUTPUT JsonObject `poResponse`. Os buffers são fornecidos pelo wrapper, sem nomes de tabela enviados pelo navegador.

Requisição fechada: `{operation:create|update|delete|get,container_id?,id?,values?}`. Create exige `container_id` manual na raiz e proíbe `id`; não há gerador comprovado. Nas demais operações, `id` é alias de `container_id`, e ambos precisam coincidir quando presentes. PK dentro de `values` é rejeitada. Get não recebe `values`; update/create exigem objeto não vazio. Todos os nomes e operações são sensíveis a maiúsculas/minúsculas. Identificadores positivos de 32 bits aceitam número JSON inteiro ou string de dígitos.

`poPolicy.actor` é exclusivamente interno, derivado da autenticação do wrapper: `{login:string,id?:string}`. O serviço normaliza somente esses campos; o navegador não pode informar identidade. Get é leitura NO-LOCK. Create/update/delete exigem chamada **fora de transação ambiente**; o serviço possui seu próprio DO TRANSACTION e rejeita composição externa para não emitir snapshots antes do commit real.

Campos editáveis de `values`:

```text
cod_estabel,cod_emit_forn,narrativa,dt_recebimento,dt_prev_chegada,situacao,
vl_dolar_venda,vl_dolar_compra,log_liberar_custo_realizado,medidas_container,
dt_prev_embarque,dt_real_embarque,situacao_container_id,ind_dt_embarque,
ind_dt_prev_chegada,ordem_busca_dolar_di,log_pagto_final,cod_agente_cargas,
cod_armador,perc_custo,vl_dolar_di,log_gerar_saldo_a_pagar,log_gerar_distr_custo,
ordem_busca_dolar_compra,cod_tipo_proj_dolar,vl_proj_dolar,ptax,cod_depos,
dt_prev_chegada_loja
```

Tipos vêm do dicionário fechado. Boolean exige boolean JSON; decimal exige número JSON; inteiro exige número integral dentro de 32 bits. DATE aceita `YYYY-MM-DD` válido ou null; strings culturais são rejeitadas. Texto respeita limites dos campos de origem. Flags de versão, timestamps, nome do fornecedor e demais campos reservados são somente leitura. A criação exige estabelecimento, fornecedor, as duas ordens de cotação e tipo de projeção. A ordem aceita entradas1/2/3/4; projeção aceita1/2/3.

## Regra principal e origem

Referências detalhadas estão em `docs/containers-painel-regras-inventario.md`. Fonte principal: `sources/scriptcase/containers/form_pp_container/form_pp_container_apl.php`.

| Comportamento | Origem PHP |
|---|---|
| Nome abreviado do fornecedor atribuído no servidor | beforeInsert7550, beforeUpdate7579, buscarNomeAbrevFornecedor18515; lookup EMS5 fornecedor.nom_abrev por código/empresa |
| Create sem sincronização fiscal de update | afterInsert9098; a sequência fiscal começa somente em afterUpdate9123 |
| Update sincroniza impostos e despesas calculadas | 9123–9124; responsabilidade obrigatória de OnUpdate |
| Seis parâmetros dólar invalidam versão | 9153,19617; compra, DI, duas ordens, tipo e valor de projeção |
| Data prevista de chegada à loja omitida preserva persistido | 7582 usa variável PHP indefinida; não se inventa substituto |
| Dependência comercial deve bloquear antes de qualquer estorno | beforeDelete7612; cascade legado7644–8005 precisa de provider concreto e transação única |

Create define situação ERP1 e versão desatualizada, preservando defaults do dicionário nos demais campos omitidos. Nome do fornecedor vem de `nom_abrev` (formato origem15; o formato de exibição12 do destino não trunca o conteúdo). O estabelecimento escolhe empresa EMS2 pelo prefixo1/5, com mapeamento EMS5 em empresas. Referência ausente/ambígua causa rollback.

Update obtém EXCLUSIVE-LOCK NO-WAIT e snapshot anterior antes de alterar campos. Somente diferença nos seis parâmetros de dólar marca versão desatualizada e grava DATETIME local atual; demais alterações preservam esses flags. `OnUpdate` roda dentro do mesmo DO TRANSACTION. Delete chama `BeforeDelete` antes de remover o mestre. O serviço não contém cascade genérico nem estorno presumido.

## Provider confiável obrigatório

Preflight ocorre antes de qualquer escrita de update/delete. A procedure precisa ser persistente, declarar o callback necessário em INTERNAL-ENTRIES e responder:

```text
GetCapabilities(OUTPUT JsonObject {
  contract:'container-rules-v1', onUpdate:boolean, beforeDelete:boolean
})
OnUpdate / BeforeDelete(
  INPUT HANDLE phContainerExclusiveLocked,
  INPUT JsonObject {operation,container_id,actor,before},
  OUTPUT JsonObject {success:true,changes:[{resource,operation,recordId,before,after}]}
)
```

GetCapabilities não pode produzir efeitos. Capacidade false, callback ausente ou contrato incompatível bloqueia antes de gravar. OnUpdate deve sincronizar impostos e atualizar despesas calculadas. BeforeDelete deve primeiro validar `pp-it-container` e outras dependências; somente depois executar os estornos/cascades comprovados. Nenhum callback pode fazer commit externo, emitir log de sucesso ou executar efeito irreversível antes do commit do serviço. Um fiscal composto pode devolver snapshots pendentes para agregação; eles não são audit confirmado antes do retorno do cabeçalho.

Cada mudança deve identificar recurso conhecido, operação create/update/delete, ID tipado e snapshots JSON antes/depois. IDs compostos ficam restritos a nfs_container, pp_container_permissao, pp_container_saldo_repres e pp_it_container; demais recursos usam inteiro positivo. Mudança duplicada exige coalescer antes/depois no provider. Snapshot malformado causa UNDO do mestre e filhos. Falha do provider nunca resulta em update parcial.

Resposta de escrita bem sucedida contém `success:true`, `data` do mestre (exceto delete) e `audit:{version:1,source:'progress-transaction',resource:'containers',operation,recordId,before,after,changes,actor}`. Antes de create e depois de delete são objetos vazios. Audit só é entregue após fim do DO TRANSACTION. Erros têm `success:false,error:{code,message}` e não expõem audit confirmado. O wrapper faz log real depois desse retorno.

## Evidência e pendências de publicação

Diagnóstico OpenEdge12.8:99 verificações em AMERICAN e99 em EUROPEAN, compilação sem erro. Inclui criação manual/duplicidade, defaults, fornecedor derivado/ausente/ambíguo, datas, tipos culturais inválidos, PK/flags readonly, aliases conflitantes, objeto vazio, ausência/capacidade false de provider, alteração de dólar, preservação de data omitida, transação ambiente bloqueada e rollback real do mestre+filho em falha após escrita/remoção. Fixture afirma ausência de transação ambiente e exige TRANSACTION ativo em cada callback.

Logs privados: `.runtime/container-cabecalho-local/compile-containers-cabecalho.log` e `containers-cabecalho-tests-{AMERICAN,EUROPEAN}.log`. Não houve escrita em banco de negócio. Bloqueio concorrente real entre sessões, compilação12.2, ligação do provider concreto ContainersCabecalhoRules.p, logger e validação PASOE permanecem responsabilidade da integração final; somente estes testes locais não habilitam CRUD no produto.

Avisos opcionais do provider são copiados para audit.warnings somente após commit: array de até100 textos de até4000 caracteres. Tipo malformado causa rollback de mestre/filhos. Testes cobrem mensagem fiscal propagada e objeto indevido rejeitado. O provider concreto preparado e suas sequências estão documentados em ContainersCabecalhoRules.md.
