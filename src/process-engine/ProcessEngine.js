(function(global, $) {
  "use strict";

  const FINAL_STATUSES = ["succeeded", "failed", "cancelled", "canceled", "finished", "completed", "done"];

  class ProcessEngine {
    constructor(options) {
      this.options = options || {};
      this.root = $(this.options.root);
      this.httpClient = this.options.httpClient || new global.CrudHttpClient();
      this.configLoader = new global.CrudConfigLoader();
      this.loader = new global.ProcessDefinitionLoader({ httpClient: this.httpClient });
      this.validator = new global.ProcessDefinitionValidator();
      this.config = this.options.config || {};
      this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
      this.definition = null;
      this.fieldWidgets = {};
      this.fieldInputs = {};
      this.isProcessing = false;
      this.destroyed = false;
      this.statusTimer = null;
      this.eventSource = null;
      this.eventFallbackTimer = null;
      this.resultGrid = null;
    }

    init() {
      global.CrudUtils.beginRenderGate(this.root);
      this.renderLoading();
      return this.loadConfig().then(() => {
        this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
        return this.loader.load({
          definitionUrl: this.options.definitionUrl,
          definition: this.options.definition,
          screenId: this.options.screenId || this.options.processId,
          securityPolicy: this.securityPolicy
        });
      }).then((definition) => {
        this.definition = this.normalizeDefinition(definition);
        this.validator.validate(this.definition, { securityPolicy: this.securityPolicy });
        this.applyDefinitionSecurity(this.definition);
        this.initializeNavigationContext();
        return this.prepareApiCompany().then(() => {
        this.render();
        if (this.apiCompanySelection) { this.apiCompanySelection.render(this.options.apiCompanyHost || $("<div></div>").prependTo(this.root)); }
        if (typeof this.options.onLastUpdated === "function") {
          this.options.onLastUpdated(new Date());
        }
        global.CrudUtils.completeRenderGate(this.root);
        return this;
        });
      }).catch((error) => {
        this.renderError(error);
        global.CrudUtils.failRenderGate(this.root);
        throw error;
      });
    }

    async prepareApiCompany() {
      if (!this.definition.apiCompany) { return; }
      if (!global.ApiCompanySelection) { throw new Error("Componente Empresa API indisponível. Atualize a aplicação."); }
      this.apiCompanySelection = global.ApiCompanySelection.forDefinition(this.definition, this.httpClient, {
        beforeChange: () => {
          if (this.isProcessing) { global.CrudUtils.showMessage("Aguarde o processamento antes de trocar a Empresa API.", "warning"); return false; }
          return global.CrudUtils.confirm("Trocar a Empresa API e limpar os parâmetros atuais?", {title:"Empresa API",confirmText:"Trocar empresa",cancelText:"Continuar editando"});
        },
        onChange: () => { this.stopJobPolling(); this.stopJobEvents(); this.clearResult(); this.render(); this.apiCompanySelection.render(this.options.apiCompanyHost || $("<div></div>").prependTo(this.root)); },
        onInvalid: () => global.CrudUtils.showMessage("A Empresa API mudou. Selecione novamente antes de processar.", "warning")
      });
      await this.apiCompanySelection.load();
      this.httpClient = this.apiCompanySelection.client(this.httpClient);
    }

    loadConfig() {
      return this.configLoader.load({
        configUrl: this.options.configUrl,
        config: this.options.config
      }).then((config) => {
        this.config = config || {};
        this.applyKendoTheme();
        this.applyTheme();
        return global.CrudUtils.loadLiteralBundle(this.config.literals || {}, this.httpClient).then(() => this.config);
      });
    }

    normalizeDefinition(definition) {
      const source = global.CrudUtils.clone(definition || {});
      source.pageType = source.pageType || "process";
      source.program = Object.assign({
        title: source.title || "Processamento",
        subtitle: source.subtitle || ""
      }, source.program || {});
      source.permissions = source.permissions || {};
      source.process = Object.assign({
        parameters: { fields: [] },
        wait: {
          mode: "auto",
          pollIntervalSeconds: 2,
          message: "Processando..."
        },
        result: {
          type: "message",
          openReportInNewTab: false
        }
      }, source.process || {});
      source.process.parameters = Object.assign({
        title: "Parametros",
        fields: []
      }, source.process.parameters || {});
      source.process.wait = Object.assign({
        mode: "auto",
        pollIntervalSeconds: 2,
        message: "Processando..."
      }, source.process.wait || {});
      source.process.result = Object.assign({
        type: "message",
        openReportInNewTab: false
      }, source.process.result || {});
      source.dataSource = source.dataSource || {};
      source.dataSource.api = Object.assign({}, source.api || {}, source.dataSource.api || {});
      source.api = source.dataSource.api;
      return source;
    }

    applyDefinitionSecurity(definition) {
      if (!definition) {
        return definition;
      }
      const api = definition.dataSource && definition.dataSource.api || definition.api || {};
      const screenId = this.getScreenId();
      Object.keys(api).forEach((key) => {
        api[key] = this.resolveEndpoint(api[key], key, screenId);
      });
      if (definition.process && definition.process.endpoints) {
        Object.keys(definition.process.endpoints).forEach((key) => {
          definition.process.endpoints[key] = this.resolveEndpoint(definition.process.endpoints[key], key, screenId);
        });
      }
      if (definition.process && definition.process.endpoint) {
        definition.process.endpoint = this.resolveEndpoint(definition.process.endpoint, "process", screenId);
      }
      if (definition.process && definition.process.statusEndpoint) {
        definition.process.statusEndpoint = this.resolveEndpoint(definition.process.statusEndpoint, "status", screenId);
      }
      definition.api = api;
      definition.dataSource.api = api;
      return definition;
    }

    resolveEndpoint(endpoint, fallbackEndpointId, screenId) {
      if (!endpoint) {
        return endpoint;
      }
      const inlineAllowed = !this.securityPolicy || !this.securityPolicy.endpoints || this.securityPolicy.endpoints.allowInlineUrls !== false;
      if (typeof endpoint === "string") {
        if (inlineAllowed && global.CrudUtils.isAllowedDocumentUrl(endpoint)) {
          return { url: endpoint, method: fallbackEndpointId === "status" ? "POST" : "POST" };
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

    applyKendoTheme() {
      const theme = this.config && this.config.theme || {};
      const href = theme.kendoTheme || "";
      const link = global.document && global.document.getElementById("kendo-theme-link");
      if (href && link && link.getAttribute("href") !== href) {
        link.setAttribute("href", href);
      }
    }

    applyTheme() {
      const theme = this.config && this.config.theme || {};
      const mode = String(theme.defaultMode || "light");
      if (global.document && global.document.body) {
        global.document.body.setAttribute("data-crud-theme", mode === "dark" ? "dark" : "light");
      }
    }

    renderLoading() {
      this.root.empty().append($("<section class=\"crud-loading\"></section>").text("Carregando processamento..."));
    }

    renderError(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar processamento.");
      this.root.empty().append($("<section class=\"crud-message crud-message-error\"></section>").text(normalized.message));
      if (normalized.details && normalized.details.errors) {
        const list = $("<ul></ul>").appendTo(this.root.find(".crud-message"));
        global.CrudUtils.ensureArray(normalized.details.errors).forEach(function(item) {
          $("<li></li>").text(item).appendTo(list);
        });
      }
    }

    render() {
      this.root.empty();
      this.fieldWidgets = {};
      this.fieldInputs = {};

      const screen = $("<section class=\"process-screen\"></section>").appendTo(this.root);
      if (this.options.hideHeader !== true) {
        this.renderHeader(screen);
      }
      this.renderParameters(screen);
      this.renderProcessingPanel(screen);
      this.renderResultPanel(screen);
      this.bindLookupParameterChanges();
      this.optionsPromise=this.loadParameterOptions();
    }

    renderHeader(screen) {
      const header = $("<header class=\"process-header\"></header>").appendTo(screen);
      $("<h1></h1>").text(this.definition.program.title || "Processamento").appendTo(header);
      if (this.definition.program.subtitle) {
        $("<p></p>").text(this.definition.program.subtitle).appendTo(header);
      }
    }

    renderParameters(screen) {
      const parameters = this.definition.process.parameters || {};
      const panel = $("<section class=\"process-parameters-panel\"></section>").appendTo(screen);
      $("<h2></h2>").text(parameters.title || "Parametros").appendTo(panel);
      const form = $("<form class=\"process-parameters-form\"></form>").appendTo(panel);

      global.CrudUtils.ensureArray(parameters.fields).forEach((field) => {
        this.renderParameterField(form, this.normalizeField(field));
      });

      const actions = $("<footer class=\"process-actionbar\"></footer>").appendTo(panel);
      this.processButton = $("<button type=\"button\"></button>")
        .text(this.getProcessActionLabel())
        .appendTo(actions);
      this.renderContextLogsButton(actions);
      this.processButton.kendoButton({
        icon: this.getProcessActionIcon(),
        enable: this.definition.permissions.process !== false,
        themeColor: "primary",
        click: () => this.execute()
      });
    }

    renderParameterField(form, field) {
      if (Object.prototype.hasOwnProperty.call(this.contextValues || {}, field.name)) { field = Object.assign({},field,{defaultValue:this.contextValues[field.name],readonly:true}); }
      const wrapper = $("<div class=\"process-field\"></div>")
        .attr("data-process-field", field.name)
        .appendTo(form);
      const labelRow = $("<div class=\"process-field-label-row\"></div>").appendTo(wrapper);
      const label = $("<label></label>")
        .attr("for", field.inputId)
        .text(field.label)
        .appendTo(labelRow);
      if (field.required) {
        $("<span class=\"process-required\"></span>").text(" *").appendTo(label);
      }
      global.CrudUtils.appendTechnicalInfoTrigger(labelRow, field.label, field.technicalProperties, {
        cssClass: "process-technical-trigger",
        dataRole: "process-technical-info"
      });
      const input = $("<input>")
        .attr("id", field.inputId)
        .attr("name", field.name)
        .appendTo(wrapper);
      if (field.placeholder) {
        input.attr("placeholder", field.placeholder);
      }
      if (field.description) {
        $("<p class=\"process-field-note\"></p>").text(field.description).appendTo(wrapper);
      }
      if (field.readonly) {
        input.prop("readonly", true);
      }
      this.initializeFieldWidget(field, input);
      const widget = this.fieldWidgets[field.name];
      if (field.readonly && widget) { if (typeof widget.readonly === "function") { widget.readonly(true); } else if (typeof widget.enable === "function") { widget.enable(false); } }
      this.fieldInputs[field.name] = input;
    }

    bindLookupParameterChanges() {
      const fields=global.CrudUtils.ensureArray(this.definition.process.parameters.fields);
      const names=new Set();fields.forEach(field=>(field.optionsSource && field.optionsSource.parameterFields || []).forEach(name=>names.add(name)));
      names.forEach(name=>{const widget=this.fieldWidgets[name];if(widget){widget.bind('change',()=>{this.optionsPromise=this.loadParameterOptions();});}});
    }

    lookupParametersMatch(parameters) {
      const fields=global.CrudUtils.ensureArray(this.definition.process.parameters.fields);
      for(const field of fields) {
        const names=field.optionsSource && field.optionsSource.parameterFields;if(!names){continue;}
        if(this.optionsLoading || this.optionsFailed){return false;}
        const confirmed=this.lookupParameterValues && this.lookupParameterValues[field.field || field.name || field.id];
        if(!confirmed){return false;}
        for(const name of names) {
          const parameter=fields.find(item=>(item.field || item.name || item.id)===name);
          if(parameters[name]!==confirmed[name] || this.getFieldValue(this.normalizeField(parameter))!==confirmed[name]){return false;}
        }
      }
      return true;
    }

    loadParameterOptions() {
      if(this.destroyed) {return Promise.resolve(false);}
      const generation=this.optionsGeneration=(this.optionsGeneration || 0)+1;
      const fields=global.CrudUtils.ensureArray(this.definition.process.parameters.fields).filter(field=>field.optionsSource);
      this.optionsLoading=fields.length>0;this.optionsFailed=false;
      const enableButton=()=>{const button=this.processButton && this.processButton.data('kendoButton');if(button){button.enable(!this.destroyed && !this.optionsLoading && !this.optionsFailed && !this.isProcessing && this.definition.permissions.process!==false);}};
      enableButton();
      const tasks=fields.map(field=>{
        const source=field.optionsSource,endpoint=this.definition.api[source.endpointId],widget=this.fieldWidgets[field.field || field.name || field.id];
        if(!endpoint || endpoint.method!=='POST' || !endpoint.url || source.endpointId!=='options.'+(field.field || field.name || field.id) || !widget){return Promise.reject(global.CrudUtils.makeError('PROCESS_OPTIONS_INVALID','Consulta de selecao indisponivel.'));}
        widget.enable(false);
        const requestData={context:Object.assign({},this.contextValues)};
        if(source.parameterFields) {
          requestData.parameters={};
          try { source.parameterFields.forEach(name=>{const parameter=this.definition.process.parameters.fields.find(item=>(item.field || item.name || item.id)===name),value=this.getFieldValue(this.normalizeField(parameter));if(!parameter.options.some(option=>option.value===value)){throw global.CrudUtils.makeError('PROCESS_OPTIONS_INVALID','Parametro fora do enum.');}requestData.parameters[name]=value;}); }
          catch(error) { return Promise.reject(error); }
        }
        return Promise.resolve(this.httpClient.request({url:endpoint.url,method:'POST',data:requestData})).then(response=>{
          if(this.destroyed || generation!==this.optionsGeneration) {return;}
          const rows=response && response.data;if(!Array.isArray(rows) || rows.length>5000){throw global.CrudUtils.makeError('PROCESS_OPTIONS_INVALID','Resposta da selecao invalida.');}
          const ids=new Set();const data=rows.map(row=>{if(!row || !Number.isInteger(row.value) || row.value<1 || row.value>2147483647 || ids.has(row.value) || typeof row.text!=='string' || row.text.length>4000){throw global.CrudUtils.makeError('PROCESS_OPTIONS_INVALID','Opcao de selecao invalida.');}ids.add(row.value);return {value:row.value,text:row.text};});
          if(source.parameterFields){
            const previous=widget.value();widget.dataSource.data(data);widget.value(previous.filter(id=>ids.has(Number(id))));
            this.lookupParameterValues=this.lookupParameterValues || {};this.lookupParameterValues[field.field || field.name || field.id]=Object.assign({},requestData.parameters);
          }else{widget.dataSource.data(data);}
          widget.enable(!field.readonly);
        });
      });
      return Promise.all(tasks).then(()=>{if(this.destroyed || generation!==this.optionsGeneration){return false;}this.optionsLoading=false;enableButton();return true;}).catch(error=>{if(this.destroyed || generation!==this.optionsGeneration){return false;}this.optionsLoading=false;this.optionsFailed=true;fields.forEach(field=>{const w=this.fieldWidgets[field.field || field.name || field.id];if(w){w.enable(false);}});enableButton();this.renderProcessError(error);return false;});
    }

    initializeFieldWidget(field, input) {
      const type = field.type;
      if (type === 'array' && field.itemType==='integer' && field.editor==='multiselect' && $.fn.kendoMultiSelect) {
        input.kendoMultiSelect({dataTextField:'text',dataValueField:'value',filter:'contains',autoClose:false,dataSource:this.normalizeOptions(field.options || []),value:field.defaultValue || []});
        this.fieldWidgets[field.name]=input.data('kendoMultiSelect');return;
      }

      if (type === "date" && $.fn.kendoDatePicker) {
        input.kendoDatePicker({
          format: field.format || "dd/MM/yyyy",
          value: this.parseDate(field.defaultValue)
        });
        this.fieldWidgets[field.name] = input.data("kendoDatePicker");
        return;
      }
      if (type === "datetime" && $.fn.kendoDateTimePicker) {
        input.kendoDateTimePicker({
          format: field.format || "dd/MM/yyyy HH:mm",
          value: this.parseDate(field.defaultValue)
        });
        this.fieldWidgets[field.name] = input.data("kendoDateTimePicker");
        return;
      }
      if (["number", "integer", "decimal"].indexOf(type) >= 0 && $.fn.kendoNumericTextBox) {
        input.kendoNumericTextBox({
          format: field.format || (type === "integer" ? "n0" : "n2"),
          decimals: type === "integer" ? 0 : Number(field.decimals == null ? 2 : field.decimals),
          value: field.defaultValue == null ? null : Number(field.defaultValue)
        });
        this.fieldWidgets[field.name] = input.data("kendoNumericTextBox");
        return;
      }
      if (["enum", "option", "dropdown"].indexOf(type) >= 0 && $.fn.kendoDropDownList) {
        input.kendoDropDownList({
          dataTextField: "text",
          dataValueField: "value",
          optionLabel: field.required ? undefined : (field.optionLabel || "Selecione"),
          dataSource: this.normalizeOptions(field.options),
          value: field.defaultValue == null ? "" : String(field.defaultValue)
        });
        this.fieldWidgets[field.name] = input.data("kendoDropDownList");
        return;
      }
      if (type === "boolean") {
        const checkbox = $("<input type=\"checkbox\">")
          .attr("id", field.inputId)
          .attr("name", field.name);
        input.replaceWith(checkbox);
        checkbox.prop("checked", field.defaultValue === true || field.defaultValue === "true");
        this.fieldInputs[field.name] = checkbox;
        if ($.fn.kendoSwitch) {
          checkbox.kendoSwitch({ messages: { checked: "Sim", unchecked: "Nao" } });
          this.fieldWidgets[field.name] = checkbox.data("kendoSwitch");
        }
        return;
      }
      if ($.fn.kendoTextBox) {
        input.kendoTextBox({ value: field.defaultValue == null ? "" : String(field.defaultValue) });
        this.fieldWidgets[field.name] = input.data("kendoTextBox");
      } else if (field.defaultValue != null) {
        input.val(field.defaultValue);
      }
    }

    renderProcessingPanel(screen) {
      this.statusPanel = $("<section class=\"process-status-panel\" hidden></section>").appendTo(screen);
      this.statusText = $("<p class=\"process-status-message\"></p>").appendTo(this.statusPanel);
      this.statusMeta = $("<div class=\"process-status-meta\"></div>").appendTo(this.statusPanel);
    }

    renderResultPanel(screen) {
      this.resultPanel = $("<section class=\"process-result-panel\" hidden></section>").appendTo(screen);
      this.resultContent = $("<div class=\"process-result-content\"></div>").appendTo(this.resultPanel);
    }

    execute() {
      if (this.destroyed || this.isProcessing || this.isConfirming || this.optionsLoading || this.optionsFailed) {
        return Promise.resolve(false);
      }
      if (this.definition.permissions.process === false) { global.CrudUtils.showMessage("Sem permissao para executar o processo.", "warning"); return Promise.resolve(false); }
      let parameters;
      try {parameters=this.collectParameters();}catch(error){global.CrudUtils.showMessage(global.CrudUtils.unwrapError(error,'Selecao invalida.').message,'warning');return Promise.resolve(false);}
      if (!parameters.valid) {
        global.CrudUtils.showMessage(parameters.message, "warning");
        return Promise.resolve(false);
      }

      const endpoint = this.getEndpoint("process");
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint de processamento nao configurado.", "error");
        return Promise.resolve(false);
      }

      const confirmation = this.definition.process.actions && this.definition.process.actions.process && this.definition.process.actions.process.confirmation;
      if (confirmation) {
        this.isConfirming = true;
        return global.CrudUtils.confirm(confirmation.message, {title:'Confirmar processamento',confirmText:confirmation.confirmText || 'Confirmar',themeColor:'warning'}).then((confirmed)=>{
          this.isConfirming = false;
          return confirmed ? this.sendProcessRequest(endpoint,parameters) : false;
        }).catch((error)=>{this.isConfirming=false;this.renderProcessError(error);return null;});
      }
      return this.sendProcessRequest(endpoint,parameters);
    }

    sendProcessRequest(endpoint, parameters) {
      if (!this.lookupParametersMatch(parameters.values)) {return Promise.resolve(false);}
      if (this.destroyed || this.isProcessing) { return Promise.resolve(false); }
      this.setProcessing(true, this.definition.process.wait.message || "Processando...");
      this.clearResult();

      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {
          parameters: parameters.values,
          context: this.buildContextPayload(),
          runtime: endpoint.runtime || null
        }
      }).then((response) => {
        this.handleProcessResponse(response, parameters.values);
        return response;
      }).catch((error) => {
        this.setProcessing(false);
        this.renderProcessError(error);
        return null;
      });
    }

    handleProcessResponse(response, parameters) {
      if (response && (response.ok === false || response.success === false || String(response.status).toLowerCase() === "failed")) { throw global.CrudUtils.makeError("PROCESS_FAILED", response.error && response.error.message || response.message || "Processamento falhou."); }
      const result = response && response.result;
      const job = this.normalizeJob(response && (response.job || response.asyncJob || response.runtimeJob));
      const waitConfig = response && response.wait || this.definition.process.wait || {};
      const status = String(response && response.status || job.status || "").toLowerCase();

      if (result) {
        this.setProcessing(false);
        this.renderResult(result, response);
        if (job && this.isFinalStatus(status)) {
          this.notifyJobCompleted(job, response);
        }
        return;
      }

      if (job && String(waitConfig.mode || this.definition.process.wait.mode || "auto") !== "none" && !this.isFinalStatus(status)) {
        this.updateStatus(response && response.message || "Processamento iniciado.", job);
        this.waitForJob(job, parameters, waitConfig);
        return;
      }

      this.setProcessing(false);
      if (job) {
        this.renderResult({
          type: "job",
          message: response && response.message || "Job iniciado.",
          job
        }, response);
      } else {
        this.renderResult({
          type: "message",
          message: response && response.message || "Processamento concluido."
        }, response);
      }
    }

    waitForJob(job, parameters, waitConfig) {
      const config = waitConfig || {};
      const mode = String(config.mode || this.definition.process.wait.mode || "auto");
      if ((mode === "auto" || mode === "sse") && this.startJobEvents(job, parameters, config)) {
        return;
      }
      if (mode === "sse") {
        this.setProcessing(false);
        global.CrudUtils.showMessage("SSE indisponivel para acompanhar o processamento.", "warning");
        return;
      }
      this.startJobPolling(job, parameters, config);
    }

    startJobPolling(job, parameters, waitConfig) {
      const endpoint = this.getEndpoint("status");
      if (!endpoint || !endpoint.url) {
        this.setProcessing(false);
        this.renderResult({
          type: "job",
          message: "Job iniciado. O status deve ser acompanhado pela pagina de jobs.",
          job
        });
        return;
      }
      const seconds = Math.max(1, Number(waitConfig && waitConfig.pollIntervalSeconds || this.definition.process.wait.pollIntervalSeconds || 2));
      this.stopJobPolling();
      this.pollJobStatus(endpoint, job, parameters);
      this.statusTimer = window.setInterval(() => {
        this.pollJobStatus(endpoint, job, parameters);
      }, seconds * 1000);
    }

    pollJobStatus(endpoint, job, parameters) {
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {
          jobId: job.id,
          id: job.id,
          parameters: parameters || {},
          context: this.buildContextPayload(),
          runtime: endpoint.runtime || null
        }
      }).then((response) => {
        this.handleJobStatusResponse(response, job);
        return response;
      }).catch((error) => {
        this.stopJobPolling();
        this.setProcessing(false);
        this.renderProcessError(error);
        return null;
      });
    }

    startJobEvents(job, parameters, waitConfig) {
      if (typeof global.EventSource !== "function" || global.location && global.location.protocol === "file:") {
        return false;
      }
      const url = this.getJobEventUrl(job, waitConfig);
      if (!url) {
        return false;
      }

      let source = null;
      try {
        source = new global.EventSource(url);
      } catch (_) {
        return false;
      }

      this.stopJobEvents();
      this.eventSource = source;
      const handle = (event) => {
        const payload = this.parseEventPayload(event);
        this.handleJobStatusResponse(payload, job);
      };

      source.onmessage = handle;
      source.addEventListener("process-job", handle);
      source.addEventListener("runtime-job", handle);
      source.addEventListener("job", handle);
      source.onerror = () => {
        if (this.eventFallbackTimer) {
          return;
        }
        this.eventFallbackTimer = window.setTimeout(() => {
          if (this.eventSource === source && source.readyState !== global.EventSource.OPEN) {
            this.stopJobEvents();
            this.startJobPolling(job, parameters, waitConfig);
          }
        }, 8000);
      };
      return true;
    }

    getJobEventUrl(job, waitConfig) {
      const wait = waitConfig || this.definition.process.wait || {};
      const events = wait.events || {};
      const policyEndpoint = this.securityPolicy && this.securityPolicy.endpoints && this.securityPolicy.endpoints.runtimeEventsEndpoint || {};
      const configuredUrl = this.securityPolicy && this.securityPolicy.production ? "" : (events.url || wait.eventSourceUrl || "");
      const url = configuredUrl || policyEndpoint.url || "";
      if (!url) {
        return "";
      }
      const eventUrl = global.CrudUtils.replaceUrlParams(url, {
        screenId: this.getScreenId(),
        jobId: job.id
      });
      if (this.httpClient && typeof this.httpClient.buildRuntimeEventUrl === "function") {
        return this.httpClient.buildRuntimeEventUrl(eventUrl, {
          screenId: this.getScreenId(),
          channel: "process",
          jobId: job.id
        });
      }
      return eventUrl;
    }

    parseEventPayload(event) {
      try {
        return JSON.parse(event && event.data || "{}");
      } catch (_) {
        return {};
      }
    }

    handleJobStatusResponse(response, originalJob) {
      const job = this.normalizeJob(response && (response.job || response.asyncJob || response.runtimeJob)) || originalJob;
      const status = String(response && response.status || job && job.status || "").toLowerCase();
      this.updateStatus(response && response.message || this.getStatusMessage(status), job);

      if (!this.isFinalStatus(status)) {
        return;
      }

      this.stopJobPolling();
      this.stopJobEvents();
      this.setProcessing(false);
      this.renderResult(response && response.result || {
        type: status === "failed" ? "message" : "job",
        message: response && response.message || this.getStatusMessage(status),
        job
      }, response);
      this.notifyJobCompleted(job, response || {});
    }

    collectParameters() {
      const fields = global.CrudUtils.ensureArray(this.definition.process.parameters.fields).map((field) => this.normalizeField(field));
      const values = {};
      let missing = "";

      fields.forEach((field) => {
        const value = Object.prototype.hasOwnProperty.call(this.contextValues || {}, field.name) ? this.contextValues[field.name] : this.getFieldValue(field);
        values[field.name] = value;
        this.setFieldInvalid(field, false);
        if (!missing && field.required && (value == null || value === "" || Array.isArray(value) && value.length===0)) {
          missing = field.label;
          this.setFieldInvalid(field, true);
        }
      });

      return {
        valid: !missing,
        values,
        message: missing ? "Informe " + missing + "." : ""
      };
    }

    getFieldValue(field) {
      const widget = this.fieldWidgets[field.name];
      const input = this.fieldInputs[field.name];
      let value;
      if (widget && typeof widget.value === "function") {
        value = widget.value();
      } else if (input && input.attr("type") === "checkbox") {
        value = input.prop("checked");
      } else {
        value = input ? input.val() : null;
      }
      if (field.type==='array') {
        if (!Array.isArray(value) || value.length>100) { throw global.CrudUtils.makeError('PROCESS_COLLECTION_INVALID','Selecione ate100IDs inteiros.'); }
        const ids=value.map(id=>{if(!/^[1-9][0-9]{0,9}$/.test(String(id)) || Number(id)>2147483647) { throw global.CrudUtils.makeError('PROCESS_COLLECTION_INVALID','ID de selecao invalido.'); }return Number(id);});
        if(new Set(ids).size!==ids.length) {throw global.CrudUtils.makeError('PROCESS_COLLECTION_INVALID','IDs repetidos.');}return ids;
      }
      if (value instanceof Date) {
        return kendo.toString(value, field.type === "datetime" ? "yyyy-MM-ddTHH:mm:ss" : "yyyy-MM-dd");
      }
      return value;
    }

    setFieldInvalid(field, invalid) {
      const wrapper = this.root.find("[data-process-field=\"" + field.name + "\"]");
      wrapper.toggleClass("is-invalid", Boolean(invalid));
    }

    clearResult() {
      if (this.resultGrid && typeof this.resultGrid.destroy === "function") {
        this.resultGrid.destroy();
      }
      this.resultGrid = null;
      if (this.resultPanel) {
        this.resultPanel.attr("hidden", true);
      }
      if (this.resultContent) {
        this.resultContent.empty();
      }
    }

    renderResult(result, response) {
      const normalized = this.normalizeResult(result, response);
      this.resultPanel.removeAttr("hidden");
      this.resultContent.empty();

      if (normalized.type === "grid") {
        this.renderGridResult(normalized);
        return;
      }
      if (normalized.type === "report") {
        this.renderReportResult(normalized);
        return;
      }
      if (normalized.type === "job") {
        this.renderJobResult(normalized);
        return;
      }
      if (normalized.type === "properties") {
        this.renderPropertiesResult(normalized);
        return;
      }
      this.renderMessageResult(normalized);
    }

    normalizeResult(result, response) {
      if (typeof result === "string") {
        return { type: "message", message: result };
      }
      const source = Object.assign({}, result || {});
      source.type = String(source.type || response && response.resultType || this.definition.process.result.type || "message");
      source.message = source.message || response && response.message || "";
      return source;
    }

    renderMessageResult(result) {
      $("<div class=\"process-result-message\"></div>")
        .text(result.message || "Processamento concluido.")
        .appendTo(this.resultContent);
    }

    renderJobResult(result) {
      const card = $("<article class=\"process-result-job\"></article>").appendTo(this.resultContent);
      $("<h2></h2>").text(result.title || "Job iniciado").appendTo(card);
      $("<p></p>").text(result.message || "O processamento foi registrado para execucao em segundo plano.").appendTo(card);
      if (result.job && result.job.id) {
        $("<span class=\"k-badge k-badge-solid k-badge-solid-info k-rounded-md\"></span>")
          .text("Job " + result.job.id)
          .appendTo(card);
      }
    }

    renderPropertiesResult(result) {
      if (typeof this.options.onResult === "function") {
        this.options.onResult({
          type: "properties",
          values: Object.assign({}, result.values || result.properties || {}),
          result: result
        });
      }
      $("<div class=\"process-result-message\"></div>")
        .text(result.message || "Propriedades aplicadas.")
        .appendTo(this.resultContent);
    }

    renderReportResult(result) {
      const card = $("<article class=\"process-result-report\"></article>").appendTo(this.resultContent);
      $("<h2></h2>").text(result.title || "Relatorio gerado").appendTo(card);
      $("<p></p>").text(result.message || "O relatorio esta disponivel para consulta.").appendTo(card);
      if (result.url && global.CrudUtils.isAllowedDocumentUrl(result.url)) {
        const link = $("<a class=\"k-button k-button-md k-rounded-md k-button-solid k-button-solid-primary\"></a>")
          .attr("href", result.url)
          .attr("target", "_blank")
          .attr("rel", "noopener noreferrer")
          .text(result.linkText || "Abrir relatorio")
          .appendTo(card);
        if (this.definition.process.result.openReportInNewTab === true || result.open === true) {
          global.open(result.url, "_blank", "noopener,noreferrer");
        }
        return link;
      }
      $("<p class=\"process-result-warning\"></p>").text("URL do relatorio nao informada ou nao permitida.").appendTo(card);
      return null;
    }

    renderGridResult(result) {
      const title = result.title || "Resultado";
      $("<h2 class=\"process-result-title\"></h2>").text(title).appendTo(this.resultContent);
      const gridElement = $("<div class=\"process-result-grid\"></div>").appendTo(this.resultContent);
      const rows = global.CrudUtils.ensureArray(result.data || result.rows || result.items);
      const columns = this.normalizeGridColumns(result.columns, rows);
      gridElement.kendoGrid({
        dataSource: {
          data: rows,
          pageSize: Number(result.pageSize || 10),
          schema: {
            total: function(data) {
              return data.length;
            }
          }
        },
        pageable: rows.length > Number(result.pageSize || 10),
        sortable: true,
        filterable: true,
        resizable: true,
        columnMenu: true,
        columns
      });
      this.resultGrid = gridElement.data("kendoGrid");
    }

    normalizeGridColumns(columns, rows) {
      const sourceColumns = global.CrudUtils.ensureArray(columns);
      if (sourceColumns.length) {
        return sourceColumns.map(function(column) {
          return {
            field: column.field,
            title: global.CrudUtils.escapeHtml(column.title || column.label || column.field),
            width: column.width,
            format: column.format
          };
        }).filter(function(column) {
          return Boolean(column.field);
        });
      }
      const first = rows && rows[0] || {};
      return Object.keys(first).map(function(field) {
        return { field, title: field };
      });
    }

    renderProcessError(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel executar o processamento.");
      this.resultPanel.removeAttr("hidden");
      this.resultContent.empty();
      $("<div class=\"process-result-error\"></div>").text(normalized.message).appendTo(this.resultContent);
    }

    setProcessing(processing, message) {
      this.isProcessing = Boolean(processing);
      if (this.processButton) {
        const widget = this.processButton.data("kendoButton");
        if (widget) {
          widget.enable(!this.isProcessing && !this.optionsLoading && !this.optionsFailed && this.definition.permissions.process!==false);
        }
      }
      if (!this.statusPanel) {
        return;
      }
      if (this.isProcessing) {
        this.statusPanel.removeAttr("hidden");
        this.statusText.text(message || "Processando...");
      } else {
        this.statusPanel.attr("hidden", true);
        this.statusText.text("");
        this.statusMeta.empty();
      }
    }

    updateStatus(message, job) {
      if (!this.statusPanel) {
        return;
      }
      this.statusPanel.removeAttr("hidden");
      this.statusText.text(message || "Aguardando processamento...");
      this.statusMeta.empty();
      if (job && job.id) {
        $("<span class=\"k-badge k-badge-outline k-badge-outline-info k-rounded-md\"></span>")
          .text("Job " + job.id)
          .appendTo(this.statusMeta);
      }
      if (job && job.status) {
        $("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>")
          .text(job.status)
          .appendTo(this.statusMeta);
      }
    }

    getStatusMessage(status) {
      switch (status) {
        case "queued":
          return "Job na fila.";
        case "running":
          return "Job em processamento.";
        case "succeeded":
        case "finished":
        case "completed":
        case "done":
          return "Processamento concluido.";
        case "failed":
          return "Processamento falhou.";
        default:
          return "Aguardando processamento.";
      }
    }

    notifyJobCompleted(job, response) {
      if (!job || !job.id || this.notifiedJobId === job.id) {
        return;
      }
      this.notifiedJobId = job.id;
      if (typeof this.options.onJobCompleted === "function") {
        this.options.onJobCompleted({
          job,
          response,
          definition: this.definition
        });
      }
    }

    getEndpoint(name) {
      const process = this.definition.process || {};
      const endpoints = process.endpoints || {};
      const api = this.definition.dataSource && this.definition.dataSource.api || this.definition.api || {};
      if (endpoints[name]) {
        return endpoints[name];
      }
      if (api[name]) {
        return api[name];
      }
      if (name === "process") {
        return process.endpoint || null;
      }
      if (name === "status") {
        return process.statusEndpoint || null;
      }
      return null;
    }

    getContextLogsConfig() {
      const logs = this.definition.logs || {};
      const field = logs.contextField;
      if (logs.enabled !== true || logs.mode !== "recordEvents" || ["admin.transacoes", "admin.logs-transacoes"].includes(this.getScreenId()) ||
          !/^[A-Za-z_][A-Za-z0-9_.:-]{0,159}$/.test(logs.screenId || "") || !/^[a-z][a-z0-9_]*$/.test(field || "") ||
          !global.CrudUtils.ensureArray(this.definition.contextFields).some(item => item.field === field) ||
          !Number.isInteger(this.contextValues[field]) || this.contextValues[field] < 1 || this.contextValues[field] > 2147483647 || !global.CrudEngine) {
        return null;
      }
      return logs;
    }

    renderContextLogsButton(container) {
      const logs = this.getContextLogsConfig();
      if (!logs) { return; }
      const button = $("<button type=\"button\" class=\"process-log-button\"></button>").text(logs.title || "Logs").appendTo(container);
      button.kendoButton({ icon: "list-unordered" });
      button.on("click", () => {
        const current = this.getContextLogsConfig();
        if (!current || this.destroyed) { return; }
        // Reuse the existing Kendo audit window without initializing another CRUD or executing a process.
        const viewer = Object.create(global.CrudEngine.prototype);
        viewer.definition = { screenId: current.screenId, dataModel: { primaryKey: current.contextField }, logs: current };
        viewer.securityPolicy = this.securityPolicy;
        viewer.openLogsWindow({ [current.contextField]: this.contextValues[current.contextField] }, current);
      });
    }

    normalizeField(field) {
      const source = field || {};
      const name = String(source.field || source.name || source.id || "").trim();
      const id = String(source.id || name).replace(/[^A-Za-z0-9_-]+/g, "-");
      return Object.assign({}, source, {
        id,
        name,
        type: String(source.type || "text"),
        label: source.label || source.title || name,
        technicalProperties: global.CrudUtils.normalizeTechnicalProperties(source.technicalProperties),
        inputId: (this.getDomIdPrefix() + "-" + id).replace(/-+/g, "-")
      });
    }

    normalizeOptions(options) {
      return global.CrudUtils.ensureArray(options).map(function(option) {
        if (typeof option === "string") {
          return { value: option, text: option };
        }
        return {
          value: option.value != null ? option.value : option.id,
          text: option.text || option.label || option.title || option.name || option.value || option.id || ""
        };
      });
    }

    normalizeJob(job) {
      if (!job) {
        return null;
      }
      return {
        id: job.id || job.jobId || job.uuid || "",
        status: job.status || "",
        type: job.type || job.job_type || job.jobType || "",
        title: job.title || job.name || ""
      };
    }

    parseDate(value) {
      if (!value) {
        return null;
      }
      const date = value instanceof Date ? value : new Date(value);
      return Number.isNaN(date.getTime()) ? null : date;
    }

    isFinalStatus(status) {
      return FINAL_STATUSES.indexOf(String(status || "").toLowerCase()) >= 0;
    }

    initializeNavigationContext() {
      this.contextValues = {};
      const supplied = this.options.contextValues || {};
      global.CrudUtils.ensureArray(this.definition.contextFields).forEach((item)=>{
        const raw=supplied[item.field];
        if (raw == null || raw === '') { if (item.required) { throw global.CrudUtils.makeError('PROCESS_CONTEXT_REQUIRED','Informe o contexto de '+item.field+'.'); } return; }
        if (!['string','number'].includes(typeof raw) || !/^[1-9][0-9]{0,9}$/.test(String(raw)) || Number(raw)>2147483647) { throw global.CrudUtils.makeError('PROCESS_CONTEXT_INVALID','Contexto invalido: '+item.field+'.'); }
        this.contextValues[item.field]=Number(raw);
      });
    }

    buildContextPayload() {
      if (global.CrudUtils.ensureArray(this.definition.contextFields).length) { return Object.assign({},this.contextValues); }

      return Object.assign({
        screenId: this.getScreenId(),
        programId: this.definition.program && (this.definition.program.id || this.definition.program.code) || "",
        programTitle: this.definition.program && this.definition.program.title || ""
      }, this.options.contextPayload || {});
    }

    getScreenId() {
      return String(
        this.definition && (this.definition.screenId || this.definition.id) ||
        this.definition && this.definition.program && (this.definition.program.screenId || this.definition.program.id) ||
        this.options.screenId ||
        "process"
      ).trim();
    }

    getDomIdPrefix() {
      return "process-" + this.getScreenId().replace(/[^A-Za-z0-9_-]+/g, "-");
    }

    getProcessActionLabel() {
      const action = this.definition.process && this.definition.process.actions && this.definition.process.actions.process || {};
      return action.label || this.definition.process.processText || "Processar";
    }

    getProcessActionIcon() {
      const action = this.definition.process && this.definition.process.actions && this.definition.process.actions.process || {};
      return action.icon || "play";
    }

    confirmDiscardChanges() {
      if (!this.isProcessing) {
        return Promise.resolve(true);
      }
      return global.CrudUtils.confirm("Existe um processamento em andamento. Deseja sair mesmo assim?", {
        title: "Processamento em andamento",
        confirmText: "Sair",
        cancelText: "Continuar"
      });
    }

    stopJobPolling() {
      if (this.statusTimer) {
        window.clearInterval(this.statusTimer);
        this.statusTimer = null;
      }
    }

    stopJobEvents() {
      if (this.eventFallbackTimer) {
        window.clearTimeout(this.eventFallbackTimer);
        this.eventFallbackTimer = null;
      }
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
    }

    destroy() {
      if (this.apiCompanySelection) { this.apiCompanySelection.destroy(); }
      this.destroyed = true;
      this.stopJobPolling();
      this.stopJobEvents();
      if (this.resultGrid && typeof this.resultGrid.destroy === "function") {
        this.resultGrid.destroy();
      }
      if (this.root && this.root.length) {
        kendo.destroy(this.root);
        this.root.empty();
      }
    }
  }

  global.ProcessEngine = ProcessEngine;
})(window, window.jQuery);
