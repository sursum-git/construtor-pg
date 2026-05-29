(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const admin = new global.AnalyticsPipelinesAdmin({
      root: "#analytics-pipelines-admin-root"
    });
    admin.init();
    global.currentAnalyticsPipelinesAdmin = admin;
  });
})(window);
