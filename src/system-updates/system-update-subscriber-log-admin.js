(function(global) {
  "use strict";

  function SystemUpdateSubscriberLogAdmin(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#system-update-subscriber-log-admin-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.centralControl = {};
    this.subscribers = [];
    this.executions = [];
    this.currentSubscriberCode = "";
    this.currentStatus = "";
    this.currentCategory = "";
    this.currentDateFrom = "";
    this.currentDateTo = "";
    this.currentExecution = null;
    this.historySummary = {};
  }

  SystemUpdateSubscriberLogAdmin.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.loadBootstrap();
  };

  SystemUpdateSubscriberLogAdmin.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell system-updates-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Atualizacoes por assinante").appendTo(title);
    global.jQuery("<p></p>").text("Consulta central do que foi aplicado em cada assinante SaaS.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    this.summaryCard = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(shell);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text("Consulta").appendTo(this.summaryCard);
    this.summaryBody = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(this.summaryCard);

    const toolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(this.summaryBody);
    const subscriberField = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:320px\"></label>").appendTo(toolbar);
    global.jQuery("<span></span>").text("Assinante").appendTo(subscriberField);
    this.subscriberInput = global.jQuery("<input>").appendTo(subscriberField);
    const statusField = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:180px\"></label>").appendTo(toolbar);
    global.jQuery("<span></span>").text("Status").appendTo(statusField);
    this.statusInput = global.jQuery("<input class=\"system-update-filter-status\">").appendTo(statusField);
    const categoryField = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:200px\"></label>").appendTo(toolbar);
    global.jQuery("<span></span>").text("Categoria").appendTo(categoryField);
    this.categoryInput = global.jQuery("<input class=\"system-update-filter-category\">").appendTo(categoryField);
    const dateFromField = global.jQuery("<label class=\"program-builder-field\"></label>").appendTo(toolbar);
    global.jQuery("<span></span>").text("De").appendTo(dateFromField);
    this.dateFromInput = global.jQuery("<input type=\"date\">").appendTo(dateFromField);
    const dateToField = global.jQuery("<label class=\"program-builder-field\"></label>").appendTo(toolbar);
    global.jQuery("<span></span>").text("Ate").appendTo(dateToField);
    this.dateToInput = global.jQuery("<input type=\"date\">").appendTo(dateToField);
    this.createButton(toolbar, "Aplicar filtros", "filter", this.handleApplyFilters.bind(this));
    this.createButton(toolbar, "Limpar filtros", "reset", this.handleResetFilters.bind(this));
    this.createButton(toolbar, "Exportar JSON", "download", this.handleExport.bind(this, "json"));
    this.createButton(toolbar, "Exportar CSV", "download", this.handleExport.bind(this, "csv"));
    this.createButton(toolbar, "Atualizar", "reload", this.loadBootstrap.bind(this));

    const grid = global.jQuery("<div class=\"program-governance-admin-grid\"></div>").appendTo(shell);
    this.leftColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.rightColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);

    const logCard = this.createCard(this.leftColumn, "Execucoes");
    this.executionsGridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(logCard.body);
    const detailCard = this.createCard(this.rightColumn, "Detalhe");
    this.detailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(detailCard.body);
    this.detailElement.text("Selecione uma execucao.");
  };

  SystemUpdateSubscriberLogAdmin.prototype.loadBootstrap = function() {
    this.setStatus("Carregando");
    return this.request("GET", "/api/admin/system-updates/subscriber-log/bootstrap", {
      subscriberCode: this.currentSubscriberCode || ""
    }).then((payload) => {
      this.centralControl = payload.centralControl || {};
      this.subscribers = global.CrudUtils.ensureArray(payload.subscribers);
      this.currentSubscriberCode = payload.selectedSubscriber && payload.selectedSubscriber.code || this.currentSubscriberCode || "";
      if (this.centralControl.centralControl === true && !this.currentSubscriberCode && this.subscribers.length) {
        this.currentSubscriberCode = this.subscribers[0].code || "";
        return this.loadBootstrap();
      }
      this.renderSummary(payload.selectedSubscriber || null);
      this.renderSubscriberSelector();
      this.renderFilters();
      return this.loadExecutionHistory().then(() => {
        this.setStatus("Pronto");
        return payload;
      });
    }).catch((error) => {
      this.setStatus("Falha");
      this.showError(error, "Nao foi possivel carregar o historico por assinante.");
      throw error;
    });
  };

  SystemUpdateSubscriberLogAdmin.prototype.renderSummary = function(selectedSubscriber) {
    this.summaryBody.find(".manual-summary").remove();
    this.summaryBody.find(".manual-meta").remove();
    if (this.centralControl.centralControl !== true) {
      global.jQuery("<p class=\"manual-summary\"></p>")
        .text("Esta consulta existe apenas no sistema central SaaS.")
        .appendTo(this.summaryBody);
      return;
    }
    const text = selectedSubscriber
      ? "Consultando historico de " + selectedSubscriber.code + " - " + selectedSubscriber.name + "."
      : "Selecione um assinante para consultar o historico aplicado.";
    global.jQuery("<p class=\"manual-summary\"></p>").text(text).appendTo(this.summaryBody);
    const executionSummary = this.summarizeExecutions();
    const historySummary = this.historySummary || {};
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryBody);
    this.appendBadge(badges, "Execucoes: " + String(historySummary.total != null ? historySummary.total : executionSummary.total));
    this.appendBadge(badges, "Sucesso: " + String(historySummary.succeeded != null ? historySummary.succeeded : executionSummary.succeeded));
    this.appendBadge(badges, "Falha: " + String(historySummary.failed != null ? historySummary.failed : executionSummary.failed));
    this.appendBadge(badges, "Fila: " + String(historySummary.queued != null ? historySummary.queued : executionSummary.queued));
    const pipelineSummary = historySummary.overlayPipeline || executionSummary.overlayPipeline;
    if (pipelineSummary.draftCreated > 0) {
      this.appendBadge(badges, "Rebases gerados: " + String(pipelineSummary.draftCreated));
    }
    if (pipelineSummary.reviewRequired > 0) {
      this.appendBadge(badges, "Revisao overlay: " + String(pipelineSummary.reviewRequired));
    }
    if (pipelineSummary.blocked > 0) {
      this.appendBadge(badges, "Overlay bloqueado: " + String(pipelineSummary.blocked));
    }
    const rolloutSummary = historySummary.rolloutAudit || {};
    if (Number(rolloutSummary.dispatchCount || 0) > 0) {
      this.appendBadge(badges, "Rollouts: " + String(rolloutSummary.dispatchCount));
    }
    if (Number(rolloutSummary.blockedEntryCount || 0) > 0) {
      this.appendBadge(badges, "Entrada bloqueada: " + String(rolloutSummary.blockedEntryCount));
    }
    if ((historySummary.byCategory && Object.keys(historySummary.byCategory).length) || (historySummary.byStatus && Object.keys(historySummary.byStatus).length)) {
      global.jQuery("<p class=\"manual-summary\"></p>")
        .text("Recorte atual: status " + this.describeMap(historySummary.byStatus) + " | categorias " + this.describeMap(historySummary.byCategory) + ".")
        .appendTo(this.summaryBody);
    }
    if (Array.isArray(historySummary.timeline) && historySummary.timeline.length) {
      global.jQuery("<p class=\"manual-summary\"></p>")
        .text("Timeline do recorte: " + String(historySummary.timeline.length) + " eventos carregados.")
        .appendTo(this.summaryBody);
    }
    if (rolloutSummary.batchCodes && rolloutSummary.batchCodes.length) {
      global.jQuery("<p class=\"manual-summary\"></p>")
        .text("Lotes SaaS observados: " + rolloutSummary.batchCodes.join(", ") + ".")
        .appendTo(this.summaryBody);
    }
  };

  SystemUpdateSubscriberLogAdmin.prototype.renderSubscriberSelector = function() {
    if (this.subscriberDropDown && typeof this.subscriberDropDown.destroy === "function") {
      this.subscriberDropDown.destroy();
    }
    this.subscriberInput.kendoDropDownList({
      dataTextField: "label",
      dataValueField: "code",
      valuePrimitive: true,
      optionLabel: "Selecione",
      dataSource: this.subscribers.map(function(item) {
        return {
          code: item.code,
          label: item.code + " - " + item.name
        };
      }),
      value: this.currentSubscriberCode || "",
      change: () => {
        this.currentSubscriberCode = this.subscriberDropDown.value() || "";
        this.loadBootstrap();
      }
    });
    this.subscriberDropDown = this.subscriberInput.data("kendoDropDownList");
  };

  SystemUpdateSubscriberLogAdmin.prototype.renderFilters = function() {
    if (this.statusDropDown && typeof this.statusDropDown.destroy === "function") {
      this.statusDropDown.destroy();
    }
    if (this.categoryDropDown && typeof this.categoryDropDown.destroy === "function") {
      this.categoryDropDown.destroy();
    }
    this.statusInput.kendoDropDownList({
      optionLabel: "Todos",
      valuePrimitive: true,
      dataSource: [
        { text: "Todos", value: "" },
        { text: "queued", value: "queued" },
        { text: "running", value: "running" },
        { text: "succeeded", value: "succeeded" },
        { text: "failed", value: "failed" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: this.currentStatus || ""
    });
    this.statusDropDown = this.statusInput.data("kendoDropDownList");
    this.categoryInput.kendoDropDownList({
      optionLabel: "Todas",
      valuePrimitive: true,
      dataSource: [
        { text: "Todas", value: "" },
        { text: "security_critical", value: "security_critical" },
        { text: "required_structural", value: "required_structural" },
        { text: "recommended", value: "recommended" },
        { text: "optional_visual", value: "optional_visual" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: this.currentCategory || ""
    });
    this.categoryDropDown = this.categoryInput.data("kendoDropDownList");
    this.dateFromInput.val(this.currentDateFrom || "");
    this.dateToInput.val(this.currentDateTo || "");
  };

  SystemUpdateSubscriberLogAdmin.prototype.loadExecutionHistory = function() {
    return this.request("GET", "/api/admin/system-updates/executions", {
      subscriberCode: this.currentSubscriberCode || "",
      status: this.currentStatus || "",
      category: this.currentCategory || "",
      dateFrom: this.currentDateFrom || "",
      dateTo: this.currentDateTo || "",
      limit: 120
    }).then((payload) => {
      this.executions = global.CrudUtils.ensureArray(payload.items);
      this.historySummary = payload.summary || {};
      this.renderSummary(this.findSelectedSubscriber());
      this.renderExecutionsGrid();
      this.renderSelectedExecution();
      return payload;
    });
  };

  SystemUpdateSubscriberLogAdmin.prototype.findSelectedSubscriber = function() {
    const code = String(this.currentSubscriberCode || "");
    if (!code) {
      return null;
    }
    return this.subscribers.find(function(item) {
      return String(item.code || "") === code;
    }) || null;
  };

  SystemUpdateSubscriberLogAdmin.prototype.renderExecutionsGrid = function() {
    const self = this;
    const rows = this.executions.slice();
    if (this.executionsGrid) {
      this.executionsGrid.destroy();
      this.executionsGridElement.empty();
    }
    this.executionsGridElement.kendoGrid({
      dataSource: { data: rows },
      sortable: true,
      selectable: "row",
      scrollable: true,
      height: 420,
      columns: [
        { field: "releaseVersion", title: "Release", width: 110 },
        { field: "status", title: "Status", width: 120 },
        { field: "targetSubscriberCode", title: "Assinante", width: 140 },
        { field: "targetDatabaseIdentity", title: "Base alvo", width: 180 },
        { field: "createdAt", title: "Criado", width: 180 },
        { field: "finishedAt", title: "Finalizado", width: 180 }
      ],
      change: function() {
        const selected = this.dataItem(this.select());
        self.currentExecution = self.executions.find(function(item) {
          return String(item.id || "") === String(selected && selected.id || "");
        }) || null;
        self.renderSelectedExecution();
      }
    });
    this.executionsGrid = this.executionsGridElement.data("kendoGrid");
    this.currentExecution = rows.length
      ? (this.executions.find((item) => String(item.id || "") === String(rows[0].id || "")) || null)
      : null;
  };

  SystemUpdateSubscriberLogAdmin.prototype.handleApplyFilters = function() {
    this.currentStatus = this.statusDropDown ? (this.statusDropDown.value() || "") : "";
    this.currentCategory = this.categoryDropDown ? (this.categoryDropDown.value() || "") : "";
    this.currentDateFrom = String(this.dateFromInput.val() || "").trim();
    this.currentDateTo = String(this.dateToInput.val() || "").trim();
    this.setStatus("Filtrando");
    return this.loadExecutionHistory().then(() => {
      this.setStatus("Pronto");
    }).catch((error) => {
      this.setStatus("Falha");
      this.showError(error, "Nao foi possivel aplicar os filtros.");
      throw error;
    });
  };

  SystemUpdateSubscriberLogAdmin.prototype.handleResetFilters = function() {
    this.currentStatus = "";
    this.currentCategory = "";
    this.currentDateFrom = "";
    this.currentDateTo = "";
    this.renderFilters();
    return this.handleApplyFilters();
  };

  SystemUpdateSubscriberLogAdmin.prototype.handleExport = function(format) {
    if (!this.executions.length) {
      global.CrudUtils.showMessage("Nenhuma execucao carregada para exportar.", "warning");
      return;
    }
    const payload = {
      filters: {
        subscriberCode: this.currentSubscriberCode || "",
        status: this.currentStatus || "",
        category: this.currentCategory || "",
        dateFrom: this.currentDateFrom || "",
        dateTo: this.currentDateTo || ""
      },
      summary: this.historySummary || {},
      items: this.executions
    };
    const fileName = global.CrudUtils.buildExportFileName("atualizacoes-assinante", format === "csv" ? "csv" : "json", [
      this.currentSubscriberCode || "global",
      this.currentStatus || "todos",
      this.currentCategory || "todas"
    ]);
    if (format === "csv") {
      const lines = ["releaseVersion,status,category,severity,subscriberCode,databaseIdentity,createdAt,finishedAt,overlayDraftCreated,overlayReviewRequired,overlayBlocked"];
      this.executions.forEach((item) => {
        const pipeline = this.resolveOverlayPipelineSummary(item);
        lines.push([
          item.releaseVersion,
          item.status,
          item.category,
          item.severity,
          item.targetSubscriberCode,
          item.targetDatabaseIdentity,
          item.createdAt,
          item.finishedAt,
          pipeline.draftCreated,
          pipeline.reviewRequired,
          pipeline.blocked
        ].map(function(value) {
          return "\"" + String(value == null ? "" : value).replace(/"/g, "\"\"") + "\"";
        }).join(","));
      });
      this.downloadFile(fileName.replace(/\.json$/i, ".csv"), "text/csv;charset=utf-8", lines.join("\n"));
      return;
    }
    this.downloadFile(fileName, "application/json;charset=utf-8", JSON.stringify(payload, null, 2));
  };

  SystemUpdateSubscriberLogAdmin.prototype.renderSelectedExecution = function() {
    const execution = this.currentExecution;
    this.detailElement.empty();
    if (!execution) {
      this.detailElement.text("Selecione uma execucao.");
      return;
    }
    const host = global.jQuery("<div class=\"manual-section\"></div>").appendTo(this.detailElement);
    const header = global.jQuery("<div class=\"manual-card\"></div>").appendTo(host);
    global.jQuery("<h4></h4>").text("Resumo da execucao").appendTo(header);
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(header);
    this.appendBadge(badges, "Release " + String(execution.releaseVersion || "-"));
    this.appendBadge(badges, "Status " + String(execution.status || "-"));
    this.appendBadge(badges, "Assinante " + String(execution.targetSubscriberCode || "-"));
    if (execution.finishedAt) {
      this.appendBadge(badges, "Finalizado");
    }

    const definition = global.jQuery("<dl class=\"manual-definition\"></dl>").appendTo(header);
    this.appendDefinition(definition, "Titulo", execution.releaseTitle || "-");
    this.appendDefinition(definition, "Base alvo", execution.targetDatabaseIdentity || "-");
    this.appendDefinition(definition, "Modo", execution.mode || "-");
    this.appendDefinition(definition, "Origem", execution.initiatedSource || "-");
    this.appendDefinition(definition, "Job runtime", execution.runtimeJobId || "-");
    if (execution.summary && execution.summary.rolloutAudit) {
      this.appendDefinition(definition, "Rollout", String(execution.summary.rolloutAudit.stage || "-"));
      this.appendDefinition(definition, "Lote", execution.summary.rolloutAudit.batchCode || "-");
      this.appendDefinition(definition, "Acesso", execution.summary.rolloutAudit.entryAccessMode || "-");
    }

    const pipelineSummary = this.resolveOverlayPipelineSummary(execution);
    if (pipelineSummary.total > 0) {
      const pipelineCard = global.jQuery("<div class=\"manual-card\"></div>").appendTo(host);
      global.jQuery("<h4></h4>").text("Pipeline de overlays").appendTo(pipelineCard);
      const pipelineBadges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(pipelineCard);
      this.appendBadge(pipelineBadges, "Drafts criados: " + String(pipelineSummary.draftCreated));
      this.appendBadge(pipelineBadges, "Drafts existentes: " + String(pipelineSummary.draftExists));
      this.appendBadge(pipelineBadges, "Revisao: " + String(pipelineSummary.reviewRequired));
      this.appendBadge(pipelineBadges, "Bloqueados: " + String(pipelineSummary.blocked));
      this.appendBadge(pipelineBadges, "Congelados: " + String(pipelineSummary.frozen));
      this.appendBadge(pipelineBadges, "Falha pipeline: " + String(pipelineSummary.pipelineFailed));

      const overlayRows = this.collectOverlayPipelineRows(execution);
      if (overlayRows.length) {
        const table = global.jQuery("<table class=\"k-grid k-widget\" style=\"width:100%;border-collapse:collapse\"></table>").appendTo(pipelineCard);
        const thead = global.jQuery("<thead><tr><th>Programa</th><th>Overlay</th><th>Status base</th><th>Pipeline</th><th>Mensagem</th></tr></thead>").appendTo(table);
        void thead;
        const tbody = global.jQuery("<tbody></tbody>").appendTo(table);
        overlayRows.forEach(function(row) {
          const tr = global.jQuery("<tr></tr>").appendTo(tbody);
          global.jQuery("<td></td>").text(row.programCode || "-").appendTo(tr);
          global.jQuery("<td></td>").text(String(row.overlayId || "-")).appendTo(tr);
          global.jQuery("<td></td>").text(row.status || "-").appendTo(tr);
          global.jQuery("<td></td>").text(row.pipelineStatus || "-").appendTo(tr);
          global.jQuery("<td></td>").text(row.pipelineMessage || row.message || "-").appendTo(tr);
        });
      }
    }

    const payloadCard = global.jQuery("<div class=\"manual-card\"></div>").appendTo(host);
    global.jQuery("<h4></h4>").text("Payload tecnico").appendTo(payloadCard);
    global.jQuery("<pre class=\"program-builder-json-preview\"></pre>")
      .text(JSON.stringify(execution || {}, null, 2))
      .appendTo(payloadCard);

    const timeline = global.CrudUtils.ensureArray(this.historySummary && this.historySummary.timeline);
    if (timeline.length) {
      const timelineCard = global.jQuery("<div class=\"manual-card\"></div>").appendTo(host);
      global.jQuery("<h4></h4>").text("Timeline do assinante").appendTo(timelineCard);
      const list = global.jQuery("<ul></ul>").appendTo(timelineCard);
      timeline.slice(0, 12).forEach(function(entry) {
        global.jQuery("<li></li>")
          .text(String(entry.createdAt || "-") + " | " + String(entry.title || "-") + " | " + String(entry.status || "-") + " | " + String(entry.releaseVersion || "-"))
          .appendTo(list);
      });
    }
  };

  SystemUpdateSubscriberLogAdmin.prototype.collectOverlayPipelineRows = function(execution) {
    const report = execution && execution.impactReport || {};
    const programs = global.CrudUtils.ensureArray(report.programs);
    const rows = [];
    programs.forEach(function(program) {
      global.CrudUtils.ensureArray(program && program.overlayImpacts).forEach(function(item) {
        rows.push({
          programCode: program.programCode || "",
          overlayId: item.overlayId || "",
          status: item.status || "",
          pipelineStatus: item.pipelineStatus || "",
          pipelineMessage: item.pipelineMessage || "",
          message: item.message || ""
        });
      });
    });
    return rows;
  };

  SystemUpdateSubscriberLogAdmin.prototype.resolveOverlayPipelineSummary = function(execution) {
    const summary = execution && execution.summary && execution.summary.overlayPipeline;
    const reportSummary = execution && execution.impactReport && execution.impactReport.overlayPipelineSummary;
    const source = summary || reportSummary || {};
    return {
      draftCreated: Number(source.draftCreated || 0),
      draftExists: Number(source.draftExists || 0),
      reviewRequired: Number(source.reviewRequired || 0),
      blocked: Number(source.blocked || 0),
      frozen: Number(source.frozen || 0),
      pipelineFailed: Number(source.pipelineFailed || 0),
      total: Number(source.draftCreated || 0)
        + Number(source.draftExists || 0)
        + Number(source.reviewRequired || 0)
        + Number(source.blocked || 0)
        + Number(source.frozen || 0)
        + Number(source.pipelineFailed || 0)
    };
  };

  SystemUpdateSubscriberLogAdmin.prototype.summarizeExecutions = function() {
    const summary = {
      total: this.executions.length,
      succeeded: 0,
      failed: 0,
      queued: 0,
      overlayPipeline: {
        draftCreated: 0,
        reviewRequired: 0,
        blocked: 0
      }
    };
    this.executions.forEach((item) => {
      const status = String(item.status || "");
      if (status === "succeeded") {
        summary.succeeded += 1;
      } else if (status === "failed") {
        summary.failed += 1;
      } else if (status === "queued" || status === "running") {
        summary.queued += 1;
      }
      const pipeline = this.resolveOverlayPipelineSummary(item);
      summary.overlayPipeline.draftCreated += pipeline.draftCreated;
      summary.overlayPipeline.reviewRequired += pipeline.reviewRequired;
      summary.overlayPipeline.blocked += pipeline.blocked;
    });
    return summary;
  };

  SystemUpdateSubscriberLogAdmin.prototype.appendBadge = function(container, text) {
    return global.jQuery("<span class=\"manual-badge\"></span>").text(text || "").appendTo(container);
  };

  SystemUpdateSubscriberLogAdmin.prototype.appendDefinition = function(container, title, value) {
    const wrapper = global.jQuery("<div></div>").appendTo(container);
    global.jQuery("<dt></dt>").text(title || "").appendTo(wrapper);
    global.jQuery("<dd></dd>").text(value == null ? "" : String(value)).appendTo(wrapper);
    return wrapper;
  };

  SystemUpdateSubscriberLogAdmin.prototype.describeMap = function(values) {
    const entries = Object.entries(values || {});
    if (!entries.length) {
      return "sem itens";
    }
    return entries.map(function(item) {
      return String(item[0]) + ": " + String(item[1]);
    }).join(", ");
  };

  SystemUpdateSubscriberLogAdmin.prototype.downloadFile = function(fileName, mimeType, content) {
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

  SystemUpdateSubscriberLogAdmin.prototype.request = function(method, url, data) {
    return this.httpClient.request({ method: method, url: url, data: data || {} });
  };

  SystemUpdateSubscriberLogAdmin.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(container);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  SystemUpdateSubscriberLogAdmin.prototype.createButton = function(container, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\" class=\"k-button k-button-solid-base\"></button>").appendTo(container);
    if (icon) {
      global.jQuery("<span class=\"k-icon\"></span>").addClass("k-i-" + icon).appendTo(button);
    }
    global.jQuery("<span></span>").text(text).appendTo(button);
    button.on("click", handler);
    return button;
  };

  SystemUpdateSubscriberLogAdmin.prototype.setStatus = function(text) {
    this.statusBadge.text(text || "");
  };

  SystemUpdateSubscriberLogAdmin.prototype.showError = function(error, fallback) {
    const message = error && error.error && error.error.message || error && error.message || fallback;
    global.CrudUtils.showMessage(message, "error");
  };

  global.SystemUpdateSubscriberLogAdmin = SystemUpdateSubscriberLogAdmin;
})(window);
