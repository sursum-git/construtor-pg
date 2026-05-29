(function(global) {
  "use strict";

  class DemoMockHttpClient {
    constructor(options) {
      const settings = options || {};
      const storageSuffix = settings.storageSuffix ? "-" + String(settings.storageSuffix).replace(/[^A-Za-z0-9_-]+/g, "-") : "";
      this.recordsStorageKey = "crud-demo-clientes-records-v4" + storageSuffix;
      this.layoutsStorageKey = "crud-demo-clientes-layouts-v2" + storageSuffix;
      this.helpSeenStorageKey = "crud-demo-help-seen-v1" + storageSuffix;
      this.runtimeStorageKey = "crud-demo-runtime-v1" + storageSuffix;
      this.adminStorageKey = "crud-demo-admin-runtime-v1" + storageSuffix;
      this.processJobsStorageKey = "crud-demo-process-jobs-v1" + storageSuffix;
      this.externalApiCrudProductsStorageKey = "crud-demo-external-api-crud-products-v1" + storageSuffix;
      this.records = this.loadInitialRecords();
      this.nextId = this.records.reduce(function(max, record) {
        return Math.max(max, Number(record.id) || 0);
      }, 0) + 1;
      this.externalApiCrudProducts = this.loadInitialExternalApiCrudProducts();
      this.nextExternalApiCrudProductId = this.externalApiCrudProducts.reduce(function(max, record) {
        return Math.max(max, Number(record.id) || 0);
      }, 0) + 1;

      const layoutState = this.loadJson(this.layoutsStorageKey) || {};
      this.nextLayoutId = layoutState.nextLayoutId || 1;
      this.nextSortId = layoutState.nextSortId || 1;
      this.nextGroupId = layoutState.nextGroupId || 1;
      this.nextFilterId = layoutState.nextFilterId || 1;
      this.nextMobileTemplateId = layoutState.nextMobileTemplateId || 1;
      this.savedLayouts = layoutState.savedLayouts || [];
      this.activeLayoutId = layoutState.activeLayoutId || null;
      this.savedSorts = layoutState.savedSorts || [];
      this.activeSortId = layoutState.activeSortId || null;
      this.savedGroups = layoutState.savedGroups || [];
      this.activeGroupId = layoutState.activeGroupId || null;
      this.savedFilters = layoutState.savedFilters || [];
      this.activeFilterId = layoutState.activeFilterId || null;
      this.savedMobileTemplates = layoutState.savedMobileTemplates || [];
      this.activeMobileTemplateId = layoutState.activeMobileTemplateId || null;
      this.helpSeen = this.loadJson(this.helpSeenStorageKey) || [];
      this.runtimeState = this.loadJson(this.runtimeStorageKey) || {
        locks: [],
        messages: [],
        revokedSessions: []
      };
      this.processJobs = this.loadJson(this.processJobsStorageKey) || [];
      this.userId = settings.userId || this.resolveRuntimeUserId();
      this.sessionId = settings.sessionId || this.resolveRuntimeSessionId();
      this.tenantId = settings.tenantId || this.resolveRuntimeTenantId();
    }

    loadInitialRecords() {
      const stored = this.loadJson(this.recordsStorageKey);
      if (Array.isArray(stored) && stored.length) {
        const normalized = this.applyDefaultPhones(stored);
        this.saveJson(this.recordsStorageKey, normalized);
        return normalized;
      }

      const records = this.getDefaultRecords();
      this.saveJson(this.recordsStorageKey, records);
      return records;
    }

    getDefaultRecords() {
      return [
        { id: 1, nome: "Acme Comercio", email: "contato@acme.test", telefone: "(85) 98888-1001", status: "ATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "FORTALEZA", razao_social: "Acme Comercio Ltda", cnpj: "12.345.678/0001-90", data_cadastro: "2026-01-10", valor_total: 15420.5, qtde_pedidos: 12, observacao: "Cliente recorrente." },
        { id: 2, nome: "Beta Servicos", email: "financeiro@beta.test", telefone: "(11) 97777-2002", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "SAO_PAULO", razao_social: "Beta Servicos Ltda", cnpj: "22.345.678/0001-90", data_cadastro: "2026-01-18", valor_total: 8450, qtde_pedidos: 5, observacao: "" },
        { id: 3, nome: "Casa Norte", email: "compras@casanorte.test", telefone: "(85) 96666-3003", status: "INATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "CAUCAIA", razao_social: "Casa Norte Comercio Ltda", cnpj: "32.345.678/0001-90", data_cadastro: "2026-02-02", valor_total: 2100, qtde_pedidos: 2, observacao: "Cadastro pausado." },
        { id: 4, nome: "Delta Atacado", email: "operacoes@delta.test", telefone: "(21) 95555-4004", status: "ATIVO", tipo_pessoa: "PJ", uf: "RJ", cidade: "RIO_DE_JANEIRO", razao_social: "Delta Atacado Ltda", cnpj: "42.345.678/0001-90", data_cadastro: "2026-02-14", valor_total: 32200.75, qtde_pedidos: 18, observacao: "" },
        { id: 5, nome: "Escola Horizonte", email: "secretaria@horizonte.test", telefone: "(11) 94444-5005", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "CAMPINAS", razao_social: "Escola Horizonte Ltda", cnpj: "52.345.678/0001-90", data_cadastro: "2026-02-22", valor_total: 4780.3, qtde_pedidos: 4, observacao: "" },
        { id: 6, nome: "Farma Popular", email: "ti@farmapopular.test", telefone: "(85) 93333-6006", status: "INATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "SOBRAL", razao_social: "Farma Popular Ltda", cnpj: "62.345.678/0001-90", data_cadastro: "2026-03-03", valor_total: 975.9, qtde_pedidos: 1, observacao: "" },
        { id: 7, nome: "Grupo Solar", email: "adm@gruposolar.test", telefone: "(21) 92222-7007", status: "ATIVO", tipo_pessoa: "PJ", uf: "RJ", cidade: "NITEROI", razao_social: "Grupo Solar Ltda", cnpj: "72.345.678/0001-90", data_cadastro: "2026-03-11", valor_total: 18900, qtde_pedidos: 9, observacao: "" },
        { id: 8, nome: "Hotel Central", email: "reservas@central.test", telefone: "(85) 91111-8008", status: "ATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "FORTALEZA", razao_social: "Hotel Central Ltda", cnpj: "82.345.678/0001-90", data_cadastro: "2026-03-19", valor_total: 7600, qtde_pedidos: 6, observacao: "" },
        { id: 9, nome: "Industria Vale", email: "suprimentos@vale.test", telefone: "(13) 90000-9009", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "SANTOS", razao_social: "Industria Vale Ltda", cnpj: "92.345.678/0001-90", data_cadastro: "2026-04-04", valor_total: 44100, qtde_pedidos: 21, observacao: "Contrato anual." },
        { id: 10, nome: "Jardins Office", email: "contato@jardinsoffice.test", telefone: "(24) 98989-1010", status: "INATIVO", tipo_pessoa: "PF", uf: "RJ", cidade: "PETROPOLIS", razao_social: "", cnpj: "", data_cadastro: "2026-04-12", valor_total: 1200, qtde_pedidos: 2, observacao: "" },
        { id: 11, nome: "Kronos Logistica", email: "logistica@kronos.test", telefone: "(88) 97878-1111", status: "ATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "JUAZEIRO_NORTE", razao_social: "Kronos Logistica Ltda", cnpj: "11.345.678/0001-90", data_cadastro: "2026-04-20", valor_total: 16780, qtde_pedidos: 8, observacao: "" },
        { id: 12, nome: "Litoral Foods", email: "pedidos@litoralfoods.test", telefone: "(11) 96767-1212", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "SAO_PAULO", razao_social: "Litoral Foods Ltda", cnpj: "21.345.678/0001-90", data_cadastro: "2026-04-28", valor_total: 25300.4, qtde_pedidos: 11, observacao: "" }
      ];
    }

    applyDefaultPhones(records) {
      const phones = {
        1: "(85) 98888-1001",
        2: "(11) 97777-2002",
        3: "(85) 96666-3003",
        4: "(21) 95555-4004",
        5: "(11) 94444-5005",
        6: "(85) 93333-6006",
        7: "(21) 92222-7007",
        8: "(85) 91111-8008",
        9: "(13) 90000-9009",
        10: "(24) 98989-1010",
        11: "(88) 97878-1111",
        12: "(11) 96767-1212"
      };
      return global.CrudUtils.ensureArray(records).map(function(record) {
        if (!record || record.telefone) {
          return record;
        }
        const id = Number(record.id);
        return Object.assign({}, record, {
          telefone: phones[id] || ""
        });
      });
    }

    getExternalApiProductsSeed() {
      return [
        { id: 101, attributes: { nome: "Mouse ergonomico", categoria: "Perifericos", status: "ATIVO", preco: 89.9, atualizado_em: "2026-05-01T09:30:00Z" } },
        { id: 102, attributes: { nome: "Teclado mecanico", categoria: "Perifericos", status: "ATIVO", preco: 249.5, atualizado_em: "2026-05-02T14:15:00Z" } },
        { id: 103, attributes: { nome: "Monitor 27", categoria: "Video", status: "INATIVO", preco: 1299.99, atualizado_em: "2026-05-03T11:00:00Z" } },
        { id: 104, attributes: { nome: "Dock USB-C", categoria: "Acessorios", status: "ATIVO", preco: 319, atualizado_em: "2026-05-04T08:45:00Z" } }
      ];
    }

    getExternalApiCrudProductsSeed() {
      return [
        { id: 201, nome: "Produto API 1", ativo: true },
        { id: 202, nome: "Produto API 2", ativo: false },
        { id: 203, nome: "Produto API 3", ativo: true }
      ];
    }

    loadInitialExternalApiCrudProducts() {
      const stored = this.loadJson(this.externalApiCrudProductsStorageKey);
      if (Array.isArray(stored) && stored.length) {
        return stored;
      }
      const records = this.getExternalApiCrudProductsSeed();
      this.saveJson(this.externalApiCrudProductsStorageKey, records);
      return records;
    }

    persistExternalApiCrudProducts() {
      this.saveJson(this.externalApiCrudProductsStorageKey, this.externalApiCrudProducts);
    }

    listExternalApiProducts(data) {
      const items = this.getExternalApiProductsSeed().map(function(item) {
        return Object.assign({ id: item.id }, item.attributes || {});
      });
      const sort = global.CrudUtils.ensureArray(data && data.sort);
      if (sort.length) {
        items.sort(function(left, right) {
          const field = String(sort[0].field || "");
          const dir = String(sort[0].dir || "asc").toLowerCase() === "desc" ? -1 : 1;
          const resolve = function(item) {
            return item[field];
          };
          const leftValue = resolve(left);
          const rightValue = resolve(right);
          return (leftValue > rightValue ? 1 : leftValue < rightValue ? -1 : 0) * dir;
        });
      }
      return {
        data: items,
        total: items.length
      };
    }

    getExternalApiProduct(id) {
      const item = this.getExternalApiProductsSeed().find(function(entry) {
        return Number(entry.id) === Number(id);
      });
      if (!item) {
        throw global.CrudUtils.makeError("API_PRODUCT_NOT_FOUND", "Produto externo nao encontrado.", { id: id });
      }
      return Object.assign({ id: item.id }, item.attributes || {});
    }

    listExternalApiCrudProducts(data) {
      const items = global.CrudUtils.clone(this.externalApiCrudProducts);
      const sort = global.CrudUtils.ensureArray(data && data.sort);
      if (sort.length) {
        items.sort(function(left, right) {
          const field = String(sort[0].field || "");
          const dir = String(sort[0].dir || "asc").toLowerCase() === "desc" ? -1 : 1;
          const leftValue = left[field];
          const rightValue = right[field];
          return (leftValue > rightValue ? 1 : leftValue < rightValue ? -1 : 0) * dir;
        });
      }
      return {
        data: items,
        total: items.length
      };
    }

    getExternalApiCrudProduct(id) {
      const item = this.externalApiCrudProducts.find(function(entry) {
        return Number(entry.id) === Number(id);
      });
      if (!item) {
        throw global.CrudUtils.makeError("API_CRUD_PRODUCT_NOT_FOUND", "Produto da API nao encontrado.", { id: id });
      }
      return global.CrudUtils.clone(item);
    }

    createExternalApiCrudProduct(data) {
      const payload = data || {};
      const nome = String(payload.nome || "").trim();
      if (!nome) {
        throw global.CrudUtils.makeError("API_CRUD_PRODUCT_NAME_REQUIRED", "Nome e obrigatorio.");
      }
      const item = {
        id: this.nextExternalApiCrudProductId++,
        nome: nome,
        ativo: payload.ativo !== false
      };
      this.externalApiCrudProducts.push(item);
      this.persistExternalApiCrudProducts();
      return global.CrudUtils.clone(item);
    }

    updateExternalApiCrudProduct(id, data) {
      const index = this.externalApiCrudProducts.findIndex(function(entry) {
        return Number(entry.id) === Number(id);
      });
      if (index === -1) {
        throw global.CrudUtils.makeError("API_CRUD_PRODUCT_NOT_FOUND", "Produto da API nao encontrado.", { id: id });
      }
      const payload = data || {};
      const nome = String(payload.nome || "").trim();
      if (!nome) {
        throw global.CrudUtils.makeError("API_CRUD_PRODUCT_NAME_REQUIRED", "Nome e obrigatorio.");
      }
      const updated = Object.assign({}, this.externalApiCrudProducts[index], {
        nome: nome,
        ativo: payload.ativo === true
      });
      this.externalApiCrudProducts[index] = updated;
      this.persistExternalApiCrudProducts();
      return global.CrudUtils.clone(updated);
    }

    deleteExternalApiCrudProduct(id) {
      const index = this.externalApiCrudProducts.findIndex(function(entry) {
        return Number(entry.id) === Number(id);
      });
      if (index === -1) {
        throw global.CrudUtils.makeError("API_CRUD_PRODUCT_NOT_FOUND", "Produto da API nao encontrado.", { id: id });
      }
      this.externalApiCrudProducts.splice(index, 1);
      this.persistExternalApiCrudProducts();
      return { ok: true, deleted: true };
    }

    loadJson(key) {
      try {
        if (!global.localStorage) {
          return null;
        }
        const value = global.localStorage.getItem(key);
        return value ? JSON.parse(value) : null;
      } catch (_) {
        return null;
      }
    }

    saveJson(key, value) {
      try {
        if (global.localStorage) {
          global.localStorage.setItem(key, JSON.stringify(value));
        }
      } catch (_) {
        return false;
      }
      return true;
    }

    persistRecords() {
      this.saveJson(this.recordsStorageKey, this.records);
    }

    persistLayouts() {
      this.saveJson(this.layoutsStorageKey, {
        nextLayoutId: this.nextLayoutId,
        nextSortId: this.nextSortId,
        nextGroupId: this.nextGroupId,
        nextFilterId: this.nextFilterId,
        nextMobileTemplateId: this.nextMobileTemplateId,
        savedLayouts: this.savedLayouts,
        activeLayoutId: this.activeLayoutId,
        savedSorts: this.savedSorts,
        activeSortId: this.activeSortId,
        savedGroups: this.savedGroups,
        activeGroupId: this.activeGroupId,
        savedFilters: this.savedFilters,
        activeFilterId: this.activeFilterId,
        savedMobileTemplates: this.savedMobileTemplates,
        activeMobileTemplateId: this.activeMobileTemplateId
      });
    }

    persistHelpSeen() {
      this.saveJson(this.helpSeenStorageKey, this.helpSeen);
    }

    persistRuntimeState() {
      this.saveJson(this.runtimeStorageKey, this.runtimeState);
    }

    persistProcessJobs() {
      this.saveJson(this.processJobsStorageKey, this.processJobs);
    }

    resolveRuntimeUserId() {
      const params = new URLSearchParams(global.location && global.location.search || "");
      const queryUser = params.get("runtimeUserId") || params.get("demoUserId");
      if (queryUser) {
        this.saveJson("crud-demo-runtime-user", queryUser);
        return queryUser;
      }
      return this.loadJson("crud-demo-runtime-user") || "demo";
    }

    resolveRuntimeSessionId() {
      const stored = this.loadJson("crud-demo-runtime-session");
      if (stored) {
        return stored;
      }
      const value = "demo-sess-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 10);
      this.saveJson("crud-demo-runtime-session", value);
      return value;
    }

    resolveRuntimeTenantId() {
      const params = new URLSearchParams(global.location && global.location.search || "");
      const queryTenant = params.get("runtimeTenantId") || params.get("tenantId");
      if (queryTenant) {
        this.saveJson("crud-demo-runtime-tenant", queryTenant);
        return queryTenant;
      }
      return this.loadJson("crud-demo-runtime-tenant") || "default";
    }

    request(options) {
      const request = options || {};
      const method = (request.method || "GET").toUpperCase();
      const url = request.url || "";

      if (!url.startsWith("/api/")) {
        return this.loadJsonDocument(url).then((definition) => {
          if (definition.pageType === "crud" && definition.crud) {
            definition.crud.userLayout = this.buildUserLayout(definition.crud.userLayout);
          } else {
            definition.userLayout = this.buildUserLayout(definition.userLayout);
          }
          return definition;
        });
      }

      return new Promise((resolve, reject) => {
        window.setTimeout(() => {
          try {
            resolve(this.route(method, url, request.data || {}));
          } catch (error) {
            reject(error.payload || error);
          }
        }, 120);
      });
    }

    loadJsonDocument(url) {
      if (global.location && global.location.protocol === "file:") {
        const embeddedDocument = this.getEmbeddedDocument(url);
        if (embeddedDocument) {
          return Promise.resolve(embeddedDocument);
        }
      }

      return fetch(url).then((response) => response.json()).catch((error) => {
        if (global.location && global.location.protocol === "file:" && global.CrudUtils && global.CrudUtils.readLocalJson) {
          return global.CrudUtils.readLocalJson(url).catch(() => {
            const fallback = this.getEmbeddedDocument(url);
            if (fallback) {
              return fallback;
            }
            throw error;
          });
        }
        throw error;
      });
    }

    getEmbeddedDocument(url) {
      const embedded = global.CrudDemoEmbedded || {};
      const normalizedUrl = String(url || "").replace(/\\/g, "/");
      if (normalizedUrl.endsWith("examples/clientes.crud.json") && embedded.clientesDefinition) {
        return global.CrudUtils.clone(embedded.clientesDefinition);
      }
      if (normalizedUrl.endsWith("examples/processamento-relatorio.process.json") && embedded.processamentoRelatorioDefinition) {
        return global.CrudUtils.clone(embedded.processamentoRelatorioDefinition);
      }
      if (normalizedUrl.endsWith("examples/codificacao-assistente-pdm.process.json") && embedded.codificacaoAssistentePdmDefinition) {
        return global.CrudUtils.clone(embedded.codificacaoAssistentePdmDefinition);
      }
      if (normalizedUrl.endsWith("public/config/crud-engine.config.json") && embedded.config) {
        return global.CrudUtils.clone(embedded.config);
      }
      return null;
    }

    route(method, url, data) {
      if (url === "/api/public/report-authenticity/verify" && method === "GET") {
        return this.verifyReportAuthenticity(String(data && data.hash || ""));
      }
      const screenMatch = String(url || "").match(/^\/api\/runtime\/screens\/([^/?]+)$/);
      if (screenMatch && method === "GET") {
        return this.getRuntimeScreenDefinition(decodeURIComponent(screenMatch[1]));
      }
      const runtimeMatch = String(url || "").match(/^\/api\/runtime\/screens\/([^/]+)\/endpoints\/([^/?]+)/);
      if (runtimeMatch) {
        return this.routeRuntimeEndpoint(decodeURIComponent(runtimeMatch[1]), decodeURIComponent(runtimeMatch[2]), data);
      }
      if (url === "/api/auth/session" && method === "GET") {
        const impersonation = global.CrudUtils.readLocalJsonValue("crudEngine.impersonation", { enabled: false });
        return {
          authenticated: true,
          tenantId: this.tenantId,
          session: {
            sessionId: this.sessionId,
            status: "active",
            impersonation: impersonation || { enabled: false }
          },
          user: {
            id: impersonation && impersonation.targetUserId || this.userId,
            username: impersonation && impersonation.targetUserId || this.userId,
            name: impersonation && impersonation.targetUserName || "Usuario Demo",
            groups: ["admin"],
            permissions: ["*"]
          }
        };
      }
      if (url === "/api/auth/impersonate/stop" && method === "POST") {
        return {
          ok: true,
          impersonationStopped: true,
          effects: [{ action: "restoreOriginalSession", message: "Simulacao encerrada." }]
        };
      }
      if (url === "/api/cadastros/clientes" && method === "GET") {
        return this.list(data);
      }
      if (url === "/api/cadastros/clientes" && method === "POST") {
        return this.create(data);
      }
      if (url === "/api/cadastros/clientes/bulk/status" && method === "POST") {
        return this.bulkUpdateStatus(data);
      }
      if (url === "/api/cadastros/clientes/bulk/delete" && method === "POST") {
        return this.bulkDelete(data);
      }
      if (/^\/api\/cadastros\/clientes\/\d+$/.test(url) && method === "GET") {
        return this.get(this.extractId(url));
      }
      if (/^\/api\/cadastros\/clientes\/\d+$/.test(url) && method === "PUT") {
        return this.update(this.extractId(url), data);
      }
      if (/^\/api\/cadastros\/clientes\/\d+$/.test(url) && method === "DELETE") {
        return this.delete(this.extractId(url), data || {});
      }
      if (url === "/api/mock/externo/produtos" && method === "POST") {
        return this.listExternalApiProducts(data);
      }
      if (/^\/api\/mock\/externo\/produtos\/\d+$/.test(url) && method === "GET") {
        return this.getExternalApiProduct(this.extractId(url));
      }
      if (url === "/api/mock/externo/produtos-crud" && method === "POST") {
        if (data && (data.page || data.pageSize || data.take || data.skip || data.sort || data.filters)) {
          return this.listExternalApiCrudProducts(data);
        }
        return this.createExternalApiCrudProduct(data);
      }
      if (/^\/api\/mock\/externo\/produtos-crud\/\d+$/.test(url) && method === "GET") {
        return this.getExternalApiCrudProduct(this.extractId(url));
      }
      if (/^\/api\/mock\/externo\/produtos-crud\/\d+$/.test(url) && method === "PUT") {
        return this.updateExternalApiCrudProduct(this.extractId(url), data);
      }
      if (/^\/api\/mock\/externo\/produtos-crud\/\d+$/.test(url) && method === "DELETE") {
        return this.deleteExternalApiCrudProduct(this.extractId(url));
      }
      if (url === "/api/cadastros/clientes/form-rules/status" && method === "POST") {
        return this.validateStatusRules(data);
      }
      if (url === "/api/localidades/cidades" && method === "POST") {
        return this.listCitiesByState(data);
      }
      if (url === "/api/cadastros/clientes/status-history" && method === "POST") {
        return this.getStatusHistory(data);
      }
      if (/^\/api\/cadastros\/clientes\/\d+\/steps\/[^\/]+\/history$/.test(url) && method === "POST") {
        return this.getStepHistory(this.extractClientStepHistoryId(url), this.extractClientStepHistoryStep(url), data);
      }
      if (/^\/api\/cadastros\/clientes\/\d+\/print\/(excel|pdf|csv)$/.test(url) && method === "POST") {
        return this.printClient(this.extractClientPrintId(url), this.extractPrintFormat(url), data);
      }
      if (/^\/api\/cadastros\/clientes\/print\/(excel|pdf|csv)$/.test(url) && method === "POST") {
        return this.printClient(data && data.id, this.extractPrintFormat(url), data);
      }
      if (/^\/api\/cadastros\/clientes\/\d+\/actions\/(check-credit|send-welcome|send-whatsapp)$/.test(url) && method === "POST") {
        return this.executeClientAction(this.extractClientActionId(url), this.extractClientAction(url), data);
      }
      if (url === "/api/crud-layout/cadastros/clientes" && method === "POST") {
        return this.saveLayout(data);
      }
      if (url === "/api/crud-layout/cadastros/clientes/sorts" && method === "POST") {
        return this.saveSort(data);
      }
      if (/^\/api\/crud-layout\/cadastros\/clientes\/sorts\/[^\/]+$/.test(url) && method === "DELETE") {
        return this.deleteSort(this.extractSortId(url));
      }
      if (url === "/api/crud-layout/cadastros/clientes/groups" && method === "POST") {
        return this.saveGroup(data);
      }
      if (/^\/api\/crud-layout\/cadastros\/clientes\/groups\/[^\/]+$/.test(url) && method === "DELETE") {
        return this.deleteGroup(this.extractSortId(url));
      }
      if (url === "/api/crud-layout/cadastros/clientes/filters" && method === "POST") {
        return this.saveFilter(data);
      }
      if (/^\/api\/crud-layout\/cadastros\/clientes\/filters\/[^\/]+$/.test(url) && method === "DELETE") {
        return this.deleteFilter(this.extractSortId(url));
      }
      if (url === "/api/crud-layout/cadastros/clientes/mobile-templates" && method === "POST") {
        return this.saveMobileTemplate(data);
      }
      if (/^\/api\/crud-layout\/cadastros\/clientes\/mobile-templates\/[^\/]+$/.test(url) && method === "DELETE") {
        return this.deleteMobileTemplate(this.extractSortId(url));
      }
      if (url === "/api/help/seen" && method === "POST") {
        return this.saveHelpSeen(data);
      }
      if (url === "/api/home/chat/contacts" && method === "GET") {
        return this.getHomeChatContacts(data);
      }
      if (url === "/api/home/chat/history" && (method === "GET" || method === "POST")) {
        return this.getHomeChatHistory(data);
      }
      if (url === "/api/home/chat/send" && method === "POST") {
        return this.sendHomeChatMessage(data);
      }
      if (url === "/api/home/support/online-users" && method === "GET") {
        return this.getHomeSupportOnlineUsers(data);
      }
      if (url === "/api/home/support/history" && (method === "GET" || method === "POST")) {
        return this.getHomeSupportHistory(data);
      }
      if (url === "/api/home/support/send" && method === "POST") {
        return this.sendHomeSupportMessage(data);
      }
      if (url === "/api/home/support/requests" && method === "POST") {
        return this.createHomeSupportRequest(data);
      }
      if (url === "/api/home/support/requests/status" && (method === "GET" || method === "POST")) {
        return this.getHomeSupportRequestStatus(data);
      }
      if (url === "/api/home/ai-chat/history" && (method === "GET" || method === "POST")) {
        return this.getHomeAiChatHistory(data);
      }
      if (url === "/api/home/ai-chat/send" && method === "POST") {
        return this.sendHomeAiChatMessage(data);
      }
      if (url === "/api/home/alerts" && method === "GET") {
        return this.getHomeAlerts(data);
      }
      if (url === "/api/home/notifications" && (method === "GET" || method === "POST")) {
        return this.getHomeNotifications(data);
      }
      if (url === "/api/home/notifications/ack" && method === "POST") {
        return this.ackHomeNotifications(data);
      }
      if (url === "/api/home/requests" && method === "GET") {
        return this.getHomeRequests(data);
      }
      if (url === "/api/home/jobs" && (method === "GET" || method === "POST")) {
        return this.getHomeJobs(data);
      }
      if (url === "/api/processamento/clientes" && method === "POST") {
        return this.startClientProcess(data);
      }
      if (url === "/api/processamento/codificacao/pdm" && method === "POST") {
        return this.runCustomCodePdmAssistant(data);
      }
      if (url === "/api/processamento/clientes/status" && method === "POST") {
        return this.getClientProcessStatus(data);
      }
      if (url === "/api/home/subscribers/change" && method === "POST") {
        return this.changeHomeSubscriber(data);
      }
      if ((url === "/api/admin/program-builder/integrity/resign" || url === "/api/runtime/screens/admin.integridade/endpoints/runtime.admin.integrity.resign") && method === "POST") {
        return this.resignAdminIntegrityRecord(data || {});
      }
      if (url === "/api/crud-layout/cadastros/clientes" && method === "DELETE") {
        this.activeLayoutId = null;
        this.persistLayouts();
        return { ok: true, userLayout: this.buildUserLayout() };
      }

      throw global.CrudUtils.makeError("ROUTE_NOT_FOUND", "Rota mock nao encontrada.", { method, url });
    }

    getRuntimeScreenDefinition(screenId) {
      const normalized = String(screenId || "");
      if (normalized === "cadastros.clientes.producao") {
        return this.buildProductionClientDefinition(normalized);
      }
      const adminDefinition = this.buildAdminRuntimeDefinition(normalized);
      if (adminDefinition) {
        return adminDefinition;
      }
      if (normalized === "admin.jobs" || normalized === "runtime-jobs") {
        return this.buildRuntimeJobsDefinition("admin.jobs");
      }
      if (normalized === "runtime.jobs.mine") {
        return this.buildRuntimeJobsDefinition("runtime.jobs.mine");
      }
      if (normalized === "processamento.relatorio-clientes.producao") {
        return this.buildProductionProcessDefinition(normalized);
      }
      if (normalized === "analytics.clientes.producao") {
        return this.buildAnalyticsClientesDefinition(normalized);
      }
      if (normalized === "processamento.relatorio-clientes") {
        const definition = global.CrudDemoEmbedded && global.CrudDemoEmbedded.processamentoRelatorioDefinition;
        if (definition) {
          return global.CrudUtils.clone(definition);
        }
      }
      if (normalized === "analytics.clientes") {
        return this.buildAnalyticsClientesDefinition(normalized);
      }
      if (normalized === "relatorios.clientes-operacional" || normalized === "relatorios.clientes-operacional.producao") {
        return this.buildReportClientesDefinition(normalized, "operational");
      }
      if (normalized === "relatorios.clientes-analitico" || normalized === "relatorios.clientes-analitico.producao") {
        return this.buildReportClientesDefinition(normalized, "analytic");
      }
      if (["documentos.especiais-base", "documentos.especiais-base.producao", "documentos.especiais-boleto", "documentos.especiais-etiqueta"].indexOf(normalized) >= 0) {
        return this.buildSpecialDocumentDefinition(normalized);
      }
      if (normalized === "assistente.codificacao.produto-pdm") {
        const definition = global.CrudDemoEmbedded && global.CrudDemoEmbedded.codificacaoAssistentePdmDefinition;
        if (definition) {
          return global.CrudUtils.clone(definition);
        }
      }
      if (normalized === "cadastros.clientes" || normalized === "clientes" || normalized === "clientes-crud") {
        const definition = global.CrudDemoEmbedded && global.CrudDemoEmbedded.clientesDefinition;
        if (definition) {
          return global.CrudUtils.clone(definition);
        }
      }
      if (normalized === "home" || normalized === "construtor-pg") {
        const definition = global.HomeDemoEmbedded && global.HomeDemoEmbedded.homeDefinition;
        if (definition) {
          return global.CrudUtils.clone(definition);
        }
      }
      if (normalized === "home.producao") {
        return this.buildProductionHomeDefinition(normalized);
      }
      if (normalized === "sistema.troca-assinante") {
        return this.buildProductionSubscriberSwitchDefinition(normalized);
      }
      throw global.CrudUtils.makeError("SCREEN_NOT_FOUND", "Tela nao encontrada no runtime mock.", { screenId });
    }

    buildProductionHomeDefinition(screenId) {
      const source = global.HomeDemoEmbedded && global.HomeDemoEmbedded.homeDefinition;
      const definition = global.CrudUtils.clone(source || {});
      definition.screenId = screenId;
      definition.currentSubscriber = {
        id: "principal",
        name: "Principal",
        principal: true
      };
      definition.availableSubscribers = this.getHomeSubscriberOptions();
      definition.layout = definition.layout || {};
      definition.layout.initialProgramId = "clientes-crud";
      definition.layout.appbar = Object.assign({}, definition.layout.appbar || {}, {
        chat: {
          enabled: true,
          title: "Chat entre usuarios",
          buttonTitle: "Conversar com usuario",
          width: 420,
          height: 560,
          endpoints: {
            contacts: { endpointId: "home.chat.contacts", method: "GET" },
            history: { endpointId: "home.chat.history", method: "POST" },
            send: { endpointId: "home.chat.send", method: "POST" },
            events: { endpointId: "home.chat.events", method: "GET" }
          }
        },
        support: {
          enabled: true,
          title: "Atendimento",
          buttonTitle: "Abrir atendimento",
          icon: "headset",
          width: 540,
          height: 620,
          fallbackRequest: {
            enabled: true,
            defaultSectorId: "suporte"
          },
          sectors: [
            { id: "suporte", name: "Suporte" },
            { id: "financeiro", name: "Financeiro" }
          ],
          endpoints: {
            onlineUsers: { endpointId: "home.support.onlineUsers", method: "GET" },
            history: { endpointId: "home.support.history", method: "POST" },
            send: { endpointId: "home.support.send", method: "POST" },
            createRequest: { endpointId: "home.support.createRequest", method: "POST" },
            requestStatus: { endpointId: "home.support.requestStatus", method: "GET" },
            events: { endpointId: "home.support.events", method: "GET" }
          }
        },
        aiChat: {
          enabled: true,
          title: "Chat de IA",
          buttonTitle: "Abrir chat de IA",
          icon: "sparkles",
          width: 460,
          height: 560,
          endpoints: {
            history: { endpointId: "home.aiChat.history", method: "POST" },
            send: { endpointId: "home.aiChat.send", method: "POST" }
          }
        },
        notifications: {
          enabled: true,
          title: "Central de notificacoes",
          buttonTitle: "Abrir central de notificacoes",
          icon: "bell",
          pollIntervalSeconds: 20,
          endpoints: {
            list: { endpointId: "home.notifications.list", method: "POST" },
            ack: { endpointId: "home.notifications.ack", method: "POST" }
          }
        },
        alerts: {
          enabled: true,
          title: "Alertas",
          buttonTitle: "Alertas de informacoes",
          icon: "bell",
          endpoints: {
            list: { endpointId: "home.alerts.list", method: "POST" }
          }
        },
        requests: {
          enabled: true,
          title: "Solicitacoes",
          buttonTitle: "Solicitacoes recebidas ou atualizadas",
          icon: "inbox",
          endpoints: {
            list: { endpointId: "home.requests.list", method: "POST" }
          }
        },
        jobs: {
          enabled: true,
          title: "Jobs concluidos",
          buttonTitle: "Jobs concluidos",
          icon: "clock",
          programId: "meus-jobs",
          endpoints: {
            list: { endpointId: "home.jobs.list", method: "POST" }
          }
        },
        subscriberSwitch: {
          enabled: true,
          programId: "troca-assinante",
          endpoints: {
            change: {
              endpointId: "home.subscriber.change",
              method: "POST"
            }
          }
        }
      });
      definition.navigation = {
        initialModuleId: "",
        modules: [
          { id: "operacional", title: "Operacional" }
        ],
        groups: [
          {
            id: "principal",
            title: "Principal",
            moduleId: "operacional",
            items: [
              { programId: "clientes-crud", title: "Clientes" },
              { programId: "analytics-clientes", title: "BI de Clientes" },
              { programId: "processamento-clientes", title: "Processamento" },
              { programId: "meus-jobs", title: "Meus Jobs" },
              { programId: "troca-assinante", title: "Troca de assinante" }
            ]
          }
        ]
      };
      definition.programs = [
        {
          id: "clientes-crud",
          title: "Clientes",
          subtitle: "CRUD seguro carregado por screenId",
          type: "crud",
          icon: "user",
          permission: "clientes.read",
          screenId: "cadastros.clientes.producao"
        },
        {
          id: "troca-assinante",
          title: "Troca de assinante",
          subtitle: "Fluxo seguro carregado por screenId",
          type: "crud",
          icon: "building",
          permission: "home.read",
          screenId: "sistema.troca-assinante"
        },
        {
          id: "analytics-clientes",
          title: "BI de Clientes",
          subtitle: "Analytics seguro carregado por screenId",
          type: "analytics",
          icon: "chart-column",
          permission: "clientes.read",
          screenId: "analytics.clientes.producao"
        },
        {
          id: "processamento-clientes",
          title: "Processamento de Clientes",
          subtitle: "Processamento seguro carregado por screenId",
          type: "process",
          icon: "play",
          permission: "processamento.read",
          screenId: "processamento.relatorio-clientes.producao"
        },
        {
          id: "meus-jobs",
          title: "Meus Jobs",
          subtitle: "Jobs iniciados pelo usuario corrente",
          type: "crud",
          icon: "clock",
          permission: "jobs.read",
          screenId: "runtime.jobs.mine"
        }
      ];
      return definition;
    }

    buildProductionProcessDefinition(screenId) {
      const source = global.CrudDemoEmbedded && global.CrudDemoEmbedded.processamentoRelatorioDefinition || {};
      const definition = global.CrudUtils.clone(source);
      definition.screenId = screenId;
      definition.program = Object.assign({}, definition.program || {}, {
        screenId,
        title: "Processamento de Clientes - Producao segura",
        subtitle: "Definicao carregada por screenId e endpoints resolvidos por endpointId."
      });
      definition.dataSource = definition.dataSource || {};
      definition.dataSource.api = {
        process: { endpointId: "process", method: "POST" },
        status: { endpointId: "status", method: "POST" }
      };
      definition.api = definition.dataSource.api;
      definition.process = definition.process || {};
      definition.process.endpoints = {
        process: { endpointId: "process", method: "POST" },
        status: { endpointId: "status", method: "POST" }
      };
      return definition;
    }

    buildAnalyticsClientesDefinition(screenId) {
      const source = global.AnalyticsDemoEmbedded && global.AnalyticsDemoEmbedded.clientesDefinition || {};
      const definition = global.CrudUtils.clone(source);
      definition.screenId = screenId;
      definition.program = Object.assign({}, definition.program || {}, {
        screenId,
        title: screenId.indexOf(".producao") >= 0 ? "BI de Clientes - Producao segura" : "BI de Clientes",
        subtitle: "Consulta analytics carregada por screenId e endpoints resolvidos por endpointId."
      });
      const api = {
        schema: { endpointId: "analytics.schema", method: "POST" },
        run: { endpointId: "analytics.query.run", method: "POST" },
        materialize: { endpointId: "analytics.materialize", method: "POST" },
        cacheStatus: { endpointId: "analytics.cache.status", method: "POST" }
      };
      definition.dataSource = definition.dataSource || {};
      definition.dataSource.api = api;
      definition.api = api;
      definition.analytics = definition.analytics || {};
      definition.analytics.endpoints = api;
      return definition;
    }

    buildReportClientesDefinition(screenId, mode) {
      const source = global.ReportDemoEmbedded && (mode === "analytic" ? global.ReportDemoEmbedded.analyticDefinition : global.ReportDemoEmbedded.operationalDefinition);
      const definition = global.CrudUtils.clone(source || {});
      if (!definition || !definition.program) {
        return definition;
      }
      definition.screenId = screenId;
      definition.program.screenId = screenId;
      definition.program.subtitle = "Relatorio carregado por screenId e endpoints fechados.";
      const api = {
        schema: { endpointId: "reports.schema", method: "POST" },
        run: { endpointId: "reports.run", method: "POST" },
        export: { endpointId: "reports.export", method: "POST" }
      };
      definition.dataSource = definition.dataSource || {};
      definition.dataSource.api = api;
      definition.api = api;
      definition.report = definition.report || {};
      definition.report.endpoints = api;
      return definition;
    }

    buildSpecialDocumentDefinition(screenId) {
      return {
        schemaVersion: "1.0",
        pageType: "special_document",
        screenId: screenId,
        program: {
          id: "documento-especial-base",
          title: "Documento especial base",
          subtitle: "Contrato separado para documentos rigidos",
          version: "1.0.0",
          screenId: screenId
        },
        dataSource: {
          api: {
            schema: { endpointId: "specialDocuments.schema", method: "POST" },
            render: { endpointId: "specialDocuments.render", method: "POST" },
            export: { endpointId: "specialDocuments.export", method: "POST" }
          }
        },
        specialDocument: {
          classification: {
            documentProfile: "special",
            documentKind: "danfe"
          },
          renderEngine: "native",
          endpoints: {
            schema: { endpointId: "specialDocuments.schema", method: "POST" },
            render: { endpointId: "specialDocuments.render", method: "POST" },
            export: { endpointId: "specialDocuments.export", method: "POST" }
          },
          source: {
            type: "operational",
            entityCode: "cliente"
          },
          parameters: [
            { id: "status", field: "status", label: "Status", type: "enum", operator: "eq", options: [{ value: "ATIVO", text: "Ativo" }, { value: "INATIVO", text: "Inativo" }] },
            { id: "uf", field: "uf", label: "UF", type: "text", operator: "eq" }
          ],
          layout: {
            title: "Documento especial base",
            subtitle: "Renderer fechado",
            notes: "Sem layout livre na v1.",
            issuerName: "Emitente padrao LTDA",
            issuerDocument: "12.345.678/0001-90"
          },
          outputs: {
            html: true,
            pdf: true
          }
        }
      };
    }

    buildRuntimeJobsDefinition(screenId) {
      const mine = screenId === "runtime.jobs.mine";
      const statusOptions = [
        { value: "queued", text: "Na fila" },
        { value: "running", text: "Executando" },
        { value: "succeeded", text: "Concluido" },
        { value: "failed", text: "Falhou" }
      ];
      return {
        schemaVersion: "1.0",
        pageType: "crud",
        screenId,
        program: {
          id: mine ? "meus-jobs" : "runtime-jobs",
          module: "administracao",
          entity: "runtime_async_job",
          title: mine ? "Meus Jobs" : "Jobs Assincronos",
          version: "1.0.0",
          subtitle: mine ? "Consulta dos processamentos iniciados pelo usuario corrente" : "Consulta das acoes executadas por fila",
          subtitleTooltip: mine ? "Tela de consulta para acompanhar somente os jobs iniciados pelo usuario corrente." : "Tela de consulta para acompanhar jobs assincronos, status, tentativas, payload, resultado e erro."
        },
        permissions: {
          read: true,
          create: false,
          edit: false,
          delete: false,
          saveLayout: true
        },
        dataSource: {
          api: {
            read: { endpointId: "read", method: "POST" },
            get: { endpointId: "get", method: "POST" },
            saveLayout: { endpointId: "saveLayout", method: "POST" },
            restoreLayout: { endpointId: "restoreLayout", method: "POST" },
            saveSort: { endpointId: "saveSort", method: "POST" },
            deleteSort: { endpointId: "deleteSort", method: "POST" },
            saveGroup: { endpointId: "saveGroup", method: "POST" },
            deleteGroup: { endpointId: "deleteGroup", method: "POST" },
            saveFilter: { endpointId: "saveFilter", method: "POST" },
            deleteFilter: { endpointId: "deleteFilter", method: "POST" },
            "runtime.messages.poll": { endpointId: "runtime.messages.poll", method: "POST" },
            "runtime.messages.ack": { endpointId: "runtime.messages.ack", method: "POST" }
          }
        },
        runtime: {
          entityCode: "runtime_async_job",
          programId: mine ? "meus-jobs" : "runtime-jobs",
          lock: { enabled: false, modes: [] },
          messages: { enabled: true, pollIntervalSeconds: 30, events: { enabled: true } }
        },
        dataModel: {
          primaryKey: "id",
          fields: {
            id: { type: "integer", label: "ID", editable: false, nullable: false },
            job_type: { type: "string", label: "Tipo do job", editable: false, nullable: false },
            status: { type: "enum", label: "Status", editable: false, nullable: false, options: statusOptions },
            attempts: { type: "integer", label: "Tentativas", editable: false, nullable: false },
            entity_code: { type: "string", label: "Entidade", editable: false, nullable: true },
            record_id: { type: "string", label: "Registro", editable: false, nullable: true },
            action_id: { type: "string", label: "Acao", editable: false, nullable: true },
            transaction_id: { type: "integer", label: "Transacao", editable: false, nullable: true },
            user_id: { type: "string", label: "Usuario", editable: false, nullable: false },
            created_at: { type: "datetime", label: "Criado em", editable: false, nullable: false },
            started_at: { type: "datetime", label: "Iniciado em", editable: false, nullable: true },
            finished_at: { type: "datetime", label: "Finalizado em", editable: false, nullable: true },
            last_error: { type: "text", label: "Ultimo erro", editable: false, nullable: true },
            payload: { type: "json", label: "Payload", editable: false, nullable: false },
            result: { type: "json", label: "Resultado", editable: false, nullable: false }
          }
        },
        crud: {
          query: {
            pageSize: 20,
            defaultSort: [{ field: "created_at", dir: "desc" }]
          },
          filter: {
            type: "window",
            mode: "basic",
            title: "Filtros de jobs",
            openOnLoad: false,
            showAppliedFilters: true,
            fields: [
              { id: "job_type", field: "job_type", label: "Tipo do job", type: "text", operator: "contains" },
              { id: "status", field: "status", label: "Status", type: "enum", operator: "eq", options: statusOptions },
              { id: "entity_code", field: "entity_code", label: "Entidade", type: "text", operator: "contains" },
              { id: "record_id", field: "record_id", label: "Registro", type: "text", operator: "contains" }
            ]
          },
          grid: {
            pageable: true,
            sortable: true,
            filterable: true,
            resizable: true,
            reorderable: true,
            columnMenu: true,
            toolbar: [
              { id: "filters", label: "Filtros", action: "filters", icon: "filter" },
              { id: "refresh", label: "Atualizar", action: "refresh", icon: "arrow-rotate-cw" },
              { id: "layout", label: "Leiaute", action: "layout", icon: "columns", permission: "saveLayout" }
            ],
            columns: [
              { field: "id", title: "ID", width: 80, align: "right" },
              { field: "job_type", title: "Tipo do job", width: 230 },
              { field: "status", title: "Status", width: 130 },
              { field: "attempts", title: "Tentativas", width: 110, align: "right" },
              { field: "entity_code", title: "Entidade", width: 140 },
              { field: "record_id", title: "Registro", width: 110 },
              { field: "user_id", title: "Usuario", width: 140 },
              { field: "created_at", title: "Criado em", width: 180 },
              { field: "finished_at", title: "Finalizado em", width: 180 },
              { field: "last_error", title: "Ultimo erro", width: 260 }
            ],
            rowActions: [
              { id: "view", label: "Visualizar", action: "view", icon: "eye", permission: "read" }
            ],
            bulkActions: { enabled: false, actions: [] },
            print: { enabled: false, options: [] }
          },
          form: {
            id: "runtime-job-form",
            mode: "popup",
            layout: "tabs",
            maximizeForm: true,
            title: { view: "Detalhe do job" },
            behavior: { closeOnSave: true, closeOnCancel: true },
            tabs: [
              { id: "geral", title: "Geral", fields: ["id", "job_type", "status", "attempts", "entity_code", "record_id", "action_id", "transaction_id", "user_id", "created_at", "started_at", "finished_at"] },
              { id: "dados", title: "Dados", fields: ["payload", "result", "last_error"] }
            ],
            fields: ["id", "job_type", "status", "attempts", "entity_code", "record_id", "action_id", "transaction_id", "user_id", "created_at", "started_at", "finished_at", "payload", "result", "last_error"].map(function(field) {
              return { field, renderAs: "readonly" };
            }),
            logs: { enabled: false },
            print: { enabled: false, options: [] },
            otherActions: { enabled: false, actions: [] }
          },
          userLayout: {
            enabled: true,
            storageKey: "runtimeJobsLayout"
          }
        }
      };
    }

    buildProductionSubscriberSwitchDefinition(screenId) {
      const definition = this.buildProductionClientDefinition(screenId);
      if (!definition || !definition.program) {
        return definition;
      }
      definition.program.title = "Troca de assinante";
      definition.program.subtitle = "Tela segura carregada por screenId para fluxo dedicado de troca.";
      if (definition.crud && definition.crud.grid) {
        definition.crud.grid.ai = { enabled: false };
        definition.crud.grid.bulkActions = { enabled: false, actions: [] };
        definition.crud.grid.print = { enabled: false, options: [] };
      }
      if (definition.crud && definition.crud.form) {
        definition.crud.form.logs = { enabled: false };
        definition.crud.form.print = { enabled: false, options: [] };
        definition.crud.form.otherActions = { enabled: false, actions: [] };
      }
      return definition;
    }

    buildProductionClientDefinition(screenId) {
      const source = global.CrudDemoEmbedded && global.CrudDemoEmbedded.clientesDefinition;
      const definition = global.CrudUtils.clone(source || {});
      if (!definition || !definition.program) {
        return definition;
      }

      definition.screenId = screenId;
      definition.program.screenId = screenId;
      definition.program.title = "Clientes - Producao segura";
      definition.program.subtitle = "Definicao carregada por screenId e APIs roteadas por endpointId.";
      definition.program.logs = { enabled: false };
      definition.program.help = { enabled: false };

      const api = definition.dataSource && definition.dataSource.api || definition.api || {};
      Object.keys(api).forEach(function(key) {
        const endpoint = api[key] || {};
        api[key] = {
          endpointId: endpoint.endpointId || key,
          method: endpoint.method || "POST"
        };
      });

      if (definition.dataSource) {
        definition.dataSource.api = api;
      }
      definition.api = api;

      if (definition.crud && definition.crud.grid) {
        definition.crud.grid.ai = { enabled: false };
      }
      if (definition.crud && definition.crud.form) {
        definition.crud.form.logs = { enabled: false };
      }

      return definition;
    }

    routeRuntimeEndpoint(screenId, endpointId, data) {
      const normalizedScreenId = String(screenId || "");
      if (normalizedScreenId === "home" || normalizedScreenId === "home.producao" || normalizedScreenId === "construtor-pg") {
        return this.routeHomeRuntimeEndpoint(normalizedScreenId, endpointId, data);
      }
      if (normalizedScreenId === "processamento.relatorio-clientes" || normalizedScreenId === "processamento.relatorio-clientes.producao") {
        if (endpointId === "process") {
          return this.startClientProcess(data || {});
        }
        if (endpointId === "status") {
          return this.getClientProcessStatus(data || {});
        }
      }
      if (normalizedScreenId === "analytics.clientes" || normalizedScreenId === "analytics.clientes.producao") {
        return this.routeAnalyticsClientesEndpoint(normalizedScreenId, endpointId, data || {});
      }
      if (normalizedScreenId === "relatorios.clientes-operacional" || normalizedScreenId === "relatorios.clientes-operacional.producao" || normalizedScreenId === "relatorios.clientes-analitico" || normalizedScreenId === "relatorios.clientes-analitico.producao") {
        return this.routeReportClientesEndpoint(normalizedScreenId, endpointId, data || {});
      }
      if (["documentos.especiais-base", "documentos.especiais-base.producao", "documentos.especiais-boleto", "documentos.especiais-etiqueta"].indexOf(normalizedScreenId) >= 0) {
        return this.routeSpecialDocumentEndpoint(normalizedScreenId, endpointId, data || {});
      }
      if (this.isSessionRevoked()) {
        throw global.CrudUtils.makeError("SESSION_REVOKED", "Sua sessao foi encerrada.", {
          reason: "Sessao encerrada no mock."
        });
      }
      const systemResponse = this.routeSystemRuntimeEndpoint(endpointId, data || {});
      if (systemResponse) {
        return systemResponse;
      }
      const jobsResponse = this.routeRuntimeJobsEndpoint(normalizedScreenId, endpointId, data || {});
      if (jobsResponse) {
        return jobsResponse;
      }
      const adminResponse = this.routeAdminRuntimeEndpoint(normalizedScreenId, endpointId, data || {});
      if (adminResponse) {
        return adminResponse;
      }

      const id = data && (data.id || data.clienteId);
      switch (endpointId) {
        case "read":
          return this.list(data || {});
        case "get":
          return this.get(id);
        case "create":
          return this.create(data || {});
        case "update":
          return this.update(id, data || {});
        case "delete":
          return this.delete(id, data || {});
        case "validateStatusCliente":
          return this.validateStatusRules(data || {});
        case "loadCidadesByUf":
          return this.listCitiesByState(data || {});
        case "statusHistory":
          return this.getStatusHistory(data || {});
        case "stepHistory":
          return this.getStepHistory(id, data && data.stepId, data || {});
        case "printClienteExcel":
          return this.printClient(id, "excel", data || {});
        case "printClientePdf":
          return this.printClient(id, "pdf", data || {});
        case "printClienteCsv":
          return this.printClient(id, "csv", data || {});
        case "checkCredit":
          return this.executeClientAction(id, "check-credit", data || {});
        case "sendWelcome":
          return this.executeClientAction(id, "send-welcome", data || {});
        case "sendWhatsapp":
          return this.executeClientAction(id, "send-whatsapp", data || {});
        case "bulkActivate":
        case "bulkInactivate":
          return this.bulkUpdateStatus(data || {});
        case "bulkDelete":
          return this.bulkDelete(data || {});
        case "saveLayout":
          return this.saveLayout(data || {});
        case "restoreLayout":
          this.activeLayoutId = null;
          this.persistLayouts();
          return { ok: true, userLayout: this.buildUserLayout() };
        case "saveSort":
          return this.saveSort(data || {});
        case "deleteSort":
          return this.deleteSort(data && data.id);
        case "saveGroup":
          return this.saveGroup(data || {});
        case "deleteGroup":
          return this.deleteGroup(data && data.id);
        case "saveFilter":
          return this.saveFilter(data || {});
        case "deleteFilter":
          return this.deleteFilter(data && data.id);
        case "saveMobileTemplate":
          return this.saveMobileTemplate(data || {});
        case "deleteMobileTemplate":
          return this.deleteMobileTemplate(data && data.id);
        case "help.markAsRead":
          return this.saveHelpSeen(data || {});
        default:
          throw global.CrudUtils.makeError("RUNTIME_ENDPOINT_NOT_FOUND", "Endpoint runtime mock nao encontrado.", { screenId, endpointId });
      }
    }

    routeAnalyticsClientesEndpoint(screenId, endpointId, data) {
      if (endpointId === "analytics.schema") {
        return this.buildAnalyticsClientesDefinition(screenId);
      }
      if (endpointId === "analytics.query.run") {
        return this.runAnalyticsClientes(data || {});
      }
      if (endpointId === "analytics.materialize") {
        return {
          ok: true,
          queued: true,
          runtimePendingRef: "analytics-demo-" + Date.now(),
          message: "Atualizacao de cache BI agendada."
        };
      }
      if (endpointId === "analytics.cache.status") {
        return {
          status: "miss",
          datasetId: data && data.datasetId || "clientes-uf-status",
          fingerprint: "demo"
        };
      }
      throw global.CrudUtils.makeError("RUNTIME_ENDPOINT_NOT_FOUND", "Endpoint analytics mock nao encontrado.", { screenId, endpointId });
    }

    routeReportClientesEndpoint(screenId, endpointId, data) {
      const analytic = screenId.indexOf("analitico") >= 0;
      if (endpointId === "reports.schema") {
        return this.buildReportClientesDefinition(screenId, analytic ? "analytic" : "operational");
      }
      if (endpointId === "reports.run") {
        return analytic ? this.runAnalyticReport(data || {}) : this.runOperationalReport(data || {});
      }
      if (endpointId === "reports.export") {
        const result = analytic ? this.runAnalyticReport(data || {}) : this.runOperationalReport(data || {});
        return this.exportReportResult(result, data && data.format || "csv");
      }
      throw global.CrudUtils.makeError("RUNTIME_ENDPOINT_NOT_FOUND", "Endpoint de relatorio mock nao encontrado.", { screenId, endpointId });
    }

    routeSpecialDocumentEndpoint(screenId, endpointId, data) {
      if (endpointId === "specialDocuments.schema") {
        return this.buildSpecialDocumentDefinition(screenId);
      }
      if (endpointId === "specialDocuments.render") {
        const parameters = data && data.parameters || {};
        const sourceType = String(data && data.sourceType || "operational").toLowerCase();
        const documentKind = String(data && data.documentKind || "danfe").toLowerCase();
        if (sourceType === "analytic") {
          const analytics = this.runAnalyticsClientes({
            datasetId: "clientes-uf-status",
            parameters: parameters
          });
          const rows = global.CrudUtils.ensureArray(analytics.data || []);
          return {
            ok: true,
            documentId: "documento-especial-base",
            documentKind: "fiscal_document",
            renderEngine: "native",
            profileType: "danfe",
            documentModel: {
              profileType: "danfe",
              issuer: { name: "Emitente analytics LTDA", document: "12.345.678/0001-90", city: "Fortaleza", state: "CE" },
              recipient: { name: "Recorte analytics", document: "---", city: "Fortaleza", state: "CE" },
              invoice: { number: "9001", series: "1", issueDate: new Date().toISOString().slice(0, 10), protocol: "135240000123456", accessKey: "3514 0530 2908 5600 0160 5500 1000 0001 2345 6789 0123" }
            },
            sourceType: "analytic",
            summary: [
              { label: "Tipo", value: "fiscal_document" },
              { label: "Fonte", value: "Analytics" },
              { label: "Linhas", value: rows.length }
            ],
            headerFields: [
              { label: "Documento", value: "Documento especial base" },
              { label: "Gerado em", value: new Date().toISOString() },
              { label: "Engine", value: "native" }
            ],
            parameterFields: Object.keys(parameters).map(function(key) {
              return { label: key, value: parameters[key] };
            }),
            table: {
              columns: analytics.columns,
              rows: rows,
              rowCount: rows.length
            },
            totals: {
              clientes: rows.reduce(function(sum, item) { return sum + Number(item.clientes || 0); }, 0),
              valor_total_sum: rows.reduce(function(sum, item) { return sum + Number(item.valor_total_sum || 0); }, 0),
              qtde_pedidos_sum: rows.reduce(function(sum, item) { return sum + Number(item.qtde_pedidos_sum || 0); }, 0)
            },
            message: rows.length
              ? "Documento especial analitico renderizado com dataset interno do mock."
              : "Documento especial analitico sem linhas para os parametros informados.",
            sections: [
              { title: "Escopo", lines: ["Documento especial separado de reports.", "Fonte interna analytics usada apenas por metadado fechado."] },
              { title: "Saida", lines: ["HTML controlado com totais agregados.", "PDF controlado com o mesmo recorte."] }
            ]
          };
        }
        const statusFilter = String(parameters.status || "").trim().toUpperCase();
        const ufFilter = String(parameters.uf || "").trim().toUpperCase();
        const rows = global.CrudUtils.clone(this.records || []).filter(function(item) {
          const matchStatus = !statusFilter || String(item.status || "").toUpperCase() === statusFilter;
          const matchUf = !ufFilter || String(item.uf || "").toUpperCase() === ufFilter;
          return matchStatus && matchUf;
        }).slice(0, 8).map(function(item) {
          return {
            nome: item.nome,
            uf: item.uf,
            status: item.status,
            valor_total: Number(item.valor_total || 0),
            qtde_pedidos: Number(item.qtde_pedidos || 0)
          };
        });
        const totalValor = rows.reduce(function(sum, item) {
          return sum + Number(item.valor_total || 0);
        }, 0);
        const totalPedidos = rows.reduce(function(sum, item) {
          return sum + Number(item.qtde_pedidos || 0);
        }, 0);
        if (documentKind === "boleto") {
          return {
            ok: true,
            documentId: "documento-especial-base",
            documentKind: "boleto",
            renderEngine: "native",
            profileType: "boleto",
            documentModel: {
              profileType: "boleto",
              beneficiary: { name: "Beneficiario padrao LTDA", document: "12.345.678/0001-90" },
              payer: { name: rows[0] && rows[0].nome || "Pagador", document: "---" },
              payment: {
                dueDate: "2026-06-30",
                documentNumber: "DOC-0001",
                nossoNumero: "10987654321",
                amount: totalValor,
                barcode: "3419 1790 0101 0435 1004 7910 2015 0008 2910 7002 6000"
              }
            },
            sourceType: "operational",
            summary: [
              { label: "Tipo", value: "boleto" },
              { label: "Fonte", value: "Operacional" },
              { label: "Linhas", value: rows.length }
            ],
            headerFields: [
              { label: "Documento", value: "Documento especial base" },
              { label: "Gerado em", value: new Date().toISOString() },
              { label: "Engine", value: "native" }
            ],
            parameterFields: Object.keys(parameters).map(function(key) { return { label: key, value: parameters[key] }; }),
            table: { columns: [{ field: "nome", title: "Nome" }, { field: "valor_total", title: "Valor" }], rows: rows, rowCount: rows.length },
            totals: { valor_total: totalValor },
            message: "Boleto controlado renderizado pelo mock local.",
            sections: [
              { title: "Escopo", lines: ["Boleto visual fechado.", "Sem template livre."] }
            ]
          };
        }
        if (documentKind === "label" || documentKind === "etiqueta") {
          return {
            ok: true,
            documentId: "documento-especial-base",
            documentKind: "label",
            renderEngine: "native",
            profileType: "label",
            documentModel: {
              profileType: "label",
              labels: rows.map(function(row, index) {
                return {
                  code: "ETQ-" + String(index + 1).padStart(4, "0"),
                  recipient: row.nome,
                  line1: row.status,
                  line2: row.uf,
                  printedAt: new Date().toISOString().slice(0, 16)
                };
              })
            },
            sourceType: "operational",
            summary: [
              { label: "Tipo", value: "label" },
              { label: "Fonte", value: "Operacional" },
              { label: "Linhas", value: rows.length }
            ],
            headerFields: [
              { label: "Documento", value: "Documento especial base" },
              { label: "Gerado em", value: new Date().toISOString() },
              { label: "Engine", value: "native" }
            ],
            parameterFields: Object.keys(parameters).map(function(key) { return { label: key, value: parameters[key] }; }),
            table: { columns: [{ field: "nome", title: "Nome" }, { field: "uf", title: "UF" }, { field: "status", title: "Status" }], rows: rows, rowCount: rows.length },
            totals: {},
            message: rows.length ? "Etiquetas renderizadas pelo mock local." : "Nenhuma etiqueta para o recorte informado.",
            sections: [
              { title: "Escopo", lines: ["Etiquetas em grade.", "Sem coordenadas livres na v1."] }
            ]
          };
        }
        return {
          ok: true,
          documentId: "documento-especial-base",
          documentKind: "danfe",
          renderEngine: "native",
          profileType: "danfe",
          documentModel: {
            profileType: "danfe",
            issuer: { name: "Emitente padrao LTDA", document: "12.345.678/0001-90", city: "Fortaleza", state: "CE" },
            recipient: { name: rows[0] && rows[0].nome || "Destinatario", document: "---", city: rows[0] && rows[0].uf || "CE", state: rows[0] && rows[0].uf || "CE" },
            invoice: { number: "12345", series: "1", issueDate: new Date().toISOString().slice(0, 10), protocol: "135240000123456", accessKey: "3514 0530 2908 5600 0160 5500 1000 0001 2345 6789 0123" }
          },
          sourceType: "operational",
          summary: [
            { label: "Tipo", value: "danfe" },
            { label: "Fonte", value: "Operacional" },
            { label: "Linhas", value: rows.length }
          ],
          headerFields: [
            { label: "Documento", value: "Documento especial base" },
            { label: "Gerado em", value: new Date().toISOString() },
            { label: "Engine", value: "native" }
          ],
          parameterFields: Object.keys(parameters).map(function(key) {
            return { label: key, value: parameters[key] };
          }),
          table: {
            columns: [
              { field: "nome", title: "Nome" },
              { field: "uf", title: "UF" },
              { field: "status", title: "Status" },
              { field: "valor_total", title: "Valor total", align: "right" },
              { field: "qtde_pedidos", title: "Pedidos", align: "right" }
            ],
            rows: rows,
            rowCount: rows.length
          },
          totals: {
            valor_total: totalValor,
            qtde_pedidos: totalPedidos
          },
          message: rows.length
            ? "Documento especial renderizado com base real do mock local."
            : "Documento especial sem linhas para os parametros informados.",
          sections: [
            { title: "Escopo", lines: ["Layout rigido separado de reports.", "Ponto de extensao futuro para engine dedicada."] },
            { title: "Saida", lines: ["HTML controlado com dados tabulares.", "PDF controlado com a mesma fonte."] }
          ]
        };
      }
      if (endpointId === "specialDocuments.export") {
        const format = String(data && data.format || "pdf").toLowerCase();
        const content = format === "html"
          ? "<html><body><h1>Documento especial base</h1><p>Renderizacao controlada com dados locais.</p><table><tr><th>Nome</th><th>UF</th></tr><tr><td>Acme Comercio</td><td>CE</td></tr></table></body></html>"
          : "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF";
        return {
          ok: true,
          format: format,
          fileName: "documento-especial-base." + (format === "html" ? "html" : "pdf"),
          contentType: format === "html" ? "text/html;charset=utf-8" : "application/pdf",
          contentBase64: global.btoa(unescape(encodeURIComponent(content)))
        };
      }
      throw global.CrudUtils.makeError("RUNTIME_ENDPOINT_NOT_FOUND", "Endpoint de documento especial mock nao encontrado.", { screenId, endpointId });
    }

    runAnalyticsClientes(data) {
      const parameters = data && data.parameters || {};
      let rows = global.CrudUtils.clone(this.records || []);
      const status = String(parameters.status || "").trim();
      const uf = String(parameters.uf || "").trim().toUpperCase();
      if (status) {
        rows = rows.filter(function(row) {
          return String(row.status || "") === status;
        });
      }
      if (uf) {
        rows = rows.filter(function(row) {
          return String(row.uf || "").toUpperCase() === uf;
        });
      }

      const grouped = {};
      rows.forEach(function(row) {
        const key = String(row.uf || "") + "|" + String(row.status || "");
        if (!grouped[key]) {
          grouped[key] = {
            uf: row.uf || "",
            status: row.status || "",
            clientes: 0,
            valor_total_sum: 0,
            qtde_pedidos_sum: 0
          };
        }
        grouped[key].clientes += 1;
        grouped[key].valor_total_sum += Number(row.valor_total || 0);
        grouped[key].qtde_pedidos_sum += Number(row.qtde_pedidos || 0);
      });

      const dataRows = Object.keys(grouped).map(function(key) {
        return grouped[key];
      }).sort(function(left, right) {
        return String(left.uf || "").localeCompare(String(right.uf || "")) || String(left.status || "").localeCompare(String(right.status || ""));
      });

      return {
        data: dataRows,
        total: dataRows.length,
        datasetId: data && data.datasetId || "clientes-uf-status",
        generatedAt: new Date().toISOString(),
        columns: [
          { field: "uf", id: "uf", title: "UF", label: "UF", type: "string", role: "dimension" },
          { field: "status", id: "status", title: "Status", label: "Status", type: "string", role: "dimension" },
          { field: "clientes", id: "clientes", title: "Clientes", label: "Clientes", type: "integer", role: "measure", aggregate: "count" },
          { field: "valor_total_sum", id: "valor_total_sum", title: "Valor total", label: "Valor total", type: "currency", role: "measure", aggregate: "sum" },
          { field: "qtde_pedidos_sum", id: "qtde_pedidos_sum", title: "Pedidos", label: "Pedidos", type: "integer", role: "measure", aggregate: "sum" }
        ],
        _runtime: {
          analytics: {
            executionMode: "auto",
            aggregated: true
          },
          analyticsCache: {
            status: "miss_live"
          }
        }
      };
    }

    runOperationalReport(data) {
      const parameters = data && data.parameters || {};
      let rows = global.CrudUtils.clone(this.records || []);
      const status = String(parameters.status || "").trim();
      const uf = String(parameters.uf || "").trim().toUpperCase();
      if (status) {
        rows = rows.filter(function(row) {
          return String(row.status || "") === status;
        });
      }
      if (uf) {
        rows = rows.filter(function(row) {
          return String(row.uf || "").toUpperCase() === uf;
        });
      }
      rows = rows.map(function(row) {
        return {
          nome: row.nome,
          uf: row.uf,
          status: row.status,
          valor_total: row.valor_total,
          qtde_pedidos: row.qtde_pedidos
        };
      });
      const generatedAt = new Date().toISOString();
      const result = {
        screenId: "relatorios.clientes-operacional",
        reportId: "relatorio-clientes-operacional",
        title: "Relatorio operacional de clientes",
        sourceType: "operational",
        rows: rows,
        columns: [
          { field: "nome", title: "Nome", type: "string", align: "left" },
          { field: "uf", title: "UF", type: "string", align: "left" },
          { field: "status", title: "Status", type: "string", align: "left" },
          { field: "valor_total", title: "Valor total", type: "currency", format: "c2", align: "right", totalable: true },
          { field: "qtde_pedidos", title: "Pedidos", type: "integer", format: "n0", align: "right", totalable: true }
        ],
        totals: {
          valor_total: rows.reduce(function(sum, row) { return sum + Number(row.valor_total || 0); }, 0),
          qtde_pedidos: rows.reduce(function(sum, row) { return sum + Number(row.qtde_pedidos || 0); }, 0)
        },
        summary: [
          { label: "Linhas", value: rows.length },
          { label: "Valor total", formattedValue: kendo.toString(rows.reduce(function(sum, row) { return sum + Number(row.valor_total || 0); }, 0), "c2") }
        ],
        metadata: [
          { label: "Gerado em", value: generatedAt },
          { label: "Parametros", value: status || uf ? ("status=" + (status || "todos") + " | uf=" + (uf || "todas")) : "Sem parametros" }
        ],
        total: rows.length,
        generatedAt: generatedAt
      };
      return this.decorateReportAuthenticity(result, data);
    }

    runAnalyticReport(data) {
      const analytics = this.runAnalyticsClientes({
        datasetId: "clientes-uf-status",
        parameters: data && data.parameters || {}
      });
      const rows = analytics.data || [];
      const groups = {};
      rows.forEach(function(row) {
        const key = String(row.uf || "");
        if (!groups[key]) {
          groups[key] = { key: key, label: key, rows: [], totals: { clientes: 0, valor_total_sum: 0, qtde_pedidos_sum: 0 } };
        }
        groups[key].rows.push(row);
        groups[key].totals.clientes += Number(row.clientes || 0);
        groups[key].totals.valor_total_sum += Number(row.valor_total_sum || 0);
        groups[key].totals.qtde_pedidos_sum += Number(row.qtde_pedidos_sum || 0);
      });
      const generatedAt = new Date().toISOString();
      const result = {
        screenId: "relatorios.clientes-analitico",
        reportId: "relatorio-clientes-analitico",
        title: "Relatorio analitico por UF",
        sourceType: "analytic",
        rows: rows,
        columns: [
          { field: "uf", title: "UF", type: "string", align: "left" },
          { field: "status", title: "Status", type: "string", align: "left" },
          { field: "clientes", title: "Clientes", type: "integer", format: "n0", align: "right", totalable: true },
          { field: "valor_total_sum", title: "Valor total", type: "currency", format: "c2", align: "right", totalable: true },
          { field: "qtde_pedidos_sum", title: "Pedidos", type: "integer", format: "n0", align: "right", totalable: true }
        ],
        groups: Object.keys(groups).sort().map(function(key) {
          return Object.assign(groups[key], { rowCount: groups[key].rows.length });
        }),
        totals: {
          clientes: rows.reduce(function(sum, row) { return sum + Number(row.clientes || 0); }, 0),
          valor_total_sum: rows.reduce(function(sum, row) { return sum + Number(row.valor_total_sum || 0); }, 0),
          qtde_pedidos_sum: rows.reduce(function(sum, row) { return sum + Number(row.qtde_pedidos_sum || 0); }, 0)
        },
        summary: [
          { label: "Linhas", value: rows.length },
          { label: "Clientes", value: rows.reduce(function(sum, row) { return sum + Number(row.clientes || 0); }, 0) }
        ],
        metadata: [
          { label: "Gerado em", value: generatedAt },
          { label: "Fonte", value: "Analytics" }
        ],
        total: rows.length,
        generatedAt: generatedAt
      };
      return this.decorateReportAuthenticity(result, data);
    }

    exportReportResult(result, format) {
      const columns = global.CrudUtils.ensureArray(result.columns || []);
      const rows = global.CrudUtils.ensureArray(result.rows || []);
      if (String(format || "").toLowerCase() === "pdf") {
        return {
          ok: true,
          format: "pdf",
          fileName: (result.reportId || "relatorio") + ".pdf",
          contentType: "application/pdf",
          contentBase64: global.btoa("%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF"),
          authenticity: result.authenticity || null
        };
      }
      if (String(format || "").toLowerCase() === "excel") {
        const workbook = JSON.stringify({
          columns: columns.map(function(column) { return column.title || column.field || ""; }),
          rows: rows
        });
        return {
          ok: true,
          format: "excel",
          fileName: (result.reportId || "relatorio") + ".xlsx",
          contentType: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
          contentBase64: global.btoa(unescape(encodeURIComponent(workbook))),
          authenticity: result.authenticity || null
        };
      }
      const lines = [];
      lines.push(columns.map(function(column) {
        return "\"" + String(column.title || column.field || "").replace(/"/g, "\"\"") + "\"";
      }).join(";"));
      rows.forEach(function(row) {
        lines.push(columns.map(function(column) {
          return "\"" + String(row[column.field] == null ? "" : row[column.field]).replace(/"/g, "\"\"") + "\"";
        }).join(";"));
      });
      const content = "\uFEFF" + lines.join("\r\n");
      return {
        ok: true,
        format: format,
        fileName: (result.reportId || "relatorio") + ".csv",
        contentType: "text/csv; charset=utf-8",
        contentBase64: global.btoa(unescape(encodeURIComponent(content))),
        authenticity: result.authenticity || null
      };
    }

    decorateReportAuthenticity(result, data) {
      const payload = global.CrudUtils.clone(result || {});
      const hash = "sha256:" + this.hashString(JSON.stringify({
        reportId: payload.reportId,
        screenId: payload.screenId,
        rows: payload.rows,
        columns: payload.columns,
        totals: payload.totals,
        parameters: data && data.parameters || {}
      }));
      payload.authenticity = {
        enabled: true,
        algorithm: "sha256",
        hash: hash,
        footerLabel: "Codigo de autenticidade",
        verificationPath: "report-authenticity.html",
        verificationUrl: "report-authenticity.html?hash=" + encodeURIComponent(hash),
        recorded: true,
        storage: {
          storeCanonicalPayload: true,
          storeExportArtifact: String(payload.reportId || "").indexOf("operacional") >= 0
        }
      };
      payload.metadata = global.CrudUtils.ensureArray(payload.metadata || []);
      payload.metadata.push({ label: "Codigo de autenticidade", value: hash });
      return payload;
    }

    verifyReportAuthenticity(hash) {
      const normalized = String(hash || "").trim().toLowerCase();
      if (!normalized) {
        throw global.CrudUtils.makeError("REPORT_AUTHENTICITY_HASH_REQUIRED", "Informe o hash de autenticidade.");
      }
      const operational = this.runOperationalReport({ parameters: {} });
      const analytic = this.runAnalyticReport({ parameters: {} });
      const match = [operational, analytic].find(function(item) {
        return item && item.authenticity && String(item.authenticity.hash || "").toLowerCase() === normalized;
      });
      if (!match) {
        return {
          enabled: true,
          found: false,
          hash: normalized,
          message: "Nenhum relatorio autenticado foi encontrado para este hash."
        };
      }
      return {
        enabled: true,
        found: true,
        hash: normalized,
        message: "Relatorio localizado na trilha demonstrativa.",
        report: {
          screenId: match.screenId,
          reportId: match.reportId,
          title: match.title,
          sourceType: match.sourceType,
          format: "html",
          rowCount: Number(match.total || (match.rows || []).length || 0),
          totalCount: Number(match.total || (match.rows || []).length || 0),
          generatedAt: match.generatedAt,
          tenantId: this.tenantId
        },
        authenticity: {
          algorithm: "sha256",
          hash: normalized,
          recorded: true,
          footerLabel: "Codigo de autenticidade",
          verificationPath: "report-authenticity.html",
          storage: match.authenticity && match.authenticity.storage || {}
        },
        artifact: {
          stored: !!(match.authenticity && match.authenticity.storage && match.authenticity.storage.storeExportArtifact),
          format: match && match.authenticity && match.authenticity.storage && match.authenticity.storage.storeExportArtifact ? "pdf" : "",
          fileName: match && match.authenticity && match.authenticity.storage && match.authenticity.storage.storeExportArtifact ? (match.reportId + ".pdf") : "",
          contentType: match && match.authenticity && match.authenticity.storage && match.authenticity.storage.storeExportArtifact ? "application/pdf" : ""
        }
      };
    }

    hashString(value) {
      let hash = 0;
      const text = String(value || "");
      for (let index = 0; index < text.length; index += 1) {
        hash = ((hash << 5) - hash) + text.charCodeAt(index);
        hash |= 0;
      }
      const normalized = Math.abs(hash).toString(16).padStart(8, "0");
      return (normalized + normalized + normalized + normalized + normalized + normalized + normalized + normalized).slice(0, 64);
    }

    routeRuntimeJobsEndpoint(screenId, endpointId, data) {
      if (screenId !== "admin.jobs" && screenId !== "runtime-jobs" && screenId !== "runtime.jobs.mine") {
        return null;
      }

      const id = data && (data.id || data.recordId);
      switch (endpointId) {
        case "read":
          return this.listRuntimeJobs(Object.assign({}, data || {}, {
            onlyMine: screenId === "runtime.jobs.mine"
          }));
        case "get":
          return this.getRuntimeJob(id);
        case "saveLayout":
          return this.saveLayout(data || {});
        case "restoreLayout":
          this.activeLayoutId = null;
          this.persistLayouts();
          return { ok: true, userLayout: this.buildUserLayout() };
        case "saveSort":
          return this.saveSort(data || {});
        case "deleteSort":
          return this.deleteSort(data && data.id);
        case "saveGroup":
          return this.saveGroup(data || {});
        case "deleteGroup":
          return this.deleteGroup(data && data.id);
        case "saveFilter":
          return this.saveFilter(data || {});
        case "deleteFilter":
          return this.deleteFilter(data && data.id);
        case "saveMobileTemplate":
          return this.saveMobileTemplate(data || {});
        case "deleteMobileTemplate":
          return this.deleteMobileTemplate(data && data.id);
        default:
          return null;
      }
    }

    routeAdminRuntimeEndpoint(screenId, endpointId, data) {
      const config = this.getAdminRuntimeConfig(screenId);
      if (!config) {
        return null;
      }
      const id = data && (data.id || data.recordId);
      if (endpointId === "runtime.admin.integrity.resign" && screenId === "admin.integridade") {
        return this.resignAdminIntegrityRecord(data || {});
      }
      switch (endpointId) {
        case "read":
          return this.listAdminRuntimeRows(screenId, data || {});
        case "get":
          return this.getAdminRuntimeRow(screenId, id);
        case "create":
          return this.createAdminRuntimeRow(screenId, data || {});
        case "update":
          return this.updateAdminRuntimeRow(screenId, id, data || {});
        case "delete":
          return this.deleteAdminRuntimeRow(screenId, id);
        case "saveLayout":
          return this.saveLayout(data || {});
        case "restoreLayout":
          this.activeLayoutId = null;
          this.persistLayouts();
          return { ok: true, userLayout: this.buildUserLayout() };
        case "saveSort":
          return this.saveSort(data || {});
        case "deleteSort":
          return this.deleteSort(data && data.id);
        case "saveGroup":
          return this.saveGroup(data || {});
        case "deleteGroup":
          return this.deleteGroup(data && data.id);
        case "saveFilter":
          return this.saveFilter(data || {});
        case "deleteFilter":
          return this.deleteFilter(data && data.id);
        case "saveMobileTemplate":
          return this.saveMobileTemplate(data || {});
        case "deleteMobileTemplate":
          return this.deleteMobileTemplate(data && data.id);
        default:
          return null;
      }
    }

    buildAdminRuntimeDefinition(screenId) {
      const config = this.getAdminRuntimeConfig(screenId);
      if (!config) {
        return null;
      }
      const editable = config.editable !== false;
      const api = {
        read: { endpointId: "read", method: "POST" },
        get: { endpointId: "get", method: "POST" },
        saveLayout: { endpointId: "saveLayout", method: "POST" },
        restoreLayout: { endpointId: "restoreLayout", method: "POST" },
        saveSort: { endpointId: "saveSort", method: "POST" },
        deleteSort: { endpointId: "deleteSort", method: "POST" },
        saveGroup: { endpointId: "saveGroup", method: "POST" },
        deleteGroup: { endpointId: "deleteGroup", method: "POST" },
        saveFilter: { endpointId: "saveFilter", method: "POST" },
        deleteFilter: { endpointId: "deleteFilter", method: "POST" },
        saveMobileTemplate: { endpointId: "saveMobileTemplate", method: "POST" },
        deleteMobileTemplate: { endpointId: "deleteMobileTemplate", method: "POST" },
        "runtime.messages.poll": { endpointId: "runtime.messages.poll", method: "POST" },
        "runtime.messages.ack": { endpointId: "runtime.messages.ack", method: "POST" }
      };
      if (editable) {
        api.create = { endpointId: "create", method: "POST" };
        api.update = { endpointId: "update", method: "POST" };
        api.delete = { endpointId: "delete", method: "POST" };
        api["runtime.lock.acquire"] = { endpointId: "runtime.lock.acquire", method: "POST" };
        api["runtime.lock.heartbeat"] = { endpointId: "runtime.lock.heartbeat", method: "POST" };
        api["runtime.lock.release"] = { endpointId: "runtime.lock.release", method: "POST" };
      }
      if (screenId === "admin.sessoes") {
        api["runtime.admin.forceLogout"] = { endpointId: "runtime.admin.forceLogout", method: "POST" };
      }
      if (config.extraApi && typeof config.extraApi === "object") {
        Object.keys(config.extraApi).forEach(function(key) {
          api[key] = global.CrudUtils.clone(config.extraApi[key]);
        });
      }

      const fields = config.fields;
      const readonlyFields = Object.keys(fields).filter(function(field) {
        return fields[field].editable === false;
      });
      return {
        schemaVersion: "1.0",
        pageType: "crud",
        screenId,
        program: {
          id: config.programId,
          module: "administracao",
          entity: config.entity,
          title: config.title,
          version: "1.0.0",
          subtitle: config.subtitle
        },
        permissions: {
          read: true,
          create: editable,
          edit: editable,
          delete: editable,
          saveLayout: true
        },
        dataSource: { api },
        api: global.CrudUtils.clone(api),
        runtime: {
          entityCode: config.entity,
          programId: config.programId,
          lock: { enabled: editable, modes: editable ? ["edit", "delete"] : [] },
          messages: { enabled: true, pollIntervalSeconds: 30, events: { enabled: true } }
        },
        dataModel: {
          primaryKey: "id",
          fields
        },
        crud: {
          query: { pageSize: 20, defaultSort: config.defaultSort || [{ field: "id", dir: "desc" }] },
          filter: {
            type: "window",
            mode: "basic",
            title: "Filtros",
            openOnLoad: false,
            showAppliedFilters: true,
            fields: config.filters.map(function(field) {
              const item = fields[field] || {};
              return {
                id: field,
                field,
                label: item.label || field,
                type: item.type === "boolean" || item.type === "enum" || item.type === "integer" ? item.type : "text",
                operator: item.type === "boolean" || item.type === "enum" || item.type === "integer" ? "eq" : "contains",
                options: item.options
              };
            })
          },
          grid: {
            pageable: true,
            sortable: true,
            filterable: true,
            resizable: true,
            reorderable: true,
            columnMenu: true,
            toolbar: (editable ? [{ id: "create", label: "Incluir", action: "create", icon: "plus", permission: "create" }] : []).concat([
              { id: "filters", label: "Filtros", action: "filters", icon: "filter" },
              { id: "refresh", label: "Atualizar", action: "refresh", icon: "arrow-rotate-cw" },
              { id: "layout", label: "Leiaute", action: "layout", icon: "columns", permission: "saveLayout" }
            ]),
            columns: config.columns.map(function(field) {
              const item = fields[field] || {};
              const column = { field, title: item.label || field, width: item.width || (item.type === "datetime" || item.type === "json" || item.type === "text" ? 220 : 150) };
              if (item.type === "integer") {
                column.align = "right";
              }
              return column;
            }),
            rowActions: [{ id: "view", label: "Visualizar", action: "view", icon: "eye", permission: "read" }].concat(editable ? [
              { id: "edit", label: "Alterar", action: "edit", icon: "pencil", permission: "edit" },
              { id: "delete", label: "Excluir", action: "delete", icon: "trash", permission: "delete" }
            ] : []),
            bulkActions: { enabled: false, actions: [] },
            print: { enabled: false, options: [] }
          },
          form: {
            id: screenId.replace(/\./g, "-") + "-form",
            mode: "popup",
            layout: "tabs",
            maximizeForm: true,
            title: { create: "Incluir " + config.title.toLowerCase(), view: "Detalhe de " + config.title.toLowerCase(), edit: "Alterar " + config.title.toLowerCase(), delete: "Excluir " + config.title.toLowerCase() },
            behavior: { closeOnSave: true, closeOnCancel: true },
            mobile: { showHeaderActions: Boolean(config.otherActions && config.otherActions.enabled !== false) },
            tabs: config.tabs,
            fields: Object.keys(fields).map(function(field) {
              const result = { field };
              if (readonlyFields.indexOf(field) !== -1) {
                result.renderAs = "readonly";
              }
              return result;
            }),
            logs: { enabled: false },
            print: { enabled: false, options: [] },
            otherActions: config.otherActions || { enabled: false, actions: [] }
          },
          userLayout: { enabled: true, storageKey: screenId.replace(/\./g, "-") + "-layout" }
        }
      };
    }

    getAdminRuntimeConfig(screenId) {
      const typeOptions = ["string", "text", "integer", "decimal", "boolean", "date", "datetime", "json", "option", "multi_option"].map(function(value) {
        return { value, text: value };
      });
      const statusOptions = [
        { value: "active", text: "Ativa" },
        { value: "revoked", text: "Revogada" },
        { value: "expired", text: "Expirada" }
      ];
      const userStatusOptions = [
        { value: "active", text: "Ativo" },
        { value: "inactive", text: "Inativo" },
        { value: "blocked", text: "Bloqueado" }
      ];
      const authSourceOptions = [
        { value: "local", text: "Local" },
        { value: "ldap", text: "LDAP" },
        { value: "sso", text: "SSO" },
        { value: "oauth", text: "OAuth" }
      ];
      const f = function(type, label, editable, nullable, extra) {
        return Object.assign({ type, label, editable: editable !== false, nullable: nullable !== false }, extra || {});
      };
      const commonDates = {
        created_at: f("datetime", "Criado em", false, false),
        updated_at: f("datetime", "Atualizado em", false, false)
      };
      const configs = {
        "admin.parametros": {
          programId: "admin-parametros",
          entity: "system_parameter",
          title: "Parametros",
          subtitle: "Cadastro dos parametros do sistema.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            code: f("string", "Codigo", true, false),
            name: f("string", "Nome", true, false),
            description: f("text", "Descricao", true, true, { editor: "textarea" }),
            data_type: f("enum", "Tipo", true, false, { options: typeOptions }),
            option_list_id: f("integer", "Lista de opcoes", true, true),
            required: f("boolean", "Obrigatorio", true, false),
            default_value: f("json", "Valor padrao", true, true, { editor: "textarea" }),
            enabled: f("boolean", "Ativo", true, false)
          }, commonDates),
          filters: ["code", "name", "data_type", "enabled"],
          columns: ["id", "code", "name", "data_type", "required", "enabled", "updated_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "code", "name", "data_type", "required", "enabled", "option_list_id"] },
            { id: "detalhes", title: "Detalhes", fields: ["description", "default_value"] },
            { id: "auditoria", title: "Auditoria", fields: ["created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "code", dir: "asc" }]
        },
        "admin.parametro-valores": {
          programId: "admin-parametro-valores",
          entity: "system_parameter_value",
          title: "Valores de Parametros",
          subtitle: "Valores vigentes por periodo e estabelecimento.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            parameter_id: f("integer", "Parametro", true, false),
            establishment_code: f("string", "Estabelecimento", true, true),
            starts_at: f("datetime", "Inicio", true, false),
            ends_at: f("datetime", "Fim", true, true),
            value: f("json", "Valor", true, true, { editor: "textarea" }),
            enabled: f("boolean", "Ativo", true, false)
          }, commonDates),
          filters: ["parameter_id", "establishment_code", "enabled"],
          columns: ["id", "parameter_id", "establishment_code", "starts_at", "ends_at", "value", "enabled"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "parameter_id", "establishment_code", "starts_at", "ends_at", "enabled"] },
            { id: "valor", title: "Valor", fields: ["value"] },
            { id: "auditoria", title: "Auditoria", fields: ["created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "starts_at", dir: "desc" }]
        },
        "admin.literais": {
          programId: "admin-literais",
          entity: "system_literal_translation",
          title: "Literais e Traducoes",
          subtitle: "Cadastro de literais por locale usados pelo frontend.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            code: f("string", "Chave", true, false),
            locale: f("string", "Locale", true, false),
            context: f("string", "Contexto", true, true),
            text: f("text", "Texto", true, false, { editor: "textarea" }),
            description: f("text", "Descricao", true, true, { editor: "textarea" }),
            enabled: f("boolean", "Ativo", true, false)
          }, commonDates),
          filters: ["code", "locale", "context", "enabled"],
          columns: ["id", "code", "locale", "context", "enabled", "updated_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "code", "locale", "context", "enabled"] },
            { id: "texto", title: "Texto", fields: ["text", "description"] },
            { id: "auditoria", title: "Auditoria", fields: ["created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "code", dir: "asc" }, { field: "locale", dir: "asc" }]
        },
        "admin.integracoes": {
          pageType: "custom",
          screenId: "admin.integracoes",
          program: {
            id: "admin-integracoes",
            title: "Integracoes",
            subtitle: "Cadastro, preview e execucao de importacao/exportacao",
            version: "1.0.0",
            screenId: "admin.integracoes"
          },
          custom: {
            mode: "iframe",
            entryUrl: "production/admin/import-export-mappings.html",
            frameTitle: "Integracoes administrativas"
          }
        },
        "admin.notificacoes": {
          programId: "admin-notificacoes",
          entity: "runtime_notification",
          title: "Notificacoes",
          subtitle: "Cadastro de notificacoes por usuario e grupo, com publicacao e rastreio de leitura.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            tenant_id: f("string", "Assinante", false, false),
            code: f("string", "Codigo", true, true),
            title: f("string", "Titulo", true, false),
            message: f("text", "Mensagem", true, false, { editor: "textarea" }),
            category: f("string", "Categoria", true, false),
            severity: f("enum", "Severidade", true, false, { options: [
              { value: "info", text: "Informacao" },
              { value: "warning", text: "Aviso" },
              { value: "error", text: "Erro" },
              { value: "success", text: "Sucesso" }
            ] }),
            status: f("enum", "Status", true, false, { options: [
              { value: "draft", text: "Rascunho" },
              { value: "published", text: "Publicada" },
              { value: "archived", text: "Arquivada" }
            ] }),
            action_required: f("boolean", "Exige acao", true, false),
            target_user_ids: f("json", "Usuarios destinatarios", true, true, { editor: "textarea" }),
            target_groups: f("json", "Grupos destinatarios", true, true, { editor: "textarea" }),
            link_program_id: f("string", "Programa vinculado", true, true),
            link_screen_id: f("string", "Screen ID vinculado", true, true),
            metadata: f("json", "Metadata", true, true, { editor: "textarea" }),
            expires_at: f("datetime", "Expira em", true, true),
            published_at: f("datetime", "Publicada em", true, true),
            created_by: f("string", "Criada por", false, true)
          }, commonDates),
          filters: ["code", "title", "category", "severity", "status"],
          columns: ["id", "code", "title", "category", "severity", "status", "published_at", "expires_at", "updated_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "tenant_id", "code", "title", "category", "severity", "status", "action_required"] },
            { id: "destinatarios", title: "Destinatarios", fields: ["target_user_ids", "target_groups", "link_program_id", "link_screen_id"] },
            { id: "mensagem", title: "Mensagem", fields: ["message", "metadata"] },
            { id: "controle", title: "Controle", fields: ["expires_at", "published_at", "created_by", "created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "updated_at", dir: "desc" }]
        },
        "admin.notificacao-destinatarios": {
          programId: "admin-notificacao-destinatarios",
          entity: "runtime_notification_recipient",
          title: "Destinatarios de Notificacoes",
          subtitle: "Acompanhamento de entrega e leitura por usuario destinatario.",
          editable: false,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            tenant_id: f("string", "Assinante", false, false),
            notification_id: f("integer", "Notificacao", false, false),
            user_id: f("string", "Usuario", false, false),
            user_name: f("string", "Nome do usuario", false, false),
            source_type: f("enum", "Origem", false, false, { options: [
              { value: "user", text: "Usuario" },
              { value: "group", text: "Grupo" }
            ] }),
            source_key: f("string", "Chave da origem", false, false),
            status: f("enum", "Status", false, false, { options: [
              { value: "pending", text: "Pendente" },
              { value: "delivered", text: "Entregue" },
              { value: "read", text: "Lida" }
            ] }),
            delivered_at: f("datetime", "Entregue em", false, true),
            read_at: f("datetime", "Lida em", false, true)
          }, commonDates),
          filters: ["notification_id", "user_id", "user_name", "source_type", "status"],
          columns: ["id", "notification_id", "user_id", "user_name", "source_type", "status", "delivered_at", "read_at", "updated_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "tenant_id", "notification_id", "user_id", "user_name", "source_type", "source_key", "status"] },
            { id: "entrega", title: "Entrega", fields: ["delivered_at", "read_at", "created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "updated_at", dir: "desc" }]
        },
        "admin.integridade": {
          programId: "admin-integridade",
          entity: "system_record_integrity",
          title: "Integridade Estrutural",
          subtitle: "Monitor administrativo das assinaturas estruturais.",
          editable: false,
          fields: {
            id: f("integer", "ID", false, false, { width: 80 }),
            table_name: f("string", "Tabela", false, false),
            record_id: f("integer", "Registro", false, false),
            integrity_schema_version: f("integer", "Schema da integridade", false, false),
            payload_hash: f("string", "Hash do payload", false, false),
            signature: f("string", "Assinatura", false, false),
            signed_by: f("string", "Assinado por", false, true),
            metadata: f("json", "Metadata", false, false, { editor: "textarea" }),
            signed_at: f("datetime", "Assinado em", false, false),
            last_check_status: f("enum", "Ultimo status", false, false, { options: [
              { value: "pending", text: "Pendente" },
              { value: "valid", text: "Valida" },
              { value: "invalid", text: "Invalida" }
            ] }),
            last_checked_at: f("datetime", "Ultima verificacao", false, true),
            last_error_message: f("text", "Ultimo erro", false, true, { editor: "textarea" })
          },
          filters: ["table_name", "record_id", "last_check_status", "signed_by"],
          columns: ["id", "table_name", "record_id", "last_check_status", "signed_by", "signed_at", "last_checked_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "table_name", "record_id", "integrity_schema_version", "last_check_status"] },
            { id: "assinatura", title: "Assinatura", fields: ["payload_hash", "signature", "signed_by", "signed_at"] },
            { id: "verificacao", title: "Verificacao", fields: ["last_checked_at", "last_error_message", "metadata"] }
          ],
          defaultSort: [{ field: "last_check_status", dir: "asc" }, { field: "last_checked_at", dir: "desc" }],
          extraApi: {
            "runtime.admin.integrity.resign": { endpointId: "runtime.admin.integrity.resign", method: "POST" }
          },
          otherActions: {
            enabled: true,
            label: "Acoes",
            icon: "more-vertical",
            actions: [
              {
                id: "resignIntegrity",
                label: "Reassinar",
                icon: "reload",
                endpointId: "runtime.admin.integrity.resign",
                permission: "read",
                visibleIn: ["view"],
                refreshGrid: true,
                confirm: {
                  title: "Reassinar registro estrutural",
                  message: "Deseja reassinar o registro {table_name}#{record_id}?",
                  confirmText: "Reassinar",
                  confirmIcon: "reload"
                },
                successMessage: "Registro reassinado."
              }
            ]
          }
        },
        "admin.listas-opcoes": {
          programId: "admin-listas-opcoes",
          entity: "system_option_list",
          title: "Listas de Opcoes",
          subtitle: "Cadastro de listas fechadas usadas por parametros.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            code: f("string", "Codigo", true, false),
            name: f("string", "Nome", true, false),
            description: f("text", "Descricao", true, true, { editor: "textarea" }),
            enabled: f("boolean", "Ativo", true, false)
          }, commonDates),
          filters: ["code", "name", "enabled"],
          columns: ["id", "code", "name", "enabled", "updated_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "code", "name", "enabled"] },
            { id: "detalhes", title: "Detalhes", fields: ["description"] },
            { id: "auditoria", title: "Auditoria", fields: ["created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "code", dir: "asc" }]
        },
        "admin.opcoes": {
          programId: "admin-opcoes",
          entity: "system_option",
          title: "Opcoes",
          subtitle: "Cadastro das opcoes de cada lista.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            option_list_id: f("integer", "Lista", true, false),
            code: f("string", "Codigo", true, false),
            description: f("string", "Descricao", true, false),
            position: f("integer", "Posicao", true, false),
            enabled: f("boolean", "Ativo", true, false),
            metadata: f("json", "Metadata", true, false, { editor: "textarea" })
          }, commonDates),
          filters: ["option_list_id", "code", "description", "enabled"],
          columns: ["id", "option_list_id", "code", "description", "position", "enabled"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "option_list_id", "code", "description", "position", "enabled"] },
            { id: "metadata", title: "Metadata", fields: ["metadata"] },
            { id: "auditoria", title: "Auditoria", fields: ["created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "option_list_id", dir: "asc" }, { field: "position", dir: "asc" }]
        },
        "admin.sessoes": {
          programId: "admin-sessoes",
          entity: "runtime_user_session",
          title: "Sessoes",
          subtitle: "Consulta das sessoes ativas, revogadas e dados do dispositivo.",
          editable: false,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            tenant_id: f("string", "Assinante", false, false),
            user_id: f("string", "Usuario", false, false),
            user_name: f("string", "Nome do usuario", false, true),
            session_id: f("string", "Sessao runtime", false, false),
            php_session_id: f("string", "Sessao PHP", false, true),
            status: f("enum", "Status", false, false, { options: statusOptions }),
            entered_at: f("datetime", "Entrada", false, false),
            device_name: f("string", "Dispositivo", false, true),
            user_agent: f("text", "User agent", false, true),
            operating_system: f("string", "Sistema operacional", false, true),
            browser: f("string", "Navegador", false, true),
            is_mobile: f("boolean", "Mobile", false, false),
            session_properties: f("json", "Propriedades da sessao", false, false),
            permission_snapshot: f("json", "Permissoes da sessao", false, false),
            revoked_by: f("string", "Revogado por", false, true),
            revoked_at: f("datetime", "Revogado em", false, true),
            revoke_reason: f("string", "Motivo", false, true),
            last_seen_at: f("datetime", "Ultima atividade", false, false)
          }, commonDates),
          filters: ["tenant_id", "user_id", "user_name", "session_id", "status"],
          columns: ["id", "status", "tenant_id", "user_id", "user_name", "session_id", "device_name", "is_mobile", "entered_at", "last_seen_at"],
          tabs: [
            { id: "identidade", title: "Identidade", fields: ["id", "tenant_id", "user_id", "user_name", "session_id", "php_session_id", "status"] },
            { id: "dispositivo", title: "Dispositivo", fields: ["device_name", "operating_system", "browser", "is_mobile", "user_agent"] },
            { id: "permissoes", title: "Permissoes", fields: ["session_properties", "permission_snapshot"] },
            { id: "revogacao", title: "Revogacao", fields: ["revoked_by", "revoked_at", "revoke_reason", "entered_at", "last_seen_at", "created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "last_seen_at", dir: "desc" }],
          otherActions: {
            enabled: true,
            label: "Acoes",
            icon: "more-vertical",
            actions: [{
              id: "forceLogout",
              label: "Derrubar sessao",
              icon: "logout",
              endpointId: "runtime.admin.forceLogout",
              visibleIn: ["view"],
              refreshGrid: true,
              confirm: { title: "Derrubar sessao", message: "Deseja derrubar a sessao {session_id} do usuario {user_id}?", confirmText: "Derrubar", confirmIcon: "logout" },
              successMessage: "Sessao revogada."
            }]
          }
        },
        "admin.usuarios": {
          programId: "admin-usuarios",
          entity: "auth_user",
          title: "Usuarios",
          subtitle: "Cadastro de usuarios do sistema, grupos e permissoes.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            tenant_id: f("string", "Tenant", false, false),
            username: f("string", "Usuario", false, false),
            display_name: f("string", "Nome", true, true),
            email: f("string", "E-mail", true, true),
            status: f("enum", "Status", true, false, { options: userStatusOptions }),
            groups: f("json", "Grupos", true, true, { editor: "textarea" }),
            permissions: f("json", "Permissoes", true, true, { editor: "textarea" }),
            auth_source: f("enum", "Origem de acesso", true, false, { options: authSourceOptions }),
            last_login_at: f("datetime", "Ultimo login", false, true),
            created_at: f("datetime", "Criado em", false, false),
            updated_at: f("datetime", "Atualizado em", false, false)
          }, commonDates),
          filters: ["tenant_id", "username", "status", "auth_source"],
          columns: ["id", "tenant_id", "username", "display_name", "status", "auth_source", "last_login_at", "updated_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "tenant_id", "username", "display_name", "email", "status", "auth_source"] },
            { id: "permissoes", title: "Permissoes", fields: ["groups", "permissions"] },
            { id: "seguranca", title: "Seguranca", fields: ["last_login_at"] },
            { id: "auditoria", title: "Auditoria", fields: ["created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "tenant_id", dir: "asc" }, { field: "username", dir: "asc" }],
          extraApi: {
            "runtime.admin.impersonateStart": { endpointId: "runtime.admin.impersonateStart", method: "POST" }
          },
          otherActions: {
            enabled: true,
            label: "Acoes",
            icon: "more-vertical",
            actions: [{
              id: "impersonate",
              label: "Entrar como usuario",
              icon: "login",
              endpointId: "runtime.admin.impersonateStart",
              prompt: {
                title: "Entrar como usuario",
                message: "Informe a justificativa da simulacao. A acao sera auditada.",
                confirmText: "Iniciar simulacao",
                fields: [{
                  name: "reason",
                  label: "Justificativa",
                  type: "textarea",
                  required: true,
                  maxLength: 1000
                }]
              },
              successMessage: "Simulacao iniciada."
            }]
          }
        },
        "admin.permissoes": Object.assign({}, {}, {
          programId: "admin-permissoes",
          entity: "auth_user",
          title: "Permissoes",
          subtitle: "Gerenciamento de grupos e permissoes dos usuarios.",
        }),
        "admin.usuario-assinantes": {
          programId: "admin-usuario-assinantes",
          entity: "auth_user_subscriber",
          title: "Usuarios por Assinante",
          subtitle: "Relacao usuario-assinante e sobrescritas de permissao por contexto.",
          editable: true,
          fields: Object.assign({
            id: f("integer", "ID", false, false, { width: 80 }),
            user_tenant_id: f("string", "Tenant do usuario", false, false),
            username: f("string", "Usuario", false, false),
            subscriber_code: f("string", "Assinante", true, false),
            default_subscriber: f("boolean", "Assinante padrao", true, false),
            enabled: f("boolean", "Ativo", true, false),
            permission_overrides: f("json", "Sobrescrita de permissoes", true, true, { editor: "textarea" }),
            metadata: f("json", "Metadados", true, true, { editor: "textarea" }),
            created_at: f("datetime", "Criado em", false, false),
            updated_at: f("datetime", "Atualizado em", false, false)
          }, commonDates),
          filters: ["user_tenant_id", "username", "subscriber_code", "enabled"],
          columns: ["id", "user_tenant_id", "username", "subscriber_code", "default_subscriber", "enabled", "updated_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "user_tenant_id", "username", "subscriber_code", "default_subscriber", "enabled"] },
            { id: "permissoes", title: "Permissoes", fields: ["permission_overrides"] },
            { id: "metadados", title: "Metadata", fields: ["metadata"] },
            { id: "auditoria", title: "Auditoria", fields: ["created_at", "updated_at"] }
          ],
          defaultSort: [{ field: "user_tenant_id", dir: "asc" }, { field: "username", dir: "asc" }, { field: "subscriber_code", dir: "asc" }]
        },
        "admin.transacoes": {
          programId: "admin-transacoes",
          entity: "runtime_transaction",
          title: "Transacoes",
          subtitle: "Consulta das transacoes executadas pelo runtime.",
          editable: false,
          fields: {
            id: f("integer", "ID", false, false, { width: 80 }),
            tenant_id: f("string", "Assinante", false, false),
            session_id: f("string", "Sessao", false, false),
            screen_id: f("string", "Tela", false, false),
            program_id: f("string", "Programa", false, true),
            entity_code: f("string", "Entidade", false, true),
            record_id: f("string", "Registro", false, true),
            endpoint_id: f("string", "Endpoint", false, false),
            action_id: f("string", "Acao", false, true),
            operation: f("string", "Operacao", false, false),
            status: f("string", "Status", false, false),
            request_context: f("json", "Contexto da requisicao", false, false),
            started_at: f("datetime", "Inicio", false, false),
            finished_at: f("datetime", "Fim", false, true)
          },
          filters: ["session_id", "screen_id", "entity_code", "record_id", "status"],
          columns: ["id", "status", "screen_id", "entity_code", "record_id", "session_id", "endpoint_id", "started_at", "finished_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "tenant_id", "session_id", "screen_id", "program_id", "entity_code", "record_id"] },
            { id: "acao", title: "Acao", fields: ["endpoint_id", "action_id", "operation", "status", "started_at", "finished_at"] },
            { id: "contexto", title: "Contexto", fields: ["request_context"] }
          ],
          defaultSort: [{ field: "started_at", dir: "desc" }]
        },
        "admin.logs-transacoes": {
          programId: "admin-logs-transacoes",
          entity: "runtime_transaction_log",
          title: "Logs de Transacoes",
          subtitle: "Consulta dos eventos, before, after, diff e metadata das transacoes.",
          editable: false,
          fields: {
            id: f("integer", "ID", false, false, { width: 80 }),
            transaction_id: f("integer", "Transacao", false, false),
            event_type: f("string", "Evento", false, false),
            message: f("text", "Mensagem", false, true),
            before_data: f("json", "Before", false, false),
            after_data: f("json", "After", false, false),
            diff_data: f("json", "Diff", false, false),
            metadata: f("json", "Metadata", false, false),
            created_at: f("datetime", "Criado em", false, false)
          },
          filters: ["transaction_id", "event_type", "message"],
          columns: ["id", "transaction_id", "event_type", "message", "created_at"],
          tabs: [
            { id: "geral", title: "Geral", fields: ["id", "transaction_id", "event_type", "message", "created_at"] },
            { id: "dados", title: "Dados", fields: ["before_data", "after_data", "diff_data", "metadata"] }
          ],
          defaultSort: [{ field: "created_at", dir: "desc" }]
        }
      };
      if (configs["admin.permissoes"]) {
        const basePermissionConfig = configs["admin.usuarios"];
        if (basePermissionConfig) {
          Object.assign(configs["admin.permissoes"], basePermissionConfig, {
            programId: "admin-permissoes",
            title: "Permissoes",
            subtitle: "Gerenciamento de grupos e permissoes dos usuarios."
          });
        }
      }
      return configs[screenId] || null;
    }

    getAdminRuntimeRows(screenId) {
      const state = this.loadJson(this.adminStorageKey) || {};
      if (!Array.isArray(state[screenId])) {
        state[screenId] = this.getDefaultAdminRuntimeRows(screenId);
        this.saveJson(this.adminStorageKey, state);
      }
      if (screenId === "admin.notificacoes") {
        this.syncDemoNotificationRecipients(state[screenId]);
      }
      if (screenId === "admin.notificacao-destinatarios" && !state[screenId].length) {
        this.syncDemoNotificationRecipients(state["admin.notificacoes"] || this.getAdminRuntimeRows("admin.notificacoes"));
        return this.getAdminRuntimeRows(screenId);
      }
      return state[screenId];
    }

    saveAdminRuntimeRows(screenId, rows) {
      const state = this.loadJson(this.adminStorageKey) || {};
      state[screenId] = rows;
      this.saveJson(this.adminStorageKey, state);
    }

    listAdminRuntimeRows(screenId, data) {
      let rows = this.getAdminRuntimeRows(screenId).slice();
      global.CrudUtils.ensureArray(data && data.filters).forEach(function(filter) {
        const field = filter && filter.field;
        const value = filter && filter.value;
        if (!field || value == null || value === "") {
          return;
        }
        rows = rows.filter(function(row) {
          return String(row[field] == null ? "" : row[field]).toLowerCase().indexOf(String(value).toLowerCase()) !== -1;
        });
      });
      const sort = Array.isArray(data && data.sort) && data.sort[0] || { field: "id", dir: "desc" };
      rows.sort(function(left, right) {
        const direction = sort.dir === "asc" ? 1 : -1;
        return String(left[sort.field] == null ? "" : left[sort.field]).localeCompare(String(right[sort.field] == null ? "" : right[sort.field])) * direction;
      });
      const skip = Number(data && data.skip || 0);
      const take = Number(data && (data.take || data.pageSize) || 20);
      return { data: rows.slice(skip, skip + take).map((row) => this.decorateAdminRuntimeRow(row, screenId)), total: rows.length };
    }

    getAdminRuntimeRow(screenId, id) {
      const row = this.getAdminRuntimeRows(screenId).find(function(item) {
        return String(item.id) === String(id);
      });
      if (!row) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro administrativo nao encontrado.", { id });
      }
      return this.decorateAdminRuntimeRow(row, screenId);
    }

    createAdminRuntimeRow(screenId, data) {
      const rows = this.getAdminRuntimeRows(screenId).slice();
      const values = Object.assign({}, data && data.values || data || {});
      const now = this.nowText();
      const nextId = rows.reduce(function(max, row) { return Math.max(max, Number(row.id) || 0); }, 0) + 1;
      const row = Object.assign({ id: nextId, created_at: now, updated_at: now }, values);
      rows.push(row);
      this.saveAdminRuntimeRows(screenId, rows);
      if (screenId === "admin.notificacoes") {
        this.syncDemoNotificationRecipients(rows);
      }
      return this.decorateAdminRuntimeRow(row, screenId);
    }

    updateAdminRuntimeRow(screenId, id, data) {
      const rows = this.getAdminRuntimeRows(screenId).slice();
      const values = Object.assign({}, data && data.values || {});
      const index = rows.findIndex(function(item) { return String(item.id) === String(id); });
      if (index === -1) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro administrativo nao encontrado.", { id });
      }
      rows[index] = Object.assign({}, rows[index], values, { updated_at: this.nowText() });
      this.saveAdminRuntimeRows(screenId, rows);
      if (screenId === "admin.notificacoes") {
        this.syncDemoNotificationRecipients(rows);
      }
      return this.decorateAdminRuntimeRow(rows[index], screenId);
    }

    deleteAdminRuntimeRow(screenId, id) {
      const rows = this.getAdminRuntimeRows(screenId).slice();
      const nextRows = rows.filter(function(item) {
        return String(item.id) !== String(id);
      });
      this.saveAdminRuntimeRows(screenId, nextRows);
      if (screenId === "admin.notificacoes") {
        this.syncDemoNotificationRecipients(nextRows);
      }
      return { ok: true };
    }

    resignAdminIntegrityRecord(data) {
      const rows = this.getAdminRuntimeRows("admin.integridade").slice();
      const id = data && (data.id || data.recordId || data.integrityId);
      const index = rows.findIndex(function(item) {
        return String(item.id) === String(id);
      });
      if (index === -1) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro de integridade nao encontrado.", { id: id });
      }
      const reason = String(data && data.reason || "Reassinatura manual via admin.integridade").trim();
      const now = this.nowText();
      const current = rows[index];
      const metadata = this.parseJsonObject(current.metadata);
      metadata.source = "admin.integridade";
      metadata.reason = reason;
      metadata.resignedBy = this.userId;
      metadata.resignedAt = now;
      rows[index] = Object.assign({}, current, {
        payload_hash: "sha256:" + String(current.table_name || "registro") + "-" + String(current.record_id || current.id) + "-" + now.replace(/[^0-9]/g, ""),
        signature: "hmac:" + String(current.table_name || "registro") + "-" + String(current.record_id || current.id) + "-" + now.replace(/[^0-9]/g, ""),
        signed_by: this.userId,
        signed_at: now,
        last_check_status: "valid",
        last_checked_at: now,
        last_error_message: "",
        metadata: JSON.stringify(metadata, null, 2),
        updated_at: now
      });
      this.saveAdminRuntimeRows("admin.integridade", rows);
      return {
        ok: true,
        integrity: this.decorateAdminRuntimeRow(rows[index], "admin.integridade")
      };
    }

    decorateAdminRuntimeRow(row, screenId) {
      const result = global.CrudUtils.clone(row || {});
      result._runtime = result._runtime || {
        version: "demo-" + screenId + "-" + result.id + "-" + String(result.updated_at || result.created_at || ""),
        lastModifiedAt: result.updated_at || result.created_at || null
      };
      return result;
    }

    nowText() {
      return new Date().toISOString().slice(0, 19).replace("T", " ");
    }

    parseJsonObject(value) {
      if (!value) {
        return {};
      }
      if (typeof value === "object") {
        return global.CrudUtils.clone(value);
      }
      try {
        return JSON.parse(String(value));
      } catch (_) {
        return {};
      }
    }

    syncDemoNotificationRecipients(notificationRows) {
      const rows = Array.isArray(notificationRows) ? notificationRows.slice() : [];
      const existing = this.loadJson(this.adminStorageKey) || {};
      const existingRecipients = Array.isArray(existing["admin.notificacao-destinatarios"]) ? existing["admin.notificacao-destinatarios"] : [];
      const existingMap = {};
      existingRecipients.forEach(function(item) {
        existingMap[String(item.notification_id) + "::" + String(item.user_id)] = item;
      });
      const recipientRows = [];
      let nextId = 1;
      const activeUsers = this.getDemoActiveUsers();
      rows.forEach((notification) => {
        if (!notification || String(notification.status || "").toLowerCase() !== "published") {
          return;
        }
        const targetUsers = this.parseDemoJsonList(notification.target_user_ids);
        const targetGroups = this.parseDemoJsonList(notification.target_groups);
        activeUsers.forEach((user) => {
          const normalizedGroups = (user.groups || []).map(function(item) {
            return String(item || "").toLowerCase();
          });
          const directMatch = targetUsers.indexOf(String(user.id)) >= 0;
          const matchedGroup = targetGroups.find(function(group) {
            return normalizedGroups.indexOf(String(group || "").toLowerCase()) >= 0;
          });
          if (!directMatch && !matchedGroup) {
            return;
          }
          const existingRecipient = existingMap[String(notification.id) + "::" + String(user.id)] || {};
          const recipientId = Number(existingRecipient.id) || nextId++;
          nextId = Math.max(nextId, recipientId + 1);
          recipientRows.push({
            id: recipientId,
            tenant_id: this.tenantId,
            notification_id: notification.id,
            user_id: user.id,
            user_name: user.name,
            source_type: directMatch ? "user" : "group",
            source_key: directMatch ? user.id : matchedGroup,
            status: existingRecipient.status || "pending",
            delivered_at: existingRecipient.delivered_at || null,
            read_at: existingRecipient.read_at || null,
            created_at: existingRecipient.created_at || notification.created_at || this.nowText(),
            updated_at: existingRecipient.updated_at || notification.updated_at || notification.created_at || this.nowText()
          });
        });
      });
      this.saveAdminRuntimeRows("admin.notificacao-destinatarios", recipientRows);
      return recipientRows;
    }

    getDemoActiveUsers() {
      const rows = this.getAdminRuntimeRows("admin.usuarios");
      return rows.filter(function(row) {
        return String(row.status || "active") === "active";
      }).map((row) => {
        return {
          id: String(row.username || row.user_id || ""),
          name: String(row.display_name || row.user_name || row.username || ""),
          groups: this.parseDemoPermissionList(row.groups)
        };
      });
    }

    parseDemoPermissionList(value) {
      if (Array.isArray(value)) {
        return value.map(function(item) { return String(item || ""); }).filter(Boolean);
      }
      if (!value) {
        return [];
      }
      try {
        const decoded = JSON.parse(String(value));
        if (Array.isArray(decoded)) {
          return decoded.map(function(item) { return String(item || ""); }).filter(Boolean);
        }
        if (decoded && Array.isArray(decoded.items)) {
          return decoded.items.map(function(item) { return String(item || ""); }).filter(Boolean);
        }
      } catch (_) {
      }
      return [];
    }

    parseDemoJsonList(value) {
      if (Array.isArray(value)) {
        return value.map(function(item) { return String(item || "").trim(); }).filter(Boolean);
      }
      if (value == null || value === "") {
        return [];
      }
      try {
        const decoded = JSON.parse(String(value));
        return this.parseDemoJsonList(decoded);
      } catch (_) {
        return String(value).split(/[,;\n]+/).map(function(item) {
          return String(item || "").trim();
        }).filter(Boolean);
      }
    }

    getDefaultAdminRuntimeRows(screenId) {
      const now = this.nowText();
      const json = function(value) {
        return JSON.stringify(value, null, 2);
      };
      const rows = {
        "admin.parametros": [
          { id: 1, code: "subscriber.enabled", name: "Habilitar conceito de assinante", description: "Controla selecao de assinante apos login.", data_type: "boolean", option_list_id: null, required: true, default_value: "false", enabled: true, created_at: "2026-05-08 09:00:00", updated_at: "2026-05-08 09:00:00" }
        ],
        "admin.parametro-valores": [
          { id: 1, parameter_id: 1, establishment_code: "", starts_at: "2000-01-01 00:00:00", ends_at: null, value: "false", enabled: true, created_at: "2026-05-08 09:00:00", updated_at: "2026-05-08 09:00:00" }
        ],
        "admin.literais": [
          { id: 1, code: "literal.button.confirm", locale: "pt-BR", context: "crud", text: "Confirmar", description: "Botao padrao de confirmacao.", enabled: true, created_at: now, updated_at: now },
          { id: 2, code: "literal.button.cancel", locale: "pt-BR", context: "crud", text: "Cancelar", description: "Botao padrao de cancelamento.", enabled: true, created_at: now, updated_at: now },
          { id: 3, code: "validation.title.inconsistencies", locale: "pt-BR", context: "validation", text: "Inconsistencias encontradas", description: "Titulo padrao de validacao.", enabled: true, created_at: now, updated_at: now },
          { id: 4, code: "validation.message.field_required", locale: "pt-BR", context: "validation", text: "{fieldLabel} e obrigatorio.", description: "Mensagem de campo obrigatorio.", enabled: true, created_at: now, updated_at: now }
        ],
        "admin.integridade": [
          {
            id: 1,
            table_name: "screen_definition",
            record_id: 101,
            integrity_schema_version: 1,
            payload_hash: "sha256:screen-definition-demo-101",
            signature: "hmac:screen-definition-demo-101",
            signed_by: "builder@1.0.0",
            metadata: json({
              source: "builder.publish",
              reason: "Assinatura inicial do registro",
              programCode: "cadastros.clientes",
              builderProgramVersionId: 4
            }),
            signed_at: "2026-05-15 08:15:00",
            last_check_status: "invalid",
            last_checked_at: "2026-05-15 10:20:00",
            last_error_message: "Assinatura divergente apos alteracao externa."
          },
          {
            id: 2,
            table_name: "runtime_endpoint",
            record_id: 205,
            integrity_schema_version: 1,
            payload_hash: "sha256:runtime-endpoint-demo-205",
            signature: "hmac:runtime-endpoint-demo-205",
            signed_by: "builder@1.0.0",
            metadata: json({
              source: "builder.publish",
              reason: "Assinatura inicial do registro",
              programCode: "admin.integridade",
              builderProgramVersionId: 7
            }),
            signed_at: "2026-05-15 09:40:00",
            last_check_status: "valid",
            last_checked_at: "2026-05-15 10:21:00",
            last_error_message: ""
          }
        ],
        "admin.notificacoes": [
          {
            id: 1,
            tenant_id: this.tenantId,
            code: "notif_bem_vindo",
            title: "Nova central de notificacoes",
            message: "A Home agora permite acompanhar envio por usuario e grupo, com leitura individual.",
            category: "Sistema",
            severity: "info",
            status: "published",
            action_required: false,
            target_user_ids: json([this.userId]),
            target_groups: json(["admin"]),
            link_program_id: "admin-literais",
            link_screen_id: "admin.literais",
            metadata: json({ source: "demo" }),
            expires_at: null,
            published_at: now,
            created_by: "admin",
            created_at: now,
            updated_at: now
          },
          {
            id: 2,
            tenant_id: this.tenantId,
            code: "notif_revisar_clientes",
            title: "Revisar clientes pendentes",
            message: "Existem cadastros aguardando revisao operacional.",
            category: "Operacional",
            severity: "warning",
            status: "published",
            action_required: true,
            target_user_ids: json([]),
            target_groups: json(["vendas"]),
            link_program_id: "clientes-crud",
            link_screen_id: "cadastros.clientes",
            metadata: json({ source: "demo", priority: "alta" }),
            expires_at: null,
            published_at: now,
            created_by: "admin",
            created_at: now,
            updated_at: now
          }
        ],
        "admin.notificacao-destinatarios": [],
        "admin.listas-opcoes": [
          { id: 1, code: "sim_nao", name: "Sim/Nao", description: "Lista demonstrativa.", enabled: true, created_at: now, updated_at: now }
        ],
        "admin.opcoes": [
          { id: 1, option_list_id: 1, code: "S", description: "Sim", position: 1, enabled: true, metadata: "{}", created_at: now, updated_at: now },
          { id: 2, option_list_id: 1, code: "N", description: "Nao", position: 2, enabled: true, metadata: "{}", created_at: now, updated_at: now }
        ],
        "admin.sessoes": [
          { id: 1, tenant_id: this.tenantId, user_id: this.userId, user_name: "Usuario Demo", session_id: this.sessionId, php_session_id: "php-demo", status: "active", entered_at: now, device_name: "Desktop Windows", user_agent: "DemoMockHttpClient", operating_system: "Windows", browser: "Chromium", is_mobile: false, session_properties: json({ demo: true }), permission_snapshot: json({ user: { groups: ["admin"], permissions: ["*"] } }), revoked_by: null, revoked_at: null, revoke_reason: null, last_seen_at: now, created_at: now, updated_at: now }
        ],
        "admin.usuarios": [
          {
            id: 1,
            tenant_id: this.tenantId,
            username: "admin",
            display_name: "Administrador",
            email: "admin@construtorpg.local",
            status: "active",
            groups: json({ items: ["admin"], default: ["admin.read", "admin.write"] }),
            permissions: json({ all: true }),
            auth_source: "local",
            last_login_at: now,
            created_at: now,
            updated_at: now
          },
          {
            id: 2,
            tenant_id: this.tenantId,
            username: this.userId,
            display_name: this.userId === "demo" ? "Usuario Demo" : this.userId,
            email: (this.userId === "demo" ? "demo" : this.userId) + "@example.com",
            status: "active",
            groups: json(["vendas"]),
            permissions: json(["home.read", "clientes.read", "jobs.read"]),
            auth_source: "local",
            last_login_at: now,
            created_at: now,
            updated_at: now
          }
        ],
        "admin.usuario-assinantes": [
          {
            id: 1,
            user_tenant_id: this.tenantId,
            username: "admin",
            subscriber_code: "assinante-demo",
            default_subscriber: true,
            enabled: true,
            permission_overrides: json({ clientes: { read: true }, usuarios: { read: true } }),
            metadata: json({ observacao: "Assinante principal para administradores" }),
            created_at: now,
            updated_at: now
          }
        ],
        "admin.transacoes": [
          { id: 1, tenant_id: this.tenantId, session_id: this.sessionId, screen_id: "cadastros.clientes", program_id: "clientes-crud", entity_code: "cliente", record_id: "1", endpoint_id: "read", action_id: "read", operation: "entity.crud", status: "succeeded", lock_token: "", request_context: json({ take: 20 }), started_at: now, finished_at: now }
        ],
        "admin.logs-transacoes": [
          { id: 1, transaction_id: 1, event_type: "runtime.request", message: "Chamada runtime recebida.", before_data: "{}", after_data: "{}", diff_data: "{}", metadata: json({ screenId: "cadastros.clientes" }), created_at: now }
        ]
      };
      if (!Array.isArray(rows["admin.permissoes"]) && Array.isArray(rows["admin.usuarios"])) {
        rows["admin.permissoes"] = rows["admin.usuarios"].map(function(item) {
          return global.CrudUtils.clone(item);
        });
      }
      return global.CrudUtils.clone(rows[screenId] || []);
    }

    listRuntimeJobs(data) {
      let rows = this.getDefaultRuntimeJobs().concat(this.getProcessRuntimeJobs());
      if (data && data.onlyMine) {
        rows = rows.filter((row) => String(row.user_id || "") === String(this.userId || "demo"));
      }
      const filters = Array.isArray(data && data.filters) ? data.filters : [];
      filters.forEach(function(filter) {
        const field = filter && filter.field;
        const value = filter && filter.value;
        if (!field || value == null || value === "") {
          return;
        }
        rows = rows.filter(function(row) {
          return String(row[field] || "").toLowerCase().indexOf(String(value).toLowerCase()) !== -1;
        });
      });

      const sort = Array.isArray(data && data.sort) && data.sort[0] || { field: "created_at", dir: "desc" };
      rows = rows.slice().sort(function(left, right) {
        const direction = sort.dir === "asc" ? 1 : -1;
        return String(left[sort.field] || "").localeCompare(String(right[sort.field] || "")) * direction;
      });
      const skip = Number(data && data.skip || 0);
      const take = Number(data && data.take || data && data.pageSize || 20);
      return {
        data: rows.slice(skip, skip + take),
        total: rows.length
      };
    }

    getRuntimeJob(id) {
      const record = this.getDefaultRuntimeJobs().concat(this.getProcessRuntimeJobs()).find(function(item) {
        return String(item.id) === String(id);
      });
      if (!record) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Job nao encontrado.", { id });
      }
      return global.CrudUtils.clone(record);
    }

    getDefaultRuntimeJobs() {
      return [
        {
          id: 101,
          job_type: "cliente.email_confirmation",
          status: "succeeded",
          attempts: 1,
          entity_code: "cliente",
          record_id: "12",
          action_id: "create",
          transaction_id: 501,
          user_id: "demo",
          created_at: "2026-05-07 14:05:22",
          started_at: "2026-05-07 14:05:30",
          finished_at: "2026-05-07 14:05:31",
          last_error: "",
          payload: "{\n  \"clienteId\": 12,\n  \"email\": \"pedidos@litoralfoods.test\"\n}",
          result: "{\n  \"delivery\": \"mailer\",\n  \"mode\": \"prepared\"\n}"
        },
        {
          id: 102,
          job_type: "cliente.email_confirmation",
          status: "failed",
          attempts: 2,
          entity_code: "cliente",
          record_id: "6",
          action_id: "create",
          transaction_id: 502,
          user_id: "demo",
          created_at: "2026-05-07 14:12:10",
          started_at: "2026-05-07 14:13:00",
          finished_at: "2026-05-07 14:13:01",
          last_error: "E-mail do cliente invalido para confirmacao.",
          payload: "{\n  \"clienteId\": 6,\n  \"email\": \"\"\n}",
          result: "{\n  \"exception\": \"RuntimeException\"\n}"
        },
        {
          id: 103,
          job_type: "cliente.whatsapp_welcome",
          status: "succeeded",
          attempts: 1,
          entity_code: "cliente",
          record_id: "1",
          action_id: "sendWhatsapp",
          transaction_id: 503,
          user_id: "demo",
          created_at: "2026-05-07 15:20:00",
          started_at: "2026-05-07 15:20:05",
          finished_at: "2026-05-07 15:20:05",
          last_error: "",
          payload: "{\n  \"clienteId\": 1,\n  \"telefone\": \"(85) 98888-1001\"\n}",
          result: "{\n  \"delivery\": \"whatsapp\",\n  \"mode\": \"prepared\"\n}"
        }
      ];
    }

    startClientProcess(data) {
      const parameters = data && data.parameters || {};
      const resultType = String(parameters.resultado || parameters.resultType || "grid");
      const now = new Date();
      const job = {
        id: "proc-" + now.getTime().toString(36) + "-" + Math.random().toString(36).slice(2, 7),
        job_type: "clientes.processamento",
        status: "queued",
        attempts: 0,
        entity_code: "cliente",
        record_id: "",
        action_id: "process",
        transaction_id: "",
        user_id: this.userId || "demo",
        created_at: now.toISOString(),
        started_at: "",
        finished_at: "",
        payload: parameters,
        resultType,
        result: null,
        last_error: "",
        notified: false
      };
      this.processJobs.unshift(job);
      this.persistProcessJobs();

      if (resultType === "job") {
        return {
          ok: true,
          status: "queued",
          message: "Job iniciado. Acompanhe o termino pelo icone de jobs no appbar.",
          job: this.toProcessJobSummary(job),
          wait: { mode: "none" },
          result: {
            type: "job",
            message: "O job foi iniciado e seguira em segundo plano.",
            job: this.toProcessJobSummary(job)
          }
        };
      }

      return {
        ok: true,
        status: "queued",
        message: "Processamento iniciado.",
        job: this.toProcessJobSummary(job),
        wait: {
          mode: "polling",
          pollIntervalSeconds: 1
        }
      };
    }

    getClientProcessStatus(data) {
      this.updateProcessJobsProgress();
      const id = String(data && (data.jobId || data.id) || "");
      const job = this.processJobs.find(function(item) {
        return String(item.id) === id;
      });
      if (!job) {
        throw global.CrudUtils.makeError("PROCESS_JOB_NOT_FOUND", "Job de processamento nao encontrado.", { id });
      }

      if (job.status !== "succeeded" && job.status !== "failed") {
        return {
          ok: true,
          status: job.status,
          message: job.status === "running" ? "Processamento em andamento." : "Job aguardando execucao.",
          job: this.toProcessJobSummary(job)
        };
      }

      return {
        ok: true,
        status: job.status,
        message: job.status === "succeeded" ? "Processamento concluido." : "Processamento falhou.",
        job: this.toProcessJobSummary(job),
        result: job.result || this.buildClientProcessResult(job)
      };
    }

    runCustomCodePdmAssistant(data) {
      const parameters = data && data.parameters && typeof data.parameters === "object" ? data.parameters : {};
      const familia = parameters.familia || "";
      const grupo = parameters.grupo || "";
      const linha = parameters.linha || "";
      const previewCode = [
        "PDM",
        this.normalizeCodeSegment(familia || "GERAL"),
        this.normalizeCodeSegment(grupo || "PADRAO"),
        this.normalizeCodeSegment(linha || "ITEM"),
        "0001"
      ].join("-");
      return {
        ok: true,
        status: "completed",
        message: "Confira a previsao do codigo antes de aplicar.",
        result: {
          type: "properties",
          message: "Confira a previsao do codigo antes de aplicar.",
          previewTitle: "Previsao do codigo PDM",
          previewCode: previewCode,
          values: {
            familia: familia,
            grupo: grupo,
            linha: linha
          }
        }
      };
    }

    updateProcessJobsProgress() {
      const now = Date.now();
      let changed = false;
      this.processJobs.forEach((job) => {
        const created = new Date(job.created_at).getTime();
        const elapsed = Number.isNaN(created) ? 0 : now - created;
        if (job.status === "queued" && elapsed >= 500) {
          job.status = "running";
          job.attempts = Math.max(1, Number(job.attempts || 0));
          job.started_at = job.started_at || new Date(now).toISOString();
          changed = true;
        }
        if ((job.status === "queued" || job.status === "running") && elapsed >= 1800) {
          job.status = "succeeded";
          job.finished_at = job.finished_at || new Date(now).toISOString();
          job.result = this.buildClientProcessResult(job);
          changed = true;
        }
      });
      if (changed) {
        this.persistProcessJobs();
      }
    }

    buildClientProcessResult(job) {
      const parameters = job && job.payload || {};
      const resultType = String(job && job.resultType || parameters.resultado || "grid");
      if (resultType === "message") {
        return {
          type: "message",
          message: "Nenhuma inconsistencia encontrada para os parametros informados."
        };
      }
      if (resultType === "report") {
        return {
          type: "report",
          title: "Relatorio preparado",
          message: "O relatorio foi gerado em uma pagina separada.",
          url: "examples/reports/clientes-processamento.html?jobId=" + encodeURIComponent(job.id),
          linkText: "Abrir relatorio"
        };
      }
      if (resultType === "job") {
        return {
          type: "job",
          message: "Job concluido em segundo plano.",
          job: this.toProcessJobSummary(job)
        };
      }

      const status = String(parameters.status || "TODOS");
      const rows = this.records.filter(function(record) {
        return status === "TODOS" || String(record.status) === status;
      }).slice(0, 6).map(function(record) {
        return {
          id: record.id,
          nome: record.nome,
          status: record.status,
          uf: record.uf,
          valor_total: record.valor_total,
          qtde_pedidos: record.qtde_pedidos
        };
      });
      return {
        type: "grid",
        title: "Clientes processados",
        columns: [
          { field: "id", title: "ID", width: 80 },
          { field: "nome", title: "Nome", width: 220 },
          { field: "status", title: "Status", width: 120 },
          { field: "uf", title: "UF", width: 80 },
          { field: "valor_total", title: "Valor total", width: 140, format: "{0:c2}" },
          { field: "qtde_pedidos", title: "Pedidos", width: 110 }
        ],
        data: rows,
        total: rows.length,
        pageSize: 5
      };
    }

    getProcessRuntimeJobs() {
      this.updateProcessJobsProgress();
      return this.processJobs.map((job) => {
        return {
          id: job.id,
          job_type: job.job_type,
          status: job.status,
          attempts: job.attempts || 0,
          entity_code: job.entity_code || "cliente",
          record_id: job.record_id || "",
          action_id: job.action_id || "process",
          transaction_id: job.transaction_id || "",
          user_id: job.user_id || this.userId || "demo",
          created_at: this.formatRuntimeDate(job.created_at),
          started_at: this.formatRuntimeDate(job.started_at),
          finished_at: this.formatRuntimeDate(job.finished_at),
          last_error: job.last_error || "",
          payload: JSON.stringify(job.payload || {}, null, 2),
          result: JSON.stringify(job.result || {}, null, 2)
        };
      });
    }

    toProcessJobSummary(job) {
      return {
        id: job.id,
        status: job.status,
        type: job.job_type,
        title: "Processamento de clientes"
      };
    }

    formatRuntimeDate(value) {
      if (!value) {
        return "";
      }
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return String(value);
      }
      return kendo.toString(date, "yyyy-MM-dd HH:mm:ss");
    }

    routeSystemRuntimeEndpoint(endpointId, data) {
      switch (endpointId) {
        case "runtime.lock.acquire":
          return this.acquireRuntimeLock(data || {});
        case "runtime.lock.heartbeat":
          return this.heartbeatRuntimeLock(data || {});
        case "runtime.lock.release":
          return this.releaseRuntimeLock(data || {});
        case "runtime.messages.poll":
          return this.pollRuntimeMessages();
        case "runtime.messages.ack":
          return this.ackRuntimeMessages(data || {});
        case "runtime.admin.forceLogout":
          return this.forceLogoutRuntimeUser(data || {});
        case "runtime.admin.impersonateStart":
          return this.startRuntimeImpersonation(data || {});
        default:
          return null;
      }
    }

    startRuntimeImpersonation(data) {
      const record = data.record || {};
      const reason = String(data.reason || "").trim();
      if (!reason) {
        throw global.CrudUtils.makeError("IMPERSONATION_REASON_REQUIRED", "Informe a justificativa da simulacao.");
      }
      const username = String(data.targetUsername || record.username || "usuario").trim();
      const tenantId = String(data.targetTenantId || record.tenant_id || "default").trim();
      const sessionId = "sess-impersonated-" + Date.now().toString(36);
      return {
        ok: true,
        authenticated: true,
        tokenType: "Bearer",
        token: "mock-impersonation-token",
        tenantId,
        user: {
          id: username,
          username,
          name: record.display_name || username,
          groups: [],
          permissions: ["home.read", "clientes.read"]
        },
        session: {
          sessionId,
          status: "active",
          impersonation: {
            enabled: true,
            actorUserId: "admin",
            actorUserName: "Administrador Demo",
            actorSessionId: "sess-demo",
            targetUserId: username,
            targetUserName: record.display_name || username,
            reason,
            startedAt: new Date().toISOString(),
            expiresAt: new Date(Date.now() + 60 * 60 * 1000).toISOString()
          }
        },
        impersonation: {
          enabled: true,
          actorUserId: "admin",
          actorUserName: "Administrador Demo",
          actorSessionId: "sess-demo",
          targetUserId: username,
          targetUserName: record.display_name || username,
          reason,
          startedAt: new Date().toISOString(),
          expiresAt: new Date(Date.now() + 60 * 60 * 1000).toISOString()
        },
        effects: [{ action: "switchSession", message: "Simulacao iniciada." }]
      };
    }

    routeHomeRuntimeEndpoint(screenId, endpointId, data) {
      if (this.isSessionRevoked() && endpointId !== "runtime.messages.poll") {
        throw global.CrudUtils.makeError("SESSION_REVOKED", "Sua sessao foi encerrada.", {
          reason: "Sessao encerrada no mock."
        });
      }
      const systemResponse = this.routeSystemRuntimeEndpoint(endpointId, data || {});
      if (systemResponse) {
        return systemResponse;
      }
      switch (endpointId) {
        case "home.chat.contacts":
          return this.getHomeChatContacts(data || {});
        case "home.chat.history":
          return this.getHomeChatHistory(data || {});
        case "home.chat.send":
          return this.sendHomeChatMessage(data || {});
        case "home.support.onlineUsers":
          return this.getHomeSupportOnlineUsers(data || {});
        case "home.support.history":
          return this.getHomeSupportHistory(data || {});
        case "home.support.send":
          return this.sendHomeSupportMessage(data || {});
        case "home.support.createRequest":
          return this.createHomeSupportRequest(data || {});
        case "home.support.requestStatus":
          return this.getHomeSupportRequestStatus(data || {});
        case "home.aiChat.history":
          return this.getHomeAiChatHistory(data || {});
        case "home.aiChat.send":
          return this.sendHomeAiChatMessage(data || {});
        case "home.notifications.list":
          return this.getHomeNotifications(data || {});
        case "home.notifications.ack":
          return this.ackHomeNotifications(data || {});
        case "home.alerts.list":
          return this.getHomeAlerts(data || {});
        case "home.requests.list":
          return this.getHomeRequests(data || {});
        case "home.jobs.list":
          return this.getHomeJobs(data || {});
        case "home.subscriber.change":
          return this.changeHomeSubscriber(data || {});
        default:
          throw global.CrudUtils.makeError("HOME_RUNTIME_ENDPOINT_NOT_FOUND", "Endpoint runtime da Home nao encontrado.", { screenId, endpointId });
      }
    }

    acquireRuntimeLock(data) {
      this.expireRuntimeLocks();
      const entityCode = String(data.entityCode || "cliente");
      const recordId = String(data.recordId || data.id || "");
      const actionId = String(data.actionId || data.mode || "edit");
      if (!recordId) {
        throw global.CrudUtils.makeError("LOCK_TARGET_REQUIRED", "Informe o registro para controlar o semaforo.");
      }
      const existing = this.runtimeState.locks.find((item) => {
        return item.status === "active" && item.entityCode === entityCode && item.recordId === recordId;
      });
      if (existing && existing.sessionId !== this.sessionId) {
        throw global.CrudUtils.makeError("RECORD_LOCKED", "Este registro esta sendo alterado por outro usuario.", {
          ownerName: existing.userName,
          expiresAt: existing.expiresAt
        });
      }

      const now = Date.now();
      const lock = existing || {
        token: "demo-lock-" + now.toString(36) + "-" + Math.random().toString(36).slice(2, 8),
        entityCode,
        recordId,
        actionId,
        userId: this.userId,
        userName: this.userId === "demo" ? "Usuario Demo" : this.userId,
        sessionId: this.sessionId,
        status: "active",
        acquiredAt: new Date(now).toISOString()
      };
      lock.lastSeenAt = new Date(now).toISOString();
      lock.expiresAt = new Date(now + 300000).toISOString();
      if (!existing) {
        this.runtimeState.locks.push(lock);
      }
      this.persistRuntimeState();
      return {
        lock: {
          status: "acquired",
          mode: "block",
          token: lock.token,
          transactionId: "demo-transaction",
          expiresAt: lock.expiresAt,
          heartbeatIntervalSeconds: 60,
          owner: null,
          policy: {
            source: "program_entity_action",
            mode: "block",
            stalePolicy: "block",
            lockTtlSeconds: 300,
            heartbeatIntervalSeconds: 60
          }
        },
        _runtime: {
          lockToken: lock.token,
          transactionId: "demo-transaction"
        }
      };
    }

    heartbeatRuntimeLock(data) {
      const token = String(data.lockToken || data._runtime && data._runtime.lockToken || "");
      const lock = this.runtimeState.locks.find((item) => item.status === "active" && item.token === token);
      if (!lock || lock.sessionId !== this.sessionId) {
        throw global.CrudUtils.makeError("LOCK_EXPIRED", "O semaforo deste registro expirou.");
      }
      const now = Date.now();
      lock.lastSeenAt = new Date(now).toISOString();
      lock.expiresAt = new Date(now + 300000).toISOString();
      this.persistRuntimeState();
      return {
        lock: {
          status: "active",
          mode: "block",
          token: lock.token,
          expiresAt: lock.expiresAt,
          heartbeatIntervalSeconds: 60,
          policy: {
            source: "program_entity_action",
            lockTtlSeconds: 300
          }
        }
      };
    }

    releaseRuntimeLock(data) {
      const token = String(data.lockToken || data._runtime && data._runtime.lockToken || "");
      this.runtimeState.locks.forEach((lock) => {
        if (lock.status === "active" && lock.token === token && lock.sessionId === this.sessionId) {
          lock.status = "released";
          lock.releasedAt = new Date().toISOString();
        }
      });
      this.persistRuntimeState();
      return { ok: true, released: true };
    }

    pollRuntimeMessages() {
      const messages = this.runtimeState.messages.filter((message) => {
        return (message.status === "pending" || message.status === "delivered") &&
          (message.targetUserId === this.userId || message.targetSessionId === this.sessionId);
      });
      messages.forEach(function(message) {
        message.status = "delivered";
        message.deliveredAt = new Date().toISOString();
      });
      this.persistRuntimeState();
      return {
        messages: messages.map(function(message) {
          return {
            id: message.id,
            type: message.type,
            severity: message.severity,
            title: message.title,
            message: message.message,
            metadata: message.metadata || {},
            actionRequired: message.actionRequired === true,
            createdAt: message.createdAt
          };
        })
      };
    }

    ackRuntimeMessages(data) {
      const ids = global.CrudUtils.ensureArray(data.ids || (data.id ? [data.id] : [])).map(String);
      this.runtimeState.messages.forEach(function(message) {
        if (ids.indexOf(String(message.id)) !== -1) {
          message.status = "acknowledged";
          message.acknowledgedAt = new Date().toISOString();
        }
      });
      this.persistRuntimeState();
      return { ok: true, count: ids.length };
    }

    forceLogoutRuntimeUser(data) {
      const targetUserId = String(data.targetUserId || data.userId || this.userId);
      const targetSessionId = String(data.targetSessionId || data.sessionId || "");
      const reason = String(data.reason || "Sessao encerrada pelo administrador.");
      this.runtimeState.revokedSessions.push({
        userId: targetUserId,
        sessionId: targetSessionId,
        reason,
        revokedAt: new Date().toISOString()
      });
      this.runtimeState.locks.forEach(function(lock) {
        if (lock.status === "active" && (lock.userId === targetUserId || lock.sessionId === targetSessionId)) {
          lock.status = "revoked";
          lock.releasedAt = new Date().toISOString();
        }
      });
      this.runtimeState.messages.push({
        id: "msg-" + Date.now().toString(36),
        targetUserId,
        targetSessionId: targetSessionId || null,
        type: "force_logout",
        severity: "error",
        title: "Sessao encerrada",
        message: reason,
        actionRequired: true,
        status: "pending",
        metadata: { reason },
        createdAt: new Date().toISOString()
      });
      this.persistRuntimeState();
      return { ok: true, revokedSessions: 1, releasedLocks: 1 };
    }

    isSessionRevoked() {
      return this.runtimeState.revokedSessions.some((item) => {
        return item.sessionId === this.sessionId || (!item.sessionId && item.userId === this.userId);
      });
    }

    expireRuntimeLocks() {
      const now = Date.now();
      this.runtimeState.locks.forEach(function(lock) {
        if (lock.status === "active" && new Date(lock.expiresAt).getTime() < now) {
          lock.status = "expired";
          lock.releasedAt = new Date().toISOString();
        }
      });
    }

    getHomeSubscriberOptions() {
      return [
        {
          id: "principal",
          name: "Principal",
          principal: true
        },
        {
          id: "assinante-demo",
          name: "Empresa Demonstracao",
          document: "00.000.000/0001-00",
          label: "Assinante"
        },
        {
          id: "assinante-filial",
          name: "Empresa Filial",
          document: "11.111.111/0001-11",
          label: "Assinante"
        }
      ];
    }

    changeHomeSubscriber(data) {
      const subscriberId = String(data && (data.subscriberId || data.id) || "").trim();
      const selected = this.getHomeSubscriberOptions().find(function(item) {
        return String(item.id) === subscriberId;
      }) || data && data.subscriber || null;
      if (!selected) {
        throw global.CrudUtils.makeError("SUBSCRIBER_NOT_FOUND", "Assinante nao encontrado.", { subscriberId });
      }
      return {
        ok: true,
        currentSubscriber: global.CrudUtils.clone(selected),
        changedAt: new Date().toISOString()
      };
    }

    list(query) {
      this.ensureRecords();
      let rows = this.records.slice();
      rows = this.applyCustomFilters(rows, query.filters || []);
      rows = this.applyKendoFilter(rows, query.filter);
      rows = this.applySort(rows, query.sort || []);

      const total = rows.length;
      const skip = Number(query.skip || 0);
      const take = Number(query.take || query.pageSize || 10);
      rows = rows.slice(skip, skip + take);

      return {
        data: rows.map((row) => this.decorateRuntimeRecord(row)),
        total
      };
    }

    ensureRecords() {
      if (Array.isArray(this.records) && this.records.length) {
        return;
      }

      this.records = this.getDefaultRecords();
      this.nextId = this.records.reduce(function(max, record) {
        return Math.max(max, Number(record.id) || 0);
      }, 0) + 1;
      this.persistRecords();
    }

    saveHelpSeen(data) {
      const itemIds = global.CrudUtils.ensureArray(data.itemIds).filter(Boolean);
      const current = Array.isArray(this.helpSeen) ? this.helpSeen.slice() : [];

      itemIds.forEach(function(itemId) {
        if (current.indexOf(itemId) === -1) {
          current.push(itemId);
        }
      });

      this.helpSeen = current;
      this.persistHelpSeen();

      return {
        ok: true,
        readAt: data.readAt || new Date().toISOString(),
        itemIds,
        seenIds: this.helpSeen.slice()
      };
    }

    getHomeChatContacts() {
      return {
        contacts: [
          {
            id: "u-ana",
            name: "Ana Lima",
            email: "ana@example.com",
            initials: "AL"
          },
          {
            id: "u-bruno",
            name: "Bruno Costa",
            email: "bruno@example.com",
            initials: "BC"
          },
          {
            id: "u-clara",
            name: "Clara Rocha",
            email: "clara@example.com",
            initials: "CR"
          }
        ]
      };
    }

    getHomeChatHistory(data) {
      const user = data && data.user ? data.user : {};
      const recipient = data && data.recipient ? data.recipient : {};
      const recipientId = recipient.id || "u-ana";
      const recipientName = recipient.name || "Ana Lima";
      return {
        messages: [
          {
            id: "home-chat-history-" + recipientId + "-1",
            text: "Bom dia. Voce consegue revisar as informacoes recebidas?",
            authorId: recipientId,
            authorName: recipientName,
            timestamp: "2026-05-05T08:40:00-03:00"
          },
          {
            id: "home-chat-history-" + recipientId + "-2",
            text: "Consigo sim. Vou verificar pelo programa atual.",
            authorId: user.id || "u-demo",
            authorName: user.name || "Usuario",
            timestamp: "2026-05-05T08:42:00-03:00"
          }
        ]
      };
    }

    sendHomeChatMessage(data) {
      const text = String(data && data.message && data.message.text || "").trim();
      const recipient = data && data.recipient ? data.recipient : {};
      return {
        ok: true,
        deliveredAt: new Date().toISOString(),
        recipientId: recipient.id || "",
        text
      };
    }

    getHomeSupportOnlineUsers() {
      return {
        onlineUsers: [
          {
            id: "u-ana",
            name: "Ana Lima",
            email: "ana@example.com",
            sectorId: "suporte",
            sectorName: "Suporte",
            status: "online"
          },
        ],
        sectors: [
          { id: "suporte", name: "Suporte" },
          { id: "financeiro", name: "Financeiro" }
        ]
      };
    }

    getHomeSupportHistory(data) {
      const user = data && data.user ? data.user : {};
      const attendant = data && data.attendant ? data.attendant : {};
      const attendantId = attendant.id || "u-ana";
      const attendantName = attendant.name || "Ana Lima";
      return {
        messages: [
          {
            id: "home-support-history-" + attendantId + "-1",
            text: "Estou online para ajudar. Pode enviar sua duvida.",
            authorId: attendantId,
            authorName: attendantName,
            timestamp: "2026-05-05T09:10:00-03:00"
          },
          {
            id: "home-support-history-" + attendantId + "-2",
            text: "Obrigado. Vou explicar o que preciso.",
            authorId: user.id || "u-demo",
            authorName: user.name || "Usuario",
            timestamp: "2026-05-05T09:11:00-03:00"
          }
        ]
      };
    }

    sendHomeSupportMessage(data) {
      const text = String(data && data.message && data.message.text || "").trim();
      const attendant = data && data.attendant ? data.attendant : {};
      return {
        message: {
          id: "home-support-reply-" + Date.now(),
          text: text ? "Recebi sua mensagem. Vou acompanhar por aqui." : "Recebi sua mensagem.",
          authorId: attendant.id || "u-atendente",
          authorName: attendant.name || "Atendimento",
          timestamp: new Date().toISOString()
        }
      };
    }

    createHomeSupportRequest(data) {
      const sector = data && data.sector ? data.sector : {};
      return {
        ok: true,
        protocol: "ATD-" + Date.now(),
        sectorId: sector.id || "suporte",
        status: "aberta",
        createdAt: new Date().toISOString()
      };
    }

    getHomeSupportRequestStatus() {
      return {
        status: "aberta",
        assignedTo: null,
        updatedAt: new Date().toISOString()
      };
    }

    getHomeAiChatHistory() {
      return {
        messages: [
          {
            id: "home-ai-chat-welcome",
            text: "Sou o assistente de IA da demo. Posso ajudar com duvidas sobre navegacao, filtros, programas e acoes disponiveis.",
            authorId: "ia",
            authorName: "IA",
            timestamp: new Date().toISOString()
          }
        ]
      };
    }

    sendHomeAiChatMessage(data) {
      const text = String(data && data.message && data.message.text || "").trim();
      const programTitle = data && data.context && data.context.programTitle
        ? String(data.context.programTitle)
        : "programa atual";
      const answer = text
        ? "Analisei sua pergunta sobre " + programTitle + ": \"" + text + "\". Nesta demo a resposta vem do mock; em producao o backend chamaria a IA e executaria apenas acoes permitidas."
        : "Informe uma duvida ou acao para a IA.";

      return {
        message: {
          id: "home-ai-chat-reply-" + Date.now(),
          text: answer,
          authorId: "ia",
          authorName: "IA",
          timestamp: new Date().toISOString()
        }
      };
    }

    getHomeAlerts(data) {
      const programTitle = data && data.context && data.context.programTitle
        ? String(data.context.programTitle)
        : "programa atual";
      return {
        items: [
          {
            id: "alerta-atualizacao-clientes",
            title: "Carga de clientes concluida",
            description: "Foram recebidas novas informacoes para conferencia no " + programTitle + ".",
            type: "Informacao",
            status: "Novo",
            receivedAt: "2026-05-05T08:15:00-03:00",
            linkUrl: "index.html",
            linkText: "Abrir clientes"
          },
          {
            id: "alerta-novidade-home",
            title: "Nova central de mensagens",
            description: "O appbar global agora pode exibir chat, alertas e solicitacoes por configuracao JSON.",
            type: "Sistema",
            status: "Lido parcialmente",
            receivedAt: "2026-05-05T09:20:00-03:00"
          }
        ]
      };
    }

    getHomeRequests() {
      return {
        items: [
          {
            id: "solicitacao-cadastro-pendente",
            title: "Revisar cadastro pendente",
            description: "Existe uma solicitacao de revisao de dados cadastrais aguardando validacao.",
            type: "Cadastro",
            status: "Pendente",
            updatedAt: "2026-05-05T10:05:00-03:00",
            linkUrl: "examples/pages/formulario-eventos.html",
            linkText: "Ver exemplo"
          },
          {
            id: "solicitacao-aprovacao-comercial",
            title: "Aprovacao comercial atualizada",
            description: "Uma etapa de aprovacao foi atualizada e precisa ser consultada.",
            type: "Workflow",
            status: "Atualizada",
            updatedAt: "2026-05-05T10:32:00-03:00"
          }
        ]
      };
    }

    getHomeJobs() {
      this.updateProcessJobsProgress();
      const finished = this.processJobs.filter((job) => {
        return String(job.user_id || "") === String(this.userId || "demo") &&
          ["succeeded", "failed"].indexOf(String(job.status || "")) >= 0;
      });
      return {
        items: finished.map((job) => {
          return {
            id: job.id,
            title: "Processamento de clientes " + (job.status === "succeeded" ? "concluido" : "falhou"),
            description: "Job " + job.id + " iniciado pelo usuario corrente.",
            type: "Job",
            status: job.status === "succeeded" ? "Concluido" : "Falhou",
            updatedAt: job.finished_at || job.created_at,
            programId: "meus-jobs",
            linkText: "Abrir meus jobs"
          };
        })
      };
    }

    getHomeNotifications(data) {
      const includeRead = data && data.includeRead === true;
      const notifications = this.getAdminRuntimeRows("admin.notificacoes");
      const recipients = this.getAdminRuntimeRows("admin.notificacao-destinatarios").map((row) => {
        return Object.assign({}, row);
      });
      const currentUserId = String(this.userId || "demo");
      const merged = [];
      recipients.forEach((recipient) => {
        if (String(recipient.user_id || "") !== currentUserId) {
          return;
        }
        if (!includeRead && String(recipient.status || "") === "read") {
          return;
        }
        const notification = notifications.find(function(item) {
          return String(item.id) === String(recipient.notification_id);
        });
        if (!notification || String(notification.status || "").toLowerCase() !== "published") {
          return;
        }
        if (String(recipient.status || "") === "pending") {
          recipient.status = "delivered";
          recipient.delivered_at = recipient.delivered_at || new Date().toISOString();
          recipient.updated_at = new Date().toISOString();
        }
        merged.push({
          id: notification.id,
          recipientId: recipient.id,
          title: notification.title,
          description: notification.message,
          type: notification.category || "Notificacao",
          status: String(recipient.status || "") === "read" ? "Lida" : (String(recipient.status || "") === "delivered" ? "Entregue" : "Pendente"),
          severity: notification.severity || "info",
          actionRequired: notification.action_required === true,
          createdAt: notification.created_at ? notification.created_at.replace(" ", "T") : null,
          updatedAt: (recipient.read_at || recipient.delivered_at || notification.updated_at || notification.created_at || "").replace(" ", "T"),
          programId: notification.link_program_id || "",
          screenId: notification.link_screen_id || "",
          linkText: notification.link_program_id ? "Abrir" : "Detalhar",
          technicalProperties: [
            { section: "Notificacao", label: "ID", value: String(notification.id || "") },
            { section: "Notificacao", label: "Severidade", value: String(notification.severity || "info"), critical: String(notification.severity || "").toLowerCase() === "error" },
            { section: "Destinatario", label: "Status", value: String(recipient.status || "pending"), critical: String(recipient.status || "").toLowerCase() !== "read" && notification.action_required === true },
            { section: "Navegacao", label: "Screen ID", value: String(notification.link_screen_id || "") || "-" },
            { section: "Navegacao", label: "Programa", value: String(notification.link_program_id || "") || "-" }
          ]
        });
      });
      this.saveAdminRuntimeRows("admin.notificacao-destinatarios", recipients);
      merged.sort((left, right) => {
        const leftDate = new Date(left.updatedAt || left.createdAt || 0).getTime() || 0;
        const rightDate = new Date(right.updatedAt || right.createdAt || 0).getTime() || 0;
        return rightDate - leftDate;
      });
      return {
        items: merged
      };
    }

    ackHomeNotifications(data) {
      const ids = global.CrudUtils.ensureArray(data && (data.ids || (data.id ? [data.id] : []))).map(String);
      if (!ids.length) {
        return { ok: true, count: 0 };
      }
      const currentUserId = String(this.userId || "demo");
      const rows = this.getAdminRuntimeRows("admin.notificacao-destinatarios").slice();
      let count = 0;
      rows.forEach(function(row) {
        if (String(row.user_id || "") !== currentUserId) {
          return;
        }
        if (ids.indexOf(String(row.notification_id || "")) === -1) {
          return;
        }
        if (String(row.status || "") !== "read") {
          row.status = "read";
          row.read_at = new Date().toISOString();
          row.delivered_at = row.delivered_at || row.read_at;
          row.updated_at = row.read_at;
          count += 1;
        }
      });
      this.saveAdminRuntimeRows("admin.notificacao-destinatarios", rows);
      return { ok: true, count: count };
    }

    printClient(id, format, data) {
      this.ensureRecords();
      const recordId = Number(id || data && data.id);
      const record = this.records.find(function(item) {
        return Number(item.id) === recordId;
      });
      if (!record) {
        throw global.CrudUtils.makeError("CLIENT_NOT_FOUND", "Cliente nao encontrado para impressao.");
      }

      return {
        ok: true,
        id: recordId,
        format,
        requestedAt: new Date().toISOString(),
        record: global.CrudUtils.clone(record)
      };
    }

    executeClientAction(id, action, data) {
      this.ensureRecords();
      const recordId = Number(id || data && data.id);
      const record = this.records.find(function(item) {
        return Number(item.id) === recordId;
      });
      if (!record) {
        throw global.CrudUtils.makeError("CLIENT_NOT_FOUND", "Cliente nao encontrado para executar acao.");
      }

      if (action === "send-whatsapp") {
        return {
          ok: true,
          id: recordId,
          action,
          receivedValues: global.CrudUtils.clone(data && data.values || {}),
          requestedAt: new Date().toISOString(),
          _runtime: {
            asyncJobs: [
              {
                type: "cliente.whatsapp_welcome",
                status: "queued"
              }
            ]
          },
          effects: [
            {
              action: "showMessage",
              type: "info",
              message: "WhatsApp agendado."
            }
          ]
        };
      }

      return {
        ok: true,
        id: recordId,
        action,
        receivedValues: global.CrudUtils.clone(data && data.values || {}),
        requestedAt: new Date().toISOString()
      };
    }

    saveLayout(data) {
      const layout = {
        id: data.id || "layout-" + this.nextLayoutId,
        name: data.name,
        isDefault: Boolean(data.isDefault),
        version: data.version || 1,
        definitionHash: data.definitionHash || "clientes-demo-v1",
        grid: global.CrudUtils.clone(data.grid)
      };
      this.decoratePreferenceScope(layout, data);

      if (!data.id) {
        this.nextLayoutId += 1;
      }

      if (layout.isDefault) {
        this.clearDefaultPreference(this.savedLayouts, layout);
      }

      const index = this.savedLayouts.findIndex(function(item) {
        return item.id === layout.id && item.tenantId === layout.tenantId;
      });
      if (index >= 0) {
        this.savedLayouts[index] = layout;
      } else {
        this.savedLayouts.push(layout);
      }

      this.activeLayoutId = layout.id;
      this.persistLayouts();
      return {
        ok: true,
        layout,
        userLayout: this.buildUserLayout()
      };
    }

    saveSort(data) {
      const sort = global.CrudUtils.ensureArray(data.sort).filter(function(item) {
        return item && item.field && (item.dir === "asc" || item.dir === "desc");
      });
      if (!data.name || !String(data.name).trim()) {
        throw global.CrudUtils.makeError("SORT_NAME_REQUIRED", "Informe o nome da ordenacao.");
      }
      if (!sort.length) {
        throw global.CrudUtils.makeError("SORT_FIELDS_REQUIRED", "Informe ao menos uma coluna para ordenar.");
      }

      const preset = {
        id: data.id || "sort-" + this.nextSortId,
        name: String(data.name).trim(),
        isDefault: Boolean(data.isDefault),
        sort: global.CrudUtils.clone(sort)
      };
      this.decoratePreferenceScope(preset, data);

      if (!data.id) {
        this.nextSortId += 1;
      }

      if (preset.isDefault) {
        this.clearDefaultPreference(this.savedSorts, preset);
      }

      const index = this.savedSorts.findIndex(function(item) {
        return item.id === preset.id && item.tenantId === preset.tenantId;
      });
      if (index >= 0) {
        this.savedSorts[index] = preset;
      } else {
        this.savedSorts.push(preset);
      }

      const defaultSort = this.findActivePreference(this.savedSorts, this.activeSortId);
      if (preset.isDefault || !defaultSort) {
        this.activeSortId = preset.id;
      }
      this.persistLayouts();
      return {
        ok: true,
        sortPreset: preset,
        userLayout: this.buildUserLayout()
      };
    }

    deleteSort(sortId) {
      const id = String(sortId || "");
      this.savedSorts = this.savedSorts.filter(function(item) {
        return !this.matchesPreferenceForDelete(item, id);
      }, this);
      if (this.activeSortId === id) {
        this.activeSortId = null;
      }
      this.persistLayouts();
      return {
        ok: true,
        userLayout: this.buildUserLayout()
      };
    }

    saveGroup(data) {
      const aggregates = this.normalizeGroupAggregates(data.aggregates);
      const group = global.CrudUtils.ensureArray(data.group).filter(function(item) {
        return item && item.field && (!item.dir || item.dir === "asc" || item.dir === "desc");
      }).map(function(item) {
        return {
          field: item.field,
          dir: item.dir === "desc" ? "desc" : "asc"
        };
      });
      if (!data.name || !String(data.name).trim()) {
        throw global.CrudUtils.makeError("GROUP_NAME_REQUIRED", "Informe o nome do agrupamento.");
      }
      if (!group.length) {
        throw global.CrudUtils.makeError("GROUP_FIELDS_REQUIRED", "Informe ao menos uma coluna para agrupar.");
      }

      const preset = {
        id: data.id || "group-" + this.nextGroupId,
        name: String(data.name).trim(),
        isDefault: Boolean(data.isDefault),
        group: global.CrudUtils.clone(group),
        aggregates: global.CrudUtils.clone(aggregates)
      };
      this.decoratePreferenceScope(preset, data);

      if (!data.id) {
        this.nextGroupId += 1;
      }

      if (preset.isDefault) {
        this.clearDefaultPreference(this.savedGroups, preset);
      }

      const index = this.savedGroups.findIndex(function(item) {
        return item.id === preset.id && item.tenantId === preset.tenantId;
      });
      if (index >= 0) {
        this.savedGroups[index] = preset;
      } else {
        this.savedGroups.push(preset);
      }

      const defaultGroup = this.findActivePreference(this.savedGroups, this.activeGroupId);
      if (preset.isDefault || !defaultGroup) {
        this.activeGroupId = preset.id;
      }
      this.persistLayouts();
      return {
        ok: true,
        groupPreset: preset,
        userLayout: this.buildUserLayout()
      };
    }

    deleteGroup(groupId) {
      const id = String(groupId || "");
      this.savedGroups = this.savedGroups.filter(function(item) {
        return !this.matchesPreferenceForDelete(item, id);
      }, this);
      if (this.activeGroupId === id) {
        this.activeGroupId = null;
      }
      this.persistLayouts();
      return {
        ok: true,
        userLayout: this.buildUserLayout()
      };
    }

    saveFilter(data) {
      const filters = global.CrudUtils.ensureArray(data.filters).filter(function(item) {
        return item && item.id && item.value != null && item.value !== "";
      });
      if (!data.name || !String(data.name).trim()) {
        throw global.CrudUtils.makeError("FILTER_NAME_REQUIRED", "Informe o nome do filtro.");
      }
      if (!filters.length) {
        throw global.CrudUtils.makeError("FILTER_FIELDS_REQUIRED", "Informe ao menos um filtro.");
      }

      const preset = {
        id: data.id || "filter-" + this.nextFilterId,
        name: String(data.name).trim(),
        isDefault: Boolean(data.isDefault),
        filters: global.CrudUtils.clone(filters)
      };
      this.decoratePreferenceScope(preset, data);

      if (!data.id) {
        this.nextFilterId += 1;
      }

      if (preset.isDefault) {
        this.clearDefaultPreference(this.savedFilters, preset);
      }

      const index = this.savedFilters.findIndex(function(item) {
        return item.id === preset.id && item.tenantId === preset.tenantId;
      });
      if (index >= 0) {
        this.savedFilters[index] = preset;
      } else {
        this.savedFilters.push(preset);
      }

      const defaultFilter = this.findActivePreference(this.savedFilters, this.activeFilterId);
      if (preset.isDefault || !defaultFilter) {
        this.activeFilterId = preset.id;
      }
      this.persistLayouts();
      return {
        ok: true,
        filterPreset: preset,
        userLayout: this.buildUserLayout()
      };
    }

    deleteFilter(filterId) {
      const id = String(filterId || "");
      this.savedFilters = this.savedFilters.filter(function(item) {
        return !this.matchesPreferenceForDelete(item, id);
      }, this);
      if (this.activeFilterId === id) {
        this.activeFilterId = null;
      }
      this.persistLayouts();
      return {
        ok: true,
        userLayout: this.buildUserLayout()
      };
    }

    saveMobileTemplate(data) {
      const template = this.normalizeMobileTemplate(data.template || data.mobileTemplate || data);
      if (!data.name || !String(data.name).trim()) {
        throw global.CrudUtils.makeError("MOBILE_TEMPLATE_NAME_REQUIRED", "Informe o nome do template mobile.");
      }
      if (!template) {
        throw global.CrudUtils.makeError("MOBILE_TEMPLATE_REQUIRED", "Informe ao menos um campo para o template mobile.");
      }

      const preset = {
        id: data.id || "mobile-template-" + this.nextMobileTemplateId,
        name: String(data.name).trim(),
        isDefault: Boolean(data.isDefault),
        template
      };
      this.decoratePreferenceScope(preset, data);

      if (!data.id) {
        this.nextMobileTemplateId += 1;
      }

      if (preset.isDefault) {
        this.clearDefaultPreference(this.savedMobileTemplates, preset);
      }

      const index = this.savedMobileTemplates.findIndex(function(item) {
        return item.id === preset.id && item.tenantId === preset.tenantId;
      });
      if (index >= 0) {
        this.savedMobileTemplates[index] = preset;
      } else {
        this.savedMobileTemplates.push(preset);
      }

      const defaultTemplate = this.findActivePreference(this.savedMobileTemplates, this.activeMobileTemplateId);
      if (preset.isDefault || !defaultTemplate) {
        this.activeMobileTemplateId = preset.id;
      }
      this.persistLayouts();
      return {
        ok: true,
        mobileTemplatePreset: preset,
        userLayout: this.buildUserLayout()
      };
    }

    deleteMobileTemplate(templateId) {
      const id = String(templateId || "");
      this.savedMobileTemplates = this.savedMobileTemplates.filter(function(item) {
        return !this.matchesPreferenceForDelete(item, id);
      }, this);
      if (this.activeMobileTemplateId === id) {
        this.activeMobileTemplateId = null;
      }
      this.persistLayouts();
      return {
        ok: true,
        userLayout: this.buildUserLayout()
      };
    }

    buildUserLayout(baseUserLayout) {
      const base = global.CrudUtils.clone(baseUserLayout || {
        enabled: true,
        version: 2,
        source: "default",
        definitionHash: "clientes-demo-v1",
        grid: this.emptyGridLayout()
      });

      base.preferenceScopes = {
        currentTenantId: this.tenantId,
        globalScope: "global",
        fallbackOrder: ["tenant", "global", "program", "system"]
      };
      base.savedLayouts = global.CrudUtils.clone(this.visiblePreferences(this.savedLayouts));
      base.savedSorts = global.CrudUtils.clone(this.visiblePreferences(this.savedSorts));
      base.savedGroups = global.CrudUtils.clone(this.visiblePreferences(this.savedGroups));
      base.savedFilters = global.CrudUtils.clone(this.visiblePreferences(this.savedFilters));
      base.savedMobileTemplates = global.CrudUtils.clone(this.visiblePreferences(this.savedMobileTemplates));

      let active = this.findActivePreference(this.savedLayouts, this.activeLayoutId);

      if (active) {
        base.activeLayoutId = active.id;
        base.source = active.scope === "global" ? "user_global" : "user";
        base.grid = global.CrudUtils.clone(active.grid);
      } else {
        base.activeLayoutId = null;
        base.source = "default";
        base.grid = this.emptyGridLayout();
      }

      const activeSort = this.findActivePreference(this.savedSorts, this.activeSortId);
      if (activeSort) {
        base.activeSortId = activeSort.id;
        base.grid.sort = global.CrudUtils.clone(activeSort.sort);
      } else {
        base.activeSortId = null;
      }

      const activeGroup = this.findActivePreference(this.savedGroups, this.activeGroupId);
      if (activeGroup) {
        base.activeGroupId = activeGroup.id;
        base.grid.group = global.CrudUtils.clone(activeGroup.group);
        base.grid.groupAggregates = global.CrudUtils.clone(activeGroup.aggregates || []);
      } else {
        base.activeGroupId = null;
        base.grid.groupAggregates = global.CrudUtils.ensureArray(base.grid.groupAggregates);
      }

      const activeFilter = this.findActivePreference(this.savedFilters, this.activeFilterId);
      base.activeFilterId = activeFilter ? activeFilter.id : null;

      const activeMobileTemplate = this.findActivePreference(this.savedMobileTemplates, this.activeMobileTemplateId);
      if (activeMobileTemplate) {
        base.activeMobileTemplateId = activeMobileTemplate.id;
        base.grid.mobileTemplate = global.CrudUtils.clone(activeMobileTemplate.template);
      } else {
        base.activeMobileTemplateId = null;
        base.grid.mobileTemplate = base.grid.mobileTemplate || null;
      }

      return base;
    }

    emptyGridLayout() {
      return {
        columns: {
          order: [],
          hidden: [],
          widths: {},
          frozen: [],
          added: []
        },
        sort: [],
        filter: null,
        group: [],
        groupAggregates: [],
        mobileTemplate: null
      };
    }

    decoratePreferenceScope(preference, data) {
      const scope = this.resolvePreferenceScope(data);
      preference.scope = scope;
      preference.tenantId = scope === "global" ? "__global__" : this.tenantId;
      preference.inherited = false;
      return preference;
    }

    resolvePreferenceScope(data) {
      const source = data || {};
      const scope = String(source.scope || source.tenantScope || "").toLowerCase();
      if (scope === "global" || scope === "all" || scope === "todos" || source.applyToAllTenants || source.allSubscribers) {
        return "global";
      }
      return "tenant";
    }

    visiblePreferences(items) {
      const globalItems = [];
      const tenantItems = [];
      global.CrudUtils.ensureArray(items).forEach((item) => {
        const normalized = this.normalizePreferenceScope(item);
        if (normalized.tenantId === "__global__") {
          globalItems.push(Object.assign({}, normalized, {
            inherited: true
          }));
          return;
        }
        if (normalized.tenantId === this.tenantId) {
          tenantItems.push(Object.assign({}, normalized, {
            inherited: false
          }));
        }
      });

      const merged = {};
      globalItems.forEach(function(item) {
        merged[item.id] = item;
      });
      tenantItems.forEach(function(item) {
        merged[item.id] = item;
      });
      return Object.keys(merged).map(function(key) {
        return merged[key];
      });
    }

    findActivePreference(items, activeId) {
      const visible = this.visiblePreferences(items);
      const tenantItems = visible.filter(function(item) {
        return item.scope !== "global";
      });
      const globalItems = visible.filter(function(item) {
        return item.scope === "global";
      });

      return tenantItems.find(function(item) {
        return item.isDefault;
      }) || globalItems.find(function(item) {
        return item.isDefault;
      }) || tenantItems.find(function(item) {
        return item.id === activeId;
      }) || globalItems.find(function(item) {
        return item.id === activeId;
      }) || null;
    }

    normalizePreferenceScope(item) {
      const copy = Object.assign({}, item || {});
      if (!copy.tenantId) {
        copy.tenantId = copy.scope === "global" ? "__global__" : this.tenantId;
      }
      copy.scope = copy.tenantId === "__global__" ? "global" : "tenant";
      return copy;
    }

    clearDefaultPreference(items, current) {
      global.CrudUtils.ensureArray(items).forEach((item) => {
        const normalized = this.normalizePreferenceScope(item);
        if (normalized.tenantId === current.tenantId && item.id !== current.id) {
          item.isDefault = false;
        }
      });
    }

    matchesPreferenceForDelete(item, id) {
      const normalized = this.normalizePreferenceScope(item);
      if (normalized.id !== id) {
        return false;
      }
      return normalized.tenantId === this.tenantId || normalized.tenantId === "__global__";
    }

    normalizeMobileTemplate(template) {
      const source = template || {};
      const fields = this.normalizeFieldList(source.fields || source.fieldPositions || []);
      const badges = this.normalizeFieldList(source.badges || source.badgeFields || []);
      const tabs = this.normalizeMobileTabs(source.tabs || {});
      const normalized = {
        titleField: this.normalizeFieldName(source.titleField),
        subtitleField: this.normalizeFieldName(source.subtitleField),
        badges,
        fields,
        tabs
      };

      if (!normalized.titleField && !normalized.subtitleField && !badges.length && !fields.length && !tabs.items.length) {
        return null;
      }

      return normalized;
    }

    normalizeMobileTabs(tabs) {
      const source = tabs || {};
      const items = global.CrudUtils.ensureArray(source.items).map((item) => {
        const fields = this.normalizeFieldList(item && item.fields || []);
        if (!fields.length) {
          return null;
        }
        return {
          id: String(item.id || "tab").replace(/[^A-Za-z0-9_.:-]+/g, "-").slice(0, 80),
          title: String(item.title || item.id || "Aba").slice(0, 120),
          fields
        };
      }).filter(Boolean);

      return {
        enabled: Boolean(source.enabled) && items.length > 0,
        items
      };
    }

    normalizeFieldList(fields) {
      const known = {};
      return global.CrudUtils.ensureArray(fields).map((fieldName) => {
        return this.normalizeFieldName(fieldName);
      }).filter(function(fieldName) {
        if (!fieldName || known[fieldName]) {
          return false;
        }
        known[fieldName] = true;
        return true;
      });
    }

    normalizeFieldName(fieldName) {
      const value = String(fieldName || "").trim();
      return /^[A-Za-z_][A-Za-z0-9_]*$/.test(value) ? value : "";
    }

    normalizeGroupAggregates(aggregates) {
      const seen = {};
      return global.CrudUtils.ensureArray(aggregates).filter(function(item) {
        const aggregate = item && item.aggregate === "sum" ? "sum" : item && item.aggregate === "count" ? "count" : null;
        if (!item || !item.field || !aggregate) {
          return false;
        }
        const key = item.field + ":" + aggregate;
        if (seen[key]) {
          return false;
        }
        seen[key] = true;
        return true;
      }).map(function(item) {
        return {
          field: item.field,
          aggregate: item.aggregate === "sum" ? "sum" : "count"
        };
      });
    }

    decorateRuntimeRecord(record) {
      const copy = global.CrudUtils.clone(record || {});
      const updatedAt = copy.updated_at || copy.updatedAt || copy.data_cadastro || "";
      copy._runtime = Object.assign({}, copy._runtime || {}, {
        version: this.runtimeRecordVersion(copy),
        lastModifiedAt: updatedAt
      });
      return copy;
    }

    decorateAsyncCreateResponse(record) {
      if (!record || !record.email) {
        return record;
      }
      const email = String(record.email || "").trim();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return record;
      }

      record._runtime = Object.assign({}, record._runtime || {}, {
        asyncJobs: [
          {
            type: "cliente.email_confirmation",
            status: "queued"
          }
        ]
      });
      record.effects = [
        {
          action: "showMessage",
          type: "info",
          message: "E-mail de confirmacao agendado."
        }
      ];
      return record;
    }

    runtimeRecordVersion(record) {
      return "demo-" + String(record.id || "") + "-" + String(record.updated_at || record.updatedAt || record.data_cadastro || "");
    }

    validateRuntimeWrite(record, data) {
      const runtime = data && data._runtime || {};
      const expectedVersion = runtime.version || data && data.expectedVersion;
      if (expectedVersion && expectedVersion !== this.runtimeRecordVersion(record)) {
        throw global.CrudUtils.makeError("STALE_RECORD", "Este registro foi alterado por outro usuario. Recarregue antes de gravar.", {
          expectedVersion,
          currentVersion: this.runtimeRecordVersion(record)
        });
      }
      const lockToken = runtime.lockToken || data && data.lockToken;
      if (!lockToken) {
        throw global.CrudUtils.makeError("LOCK_REQUIRED", "Este registro exige semaforo ativo para gravar.");
      }
      const lock = this.runtimeState.locks.find((item) => {
        return item.status === "active" && item.token === lockToken && item.sessionId === this.sessionId;
      });
      if (!lock) {
        throw global.CrudUtils.makeError("LOCK_EXPIRED", "O semaforo deste registro expirou ou pertence a outra sessao.");
      }
    }

    create(data) {
      const values = this.applyDemoCustomCodes(this.normalizeRuntimeValues(data), data, this.nextId);
      this.validateBusinessConsistency(values);
      const record = Object.assign({}, values, {
        id: this.nextId,
        data_cadastro: new Date().toISOString().slice(0, 10),
        valor_total: Number(values.valor_total || 0),
        qtde_pedidos: Number(values.qtde_pedidos || 0),
        updated_at: new Date().toISOString()
      });
      this.nextId += 1;
      this.records.push(record);
      this.persistRecords();
      return this.decorateAsyncCreateResponse(this.decorateRuntimeRecord(record));
    }

    get(id) {
      const record = this.records.find(function(item) { return item.id === id; });
      if (!record) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro nao encontrado.", { id });
      }
      return this.decorateRuntimeRecord(record);
    }

    update(id, data) {
      const index = this.records.findIndex(function(item) { return item.id === id; });
      if (index === -1) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro nao encontrado.", { id });
      }
      const current = this.records[index];
      this.validateRuntimeWrite(current, data);
      const values = this.applyDemoCustomCodes(this.normalizeRuntimeValues(data), data, id);
      this.validateBusinessConsistency(Object.assign({}, current, values));
      const next = Object.assign({}, current, values, { id, updated_at: new Date().toISOString() });
      this.records[index] = next;
      this.persistRecords();
      return this.decorateRuntimeRecord(next);
    }

    delete(id, data) {
      const index = this.records.findIndex(function(item) { return item.id === id; });
      if (index === -1) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro nao encontrado.", { id });
      }
      this.validateRuntimeWrite(this.records[index], data || {});
      this.records.splice(index, 1);
      this.persistRecords();
      return { ok: true };
    }

    bulkUpdateStatus(data) {
      const ids = this.normalizeIds(data.ids);
      const status = data.value === "INATIVO" ? "INATIVO" : "ATIVO";
      let updated = 0;
      this.records = this.records.map(function(record) {
        if (ids.indexOf(Number(record.id)) === -1) {
          return record;
        }
        updated += 1;
        return Object.assign({}, record, { status });
      });
      this.persistRecords();
      return {
        ok: true,
        updated
      };
    }

    bulkDelete(data) {
      const ids = this.normalizeIds(data.ids);
      const before = this.records.length;
      this.records = this.records.filter(function(record) {
        return ids.indexOf(Number(record.id)) === -1;
      });
      this.persistRecords();
      return {
        ok: true,
        deleted: before - this.records.length
      };
    }

    validateStatusRules(data) {
      const status = data && data.status === "INATIVO" ? "INATIVO" : "ATIVO";
      if (status === "INATIVO") {
        return {
          ok: true,
          effects: [
            {
              target: "observacao",
              action: "setValue",
              value: "Cliente inativo: revise antes de salvar."
            },
            {
              target: "observacao",
              action: "readonly",
              value: true
            },
            {
              action: "showMessage",
              type: "warning",
              message: "Cliente inativo: observacao travada pela regra do backend."
            }
          ]
        };
      }

      return {
        ok: true,
        effects: [
          {
            target: "observacao",
            action: "readonly",
            value: false
          }
        ]
      };
    }

    normalizeRuntimeValues(data) {
      const source = data && data.values && typeof data.values === "object" ? data.values : data || {};
      const allowed = ["id", "nome", "codigo_customizado", "email", "telefone", "status", "tipo_pessoa", "uf", "cidade", "razao_social", "cnpj", "data_cadastro", "valor_total", "qtde_pedidos", "observacao"];
      return allowed.reduce(function(result, field) {
        if (Object.prototype.hasOwnProperty.call(source, field)) {
          result[field] = source[field];
        }
        return result;
      }, {});
    }

    applyDemoCustomCodes(values, data, sequenceValue) {
      const source = data && data.values && typeof data.values === "object" ? data.values : data || {};
      const customCode = source._customCode && typeof source._customCode === "object" ? source._customCode : {};
      Object.keys(customCode).forEach(function(fieldName) {
        if (values[fieldName]) {
          return;
        }
        const entry = customCode[fieldName] || {};
        const config = entry.config || {};
        const properties = entry.properties || {};
        values[fieldName] = this.buildDemoCustomCode(config, properties, sequenceValue);
      }, this);
      return values;
    }

    buildDemoCustomCode(config, properties, sequenceValue) {
      const now = new Date();
      const seq = String(sequenceValue || 1).padStart(Number(config.sequencePadding || 4), "0");
      const prefix = String(config.prefix || "");
      if (config.mode === "static_method") {
        const familia = this.normalizeCodeSegment(properties.familia || "GERAL");
        const grupo = this.normalizeCodeSegment(properties.grupo || "PADRAO");
        const linha = this.normalizeCodeSegment(properties.linha || "ITEM");
        return prefix + [familia, grupo, linha, seq].join("-");
      }
      const pattern = String(config.pattern || "{YYYY}{MM}{DD}-{SEQ:4}");
      return prefix + pattern
        .replace(/\{YYYY\}/g, String(now.getFullYear()))
        .replace(/\{YY\}/g, String(now.getFullYear()).slice(-2))
        .replace(/\{MM\}/g, String(now.getMonth() + 1).padStart(2, "0"))
        .replace(/\{DD\}/g, String(now.getDate()).padStart(2, "0"))
        .replace(/\{SEQ(?::(\d+))?\}/g, function(match, padding) {
          return String(sequenceValue || 1).padStart(Number(padding || config.sequencePadding || 4), "0");
        })
        .replace(/\{PROMPT:([^}]+)\}/g, function(match, name) {
          return this.normalizeCodeSegment(properties[name] || "");
        }.bind(this));
    }

    normalizeCodeSegment(value) {
      return String(value || "").trim().toUpperCase().replace(/[^A-Z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "ITEM";
    }

    validateBusinessConsistency(values) {
      if (!values || values.status !== "INATIVO" || String(values.observacao || "").trim()) {
        return;
      }

      const error = global.CrudUtils.makeError("CLIENTE_OBSERVACAO_REQUIRED", "Existem inconsistencias no formulario.", {});
      error.payload.error.severity = "error";
      error.payload.validation = {
        status: "blocked",
        title: "Inconsistencias encontradas",
        titleKey: "validation.title.inconsistencies",
        messages: [
          {
            field: "observacao",
            type: "error",
            message: "Observacao e obrigatoria para cliente inativo.",
            messageKey: "validation.message.field_required",
            messageParams: {
              field: "observacao",
              fieldCode: "observacao",
              fieldLabel: "Observacao"
            }
          }
        ]
      };
      error.payload.effects = [
        {
          action: "required",
          target: "observacao",
          value: true
        },
        {
          action: "showMessage",
          type: "warning",
          message: "Informe uma observacao ao inativar o cliente."
        }
      ];
      throw error;
    }

    listCitiesByState(data) {
      const uf = String(data && data.uf || "").toUpperCase();
      const citiesByState = {
        CE: [
          { value: "FORTALEZA", text: "Fortaleza" },
          { value: "CAUCAIA", text: "Caucaia" },
          { value: "SOBRAL", text: "Sobral" },
          { value: "JUAZEIRO_NORTE", text: "Juazeiro do Norte" }
        ],
        SP: [
          { value: "SAO_PAULO", text: "Sao Paulo" },
          { value: "CAMPINAS", text: "Campinas" },
          { value: "SANTOS", text: "Santos" },
          { value: "RIBEIRAO_PRETO", text: "Ribeirao Preto" }
        ],
        RJ: [
          { value: "RIO_DE_JANEIRO", text: "Rio de Janeiro" },
          { value: "NITEROI", text: "Niteroi" },
          { value: "PETROPOLIS", text: "Petropolis" },
          { value: "MACAE", text: "Macae" }
        ]
      };

      return {
        ok: true,
        uf,
        options: citiesByState[uf] || []
      };
    }

    getStatusHistory(data) {
      const id = Number(data && data.id);
      const phase = data && data.phase ? String(data.phase) : "";
      const record = this.records.find(function(item) {
        return Number(item.id) === id;
      }) || {};
      const statusText = phase === "INATIVO" ? "Inativo" : "Ativo";
      const baseHistory = [
        {
          status: "ATIVO",
          statusText: "Ativo",
          changedAt: "2026-01-10T08:20:00",
          user: "Ana Souza",
          note: "Cadastro aprovado para operacao."
        }
      ];

      if (record.status === "INATIVO" || phase === "INATIVO") {
        baseHistory.push({
          status: "INATIVO",
          statusText: "Inativo",
          changedAt: "2026-04-15T14:35:00",
          user: "Carlos Lima",
          note: "Situacao alterada apos revisao cadastral."
        });
      }

      return {
        ok: true,
        id,
        phase,
        items: baseHistory.filter(function(item) {
          return !phase || item.status === phase;
        }),
        title: statusText
      };
    }

    getStepHistory(id, stepId, data) {
      const recordId = Number(id || data && data.id);
      const titles = {
        identificacao: "Identificacao",
        dados_pj: "Dados PJ",
        comercial: "Comercial"
      };
      return {
        ok: true,
        id: recordId,
        stepId,
        title: titles[stepId] || data && data.stepTitle || stepId,
        items: [
          {
            title: "Etapa criada",
            changedAt: "2026-01-10T08:20:00",
            user: "Ana Souza",
            percent: stepId === "identificacao" ? 100 : 60,
            note: "Primeiro preenchimento registrado pelo mock."
          },
          {
            title: "Etapa revisada",
            changedAt: "2026-04-20T11:05:00",
            user: "Carlos Lima",
            percent: stepId === "comercial" ? 100 : 80,
            note: "Revisao de dados da etapa."
          }
        ]
      };
    }

    normalizeIds(ids) {
      return global.CrudUtils.ensureArray(ids).map(function(id) {
        return Number(id);
      }).filter(function(id) {
        return Number.isFinite(id);
      });
    }

    applyCustomFilters(rows, filters) {
      return global.CrudUtils.ensureArray(filters).reduce((currentRows, filter) => {
        return currentRows.filter((row) => this.matchesCustomFilter(row, filter));
      }, rows);
    }

    matchesCustomFilter(row, filter) {
      const operator = filter.operator || (filter.type === "search" ? "contains" : "eq");
      if (filter.type === "search") {
        const value = String(filter.value || "").toLowerCase();
        const fields = global.CrudUtils.ensureArray(filter.fields);
        return fields.some(function(field) {
          return String(row[field] || "").toLowerCase().indexOf(value) !== -1;
        });
      }

      const actual = row[filter.field];
      const expected = filter.value;
      if (operator === "isEmpty") {
        return actual != null && String(actual) === "";
      }
      if (operator === "isNotEmpty") {
        return actual != null && String(actual) !== "";
      }
      if (operator === "isNull") {
        return actual == null;
      }
      if (operator === "isNotNull") {
        return actual != null;
      }

      if (filter.type === "dateRange" || filter.dataType === "date" || operator === "relative") {
        return this.matchesDateFilter(actual, operator, expected);
      }

      if (filter.dataType === "datetime" || filter.dataType === "time") {
        return this.matchesDateFilter(actual, operator, expected);
      }

      if (["integer", "number", "decimal"].indexOf(filter.dataType) !== -1) {
        return this.matchesNumberFilter(actual, operator, expected);
      }

      if (operator === "in" || operator === "notIn") {
        const values = this.toValueList(expected);
        const match = values.map(String).indexOf(String(actual)) !== -1;
        return operator === "notIn" ? !match : match;
      }

      return this.matchesTextFilter(actual, operator, expected);
    }

    matchesTextFilter(actual, operator, expected) {
      const left = String(actual == null ? "" : actual).toLowerCase();
      const right = String(expected == null ? "" : expected).toLowerCase();
      if (operator === "eq") {
        return left === right;
      }
      if (operator === "neq") {
        return left !== right;
      }
      if (operator === "startsWith") {
        return left.indexOf(right) === 0;
      }
      if (operator === "contains") {
        return left.indexOf(right) !== -1;
      }
      if (operator === "notContains") {
        return left.indexOf(right) === -1;
      }
      if (operator === "between") {
        const start = expected && expected.start != null ? String(expected.start).toLowerCase() : null;
        const end = expected && expected.end != null ? String(expected.end).toLowerCase() : null;
        return (!start || left >= start) && (!end || left <= end);
      }
      return true;
    }

    matchesNumberFilter(actual, operator, expected) {
      const value = Number(actual);
      if (!Number.isFinite(value)) {
        return false;
      }
      if (operator === "between") {
        const start = expected && expected.start != null ? Number(expected.start) : null;
        const end = expected && expected.end != null ? Number(expected.end) : null;
        return (start == null || value >= start) && (end == null || value <= end);
      }
      if (operator === "in" || operator === "notIn") {
        const values = this.toValueList(expected).map(Number).filter(Number.isFinite);
        const match = values.indexOf(value) !== -1;
        return operator === "notIn" ? !match : match;
      }
      const target = Number(expected);
      if (!Number.isFinite(target)) {
        return true;
      }
      if (operator === "eq") {
        return value === target;
      }
      if (operator === "neq") {
        return value !== target;
      }
      if (operator === "gte") {
        return value >= target;
      }
      if (operator === "lte") {
        return value <= target;
      }
      if (operator === "gt") {
        return value > target;
      }
      if (operator === "lt") {
        return value < target;
      }
      return true;
    }

    matchesDateFilter(actual, operator, expected) {
      const value = this.toTime(actual);
      if (value == null) {
        return false;
      }
      if (operator === "between" || operator === "relative") {
        const start = expected && expected.start ? this.toTime(expected.start) : null;
        const end = expected && expected.end ? this.toTime(expected.end) : null;
        return (start == null || value >= start) && (end == null || value <= end);
      }
      const target = this.toTime(expected);
      if (target == null) {
        return true;
      }
      if (operator === "eq") {
        const dayStart = new Date(target);
        dayStart.setHours(0, 0, 0, 0);
        const dayEnd = new Date(target);
        dayEnd.setHours(23, 59, 59, 999);
        return value >= dayStart.getTime() && value <= dayEnd.getTime();
      }
      if (operator === "gte") {
        return value >= target;
      }
      if (operator === "lte") {
        return value <= target;
      }
      if (operator === "gt") {
        return value > target;
      }
      if (operator === "lt") {
        return value < target;
      }
      return true;
    }

    toTime(value) {
      if (!value) {
        return null;
      }
      const date = value instanceof Date ? value : new Date(value);
      const time = date.getTime();
      return Number.isNaN(time) ? null : time;
    }

    toValueList(value) {
      if (Array.isArray(value)) {
        return value;
      }
      return String(value || "")
        .split(",")
        .map(function(item) { return item.trim(); })
        .filter(Boolean);
    }

    applyKendoFilter(rows, filter) {
      if (Array.isArray(filter)) {
        filter = {
          logic: "and",
          filters: filter
        };
      }
      if (!filter || !filter.filters) {
        return rows;
      }
      return rows.filter((row) => this.matchesKendoFilter(row, filter));
    }

    matchesKendoFilter(row, filter) {
      if (filter.filters) {
        const matches = filter.filters.map((child) => this.matchesKendoFilter(row, child));
        return filter.logic === "or" ? matches.some(Boolean) : matches.every(Boolean);
      }

      const value = row[filter.field];
      const expected = filter.value;
      switch (filter.operator) {
        case "contains":
          return String(value || "").toLowerCase().indexOf(String(expected || "").toLowerCase()) !== -1;
        case "eq":
          return String(value) === String(expected);
        case "neq":
          return String(value) !== String(expected);
        case "gte":
          return value >= expected;
        case "lte":
          return value <= expected;
        case "gt":
          return value > expected;
        case "lt":
          return value < expected;
        default:
          return true;
      }
    }

    applySort(rows, sort) {
      const sorts = global.CrudUtils.ensureArray(sort);
      if (!sorts.length) {
        return rows;
      }

      return rows.slice().sort(function(left, right) {
        for (let index = 0; index < sorts.length; index += 1) {
          const item = sorts[index];
          const leftValue = left[item.field];
          const rightValue = right[item.field];
          if (leftValue < rightValue) {
            return item.dir === "desc" ? 1 : -1;
          }
          if (leftValue > rightValue) {
            return item.dir === "desc" ? -1 : 1;
          }
        }
        return 0;
      });
    }

    extractId(url) {
      return Number(url.split("/").pop());
    }

    extractClientPrintId(url) {
      const match = String(url || "").match(/\/clientes\/(\d+)\/print\//);
      return match ? Number(match[1]) : null;
    }

    extractPrintFormat(url) {
      return String(url || "").split("/").pop();
    }

    extractClientActionId(url) {
      const match = String(url || "").match(/\/clientes\/(\d+)\/actions\//);
      return match ? Number(match[1]) : null;
    }

    extractClientAction(url) {
      return String(url || "").split("/").pop();
    }

    extractClientStepHistoryId(url) {
      const match = String(url || "").match(/\/clientes\/(\d+)\/steps\//);
      return match ? Number(match[1]) : null;
    }

    extractClientStepHistoryStep(url) {
      const match = String(url || "").match(/\/steps\/([^\/]+)\/history$/);
      return match ? decodeURIComponent(match[1]) : "";
    }

    extractSortId(url) {
      return decodeURIComponent(url.split("/").pop());
    }
  }

  global.DemoMockHttpClient = DemoMockHttpClient;
})(window);
