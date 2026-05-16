(function(global, $) {
  "use strict";

  class CrudFilterRenderer {
    constructor(options) {
      this.definition = options.definition;
      this.onApply = options.onApply || function() {};
      this.onClear = options.onClear || function() {};
      this.onSavePreset = options.onSavePreset || function() {};
      this.onDeletePreset = options.onDeletePreset || function() {};
      this.inputs = {};
    }

    render() {
      const filterConfig = this.getFilterConfig();
      this.windowElement = $("<div class=\"crud-filter-window\"></div>").appendTo(document.body);
      const panel = $("<section class=\"crud-filter-panel\"></section>");

      const body = $("<div class=\"crud-filter-body\"></div>").appendTo(panel);
      const savedArea = $("<div class=\"crud-filter-saved\"></div>").appendTo(body);
      const presetField = $("<div class=\"crud-field\"></div>").appendTo(savedArea);
      $("<label for=\"crud-filter-preset-select\">Filtro salvo</label>").appendTo(presetField);
      const presetSelect = $("<input id=\"crud-filter-preset-select\">").appendTo(presetField);

      this.renderFilterFields(body, filterConfig);

      const actions = $("<div class=\"crud-filter-appbar crud-filter-actions\"></div>").appendTo(panel);
      const applyButton = $("<button type=\"button\">Filtrar</button>")
        .appendTo(actions)
        .kendoButton({ icon: "filter" })
        .on("click", () => {
          this.onApply(this.getValues());
          this.close();
        });
      const clearButton = $("<button type=\"button\">Limpar</button>")
        .appendTo(actions)
        .kendoButton({ icon: "filter-clear" })
        .on("click", () => {
          this.clear();
          this.onClear();
        });
      const newButton = $("<button type=\"button\">Novo filtro</button>")
        .appendTo(actions)
        .kendoButton({ icon: "plus" })
        .on("click", () => {
          this.editingFilterId = null;
          this.presetSelect.data("kendoDropDownList").value("");
          this.clear();
        });
      const saveButton = $("<button type=\"button\">Salvar filtro</button>")
        .appendTo(actions)
        .kendoButton({ icon: "save" })
        .on("click", () => this.openSavePresetWindow());
      const deleteButton = $("<button type=\"button\">Excluir filtro</button>")
        .appendTo(actions)
        .kendoButton({ icon: "trash" })
        .on("click", () => this.deletePreset());
      const closeButton = $("<button type=\"button\">Fechar</button>")
        .appendTo(actions)
        .kendoButton()
        .on("click", () => this.close());

      this.windowElement.append(panel);
      this.windowElement.kendoWindow({
        title: filterConfig.title || "Filtros",
        modal: false,
        visible: false,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: this.getWindowWidth(),
        height: this.getWindowHeight(),
        maximize: () => {
          this.windowElement.addClass("crud-filter-window-maximized");
        },
        restore: () => {
          this.windowElement.removeClass("crud-filter-window-maximized");
        },
        close: () => {
          this.isOpen = false;
        },
        open: () => {
          this.isOpen = true;
        }
      });
      this.window = this.windowElement.data("kendoWindow");
      this.presetSelect = presetSelect;
      this.applyButton = applyButton;
      this.clearButton = clearButton;
      this.newButton = newButton;
      this.saveButton = saveButton;
      this.deleteButton = deleteButton;
      this.closeButton = closeButton;
      this.renderPresetSelect();
      return this.window;
    }

    getFilterConfig() {
      return this.definition.filter || {
        title: this.definition.query && this.definition.query.title || "Filtros",
        fields: this.definition.query && this.definition.query.filters || []
      };
    }

    open(options) {
      if (!this.window) {
        return;
      }
      const settings = options || {};
      if (!settings.maximized && this.windowElement && this.windowElement.hasClass("crud-filter-window-maximized")) {
        this.window.restore();
      }
      this.window.setOptions({
        width: this.getWindowWidth(),
        height: this.getWindowHeight()
      });
      this.window.center().open();
      if (settings.maximized) {
        this.window.maximize();
      }
    }

    close() {
      if (this.window) {
        this.window.close();
      }
    }

    destroy() {
      if (this.window) {
        this.window.destroy();
      }
      if (this.windowElement) {
        this.windowElement.remove();
      }
      this.destroyNotification();
      this.window = null;
      this.windowElement = null;
    }

    getWindowWidth() {
      return Math.min(760, Math.max(320, global.innerWidth - 24));
    }

    getWindowHeight() {
      return Math.min(680, Math.max(420, global.innerHeight - 48));
    }

    renderPresetSelect() {
      if (!this.presetSelect) {
        return;
      }
      this.presetSelect.kendoDropDownList({
        dataTextField: "name",
        dataValueField: "id",
        dataSource: this.getPresetDataSource(),
        value: this.getActiveFilterId(),
        change: () => {
          const preset = this.findSavedFilter(this.presetSelect.data("kendoDropDownList").value());
          this.loadPreset(preset);
        }
      });
      const initial = this.findSavedFilter(this.getActiveFilterId());
      if (initial) {
        this.loadPreset(initial);
      }
    }

    refreshPresetSelect() {
      const widget = this.presetSelect && this.presetSelect.data("kendoDropDownList");
      if (!widget) {
        return;
      }
      widget.setDataSource(this.getPresetDataSource());
      widget.value(this.editingFilterId || this.getActiveFilterId());
    }

    getSavedFilters() {
      return global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedFilters);
    }

    getActiveFilterId() {
      const userLayout = this.definition.userLayout || {};
      if (userLayout.activeFilterId) {
        return userLayout.activeFilterId;
      }
      const defaultFilter = this.getSavedFilters().find(function(item) {
        return item.isDefault;
      });
      return defaultFilter ? defaultFilter.id : "";
    }

    getPresetDataSource() {
      return [{
        id: "",
        name: "Livre"
      }].concat(this.getSavedFilters().map(function(item) {
        const suffix = (item.isDefault ? " (padrao)" : "") + (item.scope === "global" ? " (todos)" : "");
        return {
          id: item.id,
          name: item.name + suffix
        };
      }));
    }

    findSavedFilter(filterId) {
      return this.getSavedFilters().find(function(item) {
        return item.id === filterId;
      }) || null;
    }

    loadPreset(preset) {
      this.editingFilterId = preset ? preset.id : null;
      if (preset) {
        this.setValues(preset.filters);
      } else {
        this.clear();
      }
    }

    openSavePresetWindow() {
      const currentPreset = this.findSavedFilter(this.editingFilterId);
      const wrapper = $("<div></div>").appendTo(document.body);
      const form = $("<div class=\"crud-layout-form\"></div>").appendTo(wrapper);
      const nameField = $("<div class=\"crud-field\"></div>").appendTo(form);
      $("<label for=\"crud-filter-save-name\">Nome do filtro</label>").appendTo(nameField);
      const nameInput = $("<input id=\"crud-filter-save-name\">").appendTo(nameField);
      nameInput.kendoTextBox();
      nameInput.data("kendoTextBox").value(currentPreset ? currentPreset.name : "");

      const defaultField = $("<label class=\"crud-checkbox-field\"></label>").appendTo(form);
      const defaultInput = $("<input type=\"checkbox\" id=\"crud-filter-save-default\">").appendTo(defaultField);
      $("<span>Usar como filtro padrao</span>").appendTo(defaultField);
      defaultInput.prop("checked", Boolean(currentPreset && currentPreset.isDefault));

      const scopeInput = this.renderPreferenceScopeField(form, "crud-filter-save-global");
      scopeInput.prop("checked", Boolean(currentPreset && currentPreset.scope === "global"));

      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(form);
      const saveButton = $("<button type=\"button\">Salvar</button>").appendTo(actions);
      const cancelButton = $("<button type=\"button\">Cancelar</button>").appendTo(actions);
      saveButton.kendoButton({ themeColor: "primary", icon: "save" });
      cancelButton.kendoButton();

      wrapper.kendoWindow({
        title: "Salvar filtro",
        modal: true,
        width: Math.min(420, Math.max(320, window.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      saveButton.on("click", () => {
        this.savePreset({
          filters: this.getValues(),
          name: nameInput.data("kendoTextBox").value(),
          isDefault: defaultInput.is(":checked"),
          scope: this.getPreferenceScope(scopeInput),
          windowWidget
        });
      });
      cancelButton.on("click", function() {
        windowWidget.close();
      });

      windowWidget.center().open();
    }

    savePreset(options) {
      const settings = options || {};
      Promise.resolve(this.onSavePreset({
        id: this.editingFilterId,
        name: settings.name,
        isDefault: Boolean(settings.isDefault),
        scope: settings.scope === "global" ? "global" : "tenant",
        filters: settings.filters || this.getValues()
      })).then((response) => {
        if (response && response.filterPreset) {
          this.editingFilterId = response.filterPreset.id;
        }
        if (settings.windowWidget) {
          settings.windowWidget.close();
        }
        this.showMessage("Filtro salvo.", "success");
        this.refreshPresetSelect();
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao salvar filtro.");
        this.showMessage(normalized.message, "error");
      });
    }

    renderPreferenceScopeField(container, inputId) {
      const scopeField = $("<label class=\"crud-checkbox-field crud-preference-scope-field\"></label>").appendTo(container);
      const scopeInput = $("<input type=\"checkbox\">").attr("id", inputId).appendTo(scopeField);
      $("<span>Usar em todos os assinantes</span>").appendTo(scopeField);
      return scopeInput;
    }

    getPreferenceScope(scopeInput) {
      return scopeInput && scopeInput.is(":checked") ? "global" : "tenant";
    }

    deletePreset() {
      const value = this.presetSelect.data("kendoDropDownList").value();
      if (!value) {
        this.showMessage("Selecione um filtro salvo para excluir.", "error");
        return;
      }

      this.confirm("Deseja excluir este filtro?").then((confirmed) => {
        if (!confirmed) {
          return;
        }
        Promise.resolve(this.onDeletePreset(value)).then(() => {
          this.showMessage("Filtro excluido.", "success");
          this.editingFilterId = null;
          this.refreshPresetSelect();
          this.loadPreset(this.findSavedFilter(this.getActiveFilterId()));
        }).catch((error) => {
          const normalized = global.CrudUtils.unwrapError(error, "Erro ao excluir filtro.");
          this.showMessage(normalized.message, "error");
        });
      });
    }

    showMessage(message, type) {
      global.CrudUtils.showMessage(message, type || "success");
    }

    destroyNotification() {
      return;
    }

    confirm(message) {
      return global.CrudUtils.confirm(message, {
        title: "Confirmar exclusao",
        confirmText: "Excluir",
        confirmIcon: "trash"
      });
    }

    renderFilterFields(container, filterConfig) {
      const fields = global.CrudUtils.ensureArray(filterConfig.fields);
      if (!this.shouldRenderTabs(filterConfig)) {
        const grid = $("<div class=\"crud-filter-grid\"></div>").appendTo(container);
        fields.forEach((filter) => {
          this.renderFilter(grid, filter);
        });
        return;
      }

      const fieldMap = fields.reduce(function(acc, filter) {
        if (filter && filter.id) {
          acc[filter.id] = filter;
        }
        return acc;
      }, {});
      const rendered = {};
      const tabsConfig = filterConfig.tabs || {};
      const tabs = $("<div class=\"crud-filter-tabs\"></div>").appendTo(container);
      const tabList = $("<ul></ul>").appendTo(tabs);

      global.CrudUtils.ensureArray(tabsConfig.items).forEach((tab) => {
        $("<li></li>").text(tab.title || tab.id).appendTo(tabList);
        const tabContent = $("<div></div>").appendTo(tabs);
        const grid = $("<div class=\"crud-filter-grid\"></div>").appendTo(tabContent);
        global.CrudUtils.ensureArray(tab.fields).forEach((fieldId) => {
          if (fieldMap[fieldId] && !rendered[fieldId]) {
            rendered[fieldId] = true;
            this.renderFilter(grid, fieldMap[fieldId]);
          }
        });
      });

      const remainingFields = fields.filter(function(filter) {
        return filter && filter.id && !rendered[filter.id];
      });
      if (remainingFields.length) {
        $("<li></li>").text(tabsConfig.otherTitle || "Outros").appendTo(tabList);
        const tabContent = $("<div></div>").appendTo(tabs);
        const grid = $("<div class=\"crud-filter-grid\"></div>").appendTo(tabContent);
        remainingFields.forEach((filter) => {
          rendered[filter.id] = true;
          this.renderFilter(grid, filter);
        });
      }

      tabs.kendoTabStrip({
        animation: false
      });
      const widget = tabs.data("kendoTabStrip");
      if (widget) {
        widget.select(0);
      }
    }

    shouldRenderTabs(filterConfig) {
      const tabs = filterConfig.tabs || {};
      return tabs.enabled !== false && global.CrudUtils.ensureArray(tabs.items).length > 0;
    }

    renderFilter(container, filter, options) {
      const settings = options || {};
      const inputRegistry = settings.inputs || this.inputs;
      const idPrefix = settings.idPrefix || "filter-";
      const baseId = idPrefix + filter.id;
      const wrapper = $("<div class=\"crud-field crud-filter-field\"></div>").appendTo(container);
      const labelRow = $("<div class=\"crud-field-label-row\"></div>").appendTo(wrapper);
      $("<label></label>").attr("for", baseId).text(filter.label).appendTo(labelRow);
      const technicalProperties = global.CrudUtils.resolveTechnicalProperties(filter.technicalProperties, this.definition, filter.field);
      if (technicalProperties.length) {
        global.CrudUtils.appendTechnicalInfoTrigger(labelRow, filter.label, technicalProperties, {
          cssClass: "crud-field-technical-trigger",
          dataRole: "filter-technical-info"
        });
      }
      const controlRow = $("<div class=\"crud-filter-control-row\"></div>").appendTo(wrapper);

      if (filter.type === "dateRange" && !filter.operators && !filter.operatorOptions) {
        controlRow.addClass("crud-filter-control-row-single");
        const range = $("<div class=\"crud-filter-range\"></div>").appendTo(controlRow);
        const start = $("<input>").attr("id", baseId + "-start").appendTo(range);
        const end = $("<input>").attr("id", baseId + "-end").appendTo(range);
        start.kendoDatePicker({ format: "dd/MM/yyyy" });
        end.kendoDatePicker({ format: "dd/MM/yyyy" });
        inputRegistry[filter.id] = { filter, start, end, kind: "dateRange" };
        return;
      }

      const item = {
        filter,
        kind: "dynamic",
        baseId,
        controlRow,
        type: this.getFilterDataType(filter),
        operatorOptions: this.getOperatorOptions(filter)
      };

      if (item.operatorOptions.length > 1) {
        const operatorInput = $("<input>").attr("id", baseId + "-operator").appendTo(controlRow);
        operatorInput.kendoDropDownList({
          dataTextField: "label",
          dataValueField: "value",
          dataSource: item.operatorOptions,
          value: this.getDefaultOperator(filter, item.operatorOptions),
          change: () => this.renderDynamicEditor(item)
        });
        item.operatorInput = operatorInput;
      } else {
        controlRow.addClass("crud-filter-control-row-single");
        item.fixedOperator = this.getDefaultOperator(filter, item.operatorOptions);
      }

      item.editorContainer = $("<div class=\"crud-filter-value\"></div>").appendTo(controlRow);
      inputRegistry[filter.id] = item;
      this.renderDynamicEditor(item);
    }

    getFilterDataType(filter) {
      if (filter.filterType) {
        return filter.filterType;
      }
      if (filter.type && ["search", "enum", "lookup", "dateRange", "date", "datetime", "time", "number", "integer", "decimal", "text", "boolean"].indexOf(filter.type) !== -1) {
        return filter.type === "dateRange" ? "date" : filter.type;
      }
      const field = this.getFieldConfig(filter);
      return field && field.type ? field.type : "text";
    }

    getFieldConfig(filter) {
      const fields = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields
        : {};
      return filter.field ? fields[filter.field] : null;
    }

    getOperatorOptions(filter) {
      const configured = global.CrudUtils.ensureArray(filter.operatorOptions).length
        ? filter.operatorOptions
        : global.CrudUtils.ensureArray(filter.operators);
      if (configured.length) {
        return configured.map((operator) => this.normalizeOperatorOption(operator));
      }

      const type = this.getFilterDataType(filter);
      if (filter.type === "search") {
        return [this.normalizeOperatorOption(filter.operator || "contains")];
      }
      if (type === "enum" || type === "lookup") {
        return [this.normalizeOperatorOption(filter.operator || "eq")];
      }
      if (type === "boolean") {
        return [this.normalizeOperatorOption(filter.operator || "eq")];
      }
      if (type === "date") {
        return ["eq", "between", "gte", "lte", "gt", "lt", "relative"].map((operator) => this.normalizeOperatorOption(operator));
      }
      if (type === "datetime" || type === "time") {
        return ["eq", "between", "gte", "lte", "gt", "lt"].map((operator) => this.normalizeOperatorOption(operator));
      }
      if (type === "decimal") {
        return ["eq", "gte", "lte", "lt", "gt", "between"].map((operator) => this.normalizeOperatorOption(operator));
      }
      if (type === "integer" || type === "number") {
        return ["eq", "gte", "lte", "lt", "gt", "between", "in", "notIn"].map((operator) => this.normalizeOperatorOption(operator));
      }
      return ["eq", "startsWith", "contains", "notContains", "isEmpty", "isNull", "isNotEmpty", "isNotNull", "between", "in"].map((operator) => this.normalizeOperatorOption(operator));
    }

    normalizeOperatorOption(operator) {
      if (operator && typeof operator === "object") {
        return {
          value: operator.value || operator.id,
          label: operator.label || this.getOperatorLabel(operator.value || operator.id)
        };
      }
      return {
        value: operator,
        label: this.getOperatorLabel(operator)
      };
    }

    getDefaultOperator(filter, operatorOptions) {
      const options = global.CrudUtils.ensureArray(operatorOptions);
      const configured = filter.operator || filter.defaultOperator;
      if (configured && options.some(function(item) { return item.value === configured; })) {
        return configured;
      }
      return options.length ? options[0].value : "eq";
    }

    getOperatorLabel(operator) {
      const labels = {
        eq: "Igual a",
        neq: "Diferente de",
        startsWith: "Inicia com",
        contains: "Contem",
        notContains: "Nao contem",
        isEmpty: "Vazio",
        isNotEmpty: "Nao vazio",
        isNull: "Nulo",
        isNotNull: "Nao nulo",
        between: "Entre",
        in: "Esta contido em",
        notIn: "Nao esta contido em",
        gt: "Maior que",
        gte: "Maior ou igual",
        lt: "Menor que",
        lte: "Menor ou igual",
        relative: "Data relativa"
      };
      return labels[operator] || operator;
    }

    getCurrentOperator(item) {
      if (item.operatorInput) {
        return item.operatorInput.data("kendoDropDownList").value();
      }
      return item.fixedOperator || "eq";
    }

    renderDynamicEditor(item) {
      const operator = this.getCurrentOperator(item);
      kendo.destroy(item.editorContainer);
      item.editorContainer.empty();
      item.editor = { kind: "none", operator };

      if (this.operatorHasNoValue(operator)) {
        $("<span class=\"crud-filter-static\"></span>").text("Este operador nao usa valor.").appendTo(item.editorContainer);
        return;
      }

      if (operator === "relative" && item.type === "date") {
        this.renderRelativeDateEditor(item);
        return;
      }

      if (operator === "between") {
        this.renderRangeEditor(item);
        return;
      }

      if (operator === "in" || operator === "notIn") {
        if (this.hasOptionData(item.filter) && this.shouldUseOptionEditor(item.filter)) {
          this.renderOptionEditor(item, true);
        } else {
          this.renderListEditor(item);
        }
        return;
      }

      if (item.type === "enum" || item.type === "lookup") {
        this.renderOptionEditor(item, this.isMultiValueEditor(item.filter));
        return;
      }

      if (item.type === "boolean") {
        this.renderBooleanEditor(item);
        return;
      }

      if (item.type === "date") {
        this.renderDateEditor(item);
        return;
      }

      if (item.type === "datetime" || item.type === "time") {
        this.renderDateTimeEditor(item);
        return;
      }

      if (this.isNumericType(item.type)) {
        this.renderNumericEditor(item);
        return;
      }

      this.renderTextEditor(item);
    }

    operatorHasNoValue(operator) {
      return ["isEmpty", "isNotEmpty", "isNull", "isNotNull"].indexOf(operator) !== -1;
    }

    isNumericType(type) {
      return ["integer", "number", "decimal"].indexOf(type) !== -1;
    }

    renderTextEditor(item) {
      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      input.attr("placeholder", item.filter.placeholder || "");
      input.kendoTextBox({ value: item.filter.defaultValue || "" });
      item.editor = { kind: "text", input };
    }

    renderNumericEditor(item) {
      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      input.kendoNumericTextBox({
        format: item.type === "integer" ? "n0" : "n2",
        decimals: item.type === "integer" ? 0 : 2,
        value: item.filter.defaultValue != null ? item.filter.defaultValue : null
      });
      item.editor = { kind: "numeric", input };
    }

    renderDateEditor(item) {
      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      input.kendoDatePicker({
        format: "dd/MM/yyyy",
        value: global.CrudUtils.normalizeDateValue(item.filter.defaultValue)
      });
      item.editor = { kind: "date", input };
    }

    renderDateTimeEditor(item) {
      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      input.kendoDateTimePicker({
        format: "dd/MM/yyyy HH:mm",
        value: global.CrudUtils.normalizeDateValue(item.filter.defaultValue)
      });
      item.editor = { kind: "datetime", input };
    }

    renderBooleanEditor(item) {
      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      input.kendoDropDownList({
        dataTextField: "text",
        dataValueField: "value",
        dataSource: [
          { value: "", text: "Todos" },
          { value: "true", text: "Sim" },
          { value: "false", text: "Nao" }
        ],
        value: item.filter.defaultValue == null ? "" : String(Boolean(item.filter.defaultValue))
      });
      item.editor = { kind: "boolean", input };
    }

    renderRangeEditor(item) {
      const range = $("<div class=\"crud-filter-range\"></div>").appendTo(item.editorContainer);
      const start = $("<input>").attr("id", item.baseId + "-start").appendTo(range);
      const end = $("<input>").attr("id", item.baseId + "-end").appendTo(range);
      if (this.isNumericType(item.type)) {
        start.kendoNumericTextBox({ format: item.type === "integer" ? "n0" : "n2", decimals: item.type === "integer" ? 0 : 2 });
        end.kendoNumericTextBox({ format: item.type === "integer" ? "n0" : "n2", decimals: item.type === "integer" ? 0 : 2 });
        item.editor = { kind: "numericRange", start, end };
        return;
      }
      if (item.type === "date") {
        start.kendoDatePicker({ format: "dd/MM/yyyy" });
        end.kendoDatePicker({ format: "dd/MM/yyyy" });
        item.editor = { kind: "dateRange", start, end };
        return;
      }
      if (item.type === "datetime" || item.type === "time") {
        start.kendoDateTimePicker({ format: "dd/MM/yyyy HH:mm" });
        end.kendoDateTimePicker({ format: "dd/MM/yyyy HH:mm" });
        item.editor = { kind: "dateTimeRange", start, end };
        return;
      }
      start.kendoTextBox();
      end.kendoTextBox();
      item.editor = { kind: "textRange", start, end };
    }

    renderListEditor(item) {
      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      input.attr("placeholder", item.filter.placeholder || "Valores separados por virgula");
      input.kendoTextBox();
      item.editor = { kind: "list", input };
    }

    renderOptionEditor(item, multiple) {
      const editor = item.filter.editor || (multiple ? "multiselect" : "dropdown");
      if (editor === "checkbox" && !multiple) {
        this.renderCheckboxEditor(item);
        return;
      }
      if (editor === "checkboxgroup" || editor === "checkboxGroup") {
        this.renderCheckboxGroupEditor(item);
        return;
      }
      if (editor === "searchWindow") {
        this.renderSearchWindowEditor(item, multiple);
        return;
      }
      if (editor === "dropdowntree") {
        this.renderDropDownTreeEditor(item);
        return;
      }

      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      const options = this.getOptionData(item.filter);
      if (multiple) {
        input.kendoMultiSelect({
          dataTextField: "text",
          dataValueField: "value",
          dataSource: this.buildOptionDataSource(item.filter, options),
          value: global.CrudUtils.ensureArray(item.filter.defaultValue)
        });
        item.editor = { kind: "multiselect", input };
        return;
      }

      input.kendoDropDownList({
        optionLabel: item.filter.optionLabel || "Todos",
        dataTextField: "text",
        dataValueField: "value",
        dataSource: this.buildOptionDataSource(item.filter, options),
        valueTemplate: this.getSafeOptionTemplate(item.filter.valueTemplateFields),
        template: this.getSafeOptionTemplate(item.filter.templateFields),
        value: item.filter.defaultValue || ""
      });
      item.editor = { kind: "dropdown", input };
    }

    renderCheckboxEditor(item) {
      const options = this.getOptionData(item.filter);
      const option = options[0] || { value: true, text: item.filter.label };
      const label = $("<label class=\"crud-checkbox-field\"></label>").appendTo(item.editorContainer);
      const input = $("<input type=\"checkbox\">").appendTo(label);
      $("<span></span>").text(option.text || item.filter.label).appendTo(label);
      input.val(option.value);
      input.prop("checked", Boolean(item.filter.defaultValue));
      item.editor = { kind: "checkbox", input, option };
    }

    renderCheckboxGroupEditor(item) {
      const group = $("<div class=\"crud-filter-checkbox-group\"></div>").appendTo(item.editorContainer);
      const inputs = [];
      const defaultValues = global.CrudUtils.ensureArray(item.filter.defaultValue).map(String);
      this.getOptionData(item.filter).forEach((option, index) => {
        const id = item.baseId + "-option-" + index;
        const label = $("<label class=\"crud-checkbox-field\"></label>").appendTo(group);
        const input = $("<input type=\"checkbox\">").attr("id", id).val(option.value).appendTo(label);
        $("<span></span>").text(option.text).appendTo(label);
        input.prop("checked", defaultValues.indexOf(String(option.value)) !== -1);
        inputs.push({ input, option });
      });
      item.editor = { kind: "checkboxgroup", inputs };
    }

    renderDropDownTreeEditor(item) {
      const input = $("<input>").attr("id", item.baseId).appendTo(item.editorContainer);
      const dataSource = global.CrudUtils.ensureArray(item.filter.treeData).length
        ? item.filter.treeData
        : this.getOptionData(item.filter);
      if ($.fn.kendoDropDownTree) {
        input.kendoDropDownTree({
          dataTextField: item.filter.dataTextField || "text",
          dataValueField: item.filter.dataValueField || "value",
          checkboxes: item.filter.checkboxes !== false,
          autoClose: false,
          dataSource
        });
        item.editor = { kind: "dropdowntree", input };
        return;
      }

      input.kendoMultiSelect({
        dataTextField: "text",
        dataValueField: "value",
        dataSource: dataSource,
        value: global.CrudUtils.ensureArray(item.filter.defaultValue)
      });
      item.editor = { kind: "multiselect", input };
    }

    renderSearchWindowEditor(item, multiple) {
      const wrapper = $("<div class=\"crud-filter-search-window\"></div>").appendTo(item.editorContainer);
      const input = $("<input readonly>").attr("id", item.baseId).appendTo(wrapper);
      input.kendoTextBox();
      const button = $("<button type=\"button\">Buscar</button>").appendTo(wrapper);
      button.kendoButton({ icon: "search" });
      const state = {
        selected: multiple ? [] : null,
        selectedText: ""
      };
      button.on("click", () => this.openSearchWindow(item, state, multiple));
      item.editor = { kind: "searchWindow", input, state, multiple };
    }

    openSearchWindow(item, state, multiple) {
      const options = this.getOptionData(item.filter);
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-filter-lookup-window\"></div>").appendTo(wrapper);
      const search = $("<input>").appendTo(content);
      const list = $("<div class=\"crud-filter-lookup-list\"></div>").appendTo(content);
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const selectButton = $("<button type=\"button\">Selecionar</button>").appendTo(actions);
      const closeButton = $("<button type=\"button\">Cancelar</button>").appendTo(actions);
      let selected = multiple ? global.CrudUtils.ensureArray(state.selected).slice() : state.selected;

      search.kendoTextBox({ placeholder: "Buscar" });
      const renderList = () => {
        const text = String(search.data("kendoTextBox").value() || "").toLowerCase();
        list.empty();
        options.filter(function(option) {
          return !text || String(option.text || "").toLowerCase().indexOf(text) !== -1;
        }).forEach(function(option, index) {
          const label = $("<label class=\"crud-checkbox-field\"></label>").appendTo(list);
          const input = $("<input>").attr("type", multiple ? "checkbox" : "radio").attr("name", item.baseId + "-lookup").val(option.value).appendTo(label);
          $("<span></span>").text(option.text).appendTo(label);
          input.prop("checked", multiple ? selected.map(String).indexOf(String(option.value)) !== -1 : String(selected) === String(option.value));
          input.on("change", function() {
            if (multiple) {
              const value = String(option.value);
              selected = selected.map(String);
              if (this.checked && selected.indexOf(value) === -1) {
                selected.push(value);
              } else if (!this.checked) {
                selected = selected.filter(function(current) { return current !== value; });
              }
            } else {
              selected = option.value;
            }
          });
          if (index === 0 && !multiple && selected == null) {
            input.prop("checked", true);
            selected = option.value;
          }
        });
      };

      search.on("input", renderList);
      selectButton.kendoButton({ themeColor: "primary", icon: "check" });
      closeButton.kendoButton();
      wrapper.kendoWindow({
        title: item.filter.searchTitle || "Buscar",
        modal: true,
        width: Math.min(560, Math.max(320, window.innerWidth - 24)),
        visible: false,
        close: function() {
          kendo.destroy(content);
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });

      const windowWidget = wrapper.data("kendoWindow");
      selectButton.on("click", () => {
        state.selected = multiple ? selected.slice() : selected;
        state.selectedText = options.filter(function(option) {
          return multiple ? selected.map(String).indexOf(String(option.value)) !== -1 : String(option.value) === String(selected);
        }).map(function(option) {
          return option.text;
        }).join(", ");
        item.editor.input.data("kendoTextBox").value(state.selectedText);
        windowWidget.close();
      });
      closeButton.on("click", function() {
        windowWidget.close();
      });

      renderList();
      windowWidget.center().open();
    }

    renderRelativeDateEditor(item) {
      const config = item.filter.relativeDate || {};
      const container = $("<div class=\"crud-filter-relative\"></div>").appendTo(item.editorContainer);
      const preset = $("<input>").attr("id", item.baseId + "-preset").appendTo(container);
      const amountWrap = $("<div class=\"crud-filter-relative-extra\"></div>").appendTo(container);
      const amount = $("<input>").attr("id", item.baseId + "-amount").appendTo(amountWrap);
      const directionWrap = $("<div class=\"crud-filter-relative-extra\"></div>").appendTo(container);
      const direction = $("<input>").attr("id", item.baseId + "-direction").appendTo(directionWrap);

      preset.kendoDropDownList({
        dataTextField: "text",
        dataValueField: "value",
        dataSource: [
          { value: "yesterday", text: "Ontem" },
          { value: "today", text: "Hoje" },
          { value: "tomorrow", text: "Amanha" },
          { value: "weeks", text: "Qt. semanas" },
          { value: "fortnights", text: "Qt. quinzenas" },
          { value: "days", text: "Qt. dias" },
          { value: "months", text: "Qt. meses" },
          { value: "years", text: "Qt. anos" }
        ],
        value: this.normalizeRelativePreset(config.defaultPreset || "today", config.defaultUnit),
        change: () => this.updateRelativeDateEditorState(item.editor)
      });
      amount.kendoNumericTextBox({
        format: "n0",
        decimals: 0,
        min: 1,
        value: config.defaultAmount || 1
      });
      direction.kendoDropDownList({
        dataTextField: "text",
        dataValueField: "value",
        dataSource: [
          { value: "previous", text: "Antes" },
          { value: "next", text: "Depois" }
        ],
        value: config.defaultDirection || "previous"
      });
      item.editor = { kind: "relativeDate", preset, amount, amountWrap, direction, directionWrap };
      this.updateRelativeDateEditorState(item.editor);
    }

    updateRelativeDateEditorState(editor) {
      if (!editor || editor.kind !== "relativeDate") {
        return;
      }
      const preset = editor.preset.data("kendoDropDownList").value();
      const showExtra = ["yesterday", "today", "tomorrow"].indexOf(preset) === -1;
      editor.amountWrap.toggle(showExtra);
      editor.directionWrap.toggle(showExtra);
      editor.preset.closest(".crud-filter-relative").toggleClass("crud-filter-relative-compact", !showExtra);
    }

    hasOptionData(filter) {
      return this.getOptionData(filter).length > 0 || global.CrudUtils.ensureArray(filter.treeData).length > 0;
    }

    shouldUseOptionEditor(filter) {
      return ["dropdown", "dropdownList", "multiselect", "dropdowntree", "checkbox", "checkboxgroup", "checkboxGroup", "searchWindow"].indexOf(filter.editor) !== -1;
    }

    isMultiValueEditor(filter) {
      return Boolean(filter.multiple) || ["multiselect", "dropdowntree", "checkboxgroup", "checkboxGroup"].indexOf(filter.editor) !== -1;
    }

    getOptionData(filter) {
      const field = this.getFieldConfig(filter) || {};
      const source = global.CrudUtils.ensureArray(filter.options).length ? filter.options : global.CrudUtils.ensureArray(field.options);
      return source.map(function(option) {
        if (option && typeof option === "object") {
          return Object.assign({}, option, {
            value: option.value,
            text: option.text || option.label || String(option.value)
          });
        }
        return {
          value: option,
          text: String(option)
        };
      });
    }

    buildOptionDataSource(filter, options) {
      const dataSource = {
        data: options
      };
      if (filter.groupBy || filter.groupField) {
        dataSource.group = { field: filter.groupBy || filter.groupField };
      }
      return dataSource;
    }

    getSafeOptionTemplate(fields) {
      const templateFields = global.CrudUtils.ensureArray(fields);
      if (!templateFields.length) {
        return undefined;
      }
      return function(dataItem) {
        return templateFields.map(function(fieldName) {
          return global.CrudUtils.escapeHtml(dataItem[fieldName]);
        }).join(" - ");
      };
    }

    getValues() {
      const values = [];
      Object.keys(this.inputs).forEach((key) => {
        const item = this.inputs[key];
        if (item.kind === "dateRange") {
          const start = item.start.data("kendoDatePicker").value();
          const end = item.end.data("kendoDatePicker").value();
          if (start || end) {
            values.push(this.buildFilterValue(item, "between", { start, end }, this.formatDateRange(start, end)));
          }
          return;
        }

        const editorValue = this.getDynamicEditorValue(item);
        if (editorValue) {
          values.push(this.buildFilterValue(item, editorValue.operator, editorValue.value, editorValue.displayValue));
        }
      });
      return values;
    }

    buildFilterValue(item, operator, value, displayValue) {
      return {
        id: item.filter.id,
        label: item.filter.label,
        field: item.filter.field,
        fields: item.filter.fields,
        type: item.filter.type,
        dataType: item.type,
        operator,
        value,
        displayValue
      };
    }

    getDynamicEditorValue(item) {
      const operator = this.getCurrentOperator(item);
      if (this.operatorHasNoValue(operator)) {
        return {
          operator,
          value: true,
          displayValue: this.getOperatorLabel(operator)
        };
      }

      const editor = item.editor || {};
      if (editor.kind === "text") {
        const value = editor.input.data("kendoTextBox").value();
        return value !== "" && value != null ? { operator, value, displayValue: value } : null;
      }
      if (editor.kind === "numeric") {
        const value = editor.input.data("kendoNumericTextBox").value();
        return value != null ? { operator, value, displayValue: String(value) } : null;
      }
      if (editor.kind === "date") {
        const value = editor.input.data("kendoDatePicker").value();
        return value ? { operator, value, displayValue: kendo.toString(value, "dd/MM/yyyy") } : null;
      }
      if (editor.kind === "datetime") {
        const value = editor.input.data("kendoDateTimePicker").value();
        return value ? { operator, value, displayValue: kendo.toString(value, "dd/MM/yyyy HH:mm") } : null;
      }
      if (editor.kind === "boolean") {
        const widget = editor.input.data("kendoDropDownList");
        const value = widget.value();
        if (value === "") {
          return null;
        }
        return { operator, value: value === "true", displayValue: widget.text() };
      }
      if (editor.kind === "dropdown") {
        const widget = editor.input.data("kendoDropDownList");
        const value = widget.value();
        return value !== "" && value != null ? { operator, value, displayValue: widget.text() } : null;
      }
      if (editor.kind === "multiselect") {
        const widget = editor.input.data("kendoMultiSelect");
        const value = widget.value();
        return value.length ? { operator, value, displayValue: widget.dataItems().map(function(item) { return item.text; }).join(", ") } : null;
      }
      if (editor.kind === "dropdowntree") {
        const widget = editor.input.data("kendoDropDownTree");
        const value = widget.value();
        return value.length ? { operator: "in", value, displayValue: widget.text() } : null;
      }
      if (editor.kind === "checkbox") {
        return editor.input.is(":checked") ? { operator, value: editor.option.value, displayValue: editor.option.text } : null;
      }
      if (editor.kind === "checkboxgroup") {
        const checked = editor.inputs.filter(function(item) {
          return item.input.is(":checked");
        });
        return checked.length ? {
          operator: "in",
          value: checked.map(function(item) { return item.option.value; }),
          displayValue: checked.map(function(item) { return item.option.text; }).join(", ")
        } : null;
      }
      if (editor.kind === "searchWindow") {
        const value = editor.state.selected;
        if (Array.isArray(value)) {
          return value.length ? { operator: "in", value, displayValue: editor.state.selectedText } : null;
        }
        return value != null && value !== "" ? { operator, value, displayValue: editor.state.selectedText } : null;
      }
      if (editor.kind === "list") {
        const value = this.parseListValues(editor.input.data("kendoTextBox").value());
        return value.length ? { operator, value, displayValue: value.join(", ") } : null;
      }
      if (editor.kind === "numericRange") {
        const start = editor.start.data("kendoNumericTextBox").value();
        const end = editor.end.data("kendoNumericTextBox").value();
        return start != null || end != null ? { operator, value: { start, end }, displayValue: this.formatRange(start, end) } : null;
      }
      if (editor.kind === "dateRange") {
        const start = editor.start.data("kendoDatePicker").value();
        const end = editor.end.data("kendoDatePicker").value();
        return start || end ? { operator, value: { start, end }, displayValue: this.formatDateRange(start, end) } : null;
      }
      if (editor.kind === "dateTimeRange") {
        const start = editor.start.data("kendoDateTimePicker").value();
        const end = editor.end.data("kendoDateTimePicker").value();
        return start || end ? { operator, value: { start, end }, displayValue: this.formatDateTimeRange(start, end) } : null;
      }
      if (editor.kind === "dateRangePicker") {
        const range = editor.input.data("kendoDateRangePicker").range() || {};
        return range.start || range.end ? { operator, value: { start: range.start, end: range.end }, displayValue: this.formatDateRange(range.start, range.end) } : null;
      }
      if (editor.kind === "textRange") {
        const start = editor.start.data("kendoTextBox").value();
        const end = editor.end.data("kendoTextBox").value();
        return start || end ? { operator, value: { start, end }, displayValue: this.formatRange(start, end) } : null;
      }
      if (editor.kind === "relativeDate") {
        const value = this.getRelativeDateValue(editor);
        return { operator, value, displayValue: value.label };
      }
      return null;
    }

    parseListValues(value) {
      return String(value || "")
        .split(",")
        .map(function(item) { return item.trim(); })
        .filter(Boolean);
    }

    getRelativeDateValue(editor) {
      const preset = editor.preset.data("kendoDropDownList").value();
      const amount = ["yesterday", "today", "tomorrow"].indexOf(preset) === -1
        ? editor.amount.data("kendoNumericTextBox").value() || 1
        : null;
      const unit = this.getRelativePresetUnit(preset);
      const direction = amount == null ? null : editor.direction.data("kendoDropDownList").value();
      const range = this.resolveRelativeDateRange(preset, amount, unit, direction);
      return {
        mode: preset,
        amount,
        unit,
        direction,
        start: range.start,
        end: range.end,
        label: range.label
      };
    }

    getRelativePresetUnit(preset) {
      const units = {
        days: "day",
        weeks: "week",
        fortnights: "fortnight",
        months: "month",
        years: "year"
      };
      return units[preset] || null;
    }

    resolveRelativeDateRange(preset, amount, unit, direction) {
      const today = this.startOfDay(new Date());
      const endToday = this.endOfDay(today);
      if (preset === "yesterday") {
        const yesterday = this.addDate(today, -1, "day");
        return { start: yesterday, end: this.endOfDay(yesterday), label: "Ontem" };
      }
      if (preset === "today") {
        return { start: today, end: endToday, label: "Hoje" };
      }
      if (preset === "tomorrow") {
        const tomorrow = this.addDate(today, 1, "day");
        return { start: tomorrow, end: this.endOfDay(tomorrow), label: "Amanha" };
      }

      const multiplier = direction === "next" ? 1 : -1;
      const normalizedAmount = amount || 1;
      const normalizedUnit = unit || "day";
      const limit = this.addDate(today, multiplier * normalizedAmount, normalizedUnit);
      const start = direction === "next" ? today : limit;
      const end = direction === "next" ? this.endOfDay(limit) : endToday;
      const unitText = this.getRelativeUnitLabel(normalizedUnit, normalizedAmount);
      const directionText = direction === "next" ? "depois" : "antes";
      return {
        start,
        end,
        label: normalizedAmount + " " + unitText + " " + directionText
      };
    }

    startOfDay(date) {
      const next = new Date(date);
      next.setHours(0, 0, 0, 0);
      return next;
    }

    endOfDay(date) {
      const next = new Date(date);
      next.setHours(23, 59, 59, 999);
      return next;
    }

    addDate(date, amount, unit) {
      const next = new Date(date);
      if (unit === "week") {
        next.setDate(next.getDate() + amount * 7);
      } else if (unit === "fortnight") {
        next.setDate(next.getDate() + amount * 15);
      } else if (unit === "month") {
        next.setMonth(next.getMonth() + amount);
      } else if (unit === "year") {
        next.setFullYear(next.getFullYear() + amount);
      } else {
        next.setDate(next.getDate() + amount);
      }
      return this.startOfDay(next);
    }

    getRelativeUnitLabel(unit, amount) {
      const plural = Number(amount) > 1;
      const labels = {
        day: plural ? "dias" : "dia",
        week: plural ? "semanas" : "semana",
        fortnight: plural ? "quinzenas" : "quinzena",
        month: plural ? "meses" : "mes",
        year: plural ? "anos" : "ano"
      };
      return labels[unit] || unit;
    }

    setValues(filters) {
      this.clear();
      global.CrudUtils.ensureArray(filters).forEach((filter) => {
        const item = this.inputs[filter.id];
        if (item) {
          if (item.kind === "dynamic" && filter.operator) {
            if (item.operatorInput) {
              item.operatorInput.data("kendoDropDownList").value(filter.operator);
            } else {
              item.fixedOperator = filter.operator;
            }
            this.renderDynamicEditor(item);
          }
          this.setInputValue(item, filter.value);
        }
      });
    }

    setInputValue(item, value) {
      if (item.kind === "dateRange") {
        const range = value || {};
        item.start.data("kendoDatePicker").value(global.CrudUtils.normalizeDateValue(range.start));
        item.end.data("kendoDatePicker").value(global.CrudUtils.normalizeDateValue(range.end));
        return;
      }

      const editor = item.editor || {};
      if (editor.kind === "text") {
        editor.input.data("kendoTextBox").value(value == null ? "" : value);
      } else if (editor.kind === "numeric") {
        editor.input.data("kendoNumericTextBox").value(value == null ? null : Number(value));
      } else if (editor.kind === "date") {
        editor.input.data("kendoDatePicker").value(global.CrudUtils.normalizeDateValue(value));
      } else if (editor.kind === "datetime") {
        editor.input.data("kendoDateTimePicker").value(global.CrudUtils.normalizeDateValue(value));
      } else if (editor.kind === "boolean") {
        editor.input.data("kendoDropDownList").value(value == null ? "" : String(Boolean(value)));
      } else if (editor.kind === "dropdown") {
        editor.input.data("kendoDropDownList").value(value == null ? "" : value);
      } else if (editor.kind === "multiselect") {
        editor.input.data("kendoMultiSelect").value(global.CrudUtils.ensureArray(value));
      } else if (editor.kind === "dropdowntree") {
        editor.input.data("kendoDropDownTree").value(global.CrudUtils.ensureArray(value));
      } else if (editor.kind === "checkbox") {
        editor.input.prop("checked", String(editor.option.value) === String(value));
      } else if (editor.kind === "checkboxgroup") {
        const values = global.CrudUtils.ensureArray(value).map(String);
        editor.inputs.forEach(function(option) {
          option.input.prop("checked", values.indexOf(String(option.option.value)) !== -1);
        });
      } else if (editor.kind === "searchWindow") {
        editor.state.selected = Array.isArray(value) ? value.slice() : value;
        editor.state.selectedText = this.formatOptionValues(item.filter, value);
        editor.input.data("kendoTextBox").value(editor.state.selectedText);
      } else if (editor.kind === "list") {
        editor.input.data("kendoTextBox").value(global.CrudUtils.ensureArray(value).join(", "));
      } else if (editor.kind === "numericRange") {
        const range = value || {};
        editor.start.data("kendoNumericTextBox").value(range.start == null ? null : Number(range.start));
        editor.end.data("kendoNumericTextBox").value(range.end == null ? null : Number(range.end));
      } else if (editor.kind === "dateRange") {
        const range = value || {};
        editor.start.data("kendoDatePicker").value(global.CrudUtils.normalizeDateValue(range.start));
        editor.end.data("kendoDatePicker").value(global.CrudUtils.normalizeDateValue(range.end));
      } else if (editor.kind === "dateTimeRange") {
        const range = value || {};
        editor.start.data("kendoDateTimePicker").value(global.CrudUtils.normalizeDateValue(range.start));
        editor.end.data("kendoDateTimePicker").value(global.CrudUtils.normalizeDateValue(range.end));
      } else if (editor.kind === "dateRangePicker") {
        const range = value || {};
        editor.input.data("kendoDateRangePicker").range({
          start: global.CrudUtils.normalizeDateValue(range.start),
          end: global.CrudUtils.normalizeDateValue(range.end)
        });
      } else if (editor.kind === "textRange") {
        const range = value || {};
        editor.start.data("kendoTextBox").value(range.start || "");
        editor.end.data("kendoTextBox").value(range.end || "");
      } else if (editor.kind === "relativeDate") {
        const range = value || {};
        const preset = this.normalizeRelativePreset(range.mode, range.unit);
        editor.preset.data("kendoDropDownList").value(preset);
        editor.amount.data("kendoNumericTextBox").value(range.amount || this.getLegacyRelativeAmount(range.mode) || 1);
        editor.direction.data("kendoDropDownList").value(range.direction || "previous");
        this.updateRelativeDateEditorState(editor);
      }
    }

    normalizeRelativePreset(mode, unit) {
      const legacy = {
        last15Days: "days",
        last1Month: "months",
        last3Months: "months",
        last6Months: "months",
        last1Year: "years",
        custom: this.getRelativePresetByUnit(unit)
      };
      const value = legacy[mode] || mode;
      return ["yesterday", "today", "tomorrow", "days", "weeks", "fortnights", "months", "years"].indexOf(value) === -1
        ? "today"
        : value;
    }

    getLegacyRelativeAmount(mode) {
      const amounts = {
        last15Days: 15,
        last1Month: 1,
        last3Months: 3,
        last6Months: 6,
        last1Year: 1
      };
      return amounts[mode] || null;
    }

    getRelativePresetByUnit(unit) {
      const presets = {
        day: "days",
        week: "weeks",
        fortnight: "fortnights",
        month: "months",
        year: "years"
      };
      return presets[unit] || "days";
    }

    formatOptionValues(filter, value) {
      const values = Array.isArray(value) ? value.map(String) : [String(value)];
      return this.getOptionData(filter).filter(function(option) {
        return values.indexOf(String(option.value)) !== -1;
      }).map(function(option) {
        return option.text;
      }).join(", ");
    }

    formatDateRange(start, end) {
      const startText = start ? kendo.toString(global.CrudUtils.normalizeDateValue(start), "dd/MM/yyyy") : "";
      const endText = end ? kendo.toString(global.CrudUtils.normalizeDateValue(end), "dd/MM/yyyy") : "";
      if (startText && endText) {
        return startText + " ate " + endText;
      }
      if (startText) {
        return "a partir de " + startText;
      }
      return "ate " + endText;
    }

    formatDateTimeRange(start, end) {
      const startText = start ? kendo.toString(global.CrudUtils.normalizeDateValue(start), "dd/MM/yyyy HH:mm") : "";
      const endText = end ? kendo.toString(global.CrudUtils.normalizeDateValue(end), "dd/MM/yyyy HH:mm") : "";
      return this.formatRange(startText, endText);
    }

    formatRange(start, end) {
      if (start != null && start !== "" && end != null && end !== "") {
        return String(start) + " ate " + String(end);
      }
      if (start != null && start !== "") {
        return "a partir de " + String(start);
      }
      return "ate " + String(end);
    }

    clear() {
      Object.keys(this.inputs).forEach((key) => {
        const item = this.inputs[key];
        if (item.kind === "dateRange") {
          item.start.data("kendoDatePicker").value(null);
          item.end.data("kendoDatePicker").value(null);
          return;
        }
        const editor = item.editor || {};
        if (editor.kind === "text" || editor.kind === "list" || editor.kind === "searchWindow") {
          editor.input.data("kendoTextBox").value("");
          if (editor.state) {
            editor.state.selected = editor.multiple ? [] : null;
            editor.state.selectedText = "";
          }
        } else if (editor.kind === "numeric") {
          editor.input.data("kendoNumericTextBox").value(null);
        } else if (editor.kind === "date") {
          editor.input.data("kendoDatePicker").value(null);
        } else if (editor.kind === "datetime") {
          editor.input.data("kendoDateTimePicker").value(null);
        } else if (editor.kind === "boolean" || editor.kind === "dropdown") {
          editor.input.data("kendoDropDownList").value("");
        } else if (editor.kind === "multiselect") {
          editor.input.data("kendoMultiSelect").value([]);
        } else if (editor.kind === "dropdowntree") {
          editor.input.data("kendoDropDownTree").value([]);
        } else if (editor.kind === "checkbox") {
          editor.input.prop("checked", false);
        } else if (editor.kind === "checkboxgroup") {
          editor.inputs.forEach(function(option) {
            option.input.prop("checked", false);
          });
        } else if (editor.kind === "numericRange") {
          editor.start.data("kendoNumericTextBox").value(null);
          editor.end.data("kendoNumericTextBox").value(null);
        } else if (editor.kind === "dateRange") {
          editor.start.data("kendoDatePicker").value(null);
          editor.end.data("kendoDatePicker").value(null);
        } else if (editor.kind === "dateTimeRange") {
          editor.start.data("kendoDateTimePicker").value(null);
          editor.end.data("kendoDateTimePicker").value(null);
        } else if (editor.kind === "dateRangePicker") {
          editor.input.data("kendoDateRangePicker").range({ start: null, end: null });
        } else if (editor.kind === "textRange") {
          editor.start.data("kendoTextBox").value("");
          editor.end.data("kendoTextBox").value("");
        }
      });
    }
  }

  global.CrudFilterRenderer = CrudFilterRenderer;
})(window, jQuery);
