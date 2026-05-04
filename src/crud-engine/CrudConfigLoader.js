(function(global) {
  "use strict";

  class CrudConfigLoader {
    load(options) {
      const request = options || {};
      if (request.config) {
        return Promise.resolve(global.CrudUtils.clone(request.config));
      }
      if (!request.configUrl) {
        return Promise.resolve({});
      }

      const normalizedConfigUrl = String(request.configUrl || "").replace(/\\/g, "/");
      if (global.location &&
        global.location.protocol === "file:" &&
        normalizedConfigUrl.endsWith("public/config/crud-engine.config.json") &&
        global.CrudDemoEmbedded &&
        global.CrudDemoEmbedded.config) {
        return Promise.resolve(global.CrudUtils.clone(global.CrudDemoEmbedded.config));
      }

      return fetch(request.configUrl, {
        method: "GET",
        headers: {
          "Accept": "application/json"
        },
        cache: "no-cache"
      }).then(function(response) {
        return response.json().then(function(payload) {
          if (!response.ok) {
            throw payload;
          }
          return payload;
        });
      }).catch(function(error) {
        if (global.location && global.location.protocol === "file:" && global.CrudUtils && global.CrudUtils.readLocalJson) {
          return global.CrudUtils.readLocalJson(request.configUrl).catch(function() {
            if (global.CrudDemoEmbedded && global.CrudDemoEmbedded.config) {
              return global.CrudUtils.clone(global.CrudDemoEmbedded.config);
            }
            throw error;
          });
        }
        throw error;
      });
    }
  }

  global.CrudConfigLoader = CrudConfigLoader;
})(window);
