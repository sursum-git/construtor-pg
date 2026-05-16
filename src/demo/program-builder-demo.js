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
    if (url === "/api/admin/program-builder/preview" && method === "POST") {
      return Promise.resolve({
        definition: {
          screenId: "cadastros.clientes",
          pageType: "crud",
          dataModel: { fields: {} },
          crud: { grid: { columns: [] }, filter: { fields: [] }, form: { fields: [] } }
        },
        diagnostics: []
      });
    }
    return Promise.resolve({});
  };

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
