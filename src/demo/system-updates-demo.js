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
                { overlayId: 11, status: "rebase_warning", message: "A base e o overlay alteraram a mesma secao do contrato." },
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
        summary: { message: "Base inicial aplicada." },
        impactReport: {},
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
        execution.summary = { message: "Atualizacao aplicada com sucesso." };
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
