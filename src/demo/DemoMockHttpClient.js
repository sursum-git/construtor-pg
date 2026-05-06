(function(global) {
  "use strict";

  class DemoMockHttpClient {
    constructor(options) {
      const settings = options || {};
      const storageSuffix = settings.storageSuffix ? "-" + String(settings.storageSuffix).replace(/[^A-Za-z0-9_-]+/g, "-") : "";
      this.recordsStorageKey = "crud-demo-clientes-records-v4" + storageSuffix;
      this.layoutsStorageKey = "crud-demo-clientes-layouts-v2" + storageSuffix;
      this.helpSeenStorageKey = "crud-demo-help-seen-v1" + storageSuffix;
      this.records = this.loadInitialRecords();
      this.nextId = this.records.reduce(function(max, record) {
        return Math.max(max, Number(record.id) || 0);
      }, 0) + 1;

      const layoutState = this.loadJson(this.layoutsStorageKey) || {};
      this.nextLayoutId = layoutState.nextLayoutId || 1;
      this.nextSortId = layoutState.nextSortId || 1;
      this.nextGroupId = layoutState.nextGroupId || 1;
      this.nextFilterId = layoutState.nextFilterId || 1;
      this.savedLayouts = layoutState.savedLayouts || [];
      this.activeLayoutId = layoutState.activeLayoutId || null;
      this.savedSorts = layoutState.savedSorts || [];
      this.activeSortId = layoutState.activeSortId || null;
      this.savedGroups = layoutState.savedGroups || [];
      this.activeGroupId = layoutState.activeGroupId || null;
      this.savedFilters = layoutState.savedFilters || [];
      this.activeFilterId = layoutState.activeFilterId || null;
      this.helpSeen = this.loadJson(this.helpSeenStorageKey) || [];
    }

    loadInitialRecords() {
      const stored = this.loadJson(this.recordsStorageKey);
      if (Array.isArray(stored) && stored.length) {
        return stored;
      }

      const records = this.getDefaultRecords();
      this.saveJson(this.recordsStorageKey, records);
      return records;
    }

    getDefaultRecords() {
      return [
        { id: 1, nome: "Acme Comercio", email: "contato@acme.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "FORTALEZA", razao_social: "Acme Comercio Ltda", cnpj: "12.345.678/0001-90", data_cadastro: "2026-01-10", valor_total: 15420.5, qtde_pedidos: 12, observacao: "Cliente recorrente." },
        { id: 2, nome: "Beta Servicos", email: "financeiro@beta.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "SAO_PAULO", razao_social: "Beta Servicos Ltda", cnpj: "22.345.678/0001-90", data_cadastro: "2026-01-18", valor_total: 8450, qtde_pedidos: 5, observacao: "" },
        { id: 3, nome: "Casa Norte", email: "compras@casanorte.test", status: "INATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "CAUCAIA", razao_social: "Casa Norte Comercio Ltda", cnpj: "32.345.678/0001-90", data_cadastro: "2026-02-02", valor_total: 2100, qtde_pedidos: 2, observacao: "Cadastro pausado." },
        { id: 4, nome: "Delta Atacado", email: "operacoes@delta.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "RJ", cidade: "RIO_DE_JANEIRO", razao_social: "Delta Atacado Ltda", cnpj: "42.345.678/0001-90", data_cadastro: "2026-02-14", valor_total: 32200.75, qtde_pedidos: 18, observacao: "" },
        { id: 5, nome: "Escola Horizonte", email: "secretaria@horizonte.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "CAMPINAS", razao_social: "Escola Horizonte Ltda", cnpj: "52.345.678/0001-90", data_cadastro: "2026-02-22", valor_total: 4780.3, qtde_pedidos: 4, observacao: "" },
        { id: 6, nome: "Farma Popular", email: "ti@farmapopular.test", status: "INATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "SOBRAL", razao_social: "Farma Popular Ltda", cnpj: "62.345.678/0001-90", data_cadastro: "2026-03-03", valor_total: 975.9, qtde_pedidos: 1, observacao: "" },
        { id: 7, nome: "Grupo Solar", email: "adm@gruposolar.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "RJ", cidade: "NITEROI", razao_social: "Grupo Solar Ltda", cnpj: "72.345.678/0001-90", data_cadastro: "2026-03-11", valor_total: 18900, qtde_pedidos: 9, observacao: "" },
        { id: 8, nome: "Hotel Central", email: "reservas@central.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "FORTALEZA", razao_social: "Hotel Central Ltda", cnpj: "82.345.678/0001-90", data_cadastro: "2026-03-19", valor_total: 7600, qtde_pedidos: 6, observacao: "" },
        { id: 9, nome: "Industria Vale", email: "suprimentos@vale.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "SANTOS", razao_social: "Industria Vale Ltda", cnpj: "92.345.678/0001-90", data_cadastro: "2026-04-04", valor_total: 44100, qtde_pedidos: 21, observacao: "Contrato anual." },
        { id: 10, nome: "Jardins Office", email: "contato@jardinsoffice.test", status: "INATIVO", tipo_pessoa: "PF", uf: "RJ", cidade: "PETROPOLIS", razao_social: "", cnpj: "", data_cadastro: "2026-04-12", valor_total: 1200, qtde_pedidos: 2, observacao: "" },
        { id: 11, nome: "Kronos Logistica", email: "logistica@kronos.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "CE", cidade: "JUAZEIRO_NORTE", razao_social: "Kronos Logistica Ltda", cnpj: "11.345.678/0001-90", data_cadastro: "2026-04-20", valor_total: 16780, qtde_pedidos: 8, observacao: "" },
        { id: 12, nome: "Litoral Foods", email: "pedidos@litoralfoods.test", status: "ATIVO", tipo_pessoa: "PJ", uf: "SP", cidade: "SAO_PAULO", razao_social: "Litoral Foods Ltda", cnpj: "21.345.678/0001-90", data_cadastro: "2026-04-28", valor_total: 25300.4, qtde_pedidos: 11, observacao: "" }
      ];
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
        savedLayouts: this.savedLayouts,
        activeLayoutId: this.activeLayoutId,
        savedSorts: this.savedSorts,
        activeSortId: this.activeSortId,
        savedGroups: this.savedGroups,
        activeGroupId: this.activeGroupId,
        savedFilters: this.savedFilters,
        activeFilterId: this.activeFilterId
      });
    }

    persistHelpSeen() {
      this.saveJson(this.helpSeenStorageKey, this.helpSeen);
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
      if (normalizedUrl.endsWith("public/config/crud-engine.config.json") && embedded.config) {
        return global.CrudUtils.clone(embedded.config);
      }
      return null;
    }

    route(method, url, data) {
      const screenMatch = String(url || "").match(/^\/api\/runtime\/screens\/([^/?]+)$/);
      if (screenMatch && method === "GET") {
        return this.getRuntimeScreenDefinition(decodeURIComponent(screenMatch[1]));
      }
      const runtimeMatch = String(url || "").match(/^\/api\/runtime\/screens\/([^/]+)\/endpoints\/([^/?]+)/);
      if (runtimeMatch) {
        return this.routeRuntimeEndpoint(decodeURIComponent(runtimeMatch[1]), decodeURIComponent(runtimeMatch[2]), data);
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
        return this.delete(this.extractId(url));
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
      if (/^\/api\/cadastros\/clientes\/\d+\/actions\/(check-credit|send-welcome)$/.test(url) && method === "POST") {
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
      if (url === "/api/home/requests" && method === "GET") {
        return this.getHomeRequests(data);
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
      throw global.CrudUtils.makeError("SCREEN_NOT_FOUND", "Tela nao encontrada no runtime mock.", { screenId });
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
      if (normalizedScreenId === "home" || normalizedScreenId === "construtor-pg") {
        return this.routeHomeRuntimeEndpoint(normalizedScreenId, endpointId, data);
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
          return this.delete(id);
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
        case "help.markAsRead":
          return this.saveHelpSeen(data || {});
        default:
          throw global.CrudUtils.makeError("RUNTIME_ENDPOINT_NOT_FOUND", "Endpoint runtime mock nao encontrado.", { screenId, endpointId });
      }
    }

    routeHomeRuntimeEndpoint(screenId, endpointId, data) {
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
        case "home.alerts.list":
          return this.getHomeAlerts(data || {});
        case "home.requests.list":
          return this.getHomeRequests(data || {});
        default:
          throw global.CrudUtils.makeError("HOME_RUNTIME_ENDPOINT_NOT_FOUND", "Endpoint runtime da Home nao encontrado.", { screenId, endpointId });
      }
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
        data: rows,
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

      if (!data.id) {
        this.nextLayoutId += 1;
      }

      if (layout.isDefault) {
        this.savedLayouts.forEach(function(item) {
          item.isDefault = false;
        });
      }

      const index = this.savedLayouts.findIndex(function(item) {
        return item.id === layout.id;
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

      if (!data.id) {
        this.nextSortId += 1;
      }

      if (preset.isDefault) {
        this.savedSorts.forEach(function(item) {
          item.isDefault = false;
        });
      }

      const index = this.savedSorts.findIndex(function(item) {
        return item.id === preset.id;
      });
      if (index >= 0) {
        this.savedSorts[index] = preset;
      } else {
        this.savedSorts.push(preset);
      }

      const defaultSort = this.savedSorts.find(function(item) {
        return item.isDefault;
      });
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
        return item.id !== id;
      });
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

      if (!data.id) {
        this.nextGroupId += 1;
      }

      if (preset.isDefault) {
        this.savedGroups.forEach(function(item) {
          item.isDefault = false;
        });
      }

      const index = this.savedGroups.findIndex(function(item) {
        return item.id === preset.id;
      });
      if (index >= 0) {
        this.savedGroups[index] = preset;
      } else {
        this.savedGroups.push(preset);
      }

      const defaultGroup = this.savedGroups.find(function(item) {
        return item.isDefault;
      });
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
        return item.id !== id;
      });
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

      if (!data.id) {
        this.nextFilterId += 1;
      }

      if (preset.isDefault) {
        this.savedFilters.forEach(function(item) {
          item.isDefault = false;
        });
      }

      const index = this.savedFilters.findIndex(function(item) {
        return item.id === preset.id;
      });
      if (index >= 0) {
        this.savedFilters[index] = preset;
      } else {
        this.savedFilters.push(preset);
      }

      const defaultFilter = this.savedFilters.find(function(item) {
        return item.isDefault;
      });
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
        return item.id !== id;
      });
      if (this.activeFilterId === id) {
        this.activeFilterId = null;
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
        version: 1,
        source: "default",
        definitionHash: "clientes-demo-v1",
        grid: this.emptyGridLayout()
      });

      base.savedLayouts = global.CrudUtils.clone(this.savedLayouts);
      base.savedSorts = global.CrudUtils.clone(this.savedSorts);
      base.savedGroups = global.CrudUtils.clone(this.savedGroups);
      base.savedFilters = global.CrudUtils.clone(this.savedFilters);

      let active = this.savedLayouts.find((item) => item.id === this.activeLayoutId);
      if (!active) {
        active = this.savedLayouts.find(function(item) {
          return item.isDefault;
        });
      }

      if (active) {
        base.activeLayoutId = active.id;
        base.source = "user";
        base.grid = global.CrudUtils.clone(active.grid);
      } else {
        base.activeLayoutId = null;
        base.source = "default";
        base.grid = this.emptyGridLayout();
      }

      const activeSort = this.savedSorts.find(function(item) {
        return item.isDefault;
      }) || this.savedSorts.find((item) => item.id === this.activeSortId);
      if (activeSort) {
        base.activeSortId = activeSort.id;
        base.grid.sort = global.CrudUtils.clone(activeSort.sort);
      } else {
        base.activeSortId = null;
      }

      const activeGroup = this.savedGroups.find(function(item) {
        return item.isDefault;
      }) || this.savedGroups.find((item) => item.id === this.activeGroupId);
      if (activeGroup) {
        base.activeGroupId = activeGroup.id;
        base.grid.group = global.CrudUtils.clone(activeGroup.group);
        base.grid.groupAggregates = global.CrudUtils.clone(activeGroup.aggregates || []);
      } else {
        base.activeGroupId = null;
        base.grid.groupAggregates = global.CrudUtils.ensureArray(base.grid.groupAggregates);
      }

      const activeFilter = this.savedFilters.find(function(item) {
        return item.isDefault;
      }) || this.savedFilters.find((item) => item.id === this.activeFilterId);
      base.activeFilterId = activeFilter ? activeFilter.id : null;

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
        groupAggregates: []
      };
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

    create(data) {
      const record = Object.assign({}, data, {
        id: this.nextId,
        data_cadastro: new Date().toISOString().slice(0, 10),
        valor_total: Number(data.valor_total || 0),
        qtde_pedidos: Number(data.qtde_pedidos || 0)
      });
      this.nextId += 1;
      this.records.push(record);
      this.persistRecords();
      return record;
    }

    get(id) {
      const record = this.records.find(function(item) { return item.id === id; });
      if (!record) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro nao encontrado.", { id });
      }
      return global.CrudUtils.clone(record);
    }

    update(id, data) {
      const index = this.records.findIndex(function(item) { return item.id === id; });
      if (index === -1) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro nao encontrado.", { id });
      }
      const current = this.records[index];
      const next = Object.assign({}, current, data, { id });
      this.records[index] = next;
      this.persistRecords();
      return next;
    }

    delete(id) {
      const index = this.records.findIndex(function(item) { return item.id === id; });
      if (index === -1) {
        throw global.CrudUtils.makeError("RECORD_NOT_FOUND", "Registro nao encontrado.", { id });
      }
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
