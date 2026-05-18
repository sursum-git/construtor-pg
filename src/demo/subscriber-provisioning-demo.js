(function(global) {
  "use strict";

  function SubscriberProvisioningDemoHttpClient() {
    this.subscribers = [
      {
        id: 1,
        code: "default",
        name: "Principal",
        document: "",
        principal: true,
        enabled: true,
        deploymentMode: "dedicated_stack",
        runtimeEnvironmentCode: "default",
        primaryEnvironmentCode: "default-principal",
        sharedRuntimeEnvironment: false,
        principalEnvironmentIsolated: true,
        updateChannel: "stable",
        instanceCode: "construtor-pg-default",
        databaseEnvironment: "prod",
        databaseIdentity: "saas:default",
        databaseName: "construtor_pg_default",
        adminUsername: "admin",
        adminDisplayName: "Administrador Principal",
        adminEmail: "admin@example.com",
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString()
      }
    ];
    this.jobs = [];
    this.nextJobId = 100;
  }

  SubscriberProvisioningDemoHttpClient.STEPS = [
    { code: "prepare_env", label: "Preparar ambiente e variaveis" },
    { code: "start_database", label: "Subir banco dedicado" },
    { code: "bootstrap_app", label: "Bootstrap da aplicacao" },
    { code: "create_subscriber", label: "Criar assinante e admin inicial" },
    { code: "publish_defaults", label: "Publicar programas padrao" }
  ];

  SubscriberProvisioningDemoHttpClient.prototype.request = function(options) {
    const request = options || {};
    const method = String(request.method || "GET").toUpperCase();
    const url = String(request.url || "");
    const data = request.data || {};

    if (method === "GET" && url === "/api/admin/subscriber-provisioning/bootstrap") {
      return Promise.resolve({
        centralControl: { centralControl: true },
        environment: { databaseEnvironment: "dev", databaseIdentity: "db:demo" },
        subscribers: this.subscribers.slice(),
        jobs: this.jobs.slice().reverse(),
        runtimeEnvironments: this.buildRuntimeEnvironments(),
        operationalMatrix: this.buildOperationalMatrix(),
        isolationCatalog: this.buildIsolationCatalog()
      });
    }
    if (method === "POST" && url === "/api/admin/subscriber-provisioning/subscribers") {
      const payload = Object.assign({}, data);
      const existing = this.subscribers.find((item) => item.code === payload.code);
      const item = existing || { id: this.subscribers.length + 1, createdAt: new Date().toISOString() };
      Object.assign(item, {
        code: payload.code,
        name: payload.name,
        document: payload.document || "",
        principal: payload.principal === true,
        enabled: payload.enabled !== false,
        deploymentMode: payload.deploymentMode || "dedicated_stack",
        runtimeEnvironmentCode: payload.runtimeEnvironmentCode || payload.code || "",
        primaryEnvironmentCode: payload.primaryEnvironmentCode || ((payload.code || "") + "-principal"),
        sharedRuntimeEnvironment: payload.deploymentMode === "shared_program_shared_db",
        principalEnvironmentIsolated: true,
        updateChannel: payload.updateChannel || "stable",
        instanceCode: payload.instanceCode || "",
        databaseEnvironment: payload.databaseEnvironment || "",
        databaseIdentity: payload.databaseIdentity || "",
        databaseName: payload.databaseName || "",
        adminUsername: payload.adminUsername || "",
        adminDisplayName: payload.adminDisplayName || "",
        adminEmail: payload.adminEmail || "",
        updatedAt: new Date().toISOString()
      });
      if (!existing) {
        this.subscribers.push(item);
      }
      if (item.principal) {
        this.subscribers.forEach((subscriber) => {
          if (subscriber.code !== item.code) {
            subscriber.principal = false;
          }
        });
      }
      return Promise.resolve({ subscriber: Object.assign({}, item) });
    }
    if (method === "GET" && url === "/api/admin/subscriber-provisioning/jobs") {
      const subscriberCode = data.subscriberCode || "";
      const items = this.jobs.filter((item) => !subscriberCode || item.subscriberCode === subscriberCode).slice().reverse();
      return Promise.resolve({ items: items });
    }
    if (method === "POST" && url === "/api/admin/subscriber-provisioning/precheck") {
      return Promise.resolve(this.buildPrecheck(data));
    }
    if (method === "POST" && url === "/api/admin/subscriber-provisioning/provision") {
      const precheck = this.buildPrecheck(data);
      if (precheck.hasBlockingIssues) {
        return Promise.reject({ error: { message: "Existem conflitos bloqueantes no provisionamento." } });
      }
      const now = new Date().toISOString();
      const steps = SubscriberProvisioningDemoHttpClient.STEPS.map((step) => ({
        code: step.code,
        label: step.label,
        status: "pending"
      }));
      const job = {
        id: this.nextJobId++,
        jobType: "subscriber.environment.provision",
        status: "queued",
        attempts: 0,
        lastError: null,
        screenId: "admin.assinante-ambientes",
        programId: "admin-assinante-ambientes",
        subscriberCode: data.subscriberCode || data.code || "",
        subscriberName: data.name || "",
        databaseIdentity: data.databaseIdentity || "saas:" + (data.subscriberCode || data.code || ""),
        databaseEnvironment: data.databaseEnvironment || "prod",
        databaseName: data.databaseName || "",
        instanceCode: data.instanceCode || "",
        deploymentMode: data.deploymentMode || "dedicated_stack",
        runtimeEnvironmentCode: data.runtimeEnvironmentCode || data.subscriberCode || data.code || "",
        primaryEnvironmentCode: data.primaryEnvironmentCode || ((data.subscriberCode || data.code || "") + "-principal"),
        updateChannel: data.updateChannel || "stable",
        result: {
          phase: "queued",
          message: "Provisionamento enfileirado.",
          steps: steps,
          report: {
            subscriberCode: data.subscriberCode || data.code || "",
            databaseIdentity: data.databaseIdentity || "saas:" + (data.subscriberCode || data.code || ""),
            databaseName: data.databaseName || "",
            runtimeEnvironmentCode: data.runtimeEnvironmentCode || data.subscriberCode || data.code || ""
          }
        },
        createdAt: now,
        updatedAt: now,
        startedAt: null,
        finishedAt: null
      };
      this.jobs.push(job);
      SubscriberProvisioningDemoHttpClient.STEPS.forEach((step, index) => {
        global.setTimeout(() => {
          job.status = "running";
          job.startedAt = job.startedAt || new Date().toISOString();
          job.updatedAt = new Date().toISOString();
          job.result.phase = "running";
          job.result.currentStep = step.code;
          job.result.message = "Executando etapa: " + step.code + ".";
          job.result.outputTail = "Executando " + step.code + " para " + job.subscriberCode;
          job.result.steps = job.result.steps.map((item) => ({
            code: item.code,
            label: item.label,
            status: item.code === step.code ? "running" : (item.status === "succeeded" ? "succeeded" : "pending")
          }));
        }, 350 + (index * 260));
        global.setTimeout(() => {
          job.updatedAt = new Date().toISOString();
          job.result.steps = job.result.steps.map((item) => ({
            code: item.code,
            label: item.label,
            status: item.code === step.code ? "succeeded" : item.status
          }));
        }, 500 + (index * 260));
      });
      global.setTimeout(() => {
        job.status = "succeeded";
        job.finishedAt = new Date().toISOString();
        job.updatedAt = new Date().toISOString();
        job.result.phase = "completed";
        job.result.message = "Provisionamento concluido.";
        job.result.outputTail = "Ambiente preparado com sucesso.";
        job.result.script = "demo";
        job.result.report.completedSteps = SubscriberProvisioningDemoHttpClient.STEPS.length;
      }, 1900);
      return Promise.resolve({ queued: [{ id: job.id, type: job.jobType, status: job.status }], job: Object.assign({}, job) });
    }
    if (method === "GET" && /^\/api\/admin\/subscriber-provisioning\/jobs\/\d+$/i.test(url)) {
      const jobId = Number(url.split("/").pop());
      const job = this.jobs.find((item) => item.id === jobId);
      return Promise.resolve(job ? Object.assign({}, job) : {});
    }
    if (method === "POST" && /^\/api\/admin\/subscriber-provisioning\/jobs\/\d+\/retry$/i.test(url)) {
      const originalJobId = Number(url.split("/")[5]);
      const original = this.jobs.find((item) => item.id === originalJobId);
      if (!original) {
        return Promise.reject({ error: { message: "Job nao encontrado para retry." } });
      }
      const retryFromStep = String(data.retryFromStep || "prepare_env");
      return this.request({
        method: "POST",
        url: "/api/admin/subscriber-provisioning/provision",
        data: Object.assign({}, original, {
          code: original.subscriberCode,
          subscriberCode: original.subscriberCode,
          name: original.subscriberName,
          retryFromStep: retryFromStep
        })
      });
    }
    if (method === "GET" && url === "/api/admin/subscriber-provisioning/onprem-package") {
      const code = String(data.subscriberCode || data.code || "cliente");
      return Promise.resolve({
        fileName: "construtor-pg-onprem-" + code + ".zip",
        size: 1024,
        sha256: "demo-" + code + "-sha256",
        signature: "demo-signature-" + code,
        generatedAt: new Date().toISOString(),
        precheck: this.buildPrecheck(data)
      });
    }

    return Promise.reject({ error: { message: "Endpoint demo nao suportado: " + method + " " + url } });
  };

  SubscriberProvisioningDemoHttpClient.prototype.downloadOnPremPackage = function(payload) {
    const code = String(payload && payload.subscriberCode || payload && payload.code || "cliente");
    const content = "Pacote demo on-premise para " + code + "\n";
    const blob = new Blob([content], { type: "application/zip" });
    const url = global.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "construtor-pg-onprem-" + code + ".zip";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    global.URL.revokeObjectURL(url);
  };

  SubscriberProvisioningDemoHttpClient.prototype.buildPrecheck = function(payload) {
    const password = String(payload.adminPassword || "");
    const blockingIssues = [];
    const checklist = [
      { code: "central_control", label: "Sistema central SaaS habilitado", status: "ok", message: "Demo local sempre roda como central." },
      { code: "worker", label: "Worker de jobs disponivel", status: "manual", message: "No sistema real, manter worker ativo para subscriber.environment.provision." },
      { code: "runtime_environment", label: "Ambiente runtime definido", status: payload.runtimeEnvironmentCode ? "ok" : "error", message: payload.runtimeEnvironmentCode || "Informe o ambiente runtime." },
      { code: "primary_environment", label: "Ambiente principal isolado definido", status: payload.primaryEnvironmentCode ? "ok" : "error", message: payload.primaryEnvironmentCode || "Informe o ambiente principal isolado." }
    ];
    if (!password || password.length < 14) {
      blockingIssues.push({ code: "weak_admin_password", message: "A senha inicial precisa ter pelo menos 14 caracteres e politica forte." });
      checklist.push({ code: "admin_password", label: "Credencial inicial forte", status: "error", message: "Defina senha forte antes de provisionar." });
    } else {
      checklist.push({ code: "admin_password", label: "Credencial inicial forte", status: "ok", message: "Senha forte preenchida." });
    }
    return {
      hasBlockingIssues: blockingIssues.length > 0,
      blockingIssues: blockingIssues,
      warnings: [],
      checklist: checklist,
      steps: SubscriberProvisioningDemoHttpClient.STEPS
    };
  };

  SubscriberProvisioningDemoHttpClient.prototype.buildRuntimeEnvironments = function() {
    const grouped = {};
    this.subscribers.forEach((item) => {
      const code = String(item.runtimeEnvironmentCode || "");
      if (!code) {
        return;
      }
      grouped[code] = grouped[code] || [];
      grouped[code].push(item);
    });
    return Object.keys(grouped).sort().map((runtimeCode) => {
      const items = grouped[runtimeCode];
      const versions = Array.from(new Set(items.map((item) => String(item.latestSuccessfulVersion || "")).filter(Boolean)));
      const divergences = [];
      if (items.length > 1 && new Set(items.map((item) => String(item.databaseIdentity || ""))).size > 1) {
        divergences.push("Identidade de banco divergente entre assinantes do mesmo runtime.");
      }
      return {
        runtimeEnvironmentCode: runtimeCode,
        sharedRuntime: items.length > 1 || items.some((item) => item.sharedRuntimeEnvironment === true),
        subscriberCount: items.length,
        deploymentModes: Array.from(new Set(items.map((item) => String(item.deploymentMode || "")))).filter(Boolean),
        databaseEnvironments: Array.from(new Set(items.map((item) => String(item.databaseEnvironment || "")))).filter(Boolean),
        databaseIdentities: Array.from(new Set(items.map((item) => String(item.databaseIdentity || "")))).filter(Boolean),
        latestSuccessfulVersions: versions,
        activeProgramCount: 12,
        divergences: divergences,
        subscribers: items.map((item) => ({
          code: item.code,
          name: item.name,
          deploymentMode: item.deploymentMode,
          updateChannel: item.updateChannel || "stable",
          latestSuccessfulVersion: item.latestSuccessfulVersion || ""
        }))
      };
    });
  };

  SubscriberProvisioningDemoHttpClient.prototype.buildOperationalMatrix = function() {
    const countByRuntime = {};
    this.buildRuntimeEnvironments().forEach((item) => {
      countByRuntime[item.runtimeEnvironmentCode] = item.subscriberCount;
    });
    return this.subscribers.map((item) => ({
      code: item.code,
      name: item.name,
      deploymentMode: item.deploymentMode,
      deploymentModeLabel: item.deploymentMode,
      primaryEnvironmentCode: item.primaryEnvironmentCode,
      runtimeEnvironmentCode: item.runtimeEnvironmentCode,
      sharedRuntimeSubscriberCount: countByRuntime[item.runtimeEnvironmentCode] || 0,
      updateChannel: item.updateChannel || "stable",
      databaseEnvironment: item.databaseEnvironment || "",
      databaseIdentity: item.databaseIdentity || "",
      latestSuccessfulVersion: item.latestSuccessfulVersion || "",
      versionStatus: item.latestSuccessfulVersion ? "atual" : "sem-historico"
    }));
  };

  SubscriberProvisioningDemoHttpClient.prototype.buildIsolationCatalog = function() {
    return {
      summary: {
        globalTables: 1,
        subscriberTables: 1,
        riskTables: 0
      },
      items: [
        {
          entityCode: "estado",
          name: "Estados",
          tableName: "estado",
          scopeLabel: "Global",
          subscriberIsolationMode: "none",
          subscriberColumnName: null,
          globalTable: true,
          riskStatus: "ok",
          riskMessage: ""
        },
        {
          entityCode: "cliente",
          name: "Clientes",
          tableName: "cliente",
          scopeLabel: "Filtrada por assinante",
          subscriberIsolationMode: "subscriber_column",
          subscriberColumnName: "subscriber_id",
          globalTable: false,
          riskStatus: "ok",
          riskMessage: ""
        }
      ]
    };
  };

  global.SubscriberProvisioningDemoHttpClient = SubscriberProvisioningDemoHttpClient;
})(window);
