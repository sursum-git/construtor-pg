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

  SubscriberProvisioningDemoHttpClient.prototype.request = function(options) {
    const request = options || {};
    const method = String(request.method || "GET").toUpperCase();
    const url = String(request.url || "");
    const data = request.data || {};

    if (method === "GET" && url === "/api/admin/subscriber-provisioning/bootstrap") {
      return Promise.resolve({ environment: { databaseEnvironment: "dev", databaseIdentity: "db:demo" }, subscribers: this.subscribers.slice(), jobs: this.jobs.slice().reverse() });
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
    if (method === "POST" && url === "/api/admin/subscriber-provisioning/provision") {
      const now = new Date().toISOString();
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
        result: { phase: "queued", message: "Provisionamento enfileirado." },
        createdAt: now,
        updatedAt: now,
        startedAt: null,
        finishedAt: null
      };
      this.jobs.push(job);
      global.setTimeout(() => {
        job.status = "running";
        job.startedAt = new Date().toISOString();
        job.updatedAt = new Date().toISOString();
        job.result = { phase: "running", message: "Provisionamento em execucao.", outputTail: "Executando bootstrap do assinante " + job.subscriberCode };
      }, 400);
      global.setTimeout(() => {
        job.status = "succeeded";
        job.finishedAt = new Date().toISOString();
        job.updatedAt = new Date().toISOString();
        job.result = { phase: "completed", message: "Provisionamento concluido.", outputTail: "Ambiente preparado com sucesso.", script: "demo", subscriberCode: job.subscriberCode };
      }, 1800);
      return Promise.resolve({ queued: [{ id: job.id, type: job.jobType, status: job.status }], job: Object.assign({}, job) });
    }
    if (method === "GET" && /^\/api\/admin\/subscriber-provisioning\/jobs\/\d+$/i.test(url)) {
      const jobId = Number(url.split("/").pop());
      const job = this.jobs.find((item) => item.id === jobId);
      return Promise.resolve(job ? Object.assign({}, job) : {});
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

  global.SubscriberProvisioningDemoHttpClient = SubscriberProvisioningDemoHttpClient;
})(window);
