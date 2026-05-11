(function(global, $) {
  "use strict";

  class CrudEngine {
    constructor(options) {
      this.options = options || {};
      this.root = $(this.options.root);
      this.httpClient = this.options.httpClient || new global.CrudHttpClient();
      this.configLoader = new global.CrudConfigLoader();
      this.loader = new global.CrudDefinitionLoader({ httpClient: this.httpClient });
      this.definitionNormalizer = new global.PageDefinitionNormalizer();
      this.validator = new global.CrudDefinitionValidator();
      this.config = this.applyRuntimeConfigOptions(this.normalizeConfig(this.options.config));
      this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
      this.currentTheme = this.resolveInitialTheme();
      this.currentRuntimeLock = null;
      this.runtimeHeartbeatTimer = null;
      this.runtimeMessageTimer = null;
      this.runtimeEventSource = null;
      this.runtimeEventFallbackTimer = null;
      this.sessionRevoked = false;
      this.applyTheme(this.currentTheme, { persist: false });
    }

    init() {
      this.renderLoading();
      return this.loadConfig().then(() => {
        this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
        return this.loader.load({
          definitionUrl: this.options.definitionUrl,
          definition: this.options.definition,
          screenId: this.options.screenId,
          securityPolicy: this.securityPolicy
        });
      }).then((definition) => {
        this.rawDefinition = definition;
        this.definition = this.definitionNormalizer.normalize(definition);
        this.validator.validate(this.definition, { securityPolicy: this.securityPolicy });
        global.CrudUtils.applyEndpointSecurity(this.definition, this.securityPolicy);
        this.render();
        if (this.options.runtimeMessages !== false) {
          this.startRuntimeMessagePolling();
        }
        return this;
      }).catch((error) => {
        this.renderError(global.CrudUtils.unwrapError(error, "Erro ao carregar tela."));
        throw error;
      });
    }

    render() {
      if (this.gridRenderer && typeof this.gridRenderer.destroy === "function") {
        this.gridRenderer.destroy();
      }
      if (this.filterRenderer) {
        this.filterRenderer.destroy();
      }
      kendo.destroy(this.root);
      this.root.empty();
      const screen = $("<div class=\"crud-screen\"></div>").appendTo(this.root);
      if (this.options.hideHeader !== true) {
        this.renderHeader(screen);
      }

      this.layoutManager = new global.CrudLayoutManager({
        definition: this.definition,
        httpClient: this.httpClient,
        onDirtyChange: (dirty) => {
          if (this.toolbarRenderer) {
            this.toolbarRenderer.setButtonEnabled("saveLayout", dirty);
          }
        }
      });

      this.toolbarRenderer = new global.CrudToolbarRenderer({
        definition: this.definition,
        handlers: {
          create: () => this.openCreate(),
          refresh: () => this.refresh(),
          filters: () => this.openFilters(),
          sort: () => this.openSortWindow(),
          group: () => this.openGroupWindow(),
          layout: () => this.openLayoutWindow(),
          applyLayout: (layoutId) => this.applyLayout(layoutId),
          bulkAction: (action) => this.executeBulkAction(action),
          print: (format, option) => this.exportGrid(format, option)
        }
      });
      this.toolbarRenderer.render(screen);
      this.activeFiltersPanel = $("<section class=\"crud-active-filters\" hidden></section>").appendTo(screen);

      if (!this.definition.features || this.definition.features.filterPanel !== false) {
        this.filterRenderer = new global.CrudFilterRenderer({
          definition: this.definition,
          onApply: (filters) => this.applyFilters(filters),
          onClear: () => this.applyFilters([]),
          onSavePreset: (preset) => this.layoutManager.saveFilterPreset(preset),
          onDeletePreset: (filterId) => this.layoutManager.deleteFilterPreset(filterId)
        });
        this.filterRenderer.render();
      }

      this.formRenderer = new global.CrudKendoFormRenderer({
        definition: this.definition,
        httpClient: this.httpClient,
        config: this.config,
        securityPolicy: this.securityPolicy,
        onSaved: (record, mode) => this.afterRecordSaved(record, mode),
        onCreate: () => this.openCreate(),
        onEdit: (record) => this.openRecord("edit", record[this.definition.dataModel.primaryKey]),
        onDelete: (record, options) => this.deleteRecord(record[this.definition.dataModel.primaryKey], Object.assign({ record }, options || {})),
        onLogs: (record, logs) => this.openLogsWindow(record, logs),
        onButtonAction: (button, record, context) => this.executeFormButtonAction(button, record, context),
        onBeforeAction: (mode, record, options) => this.prepareRecordAction(mode, record, options),
        onActionCanceled: () => this.releaseRuntimeLock(),
        onClosed: () => this.releaseRuntimeLock(),
        onNavigate: (record, direction) => this.navigateFormRecord(record, direction),
        getNavigationState: (record) => this.getFormNavigationState(record)
      });

      const shouldOpenInitialFilter = this.shouldOpenFiltersOnLoad();
      const shouldDeferInitialRead = shouldOpenInitialFilter && this.shouldWaitForFilterSubmitOnLoad();
      const initialFilters = this.filterRenderer && !shouldDeferInitialRead
        ? this.filterRenderer.getValues()
        : [];
      this.currentFilters = initialFilters;
      this.gridRenderer = new global.CrudKendoGridRenderer({
        definition: this.definition,
        httpClient: this.httpClient,
        deferInitialRead: shouldDeferInitialRead,
        initialFilters,
        handlers: {
          create: () => this.openCreate(),
          view: (id) => this.openRecord("view", id),
          edit: (id) => this.openRecord("edit", id),
          delete: (id) => this.deleteRecord(id),
          selectionChange: (rows) => this.updateBulkSelection(rows),
          print: (format, option) => this.exportGrid(format, option),
          layoutDirty: () => {
            if (this.layoutManager) {
              this.layoutManager.setDirty(true);
            }
          },
          mobileModeChange: () => this.render()
        }
      });
      const grid = this.gridRenderer.render(screen);
      this.gridRenderer.bindRowActions();
      this.layoutManager.bind(grid);

      if (this.filterRenderer) {
        if (shouldOpenInitialFilter) {
          if (!this.initialFilterWindowOpened) {
            this.filterRenderer.open({
              maximized: this.shouldMaximizeFilter()
            });
            this.initialFilterWindowOpened = true;
          }
        } else if (initialFilters.length) {
          this.renderActiveFilters(initialFilters);
        }
      }
    }

    exportGrid(format, option) {
      if (!this.gridRenderer || typeof this.gridRenderer.export !== "function") {
        return;
      }
      this.gridRenderer.export(format, option || {});
    }

    shouldOpenFiltersOnLoad() {
      const filter = this.definition.filter || {};
      if (filter.openOnLoad != null) {
        return Boolean(filter.openOnLoad);
      }
      return Boolean(this.definition.query && this.definition.query.openFiltersOnLoad);
    }

    shouldMaximizeFilter() {
      const filter = this.definition.filter || {};
      return Boolean(filter.maximizeFilter);
    }

    shouldWaitForFilterSubmitOnLoad() {
      const filter = this.definition.filter || {};
      return filter.waitForSubmitOnLoad !== false;
    }

    renderHeader(container) {
      const header = $("<header class=\"crud-header\"></header>").appendTo(container);
      const titleGroup = $("<div class=\"crud-title-group\"></div>").appendTo(header);
      const titleLine = $("<div class=\"crud-title-line\"></div>").appendTo(titleGroup);
      $("<h1 class=\"crud-title\"></h1>").text(this.definition.title).appendTo(titleLine);
      if (this.hasProgramVersion()) {
        $("<span class=\"crud-program-version k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>")
          .text("v" + String(this.definition.programVersion).trim())
          .appendTo(titleLine);
      }
      const metaGroup = $("<div class=\"crud-header-meta\"></div>").appendTo(header);
      if (this.definition.subtitle) {
        const subtitle = $("<button type=\"button\" class=\"crud-subtitle\"></button>")
          .attr("title", this.definition.subtitleTooltip || this.definition.subtitle)
          .attr("aria-label", this.definition.subtitleTooltip || this.definition.subtitle)
          .text(this.definition.subtitle)
          .appendTo(metaGroup);
        subtitle.on("click", () => this.openSubtitleTooltip());
      }
      this.lastUpdatedElement = $("<span class=\"crud-last-updated k-badge k-badge-solid k-badge-solid-primary k-rounded-md\"></span>").appendTo(metaGroup);
      this.updateLastUpdated();
      if (this.isLogsEnabled()) {
        this.renderLogsButton(metaGroup);
      }
      if (this.isHelpEnabled()) {
        this.renderHelpButton(metaGroup);
      }
      if (this.isThemeSwitchEnabled()) {
        this.renderThemeToggle(metaGroup);
      }
    }

    hasProgramVersion() {
      return this.definition.programVersion != null && String(this.definition.programVersion).trim() !== "";
    }

    openSubtitleTooltip() {
      const text = this.definition.subtitleTooltip || this.definition.subtitle;
      if (!text) {
        return;
      }

      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-subtitle-tooltip-content\"></div>").appendTo(wrapper);
      $("<p></p>").text(text).appendTo(content);
      const actions = $("<div class=\"crud-subtitle-tooltip-actions\"></div>").appendTo(content);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton();

      wrapper.kendoWindow({
        title: "Descricao",
        modal: true,
        width: Math.min(420, Math.max(300, window.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      closeButton.on("click", function() {
        windowWidget.close();
      });
      windowWidget.center().open();
    }

    getLogsConfig() {
      return this.definition && this.definition.logs ? this.definition.logs : {};
    }

    isLogsEnabled() {
      const logs = this.getLogsConfig();
      return logs.enabled !== false && (typeof logs.url === "string" && logs.url.trim() !== "" || logs.documentId || logs.endpointId || logs.actionId);
    }

    renderLogsButton(container) {
      const logs = this.getLogsConfig();
      const title = logs.title || "Logs";
      const button = $("<button type=\"button\" class=\"crud-log-button crud-icon-button\"></button>")
        .attr("title", title)
        .attr("aria-label", title)
        .appendTo(container);

      button.kendoButton({ icon: "list-unordered" });
      button.on("click", () => this.openLogsWindow());
    }

    openLogsWindow(record, logsConfig) {
      const logs = logsConfig || this.getLogsConfig();
      if (!logs || logs.enabled === false) {
        return;
      }
      const screenId = global.CrudUtils.getDefinitionScreenId(this.definition);
      const sourceUrl = global.CrudUtils.resolveDocumentUrlForPolicy(logs, "logs", screenId, this.securityPolicy);
      if (!sourceUrl) {
        return;
      }
      const url = this.resolveRecordUrl(sourceUrl, record || {});

      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-log-window-content\"></div>").appendTo(wrapper);
      $("<iframe class=\"crud-log-frame\" title=\"Logs\"></iframe>")
        .attr("src", url)
        .appendTo(content);

      const actions = $("<div class=\"crud-log-actions\"></div>").appendTo(content);
      $("<a class=\"crud-log-link\" target=\"_blank\" rel=\"noopener noreferrer\"></a>")
        .attr("href", url)
        .text(logs.linkText || "Abrir logs em nova aba")
        .appendTo(actions);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton();
      let cleanupWindowLayer = function() {};

      wrapper.kendoWindow({
        title: logs.title || "Logs",
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(960, Math.max(320, window.innerWidth - 24)),
        visible: false,
        maximize: function() {
          wrapper.addClass("crud-log-window-maximized");
        },
        restore: function() {
          wrapper.removeClass("crud-log-window-maximized");
        },
        close: function() {
          cleanupWindowLayer();
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      closeButton.on("click", function() {
        windowWidget.close();
      });
      windowWidget.center().open();
      cleanupWindowLayer = this.bringContentWindowToFront(windowWidget, "crud-log-kendo-window", "crud-log-overlay", 11800);
    }

    resolveRecordUrl(url, record) {
      return global.CrudUtils.replaceUrlParams(url, this.buildRecordActionParams(record || {}));
    }

    buildRecordActionParams(record) {
      const primaryKey = this.definition.dataModel && this.definition.dataModel.primaryKey;
      const data = Object.assign({}, record || {});
      if (primaryKey && data[primaryKey] != null) {
        data.id = data[primaryKey];
      }
      return data;
    }

    bringContentWindowToFront(windowWidget, windowClass, overlayClass, zIndex) {
      if (!windowWidget || !windowWidget.wrapper) {
        return function() {};
      }

      const overlay = $(".k-overlay").last();
      const previousWindowZIndex = windowWidget.wrapper[0] ? windowWidget.wrapper[0].style.zIndex : "";
      const previousOverlayZIndex = overlay.length && overlay[0] ? overlay[0].style.zIndex : "";

      windowWidget.wrapper
        .addClass(windowClass)
        .css("z-index", zIndex);

      overlay
        .addClass(overlayClass)
        .css("z-index", zIndex - 1);

      return function() {
        if (windowWidget.wrapper && windowWidget.wrapper.length) {
          windowWidget.wrapper
            .removeClass(windowClass)
            .css("z-index", previousWindowZIndex || "");
        }
        if (overlay.length && overlay.closest("body").length) {
          overlay
            .removeClass(overlayClass)
            .css("z-index", previousOverlayZIndex || "");
        }
      };
    }

    loadConfig() {
      return this.configLoader.load({
        configUrl: this.options.configUrl,
        config: this.options.config
      }).then((config) => {
        this.config = this.applyRuntimeConfigOptions(this.normalizeConfig(config));
        this.applyKendoTheme();
        this.currentTheme = this.resolveInitialTheme();
        this.applyTheme(this.currentTheme, { persist: false });
        return this.config;
      });
    }

    normalizeConfig(config) {
      const source = config || {};
      const defaultConfig = {
        schemaVersion: "1.0",
        theme: {
          kendoTheme: "kendo/styles/default-urban.css",
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
        },
        help: {
          enabled: true,
          title: "Ajuda e novidades",
          storageKey: "crudEngine.help.seen",
          readEndpoint: null,
          items: []
        }
      };

      const sourceTheme = source.theme || {};
      const sourceTokens = sourceTheme.tokens || {};
      const theme = Object.assign({}, defaultConfig.theme, sourceTheme, {
        tokens: {
          light: Object.assign({}, defaultConfig.theme.tokens.light, sourceTokens.light || {}),
          dark: Object.assign({}, defaultConfig.theme.tokens.dark, sourceTokens.dark || {})
        }
      });

      return Object.assign({}, defaultConfig, source, {
        theme,
        help: Object.assign({}, defaultConfig.help, source.help || {})
      });
    }

    applyRuntimeConfigOptions(config) {
      const source = global.CrudUtils.clone(config || {});
      if (this.options.hideThemeSwitch === true) {
        source.theme = Object.assign({}, source.theme || {}, {
          allowUserSwitch: false
        });
      }
      return source;
    }

    renderThemeToggle(container) {
      const field = $("<label class=\"crud-theme-toggle\"></label>").appendTo(container);
      const input = $("<input type=\"checkbox\" class=\"crud-theme-input\">")
        .prop("checked", this.currentTheme === "dark")
        .appendTo(field);
      $("<span class=\"crud-theme-track\"><span class=\"crud-theme-thumb\"></span></span>").appendTo(field);
      this.updateThemeToggleLabel(field);

      input.on("change", () => {
        this.toggleTheme(input.is(":checked") ? "dark" : "light");
      });
      this.themeToggleElement = field;
      this.themeInputElement = input;
    }

    updateThemeToggleLabel(element) {
      const label = this.currentTheme === "dark" ? "Tema claro" : "Tema escuro";
      element
        .attr("title", label)
        .attr("aria-label", label);
    }

    getHelpConfig() {
      return this.config && this.config.help ? this.config.help : {};
    }

    getProgramHelpConfig() {
      return this.definition && this.definition.help ? this.definition.help : {};
    }

    hasProgramHelpConfig() {
      return Boolean(this.definition && this.definition.help && this.definition.help.enabled !== false);
    }

    getHelpItems() {
      if (!this.hasProgramHelpConfig()) {
        return [];
      }
      return this.getProgramHelpItems().concat(this.getGlobalHelpItems());
    }

    getProgramHelpItems() {
      if (!this.hasProgramHelpConfig()) {
        return [];
      }
      const help = this.getProgramHelpConfig();

      const items = global.CrudUtils.ensureArray(help.items).map((item, index) => {
        return this.normalizeHelpItem(item, {
          idPrefix: this.definition.id + ".help.",
          index,
          source: "program",
          trackSeen: false
        });
      });

      if (help.body || help.summary || help.linkUrl || help.videoUrl) {
        items.unshift(this.normalizeHelpItem(help, {
          idPrefix: this.definition.id + ".help.",
          fallbackId: "main",
          source: "program",
          trackSeen: false,
          fallbackTitle: help.title || "Ajuda do programa"
        }));
      }

      return items.filter(function(item) {
        return item && item.id && item.title;
      });
    }

    getGlobalHelpItems() {
      if (this.getHelpConfig().enabled === false) {
        return [];
      }

      return global.CrudUtils.ensureArray(this.getHelpConfig().items).map((item, index) => {
        return this.normalizeHelpItem(item, {
          idPrefix: "global.help.",
          index,
          source: "global",
          trackSeen: true
        });
      }).filter(function(item) {
        return item && item.id && item.title;
      });
    }

    normalizeHelpItem(item, options) {
      const settings = options || {};
      const source = item || {};
      const rawId = source.id || settings.fallbackId || String(settings.index + 1);
      const kind = source.kind || (source.videoUrl ? "video" : source.linkUrl && !source.body ? "link" : "text");

      return Object.assign({}, source, {
        id: String(rawId).indexOf(".") === -1 ? settings.idPrefix + rawId : String(rawId),
        title: source.title || settings.fallbackTitle || "Ajuda",
        kind,
        source: settings.source || "global",
        trackSeen: settings.trackSeen !== false
      });
    }

    isHelpEnabled() {
      return this.getHelpItems().length > 0;
    }

    getHelpStorageKey() {
      return this.getHelpConfig().storageKey || "crudEngine.help.seen";
    }

    getHelpReadEndpoint() {
      const programHelp = this.getProgramHelpConfig();
      const globalHelp = this.getHelpConfig();
      const endpoint = programHelp.readEndpoint || globalHelp.readEndpoint || null;
      const api = this.definition && this.definition.api || {};
      const screenId = this.definition ? global.CrudUtils.getDefinitionScreenId(this.definition) : "screen";
      if (endpoint && typeof endpoint === "object" && (endpoint.endpointId || endpoint.actionId)) {
        return global.CrudUtils.resolveEndpointForPolicy(endpoint, endpoint.endpointId || endpoint.actionId, screenId, this.securityPolicy);
      }
      if (typeof endpoint === "string" && this.securityPolicy && this.securityPolicy.endpoints.allowInlineUrls === false) {
        return api[endpoint] || (global.CrudUtils.isSafeIdentifier(endpoint)
          ? global.CrudUtils.resolveEndpointForPolicy({ endpointId: endpoint }, endpoint, screenId, this.securityPolicy)
          : null);
      }
      if (typeof endpoint === "string") {
        const url = endpoint.trim();
        if (!url) {
          return null;
        }
        return {
          url,
          method: "POST"
        };
      }
      if (!endpoint || typeof endpoint !== "object" || !endpoint.url || !String(endpoint.url).trim()) {
        if (endpoint && endpoint.endpointId && api[endpoint.endpointId]) {
          return api[endpoint.endpointId];
        }
        return null;
      }
      if (this.securityPolicy && this.securityPolicy.endpoints.allowInlineUrls === false) {
        return null;
      }
      return {
        url: String(endpoint.url).trim(),
        method: endpoint.method || "POST"
      };
    }

    getSeenHelpIds() {
      try {
        const value = localStorage.getItem(this.getHelpStorageKey());
        const ids = value ? JSON.parse(value) : [];
        return Array.isArray(ids) ? ids : [];
      } catch (error) {
        return [];
      }
    }

    saveSeenHelpIds(ids) {
      try {
        localStorage.setItem(this.getHelpStorageKey(), JSON.stringify(ids));
      } catch (error) {
        return;
      }
    }

    getUnseenHelpItems() {
      const seenIds = this.getSeenHelpIds();
      return this.getHelpItems().filter(function(item) {
        return item.trackSeen !== false && seenIds.indexOf(item.id) === -1;
      });
    }

    renderHelpButton(container) {
      const wrapper = $("<span class=\"crud-help-button-wrap\"></span>").appendTo(container);
      const helpTitle = this.getHelpWindowTitle();
      const button = $("<button type=\"button\" class=\"crud-help-button crud-icon-button\"></button>")
        .attr("title", helpTitle)
        .attr("aria-label", helpTitle)
        .appendTo(wrapper);
      button.kendoButton({ icon: "question-circle" });
      button.on("click", () => this.openHelpWindow());

      this.helpBadgeElement = $("<span class=\"crud-help-badge k-badge k-badge-solid k-badge-solid-error k-rounded-full\" hidden></span>")
        .appendTo(wrapper);
      this.helpButtonWidget = button.data("kendoButton");
      this.updateHelpBadge();
    }

    updateHelpBadge() {
      if (!this.helpBadgeElement) {
        return;
      }

      const count = this.getUnseenHelpItems().length;
      if (!count) {
        this.helpBadgeElement.attr("hidden", true).text("");
        return;
      }

      this.helpBadgeElement.removeAttr("hidden").text(String(count));
    }

    openHelpWindow() {
      const items = this.getHelpItems();
      const seenIds = this.getSeenHelpIds();
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-help-window-content\"></div>").appendTo(wrapper);
      const list = $("<div class=\"crud-help-list\"></div>").appendTo(content);

      if (!items.length) {
        $("<p class=\"crud-help-empty\"></p>").text("Nenhuma novidade cadastrada.").appendTo(list);
      }

      items.forEach((item) => {
        this.renderHelpItem(list, item, seenIds.indexOf(item.id) === -1);
      });

      const actions = $("<div class=\"crud-help-actions\"></div>").appendTo(content);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton();
      let cleanupWindowLayer = function() {};

      wrapper.kendoWindow({
        title: this.getHelpWindowTitle(),
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(720, Math.max(320, window.innerWidth - 24)),
        visible: false,
        maximize: function() {
          wrapper.addClass("crud-help-window-maximized");
        },
        restore: function() {
          wrapper.removeClass("crud-help-window-maximized");
        },
        close: function() {
          cleanupWindowLayer();
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      closeButton.on("click", function() {
        windowWidget.close();
      });

      this.markHelpItemsAsSeen(items);
      this.updateHelpBadge();
      windowWidget.center().open();
      cleanupWindowLayer = this.bringHelpWindowToFront(windowWidget);
    }

    getHelpWindowTitle() {
      const programHelp = this.getProgramHelpConfig();
      return programHelp.title || this.getHelpConfig().title || "Ajuda e novidades";
    }

    bringHelpWindowToFront(windowWidget) {
      const zIndex = 12000;
      return this.bringContentWindowToFront(windowWidget, "crud-help-kendo-window", "crud-help-overlay", zIndex);
    }

    renderHelpItem(container, item, isNew) {
      const element = $("<article class=\"crud-help-item\"></article>").appendTo(container);
      const header = $("<div class=\"crud-help-item-header\"></div>").appendTo(element);
      const titleGroup = $("<div class=\"crud-help-item-title-group\"></div>").appendTo(header);
      $("<h2 class=\"crud-help-item-title\"></h2>").text(item.title).appendTo(titleGroup);
      if (item.publishedAt) {
        $("<span class=\"crud-help-item-date\"></span>").text(item.publishedAt).appendTo(titleGroup);
      }
      if (isNew) {
        $("<span class=\"crud-help-new-badge k-badge k-badge-solid k-badge-solid-primary k-rounded-md\">Novo</span>").appendTo(header);
      }
      if (item.summary) {
        $("<p class=\"crud-help-summary\"></p>").text(item.summary).appendTo(element);
      }
      if (item.body) {
        const body = $("<div class=\"crud-help-body\"></div>").appendTo(element);
        String(item.body).split(/\n+/).forEach(function(paragraph) {
          if (paragraph.trim()) {
            $("<p></p>").text(paragraph.trim()).appendTo(body);
          }
        });
      }
      if (item.kind === "video") {
        this.renderHelpVideo(element, item);
      }
      if (item.kind === "link" && item.linkUrl) {
        this.renderHelpFrame(element, item);
      }
      if (item.linkUrl) {
        this.renderHelpLink(element, item);
      }
    }

    renderHelpVideo(container, item) {
      if (item.videoUrl) {
        $("<video class=\"crud-help-video\" controls></video>")
          .attr("src", item.videoUrl)
          .appendTo(container);
      }
    }

    renderHelpLink(container, item) {
      $("<a class=\"crud-help-link\" target=\"_blank\" rel=\"noopener noreferrer\"></a>")
        .attr("href", item.linkUrl)
        .text(item.linkText || (item.kind === "video" ? "Abrir video" : "Abrir ajuda"))
        .appendTo(container);
    }

    renderHelpFrame(container, item) {
      $("<iframe class=\"crud-help-frame\" title=\"Ajuda\"></iframe>")
        .attr("src", item.linkUrl)
        .appendTo(container);
    }

    markHelpItemsAsSeen(items) {
      const currentIds = this.getSeenHelpIds();
      const nextIds = currentIds.slice();
      const newlySeenItems = [];
      global.CrudUtils.ensureArray(items).forEach(function(item) {
        if (item && item.trackSeen !== false && item.id && nextIds.indexOf(item.id) === -1) {
          nextIds.push(item.id);
          newlySeenItems.push(item);
        }
      });
      if (!newlySeenItems.length) {
        return;
      }
      this.saveSeenHelpIds(nextIds);
      this.notifyHelpItemsAsRead(newlySeenItems, nextIds);
    }

    notifyHelpItemsAsRead(items, seenIds) {
      const endpoint = this.getHelpReadEndpoint();
      if (!endpoint) {
        return Promise.resolve();
      }

      const payload = {
        programId: this.definition.id,
        module: this.definition.module,
        entity: this.definition.entity,
        readAt: new Date().toISOString(),
        itemIds: items.map(function(item) {
          return item.id;
        }),
        items: items.map(function(item) {
          return {
            id: item.id,
            title: item.title,
            source: item.source,
            publishedAt: item.publishedAt || null
          };
        }),
        seenIds: seenIds.slice()
      };

      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method,
        data: payload
      }).catch(function(error) {
        if (global.console && typeof global.console.warn === "function") {
          global.console.warn("Nao foi possivel registrar leitura das novidades.", error);
        }
      });
    }

    getThemeConfig() {
      return this.config && this.config.theme ? this.config.theme : {};
    }

    applyKendoTheme() {
      const href = this.getThemeConfig().kendoTheme || "kendo/styles/default-urban.css";
      const link = document.getElementById("kendo-theme-link");
      if (link && link.getAttribute("href") !== href) {
        link.setAttribute("href", href);
      }
    }

    getThemeTokenMap() {
      return {
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
    }

    getKendoThemeTokenMap() {
      return {
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
    }

    applyThemeTokens(theme) {
      const tokens = this.getThemeConfig().tokens && this.getThemeConfig().tokens[theme]
        ? this.getThemeConfig().tokens[theme]
        : {};
      const targets = [
        document.documentElement,
        document.body
      ].filter(Boolean);

      this.applyThemeCssMap(targets, this.getThemeTokenMap(), tokens);
      this.applyThemeCssMap(targets, this.getKendoThemeTokenMap(), tokens);
    }

    applyThemeCssMap(targets, tokenMap, tokens) {
      Object.keys(tokenMap).forEach(function(key) {
        const properties = Array.isArray(tokenMap[key]) ? tokenMap[key] : [tokenMap[key]];
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

    isThemeSwitchEnabled() {
      return this.getThemeConfig().allowUserSwitch !== false;
    }

    resolveInitialTheme() {
      return this.getStoredTheme() || this.normalizeTheme(this.getThemeConfig().defaultMode);
    }

    normalizeTheme(theme) {
      return theme === "dark" ? "dark" : "light";
    }

    getThemeStorageKey() {
      return this.getThemeConfig().storageKey || "crudEngine.theme";
    }

    getStoredTheme() {
      if (this.getThemeConfig().persistUserChoice === false) {
        return null;
      }

      try {
        const value = localStorage.getItem(this.getThemeStorageKey());
        return value === "dark" || value === "light" ? value : null;
      } catch (error) {
        return null;
      }
    }

    applyTheme(theme, options) {
      const settings = options || {};
      this.currentTheme = this.normalizeTheme(theme);
      document.body.setAttribute("data-crud-theme", this.currentTheme);
      this.applyThemeTokens(this.currentTheme);
      if (settings.persist === false || this.getThemeConfig().persistUserChoice === false) {
        return;
      }
      try {
        localStorage.setItem(this.getThemeStorageKey(), this.currentTheme);
      } catch (error) {
        return;
      }
    }

    toggleTheme(theme) {
      this.applyTheme(theme);
      if (this.themeInputElement) {
        this.themeInputElement.prop("checked", this.currentTheme === "dark");
      }
      if (this.themeToggleElement) {
        this.updateThemeToggleLabel(this.themeToggleElement);
      }
    }

    renderLoading() {
      this.root.html("<div class=\"crud-loading\">Carregando tela...</div>");
    }

    renderError(error) {
      const productionErrors = this.options.productionErrors === true ||
        global.CrudUtils.isProductionSecurity(this.securityPolicy);
      const safeMessage = productionErrors
        ? "Nao foi possivel carregar a tela. Entre em contato com o suporte."
        : error.message || "Erro inesperado.";
      const message = global.CrudUtils.escapeHtml(safeMessage);
      const details = !productionErrors && error.details && error.details.errors
        ? "<ul>" + error.details.errors.map(function(item) { return "<li>" + global.CrudUtils.escapeHtml(item) + "</li>"; }).join("") + "</ul>"
        : "";
      this.root.html("<div class=\"crud-message crud-message-error\"><strong>" + message + "</strong>" + details + "</div>");
    }

    openCreate() {
      this.formRenderer.open("create", {});
    }

    openRecord(mode, id) {
      return this.fetchRecord(id).then((record) => {
        return this.prepareRecordAction(mode, record).then((allowed) => {
          if (allowed === false) {
            return null;
          }
          this.formRenderer.open(mode, record);
          return record;
        });
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar registro.");
        global.CrudUtils.showMessage(normalized.message, "error");
        return null;
      });
    }

    fetchRecord(id) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const endpoint = this.definition.api.get;
      const url = global.CrudUtils.replaceUrlParams(endpoint.url, { [primaryKey]: id });
      return this.httpClient.request({
        url,
        method: endpoint.method || "GET",
        data: { [primaryKey]: id, id }
      });
    }

    prepareRecordAction(mode, record, options) {
      const normalizedMode = String(mode || "").toLowerCase();
      if (["edit", "delete"].indexOf(normalizedMode) === -1 || this.sessionRevoked) {
        return Promise.resolve(!this.sessionRevoked);
      }

      return this.showRecordConcurrencyWarning(normalizedMode, record || {}, options).then((confirmed) => {
        if (confirmed === false) {
          return false;
        }

        const endpoint = this.getRuntimeApiEndpoint("runtime.lock.acquire");
        if (!endpoint || !endpoint.url || !this.isRuntimeLockEnabled(normalizedMode)) {
          return true;
        }

        const payload = this.buildLockPayload(normalizedMode, record || {});
        return this.httpClient.request({
          url: endpoint.url,
          method: endpoint.method || "POST",
          data: payload
        }).then((response) => {
          const lock = response && response.lock || {};
          if (lock.status === "warn") {
            const owner = lock.owner && lock.owner.name ? lock.owner.name : "outro usuario";
            return global.CrudUtils.confirm("Este registro esta sendo alterado por " + owner + ". Deseja continuar mesmo assim?", {
              title: "Registro em uso",
              confirmText: "Continuar",
              cancelText: "Cancelar",
              confirmIcon: "exclamation-circle",
              themeColor: "warning"
            });
          }

          if (lock.status === "acquired" || lock.status === "active") {
            this.setRuntimeLock(lock, response && response._runtime, record);
          }
          return true;
        }).catch((error) => {
          if (this.handleRuntimeError(error)) {
            return false;
          }
          const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel controlar o semaforo do registro.");
          global.CrudUtils.showMessage(normalized.message, "error");
          return false;
        });
      });
    }

    showRecordConcurrencyWarning(mode, record, options) {
      const settings = options || {};
      if (settings.skipConcurrencyWarning === true || settings.concurrencyWarningShown === true) {
        return Promise.resolve(true);
      }
      if (!this.formRenderer || typeof this.formRenderer.showConcurrencyWarning !== "function") {
        return Promise.resolve(true);
      }
      return this.formRenderer.showConcurrencyWarning(mode, global.CrudUtils.clone(record || {}));
    }

    buildLockPayload(mode, record) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const runtime = this.definition.runtime || {};
      const program = this.definition.program || {};
      return {
        entityCode: runtime.entityCode || this.definition.entity || "cliente",
        programId: runtime.programId || program.id || program.code || "",
        actionId: mode === "edit" ? "update" : mode,
        mode,
        recordId: record && (record[primaryKey] || record.id),
        expectedVersion: record && record._runtime && record._runtime.version,
        _runtime: record && record._runtime || {}
      };
    }

    isRuntimeLockEnabled(mode) {
      const runtime = this.definition.runtime || {};
      const lock = runtime.lock || {};
      if (lock.enabled === false) {
        return false;
      }
      const modes = global.CrudUtils.ensureArray(lock.modes);
      return !modes.length || modes.indexOf(mode) !== -1 || modes.indexOf(mode === "edit" ? "update" : mode) !== -1;
    }

    setRuntimeLock(lock, runtime, record) {
      this.releaseRuntimeLock({ silent: true });
      this.currentRuntimeLock = Object.assign({}, lock || {});
      if (record) {
        record._runtime = Object.assign({}, record._runtime || {}, runtime || {}, {
          lockToken: lock.token,
          transactionId: lock.transactionId
        });
      }
      if (this.formRenderer && typeof this.formRenderer.setRuntimeContext === "function") {
        this.formRenderer.setRuntimeContext(record && record._runtime || runtime || {});
      }
      this.startRuntimeHeartbeat();
    }

    startRuntimeHeartbeat() {
      this.stopRuntimeHeartbeat();
      const lock = this.currentRuntimeLock;
      if (!lock || !lock.token) {
        return;
      }
      const seconds = Math.max(10, Number(lock.heartbeatIntervalSeconds || 60));
      this.runtimeHeartbeatTimer = window.setInterval(() => {
        this.sendRuntimeHeartbeat();
      }, seconds * 1000);
    }

    stopRuntimeHeartbeat() {
      if (this.runtimeHeartbeatTimer) {
        window.clearInterval(this.runtimeHeartbeatTimer);
        this.runtimeHeartbeatTimer = null;
      }
    }

    sendRuntimeHeartbeat() {
      const lock = this.currentRuntimeLock;
      const endpoint = this.getRuntimeApiEndpoint("runtime.lock.heartbeat");
      if (!lock || !lock.token || !endpoint || !endpoint.url || this.sessionRevoked) {
        return Promise.resolve(false);
      }

      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: { lockToken: lock.token, _runtime: { lockToken: lock.token } }
      }).then((response) => {
        if (response && response.lock) {
          this.currentRuntimeLock = Object.assign({}, this.currentRuntimeLock || {}, response.lock);
        }
        return true;
      }).catch((error) => {
        this.handleRuntimeError(error);
        return false;
      });
    }

    releaseRuntimeLock(options) {
      const settings = options || {};
      const lock = this.currentRuntimeLock;
      this.stopRuntimeHeartbeat();
      this.currentRuntimeLock = null;
      if (!lock || !lock.token || this.sessionRevoked) {
        return Promise.resolve(false);
      }
      const endpoint = this.getRuntimeApiEndpoint("runtime.lock.release");
      if (!endpoint || !endpoint.url) {
        return Promise.resolve(false);
      }
      if (settings.silent) {
        this.httpClient.request({
          url: endpoint.url,
          method: endpoint.method || "POST",
          data: { lockToken: lock.token, _runtime: { lockToken: lock.token } }
        }).catch(function() {});
        return Promise.resolve(true);
      }
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: { lockToken: lock.token, _runtime: { lockToken: lock.token } }
      }).then(function() {
        return true;
      }).catch(function() {
        return false;
      });
    }

    buildRuntimeWritePayload(payload) {
      const data = Object.assign({}, payload || {});
      data._runtime = Object.assign({}, data._runtime || {});
      if (this.currentRuntimeLock && this.currentRuntimeLock.token) {
        data._runtime.lockToken = this.currentRuntimeLock.token;
        data._runtime.transactionId = this.currentRuntimeLock.transactionId;
      }
      return data;
    }

    getRuntimeApiEndpoint(endpointId) {
      const api = this.definition && this.definition.api || {};
      if (api[endpointId]) {
        return api[endpointId];
      }
      const screenId = global.CrudUtils.getDefinitionScreenId(this.definition);
      return global.CrudUtils.resolveEndpointForPolicy({ endpointId, method: "POST" }, endpointId, screenId, this.securityPolicy);
    }

    navigateFormRecord(record, direction) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const currentId = record && record[primaryKey];
      const nextId = this.gridRenderer && typeof this.gridRenderer.getAdjacentRecordId === "function"
        ? this.gridRenderer.getAdjacentRecordId(currentId, direction)
        : null;
      if (nextId == null) {
        return Promise.resolve(null);
      }
      return this.fetchRecord(nextId).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar registro.");
        global.CrudUtils.showMessage(normalized.message, "error");
        return null;
      });
    }

    getFormNavigationState(record) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const currentId = record && record[primaryKey];
      if (!this.gridRenderer || typeof this.gridRenderer.getNavigationState !== "function") {
        return { previous: false, next: false };
      }
      return this.gridRenderer.getNavigationState(currentId);
    }

    deleteRecord(id, options) {
      const settings = options || {};
      const action = global.CrudUtils.ensureArray(this.definition.grid.rowActions).find(function(item) {
        return item.action === "delete";
      });
      const message = action && action.confirm && action.confirm.message
        ? action.confirm.message
        : "Deseja excluir este registro?";

      const executeDelete = () => {
        const primaryKey = this.definition.dataModel.primaryKey;
        const record = Object.assign({ [primaryKey]: id }, settings.record || {});
        const endpoint = this.resolveActionEndpoint(settings.action, "delete") || this.definition.api.delete;
        const url = global.CrudUtils.replaceUrlParams(endpoint.url, this.buildRecordActionParams(record));
        return this.httpClient.request({
          url,
          method: endpoint.method || "DELETE",
          data: this.buildRuntimeWritePayload(this.buildRecordActionParams(record))
        }).then(() => {
          this.refresh();
          this.releaseRuntimeLock();
          return true;
        }).catch((error) => {
          if (this.handleBackendValidation(error)) {
            this.releaseRuntimeLock();
            return false;
          }
          const normalized = global.CrudUtils.unwrapError(error, "Erro ao excluir registro.");
          global.CrudUtils.showMessage(normalized.message, "error");
          this.releaseRuntimeLock();
          return false;
        });
      };

      if (settings.confirm === false) {
        if (settings.skipRecordPreparation === true) {
          return executeDelete();
        }
        return this.prepareRecordAction("delete", settings.record || { id }, settings).then((allowed) => {
          return allowed === false ? false : executeDelete();
        });
      }

      return this.prepareRecordAction("delete", settings.record || { id }, settings).then((allowed) => {
        if (allowed === false) {
          return false;
        }
        return global.CrudUtils.confirm(message, {
          title: "Confirmar exclusao",
          confirmText: "Excluir",
          confirmIcon: "trash"
        }).then((confirmed) => {
          if (!confirmed) {
            this.releaseRuntimeLock();
            return false;
          }
          return executeDelete();
        });
      });
    }

    updateBulkSelection(rows) {
      if (this.toolbarRenderer && typeof this.toolbarRenderer.setSelectionCount === "function") {
        this.toolbarRenderer.setSelectionCount(global.CrudUtils.ensureArray(rows).length);
      }
    }

    executeBulkAction(action) {
      const selectedIds = this.gridRenderer ? this.gridRenderer.getSelectedIds() : [];
      if (!selectedIds.length) {
        global.CrudUtils.showMessage("Selecione ao menos um registro.", "warning");
        return;
      }

      const endpoint = this.resolveActionEndpoint(action);
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint da acao em massa nao configurado.", "error");
        return;
      }

      const message = this.getBulkActionConfirmMessage(action, selectedIds.length);
      const confirmPromise = message
        ? global.CrudUtils.confirm(message, {
          title: "Confirmar acao em massa",
          confirmText: "Aplicar",
          confirmIcon: "check"
        })
        : Promise.resolve(true);

      confirmPromise.then((confirmed) => {
        if (!confirmed) {
          return;
        }

        const payload = Object.assign({}, action.data || {}, {
          ids: selectedIds,
          action: action.action
        });
        if (action.value != null) {
          payload.value = action.value;
        }

        this.httpClient.request({
          url: endpoint.url,
          method: endpoint.method || "POST",
          data: payload
        }).then(() => {
          global.CrudUtils.showMessage(action.successMessage || "Acao aplicada aos registros selecionados.", "success");
          if (this.gridRenderer) {
            this.gridRenderer.clearSelection();
            this.gridRenderer.refresh();
          }
          this.updateLastUpdated();
        }).catch((error) => {
          if (this.handleBackendValidation(error)) {
            return;
          }
          const normalized = global.CrudUtils.unwrapError(error, "Erro ao executar acao em massa.");
          global.CrudUtils.showMessage(normalized.message, "error");
        });
      });
    }

    resolveActionEndpoint(action, mode) {
      const api = this.definition.api || {};
      if (!action) {
        return null;
      }
      const modeEndpoints = action && (action.endpoints || action.endpointByMode);
      if (mode && modeEndpoints && modeEndpoints[mode]) {
        return this.resolveActionEndpoint(modeEndpoints[mode]);
      }
      if (typeof action === "string") {
        return api[action] || null;
      }
      if (action.endpointId && api[action.endpointId]) {
        return api[action.endpointId];
      }
      if (action.actionId && api[action.actionId]) {
        return api[action.actionId];
      }
      if (action.endpoint && action.endpoint.url) {
        return action.endpoint;
      }
      if (action.api && action.api.url) {
        return action.api;
      }
      if (action.url) {
        return {
          url: action.url,
          method: action.method
        };
      }
      return action.action ? api[action.action] || null : null;
    }

    executeFormButtonAction(action, record, context) {
      if (!action) {
        return Promise.resolve(true);
      }

      const mode = context && (context.action || context.actionMode || context.mode);
      const endpoint = this.resolveActionEndpoint(action, mode);
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("API ou URL do botao nao configurada.", "error");
        return Promise.resolve(false);
      }

      const url = this.resolveRecordUrl(endpoint.url, record || {});
      if (this.shouldOpenFormActionUrl(action, endpoint)) {
        this.openActionUrlWindow(action, url, {
          endpoint,
          record,
          context
        });
        return Promise.resolve(true);
      }

      const executeRequest = () => {
        return this.httpClient.request({
          url,
          method: endpoint.method || action.method || "POST",
          data: this.buildFormActionPayload(action, record, context)
        }).then((response) => {
          if (this.handleBackendValidation(response, (token) => {
            const retryAction = Object.assign({}, action, {
              data: Object.assign({}, action.data || {}, {
                _runtime: Object.assign({}, action.data && action.data._runtime || {}, {
                  validationConfirmationToken: token
                })
              })
            });
            return this.executeFormButtonAction(retryAction, record, context);
          })) {
            return false;
          }
          const showedEffectMessage = this.applyFormActionResponseEffects(response);
          if (action.successMessage && !showedEffectMessage) {
            global.CrudUtils.showMessage(action.successMessage, "success");
          }
          if (action.refreshGrid === true && this.gridRenderer) {
            this.gridRenderer.refresh();
            this.updateLastUpdated();
          }
          return true;
        }).catch((error) => {
          if (this.handleBackendValidation(error, (token) => {
            const retryAction = Object.assign({}, action, {
              data: Object.assign({}, action.data || {}, {
                _runtime: Object.assign({}, action.data && action.data._runtime || {}, {
                  validationConfirmationToken: token
                })
              })
            });
            return this.executeFormButtonAction(retryAction, record, context);
          })) {
            return false;
          }
          const normalized = global.CrudUtils.unwrapError(error, action.errorMessage || "Erro ao executar acao do formulario.");
          global.CrudUtils.showMessage(normalized.message, "error");
          return false;
        });
      };

      const message = action.confirm && action.confirm.message ? action.confirm.message : "";
      if (!message) {
        return executeRequest();
      }

      return global.CrudUtils.confirm(this.replaceActionTokens(message, record || {}), {
        title: action.confirm.title || "Confirmar acao",
        confirmText: action.confirm.confirmText || "Aplicar",
        confirmIcon: action.confirm.confirmIcon || "check"
      }).then((confirmed) => {
        return confirmed ? executeRequest() : false;
      });
    }

    shouldOpenFormActionUrl(action, endpoint) {
      const target = action.target || action.openAs || action.openIn;
      if (target === "window" || target === "newTab") {
        return true;
      }
      return Boolean(action.url && !action.method && !action.endpointId && !action.endpoint && !action.api && !endpoint.method);
    }

    openActionUrlWindow(action, url, options) {
      const settings = options || {};
      const target = action.target || action.openAs || action.openIn;
      const request = this.buildFormActionPageRequest(action, url, settings);
      if (target === "newTab") {
        if (request.method === "POST") {
          this.submitActionPageForm(request.url, "_blank", request.values);
        } else {
          window.open(request.url, "_blank", "noopener,noreferrer");
        }
        return;
      }

      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-log-window-content\"></div>").appendTo(wrapper);
      const frameName = "crud_action_frame_" + kendo.guid().replace(/-/g, "_");
      $("<iframe class=\"crud-log-frame\"></iframe>")
        .attr("title", action.title || action.label || "Conteudo")
        .attr("name", frameName)
        .attr("src", request.method === "POST" ? "about:blank" : request.url)
        .appendTo(content);

      const actions = $("<div class=\"crud-log-actions\"></div>").appendTo(content);
      const openLink = $("<a class=\"crud-log-link\" target=\"_blank\" rel=\"noopener noreferrer\"></a>")
        .attr("href", request.method === "POST" ? "#" : request.url)
        .text(action.linkText || "Abrir em nova aba")
        .appendTo(actions);
      if (request.method === "POST") {
        openLink.on("click", (event) => {
          event.preventDefault();
          this.submitActionPageForm(request.url, "_blank", request.values);
        });
      }
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton();

      wrapper.kendoWindow({
        title: action.title || action.label || "Conteudo",
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(action.width || 960, Math.max(320, window.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      closeButton.on("click", function() {
        windowWidget.close();
      });
      windowWidget.center().open();
      if (request.method === "POST") {
        this.submitActionPageForm(request.url, frameName, request.values);
      }
    }

    buildFormActionPageRequest(action, url, options) {
      const settings = options || {};
      const endpoint = settings.endpoint || {};
      const method = String(action.pageMethod || action.submitMethod || endpoint.method || action.method || "GET").toUpperCase();
      const values = this.buildFormActionPageValues(action, settings.record, settings.context);
      const transport = this.resolveFormValuesTransport(action, method);
      if (transport === "query" && Object.keys(values).length) {
        return {
          url: this.appendQueryParams(url, values),
          method: "GET",
          values: {}
        };
      }
      return {
        url,
        method: method === "POST" || transport === "post" ? "POST" : "GET",
        values: transport === "post" ? values : {}
      };
    }

    resolveFormValuesTransport(action, method) {
      const config = this.getFormActionValuesConfig(action);
      if (!config) {
        return "none";
      }
      const transport = String(config.transport || config.mode || config.submitAs || "").toLowerCase();
      if (transport === "post" || transport === "form" || transport === "body") {
        return "post";
      }
      if (transport === "query" || transport === "querystring" || transport === "get") {
        return "query";
      }
      return method === "POST" ? "post" : "query";
    }

    getFormActionValuesConfig(action) {
      if (!action) {
        return null;
      }
      const raw = action.formValues != null
        ? action.formValues
        : action.valuesPayload != null
          ? action.valuesPayload
          : action.passFormValues != null
            ? action.passFormValues
            : action.sendFormValues != null
              ? action.sendFormValues
              : action.includeFormValues;
      if (raw === false || raw == null) {
        return null;
      }
      if (raw === true) {
        return { enabled: true };
      }
      if (Array.isArray(raw)) {
        return { enabled: true, fields: raw };
      }
      if (typeof raw === "object") {
        return raw.enabled === false ? null : Object.assign({ enabled: true }, raw);
      }
      return null;
    }

    buildFormActionPageValues(action, record, context) {
      const config = this.getFormActionValuesConfig(action);
      if (!config) {
        return {};
      }
      const sourceName = String(config.source || "record").toLowerCase();
      const source = sourceName === "values"
        ? context && context.values || {}
        : sourceName === "data"
          ? action.data || {}
          : record || {};
      const fields = global.CrudUtils.ensureArray(config.fields || config.include);
      const excludes = global.CrudUtils.ensureArray(config.exclude || config.excludes);
      const result = {};
      const putValue = (key, value) => {
        if (!key || value == null || typeof value === "function") {
          return;
        }
        if (key.charAt(0) === "_" && config.includeRuntime !== true) {
          return;
        }
        if (excludes.indexOf(key) !== -1) {
          return;
        }
        const targetKey = config.prefix ? config.prefix + key : key;
        result[targetKey] = this.serializePageValue(value);
      };
      if (fields.length) {
        fields.forEach((fieldName) => {
          const key = String(fieldName || "").trim();
          putValue(key, global.CrudUtils.getByPath(source, key));
        });
      } else {
        Object.keys(source || {}).forEach((key) => putValue(key, source[key]));
      }
      if (config.payloadParam) {
        return {
          [config.payloadParam]: JSON.stringify(result)
        };
      }
      return result;
    }

    serializePageValue(value) {
      if (value instanceof Date) {
        return value.toISOString();
      }
      if (typeof value === "object") {
        return JSON.stringify(value);
      }
      return String(value);
    }

    appendQueryParams(url, values) {
      const query = Object.keys(values || {}).map(function(key) {
        return encodeURIComponent(key) + "=" + encodeURIComponent(values[key]);
      }).join("&");
      if (!query) {
        return url;
      }
      return String(url || "") + (String(url || "").indexOf("?") === -1 ? "?" : "&") + query;
    }

    submitActionPageForm(url, target, values) {
      const form = $("<form method=\"post\"></form>")
        .attr("action", url)
        .attr("target", target || "_blank")
        .css("display", "none")
        .appendTo(document.body);
      Object.keys(values || {}).forEach(function(key) {
        $("<input type=\"hidden\">")
          .attr("name", key)
          .val(values[key])
          .appendTo(form);
      });
      form[0].submit();
      window.setTimeout(function() {
        form.remove();
      }, 0);
    }

    buildFormActionPayload(action, record, context) {
      const primaryKey = this.definition.dataModel && this.definition.dataModel.primaryKey;
      const data = Object.assign({}, action.data || {});
      const currentRecord = global.CrudUtils.clone(record || {});
      if (primaryKey && currentRecord[primaryKey] != null) {
        data.id = currentRecord[primaryKey];
      }
      data.action = action.action || action.id;
      data.mode = context && context.mode;
      data.actionMode = context && context.actionMode;
      data.event = context && context.event;
      data.format = context && context.format;
      if (context && context.values) {
        data.values = global.CrudUtils.clone(context.values);
      }
      data.record = currentRecord;
      data._runtime = Object.assign({}, currentRecord._runtime || {}, data._runtime || {});
      if (this.currentRuntimeLock && this.currentRuntimeLock.token) {
        data._runtime.lockToken = this.currentRuntimeLock.token;
        data._runtime.transactionId = this.currentRuntimeLock.transactionId;
      }
      return data;
    }

    replaceActionTokens(text, record) {
      const params = this.buildRecordActionParams(record || {});
      return String(text || "").replace(/\{([^}]+)\}/g, function(_, key) {
        return params[key] == null ? "" : String(params[key]);
      });
    }

    getBulkActionConfirmMessage(action, count) {
      if (!action.confirm) {
        return "";
      }
      const message = action.confirm.message || "Deseja aplicar esta acao aos registros selecionados?";
      return message.replace(/\{count\}/g, String(count));
    }

    refresh() {
      this.gridRenderer.refresh();
      this.updateLastUpdated();
    }

    updateLastUpdated() {
      this.lastUpdatedAt = new Date();
      if (this.lastUpdatedElement) {
        this.lastUpdatedElement
          .attr("title", "Data e hora da ultima atualizacao")
          .text(kendo.toString(this.lastUpdatedAt, "dd/MM/yyyy HH:mm"));
      }
      if (typeof this.options.onLastUpdated === "function") {
        this.options.onLastUpdated(this.lastUpdatedAt);
      }
    }

    afterRecordSaved(record, mode) {
      this.releaseRuntimeLock();
      global.CrudUtils.showMessage(mode === "create" ? "Cliente criado." : "Cliente salvo.", "success");
      if (mode === "create") {
        if (this.filterRenderer) {
          this.filterRenderer.clear();
        }
        this.renderActiveFilters([]);
        this.gridRenderer.showNewestBy(this.definition.dataModel.primaryKey);
      } else {
        this.gridRenderer.goToFirstPageAndRefresh();
      }
      this.updateLastUpdated();
    }

    startRuntimeMessagePolling() {
      const runtime = this.definition && this.definition.runtime || {};
      const messages = runtime.messages || {};
      if (messages.enabled === false || this.sessionRevoked) {
        return;
      }
      this.stopRuntimeMessagePolling();
      const seconds = Math.max(10, Number(messages.pollIntervalSeconds || 30));
      if (this.startRuntimeMessageEvents(messages, seconds)) {
        return;
      }
      this.startRuntimeMessageTimer(seconds);
    }

    startRuntimeMessageTimer(seconds) {
      const endpoint = this.getRuntimeApiEndpoint("runtime.messages.poll");
      if (!endpoint || !endpoint.url || this.sessionRevoked) {
        return;
      }
      this.pollRuntimeMessages();
      this.runtimeMessageTimer = window.setInterval(() => {
        this.pollRuntimeMessages();
      }, seconds * 1000);
    }

    stopRuntimeMessagePolling() {
      if (this.runtimeMessageTimer) {
        window.clearInterval(this.runtimeMessageTimer);
        this.runtimeMessageTimer = null;
      }
      this.stopRuntimeMessageEvents();
    }

    startRuntimeMessageEvents(messages, fallbackSeconds) {
      const events = messages.events || {};
      if (events.enabled === false || typeof global.EventSource !== "function" || global.location && global.location.protocol === "file:") {
        return false;
      }
      const url = this.getRuntimeEventSourceUrl(messages);
      if (!url) {
        return false;
      }

      let source = null;
      try {
        source = new global.EventSource(url);
      } catch (_) {
        return false;
      }

      this.runtimeEventSource = source;
      source.onopen = () => {
        if (this.runtimeEventFallbackTimer) {
          window.clearTimeout(this.runtimeEventFallbackTimer);
          this.runtimeEventFallbackTimer = null;
        }
        if (this.runtimeMessageTimer) {
          window.clearInterval(this.runtimeMessageTimer);
          this.runtimeMessageTimer = null;
        }
      };
      source.addEventListener("runtime-messages", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        this.handleRuntimeMessages(payload.messages || []);
      });
      source.addEventListener("runtime-error", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        this.handleRuntimeEventError(payload.error || {});
      });
      source.onerror = () => {
        if (this.sessionRevoked || this.runtimeEventFallbackTimer) {
          return;
        }
        this.runtimeEventFallbackTimer = window.setTimeout(() => {
          if (this.runtimeEventSource === source && source.readyState !== global.EventSource.OPEN && !this.sessionRevoked) {
            this.stopRuntimeMessageEvents();
            this.startRuntimeMessageTimer(fallbackSeconds);
          }
        }, 10000);
      };

      return true;
    }

    applyFormActionResponseEffects(response) {
      const effects = global.CrudUtils.ensureArray(response && response.effects);
      let showedMessage = false;
      effects.forEach(function(effect) {
        if (!effect || effect.action !== "showMessage") {
          return;
        }
        showedMessage = true;
        global.CrudUtils.showMessage(effect.message || effect.title || "", effect.type || "info");
      });

      return showedMessage;
    }

    stopRuntimeMessageEvents() {
      if (this.runtimeEventFallbackTimer) {
        window.clearTimeout(this.runtimeEventFallbackTimer);
        this.runtimeEventFallbackTimer = null;
      }
      if (this.runtimeEventSource) {
        this.runtimeEventSource.onopen = null;
        this.runtimeEventSource.onerror = null;
        this.runtimeEventSource.close();
        this.runtimeEventSource = null;
      }
    }

    getRuntimeEventSourceUrl(messages) {
      const policyEndpoint = this.securityPolicy && this.securityPolicy.endpoints && this.securityPolicy.endpoints.runtimeEventsEndpoint || {};
      const events = messages && messages.events || {};
      const configuredUrl = this.securityPolicy && this.securityPolicy.production ? "" : (events.url || messages && messages.eventSourceUrl || "");
      const url = configuredUrl || policyEndpoint.url || "";
      if (!url) {
        return "";
      }
      const screenId = global.CrudUtils.getDefinitionScreenId(this.definition);
      const eventUrl = global.CrudUtils.replaceUrlParams(url, { screenId });
      if (this.httpClient && typeof this.httpClient.buildRuntimeEventUrl === "function") {
        return this.httpClient.buildRuntimeEventUrl(eventUrl, {
          screenId,
          channel: "crud"
        });
      }
      return eventUrl;
    }

    parseRuntimeEventPayload(event) {
      try {
        return JSON.parse(event && event.data || "{}");
      } catch (_) {
        return {};
      }
    }

    handleRuntimeEventError(error) {
      const normalized = error && error.code ? error : { code: "RUNTIME_EVENT_ERROR", message: "Canal de eventos indisponivel.", details: {} };
      if (normalized.code === "SESSION_REVOKED" || normalized.code === "SESSION_EXPIRED") {
        this.handleSessionRevoked(normalized.message, normalized.details || {});
        return;
      }
      if (!this.runtimeMessageTimer && !this.sessionRevoked) {
        this.stopRuntimeMessageEvents();
        this.startRuntimeMessageTimer(30);
      }
    }

    pollRuntimeMessages() {
      const endpoint = this.getRuntimeApiEndpoint("runtime.messages.poll");
      if (!endpoint || !endpoint.url || this.sessionRevoked) {
        return Promise.resolve([]);
      }
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {}
      }).then((response) => {
        const messages = global.CrudUtils.ensureArray(response && response.messages);
        this.handleRuntimeMessages(messages);
        return messages;
      }).catch((error) => {
        this.handleRuntimeError(error);
        return [];
      });
    }

    handleRuntimeMessages(messages) {
      const ids = [];
      global.CrudUtils.ensureArray(messages).forEach((message) => {
        if (!message) {
          return;
        }
        if (message.id != null) {
          ids.push(message.id);
        }
        if (message.type === "force_logout") {
          this.handleSessionRevoked(message.message || "Sua sessao foi encerrada.", message);
          return;
        }
        const type = message.severity || "info";
        global.CrudUtils.showMessage(message.message || message.title, type);
      });
      if (ids.length) {
        this.ackRuntimeMessages(ids);
      }
    }

    ackRuntimeMessages(ids) {
      const endpoint = this.getRuntimeApiEndpoint("runtime.messages.ack");
      if (!endpoint || !endpoint.url) {
        return Promise.resolve(false);
      }
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: { ids }
      }).catch(function() {
        return false;
      });
    }

    handleRuntimeError(error) {
      const normalized = global.CrudUtils.unwrapError(error, "");
      if (normalized.code === "SESSION_REVOKED") {
        this.handleSessionRevoked(normalized.message, normalized.details || {});
        return true;
      }
      return false;
    }

    handleBackendValidation(error, retryCallback) {
      const normalized = global.CrudUtils.normalizeBackendValidation(error, "Existem inconsistencias na acao.");
      if (!normalized.hasValidation) {
        return false;
      }

      global.CrudUtils.showBackendValidation(normalized.validation || {}, {
        themeColor: normalized.validation && normalized.validation.status === "warning" ? "warning" : "primary"
      }).then((confirmed) => {
        const validation = normalized.validation || {};
        if (confirmed && validation.requiresConfirmation && validation.confirmationToken && typeof retryCallback === "function") {
          retryCallback(validation.confirmationToken);
        }
      });
      return true;
    }

    handleSessionRevoked(message, details) {
      if (this.sessionRevoked) {
        return;
      }
      this.sessionRevoked = true;
      this.stopRuntimeHeartbeat();
      this.stopRuntimeMessagePolling();
      this.root.addClass("crud-session-revoked");
      global.CrudUtils.blockingMessage("Sessao encerrada", message || "Sua sessao foi encerrada.", {
        buttonText: "Sessao encerrada",
        themeColor: "primary"
      });
      if (details && details.reason) {
        global.CrudUtils.showMessage(details.reason, "warning");
      }
    }

    hasUnsavedChanges() {
      return Boolean(this.formRenderer && typeof this.formRenderer.hasUnsavedChanges === "function" && this.formRenderer.hasUnsavedChanges());
    }

    confirmDiscardChanges() {
      if (!this.hasUnsavedChanges()) {
        return Promise.resolve(true);
      }
      return global.CrudUtils.confirm("Existem dados alterados e nao salvos. Deseja sair da tela e perder essas alteracoes?", {
        title: "Descartar alteracoes",
        confirmText: "Sair da tela",
        cancelText: "Continuar editando",
        confirmIcon: "exclamation-circle",
        themeColor: "warning"
      });
    }

    destroy() {
      this.stopRuntimeMessagePolling();
      this.releaseRuntimeLock({ silent: true });
      if (this.gridRenderer && typeof this.gridRenderer.destroy === "function") {
        this.gridRenderer.destroy();
      }
      if (this.filterRenderer) {
        this.filterRenderer.destroy();
      }
      if (this.formRenderer && typeof this.formRenderer.close === "function") {
        this.formRenderer.close(true);
      }
    }

    applyFilters(filters) {
      this.currentFilters = this.sortFiltersByDefinition(filters);
      this.gridRenderer.setFilters(this.currentFilters);
      this.renderActiveFilters(this.currentFilters);
      this.updateLastUpdated();
    }

    renderActiveFilters(filters) {
      if (!this.activeFiltersPanel) {
        return;
      }
      if (!this.shouldShowAppliedFilters()) {
        this.activeFiltersPanel.empty().attr("hidden", true);
        return;
      }
      const activeFilters = global.CrudUtils.ensureArray(filters);
      this.activeFiltersPanel.empty();

      if (!activeFilters.length) {
        this.activeFiltersPanel.attr("hidden", true);
        return;
      }

      this.activeFiltersPanel.removeAttr("hidden");
      $("<span class=\"crud-active-filters-title\">Filtros aplicados</span>").appendTo(this.activeFiltersPanel);
      const list = $("<div class=\"crud-active-filter-list\"></div>").appendTo(this.activeFiltersPanel);
      activeFilters.forEach((filter) => {
        const label = filter.label || filter.id || filter.field || "Filtro";
        const value = filter.displayValue || filter.value;
        $("<button type=\"button\" class=\"crud-filter-chip crud-filter-chip-button\"></button>")
          .attr("title", "Alterar filtro")
          .text(label + ": " + this.formatFilterChipValue(value))
          .appendTo(list)
          .on("click", () => this.openAppliedFilterEditor(filter.id));
      });

      $("<button type=\"button\">Limpar filtros</button>")
        .appendTo(this.activeFiltersPanel)
        .kendoButton({ icon: "filter-clear" })
        .on("click", () => this.clearFilters());
    }

    shouldShowAppliedFilters() {
      const filter = this.definition.filter || {};
      return filter.showAppliedFilters !== false;
    }

    formatFilterChipValue(value) {
      if (value == null) {
        return "";
      }
      if (typeof value === "object") {
        return Object.keys(value)
          .map(function(key) { return value[key]; })
          .filter(Boolean)
          .join(" - ");
      }
      return String(value);
    }

    clearFilters() {
      if (this.filterRenderer) {
        this.filterRenderer.clear();
      }
      this.applyFilters([]);
    }

    openFilters() {
      if (this.filterRenderer) {
        this.filterRenderer.open({
          maximized: this.shouldMaximizeFilter()
        });
      }
    }

    openAppliedFilterEditor(filterId) {
      const filterDefinition = this.findFilterDefinition(filterId);
      if (!filterDefinition) {
        return;
      }

      const currentFilter = global.CrudUtils.ensureArray(this.currentFilters).find(function(item) {
        return item.id === filterId;
      });
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-filter-edit-window\"></div>").appendTo(wrapper);
      const editor = new global.CrudFilterRenderer({ definition: this.definition });
      const grid = $("<div class=\"crud-filter-grid crud-filter-edit-grid\"></div>").appendTo(content);
      editor.renderFilter(grid, filterDefinition, {
        idPrefix: "filter-edit-" + filterId + "-" + Date.now() + "-"
      });
      if (currentFilter) {
        editor.setValues([currentFilter]);
      }

      const actions = $("<div class=\"crud-filter-actions\"></div>").appendTo(content);
      const applyButton = $("<button type=\"button\">Aplicar</button>").appendTo(actions);
      const removeButton = $("<button type=\"button\">Remover filtro</button>").appendTo(actions);
      const cancelButton = $("<button type=\"button\">Cancelar</button>").appendTo(actions);
      applyButton.kendoButton({ themeColor: "primary", icon: "filter" });
      removeButton.kendoButton({ icon: "filter-clear" });
      cancelButton.kendoButton();

      wrapper.kendoWindow({
        title: filterDefinition.label || "Filtro",
        modal: true,
        width: Math.min(460, Math.max(320, window.innerWidth - 24)),
        visible: false,
        close: function() {
          kendo.destroy(content);
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      applyButton.on("click", () => {
        const values = editor.getValues();
        const nextFilters = this.replaceAppliedFilter(filterId, values[0] || null);
        if (this.filterRenderer) {
          this.filterRenderer.setValues(nextFilters);
        }
        this.applyFilters(nextFilters);
        windowWidget.close();
      });
      removeButton.on("click", () => {
        const nextFilters = this.replaceAppliedFilter(filterId, null);
        if (this.filterRenderer) {
          this.filterRenderer.setValues(nextFilters);
        }
        this.applyFilters(nextFilters);
        windowWidget.close();
      });
      cancelButton.on("click", function() {
        windowWidget.close();
      });

      windowWidget.center().open();
    }

    findFilterDefinition(filterId) {
      const fields = this.definition.filter && this.definition.filter.fields
        ? this.definition.filter.fields
        : [];
      return global.CrudUtils.ensureArray(fields).find(function(item) {
        return item.id === filterId;
      });
    }

    replaceAppliedFilter(filterId, nextFilter) {
      const filters = global.CrudUtils.ensureArray(this.currentFilters).filter(function(item) {
        return item.id !== filterId;
      });
      if (nextFilter) {
        filters.push(nextFilter);
      }
      return this.sortFiltersByDefinition(filters);
    }

    sortFiltersByDefinition(filters) {
      const order = {};
      global.CrudUtils.ensureArray(this.definition.filter && this.definition.filter.fields).forEach(function(item, index) {
        order[item.id] = index;
      });
      return global.CrudUtils.ensureArray(filters).slice().sort(function(left, right) {
        const leftOrder = order[left.id] == null ? 9999 : order[left.id];
        const rightOrder = order[right.id] == null ? 9999 : order[right.id];
        return leftOrder - rightOrder;
      });
    }

    openSortWindow() {
      const fields = this.getSortableSortFields();
      if (!fields.length) {
        global.CrudUtils.showMessage("Nao existem colunas ordenaveis configuradas.", "warning");
        return;
      }

      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-sort-window\"></div>").appendTo(wrapper);
      const selectorField = $("<div class=\"crud-field\"></div>").appendTo(content);
      $("<label for=\"crud-sort-preset-select\">Ordenacao atual</label>").appendTo(selectorField);
      const select = $("<input id=\"crud-sort-preset-select\">").appendTo(selectorField);
      const summary = $("<p class=\"crud-layout-note\"></p>").appendTo(content);

      const selectorActions = $("<div class=\"crud-layout-menu-actions\"></div>").appendTo(content);
      const applyButton = $("<button type=\"button\">Aplicar selecionada</button>").appendTo(selectorActions);
      const deleteButton = $("<button type=\"button\">Excluir selecionada</button>").appendTo(selectorActions);
      const newButton = $("<button type=\"button\">Nova ordenacao</button>").appendTo(selectorActions);

      const form = $("<div class=\"crud-sort-form\"></div>").appendTo(content);
      const nameField = $("<div class=\"crud-field\"></div>").appendTo(form);
      $("<label for=\"crud-sort-name\">Nome da ordenacao</label>").appendTo(nameField);
      const nameInput = $("<input id=\"crud-sort-name\">").appendTo(nameField);
      nameInput.kendoTextBox();

      const defaultField = $("<label class=\"crud-checkbox-field\"></label>").appendTo(form);
      const defaultInput = $("<input type=\"checkbox\" id=\"crud-sort-default\">").appendTo(defaultField);
      $("<span>Usar como ordenacao padrao</span>").appendTo(defaultField);
      const scopeInput = this.renderPreferenceScopeField(form, "crud-sort-global");

      const rowsContainer = $("<div class=\"crud-sort-rows\"></div>").appendTo(form);
      const formActions = $("<div class=\"crud-form-actions\"></div>").appendTo(form);
      const addRowButton = $("<button type=\"button\">Adicionar coluna</button>").appendTo(formActions);
      const saveButton = $("<button type=\"button\">Salvar ordenacao</button>").appendTo(formActions);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(formActions);
      let editingSortId = null;
      let sortRows = [];

      const addSortRow = (item) => {
        const row = $("<div class=\"crud-sort-row\"></div>").appendTo(rowsContainer);
        const fieldInput = $("<input>").appendTo(row);
        const dirInput = $("<input>").appendTo(row);
        const removeButton = $("<button type=\"button\">Remover</button>").appendTo(row);
        const initialField = item && item.field ? item.field : fields[0].field;

        fieldInput.kendoDropDownList({
          dataTextField: "title",
          dataValueField: "field",
          dataSource: fields,
          value: initialField
        });
        dirInput.kendoDropDownList({
          dataTextField: "text",
          dataValueField: "dir",
          dataSource: [
            { dir: "asc", text: "Ascendente" },
            { dir: "desc", text: "Descendente" }
          ],
          value: item && item.dir === "desc" ? "desc" : "asc"
        });
        removeButton.kendoButton({ icon: "trash" });
        removeButton.on("click", function() {
          kendo.destroy(row);
          row.remove();
        });
        sortRows.push({ row, fieldInput, dirInput });
      };

      const renderSortRows = (sort) => {
        kendo.destroy(rowsContainer);
        rowsContainer.empty();
        sortRows = [];
        const rows = global.CrudUtils.ensureArray(sort);
        if (!rows.length) {
          addSortRow({});
          return;
        }
        rows.forEach(addSortRow);
      };

      const collectSortRows = () => {
        const selected = [];
        const used = {};
        sortRows.forEach(function(item) {
          if (!document.body.contains(item.row[0])) {
            return;
          }
          const field = item.fieldInput.data("kendoDropDownList").value();
          const dir = item.dirInput.data("kendoDropDownList").value() === "desc" ? "desc" : "asc";
          if (!field || used[field]) {
            return;
          }
          used[field] = true;
          selected.push({ field, dir });
        });
        return selected;
      };

      const loadPreset = (preset, useCurrent) => {
        editingSortId = preset ? preset.id : null;
        nameInput.data("kendoTextBox").value(preset ? preset.name : "");
        defaultInput.prop("checked", Boolean(preset && preset.isDefault));
        scopeInput.prop("checked", Boolean(preset && preset.scope === "global"));
        const sort = preset
          ? preset.sort
          : useCurrent
            ? this.getCurrentSortForEditor()
            : this.getSystemSort();
        renderSortRows(sort);
        summary.text(this.formatSortSummary(sort) || "Sem ordenacao configurada.");
      };

      select.kendoDropDownList({
        dataTextField: "name",
        dataValueField: "id",
        dataSource: this.getSortPresetDataSource(),
        value: this.getActiveSortId(),
        change: () => {
          const preset = this.findSavedSort(select.data("kendoDropDownList").value());
          loadPreset(preset, false);
        }
      });

      applyButton.kendoButton({ themeColor: "primary", icon: "check" });
      deleteButton.kendoButton({ icon: "trash" });
      newButton.kendoButton({ icon: "plus" });
      addRowButton.kendoButton({ icon: "plus" });
      saveButton.kendoButton({ themeColor: "primary", icon: "save" });
      closeButton.kendoButton();

      wrapper.kendoWindow({
        title: "Ordenacao padrao",
        modal: true,
        width: Math.min(720, Math.max(340, window.innerWidth - 24)),
        visible: false,
        close: function() {
          kendo.destroy(content);
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      applyButton.on("click", () => {
        this.applySortPreset(select.data("kendoDropDownList").value());
        windowWidget.close();
      });
      deleteButton.on("click", () => {
        const value = select.data("kendoDropDownList").value();
        if (!value) {
          global.CrudUtils.showMessage("Selecione uma ordenacao salva para excluir.", "warning");
          return;
        }
        global.CrudUtils.confirm("Deseja excluir esta ordenacao?", {
          title: "Confirmar exclusao",
          confirmText: "Excluir",
          confirmIcon: "trash"
        }).then((confirmed) => {
          if (!confirmed) {
            return;
          }
          this.layoutManager.deleteSortPreset(value).then(() => {
            windowWidget.close();
            global.CrudUtils.showMessage("Ordenacao excluida.", "success");
            this.render();
          }).catch((error) => {
            const normalized = global.CrudUtils.unwrapError(error, "Erro ao excluir ordenacao.");
            global.CrudUtils.showMessage(normalized.message, "error");
          });
        });
      });
      newButton.on("click", () => {
        select.data("kendoDropDownList").value("");
        loadPreset(null, true);
      });
      addRowButton.on("click", () => addSortRow({}));
      closeButton.on("click", function() {
        windowWidget.close();
      });
      saveButton.on("click", () => {
        const sort = collectSortRows();
        this.layoutManager.saveSortPreset({
          id: editingSortId,
          name: nameInput.data("kendoTextBox").value(),
          isDefault: defaultInput.is(":checked"),
          scope: this.getPreferenceScope(scopeInput),
          sort
        }).then(() => {
          windowWidget.close();
          global.CrudUtils.showMessage("Ordenacao salva.", "success");
          this.render();
        }).catch((error) => {
          const normalized = global.CrudUtils.unwrapError(error, "Erro ao salvar ordenacao.");
          global.CrudUtils.showMessage(normalized.message, "error");
        });
      });

      const initialPreset = this.findSavedSort(this.getActiveSortId());
      loadPreset(initialPreset, !initialPreset);
      windowWidget.center().open();
    }

    getSortableSortFields() {
      return global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) {
          return column.field && column.visible !== false && column.sortable !== false;
        })
        .map(function(column) {
          return {
            field: column.field,
            title: column.title || column.field
          };
        });
    }

    getSavedSorts() {
      return global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedSorts);
    }

    getActiveSortId() {
      const userLayout = this.definition.userLayout || {};
      if (userLayout.activeSortId) {
        return userLayout.activeSortId;
      }
      const defaultSort = this.getSavedSorts().find(function(item) {
        return item.isDefault;
      });
      return defaultSort ? defaultSort.id : "";
    }

    findSavedSort(sortId) {
      return this.getSavedSorts().find(function(item) {
        return item.id === sortId;
      }) || null;
    }

    getSortPresetDataSource() {
      return [{
        id: "",
        name: "Padrao do sistema"
      }].concat(this.getSavedSorts().map(function(item) {
        const suffix = (item.isDefault ? " (padrao)" : "") + (item.scope === "global" ? " (todos)" : "");
        return {
          id: item.id,
          name: item.name + suffix
        };
      }));
    }

    getSystemSort() {
      return global.CrudUtils.ensureArray(this.definition.query && this.definition.query.defaultSort);
    }

    getCurrentSortForEditor() {
      const current = this.gridRenderer && typeof this.gridRenderer.getCurrentSort === "function"
        ? this.gridRenderer.getCurrentSort()
        : [];
      return current.length ? current : this.getSystemSort();
    }

    formatSortSummary(sort) {
      const fields = this.getSortableSortFields().reduce(function(acc, field) {
        acc[field.field] = field.title;
        return acc;
      }, {});
      return global.CrudUtils.ensureArray(sort).map(function(item) {
        const direction = item.dir === "desc" ? "desc" : "asc";
        return (fields[item.field] || item.field) + " " + (direction === "desc" ? "descendente" : "ascendente");
      }).join(", ");
    }

    applySortPreset(sortId) {
      const preset = this.findSavedSort(sortId);
      const sort = preset ? global.CrudUtils.clone(preset.sort) : this.getSystemSort();
      if (this.definition.userLayout) {
        this.definition.userLayout.activeSortId = preset ? preset.id : null;
        if (this.definition.userLayout.grid) {
          this.definition.userLayout.grid.sort = global.CrudUtils.clone(sort);
        }
      }
      if (this.gridRenderer && typeof this.gridRenderer.setSort === "function") {
        this.gridRenderer.setSort(sort);
      }
      this.updateLastUpdated();
    }

    openGroupWindow() {
      const fields = this.getGroupableFields();
      if (!fields.length) {
        global.CrudUtils.showMessage("Nao existem colunas agrupaveis configuradas.", "warning");
        return;
      }
      const fieldOptions = [{
        field: "",
        title: "Selecione uma coluna"
      }].concat(fields);
      const aggregateFieldOptions = [{
        field: "",
        title: "Selecione uma coluna"
      }].concat(this.getAggregateFields());
      const aggregateOperationOptions = [
        { aggregate: "count", text: "Contar" },
        { aggregate: "sum", text: "Somar" }
      ];

      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-group-window\"></div>").appendTo(wrapper);
      const selectorField = $("<div class=\"crud-field\"></div>").appendTo(content);
      $("<label for=\"crud-group-preset-select\">Agrupamento atual</label>").appendTo(selectorField);
      const select = $("<input id=\"crud-group-preset-select\">").appendTo(selectorField);
      const summary = $("<p class=\"crud-layout-note\"></p>").appendTo(content);

      const selectorActions = $("<div class=\"crud-layout-menu-actions\"></div>").appendTo(content);
      const applySelectedButton = $("<button type=\"button\">Aplicar selecionado</button>").appendTo(selectorActions);
      const deleteButton = $("<button type=\"button\">Excluir selecionado</button>").appendTo(selectorActions);
      const newButton = $("<button type=\"button\">Novo agrupamento</button>").appendTo(selectorActions);

      const form = $("<div class=\"crud-group-form\"></div>").appendTo(content);
      const nameField = $("<div class=\"crud-field\"></div>").appendTo(form);
      $("<label for=\"crud-group-name\">Nome do agrupamento</label>").appendTo(nameField);
      const nameInput = $("<input id=\"crud-group-name\">").appendTo(nameField);
      nameInput.kendoTextBox();

      const defaultField = $("<label class=\"crud-checkbox-field\"></label>").appendTo(form);
      const defaultInput = $("<input type=\"checkbox\" id=\"crud-group-default\">").appendTo(defaultField);
      $("<span>Usar como agrupamento padrao</span>").appendTo(defaultField);
      const scopeInput = this.renderPreferenceScopeField(form, "crud-group-global");

      const rowsContainer = $("<div class=\"crud-group-rows\"></div>").appendTo(form);
      $("<h3 class=\"crud-group-section-title\">Totais do agrupamento</h3>").appendTo(form);
      $("<p class=\"crud-layout-note\">Escolha as colunas que devem ser contadas ou somadas em cada grupo.</p>").appendTo(form);
      const aggregateRowsContainer = $("<div class=\"crud-aggregate-rows\"></div>").appendTo(form);
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(form);
      const addRowButton = $("<button type=\"button\">Adicionar campo</button>").appendTo(actions);
      const addAggregateButton = $("<button type=\"button\">Adicionar total</button>").appendTo(actions);
      const applyButton = $("<button type=\"button\">Aplicar agrupamento</button>").appendTo(actions);
      const saveButton = $("<button type=\"button\">Salvar agrupamento</button>").appendTo(actions);
      const clearButton = $("<button type=\"button\">Limpar agrupamento</button>").appendTo(actions);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      let editingGroupId = null;
      let groupRows = [];
      let aggregateRows = [];

      const collectGroupRows = () => {
        const selected = [];
        const used = {};
        groupRows.forEach(function(item) {
          if (!document.body.contains(item.row[0])) {
            return;
          }
          const field = item.fieldInput.data("kendoDropDownList").value();
          const dir = item.dirInput.data("kendoDropDownList").value() === "desc" ? "desc" : "asc";
          if (!field || used[field]) {
            return;
          }
          used[field] = true;
          selected.push({ field, dir });
        });
        return selected;
      };

      const collectAggregateRows = () => {
        const selected = [];
        const used = {};
        aggregateRows.forEach(function(item) {
          if (!document.body.contains(item.row[0])) {
            return;
          }
          const field = item.fieldInput.data("kendoDropDownList").value();
          const aggregate = item.aggregateInput.data("kendoDropDownList").value();
          const operation = aggregate === "sum" ? "sum" : aggregate === "count" ? "count" : "";
          const key = field + ":" + operation;
          if (!field || !operation || used[key]) {
            return;
          }
          used[key] = true;
          selected.push({ field, aggregate: operation });
        });
        return selected;
      };

      const updateSummary = () => {
        const group = collectGroupRows();
        const aggregates = collectAggregateRows();
        summary.text(this.formatGroupSummary(group, aggregates) || "Sem agrupamento configurado.");
      };

      const addGroupRow = (item) => {
        const row = $("<div class=\"crud-group-row\"></div>").appendTo(rowsContainer);
        const fieldInput = $("<input>").appendTo(row);
        const dirInput = $("<input>").appendTo(row);
        const removeButton = $("<button type=\"button\">Remover</button>").appendTo(row);
        const initialField = item && item.field ? item.field : "";

        fieldInput.kendoDropDownList({
          dataTextField: "title",
          dataValueField: "field",
          dataSource: fieldOptions,
          value: initialField,
          change: updateSummary
        });
        dirInput.kendoDropDownList({
          dataTextField: "text",
          dataValueField: "dir",
          dataSource: [
            { dir: "asc", text: "Ascendente" },
            { dir: "desc", text: "Descendente" }
          ],
          value: item && item.dir === "desc" ? "desc" : "asc",
          change: updateSummary
        });
        removeButton.kendoButton({ icon: "trash" });
        removeButton.on("click", function() {
          kendo.destroy(row);
          row.remove();
          updateSummary();
        });
        groupRows.push({ row, fieldInput, dirInput });
        updateSummary();
      };

      const addAggregateRow = (item) => {
        const row = $("<div class=\"crud-aggregate-row\"></div>").appendTo(aggregateRowsContainer);
        const fieldInput = $("<input>").appendTo(row);
        const aggregateInput = $("<input>").appendTo(row);
        const removeButton = $("<button type=\"button\">Remover</button>").appendTo(row);
        const initialField = item && item.field ? item.field : "";

        fieldInput.kendoDropDownList({
          dataTextField: "title",
          dataValueField: "field",
          dataSource: aggregateFieldOptions,
          value: initialField,
          change: updateSummary
        });
        aggregateInput.kendoDropDownList({
          dataTextField: "text",
          dataValueField: "aggregate",
          dataSource: aggregateOperationOptions,
          value: item && item.aggregate === "sum" ? "sum" : "count",
          change: updateSummary
        });
        removeButton.kendoButton({ icon: "trash" });
        removeButton.on("click", function() {
          kendo.destroy(row);
          row.remove();
          updateSummary();
        });
        aggregateRows.push({ row, fieldInput, aggregateInput });
        updateSummary();
      };

      const renderGroupRows = (group) => {
        kendo.destroy(rowsContainer);
        rowsContainer.empty();
        groupRows = [];
        const rows = global.CrudUtils.ensureArray(group);
        if (!rows.length) {
          addGroupRow({});
          return;
        }
        rows.forEach(addGroupRow);
      };

      const renderAggregateRows = (aggregates) => {
        kendo.destroy(aggregateRowsContainer);
        aggregateRowsContainer.empty();
        aggregateRows = [];
        global.CrudUtils.ensureArray(aggregates).forEach(addAggregateRow);
      };

      const loadPreset = (preset, useCurrent) => {
        editingGroupId = preset ? preset.id : null;
        nameInput.data("kendoTextBox").value(preset ? preset.name : "");
        defaultInput.prop("checked", Boolean(preset && preset.isDefault));
        scopeInput.prop("checked", Boolean(preset && preset.scope === "global"));
        const group = preset
          ? preset.group
          : useCurrent
            ? this.getCurrentGroupForEditor()
            : this.getSystemGroup();
        const aggregates = preset
          ? preset.aggregates
          : useCurrent
            ? this.getCurrentGroupAggregatesForEditor()
            : this.getSystemGroupAggregates();
        renderGroupRows(group);
        renderAggregateRows(aggregates);
        summary.text(this.formatGroupSummary(group, aggregates) || "Sem agrupamento configurado.");
      };

      select.kendoDropDownList({
        dataTextField: "name",
        dataValueField: "id",
        dataSource: this.getGroupPresetDataSource(),
        value: this.getActiveGroupId(),
        change: () => {
          const preset = this.findSavedGroup(select.data("kendoDropDownList").value());
          loadPreset(preset, false);
        }
      });

      applySelectedButton.kendoButton({ themeColor: "primary", icon: "check" });
      deleteButton.kendoButton({ icon: "trash" });
      newButton.kendoButton({ icon: "plus" });
      addRowButton.kendoButton({ icon: "plus" });
      addAggregateButton.kendoButton({ icon: "sum" });
      applyButton.kendoButton({ themeColor: "primary", icon: "check" });
      saveButton.kendoButton({ themeColor: "primary", icon: "save" });
      clearButton.kendoButton({ icon: "ungroup" });
      closeButton.kendoButton();

      wrapper.kendoWindow({
        title: "Agrupamentos",
        modal: true,
        width: Math.min(720, Math.max(340, window.innerWidth - 24)),
        visible: false,
        close: function() {
          kendo.destroy(content);
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      applySelectedButton.on("click", () => {
        this.applyGroupPreset(select.data("kendoDropDownList").value());
        windowWidget.close();
      });
      deleteButton.on("click", () => {
        const value = select.data("kendoDropDownList").value();
        if (!value) {
          global.CrudUtils.showMessage("Selecione um agrupamento salvo para excluir.", "warning");
          return;
        }
        global.CrudUtils.confirm("Deseja excluir este agrupamento?", {
          title: "Confirmar exclusao",
          confirmText: "Excluir",
          confirmIcon: "trash"
        }).then((confirmed) => {
          if (!confirmed) {
            return;
          }
          this.layoutManager.deleteGroupPreset(value).then(() => {
            windowWidget.close();
            global.CrudUtils.showMessage("Agrupamento excluido.", "success");
            this.render();
          }).catch((error) => {
            const normalized = global.CrudUtils.unwrapError(error, "Erro ao excluir agrupamento.");
            global.CrudUtils.showMessage(normalized.message, "error");
          });
        });
      });
      newButton.on("click", () => {
        select.data("kendoDropDownList").value("");
        loadPreset(null, true);
      });
      addRowButton.on("click", () => addGroupRow({}));
      addAggregateButton.on("click", () => addAggregateRow({}));
      applyButton.on("click", () => {
        const aggregates = collectAggregateRows();
        if (!this.validateGroupAggregates(aggregates)) {
          return;
        }
        this.applyGroup(collectGroupRows(), aggregates);
        windowWidget.close();
      });
      saveButton.on("click", () => {
        const group = collectGroupRows();
        const aggregates = collectAggregateRows();
        if (!this.validateGroupAggregates(aggregates)) {
          return;
        }
        this.layoutManager.saveGroupPreset({
          id: editingGroupId,
          name: nameInput.data("kendoTextBox").value(),
          isDefault: defaultInput.is(":checked"),
          scope: this.getPreferenceScope(scopeInput),
          group,
          aggregates
        }).then(() => {
          windowWidget.close();
          global.CrudUtils.showMessage("Agrupamento salvo.", "success");
          this.render();
        }).catch((error) => {
          const normalized = global.CrudUtils.unwrapError(error, "Erro ao salvar agrupamento.");
          global.CrudUtils.showMessage(normalized.message, "error");
        });
      });
      clearButton.on("click", () => {
        this.applyGroup([], []);
        windowWidget.close();
      });
      closeButton.on("click", function() {
        windowWidget.close();
      });

      const initialPreset = this.findSavedGroup(this.getActiveGroupId());
      loadPreset(initialPreset, !initialPreset);
      windowWidget.center().open();
    }

    getGroupableFields() {
      return global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) {
          return column.field && column.visible !== false && column.groupable !== false;
        })
        .map(function(column) {
          return {
            field: column.field,
            title: column.title || column.field
          };
        });
    }

    getAggregateFields() {
      return global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) {
          return column.field && column.visible !== false;
        })
        .map((column) => {
          const field = this.definition.dataModel && this.definition.dataModel.fields
            ? this.definition.dataModel.fields[column.field] || {}
            : {};
          return {
            field: column.field,
            title: column.title || field.label || column.field,
            type: field.type
          };
        });
    }

    isNumericAggregateField(fieldName) {
      const field = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields[fieldName]
        : null;
      return Boolean(field && ["integer", "decimal", "number"].indexOf(field.type) !== -1);
    }

    normalizeGroupAggregates(aggregates) {
      const fields = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields
        : {};
      const used = {};
      return global.CrudUtils.ensureArray(aggregates).filter(function(item) {
        const aggregate = item && item.aggregate === "sum" ? "sum" : item && item.aggregate === "count" ? "count" : null;
        if (!item || !item.field || !fields[item.field] || !aggregate) {
          return false;
        }
        const key = item.field + ":" + aggregate;
        if (used[key]) {
          return false;
        }
        used[key] = true;
        return true;
      }).map(function(item) {
        return {
          field: item.field,
          aggregate: item.aggregate === "sum" ? "sum" : "count"
        };
      });
    }

    validateGroupAggregates(aggregates) {
      const invalid = this.normalizeGroupAggregates(aggregates).find((item) => {
        return item.aggregate === "sum" && !this.isNumericAggregateField(item.field);
      });
      if (invalid) {
        global.CrudUtils.showMessage("A opcao Somar so pode ser usada em campos numericos.", "warning");
        return false;
      }
      return true;
    }

    getSavedGroups() {
      return global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedGroups);
    }

    getActiveGroupId() {
      const userLayout = this.definition.userLayout || {};
      if (userLayout.activeGroupId) {
        return userLayout.activeGroupId;
      }
      const defaultGroup = this.getSavedGroups().find(function(item) {
        return item.isDefault;
      });
      return defaultGroup ? defaultGroup.id : "";
    }

    findSavedGroup(groupId) {
      return this.getSavedGroups().find(function(item) {
        return item.id === groupId;
      }) || null;
    }

    getGroupPresetDataSource() {
      return [{
        id: "",
        name: "Sem agrupamento"
      }].concat(this.getSavedGroups().map(function(item) {
        const suffix = (item.isDefault ? " (padrao)" : "") + (item.scope === "global" ? " (todos)" : "");
        return {
          id: item.id,
          name: item.name + suffix
        };
      }));
    }

    getSystemGroup() {
      return [];
    }

    getSystemGroupAggregates() {
      return [];
    }

    getCurrentGroupForEditor() {
      const current = this.gridRenderer && typeof this.gridRenderer.getCurrentGroup === "function"
        ? this.gridRenderer.getCurrentGroup()
        : [];
      return current.length ? current : this.getSystemGroup();
    }

    getCurrentGroupAggregatesForEditor() {
      const current = this.gridRenderer && typeof this.gridRenderer.getCurrentGroupAggregates === "function"
        ? this.gridRenderer.getCurrentGroupAggregates()
        : [];
      if (current.length) {
        return current;
      }
      const gridLayout = this.definition.userLayout && this.definition.userLayout.grid
        ? this.definition.userLayout.grid
        : {};
      return this.normalizeGroupAggregates(gridLayout.groupAggregates || this.extractAggregatesFromGroup(gridLayout.group));
    }

    extractAggregatesFromGroup(group) {
      const aggregates = [];
      global.CrudUtils.ensureArray(group).forEach(function(item) {
        global.CrudUtils.ensureArray(item && item.aggregates).forEach(function(aggregate) {
          aggregates.push(aggregate);
        });
      });
      return aggregates;
    }

    formatGroupSummary(group, aggregates) {
      const fields = this.getGroupableFields().reduce(function(acc, field) {
        acc[field.field] = field.title;
        return acc;
      }, {});
      const groupSummary = global.CrudUtils.ensureArray(group).map(function(item) {
        const direction = item.dir === "desc" ? "desc" : "asc";
        return (fields[item.field] || item.field) + " " + (direction === "desc" ? "descendente" : "ascendente");
      }).join(", ");
      const aggregateFields = this.getAggregateFields().reduce(function(acc, field) {
        acc[field.field] = field.title;
        return acc;
      }, {});
      const aggregateSummary = this.normalizeGroupAggregates(aggregates).map(function(item) {
        const operation = item.aggregate === "sum" ? "somar" : "contar";
        return operation + " " + (aggregateFields[item.field] || item.field);
      }).join(", ");
      if (groupSummary && aggregateSummary) {
        return groupSummary + " | Totais: " + aggregateSummary;
      }
      return groupSummary || (aggregateSummary ? "Totais: " + aggregateSummary : "");
    }

    applyGroup(group, aggregates) {
      const normalized = global.CrudUtils.ensureArray(group)
        .filter(function(item) {
          return item && item.field;
        })
        .map(function(item) {
          return {
            field: item.field,
            dir: item.dir === "desc" ? "desc" : "asc"
          };
        });
      const normalizedAggregates = this.normalizeGroupAggregates(aggregates);
      if (this.definition.userLayout && this.definition.userLayout.grid) {
        this.definition.userLayout.activeGroupId = null;
        this.definition.userLayout.grid.group = global.CrudUtils.clone(normalized);
        this.definition.userLayout.grid.groupAggregates = global.CrudUtils.clone(normalizedAggregates);
      }
      if (this.gridRenderer && typeof this.gridRenderer.setGroup === "function") {
        this.gridRenderer.setGroup(normalized, normalizedAggregates);
      }
      this.updateLastUpdated();
    }

    applyGroupPreset(groupId) {
      const preset = this.findSavedGroup(groupId);
      const group = preset ? global.CrudUtils.clone(preset.group) : this.getSystemGroup();
      const aggregates = preset ? this.normalizeGroupAggregates(preset.aggregates) : this.getSystemGroupAggregates();
      if (this.definition.userLayout) {
        this.definition.userLayout.activeGroupId = preset ? preset.id : null;
        if (this.definition.userLayout.grid) {
          this.definition.userLayout.grid.group = global.CrudUtils.clone(group);
          this.definition.userLayout.grid.groupAggregates = global.CrudUtils.clone(aggregates);
        }
      }
      if (this.gridRenderer && typeof this.gridRenderer.setGroup === "function") {
        this.gridRenderer.setGroup(group, aggregates);
      }
      this.updateLastUpdated();
    }

    saveLayout() {
      this.openSaveLayoutWindow();
    }

    openLayoutWindow() {
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-layout-menu\"></div>").appendTo(wrapper);
      const selectorField = $("<div class=\"crud-field\"></div>").appendTo(content);
      $("<label for=\"crud-layout-window-select\">Leiaute atual</label>").appendTo(selectorField);
      const select = $("<input id=\"crud-layout-window-select\">").appendTo(selectorField);
      const layouts = global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedLayouts);
      const dataSource = [{
        id: "",
        name: "Padrao do sistema"
      }].concat(layouts.map(function(layout) {
        const suffix = (layout.isDefault ? " (padrao)" : "") + (layout.scope === "global" ? " (todos)" : "");
        return {
          id: layout.id,
          name: layout.name + suffix
        };
      }));

      select.kendoDropDownList({
        dataTextField: "name",
        dataValueField: "id",
        dataSource,
        value: this.definition.userLayout && this.definition.userLayout.activeLayoutId || ""
      });

      const actions = $("<div class=\"crud-layout-menu-actions\"></div>").appendTo(content);
      const applyButton = $("<button type=\"button\">Aplicar</button>").appendTo(actions);
      const saveButton = $("<button type=\"button\">Salvar novo leiaute</button>").appendTo(actions);
      const restoreButton = $("<button type=\"button\">Padrao do sistema</button>").appendTo(actions);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);

      applyButton.kendoButton({ themeColor: "primary", icon: "check" });
      saveButton.kendoButton({ icon: "save" });
      restoreButton.kendoButton({ icon: "undo" });
      closeButton.kendoButton();

      wrapper.kendoWindow({
        title: "Leiautes",
        modal: true,
        width: 420,
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      applyButton.on("click", () => {
        const value = select.data("kendoDropDownList").value();
        windowWidget.close();
        this.applyLayout(value);
      });
      saveButton.on("click", () => {
        windowWidget.close();
        this.openSaveLayoutWindow();
      });
      restoreButton.on("click", () => {
        windowWidget.close();
        this.restoreLayout();
      });
      closeButton.on("click", function() {
        windowWidget.close();
      });

      windowWidget.center().open();
    }

    isColumnFreezeEnabled() {
      const config = this.definition.grid && this.definition.grid.freezeColumns;
      return Boolean(config && config.enabled);
    }

    renderFrozenColumnsField(container) {
      const field = $("<div class=\"crud-field\"></div>").appendTo(container);
      $("<label for=\"crud-freeze-columns-select\">Colunas congeladas</label>").appendTo(field);
      const select = $("<select id=\"crud-freeze-columns-select\" multiple=\"multiple\"></select>").appendTo(field);
      const dataSource = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) { return column.field && column.visible !== false; })
        .map(function(column) {
          return {
            field: column.field,
            title: column.title || column.field
          };
        });

      select.kendoMultiSelect({
        dataTextField: "title",
        dataValueField: "field",
        dataSource,
        value: this.getFrozenColumns()
      });

      this.frozenColumnsSelect = select;
    }

    getFrozenColumns() {
      if (this.gridRenderer && typeof this.gridRenderer.getCurrentFrozenFields === "function") {
        return this.gridRenderer.getCurrentFrozenFields();
      }

      const saved = this.definition.userLayout && this.definition.userLayout.grid && this.definition.userLayout.grid.columns
        ? this.definition.userLayout.grid.columns.frozen
        : null;
      if (Array.isArray(saved)) {
        return saved;
      }

      return [];
    }

    getSelectedFrozenColumns() {
      if (!this.frozenColumnsSelect) {
        return null;
      }
      const widget = this.frozenColumnsSelect.data("kendoMultiSelect");
      if (!widget) {
        return null;
      }
      const value = widget.value();
      if (this.gridRenderer && typeof this.gridRenderer.normalizeFrozenFields === "function") {
        return this.gridRenderer.normalizeFrozenFields(value);
      }
      return value;
    }

    openSaveLayoutWindow() {
      const wrapper = $("<div></div>").appendTo(document.body);
      const form = $("<div class=\"crud-layout-form\"></div>").appendTo(wrapper);
      const nameField = $("<div class=\"crud-field\"></div>").appendTo(form);
      $("<label for=\"crud-layout-name\">Nome do leiaute</label>").appendTo(nameField);
      const nameInput = $("<input id=\"crud-layout-name\">").appendTo(nameField);
      nameInput.kendoTextBox();

      const defaultField = $("<label class=\"crud-checkbox-field\"></label>").appendTo(form);
      const defaultInput = $("<input type=\"checkbox\" id=\"crud-layout-default\">").appendTo(defaultField);
      $("<span>Usar como leiaute padrao</span>").appendTo(defaultField);
      const scopeInput = this.renderPreferenceScopeField(form, "crud-layout-global");

      if (this.isColumnFreezeEnabled()) {
        $("<p class=\"crud-layout-note\"></p>")
          .text("Congelar colunas e uma configuracao somente para desktop.")
          .appendTo(form);
        this.renderFrozenColumnsField(form);
      } else {
        this.frozenColumnsSelect = null;
      }

      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(form);
      const saveButton = $("<button type=\"button\">Salvar</button>").appendTo(actions);
      const cancelButton = $("<button type=\"button\">Cancelar</button>").appendTo(actions);
      saveButton.kendoButton({ themeColor: "primary", icon: "save" });
      cancelButton.kendoButton();

      wrapper.kendoWindow({
        title: "Salvar leiaute",
        modal: true,
        width: 420,
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      cancelButton.on("click", function() {
        windowWidget.close();
      });
      saveButton.on("click", () => {
        const metadata = {
          id: null,
          name: nameInput.data("kendoTextBox").value(),
          isDefault: defaultInput.is(":checked"),
          scope: this.getPreferenceScope(scopeInput)
        };
        const frozenColumns = this.getSelectedFrozenColumns();
        if (frozenColumns) {
          metadata.frozenColumns = frozenColumns;
        }

        this.layoutManager.save(metadata).then(() => {
          windowWidget.close();
          global.CrudUtils.showMessage("Leiaute salvo.", "success");
          this.render();
        }).catch((error) => {
          const normalized = global.CrudUtils.unwrapError(error, "Erro ao salvar leiaute.");
          global.CrudUtils.showMessage(normalized.message, "error");
        });
      });

      windowWidget.center().open();
    }

    applyLayout(layoutId) {
      if (!this.layoutManager.apply(layoutId)) {
        global.CrudUtils.showMessage("Leiaute nao encontrado.", "error");
        return;
      }
      this.render();
    }

    restoreLayout() {
      this.layoutManager.restore().then(() => {
        global.CrudUtils.showMessage("Padrao do sistema restaurado.", "success");
        this.render();
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao restaurar layout.");
        global.CrudUtils.showMessage(normalized.message, "error");
      });
    }

    renderPreferenceScopeField(container, inputId) {
      const scopeField = $("<label class=\"crud-checkbox-field crud-preference-scope-field\"></label>").appendTo(container);
      const scopeInput = $("<input type=\"checkbox\">").attr("id", inputId).appendTo(scopeField);
      $("<span>Usar em todos os assinantes</span>").appendTo(scopeField);
      return scopeInput;
    }

    getPreferenceScope(scopeInput) {
      return scopeInput && scopeInput.is(":checked") ? "global" : "tenant";
    }
  }

  global.CrudEngine = CrudEngine;
})(window, jQuery);
