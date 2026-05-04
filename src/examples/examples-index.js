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

    target.innerHTML = renderPanelBar(groups, catalog.getPropertyOptions ? catalog.getPropertyOptions() : []);
    initializePanelBar();
  });

  function renderPanelBar(groups, propertyOptions) {
    const categories = Object.keys(groups);
    const exampleItems = categories.map(function(category) {
      return renderPanelItem(
        category,
        groups[category].length + " exemplo" + (groups[category].length === 1 ? "" : "s"),
        "<section class=\"examples-index-section\">" +
          "<div class=\"examples-card-grid\">" +
            groups[category].map(renderCard).join("") +
          "</div>" +
        "</section>"
      );
    }).join("");

    const referenceItem = renderPanelItem(
      "Possibilidades de preenchimento",
      propertyOptions.length + " propriedades",
      renderPropertyOptions(propertyOptions)
    );

    return "<ul id=\"examples-index-panelbar\" class=\"examples-panelbar\">" + exampleItems + referenceItem + "</ul>";
  }

  function renderPanelItem(title, badge, content) {
    return "<li>" +
      "<span class=\"examples-panelbar-title\">" +
        "<span>" + escapeHtml(title) + "</span>" +
        "<span class=\"examples-panelbar-badge\">" + escapeHtml(badge) + "</span>" +
      "</span>" +
      "<div class=\"examples-panelbar-content\">" + content + "</div>" +
    "</li>";
  }

  function initializePanelBar() {
    if (!global.jQuery || !global.jQuery.fn || !global.jQuery.fn.kendoPanelBar) {
      return;
    }

    const panel = global.jQuery("#examples-index-panelbar");
    panel.kendoPanelBar({
      animation: false,
      expandMode: "multiple"
    });

    const widget = panel.data("kendoPanelBar");
    if (widget) {
      widget.expand(panel.children("li").first(), false);
    }
  }

  function renderCard(example) {
    return "<article class=\"example-index-card\">" +
      "<div>" +
        "<h3>" + escapeHtml(example.title) + "</h3>" +
        "<p>" + escapeHtml(example.summary) + "</p>" +
      "</div>" +
      "<a class=\"k-button k-button-md k-rounded-md k-button-solid k-button-solid-primary\" href=\"" + escapeHtml(example.page) + "\">Abrir exemplo</a>" +
    "</article>";
  }

  function renderPropertyOptions(options) {
    if (!options || !options.length) {
      return "";
    }

    return "<section class=\"examples-reference-section\" aria-label=\"Possibilidades de preenchimento\">" +
      "<div class=\"examples-reference-heading\">" +
        "<p>Referencia rapida das propriedades com valores fechados ou convencoes aceitas pelo motor.</p>" +
      "</div>" +
      "<div class=\"examples-reference-table-wrap\">" +
        "<table class=\"examples-reference-table\">" +
          "<thead>" +
            "<tr>" +
              "<th>Grupo</th>" +
              "<th>Propriedade</th>" +
              "<th>Valores aceitos</th>" +
              "<th>Padrao</th>" +
              "<th>Observacao</th>" +
            "</tr>" +
          "</thead>" +
          "<tbody>" +
            options.map(renderPropertyOption).join("") +
          "</tbody>" +
        "</table>" +
      "</div>" +
    "</section>";
  }

  function renderPropertyOption(option) {
    return "<tr>" +
      "<td>" + escapeHtml(option.category) + "</td>" +
      "<td><code>" + escapeHtml(option.path) + "</code></td>" +
      "<td>" + renderValues(option.values) + "</td>" +
      "<td>" + escapeHtml(option.defaultValue) + "</td>" +
      "<td>" + escapeHtml(option.note) + "</td>" +
    "</tr>";
  }

  function renderValues(values) {
    return String(values || "").split("|").map(function(value) {
      return "<span class=\"examples-reference-value\">" + escapeHtml(value.trim()) + "</span>";
    }).join("");
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
