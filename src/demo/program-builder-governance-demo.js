(function(global) {
  "use strict";

  function clone(value) {
    return global.CrudUtils.clone(value);
  }

  function GovernanceDemoHttpClient() {
    const source = global.ProgramBuilderGovernanceEmbeddedData || {};
    this.state = {
      bootstrap: clone(source.bootstrap || {}),
      entityDefinition: clone(source.entityDefinition || {}),
      programResponse: clone(source.standardProgramResponse || {}),
      overlayVersion: clone(source.overlayVersion || {}),
      overlayPreview: clone(source.overlayPreview || {}),
      currentLock: clone(source.currentLock || null),
      requestCounter: 0,
      grantCounter: 0,
      approvalCounter: 0,
      tests: []
    };
  }

  GovernanceDemoHttpClient.prototype.currentVersion = function() {
    return this.state.programResponse.versions[0];
  };

  GovernanceDemoHttpClient.prototype.request = function(config) {
    const url = String(config && config.url || "");
    const method = String(config && config.method || "GET").toUpperCase();
    const data = config && config.data || {};

    if (url === "/api/runtime/literals/pt-BR" && method === "GET") {
      return Promise.resolve({ literals: {} });
    }
    if (url === "/api/admin/program-builder/bootstrap" && method === "GET") {
      return Promise.resolve(clone(this.state.bootstrap));
    }
    if (url === "/api/admin/program-builder/locks/acquire" && method === "POST") {
      this.state.currentLock = Object.assign({}, this.state.currentLock || {}, {
        id: 900,
        scopeType: data.scopeType || "program",
        scopeCode: data.scopeCode || "cd1001",
        grantId: data.grantId != null ? Number(data.grantId) : (this.state.currentLock && this.state.currentLock.grantId) || null,
        lockToken: "lock-governado",
        userName: "Analista"
      });
      return Promise.resolve({
        status: "acquired",
        heartbeatIntervalSeconds: 45,
        lock: clone(this.state.currentLock)
      });
    }
    if (url === "/api/admin/program-builder/locks/heartbeat" && method === "POST") {
      return Promise.resolve({
        status: "active",
        heartbeatIntervalSeconds: 45,
        lock: clone(this.state.currentLock)
      });
    }
    if (url === "/api/admin/program-builder/locks/release" && method === "POST") {
      this.state.currentLock = null;
      return Promise.resolve({ status: "released" });
    }
    if (url === "/api/admin/program-builder/entities/cliente" && method === "GET") {
      return Promise.resolve(clone(this.state.entityDefinition));
    }
    if (url === "/api/admin/program-builder/programs/cd1001" && method === "GET") {
      return Promise.resolve(clone(this.state.programResponse));
    }
    if (url.indexOf("/api/admin/program-builder/governance/dashboard") === 0 && method === "GET") {
      const current = this.currentVersion();
      const request = current.governance && current.governance.request ? [clone(current.governance.request)] : [];
      const grants = current.governance && current.governance.grant ? [clone(current.governance.grant)] : [];
      const approvals = current.governance && current.governance.approval ? [clone(current.governance.approval)] : [];
      const tests = clone(this.state.tests || []);
      const activeLocks = this.state.currentLock ? [clone(this.state.currentLock)] : [];
      const timeline = []
        .concat(request.map(function(item) {
          return {
            type: "request",
            label: "Solicitacao",
            status: item.status || "pending",
            description: item.requestCode || "Solicitacao",
            timestamp: "2026-05-16T10:00:00-03:00"
          };
        }))
        .concat(grants.map(function(item) {
          return {
            type: "grant",
            label: "Grant",
            status: item.status || "active",
            description: "Grant #" + String(item.id || "-") + " para " + String(item.grantedToUserId || "-"),
            timestamp: "2026-05-16T10:05:00-03:00"
          };
        }))
        .concat(activeLocks.map(function(item) {
          return {
            type: "lock",
            label: "Lock",
            status: item.status || "active",
            description: String(item.scopeType || "scope") + ": " + String(item.scopeCode || "-"),
            timestamp: "2026-05-16T10:06:00-03:00"
          };
        }))
        .concat(tests.map(function(item, index) {
          return {
            type: "test",
            label: "Teste",
            status: item.status || "passed",
            description: String(item.bundleId || "-") + " / " + String(item.testPlanId || "roteiro-web"),
            timestamp: "2026-05-16T10:" + String(10 + index).padStart(2, "0") + ":00-03:00"
          };
        }))
        .concat(approvals.map(function(item) {
          return {
            type: "approval",
            label: "Aprovacao",
            status: item.status || "approved",
            description: "Aprovacao #" + String(item.id || "-") + " / bundle " + String(item.testExecutionBundleId || "-"),
            timestamp: "2026-05-16T10:20:00-03:00"
          };
        }))
        .concat([{
          type: "publish",
          label: "Versao",
          status: current.status || "draft",
          description: "Versao " + String(current.version || "1.0.0"),
          timestamp: "2026-05-16T10:30:00-03:00"
        }]);
      const suggestedActions = [];
      if (request[0] && request[0].status === "pending") {
        suggestedActions.push({ severity: "warning", text: "Existe solicitacao pendente. O proximo passo esperado e liberar ou rejeitar o grant." });
      }
      if (grants[0] && grants[0].status === "frozen") {
        suggestedActions.push({ severity: "warning", text: "Existe grant congelado. Decida se ele deve voltar a `active` ou ser revogado." });
      }
      if (grants[0] && grants[0].status === "active" && !tests.some(function(item) { return item.status === "passed"; })) {
        suggestedActions.push({ severity: "info", text: "Grant ativo sem bundle aprovado. Registrar testes e evidencias antes da aprovacao final." });
      }
      if (tests.some(function(item) { return item.status === "passed"; }) && (!approvals[0] || approvals[0].status !== "approved")) {
        suggestedActions.push({ severity: "info", text: "Bundle aprovado sem aprovacao final. Registrar a aprovacao para liberar o publish." });
      }
      if (approvals[0] && approvals[0].status === "approved" && current.status !== "published") {
        suggestedActions.push({ severity: "success", text: "Gate completo para publish. A versao ja pode seguir para publicacao governada." });
      }
      if (!suggestedActions.length) {
        suggestedActions.push({ severity: "info", text: "Sem pendencias imediatas. Use o historico e a linha do tempo para auditoria do fluxo." });
      }
      return Promise.resolve({
        requests: request,
        grants: grants,
        approvals: approvals,
        tests: tests,
        currentVersion: {
          id: current.id,
          version: current.version,
          status: current.status,
          updatedAt: "2026-05-16T10:30:00-03:00",
          publishedAt: current.status === "published" ? "2026-05-16T10:30:00-03:00" : null
        },
        activeLocks: activeLocks,
        timeline: timeline,
        retentionPolicy: clone((current.governance && current.governance.retentionPolicy) || {
          changeRequestsDays: 180,
          grantsDays: 180,
          approvalsDays: 365,
          testExecutionsDays: 365,
          administrativeNotificationsDays: 30
        }),
        summary: {
          pendingRequests: request[0] && request[0].status === "pending" ? 1 : 0,
          activeGrants: grants[0] && grants[0].status === "active" ? 1 : 0,
          approvedPublications: approvals[0] && approvals[0].status === "approved" ? 1 : 0,
          passedTests: tests.filter(function(item) { return item.status === "passed"; }).length
        },
        suggestedActions: suggestedActions
      });
    }
    if (url === "/api/admin/program-builder/governance/retention" && method === "POST") {
      const current = this.currentVersion();
      current.governance = current.governance || {};
      current.governance.retentionPolicy = Object.assign({}, current.governance.retentionPolicy || {}, {
        changeRequestsDays: Number(data.changeRequestsDays || 180),
        grantsDays: Number(data.grantsDays || 180),
        approvalsDays: Number(data.approvalsDays || 365),
        testExecutionsDays: Number(data.testExecutionsDays || 365),
        administrativeNotificationsDays: Number(data.administrativeNotificationsDays || 30)
      });
      return Promise.resolve({
        items: [],
        policy: clone(current.governance.retentionPolicy),
        retentionPolicy: clone(current.governance.retentionPolicy)
      });
    }
    if (url === "/api/admin/program-builder/governance/requests" && method === "POST") {
      this.state.requestCounter += 1;
      const request = {
        id: 600 + this.state.requestCounter,
        requestCode: "CD1001-REQ-" + String(this.state.requestCounter).padStart(2, "0"),
        status: "pending",
        requestedActions: clone(data.requestedActions || ["edit", "publish"])
      };
      this.currentVersion().governance.request = request;
      return Promise.resolve({ request: clone(request) });
    }
    if (url === "/api/admin/program-builder/governance/grants" && method === "POST") {
      this.state.grantCounter += 1;
      const grant = {
        id: 700 + this.state.grantCounter,
        requestId: Number(data.requestId || 0),
        requestCode: this.currentVersion().governance.request && this.currentVersion().governance.request.requestCode || "",
        programCode: "cd1001",
        grantedToUserId: data.grantedToUserId || "analista",
        allowedActions: ["edit", "publish"],
        status: "active"
      };
      this.currentVersion().governance.grant = grant;
      this.state.currentLock.grantId = grant.id;
      return Promise.resolve({ grant: clone(grant) });
    }
    if (url === "/api/admin/program-builder/governance/grants/status" && method === "POST") {
      const grant = this.currentVersion().governance.grant;
      if (!grant) {
        return Promise.reject({ error: { code: "PROGRAM_CHANGE_GRANT_NOT_FOUND", message: "Grant nao encontrado." } });
      }
      grant.status = String(data.status || "active");
      if (grant.status !== "active" && this.state.currentLock) {
        this.state.currentLock.grantId = null;
      }
      return Promise.resolve({ grant: clone(grant) });
    }
    if (url === "/api/admin/program-builder/governance/tests" && method === "POST") {
      const testExecution = {
        id: 800 + this.state.tests.length + 1,
        bundleId: String(data.bundleId || ""),
        status: String(data.status || "passed")
      };
      this.state.tests.push(testExecution);
      return Promise.resolve({ testExecution: clone(testExecution) });
    }
    if (url === "/api/admin/program-builder/governance/approvals" && method === "POST") {
      this.state.approvalCounter += 1;
      const approval = {
        id: 900 + this.state.approvalCounter,
        status: "approved",
        testExecutionBundleId: String(data.bundleId || "")
      };
      this.currentVersion().governance.approval = approval;
      return Promise.resolve({ approval: clone(approval) });
    }
    if (/\/api\/admin\/program-builder\/versions\/\d+\/publish$/.test(url) && method === "POST") {
      const current = this.currentVersion();
      const grant = current.governance && current.governance.grant;
      const approval = current.governance && current.governance.approval;
      const bundleId = approval && approval.testExecutionBundleId;
      const passedTests = this.state.tests.filter(function(item) {
        return item.bundleId === bundleId && item.status === "passed";
      });
      if (!grant || grant.status !== "active" || !approval || approval.status !== "approved" || !bundleId || !passedTests.length || !this.state.currentLock || Number(this.state.currentLock.grantId || 0) !== Number(grant.id)) {
        return Promise.reject({ error: { code: "PROGRAM_PUBLICATION_GOVERNANCE_FAILED", message: "Gate de governanca incompleto para publicar." } });
      }
      current.status = "published";
      current.governance.grant.status = "consumed";
      this.state.currentLock.grantId = null;
      return Promise.resolve({
        program: { code: "cd1001", status: "published" },
        versions: [clone(current)]
      });
    }
    if (url === "/api/admin/program-builder/overlays/700/rebase-preview" && method === "GET") {
      return Promise.resolve(clone(this.state.overlayPreview));
    }
    if (url === "/api/admin/program-builder/overlay-versions/701/rebase" && method === "POST") {
      return Promise.resolve({
        status: "warning",
        overlayId: 700,
        newOverlayVersionId: 702,
        preview: clone(this.state.overlayPreview)
      });
    }
    if (url === "/api/admin/program-builder/preview" && method === "POST") {
      return Promise.resolve({
        definition: {
          screenId: "cad.clientes",
          pageType: "custom",
          program: { title: "Cadastro governado" }
        },
        diagnostics: []
      });
    }

    return Promise.resolve({});
  };

  global.GovernanceDemoHttpClient = GovernanceDemoHttpClient;

  document.addEventListener("DOMContentLoaded", function() {
    if (!document.getElementById("program-builder-root")) {
      return;
    }
    const http = new GovernanceDemoHttpClient();
    const app = new global.ProgramBuilder({
      root: "#program-builder-root",
      httpClient: http
    });
    app.init().then(function() {
      const originalRefreshBootstrap = app.refreshBootstrap.bind(app);
      app.refreshBootstrap = function() {
        return originalRefreshBootstrap().then(function(result) {
          app.state.currentLock = clone(http.state.currentLock);
          app.syncToolbarState();
          return result;
        });
      };
      const originalRefreshProgramVersions = app.refreshProgramVersions.bind(app);
      app.refreshProgramVersions = function(programCode) {
        return originalRefreshProgramVersions(programCode).then(function(result) {
          app.state.currentLock = clone(http.state.currentLock);
          app.syncToolbarState();
          return result;
        });
      };
      app.populateEntityForm(clone(http.state.entityDefinition));
      app.populateProgramForm(clone(http.currentVersion()));
      app.state.currentLock = clone(http.state.currentLock);
      app.syncToolbarState();
      global.programBuilderGovernanceDemoApp = app;
      global.programBuilderGovernanceDemoHttp = http;
    });
  });
})(window);
