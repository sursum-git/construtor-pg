# Manual operacional do modulo regulado

Este documento resume como operar a trilha `regulated_document`.

## Objetivo

Usar uma camada separada para documentos de alto rigor, com:

- preparo;
- renderizacao;
- emissao;
- hash;
- artefato;
- conferencia posterior.

Ela nao substitui `reports` nem `special_document`.

## Quando usar

Use `regulated_document` quando o documento precisar de:

- ciclo proprio de emissao;
- hash de autenticidade;
- artefato salvo;
- conferencia futura;
- storage separado;
- observabilidade e trilha administrativa.

## O que ainda nao promete

Esta etapa ainda nao entrega:

- DANFE oficial homologado;
- boleto homologado por banco;
- etiqueta industrial certificada;
- engine fiscal/bancaria final.

Hoje a trilha esta em nivel `quase homologado`.

## Ativacao do ambiente

Habilite no backend local ou no ambiente de producao:

```env
REGULATED_DOCUMENT_ENABLED=1
REGULATED_DOCUMENT_DATABASE_URL=sqlite:///C:/construtor-pg/backend/var/regulated_document.sqlite
```

## Comandos principais

### Setup inicial

```powershell
php backend/bin/console app:regulated-document:setup
```

O comando:

- inicializa o storage separado;
- executa o seed runtime.

### Gerar emissoes de exemplo

```powershell
php backend/bin/console app:regulated-document:seed-samples
```

Gera emissoes reais para:

- `documentos.regulados-fiscal-base`
- `documentos.regulados-bancario-base`
- `documentos.regulados-logistico-base`

### Bootstrap documental unico

```powershell
php backend/bin/console app:regulated-document:bootstrap-demo
```

Esse comando encadeia:

- setup;
- seed runtime;
- seed de emissoes exemplo.

### Limpeza operacional

```powershell
php backend/bin/console app:regulated-document:cleanup --apply
```

Use para expurgar payload, artefatos e eventos vencidos pela politica de retencao.

## Permissoes administrativas

Permissoes dedicadas da frente:

- `regulated_document.admin.read`
- `regulated_document.admin.artifact`

## Telas principais

### Operacional

- `production/app.html?screenId=documentos.regulados-fiscal-base`
- `production/app.html?screenId=documentos.regulados-bancario-base`
- `production/app.html?screenId=documentos.regulados-logistico-base`

### Administrativa

- `production/app.html?screenId=admin.documentos-regulados`
- `production/admin/regulated-document-admin.html`

### Conferencia publica

- `production/regulated-document-authenticity.html`

## Trilha concreta priorizada

A primeira trilha concreta priorizada e:

- `fiscal`

As trilhas:

- `banking`
- `logistics`

continuam apoiadas pela base geral.

## Operacao recomendada

1. Habilitar o modulo no ambiente.
2. Rodar `app:regulated-document:setup`.
3. Rodar `app:regulated-document:seed-samples` quando quiser validar a trilha com dados reais.
4. Abrir a tela administrativa e verificar:
   - observabilidade;
   - estados;
   - timeline;
   - artefatos;
   - conferencia.
5. Manter a limpeza recorrente ativa.

## Agendamento

Existe automacao diaria para limpeza operacional do modulo regulado.

Ela executa:

```powershell
php backend/bin/console app:regulated-document:cleanup --apply
```

## Limites atuais

- sem engine homologada final;
- sem layout livre;
- sem HTML livre;
- sem JavaScript vindo do metadado;
- sem renderer externo nesta fase.
