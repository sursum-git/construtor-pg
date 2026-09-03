# SDD ledger — plan: docs/superpowers/plans/2026-09-02-program-builder-master-detail.md

Workspace: C:\construtor-pg\.worktrees\codex-program-builder-master-detail
Merge base: 43df232f
Baseline: node tests/frontend/master-detail-smoke.mjs PASS; node tests/frontend/master-detail-create-flow-smoke.mjs PASS.
Ruling: The bundled SDD shell helpers cannot run in this Windows worktree because WSL Bash receives a Windows Git-dir path; equivalent plan-scoped ledger, briefs, reports and review packages will be maintained under .superpowers/sdd/ manually — cost if wrong: only orchestration artifacts, not product code, need recovery.

## Preflight scan

| Tasks | Shared file/interface | Finding |
|---|---|---|
| 1 and 2 | ProgramBuilderService.php; masterDetailConfig | Task 1 produces normalizer/generator; Task 2 persists its normalized output. No conflict. |
| 1 and 3 | masterDetailConfig contract | Task 1 is the backend authority; Task 3 collects the same fixed contract. No conflict. |
| 2 and 4 | published master_detail definition | Task 2 publishes the definition; Task 4 consumes it for the visual smoke. No conflict. |
| 3 and 4 | Program Builder UI and generated definition | Task 3 collects/previews metadata; Task 4 verifies the runtime-rendered output. No conflict. |
| 5 and 1-4 | validation and continuity | Task 5 only records and verifies outputs from prior tasks. No conflict. |

| Task | Internal consistency | Finding |
|---|---|---|
| 1 | generator and rejection tests | Consistent; test names and closed error codes match the required contract. |
| 2 | persistence and publishing | Consistent; builderEntityCode remains the master entity. |
| 3 | editor and preview | Consistent; UI values originate only from existing entities/fields. |
| 4 | fixture and visual smoke | Consistent; production dispatcher already supports master_detail. |
| 5 | validation and documentation | Consistent; does not add domain examples before the Builder phase ends. |

Task 1: review failed — P1 total de campo textual aceito quando o payload força currency; P2 ausência de teste de total inexistente/textual. Fix round 1/5 iniciado.
Task 1: fix round 1/5 (P1 e P2 addressed, 0 open; commits 5351eb25..91d60b52)
Task 1: complete (commits 43df232f..91d60b52, review clean)
Task 2: minor (deferred): evidência RED registrada por mutação temporária porque a Task 1 já entregara a base que fazia o novo teste passar; revisar higiene TDD no review final.
Task 2: complete (commits 91d60b52..5798ccae, review clean)
Task 3: review failed — P1 inspetor não atualiza após trocar mestre; P1 validação visual 1366/768 pendente; P2 endpoint livre preservado no estado; P2 smoke não cobre essas invariantes. Fix round 1/5 iniciado.
Task 3: fix round 1/5 (all findings addressed; commits f24791b8..a9369984)
Task 3: complete (commits 5798ccae..a9369984, review clean)
