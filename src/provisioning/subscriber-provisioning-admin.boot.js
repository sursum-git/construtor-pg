(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.SubscriberProvisioningAdmin) {
      return;
    }
    const httpClient = global.SubscriberProvisioningDemoHttpClient
      ? new global.SubscriberProvisioningDemoHttpClient()
      : (global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: false }) : null);
    if (!httpClient) {
      return;
    }
    new global.SubscriberProvisioningAdmin({
      root: "#subscriber-provisioning-admin-root",
      httpClient: httpClient
    }).init();
  });
})(window);
