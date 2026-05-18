(function(global) {
  "use strict";

  function SystemUpdatesAdmin(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#system-updates-admin-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.releases = [];
    this.executions = [];
    this.jobs = [];
    this.summary = {};
    this.currentRelease = null;
    this.currentExecution = null;
    this.currentSubscriberCode = "";
    this.subscribers = [];
    this.centralControl = {};
    this.activeEventSource = null;
    this.activePollTimer = 0;
  }

  SystemUpdatesAdmin.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.loadBootstrap();
  };

  SystemUpdatesAdmin.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell system-updates-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Atualizacoes do sistema").appendTo(title);
    global.jQuery("<p></p>").text("Catalogo de releases, aplicacao controlada e impacto em programas padrao/customizados.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    const actions = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(header);
    this.createButton(actions, "Atualizar manifesto", "reload", this.handleCheck.bind(this));
    this.downloadButton = this.createButton(actions, "Baixar pacote", "download", this.handleDownload.bind(this));
    this.publishArtifactsButton = this.createButton(actions, "Publicar artefatos", "folder-up", this.handlePublishArtifacts.bind(this));
    this.consentButton = this.createButton(actions, "Registrar anuencia", "check", this.handleConsent.bind(this));
    this.applyButton = this.createButton(actions, "Aplicar release", "play", this.handleApply.bind(this));
    this.rolloutButton = this.createButton(actions, "Plano SaaS", "track-changes-enable", this.handleRolloutPlan.bind(this));
    this.dispatchRolloutButton = this.createButton(actions, "Despachar rollout", "upload", this.handleDispatchRollout.bind(this));

    this.summaryPanel = global.jQuery("<div class=\"program-builder-governance-card\"></div>").appendTo(shell);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text("Resumo").appendTo(this.summaryPanel);
    this.summaryBody = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(this.summaryPanel);
    this.centralWarning = global.jQuery("<p class=\"manual-summary\"></p>").hide().appendTo(this.summaryBody);

    const grid = global.jQuery("<div class=\"program-governance-admin-grid\"></div>").appendTo(shell);
    this.leftColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.rightColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);

    this.renderReleaseCard();
    this.renderExecutionCard();
    this.renderDetailCard();
  };

  SystemUpdatesAdmin.prototype.renderReleaseCard = function() {
    const card = this.createCard(this.leftColumn, "Releases");
    this.subscriberToolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(card.body);
    this.subscriberLabel = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:280px\"></label>").appendTo(this.subscriberToolbar);
    global.jQuery("<span></span>").text("Assinante alvo").appendTo(this.subscriberLabel);
    this.subscriberSelect = global.jQuery("<input>").appendTo(this.subscriberLabel);
    this.releasesGridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(card.body);
  };

  SystemUpdatesAdmin.prototype.renderExecutionCard = function() {
    const card = this.createCard(this.rightColumn, "Execucoes");
    this.executionToolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(card.body);
    this.executionStatusLabel = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:180px\"></label>").appendTo(this.executionToolbar);
    global.jQuery("<span></span>").text("Status").appendTo(this.executionStatusLabel);
    this.executionStatusSelect = global.jQuery("<input>").appendTo(this.executionStatusLabel);
    this.executionsGridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(card.body);
  };

  SystemUpdatesAdmin.prototype.renderDetailCard = function() {
    const card = this.createCard(this.rightColumn, "Detalhe");
    this.detailSummaryElement = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card.body);
    this.detailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(card.body);
    this.detailElement.text("Selecione uma release ou execucao.");
  };

  SystemUpdatesAdmin.prototype.loadBootstrap = function() {
    this.setStatus("Carregando");
    return this.request("GET", "/api/admin/system-updates/bootstrap", {
      subscriberCode: this.currentSubscriberCode || ""
    }).then((payload) => {
      this.centralControl = payload.centralControl || {};
      this.summary = payload.summary || {};
      this.subscribers = global.CrudUtils.ensureArray(payload.subscribers);
      this.currentSubscriberCode = payload.selectedSubscriber && payload.selectedSubscriber.code || this.currentSubscriberCode || "";
      if (this.centralControl.centralControl === true && !this.currentSubscriberCode && this.subscribers.length) {
        this.currentSubscriberCode = this.subscribers[0].code || "";
        return this.loadBootstrap();
      }
      this.releases = global.CrudUtils.ensureArray(payload.releases);
      this.executions = global.CrudUtils.ensureArray(payload.executions);
      this.jobs = global.CrudUtils.ensureArray(payload.jobs);
      this.renderSubscriberSelector();
      this.renderSummary();
      this.renderReleasesGrid();
      this.renderExecutionsGrid();
      this.setStatus("Pronto");
      return payload;
    }).catch((error) => {
      this.setStatus("Falha");
      this.showError(error, "Nao foi possivel carregar as atualizacoes.");
      throw error;
    });
  };

  SystemUpdatesAdmin.prototype.renderSummary = function() {
    const summary = this.summary || {};
    const badges = [
      "Versao atual: " + String(summary.currentVersion || "-"),
      "Modo: " + String(summary.deploymentMode || "-"),
      "Pendentes: " + String(summary.pendingCount || 0),
      "Criticas: " + String(summary.criticalPendingCount || 0)
    ];
    const autoQueued = summary.autoQueuedRelease;

    this.summaryBody.empty();
    if (this.centralControl.centralControl !== true) {
      this.centralWarning
        .text("Esta tela existe apenas no sistema central SaaS. No assinante, mantenha apenas a verificacao local de atualizacao.")
        .show()
        .appendTo(this.summaryBody);
    }
    const badgeHost = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryBody);
    badges.forEach(function(text) {
      global.jQuery("<span class=\"manual-badge\"></span>").text(text).appendTo(badgeHost);
    });
    if (autoQueued && autoQueued.version) {
      global.jQuery("<p class=\"manual-summary\"></p>")
        .text("Uma release automatica foi enfileirada ao abrir o sistema: " + String(autoQueued.version) + ".")
        .appendTo(this.summaryBody);
    }
    global.jQuery("<p class=\"manual-summary\"></p>")
      .text("Base " + String(summary.databaseIdentity || "-") + " em " + String(summary.databaseEnvironment || "-") + ".")
      .appendTo(this.summaryBody);
    if (this.centralControl.centralControl === true) {
      global.jQuery("<p class=\"manual-summary\"></p>")
        .text("Sistema central identificado por `APP_SYSTEM_ROLE=" + String(this.centralControl.systemRole || "saas_central") + "`." + (this.currentSubscriberCode ? " Assinante alvo: " + this.currentSubscriberCode + "." : ""))
        .appendTo(this.summaryBody);
    }
    global.jQuery("<p class=\"manual-summary\"></p>")
      .text("Assinatura do manifesto: " + String(summary.manifestSignatureStatus || "desconhecida") + (summary.manifestSignatureMessage ? " | " + summary.manifestSignatureMessage : ""))
      .appendTo(this.summaryBody);
    if (summary.criticalActionRequired === true) {
      global.jQuery("<p class=\"manual-summary\"></p>")
        .text("Existe atualizacao critica pendente: " + global.CrudUtils.ensureArray(summary.pendingCriticalVersions).join(", ") + ".")
        .appendTo(this.summaryBody);
    }
  };

  SystemUpdatesAdmin.prototype.renderSubscriberSelector = function() {
    const canSelectSubscriber = this.centralControl.centralControl === true && this.subscribers.length > 0;
    this.subscriberToolbar.toggle(canSelectSubscriber);
    if (!canSelectSubscriber) {
      return;
    }
    if (this.subscriberDropDown && typeof this.subscriberDropDown.destroy === "function") {
      this.subscriberDropDown.destroy();
    }
    this.subscriberSelect.kendoDropDownList({
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
    this.subscriberDropDown = this.subscriberSelect.data("kendoDropDownList");
  };

  SystemUpdatesAdmin.prototype.renderReleasesGrid = function() {
    const self = this;
    const rows = this.releases.map(function(item) {
      return {
        version: item.version,
        title: item.title,
        category: item.category,
        severity: item.severity,
        status: item.status,
        consentStatus: item.consentStatus || "-",
        autoApplicable: item.autoApplicable === true ? "Sim" : "Nao"
      };
    });
    if (this.releasesGrid) {
      this.releasesGrid.destroy();
      this.releasesGridElement.empty();
    }
    this.releasesGridElement.kendoGrid({
      dataSource: { data: rows },
      sortable: true,
      selectable: "row",
      scrollable: true,
      height: 320,
      columns: [
        { field: "version", title: "Versao", width: 110 },
        { field: "title", title: "Titulo" },
        { field: "category", title: "Categoria", width: 150 },
        { field: "severity", title: "Severidade", width: 120 },
        { field: "status", title: "Status", width: 150 },
        { field: "consentStatus", title: "Anuencia", width: 120 }
      ],
      change: function() {
        const selected = this.dataItem(this.select());
        if (!selected) {
          return;
        }
        self.currentRelease = self.releases.find(function(item) {
          return item.version === selected.version;
        }) || null;
        self.currentExecution = null;
        self.renderDetail(self.currentRelease);
      }
    });
    this.releasesGrid = this.releasesGridElement.data("kendoGrid");
    if (rows.length) {
      const firstRow = this.releasesGrid.tbody.find("tr").first();
      if (firstRow.length) {
        this.releasesGrid.select(firstRow);
        this.currentRelease = this.releases[0];
        this.renderDetail(this.currentRelease);
      }
    }
  };

  SystemUpdatesAdmin.prototype.renderExecutionsGrid = function() {
    const self = this;
    const selectedStatus = this.executionStatusDropDown ? this.executionStatusDropDown.value() : "";
    const filteredExecutions = this.executions.filter(function(item) {
      return !selectedStatus || String(item.status || "") === selectedStatus;
    });
    const rows = filteredExecutions.map(function(item) {
      return {
        id: item.id,
        releaseVersion: item.releaseVersion,
        status: item.status,
        mode: item.mode,
        targetSubscriberCode: item.targetSubscriberCode || "",
        createdAt: item.createdAt || "",
        finishedAt: item.finishedAt || ""
      };
    });
    if (this.executionsGrid) {
      this.executionsGrid.destroy();
      this.executionsGridElement.empty();
    }
    if (this.executionStatusDropDown && typeof this.executionStatusDropDown.destroy === "function") {
      this.executionStatusDropDown.destroy();
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
      value: selectedStatus || "",
      change: () => this.renderExecutionsGrid()
    });
    this.executionStatusDropDown = this.executionStatusSelect.data("kendoDropDownList");
    this.executionsGridElement.kendoGrid({
      dataSource: { data: rows },
      sortable: true,
      selectable: "row",
      scrollable: true,
      height: 320,
      columns: [
        { field: "id", title: "Execucao", width: 100 },
        { field: "releaseVersion", title: "Release", width: 110 },
        { field: "status", title: "Status", width: 120 },
        { field: "mode", title: "Modo", width: 100 },
        { field: "targetSubscriberCode", title: "Assinante", width: 130 },
        { field: "createdAt", title: "Criado", width: 180 },
        { field: "finishedAt", title: "Finalizado", width: 180 }
      ],
      change: function() {
        const selected = this.dataItem(this.select());
        if (!selected) {
          return;
        }
        self.currentExecution = self.executions.find(function(item) {
          return Number(item.id) === Number(selected.id);
        }) || null;
        self.renderDetail(self.currentExecution);
      }
    });
    this.executionsGrid = this.executionsGridElement.data("kendoGrid");
  };

  SystemUpdatesAdmin.prototype.renderDetail = function(item) {
    this.detailSummaryElement.empty();
    if (item && item.version) {
      this.renderReleaseSummary(item);
    } else if (item && item.releaseVersion) {
      this.renderExecutionSummary(item);
    }
    this.detailElement.text(JSON.stringify(item || {}, null, 2));
  };

  SystemUpdatesAdmin.prototype.renderReleaseSummary = function(item) {
    const wrap = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(wrap);
    [
      "Versao: " + String(item.version || "-"),
      "Categoria: " + String(item.category || "-"),
      "Severidade: " + String(item.severity || "-"),
      "Breaking: " + String(item.breakingLevel || "non_breaking"),
      "Status: " + String(item.status || "-")
    ].forEach(function(text) {
      global.jQuery("<span class=\"manual-badge\"></span>").text(text).appendTo(badges);
    });

    const chain = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
    global.jQuery("<p></p>").text("Versao minima: " + String(item.requiresVersionMin || "-")).appendTo(chain);
    global.jQuery("<p></p>").text("Dependencias obrigatorias: " + this.formatVersionList(item.requiresAppliedUpdates)).appendTo(chain);
    global.jQuery("<p></p>").text("Substitui: " + this.formatVersionList(item.replaces)).appendTo(chain);
    global.jQuery("<p></p>").text("Passos: " + this.formatVersionList(item.steps)).appendTo(chain);
  };

  SystemUpdatesAdmin.prototype.renderExecutionSummary = function(item) {
    const wrap = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(wrap);
    [
      "Release: " + String(item.releaseVersion || "-"),
      "Status: " + String(item.status || "-"),
      "Modo: " + String(item.mode || "-"),
      "Assinante: " + String(item.targetSubscriberCode || "-")
    ].forEach(function(text) {
      global.jQuery("<span class=\"manual-badge\"></span>").text(text).appendTo(badges);
    });
  };

  SystemUpdatesAdmin.prototype.formatVersionList = function(items) {
    const values = global.CrudUtils.ensureArray(items).filter(function(item) {
      return String(item || "").trim() !== "";
    });
    return values.length ? values.join(", ") : "-";
  };

  SystemUpdatesAdmin.prototype.handleCheck = function() {
    this.request("POST", "/api/admin/system-updates/check", {
      autoQueue: false,
      subscriberCode: this.currentSubscriberCode || ""
    }).then(() => {
      global.CrudUtils.showMessage("Manifesto atualizado.", "success");
      return this.loadBootstrap();
    }).catch((error) => this.showError(error, "Nao foi possivel atualizar o manifesto."));
  };

  SystemUpdatesAdmin.prototype.handleApply = function() {
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode) {
      global.CrudUtils.showMessage("Selecione o assinante alvo da atualizacao.", "warning");
      return;
    }
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    const release = this.currentRelease;
    const proceed = () => {
      this.request("POST", "/api/admin/system-updates/apply", {
        version: release.version,
        forceConsent: false,
        subscriberCode: this.currentSubscriberCode || ""
      }).then((response) => {
        global.CrudUtils.showMessage("Atualizacao enfileirada.", "success");
        this.loadBootstrap().then(() => {
          if (response && response.job && response.job.id) {
            this.followJob(response.job.id);
          }
        });
      }).catch((error) => this.showError(error, "Nao foi possivel enfileirar a atualizacao."));
    };

    if (release.requiresConsent === true) {
      if (release.consentApproved !== true) {
        global.CrudUtils.showMessage("Registre a anuencia antes de aplicar esta release.", "warning");
        return;
      }
    }

    proceed();
  };

  SystemUpdatesAdmin.prototype.handleDownload = function() {
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode) {
      global.CrudUtils.showMessage("Selecione o assinante alvo do pacote.", "warning");
      return;
    }
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    this.request("POST", "/api/admin/system-updates/download", {
      version: this.currentRelease.version,
      subscriberCode: this.currentSubscriberCode || ""
    }).then((payload) => {
      this.renderDetail(payload);
      global.CrudUtils.showMessage("Pacote validado e baixado.", "success");
    }).catch((error) => this.showError(error, "Nao foi possivel baixar o pacote da release."));
  };

  SystemUpdatesAdmin.prototype.handleConsent = function() {
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode) {
      global.CrudUtils.showMessage("Selecione o assinante alvo da anuencia.", "warning");
      return;
    }
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    const release = this.currentRelease;
    global.CrudUtils.confirm(
      "Registrar anuencia para a release " + release.version + "?",
      { title: "Registrar anuencia" }
    ).then((confirmed) => {
      if (!confirmed) {
        return;
      }
      this.request("POST", "/api/admin/system-updates/consent", {
        version: release.version,
        status: "approved",
        reason: "Aprovado pela tela administrativa.",
        subscriberCode: this.currentSubscriberCode || ""
      }).then(() => {
        global.CrudUtils.showMessage("Anuencia registrada.", "success");
        return this.loadBootstrap();
      }).catch((error) => this.showError(error, "Nao foi possivel registrar a anuencia."));
    });
  };

  SystemUpdatesAdmin.prototype.handlePublishArtifacts = function() {
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    this.request("POST", "/api/admin/system-updates/publish-artifacts", {
      version: this.currentRelease.version
    }).then((payload) => {
      this.currentExecution = null;
      this.renderDetail(payload);
      global.CrudUtils.showMessage("Artefatos oficiais publicados.", "success");
    }).catch((error) => this.showError(error, "Nao foi possivel publicar os artefatos oficiais."));
  };

  SystemUpdatesAdmin.prototype.handleRolloutPlan = function() {
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode) {
      global.CrudUtils.showMessage("Selecione o assinante alvo do plano SaaS.", "warning");
      return;
    }
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    this.request("GET", "/api/admin/system-updates/rollout-plan", {
      version: this.currentRelease.version,
      subscriberCode: this.currentSubscriberCode || ""
    }).then((plan) => {
      this.currentExecution = null;
      this.renderDetail(plan);
      global.CrudUtils.showMessage("Plano de rollout carregado.", "info");
    }).catch((error) => this.showError(error, "Nao foi possivel carregar o plano de rollout."));
  };

  SystemUpdatesAdmin.prototype.handleDispatchRollout = function() {
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode) {
      global.CrudUtils.showMessage("Selecione o assinante alvo do rollout.", "warning");
      return;
    }
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    this.request("POST", "/api/admin/system-updates/dispatch-rollout", {
      version: this.currentRelease.version,
      subscriberCode: this.currentSubscriberCode || ""
    }).then((payload) => {
      this.currentExecution = null;
      this.renderDetail(payload);
      global.CrudUtils.showMessage("Rollout SaaS despachado.", "success");
    }).catch((error) => this.showError(error, "Nao foi possivel despachar o rollout SaaS."));
  };

  SystemUpdatesAdmin.prototype.followJob = function(jobId) {
    this.stopFollowingJob();
    if (this.httpClient && typeof this.httpClient.subscribeSystemUpdateJob === "function") {
      this.httpClient.subscribeSystemUpdateJob(jobId, this.handleJobPayload.bind(this));
      return;
    }
    if (global.location && global.location.protocol !== "file:" && typeof global.EventSource === "function" && this.httpClient && typeof this.httpClient.buildRuntimeEventUrl === "function") {
      const url = this.httpClient.buildRuntimeEventUrl("/api/admin/system-updates/jobs/" + jobId + "/events");
      const source = new global.EventSource(url);
      source.addEventListener("system-update-job", (event) => {
        this.handleJobPayload(JSON.parse(event.data || "{}"));
      });
      source.onerror = () => {
        this.stopFollowingJob();
        this.startPoll(jobId);
      };
      this.activeEventSource = source;
      return;
    }
    this.startPoll(jobId);
  };

  SystemUpdatesAdmin.prototype.startPoll = function(jobId) {
    const poll = () => {
      this.request("GET", "/api/admin/system-updates/jobs/" + jobId).then((payload) => {
        this.handleJobPayload(payload);
        if (payload.status === "succeeded" || payload.status === "failed") {
          this.stopFollowingJob();
          this.loadBootstrap();
          return;
        }
        this.activePollTimer = global.setTimeout(poll, 1500);
      }).catch(() => {
        this.activePollTimer = global.setTimeout(poll, 3000);
      });
    };
    poll();
  };

  SystemUpdatesAdmin.prototype.handleJobPayload = function(payload) {
    this.renderDetail(payload);
    if (payload && (payload.status === "succeeded" || payload.status === "failed")) {
      this.loadBootstrap();
    }
  };

  SystemUpdatesAdmin.prototype.stopFollowingJob = function() {
    if (this.activeEventSource) {
      this.activeEventSource.close();
      this.activeEventSource = null;
    }
    if (this.activePollTimer) {
      global.clearTimeout(this.activePollTimer);
      this.activePollTimer = 0;
    }
  };

  SystemUpdatesAdmin.prototype.request = function(method, url, data) {
    return this.httpClient.request({ method: method, url: url, data: data || {} });
  };

  SystemUpdatesAdmin.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(container);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  SystemUpdatesAdmin.prototype.createButton = function(container, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\" class=\"k-button k-button-solid-base\"></button>").appendTo(container);
    if (icon) {
      global.jQuery("<span class=\"k-icon\"></span>").addClass("k-i-" + icon).appendTo(button);
    }
    global.jQuery("<span></span>").text(text).appendTo(button);
    button.on("click", handler);
    return button;
  };

  SystemUpdatesAdmin.prototype.setStatus = function(text) {
    this.statusBadge.text(text || "");
  };

  SystemUpdatesAdmin.prototype.showError = function(error, fallback) {
    const message = error && error.error && error.error.message || error && error.message || fallback;
    global.CrudUtils.showMessage(message, "error");
  };

  global.SystemUpdatesAdmin = SystemUpdatesAdmin;
})(window);
