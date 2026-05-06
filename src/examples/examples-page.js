(function(global, $) {
  "use strict";

  const CONFIG_ROOT_KEY = "crud-engine.config.json";
  const EXAMPLE_ASSET_PREFIX = "../../";

  document.addEventListener("DOMContentLoaded", function() {
    const catalog = global.CrudExamplesCatalog;
    const exampleId = document.body.getAttribute("data-example-id");
    const example = catalog && catalog.get(exampleId);

    if (!catalog || !example) {
      renderFatalError("Exemplo nao encontrado.");
      return;
    }

    const state = createState(example);
    renderPageHeader(example);
    renderCode(formatJson(state.currentPatch));
    renderConfigurator(state, catalog);
    initializeTabs();
    initializeEngine(example, catalog, state);
  });

  function createState(example) {
    const originalPatch = clone(example.code || {});
    return {
      originalPatch,
      currentPatch: clone(originalPatch),
      fields: []
    };
  }

  function renderPageHeader(example) {
    document.title = example.title + " - Exemplos CRUD Engine";
    setText("example-category", example.category);
    setText("example-title", example.title);
    setText("example-summary", example.summary);
  }

  function renderCode(code) {
    const target = document.getElementById("example-code");
    if (target) {
      target.textContent = code;
    }
  }

  function initializeTabs() {
    const tabs = $("#example-tabs");
    if (!tabs.length || !$.fn.kendoTabStrip) {
      return;
    }
    tabs.kendoTabStrip({
      animation: false,
      activate: function() {
        const engine = global.currentCrudExampleEngine;
        if (engine && engine.gridRenderer && engine.gridRenderer.grid) {
          engine.gridRenderer.grid.resize();
          engine.gridRenderer.grid.refresh();
        }
      }
    });
    const widget = tabs.data("kendoTabStrip");
    if (widget) {
      widget.select(0);
    }
  }

  function initializeEngine(example, catalog, state) {
    const root = document.getElementById("example-render-root");
    if (!root) {
      return Promise.resolve(null);
    }

    destroyCurrentEngine(root);
    if (example.engine === "home") {
      return initializeHomeEngine(example, catalog, state, root);
    }
    return initializeCrudEngine(example, catalog, state, root);
  }

  function initializeCrudEngine(example, catalog, state, root) {
    $(root).removeClass("home-app-root").addClass("crud-app-shell");
    const artifacts = buildRuntimeArtifacts(example, catalog, state);
    const httpClient = new global.DemoMockHttpClient({
      storageSuffix: "examples-" + example.id
    });
    const engine = new global.CrudEngine({
      root: "#example-render-root",
      definition: artifacts.definition,
      config: artifacts.config,
      httpClient
    });

    return engine.init().then(function(instance) {
      global.currentCrudExampleEngine = instance;
      runInitialAction(example, instance);
      return instance;
    }).catch(function(error) {
      console.error("Falha ao renderizar exemplo.", error);
      throw error;
    });
  }

  function initializeHomeEngine(example, catalog, state, root) {
    $(root).removeClass("crud-app-shell").addClass("home-app-root example-home-root");
    const artifacts = buildHomeRuntimeArtifacts(example, catalog, state);
    const httpClient = new global.DemoMockHttpClient({
      storageSuffix: "examples-" + example.id
    });
    const engine = new global.HomeEngine({
      root: "#example-render-root",
      definition: artifacts.definition,
      config: artifacts.config,
      httpClient
    });

    return engine.init().then(function(instance) {
      global.currentHomeExampleEngine = instance;
      return instance;
    }).catch(function(error) {
      console.error("Falha ao renderizar exemplo da Home.", error);
      throw error;
    });
  }

  function destroyCurrentEngine(root) {
    const engine = global.currentCrudExampleEngine;
    if (engine && engine.gridRenderer && typeof engine.gridRenderer.destroy === "function") {
      engine.gridRenderer.destroy();
    }
    if (engine && engine.filterRenderer && typeof engine.filterRenderer.destroy === "function") {
      engine.filterRenderer.destroy();
    }
    if (global.kendo) {
      destroyHomeExampleEngine();
      global.kendo.destroy($(root));
    }
    $(root).empty();
    global.currentCrudExampleEngine = null;
    global.currentHomeExampleEngine = null;
  }

  function destroyHomeExampleEngine() {
    const engine = global.currentHomeExampleEngine;
    if (!engine) {
      return;
    }
    ["destroyChatWindow", "destroySupportWindow", "destroyAiChatWindow", "destroyAppbarPanelWindows", "destroyCurrentProgram"].forEach(function(method) {
      if (typeof engine[method] === "function") {
        engine[method]();
      }
    });
  }

  function buildRuntimeArtifacts(example, catalog, state) {
    const definition = catalog.buildDefinition(example.id);
    const config = catalog.buildConfig(example.id, { assetPrefix: EXAMPLE_ASSET_PREFIX });
    const patch = diffValue(state.originalPatch, state.currentPatch) || {};
    const configPatch = patch[CONFIG_ROOT_KEY];

    if (configPatch) {
      deepMerge(config, configPatch);
      normalizeConfigAssets(config);
      delete patch[CONFIG_ROOT_KEY];
    }

    deepMerge(definition, patch);
    return { definition, config };
  }

  function buildHomeRuntimeArtifacts(example, catalog, state) {
    const definition = catalog.buildHomeDefinition(example.id);
    const config = catalog.buildConfig(example.id, { assetPrefix: EXAMPLE_ASSET_PREFIX });
    const patch = diffValue(state.originalPatch, state.currentPatch) || {};
    const configPatch = patch[CONFIG_ROOT_KEY];

    if (configPatch) {
      deepMerge(config, configPatch);
      normalizeConfigAssets(config);
      delete patch[CONFIG_ROOT_KEY];
    }

    deepMerge(definition, patch);
    normalizeHomeDefinitionAssets(definition);
    return { definition, config };
  }

  function normalizeHomeDefinitionAssets(definition) {
    const appLogo = definition && definition.app && definition.app.logo;
    if (typeof appLogo === "string") {
      definition.app.logo = prefixExamplePath(appLogo);
    } else if (appLogo && appLogo.url) {
      appLogo.url = prefixExamplePath(appLogo.url);
    }

    global.CrudUtils.ensureArray(definition && definition.programs).forEach(function(program) {
      ["url", "openUrl", "definitionUrl", "htmlUrl"].forEach(function(property) {
        if (program[property]) {
          program[property] = prefixExamplePath(program[property]);
        }
      });
      if (program.logs && program.logs.url) {
        program.logs.url = prefixExamplePath(program.logs.url);
      }
      if (program.help && program.help.linkUrl) {
        program.help.linkUrl = prefixExamplePath(program.help.linkUrl);
      }
      if (program.help && program.help.videoUrl) {
        program.help.videoUrl = prefixExamplePath(program.help.videoUrl);
      }
    });
  }

  function renderConfigurator(state, catalog) {
    const tabs = document.getElementById("example-tabs");
    if (!tabs || tabs.getAttribute("data-configurator-ready") === "true") {
      return;
    }

    const tabList = tabs.children[0];
    if (!tabList) {
      return;
    }

    const tab = document.createElement("li");
    tab.textContent = "Configuracao";
    tabList.appendChild(tab);

    const panel = document.createElement("div");
    panel.innerHTML = "<div class=\"example-config-panel\">" +
      "<div class=\"example-config-header\">" +
        "<p class=\"example-tab-note\">Altere os valores do trecho JSON deste exemplo e atualize a renderizacao corrente.</p>" +
        "<div class=\"example-config-actions\">" +
          "<button type=\"button\" id=\"example-config-apply\">Atualizar</button>" +
          "<button type=\"button\" id=\"example-config-reset\">Limpar alteracoes</button>" +
        "</div>" +
      "</div>" +
      "<div id=\"example-config-form-container\"></div>" +
    "</div>";
    tabs.appendChild(panel);
    tabs.setAttribute("data-configurator-ready", "true");

    renderConfigForm(state, catalog);
    bindConfigActions(state, catalog);
  }

  function renderConfigForm(state, catalog) {
    const target = document.getElementById("example-config-form-container");
    if (!target) {
      return;
    }

    state.fields = buildEditableFields(state.currentPatch, catalog);
    if (!state.fields.length) {
      target.innerHTML = "<section class=\"example-config-empty\">Este exemplo nao possui propriedades simples para editar.</section>";
      return;
    }

    const groups = state.fields.reduce(function(acc, field) {
      const group = field.group || "Geral";
      if (!acc[group]) {
        acc[group] = [];
      }
      acc[group].push(field);
      return acc;
    }, {});

    target.innerHTML = "<form id=\"example-config-form\" class=\"example-config-form\">" +
      Object.keys(groups).map(function(group) {
        return "<section class=\"example-config-group\">" +
          "<h3>" + escapeHtml(group) + "</h3>" +
          groups[group].map(renderConfigField).join("") +
        "</section>";
      }).join("") +
    "</form>";
  }

  function bindConfigActions(state, catalog) {
    const applyButton = document.getElementById("example-config-apply");
    const resetButton = document.getElementById("example-config-reset");
    const example = catalog.get(document.body.getAttribute("data-example-id"));

    if (applyButton) {
      $(applyButton).kendoButton({ themeColor: "primary", icon: "arrow-rotate-cw" });
      applyButton.addEventListener("click", function() {
        const nextPatch = collectConfigValues(state);
        if (!nextPatch) {
          return;
        }
        state.currentPatch = nextPatch;
        renderCode(formatJson(state.currentPatch));
        selectRenderTab();
        initializeEngine(example, catalog, state).then(function() {
          showMessage("Configuracao aplicada.", "success");
        }).catch(function(error) {
          const message = error && error.message ? error.message : "Nao foi possivel aplicar a configuracao.";
          showMessage(message, "error");
        });
      });
    }

    if (resetButton) {
      $(resetButton).kendoButton({ icon: "undo" });
      resetButton.addEventListener("click", function() {
        state.currentPatch = clone(state.originalPatch);
        renderCode(formatJson(state.currentPatch));
        renderConfigForm(state, catalog);
        selectRenderTab();
        initializeEngine(example, catalog, state).then(function() {
          showMessage("Alteracoes removidas.", "info");
        });
      });
    }
  }

  function buildEditableFields(patch, catalog) {
    const options = catalog && typeof catalog.getPropertyOptions === "function"
      ? catalog.getPropertyOptions()
      : [];
    const fields = [];

    walkEditableFields(patch, [], fields);
    return fields.map(function(field, index) {
      const metadata = findPropertyOption(field.displayPath, options);
      const allowedValues = metadata ? parseAllowedValues(metadata.values) : [];
      return Object.assign({}, field, {
        index,
        id: "example-config-field-" + index,
        group: metadata ? metadata.category : getFallbackGroup(field.segments),
        note: metadata ? metadata.note : "",
        defaultValue: metadata ? metadata.defaultValue : "",
        allowedValues,
        closedValues: isClosedAllowedList(allowedValues) ? allowedValues : []
      });
    });
  }

  function walkEditableFields(value, segments, fields) {
    if (Array.isArray(value)) {
      if (!value.length) {
        fields.push(createField(segments, value, "json"));
        return;
      }
      value.forEach(function(item, index) {
        walkEditableFields(item, segments.concat(index), fields);
      });
      return;
    }

    if (isPlainObject(value)) {
      const keys = Object.keys(value);
      if (!keys.length) {
        fields.push(createField(segments, value, "json"));
        return;
      }
      keys.forEach(function(key) {
        walkEditableFields(value[key], segments.concat(key), fields);
      });
      return;
    }

    fields.push(createField(segments, value, getValueType(value)));
  }

  function createField(segments, value, valueType) {
    return {
      segments,
      displayPath: formatPath(segments),
      normalizedPath: normalizeOptionPath(formatPath(segments)),
      value,
      valueType
    };
  }

  function renderConfigField(field) {
    return "<div class=\"example-config-field\">" +
      "<label for=\"" + escapeHtml(field.id) + "\">" + escapeHtml(field.displayPath) + "</label>" +
      renderControl(field) +
      renderAllowedValues(field) +
      renderFieldNote(field) +
    "</div>";
  }

  function renderControl(field) {
    const value = getControlValue(field.value, field.valueType);
    const common = " id=\"" + escapeHtml(field.id) + "\" data-example-config-index=\"" + field.index + "\"";

    if (field.valueType === "json") {
      return "<textarea class=\"example-config-input example-config-textarea\"" + common + ">" +
        escapeHtml(formatJson(field.value)) +
      "</textarea>";
    }

    if (field.closedValues.length) {
      return "<select class=\"example-config-input\"" + common + ">" +
        getSelectValues(field).map(function(option) {
          const optionValue = normalizeOptionValue(option);
          const selected = String(optionValue) === String(value) ? " selected" : "";
          return "<option value=\"" + escapeHtml(optionValue) + "\"" + selected + ">" + escapeHtml(option) + "</option>";
        }).join("") +
      "</select>";
    }

    if (field.valueType === "boolean") {
      return "<select class=\"example-config-input\"" + common + ">" +
        "<option value=\"true\"" + (value === "true" ? " selected" : "") + ">true</option>" +
        "<option value=\"false\"" + (value === "false" ? " selected" : "") + ">false</option>" +
      "</select>";
    }

    if (field.valueType === "number") {
      return "<input class=\"example-config-input\" type=\"number\" step=\"any\"" + common +
        " value=\"" + escapeHtml(value) + "\">";
    }

    return "<input class=\"example-config-input\" type=\"text\"" + common +
      " value=\"" + escapeHtml(value) + "\">";
  }

  function renderAllowedValues(field) {
    if (!field.allowedValues.length) {
      return "<div class=\"example-config-options\"><span>Livre</span></div>";
    }
    return "<div class=\"example-config-options\">" +
      field.allowedValues.map(function(value) {
        return "<span>" + escapeHtml(value) + "</span>";
      }).join("") +
    "</div>";
  }

  function renderFieldNote(field) {
    const parts = [];
    if (field.defaultValue) {
      parts.push("Padrao: " + field.defaultValue);
    }
    if (field.note) {
      parts.push(field.note);
    }
    if (!parts.length) {
      return "";
    }
    return "<p class=\"example-config-note\">" + escapeHtml(parts.join(" | ")) + "</p>";
  }

  function collectConfigValues(state) {
    const form = document.getElementById("example-config-form");
    const nextPatch = clone(state.currentPatch);
    if (!form) {
      return nextPatch;
    }

    for (let index = 0; index < state.fields.length; index += 1) {
      const field = state.fields[index];
      const input = form.querySelector("[data-example-config-index=\"" + index + "\"]");
      if (!input) {
        continue;
      }

      const parsed = parseControlValue(input.value, field);
      if (parsed.error) {
        showMessage(parsed.error, "error");
        input.focus();
        return null;
      }
      setBySegments(nextPatch, field.segments, parsed.value);
    }

    return nextPatch;
  }

  function parseControlValue(value, field) {
    if (field.valueType === "json") {
      try {
        return { value: JSON.parse(value || "null") };
      } catch (error) {
        return { error: "JSON invalido em " + field.displayPath + "." };
      }
    }

    if (field.valueType === "boolean") {
      return { value: value === "true" };
    }

    if (field.valueType === "number") {
      if (String(value).trim() === "") {
        return { value: null };
      }
      const numberValue = Number(value);
      if (Number.isNaN(numberValue)) {
        return { error: "Valor numerico invalido em " + field.displayPath + "." };
      }
      return { value: numberValue };
    }

    if (field.value === null && String(value).trim() === "") {
      return { value: null };
    }

    return { value };
  }

  function selectRenderTab() {
    const widget = $("#example-tabs").data("kendoTabStrip");
    if (widget) {
      widget.select(0);
    }
  }

  function runInitialAction(example, engine) {
    window.setTimeout(function() {
      switch (example.initialAction) {
        case "openFilter":
          engine.openFilters();
          break;
        case "openCreate":
          engine.openCreate();
          break;
        case "openEdit":
          engine.openRecord("edit", 1);
          break;
        case "openView":
          engine.openRecord("view", 1);
          break;
        case "openSort":
          engine.openSortWindow();
          break;
        case "openGroup":
          engine.openGroupWindow();
          break;
        case "openLayout":
          engine.openLayoutWindow();
          break;
      }
    }, 250);
  }

  function diffValue(original, current) {
    if (JSON.stringify(original) === JSON.stringify(current)) {
      return undefined;
    }

    if (isPlainObject(original) && isPlainObject(current)) {
      const diff = {};
      Object.keys(current).forEach(function(key) {
        const value = diffValue(original[key], current[key]);
        if (value !== undefined) {
          diff[key] = value;
        }
      });
      return Object.keys(diff).length ? diff : undefined;
    }

    return clone(current);
  }

  function deepMerge(target, source) {
    if (!source || !isPlainObject(source)) {
      return target;
    }

    Object.keys(source).forEach(function(key) {
      const value = source[key];
      if (Array.isArray(value) && Array.isArray(target[key])) {
        target[key] = mergeArrayByStableKey(target[key], value);
      } else if (isPlainObject(value) && isPlainObject(target[key])) {
        deepMerge(target[key], value);
      } else {
        target[key] = clone(value);
      }
    });

    return target;
  }

  function mergeArrayByStableKey(targetItems, sourceItems) {
    const key = getArrayStableKey(targetItems, sourceItems);
    if (!key) {
      return clone(sourceItems);
    }

    const merged = clone(targetItems);
    sourceItems.forEach(function(sourceItem) {
      const sourceKey = String(sourceItem && sourceItem[key] || "");
      const index = merged.findIndex(function(targetItem) {
        return String(targetItem && targetItem[key] || "") === sourceKey;
      });
      if (index === -1) {
        merged.push(clone(sourceItem));
      } else if (isPlainObject(sourceItem)) {
        deepMerge(merged[index], sourceItem);
      } else {
        merged[index] = clone(sourceItem);
      }
    });
    return merged;
  }

  function getArrayStableKey(targetItems, sourceItems) {
    const items = targetItems.concat(sourceItems);
    if (!items.length || items.some(function(item) { return !isPlainObject(item); })) {
      return "";
    }
    if (items.every(function(item) { return item.id != null; })) {
      return "id";
    }
    if (items.every(function(item) { return item.programId != null; })) {
      return "programId";
    }
    return "";
  }

  function normalizeConfigAssets(config) {
    const theme = config && config.theme ? config.theme : null;
    if (!theme || !theme.kendoTheme || isAbsoluteOrPrefixed(theme.kendoTheme)) {
      return;
    }
    theme.kendoTheme = EXAMPLE_ASSET_PREFIX + String(theme.kendoTheme).replace(/^\/+/, "");
  }

  function isAbsoluteOrPrefixed(value) {
    const text = String(value || "");
    return /^(https?:)?\/\//i.test(text) || text.indexOf(EXAMPLE_ASSET_PREFIX) === 0 || text.indexOf("/") === 0;
  }

  function prefixExamplePath(value) {
    const text = String(value || "");
    if (!text || isAbsoluteOrPrefixed(text) || text.indexOf("#") === 0 || /^[a-z]+:/i.test(text)) {
      return text;
    }
    return EXAMPLE_ASSET_PREFIX + text.replace(/^\/+/, "");
  }

  function findPropertyOption(path, options) {
    const normalized = normalizeOptionPath(path);
    return options.find(function(option) {
      return option.path === normalized;
    }) || null;
  }

  function normalizeOptionPath(path) {
    let value = String(path || "");
    if (value.indexOf(CONFIG_ROOT_KEY + ".") === 0) {
      value = "config." + value.slice(CONFIG_ROOT_KEY.length + 1);
    }
    return value.replace(/\[\d+\]/g, "[]");
  }

  function parseAllowedValues(values) {
    return String(values || "").split("|").map(function(value) {
      return value.trim();
    }).filter(Boolean);
  }

  function isClosedAllowedList(values) {
    return values.length > 1 && values.every(function(value) {
      return /^true$|^false$|^[A-Za-z0-9_.-]+$|^"[^"]+"$/.test(value);
    });
  }

  function getSelectValues(field) {
    const current = String(getControlValue(field.value, field.valueType));
    const values = field.closedValues.slice();
    const normalized = values.map(normalizeOptionValue);
    if (current && normalized.indexOf(current) === -1) {
      values.unshift(current);
    }
    return values;
  }

  function normalizeOptionValue(value) {
    return String(value || "").replace(/^"|"$/g, "");
  }

  function getValueType(value) {
    if (value === null) {
      return "string";
    }
    return typeof value === "number" ? "number" :
      typeof value === "boolean" ? "boolean" :
      "string";
  }

  function getControlValue(value, valueType) {
    if (valueType === "json") {
      return formatJson(value);
    }
    if (value == null) {
      return "";
    }
    return String(value);
  }

  function getFallbackGroup(segments) {
    if (!segments.length) {
      return "Geral";
    }
    if (segments[0] === CONFIG_ROOT_KEY) {
      return "Global";
    }
    const value = String(segments[0]);
    return value.charAt(0).toUpperCase() + value.slice(1);
  }

  function setBySegments(target, segments, value) {
    let current = target;
    for (let index = 0; index < segments.length; index += 1) {
      const key = segments[index];
      const isLast = index === segments.length - 1;
      if (isLast) {
        current[key] = value;
        return;
      }
      if (current[key] == null) {
        current[key] = typeof segments[index + 1] === "number" ? [] : {};
      }
      current = current[key];
    }
  }

  function formatPath(segments) {
    return segments.reduce(function(path, segment) {
      if (typeof segment === "number") {
        return path + "[" + segment + "]";
      }
      return path ? path + "." + segment : String(segment);
    }, "");
  }

  function formatJson(value) {
    return JSON.stringify(value || {}, null, 2);
  }

  function clone(value) {
    if (global.CrudUtils && typeof global.CrudUtils.clone === "function") {
      return global.CrudUtils.clone(value);
    }
    return value == null ? value : JSON.parse(JSON.stringify(value));
  }

  function isPlainObject(value) {
    return Boolean(value) && typeof value === "object" && !Array.isArray(value);
  }

  function showMessage(message, type) {
    if (global.CrudUtils && typeof global.CrudUtils.showMessage === "function") {
      global.CrudUtils.showMessage(message, type);
      return;
    }
    console.log(message);
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value || "";
    }
  }

  function renderFatalError(message) {
    const root = document.getElementById("example-render-root") || document.body;
    root.innerHTML = "<section class=\"crud-message crud-message-error\">" + escapeHtml(message) + "</section>";
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
})(window, jQuery);
