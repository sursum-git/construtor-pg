(function(global) {
  "use strict";

  function RegulatedDocumentAdmin(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#regulated-document-admin-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.bootstrapData = {};
    this.entries = [];
    this.currentEntry = null;
    this.filters = {
      tenantId: "",
      userId: "",
      screenId: "",
      track: "",
      documentType: "",
      state: "",
      dateFrom: "",
      dateTo: "",
      limit: 120
    };
  }

  RegulatedDocumentAdmin.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.loadBootstrap();
  };

  RegulatedDocumentAdmin.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell system-updates-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Documentos regulados").appendTo(title);
    global.jQuery("<p></p>").text("Consulta administrativa das emissoes, estados e artefatos do modulo regulado.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    this.summaryCard = this.createCard(shell, "Resumo");
    this.summaryBody = this.summaryCard.body;

    const toolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(this.summaryBody);
    this.tenantInput = this.createSelectField(toolbar, "Tenant");
    this.userInput = this.createSelectField(toolbar, "Usuario");
    this.screenInput = this.createSelectField(toolbar, "Tela");
    this.trackInput = this.createSelectField(toolbar, "Track");
    this.documentTypeInput = this.createSelectField(toolbar, "Tipo");
    this.stateInput = this.createSelectField(toolbar, "Estado");
    this.dateFromInput = this.createDateField(toolbar, "De");
    this.dateToInput = this.createDateField(toolbar, "Ate");
    this.limitInput = this.createNumberField(toolbar, "Limite", 120);
    this.createButton(toolbar, "Aplicar filtros", "filter", this.handleApplyFilters.bind(this));
    this.createButton(toolbar, "Limpar filtros", "reset", this.handleResetFilters.bind(this));
    this.createButton(toolbar, "Exportar JSON", "download", this.handleExportJson.bind(this));
    this.createButton(toolbar, "Atualizar", "reload", this.loadEntries.bind(this));
    this.createButton(toolbar, "Baixar artefato", "download", this.handleDownloadArtifact.bind(this));

    const grid = global.jQuery("<div class=\"program-governance-admin-grid\"></div>").appendTo(shell);
    this.leftColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.rightColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.gridCard = this.createCard(this.leftColumn, "Emissoes");
    this.gridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(this.gridCard.body);
    this.detailCard = this.createCard(this.rightColumn, "Detalhe");
    this.detailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(this.detailCard.body);
    this.detailElement.text("Selecione uma emissao.");
  };

  RegulatedDocumentAdmin.prototype.loadBootstrap = function() {
    this.setStatus("Carregando");
    return this.request("GET", "/api/admin/regulated-document/bootstrap").then(function(payload) {
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
      this.showError(error, "Nao foi possivel carregar o modulo regulado.");
      throw error;
    }.bind(this));
  };

  RegulatedDocumentAdmin.prototype.loadEntries = function() {
    return this.request("GET", "/api/admin/regulated-document/entries", this.filters).then(function(payload) {
      this.entries = global.CrudUtils.ensureArray(payload.items);
      this.summaryData = payload.summary || {};
      this.renderSummary();
      this.renderGrid();
      this.renderDetail();
      return payload;
    }.bind(this)).catch(function(error) {
      this.showError(error, "Nao foi possivel carregar as emissoes reguladas.");
      throw error;
    }.bind(this));
  };

  RegulatedDocumentAdmin.prototype.renderSummary = function() {
    this.summaryBody.find(".manual-summary").remove();
    this.summaryBody.find(".manual-meta").remove();
    if (this.bootstrapData.enabled === false) {
      global.jQuery("<p class=\"manual-summary\"></p>").text("O storage do modulo regulado esta desabilitado neste ambiente.").appendTo(this.summaryBody);
      return;
    }
    const summary = this.summaryData || this.bootstrapData.summary || {};
    global.jQuery("<p class=\"manual-summary\"></p>").text("Storage separado pronto para trilha de preparo, emissao, conferencia e artefato.").appendTo(this.summaryBody);
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryBody);
    this.appendBadge(badges, "Total: " + String(summary.total || 0));
    this.appendBadge(badges, "Carregado: " + String(summary.loaded || 0));
    Object.keys(summary.byState || {}).slice(0, 4).forEach(function(key) {
      this.appendBadge(badges, key + ": " + String(summary.byState[key]));
    }, this);
  };

  RegulatedDocumentAdmin.prototype.renderFilters = function() {
    this.bindDropDown(this.tenantInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.tenantIds || []);
    this.bindDropDown(this.userInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.userIds || []);
    this.bindDropDown(this.screenInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.screenIds || []);
    this.bindDropDown(this.trackInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.tracks || []);
    this.bindDropDown(this.documentTypeInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.documentTypes || []);
    this.bindDropDown(this.stateInput, this.bootstrapData.filterOptions && this.bootstrapData.filterOptions.states || []);
    this.dateFromInput.val(this.filters.dateFrom || "");
    this.dateToInput.val(this.filters.dateTo || "");
    this.limitInput.val(String(this.filters.limit || 120));
  };

  RegulatedDocumentAdmin.prototype.renderGrid = function() {
    if (this.gridWidget) {
      this.gridWidget.destroy();
      this.gridElement.empty();
    }
    this.gridElement.kendoGrid({
      dataSource: { data: this.entries, pageSize: 20 },
      height: 520,
      pageable: true,
      sortable: true,
      selectable: "row",
      columns: [
        { field: "updatedAt", title: "Atualizado em", width: 190 },
        { field: "track", title: "Track", width: 110 },
        { field: "documentType", title: "Tipo", width: 140 },
        { field: "state", title: "Estado", width: 110 },
        { field: "issueId", title: "IssueId", width: 170 },
        { field: "screenId", title: "Tela", width: 180 },
        { field: "tenantId", title: "Tenant", width: 120 }
      ],
      change: function() {
        const item = this.dataItem(this.select());
        this.currentEntry = item ? item.toJSON ? item.toJSON() : item : null;
        this.renderDetail();
      }.bind(this)
    });
    this.gridWidget = this.gridElement.data("kendoGrid");
  };

  RegulatedDocumentAdmin.prototype.renderDetail = function() {
    this.detailElement.empty();
    const entry = this.currentEntry || this.entries[0] || null;
    if (!entry) {
      this.detailElement.text(this.bootstrapData.enabled === false ? "Storage desabilitado." : "Nenhuma emissao encontrada.");
      return;
    }
    this.currentEntry = entry;
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.detailElement);
    this.appendBadge(badges, "Estado " + String(entry.state || "-"));
    this.appendBadge(badges, "Track " + String(entry.track || "-"));
    this.appendBadge(badges, "Tipo " + String(entry.documentType || "-"));
    this.appendBadge(badges, "Artefato " + (entry.artifact && entry.artifact.contentBase64 ? "salvo" : "nao salvo"));
    const dl = global.jQuery("<dl class=\"program-builder-technical-properties\"></dl>").appendTo(this.detailElement);
    this.appendDefinition(dl, "IssueId", entry.issueId || "");
    this.appendDefinition(dl, "Tela", entry.screenId || "");
    this.appendDefinition(dl, "Documento", entry.documentId || "");
    this.appendDefinition(dl, "Hash", entry.hash || "");
    this.appendDefinition(dl, "Usuario", entry.userId || "");
    this.appendDefinition(dl, "Compliance", entry.complianceProfile || "");
    this.appendDefinition(dl, "Atualizado em", entry.updatedAt || "");
    const jsonTitle = global.jQuery("<h3></h3>").text("Payload tecnico").css({ marginTop: "16px", marginBottom: "8px" }).appendTo(this.detailElement);
    global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
      .text(JSON.stringify({
        parameters: entry.parameters || null,
        canonicalPayload: entry.canonicalPayload || null,
        validation: entry.validation || null,
        verification: entry.verification || null,
        artifact: entry.artifact ? { format: entry.artifact.format, fileName: entry.artifact.fileName, stored: !!entry.artifact.contentBase64 } : null,
        metadata: entry.metadata || null
      }, null, 2))
      .appendTo(this.detailElement);
  };

  RegulatedDocumentAdmin.prototype.handleApplyFilters = function() {
    this.filters = {
      tenantId: this.readDropDownValue(this.tenantInput),
      userId: this.readDropDownValue(this.userInput),
      screenId: this.readDropDownValue(this.screenInput),
      track: this.readDropDownValue(this.trackInput),
      documentType: this.readDropDownValue(this.documentTypeInput),
      state: this.readDropDownValue(this.stateInput),
      dateFrom: String(this.dateFromInput.val() || ""),
      dateTo: String(this.dateToInput.val() || ""),
      limit: Math.max(1, Math.min(300, Number(this.limitInput.val() || 120) || 120))
    };
    this.loadEntries();
  };

  RegulatedDocumentAdmin.prototype.handleResetFilters = function() {
    this.filters = {
      tenantId: "",
      userId: "",
      screenId: "",
      track: "",
      documentType: "",
      state: "",
      dateFrom: "",
      dateTo: "",
      limit: 120
    };
    this.renderFilters();
    this.loadEntries();
  };

  RegulatedDocumentAdmin.prototype.handleExportJson = function() {
    this.downloadFile("regulated-document-audit.json", "application/json", JSON.stringify(this.entries, null, 2));
  };

  RegulatedDocumentAdmin.prototype.handleDownloadArtifact = function() {
    const entry = this.currentEntry;
    if (!entry || !entry.issueId) {
      global.CrudUtils.showMessage("Selecione uma emissao para baixar o artefato.", "warning");
      return;
    }
    this.request("GET", "/api/admin/regulated-document/artifact", { issueId: entry.issueId }).then(function(payload) {
      this.downloadBase64Payload(payload);
    }.bind(this)).catch(function(error) {
      this.showError(error, "Nao foi possivel baixar o artefato.");
    }.bind(this));
  };

  RegulatedDocumentAdmin.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(container);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  RegulatedDocumentAdmin.prototype.createButton = function(container, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\" class=\"k-button k-button-solid-base\"></button>").appendTo(container);
    if (icon) {
      global.jQuery("<span class=\"k-icon\"></span>").addClass("k-i-" + icon).appendTo(button);
    }
    global.jQuery("<span></span>").text(text).appendTo(button);
    button.on("click", handler);
    return button;
  };

  RegulatedDocumentAdmin.prototype.createSelectField = function(container, label) {
    const field = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:160px\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return global.jQuery("<input>").appendTo(field);
  };

  RegulatedDocumentAdmin.prototype.createDateField = function(container, label) {
    const field = global.jQuery("<label class=\"program-builder-field\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return global.jQuery("<input type=\"date\">").appendTo(field);
  };

  RegulatedDocumentAdmin.prototype.createNumberField = function(container, label, value) {
    const field = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:110px\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return global.jQuery("<input type=\"number\" min=\"1\" max=\"300\">").val(String(value || 120)).appendTo(field);
  };

  RegulatedDocumentAdmin.prototype.bindDropDown = function(input, items) {
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

  RegulatedDocumentAdmin.prototype.readDropDownValue = function(input) {
    const widget = input.data("kendoDropDownList");
    return widget ? String(widget.value() || "") : "";
  };

  RegulatedDocumentAdmin.prototype.appendBadge = function(container, text) {
    return global.jQuery("<span class=\"manual-badge\"></span>").text(text || "").appendTo(container);
  };

  RegulatedDocumentAdmin.prototype.appendDefinition = function(container, title, value) {
    const wrapper = global.jQuery("<div></div>").appendTo(container);
    global.jQuery("<dt></dt>").text(title || "").appendTo(wrapper);
    global.jQuery("<dd></dd>").text(value == null ? "" : String(value)).appendTo(wrapper);
    return wrapper;
  };

  RegulatedDocumentAdmin.prototype.setStatus = function(text) {
    this.statusBadge.text(text || "");
  };

  RegulatedDocumentAdmin.prototype.showError = function(error, fallback) {
    const message = error && error.error && error.error.message || error && error.message || fallback;
    global.CrudUtils.showMessage(message, "error");
  };

  RegulatedDocumentAdmin.prototype.request = function(method, url, data) {
    return this.httpClient.request({ method: method, url: url, data: data || {} });
  };

  RegulatedDocumentAdmin.prototype.downloadBase64Payload = function(payload) {
    const bytes = Uint8Array.from(global.atob(String(payload.contentBase64 || "")), function(char) {
      return char.charCodeAt(0);
    });
    const blob = new Blob([bytes], { type: String(payload.contentType || "application/octet-stream") });
    const href = global.URL.createObjectURL(blob);
    const link = global.document.createElement("a");
    link.href = href;
    link.download = String(payload.fileName || "documento-regulado");
    global.document.body.appendChild(link);
    link.click();
    link.remove();
    global.URL.revokeObjectURL(href);
  };

  RegulatedDocumentAdmin.prototype.downloadFile = function(fileName, mimeType, content) {
    const blob = new Blob([content], { type: mimeType || "application/octet-stream" });
    const href = global.URL.createObjectURL(blob);
    const link = global.document.createElement("a");
    link.href = href;
    link.download = fileName;
    global.document.body.appendChild(link);
    link.click();
    link.remove();
    global.URL.revokeObjectURL(href);
  };

  global.RegulatedDocumentAdmin = RegulatedDocumentAdmin;
})(window);
