(function(global, $) {
  "use strict";

  class CrudKendoGridRenderer {
    constructor(options) {
      this.definition = options.definition;
      this.httpClient = options.httpClient;
      this.handlers = options.handlers || {};
      this.deferInitialRead = Boolean(options.deferInitialRead);
      this.filters = global.CrudUtils.ensureArray(options.initialFilters);
      this.lastColumnMenuField = null;
    }

    render(container) {
      const panel = $("<section class=\"crud-grid-panel\"></section>").appendTo(container);
      const gridElement = $("<div class=\"crud-grid\"></div>")
        .attr("id", this.definition.grid.id || "crudGrid")
        .appendTo(panel);

      gridElement.kendoGrid(this.buildOptions());
      this.grid = gridElement.data("kendoGrid");
      this.bindRowDoubleClick();
      this.bindHeaderFreezeButtons();
      this.bindColumnMenuTracker();
      this.bindFreezeRefreshEvents();
      this.bindMobileModeChange();
      this.renderHeaderFreezeButtons();
      return this.grid;
    }

    destroy() {
      this.closeRowActionsMenu();
      if (this.grid && this.grid.tbody) {
        this.grid.tbody.off(".crudRowOpen");
      }
      $(document).off(".crudRowAction");
      $(document).off(".crudRowActionItem");
      $(global).off(".crudRowAction");
      this.unbindMobileModeChange();
    }

    buildOptions() {
      const grid = this.definition.grid || {};
      const isMobileTemplate = this.isMobileTemplateMode();
      const options = {
        dataSource: this.buildDataSource(),
        autoBind: !this.deferInitialRead,
        height: grid.height === "auto" ? undefined : grid.height,
        pageable: grid.pageable ? { pageSizes: this.definition.query && this.definition.query.pageSizes || [10, 20, 50] } : false,
        sortable: isMobileTemplate ? Boolean(grid.sortable) : Boolean(grid.sortable),
        filterable: isMobileTemplate ? false : Boolean(grid.filterable),
        groupable: isMobileTemplate ? false : Boolean(grid.groupable),
        resizable: isMobileTemplate ? false : Boolean(grid.resizable),
        reorderable: isMobileTemplate ? false : Boolean(grid.reorderable),
        columnMenu: isMobileTemplate ? false : Boolean(grid.columnMenu),
        columnMenuInit: (event) => this.handleColumnMenuInit(event),
        columnMenuOpen: (event) => this.handleColumnMenuInit(event),
        selectable: this.getSelectableMode(),
        change: () => this.notifySelectionChange(),
        dataBound: () => {
          this.notifySelectionChange();
          this.initializeMobileTemplateTabs();
          this.initializeRowActionButtons();
          this.renderMobileTemplateGroups();
        },
        columns: isMobileTemplate ? this.buildMobileTemplateColumns() : this.buildColumns(),
        noRecords: {
          template: "Nenhum registro encontrado."
        }
      };

      if (isMobileTemplate) {
        options.rowTemplate = (row) => this.buildMobileTemplateRow(row, false);
        options.altRowTemplate = (row) => this.buildMobileTemplateRow(row, true);
      }

      if (this.isGridAiEnabled() && !isMobileTemplate) {
        Object.assign(options, this.buildGridAiOptions());
      }

      if (this.getPrintOptions().length) {
        Object.assign(options, this.buildNativeExportOptions());
      }

      return options;
    }

    buildNativeExportOptions() {
      const config = this.getPrintConfig();
      const fileName = this.getExportFileName(config, "xlsx");
      return {
        excel: Object.assign({
          fileName,
          allPages: true,
          filterable: true
        }, config.excel || {}),
        pdf: Object.assign({
          fileName: this.getExportFileName(config, "pdf"),
          allPages: true,
          avoidLinks: true,
          paperSize: "A4",
          margin: {
            top: "1cm",
            right: "1cm",
            bottom: "1cm",
            left: "1cm"
          },
          landscape: true,
          repeatHeaders: true,
          scale: 0.8
        }, config.pdf || {})
      };
    }

    getPrintConfig() {
      return this.definition.grid && this.definition.grid.print
        ? this.definition.grid.print
        : {};
    }

    getPrintOptions() {
      const config = this.getPrintConfig();
      if (!config || config.enabled === false) {
        return [];
      }
      return global.CrudUtils.ensureArray(config.options || config.formats).map((item) => {
        if (typeof item === "string") {
          return this.normalizePrintOption({ format: item }, config);
        }
        return this.normalizePrintOption(item || {}, config);
      }).filter(function(item) {
        return item && ["excel", "pdf", "csv"].indexOf(item.format) !== -1;
      });
    }

    normalizePrintOption(item, config) {
      const format = String(item.format || item.type || item.id || "").toLowerCase();
      if (!format) {
        return null;
      }
      return Object.assign({}, item, {
        format,
        fileName: item.fileName || config.fileName
      });
    }

    getExportFileName(config, extension) {
      const baseName = String(config && config.fileName || this.definition.id || this.definition.entity || "exportacao")
        .replace(/\.[a-z0-9]+$/i, "")
        .replace(/[\\/:*?"<>|]+/g, "-");
      return baseName + "." + extension;
    }

    export(format, option) {
      const normalizedFormat = String(format || "").toLowerCase();
      const settings = option || {};
      if (!this.grid) {
        return;
      }
      if (normalizedFormat === "excel" && typeof this.grid.saveAsExcel === "function") {
        this.grid.saveAsExcel();
        return;
      }
      if (normalizedFormat === "pdf" && typeof this.grid.saveAsPDF === "function") {
        this.grid.saveAsPDF();
        return;
      }
      if (normalizedFormat === "csv") {
        this.exportCsv(settings);
        return;
      }
      global.CrudUtils.showMessage("Formato de exportacao nao suportado.", "warning");
    }

    exportCsv(option) {
      this.loadAllRowsForExport().then((rows) => {
        const csv = this.buildCsvContent(rows);
        const fileName = this.getExportFileName(Object.assign({}, this.getPrintConfig(), option || {}), "csv");
        const dataURI = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
        if (global.kendo && typeof global.kendo.saveAs === "function") {
          global.kendo.saveAs({
            dataURI,
            fileName
          });
          return;
        }
        const link = $("<a></a>")
          .attr("href", dataURI)
          .attr("download", fileName)
          .appendTo(document.body);
        link[0].click();
        link.remove();
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao exportar CSV.");
        global.CrudUtils.showMessage(normalized.message, "error");
      });
    }

    loadAllRowsForExport() {
      const definition = this.definition;
      const endpoint = definition.api.read;
      const dataSource = this.grid && this.grid.dataSource;
      const total = dataSource && typeof dataSource.total === "function" ? dataSource.total() : 0;
      const take = Math.max(total || 0, dataSource && dataSource.pageSize ? dataSource.pageSize() : 1000, 1000);
      const payload = {
        page: 1,
        skip: 0,
        take,
        pageSize: take,
        sort: dataSource ? dataSource.sort() : [],
        filter: dataSource ? dataSource.filter() : null,
        group: dataSource ? dataSource.group() : [],
        filters: this.filters
      };
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "GET",
        data: payload
      }).then(function(response) {
        return global.CrudUtils.ensureArray(response && response.data);
      });
    }

    buildCsvContent(rows) {
      const columns = this.getExportColumns();
      const header = columns.map((column) => this.escapeCsvValue(column.title || column.field)).join(",");
      const body = global.CrudUtils.ensureArray(rows).map((row) => {
        return columns.map((column) => this.escapeCsvValue(this.formatExportValue(row, column))).join(",");
      });
      return [header].concat(body).join("\r\n");
    }

    getExportColumns() {
      return global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns).filter(function(column) {
        return column && column.field && column.visible !== false;
      });
    }

    formatExportValue(row, column) {
      const field = this.definition.dataModel.fields[column.field] || {};
      const value = row ? row[column.field] : "";
      if (value == null) {
        return "";
      }
      if (field.type === "enum" && field.options) {
        const option = field.options.find(function(item) {
          return String(item.value) === String(value);
        });
        return option ? option.text : value;
      }
      if (field.type === "date") {
        const date = global.CrudUtils.normalizeDateValue(value);
        return date instanceof Date ? kendo.toString(date, "dd/MM/yyyy") : value;
      }
      if (field.type === "datetime") {
        const dateTime = global.CrudUtils.normalizeDateValue(value);
        return dateTime instanceof Date ? kendo.toString(dateTime, "dd/MM/yyyy HH:mm") : value;
      }
      if (column.format === "currency" || field.format === "currency") {
        return kendo.toString(Number(value), "c2");
      }
      return value;
    }

    escapeCsvValue(value) {
      const text = String(value == null ? "" : value);
      if (/[",\r\n]/.test(text)) {
        return "\"" + text.replace(/"/g, "\"\"") + "\"";
      }
      return text;
    }

    bindRowDoubleClick() {
      if (!this.grid || !this.grid.tbody) {
        return;
      }

      this.grid.tbody.off(".crudRowOpen").on("dblclick.crudRowOpen", "tr", (event) => {
        if (!global.CrudUtils.getPermission(this.definition, "read")) {
          return;
        }
        if ($(event.target).closest("button,a,input,textarea,select,.k-button,.k-checkbox,.crud-row-actions-popup,.crud-row-actions-toggle").length) {
          return;
        }

        const dataItem = this.grid.dataItem(event.currentTarget);
        if (!dataItem || typeof this.handlers.view !== "function") {
          return;
        }

        const primaryKey = this.definition.dataModel && this.definition.dataModel.primaryKey;
        const id = primaryKey ? dataItem[primaryKey] : null;
        if (id != null && id !== "") {
          this.handlers.view(id, { source: "rowDoubleClick" });
        }
      });
    }

    getGridAiConfig() {
      return this.definition.grid && this.definition.grid.ai
        ? this.definition.grid.ai
        : {};
    }

    isGridAiEnabled() {
      const ai = this.getGridAiConfig();
      return Boolean(ai && ai.enabled === true);
    }

    buildGridAiOptions() {
      const ai = this.getGridAiConfig();
      const smartBox = this.buildSmartBoxOptions(ai);
      const options = {
        toolbar: [
          {
            name: "smartbox",
            overflow: "never"
          }
        ],
        smartBox
      };
      const service = this.buildAiService(ai);

      if (service) {
        options.ai = {
          service
        };
      }

      return options;
    }

    buildSmartBoxOptions(ai) {
      const promptSuggestions = global.CrudUtils.ensureArray(ai.promptSuggestions)
        .map(function(item) { return String(item || "").trim(); })
        .filter(Boolean);
      const smartBox = {
        activeMode: ai.activeMode || "AIAssistant",
        searchSettings: {
          enabled: ai.searchEnabled !== false,
          searchFields: this.getAiSearchFields(ai)
        },
        aiAssistantSettings: {
          promptSuggestions,
          placeholder: ai.placeholder || "Ordene, filtre ou agrupe com IA"
        },
        messages: {
          searchPlaceholder: ai.searchPlaceholder || "Pesquisar no grid",
          searchButtonText: "Pesquisar",
          aiAssistantPlaceholder: ai.placeholder || "Ordene, filtre ou agrupe com IA",
          aiAssistantButtonText: "IA",
          suggestedPrompts: "Sugestoes",
          previousPrompts: "Perguntas anteriores",
          noPreviousPrompts: "Nenhuma pergunta anterior",
          send: "Enviar",
          cancel: "Cancelar"
        }
      };
      const service = this.buildAiService(ai);

      if (service) {
        smartBox.aiAssistantSettings.service = service;
      } else {
        smartBox.aiAssistantPromptRequest = (event) => this.handleMockAiPrompt(event);
      }

      return smartBox;
    }

    buildAiService(ai) {
      const service = ai.service ? Object.assign({}, ai.service) : null;
      const url = ai.serviceUrl || (service && service.url);

      if (!url) {
        return null;
      }

      return Object.assign(service || {}, {
        url
      });
    }

    getAiSearchFields(ai) {
      const configured = global.CrudUtils.ensureArray(ai.searchFields);
      if (configured.length) {
        return configured.slice();
      }

      return global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) {
          return column.field && column.visible !== false;
        })
        .map(function(column) {
          return column.field;
        });
    }

    handleMockAiPrompt(event) {
      if (event && typeof event.preventDefault === "function") {
        event.preventDefault();
      }

      const prompt = String(
        event && event.prompt
          ? event.prompt
          : this.grid && this.grid._smartBox && typeof this.grid._smartBox.value === "function"
            ? this.grid._smartBox.value()
            : ""
      ).trim();
      const response = this.buildMockAiResponse(prompt);

      if (!response || !response.commands.length) {
        global.CrudUtils.showMessage("Nao consegui interpretar esse comando. Tente: ordenar por maior valor, mostrar ativos, agrupar por status ou limpar filtros.", "warning");
        return;
      }

      this.applyMockAiResponse(response);
      this.closeSmartBox();
    }

    buildMockAiResponse(prompt) {
      const text = this.normalizeAiPrompt(prompt);
      const commands = [];

      if (!text) {
        return { commands };
      }

      if (this.hasAnyAiTerm(text, ["limpar", "remover", "resetar", "padrao"])) {
        if (this.hasAnyAiTerm(text, ["filtro", "filtros"])) {
          commands.push({ type: "GridClearFilter" });
        } else if (this.hasAnyAiTerm(text, ["ordenacao", "ordem", "sort"])) {
          commands.push({ type: "GridClearSort" });
        } else if (this.hasAnyAiTerm(text, ["grupo", "agrupar", "agrupamento"])) {
          commands.push({ type: "GridClearGroup" });
        } else {
          commands.push({ type: "GridClearFilter" }, { type: "GridClearSort" }, { type: "GridClearGroup" });
        }
        return { commands };
      }

      const groupCommand = this.buildAiGroupCommand(text);
      if (groupCommand) {
        commands.push(groupCommand);
        return { commands };
      }

      const filterCommand = this.buildAiFilterCommand(text);
      if (filterCommand) {
        commands.push(filterCommand);
      }

      const sortCommand = this.buildAiSortCommand(text);
      if (sortCommand) {
        commands.push(sortCommand);
      }

      return { commands };
    }

    normalizeAiPrompt(prompt) {
      return String(prompt || "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
    }

    hasAnyAiTerm(text, terms) {
      return terms.some(function(term) {
        return text.indexOf(term) !== -1;
      });
    }

    buildAiGroupCommand(text) {
      if (!this.hasAnyAiTerm(text, ["agrupar", "grupo", "agrupamento", "group"])) {
        return null;
      }
      if (this.hasAnyAiTerm(text, ["status", "situacao"])) {
        return { type: "GridGroup", group: { field: "status", dir: "asc" } };
      }
      if (this.hasAnyAiTerm(text, ["cadastro", "data"])) {
        return { type: "GridGroup", group: { field: "data_cadastro", dir: "desc" } };
      }
      return null;
    }

    buildAiFilterCommand(text) {
      const numberFilter = this.buildAiNumberFilter(text);

      if (numberFilter) {
        return numberFilter;
      }
      if (text.indexOf("inativo") !== -1 || text.indexOf("inativos") !== -1) {
        return {
          type: "GridFilter",
          filter: { field: "status", operator: "eq", value: "INATIVO" }
        };
      }
      if (text.indexOf("ativo") !== -1 || text.indexOf("ativos") !== -1) {
        return {
          type: "GridFilter",
          filter: { field: "status", operator: "eq", value: "ATIVO" }
        };
      }
      return null;
    }

    buildAiNumberFilter(text) {
      const value = this.extractAiNumber(text);

      if (value == null) {
        return null;
      }

      let operator = null;
      if (this.hasAnyAiTerm(text, ["maior", "acima", "mais que"])) {
        operator = "gte";
      } else if (this.hasAnyAiTerm(text, ["menor", "abaixo", "menos que"])) {
        operator = "lte";
      }

      if (!operator) {
        return null;
      }

      if (this.hasAnyAiTerm(text, ["pedido", "pedidos", "quantidade"])) {
        return {
          type: "GridFilter",
          filter: { field: "qtde_pedidos", operator, value }
        };
      }
      if (this.hasAnyAiTerm(text, ["valor", "total", "faturamento", "receita"])) {
        return {
          type: "GridFilter",
          filter: { field: "valor_total", operator, value }
        };
      }

      return null;
    }

    extractAiNumber(text) {
      const match = text.match(/(\d+(?:[.,]\d+)?)/);
      if (!match) {
        return null;
      }
      const value = Number(match[1].replace(",", "."));
      return Number.isFinite(value) ? value : null;
    }

    buildAiSortCommand(text) {
      if (this.hasAnyAiTerm(text, ["valor", "total", "faturamento", "receita"])) {
        return {
          type: "GridSort",
          sort: {
            field: "valor_total",
            dir: this.hasAnyAiTerm(text, ["menor", "crescente", "asc"]) ? "asc" : "desc"
          }
        };
      }
      if (this.hasAnyAiTerm(text, ["pedido", "pedidos", "quantidade"])) {
        return {
          type: "GridSort",
          sort: {
            field: "qtde_pedidos",
            dir: this.hasAnyAiTerm(text, ["menor", "crescente", "asc"]) ? "asc" : "desc"
          }
        };
      }
      if (this.hasAnyAiTerm(text, ["cadastro", "data", "recente", "antigo"])) {
        return {
          type: "GridSort",
          sort: {
            field: "data_cadastro",
            dir: this.hasAnyAiTerm(text, ["antigo", "crescente", "asc"]) ? "asc" : "desc"
          }
        };
      }
      if (this.hasAnyAiTerm(text, ["nome", "alfabetica", "alfabetico"])) {
        return {
          type: "GridSort",
          sort: {
            field: "nome",
            dir: this.hasAnyAiTerm(text, ["z a", "za", "desc"]) ? "desc" : "asc"
          }
        };
      }
      return null;
    }

    applyMockAiResponse(response) {
      if (!this.grid || !this.grid.dataSource) {
        return;
      }

      global.CrudUtils.ensureArray(response.commands).forEach((command) => {
        if (command.type === "GridSort" && command.sort) {
          this.grid.dataSource.sort([command.sort]);
        } else if (command.type === "GridClearSort") {
          this.grid.dataSource.sort([]);
        } else if (command.type === "GridFilter" && command.filter) {
          this.grid.dataSource.filter({
            logic: "and",
            filters: [command.filter]
          });
        } else if (command.type === "GridClearFilter") {
          this.grid.dataSource.filter(null);
        } else if (command.type === "GridGroup" && command.group) {
          this.setGroup([command.group], this.getCurrentGroupAggregates());
        } else if (command.type === "GridClearGroup") {
          this.setGroup([], []);
        }
      });

      if (this.grid.dataSource.page() !== 1) {
        this.grid.dataSource.page(1);
      }
    }

    closeSmartBox() {
      const smartBox = this.grid && this.grid._smartBox;

      if (!smartBox) {
        return;
      }
      if (typeof smartBox.value === "function") {
        smartBox.value("");
      }
      if (typeof smartBox.close === "function") {
        smartBox.close();
      }
    }

    buildDataSource() {
      const definition = this.definition;
      const endpoint = definition.api.read;
      const isMobileTemplate = this.isMobileTemplateMode();
      const initialGroup = this.getInitialGroup();
      const initialSort = this.getInitialSort();
      if (isMobileTemplate) {
        this.mobileTemplateGroupDescriptors = initialGroup;
      }
      return new kendo.data.DataSource({
        transport: {
          read: (options) => {
            const payload = Object.assign({}, options.data || {}, {
              filters: this.filters
            });
            this.httpClient.request({
              url: endpoint.url,
              method: endpoint.method || "GET",
              data: payload
            }).then((response) => {
              options.success(response);
            }).catch(options.error);
          }
        },
        schema: {
          data: "data",
          total: "total",
          model: this.buildModel()
        },
        serverPaging: true,
        serverSorting: true,
        serverFiltering: true,
        pageSize: definition.query && definition.query.defaultPageSize || 20,
        aggregate: this.getInitialGroupAggregates(),
        sort: isMobileTemplate ? this.mergeGroupSortWithSort(initialGroup, initialSort) : initialSort,
        filter: this.getInitialFilter(),
        group: isMobileTemplate ? [] : initialGroup
      });
    }

    buildModel() {
      const modelFields = {};
      Object.keys(this.definition.dataModel.fields).forEach((fieldName) => {
        const field = this.definition.dataModel.fields[fieldName];
        modelFields[fieldName] = {
          type: this.toKendoType(field.type),
          editable: Boolean(field.editable)
        };
      });
      return {
        id: this.definition.dataModel.primaryKey,
        fields: modelFields
      };
    }

    toKendoType(type) {
      if (type === "integer" || type === "decimal" || type === "number") {
        return "number";
      }
      if (type === "date" || type === "datetime") {
        return "date";
      }
      if (type === "boolean") {
        return "boolean";
      }
      return "string";
    }

    buildColumns() {
      let columns = global.CrudUtils.ensureArray(this.definition.grid.columns).map((column) => {
        const field = this.definition.dataModel.fields[column.field] || column;
        const kendoColumn = {
          field: column.field,
          title: column.title,
          width: column.width,
          hidden: column.visible === false,
          filterable: column.filterable !== false,
          sortable: column.sortable !== false,
          groupable: column.groupable !== false,
          format: global.CrudUtils.toKendoFormat(column.format || field.format, field.type),
          groupHeaderTemplate: (data) => this.buildGroupHeaderTemplate(column, data),
          attributes: column.align ? { style: "text-align:" + column.align } : undefined,
          headerAttributes: column.align ? { style: "text-align:" + column.align } : undefined
        };

        if (this.isColumnFreezeEnabled()) {
          kendoColumn.lockable = true;
        }

        if (field.type === "enum" && field.options) {
          kendoColumn.template = (row) => {
            const option = field.options.find(function(item) { return item.value === row[column.field]; });
            return global.CrudUtils.escapeHtml(option ? option.text : row[column.field]);
          };
        }

        return kendoColumn;
      });

      columns = this.applyMobileColumnConfig(columns);
      columns = this.applyUserLayout(columns);

      if (this.isBulkActionsEnabled()) {
        columns.unshift({
          selectable: true,
          width: 44,
          locked: this.getFrozenFields().length > 0,
          sortable: false,
          filterable: false,
          menu: false,
          lockable: false
        });
      }

      const actions = this.buildActionColumn();
      if (actions) {
        columns.unshift(actions);
      }
      return columns;
    }

    getMobileConfig() {
      return this.definition.grid && this.definition.grid.mobile
        ? this.definition.grid.mobile
        : {};
    }

    isMobileConfigEnabled() {
      const mobile = this.getMobileConfig();
      return mobile.enabled !== false && Boolean(mobile.mode);
    }

    getMobileBreakpoint() {
      const breakpoint = Number(this.getMobileConfig().breakpoint || 720);
      return Number.isFinite(breakpoint) && breakpoint > 0 ? breakpoint : 720;
    }

    isMobileViewport() {
      const breakpoint = this.getMobileBreakpoint();
      return global.matchMedia
        ? global.matchMedia("(max-width: " + breakpoint + "px)").matches
        : global.innerWidth <= breakpoint;
    }

    isMobileTemplateMode() {
      const mobile = this.getMobileConfig();
      return this.isMobileConfigEnabled() && mobile.mode === "template" && this.isMobileViewport();
    }

    bindMobileModeChange() {
      const mobile = this.getMobileConfig();
      if (!global.matchMedia || !this.isMobileConfigEnabled() || mobile.mode !== "template") {
        return;
      }
      this.mobileMediaQuery = global.matchMedia("(max-width: " + this.getMobileBreakpoint() + "px)");
      this.mobileMediaHandler = () => {
        if (this.handlers.mobileModeChange) {
          this.handlers.mobileModeChange();
        }
      };
      if (this.mobileMediaQuery.addEventListener) {
        this.mobileMediaQuery.addEventListener("change", this.mobileMediaHandler);
      } else if (this.mobileMediaQuery.addListener) {
        this.mobileMediaQuery.addListener(this.mobileMediaHandler);
      }
    }

    unbindMobileModeChange() {
      if (!this.mobileMediaQuery || !this.mobileMediaHandler) {
        return;
      }
      if (this.mobileMediaQuery.removeEventListener) {
        this.mobileMediaQuery.removeEventListener("change", this.mobileMediaHandler);
      } else if (this.mobileMediaQuery.removeListener) {
        this.mobileMediaQuery.removeListener(this.mobileMediaHandler);
      }
      this.mobileMediaQuery = null;
      this.mobileMediaHandler = null;
    }

    isMobileColumnsMode() {
      const mobile = this.getMobileConfig();
      return this.isMobileConfigEnabled() && mobile.mode === "columns";
    }

    applyMobileColumnConfig(columns) {
      if (!this.isMobileColumnsMode()) {
        return columns;
      }

      const fields = global.CrudUtils.ensureArray(this.getMobileConfig().columns);
      if (!fields.length) {
        return columns;
      }

      const breakpoint = this.getMobileBreakpoint();
      const desktopMedia = "(min-width: " + (breakpoint + 1) + "px)";
      return columns.map(function(column) {
        if (!column.field || fields.indexOf(column.field) !== -1) {
          return column;
        }
        return Object.assign({}, column, {
          media: desktopMedia
        });
      });
    }

    buildMobileTemplateColumns() {
      const mobile = this.getMobileConfig();
      return [{
        title: mobile.title || this.definition.title || "Registros",
        sortable: false,
        filterable: false,
        menu: false
      }];
    }

    buildMobileTemplateRow(row, alt) {
      const data = typeof row.toJSON === "function" ? row.toJSON() : row;
      const template = this.getMobileTemplateConfig();
      const titleField = template.titleField || this.getFirstVisibleField();
      const subtitleField = template.subtitleField;
      const title = this.formatFieldValue(titleField, data);
      const subtitle = subtitleField ? this.formatFieldValue(subtitleField, data) : "";
      const badges = global.CrudUtils.ensureArray(template.badges).map((fieldName) => {
        return "<span class=\"crud-mobile-card-badge\">" + this.formatFieldValue(fieldName, data) + "</span>";
      }).join("");
      const content = this.buildMobileTemplateContent(data, template);
      const actions = this.buildMobileCardActionsHtml(data);
      const className = "crud-mobile-template-row k-table-row" + (alt ? " k-alt" : "");

      return "<tr class=\"" + className + "\" data-uid=\"" + global.CrudUtils.escapeHtml(row.uid || "") + "\">" +
        "<td class=\"crud-mobile-template-cell k-table-td\">" +
          "<article class=\"crud-mobile-card\">" +
            "<header class=\"crud-mobile-card-header\">" +
              "<div class=\"crud-mobile-card-title-group\">" +
                "<strong class=\"crud-mobile-card-title\">" + title + "</strong>" +
                (subtitle ? "<span class=\"crud-mobile-card-subtitle\">" + subtitle + "</span>" : "") +
              "</div>" +
              (badges ? "<div class=\"crud-mobile-card-badges\">" + badges + "</div>" : "") +
            "</header>" +
            content +
            actions +
          "</article>" +
        "</td>" +
      "</tr>";
    }

    buildMobileTemplateContent(data, template) {
      const tabs = template.tabs || {};
      const tabItems = global.CrudUtils.ensureArray(tabs.items);
      if (tabs.enabled && tabItems.length) {
        return "<div class=\"crud-mobile-template-tabs\">" +
          "<ul>" + tabItems.map(function(tab) {
            return "<li>" + global.CrudUtils.escapeHtml(tab.title || tab.id || "Aba") + "</li>";
          }).join("") + "</ul>" +
          tabItems.map((tab) => {
            return "<div>" + this.buildMobileFieldList(data, tab.fields) + "</div>";
          }).join("") +
        "</div>";
      }

      return this.buildMobileFieldList(data, template.fields);
    }

    buildMobileFieldList(data, fields) {
      const configuredFields = global.CrudUtils.ensureArray(fields);
      const fieldNames = configuredFields.length ? configuredFields : this.getDefaultMobileTemplateFields();
      return "<dl class=\"crud-mobile-field-list\">" + fieldNames.map((fieldName) => {
        return "<div class=\"crud-mobile-field-item\">" +
          "<dt>" + global.CrudUtils.escapeHtml(this.getFieldLabel(fieldName)) + "</dt>" +
          "<dd>" + this.formatFieldValue(fieldName, data) + "</dd>" +
        "</div>";
      }).join("") + "</dl>";
    }

    getMobileTemplateConfig() {
      const base = Object.assign({}, this.getMobileConfig().template || {});
      const userLayout = this.definition.userLayout || {};
      const gridTemplate = userLayout.grid && userLayout.grid.mobileTemplate;
      const templates = global.CrudUtils.ensureArray(userLayout.savedMobileTemplates);
      const activeTemplate = templates.find(function(item) {
        return item.isDefault;
      }) || templates.find(function(item) {
        return item.id === userLayout.activeMobileTemplateId;
      });
      const source = gridTemplate || activeTemplate && (activeTemplate.template || activeTemplate) || null;
      if (!source) {
        return base;
      }

      const normalized = this.normalizeMobileTemplateConfig(source);
      return Object.assign(base, normalized);
    }

    normalizeMobileTemplateConfig(template) {
      const source = template || {};
      const normalized = {};
      const titleField = this.normalizeMobileTemplateField(source.titleField);
      const subtitleField = this.normalizeMobileTemplateField(source.subtitleField);
      const badges = this.normalizeMobileTemplateFields(source.badges || source.badgeFields || []);
      const fields = this.normalizeMobileTemplateFields(source.fields || source.fieldPositions || []);
      const tabs = this.normalizeMobileTemplateTabs(source.tabs || {});

      if (titleField) {
        normalized.titleField = titleField;
      }
      if (subtitleField) {
        normalized.subtitleField = subtitleField;
      }
      if (badges.length) {
        normalized.badges = badges;
      }
      if (fields.length) {
        normalized.fields = fields;
      }
      if (tabs.items.length) {
        normalized.tabs = tabs;
      }

      return normalized;
    }

    normalizeMobileTemplateTabs(tabs) {
      const source = tabs || {};
      const items = global.CrudUtils.ensureArray(source.items).map((item) => {
        const fields = this.normalizeMobileTemplateFields(item && item.fields || []);
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

    normalizeMobileTemplateFields(fields) {
      const known = {};
      return global.CrudUtils.ensureArray(fields).map((fieldName) => {
        return this.normalizeMobileTemplateField(fieldName);
      }).filter(function(fieldName) {
        if (!fieldName || known[fieldName]) {
          return false;
        }
        known[fieldName] = true;
        return true;
      });
    }

    normalizeMobileTemplateField(fieldName) {
      const value = String(fieldName || "").trim();
      return /^[A-Za-z_][A-Za-z0-9_]*$/.test(value) ? value : "";
    }

    initializeMobileTemplateTabs() {
      if (!this.isMobileTemplateMode() || !this.grid) {
        return;
      }
      this.grid.element.find(".crud-mobile-template-tabs").each(function() {
        const element = $(this);
        if (element.data("crudTabsReady")) {
          return;
        }
        element.data("crudTabsReady", true);
        element.kendoTabStrip({
          animation: false
        });
        const widget = element.data("kendoTabStrip");
        if (widget) {
          widget.select(0);
        }
      });
    }

    renderMobileTemplateGroups() {
      if (!this.isMobileTemplateMode() || !this.grid || !this.grid.tbody) {
        return;
      }

      this.grid.tbody.find(".crud-mobile-group-row").remove();
      const groups = global.CrudUtils.ensureArray(this.mobileTemplateGroupDescriptors);
      if (!groups.length) {
        return;
      }

      let previousKey = null;
      this.grid.tbody.children("tr.crud-mobile-template-row").each((_, row) => {
        const dataItem = this.grid.dataItem(row);
        if (!dataItem) {
          return;
        }

        const key = this.buildMobileGroupKey(dataItem, groups);
        if (key === previousKey) {
          return;
        }
        previousKey = key;

        $("<tr class=\"crud-mobile-group-row k-table-row\"></tr>")
          .append(
            $("<td class=\"crud-mobile-group-cell k-table-td\" colspan=\"1\"></td>")
              .append(
                $("<span class=\"crud-mobile-group-title\"></span>")
                  .text(this.buildMobileGroupLabel(dataItem, groups))
              )
          )
          .insertBefore(row);
      });
    }

    buildMobileGroupKey(dataItem, groups) {
      return groups.map(function(group) {
        return dataItem[group.field];
      }).join("\u001f");
    }

    buildMobileGroupLabel(dataItem, groups) {
      return groups.map((group) => {
        const label = this.getFieldLabel(group.field);
        const value = this.formatColumnValue(group.field, dataItem[group.field]);
        return label + ": " + value;
      }).join(" / ");
    }

    getFirstVisibleField() {
      const column = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns).find(function(item) {
        return item.field && item.visible !== false;
      });
      return column ? column.field : this.definition.dataModel.primaryKey;
    }

    getDefaultMobileTemplateFields() {
      return global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) {
          return column.field && column.visible !== false;
        })
        .map(function(column) {
          return column.field;
        });
    }

    getFieldLabel(fieldName) {
      const column = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns).find(function(item) {
        return item.field === fieldName;
      });
      if (column && column.title) {
        return column.title;
      }
      return global.CrudUtils.buildFieldLabel(this.definition, fieldName);
    }

    formatFieldValue(fieldName, row) {
      const field = this.definition.dataModel.fields[fieldName] || {};
      const value = row ? row[fieldName] : null;
      if (value == null || value === "") {
        return "";
      }
      if (field.type === "enum" && field.options) {
        const option = field.options.find(function(item) {
          return String(item.value) === String(value);
        });
        return global.CrudUtils.escapeHtml(option ? option.text : value);
      }
      if (field.format === "currency") {
        return global.CrudUtils.escapeHtml(kendo.toString(Number(value), "c2"));
      }
      if (field.type === "date") {
        return global.CrudUtils.escapeHtml(kendo.toString(global.CrudUtils.normalizeDateValue(value), "dd/MM/yyyy"));
      }
      if (field.type === "datetime") {
        return global.CrudUtils.escapeHtml(kendo.toString(global.CrudUtils.normalizeDateValue(value), "dd/MM/yyyy HH:mm"));
      }
      if (field.type === "boolean") {
        return value ? "Sim" : "Nao";
      }
      if (field.type === "integer" || field.type === "decimal" || field.type === "number") {
        return global.CrudUtils.escapeHtml(kendo.toString(Number(value), field.type === "integer" ? "n0" : "n2"));
      }
      return global.CrudUtils.escapeHtml(value);
    }

    isBulkActionsEnabled() {
      return this.getBulkActions().length > 0;
    }

    getBulkActions() {
      const config = this.definition.grid && this.definition.grid.bulkActions;
      if (!config || config.enabled === false || !Array.isArray(config.actions) || !config.actions.length) {
        return [];
      }
      return config.actions.filter((item) => {
        return item && item.id && item.label && item.action && global.CrudUtils.getPermission(this.definition, item.permission);
      });
    }

    getSelectableMode() {
      const grid = this.definition.grid || {};
      if (this.isBulkActionsEnabled()) {
        return grid.bulkActions.selectable || "multiple, row";
      }
      return grid.selectable || false;
    }

    isColumnFreezeEnabled() {
      const config = this.definition.grid && this.definition.grid.freezeColumns;
      return Boolean(config && config.enabled);
    }

    getFrozenFields() {
      if (!this.isColumnFreezeEnabled() || !this.isDesktopViewport()) {
        return [];
      }

      if (Array.isArray(this.frozenFieldsState)) {
        return this.normalizeFrozenFields(this.frozenFieldsState);
      }

      const saved = this.definition.userLayout && this.definition.userLayout.grid && this.definition.userLayout.grid.columns
        ? this.definition.userLayout.grid.columns.frozen
        : null;
      if (Array.isArray(saved)) {
        this.frozenFieldsState = this.normalizeFrozenFields(saved);
        return this.frozenFieldsState.slice();
      }

      this.frozenFieldsState = [];
      return this.frozenFieldsState.slice();
    }

    normalizeFrozenFields(fields) {
      const allowed = this.getFreezableFieldNames();
      const seen = {};
      const normalized = global.CrudUtils.ensureArray(fields).filter(function(fieldName) {
        if (!fieldName || seen[fieldName] || allowed.indexOf(fieldName) === -1) {
          return false;
        }
        seen[fieldName] = true;
        return true;
      });

      if (allowed.length > 1 && normalized.length >= allowed.length) {
        return [];
      }

      return normalized;
    }

    getFreezableFieldNames() {
      const configured = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.freezeColumns && this.definition.grid.freezeColumns.fields);
      const visible = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) {
          return column.field && column.visible !== false;
        })
        .map(function(column) {
          return column.field;
        });

      if (!configured.length) {
        return visible;
      }
      return visible.filter(function(fieldName) {
        return configured.indexOf(fieldName) !== -1;
      });
    }

    isFieldFrozen(fieldName) {
      return this.getFrozenFields().indexOf(fieldName) !== -1;
    }

    isDesktopViewport() {
      return global.matchMedia ? global.matchMedia("(min-width: 1024px)").matches : global.innerWidth >= 1024;
    }

    handleColumnMenuInit(event) {
      const fieldName = this.resolveColumnMenuField(event);
      if (!this.canShowColumnFreezeMenu(fieldName)) {
        return;
      }

      const container = $(event.container);
      container.find(".crud-column-menu-freeze").remove();

      const column = this.findGridColumn(fieldName);
      if (!column) {
        return;
      }

      const isLocked = this.isFieldFrozen(fieldName);
      const action = $("<div class=\"crud-column-menu-freeze\"></div>");
      const button = $("<button type=\"button\"></button>")
        .text(isLocked ? "Descongelar coluna" : "Congelar coluna")
        .appendTo(action);

      button.kendoButton({
        icon: isLocked ? "lock" : "unlock"
      });
      button.on("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        this.toggleColumnFreeze(fieldName);
        this.closeColumnMenu(container);
      });

      this.getColumnMenuInsertionTarget(container).append(action);
    }

    resolveColumnMenuField(event) {
      if (!event) {
        return null;
      }
      if (event.field) {
        return event.field;
      }
      if (event.column && event.column.field) {
        return event.column.field;
      }
      const container = event.container ? $(event.container) : $();
      return container.attr("data-field") ||
        container.find("[data-field]").first().attr("data-field") ||
        this.lastColumnMenuField;
    }

    getColumnMenuInsertionTarget(container) {
      const content = container.find(".k-columnmenu, .k-column-menu, .k-menu, .k-popup").first();
      return content.length ? content : container;
    }

    canShowColumnFreezeMenu(fieldName) {
      return Boolean(
        fieldName &&
        this.isColumnFreezeEnabled() &&
        this.isDesktopViewport() &&
        this.getFreezableFieldNames().indexOf(fieldName) !== -1
      );
    }

    findGridColumn(fieldName) {
      if (!this.grid) {
        return null;
      }
      return global.CrudUtils.ensureArray(this.grid.columns).find(function(column) {
        return column.field === fieldName;
      });
    }

    bindHeaderFreezeButtons() {
      if (!this.grid) {
        return;
      }
      this.grid.wrapper
        .off(".crudFreezeHeader")
        .on("pointerdown.crudFreezeHeader mousedown.crudFreezeHeader touchstart.crudFreezeHeader dblclick.crudFreezeHeader keydown.crudFreezeHeader", ".crud-freeze-header-button", (event) => {
          event.preventDefault();
          event.stopPropagation();
          event.stopImmediatePropagation();
        })
        .on("click.crudFreezeHeader", ".crud-freeze-header-button", (event) => {
          event.preventDefault();
          event.stopPropagation();
          event.stopImmediatePropagation();
          this.toggleColumnFreeze($(event.currentTarget).attr("data-field"));
        });
    }

    bindColumnMenuTracker() {
      if (!this.grid) {
        return;
      }
      this.grid.wrapper.off("click.crudColumnMenuTracker").on("click.crudColumnMenuTracker", ".k-grid-column-menu", (event) => {
        this.lastColumnMenuField = $(event.currentTarget).closest("th").attr("data-field") || null;
      });
    }

    bindFreezeRefreshEvents() {
      if (!this.grid || this.freezeRefreshBound) {
        return;
      }
      this.freezeRefreshBound = true;
      const refresh = () => {
        global.setTimeout(() => this.renderHeaderFreezeButtons(), 0);
      };
      ["dataBound", "columnLock", "columnUnlock", "columnReorder", "columnShow", "columnHide", "columnResize"].forEach((eventName) => {
        this.grid.bind(eventName, refresh);
      });
    }

    renderHeaderFreezeButtons() {
      if (!this.grid) {
        return;
      }

      const headers = this.getHeaderCells();
      headers.find(".crud-freeze-header-button").remove();

      if (!this.isColumnFreezeEnabled() || !this.isDesktopViewport()) {
        return;
      }

      headers.each((index, header) => {
        const headerCell = $(header);
        const fieldName = headerCell.attr("data-field");
        const column = this.findGridColumn(fieldName);
        if (!fieldName || !column || column.command || column.selectable || column.draggable) {
          return;
        }

        const isLocked = this.isFieldFrozen(fieldName);
        const label = isLocked
          ? "Coluna congelada. Clique para descongelar (somente desktop)"
          : "Coluna descongelada. Clique para congelar (somente desktop)";
        const button = $("<button type=\"button\" class=\"crud-freeze-header-button\"></button>")
          .attr("data-field", fieldName)
          .attr("title", label)
          .attr("aria-label", label);

        const target = headerCell.find(".k-cell-inner").first();
        if (target.length) {
          button.appendTo(target);
        } else {
          button.appendTo(headerCell);
        }

        button.kendoButton({
          icon: isLocked ? "lock" : "unlock",
          fillMode: "flat",
          size: "small"
        });
        this.blockHeaderFreezeButtonEvents(button, fieldName);
        button
          .on("click.crudFreezeHeaderButton", (event) => {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            this.toggleColumnFreeze(fieldName);
          })
          .on("mousedown.crudFreezeHeaderButton pointerdown.crudFreezeHeaderButton touchstart.crudFreezeHeaderButton keydown.crudFreezeHeaderButton", function(event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
          });
      });
    }

    blockHeaderFreezeButtonEvents(button, fieldName) {
      const element = button && button[0];
      if (!element || element.dataset.crudFreezeNativeBound === "true") {
        return;
      }
      element.dataset.crudFreezeNativeBound = "true";

      const block = function(event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
      };
      ["pointerdown", "mousedown", "touchstart", "dblclick", "keydown"].forEach(function(eventName) {
        element.addEventListener(eventName, block, true);
      });
      element.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        this.toggleColumnFreeze(fieldName);
      }, true);
    }

    getHeaderCells() {
      if (!this.grid) {
        return $();
      }

      let headers = this.grid.thead ? this.grid.thead.find("th[data-field]") : $();
      if (this.grid.lockedHeader && this.grid.lockedHeader.length) {
        headers = headers.add(this.grid.lockedHeader.find("th[data-field]"));
      }
      return headers;
    }

    toggleColumnFreeze(fieldName) {
      const column = this.findGridColumn(fieldName);
      if (!column || !this.grid) {
        return;
      }

      const frozen = this.getFrozenFields();
      this.rememberUnfrozenColumnOrder(frozen);
      const index = frozen.indexOf(fieldName);
      const next = index === -1
        ? frozen.concat([fieldName])
        : frozen.filter(function(item) { return item !== fieldName; });

      this.setFrozenFields(next);
      this.applyFrozenFieldsToGrid();
      if (this.handlers.layoutDirty) {
        this.handlers.layoutDirty();
      }
      global.setTimeout(() => this.renderHeaderFreezeButtons(), 0);
    }

    setFrozenFields(fields) {
      this.frozenFieldsState = this.normalizeFrozenFields(fields);
      if (!this.definition.userLayout) {
        this.definition.userLayout = {};
      }
      if (!this.definition.userLayout.grid) {
        this.definition.userLayout.grid = {};
      }
      if (!this.definition.userLayout.grid.columns) {
        this.definition.userLayout.grid.columns = {};
      }
      this.definition.userLayout.grid.columns.frozen = this.frozenFieldsState.slice();
    }

    applyFrozenFieldsToGrid() {
      if (!this.grid) {
        return;
      }

      const frozen = this.getFrozenFields();
      const options = this.grid.getOptions();
      const baseOrder = this.unfrozenColumnOrder || this.getConfiguredUnfrozenColumnOrder(options.columns);
      let columns = this.sortColumnsByIdentity(global.CrudUtils.ensureArray(options.columns), baseOrder).map(function(column) {
        const next = Object.assign({}, column);
        if (next.field) {
          next.locked = frozen.indexOf(next.field) !== -1;
        } else if (next.selectable || next.title === "Acoes") {
          next.locked = frozen.length > 0;
        }
        return next;
      });

      columns.sort(function(left, right) {
        if (left.locked === right.locked) {
          return 0;
        }
        return left.locked ? -1 : 1;
      });

      if (!frozen.length) {
        columns = this.sortColumnsByIdentity(columns, baseOrder);
        this.unfrozenColumnOrder = this.captureColumnIdentityOrder(columns);
      }

      this.grid.setOptions({ columns });
      this.bindHeaderFreezeButtons();
      this.bindColumnMenuTracker();
      this.bindFreezeRefreshEvents();
    }

    rememberUnfrozenColumnOrder(currentFrozen) {
      if (!this.grid) {
        return;
      }
      const columns = global.CrudUtils.ensureArray(this.grid.getOptions().columns);
      if (!global.CrudUtils.ensureArray(currentFrozen).length) {
        this.unfrozenColumnOrder = this.captureColumnIdentityOrder(columns);
        return;
      }
      if (!this.unfrozenColumnOrder || !this.unfrozenColumnOrder.length) {
        this.unfrozenColumnOrder = this.getConfiguredUnfrozenColumnOrder(columns);
      }
    }

    captureColumnIdentityOrder(columns) {
      return global.CrudUtils.ensureArray(columns).map((column) => this.getColumnIdentity(column)).filter(Boolean);
    }

    getConfiguredUnfrozenColumnOrder(columns) {
      const sourceColumns = global.CrudUtils.ensureArray(columns);
      const identities = [];
      const known = {};

      sourceColumns.forEach((column) => {
        if (!column.field) {
          const identity = this.getColumnIdentity(column);
          if (identity && !known[identity]) {
            known[identity] = true;
            identities.push(identity);
          }
        }
      });

      this.getConfiguredFieldOrder(sourceColumns).forEach(function(fieldName) {
        const identity = "field:" + fieldName;
        if (!known[identity]) {
          known[identity] = true;
          identities.push(identity);
        }
      });

      sourceColumns.forEach((column) => {
        const identity = this.getColumnIdentity(column);
        if (identity && !known[identity]) {
          known[identity] = true;
          identities.push(identity);
        }
      });

      return identities;
    }

    getConfiguredFieldOrder(columns) {
      const available = global.CrudUtils.ensureArray(columns)
        .filter(function(column) { return column.field; })
        .map(function(column) { return column.field; });
      const known = {};
      const configured = this.definition.userLayout && this.definition.userLayout.grid && this.definition.userLayout.grid.columns
        ? global.CrudUtils.ensureArray(this.definition.userLayout.grid.columns.order)
        : [];
      const fallback = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .map(function(column) { return column.field; })
        .filter(Boolean);

      return configured.concat(fallback).concat(available).filter(function(fieldName) {
        if (!fieldName || known[fieldName] || available.indexOf(fieldName) === -1) {
          return false;
        }
        known[fieldName] = true;
        return true;
      });
    }

    sortColumnsByIdentity(columns, order) {
      const indexByIdentity = global.CrudUtils.ensureArray(order).reduce(function(acc, identity, index) {
        if (acc[identity] == null) {
          acc[identity] = index;
        }
        return acc;
      }, {});

      return global.CrudUtils.ensureArray(columns).map((column, index) => {
        return {
          column,
          index,
          order: indexByIdentity[this.getColumnIdentity(column)]
        };
      }).sort(function(left, right) {
        const leftOrder = left.order == null ? Number.MAX_SAFE_INTEGER : left.order;
        const rightOrder = right.order == null ? Number.MAX_SAFE_INTEGER : right.order;
        if (leftOrder === rightOrder) {
          return left.index - right.index;
        }
        return leftOrder - rightOrder;
      }).map(function(item) {
        return item.column;
      });
    }

    getColumnIdentity(column) {
      if (!column) {
        return "";
      }
      if (column.field) {
        return "field:" + column.field;
      }
      if (column.selectable) {
        return "selectable";
      }
      if (column.title === "Acoes") {
        return "actions";
      }
      return column.title ? "title:" + column.title : "";
    }

    closeColumnMenu(container) {
      const popup = container.closest(".k-popup");
      const animationContainer = popup.closest(".k-animation-container");
      const popupWidget = container.data("kendoPopup") || popup.data("kendoPopup") || animationContainer.data("kendoPopup");

      if (popupWidget && typeof popupWidget.close === "function") {
        popupWidget.close();
        return;
      }

      animationContainer.hide();
    }

    getCurrentFrozenFields() {
      return this.getFrozenFields();
    }

    applyUserLayout(columns) {
      const frozen = this.getFrozenFields();
      const layout = this.definition.userLayout && this.definition.userLayout.grid
        ? this.definition.userLayout.grid.columns
        : null;
      if (!layout) {
        columns.forEach(function(column) {
          column.locked = frozen.indexOf(column.field) !== -1;
        });
        columns.sort(function(left, right) {
          if (left.locked === right.locked) {
            return 0;
          }
          return left.locked ? -1 : 1;
        });
        return columns;
      }

      const byField = columns.reduce(function(acc, column) {
        acc[column.field] = column;
        return acc;
      }, {});
      const ordered = [];

      global.CrudUtils.ensureArray(layout.order).forEach(function(fieldName) {
        if (byField[fieldName]) {
          ordered.push(byField[fieldName]);
          delete byField[fieldName];
        }
      });

      Object.keys(byField).forEach(function(fieldName) {
        ordered.push(byField[fieldName]);
      });

      const hidden = global.CrudUtils.ensureArray(layout.hidden);
      const widths = layout.widths || {};
      ordered.forEach(function(column) {
        if (hidden.indexOf(column.field) !== -1) {
          column.hidden = true;
        }
        if (widths[column.field]) {
          column.width = widths[column.field];
        }
        column.locked = frozen.indexOf(column.field) !== -1;
      });

      ordered.sort(function(left, right) {
        if (left.locked === right.locked) {
          return 0;
        }
        return left.locked ? -1 : 1;
      });

      return ordered;
    }

    getInitialSort() {
      const savedPresetSort = this.getInitialSavedSort();
      if (savedPresetSort.length) {
        return savedPresetSort;
      }

      const saved = this.definition.userLayout && this.definition.userLayout.grid
        ? this.definition.userLayout.grid.sort
        : null;
      return saved && saved.length ? saved : this.definition.query && this.definition.query.defaultSort || [];
    }

    getInitialSavedSort() {
      const userLayout = this.definition.userLayout || {};
      const savedSorts = global.CrudUtils.ensureArray(userLayout.savedSorts);
      const activeSort = savedSorts.find(function(item) {
        return item.isDefault;
      }) || savedSorts.find(function(item) {
        return item.id === userLayout.activeSortId;
      });

      return activeSort ? global.CrudUtils.ensureArray(activeSort.sort) : [];
    }

    getInitialFilter() {
      return this.definition.userLayout && this.definition.userLayout.grid
        ? this.definition.userLayout.grid.filter || null
        : null;
    }

    getInitialGroup() {
      const savedPreset = this.getInitialSavedGroupPreset();
      if (savedPreset) {
        return this.buildGroupDescriptors(savedPreset.group, savedPreset.aggregates);
      }

      const saved = this.definition.userLayout && this.definition.userLayout.grid
        ? this.definition.userLayout.grid.group
        : null;
      return this.buildGroupDescriptors(saved || [], this.getInitialGroupAggregates());
    }

    getInitialSavedGroupPreset() {
      const userLayout = this.definition.userLayout || {};
      const savedGroups = global.CrudUtils.ensureArray(userLayout.savedGroups);
      return savedGroups.find(function(item) {
        return item.isDefault;
      }) || savedGroups.find(function(item) {
        return item.id === userLayout.activeGroupId;
      }) || null;
    }

    getInitialGroupAggregates() {
      const savedPreset = this.getInitialSavedGroupPreset();
      if (savedPreset) {
        return this.normalizeGroupAggregates(savedPreset.aggregates);
      }

      const gridLayout = this.definition.userLayout && this.definition.userLayout.grid
        ? this.definition.userLayout.grid
        : {};
      return this.normalizeGroupAggregates(gridLayout.groupAggregates || this.extractAggregatesFromGroup(gridLayout.group));
    }

    buildGroupDescriptors(group, aggregates) {
      const normalizedAggregates = this.normalizeGroupAggregates(aggregates);
      const descriptors = global.CrudUtils.ensureArray(group)
        .filter(function(item) {
          return item && item.field;
        })
        .map(function(item) {
          const descriptor = {
            field: item.field,
            dir: item.dir === "desc" ? "desc" : "asc"
          };
          if (normalizedAggregates.length) {
            descriptor.aggregates = global.CrudUtils.clone(normalizedAggregates);
          }
          return descriptor;
        });
      this.currentGroupAggregates = normalizedAggregates;
      return descriptors;
    }

    normalizeGroupAggregates(aggregates) {
      const fields = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields
        : {};
      const seen = {};
      return global.CrudUtils.ensureArray(aggregates).filter(function(item) {
        if (!item || !item.field || !fields[item.field]) {
          return false;
        }
        const aggregate = item.aggregate === "sum" ? "sum" : item.aggregate === "count" ? "count" : null;
        if (!aggregate) {
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

    extractAggregatesFromGroup(group) {
      const found = [];
      global.CrudUtils.ensureArray(group).forEach(function(item) {
        global.CrudUtils.ensureArray(item && item.aggregates).forEach(function(aggregate) {
          found.push(aggregate);
        });
      });
      return found;
    }

    buildActionColumn() {
      const actions = this.getPermittedRowActions();

      if (!actions.length) {
        return null;
      }

      return {
        title: "Acoes",
        width: 72,
        locked: this.getFrozenFields().length > 0,
        lockable: false,
        sortable: false,
        filterable: false,
        menu: false,
        attributes: { style: "text-align:center" },
        template: (row) => this.buildRowActionsHtml(row)
      };
    }

    buildGroupHeaderTemplate(column, data) {
      const fieldName = column.field;
      const title = column.title || fieldName;
      const value = this.formatColumnValue(fieldName, data && data.value);
      const summary = this.formatGroupAggregateSummary(data && data.aggregates);
      return "<span class=\"crud-group-header-title\">" +
        global.CrudUtils.escapeHtml(title) +
        ": " +
        global.CrudUtils.escapeHtml(value) +
        "</span>" +
        (summary ? " <span class=\"crud-group-header-summary\">" + summary + "</span>" : "");
    }

    formatGroupAggregateSummary(aggregates) {
      const configured = this.currentGroupAggregates || this.getInitialGroupAggregates();
      const parts = [];
      configured.forEach((item) => {
        const aggregateValues = aggregates && aggregates[item.field] ? aggregates[item.field] : {};
        const value = aggregateValues[item.aggregate];
        if (value == null) {
          return;
        }
        const label = global.CrudUtils.buildFieldLabel(this.definition, item.field);
        const operation = item.aggregate === "sum" ? "soma" : "contagem";
        parts.push(
          "<span class=\"crud-group-aggregate\">" +
            global.CrudUtils.escapeHtml(label + " " + operation + ": " + this.formatAggregateValue(item.field, item.aggregate, value)) +
          "</span>"
        );
      });
      return parts.join("");
    }

    formatAggregateValue(fieldName, aggregate, value) {
      if (aggregate === "count") {
        return String(Number(value || 0));
      }
      const field = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields[fieldName]
        : {};
      const column = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns).find(function(item) {
        return item.field === fieldName;
      }) || {};
      const format = global.CrudUtils.toKendoFormat(column.format || field.format, field.type);
      return format && global.kendo ? kendo.format(format, value) : String(value);
    }

    formatColumnValue(fieldName, value) {
      const field = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields[fieldName]
        : {};
      if (field.type === "enum" && field.options) {
        const option = field.options.find(function(item) {
          return item.value === value;
        });
        return option ? option.text : value;
      }
      const column = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns).find(function(item) {
        return item.field === fieldName;
      }) || {};
      const format = global.CrudUtils.toKendoFormat(column.format || field.format, field.type);
      return format && global.kendo ? kendo.format(format, value) : String(value == null ? "" : value);
    }

    getPermittedRowActions() {
      return global.CrudUtils.ensureArray(this.definition.grid.rowActions)
        .filter((action) => global.CrudUtils.getPermission(this.definition, action.permission));
    }

    buildRowActionsHtml(row, extraClass) {
      const actions = this.getPermittedRowActions();
      if (!actions.length) {
        return "";
      }
      const id = row[this.definition.dataModel.primaryKey];
      const classes = "crud-row-actions" + (extraClass ? " " + extraClass : "");
      return "<div class=\"" + classes + "\">" +
        "<button type=\"button\" class=\"crud-row-actions-toggle\" data-id=\"" +
          global.CrudUtils.escapeHtml(id) +
          "\" aria-haspopup=\"menu\" aria-expanded=\"false\" title=\"Acoes\" aria-label=\"Acoes da linha\">Acoes</button>" +
      "</div>";
    }

    buildMobileCardActionsHtml(row) {
      const mobile = this.getMobileConfig();
      if (mobile.cardActions === false) {
        return "";
      }

      const id = row[this.definition.dataModel.primaryKey];
      const actions = [];
      if (global.CrudUtils.getPermission(this.definition, "create")) {
        actions.push({
          label: "Incluir",
          action: "create",
          icon: "plus"
        });
      }
      this.getPermittedRowActions().forEach(function(action) {
        actions.push({
          label: action.action === "edit" ? "Alterar" : action.label,
          action: action.action,
          icon: action.icon || (action.action === "view" ? "eye" : action.action === "edit" ? "pencil" : action.action === "delete" ? "trash" : "more-vertical")
        });
      });

      if (!actions.length) {
        return "";
      }

      return "<div class=\"crud-mobile-card-actions\">" + actions.map(function(action) {
        return "<button type=\"button\" class=\"crud-row-action crud-mobile-card-action\" data-action=\"" +
          global.CrudUtils.escapeHtml(action.action) +
          "\" data-id=\"" +
          global.CrudUtils.escapeHtml(id) +
          "\" data-icon=\"" +
          global.CrudUtils.escapeHtml(action.icon || "") +
          "\" title=\"" +
          global.CrudUtils.escapeHtml(action.label) +
          "\" aria-label=\"" +
          global.CrudUtils.escapeHtml(action.label) +
          "\">" +
          global.CrudUtils.escapeHtml(action.label) +
          "</button>";
      }).join("") + "</div>";
    }

    bindRowActions() {
      const wrapper = this.grid.wrapper;

      wrapper.off("click.crudRowAction").on("click.crudRowAction", ".crud-row-actions-toggle", (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.toggleRowActionsMenu($(event.currentTarget));
      });

      wrapper.find(".k-grid-content, .k-grid-content-locked").off("scroll.crudRowAction").on("scroll.crudRowAction", () => {
        this.closeRowActionsMenu();
      });

      $(document).off("click.crudRowActionItem").on("click.crudRowActionItem", ".crud-row-action", (event) => {
        const button = $(event.currentTarget);
        const action = button.data("action");
        const id = button.data("id");
        this.closeRowActionsMenu();
        if (this.handlers[action]) {
          this.handlers[action](id);
        }
      });

      $(document).off("click.crudRowAction").on("click.crudRowAction", (event) => {
        const target = $(event.target);
        if (target.closest(".crud-row-actions-popup, .crud-row-actions-toggle").length) {
          return;
        }
        this.closeRowActionsMenu();
      });

      $(document).off("keydown.crudRowAction").on("keydown.crudRowAction", (event) => {
        if (event.key === "Escape") {
          this.closeRowActionsMenu();
        }
      });

      $(global).off("resize.crudRowAction").on("resize.crudRowAction", () => {
        this.closeRowActionsMenu();
      });
    }

    initializeRowActionButtons() {
      if (!this.grid) {
        return;
      }
      this.grid.wrapper.find(".crud-row-actions-toggle").each(function() {
        const button = $(this);
        if (button.data("crudButtonReady")) {
          return;
        }
        button.data("crudButtonReady", true);
        button.kendoButton({
          icon: "more-vertical",
          fillMode: "flat",
          size: "small"
        });
      });
      this.grid.wrapper.find(".crud-mobile-card-action").each(function() {
        const button = $(this);
        if (button.data("crudButtonReady")) {
          return;
        }
        button.data("crudButtonReady", true);
        button.kendoButton({
          icon: button.attr("data-icon") || undefined,
          size: "small"
        });
      });
    }

    toggleRowActionsMenu(button) {
      const id = button.attr("data-id");
      if (this.rowActionsMenu && this.rowActionsMenu.button && this.rowActionsMenu.button[0] === button[0]) {
        this.closeRowActionsMenu();
        return;
      }

      this.closeRowActionsMenu();

      const actions = this.getPermittedRowActions();
      if (!actions.length) {
        return;
      }

      const menu = $("<div class=\"crud-row-actions-popup\" role=\"menu\"></div>").appendTo(document.body);
      actions.forEach((action) => {
        const item = $("<button type=\"button\" class=\"crud-row-action\" role=\"menuitem\"></button>")
          .attr("data-action", action.action)
          .attr("data-id", id)
          .text(action.label)
          .appendTo(menu);

        item.kendoButton({
          icon: action.icon || undefined,
          fillMode: "flat"
        });
      });

      this.rowActionsMenu = {
        id,
        button,
        menu
      };
      button.attr("aria-expanded", "true");
      this.positionRowActionsMenu(menu, button);
      menu.find(".crud-row-action").first().trigger("focus");
    }

    positionRowActionsMenu(menu, button) {
      const rect = button[0].getBoundingClientRect();
      const margin = 8;
      const width = menu.outerWidth();
      const height = menu.outerHeight();
      let top = rect.bottom + 4;
      if (top + height > global.innerHeight - margin) {
        top = Math.max(margin, rect.top - height - 4);
      }
      const left = Math.max(margin, Math.min(rect.left, global.innerWidth - width - margin));

      menu.css({
        top: top + "px",
        left: left + "px"
      });
    }

    closeRowActionsMenu() {
      if (!this.rowActionsMenu) {
        return;
      }
      if (this.rowActionsMenu.button) {
        this.rowActionsMenu.button.attr("aria-expanded", "false");
      }
      if (this.rowActionsMenu.menu) {
        this.rowActionsMenu.menu.remove();
      }
      this.rowActionsMenu = null;
    }

    notifySelectionChange() {
      if (this.handlers.selectionChange) {
        this.handlers.selectionChange(this.getSelectedDataItems());
      }
    }

    getSelectedDataItems() {
      if (!this.grid) {
        return [];
      }

      const primaryKey = this.definition.dataModel.primaryKey;
      const selected = [];
      const seen = {};

      this.grid.select().each((index, row) => {
        const item = this.grid.dataItem(row);
        if (!item) {
          return;
        }
        const data = typeof item.toJSON === "function" ? item.toJSON() : item;
        const id = data[primaryKey];
        const key = id == null ? String(index) : String(id);
        if (seen[key]) {
          return;
        }
        seen[key] = true;
        selected.push(data);
      });

      return selected;
    }

    getSelectedIds() {
      const primaryKey = this.definition.dataModel.primaryKey;
      return this.getSelectedDataItems().map(function(item) {
        return item[primaryKey];
      }).filter(function(id) {
        return id != null;
      });
    }

    getCurrentPageDataItems() {
      if (!this.grid || !this.grid.dataSource) {
        return [];
      }
      return this.flattenDataSourceView(this.grid.dataSource.view()).map(function(item) {
        return item && typeof item.toJSON === "function" ? item.toJSON() : item;
      });
    }

    flattenDataSourceView(items) {
      const rows = [];
      this.toArray(items).forEach((item) => {
        if (item && Array.isArray(item.items)) {
          rows.push.apply(rows, this.flattenDataSourceView(item.items));
          return;
        }
        if (item && item.items && typeof item.items.length === "number") {
          rows.push.apply(rows, this.flattenDataSourceView(item.items));
          return;
        }
        if (item) {
          rows.push(item);
        }
      });
      return rows;
    }

    toArray(items) {
      if (!items) {
        return [];
      }
      if (Array.isArray(items)) {
        return items;
      }
      if (typeof items.toJSON === "function") {
        const value = items.toJSON();
        return Array.isArray(value) ? value : [];
      }
      if (typeof items.length === "number") {
        return Array.prototype.slice.call(items);
      }
      return [];
    }

    getAdjacentRecordId(currentId, direction) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const rows = this.getCurrentPageDataItems();
      const currentIndex = rows.findIndex(function(row) {
        return String(row[primaryKey]) === String(currentId);
      });
      if (currentIndex === -1) {
        return null;
      }
      const nextIndex = direction === "previous" ? currentIndex - 1 : currentIndex + 1;
      const nextRow = rows[nextIndex];
      return nextRow && nextRow[primaryKey] != null ? nextRow[primaryKey] : null;
    }

    getNavigationState(currentId) {
      return {
        previous: this.getAdjacentRecordId(currentId, "previous") != null,
        next: this.getAdjacentRecordId(currentId, "next") != null
      };
    }

    clearSelection() {
      if (!this.grid) {
        return;
      }
      this.grid.clearSelection();
      this.notifySelectionChange();
    }

    setFilters(filters) {
      this.filters = global.CrudUtils.ensureArray(filters);
      if (this.grid) {
        if (this.grid.dataSource.page() === 1) {
          this.grid.dataSource.read();
        } else {
          this.grid.dataSource.page(1);
        }
      }
    }

    refresh() {
      if (this.grid) {
        this.grid.dataSource.read();
      }
    }

    getCurrentSort() {
      if (!this.grid) {
        return [];
      }
      const sort = global.CrudUtils.ensureArray(this.grid.dataSource.sort());
      if (this.isMobileTemplateMode()) {
        return this.removeGroupSort(sort, this.mobileTemplateGroupDescriptors);
      }
      return sort;
    }

    setSort(sort) {
      if (!this.grid) {
        return;
      }
      const nextSort = this.isMobileTemplateMode()
        ? this.mergeGroupSortWithSort(this.mobileTemplateGroupDescriptors, sort)
        : global.CrudUtils.ensureArray(sort);
      this.grid.dataSource.sort(nextSort);
      if (this.grid.dataSource.page() !== 1) {
        this.grid.dataSource.page(1);
      }
    }

    getCurrentGroup() {
      if (this.isMobileTemplateMode()) {
        return global.CrudUtils.ensureArray(this.mobileTemplateGroupDescriptors).map(function(item) {
          return {
            field: item.field,
            dir: item.dir === "desc" ? "desc" : "asc"
          };
        });
      }
      return this.grid ? global.CrudUtils.ensureArray(this.grid.dataSource.group()).map(function(item) {
        return {
          field: item.field,
          dir: item.dir === "desc" ? "desc" : "asc"
        };
      }) : [];
    }

    getCurrentGroupAggregates() {
      if (!this.grid) {
        return [];
      }
      if (this.isMobileTemplateMode()) {
        return this.normalizeGroupAggregates(this.currentGroupAggregates);
      }
      const aggregates = this.grid.dataSource.aggregate && this.grid.dataSource.aggregate();
      return this.normalizeGroupAggregates(aggregates && aggregates.length ? aggregates : this.extractAggregatesFromGroup(this.grid.dataSource.group()));
    }

    setGroup(group, aggregates) {
      if (!this.grid) {
        return;
      }
      const normalizedAggregates = this.normalizeGroupAggregates(aggregates);
      const descriptors = this.buildGroupDescriptors(group, normalizedAggregates);
      this.grid.dataSource.aggregate(normalizedAggregates);
      if (this.isMobileTemplateMode()) {
        const previousGroups = this.mobileTemplateGroupDescriptors;
        const baseSort = this.removeGroupSort(this.grid.dataSource.sort(), previousGroups);
        this.mobileTemplateGroupDescriptors = descriptors;
        this.grid.dataSource.group([]);
        this.grid.dataSource.sort(this.mergeGroupSortWithSort(descriptors, baseSort));
        if (this.grid.dataSource.page() !== 1) {
          this.grid.dataSource.page(1);
        } else {
          this.grid.dataSource.read();
        }
        return;
      }
      this.grid.dataSource.group(descriptors);
      if (this.grid.dataSource.page() !== 1) {
        this.grid.dataSource.page(1);
      }
    }

    mergeGroupSortWithSort(groups, sort) {
      const groupSort = global.CrudUtils.ensureArray(groups)
        .filter(function(item) {
          return item && item.field;
        })
        .map(function(item) {
          return {
            field: item.field,
            dir: item.dir === "desc" ? "desc" : "asc"
          };
        });
      const groupFields = groupSort.map(function(item) {
        return item.field;
      });
      const remainingSort = this.removeGroupSort(sort, groupSort);
      return groupSort.concat(remainingSort.filter(function(item) {
        return groupFields.indexOf(item.field) === -1;
      }));
    }

    removeGroupSort(sort, groups) {
      const groupFields = global.CrudUtils.ensureArray(groups)
        .filter(function(item) {
          return item && item.field;
        })
        .map(function(item) {
          return item.field;
        });
      if (!groupFields.length) {
        return global.CrudUtils.ensureArray(sort);
      }
      return global.CrudUtils.ensureArray(sort).filter(function(item) {
        return item && groupFields.indexOf(item.field) === -1;
      });
    }

    goToFirstPageAndRefresh() {
      if (!this.grid) {
        return;
      }
      if (this.grid.dataSource.page() === 1) {
        this.grid.dataSource.read();
      } else {
        this.grid.dataSource.page(1);
      }
    }

    showNewestBy(fieldName) {
      if (!this.grid) {
        return;
      }
      this.filters = [];
      this.grid.dataSource.filter(null);
      this.grid.dataSource.sort([{ field: fieldName, dir: "desc" }]);
      if (this.grid.dataSource.page() === 1) {
        this.grid.dataSource.read();
      } else {
        this.grid.dataSource.page(1);
      }
    }
  }

  global.CrudKendoGridRenderer = CrudKendoGridRenderer;
})(window, jQuery);
