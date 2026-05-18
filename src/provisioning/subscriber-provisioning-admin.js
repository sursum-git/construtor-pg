(function(global) {
  "use strict";

  function SubscriberProvisioningAdmin(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#subscriber-provisioning-admin-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.activeJobId = 0;
    this.activeEventSource = null;
    this.activePollTimer = 0;
    this.subscribers = [];
    this.jobs = [];
    this.centralControl = {};
    this.runtimeEnvironments = [];
    this.operationalMatrix = [];
    this.isolationCatalog = { summary: {}, items: [] };
  }

  SubscriberProvisioningAdmin.DEPLOYMENT_MODES = [
    { value: "shared_program_shared_db", text: "Programa e banco compartilhados por coluna de assinante" },
    { value: "shared_program_dedicated_db", text: "Programa compartilhado e banco dedicado" },
    { value: "dedicated_stack", text: "Container e banco dedicados no SaaS" },
    { value: "onprem_remote", text: "Instalacao on-premise remota" }
  ];

  SubscriberProvisioningAdmin.UPDATE_CHANNELS = [
    { value: "stable", text: "Stable" },
    { value: "pilot", text: "Pilot" },
    { value: "canary", text: "Canary" },
    { value: "lts", text: "LTS" }
  ];

  SubscriberProvisioningAdmin.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.loadBootstrap();
  };

  SubscriberProvisioningAdmin.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell subscriber-provisioning-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Provisionamento de assinantes").appendTo(title);
    global.jQuery("<p></p>").text("Cria o assinante, dispara o provisionamento SaaS por job e gera pacote on-premise.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    const content = global.jQuery("<div class=\"program-governance-admin-grid\"></div>").appendTo(shell);
    this.leftColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(content);
    this.rightColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(content);

    this.renderSubscriberCard();
    this.renderJobCard();
    this.renderRuntimeEnvironmentCard();
    this.renderIsolationCatalogCard();
  };

  SubscriberProvisioningAdmin.prototype.renderSubscriberCard = function() {
    const card = this.createCard(this.leftColumn, "Assinantes");
    const body = card.body;
    const toolbar = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(body);
    this.createButton(toolbar, "Atualizar", "reload", this.loadBootstrap.bind(this));

    this.subscribersGridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(body);
    const form = global.jQuery("<div class=\"program-builder-side-section\"></div>").appendTo(body);
    this.codeInput = this.createTextField(form, "Codigo");
    this.nameInput = this.createTextField(form, "Nome");
    this.documentInput = this.createTextField(form, "Documento");
    this.deploymentModeSelect = this.createSelectField(form, "Modelo de deployment", SubscriberProvisioningAdmin.DEPLOYMENT_MODES, "dedicated_stack", this.syncDeploymentHint.bind(this));
    this.runtimeEnvironmentCodeInput = this.createTextField(form, "Ambiente runtime");
    this.primaryEnvironmentCodeInput = this.createTextField(form, "Ambiente principal isolado");
    this.updateChannelSelect = this.createSelectField(form, "Canal de update", SubscriberProvisioningAdmin.UPDATE_CHANNELS, "stable");
    this.deploymentHint = global.jQuery("<div class=\"program-builder-inline-hint\"></div>").appendTo(form);
    this.instanceCodeInput = this.createTextField(form, "Instance code");
    this.databaseEnvironmentInput = this.createTextField(form, "Ambiente do banco");
    this.databaseIdentityInput = this.createTextField(form, "Identidade do banco");
    this.databaseNameInput = this.createTextField(form, "Nome do banco");
    this.adminUsernameInput = this.createTextField(form, "Usuario admin");
    this.adminDisplayNameInput = this.createTextField(form, "Nome do admin");
    this.adminEmailInput = this.createTextField(form, "Email do admin");
    this.adminPasswordInput = this.createTextField(form, "Senha do admin", "password");
    this.principalCheckbox = this.createCheckboxField(form, "Assinante principal");
    this.enabledCheckbox = this.createCheckboxField(form, "Ativo", true);

    const actions = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(form);
    this.createButton(actions, "Salvar assinante", "save", this.handleSaveSubscriber.bind(this));
    this.createButton(actions, "Criar ambiente", "play", this.handleProvision.bind(this));
    this.createButton(actions, "Baixar pacote on-premise", "download", this.handleDownloadOnPrem.bind(this));
    this.syncDeploymentHint();
  };

  SubscriberProvisioningAdmin.prototype.renderJobCard = function() {
    const card = this.createCard(this.rightColumn, "Jobs de provisionamento");
    const body = card.body;
    this.jobsGridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(body);
    this.jobDetailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(body);
    this.jobDetailElement.text("Nenhum job selecionado.");
  };

  SubscriberProvisioningAdmin.prototype.renderRuntimeEnvironmentCard = function() {
    const card = this.createCard(this.rightColumn, "Ambientes runtime compartilhados");
    const body = card.body;
    this.runtimeEnvironmentGridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(body);
    this.runtimeEnvironmentDetailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(body);
    this.runtimeEnvironmentDetailElement.text("Nenhum ambiente runtime carregado.");
  };

  SubscriberProvisioningAdmin.prototype.renderIsolationCatalogCard = function() {
    const card = this.createCard(this.leftColumn, "Catalogo de isolamento");
    const body = card.body;
    this.isolationSummaryElement = global.jQuery("<div class=\"program-builder-inline-hint\"></div>").appendTo(body);
    this.isolationGridElement = global.jQuery("<div class=\"program-builder-governance-list\"></div>").appendTo(body);
    this.isolationDetailElement = global.jQuery("<div class=\"program-builder-json-preview\"></div>").appendTo(body);
    this.isolationDetailElement.text("Nenhuma entidade selecionada.");
  };

  SubscriberProvisioningAdmin.prototype.loadBootstrap = function(preferredSubscriberCode) {
    this.setStatus("Carregando");
    return this.request("GET", "/api/admin/subscriber-provisioning/bootstrap").then((payload) => {
      this.centralControl = payload.centralControl || {};
      this.subscribers = global.CrudUtils.ensureArray(payload.subscribers);
      this.jobs = global.CrudUtils.ensureArray(payload.jobs);
      this.runtimeEnvironments = global.CrudUtils.ensureArray(payload.runtimeEnvironments);
      this.operationalMatrix = global.CrudUtils.ensureArray(payload.operationalMatrix);
      this.isolationCatalog = payload.isolationCatalog || { summary: {}, items: [] };
      if (this.centralControl.centralControl !== true) {
        global.CrudUtils.showMessage("Esta tela existe apenas no sistema central SaaS.", "warning");
      }
      this.renderSubscribersGrid(preferredSubscriberCode || this.currentSubscriberCode || "");
      this.renderJobsGrid();
      this.renderRuntimeEnvironmentGrid();
      this.renderIsolationCatalogGrid();
      this.setStatus("Pronto");
      return payload;
    }).catch((error) => {
      this.setStatus("Falha");
      this.showError(error, "Nao foi possivel carregar o provisionamento.");
      throw error;
    });
  };

  SubscriberProvisioningAdmin.prototype.renderSubscribersGrid = function(preferredSubscriberCode) {
    const self = this;
    const rows = this.subscribers.map(function(item) {
      return {
        code: item.code,
        name: item.name,
        deploymentMode: item.deploymentModeLabel || item.deploymentMode || "-",
        runtimeEnvironmentCode: item.runtimeEnvironmentCode || "-",
        primaryEnvironmentCode: item.primaryEnvironmentCode || "-",
        updateChannel: item.updateChannel || "stable",
        latestSuccessfulVersion: item.latestSuccessfulVersion || "-",
        versionStatus: item.versionStatus || "sem-historico",
        runtimeSubscriberCount: item.runtimeSubscriberCount || 0,
        databaseEnvironment: item.databaseEnvironment || "-",
        databaseIdentity: item.databaseIdentity || "-",
        updatedAt: item.updatedAt || ""
      };
    });
    if (this.subscribersGrid) {
      this.subscribersGrid.destroy();
      this.subscribersGridElement.empty();
    }
    this.subscribersGridElement.kendoGrid({
      dataSource: { data: rows },
      sortable: true,
      selectable: "row",
      scrollable: true,
      height: 280,
      columns: [
        { field: "code", title: "Codigo", width: 160 },
        { field: "name", title: "Nome" },
        { field: "deploymentMode", title: "Modelo", width: 240 },
        { field: "runtimeEnvironmentCode", title: "Runtime", width: 180 },
        { field: "runtimeSubscriberCount", title: "Assinantes no runtime", width: 160 },
        { field: "updateChannel", title: "Canal", width: 110 },
        { field: "latestSuccessfulVersion", title: "Versao", width: 120 },
        { field: "versionStatus", title: "Status", width: 120 },
        { field: "updatedAt", title: "Atualizado", width: 180 }
      ],
      change: function() {
        const selected = this.dataItem(this.select());
        if (!selected) {
          return;
        }
        const subscriber = self.subscribers.find(function(item) {
          return item.code === selected.code;
        });
        if (subscriber) {
          self.applySubscriber(subscriber);
        }
      }
    });
    this.subscribersGrid = this.subscribersGridElement.data("kendoGrid");
    if (this.subscribers.length) {
      const preferred = this.subscribers.find(function(item) {
        return item.code === preferredSubscriberCode;
      }) || this.subscribers[0];
      this.applySubscriber(preferred);
      const rows = this.subscribersGrid.tbody.find("tr");
      rows.each(function() {
        const item = self.subscribersGrid.dataItem(this);
        if (item && item.code === preferred.code) {
          self.subscribersGrid.select(this);
        }
      });
    }
  };

  SubscriberProvisioningAdmin.prototype.renderJobsGrid = function() {
    const self = this;
    const rows = this.jobs.map(function(item) {
      return {
        id: item.id,
        subscriberCode: item.subscriberCode || "-",
        status: item.status || "-",
        createdAt: item.createdAt || "",
        finishedAt: item.finishedAt || ""
      };
    });
    if (this.jobsGrid) {
      this.jobsGrid.destroy();
      this.jobsGridElement.empty();
    }
    this.jobsGridElement.kendoGrid({
      dataSource: { data: rows },
      sortable: true,
      selectable: "row",
      scrollable: true,
      height: 280,
      columns: [
        { field: "id", title: "Job", width: 90 },
        { field: "subscriberCode", title: "Assinante", width: 160 },
        { field: "status", title: "Status", width: 120 },
        { field: "createdAt", title: "Criado", width: 180 },
        { field: "finishedAt", title: "Finalizado", width: 180 }
      ],
      change: function() {
        const selected = this.dataItem(this.select());
        if (selected) {
          self.selectJob(selected.id);
        }
      }
    });
    this.jobsGrid = this.jobsGridElement.data("kendoGrid");
  };

  SubscriberProvisioningAdmin.prototype.renderRuntimeEnvironmentGrid = function() {
    const self = this;
    const rows = this.runtimeEnvironments.map(function(item) {
      return {
        runtimeEnvironmentCode: item.runtimeEnvironmentCode || "-",
        subscriberCount: item.subscriberCount || 0,
        sharedRuntime: item.sharedRuntime === true ? "sim" : "nao",
        latestSuccessfulVersions: global.CrudUtils.ensureArray(item.latestSuccessfulVersions).join(", ") || "-",
        divergences: global.CrudUtils.ensureArray(item.divergences).length
      };
    });
    if (this.runtimeEnvironmentGrid) {
      this.runtimeEnvironmentGrid.destroy();
      this.runtimeEnvironmentGridElement.empty();
    }
    this.runtimeEnvironmentGridElement.kendoGrid({
      dataSource: { data: rows },
      sortable: true,
      selectable: "row",
      scrollable: true,
      height: 240,
      columns: [
        { field: "runtimeEnvironmentCode", title: "Runtime", width: 180 },
        { field: "subscriberCount", title: "Assinantes", width: 100 },
        { field: "sharedRuntime", title: "Compartilhado", width: 120 },
        { field: "latestSuccessfulVersions", title: "Versoes", width: 180 },
        { field: "divergences", title: "Divergencias", width: 120 }
      ],
      change: function() {
        const selected = this.dataItem(this.select());
        if (!selected) {
          return;
        }
        const runtimeEnvironment = self.runtimeEnvironments.find(function(item) {
          return item.runtimeEnvironmentCode === selected.runtimeEnvironmentCode;
        });
        self.runtimeEnvironmentDetailElement.text(JSON.stringify(runtimeEnvironment || {}, null, 2));
      }
    });
    this.runtimeEnvironmentGrid = this.runtimeEnvironmentGridElement.data("kendoGrid");
    if (this.runtimeEnvironments.length) {
      this.runtimeEnvironmentDetailElement.text(JSON.stringify(this.runtimeEnvironments[0], null, 2));
    } else {
      this.runtimeEnvironmentDetailElement.text("Nenhum ambiente runtime carregado.");
    }
  };

  SubscriberProvisioningAdmin.prototype.renderIsolationCatalogGrid = function() {
    const self = this;
    const catalog = this.isolationCatalog || { summary: {}, items: [] };
    const summary = catalog.summary || {};
    this.isolationSummaryElement.text(
      "Globais: " + String(summary.globalTables || 0)
      + " | Filtradas por assinante: " + String(summary.subscriberTables || 0)
      + " | Riscos: " + String(summary.riskTables || 0)
    );
    if (this.isolationGrid) {
      this.isolationGrid.destroy();
      this.isolationGridElement.empty();
    }
    this.isolationGridElement.kendoGrid({
      dataSource: { data: global.CrudUtils.ensureArray(catalog.items) },
      sortable: true,
      selectable: "row",
      scrollable: true,
      height: 240,
      columns: [
        { field: "entityCode", title: "Entidade", width: 160 },
        { field: "tableName", title: "Tabela", width: 180 },
        { field: "scopeLabel", title: "Escopo", width: 180 },
        { field: "subscriberColumnName", title: "Coluna do assinante", width: 160 },
        { field: "riskStatus", title: "Risco", width: 100 }
      ],
      change: function() {
        const selected = this.dataItem(this.select());
        self.isolationDetailElement.text(JSON.stringify(selected || {}, null, 2));
      }
    });
    this.isolationGrid = this.isolationGridElement.data("kendoGrid");
    if (global.CrudUtils.ensureArray(catalog.items).length) {
      this.isolationDetailElement.text(JSON.stringify(catalog.items[0], null, 2));
    } else {
      this.isolationDetailElement.text("Nenhuma entidade persistente catalogada.");
    }
  };

  SubscriberProvisioningAdmin.prototype.applySubscriber = function(subscriber) {
    this.currentSubscriberCode = subscriber.code || "";
    this.codeInput.value(subscriber.code || "");
    this.nameInput.value(subscriber.name || "");
    this.documentInput.value(subscriber.document || "");
    this.instanceCodeInput.value(subscriber.instanceCode || "");
    this.deploymentModeSelect.value(subscriber.deploymentMode || "dedicated_stack");
    this.runtimeEnvironmentCodeInput.value(subscriber.runtimeEnvironmentCode || "");
    this.primaryEnvironmentCodeInput.value(subscriber.primaryEnvironmentCode || "");
    this.updateChannelSelect.value(subscriber.updateChannel || "stable");
    this.databaseEnvironmentInput.value(subscriber.databaseEnvironment || "");
    this.databaseIdentityInput.value(subscriber.databaseIdentity || "");
    this.databaseNameInput.value(subscriber.databaseName || "");
    this.adminUsernameInput.value(subscriber.adminUsername || "");
    this.adminDisplayNameInput.value(subscriber.adminDisplayName || "");
    this.adminEmailInput.value(subscriber.adminEmail || "");
    this.adminPasswordInput.value("");
    this.principalCheckbox.prop("checked", subscriber.principal === true);
    this.enabledCheckbox.prop("checked", subscriber.enabled !== false);
    this.syncDeploymentHint();
  };

  SubscriberProvisioningAdmin.prototype.collectSubscriberPayload = function() {
    return {
      code: this.codeInput.value(),
      name: this.nameInput.value(),
      document: this.documentInput.value(),
      deploymentMode: this.deploymentModeSelect.value(),
      runtimeEnvironmentCode: this.runtimeEnvironmentCodeInput.value(),
      primaryEnvironmentCode: this.primaryEnvironmentCodeInput.value(),
      updateChannel: this.updateChannelSelect.value(),
      instanceCode: this.instanceCodeInput.value(),
      databaseEnvironment: this.databaseEnvironmentInput.value(),
      databaseIdentity: this.databaseIdentityInput.value(),
      databaseName: this.databaseNameInput.value(),
      adminUsername: this.adminUsernameInput.value(),
      adminDisplayName: this.adminDisplayNameInput.value(),
      adminEmail: this.adminEmailInput.value(),
      adminPassword: this.adminPasswordInput.value(),
      principal: this.principalCheckbox.is(":checked"),
      enabled: this.enabledCheckbox.is(":checked")
    };
  };

  SubscriberProvisioningAdmin.prototype.handleSaveSubscriber = function() {
    const payload = this.collectSubscriberPayload();
    this.request("POST", "/api/admin/subscriber-provisioning/subscribers", payload).then((response) => {
      global.CrudUtils.showMessage("Assinante salvo.", "success");
      this.currentSubscriberCode = payload.code || "";
      this.loadBootstrap(this.currentSubscriberCode);
      if (response && response.subscriber) {
        this.applySubscriber(response.subscriber);
      }
    }).catch((error) => this.showError(error, "Nao foi possivel salvar o assinante."));
  };

  SubscriberProvisioningAdmin.prototype.handleProvision = function() {
    const payload = this.collectSubscriberPayload();
    payload.subscriberCode = payload.code;
    this.request("POST", "/api/admin/subscriber-provisioning/provision", payload).then((response) => {
      const job = response && response.job;
      global.CrudUtils.showMessage("Provisionamento enfileirado.", "success");
      this.loadJobs().then(() => {
        if (job && job.id) {
          this.selectJob(job.id, true);
        }
      });
    }).catch((error) => this.showError(error, "Nao foi possivel enfileirar o provisionamento."));
  };

  SubscriberProvisioningAdmin.prototype.handleDownloadOnPrem = function() {
    const payload = this.collectSubscriberPayload();
    payload.subscriberCode = payload.code;
    if (this.httpClient && typeof this.httpClient.downloadOnPremPackage === "function") {
      this.httpClient.downloadOnPremPackage(payload);
      return;
    }
    const url = new URL("/api/admin/subscriber-provisioning/onprem-package", global.location && global.location.href || "http://localhost/");
    Object.keys(payload).forEach(function(key) {
      if (payload[key] != null && payload[key] !== "") {
        url.searchParams.set(key, String(payload[key]));
      }
    });
    const link = document.createElement("a");
    link.href = url.href;
    link.download = "";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  SubscriberProvisioningAdmin.prototype.loadJobs = function() {
    return this.request("GET", "/api/admin/subscriber-provisioning/jobs", {
      subscriberCode: this.currentSubscriberCode || ""
    }).then((payload) => {
      this.jobs = global.CrudUtils.ensureArray(payload.items);
      this.renderJobsGrid();
    });
  };

  SubscriberProvisioningAdmin.prototype.selectJob = function(jobId, follow) {
    this.activeJobId = Number(jobId) || 0;
    if (!this.activeJobId) {
      return;
    }
    this.request("GET", "/api/admin/subscriber-provisioning/jobs/" + this.activeJobId).then((payload) => {
      this.renderJobDetail(payload);
      if (follow) {
        this.followJob(this.activeJobId);
      }
    }).catch((error) => this.showError(error, "Nao foi possivel carregar o job."));
  };

  SubscriberProvisioningAdmin.prototype.followJob = function(jobId) {
    this.stopFollowingJob();
    if (this.httpClient && typeof this.httpClient.subscribeProvisionJob === "function") {
      this.httpClient.subscribeProvisionJob(jobId, this.renderJobDetail.bind(this), this.loadJobs.bind(this));
      return;
    }
    if (global.location && global.location.protocol !== "file:" && typeof global.EventSource === "function" && this.httpClient && typeof this.httpClient.buildRuntimeEventUrl === "function") {
      const url = this.httpClient.buildRuntimeEventUrl("/api/admin/subscriber-provisioning/jobs/" + jobId + "/events");
      const source = new global.EventSource(url);
      source.addEventListener("subscriber-provisioning-job", (event) => {
        const payload = JSON.parse(event.data || "{}");
        this.renderJobDetail(payload);
        if (payload.status === "succeeded" || payload.status === "failed") {
          this.loadJobs();
          this.stopFollowingJob();
        }
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

  SubscriberProvisioningAdmin.prototype.startPoll = function(jobId) {
    const poll = () => {
      this.request("GET", "/api/admin/subscriber-provisioning/jobs/" + jobId).then((payload) => {
        this.renderJobDetail(payload);
        if (payload.status === "succeeded" || payload.status === "failed") {
          this.stopFollowingJob();
          this.loadJobs();
          return;
        }
        this.activePollTimer = global.setTimeout(poll, 1500);
      }).catch(() => {
        this.activePollTimer = global.setTimeout(poll, 3000);
      });
    };
    poll();
  };

  SubscriberProvisioningAdmin.prototype.stopFollowingJob = function() {
    if (this.activeEventSource) {
      this.activeEventSource.close();
      this.activeEventSource = null;
    }
    if (this.activePollTimer) {
      global.clearTimeout(this.activePollTimer);
      this.activePollTimer = 0;
    }
  };

  SubscriberProvisioningAdmin.prototype.renderJobDetail = function(job) {
    this.jobDetailElement.text(JSON.stringify(job || {}, null, 2));
  };

  SubscriberProvisioningAdmin.prototype.request = function(method, url, data) {
    return this.httpClient.request({ method: method, url: url, data: data || {} });
  };

  SubscriberProvisioningAdmin.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(container);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  SubscriberProvisioningAdmin.prototype.createTextField = function(container, label, type) {
    const field = global.jQuery("<label class=\"program-builder-field\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    const input = global.jQuery("<input>").attr("type", type || "text").appendTo(field);
    input.kendoTextBox();
    return input.data("kendoTextBox");
  };

  SubscriberProvisioningAdmin.prototype.createSelectField = function(container, label, items, value, changeHandler) {
    const field = global.jQuery("<label class=\"program-builder-field\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    const input = global.jQuery("<input>").appendTo(field);
    input.kendoDropDownList({
      dataSource: items || [],
      dataTextField: "text",
      dataValueField: "value",
      value: value || "",
      change: changeHandler
    });
    return input.data("kendoDropDownList");
  };

  SubscriberProvisioningAdmin.prototype.createCheckboxField = function(container, label, checked) {
    const field = global.jQuery("<label class=\"program-builder-checkbox\"></label>").appendTo(container);
    const input = global.jQuery("<input type=\"checkbox\">").prop("checked", checked === true).appendTo(field);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return input;
  };

  SubscriberProvisioningAdmin.prototype.createButton = function(container, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\" class=\"k-button k-button-solid-base\"></button>").appendTo(container);
    if (icon) {
      global.jQuery("<span class=\"k-icon\"></span>").addClass("k-i-" + icon).appendTo(button);
    }
    global.jQuery("<span></span>").text(text).appendTo(button);
    button.on("click", handler);
    return button;
  };

  SubscriberProvisioningAdmin.prototype.setStatus = function(text) {
    this.statusBadge.text(text || "");
  };

  SubscriberProvisioningAdmin.prototype.showError = function(error, fallback) {
    const message = error && error.error && error.error.message || error && error.message || fallback;
    global.CrudUtils.showMessage(message, "error");
  };

  SubscriberProvisioningAdmin.prototype.syncDeploymentHint = function() {
    if (!this.deploymentHint || !this.deploymentModeSelect) {
      return;
    }
    const mode = String(this.deploymentModeSelect.value() || "");
    if (mode === "shared_program_shared_db") {
      this.deploymentHint.text("Varios assinantes podem apontar para o mesmo ambiente runtime. O ambiente principal continua isolado e separado do ambiente compartilhado.");
      return;
    }
    if (mode === "shared_program_dedicated_db") {
      this.deploymentHint.text("Os programas permanecem compartilhados, mas cada assinante aponta para um banco proprio.");
      return;
    }
    if (mode === "onprem_remote") {
      this.deploymentHint.text("Cada assinante recebe instalacao propria. O ambiente principal continua isolado para controle e publicacao.");
      return;
    }
    this.deploymentHint.text("Cada assinante usa stack propria no SaaS. O ambiente principal continua isolado e nao se mistura ao ambiente do cliente.");
  };

  global.SubscriberProvisioningAdmin = SubscriberProvisioningAdmin;
})(window);
