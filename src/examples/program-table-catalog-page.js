(function(global, $) {
  "use strict";

  function escapeHtml(value) {
    return $("<div>").text(value == null ? "" : String(value)).html();
  }

  function normalizeText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  }

  function filterPrograms(programs, term) {
    if (!term) {
      return programs.slice();
    }
    return programs.filter(function(item) {
      return normalizeText([
        item.programCode,
        item.title,
        item.screenId,
        item.pageType,
        item.programType,
        item.primaryTableName,
        (item.tableNames || []).join(" "),
        item.summary,
        item.explanation
      ].join(" ")).indexOf(term) !== -1;
    });
  }

  function filterTables(tables, term) {
    if (!term) {
      return tables.slice();
    }
    return tables.filter(function(item) {
      return normalizeText([
        item.tableName,
        item.entityCode,
        item.entityName,
        item.category,
        item.scope,
        item.explanation
      ].join(" ")).indexOf(term) !== -1;
    });
  }

  function filterRelations(relations, term) {
    if (!term) {
      return relations.slice();
    }
    return relations.filter(function(item) {
      return normalizeText([
        item.programCode,
        item.title,
        item.screenId,
        item.tableName,
        item.relationType,
        item.fieldCode,
        item.fieldLabel,
        item.notes
      ].join(" ")).indexOf(term) !== -1;
    });
  }

  function renderProgramDetails(item) {
    if (!item) {
      return "<p class=\"catalog-empty\">Selecione um programa.</p>";
    }
    return [
      "<div class=\"catalog-detail-card\">",
      "<h3>", escapeHtml(item.title), "</h3>",
      "<dl class=\"catalog-definition\">",
      "<div><dt>Programa</dt><dd>", escapeHtml(item.programCode), "</dd></div>",
      "<div><dt>ScreenId</dt><dd>", escapeHtml(item.screenId || "-"), "</dd></div>",
      "<div><dt>PageType</dt><dd>", escapeHtml(item.pageType || "-"), "</dd></div>",
      "<div><dt>Tabela principal</dt><dd>", escapeHtml(item.primaryTableName || "-"), "</dd></div>",
      "<div><dt>Tabelas relacionadas</dt><dd>", escapeHtml((item.tableNames || []).join(", ") || "-"), "</dd></div>",
      "<div><dt>Resumo</dt><dd>", escapeHtml(item.summary || "-"), "</dd></div>",
      "<div><dt>Explicacao</dt><dd>", escapeHtml(item.explanation || "-"), "</dd></div>",
      "</dl>",
      "</div>"
    ].join("");
  }

  function renderTableDetails(item) {
    if (!item) {
      return "<p class=\"catalog-empty\">Selecione uma tabela.</p>";
    }
    var programs = (item.relatedPrograms || []).map(function(link) {
      return "<li><strong>" + escapeHtml(link.programCode) + "</strong> - " + escapeHtml(link.title) + " (" + escapeHtml(link.relationType) + ")</li>";
    }).join("");
    return [
      "<div class=\"catalog-detail-card\">",
      "<h3>", escapeHtml(item.tableName), "</h3>",
      "<dl class=\"catalog-definition\">",
      "<div><dt>Categoria</dt><dd>", escapeHtml(item.category || "-"), "</dd></div>",
      "<div><dt>Escopo</dt><dd>", escapeHtml(item.scope || "-"), "</dd></div>",
      "<div><dt>Entidade</dt><dd>", escapeHtml(item.entityCode || "-"), "</dd></div>",
      "<div><dt>Nome da entidade</dt><dd>", escapeHtml(item.entityName || "-"), "</dd></div>",
      "<div><dt>Colunas</dt><dd>", escapeHtml(item.columnCount), "</dd></div>",
      "<div><dt>Programas</dt><dd>", escapeHtml(item.programCount), "</dd></div>",
      "<div><dt>Explicacao</dt><dd>", escapeHtml(item.explanation || "-"), "</dd></div>",
      "</dl>",
      "<div class=\"catalog-linked\">",
      "<h4>Programas relacionados</h4>",
      programs ? "<ul>" + programs + "</ul>" : "<p>Nenhum programa relacionado encontrado.</p>",
      "</div>",
      "</div>"
    ].join("");
  }

  function renderRelationDetails(item) {
    if (!item) {
      return "<p class=\"catalog-empty\">Selecione uma relacao.</p>";
    }
    return [
      "<div class=\"catalog-detail-card\">",
      "<h3>", escapeHtml(item.programCode), " -> ", escapeHtml(item.tableName), "</h3>",
      "<dl class=\"catalog-definition\">",
      "<div><dt>Programa</dt><dd>", escapeHtml(item.title || "-"), "</dd></div>",
      "<div><dt>ScreenId</dt><dd>", escapeHtml(item.screenId || "-"), "</dd></div>",
      "<div><dt>Tabela</dt><dd>", escapeHtml(item.tableName || "-"), "</dd></div>",
      "<div><dt>Tipo da relacao</dt><dd>", escapeHtml(item.relationType || "-"), "</dd></div>",
      "<div><dt>Campo</dt><dd>", escapeHtml(item.fieldCode || "-"), "</dd></div>",
      "<div><dt>Rotulo do campo</dt><dd>", escapeHtml(item.fieldLabel || "-"), "</dd></div>",
      "<div><dt>Observacao</dt><dd>", escapeHtml(item.notes || "-"), "</dd></div>",
      "</dl>",
      "</div>"
    ].join("");
  }

  function setSummary(data) {
    $("#catalog-generated-at").text(data.generatedAt || "-");
    $("#catalog-program-count").text(String(data.stats && data.stats.programCount || 0));
    $("#catalog-table-count").text(String(data.stats && data.stats.tableCount || 0));
    $("#catalog-relation-count").text(String(data.stats && data.stats.relationCount || 0));
  }

  $(function() {
    var data = global.ProgramTableCatalogData || { stats: {}, programs: [], tables: [], relations: [] };
    var state = {
      programs: data.programs || [],
      tables: data.tables || [],
      relations: data.relations || [],
      term: ""
    };

    setSummary(data);

    $("#catalog-search").kendoTextBox({
      placeholder: "Buscar por programa, screenId, tabela ou explicacao"
    });

    $("#catalog-tabs").kendoTabStrip({ animation: false });

    var programGrid = $("#catalog-program-grid").kendoGrid({
      height: 420,
      sortable: true,
      selectable: "row",
      dataSource: { data: state.programs, pageSize: 15 },
      pageable: true,
      columns: [
        { field: "programCode", title: "Programa", width: 180 },
        { field: "title", title: "Titulo", width: 240 },
        { field: "screenId", title: "ScreenId", width: 220 },
        { field: "pageType", title: "Tipo", width: 110 },
        { field: "primaryTableName", title: "Tabela principal", width: 180 }
      ],
      change: function() {
        var selected = this.dataItem(this.select());
        $("#catalog-program-details").html(renderProgramDetails(selected));
      },
      dataBound: function() {
        var row = this.tbody.find("tr:first");
        if (row.length) {
          this.select(row);
          $("#catalog-program-details").html(renderProgramDetails(this.dataItem(row)));
        } else {
          $("#catalog-program-details").html(renderProgramDetails(null));
        }
      }
    }).data("kendoGrid");

    var tableGrid = $("#catalog-table-grid").kendoGrid({
      height: 420,
      sortable: true,
      selectable: "row",
      dataSource: { data: state.tables, pageSize: 15 },
      pageable: true,
      columns: [
        { field: "tableName", title: "Tabela", width: 220 },
        { field: "category", title: "Categoria", width: 120 },
        { field: "scope", title: "Escopo", width: 110 },
        { field: "columnCount", title: "Colunas", width: 90 },
        { field: "programCount", title: "Programas", width: 100 }
      ],
      change: function() {
        var selected = this.dataItem(this.select());
        $("#catalog-table-details").html(renderTableDetails(selected));
      },
      dataBound: function() {
        var row = this.tbody.find("tr:first");
        if (row.length) {
          this.select(row);
          $("#catalog-table-details").html(renderTableDetails(this.dataItem(row)));
        } else {
          $("#catalog-table-details").html(renderTableDetails(null));
        }
      }
    }).data("kendoGrid");

    var relationGrid = $("#catalog-relation-grid").kendoGrid({
      height: 420,
      sortable: true,
      selectable: "row",
      dataSource: { data: state.relations, pageSize: 15 },
      pageable: true,
      columns: [
        { field: "programCode", title: "Programa", width: 180 },
        { field: "tableName", title: "Tabela", width: 220 },
        { field: "relationType", title: "Relacao", width: 110 },
        { field: "fieldCode", title: "Campo", width: 140 },
        { field: "fieldLabel", title: "Rotulo", width: 180 }
      ],
      change: function() {
        var selected = this.dataItem(this.select());
        $("#catalog-relation-details").html(renderRelationDetails(selected));
      },
      dataBound: function() {
        var row = this.tbody.find("tr:first");
        if (row.length) {
          this.select(row);
          $("#catalog-relation-details").html(renderRelationDetails(this.dataItem(row)));
        } else {
          $("#catalog-relation-details").html(renderRelationDetails(null));
        }
      }
    }).data("kendoGrid");

    function applySearch() {
      var term = normalizeText($("#catalog-search").data("kendoTextBox").value());
      state.term = term;
      programGrid.dataSource.data(filterPrograms(data.programs || [], term));
      tableGrid.dataSource.data(filterTables(data.tables || [], term));
      relationGrid.dataSource.data(filterRelations(data.relations || [], term));
    }

    $("#catalog-search").on("input", applySearch);
    applySearch();
  });
})(window, jQuery);
