# Runbook curto do modulo regulado

## Ativar

Definir no ambiente:

```env
REGULATED_DOCUMENT_ENABLED=1
REGULATED_DOCUMENT_DATABASE_URL=...
```

## Preparar

```powershell
php backend/bin/console app:regulated-document:setup
```

## Popular com exemplos

```powershell
php backend/bin/console app:regulated-document:bootstrap-demo
```

## Verificar

Abrir:

- `production/app.html?screenId=admin.documentos-regulados`
- `production/regulated-document-authenticity.html`

Conferir:

- estados;
- hash;
- timeline;
- artefato.

## Limpar

```powershell
php backend/bin/console app:regulated-document:cleanup --apply
```

## Recuperar artefato

Usar a tela administrativa `admin.documentos-regulados` e o botao `Baixar artefato`.

## Prioridade atual

- trilha concreta priorizada: `fiscal`
- `banking` e `logistics` continuam na base geral
