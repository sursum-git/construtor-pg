(function(global, $) {
  "use strict";

  class SpecialDocumentEngine {
    constructor(options) {
      this.options = options || {};
      this.root = $(this.options.root);
      this.httpClient = this.options.httpClient || new global.CrudHttpClient();
      this.configLoader = new global.CrudConfigLoader();
      this.config = this.options.config || {};
      this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
      this.definition = null;
      this.currentResult = null;
      this.parameterWidgets = {};
    }

    init() {
      return this.loadConfig()
        .then(() => this.loadDefinition())
        .then((definition) => {
          this.definition = this.normalizeDefinition(definition);
          this.render();
          return this.runDocument();
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
      return this.httpClient.request(global.CrudUtils.buildScreenDefinitionRequest(screenId, this.securityPolicy, "special_document"));
    }

    normalizeDefinition(definition) {
      const source = global.CrudUtils.clone(definition || {});
      if (String(source.pageType || "") !== "special_document") {
        throw global.CrudUtils.makeError("INVALID_SPECIAL_DOCUMENT_DEFINITION", "A definicao recebida nao e do tipo special_document.");
      }
      source.program = Object.assign({ title: "Documento especial", subtitle: "" }, source.program || {});
      source.specialDocument = Object.assign({
        renderEngine: "native",
        source: { type: "operational" },
        layout: { title: source.program.title || "Documento especial", subtitle: source.program.subtitle || "", notes: "" },
        outputs: { html: true, pdf: true }
      }, source.specialDocument || {});
      source.specialDocument.endpoints = Object.assign({}, source.specialDocument.endpoints || {}, source.dataSource && source.dataSource.api || source.api || {});
      return source;
    }

    render() {
      this.root.empty().addClass("report-shell");
      const screen = $("<section class=\"report-screen\"></section>").appendTo(this.root);
      const header = $("<header class=\"report-header\"></header>").appendTo(screen);
      $("<p class=\"report-kicker\"></p>").text("Documentos especiais").appendTo(header);
      $("<h1></h1>").text(this.definition.program.title || "Documento especial").appendTo(header);
      $("<p class=\"report-subtitle\"></p>").text(this.definition.program.subtitle || "Renderer fechado para documentos rigidos.").appendTo(header);
      const toolbar = $("<section class=\"report-toolbar\"></section>").appendTo(screen);
      $("<p class=\"report-toolbar-note\"></p>").text("Trilha separada de reports. O renderer continua fechado e sem layout livre.").appendTo(toolbar);
      const actions = $("<div class=\"report-toolbar-actions\"></div>").appendTo(toolbar);
      $("<button type=\"button\"></button>").appendTo(actions).kendoButton({ icon: "play", themeColor: "primary", click: () => this.runDocument(true) }).data("kendoButton").element.text("Gerar");
      if ((this.definition.specialDocument.outputs || {}).html !== false) {
        $("<button type=\"button\"></button>").appendTo(actions).kendoButton({ icon: "file", click: () => this.exportDocument("html") }).data("kendoButton").element.text("HTML");
      }
      if ((this.definition.specialDocument.outputs || {}).pdf !== false) {
        $("<button type=\"button\"></button>").appendTo(actions).kendoButton({ icon: "file-pdf", click: () => this.exportDocument("pdf") }).data("kendoButton").element.text("PDF");
      }
      this.renderParameters(screen);
      this.outputHost = $("<section class=\"report-output\"></section>").appendTo(screen);
    }

    renderParameters(screen) {
      const parameters = global.CrudUtils.ensureArray(this.definition.specialDocument && this.definition.specialDocument.parameters);
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
        let input;
        if (global.kendo && type === "enum" && Array.isArray(parameter.options) && parameter.options.length) {
          input = $("<input>").appendTo(field);
          input.kendoDropDownList({
            dataSource: parameter.options.map(function(item) {
              return {
                value: item.value,
                text: item.text || item.label || String(item.value || "")
              };
            }),
            dataValueField: "value",
            dataTextField: "text",
            optionLabel: "Todos"
          });
          this.parameterWidgets[key] = input.data("kendoDropDownList");
          return;
        }
        input = $("<input>").attr("type", this.parameterInputType(type)).appendTo(field);
        if (type === "date" && global.kendo) {
          input.kendoDatePicker({ format: "dd/MM/yyyy" });
          this.parameterWidgets[key] = input.data("kendoDatePicker");
          return;
        }
        this.parameterWidgets[key] = input;
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
      const parameters = global.CrudUtils.ensureArray(this.definition.specialDocument && this.definition.specialDocument.parameters);
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
      const endpointKey = key;
      const endpoints = this.definition.specialDocument && this.definition.specialDocument.endpoints || {};
      const fallback = {
        schema: "specialDocuments.schema",
        render: "specialDocuments.render",
        export: "specialDocuments.export"
      }[endpointKey] || endpointKey;
      const screenId = String(this.definition.screenId || this.definition.program && this.definition.program.screenId || this.options.screenId || "").trim();
      const resolved = global.CrudUtils.resolveEndpointForPolicy(endpoints[endpointKey] || { endpointId: fallback, method: "POST" }, fallback, screenId, this.securityPolicy);
      return this.httpClient.request(Object.assign({}, resolved, { method: "POST", data: payload || {} }));
    }

    runDocument(notify) {
      return this.runtimeRequest("render", {
        documentId: String(this.definition.program && this.definition.program.id || this.definition.screenId || "special-document"),
        sourceType: this.definition.specialDocument && this.definition.specialDocument.source && this.definition.specialDocument.source.type || "operational",
        documentKind: this.definition.specialDocument && this.definition.specialDocument.classification && this.definition.specialDocument.classification.documentKind || "special",
        renderEngine: this.definition.specialDocument && this.definition.specialDocument.renderEngine || "native",
        parameters: this.currentParameters()
      }).then((response) => {
        this.currentResult = response || {};
        this.renderResult();
        if (notify) {
          global.CrudUtils.showMessage("Documento especial atualizado.", "success");
        }
      });
    }

    exportDocument(format) {
      return this.runtimeRequest("export", {
        format: format,
        documentKind: this.definition.specialDocument && this.definition.specialDocument.classification && this.definition.specialDocument.classification.documentKind || "special",
        parameters: this.currentParameters()
      }).then((response) => {
        const bytes = Uint8Array.from(global.atob(String(response.contentBase64 || "")), function(char) {
          return char.charCodeAt(0);
        });
        const blob = new Blob([bytes], { type: String(response.contentType || "application/octet-stream") });
        const href = global.URL.createObjectURL(blob);
        const link = global.document.createElement("a");
        link.href = href;
        link.download = String(response.fileName || ("documento-especial." + format));
        global.document.body.appendChild(link);
        link.click();
        link.remove();
        global.URL.revokeObjectURL(href);
      });
    }

    renderResult() {
      const result = this.currentResult || {};
      const summary = $("<section></section>").appendTo(this.outputHost.empty());
      $("<h2></h2>").text("Resumo").appendTo(summary);
      const grid = $("<div class=\"report-summary-grid\"></div>").appendTo(summary);
      global.CrudUtils.ensureArray(result.summary).forEach(function(item) {
        const card = $("<article class=\"report-summary-card\"></article>").appendTo(grid);
        $("<span></span>").text(item.label || "").appendTo(card);
        $("<strong></strong>").text(item.value == null ? "" : item.value).appendTo(card);
      });
      this.renderPairs("Cabecalho", result.headerFields);
      this.renderPairs("Parametros", result.parameterFields);
      this.renderProfile(result);
      this.renderTable(result);
      this.renderTotals(result);
      if (this.definition.specialDocument && this.definition.specialDocument.layout && this.definition.specialDocument.layout.notes) {
        const notes = $("<section></section>").appendTo(this.outputHost);
        $("<h2></h2>").text("Notas do layout").appendTo(notes);
        $("<p class=\"report-footer-note\"></p>").text(this.definition.specialDocument.layout.notes).appendTo(notes);
      }
      const sections = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Secoes").appendTo(sections);
      global.CrudUtils.ensureArray(result.sections).forEach(function(item) {
        const host = $("<article class=\"report-group\"></article>").appendTo(sections);
        $("<div class=\"report-group-header\"></div>").append($("<strong></strong>").text(item.title || "Secao")).appendTo(host);
        global.CrudUtils.ensureArray(item.lines).forEach(function(line) {
          $("<p class=\"report-footer-note\"></p>").text(line).appendTo(host);
        });
      });
      const context = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Observacoes").appendTo(context);
      $("<p class=\"report-footer-note\"></p>").text(result.message || "Contrato base registrado para documentos especiais.").appendTo(context);
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

    renderProfile(result) {
      const profileType = String(result.profileType || "generic");
      const model = result && result.documentModel || {};
      if (profileType === "danfe") {
        const section = $("<section></section>").appendTo(this.outputHost);
        $("<h2></h2>").text("Documento fiscal").appendTo(section);
        const head = $("<div class=\"special-document-head\"></div>").appendTo(section);
        this.renderProfileCard(head, "Emitente", [
          model.issuer && model.issuer.name,
          model.issuer && model.issuer.document,
          [model.issuer && model.issuer.city, model.issuer && model.issuer.state].filter(Boolean).join(" / ")
        ]);
        this.renderProfileCard(head, "Destinatario", [
          model.recipient && model.recipient.name,
          model.recipient && model.recipient.document,
          [model.recipient && model.recipient.city, model.recipient && model.recipient.state].filter(Boolean).join(" / ")
        ]);
        const grid = $("<div class=\"special-document-grid\"></div>").appendTo(section);
        this.renderProfileCard(grid, "Numero / Serie", [String((model.invoice && model.invoice.number) || "") + " / " + String((model.invoice && model.invoice.series) || "")]);
        this.renderProfileCard(grid, "Protocolo", [model.invoice && model.invoice.protocol]);
        this.renderProfileCard(grid, "Emissao", [model.invoice && model.invoice.issueDate]);
        const barcode = $("<div class=\"special-document-barcode\"></div>").appendTo(section);
        $("<span></span>").text(model.invoice && model.invoice.accessKey || "").appendTo(barcode);
        return;
      }
      if (profileType === "boleto") {
        const section = $("<section></section>").appendTo(this.outputHost);
        $("<h2></h2>").text("Boleto").appendTo(section);
        const head = $("<div class=\"special-document-head\"></div>").appendTo(section);
        this.renderProfileCard(head, "Beneficiario", [model.beneficiary && model.beneficiary.name, model.beneficiary && model.beneficiary.document]);
        this.renderProfileCard(head, "Pagador", [model.payer && model.payer.name, model.payer && model.payer.document]);
        const grid = $("<div class=\"special-document-grid\"></div>").appendTo(section);
        this.renderProfileCard(grid, "Vencimento", [model.payment && model.payment.dueDate]);
        this.renderProfileCard(grid, "Nosso numero", [model.payment && model.payment.nossoNumero]);
        this.renderProfileCard(grid, "Valor", [String(model.payment && model.payment.amount || "")]);
        const barcode = $("<div class=\"special-document-barcode\"></div>").appendTo(section);
        $("<span></span>").text(model.payment && model.payment.barcode || "").appendTo(barcode);
        return;
      }
      if (profileType === "label") {
        const section = $("<section></section>").appendTo(this.outputHost);
        $("<h2></h2>").text("Etiquetas").appendTo(section);
        const grid = $("<div class=\"special-document-label-grid\"></div>").appendTo(section);
        global.CrudUtils.ensureArray(model.labels).forEach((label) => {
          const card = $("<article class=\"special-document-label-card\"></article>").appendTo(grid);
          $("<strong></strong>").text(label.recipient || "").appendTo(card);
          $("<div></div>").text(label.line1 || "").appendTo(card);
          $("<div></div>").text(label.line2 || "").appendTo(card);
          $("<div></div>").css({ marginTop: "10px", fontFamily: "monospace" }).text(label.code || "").appendTo(card);
        });
      }
    }

    renderProfileCard(host, title, lines) {
      const card = $("<article class=\"special-document-card\"></article>").appendTo(host);
      $("<strong></strong>").text(title || "").appendTo(card);
      global.CrudUtils.ensureArray(lines).forEach(function(line) {
        if (line) {
          $("<div></div>").text(String(line)).appendTo(card);
        }
      });
    }
  }

  global.SpecialDocumentEngine = SpecialDocumentEngine;
})(window, window.jQuery);
