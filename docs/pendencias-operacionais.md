# Pendencias operacionais

Este arquivo registra pendencias que nao dependem mais de codigo no repositorio e precisam ser executadas depois, no ambiente correto.

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

## Como usar este arquivo

- quando houver pedido para consultar pendencias, revisar este arquivo primeiro;
- quando alguma pendencia for concluida no ambiente real, atualizar este arquivo;
- se surgir nova pendencia operacional fora do codigo, registrar aqui.
