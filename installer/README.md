# Instaladores compilados

Fonte dos instaladores Go do Construtor PG.

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
