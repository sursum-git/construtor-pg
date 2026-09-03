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
      { value: "master_detail", text: "Mestre-detalhe" },
      { value: "analytics", text: "Analytics / BI" },
      { value: "report", text: "Relatorios" },
      { value: "special_document", text: "Documento especial" },
      { value: "regulated_document", text: "Documento regulado" },
      { value: "custom", text: "Custom" }
    ], () => this.pageTypeSelect.value(), (value) => { this.pageTypeSelect.value(value); this.syncProgramTypeState(); this.renderPropertyInspector(); }, this.programFieldTechnicalProperties("pageType"));
    this.appendPropertySelect(panel, "Modulo", this.state.modules.map(function(item) {
      return { value: item.code, text: item.code + " - " + item.name };
    }), () => this.moduleInput.value(), (value) => this.moduleInput.value(value), this.programFieldTechnicalProperties("programModule"));
    const pageType = String(this.pageTypeSelect.value() || "crud");
    if (pageType === "crud" || pageType === "master_detail") {
      this.appendPropertySelect(panel, "Entidade base", this.state.entities.filter(function(item) {
        return pageType !== "master_detail" || item.entityType === "persistence";
      }).map(function(item) {
        return { value: item.code, text: item.code + " - " + item.name };
      }), () => this.builderEntitySelect.value(), (value) => { this.builderEntitySelect.value(value); this.handleProgramEntityChange(false); }, this.programFieldTechnicalProperties("baseEntity"));
      if (pageType === "master_detail") {
        this.renderMasterDetailProperties(panel);
        return;
      }
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

  ProgramBuilder.prototype.renderMasterDetailProperties = function(panel) {
    const config = this.masterDetailConfigValue();
    const masterCode = String(config.masterEntityCode || "");
    const context = $("<section class=\"program-builder-master-detail-context\"></section>").appendTo(panel);
    $("<h3></h3>").text("Configuracao mestre-detalhe").appendTo(context);
    const summary = $("<div class=\"program-builder-master-detail-summary\"></div>").appendTo(context);
    [
      { label: "Mestre", value: masterCode || "Selecione a entidade mestre" },
      { label: "Modo", value: config.createFlow.mode === "draftWithChildren" ? "Rascunho com filhos" : "Salvar mestre primeiro" },
      { label: "Filhos", value: String(config.details.length) },
      { label: "Endpoint ID", value: config.createFlow.endpointId || "Nao informado" }
    ].forEach(function(item) {
      const itemElement = $("<div class=\"program-builder-master-detail-summary-item\"></div>").appendTo(summary);
      $("<span></span>").text(item.label).appendTo(itemElement);
      $("<strong></strong>").text(item.value).appendTo(itemElement);
    });

    const editor = $("<div class=\"program-builder-master-detail-editor\"></div>").appendTo(context);
    const thisProgram = this;
    let parentSelect;
    const detailField = this.appendPropertyField(editor, "Entidade filha", this.buildTechnicalProperties("Programa", "Entidade filha", "Entidade persistence que sera exibida como detalhe do mestre."));
    const detailOptions = this.state.entities.filter(function(entity) {
      return entity && entity.entityType === "persistence" && entity.code !== masterCode;
    }).map(function(entity) {
      return { value: entity.code, text: entity.code + " - " + entity.name };
    });
    const detailInput = $("<input>").appendTo(detailField).kendoComboBox({
      dataSource: detailOptions,
      dataTextField: "text",
      dataValueField: "value",
      optionLabel: "Selecione a entidade filha",
      change: function() {
        const code = String(this.value() || "");
        parentSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
        parentSelect.value("");
        if (!code) {
          thisProgram.schedulePreview();
          return;
        }
        thisProgram.ensureEntityDetail(code).then(function() {
          const fields = thisProgram.masterDetailEligibleParentFields(code, masterCode).map(function(field) {
            return { value: field.code, text: field.code + " - " + (field.label || field.code) };
          });
          parentSelect.setDataSource(new kendo.data.DataSource({ data: fields }));
          thisProgram.schedulePreview();
        });
      }
    }).data("kendoComboBox");
    const parentField = this.appendPropertyField(editor, "Campo de vinculo", this.buildTechnicalProperties("Programa", "Campo de vinculo", "Campo existente na entidade filha que referencia o mestre."));
    parentSelect = $("<input>").appendTo(parentField).kendoDropDownList({
      dataTextField: "text",
      dataValueField: "value",
      optionLabel: "Selecione o campo de vinculo",
      dataSource: []
    }).data("kendoDropDownList");
    const addButton = $("<button type=\"button\" class=\"k-button k-button-md k-rounded-md k-button-solid k-button-solid-primary\"></button>").text("Adicionar filho").appendTo(editor);
    addButton.on("click", function() {
      if (thisProgram.addMasterDetail(detailInput.value(), parentSelect.value())) {
        thisProgram.renderPropertyInspector();
      }
    });

    const flowField = this.appendPropertyField(editor, "Fluxo de criacao", this.buildTechnicalProperties("Programa", "Fluxo de criacao", "Define se os filhos exigem o mestre salvo ou podem ser enviados no mesmo comando declarativo."));
    const endpointField = this.appendPropertyField(editor, "Endpoint ID", this.buildTechnicalProperties("Programa", "Endpoint ID", "Identificador seguro do endpoint transacional para inclusao conjunta, sem URL livre."));
    const endpointSelect = $("<input>").appendTo(endpointField).kendoDropDownList({
      dataSource: [{ value: "", text: "Nao se aplica" }, { value: "createGraph", text: "createGraph" }],
      dataTextField: "text",
      dataValueField: "value",
      value: config.createFlow.endpointId,
      change: function() {
        thisProgram.setMasterDetailCreateFlow(flowSelect.value(), this.value());
        thisProgram.renderPropertyInspector();
      }
    }).data("kendoDropDownList");
    const flowSelect = $("<input>").appendTo(flowField).kendoDropDownList({
      dataSource: [{ value: "parentFirst", text: "Salvar mestre primeiro" }, { value: "draftWithChildren", text: "Rascunho com filhos" }],
      dataTextField: "text",
      dataValueField: "value",
      value: config.createFlow.mode,
      change: function() {
        const joint = this.value() === "draftWithChildren";
        const endpointId = joint ? (endpointSelect.value() || "createGraph") : "";
        endpointSelect.enable(joint);
        endpointSelect.value(endpointId);
        thisProgram.setMasterDetailCreateFlow(this.value(), endpointId);
        thisProgram.renderPropertyInspector();
      }
    }).data("kendoDropDownList");
    endpointSelect.enable(config.createFlow.mode === "draftWithChildren");

    const detailList = $("<div class=\"program-builder-master-detail-list\"></div>").appendTo(context);
    if (!config.details.length) {
      $("<p class=\"program-builder-inline-muted\"></p>").text("Adicione ao menos uma entidade filha e o campo que a vincula ao mestre.").appendTo(detailList);
      return;
    }
    config.details.forEach(function(detail) {
      const row = $("<div class=\"program-builder-master-detail-row\"></div>").appendTo(detailList);
      $("<strong></strong>").text(detail.title || detail.entityCode).appendTo(row);
      $("<span></span>").text(detail.entityCode + " • " + detail.parentField).appendTo(row);
      const fields = thisProgram.masterDetailEntityFields(detail.entityCode);
      const displayOptions = fields.filter(function(field) {
        return field && field.primaryKey !== true && String(field.code || "") !== detail.parentField;
      }).map(function(field) {
        return { value: String(field.code || ""), text: String(field.label || field.code || "") + " (" + String(field.code || "") + ")" };
      });
      const numericOptions = fields.filter(function(field) {
        return field && ["integer", "decimal", "number", "currency"].indexOf(String(field.dataType || field.type || "").toLowerCase()) >= 0;
      }).map(function(field) {
        return { value: String(field.code || ""), text: String(field.label || field.code || "") + " (" + String(field.code || "") + ")" };
      });

      const titleField = thisProgram.appendPropertyField(row, "Titulo da filha", thisProgram.buildTechnicalProperties("Programa", "Titulo da filha", "Titulo da aba e da grade filha."));
      const titleInput = $("<input type=\"text\" class=\"program-builder-mini-input program-builder-master-detail-title\">").val(detail.title || "").appendTo(titleField);
      titleInput.on("input change", function() {
        thisProgram.updateMasterDetailDetail(detail.entityCode, { title: titleInput.val() });
      });

      const displayField = thisProgram.appendPropertyField(row, "Campos exibidos", thisProgram.buildTechnicalProperties("Programa", "Campos exibidos", "Campos existentes da filha que formam as colunas da grade."));
      const displaySelect = $("<select multiple class=\"program-builder-master-detail-display-fields\"></select>").appendTo(displayField);
      const displayWidget = displaySelect.kendoMultiSelect({
        dataSource: displayOptions,
        dataTextField: "text",
        dataValueField: "value",
        value: detail.displayFields || [],
        change: function() {
          thisProgram.updateMasterDetailDetail(detail.entityCode, { displayFields: this.value() });
        }
      }).data("kendoMultiSelect");
      if (displayWidget) {
        displayWidget.value(detail.displayFields || []);
      }

      const totalsField = thisProgram.appendPropertyField(row, "Campos totalizados", thisProgram.buildTechnicalProperties("Programa", "Campos totalizados", "Campos numericos da filha resumidos abaixo da grade."));
      const totalsSelect = $("<select multiple class=\"program-builder-master-detail-totals\"></select>").appendTo(totalsField);
      const totalsWidget = totalsSelect.kendoMultiSelect({
        dataSource: numericOptions,
        dataTextField: "text",
        dataValueField: "value",
        value: (detail.totals || []).map(function(total) { return total.field; }),
        change: function() {
          const previous = (thisProgram.masterDetailConfigValue().details.find(function(item) {
            return item.entityCode === detail.entityCode;
          }) || {}).totals || [];
          const totals = this.value().map(function(fieldCode) {
            const field = fields.find(function(item) { return String(item.code || "") === fieldCode; }) || {};
            const existing = previous.find(function(item) { return item.field === fieldCode; }) || {};
            return {
              field: fieldCode,
              label: existing.label || field.label || fieldCode,
              type: String(field.dataType || field.type || "number").toLowerCase()
            };
          });
          thisProgram.updateMasterDetailDetail(detail.entityCode, { totals: totals });
          thisProgram.renderPropertyInspector();
        }
      }).data("kendoMultiSelect");
      if (totalsWidget) {
        totalsWidget.value((detail.totals || []).map(function(total) { return total.field; }));
      }

      (detail.totals || []).forEach(function(total) {
        const labelField = thisProgram.appendPropertyField(row, "Rotulo do total " + total.field, thisProgram.buildTechnicalProperties("Programa", "Rotulo do total", "Texto exibido no resumo do campo totalizado."));
        const labelInput = $("<input type=\"text\" class=\"program-builder-mini-input program-builder-master-detail-total-label\">").val(total.label || "").appendTo(labelField);
        labelInput.on("input change", function() {
          const totals = (thisProgram.masterDetailConfigValue().details.find(function(item) {
            return item.entityCode === detail.entityCode;
          }) || {}).totals || [];
          totals.forEach(function(item) {
            if (item.field === total.field) {
              item.label = String(labelInput.val() || "").trim();
            }
          });
          thisProgram.updateMasterDetailDetail(detail.entityCode, { totals: totals });
        });
      });
      $("<button type=\"button\" class=\"k-button k-button-md k-rounded-md\"></button>").text("Remover").appendTo(row).on("click", function() {
        thisProgram.removeMasterDetail(detail.entityCode);
        thisProgram.renderPropertyInspector();
      });
    });
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
