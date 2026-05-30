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
    this.currentImpact = null;
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
    global.jQuery("<p></p>").text("Execute, revise o working dataset e publique apenas a versao ativa do dataset de negocio.").appendTo(title);
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
    this.detailCard = this.createCard(right, "Revisao de publicacao");
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
      this.request("GET", "/api/admin/analytics-pipelines/logs", query),
      this.request("GET", "/api/admin/analytics-pipelines/impact", query)
    ]).then(function(results) {
      this.currentStatus = results[0] || null;
      this.currentVersions = results[1] || null;
      this.currentLogs = results[2] || null;
      this.currentImpact = results[3] || null;
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
    const working = this.extractWorkingDataset();
    const activeVersion = this.currentVersions && this.currentVersions.activeVersion || null;
    const published = activeVersion && activeVersion.data || null;
    const comparison = this.compareDatasets(activeVersion, published, working);

    this.renderDatasetSummary(this.detailElement, "Pipeline", {
      screenId: this.currentEntry.screenId,
      pipelineId: this.currentEntry.pipelineId,
      title: this.currentEntry.title,
      publishedDatasetId: this.currentEntry.publishedDatasetId
    });
    this.renderDatasetSummary(this.detailElement, "Working dataset", working);
    this.renderDatasetSummary(this.detailElement, "Dataset publicado ativo", published, activeVersion);
    this.renderComparison(this.detailElement, comparison, activeVersion);
    this.renderImpact(this.detailElement, this.currentImpact, comparison);
    this.renderVersionHistory(this.versionsElement, this.currentVersions, this.currentLogs);
  };

  AnalyticsPipelinesAdmin.prototype.extractWorkingDataset = function() {
    if (!this.currentStatus || !this.currentStatus.latestExecution || !this.currentStatus.latestExecution.workingDataset) {
      return null;
    }
    return this.currentStatus.latestExecution.workingDataset;
  };

  AnalyticsPipelinesAdmin.prototype.renderDatasetSummary = function(container, title, dataset, meta) {
    const block = global.jQuery("<section class=\"program-builder-analytics-runtime-block\"></section>").appendTo(container);
    global.jQuery("<h5></h5>").text(title).appendTo(block);
    if (!dataset) {
      global.jQuery("<p class=\"program-builder-empty\"></p>").text("Sem dados carregados.").appendTo(block);
      return;
    }
    if (dataset.pipelineId || dataset.publishedDatasetId) {
      global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
        .text("Tela: " + String(dataset.screenId || "") + " | pipeline: " + String(dataset.pipelineId || "") + " | dataset publicado: " + String(dataset.publishedDatasetId || ""))
        .appendTo(block);
      return;
    }
    const columns = global.CrudUtils.ensureArray(dataset.columns || []);
    const rows = global.CrudUtils.ensureArray(dataset.rows || []);
    global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
      .text("Linhas: " + String(rows.length) + " | colunas: " + String(columns.length))
      .appendTo(block);
    if (meta && meta.versionNo) {
      global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
        .text("Versao ativa: " + String(meta.versionNo) + " | publicada em: " + String(meta.publishedAt || "-") + " | execucao: " + String(meta.executionId || "-"))
        .appendTo(block);
    }
    global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
      .text(JSON.stringify({
        columns: columns,
        rows: rows.slice(0, 10)
      }, null, 2))
      .appendTo(block);
  };

  AnalyticsPipelinesAdmin.prototype.compareDatasets = function(activeVersion, published, working) {
    const publishedColumns = global.CrudUtils.ensureArray(published && published.columns || []);
    const workingColumns = global.CrudUtils.ensureArray(working && working.columns || []);
    const publishedMap = {};
    const workingMap = {};
    publishedColumns.forEach(function(column) {
      publishedMap[String(column.field || column.id || "")] = column;
    });
    workingColumns.forEach(function(column) {
      workingMap[String(column.field || column.id || "")] = column;
    });
    const added = [];
    const removed = [];
    const changedTypes = [];
    Object.keys(workingMap).forEach(function(key) {
      if (!publishedMap[key]) {
        added.push(key);
      }
    });
    Object.keys(publishedMap).forEach(function(key) {
      if (!workingMap[key]) {
        removed.push(key);
        return;
      }
      const before = String(publishedMap[key].type || "");
      const after = String(workingMap[key].type || "");
      if (before !== after) {
        changedTypes.push({ field: key, before: before, after: after });
      }
    });
    return {
      activeVersionNo: activeVersion && activeVersion.versionNo || null,
      publishedRows: global.CrudUtils.ensureArray(published && published.rows || []).length,
      workingRows: global.CrudUtils.ensureArray(working && working.rows || []).length,
      rowDelta: global.CrudUtils.ensureArray(working && working.rows || []).length - global.CrudUtils.ensureArray(published && published.rows || []).length,
      columnDelta: workingColumns.length - publishedColumns.length,
      addedColumns: added,
      removedColumns: removed,
      changedTypes: changedTypes
    };
  };

  AnalyticsPipelinesAdmin.prototype.renderComparison = function(container, comparison, activeVersion) {
    const block = global.jQuery("<section class=\"program-builder-analytics-runtime-block\"></section>").appendTo(container);
    global.jQuery("<h5></h5>").text("Comparacao working x publicado").appendTo(block);
    if (!comparison || !comparison.activeVersionNo) {
      global.jQuery("<p class=\"program-builder-empty\"></p>").text("Publique ao menos uma versao para comparar o working dataset com a versao ativa.").appendTo(block);
      return;
    }
    global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
      .text("Versao ativa: " + String(comparison.activeVersionNo) + " | delta de linhas: " + String(comparison.rowDelta) + " | delta de colunas: " + String(comparison.columnDelta))
      .appendTo(block);
    if (!comparison.addedColumns.length && !comparison.removedColumns.length && !comparison.changedTypes.length) {
      global.jQuery("<div class=\"program-builder-diagnostic-item is-success\"></div>")
        .append(global.jQuery("<strong></strong>").text("Contrato estavel"))
        .append(global.jQuery("<span></span>").text("O working dataset nao removeu colunas nem alterou tipos da versao ativa."))
        .appendTo(block);
      return;
    }
    if (comparison.removedColumns.length) {
      global.jQuery("<div class=\"program-builder-diagnostic-item is-warn\"></div>")
        .append(global.jQuery("<strong></strong>").text("Colunas removidas"))
        .append(global.jQuery("<span></span>").text(comparison.removedColumns.join(", ")))
        .appendTo(block);
    }
    if (comparison.changedTypes.length) {
      global.jQuery("<div class=\"program-builder-diagnostic-item is-warn\"></div>")
        .append(global.jQuery("<strong></strong>").text("Tipos alterados"))
        .append(global.jQuery("<span></span>").text(comparison.changedTypes.map(function(item) {
          return item.field + " (" + item.before + " -> " + item.after + ")";
        }).join(", ")))
        .appendTo(block);
    }
    if (comparison.addedColumns.length) {
      global.jQuery("<div class=\"program-builder-diagnostic-item is-info\"></div>")
        .append(global.jQuery("<strong></strong>").text("Colunas novas"))
        .append(global.jQuery("<span></span>").text(comparison.addedColumns.join(", ")))
        .appendTo(block);
    }
    if (activeVersion) {
      global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
        .text("Execucao da versao ativa: " + String(activeVersion.executionId || "-") + " | publicada em: " + String(activeVersion.publishedAt || "-"))
        .appendTo(block);
    }
  };

  AnalyticsPipelinesAdmin.prototype.renderImpact = function(container, impact, comparison) {
    const block = global.jQuery("<section class=\"program-builder-analytics-runtime-block\"></section>").appendTo(container);
    global.jQuery("<h5></h5>").text("Impacto do publish").appendTo(block);
    if (!impact) {
      global.jQuery("<p class=\"program-builder-empty\"></p>").text("Nao foi possivel calcular o impacto desta publicacao.").appendTo(block);
      return;
    }
    global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
      .text("Datasets consumidores: " + String(impact.summary && impact.summary.datasets || 0) + " | views afetadas: " + String(impact.summary && impact.summary.views || 0) + " | reports afetados: " + String(impact.summary && impact.summary.reports || 0))
      .appendTo(block);
    if (comparison && (comparison.removedColumns.length || comparison.changedTypes.length) && ((impact.summary && impact.summary.views) || (impact.summary && impact.summary.reports))) {
      global.jQuery("<div class=\"program-builder-diagnostic-item is-warn\"></div>")
        .append(global.jQuery("<strong></strong>").text("Risco de quebra de contrato"))
        .append(global.jQuery("<span></span>").text("Ha consumidores publicados e o working dataset remove colunas ou altera tipos. Revise antes de publicar."))
        .appendTo(block);
    }
    if (global.CrudUtils.ensureArray(impact.affectedViews || []).length) {
      global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
        .text("Views: " + impact.affectedViews.map(function(item) { return item.title + " [" + item.type + "]"; }).join(" | "))
        .appendTo(block);
    }
    if (global.CrudUtils.ensureArray(impact.affectedReports || []).length) {
      global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
        .text("Reports: " + impact.affectedReports.map(function(item) { return item.title + " (" + item.screenId + ")"; }).join(" | "))
        .appendTo(block);
    }
    if (!global.CrudUtils.ensureArray(impact.affectedViews || []).length && !global.CrudUtils.ensureArray(impact.affectedReports || []).length) {
      global.jQuery("<p class=\"program-builder-empty\"></p>").text("Nenhuma view analytics ou report publicado foi relacionado a este dataset.").appendTo(block);
    }
  };

  AnalyticsPipelinesAdmin.prototype.renderVersionHistory = function(container, versionsPayload, logsPayload) {
    const versions = global.CrudUtils.ensureArray(versionsPayload && versionsPayload.versions || []);
    const logs = global.CrudUtils.ensureArray(logsPayload && logsPayload.logs || []);
    const versionsBlock = global.jQuery("<section class=\"program-builder-analytics-runtime-block\"></section>").appendTo(container);
    global.jQuery("<h5></h5>").text("Versoes publicadas").appendTo(versionsBlock);
    if (!versions.length) {
      global.jQuery("<p class=\"program-builder-empty\"></p>").text("Nenhuma versao publicada ainda.").appendTo(versionsBlock);
    } else {
      global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
        .text("Versao ativa: " + String(versionsPayload && versionsPayload.activeVersion && versionsPayload.activeVersion.versionNo || "-") + (versions[1] ? " | versao anterior: " + String(versions[1].versionNo) : ""))
        .appendTo(versionsBlock);
      global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
        .text(JSON.stringify({
          activeVersion: versionsPayload && versionsPayload.activeVersion || null,
          versions: versions
        }, null, 2))
        .appendTo(versionsBlock);
    }
    const logsBlock = global.jQuery("<section class=\"program-builder-analytics-runtime-block\"></section>").appendTo(container);
    global.jQuery("<h5></h5>").text("Logs por execucao").appendTo(logsBlock);
    if (!logs.length) {
      global.jQuery("<p class=\"program-builder-empty\"></p>").text("Nenhum log disponivel.").appendTo(logsBlock);
      return;
    }
    global.jQuery("<div class=\"program-builder-analytics-runtime-meta\"></div>")
      .text("Ultimas execucoes: " + logs.map(function(item) {
        const execution = item.execution || {};
        return String(execution.executionId || "-") + " (" + String(execution.status || "-") + ")";
      }).join(" | "))
      .appendTo(logsBlock);
    global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
      .text(JSON.stringify({ logs: logs }, null, 2))
      .appendTo(logsBlock);
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
