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
