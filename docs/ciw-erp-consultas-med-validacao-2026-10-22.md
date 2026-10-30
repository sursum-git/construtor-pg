# Consultas ERP MED — validação em 22/10/2026

Publicadas pelo Builder e validadas no PASOE `sursum-api/med` e no frontend existente:

| Origem Scriptcase | Tela | Programa / versão | Escopo entregue |
|---|---|---|---|
| cons_nota_fiscal | ciw.notas-fiscais-med | cd0105 / 1.0.0 | Consulta independente de notas fiscais MED |
| cons_tit_ap | ciw.titulos-pagar-med | cd0106 / 1.0.0 | Consulta independente de adiantamentos MED não estornados |

O usuário escolheu **MED inicialmente, com origem explícita na tela**. Título e subtítulo informam MED. A Home assinada recebeu dois atalhos em CIW → Consultas ERP, preservando os demais menus e permissões humanas. Versões Builder54/55, aprovações53/54. As duas telas são somente leitura; nenhum registro de negócio foi criado, alterado ou excluído nesta entrega.

## Origem e identidade

Fontes Scriptcase: `root@192.168.0.170:22`, `/var/www/clients/client1/web2/web/ciw/{cons_nota_fiscal,cons_tit_ap}/`. Snapshots em `sources/scriptcase/phase2/`. Nenhum PHP legado foi executado. Seis fontes atuais conferidos por SFTP em22/10: equivalentes após a mesma sanitização original dos snapshots; quatro hashes brutos diferem somente pelos literais omitidos na cópia de análise. Não houve divergência de regra. Hashes brutos e resultado da comparação em recibos privados, sem copiar credenciais para as memórias.

- Notas: `ems2med.nota-fiscal`, natureza iniciada por `3`; oito campos legados, mais estabelecimento/série para identidade. Chave completa `cod_estabel,serie,nr_nota_fis`, índice físico único `ch-nota`.
- Títulos: `ems5.tit_ap`, `cod_empresa=500`, não estornado e primeiros oito caracteres de `ind_tip_espec_docto='antecipa'`; doze campos legados. Chave `cod_empresa,cod_estab,num_id_tit_ap`; índice físico único `titap_token(cod_estab,num_id_tit_ap)`.
- MED foi resolvida pelo cadastro `espec.empresas`, correspondência EMS2=5 → EMS5=500, confirmada por leitura real da API existente. `CiwMedCompanyContext.p` recusa correspondência ausente/ambígua; nenhuma empresa é recebida do navegador ou presumida por nome.
- `record_key` é uma string JSON com a chave composta, oculta no frontend e validada por campo/tipo no servidor. Número da nota isolado não identifica o registro.

## Rotinas e contratos

`CiwCadastrosCrud.p` manteve autenticação/rota/recursos anteriores; acrescentou dois recursos fechados que chamam o mesmo **CiwErpReadService.p** com buffer e empresa resolvidos internamente. Não recebe banco, tabela, SQL ou programa do navegador. Somente `read/get`; outras operações recusadas. Não adiciona fórmulas de impostos/custos nem writers financeiros.

A coleta/cálculo/persistência de impostos, custos e versões continuam nos serviços reutilizáveis existentes, enumerados em `integration-src/ciw-fase2-20261022/analysis.json`. Não copiar essas regras para APIs, PHP ou metadados. A consulta atual não usa parâmetros IMA; consumidores existentes continuam usando `parametros_ima.i`, leitura NO-LOCK sem cache.

O motor OO de consultas existente foi avaliado: oferece filtros planos AND e resposta com `hasMore`, sem total exato nem ABI de buffer confiável que permita os testes em TT. Por isso foi criado **um único leitor compartilhado**, com duas descrições de campos/predicados, sem duplicação da infraestrutura por tabela ou alteração global do motor OO.

Na fonte existente `motor_progress_ciw_cadastros`, quatro operações aditivas usam o mesmo POST `/SursumPrograms/ciw-cadastros`:

- `notas_fiscais_med_read/get`: corpo fixo `resource=notas_fiscais_med`, `operation=read/get`.
- `titulos_pagar_med_read/get`: corpo fixo `resource=titulos_pagar_med`, `operation=read/get`.

Leitura aceita `skip`0..100000, `take`1..200, árvore de filtros com até50folhas/profundidade6 e até4ordenações. Retorna `{success:true,data:[],total:n}` com total exato e página calculada no ABL. `get` recebe `id=record_key` e devolve um objeto; não pagina novamente no PHP. Consulta dinâmica NO-LOCK, ordenação estável pela chave natural, limite de10segundos, erro sem resultado parcial. Os literais numéricos são preparados em AMERICAN com restauração da cultura anterior, inclusive em falha.

`RuntimeCiwErpQuery.php` fecha fonte/rota/recursos e normaliza os DTOs reais da barra/colunas nativas: rótulos não mudam tipo/campo; operadores camelCase, listas e faixas viram folhas tipadas. Datas `Date` serializadas como timestamps são convertidas para a data civil **America/Sao_Paulo**; ISO curto já é data civil. Essa origem/fuso é o contrato inicial MED, não uma implementação de seleção mundial de fusos. Faixas CHARACTER permanecem sob a comparação ABL, evitando a comparação numérica de strings do PHP. A profundidade é conferida depois da composição final.

`RuntimeApiEntityActionService.php` tem um ramo restrito a essas duas entidades: transporta filtros/página, valida envelope/total/tamanho, usa os mapeadores e transação/logger nativos, registra duração/status/total/origem e preserva o usuário da sessão. As consultas não geram imagens anterior/atual porque não alteram dados. Autorização, identidade e logs do transporte permanecem no fluxo existente.

Frontend por JSON, sem páginas paralelas nem alterações no Kendo. Todos os campos são readonly; inteiros mantêm tipo integer e sem fração; decimais são numéricos com apresentação n5, sem arredondamento do valor no leitor. Os campos monetários não entram na barra: o gerador atual criaria editores text/contains incompatíveis. Continuam disponíveis como colunas numéricas e detalhe, com filtros numéricos da grade. NF tem10colunas, títulos12; página inicial50.

## Validação realizada

- OpenEdge12.2 no `.42`: quatro fontes compilados, `COMPILER:ERROR=no`, NUMDBS0; **47checks em cada combinação** AMERICAN/EUROPEAN × UTF-8/ISO8859-1. Compilação local12.8 anterior foi apenas diagnóstico; nenhum .r12.8 foi publicado.
- PHP canônico: **622testes / 3688asserções**, sem falha. Regressões reproduziram antes da correção DTO real da barra, timestamps e operadores; incluem faixas CHARACTER e profundidade final.
- Revisão independente final sem defeito concreto remanescente. Os erros da barra nativa e comparação de faixas encontrados na revisão foram corrigidos e revalidados.
- Publicados somente três pares p/r12.2 em `/mnt/datasul/ERP/sursum/sursum-api/programs` (compartilhamento `.137`), com backup, hashes anteriores e conferência final. Dependências novas antes do dispatcher. Nenhuma rota/token/schema/PROPATH global modificado.
- Recarga oficial JMX somente sessões MED ociosas45/46 da mesma instância sursum-api; sem restart ou troca de instância.
- PASOE real: ambas as leituras limitadas funcionaram; títulos retornam apenas empresa500. Contagens observadas no momento:6021notas e11730títulos, sujeitas a mudanças operacionais.
- Runtime real: definição nativa válida, paginação sem repetir página, detalhe por chave completa, filtro textual produzido pela função nativa `buildFilterValue`, inteiro, data, faixa, consulta vazia; quatro consultas inválidas recusadas por tela.
- Edge isolado:50linhas por tela, MED visível, duplo clique abre detalhe nativo, sem botão Salvar e sem erro JavaScript. Sessão humana do navegador preservada. Capturas comerciais somente nos recibos privados.
- Home com assinatura/concorrência verificadas, dois atalhos adicionais e hashes de permissões preservados. Catálogo atual:32definições CIW HTTP200; isso não significa262aplicações integralmente convertidas.

## Limites e continuidade

**cons_tit_ap continua parcial como aplicação Scriptcase completa.** A tela nova atende a consulta independente MED. O legado também é um seletor contextual de associação/desvinculação para container e processo de compra; não substituir todas as chamadas por uma grade somente leitura. Associação de container já usa writer reutilizável do painel. Adaptar os demais chamadores/ações com contexto validado e os mesmos serviços, sem copiar regras financeiras.

IMA, ativação do seletor empresarial e concessão geral de permissões não fazem parte deste recorte. Continuar na ordem do inventário, permissões CIW ao final e Conta corrente fornecedor fora por orientação vigente. Preservar as frentes PV/NF/boSaldoPV/IOL e outros trabalhos; não executar fixtures antigos de negócio para refazer evidências.

## Arquivos e recibos

- Candidatos finais: `integration-src/ciw-erp-consultas/{*.p,backend,metadata}`.
- Canônico ABL: `D:/opencode/motor-progress/v2/sursum-api/programs/{CiwErpReadService,CiwMedCompanyContext,CiwCadastrosCrud}.p`; testes em `tests/abl/ciw-erp-consultas`.
- Canônico PHP: `D:/opencode/construtor-pg/backend/src/Runtime/{RuntimeCiwErpQuery,RuntimeApiEntityActionService}.php`, testes `backend/tests/Runtime`; JSON em `integrations/ciw-erp-consultas`.
- Ferramentas delimitadas: `tools/construtor-local/{publish-ciw-erp-consultas.cjs,verify-ciw-erp-consultas.cjs,publish-ciw-erp-home.php}`. Não repetir publicações se recibos já existem.
- Privado ignorado `.runtime/ciw-fase2-20261022/`: compiled122/, canonical-manifest.json, active-manifest.json, med-session-refresh.json, source-recheck.json, pasoe-read-proof.json, publicações54/55, runtime-verification.json, ui-verification.json, home-link-publication.json, php-canonical.log e final-verification.json. Não copiar conteúdo de conexão, senhas ou tokens para memória/fontes/exemplos.

A indisponibilidade inicial da rede foi resolvida; túnel padrão19910 para MED9910 restabelecido por SSH com chave de host verificada, credencial só em memória. O backend local continua usando a fonte existente. Não reconfigurar fonte ou trocar PASOE como fallback para uma interrupção futura.
