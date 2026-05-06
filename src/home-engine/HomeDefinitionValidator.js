(function(global) {
  "use strict";

  const ALLOWED_PROGRAM_TYPES = ["iframe", "crud", "html"];
  const ALL_MODULES_ID = "__all__";
  const UNSAFE_KEY_PATTERN = /^(on[A-Z]|on_|script|eval|template|handler)$/i;
  const UNSAFE_HTML_PATTERN = /<\s*script|javascript\s*:|on[a-z]+\s*=|<\s*(object|embed|base|meta|link)\b/i;

  class HomeDefinitionValidator {
    validate(definition, options) {
      const errors = [];
      this.securityPolicy = options && options.securityPolicy || global.CrudUtils.normalizeSecurityPolicy({}, {});

      if (!definition || typeof definition !== "object" || Array.isArray(definition)) {
        errors.push("A definicao da pagina inicial precisa ser um objeto JSON.");
      } else {
        this.validateRoot(definition, errors);
        this.validateLayout(definition, errors);
        this.validatePrograms(definition, errors);
        this.validateNavigation(definition, errors);
        this.validatePermissions(definition, errors);
        this.validateSensitiveKeys(definition, "", errors);
        this.validateUnsafeContent(definition, "", errors);
      }

      if (errors.length) {
        throw global.CrudUtils.makeError("INVALID_HOME_DEFINITION", "JSON de pagina inicial invalido.", { errors });
      }

      return true;
    }

    validateRoot(definition, errors) {
      if (!definition.schemaVersion) {
        errors.push("schemaVersion e obrigatorio.");
      }
      if (definition.pageType !== "home") {
        errors.push("pageType deve ser home.");
      }
      if (!definition.app || !definition.app.title) {
        errors.push("app.title e obrigatorio.");
      }
      this.validateAppLogo(definition.app || {}, errors);
      this.validateCurrentSubscriber(definition.currentSubscriber || definition.currentTenant || definition.tenant || definition.subscriber, errors);
      if (!Array.isArray(definition.programs) || !definition.programs.length) {
        errors.push("programs deve informar ao menos um programa.");
      }
    }

    validateAppLogo(app, errors) {
      const logo = app.logo || app.companyLogo || app.logoUrl;
      if (!logo) {
        return;
      }
      if (typeof logo === "string") {
        this.validateUrl(logo, "app.logo", errors, false);
        return;
      }
      if (typeof logo !== "object" || Array.isArray(logo)) {
        errors.push("app.logo precisa ser string ou objeto.");
        return;
      }
      this.validateUrl(logo.url, "app.logo.url", errors, true);
      ["showTitle", "showSubtitle"].forEach(function(property) {
        if (logo[property] != null && typeof logo[property] !== "boolean") {
          errors.push("app.logo." + property + " precisa ser booleano.");
        }
      });
    }

    validateCurrentSubscriber(subscriber, errors) {
      if (subscriber == null) {
        return;
      }
      if (typeof subscriber === "string") {
        return;
      }
      if (typeof subscriber !== "object" || Array.isArray(subscriber)) {
        errors.push("currentSubscriber precisa ser texto ou objeto.");
        return;
      }
      ["id", "name", "displayName", "title", "code", "document", "label"].forEach(function(property) {
        if (subscriber[property] != null && typeof subscriber[property] !== "string") {
          errors.push("currentSubscriber." + property + " precisa ser texto.");
        }
      });
    }

    validateLayout(definition, errors) {
      const sidebar = definition.layout && definition.layout.sidebar ? definition.layout.sidebar : {};
      const appbar = definition.layout && definition.layout.appbar ? definition.layout.appbar : {};
      if (sidebar.component && sidebar.component !== "kendoTreeView") {
        errors.push("layout.sidebar.component deve ser kendoTreeView.");
      }
      ["collapsible", "collapsed", "expanded"].forEach(function(property) {
        if (sidebar[property] != null && typeof sidebar[property] !== "boolean") {
          errors.push("layout.sidebar." + property + " precisa ser booleano.");
        }
      });
      ["showSidebarToggle", "showRefresh", "showThemeSwitch", "showFavoriteToggle", "showCurrentSubscriber", "showUserMenu"].forEach(function(property) {
        if (appbar[property] != null && typeof appbar[property] !== "boolean") {
          errors.push("layout.appbar." + property + " precisa ser booleano.");
        }
      });
      this.validateChatConfig(appbar.chat || definition.chat, errors);
      this.validateSupportConfig(appbar.support || definition.support, errors);
      this.validateAiChatConfig(appbar.aiChat || appbar.iaChat || definition.aiChat || definition.iaChat, errors);
      this.validateAppbarListConfig(appbar.alerts || definition.alerts, "layout.appbar.alerts", errors);
      this.validateAppbarListConfig(appbar.requests || definition.requests, "layout.appbar.requests", errors);
      global.CrudUtils.ensureArray(appbar.userMenu && appbar.userMenu.items).forEach((item, index) => {
        const label = "layout.appbar.userMenu.items[" + index + "]";
        if (!item || !item.id || !item.label) {
          errors.push(label + " precisa ter id e label.");
          return;
        }
        this.validateUrl(item.url, label + ".url", errors, false);
      });
    }

    validateChatConfig(chat, errors) {
      if (!chat) {
        return;
      }
      const label = "layout.appbar.chat";
      if (typeof chat !== "object" || Array.isArray(chat)) {
        errors.push(label + " precisa ser um objeto.");
        return;
      }
      if (chat.enabled != null && typeof chat.enabled !== "boolean") {
        errors.push(label + ".enabled precisa ser booleano.");
      }
      ["width", "height"].forEach(function(property) {
        if (chat[property] != null && (typeof chat[property] !== "number" || chat[property] <= 0)) {
          errors.push(label + "." + property + " precisa ser numerico positivo.");
        }
      });

      const endpoints = chat.endpoints || {};
      const hasStaticContacts = global.CrudUtils.ensureArray(chat.contacts || chat.users || chat.recipients).length > 0;
      this.validateChatContacts(chat, errors);
      this.validateChatEndpoint(endpoints.contacts || chat.contactsUrl, label + ".endpoints.contacts", errors, chat.enabled === true && !hasStaticContacts);
      this.validateChatEndpoint(endpoints.history || chat.historyUrl, label + ".endpoints.history", errors, false);
      this.validateChatEndpoint(endpoints.send || chat.sendUrl, label + ".endpoints.send", errors, chat.enabled === true);
    }

    validateChatContacts(chat, errors) {
      const source = chat.contacts || chat.users || chat.recipients;
      if (source == null) {
        return;
      }
      if (!Array.isArray(source)) {
        errors.push("layout.appbar.chat.contacts precisa ser uma lista quando informado.");
        return;
      }
      source.forEach(function(item, index) {
        const label = "layout.appbar.chat.contacts[" + index + "]";
        if (typeof item === "string") {
          if (!item.trim()) {
            errors.push(label + " nao pode ser vazio.");
          }
          return;
        }
        if (!item || typeof item !== "object" || Array.isArray(item)) {
          errors.push(label + " precisa ser texto ou objeto.");
          return;
        }
        if (!item.id && !item.userId && !item.email) {
          errors.push(label + " precisa informar id, userId ou email.");
        }
        if (!item.name && !item.fullName && !item.username && !item.email) {
          errors.push(label + " precisa informar name, fullName, username ou email.");
        }
      });
    }

    validateChatEndpoint(endpoint, label, errors, required) {
      if (!endpoint) {
        if (required) {
          errors.push(label + " e obrigatorio quando o chat esta habilitado.");
        }
        return;
      }
      if (typeof endpoint === "string") {
        if (this.isInlineEndpointUrlDisallowed()) {
          errors.push(label + " nao pode usar URL livre em modo producao. Use endpointId ou actionId.");
          return;
        }
        this.validateUrl(endpoint, label, errors, true);
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
      this.validateUrl(endpoint.url, label + ".url", errors, true);
      if (endpoint.method && ["GET", "POST"].indexOf(String(endpoint.method).toUpperCase()) === -1) {
        errors.push(label + ".method deve ser GET ou POST.");
      }
    }

    validateAiChatConfig(chat, errors) {
      if (!chat) {
        return;
      }
      const label = "layout.appbar.aiChat";
      if (typeof chat !== "object" || Array.isArray(chat)) {
        errors.push(label + " precisa ser um objeto.");
        return;
      }
      if (chat.enabled != null && typeof chat.enabled !== "boolean") {
        errors.push(label + ".enabled precisa ser booleano.");
      }
      ["width", "height"].forEach(function(property) {
        if (chat[property] != null && (typeof chat[property] !== "number" || chat[property] <= 0)) {
          errors.push(label + "." + property + " precisa ser numerico positivo.");
        }
      });

      const endpoints = chat.endpoints || {};
      this.validateChatEndpoint(endpoints.history || chat.historyUrl, label + ".endpoints.history", errors, false);
      this.validateChatEndpoint(endpoints.send || chat.sendUrl, label + ".endpoints.send", errors, chat.enabled === true);
    }

    validateSupportConfig(support, errors) {
      if (!support) {
        return;
      }
      const label = "layout.appbar.support";
      if (typeof support !== "object" || Array.isArray(support)) {
        errors.push(label + " precisa ser um objeto.");
        return;
      }
      if (support.enabled != null && typeof support.enabled !== "boolean") {
        errors.push(label + ".enabled precisa ser booleano.");
      }
      ["width", "height"].forEach(function(property) {
        if (support[property] != null && (typeof support[property] !== "number" || support[property] <= 0)) {
          errors.push(label + "." + property + " precisa ser numerico positivo.");
        }
      });
      this.validateSupportList(support.sectors, label + ".sectors", errors, false);
      this.validateSupportList(support.priorities, label + ".priorities", errors, false);

      const endpoints = support.endpoints || {};
      this.validateChatEndpoint(endpoints.onlineUsers || support.onlineUsersUrl, label + ".endpoints.onlineUsers", errors, support.enabled === true);
      this.validateChatEndpoint(endpoints.history || support.historyUrl, label + ".endpoints.history", errors, false);
      this.validateChatEndpoint(endpoints.send || support.sendUrl, label + ".endpoints.send", errors, support.enabled === true);
      this.validateChatEndpoint(endpoints.createRequest || support.createRequestUrl, label + ".endpoints.createRequest", errors, support.enabled === true);
      this.validateChatEndpoint(endpoints.requestStatus || support.requestStatusUrl, label + ".endpoints.requestStatus", errors, false);
    }

    validateSupportList(items, label, errors, required) {
      if (items == null) {
        if (required) {
          errors.push(label + " e obrigatorio.");
        }
        return;
      }
      if (!Array.isArray(items)) {
        errors.push(label + " precisa ser uma lista.");
        return;
      }
      items.forEach(function(item, index) {
        const itemLabel = label + "[" + index + "]";
        if (typeof item === "string") {
          if (!item.trim()) {
            errors.push(itemLabel + " nao pode ser vazio.");
          }
          return;
        }
        if (!item || typeof item !== "object" || Array.isArray(item)) {
          errors.push(itemLabel + " precisa ser texto ou objeto.");
          return;
        }
        if (!item.id && !item.name && !item.title) {
          errors.push(itemLabel + " precisa informar id, name ou title.");
        }
      });
    }

    validateAppbarListConfig(config, label, errors) {
      if (!config) {
        return;
      }
      if (typeof config !== "object" || Array.isArray(config)) {
        errors.push(label + " precisa ser um objeto.");
        return;
      }
      if (config.enabled != null && typeof config.enabled !== "boolean") {
        errors.push(label + ".enabled precisa ser booleano.");
      }
      ["width", "height"].forEach(function(property) {
        if (config[property] != null && (typeof config[property] !== "number" || config[property] <= 0)) {
          errors.push(label + "." + property + " precisa ser numerico positivo.");
        }
      });

      const endpoints = config.endpoints || {};
      this.validateListEndpoint(endpoints.list || config.listUrl || config.url, label + ".endpoints.list", errors, config.enabled === true);
    }

    validateListEndpoint(endpoint, label, errors, required) {
      if (!endpoint) {
        if (required) {
          errors.push(label + " e obrigatorio quando o recurso esta habilitado.");
        }
        return;
      }
      if (typeof endpoint === "string") {
        if (this.isInlineEndpointUrlDisallowed()) {
          errors.push(label + " nao pode usar URL livre em modo producao. Use endpointId ou actionId.");
          return;
        }
        this.validateUrl(endpoint, label, errors, true);
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
      this.validateUrl(endpoint.url, label + ".url", errors, true);
      if (endpoint.method && ["GET", "POST"].indexOf(String(endpoint.method).toUpperCase()) === -1) {
        errors.push(label + ".method deve ser GET ou POST.");
      }
    }

    validatePrograms(definition, errors) {
      const ids = {};

      global.CrudUtils.ensureArray(definition.programs).forEach((program, index) => {
        const label = "programs[" + index + "]";
        if (!program || typeof program !== "object") {
          errors.push(label + " precisa ser um objeto.");
          return;
        }
        if (!program.id || !program.title) {
          errors.push(label + " precisa ter id e title.");
        }
        if (program.id) {
          if (ids[program.id]) {
            errors.push(label + " possui id repetido: " + program.id + ".");
          }
          ids[program.id] = true;
        }

        const type = program.type || "iframe";
        if (ALLOWED_PROGRAM_TYPES.indexOf(type) === -1) {
          errors.push(label + ".type invalido: " + type + ".");
          return;
        }
        ["favorite", "isFavorite"].forEach(function(property) {
          if (program[property] != null && typeof program[property] !== "boolean") {
            errors.push(label + "." + property + " precisa ser booleano.");
          }
        });

        this.validateDocumentUrl(program.openUrl, label + ".openUrl", errors, false);

        if (type === "iframe") {
          this.validateDocumentUrl(program.url, label + ".url", errors, true);
        }
        if (type === "crud") {
          if (!program.definition && !program.definitionUrl && !program.screenId) {
            errors.push(label + " precisa informar definition, definitionUrl ou screenId quando type=crud.");
          }
          if (program.definition && this.securityPolicy.definitionSource.allowDirectDefinition === false) {
            errors.push(label + ".definition nao pode ser usado em modo producao. Use screenId.");
          }
          if (program.definitionUrl && this.securityPolicy.definitionSource.allowDefinitionUrl === false) {
            errors.push(label + ".definitionUrl nao pode ser usado em modo producao. Use screenId.");
          }
          this.validateDocumentUrl(program.definitionUrl, label + ".definitionUrl", errors, false);
        }
        if (type === "html") {
          if (!program.html && !program.htmlUrl) {
            errors.push(label + " precisa informar html ou htmlUrl quando type=html.");
          }
          if (program.html && this.securityPolicy.content.allowInlineHtml === false) {
            errors.push(label + ".html nao pode ser usado em modo producao. Use um tipo fechado ou conteudo servido pelo backend.");
          }
          this.validateDocumentUrl(program.htmlUrl, label + ".htmlUrl", errors, false);
          if (program.html && UNSAFE_HTML_PATTERN.test(String(program.html))) {
            errors.push(label + ".html possui conteudo inseguro.");
          }
        }
        this.validateProgramLogs(program.logs, label + ".logs", errors);
      });
    }

    validateNavigation(definition, errors) {
      const programIds = global.CrudUtils.ensureArray(definition.programs).reduce(function(acc, program) {
        if (program && program.id) {
          acc[program.id] = true;
        }
        return acc;
      }, {});

      const moduleIds = {};
      const modules = definition.navigation && definition.navigation.modules;
      if (modules != null && !Array.isArray(modules)) {
        errors.push("navigation.modules precisa ser uma lista quando informado.");
      }
      global.CrudUtils.ensureArray(modules).forEach(function(module, moduleIndex) {
        const label = "navigation.modules[" + moduleIndex + "]";
        if (!module || !module.id || !module.title) {
          errors.push(label + " precisa ter id e title.");
          return;
        }
        if (String(module.id) === ALL_MODULES_ID) {
          errors.push(label + ".id usa valor reservado: " + ALL_MODULES_ID + ".");
        }
        if (moduleIds[module.id]) {
          errors.push(label + " possui id repetido: " + module.id + ".");
        }
        moduleIds[module.id] = true;
      });

      const initialModuleId = String(definition.navigation && definition.navigation.initialModuleId || "").trim();
      if (initialModuleId && initialModuleId !== ALL_MODULES_ID && modules && !moduleIds[initialModuleId]) {
        errors.push("navigation.initialModuleId referencia modulo inexistente: " + initialModuleId + ".");
      }

      const groups = definition.navigation && definition.navigation.groups;
      if (!Array.isArray(groups) || !groups.length) {
        errors.push("navigation.groups deve informar ao menos um grupo.");
        return;
      }

      groups.forEach((group, groupIndex) => {
        const label = "navigation.groups[" + groupIndex + "]";
        if (!group || !group.id || !group.title) {
          errors.push(label + " precisa ter id e title.");
        }
        if (group && group.moduleId && modules && !moduleIds[group.moduleId]) {
          errors.push(label + ".moduleId referencia modulo inexistente: " + group.moduleId + ".");
        }
        const items = global.CrudUtils.ensureArray(group && group.items);
        if (!items.length) {
          errors.push(label + ".items deve informar ao menos um item.");
        }
        items.forEach((item, itemIndex) => {
          const itemLabel = label + ".items[" + itemIndex + "]";
          if (!item || !item.programId) {
            errors.push(itemLabel + " precisa ter programId.");
            return;
          }
          if (!programIds[item.programId]) {
            errors.push(itemLabel + " referencia programa inexistente: " + item.programId + ".");
          }
          ["favorite", "isFavorite"].forEach(function(property) {
            if (item[property] != null && typeof item[property] !== "boolean") {
              errors.push(itemLabel + "." + property + " precisa ser booleano.");
            }
          });
        });
      });
    }

    validatePermissions(definition, errors) {
      const permissions = definition.permissions || {};
      const inspect = function(item, label) {
        if (item && item.permission && permissions[item.permission] == null) {
          errors.push(label + " referencia permissao inexistente: " + item.permission + ".");
        }
      };

      global.CrudUtils.ensureArray(definition.programs).forEach(function(program, index) {
        inspect(program, "programs[" + index + "]");
      });
      global.CrudUtils.ensureArray(definition.navigation && definition.navigation.modules).forEach(function(module, index) {
        inspect(module, "navigation.modules[" + index + "]");
      });
      global.CrudUtils.ensureArray(definition.navigation && definition.navigation.groups).forEach(function(group, groupIndex) {
        global.CrudUtils.ensureArray(group && group.items).forEach(function(item, itemIndex) {
          inspect(item, "navigation.groups[" + groupIndex + "].items[" + itemIndex + "]");
        });
      });
      global.CrudUtils.ensureArray(definition.layout && definition.layout.appbar && definition.layout.appbar.userMenu && definition.layout.appbar.userMenu.items).forEach(function(item, index) {
        inspect(item, "layout.appbar.userMenu.items[" + index + "]");
      });
      const appbar = definition.layout && definition.layout.appbar ? definition.layout.appbar : {};
      inspect(appbar.chat || definition.chat, "layout.appbar.chat");
      inspect(appbar.support || definition.support, "layout.appbar.support");
      inspect(appbar.aiChat || appbar.iaChat || definition.aiChat || definition.iaChat, "layout.appbar.aiChat");
      inspect(appbar.alerts || definition.alerts, "layout.appbar.alerts");
      inspect(appbar.requests || definition.requests, "layout.appbar.requests");
    }

    validateUrl(url, label, errors, required) {
      if (!url) {
        if (required) {
          errors.push(label + " e obrigatorio.");
        }
        return;
      }
      if (!global.CrudUtils.isAllowedDocumentUrl(url)) {
        errors.push(label + " deve ser uma URL relativa, http ou https.");
      }
      if (this.securityPolicy.production && !this.securityPolicy.documents.allowExternalUrls && global.CrudUtils.isHttpUrl(url)) {
        errors.push(label + " nao pode usar URL externa em modo seguro.");
      }
    }

    validateProgramLogs(logs, label, errors) {
      if (!logs || logs.enabled === false) {
        return;
      }
      if (typeof logs !== "object" || Array.isArray(logs)) {
        errors.push(label + " precisa ser um objeto.");
        return;
      }
      if (!logs.url && !logs.documentId && !logs.endpointId && !logs.actionId) {
        errors.push(label + " precisa configurar url, documentId, endpointId ou actionId.");
        return;
      }
      if (logs.url && this.isInlineDocumentUrlDisallowed()) {
        errors.push(label + ".url nao pode usar URL livre em modo producao. Use documentId, endpointId ou actionId.");
        return;
      }
      this.validateUrl(logs.url, label + ".url", errors, false);
    }

    validateDocumentUrl(url, label, errors, required) {
      if (!url) {
        if (required) {
          errors.push(label + " e obrigatorio.");
        }
        return;
      }
      if (this.isInlineDocumentUrlDisallowed()) {
        errors.push(label + " nao pode usar URL livre em modo producao. Use identificador controlado pelo backend.");
        return;
      }
      this.validateUrl(url, label, errors, required);
    }

    validateUnsafeContent(value, path, errors) {
      if (value == null || typeof value !== "object") {
        return;
      }

      Object.keys(value).forEach((key) => {
        const currentPath = path ? path + "." + key : key;
        if (key === "definition") {
          return;
        }
        if (UNSAFE_KEY_PATTERN.test(key)) {
          errors.push(currentPath + " nao e permitido no JSON da pagina inicial.");
        }
        const item = value[key];
        if (typeof item === "string" && key !== "html" && UNSAFE_HTML_PATTERN.test(item)) {
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
          errors.push(currentPath + " nao deve existir no JSON da pagina inicial. Segredos e tokens devem ficar no backend.");
        }
        this.validateSensitiveKeys(value[key], currentPath, errors);
      });
    }

    isInlineEndpointUrlDisallowed() {
      return Boolean(this.securityPolicy && this.securityPolicy.endpoints && this.securityPolicy.endpoints.allowInlineUrls === false);
    }

    isInlineDocumentUrlDisallowed() {
      return Boolean(this.securityPolicy && this.securityPolicy.documents && this.securityPolicy.documents.allowInlineUrls === false);
    }
  }

  global.HomeDefinitionValidator = HomeDefinitionValidator;
})(window);
