(function(global) {
  "use strict";

  function SystemInstallDemoHttpClient() {
    this.lastRun = null;
    this.systemInstalled = false;
    this.installerPassword = "";
  }

  SystemInstallDemoHttpClient.prototype.request = function(options) {
    const request = options || {};
    const method = request.method || "GET";
    const url = String(request.url || "");
    const data = request.data || {};

    if (method === "GET" && url.indexOf("/api/install/status") === 0) {
      return Promise.resolve(this.status(data));
    }
    if (method === "POST" && url === "/api/install/precheck") {
      return Promise.resolve(this.precheck(data));
    }
    if (method === "POST" && url === "/api/install/run") {
      const check = this.precheck(data);
      if (check.hasBlockingIssues) {
        return Promise.reject({
          error: {
            code: "INSTALL_PRECHECK_FAILED",
            message: "Revise os bloqueios antes de executar a instalacao.",
            details: check
          }
        });
      }
      this.lastRun = this.run(data, check);
      return Promise.resolve(this.lastRun);
    }

    return Promise.reject({
      error: {
        code: "DEMO_ROUTE_NOT_FOUND",
        message: "Rota demo nao encontrada."
      }
    });
  };

  SystemInstallDemoHttpClient.prototype.status = function() {
    return {
      systemInstalled: this.systemInstalled,
      activation: {
        required: true,
        valid: true,
        profile: "subscriber",
        profileLabel: "Assinante",
        subscriberCode: "principal",
        mode: "demo",
        sessionId: "demo-session",
        issuedAt: new Date(Date.now() - 60000).toISOString(),
        expiresAt: new Date(Date.now() + 3600000).toISOString(),
        proofHash: "demo",
        message: "Ativacao demo simulada pelo instalador compilado."
      },
      requiresInstallerPassword: true,
      installerPasswordConfigured: Boolean(this.installerPassword),
      canRun: !this.systemInstalled,
      lockReason: "",
      databaseAvailable: true,
      authUserTableExists: false,
      authUserCount: 0,
      environment: {
        databaseEnvironment: "prod",
        databaseIdentity: "install:principal",
        authRequired: true,
        centralControl: false
      },
      steps: this.steps({ publishDefaults: true, runIntegrity: true }),
      message: this.systemInstalled ? "Sistema demo ja instalado." : "Instalador demo disponivel."
    };
  };

  SystemInstallDemoHttpClient.prototype.precheck = function(data) {
    const payload = this.normalize(data);
    const blockingIssues = [];
    const warnings = [];
    const installerPassword = this.evaluateInstallerPassword(payload.installerPassword);
    const password = this.evaluatePassword(payload.adminPassword, payload.subscriberCode, payload.adminUsername);

    if (this.systemInstalled) {
      if (!this.installerPassword || payload.installerPassword !== this.installerPassword) {
        blockingIssues.push({ code: "installer_password", message: "Senha do instalador invalida ou ausente." });
      }
      if (!payload.reinstallConfirmed) {
        blockingIssues.push({ code: "reinstall_confirmation", message: "Confirme explicitamente que deseja reinstalar." });
      }
    } else if (installerPassword.status === "error") {
      blockingIssues.push({ code: "installer_password", message: installerPassword.message });
    }
    if (!payload.subscriberCode) {
      blockingIssues.push({ code: "subscriber_code", message: "Informe o codigo do assinante principal." });
    }
    if (!payload.subscriberName) {
      blockingIssues.push({ code: "subscriber_name", message: "Informe o nome do assinante principal." });
    }
    if (!payload.adminUsername) {
      blockingIssues.push({ code: "admin_username", message: "Informe o usuario administrador inicial." });
    }
    if (password.status === "error") {
      blockingIssues.push({ code: "admin_password", message: password.message });
    }
    if (!payload.databaseUrl) {
      warnings.push({ code: "database_url_empty", message: "Demo vai usar a configuracao atual do backend." });
    }

    return {
      payload: Object.assign({}, payload, {
        adminPassword: payload.adminPassword ? "********" : "",
        installerPassword: payload.installerPassword ? "********" : ""
      }),
      canRun: !blockingIssues.length,
      hasBlockingIssues: Boolean(blockingIssues.length),
      blockingIssues: blockingIssues,
      warnings: warnings,
      checklist: [
        { code: "installed_state", label: "Estado da instalacao", status: this.systemInstalled ? "warning" : "ok", message: this.systemInstalled ? "Sistema demo ja instalado; proxima execucao sera reinstalacao." : "Primeira instalacao demo." },
        { code: "activation", label: "Ativacao pelo instalador compilado", status: "ok", message: "Ativacao demo simulada." },
        { code: "installer_password", label: "Senha do instalador", status: this.systemInstalled ? (payload.installerPassword === this.installerPassword ? "ok" : "error") : installerPassword.status, message: this.systemInstalled ? "Senha exigida para reinstalar." : installerPassword.message },
        { code: "reinstall_confirmation", label: "Confirmacao de reinstalacao", status: !this.systemInstalled || payload.reinstallConfirmed ? "ok" : "error", message: this.systemInstalled ? "Confirme a reinstalacao antes de executar." : "Nao se aplica a primeira instalacao." },
        { code: "database", label: "Banco configurado", status: payload.databaseUrl ? "ok" : "warning", message: payload.databaseUrl ? "DATABASE_URL informado." : "Usando configuracao atual." },
        { code: "subscriber", label: "Assinante principal", status: payload.subscriberCode && payload.subscriberName ? "ok" : "error", message: "Sera criado ou atualizado pelo comando app:subscriber:create." },
        { code: "admin", label: "Administrador inicial", status: password.status, message: password.message },
        { code: "seed", label: "Seed de metadados runtime", status: payload.runSeed ? "ok" : "warning", message: payload.runSeed ? "Sera executado." : "Desmarcado." },
        { code: "publish", label: "Publicacao do catalogo padrao", status: payload.publishDefaults ? "ok" : "warning", message: payload.publishDefaults ? "Sera validada." : "Desmarcada." },
        { code: "integrity", label: "Integridade estrutural", status: payload.runIntegrity ? "ok" : "warning", message: payload.runIntegrity ? "Sera validada." : "Desmarcada." }
      ],
      steps: this.steps(payload)
    };
  };

  SystemInstallDemoHttpClient.prototype.run = function(data, precheck) {
    const payload = this.normalize(data);
    if (!this.systemInstalled) {
      this.installerPassword = payload.installerPassword;
    }
    this.systemInstalled = true;
    const steps = this.steps(payload).map(function(step, index) {
      return Object.assign({}, step, {
        status: "succeeded",
        durationSeconds: index + 1,
        message: "Etapa demo concluida.",
        outputTail: "Demo: " + step.label + " concluida."
      });
    });
    const output = [
      "Demo de instalacao concluida.",
      "Comandos reais equivalentes:",
      "php backend/bin/console app:install:bootstrap --create-database --database-environment=" + payload.databaseEnvironment + " --database-identity=" + payload.databaseIdentity,
      "php backend/bin/console app:subscriber:create --code=" + payload.subscriberCode + " --name=\"" + payload.subscriberName + "\" --principal --admin-username=" + payload.adminUsername + " --admin-password-env=CONSTRUTOR_PG_ADMIN_PASSWORD",
      "php backend/bin/console app:runtime:publish-defaults --refresh --fail-on-missing",
      "backend/.env.local receberia APP_SYSTEM_INSTALLED=1 e APP_INSTALLER_PASSWORD_HASH=<hash>"
    ].join("\n");

    return {
      success: true,
      status: "succeeded",
      startedAt: new Date().toISOString(),
      finishedAt: new Date().toISOString(),
      steps: steps,
      outputTail: output,
      precheck: precheck
    };
  };

  SystemInstallDemoHttpClient.prototype.steps = function(payload) {
    const steps = [
      { code: "bootstrap", label: "Criar banco, aplicar migrations e seed", status: "pending" },
      { code: "subscriber", label: "Criar assinante principal e administrador", status: "pending" }
    ];
    if (!payload || payload.publishDefaults !== false) {
      steps.push({ code: "publish_defaults", label: "Publicar e validar catalogo padrao", status: "pending" });
    }
    if (!payload || payload.runIntegrity !== false) {
      steps.push({ code: "integrity", label: "Validar integridade estrutural", status: "pending" });
    }
    return steps;
  };

  SystemInstallDemoHttpClient.prototype.normalize = function(data) {
    return {
      installerPassword: String(data.installerPassword || data.installToken || ""),
      reinstallConfirmed: data.reinstallConfirmed === true,
      databaseUrl: String(data.databaseUrl || ""),
      saveEnv: data.saveEnv !== false,
      createDatabase: data.createDatabase !== false,
      databaseEnvironment: String(data.databaseEnvironment || "prod"),
      databaseIdentity: String(data.databaseIdentity || "install:principal"),
      systemRole: String(data.systemRole || "onprem"),
      centralControl: data.centralControl === true,
      authRequired: data.authRequired !== false,
      mailerDsn: String(data.mailerDsn || "null://null"),
      subscriberCode: String(data.subscriberCode || "principal").trim(),
      subscriberName: String(data.subscriberName || "Principal").trim(),
      subscriberDocument: String(data.subscriberDocument || ""),
      principal: data.principal !== false,
      userTenantId: String(data.userTenantId || "default"),
      adminUsername: String(data.adminUsername || "admin").trim(),
      adminPassword: String(data.adminPassword || ""),
      adminDisplayName: String(data.adminDisplayName || "Administrador"),
      adminEmail: String(data.adminEmail || ""),
      forcePasswordChange: data.forcePasswordChange !== false,
      runSeed: data.runSeed !== false,
      publishDefaults: data.publishDefaults !== false,
      runIntegrity: data.runIntegrity !== false
    };
  };

  SystemInstallDemoHttpClient.prototype.evaluateInstallerPassword = function(password) {
    const checks = [
      /[a-z]/.test(password),
      /[A-Z]/.test(password),
      /\d/.test(password),
      /[^a-zA-Z0-9]/.test(password),
      password.length >= 14
    ];
    if (checks.indexOf(false) !== -1) {
      return {
        status: "error",
        message: "Defina uma senha do instalador com pelo menos 14 caracteres, maiuscula, minuscula, numero e simbolo."
      };
    }
    return {
      status: "ok",
      message: "Senha do instalador definida para proteger reinstalacoes futuras."
    };
  };

  SystemInstallDemoHttpClient.prototype.evaluatePassword = function(password, subscriberCode, adminUsername) {
    const checks = [
      /[a-z]/.test(password),
      /[A-Z]/.test(password),
      /\d/.test(password),
      /[^a-zA-Z0-9]/.test(password),
      password.length >= 14,
      !subscriberCode || password.toLowerCase().indexOf(subscriberCode.toLowerCase()) === -1,
      !adminUsername || password.toLowerCase().indexOf(adminUsername.toLowerCase()) === -1
    ];
    if (checks.indexOf(false) !== -1) {
      return {
        status: "error",
        message: "A senha inicial precisa ter pelo menos 14 caracteres, maiuscula, minuscula, numero, simbolo e nao pode repetir usuario ou codigo do assinante."
      };
    }
    return {
      status: "ok",
      message: "Credencial inicial atende a politica minima."
    };
  };

  global.SystemInstallDemoHttpClient = SystemInstallDemoHttpClient;
})(window);
