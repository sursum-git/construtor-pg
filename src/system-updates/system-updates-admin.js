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
    this.currentBatchCode = "";
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
    this.tenantActivationButton = this.createButton(actions, "Ativar tenant", "link-horizontal", this.handleTenantActivation.bind(this));
    this.simulateButton = this.createButton(actions, "Simular", "eye", this.handleSimulate.bind(this));
    this.rollbackButton = this.createButton(actions, "Rollback", "undo", this.handleRollback.bind(this));
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
    this.batchLabel = global.jQuery("<label class=\"program-builder-field\" style=\"min-width:220px\"></label>").appendTo(this.subscriberToolbar);
    global.jQuery("<span></span>").text("Lote SaaS").appendTo(this.batchLabel);
    this.batchSelect = global.jQuery("<input>").appendTo(this.batchLabel);
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
    if (summary.delayDashboard) {
      const delay = summary.delayDashboard;
      const delayHost = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryBody);
      [
        "Atrasados: " + String(delay.outdatedSubscribers || 0),
        "Bloqueio cadeia: " + String(delay.blockedDependencySubscribers || 0),
        "Aguardando anuencia: " + String(delay.awaitingConsentSubscribers || 0),
        "Aguardando ativacao: " + String(delay.awaitingActivationSubscribers || 0),
        "Falha rollout: " + String(delay.failedRolloutSubscribers || 0)
      ].forEach(function(text) {
        global.jQuery("<span class=\"manual-badge\"></span>").text(text).appendTo(delayHost);
      });
    }
    const alerts = global.CrudUtils.ensureArray(summary.operationalAlerts);
    if (alerts.length) {
      const alertList = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.summaryBody);
      global.jQuery("<p></p>").text("Alertas operacionais automaticos:").appendTo(alertList);
      const list = global.jQuery("<ul></ul>").appendTo(alertList);
      alerts.forEach(function(item) {
        global.jQuery("<li></li>").text(String(item.message || "")).appendTo(list);
      });
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
      optionLabel: "Todos os assinantes",
      dataSource: this.subscribers.map(function(item) {
        return {
          code: item.code,
          label: item.code + " - " + item.name
        };
      }),
      value: this.currentSubscriberCode || "",
      change: () => {
        this.currentSubscriberCode = this.subscriberDropDown.value() || "";
        this.currentBatchCode = "";
        this.loadBootstrap();
      }
    });
    this.subscriberDropDown = this.subscriberSelect.data("kendoDropDownList");
    this.renderBatchSelector();
  };

  SystemUpdatesAdmin.prototype.renderBatchSelector = function() {
    const options = this.computeBatchOptions();
    const visible = this.centralControl.centralControl === true && !this.currentSubscriberCode && options.length > 0;
    this.batchLabel.toggle(visible);
    if (!visible) {
      this.currentBatchCode = "";
      if (this.batchDropDown && typeof this.batchDropDown.destroy === "function") {
        this.batchDropDown.destroy();
        this.batchDropDown = null;
      }
      return;
    }
    if (this.batchDropDown && typeof this.batchDropDown.destroy === "function") {
      this.batchDropDown.destroy();
    }
    if (!this.currentBatchCode && options.length) {
      this.currentBatchCode = options[0].code;
    }
    this.batchSelect.kendoDropDownList({
      dataTextField: "label",
      dataValueField: "code",
      valuePrimitive: true,
      dataSource: options,
      value: this.currentBatchCode || "",
      change: () => {
        this.currentBatchCode = this.batchDropDown.value() || "";
      }
    });
    this.batchDropDown = this.batchSelect.data("kendoDropDownList");
  };

  SystemUpdatesAdmin.prototype.computeBatchOptions = function() {
    if (!this.currentRelease || this.currentSubscriberCode) {
      return [];
    }
    const configured = this.currentRelease.metadata && Array.isArray(this.currentRelease.metadata.saasRolloutBatches)
      ? this.currentRelease.metadata.saasRolloutBatches
      : [];
    if (configured.length) {
      return configured.map(function(item, index) {
        const code = String(item && item.code || "batch-" + (index + 1));
        return {
          code: code,
          label: code + " - " + String(item && item.title || code)
        };
      });
    }
    if (!this.subscribers.length) {
      return [];
    }
    if (this.subscribers.length === 1) {
      return [{ code: "canary", label: "canary - Lote unico" }];
    }
    return [
      { code: "canary", label: "canary - Canario inicial" },
      { code: "wave-1", label: "wave-1 - Lote principal" }
    ];
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
        channel: global.CrudUtils.ensureArray(item.channels).join(", "),
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
        { field: "channel", title: "Canal", width: 140 },
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
        self.renderBatchSelector();
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
    } else if (item && item.release && item.release.version) {
      this.renderReleaseSummary(item.release);
      this.renderSimulationSummary(item);
    } else if (item && item.releaseVersion) {
      this.renderExecutionSummary(item);
    }
    this.detailElement.text(JSON.stringify(item || {}, null, 2));
  };

  SystemUpdatesAdmin.prototype.renderSimulationSummary = function(payload) {
    const precheck = payload && payload.precheck || {};
    const subscriberImpact = payload && payload.subscriberImpact || {};
    const rollbackPlan = payload && payload.rollbackPlan || {};
    const dashboard = payload && payload.delayDashboard || {};
    const alerts = global.CrudUtils.ensureArray(payload && payload.operationalAlerts);
    const simulation = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
    global.jQuery("<p></p>").text("Simulacao operacional").appendTo(simulation);
    const badges = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(simulation);
    [
      "Pre-check: " + String(precheck.status || "-"),
      "Prontos: " + String(subscriberImpact.summary && subscriberImpact.summary.ready || 0),
      "Anuencia: " + String(subscriberImpact.summary && subscriberImpact.summary.requiresConsent || 0),
      "Ativacao: " + String(subscriberImpact.summary && subscriberImpact.summary.awaitingActivation || 0),
      "Rollback: " + (rollbackPlan.supported === true ? "suportado" : "indisponivel")
    ].forEach(function(text) {
      global.jQuery("<span class=\"manual-badge\"></span>").text(text).appendTo(badges);
    });
    if (dashboard && Object.keys(dashboard).length) {
      global.jQuery("<p></p>")
        .text("Atrasos: " + String(dashboard.outdatedSubscribers || 0) + " | Falha rollout: " + String(dashboard.failedRolloutSubscribers || 0))
        .appendTo(simulation);
    }
    if (alerts.length) {
      const list = global.jQuery("<ul></ul>").appendTo(simulation);
      alerts.forEach(function(item) {
        global.jQuery("<li></li>").text(String(item.message || "")).appendTo(list);
      });
    }
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
    global.jQuery("<p></p>").text("Canais: " + this.formatVersionList(item.channels)).appendTo(chain);
    global.jQuery("<p></p>").text("Canal alvo: " + String(item.targetChannel || "stable")).appendTo(chain);
    global.jQuery("<p></p>").text("Passos: " + this.formatStepList(item.stepCatalog || item.steps)).appendTo(chain);
    if (item.rolloutWindow && typeof item.rolloutWindow === "object") {
      global.jQuery("<p></p>").text("Janela SaaS: " + String(item.rolloutWindow.status || "unscheduled") + (item.rolloutWindow.startAt ? " | inicio " + String(item.rolloutWindow.startAt) : "")).appendTo(chain);
    }
    if (item.tenantActivationRequired === true) {
      global.jQuery("<p></p>").text("Ativacao por tenant: " + String(item.tenantActivationStatus || "pending")).appendTo(chain);
    }

    if (item.scenarioBehavior && typeof item.scenarioBehavior === "object") {
      const behavior = item.scenarioBehavior;
      const scenario = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
      global.jQuery("<p></p>").text("Controle: " + String(behavior.control || "-")).appendTo(scenario);
      global.jQuery("<p></p>").text("Aplicacao no cenario: " + String(behavior.applyMode || "-")).appendTo(scenario);
      if (behavior.rolloutMode) {
        global.jQuery("<p></p>").text("Rollout: " + String(behavior.rolloutMode || "-")).appendTo(scenario);
      }
      if (behavior.accessPolicy) {
        global.jQuery("<p></p>").text("Politica de acesso: " + String(behavior.accessPolicy || "-")).appendTo(scenario);
      }
      if (behavior.entryBlockAllowed === true) {
        global.jQuery("<p></p>").text("Entrada do tenant pode ser bloqueada durante o rollout.").appendTo(scenario);
      }
    }
    if (item.deploymentRule && typeof item.deploymentRule === "object") {
      const deployment = item.deploymentRule;
      const block = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
      global.jQuery("<p></p>").text("Regra do deployment: " + String(deployment.mode || "-")).appendTo(block);
      global.jQuery("<p></p>").text("Escopo de aplicacao: " + String(deployment.applyScope || "-")).appendTo(block);
      global.jQuery("<p></p>").text("Escopo de rollout: " + String(deployment.rolloutScope || "-")).appendTo(block);
      global.jQuery("<p></p>").text("Escopo de anuencia: " + String(deployment.consentScope || "-")).appendTo(block);
      if (deployment.runtimeEnvironmentCode) {
        global.jQuery("<p></p>").text("Runtime alvo: " + String(deployment.runtimeEnvironmentCode || "-")).appendTo(block);
      }
      if (deployment.sharedRuntimeSubscriberCount) {
        global.jQuery("<p></p>").text("Assinantes no mesmo runtime: " + String(deployment.sharedRuntimeSubscriberCount || 0)).appendTo(block);
      }
      global.jQuery("<p></p>").text("Ativacao por tenant: " + ((deployment.supportsPerTenantActivation === true) ? "suportada" : "nao suportada")).appendTo(block);
    }
    if (item.compatibilityPrecheck && Array.isArray(item.compatibilityPrecheck.checks)) {
      const precheck = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
      global.jQuery("<p></p>").text("Pre-check: " + String(item.compatibilityPrecheck.status || "-")).appendTo(precheck);
      const list = global.jQuery("<ul></ul>").appendTo(precheck);
      item.compatibilityPrecheck.checks.forEach(function(check) {
        global.jQuery("<li></li>")
          .text("[" + String(check.status || "-") + "] " + String(check.title || "-") + ": " + String(check.message || ""))
          .appendTo(list);
      });
    }
    const changelog = Array.isArray(item.changelog) ? item.changelog : [];
    if (changelog.length) {
      const changelogCard = global.jQuery("<div class=\"manual-summary\"></div>").appendTo(this.detailSummaryElement);
      global.jQuery("<p></p>").text("Changelog estruturado").appendTo(changelogCard);
      changelog.forEach(function(section) {
        global.jQuery("<p></p>").text(String(section.title || "-")).appendTo(changelogCard);
        const sectionList = global.jQuery("<ul></ul>").appendTo(changelogCard);
        global.CrudUtils.ensureArray(section.items).forEach(function(entry) {
          global.jQuery("<li></li>").text(String(entry || "")).appendTo(sectionList);
        });
      });
    }
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
    if (item.summary && item.summary.rolloutAudit) {
      global.jQuery("<p></p>")
        .text("Rollout: " + String(item.summary.rolloutAudit.stage || "-") + (item.summary.rolloutAudit.batchCode ? " | lote " + String(item.summary.rolloutAudit.batchCode) : ""))
        .appendTo(wrap);
    }
  };

  SystemUpdatesAdmin.prototype.formatVersionList = function(items) {
    const values = global.CrudUtils.ensureArray(items).filter(function(item) {
      return String(item || "").trim() !== "";
    });
    return values.length ? values.join(", ") : "-";
  };

  SystemUpdatesAdmin.prototype.formatStepList = function(items) {
    const values = global.CrudUtils.ensureArray(items).map(function(item) {
      if (item && typeof item === "object") {
        return String(item.title || item.code || "").trim();
      }
      return String(item || "").trim();
    }).filter(function(item) {
      return item !== "";
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

  SystemUpdatesAdmin.prototype.handleSimulate = function() {
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    this.request("GET", "/api/admin/system-updates/simulate", {
      version: this.currentRelease.version,
      subscriberCode: this.currentSubscriberCode || "",
      batchCode: this.currentSubscriberCode ? "" : (this.currentBatchCode || "")
    }).then((payload) => {
      this.currentExecution = null;
      this.renderDetail(payload);
      global.CrudUtils.showMessage("Simulacao carregada.", "info");
    }).catch((error) => this.showError(error, "Nao foi possivel simular a release."));
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

  SystemUpdatesAdmin.prototype.handleTenantActivation = function() {
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode) {
      global.CrudUtils.showMessage("Selecione o assinante alvo da ativacao.", "warning");
      return;
    }
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    const release = this.currentRelease;
    const currentStatus = String(release.tenantActivationStatus || "pending");
    const nextStatus = currentStatus === "enabled" ? "disabled" : "enabled";
    const actionLabel = nextStatus === "enabled" ? "ativar" : "desativar";
    global.CrudUtils.confirm(
      "Deseja " + actionLabel + " esta release para o assinante selecionado?",
      { title: nextStatus === "enabled" ? "Ativar tenant" : "Desativar tenant" }
    ).then((confirmed) => {
      if (!confirmed) {
        return;
      }
      this.request("POST", "/api/admin/system-updates/tenant-activation", {
        version: release.version,
        status: nextStatus,
        reason: nextStatus === "enabled" ? "Ativacao pela tela administrativa." : "Desativacao pela tela administrativa.",
        subscriberCode: this.currentSubscriberCode || ""
      }).then(() => {
        global.CrudUtils.showMessage("Ativacao por tenant atualizada.", "success");
        return this.loadBootstrap();
      }).catch((error) => this.showError(error, "Nao foi possivel atualizar a ativacao por tenant."));
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
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode && !this.currentBatchCode) {
      global.CrudUtils.showMessage("Selecione um lote SaaS para o rollout progressivo.", "warning");
      return;
    }
    this.request("POST", "/api/admin/system-updates/dispatch-rollout", {
      version: this.currentRelease.version,
      subscriberCode: this.currentSubscriberCode || "",
      batchCode: this.currentSubscriberCode ? "" : (this.currentBatchCode || "")
    }).then((payload) => {
      this.currentExecution = null;
      this.renderDetail(payload);
      global.CrudUtils.showMessage("Rollout SaaS despachado.", "success");
    }).catch((error) => this.showError(error, "Nao foi possivel despachar o rollout SaaS."));
  };

  SystemUpdatesAdmin.prototype.handleRollback = function() {
    if (!this.currentRelease) {
      global.CrudUtils.showMessage("Selecione uma release.", "warning");
      return;
    }
    if (this.centralControl.centralControl === true && !this.currentSubscriberCode) {
      global.CrudUtils.showMessage("Selecione o assinante alvo do rollback.", "warning");
      return;
    }
    global.CrudUtils.confirm(
      "Deseja executar o rollback formal da release " + this.currentRelease.version + "?",
      { title: "Executar rollback" }
    ).then((confirmed) => {
      if (!confirmed) {
        return;
      }
      this.request("POST", "/api/admin/system-updates/rollback", {
        version: this.currentRelease.version,
        subscriberCode: this.currentSubscriberCode || "",
        reason: "Rollback solicitado pela tela administrativa."
      }).then((payload) => {
        this.currentExecution = null;
        this.renderDetail(payload);
        this.loadBootstrap();
        global.CrudUtils.showMessage("Rollback executado.", "success");
      }).catch((error) => this.showError(error, "Nao foi possivel executar o rollback."));
    });
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
