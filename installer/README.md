# Instaladores compilados

Fonte dos instaladores Go do Construtor PG.

Manual completo:

- `../docs/manual-instalacao.md`

## Perfis

- `cmd/system-builder`: gera o instalador do Construtor de Sistemas.
- `cmd/subscriber`: gera o instalador do Assinante.

O perfil fica compilado no binario e nao aparece como opcao de tela/linha de comando.

## Build

Linux:

```bash
./build.sh
```

Windows PowerShell:

```powershell
.\build.ps1
```

Binarios previstos em `dist/`:

- `construtor-builder-installer-linux`
- `construtor-subscriber-installer-linux`
- `construtor-builder-installer.exe`
- `construtor-subscriber-installer.exe`

## Release operacional

Para gerar binarios, checksums e manifesto assinado:

```powershell
$env:APP_INSTALLER_ARTIFACT_SIGNING_KEY="chave-real"
..\scripts\installer\build-release.ps1 -PublicBaseUrl "https://downloads.seudominio.com.br/construtor"
```

Os arquivos ficam em `outputs/installer-artifacts`.

## Uso

Precheck Docker Linux:

```bash
./construtor-subscriber-installer-linux --precheck --mode=docker --subscriber-code=cliente-x --activation-url=https://central.exemplo
```

Instalacao Docker Linux:

```bash
./construtor-subscriber-installer-linux --mode=docker --subscriber-code=cliente-x --activation-url=https://central.exemplo
```

Teste local sem central real:

```bash
./construtor-subscriber-installer-linux --mode=native --subscriber-code=cliente-x --dev-signing-key=dev-local
```

Nesse modo, configure o backend local com a mesma chave em `APP_INSTALLATION_SESSION_SIGNING_KEY`.

## Fluxo resumido

1. executar `--precheck` no modo escolhido;
2. corrigir qualquer item com status `ERRO`;
3. ativar com codigo do assinante;
4. confirmar o codigo recebido por e-mail;
5. gravar a sessao local de instalacao;
6. abrir `production/install.html`;
7. concluir senha do instalador, admin inicial, assinante, migrations, seed, catalogo e integridade.

No SaaS, o orquestrador usa token interno e nao exige confirmacao manual por e-mail.

Windows e apenas para teste sem Docker.

## Licencas na central

Antes da ativacao, cadastre o assinante na central:

- tela: `production/app.html?screenId=admin.instalacao-licencas`
- tabela: `installer_activation_license`

O cadastro define e-mail de confirmacao, perfis permitidos, modos permitidos, validade, status e limite de ativacoes. O fallback por `APP_INSTALLER_ACTIVATION_SUBSCRIBERS` continua existindo apenas para transicao.

Para tokens SaaS, use:

```powershell
php ..\scripts\installer\generate-service-token.php
```

Cadastre o hash em `production/app.html?screenId=admin.instalacao-tokens`.
