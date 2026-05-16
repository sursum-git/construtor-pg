(function(global) {
  "use strict";

  const ALLOWED_FIELD_TYPES = [
    "text",
    "string",
    "number",
    "integer",
    "decimal",
    "date",
    "datetime",
    "boolean",
    "enum",
    "option",
    "dropdown"
  ];
  const ALLOWED_WAIT_MODES = ["auto", "sse", "polling", "none"];
  const ALLOWED_RESULT_TYPES = ["message", "grid", "report", "job", "properties"];
  const UNSAFE_KEY_PATTERN = /^(on[A-Z]|on_|script|eval|template|handler|function|callback)$/i;
  const UNSAFE_TEXT_PATTERN = /<\s*script|javascript\s*:|on[a-z]+\s*=|\beval\s*\(|\bFunction\s*\(/i;

  class ProcessDefinitionValidator {
    validate(definition, options) {
      const errors = [];
      this.securityPolicy = options && options.securityPolicy || global.CrudUtils.normalizeSecurityPolicy({}, {});

      if (!definition || typeof definition !== "object" || Array.isArray(definition)) {
        errors.push("A definicao de processamento precisa ser um objeto JSON.");
      } else {
        this.validateRoot(definition, errors);
        this.validateParameters(definition, errors);
        this.validateEndpoints(definition, errors);
        this.validateWait(definition, errors);
        this.validateResult(definition, errors);
        this.validateSensitiveKeys(definition, "", errors);
        this.validateUnsafeContent(definition, "", errors);
      }

      if (errors.length) {
        throw global.CrudUtils.makeError("INVALID_PROCESS_DEFINITION", "JSON de processamento invalido.", { errors });
      }

      return true;
    }

    validateRoot(definition, errors) {
      if (!definition.schemaVersion) {
        errors.push("schemaVersion e obrigatorio.");
      }
      if (definition.pageType !== "process") {
        errors.push("pageType deve ser process.");
      }
      const program = definition.program || {};
      if (!program.title && !definition.title) {
        errors.push("program.title e obrigatorio.");
      }
      if (!definition.process || typeof definition.process !== "object" || Array.isArray(definition.process)) {
        errors.push("process precisa ser um objeto.");
      }
    }

    validateParameters(definition, errors) {
      const parameters = definition.process && definition.process.parameters || {};
      const fields = global.CrudUtils.ensureArray(parameters.fields);
      if (!fields.length) {
        errors.push("process.parameters.fields deve informar ao menos um parametro.");
        return;
      }

      const ids = {};
      fields.forEach((field, index) => {
        const label = "process.parameters.fields[" + index + "]";
        if (!field || typeof field !== "object" || Array.isArray(field)) {
          errors.push(label + " precisa ser um objeto.");
          return;
        }
        const name = field.field || field.name || field.id;
        if (!name) {
          errors.push(label + " precisa informar field, name ou id.");
        }
        const id = field.id || name;
        if (id) {
          if (ids[id]) {
            errors.push(label + ".id repetido: " + id + ".");
          }
          ids[id] = true;
        }
        const type = String(field.type || "text");
        if (ALLOWED_FIELD_TYPES.indexOf(type) === -1) {
          errors.push(label + ".type invalido: " + type + ".");
        }
        if (field.required != null && typeof field.required !== "boolean") {
          errors.push(label + ".required precisa ser booleano.");
        }
        if (field.readonly != null && typeof field.readonly !== "boolean") {
          errors.push(label + ".readonly precisa ser booleano.");
        }
        if ((type === "enum" || type === "option" || type === "dropdown") && field.options != null && !Array.isArray(field.options)) {
          errors.push(label + ".options precisa ser uma lista.");
        }
        if (field.technicalProperties != null && !this.isValidTechnicalProperties(field.technicalProperties)) {
          errors.push(label + ".technicalProperties precisa ser lista ou objeto de propriedades tecnicas.");
        }
      });
    }

    isValidTechnicalProperties(value) {
      if (value == null) {
        return true;
      }
      if (Array.isArray(value)) {
        return value.every(function(item) {
          return item == null ||
            typeof item === "string" ||
            typeof item === "number" ||
            typeof item === "boolean" ||
            (typeof item === "object" && !Array.isArray(item));
        });
      }
      return typeof value === "object" && !Array.isArray(value);
    }

    validateEndpoints(definition, errors) {
      const process = definition.process || {};
      const endpoints = Object.assign(
        {},
        definition.api || {},
        definition.dataSource && definition.dataSource.api || {},
        process.endpoints || {}
      );

      const processEndpoint = endpoints.process || process.endpoint || process.url;
      this.validateEndpoint(processEndpoint, "process.endpoints.process", errors, true);
      this.validateEndpoint(endpoints.status || process.statusEndpoint, "process.endpoints.status", errors, false);
    }

    validateWait(definition, errors) {
      const wait = definition.process && definition.process.wait || {};
      if (wait.mode && ALLOWED_WAIT_MODES.indexOf(String(wait.mode)) === -1) {
        errors.push("process.wait.mode deve ser auto, sse, polling ou none.");
      }
      if (wait.pollIntervalSeconds != null && (typeof wait.pollIntervalSeconds !== "number" || wait.pollIntervalSeconds <= 0)) {
        errors.push("process.wait.pollIntervalSeconds precisa ser numerico positivo.");
      }
      if (wait.events && typeof wait.events === "object") {
        this.validateEndpoint(wait.events.endpoint || wait.events.status || wait.events.url, "process.wait.events", errors, false);
      }
    }

    validateResult(definition, errors) {
      const result = definition.process && definition.process.result || {};
      if (result.type && ALLOWED_RESULT_TYPES.indexOf(String(result.type)) === -1) {
        errors.push("process.result.type deve ser message, grid, report, job ou properties.");
      }
      if (result.openReportInNewTab != null && typeof result.openReportInNewTab !== "boolean") {
        errors.push("process.result.openReportInNewTab precisa ser booleano.");
      }
    }

    validateEndpoint(endpoint, label, errors, required) {
      if (!endpoint) {
        if (required) {
          errors.push(label + " e obrigatorio.");
        }
        return;
      }
      if (typeof endpoint === "string") {
        if (this.isInlineEndpointUrlDisallowed()) {
          errors.push(label + " nao pode usar URL livre em modo producao. Use endpointId ou actionId.");
          return;
        }
        if (!global.CrudUtils.isAllowedDocumentUrl(endpoint)) {
          errors.push(label + " deve ser uma URL relativa, http ou https.");
        }
        return;
      }
      if (typeof endpoint !== "object" || Array.isArray(endpoint)) {
        errors.push(label + " precisa ser URL ou objeto com url, endpointId ou actionId.");
        return;
      }
      if (!endpoint.url && (endpoint.endpointId || endpoint.actionId || endpoint.id)) {
        return;
      }
      if (endpoint.url && this.isInlineEndpointUrlDisallowed()) {
        errors.push(label + ".url nao pode usar URL livre em modo producao. Use endpointId ou actionId.");
        return;
      }
      if (!endpoint.url) {
        errors.push(label + " precisa informar url, endpointId ou actionId.");
        return;
      }
      if (!global.CrudUtils.isAllowedDocumentUrl(endpoint.url)) {
        errors.push(label + ".url deve ser uma URL relativa, http ou https.");
      }
      if (endpoint.method && ["GET", "POST"].indexOf(String(endpoint.method).toUpperCase()) === -1) {
        errors.push(label + ".method deve ser GET ou POST.");
      }
    }

    validateUnsafeContent(value, path, errors) {
      if (value == null || typeof value !== "object") {
        return;
      }
      Object.keys(value).forEach((key) => {
        const currentPath = path ? path + "." + key : key;
        if (UNSAFE_KEY_PATTERN.test(key)) {
          errors.push(currentPath + " nao e permitido no JSON de processamento.");
        }
        const item = value[key];
        if (typeof item === "string" && UNSAFE_TEXT_PATTERN.test(item)) {
          errors.push(currentPath + " possui conteudo inseguro.");
        }
        if (item && typeof item === "object") {
          this.validateUnsafeContent(item, currentPath, errors);
        }
      });
    }

    validateSensitiveKeys(value, path, errors) {
      if (value == null || typeof value !== "object") {
        return;
      }
      const pattern = new RegExp(this.securityPolicy.blockedKeyPattern, "i");
      Object.keys(value).forEach((key) => {
        const currentPath = path ? path + "." + key : key;
        if (pattern.test(key)) {
          errors.push(currentPath + " nao deve existir no JSON de processamento. Segredos e tokens devem ficar no backend.");
        }
        this.validateSensitiveKeys(value[key], currentPath, errors);
      });
    }

    isInlineEndpointUrlDisallowed() {
      return Boolean(this.securityPolicy && this.securityPolicy.endpoints && this.securityPolicy.endpoints.allowInlineUrls === false);
    }
  }

  global.ProcessDefinitionValidator = ProcessDefinitionValidator;
})(window);
