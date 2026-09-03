# Correcoes finais — Program Builder mestre-detalhe

## Status

Os achados P1 e P2 foram corrigidos. A publicacao de `master_detail` agora materializa endpoints runtime fechados para mestre, filhas e inclusao conjunta; o relacionamento pai-filho exige uma FK coerente; o editor cobre as propriedades das filhas; e as permissoes de escrita controlam tanto a definicao/endpoints quanto as acoes visuais.

## Correcoes entregues

### Publicacao e runtime

- `ProgramBuilderService::syncRuntimeEndpoints()` publica e ativa `master.*` e `detail.<entidade>.*` com handler fechado `entity.crud`.
- Operacoes `create`, `update` e `delete` so sao geradas e ativadas quando `allowCreate`, `allowUpdate` e `allowDelete` permitem; endpoints gerados anteriormente que deixaram de valer sao desativados.
- O fluxo `draftWithChildren` aceita somente o endpoint canonico `createGraph`.
- `createGraph` usa o novo handler fechado `master_detail.createGraph`, protegido pela permissao de criacao e por assinatura estrutural.
- `RuntimeMasterDetailActionService` cria mestre e filhas dentro de uma unica transacao DBAL, deriva entidades/campo pai apenas da configuracao publicada, rejeita filhas nao declaradas e sobrescreve o `parentField` de cada filha com a chave criada do mestre.

### Relacionamento pai-filho

- O backend aceita `parentField` somente quando o campo possui `foreignKey` para a tabela e a coluna da chave primaria do mestre, ou uma relacao explicita equivalente por entidade/campo.
- O editor oferece somente esses campos elegiveis no seletor de vinculo.
- Ao trocar a entidade mestre, filhas cuja compatibilidade ja e conhecida e deixou de ser valida sao removidas da configuracao e do preview.

### Edicao das filhas e permissoes

- Cada filha pode editar `title`, `displayFields`, `totals` e o rotulo de cada total no inspetor.
- Campos exibidos ficam limitados ao catalogo da filha; totais ficam limitados a campos numericos.
- `MasterDetailEngine` nao renderiza botoes, comandos de grade nem duplo clique de edicao quando a permissao correspondente e `false`; os metodos de escrita tambem possuem guardas defensivas.
- Definicoes antigas sem o objeto `permissions` conservam o comportamento anterior; definicoes publicadas pelo Builder sempre levam permissoes explicitas.

### Documentacao

- `CONTEXTO_PROJETO.md`, `docs/arquitetura-crud-engine.md` e `docs/continuidade-codex.md` foram alinhados ao suporte atual de `master_detail`, ao contrato de FK e aos endpoints publicados.
- Nenhum arquivo de demo, `examples/`, `src/demo` ou `kendo/` foi alterado; por isso a verificacao de paridade demo/producao nao exigiu atualizacao de `docs/paridade-demo-producao.md`.

## Evidencia TDD

### RED

- O teste de publicacao encontrou zero endpoints `master.*`, `detail.*` e `createGraph` antes da implementacao.
- A normalizacao aceitava um campo existente sem FK como `parentField`.
- A definicao ainda continha APIs de escrita com os tres flags desabilitados.
- O smoke do editor falhou inicialmente porque `updateMasterDetailDetail()` nao existia.
- O smoke da engine contou 3 acoes de mestre, 6 acoes de filhas e 18 comandos de grade mesmo com escrita negada.
- O teste do handler transacional falhou inicialmente porque `RuntimeMasterDetailActionService` nao existia; uma assercao adicional tambem detectou que o `entityCode` da filha precisava ser sobrescrito no payload interno.

### GREEN focado

```text
php backend/bin/phpunit backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php backend/tests/Builder/ProgramBuilderServicePublishFlowTest.php backend/tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php backend/tests/Builder/ProgramBuilderServiceGovernanceTest.php backend/tests/Runtime/RuntimeMasterDetailActionServiceTest.php
OK (27 tests, 172 assertions)
```

```text
node tests/frontend/program-builder-technical-smoke.mjs
node tests/frontend/program-builder-master-detail-smoke.mjs
node tests/frontend/master-detail-smoke.mjs
node tests/frontend/master-detail-create-flow-smoke.mjs
Todos passaram.
```

### Regressao ampla

```text
cd backend
php bin/phpunit -c phpunit.dist.xml
OK (183 tests, 871 assertions)
```

Tambem passaram:

- `php backend/bin/console lint:container`;
- `php -l` nos arquivos PHP alterados e novos;
- `node --check` nos tres modulos JavaScript alterados;
- smokes adicionais de Program Builder para analytics, reports e regulated documents;
- `git diff --check`.

## Auto-revisao e preocupacoes

- Nenhum subagente/revisor foi acionado, conforme a restricao explicita desta correcao; a revisao foi feita localmente sobre o diff completo.
- O smoke preexistente `program-builder-governance-smoke.mjs` continua expirando porque espera o texto generico `Deseja continuar?`, enquanto `handlePublish()` abre a confirmacao com a mensagem especifica `Publicar esta versao vai atualizar...`. O gate estava `ready=true`, e nenhum arquivo desse fluxo possui diferenca de conteudo nesta correcao; portanto o problema foi mantido fora do escopo.
- Nao foi executado teste contra um banco PostgreSQL real para `createGraph`; a atomicidade foi coberta por teste unitario da fronteira transacional e pela suite completa do backend.
