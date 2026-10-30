# Consultas CIW — descontos e distribuição

Metadados já publicados no construtor em 28/10/2026. Esta pasta é uma referência para manutenção; não reimportar como novos programas ou substituir a fonte global por estes oito registros. A fonte `motor_progress_ciw_cadastros` possui 140 operações e preserva as 132 anteriores e sua autenticação.

| Programa | Tela | Escopo |
| --- | --- | --- |
| op1045 | ciw.descontos-importacao-geral | Consulta global de descontos, conforme a origem Scriptcase |
| op1046 | ciw.descontos-importacao-lancamentos | Movimentos do desconto selecionado; exige desconto_importacao_id |
| op1049 | ciw.custo-distribuicao-itens-aptos | Consulta global dos itens com a condição de situação da origem |
| op1050 | ciw.custo-distribuicao-lancamentos-item | Movimentos do item selecionado; exige item_container_id |

As quatro telas estão em 1.0.0 e permitem apenas consulta. O link de op1049 fornece o contexto de op1050. A manutenção usa os drafts JSON e os serviços existentes; não há frontend paralelo ou fórmulas de custos duplicadas. As permissões do usuário final exigem autorização própria e não são concedidas pela importação dos metadados.

No cadastro da API, a operação de detalhe usa `type: detail`; o corpo da chamada ao Progress continua com `operation: get`. O tipo `get` não é aceito como classificação do catálogo nativo. O arquivo de adições inclui somente oito operações sem credenciais; preservar o cadastro atual e a autenticação ao fazer futuras alterações.

Validação: OpenEdge 12.2 no compilador .42, 68 compilações e 4.120 verificações em tabelas temporárias sem banco; publicação de 25 artefatos no PASOE sursum-api/MED/9911. O helper PHP do extrato recebeu uma correção de comparação das propriedades da chave JSON, mantendo campos, tipos, dono e chave opaca; 917 testes PHP e 6.018 asserções passaram. Lista, detalhe, filtros e contexto foram confirmados nas quatro APIs e no runtime nativo usando somente leitura de registros existentes. Nenhum registro de negócio foi criado ou alterado para estas provas.

Recibos técnicos e dados privados de validação ficam no workspace de conversão em `.runtime/ciw-completion-20261028/official-build7`; não contêm exemplos a serem publicados no PASOE. Memória de retomada: `D:/opencode/conversao-sc/context/CIW_COMPLETION_GOAL_2026-10-28.md`.
