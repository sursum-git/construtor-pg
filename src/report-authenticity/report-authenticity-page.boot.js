(function(global) {
  "use strict";

  global.__REPORT_AUTHENTICITY_AUTO_INIT__ = global.__REPORT_AUTHENTICITY_AUTO_INIT__ !== false;

  document.addEventListener("DOMContentLoaded", function() {
    if (global.__REPORT_AUTHENTICITY_AUTO_INIT__ === false) {
      return;
    }
    const app = new global.ReportAuthenticityPage({
      root: "#report-authenticity-root"
    });
    app.init();
    global.reportAuthenticityApp = app;
  });
})(window);
