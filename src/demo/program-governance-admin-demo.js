(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const root = document.getElementById("program-governance-admin-root");
    if (!root || !global.ProgramGovernanceAdmin || !global.GovernanceDemoHttpClient) {
      return;
    }
    new global.ProgramGovernanceAdmin({
      root: "#program-governance-admin-root",
      httpClient: new global.GovernanceDemoHttpClient()
    }).init();
  });
})(window);
