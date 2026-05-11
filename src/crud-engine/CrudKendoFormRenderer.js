(function(global, $) {
  "use strict";

  class CrudKendoFormRenderer {
    constructor(options) {
      this.definition = options.definition;
      this.httpClient = options.httpClient;
      this.config = options.config || {};
      this.securityPolicy = options.securityPolicy || {};
      this.onSaved = options.onSaved || function() {};
      this.onCreate = options.onCreate || function() {};
      this.onEdit = options.onEdit || function() {};
      this.onDelete = options.onDelete || function() {};
      this.onLogs = options.onLogs || function() {};
      this.onButtonAction = options.onButtonAction || function() { return Promise.resolve(true); };
      this.onBeforeAction = options.onBeforeAction || function() { return Promise.resolve(true); };
      this.onActionCanceled = options.onActionCanceled || function() {};
      this.onClosed = options.onClosed || function() {};
      this.onDirtyChange = options.onDirtyChange || function() {};
      this.onNavigate = options.onNavigate || function() { return Promise.resolve(null); };
      this.getNavigationState = options.getNavigationState || function() {
        return { previous: false, next: false };
      };
    }

    open(mode, data) {
      this.passiveData = mode === "create" ? {} : global.CrudUtils.clone(data || {});
      this.mode = this.normalizeMode(mode);
      this.actionMode = this.getActionMode(this.mode);
      this.data = data ? global.CrudUtils.clone(data) : {};
      this.currentStepIndex = 0;
      this.formRuntime = {
        readonly: {},
        required: {},
        visible: {},
        steps: {}
      };
      this.customCodeState = {};

      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<form class=\"crud-form\"></form>").appendTo(wrapper);
      this.wrapper = wrapper;
      this.formElement = content;
      this.inputs = {};

      this.renderForm();
      this.resetDirtyState();

      wrapper.kendoWindow({
        title: this.getTitle(mode),
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: this.getWindowWidth(),
        visible: false,
        maximize: function() {
          wrapper.addClass("crud-form-window-maximized");
        },
        restore: function() {
          wrapper.removeClass("crud-form-window-maximized");
        },
        close: (event) => {
          if (!this.forceClosing && this.hasUnsavedChanges()) {
            event.preventDefault();
            this.confirmDiscardChanges().then((confirmed) => {
              if (confirmed) {
                this.forceClosing = true;
                this.close(true);
              }
            });
            return;
          }
          this.onClosed();
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      this.window = wrapper.data("kendoWindow");
      this.window.center().open();
      if (this.shouldOpenMaximized()) {
        this.window.maximize();
      }
      this.executeFormLifecycleEvents("open");
    }

    close(force) {
      if (this.window) {
        this.forceClosing = force === true;
        this.window.close();
      }
    }

    normalizeMode(mode) {
      return ["create", "edit", "delete", "view"].indexOf(mode) === -1 ? "view" : mode;
    }

    getActionMode(mode) {
      return ["create", "edit", "delete"].indexOf(mode) === -1 ? null : mode;
    }

    renderForm() {
      if (!this.formElement) {
        return;
      }
      kendo.destroy(this.formElement);
      this.formElement.empty();
      this.formElement
        .attr("id", this.getFormDomId())
        .attr("data-form-id", this.getFormDomId());
      this.inputs = {};
      this.formElement.toggleClass("crud-form-has-situation", this.isSituationEnabled());
      this.renderHeaderAppbar(this.formElement);
      this.renderSituationBar(this.formElement);
      const body = $("<div class=\"crud-form-body\"></div>").appendTo(this.formElement);
      this.renderContent(body);
      this.renderFooterAppbar(this.formElement);
      this.bindFormEvents();
      this.executeFormLifecycleEvents("afterLoad");
      if (this.window) {
        this.window.title(this.getTitle(this.mode));
        this.window.center();
      }
    }

    activateAction(mode) {
      const nextMode = this.normalizeMode(mode);
      this.mode = nextMode;
      this.actionMode = this.getActionMode(nextMode);
      this.data = nextMode === "create" ? {} : global.CrudUtils.clone(this.passiveData || this.data || {});
      this.renderForm();
      this.resetDirtyState();
    }

    deactivateAction() {
      this.mode = "view";
      this.actionMode = null;
      this.data = global.CrudUtils.clone(this.passiveData || {});
      this.renderForm();
      this.resetDirtyState();
      this.onActionCanceled();
    }

    renderHeaderAppbar(container) {
      const appbar = $("<div class=\"crud-form-appbar crud-form-header-appbar\"></div>").appendTo(container);
      const hasRecord = this.hasCurrentRecord();
      const hasActiveAction = Boolean(this.actionMode);
      const disableNavigationForAction = hasActiveAction && !this.canNavigateWhileActionActive();
      const navigationState = this.getCurrentNavigationState();
      let previousButton = null;
      let nextButton = null;
      let createButton = null;
      let editButton = null;
      let deleteButton = null;
      let logsButton = null;
      let printButton = null;
      let otherActionsButton = null;

      if (this.isNavigationEnabled()) {
        previousButton = this.renderHeaderButton(appbar, {
          label: "",
          title: this.getButtonTitle("previous", "Registro anterior"),
          icon: "chevron-left",
          iconOnly: true,
          cssClass: "crud-form-nav-button",
          config: this.getFormButton("previous"),
          disabled: disableNavigationForAction || !navigationState.previous,
          click: () => this.navigate("previous")
        });
        nextButton = this.renderHeaderButton(appbar, {
          label: "",
          title: this.getButtonTitle("next", "Proximo registro"),
          icon: "chevron-right",
          iconOnly: true,
          cssClass: "crud-form-nav-button",
          config: this.getFormButton("next"),
          disabled: disableNavigationForAction || !navigationState.next,
          click: () => this.navigate("next")
        });
      }

      if (this.shouldShowHeaderActions()) {
        createButton = this.renderHeaderButton(appbar, {
          label: this.getButtonLabel("create", "Incluir"),
          icon: "plus",
          config: this.getFormButton("create"),
          permission: "create",
          disabled: hasActiveAction,
          active: this.actionMode === "create",
          click: () => {
            this.activateActionFromButton("create");
          }
        });
        editButton = this.renderHeaderButton(appbar, {
          label: this.getButtonLabel("edit", "Alterar"),
          icon: "pencil",
          config: this.getFormButton("edit"),
          permission: "edit",
          disabled: !hasRecord || hasActiveAction,
          active: this.actionMode === "edit",
          click: () => {
            if (!hasRecord) {
              return;
            }
            this.activateActionFromButton("edit");
          }
        });
        deleteButton = this.renderHeaderButton(appbar, {
          label: this.getButtonLabel("delete", "Excluir"),
          icon: "trash",
          config: this.getFormButton("delete"),
          permission: "delete",
          disabled: !hasRecord || hasActiveAction,
          active: this.actionMode === "delete",
          click: () => {
            if (!hasRecord) {
              return;
            }
            this.activateActionFromButton("delete");
          }
        });
        logsButton = this.renderHeaderButton(appbar, {
          label: this.getButtonLabel("logs", "Logs"),
          icon: "list-unordered",
          config: this.getFormButton("logs"),
          visible: this.isLogsEnabled(),
          disabled: !hasRecord,
          click: () => this.openCurrentRecordLogs()
        });
        printButton = this.renderPrintButton(appbar);
        otherActionsButton = this.renderOtherActionsButton(appbar);
        this.renderConfiguredButtons(appbar, "header");
      }

      if (!previousButton && !nextButton && !createButton && !editButton && !deleteButton && !logsButton && !printButton && !otherActionsButton) {
        appbar.attr("hidden", true);
      }
    }

    renderHeaderButton(container, options) {
      const settings = options || {};
      const config = settings.config || {};
      if (settings.visible === false) {
        return null;
      }
      if (config.visible === false) {
        return null;
      }
      if (!this.isButtonVisibleIn(config)) {
        return null;
      }
      const permission = config.permission || settings.permission;
      if (permission && !global.CrudUtils.getPermission(this.definition, permission)) {
        return null;
      }
      const label = config.label || settings.label || "";
      const title = config.title || settings.title || label;
      const icon = config.icon || settings.icon;
      const button = $("<button type=\"button\"></button>")
        .text(settings.iconOnly ? "" : label)
        .attr("title", title)
        .attr("aria-label", title)
        .toggleClass("crud-form-action-active", Boolean(settings.active))
        .toggleClass("crud-icon-only-button", Boolean(settings.iconOnly))
        .toggleClass(settings.cssClass || "", Boolean(settings.cssClass))
        .appendTo(container);
      button.kendoButton({
        icon,
        enable: settings.disabled !== true && config.disabled !== true
      });
      button.on("click", function() {
        if (settings.disabled || config.disabled) {
          return;
        }
        settings.click();
      });
      return button;
    }

    renderConfiguredButtons(container, placement) {
      const standardActions = {
        previous: true,
        next: true,
        create: true,
        edit: true,
        delete: true,
        logs: true,
        print: true,
        save: true,
        confirm: true,
        cancel: true
      };
      global.CrudUtils.ensureArray(this.getFormConfig().buttons).forEach((buttonConfig) => {
        const action = buttonConfig && (buttonConfig.action || buttonConfig.id);
        if (!action || standardActions[action]) {
          return;
        }
        if ((buttonConfig.placement || "footer") !== placement) {
          return;
        }
        const requiresRecord = buttonConfig.requiresRecord !== false;
        this.renderHeaderButton(container, {
          label: buttonConfig.label || action,
          icon: buttonConfig.icon,
          config: buttonConfig,
          disabled: requiresRecord && !this.hasCurrentRecord(),
          click: () => this.executeConfiguredButton(buttonConfig)
        });
      });
    }

    getFormButton(action) {
      return global.CrudUtils.ensureArray(this.getFormConfig().buttons).find(function(button) {
        return button && (button.action === action || button.id === action);
      }) || null;
    }

    renderOtherActionsButton(container) {
      const config = this.getOtherActionsConfig();
      const actions = this.getOtherActions();
      if (!actions.length) {
        return null;
      }

      const wrapper = $("<span class=\"crud-other-actions\"></span>").appendTo(container);
      const label = config.label || "Outras acoes";
      const button = $("<button type=\"button\" class=\"crud-other-actions-button\"></button>")
        .attr("title", label)
        .attr("aria-haspopup", "menu")
        .attr("aria-expanded", "false")
        .text(label)
        .appendTo(wrapper);
      const menu = $("<div class=\"crud-other-actions-menu\" role=\"menu\" hidden></div>").appendTo(wrapper);
      const hasEnabledAction = actions.some((action) => !this.isOtherActionDisabled(action));

      button.kendoButton({
        icon: config.icon || "more-vertical",
        enable: hasEnabledAction
      });

      actions.forEach((action) => {
        const disabled = this.isOtherActionDisabled(action);
        const actionButton = $("<button type=\"button\" class=\"crud-other-action\" role=\"menuitem\"></button>")
          .attr("title", action.title || action.label)
          .text(action.label || action.id || action.action)
          .appendTo(menu);
        actionButton.kendoButton({
          icon: action.icon || "gear",
          enable: !disabled
        });
        actionButton.on("click", () => {
          if (disabled) {
            return;
          }
          this.closeOtherActions(menu, button);
          this.executeOtherAction(action);
        });
      });

      button.on("click", (event) => {
        event.stopPropagation();
        if (!hasEnabledAction) {
          return;
        }
        const isHidden = menu.prop("hidden");
        menu.prop("hidden", !isHidden);
        button.attr("aria-expanded", isHidden ? "true" : "false");
      });

      $(document).off("click.crudFormOtherActions").on("click.crudFormOtherActions", () => {
        this.closeOtherActions(menu, button);
      });

      wrapper.on("click", function(event) {
        event.stopPropagation();
      });

      return button;
    }

    closeOtherActions(menu, button) {
      if (menu) {
        menu.prop("hidden", true);
      }
      if (button) {
        button.attr("aria-expanded", "false");
      }
    }

    getOtherActionsConfig() {
      const configured = this.getFormConfig().otherActions;
      if (Array.isArray(configured)) {
        return {
          actions: configured
        };
      }
      return configured || {};
    }

    getOtherActions() {
      const config = this.getOtherActionsConfig();
      if (!config || config.enabled === false) {
        return [];
      }
      return global.CrudUtils.ensureArray(config.actions || config.items || config.options).filter((action) => {
        if (!action || action.visible === false) {
          return false;
        }
        if (!this.isButtonVisibleIn(action)) {
          return false;
        }
        if (!this.hasButtonTarget(action)) {
          return false;
        }
        if (action.permission && !global.CrudUtils.getPermission(this.definition, action.permission)) {
          return false;
        }
        return true;
      });
    }

    isOtherActionDisabled(action) {
      const requiresRecord = action.requiresRecord !== false;
      return action.disabled === true || (requiresRecord && !this.hasCurrentRecord());
    }

    executeOtherAction(action) {
      this.executeConfiguredButton(action, {
        event: "otherAction",
        otherAction: action.action || action.id
      });
    }

    renderPrintButton(container) {
      const config = this.getPrintConfig();
      const options = this.getPrintOptions();
      if (!options.length) {
        return null;
      }

      const requiresRecord = config.requiresRecord !== false;
      const disabled = requiresRecord && !this.hasCurrentRecord();
      const wrapper = $("<span class=\"crud-print-actions crud-form-print-actions\"></span>").appendTo(container);
      const label = config.label || "Imprimir";
      const button = $("<button type=\"button\" class=\"crud-print-actions-button\"></button>")
        .attr("title", label)
        .attr("aria-haspopup", "menu")
        .attr("aria-expanded", "false")
        .text(label)
        .appendTo(wrapper);
      const menu = $("<div class=\"crud-print-actions-menu\" role=\"menu\" hidden></div>").appendTo(wrapper);

      button.kendoButton({
        icon: config.icon || "print",
        enable: !disabled
      });

      options.forEach((item) => {
        const actionButton = $("<button type=\"button\" class=\"crud-print-action\" role=\"menuitem\"></button>")
          .attr("title", item.label)
          .text(item.label)
          .appendTo(menu);
        actionButton.kendoButton({
          icon: item.icon || this.getPrintIcon(item.format)
        });
        actionButton.on("click", () => {
          this.closePrintActions(menu, button);
          this.executePrintOption(item);
        });
      });

      button.on("click", (event) => {
        event.stopPropagation();
        if (disabled) {
          return;
        }
        const isHidden = menu.prop("hidden");
        menu.prop("hidden", !isHidden);
        button.attr("aria-expanded", isHidden ? "true" : "false");
      });

      $(document).off("click.crudFormPrintActions").on("click.crudFormPrintActions", () => {
        this.closePrintActions(menu, button);
      });

      wrapper.on("click", function(event) {
        event.stopPropagation();
      });

      return button;
    }

    closePrintActions(menu, button) {
      if (menu) {
        menu.prop("hidden", true);
      }
      if (button) {
        button.attr("aria-expanded", "false");
      }
    }

    getPrintConfig() {
      return this.getFormConfig().print || null;
    }

    getPrintOptions() {
      const config = this.getPrintConfig();
      if (!config || config.enabled === false) {
        return [];
      }
      return global.CrudUtils.ensureArray(config.options || config.formats).map((item) => {
        if (typeof item === "string") {
          return this.normalizePrintOption({ format: item }, config);
        }
        return this.normalizePrintOption(item || {}, config);
      }).filter(function(item) {
        return item && ["excel", "pdf", "csv"].indexOf(item.format) !== -1;
      });
    }

    normalizePrintOption(item, config) {
      const format = String(item.format || item.type || item.id || "").toLowerCase();
      if (!format) {
        return null;
      }
      const labels = {
        excel: "Excel",
        pdf: "PDF",
        csv: "CSV"
      };
      return Object.assign({}, item, {
        format,
        label: item.label || labels[format] || format.toUpperCase(),
        fileName: item.fileName || config.fileName
      });
    }

    getPrintIcon(format) {
      if (format === "excel" || format === "csv") {
        return "file-excel";
      }
      if (format === "pdf") {
        return "file-pdf";
      }
      return "print";
    }

    executePrintOption(option) {
      const mode = String(option.mode || option.source || "").toLowerCase();
      const api = this.definition.api || {};
      const hasApiTarget = this.hasButtonTarget(option) || Boolean(option.action && api[option.action]);
      if (mode === "browser" || (!hasApiTarget && option.browser !== false)) {
        this.printFormInBrowser(option);
        return;
      }

      this.executeConfiguredButton(Object.assign({
        action: "print",
        successMessage: option.successMessage || "Impressao solicitada."
      }, option), {
        event: "print",
        format: option.format,
        values: this.collectAllValues()
      });
    }

    printFormInBrowser(option) {
      const printWindow = window.open("", "_blank", "noopener,noreferrer");
      if (!printWindow) {
        window.print();
        return;
      }
      printWindow.document.open();
      printWindow.document.write(this.buildPrintableFormHtml(option || {}));
      printWindow.document.close();
      printWindow.focus();
      window.setTimeout(function() {
        printWindow.print();
      }, 100);
    }

    buildPrintableFormHtml(option) {
      const title = this.getTitle(this.mode);
      const fields = this.getPrintableFields();
      const values = this.collectAllValues();
      const rows = fields.map((item) => {
        const field = this.definition.dataModel.fields[item.field] || {};
        return "<tr><th>" + global.CrudUtils.escapeHtml(item.label || field.label || item.field) + "</th><td>" +
          global.CrudUtils.escapeHtml(this.formatReadonly(field, values[item.field])) + "</td></tr>";
      }).join("");
      return "<!doctype html><html><head><meta charset=\"utf-8\"><title>" +
        global.CrudUtils.escapeHtml(title) +
        "</title><style>body{font-family:Arial,sans-serif;margin:24px;color:#111827}h1{font-size:20px;margin:0 0 16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d0d5dd;padding:8px;text-align:left;vertical-align:top}th{width:32%;background:#f8fafc}</style></head><body><h1>" +
        global.CrudUtils.escapeHtml(title) +
        "</h1><table>" + rows + "</table></body></html>";
    }

    getPrintableFields() {
      const form = this.getFormConfig();
      const fields = [];
      const collect = function(section) {
        global.CrudUtils.ensureArray(section && section.fields).forEach(function(item) {
          if (item && item.field) {
            fields.push(item);
          }
        });
      };
      if (form.layout === "steps") {
        global.CrudUtils.ensureArray(form.steps).forEach(function(step) {
          global.CrudUtils.ensureArray(step.sections).forEach(collect);
          global.CrudUtils.ensureArray(step.fields).forEach(function(item) {
            if (typeof item === "string") {
              fields.push({ field: item });
            } else if (item && item.field) {
              fields.push(item);
            }
          });
        });
        return fields;
      }
      global.CrudUtils.ensureArray(form.tabs).forEach(function(tab) {
        global.CrudUtils.ensureArray(tab.sections).forEach(collect);
      });
      global.CrudUtils.ensureArray(form.sections).forEach(collect);
      return fields;
    }

    getButtonLabel(action, fallback) {
      const button = this.getFormButton(action);
      return button && button.label ? button.label : fallback;
    }

    getButtonTitle(action, fallback) {
      const button = this.getFormButton(action);
      return button && (button.title || button.label) ? button.title || button.label : fallback;
    }

    isButtonVisibleIn(buttonConfig) {
      if (!buttonConfig || !buttonConfig.visibleIn) {
        return true;
      }
      return global.CrudUtils.ensureArray(buttonConfig.visibleIn).indexOf(this.mode) !== -1;
    }

    hasButtonTarget(buttonConfig) {
      const api = this.definition.api || {};
      return Boolean(buttonConfig && (
        buttonConfig.endpointId ||
        buttonConfig.actionId ||
        buttonConfig.endpoint ||
        buttonConfig.api ||
        buttonConfig.url ||
        buttonConfig.endpoints ||
        buttonConfig.endpointByMode ||
        (buttonConfig.action && api[buttonConfig.action])
      ));
    }

    executeConfiguredButton(buttonConfig, context) {
      if (!this.hasButtonTarget(buttonConfig)) {
        return Promise.resolve(true);
      }
      const actionContext = Object.assign({
        mode: this.mode,
        actionMode: this.actionMode
      }, context || {});
      if (!actionContext.values) {
        actionContext.values = this.collectAllValues();
      }
      const record = Object.assign({}, this.passiveData || this.data || {}, actionContext.values || {});
      return Promise.resolve(this.onButtonAction(
        global.CrudUtils.clone(buttonConfig),
        global.CrudUtils.clone(record),
        global.CrudUtils.clone(actionContext)
      )).then(function(result) {
        return result !== false;
      });
    }

    activateActionFromButton(mode) {
      const buttonConfig = this.getFormButton(mode);
      this.showConcurrencyWarning(mode).then((confirmed) => {
        if (!confirmed) {
          return false;
        }
        return Promise.resolve(this.onBeforeAction(mode, global.CrudUtils.clone(this.passiveData || this.data || {}), {
          concurrencyWarningShown: true
        }));
      }).then((allowed) => {
        if (allowed === false) {
          return false;
        }
        return this.executeConfiguredButton(buttonConfig, {
          event: "beforeActivate",
          action: mode
        });
      }).then((shouldContinue) => {
        if (shouldContinue !== false) {
          this.activateAction(mode);
        }
      });
    }

    showConcurrencyWarning(action, record) {
      const config = this.getConcurrencyWarningConfig(action);
      if (!config) {
        return Promise.resolve(true);
      }
      const message = this.replaceRecordTokens(this.getConcurrencyWarningMessage(config, action), record || this.passiveData || this.data || {});
      if (!message) {
        return Promise.resolve(true);
      }
      if (config.confirm === false || config.blocking === false) {
        global.CrudUtils.showMessage(message, config.type || "warning");
        return Promise.resolve(true);
      }
      return global.CrudUtils.confirm(message, {
        title: config.title || "Aviso de concorrencia",
        confirmText: config.confirmText || "Continuar",
        cancelText: config.cancelText || "Cancelar",
        confirmIcon: config.confirmIcon || "exclamation-circle",
        themeColor: config.themeColor || "warning",
        width: config.width || 460
      });
    }

    getConcurrencyWarningConfig(action) {
      const form = this.getFormConfig();
      const source = form.concurrencyWarning || form.concurrentWarning;
      if (!source) {
        return null;
      }
      const base = typeof source === "string" ? { message: source } : Object.assign({}, source || {});
      if (base.enabled === false) {
        return null;
      }
      const specificSource = base[action];
      const specific = typeof specificSource === "string" ? { message: specificSource } : Object.assign({}, specificSource || {});
      if (specific.enabled === false) {
        return null;
      }
      const actions = this.normalizeWarningActions(specific.actions || base.actions || base.visibleIn || base.modes);
      if (actions.length && actions.indexOf(action) === -1) {
        return null;
      }
      return Object.assign({}, base, specific);
    }

    normalizeWarningActions(value) {
      if (value == null) {
        return ["edit", "delete"];
      }
      if (typeof value === "string") {
        return value.split(",").map(function(item) {
          return item.trim();
        }).filter(Boolean);
      }
      return global.CrudUtils.ensureArray(value).map(function(item) {
        return String(item || "").trim();
      }).filter(Boolean);
    }

    getConcurrencyWarningMessage(config, action) {
      const messages = config.messages || {};
      return config.message ||
        config[action + "Message"] ||
        messages[action] ||
        "Este registro pode estar em uso por outro usuario. Antes de continuar, o sistema deve validar se a operacao ainda pode ser realizada.";
    }

    replaceRecordTokens(text, record) {
      return String(text || "").replace(/\{([^}]+)\}/g, function(_, key) {
        const value = global.CrudUtils.getByPath(record || {}, key);
        return value == null ? "" : String(value);
      });
    }

    getCurrentNavigationState() {
      if (!this.hasCurrentRecord()) {
        return { previous: false, next: false };
      }
      return this.getNavigationState(global.CrudUtils.clone(this.passiveData || this.data || {})) || {
        previous: false,
        next: false
      };
    }

    navigate(direction) {
      if (!this.hasCurrentRecord()) {
        return;
      }
      const preserveActionMode = this.canNavigateWhileActionActive();
      if (this.actionMode && !preserveActionMode) {
        return;
      }
      Promise.resolve(this.onNavigate(global.CrudUtils.clone(this.passiveData || this.data || {}), direction)).then((record) => {
        if (!record) {
          return;
        }
        this.passiveData = global.CrudUtils.clone(record);
        this.data = global.CrudUtils.clone(record);
        if (preserveActionMode) {
          this.mode = this.normalizeMode(this.mode);
          this.actionMode = this.getActionMode(this.mode);
        } else {
          this.mode = "view";
          this.actionMode = null;
        }
        this.renderForm();
      });
    }

    canNavigateWhileActionActive() {
      return this.isMobileViewport() && this.actionMode === "edit";
    }

    hasCurrentRecord() {
      const primaryKey = this.definition.dataModel && this.definition.dataModel.primaryKey;
      return Boolean(primaryKey && this.data && this.data[primaryKey] != null && this.data[primaryKey] !== "");
    }

    isLogsEnabled() {
      const logs = this.getLogsConfig();
      return logs.enabled !== false && (typeof logs.url === "string" && logs.url.trim() !== "" || logs.documentId || logs.endpointId || logs.actionId);
    }

    getLogsConfig() {
      const form = this.getFormConfig();
      const logsButton = this.getFormButton("logs");
      return Object.assign({}, logsButton || {}, form.logs || {});
    }

    openCurrentRecordLogs() {
      const logs = this.getLogsConfig();
      if (!this.isLogsEnabled() || !this.hasCurrentRecord()) {
        return;
      }
      this.onLogs(global.CrudUtils.clone(this.passiveData || this.data || {}), global.CrudUtils.clone(logs));
    }

    getSituationConfig() {
      return this.getFormConfig().situation || this.getFormConfig().statusFlow || null;
    }

    isSituationEnabled() {
      const situation = this.getSituationConfig();
      return Boolean(situation &&
        situation.enabled !== false &&
        situation.field &&
        this.definition.dataModel &&
        this.definition.dataModel.fields &&
        this.definition.dataModel.fields[situation.field]);
    }

    renderSituationBar(container) {
      if (!this.isSituationEnabled()) {
        return;
      }

      const situation = this.getSituationConfig();
      const field = this.definition.dataModel.fields[situation.field];
      const display = situation.display || situation.mode || "stepper";
      const currentValue = this.data ? this.data[situation.field] : null;
      const steps = this.getSituationSteps(situation, field, currentValue);
      const currentText = this.getSituationText(steps, currentValue, field);
      const wrapper = $("<div class=\"crud-situation-bar\"></div>")
        .toggleClass("crud-situation-badge-mode", display === "badge")
        .appendTo(container);

      $("<span class=\"crud-situation-title\"></span>")
        .text(situation.label || field.label || "Situacao")
        .appendTo(wrapper);

      if (display === "badge") {
        const badge = $("<button type=\"button\" class=\"crud-situation-badge\"></button>")
          .text(currentText)
          .attr("title", situation.historyTitle || "Historico da situacao")
          .appendTo(wrapper);
        badge.on("click", () => this.openSituationHistory(currentValue));
        return;
      }

      if ($.fn.kendoStepper) {
        this.renderKendoSituationStepper(wrapper, situation, steps, currentValue);
        this.scrollCurrentSituationStep(wrapper);
        return;
      }

      this.renderFallbackSituationSteps(wrapper, situation, steps, currentValue);
      this.scrollCurrentSituationStep(wrapper);
    }

    renderKendoSituationStepper(container, situation, steps, currentValue) {
      const currentIndex = Math.max(0, steps.findIndex(function(step) {
        return String(step.value) === String(currentValue);
      }));
      const stepperElement = $("<div class=\"crud-situation-stepper\"></div>").appendTo(container);
      stepperElement.kendoStepper({
        linear: false,
        indicator: true,
        label: true,
        orientation: situation.orientation || "horizontal",
        steps: steps.map(function(step, index) {
          return {
            label: step.text,
            selected: index === currentIndex,
            enabled: true,
            previous: currentIndex >= 0 && index < currentIndex
          };
        }),
        select: (event) => {
          if (event && typeof event.preventDefault === "function") {
            event.preventDefault();
          }
        }
      });
      stepperElement.on("click.crudSituation", ".k-step, .k-step-link", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const stepElement = $(event.target).closest(".k-step");
        const index = stepElement.length ? stepElement.index() : currentIndex;
        const step = steps[index] || steps[currentIndex];
        this.openSituationHistory(step && step.value);
      });
    }

    scrollCurrentSituationStep(container) {
      global.setTimeout(function() {
        const scrollContainer = container.find(".crud-situation-stepper, .crud-situation-steps").first();
        const active = scrollContainer.find(".k-step-selected, .k-selected, .is-active").first();
        if (!scrollContainer.length || !active.length) {
          return;
        }

        const containerEl = scrollContainer[0];
        const activeEl = active[0];
        const target = activeEl.offsetLeft - Math.max(0, (containerEl.clientWidth - activeEl.offsetWidth) / 2);
        containerEl.scrollLeft = Math.max(0, target);
      }, 0);
    }

    renderFallbackSituationSteps(container, situation, steps, currentValue) {
      const list = $("<div class=\"crud-situation-steps\" role=\"list\"></div>").appendTo(container);
      const currentIndex = steps.findIndex(function(step) {
        return String(step.value) === String(currentValue);
      });

      steps.forEach((step, index) => {
        const button = $("<button type=\"button\" class=\"crud-situation-step\" role=\"listitem\"></button>")
          .toggleClass("is-active", String(step.value) === String(currentValue))
          .toggleClass("is-completed", currentIndex >= 0 && index < currentIndex)
          .attr("data-value", step.value == null ? "" : step.value)
          .attr("title", step.description || situation.historyTitle || "Ver historico")
          .appendTo(list);
        $("<span class=\"crud-situation-step-text\"></span>").text(step.text).appendTo(button);
        button.on("click", () => this.openSituationHistory(step.value));
      });
    }

    getSituationSteps(situation, field, currentValue) {
      const configured = global.CrudUtils.ensureArray(situation.steps);
      const source = configured.length ? configured : global.CrudUtils.ensureArray(field.options);
      const steps = source.map(function(item) {
        if (typeof item !== "object" || item == null) {
          return { value: item, text: String(item == null ? "" : item) };
        }
        const value = item.value != null ? item.value : item.id;
        const text = item.text || item.label || item.title || String(value == null ? "" : value);
        return Object.assign({}, item, {
          value,
          text
        });
      });

      if (!steps.length && currentValue != null && currentValue !== "") {
        steps.push({ value: currentValue, text: String(currentValue) });
      }
      return steps;
    }

    getSituationText(steps, currentValue, field) {
      const found = global.CrudUtils.ensureArray(steps).find(function(step) {
        return String(step.value) === String(currentValue);
      });
      if (found) {
        return found.text;
      }
      return this.formatReadonly(field, currentValue);
    }

    openSituationHistory(stepValue) {
      const situation = this.getSituationConfig();
      const endpoint = this.resolveSituationHistoryEndpoint(situation || {});
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Historico da situacao nao configurado.", "info");
        return;
      }

      const primaryKey = this.definition.dataModel.primaryKey;
      const values = this.collectAllValues();
      const record = global.CrudUtils.clone(this.passiveData || this.data || {});
      const payload = {
        id: record && record[primaryKey],
        field: situation.field,
        value: values[situation.field],
        phase: stepValue,
        record,
        values
      };
      const url = global.CrudUtils.replaceUrlParams(endpoint.url, Object.assign({}, record, values, {
        phase: stepValue
      }));

      this.httpClient.request({
        url,
        method: endpoint.method || "POST",
        data: payload
      }).then((response) => {
        this.openSituationHistoryWindow(situation, stepValue, response);
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar historico da situacao.");
        global.CrudUtils.showMessage(normalized.message, "error");
      });
    }

    resolveSituationHistoryEndpoint(situation) {
      const api = this.definition.api || {};
      const endpointId = situation.historyEndpointId || situation.endpointId;
      if (endpointId && api[endpointId]) {
        return api[endpointId];
      }
      if (situation.actionId && api[situation.actionId]) {
        return api[situation.actionId];
      }
      if (situation.historyEndpoint && situation.historyEndpoint.url) {
        return situation.historyEndpoint;
      }
      if (situation.endpoint && situation.endpoint.url) {
        return situation.endpoint;
      }
      if (situation.api && situation.api.url) {
        return situation.api;
      }
      if (situation.url) {
        return {
          url: situation.url,
          method: situation.method
        };
      }
      return null;
    }

    openSituationHistoryWindow(situation, stepValue, response) {
      const items = global.CrudUtils.ensureArray(response && (response.items || response.history || response.data));
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-situation-history-window\"></div>").appendTo(wrapper);
      const steps = this.getSituationSteps(situation, this.definition.dataModel.fields[situation.field], this.data[situation.field]);
      const stepText = this.getSituationText(steps, stepValue, this.definition.dataModel.fields[situation.field]);

      $("<h3 class=\"crud-situation-history-title\"></h3>")
        .text(stepText || situation.label || "Situacao")
        .appendTo(content);

      if (!items.length) {
        $("<p class=\"crud-situation-history-empty\"></p>")
          .text("Nenhum historico encontrado.")
          .appendTo(content);
      } else {
        const list = $("<div class=\"crud-situation-history-list\"></div>").appendTo(content);
        items.forEach((item) => {
          const entry = $("<article class=\"crud-situation-history-item\"></article>").appendTo(list);
          $("<strong></strong>").text(item.title || item.statusText || item.text || stepText || "").appendTo(entry);
          $("<span></span>").text(this.formatHistoryDate(item.changedAt || item.dateTime || item.date || item.createdAt)).appendTo(entry);
          $("<span></span>").text(item.user || item.changedBy || item.author || "").appendTo(entry);
          if (item.note || item.observation || item.description) {
            $("<p></p>").text(item.note || item.observation || item.description).appendTo(entry);
          }
        });
      }

      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const closeButton = $("<button type=\"button\"></button>").text("Fechar").appendTo(actions);

      wrapper.kendoWindow({
        title: situation.historyTitle || "Historico da situacao",
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(Number(situation.historyWidth || 560), Math.max(320, global.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const historyWindow = wrapper.data("kendoWindow");
      closeButton.kendoButton({ icon: "x" });
      closeButton.on("click", function() {
        historyWindow.close();
      });
      historyWindow.center().open();
      if (typeof historyWindow.toFront === "function") {
        historyWindow.toFront();
      }
    }

    formatHistoryDate(value) {
      if (!value) {
        return "";
      }
      const date = global.CrudUtils.normalizeDateValue(value);
      return date instanceof Date ? kendo.toString(date, "dd/MM/yyyy HH:mm") : String(value);
    }

    getFormConfig() {
      return this.definition.form || {};
    }

    getFormDomId() {
      const form = this.getFormConfig();
      const fallback = (this.definition.id || this.definition.entity || "crud") + "-form";
      return this.normalizeDomId(form.id || fallback, fallback);
    }

    getFieldDomId(item) {
      const fieldName = item && item.field ? item.field : "field";
      const fieldId = this.normalizeDomId(item && item.id || fieldName, fieldName);
      return this.getFormDomId() + "-" + fieldId;
    }

    normalizeDomId(value, fallback) {
      const normalized = String(value || fallback || "item")
        .trim()
        .replace(/[^A-Za-z0-9_-]+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "");
      return normalized || String(fallback || "item");
    }

    getFormBehavior() {
      return this.getFormConfig().behavior || {};
    }

    getWindowWidth() {
      const configuredWidth = Number(this.getFormConfig().width || 800);
      const maxWidth = Math.max(320, global.innerWidth - 16);
      return Math.min(Number.isFinite(configuredWidth) ? configuredWidth : 800, maxWidth);
    }

    shouldOpenMaximized() {
      const form = this.getFormConfig();
      if (form.maximizeForm != null) {
        return Boolean(form.maximizeForm);
      }
      if (form.maximizeOnOpen != null) {
        return Boolean(form.maximizeOnOpen);
      }
      return false;
    }

    shouldCloseOnSave() {
      const form = this.getFormConfig();
      const behavior = this.getFormBehavior();
      if (behavior.closeOnSave != null) {
        return Boolean(behavior.closeOnSave);
      }
      if (form.closeOnSave != null) {
        return Boolean(form.closeOnSave);
      }
      return true;
    }

    shouldCloseOnCancel() {
      const form = this.getFormConfig();
      const behavior = this.getFormBehavior();
      if (behavior.closeOnCancel != null) {
        return Boolean(behavior.closeOnCancel);
      }
      if (form.closeOnCancel != null) {
        return Boolean(form.closeOnCancel);
      }
      return false;
    }

    isNavigationEnabled() {
      const form = this.getFormConfig();
      const navigation = form.navigation || {};
      if (navigation.enabled != null) {
        return Boolean(navigation.enabled);
      }
      if (form.showNavigation != null) {
        return Boolean(form.showNavigation);
      }
      return true;
    }

    shouldShowHeaderActions() {
      const mobile = this.getFormConfig().mobile || {};
      if (this.isMobileViewport()) {
        return mobile.showHeaderActions === true;
      }
      return true;
    }

    isMobileViewport() {
      const mobile = this.getFormConfig().mobile || {};
      const breakpoint = Number(mobile.breakpoint || 720);
      const width = Number.isFinite(breakpoint) && breakpoint > 0 ? breakpoint : 720;
      return global.matchMedia
        ? global.matchMedia("(max-width: " + width + "px)").matches
        : global.innerWidth <= width;
    }

    renderContent(container) {
      if (this.definition.form.layout === "steps") {
        this.renderStepLayout(container);
        return;
      }

      const tabs = global.CrudUtils.ensureArray(this.definition.form.tabs);
      if (this.definition.form.layout === "tabs" && tabs.length) {
        const tabStrip = $("<div class=\"crud-form-tabs\"></div>").appendTo(container);
        const list = $("<ul></ul>").appendTo(tabStrip);
        tabs.forEach(function(tab) {
          $("<li></li>")
            .attr("data-tab-id", tab.id || "")
            .text(tab.title)
            .appendTo(list);
        });
        tabs.forEach((tab) => {
          const tabContent = $("<div class=\"crud-form-tab-content\"></div>")
            .attr("data-tab-id", tab.id || "")
            .appendTo(tabStrip);
          this.renderSections(tabContent, tab.sections);
        });
        tabStrip.kendoTabStrip({
          animation: false
        });
        const widget = tabStrip.data("kendoTabStrip");
        if (widget) {
          widget.select(0);
        }
        return;
      }

      this.renderSections(container, this.definition.form.sections || []);
    }

    renderStepLayout(container) {
      const steps = this.getFormSteps();
      if (!steps.length) {
        this.renderSections(container, this.definition.form.sections || []);
        return;
      }

      this.currentStepIndex = Math.max(0, Math.min(Number(this.currentStepIndex || 0), steps.length - 1));
      const currentStep = steps[this.currentStepIndex];
      const stepReadonly = this.isStepReadonly(currentStep);
      const shell = $("<div class=\"crud-form-steps\"></div>").appendTo(container);
      const nav = $("<ol class=\"crud-form-steps-nav\"></ol>").appendTo(shell);

      steps.forEach((step, index) => {
        const button = $("<button type=\"button\" class=\"crud-form-step-nav-button\"></button>")
          .toggleClass("is-active", index === this.currentStepIndex)
          .toggleClass("is-readonly", this.isStepReadonly(step))
          .attr("data-step-id", step.id || "")
          .attr("aria-current", index === this.currentStepIndex ? "step" : "false")
          .appendTo($("<li></li>").appendTo(nav));
        $("<span class=\"crud-form-step-number\"></span>").text(String(index + 1)).appendTo(button);
        $("<span class=\"crud-form-step-label\"></span>").text(step.title || step.label || step.id).appendTo(button);
        button.on("click", () => this.goToFormStep(index));
      });

      const panel = $("<section class=\"crud-form-step-panel\"></section>")
        .attr("data-step-id", currentStep.id || "")
        .toggleClass("is-readonly", stepReadonly)
        .appendTo(shell);
      const header = $("<div class=\"crud-form-step-header\"></div>").appendTo(panel);
      const titleGroup = $("<div class=\"crud-form-step-title-group\"></div>").appendTo(header);
      $("<h3 class=\"crud-form-step-title\"></h3>").text(currentStep.title || currentStep.label || currentStep.id).appendTo(titleGroup);
      if (currentStep.description) {
        $("<p class=\"crud-form-step-description\"></p>").text(currentStep.description).appendTo(titleGroup);
      }
      const headerActions = $("<div class=\"crud-form-step-header-actions\"></div>").appendTo(header);
      if (stepReadonly) {
        $("<span class=\"crud-form-step-readonly-badge\"></span>").text("Somente consulta").appendTo(headerActions);
      }
      this.renderStepLogButton(headerActions, currentStep);

      this.renderSections(panel, this.getStepSections(currentStep), {
        step: currentStep,
        stepReadonly
      });
      this.applyStepRequiredMarkers(currentStep);
      this.renderStepActions(panel, steps, currentStep);
    }

    getFormSteps() {
      return global.CrudUtils.ensureArray(this.getFormConfig().steps).filter(function(step) {
        return step && step.visible !== false;
      }).filter((step) => {
        return !(this.formRuntime && this.formRuntime.steps && this.formRuntime.steps[step.id] === false);
      });
    }

    getStepSections(step) {
      const sections = global.CrudUtils.ensureArray(step && step.sections);
      if (sections.length) {
        return sections;
      }
      const fields = global.CrudUtils.ensureArray(step && step.fields).map(function(item) {
        return typeof item === "string" ? { field: item } : item;
      });
      return fields.length ? [{
        id: (step && step.id || "step") + "-section",
        title: "",
        columns: step && step.columns || 1,
        fields
      }] : [];
    }

    renderStepActions(container, steps, step) {
      const actions = $("<div class=\"crud-form-step-actions\"></div>").appendTo(container);
      const previousButton = $("<button type=\"button\"></button>").text("Anterior").appendTo(actions);
      const nextButton = $("<button type=\"button\"></button>").text("Proxima etapa").appendTo(actions);
      const isFirst = this.currentStepIndex <= 0;
      const isLast = this.currentStepIndex >= steps.length - 1;

      previousButton.kendoButton({
        icon: "chevron-left",
        enable: !isFirst
      });
      nextButton.kendoButton({
        icon: "chevron-right",
        enable: !isLast
      });
      previousButton.on("click", () => {
        if (!isFirst) {
          this.goToFormStep(this.currentStepIndex - 1);
        }
      });
      nextButton.on("click", () => {
        if (!isLast) {
          this.goToFormStep(this.currentStepIndex + 1);
        }
      });

      if (this.isStepReadonly(step)) {
        $("<span class=\"crud-form-step-note\"></span>")
          .text("Esta etapa foi liberada apenas para consulta pelas regras recebidas do backend.")
          .appendTo(actions);
      }
    }

    goToFormStep(index) {
      const steps = this.getFormSteps();
      const targetIndex = Math.max(0, Math.min(Number(index || 0), steps.length - 1));
      if (targetIndex === this.currentStepIndex) {
        return;
      }
      if (targetIndex > this.currentStepIndex && !this.validateCurrentStepBeforeLeave()) {
        return;
      }
      this.data = Object.assign({}, this.data || {}, this.collectAllValues());
      this.currentStepIndex = targetIndex;
      this.renderForm();
    }

    validateCurrentStepBeforeLeave() {
      const step = this.getFormSteps()[this.currentStepIndex];
      if (!step || this.isStepReadonly(step)) {
        return true;
      }
      const errors = this.validateStepRequiredFields(step, this.collectAllValues());
      if (errors.length) {
        global.CrudUtils.showMessage(errors.join("\n"), "warning");
        return false;
      }
      return true;
    }

    validateStepRequiredFields(step, values) {
      return this.getStepRequiredFields(step).reduce((errors, fieldName) => {
        const field = this.definition.dataModel.fields[fieldName] || {};
        const value = values ? values[fieldName] : undefined;
        if (value == null || value === "") {
          errors.push((field.label || fieldName) + " e obrigatorio para avancar.");
        }
        return errors;
      }, []);
    }

    getStepRequiredFields(step) {
      return global.CrudUtils.ensureArray(step && (step.requiredFields || step.required)).map(function(item) {
        return typeof item === "string" ? item : item && item.field;
      }).filter(Boolean);
    }

    applyStepRequiredMarkers(step) {
      this.getStepRequiredFields(step).forEach((fieldName) => {
        const item = this.inputs[fieldName];
        if (!item || this.isStepReadonly(step)) {
          return;
        }
        item.wrapper.toggleClass("crud-field-required", true);
        if (item.input) {
          item.input.prop("required", true);
        }
      });
    }

    isStepReadonly(step) {
      if (!step) {
        return false;
      }
      if (step.readonly === true || step.editable === false) {
        return true;
      }
      if (step.permission && !global.CrudUtils.getPermission(this.definition, step.permission)) {
        return true;
      }
      const userGroups = this.getCurrentUserGroups();
      const readonlyGroups = global.CrudUtils.ensureArray(step.readonlyGroups);
      if (readonlyGroups.some(function(group) { return userGroups.indexOf(group) !== -1; })) {
        return true;
      }
      const editableGroups = global.CrudUtils.ensureArray(step.editableGroups);
      if (editableGroups.length && !editableGroups.some(function(group) { return userGroups.indexOf(group) !== -1; })) {
        return true;
      }
      return false;
    }

    getCurrentUserGroups() {
      const security = this.definition.security || {};
      const currentUser = this.definition.currentUser || {};
      return []
        .concat(global.CrudUtils.ensureArray(security.userGroups))
        .concat(global.CrudUtils.ensureArray(security.groups))
        .concat(global.CrudUtils.ensureArray(currentUser.groups))
        .map(String);
    }

    renderStepLogButton(container, step) {
      const logConfig = this.getStepLogConfig(step);
      if (!logConfig) {
        return null;
      }
      const requiresRecord = logConfig.requiresRecord !== false;
      const disabled = requiresRecord && !this.hasCurrentRecord();
      const button = $("<button type=\"button\" class=\"crud-form-step-log-button\"></button>")
        .text(logConfig.label || "Logs da etapa")
        .appendTo(container);
      button.kendoButton({
        icon: logConfig.icon || "list-unordered",
        enable: !disabled
      });
      button.on("click", () => {
        if (!disabled) {
          this.openStepLog(step, logConfig);
        }
      });
      return button;
    }

    getStepLogConfig(step) {
      const logConfig = step && (step.logs || step.log || step.history);
      if (!logConfig || logConfig.enabled === false) {
        return null;
      }
      if (logConfig.endpointId || logConfig.actionId || logConfig.endpoint || logConfig.api || logConfig.url) {
        return logConfig;
      }
      return null;
    }

    openStepLog(step, logConfig) {
      const endpoint = this.resolveStepLogEndpoint(logConfig);
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint de logs da etapa nao configurado.", "error");
        return;
      }
      const values = this.collectAllValues();
      const primaryKey = this.definition.dataModel && this.definition.dataModel.primaryKey;
      const record = global.CrudUtils.clone(this.passiveData || this.data || {});
      const params = Object.assign({}, record, values, {
        id: record && record[primaryKey],
        stepId: step.id
      });
      const url = global.CrudUtils.replaceUrlParams(endpoint.url, params);
      this.httpClient.request({
        url,
        method: endpoint.method || logConfig.method || "POST",
        data: {
          id: params.id,
          stepId: step.id,
          stepTitle: step.title || step.label || step.id,
          values,
          record,
          mode: this.mode,
          actionMode: this.actionMode
        }
      }).then((response) => {
        this.openStepLogWindow(step, logConfig, response);
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar logs da etapa.");
        global.CrudUtils.showMessage(normalized.message, "error");
      });
    }

    resolveStepLogEndpoint(logConfig) {
      const api = this.definition.api || {};
      if (logConfig.endpointId && api[logConfig.endpointId]) {
        return api[logConfig.endpointId];
      }
      if (logConfig.actionId && api[logConfig.actionId]) {
        return api[logConfig.actionId];
      }
      if (logConfig.endpoint && logConfig.endpoint.url) {
        return logConfig.endpoint;
      }
      if (logConfig.api && logConfig.api.url) {
        return logConfig.api;
      }
      if (logConfig.url) {
        return {
          url: logConfig.url,
          method: logConfig.method
        };
      }
      return null;
    }

    openStepLogWindow(step, logConfig, response) {
      const items = global.CrudUtils.ensureArray(response && (response.items || response.history || response.data));
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-step-log-window\"></div>").appendTo(wrapper);
      $("<h3 class=\"crud-step-log-title\"></h3>").text(logConfig.title || "Logs da etapa").appendTo(content);
      $("<p class=\"crud-step-log-subtitle\"></p>").text(step.title || step.label || step.id).appendTo(content);

      if (!items.length) {
        $("<p class=\"crud-step-log-empty\"></p>").text("Nenhum log encontrado para esta etapa.").appendTo(content);
      } else {
        const list = $("<div class=\"crud-step-log-list\"></div>").appendTo(content);
        items.forEach(function(item) {
          const entry = $("<article class=\"crud-step-log-item\"></article>").appendTo(list);
          $("<strong></strong>").text(item.title || item.action || item.status || "Atualizacao").appendTo(entry);
          $("<span></span>").text(item.changedAt || item.dateTime || item.date || item.createdAt || "").appendTo(entry);
          $("<span></span>").text(item.user || item.changedBy || item.author || "").appendTo(entry);
          if (item.percent != null || item.completion != null) {
            $("<span></span>").text("Preenchimento: " + (item.percent != null ? item.percent : item.completion) + "%").appendTo(entry);
          }
          if (item.note || item.observation || item.description) {
            $("<p></p>").text(item.note || item.observation || item.description).appendTo(entry);
          }
        });
      }

      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton({ icon: "x" });
      wrapper.kendoWindow({
        title: logConfig.title || "Logs da etapa",
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(Number(logConfig.width || 560), Math.max(320, global.innerWidth - 24)),
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
      if (typeof windowWidget.toFront === "function") {
        windowWidget.toFront();
      }
    }

    renderSections(container, sections, options) {
      const settings = options || {};
      global.CrudUtils.ensureArray(sections).forEach((section) => {
        const sectionElement = $("<section class=\"crud-section\"></section>")
          .attr("data-section-id", section.id || "")
          .appendTo(container);
        $("<h3 class=\"crud-section-title\"></h3>").text(section.title || "").appendTo(sectionElement);
        const grid = $("<div class=\"crud-section-grid\"></div>")
          .css("--crud-columns", section.columns || 1)
          .appendTo(sectionElement);

        global.CrudUtils.ensureArray(section.fields).forEach((item) => {
          this.renderField(grid, item, settings);
        });
      });
    }

    renderField(container, item, options) {
      const settings = options || {};
      const field = this.definition.dataModel.fields[item.field];
      const fieldDomId = this.getFieldDomId(item);
      const renderAs = item.renderAs || item.display || item.mode;
      const runtimeReadonly = this.formRuntime && this.formRuntime.readonly[item.field];
      const readonly = this.mode === "view" ||
        this.mode === "delete" ||
        item.readonly ||
        runtimeReadonly === true ||
        settings.stepReadonly === true ||
        field.editable === false ||
        renderAs === "label" ||
        renderAs === "readonly";
      const wrapper = $("<div class=\"crud-field\"></div>")
        .attr("data-field", item.field)
        .toggleClass("crud-col-span-2", item.colSpan === 2)
        .toggleClass("crud-field-readonly", readonly)
        .appendTo(container);
      const labelElement = $("<label></label>")
        .attr("for", fieldDomId)
        .text(item.label || field.label)
        .appendTo(wrapper);

      if (field.type === "hidden" || field.editor === "hidden") {
        const input = $("<input type=\"hidden\">")
          .attr("id", fieldDomId)
          .attr("name", item.field)
          .appendTo(wrapper);
        input.val(this.data[item.field] == null ? "" : this.data[item.field]);
        this.inputs[item.field] = { input, wrapper, labelElement, field, item, id: fieldDomId, kind: "hidden", readonly, baseReadonly: readonly };
        return;
      }

      if ((field.editor || field.type) === "customCode" && !readonly) {
        this.renderCustomCodeField(wrapper, labelElement, field, item, fieldDomId);
        return;
      }

      if (readonly) {
        const readonlyElement = $("<div class=\"crud-readonly-value\"></div>")
          .attr("id", fieldDomId)
          .text(this.formatReadonly(field, this.data[item.field]))
          .appendTo(wrapper);
        this.inputs[item.field] = {
          wrapper,
          labelElement,
          readonlyElement,
          field,
          item,
          id: fieldDomId,
          kind: "readonly",
          readonly,
          baseReadonly: readonly
        };
        return;
      }

      const input = field.editor === "textarea" || field.type === "text"
        ? $("<textarea></textarea>").attr("id", fieldDomId).attr("name", item.field).appendTo(wrapper)
        : $("<input>").attr("id", fieldDomId).attr("name", item.field).appendTo(wrapper);
      this.createWidget(input, field, this.data[item.field]);
      this.inputs[item.field] = {
        input,
        wrapper,
        labelElement,
        field,
        item,
        id: fieldDomId,
        kind: field.editor || field.type,
        readonly,
        baseReadonly: readonly
      };
    }

    renderCustomCodeField(wrapper, labelElement, field, item, fieldDomId) {
      const line = $("<div class=\"crud-custom-code-line\"></div>").appendTo(wrapper);
      const input = $("<input>")
        .attr("id", fieldDomId)
        .attr("name", item.field)
        .prop("readonly", true)
        .appendTo(line);
      input.kendoTextBox({
        value: this.data[item.field] == null ? "" : this.data[item.field]
      });

      const config = field.customCode || {};
      const promptFields = global.CrudUtils.ensureArray(config.promptFields);
      let button = null;
      if (config.assistantScreenId || promptFields.length) {
        button = $("<button type=\"button\"></button>").text(config.assistantScreenId ? "Abrir assistente" : "Configurar").appendTo(line);
        button.kendoButton({
          icon: "gear",
          click: () => {
            if (config.assistantScreenId) {
              this.openCustomCodeAssistantScreen(item.field);
              return;
            }
            this.openCustomCodeDialog(item.field);
          }
        });
      }

      const hintText = config.assistantScreenId
        ? (config.promptTitle || "Abra a tela auxiliar para montar as propriedades do codigo antes do salvar.")
        : promptFields.length
        ? (config.promptTitle || "Configure as propriedades do codigo e o backend gera o valor no salvar.")
        : "Codigo gerado automaticamente no salvar.";
      const hint = $("<div class=\"crud-field-hint\"></div>").text(hintText).appendTo(wrapper);

      this.customCodeState[item.field] = {
        field,
        input,
        properties: {},
        promptInputs: {},
        hint,
      };
      this.inputs[item.field] = {
        input,
        wrapper,
        labelElement,
        field,
        item,
        id: fieldDomId,
        kind: "customCode",
        readonly: false,
        baseReadonly: false,
        button
      };
    }

    createWidget(input, field, value) {
      switch (field.editor || field.type) {
        case "textarea":
        case "text":
        case "string":
        case "email":
          input.kendoTextBox({ value: value == null ? "" : value });
          break;
        case "integer":
        case "decimal":
        case "number":
        case "currency":
          input.kendoNumericTextBox({
            value: value == null ? null : Number(value),
            format: field.format === "currency" ? "c2" : "n2",
            decimals: field.type === "integer" ? 0 : 2
          });
          break;
        case "date":
          input.kendoDatePicker({
            format: "dd/MM/yyyy",
            value: global.CrudUtils.normalizeDateValue(value)
          });
          break;
        case "datetime":
          input.kendoDateTimePicker({
            format: "dd/MM/yyyy HH:mm",
            value: global.CrudUtils.normalizeDateValue(value)
          });
          break;
        case "boolean":
        case "switch":
          input.kendoSwitch({
            checked: Boolean(value)
          });
          break;
        case "enum":
        case "dropdown":
          const options = global.CrudUtils.ensureArray(field.options);
          input.kendoDropDownList({
            dataTextField: "text",
            dataValueField: "value",
            dataSource: options,
            value: value == null || value === "" ? (options[0] && options[0].value || "") : value
          });
          break;
        default:
          input.kendoTextBox({ value: value == null ? "" : value });
      }
    }

    bindFormEvents() {
      this.getFormEvents().forEach((eventConfig) => {
        const eventName = this.normalizeFormEventName(eventConfig.event);
        const source = eventConfig.source || eventConfig.field;
        const inputState = source ? this.inputs[source] : null;

        if (!inputState || !inputState.input || eventName === "open" || eventName === "afterLoad") {
          return;
        }

        const handler = () => {
          if (this.applyingEffects) {
            return;
          }
          this.executeFormEvent(eventConfig);
        };

        if (eventName === "change") {
          this.bindWidgetChange(inputState.input, handler);
          inputState.input.off("change.crudFormEvent." + eventConfig.id).on("change.crudFormEvent." + eventConfig.id, handler);
          return;
        }

        if (eventName === "enter") {
          inputState.input.off("keydown.crudFormEvent." + eventConfig.id).on("keydown.crudFormEvent." + eventConfig.id, function(event) {
            if (event.key === "Enter") {
              handler();
            }
          });
          return;
        }

        inputState.input.off(eventName + ".crudFormEvent." + eventConfig.id).on(eventName + ".crudFormEvent." + eventConfig.id, handler);
      });
      this.bindDirtyEvents();
    }

    bindDirtyEvents() {
      Object.keys(this.inputs || {}).forEach((fieldName) => {
        const item = this.inputs[fieldName];
        if (!item || !item.input) {
          return;
        }
        const handler = () => this.updateDirtyState();
        item.input.off("input.crudDirty change.crudDirty").on("input.crudDirty change.crudDirty", handler);
        const widget = this.getInputWidget(item.input);
        if (widget && typeof widget.bind === "function") {
          widget.bind("change", handler);
        }
      });
    }

    bindWidgetChange(input, handler) {
      const widget = this.getInputWidget(input);
      if (!widget || typeof widget.bind !== "function") {
        return;
      }
      if (typeof widget.unbind === "function") {
        widget.unbind("change", handler);
      }
      widget.bind("change", handler);
    }

    executeFormLifecycleEvents(eventName) {
      this.getFormEvents().forEach((eventConfig) => {
        if (this.normalizeFormEventName(eventConfig.event) === eventName) {
          this.executeFormEvent(eventConfig);
        }
      });
    }

    getFormEvents() {
      return global.CrudUtils.ensureArray(this.getFormConfig().events).map(function(eventConfig, index) {
        return Object.assign({
          id: "form-event-" + index
        }, eventConfig || {});
      });
    }

    normalizeFormEventName(eventName) {
      const value = String(eventName || "change");
      return ["change", "blur", "focus", "enter", "open", "afterLoad"].indexOf(value) === -1 ? "change" : value;
    }

    executeFormEvent(eventConfig) {
      const context = this.buildEventContext();
      if (!this.evaluateCondition(eventConfig.when, context)) {
        return Promise.resolve(false);
      }

      const endpoint = this.resolveFormEventEndpoint(eventConfig);
      if (!endpoint || !endpoint.url) {
        this.applyEffects(this.getConfiguredEventEffects(eventConfig), context);
        return Promise.resolve(true);
      }

      const url = global.CrudUtils.replaceUrlParams(endpoint.url, context.values);
      return this.httpClient.request({
        url,
        method: endpoint.method || eventConfig.method || "POST",
        data: this.buildEventPayload(eventConfig, context)
      }).then((response) => {
        if (this.handleBackendValidationResponse(response, function() { return Promise.resolve(false); })) {
          return false;
        }
        const responseContext = this.buildEventContext(response);
        this.applyEffects(this.getResponseEventEffects(eventConfig, response), responseContext);
        return true;
      }).catch((error) => {
        if (this.handleBackendValidationResponse(error, (token) => {
          const retryConfig = Object.assign({}, eventConfig, {
            data: Object.assign({}, eventConfig.data || {}, {
              _runtime: Object.assign({}, eventConfig.data && eventConfig.data._runtime || {}, {
                validationConfirmationToken: token
              })
            })
          });
          return this.executeFormEvent(retryConfig);
        })) {
          return false;
        }
        const normalized = global.CrudUtils.unwrapError(error, eventConfig.errorMessage || "Erro ao executar evento do formulario.");
        global.CrudUtils.showMessage(normalized.message, "error");
        return false;
      });
    }

    resolveFormEventEndpoint(eventConfig) {
      const api = this.definition.api || {};
      if (!eventConfig) {
        return null;
      }
      if (eventConfig.endpointId && api[eventConfig.endpointId]) {
        return api[eventConfig.endpointId];
      }
      if (eventConfig.actionId && api[eventConfig.actionId]) {
        return api[eventConfig.actionId];
      }
      if (eventConfig.endpoint && eventConfig.endpoint.url) {
        return eventConfig.endpoint;
      }
      if (eventConfig.api && eventConfig.api.url) {
        return eventConfig.api;
      }
      if (eventConfig.url) {
        return {
          url: eventConfig.url,
          method: eventConfig.method
        };
      }
      return eventConfig.action && api[eventConfig.action] ? api[eventConfig.action] : null;
    }

    buildEventPayload(eventConfig, context) {
      const request = eventConfig.request || {};
      const payload = Object.assign({}, eventConfig.data || {}, request.data || {});
      const map = request.map || {};

      Object.keys(map).forEach((targetName) => {
        payload[targetName] = this.resolveContextPath(map[targetName], context);
      });

      if (!Object.keys(map).length && request.includeValues !== false) {
        payload.values = global.CrudUtils.clone(context.values);
      }

      payload.eventId = eventConfig.id;
      payload.source = eventConfig.source || eventConfig.field || null;
      payload.mode = this.mode;
      payload.actionMode = this.actionMode;
      payload.record = global.CrudUtils.clone(this.data || {});
      return payload;
    }

    buildEventContext(response) {
      const values = this.collectAllValues();
      return {
        values,
        data: this.data || {},
        response: response || {},
        mode: this.mode,
        actionMode: this.actionMode
      };
    }

    collectAllValues() {
      const values = Object.assign({}, this.data || {});
      Object.keys(this.inputs || {}).forEach((fieldName) => {
        values[fieldName] = this.readFieldCurrentValue(fieldName);
      });
      const customCode = this.buildCustomCodeRuntimePayload();
      if (Object.keys(customCode).length) {
        values._customCode = customCode;
      }
      return values;
    }

    setRuntimeContext(runtime) {
      this.data = Object.assign({}, this.data || {}, {
        _runtime: Object.assign({}, this.data && this.data._runtime || {}, runtime || {})
      });
      this.passiveData = Object.assign({}, this.passiveData || {}, {
        _runtime: Object.assign({}, this.passiveData && this.passiveData._runtime || {}, runtime || {})
      });
    }

    resetDirtyState() {
      this.initialValuesSnapshot = this.buildDirtySnapshot();
      this.isDirty = false;
      this.onDirtyChange(false);
    }

    updateDirtyState() {
      const dirty = this.hasUnsavedChanges();
      if (dirty === this.isDirty) {
        return;
      }
      this.isDirty = dirty;
      this.onDirtyChange(dirty);
    }

    hasUnsavedChanges() {
      if (["create", "edit"].indexOf(this.actionMode) === -1) {
        return false;
      }
      return this.buildDirtySnapshot() !== this.initialValuesSnapshot;
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

    buildDirtySnapshot() {
      const values = this.collectAllValues();
      delete values._runtime;
      return JSON.stringify(values);
    }

    readFieldCurrentValue(fieldName) {
      const item = this.inputs[fieldName];
      if (!item) {
        return this.data ? this.data[fieldName] : undefined;
      }
      if (!item.input) {
        return this.data ? this.data[fieldName] : undefined;
      }
      return this.readWidgetValue(item.input, item.field);
    }

    getConfiguredEventEffects(eventConfig) {
      return global.CrudUtils.ensureArray(eventConfig.effects);
    }

    getResponseEventEffects(eventConfig, response) {
      if (response && Array.isArray(response.effects)) {
        return response.effects;
      }
      if (eventConfig.response && Array.isArray(eventConfig.response.effects)) {
        return eventConfig.response.effects;
      }
      return this.getConfiguredEventEffects(eventConfig);
    }

    applyEffects(effects, context) {
      this.applyingEffects = true;
      try {
        global.CrudUtils.ensureArray(effects).forEach((effect) => {
          if (!this.evaluateCondition(effect && effect.when, context)) {
            return;
          }
          this.applyEffect(effect, context);
        });
      } finally {
        this.applyingEffects = false;
      }
    }

    handleBackendValidationResponse(payload, retryCallback) {
      const normalized = global.CrudUtils.normalizeBackendValidation(payload, "Existem inconsistencias no formulario.");
      if (!normalized.hasValidation) {
        return false;
      }

      const validation = normalized.validation || {};
      const responseContext = this.buildEventContext({
        validation,
        error: normalized.error || {},
        effects: normalized.effects || []
      });
      this.applyEffects(normalized.effects, responseContext);
      this.applyBackendValidationMessages(validation.messages || []);

      global.CrudUtils.showBackendValidation(validation, {
        themeColor: validation.status === "warning" ? "warning" : "primary"
      }).then((confirmed) => {
        if (confirmed && validation.requiresConfirmation && validation.confirmationToken && typeof retryCallback === "function") {
          retryCallback(validation.confirmationToken);
        }
      });

      return true;
    }

    clearBackendValidation() {
      Object.keys(this.inputs || {}).forEach((fieldName) => {
        const item = this.inputs[fieldName];
        if (!item || !item.wrapper) {
          return;
        }
        item.wrapper.removeClass("crud-field-backend-invalid crud-field-backend-warning");
        item.wrapper.find(".crud-field-validation-message").remove();
        if (item.input) {
          item.input.attr("aria-invalid", "false");
        }
      });
    }

    applyBackendValidationMessages(messages) {
      this.clearBackendValidation();
      let firstInvalid = null;
      global.CrudUtils.ensureArray(messages).forEach((message) => {
        const fieldName = message && (message.field || message.target);
        if (!fieldName || !this.inputs[fieldName]) {
          return;
        }
        const item = this.inputs[fieldName];
        const type = message.type === "warning" ? "warning" : "error";
        item.wrapper
          .addClass(type === "warning" ? "crud-field-backend-warning" : "crud-field-backend-invalid")
          .append($("<div class=\"crud-field-validation-message\"></div>").text(message.message || "Campo invalido."));
        if (item.input) {
          item.input.attr("aria-invalid", "true");
        }
        if (!firstInvalid) {
          firstInvalid = item;
        }
      });
      if (firstInvalid) {
        this.focusField(firstInvalid);
      }
    }

    focusField(item) {
      if (!item) {
        return;
      }
      const target = item.input || item.readonlyElement;
      if (!target || !target.length) {
        return;
      }
      const widget = item.input ? this.getInputWidget(item.input) : null;
      if (widget && widget.element && typeof widget.element.focus === "function") {
        widget.element.focus();
        return;
      }
      target.trigger("focus");
    }

    applyEffect(effect, context) {
      if (!effect || !effect.action) {
        return;
      }

      const action = effect.action;
      const target = effect.target || effect.field;
      const value = this.resolveEffectValue(effect, context);

      if (action === "setValue") {
        this.setFieldValue(target, value);
      } else if (action === "clearValue") {
        this.setFieldValue(target, "");
      } else if (action === "readonly") {
        this.setFieldReadonly(target, Boolean(value));
      } else if (action === "enabled") {
        this.setFieldReadonly(target, !Boolean(value));
      } else if (action === "disabled") {
        this.setFieldReadonly(target, Boolean(value));
      } else if (action === "visible") {
        this.setTargetVisible(target, Boolean(value));
      } else if (action === "show") {
        this.setTargetVisible(target, true);
      } else if (action === "hide") {
        this.setTargetVisible(target, false);
      } else if (action === "required") {
        this.setFieldRequired(target, Boolean(value));
      } else if (action === "setOptions") {
        this.setFieldOptions(target, value);
      } else if (action === "reloadOptions") {
        this.reloadFieldOptions(target, effect, context);
      } else if (action === "showMessage") {
        global.CrudUtils.showMessage(String(value || effect.message || ""), effect.type || "info");
      }
    }

    resolveEffectValue(effect, context) {
      if (effect.valueWhen) {
        return this.evaluateCondition(effect.valueWhen, context);
      }
      if (effect.valueFrom) {
        return this.resolveContextPath(effect.valueFrom, context);
      }
      if (effect.responseField) {
        return global.CrudUtils.getByPath(context.response, effect.responseField);
      }
      if (effect.fieldValue) {
        return context.values[effect.fieldValue];
      }
      if (effect.value != null) {
        return effect.value;
      }
      return effect.message || true;
    }

    resolveContextPath(path, context) {
      if (path == null) {
        return undefined;
      }
      if (typeof path !== "string") {
        return path;
      }
      if (Object.prototype.hasOwnProperty.call(context.values, path)) {
        return context.values[path];
      }
      return global.CrudUtils.getByPath(context, path);
    }

    evaluateCondition(condition, context) {
      if (!condition) {
        return true;
      }
      if (Array.isArray(condition.all)) {
        return condition.all.every((item) => this.evaluateCondition(item, context));
      }
      if (Array.isArray(condition.any)) {
        return condition.any.some((item) => this.evaluateCondition(item, context));
      }
      if (condition.not) {
        return !this.evaluateCondition(condition.not, context);
      }

      const actual = condition.field
        ? context.values[condition.field]
        : condition.responseField
          ? global.CrudUtils.getByPath(context.response, condition.responseField)
          : condition.path
            ? this.resolveContextPath(condition.path, context)
            : undefined;
      const expected = condition.value;
      const operator = condition.operator || "eq";

      switch (operator) {
        case "eq":
          return String(actual) === String(expected);
        case "neq":
          return String(actual) !== String(expected);
        case "in":
          return global.CrudUtils.ensureArray(expected).map(String).indexOf(String(actual)) !== -1;
        case "notIn":
          return global.CrudUtils.ensureArray(expected).map(String).indexOf(String(actual)) === -1;
        case "contains":
          return String(actual || "").indexOf(String(expected || "")) !== -1;
        case "isEmpty":
          return actual === "";
        case "isNotEmpty":
          return actual !== "" && actual != null;
        case "isNull":
          return actual == null;
        case "isNotNull":
          return actual != null;
        case "truthy":
          return Boolean(actual);
        case "falsy":
          return !actual;
        default:
          return false;
      }
    }

    normalizeTarget(target) {
      const value = String(target || "");
      if (value.indexOf("field.") === 0) {
        return { type: "field", id: value.slice(6) };
      }
      if (value.indexOf("tab.") === 0) {
        return { type: "tab", id: value.slice(4) };
      }
      if (value.indexOf("section.") === 0) {
        return { type: "section", id: value.slice(8) };
      }
      if (value.indexOf("step.") === 0) {
        return { type: "step", id: value.slice(5) };
      }
      return { type: "field", id: value };
    }

    setFieldValue(target, value) {
      const normalized = this.normalizeTarget(target);
      const fieldName = normalized.id;
      const item = this.inputs[fieldName];
      if (!item) {
        return;
      }

      this.data[fieldName] = value;
      if (item.readonlyElement) {
        item.readonlyElement.text(this.formatReadonly(item.field, value));
        return;
      }
      if (!item.input) {
        return;
      }
      this.writeWidgetValue(item.input, item.field, value);
    }

    writeWidgetValue(input, field, value) {
      const widget = this.getInputWidget(input);
      if (field.type === "boolean" || field.editor === "switch") {
        if (widget && typeof widget.check === "function") {
          widget.check(Boolean(value));
        }
        return;
      }
      if (widget && typeof widget.value === "function") {
        widget.value(value == null ? "" : value);
        return;
      }
      input.val(value == null ? "" : value);
    }

    setFieldReadonly(target, readonly) {
      const normalized = this.normalizeTarget(target);
      const item = this.inputs[normalized.id];
      if (!item) {
        return;
      }
      if (!this.formRuntime) {
        this.formRuntime = { readonly: {}, required: {}, visible: {} };
      }
      this.formRuntime.readonly[normalized.id] = readonly;
      item.readonly = readonly;
      item.wrapper.toggleClass("crud-field-readonly", readonly);

      const widget = item.input ? this.getInputWidget(item.input) : null;
      if (widget && typeof widget.enable === "function") {
        widget.enable(!readonly);
      } else if (item.input) {
        item.input.prop("disabled", readonly);
      }
    }

    setFieldRequired(target, required) {
      const normalized = this.normalizeTarget(target);
      const item = this.inputs[normalized.id];
      if (!item) {
        return;
      }
      if (!this.formRuntime) {
        this.formRuntime = { readonly: {}, required: {}, visible: {} };
      }
      this.formRuntime.required[normalized.id] = required;
      item.wrapper.toggleClass("crud-field-required", required);
      if (item.input) {
        item.input.prop("required", required);
      }
    }

    setTargetVisible(target, visible) {
      const normalized = this.normalizeTarget(target);
      if (normalized.type === "tab") {
        this.setTabVisible(normalized.id, visible);
        return;
      }
      if (normalized.type === "section") {
        this.formElement.find(".crud-section[data-section-id=\"" + normalized.id + "\"]").toggle(visible);
        return;
      }
      if (normalized.type === "step") {
        this.setStepVisible(normalized.id, visible);
        return;
      }
      const item = this.inputs[normalized.id];
      if (item && item.wrapper) {
        item.wrapper.toggle(visible);
      }
    }

    setStepVisible(stepId, visible) {
      if (!this.formRuntime) {
        this.formRuntime = { readonly: {}, required: {}, visible: {}, steps: {} };
      }
      if (!this.formRuntime.steps) {
        this.formRuntime.steps = {};
      }
      this.formRuntime.steps[stepId] = Boolean(visible);
      this.formElement.find(".crud-form-step-nav-button[data-step-id=\"" + stepId + "\"]").closest("li").toggle(Boolean(visible));

      const currentStep = this.getFormSteps()[this.currentStepIndex];
      if (currentStep && currentStep.id === stepId && !visible) {
        this.currentStepIndex = 0;
        this.renderForm();
      }
    }

    setTabVisible(tabId, visible) {
      const tabStripElement = this.formElement.find(".crud-form-tabs").first();
      const tabStrip = tabStripElement.data("kendoTabStrip");
      const tabItem = tabStripElement.find("li[data-tab-id=\"" + tabId + "\"]").first();
      const contentItem = tabStripElement.find(".crud-form-tab-content[data-tab-id=\"" + tabId + "\"]").first();
      tabItem.toggle(visible);
      contentItem.toggle(visible);

      if (!visible && tabStrip && tabItem.hasClass("k-active")) {
        const nextVisible = tabStripElement.find("li[data-tab-id]:visible").first();
        if (nextVisible.length) {
          tabStrip.select(nextVisible);
        }
      }
    }

    reloadFieldOptions(target, effect, context) {
      const options = this.resolveEffectOptions(effect, context);
      this.setFieldOptions(target, options, {
        clearInvalidValue: effect.clearInvalidValue !== false,
        disableWhenEmpty: effect.disableWhenEmpty === true,
        selectedValue: effect.selectedValue,
        selectedValueFrom: effect.selectedValueFrom,
        context
      });
    }

    resolveEffectOptions(effect, context) {
      if (!effect) {
        return [];
      }
      if (effect.optionsFrom) {
        return this.resolveContextPath(effect.optionsFrom, context);
      }
      if (effect.options) {
        return effect.options;
      }
      if (effect.valueFrom) {
        return this.resolveContextPath(effect.valueFrom, context);
      }
      if (effect.responseField) {
        return global.CrudUtils.getByPath(context.response, effect.responseField);
      }
      if (effect.value != null) {
        return effect.value;
      }
      return context.response.options || context.response.items || context.response.data || [];
    }

    setFieldOptions(target, options, settings) {
      const normalized = this.normalizeTarget(target);
      const item = this.inputs[normalized.id];
      if (!item || !item.input) {
        return;
      }
      const widget = item.input.data("kendoDropDownList");
      if (!widget) {
        return;
      }
      const nextOptions = this.normalizeOptionItems(options);
      const currentValue = this.readFieldCurrentValue(normalized.id);
      const selectedValue = this.resolveSelectedOptionValue(settings || {}, currentValue, nextOptions);

      item.field.options = nextOptions;
      widget.setDataSource(new kendo.data.DataSource({ data: nextOptions }));
      widget.value(selectedValue == null ? "" : selectedValue);
      this.data[normalized.id] = selectedValue == null ? "" : selectedValue;

      if (settings && settings.disableWhenEmpty && typeof widget.enable === "function") {
        widget.enable(nextOptions.length > 0);
      }
    }

    resolveSelectedOptionValue(settings, currentValue, options) {
      if (settings.selectedValueFrom && settings.context) {
        return this.resolveContextPath(settings.selectedValueFrom, settings.context);
      }
      if (settings.selectedValue != null) {
        return settings.selectedValue;
      }
      if (settings.clearInvalidValue === false || currentValue == null || currentValue === "") {
        return currentValue == null ? "" : currentValue;
      }
      const currentText = String(currentValue);
      const found = global.CrudUtils.ensureArray(options).some(function(option) {
        return String(option && option.value) === currentText;
      });
      return found ? currentValue : "";
    }

    normalizeOptionItems(options) {
      return global.CrudUtils.ensureArray(options).map(function(option) {
        if (option == null) {
          return { value: "", text: "" };
        }
        if (typeof option !== "object") {
          return { value: option, text: String(option) };
        }
        const value = option.value != null
          ? option.value
          : option.id != null
            ? option.id
            : option.codigo != null
              ? option.codigo
              : option.code;
        const text = option.text != null
          ? option.text
          : option.label != null
            ? option.label
            : option.nome != null
              ? option.nome
              : option.name != null
                ? option.name
                : option.descricao != null
                  ? option.descricao
                  : option.description != null
                    ? option.description
                    : value;
        return Object.assign({}, option, {
          value: value == null ? "" : value,
          text: text == null ? "" : text
        });
      });
    }

    getInputWidget(input) {
      if (!input) {
        return null;
      }
      return input.data("kendoTextBox") ||
        input.data("kendoNumericTextBox") ||
        input.data("kendoDatePicker") ||
        input.data("kendoDateTimePicker") ||
        input.data("kendoSwitch") ||
        input.data("kendoDropDownList") ||
        null;
    }

    renderFooterAppbar(container) {
      const actions = $("<div class=\"crud-form-appbar crud-form-footer-appbar\"></div>").appendTo(container);
      const canConfirm = this.canConfirm();
      const closeOnCancel = this.shouldCloseOnCancel();
      const canCancel = Boolean(this.actionMode) || closeOnCancel;
      const confirmButtonConfig = this.getFormButton("confirm") || this.getFormButton("save");
      const cancelButtonConfig = this.getFormButton("cancel");
      $("<button type=\"button\"></button>")
        .text(confirmButtonConfig && confirmButtonConfig.label ? confirmButtonConfig.label : "Confirmar")
        .appendTo(actions)
        .kendoButton({
          themeColor: "primary",
          icon: confirmButtonConfig && confirmButtonConfig.icon || "check",
          enable: canConfirm
        })
        .on("click", () => {
          if (canConfirm) {
            this.confirmAction(confirmButtonConfig);
          }
        });
      $("<button type=\"button\"></button>")
        .text(cancelButtonConfig && cancelButtonConfig.label ? cancelButtonConfig.label : "Cancelar")
        .appendTo(actions)
        .kendoButton({
          icon: cancelButtonConfig && cancelButtonConfig.icon || "cancel",
          enable: canCancel
        })
        .on("click", () => {
          if (!canCancel) {
            return;
          }
          this.confirmDiscardChanges().then((confirmed) => {
            if (!confirmed) {
              return;
            }
            this.executeConfiguredButton(cancelButtonConfig, {
              event: "cancel",
              action: "cancel"
            }).then((shouldContinue) => {
              if (shouldContinue === false) {
                return;
              }
              if (closeOnCancel) {
                this.close(true);
              } else {
                this.deactivateAction();
              }
            });
          });
        });
      this.renderConfiguredButtons(actions, "footer");
    }

    canConfirm() {
      if (!this.actionMode) {
        return false;
      }
      if (this.isStepLayout() && !this.isLastFormStep()) {
        return false;
      }
      if (this.actionMode === "delete") {
        return global.CrudUtils.getPermission(this.definition, "delete") && this.hasCurrentRecord();
      }
      const saveButton = global.CrudUtils.ensureArray(this.definition.form.buttons).find(function(button) {
        return button.action === "save";
      });
      if (saveButton && saveButton.visibleIn && saveButton.visibleIn.indexOf(this.mode) === -1) {
        return false;
      }
      if (saveButton && saveButton.permission) {
        return global.CrudUtils.getPermission(this.definition, saveButton.permission);
      }
      return global.CrudUtils.getPermission(this.definition, this.mode === "create" ? "create" : "edit");
    }

    isStepLayout() {
      return this.getFormConfig().layout === "steps" && this.getFormSteps().length > 0;
    }

    isLastFormStep() {
      const steps = this.getFormSteps();
      return !steps.length || this.currentStepIndex >= steps.length - 1;
    }

    confirmAction(buttonConfig) {
      if (this.actionMode === "delete") {
        const deleteButtonConfig = this.getFormButton("delete");
        Promise.resolve(this.onDelete(global.CrudUtils.clone(this.data), {
          confirm: false,
          action: deleteButtonConfig,
          concurrencyWarningShown: true,
          skipRecordPreparation: true
        })).then((deleted) => {
          if (deleted) {
            this.close();
          }
        });
        return;
      }
      this.save(buttonConfig);
    }

    save(buttonConfig) {
      const payload = this.collectValues();
      payload._runtime = Object.assign({}, this.data && this.data._runtime || {}, payload._runtime || {});
      const validationErrors = this.validatePayload(payload);
      if (validationErrors.length) {
        global.CrudUtils.showMessage(validationErrors.join("\n"), "error");
        return;
      }
      const primaryKey = this.definition.dataModel.primaryKey;
      const isCreate = this.mode === "create";
      const endpoint = this.resolveButtonEndpoint(buttonConfig, isCreate ? "create" : "edit") ||
        (isCreate ? this.definition.api.create : this.definition.api.update);
      const urlParams = Object.assign({}, this.data || {}, payload || {}, {
        [primaryKey]: payload[primaryKey] != null ? payload[primaryKey] : this.data[primaryKey]
      });
      const url = global.CrudUtils.replaceUrlParams(endpoint.url, urlParams);

      const send = (requestPayload) => this.httpClient.request({
        url,
        method: endpoint.method || (isCreate ? "POST" : "PUT"),
        data: requestPayload
      }).then((response) => {
        if (this.handleBackendValidationResponse(response, (token) => {
          const retryPayload = global.CrudUtils.clone(requestPayload);
          retryPayload._runtime = Object.assign({}, retryPayload._runtime || {}, {
            validationConfirmationToken: token
          });
          return send(retryPayload);
        })) {
          return;
        }
        const savedMode = this.mode;
        this.clearBackendValidation();
        this.applyEffects(response && response.effects, {
          event: "save",
          values: requestPayload,
          response: response || {},
          effects: response && response.effects || []
        });
        this.resetDirtyState();
        if (this.shouldCloseOnSave()) {
          this.close(true);
        } else {
          this.passiveData = global.CrudUtils.clone(response);
          this.data = global.CrudUtils.clone(response);
          this.mode = "view";
          this.actionMode = null;
          this.renderForm();
          this.resetDirtyState();
        }
        this.onSaved(response, savedMode);
      }).catch((error) => {
        if (this.handleBackendValidationResponse(error, (token) => {
          const retryPayload = global.CrudUtils.clone(requestPayload);
          retryPayload._runtime = Object.assign({}, retryPayload._runtime || {}, {
            validationConfirmationToken: token
          });
          return send(retryPayload);
        })) {
          return;
        }
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao salvar.");
        global.CrudUtils.showMessage(normalized.message, "error");
      });

      send(payload);
    }

    resolveButtonEndpoint(buttonConfig, mode) {
      if (!buttonConfig) {
        return null;
      }
      const configured = this.getButtonEndpointConfig(buttonConfig, mode);
      const api = this.definition.api || {};
      if (!configured) {
        return null;
      }
      if (typeof configured === "string") {
        return api[configured] || null;
      }
      if (configured.endpointId && api[configured.endpointId]) {
        return api[configured.endpointId];
      }
      if (configured.actionId && api[configured.actionId]) {
        return api[configured.actionId];
      }
      if (configured.endpoint && configured.endpoint.url) {
        return configured.endpoint;
      }
      if (configured.api && configured.api.url) {
        return configured.api;
      }
      if (configured.url) {
        return {
          url: configured.url,
          method: configured.method || buttonConfig.method
        };
      }
      return null;
    }

    getButtonEndpointConfig(buttonConfig, mode) {
      if (!buttonConfig) {
        return null;
      }
      const byMode = buttonConfig.endpoints || buttonConfig.endpointByMode;
      if (byMode && byMode[mode]) {
        return byMode[mode];
      }
      if (buttonConfig.endpointId || buttonConfig.actionId || buttonConfig.endpoint || buttonConfig.api || buttonConfig.url) {
        return buttonConfig;
      }
      return null;
    }

    collectValues() {
      const values = Object.assign({}, this.data);
      Object.keys(this.inputs).forEach((fieldName) => {
        const item = this.inputs[fieldName];
        if (item.readonly) {
          return;
        }
        const value = this.readWidgetValue(item.input, item.field);
        values[fieldName] = value;
      });
      const customCode = this.buildCustomCodeRuntimePayload();
      if (Object.keys(customCode).length) {
        values._customCode = customCode;
      }
      return values;
    }

    validatePayload(payload) {
      const errors = [];
      Object.keys(this.inputs).forEach((fieldName) => {
        const item = this.inputs[fieldName];
        if (item.readonly) {
          return;
        }

        const field = item.field;
        const value = payload[fieldName];
        const validation = field.validation || {};
        const runtimeRequired = this.formRuntime && this.formRuntime.required[fieldName];
        const required = runtimeRequired === true || validation.required || field.nullable === false;
        const generatedByBackend = (field.editor || field.type) === "customCode";

        if (required && !generatedByBackend && (value == null || value === "")) {
          errors.push(field.label + " e obrigatorio.");
        }
        if (validation.maxLength && value != null && String(value).length > validation.maxLength) {
          errors.push(field.label + " deve ter no maximo " + validation.maxLength + " caracteres.");
        }
      });
      if (this.isStepLayout()) {
        this.getFormSteps().forEach((step) => {
          if (this.isStepReadonly(step)) {
            return;
          }
          this.validateStepRequiredFields(step, payload).forEach(function(error) {
            if (errors.indexOf(error) === -1) {
              errors.push(error);
            }
          });
        });
      }
      return errors;
    }

    readWidgetValue(input, field) {
      if (field.type === "integer" || field.type === "decimal" || field.type === "number" || field.editor === "currency") {
        return input.data("kendoNumericTextBox").value();
      }
      if (field.type === "date") {
        const value = input.data("kendoDatePicker").value();
        return value ? value.toISOString().slice(0, 10) : null;
      }
      if (field.type === "datetime") {
        const value = input.data("kendoDateTimePicker").value();
        return value ? value.toISOString() : null;
      }
      if (field.type === "boolean" || field.editor === "switch") {
        return input.data("kendoSwitch").check();
      }
      if (field.type === "enum" || field.editor === "dropdown") {
        return input.data("kendoDropDownList").value();
      }
      return input.val();
    }

    buildCustomCodeRuntimePayload() {
      const payload = {};
      Object.keys(this.customCodeState || {}).forEach((fieldName) => {
        const state = this.customCodeState[fieldName];
        const item = this.inputs[fieldName];
        if (!state || !item || item.readonly) {
          return;
        }
        const config = item.field && item.field.customCode || {};
        const properties = Object.assign({}, state.properties || {});
        payload[fieldName] = {
          properties
        };
        if (config.mode === "pattern" || config.promptFields) {
          payload[fieldName].config = global.CrudUtils.clone(config);
        }
      });
      return payload;
    }

    openCustomCodeDialog(fieldName) {
      const state = this.customCodeState && this.customCodeState[fieldName];
      const item = this.inputs[fieldName];
      if (!state || !item) {
        return;
      }
      const config = item.field && item.field.customCode || {};
      const promptFields = global.CrudUtils.ensureArray(config.promptFields);
      if (!promptFields.length) {
        global.CrudUtils.showMessage("Este codigo sera gerado automaticamente no salvar.", "info");
        return;
      }

      const wrapper = $("<div></div>").appendTo(document.body);
      const body = $("<form class=\"crud-form\"></form>").appendTo(wrapper);
      const grid = $("<div class=\"crud-section-grid\"></div>").css("--crud-columns", 1).appendTo(body);
      const inputs = {};
      promptFields.forEach((promptField) => {
        const fieldWrapper = $("<div class=\"crud-field\"></div>").appendTo(grid);
        $("<label></label>").text(promptField.label || promptField.name).appendTo(fieldWrapper);
        const input = $("<input>").appendTo(fieldWrapper);
        const savedValue = state.properties[promptField.name];
        switch (promptField.type) {
          case "integer":
          case "decimal":
            input.kendoNumericTextBox({
              value: savedValue == null ? null : Number(savedValue),
              format: promptField.type === "integer" ? "n0" : "n2",
              decimals: promptField.type === "integer" ? 0 : 2
            });
            break;
          case "boolean":
            input.kendoSwitch({
              checked: Boolean(savedValue)
            });
            break;
          case "enum":
          case "dropdown":
            input.kendoDropDownList({
              dataTextField: "text",
              dataValueField: "value",
              dataSource: global.CrudUtils.ensureArray(promptField.options),
              value: savedValue == null ? "" : savedValue,
              optionLabel: "Selecione"
            });
            break;
          default:
            input.kendoTextBox({
              value: savedValue == null ? "" : savedValue
            });
        }
        inputs[promptField.name] = {
          field: promptField,
          input
        };
      });
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(body);
      const saveButton = $("<button type=\"button\"></button>").text("Aplicar").appendTo(actions);
      const cancelButton = $("<button type=\"button\"></button>").text("Cancelar").appendTo(actions);
      saveButton.kendoButton({ icon: "check", themeColor: "primary" });
      cancelButton.kendoButton({ icon: "cancel" });

      wrapper.kendoWindow({
        title: config.promptTitle || ("Codificacao de " + (item.field.label || fieldName)),
        modal: true,
        visible: false,
        width: Math.min(520, Math.max(320, global.innerWidth - 24)),
        resizable: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });
      const windowWidget = wrapper.data("kendoWindow");

      saveButton.on("click", () => {
        const properties = {};
        for (const name in inputs) {
          const entry = inputs[name];
          const value = this.readCustomCodePromptValue(entry.input, entry.field);
          if (entry.field.required && (value == null || value === "")) {
            global.CrudUtils.showMessage((entry.field.label || name) + " e obrigatorio.", "warning");
            return;
          }
          properties[name] = value;
        }
        state.properties = properties;
        state.hint.text("Propriedades da codificacao configuradas. O backend gera o codigo final ao salvar.");
        this.updateDirtyState();
        global.CrudUtils.showMessage("Propriedades da codificacao atualizadas.", "success");
        windowWidget.close();
      });
      cancelButton.on("click", function() {
        windowWidget.close();
      });
      windowWidget.center().open();
    }

    openCustomCodeAssistantScreen(fieldName) {
      const state = this.customCodeState && this.customCodeState[fieldName];
      const item = this.inputs[fieldName];
      if (!state || !item) {
        return;
      }
      const config = item.field && item.field.customCode || {};
      if (!config.assistantScreenId) {
        this.openCustomCodeDialog(fieldName);
        return;
      }
      if (typeof global.ProcessEngine !== "function") {
        global.CrudUtils.showMessage("ProcessEngine nao esta carregado para abrir o assistente.", "error");
        return;
      }

      const wrapper = $("<div></div>").appendTo(document.body);
      const root = $("<div class=\"crud-custom-code-assistant-root\"></div>").appendTo(wrapper);
      let processEngine = null;
      wrapper.kendoWindow({
        title: config.promptTitle || ("Assistente de " + (item.field.label || fieldName)),
        modal: true,
        visible: false,
        width: Math.min(820, Math.max(360, global.innerWidth - 24)),
        resizable: true,
        close: function() {
          if (processEngine && typeof processEngine.destroy === "function") {
            processEngine.destroy();
          }
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });
      const windowWidget = wrapper.data("kendoWindow");
      processEngine = new global.ProcessEngine({
        root: root,
        screenId: config.assistantScreenId,
        config: this.config || {},
        httpClient: this.httpClient,
        hideHeader: true,
        contextPayload: {
          parentField: fieldName,
          parentValues: this.collectAllValues()
        },
        onResult: (payload) => {
          if (payload && payload.result && payload.result.previewCode) {
            this.openCustomCodePreviewConfirm(fieldName, state, payload, windowWidget);
            return;
          }
          state.properties = Object.assign({}, payload && payload.values || {});
          state.hint.text("Assistente da codificacao aplicado. O backend gera o codigo final ao salvar.");
          this.updateDirtyState();
          global.CrudUtils.showMessage("Propriedades da codificacao atualizadas.", "success");
          windowWidget.close();
        }
      });
      processEngine.init().catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel abrir o assistente da codificacao.");
        global.CrudUtils.showMessage(normalized.message, "error");
        windowWidget.close();
      });
      windowWidget.center().open();
    }

    openCustomCodePreviewConfirm(fieldName, state, payload, parentWindow) {
      const result = payload && payload.result || {};
      const values = Object.assign({}, payload && payload.values || {});
      const previewCode = String(result.previewCode || "").trim();
      const previewTitle = result.previewTitle || "Previsao do codigo";
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-custom-code-preview\"></div>").appendTo(wrapper);
      $("<p></p>").text(result.message || "Confira o codigo previsto antes de aplicar as propriedades.").appendTo(content);
      $("<div class=\"crud-custom-code-preview-code\"></div>").text(previewCode).appendTo(content);
      const list = $("<dl class=\"crud-custom-code-preview-properties\"></dl>").appendTo(content);
      Object.keys(values).forEach(function(key) {
        $("<dt></dt>").text(key).appendTo(list);
        $("<dd></dd>").text(values[key] == null ? "" : String(values[key])).appendTo(list);
      });
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const confirmButton = $("<button type=\"button\"></button>").text("Aplicar").appendTo(actions);
      const cancelButton = $("<button type=\"button\"></button>").text("Cancelar").appendTo(actions);
      confirmButton.kendoButton({ icon: "check", themeColor: "primary" });
      cancelButton.kendoButton({ icon: "cancel" });

      wrapper.kendoWindow({
        title: previewTitle,
        modal: true,
        visible: false,
        width: Math.min(520, Math.max(320, global.innerWidth - 24)),
        resizable: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });
      const confirmWindow = wrapper.data("kendoWindow");
      confirmButton.on("click", () => {
        state.properties = values;
        state.hint.text("Assistente da codificacao aplicado. Codigo previsto: " + previewCode + ".");
        this.updateDirtyState();
        global.CrudUtils.showMessage("Propriedades da codificacao atualizadas.", "success");
        confirmWindow.close();
        parentWindow.close();
      });
      cancelButton.on("click", function() {
        confirmWindow.close();
      });
      confirmWindow.center().open();
    }

    readCustomCodePromptValue(input, field) {
      switch (field.type) {
        case "integer":
        case "decimal":
          return input.data("kendoNumericTextBox").value();
        case "boolean":
          return input.data("kendoSwitch").check();
        case "enum":
        case "dropdown":
          return input.data("kendoDropDownList").value();
        default:
          return input.val();
      }
    }

    formatReadonly(field, value) {
      if (value == null || value === "") {
        return "";
      }
      if (field.type === "enum" && field.options) {
        const option = field.options.find(function(item) { return item.value === value; });
        return option ? option.text : value;
      }
      if (field.format === "currency") {
        return kendo.toString(Number(value), "c2");
      }
      if (field.type === "date") {
        return kendo.toString(global.CrudUtils.normalizeDateValue(value), "dd/MM/yyyy");
      }
      return String(value);
    }

    getTitle(mode) {
      const titles = this.definition.form.title || {};
      return titles[mode] || this.definition.title;
    }
  }

  global.CrudKendoFormRenderer = CrudKendoFormRenderer;
})(window, jQuery);
