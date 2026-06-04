# Padroes CSS para projetos com Kendo UI

Este guia resume o padrao visual usado neste projeto para servir como base em outro sistema com Kendo UI for jQuery.

## Stack visual

- Usar Kendo UI for jQuery e jQuery locais.
- Carregar primeiro o CSS do Kendo e depois o CSS da aplicacao.
- Manter um arquivo de tema proprio da aplicacao para sobrescrever Kendo de forma controlada.
- Evitar alterar arquivos dentro de `kendo/`.
- Usar HTML simples quando o projeto nao tiver build.
- Manter mensagens, labels e textos em pt-BR.

Ordem recomendada:

```html
<link rel="stylesheet" href="kendo/styles/default-urban.css">
<link rel="stylesheet" href="src/styles/app-theme.css">
```

## Tokens de tema

O projeto usa variaveis CSS semanticas. A aplicacao deve trocar cores pelo token, nao por seletores isolados.

```css
:root {
  --app-bg: #f4f5f7;
  --app-surface: #ffffff;
  --app-border: #d9dee7;
  --app-text: #1f2937;
  --app-muted: #667085;
  --app-title: #0f4f8f;
  --app-accent: #0d6efd;
  --app-accent-soft: #eef6ff;
  --app-accent-border: #bfdaf8;
  --app-input-bg: #ffffff;
  --app-readonly-bg: #f8fafc;
  --app-error-bg: #fff5f5;
  --app-error-border: #f4c7c3;
  --app-button-bg: #ffffff;
  --app-button-hover-bg: #f8fafc;
  --app-button-border: #cfd5df;
  --app-button-text: #344054;
  --app-button-primary-bg: #0f5f9f;
  --app-button-primary-hover-bg: #0b4f8a;
  --app-button-primary-text: #ffffff;
  --app-danger: #b42318;
  --app-shadow: rgba(15, 23, 42, 0.08);
}

body[data-app-theme="dark"] {
  --app-bg: #111827;
  --app-surface: #182230;
  --app-border: #344054;
  --app-text: #f2f4f7;
  --app-muted: #98a2b3;
  --app-title: #8ec5ff;
  --app-accent: #4da3ff;
  --app-accent-soft: #102a43;
  --app-accent-border: #275f9f;
  --app-input-bg: #101828;
  --app-readonly-bg: #101828;
  --app-error-bg: #2a1210;
  --app-error-border: #7a271a;
  --app-button-bg: #202c3d;
  --app-button-hover-bg: #28384d;
  --app-button-border: #42526a;
  --app-button-text: #f2f4f7;
  --app-button-primary-bg: #2f7ec8;
  --app-button-primary-hover-bg: #3b8fdf;
  --app-button-primary-text: #ffffff;
  --app-danger: #ffb4ab;
  --app-shadow: rgba(0, 0, 0, 0.35);
}
```

## Base da pagina

```css
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  background: var(--app-bg);
  color: var(--app-text);
  font-family: Arial, Helvetica, sans-serif;
  font-size: 14px;
}

.app-shell {
  width: min(1440px, 100%);
  margin: 0 auto;
  padding: 20px;
}

.app-screen {
  display: grid;
  gap: 14px;
}
```

## Layout e paineis

- Usar `display: grid` para telas, formularios e blocos verticais.
- Usar `display: flex` para toolbars e grupos de botoes.
- Usar `min-width: 0` em colunas flex/grid que recebem texto dinamico.
- Usar `overflow: hidden`, `text-overflow: ellipsis` e `white-space: nowrap` em titulos compactos.
- Paineis de tela usam borda leve, fundo branco, raio `6px` e sombra sutil.
- Evitar cartoes dentro de cartoes.
- Usar raio ate `8px`; reservar `999px` apenas para badges, chips e toggles.

```css
.app-panel {
  background: var(--app-surface);
  border: 1px solid var(--app-border);
  border-radius: 6px;
  box-shadow: 0 1px 2px var(--app-shadow);
}

.app-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
  justify-content: space-between;
  padding: 10px;
}

.app-toolbar-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
}
```

## Tipografia

- Fonte base: `Arial, Helvetica, sans-serif`.
- Texto base: `14px`.
- Titulos de tela: `20px` a `24px`, peso `700`, cor `--app-title`.
- Subtitulos e metadados: `12px` a `13px`, cor `--app-muted`.
- Labels de formulario: `12px` ou `13px`, peso `700`.
- Nao usar tamanho de fonte baseado em viewport.
- Nao usar letter-spacing negativo.

```css
.app-title {
  margin: 0;
  color: var(--app-title);
  font-size: 24px;
  line-height: 1.2;
  font-weight: 700;
}

.app-muted {
  color: var(--app-muted);
  font-size: 13px;
  line-height: 1.4;
}
```

## Botoes Kendo

- Usar `kendoButton` para acoes.
- Botoes primarios devem usar o tema primario do Kendo com override por token.
- Botoes de icone devem ter dimensao fixa e tooltip.
- Em botao somente icone, esconder `.k-button-text`.
- Acoes destrutivas precisam ser visualmente diferentes de editar/visualizar.
- Nao usar `alert`, `confirm` ou `prompt`; usar Kendo Window/Dialog/Notification.

```css
.app-icon-button.k-button {
  width: 32px;
  min-width: 32px;
  height: 32px;
  padding-inline: 0;
}

.app-icon-button .k-button-text {
  display: none;
}

body .k-button-solid-base {
  border-color: var(--app-button-border);
  background-color: var(--app-button-bg);
  color: var(--app-button-text);
}

body .k-button-solid-base:hover,
body .k-button-solid-base.k-hover {
  border-color: var(--app-button-border);
  background-color: var(--app-button-hover-bg);
  color: var(--app-button-text);
}

body .k-button-solid-primary {
  border-color: var(--app-button-primary-bg);
  background-color: var(--app-button-primary-bg);
  color: var(--app-button-primary-text);
}

body .k-button-solid-primary:hover,
body .k-button-solid-primary.k-hover {
  border-color: var(--app-button-primary-hover-bg);
  background-color: var(--app-button-primary-hover-bg);
  color: var(--app-button-primary-text);
}

body .k-button:disabled,
body .k-button.k-disabled {
  opacity: 0.58;
}
```

## Formularios

- Labels ficam acima dos campos.
- Campos usam largura total.
- Formularios em popup usam Kendo Window.
- Cabecalho e rodape do formulario funcionam como appbar.
- Botoes do rodape ficam alinhados a esquerda.
- Campos somente leitura devem ter fundo proprio e ainda parecer campo.
- Erros devem marcar o campo e exibir mensagem proxima.

```css
.app-form {
  display: grid;
  gap: 14px;
}

.app-field {
  display: grid;
  gap: 6px;
}

.app-field label {
  color: var(--app-muted);
  font-size: 12px;
  font-weight: 700;
}

.app-field.is-required label::after {
  content: " *";
  color: var(--app-danger);
}

.app-readonly-value {
  display: flex;
  width: 100%;
  min-height: 34px;
  align-items: center;
  padding: 7px 10px;
  border: 1px solid var(--app-border);
  border-radius: 4px;
  background: var(--app-readonly-bg);
  color: var(--app-text);
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.app-field-error {
  padding: 8px;
  border: 1px solid var(--app-danger);
  border-radius: 6px;
  background: var(--app-error-bg);
}

.app-validation-message {
  color: var(--app-danger);
  font-size: 12px;
  font-weight: 600;
  line-height: 1.35;
}
```

## Grid Kendo

- Usar Kendo Grid real.
- Coluna de acoes deve ser a primeira.
- Acoes de linha devem ser compactas.
- Hover e selecao usam `--app-accent-soft`, sem cores fortes.
- Linhas alternadas usam `--app-readonly-bg`.
- Congelamento de coluna e recursos densos devem ser tratados como desktop.

```css
body .k-grid,
body .k-grid .k-grid-aria-root,
body .k-grid .k-grid-container,
body .k-grid .k-grid-header,
body .k-grid .k-grid-header-wrap,
body .k-grid .k-grid-content,
body .k-grid .k-grid-pager,
body .k-pager,
body .k-window,
body .k-window-titlebar,
body .k-popup,
body .k-list-container,
body .k-menu-popup,
body .k-column-menu,
body .k-tabstrip,
body .k-content {
  border-color: var(--app-border) !important;
  background-color: var(--app-surface) !important;
  color: var(--app-text) !important;
}

body .k-grid .k-table,
body .k-grid .k-table-row,
body .k-grid .k-table-th,
body .k-grid .k-header,
body .k-grid th,
body .k-grid td,
body .k-grid .k-table-td {
  border-color: var(--app-border) !important;
  background-color: var(--app-surface) !important;
  color: var(--app-text) !important;
}

body .k-grid .k-table-alt-row,
body .k-grid .k-alt {
  background-color: var(--app-readonly-bg) !important;
}

body .k-grid tr:hover,
body .k-grid .k-table-row:hover,
body .k-grid .k-hover,
body .k-list-item:hover,
body .k-grid .k-selected,
body .k-grid .k-table-row.k-selected {
  background-color: var(--app-accent-soft) !important;
  color: var(--app-text) !important;
}
```

## Inputs Kendo

```css
body .k-input,
body .k-input-inner,
body .k-picker,
body .k-textbox,
body .k-dropdownlist,
body input,
body textarea {
  border-color: var(--app-border) !important;
  background-color: var(--app-input-bg) !important;
  color: var(--app-text) !important;
}

.app-field .k-input,
.app-field .k-picker,
.app-field .k-dropdownlist,
.app-field .k-combobox,
.app-field .k-textbox,
.app-field input,
.app-field textarea {
  width: 100%;
  min-width: 0;
}
```

## Chips, badges e avisos

- Chips de filtro e badges usam borda leve e fundo suave.
- Favorito pode usar amarelo, mas apenas como estado pontual.
- Perigo/erro usa vermelho contido, nao vermelho dominante na tela.
- Avisos usam amarelo claro ou laranja discreto.

```css
.app-chip {
  display: inline-flex;
  align-items: center;
  max-width: 280px;
  padding: 4px 8px;
  overflow: hidden;
  border: 1px solid var(--app-accent-border);
  border-radius: 999px;
  background: var(--app-accent-soft);
  color: var(--app-title);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-status-warning {
  border-color: #f0d38a;
  background: #fffaf0;
  color: #8a5a00;
}

.app-status-error {
  border-color: #f1b6b6;
  background: #fff5f5;
  color: #b42318;
}
```

## Appbar e navegacao

- Appbar superior compacta, com acoes a direita.
- Usar botoes de icone para tema, ajuda, notificacoes e usuario.
- Menu lateral usa Kendo TreeView quando houver hierarquia.
- Filtros do menu usam Kendo ComboBox/TextBox.
- No mobile, menu lateral deve iniciar recolhido ou ocupar largura limitada.

## Janelas Kendo

- Filtros, confirmacoes, logs e recuperacao de senha abrem em Kendo Window/Dialog.
- Conteudo da janela usa `display: grid` e `gap` entre blocos.
- Botoes em janelas ficam alinhados a esquerda.
- Em mobile, janela deve ocupar quase a largura toda.

```css
.app-dialog-content {
  display: grid;
  gap: 14px;
  color: var(--app-text);
}

.app-dialog-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-start;
}
```

## Responsividade

- Breakpoint principal para telas CRUD: `720px`.
- Breakpoint para home/appbar/sidebar: `860px`.
- Breakpoint para editores densos: `980px`.
- No mobile, trocar grids de formulario para uma coluna.
- Toolbars devem recolher acoes secundarias atras de botao compacto.
- Botoes no rodape podem ocupar `50%` quando necessario.
- Nao deixar texto sobrepor botoes ou campos.

```css
@media (max-width: 720px) {
  .app-shell {
    padding: 10px;
  }

  .app-title {
    min-width: 0;
    font-size: 20px;
  }

  .app-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    width: 100%;
    gap: 6px;
    align-items: center;
  }

  .app-form-grid {
    grid-template-columns: 1fr;
  }

  body .k-window {
    max-width: calc(100vw - 12px);
  }
}
```

## Paleta e tom visual

- Visual claro, operacional e discreto.
- Azul aparece como acento, titulo, link e estado selecionado.
- Evitar tela inteira dominada por azul, roxo, bege, marrom ou cinza escuro.
- Usar fundo neutro, superficies brancas e bordas leves.
- Sombras devem ser sutis; evitar decoracao com gradientes ou formas soltas.
- Preferir densidade organizada a visual de landing page.

## Regras de seguranca visual

- Nao renderizar HTML livre vindo de configuracao sem sanitizacao.
- Nao usar template Kendo livre vindo de JSON/API.
- Nao usar `eval`, `Function` ou JavaScript recebido como texto.
- Nao usar `alert`, `confirm` ou `prompt` nativos.
- Usar classes com namespace do modulo: `app-`, `pedido-`, `builder-`, etc.
- Evitar seletores globais agressivos fora da camada de override Kendo.
- Usar `!important` apenas para normalizar Kendo quando necessario.

## Checklist para nova tela

- CSS do Kendo carregado antes do CSS da aplicacao.
- Tokens de tema definidos em `:root`.
- Tema escuro definido por atributo no `body`.
- Appbar compacta e sem quebra visual.
- Labels acima dos campos.
- Botoes principais com Kendo Button.
- Botoes de icone com tamanho fixo e tooltip.
- Grid com hover, selecao e linha alternada aderentes ao tema.
- Janelas Kendo usadas para mensagens e confirmacoes.
- Mobile validado em largura pequena.
- Nenhum texto sobrepondo botao, campo ou grid.
- Nenhuma alteracao feita nos arquivos do Kendo.
