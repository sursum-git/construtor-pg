(function(global) {
  "use strict";

  class CrudHttpClient {
    request(options) {
      const request = options || {};
      return fetch(request.url, {
        method: request.method || "GET",
        headers: {
          "Content-Type": "application/json"
        },
        body: request.method && request.method !== "GET" ? JSON.stringify(request.data || {}) : undefined
      }).then(function(response) {
        return response.json().then(function(payload) {
          if (!response.ok) {
            throw payload;
          }
          return payload;
        });
      }).catch(function(error) {
        if (
          global.location &&
          global.location.protocol === "file:" &&
          (!request.method || request.method === "GET") &&
          global.CrudUtils &&
          global.CrudUtils.readLocalJson
        ) {
          return global.CrudUtils.readLocalJson(request.url);
        }
        throw error;
      });
    }
  }

  global.CrudHttpClient = CrudHttpClient;
})(window);
