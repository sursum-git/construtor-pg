(function(global) {
  "use strict";

  const REQUIRED_ROOT = [
    "schemaVersion",
    "pageType",
    "id",
    "module",
    "entity",
    "title",
    "api",
    "permissions",
    "dataModel",
    "grid",
    "form"
  ];

  const FIELD_NAME_PATTERN = /^[A-Za-z_][A-Za-z0-9_.]*$/;
  const MAX_SUBTITLE_LENGTH = 80;
  const MAX_SUBTITLE_TOOLTIP_LENGTH = 300;

  class CrudDefinitionValidator {
    validate(definition, options) {
      const errors = [];
      const normalizedDefinition = this.normalizeDefinition(definition);
      this.securityPolicy = options && options.securityPolicy || global.CrudUtils.normalizeSecurityPolicy({}, {});

      if (!normalizedDefinition || typeof normalizedDefinition !== "object") {
        errors.push("A definicao precisa ser um objeto JSON.");
      } else {
        this.validateRoot(normalizedDefinition, errors);
        this.validateFields(normalizedDefinition, errors);
        this.validateTextLimits(normalizedDefinition, errors);
        this.validateFilter(normalizedDefinition, errors);
        this.validateGrid(normalizedDefinition, errors);
        this.validateMobileGrid(normalizedDefinition, errors);
        this.validateFreezeColumns(normalizedDefinition, errors);
        this.validateSavedSorts(normalizedDefinition, errors);
        this.validateSavedGroups(normalizedDefinition, errors);
        this.validateSavedFilters(normalizedDefinition, errors);
        this.validateUserLayoutGroup(normalizedDefinition, errors);
        this.validateForm(normalizedDefinition, errors);
        this.validatePermissions(normalizedDefinition, errors);
        this.validateSensitiveKeys(normalizedDefinition, "", errors);
        this.validateUrls(normalizedDefinition, errors);
        this.validateLogs(normalizedDefinition, errors);
      }

      if (errors.length) {
        throw global.CrudUtils.makeError("INVALID_CRUD_DEFINITION", "JSON de definicao invalido.", { errors });
      }

      return true;
    }

    normalizeDefinition(definition) {
      if (!global.PageDefinitionNormalizer || !definition || typeof definition !== "object") {
        return definition;
      }
      return new global.PageDefinitionNormalizer().normalize(definition);
    }

    validateRoot(definition, errors) {
      REQUIRED_ROOT.forEach((key) => {
        if (definition[key] == null) {
          errors.push("Campo obrigatorio ausente: " + this.rootPath(definition, key) + ".");
        }
      });

      if (definition.pageType !== "crud") {
        errors.push("pageType deve ser crud nesta versao.");
      }

      if (!definition.dataModel || !definition.dataModel.fields) {
        errors.push("dataModel.fields e obrigatorio.");
      }

      if (!definition.grid || !Array.isArray(definition.grid.columns)) {
        errors.push(this.path(definition, "grid.columns") + " e obrigatorio.");
      }
    }

    validateTextLimits(definition, errors) {
      this.validateMaxLength(
        definition.subtitle,
        MAX_SUBTITLE_LENGTH,
        this.programPath(definition, "subtitle"),
        "Use subtitleTooltip para textos maiores.",
        errors
      );
      this.validateMaxLength(
        definition.subtitleTooltip,
        MAX_SUBTITLE_TOOLTIP_LENGTH,
        this.programPath(definition, "subtitleTooltip"),
        "",
        errors
      );
    }

    validateMaxLength(value, maxLength, fieldName, hint, errors) {
      if (value == null) {
        return;
      }
      if (String(value).length > maxLength) {
        errors.push(fieldName + " deve ter no maximo " + maxLength + " caracteres." + (hint ? " " + hint : ""));
      }
    }

    validateFields(definition, errors) {
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      Object.keys(fields).forEach(function(fieldName) {
        if (!FIELD_NAME_PATTERN.test(fieldName)) {
          errors.push("Nome de campo invalido: " + fieldName + ".");
        }
        if (!fields[fieldName].type || !fields[fieldName].label) {
          errors.push("Campo " + fieldName + " precisa ter type e label.");
        }
      });
      this.validateBulkActions(definition, errors);
    }

    validateBulkActions(definition, errors) {
      const bulkActions = definition.grid && definition.grid.bulkActions;
      if (!bulkActions || bulkActions.enabled === false) {
        return;
      }

      global.CrudUtils.ensureArray(bulkActions.actions).forEach((action) => {
        if (!action.id || !action.label || !action.action) {
          errors.push(this.path(definition, "grid.bulkActions.actions") + " precisa ter id, label e action.");
        }
        if (action.endpointId && !(definition.api || {})[action.endpointId]) {
          errors.push(this.path(definition, "grid.bulkActions.actions") + " referencia endpoint inexistente: " + action.endpointId + ".");
        }
        if (action.endpoint && !action.endpoint.url) {
          errors.push(this.path(definition, "grid.bulkActions.actions") + " possui endpoint sem url.");
        }
        this.validateActionEndpointReferences(definition, action, this.path(definition, "grid.bulkActions.actions"), errors);
      });
    }

    validateFilter(definition, errors) {
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      const filter = definition.filter || {};
      const allowedTypes = ["window"];
      const allowedModes = ["basic"];

      if (filter.type && allowedTypes.indexOf(filter.type) === -1) {
        errors.push(this.path(definition, "filter.type") + " invalido: " + filter.type + ".");
      }
      if (filter.mode && allowedModes.indexOf(filter.mode) === -1) {
        errors.push(this.path(definition, "filter.mode") + " invalido: " + filter.mode + ".");
      }

      ["openOnLoad", "maximizeFilter", "waitForSubmitOnLoad", "showAppliedFilters", "showAppliedSummary"].forEach((property) => {
        if (filter[property] != null && typeof filter[property] !== "boolean") {
          errors.push(this.path(definition, "filter." + property) + " precisa ser booleano.");
        }
      });

      global.CrudUtils.ensureArray(filter.fields).forEach((item) => {
        if (item.field && !fields[item.field]) {
          errors.push(this.path(definition, "filter.fields") + " referencia campo inexistente: " + item.field + ".");
        }
        global.CrudUtils.ensureArray(item.fields).forEach((fieldName) => {
          if (!fields[fieldName]) {
            errors.push(this.path(definition, "filter.fields") + " referencia campo inexistente: " + fieldName + ".");
          }
        });
        this.validateFilterOperators(definition, item, errors);
        this.validateFilterTemplates(definition, item, errors);
      });
      this.validateFilterTabs(definition, filter, errors);
    }

    validateFilterTabs(definition, filter, errors) {
      const tabs = filter.tabs || {};
      const tabItems = global.CrudUtils.ensureArray(tabs.items);
      if (tabs.enabled === false) {
        return;
      }
      if (tabs.enabled && !tabItems.length) {
        errors.push(this.path(definition, "filter.tabs.items") + " deve informar ao menos uma aba.");
        return;
      }
      if (!tabItems.length) {
        return;
      }

      const knownFilters = global.CrudUtils.ensureArray(filter.fields).reduce(function(acc, item) {
        if (item && item.id) {
          acc[item.id] = true;
        }
        return acc;
      }, {});
      const usedFilters = {};

      tabItems.forEach((tab) => {
        if (!tab.id || !tab.title) {
          errors.push(this.path(definition, "filter.tabs.items") + " precisa ter id e title.");
        }
        const tabFields = global.CrudUtils.ensureArray(tab.fields);
        if (!tabFields.length) {
          errors.push(this.path(definition, "filter.tabs.items.fields") + " precisa informar filtros.");
        }
        tabFields.forEach((filterId) => {
          if (!knownFilters[filterId]) {
            errors.push(this.path(definition, "filter.tabs.items.fields") + " referencia filtro inexistente: " + filterId + ".");
          }
          if (usedFilters[filterId]) {
            errors.push(this.path(definition, "filter.tabs.items.fields") + " referencia filtro repetido: " + filterId + ".");
          }
          usedFilters[filterId] = true;
        });
      });
    }

    validateFilterOperators(definition, item, errors) {
      const allowed = [
        "eq",
        "neq",
        "startsWith",
        "contains",
        "notContains",
        "isEmpty",
        "isNotEmpty",
        "isNull",
        "isNotNull",
        "between",
        "in",
        "notIn",
        "gt",
        "gte",
        "lt",
        "lte",
        "relative"
      ];
      const inspect = (operator) => {
        const value = operator && typeof operator === "object" ? operator.value || operator.id : operator;
        if (value && allowed.indexOf(value) === -1) {
          errors.push(this.path(definition, "filter.fields.operators") + " possui operador invalido: " + value + ".");
        }
      };
      if (item.operator) {
        inspect(item.operator);
      }
      global.CrudUtils.ensureArray(item.operators).forEach(inspect);
      global.CrudUtils.ensureArray(item.operatorOptions).forEach(inspect);
    }

    validateFilterTemplates(definition, item, errors) {
      if (item.template || item.valueTemplate) {
        errors.push(this.path(definition, "filter.fields") + " nao pode usar template livre.");
      }
      global.CrudUtils.ensureArray(item.templateFields).forEach((fieldName) => {
        this.validateFieldReference(definition, fieldName, "filter.fields.templateFields", errors);
      });
      global.CrudUtils.ensureArray(item.valueTemplateFields).forEach((fieldName) => {
        this.validateFieldReference(definition, fieldName, "filter.fields.valueTemplateFields", errors);
      });
    }

    validateGrid(definition, errors) {
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      if (definition.grid && definition.grid.groupable != null && typeof definition.grid.groupable !== "boolean") {
        errors.push(this.path(definition, "grid.groupable") + " precisa ser booleano.");
      }
      this.validateGridAi(definition, errors);
      this.validatePrintConfig(definition, definition.grid && definition.grid.print, this.path(definition, "grid.print"), false, errors);
      global.CrudUtils.ensureArray(definition.grid && definition.grid.columns).forEach((column) => {
        if (column.template) {
          errors.push("Coluna " + column.field + " nao pode usar template livre.");
        }
        if (!column.field || !column.title) {
          errors.push("Toda coluna precisa ter field e title.");
          return;
        }
        if (!FIELD_NAME_PATTERN.test(column.field)) {
          errors.push("Campo de coluna invalido: " + column.field + ".");
        }
        if (!fields[column.field]) {
          errors.push("Coluna referencia campo inexistente: " + column.field + ".");
        }
        if (column.kind) {
          errors.push("Tipo customizado de coluna ainda nao implementado: " + column.kind + ".");
        }
        if (column.formula) {
          errors.push("Formula de coluna ainda nao implementada: " + column.field + ".");
        }
        if (column.groupable != null && typeof column.groupable !== "boolean") {
          errors.push(this.path(definition, "grid.columns.groupable") + " precisa ser booleano.");
        }
      });
    }

    validateGridAi(definition, errors) {
      const ai = definition.grid && definition.grid.ai;

      if (!ai || ai.enabled == null || ai.enabled === false) {
        return;
      }

      if (typeof ai.enabled !== "boolean") {
        errors.push(this.path(definition, "grid.ai.enabled") + " precisa ser booleano.");
      }
      if (ai.provider && ["mock", "service"].indexOf(ai.provider) === -1) {
        errors.push(this.path(definition, "grid.ai.provider") + " invalido: " + ai.provider + ".");
      }
      if (ai.tool && ai.tool !== "smartbox") {
        errors.push(this.path(definition, "grid.ai.tool") + " invalido: " + ai.tool + ".");
      }
      if (ai.token || ai.apiKey || ai.authorization) {
        errors.push(this.path(definition, "grid.ai") + " nao deve conter token ou chave de IA no JSON do frontend.");
      }

      const serviceUrl = ai.serviceUrl || (ai.service && ai.service.url);
      if ((ai.provider === "service" || ai.service) && !serviceUrl) {
        errors.push(this.path(definition, "grid.ai.serviceUrl") + " e obrigatorio quando provider=service.");
      }
      if (serviceUrl && this.isInlineEndpointUrlDisallowed()) {
        errors.push(this.path(definition, "grid.ai.serviceUrl") + " nao pode usar URL livre em modo producao. Use endpointId/actionId.");
      }
      if (serviceUrl && !global.CrudUtils.isRelativeUrl(serviceUrl)) {
        errors.push(this.path(definition, "grid.ai.serviceUrl") + " deve ser uma URL relativa para um endpoint backend.");
      }

      global.CrudUtils.ensureArray(ai.searchFields).forEach((fieldName) => {
        this.validateFieldReference(definition, fieldName, "grid.ai.searchFields", errors);
      });
    }

    validateMobileGrid(definition, errors) {
      const mobile = definition.grid && definition.grid.mobile;
      if (!mobile || mobile.enabled === false) {
        return;
      }

      const mode = mobile.mode || "columns";
      if (["columns", "template"].indexOf(mode) === -1) {
        errors.push(this.path(definition, "grid.mobile.mode") + " invalido: " + mode + ".");
        return;
      }

      if (mobile.breakpoint != null && (!Number.isFinite(Number(mobile.breakpoint)) || Number(mobile.breakpoint) <= 0)) {
        errors.push(this.path(definition, "grid.mobile.breakpoint") + " precisa ser numero positivo.");
      }

      if (mode === "columns") {
        const columns = global.CrudUtils.ensureArray(mobile.columns);
        if (!columns.length) {
          errors.push(this.path(definition, "grid.mobile.columns") + " deve informar ao menos uma coluna.");
        }
        columns.forEach((fieldName) => this.validateFieldReference(definition, fieldName, "grid.mobile.columns", errors));
        return;
      }

      const template = mobile.template || {};
      this.validateFieldReference(definition, template.titleField, "grid.mobile.template.titleField", errors, true);
      this.validateFieldReference(definition, template.subtitleField, "grid.mobile.template.subtitleField", errors, true);
      global.CrudUtils.ensureArray(template.badges).forEach((fieldName) => {
        this.validateFieldReference(definition, fieldName, "grid.mobile.template.badges", errors);
      });

      const tabs = template.tabs || {};
      const tabItems = global.CrudUtils.ensureArray(tabs.items);
      if (tabs.enabled && !tabItems.length) {
        errors.push(this.path(definition, "grid.mobile.template.tabs.items") + " deve informar ao menos uma aba.");
      }

      if (tabs.enabled) {
        tabItems.forEach((tab) => {
          if (!tab.id || !tab.title) {
            errors.push(this.path(definition, "grid.mobile.template.tabs.items") + " precisa ter id e title.");
          }
          const tabFields = global.CrudUtils.ensureArray(tab.fields);
          if (!tabFields.length) {
            errors.push(this.path(definition, "grid.mobile.template.tabs.items") + " precisa informar fields.");
          }
          tabFields.forEach((fieldName) => this.validateFieldReference(definition, fieldName, "grid.mobile.template.tabs.items.fields", errors));
        });
      } else {
        const fields = global.CrudUtils.ensureArray(template.fields);
        if (!fields.length) {
          errors.push(this.path(definition, "grid.mobile.template.fields") + " deve informar ao menos um campo quando tabs.enabled nao estiver ativo.");
        }
        fields.forEach((fieldName) => this.validateFieldReference(definition, fieldName, "grid.mobile.template.fields", errors));
      }
    }

    validateFieldReference(definition, fieldName, context, errors, optional) {
      if (!fieldName) {
        if (!optional) {
          errors.push(this.path(definition, context) + " referencia campo vazio.");
        }
        return;
      }
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      if (!fields[fieldName]) {
        errors.push(this.path(definition, context) + " referencia campo inexistente: " + fieldName + ".");
      }
    }

    validateFreezeColumns(definition, errors) {
      const config = definition.grid && definition.grid.freezeColumns;
      if (!config || !config.enabled) {
        return;
      }

      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      global.CrudUtils.ensureArray(config.fields).forEach((fieldName) => {
        if (!fields[fieldName]) {
          errors.push(this.path(definition, "grid.freezeColumns") + " referencia campo inexistente: " + fieldName + ".");
        }
      });
    }

    validateSavedSorts(definition, errors) {
      global.CrudUtils.ensureArray(definition.userLayout && definition.userLayout.savedSorts).forEach((preset) => {
        if (!preset.id || !preset.name) {
          errors.push(this.path(definition, "userLayout.savedSorts") + " precisa ter id e name.");
        }
        const sort = global.CrudUtils.ensureArray(preset.sort);
        if (!sort.length) {
          errors.push(this.path(definition, "userLayout.savedSorts") + " precisa informar sort.");
        }
        sort.forEach((item) => {
          this.validateFieldReference(definition, item && item.field, "userLayout.savedSorts.sort.field", errors);
          if (!item || ["asc", "desc"].indexOf(item.dir) === -1) {
            errors.push(this.path(definition, "userLayout.savedSorts.sort.dir") + " deve ser asc ou desc.");
          }
        });
      });
    }

    validateSavedGroups(definition, errors) {
      global.CrudUtils.ensureArray(definition.userLayout && definition.userLayout.savedGroups).forEach((preset) => {
        if (!preset.id || !preset.name) {
          errors.push(this.path(definition, "userLayout.savedGroups") + " precisa ter id e name.");
        }
        const group = global.CrudUtils.ensureArray(preset.group);
        if (!group.length) {
          errors.push(this.path(definition, "userLayout.savedGroups") + " precisa informar group.");
        }
        group.forEach((item) => {
          this.validateFieldReference(definition, item && item.field, "userLayout.savedGroups.group.field", errors);
          if (item && item.dir && ["asc", "desc"].indexOf(item.dir) === -1) {
            errors.push(this.path(definition, "userLayout.savedGroups.group.dir") + " deve ser asc ou desc.");
          }
          global.CrudUtils.ensureArray(item && item.aggregates).forEach((aggregate) => {
            this.validateGroupAggregate(definition, aggregate, "userLayout.savedGroups.group.aggregates", errors);
          });
        });
        global.CrudUtils.ensureArray(preset.aggregates).forEach((aggregate) => {
          this.validateGroupAggregate(definition, aggregate, "userLayout.savedGroups.aggregates", errors);
        });
      });
    }

    validateSavedFilters(definition, errors) {
      const filterDefinitions = global.CrudUtils.ensureArray(definition.filter && definition.filter.fields);
      const knownFilters = filterDefinitions.reduce(function(acc, item) {
        if (item && item.id) {
          acc[item.id] = true;
        }
        return acc;
      }, {});

      global.CrudUtils.ensureArray(definition.userLayout && definition.userLayout.savedFilters).forEach((preset) => {
        if (!preset.id || !preset.name) {
          errors.push(this.path(definition, "userLayout.savedFilters") + " precisa ter id e name.");
        }
        const filters = global.CrudUtils.ensureArray(preset.filters);
        if (!filters.length) {
          errors.push(this.path(definition, "userLayout.savedFilters") + " precisa informar filters.");
        }
        filters.forEach((item) => {
          if (!item || !item.id) {
            errors.push(this.path(definition, "userLayout.savedFilters.filters.id") + " referencia filtro vazio.");
          } else if (!knownFilters[item.id]) {
            errors.push(this.path(definition, "userLayout.savedFilters.filters.id") + " referencia filtro inexistente: " + item.id + ".");
          }
          if (item && item.field) {
            this.validateFieldReference(definition, item.field, "userLayout.savedFilters.filters.field", errors);
          }
          global.CrudUtils.ensureArray(item && item.fields).forEach((fieldName) => {
            this.validateFieldReference(definition, fieldName, "userLayout.savedFilters.filters.fields", errors);
          });
        });
      });
    }

    validateUserLayoutGroup(definition, errors) {
      const group = definition.userLayout && definition.userLayout.grid
        ? definition.userLayout.grid.group
        : [];
      global.CrudUtils.ensureArray(group).forEach((item) => {
        this.validateFieldReference(definition, item && item.field, "userLayout.grid.group.field", errors);
        if (item && item.dir && ["asc", "desc"].indexOf(item.dir) === -1) {
          errors.push(this.path(definition, "userLayout.grid.group.dir") + " deve ser asc ou desc.");
        }
        global.CrudUtils.ensureArray(item && item.aggregates).forEach((aggregate) => {
          this.validateGroupAggregate(definition, aggregate, "userLayout.grid.group.aggregates", errors);
        });
      });
      const groupAggregates = definition.userLayout && definition.userLayout.grid
        ? definition.userLayout.grid.groupAggregates
        : [];
      global.CrudUtils.ensureArray(groupAggregates).forEach((aggregate) => {
        this.validateGroupAggregate(definition, aggregate, "userLayout.grid.groupAggregates", errors);
      });
    }

    validateGroupAggregate(definition, aggregate, context, errors) {
      this.validateFieldReference(definition, aggregate && aggregate.field, context + ".field", errors);
      if (!aggregate || ["count", "sum"].indexOf(aggregate.aggregate) === -1) {
        errors.push(this.path(definition, context + ".aggregate") + " deve ser count ou sum.");
        return;
      }
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      const field = fields[aggregate.field];
      if (aggregate.aggregate === "sum" && field && ["integer", "decimal", "number"].indexOf(field.type) === -1) {
        errors.push(this.path(definition, context) + " so pode somar campos numericos: " + aggregate.field + ".");
      }
    }

    validateForm(definition, errors) {
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      const form = definition.form || {};

      if (form.mode && form.mode !== "popup") {
        errors.push(this.path(definition, "form.mode") + " implementado nesta versao: popup.");
      }

      if (!form.id) {
        errors.push(this.path(definition, "form.id") + " e obrigatorio para gerar ids unicos dos campos.");
      }

      ["maximizeForm", "maximizeOnOpen"].forEach((property) => {
        if (form[property] != null && typeof form[property] !== "boolean") {
          errors.push(this.path(definition, "form." + property) + " precisa ser booleano.");
        }
      });

      if (form.layout === "tabs" && !Array.isArray(form.tabs)) {
        errors.push(this.path(definition, "form.tabs") + " e obrigatorio quando layout = tabs.");
      }
      if (form.layout === "steps" && !Array.isArray(form.steps)) {
        errors.push(this.path(definition, "form.steps") + " e obrigatorio quando layout = steps.");
      }

      const fieldDomIds = {};
      const inspectField = (item) => {
        item = item || {};
        if (!item.field || !fields[item.field]) {
          errors.push(this.path(definition, "form") + " referencia campo inexistente: " + (item.field || "(vazio)") + ".");
        }
        const displayMode = item.renderAs || item.display || item.mode;
        if (displayMode && ["label", "readonly"].indexOf(displayMode) === -1) {
          errors.push(this.path(definition, "form.fields.renderAs") + " deve ser label ou readonly.");
        }
        const fieldDomId = this.buildFieldDomId(definition, form, item);
        if (fieldDomIds[fieldDomId]) {
          errors.push(this.path(definition, "form.fields.id") + " gera id duplicado: " + fieldDomId + ".");
        }
        fieldDomIds[fieldDomId] = true;
      };

      if (form.layout !== "steps") {
        global.CrudUtils.ensureArray(form.tabs).forEach((tab) => {
          global.CrudUtils.ensureArray(tab.sections).forEach((section) => {
            global.CrudUtils.ensureArray(section.fields).forEach(inspectField);
          });
        });
        global.CrudUtils.ensureArray(form.sections).forEach((section) => {
          global.CrudUtils.ensureArray(section.fields).forEach(inspectField);
        });
      }
      global.CrudUtils.ensureArray(form.steps).forEach((step) => {
        if (!step || !step.id || !step.title) {
          errors.push(this.path(definition, "form.steps") + " precisa ter id e title.");
          return;
        }
        if (step.permission && (definition.permissions || {})[step.permission] == null) {
          errors.push(this.path(definition, "form.steps.permission") + " referencia permissao inexistente: " + step.permission + ".");
        }
        const stepFields = this.getStepFieldItems(step);
        if (!stepFields.length && !global.CrudUtils.ensureArray(step.sections).length) {
          errors.push(this.path(definition, "form.steps.fields") + " precisa informar fields ou sections.");
        }
        stepFields.forEach(inspectField);
        global.CrudUtils.ensureArray(step.sections).forEach((section) => {
          global.CrudUtils.ensureArray(section.fields).forEach(inspectField);
        });
        this.validateStepRequiredFields(definition, step, errors);
        this.validateStepLogConfig(definition, step, errors);
      });

      global.CrudUtils.ensureArray(form.buttons).forEach((button) => {
        if (!button.id && !button.action) {
          errors.push(this.path(definition, "form.buttons") + " precisa ter id ou action.");
        }
        this.validateActionEndpointReferences(definition, button, this.path(definition, "form.buttons"), errors);
      });
      this.validateFormConcurrencyWarning(definition, form, errors);
      this.validateFormOtherActions(definition, form, errors);

      if (form.logs && form.logs.enabled !== false) {
        if (!form.logs.url && !form.logs.documentId && !form.logs.endpointId && !form.logs.actionId) {
          errors.push(this.path(definition, "form.logs") + " precisa configurar url, documentId, endpointId ou actionId.");
        } else if (!global.CrudUtils.isAllowedDocumentUrl(form.logs.url)) {
          if (form.logs.url) {
            errors.push(this.path(definition, "form.logs.url") + " deve ser uma URL relativa, http ou https.");
          }
        }
        if (form.logs.url && this.isInlineDocumentUrlDisallowed()) {
          errors.push(this.path(definition, "form.logs.url") + " nao pode usar URL livre em modo producao. Use documentId, endpointId ou actionId.");
        }
      }

      this.validateFormSituation(definition, form, errors);
      this.validatePrintConfig(definition, form.print, this.path(definition, "form.print"), true, errors);
      this.validateFormEvents(definition, form, errors);
    }

    validateFormConcurrencyWarning(definition, form, errors) {
      const config = form.concurrencyWarning || form.concurrentWarning;
      if (!config) {
        return;
      }
      if (typeof config === "string") {
        return;
      }
      if (typeof config !== "object" || Array.isArray(config)) {
        errors.push(this.path(definition, "form.concurrencyWarning") + " precisa ser texto ou objeto.");
        return;
      }
      const validateConfig = (item, label) => {
        if (!item || typeof item !== "object" || Array.isArray(item)) {
          return;
        }
        ["enabled", "confirm", "blocking"].forEach((property) => {
          if (item[property] != null && typeof item[property] !== "boolean") {
            errors.push(label + "." + property + " precisa ser booleano.");
          }
        });
        ["message", "editMessage", "deleteMessage", "title", "confirmText", "cancelText", "confirmIcon", "themeColor", "type"].forEach((property) => {
          if (item[property] != null && typeof item[property] !== "string") {
            errors.push(label + "." + property + " precisa ser texto.");
          }
        });
        if (item.width != null && (typeof item.width !== "number" || item.width <= 0)) {
          errors.push(label + ".width precisa ser numerico positivo.");
        }
        const actions = item.actions || item.visibleIn || item.modes;
        if (actions != null) {
          const list = typeof actions === "string" ? actions.split(",") : actions;
          if (!Array.isArray(list)) {
            errors.push(label + ".actions precisa ser lista ou texto separado por virgula.");
          } else {
            list.forEach((action) => {
              const value = String(action || "").trim();
              if (["edit", "delete"].indexOf(value) === -1) {
                errors.push(label + ".actions deve conter apenas edit ou delete.");
              }
            });
          }
        }
      };
      validateConfig(config, this.path(definition, "form.concurrencyWarning"));
      ["edit", "delete"].forEach((action) => {
        const item = config[action];
        if (typeof item === "string" || item == null) {
          return;
        }
        if (typeof item !== "object" || Array.isArray(item)) {
          errors.push(this.path(definition, "form.concurrencyWarning." + action) + " precisa ser texto ou objeto.");
          return;
        }
        validateConfig(item, this.path(definition, "form.concurrencyWarning." + action));
      });
    }

    getStepFieldItems(step) {
      return global.CrudUtils.ensureArray(step && step.fields).map(function(item) {
        return typeof item === "string" ? { field: item } : item;
      });
    }

    validateStepRequiredFields(definition, step, errors) {
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      global.CrudUtils.ensureArray(step.requiredFields || step.required).forEach((item) => {
        const fieldName = typeof item === "string" ? item : item && item.field;
        if (!fieldName || !fields[fieldName]) {
          errors.push(this.path(definition, "form.steps.requiredFields") + " referencia campo inexistente: " + (fieldName || "(vazio)") + ".");
        }
      });
    }

    validateStepLogConfig(definition, step, errors) {
      const logConfig = step && (step.logs || step.log || step.history);
      if (!logConfig || logConfig.enabled === false) {
        return;
      }
      if (!this.hasActionEndpointReference(definition, logConfig)) {
        errors.push(this.path(definition, "form.steps.logs") + " precisa configurar endpointId, endpoint, api ou url.");
      }
      this.validateActionEndpointReferences(definition, logConfig, this.path(definition, "form.steps.logs"), errors);
    }

    validateFormOtherActions(definition, form, errors) {
      const config = Array.isArray(form.otherActions) ? { actions: form.otherActions } : form.otherActions;
      if (!config || config.enabled === false) {
        return;
      }

      const actions = global.CrudUtils.ensureArray(config.actions || config.items || config.options);
      if (!actions.length) {
        errors.push(this.path(definition, "form.otherActions.actions") + " precisa informar ao menos uma acao.");
        return;
      }

      actions.forEach((action) => {
        if (!action || (!action.id && !action.action)) {
          errors.push(this.path(definition, "form.otherActions.actions") + " precisa ter id ou action.");
          return;
        }
        if (!action.label) {
          errors.push(this.path(definition, "form.otherActions.actions") + " precisa ter label.");
        }
        if (!this.hasActionEndpointReference(definition, action)) {
          errors.push(this.path(definition, "form.otherActions.actions") + " precisa configurar endpointId, endpoint, api, url ou action com endpoint cadastrado.");
        }
        this.validateActionEndpointReferences(definition, action, this.path(definition, "form.otherActions.actions"), errors);
      });
    }

    hasActionEndpointReference(definition, action) {
      if (!action) {
        return false;
      }
      const api = definition.api || {};
      return Boolean(
        action.endpointId ||
        action.actionId ||
        action.endpoint ||
        action.api ||
        action.url ||
        action.endpoints ||
        action.endpointByMode ||
        (action.action && api[action.action])
      );
    }

    buildFieldDomId(definition, form, item) {
      const fallbackFormId = (definition.id || definition.entity || "crud") + "-form";
      const formId = this.normalizeDomId(form.id || fallbackFormId, fallbackFormId);
      const fieldName = item && item.field ? item.field : "field";
      const fieldId = this.normalizeDomId(item && item.id || fieldName, fieldName);
      return formId + "-" + fieldId;
    }

    normalizeDomId(value, fallback) {
      const normalized = String(value || fallback || "item")
        .trim()
        .replace(/[^A-Za-z0-9_-]+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "");
      return normalized || String(fallback || "item");
    }

    validatePrintConfig(definition, printConfig, label, allowEndpoints, errors) {
      if (!printConfig || printConfig.enabled === false) {
        return;
      }
      const allowedFormats = ["excel", "pdf", "csv"];
      const options = global.CrudUtils.ensureArray(printConfig.options || printConfig.formats);
      if (!options.length) {
        errors.push(label + ".options precisa informar ao menos uma opcao.");
      }
      options.forEach((option) => {
        const item = typeof option === "string" ? { format: option } : option || {};
        const format = String(item.format || item.type || item.id || "").toLowerCase();
        if (allowedFormats.indexOf(format) === -1) {
          errors.push(label + ".options possui formato invalido: " + (format || "(vazio)") + ".");
        }
        if (!allowEndpoints && (item.endpointId || item.actionId || item.endpoint || item.api || item.url)) {
          errors.push(label + ".options nao deve configurar API; exportacoes do grid usam o Kendo Grid.");
        }
        if (allowEndpoints) {
          this.validateActionEndpointReferences(definition, item, label + ".options", errors);
        }
      });
    }

    validateFormSituation(definition, form, errors) {
      const situation = form.situation || form.statusFlow;
      if (!situation || situation.enabled === false) {
        return;
      }

      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      const api = definition.api || {};
      if (!situation.field || !fields[situation.field]) {
        errors.push(this.path(definition, "form.situation.field") + " referencia campo inexistente: " + (situation.field || "(vazio)") + ".");
      }
      const display = situation.display || situation.mode || "stepper";
      if (["stepper", "arrowstep", "badge"].indexOf(display) === -1) {
        errors.push(this.path(definition, "form.situation.display") + " deve ser stepper, arrowstep ou badge.");
      }

      global.CrudUtils.ensureArray(situation.steps).forEach((step) => {
        if (!step || step.value == null || !step.text) {
          errors.push(this.path(definition, "form.situation.steps") + " precisa ter value e text.");
        }
      });

      const endpointId = situation.historyEndpointId || situation.endpointId || situation.actionId;
      if (endpointId && !api[endpointId]) {
        errors.push(this.path(definition, "form.situation.historyEndpointId") + " referencia endpoint inexistente: " + endpointId + ".");
      }
      this.validateActionEndpointReferences(definition, {
        endpoint: situation.historyEndpoint || situation.endpoint,
        api: situation.api,
        url: situation.url,
        method: situation.method
      }, this.path(definition, "form.situation"), errors);
    }

    validateFormEvents(definition, form, errors) {
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      const allowedEvents = ["change", "blur", "focus", "enter", "open", "afterLoad"];
      const allowedActions = ["setValue", "clearValue", "readonly", "enabled", "disabled", "visible", "show", "hide", "required", "setOptions", "reloadOptions", "showMessage"];

      global.CrudUtils.ensureArray(form.events).forEach((eventConfig) => {
        const eventName = eventConfig && eventConfig.event ? eventConfig.event : "change";
        if (allowedEvents.indexOf(eventName) === -1) {
          errors.push(this.path(definition, "form.events.event") + " invalido: " + eventName + ".");
        }
        const source = eventConfig && (eventConfig.source || eventConfig.field);
        if (source && !fields[source]) {
          errors.push(this.path(definition, "form.events.source") + " referencia campo inexistente: " + source + ".");
        }
        this.validateActionEndpointReferences(definition, eventConfig, this.path(definition, "form.events"), errors);

        const inspectEffects = (effects) => {
          global.CrudUtils.ensureArray(effects).forEach((effect) => {
            if (!effect || allowedActions.indexOf(effect.action) === -1) {
              errors.push(this.path(definition, "form.events.effects.action") + " invalido: " + (effect && effect.action || "(vazio)") + ".");
            }
            this.validateFormEventTarget(definition, form, effect && (effect.target || effect.field), errors);
          });
        };

        inspectEffects(eventConfig && eventConfig.effects);
        inspectEffects(eventConfig && eventConfig.response && eventConfig.response.effects);
      });
    }

    validateFormEventTarget(definition, form, target, errors) {
      if (!target) {
        return;
      }
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      const value = String(target);
      if (value.indexOf("tab.") === 0) {
        const tabId = value.slice(4);
        const found = global.CrudUtils.ensureArray(form.tabs).some(function(tab) {
          return tab && tab.id === tabId;
        });
        if (!found) {
          errors.push(this.path(definition, "form.events.effects.target") + " referencia aba inexistente: " + tabId + ".");
        }
        return;
      }
      if (value.indexOf("section.") === 0) {
        const sectionId = value.slice(8);
        let found = false;
        global.CrudUtils.ensureArray(form.tabs).forEach(function(tab) {
          global.CrudUtils.ensureArray(tab.sections).forEach(function(section) {
            found = found || section.id === sectionId;
          });
        });
        global.CrudUtils.ensureArray(form.steps).forEach(function(step) {
          global.CrudUtils.ensureArray(step.sections).forEach(function(section) {
            found = found || section.id === sectionId;
          });
        });
        global.CrudUtils.ensureArray(form.sections).forEach(function(section) {
          found = found || section.id === sectionId;
        });
        if (!found) {
          errors.push(this.path(definition, "form.events.effects.target") + " referencia secao inexistente: " + sectionId + ".");
        }
        return;
      }
      if (value.indexOf("step.") === 0) {
        const stepId = value.slice(5);
        const found = global.CrudUtils.ensureArray(form.steps).some(function(step) {
          return step && step.id === stepId;
        });
        if (!found) {
          errors.push(this.path(definition, "form.events.effects.target") + " referencia etapa inexistente: " + stepId + ".");
        }
        return;
      }
      const fieldName = value.indexOf("field.") === 0 ? value.slice(6) : value;
      if (fieldName && !fields[fieldName]) {
        errors.push(this.path(definition, "form.events.effects.target") + " referencia campo inexistente: " + fieldName + ".");
      }
    }

    validatePermissions(definition, errors) {
      const permissions = definition.permissions || {};
      const inspect = function(items, context) {
        global.CrudUtils.ensureArray(items).forEach(function(item) {
          if (item.permission && permissions[item.permission] == null) {
            errors.push(context + " referencia permissao inexistente: " + item.permission + ".");
          }
        });
      };

      inspect(definition.grid && definition.grid.toolbar, this.path(definition, "grid.toolbar"));
      inspect(definition.grid && definition.grid.rowActions, this.path(definition, "grid.rowActions"));
      inspect(definition.grid && definition.grid.bulkActions && definition.grid.bulkActions.actions, this.path(definition, "grid.bulkActions.actions"));
      inspect(definition.form && definition.form.buttons, this.path(definition, "form.buttons"));
      const otherActions = definition.form && Array.isArray(definition.form.otherActions)
        ? definition.form.otherActions
        : definition.form && definition.form.otherActions && definition.form.otherActions.actions;
      inspect(otherActions, this.path(definition, "form.otherActions.actions"));
      inspect(definition.form && definition.form.steps, this.path(definition, "form.steps"));
    }

    validateUrls(definition, errors) {
      const inspectEndpoint = (endpoint, label) => {
        if (!endpoint || !endpoint.url) {
          if (this.isEndpointIdRequired() && endpoint && !endpoint.endpointId && !endpoint.actionId && !endpoint.id) {
            errors.push(label + " precisa informar endpointId ou actionId em modo producao.");
          }
          return;
        }
        if (this.isInlineEndpointUrlDisallowed()) {
          errors.push(label + " nao pode usar url livre em modo producao. Use endpointId ou actionId.");
          return;
        }
        if (!global.CrudUtils.isRelativeUrl(endpoint.url)) {
          errors.push(label + " usa URL externa nao permitida: " + endpoint.url + ".");
        }
      };

      Object.keys(definition.api || {}).forEach((key) => {
        inspectEndpoint(definition.api[key], this.dataSourcePath(definition, "api." + key));
      });

    }

    validateActionEndpointReferences(definition, action, label, errors) {
      if (!action) {
        return;
      }
      if (typeof action === "string") {
        if (!(definition.api || {})[action]) {
          errors.push(label + " referencia endpoint inexistente: " + action + ".");
        }
        return;
      }
      const api = definition.api || {};
      if (action.endpointId && !api[action.endpointId]) {
        errors.push(label + " referencia endpoint inexistente: " + action.endpointId + ".");
      }
      if (action.actionId && !api[action.actionId]) {
        errors.push(label + " referencia endpoint inexistente: " + action.actionId + ".");
      }
      this.validateInlineEndpointObject(action.endpoint, label + ".endpoint", errors);
      this.validateInlineEndpointObject(action.api, label + ".api", errors);
      if (action.endpoint && action.endpoint.url && !global.CrudUtils.isRelativeUrl(action.endpoint.url)) {
        errors.push(label + " possui endpoint com URL externa nao permitida: " + action.endpoint.url + ".");
      }
      if (action.api && action.api.url && !global.CrudUtils.isRelativeUrl(action.api.url)) {
        errors.push(label + " possui api com URL externa nao permitida: " + action.api.url + ".");
      }
      if (this.isInlineEndpointUrlDisallowed() && (action.endpoint && action.endpoint.url || action.api && action.api.url)) {
        errors.push(label + " nao pode usar endpoint/api com URL livre em modo producao. Use endpointId ou actionId.");
      }
      if (this.isInlineDocumentUrlDisallowed() && action.url) {
        errors.push(label + " nao pode usar url livre em modo producao. Use documentId, endpointId ou actionId.");
      }
      if (action.url && !global.CrudUtils.isAllowedDocumentUrl(action.url)) {
        errors.push(label + " possui url invalida: " + action.url + ".");
      }
      this.validateFormButtonValues(definition, action, label, errors);

      global.CrudUtils.ensureArray(Object.keys(action.endpoints || action.endpointByMode || {})).forEach((key) => {
        this.validateActionEndpointReferences(definition, (action.endpoints || action.endpointByMode)[key], label + "." + key, errors);
      });
    }

    validateFormButtonValues(definition, action, label, errors) {
      if (!action || typeof action !== "object") {
        return;
      }
      ["passFormValues", "sendFormValues", "includeFormValues"].forEach(function(property) {
        if (action[property] != null && typeof action[property] !== "boolean") {
          errors.push(label + "." + property + " precisa ser booleano.");
        }
      });
      ["pageMethod", "submitMethod"].forEach(function(property) {
        const value = action[property] == null ? "" : String(action[property]).toUpperCase();
        if (value && ["GET", "POST"].indexOf(value) === -1) {
          errors.push(label + "." + property + " deve ser GET ou POST.");
        }
      });
      const config = action.formValues != null ? action.formValues : action.valuesPayload;
      if (config == null) {
        return;
      }
      if (typeof config === "boolean") {
        return;
      }
      const fields = definition.dataModel && definition.dataModel.fields ? definition.dataModel.fields : {};
      const validateFieldList = (items, property) => {
        global.CrudUtils.ensureArray(items).forEach((item) => {
          const fieldName = String(item || "").trim();
          const rootField = fieldName.split(".")[0];
          if (!fieldName || !fields[rootField]) {
            errors.push(label + "." + property + " referencia campo inexistente: " + (fieldName || "(vazio)") + ".");
          }
        });
      };
      if (Array.isArray(config)) {
        validateFieldList(config, "formValues");
        return;
      }
      if (typeof config !== "object") {
        errors.push(label + ".formValues precisa ser booleano, lista ou objeto.");
        return;
      }
      if (config.enabled != null && typeof config.enabled !== "boolean") {
        errors.push(label + ".formValues.enabled precisa ser booleano.");
      }
      if (config.includeRuntime != null && typeof config.includeRuntime !== "boolean") {
        errors.push(label + ".formValues.includeRuntime precisa ser booleano.");
      }
      ["source", "transport", "mode", "submitAs", "prefix", "payloadParam"].forEach(function(property) {
        if (config[property] != null && typeof config[property] !== "string") {
          errors.push(label + ".formValues." + property + " precisa ser texto.");
        }
      });
      if (config.source && ["record", "values", "data"].indexOf(String(config.source).toLowerCase()) === -1) {
        errors.push(label + ".formValues.source deve ser record, values ou data.");
      }
      ["transport", "mode", "submitAs"].forEach(function(property) {
        const value = config[property] == null ? "" : String(config[property]).toLowerCase();
        if (value && ["auto", "query", "post", "form", "body", "querystring", "get"].indexOf(value) === -1) {
          errors.push(label + ".formValues." + property + " possui valor invalido.");
        }
      });
      validateFieldList(config.fields, "formValues.fields");
      validateFieldList(config.include, "formValues.include");
      ["exclude", "excludes"].forEach(function(property) {
        const value = config[property];
        if (value != null && !Array.isArray(value)) {
          errors.push(label + ".formValues." + property + " precisa ser lista.");
        }
      });
    }

    validateInlineEndpointObject(endpoint, label, errors) {
      if (!endpoint) {
        return;
      }
      if (typeof endpoint === "string") {
        return;
      }
      if (endpoint.url) {
        return;
      }
      if (endpoint.endpointId || endpoint.actionId || endpoint.id) {
        return;
      }
      errors.push(label + " precisa informar url, endpointId ou actionId.");
    }

    validateLogs(definition, errors) {
      const logs = definition.logs;
      if (!logs || logs.enabled === false) {
        return;
      }
      if (!logs.url && !logs.documentId && !logs.endpointId && !logs.actionId) {
        errors.push(this.programPath(definition, "logs") + " precisa configurar url, documentId, endpointId ou actionId.");
        return;
      }
      if (logs.url && !global.CrudUtils.isAllowedDocumentUrl(logs.url)) {
        errors.push(this.programPath(definition, "logs.url") + " deve ser uma URL relativa, http ou https.");
      }
      if (logs.url && this.isInlineDocumentUrlDisallowed()) {
        errors.push(this.programPath(definition, "logs.url") + " nao pode usar URL livre em modo producao. Use documentId, endpointId ou actionId.");
      }
    }

    validateSensitiveKeys(value, path, errors) {
      if (value == null || typeof value !== "object") {
        return;
      }
      const pattern = new RegExp(this.securityPolicy.blockedKeyPattern, "i");
      const allowedSensitiveLikeKeys = {
        forcePasswordChange: true
      };
      Object.keys(value).forEach((key) => {
        const currentPath = path ? path + "." + key : key;
        if (pattern.test(key) && !allowedSensitiveLikeKeys[key]) {
          errors.push(currentPath + " nao deve existir no JSON de tela. Segredos e tokens devem ficar no backend.");
        }
        this.validateSensitiveKeys(value[key], currentPath, errors);
      });
    }

    isInlineEndpointUrlDisallowed() {
      return Boolean(this.securityPolicy && this.securityPolicy.endpoints && this.securityPolicy.endpoints.allowInlineUrls === false);
    }

    isEndpointIdRequired() {
      return Boolean(this.securityPolicy && this.securityPolicy.endpoints && this.securityPolicy.endpoints.requireEndpointIds === true);
    }

    isInlineDocumentUrlDisallowed() {
      return Boolean(this.securityPolicy && this.securityPolicy.documents && this.securityPolicy.documents.allowInlineUrls === false);
    }

    path(definition, path) {
      return definition._definitionStyle === "segmented" ? "crud." + path : path;
    }

    programPath(definition, path) {
      return definition._definitionStyle === "segmented" ? "program." + path : path;
    }

    dataSourcePath(definition, path) {
      return definition._definitionStyle === "segmented" ? "dataSource." + path : path;
    }

    rootPath(definition, key) {
      if (definition._definitionStyle !== "segmented") {
        return key;
      }
      if (key === "api") {
        return "dataSource.api";
      }
      if (key === "grid" || key === "form") {
        return "crud." + key;
      }
      if (key === "id" || key === "module" || key === "entity" || key === "title") {
        return "program." + key;
      }
      return key;
    }

  }

  global.CrudDefinitionValidator = CrudDefinitionValidator;
})(window);
