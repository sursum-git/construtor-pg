# Gestão de e-mails SES Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar um módulo somente leitura com dashboard SES e Kendo Grid para a tabela externa `notificacoes`.

**Architecture:** Um repositório PDO/DBAL normaliza eventos e aplica filtros permitidos; um controlador entrega resumo e páginas de registros. A página local usa Kendo Grid remoto e traduz cada cartão em filtro de tipos SES.

**Tech Stack:** Symfony/PHP, Doctrine DBAL, PostgreSQL, Kendo UI for jQuery local, Playwright.

**Spec:** `docs/superpowers/specs/2026-11-04-gestao-emails-design.md`

## Global Constraints

- Não alterar `kendo/`.
- pt-BR, sem `alert`, `confirm`, `prompt`, `eval` ou templates livres.
- Credenciais somente em `EMAIL_ANALYTICS_DATABASE_URL`.
- Consultas exclusivamente de leitura e SQL parametrizado.

## Review Focus

- `json_evento` inválido não pode derrubar a listagem; o tipo deve cair para `cod_tipo` normalizado.
- Filtros e ordenações fora da lista permitida devem ser recusados.
- O clique no cartão deve reiniciar a página da grade e preservar o filtro selecionado.
- Eventos sem destinatário ou título devem permanecer listáveis.
- Falhas devem agregar somente `BOUNCE`, `REJECT` e `RENDERING_FAILURE`.

### Task 1: Consulta segura de eventos

**Files:**
- Create: `backend/src/Email/EmailAnalyticsRepository.php`
- Test: `backend/tests/Email/EmailAnalyticsRepositoryTest.php`

- [ ] Escrever testes falhos para normalização de evento SES, agrupamento dos contadores e filtro parametrizado por tipos.
- [ ] Executar `php bin/phpunit tests/Email/EmailAnalyticsRepositoryTest.php` e confirmar falha por classe ausente.
- [ ] Implementar repositório com `summary(): array` e `search(array $criteria): array`, whitelist de ordenação e conexão em `EMAIL_ANALYTICS_DATABASE_URL`.
- [ ] Reexecutar o teste e confirmar aprovação.

### Task 2: Endpoints somente leitura

**Files:**
- Create: `backend/src/Controller/EmailAnalyticsController.php`
- Test: `backend/tests/Controller/EmailAnalyticsControllerTest.php`

- [ ] Escrever testes falhos para `GET /email-analytics/summary` e `GET /email-analytics/records`, incluindo paginação e rejeição de filtro inválido.
- [ ] Executar o teste e confirmar falha por controlador ausente.
- [ ] Implementar controlador com sessão vigente, envelopes JSON fechados e delegação ao repositório.
- [ ] Reexecutar os testes e confirmar aprovação.

### Task 3: Página Kendo do módulo

**Files:**
- Create: `public/email-analytics.html`
- Create: `src/email-analytics/email-analytics.js`
- Create: `src/email-analytics/email-analytics.css`
- Test: `tests/frontend/email-analytics-smoke.mjs`

- [ ] Escrever smoke test falho que clica em `Enviados hoje` e confirma recarga remota do Kendo Grid com `SEND`.
- [ ] Executar `node tests/frontend/email-analytics-smoke.mjs` e confirmar falha porque a página não existe.
- [ ] Implementar cartões, filtros de período, DataSource remoto e Kendo Grid com paginação, ordenação, filtro, menu de coluna, reordenação e redimensionamento.
- [ ] Reexecutar o smoke test e confirmar aprovação.

### Task 4: Guia de configuração e regressão

**Files:**
- Create: `integrations/email-analytics/README.md`
- Modify: `docs/paridade-demo-producao.md`

- [ ] Documentar a variável de ambiente, permissões de banco mínimas e os endpoints sem incluir senha.
- [ ] Executar os testes novos e `npm run test:analytics-demo`.
- [ ] Registrar a paridade demo/produção conforme a documentação do projeto.
