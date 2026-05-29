(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.AnalyticsAuditAdmin) {
      return;
    }
    const httpClient = global.AnalyticsAuditAdminDemoHttpClient
      ? new global.AnalyticsAuditAdminDemoHttpClient()
      : (global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: false }) : null);
    if (!httpClient) {
      return;
    }
    new global.AnalyticsAuditAdmin({
      root: "#analytics-audit-admin-root",
      httpClient: httpClient
    }).init();
  });
})(window);
