(function(global, $) {
  "use strict";

  function ReportAuthenticityPage(options) {
    this.options = options || {};
    this.root = $(this.options.root || "#report-authenticity-root");
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
  }

  ReportAuthenticityPage.prototype.init = function() {
    this.root.empty().addClass("report-authenticity-shell");
    this.render();
    const hash = this.readQueryHash();
    if (hash) {
      this.hashInput.val(hash);
      this.verify();
    }
  };

  ReportAuthenticityPage.prototype.render = function() {
    const page = $("<section class=\"report-authenticity-page\"></section>").appendTo(this.root);
    const header = $("<header class=\"report-authenticity-header\"></header>").appendTo(page);
    $("<p class=\"report-kicker\"></p>").text("Conferencia publica").appendTo(header);
    $("<h1></h1>").text("Autenticidade de relatorio").appendTo(header);
    $("<p class=\"report-subtitle\"></p>").text("Confere o hash gravado na emissao do relatorio sem expor o dataset completo.").appendTo(header);

    const search = $("<section class=\"report-authenticity-search\"></section>").appendTo(page);
    const field = $("<label class=\"report-field\"></label>").appendTo(search);
    $("<span></span>").text("Hash").appendTo(field);
    this.hashInput = $("<input type=\"text\" class=\"k-input k-textbox\">").appendTo(field);
    const actions = $("<div class=\"report-toolbar-actions\"></div>").appendTo(search);
    $("<button type=\"button\"></button>").appendTo(actions).kendoButton({
      icon: "search",
      themeColor: "primary",
      click: this.verify.bind(this)
    }).data("kendoButton").element.text("Conferir");

    this.statusHost = $("<section class=\"report-output\"></section>").appendTo(page);
    this.detailHost = $("<section class=\"report-output\"></section>").appendTo(page);
    this.renderIdle();
  };

  ReportAuthenticityPage.prototype.renderIdle = function() {
    this.statusHost.empty().append($("<div class=\"report-output-empty\"></div>").text("Informe o hash do relatorio para verificar a autenticidade."));
    this.detailHost.empty();
  };

  ReportAuthenticityPage.prototype.verify = function() {
    const hash = String(this.hashInput.val() || "").trim();
    if (!hash) {
      global.CrudUtils.showMessage("Informe o hash de autenticidade.", "warning");
      return;
    }
    this.statusHost.empty().append($("<div class=\"report-output-empty\"></div>").text("Conferindo..."));
    this.request("GET", "/api/public/report-authenticity/verify", { hash: hash }).then(function(payload) {
      this.renderResult(payload || {});
    }.bind(this)).catch(function(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel conferir a autenticidade.");
      this.statusHost.empty().append($("<section class=\"crud-message crud-message-error\"></section>").text(normalized.message));
      this.detailHost.empty();
    }.bind(this));
  };

  ReportAuthenticityPage.prototype.renderResult = function(payload) {
    this.statusHost.empty();
    this.detailHost.empty();
    const found = payload && payload.found === true;
    const enabled = payload && payload.enabled !== false;
    const statusCard = $("<article class=\"report-summary-card\"></article>").appendTo($("<div class=\"report-summary-grid\"></div>").appendTo(this.statusHost));
    $("<span></span>").text(enabled ? (found ? "Conferencia valida" : "Hash nao localizado") : "Conferencia indisponivel").appendTo(statusCard);
    $("<strong></strong>").text(payload && payload.message || "").appendTo(statusCard);

    const meta = $("<div class=\"report-metadata-grid\"></div>").appendTo(this.statusHost);
    this.appendMeta(meta, "Hash", payload.hash || "");
    this.appendMeta(meta, "Algoritmo", payload.authenticity && payload.authenticity.algorithm || "sha256");
    this.appendMeta(meta, "Registro", payload.authenticity && payload.authenticity.recorded === false ? "Nao confirmado" : "Registrado");
    this.appendMeta(meta, "Payload canonico", payload.authenticity && payload.authenticity.storage && payload.authenticity.storage.storeCanonicalPayload === false ? "Nao" : "Sim");
    this.appendMeta(meta, "Artefato salvo", payload.artifact && payload.artifact.stored ? "Sim" : "Nao");

    if (!found) {
      return;
    }

    const report = payload.report || {};
    const details = $("<div class=\"report-metadata-grid\"></div>").appendTo(this.detailHost);
    this.appendMeta(details, "Relatorio", report.title || report.reportId || "");
    this.appendMeta(details, "ScreenId", report.screenId || "");
    this.appendMeta(details, "Fonte", report.sourceType || "");
    this.appendMeta(details, "Formato", report.format || "");
    this.appendMeta(details, "Linhas", String(report.rowCount || 0));
    this.appendMeta(details, "Emitido em", report.generatedAt || "");
    if (payload.artifact && payload.artifact.stored) {
      this.appendMeta(details, "Formato salvo", payload.artifact.format || "");
      this.appendMeta(details, "Arquivo salvo", payload.artifact.fileName || "");
    }
  };

  ReportAuthenticityPage.prototype.appendMeta = function(parent, label, value) {
    const card = $("<article class=\"report-metadata-card\"></article>").appendTo(parent);
    $("<span></span>").text(label || "").appendTo(card);
    $("<strong></strong>").text(value == null ? "" : String(value)).appendTo(card);
  };

  ReportAuthenticityPage.prototype.readQueryHash = function() {
    try {
      const params = new URLSearchParams(global.location.search || "");
      return String(params.get("hash") || "").trim();
    } catch (_) {
      return "";
    }
  };

  ReportAuthenticityPage.prototype.request = function(method, url, data) {
    return this.httpClient.request({
      method: method,
      url: url,
      data: data || {}
    });
  };

  global.ReportAuthenticityPage = ReportAuthenticityPage;
})(window, window.jQuery);
