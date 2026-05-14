(function(global) {
  "use strict";

  const CrudUtils = {
    literalCatalogs: {
      "pt-BR": {
        "literal.button.continue": "Continuar",
        "literal.button.cancel": "Cancelar",
        "literal.button.close": "Fechar",
        "literal.button.confirm": "Confirmar",
        "literal.button.understood": "Entendi",
        "literal.title.confirm": "Confirmar",
        "literal.title.warning": "Aviso",
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

      return new Promise(function(resolve) {
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
        method: runtime.method || "POST",
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
