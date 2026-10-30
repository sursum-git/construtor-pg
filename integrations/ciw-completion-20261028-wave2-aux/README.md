# Consultas históricas CIW — onda2

Fontes dos metadados publicados em 28/10/2026 pelo Builder oficial: op1036 pagamentos, op1037 NFs, op1038 descontos e op1039 despesas da versão, todos1.0.0. Pai op1005/ciw.container-versoes-custo1.0.2, cinco links. Fonte compartilhada132operações, preservadas124anteriores. Não reaplicar estes JSONs por importação cega.

Consultas somente leitura de snapshot persistido, com container_id e versao_id obrigatórios. Integração usa os coletores centrais, não recalcula valores históricos. Compilação oficial OpenEdge12.2/.42, publicação137 e validação sursum-api/MED9911. Detalhes positivos em pagamentos/NFs/despesas; descontos vazios nas duas versões reais verificadas, detalhe coberto em TT.

Nomes atuais de itens/fornecedores/despesas e comparações BRL/USD continuam em incrementos separados. As quatro novas permissões humanas de leitura aguardam autorização; as três leituras da onda1 já estão autorizadas e aplicadas. Manutenção em lote1042: API publicada e leitura/negativas validadas, mas tela/fonte específica ainda não publicadas, teste de escrita depende de novo cenário autorizado e limpeza comprovada.

Recibos privados e memória de retomada no workspace D:/opencode/conversao-sc; nenhum cabeçalho secreto é armazenado nestes exemplos. A produção usa metadados assinados por screenId/endpointId e nunca carrega mocks ou JSON de exemplo.
