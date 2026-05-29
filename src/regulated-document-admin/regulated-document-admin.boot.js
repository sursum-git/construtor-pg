(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const admin = new global.RegulatedDocumentAdmin({
      root: "#regulated-document-admin-root"
    });
    admin.init();
    global.currentRegulatedDocumentAdmin = admin;
  });
})(window);
