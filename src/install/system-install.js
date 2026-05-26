(function(global) {
  "use strict";

  function SystemInstallPage(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#system-install-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.status = null;
    this.lastPrecheck = null;
    this.lastRun = null;
  }

  SystemInstallPage.SYSTEM_ROLES = [
    { value: "onprem", text: "On-premise" },
    { value: "saas_central", text: "Central SaaS" },
    { value: "runtime", text: "Runtime comum" }
  ];

  SystemInstallPage.BACKUP_POLICIES = [
    { value: "", text: "Selecione quando for reinstalacao" },
    { value: "validated", text: "Backup validado" },
    { value: "skip_with_reason", text: "Pular backup com justificativa" },
    { value: "discardable_test", text: "Ambiente descartavel/teste" }
  ];

  SystemInstallPage.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("system-install-root");
    this.renderShell();
    this.loadStatus();
  };

  SystemInstallPage.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"system-install-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"system-install-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Instalacao do sistema").appendTo(title);
    global.jQuery("<p></p>").text("Executa as etapas iniciais fechadas: migrations, seed, assinante principal, administrador e catalogo padrao.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    const grid = global.jQuery("<div class=\"system-install-grid\"></div>").appendTo(shell);
    this.leftColumn = global.jQuery("<div class=\"system-install-stack\"></div>").appendTo(grid);
    this.rightColumn = global.jQuery("<div class=\"system-install-stack\"></div>").appendTo(grid);

    this.renderAccessCard();
    this.renderActivationCard();
    this.renderDatabaseCard();
    this.renderSubscriberCard();
    this.renderOptionsCard();
    this.renderResultCard();
  };

  SystemInstallPage.prototype.renderActivationCard = function() {
    const card = this.createCard(this.leftColumn, "Ativacao");
    this.activationPanel = global.jQuery("<div class=\"system-install-panel system-install-activation\"></div>").text("Aguardando sessao criada pelo instalador compilado.").appendTo(card.body);
  };

  SystemInstallPage.prototype.renderAccessCard = function() {
    const card = this.createCard(this.leftColumn, "Senha do instalador");
    this.installerPasswordInput = this.createTextField(card.body, "Senha do instalador", "password");
    this.reinstallConfirmedCheckbox = this.createCheckboxField(card.body, "Confirmo que desejo reinstalar quando o sistema ja estiver instalado", false);
    this.backupPolicySelect = this.createSelectField(card.body, "Politica de backup para reinstalacao", SystemInstallPage.BACKUP_POLICIES, "");
    this.backupJustificationInput = this.createTextField(card.body, "Justificativa quando pular backup");
    this.statusPanel = global.jQuery("<div class=\"system-install-panel\"></div>").text("Status ainda nao carregado.").appendTo(card.body);
    const actions = global.jQuery("<div class=\"system-install-actions\"></div>").appendTo(card.body);
    this.createButton(actions, "Atualizar status", "reload", this.loadStatus.bind(this));
  };

  SystemInstallPage.prototype.renderDatabaseCard = function() {
    const card = this.createCard(this.leftColumn, "Banco e ambiente");
    this.databaseUrlInput = this.createTextField(card.body, "DATABASE_URL", "text");
    this.databaseEnvironmentInput = this.createTextField(card.body, "Ambiente do banco");
    this.databaseIdentityInput = this.createTextField(card.body, "Identidade do banco");
    this.systemRoleSelect = this.createSelectField(card.body, "Papel do sistema", SystemInstallPage.SYSTEM_ROLES, "onprem");
    this.mailerDsnInput = this.createTextField(card.body, "MAILER_DSN");
    this.saveEnvCheckbox = this.createCheckboxField(card.body, "Salvar chaves permitidas em backend/.env.local", true);
    this.createDatabaseCheckbox = this.createCheckboxField(card.body, "Criar banco se nao existir", true);
    this.centralControlCheckbox = this.createCheckboxField(card.body, "Habilitar central SaaS", false);
    this.authRequiredCheckbox = this.createCheckboxField(card.body, "Exigir autenticacao depois da instalacao", true);
  };

  SystemInstallPage.prototype.renderSubscriberCard = function() {
    const card = this.createCard(this.leftColumn, "Assinante e administrador");
    this.subscriberCodeInput = this.createTextField(card.body, "Codigo do assinante");
    this.subscriberNameInput = this.createTextField(card.body, "Nome do assinante");
    this.subscriberDocumentInput = this.createTextField(card.body, "Documento");
    this.userTenantIdInput = this.createTextField(card.body, "Tenant do usuario");
    this.adminUsernameInput = this.createTextField(card.body, "Usuario admin");
    this.adminPasswordInput = this.createTextField(card.body, "Senha admin", "password");
    this.adminDisplayNameInput = this.createTextField(card.body, "Nome do admin");
    this.adminEmailInput = this.createTextField(card.body, "Email do admin");
    this.principalCheckbox = this.createCheckboxField(card.body, "Marcar como assinante principal", true);
    this.forcePasswordChangeCheckbox = this.createCheckboxField(card.body, "Exigir troca de senha no primeiro acesso", true);
    const actions = global.jQuery("<div class=\"system-install-actions\"></div>").appendTo(card.body);
    this.createButton(actions, "Gerar senha forte", "lock", this.fillStrongPassword.bind(this));
  };

  SystemInstallPage.prototype.renderOptionsCard = function() {
    const card = this.createCard(this.rightColumn, "Etapas");
    this.runSeedCheckbox = this.createCheckboxField(card.body, "Executar seed runtime", true);
    this.publishDefaultsCheckbox = this.createCheckboxField(card.body, "Publicar e validar catalogo padrao", true);
    this.runIntegrityCheckbox = this.createCheckboxField(card.body, "Validar integridade estrutural", true);
    this.stepsPanel = global.jQuery("<div class=\"system-install-panel\"></div>").text("As etapas aparecem aqui.").appendTo(card.body);
    const actions = global.jQuery("<div class=\"system-install-actions\"></div>").appendTo(card.body);
    this.createButton(actions, "Validar", "check", this.handlePrecheck.bind(this));
    this.runButton = this.createButton(actions, "Executar instalacao", "play", this.handleRun.bind(this), "primary");
  };

  SystemInstallPage.prototype.renderResultCard = function() {
    const card = this.createCard(this.rightColumn, "Resultado");
    this.checklistPanel = global.jQuery("<div class=\"system-install-panel\"></div>").text("A validacao aparece aqui.").appendTo(card.body);
    this.outputPanel = global.jQuery("<pre class=\"system-install-output\"></pre>").text("A saida das etapas aparece aqui.").appendTo(card.body);
  };

  SystemInstallPage.prototype.loadStatus = function() {
    this.setStatus("Carregando");
    return this.request("GET", "/api/install/status").then((payload) => {
      this.status = payload || {};
      this.applyDefaultsFromStatus();
      this.renderStatus();
      this.renderSteps(payload.steps || []);
      this.setStatus(payload.systemInstalled ? "Instalado" : "Nao instalado");
      return payload;
    }).catch((error) => {
      this.setStatus("Falha");
      this.showError(error, "Nao foi possivel carregar o status do instalador.");
    });
  };

  SystemInstallPage.prototype.applyDefaultsFromStatus = function() {
    if (!this.status || this.defaultsApplied) {
      return;
    }
    const environment = this.status.environment || {};
    const activation = this.status.activation || {};
    this.databaseEnvironmentInput.value(environment.databaseEnvironment || "prod");
    this.databaseIdentityInput.value(environment.databaseIdentity || "install:principal");
    this.systemRoleSelect.value(activation.profile === "system_builder" ? "saas_central" : (environment.centralControl ? "saas_central" : "onprem"));
    this.centralControlCheckbox.prop("checked", activation.profile === "system_builder" || environment.centralControl === true);
    this.authRequiredCheckbox.prop("checked", environment.authRequired !== false);
    this.subscriberCodeInput.value(activation.subscriberCode || "principal");
    this.subscriberNameInput.value("Principal");
    this.userTenantIdInput.value("default");
    this.adminUsernameInput.value("admin");
    this.adminDisplayNameInput.value("Administrador");
    this.mailerDsnInput.value("null://null");
    this.defaultsApplied = true;
  };

  SystemInstallPage.prototype.renderStatus = function() {
    const status = this.status || {};
    const activation = status.activation || {};
    const lines = [
      "Sistema instalado: " + (status.systemInstalled ? "sim" : "nao"),
      "Senha salva: " + (status.installerPasswordConfigured ? "sim" : "nao"),
      "Senha exigida: " + (status.requiresInstallerPassword ? "sim" : "nao"),
      "Banco atual disponivel: " + (status.databaseAvailable ? "sim" : "nao"),
      "Tabela auth_user: " + (status.authUserTableExists ? "existe" : "nao detectada"),
      "Usuarios: " + (status.authUserCount == null ? "-" : String(status.authUserCount))
    ];
    if (status.lockReason) {
      lines.push("");
      lines.push(status.lockReason);
    }
    this.statusPanel.text(lines.join("\n"));
    this.renderActivation(activation);
  };

  SystemInstallPage.prototype.renderActivation = function(activation) {
    if (!this.activationPanel) {
      return;
    }
    const lines = [
      "Ativacao obrigatoria: " + (activation.required ? "sim" : "nao"),
      "Ativacao valida: " + (activation.valid ? "sim" : "nao"),
      "Perfil liberado: " + String(activation.profileLabel || "-"),
      "Codigo do assinante: " + String(activation.subscriberCode || "-"),
      "Modo preparado: " + String(activation.mode || "-"),
      "Sessao: " + String(activation.sessionId || "-"),
      "Validade: " + String(activation.expiresAt || "-"),
      "",
      String(activation.message || "")
    ];
    this.activationPanel
      .toggleClass("system-install-activation-ok", activation.valid === true)
      .toggleClass("system-install-activation-error", activation.valid !== true)
      .text(lines.join("\n"));
  };

  SystemInstallPage.prototype.handlePrecheck = function() {
    const payload = this.collectPayload();
    this.request("POST", "/api/install/precheck", payload).then((response) => {
      this.lastPrecheck = response || {};
      this.renderPrecheck(this.lastPrecheck);
      this.renderSteps(this.lastPrecheck.steps || []);
      global.CrudUtils.showMessage(response.hasBlockingIssues ? "Existem bloqueios na instalacao." : "Validacao concluida.", response.hasBlockingIssues ? "warning" : "success");
    }).catch((error) => this.showError(error, "Nao foi possivel validar a instalacao."));
  };

  SystemInstallPage.prototype.handleRun = function() {
    const payload = this.collectPayload();
    const reinstall = this.status && this.status.systemInstalled === true;
    const proceed = () => {
      this.setStatus("Executando");
      this.runButton.prop("disabled", true);
      this.request("POST", "/api/install/run", payload).then((response) => {
        this.lastRun = response || {};
        this.renderRun(this.lastRun);
        this.setStatus(response.success ? "Concluido" : "Falha");
        global.CrudUtils.showMessage(response.success ? "Instalacao concluida." : "Instalacao falhou. Revise a saida.", response.success ? "success" : "error");
      }).catch((error) => {
        this.setStatus("Falha");
        this.showError(error, "Nao foi possivel executar a instalacao.");
      }).finally(() => {
        this.runButton.prop("disabled", false);
      });
    };

    if (global.CrudUtils && typeof global.CrudUtils.confirm === "function") {
      global.CrudUtils.confirm(reinstall ? "O sistema ja esta instalado. Confirma reinstalar agora?" : "Executar a instalacao agora?", {
        title: reinstall ? "Confirmar reinstalacao" : "Confirmar instalacao",
        confirmText: reinstall ? "Reinstalar" : "Executar",
        confirmIcon: "play"
      }).then(function(confirmed) {
        if (confirmed) {
          proceed();
        }
      });
      return;
    }
    proceed();
  };

  SystemInstallPage.prototype.collectPayload = function() {
    const role = this.systemRoleSelect.value();
    return {
      installerPassword: this.installerPasswordInput.value(),
      reinstallConfirmed: this.reinstallConfirmedCheckbox.is(":checked"),
      backupPolicy: this.backupPolicySelect.value(),
      backupJustification: this.backupJustificationInput.value(),
      databaseUrl: this.databaseUrlInput.value(),
      saveEnv: this.saveEnvCheckbox.is(":checked"),
      createDatabase: this.createDatabaseCheckbox.is(":checked"),
      databaseEnvironment: this.databaseEnvironmentInput.value(),
      databaseIdentity: this.databaseIdentityInput.value(),
      systemRole: role,
      centralControl: this.centralControlCheckbox.is(":checked") || role === "saas_central",
      authRequired: this.authRequiredCheckbox.is(":checked"),
      mailerDsn: this.mailerDsnInput.value(),
      subscriberCode: this.subscriberCodeInput.value(),
      subscriberName: this.subscriberNameInput.value(),
      subscriberDocument: this.subscriberDocumentInput.value(),
      principal: this.principalCheckbox.is(":checked"),
      userTenantId: this.userTenantIdInput.value(),
      adminUsername: this.adminUsernameInput.value(),
      adminPassword: this.adminPasswordInput.value(),
      adminDisplayName: this.adminDisplayNameInput.value(),
      adminEmail: this.adminEmailInput.value(),
      forcePasswordChange: this.forcePasswordChangeCheckbox.is(":checked"),
      runSeed: this.runSeedCheckbox.is(":checked"),
      publishDefaults: this.publishDefaultsCheckbox.is(":checked"),
      runIntegrity: this.runIntegrityCheckbox.is(":checked")
    };
  };

  SystemInstallPage.prototype.renderPrecheck = function(precheck) {
    const lines = [];
    lines.push("Bloqueios: " + String((precheck.blockingIssues || []).length));
    lines.push("Avisos: " + String((precheck.warnings || []).length));
    lines.push("");
    (precheck.checklist || []).forEach(function(item) {
      lines.push("[" + String(item.status || "-").toUpperCase() + "] " + String(item.label || item.code || "-") + " - " + String(item.message || ""));
    });
    if ((precheck.blockingIssues || []).length) {
      lines.push("");
      lines.push("Bloqueios:");
      precheck.blockingIssues.forEach(function(item) {
        lines.push("- " + String(item.message || item.code || "-"));
      });
    }
    if ((precheck.warnings || []).length) {
      lines.push("");
      lines.push("Avisos:");
      precheck.warnings.forEach(function(item) {
        lines.push("- " + String(item.message || item.code || "-"));
      });
    }
    this.checklistPanel.text(lines.join("\n"));
  };

  SystemInstallPage.prototype.renderRun = function(result) {
    this.renderPrecheck(result.precheck || {});
    this.renderSteps(result.steps || []);
    this.outputPanel.text(result.outputTail || JSON.stringify(result, null, 2));
  };

  SystemInstallPage.prototype.renderSteps = function(steps) {
    const items = (steps || []).map(function(step) {
      const duration = step.durationSeconds ? " (" + step.durationSeconds + "s)" : "";
      const message = step.message ? " - " + step.message : "";
      return "<li><strong>" + escapeHtml(step.label || step.code || "-") + "</strong>: " + escapeHtml(step.status || "pending") + escapeHtml(duration + message) + "</li>";
    }).join("");
    this.stepsPanel.html(items ? "<ul class=\"system-install-steps\">" + items + "</ul>" : "Nenhuma etapa carregada.");
  };

  SystemInstallPage.prototype.fillStrongPassword = function() {
    const value = "CpG!" + Math.random().toString(36).slice(2, 9) + "A9#" + Math.random().toString(36).slice(2, 9).toUpperCase();
    this.adminPasswordInput.value(value);
    global.CrudUtils.showMessage("Senha forte sugerida no formulario.", "info");
  };

  SystemInstallPage.prototype.request = function(method, url, data) {
    return this.httpClient.request({ method: method, url: url, data: data || {} });
  };

  SystemInstallPage.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"system-install-card\"></section>").appendTo(container);
    global.jQuery("<h2></h2>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"system-install-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  SystemInstallPage.prototype.createTextField = function(container, label, type) {
    const field = global.jQuery("<label class=\"system-install-field\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    const input = global.jQuery("<input>").attr("type", type || "text").appendTo(field);
    input.kendoTextBox();
    return input.data("kendoTextBox");
  };

  SystemInstallPage.prototype.createSelectField = function(container, label, items, value) {
    const field = global.jQuery("<label class=\"system-install-field\"></label>").appendTo(container);
    global.jQuery("<span></span>").text(label).appendTo(field);
    const input = global.jQuery("<input>").appendTo(field);
    input.kendoDropDownList({
      dataSource: items || [],
      dataTextField: "text",
      dataValueField: "value",
      value: value || ""
    });
    return input.data("kendoDropDownList");
  };

  SystemInstallPage.prototype.createCheckboxField = function(container, label, checked) {
    const field = global.jQuery("<label class=\"system-install-checkbox\"></label>").appendTo(container);
    const input = global.jQuery("<input type=\"checkbox\">").prop("checked", checked === true).appendTo(field);
    global.jQuery("<span></span>").text(label).appendTo(field);
    return input;
  };

  SystemInstallPage.prototype.createButton = function(container, text, icon, handler, themeColor) {
    const button = global.jQuery("<button type=\"button\"></button>").text(text).appendTo(container);
    button.kendoButton({
      icon: icon || undefined,
      themeColor: themeColor || undefined
    });
    button.on("click", handler);
    return button;
  };

  SystemInstallPage.prototype.setStatus = function(text) {
    this.statusBadge.text(text || "");
  };

  SystemInstallPage.prototype.showError = function(error, fallback) {
    const message = error && error.error && error.error.message || error && error.message || fallback;
    const details = error && error.error && error.error.details || {};
    if (details && details.checklist) {
      this.renderPrecheck(details);
    }
    global.CrudUtils.showMessage(message, "error");
  };

  function escapeHtml(text) {
    return String(text || "").replace(/[&<>\"']/g, function(char) {
      return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" })[char] || char;
    });
  }

  global.SystemInstallPage = SystemInstallPage;
})(window);
