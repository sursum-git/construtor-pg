(function(global) {
  "use strict";

  const ProgramBuilderLiterals = {
    init(httpClient) {
      if (!global.CrudUtils || typeof global.CrudUtils.loadLiteralBundle !== "function") {
        return Promise.resolve(null);
      }
      return global.CrudUtils.loadLiteralBundle({
        enabled: global.location && global.location.protocol !== "file:",
        locale: "pt-BR",
        url: "/api/runtime/literals/{locale}"
      }, httpClient).catch(function() {
        return null;
      });
    },

    t(key, fallback, params) {
      if (global.CrudUtils && typeof global.CrudUtils.resolveLiteral === "function") {
        return global.CrudUtils.resolveLiteral(key, params || null, fallback || "");
      }
      return String(fallback || key || "");
    }
  };

  global.ProgramBuilderLiterals = ProgramBuilderLiterals;
})(window);
