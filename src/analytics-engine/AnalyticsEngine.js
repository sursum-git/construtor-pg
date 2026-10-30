(function(global, $) {
  "use strict";

  // Declarative card rendering shared by every API dashboard; no screen-specific code.
  class AnalyticsDashboardRenderer {
    static validate(definition) {
      const a = definition.analytics;
      const invalid = () => { throw global.CrudUtils.makeError("ANALYTICS_DASHBOARD_INVALID", "Configuracao do painel analytics invalida."); };
      const code = value => typeof value === "string" && /^[A-Za-z_][A-Za-z0-9_-]{0,119}$/.test(value) && !["__proto__", "prototype", "constructor"].includes(value);
      const fields = Object.create(null);
      a.datasets.forEach(d => {
        if (!code(d.id) || fields[d.id]) invalid();
        fields[d.id] = new Set((d.fields || []).map(f => typeof f === "string" ? f : f.field || f.id));
      });
      const navigation = a.navigation || {};
      Object.entries(navigation).forEach(([id, link]) => {
        if (!code(id) || !link || typeof link.url !== "string" || /[\s\\]/.test(link.url)) invalid();
        let u; try { u = new URL(link.url); } catch (_) { invalid(); }
        if (u.protocol !== "https:" || u.username || u.password || u.search || u.hash || u.port || !(a.navigationHosts || []).includes(u.hostname)) invalid();
      });
      const ids = new Set();
      const walk = (tiles, dataset, depth) => {
        if (depth > 2 || !Array.isArray(tiles) || !tiles.length || tiles.length > 50) invalid();
        tiles.forEach(t => {
          if (!t || !code(t.id) || ids.has(t.id) || !["group","kpi","progress","list","links"].includes(t.type)) invalid();
          ids.add(t.id);
          const ds = t.datasetId || dataset;
          if (!fields[ds] || Object.keys(t).some(k => !["id","type","title","datasetId","valueField","labelField","labelCase","format","items","children","collapsed","navigationId"].includes(k))) invalid();
          if (["kpi","progress","list"].includes(t.type) && !fields[ds].has(t.valueField)) invalid();
          if (t.type === "list" && !fields[ds].has(t.labelField)) invalid();
          if (t.labelCase !== undefined && (t.type !== "list" || !["none","title"].includes(t.labelCase))) invalid();
          if (t.format && !["n0","n2","c2","percent0","percent2"].includes(t.format)) invalid();
          if (t.navigationId && !Object.hasOwn(navigation,t.navigationId)) invalid();
          if (t.type === "links" && (!Array.isArray(t.items) || t.items.some(i => !i || !Object.hasOwn(navigation,i.navigationId) || Object.keys(i).some(k => k !== "navigationId")))) invalid();
          if (t.type === "group") walk(t.children,ds,depth+1);
        });
      };
      a.views.forEach(v => { if (v.type !== "dashboard" || !fields[v.datasetId]) invalid(); walk(v.tiles,v.datasetId,0); });
    }
    constructor(engine) { this.engine = engine; }
    format(value, format) {
      if (typeof value !== "number" || !Number.isFinite(value)) return "Indisponível";
      if (format === "percent0" || format === "percent2") return kendo.toString(value,format === "percent0" ? "n0" : "n2") + "%";
      return kendo.toString(value,format || "n2");
    }
    link(container,id) {
      const link = this.engine.definition.analytics.navigation[id];
      return $("<a></a>").text(link.label).attr({href:link.url,target:"_blank",rel:"noopener noreferrer"}).appendTo(container);
    }
    render(container,view,results) {
      const grid = $("<div class=\"analytics-dashboard-grid analytics-declarative-dashboard\"></div>").appendTo(container);
      this.tiles(grid,view.tiles,view.datasetId,results);
    }
    tiles(container,tiles,dataset,results) {
      tiles.forEach(tile => {
        const ds = tile.datasetId || dataset;
        const rows = results[ds] && results[ds].data || [];
        const card = $("<section class=\"analytics-dashboard-tile\"></section>").attr("data-tile-id",tile.id).appendTo(container);
        $("<h3></h3>").text(tile.title || "").appendTo(card);
        if (tile.type === "group") {
          card.addClass("analytics-dashboard-group");
          const button = $("<button type=\"button\"></button>").appendTo(card);
          const body = $("<div class=\"analytics-dashboard-group-body\"></div>").prop("hidden",tile.collapsed === true).appendTo(card);
          const update = () => {
            const open = !body.prop("hidden");
            button.attr({"aria-expanded":String(open),"aria-label":(open ? "Ocultar" : "Mostrar") + " indicadores de " + tile.title});
            button.text(open ? "Ocultar indicadores" : "Mostrar indicadores");
          };
          button.kendoButton({click:() => { body.prop("hidden",!body.prop("hidden")); update(); }});
          update(); this.tiles(body,tile.children,ds,results); return;
        }
        if (tile.type === "links") {
          const links = $("<ul class=\"analytics-dashboard-links\"></ul>").appendTo(card);
          tile.items.forEach(item => this.link($("<li></li>").appendTo(links),item.navigationId)); return;
        }
        if (tile.type === "list") {
          const list = $("<ul class=\"analytics-dashboard-list\"></ul>").appendTo(card);
          rows.forEach(row => {
            const li = $("<li></li>").appendTo(list);
            let label = row[tile.labelField] == null ? "Indisponível" : String(row[tile.labelField]);
            if (row[tile.labelField] != null && tile.labelCase === "title") {
              label = label.toLocaleLowerCase("pt-BR").replace(/\p{L}[\p{L}\p{M}]*/gu, word => {
                const letters = Array.from(word);
                return letters[0].toLocaleUpperCase("pt-BR") + letters.slice(1).join("");
              });
            }
            $("<span></span>").text(label).appendTo(li);
            $("<strong></strong>").text(this.format(row[tile.valueField],tile.format)).appendTo(li);
          });
          if (!rows.length) $("<p></p>").text("Nenhum registro disponível.").appendTo(card);
          return;
        }
        const value = rows.length === 1 ? rows[0][tile.valueField] : null;
        const metric = $("<div class=\"analytics-kpi\"></div>").appendTo(card);
        $("<strong></strong>").text(this.format(value,tile.format || (tile.type === "progress" ? "percent0" : "n2"))).appendTo(metric);
        if (tile.type === "progress") {
          metric.addClass("analytics-dashboard-progress");
          if (typeof value === "number" && Number.isFinite(value)) $("<div></div>").appendTo(metric).kendoProgressBar({min:0,max:100,value:Math.max(0,Math.min(100,value)),type:"percent"});
        }
        if (tile.navigationId) this.link(card,tile.navigationId);
      });
    }
  }

  class AnalyticsEngine {
    constructor(options) {
      this.options = options || {};
      this.root = $(this.options.root);
      this.httpClient = this.options.httpClient || new global.CrudHttpClient();
      this.configLoader = new global.CrudConfigLoader();
      this.config = this.options.config || {};
      this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
      this.definition = null;
      this.currentDataset = null;
      this.currentResult = null;
      this.parameterWidgets = {};
      this.viewContainers = {};
      this.isLoading = false;
      this.resultsByDataset = Object.create(null);
      this.queryGeneration = 0;
      this.destroyed = false;
    }

    init() {
      global.CrudUtils.beginRenderGate(this.root);
      this.renderLoading();
      return this.loadConfig().then(() => {
        return this.loadDefinition();
      }).then((definition) => {
        this.definition = this.normalizeDefinition(definition);
        this.applyDefinitionSecurity(this.definition);
        this.currentDataset = this.resolveInitialDataset();
        this.render();
        return this.runQuery();
      }).then(() => {
        global.CrudUtils.completeRenderGate(this.root);
        return this;
      }).catch((error) => {
        this.renderError(error);
        global.CrudUtils.failRenderGate(this.root);
        throw error;
      });
    }

    destroy() {
      this.destroyed = true;
      this.queryGeneration++;
      this.currentResult = null;
      this.resultsByDataset = Object.create(null);
      if (global.kendo) {
        global.kendo.destroy(this.root);
      }
      this.root.empty();
      this.parameterWidgets = {};
      this.viewContainers = {};
    }

    loadConfig() {
      return this.configLoader.load({
        configUrl: this.options.configUrl,
        config: this.options.config
      }).then((config) => {
        this.config = config || {};
        this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
        this.applyKendoTheme();
        return global.CrudUtils.loadLiteralBundle(this.config.literals || {}, this.httpClient).then(() => this.config);
      });
    }

    loadDefinition() {
      if (this.options.definition) {
        return Promise.resolve(global.CrudUtils.clone(this.options.definition));
      }

      const screenId = String(this.options.screenId || "").trim();
      if (screenId) {
        const request = global.CrudUtils.buildScreenDefinitionRequest(screenId, this.securityPolicy, "analytics");
        return this.httpClient.request(request);
      }

      const definitionUrl = String(this.options.definitionUrl || "").trim();
      if (!definitionUrl) {
        return Promise.reject(global.CrudUtils.makeError("ANALYTICS_DEFINITION_SOURCE_MISSING", "Nenhuma definicao analytics foi informada."));
      }
      if (this.securityPolicy.definitionSource && this.securityPolicy.definitionSource.allowDefinitionUrl === false) {
        return Promise.reject(global.CrudUtils.makeError("ANALYTICS_DEFINITION_URL_DISABLED", "Carregamento por definitionUrl livre desabilitado pela politica de seguranca."));
      }

      return this.httpClient.request({
        url: definitionUrl,
        method: "GET"
      });
    }

    normalizeDefinition(definition) {
      const source = global.CrudUtils.clone(definition || {});
      if (String(source.pageType || "") !== "analytics") {
        throw global.CrudUtils.makeError("INVALID_ANALYTICS_DEFINITION", "A definicao recebida nao e do tipo analytics.", {
          pageType: source.pageType || ""
        });
      }
      source.program = Object.assign({
        title: "Analytics",
        subtitle: ""
      }, source.program || {});
      source.dataSource = source.dataSource || {};
      source.dataSource.api = Object.assign({}, source.api || {}, source.dataSource.api || {});
      source.api = source.dataSource.api;
      source.analytics = Object.assign({
        datasets: [],
        views: []
      }, source.analytics || {});
      source.analytics.datasets = global.CrudUtils.ensureArray(source.analytics.datasets);
      source.analytics.views = global.CrudUtils.ensureArray(source.analytics.views);
      if (!source.analytics.datasets.length) {
        throw global.CrudUtils.makeError("ANALYTICS_DATASET_MISSING", "Definicao analytics precisa de ao menos um dataset.");
      }
      source.analytics.datasets = source.analytics.datasets.map(function(dataset, index) {
        return Object.assign({
          id: "dataset" + (index + 1),
          title: "Dataset " + (index + 1),
          parameters: [],
          executionMode: "live",
          limit: 1000
        }, dataset || {});
      });
      if (!source.analytics.views.length) {
        source.analytics.views = [
          { id: "grid", type: "grid", title: "Dados", datasetId: source.analytics.datasets[0].id }
        ];
      }
      source.analytics.endpoints = Object.assign({}, source.analytics.endpoints || {}, {
        schema: source.analytics.endpoints && source.analytics.endpoints.schema || source.api.schema,
        run: source.analytics.endpoints && source.analytics.endpoints.run || source.api.run,
        materialize: source.analytics.endpoints && source.analytics.endpoints.materialize || source.api.materialize,
        cacheStatus: source.analytics.endpoints && source.analytics.endpoints.cacheStatus || source.api.cacheStatus
      });
      if (source.analytics.presentation && source.analytics.presentation.mode === "dashboard") AnalyticsDashboardRenderer.validate(source);
      return source;
    }

    applyDefinitionSecurity(definition) {
      const api = definition.dataSource && definition.dataSource.api || definition.api || {};
      const endpoints = definition.analytics && definition.analytics.endpoints || {};
      const screenId = this.getScreenId();
      Object.keys(api).forEach((key) => {
        api[key] = this.resolveEndpoint(api[key], this.defaultEndpointId(key), screenId);
      });
      Object.keys(endpoints).forEach((key) => {
        endpoints[key] = this.resolveEndpoint(endpoints[key], this.defaultEndpointId(key), screenId);
      });
      definition.api = api;
      definition.dataSource.api = api;
      definition.analytics.endpoints = endpoints;
    }

    resolveEndpoint(endpoint, fallbackEndpointId, screenId) {
      if (!endpoint) {
        return endpoint;
      }
      const inlineAllowed = !this.securityPolicy || !this.securityPolicy.endpoints || this.securityPolicy.endpoints.allowInlineUrls !== false;
      if (typeof endpoint === "string") {
        if (inlineAllowed && global.CrudUtils.isAllowedDocumentUrl(endpoint)) {
          return { url: endpoint, method: "POST" };
        }
        return global.CrudUtils.resolveEndpointForPolicy({ endpointId: endpoint, method: "POST" }, fallbackEndpointId, screenId, this.securityPolicy);
      }
      const source = Object.assign({}, endpoint);
      if (source.url && inlineAllowed) {
        return Object.assign({ method: "POST" }, source, {
          method: String(source.method || "POST").toUpperCase()
        });
      }
      return global.CrudUtils.resolveEndpointForPolicy(source, fallbackEndpointId, screenId, this.securityPolicy);
    }

    defaultEndpointId(key) {
      return {
        schema: "analytics.schema",
        run: "analytics.query.run",
        materialize: "analytics.materialize",
        cacheStatus: "analytics.cache.status"
      }[key] || key;
    }

    getScreenId() {
      return String(this.definition && (this.definition.screenId || this.definition.program && this.definition.program.screenId) || this.options.screenId || "").trim();
    }

    resolveInitialDataset() {
      const datasets = this.definition.analytics.datasets;
      const requested = String(this.options.datasetId || "").trim();
      return datasets.find(function(dataset) {
        return String(dataset.id || "") === requested;
      }) || datasets[0];
    }

    applyKendoTheme() {
      const theme = this.config && this.config.theme || {};
      const href = theme.kendoTheme || "";
      const link = global.document && global.document.getElementById("kendo-theme-link");
      if (href && link && link.getAttribute("href") !== href) {
        link.setAttribute("href", href);
      }
    }

    renderLoading() {
      this.root.empty().append(
        $("<section class=\"crud-message crud-message-info\"></section>").text("Carregando analytics...")
      );
    }

    renderError(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar analytics.");
      this.root.empty().append(
        $("<section class=\"crud-message crud-message-error\"></section>").text(normalized.message)
      );
    }

    render() {
      this.root.empty().addClass("analytics-shell");
      const screen = $("<section class=\"analytics-screen\"></section>").appendTo(this.root);
      if (this.options.hideHeader !== true) {
        this.renderHeader(screen);
      }
      this.renderToolbar(screen);
      if (!this.isDashboardMode()) this.renderParameters(screen);
      this.renderViews(screen);
    }

    isDashboardMode() { return this.definition && this.definition.analytics.presentation && this.definition.analytics.presentation.mode === "dashboard"; }

    renderHeader(screen) {
      const program = this.definition.program || {};
      const header = $("<header class=\"analytics-header\"></header>").appendTo(screen);
      const titleArea = $("<div></div>").appendTo(header);
      $("<p class=\"analytics-kicker\"></p>").text("Consultas avancadas e BI").appendTo(titleArea);
      $("<h1></h1>").text(program.title || "Analytics").appendTo(titleArea);
      if (program.subtitle) {
        $("<p class=\"analytics-subtitle\"></p>").text(program.subtitle).appendTo(titleArea);
      }
      if (program.version) {
        $("<span class=\"analytics-version\"></span>").text("v" + program.version).appendTo(header);
      }
    }

    renderToolbar(screen) {
      if (this.isDashboardMode()) {
        const toolbar = $("<section class=\"analytics-toolbar\"></section>").appendTo(screen);
        this.refreshButton = $("<button type=\"button\"></button>").text("Atualizar").appendTo(toolbar);
        this.refreshButton.kendoButton({icon:"arrow-rotate-cw",themeColor:"primary",click:()=>this.runQuery()});
        this.statusElement = $("<span class=\"analytics-status\"></span>").appendTo(toolbar);
        return;
      }
      const toolbar = $("<section class=\"analytics-toolbar\"></section>").appendTo(screen);
      const datasetField = $("<label class=\"analytics-dataset-field\"></label>").appendTo(toolbar);
      $("<span></span>").text("Dataset").appendTo(datasetField);
      const datasetInput = $("<input>").appendTo(datasetField);
      datasetInput.kendoDropDownList({
        dataTextField: "title",
        dataValueField: "id",
        dataSource: this.definition.analytics.datasets.map(function(dataset) {
          return {
            id: dataset.id,
            title: dataset.title || dataset.id
          };
        }),
        value: this.currentDataset && this.currentDataset.id,
        change: () => {
          const widget = datasetInput.data("kendoDropDownList");
          this.currentDataset = this.definition.analytics.datasets.find(function(dataset) {
            return String(dataset.id || "") === String(widget.value() || "");
          }) || this.currentDataset;
          this.renderParameters(this.root.find(".analytics-screen"));
          this.runQuery();
        }
      });
      this.datasetSelect = datasetInput.data("kendoDropDownList");

      const actions = $("<div class=\"analytics-toolbar-actions\"></div>").appendTo(toolbar);
      this.refreshButton = $("<button type=\"button\"></button>").text("Atualizar").appendTo(actions);
      this.refreshButton.kendoButton({
        icon: "arrow-rotate-cw",
        themeColor: "primary",
        click: () => this.runQuery({ forceLive: true })
      });
      this.cacheButton = $("<button type=\"button\"></button>").text("Atualizar cache").appendTo(actions);
      this.cacheButton.kendoButton({
        icon: "clock-arrow-rotate",
        click: () => this.materializeCache()
      });
      this.statusElement = $("<span class=\"analytics-status\"></span>").appendTo(actions);
    }

    renderParameters(screen) {
      const existing = screen.find(".analytics-parameters-panel");
      if (existing.length) {
        existing.remove();
      }
      this.parameterWidgets = {};
      const panel = $("<section class=\"analytics-parameters-panel\"></section>");
      const toolbar = screen.find(".analytics-toolbar");
      if (toolbar.length) {
        panel.insertAfter(toolbar);
      } else {
        panel.appendTo(screen);
      }
      $("<h2></h2>").text("Filtros").appendTo(panel);
      const form = $("<div class=\"analytics-parameters-form\"></div>").appendTo(panel);
      const parameters = global.CrudUtils.ensureArray(this.currentDataset && this.currentDataset.parameters);
      if (!parameters.length) {
        $("<p class=\"analytics-muted\"></p>").text("Nenhum filtro configurado para este dataset.").appendTo(form);
        return;
      }
      parameters.forEach((parameter) => this.renderParameter(form, parameter));
    }

    renderParameter(form, parameter) {
      const field = $("<label class=\"analytics-field\"></label>").appendTo(form);
      $("<span></span>").text(parameter.label || parameter.id || parameter.field).appendTo(field);
      const input = $("<input>").appendTo(field);
      const type = String(parameter.type || "text");
      const id = String(parameter.id || parameter.field || "");
      if (type === "date" || type === "datetime") {
        input.kendoDatePicker({
          culture: "pt-BR",
          format: "dd/MM/yyyy",
          value: parameter.defaultValue ? kendo.parseDate(parameter.defaultValue) : null,
          change: () => this.runQuery()
        });
        this.parameterWidgets[id] = input.data("kendoDatePicker");
        return;
      }
      if (type === "integer" || type === "decimal" || type === "number" || type === "currency") {
        input.kendoNumericTextBox({
          format: type === "currency" ? "c2" : "n2",
          decimals: type === "integer" ? 0 : 2,
          value: parameter.defaultValue,
          change: () => this.runQuery()
        });
        this.parameterWidgets[id] = input.data("kendoNumericTextBox");
        return;
      }
      if (Array.isArray(parameter.options) && parameter.options.length) {
        input.kendoDropDownList({
          dataTextField: "text",
          dataValueField: "value",
          optionLabel: "Todos",
          dataSource: parameter.options,
          value: parameter.defaultValue || "",
          change: () => this.runQuery()
        });
        this.parameterWidgets[id] = input.data("kendoDropDownList");
        return;
      }
      input.kendoTextBox({
        value: parameter.defaultValue || ""
      });
      input.on("change", () => this.runQuery());
      this.parameterWidgets[id] = input.data("kendoTextBox");
    }

    renderViews(screen) {
      if (this.isDashboardMode()) {
        this.viewContainers = {};
        this.definition.analytics.views.forEach((view,index) => {
          this.viewContainers[String(view.id || view.type || index)] = $("<section class=\"analytics-view\"></section>").appendTo(screen);
        });
        return;
      }
      const panel = $("<section class=\"analytics-views-panel\"></section>").appendTo(screen);
      const tabRoot = $("<div class=\"analytics-tabs\"></div>").appendTo(panel);
      const list = $("<ul></ul>").appendTo(tabRoot);
      this.viewContainers = {};
      this.definition.analytics.views.forEach((view, index) => {
        const id = this.safeDomId(view.id || view.type || ("view-" + index));
        $("<li></li>").toggleClass("k-active", index === 0).text(view.title || this.viewTitle(view.type)).appendTo(list);
        this.viewContainers[String(view.id || view.type || index)] = $("<div></div>")
          .attr("id", "analytics-view-" + id)
          .addClass("analytics-view")
          .appendTo(tabRoot);
      });
      tabRoot.kendoTabStrip({
        animation: false
      });
    }

    runQuery(options) {
      if (this.isDashboardMode()) return this.runDashboardQuery();
      if (!this.currentDataset || this.isLoading) {
        return Promise.resolve(null);
      }
      const endpoint = this.getEndpoint("run");
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint analytics.query.run nao configurado.", "error");
        return Promise.resolve(null);
      }
      this.isLoading = true;
      this.setLoadingState(true);
      const payload = Object.assign({
        datasetId: this.currentDataset.id,
        parameters: this.collectParameters(),
        take: Number(this.currentDataset.limit || 1000) || 1000
      }, options || {});
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: payload
      }).then((result) => {
        this.currentResult = result || { data: [], total: 0, columns: [] };
        this.renderResult();
        if (typeof this.options.onLastUpdated === "function") {
          this.options.onLastUpdated(new Date());
        }
        return this.currentResult;
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel executar a consulta.");
        global.CrudUtils.showMessage(normalized.message, "error");
        return null;
      }).finally(() => {
        this.isLoading = false;
        this.setLoadingState(false);
      });
    }

    materializeCache() {
      const endpoint = this.getEndpoint("materialize");
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint analytics.materialize nao configurado.", "error");
        return Promise.resolve(null);
      }
      const payload = {
        datasetId: this.currentDataset && this.currentDataset.id,
        parameters: this.collectParameters()
      };
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: payload
      }).then((result) => {
        const message = result && result.message || "Atualizacao de cache solicitada.";
        global.CrudUtils.showMessage(message, "success");
        return result;
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel atualizar o cache.");
        global.CrudUtils.showMessage(normalized.message, "error");
        return null;
      });
    }

    collectParameters() {
      const values = {};
      Object.keys(this.parameterWidgets).forEach((key) => {
        const widget = this.parameterWidgets[key];
        if (!widget || typeof widget.value !== "function") {
          return;
        }
        const value = widget.value();
        if (value === null || value === "") {
          return;
        }
        if (value instanceof Date) {
          values[key] = kendo.toString(value, "yyyy-MM-dd");
        } else {
          values[key] = value;
        }
      });
      return values;
    }

    runDashboardQuery() {
      if (this.destroyed || this.isLoading || !this.currentDataset) return Promise.resolve(null);
      const generation = ++this.queryGeneration;
      const endpoint = this.getEndpoint("run");
      if (!endpoint || !endpoint.url) return Promise.reject(global.CrudUtils.makeError("ANALYTICS_ENDPOINT_MISSING","Endpoint de consulta indisponivel."));
      const ids = new Set();
      const walk = (tiles,base) => tiles.forEach(t => { const id=t.datasetId||base; if (t.type!=="links" && t.type!=="group") ids.add(id); if(t.children) walk(t.children,id); });
      this.definition.analytics.views.forEach(view => walk(view.tiles,view.datasetId));
      this.isLoading=true; this.setLoadingState(true);
      const datasetIds=Array.from(ids);
      const grouped=datasetIds.length>0 && datasetIds.length<=10 && datasetIds.every(id=>this.definition.analytics.datasets.find(d=>d.id===id)?.source?.type==='api');
      const pending=grouped
        ? this.httpClient.request({url:endpoint.url,method:endpoint.method||"POST",data:{datasetIds}}).then(response=>{
          if(!response || !response.datasets || typeof response.datasets!=="object")throw global.CrudUtils.makeError("ANALYTICS_DATA_INVALID","Resposta de indicadores invalida.");
          return datasetIds.map(id=>[id,response.datasets[id]]);
        })
        : Promise.all(datasetIds.map(id => {
        const dataset=this.definition.analytics.datasets.find(d=>d.id===id);
        return this.httpClient.request({url:endpoint.url,method:endpoint.method||"POST",data:{datasetId:id,take:dataset.limit||1000}}).then(result=>[id,result]);
      }));
      return pending.then(entries => {
        if(this.destroyed || generation!==this.queryGeneration) return null;
        const results=Object.create(null);
        entries.forEach(([id,result])=>{if(!result||!Array.isArray(result.data))throw global.CrudUtils.makeError("ANALYTICS_DATA_INVALID","Resposta de indicadores invalida.");results[id]=result;});
        this.resultsByDataset=results;this.currentResult=results[this.currentDataset.id]||null;this.renderResult();
        const row=this.currentResult && this.currentResult.data[0];
        if(row && row.competencia) this.statusElement.text("Competência: "+row.competencia+" — atualizado "+kendo.toString(new Date(),"HH:mm"));
        if(typeof this.options.onLastUpdated==="function")this.options.onLastUpdated(new Date());
        return this.currentResult;
      }).catch(error=>{
        if(this.destroyed || generation!==this.queryGeneration) return null;
        this.currentResult=null;this.resultsByDataset=Object.create(null);
        Object.values(this.viewContainers).forEach(container=>{if(global.kendo)global.kendo.destroy(container);container.empty().append($("<p class=\"crud-message crud-message-error\"></p>").text(global.CrudUtils.unwrapError(error,"Indicadores indisponíveis.").message));});
        return null;
      }).finally(()=>{if(!this.destroyed && generation===this.queryGeneration){this.isLoading=false;this.setLoadingState(false);}});
    }

    getEndpoint(key) {
      const endpoints = this.definition.analytics && this.definition.analytics.endpoints || {};
      const api = this.definition.api || {};
      return endpoints[key] || api[key] || null;
    }

    setLoadingState(loading) {
      if (this.refreshButton && this.refreshButton.data("kendoButton")) {
        this.refreshButton.data("kendoButton").enable(!loading);
      }
      this.statusElement.text(loading ? "Consultando..." : this.statusText());
    }

    statusText() {
      if (!this.currentResult) {
        return "";
      }
      if (this.definition.analytics.presentation && this.definition.analytics.presentation.mode === "dashboard") {
        const row = this.currentResult.data && this.currentResult.data[0];
        if (row && typeof row.competencia === "string") return "Competência: " + row.competencia + " — ao vivo";
      }
      const total = Number(this.currentResult.total || 0);
      const cache = this.currentResult._runtime && this.currentResult._runtime.analyticsCache || {};
      const cacheText = cache.status === "hit" ? "cache" : "ao vivo";
      return total + " registro(s) - " + cacheText;
    }

    renderResult() {
      const result = this.currentResult || { data: [], columns: [] };
      this.definition.analytics.views.forEach((view, index) => {
        const key = String(view.id || view.type || index);
        const container = this.viewContainers[key];
        if (!container) {
          return;
        }
        if (global.kendo) global.kendo.destroy(container);
        container.empty();
        const type = String(view.type || "grid");
        if (type === "chart") {
          this.renderChart(container, view, result);
        } else if (type === "pivot") {
          this.renderPivot(container, view, result);
        } else if (type === "kpi") {
          this.renderKpi(container, view, result);
        } else if (type === "dashboard") {
          this.renderDashboard(container, view, result);
        } else {
          this.renderGrid(container, view, result);
        }
      });
      this.statusElement.text(this.statusText());
    }

    renderGrid(container, view, result) {
      const grid = $("<div class=\"analytics-grid\"></div>").appendTo(container);
      const columns = this.resolveGridColumns(result.columns || []);
      grid.kendoGrid({
        dataSource: {
          data: result.data || [],
          pageSize: Number(view.pageSize || 20)
        },
        height: view.height || 440,
        pageable: true,
        sortable: true,
        resizable: true,
        columnMenu: true,
        noRecords: {
          template: "Nenhum dado encontrado."
        },
        columns
      });
    }

    renderChart(container, view, result) {
      const rows = result.data || [];
      const categoryField = view.categoryField || this.firstColumnByRole(result.columns, "dimension") || this.firstColumn(result.columns);
      const valueField = view.valueField || this.firstColumnByRole(result.columns, "measure") || this.secondColumn(result.columns);
      const valueColumn = this.columnByField(result.columns, valueField);
      const valueFormat = view.valueFormat || this.normalizeValueFormat(valueColumn && valueColumn.format) || "n2";
      if (!rows.length || !categoryField || !valueField) {
        this.renderEmpty(container, "Sem dados suficientes para o grafico.");
        return;
      }
      $("<div class=\"analytics-chart\"></div>").appendTo(container).kendoChart({
        dataSource: { data: rows },
        seriesDefaults: { type: view.seriesType || "column" },
        series: [{ field: valueField, name: this.columnTitle(result.columns, valueField) }],
        categoryAxis: { field: categoryField, labels: { rotation: "auto" } },
        valueAxis: { labels: { format: valueFormat } },
        legend: { position: "bottom" },
        tooltip: { visible: true, format: valueFormat }
      });
    }

    renderPivot(container, view, result) {
      const rows = result.data || [];
      if (!rows.length) {
        this.renderEmpty(container, "Sem dados para pivot.");
        return;
      }
      if ($.fn.kendoPivotGrid) {
        try {
          $("<div class=\"analytics-pivot\"></div>").appendTo(container).kendoPivotGrid({
            height: view.height || 420,
            dataSource: {
              data: rows
            }
          });
          return;
        } catch (_) {
          container.empty();
        }
      }
      this.renderGrid(container, view, result);
    }

    renderKpi(container, view, result) {
      const rows = result.data || [];
      const valueField = view.valueField || this.firstColumnByRole(result.columns, "measure") || this.firstColumn(result.columns);
      const valueColumn = this.columnByField(result.columns, valueField);
      const valueFormat = view.format || this.normalizeValueFormat(valueColumn && valueColumn.format) || "n2";
      const value = rows.length && valueField ? rows.reduce(function(total, row) {
        return total + (Number(row[valueField]) || 0);
      }, 0) : 0;
      const card = $("<article class=\"analytics-kpi\"></article>").appendTo(container);
      $("<span></span>").text(view.title || this.columnTitle(result.columns, valueField) || "Indicador").appendTo(card);
      $("<strong></strong>").text(kendo.toString(value, valueFormat)).appendTo(card);
    }

    renderDashboard(container, view, result) {
      if(this.isDashboardMode()) { new AnalyticsDashboardRenderer(this).render(container,view,this.resultsByDataset); return; }
      const tiles = global.CrudUtils.ensureArray(view.tiles);
      const normalizedTiles = tiles.length ? tiles : [
        { id: "kpi", type: "kpi", title: "Total" },
        { id: "chart", type: "chart", title: "Grafico" }
      ];
      if ($.fn.kendoTileLayout) {
        this.renderTileLayout(container, normalizedTiles, result);
        return;
      }
      const grid = $("<div class=\"analytics-dashboard-grid\"></div>").appendTo(container);
      normalizedTiles.forEach((tile) => {
        const item = $("<section class=\"analytics-dashboard-tile\"></section>").appendTo(grid);
        $("<h3></h3>").text(tile.title || this.viewTitle(tile.type)).appendTo(item);
        this.renderTileBody(item, tile, result);
      });
    }

    renderTileLayout(container, tiles, result) {
      const host = $("<div class=\"analytics-tilelayout\"></div>").appendTo(container);
      host.kendoTileLayout({
        columns: Math.min(3, Math.max(1, tiles.length)),
        columnsWidth: "1fr",
        rowsHeight: 220,
        resizable: true,
        reorderable: true,
        containers: tiles.map((tile, index) => {
          const bodyId = "analytics-tile-body-" + this.safeDomId(tile.id || index);
          return {
            colSpan: tile.type === "chart" ? 2 : 1,
            rowSpan: 1,
            header: { text: tile.title || this.viewTitle(tile.type) },
            bodyTemplate: "<div id=\"" + bodyId + "\" class=\"analytics-tile-body\"></div>"
          };
        })
      });
      tiles.forEach((tile, index) => {
        const body = host.find("#analytics-tile-body-" + this.safeDomId(tile.id || index));
        this.renderTileBody(body, tile, result);
      });
    }

    renderTileBody(container, tile, result) {
      if (String(tile.type || "") === "chart") {
        this.renderChart(container, tile, result);
        return;
      }
      this.renderKpi(container, tile, result);
    }

    resolveGridColumns(columns) {
      return global.CrudUtils.ensureArray(columns).map(function(column) {
        const config = {
          field: column.field || column.id,
          title: column.title || column.label || column.field || column.id,
          width: column.role === "measure" ? 150 : 180
        };
        if (column.role === "measure" || ["integer", "decimal", "number", "currency"].indexOf(column.type) !== -1) {
          config.attributes = { class: "k-text-right" };
          config.headerAttributes = { class: "k-text-right" };
          config.format = this.normalizeGridFormat(column.format) || (column.type === "currency" ? "{0:c2}" : "{0:n2}");
        }
        return config;
      }.bind(this));
    }

    normalizeGridFormat(format) {
      const value = String(format || "").trim();
      if (!value) {
        return "";
      }
      return value.indexOf("{0:") === 0 ? value : "{0:" + value + "}";
    }

    normalizeValueFormat(format) {
      const value = String(format || "").trim();
      if (!value) {
        return "";
      }
      const match = value.match(/^\{0:([^}]+)\}$/);
      return match ? match[1] : value;
    }

    columnByField(columns, field) {
      return global.CrudUtils.ensureArray(columns).find(function(column) {
        return column && (column.field === field || column.id === field);
      }) || null;
    }

    firstColumnByRole(columns, role) {
      const column = global.CrudUtils.ensureArray(columns).find(function(item) {
        return item && item.role === role;
      });
      return column && (column.field || column.id) || "";
    }

    firstColumn(columns) {
      const column = global.CrudUtils.ensureArray(columns)[0];
      return column && (column.field || column.id) || "";
    }

    secondColumn(columns) {
      const column = global.CrudUtils.ensureArray(columns)[1];
      return column && (column.field || column.id) || this.firstColumn(columns);
    }

    columnTitle(columns, field) {
      const column = global.CrudUtils.ensureArray(columns).find(function(item) {
        return String(item.field || item.id || "") === String(field || "");
      });
      return column && (column.title || column.label || column.field || column.id) || "";
    }

    renderEmpty(container, message) {
      $("<section class=\"crud-message crud-message-info\"></section>").text(message).appendTo(container);
    }

    viewTitle(type) {
      return {
        grid: "Dados",
        chart: "Grafico",
        pivot: "Pivot",
        kpi: "Indicador",
        dashboard: "Dashboard"
      }[String(type || "grid")] || "Visualizacao";
    }

    safeDomId(value) {
      return String(value || "item").replace(/[^A-Za-z0-9_-]+/g, "-");
    }
  }

  global.AnalyticsEngine = AnalyticsEngine;
})(window, window.jQuery);
