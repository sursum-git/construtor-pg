(function(global) {
  "use strict";

  class CrudDefinitionLoader {
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
          "DEFINITION_SOURCE_MISSING",
          "Nenhuma definicao de tela foi informada."
        ));
      }
      return this.httpClient.request({
        url: request.definitionUrl,
        method: "GET"
      });
    }
  }

  global.CrudDefinitionLoader = CrudDefinitionLoader;
})(window);
