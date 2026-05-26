# Pendencias operacionais

Este arquivo registra pendencias que nao dependem mais de codigo no repositorio e precisam ser executadas depois, no ambiente correto.

## Central real de instalacao e atualizacao

Status atual:

- a central ja possui CRUD de licencas em `admin.instalacao-licencas`;
- a central ja possui CRUD de tokens internos em `admin.instalacao-tokens`;
- a central ja possui painel consolidado em `admin.central-operacoes`;
- a API `/api/admin/central-operations/dashboard` consolida licencas, tokens, artefatos, chaves, auditoria, saude dos assinantes e notificacoes derivadas;
- a confirmacao por e-mail ja bloqueia tentativas repetidas por requisicao usando `APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS` e `APP_INSTALLER_ACTIVATION_BLOCK_MINUTES`.

Pendencias a executar depois:

1. configurar a central real com dominio HTTPS e SMTP real
   - preencher `MAILER_DSN`;
   - preencher `APP_INSTALLER_ACTIVATION_FROM`;
   - validar com `php scripts/installer/validate-central-config.php`.

2. cadastrar licencas reais e tokens internos
   - usar `admin.instalacao-licencas`;
   - usar `admin.instalacao-tokens`;
   - revisar limites de ativacao, perfis, modos e validade.

3. revisar o painel `admin.central-operacoes` antes da liberacao
   - confirmar chaves fortes;
   - confirmar artefatos publicados;
   - confirmar ausencia de alertas criticos;
   - revisar saude dos assinantes.

4. definir rotina operacional
   - periodicidade de rotacao de chaves;
   - politica de revogacao de tokens e fingerprints;
   - responsavel por tratar alertas/notificacoes da central.

## Orquestrador de rollout SaaS

Status atual:

- o receptor externo do webhook ja existe em `scripts/orchestrator/`;
- a configuracao local de exemplo ja foi validada;
- o sistema central local ja sabe apontar para o orquestrador.

Pendencias a executar depois:

1. instalar Docker e Docker Compose no host que vai executar o rollout real
   - no ambiente local atual, o health do orquestrador retornou `dockerComposeAvailable=false`

2. trocar os alvos locais pelos alvos reais do SaaS
   - ajustar `scripts/orchestrator/system-update-orchestrator.config.json`
   - apontar `composeFile`, `workdir`, `projectName` e `services` reais por assinante

3. trocar o token e a chave locais por credenciais reais
   - substituir os valores locais usados no ambiente atual:
     - `APP_UPDATE_ORCHESTRATOR_TOKEN`
     - `APP_UPDATE_ORCHESTRATOR_SIGNING_KEY`

4. revisar os servicos que entram no rollout real
   - no ambiente local atual o `compose.yaml` so expunha `database`
   - no host real, ajustar `services` por assinante para incluir `app`, `web` ou outros containers necessarios

## Bloqueio local durante rollout SaaS

Status atual:

- o app ja sabe ler estado local de rollout SaaS por `APP_SAAS_ROLLOUT_STATE_FILE`;
- o orquestrador externo ja sabe gravar esse estado por assinante durante o rollout;
- a Home ja bloqueia a entrada quando o rollout critico estiver ativo e o estado indicar bloqueio.

Pendencias a executar depois:

1. configurar `APP_SAAS_ROLLOUT_STATE_FILE` no ambiente SaaS real
   - o sistema que atende o tenant precisa apontar para o arquivo local de estado do rollout

2. preencher `rolloutStateFile` na configuracao real de cada target do orquestrador
   - ajustar `scripts/orchestrator/system-update-orchestrator.config.json`
   - definir um caminho real e persistente por assinante ou por ambiente controlado

3. subir essa configuracao junto do orquestrador no host SaaS real
   - garantir que o processo do orquestrador tenha permissao de escrita nesse arquivo
   - garantir que a aplicacao que atende o tenant tenha permissao de leitura

## Como usar este arquivo

- quando houver pedido para consultar pendencias, revisar este arquivo primeiro;
- quando alguma pendencia for concluida no ambiente real, atualizar este arquivo;
- se surgir nova pendencia operacional fora do codigo, registrar aqui.
