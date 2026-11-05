# Detalhe de evento de e-mail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrar o resultado SES em uma janela de detalhe e localizar o grid de e-mails para pt-BR.

**Architecture:** O repositório transforma o envelope SNS em campos de detalhe. O JavaScript liga esses campos ao Kendo Window e configura a exibição da data e a cultura.

**Tech Stack:** PHP/Symfony, PostgreSQL JSONB, jQuery e Kendo UI.

**Spec:** `docs/superpowers/specs/2026-11-05-detalhe-evento-email-design.md`

## Global Constraints

- Não enviar `json_evento` bruto ao navegador.
- Preservar o Kendo UI local e a cultura `pt-BR`.

## Review Focus

- Envelopes SNS cujo evento SES está em `Message` devem mostrar o motivo da falha.
- Entregas devem mostrar a resposta SMTP.
- Eventos sem detalhe não podem impedir a abertura da janela.
- O primeiro carregamento deve listar registros do dia sem filtro de card.
- A data não pode permanecer no formato ISO no grid.

### Task 1: Resumo seguro do SES

**Files:**
- Modify: `backend/src/Email/EmailAnalyticsRepository.php`
- Test: `backend/tests/Email/EmailAnalyticsRepositoryTest.php`

- [ ] Escrever teste que espera status e motivo para um envelope SNS Bounce.
- [ ] Executar o teste e confirmar falha.
- [ ] Implementar extração de detalhe SES e incluí-la nos registros retornados.
- [ ] Executar os testes PHP.

### Task 2: Grid e janela Kendo

**Files:**
- Modify: `src/email-analytics/email-analytics.js`
- Test: `tests/frontend/email-analytics-smoke.mjs`

- [ ] Escrever teste do carregamento inicial, data brasileira e abertura de detalhe.
- [ ] Executar o teste e confirmar falha.
- [ ] Implementar filtro inicial vazio, formatação pt-BR e Kendo Window no duplo clique.
- [ ] Executar o smoke test e validar visualmente.
