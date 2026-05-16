(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.ImportExportAdmin || !global.CrudHttpClient) {
      return;
    }

    new global.ImportExportAdmin({
      root: "#import-export-admin-root",
      httpClient: new global.CrudHttpClient({ allowLocalFallback: false })
    }).init();
  });
})(window);
