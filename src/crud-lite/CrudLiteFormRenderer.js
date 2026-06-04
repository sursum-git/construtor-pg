(function(global) {
  "use strict";

  class CrudLiteFormRenderer {
    constructor(options) {
      this.definition = options.definition;
      this.httpClient = options.httpClient;
      this.config = options.config || {};
      this.onSaved = options.onSaved || function() {};
      this.onCreate = options.onCreate || function() {};
      this.onEdit = options.onEdit || function() {};
      this.onDelete = options.onDelete || function() {};
      this.onClosed = options.onClosed || function() {};
      this.onActionCanceled = options.onActionCanceled || function() {};
      this.onButtonAction = options.onButtonAction || function() { return Promise.resolve(true); };
      this.onNavigate = options.onNavigate || function() { return Promise.resolve(null); };
      this.getNavigationState = options.getNavigationState || function() { return { previous: false, next: false }; };
      this.inputs = {};
      this.fieldWrappers = {};
      this.requiredRuntime = {};
      this.readonlyRuntime = {};
      this.visibleRuntime = {};
    }

    open(mode, data) {
      this.mode = this.normalizeMode(mode);
      this.data = global.CrudUtils.clone(data || {});
      this.originalData = global.CrudUtils.clone(this.data);
      this.runtimeContext = this.data && this.data._runtime || {};
      this.activeTabId = "";
      this.dirty = false;
      this.renderModal();
      this.executeLifecycleEvents("afterLoad");
    }

    close(force) {
      if (!force && this.hasUnsavedChanges()) {
        this.confirmDiscardChanges().then((confirmed) => {
          if (confirmed) {
            this.destroyModal();
          }
        });
        return;
      }
      this.destroyModal();
    }

    destroyModal() {
      if (this.modal) {
        this.modal.remove();
        this.modal = null;
      }
      this.onClosed();
    }

    normalizeMode(mode) {
      return ["create", "edit", "delete", "view"].indexOf(mode) === -1 ? "view" : mode;
    }

    renderModal() {
      this.modal = document.createElement("div");
      this.modal.className = "crud-lite-modal-overlay";
      this.modal.innerHTML = [
        "<section class=\"crud-lite-dialog crud-lite-form-dialog\" role=\"dialog\" aria-modal=\"true\">",
        "<header class=\"crud-lite-dialog-header\"><h2>" + global.CrudUtils.escapeHtml(this.getTitle()) + "</h2><button type=\"button\" class=\"crud-lite-dialog-close\" data-lite-close>X</button></header>",
        "<div class=\"crud-lite-dialog-body\"><form class=\"crud-lite-form\"></form></div>",
        "<footer class=\"crud-lite-dialog-footer\"></footer>",
        "</section>"
      ].join("");
      document.body.appendChild(this.modal);
      this.formElement = this.modal.querySelector(".crud-lite-form");
      this.footerElement = this.modal.querySelector(".crud-lite-dialog-footer");
      this.renderForm();
      this.renderFooter();
      this.bindModalEvents();
      const first = this.formElement.querySelector("input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button");
      if (first) {
        first.focus();
      }
    }

    bindModalEvents() {
      this.modal.addEventListener("click", (event) => {
        if (event.target.closest("[data-lite-close]")) {
          this.close(false);
          return;
        }
        const action = event.target.closest("[data-lite-form-action]");
        if (!action) {
          return;
        }
        const actionName = action.getAttribute("data-lite-form-action");
        if (actionName === "save") {
          this.save();
        } else if (actionName === "delete") {
          this.deleteRecord();
        } else if (actionName === "cancel") {
          this.close(false);
        } else if (actionName === "previous" || actionName === "next") {
          this.navigate(actionName);
        }
      });
      this.modal.addEventListener("click", (event) => {
        const tab = event.target.closest("[data-lite-tab]");
        if (!tab) {
          return;
        }
        this.activateTab(tab.getAttribute("data-lite-tab"));
      });
      this.modal.addEventListener("input", (event) => {
        if (event.target.closest("[data-lite-field]")) {
          this.markDirty();
        }
      });
      this.modal.addEventListener("change", (event) => {
        const input = event.target.closest("[data-lite-field]");
        if (!input) {
          return;
        }
        this.markDirty();
        this.executeFieldEvents(input.getAttribute("data-lite-field"), "change");
      });
    }

    renderForm() {
      this.inputs = {};
      this.fieldWrappers = {};
      const groups = this.resolveGroups();
      if (!this.activeTabId && groups.length) {
        this.activeTabId = groups[0].id;
      }
      const tabs = groups.length > 1
        ? "<nav class=\"crud-lite-tabs\" role=\"tablist\">" + groups.map((group) => {
          return "<button type=\"button\" role=\"tab\" class=\"crud-lite-tab" + (group.id === this.activeTabId ? " crud-lite-tab-active" : "") + "\" data-lite-tab=\"" + global.CrudUtils.escapeHtml(group.id) + "\">" + global.CrudUtils.escapeHtml(group.title) + "</button>";
        }).join("") + "</nav>"
        : "";
      const panels = groups.map((group) => {
        return "<section class=\"crud-lite-tab-panel\" data-lite-tab-panel=\"" + global.CrudUtils.escapeHtml(group.id) + "\"" + (group.id === this.activeTabId ? "" : " hidden") + ">" +
          this.renderSections(group.sections || []) +
          "</section>";
      }).join("");
      this.formElement.innerHTML = tabs + panels;
      this.captureInputs();
    }

    renderSections(sections) {
      return global.CrudUtils.ensureArray(sections).map((section) => {
        return "<section class=\"crud-lite-form-section\"><h3>" + global.CrudUtils.escapeHtml(section.title || "") + "</h3><div class=\"crud-lite-form-grid\">" +
          global.CrudUtils.ensureArray(section.fields).map((fieldConfig) => this.renderField(fieldConfig)).join("") +
          "</div></section>";
      }).join("");
    }

    renderField(fieldConfig) {
      const config = typeof fieldConfig === "string" ? { field: fieldConfig } : fieldConfig || {};
      const fieldName = config.field;
      const field = this.definition.dataModel.fields[fieldName] || {};
      const label = config.label || field.label || fieldName;
      const id = this.getInputId(fieldName);
      const readonly = this.isReadonly(fieldName, config, field);
      const required = this.isRequired(fieldName, config, field);
      const hidden = this.visibleRuntime[fieldName] === false;
      return [
        "<label class=\"crud-lite-field" + (hidden ? " crud-lite-hidden" : "") + "\" data-lite-field-wrapper=\"" + global.CrudUtils.escapeHtml(fieldName) + "\">",
        "<span>" + global.CrudUtils.escapeHtml(label) + (required ? " <strong>*</strong>" : "") + "</span>",
        this.renderEditor(id, fieldName, field, readonly, required),
        "<small class=\"crud-lite-field-error\" data-lite-field-error=\"" + global.CrudUtils.escapeHtml(fieldName) + "\"></small>",
        "</label>"
      ].join("");
    }

    renderEditor(id, fieldName, field, readonly, required) {
      const value = this.data[fieldName] == null ? "" : this.data[fieldName];
      const disabled = readonly ? " disabled" : "";
      const requiredAttr = required ? " required" : "";
      const common = " id=\"" + global.CrudUtils.escapeHtml(id) + "\" data-lite-field=\"" + global.CrudUtils.escapeHtml(fieldName) + "\"" + disabled + requiredAttr;
      if (field.editor === "textarea" || field.type === "text") {
        return "<textarea" + common + ">" + global.CrudUtils.escapeHtml(value) + "</textarea>";
      }
      if (this.hasOptions(field)) {
        return "<select" + common + ">" + global.CrudUtils.ensureArray(field.options).map(function(option) {
          const optionValue = option.value == null ? "" : String(option.value);
          return "<option value=\"" + global.CrudUtils.escapeHtml(optionValue) + "\"" + (String(value) === optionValue ? " selected" : "") + ">" + global.CrudUtils.escapeHtml(option.text || option.label || optionValue) + "</option>";
        }).join("") + "</select>";
      }
      if (field.type === "date" || field.type === "datetime") {
        return "<input type=\"date\"" + common + " value=\"" + global.CrudUtils.escapeHtml(String(value).slice(0, 10)) + "\">";
      }
      if (field.type === "integer" || field.type === "decimal" || field.type === "number") {
        return "<input type=\"number\"" + common + " value=\"" + global.CrudUtils.escapeHtml(value) + "\">";
      }
      if (field.type === "email") {
        return "<input type=\"email\"" + common + " value=\"" + global.CrudUtils.escapeHtml(value) + "\">";
      }
      return "<input type=\"text\"" + common + " value=\"" + global.CrudUtils.escapeHtml(value) + "\">";
    }

    renderFooter() {
      const navigation = this.mode !== "create" && this.definition.form && this.definition.form.navigation && this.definition.form.navigation.enabled !== false;
      const navState = navigation ? this.getNavigationState(this.data) : { previous: false, next: false };
      const buttons = [];
      if (navigation) {
        buttons.push("<button type=\"button\" class=\"crud-lite-button\" data-lite-form-action=\"previous\"" + (!navState.previous ? " disabled" : "") + ">Anterior</button>");
        buttons.push("<button type=\"button\" class=\"crud-lite-button\" data-lite-form-action=\"next\"" + (!navState.next ? " disabled" : "") + ">Proximo</button>");
      }
      if (this.mode === "create" || this.mode === "edit") {
        buttons.push("<button type=\"button\" class=\"crud-lite-button crud-lite-button-primary\" data-lite-form-action=\"save\">Confirmar</button>");
        buttons.push("<button type=\"button\" class=\"crud-lite-button\" data-lite-form-action=\"cancel\">Cancelar</button>");
      } else if (this.mode === "delete") {
        buttons.push("<button type=\"button\" class=\"crud-lite-button crud-lite-button-danger\" data-lite-form-action=\"delete\">Excluir</button>");
        buttons.push("<button type=\"button\" class=\"crud-lite-button\" data-lite-form-action=\"cancel\">Cancelar</button>");
      } else {
        buttons.push("<button type=\"button\" class=\"crud-lite-button\" data-lite-form-action=\"cancel\">Fechar</button>");
      }
      this.footerElement.innerHTML = buttons.join("");
    }

    captureInputs() {
      this.formElement.querySelectorAll("[data-lite-field]").forEach((input) => {
        const fieldName = input.getAttribute("data-lite-field");
        this.inputs[fieldName] = input;
        this.fieldWrappers[fieldName] = this.formElement.querySelector("[data-lite-field-wrapper=\"" + CSS.escape(fieldName) + "\"]");
      });
    }

    activateTab(tabId) {
      this.activeTabId = tabId;
      this.formElement.querySelectorAll("[data-lite-tab]").forEach(function(tab) {
        tab.classList.toggle("crud-lite-tab-active", tab.getAttribute("data-lite-tab") === tabId);
      });
      this.formElement.querySelectorAll("[data-lite-tab-panel]").forEach(function(panel) {
        panel.hidden = panel.getAttribute("data-lite-tab-panel") !== tabId;
      });
    }

    resolveGroups() {
      const form = this.definition.form || {};
      const steps = global.CrudUtils.ensureArray(form.steps);
      const tabs = global.CrudUtils.ensureArray(form.tabs);
      const source = steps.length ? steps : tabs;
      if (source.length) {
        return source.map(function(item, index) {
          return {
            id: item.id || "tab-" + index,
            title: item.title || "Aba " + (index + 1),
            sections: item.sections || []
          };
        });
      }
      return [{
        id: "geral",
        title: "Geral",
        sections: [{
          title: "Dados",
          fields: Object.keys(this.definition.dataModel.fields).map(function(field) { return { field }; })
        }]
      }];
    }

    save() {
      const errors = this.validate();
      if (errors.length) {
        global.CrudUtils.showMessage(errors.join("\n"), "error");
        return;
      }
      const endpoint = this.mode === "create" ? this.definition.api.create : this.definition.api.update;
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint de gravacao nao configurado.", "error");
        return;
      }
      const values = this.collectValues();
      const primaryKey = this.definition.dataModel.primaryKey;
      const id = this.data[primaryKey] || this.data.id;
      const payload = {
        values,
        _runtime: this.runtimeContext || {}
      };
      const url = global.CrudUtils.replaceUrlParams(endpoint.url, Object.assign({}, values, { [primaryKey]: id, id }));
      this.httpClient.request({
        url,
        method: endpoint.method || (this.mode === "create" ? "POST" : "PUT"),
        data: payload
      }).then((response) => {
        if (response && response.validation) {
          return global.CrudUtils.showBackendValidation(response.validation).then(function() {
            return null;
          });
        }
        return response;
      }).then((response) => {
        if (!response) {
          return;
        }
        this.applyEffects(response && response.effects, { response });
        const record = Object.assign({}, this.data, values, response || {});
        this.dirty = false;
        this.onSaved(record, this.mode);
        this.close(true);
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao gravar registro.");
        if (normalized.validation) {
          global.CrudUtils.showBackendValidation(normalized.validation);
          return;
        }
        global.CrudUtils.showMessage(normalized.message, "error");
      });
    }

    deleteRecord() {
      const endpoint = this.definition.api.delete;
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint de exclusao nao configurado.", "error");
        return;
      }
      global.CrudUtils.confirm("Deseja excluir este registro?", {
        title: "Excluir",
        confirmText: "Excluir",
        confirmIcon: "trash",
        themeColor: "error"
      }).then((confirmed) => {
        if (!confirmed) {
          return null;
        }
        const primaryKey = this.definition.dataModel.primaryKey;
        const id = this.data[primaryKey] || this.data.id;
        const url = global.CrudUtils.replaceUrlParams(endpoint.url, { [primaryKey]: id, id });
        return this.httpClient.request({
          url,
          method: endpoint.method || "DELETE",
          data: {
            id,
            _runtime: this.runtimeContext || {}
          }
        });
      }).then((response) => {
        if (!response) {
          return;
        }
        this.onSaved(this.data, "delete");
        this.close(true);
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao excluir registro.");
        global.CrudUtils.showMessage(normalized.message, "error");
      });
    }

    validate() {
      const errors = [];
      Object.keys(this.inputs).forEach((fieldName) => {
        const field = this.definition.dataModel.fields[fieldName] || {};
        const input = this.inputs[fieldName];
        const value = input.value;
        const label = field.label || fieldName;
        this.setFieldError(fieldName, "");
        if (this.isRequired(fieldName, {}, field) && String(value || "").trim() === "") {
          errors.push(label + " e obrigatorio.");
          this.setFieldError(fieldName, "Obrigatorio.");
        }
        const maxLength = field.validation && field.validation.maxLength;
        if (maxLength && value && String(value).length > Number(maxLength)) {
          errors.push(label + " deve ter no maximo " + maxLength + " caracteres.");
          this.setFieldError(fieldName, "Maximo de " + maxLength + " caracteres.");
        }
      });
      return errors;
    }

    collectValues() {
      const values = {};
      Object.keys(this.inputs).forEach((fieldName) => {
        const input = this.inputs[fieldName];
        if (input.disabled && this.mode !== "delete") {
          values[fieldName] = this.data[fieldName];
          return;
        }
        values[fieldName] = input.value;
      });
      return values;
    }

    markDirty() {
      this.dirty = true;
    }

    hasUnsavedChanges() {
      return Boolean(this.dirty && (this.mode === "create" || this.mode === "edit"));
    }

    confirmDiscardChanges() {
      if (!this.hasUnsavedChanges()) {
        return Promise.resolve(true);
      }
      return global.CrudUtils.confirm("Existem dados alterados e nao salvos. Deseja descartar as alteracoes?", {
        title: "Descartar alteracoes",
        confirmText: "Descartar",
        cancelText: "Voltar",
        confirmIcon: "exclamation-circle",
        themeColor: "warning"
      });
    }

    setRuntimeContext(runtime) {
      this.runtimeContext = runtime || {};
    }

    showConcurrencyWarning(action) {
      const config = this.definition.form && this.definition.form.concurrencyWarning || {};
      if (config.enabled !== true || config.liteEnabled !== true) {
        return Promise.resolve(true);
      }
      const message = action === "delete"
        ? config.deleteMessage || "O registro pode estar em uso. Deseja continuar?"
        : config.editMessage || "O registro pode estar em uso. Deseja continuar?";
      return global.CrudUtils.confirm(message, {
        title: config.title || "Aviso de concorrencia",
        confirmText: config.confirmText || "Continuar",
        cancelText: config.cancelText || "Cancelar",
        confirmIcon: "exclamation-circle",
        themeColor: "warning"
      });
    }

    navigate(direction) {
      this.onNavigate(this.data, direction).then((record) => {
        if (!record) {
          return;
        }
        this.data = global.CrudUtils.clone(record);
        this.originalData = global.CrudUtils.clone(record);
        this.runtimeContext = record._runtime || {};
        this.dirty = false;
        this.renderForm();
        this.renderFooter();
      });
    }

    executeLifecycleEvents(eventName) {
      global.CrudUtils.ensureArray(this.definition.form && this.definition.form.events).forEach((eventConfig) => {
        if (eventConfig.event === eventName) {
          this.executeFormEvent(eventConfig);
        }
      });
    }

    executeFieldEvents(fieldName, eventName) {
      global.CrudUtils.ensureArray(this.definition.form && this.definition.form.events).forEach((eventConfig) => {
        if (eventConfig.source === fieldName && eventConfig.event === eventName) {
          this.executeFormEvent(eventConfig);
        }
      });
    }

    executeFormEvent(eventConfig) {
      const localEffects = global.CrudUtils.ensureArray(eventConfig.effects);
      if (localEffects.length) {
        this.applyEffects(localEffects, { values: this.collectValues() });
      }
      const endpointId = eventConfig.endpointId || eventConfig.actionId;
      const endpoint = endpointId && this.definition.api && this.definition.api[endpointId];
      if (!endpoint || !endpoint.url) {
        return;
      }
      this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {
          values: this.collectValues()
        }
      }).then((response) => {
        this.applyEffects(this.getResponseEffects(eventConfig, response), { response });
        if (response && response.validation) {
          global.CrudUtils.showBackendValidation(response.validation);
        }
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao executar regra do formulario.");
        global.CrudUtils.showMessage(normalized.message, "error");
      });
    }

    getResponseEffects(eventConfig, response) {
      if (response && Array.isArray(response.effects)) {
        return response.effects;
      }
      return global.CrudUtils.ensureArray(eventConfig.response && eventConfig.response.effects).map(function(effect) {
        if (effect.optionsFrom === "response.options") {
          return Object.assign({}, effect, { options: response && response.options || [] });
        }
        return effect;
      });
    }

    applyEffects(effects, context) {
      const values = context && context.values || this.collectValues();
      global.CrudUtils.ensureArray(effects).forEach((effect) => {
        const action = effect.action;
        const target = String(effect.target || "");
        const value = this.resolveEffectValue(effect, values);
        if (action === "showMessage") {
          global.CrudUtils.showMessage(effect.message || effect.title || "", effect.type || "info");
        } else if (action === "setValue") {
          this.setFieldValue(target, value);
        } else if (action === "clearValue") {
          this.setFieldValue(target, "");
        } else if (action === "readonly" || action === "disabled") {
          this.setFieldDisabled(target, value !== false);
        } else if (action === "enabled") {
          this.setFieldDisabled(target, value === false);
        } else if (action === "visible" || action === "show" || action === "hide") {
          this.setTargetVisible(target, action === "hide" ? false : value !== false);
        } else if (action === "required") {
          this.requiredRuntime[target] = value !== false;
        } else if (action === "setOptions" || action === "reloadOptions") {
          this.setFieldOptions(target, effect.options || []);
        }
      });
    }

    resolveEffectValue(effect, values) {
      if (effect.valueWhen) {
        return this.evaluateCondition(effect.valueWhen, values);
      }
      if (Object.prototype.hasOwnProperty.call(effect, "value")) {
        return effect.value;
      }
      return true;
    }

    evaluateCondition(condition, values) {
      const actual = values[condition.field];
      const expected = condition.value;
      if (condition.operator === "neq") {
        return String(actual) !== String(expected);
      }
      return String(actual) === String(expected);
    }

    setFieldValue(fieldName, value) {
      if (this.inputs[fieldName]) {
        this.inputs[fieldName].value = value == null ? "" : value;
      }
    }

    setFieldDisabled(fieldName, disabled) {
      if (this.inputs[fieldName]) {
        this.inputs[fieldName].disabled = Boolean(disabled);
      }
    }

    setTargetVisible(target, visible) {
      if (target.indexOf("step.") === 0 || target.indexOf("tab.") === 0) {
        const id = target.split(".")[1];
        const tab = this.formElement.querySelector("[data-lite-tab=\"" + CSS.escape(id) + "\"]");
        const panel = this.formElement.querySelector("[data-lite-tab-panel=\"" + CSS.escape(id) + "\"]");
        if (tab) {
          tab.hidden = !visible;
        }
        if (panel) {
          panel.hidden = !visible || panel.getAttribute("data-lite-tab-panel") !== this.activeTabId;
        }
        return;
      }
      const wrapper = this.fieldWrappers[target];
      if (wrapper) {
        wrapper.classList.toggle("crud-lite-hidden", !visible);
      }
    }

    setFieldOptions(fieldName, options) {
      const input = this.inputs[fieldName];
      if (!input || input.tagName !== "SELECT") {
        return;
      }
      const current = input.value;
      input.innerHTML = global.CrudUtils.ensureArray(options).map(function(option) {
        const value = option.value == null ? "" : String(option.value);
        return "<option value=\"" + global.CrudUtils.escapeHtml(value) + "\">" + global.CrudUtils.escapeHtml(option.text || option.label || value) + "</option>";
      }).join("");
      if (Array.from(input.options).some(function(option) { return option.value === current; })) {
        input.value = current;
      }
    }

    setFieldError(fieldName, message) {
      const target = this.formElement.querySelector("[data-lite-field-error=\"" + CSS.escape(fieldName) + "\"]");
      if (target) {
        target.textContent = message || "";
      }
    }

    isReadonly(fieldName, config, field) {
      return this.mode === "view" || this.mode === "delete" || config.readonly === true || field.editable === false || this.readonlyRuntime[fieldName] === true;
    }

    isRequired(fieldName, config, field) {
      return this.requiredRuntime[fieldName] === true || config.required === true || field.nullable === false || field.validation && field.validation.required === true;
    }

    hasOptions(field) {
      return global.CrudUtils.ensureArray(field.options).length > 0 || ["enum", "option", "dropdown"].indexOf(String(field.type || "")) !== -1;
    }

    getInputId(fieldName) {
      const formId = this.definition.form && this.definition.form.id || "crud-lite-form";
      return formId + "-" + fieldName;
    }

    getTitle() {
      const title = this.definition.form && this.definition.form.title || {};
      if (title[this.mode]) {
        return title[this.mode];
      }
      const labels = {
        create: "Novo registro",
        edit: "Editar registro",
        delete: "Excluir registro",
        view: "Visualizar registro"
      };
      return labels[this.mode] || "Registro";
    }
  }

  global.CrudLiteFormRenderer = CrudLiteFormRenderer;
})(window);
