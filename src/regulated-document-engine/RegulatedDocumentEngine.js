(function(global, $) {
  "use strict";

  class RegulatedDocumentEngine {
    constructor(options) {
      this.options = options || {};
      this.root = $(this.options.root);
      this.httpClient = this.options.httpClient || new global.CrudHttpClient();
      this.configLoader = new global.CrudConfigLoader();
      this.config = this.options.config || {};
      this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
      this.definition = null;
      this.parameterWidgets = {};
      this.currentPrepared = null;
      this.currentPreview = null;
      this.currentIssued = null;
      this.currentVerification = null;
    }

    init() {
      return this.loadConfig()
        .then(() => this.loadDefinition())
        .then((definition) => {
          this.definition = this.normalizeDefinition(definition);
          this.render();
          return this.prepareDocument();
        })
        .then(() => this);
    }

    destroy() {
      if (global.kendo) {
        global.kendo.destroy(this.root);
      }
      this.root.empty();
      this.parameterWidgets = {};
    }

    loadConfig() {
      return this.configLoader.load({
        configUrl: this.options.configUrl,
        config: this.options.config
      }).then((config) => {
        this.config = config || {};
        this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
        return this.config;
      });
    }

    loadDefinition() {
      if (this.options.definition) {
        return Promise.resolve(global.CrudUtils.clone(this.options.definition));
      }
      const screenId = String(this.options.screenId || "").trim();
      return this.httpClient.request(global.CrudUtils.buildScreenDefinitionRequest(screenId, this.securityPolicy, "regulated_document"));
    }

    normalizeDefinition(definition) {
      const source = global.CrudUtils.clone(definition || {});
      if (String(source.pageType || "") !== "regulated_document") {
        throw global.CrudUtils.makeError("INVALID_REGULATED_DOCUMENT_DEFINITION", "A definicao recebida nao e do tipo regulated_document.");
      }
      source.program = Object.assign({ title: "Documento regulado", subtitle: "" }, source.program || {});
      source.regulatedDocument = Object.assign({
        track: "fiscal",
        documentType: "regulated_document",
        complianceProfile: "near_homologated",
        renderEngine: "internal",
        source: { type: "operational" },
        outputs: { html: true, pdf: true },
        artifactPolicy: { storeCanonicalPayload: true, storeArtifact: true, defaultFormat: "pdf" },
        verification: { enabled: true, algorithm: "sha256", publicPath: "regulated-document-authenticity.html", label: "Codigo de conferencia" },
        retention: { keepPayload: true, keepArtifact: true, storeDays: 365 },
        layout: { title: source.program.title || "Documento regulado", subtitle: source.program.subtitle || "", notes: "" }
      }, source.regulatedDocument || {});
      source.regulatedDocument.endpoints = Object.assign({}, source.regulatedDocument.endpoints || {}, source.dataSource && source.dataSource.api || source.api || {});
      return source;
    }

    render() {
      this.root.empty().addClass("report-shell regulated-document-shell");
      const screen = $("<section class=\"report-screen\"></section>").appendTo(this.root);
      const header = $("<header class=\"report-header\"></header>").appendTo(screen);
      $("<p class=\"report-kicker\"></p>").text("Modulo regulado").appendTo(header);
      $("<h1></h1>").text(this.definition.program.title || "Documento regulado").appendTo(header);
      $("<p class=\"report-subtitle\"></p>").text(this.definition.program.subtitle || "Trilha separada para documentos de alto rigor.").appendTo(header);
      const toolbar = $("<section class=\"report-toolbar\"></section>").appendTo(screen);
      $("<p class=\"report-toolbar-note\"></p>").text("Base geral interna, com pipeline de preparo, emissao, hash e artefato.").appendTo(toolbar);
      const actions = $("<div class=\"report-toolbar-actions\"></div>").appendTo(toolbar);
      this.prepareButton = this.makeButton(actions, "Preparar", "gear", "primary", () => this.prepareDocument(true));
      this.previewButton = this.makeButton(actions, "Preview", "eye", "", () => this.renderDocument());
      this.issuePdfButton = this.makeButton(actions, "Emitir PDF", "file-pdf", "", () => this.issueDocument("pdf"));
      this.issueHtmlButton = this.makeButton(actions, "Emitir HTML", "file", "", () => this.issueDocument("html"));
      this.verifyButton = this.makeButton(actions, "Conferir", "search", "", () => this.verifyDocument());
      this.downloadButton = this.makeButton(actions, "Baixar artefato", "download", "", () => this.downloadArtifact());
      this.renderParameters(screen);
      this.stateHost = $("<section></section>").appendTo(screen);
      this.validationHost = $("<section></section>").appendTo(screen);
      this.outputHost = $("<section class=\"report-output\"></section>").appendTo(screen);
      this.updateStateCards();
      this.updateValidationList();
      this.renderEmpty("Prepare o documento para montar o payload canonico.");
    }

    makeButton(host, text, icon, themeColor, click) {
      const button = $("<button type=\"button\"></button>").appendTo(host).kendoButton({
        icon: icon,
        themeColor: themeColor || "base",
        click: click
      }).data("kendoButton");
      button.element.text(text);
      return button;
    }

    renderParameters(screen) {
      const parameters = global.CrudUtils.ensureArray(this.definition.regulatedDocument && this.definition.regulatedDocument.parameters);
      if (!parameters.length) {
        return;
      }
      const wrapper = $("<section class=\"report-parameters\"></section>").appendTo(screen);
      $("<h2></h2>").text("Parametros").appendTo(wrapper);
      const grid = $("<div class=\"report-parameters-grid\"></div>").appendTo(wrapper);
      this.parameterWidgets = {};
      parameters.forEach((parameter) => {
        const field = $("<label class=\"report-field\"></label>").appendTo(grid);
        $("<span></span>").text(parameter.label || parameter.id || "Parametro").appendTo(field);
        const key = String(parameter.id || parameter.field || "");
        const type = String(parameter.type || "text").toLowerCase();
        const input = $("<input>").attr("type", this.parameterInputType(type)).appendTo(field);
        if (type === "date" && global.kendo) {
          input.kendoDatePicker({ format: "dd/MM/yyyy" });
          this.parameterWidgets[key] = input.data("kendoDatePicker");
        } else {
          this.parameterWidgets[key] = input;
        }
      });
    }

    parameterInputType(type) {
      if (type === "number" || type === "integer" || type === "decimal") {
        return "number";
      }
      if (type === "date") {
        return "date";
      }
      return "text";
    }

    currentParameters() {
      const values = {};
      const parameters = global.CrudUtils.ensureArray(this.definition.regulatedDocument && this.definition.regulatedDocument.parameters);
      parameters.forEach((parameter) => {
        const key = String(parameter.id || parameter.field || "");
        const widget = this.parameterWidgets[key];
        if (!widget) {
          return;
        }
        const value = typeof widget.value === "function" ? widget.value() : $(widget).val();
        if (value === "" || value == null) {
          return;
        }
        values[key] = value;
      });
      return values;
    }

    runtimeRequest(key, payload) {
      const endpoints = this.definition.regulatedDocument && this.definition.regulatedDocument.endpoints || {};
      const fallback = {
        schema: "regulatedDocuments.schema",
        prepare: "regulatedDocuments.prepare",
        render: "regulatedDocuments.render",
        issue: "regulatedDocuments.issue",
        verify: "regulatedDocuments.verify",
        artifact: "regulatedDocuments.artifact"
      }[key] || key;
      const screenId = String(this.definition.screenId || this.definition.program && this.definition.program.screenId || this.options.screenId || "").trim();
      const resolved = global.CrudUtils.resolveEndpointForPolicy(endpoints[key] || { endpointId: fallback, method: "POST" }, fallback, screenId, this.securityPolicy);
      return this.httpClient.request(Object.assign({}, resolved, { method: "POST", data: payload || {} }));
    }

    prepareDocument(notify) {
      return this.runtimeRequest("prepare", {
        issueId: this.currentPrepared && this.currentPrepared.issueId || "",
        parameters: this.currentParameters(),
        documentType: this.definition.regulatedDocument && this.definition.regulatedDocument.documentType || "regulated_document",
        track: this.definition.regulatedDocument && this.definition.regulatedDocument.track || "fiscal"
      }).then((response) => {
        this.currentPrepared = response || {};
        this.currentPreview = null;
        this.currentIssued = null;
        this.currentVerification = null;
        this.updateStateCards();
        this.updateValidationList();
        this.renderEmpty(response && response.ok === false
          ? "Preparacao com pendencias. Revise as validacoes antes de emitir."
          : "Payload canonico preparado. Use Preview ou Emitir.");
        if (notify) {
          global.CrudUtils.showMessage(response && response.ok === false ? "Preparacao concluiu com pendencias." : "Payload preparado.", response && response.ok === false ? "warning" : "success");
        }
        return response;
      });
    }

    renderDocument() {
      return this.runtimeRequest("render", {
        issueId: this.currentPrepared && this.currentPrepared.issueId || "",
        parameters: this.currentParameters()
      }).then((response) => {
        this.currentPreview = response || {};
        this.currentPrepared = Object.assign({}, this.currentPrepared || {}, { issueId: response.issueId, state: "rendered" });
        this.updateStateCards();
        this.updateValidationList();
        this.renderPreview();
        return response;
      }).catch((error) => {
        this.handleError(error, "Nao foi possivel gerar o preview do documento regulado.");
        throw error;
      });
    }

    issueDocument(format) {
      return this.runtimeRequest("issue", {
        issueId: this.currentPrepared && this.currentPrepared.issueId || "",
        parameters: this.currentParameters(),
        format: format
      }).then((response) => {
        this.currentIssued = response || {};
        this.currentPrepared = Object.assign({}, this.currentPrepared || {}, { issueId: response.issueId, state: response.state || "issued" });
        this.updateStateCards();
        if (response && response.artifact && response.artifact.contentBase64) {
          this.downloadPayload(response.artifact);
        }
        global.CrudUtils.showMessage("Documento regulado emitido.", "success");
        return response;
      }).catch((error) => {
        this.handleError(error, "Nao foi possivel emitir o documento regulado.");
        throw error;
      });
    }

    verifyDocument() {
      const issueId = this.currentIssued && this.currentIssued.issueId || this.currentPrepared && this.currentPrepared.issueId || "";
      const hash = this.currentIssued && this.currentIssued.hash || "";
      return this.runtimeRequest("verify", {
        issueId: issueId,
        hash: hash
      }).then((response) => {
        this.currentVerification = response || {};
        if (this.currentPrepared && response && response.state) {
          this.currentPrepared.state = response.state;
        }
        this.updateStateCards();
        this.renderPreview();
        global.CrudUtils.showMessage(response && response.ok === true ? "Conferencia concluida." : "Conferencia retornou pendencias.", response && response.ok === true ? "success" : "warning");
        return response;
      }).catch((error) => {
        this.handleError(error, "Nao foi possivel conferir o documento regulado.");
        throw error;
      });
    }

    downloadArtifact() {
      const issueId = this.currentIssued && this.currentIssued.issueId || this.currentPrepared && this.currentPrepared.issueId || "";
      if (!issueId) {
        global.CrudUtils.showMessage("Prepare e emita o documento antes de baixar o artefato.", "warning");
        return Promise.resolve(null);
      }
      return this.runtimeRequest("artifact", { issueId: issueId }).then((response) => {
        this.downloadPayload(response);
        return response;
      }).catch((error) => {
        this.handleError(error, "Nao foi possivel baixar o artefato emitido.");
        throw error;
      });
    }

    downloadPayload(payload) {
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
    }

    updateStateCards() {
      this.stateHost.empty();
      const grid = $("<div class=\"regulated-document-state-grid\"></div>").appendTo(this.stateHost);
      this.appendStateCard(grid, "Track", this.definition && this.definition.regulatedDocument && this.definition.regulatedDocument.track || "fiscal");
      this.appendStateCard(grid, "IssueId", this.currentPrepared && this.currentPrepared.issueId || "-");
      this.appendStateCard(grid, "Estado", this.currentVerification && this.currentVerification.state || this.currentIssued && this.currentIssued.state || this.currentPrepared && this.currentPrepared.state || "novo");
      this.appendStateCard(grid, "Hash", this.currentIssued && this.currentIssued.hash || "-");
      this.appendStateCard(grid, "Artefato", this.currentIssued ? (this.currentIssued.format || "-") : "-");
      this.appendStateCard(grid, "Store", this.definition && this.definition.regulatedDocument && this.definition.regulatedDocument.artifactPolicy && this.definition.regulatedDocument.artifactPolicy.storeArtifact === false ? "Payload" : "Payload + artefato");
    }

    appendStateCard(parent, label, value) {
      const card = $("<article class=\"regulated-document-state-card\"></article>").appendTo(parent);
      $("<span></span>").text(label).appendTo(card);
      $("<strong></strong>").text(value == null ? "" : String(value)).appendTo(card);
    }

    updateValidationList() {
      this.validationHost.empty();
      const items = global.CrudUtils.ensureArray(this.currentPrepared && this.currentPrepared.validation);
      $("<h2></h2>").text("Validacoes").appendTo(this.validationHost);
      if (!items.length) {
        $("<div class=\"regulated-document-empty\"></div>").text("Nenhuma validacao registrada.").appendTo(this.validationHost);
        return;
      }
      const list = $("<div class=\"regulated-document-validation-list\"></div>").appendTo(this.validationHost);
      items.forEach(function(item) {
        const row = $("<article class=\"regulated-document-validation-item\"></article>").attr("data-level", String(item.level || "info")).appendTo(list);
        $("<strong></strong>").text(item.code || item.level || "Validacao").appendTo(row);
        $("<div></div>").text(item.message || "").appendTo(row);
      });
    }

    renderPreview() {
      if (!this.currentPreview) {
        this.renderEmpty("Preview indisponivel.");
        return;
      }
      const result = this.currentPreview;
      this.outputHost.empty();
      const summary = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Resumo").appendTo(summary);
      const grid = $("<div class=\"report-summary-grid\"></div>").appendTo(summary);
      global.CrudUtils.ensureArray(result.summary).forEach(function(item) {
        const card = $("<article class=\"report-summary-card\"></article>").appendTo(grid);
        $("<span></span>").text(item.label || "").appendTo(card);
        $("<strong></strong>").text(item.value == null ? "" : item.value).appendTo(card);
      });
      this.renderPairs("Cabecalho", result.headerFields);
      this.renderPairs("Parametros", result.parameterFields);
      this.renderTable(result);
      this.renderTotals(result);
      this.renderSections(result.sections);
      if (this.currentVerification) {
        this.renderVerification();
      }
    }

    renderPairs(title, items) {
      const list = global.CrudUtils.ensureArray(items);
      if (!list.length) {
        return;
      }
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text(title).appendTo(section);
      list.forEach(function(item) {
        const row = $("<article class=\"report-group\"></article>").appendTo(section);
        $("<div class=\"report-group-header\"></div>").append($("<strong></strong>").text(item.label || "Campo")).appendTo(row);
        $("<p class=\"report-footer-note\"></p>").text(item.value == null ? "" : item.value).appendTo(row);
      });
    }

    renderTable(result) {
      const table = result && result.table || {};
      const columns = global.CrudUtils.ensureArray(table.columns);
      const rows = global.CrudUtils.ensureArray(table.rows);
      if (!columns.length) {
        return;
      }
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Dados consultados").appendTo(section);
      $("<p class=\"report-footer-note\"></p>").text("Linhas: " + String(table.rowCount || rows.length || 0)).appendTo(section);
      const tableEl = $("<table class=\"report-table\"></table>").appendTo(section);
      const thead = $("<thead><tr></tr></thead>").appendTo(tableEl);
      columns.forEach(function(column) {
        $("<th></th>").text(column.title || column.label || column.field || "").appendTo(thead.find("tr"));
      });
      const tbody = $("<tbody></tbody>").appendTo(tableEl);
      rows.forEach(function(row) {
        const tr = $("<tr></tr>").appendTo(tbody);
        columns.forEach(function(column) {
          const value = row && row[column.field];
          $("<td></td>").text(value == null ? "" : String(value)).appendTo(tr);
        });
      });
    }

    renderTotals(result) {
      const totals = result && result.totals || {};
      const entries = Object.keys(totals);
      if (!entries.length) {
        return;
      }
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Totais").appendTo(section);
      const grid = $("<div class=\"report-summary-grid\"></div>").appendTo(section);
      entries.forEach(function(field) {
        const card = $("<article class=\"report-total-card\"></article>").appendTo(grid);
        $("<span></span>").text(field).appendTo(card);
        $("<strong></strong>").text(totals[field]).appendTo(card);
      });
    }

    renderSections(sections) {
      const list = global.CrudUtils.ensureArray(sections);
      if (!list.length) {
        return;
      }
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Observacoes").appendTo(section);
      list.forEach(function(item) {
        const host = $("<article class=\"report-group\"></article>").appendTo(section);
        $("<div class=\"report-group-header\"></div>").append($("<strong></strong>").text(item.title || "Secao")).appendTo(host);
        global.CrudUtils.ensureArray(item.lines).forEach(function(line) {
          $("<p class=\"report-footer-note\"></p>").text(line).appendTo(host);
        });
      });
    }

    renderVerification() {
      const verification = this.currentVerification && this.currentVerification.verification || {};
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Conferencia").appendTo(section);
      const grid = $("<div class=\"report-summary-grid\"></div>").appendTo(section);
      [
        { label: "Resultado", value: this.currentVerification && this.currentVerification.ok === true ? "Valido" : "Pendente" },
        { label: "Hash gravado", value: verification.recordedHash || "-" },
        { label: "Hash esperado", value: verification.expectedHash || "-" },
        { label: "Artefato salvo", value: verification.artifactStored ? "Sim" : "Nao" }
      ].forEach(function(item) {
        const card = $("<article class=\"report-summary-card\"></article>").appendTo(grid);
        $("<span></span>").text(item.label).appendTo(card);
        $("<strong></strong>").text(item.value).appendTo(card);
      });
    }

    renderEmpty(message) {
      this.outputHost.empty().append($("<div class=\"regulated-document-empty\"></div>").text(message || ""));
    }

    handleError(error, fallback) {
      const normalized = global.CrudUtils.unwrapError(error, fallback);
      global.CrudUtils.showMessage(normalized.message, "error");
    }
  }

  global.RegulatedDocumentEngine = RegulatedDocumentEngine;
})(window, window.jQuery);
