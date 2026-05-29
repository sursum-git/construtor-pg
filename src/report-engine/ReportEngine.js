(function(global, $) {
  "use strict";

  class ReportEngine {
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
      this.currentFormat = "";
    }

    init() {
      this.renderLoading();
      return this.loadConfig()
        .then(() => this.loadDefinition())
        .then((definition) => {
          this.definition = this.normalizeDefinition(definition);
          this.applyDefinitionSecurity(this.definition);
          this.render();
          return this.runReport();
        })
        .then(() => this)
        .catch((error) => {
          this.renderError(error);
          throw error;
        });
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
        this.applyKendoTheme();
        return global.CrudUtils.loadLiteralBundle(this.config.literals || {}, this.httpClient).then(() => this.config);
      });
    }

    loadDefinition() {
      if (this.options.definition) {
        return Promise.resolve(global.CrudUtils.clone(this.options.definition));
      }

      const screenId = String(this.options.screenId || "").trim();
      if (screenId) {
        return this.httpClient.request(global.CrudUtils.buildScreenDefinitionRequest(screenId, this.securityPolicy, "report"));
      }

      const definitionUrl = String(this.options.definitionUrl || "").trim();
      if (!definitionUrl) {
        return Promise.reject(global.CrudUtils.makeError("REPORT_DEFINITION_SOURCE_MISSING", "Nenhuma definicao de relatorio foi informada."));
      }
      if (this.securityPolicy.definitionSource && this.securityPolicy.definitionSource.allowDefinitionUrl === false) {
        return Promise.reject(global.CrudUtils.makeError("REPORT_DEFINITION_URL_DISABLED", "Carregamento por definitionUrl livre desabilitado pela politica de seguranca."));
      }

      return this.httpClient.request({
        url: definitionUrl,
        method: "GET"
      });
    }

    normalizeDefinition(definition) {
      const source = global.CrudUtils.clone(definition || {});
      if (String(source.pageType || "") !== "report") {
        throw global.CrudUtils.makeError("INVALID_REPORT_DEFINITION", "A definicao recebida nao e do tipo report.", {
          pageType: source.pageType || ""
        });
      }
      source.program = Object.assign({
        title: "Relatorio",
        subtitle: ""
      }, source.program || {});
      source.dataSource = source.dataSource || {};
      source.dataSource.api = Object.assign({}, source.api || {}, source.dataSource.api || {});
      source.api = source.dataSource.api;
      source.report = Object.assign({
        source: { type: "operational" },
        query: { fields: [], parameters: [], filters: [], sort: [], limit: 200 },
        layout: {
          title: source.program.title || "Relatorio",
          subtitle: source.program.subtitle || "",
          blocks: [
            { id: "header", type: "header", title: "Cabecalho" },
            { id: "summary", type: "summary", title: "Resumo" },
            { id: "table", type: "table", title: "Detalhamento" },
            { id: "totals", type: "totals", title: "Totais" },
            { id: "footer", type: "footer", title: "Rodape" }
          ]
        },
        outputs: {
          html: true,
          print: true,
          pdf: true,
          pdfBrowser: true,
          excel: true,
          csv: true
        }
      }, source.report || {});
      source.report.query = Object.assign({
        fields: [],
        parameters: [],
        filters: [],
        sort: [],
        limit: 200
      }, source.report.query || {});
      source.report.query.fields = global.CrudUtils.ensureArray(source.report.query.fields);
      source.report.query.parameters = global.CrudUtils.ensureArray(source.report.query.parameters);
      source.report.query.filters = global.CrudUtils.ensureArray(source.report.query.filters);
      source.report.query.sort = global.CrudUtils.ensureArray(source.report.query.sort);
      source.report.layout.blocks = global.CrudUtils.ensureArray(source.report.layout && source.report.layout.blocks);
      source.report.endpoints = Object.assign({}, source.report.endpoints || {}, {
        schema: source.report.endpoints && source.report.endpoints.schema || source.api.schema,
        run: source.report.endpoints && source.report.endpoints.run || source.api.run,
        export: source.report.endpoints && source.report.endpoints.export || source.api.export
      });
      return source;
    }

    applyDefinitionSecurity(definition) {
      const api = definition.dataSource && definition.dataSource.api || definition.api || {};
      const endpoints = definition.report && definition.report.endpoints || {};
      const screenId = this.getScreenId();
      Object.keys(api).forEach((key) => {
        api[key] = this.resolveEndpoint(api[key], this.defaultEndpointId(key), screenId);
      });
      Object.keys(endpoints).forEach((key) => {
        endpoints[key] = this.resolveEndpoint(endpoints[key], this.defaultEndpointId(key), screenId);
      });
      definition.api = api;
      definition.dataSource.api = api;
      definition.report.endpoints = endpoints;
    }

    resolveEndpoint(endpoint, fallbackEndpointId, screenId) {
      if (!endpoint) {
        return endpoint;
      }
      const inlineAllowed = !this.securityPolicy || !this.securityPolicy.endpoints || this.securityPolicy.endpoints.allowInlineUrls !== false;
      if (typeof endpoint === "string") {
        if (inlineAllowed && global.CrudUtils.isAllowedDocumentUrl(endpoint)) {
          return { url: endpoint, method: "POST" };
        }
        return global.CrudUtils.resolveEndpointForPolicy({ endpointId: endpoint, method: "POST" }, fallbackEndpointId, screenId, this.securityPolicy);
      }
      const source = Object.assign({}, endpoint);
      if (source.url && inlineAllowed) {
        return Object.assign({ method: "POST" }, source, {
          method: String(source.method || "POST").toUpperCase()
        });
      }
      return global.CrudUtils.resolveEndpointForPolicy(source, fallbackEndpointId, screenId, this.securityPolicy);
    }

    defaultEndpointId(key) {
      return {
        schema: "reports.schema",
        run: "reports.run",
        export: "reports.export"
      }[key] || key;
    }

    getScreenId() {
      return String(this.definition && (this.definition.screenId || this.definition.program && this.definition.program.screenId) || this.options.screenId || "").trim();
    }

    applyKendoTheme() {
      const theme = this.config && this.config.theme || {};
      const href = theme.kendoTheme || "";
      const link = global.document && global.document.getElementById("kendo-theme-link");
      if (href && link && link.getAttribute("href") !== href) {
        link.setAttribute("href", href);
      }
    }

    renderLoading() {
      this.root.empty().append($("<section class=\"crud-message crud-message-info\"></section>").text("Carregando relatorio..."));
    }

    renderError(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar relatorio.");
      this.root.empty().append($("<section class=\"crud-message crud-message-error\"></section>").text(normalized.message));
    }

    render() {
      this.root.empty().addClass("report-shell");
      const screen = $("<section class=\"report-screen\"></section>").appendTo(this.root);
      if (this.options.hideHeader !== true) {
        this.renderHeader(screen);
      }
      this.renderToolbar(screen);
      this.renderParameters(screen);
      this.outputHost = $("<section class=\"report-output\"></section>").appendTo(screen);
      this.renderEmpty();
    }

    renderHeader(screen) {
      const program = this.definition.program || {};
      const header = $("<header class=\"report-header\"></header>").appendTo(screen);
      $("<p class=\"report-kicker\"></p>").text("Relatorios nativos").appendTo(header);
      $("<h1></h1>").text(program.title || "Relatorio").appendTo(header);
      if (program.subtitle) {
        $("<p class=\"report-subtitle\"></p>").text(program.subtitle).appendTo(header);
      }
    }

    renderToolbar(screen) {
      const toolbar = $("<section class=\"report-toolbar\"></section>").appendTo(screen);
      const note = $("<p class=\"report-toolbar-note\"></p>").text("Somente documentos compativeis com a v1. DANFE, boleto e formulario rigido ficam fora desta camada.").appendTo(toolbar);
      const actions = $("<div class=\"report-toolbar-actions\"></div>").appendTo(toolbar);

      $("<button type=\"button\"></button>").appendTo(actions).kendoButton({
        icon: "play",
        themeColor: "primary",
        click: () => this.runReport(true)
      }).data("kendoButton").element.text("Gerar");

      if ((this.definition.report.outputs || {}).print !== false) {
        $("<button type=\"button\"></button>").appendTo(actions).kendoButton({
          icon: "print",
          click: () => global.print()
        }).data("kendoButton").element.text("Imprimir");
      }
      if ((this.definition.report.outputs || {}).pdf === true || (this.definition.report.outputs || {}).pdfBrowser === true) {
        $("<button type=\"button\"></button>").appendTo(actions).kendoButton({
          icon: "file-pdf",
          click: () => this.exportReport("pdf")
        }).data("kendoButton").element.text("PDF");
      }

      const exportActions = $("<div class=\"report-export-actions\"></div>").appendTo(actions);
      if ((this.definition.report.outputs || {}).excel === true) {
        $("<button type=\"button\"></button>").appendTo(exportActions).kendoButton({
          icon: "file-excel",
          click: () => this.exportReport("excel")
        }).data("kendoButton").element.text("Excel");
      }
      if ((this.definition.report.outputs || {}).csv === true) {
        $("<button type=\"button\"></button>").appendTo(exportActions).kendoButton({
          icon: "file-csv",
          click: () => this.exportReport("csv")
        }).data("kendoButton").element.text("CSV");
      }
    }

    renderParameters(screen) {
      const host = screen.find(".report-parameters");
      if (host.length) {
        if (global.kendo) {
          global.kendo.destroy(host);
        }
        host.remove();
      }
      const parameters = global.CrudUtils.ensureArray(this.definition.report && this.definition.report.query && this.definition.report.query.parameters);
      if (!parameters.length) {
        return;
      }
      const wrapper = $("<section class=\"report-parameters\"></section>").insertBefore(this.outputHost || screen.children().last());
      $("<h2></h2>").text("Parametros").appendTo(wrapper);
      const grid = $("<div class=\"report-parameters-grid\"></div>").appendTo(wrapper);
      this.parameterWidgets = {};
      parameters.forEach((parameter) => {
        const field = $("<label class=\"report-field\"></label>").appendTo(grid);
        $("<span></span>").text(parameter.label || parameter.id || "Parametro").appendTo(field);
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
          this.parameterWidgets[String(parameter.id || parameter.field)] = input.data("kendoDropDownList");
          return;
        }
        input = $("<input>").attr("type", this.parameterInputType(type)).appendTo(field);
        if (type === "date" && global.kendo) {
          input.kendoDatePicker({ format: "dd/MM/yyyy" });
          this.parameterWidgets[String(parameter.id || parameter.field)] = input.data("kendoDatePicker");
          return;
        }
        this.parameterWidgets[String(parameter.id || parameter.field)] = input;
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
      const parameters = global.CrudUtils.ensureArray(this.definition.report && this.definition.report.query && this.definition.report.query.parameters);
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
      const endpoints = this.definition.report && this.definition.report.endpoints || {};
      const fallback = this.defaultEndpointId(key);
      const endpoint = endpoints[key] || this.definition.api && this.definition.api[key];
      const resolved = this.resolveEndpoint(endpoint || fallback, fallback, this.getScreenId());
      if (!resolved) {
        return Promise.reject(global.CrudUtils.makeError("REPORT_RUNTIME_ENDPOINT_UNRESOLVED", "Nao foi possivel resolver o endpoint runtime do relatorio."));
      }
      const request = Object.assign({}, resolved, {
        method: String(resolved.method || "POST").toUpperCase(),
        data: payload
      });
      return this.httpClient.request(request);
    }

    buildPayload(extra) {
      const report = this.definition.report || {};
      const query = report.query || {};
      return Object.assign({
        reportId: String(this.definition.program && this.definition.program.id || this.getScreenId()),
        sourceType: report.source && report.source.type || "operational",
        parameters: this.currentParameters(),
        sort: global.CrudUtils.clone(query.sort || []),
        limit: Number(query.limit || 200)
      }, extra || {});
    }

    runReport(notify) {
      return this.runtimeRequest("run", this.buildPayload()).then((response) => {
        this.currentResult = response || null;
        this.renderResult();
        if (notify) {
          global.CrudUtils.showMessage("Relatorio atualizado.", "success");
        }
        return response;
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel gerar o relatorio.");
        if (notify) {
          global.CrudUtils.showMessage(normalized.message, "error");
        }
        this.renderError(normalized);
        throw error;
      });
    }

    exportReport(format) {
      return this.runtimeRequest("export", this.buildPayload({ format: format })).then((response) => {
        const extension = format === "excel" ? "xlsx" : format;
        const fallbackType = format === "excel"
          ? "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          : (format === "pdf" ? "application/pdf" : "text/csv;charset=utf-8");
        const name = String(response.fileName || ("relatorio." + extension)).trim();
        const contentType = String(response.contentType || fallbackType).trim();
        const contentBase64 = String(response.contentBase64 || "").trim();
        if (!contentBase64) {
          throw global.CrudUtils.makeError("REPORT_EXPORT_EMPTY", "O runtime nao devolveu conteudo para exportacao.");
        }
        const bytes = Uint8Array.from(global.atob(contentBase64), function(char) {
          return char.charCodeAt(0);
        });
        const blob = new Blob([bytes], { type: contentType });
        const href = global.URL.createObjectURL(blob);
        const link = global.document.createElement("a");
        link.href = href;
        link.download = name;
        global.document.body.appendChild(link);
        link.click();
        link.remove();
        global.URL.revokeObjectURL(href);
        const authenticity = response && response.authenticity;
        if (authenticity && authenticity.hash) {
          global.CrudUtils.showMessage("Arquivo gerado. " + String(authenticity.footerLabel || "Codigo de autenticidade") + ": " + String(authenticity.hash), "success");
        } else {
          global.CrudUtils.showMessage("Arquivo gerado.", "success");
        }
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel exportar o relatorio.");
        global.CrudUtils.showMessage(normalized.message, "error");
        throw error;
      });
    }

    renderEmpty() {
      this.outputHost.empty().append($("<div class=\"report-output-empty\"></div>").text("Preencha os parametros e gere o relatorio."));
    }

    renderResult() {
      const result = this.currentResult || {};
      const rows = global.CrudUtils.ensureArray(result.rows || result.data);
      const columns = global.CrudUtils.ensureArray(result.columns);
      const groups = global.CrudUtils.ensureArray(result.groups);
      const totals = result.totals || {};
      const summary = global.CrudUtils.ensureArray(result.summary);
      const metadata = global.CrudUtils.ensureArray(result.metadata);

      this.outputHost.empty();
      this.renderSummary(summary, rows, result);
      this.renderMetadata(metadata, result);
      if (groups.length) {
        this.renderGroups(groups, columns);
      } else {
        this.renderTable(columns, rows, this.outputHost, "Detalhamento");
      }
      this.renderTotals(totals, columns);
      this.renderFooter(result);
    }

    renderSummary(summary, rows, result) {
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Resumo").appendTo(section);
      const grid = $("<div class=\"report-summary-grid\"></div>").appendTo(section);
      const items = summary.length ? summary : [
        { label: "Linhas", value: result.total || rows.length || 0 },
        { label: "Gerado em", value: result.generatedAt || "" }
      ];
      items.forEach(function(item) {
        const card = $("<article class=\"report-summary-card\"></article>").appendTo(grid);
        $("<span></span>").text(item.label || item.title || "Indicador").appendTo(card);
        $("<strong></strong>").text(item.formattedValue || item.value || "").appendTo(card);
      });
    }

    renderMetadata(metadata, result) {
      const cards = metadata.length ? metadata : [
        { label: "Fonte", value: result.sourceType || "" },
        { label: "Formato", value: "HTML / impressao" }
      ];
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Contexto").appendTo(section);
      const grid = $("<div class=\"report-metadata-grid\"></div>").appendTo(section);
      cards.forEach(function(item) {
        const card = $("<article class=\"report-metadata-card\"></article>").appendTo(grid);
        $("<span></span>").text(item.label || item.title || "Item").appendTo(card);
        $("<strong></strong>").text(item.value || "").appendTo(card);
      });
    }

    renderGroups(groups, columns) {
      const section = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Grupos").appendTo(section);
      this.renderGroupNodes(groups, columns, section, 1);
    }

    renderGroupNodes(groups, columns, parent, level) {
      global.CrudUtils.ensureArray(groups).forEach((group) => {
        const host = $("<article class=\"report-group\"></article>").appendTo(parent).attr("data-level", String(level || 1));
        const header = $("<div class=\"report-group-header\"></div>").appendTo(host);
        $("<strong></strong>").text(group.label || group.key || "Grupo").appendTo(header);
        $("<span></span>").text("Linhas: " + Number(group.rowCount || global.CrudUtils.ensureArray(group.rows).length || 0)).appendTo(header);
        if (Array.isArray(group.children) && group.children.length) {
          this.renderGroupNodes(group.children, columns, host, (level || 1) + 1);
        } else {
          this.renderTable(columns, group.rows || [], host, "");
        }
        if (group.showSubtotal !== false && group.totals && Object.keys(group.totals).length) {
          this.renderTotals(group.totals, columns, host);
        }
      });
    }

    renderTable(columns, rows, parent, title) {
      const section = $("<section></section>").appendTo(parent);
      if (title) {
        $("<h3></h3>").text(title).appendTo(section);
      }
      const wrap = $("<div class=\"report-table-wrap\"></div>").appendTo(section);
      const table = $("<table class=\"report-table\"></table>").appendTo(wrap);
      const head = $("<thead><tr></tr></thead>").appendTo(table).find("tr");
      columns.forEach((column) => {
        $("<th></th>").text(column.title || column.label || column.field || "").toggleClass("report-align-right", String(column.align || "").toLowerCase() === "right").appendTo(head);
      });
      const body = $("<tbody></tbody>").appendTo(table);
      rows.forEach((row) => {
        const tr = $("<tr></tr>").appendTo(body);
        columns.forEach((column) => {
          const value = row[column.field];
          $("<td></td>").toggleClass("report-align-right", String(column.align || "").toLowerCase() === "right").text(this.formatValue(value, column)).appendTo(tr);
        });
      });
    }

    renderTotals(totals, columns, parent) {
      const host = parent || this.outputHost;
      const entries = Object.keys(totals || {}).filter((key) => totals[key] !== null && totals[key] !== undefined);
      if (!entries.length) {
        return;
      }
      const section = $("<section></section>").appendTo(host);
      $("<h2></h2>").text("Totais").appendTo(section);
      const grid = $("<div class=\"report-totals-grid\"></div>").appendTo(section);
      entries.forEach((field) => {
        const column = columns.find(function(item) {
          return item.field === field;
        }) || { field: field, title: field };
        const card = $("<article class=\"report-total-card\"></article>").appendTo(grid);
        $("<span></span>").text(column.title || column.label || field).appendTo(card);
        $("<strong></strong>").text(this.formatValue(totals[field], column)).appendTo(card);
      });
    }

    renderFooter(result) {
      const layout = this.definition.report && this.definition.report.layout || {};
      const footer = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Rodape").appendTo(footer);
      $("<p class=\"report-footer-note\"></p>").text(layout.footerText || ("Emitido em " + this.formatDateTime(result.generatedAt || new Date().toISOString()))).appendTo(footer);
      const authenticity = result && result.authenticity || null;
      if (authenticity && authenticity.hash) {
        const meta = $("<div class=\"manual-meta\"></div>").appendTo(footer);
        $("<span class=\"manual-badge\"></span>").text(String(authenticity.footerLabel || "Codigo de autenticidade") + ": " + String(authenticity.hash)).appendTo(meta);
        if (authenticity.verificationUrl) {
          $("<a class=\"k-button k-button-solid-base\" target=\"_blank\" rel=\"noopener noreferrer\"></a>")
            .attr("href", String(authenticity.verificationUrl))
            .text("Conferir autenticidade")
            .appendTo(footer);
        }
      }
    }

    formatDateTime(value) {
      try {
        return global.kendo.toString(new Date(value), "g");
      } catch (_) {
        return String(value || "");
      }
    }

    formatValue(value, column) {
      if (value == null) {
        return "";
      }
      const format = column && column.format;
      if (format && global.kendo && typeof global.kendo.toString === "function") {
        try {
          return global.kendo.toString(value, format);
        } catch (_) {
        }
      }
      return String(value);
    }
  }

  global.ReportEngine = ReportEngine;
})(window, window.jQuery);
