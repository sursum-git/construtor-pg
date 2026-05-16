(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.ProgramGovernanceAdmin) {
      return;
    }
    const root = document.querySelector("#program-governance-admin-root");
    const httpClient = global.GovernanceDemoHttpClient
      ? new global.GovernanceDemoHttpClient()
      : (global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: false }) : null);
    if (!httpClient) {
      return;
    }
    new global.ProgramGovernanceAdmin({
      root: "#program-governance-admin-root",
      mode: root && root.dataset ? root.dataset.mode : "",
      httpClient: httpClient
    }).init();
  });
})(window);
