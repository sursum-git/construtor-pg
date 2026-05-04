(function(global) {
  "use strict";

  const CrudUtils = {
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
      $("<p></p>").text(message || "Deseja continuar?").appendTo(content);
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const confirmButton = $("<button type=\"button\"></button>")
        .text(settings.confirmText || "Confirmar")
        .appendTo(actions);
      const cancelButton = $("<button type=\"button\"></button>")
        .text(settings.cancelText || "Cancelar")
        .appendTo(actions);
      let resolved = false;

      confirmButton.kendoButton({
        themeColor: settings.themeColor || "primary",
        icon: settings.confirmIcon || "check"
      });
      cancelButton.kendoButton();

      return new Promise(function(resolve) {
        wrapper.kendoWindow({
          title: settings.title || "Confirmar",
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
    }
  };

  global.CrudUtils = CrudUtils;
})(window);
