(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const catalog = global.CrudExamplesCatalog;
    const target = document.getElementById("examples-index-list");
    if (!catalog || !target) {
      return;
    }

    const groups = catalog.list().reduce(function(acc, example) {
      if (!acc[example.category]) {
        acc[example.category] = [];
      }
      acc[example.category].push(example);
      return acc;
    }, {});

    target.innerHTML = Object.keys(groups).map(function(category) {
      return "<section class=\"examples-index-section\">" +
        "<h2>" + escapeHtml(category) + "</h2>" +
        "<div class=\"examples-card-grid\">" +
          groups[category].map(renderCard).join("") +
        "</div>" +
      "</section>";
    }).join("");
  });

  function renderCard(example) {
    return "<article class=\"example-index-card\">" +
      "<div>" +
        "<h3>" + escapeHtml(example.title) + "</h3>" +
        "<p>" + escapeHtml(example.summary) + "</p>" +
      "</div>" +
      "<a class=\"k-button k-button-md k-rounded-md k-button-solid k-button-solid-primary\" href=\"" + escapeHtml(example.page) + "\">Abrir exemplo</a>" +
    "</article>";
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
})(window);
