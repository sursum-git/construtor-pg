# Empresas — Scriptcase para o construtor

Esta integração usa o frontend existente, Kendo/jQuery e JSON do Program Builder.

- Programa: `cd0090`; tela: `ciw.empresas`; entidade: `empresas_progress`.
- `builder-draft.json`: entidade API e programa; não cria tabela física PostgreSQL.
- `api-source.json`: fonte reutilizável com cinco operações POST e contrato `sursum-programs`.
- Banco de negócio: Progress `espec`, tabela `PUB.empresas`.

`__RUNTIME_SECRET__` é um marcador. O importador injeta uma credencial aleatória em `authHeaders.X-Empresas-Token`; nunca salvar uma credencial real neste arquivo. A API compara seu SHA-256 com a configuração privada publicada. O corpo enviado ao PASOE contém apenas operação, ID e campos do cadastro.

A instalação atual fica em `D:\opencode\construtor-pg`; serviços e ferramentas de implantação ficam em `D:\opencode\conversao-sc\tools\construtor-local`. Documentação e evidências: `D:\opencode\conversao-sc\docs\integracao-empresas-validacao.md`.

Após login em `http://127.0.0.1:8765/production/login.html`, abrir `production/app.html?screenId=ciw.empresas`. O túnel SSH precisa estar ativo; reconectar com `tunnel.ps1`. O ambiente local usa o conector 9910 da mesma instância `sursum-api/med`, através do loopback SSH. O HTTPS 9911 não foi validado nesta instalação.

Importação inicial pelo fluxo oficial:

```powershell
& D:\opencode\conversao-sc\tools\construtor-local\import-empresas.ps1 -ValidateOnly
# Publicar somente depois de compilar e publicar a API Progress 12.2:
& D:\opencode\conversao-sc\tools\construtor-local\import-empresas.ps1
```

Não repetir a criação se `cd0090` já existir. `-RefreshBindingOnly` atualiza a configuração da entidade pelo fluxo governado; `-RotateToken` também gera nova credencial e exige publicar seu novo hash no PASOE. A atualização da fonte reutilizável requer atualizar a entidade para renovar o snapshot consumido pelo runtime.

Limites confirmados: ID gerado e somente leitura; nome opcional com até 50 caracteres; códigos EMS2/EMS5 inteiros opcionais. A exclusão verifica as tabelas relacionadas. A API rejeita nomes de tabela/campo/operação fora do contrato.

O PHP traduz o envelope `error` do Progress, inclusive HTTP 200, em erro do runtime. Fontes genéricas mantêm seu contrato anterior. Recusas incluem `success:false` para a auditoria PASOE.

Consulta, formulários e CRUD pelo PHP foram verificados com dados reais. Após autorização explícita do usuário em 15/10/2026, quatro inclusões simultâneas também passaram com IDs distintos. Todos os registros de teste foram removidos, e as empresas preexistentes permaneceram inalteradas.
