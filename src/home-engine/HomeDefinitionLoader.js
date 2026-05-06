(function(global) {
  "use strict";

  class HomeDefinitionLoader {
    constructor(options) {
      this.httpClient = options && options.httpClient ? options.httpClient : new global.CrudHttpClient();
    }

    load(options) {
      const request = options || {};
      const policy = request.securityPolicy || global.CrudUtils.normalizeSecurityPolicy({}, {});
      const screenId = request.screenId || request.homeId || request.appId;
      if (request.definition) {
        if (policy.definitionSource && policy.definitionSource.allowDirectDefinition === false) {
          return Promise.reject(global.CrudUtils.makeError(
            "DIRECT_HOME_DEFINITION_DISABLED",
            "Definicao direta da pagina inicial desabilitada pela politica de seguranca."
          ));
        }
        return Promise.resolve(global.CrudUtils.clone(request.definition));
      }
      if (screenId) {
        let runtimeRequest;
        try {
          runtimeRequest = global.CrudUtils.buildScreenDefinitionRequest(screenId, policy, "home");
        } catch (error) {
          return Promise.reject(error);
        }
        return this.httpClient.request(runtimeRequest);
      }
      if (!request.definitionUrl) {
        return Promise.reject(global.CrudUtils.makeError(
          policy.definitionSource && policy.definitionSource.requireScreenId ? "HOME_SCREEN_ID_REQUIRED" : "HOME_DEFINITION_SOURCE_MISSING",
          policy.definitionSource && policy.definitionSource.requireScreenId
            ? "Informe o screenId da pagina inicial."
            : "Nenhuma definicao de pagina inicial foi informada."
        ));
      }
      if (policy.definitionSource && policy.definitionSource.allowDefinitionUrl === false) {
        return Promise.reject(global.CrudUtils.makeError(
          "HOME_DEFINITION_URL_DISABLED",
          "Carregamento da pagina inicial por definitionUrl livre desabilitado pela politica de seguranca."
        ));
      }

      const embedded = this.getEmbeddedDefinition(request.definitionUrl);
      if (global.location && global.location.protocol === "file:" && embedded) {
        return Promise.resolve(embedded);
      }

      return this.httpClient.request({
        url: request.definitionUrl,
        method: "GET"
      }).catch((error) => {
        const fallback = this.getEmbeddedDefinition(request.definitionUrl);
        if (fallback) {
          return fallback;
        }
        throw error;
      });
    }

    getEmbeddedDefinition(url) {
      const embedded = global.HomeDemoEmbedded || {};
      const normalizedUrl = String(url || "").replace(/\\/g, "/");
      if (normalizedUrl.endsWith("examples/home.home.json") && embedded.homeDefinition) {
        return global.CrudUtils.clone(embedded.homeDefinition);
      }
      return null;
    }
  }

  global.HomeDefinitionLoader = HomeDefinitionLoader;
})(window);
