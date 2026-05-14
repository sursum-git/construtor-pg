# Roteiro de Teste Web

Use este documento para validar as funcionalidades web disponíveis no projeto, tanto em demo quanto em runtime real.

## 1. Pre-requisitos

### Backend

No diretório `C:\construtor-pg\backend`:

```powershell
php bin\console doctrine:migrations:migrate --no-interaction
php bin\console app:seed-runtime-metadata
php bin\console messenger:consume async -vv
symfony server:start -d
```

### Frontend

No diretório `C:\construtor-pg`:

```powershell
$env:CRUD_ENGINE_API_PROXY='http://127.0.0.1:8000'
node scripts\serve-static.js
```

URLs esperadas:

- frontend: `http://127.0.0.1:8765`
- backend: `http://127.0.0.1:8000`

## 2. Credenciais e condições de teste

- usuário inicial criado pelo seed: `admin`
- senha inicial: `admin123`
- e-mail do usuário inicial: `admin@example.com`
- `subscriber.enabled=false` por padrão

Se quiser testar seleção de assinante:

1. abra `production/app.html?screenId=admin.parametros`
2. altere o parâmetro `subscriber.enabled` para `true`
3. faça logout/login novamente

## 3. Páginas principais

### Demo

- `file:///C:/construtor-pg/index.html`
- `file:///C:/construtor-pg/login.html`
- `file:///C:/construtor-pg/home.html`
- `file:///C:/construtor-pg/exemplos.html`
- `file:///C:/construtor-pg/theme-builder.html`
- `file:///C:/construtor-pg/examples/pages/processamento-parametros.html`

### Produção local

- `http://127.0.0.1:8765/production/login.html`
- `http://127.0.0.1:8765/production/home.html?screenId=home`
- `http://127.0.0.1:8765/production/app.html?screenId=cadastros.clientes`
- `http://127.0.0.1:8765/production/app.html?screenId=admin.jobs`
- `http://127.0.0.1:8765/production/app.html?screenId=admin.parametros`
- `http://127.0.0.1:8765/production/app.html?screenId=admin.sessoes`
- `http://127.0.0.1:8765/production/app.html?screenId=admin.transacoes`
- `http://127.0.0.1:8765/production/program-builder.html`

## 4. Checklist por fluxo

## 4.1 Login

### Demo

1. abrir `login.html`
2. validar usuário, senha, exibir/ocultar senha e checkbox de lembrar acesso
3. validar recuperação de senha
4. validar escolha de área para admin

### Produção

1. abrir `production/login.html`
2. entrar com `admin / admin123`
3. validar redirecionamento para Home
4. repetir com `manter logado` marcado
5. fechar e abrir a tela novamente para validar `/api/auth/remember`
6. validar recuperação de senha

Resultado esperado:

- login cria sessão
- manter logado grava token local e reabre sessão
- recuperação de senha responde sem erro

## 4.2 Home

1. abrir `production/home.html?screenId=home`
2. validar appbar, menu lateral e abertura de programas
3. abrir um CRUD pelo menu
4. abrir um `process` pelo menu
5. validar troca de tema
6. validar badge de assinante quando `subscriber.enabled=true`

Resultado esperado:

- a Home abre programas `crud` e `process`
- não há erro de carregamento de tela por `screenId`

## 4.3 CRUD de clientes

Tela:

- `production/app.html?screenId=cadastros.clientes`

Passos:

1. carregar grid
2. ordenar colunas
3. abrir filtro
4. aplicar filtro e remover filtro aplicado
5. agrupar
6. abrir formulário
7. criar registro
8. editar registro
9. validar abas/etapas se existirem
10. testar exportação
11. testar ação manual que enfileira job, se disponível

Resultado esperado:

- grid responde com dados
- filtros e agrupamentos refletem no resultado
- create/update persistem
- ações mostram mensagens Kendo, sem `alert` nativo

## 4.4 Processamento por parâmetros

Tela:

- `production/home.html?screenId=home`
- programa: `processamento.relatorio-clientes`

Passos:

1. abrir a tela de processamento
2. preencher parâmetros
3. executar processamento
4. validar mensagem de início
5. validar atualização de status
6. validar retorno final
7. abrir relatório gerado

Resultado esperado:

- o endpoint `process` inicia corretamente
- o endpoint `status` acompanha o job
- o relatório abre por documento seguro runtime

## 4.5 Áreas administrativas runtime

Telas:

- `admin.parametros`
- `admin.parametro-valores`
- `admin.listas-opcoes`
- `admin.opcoes`
- `admin.sessoes`
- `admin.transacoes`
- `admin.logs-transacoes`
- `admin.jobs`

Passos mínimos:

1. abrir cada tela por `production/app.html?screenId=...`
2. validar carga do grid
3. abrir um registro
4. validar paginação, ordenação e filtros
5. em `admin.sessoes`, validar derrubada de sessão, se disponível
6. em `admin.jobs`, validar jobs enfileirados e concluídos

Resultado esperado:

- todas as telas carregam por `screenId`
- não há fallback para JSON local

## 4.6 Construtor visual de programas

Tela:

- `http://127.0.0.1:8765/production/program-builder.html`

### Módulos estruturais

1. criar módulo com:
   - código
   - nome
   - abreviação
   - número inicial/final
2. tentar repetir abreviação
3. tentar sobrepor faixa

Resultado esperado:

- salva quando a faixa é válida
- bloqueia abreviação duplicada
- bloqueia sobreposição de faixa

### Entidade

1. criar entidade `persistence`
2. escolher módulo estrutural
3. informar número base
4. usar `Sugerir tabela`
5. cadastrar campos
6. salvar entidade
7. alterar entidade e salvar nova revisão
8. restaurar revisão anterior

Resultado esperado:

- tabela física criada
- nome estrutural validado
- revisão da entidade aparece no histórico
- rollback restaura metadados e estrutura gerenciada

### Recursos avançados da entidade

Validar ao menos uma vez cada item:

- campo `custom_code`
- assistente por `screenId`
- `previewCode`
- `uniqueKeys`
- campo `readonly`
- FK com `dependencyType`, `onDelete`, `onUpdate`
- regras de negócio declarativas
- regras por `classe + método`
- cadastro mestre versionado
- assistente de histórico `*_version_id` e `*_historico`

### Programa

1. informar código manual do programa
2. escolher módulo
3. tentar código incompatível com a abreviação do módulo
4. tentar código fora da faixa do módulo
5. informar código válido
6. gerar preview
7. salvar rascunho
8. publicar
9. duplicar versão

Resultado esperado:

- código incompatível é bloqueado
- código fora da faixa é bloqueado
- código válido salva e publica
- histórico do programa aparece no grid

## 4.7 Recuperação de senha

1. abrir `production/login.html`
2. acionar recuperação
3. solicitar token
4. redefinir senha
5. autenticar com a nova senha

Resultado esperado:

- o backend aceita a solicitação
- em dev, o token pode voltar na resposta
- a nova senha passa a autenticar

## 4.8 Lembrar acesso

1. autenticar com `manter logado`
2. fechar a aba
3. abrir `production/login.html` novamente
4. validar reentrada automática

Resultado esperado:

- `/api/auth/remember` reabre a sessão

## 5. Itens de verificação visual

Em todas as telas:

- textos em pt-BR
- sem `alert`, `confirm` ou `prompt` nativos
- janelas e mensagens via Kendo
- sem sobreposição quebrada de layout
- grids e formulários responsivos
- sem erro JavaScript no console

## 6. Evidências recomendadas

Para cada fluxo principal, registrar:

- URL testada
- usuário usado
- parâmetros relevantes
- resultado esperado
- resultado obtido
- print ou vídeo curto quando houver falha visual

## 7. Ordem mínima recomendada

Se quiser um ciclo rápido de regressão:

1. `production/login.html`
2. `production/home.html?screenId=home`
3. `production/app.html?screenId=cadastros.clientes`
4. processo `processamento.relatorio-clientes`
5. `production/app.html?screenId=admin.jobs`
6. `production/program-builder.html`

## 8. Observações

- para `process`, manter o worker `messenger:consume async` ativo
- para `program-builder`, manter migrations e seed em dia
- para seleção de assinante, ativar `subscriber.enabled=true`
- para e-mail real, configurar `MAILER_DSN`
