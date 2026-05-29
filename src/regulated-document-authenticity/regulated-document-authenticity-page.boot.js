(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const page = new global.RegulatedDocumentAuthenticityPage({
      root: "#regulated-document-authenticity-root"
    });
    page.init();
    global.currentRegulatedDocumentAuthenticityPage = page;
  });
})(window);
