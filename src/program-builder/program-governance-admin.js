(function(global, $) {
  "use strict";

  function ProgramGovernanceAdmin(options) {
    this.options = options || {};
    this.root = $(this.options.root || "#program-governance-admin-root");
    this.http = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.state = {
      programs: [],
      versions: [],
      currentProgramCode: "",
      currentVersionId: 0,
      dashboard: null,
      overlayPreview: null
    };
    this.query = this.readQuery();
    this.mode = String(this.options.mode || this.queryValue("mode") || "full").trim().toLowerCase();
  }

  ProgramGovernanceAdmin.prototype.init = function() {
    this.renderShell();
    return this.loadBootstrap().then(function() {
      return this.initializeSelection();
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.readQuery = function() {
    try {
      return new URLSearchParams(global.location && global.location.search || "");
    } catch (_) {
      return new URLSearchParams();
    }
  };

  ProgramGovernanceAdmin.prototype.queryValue = function(name) {
    return String(this.query.get(name) || "").trim();
  };

  ProgramGovernanceAdmin.prototype.renderShell = function() {
    this.root.empty();
    const shell = $("<section class=\"program-governance-admin-shell\"></section>").appendTo(this.root);
    const header = $("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = $("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    $("<h1></h1>").text(this.mode === "grants" ? "Grants de programas" : (this.mode === "approvals" ? "Aprovacoes de publicacao" : (this.mode === "retention" ? "Retencao da governanca" : "Governanca de programas"))).appendTo(title);
    $("<p></p>").text(this.mode === "grants"
      ? "Operacao dedicada de grants com foco em liberacao, congelamento, reativacao e revogacao."
      : (this.mode === "approvals"
        ? "Operacao dedicada de aprovacoes finais com foco em bundle e liberacao de publish."
        : (this.mode === "retention"
          ? "Operacao dedicada de retencao de historico e politica de limpeza da governanca."
          : "Operacao dedicada de solicitacoes, grants, testes, aprovacoes, retencao e rebase."))).appendTo(title);
    const toolbar = $("<div class=\"program-governance-admin-toolbar\"></div>").appendTo(header);
    this.reloadButton = this.createButton(toolbar, "Atualizar", "reload", this.refreshCurrent.bind(this));
    this.focusBanner = $("<div class=\"program-governance-admin-banner\"></div>").appendTo(shell).hide();

    const filters = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(shell);
    $("<h2></h2>").text("Escopo").appendTo(filters);
    const filterGrid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(filters);
    this.programInput = this.appendDropDown(filterGrid, "Programa");
    this.versionInput = this.appendDropDown(filterGrid, "Versao");
    this.focusInfo = $("<p class=\"program-builder-inline-muted\"></p>").appendTo(filters);

    const tabs = $("<div class=\"program-governance-admin-tabs\"></div>").appendTo(shell);
    const list = $("<ul></ul>").appendTo(tabs);
    ["Resumo", "Requests", "Grants", "Testes", "Aprovacoes", "Retencao", "Rebase"].forEach(function(titleText, index) {
      $("<li></li>").toggleClass("k-active", index === 0).text(titleText).appendTo(list);
    });
    this.summaryTab = $("<div></div>").appendTo(tabs);
    this.requestsTab = $("<div></div>").appendTo(tabs);
    this.grantsTab = $("<div></div>").appendTo(tabs);
    this.testsTab = $("<div></div>").appendTo(tabs);
    this.approvalsTab = $("<div></div>").appendTo(tabs);
    this.retentionTab = $("<div></div>").appendTo(tabs);
    this.rebaseTab = $("<div></div>").appendTo(tabs);
    tabs.kendoTabStrip({ animation: false });
    this.tabs = tabs.data("kendoTabStrip");

    this.renderSummaryTab();
    this.renderRequestsTab();
    this.renderGrantsTab();
    this.renderTestsTab();
    this.renderApprovalsTab();
    this.renderRetentionTab();
    this.renderRebaseTab();
    this.applyMode();
  };

  ProgramGovernanceAdmin.prototype.applyMode = function() {
    if (this.mode !== "grants" && this.mode !== "approvals" && this.mode !== "retention") {
      return;
    }
    const keepIndexes = this.mode === "grants" ? [0, 2] : (this.mode === "approvals" ? [0, 4] : [0, 5]);
    const tabItems = this.tabs.tabGroup.children("li");
    const tabContents = this.tabs.contentElements;
    tabItems.each(function(index, item) {
      if (keepIndexes.indexOf(index) < 0) {
        $(item).hide();
      }
    });
    $(tabContents).each(function(index, item) {
      if (keepIndexes.indexOf(index) < 0) {
        $(item).hide();
      }
    });
    this.tabs.select(keepIndexes[1]);
  };

  ProgramGovernanceAdmin.prototype.renderSummaryTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.summaryTab);
    $("<h2></h2>").text("Resumo operacional").appendTo(panel);
    this.summaryGrid = $("<div class=\"program-builder-governance-grid\"></div>").appendTo(panel);
    this.summaryActions = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.timelineHost = $("<div class=\"program-governance-admin-timeline\"></div>").appendTo(panel);
    this.summaryJson = $("<pre class=\"program-builder-inline-json\"></pre>").appendTo(panel);
  };

  ProgramGovernanceAdmin.prototype.renderRequestsTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.requestsTab);
    $("<h2></h2>").text("Solicitacoes").appendTo(panel);
    const grid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(panel);
    this.requestReasonInput = this.appendTextArea(grid, "Motivo", 3);
    this.requestActionsInput = this.appendTextField(grid, "Acoes");
    this.requestActionsInput.value("edit,publish");
    this.createButton(panel, "Criar solicitacao", "lock", this.handleCreateRequest.bind(this));
    this.requestsList = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
  };

  ProgramGovernanceAdmin.prototype.renderGrantsTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.grantsTab);
    $("<h2></h2>").text("Grants").appendTo(panel);
    const grid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(panel);
    this.requestIdInput = this.appendTextField(grid, "Request ID");
    this.grantedUserInput = this.appendTextField(grid, "Usuario");
    this.grantedUserInput.value("analista");
    this.createButton(panel, "Liberar grant", "unlock", this.handleCreateGrant.bind(this));
    const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(panel);
    this.createButton(actions, "Reativar", null, this.handleGrantStatus.bind(this, "active"));
    this.createButton(actions, "Congelar", null, this.handleGrantStatus.bind(this, "frozen"));
    this.createButton(actions, "Revogar", null, this.handleGrantStatus.bind(this, "revoked"));
    this.grantsList = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
  };

  ProgramGovernanceAdmin.prototype.renderTestsTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.testsTab);
    $("<h2></h2>").text("Bundle de testes").appendTo(panel);
    const grid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(panel);
    this.bundleInput = this.appendTextField(grid, "Bundle");
    this.testPlanInput = this.appendTextField(grid, "Roteiro");
    this.testPlanInput.value("roteiro-web");
    this.testNotesInput = this.appendTextArea(grid, "Observacoes", 3);
    this.createButton(panel, "Registrar teste aprovado", "check", this.handleRegisterTest.bind(this));
    this.testsList = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
  };

  ProgramGovernanceAdmin.prototype.renderApprovalsTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.approvalsTab);
    $("<h2></h2>").text("Aprovacao final").appendTo(panel);
    const grid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(panel);
    this.approvalBundleInput = this.appendTextField(grid, "Bundle aprovado");
    this.createButton(panel, "Aprovar publicacao", "check-outline", this.handleApprovePublication.bind(this));
    this.approvalsList = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
  };

  ProgramGovernanceAdmin.prototype.renderRetentionTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.retentionTab);
    $("<h2></h2>").text("Politica de retencao").appendTo(panel);
    const grid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(panel);
    this.retentionInputs = {
      changeRequestsDays: this.appendNumberField(grid, "Solicitacoes"),
      grantsDays: this.appendNumberField(grid, "Grants"),
      approvalsDays: this.appendNumberField(grid, "Aprovacoes"),
      testExecutionsDays: this.appendNumberField(grid, "Testes"),
      administrativeNotificationsDays: this.appendNumberField(grid, "Notificacoes")
    };
    this.createButton(panel, "Salvar retencao", "save", this.handleSaveRetention.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderRebaseTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.rebaseTab);
    $("<h2></h2>").text("Rebase de overlay").appendTo(panel);
    const grid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(panel);
    this.overlayIdInput = this.appendTextField(grid, "Overlay ID");
    this.overlayVersionIdInput = this.appendTextField(grid, "Versao do overlay");
    const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(panel);
    this.createButton(actions, "Preview", "eye", this.handlePreviewRebase.bind(this));
    this.createButton(actions, "Executar rebase", "arrows-merge", this.handleExecuteRebase.bind(this));
    this.rebaseSummary = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.rebaseJson = $("<pre class=\"program-builder-inline-json\"></pre>").appendTo(panel);
  };

  ProgramGovernanceAdmin.prototype.createButton = function(host, label, icon, handler) {
    const button = $("<button type=\"button\"></button>").text(label).appendTo(host);
    button.kendoButton(icon ? { icon: icon } : {});
    if (typeof handler === "function") {
      button.on("click", handler);
    }
    return button;
  };

  ProgramGovernanceAdmin.prototype.appendDropDown = function(host, label) {
    const field = $("<div class=\"program-builder-field\"></div>").appendTo(host);
    $("<label></label>").text(label).appendTo(field);
    return $("<input>").appendTo(field).kendoDropDownList({
      dataTextField: "text",
      dataValueField: "value",
      dataSource: []
    }).data("kendoDropDownList");
  };

  ProgramGovernanceAdmin.prototype.appendTextField = function(host, label) {
    const field = $("<div class=\"program-builder-field\"></div>").appendTo(host);
    $("<label></label>").text(label).appendTo(field);
    return $("<input>").appendTo(field).kendoTextBox().data("kendoTextBox");
  };

  ProgramGovernanceAdmin.prototype.appendNumberField = function(host, label) {
    const field = $("<div class=\"program-builder-field\"></div>").appendTo(host);
    $("<label></label>").text(label).appendTo(field);
    return $("<input type=\"number\" min=\"1\">").appendTo(field).kendoNumericTextBox({
      min: 1,
      format: "n0",
      decimals: 0
    }).data("kendoNumericTextBox");
  };

  ProgramGovernanceAdmin.prototype.appendTextArea = function(host, label, rows) {
    const field = $("<div class=\"program-builder-field\"></div>").appendTo(host);
    $("<label></label>").text(label).appendTo(field);
    return $("<textarea></textarea>").attr("rows", rows || 3).appendTo(field).kendoTextArea().data("kendoTextArea");
  };

  ProgramGovernanceAdmin.prototype.loadBootstrap = function() {
    return this.http.request({
      url: "/api/admin/program-builder/bootstrap",
      method: "GET"
    }).then(function(response) {
      const programs = global.CrudUtils.ensureArray(response && response.programs).filter(function(item) {
        return String(item && item.programOrigin || "standard") === "standard"
          && String(item && item.ownerScope || "system") === "system";
      });
      this.state.programs = programs;
      this.programInput.setDataSource(programs.map(function(item) {
        return { value: item.code, text: item.title + " (" + item.code + ")" };
      }));
      this.programInput.bind("change", this.handleProgramChange.bind(this));
      this.versionInput.bind("change", this.handleVersionChange.bind(this));
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.initializeSelection = function() {
    const programCode = this.queryValue("programCode") || (this.state.programs[0] && this.state.programs[0].code) || "";
    if (!programCode) {
      return Promise.resolve();
    }
    this.programInput.value(programCode);
    return this.loadProgram(programCode).then(function() {
      const versionId = Number(this.queryValue("builderProgramVersionId") || this.queryValue("versionId") || 0);
      if (versionId > 0) {
        this.versionInput.value(String(versionId));
      }
      return this.loadDashboard();
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.handleProgramChange = function() {
    return this.loadProgram(this.programInput.value()).then(this.loadDashboard.bind(this));
  };

  ProgramGovernanceAdmin.prototype.handleVersionChange = function() {
    return this.loadDashboard();
  };

  ProgramGovernanceAdmin.prototype.loadProgram = function(programCode) {
    const code = String(programCode || "").trim();
    if (!code) {
      return Promise.resolve();
    }
    this.state.currentProgramCode = code;
    return this.http.request({
      url: "/api/admin/program-builder/programs/" + encodeURIComponent(code),
      method: "GET"
    }).then(function(response) {
      const versions = global.CrudUtils.ensureArray(response && response.versions);
      this.state.versions = versions;
      this.versionInput.setDataSource(versions.map(function(item) {
        return { value: String(item.id || ""), text: (item.version || "sem versao") + " [" + (item.status || "draft") + "]" };
      }));
      const selected = Number(this.queryValue("builderProgramVersionId") || this.queryValue("versionId") || 0) || Number(versions[0] && versions[0].id || 0);
      if (selected > 0) {
        this.versionInput.value(String(selected));
      }
      this.bundleInput.value("bundle-" + String(selected || ""));
      this.approvalBundleInput.value("bundle-" + String(selected || ""));
      this.focusInfo.text("Programa atual: " + code);
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.loadDashboard = function() {
    const programCode = String(this.programInput.value() || "").trim();
    const versionId = Number(this.versionInput.value() || 0);
    if (!programCode) {
      return Promise.resolve();
    }
    this.state.currentProgramCode = programCode;
    this.state.currentVersionId = versionId;
    return this.http.request({
      url: "/api/admin/program-builder/governance/dashboard",
      method: "GET",
      data: {
        programCode: programCode,
        builderProgramVersionId: versionId || null
      }
    }).then(function(response) {
      this.state.dashboard = response || {};
      this.renderDashboard();
      this.applyTabSelection();
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel carregar a governanca."));
  };

  ProgramGovernanceAdmin.prototype.renderDashboard = function() {
    const dashboard = this.state.dashboard || {};
    this.renderFocusBanner(dashboard);
    this.summaryGrid.empty();
    [
      { label: "Requests pendentes", value: dashboard.summary && dashboard.summary.pendingRequests || 0 },
      { label: "Grants ativos", value: dashboard.summary && dashboard.summary.activeGrants || 0 },
      { label: "Aprovacoes", value: dashboard.summary && dashboard.summary.approvedPublications || 0 },
      { label: "Testes aprovados", value: dashboard.summary && dashboard.summary.passedTests || 0 }
    ].forEach(function(item) {
      const card = $("<article class=\"program-builder-governance-card\"></article>").appendTo(this.summaryGrid);
      $("<strong></strong>").text(item.label).appendTo(card);
      $("<span class=\"is-valid\"></span>").text(String(item.value)).appendTo(card);
    }.bind(this));
    this.renderSuggestedActions(dashboard.suggestedActions);
    this.renderTimeline(dashboard.timeline);
    this.summaryJson.text(JSON.stringify(dashboard, null, 2));
    this.renderCollection(this.requestsList, dashboard.requests, this.queryValue("focusRequestCode"), function(item) {
      return (item.requestCode || "-") + " | " + (item.status || "-") + " | " + (item.reason || "");
    });
    this.renderCollection(this.grantsList, dashboard.grants, this.queryValue("focusGrantId"), function(item) {
      return "Grant #" + String(item.id || "-") + " | " + (item.status || "-") + " | " + (item.grantedToUserId || "-");
    });
    this.renderCollection(this.testsList, dashboard.tests, this.queryValue("focusBundleId"), function(item) {
      return (item.bundleId || "-") + " | " + (item.status || "-") + " | " + (item.testPlanId || "-");
    });
    this.renderCollection(this.approvalsList, dashboard.approvals, this.queryValue("focusApprovalId"), function(item) {
      return "Aprovacao #" + String(item.id || "-") + " | " + (item.status || "-") + " | " + (item.testExecutionBundleId || "-");
    });
    const retention = dashboard.retentionPolicy || {};
    Object.keys(this.retentionInputs).forEach(function(key) {
      this.retentionInputs[key].value(Number(retention[key] || 0));
    }.bind(this));
    const latestRequest = dashboard.requests && dashboard.requests[0];
    const latestGrant = dashboard.grants && dashboard.grants[0];
    if (latestRequest && latestRequest.id) {
      this.requestIdInput.value(String(latestRequest.id));
    }
  };

  ProgramGovernanceAdmin.prototype.renderFocusBanner = function(dashboard) {
    this.focusBanner.empty();
    const items = [];
    const suggestion = String(this.queryValue("actionSuggestion") || "").trim();
    if (suggestion) {
      items.push({ severity: "warning", text: suggestion });
    }
    const version = dashboard && dashboard.currentVersion;
    if (version && version.version) {
      items.push({ severity: "info", text: "Versao em foco: " + version.version + " (" + (version.status || "draft") + ")." });
    }
    if (!items.length) {
      this.focusBanner.hide();
      return;
    }
    this.focusBanner.show();
    items.forEach(function(item) {
      $("<div class=\"program-governance-admin-banner-item\"></div>")
        .addClass("is-" + String(item.severity || "info"))
        .text(String(item.text || ""))
        .appendTo(this.focusBanner);
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderSuggestedActions = function(items) {
    this.summaryActions.empty();
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      return;
    }
    $("<h3></h3>").text("Proximas acoes").appendTo(this.summaryActions);
    rows.forEach(function(item) {
      $("<div class=\"program-governance-admin-row\"></div>")
        .addClass("is-" + String(item.severity || "info"))
        .text(String(item.text || ""))
        .appendTo(this.summaryActions);
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderTimeline = function(items) {
    this.timelineHost.empty();
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      return;
    }
    $("<h3></h3>").text("Linha do tempo").appendTo(this.timelineHost);
    rows.forEach(function(item) {
      const row = $("<article class=\"program-governance-admin-timeline-item\"></article>").appendTo(this.timelineHost);
      const header = $("<div class=\"program-governance-admin-timeline-header\"></div>").appendTo(row);
      $("<strong></strong>").text(String(item.label || item.type || "Evento")).appendTo(header);
      $("<span class=\"k-badge k-rounded-md\"></span>").text(String(item.status || "-")).appendTo(header);
      $("<p></p>").text(String(item.description || "")).appendTo(row);
      if (item.timestamp) {
        $("<small></small>").text(String(item.timestamp)).appendTo(row);
      }
    });
  };

  ProgramGovernanceAdmin.prototype.renderCollection = function(host, items, focusValue, formatter) {
    host.empty();
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum item.").appendTo(host);
      return;
    }
    rows.forEach(function(item) {
      const text = formatter(item);
      const row = $("<div class=\"program-governance-admin-row\"></div>")
        .toggleClass("is-focused", focusValue && (String(item.id || "") === String(focusValue) || String(item.requestCode || "") === String(focusValue) || String(item.bundleId || "") === String(focusValue)))
        .appendTo(host);
      $("<span></span>").text(text).appendTo(row);
    });
  };

  ProgramGovernanceAdmin.prototype.applyTabSelection = function() {
    const tab = this.queryValue("tab");
    const tabs = {
      summary: 0,
      requests: 1,
      grants: 2,
      tests: 3,
      approvals: 4,
      retention: 5,
      rebase: 6
    };
    if (tab && Object.prototype.hasOwnProperty.call(tabs, tab)) {
      this.tabs.select(tabs[tab]);
    }
  };

  ProgramGovernanceAdmin.prototype.refreshCurrent = function() {
    return this.loadProgram(this.programInput.value()).then(this.loadDashboard.bind(this));
  };

  ProgramGovernanceAdmin.prototype.handleCreateRequest = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/requests",
      method: "POST",
      data: {
        programCode: this.state.currentProgramCode,
        requestedActions: String(this.requestActionsInput.value() || "").split(",").map(function(item) { return item.trim(); }).filter(Boolean),
        reason: this.requestReasonInput.value() || ""
      }
    }).then(this.afterMutation.bind(this, "Solicitacao criada."));
  };

  ProgramGovernanceAdmin.prototype.handleCreateGrant = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/grants",
      method: "POST",
      data: {
        requestId: Number(this.requestIdInput.value() || 0),
        grantedToUserId: this.grantedUserInput.value() || ""
      }
    }).then(this.afterMutation.bind(this, "Grant criado."));
  };

  ProgramGovernanceAdmin.prototype.handleGrantStatus = function(status) {
    const grants = global.CrudUtils.ensureArray(this.state.dashboard && this.state.dashboard.grants);
    const current = grants[0];
    if (!current || !current.id) {
      global.CrudUtils.showMessage("Nao existe grant para alterar.", "warning");
      return Promise.resolve();
    }
    return this.http.request({
      url: "/api/admin/program-builder/governance/grants/status",
      method: "POST",
      data: {
        grantId: Number(current.id),
        status: status
      }
    }).then(this.afterMutation.bind(this, "Grant atualizado."));
  };

  ProgramGovernanceAdmin.prototype.handleRegisterTest = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/tests",
      method: "POST",
      data: {
        builderProgramVersionId: this.state.currentVersionId,
        bundleId: this.bundleInput.value() || "",
        testPlanId: this.testPlanInput.value() || "",
        status: "passed",
        checklistSnapshot: [{ item: this.testPlanInput.value() || "roteiro-web", status: "passed" }],
        notes: this.testNotesInput.value() || ""
      }
    }).then(this.afterMutation.bind(this, "Teste registrado."));
  };

  ProgramGovernanceAdmin.prototype.handleApprovePublication = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/approvals",
      method: "POST",
      data: {
        builderProgramVersionId: this.state.currentVersionId,
        bundleId: this.approvalBundleInput.value() || ""
      }
    }).then(this.afterMutation.bind(this, "Aprovacao registrada."));
  };

  ProgramGovernanceAdmin.prototype.handleSaveRetention = function() {
    const payload = {};
    Object.keys(this.retentionInputs).forEach(function(key) {
      payload[key] = Number(this.retentionInputs[key].value() || 0);
    }.bind(this));
    return this.http.request({
      url: "/api/admin/program-builder/governance/retention",
      method: "POST",
      data: payload
    }).then(this.afterMutation.bind(this, "Retencao atualizada."));
  };

  ProgramGovernanceAdmin.prototype.handlePreviewRebase = function() {
    const overlayId = Number(this.overlayIdInput.value() || 0);
    if (!overlayId) {
      global.CrudUtils.showMessage("Informe o overlay.", "warning");
      return Promise.resolve();
    }
    return this.http.request({
      url: "/api/admin/program-builder/overlays/" + encodeURIComponent(overlayId) + "/rebase-preview",
      method: "GET"
    }).then(function(response) {
      this.state.overlayPreview = response;
      this.renderRebasePreview();
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel gerar o preview do rebase."));
  };

  ProgramGovernanceAdmin.prototype.handleExecuteRebase = function() {
    const overlayVersionId = Number(this.overlayVersionIdInput.value() || 0);
    if (!overlayVersionId) {
      global.CrudUtils.showMessage("Informe a versao do overlay.", "warning");
      return Promise.resolve();
    }
    return this.http.request({
      url: "/api/admin/program-builder/overlay-versions/" + encodeURIComponent(overlayVersionId) + "/rebase",
      method: "POST"
    }).then(function(response) {
      this.state.overlayPreview = response && response.preview ? response.preview : response;
      this.renderRebasePreview();
      global.CrudUtils.showMessage("Rebase executado.", "success");
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel executar o rebase."));
  };

  ProgramGovernanceAdmin.prototype.renderRebasePreview = function() {
    const response = this.state.overlayPreview || null;
    this.rebaseSummary.empty();
    if (!response) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum preview carregado.").appendTo(this.rebaseSummary);
      this.rebaseJson.text("");
      return;
    }
    $("<p></p>").text("Status: " + String(response.status || "-") + " | Base atual: " + String(response.currentBaseVersion || "-") + " | Base alvo: " + String(response.targetBaseVersion || "-")).appendTo(this.rebaseSummary);
    global.CrudUtils.ensureArray(response.sections).forEach(function(section) {
      const row = $("<div class=\"program-governance-admin-row\"></div>")
        .toggleClass("is-focused", (section.classification || "").indexOf("conflict") === 0)
        .appendTo(this.rebaseSummary);
      $("<span></span>").text((section.key || "-") + " | " + (section.classification || "-") + " | " + (section.resolution || "-")).appendTo(row);
    }.bind(this));
    this.rebaseJson.text(JSON.stringify(response, null, 2));
  };

  ProgramGovernanceAdmin.prototype.afterMutation = function(message) {
    global.CrudUtils.showMessage(message, "success");
    return this.refreshCurrent();
  };

  ProgramGovernanceAdmin.prototype.handleError = function(fallback, error) {
    const normalized = global.CrudUtils.unwrapError(error, fallback);
    global.CrudUtils.showMessage(normalized.message, "error");
  };

  global.ProgramGovernanceAdmin = ProgramGovernanceAdmin;
})(window, window.jQuery);
