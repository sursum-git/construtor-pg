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
      timelineItems: [],
      selectedTimelineItem: null,
      overlayPreview: null,
      overlayRebaseSelections: {},
      overlayVersions: [],
      overlayVersionComparison: null,
      selectedOverlayId: 0,
      lastRetentionReport: null,
      retentionRuns: [],
      selectedRetentionRun: null,
      auditSummary: null,
      savedAuditFilters: null,
      operationsReport: null
    };
    this.query = this.readQuery();
    this.mode = String(this.options.mode || this.queryValue("mode") || "full").trim().toLowerCase();
  }

  ProgramGovernanceAdmin.prototype.init = function() {
    this.renderShell();
    this.applySavedAuditFilters();
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

  ProgramGovernanceAdmin.prototype.modeMeta = function() {
    const map = {
      grants: {
        title: "Grants de programas",
        subtitle: "Operacao dedicada de grants com foco em liberacao, congelamento, reativacao e revogacao.",
        tabs: [0, 2]
      },
      approvals: {
        title: "Aprovacoes de publicacao",
        subtitle: "Operacao dedicada de aprovacoes finais com foco em bundle e liberacao de publish.",
        tabs: [0, 4]
      },
      retention: {
        title: "Retencao da governanca",
        subtitle: "Operacao dedicada de retencao de historico e politica de limpeza da governanca.",
        tabs: [0, 5]
      },
      "retention-history": {
        title: "Historico da retencao",
        subtitle: "Operacao dedicada ao historico persistido da retencao, com comparacao entre preview e aplicacao.",
        tabs: [5]
      },
      audit: {
        title: "Auditoria da governanca",
        subtitle: "Operacao dedicada para revisar a linha do tempo, sinais operacionais e o historico detalhado por programa.",
        tabs: [0]
      },
      operations: {
        title: "Operacoes da governanca",
        subtitle: "Operacao administrativa unificada para monitoramento, integridade e retencao.",
        tabs: [0]
      },
      overlays: {
        title: "Overlays de programas",
        subtitle: "Operacao dedicada de overlays, assinantes, versoes e rebase.",
        tabs: [0, 6, 7, 8]
      },
      "overlay-versions": {
        title: "Versoes de overlay",
        subtitle: "Operacao dedicada ao historico, comparacao e publish de versoes do overlay.",
        tabs: [0, 7]
      }
    };
    return map[this.mode] || {
      title: "Governanca de programas",
      subtitle: "Operacao dedicada de solicitacoes, grants, testes, aprovacoes, retencao e rebase.",
      tabs: null
    };
  };

  ProgramGovernanceAdmin.prototype.renderShell = function() {
    this.root.empty();
    const meta = this.modeMeta();
    const shell = $("<section class=\"program-governance-admin-shell\"></section>").appendTo(this.root);
    const header = $("<header class=\"program-governance-admin-header\"></header>").appendTo(shell);
    const title = $("<div class=\"program-governance-admin-title\"></div>").appendTo(header);
    $("<h1></h1>").text(meta.title).appendTo(title);
    $("<p></p>").text(meta.subtitle).appendTo(title);
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
    ["Resumo", "Requests", "Grants", "Testes", "Aprovacoes", "Retencao", "Overlays", "Versoes de overlay", "Rebase"].forEach(function(label, index) {
      $("<li></li>").toggleClass("k-active", index === 0).text(label).appendTo(list);
    });

    this.summaryTab = $("<div></div>").appendTo(tabs);
    this.requestsTab = $("<div></div>").appendTo(tabs);
    this.grantsTab = $("<div></div>").appendTo(tabs);
    this.testsTab = $("<div></div>").appendTo(tabs);
    this.approvalsTab = $("<div></div>").appendTo(tabs);
    this.retentionTab = $("<div></div>").appendTo(tabs);
    this.overlaysTab = $("<div></div>").appendTo(tabs);
    this.overlayVersionsTab = $("<div></div>").appendTo(tabs);
    this.rebaseTab = $("<div></div>").appendTo(tabs);
    tabs.kendoTabStrip({ animation: false });
    this.tabs = tabs.data("kendoTabStrip");

    this.renderSummaryTab();
    this.renderRequestsTab();
    this.renderGrantsTab();
    this.renderTestsTab();
    this.renderApprovalsTab();
    this.renderRetentionTab();
    this.renderOverlaysTab();
    this.renderOverlayVersionsTab();
    this.renderRebaseTab();
    this.applyMode();
  };

  ProgramGovernanceAdmin.prototype.applyMode = function() {
    const keepIndexes = this.modeMeta().tabs;
    if (!keepIndexes) {
      return;
    }
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
    if (keepIndexes.length > 1) {
      this.tabs.select(keepIndexes[1]);
    }
  };

  ProgramGovernanceAdmin.prototype.renderSummaryTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.summaryTab);
    $("<h2></h2>").text("Resumo operacional").appendTo(panel);
    this.summaryGrid = $("<div class=\"program-builder-governance-grid\"></div>").appendTo(panel);
    this.operationalSignals = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.summaryActions = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.summaryOperationsHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.auditSummaryHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);

    const timelinePanel = $("<div class=\"program-governance-admin-timeline\"></div>").appendTo(panel);
    $("<h3></h3>").text("Linha do tempo").appendTo(timelinePanel);
    const filterGrid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(timelinePanel);
    this.timelineTypeInput = this.appendDropDown(filterGrid, "Tipo");
    this.timelineUserInput = this.appendTextField(filterGrid, "Usuario");
    this.timelineDaysInput = this.appendNumberField(filterGrid, "Periodo (dias)");
    this.timelineDaysInput.value(365);
    this.timelineStartDateInput = this.appendDateField(filterGrid, "Data inicial");
    this.timelineEndDateInput = this.appendDateField(filterGrid, "Data final");
    const timelineFilterActions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(timelinePanel);
    this.createButton(timelineFilterActions, "Aplicar filtro", "filter", this.handleApplyAuditFilters.bind(this));
    this.createButton(timelineFilterActions, "Limpar filtro", "reset", function() {
      this.timelineTypeInput.value("");
      this.timelineUserInput.value("");
      this.timelineDaysInput.value(365);
      this.timelineStartDateInput.value(null);
      this.timelineEndDateInput.value(null);
      this.persistAuditFilters(this.currentAuditFilters());
      this.handleApplyAuditFilters();
    }.bind(this));
    this.createButton(timelineFilterActions, "Exportar auditoria JSON", "download", this.handleExportAuditData.bind(this, "json"));
    this.createButton(timelineFilterActions, "Exportar auditoria CSV", "file-csv", this.handleExportAuditData.bind(this, "csv"));
    this.timelineHost = $("<div class=\"program-governance-admin-timeline\"></div>").appendTo(timelinePanel);
    this.timelineSummaryHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(timelinePanel);
    this.timelineDetailHost = $("<pre class=\"program-builder-inline-json\"></pre>").appendTo(timelinePanel);
    this.timelineActionHost = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(timelinePanel);

    this.integrityCoverageHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
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
    const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(panel);
    this.createButton(actions, "Salvar retencao", "save", this.handleSaveRetention.bind(this));
    this.createButton(actions, "Preview da limpeza", "eye", this.handlePreviewRetention.bind(this));
    this.createButton(actions, "Executar limpeza", "trash", this.handleExecuteRetention.bind(this));
    this.createButton(actions, "Exportar JSON", "download", this.handleExportRetention.bind(this, "json"));
    this.createButton(actions, "Exportar CSV", "file-csv", this.handleExportRetention.bind(this, "csv"));
    this.retentionCleanupHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.retentionHistoryHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.retentionHistoryCompareHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.retentionHistoryDetailHost = $("<pre class=\"program-builder-inline-json\"></pre>").appendTo(panel);
    this.retentionHistoryActions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(panel);
    this.createButton(this.retentionHistoryActions, "Exportar historico JSON", "download", this.handleExportRetentionHistory.bind(this, "json"));
    this.createButton(this.retentionHistoryActions, "Exportar historico CSV", "file-csv", this.handleExportRetentionHistory.bind(this, "csv"));
  };

  ProgramGovernanceAdmin.prototype.renderOverlaysTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.overlaysTab);
    $("<h2></h2>").text("Overlays e variantes").appendTo(panel);
    $("<p class=\"program-builder-inline-muted\"></p>")
      .text("Use esta aba para localizar overlays do programa, conferir congelamento e abrir o preview do rebase com os IDs corretos.")
      .appendTo(panel);
    this.overlaysList = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
  };

  ProgramGovernanceAdmin.prototype.renderOverlayVersionsTab = function() {
    const panel = $("<section class=\"program-governance-admin-panel\"></section>").appendTo(this.overlayVersionsTab);
    $("<h2></h2>").text("Versoes de overlay").appendTo(panel);
    const grid = $("<div class=\"program-governance-admin-grid\"></div>").appendTo(panel);
    this.overlayVersionsOverlayIdInput = this.appendTextField(grid, "Overlay ID");
    this.overlayVersionsLeftInput = this.appendDropDown(grid, "Versao esquerda");
    this.overlayVersionsRightInput = this.appendDropDown(grid, "Versao direita");
    const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(panel);
    this.createButton(actions, "Carregar versoes", "reload", this.handleLoadOverlayVersions.bind(this));
    this.createButton(actions, "Comparar versoes", "columns", this.handleCompareOverlayVersions.bind(this));
    this.createButton(actions, "Publicar versao esquerda", "upload", this.handlePublishOverlayVersion.bind(this));
    this.overlayVersionsList = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
    this.overlayVersionsCompareHost = $("<div class=\"program-governance-admin-list\"></div>").appendTo(panel);
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
      optionLabel: "Selecione",
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

  ProgramGovernanceAdmin.prototype.appendDateField = function(host, label) {
    const field = $("<div class=\"program-builder-field\"></div>").appendTo(host);
    $("<label></label>").text(label).appendTo(field);
    return $("<input>").appendTo(field).kendoDatePicker({
      format: "dd/MM/yyyy",
      parseFormats: ["yyyy-MM-dd", "dd/MM/yyyy"]
    }).data("kendoDatePicker");
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
      return this.loadDashboard().then(function() {
        const overlayId = Number(this.queryValue("overlayId") || 0);
        if (overlayId > 0) {
          this.overlayVersionsOverlayIdInput.value(String(overlayId));
          if (this.mode === "overlay-versions") {
            return this.handleLoadOverlayVersions();
          }
        }
        return null;
      }.bind(this));
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
    this.applySavedAuditFilters();
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
      this.state.timelineItems = global.CrudUtils.ensureArray(this.state.dashboard.timeline);
      this.state.selectedTimelineItem = this.state.timelineItems[0] || null;
      this.state.lastRetentionReport = this.state.dashboard.retentionPreview || null;
      this.state.retentionRuns = global.CrudUtils.ensureArray(this.state.dashboard.retentionRuns);
      this.state.selectedRetentionRun = this.state.retentionRuns[0] || null;
      this.state.auditSummary = null;
      this.renderDashboard();
      this.applyTabSelection();
      this.loadOperationsSnapshot();
      if (this.mode === "audit" || (this.state.savedAuditFilters && (this.state.savedAuditFilters.eventType || this.state.savedAuditFilters.userId || this.state.savedAuditFilters.dateFrom || this.state.savedAuditFilters.dateTo))) {
        return this.loadAuditData();
      }
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
      { label: "Testes aprovados", value: dashboard.summary && dashboard.summary.passedTests || 0 },
      { label: "Overlays em alerta", value: (dashboard.summary && ((dashboard.summary.warningOverlays || 0) + (dashboard.summary.blockedOverlays || 0))) || 0 },
      { label: "Retencao elegivel", value: dashboard.summary && dashboard.summary.retentionEligibleRecords || 0 },
      { label: "Integridade invalida", value: dashboard.summary && dashboard.summary.invalidIntegrityRecords || 0 }
    ].forEach(function(item) {
      const card = $("<article class=\"program-builder-governance-card\"></article>").appendTo(this.summaryGrid);
      $("<strong></strong>").text(item.label).appendTo(card);
      $("<span class=\"is-valid\"></span>").text(String(item.value)).appendTo(card);
    }.bind(this));

    this.renderOperationalSignals(dashboard.operationalSignals);
    this.renderSuggestedActions(dashboard.suggestedActions);
    this.renderOperationsPanel();
    this.renderTimeline();
    this.renderAuditSummary();
    this.renderIntegrityCoverage(dashboard.integrityCoverage);
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
    this.renderRetentionPreview(dashboard.retentionPreview);
    this.renderRetentionHistory(this.state.retentionRuns);
    this.renderOverlays(dashboard.overlays);
    this.renderOverlayVersions(this.state.overlayVersions);

    const latestRequest = dashboard.requests && dashboard.requests[0];
    const latestGrant = dashboard.grants && dashboard.grants[0];
    if (latestRequest && latestRequest.id) {
      this.requestIdInput.value(String(latestRequest.id));
    }
    if (latestGrant && latestGrant.id) {
      this.overlayVersionsOverlayIdInput.value(String(this.state.selectedOverlayId || this.queryValue("overlayId") || ""));
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

  ProgramGovernanceAdmin.prototype.renderOperationsPanel = function() {
    this.summaryOperationsHost.empty();
    $("<h3></h3>").text("Operacao administrativa").appendTo(this.summaryOperationsHost);
    const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(this.summaryOperationsHost);
    this.createButton(actions, "Atualizar operacoes", "reload", this.handleRefreshOperations.bind(this));
    this.createButton(actions, "Rodar monitor", "search", this.handleRunGovernanceMonitor.bind(this));
    this.createButton(actions, "Rodar operacao unificada", "gear", this.handleRunGovernanceOperations.bind(this));

    if (!this.state.operationsReport) {
      $("<p class=\"program-builder-inline-muted\"></p>").text("Nenhuma execucao administrativa registrada nesta sessao.").appendTo(this.summaryOperationsHost);
      return;
    }

    const report = this.state.operationsReport;
    $("<div class=\"program-governance-admin-row is-info\"></div>")
      .text("Ultimo resultado: " + String(report.modeLabel || "snapshot") + " | " + String(report.generatedAt || "-"))
      .appendTo(this.summaryOperationsHost);
    [
      "Integridade invalida: " + String(report.invalidIntegrity || 0),
      "Alertas operacionais: " + String(report.alertCount || 0),
      "Retencao elegivel: " + String(report.retentionTotalRecords || 0),
      report.cleanupApplied ? ("Limpeza aplicada: " + String(report.cleanupAppliedRecords || 0)) : "Limpeza aplicada: nao"
    ].forEach(function(text) {
      $("<div class=\"program-governance-admin-row\"></div>").text(text).appendTo(this.summaryOperationsHost);
    }.bind(this));
    $("<pre class=\"program-builder-inline-json\"></pre>").text(JSON.stringify(report, null, 2)).appendTo(this.summaryOperationsHost);
  };

  ProgramGovernanceAdmin.prototype.renderOperationalSignals = function(items) {
    this.operationalSignals.empty();
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      return;
    }
    $("<h3></h3>").text("Sinais operacionais").appendTo(this.operationalSignals);
    rows.forEach(function(item) {
      const row = $("<div class=\"program-governance-admin-row\"></div>")
        .addClass("is-" + String(item.severity || "info"))
        .appendTo(this.operationalSignals);
      $("<strong></strong>").text(String(item.label || "Indicador") + ": ").appendTo(row);
      $("<span></span>").text(String(item.count || 0) + " - " + String(item.description || "")).appendTo(row);
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.filteredTimeline = function() {
    const type = String(this.timelineTypeInput && this.timelineTypeInput.value() || "").trim();
    const userFilter = String(this.timelineUserInput && this.timelineUserInput.value() || "").trim().toLowerCase();
    const days = Number(this.timelineDaysInput && this.timelineDaysInput.value() || 0);
    const dateFrom = this.timelineStartDateInput && this.timelineStartDateInput.value() ? new Date(this.timelineStartDateInput.value()) : null;
    const dateTo = this.timelineEndDateInput && this.timelineEndDateInput.value() ? new Date(this.timelineEndDateInput.value()) : null;
    if (dateTo) {
      dateTo.setHours(23, 59, 59, 999);
    }
    const cutoff = days > 0 ? Date.now() - (days * 24 * 60 * 60 * 1000) : 0;
    return global.CrudUtils.ensureArray(this.state.timelineItems).filter(function(item) {
      if (type && String(item.type || "") !== type) {
        return false;
      }
      if (userFilter && String(item.userId || "").toLowerCase().indexOf(userFilter) < 0) {
        return false;
      }
      if (cutoff > 0 && item.timestamp) {
        const timestamp = Date.parse(String(item.timestamp));
        if (!Number.isNaN(timestamp) && timestamp < cutoff) {
          return false;
        }
      }
      if (item.timestamp && dateFrom instanceof Date && !Number.isNaN(dateFrom.getTime())) {
        const timestamp = Date.parse(String(item.timestamp));
        if (!Number.isNaN(timestamp) && timestamp < dateFrom.getTime()) {
          return false;
        }
      }
      if (item.timestamp && dateTo instanceof Date && !Number.isNaN(dateTo.getTime())) {
        const timestamp = Date.parse(String(item.timestamp));
        if (!Number.isNaN(timestamp) && timestamp > dateTo.getTime()) {
          return false;
        }
      }
      return true;
    });
  };

  ProgramGovernanceAdmin.prototype.handleApplyAuditFilters = function() {
    this.persistAuditFilters(this.currentAuditFilters());
    return this.loadAuditData().catch(this.handleError.bind(this, "Nao foi possivel aplicar os filtros da auditoria."));
  };

  ProgramGovernanceAdmin.prototype.loadAuditData = function() {
    const filters = this.currentAuditFilters();
    return this.http.request({
      url: "/api/admin/program-builder/governance/audit",
      method: "GET",
      data: {
        programCode: this.state.currentProgramCode,
        builderProgramVersionId: this.state.currentVersionId || null,
        eventType: filters.eventType,
        userId: filters.userId,
        dateFrom: filters.dateFrom,
        dateTo: filters.dateTo
      }
    }).then(function(response) {
      this.state.timelineItems = global.CrudUtils.ensureArray(response && response.timeline);
      this.state.selectedTimelineItem = this.state.timelineItems[0] || null;
      this.state.retentionRuns = global.CrudUtils.ensureArray(response && response.retentionRuns);
      this.state.selectedRetentionRun = this.state.retentionRuns[0] || this.state.selectedRetentionRun;
      this.state.auditSummary = response && response.summary || null;
      this.renderTimeline();
      this.renderAuditSummary();
      this.renderRetentionHistory(this.state.retentionRuns);
      return response;
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderAuditSummary = function() {
    this.auditSummaryHost.empty();
    const summary = this.state.auditSummary || null;
    if (!summary) {
      $("<p class=\"program-builder-inline-muted\"></p>").text("Sem resumo dedicado da auditoria para o recorte atual.").appendTo(this.auditSummaryHost);
      return;
    }
    $("<h3></h3>").text("Resumo da auditoria").appendTo(this.auditSummaryHost);
    [
      "Eventos no recorte: " + String(summary.timelineCount || 0),
      "Execucoes de retencao no recorte: " + String(summary.retentionRunCount || 0)
    ].forEach(function(text) {
      $("<div class=\"program-governance-admin-row is-info\"></div>").text(text).appendTo(this.auditSummaryHost);
    }.bind(this));
    this.renderAuditCountList("Eventos por tipo", summary.eventTypeCounts);
    this.renderAuditCountList("Eventos por usuario", summary.userCounts);
    this.renderAuditCountList("Retencao por modo", summary.retentionByMode);
  };

  ProgramGovernanceAdmin.prototype.renderAuditCountList = function(title, items) {
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      return;
    }
    $("<strong></strong>").text(title).appendTo(this.auditSummaryHost);
    rows.forEach(function(item) {
      $("<div class=\"program-governance-admin-row\"></div>")
        .text(String(item.label || "-") + ": " + String(item.count || 0))
        .appendTo(this.auditSummaryHost);
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderTimeline = function() {
    this.timelineHost.empty();
    this.timelineSummaryHost.empty();
    const rows = this.filteredTimeline();
    const eventTypes = Array.from(new Set(global.CrudUtils.ensureArray(this.state.timelineItems).map(function(item) {
      return String(item.type || "");
    }).filter(Boolean)));
    this.timelineTypeInput.setDataSource([{ value: "", text: "Todos" }].concat(eventTypes.map(function(type) {
      return { value: type, text: type };
    })));
    if (this.state.savedAuditFilters && this.state.savedAuditFilters.eventType) {
      this.timelineTypeInput.value(String(this.state.savedAuditFilters.eventType));
    }

    if (!rows.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum evento atende ao filtro atual.").appendTo(this.timelineHost);
      $("<p class=\"program-builder-inline-muted\"></p>").text("Ajuste os filtros para ampliar o recorte auditado.").appendTo(this.timelineSummaryHost);
      this.timelineDetailHost.text("");
      return;
    }
    [
      "Eventos filtrados: " + String(rows.length),
      "Primeiro evento: " + String(rows[0] && rows[0].timestamp || "-"),
      "Ultimo evento: " + String(rows[rows.length - 1] && rows[rows.length - 1].timestamp || "-")
    ].forEach(function(text) {
      $("<div class=\"program-governance-admin-row is-info\"></div>").text(text).appendTo(this.timelineSummaryHost);
    }.bind(this));
    rows.forEach(function(item, index) {
      const row = $("<article class=\"program-governance-admin-timeline-item\"></article>")
        .toggleClass("is-focused", this.state.selectedTimelineItem === item || (!this.state.selectedTimelineItem && index === 0))
        .appendTo(this.timelineHost);
      const header = $("<div class=\"program-governance-admin-timeline-header\"></div>").appendTo(row);
      $("<strong></strong>").text(String(item.label || item.type || "Evento")).appendTo(header);
      $("<span class=\"k-badge k-rounded-md\"></span>").text(String(item.status || "-")).appendTo(header);
      $("<p></p>").text(String(item.description || "")).appendTo(row);
      $("<small></small>").text([
        item.timestamp || "-",
        item.userId ? "usuario: " + item.userId : null,
        item.type ? "tipo: " + item.type : null
      ].filter(Boolean).join(" | ")).appendTo(row);
      row.on("click", function() {
        this.state.selectedTimelineItem = item;
        this.renderTimeline();
      }.bind(this));
    }.bind(this));

    this.renderTimelineDetail(this.state.selectedTimelineItem || rows[0]);
  };

  ProgramGovernanceAdmin.prototype.renderTimelineDetail = function(item) {
    this.timelineSummaryHost.find(".is-focused").remove();
    if (item) {
      [
        item.type ? "Tipo selecionado: " + String(item.type) : "",
        item.userId ? "Usuario: " + String(item.userId) : "",
        item.status ? "Status: " + String(item.status) : ""
      ].filter(Boolean).forEach(function(text) {
        $("<div class=\"program-governance-admin-row is-focused\"></div>").text(text).appendTo(this.timelineSummaryHost);
      }.bind(this));
    }
    this.timelineDetailHost.text(item ? JSON.stringify(item.details || item, null, 2) : "");
    this.timelineActionHost.empty();
    if (!item) {
      return;
    }
    const target = this.resolveTimelineTarget(item);
    if (target) {
      this.createButton(this.timelineActionHost, "Abrir governanca completa", "open", function() {
        global.location.href = this.buildGovernanceUrl(target.tab, target.query || {});
      }.bind(this));
      this.createButton(this.timelineActionHost, "Abrir tela focada", "open", function() {
        global.location.href = this.buildFocusedTargetUrl(item, target);
      }.bind(this));
    }
  };

  ProgramGovernanceAdmin.prototype.renderIntegrityCoverage = function(payload) {
    this.integrityCoverageHost.empty();
    const info = payload || null;
    if (!info) {
      return;
    }
    $("<h3></h3>").text("Cobertura da integridade").appendTo(this.integrityCoverageHost);
    $("<div class=\"program-governance-admin-row\"></div>")
      .addClass((Number(info.invalidRecords || 0) > 0) ? "is-error" : "is-valid")
      .text("Tabelas cobertas: " + String(info.supportedCount || 0) + " | Registros invalidos: " + String(info.invalidRecords || 0))
      .appendTo(this.integrityCoverageHost);
    if (global.CrudUtils.ensureArray(info.supportedTables).length) {
      $("<small></small>").text(global.CrudUtils.ensureArray(info.supportedTables).join(", ")).appendTo(this.integrityCoverageHost);
    }
  };

  ProgramGovernanceAdmin.prototype.renderCollection = function(host, items, focusValue, formatter) {
    host.empty();
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum item.").appendTo(host);
      return;
    }
    rows.forEach(function(item) {
      $("<div class=\"program-governance-admin-row\"></div>")
        .toggleClass("is-focused", focusValue && (String(item.id || "") === String(focusValue) || String(item.requestCode || "") === String(focusValue) || String(item.bundleId || "") === String(focusValue)))
        .append($("<span></span>").text(formatter(item)))
        .appendTo(host);
    });
  };

  ProgramGovernanceAdmin.prototype.renderRetentionPreview = function(payload) {
    this.retentionCleanupHost.empty();
    const info = payload || null;
    this.state.lastRetentionReport = info;
    if (!info) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum preview de limpeza carregado.").appendTo(this.retentionCleanupHost);
      return;
    }
    $("<h3></h3>").text(info.mode === "apply" ? "Ultima limpeza aplicada" : "Preview da limpeza").appendTo(this.retentionCleanupHost);
    $("<div class=\"program-governance-admin-row\"></div>")
      .addClass((Number(info.totalRecords || 0) > 0) ? "is-warning" : "is-valid")
      .text("Total elegivel: " + String(info.totalRecords || 0))
      .appendTo(this.retentionCleanupHost);
    if (info.executionGroup || info.retentionRunId || info.relatedPreviewRunId) {
      $("<div class=\"program-governance-admin-row is-info\"></div>")
        .text("Execucao: " + String(info.executionGroup || "-") + " | Run #" + String(info.retentionRunId || "-") + (info.relatedPreviewRunId ? " | Preview relacionado #" + String(info.relatedPreviewRunId) : ""))
        .appendTo(this.retentionCleanupHost);
    }
    global.CrudUtils.ensureArray(info.items).forEach(function(item) {
      $("<div class=\"program-governance-admin-row\"></div>")
        .toggleClass("is-focused", Number(item.records || 0) > 0)
        .text(String(item.label || "-") + " | " + String(item.records || 0) + " | corte " + String(item.cutoff || "-"))
        .appendTo(this.retentionCleanupHost);
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderRetentionHistory = function(items) {
    this.retentionHistoryHost.empty();
    $("<h3></h3>").text("Historico da retencao").appendTo(this.retentionHistoryHost);
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhuma execucao registrada ate agora.").appendTo(this.retentionHistoryHost);
      this.retentionHistoryDetailHost.text("");
      return;
    }
    rows.forEach(function(item) {
      const row = $("<div class=\"program-governance-admin-row\"></div>")
        .addClass(String(item.mode || "") === "apply" ? "is-warning" : "is-info")
        .appendTo(this.retentionHistoryHost);
      row.toggleClass("is-focused", this.state.selectedRetentionRun && Number(this.state.selectedRetentionRun.id || 0) === Number(item.id || 0));
      $("<span></span>").text([
        String(item.createdAt || "-"),
        String(item.mode || "-"),
        String(item.executionGroup || "-"),
        String(item.source || "-"),
        String(item.executedBy || "-"),
        "total " + String(item.totalRecords || 0)
      ].join(" | ")).appendTo(row);
      if (item.databaseEnvironment || item.databaseIdentity) {
        $("<small></small>").text([
          item.databaseEnvironment ? "ambiente " + String(item.databaseEnvironment) : null,
          item.databaseIdentity ? "base " + String(item.databaseIdentity) : null
        ].filter(Boolean).join(" | ")).appendTo(row);
      }
      if (Number(item.deltaTotalRecords || 0) !== 0) {
        $("<small></small>").text("Delta total: " + String(item.deltaTotalRecords || 0)).appendTo(row);
      }
      if (item.beforeAfterLabel) {
        $("<small></small>").text(String(item.beforeAfterLabel)).appendTo(row);
      }
      if (item.pairedRun && item.pairedRun.id) {
        $("<small></small>").text("Relacionada a #" + String(item.pairedRun.id) + " (" + String(item.pairedRun.mode || "-") + ")").appendTo(row);
      }
      row.on("click", function() {
        this.state.selectedRetentionRun = item;
        this.renderRetentionHistory(this.state.retentionRuns);
      }.bind(this));
    }.bind(this));
    this.renderRetentionHistoryDetail(this.state.selectedRetentionRun || rows[0]);
  };

  ProgramGovernanceAdmin.prototype.renderRetentionHistoryDetail = function(item) {
    this.retentionHistoryCompareHost.empty();
    if (item) {
      $("<h3></h3>").text("Comparativo da execucao").appendTo(this.retentionHistoryCompareHost);
      if (item.executionGroup) {
        const relatedRuns = global.CrudUtils.ensureArray(this.state.retentionRuns).filter(function(candidate) {
          return String(candidate.executionGroup || "") === String(item.executionGroup || "");
        });
        $("<div class=\"program-governance-admin-row is-info\"></div>")
          .text("Grupo " + String(item.executionGroup) + " | execucoes " + String(relatedRuns.length))
          .appendTo(this.retentionHistoryCompareHost);
      }
      [
        "Execucao: " + String(item.executionGroup || "-"),
        "Modo: " + String(item.mode || "-"),
        "Total atual: " + String(item.totalRecords || 0),
        item.pairedRun ? ("Relacionada a " + String(item.pairedRun.mode || "-") + " #" + String(item.pairedRun.id || "-") + " com total " + String(item.pairedRun.totalRecords || 0)) : "Sem execucao relacionada"
      ].forEach(function(text) {
        $("<div class=\"program-governance-admin-row is-info\"></div>").text(text).appendTo(this.retentionHistoryCompareHost);
      }.bind(this));
      global.CrudUtils.ensureArray(item.deltaByTable).forEach(function(delta) {
        $("<div class=\"program-governance-admin-row\"></div>")
          .addClass(Number(delta.deltaRecords || 0) !== 0 ? "is-warning" : "is-valid")
          .text(String(delta.label || delta.table || "-") + " | atual " + String(delta.records || 0) + " | anterior " + String(delta.previousRecords || 0) + " | delta " + String(delta.deltaRecords || 0))
          .appendTo(this.retentionHistoryCompareHost);
      }.bind(this));
    }
    this.retentionHistoryDetailHost.text(item ? JSON.stringify(item, null, 2) : "");
  };

  ProgramGovernanceAdmin.prototype.currentAuditFilters = function() {
    return {
      eventType: this.timelineTypeInput && this.timelineTypeInput.value() || "",
      userId: this.timelineUserInput && this.timelineUserInput.value() || "",
      dateFrom: this.formatDateInput(this.timelineStartDateInput && this.timelineStartDateInput.value()),
      dateTo: this.formatDateInput(this.timelineEndDateInput && this.timelineEndDateInput.value()),
      days: Number(this.timelineDaysInput && this.timelineDaysInput.value() || 365)
    };
  };

  ProgramGovernanceAdmin.prototype.auditFilterStorageKey = function() {
    return "program-governance-audit-filters:" + this.auditFilterScopeProgramCode();
  };

  ProgramGovernanceAdmin.prototype.auditFilterScopeProgramCode = function() {
    return String(this.state.currentProgramCode || this.queryValue("programCode") || "global").trim().toLowerCase();
  };

  ProgramGovernanceAdmin.prototype.applySavedAuditFilters = function() {
    let stored = null;
    if (this.timelineTypeInput) {
      this.timelineTypeInput.value("");
    }
    if (this.timelineUserInput) {
      this.timelineUserInput.value("");
    }
    if (this.timelineDaysInput) {
      this.timelineDaysInput.value(365);
    }
    if (this.timelineStartDateInput) {
      this.timelineStartDateInput.value(null);
    }
    if (this.timelineEndDateInput) {
      this.timelineEndDateInput.value(null);
    }
    stored = global.CrudUtils.readLocalStateValue(this.auditFilterStorageKey(), null, { version: 1 });
    this.state.savedAuditFilters = stored;
    if (!stored || typeof stored !== "object") {
      return;
    }
    if (this.timelineUserInput && stored.userId) {
      this.timelineUserInput.value(String(stored.userId || ""));
    }
    if (this.timelineDaysInput && Number(stored.days || 0) > 0) {
      this.timelineDaysInput.value(Number(stored.days));
    }
    if (this.timelineStartDateInput && stored.dateFrom) {
      this.timelineStartDateInput.value(new Date(stored.dateFrom));
    }
    if (this.timelineEndDateInput && stored.dateTo) {
      this.timelineEndDateInput.value(new Date(stored.dateTo));
    }
  };

  ProgramGovernanceAdmin.prototype.persistAuditFilters = function(filters) {
    this.state.savedAuditFilters = filters || null;
    global.CrudUtils.saveLocalStateValue(this.auditFilterStorageKey(), filters || {}, { version: 1 });
  };

  ProgramGovernanceAdmin.prototype.loadOperationsSnapshot = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/operations",
      method: "GET",
      data: {
        programCode: this.state.currentProgramCode || "",
        builderProgramVersionId: this.state.currentVersionId || null
      }
    }).then(function(response) {
      this.state.operationsReport = response || null;
      this.renderOperationsPanel();
    }.bind(this)).catch(function() {
      return null;
    });
  };

  ProgramGovernanceAdmin.prototype.handleRefreshOperations = function() {
    return this.loadOperationsSnapshot().then(function() {
      global.CrudUtils.showMessage("Resumo operacional atualizado.", "info");
    }).catch(this.handleError.bind(this, "Nao foi possivel atualizar o resumo operacional."));
  };

  ProgramGovernanceAdmin.prototype.handleRunGovernanceMonitor = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/operations/monitor",
      method: "POST",
      data: {
        programCode: this.state.currentProgramCode || ""
      }
    }).then(function(response) {
      this.state.operationsReport = response || null;
      this.renderOperationsPanel();
      return this.refreshCurrent().then(function() {
        global.CrudUtils.showMessage("Monitor operacional executado.", "success");
      });
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel executar o monitor operacional."));
  };

  ProgramGovernanceAdmin.prototype.handleRunGovernanceOperations = function() {
    return global.CrudUtils.confirm({
      title: "Rodar operacao unificada",
      message: "A rotina vai revisar integridade, monitoramento e preview de retencao. Deseja continuar?",
      confirmText: "Executar",
      cancelText: "Cancelar"
    }).then(function(confirmed) {
      if (!confirmed) {
        return null;
      }
      return this.http.request({
        url: "/api/admin/program-builder/governance/operations",
        method: "POST",
        data: {
          programCode: this.state.currentProgramCode || "",
          builderProgramVersionId: this.state.currentVersionId || null,
          applyCleanup: false
        }
      }).then(function(response) {
        this.state.operationsReport = response || null;
        this.renderOperationsPanel();
        return this.refreshCurrent().then(function() {
          global.CrudUtils.showMessage("Operacao unificada executada.", "success");
        });
      }.bind(this));
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel executar a operacao unificada."));
  };

  ProgramGovernanceAdmin.prototype.handleExportRetentionHistory = function(format) {
    const run = this.state.selectedRetentionRun;
    if (!run) {
      global.CrudUtils.showMessage("Selecione uma execucao do historico para exportar.", "warning");
      return;
    }
    const fileBase = global.CrudUtils.buildExportFileName("governanca-retencao-historico", format === "csv" ? "csv" : "json", [
      this.auditFilterScopeProgramCode() || "global"
    ]);
    if (format === "csv") {
      const lines = ["label,table,records,previousRecords,deltaRecords"];
      global.CrudUtils.ensureArray(run.deltaByTable).forEach(function(item) {
        lines.push([
          item.label,
          item.table,
          item.records,
          item.previousRecords,
          item.deltaRecords
        ].map(function(value) {
          return "\"" + String(value == null ? "" : value).replace(/"/g, "\"\"") + "\"";
        }).join(","));
      });
      this.downloadFile(fileBase.replace(/\.json$/i, ".csv"), "text/csv;charset=utf-8", lines.join("\n"));
      return;
    }
    this.downloadFile(fileBase, "application/json;charset=utf-8", JSON.stringify(run, null, 2));
  };

  ProgramGovernanceAdmin.prototype.handleExportAuditData = function(format) {
    const payload = {
      filters: this.currentAuditFilters(),
      summary: this.state.auditSummary || {},
      timeline: this.filteredTimeline(),
      retentionRuns: global.CrudUtils.ensureArray(this.state.retentionRuns)
    };
    const fileBase = global.CrudUtils.buildExportFileName("governanca-auditoria", format === "csv" ? "csv" : "json", [
      this.auditFilterScopeProgramCode() || "global"
    ]);
    if (format === "csv") {
      const lines = ["type,userId,at,status,summary"];
      global.CrudUtils.ensureArray(payload.timeline).forEach(function(item) {
        lines.push([
          item.type,
          item.userId,
          item.at,
          item.status,
          item.summary || item.message || ""
        ].map(function(value) {
          return "\"" + String(value == null ? "" : value).replace(/"/g, "\"\"") + "\"";
        }).join(","));
      });
      this.downloadFile(fileBase.replace(/\.json$/i, ".csv"), "text/csv;charset=utf-8", lines.join("\n"));
      return;
    }
    this.downloadFile(fileBase, "application/json;charset=utf-8", JSON.stringify(payload, null, 2));
  };

  ProgramGovernanceAdmin.prototype.renderOverlays = function(items) {
    this.overlaysList.empty();
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum overlay encontrado para o programa atual.").appendTo(this.overlaysList);
      return;
    }
    rows.forEach(function(item) {
      const row = $("<div class=\"program-governance-admin-row\"></div>")
        .addClass((item.rebaseStatus || "") === "blocked" ? "is-error" : ((item.rebaseStatus || "") === "warning" ? "is-warning" : "is-valid"))
        .appendTo(this.overlaysList);
      $("<span></span>").text([
        "Overlay #" + String(item.id || "-"),
        String(item.subscriberId || "-"),
        String(item.customizationKind || "-"),
        "v" + String(item.latestVersionNumber || "-"),
        String(item.rebaseStatus || "-")
      ].join(" | ")).appendTo(row);
      const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(row);
      this.createButton(actions, "Usar no rebase", null, function() {
        this.overlayIdInput.value(String(item.id || ""));
        this.overlayVersionIdInput.value(String(item.latestVersionId || ""));
        this.tabs.select(8);
        this.handlePreviewRebase();
      }.bind(this));
      this.createButton(actions, "Abrir versoes", null, function() {
        this.overlayVersionsOverlayIdInput.value(String(item.id || ""));
        this.tabs.select(7);
        this.handleLoadOverlayVersions();
      }.bind(this));
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderOverlayVersions = function(items) {
    this.overlayVersionsList.empty();
    const rows = global.CrudUtils.ensureArray(items);
    if (!rows.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhuma versao de overlay carregada.").appendTo(this.overlayVersionsList);
      return;
    }
    rows.forEach(function(item) {
      const row = $("<div class=\"program-governance-admin-row\"></div>")
        .addClass(item.status === "published" ? "is-valid" : (item.rebaseStatus === "blocked" ? "is-error" : "is-warning"))
        .appendTo(this.overlayVersionsList);
      $("<span></span>").text([
        "Versao #" + String(item.versionNumber || "-"),
        "id " + String(item.id || "-"),
        String(item.status || "-"),
        item.baseProgramVersion ? "base " + item.baseProgramVersion : "base -"
      ].join(" | ")).appendTo(row);
      if (item.changeSummary) {
        $("<small></small>").text(String(item.changeSummary)).appendTo(row);
      }
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.renderOverlayVersionComparison = function(payload) {
    this.overlayVersionsCompareHost.empty();
    const info = payload || null;
    if (!info) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhuma comparacao carregada.").appendTo(this.overlayVersionsCompareHost);
      return;
    }
    $("<h3></h3>").text("Comparacao de versoes").appendTo(this.overlayVersionsCompareHost);
    $("<div class=\"program-governance-admin-row\"></div>")
      .addClass("is-info")
      .text("Secoes alteradas: " + String(info.changedSections || 0) + " | Caminhos alterados: " + String(info.changedPaths || 0))
      .appendTo(this.overlayVersionsCompareHost);
    global.CrudUtils.ensureArray(info.sections).forEach(function(section) {
      const row = $("<div class=\"program-governance-admin-row\"></div>").addClass("is-warning").appendTo(this.overlayVersionsCompareHost);
      $("<strong></strong>").text(String(section.key || "-") + ": ").appendTo(row);
      $("<span></span>").text(String(section.pathCount || 0) + " caminhos alterados").appendTo(row);
    }.bind(this));
    $("<pre class=\"program-builder-inline-json\"></pre>").text(JSON.stringify(info, null, 2)).appendTo(this.overlayVersionsCompareHost);
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
      overlays: 6,
      "overlay-versions": 7,
      rebase: 8
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

  ProgramGovernanceAdmin.prototype.handlePreviewRetention = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/retention/preview",
      method: "GET"
    }).then(function(response) {
      this.renderRetentionPreview(response);
      global.CrudUtils.showMessage("Preview da limpeza carregado.", "info");
      return this.loadRetentionHistory().catch(function() {
        return null;
      });
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel gerar o preview da limpeza."));
  };

  ProgramGovernanceAdmin.prototype.handleExecuteRetention = function() {
    return global.CrudUtils.confirm({
      title: "Executar limpeza",
      message: "A limpeza remove historico antigo conforme a politica atual. Deseja continuar?",
      confirmText: "Executar",
      cancelText: "Cancelar"
    }).then(function(confirmed) {
      if (!confirmed) {
        return null;
      }
      const selectedPreviewRun = this.state.selectedRetentionRun && String(this.state.selectedRetentionRun.mode || "") === "preview"
        ? Number(this.state.selectedRetentionRun.id || 0)
        : 0;
      return this.http.request({
        url: "/api/admin/program-builder/governance/retention/cleanup",
        method: "POST",
        data: {
          previewRunId: Number(this.state.lastRetentionReport && this.state.lastRetentionReport.retentionRunId || 0) || selectedPreviewRun || null
        }
      }).then(function(response) {
        this.renderRetentionPreview(response);
        global.CrudUtils.showMessage("Limpeza executada.", "success");
        return this.loadRetentionHistory().catch(function() {
          return null;
        }).then(function() {
          return this.refreshCurrent();
        }.bind(this));
      }.bind(this));
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel executar a limpeza da governanca."));
  };

  ProgramGovernanceAdmin.prototype.loadRetentionHistory = function() {
    return this.http.request({
      url: "/api/admin/program-builder/governance/retention/history",
      method: "GET",
      data: { limit: 20 }
    }).then(function(response) {
      this.state.retentionRuns = global.CrudUtils.ensureArray(response && response.items);
      this.renderRetentionHistory(this.state.retentionRuns);
    }.bind(this));
  };

  ProgramGovernanceAdmin.prototype.handleExportRetention = function(format) {
    const report = this.state.lastRetentionReport;
    if (!report) {
      global.CrudUtils.showMessage("Gere um preview ou execute a limpeza antes de exportar o relatorio.", "warning");
      return;
    }
    const fileBase = global.CrudUtils.buildExportFileName("governanca-retencao", format === "csv" ? "csv" : "json", [
      this.auditFilterScopeProgramCode() || "global"
    ]);
    if (format === "csv") {
      const lines = ["label,table,days,cutoff,records"];
      global.CrudUtils.ensureArray(report.items).forEach(function(item) {
        lines.push([
          item.label,
          item.table,
          item.days,
          item.cutoff,
          item.records
        ].map(function(value) {
          return "\"" + String(value == null ? "" : value).replace(/"/g, "\"\"") + "\"";
        }).join(","));
      });
      this.downloadFile(fileBase.replace(/\.json$/i, ".csv"), "text/csv;charset=utf-8", lines.join("\n"));
      return;
    }
    this.downloadFile(fileBase, "application/json;charset=utf-8", JSON.stringify(report, null, 2));
  };

  ProgramGovernanceAdmin.prototype.handleLoadOverlayVersions = function() {
    const overlayId = Number(this.overlayVersionsOverlayIdInput.value() || 0);
    if (!overlayId) {
      global.CrudUtils.showMessage("Informe o overlay para carregar as versoes.", "warning");
      return Promise.resolve();
    }
    this.state.selectedOverlayId = overlayId;
    return this.http.request({
      url: "/api/admin/program-builder/overlays/" + encodeURIComponent(overlayId) + "/versions",
      method: "GET"
    }).then(function(response) {
      const items = global.CrudUtils.ensureArray(response && response.items);
      this.state.overlayVersions = items;
      const dataSource = items.map(function(item) {
        return {
          value: String(item.id || ""),
          text: "v" + String(item.versionNumber || "-") + " [" + String(item.status || "draft") + "]"
        };
      });
      this.overlayVersionsLeftInput.setDataSource(dataSource);
      this.overlayVersionsRightInput.setDataSource(dataSource);
      if (items[0]) {
        this.overlayVersionsLeftInput.value(String(items[0].id || ""));
      }
      if (items[1]) {
        this.overlayVersionsRightInput.value(String(items[1].id || ""));
      } else if (items[0]) {
        this.overlayVersionsRightInput.value(String(items[0].id || ""));
      }
      this.renderOverlayVersions(items);
      global.CrudUtils.showMessage("Versoes do overlay carregadas.", "info");
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel carregar as versoes do overlay."));
  };

  ProgramGovernanceAdmin.prototype.handleCompareOverlayVersions = function() {
    const leftVersionId = Number(this.overlayVersionsLeftInput.value() || 0);
    const rightVersionId = Number(this.overlayVersionsRightInput.value() || 0);
    if (!leftVersionId || !rightVersionId) {
      global.CrudUtils.showMessage("Selecione as duas versoes para comparar.", "warning");
      return Promise.resolve();
    }
    return this.http.request({
      url: "/api/admin/program-builder/overlay-versions/compare",
      method: "GET",
      data: {
        leftVersionId: leftVersionId,
        rightVersionId: rightVersionId
      }
    }).then(function(response) {
      this.state.overlayVersionComparison = response || null;
      this.renderOverlayVersionComparison(this.state.overlayVersionComparison);
      global.CrudUtils.showMessage("Comparacao carregada.", "info");
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel comparar as versoes do overlay."));
  };

  ProgramGovernanceAdmin.prototype.handlePublishOverlayVersion = function() {
    const overlayVersionId = Number(this.overlayVersionsLeftInput.value() || 0);
    if (!overlayVersionId) {
      global.CrudUtils.showMessage("Selecione a versao que sera publicada.", "warning");
      return Promise.resolve();
    }
    return global.CrudUtils.confirm({
      title: "Publicar versao do overlay",
      message: "A versao selecionada sera marcada como publicada para o overlay atual. Deseja continuar?",
      confirmText: "Publicar",
      cancelText: "Cancelar"
    }).then(function(confirmed) {
      if (!confirmed) {
        return null;
      }
      return this.http.request({
        url: "/api/admin/program-builder/overlay-versions/" + encodeURIComponent(overlayVersionId) + "/publish",
        method: "POST"
      }).then(function() {
        global.CrudUtils.showMessage("Versao do overlay publicada.", "success");
        return this.handleLoadOverlayVersions().then(this.refreshCurrent.bind(this));
      }.bind(this));
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel publicar a versao do overlay."));
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
      this.state.overlayRebaseSelections = {};
      this.renderRebasePreview();
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel gerar o preview do rebase."));
  };

  ProgramGovernanceAdmin.prototype.handleExecuteRebase = function() {
    const overlayVersionId = Number(this.overlayVersionIdInput.value() || 0);
    if (!overlayVersionId) {
      global.CrudUtils.showMessage("Informe a versao do overlay.", "warning");
      return Promise.resolve();
    }
    const preview = this.state.overlayPreview || null;
    const hasBlocking = global.CrudUtils.ensureArray(preview && preview.sections).some(function(section) {
      return String(section.classification || "") === "conflict_blocking";
    });
    const runner = function(confirmWarning) {
      return this.http.request({
        url: "/api/admin/program-builder/overlay-versions/" + encodeURIComponent(overlayVersionId) + "/rebase",
        method: "POST",
        data: {
          resolutions: Object.assign({}, this.state.overlayRebaseSelections || {}, confirmWarning ? { __confirmWarning__: true } : {})
        }
      }).then(function(response) {
        this.state.overlayPreview = response && response.preview ? response.preview : response;
        this.state.overlayRebaseSelections = {};
        this.renderRebasePreview();
        global.CrudUtils.showMessage("Rebase executado.", "success");
        return this.handleLoadOverlayVersions().then(this.refreshCurrent.bind(this));
      }.bind(this));
    }.bind(this);

    if (hasBlocking || preview && preview.canApply === false) {
      global.CrudUtils.showMessage("O preview possui conflitos bloqueantes. Ajuste o overlay antes de tentar o rebase.", "error");
      return Promise.resolve();
    }

    if (!(preview && preview.requiresConfirmation)) {
      return runner(false).catch(this.handleError.bind(this, "Nao foi possivel executar o rebase."));
    }

    return global.CrudUtils.confirm({
      title: "Confirmar rebase",
      message: "Existem conflitos leves no rebase. Deseja seguir com o plano de resolucao atual?",
      confirmText: "Confirmar",
      cancelText: "Cancelar"
    }).then(function(confirmed) {
      if (!confirmed) {
        return null;
      }
      return runner(true);
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
    if (response.policyDecision) {
      $("<div class=\"program-governance-admin-row\"></div>")
        .addClass(response.canApply === false ? "is-error" : (response.requiresConfirmation ? "is-warning" : "is-valid"))
        .text(String(response.policyDecision))
        .appendTo(this.rebaseSummary);
    }
    const counters = { rebased: 0, overlay: 0, base: 0 };
    global.CrudUtils.ensureArray(response.sections).forEach(function(section) {
      const row = $("<div class=\"program-governance-admin-row\"></div>")
        .addClass(String(section.classification || "").indexOf("conflict") === 0 ? "is-warning" : "is-valid")
        .appendTo(this.rebaseSummary);
      $("<strong></strong>").text(String(section.key || "-") + ": ").appendTo(row);
      $("<span></span>").text(String(section.classification || "-")).appendTo(row);
      const entries = global.CrudUtils.ensureArray(section.entries);
      if (entries.length) {
        const table = $("<table class=\"program-builder-table\"></table>").appendTo(this.rebaseSummary);
        $("<thead><tr><th>Caminho</th><th>Classificacao</th><th>Base nova</th><th>Overlay</th><th>Rebase sugerido</th><th>Escolha</th></tr></thead>").appendTo(table);
        const body = $("<tbody></tbody>").appendTo(table);
        entries.forEach(function(entry) {
          const entryPath = String(entry.path || "");
          const options = global.CrudUtils.ensureArray(entry.resolutionOptions).length ? entry.resolutionOptions : ["rebased"];
          const selected = this.state.overlayRebaseSelections[entryPath] || entry.selectedResolution || options[0];
          counters[selected] = (counters[selected] || 0) + 1;
          const tr = $("<tr></tr>").appendTo(body);
          $("<td></td>").append($("<code></code>").text(entryPath)).appendTo(tr);
          $("<td></td>").text(String(entry.classification || "-")).appendTo(tr);
          $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry.baseValue))).appendTo(tr);
          $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry.overlayValue))).appendTo(tr);
          $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry.rebasedValue))).appendTo(tr);
          const select = $("<select></select>");
          options.forEach(function(option) {
            $("<option></option>")
              .attr("value", option)
              .prop("selected", option === selected)
              .text(option === "rebased" ? "Rebase sugerido" : (option === "overlay" ? "Manter overlay" : "Usar base"))
              .appendTo(select);
          });
          select.on("change", function() {
            this.state.overlayRebaseSelections[entryPath] = String($(this).val() || "rebased");
            this.renderRebasePreview();
          }.bind(this));
          $("<td></td>").append(select).appendTo(tr);
        }.bind(this));
      }
    }.bind(this));
    $("<div class=\"program-governance-admin-row is-info\"></div>")
      .text("Plano atual: rebase sugerido " + counters.rebased + " | manter overlay " + counters.overlay + " | usar base " + counters.base)
      .appendTo(this.rebaseSummary);
    if (response.finalResolutionSummary) {
      $("<div class=\"program-governance-admin-row is-info\"></div>")
        .text("Resumo final do plano: sugerido " + String(response.finalResolutionSummary.rebased || 0) + " | overlay " + String(response.finalResolutionSummary.overlay || 0) + " | base " + String(response.finalResolutionSummary.base || 0))
        .appendTo(this.rebaseSummary);
    }
    if (response.runtimeImpactSummary) {
      $("<div class=\"program-governance-admin-row is-info\"></div>")
        .text("Impacto no runtime: secoes criticas " + String(response.runtimeImpactSummary.criticalSections || 0) + " | conflitos leves " + String(response.runtimeImpactSummary.warningConflicts || 0) + " | conflitos bloqueantes " + String(response.runtimeImpactSummary.blockingConflicts || 0))
        .appendTo(this.rebaseSummary);
    }
    const localPolicyViolations = this.countLocalRebasePolicyViolations(response.sections);
    if (response.policySummary) {
      $("<div class=\"program-governance-admin-row\"></div>")
        .addClass(localPolicyViolations > 0 || Number(response.policySummary.violationCount || 0) > 0 ? "is-error" : "is-info")
        .text(String(response.policySummary.message || ""))
        .appendTo(this.rebaseSummary);
      global.CrudUtils.ensureArray(response.policySummary.violations).forEach(function(item) {
        $("<div class=\"program-governance-admin-row is-error\"></div>")
          .text("Politica critica: " + String(item.path || "-") + " aceita apenas " + global.CrudUtils.ensureArray(item.allowedResolutions).join(", "))
          .appendTo(this.rebaseSummary);
      }.bind(this));
    }
    if (localPolicyViolations > 0) {
      $("<div class=\"program-governance-admin-row is-error\"></div>")
        .text("Escolhas locais atuais violam a politica de rebase em " + String(localPolicyViolations) + " campo(s) critico(s).")
        .appendTo(this.rebaseSummary);
    }
    if (global.CrudUtils.ensureArray(response.finalDiffEntries).length) {
      $("<h3></h3>").text("Diff final consolidado").appendTo(this.rebaseSummary);
      const diffTable = $("<table class=\"program-builder-table\"></table>").appendTo(this.rebaseSummary);
      $("<thead><tr><th>Caminho</th><th>Atual</th><th>Final</th><th>Origem</th></tr></thead>").appendTo(diffTable);
      const diffBody = $("<tbody></tbody>").appendTo(diffTable);
      global.CrudUtils.ensureArray(response.finalDiffEntries).forEach(function(entry) {
        const tr = $("<tr></tr>").appendTo(diffBody);
        $("<td></td>").append($("<code></code>").text(String(entry.path || "-"))).appendTo(tr);
        $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry.currentValue))).appendTo(tr);
        $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry.finalValue))).appendTo(tr);
        $("<td></td>").text(String(entry.selectedResolution || entry.classification || "-")).appendTo(tr);
      }.bind(this));
      if (response.finalDiffDefinition) {
        $("<pre class=\"program-builder-inline-json\"></pre>").text(JSON.stringify(response.finalDiffDefinition, null, 2)).appendTo(this.rebaseSummary);
      }
    }
    this.rebaseJson.text(JSON.stringify(response, null, 2));
  };

  ProgramGovernanceAdmin.prototype.countLocalRebasePolicyViolations = function(sections) {
    let count = 0;
    global.CrudUtils.ensureArray(sections).forEach(function(section) {
      global.CrudUtils.ensureArray(section && section.entries).forEach(function(entry) {
        const pathKey = String(entry && entry.path || "");
        const selected = String((this.state.overlayRebaseSelections[pathKey] || entry && entry.selectedResolution || "rebased")).toLowerCase();
        if (String(entry && entry.classification || "") === "conflict_warning" && selected === "overlay") {
          count += 1;
        }
      }, this);
    }, this);
    return count;
  };

  ProgramGovernanceAdmin.prototype.stringifyInlineJson = function(value) {
    if (value == null) {
      return "null";
    }
    if (typeof value === "string") {
      return value;
    }
    try {
      return JSON.stringify(value);
    } catch (_) {
      return String(value);
    }
  };

  ProgramGovernanceAdmin.prototype.downloadFile = function(fileName, mimeType, content) {
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

  ProgramGovernanceAdmin.prototype.formatDateInput = function(value) {
    if (!value) {
      return "";
    }
    const date = value instanceof Date ? value : new Date(value);
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return "";
    }
    return date.toISOString().slice(0, 10);
  };

  ProgramGovernanceAdmin.prototype.resolveTimelineTarget = function(item) {
    if (!item || !item.type) {
      return null;
    }
    const details = item.details || {};
    switch (String(item.type)) {
      case "request":
        return { tab: "requests", query: { focusRequestCode: details.requestCode || "" } };
      case "grant":
        return { tab: "grants", query: { focusGrantId: details.id || "" } };
      case "approval":
        return { tab: "approvals", query: { focusApprovalId: details.id || "" } };
      case "overlay":
        return { tab: "overlays", query: { overlayId: details.id || "", focusOverlayId: details.id || "" } };
      case "publish":
        return { tab: "summary", query: {} };
      default:
        return null;
    }
  };

  ProgramGovernanceAdmin.prototype.buildGovernanceUrl = function(tab, extraQuery) {
    const current = String(global.location && global.location.href || "");
    const next = current
      .replace(/program-audit\.html/i, "program-governance.html")
      .replace(/program-grants\.html/i, "program-governance.html")
      .replace(/program-approvals\.html/i, "program-governance.html")
      .replace(/program-retention\.html/i, "program-governance.html")
      .replace(/program-retention-history\.html/i, "program-governance.html")
      .replace(/program-operations\.html/i, "program-governance.html")
      .replace(/program-overlays\.html/i, "program-governance.html")
      .replace(/program-overlay-versions\.html/i, "program-governance.html");
    let targetUrl;
    try {
      targetUrl = new URL(next);
    } catch (_) {
      targetUrl = new URL(global.location.href);
    }
    targetUrl.searchParams.set("programCode", this.state.currentProgramCode || "");
    if (this.state.currentVersionId) {
      targetUrl.searchParams.set("builderProgramVersionId", String(this.state.currentVersionId));
    }
    if (tab) {
      targetUrl.searchParams.set("tab", tab);
    }
    Object.keys(extraQuery || {}).forEach(function(key) {
      const value = extraQuery[key];
      if (value === null || value === undefined || String(value) === "") {
        return;
      }
      targetUrl.searchParams.set(key, String(value));
    });
    return targetUrl.toString();
  };

  ProgramGovernanceAdmin.prototype.buildFocusedTargetUrl = function(item, target) {
    const current = String(global.location && global.location.href || "");
    let next = current;
    switch (String(item && item.type || "")) {
      case "grant":
        next = current.replace(/program-[^/]+\.html/i, "program-grants.html");
        break;
      case "approval":
        next = current.replace(/program-[^/]+\.html/i, "program-approvals.html");
        break;
      case "overlay":
        next = current.replace(/program-[^/]+\.html/i, "program-overlays.html");
        break;
      case "retention":
        next = current.replace(/program-[^/]+\.html/i, "program-retention-history.html");
        break;
      default:
        next = current.replace(/program-[^/]+\.html/i, "program-audit.html");
        break;
    }
    let targetUrl;
    try {
      targetUrl = new URL(next);
    } catch (_) {
      targetUrl = new URL(global.location.href);
    }
    targetUrl.searchParams.set("programCode", this.state.currentProgramCode || "");
    if (this.state.currentVersionId) {
      targetUrl.searchParams.set("builderProgramVersionId", String(this.state.currentVersionId));
    }
    Object.keys((target && target.query) || {}).forEach(function(key) {
      const value = target.query[key];
      if (value === null || value === undefined || String(value) === "") {
        return;
      }
      targetUrl.searchParams.set(key, String(value));
    });
    return targetUrl.toString();
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
