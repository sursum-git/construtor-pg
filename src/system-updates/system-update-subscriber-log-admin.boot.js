(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.SystemUpdateSubscriberLogAdmin) {
      return;
    }
    const httpClient = global.SystemUpdatesDemoHttpClient
      ? new global.SystemUpdatesDemoHttpClient()
      : (global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: false }) : null);
    if (!httpClient) {
      return;
    }
    new global.SystemUpdateSubscriberLogAdmin({
      root: "#system-update-subscriber-log-admin-root",
      httpClient: httpClient
    }).init();
  });
})(window);
