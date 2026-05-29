(function(global) {
  "use strict";

  const demoRows = [
    {
      id: 1,
      tenantId: "tenant-a",
      userId: "admin",
      sessionId: "sess-1",
      screenId: "analytics.clientes",
      datasetId: "clientes-uf-status",
      viewId: "grid",
      executionMode: "auto",
      resultSource: "cache_hit",
      filterFingerprint: "fp-001",
      rowCount: 3,
      totalCount: 3,
      filters: { logic: "and", filters: [{ field: "status", operator: "eq", value: "ATIVO" }] },
      parameters: { status: "ATIVO" },
      sort: [{ field: "uf", dir: "asc" }],
      requestPayload: { datasetId: "clientes-uf-status", viewId: "grid" },
      resultColumns: [{ field: "uf" }, { field: "clientes" }],
      resultRows: [{ uf: "CE", clientes: 2 }, { uf: "SP", clientes: 1 }],
      metadata: { aggregated: true },
      errorMessage: null,
      consultedAt: "2026-05-29T02:40:00Z"
    },
    {
      id: 2,
      tenantId: "tenant-a",
      userId: "analista",
      sessionId: "sess-2",
      screenId: "analytics.clientes",
      datasetId: "clientes-uf-status",
      viewId: "chart",
      executionMode: "live",
      resultSource: "live",
      filterFingerprint: "fp-002",
      rowCount: 2,
      totalCount: 2,
      filters: null,
      parameters: { uf: "SP" },
      sort: [{ field: "valor_total_sum", dir: "desc" }],
      requestPayload: { datasetId: "clientes-uf-status", viewId: "chart" },
      resultColumns: [{ field: "uf" }, { field: "valor_total_sum" }],
      resultRows: [{ uf: "SP", valor_total_sum: 12000 }],
      metadata: { aggregated: true },
      errorMessage: null,
      consultedAt: "2026-05-29T02:44:00Z"
    },
    {
      id: 3,
      tenantId: "tenant-b",
      userId: "admin",
      sessionId: "sess-3",
      screenId: "analytics.faturamento",
      datasetId: "faturamento-mensal",
      viewId: "dashboard",
      executionMode: "cached",
      resultSource: "materialize",
      filterFingerprint: "fp-003",
      rowCount: 1,
      totalCount: 1,
      filters: null,
      parameters: { mes: "2026-05" },
      sort: null,
      requestPayload: { datasetId: "faturamento-mensal", sync: true },
      resultColumns: [{ field: "mes" }, { field: "valor_total_sum" }],
      resultRows: [{ mes: "2026-05", valor_total_sum: 54000 }],
      metadata: { aggregated: true, rowsTruncated: false },
      errorMessage: null,
      consultedAt: "2026-05-29T02:48:00Z"
    }
  ];

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function AnalyticsAuditAdminDemoHttpClient() {
    this.base = new global.CrudHttpClient({ allowLocalFallback: true });
  }

  AnalyticsAuditAdminDemoHttpClient.prototype.request = function(options) {
    const method = String(options && options.method || "GET").toUpperCase();
    const url = String(options && options.url || "");
    const data = options && options.data || {};
    if (method === "GET" && url === "/api/admin/analytics-audit/bootstrap") {
      return Promise.resolve({
        enabled: true,
        filterOptions: {
          tenantIds: ["tenant-a", "tenant-b"],
          userIds: ["admin", "analista"],
          screenIds: ["analytics.clientes", "analytics.faturamento"],
          datasetIds: ["clientes-uf-status", "faturamento-mensal"],
          resultSources: ["cache_hit", "live", "materialize"]
        },
        summary: {
          total: demoRows.length,
          loaded: demoRows.length,
          bySource: { cache_hit: 1, live: 1, materialize: 1 }
        }
      });
    }
    if (method === "GET" && url === "/api/admin/analytics-audit/entries") {
      let rows = demoRows.slice();
      ["tenantId", "userId", "screenId", "datasetId", "resultSource"].forEach(function(key) {
        const expected = String(data[key] || "").trim();
        if (expected) {
          rows = rows.filter(function(item) {
            return String(item[key] || "") === expected;
          });
        }
      });
      if (data.dateFrom) {
        rows = rows.filter(function(item) { return String(item.consultedAt || "") >= String(data.dateFrom) + "T00:00:00"; });
      }
      if (data.dateTo) {
        rows = rows.filter(function(item) { return String(item.consultedAt || "") <= String(data.dateTo) + "T23:59:59"; });
      }
      const limit = Math.max(1, Math.min(300, Number(data.limit || 120) || 120));
      rows = rows.slice(0, limit);
      const bySource = {};
      rows.forEach(function(item) {
        bySource[item.resultSource] = (bySource[item.resultSource] || 0) + 1;
      });
      return Promise.resolve({
        enabled: true,
        items: clone(rows),
        total: rows.length,
        summary: {
          total: rows.length,
          loaded: rows.length,
          bySource: bySource
        }
      });
    }
    return this.base.request(options);
  };

  global.AnalyticsAuditAdminDemoHttpClient = AnalyticsAuditAdminDemoHttpClient;
})(window);
