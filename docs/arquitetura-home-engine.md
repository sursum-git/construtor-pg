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
- Abre telas de processamento por parametros com `ProcessEngine`.
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

`process`

- Instancia `ProcessEngine` dentro da area central.
- Uso esperado: rotinas por parametros, processamento assincrono, geracao de relatorio e jobs em segundo plano.
- Recebe a configuracao de tema da Home.
- O cabecalho interno fica oculto; titulo, versao e subtitulo sao exibidos na appbar global da Home.
- Em producao deve preferir `screenId`; `definitionUrl` fica restrito a demo.

## JSON principal

Niveis importantes:

- `app`: titulo, subtitulo, versao e logo da empresa.
- `currentUser`: usuario logado exibido no menu da appbar global.
- `currentSubscriber`: assinante/tenant corrente exibido no cabecalho global quando informado.
- `permissions`: permissoes visuais.
- `layout`: programa inicial, Kendo TreeView lateral, appbar e menu do usuario.
- `layout.appbar.showSidebarToggle`: exibe o botao de expandir/recolher o menu lateral quando o menu for recolhivel.
- `layout.appbar.showCurrentSubscriber`: exibe ou oculta o assinante corrente quando `currentSubscriber` estiver informado.
- `layout.appbar.subscriberSwitch`: habilita a troca de assinante pelo badge do cabecalho, podendo informar `programId`, `url` e endpoint `endpoints.change`.
- Em producao, `layout.appbar.subscriberSwitch.endpoints.change` deve usar `endpointId` ou `actionId`; URL livre fica restrita a demo/ambiente controlado.
- `layout.appbar.chat`: habilita o botao de chat no appbar global. O chat e entre usuarios do sistema, usa ComboBox de destinatarios e informa endpoints `contacts`, `history`, `send` e opcionalmente `events`.
- `layout.appbar.support`: habilita o botao de atendimento no appbar global. O usuario escolhe o setor; se houver atendente online naquele setor, abre chat; se nao houver, abre formulario de solicitacao com o setor travado. O bloco tambem aceita `events` para presenca, mensagens e atualizacao de protocolo.
- Ao abrir o atendimento, o `HomeEngine` captura o programa corrente e envia no contexto das chamadas de suporte (`programId`, `programCode`, `programTitle`, `programScreenId`, `programType`, `moduleId` e `currentProgram`).
- No backend atual, o chat entre usuarios e o chat de suporte usam `runtime_user_message` como historico/persistencia leve. Solicitacoes de suporte ficam em `runtime_support_request`.
- O envio de mensagem continua em `POST`, mas o recebimento em tempo quase real pode usar SSE proprio por conversa/atendimento.
- `layout.appbar.aiChat`: habilita outro botao no appbar global para chat de IA, sem selecao de destinatario, com endpoints `history` e `send`.
- `layout.appbar.alerts`: habilita o botao de sino no appbar global e informa endpoint `list` para alertas de informacoes recebidas.
- `layout.appbar.requests`: habilita o botao de solicitacoes no appbar global e informa endpoint `list` para solicitacoes recebidas ou atualizadas.
- `layout.appbar.jobs`: habilita o botao de jobs concluidos no appbar global, informa endpoint `list` e pode apontar `programId` para a pagina "Meus Jobs".
- `layout.appbar.runtimeMessages`: habilita polling de mensagens/interceptacoes do runtime, incluindo pedido de saida e derrubada de sessao.
- `navigation.modules`: sistemas/modulos disponiveis no ComboBox acima do menu; o motor acrescenta a opcao interna `Todos`.
- `navigation.initialModuleId`: modulo inicial selecionado; se ausente ou em branco, exibe `Todos` ao abrir.
- `initialProgramId` pode ser passado pelo bootstrap para sobrescrever o programa inicial da Home, por exemplo para abrir a area administrativa apos login.
- `navigation.groups`: grupos e itens do menu.
- `navigation.groups[].moduleId`: vincula um grupo da TreeView ao sistema/modulo selecionado.
- `navigation.groups[].items[].favorite`: marca programa como favorito para o filtro e para o estado do botao da pagina corrente; o item do menu nao exibe marcador visual.
- `currentUser.favoritePrograms`: lista de programas favoritos do usuario.
- `currentUser.unfavoritePrograms`: lista de favoritos removidos pelo usuario quando houver favorito inicial no JSON.
- `availableSubscribers`: lista opcional de assinantes/tenants disponiveis para troca.
- `programs`: programas disponiveis e seus modos de abertura.
- `programs[].openUrl`: URL navegavel preferencial usada no botao "Abrir em nova aba" de cada item do menu; se ausente, o motor tenta `url` ou `htmlUrl`; nao deve ser confundida com `definitionUrl`.

Ao clicar em um programa do menu para abrir na area central, o `HomeEngine` recolhe o menu lateral quando `layout.sidebar.collapsible` nao for `false`.
Em viewport mobile (`max-width: 860px`), o menu lateral inicia recolhido mesmo quando `layout.sidebar.collapsed=false`.
Antes de trocar de programa, a Home consulta o CRUD atual; se houver formulario alterado e nao salvo, exibe confirmacao Kendo para evitar perda de dados.

## Interface publica

```js
new HomeEngine({
  root,
  screenId,
  definitionUrl,
  definition,
  config,
  configUrl,
  initialProgramId,
  httpClient
}).init()
```

## Regras de seguranca

- Em producao, preferir `screenId` para carregar a Home.
- `production/home.html` e a entrada separada para producao; `home.html` continua sendo demo.
- Programas `crud` abertos pela Home devem preferir `screenId` em vez de `definitionUrl`.
- Programas `process` abertos pela Home tambem devem preferir `screenId` em vez de `definitionUrl`.
- Endpoints de chat, atendimento, IA, alertas e solicitacoes podem usar `endpointId` ou `actionId`, resolvidos pelo gateway runtime.
- `home.chat.contacts` lista usuarios ativos a partir das sessoes runtime.
- `home.chat.history` e `home.chat.send` persistem conversa simples entre usuarios.
- `home.chat.events` expõe SSE proprio por conversa, enviando apenas mensagens novas a partir de `afterId`.
- `home.support.onlineUsers` lista atendentes online por setor usando sessoes ativas e grupos `support` ou `support.<setor>`.
- `home.support.history` e `home.support.send` persistem o atendimento em `runtime_user_message` com canal proprio.
- `home.support.createRequest` grava protocolo em `runtime_support_request`.
- `home.support.requestStatus` consulta o ultimo protocolo do usuario ou um protocolo especifico.
- `home.support.events` expõe SSE proprio para presenca online, novas mensagens do atendimento e evolucao do protocolo da solicitacao.
- Endpoints da troca de assinante seguem a mesma regra dos demais endpoints da appbar em producao.
- Endpoint de jobs do appbar e endpoints de processamento devem usar `endpointId` ou `actionId` em producao.
- Nao aceitar JavaScript vindo do JSON.
- Nao aceitar `template` livre.
- Nao executar `eval`.
- HTML do tipo `html` deve passar por sanitizacao antes de entrar no DOM.
- Permissao visual no frontend nao substitui validacao no backend.
- `currentSubscriber` e `availableSubscribers` sao apenas informativos na interface; o backend continua responsavel por validar tenant/assinante em todas as chamadas e na troca via `endpoints.change`.
