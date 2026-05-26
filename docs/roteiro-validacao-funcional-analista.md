# Roteiro de validacao funcional para analista

Este roteiro foi escrito para um analista funcional que esta entrando no sistema sem contexto previo.

Objetivo:

- conseguir entrar no sistema;
- entender a navegacao basica;
- validar o caminho necessario para gerar um programa novo;
- depois percorrer as demais trilhas funcionais existentes hoje.

---

## 1. Preparacao minima

Antes de validar:

1. confirmar qual ambiente sera usado:
   - demo local;
   - producao local;
   - backend real.
2. confirmar se existe usuario com perfil administrativo.
3. confirmar se o backend ja publicou os metadados runtime.
4. confirmar se o worker de jobs esta ativo quando a trilha depender de fila.

Se o objetivo for validar tudo com backend real, os pontos minimos sao:

- login habilitado;
- `screenId` publicados;
- worker ativo para jobs;
- banco com migrations aplicadas.

Se o ambiente ainda nao foi instalado, seguir primeiro o manual:

- `docs/manual-instalacao.md`

Validar qual cenario sera usado:

1. Linux Docker on-premise;
2. Linux sem Docker on-premise;
3. Windows teste sem Docker;
4. Docker SaaS provisionado pelo orquestrador.

Resultado esperado da instalacao inicial:

- licenca cadastrada em `admin.instalacao-licencas` ou fallback configurado no `.env`;
- painel `admin.central-operacoes` sem alerta critico de chave, artefato ou licenca;
- executavel do perfil correto usado;
- codigo do assinante validado;
- codigo enviado por e-mail confirmado, exceto no SaaS com token interno;
- excesso de tentativas de codigo bloqueado conforme politica da central;
- precheck sem `ERRO`;
- sessao local de instalacao criada;
- `production/install.html` mostrando perfil, assinante e validade da sessao;
- `APP_SYSTEM_INSTALLED=1` gravado apos sucesso.

---

## 2. Trilha principal: do login ate gerar um programa novo

Essa e a trilha mais importante para um primeiro giro funcional.

### 2.1. Login

1. abrir a tela de login:
   - local: `C:/construtor-pg/login.html`
   - producao: `C:/construtor-pg/production/login.html`
2. validar os elementos basicos:
   - usuario;
   - senha;
   - manter logado;
   - exibir/ocultar senha;
   - esqueci a senha;
   - limpar sessao local.
3. informar usuario e senha validos.
4. se o usuario tiver mais de um assinante:
   - validar se a tela de selecao aparece depois da credencial;
   - escolher um assinante.
5. se o usuario for administrador:
   - validar a escolha de area:
     - area principal;
     - area administrativa.

Resultado esperado:

- sessao autenticada;
- assinante corrente definido quando aplicavel;
- abertura da Home ou da area administrativa.

### 2.2. Home

1. validar se a Home abriu com:
   - appbar;
   - menu lateral;
   - usuario corrente;
   - assinante corrente;
   - modulo selecionado.
2. navegar pelo menu lateral.
3. validar:
   - filtro do menu;
   - favoritos;
   - notificacoes;
   - jobs;
   - troca de assinante, quando habilitada.

Resultado esperado:

- o usuario consegue localizar programas e abrir a area administrativa.

### 2.3. Acessar a trilha de construcao

Para gerar um programa novo, o caminho funcional base e:

1. entrar na area administrativa.
2. abrir o **Program Builder**.
3. se necessario, abrir tambem programas de apoio:
   - parametros;
   - usuarios/permissoes;
   - literais;
   - integracoes;
   - governanca.

### 2.4. Criar ou revisar o modulo estrutural

1. no Program Builder, localizar a area de modulos.
2. validar se ja existe um modulo para a funcionalidade desejada.
3. se nao existir:
   - criar modulo;
   - informar abreviacao;
   - informar faixa numerica;
   - salvar.

Resultado esperado:

- modulo disponivel para receber entidade e programa.

### 2.5. Criar a entidade

1. criar nova entidade.
2. escolher o tipo da entidade:
   - `persistence`;
   - `query`;
   - `io`;
   - `api`.
3. para o primeiro teste funcional, priorizar `persistence`.
4. preencher:
   - codigo;
   - nome;
   - tabela fisica quando aplicavel;
   - campos;
   - chave primaria;
   - tipos de dados.
5. validar recursos da entidade:
   - obrigatoriedade;
   - `readonly`;
   - chaves unicas;
   - relacionamentos/FKs;
   - snapshots/versionamento quando aplicavel;
   - `subscriberIsolation` quando a tabela for por assinante.
6. se a entidade for compartilhada:
   - validar a confirmacao explicita de tabela global.

Resultado esperado:

- entidade salva;
- diagnostico estrutural sem pendencias bloqueantes.

### 2.6. Gerar o programa

1. abrir a parte de programa ligada a entidade.
2. informar:
   - codigo do programa;
   - nome;
   - tipo do programa;
   - configuracoes de grid e formulario.
3. gerar o preview.
4. validar:
   - colunas;
   - formulario;
   - filtros;
   - permissoes visuais;
   - mensagens.

Resultado esperado:

- preview utilizavel;
- versao de rascunho pronta para publicar ou seguir em governanca.

### 2.7. Publicar o programa

Existem dois cenarios principais:

#### A. Programa sem governanca especial

1. revisar preview.
2. salvar.
3. publicar.
4. abrir pela Home ou por `screenId`.

#### B. Programa padrao com governanca

1. abrir a governanca.
2. registrar request.
3. obter grant.
4. gerar/testar bundle quando exigido.
5. obter aprovacao.
6. publicar.

Resultado esperado:

- o programa fica disponivel no runtime;
- a versao publicada aparece no historico;
- a tela abre pela Home.

---

## 3. Trilha funcional por grupos

Depois da trilha principal, seguir estas validacoes por area.

## 3.1. CRUD e operacao diaria

Programas principais:

- cadastro de clientes;
- CRUDs publicados pelo builder;
- programas administrativos baseados em CRUD.

Validar:

1. abertura por `screenId`;
2. grid;
3. filtro;
4. ordenacao;
5. agrupamento;
6. exportacao;
7. formulario;
8. inclusao;
9. alteracao;
10. exclusao;
11. consistencias;
12. lock/semaforo quando aplicavel.

## 3.2. Processamentos

Programas principais:

- telas `process`.

Validar:

1. parametros;
2. botao processar;
3. retorno imediato;
4. acompanhamento de job;
5. abertura de documento/resultado.

## 3.3. Home e navegacao

Validar:

1. modulos;
2. busca no menu;
3. favoritos;
4. troca de assinante;
5. central de notificacoes;
6. jobs;
7. chat;
8. suporte;
9. persistencia de contexto apos recarga.

## 3.4. Login, sessao e autenticacao

Validar:

1. login normal;
2. lembrar usuario;
3. manter sessao;
4. token expirado;
5. limpar sessao local;
6. selecao de assinante;
7. escolha de area administrativa;
8. logout;
9. recuperacao de senha.

## 3.5. Parametros, literais e opcoes

Programas:

- `admin.parametros`
- `admin.parametro-valores`
- `admin.listas-opcoes`
- `admin.opcoes`
- `admin.literais`

Validar:

1. cadastro do parametro;
2. definicao do valor;
3. escopo global/por assinante quando houver;
4. lista de opcoes;
5. traducao/literal refletindo na interface.

## 3.6. Usuarios, permissoes e sessoes

Programas:

- `admin.usuarios`
- `admin.usuario-assinantes`
- `admin.permissoes`
- `admin.sessoes`

Validar:

1. cadastro de usuario;
2. vinculo com assinante;
3. permissao por tela/acao;
4. derrubar sessao;
5. reflexo do perfil no login e na Home.

## 3.7. Integracoes e import/export

Programa:

- `admin.integracoes`

Validar:

1. cadastro de mapping;
2. origem e destino;
3. preview estrutural;
4. TreeView do layout;
5. versoes do mapping;
6. historico persistido;
7. detalhe da execucao;
8. agendamentos;
9. exportacao do payload.

## 3.8. Program Builder avancado

Validar alem do fluxo base:

1. importacao de tabela existente;
2. importacao de JSON externo;
3. assistente interno de IA;
4. entidades `api`;
5. Odoo readonly;
6. codificacao customizada;
7. regras de negocio;
8. snapshots historicos;
9. versionamento;
10. rollback;
11. comparativo;
12. reordenacao visual;
13. lock de edicao.

## 3.9. Governanca de programas

Programas:

- `admin.programa-governanca`
- `admin.programa-grants`
- `admin.programa-aprovacoes`
- `admin.programa-auditoria-operacao`
- `admin.programa-overlays`
- `admin.programa-overlay-versoes`

Validar:

1. request;
2. grant;
3. freeze/revoke;
4. bundle de testes;
5. aprovacao;
6. publish;
7. overlay;
8. versao de overlay;
9. rebase assistido;
10. retencao;
11. auditoria.

## 3.10. Integridade estrutural

Programa:

- `admin.integridade`

Validar:

1. consulta do status;
2. registros invalidos;
3. reassinatura controlada;
4. reflexo no monitor backend.

## 3.11. Provisionamento de assinantes

Programa:

- `admin.assinante-ambientes`

Validar:

1. cadastro do assinante;
2. deployment mode;
3. ambiente principal isolado;
4. ambiente runtime;
5. precheck;
6. checklist;
7. job de provisionamento;
8. progresso por etapa;
9. retry parcial;
10. pacote on-premise;
11. checksum;
12. auditoria de runtime compartilhado;
13. matriz operacional.

Tambem validar a instalacao inicial quando o objetivo for entregar um ambiente novo:

1. cadastrar a licenca em `admin.instalacao-licencas`, informando e-mail, perfis permitidos, modos permitidos, validade e limite de ativacoes.
2. se for SaaS, cadastrar o token interno em `admin.instalacao-tokens`.
3. abrir `admin.central-operacoes` e revisar:
   - licencas;
   - tokens;
   - chaves;
   - artefatos;
   - auditoria;
   - saude dos assinantes;
   - alertas/notificacoes.
4. gerar ou baixar o instalador correto:
   - Construtor de Sistemas;
   - Assinante.
5. executar `--precheck` no modo escolhido.
6. confirmar que erro bloqueante impede continuidade.
7. executar a ativacao com codigo do assinante.
8. confirmar o codigo recebido por e-mail.
9. testar codigo incorreto ate o bloqueio temporario quando a trilha incluir caso negativo.
10. abrir `production/install.html`.
11. conferir perfil autorizado, codigo do assinante, modo e validade da sessao.
12. informar senha do instalador, admin inicial e dados do assinante.
13. executar instalacao.
14. em reinstalacao, confirmar que nova ativacao, senha e confirmacao explicita sao obrigatorias.

No Docker local, validar tambem:

1. `docker compose build`;
2. `APP_HTTP_PORT=18080 docker compose up -d`, se `8080` estiver ocupada;
3. `GET /health`;
4. `GET /api/install/status`;
5. `docker compose down`.

## 3.12. Atualizacoes do sistema

Programas:

- `admin.atualizacoes`
- `admin.atualizacoes-assinantes`

Validar:

1. leitura do manifesto;
2. cadeia obrigatoria;
3. politica por release;
4. simulacao;
5. apply;
6. rollback;
7. rollout SaaS;
8. updates por assinante;
9. timeline por assinante;
10. overlays/customizacoes afetadas;
11. publicacao de artefatos;
12. runner on-premise.

---

## 4. Ordem recomendada de validacao

Para nao se perder, esta e a sequencia mais racional:

1. login
2. selecao de assinante e escolha de area
3. Home
4. usuarios/permissoes
5. parametros/literais
6. CRUD base
7. processos
8. Program Builder
9. governanca
10. integracoes
11. integridade estrutural
12. provisionamento
13. atualizacoes

---

## 5. Evidencias que o analista deve coletar

Para cada trilha, registrar:

1. qual programa foi validado;
2. qual `screenId` foi usado;
3. qual usuario/assinante foi usado;
4. passos executados;
5. resultado esperado x obtido;
6. screenshots;
7. erros encontrados;
8. se o erro foi:
   - funcional;
   - permissao;
   - dado;
   - ambiente;
   - documentacao.

---

## 6. Resultado esperado desta bateria

Ao fim desse roteiro, o analista deve conseguir responder:

1. o login e a sessao estao corretos?
2. a navegacao principal esta coerente?
3. o sistema consegue gerar e publicar um programa novo?
4. a governanca do programa padrao funciona?
5. as integracoes e import/export estao operacionais?
6. o isolamento por assinante esta coerente?
7. o provisionamento de ambientes funciona?
8. a esteira de atualizacao esta controlada?
