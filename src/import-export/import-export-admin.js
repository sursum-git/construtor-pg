(function(global, $) {
  "use strict";

  function ImportExportAdmin(options) {
    this.options = options || {};
    this.root = $(this.options.root || "#import-export-admin-root");
    this.http = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.state = {
      items: [],
      current: null,
      preview: null,
      execution: null,
      executionHistory: [],
      currentExecutions: [],
      currentSchedules: [],
      selectedExecution: null
    };
    this.locale = "pt-BR";
    this.designerState = {
      type: null,
      selectedPath: null
    };
  }

  ImportExportAdmin.prototype.init = function() {
    this.renderShell();
    return this.loadLiterals().then(function() {
      return this.loadMappings();
    }.bind(this));
  };

  ImportExportAdmin.prototype.t = function(key, fallback, params) {
    return global.CrudUtils.resolveLiteral(key, params || null, fallback || "");
  };

  ImportExportAdmin.prototype.renderShell = function() {
    this.root.empty();
    const shell = $("<section class=\"import-export-admin-shell\"></section>").appendTo(this.root);
    const header = $("<header class=\"import-export-admin-header\"></header>").appendTo(shell);
    const title = $("<div class=\"import-export-admin-title\"></div>").appendTo(header);
    $("<h1></h1>").text("Integracoes").appendTo(title);
    $("<p></p>").text("Cadastre, valide e execute importacoes/exportacoes entre entidades e arquivos.").appendTo(title);
    const toolbar = $("<div class=\"import-export-admin-toolbar\"></div>").appendTo(header);
    this.newButton = this.createButton(toolbar, "Novo", "plus", "primary", this.handleNew.bind(this));
    this.saveButton = this.createButton(toolbar, "Salvar", "save", null, this.handleSave.bind(this));
    this.previewButton = this.createButton(toolbar, "Preview", "eye", null, this.handlePreview.bind(this));
    this.executeButton = this.createButton(toolbar, "Executar", "play", null, this.handleExecute.bind(this));
    this.reloadButton = this.createButton(toolbar, "Atualizar", "reload", null, this.loadMappings.bind(this));

    this.banner = $("<section class=\"import-export-admin-banner\"></section>")
      .text("Carregando mapeamentos...")
      .appendTo(shell);

    const content = $("<section class=\"import-export-admin-content\"></section>").appendTo(shell);
    const splitterHost = $("<div class=\"import-export-admin-splitter\"></div>").appendTo(content);
    const listPane = $("<section></section>").appendTo(splitterHost);
    const detailPane = $("<section></section>").appendTo(splitterHost);

    this.renderListPane(listPane);
    this.renderDetailPane(detailPane);

    splitterHost.kendoSplitter({
      orientation: "horizontal",
      panes: [
        { collapsible: true, size: "30%", min: "260px" },
        { collapsible: false }
      ]
    });
  };

  ImportExportAdmin.prototype.renderListPane = function(parent) {
    const panel = $("<article class=\"import-export-admin-panel\"></article>").appendTo(parent);
    $("<h2></h2>").text("Mapeamentos").appendTo(panel);
    this.gridElement = $("<div></div>").appendTo(panel);
    this.gridElement.kendoGrid({
      dataSource: {
        data: [],
        schema: {
          model: {
            id: "code",
            fields: {
              code: { type: "string" },
              name: { type: "string" },
              direction: { type: "string" },
              format: { type: "string" },
              status: { type: "string" }
            }
          }
        }
      },
      selectable: "row",
      sortable: true,
      filterable: true,
      pageable: false,
      columns: [
        { field: "code", title: "Codigo", width: 150 },
        { field: "name", title: "Nome" },
        { field: "direction", title: "Direcao", width: 110 },
        { field: "format", title: "Formato", width: 120 },
        { field: "status", title: "Status", width: 110 }
      ],
      change: this.handleGridSelection.bind(this)
    });
    this.grid = this.gridElement.data("kendoGrid");
  };

  ImportExportAdmin.prototype.renderDetailPane = function(parent) {
    const tabs = $("<div class=\"import-export-admin-tabs\"></div>").appendTo(parent);
    const tabList = $("<ul></ul>").appendTo(tabs);
    $("<li class=\"k-active\">Cadastro</li>").appendTo(tabList);
    $("<li>Preview</li>").appendTo(tabList);
    $("<li>Execucao</li>").appendTo(tabList);
    $("<li>Historico</li>").appendTo(tabList);
    $("<li>Agendamentos</li>").appendTo(tabList);

    const editTab = $("<div></div>").appendTo(tabs);
    const previewTab = $("<div></div>").appendTo(tabs);
    const executionTab = $("<div></div>").appendTo(tabs);
    const historyTab = $("<div></div>").appendTo(tabs);
    const schedulesTab = $("<div></div>").appendTo(tabs);

    this.renderEditTab(editTab);
    this.renderPreviewTab(previewTab);
    this.renderExecutionTab(executionTab);
    this.renderHistoryTab(historyTab);
    this.renderSchedulesTab(schedulesTab);

    tabs.kendoTabStrip({ animation: false });
    this.tabs = tabs.data("kendoTabStrip");
  };

  ImportExportAdmin.prototype.renderEditTab = function(parent) {
    const splitterHost = $("<div class=\"import-export-admin-editor-splitter\"></div>").appendTo(parent);
    const formPane = $("<section></section>").appendTo(splitterHost);
    const designerPane = $("<section></section>").appendTo(splitterHost);

    this.formPanel = $("<article class=\"import-export-admin-panel\"></article>").appendTo(formPane);
    $("<h2></h2>").text("Cadastro").appendTo(this.formPanel);
    this.form = $("<form class=\"import-export-admin-form\"></form>").appendTo(this.formPanel);

    this.codeInput = this.appendTextField(this.form, "Codigo");
    this.nameInput = this.appendTextField(this.form, "Nome");
    this.directionInput = this.appendDropDown(this.form, "Direcao", [
      { value: "import", text: "Importacao" },
      { value: "export", text: "Exportacao" }
    ]);
    this.targetTypeInput = this.appendDropDown(this.form, "Destino", [
      { value: "entity", text: "Entidade" },
      { value: "file", text: "Arquivo" }
    ]);
    this.targetCodeInput = this.appendTextField(this.form, "Codigo do destino");
    this.formatInput = this.appendDropDown(this.form, "Formato", [
      { value: "entity_copy", text: "entity_copy" },
      { value: "api_json", text: "api_json" },
      { value: "csv", text: "csv" },
      { value: "txt_layout", text: "txt_layout" },
      { value: "xml", text: "xml" }
    ]);
    this.statusInput = this.appendDropDown(this.form, "Status", [
      { value: "draft", text: "Rascunho" },
      { value: "active", text: "Ativo" },
      { value: "inactive", text: "Inativo" }
    ]);
    this.mappingInput = this.appendTextArea(this.form, "Mapping JSON", 16);
    this.parametersInput = this.appendTextArea(this.form, "Parametros (opcional)", 6);

    this.formatInput.bind("change", this.syncDesignerFromJson.bind(this));

    this.designerPanel = $("<article class=\"import-export-admin-panel import-export-admin-designer-panel\"></article>").appendTo(designerPane);
    const header = $("<div class=\"import-export-admin-section-header\"></div>").appendTo(this.designerPanel);
    $("<h2></h2>").text("Editor visual").appendTo(header);
    const tools = $("<div class=\"import-export-admin-inline-actions\"></div>").appendTo(header);
    this.syncDesignerButton = this.createButton(tools, "Sincronizar do JSON", "arrow-rotate-cw", null, this.syncDesignerFromJson.bind(this));
    this.addRootNodeButton = this.createButton(tools, "Adicionar raiz", "plus", null, this.handleAddRootNode.bind(this));

    this.designerMeta = $("<div class=\"import-export-admin-designer-meta\"></div>").appendTo(this.designerPanel);
    const designerBody = $("<div class=\"import-export-admin-designer-body\"></div>").appendTo(this.designerPanel);
    this.treeElement = $("<div class=\"import-export-admin-tree\"></div>").appendTo(designerBody);
    this.nodeEditor = $("<section class=\"import-export-admin-node-editor\"></section>").appendTo(designerBody);
    $("<h3></h3>").text("No selecionado").appendTo(this.nodeEditor);
    this.nodeSummary = $("<div class=\"import-export-admin-node-summary\"></div>").appendTo(this.nodeEditor);
    const nodeActions = $("<div class=\"import-export-admin-inline-actions\"></div>").appendTo(this.nodeEditor);
    this.applyNodeButton = this.createButton(nodeActions, "Aplicar no", "check", null, this.applyNodeEditor.bind(this));
    this.addChildButton = this.createButton(nodeActions, "Adicionar filho", "folder-add", null, this.handleAddChildNode.bind(this));
    this.addSiblingButton = this.createButton(nodeActions, "Adicionar irmao", "plus", null, this.handleAddSiblingNode.bind(this));
    this.removeNodeButton = this.createButton(nodeActions, "Remover no", "trash", null, this.handleRemoveNode.bind(this));
    this.nodeJsonInput = this.appendStandaloneTextArea(this.nodeEditor, "JSON do no", 16);

    splitterHost.kendoSplitter({
      orientation: "horizontal",
      panes: [
        { collapsible: false, size: "48%", min: "320px" },
        { collapsible: false }
      ]
    });
  };

  ImportExportAdmin.prototype.renderPreviewTab = function(parent) {
    const panel = $("<article class=\"import-export-admin-panel\"></article>").appendTo(parent);
    $("<h2></h2>").text("Preview").appendTo(panel);
    this.previewSummary = $("<div class=\"import-export-admin-summary\"></div>").appendTo(panel);
    this.previewDiagnostics = $("<div class=\"import-export-admin-diagnostics\"></div>").appendTo(panel);
    const splitterHost = $("<div class=\"import-export-admin-preview-splitter\"></div>").appendTo(panel);
    const left = $("<section></section>").appendTo(splitterHost);
    const right = $("<section></section>").appendTo(splitterHost);
    const leftPanel = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(left);
    $("<h3></h3>").text("Saida").appendTo(leftPanel);
    this.previewResult = $("<pre class=\"import-export-admin-pre\"></pre>").appendTo(leftPanel);
    const rightPanel = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(right);
    $("<h3></h3>").text("Estrutura").appendTo(rightPanel);
    this.previewStructureMeta = $("<div class=\"import-export-admin-node-summary\"></div>").appendTo(rightPanel);
    this.previewStructureTree = $("<div class=\"import-export-admin-tree\"></div>").appendTo(rightPanel);
    splitterHost.kendoSplitter({
      orientation: "horizontal",
      panes: [
        { collapsible: false, size: "50%", min: "300px" },
        { collapsible: false }
      ]
    });
  };

  ImportExportAdmin.prototype.renderExecutionTab = function(parent) {
    const panel = $("<article class=\"import-export-admin-panel\"></article>").appendTo(parent);
    const header = $("<div class=\"import-export-admin-section-header\"></div>").appendTo(panel);
    $("<h2></h2>").text("Execucao").appendTo(header);
    this.executionSummary = $("<div class=\"import-export-admin-summary\"></div>").appendTo(panel);
    this.executionDiagnostics = $("<div class=\"import-export-admin-diagnostics\"></div>").appendTo(panel);
    const body = $("<div class=\"import-export-admin-execution-body\"></div>").appendTo(panel);
    const left = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(body);
    $("<h3></h3>").text("Resultado").appendTo(left);
    this.executionResult = $("<pre class=\"import-export-admin-pre\"></pre>").appendTo(left);
    const right = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(body);
    $("<h3></h3>").text("Historico local").appendTo(right);
    this.executionHistoryElement = $("<div class=\"import-export-admin-history\"></div>").appendTo(right);
  };

  ImportExportAdmin.prototype.renderHistoryTab = function(parent) {
    const panel = $("<article class=\"import-export-admin-panel\"></article>").appendTo(parent);
    const header = $("<div class=\"import-export-admin-section-header\"></div>").appendTo(panel);
    $("<h2></h2>").text("Historico persistido").appendTo(header);
    const actions = $("<div class=\"import-export-admin-inline-actions\"></div>").appendTo(header);
    this.reloadExecutionsButton = this.createButton(actions, "Atualizar historico", "reload", null, this.loadExecutions.bind(this));
    this.exportExecutionButton = this.createButton(actions, "Exportar execucao", "download", null, this.handleExportSelectedExecution.bind(this));
    this.versionsSummary = $("<div class=\"import-export-admin-node-summary\"></div>").appendTo(panel);
    const filters = $("<div class=\"import-export-admin-inline-filters\"></div>").appendTo(panel);
    this.executionFilterMappingInput = this.appendStandaloneTextField(filters, "Mapping");
    this.executionFilterModeInput = this.appendStandaloneDropDown(filters, "Modo", [
      { value: "", text: "Todos" },
      { value: "execute", text: "Execucao" },
      { value: "scheduled", text: "Agendado" }
    ]);
    this.executionFilterStatusInput = this.appendStandaloneDropDown(filters, "Status", [
      { value: "", text: "Todos" },
      { value: "succeeded", text: "Sucesso" },
      { value: "failed", text: "Falha" }
    ]);
    this.applyExecutionFiltersButton = this.createButton(filters, "Aplicar filtro", "filter", null, this.loadExecutions.bind(this));
    const body = $("<div class=\"import-export-admin-execution-body\"></div>").appendTo(panel);
    const left = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(body);
    $("<h3></h3>").text("Execucoes").appendTo(left);
    this.executionsGridElement = $("<div></div>").appendTo(left);
    this.executionsGridElement.kendoGrid({
      dataSource: { data: [] },
      selectable: "row",
      sortable: true,
      pageable: false,
      change: this.handleExecutionSelection.bind(this),
      columns: [
        { field: "createdAt", title: "Quando", width: 180 },
        { field: "mappingCode", title: "Mapping", width: 150 },
        { field: "mode", title: "Modo", width: 100 },
        { field: "status", title: "Status", width: 100 },
        { field: "format", title: "Formato", width: 110 }
      ]
    });
    this.executionsGrid = this.executionsGridElement.data("kendoGrid");
    const right = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(body);
    $("<h3></h3>").text("Detalhe da execucao").appendTo(right);
    this.executionDetailSummary = $("<div class=\"import-export-admin-node-summary\"></div>").appendTo(right);
    this.executionDetailDiagnostics = $("<div class=\"import-export-admin-diagnostics\"></div>").appendTo(right);
    this.executionDetailResult = $("<pre class=\"import-export-admin-pre import-export-admin-pre-compact\"></pre>").appendTo(right);
    $("<h3></h3>").text("Versoes do mapping").appendTo(right);
    this.versionsGridElement = $("<div></div>").appendTo(right);
    this.versionsGridElement.kendoGrid({
      dataSource: { data: [] },
      selectable: "row",
      sortable: true,
      pageable: false,
      columns: [
        { field: "versionNumber", title: "Versao", width: 90 },
        { field: "createdAt", title: "Criada em", width: 180 },
        { field: "createdBy", title: "Por", width: 140 },
        { field: "changeSummary", title: "Resumo" }
      ]
    });
    this.versionsGrid = this.versionsGridElement.data("kendoGrid");
  };

  ImportExportAdmin.prototype.renderSchedulesTab = function(parent) {
    const panel = $("<article class=\"import-export-admin-panel\"></article>").appendTo(parent);
    const header = $("<div class=\"import-export-admin-section-header\"></div>").appendTo(panel);
    $("<h2></h2>").text("Agendamentos").appendTo(header);
    const actions = $("<div class=\"import-export-admin-inline-actions\"></div>").appendTo(header);
    this.reloadSchedulesButton = this.createButton(actions, "Atualizar agendamentos", "reload", null, this.loadSchedules.bind(this));
    this.runDueSchedulesButton = this.createButton(actions, "Executar vencidos", "play", null, this.handleRunDueSchedules.bind(this));
    const body = $("<div class=\"import-export-admin-execution-body\"></div>").appendTo(panel);
    const left = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(body);
    $("<h3></h3>").text("Cadastro rapido").appendTo(left);
    this.scheduleForm = $("<form class=\"import-export-admin-form\"></form>").appendTo(left);
    this.scheduleCodeInput = this.appendTextField(this.scheduleForm, "Codigo");
    this.scheduleNameInput = this.appendTextField(this.scheduleForm, "Nome");
    this.scheduleMappingCodeInput = this.appendTextField(this.scheduleForm, "Mapping");
    this.scheduleFrequencyInput = this.appendDropDown(this.scheduleForm, "Frequencia", [
      { value: "manual", text: "manual" },
      { value: "interval", text: "interval" },
      { value: "hourly", text: "hourly" },
      { value: "daily", text: "daily" }
    ]);
    this.scheduleIntervalInput = this.appendTextField(this.scheduleForm, "Intervalo (min)");
    this.scheduleDailyHourInput = this.appendTextField(this.scheduleForm, "Hora diaria");
    this.scheduleDailyMinuteInput = this.appendTextField(this.scheduleForm, "Minuto diario");
    this.scheduleParametersInput = this.appendTextArea(this.scheduleForm, "Parametros JSON", 8);
    const saveHost = $("<div class=\"import-export-admin-inline-actions\"></div>").appendTo(left);
    this.saveScheduleButton = this.createButton(saveHost, "Salvar agendamento", "save", null, this.handleSaveSchedule.bind(this));
    const right = $("<div class=\"import-export-admin-result-panel\"></div>").appendTo(body);
    $("<h3></h3>").text("Agendamentos cadastrados").appendTo(right);
    this.schedulesGridElement = $("<div></div>").appendTo(right);
    this.schedulesGridElement.kendoGrid({
      dataSource: { data: [] },
      selectable: "row",
      sortable: true,
      pageable: false,
      change: this.handleScheduleSelection.bind(this),
      columns: [
        { field: "code", title: "Codigo", width: 140 },
        { field: "mappingCode", title: "Mapping", width: 140 },
        { field: "frequency", title: "Frequencia", width: 120 },
        { field: "nextRunAt", title: "Proxima execucao", width: 180 },
        { field: "lastStatus", title: "Ultimo status", width: 120 }
      ]
    });
    this.schedulesGrid = this.schedulesGridElement.data("kendoGrid");
    this.scheduleSummary = $("<div class=\"import-export-admin-node-summary\"></div>").appendTo(right);
  };

  ImportExportAdmin.prototype.createButton = function(parent, text, icon, themeColor, handler) {
    const element = $("<button type=\"button\"></button>").appendTo(parent);
    element.kendoButton({
      icon: icon,
      themeColor: themeColor || "base"
    });
    element.data("kendoButton").element.text(text);
    element.on("click", handler);
    return element.data("kendoButton");
  };

  ImportExportAdmin.prototype.appendField = function(form, label) {
    const field = $("<label class=\"import-export-admin-field\"></label>").appendTo(form);
    $("<span></span>").text(label).appendTo(field);
    return field;
  };

  ImportExportAdmin.prototype.appendTextField = function(form, label) {
    const field = this.appendField(form, label);
    const input = $("<input type=\"text\">").appendTo(field);
    input.kendoTextBox();
    return input.data("kendoTextBox");
  };

  ImportExportAdmin.prototype.appendDropDown = function(form, label, items) {
    const field = this.appendField(form, label);
    const input = $("<input type=\"text\">").appendTo(field);
    input.kendoDropDownList({
      dataTextField: "text",
      dataValueField: "value",
      dataSource: items
    });
    return input.data("kendoDropDownList");
  };

  ImportExportAdmin.prototype.appendTextArea = function(form, label, rows) {
    const field = this.appendField(form, label);
    return this.appendStandaloneTextArea(field, null, rows);
  };

  ImportExportAdmin.prototype.appendStandaloneTextArea = function(parent, label, rows) {
    if (label) {
      $("<span class=\"import-export-admin-standalone-label\"></span>").text(label).appendTo(parent);
    }
    const input = $("<textarea class=\"import-export-admin-textarea\"></textarea>")
      .attr("rows", rows || 10)
      .appendTo(parent);
    if ($.fn.kendoTextArea) {
      input.kendoTextArea({ rows: rows || 10 });
      return input.data("kendoTextArea");
    }
    return {
      value: function(next) {
        if (arguments.length) {
          input.val(next);
        }
        return input.val();
      }
    };
  };

  ImportExportAdmin.prototype.appendStandaloneTextField = function(parent, label) {
    const field = $("<label class=\"import-export-admin-inline-field\"></label>").appendTo(parent);
    if (label) {
      $("<span></span>").text(label).appendTo(field);
    }
    const input = $("<input type=\"text\">").appendTo(field);
    input.kendoTextBox();
    return input.data("kendoTextBox");
  };

  ImportExportAdmin.prototype.appendStandaloneDropDown = function(parent, label, items) {
    const field = $("<label class=\"import-export-admin-inline-field\"></label>").appendTo(parent);
    if (label) {
      $("<span></span>").text(label).appendTo(field);
    }
    const input = $("<input type=\"text\">").appendTo(field);
    input.kendoDropDownList({
      dataTextField: "text",
      dataValueField: "value",
      dataSource: items
    });
    return input.data("kendoDropDownList");
  };

  ImportExportAdmin.prototype.loadLiterals = function() {
    return global.CrudUtils.loadLiteralBundle({
      enabled: global.location && global.location.protocol !== "file:",
      locale: this.locale,
      url: "/api/runtime/literals/{locale}"
    }, this.http).catch(function() {
      return null;
    });
  };

  ImportExportAdmin.prototype.loadMappings = function() {
    this.banner.text("Carregando mapeamentos...");
    return this.http.request({
      url: "/api/admin/import-export-mappings",
      method: "GET"
    }).then(function(response) {
      this.state.items = global.CrudUtils.ensureArray(response && response.items);
      this.grid.dataSource.data(this.state.items);
      this.banner.text("Selecione um mapeamento existente ou crie um novo.");
      this.loadExecutions();
      this.loadSchedules();
      if (this.state.current && this.state.current.code) {
        const current = this.state.items.find(function(item) { return item.code === this.state.current.code; }.bind(this));
        if (current) {
          return this.loadMapping(current.code);
        }
      }
      return response;
    }.bind(this)).catch(function(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel carregar os mapeamentos.");
      this.banner.text(normalized.message);
      throw error;
    }.bind(this));
  };

  ImportExportAdmin.prototype.handleGridSelection = function() {
    const item = this.grid.dataItem(this.grid.select());
    if (!item || !item.code) {
      return;
    }
    this.loadMapping(item.code);
  };

  ImportExportAdmin.prototype.loadMapping = function(code) {
    return this.http.request({
      url: "/api/admin/import-export-mappings/" + encodeURIComponent(code),
      method: "GET"
    }).then(function(response) {
      this.state.current = response && response.mapping ? response.mapping : null;
      this.state.currentVersions = global.CrudUtils.ensureArray(response && response.versions);
      this.bindCurrent();
      this.renderVersionHistory();
      this.banner.text("Mapeamento carregado para edicao.");
      return response;
    }.bind(this)).catch(function(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel carregar o mapeamento.");
      global.CrudUtils.showMessage(normalized.message, "error");
      throw error;
    });
  };

  ImportExportAdmin.prototype.handleNew = function() {
    this.state.current = null;
    this.state.currentVersions = [];
    this.state.preview = null;
    this.state.execution = null;
    this.bindCurrent();
    this.renderPreview(null);
    this.renderExecution(null);
    this.renderVersionHistory();
    this.banner.text("Novo mapeamento. Informe os dados e salve.");
  };

  ImportExportAdmin.prototype.bindCurrent = function() {
    const current = this.state.current || {};
    this.codeInput.value(current.code || "");
    this.nameInput.value(current.name || "");
    this.directionInput.value(current.direction || "export");
    this.targetTypeInput.value(current.targetType || "entity");
    this.targetCodeInput.value(current.targetCode || "");
    this.formatInput.value(current.format || "entity_copy");
    this.statusInput.value(current.status || "draft");
    this.mappingInput.value(this.stringifyJson(current.mapping || {}));
    this.parametersInput.value("");
    if (this.scheduleMappingCodeInput) {
      this.scheduleMappingCodeInput.value(current.code || "");
    }
    this.syncDesignerFromJson();
  };

  ImportExportAdmin.prototype.collectPayload = function() {
    return {
      code: String(this.codeInput.value() || "").trim(),
      name: String(this.nameInput.value() || "").trim(),
      direction: String(this.directionInput.value() || "export").trim(),
      targetType: String(this.targetTypeInput.value() || "entity").trim(),
      targetCode: String(this.targetCodeInput.value() || "").trim(),
      format: String(this.formatInput.value() || "entity_copy").trim(),
      status: String(this.statusInput.value() || "draft").trim(),
      mapping: this.parseJsonField(this.mappingInput.value(), "Mapping JSON")
    };
  };

  ImportExportAdmin.prototype.collectParameters = function() {
    const raw = String(this.parametersInput.value() || "").trim();
    if (!raw) {
      return {};
    }
    return this.parseJsonField(raw, "Parametros");
  };

  ImportExportAdmin.prototype.parseJsonField = function(raw, label) {
    const text = String(raw || "").trim();
    if (!text) {
      return {};
    }
    try {
      const parsed = JSON.parse(text);
      if (!parsed || typeof parsed !== "object") {
        throw new Error("JSON deve ser objeto.");
      }
      return parsed;
    } catch (error) {
      throw global.CrudUtils.makeError("IMPORT_EXPORT_JSON_INVALID", label + " invalido.", {
        field: label,
        raw: text,
        cause: error && error.message ? error.message : String(error)
      });
    }
  };

  ImportExportAdmin.prototype.handleSave = function() {
    const payload = this.collectPayload();
    this.banner.text("Salvando mapeamento...");
    return this.http.request({
      url: "/api/admin/import-export-mappings",
      method: "POST",
      data: payload
    }).then(function(response) {
      this.state.current = response && response.mapping ? response.mapping : payload;
      this.state.currentVersions = global.CrudUtils.ensureArray(response && response.versions);
      this.bindCurrent();
      this.renderVersionHistory();
      this.banner.text("Mapeamento salvo.");
      global.CrudUtils.showMessage("Mapeamento salvo.", "success");
      return this.loadMappings();
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel salvar o mapeamento."));
  };

  ImportExportAdmin.prototype.handlePreview = function() {
    const payload = this.collectPayload();
    const parameters = this.collectParameters();
    this.banner.text("Executando preview...");
    return this.http.request({
      url: "/api/admin/import-export-mappings/preview",
      method: "POST",
      data: {
        code: payload.code,
        name: payload.name,
        direction: payload.direction,
        targetType: payload.targetType,
        targetCode: payload.targetCode,
        format: payload.format,
        status: payload.status,
        mapping: payload.mapping,
        parameters: parameters
      }
    }).then(function(response) {
      this.state.preview = response;
      this.renderPreview(response);
      this.tabs.select(1);
      this.banner.text("Preview gerado.");
      global.CrudUtils.showMessage("Preview gerado.", "info");
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel gerar o preview."));
  };

  ImportExportAdmin.prototype.handleExecute = function() {
    const payload = this.collectPayload();
    const parameters = this.collectParameters();
    return global.CrudUtils.confirmAction("Deseja executar este mapeamento?", {
      title: "Executar integracao",
      confirmText: "Executar"
    }).then(function(confirmed) {
      if (!confirmed) {
        return false;
      }
      this.banner.text("Executando mapeamento...");
      return this.http.request({
        url: "/api/admin/import-export-mappings/execute",
        method: "POST",
        data: {
          code: payload.code,
          name: payload.name,
          direction: payload.direction,
          targetType: payload.targetType,
          targetCode: payload.targetCode,
          format: payload.format,
          status: payload.status,
          mapping: payload.mapping,
          parameters: parameters
        }
      }).then(function(response) {
        this.state.execution = response;
        this.pushExecutionHistory(payload, response);
        this.renderExecution(response);
        this.loadExecutions(payload.code);
        this.tabs.select(2);
        this.banner.text("Execucao concluida.");
        global.CrudUtils.showMessage("Execucao concluida.", "success");
      }.bind(this));
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel executar o mapeamento."));
  };

  ImportExportAdmin.prototype.pushExecutionHistory = function(payload, response) {
    this.state.executionHistory.unshift({
      at: new Date().toISOString(),
      code: payload.code,
      format: payload.format,
      counts: response && response.counts ? response.counts : {}
    });
    this.state.executionHistory = this.state.executionHistory.slice(0, 10);
  };

  ImportExportAdmin.prototype.renderPreview = function(response) {
    this.renderResult(response, this.previewSummary, this.previewDiagnostics, this.previewResult, true);
  };

  ImportExportAdmin.prototype.renderExecution = function(response) {
    this.renderResult(response, this.executionSummary, this.executionDiagnostics, this.executionResult, false);
    this.renderExecutionHistory();
  };

  ImportExportAdmin.prototype.renderResult = function(response, summaryHost, diagnosticsHost, resultHost, isPreview) {
    summaryHost.empty();
    diagnosticsHost.empty();
    resultHost.text("");
    if (!response) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Nenhum resultado ainda.").appendTo(summaryHost);
      if (isPreview) {
        this.renderPreviewStructure(null);
      }
      return;
    }
    const counts = response.counts || {};
    [
      ["Lidos", counts.read],
      ["Gravados", counts.written],
      ["Ignorados", counts.skipped],
      ["Erros", counts.errors]
    ].forEach(function(item) {
      const card = $("<div class=\"import-export-admin-summary-card\"></div>").appendTo(summaryHost);
      $("<span></span>").text(item[0]).appendTo(card);
      $("<strong></strong>").text(String(item[1] == null ? 0 : item[1])).appendTo(card);
    });
    global.CrudUtils.ensureArray(response.diagnostics).forEach(function(item) {
      const level = String(item && item.level || "info");
      $("<div class=\"import-export-admin-diagnostic\"></div>")
        .addClass("is-" + level)
        .text(String(item && item.message || "Diagnostico"))
        .appendTo(diagnosticsHost);
    });
    resultHost.text(this.stringifyJson(response.result || {}, true));
    if (isPreview) {
      this.renderPreviewStructure(response.result || null);
    }
  };

  ImportExportAdmin.prototype.renderExecutionHistory = function() {
    this.executionHistoryElement.empty();
    if (!this.state.executionHistory.length) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Nenhuma execucao nesta sessao.").appendTo(this.executionHistoryElement);
      return;
    }
    this.state.executionHistory.forEach(function(item) {
      const card = $("<article class=\"import-export-admin-history-card\"></article>").appendTo(this.executionHistoryElement);
      $("<strong></strong>").text(item.code || "(sem codigo)").appendTo(card);
      $("<span></span>").text([item.format || "-", item.at || "-"].join(" | ")).appendTo(card);
      $("<small></small>").text("Lidos: " + (item.counts.read || 0) + " | Gravados: " + (item.counts.written || 0) + " | Erros: " + (item.counts.errors || 0)).appendTo(card);
    }, this);
  };

  ImportExportAdmin.prototype.loadExecutions = function(mappingCode) {
    const filters = [];
    const selectedMapping = String(mappingCode || this.executionFilterMappingInput && this.executionFilterMappingInput.value() || "").trim();
    const selectedMode = String(this.executionFilterModeInput && this.executionFilterModeInput.value() || "").trim();
    const selectedStatus = String(this.executionFilterStatusInput && this.executionFilterStatusInput.value() || "").trim();
    if (selectedMapping) {
      filters.push("mappingCode=" + encodeURIComponent(selectedMapping));
    }
    if (selectedMode) {
      filters.push("mode=" + encodeURIComponent(selectedMode));
    }
    if (selectedStatus) {
      filters.push("status=" + encodeURIComponent(selectedStatus));
    }
    const query = filters.length ? ("?" + filters.join("&")) : "";
    return this.http.request({
      url: "/api/admin/import-export-mappings/executions" + query,
      method: "GET"
    }).then(function(response) {
      const items = global.CrudUtils.ensureArray(response && response.items);
      this.state.currentExecutions = items;
      this.state.selectedExecution = null;
      this.executionsGrid.dataSource.data(items);
      this.renderSelectedExecution(null);
      return items;
    }.bind(this)).catch(function() {
      return [];
    });
  };

  ImportExportAdmin.prototype.renderVersionHistory = function() {
    const items = global.CrudUtils.ensureArray(this.state.currentVersions);
    this.versionsGrid.dataSource.data(items);
    this.versionsSummary.empty();
    if (!items.length) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Nenhuma versao salva ainda.").appendTo(this.versionsSummary);
      return;
    }
    this.renderDefinitionList(this.versionsSummary, [
      { label: "Versoes", value: String(items.length) },
      { label: "Atual", value: String(items[0].versionNumber || "-") }
    ]);
  };

  ImportExportAdmin.prototype.loadSchedules = function() {
    return this.http.request({
      url: "/api/admin/import-export-mappings/schedules",
      method: "GET"
    }).then(function(response) {
      const items = global.CrudUtils.ensureArray(response && response.items);
      this.state.currentSchedules = items;
      this.schedulesGrid.dataSource.data(items);
      this.renderScheduleSummary(null);
      return items;
    }.bind(this)).catch(function() {
      return [];
    });
  };

  ImportExportAdmin.prototype.handleExecutionSelection = function() {
    const selected = this.executionsGrid.select();
    const item = selected && selected.length ? this.executionsGrid.dataItem(selected) : null;
    this.state.selectedExecution = item ? item.toJSON ? item.toJSON() : item : null;
    this.renderSelectedExecution(this.state.selectedExecution);
  };

  ImportExportAdmin.prototype.renderSelectedExecution = function(item) {
    this.executionDetailSummary.empty();
    this.executionDetailDiagnostics.empty();
    this.executionDetailResult.text("");
    if (!item) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Selecione uma execucao para ver o detalhe.").appendTo(this.executionDetailSummary);
      return;
    }
    this.renderDefinitionList(this.executionDetailSummary, [
      { label: "Mapping", value: item.mappingCode || "-" },
      { label: "Modo", value: item.mode || "-" },
      { label: "Status", value: item.status || "-" },
      { label: "Quando", value: item.createdAt || "-" },
      { label: "Arquivo", value: item.fileName || "-" }
    ]);
    global.CrudUtils.ensureArray(item.diagnostics).forEach(function(entry) {
      $("<div class=\"import-export-admin-diagnostic\"></div>")
        .addClass("is-" + String(entry && entry.level || "info"))
        .text(String(entry && entry.message || "Diagnostico"))
        .appendTo(this.executionDetailDiagnostics);
    }, this);
    this.executionDetailResult.text(this.stringifyJson({
      counts: item.counts || {},
      resultSummary: item.resultSummary || {},
      parameters: item.parameters || {}
    }, true));
  };

  ImportExportAdmin.prototype.handleExportSelectedExecution = function() {
    if (!this.state.selectedExecution) {
      global.CrudUtils.showMessage("Selecione uma execucao para exportar.", "warning");
      return;
    }
    const payload = this.stringifyJson(this.state.selectedExecution, true);
    const fileName = "import-export-execution-" + String(this.state.selectedExecution.id || "selecionada") + ".json";
    this.downloadTextFile(fileName, payload, "application/json;charset=utf-8");
    global.CrudUtils.showMessage("Execucao exportada.", "success");
  };

  ImportExportAdmin.prototype.handleScheduleSelection = function() {
    const selected = this.schedulesGrid.select();
    const item = selected && selected.length ? this.schedulesGrid.dataItem(selected) : null;
    this.renderScheduleSummary(item ? (item.toJSON ? item.toJSON() : item) : null);
  };

  ImportExportAdmin.prototype.renderScheduleSummary = function(item) {
    this.scheduleSummary.empty();
    if (!item) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Selecione um agendamento para ver o resumo.").appendTo(this.scheduleSummary);
      return;
    }
    this.renderDefinitionList(this.scheduleSummary, [
      { label: "Mapping", value: item.mappingCode || "-" },
      { label: "Frequencia", value: item.frequency || "-" },
      { label: "Status", value: item.lastStatus || "-" },
      { label: "Proxima execucao", value: item.nextRunAt || "-" },
      { label: "Ultima execucao", value: item.lastRunAt || "-" }
    ]);
  };

  ImportExportAdmin.prototype.handleSaveSchedule = function() {
    let parameters = {};
    try {
      parameters = this.collectScheduleParameters();
    } catch (error) {
      this.handleError("Parametros do agendamento invalidos.", error);
      return;
    }
    return this.http.request({
      url: "/api/admin/import-export-mappings/schedules",
      method: "POST",
      data: {
        code: String(this.scheduleCodeInput.value() || "").trim(),
        name: String(this.scheduleNameInput.value() || "").trim(),
        mappingCode: String(this.scheduleMappingCodeInput.value() || this.codeInput.value() || "").trim(),
        frequency: String(this.scheduleFrequencyInput.value() || "daily").trim(),
        intervalMinutes: this.parseInteger(this.scheduleIntervalInput.value()),
        dailyHour: this.parseInteger(this.scheduleDailyHourInput.value()),
        dailyMinute: this.parseInteger(this.scheduleDailyMinuteInput.value()),
        parameters: parameters
      }
    }).then(function() {
      this.banner.text("Agendamento salvo.");
      global.CrudUtils.showMessage("Agendamento salvo.", "success");
      return this.loadSchedules();
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel salvar o agendamento."));
  };

  ImportExportAdmin.prototype.handleRunDueSchedules = function() {
    return this.http.request({
      url: "/api/admin/import-export-mappings/schedules/run-due",
      method: "POST",
      data: {}
    }).then(function(response) {
      this.banner.text("Agendamentos vencidos processados.");
      global.CrudUtils.showMessage("Agendamentos vencidos processados.", "success");
      this.loadSchedules();
      this.loadExecutions();
      if (response && Array.isArray(response.executed) && response.executed.length) {
        this.tabs.select(3);
      }
      return response;
    }.bind(this)).catch(this.handleError.bind(this, "Nao foi possivel executar os agendamentos vencidos."));
  };

  ImportExportAdmin.prototype.collectScheduleParameters = function() {
    const raw = String(this.scheduleParametersInput.value() || "").trim();
    if (!raw) {
      return {};
    }
    return this.parseJsonField(raw, "Parametros do agendamento");
  };

  ImportExportAdmin.prototype.parseInteger = function(value) {
    const text = String(value == null ? "" : value).trim();
    if (!text) {
      return null;
    }
    const parsed = parseInt(text, 10);
    return isNaN(parsed) ? null : parsed;
  };

  ImportExportAdmin.prototype.renderPreviewStructure = function(result) {
    this.previewStructureMeta.empty();
    this.previewStructureTree.empty();
    if (!result || result.type !== "file") {
      $("<p class=\"import-export-admin-empty\"></p>").text("Sem estrutura de arquivo para exibir.").appendTo(this.previewStructureMeta);
      return;
    }
    const fileName = String(result.fileName || "");
    const mimeType = String(result.mimeType || "");
    const summary = [
      { label: "Arquivo", value: fileName || "-" },
      { label: "Mime", value: mimeType || "-" }
    ];
    this.renderDefinitionList(this.previewStructureMeta, summary);
    const treeData = this.buildResultStructureTree(result);
    if (!treeData.length) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Estrutura nao disponivel para este formato.").appendTo(this.previewStructureTree);
      return;
    }
    this.previewStructureTree.kendoTreeView({
      dataSource: treeData,
      dataTextField: "text"
    });
  };

  ImportExportAdmin.prototype.buildResultStructureTree = function(result) {
    const mimeType = String(result.mimeType || "").toLowerCase();
    const previewText = String(result.previewText || "");
    if (mimeType.indexOf("xml") >= 0) {
      return this.buildXmlPreviewTree(previewText);
    }
    if (mimeType.indexOf("csv") >= 0 || mimeType.indexOf("text/plain") >= 0) {
      return this.buildDelimitedPreviewTree(previewText);
    }
    return [];
  };

  ImportExportAdmin.prototype.buildXmlPreviewTree = function(xmlText) {
    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(xmlText, "application/xml");
      if (!doc || !doc.documentElement) {
        return [];
      }
      const hasError = doc.getElementsByTagName("parsererror").length > 0;
      if (hasError) {
        return [];
      }
      return [this.xmlElementToTreeNode(doc.documentElement)];
    } catch (_) {
      return [];
    }
  };

  ImportExportAdmin.prototype.xmlElementToTreeNode = function(element) {
    const attrs = [];
    if (element && element.attributes) {
      for (let index = 0; index < element.attributes.length; index++) {
        const attr = element.attributes[index];
        attrs.push(attr.name + "=" + attr.value);
      }
    }
    const text = attrs.length ? element.nodeName + " [" + attrs.join(", ") + "]" : element.nodeName;
    const items = [];
    const children = element && element.childNodes ? element.childNodes : [];
    for (let index = 0; index < children.length; index++) {
      const child = children[index];
      if (child.nodeType === 1) {
        items.push(this.xmlElementToTreeNode(child));
      } else if (child.nodeType === 3) {
        const value = String(child.nodeValue || "").trim();
        if (value) {
          items.push({ text: value });
        }
      }
    }
    return { text: text, items: items };
  };

  ImportExportAdmin.prototype.buildDelimitedPreviewTree = function(text) {
    const lines = String(text || "").split(/\r?\n/).filter(Boolean);
    return lines.slice(0, 40).map(function(line, index) {
      return {
        text: "Linha " + (index + 1),
        items: [
          { text: line }
        ]
      };
    });
  };

  ImportExportAdmin.prototype.syncDesignerFromJson = function() {
    try {
      const payload = this.collectPayload();
      const mapping = payload.mapping || {};
      const type = this.resolveDesignerType(payload.format, mapping);
      this.designerState.type = type;
      this.designerState.selectedPath = null;
      this.renderDesignerMeta(type, mapping);
      this.renderDesignerTree(type, mapping);
    } catch (error) {
      this.designerState.type = null;
      this.designerState.selectedPath = null;
      this.renderDesignerMeta(null, null, error);
      this.renderDesignerTree(null, null);
    }
  };

  ImportExportAdmin.prototype.resolveDesignerType = function(format, mapping) {
    const safeMapping = mapping || {};
    if (String(format) === "txt_layout" || (safeMapping.destination && safeMapping.destination.fileFormat === "txt_layout")) {
      return "txt";
    }
    if (String(format) === "xml" || (safeMapping.destination && safeMapping.destination.fileFormat === "xml")) {
      return "xml";
    }
    return null;
  };

  ImportExportAdmin.prototype.renderDesignerMeta = function(type, mapping, error) {
    this.designerMeta.empty();
    if (error) {
      $("<div class=\"import-export-admin-diagnostic is-error\"></div>").text(error.message || String(error)).appendTo(this.designerMeta);
      return;
    }
    if (!type) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Editor visual disponivel apenas para TXT layout e XML.").appendTo(this.designerMeta);
      return;
    }
    const destination = mapping && mapping.destination ? mapping.destination : {};
    const summary = [
      { label: "Modo", value: type === "txt" ? "TXT layout" : "XML" },
      { label: "Arquivo", value: destination.fileNamePattern || "-" },
      { label: "Encoding", value: destination.encodingLabel || "UTF-8" }
    ];
    if (type === "txt") {
      summary.push({ label: "Layout", value: destination.layoutMode || "flat" });
    } else {
      summary.push({ label: "Raiz", value: destination.rootName || "items" });
    }
    this.renderDefinitionList(this.designerMeta, summary);
  };

  ImportExportAdmin.prototype.renderDesignerTree = function(type, mapping) {
    this.treeElement.empty();
    this.nodeSummary.empty();
    this.nodeJsonInput.value("{}");
    if (!type) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Sem estrutura visual para o formato atual.").appendTo(this.treeElement);
      return;
    }
    const nodes = this.getDesignerNodes(type, mapping);
    if (!nodes.length) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Nenhum no configurado. Use \"Adicionar raiz\" ou edite o JSON.").appendTo(this.treeElement);
      return;
    }
    const data = this.buildDesignerTreeData(nodes, type, []);
    this.treeElement.kendoTreeView({
      dataSource: data,
      dataTextField: "text",
      select: this.handleDesignerSelect.bind(this)
    });
  };

  ImportExportAdmin.prototype.getDesignerNodes = function(type, mapping) {
    const destination = mapping && mapping.destination ? mapping.destination : {};
    if (type === "txt") {
      return global.CrudUtils.ensureArray(destination.recordLayouts);
    }
    if (type === "xml") {
      return global.CrudUtils.ensureArray(destination.xmlLayouts);
    }
    return [];
  };

  ImportExportAdmin.prototype.buildDesignerTreeData = function(nodes, type, basePath) {
    return global.CrudUtils.ensureArray(nodes).map(function(node, index) {
      const path = basePath.concat(index);
      return {
        text: this.describeDesignerNode(node, type),
        items: this.buildDesignerTreeData(global.CrudUtils.ensureArray(node && node.children), type, path),
        path: path.join(".")
      };
    }.bind(this));
  };

  ImportExportAdmin.prototype.describeDesignerNode = function(node, type) {
    if (!node) {
      return "(vazio)";
    }
    if (type === "txt") {
      return [
        node.recordType || node.label || "registro",
        node.sourceAlias ? "[" + node.sourceAlias + "]" : "",
        node.nodeType && node.nodeType !== "record" ? "(" + node.nodeType + ")" : ""
      ].join(" ").trim();
    }
    return [
      node.name || "node",
      node.sourceAlias ? "[" + node.sourceAlias + "]" : "",
      node.label ? "- " + node.label : ""
    ].join(" ").trim();
  };

  ImportExportAdmin.prototype.handleDesignerSelect = function(event) {
    const item = event && event.node ? $(event.node) : $();
    const treeView = this.treeElement.data("kendoTreeView");
    const dataItem = treeView && item.length ? treeView.dataItem(item) : null;
    if (!dataItem) {
      return;
    }
    const path = String(dataItem.path || "");
    this.designerState.selectedPath = path;
    this.renderSelectedDesignerNode();
  };

  ImportExportAdmin.prototype.renderSelectedDesignerNode = function() {
    this.nodeSummary.empty();
    const selected = this.getSelectedDesignerNode();
    if (!selected) {
      $("<p class=\"import-export-admin-empty\"></p>").text("Selecione um no na arvore.").appendTo(this.nodeSummary);
      this.nodeJsonInput.value("{}");
      return;
    }
    const summary = [];
    Object.keys(selected.node).forEach(function(key) {
      if (key === "children") {
        summary.push({ label: "children", value: String(global.CrudUtils.ensureArray(selected.node.children).length) });
        return;
      }
      const value = selected.node[key];
      summary.push({ label: key, value: typeof value === "object" ? JSON.stringify(value) : String(value) });
    });
    this.renderDefinitionList(this.nodeSummary, summary.slice(0, 8));
    this.nodeJsonInput.value(this.stringifyJson(selected.node));
  };

  ImportExportAdmin.prototype.getSelectedDesignerNode = function() {
    const path = this.designerState.selectedPath;
    if (path == null) {
      return null;
    }
    const indexes = String(path).split(".").filter(Boolean).map(function(value) {
      return parseInt(value, 10);
    }).filter(function(value) {
      return !isNaN(value);
    });
    const mapping = this.collectPayload().mapping || {};
    const nodes = this.getDesignerNodes(this.designerState.type, mapping);
    let current = null;
    let cursor = nodes;
    indexes.forEach(function(index) {
      current = cursor[index] || null;
      cursor = current && Array.isArray(current.children) ? current.children : [];
    });
    if (!current) {
      return null;
    }
    return {
      path: indexes,
      node: global.CrudUtils.clone(current)
    };
  };

  ImportExportAdmin.prototype.handleAddRootNode = function() {
    const mapping = this.collectPayload().mapping || {};
    const type = this.resolveDesignerType(this.formatInput.value(), mapping);
    if (!type) {
      global.CrudUtils.showMessage("Selecione TXT layout ou XML para usar o editor visual.", "warning");
      return;
    }
    const nodes = this.getDesignerNodes(type, mapping).slice();
    nodes.push(this.createDefaultDesignerNode(type));
    this.setDesignerNodes(type, mapping, nodes);
    this.mappingInput.value(this.stringifyJson(mapping));
    this.syncDesignerFromJson();
    this.designerState.selectedPath = String(nodes.length - 1);
    this.renderSelectedDesignerNode();
  };

  ImportExportAdmin.prototype.handleAddChildNode = function() {
    this.insertRelativeDesignerNode(true);
  };

  ImportExportAdmin.prototype.handleAddSiblingNode = function() {
    this.insertRelativeDesignerNode(false);
  };

  ImportExportAdmin.prototype.insertRelativeDesignerNode = function(asChild) {
    const selected = this.getSelectedDesignerNode();
    if (!selected) {
      global.CrudUtils.showMessage("Selecione um no da arvore primeiro.", "warning");
      return;
    }
    const mapping = this.collectPayload().mapping || {};
    const type = this.designerState.type;
    if (!type) {
      return;
    }
    const nodes = this.getDesignerNodes(type, mapping).slice();
    if (asChild) {
      const target = this.getNodeReference(nodes, selected.path);
      target.children = global.CrudUtils.ensureArray(target.children);
      target.children.push(this.createDefaultDesignerNode(type));
      this.designerState.selectedPath = selected.path.concat(target.children.length - 1).join(".");
    } else {
      const parentPath = selected.path.slice(0, -1);
      const branch = parentPath.length ? global.CrudUtils.ensureArray(this.getNodeReference(nodes, parentPath).children) : nodes;
      const insertAt = selected.path[selected.path.length - 1] + 1;
      branch.splice(insertAt, 0, this.createDefaultDesignerNode(type));
      this.designerState.selectedPath = parentPath.concat(insertAt).join(".");
    }
    this.setDesignerNodes(type, mapping, nodes);
    this.mappingInput.value(this.stringifyJson(mapping));
    this.syncDesignerFromJson();
    this.renderSelectedDesignerNode();
  };

  ImportExportAdmin.prototype.handleRemoveNode = function() {
    const selected = this.getSelectedDesignerNode();
    if (!selected) {
      global.CrudUtils.showMessage("Selecione um no da arvore primeiro.", "warning");
      return;
    }
    const mapping = this.collectPayload().mapping || {};
    const type = this.designerState.type;
    const nodes = this.getDesignerNodes(type, mapping).slice();
    const parentPath = selected.path.slice(0, -1);
    const branch = parentPath.length ? global.CrudUtils.ensureArray(this.getNodeReference(nodes, parentPath).children) : nodes;
    branch.splice(selected.path[selected.path.length - 1], 1);
    this.setDesignerNodes(type, mapping, nodes);
    this.mappingInput.value(this.stringifyJson(mapping));
    this.designerState.selectedPath = null;
    this.syncDesignerFromJson();
  };

  ImportExportAdmin.prototype.applyNodeEditor = function() {
    const selected = this.getSelectedDesignerNode();
    if (!selected) {
      global.CrudUtils.showMessage("Selecione um no da arvore primeiro.", "warning");
      return;
    }
    const mapping = this.collectPayload().mapping || {};
    const type = this.designerState.type;
    const nodes = this.getDesignerNodes(type, mapping).slice();
    let parsed;
    try {
      parsed = this.parseJsonField(this.nodeJsonInput.value(), "JSON do no");
    } catch (error) {
      this.handleError("Nao foi possivel aplicar o JSON do no.", error);
      return;
    }
    this.replaceNodeReference(nodes, selected.path, parsed);
    this.setDesignerNodes(type, mapping, nodes);
    this.mappingInput.value(this.stringifyJson(mapping));
    this.syncDesignerFromJson();
    this.renderSelectedDesignerNode();
    global.CrudUtils.showMessage("No atualizado.", "success");
  };

  ImportExportAdmin.prototype.createDefaultDesignerNode = function(type) {
    if (type === "txt") {
      return {
        nodeType: "record",
        recordType: "REG",
        label: "Novo registro",
        sourceAlias: "cliente",
        lineMode: "fixed",
        widthMode: "characters",
        fields: [
          { constant: "01", length: 2 },
          { sourcePath: "id", length: 5, align: "right", padChar: "0" }
        ],
        children: []
      };
    }
    return {
      name: "item",
      label: "Novo no",
      sourceAlias: "cliente",
      attributes: [
        { name: "id", sourcePath: "id" }
      ],
      fields: [
        { name: "nome", sourcePath: "nome" }
      ],
      children: []
    };
  };

  ImportExportAdmin.prototype.setDesignerNodes = function(type, mapping, nodes) {
    mapping.destination = mapping.destination || { type: "file" };
    if (type === "txt") {
      mapping.destination.fileFormat = "txt_layout";
      mapping.destination.recordLayouts = nodes;
      return;
    }
    mapping.destination.fileFormat = "xml";
    mapping.destination.xmlLayouts = nodes;
  };

  ImportExportAdmin.prototype.getNodeReference = function(nodes, path) {
    let current = null;
    let cursor = nodes;
    path.forEach(function(index) {
      current = cursor[index];
      cursor = current.children = global.CrudUtils.ensureArray(current.children);
    });
    return current;
  };

  ImportExportAdmin.prototype.replaceNodeReference = function(nodes, path, node) {
    if (!path.length) {
      return;
    }
    if (path.length === 1) {
      nodes[path[0]] = node;
      return;
    }
    const parent = this.getNodeReference(nodes, path.slice(0, -1));
    parent.children = global.CrudUtils.ensureArray(parent.children);
    parent.children[path[path.length - 1]] = node;
  };

  ImportExportAdmin.prototype.renderDefinitionList = function(host, items) {
    const list = $("<dl class=\"import-export-admin-definition-list\"></dl>").appendTo(host.empty());
    global.CrudUtils.ensureArray(items).forEach(function(item) {
      const value = item && item.value != null ? String(item.value) : "-";
      $("<dt></dt>").text(String(item && item.label || "")).appendTo(list);
      $("<dd></dd>").text(value).appendTo(list);
    });
  };

  ImportExportAdmin.prototype.handleError = function(fallback, error) {
    const normalized = global.CrudUtils.unwrapError(error, fallback);
    this.banner.text(normalized.message);
    global.CrudUtils.showMessage(normalized.message, "error");
    throw error;
  };

  ImportExportAdmin.prototype.downloadTextFile = function(fileName, content, mimeType) {
    const blob = new Blob([String(content || "")], { type: mimeType || "text/plain;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.setTimeout(function() {
      URL.revokeObjectURL(url);
    }, 0);
  };

  ImportExportAdmin.prototype.stringifyJson = function(value, pretty) {
    try {
      return JSON.stringify(value == null ? {} : value, null, pretty === false ? 0 : 2);
    } catch (_) {
      return "{}";
    }
  };

  global.ImportExportAdmin = ImportExportAdmin;
})(window, window.jQuery);
