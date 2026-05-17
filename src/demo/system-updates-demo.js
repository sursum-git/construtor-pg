(function(global) {
  "use strict";

  function SystemUpdatesDemoHttpClient() {
    this.consents = [];
    this.subscribers = [
      { code: "empresa-a", name: "Empresa A", databaseEnvironment: "prod", databaseIdentity: "saas:empresa-a" },
      { code: "empresa-b", name: "Empresa B", databaseEnvironment: "prod", databaseIdentity: "saas:empresa-b" }
    ];
    this.releases = [
      {
        version: "1.0.1",
        title: "Correcao critica de seguranca do runtime",
        category: "security_critical",
        severity: "critical",
        requiresConsent: false,
        autoApplicable: true,
        packageAvailable: true,
        packageUrl: "backend/config/system-updates/packages/runtime-security-1.0.1.pkg",
        impactReport: {
          programs: [
            {
              programCode: "admin-integracoes",
              overlayImpacts: [
                { overlayId: 11, status: "rebase_ok", message: "A nova base pode gerar rascunho de rebase automaticamente." },
                { overlayId: 14, status: "rebase_warning", message: "A base e o overlay alteraram a mesma secao do contrato." },
                { overlayId: 12, status: "custom_frozen", message: "Variante completa permanece congelada." }
              ]
            }
          ]
        }
      },
      {
        version: "1.0.2",
        title: "Ajuste estrutural obrigatorio",
        category: "required_structural",
        severity: "high",
        requiresConsent: true,
        autoApplicable: false,
        packageAvailable: true,
        packageUrl: "backend/config/system-updates/packages/runtime-structure-1.0.2.pkg",
        dependencyIssues: ["Atualizacao obrigatoria pendente: 1.0.1."],
        metadata: {
          requiresBackup: true,
          requiresMaintenanceMode: true,
          orchestratorAction: "maintenance-rollout"
        }
      }
    ];
    this.executions = [
      {
        id: 1,
        releaseVersion: "1.0.0",
        releaseTitle: "Base inicial",
        category: "recommended",
        severity: "low",
        status: "succeeded",
        mode: "bootstrap",
        deploymentMode: "saas",
        databaseEnvironment: "dev",
        databaseIdentity: "saas:demo",
        targetSubscriberCode: "empresa-a",
        targetSubscriberName: "Empresa A",
        targetDatabaseEnvironment: "prod",
        targetDatabaseIdentity: "saas:empresa-a",
        initiatedBy: "admin",
        initiatedSource: "seed",
        runtimeJobId: null,
        summary: {
          message: "Base inicial aplicada.",
          overlayPipeline: {
            draftCreated: 1,
            draftExists: 0,
            reviewRequired: 1,
            blocked: 0,
            frozen: 1,
            missingVersion: 0,
            pipelineFailed: 0
          }
        },
        impactReport: {
          overlayPipelineSummary: {
            draftCreated: 1,
            draftExists: 0,
            reviewRequired: 1,
            blocked: 0,
            frozen: 1,
            missingVersion: 0,
            pipelineFailed: 0
          },
          programs: [
            {
              programCode: "admin-integracoes",
              overlayImpacts: [
                {
                  overlayId: 11,
                  status: "rebase_ok",
                  message: "Rascunho de rebase gerado pela esteira.",
                  pipelineStatus: "draft_created",
                  pipelineMessage: "Novo draft criado para a base publicada."
                },
                {
                  overlayId: 14,
                  status: "rebase_warning",
                  message: "Conflito leve entre a base e o overlay.",
                  pipelineStatus: "review_required",
                  pipelineMessage: "Release aplicada, mas o overlay exige revisao manual antes de publicar."
                },
                {
                  overlayId: 12,
                  status: "custom_frozen",
                  message: "Variante completa congelada.",
                  pipelineStatus: "frozen",
                  pipelineMessage: "Atualizacao registrada sem sobrescrever a variante do assinante."
                }
              ]
            }
          ]
        },
        createdAt: "2026-05-17T12:00:00.000Z",
        updatedAt: "2026-05-17T12:00:00.000Z",
        finishedAt: "2026-05-17T12:00:00.000Z"
      }
    ];
    this.jobs = [];
    this.nextJobId = 200;
  }

  SystemUpdatesDemoHttpClient.prototype.request = function(options) {
    const request = options || {};
    const method = String(request.method || "GET").toUpperCase();
    const url = String(request.url || "");
    const data = request.data || {};

    if (method === "GET" && url === "/api/admin/system-updates/bootstrap") {
      return Promise.resolve(this.bootstrapPayload(String(data.subscriberCode || "")));
    }
    if (method === "GET" && url === "/api/admin/system-updates/subscriber-log/bootstrap") {
      return Promise.resolve(this.subscriberLogPayload(String(data.subscriberCode || "")));
    }
    if (method === "GET" && url === "/api/admin/system-updates/executions") {
      return Promise.resolve(this.executionHistoryPayload(data || {}));
    }
    if (method === "POST" && url === "/api/admin/system-updates/check") {
      return Promise.resolve(this.bootstrapPayload(String(data.subscriberCode || "")));
    }
    if (method === "POST" && url === "/api/admin/system-updates/consent") {
      const version = String(data.version || "");
      const consent = {
        id: this.consents.length + 1,
        releaseVersion: version,
        status: String(data.status || "approved"),
        approvedBy: "admin",
        source: "ui",
        deploymentMode: "saas",
        databaseIdentity: "saas:demo",
        targetSubscriberCode: String(data.subscriberCode || ""),
        targetSubscriberName: this.findSubscriberName(String(data.subscriberCode || "")),
        reason: String(data.reason || ""),
        createdAt: new Date().toISOString()
      };
      this.consents.unshift(consent);
      return Promise.resolve(consent);
    }
    if (method === "POST" && url === "/api/admin/system-updates/download") {
      const version = String(data.version || "");
      const subscriberCode = String(data.subscriberCode || "");
      return Promise.resolve({
        releaseVersion: version,
        targetSubscriber: this.findSubscriber(subscriberCode) || null,
        package: {
          fileName: "system-update-" + version + ".pkg",
          hash: "demo-hash-" + version,
          signatureStatus: "verified",
          signatureMessage: "Assinatura demo validada.",
          savedPath: "C:/construtor-pg/var/system-updates/" + version + "/system-update-" + version + ".pkg",
          sizeBytes: 2048
        }
      });
    }
    if (method === "POST" && url === "/api/admin/system-updates/publish-artifacts") {
      const version = String(data.version || "");
      return Promise.resolve({
        status: "published",
        distributionDirectory: "C:/construtor-pg/var/system-updates/distribution/" + (version || "catalog"),
        manifestPath: "C:/construtor-pg/var/system-updates/distribution/" + (version || "catalog") + "/manifest.json",
        manifestSignature: "demo-signature-" + (version || "catalog"),
        channel: "stable",
        releaseCount: 1,
        versions: [version || "1.0.1"],
        baseUrl: "https://updates.demo.local/stable",
        packages: [
          {
            version: version || "1.0.1",
            fileName: "system-update-" + (version || "1.0.1") + ".pkg",
            path: "C:/construtor-pg/var/system-updates/distribution/" + (version || "catalog") + "/packages/system-update-" + (version || "1.0.1") + ".pkg",
            hash: "demo-publish-hash-" + (version || "1.0.1"),
            signature: "demo-package-signature-" + (version || "1.0.1"),
            url: "https://updates.demo.local/stable/packages/system-update-" + (version || "1.0.1") + ".pkg"
          }
        ]
      });
    }
    if (method === "GET" && url === "/api/admin/system-updates/rollout-plan") {
      const version = String(data.version || "");
      const release = this.resolveReleases(String(data.subscriberCode || "")).find((item) => item.version === version);
      if (!release) {
        return Promise.reject({ error: { message: "Release demo nao encontrada." } });
      }
      return Promise.resolve({
        version: release.version,
        title: release.title,
        deploymentMode: "saas",
        targetSubscriber: this.findSubscriber(String(data.subscriberCode || "")) || null,
        requiresMaintenanceMode: release.metadata && release.metadata.requiresMaintenanceMode === true,
        requiresBackup: release.metadata && release.metadata.requiresBackup === true,
        orchestratorAction: release.metadata && release.metadata.orchestratorAction || "rolling-restart",
        consentStatus: release.consentStatus || "not-required",
        impactReport: release.impactReport || {},
        suggestedSequence: ["validar impacto", "executar rollout controlado", "validar integridade"]
      });
    }
    if (method === "POST" && url === "/api/admin/system-updates/dispatch-rollout") {
      const version = String(data.version || "");
      const subscriberCode = String(data.subscriberCode || "");
      return Promise.resolve({
        releaseVersion: version,
        targetSubscriber: this.findSubscriber(subscriberCode) || null,
        dispatch: {
          status: "dispatched",
          message: "Rollout despachado para o orquestrador demo.",
          endpoint: "https://orchestrator.demo.local/system-update",
          httpStatus: 202,
          responseHeaders: {
            "x-request-id": "demo-rollout-" + version
          },
          responseBody: {
            queued: true,
            version: version
          }
        },
        payload: {
          event: "system.update.rollout",
          releaseVersion: version,
          targetSubscriber: this.findSubscriber(subscriberCode) || null
        }
      });
    }
    if (method === "POST" && url === "/api/admin/system-updates/apply") {
      const version = String(data.version || "");
      const subscriberCode = String(data.subscriberCode || "");
      const release = this.resolveReleases(subscriberCode).find((item) => item.version === version);
      if (!release) {
        return Promise.reject({ error: { message: "Release demo nao encontrada." } });
      }
      const subscriber = this.findSubscriber(subscriberCode);
      const execution = {
        id: this.executions.length + 1,
        releaseVersion: release.version,
        releaseTitle: release.title,
        category: release.category,
        severity: release.severity,
        status: "queued",
        mode: "manual",
        deploymentMode: "saas",
        databaseEnvironment: "dev",
        databaseIdentity: "saas:demo",
        targetSubscriberCode: subscriberCode || "",
        targetSubscriberName: subscriber ? subscriber.name : "",
        targetDatabaseEnvironment: subscriber ? subscriber.databaseEnvironment : "",
        targetDatabaseIdentity: subscriber ? subscriber.databaseIdentity : "",
        initiatedBy: "admin",
        initiatedSource: "ui",
        runtimeJobId: this.nextJobId,
        summary: { message: "Atualizacao enfileirada." },
        impactReport: release.impactReport || {},
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
        finishedAt: null
      };
      this.executions.unshift(execution);
      const job = {
        id: this.nextJobId++,
        status: "queued",
        jobType: "system.update.apply",
        recordId: release.version,
        createdAt: execution.createdAt,
        updatedAt: execution.updatedAt,
        finishedAt: null,
        result: { phase: "queued", message: "Atualizacao enfileirada.", releaseVersion: release.version },
        lastError: null
      };
      execution.runtimeJobId = job.id;
      this.jobs.unshift(job);
      global.setTimeout(() => {
        job.status = "running";
        job.updatedAt = new Date().toISOString();
        job.result = { phase: "running", message: "Atualizacao em execucao.", releaseVersion: release.version };
        execution.status = "running";
        execution.updatedAt = job.updatedAt;
      }, 400);
      global.setTimeout(() => {
        job.status = "succeeded";
        job.updatedAt = new Date().toISOString();
        job.finishedAt = job.updatedAt;
        job.result = { phase: "completed", message: "Atualizacao aplicada.", releaseVersion: release.version, status: "succeeded" };
        execution.status = "succeeded";
        execution.updatedAt = job.updatedAt;
        execution.finishedAt = job.updatedAt;
        execution.impactReport = this.decorateImpactReportForExecution(release.impactReport || {}, release.version);
        execution.summary = {
          message: "Atualizacao aplicada com sucesso.",
          overlayPipeline: execution.impactReport.overlayPipelineSummary || {}
        };
        const original = this.releases.find((item) => item.version === release.version);
        if (original) {
          original.status = "applied";
        }
      }, 1600);
      return Promise.resolve({ execution: execution, job: job });
    }
    if (method === "GET" && /^\/api\/admin\/system-updates\/jobs\/\d+$/i.test(url)) {
      const jobId = Number(url.split("/").pop());
      const job = this.jobs.find((item) => item.id === jobId);
      return Promise.resolve(job ? Object.assign({}, job) : {});
    }

    return Promise.reject({ error: { message: "Endpoint demo nao suportado: " + method + " " + url } });
  };

  SystemUpdatesDemoHttpClient.prototype.bootstrapPayload = function(subscriberCode) {
    const releases = this.resolveReleases(subscriberCode);
    const selectedSubscriber = this.findSubscriber(subscriberCode);
    return {
      centralControl: {
        centralControl: true,
        systemRole: "saas_central",
        deploymentMode: "saas"
      },
      summary: {
        currentVersion: "1.0.0",
        deploymentMode: "saas",
        databaseEnvironment: "dev",
        databaseIdentity: "saas:demo",
        manifestSignatureStatus: "local-unsigned",
        manifestSignatureMessage: "Manifesto local sem assinatura obrigatoria.",
        pendingCount: releases.filter((item) => item.status === "pending").length,
        criticalPendingCount: releases.filter((item) => item.status === "pending" && item.severity === "critical").length,
        targetSubscriberCode: selectedSubscriber && selectedSubscriber.code || "",
        targetSubscriberName: selectedSubscriber && selectedSubscriber.name || ""
      },
      subscribers: this.subscribers.slice(),
      selectedSubscriber: selectedSubscriber || null,
      releases: releases,
      consents: this.consents.slice(),
      executions: this.filterExecutions(subscriberCode),
      jobs: this.jobs.slice()
    };
  };

  SystemUpdatesDemoHttpClient.prototype.subscriberLogPayload = function(subscriberCode) {
    return {
      centralControl: {
        centralControl: true,
        systemRole: "saas_central",
        deploymentMode: "saas"
      },
      subscribers: this.subscribers.slice(),
      selectedSubscriber: this.findSubscriber(subscriberCode) || null,
      executions: this.filterExecutions(subscriberCode)
    };
  };

  SystemUpdatesDemoHttpClient.prototype.executionHistoryPayload = function(filters) {
    const subscriberCode = String(filters.subscriberCode || "");
    const status = String(filters.status || "");
    const category = String(filters.category || "");
    const dateFrom = String(filters.dateFrom || "");
    const dateTo = String(filters.dateTo || "");
    const rows = this.filterExecutions(subscriberCode).filter(function(item) {
      if (status && String(item.status || "") !== status) {
        return false;
      }
      if (category && String(item.category || "") !== category) {
        return false;
      }
      const createdAt = String(item.createdAt || "");
      if (dateFrom && createdAt && createdAt < dateFrom) {
        return false;
      }
      if (dateTo && createdAt && createdAt > (dateTo + "T99:99:99")) {
        return false;
      }
      return true;
    });
    const byStatus = {};
    const byCategory = {};
    const overlayPipeline = {
      draftCreated: 0,
      draftExists: 0,
      reviewRequired: 0,
      blocked: 0,
      frozen: 0,
      missingVersion: 0,
      pipelineFailed: 0
    };
    rows.forEach(function(item) {
      const itemStatus = String(item.status || "");
      const itemCategory = String(item.category || "");
      if (itemStatus) {
        byStatus[itemStatus] = Number(byStatus[itemStatus] || 0) + 1;
      }
      if (itemCategory) {
        byCategory[itemCategory] = Number(byCategory[itemCategory] || 0) + 1;
      }
      const pipeline = item.summary && item.summary.overlayPipeline || item.impactReport && item.impactReport.overlayPipelineSummary || {};
      overlayPipeline.draftCreated += Number(pipeline.draftCreated || 0);
      overlayPipeline.draftExists += Number(pipeline.draftExists || 0);
      overlayPipeline.reviewRequired += Number(pipeline.reviewRequired || 0);
      overlayPipeline.blocked += Number(pipeline.blocked || 0);
      overlayPipeline.frozen += Number(pipeline.frozen || 0);
      overlayPipeline.missingVersion += Number(pipeline.missingVersion || 0);
      overlayPipeline.pipelineFailed += Number(pipeline.pipelineFailed || 0);
    });
    return {
      items: rows,
      summary: {
        total: rows.length,
        succeeded: rows.filter((item) => item.status === "succeeded").length,
        failed: rows.filter((item) => item.status === "failed").length,
        queued: rows.filter((item) => item.status === "queued" || item.status === "running").length,
        byStatus: byStatus,
        byCategory: byCategory,
        overlayPipeline: overlayPipeline,
        filters: {
          subscriberCode: subscriberCode,
          status: status,
          category: category,
          dateFrom: dateFrom,
          dateTo: dateTo
        }
      }
    };
  };

  SystemUpdatesDemoHttpClient.prototype.decorateImpactReportForExecution = function(impactReport, releaseVersion) {
    const cloned = JSON.parse(JSON.stringify(impactReport || {}));
    const summary = {
      draftCreated: 0,
      draftExists: 0,
      reviewRequired: 0,
      blocked: 0,
      frozen: 0,
      missingVersion: 0,
      pipelineFailed: 0
    };
    (cloned.programs || []).forEach(function(program) {
      (program.overlayImpacts || []).forEach(function(item) {
        if (item.status === "rebase_ok") {
          item.pipelineStatus = "draft_created";
          item.pipelineMessage = "Rascunho de rebase criado para a release " + releaseVersion + ".";
          item.pipelineDraftOverlayVersionId = Number(item.overlayId || 0) + 100;
          summary.draftCreated += 1;
          return;
        }
        if (item.status === "rebase_warning") {
          item.pipelineStatus = "review_required";
          item.pipelineMessage = "Overlay exige revisao manual antes da publicacao.";
          summary.reviewRequired += 1;
          return;
        }
        if (item.status === "rebase_blocked") {
          item.pipelineStatus = "blocked";
          item.pipelineMessage = "Conflito bloqueante impede a continuidade automatica.";
          summary.blocked += 1;
          return;
        }
        if (item.status === "custom_frozen") {
          item.pipelineStatus = "frozen";
          item.pipelineMessage = "Variante especifica permanece congelada na release.";
          summary.frozen += 1;
          return;
        }
        item.pipelineStatus = "ignored";
        item.pipelineMessage = "Sem acao automatica para este overlay.";
      });
    });
    cloned.overlayPipelineSummary = summary;
    return cloned;
  };

  SystemUpdatesDemoHttpClient.prototype.resolveReleases = function(subscriberCode) {
    const applied = new Set(this.executions.filter((item) => item.status === "succeeded").map((item) => item.releaseVersion));
    const latestConsentByVersion = {};
    this.consents.forEach((item) => {
      const consentKey = item.releaseVersion + "|" + String(item.targetSubscriberCode || "");
      if (!latestConsentByVersion[consentKey]) {
        latestConsentByVersion[consentKey] = item;
      }
    });
    return this.releases.map((item) => {
      const release = Object.assign({}, item);
      if (applied.has(release.version)) {
        release.status = "applied";
      } else if (release.version === "1.0.2" && !applied.has("1.0.1")) {
        release.status = "blocked_dependency";
      } else {
        release.status = "pending";
      }
      const consent = latestConsentByVersion[release.version + "|" + String(subscriberCode || "")] || null;
      release.consentStatus = release.requiresConsent ? (consent ? consent.status : "pending") : "not-required";
      release.consentApproved = release.requiresConsent ? Boolean(consent && consent.status === "approved") : true;
      return release;
    });
  };

  SystemUpdatesDemoHttpClient.prototype.filterExecutions = function(subscriberCode) {
    const normalized = String(subscriberCode || "");
    if (!normalized) {
      return this.executions.slice();
    }
    return this.executions.filter((item) => String(item.targetSubscriberCode || "") === normalized);
  };

  SystemUpdatesDemoHttpClient.prototype.findSubscriber = function(subscriberCode) {
    const normalized = String(subscriberCode || "");
    if (!normalized) {
      return null;
    }
    return this.subscribers.find((item) => item.code === normalized) || null;
  };

  SystemUpdatesDemoHttpClient.prototype.findSubscriberName = function(subscriberCode) {
    const subscriber = this.findSubscriber(subscriberCode);
    return subscriber ? subscriber.name : "";
  };

  global.SystemUpdatesDemoHttpClient = SystemUpdatesDemoHttpClient;
})(window);
