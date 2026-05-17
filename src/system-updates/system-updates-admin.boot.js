(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.SystemUpdatesAdmin) {
      return;
    }
    const httpClient = global.SystemUpdatesDemoHttpClient
      ? new global.SystemUpdatesDemoHttpClient()
      : (global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: false }) : null);
    if (!httpClient) {
      return;
    }
    new global.SystemUpdatesAdmin({
      root: "#system-updates-admin-root",
      httpClient: httpClient
    }).init();
  });
})(window);
