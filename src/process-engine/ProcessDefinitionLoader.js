(function(global) {
  "use strict";

  class ProcessDefinitionLoader {
    constructor(options) {
      this.httpClient = options && options.httpClient ? options.httpClient : new global.CrudHttpClient();
    }

    load(options) {
      const request = options || {};
      const policy = request.securityPolicy || global.CrudUtils.normalizeSecurityPolicy({}, {});
      const screenId = request.screenId || request.processId || request.programId;

      if (request.definition) {
        if (policy.definitionSource && policy.definitionSource.allowDirectDefinition === false) {
          return Promise.reject(global.CrudUtils.makeError(
            "DIRECT_DEFINITION_DISABLED",
            "Definicao direta desabilitada pela politica de seguranca."
          ));
        }
        return Promise.resolve(global.CrudUtils.clone(request.definition));
      }

      if (screenId) {
        let runtimeRequest;
        try {
          runtimeRequest = global.CrudUtils.buildScreenDefinitionRequest(screenId, policy, "process");
        } catch (error) {
          return Promise.reject(error);
        }
        return this.httpClient.request(runtimeRequest);
      }

      if (!request.definitionUrl) {
        return Promise.reject(global.CrudUtils.makeError(
          policy.definitionSource && policy.definitionSource.requireScreenId ? "SCREEN_ID_REQUIRED" : "DEFINITION_SOURCE_MISSING",
          policy.definitionSource && policy.definitionSource.requireScreenId
            ? "Informe o screenId da tela de processamento."
            : "Nenhuma definicao de processamento foi informada."
        ));
      }

      if (policy.definitionSource && policy.definitionSource.allowDefinitionUrl === false) {
        return Promise.reject(global.CrudUtils.makeError(
          "DEFINITION_URL_DISABLED",
          "Carregamento por definitionUrl livre desabilitado pela politica de seguranca."
        ));
      }

      return this.httpClient.request({
        url: request.definitionUrl,
        method: "GET"
      });
    }
  }

  global.ProcessDefinitionLoader = ProcessDefinitionLoader;
})(window);
