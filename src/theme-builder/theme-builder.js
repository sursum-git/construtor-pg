(function() {
  "use strict";

  const tokenDefinitions = [
    ["background", "Fundo"],
    ["surface", "Superficie"],
    ["border", "Borda"],
    ["text", "Texto"],
    ["muted", "Texto secundario"],
    ["title", "Titulo"],
    ["accent", "Azul/acento"],
    ["accentSoft", "Acento suave"],
    ["accentBorder", "Borda do acento"],
    ["inputBackground", "Fundo dos campos"],
    ["readonlyBackground", "Fundo somente leitura"],
    ["messageBackground", "Fundo de mensagens"],
    ["errorBorder", "Borda de erro"],
    ["errorBackground", "Fundo de erro"],
    ["buttonBackground", "Botao"],
    ["buttonHoverBackground", "Botao hover"],
    ["buttonBorder", "Borda do botao"],
    ["buttonText", "Texto do botao"],
    ["buttonPrimaryBackground", "Botao primario"],
    ["buttonPrimaryHoverBackground", "Botao primario hover"],
    ["buttonPrimaryText", "Texto primario"],
    ["notificationBackground", "Notificacao"],
    ["notificationText", "Texto notificacao"],
    ["danger", "Perigo"]
  ];

  const kendoThemes = [
    ["kendo/styles/default-blue.css", "Default Blue"],
    ["kendo/styles/default-main.css", "Default Main"],
    ["kendo/styles/default-main-dark.css", "Default Main Dark"],
    ["kendo/styles/default-green.css", "Default Green"],
    ["kendo/styles/default-nordic.css", "Default Nordic"],
    ["kendo/styles/default-ocean-blue.css", "Default Ocean Blue"],
    ["kendo/styles/default-ocean-blue-a11y.css", "Default Ocean Blue A11y"],
    ["kendo/styles/default-orange.css", "Default Orange"],
    ["kendo/styles/default-purple.css", "Default Purple"],
    ["kendo/styles/default-turquoise.css", "Default Turquoise"],
    ["kendo/styles/default-urban.css", "Default Urban"],
    ["kendo/styles/bootstrap-main.css", "Bootstrap Main"],
    ["kendo/styles/bootstrap-main-dark.css", "Bootstrap Main Dark"],
    ["kendo/styles/bootstrap-3.css", "Bootstrap 3"],
    ["kendo/styles/bootstrap-3-dark.css", "Bootstrap 3 Dark"],
    ["kendo/styles/bootstrap-4.css", "Bootstrap 4"],
    ["kendo/styles/bootstrap-4-dark.css", "Bootstrap 4 Dark"],
    ["kendo/styles/bootstrap-nordic.css", "Bootstrap Nordic"],
    ["kendo/styles/bootstrap-turquoise.css", "Bootstrap Turquoise"],
    ["kendo/styles/bootstrap-turquoise-dark.css", "Bootstrap Turquoise Dark"],
    ["kendo/styles/bootstrap-urban.css", "Bootstrap Urban"],
    ["kendo/styles/bootstrap-vintage.css", "Bootstrap Vintage"],
    ["kendo/styles/classic-main.css", "Classic Main"],
    ["kendo/styles/classic-main-dark.css", "Classic Main Dark"],
    ["kendo/styles/classic-green.css", "Classic Green"],
    ["kendo/styles/classic-green-dark.css", "Classic Green Dark"],
    ["kendo/styles/classic-lavender.css", "Classic Lavender"],
    ["kendo/styles/classic-lavender-dark.css", "Classic Lavender Dark"],
    ["kendo/styles/classic-metro.css", "Classic Metro"],
    ["kendo/styles/classic-metro-dark.css", "Classic Metro Dark"],
    ["kendo/styles/classic-moonlight.css", "Classic Moonlight"],
    ["kendo/styles/classic-opal.css", "Classic Opal"],
    ["kendo/styles/classic-opal-dark.css", "Classic Opal Dark"],
    ["kendo/styles/classic-silver.css", "Classic Silver"],
    ["kendo/styles/classic-silver-dark.css", "Classic Silver Dark"],
    ["kendo/styles/classic-uniform.css", "Classic Uniform"],
    ["kendo/styles/fluent-main.css", "Fluent Main"],
    ["kendo/styles/fluent-main-dark.css", "Fluent Main Dark"],
    ["kendo/styles/fluent-1.css", "Fluent 1"],
    ["kendo/styles/fluent-1-dark.css", "Fluent 1 Dark"],
    ["kendo/styles/material-main.css", "Material Main"],
    ["kendo/styles/material-main-dark.css", "Material Main Dark"],
    ["kendo/styles/material-2.css", "Material 2"],
    ["kendo/styles/material-2-dark.css", "Material 2 Dark"],
    ["kendo/styles/material-aqua-dark.css", "Material Aqua Dark"],
    ["kendo/styles/material-arctic.css", "Material Arctic"],
    ["kendo/styles/material-burnt-teal.css", "Material Burnt Teal"],
    ["kendo/styles/material-eggplant.css", "Material Eggplant"],
    ["kendo/styles/material-lime.css", "Material Lime"],
    ["kendo/styles/material-lime-dark.css", "Material Lime Dark"],
    ["kendo/styles/material-nova.css", "Material Nova"],
    ["kendo/styles/material-pacific.css", "Material Pacific"],
    ["kendo/styles/material-pacific-dark.css", "Material Pacific Dark"],
    ["kendo/styles/material-sky.css", "Material Sky"],
    ["kendo/styles/material-sky-dark.css", "Material Sky Dark"],
    ["kendo/styles/material-smoke.css", "Material Smoke"]
  ];

  const cssMap = {
    background: "--crud-bg",
    surface: "--crud-surface",
    border: "--crud-border",
    text: "--crud-text",
    muted: "--crud-muted",
    title: "--crud-title",
    accent: "--crud-accent",
    accentSoft: "--crud-accent-soft",
    accentBorder: "--crud-accent-border",
    inputBackground: "--crud-input-bg",
    readonlyBackground: "--crud-readonly-bg",
    messageBackground: "--crud-message-bg",
    errorBorder: "--crud-error-border",
    errorBackground: "--crud-error-bg",
    buttonBackground: "--crud-button-bg",
    buttonHoverBackground: "--crud-button-hover-bg",
    buttonBorder: "--crud-button-border",
    buttonText: "--crud-button-text",
    buttonPrimaryBackground: "--crud-button-primary-bg",
    buttonPrimaryHoverBackground: "--crud-button-primary-hover-bg",
    buttonPrimaryText: "--crud-button-primary-text",
    notificationBackground: "--crud-notification-bg",
    notificationText: "--crud-notification-text",
    danger: "--crud-danger"
  };

  const kendoCssMap = {
    background: [
      "--kendo-color-app-surface"
    ],
    surface: [
      "--kendo-color-surface"
    ],
    border: [
      "--kendo-color-border",
      "--kendo-color-base-emphasis"
    ],
    text: [
      "--kendo-color-on-app-surface",
      "--kendo-color-on-base",
      "--kendo-color-base-on-surface"
    ],
    muted: [
      "--kendo-color-subtle",
      "--kendo-color-base-on-subtle"
    ],
    title: [
      "--kendo-color-primary-on-surface",
      "--kendo-color-primary-on-subtle"
    ],
    accent: [
      "--kendo-color-primary",
      "--kendo-color-primary-emphasis",
      "--kendo-color-info",
      "--kendo-color-info-on-surface",
      "--kendo-color-tertiary",
      "--kendo-color-series-a"
    ],
    accentSoft: [
      "--kendo-color-primary-subtle",
      "--kendo-color-primary-subtle-hover",
      "--kendo-color-primary-subtle-active",
      "--kendo-color-info-subtle",
      "--kendo-color-tertiary-subtle"
    ],
    accentBorder: [
      "--kendo-color-border-alt"
    ],
    inputBackground: [
      "--kendo-color-surface-alt"
    ],
    readonlyBackground: [
      "--kendo-color-base-subtle",
      "--kendo-color-base-subtle-active",
      "--kendo-color-base-active"
    ],
    buttonBackground: [
      "--kendo-color-base",
      "--kendo-color-secondary"
    ],
    buttonHoverBackground: [
      "--kendo-color-base-hover",
      "--kendo-color-base-subtle-hover",
      "--kendo-color-secondary-hover"
    ],
    buttonText: [
      "--kendo-color-on-secondary",
      "--kendo-color-secondary-on-subtle",
      "--kendo-color-secondary-on-surface"
    ],
    buttonPrimaryBackground: [
      "--kendo-color-primary"
    ],
    buttonPrimaryHoverBackground: [
      "--kendo-color-primary-hover",
      "--kendo-color-primary-active"
    ],
    buttonPrimaryText: [
      "--kendo-color-on-primary"
    ],
    errorBackground: [
      "--kendo-color-error-subtle",
      "--kendo-color-error-subtle-hover",
      "--kendo-color-error-subtle-active"
    ],
    danger: [
      "--kendo-color-error",
      "--kendo-color-error-hover",
      "--kendo-color-error-active",
      "--kendo-color-error-emphasis",
      "--kendo-color-error-on-surface",
      "--kendo-color-error-on-subtle"
    ]
  };

  const state = {
    mode: "light",
    kendoTheme: "kendo/styles/default-blue.css",
    defaultMode: "light",
    allowUserSwitch: true,
    persistUserChoice: true,
    storageKey: "crudEngine.theme",
    tokens: {
      light: {
        background: "#f4f5f7",
        surface: "#ffffff",
        border: "#d9dee7",
        text: "#1f2937",
        muted: "#667085",
        title: "#0f4f8f",
        accent: "#0d6efd",
        accentSoft: "#eef6ff",
        accentBorder: "#bfdaf8",
        inputBackground: "#ffffff",
        readonlyBackground: "#f8fafc",
        messageBackground: "#ffffff",
        errorBorder: "#f4c7c3",
        errorBackground: "#fff5f5",
        buttonBackground: "#ffffff",
        buttonHoverBackground: "#f8fafc",
        buttonBorder: "#cfd5df",
        buttonText: "#344054",
        buttonPrimaryBackground: "#0f5f9f",
        buttonPrimaryHoverBackground: "#0b4f8a",
        buttonPrimaryText: "#ffffff",
        notificationBackground: "#d92d20",
        notificationText: "#ffffff",
        danger: "#b42318"
      },
      dark: {
        background: "#111827",
        surface: "#182230",
        border: "#344054",
        text: "#f2f4f7",
        muted: "#98a2b3",
        title: "#8ec5ff",
        accent: "#4da3ff",
        accentSoft: "#102a43",
        accentBorder: "#275f9f",
        inputBackground: "#101828",
        readonlyBackground: "#101828",
        messageBackground: "#182230",
        errorBorder: "#7a271a",
        errorBackground: "#2a1210",
        buttonBackground: "#202c3d",
        buttonHoverBackground: "#28384d",
        buttonBorder: "#42526a",
        buttonText: "#f2f4f7",
        buttonPrimaryBackground: "#2f7ec8",
        buttonPrimaryHoverBackground: "#3b8fdf",
        buttonPrimaryText: "#ffffff",
        notificationBackground: "#f97066",
        notificationText: "#111827",
        danger: "#ffb4ab"
      }
    }
  };

  const defaultState = cloneThemeSettings(state);
  let originalState = cloneThemeSettings(state);

  document.addEventListener("DOMContentLoaded", function() {
    renderKendoThemeOptions();
    renderTokenInputs();
    bindEvents();
    initializePreviewWidgets();
    updateScreen();
  });

  function bindEvents() {
    document.getElementById("kendo-theme").addEventListener("change", function(event) {
      state.kendoTheme = event.target.value;
      applyKendoTheme();
      updateOutput();
    });

    document.getElementById("theme-mode").addEventListener("change", function(event) {
      state.mode = event.target.value;
      updateScreen();
    });

    document.getElementById("copy-json").addEventListener("click", function() {
      const output = document.getElementById("theme-json-output");
      output.select();
      document.execCommand("copy");
    });

    document.getElementById("reset-current-mode").addEventListener("click", function() {
      resetCurrentModeChanges();
    });

    document.getElementById("load-current").addEventListener("click", function() {
      fetch("public/config/crud-engine.config.json", { cache: "no-cache" })
        .then(function(response) { return response.json(); })
        .then(function(config) {
          applyConfig(config);
          originalState = cloneThemeSettings(state);
          updateScreen();
        })
        .catch(function() {
          window.CrudUtils.showMessage("Nao foi possivel carregar o config atual.", "error");
        });
    });
  }

  function renderKendoThemeOptions() {
    const select = document.getElementById("kendo-theme");
    select.innerHTML = "";
    kendoThemes.forEach(function(item) {
      const option = document.createElement("option");
      option.value = item[0];
      option.textContent = item[1];
      select.appendChild(option);
    });
  }

  function renderTokenInputs() {
    const list = document.getElementById("theme-token-list");
    list.innerHTML = "";
    tokenDefinitions.forEach(function(item) {
      const key = item[0];
      const label = item[1];
      const row = document.createElement("div");
      row.className = "theme-token-row";
      row.innerHTML = "<label for=\"token-" + key + "\">" + label + "</label>" +
        "<input type=\"color\" id=\"token-" + key + "\" data-token=\"" + key + "\">" +
        "<input type=\"text\" data-token-text=\"" + key + "\" maxlength=\"7\">";
      list.appendChild(row);
    });

    list.addEventListener("input", function(event) {
      const colorToken = event.target.getAttribute("data-token");
      const textToken = event.target.getAttribute("data-token-text");
      const key = colorToken || textToken;
      if (!key) {
        return;
      }

      const value = normalizeColor(event.target.value);
      if (!value) {
        return;
      }
      state.tokens[state.mode][key] = value;
      syncInputs();
      applyTokens();
      updateOutput();
    });
  }

  function applyConfig(config) {
    const theme = config && config.theme ? config.theme : {};
    state.kendoTheme = theme.kendoTheme || defaultState.kendoTheme;
    state.defaultMode = theme.defaultMode || defaultState.defaultMode;
    state.allowUserSwitch = theme.allowUserSwitch !== false;
    state.persistUserChoice = theme.persistUserChoice !== false;
    state.storageKey = theme.storageKey || defaultState.storageKey;

    state.tokens.light = Object.assign(
      {},
      defaultState.tokens.light,
      theme.tokens && theme.tokens.light ? theme.tokens.light : {}
    );
    state.tokens.dark = Object.assign(
      {},
      defaultState.tokens.dark,
      theme.tokens && theme.tokens.dark ? theme.tokens.dark : {}
    );
  }

  function resetCurrentModeChanges() {
    const originalTokens = originalState.tokens && originalState.tokens[state.mode]
      ? originalState.tokens[state.mode]
      : {};

    state.tokens[state.mode] = cloneTokens(originalTokens);
    syncInputs();
    applyTokens();
    updateOutput();
  }

  function updateScreen() {
    document.body.setAttribute("data-crud-theme", state.mode);
    document.getElementById("kendo-theme").value = state.kendoTheme;
    document.getElementById("theme-mode").value = state.mode;
    applyKendoTheme();
    syncInputs();
    applyTokens();
    updateOutput();
  }

  function syncInputs() {
    const tokens = state.tokens[state.mode];
    Object.keys(tokens).forEach(function(key) {
      const color = document.querySelector("[data-token=\"" + key + "\"]");
      const text = document.querySelector("[data-token-text=\"" + key + "\"]");
      if (color) {
        color.value = tokens[key];
      }
      if (text) {
        text.value = tokens[key];
      }
    });
  }

  function applyTokens() {
    const tokens = state.tokens[state.mode];
    const targets = [
      document.documentElement,
      document.body,
      document.getElementById("theme-preview")
    ].filter(Boolean);

    applyCssMap(targets, cssMap, tokens);
    applyCssMap(targets, kendoCssMap, tokens);
    refreshPreviewWidgets();
  }

  function applyKendoTheme() {
    const link = document.getElementById("kendo-theme-link");
    if (link && link.getAttribute("href") !== state.kendoTheme) {
      link.addEventListener("load", refreshPreviewWidgets, { once: true });
      link.setAttribute("href", state.kendoTheme);
      return;
    }
    refreshPreviewWidgets();
  }

  function initializePreviewWidgets() {
    if (!window.jQuery || !window.kendo) {
      return;
    }

    renderPreviewGrid();
    renderPreviewFormFields();
  }

  function refreshPreviewWidgets() {
    if (!window.jQuery || !window.kendo) {
      return;
    }

    const grid = window.jQuery("#theme-preview-grid").data("kendoGrid");
    if (grid) {
      grid.resize();
      grid.refresh();
    }
  }

  function renderPreviewGrid() {
    const grid = window.jQuery("#theme-preview-grid");
    if (!grid.length || grid.data("kendoGrid")) {
      return;
    }

    grid.kendoGrid({
      dataSource: {
        data: [
          {
            nome: "Acme Comercio",
            status: "Ativo",
            dataCadastro: new Date(2026, 4, 2),
            valorTotal: 15420.5
          },
          {
            nome: "Beta Servicos",
            status: "Ativo",
            dataCadastro: new Date(2026, 3, 18),
            valorTotal: 8450
          },
          {
            nome: "Delta Industria",
            status: "Inativo",
            dataCadastro: new Date(2026, 2, 9),
            valorTotal: 2300.75
          }
        ],
        schema: {
          model: {
            fields: {
              nome: { type: "string" },
              status: { type: "string" },
              dataCadastro: { type: "date" },
              valorTotal: { type: "number" }
            }
          }
        },
        pageSize: 2
      },
      height: 310,
      pageable: {
        buttonCount: 3
      },
      sortable: true,
      filterable: true,
      resizable: true,
      selectable: "row",
      columns: [
        {
          field: "nome",
          title: "Nome",
          width: 220
        },
        {
          field: "status",
          title: "Status",
          width: 130
        },
        {
          field: "dataCadastro",
          title: "Cadastro",
          width: 140,
          format: "{0:dd/MM/yyyy}"
        },
        {
          field: "valorTotal",
          title: "Valor Total",
          width: 150,
          format: "{0:c2}",
          attributes: {
            style: "text-align:right"
          },
          headerAttributes: {
            style: "text-align:right"
          }
        }
      ]
    });
  }

  function renderPreviewFormFields() {
    widget("#preview-nome", "kendoTextBox", {
      value: "Acme Comercio"
    });
    widget("#preview-status", "kendoDropDownList", {
      dataSource: [
        { text: "Ativo", value: "ATIVO" },
        { text: "Inativo", value: "INATIVO" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "ATIVO"
    });
    widget("#preview-data", "kendoDatePicker", {
      format: "dd/MM/yyyy",
      value: new Date(2026, 4, 2)
    });
    widget("#preview-valor", "kendoNumericTextBox", {
      format: "c2",
      decimals: 2,
      value: 15420.5
    });
    widget("#preview-tags", "kendoMultiSelect", {
      dataSource: ["Preferencial", "Varejo", "Comercial", "Bloqueado"],
      value: ["Preferencial", "Comercial"]
    });
    widget("#preview-observacao", "kendoTextArea", {
      rows: 3,
      value: "Observacao do cliente para visualizar textarea no tema."
    });
    widget("#preview-ativo", "kendoSwitch", {
      checked: true,
      messages: {
        checked: "Sim",
        unchecked: "Nao"
      }
    });
    widget("#preview-save", "kendoButton", {
      themeColor: "primary",
      icon: "save"
    });
    widget("#preview-cancel", "kendoButton");
  }

  function widget(selector, pluginName, options) {
    const element = window.jQuery(selector);
    if (!element.length || element.data(pluginName)) {
      return;
    }
    if (typeof element[pluginName] === "function") {
      element[pluginName](options || {});
    }
  }

  function updateOutput() {
    const output = {
      theme: {
        kendoTheme: state.kendoTheme,
        defaultMode: state.defaultMode,
        allowUserSwitch: state.allowUserSwitch,
        persistUserChoice: state.persistUserChoice,
        storageKey: state.storageKey,
        tokens: state.tokens
      }
    };
    document.getElementById("theme-json-output").value = JSON.stringify(output, null, 2);
  }

  function normalizeColor(value) {
    const color = String(value || "").trim();
    return /^#[0-9a-f]{6}$/i.test(color) ? color.toLowerCase() : null;
  }

  function cloneThemeSettings(source) {
    return {
      mode: source.mode,
      kendoTheme: source.kendoTheme,
      defaultMode: source.defaultMode,
      allowUserSwitch: source.allowUserSwitch,
      persistUserChoice: source.persistUserChoice,
      storageKey: source.storageKey,
      tokens: {
        light: cloneTokens(source.tokens && source.tokens.light),
        dark: cloneTokens(source.tokens && source.tokens.dark)
      }
    };
  }

  function cloneTokens(tokens) {
    return Object.assign({}, tokens || {});
  }

  function applyCssMap(targets, map, tokens) {
    Object.keys(map).forEach(function(key) {
      const properties = Array.isArray(map[key]) ? map[key] : [map[key]];
      properties.forEach(function(propertyName) {
        targets.forEach(function(target) {
          if (tokens[key]) {
            target.style.setProperty(propertyName, tokens[key]);
          } else {
            target.style.removeProperty(propertyName);
          }
        });
      });
    });
  }
})();
