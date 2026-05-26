(function(global) {
  "use strict";

  function CentralOperationsAdmin(options) {
    this.options = options || {};
    this.rootSelector = this.options.root || "#central-operations-admin-root";
    this.httpClient = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.payload = {};
    this.currentLicense = null;
    this.currentToken = null;
  }

  CentralOperationsAdmin.prototype.init = function() {
    this.root = global.jQuery(this.rootSelector);
    this.root.empty().addClass("program-builder-root");
    this.renderShell();
    this.load();
  };

  CentralOperationsAdmin.prototype.renderShell = function() {
    const shell = global.jQuery("<section class=\"program-governance-admin-shell\"></section>").appendTo(this.root);
    const header = global.jQuery("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = global.jQuery("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    global.jQuery("<h1></h1>").text("Operacoes da central").appendTo(title);
    global.jQuery("<p></p>").text("Painel operacional de ativacao, licencas, tokens, artefatos, chaves e saude dos assinantes.").appendTo(title);
    this.statusBadge = global.jQuery("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text("Carregando").appendTo(header);

    const actions = global.jQuery("<div class=\"program-builder-toolbar\"></div>").appendTo(header);
    this.createButton(actions, "Atualizar", "reload", this.load.bind(this));
    this.createButton(actions, "Suspender licenca", "lock", this.handleLicenseAction.bind(this, "suspend_license"));
    this.createButton(actions, "Reativar licenca", "unlock", this.handleLicenseAction.bind(this, "activate_license"));
    this.createButton(actions, "Revogar licenca", "cancel", this.handleLicenseAction.bind(this, "revoke_license"));
    this.createButton(actions, "Revogar fingerprint", "unlink-horizontal", this.handleRevokeFingerprint.bind(this));
    this.createButton(actions, "Suspender token", "lock", this.handleTokenAction.bind(this, "suspend_token"));
    this.createButton(actions, "Reativar token", "unlock", this.handleTokenAction.bind(this, "activate_token"));
    this.createButton(actions, "Revogar token", "cancel", this.handleTokenAction.bind(this, "revoke_token"));

    this.summaryPanel = this.createCard(shell, "Resumo").body;
    const grid = global.jQuery("<div class=\"program-governance-admin-grid\"></div>").appendTo(shell);
    this.leftColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);
    this.rightColumn = global.jQuery("<div class=\"program-builder-governance-stack\"></div>").appendTo(grid);

    this.subscribersGridElement = this.createCard(this.leftColumn, "Saude dos assinantes").body.append("<div></div>").children().last();
    this.licensesGridElement = this.createCard(this.leftColumn, "Licencas").body.append("<div></div>").children().last();
    this.tokensGridElement = this.createCard(this.leftColumn, "Tokens internos").body.append("<div></div>").children().last();
    this.alertsGridElement = this.createCard(this.rightColumn, "Alertas e notificacoes").body.append("<div></div>").children().last();
    this.artifactsGridElement = this.createCard(this.rightColumn, "Artefatos").body.append("<div></div>").children().last();
    this.keysGridElement = this.createCard(this.rightColumn, "Chaves").body.append("<div></div>").children().last();
    this.auditGridElement = this.createCard(this.rightColumn, "Auditoria").body.append("<div></div>").children().last();
  };

  CentralOperationsAdmin.prototype.load = function() {
    this.setStatus("Carregando");
    return this.request("GET", "/api/admin/central-operations/dashboard")
      .then((payload) => {
        this.payload = payload || {};
        this.render();
        this.setStatus("Pronto");
      })
      .catch((error) => {
        this.setStatus("Falha");
        this.showError(error, "Nao foi possivel carregar as operacoes da central.");
      });
  };

  CentralOperationsAdmin.prototype.render = function() {
    this.renderSummary();
    this.renderSubscribersGrid();
    this.renderLicensesGrid();
    this.renderTokensGrid();
    this.renderAlertsGrid();
    this.renderArtifactsGrid();
    this.renderKeysGrid();
    this.renderAuditGrid();
  };

  CentralOperationsAdmin.prototype.renderSummary = function() {
    const summary = this.payload.summary || {};
    const policy = this.payload.attemptPolicy || {};
    this.summaryPanel.empty();
    const items = [
      "Licencas ativas: " + String(summary.activeLicenses || 0) + "/" + String(summary.licenseCount || 0),
      "Tokens ativos: " + String(summary.activeServiceTokens || 0) + "/" + String(summary.serviceTokenCount || 0),
      "Falhas de update: " + String(summary.updateFailureCount || 0),
      "Artefatos pendentes: " + String(summary.missingArtifactCount || 0),
      "Chaves pendentes: " + String(summary.missingKeyCount || 0),
      "Alertas: " + String(summary.alertCount || 0),
      "Tentativas: " + String(policy.maxAttempts || 5),
      "Bloqueio: " + String(policy.blockMinutes || 30) + " min"
    ];
    const host = global.jQuery("<div class=\"manual-meta\"></div>").appendTo(this.summaryPanel);
    items.forEach(function(text) {
      global.jQuery("<span class=\"manual-badge\"></span>").text(text).appendTo(host);
    });
    global.jQuery("<p class=\"manual-summary\"></p>")
      .text("Gerado em " + String(this.payload.generatedAt || "-") + ". A tela nao exibe segredo bruto, apenas status e tamanho de chave.")
      .appendTo(this.summaryPanel);
  };

  CentralOperationsAdmin.prototype.renderSubscribersGrid = function() {
    this.buildGrid(this.subscribersGridElement, this.payload.subscribers || [], [
      { field: "subscriberCode", title: "Assinante", width: 130 },
      { field: "subscriberName", title: "Nome" },
      { field: "licenseStatus", title: "Licenca", width: 110 },
      { field: "currentVersion", title: "Versao", width: 100 },
      { field: "lastUpdateStatus", title: "Ultimo update", width: 120 },
      { field: "lastActivationAt", title: "Ultima ativacao", width: 170 },
      { field: "activationCount", title: "Ativ.", width: 80 },
      { field: "fingerprintCount", title: "Hosts", width: 80 }
    ]);
  };

  CentralOperationsAdmin.prototype.renderLicensesGrid = function() {
    this.buildGrid(this.licensesGridElement, this.payload.licenses || [], [
      { field: "subscriberCode", title: "Assinante", width: 130 },
      { field: "subscriberName", title: "Nome" },
      { field: "status", title: "Status", width: 100 },
      { field: "activationEmail", title: "E-mail" },
      { field: "activationCount", title: "Ativ.", width: 80 },
      { field: "maxActivations", title: "Limite", width: 80 },
      { field: "expiresAt", title: "Validade", width: 160 },
      { field: "revokedFingerprintCount", title: "Hosts rev.", width: 110 }
    ], (item) => {
      this.currentLicense = item;
    });
  };

  CentralOperationsAdmin.prototype.renderTokensGrid = function() {
    this.buildGrid(this.tokensGridElement, this.payload.serviceTokens || [], [
      { field: "code", title: "Codigo", width: 150 },
      { field: "name", title: "Nome" },
      { field: "status", title: "Status", width: 100 },
      { field: "usageCount", title: "Usos", width: 80 },
      { field: "lastUsedAt", title: "Ultimo uso", width: 160 },
      { field: "expiresAt", title: "Validade", width: 160 }
    ], (item) => {
      this.currentToken = item;
    });
  };

  CentralOperationsAdmin.prototype.renderAlertsGrid = function() {
    const alerts = global.CrudUtils.ensureArray(this.payload.alerts).concat(global.CrudUtils.ensureArray(this.payload.notifications).map(function(item) {
      return {
        severity: item.severity,
        title: "Notificacao: " + item.title,
        message: item.message,
        target: item.target
      };
    }));
    this.buildGrid(this.alertsGridElement, alerts, [
      { field: "severity", title: "Nivel", width: 90 },
      { field: "title", title: "Titulo", width: 180 },
      { field: "message", title: "Mensagem" },
      { field: "target", title: "Alvo", width: 150 }
    ]);
  };

  CentralOperationsAdmin.prototype.renderArtifactsGrid = function() {
    this.buildGrid(this.artifactsGridElement, this.payload.artifacts || [], [
      { field: "name", title: "Artefato" },
      { field: "env", title: "Variavel", width: 260 },
      { field: "status", title: "Status", width: 110 },
      { field: "valuePreview", title: "Valor" }
    ]);
  };

  CentralOperationsAdmin.prototype.renderKeysGrid = function() {
    this.buildGrid(this.keysGridElement, this.payload.keys || [], [
      { field: "name", title: "Chave" },
      { field: "env", title: "Variavel", width: 280 },
      { field: "status", title: "Status", width: 100 },
      { field: "length", title: "Tamanho", width: 90 }
    ]);
  };

  CentralOperationsAdmin.prototype.renderAuditGrid = function() {
    this.buildGrid(this.auditGridElement, this.payload.audit || [], [
      { field: "at", title: "Data", width: 170 },
      { field: "source", title: "Origem", width: 120 },
      { field: "target", title: "Alvo", width: 160 },
      { field: "type", title: "Tipo", width: 150 },
      {
        field: "detail",
        title: "Detalhe",
        template: function(dataItem) {
          return global.jQuery("<div></div>").text(JSON.stringify(dataItem.detail || {})).html();
        }
      }
    ]);
  };

  CentralOperationsAdmin.prototype.handleLicenseAction = function(action) {
    if (!this.currentLicense) {
      this.showMessage("Selecione uma licenca.", "warning");
      return;
    }
    const label = {
      suspend_license: "suspender a licenca",
      activate_license: "reativar a licenca",
      revoke_license: "revogar a licenca"
    }[action] || "executar a acao";
    this.confirm("Confirma " + label + " do assinante " + this.currentLicense.subscriberCode + "?", () => {
      return this.request("POST", "/api/admin/central-operations/license-action", {
        action: action,
        subscriberCode: this.currentLicense.subscriberCode,
        reason: "Acao administrativa pela tela de operacoes da central"
      }).then((payload) => this.replaceDashboard(payload));
    });
  };

  CentralOperationsAdmin.prototype.handleRevokeFingerprint = function() {
    if (!this.currentLicense) {
      this.showMessage("Selecione uma licenca.", "warning");
      return;
    }
    const fingerprints = global.CrudUtils.ensureArray(this.currentLicense.fingerprints).filter((item) => {
      return global.CrudUtils.ensureArray(this.currentLicense.revokedFingerprints).indexOf(item) < 0;
    });
    if (!fingerprints.length) {
      this.showMessage("A licenca selecionada nao possui fingerprint ativo para revogar.", "warning");
      return;
    }
    const fingerprint = fingerprints[0];
    this.confirm("Confirma revogar o fingerprint " + fingerprint + " do assinante " + this.currentLicense.subscriberCode + "?", () => {
      return this.request("POST", "/api/admin/central-operations/license-action", {
        action: "revoke_fingerprint",
        subscriberCode: this.currentLicense.subscriberCode,
        fingerprint: fingerprint,
        reason: "Revogacao administrativa de fingerprint"
      }).then((payload) => this.replaceDashboard(payload));
    });
  };

  CentralOperationsAdmin.prototype.handleTokenAction = function(action) {
    if (!this.currentToken) {
      this.showMessage("Selecione um token interno.", "warning");
      return;
    }
    const label = {
      suspend_token: "suspender o token",
      activate_token: "reativar o token",
      revoke_token: "revogar o token"
    }[action] || "executar a acao";
    this.confirm("Confirma " + label + " " + this.currentToken.code + "?", () => {
      return this.request("POST", "/api/admin/central-operations/token-action", {
        action: action,
        code: this.currentToken.code,
        reason: "Acao administrativa pela tela de operacoes da central"
      }).then((payload) => this.replaceDashboard(payload));
    });
  };

  CentralOperationsAdmin.prototype.replaceDashboard = function(payload) {
    if (payload && payload.dashboard) {
      this.payload = payload.dashboard;
      this.currentLicense = null;
      this.currentToken = null;
      this.render();
      this.showMessage("Acao concluida.", "success");
      return;
    }
    this.load();
  };

  CentralOperationsAdmin.prototype.buildGrid = function(element, data, columns, changeHandler) {
    const existing = element.data("kendoGrid");
    if (existing) {
      existing.destroy();
      element.empty();
    }
    element.kendoGrid({
      dataSource: {
        data: data || [],
        pageSize: 10
      },
      height: 330,
      selectable: changeHandler ? "row" : false,
      sortable: true,
      filterable: true,
      pageable: {
        refresh: false,
        pageSizes: [10, 20, 50],
        buttonCount: 4
      },
      columns: columns,
      change: function(event) {
        if (!changeHandler) {
          return;
        }
        const grid = event.sender;
        const item = grid.dataItem(grid.select());
        changeHandler(item ? item.toJSON ? item.toJSON() : item : null);
      }
    });
  };

  CentralOperationsAdmin.prototype.request = function(method, url, data) {
    return this.httpClient.request({ method: method, url: url, data: data || {} });
  };

  CentralOperationsAdmin.prototype.confirm = function(message, onConfirm) {
    if (global.CrudUtils && typeof global.CrudUtils.confirm === "function") {
      global.CrudUtils.confirm(message).then(function(confirmed) {
        if (confirmed) {
          onConfirm();
        }
      });
      return;
    }
    onConfirm();
  };

  CentralOperationsAdmin.prototype.showMessage = function(message, type) {
    if (global.CrudUtils && typeof global.CrudUtils.showMessage === "function") {
      global.CrudUtils.showMessage(message, type || "info");
      return;
    }
    const host = this.summaryPanel || this.root;
    global.jQuery("<p class=\"manual-summary\"></p>").text(message).prependTo(host);
  };

  CentralOperationsAdmin.prototype.showError = function(error, fallback) {
    const message = error && error.error && error.error.message || error && error.message || fallback;
    this.showMessage(message, "error");
  };

  CentralOperationsAdmin.prototype.createCard = function(container, title) {
    const card = global.jQuery("<section class=\"program-builder-governance-card\"></section>").appendTo(container);
    global.jQuery("<div class=\"program-builder-governance-card-title\"></div>").text(title).appendTo(card);
    const body = global.jQuery("<div class=\"program-builder-governance-card-body\"></div>").appendTo(card);
    return { card: card, body: body };
  };

  CentralOperationsAdmin.prototype.createButton = function(container, text, icon, handler) {
    const button = global.jQuery("<button type=\"button\" class=\"k-button k-button-solid-base\"></button>").appendTo(container);
    if (icon) {
      global.jQuery("<span class=\"k-icon\"></span>").addClass("k-i-" + icon).appendTo(button);
    }
    global.jQuery("<span></span>").text(text).appendTo(button);
    button.on("click", handler);
    return button;
  };

  CentralOperationsAdmin.prototype.setStatus = function(text) {
    this.statusBadge.text(text || "");
  };

  global.CentralOperationsAdmin = CentralOperationsAdmin;
})(window);
