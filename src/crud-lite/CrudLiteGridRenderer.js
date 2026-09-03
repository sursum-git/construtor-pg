(function(global) {
  "use strict";

  class CrudLiteGridRenderer {
    constructor(options) {
      this.definition = options.definition;
      this.httpClient = options.httpClient;
      this.handlers = options.handlers || {};
      this.deferInitialRead = Boolean(options.deferInitialRead);
      this.filters = global.CrudUtils.ensureArray(options.initialFilters);
      this.pageSize = this.definition.query && this.definition.query.defaultPageSize || 20;
      this.page = 1;
      this.sort = global.CrudUtils.ensureArray(this.definition.query && this.definition.query.defaultSort);
      this.group = [];
      this.aggregates = [];
      this.rows = [];
      this.total = 0;
      this.selectedIds = {};
      this.events = {};
      this.dataSourceEvents = {};
      this.columns = this.resolveColumns();
      this.grid = this.createGridAdapter();
      this.initialReadPromise = Promise.resolve(null);
    }

    render(container) {
      this.panel = document.createElement("section");
      this.panel.className = "crud-lite-grid-panel";
      this.panel.setAttribute("aria-label", "Lista de registros");
      this.panel.innerHTML = [
        "<div class=\"crud-lite-grid-status\" aria-live=\"polite\"></div>",
        "<div class=\"crud-lite-table-wrap\"><table class=\"crud-lite-table\"><thead></thead><tbody></tbody></table></div>",
        "<footer class=\"crud-lite-pager\"></footer>"
      ].join("");
      this.statusElement = this.panel.querySelector(".crud-lite-grid-status");
      this.table = this.panel.querySelector("table");
      this.thead = this.panel.querySelector("thead");
      this.tbody = this.panel.querySelector("tbody");
      this.pager = this.panel.querySelector(".crud-lite-pager");
      const target = container && container[0] ? container[0] : container;
      target.appendChild(this.panel);
      this.renderHeader();
      this.bindEvents();
      if (!this.deferInitialRead) {
        this.initialReadPromise = this.refresh({ rejectOnError: true });
      } else {
        this.initialReadPromise = Promise.resolve(null);
        this.renderRows();
      }
      return this.grid;
    }

    destroy() {
      if (this.panel) {
        this.panel.remove();
      }
      this.events = {};
      this.dataSourceEvents = {};
    }

    bindRowActions() {}

    waitForInitialRead() {
      return this.initialReadPromise || Promise.resolve(null);
    }

    createGridAdapter() {
      return {
        bind: (name, handler) => this.bindAdapterEvent(name, handler),
        getOptions: () => ({
          columns: this.columns.map(function(column) {
            return {
              field: column.field,
              title: column.title,
              width: column.width,
              hidden: column.hidden === true,
              locked: false
            };
          })
        }),
        setOptions: (options) => this.applyGridOptions(options || {}),
        dataSource: {
          bind: (name, handler) => this.bindDataSourceEvent(name, handler),
          sort: (value) => this.getSetDataSourceState("sort", value),
          filter: (value) => this.getSetDataSourceState("filter", value),
          group: (value) => this.getSetDataSourceState("group", value),
          aggregate: (value) => this.getSetDataSourceState("aggregate", value),
          page: (value) => this.getSetPage(value),
          pageSize: (value) => this.getSetPageSize(value),
          total: () => this.total,
          view: () => this.rows.slice(),
          read: () => this.refresh()
        }
      };
    }

    bindAdapterEvent(name, handler) {
      if (!this.events[name]) {
        this.events[name] = [];
      }
      this.events[name].push(handler);
    }

    bindDataSourceEvent(name, handler) {
      if (!this.dataSourceEvents[name]) {
        this.dataSourceEvents[name] = [];
      }
      this.dataSourceEvents[name].push(handler);
    }

    emit(name, event) {
      global.CrudUtils.ensureArray(this.events[name]).forEach(function(handler) {
        handler(event || {});
      });
    }

    emitDataSource(name, event) {
      global.CrudUtils.ensureArray(this.dataSourceEvents[name]).forEach(function(handler) {
        handler(event || {});
      });
    }

    getSetDataSourceState(kind, value) {
      if (kind === "sort") {
        if (value === undefined) {
          return this.sort.slice();
        }
        this.sort = global.CrudUtils.ensureArray(value);
        this.page = 1;
        this.emitDataSource("change", { action: "sort" });
        this.emit("sort", {});
        this.refresh();
        return this.sort;
      }
      if (kind === "filter") {
        if (value === undefined) {
          return this.filter || null;
        }
        this.filter = value || null;
        this.page = 1;
        this.emitDataSource("change", { action: "filter" });
        this.emit("filter", {});
        this.refresh();
        return this.filter;
      }
      if (kind === "group") {
        if (value === undefined) {
          return this.group.slice();
        }
        this.group = global.CrudUtils.ensureArray(value);
        this.page = 1;
        this.emitDataSource("change", { action: "group" });
        this.emit("group", {});
        this.refresh();
        return this.group;
      }
      if (kind === "aggregate") {
        if (value === undefined) {
          return this.aggregates.slice();
        }
        this.aggregates = global.CrudUtils.ensureArray(value);
        return this.aggregates;
      }
      return null;
    }

    getSetPage(value) {
      if (value === undefined) {
        return this.page;
      }
      this.page = Math.max(1, Number(value) || 1);
      this.refresh();
      return this.page;
    }

    getSetPageSize(value) {
      if (value === undefined) {
        return this.pageSize;
      }
      this.pageSize = Math.max(1, Number(value) || this.pageSize);
      this.page = 1;
      this.refresh();
      return this.pageSize;
    }

    applyGridOptions(options) {
      const order = global.CrudUtils.ensureArray(options.columns).map(function(column) {
        return column && column.field;
      }).filter(Boolean);
      if (order.length) {
        const current = this.columns.slice();
        this.columns = order.map(function(field) {
          return current.find(function(column) { return column.field === field; });
        }).filter(Boolean);
      }
      this.renderHeader();
      this.renderRows();
    }

    resolveColumns() {
      return global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.columns)
        .filter(function(column) {
          return column && column.field && column.visible !== false;
        })
        .map(function(column) {
          return Object.assign({}, column, {
            title: column.title || column.label || column.field,
            hidden: column.hidden === true || column.visible === false
          });
        });
    }

    bindEvents() {
      this.thead.addEventListener("click", (event) => {
        const button = event.target.closest("[data-lite-sort-field]");
        if (!button) {
          return;
        }
        this.toggleSort(button.getAttribute("data-lite-sort-field"));
      });
      this.tbody.addEventListener("click", (event) => {
        const selection = event.target.closest("[data-lite-select-row]");
        if (selection) {
          this.toggleSelection(selection.getAttribute("data-lite-select-row"), selection.checked);
          return;
        }
        const action = event.target.closest("[data-lite-row-action]");
        if (action) {
          this.executeRowAction(action.getAttribute("data-lite-row-action"), action.getAttribute("data-lite-row-id"));
        }
      });
      this.tbody.addEventListener("dblclick", (event) => {
        const row = event.target.closest("[data-lite-row-id]");
        if (row && this.handlers.view) {
          const id = row.getAttribute("data-lite-row-id");
          this.handlers.view(id, { record: this.getRecordById(id) });
        }
      });
      this.pager.addEventListener("click", (event) => {
        const button = event.target.closest("[data-lite-page]");
        if (!button || button.disabled) {
          return;
        }
        this.page = Number(button.getAttribute("data-lite-page")) || 1;
        this.refresh();
      });
      this.pager.addEventListener("change", (event) => {
        const select = event.target.closest("[data-lite-page-size]");
        if (!select) {
          return;
        }
        this.pageSize = Number(select.value) || this.pageSize;
        this.page = 1;
        this.refresh();
      });
    }

    renderHeader() {
      const sortable = this.definition.grid && this.definition.grid.sortable !== false;
      const cells = [];
      if (this.isSelectionEnabled()) {
        cells.push("<th class=\"crud-lite-select-col\"></th>");
      }
      cells.push("<th class=\"crud-lite-actions-col\">Acoes</th>");
      this.columns.forEach((column) => {
        const active = this.sort.find(function(item) { return item.field === column.field; });
        const label = global.CrudUtils.escapeHtml(column.title);
        if (sortable && column.sortable !== false) {
          cells.push("<th><button type=\"button\" class=\"crud-lite-sort-button\" data-lite-sort-field=\"" +
            global.CrudUtils.escapeHtml(column.field) + "\">" + label +
            (active ? " <span aria-hidden=\"true\">" + (active.dir === "desc" ? "v" : "^") + "</span>" : "") +
            "</button></th>");
          return;
        }
        cells.push("<th>" + label + "</th>");
      });
      this.thead.innerHTML = "<tr>" + cells.join("") + "</tr>";
    }

    renderRows() {
      if (!this.rows.length) {
        const colSpan = this.columns.length + 1 + (this.isSelectionEnabled() ? 1 : 0);
        this.tbody.innerHTML = "<tr><td colspan=\"" + colSpan + "\" class=\"crud-lite-empty\">Nenhum registro encontrado.</td></tr>";
        this.renderPager();
        return;
      }
      this.tbody.innerHTML = this.rows.map((row) => this.renderRow(row)).join("");
      this.renderPager();
    }

    renderRow(row) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const id = row && row[primaryKey];
      const cells = [];
      if (this.isSelectionEnabled()) {
        cells.push("<td class=\"crud-lite-select-col\"><input type=\"checkbox\" data-lite-select-row=\"" +
          global.CrudUtils.escapeHtml(id) + "\"" + (this.selectedIds[id] ? " checked" : "") + "></td>");
      }
      cells.push("<td class=\"crud-lite-actions-col\">" + this.renderRowActions(row) + "</td>");
      this.columns.forEach((column) => {
        const value = this.formatFieldValue(column.field, row[column.field], column);
        cells.push("<td data-label=\"" + global.CrudUtils.escapeHtml(column.title) + "\"" + (column.align ? " class=\"crud-lite-align-" + global.CrudUtils.escapeHtml(column.align) + "\"" : "") + ">" + value + "</td>");
      });
      return "<tr data-lite-row-id=\"" + global.CrudUtils.escapeHtml(id) + "\">" + cells.join("") + "</tr>";
    }

    renderRowActions(row) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const id = row && row[primaryKey];
      const actions = global.CrudUtils.ensureArray(this.definition.grid && this.definition.grid.rowActions)
        .filter((action) => action && action.action && global.CrudUtils.getPermission(this.definition, action.permission));
      return actions.map(function(action) {
        return "<button type=\"button\" class=\"crud-lite-row-action crud-lite-row-action-" + global.CrudUtils.escapeHtml(action.action) + "\" data-lite-row-action=\"" +
          global.CrudUtils.escapeHtml(action.action) + "\" data-lite-row-id=\"" + global.CrudUtils.escapeHtml(id) + "\">" +
          global.CrudUtils.escapeHtml(action.label || action.action) + "</button>";
      }).join("");
    }

    renderPager() {
      const totalPages = Math.max(1, Math.ceil((this.total || 0) / this.pageSize));
      this.page = Math.min(Math.max(1, this.page), totalPages);
      const start = this.total ? ((this.page - 1) * this.pageSize) + 1 : 0;
      const end = Math.min(this.total, this.page * this.pageSize);
      const pageSizes = global.CrudUtils.ensureArray(this.definition.query && this.definition.query.pageSizes);
      this.pager.innerHTML = [
        "<span class=\"crud-lite-pager-summary\">" + start + "-" + end + " de " + this.total + "</span>",
        "<button type=\"button\" class=\"crud-lite-button\" data-lite-page=\"" + (this.page - 1) + "\"" + (this.page <= 1 ? " disabled" : "") + ">Anterior</button>",
        "<span class=\"crud-lite-pager-current\">Pagina " + this.page + " de " + totalPages + "</span>",
        "<button type=\"button\" class=\"crud-lite-button\" data-lite-page=\"" + (this.page + 1) + "\"" + (this.page >= totalPages ? " disabled" : "") + ">Proxima</button>",
        pageSizes.length ? "<label class=\"crud-lite-page-size\">Linhas <select data-lite-page-size>" + pageSizes.map((size) => {
          return "<option value=\"" + Number(size) + "\"" + (Number(size) === Number(this.pageSize) ? " selected" : "") + ">" + Number(size) + "</option>";
        }).join("") + "</select></label>" : ""
      ].join("");
    }

    refresh(options) {
      const settings = options || {};
      const endpoint = this.definition.api && this.definition.api.read;
      if (!endpoint || !endpoint.url) {
        global.CrudUtils.showMessage("Endpoint de leitura nao configurado.", "error");
        return Promise.resolve(null);
      }
      this.setLoading(true);
      const payload = {
        page: this.page,
        skip: (this.page - 1) * this.pageSize,
        take: this.pageSize,
        pageSize: this.pageSize,
        sort: this.sort,
        filter: this.filter || null,
        group: this.group,
        filters: this.filters
      };
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "GET",
        data: payload
      }).then((response) => {
        this.rows = global.CrudUtils.ensureArray(response && response.data);
        this.total = Number(response && response.total || this.rows.length || 0);
        this.selectedIds = {};
        this.renderHeader();
        this.renderRows();
        this.notifySelectionChange();
        this.emitDataSource("change", { action: "read" });
        return response;
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar registros.");
        global.CrudUtils.showMessage(normalized.message, "error");
        if (settings.rejectOnError) {
          throw normalized;
        }
        return null;
      }).finally(() => {
        this.setLoading(false);
      });
    }

    setLoading(value) {
      if (!this.statusElement) {
        return;
      }
      this.statusElement.textContent = value ? "Carregando registros..." : "";
      this.panel.classList.toggle("crud-lite-loading", Boolean(value));
    }

    setFilters(filters) {
      this.filters = global.CrudUtils.ensureArray(filters);
      this.page = 1;
      return this.refresh();
    }

    toggleSort(field) {
      const current = this.sort.find(function(item) { return item.field === field; });
      this.sort = [{ field, dir: current && current.dir === "asc" ? "desc" : "asc" }];
      this.page = 1;
      this.emitDataSource("change", { action: "sort" });
      this.emit("sort", {});
      this.refresh();
    }

    executeRowAction(action, id) {
      if (action === "view" && this.handlers.view) {
        this.handlers.view(id, { record: this.getRecordById(id) });
      } else if (action === "edit" && this.handlers.edit) {
        this.handlers.edit(id, { record: this.getRecordById(id) });
      } else if (action === "delete" && this.handlers.delete) {
        this.handlers.delete(id, { record: this.getRecordById(id) });
      }
    }

    isSelectionEnabled() {
      const bulk = this.definition.grid && this.definition.grid.bulkActions;
      return Boolean(bulk && bulk.enabled !== false && global.CrudUtils.ensureArray(bulk.actions).length);
    }

    toggleSelection(id, checked) {
      if (checked) {
        this.selectedIds[id] = true;
      } else {
        delete this.selectedIds[id];
      }
      this.notifySelectionChange();
    }

    notifySelectionChange() {
      const rows = this.getSelectedRows();
      if (this.handlers.selectionChange) {
        this.handlers.selectionChange(rows);
      }
    }

    getSelectedRows() {
      const primaryKey = this.definition.dataModel.primaryKey;
      return this.rows.filter((row) => this.selectedIds[row[primaryKey]]);
    }

    getSelectedIds() {
      return Object.keys(this.selectedIds);
    }

    clearSelection() {
      this.selectedIds = {};
      this.renderRows();
      this.notifySelectionChange();
    }

    getRecordById(id) {
      const primaryKey = this.definition.dataModel.primaryKey;
      return this.rows.find(function(row) {
        return String(row && row[primaryKey]) === String(id);
      }) || null;
    }

    getAdjacentRecordId(currentId, direction) {
      const primaryKey = this.definition.dataModel.primaryKey;
      const index = this.rows.findIndex(function(row) {
        return String(row && row[primaryKey]) === String(currentId);
      });
      if (index === -1) {
        return null;
      }
      const next = direction === "previous" ? this.rows[index - 1] : this.rows[index + 1];
      return next ? next[primaryKey] : null;
    }

    getNavigationState(currentId) {
      return {
        previous: this.getAdjacentRecordId(currentId, "previous") != null,
        next: this.getAdjacentRecordId(currentId, "next") != null
      };
    }

    goToFirstPageAndRefresh() {
      this.page = 1;
      return this.refresh();
    }

    showNewestBy(fieldName) {
      this.sort = [{ field: fieldName, dir: "desc" }];
      this.page = 1;
      return this.refresh();
    }

    getCurrentSort() {
      return this.sort.slice();
    }

    setSort(sort) {
      this.sort = global.CrudUtils.ensureArray(sort);
      this.page = 1;
      return this.refresh();
    }

    getCurrentGroup() {
      return this.group.slice();
    }

    getCurrentGroupAggregates() {
      return this.aggregates.slice();
    }

    setGroup(group, aggregates) {
      this.group = global.CrudUtils.ensureArray(group);
      this.aggregates = global.CrudUtils.ensureArray(aggregates);
      this.page = 1;
      return this.refresh();
    }

    getCurrentFrozenFields() {
      return [];
    }

    normalizeFrozenFields() {
      return [];
    }

    export(format, option) {
      const normalized = String(format || "csv").toLowerCase();
      if (normalized !== "csv") {
        global.CrudUtils.showMessage("Na engine Lite, a v1 exporta apenas CSV.", "warning");
        return;
      }
      this.loadAllRowsForExport().then((rows) => {
        const csv = this.buildCsv(rows);
        const fileName = this.getExportFileName(option || {}, "csv");
        const link = global.document.createElement("a");
        link.href = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
        link.download = fileName;
        global.document.body.appendChild(link);
        link.click();
        link.remove();
      });
    }

    loadAllRowsForExport() {
      const endpoint = this.definition.api && this.definition.api.read;
      const take = Math.max(this.total || 0, this.pageSize, 1000);
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "GET",
        data: {
          page: 1,
          skip: 0,
          take,
          pageSize: take,
          sort: this.sort,
          filter: this.filter || null,
          group: this.group,
          filters: this.filters
        }
      }).then(function(response) {
        return global.CrudUtils.ensureArray(response && response.data);
      });
    }

    buildCsv(rows) {
      const headers = this.columns.map(function(column) {
        return column.title;
      });
      const lines = [headers.map((value) => this.escapeCsv(value)).join(";")];
      rows.forEach((row) => {
        lines.push(this.columns.map((column) => this.escapeCsv(this.formatPlainValue(column.field, row[column.field], column))).join(";"));
      });
      return lines.join("\r\n");
    }

    escapeCsv(value) {
      const text = String(value == null ? "" : value);
      return "\"" + text.replace(/"/g, "\"\"") + "\"";
    }

    getExportFileName(option, extension) {
      const base = String(option.fileName || this.definition.id || "exportacao").replace(/\.[a-z0-9]+$/i, "").replace(/[\\/:*?"<>|]+/g, "-");
      return base + "." + extension;
    }

    formatFieldValue(fieldName, value, column) {
      return global.CrudUtils.escapeHtml(this.formatPlainValue(fieldName, value, column));
    }

    formatPlainValue(fieldName, value, column) {
      const field = this.definition.dataModel.fields[fieldName] || {};
      const format = column.format || field.format || field.type;
      if (value == null || value === "") {
        return "";
      }
      if (format === "currency") {
        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(Number(value) || 0);
      }
      if (format === "date" || field.type === "date") {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat("pt-BR").format(date);
      }
      if (field.type === "integer" || field.type === "decimal" || format === "number") {
        return new Intl.NumberFormat("pt-BR").format(Number(value) || 0);
      }
      const option = global.CrudUtils.ensureArray(field.options).find(function(item) {
        return String(item.value) === String(value);
      });
      return option ? option.text : String(value);
    }
  }

  global.CrudLiteGridRenderer = CrudLiteGridRenderer;
})(window);
