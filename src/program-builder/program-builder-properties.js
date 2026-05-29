(function(global) {
  "use strict";

  const ProgramBuilder = global.ProgramBuilder;
  if (!ProgramBuilder) {
    return;
  }

  ProgramBuilder.prototype.renderEntityProperties = function() {
    const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
    const fieldCount = this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).length;
    const ruleCount = this.rulesTableBody.find(".program-builder-rule-row").length;
    const uniqueCount = this.uniqueKeysTableBody.find(".program-builder-unique-key-row").length;
    this.appendPropertyText(panel, "Codigo", () => this.entityCodeInput.value(), (value) => this.entityCodeInput.value(value), "text", this.entityFieldTechnicalProperties("entityCode"));
    this.appendPropertyText(panel, "Nome", () => this.entityNameInput.value(), (value) => this.entityNameInput.value(value), "text", this.entityFieldTechnicalProperties("entityName"));
    this.appendPropertySelect(panel, "Tipo", [
      { value: "persistence", text: "Persistence" },
      { value: "query", text: "Query" },
      { value: "io", text: "IO" },
      { value: "api", text: "API" }
    ], () => this.entityTypeSelect.value(), (value) => { this.entityTypeSelect.value(value); this.syncEntityTypeState(); }, this.entityFieldTechnicalProperties("entityType"));
    this.appendPropertyText(panel, "Tabela", () => this.entityTableNameInput.value(), (value) => this.entityTableNameInput.value(value), "text", this.entityFieldTechnicalProperties("tableName"));
    this.appendPropertyCheckbox(panel, "Criar tabela", () => this.entityCreateTableInput.is(":checked"), (checked) => this.entityCreateTableInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Entidade", "Criar tabela", "Controla se o construtor deve gerar ou sincronizar a tabela fisica.", [{ section: "Banco", label: "Aplicavel", value: "Persistence" }]));
    this.appendPropertyCheckbox(panel, "Versionada", () => this.entityVersioningEnabledInput.is(":checked"), (checked) => this.entityVersioningEnabledInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Entidade", "Versionada", "Liga o fluxo de snapshot historico da entidade e dos campos marcados para historico."));
    this.appendPropertyReadOnly(panel, "Campos", String(fieldCount), this.buildTechnicalProperties("Entidade", "Campos", "Quantidade atual de campos modelados na entidade."));
    this.appendPropertyReadOnly(panel, "Regras", String(ruleCount), this.buildTechnicalProperties("Entidade", "Regras", "Quantidade atual de regras de negocio configuradas."));
    this.appendPropertyReadOnly(panel, "Chaves unicas", String(uniqueCount), this.buildTechnicalProperties("Entidade", "Chaves unicas", "Quantidade atual de chaves compostas configuradas."));
  };

  ProgramBuilder.prototype.renderProgramProperties = function() {
    const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
    this.appendPropertyText(panel, "Codigo", () => this.programCodeInput.value(), (value) => this.programCodeInput.value(value), "text", this.programFieldTechnicalProperties("programCode"));
    this.appendPropertyText(panel, "Titulo", () => this.programTitleInput.value(), (value) => this.programTitleInput.value(value), "text", this.programFieldTechnicalProperties("programTitle"));
    this.appendPropertyText(panel, "Screen ID", () => this.screenIdInput.value(), (value) => this.screenIdInput.value(value), "text", this.programFieldTechnicalProperties("screenId"));
    this.appendPropertyText(panel, "Versao", () => this.versionInput.value(), (value) => this.versionInput.value(value), "text", this.programFieldTechnicalProperties("version"));
    this.appendPropertySelect(panel, "Tipo", [
      { value: "crud", text: "CRUD" },
      { value: "analytics", text: "Analytics / BI" },
      { value: "report", text: "Relatorios" },
      { value: "special_document", text: "Documento especial" },
      { value: "regulated_document", text: "Documento regulado" },
      { value: "custom", text: "Custom" }
    ], () => this.pageTypeSelect.value(), (value) => { this.pageTypeSelect.value(value); this.syncProgramTypeState(); }, this.programFieldTechnicalProperties("pageType"));
    this.appendPropertySelect(panel, "Modulo", this.state.modules.map(function(item) {
      return { value: item.code, text: item.code + " - " + item.name };
    }), () => this.moduleInput.value(), (value) => this.moduleInput.value(value), this.programFieldTechnicalProperties("programModule"));
    if (String(this.pageTypeSelect.value() || "crud") === "crud") {
      this.appendPropertySelect(panel, "Entidade base", this.state.entities.map(function(item) {
        return { value: item.code, text: item.code + " - " + item.name };
      }), () => this.builderEntitySelect.value(), (value) => { this.builderEntitySelect.value(value); this.handleProgramEntityChange(false); }, this.programFieldTechnicalProperties("baseEntity"));
      this.appendPropertyCheckbox(panel, "Permite incluir", () => this.allowCreateInput.is(":checked"), (checked) => this.allowCreateInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Programa", "Permite incluir", "Habilita a acao create no CRUD quando o runtime possui endpoint compativel."));
      this.appendPropertyCheckbox(panel, "Permite alterar", () => this.allowUpdateInput.is(":checked"), (checked) => this.allowUpdateInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Programa", "Permite alterar", "Habilita a acao update no CRUD quando o runtime possui endpoint compativel."));
      this.appendPropertyCheckbox(panel, "Permite excluir", () => this.allowDeleteInput.is(":checked"), (checked) => this.allowDeleteInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Programa", "Permite excluir", "Habilita a acao delete no CRUD quando o runtime possui endpoint compativel."));
      return;
    }
    this.appendPropertySelect(panel, "Modo custom", [
      { value: "iframe", text: "Iframe interno" },
      { value: "htmlUrl", text: "Fragmento HTML por URL" }
    ], () => this.customModeSelect.value(), (value) => { this.customModeSelect.value(value); this.schedulePreview(); }, this.programFieldTechnicalProperties("customMode"));
    this.appendPropertyText(panel, "Entry URL", () => this.customEntryUrlInput.value(), (value) => this.customEntryUrlInput.value(value), "text", this.programFieldTechnicalProperties("customEntryUrl"));
    this.appendPropertyText(panel, "Titulo do frame", () => this.customFrameTitleInput.value(), (value) => this.customFrameTitleInput.value(value), "text", this.programFieldTechnicalProperties("customFrameTitle"));
  };

  ProgramBuilder.prototype.renderFieldProperties = function(index) {
    const pair = this.getFieldRowPair(index);
    if (!pair.row) {
      $("<p class=\"program-builder-empty\"></p>").text("Selecione um campo.").appendTo(this.propertiesElement);
      return;
    }
    const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
    this.appendPropertyText(panel, "Codigo", () => pair.row.find(".program-builder-field-code").val(), (value) => pair.row.find(".program-builder-field-code").val(value), "text", this.buildTechnicalProperties("Campo", "Codigo", "Identificador tecnico do campo dentro da entidade.", [{ section: "Modelo", label: "Uso", value: "Referencia em regras, FKs, grid e runtime.", critical: true }]));
    this.appendPropertyText(panel, "Label", () => pair.row.find(".program-builder-field-label").val(), (value) => pair.row.find(".program-builder-field-label").val(value), "text", this.buildTechnicalProperties("Campo", "Label", "Rotulo funcional exibido no grid, filtro e formulario."));
    this.appendPropertySelect(panel, "Tipo", [
      "string", "text", "integer", "decimal", "boolean", "date", "datetime", "enum", "dropdown", "email", "json", "custom_code"
    ].map(function(item) { return { value: item, text: item }; }), () => pair.row.find(".program-builder-field-type").val(), (value) => { pair.row.find(".program-builder-field-type").val(value); this.syncFieldRowState(pair.row, pair.details); }, this.buildTechnicalProperties("Campo", "Tipo", "Tipo declarativo usado pelo motor para grid, filtro, formulario e serializacao."));
    this.appendPropertyText(panel, "Coluna", () => pair.row.find(".program-builder-field-column").val(), (value) => pair.row.find(".program-builder-field-column").val(value), "text", this.buildTechnicalProperties("Campo", "Coluna", "Nome da coluna fisica quando a entidade usa persistencia local."));
    this.appendPropertyText(panel, "Tamanho", () => pair.row.find(".program-builder-field-length").val(), (value) => pair.row.find(".program-builder-field-length").val(value), "number", this.buildTechnicalProperties("Campo", "Tamanho", "Limite de tamanho usado em validacao e geracao de banco quando aplicavel."));
    this.appendPropertyCheckbox(panel, "Obrigatorio", () => pair.row.find(".program-builder-field-required").is(":checked"), (checked) => pair.row.find(".program-builder-field-required").prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Campo", "Obrigatorio", "Marca o campo como necessario no runtime e em validacoes.", [{ section: "Regra", label: "Impacto", value: "Gera obrigatoriedade no CRUD.", critical: true }]));
    this.appendPropertyCheckbox(panel, "PK", () => pair.row.find(".program-builder-field-pk").is(":checked"), (checked) => { pair.row.find(".program-builder-field-pk").prop("checked", checked).trigger("change"); this.syncFieldRowState(pair.row, pair.details); }, this.buildTechnicalProperties("Campo", "PK", "Define a chave primaria logica da entidade.", [{ section: "Banco", label: "Impacto", value: "Usado para grid, get, update e delete.", critical: true }]));
    this.appendPropertyCheckbox(panel, "Unico", () => pair.details.find(".program-builder-field-unique").is(":checked"), (checked) => pair.details.find(".program-builder-field-unique").prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Campo", "Unico", "Impede repeticao de valor por validacao/estrutura quando aplicavel."));
    this.appendPropertyCheckbox(panel, "Nao editavel", () => pair.details.find(".program-builder-field-readonly").is(":checked"), (checked) => pair.details.find(".program-builder-field-readonly").prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Campo", "Nao editavel", "Desliga escrita do campo no runtime gerado.", [{ section: "Runtime", label: "Impacto", value: "Campo somente leitura.", critical: true }]));
    this.appendPropertyText(panel, "FK tabela", () => pair.details.find(".program-builder-field-fk-table").val(), (value) => pair.details.find(".program-builder-field-fk-table").val(value), "text", this.buildTechnicalProperties("Campo", "FK tabela", "Tabela ou entidade de referencia da chave estrangeira."));
    this.appendPropertyText(panel, "FK coluna", () => pair.details.find(".program-builder-field-fk-column").val(), (value) => pair.details.find(".program-builder-field-fk-column").val(value), "text", this.buildTechnicalProperties("Campo", "FK coluna", "Campo de referencia usado pela chave estrangeira."));
  };

  ProgramBuilder.prototype.renderRuleProperties = function(index) {
    const pair = this.getRuleRowPair(index);
    if (!pair.row) {
      $("<p class=\"program-builder-empty\"></p>").text("Selecione uma regra.").appendTo(this.propertiesElement);
      return;
    }
    const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
    this.appendPropertyText(panel, "Rotulo", () => pair.row.find(".program-builder-rule-label").val(), (value) => pair.row.find(".program-builder-rule-label").val(value), "text", this.buildTechnicalProperties("Regra", "Rotulo", "Nome curto exibido na lista de regras do construtor."));
    this.appendPropertyText(panel, "Ordem", () => pair.row.find(".program-builder-rule-order").val(), (value) => pair.row.find(".program-builder-rule-order").val(value), "number", this.buildTechnicalProperties("Regra", "Ordem", "Sequencia de execucao da regra dentro da mesma fase."));
    this.appendPropertySelect(panel, "Fase", [
      { value: "beforeValidate", text: "Antes da validacao" },
      { value: "beforePersist", text: "Antes de gravar" },
      { value: "afterPersist", text: "Apos gravar" },
      { value: "afterCommit", text: "Apos concluir" }
    ], () => pair.row.find(".program-builder-rule-phase").val(), (value) => pair.row.find(".program-builder-rule-phase").val(value), this.buildTechnicalProperties("Regra", "Fase", "Momento do ciclo runtime em que a regra sera executada."));
    this.appendPropertySelect(panel, "Tipo", [
      { value: "requiredWhen", text: "Declarativa" },
      { value: "class_method", text: "Classe/metodo" }
    ], () => pair.row.find(".program-builder-rule-type").val(), (value) => { pair.row.find(".program-builder-rule-type").val(value); this.syncRuleRowState(pair.row, pair.details); }, this.buildTechnicalProperties("Regra", "Tipo", "Escolhe entre regra declarativa simples ou regra por classe/metodo no backend."));
    this.appendPropertyCheckbox(panel, "Ativa", () => pair.row.find(".program-builder-rule-enabled").is(":checked"), (checked) => pair.row.find(".program-builder-rule-enabled").prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Regra", "Ativa", "Controla se a regra participa do pipeline runtime."));
    this.appendPropertyCheckbox(panel, "Continua apos erro", () => pair.row.find(".program-builder-rule-continue").is(":checked"), (checked) => pair.row.find(".program-builder-rule-continue").prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Regra", "Continua apos erro", "Permite seguir para a proxima regra mesmo quando esta falhar."));
    this.appendPropertyText(panel, "Classe", () => pair.details.find(".program-builder-rule-class-name").val(), (value) => pair.details.find(".program-builder-rule-class-name").val(value), "text", this.buildTechnicalProperties("Regra", "Classe", "Classe backend usada quando o tipo da regra for class_method."));
    this.appendPropertyText(panel, "Metodo", () => pair.details.find(".program-builder-rule-method-name").val(), (value) => pair.details.find(".program-builder-rule-method-name").val(value), "text", this.buildTechnicalProperties("Regra", "Metodo", "Metodo backend chamado quando o tipo da regra for class_method."));
    this.appendPropertyText(panel, "Campo", () => pair.details.find(".program-builder-rule-field").val(), (value) => pair.details.find(".program-builder-rule-field").val(value), "text", this.buildTechnicalProperties("Regra", "Campo", "Campo principal avaliado pela regra ou usado como alvo de mensagem."));
  };

  ProgramBuilder.prototype.renderUniqueKeyProperties = function(index) {
    const row = this.getUniqueKeyRow(index);
    if (!row || !row.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Selecione uma chave unica.").appendTo(this.propertiesElement);
      return;
    }
    const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
    this.appendPropertyText(panel, "Nome", () => row.find(".program-builder-unique-key-name").val(), (value) => row.find(".program-builder-unique-key-name").val(value), "text", this.buildTechnicalProperties("Chave unica", "Nome", "Identificador tecnico da restricao composta."));
    this.appendPropertyText(panel, "Campos", () => row.find(".program-builder-unique-key-fields").val(), (value) => row.find(".program-builder-unique-key-fields").val(value), "text", this.buildTechnicalProperties("Chave unica", "Campos", "Lista ordenada de campos que participam da chave composta.", [{ section: "Banco", label: "Impacto", value: "Restringe duplicidade no conjunto.", critical: true }]));
  };

  ProgramBuilder.prototype.appendPropertyField = function(parent, label, technicalProperties) {
    const field = $("<label class=\"program-builder-field\"></label>").appendTo(parent);
    this.appendFieldLabel(field, label, technicalProperties);
    return field;
  };

  ProgramBuilder.prototype.appendPropertyText = function(parent, label, getter, setter, type, technicalProperties) {
    const field = this.appendPropertyField(parent, label, technicalProperties);
    const input = $("<input>").attr("type", type || "text").addClass("program-builder-mini-input").val(getter() || "").appendTo(field);
    input.on("input change", function() {
      setter(input.val());
      this.handleEditorMutation();
    }.bind(this));
  };

  ProgramBuilder.prototype.appendPropertyCheckbox = function(parent, label, getter, setter, technicalProperties) {
    const field = this.appendPropertyField(parent, label, technicalProperties);
    const wrap = $("<label class=\"program-builder-property-check\"></label>").appendTo(field);
    const input = $("<input type=\"checkbox\">").prop("checked", getter() === true).appendTo(wrap);
    $("<span></span>").text("Ativar").appendTo(wrap);
    input.on("change", function() {
      setter(input.is(":checked"));
      this.handleEditorMutation();
    }.bind(this));
  };

  ProgramBuilder.prototype.appendPropertySelect = function(parent, label, items, getter, setter, technicalProperties) {
    const field = this.appendPropertyField(parent, label, technicalProperties);
    const select = $("<select class=\"program-builder-mini-select\"></select>").appendTo(field);
    (items || []).forEach(function(item) {
      $("<option></option>").attr("value", item.value).text(item.text).appendTo(select);
    });
    select.val(getter() || "");
    select.on("change", function() {
      setter(select.val());
      this.handleEditorMutation();
    }.bind(this));
  };

  ProgramBuilder.prototype.appendPropertyReadOnly = function(parent, label, value, technicalProperties) {
    const field = this.appendPropertyField(parent, label, technicalProperties);
    $("<div class=\"program-builder-property-readonly\"></div>").text(value || "").appendTo(field);
  };
})(window);
