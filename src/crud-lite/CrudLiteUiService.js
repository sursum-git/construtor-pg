(function(global, $) {
  "use strict";

  const ICON_LABELS = {
    "arrow-rotate-cw": "R",
    cancel: "X",
    check: "OK",
    "chevron-left": "<",
    "chevron-right": ">",
    "exclamation-circle": "!",
    filter: "F",
    "filter-clear": "X",
    "list-unordered": "L",
    "more-vertical": "...",
    plus: "+",
    print: "P",
    "question-circle": "?",
    save: "S",
    trash: "X",
    undo: "U"
  };

  const CrudLiteUiService = {
    active: false,

    activate() {
      this.active = true;
      this.installCompatibility();
      if (global.document && global.document.documentElement) {
        global.document.documentElement.classList.add("crud-lite-runtime");
      }
    },

    installCompatibility() {
      this.installKendoNamespace();
      if (!$ || !$.fn) {
        return;
      }
      this.installButtonPlugin();
      this.installWindowPlugin();
      this.installNotificationPlugin();
    },

    installKendoNamespace() {
      const existing = global.kendo || {};
      if (!existing.destroy) {
        existing.destroy = function() {};
      }
      if (!Object.prototype.hasOwnProperty.call(existing, "toString") || existing.toString === Object.prototype.toString) {
        existing.toString = function(value, format) {
          return CrudLiteUiService.formatValue(value, format);
        };
      }
      if (!existing.parseDate) {
        existing.parseDate = function(value) {
          if (!value) {
            return null;
          }
          const parsed = value instanceof Date ? value : new Date(value);
          return Number.isNaN(parsed.getTime()) ? null : parsed;
        };
      }
      if (!existing.saveAs) {
        existing.saveAs = function(options) {
          const link = global.document.createElement("a");
          link.href = options.dataURI;
          link.download = options.fileName || "download";
          global.document.body.appendChild(link);
          link.click();
          link.remove();
        };
      }
      global.kendo = existing;
    },

    installButtonPlugin() {
      if ($.fn.kendoButton) {
        return;
      }
      $.fn.kendoButton = function(options) {
        const settings = options || {};
        return this.each(function() {
          const element = $(this);
          element.addClass("k-button crud-lite-button");
          if (settings.themeColor === "primary") {
            element.addClass("crud-lite-button-primary");
          }
          if (settings.icon) {
            element.attr("data-lite-icon", settings.icon);
            if (!element.find(".crud-lite-button-icon").length) {
              $("<span class=\"crud-lite-button-icon\" aria-hidden=\"true\"></span>")
                .text(ICON_LABELS[settings.icon] || "")
                .prependTo(element);
            }
          }
          if (!element.find(".k-button-text").length) {
            const textNodes = element.contents().filter(function() {
              return this.nodeType === 3 && String(this.nodeValue || "").trim() !== "";
            });
            if (textNodes.length) {
              const text = textNodes.toArray().map(function(node) {
                return node.nodeValue;
              }).join("").trim();
              textNodes.remove();
              $("<span class=\"k-button-text\"></span>").text(text).appendTo(element);
            }
          }
          element.prop("disabled", settings.enable === false);
          element.data("kendoButton", {
            enable(value) {
              element.prop("disabled", value === false);
            },
            destroy() {
              element.removeData("kendoButton");
            }
          });
          if (typeof settings.click === "function") {
            element.off("click.crudLiteButton").on("click.crudLiteButton", settings.click);
          }
        });
      };
    },

    installWindowPlugin() {
      if ($.fn.kendoWindow) {
        return;
      }
      $.fn.kendoWindow = function(options) {
        const settings = options || {};
        return this.each(function() {
          const element = $(this);
          const overlay = $("<div class=\"k-overlay crud-lite-modal-overlay\" hidden></div>").appendTo(global.document.body);
          const dialog = $("<section class=\"crud-lite-dialog\" role=\"dialog\" aria-modal=\"true\"></section>").appendTo(overlay);
          const header = $("<header class=\"crud-lite-dialog-header\"></header>").appendTo(dialog);
          const title = $("<h2></h2>").text(settings.title || "").appendTo(header);
          const closeButton = $("<button type=\"button\" class=\"crud-lite-dialog-close\" aria-label=\"Fechar\">X</button>").appendTo(header);
          const body = $("<div class=\"crud-lite-dialog-body\"></div>").appendTo(dialog);
          let closing = false;

          element.show().appendTo(body);
          if (settings.width) {
            dialog.css("width", typeof settings.width === "number" ? settings.width + "px" : settings.width);
          }

          const widget = {
            wrapper: dialog,
            element,
            center() {
              return widget;
            },
            open() {
              overlay.prop("hidden", false);
              setTimeout(function() {
                const focusable = dialog.find("button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])").first();
                (focusable[0] || closeButton[0]).focus();
              }, 0);
              return widget;
            },
            close() {
              if (closing) {
                return widget;
              }
              closing = true;
              if (typeof settings.close === "function") {
                settings.close.call(element[0]);
              } else {
                widget.destroy();
              }
              return widget;
            },
            destroy() {
              overlay.remove();
              element.removeData("kendoWindow");
              return widget;
            },
            maximize() {
              dialog.addClass("crud-lite-dialog-maximized");
              if (typeof settings.maximize === "function") {
                settings.maximize.call(element[0]);
              }
              return widget;
            },
            restore() {
              dialog.removeClass("crud-lite-dialog-maximized");
              if (typeof settings.restore === "function") {
                settings.restore.call(element[0]);
              }
              return widget;
            },
            title(value) {
              if (value == null) {
                return title.text();
              }
              title.text(value);
              return widget;
            }
          };

          closeButton.on("click", function() {
            widget.close();
          });
          element.data("kendoWindow", widget);
        });
      };
    },

    installNotificationPlugin() {
      if ($.fn.kendoNotification) {
        return;
      }
      $.fn.kendoNotification = function() {
        return this.each(function() {
          $(this).data("kendoNotification", {
            hide() {},
            show(data, type) {
              CrudLiteUiService.showMessage(data && data.message || data || "", type || "info");
            },
            destroy() {
              $(this).removeData("kendoNotification");
            }
          });
        });
      };
    },

    showMessage(message, type) {
      const text = String(message == null ? "" : message);
      if (!text) {
        return null;
      }
      const kind = ["success", "error", "info", "warning"].indexOf(type) === -1 ? "info" : type;
      let region = global.document.querySelector(".crud-lite-toast-region");
      if (!region) {
        region = global.document.createElement("div");
        region.className = "crud-lite-toast-region";
        region.setAttribute("aria-live", "polite");
        global.document.body.appendChild(region);
      }
      const toast = global.document.createElement("div");
      toast.className = "crud-lite-toast crud-lite-toast-" + kind;
      toast.textContent = text;
      region.appendChild(toast);
      global.setTimeout(function() {
        toast.classList.add("crud-lite-toast-hide");
        global.setTimeout(function() {
          toast.remove();
        }, 220);
      }, 3200);
      return toast;
    },

    confirm(message, options) {
      const settings = options || {};
      return this.openChoiceDialog({
        title: settings.title || "Confirmar",
        message: message || "Deseja continuar?",
        confirmText: settings.confirmText || "Confirmar",
        cancelText: settings.cancelText || "Cancelar",
        danger: settings.themeColor === "error" || settings.confirmIcon === "trash"
      });
    },

    openChoiceDialog(options) {
      const settings = options || {};
      return new Promise(function(resolve) {
        const overlay = global.document.createElement("div");
        overlay.className = "crud-lite-modal-overlay";
        const dialog = global.document.createElement("section");
        dialog.className = "crud-lite-dialog crud-lite-choice-dialog";
        dialog.setAttribute("role", "dialog");
        dialog.setAttribute("aria-modal", "true");
        overlay.appendChild(dialog);

        const header = global.document.createElement("header");
        header.className = "crud-lite-dialog-header";
        const title = global.document.createElement("h2");
        title.textContent = settings.title || "Confirmar";
        header.appendChild(title);
        dialog.appendChild(header);

        const body = global.document.createElement("div");
        body.className = "crud-lite-dialog-body";
        const message = global.document.createElement("p");
        message.textContent = settings.message || "";
        body.appendChild(message);
        dialog.appendChild(body);

        const footer = global.document.createElement("footer");
        footer.className = "crud-lite-dialog-footer";
        const confirm = global.document.createElement("button");
        confirm.type = "button";
        confirm.className = "crud-lite-button " + (settings.danger ? "crud-lite-button-danger" : "crud-lite-button-primary");
        confirm.textContent = settings.confirmText || "Confirmar";
        const cancel = global.document.createElement("button");
        cancel.type = "button";
        cancel.className = "crud-lite-button";
        cancel.textContent = settings.cancelText || "Cancelar";
        footer.appendChild(confirm);
        footer.appendChild(cancel);
        dialog.appendChild(footer);
        global.document.body.appendChild(overlay);

        function finish(value) {
          overlay.remove();
          resolve(value);
        }
        confirm.addEventListener("click", function() { finish(true); });
        cancel.addEventListener("click", function() { finish(false); });
        confirm.focus();
      });
    },

    showBackendValidation(validation) {
      const normalized = validation || {};
      const messages = Array.isArray(normalized.messages) ? normalized.messages : [];
      const text = normalized.message || messages.map(function(item) {
        return item && item.message || String(item || "");
      }).filter(Boolean).join("\n") || "Verifique as inconsistencias informadas.";
      if (normalized.requiresConfirmation) {
        return this.openChoiceDialog({
          title: normalized.title || "Aviso de consistencia",
          message: text,
          confirmText: normalized.confirmText || "Continuar",
          cancelText: normalized.cancelText || "Cancelar"
        });
      }
      this.showMessage(text, normalized.status === "warning" ? "warning" : "error");
      return Promise.resolve(false);
    },

    formatValue(value, format) {
      if (value == null || value === "") {
        return "";
      }
      const normalizedFormat = String(format || "");
      if (value instanceof Date || /y{2,4}|d{1,2}|M{1,2}|H{1,2}|h{1,2}/.test(normalizedFormat)) {
        const date = value instanceof Date ? value : new Date(value);
        if (!Number.isNaN(date.getTime())) {
          return new Intl.DateTimeFormat("pt-BR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: /H|h/.test(normalizedFormat) ? "2-digit" : undefined,
            minute: /m/.test(normalizedFormat) ? "2-digit" : undefined
          }).format(date);
        }
      }
      if (/^c|currency/i.test(normalizedFormat)) {
        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(Number(value) || 0);
      }
      if (/^n|number/i.test(normalizedFormat)) {
        return new Intl.NumberFormat("pt-BR").format(Number(value) || 0);
      }
      return String(value);
    }
  };

  global.CrudLiteUiService = CrudLiteUiService;
  CrudLiteUiService.installCompatibility();
})(window, window.jQuery);
