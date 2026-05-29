(function(global) {
  "use strict";

  function ReportAuditAdmin(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#report-audit-admin-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.bootstrapData = {};
    this.entries = [];
    this.currentEntry = null;
    this.filters = {
      tenantId: "",
      userId: "",
      screenId: "",
      reportId: "",
      resultSource: "",
      dateFrom: "",
      dateTo: "",
      limit: 120
    };
  }

  ReportAuditAdmin.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.loadBootstrap();
  };

  ReportAuditAdmin.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell system-updates-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Auditoria de relatorios").appendTo(title);
    global.jQuery("<p></p>").text("Consulta administrativa das emissoes de relatorios gravadas no banco separado de auditoria.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    this.summaryCard = this.createCard(shell, "Resumo");
    this.summaryBody = this.summaryCard.body;

    const toolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(this.summaryBody);
    this.tenantInput = this.createSelectField(toolbar, "Tenant");
    this.userInput = this.createSelectField(toolbar, "Usuario");
    this.screenInput = this.createSelectField(toolbar, "Tela");
    this.reportInput = this.createSelectField(toolbar, "Relatorio");
    this.resultSourceInput = this.createSelectField(toolbar, "Origem");
    this.dateFromInput = this.createDateField(toolbar, "De");
    this.dateToInput = this.createDateField(toolbar, "Ate");
    this.limitInput = this.createNumberField(toolbar, "Limite", 120);
    this.createButton(toolbar, "Aplicar filtros", "filter", this.handleApplyFilters.bind(this));
    this.createButton(toolbar, "Limpar filtros", "reset", this.handleResetFilters.bind(this));
    this.createButton(toolbar, "Exportar JSON", "download", this.handleExportJson.bind(this));
    this.createButton(toolbar, "Atualizar", "reload", this.loadEntries.bind(this));

    const grid = global.jQuery("<div class=\"program-governance-admin-grid\"></div>").appendTo(shell);
    this.leftColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.rightColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.gridCard = this.createCard(this.leftColumn, "Emissoes");
    this.gridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(this.gridCard.body);
    this.detailCard = this.createCard(this.rightColumn, "Detalhe");
    this.detailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(this.detailCard.body);
    this.detailElement.text("Selecione uma emissao.");
  };

  ReportAuditAdmin.prototype.loadBootstrap = function() {
    this.setStatus("Carregando");
    return this.request("GET", "/api/admin/report-audit/bootstrap").then(function(payload) {
      this.bootstrapData = payload || {};
      this.renderSummary();
      this.renderFilters();
      if (payload && payload.enabled === false) {
        this.entries = [];
        this.renderGrid();
        this.renderDetail();
        this.setStatus("Desabilitado");
        return payload;
      }
      return this.loadEntries().then(function() {
        this.setStatus("Pronto");
        return payload;
      }.bind(this));
    }.bind(this)).catch(function(error) {
      this.setStatus("Falha");
      this.showError(error, "Nao foi possivel carregar a auditoria de relatorios.");
      throw error;
    }.bind(this));
  };

  ReportAuditAdmin.prototype.loadEntries = function() {
    return this.request("GET", "/api/admin/report-audit/entries", this.filters).then(function(payload) {
      this.entries = global.CrudUtils.ensureArray(payload.items);
      this.summaryData = payload.summary || {};
      this.renderSummary();
      this.renderGrid();
      this.renderDetail();
      return payload;
    }.bind(this)).catch(function(error) {
      this.showError(error, "Nao foi possivel carregar as emissoes auditadas.");
      throw error;
    }.bind(this));
  };

  ReportAuditAdmin.prototype.renderSummary = function() {
    this.summaryBody.find(".manual-summary").remove();
    this.summaryBody.find(".manual-meta").remove();
    if (this.bootstrapData.enabled === false) {
      global.jQuery("<p class=\"manual-summary\"></p>").text("A auditoria de relatorios esta desabilitada neste ambiente.").appendTo(this.summaryBody);
      return;
    }
    const summary = this.summaryData || this.bootstrapData.summary || {};
    global.jQuery("<p class=\"manual-summary\"></p>").text("Base separada pronta para rastrear emissoes de relatorios operacionais e analiticos.").appendTo(this.summaryBody);
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryBody);
    this.appendBadge(badges, "Total: " + String(summary.total || 0));
    this.appendBadge(badges, "Carregado: " + String(summary.loaded || 0));
    Object.keys(summary.byReport || {}).slice(0, 4).forEach(function(key) {
      this.appendBadge(badges, key + ": " + String(summary.byReport[key]));
    }, this);
  };

  ReportAuditAdmin.prototype.renderFilters = function() {
    this.bindDropDown(this.tenantInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.tenantIds || []);
    this.bindDropDown(this.userInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.userIds || []);
    this.bindDropDown(this.screenInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.screenIds || []);
    this.bindDropDown(this.reportInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.reportIds || []);
    this.bindDropDown(this.resultSourceInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.resultSources || []);
    this.dateFromInput.val(this.filters.dateFrom || "");
    this.dateToInput.val(this.filters.dateTo || "");
    this.limitInput.val(String(this.filters.limit || 120));
  };

  ReportAuditAdmin.prototype.renderGrid = function() {
    if (this.gridWidget) {
      this.gridWidget.destroy();
      this.gridElement.empty();
    }
    this.gridElement.kendoGrid({
      dataSource: {
        data: this.entries,
        pageSize: 20
      },
      height: 520,
      pageable: true,
      sortable: true,
      selectable: "row",
      columns: [
        { field: "consultedAt", title: "Emitido em", width: 190 },
        { field: "tenantId", title: "Tenant", width: 120 },
        { field: "userId", title: "Usuario", width: 120 },
        { field: "screenId", title: "Tela", width: 180 },
        { field: "datasetId", title: "Relatorio", width: 180 },
        { field: "resultSource", title: "Origem", width: 120 },
        { field: "rowCount", title: "Linhas", width: 90 },
        { field: "viewId", title: "Formato", width: 100 }
      ],
      change: function() {
        const item = this.dataItem(this.select());
        this.currentEntry = item ? item.toJSON ? item.toJSON() : item : null;
        this.renderDetail();
      }.bind(this)
    });
    this.gridWidget = this.gridElement.data("kendoGrid");
  };

  ReportAuditAdmin.prototype.renderDetail = function() {
    this.detailElement.empty();
    const entry = this.currentEntry || this.entries[0] || null;
    if (!entry) {
      this.detailElement.text(this.bootstrapData.enabled === false ? "Auditoria desabilitada." : "Nenhuma emissao encontrada.");
      return;
    }
    this.currentEntry = entry;
    const metadata = entry.metadata || {};
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.detailElement);
    this.appendBadge(badges, "Tela " + String(entry.screenId || "-"));
    this.appendBadge(badges, "Relatorio " + String(metadata.reportId || entry.datasetId || "-"));
    this.appendBadge(badges, "Origem " + String(entry.resultSource || "-"));
    this.appendBadge(badges, "Fonte " + String(metadata.sourceType || entry.executionMode || "-"));
    this.appendBadge(badges, "Formato " + String(entry.viewId || "-"));
    const dl = global.jQuery("<dl class=\"program-builder-technical-properties\"></dl>").appendTo(this.detailElement);
    this.appendDefinition(dl, "Emitido em", entry.consultedAt || "");
    this.appendDefinition(dl, "Usuario", entry.userId || "");
    this.appendDefinition(dl, "Sessao", entry.sessionId || "");
    this.appendDefinition(dl, "Fingerprint", entry.filterFingerprint || "");
    this.appendDefinition(dl, "Linhas", String(entry.rowCount || 0));
    this.appendDefinition(dl, "Total", String(entry.totalCount || 0));
    if (entry.errorMessage) {
      this.appendDefinition(dl, "Erro", entry.errorMessage);
    }
    const jsonTitle = global.jQuery("<h3></h3>").text("Payload tecnico").appendTo(this.detailElement);
    jsonTitle.css({ marginTop: "16px", marginBottom: "8px" });
    global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
      .text(JSON.stringify({
        filters: entry.filters || null,
        parameters: entry.parameters || null,
        sort: entry.sort || null,
        requestPayload: entry.requestPayload || null,
        resultColumns: entry.resultColumns || null,
        resultRows: entry.resultRows || null,
        metadata: metadata || null
      }, null, 2))
      .appendTo(this.detailElement);
  };

  ReportAuditAdmin.prototype.handleApplyFilters = function() {
    this.filters = {
      tenantId: this.readDropDownValue(this.tenantInput),
      userId: this.readDropDownValue(this.userInput),
      screenId: this.readDropDownValue(this.screenInput),
      reportId: this.readDropDownValue(this.reportInput),
      resultSource: this.readDropDownValue(this.resultSourceInput),
      dateFrom: String(this.dateFromInput.val() || ""),
      dateTo: String(this.dateToInput.val() || ""),
      limit: Math.max(1, Math.min(300, Number(this.limitInput.val() || 120) || 120))
    };
    this.loadEntries();
  };

  ReportAuditAdmin.prototype.handleResetFilters = function() {
    this.filters = {
      tenantId: "",
      userId: "",
      screenId: "",
      reportId: "",
      resultSource: "",
      dateFrom: "",
      dateTo: "",
      limit: 120
    };
    this.renderFilters();
    this.loadEntries();
  };

  ReportAuditAdmin.prototype.handleExportJson = function() {
    const content = JSON.stringify(this.entries, null, 2);
    this.downloadFile("report-audit.json", "application/json", content);
  };

  ReportAuditAdmin.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(container);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  ReportAuditAdmin.prototype.createButton = function(container, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\" class=\"k-button k-button-solid-base\"></button>").appendTo(container);
    if (icon) {
      global.jQuery("<span class=\"k-icon\"></span>").addClass("k-i-" + icon).appendTo(button);
    }
    global.jQuery("<span></span>").text(text).appendTo(button);
    button.on("click", handler);
    return button;
  };

  ReportAuditAdmin.prototype.createSelectField = function(container, label) {
    const field = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:180px\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return global.jQuery("<input>").appendTo(field);
  };

  ReportAuditAdmin.prototype.createDateField = function(container, label) {
    const field = global.jQuery("<label class=\"program-builder-field\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return global.jQuery("<input type=\"date\">").appendTo(field);
  };

  ReportAuditAdmin.prototype.createNumberField = function(container, label, value) {
    const field = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:110px\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return global.jQuery("<input type=\"number\" min=\"1\" max=\"300\">").val(String(value || 120)).appendTo(field);
  };

  ReportAuditAdmin.prototype.bindDropDown = function(input, items) {
    const widget = input.data("kendoDropDownList");
    if (widget) {
      widget.destroy();
    }
    input.kendoDropDownList({
      optionLabel: "Todos",
      valuePrimitive: true,
      dataSource: (items || []).map(function(item) {
        return { value: item, text: item };
      }),
      dataTextField: "text",
      dataValueField: "value",
      value: ""
    });
  };

  ReportAuditAdmin.prototype.readDropDownValue = function(input) {
    const widget = input.data("kendoDropDownList");
    return widget ? String(widget.value() || "") : "";
  };

  ReportAuditAdmin.prototype.appendBadge = function(container, text) {
    return global.jQuery("<span class=\"manual-badge\"></span>").text(text || "").appendTo(container);
  };

  ReportAuditAdmin.prototype.appendDefinition = function(container, title, value) {
    const wrapper = global.jQuery("<div></div>").appendTo(container);
    global.jQuery("<dt></dt>").text(title || "").appendTo(wrapper);
    global.jQuery("<dd></dd>").text(value == null ? "" : String(value)).appendTo(wrapper);
    return wrapper;
  };

  ReportAuditAdmin.prototype.setStatus = function(text) {
    this.statusBadge.text(text || "");
  };

  ReportAuditAdmin.prototype.showError = function(error, fallback) {
    const message = error && error.error && error.error.message || error && error.message || fallback;
    global.CrudUtils.showMessage(message, "error");
  };

  ReportAuditAdmin.prototype.request = function(method, url, data) {
    return this.httpClient.request({ method: method, url: url, data: data || {} });
  };

  ReportAuditAdmin.prototype.downloadFile = function(fileName, mimeType, content) {
    const blob = new Blob([content], { type: mimeType });
    const url = global.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    global.setTimeout(function() {
      global.URL.revokeObjectURL(url);
    }, 0);
  };

  global.ReportAuditAdmin = ReportAuditAdmin;
})(window);
