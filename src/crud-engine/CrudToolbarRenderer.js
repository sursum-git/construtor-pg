(function(global, $) {
  "use strict";

  class CrudToolbarRenderer {
    constructor(options) {
      this.definition = options.definition;
      this.handlers = options.handlers || {};
      this.buttons = {};
      this.selectionCount = 0;
    }

    render(container) {
      const toolbar = $("<section class=\"crud-toolbar\" aria-label=\"Acoes principais\"></section>");
      const mainGroup = $("<div class=\"crud-toolbar-group crud-main-actions\"></div>").appendTo(toolbar);

      global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.toolbar).forEach((item) => {
        if (!global.CrudUtils.getPermission(this.definition, item.permission)) {
          return;
        }

        this.renderButton(mainGroup, item);
      });
      this.renderBulkActions(mainGroup);
      this.renderPrintActions(mainGroup);

      $(container).append(toolbar);
      return toolbar;
    }

    renderButton(container, item) {
      const button = $("<button type=\"button\"></button>")
        .attr("id", "crud-action-" + item.id)
        .attr("title", item.label)
        .text(item.label)
        .appendTo(container);

      if (item.action === "saveLayout" || item.action === "restoreLayout") {
        button.addClass("crud-layout-button");
      }

      button.kendoButton({
        icon: item.icon || undefined,
        themeColor: item.action === "create" ? "primary" : "base",
        enable: item.action === "saveLayout" ? true : !item.enabledWhenDirty
      });

      button.on("click", () => {
        if (this.handlers[item.action]) {
          this.handlers[item.action](item);
        }
      });

      this.buttons[item.id] = button.data("kendoButton");
    }

    renderPrintActions(container) {
      const config = this.getPrintConfig();
      const options = this.getPrintOptions();
      if (!options.length) {
        return;
      }

      const wrapper = $("<span class=\"crud-print-actions\"></span>").appendTo(container);
      const label = config.label || "Imprimir";
      const button = $("<button type=\"button\" class=\"crud-print-actions-button\"></button>")
        .attr("id", "crud-action-print")
        .attr("title", label)
        .attr("aria-haspopup", "menu")
        .attr("aria-expanded", "false")
        .text(label)
        .appendTo(wrapper);
      const menu = $("<div class=\"crud-print-actions-menu\" role=\"menu\" hidden></div>").appendTo(wrapper);

      button.kendoButton({
        icon: config.icon || "print"
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
          this.closePrintActions();
          if (this.handlers.print) {
            this.handlers.print(item.format, item);
          }
        });
      });

      button.on("click", (event) => {
        event.stopPropagation();
        this.togglePrintActions();
      });

      $(document).off("click.crudPrintActions").on("click.crudPrintActions", () => {
        this.closePrintActions();
      });

      wrapper.on("click", function(event) {
        event.stopPropagation();
      });

      this.printButtonElement = button;
      this.printMenu = menu;
    }

    getPrintConfig() {
      return this.definition.grid && this.definition.grid.print
        ? this.definition.grid.print
        : null;
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

    togglePrintActions() {
      if (!this.printMenu || !this.printButtonElement) {
        return;
      }
      const isHidden = this.printMenu.prop("hidden");
      this.printMenu.prop("hidden", !isHidden);
      this.printButtonElement.attr("aria-expanded", isHidden ? "true" : "false");
    }

    closePrintActions() {
      if (this.printMenu) {
        this.printMenu.prop("hidden", true);
      }
      if (this.printButtonElement) {
        this.printButtonElement.attr("aria-expanded", "false");
      }
    }

    renderBulkActions(container) {
      const config = this.getBulkActionsConfig();
      const actions = this.getBulkActions();
      if (!actions.length) {
        return;
      }

      const wrapper = $("<span class=\"crud-bulk-actions\" hidden></span>").appendTo(container);
      const label = config.label || "Acoes";
      const button = $("<button type=\"button\" class=\"crud-bulk-actions-button\"></button>")
        .attr("id", "crud-action-bulk-actions")
        .attr("title", label)
        .attr("aria-haspopup", "menu")
        .attr("aria-expanded", "false")
        .text(label)
        .appendTo(wrapper);
      const menu = $("<div class=\"crud-bulk-actions-menu\" role=\"menu\" hidden></div>").appendTo(wrapper);

      button.kendoButton({
        icon: config.icon || "more-vertical",
        enable: false
      });

      actions.forEach((item) => {
        const actionButton = $("<button type=\"button\" class=\"crud-bulk-action\" role=\"menuitem\"></button>")
          .attr("title", item.label)
          .text(item.label)
          .appendTo(menu);
        actionButton.kendoButton({
          icon: item.icon || undefined
        });
        actionButton.on("click", () => {
          this.closeBulkActions();
          if (this.handlers.bulkAction) {
            this.handlers.bulkAction(item);
          }
        });
      });

      button.on("click", (event) => {
        event.stopPropagation();
        if (!this.selectionCount) {
          return;
        }
        this.toggleBulkActions();
      });

      $(document).off("click.crudBulkActions").on("click.crudBulkActions", () => {
        this.closeBulkActions();
      });

      wrapper.on("click", function(event) {
        event.stopPropagation();
      });

      this.bulkActionsLabel = label;
      this.bulkWrapper = wrapper;
      this.bulkButtonElement = button;
      this.bulkButton = button.data("kendoButton");
      this.bulkMenu = menu;
      this.setSelectionCount(0);
    }

    getBulkActionsConfig() {
      return this.definition.grid && this.definition.grid.bulkActions
        ? this.definition.grid.bulkActions
        : null;
    }

    getBulkActions() {
      const config = this.getBulkActionsConfig();
      if (!config || config.enabled === false || !Array.isArray(config.actions) || !config.actions.length) {
        return [];
      }
      return config.actions.filter((item) => {
        return item && item.id && item.label && item.action && global.CrudUtils.getPermission(this.definition, item.permission);
      });
    }

    toggleBulkActions() {
      if (!this.bulkMenu || !this.bulkButtonElement) {
        return;
      }
      const isHidden = this.bulkMenu.prop("hidden");
      this.bulkMenu.prop("hidden", !isHidden);
      this.bulkButtonElement.attr("aria-expanded", isHidden ? "true" : "false");
    }

    closeBulkActions() {
      if (this.bulkMenu) {
        this.bulkMenu.prop("hidden", true);
      }
      if (this.bulkButtonElement) {
        this.bulkButtonElement.attr("aria-expanded", "false");
      }
    }

    setSelectionCount(count) {
      this.selectionCount = Number(count || 0);
      if (!this.bulkButton || !this.bulkButtonElement) {
        return;
      }

      const hasSelection = this.selectionCount > 0;
      if (this.bulkWrapper) {
        this.bulkWrapper.prop("hidden", !hasSelection);
      }
      this.bulkButton.enable(hasSelection);
      const textElement = this.bulkButtonElement.find(".k-button-text");
      if (textElement.length) {
        textElement.text(this.bulkActionsLabel + " (" + this.selectionCount + ")");
      } else {
        this.bulkButtonElement.text(this.bulkActionsLabel + " (" + this.selectionCount + ")");
      }
      if (!hasSelection) {
        this.closeBulkActions();
      }
    }

    setButtonEnabled(id, enabled) {
      if (this.buttons[id]) {
        this.buttons[id].enable(Boolean(enabled));
      }
    }
  }

  global.CrudToolbarRenderer = CrudToolbarRenderer;
})(window, jQuery);
