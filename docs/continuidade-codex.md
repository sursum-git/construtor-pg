# Continuidade para novas sessoes Codex


## Login CIW bl_tela_login — estado vigente17/10/2026

Plano complementar aprovado e executado aqui. Provedor **ima-user-web** integrado
ao AuthService/login existentes, cadastrado/habilitado pelo comando governado.
Usuario indicou **tasilpar/default**, vinculado pelo CRUD administrativo; grupos,
permissoes e demais contas preservados. Nenhum usuario/senha/grant Progress alterado.
Campo auth_source acrescentado a entidade compartilhada auth_user via Builder
oficial, sem schema/reseed: preservado modo none; normalizador registrou defaults
administrativos e globalTable=true para tabela tecnica ja compartilhada. Backups
privados auth-user-entity-{before,after}.json; nao repetir seed/import.

ABL12.2/.42:108servico+31fronteira em cada cultura AMERICAN/EUROPEAN. Revisao
independente unica concluida; lacuna Unicode reproduziu RED ISO8859-1 e foi
corrigida com GetLongchar UTF8/comparacao de bytes. EncodingGREEN3+3; espec tem
codepageISO8859-1. PHP completo491testes/2846assercoes, Auth26/116 e lint container.
Publicado em share137 via111, **sursum-api/med/9911**: handler dedicado
/CiwAuthentication sem ApiExecutionRepository/payload/CORS, no-store.11negativos
PASOE apos delta e11negativos da autorizacaoCIW passaram. Probe exclusivo: zero
execucoes de payload, nenhum payload lido. Sem negocio escrito.

Manifestos privados em D:/opencode/conversao-sc/.runtime/ciw-auth-20261017/:
publish/active-manifest.json + encoding-publish/active-manifest.json (prevalece
no servico .p/.r). ROOT.handlers ativado por tcman jmx refreshWeb para med;
refreshAgent sozinho nao recarrega mapping. Delta carregado apenas em sessoes
med ociosas; nao reiniciar/refresh por rotina. CIW_AUTH_CONNECTION_FILE aponta
arquivo privado; nunca imprimir/conservar conteudo em memoria de projeto.

**Atualizacao:** pessoa confirmou login tasilpar. GET /api/auth/session HTTP200
confirmou ima-user-web; mesma sessao ativa no servidor por hash, provider/type
corretos, sem impersonacao. AllowlistCIW agora somente ima-user-web; manifesto
source-enable/active-manifest.json prevalece no config de autorizacao, token
preservado.11negativos repetidos apos ativacao passaram.

**Pendente:** tasilpar teve as7decisoes de Coloracoes negadas no PASOE. ProbeRO
12.2 confirmou5vinculos de grupo e zero concessoes para grid_pub_coloracoes e
form_pub_coloracoes. Nenhum grant/usuario/permissao modificado. Permissoes locais
seguem apenasIOL. Pergunta enviada por outra conta existente/autorizada para
ALLOW, sem senha. Nao concluir1A nem habilitar writers; faltam positivo e
logout/revogacao reais. Sessao atual preservada. DiagnosticoPASOE usa identidade
derivada da sessao real verificada, nao prova uma telaCIW autorizada no PHP.
Admin continua local. Dois minors do cadastro adiados conforme relatorio. Proximo
funcional na ordem geral: Coloracoes; Conta corrente fornecedor/Tarefas excluido
temporariamente. Relatorio: D:/opencode/conversao-sc/docs/integracao-ciw-auth-validacao.md.

## Painel de containers — comandos publicados em16/10/2026

**Estado atual do painel; substitui descrições históricas readonly abaixo.** Painel ciw.container-painel/op1001/1.0.4 com14abas, cabeçalho update/delete e Despesas/op1003/1.0.1 CRUD individual. Processos nativos **op1015/ciw.container-gerar-versao**, **op1016/ciw.container-fechar-custos**, **op1017/ciw.container-sincronizar-impostos**, todos1.0.0. op1012 já estava ocupado por iol.inicial-gerencial e foi preservado. Três fontes novas motor_progress_container_cabecalho/despesas/processos; JSONs publicados pelos endpoints oficiais Builder/governança. Não repetir creates nem reimportar drafts antigos.

Reutilização mantida: Collector/Calculator/Distribuição/Version/Inputs/Close/Fiscal em serviços separados; wrappers sem cópia de fórmulas. NAT-OPERACAO-IMP corrigido, padrão vazio, NO-LOCK/sem cache; distribuição pela empresa do container. Snapshot sob lock e audit confirmado somente após commit raiz; PHP importa contrato fechado para transação/log existentes, autoria da sessão autenticada, eventos mestre/filhos vinculados ao container. Cabeçalho29campos e despesas6campos usam values.<campo>; processos/contexto permanecem na raiz. Despesas respeitam eco inalterado de campos derivados no formulário completo e recalculam no serviço.

**Publicado/validado:** lote principal60artefatos+3rotas .runtime/container-commands-publish-20261016/active-manifest.json; delta ativo de header/trigger .runtime/container-delete-compat-publish-20261016/active-manifest.json (prevalece nos arquivos sobrepostos). Compile real OpenEdge12.2/.42 final em .runtime/container-commands-compiled-20261016-v5/:62fontes sem erro,30suítes,2167checks em cada cultura AMERICAN/EUROPEAN. PHP462testes/2674asserções; root e revisão independente27testes/414asserções do delta. Metadados8/392, smokes contexto/confirmação/Logs/flag sessão passaram. PASOE13fronteiras novas,27custos,25financeiro e PHP11consultas passaram.

**Negócio real testado com autorização específica do usuário:** SOMENTE novo container2147000100/tagSC_TEST_CMD_20261016_A, ERP sintéticoZTSC161026. Cabeçalho, despesa individual create/update/delete, sincronização fiscal, versão2358 e custo6144 confirmados. Logs73eventos confirmados após limpeza, usuário Administrador/admin, valores1→2 na despesa, filhos/versão/custo e exclusões com snapshot anterior. UI confirmou14abas, três formulários, cancelamento Kendo e Logs contextual/Anterior/Atual. Exclusão bloqueou custos antes da limpeza. Nenhum container/nota/estoque existente foi alterado.

**Limpeza concluída:** .runtime/container-commands-20261016/command-cleanup-verification.json confirmou12tabelas, zero registros restantes do teste e2393containers. Testes consumiram IDs reais; não restaurar/reiniciar sequências. Transação1624 preserva a exclusão do master/versão/5auxiliares. Os quatro tipos de snapshots auxiliares são removidos/auditados antes das versões; PHP só aceita essesDELETEs com versão excluída no mesmo envelope/CID. Getter/contexto/runtime não recebem tabelas, programas, conexões ou políticas do navegador.

**Dependência legada resolvida:** trigger original /mnt/datasul/ERP/especificos/ems2/ima/estrg/tdpp001.p cadastrado no banco com barra invertida. Mesmo código compilado12.2 em sursum-api/legacy-triggers/tdpp001.p/r; dois aliases literais estrg\tdpp001.p/r no diretório LOCAL /dados/pasoe/sursum-api/webapps/med/WEB-INF/openedge já presente no PROPATH. CIFS não aceita esses nomes; tentativa no compartilhamento foi restaurada antes da publicação local. Sem alterar schema/definição de trigger/PROPATH/autenticação e sem desabilitar trigger. Preserva bloqueios de situação3/pedidos. OEJMX oficial recarregou apenas sessões med11/12/13 ociosas, resposta success salva. Não repetir refresh por rotina.

Sincronização canônica: .runtime/container-commands-canonical-20261016/verification.json e context-verification.json; arquivos publicados em motor-progress/v2/sursum-api, testes em tests/abl/container-panel, JSONs e teste em construtor-pg/integrations/ciw-container-painel. Core PHP/JS e testes no construtor-pg; ProcessDefinitionValidator aceita apenas booleano currentUser.forcePasswordChange, mantendo bloqueio de segredos. Preserve diffs dos demais chats e do painel IOL.

Guia da entrega: [manutenção/processos e logs](D:/opencode/conversao-sc/docs/painel-container-comandos.md). Origem Scriptcase continua root@192.168.0.170:22, /var/www/clients/client1/web2/web/ciw; cópias em sources/scriptcase/containers/<aplicação>/, fluxo grid_pp_container→bl_ponte_painel_container→menu_container→form_pp_container. Credenciais não estão nesta memória.

**Continuidade fora desta entrega:** manutenção/alocação de itens, associações de pagamentos/NFs, distribuição manual e anexos binários. Não declarar conversão integral do menu. Usuário excluiu temporariamente do escopo geral CIW o programa rotulado conta corrente fornecedor; preservar registro específico em PROJECT_CONTEXT.md, sem inferir exclusão de todo financeiro. Sem commit/push/schema/reseed/reassinatura.



## Retomada da conversão Scriptcase em16/10/2026

Leia primeiro **[checkpoint atualizado](D:/opencode/conversao-sc/context/RETOMADA_2026-10-16.md)** e [validação atual](D:/opencode/conversao-sc/docs/integracao-painel-container-validacao.md). Origem root@192.168.0.170:22, `/var/www/clients/client1/web2/web/ciw`, URL `https://imaonline.imatextil.com.br/ciw/`. Cópias em D:/opencode/conversao-sc/sources/scriptcase/containers/<aplicação>/. Fluxo: grid_pp_container→bl_ponte_painel_container→menu_container→form_pp_container. Inventário completo no checkpoint. Sem credenciais nesta memória.

**Estado atual:** consulta ciw.containers1.0.2; **painel1.0.3 com11abas readonly publicadas**, incluindo Custos dos Itens/op1008 e Resumo/op1009. Getter **NAT-OPERACAO-IMP corrigido**, padrão vazio, sem cache/NO-LOCK; valor físico31201M,31206,31207. Include/consumidores compilados12.2, publicados/ativados em sursum-api/med. Coletor preserva histórico ausente como JSONnull sem alterar fórmulas. Manifesto ativo de custo D:/opencode/conversao-sc/.runtime/container-null-history-publish-20261016/active-manifest.json; financeiro nat-operacao-imp-publish-20261016/finance-active-manifest.json. Manifestos anteriores históricos.

Lote coerente12.2 atual:52fontes sem erro,24suites,1962checks/cultura em .runtime/container-panel-coherent-compiled-20261016-v2/. PASOE27checks custos/4recursos/2moedas, financeiro25 e PHP11telas passaram; UI332526 mostrou Resumo e Custos preenchidos;2393containers preservados. Sincronizados26arquivos canônicos das consultas/metadados, com guards/hash/diffs preservados, além da include central. Prova em .runtime/container-panel-canonical-current-20261016/verification.json.

**Preparado privado, sem habilitar escrita:** driver de versões até2snapshots com rollback/instabilidade e política distribution_policy; Inputs/Close unitários implementados,175checks/cultura12.2; Fiscal128/Rules45/Runtime45/Despesas128/Distribuição55+55/Version186/Aux22/Context11/Composition34/MissingActual7 passaram no lote coerente. Não reutilizar .r anteriores. Apenas o coletor de leitura desse lote foi publicado; wrappers/comandos/associações/arquivos binários e auditoria PHP completa de filhos/processos permanecem pendentes. Abas de consulta não equivalem à conversão completa. Nenhuma sequência ou escrita de negócio nesta etapa.

Decisões mantidas: distribuição pela **empresa do próprio container**; custos, coleta, versões e fechamento separados/reutilizáveis, sem fórmulas duplicadas; include de parâmetros sem cache; exclusão bloqueia pagamentos/custos vinculados. Core ProcessEngine integrado após autorização específica, sem execução fiscal. Sem commit/push/schema/reseed/reassinatura. Preservar diffs e registros anteriores. Estado operacional detalhado e próxima ação estão no checkpoint.



## 16/10/2026 — Painel do Container: leitura e reutilização

Consulta ciw.containers/op1000 versão1.0.2 possui Painel no início da linha. Painel ciw.container-painel/op1001 versão1.0.1 tem sete abas nativas: Dados, Itens, Despesas, Anexos (metadados), Versões Custo, Pagamentos e Notas Fiscais. Filhasop1002–1007; pagamentos/notas na1.0.1. Fontes motor_progress_container_painel e motor_progress_container_financeiro; somente read/get, contexto obrigatório validado PHP/ABL. Publicação pelo Builder/governança, sem alteração assinada/reseed. API containers-painel347checks/cultura12.2 e46PASOE; financeiro139+política10/cultura e25PASOE, PHPsete telas e UI332526/2pagtos/1NF comprovados. Estado/evidências: D:/opencode/conversao-sc/docs/integracao-painel-container-validacao.md.

Determinação explícita: cálculo de custo e versões de container são rotinas separadas reutilizáveis; não duplicar fórmulas/regras nos programas. Coletor central+Calculator puro, adapter de snapshot e VersionService separado; fechamento unitário tem seu próprio núcleo compartilhado por simulação/writer. Include parâmetros Progress consulta im-param diretamente NO-LOCK, sem cache. Usuário confirmou bloquear exclusão enquanto houver pagamentos ou custos vinculados.

Core ProcessEngine/Builder para API customPOST foi integrado após autorização específica dos10arquivos, duplooptin, permissão própria/contexto e confirmação Kendo, sem executar ao abrir. PHP414/2192 após contrato fechado de auditoria containers, mais11smokes. Essa integração de código não habilitou metadados/comandos fiscais. Serviços fiscais/cabeçalho/despesas continuam preparados e compilados12.2 com testes TT, sem escrita operacional ou manutenção habilitada. Anexos binários, versões e demais processos permanecem em integração; não considerar painel completo.


## IOL inicial gerencial publicado em16/10/2026

Tela op1012/iol.inicial-gerencial1.0.0 no construtor local8765, login restrito tasilpar (mesmo IMA). Credencial temporaria somente em D:/opencode/conversao-sc/.runtime/iol-gerencial/acesso-local-tadeu.json; nao copiar valores. Fonte API motor_progress_iol_gerencial, resumo/vendedores live sem cache/materializacao. AnalyticsEngine existente com cartoes declarativos e10destinos legados. Sem frontend paralelo/Kendo/vendor.

ABL compilado OpenEdge12.2 no .42,14checks identidade/coleta +20calculador por cultura, boundary401; publicado sursum-api/med por lote versionado iol-gerencial-20261016-v3, compartilhamento137 via .111. Rota POST /SursumPrograms/iol-inicial-gerencial.20buffers NO-LOCK com restricoes por usuario, hierarquia vazia falha fechada; parametro calend_global direto pela include central sem cache. Loja agregada conserva regra legada; vendedor comum recebe somente indicador individual proprio.

Aceite real PHP/PASOE: resumo1linha,6vendedores para tasilpar, negacoes401/403/405/422; UI desktop/mobile390 sem overflow/erro JS e metas alternaveis. PHP459/2498, DI lint e integridade sem invalidos. Diffs anteriores preservados, sem commits/push/schema/reseed. Relatorio detalhado e limitacoes: D:/opencode/conversao-sc/docs/integracao-iol-inicial-gerencial-validacao.md; manifesto ativo .runtime/iol-gerencial/publish/active-manifest-v3.json nesse workspace. Nao refazer cadastros nem usar compilados antigos.

## IOL: comparacao autenticada concluida em17/10/2026

Atualizacao que substitui a pendencia de autenticacao anterior: login real tasilpar concluido; auditoria antes7804/depois7846 confirmou tasilpar,1execucaoProgress,1projecaoresumo/1vendedores. Original confirmouTASILPAR no menu. Publicacao v4 sursum-api/med permanece ativa;1analytics.query.run em todas12amostras novas.2aquecimentos+12recargas alternadas por painel: mediana PHP2,314s vs novo2,313s (empate pratico, diferenca1,75ms);P95 2,475s/2,730s, originalmenosvariavel. Sem inferir ganho a partir da comparacao anterior localadmin, explicitamente invalidada. Relatorio D:/opencode/conversao-sc/docs/comparacao-iol-leitura-unificada-2026-10-17.md. Evidencias privadas benchmark-browser-grouped-20261017.json/benchmark-summary-grouped-20261017.json/single-execution-verification.json em .runtime/iol-gerencial. PHPfinal510testes/2991assercoes e smokefrontend passaram. Fontes compiladas12.2/.42 e loteiol-gerencial-20261017-v4 publicado; coletor/calculador1vez, compatibilidade recursos antigos. ParametrosIMAsemcache, permissoes/calculos preservados. IdealLoja0/52 continua pendente; estaticos/hospedagem nao alterados. Credenciais nao registradas. Semcommit/push/escritadenegocio.

## IOL seletor12.2/12.8 — publicado e validado em19/10/2026

Pedido/desenho aprovados: preparar AGGREGATE12.8 e alternar por espec.im-param. Registro real criado: cod-param iol_gerencial_engine, val-param12.2, tp-param0, grupo0, sem sobrescrever existentes. Porta10012 confirmada dbprod_IMA_espec. Include parametros_ima.i direta NO-LOCK/semcache em cada request; default ausente12.2, invalido/incompativel503. ClienteHTTP nao seleciona engine. Policy exige runtime>=12.8, DBRESTRICTIONS semAGGREGATE e artefato.r disponivel. Fonte128 separado agrega metas mensal/quinzenal e pending; joins/descontos/calculador/escopo compartilhados. Nao prometer ganho antes medir.

Lote ATIVO agora iol-gerencial-20261019-v5, substitui v4 somente IOL, sursum-api/med.13artefatos,5.r12.2, fontes128preparados SEM.r128.10fontes compilados12.2/.42,14policy+14identity+20calculator+boundary por cultura. Parametro instalado pelo utilitario explicito semrota; fonte/tests sincronizados por hash/backup. Manifesto D:/opencode/conversao-sc/.runtime/iol-engine-20261019/publish/active-manifest-v5.json; backup /tmp/codex-iol-engine-20261019/backup-20261019T122121Z. Runtime segue12.2.17; logsIOL_ENGINE confirmaram12.2, rota v5 e ausencia.r128. Semrestart/frontend/Kendo/auth/permissoes/escritacomercial/commit/push.

PASOE real tasilpar resumo1/vendedores6, recursoslegados e5negativas passaram. HashHTTP antes/depois divergiu; causa especifica nao identificada, nao alegarparidade porhash. Comparacao direta readonly v4-v5-v4 no banco real:3rodadas snapshotsidenticos/legadoestavel. Logs .runtime/iol-engine-20261019/{compile-iol-live,iol-engine-live-comparison}.log. Teste local12.8 emDBisolado:68checks/cultura (14policy,11aggregate,14identity,20calculator,1boundary,3paridades vendedor/gerente/geral,5instalador), guardainstalacao e helpercompile128; fixturesrollback. Evidencia local128full/verification.json NAO provaPASOE128. Reviewerunico semdefeitofuncional confirmado; normalizacaorestricoes reproduziuRED/GREEN. Publicacao/validacao128 permanece futura.

Aposmigrar: compilar IolGerencialAggregate128.p no12.8 contraSCHEMAREAL pelo CompileIolGerencial128.p, publicar.rcompativel/validarPASOEmigrado e somenteentao mudar val-param para12.8. NUNCA publicar.rlocalfixture128 noPASOE122 nem considerar somentealterarparametro suficiente antesimplantar.r128. Reversao de selecao12.2. Guia D:/opencode/conversao-sc/docs/iol-engine-12.8.md (copia motor docs/IOL_ENGINE_SELECTION.md). Usuario destacou estabilidade/licenciamento viaPASOE/auditoria como beneficios; auditoria comprovada, estabilidade/custos naoquantificados pelo benchmark.

Validacao complementar em19/10/2026: o modulo IolGerencialAggregate128.p tambem compilou no OpenEdge12.8 local conectado ao schema REAL de espec (192.168.0.111:10012), sem erros e sem executar agregacoes no banco12.2. Artefato e hashes privados em .runtime/iol-engine-20261019/compiled128-real-schema/verification.json. Esse.r NAO foi publicado no PASOE12.2. Manter a validacao no PASOE migrado e recompilar quando o schema/ambiente da migracao exigir. A selecao ativa permanece12.2.

## Ideal Loja — diagnostico em19/10/2026; sem alteracao de producao

Causa identificada da divergenciaPHP0/API52 anterior: sources/scriptcase/iol/bl_pag_ini_ger/index.php6291-6299 calcula percMetaIdeal, mas cartao6942-6946 ignora variavel e usa width:0% e texto0% fixos. API Calculator73-74 aplica diasuteispassados/total*100 se metaLoja<>0, formula prevista no proprioPHP. Navegador recarregado confirmou TASILPAR no menu e Ideal0%/DOMwidth0%; foto privada .runtime/iol-ideal-20261019/php-ideal-zero.png e browser-verification.json. PHPmostrou9diasrestantes. SSH170/111 expiraram e AtualizarAPIfalhouintegracao;52% e observacao de17/10, NAOmedicaoatual19/10; calendario/percentualAPIatualpendentes. Nao afirmarhashdofonteremotoatualconferido.

Nenhum fontePHP/API/ABL/configuracao/negocio alterado. Patch local de2linhas parausar percMetaIdealFormat e percMetaIdeal apenas no bloco certo preparado em integration-src/iol-pagina-inicial-gerencial/ideal-loja-php-display.patch; gitapply--check--ignore-space-change passou contra copia coletada, NAO aplicado. Corrigir no evento/templateorigemScriptcase para sobrevivergeracao; manterAPIcalculada, nao imitarzeroPHP. Guia D:/opencode/conversao-sc/docs/diagnostico-ideal-loja-2026-10-19.md. IOLv5/param12.2 continuam conforme publicacaoanterior; nenhum novo deploy. Novos scriptsinspect-iol-ideal-php.py/verify-iol-ideal-api.py sao ferramentasRO, nao rotas.


## CIW SQL — publicado em19/10/2026; login humano pendente

Adendo/plano SQL aprovados e executados. Origem efetiva de Coloracoes: ticontrole2
SQLServer sistema18 para thiago.cassimiro, distinta de Progress tcassimiro ativo.
Mantido login ima-user-web. POST /api/ciw/access resolve sessao e vinculo integro,
confirma Progress pela nova /SursumPrograms/ciw-identity e consulta SQL por chamada.
Conta tcassimiro/default e vinculo explicito criados pelo comando governado,
sem senha local/grupos/permissoes/overrides; segunda execucao idempotente.
Nao alterar concessoes Progress/SQL ou contas tasilpar/admin para simular sucesso.

Lote8artefatos compilados12.2/.42 e publicados sursum-api/med, rota mesclada,
backup/hash e refreshAgent IDLE25/26 success; 297checks/cultura (156+82+32+27).
PHP532testes3186assertions+DI PASS,6casosPASOE reais+7decisoesSQLreadonlyPASS.
APIsem auth401. PDO_ODBC no processo, SSH hostkeyverificada170/proprioSQL,
loopback19933 com guardPIDlistener+estado privado antesPDO. Arquivosprivados em
D:/opencode/conversao-sc/.runtime/ciw-authz-sql-20261019/private/: mencionar caminhos,
nunca conteudo; senhashumanasnaopersistidas. Guardian usaWindows; outrasplataformas
falhamfechado ate guard equivalente. Tunnels19910/19933 necessarios no ambiente local.

**Fase1A parcial:** falta loginhumano tcassimiro na UIexistente/default, ALLOWPHP
na mesma sessao e logout401. Nao confundir preflight com loginreal. Nenhumaescrita
comercial/frontend/Kendo/schema/commit/push/reseed ou concessao local de negocio.
Revisao0Critical/1Important ownershipcorrigido RED/GREEN; menoresdecobertura sobre
impersonation/rollback/concorrencia real registrados no guia, semdefeitoconfirmado.
Guia canonico CIW_SQL_AUTHORIZATION.md e relatorio em
D:/opencode/conversao-sc/docs/integracao-ciw-autorizacao-sql-validacao.md.
Manifesto/evidencias: D:/opencode/conversao-sc/.runtime/ciw-authz-sql-20261019/.
Conta corrente fornecedor continua excluido. Preservar demais trabalhos concorrentes.


## CIW: novos cadastros e prioridade do usuário —20/10/2026

Determinação vigente: converter outras aplicações e deixar permissões CIW por último. Substitui o gate histórico de1A; preservar autenticação/controles atuais. Não pedir novamente login tcassimiro para bloquear cadastros. Conta corrente fornecedor do menu Tarefas continua excluído. Regra documentada em integration-src/ciw-inventory/conversion-scope.json.

Publicados pelo Builder no frontend oficial, versão1.0.0: ciw.coloracoes/cd0094; ciw.situacoes-container/cd0095; ciw.tipos-versao-custo/cd0096; ciw.motivos-desconto/cd0097. Quatro formulários com CRUD/lote validado via runtime; consultas correspondentes parcialmente absorvidas, apresentação/filtros/navegação e conferência visual ainda pendentes. Navegador permanece no login humano; não houve substituição da sessão do usuário. Catálogo23telasCIW com definiçãoHTTP200; não equivale a262programas convertidos.

Nova API fechada POST /SursumPrograms/ciw-cadastros: serviço privado compartilhado CiwCadastroCrudService.p, CiwCadastroDefinition.p, wrapper CiwCadastrosCrud.p. Canonicos motor-progress/v2/sursum-api/programs; testes em tests/abl/ciw-cadastros. Metadata e hook de auditoria no construtor-pg, sem frontend paralelo/Kendo/schema. OpenEdge12.2/.42:5fontes sem erro,60checksTT por culturaAMERICAN/EUROPEAN;8checks de definição emUTF8/ISO8859-1. Publicado via111 no compartilhamento137; PASOE sursum-api/med. Erro16063 de JSON reproduzido e corrigido com FIX-CODEPAGE UTF8 antes da atribuição. Refresh oficial somente sessões med ociosas após delta p/r com backup/hash.

CRUD real4recursos: inclusão/lote misto/conflito/rollback posterior/campo extra recusado; apenas novos registros CODEx, todos removidos e dados anteriores preservados. Revisão independente1P2: batchAuditSnapshots aceitava sótipos_anexo e descartava snapshotsdosnovosrecursos. RED/GREEN confirmou correção fechada, sem relaxar vínculo entidade/fonte/rota/recurso. Segunda rodada real e consulta RO provaram28eventos/4recursos, estados antes/depois e ator da sessão apesar do header forjado no teste. PHP536testes/3206asserções PASS. Evidências privadas .runtime/ciw-cadastros-20261020/{real-crud-evidence.json,audit-evidence.json,publish/active-manifest.json}; não expor conteúdo de configuração técnica. Documento público D:/opencode/conversao-sc/docs/ciw-cadastros-validacao-2026-10-20.md e inventário atualizado.

Próxima ordem: Despesas×Impostos (PK física3campos, validar referências, não inventar ID), ParâmetrosIMA (PKtextualcod-param, camposlegados; consumo includeparametros_ima.i diretoNOLOCKsemcache), resolver origem de TiposValor/Empresas×Imposto; conferir TiposAnexo/logs/navegação e seguir fases do inventário. Duas tabelas tipos_valor/empresas_imposto declaradas nos formulários NÃO encontradas no dicionário espec/.111. Perfilprincipal especw usaDSNespec_w_pro, mesmo espec/111SQLbroker10112; ticontrole2 é auxiliar, não assumir origemSQL por biblioteca replicada. Catálogoticontrole não as encontrou; ODBC legado não conectou. Pergunta assíncrona ao usuário sobre uso dasduastelas pendente; não impede demais cadastros.

Sem novosgrantsdenegócio/alteraçãohumanadecontas/commit/push/reseed; preservados trabalhos concorrentes. Fase1A humana e integração de permissões continuam parciais, agora ao final. Não marcar fase1 inteira concluída nem repetir processos de autenticação já autorizados.


## CIW: Parametros IMA publicado e Despesas x Impostos absorvido —20/10/2026

Usuario reafirmou executar ambos com permissoes por ultimo. Entrega EFETIVA: Parâmetros IMA cd0098/ciw.parametros-ima1.0.0 publicado pelo Builder; Despesas×Impostos mantido pela colecao impostos de cd0093/ciw.empresas-despesa1.0.1 (Despesas→Empresas→Impostos que usam esta despesa na base). Reutiliza DespesasCrudService.p::SyncChildren, chavefisica despesa_id/imposto_id/empresa_id; referencia de imposto e despesas.log_imposto=1. Nao criar tabela impostos, IDartificial ou segundo writer. DiagnosticoRO real12vinculos,0semparent/empresa/base/imposto; todosalcancaveis pelo fluxo. Consulta globallegada e apresentacao/filtros/visualizacao continuam parcialmente absorvidos; nao alegar paridadevisual total.

Novo CiwParametrosService.p e delta CiwCadastrosCrud.p publicados p/r12.2 no137 via111, PASOE sursum-api/med, mesma rota/configtoken existente; manifesto/backup/hash em .runtime/ciw-keyed-20261020/active-manifest.json. Refresh oficial somente sessoesmedociosas. Campo codigo e aliasUI de PKtextualcod-param (readonlyOnEdit existente); row_stamp oculto SHA256estadofisicocompleto rejeita estadoobsoleto, grupoagrup_im_param_id preservado. Camposlegadosvalor/tipo1..5 (0legado) mantidos. ConsumopelasAPIs segueincludeparametros_ima.i diretaNOLOCKsemcache. Nao confundir editor administrativo com outro mecanismo de consumo.

Backend canonico: RuntimeCiwParametroAudit contrato fechado comIDtextual; pequeno hook emRuntimeApiEntityActionService. RuntimeTransactionService omite val_param/val-param do contexto e dos snapshots antes/depois/diff, inclusiveget, para nao copiar valorespotencialmentesecretos aojournal. Auditoria conservaidentidade/grupo/hash/ator. Novosmetadados B/integrations/ciw-parametros; testesbackend eABL em M/tests/abl/ciw-parametros. Nova sincronizacao porguards .runtime/ciw-keyed-20261020/canonical-manifest.json; syncantigo ciw-cadastros devefalhar porhash se tentado, nao sobrescrever o hooknovo. Preservar trabalhos concorrentes.

Provas: SSHinicialexpirou; usuario informourestabelecimento darede e oficial12.2/.42 concluiu4fontes semerro. Parametros71checks emCADA culturaAMERICAN/EUROPEAN eCPUTF8/ISO8859-1; despesas229checks/cultura comfixturesTTsomente. Diagnostico12.8 NAOpublicado. PHPcanonico542tests/3237assertions. Builderdraftvalidado epublicado; fonteAPI mesclada com5operacoes preservandoheadersmascarados server-side. Catálogo24telasCIWHTTP200, nao262convertidos.

PASOEreal pelobackend: parametroCODExnovo comapostrofo/acento, CRUD/get, conflitohash, chaveimutavel, limite500; novabase/novoimposto e2vinculosemempresasexistentes somentecomo referencia. Isolamentoentreempresas e rollbackconfirmados. TODOSosregistrostestadosCODExnovosremovidos; test-ownership.json vazio. AuditoriaRO confirmou3eventosparam+5vinculos, ator da sessaoapesarheaderforjado, valores ausentesdosjournals/contextos. Antigos4cadastros continuam lendo pelowrapper novo. Revisaoindependente semdefeitoconcreto. Evidencias privadas .runtime/ciw-keyed-20261020/{real-validation.json,impostos-coverage.json,compiled122/,publication-im-param.json}; nao exporconfiguracoes.

Documento publico D:/opencode/conversao-sc/docs/ciw-parametros-impostos-validacao-2026-10-20.md e inventarioatualizado. Conferenciavisualpendente (nao alterar sessao humana paraautoteste). Restantes: TiposValor/Empresas×Imposto precisam origem/schema; consultastiposAnexo/logs/navegacao e demaisfases. PermissoesCIW/humanotcassimiro ficam aoFINAL, semimpedirconversoes. Conta corrente fornecedor continuaexcluido. Semnovasconcessoesdenegocio/contashumanas/commit/push/reseed/schema/Kendo.


## CIW: consultas de fornecedores publicadas —20/10/2026

Continuidade autorizada, permissoes aoFINAL e Conta corrente fornecedor excluido. Duas consultas publicadas Builder1.0.0: ciw.fornecedores/cd0099 e ciw.fornecedores-estrangeiros/cd0100. Origem confirmada comum/comum_pro=ems2cad/.111, nao ticontrole2auxiliar. Predicadoslegados independentes identific=2 vs cod-gr-forn=2; estrangeiro NAOacrescentaidentific. Projecao codigo/nomeabrev/nome; estrangeirodataimplantISO/null. CiwFornecedoresService.p compartilhado, ramosfixos wrapperCiwCadastrosCrud.p, mesmarota/configtoken. Somenteread/getNOLOCK, semwriter/tabelabancolivre. ID/aliascod_emitente doBuilder validados eigualdadeobrigatoria. Filtros/ordenacao/paginacaolocais frontendJSONexistente, semKendo/PHPnovo; APIcarrega subconjuntointeiro semtruncamento.

Oficial12.2/.42:3fontes semerro,27checks/culturaAMERICAN/EUROPEAN. ServicoausenteRED->24GREEN; integracaoencontroualiasadicionalBuildernoget, REDreproduzido->27GREEN, recompilado/republicadofinal. Diagnostico12.8nuncapublicado.4artefatosp/r137via111, guardahashbaseline/backup, rotas/configpreservadas; recargaOEJMXsomentesessoesmedociosas. CanonicosM/sursum-api/programs servico/wrapper e tests/abl/ciw-fornecedores; B/integrations/ciw-fornecedores metadados. Syncantigosdewrapperagoradevemfalharporhash; nao sobrescrever este ramo com candidatosanteriores.

PASOEreal sursum-api/med+backend:4376fornecedores/140estrangeiros, contagem/somacodigos/primeiralinhaexata conferidas contra diagnostico12.2DBNOLOCKindependente; projecao/unicidade/ordem/dataISOtodaslinhas. Detalhe/filtrocodigo/totalfiltrado/segundapagina, recusaescrita/campoSQL/tokenausente passaram. Leiturasanteriores4cadastros+ParametrosIMA passaram. NENHUMAescritade negocio/fixtureoperacional; semnecessidadelimpeza. Revisaoindependentesembloqueador; visualpendente e sessaohumanapreservada. Catalogo26telasCIWdefinicaoHTTP200, NAO262convertidos.

Evidenciasprivadas D:/opencode/conversao-sc/.runtime/ciw-fornecedores-20261020/{compiled122/,active-manifest.json,real-validation.json,coverage.json,canonical-manifest.json,progress.md}; active-manifest-v1historicosupersedido. Docpublico docs/ciw-fornecedores-validacao-2026-10-20.md; inventarioatualizado. Fonteslegadas10apps/27arquivoscoletadosemsources/scriptcase/phase2,manifestosanitizado; naoexecutarPHPgeradoparaanalisar. Restantesfase1TiposValor/EmpresasxImpostosemorigem/schema, log/navegacao/equivalenciavisual; fase2itens/representantes/titulos/notas/cambio continuam. ODBCespec_w_proaindanaoconectou,categoriasanitizadaunclassified,naoconfundircom tabelainexistente. Nenhumgrantdenegocio/contahumana/commit/push/schema/reseed.


## CIW: Itens ERP e Representantes ativos publicados — 20/10/2026

Pedido: converter os próximos programas, mantendo permissões CIW por último e Conta corrente fornecedor excluído. Publicadas pelo Builder oficial1.0.0: ciw.itens-erp/cd0101 (cons_itens) e ciw.representantes-ativos/cd0102 (cons_representante_ativos). Perfis confirmados: ems2med→med_pro→ems2med/.111 e ems5→ems5_pro→ems5/.111. Itens preserva ge-codigo50..60 inclusive, it-codigo/desc-item como it_codigo/descricao, sem regras adicionais da BO de saldos. Representantes preserva dat_desligto desconhecida e DISTINCT cdn_repres/nom_pessoa/nom_abrev.

Diagnóstico encontrou876linhas ativas com438repetições de código. cdn_repres não serve como PK única da consulta. Serviço elimina duplicatas da projeção completa e expõe representante_key SHA256 do JSONUTF8 compacto, oculta e somente de transporte. Mesmo código com nomes diferentes permanece distinto; null preservado. Hash opaco inclui escape de solidus do writer OpenEdge; cliente não deve reconstruí-lo. Nome alterado muda a identidade de consulta. Itens mantém PK textual original. Aliases Builder precisam ser iguais; QUOTER protege literal textual.

Novo CiwReferenciasService.p e dois ramos fixos em CiwCadastrosCrud.p, mesma rota/configtoken existente. Somente read/getNOLOCK, sem writer/SQL/tabela/DBlivre. Frontend oficial por metadados, sem Kendo/PHPnovo. Filtros/ordenação/paginação nativos; códigos textuais numéricos podem ordenar diferentemente do BY ABL, sem afirmar equivalência dos comparadores. API lê todo o subconjunto sem truncamento. Canônicos M/sursum-api/programs, teste M/tests/abl/ciw-referencias; B/integrations/ciw-referencias metadados. Sync guardado por baseline/hash; scripts antigos do wrapper devem recusar sobrescrita.

Oficial12.2/.42:3fontes sem erro,48checks em CADA cultura AMERICAN/EUROPEAN eCPUTF8/ISO8859-1, incluindo goldenhashUTF8 calculado independentemente com acento, DISTINCT, mesmo código/nomes distintos, limites49/50/60/61, apóstrofo/injeção, null e negativas. TT somente; diagnóstico12.8 não publicado. Quatro p/r publicados no137via111 por guard/hash/backup, sem rota/config alterada; recarga OEJMX restrita às sessões med ociosas de sursum-api. Primeiro preflight ocorreu antes da recarga concluir e recusou; aguardado recibo, repetição passou sem republicação.

PASOEreal sursum-api/med+backend:4373itens e438representantes DISTINCT de876linhas físicas; contagens/agregações/primeira imagem exata vs diagnóstico12.2DBNOLOCK independente. Projeção, identidades únicas, hashesUTF8 todas438linhas, detalhe/filtros/totalfiltrado/paginação/aliases divergentes/campoSQL/recusaescrita/token obrigatório passaram. Leituras dos7recursos anteriores passaram. Nenhuma escrita de negócio nem fixture operacional, nada para limpar. Revisão independente sem bloqueadores. Menor adiado/documentado: importador --validate-only registra operações da fonte compartilhada antes de validar drafts, sem publicar telas. Visual pendente e sessão humana preservada.

Evidências privadas .runtime/ciw-referencias-20261020/{compiled122/,active-manifest.json,real-validation.json,referencias-coverage.json,canonical-manifest.json,progress.md}; doc público docs/ciw-itens-representantes-validacao-2026-10-20.md. Inventário atualizado, catálogo28telasCIW com definiçãoHTTP200; não confundir com262aplicações convertidas. Restantes: cotações/câmbio, títulos/notas, logs, equivalências visuais/navegação e TiposValor/Empresas×Imposto sem origem/schema confirmado. Permissões CIW continuam ao final. Sem concessões de negócio/contas humanas/schema/commit/push/reseed.


## CIW: menu da Home organizado — 20/10/2026

Pedido explícito: organizar as aplicações já convertidas no módulo CIW, permissões por último e Conta corrente fornecedor fora. PUBLICADO no banco local, ScreenDefinition home/id59: módulo visual ciw/CIW, grupos Cadastros e parâmetros(8), Consultas ERP(4), Containers(1), total13links diretos. Cadastros: Empresas,Despesas,Colorações,TiposAnexo,SituaçõesContainer,TiposVersãoCusto,MotivosDesconto,ParâmetrosIMA. Consultas:Fornecedores,FornecedoresEstrangeiros,ItensERP,RepresentantesAtivos. Containers:consulta de Containers. IDsHome ciw-<sufixo>,code mantém cd/op canônico, moduleId visualciw; não alterou módulos técnicos/numeração Builder.

15telas restantes do catálogo exigem contexto e permanecem no fluxo: Despesas→Empresas por despesa; Containers→Painel/14abas. Nenhum atalho global sem despesa_id/container_id nem execução de processo. Não interpretar organização do menu como conclusão funcional de todas as28telas/262aplicações. Conta corrente fornecedor NÃO incluído. LinksHome copiam referência read de cada tela; sem alteração em contas/grupos/grants/permissões. Visibilidade segue acessos atuais, integração CIW aoFINAL.

Demais módulos/grupos/programas e estado inicial da Home preservados integralmente. Aplicação somente metadataHome por ORM, transação com bloqueio, baselineSHA/backup e serviço nativo StructuralIntegrityService::signScreen, integridade antes/depois, sem desabilitar controles. Snapshot de permissões/vínculos/grants inalterado. Sem schema/reseed/commit/push/ABL/frontend/Kendo/demo. Config em integration-src/ciw-menu/home-menu.json; canônico construtor-pg/integrations/ciw-menu. Não usar reseed para aplicar esse menu; publicador recusa reaplicação e baseline concorrente.

Provas privadas .runtime/ciw-menu-20261020/{home-before.json,baseline.json,home-candidate.json,publication.json,runtime-validation.json}; tools/construtor-local/{inspect-ciw-home.php,publish-ciw-home.php,verify-ciw-home.cjs}. API:HomeHTTP200,3grupos/13links e13definições destinoHTTP200, referências read, exclusão e linkscontextuais/14abas preservados. Nenhum endpoint negócio; sessão técnica própria encerrada, humana preservada. Revisão independente sem achado concreto; conferência visual humana pendente. Doc docs/ciw-menu-2026-10-20.md. Atualizar production/home.html?screenId=home e selecionar CIW.


## LDAP solicitado para tadeu.parreiras — preparação20/10/2026, ativação pendente

Usuário pediu habilitar LDAP e confirmou conta tadeu.parreiras (não perguntar nome novamente; não reutilizar credenciaisSSH). Estado prevalente: LDAP NÃO habilitado efetivamente no banco, conta ainda não criada/vinculada. Providerldap continua disabled/configvazia; local eima-user-web ativos e preservados. Nenhuma tentativa de senhaLDAP/alteração de permissões.

Confirmado por rede/RootDSEanônimo Windows:principal192.168.0.61,IMA-SRV-AD1.ima.com.br,baseDN DC=ima,DC=com,DC=br,domínioIMA. TCP389/636respondem. CertLDAPS CN=IMA-SRV-AD1.ima.com.br expirou30/07/2019 (início30/07/2018), cadeia PartialChain+NotTimeValid. Inspeção não aceitou certificado inválido nem desabilitou TLS. DNSdoADanunciaAD2/192.168.0.62,semrespostaLDAPS636nesta verificação. DNSlocalpadrão não resolve nomeinterno;consultaautoritatativaao61confirmoucontroladores. Necessário certificado válido/cadeiaCAconfiável eDNScorreto ououtrocontrolador válido; pergunta ao usuário pendente.

PHP8.4.3 contém DLLLDAP ecarrega com-dextension=ldap. LauncherC/tools/construtor-local/start.ps1 modificado para detectarecarregarLDAP somenteprocessobackend,sujeitoaprobedisponibilidade,semalterarphp.ini global. Backendlocal8000 próprio reiniciado comguardPID/caminho/porta;frontend/sessõespersistidaspreservados. Flagdo novoprocessoconferida,health e/api/auth/providers pass;logintécnicolocal+13linksCIWverificados. Não confundirCLIsem-d(extensionfalse) combackendagoracarregado. NãohouveABL/PASOE/schema/seed/commit/push.

Candidato inativo semsegredos integration-src/ciw-ldap/provider-candidate.json usaLDAPS/sAMAccountName/padrãoIMA\{username}/baseDNconfirmado,APENASPREPARADO,nãoaplicado. Evidênciasprivadas .runtime/ciw-ldap-20261020/readiness.json;doc docs/ciw-ldap-preparacao-2026-10-20.md. Apóscertificado/DNS,cadastrar/vincularsomente tadeu.parreiras conformejáautorizado evalidarloginhumanosemsenhanachat,semgrantsCIWartificiais/nemalteraroutrascontas. PermissõesCIWaoFINAL,Contacorrentefornecedorexcluído.


## LDAP389 ATIVADO por autorização explícita —20/10/2026

Estado PREVALENTE sobre diagnóstico anterior bloqueado porLDAPS: usuário confirmou SIM para LDAPporta389SEM TLS depois do esclarecimento de que senha trafega semcriptografia. Host192.168.0.61,baseDNDC=ima,DC=com,DC=br,domínioIMA,sAMAccountName,padrãoIMA\{username}. CertLDAPSexpirado nãoimpedeessemodoautorizado; nenhuma verificacaoTLSfoidesabilitada,conexãoéLDAPpura. ProviderldapHABILITADO eassinado peloStructuralIntegrityService nativo.

Criada SOMENTE tadeu.parreiras/default com authSourceldap,statusactive,passwordHashnull,forcePasswordChangefalse,groups/permissionsvazios. VínculoPrincipal/defaultsemoverrides,assinado. Usuáriojáautorizouessaconta, não perguntarnomeoupermissãoparaomesmomodo. Semsenhalocal/credencialsalva/reutilizaçãoSSH/tentativafalsaAD. Outrosusuários/provedores/vínculos/grantsidpreservadosporhash;local eima-user-web continuamativos. PermissõesCIWaoFINAL,nãoforamconcedidasparaessaconta.

Aplicado tools/construtor-local/enable-ciw-ldap.php emtransação,guardprovedorantesdisabled/vazioecontaausente,backup+recibo .runtime/ciw-ldap-20261020/{activation-before.json,activation.json,readiness.json}. Scriptrecusareaplicação/substituiçãodeconta. Configsemsegredos integration-src/ciw-ldap/provider-active.json EFETIVA;provider-candidate.jsonantigoLDAPSéHISTÓRICO. PHPbackendjátemextensionldapvia-dsomenteprocesso;semphp.iniglobal. RootDSEPHP389anônimoPASS,APIlogincomsenhavaziaconfirmouresolveLDAPe422antesbind;loginlocaltécnico+HomeCIW13linksPASS. LoginPOSITIVOhumanoPENDENTE:perguntaassíncrona enviada paraentrarcom tadeu.parreiras/senhaderede emproduction/login.html,sem enviar senha. Nãodeclararbindpositivoconcluídoantesdessaevidência.

Doc docs/ciw-ldap-preparacao-2026-10-20.md agoraabrecomestadoativo,diagnósticoanteriormarcadohistórico. NenhumABL/PASOE/schema/reseed/commit/push/frontend/Kendo,contaspreexistentesougrantsnegócioalterados;Contacorrentefornecedorcontinuaexcluído.

Revisão independente somenteleitura daativação/launcher semachadobloqueador. Configcanônica emconstrutor-pg/integrations/ciw-ldap/provider-active.json,hashconferido.


## Login LDAP positivo e Home corrigida — 20/10/2026

Login humano tadeu.parreiras confirmado: sessão244/default, provedor e tipo ldap, permissões[]. Nenhuma senha/token consultado ou persistido. Falha da Home tinha duas causas: validador recusava programs/grupos vazios após filtro e tratava currentUser.forcePasswordChange(boolean) como segredo. Corrigidos HomeDefinitionValidator.js e HomeEngine.js: arrays vazios aceitos, indicador booleano permitido SOMENTE nos caminhos currentUser/user, mensagem neutra quando não há programas sem abrir initialProgramId obsoleto. Campos de senha, indicador textual/aninhado e referências inválidas continuam recusados. Sem concessões de acesso.

Teste puro12/12PASS com RED anterior; revisão independente final sem achados. Navegador da própria sessão humana validado: Home carregada, data-render-state=ready e mensagem de ausência de aplicações. Menu técnico regressãoPASS:3grupos/13links/13definiçõesHTTP200, navegação contextual e14abas preservadas; não equivale a liberar esses links para tadeu. Conta corrente fornecedor fora; permissõesCIW seguem por último.

Fontes staging integration-src/home-empty; canônicos construtor-pg/src/home-engine e tests/frontend/home-empty-access.cjs. Aplicação guardada por hashes e backup; recibo final .runtime/home-empty-20261020/canonical-manifest-v2.json. Screenshot home-ldap-validada.png nesse diretório. Sem alteração backend/ABL/PASOE/Kendo/schema/reseed/commit/push. Assinante visual exibiu Empresa Demonstracao por estado existente; sessão autenticada continua default, esse estado visual não foi alterado.


## CIW liberado para tadeu.parreiras por pedido explícito — 20/10/2026

Usuário solicitou liberar acesso ao menu CIW para tadeu.parreiras. Concedidas SOMENTE 28 permissões exatas .read das telas CIW publicadas (13 atalhos diretos e15telas contextuais), no permissionOverrides do vínculo default/Principal da contaLDAP. Não alterar configuração do provedorLDAP nem permissions/grupos do AuthUser; overrides preservam liberação no próximo loginLDAP. Não foram concedidas create/update/delete/execute/admin nem curingas. Conta corrente fornecedor continua excluído. A integração geral das permissões CIW continua pendente; esta é liberação explícita de consulta para uma conta.

Aplicado via ORM em transação/bloqueio/guarda baseline e assinatura nativa StructuralIntegrityService::signAuthUserSubscriber. Hash das demais contas/vínculos/provedores/grants de alteração/definições preservado. Preflight nativo antes da aplicação e verificação em processo novo após commit:28canReadScreen, Home filtrada exatamente13programasCIW, ações de escrita/admin recusadas e integridade válida. Nenhum endpoint de negócio chamado, nenhuma sessão/tokenhumano consultado ou alterado. Sessões existentes mantêm snapshot anterior; usuário deve sair e entrar novamente no assinantePrincipal/default para receber a liberação. Validação visual após novo login ainda pendente.

Ferramenta tools/construtor-local/grant-ciw-menu.php (prepare/check/apply/verify); recibos privados .runtime/ciw-menu-access-20261020/{plan.json,applied.json,validation.json}. Apply recusa repetição; não executar novamente. Sem mudanças de frontend/backendprod/ABL/PASOE/schema/reseed/commit/push.


## CIW — menu validado na sessãoLDAP de Tadeu após correção do login —20/10/2026

Estado prevalente sobre liberação anterior: novo loginLDAP/sessão246 ainda tinha permissions[] apesar do vínculo28read. Causa: subscriber.enabled=false; AuthService só aplicava overrides se seleção de assinante habilitada. Corrigido AuthService (completeAuthenticatedLogin e remember) por helper compartilhado: seletorhabilitado usa selected.id; seletordesabilitado usa vínculo do próprio tenant/usuário. Preserva merge/negativas e isolamento por tenant+usuário+assinante. Auditoria agregada confirmou nenhum outro vínculo com overrides não vazios; não habilitar seleção de assinantes globalmente. Fontes anteriores já estavam sujas; baseline integral e hash preservam alterações preexistentes.

Testes reais das classes: RED1/6->GREEN6testes75assertivas; suíteAuth completa32/191 e PermissionResolver9/25PASS (41PHP/216assertivas). Revisão independente sem defeitos concretos. Candidato integration-src/ldap-permissions/AuthService.php/teste; canônico backend/src/Auth/AuthService.php e backend/tests/Auth/AuthSubscriberPermissionsTest.php. Backend local lê fonte por requisição; sem reinstalação/reseed/restart global.

Atualizadas SOMENTE permissions e user.permissions dos snapshots das duas sessõesLDAPativas244/246 de tadeu/default para as mesmas28read já autorizadas, por baseline/transação e resolvedorAuthService nativo. Propriedadesdeautenticação/status de todas sessões preservados por digest; nenhuma credencial/token humano lido, nenhum login forjado. Sessões locais históricas não alteradas. Ferramenta refresh-ciw-menu-sessions.php e recibos sessions-before.json/sessions-refreshed.json; guard impede repetição.

Navegador depois revelou segundo problema no menu publicado:13programas/3grupos retornados mas13referências ausentes em Home.permissions, bloqueando HomeDefinitionValidator. Corrigida SOMENTE metadataHome com13entradas permissions[p]=p para leituraCIW; assinatura nativa/backup/guarda, demais definições e concessões preservadas. Sem novo grant. Ferramenta fix-ciw-home-permission-references.php; recibos home-references-before.json/home-references-fixed.json. Baselines antigos que incluíam hash integralHome agora devem recusar por concorrência; não apagar guards nem reaplicar publicadores antigos. verify-ciw-home.cjs agora exige13referências root além de13destinosHTTP200; PASS.

HomeEngine passou a escolher programa inicial presente na lista filtrada (painel antigo indisponível recai em Empresas). TesteRED12/13->GREEN13/13; revisão independente sem achados. Não alterar Kendo/definiçãoHome inicial global. CanônicoJS aplicado com hash/backup; recibo home-initial-applied.json.

VALIDAÇÃO VISUAL REAL CONCLUÍDA na própria sessão humana: Home data-render-state=ready, TadeuParreiras, seletorCIW selecionado,3grupos/13atalhos, Empresas carregada com dadosProgress e somente Filtros/Atualizar na barra. Captura menu-ciw-tadeu-validado.png em.runtime/ciw-menu-access-20261020. Sessão já funciona SEM novo login; correção também cobre logins futuros/remember. Não afirmar consulta de todas telas/execução/escrita: somente definição13destinos e leituraEmpresas observadas. Conta corrente fornecedor excluído;28read apenas, alteração/execução/admin não concedidas. BadgeEmpresaDemonstracaoéestado visualpreexistente; sessão realdefault, não alterado neste pedido. SemABL/PASOE/schema/commit/push.


## Opção Sair restaurada na Home —20/10/2026

Usuário relatou ausência do botãoSair. A ação já existia no menu de usuário da Home, mas era filtrada por permission=user.logout para a contaCIW de consulta. Correção SOMENTE nos metadados publicadosHome: retirar permission do único item id/actionlogout, preservando labelSair/ícone/ação nativa handleLogout. Encerrar a própria sessão não depende de permissão de negócio. Semgrant user.logout/nova ação/frontend/Kendo/ABL/backendauth alterado. Demais itens/menus/definições/contas/vínculos e28read preservados.

Ferramenta tools/construtor-local/fix-home-logout-visibility.php: transação/bloqueio/delta exato, backupHome, assinatura nativa StructuralIntegrityService. Resolvedor nativo com0permissões reproduziu açãooculta antes e visível depois. Integridade e hashes das demais definições/concessões preservados; regressãoCIW13atalhos/13definiçõesHTTP200/14abasPASS. Recibos privados .runtime/home-logout-20261020/{home-before.json,applied.json,validation.json,sair-visivel.png}; aplicação recusa repetição.

Verificado na sessão humana tadeu: Home carregada emDespesas, CIWselecionado, menuTP(canto superior direito) aberto e botãoSairvisível. Não clicadoSair para preservar a sessão humana; comportamento de logout existente reutilizado. Screenshot entregue com menuaberto. Sessão permaneceativa, semcredenciais/tokens consultados. BaselinesHomeanteriores devemrecusar reaplicação depois deste delta.


## Menu Home — largura configurável e automática concluída —20/10/2026

Usuário aprovou desenho: alterar construtor original e ativar nestaHome, base320px, medir texto/opções e teto480px. Implementado no HomeEngine original+CSS+HomeDefinitionValidator+schema. layout.sidebar.width (160..1200; padrão280), autoWidth(boolean, padrãofalse), maxWidth (160..1200, >=width; padrão max(width,480)). Semnovasopções mantém280 e comportamento móvel anterior. Limite naárea principal45%desktop/72%até860px; recolher zera coluna.

Auto mede cópiaDOMinerte/offscreen comCSSreal doTreeView, textoemnegrito/recuos/ícones/botões egruposrecolhidos; remove cópia emfinally. Só conteúdo já autorizado/renderizado, sem novos dados ou acesso. Maiormedida guardada p/módulo durante sessão, evitando encolher ao pesquisar/favoritos. Recalcula árvore/fontes/resize, comrequestAnimationFrame noresize. Reticências+title se título exceder teto. BibliotecaKendo intacta.

Canônicos B/src/home-engine/{HomeEngine,HomeDefinitionValidator}.js,src/styles/home-engine.css,public/metadata/schemas/home-definition-v1.schema.json,tests/frontend/home-sidebar-width.cjs,examples/home.home.json. Arquitetura/padrõesUI/matrizparidadedemoatualizados. Demo e produção usam mesmosJS/CSS/validator; produção continua screenId/metadataautorizada, semmock/examples nofluxo. Skilllocal construtor-pg-demo-production-parity não encontrada após buscas nas skills/workspaces/plugins; checklist concreto docs/paridade-demo-producao.md executado e matrizatualizada, sem instalar skill.

Candidato integration-src/home-sidebar-width; baselinebackup/hash dos8arquivos existentes e novo teste em.runtime/home-sidebar-width-20261020/{baseline.json,before/,canonical-applied.json}. Não sobrescrever com candidatos antigos; fontes originais já tinham alterações anteriores preservadas por hash. Nenhumcommit/push.

HomeDB EFETIVAMENTE CONFIGURADA: sidebar.width320/autoWidthtrue/maxWidth480, assinatura nativa StructuralIntegrityService e transação/bloqueio/deltaexato. Demais metadados e contas/vínculos/permissões preservados. tools/construtor-local/configure-home-sidebar-width.php, recibos home-before.json/home-configured.json; script recusa repetição. Não confundir exemplo preparado com configuração já aplicada.

Validação: TDD13novos (RED1/13->GREEN13/13), regressãoHome13/13 (26testespuros), exemplo JSONcompleto validado peloHomeDefinitionValidatorreal,13destinosCIWHTTP200/navegaçãocontextual/14abasPASS. Revisão independente sem achados. JSONsyntax/schema properties conferidos; bibliotecaPythonjsonschema indisponível, nãoalegar execução desse validadorexterno.

VISUALREAL na sessão humanaTadeu: Home ready/CIW/Despesas carregada; viewport919/áreaprincipal904, menu332px automático e0textos cortados. PesquisaEmpresas mantém332 com1resultado, limpar restaura13links. Recolher0px eexpandirfuncionam. Viewporttemporário400/áreaprincipal385 →menu277px (floor72%385), depois reset para viewport original e332px/0cortes. Semcópiasinerteremanescentes. Captura menu-largura-automatica.png. Estadofinal pesquisa vazia/menuexpandido/CIW e viewportnormal restaurado. Sempermissõesnovas/ABL/PASOE/schemaDB/frontendparalelo/Kendoalterado. Contacorrentefornecedorfora.


## Despesas — Alterar/Excluir liberados; imagem Empresas aguardando desenho —20/10/2026

Usuário relatou falta de Alterar/Excluir e pediu imagem em vez de texto na colunaEmpresas. Primeiro item CONCLUÍDO: conta tadeu.parreiras/default recebeu somente despesas_progress.edit e despesas_progress.delete adicionais às28read. Não confundir alteração com .update: contrato publicado usa permissions.edit=despesas_progress.edit. Tentativa inicial liberou .update (não usada) e .delete; conferência visual revelou falta deAlterar. CorreçãoFINAL removeu .update e adicionou .edit no vínculo e nos snapshots. Comparações do resolvedor nativo exigem ordenar mapas (ksort); transações com comparação divergente foram revertidas sem efeitosparciais. Estadofinal tem30permissões exatas; sem.create/admin/curingas.

Vínculoassinado StructuralIntegrityService, snapshots somente sessõesLDAPativas244/246; demais usuários/vínculos/definições/propriedadesauth/status preservados. Nenhuma despesa de negócio alterada/excluída, nemendpoint de escrita chamado. Sessãohumana atualizada semnovo login. Verificado VISUALMENTE emDespesas:menu⋮ da primeiralinha oferece Visualizar,Alterar,Excluir; não clicar emExcluir/Salvar emregistroexistente paratestar. Captura alterar-excluir-visiveis.png. Regressão13atalhos/13definiçõesCIW/14abasPASS.

Ferramenta tools/construtor-local/enable-despesas-edit-delete.php, recibo original.runtime/despesas-actions-20261020/applied.json éhistórico e supersedido pelo estadoFINAL edit-correction/applied.json. Correctorprivado correct-edit-permission.php jáexecutado; guardsnão devemserapagados, NÃOrepetir. Backupsbefore.json nos respectivosdiretórios. Scriptsantigos que exigem exatamente28read devemrecusar porbaselinealterado.

Segundoitem PENDENTE: CrudKendoGridRenderer.buildRowLinkHtml sóaceitatexto e ProgramBuilderService.normalizeRowLinks/CrudDefinitionValidator/schema fechamchaves id,label,screenId,parameters,position. Necessário acrescentarrecurso reutilizável no construtororiginal (ícone/íconeapenas), preservando cabeçalhoEmpresas, aria-label/title,screenIdciw.empresas-despesa econtextdespesa_id. Íconenativo disponível k-i-buildings-outline (não alterarKendo). Usuário recebeu pergunta assíncrona para aprovar esse desenho pontual conforme skillbrainstorming, aindaSEMresposta. Nenhuma implementação doícone iniciada. Apósaprovação: TDD, frontend/backendnormalização/schema/metadadosBuilderpublicados efontescanônicas, validar navegação/legado/escritasnegadasoutros, revisão eUI.


## Empresas em Despesas — ícone publicado e navegação validada —20/10/2026

Usuário APROVOU desenho do ícone de prédios, mantendo cabeçalhoEmpresas/descrição/destino/contexto; não pedir nova aprovação. Implementado recurso reutilizável no construtororiginal: rowLinks.icon(nomefechado ASCIIletra+letras/números/hífen até80) e iconOnlyboolean. iconOnlytrue exigeicon. Frontend/Builder/schema rejeitam HTML/classeslivres/URL/nãobooleano; renderer tambémrecusa defensivamente. Labelescapado emtitle/aria-label; célulaiconOnly não repete texto, cabeçalho permaneceEmpresas. Linksantigos mantêmtexto/contexto, opcional íconecomtexto. ColunaiconOnly100px para cabeçalholegível; textual130.

Renderizador usa API NATIVA kendo.ui.icon({icon,type:svg}), comspanaria-hidden. Tentativainicialclassefont rendeu vazio no tema material-ima; corrigida apósconferênciareal. Não carregarfont/iconsCSSadicional nem modificarKendo. Recurso valegrid/cartõesmóveis pelo mesmo buildRowLinkHtml; cartão nãofoivalidadovisualmente.

CanônicosB/src/crud-engine/{CrudKendoGridRenderer,CrudDefinitionValidator}.js,src/styles/crud-engine.css,backend/src/Builder/ProgramBuilderService.php,public/metadata/schemas/crud-definition-v1.schema.json,tests/frontend/row-link-icons.cjs,backend/tests/Builder/RowLinkIconsTest.php,examples/row-link-icons.fragment.json. Arquitetura/padrõesUI/paridadedemoatualizados; checklist da matrizexecutado (skilllocaldeparidadeausente járegistrada). FonteC integration-src/despesas/despesas/builder-draft.json e canônicoB/integrations/ciw-despesas/builder-draft.json alinhados1.0.2/iconbuildings-outline/iconOnlytrue. Não usar fonteantiga1.0.1para republicar.

Despesas EFETIVAMENTE PUBLICADA pelo Builderoficial: cd0092/ciw.despesas1.0.2, versionId50,approvalId49; rascunho/testbundle/aprovação/publicação, locks própriosliberados, grantgovernançatécnicorevogado e sessãoadminprópriaencerrada. Ferramenta tools/construtor-local/publish-despesas-icons.cjs, .runtime/row-link-icons-20261020/{preview.json,tests.log,published.json}. Guard recusa republicação. Semalteraçãoentidade/fonteAPI/ABL/rotaPASOE/dadosnegócio.

Preflight comparaCRUDnovo comgeneratedDefinition da versãoanterior: somente rowLinksícone e defaultgroupable=false antesausente; campos/formulário/contexto/contratos preservados. NÃOcompararcruamente previewvsruntime: runtime injeta logs epreferências; isso causou recusa preventivaanteriorantesdemutar. Logscontinuamdecorados nativamente peloScreenDefinitionService, sem removê-los.

Testes TDDfrontendRED5/9→9/9 e depois SVGRED8/10→GREEN10/10; backend novo5/5 e suíteBuilder135testes/509assertivasPASS; revisãoindependentesemachados inicialefinalSVG. Regressãomenu13definiçõesHTTP200/navegação/14abasPASS. Canonical-applied-final.json contémhashes12arquivos; backupsbefore/e before-svg preservamfontespreexistentes. Semcommit/push/schemaDB/reseed.

VISUALREAL confirmado emsessãohumanaTadeu: Despesasv1.0.2, Home ready;20linksda páginacomSVG/pathreal, texto vazio,title/ariaEmpresas econtext__despesa_idcorretoporlinha. Cliqueprimeiroícone abriu produçãoappciw.empresas-despesa&context__despesa_id=1, telaEmpresasporDespesa1.0.1 comdados lidos econtextopreservado. VoltaaoCIWDespesas, semwrite de negócio. Alterar/ExcluirdeDespesas continuamliberados; totalpermissõeshumanas30,semnovosgrantsnesteícone. Conta corrente fornecedor fora. Screenshot empresas-icone-validado.png nosrecibosprivados.


## Despesas/Empresas por despesa — inclusão e manutenção liberadas —20/10/2026

Pedido explícito do usuário: liberarIncluir no cadastroDespesas e inclusão/alteração/exclusão emEmpresas×Despesas para a conta emuso tadeu.parreiras/default. CONCLUÍDO: quatro permissões exatas adicionais despesas_progress.create, empresas_despesa_progress.create/.edit/.delete. Mantidas28read e despesas_progress.edit/.delete; total34. Semadmin/curingas/outroscadastros liberados. Permissões extraídas dos contratospublicados/assinados dasduastelas; .edit énomecorreto, não .update. Nenhumaalteração nasdefinições/frontend/ABL/fontesAPI/rotas.

Ferramenta tools/construtor-local/enable-despesas-empresas-actions.php, comprepare sóleitura eapplyguardado, transação/lock/vínculoassinado StructuralIntegrityService/backup eguardsdeoutrosusuários/vínculos/telas/propriedadesauth/status. AuthService nativo resolveumapafinalcom34 antesdeatualizar somentepermissions euser.permissions das sessõesLDAPativas. Foram atualizadas3sessões; verificação emprocessonovo confirmouassinatura/mapapersistido eigualdadedossnapshots. Não ler/reutilizartokenshumanos nemreaplicarscript:applied.jsonimpederepetição. Baselinesantigosde28/30devemrecusarcomestadocorrente.

Recibosprivados .runtime/despesas-empresas-access-20261020/{before.json,applied.json,verify.php,validation.json}. Dadosnegócio NÃOincluídos/alterados/excluídos. Nemendpoint dewrite chamado. Sóliberação depermissões; não alegarnovotesteCRUDdeescrita. ContratosdeCRUDjápublicadospreservados.

VISUALna sessãorealTadeu: Despesas1.0.2combotãoIncluir. CliqueíconeEmpresas daprimeiradespesamanteve context__despesa_id=1 eabriuEmpresasporDespesa1.0.1; botãoIncluirpresente emenu⋮ofereceVisualizar/Alterar/Excluir. Não clicarExcluir ouSalvar paratestarregistrosreais. Capturas despesas-incluir.png eempresas-despesa-acoes.png. RetornadoaHome/Despesas; nãoprecisa novo login. Contacorrentefornecedorseguefora, demaispermissõesCIWsemexpansãogeralsolicitada.

## CIW: ações dos cadastros liberadas; auditoria e empresa na API em diagnóstico — 20/10/2026

Pedido atual: liberar gravação nos demais cadastros convertidos, exibir transações/logs e vincular empresa do construtor ao contexto de execução API. Conta corrente fornecedor continua fora.

CONCLUÍDO somente item1: tadeu.parreiras/default recebeu31 concessões adicionais, extraídas das ações efetivamente publicadas em13telas com gravação; total65 permissões (28read+37write). Não inventar ações ausentes: pagamentos de container sócreate/delete; painel sóedit/delete; demais telas de consulta continuamreadonly e processos não receberam execução. Ferramentas tools/construtor-local/inspect-ciw-access-scope.php e enable-ciw-converted-crud.php. Apply transacional com assinatura nativa, guards, backup, preservação de outros acessos e atualização de3sessõesLDAP. Recibo .runtime/ciw-full-access-20261020/applied.json existe: NÃOreaplicar. Verificação independente verify.php PASS65permissões/assinatura/3snapshots coerentes. Nenhum dado de negócio gravado nem endpointwrite executado. Visual em sessão humana Colorações: Adicionar linha(Incluir), Excluir linha e Salvar lote/Cancelar lote(disabled semedições); screenshot coloracoes-acoes.png. Não alegar testeCRUD de negócio.

PENDENTE item2: mecanismos de transações/logs existem, mas admin.transacoes/admin.logs-transacoes exigem security.userGroups admin. ScreenDefinitionService consulta esse acesso e desabilita controlesLogs/LogsExclusão. Não concederadmin. Precisará desenho aprovado para permissão específica de auditoriaCIW + restrição backend obrigatória por assinante/telasCIW autorizadas, emlist/get/count/export. RuntimeEntityActionService hoje aplica audit_screen_id/operation/record apenas quando recebidos e só emruntime_transaction_log; filtrosUI não são autorização. Não habilitar telas globais sem scope obrigatório. Nenhum código de auditoria alterado nesta tarefa.

PENDENTE item3: empresa selecionada no cabeçalho não é propagada como contexto confiável para RuntimeApiEntityActionService.requestEndpoint; ApiRequestContext trata chaves de registro, não empresa operacional. AuthSubscriber é assinante/isolamento, não ERPcompany; subscriber.enabled=false, badgeEmpresaDemonstracao é legado. ciw.empresas tem empresa_id/cod_emp_ems2/cod_emp_ems5. Não inferir mapeamento por nome, não alterar registroteste1 nem ativar assinantes globalmente. Desenho proposto: vínculo explícito servidor entre empresa e códigosERP, seleção autorizada na sessão, envio nas operações API registradas e registro da empresa efetiva noslogs; separar cadastrosglobais/porEmpresa/vinculadosAoRegistro. Não sobrescrever empresa decontainer. Não assumir códigoempresa troca banco; conexãoIMA precisa preflight. Manter sursum-api/med; ABL futuro exige skillcompilar12.2/publicarPASOE. NenhumcontextoempresaAPI implementado. Skillbrainstorming exige aprovação do desenho antes de implementação nova; próxima etapa apresentar abordagem e obter aceite para documento arquitetural, depois revisãoespec/plano.


## Planos CIW aprovados e auditoria aplicada —21/10/2026
Usuário aprovou ambos osplanos e confirmoualteração noconstrutor ORIGINAL B. Auditoria implementada/aplicada12arquivos, docs/ciw-auditoria-validacao.md; hashes/backups .runtime/ciw-audit-20261021/code-final.json. Não reaplicarcódigo/grant. Vínculotadeu/default agora66permissões (somenteadicionada ciw.audit.read), assinatura+3sessõesLDAPválidas; fullPHP562/3378PASS,9novostestes. CatalogPublishedModuleCatalog lêHomeassinada e referênciasfilhas (28telas), pois Builder.modulecadastros/operacional NÃOé móduloCIW. Policyobrigatórialist/count/get/paiEvento, revalida vínculoassinado viaAuthService helpernovo. PayloadentityCode divergente da configassinada érejeitado; rawORMauditoria exigeadminautenticado eGET/HEAD. Reviewindependente2P1scorrigidosantesgrant,semoutrosbloqueadores. CacheSymfonydevdebugfalse reconstruído,policyInjectedtrue conferido antesgrant; não deixar policynull permitir auditreads.
VISUALsessãohumana auditora standalone admin.transacoes?context__screen_id=ciw.despesas abriulocal; Eventosdatransação17858 mostrou4eventos eautoriaTadeu/consultasemalteração. IABtab5 foi criada/marcada deliverable; prints transacoes-ciw.png/eventos-ciw.png. Homehumanatab3 apósreload falhaporAPIexterna: curl7porta19910sem listener. TCP22 .42/.111timeout confirmadofora sandbox; perguntaassincronaVPNenviada. ControlescadastrosProgressvisualpendentesrede; históricoslocaisfuncionam. Nãonegociowrites/ABL/rotas/schema.
Próximaentrega APROVADA: docs/superpowers/plans/2026-10-21-ciw-empresa-api.md. Ledger .runtime/ciw-api-company-20261021/progress.md, Task1inprogress, ainda nenhum códigoAPIcontextoaplicado. Executarsemnovaaprovação. RestriçãoapenasentidadesAPIemmóduloselegíveis, locaisintactas, seleçãooperacionalindependentetenant. Não confundir aprovação com taskconcluída.



## Empresa API: núcleo aplicado, ativação ainda pendente —21/10/2026
Usuário aprovou execução aqui e alteração no construtor ORIGINAL. Núcleo aplicado em39arquivos B com backups/hashes privados C/.runtime/ciw-api-company-20261021/{core-applied.json,before-core/}; NÃO reaplicar deploy-core.py. Fullcanonical598testes3542assertivasPASS, três Node novosPASS+Home13/13, cacheSymfonydevdebugfalse reconstruído e dependências reais verificadas por verify-canonical.php. Rota /api/runtime/api-companies/{moduleId} registrada, contexto nativo autenticado. Nenhum vínculo/grant/JSONpublicado de empresa foi ativado; 66permissões de tadeu/default seguem anteriores.
Núcleo: registry/validação estrita em metadados assinados, seleção por módulo na sessão com CAS, adaptador central de transporte, contexto imutável nos logs e filtro por empresa, seletor nativo Kendo em Home/standalone/processo e propriedades estruturadas de fontes/entidades. Entidades locais/semoptin mantêm requests; não ativar subscriber.enabled globalmente. Source/entity ausênciaConfig é legado somente após assinatura validada no runtime real.
Revisão independente5achados corrigidos RED→GREEN: corrida durantePOSTselecao, logtentativa semempresa, removersignatureconfig, identidadeendpointquery/headers e formcontextual. Revisor conferiu sembloqueadores nessesachados; novo ApiCompanyAuditClassifier semAuth evita ciclo comTransactions. begin inclui modepending confiável ANTESprimeiropersist para APIoptin; pending nunca visível ao operacional e sósubstituído porcontextoverificado mesmafonte/módulo. Formcontextualaguardaescolha e ignora callbacksantigos porrequestVersion. Container/SQLPG reais PASS (contagens1512/5331 na rodada); CUA componente sintético390px/desktopPASS, não prova empresas reais.
Auditoria entrega anterior concluída: rede restaurada, túnelSSHsession82688 local19910->.111:9910 mesma sursum-api/med ativo; controles Colorações/Despesas/EmpresasDespesa e históricos21/4consultados CUA semwrites. docs/ciw-auditoria-validacao.md substitui bloqueioanterior.
RETOMAR Tarefa6 plano docs/superpowers/plans/2026-10-21-ciw-empresa-api.md semnovaaprovação. Inventário atual29telasAPI27entidades em C/docs/ciw-empresa-api-matriz.md; fonte somenteleitura tools/construtor-local/prepare-ciw-company-bindings.php. Matriz preparada, nenhumABL novo destaentrega. Itens usaems2med.item; fornecedores ems2cad.emitente/representantes ems5 comuns; specglobais shared; containers porprefixo cod-estabel->empresas.cod_emp_ems2; EmpresasDespesa por empresa_id. IMA não inferir pelo nome/nãoMEDfallback. ContainersCustoRead.p já tem conexãoROIMA conhecida broker10030 quando necessário, reutilizar regra/preflight. Implementar fronteira empresarial reutilizável nasAPIsespecíficas, compilar12.2/.42 publicar137 validarPASOEsursum-api/med; sódepoisvínculosgrants/classificação/publicação viaBuilderoficial. Preservar registrosusuario; writesnovosmarcadoslimpar. Ainda falta gestão governada bindings/ativação e contratos externos, não anunciar trocaempresafuncional.
Preservar mudança concorrente CiwCotacaoAudit da conversão de cotações: mergeexato cinco linhasfeito nostageantesaplicar, baseline26FDC... original; corefulljáinclui4testescotações. Cotaçõespresentes no catálogoatual. Nenhumcommit/push/reset/reseed/schema; senhas/token nuncaemmemóriafontes.


## Empresa API: correção prevalente do usuário —21/10/2026
Usuário determinou que o construtor envie a empresa para a API, e a própria API trate tabelas/bancos. Não exigir classificação de tabelas/operações no construtor. Contrato simplificado apiCompanyContext {version:1,moduleId,adapter}: mesmo mapeamento de transporte em todas as chamadas registradas da entidade API, inclusive auxiliares, opções e snapshots. API decide regras e conexões. Tenant autenticado permanece independente; permissões/revisões/assinaturas mantidas. Auditoria prova empresa enviada, não prova sozinha isolamento implementado externamente.
APLICADO no construtor ORIGINAL:11arquivos com backups/hashguard em C/.runtime/ciw-api-company-20261021/{before-simple,simple-applied.json}; NÃO repetir deploy-simple.py. RED3testes→GREEN; suíte canônica601testes3563assertivasPASS, três scripts NodePASS; revisão independente do delta sem achados bloqueadores. Editor/exemplo oferecem único mapeamento; contratos antigos operations preservados até migração explícita. Nenhum vínculo/grant/JSONempresarial ativado, nenhum ABLalterado/publicado, nenhum dado negócio alterado.
Correção substitui exigência anterior de integrar ApiCompanyBoundary.i em todos os serviços. Protótipo e14checks12.2TT anteriores continuam só preparados/históricos. Matriz docs/ciw-empresa-api-matriz.md mantém inventário29telas27entidades; modos históricos não são requisito. Contrato vigente docs/ciw-empresa-api-responsabilidades.md.
Pendente: confirmar parâmetro empresarial/entrada real de cada fonte API antes de ativar envio e vínculos reais, sem mapear EmpresaA/B por inferência. Evidência local: CiwCadastrosCrud.p abre explicitamente ems2med.item; DespesasCrudService.p tem whitelist que não aceita parâmetro extra; JsonProgramRouter apenas encaminha JSON, sem troca automática de banco. Não afirmar que rotas convertidas já implementam seleção só porque transporte pronto. Não reconstruir regras ERP no PHP. Preservar66grants e dados de usuário.


## De/para estoque — implantação parcial, ativação pendente22/10/2026

Usuário aprovou cadastro específico, BO/cliente/API somente (PHPlegado fora) e
segunda seleção APENAS quando etapa1 não encontra candidatos. Todas palavras
originais mapeadas são substituídas juntas uma vez, sem cadeia. Etapas2/3 de
saldo/minimo nunca acionam nova busca. Interface TT/JSON e formulas preservadas.
Regras iniciais previstas CAMBRAYA→CAMBRAIA, CAIMAN→CAYMAN, ELASTANO→ELAST..

Implementado em integration-src/saldo-pv: esbo/normalizarPesquisaEstoque.p,
termosPesquisaEstoque.i compartilhando validacao/gramatica numerica; delta na
boSaldoPV; sursum-api/programs/EstoqueTermosCrudService.p e recursofechado
estoque_termos no CiwCadastrosCrud.p; PHP RuntimeEstoqueTermoAudit +hookrestrito
no backendcanonico. CRUD chave textual normalizada imutável, ativo/destino,
concorrencia SHA256, transacaoraiz e imagens antes/depois; bloqueia numericos
inclusive1E3/2E-2 (achadoP2 reproduzidoRED/corrigidoGREEN). Revisao final sem
outro bloqueador.113BO+62cliente+52JSON+23CRUD em CADA cultura12.2/.42;
PHP660tests4292assertionsPASS sem cachewarning. Metadados preparados
metadata/estoque-termos-builder-draft.json: cd0190/iol.estoque-termos-busca,
entity estoque_termos_busca_progress, fonte existente motor_progress_ciw_cadastros.

SCHEMA REAL APLICADO: espec.estoque_termo_busca, termo_digitadoCHAR chaveunica,
termo_pesquisaCHAR,ativoLOG, area dados. Catalogo nao tinhaequivalentes. Primeira
tentativa semNEWOBJECTS aguardouexclusivo; somente proprioPID5412 comcomando e
creation1792699252892 validados foi encerrado. Guard schema-attempt.json remoto
preservado. Segunda tentativa prodict/load_df.p arquivo,,NEW OBJECTS concluida,
3camposrelidos; semforcecommit,nenhumrestart,nenhumobjetoexistentealterado.
NAO reaplicar schema; guard schema-online-attempt.json. DDLcanonico
motor-progress/v2/schema/espec/estoque_termo_busca.df.

PUBLICADOS18artefatos (9share137+9canonical): helper/include/BO emesbo,
service/wrapperCIW emsursum-api/programs; backups/hashguards,originalboSaldo.p
preservado. A compilacao real dos4p12.2PASS. Nao confundir publicar arquivo com
ativar AVM. PASOE sursum-api/med9911 ainda usa dispatcherantigo: probe get
estoque_termos retornaCIW_CADASTRO_RESOURCE_INVALID. SSHkeyroot@111 recusada.
Solicitada conta/chaveautorizada ou equipe executar recarga oficial SOMENTE
sessoesmedociosassursum-api. Semtentar senhadeoutroservidor,semfallbackinstancia.

PENDENTES OBRIGATORIOS: recarga/confirmar novo recurso; rodar import-screen.cjs
viaBuilderoficial (preflight barra antes de mutar fonte/entidade enquanto API
naoativa), registrar5operacoes e publicar tela; cadastrar3regras viaAPI/PHP com
auditoria; testesreaisdebusca/cadastro/concorrencia/auditoria comregistrosnovos
marcados ecleanup; atualizarhomecomlinkseconveniente sem mudarpermissoes; QAUI;
registrofinal. TelaAINDA NAOpublicada;3regrasAINDA NAOcadastradas; validacaoPASOE
do comportamentonovo AINDApendente. Nao repetir mutacoes/guards concluídos.

Docs docs/estoque-termos-busca.md e estoque-termos-execucao.md. Privado
.runtime/estoque-termos-20261022/: schema-inspect/reference/applied,official,
compiled122,fixture-source-manifest,php-full,publish-receipt,probe e
pending-verification.json. Compilador SSH session38126 e SMBsession45459
encerrados ao parar; credenciais nao persistidas. Prova de113checks reforcada
com candidatoELASTX que nao deve satisfazer pontoELAST.literal.


## De/para estoque CONCLUÍDO22/10/2026 — substitui pendência anterior

Acesso administrativo .111 fornecido nesta conversa, usado só em getpass; nenhuma
credencial persistida. Recarga oficial somente2sessões med IDLE na sursum-api,
mesmoPID3856625, semrestart/instânciaalternativa. Novo recurso estoque_termos ativo.
Tela cd0190/iol.estoque-termos-busca1.0.0 publicada viaBuilder versão60/aprovação59,
fonte **motor_progress_estoque_termos** exclusiva com5operações, MESMAAPI/token.
Motivo: fonteCIWcompartilhada temoperaçõescontextuais deoutrosrecursos eBuilder
exige seuscamposint naentidade; não alterarBuilder ou adicionar camposfalsos.
Foram removidos APENAS5acréscimospróprios dafonteantiga, demaisoperaçõespreservadas.
ClasseRuntimeEstoqueTermoAudit e testes ajustados a essa fonte exclusiva.

Regra confirmada: apenas etapa1vazia → todaspalavrasoriginais corrigidas juntas,
umaúnica segunda seleção. Saldo/minimos posteriores nunca reabrembusca. Semalterar
contratoJSON/TT, cálculos ou PHPlegado. Tabelaespec.estoque_termo_busca criadaonline
anteriormente, NAOreaplicar. Trêsregraslegítimas cadastradaseativas viaPHPadmin:
CAMBRAYA→CAMBRAIA,CAIMAN→CAYMAN,ELASTANO→ELAST.. Semproduto/saldo/WPmutado.

Validação REAL tasilpar:14consultasPASOE; CAMBRAYA vazio semdepara→mesmoitem/saldos
CAMBRAIA; CAIMAN idemCAYMAN. Duas/três palavraspositivas comprovadas com2aliases
temporáriosnovosSC-TESTE→FIO/BRANCO e CAMBRAYA, comparadosaCAMBRAIAFIOBRANCO noitem
175182. Aliasesremovidos porimagemexata eGET404. Não afirmar que CAYMANELAST. tem
produtoreal: nenhum dos3CAYMANconsultados continhaessaabreviação. Fixture12.2
comprovou3regrasaprovadas simultâneas e pontoliteral versusELASTX.

CRUD realPHP:1registroSC-TESTE incluídoinativo, alteradoativo, stampobsoletorecusado,
excluído eGET404. Trêseventosauditados comimagens/autoriaadminconferidosapóscleanup.
LogsdoRegistro/LogsExclusão nativos tambémconferidos comchavetextual. Para isso,
RuntimeEntityActionService ganhou escopotextual EXCLUSIVOiol.estoque-termos-busca,
tenant/tela/identidadeexata mantidos, p_hashcontinua restritopagamentos.2testes
RED→GREEN, backendfinal662tests4295assertionsPASS. Revisãoindependentefinal
nenhumdefeitoconcreto. Oficial12.2 anterior113BO+62cliente+52API+23CRUDpercultura
mantido; ABLnãoalterado após publicação18artefatos. Hashesreconferidos.

UIverificadanavegadorisolado semtrocar sessãodousuário:grade3regras, Ativodefault,
Digitadoimutáveledição; nenhumformuláriosalvo. Homeassinada recebeu1link em
CIW→Cadastros e parâmetros, permissõeshumanas intactas. URLlocal
http://127.0.0.1:8765/production/app.html?screenId=iol.estoque-termos-busca .
TodoCRUDdeteste removido(3registros), apenas3regrasdefinitivaspermanecem.

Evidências privadas .runtime/estoque-termos-20261022/: med-session-refresh.json,
publication-estoque-termos.json,initial-rules.json,crud-real.json,confirmed-audit.json,
native-record-log-scope.json,stock-real-results.json,stock-multiword-verification.json,
multiword-cleanup.json,source-cleanup.json,ui-verification.json/home-link-publication.json,
final-runtime-read.json,php-full-final.log e **final-verification.json**. Scan sem
tokenprivado nosarquivosgerados/fontes. NÃOrepetirschema/publicação/seeds/fixtures:
guardsdefinitivos existem. Contextoanteriorparcialé histórico; pendênciadestaentrega
nenhuma. NovaFonteAPI eLogsdechavetextual são decisõesprevalentessobreplanooriginal.
