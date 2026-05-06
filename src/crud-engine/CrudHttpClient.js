(function(global) {
  "use strict";

  class CrudHttpClient {
    constructor(options) {
      const settings = options || {};
      this.allowLocalFallback = settings.allowLocalFallback !== false;
    }

    request(options) {
      const request = options || {};
      return fetch(request.url, {
        method: request.method || "GET",
        headers: {
          "Accept": "application/json",
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
      }).catch((error) => {
        if (
          this.allowLocalFallback &&
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
