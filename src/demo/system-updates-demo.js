(function(global) {
  "use strict";

  function SystemUpdatesDemoHttpClient() {
    this.consents = [];
    this.activations = [];
    this.subscribers = [
      { code: "empresa-a", name: "Empresa A", databaseEnvironment: "prod", databaseIdentity: "saas:empresa-a", updateChannel: "stable" },
      { code: "empresa-b", name: "Empresa B", databaseEnvironment: "prod", databaseIdentity: "saas:empresa-b", updateChannel: "pilot" }
    ];
    this.releases = [
      {
        version: "1.0.1",
        title: "Correcao critica de seguranca do runtime",
        category: "security_critical",
        severity: "critical",
        requiresVersionMin: "1.0.0",
        requiresAppliedUpdates: [],
        replaces: [],
        breakingLevel: "security_forced",
        autoApplySaas: true,
        autoApplyOnPrem: true,
        requiresSubscriberConsent: false,
        blocksNextUpdates: true,
        internetRequired: false,
        requiresConsent: false,
        autoApplicable: true,
        channels: ["stable", "pilot", "canary"],
        packageAvailable: true,
        packageUrl: "backend/config/system-updates/packages/runtime-security-1.0.1.pkg",
        steps: [
          { code: "migrate", title: "Aplicar migrations", timeoutSeconds: 900, idempotent: true },
          { code: "seed_runtime_metadata", title: "Atualizar metadados runtime", timeoutSeconds: 300, idempotent: true },
          { code: "publish_runtime_defaults", title: "Publicar catalogo padrao", timeoutSeconds: 300, idempotent: true },
          { code: "integrity_monitor", title: "Verificar integridade estrutural", timeoutSeconds: 180, idempotent: true }
        ],
        metadata: {
          requiresBackup: false,
          requiresMaintenanceMode: false,
          channels: ["stable", "pilot", "canary"],
          changelog: [
            {
              title: "Seguranca",
              items: [
                "Corrige validacoes obrigatorias do runtime.",
                "Fecha o caminho vulneravel de publicacao sem manifesto valido."
              ],
              impact: "tecnico",
              risk: "baixo",
              reversible: true
            }
          ],
          saasRolloutWindow: {
            startAt: "2026-05-17T22:00:00-03:00",
            durationMinutes: 45,
            freezeNewSessions: true
          },
          saasRolloutBatches: [
            { code: "canary", title: "Canario inicial", subscriberCodes: ["empresa-a"] },
            { code: "wave-1", title: "Lote principal", subscriberCodes: ["empresa-b"] }
          ]
        },
        impactReport: {
          standardProgramSummary: {
            install: 1,
            update: 0,
            verify: 0,
            aheadOfTarget: 0
          },
          programs: [
            {
              programCode: "admin-assinante-ambientes",
              targetPublishedVersion: "1.0.0",
              currentPublishedVersion: null,
              standardProgramAction: "install",
              standardProgramStatus: "install_new_standard",
              standardProgramMessage: "Programa padrao novo aguardando instalacao controlada pela esteira da release.",
              overlayImpacts: []
            },
            {
              programCode: "admin-integracoes",
              targetPublishedVersion: "1.0.1",
              currentPublishedVersion: "1.0.0",
              standardProgramAction: "update",
              standardProgramStatus: "update_standard",
              standardProgramMessage: "Programa padrao admin-integracoes precisa subir de 1.0.0 para 1.0.1.",
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
        requiresVersionMin: "1.0.0",
        requiresAppliedUpdates: ["1.0.1"],
        replaces: [],
        breakingLevel: "structural_breaking",
        autoApplySaas: true,
        autoApplyOnPrem: false,
        requiresSubscriberConsent: true,
        blocksNextUpdates: true,
        internetRequired: false,
        requiresConsent: true,
        autoApplicable: false,
        channels: ["stable", "pilot"],
        packageAvailable: true,
        packageUrl: "backend/config/system-updates/packages/runtime-structure-1.0.2.pkg",
        steps: [
          { code: "migrate", title: "Aplicar migrations", timeoutSeconds: 900, idempotent: true },
          { code: "seed_runtime_metadata", title: "Atualizar metadados runtime", timeoutSeconds: 300, idempotent: true },
          { code: "publish_runtime_defaults", title: "Publicar catalogo padrao", timeoutSeconds: 300, idempotent: true },
          { code: "integrity_monitor", title: "Verificar integridade estrutural", timeoutSeconds: 180, idempotent: true }
        ],
        dependencyIssues: ["Atualizacao obrigatoria pendente: 1.0.1."],
        impactReport: {
          standardProgramSummary: {
            install: 0,
            update: 1,
            verify: 0,
            aheadOfTarget: 0
          },
          programs: [
            {
              programCode: "admin-programa-governanca",
              targetPublishedVersion: "1.0.0",
              currentPublishedVersion: "0.9.0",
              standardProgramAction: "update",
              standardProgramStatus: "update_standard",
              standardProgramMessage: "Programa padrao admin-programa-governanca precisa subir de 0.9.0 para 1.0.0.",
              overlayImpacts: []
            }
          ]
        },
        metadata: {
          channels: ["stable", "pilot"],
          changelog: [
            {
              title: "Estrutura",
              items: [
                "Padroniza as estruturas exigidas antes das proximas releases.",
                "Exige backup e janela controlada."
              ],
              impact: "estrutural",
              risk: "medio",
              reversible: false,
              actionRequired: "Aplicar backup antes do rollout."
            }
          ],
          requiresBackup: true,
          requiresMaintenanceMode: true,
          orchestratorAction: "maintenance-rollout",
          rollbackSteps: [
            { code: "dispatch_rollback", title: "Despachar rollback SaaS" }
          ],
          saasRolloutWindow: {
            startAt: "2026-05-18T00:30:00-03:00",
            durationMinutes: 90,
            freezeNewSessions: false
          },
          saasRolloutBatches: [
            { code: "canary", title: "Canario inicial", subscriberCodes: ["empresa-a"] },
            { code: "wave-1", title: "Lote principal", subscriberCodes: ["empresa-b"] }
          ]
        }
      },
      {
        version: "1.0.3",
        title: "Melhoria visual opcional",
        category: "optional_visual",
        severity: "low",
        requiresVersionMin: "1.0.0",
        requiresAppliedUpdates: ["1.0.1", "1.0.2"],
        replaces: [],
        breakingLevel: "non_breaking",
        autoApplySaas: false,
        autoApplyOnPrem: false,
        requiresSubscriberConsent: true,
        blocksNextUpdates: false,
        internetRequired: false,
        requiresConsent: true,
        autoApplicable: false,
        channels: ["pilot", "canary"],
        packageAvailable: true,
        packageUrl: "backend/config/system-updates/packages/admin-visual-1.0.3.pkg",
        steps: [
          { code: "seed_runtime_metadata", title: "Atualizar metadados runtime", timeoutSeconds: 300, idempotent: true },
          { code: "publish_runtime_defaults", title: "Publicar catalogo padrao", timeoutSeconds: 300, idempotent: true }
        ],
        dependencyIssues: ["Atualizacao obrigatoria pendente: 1.0.2."],
        impactReport: {
          standardProgramSummary: {
            install: 0,
            update: 1,
            verify: 0,
            aheadOfTarget: 0
          },
          programs: [
            {
              programCode: "admin-integracoes",
              targetPublishedVersion: "1.1.0",
              currentPublishedVersion: "1.0.0",
              standardProgramAction: "update",
              standardProgramStatus: "update_standard",
              standardProgramMessage: "Programa padrao admin-integracoes precisa subir de 1.0.0 para 1.1.0.",
              overlayImpacts: [
                { overlayId: 11, status: "rebase_ok", message: "A nova base pode gerar rascunho de rebase automaticamente." },
                { overlayId: 14, status: "rebase_warning", message: "A base e o overlay alteraram a mesma secao do contrato." },
                { overlayId: 12, status: "custom_frozen", message: "Variante completa permanece congelada." }
              ]
            }
          ]
        },
        metadata: {
          channels: ["pilot", "canary"],
          changelog: [
            {
              title: "Visual",
              items: [
                "Ajusta a apresentacao das telas administrativas.",
                "Pode ser liberada por tenant conforme canal."
              ],
              impact: "visual",
              risk: "baixo",
              reversible: true
            }
          ],
          requiresBackup: false,
          requiresMaintenanceMode: false,
          orchestratorAction: "frontend-refresh",
          saasRolloutBatches: [
            { code: "canary", title: "Canario inicial", subscriberCodes: ["empresa-a"] },
            { code: "wave-1", title: "Lote principal", subscriberCodes: ["empresa-b"] }
          ]
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
          applicationPolicy: {
            requiresBackup: false,
            requiresMaintenanceMode: false,
            autoApplySaas: false,
            autoApplyOnPrem: false,
            requiresSubscriberConsent: true,
            blocksNextUpdates: false,
            internetRequired: false
          },
          structuralPipeline: {
            migrationsApplied: true,
            metadataSeeded: true,
            runtimeDefaultsPublished: true,
            integrityChecked: true
          },
          standardProgramPipeline: {
            installed: 1,
            updated: 1,
            verified: 0,
            aheadOfTarget: 0,
            failed: 0
          },
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
          standardProgramPipelineSummary: {
            installed: 1,
            updated: 1,
            verified: 0,
            aheadOfTarget: 0,
            failed: 0
          },
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
              programCode: "admin-assinante-ambientes",
              standardProgramPipelineStatus: "installed",
              standardProgramPipelineMessage: "Programa padrao novo instalado pela esteira da release.",
              appliedPublishedVersion: "1.0.0",
              overlayImpacts: []
            },
            {
              programCode: "admin-integracoes",
              standardProgramPipelineStatus: "updated",
              standardProgramPipelineMessage: "Nova versao padrao aplicada pela release.",
              appliedPublishedVersion: "1.0.1",
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
    if (method === "POST" && url === "/api/admin/system-updates/tenant-activation") {
      const version = String(data.version || "");
      const activation = {
        id: this.activations.length + 1,
        releaseVersion: version,
        status: String(data.status || "enabled"),
        decidedBy: "admin",
        source: "ui",
        deploymentMode: "saas",
        databaseIdentity: "saas:demo",
        targetSubscriberCode: String(data.subscriberCode || ""),
        targetSubscriberName: this.findSubscriberName(String(data.subscriberCode || "")),
        reason: String(data.reason || ""),
        createdAt: new Date().toISOString()
      };
      this.activations.unshift(activation);
      return Promise.resolve(activation);
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
    if (method === "GET" && url === "/api/admin/system-updates/simulate") {
      const version = String(data.version || "");
      const subscriberCode = String(data.subscriberCode || "");
      const release = this.resolveReleases(subscriberCode).find((item) => item.version === version);
      if (!release) {
        return Promise.reject({ error: { message: "Release demo nao encontrada." } });
      }
      return Promise.resolve({
        release: release,
        precheck: this.buildCompatibilityPrecheck(release),
        subscriberImpact: this.buildSubscriberImpact(release, subscriberCode, String(data.batchCode || "")),
        rollbackPlan: this.buildRollbackPlan(release),
        delayDashboard: this.buildDelayDashboard(),
        operationalAlerts: this.buildOperationalAlerts(this.resolveReleases(subscriberCode))
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
        ],
        externalPublication: {
          status: "dispatched",
          url: "https://distribution.demo.local/system-updates",
          httpStatus: 202,
          signatureAlgorithm: "hmac-sha256",
          response: {
            accepted: true,
            provider: "demo-distribution"
          }
        }
      });
    }
    if (method === "GET" && url === "/api/admin/system-updates/rollout-plan") {
      const version = String(data.version || "");
      const release = this.resolveReleases(String(data.subscriberCode || "")).find((item) => item.version === version);
      if (!release) {
        return Promise.reject({ error: { message: "Release demo nao encontrada." } });
      }
      const rolloutBatches = this.resolveRolloutBatches(release, String(data.subscriberCode || ""));
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
        rolloutWindow: this.resolveRolloutWindow(release),
        rolloutBatches: rolloutBatches,
        defaultBatchCode: rolloutBatches.length ? rolloutBatches[0].code : null,
        entryBlockPlan: {
          enabled: release.category === "security_critical",
          accessMode: release.category === "security_critical" ? "blocked" : "warning",
          message: release.category === "security_critical"
            ? "A entrada do tenant pode ficar temporariamente bloqueada durante o rollout."
            : "A release pode seguir sem bloquear novas sessoes."
        },
        suggestedSequence: ["validar impacto", "executar rollout controlado", "validar integridade"]
      });
    }
    if (method === "POST" && url === "/api/admin/system-updates/dispatch-rollout") {
      const version = String(data.version || "");
      const subscriberCode = String(data.subscriberCode || "");
      const batchCode = String(data.batchCode || "");
      const release = this.resolveReleases(subscriberCode).find((item) => item.version === version);
      if (!release) {
        return Promise.reject({ error: { message: "Release demo nao encontrada." } });
      }
      const plan = {
        rolloutWindow: this.resolveRolloutWindow(release),
        rolloutBatches: this.resolveRolloutBatches(release, subscriberCode)
      };
      const subscribers = subscriberCode
        ? [this.findSubscriber(subscriberCode)].filter(Boolean)
        : this.resolveDispatchSubscribers(batchCode, plan.rolloutBatches);
      const dispatches = subscribers.map((subscriber, index) => {
        const execution = {
          id: this.executions.length + 1 + index,
          releaseVersion: release.version,
          releaseTitle: release.title,
          category: release.category,
          severity: release.severity,
          status: "succeeded",
          mode: "rollout_dispatch",
          deploymentMode: "saas",
          databaseEnvironment: "dev",
          databaseIdentity: "saas:demo",
          targetSubscriberCode: subscriber.code,
          targetSubscriberName: subscriber.name,
          targetDatabaseEnvironment: subscriber.databaseEnvironment,
          targetDatabaseIdentity: subscriber.databaseIdentity,
          initiatedBy: "admin",
          initiatedSource: "ui",
          runtimeJobId: null,
          summary: {
            message: "Rollout despachado para o orquestrador demo.",
            applicationPolicy: this.buildApplicationPolicy(release),
            rolloutAudit: {
              stage: "dispatch",
              dispatchCount: 1,
              entryAccessMode: release.category === "security_critical" ? "blocked" : "warning",
              windowStatus: this.resolveRolloutWindow(release).status || "unscheduled",
              batchCode: subscriberCode ? null : batchCode
            }
          },
          impactReport: release.impactReport || {},
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
          finishedAt: new Date().toISOString()
        };
        this.executions.unshift(execution);
        return {
          targetSubscriber: subscriber,
          batch: batchCode ? { code: batchCode } : null,
          dispatch: {
            status: "dispatched",
            message: "Rollout despachado para o orquestrador demo.",
            endpoint: "https://orchestrator.demo.local/system-update",
            httpStatus: 202,
            responseHeaders: {
              "x-request-id": "demo-rollout-" + version + "-" + subscriber.code
            },
            responseBody: {
              queued: true,
              version: version
            }
          },
          execution: execution
        };
      });
      return Promise.resolve({
        releaseVersion: version,
        targetSubscriber: this.findSubscriber(subscriberCode) || null,
        plan: plan,
        dispatches: dispatches,
        summary: {
          dispatchCount: dispatches.length,
          succeededCount: dispatches.length,
          failedCount: 0
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
        summary: {
          message: "Atualizacao enfileirada.",
          applicationPolicy: this.buildApplicationPolicy(release)
        },
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
          applicationPolicy: this.buildApplicationPolicy(release),
          structuralPipeline: {
            migrationsApplied: release.steps.some((step) => String(step.code || step) === "migrate"),
            metadataSeeded: release.steps.some((step) => String(step.code || step) === "seed_runtime_metadata"),
            runtimeDefaultsPublished: release.steps.some((step) => String(step.code || step) === "publish_runtime_defaults"),
            integrityChecked: release.steps.some((step) => String(step.code || step) === "integrity_monitor")
          },
          standardProgramPipeline: execution.impactReport.standardProgramPipelineSummary || {},
          overlayPipeline: execution.impactReport.overlayPipelineSummary || {}
        };
        const original = this.releases.find((item) => item.version === release.version);
        if (original) {
          original.status = "applied";
        }
      }, 1600);
      return Promise.resolve({ execution: execution, job: job });
    }
    if (method === "POST" && url === "/api/admin/system-updates/rollback") {
      const version = String(data.version || "");
      const subscriberCode = String(data.subscriberCode || "");
      const release = this.resolveReleases(subscriberCode).find((item) => item.version === version);
      if (!release) {
        return Promise.reject({ error: { message: "Release demo nao encontrada." } });
      }
      const execution = {
        id: this.executions.length + 1,
        releaseVersion: release.version,
        releaseTitle: release.title,
        category: release.category,
        severity: release.severity,
        status: "succeeded",
        mode: "rollback",
        deploymentMode: "saas",
        databaseEnvironment: "dev",
        databaseIdentity: "saas:demo",
        targetSubscriberCode: subscriberCode || "",
        targetSubscriberName: this.findSubscriberName(subscriberCode),
        targetDatabaseEnvironment: "prod",
        targetDatabaseIdentity: subscriberCode ? "saas:" + subscriberCode : "",
        initiatedBy: "admin",
        initiatedSource: "ui",
        runtimeJobId: null,
        summary: {
          message: "Rollback formal concluido.",
          applicationPolicy: this.buildApplicationPolicy(release),
          rollback: this.buildRollbackPlan(release),
          reason: String(data.reason || "")
        },
        impactReport: {},
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
        finishedAt: new Date().toISOString()
      };
      this.executions.unshift(execution);
      return Promise.resolve({
        status: "succeeded",
        releaseVersion: version,
        execution: execution,
        rollbackPlan: this.buildRollbackPlan(release),
        steps: [{ step: "dispatch_rollback", status: "ok" }]
      });
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
        delayDashboard: this.buildDelayDashboard(),
        operationalAlerts: this.buildOperationalAlerts(releases),
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
    const standardProgramPipeline = {
      installed: 0,
      updated: 0,
      verified: 0,
      aheadOfTarget: 0,
      failed: 0
    };
    const rolloutAudit = {
      dispatchCount: 0,
      blockedEntryCount: 0,
      windowScheduledCount: 0,
      batchCodes: [],
      byStage: {}
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
      const standardPipeline = item.summary && item.summary.standardProgramPipeline || item.impactReport && item.impactReport.standardProgramPipelineSummary || {};
      standardProgramPipeline.installed += Number(standardPipeline.installed || 0);
      standardProgramPipeline.updated += Number(standardPipeline.updated || 0);
      standardProgramPipeline.verified += Number(standardPipeline.verified || 0);
      standardProgramPipeline.aheadOfTarget += Number(standardPipeline.aheadOfTarget || 0);
      standardProgramPipeline.failed += Number(standardPipeline.failed || 0);
      const rollout = item.summary && item.summary.rolloutAudit || {};
      rolloutAudit.dispatchCount += Number(rollout.dispatchCount || 0);
      rolloutAudit.blockedEntryCount += rollout.entryAccessMode === "blocked" ? 1 : 0;
      rolloutAudit.windowScheduledCount += rollout.windowStatus === "scheduled" ? 1 : 0;
      if (rollout.batchCode && rolloutAudit.batchCodes.indexOf(rollout.batchCode) < 0) {
        rolloutAudit.batchCodes.push(rollout.batchCode);
      }
      if (rollout.stage) {
        rolloutAudit.byStage[rollout.stage] = Number(rolloutAudit.byStage[rollout.stage] || 0) + 1;
      }
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
        standardProgramPipeline: standardProgramPipeline,
        overlayPipeline: overlayPipeline,
        rolloutAudit: rolloutAudit,
        timeline: rows.map((item) => ({
          id: item.id,
          releaseVersion: item.releaseVersion,
          title: item.mode === "rollback" ? "Rollback da release" : (item.mode === "rollout_dispatch" ? "Despacho de rollout" : "Aplicacao da release"),
          status: item.status,
          mode: item.mode,
          createdAt: item.createdAt,
          finishedAt: item.finishedAt,
          subscriberCode: item.targetSubscriberCode,
          message: item.summary && item.summary.message || ""
        })),
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
    const standardSummary = {
      installed: 0,
      updated: 0,
      verified: 0,
      aheadOfTarget: 0,
      failed: 0
    };
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
      if (program.standardProgramStatus === "install_new_standard") {
        program.standardProgramPipelineStatus = "installed";
        program.standardProgramPipelineMessage = "Programa padrao novo instalado pela esteira da release " + releaseVersion + ".";
        program.appliedPublishedVersion = String(program.targetPublishedVersion || "1.0.0");
        standardSummary.installed += 1;
      } else if (program.standardProgramStatus === "update_standard") {
        program.standardProgramPipelineStatus = "updated";
        program.standardProgramPipelineMessage = "Nova versao padrao aplicada na release " + releaseVersion + ".";
        program.appliedPublishedVersion = String(program.targetPublishedVersion || program.currentPublishedVersion || "");
        standardSummary.updated += 1;
      } else if (program.standardProgramStatus === "ahead_of_target") {
        program.standardProgramPipelineStatus = "ahead_of_target";
        program.standardProgramPipelineMessage = "Ambiente ja estava acima da versao alvo declarada.";
        program.appliedPublishedVersion = String(program.currentPublishedVersion || "");
        standardSummary.aheadOfTarget += 1;
      } else {
        program.standardProgramPipelineStatus = "verified";
        program.standardProgramPipelineMessage = "Programa padrao validado sem sobrescrita arbitraria.";
        program.appliedPublishedVersion = String(program.currentPublishedVersion || program.targetPublishedVersion || "");
        standardSummary.verified += 1;
      }
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
    cloned.standardProgramPipelineSummary = standardSummary;
    cloned.overlayPipelineSummary = summary;
    return cloned;
  };

  SystemUpdatesDemoHttpClient.prototype.resolveReleases = function(subscriberCode) {
    const normalizedSubscriber = String(subscriberCode || "");
    const applied = new Set(this.executions
      .filter((item) => item.status === "succeeded" && (!normalizedSubscriber || String(item.targetSubscriberCode || "") === normalizedSubscriber))
      .map((item) => item.releaseVersion));
    const latestConsentByVersion = {};
    this.consents.forEach((item) => {
      const consentKey = item.releaseVersion + "|" + String(item.targetSubscriberCode || "");
      if (!latestConsentByVersion[consentKey]) {
        latestConsentByVersion[consentKey] = item;
      }
    });
    return this.releases.map((item) => {
      const release = Object.assign({}, item);
      release.requiresConsent = release.requiresSubscriberConsent !== false;
      if (applied.has(release.version)) {
        release.status = "applied";
      } else if ((release.requiresAppliedUpdates || []).some((requiredVersion) => !applied.has(requiredVersion))) {
        release.status = "blocked_dependency";
      } else {
        release.status = "pending";
      }
      const consent = latestConsentByVersion[release.version + "|" + normalizedSubscriber] || null;
      const activation = this.findActivation(release.version, normalizedSubscriber);
      release.consentStatus = release.requiresConsent ? (consent ? consent.status : "pending") : "not-required";
      release.consentApproved = release.requiresConsent ? Boolean(consent && consent.status === "approved") : true;
      release.scenarioBehavior = this.resolveScenarioBehavior(release);
      release.channels = Array.isArray(release.channels) && release.channels.length ? release.channels.slice() : ["stable"];
      release.targetChannel = this.resolveSubscriberChannel(normalizedSubscriber);
      release.channelStatus = release.channels.indexOf(release.targetChannel) >= 0 ? "eligible" : "out_of_channel";
      release.rolloutWindow = this.resolveRolloutWindow(release);
      release.rolloutWindowStatus = release.rolloutWindow.status || "unscheduled";
      release.changelog = Array.isArray(release.metadata && release.metadata.changelog) ? release.metadata.changelog : [];
      release.stepCatalog = Array.isArray(release.steps) ? release.steps.slice() : [];
      release.applicationPolicy = this.buildApplicationPolicy(release);
      release.tenantActivationRequired = release.scenarioBehavior && release.scenarioBehavior.applyMode === "tenant_activation" && !!normalizedSubscriber;
      release.tenantActivationStatus = release.tenantActivationRequired ? (activation ? activation.status : "pending") : "not-required";
      release.tenantActivationInfo = activation || null;
      if (release.status === "pending" && release.channelStatus !== "eligible") {
        release.status = "channel_unavailable";
        release.dependencyIssues = ["Release fora do canal do assinante."];
      }
      if (release.status === "pending" && release.tenantActivationRequired && release.tenantActivationStatus !== "enabled") {
        release.status = "awaiting_tenant_activation";
        release.dependencyIssues = ["A release opcional exige ativacao explicita para este assinante."];
      }
      release.compatibilityPrecheck = this.buildCompatibilityPrecheck(release);
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

  SystemUpdatesDemoHttpClient.prototype.resolveSubscriberChannel = function(subscriberCode) {
    const subscriber = this.findSubscriber(subscriberCode);
    return subscriber && subscriber.updateChannel ? String(subscriber.updateChannel) : "stable";
  };

  SystemUpdatesDemoHttpClient.prototype.findActivation = function(version, subscriberCode) {
    const normalizedVersion = String(version || "");
    const normalizedSubscriber = String(subscriberCode || "");
    return this.activations.find(function(item) {
      return String(item.releaseVersion || "") === normalizedVersion && String(item.targetSubscriberCode || "") === normalizedSubscriber;
    }) || null;
  };

  SystemUpdatesDemoHttpClient.prototype.resolveScenarioBehavior = function(release) {
    const category = String(release && release.category || "recommended");
    if (category === "security_critical") {
      return {
        control: "provider",
        applyMode: "automatic",
        rolloutMode: "short_window",
        entryBlockAllowed: true
      };
    }
    if (category === "required_structural") {
      return {
        control: "provider",
        applyMode: "automatic",
        rolloutMode: "progressive_by_tenant"
      };
    }
    return {
      control: "provider",
      applyMode: "tenant_activation",
      rolloutMode: "opt_in"
    };
  };

  SystemUpdatesDemoHttpClient.prototype.buildApplicationPolicy = function(release) {
    return {
      requiresBackup: !!(release && release.metadata && release.metadata.requiresBackup === true),
      requiresMaintenanceMode: !!(release && release.metadata && release.metadata.requiresMaintenanceMode === true),
      autoApplySaas: !!(release && release.autoApplySaas === true),
      autoApplyOnPrem: !!(release && release.autoApplyOnPrem === true),
      requiresSubscriberConsent: !(release && release.requiresSubscriberConsent === false),
      blocksNextUpdates: !!(release && release.blocksNextUpdates === true),
      internetRequired: !!(release && release.internetRequired === true),
      override: !!(release && release.metadata && release.metadata.applicationPolicyOverride === true),
      overrideJustification: release && release.metadata ? (release.metadata.applicationPolicyOverrideJustification || null) : null
    };
  };

  SystemUpdatesDemoHttpClient.prototype.buildCompatibilityPrecheck = function(release) {
    const checks = [
      { code: "version_chain", title: "Cadeia de versoes", status: release.status === "blocked_dependency" ? "blocked" : "ok", message: release.status === "blocked_dependency" ? (release.dependencyIssues || []).join(" ") : "Dependencias de versao satisfeitas." },
      { code: "channel", title: "Canal", status: release.channelStatus === "eligible" ? "ok" : "blocked", message: release.channelStatus === "eligible" ? "Release liberada para o canal do assinante." : "Release fora do canal do assinante." },
      { code: "consent", title: "Anuencia", status: release.requiresConsent && !release.consentApproved ? "warning" : "ok", message: release.requiresConsent ? (release.consentApproved ? "Anuencia registrada." : "A release exige anuencia.") : "Sem exigencia de anuencia." },
      { code: "tenant_activation", title: "Ativacao por tenant", status: release.tenantActivationRequired && release.tenantActivationStatus !== "enabled" ? "warning" : "ok", message: release.tenantActivationRequired ? (release.tenantActivationStatus === "enabled" ? "Tenant ativado." : "Aguardando ativacao do tenant.") : "Sem ativacao especifica por tenant." },
      { code: "package", title: "Pacote", status: release.packageAvailable ? "ok" : "blocked", message: release.packageAvailable ? "Pacote configurado para a release." : "Pacote ausente." },
      { code: "customization", title: "Customizacoes", status: release.status === "blocked_customization" ? "blocked" : "ok", message: release.status === "blocked_customization" ? "Existe customizacao bloqueante." : "Sem bloqueios de customizacao." }
    ];
    return {
      status: checks.some((item) => item.status === "blocked") ? "blocked" : (checks.some((item) => item.status === "warning") ? "warning" : "ok"),
      blockingCount: checks.filter((item) => item.status === "blocked").length,
      warningCount: checks.filter((item) => item.status === "warning").length,
      checks: checks
    };
  };

  SystemUpdatesDemoHttpClient.prototype.buildSubscriberImpact = function(release, subscriberCode, batchCode) {
    let subscribers = subscriberCode ? [this.findSubscriber(subscriberCode)].filter(Boolean) : this.subscribers.slice();
    if (!subscriberCode && batchCode) {
      const batches = this.resolveRolloutBatches(release, "");
      const batch = batches.find((item) => String(item.code || "") === String(batchCode || ""));
      subscribers = batch ? batch.subscribers.slice() : [];
    }
    const items = subscribers.map((subscriber) => {
      const evaluated = this.resolveReleases(subscriber.code).find((item) => item.version === release.version);
      return {
        subscriber: subscriber,
        status: evaluated && evaluated.status || "unknown",
        autoApplicable: evaluated && evaluated.autoApplicable === true,
        requiresConsent: evaluated && evaluated.requiresConsent === true,
        consentStatus: evaluated && evaluated.consentStatus || "not-required",
        tenantActivationStatus: evaluated && evaluated.tenantActivationStatus || "not-required",
        channel: evaluated && evaluated.targetChannel || "stable",
        compatibilityPrecheck: evaluated && evaluated.compatibilityPrecheck || { status: "ok", checks: [] }
      };
    });
    return {
      items: items,
      summary: {
        totalSubscribers: items.length,
        ready: items.filter((item) => item.status === "pending").length,
        requiresConsent: items.filter((item) => item.requiresConsent && item.consentStatus !== "approved").length,
        awaitingActivation: items.filter((item) => item.tenantActivationStatus === "pending").length,
        blockedDependency: items.filter((item) => item.status === "blocked_dependency").length,
        blockedCustomization: items.filter((item) => item.status === "blocked_customization").length,
        channelUnavailable: items.filter((item) => item.status === "channel_unavailable").length
      }
    };
  };

  SystemUpdatesDemoHttpClient.prototype.buildRollbackPlan = function(release) {
    return {
      supported: true,
      targetVersion: Array.isArray(release.replaces) && release.replaces.length ? release.replaces[0] : "1.0.0",
      dispatchRollback: true,
      steps: [{ code: "dispatch_rollback", title: "Despachar rollback SaaS" }]
    };
  };

  SystemUpdatesDemoHttpClient.prototype.buildDelayDashboard = function() {
    return {
      outdatedSubscribers: 2,
      blockedDependencySubscribers: 1,
      awaitingConsentSubscribers: 1,
      awaitingActivationSubscribers: 1,
      blockedCustomizationSubscribers: 0,
      channelUnavailableSubscribers: 1,
      failedRolloutSubscribers: this.executions.filter((item) => item.mode === "rollout_dispatch" && item.status === "failed").length
    };
  };

  SystemUpdatesDemoHttpClient.prototype.buildOperationalAlerts = function(releases) {
    const alerts = [];
    (releases || []).forEach(function(release) {
      if (release.status === "blocked_dependency") {
        alerts.push({ severity: "high", kind: "dependency", message: "A release " + release.version + " esta bloqueada pela cadeia obrigatoria." });
      }
      if (release.status === "awaiting_tenant_activation") {
        alerts.push({ severity: "medium", kind: "tenant_activation", message: "A release " + release.version + " aguarda ativacao do tenant." });
      }
      if (release.category === "security_critical" && release.applicationPolicy && release.applicationPolicy.autoApplyOnPrem === true && release.applicationPolicy.internetRequired !== true) {
        alerts.push({ severity: "medium", kind: "application_policy", message: "A release critica " + release.version + " aplica no on-premise sem internet obrigatoria declarada." });
      }
    });
    return alerts;
  };

  SystemUpdatesDemoHttpClient.prototype.resolveRolloutWindow = function(release) {
    const windowConfig = release && release.metadata && release.metadata.saasRolloutWindow || {};
    const startAt = String(windowConfig.startAt || "");
    const durationMinutes = Number(windowConfig.durationMinutes || 0);
    if (!startAt || !durationMinutes) {
      return {
        requiresWindow: false,
        startAt: null,
        durationMinutes: null,
        endAt: null,
        freezeNewSessions: false,
        status: "unscheduled"
      };
    }
    return {
      requiresWindow: true,
      startAt: startAt,
      durationMinutes: durationMinutes,
      endAt: startAt,
      freezeNewSessions: windowConfig.freezeNewSessions === true,
      status: "scheduled"
    };
  };

  SystemUpdatesDemoHttpClient.prototype.resolveRolloutBatches = function(release, subscriberCode) {
    const targetSubscriberCode = String(subscriberCode || "");
    const configured = release && release.metadata && Array.isArray(release.metadata.saasRolloutBatches)
      ? release.metadata.saasRolloutBatches
      : [];
    const availableSubscribers = targetSubscriberCode
      ? this.subscribers.filter((item) => item.code === targetSubscriberCode)
      : this.subscribers.slice();
    return configured.map((item) => {
      const subscriberCodes = Array.isArray(item && item.subscriberCodes) ? item.subscriberCodes : [];
      const subscribers = availableSubscribers.filter((subscriber) => subscriberCodes.indexOf(subscriber.code) >= 0);
      return {
        code: String(item && item.code || ""),
        title: String(item && item.title || ""),
        status: "pending",
        subscribers: subscribers
      };
    }).filter((item) => item.subscribers.length > 0);
  };

  SystemUpdatesDemoHttpClient.prototype.resolveDispatchSubscribers = function(batchCode, batches) {
    const code = String(batchCode || "");
    const match = (batches || []).find((item) => String(item.code || "") === code);
    return match ? match.subscribers.slice() : [];
  };

  global.SystemUpdatesDemoHttpClient = SystemUpdatesDemoHttpClient;
})(window);
