# Checklist de producao da instalacao

## 1. Central real

- Definir dominio HTTPS da central.
- Configurar SMTP real em `MAILER_DSN`.
- Configurar remetente real em `APP_INSTALLER_ACTIVATION_FROM`.
- Gerar chaves fortes para ativacao, sessao local e artefatos.
- Configurar bloqueio de tentativas do codigo de e-mail:
  - `APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS=5`
  - `APP_INSTALLER_ACTIVATION_BLOCK_MINUTES=30`
- Rodar:

```powershell
php scripts\installer\validate-central-config.php
```

## 2. Cadastros reais

- Cadastrar assinantes em `admin.instalacao-licencas`.
- Cadastrar tokens SaaS em `admin.instalacao-tokens`.
- Revisar o painel `admin.central-operacoes` antes de liberar a central:
  - licencas ativas;
  - tokens ativos;
  - chaves configuradas;
  - artefatos publicados;
  - alertas e notificacoes derivadas;
  - saude dos assinantes.
- Gerar token SaaS com:

```powershell
php scripts\installer\generate-service-token.php
```

- Guardar o token em cofre seguro e cadastrar apenas o hash.
- Definir `maxHosts`, `allowedFingerprints` e `revokedFingerprints` quando aplicavel.

## 3. Publicacao de artefatos

- Gerar binarios finais.
- Publicar manifesto, Compose e checksums:

```powershell
$env:APP_INSTALLER_ARTIFACT_SIGNING_KEY="chave-real"
.\scripts\installer\build-release.ps1 -PublicBaseUrl "https://downloads.seudominio.com.br/construtor"
```

- Enviar o conteudo de `outputs/installer-artifacts` para o storage/CDN definido.
- Atualizar `APP_INSTALLER_MANIFEST_URL` e `APP_INSTALLER_DOCKER_COMPOSE_URL`.

## 4. Testes fora da maquina local

- Linux Docker on-premise: precheck, ativacao por e-mail, download assinado, subida da stack e instalacao web.
- Linux nativo: precheck de PHP/Composer/PostgreSQL client, download assinado e instalacao web.
- Docker SaaS: ativacao por token interno e provisionamento sem e-mail.
- Reinstalacao: nova ativacao, senha do instalador, politica de backup e confirmacao.
- Casos negativos: token invalido, codigo expirado, excesso de tentativas, fingerprint revogado, porta ocupada, Docker ausente.

## 5. Distribuicao dos executaveis

- Publicar binarios apenas em canal controlado.
- Divulgar SHA-256 de cada binario.
- Versionar pacote e manifesto.
- Nao enviar chaves em ZIP, e-mail ou repositório.

## 6. Endurecimento continuo

- Rotacionar chaves periodicamente.
- Revogar tokens antigos.
- Revisar `metadata.activationHistory`, `metadata.fingerprints` e `metadata.usageHistory`.
- Revisar `metadata.auditTrail` e `metadata.revocationHistory` em `admin.central-operacoes`.
- Registrar incidentes e fingerprints bloqueados.
- Monitorar excesso de tentativas e falhas de ativacao.
