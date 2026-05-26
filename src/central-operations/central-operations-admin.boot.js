(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.CentralOperationsAdmin || !global.CrudHttpClient) {
      return;
    }
    new global.CentralOperationsAdmin({
      root: "#central-operations-admin-root",
      httpClient: new global.CrudHttpClient({ allowLocalFallback: false })
    }).init();
  });
})(window);
