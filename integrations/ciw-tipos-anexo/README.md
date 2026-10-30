# CIW — Tipos de anexo

Integração publicada: production/app.html?screenId=ciw.tipos-anexo, programa cd0091. JSONs api-source.json e builder-draft.json descrevem a fonte Progress e a tela de múltiplos registros. O marcador __RUNTIME_SECRET__ precisa ser resolvido somente no backend privado, nunca no browser.

Modo gridEditMode=batch, operação apiBatchOperationCode=batch e POST /SursumPrograms/tipos-anexo. Adicionar/Excluir linha e Salvar/Cancelar lote. ID gerado no servidor, descrição opcional até100 caracteres. Um lote executa uma transação ABL com rollback integral, conferência do original e regras de dependência/Pró-Forma.

A importação inicial já foi executada pelo fluxo oficial de governança. Para substituir APIs, salvar a fonte e depois o vínculo da entidade que guarda seu snapshot. Preservar o contrato transacional. Não rodar outra importação inicial sobre cd0091.

Documentação do modo genérico: docs/crud-edicao-lote.md e backend/docs/api-entity-batch.md na raiz do projeto. Memória completa D:/opencode/conversao-sc/context/PROJECT_CONTEXT.md e relatório docs/integracao-tipos-anexo-validacao.md desse workspace. Nenhuma credencial nos JSONs versionáveis.
