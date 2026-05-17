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
      overlayVersions: clone(source.overlayVersions || [
        {
          id: 701,
          overlayId: 700,
          versionNumber: 2,
          status: "draft",
          changeSummary: "Revisao do formulario e do titulo.",
          publishedAt: null,
          updatedAt: "2026-05-16T10:25:00-03:00",
          baseProgramVersionId: 500,
          baseProgramVersion: "1.0.0",
          rebaseStatus: "warning",
          rebaseReason: "A base e o overlay alteraram as mesmas secoes do contrato.",
          rebaseSummaryCounts: { autoMerge: 0, overlayOnly: 0, warningConflicts: 1, blockingConflicts: 0 }
        },
        {
          id: 699,
          overlayId: 700,
          versionNumber: 1,
          status: "published",
          changeSummary: "Primeira versao publicada do overlay.",
          publishedAt: "2026-05-15T16:00:00-03:00",
          updatedAt: "2026-05-15T16:00:00-03:00",
          baseProgramVersionId: 499,
          baseProgramVersion: "0.9.0",
          rebaseStatus: "ok",
          rebaseReason: "Sem conflitos relevantes.",
          rebaseSummaryCounts: { autoMerge: 1, overlayOnly: 0, warningConflicts: 0, blockingConflicts: 0 }
        }
      ]),
      currentLock: clone(source.currentLock || null),
      requestCounter: 0,
      grantCounter: 0,
      approvalCounter: 0,
      tests: [],
      retentionPreview: {
        mode: "preview",
        totalRecords: 5,
        items: [
          { label: "Solicitacoes resolvidas", table: "program_change_request", days: 180, cutoff: "2025-11-17 00:00:00", records: 2 },
          { label: "Grants encerrados", table: "program_change_grant", days: 180, cutoff: "2025-11-17 00:00:00", records: 1 },
          { label: "Aprovacoes encerradas", table: "program_publication_approval", days: 365, cutoff: "2025-05-16 00:00:00", records: 1 },
          { label: "Execucoes de teste", table: "program_test_execution", days: 365, cutoff: "2025-05-16 00:00:00", records: 1 },
          { label: "Notificacoes administrativas", table: "runtime_notification", days: 30, cutoff: "2026-04-16 00:00:00", records: 0 }
        ]
      },
      retentionRuns: [
        {
          id: 1,
          mode: "preview",
          source: "ui",
          executionGroup: "ret-1",
          relatedRunId: null,
          beforeAfterLabel: "Preview da politica atual",
          executedBy: "analista",
          databaseEnvironment: "dev",
          databaseIdentity: "construtor-pg-dev",
          totalRecords: 5,
          createdAt: "2026-05-16T10:15:00-03:00",
          items: [
            { label: "Solicitacoes resolvidas", table: "program_change_request", days: 180, cutoff: "2025-11-17 00:00:00", records: 2 }
          ],
          deltaTotalRecords: 5,
          deltaByTable: [
            { label: "Solicitacoes resolvidas", table: "program_change_request", records: 2, previousRecords: 0, deltaRecords: 2 }
          ]
        }
      ]
    };
    this.state.overlayPreview = this.decorateOverlayPreview(this.state.overlayPreview || {});
  }

  GovernanceDemoHttpClient.prototype.decorateOverlayPreview = function(preview) {
    const clonePreview = clone(preview || {});
    const status = String(clonePreview.status || "warning");
    clonePreview.canApply = status !== "blocked";
    clonePreview.requiresConfirmation = status === "warning";
    clonePreview.policyDecision = clonePreview.policyDecision || (status === "blocked"
      ? "Conflito bloqueante: rebase proibido ate revisao manual."
      : (status === "warning"
        ? "Conflito leve: rebase permitido apenas com confirmacao explicita."
        : "Sem bloqueios: rebase pode seguir normalmente."));
    clonePreview.finalResolutionSummary = clonePreview.finalResolutionSummary || { rebased: 1, overlay: 0, base: 0 };
    clonePreview.runtimeImpactSummary = clonePreview.runtimeImpactSummary || { criticalSections: 0, warningConflicts: 1, blockingConflicts: 0 };
    clonePreview.policySummary = clonePreview.policySummary || {
      criticalWarningPaths: 1,
      violationCount: 0,
      violations: [],
      message: "Conflitos leves aceitam apenas rebase sugerido ou base publicada."
    };
    clonePreview.finalDiffEntries = clonePreview.finalDiffEntries || [{
      path: "program.title",
      currentValue: "Cadastro de clientes",
      finalValue: "Cadastro de clientes ajustado",
      selectedResolution: "rebased",
      classification: "changed"
    }];
    clonePreview.finalDiffDefinition = clonePreview.finalDiffDefinition || {
      program: { title: "Cadastro de clientes ajustado" }
    };
    return clonePreview;
  };

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
    if ((url.indexOf("/api/admin/program-builder/governance/dashboard") === 0 || url.indexOf("/api/admin/program-builder/governance/audit") === 0) && method === "GET") {
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
            timestamp: "2026-05-16T10:00:00-03:00",
            userId: "analista",
            details: item
          };
        }))
        .concat(grants.map(function(item) {
          return {
            type: "grant",
            label: "Grant",
            status: item.status || "active",
            description: "Grant #" + String(item.id || "-") + " para " + String(item.grantedToUserId || "-"),
            timestamp: "2026-05-16T10:05:00-03:00",
            userId: item.grantedToUserId || "analista",
            details: item
          };
        }))
        .concat(activeLocks.map(function(item) {
          return {
            type: "lock",
            label: "Lock",
            status: item.status || "active",
            description: String(item.scopeType || "scope") + ": " + String(item.scopeCode || "-"),
            timestamp: "2026-05-16T10:06:00-03:00",
            userId: item.userId || "analista",
            details: item
          };
        }))
        .concat(tests.map(function(item, index) {
          return {
            type: "test",
            label: "Teste",
            status: item.status || "passed",
            description: String(item.bundleId || "-") + " / " + String(item.testPlanId || "roteiro-web"),
            timestamp: "2026-05-16T10:" + String(10 + index).padStart(2, "0") + ":00-03:00",
            userId: "analista",
            details: item
          };
        }))
        .concat(approvals.map(function(item) {
          return {
            type: "approval",
            label: "Aprovacao",
            status: item.status || "approved",
            description: "Aprovacao #" + String(item.id || "-") + " / bundle " + String(item.testExecutionBundleId || "-"),
            timestamp: "2026-05-16T10:20:00-03:00",
            userId: "aprovador",
            details: item
          };
        }))
        .concat([{
          type: "publish",
          label: "Versao",
          status: current.status || "draft",
          description: "Versao " + String(current.version || "1.0.0"),
          timestamp: "2026-05-16T10:30:00-03:00",
          userId: "sistema",
          details: {
            id: current.id,
            version: current.version,
            status: current.status
          }
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
      const overlays = [{
        id: 700,
        programCode: "cd1001",
        subscriberId: "tenant-a",
        customizationKind: "customer_overlay",
        status: "draft",
        baseProgramVersionId: 500,
        upgradeFrozen: false,
        frozenReason: null,
        updatedAt: "2026-05-16T10:25:00-03:00",
        latestVersionId: 701,
        latestVersionNumber: 2,
        latestVersionStatus: "draft",
        rebaseStatus: String(this.state.overlayPreview.status || "warning"),
        rebaseReason: String(this.state.overlayPreview.reason || ""),
        rebaseSummaryCounts: clone(this.state.overlayPreview.summaryCounts || { autoMerge: 0, overlayOnly: 0, warningConflicts: 1, blockingConflicts: 0 })
      }];
      const retentionPreview = Object.assign({}, clone(this.state.retentionPreview || {}), {
        policy: clone((current.governance && current.governance.retentionPolicy) || {
          changeRequestsDays: 180,
          grantsDays: 180,
          approvalsDays: 365,
          testExecutionsDays: 365,
          administrativeNotificationsDays: 30
        })
      });
      const operationalSignals = [
        { severity: "warning", label: "Solicitacoes pendentes", count: request[0] && request[0].status === "pending" ? 1 : 0, description: "Itens aguardando decisao." },
        { severity: "warning", label: "Grants congelados/revogados", count: grants.filter(function(item) { return ["frozen", "revoked"].includes(item.status); }).length, description: "Fluxos pausados ou encerrados." },
        { severity: "warning", label: "Overlays em revisao", count: overlays.filter(function(item) { return ["warning", "blocked"].includes(item.rebaseStatus); }).length, description: "Customizacoes do assinante com rebase pendente." },
        { severity: "success", label: "Integridade invalida", count: 0, description: "Nenhum registro estrutural invalido no mock." },
        { severity: retentionPreview.totalRecords > 0 ? "info" : "success", label: "Retencao elegivel", count: retentionPreview.totalRecords || 0, description: "Registros antigos prontos para limpeza." }
      ];
      const payload = {
        requests: request,
        grants: grants,
        approvals: approvals,
        tests: tests,
        overlays: overlays,
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
        retentionPreview: retentionPreview,
        retentionRuns: clone(this.state.retentionRuns || []),
        integrityCoverage: {
          supportedTables: ["builder_program", "builder_program_version", "builder_entity", "screen_definition", "runtime_endpoint", "builder_program_overlay", "builder_program_overlay_version", "program_change_request", "program_change_grant", "program_publication_approval", "program_test_execution", "import_export_mapping", "import_export_mapping_version", "import_export_schedule", "system_parameter", "system_parameter_value"],
          supportedCount: 16,
          invalidRecords: 0
        },
        summary: {
          pendingRequests: request[0] && request[0].status === "pending" ? 1 : 0,
          activeGrants: grants[0] && grants[0].status === "active" ? 1 : 0,
          approvedPublications: approvals[0] && approvals[0].status === "approved" ? 1 : 0,
          passedTests: tests.filter(function(item) { return item.status === "passed"; }).length,
          warningOverlays: overlays.filter(function(item) { return item.rebaseStatus === "warning"; }).length,
          blockedOverlays: overlays.filter(function(item) { return item.rebaseStatus === "blocked"; }).length,
          retentionEligibleRecords: retentionPreview.totalRecords || 0,
          invalidIntegrityRecords: 0
        },
        suggestedActions: suggestedActions,
        operationalSignals: operationalSignals
      };
      if (url.indexOf("/api/admin/program-builder/governance/audit") === 0) {
        const eventType = String(data.eventType || "").trim();
        const userId = String(data.userId || "").trim().toLowerCase();
        const dateFrom = String(data.dateFrom || "").trim();
        const dateTo = String(data.dateTo || "").trim();
        const filteredTimeline = timeline.filter(function(item) {
          if (eventType && String(item.type || "") !== eventType) {
            return false;
          }
          if (userId && String(item.userId || "").toLowerCase().indexOf(userId) < 0) {
            return false;
          }
          const timestamp = Date.parse(String(item.timestamp || ""));
          if (dateFrom) {
            const fromValue = Date.parse(dateFrom + "T00:00:00");
            if (!Number.isNaN(timestamp) && timestamp < fromValue) {
              return false;
            }
          }
          if (dateTo) {
            const toValue = Date.parse(dateTo + "T23:59:59");
            if (!Number.isNaN(timestamp) && timestamp > toValue) {
              return false;
            }
          }
          return true;
        });
        const filteredRuns = clone(this.state.retentionRuns || []).filter(function(item) {
          const timestamp = Date.parse(String(item.createdAt || ""));
          if (dateFrom) {
            const fromValue = Date.parse(dateFrom + "T00:00:00");
            if (!Number.isNaN(timestamp) && timestamp < fromValue) {
              return false;
            }
          }
          if (dateTo) {
            const toValue = Date.parse(dateTo + "T23:59:59");
            if (!Number.isNaN(timestamp) && timestamp > toValue) {
              return false;
            }
          }
          return true;
        });
        return Promise.resolve({
          programCode: "cd1001",
          builderProgramVersionId: current.id,
          filters: { eventType: eventType, userId: userId, dateFrom: dateFrom, dateTo: dateTo },
          timeline: filteredTimeline,
          retentionRuns: filteredRuns,
          summary: {
            timelineCount: filteredTimeline.length,
            retentionRunCount: filteredRuns.length,
            eventTypes: Array.from(new Set(filteredTimeline.map(function(item) { return item.type; }))),
            users: Array.from(new Set(filteredTimeline.map(function(item) { return item.userId; }).filter(Boolean))),
            eventTypeCounts: Array.from(new Set(filteredTimeline.map(function(item) { return item.type; }))).map(function(type) {
              return { label: type, count: filteredTimeline.filter(function(item) { return item.type === type; }).length };
            }),
            userCounts: Array.from(new Set(filteredTimeline.map(function(item) { return item.userId; }).filter(Boolean))).map(function(userId) {
              return { label: userId, count: filteredTimeline.filter(function(item) { return item.userId === userId; }).length };
            }),
            retentionByMode: Array.from(new Set(filteredRuns.map(function(item) { return item.mode; }).filter(Boolean))).map(function(mode) {
              return { label: mode, count: filteredRuns.filter(function(item) { return item.mode === mode; }).length };
            })
          },
          operationalSignals: operationalSignals
        });
      }
      return Promise.resolve(payload);
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
    if (url === "/api/admin/program-builder/governance/retention/preview" && method === "GET") {
      const nextId = (this.state.retentionRuns[0] && Number(this.state.retentionRuns[0].id || 0) + 1) || 2;
      const executionGroup = "ret-" + String(nextId);
      const previewRun = {
        id: nextId,
        mode: "preview",
        source: "ui",
        executionGroup: executionGroup,
        relatedRunId: null,
        beforeAfterLabel: "Preview da politica atual",
        executedBy: "analista",
        databaseEnvironment: "dev",
        databaseIdentity: "construtor-pg-dev",
        totalRecords: Number(this.state.retentionPreview.totalRecords || 0),
        createdAt: new Date().toISOString(),
        items: clone(this.state.retentionPreview.items || []),
        deltaTotalRecords: Number(this.state.retentionPreview.totalRecords || 0),
        deltaByTable: global.CrudUtils.ensureArray(this.state.retentionPreview.items).map(function(item) {
          return { label: item.label, table: item.table, records: Number(item.records || 0), previousRecords: 0, deltaRecords: Number(item.records || 0) };
        })
      };
      this.state.retentionRuns.unshift(previewRun);
      this.state.retentionRuns = this.state.retentionRuns.slice(0, 20);
      return Promise.resolve(Object.assign({}, clone(this.state.retentionPreview), {
        retentionRunId: previewRun.id,
        executionGroup: executionGroup
      }));
    }
    if (url === "/api/admin/program-builder/governance/retention/history" && method === "GET") {
      return Promise.resolve({ items: clone(this.state.retentionRuns || []) });
    }
    if (url === "/api/admin/program-builder/governance/operations" && method === "GET") {
      return Promise.resolve({
        modeLabel: "snapshot",
        generatedAt: new Date().toISOString(),
        invalidIntegrity: 0,
        alertCount: 0,
        retentionTotalRecords: Number(this.state.retentionPreview.totalRecords || 0),
        cleanupApplied: false,
        cleanupAppliedRecords: 0
      });
    }
    if (url === "/api/admin/program-builder/governance/operations/monitor" && method === "POST") {
      return Promise.resolve({
        modeLabel: "monitor",
        generatedAt: new Date().toISOString(),
        invalidIntegrity: 0,
        alertCount: 0,
        retentionTotalRecords: Number(this.state.retentionPreview.totalRecords || 0),
        cleanupApplied: false,
        cleanupAppliedRecords: 0
      });
    }
    if (url === "/api/admin/program-builder/governance/operations" && method === "POST") {
      return Promise.resolve({
        modeLabel: "operacao-unificada",
        generatedAt: new Date().toISOString(),
        invalidIntegrity: 0,
        alertCount: 0,
        retentionTotalRecords: Number(this.state.retentionPreview.totalRecords || 0),
        cleanupApplied: false,
        cleanupAppliedRecords: 0
      });
    }
    if (url === "/api/admin/program-builder/governance/retention/cleanup" && method === "POST") {
      const relatedPreviewRunId = Number(data.previewRunId || 0) || (this.state.retentionRuns.find(function(item) { return item.mode === "preview"; }) || {}).id || null;
      const relatedPreviewRun = this.state.retentionRuns.find(function(item) { return Number(item.id || 0) === Number(relatedPreviewRunId || 0); }) || null;
      this.state.retentionPreview = Object.assign({}, clone(this.state.retentionPreview), {
        mode: "apply",
        totalRecords: 0,
        items: global.CrudUtils.ensureArray(this.state.retentionPreview.items).map(function(item) {
          return Object.assign({}, item, { records: 0 });
        })
      });
      const applyRun = {
        id: (this.state.retentionRuns[0] && Number(this.state.retentionRuns[0].id || 0) + 1) || 2,
        mode: "apply",
        source: "ui",
        executionGroup: relatedPreviewRun && relatedPreviewRun.executionGroup || ("ret-" + String(Date.now())),
        relatedRunId: relatedPreviewRunId,
        beforeAfterLabel: relatedPreviewRunId ? ("Aplicacao relacionada ao preview #" + String(relatedPreviewRunId)) : "Execucao aplicada",
        executedBy: "analista",
        databaseEnvironment: "dev",
        databaseIdentity: "construtor-pg-dev",
        totalRecords: 0,
        createdAt: new Date().toISOString(),
        items: clone(this.state.retentionPreview.items || []),
        deltaTotalRecords: relatedPreviewRun ? -Number(relatedPreviewRun.totalRecords || 0) : 0,
        deltaByTable: global.CrudUtils.ensureArray(this.state.retentionPreview.items).map(function(item) {
          const previous = relatedPreviewRun && Array.isArray(relatedPreviewRun.items)
            ? relatedPreviewRun.items.find(function(previousItem) { return previousItem.table === item.table; })
            : null;
          return {
            label: item.label,
            table: item.table,
            records: 0,
            previousRecords: Number(previous && previous.records || 0),
            deltaRecords: -Number(previous && previous.records || 0)
          };
        })
      };
      this.state.retentionRuns.unshift(applyRun);
      this.state.retentionRuns = this.state.retentionRuns.slice(0, 20);
      return Promise.resolve(Object.assign({}, clone(this.state.retentionPreview), {
        retentionRunId: applyRun.id,
        executionGroup: applyRun.executionGroup,
        relatedPreviewRunId: relatedPreviewRunId
      }));
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
      return Promise.resolve(this.decorateOverlayPreview(this.state.overlayPreview));
    }
    if (url === "/api/admin/program-builder/overlays/700/versions" && method === "GET") {
      return Promise.resolve({ items: clone(this.state.overlayVersions) });
    }
    if (url.indexOf("/api/admin/program-builder/overlay-versions/compare") === 0 && method === "GET") {
      return Promise.resolve({
        leftVersion: {
          id: Number(data.leftVersionId || 701),
          versionNumber: 2,
          status: "draft",
          changeSummary: "Revisao do formulario e do titulo."
        },
        rightVersion: {
          id: Number(data.rightVersionId || 699),
          versionNumber: 1,
          status: "published",
          changeSummary: "Primeira versao publicada do overlay."
        },
        changedSections: 2,
        changedPaths: 3,
        sections: [
          { key: "program", pathCount: 1, paths: ["program.title"] },
          { key: "crud", pathCount: 2, paths: ["crud.form.tabs.0.title", "crud.grid.columns.1.title"] }
        ]
      });
    }
    if (url === "/api/admin/program-builder/overlay-versions/701/rebase" && method === "POST") {
      const previewSections = global.CrudUtils.ensureArray(this.state.overlayPreview && this.state.overlayPreview.sections);
      const violations = previewSections.some(function(section) {
        return global.CrudUtils.ensureArray(section.entries).some(function(entry) {
          return String(entry.classification || "") === "conflict_warning"
            && String(data.resolutions && data.resolutions[entry.path] || "").toLowerCase() === "overlay";
        });
      });
      if (violations) {
        return Promise.reject({ error: { code: "PROGRAM_OVERLAY_REBASE_POLICY_BLOCKED", message: "O plano atual viola a politica de rebase para conflitos leves." } });
      }
      if (this.state.overlayPreview && this.state.overlayPreview.requiresConfirmation && !data.resolutions.__confirmWarning__) {
        return Promise.reject({ error: { code: "PROGRAM_OVERLAY_REBASE_CONFIRMATION_REQUIRED", message: "O rebase possui conflitos leves e exige confirmacao explicita." } });
      }
      return Promise.resolve({
        status: "warning",
        overlayId: 700,
        newOverlayVersionId: 702,
        preview: this.decorateOverlayPreview(this.state.overlayPreview)
      });
    }
    if (url === "/api/admin/program-builder/overlay-versions/701/publish" && method === "POST") {
      this.state.overlayVersions = this.state.overlayVersions.map(function(item) {
        return Object.assign({}, item, {
          status: item.id === 701 ? "published" : (item.status === "published" ? "archived" : item.status),
          publishedAt: item.id === 701 ? "2026-05-16T11:00:00-03:00" : item.publishedAt
        });
      });
      return Promise.resolve({
        overlayId: 700,
        overlayVersionId: 701,
        status: "published",
        publishedAt: "2026-05-16T11:00:00-03:00"
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
