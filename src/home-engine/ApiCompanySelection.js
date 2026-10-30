(function(global, $) {
  "use strict";
  const fail = (code, message) => ({ error: { code, message } });
  const WRITE = new Set(["create", "update", "delete", "batch", "process"]);

  class ApiCompanySelection {
    static forDefinition(definition, httpClient, options) {
      const config = definition && definition.apiCompany;
      if (!config || config.version !== 1 || !config.moduleId || !config.endpointModes) { return null; }
      return new ApiCompanySelection(config, definition.dataSource && definition.dataSource.api || definition.api || definition.endpoints || {}, httpClient, options);
    }

    constructor(config, endpoints, httpClient, options) {
      this.config = config; this.endpoints = endpoints; this.httpClient = httpClient;
      this.options = options || {}; this.selected = null; this.revision = 0;
      this.epoch = 0; this.writes = 0; this.changing = false; this.destroyed = false;
      this.items = []; this.enabled = false;
    }

    async load() {
      const result = await this.httpClient.request({ url: this.url(), method: "GET" });
      this.enabled = result.enabled === true;
      this.items = Array.isArray(result.items) ? result.items : [];
      this.selected = result.selected || null; this.revision = result.selectionRevision || 0;
      this.updateView(); return this;
    }

    url() { return "/api/runtime/api-companies/" + encodeURIComponent(this.config.moduleId); }

    async select(key) {
      if (this.destroyed) { throw fail("API_COMPANY_CONTEXT_CHANGED", "A tela foi encerrada."); }
      if (this.writes || this.changing) { throw fail("API_COMPANY_BUSY", "Aguarde a conclusão da operação antes de trocar a empresa."); }
      if (!this.items.some(item => item.key === key)) { throw fail("API_COMPANY_FORBIDDEN", "Empresa indisponível."); }
      this.changing = true; this.updateView();
      try {
        if (this.options.beforeChange && !(await this.options.beforeChange())) { return false; }
        this.epoch++;
        const result = await this.httpClient.request({url:this.url(),method:"POST",data:{key,expectedSelectionRevision:this.revision}});
        if (this.destroyed) { return false; }
        this.epoch++; // Also invalidate reads started while the selection POST was pending.
        this.selected = result; this.revision = result.selectionRevision;
        if (this.options.onChange) { await this.options.onChange(result); }
        return true;
      } catch (error) {
        if (this.isContextError(error)) {
          this.invalidate(error);
          try { await this.load(); if (this.options.onChange) { await this.options.onChange(this.selected); } } catch (refreshError) { this.selected = null; }
        }
        throw error;
      } finally { this.changing = false; this.updateView(); }
    }

    client(base) {
      // Per-engine facade: never replace the Home's shared client's request function.
      const facade = Object.create(base);
      facade.request = request => this.request(base, request || {});
      return facade;
    }

    descriptor(request) {
      const base = global.location && global.location.href || "http://localhost/";
      const url = new URL(request.url, base);
      if (this.config.screenId) {
        const marker="/api/runtime/screens/" + encodeURIComponent(this.config.screenId) + "/endpoints/";
        const start=url.pathname.lastIndexOf(marker);
        if (start >= 0 && start + marker.length < url.pathname.length) {
          return this.config.endpointModes[decodeURIComponent(url.pathname.slice(start + marker.length))] || null;
        }
        return null;
      }
      for (const [id, entry] of Object.entries(this.config.endpointModes)) {
        const endpoint = this.endpoints[id];
        if (endpoint && endpoint.url && new URL(endpoint.url, base).href === url.href) { return entry; }
      }
      return null;
    }

    async request(base, request) {
      const entry = this.descriptor(request);
      if (!entry) { return base.request(request); }
      if (this.destroyed) { throw fail("API_COMPANY_CONTEXT_CHANGED", "A tela foi encerrada."); }
      const writing = WRITE.has(entry.operation);
      if (this.changing && writing) { throw fail("API_COMPANY_BUSY", "Aguarde a troca da empresa."); }
      if (entry.mode !== "shared" && !this.selected) {
        if (entry.operation === "read") { return {data:[],total:0}; }
        throw fail("API_COMPANY_REQUIRED", "Selecione a Empresa API antes de continuar.");
      }
      const epoch = this.epoch;
      const next = entry.mode === "shared" ? request : Object.assign({}, request, {data:Object.assign({}, request.data || {}, {apiCompany:{selectionRevision:this.selected.selectionRevision}})});
      if (writing) { this.writes++; this.updateView(); }
      try {
        const result = await base.request(next);
        if (this.destroyed || (entry.mode !== "shared" && epoch !== this.epoch)) { throw fail("API_COMPANY_CONTEXT_CHANGED", "A empresa mudou durante a consulta. Atualize os dados."); }
        return result;
      } catch (error) {
        if (epoch === this.epoch && this.isContextError(error)) { this.invalidate(error); }
        throw error; // Writes are never retried, including operationCompleted responses.
      } finally { if (writing) { this.writes--; this.updateView(); } }
    }

    isContextError(error) {
      const code = error && error.error && error.error.code || "";
      return ["API_COMPANY_CONTEXT_CHANGED","API_COMPANY_RESULT_CONTEXT_CHANGED","API_COMPANY_FORBIDDEN","API_COMPANY_UNAVAILABLE"].includes(code);
    }

    invalidate(error) {
      this.epoch++; this.selected = null; this.updateView();
      if (this.options.onInvalid) { this.options.onInvalid(error); }
    }

    render(host) {
      if (!$ || !host) { return; }
      if (this.widget) { this.widget.destroy(); this.widget = null; }
      this.host = $(host).empty().addClass("api-company-selection");
      $("<label></label>").text("Empresa API").appendTo(this.host);
      const input = $("<input aria-label='Empresa API'>").appendTo(this.host);
      input.kendoDropDownList({dataTextField:"label",dataValueField:"key",optionLabel:"Selecione a empresa",dataSource:this.items,
        change:()=>{ const key=this.widget.value(); this.select(key).catch(error=>{
          global.CrudUtils.showMessage(global.CrudUtils.unwrapError(error,"Não foi possível trocar a empresa."),"warning"); this.updateView();
        }); }});
      this.widget = input.data("kendoDropDownList"); this.updateView();
    }

    updateView() {
      if (!this.widget) { return; }
      this.widget.value(this.selected ? this.selected.key : "");
      this.widget.enable(!this.changing && !this.writes && !this.destroyed && this.items.length > 0);
      this.host.toggle(this.enabled);
    }

    destroy() {
      this.destroyed = true; this.epoch++;
      if (this.widget) { this.widget.destroy(); this.widget = null; }
      if (this.host) { this.host.empty().hide(); }
    }
  }
  global.ApiCompanySelection = ApiCompanySelection;
})(window, window.jQuery);
