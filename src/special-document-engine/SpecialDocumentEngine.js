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
        renderEngine: "native_stub",
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
      $("<p class=\"report-toolbar-note\"></p>").text("Trilha separada de reports. Esta etapa registra contrato, fonte e auditoria sem layout livre.").appendTo(toolbar);
      const actions = $("<div class=\"report-toolbar-actions\"></div>").appendTo(toolbar);
      $("<button type=\"button\"></button>").appendTo(actions).kendoButton({ icon: "play", themeColor: "primary", click: () => this.runDocument(true) }).data("kendoButton").element.text("Gerar");
      if ((this.definition.specialDocument.outputs || {}).html !== false) {
        $("<button type=\"button\"></button>").appendTo(actions).kendoButton({ icon: "file", click: () => this.exportDocument("html") }).data("kendoButton").element.text("HTML");
      }
      if ((this.definition.specialDocument.outputs || {}).pdf !== false) {
        $("<button type=\"button\"></button>").appendTo(actions).kendoButton({ icon: "file-pdf", click: () => this.exportDocument("pdf") }).data("kendoButton").element.text("PDF");
      }
      this.outputHost = $("<section class=\"report-output\"></section>").appendTo(screen);
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
        sourceType: this.definition.specialDocument && this.definition.specialDocument.source && this.definition.specialDocument.source.type || "operational"
      }).then((response) => {
        this.currentResult = response || {};
        this.renderResult();
        if (notify) {
          global.CrudUtils.showMessage("Documento especial atualizado.", "success");
        }
      });
    }

    exportDocument(format) {
      return this.runtimeRequest("export", { format: format }).then((response) => {
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
      [
        { label: "Tipo", value: result.documentKind || "" },
        { label: "Engine", value: result.renderEngine || "native_stub" },
        { label: "Fonte", value: result.sourceType || "" }
      ].forEach(function(item) {
        const card = $("<article class=\"report-summary-card\"></article>").appendTo(grid);
        $("<span></span>").text(item.label).appendTo(card);
        $("<strong></strong>").text(item.value).appendTo(card);
      });
      const context = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Contexto").appendTo(context);
      $("<p class=\"report-footer-note\"></p>").text(result.message || "Contrato base registrado para documentos especiais.").appendTo(context);
      const sections = $("<section></section>").appendTo(this.outputHost);
      $("<h2></h2>").text("Secoes").appendTo(sections);
      global.CrudUtils.ensureArray(result.sections).forEach(function(item) {
        const host = $("<article class=\"report-group\"></article>").appendTo(sections);
        $("<div class=\"report-group-header\"></div>").append($("<strong></strong>").text(item.title || "Secao")).appendTo(host);
        global.CrudUtils.ensureArray(item.lines).forEach(function(line) {
          $("<p class=\"report-footer-note\"></p>").text(line).appendTo(host);
        });
      });
    }
  }

  global.SpecialDocumentEngine = SpecialDocumentEngine;
})(window, window.jQuery);
