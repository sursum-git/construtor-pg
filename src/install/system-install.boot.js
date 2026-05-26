(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.SystemInstallPage) {
      return;
    }
    const httpClient = global.SystemInstallDemoHttpClient
      ? new global.SystemInstallDemoHttpClient()
      : (global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: false }) : null);
    if (!httpClient) {
      return;
    }
    new global.SystemInstallPage({
      root: "#system-install-root",
      httpClient: httpClient
    }).init();
  });
})(window);
