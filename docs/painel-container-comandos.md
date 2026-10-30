# Painel do container — manutenção, processos e logs

Entrega de16/10/2026 no construtor local e PASOE `sursum-api/med`. Painel `ciw.container-painel` versão1.0.4,14abas; Despesas1.0.1. A consulta `ciw.containers` permanece1.0.2. Programas novos: **op1015** Gerar Versão, **op1016** Fechar Custos e **op1017** Sincronizar Impostos. op1012 pertence a `iol.inicial-gerencial` e foi preservado.

## Utilização

1. Abra o painel pelo primeiro botão da consulta de containers. Em Dados, use **Alterar** para editar o cabeçalho. A inclusão de cabeçalho pela UI permanece desabilitada porque o contrato exige ID manual raiz. Exclusão valida dependências e executa as regras do banco na mesma transação.
2. Em Despesas, o formulário nativo permite inclusão, alteração e exclusão individual. Os seis campos editáveis passam pelo serviço central; valores derivados são recalculados, sem aceitar alteração arbitrária pelo navegador. O batch ABL homogêneo permanece privado.
3. Em **Gerar Versão de Custo**, informe tipo, descrição e ID da última versão esperada;0 apenas para container sem versão. Consulte os IDs na aba Versões Custo. Na confirmação Kendo, **Gerar versão** executa a operação.
4. Em **Fechar Custos**, informe a versão e a última versão esperada, além da opção de priorizar custo previsto. A confirmação informa que serão gravados custos. A mensagem de sucesso apresenta os IDs gerados.
5. **Sincronizar Impostos** usa o contexto fixo do container. Abrir qualquer aba apenas carrega parâmetros; cancelar a confirmação não executa.
6. **Logs**, no registro ou no processo, abre a consulta nativa contextual. Abra um evento e a aba **Alterações** mostra usuário, código do usuário e comparação **Anterior / Atual**. **Logs Exclusão** permite consultar registros removidos.

O ID do container é obrigatório e validado pelo PHP e ABL; empresa, bancos, recursos, operações, providers e políticas são determinados no backend. As três fontes novas são `motor_progress_container_cabecalho`, `motor_progress_container_despesas` e `motor_progress_container_processos`. Os JSONs contêm somente marcador de segredo; o token real fica no estado privado.

## Implementação

`ContainersCabecalhoCrud.p`, `ContainersDespesasCrud.p` e `ContainersProcessos.p` são adapters. A composição de comandos usa `ContainersProcessosService.p`; custo/coleta, impostos, distribuição, versões e fechamento permanecem serviços separados reutilizáveis. Não foram copiadas fórmulas para telas ou wrappers.

Snapshots são capturados sob lock. Somente após commit raiz a resposta expõe `audit.source=progress-transaction`; chamadas aninhadas retornam mudanças pendentes. O PHP valida recurso, rota, chave, campos, tipos e vínculo antes de importar para as classes existentes de transação/log. A autoria vem da sessão autenticada do construtor, nunca do JSON do navegador. Falha na apresentação/importação opcional após commit produz aviso, evitando induzir repetição da operação confirmada.

A exclusão do cabeçalho verifica pagamentos/custos e dependências comerciais, remove os quatro conjuntos auxiliares das versões antes das versões e audita cada exclusão com chave composta. O importador aceita esses auxiliares no cabeçalho somente em DELETE, com a exclusão da versão comprovada no mesmo envelope/container.

O trigger original `ERP/especificos/ems2/ima/estrg/tdpp001.p` protege container fechado e pedidos de venda. A definição física usa barra invertida; em Linux a publicação inclui aliases com esse nome para o mesmo código compilado12.2. Nenhuma regra, definição de trigger, schema, PROPATH global ou autenticação é desabilitada/alterada.

## Evidências e continuidade

- Compilação real OpenEdge12.2.12/.42, runtime12.2.17/.111: lote final62fontes,30suítes,2167checks em cada cultura AMERICAN/EUROPEAN. `.runtime/container-commands-compiled-20261016-v5/`.
- PHP462testes/2674asserções; root confirmou27testes direcionados/414asserções. Metadados8testes/392asserções; smokes de contexto/confirmação, Logs e flag booleana de sessão passaram.
- PASOE13fronteiras das novas rotas,27custos e25financeiro; PHP11consultas com contexto/read/get/isolamento passaram.
- Teste real autorizado exclusivamente no novo container2147000100/ERP sintéticoZTSC161026: cabeçalho, despesa individual, sincronização, versão2358 e custo6144 confirmados.55eventos confirmados mostraram usuário Administrador/admin, filhos do mesmo container e alteração de despesa1→2. Exclusão foi bloqueada com custos vinculados. Evidências em `.runtime/container-commands-20261016/`.
- UI real confirmou14abas, formulários dos três processos, confirmação Kendo cancelada e Logs contextual; comparação de despesa exibiu usuário e valores1/2.

Manifests ativos: lote principal `.runtime/container-commands-publish-20261016/active-manifest.json`, delta de exclusão/trigger `.runtime/container-delete-compat-publish-20261016/active-manifest.json`. O segundo substitui somente seus artefatos no primeiro. Publicação Builder pelo plano `.runtime/container-commands-20261016/builder-publication-plan.json`; não repetir creates. Fontes canônicos em motor-progress/v2 e JSONs em construtor-pg/integrations/ciw-container-painel.

**Limpeza concluída:** command-cleanup-verification.json confirmou zero registros restantes do teste nas12tabelas verificadas e2393containers. Logs preservados:73eventos confirmados; transação1624 registra a exclusão do cabeçalho, versão e5snapshots auxiliares. Nenhuma sequência foi reiniciada.

Fora desta entrega: manutenção/alocação de itens, associações de pagamentos/NFs, distribuição manual e arquivos binários dos anexos. A conversão integral do menu continua; essas abas permanecem consultas. Locks protegem o próprio container e os registros lidos; não serializam todos os containers que compartilham um item ERP. Não alterar sequências para preencher lacunas de IDs consumidos pelos testes.

