(function(global, $) {
  "use strict";

  function MasterDetailEngine(options) {
    const settings = options || {};
    this.root = $(settings.root || "body");
    this.screenId = String(settings.screenId || "");
    this.config = settings.config || {};
    this.securityPolicy = global.CrudUtils && typeof global.CrudUtils.normalizeSecurityPolicy === "function"
      ? global.CrudUtils.normalizeSecurityPolicy(this.config, settings)
      : {};
    this.httpClient = settings.httpClient || (global.CrudHttpClient ? new global.CrudHttpClient() : null);
    this.productionErrors = settings.productionErrors === true;
    this.hideHeader = settings.hideHeader === true;
    this.definition = clone(settings.definition || {});
    this.masterRecords = [];
    this.detailRecords = {};
    this.detailGrids = {};
    this.detailButtons = {};
    this.detailSummaries = {};
    this.selectedMasterId = null;
    this.masterGrid = null;
    this.tabStrip = null;
    this.masterContextElement = null;
  }

  MasterDetailEngine.prototype.init = function() {
    if (!$ || !global.kendo || !$.fn.kendoGrid) {
      this.renderFatal("Kendo UI nao esta carregado.");
      return Promise.resolve(this);
    }

    this.normalizeDefinition();
    return this.loadData()
      .then(() => {
        this.render();
        return this;
      })
      .catch((error) => {
        this.renderFatal(this.errorMessage(error, "Falha ao carregar mestre-detalhe."));
        return this;
      });
  };

  MasterDetailEngine.prototype.destroy = function() {
    if (global.kendo) {
      global.kendo.destroy(this.root);
    }
    this.root.empty();
    this.masterGrid = null;
    this.tabStrip = null;
    this.detailGrids = {};
    this.detailButtons = {};
    this.detailSummaries = {};
  };

  MasterDetailEngine.prototype.normalizeDefinition = function() {
    const definition = this.definition;
    definition.program = definition.program || {};
    definition.master = definition.master || {};
    definition.master.idField = definition.master.idField || "id";
    definition.master.fields = ensureArray(definition.master.fields);
    definition.master.grid = definition.master.grid || {};
    definition.master.api = this.resolveSectionApi(definition.master, "master");
    definition.details = ensureArray(definition.details).filter(function(detail) {
      return detail && typeof detail === "object";
    });

    this.masterRecords = clone(ensureArray(definition.master.records));
    definition.details.forEach((detail) => {
      detail.id = String(detail.id || detail.entity || "detail");
      detail.idField = detail.idField || "id";
      detail.parentField = detail.parentField || (definition.master.idField + "_pai");
      detail.fields = ensureArray(detail.fields);
      detail.grid = detail.grid || {};
      detail.totals = ensureArray(detail.totals);
      detail.api = this.resolveSectionApi(detail, "detail." + detail.id);
      this.detailRecords[detail.id] = clone(ensureArray(detail.records));
    });

    if (this.masterRecords.length) {
      this.selectedMasterId = this.masterRecords[0][definition.master.idField];
    }
  };

  MasterDetailEngine.prototype.render = function() {
    this.destroy();
    this.root.addClass("master-detail-root").removeClass("crud-app-shell");

    const screen = $("<section class=\"master-detail-screen\"></section>").appendTo(this.root);
    if (!this.hideHeader) {
      this.renderHeader(screen);
    }

    const layout = $("<div class=\"master-detail-layout\"></div>").appendTo(screen);
    const masterPanel = $("<section class=\"master-detail-panel master-detail-parent-panel\"></section>").appendTo(layout);
    const detailPanel = $("<section class=\"master-detail-panel master-detail-child-panel\"></section>").appendTo(layout);

    this.renderMasterPanel(masterPanel);
    this.renderDetailPanel(detailPanel);
    this.setSelectedMaster(this.selectedMasterId);
  };

  MasterDetailEngine.prototype.resolveSectionApi = function(section, prefix) {
    const api = Object.assign({}, section.api || {});
    const dataSourceApi = this.definition && this.definition.dataSource && this.definition.dataSource.api || {};
    ["read", "get", "create", "update", "delete"].forEach((operation) => {
      const endpointId = prefix + "." + operation;
      const source = api[operation] || dataSourceApi[endpointId] || null;
      if (!source) {
        return;
      }
      api[operation] = this.resolveEndpoint(source, endpointId);
    });
    return api;
  };

  MasterDetailEngine.prototype.resolveEndpoint = function(endpoint, fallbackEndpointId) {
    const screenId = this.screenId || this.definition.screenId || this.definition.program && this.definition.program.screenId || "screen";
    if (global.CrudUtils && typeof global.CrudUtils.resolveEndpointForPolicy === "function") {
      return global.CrudUtils.resolveEndpointForPolicy(endpoint, fallbackEndpointId, screenId, this.securityPolicy);
    }
    if (typeof endpoint === "string") {
      return { endpointId: endpoint, method: "POST" };
    }
    return Object.assign({}, endpoint || {});
  };

  MasterDetailEngine.prototype.loadData = function() {
    return this.loadMasterRecords().then(() => this.loadDetailRecordsForSelected());
  };

  MasterDetailEngine.prototype.loadMasterRecords = function() {
    const master = this.definition.master;
    if (!this.hasEndpoint(master, "read")) {
      return Promise.resolve(this.masterRecords);
    }
    return this.requestSection(master, "read", {
      skip: 0,
      take: 500,
      pageSize: 500,
      sort: ensureArray(master.query && master.query.sort)
    }).then((response) => {
      this.masterRecords = this.extractResponseRows(response);
      if (this.masterRecords.length) {
        this.selectedMasterId = this.masterRecords[0][master.idField];
      } else {
        this.selectedMasterId = null;
      }
      return this.masterRecords;
    });
  };

  MasterDetailEngine.prototype.loadDetailRecordsForSelected = function() {
    const tasks = this.definition.details.map((detail) => this.loadDetailRecords(detail));
    return Promise.all(tasks);
  };

  MasterDetailEngine.prototype.loadDetailRecords = function(detail) {
    if (!this.hasEndpoint(detail, "read")) {
      return Promise.resolve(this.detailRecords[detail.id] || []);
    }
    if (this.selectedMasterId == null) {
      this.detailRecords[detail.id] = [];
      return Promise.resolve(this.detailRecords[detail.id]);
    }
    return this.requestSection(detail, "read", {
      skip: 0,
      take: 500,
      pageSize: 500,
      filters: [
        {
          id: detail.parentField,
          field: detail.parentField,
          operator: "eq",
          value: this.selectedMasterId
        }
      ],
      sort: ensureArray(detail.query && detail.query.sort)
    }).then((response) => {
      this.detailRecords[detail.id] = this.extractResponseRows(response);
      return this.detailRecords[detail.id];
    });
  };

  MasterDetailEngine.prototype.renderHeader = function(screen) {
    const program = this.definition.program || {};
    const header = $("<header class=\"master-detail-header\"></header>").appendTo(screen);
    const titleGroup = $("<div></div>").appendTo(header);
    $("<p class=\"master-detail-kicker\"></p>").text(program.module || "Mestre-detalhe").appendTo(titleGroup);
    $("<h2></h2>").text(program.title || "Tela pai e filhos").appendTo(titleGroup);
    if (program.subtitle) {
      $("<p></p>").text(program.subtitle).appendTo(titleGroup);
    }
    const badges = $("<div class=\"master-detail-badges\"></div>").appendTo(header);
    $("<span></span>").text("Kendo Grid").appendTo(badges);
    $("<span></span>").text(String(this.definition.details.length) + " filho(s)").appendTo(badges);
  };

  MasterDetailEngine.prototype.renderMasterPanel = function(panel) {
    const master = this.definition.master;
    const heading = $("<div class=\"master-detail-panel-heading\"></div>").appendTo(panel);
    $("<div><h3></h3><p></p></div>").appendTo(heading)
      .find("h3").text(master.title || "Pai").end()
      .find("p").text(master.subtitle || "Registros principais");

    const actions = $("<div class=\"master-detail-actions\"></div>").appendTo(heading);
    this.createButton(actions, "Incluir", "plus", "primary", () => this.openMasterWindow("create"));
    this.createButton(actions, "Alterar", "pencil", null, () => this.openMasterWindow("edit", this.getSelectedMaster()));
    this.createButton(actions, "Excluir", "trash", null, () => this.deleteSelectedMaster());

    this.masterContextElement = $("<div class=\"master-detail-context\"></div>").appendTo(panel);
    const gridElement = $("<div class=\"master-detail-grid master-detail-master-grid\"></div>").appendTo(panel);
    const columns = this.buildGridColumns(master, {
      edit: (record) => this.openMasterWindow("edit", record),
      remove: (record) => this.deleteMaster(record)
    });

    gridElement.kendoGrid({
      dataSource: this.createDataSource(this.masterRecords, master),
      selectable: "row",
      sortable: true,
      pageable: false,
      resizable: true,
      columns: columns,
      change: () => {
        const selected = this.masterGrid.dataItem(this.masterGrid.select());
        if (selected) {
          this.setSelectedMaster(selected[master.idField]);
        }
      },
      dataBound: () => this.bindMasterGridRows()
    });
    this.masterGrid = gridElement.data("kendoGrid");
  };

  MasterDetailEngine.prototype.renderDetailPanel = function(panel) {
    const heading = $("<div class=\"master-detail-panel-heading\"></div>").appendTo(panel);
    $("<div><h3>Filhos</h3><p>Grades filtradas pelo registro pai selecionado.</p></div>").appendTo(heading);

    const details = this.definition.details;
    if (!details.length) {
      $("<div class=\"master-detail-empty\"></div>").text("Nenhum filho configurado.").appendTo(panel);
      return;
    }

    const tabs = $("<div class=\"master-detail-tabs\"><ul></ul></div>").appendTo(panel);
    details.forEach((detail) => {
      tabs.children("ul").append($("<li></li>").text(detail.title || detail.id));
      const content = $("<div></div>").appendTo(tabs);
      this.renderSingleDetail(content, detail);
    });

    tabs.kendoTabStrip({ animation: false });
    this.tabStrip = tabs.data("kendoTabStrip");
    if (this.tabStrip) {
      this.tabStrip.select(0);
    }
  };

  MasterDetailEngine.prototype.renderSingleDetail = function(container, detail) {
    const toolbar = $("<div class=\"master-detail-detail-toolbar\"></div>").appendTo(container);
    this.detailButtons[detail.id] = {
      create: this.createButton(toolbar, "Incluir", "plus", "primary", () => this.openDetailWindow(detail, "create")),
      edit: this.createButton(toolbar, "Alterar", "pencil", null, () => {
        const record = this.getSelectedDetailRecord(detail);
        if (record) {
          this.openDetailWindow(detail, "edit", record);
        }
      }),
      remove: this.createButton(toolbar, "Excluir", "trash", null, () => this.deleteSelectedDetail(detail))
    };

    this.detailSummaries[detail.id] = $("<div class=\"master-detail-summary\"></div>").appendTo(container);

    const gridElement = $("<div class=\"master-detail-grid master-detail-detail-grid\"></div>").appendTo(container);
    gridElement.kendoGrid({
      dataSource: this.createDataSource(this.getFilteredDetailRecords(detail), detail),
      selectable: "row",
      sortable: true,
      pageable: false,
      resizable: true,
      columns: this.buildGridColumns(detail, {
        edit: (record) => this.openDetailWindow(detail, "edit", record),
        remove: (record) => this.deleteDetail(detail, record)
      }),
      dataBound: () => this.bindDetailGridRows(detail)
    });
    this.detailGrids[detail.id] = gridElement.data("kendoGrid");
  };

  MasterDetailEngine.prototype.createButton = function(container, text, icon, themeColor, click) {
    const button = $("<button type=\"button\"></button>").text(text).appendTo(container);
    button.kendoButton({
      icon: icon,
      themeColor: themeColor || undefined,
      click: click
    });
    return button.data("kendoButton");
  };

  MasterDetailEngine.prototype.buildGridColumns = function(section, handlers) {
    const columns = [{
      title: "Acoes",
      width: 170,
      command: [
        {
          name: "masterDetailEdit",
          text: "Editar",
          click: (event) => {
            event.preventDefault();
            const record = this.getCommandRecord(event);
            if (record) {
              handlers.edit(record);
            }
          }
        },
        {
          name: "masterDetailDelete",
          text: "Excluir",
          click: (event) => {
            event.preventDefault();
            const record = this.getCommandRecord(event);
            if (record) {
              handlers.remove(record);
            }
          }
        }
      ]
    }];

    const fieldsById = this.fieldsById(section.fields);
    const configured = ensureArray(section.grid.columns);
    const sourceColumns = configured.length
      ? configured
      : section.fields.filter(function(field) { return field.hidden !== true; }).map(function(field) {
        return { field: field.id || field.code, title: field.label };
      });

    sourceColumns.forEach((column) => {
      const fieldId = String(column.field || "");
      if (!fieldId) {
        return;
      }
      const field = fieldsById[fieldId] || {};
      const next = {
        field: fieldId,
        title: column.title || field.label || fieldId,
        width: column.width || field.width || undefined,
        format: column.format || this.kendoFormat(field),
        attributes: this.alignAttributes(column.align || field.align),
        headerAttributes: this.alignAttributes(column.align || field.align)
      };
      columns.push(next);
    });

    return columns;
  };

  MasterDetailEngine.prototype.getCommandRecord = function(event) {
    const gridElement = $(event.currentTarget).closest(".k-grid");
    const grid = gridElement.data("kendoGrid");
    const row = $(event.currentTarget).closest("tr");
    return grid ? grid.dataItem(row) : null;
  };

  MasterDetailEngine.prototype.createDataSource = function(data, section) {
    return new global.kendo.data.DataSource({
      data: this.normalizeDataRecords(data, section),
      schema: {
        model: {
          id: section.idField || "id",
          fields: this.kendoModelFields(section.fields)
        }
      }
    });
  };

  MasterDetailEngine.prototype.normalizeDataRecords = function(data, section) {
    const records = clone(data);
    const dateFields = ensureArray(section.fields).filter(function(field) {
      return String(field.type || field.dataType || "").toLowerCase() === "date";
    }).map(function(field) {
      return field.id || field.code;
    }).filter(Boolean);

    if (!dateFields.length) {
      return records;
    }

    return records.map(function(record) {
      dateFields.forEach(function(fieldId) {
        if (record[fieldId]) {
          record[fieldId] = parseDateValue(record[fieldId]);
        }
      });
      return record;
    });
  };

  MasterDetailEngine.prototype.kendoModelFields = function(fields) {
    return ensureArray(fields).reduce(function(acc, field) {
      const id = field.id || field.code;
      if (!id) {
        return acc;
      }
      acc[id] = { type: mapKendoType(field.type || field.dataType) };
      return acc;
    }, {});
  };

  MasterDetailEngine.prototype.bindMasterGridRows = function() {
    if (!this.masterGrid) {
      return;
    }
    const idField = this.definition.master.idField;
    const rows = this.masterGrid.tbody.children("tr");
    rows.off("dblclick.masterDetail").on("dblclick.masterDetail", (event) => {
      const record = this.masterGrid.dataItem(event.currentTarget);
      if (record) {
        this.openMasterWindow("edit", record);
      }
    });
    rows.each((_, row) => {
      const record = this.masterGrid.dataItem(row);
      if (record && String(record[idField]) === String(this.selectedMasterId)) {
        this.masterGrid.select(row);
      }
    });
  };

  MasterDetailEngine.prototype.bindDetailGridRows = function(detail) {
    const grid = this.detailGrids[detail.id];
    if (!grid) {
      return;
    }
    grid.tbody.children("tr").off("dblclick.masterDetail").on("dblclick.masterDetail", (event) => {
      const record = grid.dataItem(event.currentTarget);
      if (record) {
        this.openDetailWindow(detail, "edit", record);
      }
    });
  };

  MasterDetailEngine.prototype.setSelectedMaster = function(id) {
    if (id == null && this.masterRecords.length) {
      id = this.masterRecords[0][this.definition.master.idField];
    }
    this.selectedMasterId = id;
    this.refreshMasterContext();
    if (this.hasRemoteDetails()) {
      return this.loadDetailRecordsForSelected()
        .then(() => {
          this.refreshDetailGrids();
          this.refreshMasterContext();
          return this;
        })
        .catch((error) => {
          showMessage(this.errorMessage(error, "Falha ao carregar filhos."), "error");
          return this;
        });
    }
    this.refreshDetailGrids();
    return Promise.resolve(this);
  };

  MasterDetailEngine.prototype.refreshMasterGrid = function() {
    if (!this.masterGrid) {
      return;
    }
    this.masterGrid.setDataSource(this.createDataSource(this.masterRecords, this.definition.master));
    this.masterGrid.refresh();
    this.bindMasterGridRows();
  };

  MasterDetailEngine.prototype.refreshDetailGrids = function() {
    this.definition.details.forEach((detail) => {
      const grid = this.detailGrids[detail.id];
      if (grid) {
        grid.setDataSource(this.createDataSource(this.getFilteredDetailRecords(detail), detail));
        grid.refresh();
      }
      this.refreshDetailSummary(detail);
      this.setDetailButtonsEnabled(detail, this.selectedMasterId != null);
    });
  };

  MasterDetailEngine.prototype.refreshMasterContext = function() {
    if (!this.masterContextElement) {
      return;
    }
    const record = this.getSelectedMaster();
    this.masterContextElement.empty();
    if (!record) {
      $("<span></span>").text("Nenhum pai selecionado.").appendTo(this.masterContextElement);
      return;
    }

    const master = this.definition.master;
    const titleField = master.displayField || master.idField;
    $("<span></span>").text("Selecionado: " + formatValue(record[titleField], this.getField(master, titleField))).appendTo(this.masterContextElement);
    this.definition.details.forEach((detail) => {
      const rows = this.getFilteredDetailRecords(detail);
      $("<span></span>").text((detail.title || detail.id) + ": " + rows.length).appendTo(this.masterContextElement);
    });
  };

  MasterDetailEngine.prototype.refreshDetailSummary = function(detail) {
    const host = this.detailSummaries[detail.id];
    if (!host) {
      return;
    }
    host.empty();
    const rows = this.getFilteredDetailRecords(detail);
    if (!rows.length) {
      $("<span></span>").text("Nenhum registro para o pai selecionado.").appendTo(host);
      return;
    }

    $("<span></span>").text(rows.length + " registro(s)").appendTo(host);
    detail.totals.forEach((total) => {
      const fieldId = String(total.field || "");
      const field = this.getField(detail, fieldId);
      const value = rows.reduce(function(sum, row) {
        return sum + Number(row[fieldId] || 0);
      }, 0);
      $("<span></span>").text((total.label || field.label || fieldId) + ": " + formatValue(value, Object.assign({}, field, total))).appendTo(host);
    });
  };

  MasterDetailEngine.prototype.setDetailButtonsEnabled = function(detail, enabled) {
    const buttons = this.detailButtons[detail.id] || {};
    Object.keys(buttons).forEach(function(key) {
      if (buttons[key] && typeof buttons[key].enable === "function") {
        buttons[key].enable(enabled);
      }
    });
  };

  MasterDetailEngine.prototype.getFilteredDetailRecords = function(detail) {
    const records = this.detailRecords[detail.id] || [];
    return records.filter((record) => String(record[detail.parentField]) === String(this.selectedMasterId));
  };

  MasterDetailEngine.prototype.getSelectedMaster = function() {
    const idField = this.definition.master.idField;
    return this.masterRecords.find((record) => String(record[idField]) === String(this.selectedMasterId)) || null;
  };

  MasterDetailEngine.prototype.getSelectedDetailRecord = function(detail) {
    const grid = this.detailGrids[detail.id];
    if (!grid) {
      return null;
    }
    return grid.dataItem(grid.select()) || null;
  };

  MasterDetailEngine.prototype.openMasterWindow = function(mode, record) {
    const master = this.definition.master;
    const values = mode === "create" ? this.createDefaultRecord(master) : clone(toPlainRecord(record));
    this.openRecordWindow({
      title: (mode === "create" ? "Incluir " : "Alterar ") + (master.singularTitle || master.title || "pai"),
      section: master,
      mode: mode,
      values: values,
      save: (nextValues) => this.saveMaster(mode, record, nextValues)
    });
  };

  MasterDetailEngine.prototype.openDetailWindow = function(detail, mode, record) {
    if (this.selectedMasterId == null) {
      showMessage("Selecione o registro pai antes de incluir filhos.", "warning");
      return;
    }
    const values = mode === "create" ? this.createDefaultRecord(detail) : clone(toPlainRecord(record));
    values[detail.parentField] = this.selectedMasterId;
    this.openRecordWindow({
      title: (mode === "create" ? "Incluir " : "Alterar ") + (detail.singularTitle || detail.title || "filho"),
      section: detail,
      mode: mode,
      values: values,
      save: (nextValues) => this.saveDetail(detail, mode, record, nextValues)
    });
  };

  MasterDetailEngine.prototype.openRecordWindow = function(options) {
    const wrapper = $("<div class=\"master-detail-window\"></div>").appendTo(document.body);
    const form = $("<form class=\"master-detail-form\"></form>").appendTo(wrapper);
    const editors = {};

    ensureArray(options.section.fields).forEach((field) => {
      const id = field.id || field.code;
      if (!id || field.hidden === true) {
        return;
      }
      const row = $("<label class=\"master-detail-field\"></label>").appendTo(form);
      $("<span></span>").text((field.label || id) + (field.required ? " *" : "")).appendTo(row);
      editors[id] = this.createEditor(row, field, options.values[id], options.mode);
    });

    const validation = $("<div class=\"master-detail-validation\"></div>").appendTo(form);
    const actions = $("<div class=\"master-detail-window-actions\"></div>").appendTo(form);
    const saveButton = $("<button type=\"button\"></button>").text("Confirmar").appendTo(actions).kendoButton({
      icon: "check",
      themeColor: "primary"
    }).data("kendoButton");
    const cancelButton = $("<button type=\"button\"></button>").text("Cancelar").appendTo(actions).kendoButton({
      icon: "cancel"
    }).data("kendoButton");

    wrapper.kendoWindow({
      title: options.title,
      modal: true,
      visible: false,
      width: "min(760px, 94vw)",
      close: function() {
        wrapper.data("kendoWindow").destroy();
      }
    });
    const windowWidget = wrapper.data("kendoWindow");

    saveButton.bind("click", () => {
      validation.empty();
      const values = Object.assign({}, options.values, this.collectEditorValues(options.section, editors));
      const errors = this.validateRecord(options.section, values);
      if (errors.length) {
        validation.text(errors.join(" "));
        showMessage(errors.join("\n"), "warning");
        return;
      }
      const result = options.save(values);
      if (result && typeof result.then === "function") {
        setWindowButtonsEnabled(saveButton, cancelButton, false);
        result.then(() => {
          windowWidget.close();
        }).catch((error) => {
          const message = this.errorMessage(error, "Nao foi possivel salvar o registro.");
          validation.text(message);
          showMessage(message, "error");
          setWindowButtonsEnabled(saveButton, cancelButton, true);
        });
        return;
      }
      windowWidget.close();
    });
    cancelButton.bind("click", () => windowWidget.close());

    windowWidget.center().open();
  };

  MasterDetailEngine.prototype.createEditor = function(row, field, value, mode) {
    const type = String(field.type || field.dataType || "string").toLowerCase();
    const disabled = field.readonly === true || (field.readonlyOnEdit === true && mode === "edit");
    let input;

    if (type === "text") {
      input = $("<textarea rows=\"4\"></textarea>").appendTo(row);
      input.val(value == null ? "" : String(value));
      if ($.fn.kendoTextArea) {
        input.kendoTextArea();
      }
    } else if (type === "enum" || type === "dropdown") {
      input = $("<input>").appendTo(row);
      input.kendoDropDownList({
        dataTextField: "text",
        dataValueField: "value",
        optionLabel: field.required ? undefined : "Selecione",
        dataSource: normalizeOptions(field.options || field.items),
        value: value == null ? "" : String(value)
      });
    } else if (type === "date") {
      input = $("<input>").appendTo(row);
      input.kendoDatePicker({
        format: "dd/MM/yyyy",
        value: value ? parseDateValue(value) : null
      });
    } else if (["integer", "number", "decimal", "currency"].indexOf(type) !== -1) {
      input = $("<input>").appendTo(row);
      input.kendoNumericTextBox({
        decimals: type === "integer" ? 0 : Number(field.decimals || 2),
        format: type === "currency" ? "c2" : (type === "integer" ? "n0" : "n2"),
        value: value == null || value === "" ? null : Number(value)
      });
    } else if (type === "boolean") {
      input = $("<input type=\"checkbox\">").appendTo(row);
      input.prop("checked", value === true || value === "true" || value === 1);
      if ($.fn.kendoSwitch) {
        input.kendoSwitch();
      }
    } else {
      input = $("<input>").appendTo(row);
      input.val(value == null ? "" : String(value));
      input.kendoTextBox();
    }

    if (disabled) {
      disableEditor(input);
    }
    return { input: input, field: field };
  };

  MasterDetailEngine.prototype.collectEditorValues = function(section, editors) {
    const values = {};
    Object.keys(editors).forEach(function(id) {
      const editor = editors[id];
      const input = editor.input;
      const field = editor.field;
      const type = String(field.type || field.dataType || "string").toLowerCase();
      const widget = input.data("kendoDropDownList") || input.data("kendoDatePicker") || input.data("kendoNumericTextBox") || input.data("kendoTextBox") || input.data("kendoTextArea") || input.data("kendoSwitch");
      let value;
      if (type === "boolean") {
        value = widget && typeof widget.check === "function" ? widget.check() : input.is(":checked");
      } else if (type === "date") {
        value = formatDate(widget ? widget.value() : input.val());
      } else if (widget && typeof widget.value === "function") {
        value = widget.value();
      } else {
        value = input.val();
      }
      values[id] = value;
    });
    return values;
  };

  MasterDetailEngine.prototype.validateRecord = function(section, values) {
    const errors = [];
    ensureArray(section.fields).forEach(function(field) {
      const id = field.id || field.code;
      if (!id || field.required !== true || field.hidden === true) {
        return;
      }
      const value = values[id];
      if (value == null || String(value).trim() === "") {
        errors.push((field.label || id) + " e obrigatorio.");
      }
    });
    return errors;
  };

  MasterDetailEngine.prototype.saveMaster = function(mode, originalRecord, values) {
    const master = this.definition.master;
    const operation = mode === "create" ? "create" : "update";
    if (this.hasEndpoint(master, operation)) {
      return this.requestSection(master, operation, this.writePayload(master, values, originalRecord))
        .then((response) => {
          const saved = this.extractResponseRecord(response, values);
          this.storeMasterRecord(mode, originalRecord, saved);
          showMessage("Registro pai salvo.", "success");
          return saved;
        });
    }
    this.storeMasterRecord(mode, originalRecord, values);
    showMessage("Registro pai salvo.", "success");
  };

  MasterDetailEngine.prototype.storeMasterRecord = function(mode, originalRecord, values) {
    const idField = this.definition.master.idField;
    if (mode === "create") {
      values[idField] = values[idField] || this.nextId(this.masterRecords, idField);
      this.masterRecords.push(values);
      this.selectedMasterId = values[idField];
    } else {
      const originalId = originalRecord[idField];
      const index = this.masterRecords.findIndex(function(item) {
        return String(item[idField]) === String(originalId);
      });
      if (index !== -1) {
        this.masterRecords[index] = values;
        this.selectedMasterId = values[idField];
      }
    }
    this.refreshMasterGrid();
    this.setSelectedMaster(this.selectedMasterId);
  };

  MasterDetailEngine.prototype.saveDetail = function(detail, mode, originalRecord, values) {
    const operation = mode === "create" ? "create" : "update";
    values[detail.parentField] = this.selectedMasterId;
    if (this.hasEndpoint(detail, operation)) {
      return this.requestSection(detail, operation, this.writePayload(detail, values, originalRecord))
        .then((response) => {
          const saved = this.extractResponseRecord(response, values);
          if (saved[detail.parentField] == null || saved[detail.parentField] === "") {
            saved[detail.parentField] = this.selectedMasterId;
          }
          this.storeDetailRecord(detail, mode, originalRecord, saved);
          showMessage("Registro filho salvo.", "success");
          return saved;
        });
    }
    this.storeDetailRecord(detail, mode, originalRecord, values);
    showMessage("Registro filho salvo.", "success");
  };

  MasterDetailEngine.prototype.storeDetailRecord = function(detail, mode, originalRecord, values) {
    const idField = detail.idField;
    values[detail.parentField] = this.selectedMasterId;
    const records = this.detailRecords[detail.id] || [];
    if (mode === "create") {
      values[idField] = values[idField] || this.nextId(records, idField);
      records.push(values);
    } else {
      const originalId = originalRecord[idField];
      const index = records.findIndex(function(item) {
        return String(item[idField]) === String(originalId);
      });
      if (index !== -1) {
        records[index] = values;
      }
    }
    this.detailRecords[detail.id] = records;
    this.refreshDetailGrids();
    this.refreshMasterContext();
  };

  MasterDetailEngine.prototype.deleteSelectedMaster = function() {
    const record = this.getSelectedMaster();
    if (record) {
      this.deleteMaster(record);
    }
  };

  MasterDetailEngine.prototype.deleteMaster = function(record) {
    const idField = this.definition.master.idField;
    const id = record[idField];
    confirmAction("Excluir o registro pai tambem remove os filhos vinculados nesta tela. Deseja continuar?", () => {
      const removeLocal = () => {
        this.masterRecords = this.masterRecords.filter(function(item) {
          return String(item[idField]) !== String(id);
        });
        this.definition.details.forEach((detail) => {
          this.detailRecords[detail.id] = (this.detailRecords[detail.id] || []).filter(function(item) {
            return String(item[detail.parentField]) !== String(id);
          });
        });
        this.selectedMasterId = this.masterRecords.length ? this.masterRecords[0][idField] : null;
        this.refreshMasterGrid();
        this.setSelectedMaster(this.selectedMasterId);
        showMessage("Registro pai excluido.", "success");
      };
      if (this.hasEndpoint(this.definition.master, "delete")) {
        this.requestSection(this.definition.master, "delete", this.deletePayload(this.definition.master, record))
          .then(removeLocal)
          .catch((error) => showMessage(this.errorMessage(error, "Nao foi possivel excluir o registro pai."), "error"));
        return;
      }
      removeLocal();
    });
  };

  MasterDetailEngine.prototype.deleteSelectedDetail = function(detail) {
    const record = this.getSelectedDetailRecord(detail);
    if (record) {
      this.deleteDetail(detail, record);
    }
  };

  MasterDetailEngine.prototype.deleteDetail = function(detail, record) {
    const idField = detail.idField;
    const id = record[idField];
    confirmAction("Excluir este registro filho?", () => {
      const removeLocal = () => {
        this.detailRecords[detail.id] = (this.detailRecords[detail.id] || []).filter(function(item) {
          return String(item[idField]) !== String(id);
        });
        this.refreshDetailGrids();
        this.refreshMasterContext();
        showMessage("Registro filho excluido.", "success");
      };
      if (this.hasEndpoint(detail, "delete")) {
        this.requestSection(detail, "delete", this.deletePayload(detail, record))
          .then(removeLocal)
          .catch((error) => showMessage(this.errorMessage(error, "Nao foi possivel excluir o registro filho."), "error"));
        return;
      }
      removeLocal();
    });
  };

  MasterDetailEngine.prototype.hasEndpoint = function(section, operation) {
    const endpoint = section && section.api && section.api[operation];
    return Boolean(endpoint && endpoint.url && this.httpClient && typeof this.httpClient.request === "function");
  };

  MasterDetailEngine.prototype.hasRemoteDetails = function() {
    return this.definition.details.some((detail) => this.hasEndpoint(detail, "read"));
  };

  MasterDetailEngine.prototype.requestSection = function(section, operation, payload) {
    const endpoint = section.api && section.api[operation];
    if (!endpoint || !endpoint.url || !this.httpClient) {
      return Promise.reject(new Error("Endpoint nao configurado."));
    }
    return this.httpClient.request({
      url: endpoint.url,
      method: endpoint.method || "POST",
      data: payload || {}
    });
  };

  MasterDetailEngine.prototype.extractResponseRows = function(response) {
    if (Array.isArray(response)) {
      return clone(response);
    }
    if (response && Array.isArray(response.data)) {
      return clone(response.data);
    }
    if (response && Array.isArray(response.items)) {
      return clone(response.items);
    }
    if (response && Array.isArray(response.rows)) {
      return clone(response.rows);
    }
    return [];
  };

  MasterDetailEngine.prototype.extractResponseRecord = function(response, fallback) {
    if (response && response.data && !Array.isArray(response.data)) {
      return clone(response.data);
    }
    if (response && response.record) {
      return clone(response.record);
    }
    if (response && typeof response === "object" && !Array.isArray(response)) {
      return clone(response);
    }
    return clone(fallback || {});
  };

  MasterDetailEngine.prototype.writePayload = function(section, values, originalRecord) {
    const idField = section.idField || "id";
    const plainValues = clone(values || {});
    const original = toPlainRecord(originalRecord);
    const id = original && original[idField] != null ? original[idField] : plainValues[idField];
    const payload = {
      values: plainValues
    };
    if (id != null && id !== "") {
      payload.id = id;
      payload[idField] = id;
    }
    return payload;
  };

  MasterDetailEngine.prototype.deletePayload = function(section, record) {
    const idField = section.idField || "id";
    const plainRecord = toPlainRecord(record);
    const id = plainRecord[idField];
    const payload = {
      id: id,
      values: {}
    };
    payload[idField] = id;
    return payload;
  };

  MasterDetailEngine.prototype.errorMessage = function(error, fallback) {
    if (global.CrudUtils && typeof global.CrudUtils.unwrapError === "function") {
      const unwrapped = global.CrudUtils.unwrapError(error, fallback || "Falha na operacao.");
      return unwrapped && unwrapped.message ? unwrapped.message : fallback;
    }
    return error && (error.message || error.error && error.error.message) || fallback || "Falha na operacao.";
  };

  MasterDetailEngine.prototype.createDefaultRecord = function(section) {
    const record = {};
    ensureArray(section.fields).forEach(function(field) {
      const id = field.id || field.code;
      if (!id) {
        return;
      }
      if (field.defaultValue !== undefined) {
        record[id] = clone(field.defaultValue);
      } else if (String(field.type || field.dataType || "").toLowerCase() === "boolean") {
        record[id] = false;
      } else {
        record[id] = "";
      }
    });
    return record;
  };

  MasterDetailEngine.prototype.nextId = function(records, idField) {
    return records.reduce(function(max, record) {
      const value = Number(record[idField]);
      return Number.isFinite(value) ? Math.max(max, value) : max;
    }, 0) + 1;
  };

  MasterDetailEngine.prototype.getField = function(section, fieldId) {
    return this.fieldsById(section.fields)[fieldId] || { id: fieldId, label: fieldId };
  };

  MasterDetailEngine.prototype.fieldsById = function(fields) {
    return ensureArray(fields).reduce(function(acc, field) {
      const id = field.id || field.code;
      if (id) {
        acc[id] = field;
      }
      return acc;
    }, {});
  };

  MasterDetailEngine.prototype.kendoFormat = function(field) {
    const type = String(field.type || field.dataType || "").toLowerCase();
    if (field.format) {
      return field.format;
    }
    if (type === "date") {
      return "{0:dd/MM/yyyy}";
    }
    if (type === "currency") {
      return "{0:c2}";
    }
    if (type === "decimal" || type === "number") {
      return "{0:n2}";
    }
    if (type === "integer") {
      return "{0:n0}";
    }
    return undefined;
  };

  MasterDetailEngine.prototype.alignAttributes = function(align) {
    return align ? { style: "text-align:" + align } : undefined;
  };

  MasterDetailEngine.prototype.renderFatal = function(message) {
    this.root.html("<div class=\"crud-message crud-message-error\"><strong>" + escapeHtml(message) + "</strong></div>");
  };

  function ensureArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function clone(value) {
    return value == null ? value : JSON.parse(JSON.stringify(value));
  }

  function toPlainRecord(record) {
    if (!record) {
      return {};
    }
    return typeof record.toJSON === "function" ? record.toJSON() : record;
  }

  function mapKendoType(type) {
    const value = String(type || "").toLowerCase();
    if (value === "integer" || value === "number" || value === "decimal" || value === "currency") {
      return "number";
    }
    if (value === "date") {
      return "date";
    }
    if (value === "boolean") {
      return "boolean";
    }
    return "string";
  }

  function normalizeOptions(options) {
    return ensureArray(options).map(function(option) {
      if (typeof option === "string") {
        return { value: option, text: option };
      }
      return {
        value: option.value != null ? option.value : option.id,
        text: option.text || option.label || option.name || option.value || option.id
      };
    });
  }

  function disableEditor(input) {
    const widget = input.data("kendoDropDownList") || input.data("kendoDatePicker") || input.data("kendoNumericTextBox") || input.data("kendoTextBox") || input.data("kendoTextArea") || input.data("kendoSwitch");
    if (widget && typeof widget.enable === "function") {
      widget.enable(false);
      return;
    }
    input.prop("disabled", true);
  }

  function setWindowButtonsEnabled(saveButton, cancelButton, enabled) {
    [saveButton, cancelButton].forEach(function(button) {
      if (button && typeof button.enable === "function") {
        button.enable(enabled);
      }
    });
  }

  function formatDate(value) {
    if (!value) {
      return "";
    }
    const date = parseDateValue(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return date.getFullYear() + "-" + month + "-" + day;
  }

  function formatValue(value, field) {
    const type = String(field && (field.type || field.dataType) || "").toLowerCase();
    if (value == null || value === "") {
      return "-";
    }
    if (global.kendo) {
      if (type === "date") {
        return global.kendo.toString(parseDateValue(value), "dd/MM/yyyy");
      }
      if (type === "currency") {
        return global.kendo.toString(Number(value || 0), "c2");
      }
      if (type === "decimal" || type === "number") {
        return global.kendo.toString(Number(value || 0), "n2");
      }
      if (type === "integer") {
        return global.kendo.toString(Number(value || 0), "n0");
      }
    }
    return String(value);
  }

  function parseDateValue(value) {
    if (value instanceof Date) {
      return value;
    }
    const text = String(value || "");
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (match) {
      return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }
    return new Date(value);
  }

  function showMessage(message, type) {
    if (global.CrudUtils && typeof global.CrudUtils.showMessage === "function") {
      global.CrudUtils.showMessage(message, type || "info");
      return;
    }
    const wrapper = $("<div class=\"master-detail-inline-message\"></div>").text(message).appendTo(document.body);
    window.setTimeout(function() { wrapper.remove(); }, 3000);
  }

  function confirmAction(message, onConfirm) {
    if (global.CrudUtils && typeof global.CrudUtils.confirm === "function") {
      global.CrudUtils.confirm(message, {
        title: "Confirmar exclusao",
        confirmText: "Excluir",
        confirmIcon: "trash"
      }).then(function(confirmed) {
        if (confirmed) {
          onConfirm();
        }
      });
      return;
    }
    onConfirm();
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  global.MasterDetailEngine = MasterDetailEngine;
})(window, window.jQuery);
