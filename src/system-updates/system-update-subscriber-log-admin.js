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
    this.currentExecution = null;
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
      this.executions = global.CrudUtils.ensureArray(payload.executions);
      this.currentSubscriberCode = payload.selectedSubscriber && payload.selectedSubscriber.code || this.currentSubscriberCode || "";
      if (this.centralControl.centralControl === true && !this.currentSubscriberCode && this.subscribers.length) {
        this.currentSubscriberCode = this.subscribers[0].code || "";
        return this.loadBootstrap();
      }
      this.renderSummary(payload.selectedSubscriber || null);
      this.renderSubscriberSelector();
      this.renderExecutionsGrid();
      this.renderSelectedExecution();
      this.setStatus("Pronto");
      return payload;
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
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryBody);
    this.appendBadge(badges, "Execucoes: " + String(executionSummary.total));
    this.appendBadge(badges, "Sucesso: " + String(executionSummary.succeeded));
    this.appendBadge(badges, "Falha: " + String(executionSummary.failed));
    this.appendBadge(badges, "Fila: " + String(executionSummary.queued));
    if (executionSummary.overlayPipeline.draftCreated > 0) {
      this.appendBadge(badges, "Rebases gerados: " + String(executionSummary.overlayPipeline.draftCreated));
    }
    if (executionSummary.overlayPipeline.reviewRequired > 0) {
      this.appendBadge(badges, "Revisao overlay: " + String(executionSummary.overlayPipeline.reviewRequired));
    }
    if (executionSummary.overlayPipeline.blocked > 0) {
      this.appendBadge(badges, "Overlay bloqueado: " + String(executionSummary.overlayPipeline.blocked));
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

  SystemUpdateSubscriberLogAdmin.prototype.renderExecutionsGrid = function() {
    const self = this;
    if (this.executionStatusDropDown && typeof this.executionStatusDropDown.destroy === "function") {
      this.executionStatusDropDown.destroy();
    }
    if (!this.executionFilterToolbar || !this.executionFilterToolbar.length) {
      this.executionFilterToolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").insertBefore(this.executionsGridElement);
      const statusLabel = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:180px\"></label>").appendTo(this.executionFilterToolbar);
      global.jQuery("<span></span>").text("Status").appendTo(statusLabel);
      this.executionStatusSelect = global.jQuery("<input>").appendTo(statusLabel);
    }
    this.executionStatusSelect.kendoDropDownList({
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
      value: this.currentStatus || "",
      change: () => {
        this.currentStatus = this.executionStatusDropDown.value() || "";
        this.renderExecutionsGrid();
      }
    });
    this.executionStatusDropDown = this.executionStatusSelect.data("kendoDropDownList");
    const rows = this.executions.filter((item) => !this.currentStatus || String(item.status || "") === this.currentStatus);
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
