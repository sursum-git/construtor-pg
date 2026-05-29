(function(global) {
  "use strict";

  function bootstrap() {
    if (!global.jQuery || !global.ReportAuditAdmin) {
      return;
    }
    const root = global.document.getElementById("report-audit-admin-root");
    if (!root) {
      return;
    }
    const app = new global.ReportAuditAdmin({
      root: "#report-audit-admin-root"
    });
    global.currentReportAuditAdmin = app;
    app.init();
  }

  if (global.document.readyState === "loading") {
    global.document.addEventListener("DOMContentLoaded", bootstrap);
    return;
  }
  bootstrap();
})(window);
