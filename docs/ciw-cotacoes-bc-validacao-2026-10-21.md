# Cotações BC — consulta e manutenção manual

Publicado em21/10/2026: **ciw.cotacoes-bc / cd0103 / 1.0.0**, no frontend oficial do construtor. A Home recebeu somente o atalho **CIW → Cadastros e parâmetros → Cotações Banco Central**. As permissões existentes foram preservadas; o novo atalho segue a permissão própria da tela, sem conceder acesso adicional a contas humanas.

Endereço local: `http://127.0.0.1:8765/production/app.html?screenId=ciw.cotacoes-bc`.

Caminhos relativos deste documento referem-se ao workspace `D:/opencode/conversao-sc`.

## Escopo entregue

Consulta `cons_hist_cotacoes_bc` e formulário `form_hist_cotacoes_bc` consolidados no CRUD existente, por JSON do Builder. Oito campos físicos de `espec.hist_cotacoes_bc`: ID, data, série, valor, data/hora da busca, nome da série, indicador de sucesso e indicador manual. O `row_stamp` é uma representação adicional do estado, oculta no formulário, para recusar alterações e exclusões com dados desatualizados.

O valor conserva **cinco casas decimais**; IDs e série são inteiros. Filtros e paginação usam o mecanismo existente; ordenação inicial por data, série e ID descendentes. Nome da série e indicadores são somente leitura. A inclusão exige data ISO, série inteira positiva e valor numérico com até cinco casas; gera ID pela sequência privada do servidor e marca a cotação como manual. Data/hora pode ser informada; sem envio, o servidor usa NOW. Valores antigos desconhecidos continuam legíveis.

Alterar/excluir verifica `log_manual` persistido sob lock, além do hash do estado. Ocultar um botão no Scriptcase não era suficiente para proteger a API; o bloqueio agora ocorre no backend. Os índices físicos coletados são **não únicos**: a API recusa IDs ambíguos, inclusive na listagem, em vez de editar uma linha arbitrária. Não houve alteração de índice ou schema.

Ao incluir/alterar uma cotação cuja série final seja **9999**, o participante `ContainerCostInvalidation.p` marca a versão de custo como desatualizada e atualiza a data/hora dos containers com `situacao=1` e `ordem_busca_dolar_compra` exatamente igual a `1`. Não interpreta `1,2` como a mesma regra. Um teste confirmou que a comparação ABL ignorava espaços finais; o participante também verifica comprimento RAW, mantendo o contrato exato do importador de logs. A exclusão não executa essa invalidação, conforme o evento legado lido.

A cotação e todas as invalidações pertencem à mesma transação raiz. Falha de um participante desfaz tudo; os eventos só são expostos depois do commit. O serviço recusa transação externa para não anunciar confirmação de uma transação ainda pertencente a outro chamador. Não calcula novamente custos, não gera versões e não copia fórmulas: só invalida o estado por uma rotina reutilizável.

## APIs e auditoria

Cinco operações novas mescladas na fonte já registrada **motor_progress_ciw_cadastros**, sem substituir as operações anteriores. Mesma rota POST `/SursumPrograms/ciw-cadastros`, recurso fechado `hist_cotacoes_bc`, operações `read/get/create/update/delete`, entidade `hist_cotacoes_bc_progress`. A autenticação de integração existente permanece no servidor; o navegador não escolhe programa, banco, tabela, sequência ou usuário.

`RuntimeCiwCotacaoAudit.php` valida fonte, rota, operação, chave, definição dos campos e imagens completas da cotação, além dos efeitos permitidos nos containers. O hook usa as classes de transação/log já existentes. A autoria vem da sessão autenticada reconstruída pelo PHP; cabeçalho de usuário forjado no teste não substituiu o ator. Cada invalidação tem imagem anterior/atual dos campos afetados, identidade do container e referência à cotação. Os logs do formulário e **Logs Exclusão** usam os componentes nativos e respeitam os acessos existentes.

O renderer Kendo envia data/hora em UTC. O parser aceita esse formato e converte para o deslocamento efetivo da sessão/SO, pois o campo físico é DATETIME, sem fuso. `SESSION:TIMEZONE=?` significa ausência de override; o fallback usa `TIMEZONE(NOW)`. Testes cobrem tanto esse estado quanto o override explícito. Na integração, a primeira inclusão foi recusada422 antes de gravar porque o caso sem override ainda não estava coberto; a regressão foi reproduzida, corrigida, compilada e publicada. A nova tentativa teve recibo separado; o primeiro guard foi preservado.

## Verificação executada

- OpenEdge **12.2/.42**: quatro fontes compilados sem erro; **81checks em cada combinação** de AMERICAN/EUROPEAN e UTF-8/ISO8859-1, sem bancos conectados. Mais81checks por cultura com o participante somente em `sursum-api/programs/`, comprovando resolução no layout publicado. Testes incluem CRUD, conflitos, ambiguidade, manual/automático, cinco casas, UTC e invalidação/rollback em tabelas temporárias.
- PHP: **566testes / 3413asserções**, incluindo quatro testes/35asserções do contrato fechado e integração do hook com o logger existente. Revisão independente encerrada sem defeitos remanescentes.
- Publicação inicial: três programas `.p/.r` no compartilhamento137 via111, **sursum-api/med**, com baseline, backup e hashes; os deltas UTC e de resolução do participante substituíram somente `CiwCotacoesService.p/.r`. O serviço prioriza o caminho publicado do `.r`; caminhos locais constantes servem aos testes isolados, sem aceitar caminhos do cliente. A prova de layout reproduziu e corrigiu a chamada anterior sem prefixo. O manifesto final confere os seis artefatos. JMX oficial carregou somente sessões med ociosas, sem restart. Configuração/token/rotas anteriores foram preservados.
- Builder publicou a tela oficial; runtime confirmou definição, formulário, precisão, inteiros, read/get, filtro e paginação. Home assinada contém um único link novo; outros menus e permissões permaneceram intactos. Catálogo real: **29telas CIW com definição HTTP200**, sem equivaler a262aplicações integralmente convertidas.
- Teste real somente em cotação nova identificada pela série sintética **2147000103**, data **2099-10-21**. Inclusão com valor1,23456; alteração para2,34567; hash obsoleto e precisão excessiva recusados; exclusão e GET404 confirmados. **Transações21716/21717/21721**, ator **admin**, com imagens anteriores/atuais. A consulta nativa de Logs Exclusão retornou o evento removido.
- O registro novo foi removido; a série de teste terminou vazia e o journal de propriedade ficou sem registro. Nenhuma cotação existente ou container de negócio foi alterado. As verificações finais de logs e limpeza foram somente leitura; não repetir o CRUD para refazer uma conferência de logs.

## Limites e continuidade

O botão legado **Buscar cotação** chama `bl_atu_dolar_dia`, que consulta o BC e grava histórico. Depois da entrega manual, o usuário determinou implementar o cliente PHP; o processo foi publicado em21/10/2026 como `ciw.cotacoes-bc-buscar/cd0104/1.0.0`, com acesso pelo cadastro agora1.0.1. Ver [cliente BC e validação](ciw-banco-central-client-2026-10-21.md), incluindo os limites dos testes de persistência automática. Os métodos de sincronização EMS2/EMS5 replicados no arquivo não demonstram chamada ativa pelo evento analisado. Abrir a consulta ou o processo não executa atualização automática.

Invalidação da série9999 e rollback foram exercitados somente em TTs; nenhum teste real invalidou containers existentes. Conferência visual com a sessão humana, agrupamentos de apresentação legados e integração adicional de permissões continuam pendentes. O formulário manual está publicado e validado; a consulta foi absorvida no cadastro, sem alegar equivalência visual integral.

Fontes da conversão: `integration-src/ciw-cotacoes/`; fontes canônicos: `D:/opencode/motor-progress/v2/sursum-api/programs/`; teste ABL em `tests/abl/ciw-cotacoes/`. Metadados, contrato/hook PHP e teste ficam nos diretórios correspondentes do construtor canônico. Origem Scriptcase em `sources/scriptcase/phase2/`; o formulário foi lido por SFTP do `.170`, sem executar PHP.

Provas privadas em `.runtime/ciw-cotacoes-20261021/`: `compiled122-verification-final.json`, `compiled122-final/`, `active-manifest.json`, `utc-active-manifest.json` e **`layout-active-manifest.json`** (prevalece para o serviço e confere os seis artefatos); `canonical-manifest.json`, `canonical-utc-manifest.json` e **`canonical-layout-manifest.json`**; `publication-hist-cotacoes-bc.json`, `real-validation.json`, `confirmed-audit-events.json`, `deletion-scope-verified.json`, `test-ownership.json` e `home-runtime-verification.json`. Guards `crud-attempt-once.json` e `crud-attempt-utc-fixed.json` permanecem; não apagar nem reexecutar. Não copiar credenciais ou conteúdo de configuração privada para documentação.
