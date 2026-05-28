# Backlog v1 estavel

Registro para fechar a primeira versao estavel do motor CRUD dinamico.

## Seguranca da definicao de telas

- Implementado no frontend: modo `security.mode="production"` para bloquear JSON direto e `definitionUrl` livre.
- Implementado no frontend: motor pode iniciar por `screenId`, por exemplo `cadastros.clientes`.
- Implementado no frontend: entradas separadas em `production/`, sem mock e sem JSON local de `examples/`.
- Implementado no backend: autentica usuario, valida tenant/sessao e filtra o JSON autorizado por grupos/permissoes da sessao.
- A demo pode continuar usando `definitionUrl` e JSON local.
- Implementado no frontend: a configuracao de producao pode desabilitar `definition` direto e `definitionUrl` livre.

## Endpoints e acoes

- Implementado no frontend: URL livre no JSON pode ser bloqueada em producao.
- Implementado no frontend: `endpointId` e `actionId` podem ser resolvidos por gateway runtime.
- Implementado no backend: valida se a acao pertence a tela solicitada via `runtime_endpoint`.
- Implementado no backend: valida permissao por endpoint em `runtime_endpoint.permission`, incluindo consultar, criar, editar, excluir, acoes em massa, preferencias, ajuda lida e administracao.
- Implementado no frontend: o endpoint de novidades lidas aceita identificador controlado, por exemplo `help.markAsRead`.

## Validacao no backend

- O backend nao deve confiar no JSON recebido do frontend.
- Validar campos retornados no grid.
- Validar campos editaveis no formulario.
- Validar filtros permitidos.
- Validar ordenacao e agrupamento permitidos.
- Validar acoes em massa e lista de ids.
- Garantir isolamento por tenant.
- Retornar `403` quando o usuario nao tiver permissao.

## Validacao no frontend

- Manter o JSON como metadado declarativo.
- Nao executar JavaScript vindo do JSON.
- Bloquear `eval`, `Function`, script inline, template livre e HTML bruto inseguro.
- Validar campos, permissoes, formulas, URLs e referencias antes de renderizar.
- Nao colocar tokens, senhas, segredos ou regras sensiveis no JSON.

## Arquitetura recomendada

- Frontend solicita uma tela por `screenId`.
- Backend devolve a definicao ja filtrada e autorizada.
- Frontend renderiza dinamicamente.
- Todas as chamadas de dados passam por uma camada runtime controlada pelo backend.
- O JSON define experiencia visual, mas nao autoriza acesso a dados.

## Roadmap: rollback e downgrade de atualizacoes

Situacao atual:

- Existe rollback formal de release quando o manifesto declara `rollbackStep`, `rollbackSteps[]` ou `rollbackTargetVersion`.
- Existe historico por assinante em `system_update_execution`.
- Nao existe downgrade livre para qualquer versao antiga.

Evolucao planejada:

- Definir politica central de rollback e downgrade por release.
- Exigir matriz de compatibilidade entre versao atual, versoes de update e versoes permitidas para retorno.
- Indicar na tela `admin.atualizacoes` se a release permite rollback seguro, rollback parcial ou rollback proibido.
- Indicar na tela `admin.atualizacoes-assinantes` a versao atual do assinante, o historico aplicado e as acoes de retorno permitidas.
- Bloquear rollback quando houver migracao de banco sem passo reversivel ou sem backup validado.
- Permitir downgrade apenas quando a cadeia declarar explicitamente o alvo permitido e os passos reversiveis.
- Criar testes cobrindo apply, historico por assinante, rollback permitido e rollback proibido.

Decisao de produto:

- Tratar rollback como operacao controlada de recuperacao.
- Tratar downgrade livre como recurso futuro e restrito, nao como acao administrativa comum.

## Roadmap: arquitetura complementar pos-v1

Itens importantes para evolucao do construtor e do runtime, mas que nao bloqueiam a primeira entrada em producao:

- **Workflow/state machine visual**: importante para processos longos e fluxos com varias etapas, aprovacoes e retornos. Pode vir depois porque ja existe a base de situacao/transicao por entidade, que atende o ciclo de vida simples de registros.
- **Observabilidade operacional**: adicionar metricas, saude de filas, saude do EventBus, atrasos, falhas por handler, tentativas e alertas operacionais. Deve comecar simples, aproveitando `runtime_transaction`, `runtime_transaction_log`, `runtime_async_job`, `runtime_event` e `runtime_event_delivery`.
- **Feature flags**: permitir liberar recursos por release, assinante, ambiente ou canal. E util para SaaS e on-premise, mas a base atual de parametros, canais e politicas de update ja cobre parte do controle inicial.
- **Cache distribuido**: avaliar Redis ou equivalente quando houver volume real, concorrencia alta ou necessidade de cache compartilhado entre workers/containers. Nao deve ser prioridade antes de haver medicao de gargalo.

## Roadmap: adequacao LGPD

Recursos recomendados para evoluir a governanca de dados pessoais:

- **Catalogo de dados pessoais por campo**: marcar no construtor se um campo contem dado pessoal, dado sensivel, identificador, contato, documento, financeiro ou dado anonimizavel.
- **Base legal e finalidade**: registrar por entidade/campo a finalidade de tratamento, base legal, origem do dado, compartilhamento e prazo de retencao.
- **Retencao e descarte**: criar politicas por entidade para expurgo, anonimizacao ou soft delete apos prazo configurado, com simulacao antes de aplicar.
- **Anonimizacao/pseudonimizacao**: handlers fechados para mascarar CPF/CNPJ, e-mail, telefone, nome e campos livres, sem script arbitrario.
- **Solicitacoes do titular**: tela e workflow para acesso, correcao, portabilidade, oposicao, bloqueio, anonimizacao e eliminacao, com evidencias.
- **Auditoria LGPD**: registrar quem consultou/exportou/alterou dados pessoais, com filtro por titular e relatorio de atendimento.
- **Exportacao do titular**: pacote JSON/CSV/PDF com os dados relacionados a um titular, respeitando permissoes e escopo do assinante.
- **Consentimento**: cadastro de consentimentos por finalidade, versao do termo, aceite, revogacao e historico.
- **Minimizacao de dados**: alertas no construtor quando campo pessoal nao tiver finalidade/base legal ou quando for exibido sem necessidade na grid.
- **Mapa de integrações**: indicar em import/export e APIs externas quais dados pessoais saem ou entram, com contrato, operador e evidencia.
- **Criptografia/segredo por campo**: avaliar criptografia em repouso para campos sensiveis e mascaramento por perfil no frontend/runtime.
- **Relatorio de impacto**: gerar DPIA/RIPD inicial por entidade/programa, usando catalogo de campos, finalidade, retencao e integrações.
