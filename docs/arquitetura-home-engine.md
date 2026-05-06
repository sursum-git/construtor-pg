# Arquitetura do Home Engine

O Home Engine monta a pagina inicial a partir de JSON recuperado do backend ou de arquivo local na demo.

Ideia central:

```text
Backend decide a navegacao.
Frontend renderiza apenas modos permitidos.
```

## Estrutura

```text
src/home-engine/
  HomeEngine.js
  HomeDefinitionLoader.js
  HomeDefinitionValidator.js

examples/home.home.json
public/metadata/schemas/home-definition-v1.schema.json
home.html
production/home.html
```

## Responsabilidades

`HomeEngine.js`

- Renderiza o shell global da aplicacao.
- Monta appbar superior.
- Monta menu lateral com Kendo TreeView.
- O menu lateral usa `kendo.data.HierarchicalDataSource` como fonte do TreeView.
- Monta seletor de sistema/modulo com Kendo ComboBox acima da TreeView.
- Permite filtrar programas por nome e mostrar apenas favoritos.
- Permite favoritar/desfavoritar o programa corrente pela appbar global.
- Abre programas na area central.
- Reutiliza tema global do CRUD Engine.
- Controla o tema claro/escuro no appbar global; CRUD embutido nao renderiza outro seletor de tema.

`HomeDefinitionLoader.js`

- Carrega o JSON da home por URL ou objeto informado.
- Usa o mesmo contrato de `httpClient.request({ url, method, data })`.
- Em modo de producao, tambem pode carregar por `screenId`, bloqueando `definition` direto e `definitionUrl` livre.

`HomeDefinitionValidator.js`

- Valida `pageType=home`.
- Valida programas, menu, permissoes e URLs.
- Bloqueia chaves inseguras como `template`, `eval`, `script` e handlers.
- Bloqueia HTML com `<script>`, eventos inline e `javascript:`.

## Tipos de programa

`iframe`

- Abre uma URL em iframe.
- Uso esperado: paginas legadas ou programas isolados.
- Quando a Home tem seletor de tema, a URL recebe `hideThemeSwitch=1`; paginas compativeis devem esconder o seletor local.
- A URL recebe `hideProgramHeader=1`; paginas compativeis devem esconder o cabecalho local para evitar duplicidade.
- A Home envia mudancas de tema ao iframe por `postMessage` com `type="homeThemeChange"`.

`crud`

- Instancia `CrudEngine` dentro da area central.
- Uso esperado: telas novas baseadas no contrato CRUD.
- Recebe a configuracao de tema da Home, mas com `theme.allowUserSwitch=false` para evitar controle duplicado.
- O cabecalho interno do CRUD fica oculto; titulo, versao, subtitulo, ultima atualizacao, ajuda e logs sao exibidos na appbar global da Home.

`html`

- Injeta HTML controlado e sanitizado.
- Uso esperado: paineis simples, dashboards estaticos ou fragmentos sem JavaScript.
- Nao executa script vindo do JSON.

## JSON principal

Niveis importantes:

- `app`: titulo, subtitulo, versao e logo da empresa.
- `currentUser`: usuario logado exibido no menu da appbar global.
- `currentSubscriber`: assinante/tenant corrente exibido no cabecalho global quando informado.
- `permissions`: permissoes visuais.
- `layout`: programa inicial, Kendo TreeView lateral, appbar e menu do usuario.
- `layout.appbar.showSidebarToggle`: exibe o botao de expandir/recolher o menu lateral quando o menu for recolhivel.
- `layout.appbar.showCurrentSubscriber`: exibe ou oculta o assinante corrente quando `currentSubscriber` estiver informado.
- `layout.appbar.chat`: habilita o botao de chat no appbar global. O chat e entre usuarios do sistema, usa ComboBox de destinatarios e informa endpoints `contacts`, `history` e `send`.
- `layout.appbar.support`: habilita o botao de atendimento no appbar global. O usuario escolhe o setor; se houver atendente online naquele setor, abre chat; se nao houver, abre formulario de solicitacao com o setor travado.
- `layout.appbar.aiChat`: habilita outro botao no appbar global para chat de IA, sem selecao de destinatario, com endpoints `history` e `send`.
- `layout.appbar.alerts`: habilita o botao de sino no appbar global e informa endpoint `list` para alertas de informacoes recebidas.
- `layout.appbar.requests`: habilita o botao de solicitacoes no appbar global e informa endpoint `list` para solicitacoes recebidas ou atualizadas.
- `navigation.modules`: sistemas/modulos disponiveis no ComboBox acima do menu; o motor acrescenta a opcao interna `Todos`.
- `navigation.initialModuleId`: modulo inicial selecionado; se ausente ou em branco, exibe `Todos` ao abrir.
- `navigation.groups`: grupos e itens do menu.
- `navigation.groups[].moduleId`: vincula um grupo da TreeView ao sistema/modulo selecionado.
- `navigation.groups[].items[].favorite`: marca programa como favorito para o filtro e para o estado do botao da pagina corrente; o item do menu nao exibe marcador visual.
- `currentUser.favoritePrograms`: lista de programas favoritos do usuario.
- `currentUser.unfavoritePrograms`: lista de favoritos removidos pelo usuario quando houver favorito inicial no JSON.
- `programs`: programas disponiveis e seus modos de abertura.
- `programs[].openUrl`: URL navegavel preferencial usada no botao "Abrir em nova aba" de cada item do menu; se ausente, o motor tenta `url` ou `htmlUrl`; nao deve ser confundida com `definitionUrl`.

Ao clicar em um programa do menu para abrir na area central, o `HomeEngine` recolhe o menu lateral quando `layout.sidebar.collapsible` nao for `false`.
Em viewport mobile (`max-width: 860px`), o menu lateral inicia recolhido mesmo quando `layout.sidebar.collapsed=false`.

## Interface publica

```js
new HomeEngine({
  root,
  screenId,
  definitionUrl,
  definition,
  config,
  configUrl,
  httpClient
}).init()
```

## Regras de seguranca

- Em producao, preferir `screenId` para carregar a Home.
- `production/home.html` e a entrada separada para producao; `home.html` continua sendo demo.
- Programas `crud` abertos pela Home devem preferir `screenId` em vez de `definitionUrl`.
- Endpoints de chat, atendimento, IA, alertas e solicitacoes podem usar `endpointId` ou `actionId`, resolvidos pelo gateway runtime.
- Nao aceitar JavaScript vindo do JSON.
- Nao aceitar `template` livre.
- Nao executar `eval`.
- HTML do tipo `html` deve passar por sanitizacao antes de entrar no DOM.
- Permissao visual no frontend nao substitui validacao no backend.
- `currentSubscriber` e apenas informativo na interface; o backend continua responsavel por validar tenant/assinante em todas as chamadas.
