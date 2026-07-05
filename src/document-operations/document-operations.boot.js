(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const page = new global.DocumentOperationsPage({
      root: "#document-operations-root"
    });
    page.init();
  });
})(window);
