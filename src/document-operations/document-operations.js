(function(global) {
  "use strict";

  function DocumentOperationsPage(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#document-operations-root";
    this.items = this.buildItems();
    this.filters = {
      family: "",
      maturity: "",
      audit: ""
    };
  }

  DocumentOperationsPage.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.render();
  };

  DocumentOperationsPage.prototype.buildItems = function() {
    const catalog = global.CrudExamplesCatalog;
    const all = catalog && typeof catalog.list === "function" ? catalog.list() : [];
    return all.filter(function(item) {
      return ["reports", "special_document", "regulated_document"].indexOf(String(item.documentFamily || "")) !== -1;
    }).concat([
      {
        id: "admin-report-audit",
        category: "Relatorios",
        title: "Auditoria de relatorios",
        summary: "Consulta administrativa das emissoes auditadas da camada reports.",
        page: "examples/pages/admin-report-audit.html",
        productionUrl: "production/app.html?screenId=admin.relatorios-auditoria",
        maturity: "operacional",
        documentFamily: "reports",
        auditCapable: true
      }
    ]);
  };

  DocumentOperationsPage.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell system-updates-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Operacao documental").appendTo(title);
    global.jQuery("<p></p>").text("Visao unica das trilhas documentais com filtros por familia, maturidade e auditoria.").appendTo(title);

    this.summaryCard = this.createCard(shell, "Painel");
    this.summaryBody = this.summaryCard.body;
    const toolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(this.summaryBody);
    this.familyInput = this.createSelectField(toolbar, "Familia");
    this.maturityInput = this.createSelectField(toolbar, "Maturidade");
    this.auditInput = this.createSelectField(toolbar, "Auditoria");
    this.createButton(toolbar, "Aplicar filtros", "filter", this.applyFilters.bind(this));
    this.createButton(toolbar, "Limpar filtros", "reset", this.resetFilters.bind(this));

    this.cardsCard = this.createCard(shell, "Trilhas");
    this.cardsBody = this.cardsCard.body;
  };

  DocumentOperationsPage.prototype.render = function() {
    this.renderFilters();
    this.renderSummary();
    this.renderCards();
  };

  DocumentOperationsPage.prototype.renderFilters = function() {
    this.bindDropDown(this.familyInput, [
      { value: "", text: "Todas" },
      { value: "reports", text: "reports" },
      { value: "special_document", text: "special_document" },
      { value: "regulated_document", text: "regulated_document" }
    ], this.filters.family);
    this.bindDropDown(this.maturityInput, [
      { value: "", text: "Todas" },
      { value: "operacional", text: "operacional" },
      { value: "interno controlado", text: "interno controlado" },
      { value: "quase homologado", text: "quase homologado" }
    ], this.filters.maturity);
    this.bindDropDown(this.auditInput, [
      { value: "", text: "Todas" },
      { value: "com", text: "com auditoria" },
      { value: "sem", text: "sem auditoria" }
    ], this.filters.audit);
  };

  DocumentOperationsPage.prototype.filteredItems = function() {
    return this.items.filter(function(item) {
      if (this.filters.family && String(item.documentFamily || "") !== this.filters.family) {
        return false;
      }
      if (this.filters.maturity && String(item.maturity || "") !== this.filters.maturity) {
        return false;
      }
      if (this.filters.audit === "com" && item.auditCapable !== true) {
        return false;
      }
      if (this.filters.audit === "sem" && item.auditCapable === true) {
        return false;
      }
      return true;
    }.bind(this));
  };

  DocumentOperationsPage.prototype.renderSummary = function() {
    this.summaryBody.find(".manual-meta").remove();
    this.summaryBody.find(".manual-summary").remove();
    const filtered = this.filteredItems();
    global.jQuery("<p class=\"manual-summary\"></p>").text("Use esta pagina como ponto unico de triagem das frentes documentais.").appendTo(this.summaryBody);
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryBody);
    this.appendBadge(badges, "Itens: " + String(filtered.length));
    this.appendBadge(badges, "Com auditoria: " + String(filtered.filter(function(item) { return item.auditCapable === true; }).length));
    this.appendBadge(badges, "Reports: " + String(filtered.filter(function(item) { return item.documentFamily === "reports"; }).length));
    this.appendBadge(badges, "Special: " + String(filtered.filter(function(item) { return item.documentFamily === "special_document"; }).length));
    this.appendBadge(badges, "Regulated: " + String(filtered.filter(function(item) { return item.documentFamily === "regulated_document"; }).length));
  };

  DocumentOperationsPage.prototype.renderCards = function() {
    this.cardsBody.empty();
    const grid = global.jQuery("<div class=\"documents-guide-grid\"></div>").appendTo(this.cardsBody);
    this.filteredItems().forEach(function(item) {
      const card = global.jQuery("<article class=\"documents-guide-card\"></article>").appendTo(grid);
      const badges = global.jQuery("<div class=\"documents-guide-badges\"></div>").appendTo(card);
      this.appendGuideBadge(badges, item.maturity || "-");
      this.appendGuideBadge(badges, item.documentFamily || "-");
      this.appendGuideBadge(badges, item.auditCapable ? "com auditoria" : "sem auditoria");
      const content = global.jQuery("<div></div>").appendTo(card);
      global.jQuery("<h3></h3>").text(item.title || "").appendTo(content);
      global.jQuery("<p></p>").text(item.summary || "").appendTo(content);
      const actions = global.jQuery("<div class=\"documents-guide-actions\"></div>").appendTo(card);
      if (item.page) {
        global.jQuery("<a class=\"k-button k-button-solid-base\" target=\"_blank\" rel=\"noopener\"></a>")
          .attr("href", this.resolveExampleUrl(String(item.page || "")))
          .text("Abrir exemplo")
          .appendTo(actions);
      }
      if (item.productionUrl) {
        global.jQuery("<a class=\"k-button k-button-solid-base\" target=\"_blank\" rel=\"noopener\"></a>")
          .attr("href", this.resolveProductionUrl(String(item.productionUrl || "")))
          .text("Abrir producao")
          .appendTo(actions);
      }
    }.bind(this));
    if (!this.filteredItems().length) {
      global.jQuery("<p class=\"manual-summary\"></p>").text("Nenhum item encontrado para o filtro atual.").appendTo(this.cardsBody);
    }
  };

  DocumentOperationsPage.prototype.applyFilters = function() {
    this.filters.family = this.readDropDownValue(this.familyInput);
    this.filters.maturity = this.readDropDownValue(this.maturityInput);
    this.filters.audit = this.readDropDownValue(this.auditInput);
    this.renderSummary();
    this.renderCards();
  };

  DocumentOperationsPage.prototype.resetFilters = function() {
    this.filters = { family: "", maturity: "", audit: "" };
    this.render();
  };

  DocumentOperationsPage.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(container);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  DocumentOperationsPage.prototype.createButton = function(container, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\" class=\"k-button k-button-solid-base\"></button>").appendTo(container);
    if (icon) {
      global.jQuery("<span class=\"k-icon\"></span>").addClass("k-i-" + icon).appendTo(button);
    }
    global.jQuery("<span></span>").text(text).appendTo(button);
    button.on("click", handler);
    return button;
  };

  DocumentOperationsPage.prototype.createSelectField = function(container, label) {
    const field = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:180px\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return global.jQuery("<input>").appendTo(field);
  };

  DocumentOperationsPage.prototype.bindDropDown = function(input, items, value) {
    const widget = input.data("kendoDropDownList");
    if (widget) {
      widget.destroy();
    }
    input.kendoDropDownList({
      dataSource: items || [],
      dataTextField: "text",
      dataValueField: "value",
      valuePrimitive: true,
      value: value || ""
    });
  };

  DocumentOperationsPage.prototype.readDropDownValue = function(input) {
    const widget = input.data("kendoDropDownList");
    return widget ? String(widget.value() || "") : "";
  };

  DocumentOperationsPage.prototype.appendBadge = function(container, text) {
    return global.jQuery("<span class=\"manual-badge\"></span>").text(text || "").appendTo(container);
  };

  DocumentOperationsPage.prototype.appendGuideBadge = function(container, text) {
    return global.jQuery("<span class=\"documents-guide-badge\"></span>").text(text || "").appendTo(container);
  };

  DocumentOperationsPage.prototype.resolveProductionUrl = function(url) {
    const value = String(url || "").replace(/^\/+/, "");
    const path = String(global.location && global.location.pathname || "").replace(/\\/g, "/");
    if (path.indexOf("/production/admin/") !== -1 && value.indexOf("production/") === 0) {
      return "../" + value.slice("production/".length);
    }
    return "../../" + value;
  };

  DocumentOperationsPage.prototype.resolveExampleUrl = function(url) {
    const value = String(url || "").replace(/^\/+/, "");
    const path = String(global.location && global.location.pathname || "").replace(/\\/g, "/");
    if (path.indexOf("/examples/pages/") !== -1) {
      return "../../" + value;
    }
    if (path.indexOf("/production/admin/") !== -1) {
      return "../../" + value;
    }
    return value;
  };

  global.DocumentOperationsPage = DocumentOperationsPage;
})(window);
