(function(global) {
  "use strict";

  function AnalyticsPipelinesAdmin(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#analytics-pipelines-admin-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.bootstrapData = {};
    this.entries = [];
    this.currentEntry = null;
    this.currentStatus = null;
    this.currentVersions = null;
    this.currentLogs = null;
  }

  AnalyticsPipelinesAdmin.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.loadBootstrap();
  };

  AnalyticsPipelinesAdmin.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell system-updates-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Pipelines analytics").appendTo(title);
    global.jQuery("<p></p>").text("Operacao basica dos pipelines semanticos publicados na camada BI.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    const toolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(shell);
    this.runButton = this.createButton(toolbar, "Executar", "play", this.handleRun.bind(this));
    this.publishButton = this.createButton(toolbar, "Publicar", "upload", this.handlePublish.bind(this));
    this.rollbackButton = this.createButton(toolbar, "Rollback", "undo", this.handleRollback.bind(this));
    this.refreshButton = this.createButton(toolbar, "Atualizar", "reload", this.loadRows.bind(this));
    const rollbackField = global.jQuery("<label class=\"program-builder-field\"></label>").appendTo(toolbar);
    global.jQuery("<span></span>").text("Versao rollback").appendTo(rollbackField);
    this.rollbackInput = global.jQuery("<input type=\"number\" min=\"1\" class=\"program-builder-mini-input\">").appendTo(rollbackField);

    const grid = global.jQuery("<div class=\"program-governance-admin-grid\"></div>").appendTo(shell);
    const left = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    const right = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.listCard = this.createCard(left, "Pipelines");
    this.listElement = global.jQuery("<div></div>").appendTo(this.listCard.body);
    this.detailCard = this.createCard(right, "Detalhe");
    this.detailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(this.detailCard.body);
    this.detailElement.text("Selecione um pipeline.");
    this.versionsCard = this.createCard(right, "Versoes e logs");
    this.versionsElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(this.versionsCard.body);
    this.versionsElement.text("Selecione um pipeline.");
  };

  AnalyticsPipelinesAdmin.prototype.loadBootstrap = function() {
    this.setStatus("Carregando");
    return this.request("GET", "/api/admin/analytics-pipelines/bootstrap").then(function(payload) {
      this.bootstrapData = payload || {};
      return this.loadRows().then(function() {
        this.setStatus("Pronto");
        return payload;
      }.bind(this));
    }.bind(this)).catch(function(error) {
      this.setStatus("Falha");
      this.showError(error, "Nao foi possivel carregar a administracao de pipelines analytics.");
      throw error;
    }.bind(this));
  };

  AnalyticsPipelinesAdmin.prototype.loadRows = function() {
    return this.request("GET", "/api/admin/analytics-pipelines/rows").then(function(payload) {
      this.entries = global.CrudUtils.ensureArray(payload.items || []);
      this.currentEntry = this.entries[0] || null;
      this.renderGrid();
      return this.loadSelectedDetails();
    }.bind(this)).catch(function(error) {
      this.showError(error, "Nao foi possivel carregar os pipelines analytics.");
      throw error;
    }.bind(this));
  };

  AnalyticsPipelinesAdmin.prototype.loadSelectedDetails = function() {
    if (!this.currentEntry) {
      this.renderDetail();
      return Promise.resolve(null);
    }
    const query = {
      screenId: this.currentEntry.screenId,
      pipelineId: this.currentEntry.pipelineId
    };
    return Promise.all([
      this.request("GET", "/api/admin/analytics-pipelines/status", query),
      this.request("GET", "/api/admin/analytics-pipelines/versions", query),
      this.request("GET", "/api/admin/analytics-pipelines/logs", query)
    ]).then(function(results) {
      this.currentStatus = results[0] || null;
      this.currentVersions = results[1] || null;
      this.currentLogs = results[2] || null;
      this.renderDetail();
      return results;
    }.bind(this)).catch(function(error) {
      this.showError(error, "Nao foi possivel consultar o detalhe do pipeline.");
      this.renderDetail();
      return null;
    }.bind(this));
  };

  AnalyticsPipelinesAdmin.prototype.renderGrid = function() {
    if (this.gridWidget) {
      this.gridWidget.destroy();
      this.listElement.empty();
    }
    this.listElement.kendoGrid({
      dataSource: { data: this.entries, pageSize: 20 },
      height: 520,
      pageable: true,
      sortable: true,
      selectable: "row",
      columns: [
        { field: "screenId", title: "Tela", width: 180 },
        { field: "pipelineId", title: "Pipeline", width: 160 },
        { field: "title", title: "Titulo", width: 220 },
        { field: "enabled", title: "Ativo", width: 90, template: "#= enabled ? 'Sim' : 'Nao' #" },
        { field: "publishedDatasetId", title: "Dataset publicado", width: 180 },
        { field: "latestExecution.status", title: "Ultima execucao", width: 150 },
        { field: "activeVersion.versionNo", title: "Versao ativa", width: 110 }
      ],
      change: function() {
        const item = this.dataItem(this.select());
        this.currentEntry = item ? item.toJSON ? item.toJSON() : item : null;
        this.loadSelectedDetails();
      }.bind(this)
    });
    this.gridWidget = this.listElement.data("kendoGrid");
    if (this.entries.length) {
      this.gridWidget.select(this.gridWidget.tbody.children().first());
    }
  };

  AnalyticsPipelinesAdmin.prototype.renderDetail = function() {
    this.detailElement.empty();
    this.versionsElement.empty();
    if (!this.currentEntry) {
      this.detailElement.text("Nenhum pipeline encontrado.");
      this.versionsElement.text("Nenhum pipeline encontrado.");
      return;
    }
    global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
      .text(JSON.stringify({
        pipeline: this.currentEntry,
        status: this.currentStatus
      }, null, 2))
      .appendTo(this.detailElement);
    global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
      .text(JSON.stringify({
        versions: this.currentVersions,
        logs: this.currentLogs
      }, null, 2))
      .appendTo(this.versionsElement);
  };

  AnalyticsPipelinesAdmin.prototype.handleRun = function() {
    if (!this.currentEntry) {
      return;
    }
    return this.request("POST", "/api/admin/analytics-pipelines/run", {
      screenId: this.currentEntry.screenId,
      pipelineId: this.currentEntry.pipelineId,
      sync: true
    }).then(function(response) {
      if (response && response.executionId) {
        this.lastExecutionId = response.executionId;
      }
      return this.loadRows();
    }.bind(this));
  };

  AnalyticsPipelinesAdmin.prototype.handlePublish = function() {
    if (!this.currentEntry) {
      return;
    }
    const executionId = this.currentStatus && this.currentStatus.latestExecution && this.currentStatus.latestExecution.executionId || this.lastExecutionId || "";
    if (!executionId) {
      global.CrudUtils.showMessage("Execute o pipeline antes de publicar a versao.", "warning");
      return Promise.resolve(null);
    }
    return this.request("POST", "/api/admin/analytics-pipelines/publish", {
      screenId: this.currentEntry.screenId,
      pipelineId: this.currentEntry.pipelineId,
      executionId: executionId
    }).then(function() {
      return this.loadRows();
    }.bind(this));
  };

  AnalyticsPipelinesAdmin.prototype.handleRollback = function() {
    if (!this.currentEntry) {
      return;
    }
    const versionNo = Math.max(1, Number(this.rollbackInput.val() || 0) || 0);
    if (!versionNo) {
      global.CrudUtils.showMessage("Informe a versao para rollback.", "warning");
      return Promise.resolve(null);
    }
    return this.request("POST", "/api/admin/analytics-pipelines/rollback", {
      screenId: this.currentEntry.screenId,
      pipelineId: this.currentEntry.pipelineId,
      versionNo: versionNo
    }).then(function() {
      return this.loadRows();
    }.bind(this));
  };

  AnalyticsPipelinesAdmin.prototype.createCard = function(parent, title) {
    const card = global.jQuery("<section class=\"program-builder-card\"></section>").appendTo(parent);
    global.jQuery("<h2></h2>").text(title).appendTo(card);
    return { element: card, body: card };
  };

  AnalyticsPipelinesAdmin.prototype.createButton = function(parent, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\"></button>").text(text).appendTo(parent);
    button.kendoButton({ icon: icon, click: handler });
    return button;
  };

  AnalyticsPipelinesAdmin.prototype.request = function(method, url, data) {
    return this.httpClient.request({
      method: method,
      url: url,
      data: data || {}
    });
  };

  AnalyticsPipelinesAdmin.prototype.setStatus = function(text) {
    this.statusBadge.text(text);
  };

  AnalyticsPipelinesAdmin.prototype.showError = function(error, fallbackMessage) {
    const normalized = global.CrudUtils.unwrapError(error, fallbackMessage);
    global.CrudUtils.showMessage(normalized.message || fallbackMessage, "error");
  };

  global.AnalyticsPipelinesAdmin = AnalyticsPipelinesAdmin;
})(window);
