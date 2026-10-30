# Cabeçalho e Logs Exclusão da aba Itens

Publicado em17/10/2026 pelo Builder/governança oficiais: `ciw.container-painel`, `op1001`, versão1.0.6. A única mudança funcional sobre1.0.5 é `formOptions.tabs[id=itens].linkedPage.hideHeader=false`. As14abas, permissões, contexto, demais opções, entidade e `op1002` foram preservados. O cabeçalho nativo da filha passa a disponibilizar **Logs Exclusão** dentro da aba Itens / Alocação.

O botão nativo consulta exclusões da tela `ciw.container-itens` em **todos os containers permitidos**, com isolamento do tenant e permissões existentes. Essa publicação não acrescentou filtro pelo container atual. Logs por registro continuam no formulário do item, pelo contrato existente.

Candidato rastreável: `D:/opencode/construtor-pg/integrations/ciw-container-painel/painel-1.0.6-builder-draft-candidate.json`. Evidências privadas: `.runtime/container-items-20261017/panel-header-delta-test.json`, `panel-header-publication-plan.json` e `panel-header-publication-receipt.json`. Recibo confirma versão36, aprovação35 e statusverified. Locks liberados e grant temporário revogado; nenhum cadastro de entidade/fonte reexecutado.

Validação: comparação oficial da definição publicada1.0.5 com preview; delta gerado limitado a versão e hideHeader. O preview recebeu o ícone null existente explicitamente, pois external/validate fornece padrãofile quando omitido, enquanto saveDraft preserva null. A metadata candidata conservou os bytes de espaçamento/quebras e demais valores do arquivo1.0.5. Fingerprint ignora somente versions[].governance, cujo resumo muda pela própria concessão de edição; configuração, definições, identidades, status e timestamps permanecem protegidos.

Teste canônico `ContainerItemsMetadataTest.php`:9testes/271asserções. Verificador HTTP somente leitura `tools/construtor-local/verify-container-items.cjs runtime-read` atualizado para1.0.6 e para o cabeçalho/escopo de Logs Exclusão. A validação visual foi coordenada com o agente principal para não interferir em sua aba ativa. Nenhuma operação de negócio foi executada nesta publicação.
