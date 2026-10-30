# CIW: autorização SQL de Colorações —19/10/2026

Implementação do adendo aprovado em `docs/superpowers/specs/2026-10-19-ciw-autorizacao-sql-design.md`
e plano `docs/superpowers/plans/2026-10-19-ciw-autorizacao-sql.md`.

## Estado efetivo

Publicado e validado tecnicamente. A conta `tcassimiro/default` foi criada pela
operação governada `app:auth:configure-ciw-identity --apply`. Usa `ima-user-web`,
sem senha local, grupos, permissões de negócio ou overrides. Seu vínculo íntegro
de assinante aponta explicitamente para `thiago.cassimiro`, sistema18. Segunda
execução retornou created=false/unchanged=true, sem sobrescrever cadastro.

**Pendente:** login humano de tcassimiro na interface existente, consulta PHP
autenticada ponta a ponta e logout da mesma sessão. Solicitado ao usuário sem
pedir senha no chat. A fase1A continua parcial até essa prova. Não tratar a
consulta técnica de identidade no PASOE como login real no construtor.

## Origem e contratos

O CIW atual redireciona para ctrl_login_html. Esse fluxo autentica s_users/LDAP
e aplica concessões SQL ticontrole2: s_users_groups/s_groups_apps/programas/modulos,
sistema18. Colorações tem flagsY para thiago.cassimiro. Progress tcassimiro existe
ativo, mas espec.grupos_aplicacoes não contém concessões desses programas.

O login Progress escolhido a partir de bl_tela_login foi preservado. O novo
`POST /api/ciw/access` resolve token/sessão, status, origem e vínculo íntegro antes
de confirmar identidade Progress e ler concessões SQL. Corpo fechado:
`operation=authorize,resource=cadastros.coloracoes,action=read|get|create|update|delete|export|print`.
Retorno positivo traz scope=legacy_ciw. Não substitui permissões locais dos
endpoints de negócio. Nenhuma ação CRUD é executada por essa consulta.

Read/export/print usam grid_pub_coloracoes; get/create/update/delete usam
form_pub_coloracoes. AcessoY é obrigatório; as demais ações exigem flagY própria.
União determinística por flag entre grupos, sem cache, TOP1 ou bypass de admin.
O legado sobrescreve linhas divergentes sem ORDER BY; não se promete reproduzir
essa ordem indeterminada. As concessões reais observadas são todasY.

Nova rota PASOE `POST /SursumPrograms/ciw-identity` aceita somente operation=identify,
token técnico e identidade internos, sem senha. CiwIdentityService foi extraído
do serviço anterior; ciw-access conserva concessões Progress e continua negando
Colorações nesse banco. Não há fallback SQL→Progress ou PHP→próprioPHP.

## Publicação e transporte

Onze fontes sem erro no compilador OpenEdge12.2/.42. Publicados oito artefatos
(.p/.r de identidade serviço/fronteira/entrada e serviço antigo refatorado), com
guards de hashes, backup e rota mesclada. Instância única sursum-api/med; recarga
oficial confirmou sucesso das sessões ociosas25/26, sem reiniciar instância.
Manifesto `.runtime/ciw-authz-sql-20261019/publish/active-manifest.json`.

Fontes/testes/catálogo sincronizados em motor-progress/v2; PHP em construtor-pg,
com guards/backups. Nenhum Kendo, frontend paralelo, schema, concessão Progress/SQL,
dado de negócio, admin ou tasilpar alterado por este lote. Conta corrente fornecedor
continua excluído. Sem commit/push/reseed/reassinatura de registros existentes.

ODBC Driver17 via PDO_ODBC no processo PHP. SQL somente por loopback19933 no túnel
SSH com chave do host verificada até170 e destino no próprio170:1433. O backend
exige estado privado válido e PID do listener correspondente ao túnel antes de
abrir PDO. Colisão/ausência/queda falham503 sem enviar credencial ao outro listener.
Não houve bypass de validação TLS direta ou alteração do truststore/globalPHP.

Conexões técnicas, estado do túnel e backup de ambiente ficam em
`.runtime/ciw-authz-sql-20261019/private/`, com ACL aplicada antes dos segredos.
Mencionar caminhos, nunca conteúdo. Nenhuma senha humana foi persistida.
Verificação de ownership usa netstat do Windows; outra plataforma requer guard
equivalente antes de implantar, e hoje falha fechada. Não há snapshot atômico
distribuído entre identidade Progress e grants SQL.

## Evidência confirmada

- PHP final: **532 testes,3186 asserções**, sem falhas; lint:container PASS.
- ABL12.2: **297 checks por cultura** AMERICAN/EUROPEAN: identidade32,
  fronteira identidade27, concessões156 e fronteira antiga82.
- PASOE real: seis casos PASS — identidade ativa, token inválido, origem local,
  login inexistente, corpo com identidade forjada e concessões antigas negadas.
- SQL real: sete decisões ALLOW somente SELECT; não são ações de negócio.
- Túnel: dois testes Python de destino, transporte inativo, colisão e limpeza;
  teste PHP de ownership com listener correspondente, alheio e estado ausente.
- Reinício recusou projeto incorreto antes de parar processo; reinício correto
  preservou frontend/banco/túneis e carregou PDO_ODBC somente no processo.
- Revisão independente única: nenhum Critical; Important de ownership corrigido
  com RED→GREEN e nova suíte completa verde.

Cobertura menor pendente da revisão: teste integrado de impersonation vencida
ainda ativa/token trocado no fluxo real; rollback persistido, falha de assinatura
e corrida de duas ativações no banco real. A revisão examinou o AuthService e a
transação Doctrine e não confirmou defeito funcional nesses caminhos. As recusas
por status/token, idempotência real e falhas sanitizadas já possuem evidência.

Evidências privadas em `.runtime/ciw-authz-sql-20261019/`: progress.md,
compile/,compiled/,publish/,php-final.log,sql-preflight.json,
pasoe-identity-evidence.json,registration-receipt.json,registration-idempotency.json,
canonical-manifest.json,motor-canonical-manifest.json e review-ownership-red.log.

## Reversão delimitada

Desabilitar somente o vínculo novo por operação governada se necessário, sem
acrescentar concessões alternativas. Encerrar apenas os túneis próprios conforme
PID registrado. Restaurar somente os artefatos alterados a partir do backup,
conferindo hashes atuais antes de substituir; remover a rota nova preservando
as demais. Não usar o catálogo histórico completo como substituto de um catálogo
que recebeu mudanças concorrentes. Login Progress e outras contas permanecem.
