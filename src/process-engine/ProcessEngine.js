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
      this.statusTimer = null;
      this.eventSource = null;
      this.eventFallbackTimer = null;
      this.resultGrid = null;
    }

    init() {
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
        this.render();
        if (typeof this.options.onLastUpdated === "function") {
          this.options.onLastUpdated(new Date());
        }
        return this;
      }).catch((error) => {
        this.renderError(error);
        throw error;
      });
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
      this.processButton.kendoButton({
        icon: this.getProcessActionIcon(),
        themeColor: "primary",
        click: () => this.execute()
      });
    }

    renderParameterField(form, field) {
      const wrapper = $("<div class=\"process-field\"></div>")
        .attr("data-process-field", field.name)
        .appendTo(form);
      const label = $("<label></label>")
        .attr("for", field.inputId)
        .text(field.label)
        .appendTo(wrapper);
      if (field.required) {
        $("<span class=\"process-required\"></span>").text(" *").appendTo(label);
      }
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
      this.fieldInputs[field.name] = input;
    }

    initializeFieldWidget(field, input) {
      const type = field.type;
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
      if (this.isProcessing) {
        return Promise.resolve(false);
      }
      const parameters = this.collectParameters();
      if (!parameters.valid) {
        global.CrudUtils.showMessage(parameters.message, "warning");
        return Promise.resolve(false);
      }

      const endpoint = this.getEndpoint("process");
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint de processamento nao configurado.", "error");
        return Promise.resolve(false);
      }

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
        const value = this.getFieldValue(field);
        values[field.name] = value;
        this.setFieldInvalid(field, false);
        if (!missing && field.required && (value == null || value === "")) {
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
            title: column.title || column.label || column.field,
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
          widget.enable(!this.isProcessing);
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

    normalizeField(field) {
      const source = field || {};
      const name = String(source.field || source.name || source.id || "").trim();
      const id = String(source.id || name).replace(/[^A-Za-z0-9_-]+/g, "-");
      return Object.assign({}, source, {
        id,
        name,
        type: String(source.type || "text"),
        label: source.label || source.title || name,
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

    buildContextPayload() {
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
