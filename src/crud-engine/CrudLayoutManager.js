(function(global) {
  "use strict";

  class CrudLayoutManager {
    constructor(options) {
      this.definition = options.definition;
      this.httpClient = options.httpClient;
      this.dirty = false;
      this.onDirtyChange = options.onDirtyChange || function() {};
    }

    bind(grid) {
      this.grid = grid;
      const markDirty = () => this.setDirty(true);
      grid.bind("columnReorder", markDirty);
      grid.bind("columnResize", markDirty);
      grid.bind("columnHide", markDirty);
      grid.bind("columnShow", markDirty);
      grid.bind("columnLock", markDirty);
      grid.bind("columnUnlock", markDirty);
      grid.bind("sort", markDirty);
      grid.bind("filter", markDirty);
      grid.bind("group", markDirty);
      grid.dataSource.bind("change", (event) => {
        if (event.action === "sort" || event.action === "filter" || event.action === "group") {
          markDirty();
        }
      });
    }

    setDirty(value) {
      if (this.dirty === value) {
        return;
      }
      this.dirty = value;
      this.onDirtyChange(value);
    }

    capture() {
      if (!this.grid) {
        return null;
      }
      const options = this.grid.getOptions();
      return {
        enabled: true,
        version: this.definition.userLayout && this.definition.userLayout.version
          ? this.definition.userLayout.version
          : 1,
        definitionHash: this.definition.userLayout && this.definition.userLayout.definitionHash
          ? this.definition.userLayout.definitionHash
          : null,
        grid: {
          columns: {
            order: global.CrudUtils.ensureArray(options.columns)
              .filter(function(column) { return column.field; })
              .map(function(column) { return column.field; }),
            hidden: global.CrudUtils.ensureArray(options.columns)
              .filter(function(column) { return column.field && column.hidden; })
              .map(function(column) { return column.field; }),
            widths: global.CrudUtils.ensureArray(options.columns)
              .filter(function(column) { return column.field && column.width; })
              .reduce(function(acc, column) {
                acc[column.field] = column.width;
                return acc;
              }, {}),
            frozen: global.CrudUtils.ensureArray(options.columns)
              .filter(function(column) { return column.field && column.locked; })
              .map(function(column) { return column.field; }),
            added: []
          },
          sort: this.grid.dataSource.sort() || [],
          filter: this.grid.dataSource.filter() || null,
          group: this.grid.dataSource.group() || [],
          groupAggregates: this.grid.dataSource.aggregate ? this.grid.dataSource.aggregate() || [] : []
        }
      };
    }

    getSavedLayouts() {
      return global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedLayouts);
    }

    getSavedSorts() {
      return global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedSorts);
    }

    getSavedGroups() {
      return global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedGroups);
    }

    getSavedFilters() {
      return global.CrudUtils.ensureArray(this.definition.userLayout && this.definition.userLayout.savedFilters);
    }

    save(options) {
      const endpoint = this.definition.api && this.definition.api.saveLayout;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de salvamento de layout nao configurado."));
      }
      const metadata = options || {};
      if (!metadata.name || !String(metadata.name).trim()) {
        return Promise.reject(global.CrudUtils.makeError("LAYOUT_NAME_REQUIRED", "Informe o nome do leiaute."));
      }

      const payload = Object.assign(this.capture(), {
        id: metadata.id || null,
        name: String(metadata.name).trim(),
        isDefault: Boolean(metadata.isDefault)
      });
      if (Array.isArray(metadata.frozenColumns)) {
        payload.grid.columns.frozen = metadata.frozenColumns;
      }

      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: payload
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        }
        this.setDirty(false);
        return response;
      });
    }

    saveSortPreset(options) {
      const endpoint = this.definition.api && this.definition.api.saveSort;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de salvamento de ordenacao nao configurado."));
      }

      const metadata = options || {};
      const sort = global.CrudUtils.ensureArray(metadata.sort).filter(function(item) {
        return item && item.field && (item.dir === "asc" || item.dir === "desc");
      });
      if (!metadata.name || !String(metadata.name).trim()) {
        return Promise.reject(global.CrudUtils.makeError("SORT_NAME_REQUIRED", "Informe o nome da ordenacao."));
      }
      if (!sort.length) {
        return Promise.reject(global.CrudUtils.makeError("SORT_FIELDS_REQUIRED", "Informe ao menos uma coluna para ordenar."));
      }

      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {
          id: metadata.id || null,
          name: String(metadata.name).trim(),
          isDefault: Boolean(metadata.isDefault),
          sort
        }
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        }
        this.setDirty(false);
        return response;
      });
    }

    deleteSortPreset(sortId) {
      const endpoint = this.definition.api && this.definition.api.deleteSort;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de exclusao de ordenacao nao configurado."));
      }
      if (!sortId) {
        return Promise.reject(global.CrudUtils.makeError("SORT_ID_REQUIRED", "Ordenacao nao informada."));
      }

      const url = global.CrudUtils.replaceUrlParams(endpoint.url, { id: sortId });
      return this.httpClient.request({
        url,
        method: endpoint.method || "DELETE",
        data: {}
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        }
        this.setDirty(false);
        return response;
      });
    }

    saveGroupPreset(options) {
      const endpoint = this.definition.api && this.definition.api.saveGroup;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de salvamento de agrupamento nao configurado."));
      }

      const metadata = options || {};
      const aggregates = this.normalizeGroupAggregates(metadata.aggregates);
      const group = global.CrudUtils.ensureArray(metadata.group).filter(function(item) {
        return item && item.field && (!item.dir || item.dir === "asc" || item.dir === "desc");
      }).map(function(item) {
        return {
          field: item.field,
          dir: item.dir === "desc" ? "desc" : "asc"
        };
      });
      if (!metadata.name || !String(metadata.name).trim()) {
        return Promise.reject(global.CrudUtils.makeError("GROUP_NAME_REQUIRED", "Informe o nome do agrupamento."));
      }
      if (!group.length) {
        return Promise.reject(global.CrudUtils.makeError("GROUP_FIELDS_REQUIRED", "Informe ao menos uma coluna para agrupar."));
      }
      const invalidSum = aggregates.find((item) => item.aggregate === "sum" && !this.isNumericField(item.field));
      if (invalidSum) {
        return Promise.reject(global.CrudUtils.makeError("GROUP_AGGREGATE_INVALID", "A opcao Somar so pode ser usada em campos numericos."));
      }

      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {
          id: metadata.id || null,
          name: String(metadata.name).trim(),
          isDefault: Boolean(metadata.isDefault),
          group,
          aggregates
        }
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        }
        this.setDirty(false);
        return response;
      });
    }

    normalizeGroupAggregates(aggregates) {
      const fields = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields
        : {};
      const seen = {};
      return global.CrudUtils.ensureArray(aggregates).filter(function(item) {
        const aggregate = item && item.aggregate === "sum" ? "sum" : item && item.aggregate === "count" ? "count" : null;
        if (!item || !item.field || !fields[item.field] || !aggregate) {
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

    isNumericField(fieldName) {
      const field = this.definition.dataModel && this.definition.dataModel.fields
        ? this.definition.dataModel.fields[fieldName]
        : null;
      return Boolean(field && ["integer", "decimal", "number"].indexOf(field.type) !== -1);
    }

    deleteGroupPreset(groupId) {
      const endpoint = this.definition.api && this.definition.api.deleteGroup;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de exclusao de agrupamento nao configurado."));
      }
      if (!groupId) {
        return Promise.reject(global.CrudUtils.makeError("GROUP_ID_REQUIRED", "Agrupamento nao informado."));
      }

      const url = global.CrudUtils.replaceUrlParams(endpoint.url, { id: groupId });
      return this.httpClient.request({
        url,
        method: endpoint.method || "DELETE",
        data: {}
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        }
        this.setDirty(false);
        return response;
      });
    }

    saveFilterPreset(options) {
      const endpoint = this.definition.api && this.definition.api.saveFilter;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de salvamento de filtro nao configurado."));
      }

      const metadata = options || {};
      const filters = global.CrudUtils.ensureArray(metadata.filters).filter(function(item) {
        return item && item.id && item.value != null && item.value !== "";
      });
      if (!metadata.name || !String(metadata.name).trim()) {
        return Promise.reject(global.CrudUtils.makeError("FILTER_NAME_REQUIRED", "Informe o nome do filtro."));
      }
      if (!filters.length) {
        return Promise.reject(global.CrudUtils.makeError("FILTER_FIELDS_REQUIRED", "Informe ao menos um filtro."));
      }

      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {
          id: metadata.id || null,
          name: String(metadata.name).trim(),
          isDefault: Boolean(metadata.isDefault),
          filters: global.CrudUtils.clone(filters)
        }
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        }
        this.setDirty(false);
        return response;
      });
    }

    deleteFilterPreset(filterId) {
      const endpoint = this.definition.api && this.definition.api.deleteFilter;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de exclusao de filtro nao configurado."));
      }
      if (!filterId) {
        return Promise.reject(global.CrudUtils.makeError("FILTER_ID_REQUIRED", "Filtro nao informado."));
      }

      const url = global.CrudUtils.replaceUrlParams(endpoint.url, { id: filterId });
      return this.httpClient.request({
        url,
        method: endpoint.method || "DELETE",
        data: {}
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        }
        this.setDirty(false);
        return response;
      });
    }

    apply(layoutId) {
      if (!layoutId) {
        this.definition.userLayout.activeLayoutId = null;
        this.definition.userLayout.source = "default";
        this.definition.userLayout.grid = this.getEmptyGridLayout();
        this.setDirty(false);
        return true;
      }

      const layout = this.getSavedLayouts().find(function(item) {
        return item.id === layoutId;
      });
      if (!layout) {
        return false;
      }

      this.definition.userLayout.activeLayoutId = layout.id;
      this.definition.userLayout.source = "user";
      this.definition.userLayout.grid = global.CrudUtils.clone(layout.grid);
      this.setDirty(false);
      return true;
    }

    restore() {
      const endpoint = this.definition.api && this.definition.api.restoreLayout;
      if (!endpoint) {
        return Promise.reject(global.CrudUtils.makeError("ENDPOINT_MISSING", "Endpoint de restauracao de layout nao configurado."));
      }
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "DELETE",
        data: {}
      }).then((response) => {
        if (response && response.userLayout) {
          this.definition.userLayout = response.userLayout;
        } else if (this.definition.userLayout) {
          this.definition.userLayout.activeLayoutId = null;
          this.definition.userLayout.source = "default";
          this.definition.userLayout.grid = this.getEmptyGridLayout();
        }
        this.setDirty(false);
        return response;
      });
    }

    getEmptyGridLayout() {
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
  }

  global.CrudLayoutManager = CrudLayoutManager;
})(window);
