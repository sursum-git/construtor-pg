(function(global) {
  "use strict";

  const CrudUtils = {
    literalCatalogs: {
      "pt-BR": {
        "literal.button.continue": "Continuar",
        "literal.button.cancel": "Cancelar",
        "literal.button.close": "Fechar",
        "literal.button.confirm": "Confirmar",
        "literal.button.copy": "Copiar",
        "literal.button.understood": "Entendi",
        "literal.technical_properties.copy_all": "Copiar propriedades",
        "literal.technical_properties.copy_json": "Copiar JSON",
        "literal.technical_properties.download_json": "Baixar JSON",
        "literal.technical_properties.open_link": "Abrir",
        "literal.title.confirm": "Confirmar",
        "literal.title.warning": "Aviso",
        "literal.title.technical_properties": "Propriedades tecnicas",
        "literal.technical_properties.button": "Ver propriedades tecnicas",
        "literal.technical_properties.copy": "Copiar valor",
        "literal.technical_properties.filter_placeholder": "Filtrar propriedades",
        "literal.technical_properties.copied": "Valor copiado.",
        "literal.technical_properties.copied_all": "Propriedades copiadas.",
        "literal.technical_properties.empty": "Nenhuma propriedade encontrada para o filtro informado.",
        "literal.technical_properties.section.general": "Geral",
        "validation.title.consistency_warning": "Aviso de consistencia",
        "validation.title.inconsistencies": "Inconsistencias encontradas",
        "validation.title.invalid_situation": "Situacao invalida",
        "validation.title.situation_not_allowed": "Situacao nao permitida",
        "validation.title.situation_blocked": "Mudanca de situacao bloqueada",
        "validation.message.confirm_default": "Deseja continuar?",
        "validation.message.form_inconsistencies": "Existem inconsistencias no formulario.",
        "validation.message.no_allowed_fields": "Nenhum campo permitido foi informado.",
        "validation.message.field_required": "{fieldLabel} e obrigatorio.",
        "validation.message.field_min_length": "{fieldLabel} precisa ter ao menos {min} caracteres.",
        "validation.message.field_required_for_situation": "{fieldLabel} e obrigatorio para esta mudanca de situacao.",
        "validation.message.inactive_customer_note_required": "Informe uma observacao ao inativar o cliente.",
        "validation.message.json_invalid": "{fieldLabel} precisa conter um JSON valido.",
        "validation.message.situation_not_registered": "A situacao informada nao esta cadastrada para esta entidade.",
        "validation.message.situation_transition_not_allowed": "Nao e permitido mudar a situacao de {from} para {to} nesta acao.",
        "validation.message.situation_transition_blocked": "Transicao de situacao nao permitida.",
        "validation.message.situation_rules_pending": "Existem regras pendentes para mudar a situacao.",
        "validation.message.version_reference_not_found": "Nao foi encontrada uma versao historica valida para {fieldLabel}.",
        "runtime.message.operation_blocked": "Operacao bloqueada."
      }
    },
    runtimeLiteralCatalogs: {},
    literalLoadCache: {},

    clone(value) {
      return value == null ? value : JSON.parse(JSON.stringify(value));
    },

    ensureArray(value) {
      return Array.isArray(value) ? value : [];
    },

    getByPath(value, path) {
      if (!path) {
        return value;
      }

      return String(path).split(".").reduce((current, part) => {
        if (current == null) {
          return undefined;
        }
        return current[part];
      }, value);
    },

    replaceUrlParams(url, params) {
      return String(url || "").replace(/\{([^}]+)\}/g, function(_, key) {
        return encodeURIComponent(params && params[key] != null ? params[key] : "");
      });
    },

    isRelativeUrl(url) {
      return typeof url === "string" && /^(\/|\.\/|\.\.\/|[A-Za-z0-9_-])/.test(url) && !/^[a-z][a-z0-9+.-]*:\/\//i.test(url);
    },

    isHttpUrl(url) {
      return typeof url === "string" && /^https?:\/\//i.test(url.trim());
    },

    isAllowedDocumentUrl(url) {
      if (typeof url !== "string" || !url.trim()) {
        return false;
      }
      const value = url.trim();
      return this.isHttpUrl(value) || (/^(\/|\.\/|\.\.\/|[A-Za-z0-9_-])/.test(value) && !/^[a-z][a-z0-9+.-]*:/i.test(value));
    },

    escapeHtml(value) {
      return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },

    getCurrentLocale() {
      if (global.kendo && typeof global.kendo.culture === "function") {
        const culture = global.kendo.culture();
        if (culture && culture.name) {
          return culture.name;
        }
      }
      const documentLanguage = global.document && global.document.documentElement && global.document.documentElement.lang;
      return documentLanguage || "pt-BR";
    },

    getLiteralCatalog(locale) {
      const current = String(locale || this.getCurrentLocale() || "pt-BR");
      return Object.assign(
        {},
        this.literalCatalogs["pt-BR"] || {},
        this.literalCatalogs[current] || {},
        this.runtimeLiteralCatalogs["pt-BR"] || {},
        this.runtimeLiteralCatalogs[current] || {}
      );
    },

    setRuntimeLiterals(locale, literals) {
      const current = String(locale || this.getCurrentLocale() || "pt-BR");
      const catalog = literals && typeof literals === "object" ? literals : {};
      this.runtimeLiteralCatalogs[current] = Object.assign({}, this.runtimeLiteralCatalogs[current] || {}, catalog);
      return this.runtimeLiteralCatalogs[current];
    },

    loadLiteralBundle(settings, httpClient) {
      const config = settings && typeof settings === "object" ? settings : {};
      if (config.enabled !== true) {
        return Promise.resolve(null);
      }

      const locale = String(config.locale || this.getCurrentLocale() || "pt-BR").trim() || "pt-BR";
      if (this.literalLoadCache[locale]) {
        return this.literalLoadCache[locale];
      }

      const endpoint = config.endpoint && typeof config.endpoint === "object" ? config.endpoint : {};
      const url = this.replaceUrlParams(endpoint.url || "", { locale: locale });
      if (!url) {
        return Promise.resolve(null);
      }

      const client = httpClient || (global.CrudHttpClient ? new global.CrudHttpClient() : null);
      if (!client || typeof client.request !== "function") {
        return Promise.resolve(null);
      }

      this.literalLoadCache[locale] = client.request({
        url: url,
        method: endpoint.method || "GET"
      }).then((payload) => {
        const literals = payload && payload.literals && typeof payload.literals === "object" ? payload.literals : {};
        this.setRuntimeLiterals(locale, literals);
        return payload || null;
      }).catch((error) => {
        delete this.literalLoadCache[locale];
        if (global.console && typeof global.console.warn === "function") {
          global.console.warn("Falha ao carregar literais runtime.", error);
        }
        return null;
      });

      return this.literalLoadCache[locale];
    },

    formatLiteral(template, params) {
      if (typeof template !== "string" || template === "") {
        return "";
      }
      const values = params && typeof params === "object" ? params : {};
      return template.replace(/\{([^}]+)\}/g, function(match, key) {
        return values[key] != null ? String(values[key]) : "";
      });
    },

    resolveLiteral(key, params, fallback) {
      const template = key ? this.getLiteralCatalog()[key] : null;
      if (typeof template === "string" && template !== "") {
        return this.formatLiteral(template, params);
      }
      if (typeof fallback === "string" && fallback !== "") {
        return this.formatLiteral(fallback, params);
      }
      return typeof key === "string" && key !== "" ? key : "";
    },

    resolveValidationMessage(item, fallbackType) {
      const source = item && typeof item === "object" ? item : { message: item };
      const params = source.messageParams && typeof source.messageParams === "object" ? source.messageParams : {};
      const message = this.resolveLiteral(source.messageKey, params, source.message || "");
      return Object.assign({
        type: fallbackType || "error",
        message
      }, source, {
        message
      });
    },

    toKendoFormat(format, type) {
      if (format === "currency") {
        return "{0:c2}";
      }
      if (format === "date" || type === "date") {
        return "{0:dd/MM/yyyy}";
      }
      if (format === "datetime" || type === "datetime") {
        return "{0:dd/MM/yyyy HH:mm}";
      }
      if (format === "number" || type === "number" || type === "decimal") {
        return "{0:n2}";
      }
      return undefined;
    },

    normalizeDateValue(value) {
      if (!value) {
        return value;
      }
      if (value instanceof Date) {
        return value;
      }
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date;
    },

    readLocalJson(url) {
      return new Promise(function(resolve, reject) {
        const xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.overrideMimeType("application/json");
        xhr.onload = function() {
          const ok = xhr.status === 0 || (xhr.status >= 200 && xhr.status < 300);
          if (!ok) {
            reject(new Error("Falha ao carregar " + url + "."));
            return;
          }
          try {
            resolve(JSON.parse(xhr.responseText));
          } catch (error) {
            reject(error);
          }
        };
        xhr.onerror = function() {
          reject(new Error("Falha ao carregar " + url + "."));
        };
        xhr.send();
      });
    },

    readLocalValue(key, fallbackValue) {
      try {
        if (!global.localStorage) {
          return fallbackValue == null ? "" : fallbackValue;
        }
        const value = global.localStorage.getItem(String(key || ""));
        return value == null ? (fallbackValue == null ? "" : fallbackValue) : value;
      } catch (_) {
        return fallbackValue == null ? "" : fallbackValue;
      }
    },

    saveLocalValue(key, value) {
      try {
        if (!global.localStorage) {
          return false;
        }
        global.localStorage.setItem(String(key || ""), String(value == null ? "" : value));
      } catch (_) {
        return false;
      }
      return true;
    },

    normalizeLocalStateEnvelope(value, options) {
      const settings = options && typeof options === "object" ? options : {};
      return {
        version: Number(settings.version || 1),
        updatedAt: settings.updatedAt || new Date().toISOString(),
        data: value == null ? null : value
      };
    },

    readLocalStateValue(key, fallbackValue, options) {
      const settings = options && typeof options === "object" ? options : {};
      const raw = this.readLocalJsonValue(key, null);
      if (raw == null) {
        return fallbackValue == null ? null : fallbackValue;
      }
      if (raw && typeof raw === "object" && Object.prototype.hasOwnProperty.call(raw, "data") && Object.prototype.hasOwnProperty.call(raw, "version")) {
        if (settings.version && Number(raw.version || 0) > Number(settings.version || 0)) {
          return fallbackValue == null ? null : fallbackValue;
        }
        return raw.data == null ? (fallbackValue == null ? null : fallbackValue) : raw.data;
      }
      return raw;
    },

    saveLocalStateValue(key, value, options) {
      return this.saveLocalJsonValue(key, this.normalizeLocalStateEnvelope(value, options));
    },

    removeLocalValue(key) {
      try {
        if (!global.localStorage) {
          return false;
        }
        global.localStorage.removeItem(String(key || ""));
      } catch (_) {
        return false;
      }
      return true;
    },

    readLocalJsonValue(key, fallbackValue) {
      const raw = this.readLocalValue(key, "");
      if (!raw) {
        return fallbackValue == null ? null : fallbackValue;
      }
      try {
        return JSON.parse(raw);
      } catch (_) {
        return fallbackValue == null ? null : fallbackValue;
      }
    },

    saveLocalJsonValue(key, value) {
      try {
        return this.saveLocalValue(key, JSON.stringify(value == null ? null : value));
      } catch (_) {
        return false;
      }
    },

    clearLocalKeys(keys) {
      this.ensureArray(keys).forEach((key) => this.removeLocalValue(key));
      return true;
    },

    clearLocalKeysByPrefix(prefixes) {
      try {
        if (!global.localStorage) {
          return false;
        }
        const list = this.ensureArray(prefixes).map(function(item) {
          return String(item || "");
        }).filter(Boolean);
        if (!list.length) {
          return true;
        }
        const keysToRemove = [];
        for (let index = 0; index < global.localStorage.length; index += 1) {
          const key = String(global.localStorage.key(index) || "");
          if (list.some(function(prefix) { return key.indexOf(prefix) === 0; })) {
            keysToRemove.push(key);
          }
        }
        keysToRemove.forEach((key) => global.localStorage.removeItem(key));
      } catch (_) {
        return false;
      }
      return true;
    },

    getRuntimeSessionStoragePrefixes(extraPrefixes) {
      return [
        "homeEngine.",
        "importExportAdmin.",
        "program-governance-audit-filters:"
      ].concat(this.ensureArray(extraPrefixes));
    },

    clearRuntimeSessionContext(options) {
      const settings = options && typeof options === "object" ? options : {};
      const preserveLastUsername = settings.preserveLastUsername === true;
      const clearRememberToken = settings.clearRememberToken === true;
      const keys = [
        "crudEngine.authToken",
        "crudEngine.runtimeTenantId",
        "crudEngine.runtimeSessionId",
        "crudEngine.currentSubscriber",
        "crudEngine.availableSubscribers",
        "crudEngine.runtimeUserId",
        "crudEngine.runtimeUserName",
        "crudEngine.runtimeUserGroups",
        "crudEngine.runtimeUserPermissions",
        "crudEngine.accessArea"
      ];
      if (clearRememberToken) {
        keys.push("crudEngine.rememberToken");
        keys.push("crudEngine.rememberTokenExpiresAt");
      }
      if (!preserveLastUsername) {
        keys.push("crudEngine.lastUsername");
      }
      this.clearLocalKeys(keys);
      this.clearLocalKeysByPrefix(this.getRuntimeSessionStoragePrefixes(settings.extraPrefixes));
      return true;
    },

    buildExportFileName(prefix, extension, context) {
      const safePrefix = String(prefix || "export")
        .trim()
        .replace(/[^A-Za-z0-9._-]+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "") || "export";
      const extra = this.ensureArray(context).map(function(item) {
        return String(item || "")
          .trim()
          .replace(/[^A-Za-z0-9._-]+/g, "-")
          .replace(/-+/g, "-")
          .replace(/^-|-$/g, "");
      }).filter(Boolean);
      const stamp = new Date().toISOString().replace(/[:]/g, "-").replace(/\.\d{3}Z$/, "Z");
      return [safePrefix].concat(extra).concat(stamp).join("-") + "." + String(extension || "txt").replace(/^\./, "");
    },

    makeError(code, message, details) {
      const error = new Error(message);
      error.payload = { error: { code, message, details: details || {} } };
      return error;
    },

    unwrapError(error, fallbackMessage) {
      if (error && error.payload && error.payload.error) {
        return error.payload.error;
      }
      if (error && error.error) {
        return error.error;
      }
      return {
        code: "UNEXPECTED_ERROR",
        message: error && error.message ? error.message : fallbackMessage,
        details: {}
      };
    },

    normalizeBackendValidation(payload, fallbackMessage) {
      const source = payload && payload.payload ? payload.payload : payload || {};
      const error = this.unwrapError(source, fallbackMessage || "Existem inconsistencias no formulario.");
      const details = error && error.details || {};
      const validation = source.validation || error.validation || details.validation || null;
      const effects = this.ensureArray(source.effects || error.effects || details.effects);
      const rootStatus = validation && validation.status || error.severity || "error";
      const messages = this.ensureArray(validation && validation.messages).map((item) => {
        if (typeof item === "string") {
          return { message: item, type: "error" };
        }
        return this.resolveValidationMessage(Object.assign({
          type: item && item.type || rootStatus
        }, item || {}), rootStatus);
      });
      const title = validation
        ? this.resolveLiteral(validation.titleKey, validation.titleParams, validation.title || error.message || "")
        : this.resolveLiteral("validation.message.form_inconsistencies", null, error.message || "");

      if (!validation && !effects.length) {
        return {
          hasValidation: false,
          error,
          effects: []
        };
      }

      return {
        hasValidation: true,
        error,
        effects,
        validation: Object.assign({
          status: error.severity || "error",
          title,
          messages
        }, validation || {}, {
          title,
          messages
        })
      };
    },

    showBackendValidation(validation, options) {
      const settings = options || {};
      const normalized = validation || {};
      const messages = this.ensureArray(normalized.messages);
      const status = normalized.status || "blocked";
      const requiresConfirmation = normalized.requiresConfirmation === true;
      const $ = global.jQuery || global.$;
      const title = normalized.title || this.resolveLiteral(
        requiresConfirmation ? "validation.title.consistency_warning" : "validation.title.inconsistencies",
        null,
        requiresConfirmation ? "Aviso de consistencia" : "Inconsistencias encontradas"
      );

      if (!$ || !global.kendo || !$.fn || !$.fn.kendoWindow) {
        const text = messages.map(function(item) { return item && item.message || ""; }).filter(Boolean).join("\n") || title;
        this.showMessage(text, status === "warning" ? "warning" : "error");
        return Promise.resolve(false);
      }

      return new Promise((resolve) => {
        const wrapper = $("<div></div>").appendTo(global.document.body);
        const content = $("<div class=\"crud-validation-dialog\"></div>").appendTo(wrapper);
        if (normalized.message && !messages.length) {
          $("<p></p>").text(normalized.message).appendTo(content);
        }
        if (messages.length) {
          const list = $("<ul class=\"crud-validation-list\"></ul>").appendTo(content);
          messages.forEach(function(item) {
            const li = $("<li></li>").appendTo(list);
            const text = item && item.message ? item.message : String(item || "");
            if (item && item.field) {
              $("<strong></strong>").text(item.field + ": ").appendTo(li);
            }
            $("<span></span>").text(text).appendTo(li);
          });
        }
        const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
        const confirmButton = requiresConfirmation
          ? $("<button type=\"button\"></button>").text(
            this.resolveLiteral(
              normalized.confirmTextKey || "literal.button.continue",
              normalized.confirmTextParams,
              normalized.confirmText || "Continuar"
            )
          ).appendTo(actions)
          : null;
        const closeButton = $("<button type=\"button\"></button>")
          .text(requiresConfirmation
            ? this.resolveLiteral(
              normalized.cancelTextKey || "literal.button.cancel",
              normalized.cancelTextParams,
              normalized.cancelText || "Cancelar"
            )
            : this.resolveLiteral(
              settings.closeTextKey || "literal.button.close",
              settings.closeTextParams,
              settings.closeText || "Fechar"
            ))
          .appendTo(actions);

        const windowElement = wrapper.kendoWindow({
          title,
          modal: true,
          visible: false,
          width: settings.width || 520,
          resizable: false,
          close: function() {
            windowElement.destroy();
            wrapper.remove();
          }
        }).data("kendoWindow");

        const finish = function(value) {
          resolve(value);
          windowElement.close();
        };

        if (confirmButton) {
          confirmButton.kendoButton({
            icon: settings.confirmIcon || "check",
            themeColor: settings.themeColor || "warning",
            click: function() { finish(true); }
          });
        }
        closeButton.kendoButton({
          icon: requiresConfirmation ? "cancel" : "check",
          click: function() { finish(false); }
        });
        windowElement.center().open();
      });
    },

    showMessage(message, type) {
      const text = String(message == null ? "" : message);
      if (!text) {
        return null;
      }

      const $ = global.jQuery || global.$;
      const messageType = ["success", "error", "info", "warning"].indexOf(type) === -1 ? "info" : type;
      if (!$ || !global.kendo || !$.fn || !$.fn.kendoNotification) {
        if (global.console && typeof global.console[messageType === "error" ? "error" : "log"] === "function") {
          global.console[messageType === "error" ? "error" : "log"](text);
        }
        return null;
      }

      if (!this._notificationElement || !this._notificationElement.closest("body").length) {
        this._notificationElement = $("<span></span>").appendTo(global.document.body);
        this._notificationElement.kendoNotification({
          autoHideAfter: 3000,
          stacking: "down",
          position: {
            pinned: true,
            top: 16,
            right: 16
          },
          show: function(event) {
            if (event && event.element) {
              event.element.addClass("crud-kendo-notification");
            }
          },
          templates: [
            {
              type: "success",
              template: "<div class=\"crud-toast crud-toast-success\">#: message #</div>"
            },
            {
              type: "error",
              template: "<div class=\"crud-toast crud-toast-error\">#: message #</div>"
            },
            {
              type: "info",
              template: "<div class=\"crud-toast crud-toast-info\">#: message #</div>"
            },
            {
              type: "warning",
              template: "<div class=\"crud-toast crud-toast-warning\">#: message #</div>"
            }
          ]
        });
      }

      const widget = this._notificationElement.data("kendoNotification");
      if (widget) {
        if (typeof widget.hide === "function") {
          widget.hide();
        }
        widget.show({ message: text }, messageType);
      }
      return widget || null;
    },

    confirm(message, options) {
      const settings = options || {};
      const $ = global.jQuery || global.$;
      if (!$ || !global.kendo || !$.fn || !$.fn.kendoWindow) {
        if (global.console && typeof global.console.warn === "function") {
          global.console.warn(String(message || ""));
        }
        return Promise.resolve(false);
      }

      const wrapper = $("<div></div>").appendTo(global.document.body);
      const content = $("<div class=\"crud-confirm-content\"></div>").appendTo(wrapper);
      $("<p></p>").text(this.resolveLiteral(
        settings.messageKey || "validation.message.confirm_default",
        settings.messageParams,
        message || "Deseja continuar?"
      )).appendTo(content);
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const confirmButton = $("<button type=\"button\"></button>")
        .text(this.resolveLiteral(
          settings.confirmTextKey || "literal.button.confirm",
          settings.confirmTextParams,
          settings.confirmText || "Confirmar"
        ))
        .appendTo(actions);
      const cancelButton = $("<button type=\"button\"></button>")
        .text(this.resolveLiteral(
          settings.cancelTextKey || "literal.button.cancel",
          settings.cancelTextParams,
          settings.cancelText || "Cancelar"
        ))
        .appendTo(actions);
      let resolved = false;

      confirmButton.kendoButton({
        themeColor: settings.themeColor || "primary",
        icon: settings.confirmIcon || "check"
      });
      cancelButton.kendoButton();

      return new Promise((resolve) => {
        wrapper.kendoWindow({
          title: this.resolveLiteral(settings.titleKey || "literal.title.confirm", settings.titleParams, settings.title || "Confirmar"),
          modal: true,
          width: Math.min(settings.width || 420, Math.max(300, global.innerWidth - 24)),
          visible: false,
          close: function() {
            if (!resolved) {
              resolved = true;
              resolve(false);
            }
            wrapper.data("kendoWindow").destroy();
            wrapper.remove();
          }
        });

        const windowWidget = wrapper.data("kendoWindow");
        confirmButton.on("click", function() {
          resolved = true;
          resolve(true);
          windowWidget.close();
        });
        cancelButton.on("click", function() {
          resolved = true;
          resolve(false);
          windowWidget.close();
        });
        windowWidget.center().open();
      });
    },

    blockingMessage(title, message, options) {
      const settings = options || {};
      const $ = global.jQuery || global.$;
      if (!$ || !global.kendo || !$.fn || !$.fn.kendoWindow) {
        this.showMessage(message || title, settings.type || "error");
        return null;
      }

      const wrapper = $("<div></div>").appendTo(global.document.body);
      const content = $("<div class=\"crud-confirm-content crud-blocking-message\"></div>").appendTo(wrapper);
      $("<p></p>").text(this.resolveLiteral(
        settings.messageKey || "runtime.message.operation_blocked",
        settings.messageParams,
        message || title || "Operacao bloqueada."
      )).appendTo(content);
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const button = $("<button type=\"button\"></button>")
        .text(this.resolveLiteral(
          settings.buttonTextKey || "literal.button.understood",
          settings.buttonTextParams,
          settings.buttonText || "Entendi"
        ))
        .appendTo(actions);
      button.kendoButton({ themeColor: settings.themeColor || "primary", icon: settings.icon || "lock" });

      wrapper.kendoWindow({
        title: this.resolveLiteral(settings.titleKey || "literal.title.warning", settings.titleParams, title || "Aviso"),
        modal: true,
        actions: [],
        width: Math.min(settings.width || 460, Math.max(300, global.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      button.on("click", function() {
        if (settings.closeOnButton === true) {
          windowWidget.close();
        }
      });
      windowWidget.center().open();
      return windowWidget;
    },

    buildFieldLabel(definition, fieldName) {
      const field = definition.dataModel && definition.dataModel.fields
        ? definition.dataModel.fields[fieldName]
        : null;
      return field && field.label ? field.label : fieldName;
    },

    normalizeTechnicalProperties(value) {
      const items = [];
      const generalSection = this.resolveLiteral("literal.technical_properties.section.general", null, "Geral");
      const normalizeAction = (action) => {
        if (!action || typeof action !== "object" || Array.isArray(action)) {
          return null;
        }
        const type = String(action.type || "").trim();
        if (!type) {
          return null;
        }
        if (type === "openUrl") {
          const url = String(action.url || "").trim();
          if (!this.isAllowedDocumentUrl(url)) {
            return null;
          }
          return {
            type,
            url,
            label: String(action.label || "").trim()
          };
        }
        if (type === "openProgram" || type === "openScreen") {
          const value = String(action.programId || action.screenId || action.value || "").trim();
          if (!value) {
            return null;
          }
          return {
            type,
            programId: String(action.programId || "").trim(),
            screenId: String(action.screenId || "").trim(),
            value,
            label: String(action.label || "").trim()
          };
        }
        return null;
      };
      if (Array.isArray(value)) {
        value.forEach((item) => {
          if (item == null) {
            return;
          }
          if (typeof item === "string" || typeof item === "number" || typeof item === "boolean") {
            items.push({
              section: generalSection,
              label: "Valor",
              value: String(item),
              critical: false
            });
            return;
          }
          if (typeof item !== "object") {
            return;
          }
          const sharedParams = item.params && typeof item.params === "object" ? item.params : {};
          const label = String(
            this.resolveLiteral(item.labelKey, Object.assign({}, sharedParams, item.labelParams || {}), item.label || item.name || item.key || item.id || "")
          ).trim();
          const rawValue = item.value != null ? item.value : item.text != null ? item.text : item.description;
          const propertyValue = item.valueKey
            ? this.resolveLiteral(item.valueKey, Object.assign({}, sharedParams, item.valueParams || {}), rawValue == null ? "" : String(rawValue))
            : rawValue;
          if (!label || propertyValue == null || propertyValue === "") {
            return;
          }
          items.push({
            section: String(this.resolveLiteral(item.sectionKey, sharedParams, item.section || item.group || item.category || generalSection)),
            label,
            value: String(propertyValue),
            critical: item.critical === true,
            action: normalizeAction(item.action)
          });
        });
        return items;
      }

      if (!value || typeof value !== "object") {
        return items;
      }

      Object.keys(value).forEach((key) => {
        const propertyValue = value[key];
        if (propertyValue == null || propertyValue === "") {
          return;
        }
        items.push({
          section: generalSection,
          label: String(key),
          value: typeof propertyValue === "string" ? propertyValue : JSON.stringify(propertyValue),
          critical: false,
          action: null
        });
      });

      return items;
    },

    resolveTechnicalProperties(source, fallbackDefinition, fallbackFieldName) {
      const direct = this.normalizeTechnicalProperties(source);
      if (direct.length) {
        return direct;
      }
      if (!fallbackDefinition || !fallbackFieldName) {
        return [];
      }
      const field = fallbackDefinition && fallbackDefinition.dataModel && fallbackDefinition.dataModel.fields
        ? fallbackDefinition.dataModel.fields[fallbackFieldName]
        : null;
      return this.normalizeTechnicalProperties(field && field.technicalProperties);
    },

    getFieldTechnicalProperties(definition, fieldName) {
      return this.resolveTechnicalProperties(null, definition, fieldName);
    },

    hasTechnicalProperties(source, fallbackDefinition, fallbackFieldName) {
      return this.resolveTechnicalProperties(source, fallbackDefinition, fallbackFieldName).length > 0;
    },

    hasFieldTechnicalProperties(definition, fieldName) {
      return this.getFieldTechnicalProperties(definition, fieldName).length > 0;
    },

    groupTechnicalProperties(properties) {
      const groups = [];
      const seen = {};
      this.normalizeTechnicalProperties(properties).forEach((item) => {
        const section = String(item.section || this.resolveLiteral("literal.technical_properties.section.general", null, "Geral")).trim() || "Geral";
        if (!seen[section]) {
          seen[section] = {
            title: section,
            items: []
          };
          groups.push(seen[section]);
        }
        seen[section].items.push(item);
      });
      groups.forEach((group) => {
        group.items.sort((left, right) => {
          const leftCritical = this.isCriticalTechnicalProperty(left) ? 1 : 0;
          const rightCritical = this.isCriticalTechnicalProperty(right) ? 1 : 0;
          if (leftCritical !== rightCritical) {
            return rightCritical - leftCritical;
          }
          return String(left.label || "").localeCompare(String(right.label || ""), "pt-BR");
        });
      });
      return groups.sort((left, right) => {
        return String(left.title || "").localeCompare(String(right.title || ""), "pt-BR");
      });
    },

    isCriticalTechnicalProperty(item) {
      if (!item || typeof item !== "object") {
        return false;
      }
      if (item.critical === true) {
        return true;
      }
      const signature = (String(item.section || "") + " " + String(item.label || "")).toLowerCase();
      return [
        "chave primaria",
        "somente leitura",
        "valor unico",
        "json path",
        "modelo odoo",
        "fk ",
        "obrigatorio"
      ].some(function(token) {
        return signature.indexOf(token) >= 0;
      });
    },

    buildTechnicalPropertiesCopyText(properties) {
      const groups = this.groupTechnicalProperties(properties);
      return groups.map((group) => {
        const header = "[" + group.title + "]";
        const rows = group.items.map(function(item) {
          return item.label + ": " + item.value;
        });
        return [header].concat(rows).join("\n");
      }).join("\n\n");
    },

    buildTechnicalPropertiesHtml(title, properties) {
      const groups = this.groupTechnicalProperties(properties);
      const heading = String(title || "Campo").trim() || "Campo";
      if (!groups.length) {
        return "";
      }

      const toolbar = "<div class=\"crud-technical-info-toolbar\">" +
        "<input type=\"text\" class=\"crud-technical-filter-input\" placeholder=\"" + this.escapeHtml(this.resolveLiteral("literal.technical_properties.filter_placeholder", null, "Filtrar propriedades")) + "\">" +
        "<button type=\"button\" class=\"crud-technical-copy-all-button\">" + this.escapeHtml(this.resolveLiteral("literal.technical_properties.copy_all", null, "Copiar propriedades")) + "</button>" +
        "<button type=\"button\" class=\"crud-technical-copy-json-button\">" + this.escapeHtml(this.resolveLiteral("literal.technical_properties.copy_json", null, "Copiar JSON")) + "</button>" +
        "<button type=\"button\" class=\"crud-technical-download-json-button\">" + this.escapeHtml(this.resolveLiteral("literal.technical_properties.download_json", null, "Baixar JSON")) + "</button>" +
      "</div>";

      const sectionsHtml = groups.map((group, groupIndex) => {
        const rows = group.items.map((item, itemIndex) => {
          const criticalClass = this.isCriticalTechnicalProperty(item) ? " is-critical" : "";
          const actionButton = item.action
            ? "<button type=\"button\" class=\"crud-technical-action-button\" data-action-id=\"" + this.escapeHtml(String(groupIndex) + "-" + String(itemIndex)) + "\">" + this.escapeHtml(item.action.label || this.resolveLiteral("literal.technical_properties.open_link", null, "Abrir")) + "</button>"
            : "";
          return "<div class=\"crud-technical-info-item\">" +
            "<dt class=\"crud-technical-info-label" + criticalClass + "\">" + this.escapeHtml(item.label) + "</dt>" +
            "<dd>" +
              "<span class=\"crud-technical-info-value\">" + this.escapeHtml(item.value) + "</span>" +
              actionButton +
              "<button type=\"button\" class=\"crud-technical-copy-button\" data-copy-value=\"" + this.escapeHtml(item.value) + "\" data-copy-id=\"" + this.escapeHtml(String(groupIndex) + "-" + String(itemIndex)) + "\">" + this.escapeHtml(this.resolveLiteral("literal.button.copy", null, "Copiar")) + "</button>" +
            "</dd>" +
          "</div>";
        }).join("");

        return "<section class=\"crud-technical-info-section\">" +
          "<h4 class=\"crud-technical-info-section-title\">" + this.escapeHtml(group.title) + "</h4>" +
          "<dl class=\"crud-technical-info-list\">" + rows + "</dl>" +
        "</section>";
      }).join("");

      return "<section class=\"crud-technical-info-dialog\">" +
        "<p class=\"crud-technical-info-title\">" + this.escapeHtml(heading) + "</p>" +
        toolbar +
        sectionsHtml +
        "<p class=\"crud-technical-info-empty\" hidden>" + this.escapeHtml(this.resolveLiteral("literal.technical_properties.empty", null, "Nenhuma propriedade encontrada para o filtro informado.")) + "</p>" +
      "</section>";
    },

    openTechnicalPropertiesDialog(title, properties, options) {
      const html = this.buildTechnicalPropertiesHtml(title, properties);
      if (!html || !global.$ || !global.kendo) {
        return null;
      }
      const settings = options || {};
      const normalizedProperties = this.normalizeTechnicalProperties(properties);

      const wrapper = global.$("<div class=\"crud-technical-info-window\"></div>").appendTo(document.body);
      wrapper.html(html);
      wrapper.kendoWindow({
        title: this.resolveLiteral("literal.title.technical_properties", null, "Propriedades tecnicas"),
        modal: true,
        actions: ["Close"],
        resizable: true,
        width: Math.min(460, Math.max(320, global.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      wrapper.on("click", ".crud-technical-copy-button", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const value = global.$(event.currentTarget).attr("data-copy-value") || "";
        this.copyText(value).then(() => {
          this.showMessage(this.resolveLiteral("literal.technical_properties.copied", null, "Valor copiado."), "success");
        }).catch(() => {
          this.showMessage(value, "info");
        });
      });
      wrapper.on("click", ".crud-technical-copy-all-button", (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.copyText(this.buildTechnicalPropertiesCopyText(properties)).then(() => {
          this.showMessage(this.resolveLiteral("literal.technical_properties.copied_all", null, "Propriedades copiadas."), "success");
        }).catch(() => {
          this.showMessage(this.resolveLiteral("literal.technical_properties.copy_all", null, "Copiar propriedades"), "info");
        });
      });
      wrapper.on("click", ".crud-technical-copy-json-button", (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.copyText(JSON.stringify(normalizedProperties, null, 2)).then(() => {
          this.showMessage(this.resolveLiteral("literal.technical_properties.copied_all", null, "Propriedades copiadas."), "success");
        }).catch(() => {
          this.showMessage(this.resolveLiteral("literal.technical_properties.copy_json", null, "Copiar JSON"), "info");
        });
      });
      wrapper.on("click", ".crud-technical-download-json-button", (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.downloadTextFile((String(title || "propriedades-tecnicas").trim() || "propriedades-tecnicas") + ".json", JSON.stringify(normalizedProperties, null, 2), "application/json;charset=utf-8");
      });
      wrapper.on("click", ".crud-technical-action-button", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const actionId = global.$(event.currentTarget).attr("data-action-id") || "";
        const [groupIndex, itemIndex] = actionId.split("-").map(function(part) { return Number(part); });
        const groups = this.groupTechnicalProperties(properties);
        const action = groups[groupIndex] && groups[groupIndex].items[itemIndex] ? groups[groupIndex].items[itemIndex].action : null;
        if (!action) {
          return;
        }
        this.runTechnicalPropertyAction(action, settings);
      });
      const filterInput = wrapper.find(".crud-technical-filter-input");
      if (filterInput.length) {
        if (typeof filterInput.kendoTextBox === "function") {
          filterInput.kendoTextBox();
        }
        const applyFilter = () => {
          const query = String(filterInput.val() || "").trim().toLowerCase();
          let visibleCount = 0;
          wrapper.find(".crud-technical-info-section").each(function() {
            const section = global.$(this);
            let sectionVisible = 0;
            section.find(".crud-technical-info-item").each(function() {
              const row = global.$(this);
              const haystack = row.text().toLowerCase();
              const visible = !query || haystack.indexOf(query) >= 0;
              row.toggle(visible);
              if (visible) {
                sectionVisible += 1;
                visibleCount += 1;
              }
            });
            section.toggle(sectionVisible > 0);
          });
          wrapper.find(".crud-technical-info-empty").prop("hidden", visibleCount > 0);
        };
        filterInput.on("input keyup change", applyFilter);
      }
      windowWidget.center().open();
      return windowWidget;
    },

    buildRuntimeScreenUrl(screenId) {
      const id = String(screenId || "").trim();
      if (!id) {
        return "";
      }
      try {
        const current = new URL(global.location.href);
        const path = /\/production\//.test(current.pathname) ? "app.html" : "production/app.html";
        const target = new URL(path, current.href);
        target.searchParams.set("screenId", id);
        return target.href;
      } catch (_) {
        return "production/app.html?screenId=" + encodeURIComponent(id);
      }
    },

    buildHomeProgramUrl(programId) {
      const id = String(programId || "").trim();
      if (!id) {
        return "";
      }
      try {
        const current = new URL(global.location.href);
        const path = /\/production\//.test(current.pathname) ? "home.html" : "home.html";
        const target = new URL(path, current.href);
        target.searchParams.set("initialProgramId", id);
        return target.href;
      } catch (_) {
        return "home.html?initialProgramId=" + encodeURIComponent(id);
      }
    },

    runTechnicalPropertyAction(action, options) {
      const config = options && typeof options === "object" ? options : {};
      if (!action || typeof action !== "object") {
        return;
      }
      if (typeof config.onAction === "function") {
        const handled = config.onAction(action);
        if (handled === true) {
          return;
        }
      }
      if (action.type === "openUrl" && action.url) {
        global.open(action.url, "_blank", "noopener");
        return;
      }
      if (action.type === "openScreen" && action.screenId) {
        const url = this.buildRuntimeScreenUrl(action.screenId);
        if (url) {
          global.open(url, "_blank", "noopener");
        }
        return;
      }
      if (action.type === "openProgram" && action.programId) {
        const url = this.buildHomeProgramUrl(action.programId);
        if (url) {
          global.open(url, "_blank", "noopener");
        }
      }
    },

    downloadTextFile(fileName, content, mimeType) {
      const blob = new Blob([String(content == null ? "" : content)], {
        type: mimeType || "text/plain;charset=utf-8"
      });
      const url = global.URL.createObjectURL(blob);
      const anchor = global.document.createElement("a");
      anchor.href = url;
      anchor.download = String(fileName || "arquivo.txt");
      global.document.body.appendChild(anchor);
      anchor.click();
      global.document.body.removeChild(anchor);
      global.setTimeout(function() {
        global.URL.revokeObjectURL(url);
      }, 0);
    },

    appendTechnicalInfoTrigger(container, title, properties, options) {
      if (!global.$ || !container || !this.normalizeTechnicalProperties(properties).length) {
        return null;
      }

      const settings = options || {};
      const button = global.$("<button type=\"button\" class=\"crud-technical-info-trigger\"></button>")
        .attr("title", settings.buttonTitle || this.resolveLiteral("literal.technical_properties.button", null, "Ver propriedades tecnicas"))
        .attr("aria-label", settings.buttonTitle || this.resolveLiteral("literal.technical_properties.button", null, "Ver propriedades tecnicas"))
        .appendTo(container);

      if (settings.dataRole) {
        button.attr("data-crud-role", settings.dataRole);
      }

      if (typeof button.kendoButton === "function") {
        button.kendoButton({
          icon: settings.icon || "info-circle",
          fillMode: settings.fillMode || "flat",
          size: settings.size || "small"
        });
      } else {
        button.text("i");
      }

      if (settings.cssClass) {
        button.addClass(settings.cssClass);
      }

      button.on("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        this.openTechnicalPropertiesDialog(title, properties, settings);
      });

      return button;
    },

    copyText(value) {
      const text = String(value == null ? "" : value);
      if (global.navigator && global.navigator.clipboard && typeof global.navigator.clipboard.writeText === "function") {
        return global.navigator.clipboard.writeText(text);
      }
      return new Promise((resolve, reject) => {
        try {
          const input = global.document.createElement("textarea");
          input.value = text;
          input.setAttribute("readonly", "readonly");
          input.style.position = "absolute";
          input.style.left = "-9999px";
          global.document.body.appendChild(input);
          input.select();
          const ok = global.document.execCommand("copy");
          global.document.body.removeChild(input);
          if (ok) {
            resolve(true);
            return;
          }
        } catch (error) {
          reject(error);
          return;
        }
        reject(new Error("COPY_UNSUPPORTED"));
      });
    },

    getPermission(definition, permission) {
      if (!permission) {
        return true;
      }
      return Boolean(definition.permissions && definition.permissions[permission]);
    },

    normalizeSecurityPolicy(config, options) {
      const source = Object.assign(
        {},
        config && config.security || {},
        options && options.security || {}
      );
      const mode = String(options && options.securityMode || source.mode || "").toLowerCase();
      const production = source.production === true || source.productionMode === true || mode === "production";
      const definitionSource = Object.assign({}, source.definitionSource || {});
      const endpoints = Object.assign({}, source.endpoints || {});
      const documents = Object.assign({}, source.documents || {});
      const content = Object.assign({}, source.content || {});

      return {
        mode: production ? "production" : "demo",
        production,
        definitionSource: {
          allowDirectDefinition: this.resolveSecurityBoolean(definitionSource.allowDirectDefinition, !production),
          allowDefinitionUrl: this.resolveSecurityBoolean(definitionSource.allowDefinitionUrl, !production),
          requireScreenId: this.resolveSecurityBoolean(definitionSource.requireScreenId, production),
          endpoint: definitionSource.endpoint || source.screenDefinitionEndpoint || {
            url: "/api/runtime/screens/{screenId}",
            method: "GET"
          }
        },
        endpoints: {
          allowInlineUrls: this.resolveSecurityBoolean(endpoints.allowInlineUrls, !production),
          requireEndpointIds: this.resolveSecurityBoolean(endpoints.requireEndpointIds, production),
          runtimeEndpoint: endpoints.runtimeEndpoint || source.runtimeEndpoint || {
            url: "/api/runtime/screens/{screenId}/endpoints/{endpointId}",
            method: "POST"
          },
          runtimeEventsEndpoint: endpoints.runtimeEventsEndpoint || source.runtimeEventsEndpoint || {
            url: "/api/runtime/events",
            method: "GET"
          }
        },
        documents: {
          allowInlineUrls: this.resolveSecurityBoolean(documents.allowInlineUrls, !production),
          allowExternalUrls: this.resolveSecurityBoolean(documents.allowExternalUrls, false),
          runtimeEndpoint: documents.runtimeEndpoint || {
            url: "/api/runtime/screens/{screenId}/documents/{documentId}",
            method: "GET"
          }
        },
        content: {
          allowInlineHtml: this.resolveSecurityBoolean(content.allowInlineHtml, !production)
        },
        blockedKeyPattern: source.blockedKeyPattern || "(token|password|senha|secret|apiKey|authorization|clientSecret|privateKey)"
      };
    },

    resolveSecurityBoolean(value, fallback) {
      return value == null ? fallback : Boolean(value);
    },

    isProductionSecurity(policy) {
      return Boolean(policy && policy.production);
    },

    isSafeIdentifier(value) {
      return typeof value === "string" && /^[A-Za-z0-9_.:-]+$/.test(value.trim());
    },

    buildScreenDefinitionRequest(screenId, policy, pageType) {
      const normalizedScreenId = String(screenId || "").trim();
      if (!this.isSafeIdentifier(normalizedScreenId)) {
        throw this.makeError("INVALID_SCREEN_ID", "Identificador de tela invalido.", { screenId });
      }

      const endpoint = policy && policy.definitionSource && policy.definitionSource.endpoint || {};
      if (!endpoint.url) {
        throw this.makeError("SCREEN_ENDPOINT_MISSING", "Endpoint de carregamento por screenId nao configurado.");
      }
      const method = String(endpoint.method || "GET").toUpperCase();
      return {
        url: this.replaceUrlParams(endpoint.url, { screenId: normalizedScreenId, pageType: pageType || "" }),
        method,
        data: method === "GET" ? undefined : {
          screenId: normalizedScreenId,
          pageType: pageType || ""
        }
      };
    },

    applyEndpointSecurity(definition, policy) {
      if (!definition || !definition.api || !policy) {
        return definition;
      }
      const screenId = this.getDefinitionScreenId(definition);
      Object.keys(definition.api).forEach((key) => {
        definition.api[key] = this.resolveEndpointForPolicy(definition.api[key], key, screenId, policy);
      });
      if (definition.dataSource && definition.dataSource.api) {
        definition.dataSource.api = definition.api;
      }
      return definition;
    },

    getDefinitionScreenId(definition) {
      return String(
        definition && (definition.screenId || definition.id || definition.entity) ||
        definition && definition.program && (definition.program.screenId || definition.program.id) ||
        "screen"
      ).trim();
    },

    resolveEndpointForPolicy(endpoint, fallbackEndpointId, screenId, policy) {
      const source = typeof endpoint === "string"
        ? { endpointId: endpoint }
        : Object.assign({}, endpoint || {});
      const endpointId = String(source.endpointId || source.actionId || source.id || fallbackEndpointId || "").trim();
      const inlineUrlAllowed = policy && policy.endpoints && policy.endpoints.allowInlineUrls !== false;

      if (source.url && inlineUrlAllowed) {
        return source;
      }

      if (!endpointId) {
        return source;
      }

      const runtime = policy && policy.endpoints && policy.endpoints.runtimeEndpoint || {};
      if (!runtime.url) {
        return source;
      }

      return Object.assign({}, source, {
        endpointId,
        originalMethod: source.method || "GET",
        url: this.replaceUrlParams(runtime.url, {
          screenId,
          endpointId
        }),
        method: String(source.method || runtime.method || "POST").toUpperCase(),
        runtime: {
          screenId,
          endpointId,
          operation: fallbackEndpointId || endpointId,
          originalMethod: source.method || "GET"
        }
      });
    },

    resolveDocumentUrlForPolicy(source, fallbackDocumentId, screenId, policy) {
      const config = typeof source === "string" ? { url: source } : Object.assign({}, source || {});
      if (config.url && (!policy || policy.documents.allowInlineUrls !== false)) {
        return config.url;
      }
      const documentId = String(config.documentId || config.endpointId || config.actionId || fallbackDocumentId || "").trim();
      const runtime = policy && policy.documents && policy.documents.runtimeEndpoint || {};
      if (!documentId || !runtime.url) {
        return config.url || "";
      }
      return this.replaceUrlParams(runtime.url, {
        screenId,
        documentId
      });
    }
  };

  global.CrudUtils = CrudUtils;
})(window);
