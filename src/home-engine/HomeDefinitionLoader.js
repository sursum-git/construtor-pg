(function(global) {
  "use strict";

  class HomeDefinitionLoader {
    constructor(options) {
      this.httpClient = options && options.httpClient ? options.httpClient : new global.CrudHttpClient();
    }

    load(options) {
      const request = options || {};
      if (request.definition) {
        return Promise.resolve(global.CrudUtils.clone(request.definition));
      }
      if (!request.definitionUrl) {
        return Promise.reject(global.CrudUtils.makeError(
          "HOME_DEFINITION_SOURCE_MISSING",
          "Nenhuma definicao de pagina inicial foi informada."
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
