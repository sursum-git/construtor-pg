(function(global) {
  "use strict";

  const demoRows = [
    {
      id: 11,
      tenantId: "tenant-a",
      userId: "admin",
      sessionId: "sess-report-1",
      screenId: "relatorios.clientes-operacional",
      datasetId: "relatorio-clientes-operacional",
      viewId: "csv",
      executionMode: "operational",
      resultSource: "report_run",
      filterFingerprint: "report-fp-001",
      rowCount: 4,
      totalCount: 4,
      filters: null,
      parameters: { status: "ATIVO" },
      sort: [{ field: "nome", dir: "asc" }],
      requestPayload: { format: "csv" },
      resultColumns: [{ field: "nome" }, { field: "uf" }, { field: "status" }],
      resultRows: [{ nome: "Ana Comercio LTDA", uf: "CE", status: "ATIVO" }],
      metadata: { auditContext: "report", reportId: "relatorio-clientes-operacional", sourceType: "operational" },
      errorMessage: null,
      consultedAt: "2026-05-29T03:10:00Z"
    },
    {
      id: 12,
      tenantId: "tenant-a",
      userId: "analista",
      sessionId: "sess-report-2",
      screenId: "relatorios.clientes-analitico",
      datasetId: "relatorio-clientes-analitico",
      viewId: "html",
      executionMode: "analytic",
      resultSource: "report_run",
      filterFingerprint: "report-fp-002",
      rowCount: 3,
      totalCount: 3,
      filters: null,
      parameters: { status: "ATIVO" },
      sort: [{ field: "uf", dir: "asc" }],
      requestPayload: { format: "html" },
      resultColumns: [{ field: "uf" }, { field: "clientes" }],
      resultRows: [{ uf: "CE", clientes: 2 }],
      metadata: { auditContext: "report", reportId: "relatorio-clientes-analitico", sourceType: "analytic" },
      errorMessage: null,
      consultedAt: "2026-05-29T03:18:00Z"
    }
  ];

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function ReportAuditAdminDemoHttpClient() {
  }

  ReportAuditAdminDemoHttpClient.prototype.request = function(options) {
    const method = String(options && options.method || "GET").toUpperCase();
    const url = String(options && options.url || "");
    const data = options && options.data || {};
    if (method === "GET" && url === "/api/admin/report-audit/bootstrap") {
      return Promise.resolve({
        enabled: true,
        filterOptions: {
          tenantIds: ["tenant-a"],
          userIds: ["admin", "analista"],
          screenIds: ["relatorios.clientes-operacional", "relatorios.clientes-analitico"],
          datasetIds: ["relatorio-clientes-operacional", "relatorio-clientes-analitico"],
          resultSources: ["report_run"],
          reportIds: ["relatorio-clientes-operacional", "relatorio-clientes-analitico"]
        },
        summary: {
          total: demoRows.length,
          loaded: demoRows.length,
          bySource: { report_run: 2 },
          byReport: { "relatorio-clientes-operacional": 1, "relatorio-clientes-analitico": 1 }
        }
      });
    }
    if (method === "GET" && url === "/api/admin/report-audit/entries") {
      let rows = demoRows.slice();
      ["tenantId", "userId", "screenId", "resultSource"].forEach(function(key) {
        const expected = String(data[key] || "").trim();
        if (expected) {
          rows = rows.filter(function(item) {
            return String(item[key] || "") === expected;
          });
        }
      });
      if (data.reportId) {
        rows = rows.filter(function(item) {
          return String(item.metadata && item.metadata.reportId || "") === String(data.reportId || "");
        });
      }
      if (data.dateFrom) {
        rows = rows.filter(function(item) { return String(item.consultedAt || "") >= String(data.dateFrom) + "T00:00:00"; });
      }
      if (data.dateTo) {
        rows = rows.filter(function(item) { return String(item.consultedAt || "") <= String(data.dateTo) + "T23:59:59"; });
      }
      const limit = Math.max(1, Math.min(300, Number(data.limit || 120) || 120));
      rows = rows.slice(0, limit);
      return Promise.resolve({
        enabled: true,
        items: clone(rows),
        total: rows.length,
        summary: {
          total: rows.length,
          loaded: rows.length,
          bySource: { report_run: rows.length },
          byReport: rows.reduce(function(acc, item) {
            const reportId = String(item.metadata && item.metadata.reportId || "");
            acc[reportId] = (acc[reportId] || 0) + 1;
            return acc;
          }, {})
        }
      });
    }
    return Promise.reject(new Error("Endpoint demo de auditoria de relatorios nao mapeado: " + method + " " + url));
  };

  global.ReportAuditAdminDemoHttpClient = ReportAuditAdminDemoHttpClient;
})(window);
