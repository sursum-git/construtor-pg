(function(global) {
  "use strict";

  class CrudLiteFilterRenderer {
    constructor(options) {
      this.definition = options.definition;
      this.httpClient = options.httpClient;
      this.onApply = options.onApply || function() {};
      this.onClear = options.onClear || function() {};
      this.values = {};
      this.operatorValues = {};
      this.rangeValues = {};
      this.fields = global.CrudUtils.ensureArray(this.definition.filter && this.definition.filter.fields);
      this.applyDefaults();
    }

    render() {}

    destroy() {
      if (this.modal) {
        this.modal.remove();
      }
    }

    open() {
      this.modal = document.createElement("div");
      this.modal.className = "crud-lite-modal-overlay";
      this.modal.innerHTML = [
        "<section class=\"crud-lite-dialog crud-lite-filter-dialog\" role=\"dialog\" aria-modal=\"true\">",
        "<header class=\"crud-lite-dialog-header\"><h2>" + global.CrudUtils.escapeHtml(this.definition.filter && this.definition.filter.title || "Filtros") + "</h2><button type=\"button\" class=\"crud-lite-dialog-close\" data-lite-close>X</button></header>",
        "<div class=\"crud-lite-dialog-body\"><form class=\"crud-lite-filter-form\"></form></div>",
        "<footer class=\"crud-lite-dialog-footer\"><button type=\"button\" class=\"crud-lite-button crud-lite-button-primary\" data-lite-apply>Filtrar</button><button type=\"button\" class=\"crud-lite-button\" data-lite-clear>Limpar</button><button type=\"button\" class=\"crud-lite-button\" data-lite-close>Cancelar</button></footer>",
        "</section>"
      ].join("");
      document.body.appendChild(this.modal);
      this.form = this.modal.querySelector(".crud-lite-filter-form");
      this.renderFields();
      this.bindModalEvents();
      const first = this.form.querySelector("input, select, textarea, button");
      if (first) {
        first.focus();
      }
    }

    bindModalEvents() {
      this.modal.addEventListener("click", (event) => {
        if (event.target.closest("[data-lite-close]")) {
          this.close();
          return;
        }
        if (event.target.closest("[data-lite-apply]")) {
          this.collectValues();
          this.onApply(this.getValues());
          this.close();
          return;
        }
        if (event.target.closest("[data-lite-clear]")) {
          this.clearValues();
          this.onClear();
          this.close();
        }
      });
    }

    close() {
      if (this.modal) {
        this.modal.remove();
        this.modal = null;
      }
    }

    renderFields() {
      this.form.innerHTML = this.fields.map((field) => this.renderField(field)).join("");
    }

    renderField(field) {
      const id = this.getFieldId(field);
      const label = field.label || field.field || field.id;
      const operator = this.operatorValues[id] || field.operator || this.getDefaultOperator(field);
      const operatorHtml = this.renderOperator(field, operator);
      return [
        "<label class=\"crud-lite-field\" data-lite-filter-field=\"" + global.CrudUtils.escapeHtml(id) + "\">",
        "<span>" + global.CrudUtils.escapeHtml(label) + "</span>",
        operatorHtml,
        this.renderEditor(field),
        "</label>"
      ].join("");
    }

    renderOperator(field, value) {
      const operators = global.CrudUtils.ensureArray(field.operators);
      if (!operators.length || operators.length === 1) {
        return "";
      }
      return "<select class=\"crud-lite-filter-operator\" data-lite-filter-operator>" + operators.map(function(operator) {
        return "<option value=\"" + global.CrudUtils.escapeHtml(operator) + "\"" + (operator === value ? " selected" : "") + ">" + global.CrudUtils.escapeHtml(CrudLiteFilterRenderer.operatorLabel(operator)) + "</option>";
      }).join("") + "</select>";
    }

    renderEditor(field) {
      const id = this.getFieldId(field);
      const value = this.values[id] == null ? "" : this.values[id];
      const type = String(field.editor || field.type || "text");
      if (type === "dateRange" || field.operator === "between") {
        const range = this.rangeValues[id] || {};
        return [
          "<div class=\"crud-lite-range\">",
          "<input type=\"" + (this.isDateField(field) ? "date" : "text") + "\" data-lite-filter-start value=\"" + global.CrudUtils.escapeHtml(range.start || "") + "\">",
          "<input type=\"" + (this.isDateField(field) ? "date" : "text") + "\" data-lite-filter-end value=\"" + global.CrudUtils.escapeHtml(range.end || "") + "\">",
          "</div>"
        ].join("");
      }
      if (this.isOptionField(field)) {
        const options = this.resolveOptions(field);
        return "<select data-lite-filter-value><option value=\"\">Todos</option>" + options.map(function(option) {
          const optionValue = option.value == null ? "" : String(option.value);
          return "<option value=\"" + global.CrudUtils.escapeHtml(optionValue) + "\"" + (String(value) === optionValue ? " selected" : "") + ">" + global.CrudUtils.escapeHtml(option.text || option.label || optionValue) + "</option>";
        }).join("") + "</select>";
      }
      if (this.isNumericField(field)) {
        return "<input type=\"number\" data-lite-filter-value value=\"" + global.CrudUtils.escapeHtml(value) + "\">";
      }
      if (this.isDateField(field)) {
        return "<input type=\"date\" data-lite-filter-value value=\"" + global.CrudUtils.escapeHtml(value) + "\">";
      }
      return "<input type=\"text\" data-lite-filter-value value=\"" + global.CrudUtils.escapeHtml(value) + "\" placeholder=\"" + global.CrudUtils.escapeHtml(field.placeholder || "") + "\">";
    }

    collectValues() {
      this.values = {};
      this.operatorValues = {};
      this.rangeValues = {};
      this.form.querySelectorAll("[data-lite-filter-field]").forEach((wrapper) => {
        const id = wrapper.getAttribute("data-lite-filter-field");
        const operator = wrapper.querySelector("[data-lite-filter-operator]");
        if (operator) {
          this.operatorValues[id] = operator.value;
        }
        const start = wrapper.querySelector("[data-lite-filter-start]");
        const end = wrapper.querySelector("[data-lite-filter-end]");
        if (start || end) {
          this.rangeValues[id] = {
            start: start ? start.value : "",
            end: end ? end.value : ""
          };
          return;
        }
        const input = wrapper.querySelector("[data-lite-filter-value]");
        this.values[id] = input ? input.value : "";
      });
    }

    getValues() {
      return this.fields.map((field) => {
        const id = this.getFieldId(field);
        const operator = this.operatorValues[id] || field.operator || this.getDefaultOperator(field);
        const range = this.rangeValues[id];
        const rawValue = range ? range : this.values[id];
        if (!this.hasFilterValue(rawValue, operator)) {
          return null;
        }
        const dataType = field.type === "search" ? "string" : field.type;
        return {
          id,
          field: field.field,
          fields: field.fields,
          type: field.type,
          dataType,
          operator,
          value: rawValue,
          displayValue: this.buildDisplayValue(field, rawValue)
        };
      }).filter(Boolean);
    }

    setValues(filters) {
      global.CrudUtils.ensureArray(filters).forEach((filter) => {
        const id = filter.id || filter.field;
        this.operatorValues[id] = filter.operator;
        if (filter.value && typeof filter.value === "object" && !Array.isArray(filter.value)) {
          this.rangeValues[id] = {
            start: filter.value.start || "",
            end: filter.value.end || ""
          };
        } else {
          this.values[id] = filter.value == null ? "" : filter.value;
        }
      });
    }

    clearValues() {
      this.values = {};
      this.operatorValues = {};
      this.rangeValues = {};
      this.applyDefaults();
    }

    clear() {
      this.values = {};
      this.operatorValues = {};
      this.rangeValues = {};
      if (this.form) {
        this.renderFields();
      }
    }

    applyDefaults() {
      this.fields.forEach((field) => {
        const id = this.getFieldId(field);
        if (field.defaultValue != null && this.values[id] == null) {
          this.values[id] = field.defaultValue;
        }
      });
    }

    hasFilterValue(value, operator) {
      if (["isEmpty", "isNotEmpty", "isNull", "isNotNull"].indexOf(operator) !== -1) {
        return true;
      }
      if (value && typeof value === "object" && !Array.isArray(value)) {
        return Boolean(value.start || value.end);
      }
      return value != null && String(value).trim() !== "";
    }

    buildDisplayValue(field, value) {
      if (value && typeof value === "object" && !Array.isArray(value)) {
        return [value.start, value.end].filter(Boolean).join(" ate ");
      }
      const option = this.resolveOptions(field).find(function(item) {
        return String(item.value) === String(value);
      });
      return option ? option.text || option.label || value : value;
    }

    resolveOptions(field) {
      if (field.options) {
        return global.CrudUtils.ensureArray(field.options);
      }
      const dataField = field.field && this.definition.dataModel.fields[field.field];
      return global.CrudUtils.ensureArray(dataField && dataField.options);
    }

    isOptionField(field) {
      const type = String(field.editor || field.type || "");
      return ["enum", "option", "dropdown", "dropdownList", "boolean"].indexOf(type) !== -1 || this.resolveOptions(field).length > 0;
    }

    isNumericField(field) {
      return ["integer", "number", "decimal"].indexOf(String(field.type || "")) !== -1;
    }

    isDateField(field) {
      return ["date", "datetime"].indexOf(String(field.type || "")) !== -1;
    }

    getDefaultOperator(field) {
      if (field.type === "search") {
        return "contains";
      }
      if (this.isOptionField(field)) {
        return "eq";
      }
      return field.operator || "contains";
    }

    getFieldId(field) {
      return String(field.id || field.field || "");
    }

    static operatorLabel(operator) {
      const labels = {
        contains: "Contem",
        eq: "Igual",
        startsWith: "Comeca com",
        notContains: "Nao contem",
        isEmpty: "Vazio",
        isNull: "Nulo",
        isNotEmpty: "Preenchido",
        isNotNull: "Nao nulo",
        between: "Entre",
        in: "Em",
        notIn: "Nao em",
        gte: "Maior ou igual",
        lte: "Menor ou igual",
        gt: "Maior",
        lt: "Menor"
      };
      return labels[operator] || operator;
    }
  }

  global.CrudLiteFilterRenderer = CrudLiteFilterRenderer;
})(window);
