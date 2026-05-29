(function(global) {
  "use strict";

  function ProgramBuilderDemoHttpClient() {
  }

  ProgramBuilderDemoHttpClient.prototype.request = function(config) {
    const url = String(config && config.url || "");
    const method = String(config && config.method || "GET").toUpperCase();
    const data = config && config.data || {};
    const embedded = global.ProgramBuilderEmbeddedData || {};

    if (url === "/api/runtime/literals/pt-BR" && method === "GET") {
      return Promise.resolve({ literals: {} });
    }
    if (url === "/api/admin/program-builder/bootstrap" && method === "GET") {
      return Promise.resolve(global.CrudUtils.clone(embedded.bootstrap || {}));
    }
    if (url === "/api/admin/program-builder/database/tables" && method === "GET") {
      return Promise.resolve({
        tables: [
          { qualifiedName: "public.cliente", schema: "public", tableName: "cliente", existingEntityCode: "cliente" }
        ]
      });
    }
    if (url === "/api/admin/program-builder/database/inspect-ddl" && method === "POST") {
      return Promise.resolve(buildDdlInspection(data));
    }
    if (url === "/api/admin/program-builder/database/import-ddl" && method === "POST") {
      const inspection = buildDdlInspection(data);
      return Promise.resolve({
        table: inspection.table,
        classification: inspection.classification,
        diagnostics: inspection.diagnostics,
        entity: inspection.entityDraft,
        entityVersion: { id: 9101, revision: 1, source: "import", changeSummary: "Entidade importada de SQL/DDL." },
        entityVersions: [{ id: 9101, revision: 1, source: "import", changeSummary: "Entidade importada de SQL/DDL." }],
        programVersion: data.generateProgramDraft === false ? null : {
          id: 9102,
          programCode: inspection.programDraft.programCode || "cd9001",
          version: inspection.programDraft.version || "1.0.0",
          status: "draft"
        },
        programDraftGenerated: data.generateProgramDraft !== false,
        source: "ddl"
      });
    }
    if (/\/api\/admin\/program-builder\/api-sources\/[^/]+$/.test(url) && method === "GET") {
      const code = decodeURIComponent(url.split("/").pop() || "");
      return Promise.resolve(global.CrudUtils.clone((embedded.apiSources || {})[code] || null));
    }
    if (url === "/api/admin/program-builder/entities/cliente" && method === "GET") {
      return Promise.resolve(global.CrudUtils.clone(embedded.entityDefinition || {}));
    }
    if (url === "/api/admin/program-builder/programs/cd1001" && method === "GET") {
      return Promise.resolve(global.CrudUtils.clone(embedded.programDefinition || {}));
    }
    if (url === "/api/admin/program-builder/api-sources/odoo/model-metadata" && method === "POST") {
      return Promise.resolve({
        fields: [
          { code: "id", label: "ID", dataType: "integer", primaryKey: true, readonlyField: true, apiJsonPath: "id", options: { odoo: { fieldType: "integer" } } },
          { code: "name", label: "Nome", dataType: "string", apiJsonPath: "name", options: { odoo: { fieldType: "char" } } }
        ]
      });
    }
    if (url === "/api/admin/program-builder/api-sources/odoo/test-connection" && method === "POST") {
      return Promise.resolve({ ok: true, transport: data.transport || "jsonrpc", uid: 7 });
    }
    if (url === "/api/admin/program-builder/ai/settings" && method === "GET") {
      return Promise.resolve({
        enabled: true,
        provider: "mock",
        agentName: "Assistente do construtor",
        apiTokenConfigured: false,
        transcriptionEnabled: false
      });
    }
    if (url === "/api/admin/program-builder/ai/session" && method === "POST") {
      const sessionId = data.forceNew || !data.sessionId ? "builder-ai-demo-" + Date.now() : data.sessionId;
      return Promise.resolve({
        sessionId: sessionId,
        session: buildAiSession(sessionId, null),
        messages: [{
          id: "builder-ai-demo-welcome",
          text: "Descreva o cadastro que voce quer montar. Esta e uma sessao persistente simulada na demo.",
          authorId: "ia-builder",
          authorName: "Assistente do construtor",
          timestamp: new Date().toISOString()
        }],
        draft: null,
        diagnostics: [],
        readyToApply: false
      });
    }
    if (url === "/api/admin/program-builder/ai/message" && method === "POST") {
      const sessionId = data.sessionId || "builder-ai-demo";
      const draft = buildAiDraftFromText(String(data.message && data.message.text || data.text || ""));
      return Promise.resolve({
        sessionId: sessionId,
        session: buildAiSession(sessionId, draft),
        messages: [{
          id: "builder-ai-demo-response-" + Date.now(),
          text: "Montei um rascunho inicial em uma sessao persistente simulada. Revise antes de carregar.",
          authorId: "ia-builder",
          authorName: "Assistente do construtor",
          timestamp: new Date().toISOString()
        }],
        draft: draft,
        generatedDefinition: {},
        diagnostics: [{ level: "info", message: "Resposta gerada pelo mock local do Program Builder." }],
        readyToApply: true,
        missingInputs: []
      });
    }
    if (url === "/api/admin/program-builder/ai/finalize-draft" && method === "POST") {
      const draft = data.draft || buildAiDraftFromText("produto");
      return Promise.resolve({
        sessionId: data.sessionId || "builder-ai-demo",
        session: buildAiSession(data.sessionId || "builder-ai-demo", draft),
        readyToApply: true,
        entityDraft: draft.entityDraft,
        programDraft: draft.programDraft,
        normalizedDraft: draft,
        generatedDefinition: {},
        diagnostics: [{ level: "info", message: "Rascunho validado pelo mock local." }]
      });
    }
    if (url === "/api/admin/program-builder/preview" && method === "POST") {
      if (String(data.pageType || "crud") === "analytics") {
        return Promise.resolve({
          generatedDefinition: buildAnalyticsPreview(data),
          diagnostics: []
        });
      }
      if (String(data.pageType || "crud") === "report") {
        return Promise.resolve({
          generatedDefinition: buildReportPreview(data),
          diagnostics: []
        });
      }
      if (String(data.pageType || "crud") === "special_document") {
        return Promise.resolve({
          generatedDefinition: buildSpecialDocumentPreview(data),
          diagnostics: []
        });
      }
      return Promise.resolve({
        generatedDefinition: {
          screenId: "cadastros.clientes",
          pageType: "crud",
          dataModel: { fields: {} },
          crud: { grid: { columns: [] }, filter: { fields: [] }, form: { fields: [] } }
        },
        diagnostics: []
      });
    }
    if (/^\/api\/runtime\/screens\/[^/]+\/endpoints\/analytics\.query\.run$/.test(url) && method === "POST") {
      return Promise.resolve(runAnalyticsDataset(data));
    }
    if (/^\/api\/runtime\/screens\/[^/]+\/endpoints\/analytics\.cache\.status$/.test(url) && method === "POST") {
      return Promise.resolve({
        status: "ready",
        screenId: String((data && data.screenId) || "analytics.clientes"),
        datasetId: String(data && data.datasetId || "principal"),
        viewId: "",
        fingerprint: "demo-fingerprint",
        rowCount: 3,
        refreshedAt: new Date().toISOString(),
        expiresAt: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
        expired: false,
        lastError: null
      });
    }
    if (/^\/api\/runtime\/screens\/[^/]+\/endpoints\/analytics\.materialize$/.test(url) && method === "POST") {
      const result = runAnalyticsDataset(data);
      return Promise.resolve({
        ok: true,
        screenId: "analytics.clientes",
        datasetId: String(data && data.datasetId || "principal"),
        viewId: "",
        fingerprint: "demo-fingerprint",
        rowCount: Array.isArray(result.data) ? result.data.length : 0,
        expiresAt: new Date(Date.now() + 15 * 60 * 1000).toISOString()
      });
    }
    if (/^\/api\/runtime\/screens\/[^/]+\/endpoints\/reports\.run$/.test(url) && method === "POST") {
      return Promise.resolve(runReportDataset(url, data));
    }
    if (/^\/api\/runtime\/screens\/[^/]+\/endpoints\/reports\.export$/.test(url) && method === "POST") {
      return Promise.resolve(exportReportDataset(url, data));
    }
    return Promise.resolve({});
  };

  function buildAnalyticsPreview(data) {
    const analyticsConfig = Object.assign({
      executionMode: "live",
      limit: 1000,
      cacheTtlSeconds: 900,
      defaultSortField: "",
      defaultSortDir: "asc",
      chartSeriesType: "column",
      chartCategoryField: "",
      chartValueField: "",
      views: {
        grid: true,
        chart: true,
        pivot: true,
        kpi: false,
        dashboard: true
      },
      joins: []
    }, data && data.analyticsConfig || {});
    const screenId = String(data && data.screenId || "analytics.clientes").trim() || "analytics.clientes";
    const title = String(data && data.programTitle || "Clientes por UF").trim() || "Clientes por UF";
    const version = String(data && data.version || "1.0.0").trim() || "1.0.0";
    const entityCode = String(data && data.builderEntityCode || "cliente").trim() || "cliente";
    const views = [];
    if (analyticsConfig.views.grid !== false) {
      views.push({ id: "grid", type: "grid", title: "Grid", datasetId: "principal" });
    }
    if (analyticsConfig.views.chart !== false) {
      views.push({ id: "chart", type: "chart", title: "Grafico", datasetId: "principal", categoryField: analyticsConfig.chartCategoryField || "uf", valueField: analyticsConfig.chartValueField || "limite_credito_sum", valueFormat: "c2", seriesType: analyticsConfig.chartSeriesType || "column" });
    }
    if (analyticsConfig.views.pivot !== false) {
      views.push({ id: "pivot", type: "pivot", title: "Pivot", datasetId: "principal" });
    }
    if (analyticsConfig.views.kpi === true) {
      views.push({ id: "kpi", type: "kpi", title: "Indicador", datasetId: "principal", valueField: analyticsConfig.chartValueField || "limite_credito_sum", format: "c2" });
    }
    if (analyticsConfig.views.dashboard !== false) {
      views.push({ id: "dashboard", type: "dashboard", title: "Dashboard", datasetId: "principal" });
    }
    return {
      screenId: screenId,
      pageType: "analytics",
      program: {
        id: String(data && data.programCode || "cd1001").trim() || "cd1001",
        title: title,
        module: String(data && data.module || "cadastros").trim() || "cadastros",
        version: version,
        subtitle: "Preview local analytics do construtor",
        icon: "chart-line",
        screenId: screenId
      },
      permissions: {
        read: true,
        materialize: true
      },
      runtime: {
        entityCode: entityCode,
        programId: String(data && data.programCode || "cd1001").trim() || "cd1001",
        mode: "analytics"
      },
      dataSource: {
        api: {
          schema: { endpointId: "analytics.schema" },
          run: { endpointId: "analytics.query.run" },
          materialize: { endpointId: "analytics.materialize" },
          cacheStatus: { endpointId: "analytics.cache.status" }
        }
      },
      analytics: {
        version: "1.0",
        audit: {
          enabled: !(analyticsConfig.audit && analyticsConfig.audit.enabled === false),
          includeCacheHits: !(analyticsConfig.audit && analyticsConfig.audit.includeCacheHits === false)
        },
        endpoints: {
          schema: { endpointId: "analytics.schema" },
          run: { endpointId: "analytics.query.run" },
          materialize: { endpointId: "analytics.materialize" },
          cacheStatus: { endpointId: "analytics.cache.status" }
        },
        datasets: [
          {
            id: "principal",
            title: title,
            source: { type: "entity", entityCode: entityCode },
            joins: Array.isArray(analyticsConfig.joins) ? analyticsConfig.joins : [],
            fields: [
              { id: "uf", field: "uf", label: "UF", type: "string" },
              { id: "status", field: "status", label: "Status", type: "string" },
              { id: "limite_credito_sum", field: "limite_credito", label: "Limite de credito", type: "decimal", format: "c2" }
            ],
            dimensions: [
              { id: "uf", field: "uf", label: "UF", type: "string" },
              { id: "status", field: "status", label: "Status", type: "string" }
            ],
            measures: [
              { id: "limite_credito_sum", field: "limite_credito", label: "Limite de credito", aggregate: "sum", format: "c2" },
              { id: "total_clientes", field: "id", label: "Clientes", aggregate: "count", format: "n0" }
            ],
            parameters: [
              { id: "status", field: "status", label: "Status", type: "text" },
              { id: "uf", field: "uf", label: "UF", type: "text" }
            ],
            defaultSort: analyticsConfig.defaultSortField ? [{ field: analyticsConfig.defaultSortField, dir: analyticsConfig.defaultSortDir || "asc" }] : [{ field: "uf", dir: "asc" }],
            limit: analyticsConfig.limit || 1000,
            executionMode: analyticsConfig.executionMode || "auto",
            audit: {
              enabled: !(analyticsConfig.datasetAudit && analyticsConfig.datasetAudit.enabled === false),
              includeCacheHits: !(analyticsConfig.datasetAudit && analyticsConfig.datasetAudit.includeCacheHits === false)
            },
            cache: { ttlSeconds: analyticsConfig.cacheTtlSeconds || 900 }
          }
        ],
        views: views
      }
    };
  }

  function runAnalyticsDataset(data) {
    const parameters = data && data.parameters || {};
    const statusFilter = String(parameters.status || "").trim().toLowerCase();
    const ufFilter = String(parameters.uf || "").trim().toLowerCase();
    const rows = [
      { uf: "CE", status: "Ativo", limite_credito_sum: 7000, total_clientes: 1 },
      { uf: "RJ", status: "Inativo", limite_credito_sum: 4200, total_clientes: 1 },
      { uf: "SP", status: "Ativo", limite_credito_sum: 12000, total_clientes: 2 }
    ].filter(function(item) {
      const matchesStatus = !statusFilter || String(item.status || "").toLowerCase().indexOf(statusFilter) >= 0;
      const matchesUf = !ufFilter || String(item.uf || "").toLowerCase().indexOf(ufFilter) >= 0;
      return matchesStatus && matchesUf;
    });

    return {
      data: rows,
      total: rows.length,
      datasetId: String(data && data.datasetId || "principal"),
      generatedAt: new Date().toISOString(),
      columns: [
        { field: "uf", id: "uf", title: "UF", label: "UF", type: "string", role: "dimension" },
        { field: "status", id: "status", title: "Status", label: "Status", type: "string", role: "dimension" },
        { field: "limite_credito_sum", id: "limite_credito_sum", title: "Limite de credito", label: "Limite de credito", type: "decimal", role: "measure", aggregate: "sum", format: "c2" },
        { field: "total_clientes", id: "total_clientes", title: "Clientes", label: "Clientes", type: "integer", role: "measure", aggregate: "count", format: "n0" }
      ],
      _runtime: {
        analyticsCache: {
          status: "ready"
        }
      }
    };
  }

  function buildReportPreview(data) {
    const reportConfig = Object.assign({
      sourceType: "operational",
      documentKind: "management",
      groupField: "",
      groups: [],
      totalField: "",
      sortField: "",
      sortDir: "asc",
      limit: 200,
      authenticity: {
        enabled: false,
        footerLabel: "Codigo de autenticidade",
        verificationPath: "report-authenticity.html",
        storage: {
          storeCanonicalPayload: true,
          storeExportArtifact: false
        }
      },
      outputs: {
        html: true,
        print: true,
        pdf: true,
        pdfBrowser: true,
        excel: true,
        csv: true
      }
    }, data && data.reportConfig || {});
    const screenId = String(data && data.screenId || "relatorios.clientes").trim() || "relatorios.clientes";
    const title = String(data && data.programTitle || "Relatorio").trim() || "Relatorio";
    const version = String(data && data.version || "1.0.0").trim() || "1.0.0";
    const entityCode = String(data && data.builderEntityCode || "cliente").trim() || "cliente";
    const sourceType = String(reportConfig.sourceType || "operational").toLowerCase() === "analytic" ? "analytic" : "operational";
    const isSpecial = ["danfe", "dacte", "boleto", "label", "etiqueta"].indexOf(String(reportConfig.documentKind || "").toLowerCase()) >= 0;
    return {
      screenId: screenId,
      pageType: "report",
      program: {
        id: String(data && data.programCode || "cd1001").trim() || "cd1001",
        title: title,
        module: String(data && data.module || "relatorios").trim() || "relatorios",
        version: version,
        subtitle: "Preview local de relatorio",
        icon: "file",
        screenId: screenId
      },
      permissions: {
        read: true,
        export: true
      },
      runtime: {
        entityCode: entityCode,
        programId: String(data && data.programCode || "cd1001").trim() || "cd1001",
        mode: "report"
      },
      dataSource: {
        api: {
          schema: { endpointId: "reports.schema" },
          run: { endpointId: "reports.run" },
          export: { endpointId: "reports.export" }
        }
      },
      report: {
        version: "1.0",
        classification: {
          documentProfile: isSpecial ? "special" : "general",
          documentKind: reportConfig.documentKind || "management"
        },
        endpoints: {
          schema: { endpointId: "reports.schema" },
          run: { endpointId: "reports.run" },
          export: { endpointId: "reports.export" }
        },
        source: sourceType === "analytic" ? {
          type: "analytic",
          analyticsScreenId: String(reportConfig.analyticsScreenId || "analytics.clientes"),
          analyticsDatasetId: String(reportConfig.analyticsDatasetId || "clientes-uf-status")
        } : {
          type: "operational",
          entityCode: entityCode
        },
        query: {
          fields: sourceType === "analytic" ? [] : [
            { field: "nome", label: "Nome" },
            { field: "uf", label: "UF" },
            { field: "status", label: "Status" },
            { field: "limite_credito", label: "Limite de credito", type: "decimal", format: "c2", align: "right", totalable: reportConfig.totalField === "limite_credito" }
          ],
          parameters: sourceType === "analytic" ? [
            { id: "status", field: "status", label: "Status", type: "text", operator: "contains" }
          ] : [
            { id: "status", field: "status", label: "Status", type: "text", operator: "contains" },
            { id: "uf", field: "uf", label: "UF", type: "text", operator: "contains" }
          ],
          filters: [],
          sort: reportConfig.sortField ? [{ field: reportConfig.sortField, dir: reportConfig.sortDir || "asc" }] : [],
          limit: reportConfig.limit || 200
        },
        layout: {
          title: title,
          subtitle: "Preview local de relatorio",
          groupField: sourceType === "analytic" ? "" : (reportConfig.groupField || ""),
          groups: sourceType === "analytic" ? [] : (reportConfig.groups || []),
          footerText: "",
          blocks: [
            { id: "header", type: "header" },
            { id: "summary", type: "summary" },
            { id: "table", type: "table" },
            sourceType !== "analytic" && (reportConfig.groupField || (reportConfig.groups || []).length) ? { id: "group", type: "group" } : null,
            { id: "totals", type: "totals" },
            { id: "footer", type: "footer" }
          ].filter(Boolean)
        },
        authenticity: reportConfig.authenticity || {},
        outputs: reportConfig.outputs || {}
      }
    };
  }

  function buildSpecialDocumentPreview(data) {
    const config = Object.assign({
      sourceType: "operational",
      documentKind: "danfe",
      analyticsScreenId: "",
      analyticsDatasetId: "",
      title: "",
      subtitle: "",
      notes: "",
      outputs: {
        html: true,
        pdf: true
      }
    }, data && data.specialDocumentConfig || {});
    const screenId = String(data && data.screenId || "documentos.especiais-base").trim() || "documentos.especiais-base";
    return {
      screenId: screenId,
      pageType: "special_document",
      program: {
        id: String(data && data.programCode || "dc1001").trim() || "dc1001",
        title: String(data && data.programTitle || "Documento especial").trim() || "Documento especial",
        module: String(data && data.module || "documentos").trim() || "documentos",
        version: String(data && data.version || "1.0.0").trim() || "1.0.0",
        subtitle: config.subtitle || "Preview local de documento especial",
        icon: "file",
        screenId: screenId
      },
      dataSource: {
        api: {
          schema: { endpointId: "specialDocuments.schema" },
          render: { endpointId: "specialDocuments.render" },
          export: { endpointId: "specialDocuments.export" }
        }
      },
      specialDocument: {
        classification: {
          documentProfile: "special",
          documentKind: config.documentKind || "danfe"
        },
        renderEngine: "native",
        endpoints: {
          schema: { endpointId: "specialDocuments.schema" },
          render: { endpointId: "specialDocuments.render" },
          export: { endpointId: "specialDocuments.export" }
        },
        source: config.sourceType === "analytic" ? {
          type: "analytic",
          analyticsScreenId: String(config.analyticsScreenId || "analytics.clientes"),
          analyticsDatasetId: String(config.analyticsDatasetId || "clientes-uf-status")
        } : {
          type: "operational",
          entityCode: String(data && data.builderEntityCode || "cliente")
        },
        parameters: [
          { id: "status", field: "status", label: "Status", type: "enum", operator: "eq", options: [{ value: "ATIVO", text: "Ativo" }, { value: "INATIVO", text: "Inativo" }] },
          { id: "uf", field: "uf", label: "UF", type: "text", operator: "eq" }
        ],
        layout: {
          title: config.title || String(data && data.programTitle || "Documento especial"),
          subtitle: config.subtitle || "Preview local de documento especial",
          notes: config.notes || ""
        },
        outputs: config.outputs || { html: true, pdf: true }
      }
    };
  }

  function runReportDataset(url, data) {
    const screenIdMatch = String(url || "").match(/^\/api\/runtime\/screens\/([^/]+)\/endpoints\/reports\.run$/);
    const screenId = decodeURIComponent(screenIdMatch && screenIdMatch[1] || "relatorios.clientes-operacional");
    const parameters = data && data.parameters || {};
    if (screenId.indexOf("analitico") >= 0) {
      const analytics = runAnalyticsDataset({
        datasetId: "clientes-uf-status",
        parameters: parameters
      });
      const rows = Array.isArray(analytics.data) ? analytics.data : [];
      return {
        screenId: screenId,
        reportId: "relatorio-clientes-analitico",
        title: "Relatorio analitico por UF",
        sourceType: "analytic",
        rows: rows,
        columns: analytics.columns,
        groups: Object.keys(rows.reduce(function(acc, row) {
          const key = String(row.uf || "");
          const existing = acc[key] || { key: key, label: key, rowCount: 0 };
          existing.rowCount += 1;
          acc[key] = existing;
          return acc;
        }, {})).map(function(key) {
          return rows.reduce(function(acc, row) {
            if (String(row.uf || "") === key) {
              acc.rowCount += 1;
            }
            return acc;
          }, { key: key, label: key, rowCount: 0 });
        }),
        total: rows.length,
        generatedAt: new Date().toISOString()
      };
    }
    const statusFilter = String(parameters.status || "").trim().toLowerCase();
    const ufFilter = String(parameters.uf || "").trim().toLowerCase();
    const rows = [
      { nome: "Ana Comercio LTDA", uf: "CE", status: "Ativo", limite_credito: 7000 },
      { nome: "Rio Norte SA", uf: "RJ", status: "Inativo", limite_credito: 4200 },
      { nome: "Sol Paulista ME", uf: "SP", status: "Ativo", limite_credito: 5400 },
      { nome: "Delta Paulista LTDA", uf: "SP", status: "Ativo", limite_credito: 6600 }
    ].filter(function(item) {
      const matchesStatus = !statusFilter || String(item.status || "").toLowerCase().indexOf(statusFilter) >= 0;
      const matchesUf = !ufFilter || String(item.uf || "").toLowerCase().indexOf(ufFilter) >= 0;
      return matchesStatus && matchesUf;
    });
    return {
      screenId: screenId,
      reportId: "relatorio-clientes-operacional",
      title: "Relatorio operacional de clientes",
      sourceType: "operational",
      rows: rows,
      columns: [
        { field: "nome", title: "Nome", type: "string" },
        { field: "uf", title: "UF", type: "string" },
        { field: "status", title: "Status", type: "string" },
        { field: "limite_credito", title: "Limite de credito", type: "decimal", format: "c2" }
      ],
      groups: [],
      total: rows.length,
      generatedAt: new Date().toISOString()
    };
  }

  function exportReportDataset(url, data) {
    const result = runReportDataset(String(url || "").replace(/reports\.export$/, "reports.run"), data);
    const format = String(data && data.format || "csv").toLowerCase();
    if (format === "pdf") {
      return {
        ok: true,
        format: "pdf",
        fileName: String(result.reportId || "relatorio") + ".pdf",
        contentType: "application/pdf",
        contentBase64: global.btoa("%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF")
      };
    }
    if (format === "excel") {
      return {
        ok: true,
        format: "excel",
        fileName: String(result.reportId || "relatorio") + ".xlsx",
        contentType: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        contentBase64: global.btoa(unescape(encodeURIComponent(JSON.stringify(result))))
      };
    }
    const columns = Array.isArray(result.columns) ? result.columns : [];
    const rows = Array.isArray(result.rows) ? result.rows : [];
    const content = "\uFEFF" + [columns.map(function(column) {
      return "\"" + String(column.title || column.field || "").replace(/"/g, "\"\"") + "\"";
    }).join(";")].concat(rows.map(function(row) {
      return columns.map(function(column) {
        return "\"" + String(row[column.field] == null ? "" : row[column.field]).replace(/"/g, "\"\"") + "\"";
      }).join(";");
    })).join("\r\n");
    return {
      ok: true,
      format: "csv",
      fileName: String(result.reportId || "relatorio") + ".csv",
      contentType: "text/csv;charset=utf-8",
      contentBase64: global.btoa(unescape(encodeURIComponent(content)))
    };
  }

  function buildDdlInspection(data) {
    const ddl = String(data && data.ddl || "");
    const tableMatch = ddl.match(/create\s+table\s+(?:if\s+not\s+exists\s+)?(?:(?:"?([a-z0-9_]+)"?)\.)?"?([a-z0-9_]+)"?/i);
    const tableName = tableMatch && tableMatch[2] ? tableMatch[2].toLowerCase() : "produto";
    const entityCode = String(data && data.entityCode || tableName).trim() || tableName;
    const entityName = String(data && data.entityName || humanize(tableName)).trim() || humanize(tableName);
    const fields = [
      {
        id: 0,
        code: "id",
        label: "Id",
        dataType: "integer",
        columnName: "id",
        originalCode: "id",
        originalColumnName: "id",
        required: true,
        primaryKey: true,
        readonlyField: true,
        options: {}
      },
      {
        id: 0,
        code: "c_nome",
        label: "Nome",
        dataType: "string",
        columnName: "c_nome",
        originalCode: "c_nome",
        originalColumnName: "c_nome",
        length: 120,
        required: true,
        primaryKey: false,
        readonlyField: false,
        options: {}
      }
    ];
    return {
      table: { schema: "public", tableName: tableName, qualifiedName: "public." + tableName },
      classification: { code: "support", label: "Tabela de apoio/cadastro simples", structureType: "main" },
      diagnostics: [{ level: "info", message: "Script analisado sem executar comandos no schema real." }],
      entityDraft: {
        code: entityCode,
        name: entityName,
        entityType: "persistence",
        tableName: tableName,
        originalTableName: tableName,
        createPhysicalTable: false,
        allowTableRename: false,
        allowColumnRename: false,
        dropRemovedColumns: false,
        skipStructureValidation: true,
        fields: fields,
        uniqueKeys: [],
        rules: []
      },
      programDraft: {
        programCode: String(data && data.programCode || "").trim(),
        programTitle: String(data && data.programTitle || entityName).trim(),
        module: String(data && data.module || "").trim(),
        pageType: "crud",
        builderEntityCode: entityCode,
        screenId: String(data && data.screenId || ("cadastros." + tableName.replace(/_/g, "-"))).trim(),
        version: String(data && data.version || "1.0.0").trim(),
        subtitle: "Gerado a partir de script de tabela",
        icon: "file-txt",
        permissionPrefix: String(data && data.programCode || "").trim(),
        allowCreate: true,
        allowUpdate: true,
        allowDelete: true,
        changeSummary: "Rascunho gerado por importacao de script SQL/DDL."
      },
      source: "ddl"
    };
  }

  function humanize(value) {
    return String(value || "")
      .split("_")
      .filter(Boolean)
      .map(function(part) {
        return part.charAt(0).toUpperCase() + part.slice(1);
      })
      .join(" ");
  }

  function buildAiSession(sessionId, draft) {
    return {
      sessionId: sessionId,
      purpose: "program_builder",
      catalogHash: "demo-catalog",
      catalogVersion: "demo",
      status: "active",
      expiresAt: new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString(),
      lastSeenAt: new Date().toISOString(),
      currentDraft: draft || {},
      currentDiagnostics: []
    };
  }

  function buildAiDraftFromText(text) {
    const slug = String(text || "produto").toLowerCase().indexOf("cliente") >= 0 ? "cliente_ia" : "produto_ia";
    const name = humanize(slug);
    return {
      entityDraft: {
        code: slug,
        name: name,
        entityType: "persistence",
        tableName: "t900",
        fields: [
          { code: "id", label: "ID", dataType: "integer", primaryKey: true, required: true },
          { code: "c_descr", label: "Descricao", dataType: "string", length: 160, required: true },
          { code: "log_ativo", label: "Ativo", dataType: "boolean", required: false }
        ],
        rules: []
      },
      programDraft: {
        pageType: "crud",
        module: "cadastros",
        programCode: "cd0900",
        programTitle: name,
        screenId: "cadastros." + slug.replace(/_/g, "-"),
        version: "1.0.0"
      }
    };
  }

  document.addEventListener("DOMContentLoaded", function() {
    const app = new global.ProgramBuilder({
      root: "#program-builder-root",
      httpClient: new ProgramBuilderDemoHttpClient()
    });
    app.init().then(function() {
      const entity = global.ProgramBuilderEmbeddedData && global.ProgramBuilderEmbeddedData.entityDefinition;
      const program = global.ProgramBuilderEmbeddedData && global.ProgramBuilderEmbeddedData.programDefinition;
      if (entity) {
        app.populateEntityForm(global.CrudUtils.clone(entity));
        app.selectPropertyNode("field", { index: 0 });
      }
      if (program) {
        app.populateProgramForm(global.CrudUtils.clone(program));
      }
      global.programBuilderDemoApp = app;
    });
  });
})(window);
