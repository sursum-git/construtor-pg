# Lote com originais e contexto protegidos

O exemplo `examples/pages/batch-snapshot.html` usa o mesmo `CrudKendoGridRenderer`, normalizador e validador publicados. Funciona com duas linhas fictícias em memória, sem requisições de rede. O botão salvar mostra o payload produzido pelo renderer; recarregar restaura os dados. Não reproduz regras fiscais, permissões ou transações do backend.

No Builder, habilite `gridEditMode: "batch"` e `crudOptions.batchSnapshot` com `originalFields` e `contextFields`. Os nomes devem ser campos existentes e únicos, no máximo64 por lista. No JSON gerado, a opção fica em `crud.grid.editing.snapshot`. Cada contexto listado precisa ser obrigatório e readonly. A PK e os campos históricos ficam readonly; somente os campos explicitamente editáveis vão em `changes[].data`. Os originais completos vão em `changes[].original`; o contexto vai em `context`. Nunca use um textarea de JSON para editar snapshot.

Sem opt-in, permanece o contrato anterior `{changes}`, com originais apenas dos campos editáveis. A capacidade é genérica; concorrência, ownership, transação, permissões e auditoria devem ser verificados pelo backend real de cada entidade.

Produção continua exclusivamente por `screenId`, metadata assinada e `endpointId`. Não copie a página de exemplo, os dados fictícios nem seu cliente em memória para `production/`; não carregue o JSON local no bootstrap de produção. Use os endpoints cadastrados e a sessão autenticada. O exemplo não libera permissões nem executa operações CIW.

No CIW1042, o snapshot contém14campos físicos e contexto `container_id`; apenas `vl_prev`, `vl_base_imp` e `log_manual` são editáveis. Os lotes update/delete são homogêneos. Recusa de stale precisa ocorrer antes de qualquer efeito, e uma resposta confirmada com apresentação indisponível limpa o rascunho para impedir reenvio. O teste comercial real depende de cenário novo autorizado, limites concretos e limpeza canônica comprovada. Compilação/publicação e leituras reais não comprovam essas escritas.

Validação da capacidade: testes nativos `container-expense-batch/tests/native-batch.cjs` e `browser-native.cjs`, além do teste funcional deste exemplo. A skill local `construtor-pg-demo-production-parity` não está instalada; o checklist de `docs/paridade-demo-producao.md` foi aplicado diretamente.
