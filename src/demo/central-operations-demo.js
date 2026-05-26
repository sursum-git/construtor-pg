(function(global) {
  "use strict";

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  const dashboard = {
    summary: {
      licenseCount: 2,
      activeLicenses: 1,
      inactiveLicenses: 1,
      serviceTokenCount: 1,
      activeServiceTokens: 1,
      updateFailureCount: 1,
      missingArtifactCount: 1,
      missingKeyCount: 1,
      alertCount: 3
    },
    subscribers: [
      {
        subscriberCode: "cliente-x",
        subscriberName: "Cliente X",
        licenseStatus: "active",
        currentVersion: "1.4.0",
        lastUpdateStatus: "succeeded",
        lastUpdateAt: "2026-05-26T09:10:00-03:00",
        lastActivationAt: "2026-05-25T15:10:00-03:00",
        activationCount: 2,
        maxActivations: 5,
        fingerprintCount: 2,
        revokedFingerprintCount: 0,
        alerts: []
      },
      {
        subscriberCode: "cliente-y",
        subscriberName: "Cliente Y",
        licenseStatus: "suspended",
        currentVersion: "1.3.0",
        lastUpdateStatus: "failed",
        lastUpdateAt: "2026-05-25T18:40:00-03:00",
        lastActivationAt: "2026-05-20T11:00:00-03:00",
        activationCount: 1,
        maxActivations: 1,
        fingerprintCount: 1,
        revokedFingerprintCount: 1,
        alerts: ["Licenca suspended", "Ultima atualizacao falhou"]
      }
    ],
    licenses: [
      {
        subscriberCode: "cliente-x",
        subscriberName: "Cliente X",
        activationEmail: "responsavel@cliente-x.com.br",
        status: "active",
        activationCount: 2,
        maxActivations: 5,
        expiresAt: "2026-12-31T23:59:59-03:00",
        lastActivatedAt: "2026-05-25T15:10:00-03:00",
        fingerprintCount: 2,
        revokedFingerprintCount: 0,
        fingerprints: ["host-a", "host-b"],
        revokedFingerprints: [],
        metadata: {
          activationHistory: [
            { sessionId: "sess-demo-1", profile: "subscriber", mode: "docker", issuedAt: "2026-05-25T15:10:00-03:00" }
          ]
        }
      },
      {
        subscriberCode: "cliente-y",
        subscriberName: "Cliente Y",
        activationEmail: "responsavel@cliente-y.com.br",
        status: "suspended",
        activationCount: 1,
        maxActivations: 1,
        expiresAt: "2026-06-03T23:59:59-03:00",
        lastActivatedAt: "2026-05-20T11:00:00-03:00",
        fingerprintCount: 1,
        revokedFingerprintCount: 1,
        fingerprints: ["host-y"],
        revokedFingerprints: ["host-y"],
        metadata: {
          auditTrail: [
            { type: "suspend_license", reason: "Inadimplencia operacional", at: "2026-05-21T10:00:00-03:00" }
          ]
        }
      }
    ],
    serviceTokens: [
      {
        code: "saas-orchestrator",
        name: "Orquestrador SaaS",
        status: "active",
        usageCount: 18,
        lastUsedAt: "2026-05-26T08:30:00-03:00",
        expiresAt: "2026-09-30T23:59:59-03:00",
        metadata: {
          usageHistory: [
            { subscriberCode: "cliente-x", mode: "saas-docker", usedAt: "2026-05-26T08:30:00-03:00" }
          ]
        }
      }
    ],
    artifacts: [
      { name: "Manifesto do instalador", env: "APP_INSTALLER_MANIFEST_URL", status: "configured", valuePreview: "https://downloads.exemplo/installer-manifest.json" },
      { name: "Pacote nativo do instalador", env: "APP_INSTALLER_PACKAGE_URL", status: "missing", valuePreview: "" }
    ],
    keys: [
      { name: "Sessao de ativacao", env: "APP_INSTALLER_ACTIVATION_SIGNING_KEY", status: "ok", length: 48 },
      { name: "Pacote de atualizacao", env: "APP_UPDATE_PACKAGE_SIGNING_KEY", status: "missing", length: 0 }
    ],
    attemptPolicy: {
      maxAttempts: 5,
      blockMinutes: 30,
      requestTtlMinutes: 15,
      sessionTtlMinutes: 120
    },
    alerts: [
      { severity: "warning", title: "Licenca inativa", message: "Assinante cliente-y esta suspenso.", target: "cliente-y" },
      { severity: "error", title: "Atualizacao falhou", message: "Release 1.4.0 falhou para cliente-y.", target: "cliente-y" },
      { severity: "error", title: "Chave ausente", message: "APP_UPDATE_PACKAGE_SIGNING_KEY nao esta configurada.", target: "APP_UPDATE_PACKAGE_SIGNING_KEY" }
    ],
    notifications: [
      { severity: "warning", title: "Revisar licenca", message: "Cliente Y precisa de revisao administrativa.", target: "cliente-y" }
    ],
    audit: [
      { source: "license", target: "cliente-y", type: "auditTrail", at: "2026-05-21T10:00:00-03:00", detail: { type: "suspend_license", reason: "Inadimplencia operacional" } },
      { source: "service_token", target: "saas-orchestrator", type: "usageHistory", at: "2026-05-26T08:30:00-03:00", detail: { subscriberCode: "cliente-x", mode: "saas-docker" } }
    ],
    generatedAt: "2026-05-26T09:30:00-03:00"
  };

  function CentralOperationsDemoHttpClient() {
    this.dashboard = clone(dashboard);
  }

  CentralOperationsDemoHttpClient.prototype.request = function(options) {
    const method = String(options && options.method || "GET").toUpperCase();
    const url = String(options && options.url || "");
    const data = options && options.data || {};
    if (method === "GET" && url === "/api/admin/central-operations/dashboard") {
      return Promise.resolve(clone(this.dashboard));
    }
    if (method === "POST" && url === "/api/admin/central-operations/license-action") {
      this.applyLicenseAction(data);
      return Promise.resolve({ ok: true, dashboard: clone(this.dashboard) });
    }
    if (method === "POST" && url === "/api/admin/central-operations/token-action") {
      this.applyTokenAction(data);
      return Promise.resolve({ ok: true, dashboard: clone(this.dashboard) });
    }

    return Promise.reject({ error: { message: "Endpoint demo nao implementado: " + method + " " + url } });
  };

  CentralOperationsDemoHttpClient.prototype.applyLicenseAction = function(data) {
    const license = this.dashboard.licenses.find(function(item) {
      return item.subscriberCode === data.subscriberCode;
    });
    if (!license) {
      return;
    }
    if (data.action === "suspend_license") {
      license.status = "suspended";
    } else if (data.action === "activate_license") {
      license.status = "active";
    } else if (data.action === "revoke_license") {
      license.status = "revoked";
    } else if (data.action === "revoke_fingerprint" && data.fingerprint) {
      license.revokedFingerprints = license.revokedFingerprints || [];
      if (license.revokedFingerprints.indexOf(data.fingerprint) < 0) {
        license.revokedFingerprints.push(data.fingerprint);
      }
      license.revokedFingerprintCount = license.revokedFingerprints.length;
    }
    this.dashboard.audit.unshift({
      source: "license",
      target: license.subscriberCode,
      type: data.action,
      at: new Date().toISOString(),
      detail: data
    });
    this.syncSummary();
  };

  CentralOperationsDemoHttpClient.prototype.applyTokenAction = function(data) {
    const token = this.dashboard.serviceTokens.find(function(item) {
      return item.code === data.code;
    });
    if (!token) {
      return;
    }
    if (data.action === "suspend_token") {
      token.status = "suspended";
    } else if (data.action === "activate_token") {
      token.status = "active";
    } else if (data.action === "revoke_token") {
      token.status = "revoked";
    }
    this.dashboard.audit.unshift({
      source: "service_token",
      target: token.code,
      type: data.action,
      at: new Date().toISOString(),
      detail: data
    });
    this.syncSummary();
  };

  CentralOperationsDemoHttpClient.prototype.syncSummary = function() {
    this.dashboard.summary.activeLicenses = this.dashboard.licenses.filter(function(item) {
      return item.status === "active";
    }).length;
    this.dashboard.summary.inactiveLicenses = this.dashboard.licenses.length - this.dashboard.summary.activeLicenses;
    this.dashboard.summary.activeServiceTokens = this.dashboard.serviceTokens.filter(function(item) {
      return item.status === "active";
    }).length;
  };

  global.CentralOperationsDemoHttpClient = CentralOperationsDemoHttpClient;
})(window);
