(function(global) {
  "use strict";

  class PageDefinitionNormalizer {
    normalize(definition) {
      if (!definition || typeof definition !== "object") {
        return definition;
      }

      if (definition.pageType) {
        return this.normalizeSegmentedDefinition(definition);
      }

      return this.normalizeLegacyCrudDefinition(definition);
    }

    normalizeSegmentedDefinition(definition) {
      const pageType = String(definition.pageType || "").trim();
      if (pageType !== "crud") {
        throw global.CrudUtils.makeError("UNSUPPORTED_PAGE_TYPE", "Tipo de pagina nao suportado: " + (pageType || "(vazio)") + ".");
      }

      const source = global.CrudUtils.clone(definition);
      const program = source.program || {};
      const crud = source.crud || {};
      const dataSource = source.dataSource || {};
      const query = this.normalizeQueryConfig(crud.query || source.query || {});
      const filter = this.normalizeFilterConfig(crud.filter || source.filter, crud.query || source.query || {});

      crud.query = query;
      crud.filter = filter;

      const normalized = Object.assign({}, source, {
        _definitionStyle: "segmented",
        _rawDefinition: global.CrudUtils.clone(definition),
        schemaVersion: source.schemaVersion,
        pageType,
        program,
        crud,
        dataSource,
        id: program.id || source.id,
        module: program.module || source.module,
        entity: program.entity || source.entity,
        title: program.title || source.title,
        programVersion: program.version || program.programVersion || source.programVersion,
        subtitle: program.subtitle || source.subtitle,
        subtitleTooltip: program.subtitleTooltip || source.subtitleTooltip,
        description: program.description || source.description,
        help: program.help || source.help,
        logs: program.logs || source.logs,
        api: dataSource.api || dataSource.endpoints || crud.api || source.api,
        permissions: source.permissions || {},
        security: source.security || {},
        currentUser: source.currentUser || source.user || {},
        features: crud.features || source.features || {},
        dataModel: source.dataModel || crud.dataModel,
        query,
        filter,
        grid: crud.grid || source.grid,
        form: crud.form || source.form,
        layoutCustomization: crud.layoutCustomization || source.layoutCustomization || {},
        userLayout: crud.userLayout || source.userLayout || {}
      });

      normalized.program.version = normalized.program.version || normalized.programVersion;
      normalized.program.title = normalized.program.title || normalized.title;
      normalized.program.id = normalized.program.id || normalized.id;

      return normalized;
    }

    normalizeLegacyCrudDefinition(definition) {
      const source = global.CrudUtils.clone(definition);
      const query = this.normalizeQueryConfig(source.query || {});
      const filter = this.normalizeFilterConfig(source.filter, source.query || {});
      const program = {
        id: source.id,
        module: source.module,
        entity: source.entity,
        title: source.title,
        version: source.programVersion,
        subtitle: source.subtitle,
        subtitleTooltip: source.subtitleTooltip,
        description: source.description,
        help: source.help,
        logs: source.logs
      };

      const crud = {
        features: source.features || {},
        query,
        filter,
        grid: source.grid,
        form: source.form,
        layoutCustomization: source.layoutCustomization || {},
        userLayout: source.userLayout || {}
      };

      return Object.assign({}, source, {
        _definitionStyle: "legacy",
        _rawDefinition: global.CrudUtils.clone(definition),
        pageType: "crud",
        program,
        dataSource: {
          api: source.api
        },
        crud,
        security: source.security || {},
        currentUser: source.currentUser || source.user || {},
        query,
        filter
      });
    }

    normalizeQueryConfig(queryConfig) {
      const query = Object.assign({}, queryConfig || {});
      delete query.title;
      delete query.openFiltersOnLoad;
      delete query.filters;
      return query;
    }

    normalizeFilterConfig(filterConfig, legacyQueryConfig) {
      const filter = Object.assign({}, filterConfig || {});
      const legacyQuery = legacyQueryConfig || {};

      if (!filter.type) {
        filter.type = "window";
      }
      if (!filter.mode) {
        filter.mode = "basic";
      }
      if (!filter.title) {
        filter.title = legacyQuery.title || "Filtros";
      }
      if (filter.openOnLoad == null) {
        filter.openOnLoad = legacyQuery.openFiltersOnLoad == null
          ? false
          : Boolean(legacyQuery.openFiltersOnLoad);
      }
      if (filter.maximizeFilter == null) {
        filter.maximizeFilter = filter.maximizeOnLoad == null
          ? false
          : Boolean(filter.maximizeOnLoad);
      }
      delete filter.maximizeOnLoad;
      if (filter.waitForSubmitOnLoad == null) {
        filter.waitForSubmitOnLoad = true;
      }
      const showAppliedFilters = filter.showAppliedFilters != null
        ? Boolean(filter.showAppliedFilters)
        : filter.showAppliedSummary != null
          ? Boolean(filter.showAppliedSummary)
          : true;
      filter.showAppliedFilters = showAppliedFilters;
      filter.showAppliedSummary = showAppliedFilters;
      if (!Array.isArray(filter.fields)) {
        filter.fields = global.CrudUtils.ensureArray(legacyQuery.filters);
      }

      return filter;
    }
  }

  global.PageDefinitionNormalizer = PageDefinitionNormalizer;
})(window);
