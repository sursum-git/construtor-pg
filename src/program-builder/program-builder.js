(function(global) {
  "use strict";

  function ProgramBuilder(options) {
    this.options = options || {};
    this.root = $(this.options.root || "#program-builder-root");
    this.http = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.previewTimer = null;
    this.state = {
      entities: [],
      modules: [],
      currentModuleId: null,
      programs: [],
      entityVersions: [],
      currentEntityVersion: null,
      historySourceEntity: null,
      versions: [],
      currentVersion: null,
      currentProgramCode: "",
      currentEntityCode: "",
      originalEntityTableName: "",
      preview: null
    };
  }

  ProgramBuilder.FIELD_ABBREVIATIONS = {
    abreviado: "abrev",
    abreviado: "abrev",
    acumulado: "acum",
    pagar: "apg",
    receber: "arec",
    atualizacao: "atu",
    atualizacaoes: "atu",
    bruto: "bruto",
    centro: "cc",
    custo: "cc",
    cliente: "cli",
    cobranca: "cobr",
    codigo: "cod",
    condicao: "cond",
    credito: "cred",
    conta: "ct",
    desconto: "desc",
    descricao: "descr",
    diferenca: "dif",
    duplicata: "dupl",
    email: "email",
    emissao: "emis",
    endereco: "end",
    entrada: "entr",
    especial: "esp",
    especifico: "espec",
    especifica: "espec",
    fim: "fim",
    fornecedor: "forn",
    informacao: "inf",
    inicio: "ini",
    inscricao: "inscr",
    liberado: "lib",
    limite: "lim",
    liquido: "liq",
    moeda: "mo",
    modelo: "mod",
    natureza: "nat",
    nota: "nf",
    fiscal: "nf",
    nome: "nome",
    numero: "num",
    documento: "docto",
    observacao: "obs",
    origem: "ori",
    original: "orig",
    pagamento: "pagto",
    pacote: "pct",
    percentual: "perc",
    proximo: "prox",
    quantidade: "qt",
    rendimento: "rend",
    representante: "repres",
    saida: "saida",
    sequencia: "seq",
    situacao: "sit",
    telefone: "tel",
    titulo: "tit",
    tipo: "tp",
    taxa: "tx",
    valor: "vl"
  };

  ProgramBuilder.prototype.init = function() {
    this.renderShell();
    return this.loadBootstrap();
  };

  ProgramBuilder.prototype.renderShell = function() {
    this.root.empty();
    const shell = $("<section class=\"program-builder-shell\"></section>").appendTo(this.root);

    const appbar = $("<header class=\"program-builder-appbar\"></header>").appendTo(shell);
    const title = $("<div class=\"program-builder-title\"></div>").appendTo(appbar);
    $("<h1></h1>").text("Construtor de Programas").appendTo(title);
    $("<p></p>").text("Modele a entidade, gere o programa CRUD e controle o historico de versoes no mesmo fluxo.").appendTo(title);
    this.toolbarElement = $("<div class=\"program-builder-toolbar\"></div>").appendTo(appbar);
    this.newEntityButton = this.createToolbarButton("Nova entidade", "plus", "primary", this.handleNewEntity.bind(this));
    this.saveEntityButton = this.createToolbarButton("Salvar entidade", "save", null, this.handleSaveEntity.bind(this));
    this.restoreEntityButton = this.createToolbarButton("Restaurar entidade", "undo", null, this.handleRestoreEntityVersion.bind(this));
    this.newDraftButton = this.createToolbarButton("Novo rascunho", "file-add", null, this.handleNewDraft.bind(this));
    this.previewButton = this.createToolbarButton("Gerar preview", "eye", null, this.handlePreview.bind(this));
    this.saveDraftButton = this.createToolbarButton("Salvar rascunho", "save", null, this.handleSaveDraft.bind(this));
    this.publishButton = this.createToolbarButton("Publicar", "upload", null, this.handlePublish.bind(this));
    this.duplicateButton = this.createToolbarButton("Duplicar versao", "copy", null, this.handleDuplicate.bind(this));

    this.bannerElement = $("<section class=\"program-builder-banner\"></section>").text("Carregando metadados do construtor...").appendTo(shell);

    const content = $("<section class=\"program-builder-content\"></section>").appendTo(shell);
    const leftColumn = $("<section class=\"program-builder-left\"></section>").appendTo(content);

    this.modulesPanel = $("<article class=\"program-builder-panel\"></article>").appendTo(leftColumn);
    $("<h2></h2>").text("1. Modulos estruturais").appendTo(this.modulesPanel);
    this.modulesFormElement = $("<form class=\"program-builder-form\"></form>").appendTo(this.modulesPanel);
    this.modulesGridElement = $("<div></div>").appendTo(this.modulesPanel);

    this.entityPanel = $("<article class=\"program-builder-panel\"></article>").appendTo(leftColumn);
    $("<h2></h2>").text("2. Modelagem da entidade").appendTo(this.entityPanel);
    this.entityFormElement = $("<form class=\"program-builder-form\"></form>").appendTo(this.entityPanel);
    this.entityVersionsPanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(this.entityPanel);
    $("<div class=\"program-builder-versions-header\"><h3>Historico da entidade</h3><p>Cada salvamento gera uma revisao completa da modelagem e permite rollback estrutural.</p></div>").appendTo(this.entityVersionsPanel);
    this.entityVersionsGridElement = $("<div></div>").appendTo(this.entityVersionsPanel);

    this.programPanel = $("<article class=\"program-builder-panel\"></article>").appendTo(leftColumn);
    $("<h2></h2>").text("3. Geracao do programa").appendTo(this.programPanel);
    this.programFormElement = $("<form class=\"program-builder-form\"></form>").appendTo(this.programPanel);

    this.previewPanel = $("<article class=\"program-builder-panel program-builder-preview\"></article>").appendTo(content);
    $("<h2></h2>").text("Definicao gerada").appendTo(this.previewPanel);
    this.previewMeta = $("<div class=\"program-builder-preview-meta\"></div>").appendTo(this.previewPanel);
    this.previewElement = $("<pre></pre>").appendTo(this.previewPanel);
    this.previewFooter = $("<div class=\"program-builder-preview-footer\"></div>").appendTo(this.previewPanel);

    this.versionsPanel = $("<article class=\"program-builder-panel program-builder-versions\"></article>").appendTo(shell);
    $("<div class=\"program-builder-versions-header\"><h2>Historico de versoes</h2><p>Selecione uma versao para revisar, publicar novamente ou duplicar para um novo rascunho.</p></div>").appendTo(this.versionsPanel);
    this.versionsGridElement = $("<div></div>").appendTo(this.versionsPanel);

    this.renderModulesForm();
    this.renderModulesGrid();
    this.renderEntityForm();
    this.renderEntityVersionsGrid();
    this.renderProgramForm();
    this.renderVersionsGrid();
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.createToolbarButton = function(text, icon, themeColor, handler) {
    return $("<button type=\"button\"></button>")
      .text(text)
      .appendTo(this.toolbarElement)
      .kendoButton({
        icon: icon,
        themeColor: themeColor || "base",
        click: handler
      })
      .data("kendoButton");
  };

  ProgramBuilder.prototype.renderModulesForm = function() {
    const form = this.modulesFormElement;
    const splitA = $("<div class=\"program-builder-split program-builder-split-4\"></div>").appendTo(form);
    this.moduleCatalogCodeInput = this.createTextField(splitA, "Codigo");
    this.moduleCatalogNameInput = this.createTextField(splitA, "Nome");
    this.moduleCatalogAbbreviationInput = this.createTextField(splitA, "Abreviacao");
    this.moduleCatalogStartInput = this.createTextField(splitA, "Numero inicial");
    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.moduleCatalogEndInput = this.createTextField(splitB, "Numero final");

    const flagsField = this.appendField(form, "Status");
    const flags = $("<div class=\"program-builder-flags\"></div>").appendTo(flagsField);
    this.moduleCatalogEnabledInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Modulo habilitado").appendTo(this.moduleCatalogEnabledInput.parent());
    this.moduleCatalogEnabledInput.kendoCheckBox();

    const actions = $("<div class=\"program-builder-fields-header\"></div>").appendTo(form);
    $("<span></span>").text("Faixas numericas por modulo").appendTo(actions);
    $("<button type=\"button\"></button>").text("Salvar modulo").appendTo(actions).kendoButton({
      icon: "save",
      click: this.handleSaveModule.bind(this)
    });
  };

  ProgramBuilder.prototype.renderModulesGrid = function() {
    this.modulesGridElement.kendoGrid({
      dataSource: {
        data: [],
        pageSize: 6
      },
      selectable: "row",
      pageable: false,
      sortable: true,
      resizable: true,
      noRecords: {
        template: "Nenhum modulo estrutural cadastrado."
      },
      columns: [
        { field: "code", title: "Codigo", width: 140 },
        { field: "name", title: "Nome", width: 220 },
        { field: "abbreviation", title: "Abrev.", width: 100 },
        { field: "numberStart", title: "Inicial", width: 110 },
        { field: "numberEnd", title: "Final", width: 110 },
        { field: "enabled", title: "Ativo", width: 90, template: "#= enabled ? 'Sim' : 'Nao' #" }
      ],
      change: this.handleModuleSelection.bind(this)
    });
    this.modulesGrid = this.modulesGridElement.data("kendoGrid");
  };

  ProgramBuilder.prototype.renderEntityForm = function() {
    const form = this.entityFormElement;

    const entitySelectorField = this.appendField(form, "Entidade existente");
    this.entitySelectorInput = $("<input>").appendTo(entitySelectorField).kendoDropDownList({
      dataTextField: "title",
      dataValueField: "code",
      optionLabel: "Nova entidade",
      change: this.handleEntitySelection.bind(this)
    }).data("kendoDropDownList");

    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.entityCodeInput = this.createTextField(splitA, "Codigo da entidade");
    this.entityNameInput = this.createTextField(splitA, "Nome da entidade");

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.entityTableNameInput = this.createTextField(splitB, "Tabela fisica");
    const entityTypeField = this.appendField(splitB, "Tipo da entidade");
    this.entityTypeSelect = $("<input>").appendTo(entityTypeField).kendoDropDownList({
      dataSource: [
        { value: "persistence", text: "Persistence" },
        { value: "query", text: "Query" },
        { value: "io", text: "IO" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "persistence",
      change: this.syncEntityTypeState.bind(this)
    }).data("kendoDropDownList");

    const splitC = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.entitySituationFieldInput = this.createTextField(splitC, "Campo de situacao");
    this.entityTypeHint = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(form);
    this.entityTypeHint.text("Fluxo completo atual: tipo persistence com tabela fisica e programa CRUD.");

    this.renderStructureEditor(form);

    const flagsField = this.appendField(form, "Opcoes da entidade");
    const flags = $("<div class=\"program-builder-flags\"></div>").appendTo(flagsField);
    this.entityCreateTableInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Criar tabela fisica").appendTo(this.entityCreateTableInput.parent());
    this.entityAllowTableRenameInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Renomear tabela").appendTo(this.entityAllowTableRenameInput.parent());
    this.entityAllowColumnRenameInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Renomear colunas").appendTo(this.entityAllowColumnRenameInput.parent());
    this.entityDropRemovedColumnsInput = $("<input type=\"checkbox\">").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Excluir colunas removidas").appendTo(this.entityDropRemovedColumnsInput.parent());
    this.entitySituationEnabledInput = $("<input type=\"checkbox\">").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Habilitar situacao").appendTo(this.entitySituationEnabledInput.parent());
    this.entityVersioningEnabledInput = $("<input type=\"checkbox\">").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Cadastro versionado").appendTo(this.entityVersioningEnabledInput.parent());
    this.entityVersioningDeduplicateInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Reusar snapshot igual").appendTo(this.entityVersioningDeduplicateInput.parent());
    this.entityCreateTableInput.kendoCheckBox();
    this.entityAllowTableRenameInput.kendoCheckBox();
    this.entityAllowColumnRenameInput.kendoCheckBox();
    this.entityDropRemovedColumnsInput.kendoCheckBox();
    this.entitySituationEnabledInput.kendoCheckBox({
      change: this.syncSituationFieldState.bind(this)
    });
    this.entityVersioningEnabledInput.kendoCheckBox({
      change: this.syncEntityTypeState.bind(this)
    });
    this.entityVersioningDeduplicateInput.kendoCheckBox();

    const fieldsHeader = $("<div class=\"program-builder-fields-header\"></div>").appendTo(form);
    $("<span></span>").text("Campos da entidade").appendTo(fieldsHeader);
    $("<button type=\"button\"></button>").text("Sugerir nomes").appendTo(fieldsHeader).kendoButton({
      icon: "wand",
      click: this.handleSuggestFieldNames.bind(this)
    });
    $("<button type=\"button\"></button>").text("Adicionar campo").appendTo(fieldsHeader).kendoButton({
      icon: "plus",
      click: this.handleAddFieldRow.bind(this)
    });

    this.fieldsTableElement = $("<table class=\"program-builder-fields-table\"></table>").appendTo(form);
    $("<thead><tr><th>Campo</th><th>Label</th><th>Tipo</th><th>Coluna</th><th>Tam.</th><th>Obrig.</th><th>PK</th><th></th></tr></thead>").appendTo(this.fieldsTableElement);
    this.fieldsTableBody = $("<tbody></tbody>").appendTo(this.fieldsTableElement);

    this.namingHintElement = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(form);
    this.namingHintElement.text("Padrao Genesis: tabelas como t1, t1c1, t1e1, t1r, t1m, t1e2at2e3; campos com prefixos tecnicos como c_, t_, d_, dt_, dt_hr_, log_ e sufixo _id para FKs.");

    this.renderUniqueKeysEditor(form);
    this.renderRulesEditor(form);
    this.renderHistoricalAssistant(form);
  };

  ProgramBuilder.prototype.renderStructureEditor = function(form) {
    this.structurePanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Estrutura da tabela</h3><p>Defina o modulo, a classificacao estrutural e gere o nome fisico conforme o padrao Genesis.</p></div>").appendTo(this.structurePanel);

    const structureForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.structurePanel);

    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(structureForm);
    const moduleField = this.appendField(splitA, "Modulo estrutural");
    this.entityStructureModuleSelect = $("<input>").appendTo(moduleField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione o modulo",
      change: this.syncStructureState.bind(this)
    }).data("kendoDropDownList");

    const typeField = this.appendField(splitA, "Tipo estrutural");
    this.entityStructureTypeSelect = $("<input>").appendTo(typeField).kendoDropDownList({
      dataSource: [
        { value: "main", text: "Principal" },
        { value: "composition", text: "Composicao" },
        { value: "specific_relation", text: "Especifica" },
        { value: "aggregation", text: "Agregacao" },
        { value: "recursive", text: "Recursiva" },
        { value: "multi_level", text: "Multinivel" },
        { value: "view", text: "View" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "main",
      change: this.syncStructureState.bind(this)
    }).data("kendoDropDownList");

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(structureForm);
    this.entityStructureBaseNumberInput = this.createTextField(splitB, "Numero base");
    this.entityStructureSequenceNumberInput = this.createTextField(splitB, "Sequencia");

    const splitC = $("<div class=\"program-builder-split program-builder-split-3\"></div>").appendTo(structureForm);
    const parentField = this.appendField(splitC, "Entidade pai/base");
    this.entityStructureParentSelect = $("<input>").appendTo(parentField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a entidade",
      change: this.syncStructureState.bind(this)
    }).data("kendoDropDownList");

    const leftField = this.appendField(splitC, "Entidade esquerda");
    this.entityStructureLeftSelect = $("<input>").appendTo(leftField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a entidade",
      change: this.syncStructureState.bind(this)
    }).data("kendoDropDownList");

    const rightField = this.appendField(splitC, "Entidade direita");
    this.entityStructureRightSelect = $("<input>").appendTo(rightField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a entidade",
      change: this.syncStructureState.bind(this)
    }).data("kendoDropDownList");

    const actions = $("<div class=\"program-builder-fields-header\"></div>").appendTo(structureForm);
    $("<span></span>").text("Assistente de nomenclatura").appendTo(actions);
    $("<button type=\"button\"></button>").text("Sugerir tabela").appendTo(actions).kendoButton({
      icon: "wand",
      click: this.handleSuggestTableName.bind(this)
    });

    this.structureHintElement = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(structureForm);
    this.structureHintElement.text("Tipos principais usam faixa numerica do modulo. Estruturas derivadas usam a entidade pai ou as entidades agregadas.");
  };

  ProgramBuilder.prototype.renderUniqueKeysEditor = function(form) {
    this.uniqueKeysPanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Chaves unicas</h3><p>Cadastre chaves unicas compostas. Para chave unica de um campo só, continue usando a opcao Unico do campo.</p></div>").appendTo(this.uniqueKeysPanel);

    const header = $("<div class=\"program-builder-fields-header\"></div>").appendTo(this.uniqueKeysPanel);
    $("<span></span>").text("Chaves configuradas").appendTo(header);
    $("<button type=\"button\"></button>").text("Adicionar chave").appendTo(header).kendoButton({
      icon: "plus",
      click: this.handleAddUniqueKeyRow.bind(this)
    });

    this.uniqueKeysTableElement = $("<table class=\"program-builder-rules-table\"></table>").appendTo(this.uniqueKeysPanel);
    $("<thead><tr><th>Nome</th><th>Campos</th><th></th></tr></thead>").appendTo(this.uniqueKeysTableElement);
    this.uniqueKeysTableBody = $("<tbody></tbody>").appendTo(this.uniqueKeysTableElement);
  };

  ProgramBuilder.prototype.renderRulesEditor = function(form) {
    this.rulesPanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Regras de negocio</h3><p>Cadastre regras declarativas ou por classe/metodo, com ordem de execucao, continuidade apos erro e log em transacao.</p></div>").appendTo(this.rulesPanel);

    const rulesHeader = $("<div class=\"program-builder-fields-header\"></div>").appendTo(this.rulesPanel);
    $("<span></span>").text("Regras configuradas").appendTo(rulesHeader);
    $("<button type=\"button\"></button>").text("Adicionar regra").appendTo(rulesHeader).kendoButton({
      icon: "plus",
      click: this.handleAddRuleRow.bind(this)
    });

    this.rulesTableElement = $("<table class=\"program-builder-rules-table\"></table>").appendTo(this.rulesPanel);
    $("<thead><tr><th>Ordem</th><th>Fase</th><th>Tipo</th><th>Regra</th><th>Ativa</th><th>Continua</th><th></th></tr></thead>").appendTo(this.rulesTableElement);
    this.rulesTableBody = $("<tbody></tbody>").appendTo(this.rulesTableElement);
  };

  ProgramBuilder.prototype.renderHistoricalAssistant = function(form) {
    this.historyAssistantPanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Assistente de historico</h3><p>Monte referencias historicas para itens transacionais a partir de um cadastro mestre versionado.</p></div>").appendTo(this.historyAssistantPanel);

    const helperForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.historyAssistantPanel);
    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(helperForm);

    const sourceEntityField = this.appendField(splitA, "Cadastro mestre versionado");
    this.historyEntitySelect = $("<input>").appendTo(sourceEntityField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a entidade",
      change: this.handleHistoricalEntityChange.bind(this)
    }).data("kendoDropDownList");

    const sourceIdField = this.appendField(splitA, "Campo origem ID");
    this.historySourceFieldSelect = $("<input>").appendTo(sourceIdField).kendoDropDownList({
      dataTextField: "label",
      dataValueField: "code",
      optionLabel: "Selecione o campo",
      change: function() {
        const currentSourceField = String(this.historySourceFieldSelect.value() || "");
        if (currentSourceField && !String(this.historyAliasInput.value() || "").trim()) {
          this.historyAliasInput.value(this.normalizeHistoryAlias(currentSourceField));
        }
      }.bind(this)
    }).data("kendoDropDownList");

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(helperForm);
    this.historyAliasInput = this.createTextField(splitB, "Prefixo dos campos");
    this.historyAliasInput.value("");

    const fieldsField = this.appendField(helperForm, "Campos historicos");
    this.historyFieldsList = $("<div class=\"program-builder-history-fields\"></div>").appendTo(fieldsField);

    const actions = $("<div class=\"program-builder-history-actions\"></div>").appendTo(helperForm);
    $("<button type=\"button\"></button>").text("Gerar campos historicos").appendTo(actions).kendoButton({
      icon: "sparkles",
      click: this.handleApplyHistoricalAssistant.bind(this)
    });
  };

  ProgramBuilder.prototype.renderProgramForm = function() {
    const form = this.programFormElement;

    const programSelectorField = this.appendField(form, "Programa existente");
    this.programSelectorInput = $("<input>").appendTo(programSelectorField).kendoDropDownList({
      dataTextField: "title",
      dataValueField: "code",
      optionLabel: "Novo programa",
      change: this.handleProgramSelection.bind(this)
    }).data("kendoDropDownList");

    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.programCodeInput = this.createTextField(splitA, "Codigo do programa");
    this.programTitleInput = this.createTextField(splitA, "Titulo do programa");

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    const moduleProgramField = this.appendField(splitB, "Modulo");
    this.moduleInput = $("<input>").appendTo(moduleProgramField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione o modulo",
      change: this.handleProgramModuleChange.bind(this)
    }).data("kendoDropDownList");
    this.screenIdInput = this.createTextField(splitB, "Screen ID");

    const splitC = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    const builderEntityField = this.appendField(splitC, "Entidade base");
    this.builderEntitySelect = $("<input>").appendTo(builderEntityField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a entidade",
      change: this.handleProgramEntityChange.bind(this)
    }).data("kendoDropDownList");
    this.versionInput = this.createTextField(splitC, "Versao");

    const splitD = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.subtitleInput = this.createTextField(splitD, "Subtitulo");
    this.iconInput = this.createTextField(splitD, "Icone");

    const splitE = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.permissionPrefixInput = this.createTextField(splitE, "Prefixo de permissao");
    const pageTypeField = this.appendField(splitE, "Tipo de pagina");
    this.pageTypeSelect = $("<input>").appendTo(pageTypeField).kendoDropDownList({
      dataSource: [{ value: "crud", text: "CRUD" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "crud",
      enable: false
    }).data("kendoDropDownList");

    const flagsField = this.appendField(form, "Permissoes de escrita");
    const flags = $("<div class=\"program-builder-flags\"></div>").appendTo(flagsField);
    this.allowCreateInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Permitir inclusao").appendTo(this.allowCreateInput.parent());
    this.allowUpdateInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Permitir alteracao").appendTo(this.allowUpdateInput.parent());
    this.allowDeleteInput = $("<input type=\"checkbox\">").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Permitir exclusao").appendTo(this.allowDeleteInput.parent());
    this.allowCreateInput.kendoCheckBox({ change: this.schedulePreview.bind(this) });
    this.allowUpdateInput.kendoCheckBox({ change: this.schedulePreview.bind(this) });
    this.allowDeleteInput.kendoCheckBox({ change: this.schedulePreview.bind(this) });

    const summaryField = this.appendField(form, "Resumo da versao");
    this.changeSummaryTextArea = $("<textarea rows=\"4\"></textarea>").appendTo(summaryField).kendoTextArea({
      inputMode: "text",
      placeholder: "Descreva o objetivo desta versao."
    }).data("kendoTextArea");

    this.attachLivePreview();
  };

  ProgramBuilder.prototype.appendField = function(parent, label) {
    const wrapper = $("<label class=\"program-builder-field\"></label>").appendTo(parent);
    $("<span></span>").text(label).appendTo(wrapper);
    return wrapper;
  };

  ProgramBuilder.prototype.createTextField = function(parent, label) {
    const wrapper = this.appendField(parent, label);
    return $("<input>").appendTo(wrapper).kendoTextBox().data("kendoTextBox");
  };

  ProgramBuilder.prototype.attachLivePreview = function() {
    const self = this;
    [
      this.programCodeInput,
      this.programTitleInput,
      this.screenIdInput,
      this.versionInput,
      this.subtitleInput,
      this.iconInput,
      this.permissionPrefixInput
    ].forEach(function(widget) {
      const input = widget && (widget.input || widget.element);
      if (!input || typeof input.on !== "function") {
        return;
      }
      input.on("input", self.schedulePreview.bind(self));
      input.on("change", self.schedulePreview.bind(self));
    });

    this.changeSummaryTextArea.element.on("input", this.schedulePreview.bind(this));
    this.changeSummaryTextArea.element.on("change", this.schedulePreview.bind(this));
    this.moduleInput.bind("change", this.schedulePreview.bind(this));
  };

  ProgramBuilder.prototype.renderVersionsGrid = function() {
    this.versionsGridElement.kendoGrid({
      dataSource: {
        data: [],
        pageSize: 8
      },
      selectable: "row",
      pageable: false,
      sortable: true,
      resizable: true,
      noRecords: {
        template: "Nenhuma versao registrada para este programa."
      },
      columns: [
        { field: "version", title: "Versao", width: 120 },
        { field: "status", title: "Status", width: 120 },
        { field: "builderEntityCode", title: "Entidade", width: 160 },
        { field: "screenId", title: "Screen ID", width: 220 },
        { field: "updatedAt", title: "Atualizado em", width: 180, template: "#= kendo.toString(kendo.parseDate(updatedAt), 'dd/MM/yyyy HH:mm') || '' #" },
        { field: "changeSummary", title: "Resumo" }
      ],
      change: this.handleVersionSelection.bind(this),
      dataBound: this.syncSelectedVersionRow.bind(this)
    });
    this.versionsGrid = this.versionsGridElement.data("kendoGrid");
  };

  ProgramBuilder.prototype.renderEntityVersionsGrid = function() {
    this.entityVersionsGridElement.kendoGrid({
      dataSource: {
        data: [],
        pageSize: 6
      },
      selectable: "row",
      pageable: false,
      sortable: true,
      resizable: true,
      noRecords: {
        template: "Nenhuma revisao registrada para esta entidade."
      },
      columns: [
        { field: "revision", title: "Rev.", width: 90 },
        { field: "status", title: "Status", width: 100 },
        { field: "action", title: "Acao", width: 100 },
        { field: "tableName", title: "Tabela", width: 160 },
        { field: "updatedAt", title: "Atualizado em", width: 180, template: "#= kendo.toString(kendo.parseDate(updatedAt), 'dd/MM/yyyy HH:mm') || '' #" },
        { field: "changeSummary", title: "Resumo" }
      ],
      change: this.handleEntityVersionSelection.bind(this),
      dataBound: this.syncSelectedEntityVersionRow.bind(this)
    });
    this.entityVersionsGrid = this.entityVersionsGridElement.data("kendoGrid");
  };

  ProgramBuilder.prototype.loadBootstrap = function() {
    return this.http.request({
      url: "/api/admin/program-builder/bootstrap",
      method: "GET"
    }).then((payload) => {
      this.state.entities = payload.entities || [];
      this.state.modules = payload.modules || [];
      this.state.programs = payload.programs || [];
      this.applyBootstrapData();
      this.resetModuleForm();
      this.resetEntityForm();
      this.resetProgramForm();
      this.bannerElement.text("Modele uma entidade nova ou escolha uma existente. Depois gere e publique o programa CRUD.");
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel carregar o construtor.");
    });
  };

  ProgramBuilder.prototype.applyBootstrapData = function() {
    const modules = this.state.modules.map(function(item) {
      return { code: item.code, name: (item.abbreviation || "").toUpperCase() + " - " + item.name + " (" + item.numberStart + "-" + item.numberEnd + ")" };
    });
    this.modulesGrid.dataSource.data(this.state.modules);
    this.entitySelectorInput.setDataSource(new kendo.data.DataSource({
      data: this.state.entities.map(function(item) {
        return { code: item.code, title: item.code + " - " + item.name + " (" + (item.entityType || "persistence") + ")" };
      })
    }));
    this.builderEntitySelect.setDataSource(new kendo.data.DataSource({
      data: this.state.entities.map(function(item) {
        return { code: item.code, name: item.code + " - " + item.name + " (" + (item.entityType || "persistence") + ")" };
      })
    }));
    this.programSelectorInput.setDataSource(new kendo.data.DataSource({
      data: this.state.programs.map(function(item) {
        return { code: item.code, title: item.code + " - " + item.title };
      })
    }));
    this.moduleInput.setDataSource(new kendo.data.DataSource({ data: modules }));
    this.entityStructureModuleSelect.setDataSource(new kendo.data.DataSource({ data: modules }));
    this.entityStructureParentSelect.setDataSource(new kendo.data.DataSource({ data: this.buildStructureEntityOptions() }));
    this.entityStructureLeftSelect.setDataSource(new kendo.data.DataSource({ data: this.buildStructureEntityOptions() }));
    this.entityStructureRightSelect.setDataSource(new kendo.data.DataSource({ data: this.buildStructureEntityOptions() }));
    this.refreshHistoricalAssistantOptions();
  };

  ProgramBuilder.prototype.buildStructureEntityOptions = function() {
    return this.state.entities.map(function(item) {
      return {
        code: item.code,
        name: item.code + " - " + item.name + (item.tableName ? " [" + item.tableName + "]" : "")
      };
    });
  };

  ProgramBuilder.prototype.handleEntitySelection = function() {
    const code = String(this.entitySelectorInput.value() || "");
    if (!code) {
      this.handleNewEntity();
      return;
    }

    this.http.request({
      url: "/api/admin/program-builder/entities/" + encodeURIComponent(code),
      method: "GET"
    }).then((payload) => {
      this.populateEntityForm(payload.entity || null, payload.versions || []);
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel carregar a entidade.");
    });
  };

  ProgramBuilder.prototype.handleModuleSelection = function() {
    const item = this.modulesGrid.dataItem(this.modulesGrid.select());
    if (item) {
      this.populateModuleForm(item.toJSON ? item.toJSON() : item);
    }
  };

  ProgramBuilder.prototype.populateModuleForm = function(module) {
    const item = module || {};
    this.state.currentModuleId = item.id || null;
    this.moduleCatalogCodeInput.value(item.code || "");
    this.moduleCatalogNameInput.value(item.name || "");
    this.moduleCatalogAbbreviationInput.value(item.abbreviation || "");
    this.moduleCatalogStartInput.value(item.numberStart != null ? String(item.numberStart) : "");
    this.moduleCatalogEndInput.value(item.numberEnd != null ? String(item.numberEnd) : "");
    this.moduleCatalogEnabledInput.prop("checked", item.enabled !== false);
  };

  ProgramBuilder.prototype.resetModuleForm = function() {
    this.state.currentModuleId = null;
    this.moduleCatalogCodeInput.value("");
    this.moduleCatalogNameInput.value("");
    this.moduleCatalogAbbreviationInput.value("");
    this.moduleCatalogStartInput.value("");
    this.moduleCatalogEndInput.value("");
    this.moduleCatalogEnabledInput.prop("checked", true);
    if (this.modulesGrid) {
      this.modulesGrid.clearSelection();
    }
  };

  ProgramBuilder.prototype.handleSaveModule = function() {
    const payload = {
      id: this.state.currentModuleId,
      code: this.moduleCatalogCodeInput.value(),
      name: this.moduleCatalogNameInput.value(),
      abbreviation: this.moduleCatalogAbbreviationInput.value(),
      numberStart: this.moduleCatalogStartInput.value(),
      numberEnd: this.moduleCatalogEndInput.value(),
      enabled: this.moduleCatalogEnabledInput.is(":checked")
    };

    this.http.request({
      url: "/api/admin/program-builder/modules",
      method: "POST",
      data: payload
    }).then((response) => {
      global.CrudUtils.showMessage("Modulo salvo.", "success");
      this.state.modules = response.modules || [];
      this.applyBootstrapData();
      this.populateModuleForm(response.module || {});
      this.syncStructureState();
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel salvar o modulo.");
    });
  };

  ProgramBuilder.prototype.populateEntityForm = function(entity, versions) {
    const item = entity || {};
    this.state.historySourceEntity = null;
    this.state.currentEntityCode = item.code || "";
    this.state.originalEntityTableName = item.tableName || "";
    this.state.entityVersions = Array.isArray(versions) ? versions : this.state.entityVersions;
    this.state.currentEntityVersion = this.state.entityVersions[0] || null;
    this.entityCodeInput.value(item.code || "");
    this.entityNameInput.value(item.name || "");
    this.entityTableNameInput.value(item.tableName || "");
    this.entityTypeSelect.value(item.entityType || "persistence");
    this.entityStructureModuleSelect.value(item.structureModuleCode || "");
    this.entityStructureTypeSelect.value(item.structureType || "main");
    this.entityStructureBaseNumberInput.value(item.structureBaseNumber != null ? String(item.structureBaseNumber) : "");
    this.entityStructureSequenceNumberInput.value(item.structureSequenceNumber != null ? String(item.structureSequenceNumber) : "");
    this.entityStructureParentSelect.value(item.structureParentEntityCode || "");
    this.entityStructureLeftSelect.value(item.structureLeftEntityCode || "");
    this.entityStructureRightSelect.value(item.structureRightEntityCode || "");
    this.entitySituationEnabledInput.prop("checked", item.situationEnabled === true);
    this.entitySituationFieldInput.value(item.situationFieldCode || "status");
    this.entityVersioningEnabledInput.prop("checked", item.versioningEnabled === true);
    this.entityVersioningDeduplicateInput.prop("checked", item.versioningDeduplicate !== false);
    this.entityCreateTableInput.prop("checked", true);
    this.entityAllowTableRenameInput.prop("checked", true);
    this.entityAllowColumnRenameInput.prop("checked", true);
    this.entityDropRemovedColumnsInput.prop("checked", false);
    this.renderFieldRows(item.fields || []);
    this.renderUniqueKeyRows(item.uniqueKeys || []);
    this.renderRuleRows(item.rules || []);
    this.entityVersionsGrid.dataSource.data(this.state.entityVersions);
    this.historyEntitySelect.value("");
    this.historyFieldsList.empty();
    this.syncEntityTypeState();
    this.syncStructureState();
    this.syncSituationFieldState();
    this.refreshHistoricalAssistantSourceFields();
    this.builderEntitySelect.value(item.code || "");
    this.handleProgramEntityChange();
    this.syncSelectedEntityVersionRow();
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.renderFieldRows = function(fields) {
    this.fieldsTableBody.empty();
    const rows = fields && fields.length ? fields : [this.defaultFieldRow(), this.defaultNameFieldRow()];
    rows.forEach(this.addFieldRow.bind(this));
    this.refreshHistoricalAssistantSourceFields();
  };

  ProgramBuilder.prototype.renderRuleRows = function(rules) {
    this.rulesTableBody.empty();
    (rules || []).forEach(this.addRuleRow.bind(this));
  };

  ProgramBuilder.prototype.renderUniqueKeyRows = function(keys) {
    this.uniqueKeysTableBody.empty();
    (keys || []).forEach(this.addUniqueKeyRow.bind(this));
  };

  ProgramBuilder.prototype.addUniqueKeyRow = function(key) {
    const item = key || {};
    const row = $("<tr></tr>").appendTo(this.uniqueKeysTableBody);
    $("<td><input type=\"text\" class=\"program-builder-mini-input program-builder-unique-key-name\"></td>").appendTo(row).find("input").val(item.name || "");
    $("<td><input type=\"text\" class=\"program-builder-mini-input program-builder-unique-key-fields\"></td>").appendTo(row).find("input").val(Array.isArray(item.fields) ? item.fields.join(", ") : "");
    $("<td class=\"program-builder-check-cell\"><button type=\"button\" class=\"program-builder-remove-unique-key\">Remover</button></td>").appendTo(row);
    row.find(".program-builder-remove-unique-key").kendoButton({
      icon: "trash",
      click: this.handleRemoveUniqueKeyRow.bind(this, row)
    });
  };

  ProgramBuilder.prototype.handleAddUniqueKeyRow = function() {
    this.addUniqueKeyRow({
      name: "",
      fields: []
    });
  };

  ProgramBuilder.prototype.handleRemoveUniqueKeyRow = function(row) {
    row.remove();
  };

  ProgramBuilder.prototype.addRuleRow = function(rule) {
    const item = rule || {};
    const row = $("<tr></tr>").appendTo(this.rulesTableBody);
    $("<td><input type=\"number\" min=\"0\" class=\"program-builder-mini-input program-builder-rule-order\"></td>").appendTo(row).find("input").val(item.order != null ? item.order : ((this.rulesTableBody.children().length + 1) * 10));

    const phaseCell = $("<td></td>").appendTo(row);
    const phaseSelect = $("<select class=\"program-builder-mini-select program-builder-rule-phase\"></select>").appendTo(phaseCell);
    [
      { value: "beforeValidate", text: "Antes da validacao" },
      { value: "beforePersist", text: "Antes de gravar" },
      { value: "afterPersist", text: "Apos gravar" },
      { value: "afterCommit", text: "Apos concluir" }
    ].forEach(function(option) {
      $("<option></option>").attr("value", option.value).text(option.text).appendTo(phaseSelect);
    });
    phaseSelect.val(item.phase || "beforeValidate");

    const typeCell = $("<td></td>").appendTo(row);
    const typeSelect = $("<select class=\"program-builder-mini-select program-builder-rule-type\"></select>").appendTo(typeCell);
    [
      { value: "requiredWhen", text: "Declarativa" },
      { value: "class_method", text: "Classe/metodo" }
    ].forEach(function(option) {
      $("<option></option>").attr("value", option.value).text(option.text).appendTo(typeSelect);
    });
    typeSelect.val(item.ruleType || "requiredWhen");

    $("<td><input type=\"text\" class=\"program-builder-mini-input program-builder-rule-label\"></td>").appendTo(row).find("input").val(item.label || "");
    $("<td class=\"program-builder-check-cell\"><input type=\"checkbox\" class=\"program-builder-rule-enabled\"></td>").appendTo(row).find("input").prop("checked", item.enabled !== false);
    $("<td class=\"program-builder-check-cell\"><input type=\"checkbox\" class=\"program-builder-rule-continue\"></td>").appendTo(row).find("input").prop("checked", item.continueOnError === true);
    $("<td class=\"program-builder-check-cell\"><button type=\"button\" class=\"program-builder-remove-rule\">Remover</button></td>").appendTo(row);

    const detailsRow = $("<tr class=\"program-builder-rule-details-row\"></tr>").appendTo(this.rulesTableBody);
    const detailsCell = $("<td colspan=\"7\"></td>").appendTo(detailsRow);
    const detailsGrid = $("<div class=\"program-builder-field-details\"></div>").appendTo(detailsCell);

    const idField = this.appendField(detailsGrid, "ID tecnico");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-rule-id\">").appendTo(idField).val(item.id || "");

    const ruleField = this.appendField(detailsGrid, "Campo validado");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-rule-field\">").appendTo(ruleField).val(item.field || "");

    const whenField = this.appendField(detailsGrid, "Campo gatilho");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-rule-when-field\">").appendTo(whenField).val(item.whenField || "");

    const whenEqualsField = this.appendField(detailsGrid, "Valor do gatilho");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-rule-when-equals\">").appendTo(whenEqualsField).val(item.whenEquals == null ? "" : item.whenEquals);

    const messageField = this.appendField(detailsGrid, "Mensagem de erro");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-rule-message\">").appendTo(messageField).val(item.message || "");

    const classField = this.appendField(detailsGrid, "Classe");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-rule-class-name\">").appendTo(classField).val(item.className || "");

    const methodField = this.appendField(detailsGrid, "Metodo");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-rule-method-name\">").appendTo(methodField).val(item.methodName || "");

    const paramsField = this.appendField(detailsGrid, "Parametros JSON");
    $("<textarea rows=\"4\" class=\"program-builder-mini-textarea program-builder-rule-params\"></textarea>").appendTo(paramsField).val(this.stringifyRuleParams(item.params || {}));

    row.find(".program-builder-remove-rule").kendoButton({
      icon: "trash",
      click: this.handleRemoveRuleRow.bind(this, row, detailsRow)
    });
    row.find(".program-builder-rule-enabled").kendoCheckBox();
    row.find(".program-builder-rule-continue").kendoCheckBox();

    typeSelect.on("change", this.syncRuleRowState.bind(this, row, detailsRow));
    this.syncRuleRowState(row, detailsRow);
  };

  ProgramBuilder.prototype.handleAddRuleRow = function() {
    this.addRuleRow({
      id: "",
      label: "",
      order: (this.rulesTableBody.find("tr").filter(function() {
        return !$(this).hasClass("program-builder-rule-details-row");
      }).length + 1) * 10,
      phase: "beforeValidate",
      ruleType: "requiredWhen",
      enabled: true,
      continueOnError: false,
      field: "",
      whenField: "",
      whenEquals: "",
      message: "",
      className: "",
      methodName: "",
      params: {}
    });
  };

  ProgramBuilder.prototype.handleRemoveRuleRow = function(row, detailsRow) {
    row.remove();
    detailsRow.remove();
  };

  ProgramBuilder.prototype.syncRuleRowState = function(row, detailsRow) {
    const type = row.find(".program-builder-rule-type").val();
    const declarative = type === "requiredWhen";
    detailsRow.find(".program-builder-rule-field").prop("disabled", !declarative);
    detailsRow.find(".program-builder-rule-when-field").prop("disabled", !declarative);
    detailsRow.find(".program-builder-rule-when-equals").prop("disabled", !declarative);
    detailsRow.find(".program-builder-rule-class-name").prop("disabled", declarative);
    detailsRow.find(".program-builder-rule-method-name").prop("disabled", declarative);
    if (declarative) {
      detailsRow.find(".program-builder-rule-class-name").val("");
      detailsRow.find(".program-builder-rule-method-name").val("");
    } else {
      detailsRow.find(".program-builder-rule-field").val("");
      detailsRow.find(".program-builder-rule-when-field").val("");
      detailsRow.find(".program-builder-rule-when-equals").val("");
    }
  };

  ProgramBuilder.prototype.addFieldRow = function(field) {
    const item = field || {};
    const row = $("<tr></tr>").appendTo(this.fieldsTableBody);
    row.attr("data-field-id", item.id || "");
    row.attr("data-original-code", item.originalCode || item.code || "");
    row.attr("data-original-column", item.originalColumnName || item.columnName || item.code || "");
    $("<td><input type=\"text\" class=\"program-builder-mini-input program-builder-field-code\"></td>").appendTo(row).find("input").val(item.code || "");
    $("<td><input type=\"text\" class=\"program-builder-mini-input program-builder-field-label\"></td>").appendTo(row).find("input").val(item.label || "");

    const typeCell = $("<td></td>").appendTo(row);
    const typeSelect = $("<select class=\"program-builder-mini-select program-builder-field-type\"></select>").appendTo(typeCell);
    ["string", "text", "integer", "decimal", "boolean", "date", "datetime", "enum", "dropdown", "email", "json", "custom_code"].forEach(function(type) {
      $("<option></option>").attr("value", type).text(type).appendTo(typeSelect);
    });
    typeSelect.val(item.dataType || "string");

    $("<td><input type=\"text\" class=\"program-builder-mini-input program-builder-field-column\"></td>").appendTo(row).find("input").val(item.columnName || item.code || "");
    $("<td><input type=\"number\" min=\"0\" class=\"program-builder-mini-input program-builder-field-length\"></td>").appendTo(row).find("input").val(item.length || "");
    $("<td class=\"program-builder-check-cell\"><input type=\"checkbox\" class=\"program-builder-field-required\"></td>").appendTo(row).find("input").prop("checked", item.required === true);
    $("<td class=\"program-builder-check-cell\"><input type=\"checkbox\" class=\"program-builder-field-pk\"></td>").appendTo(row).find("input").prop("checked", item.primaryKey === true);
    $("<td class=\"program-builder-check-cell\"><button type=\"button\" class=\"program-builder-remove-row\">Remover</button></td>").appendTo(row);

    const detailsRow = $("<tr class=\"program-builder-field-details-row\"></tr>").appendTo(this.fieldsTableBody);
    const detailsCell = $("<td colspan=\"8\"></td>").appendTo(detailsRow);
    const detailsGrid = $("<div class=\"program-builder-field-details\"></div>").appendTo(detailsCell);

    const precisionField = this.appendField(detailsGrid, "Precision");
    $("<input type=\"number\" min=\"0\" class=\"program-builder-mini-input program-builder-field-precision\">").appendTo(precisionField).val(item.precision || "");

    const scaleField = this.appendField(detailsGrid, "Scale");
    $("<input type=\"number\" min=\"0\" class=\"program-builder-mini-input program-builder-field-scale\">").appendTo(scaleField).val(item.scale || "");

    const defaultField = this.appendField(detailsGrid, "Valor padrao");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-default\">").appendTo(defaultField).val(item.defaultValue == null ? "" : item.defaultValue);

    const uniqueField = this.appendField(detailsGrid, "Unico");
    $("<input type=\"checkbox\" class=\"program-builder-field-unique\">").appendTo(uniqueField).prop("checked", item.unique === true);

    const readonlyField = this.appendField(detailsGrid, "Nao editavel");
    $("<input type=\"checkbox\" class=\"program-builder-field-readonly\">").appendTo(readonlyField).prop("checked", item.readonlyField === true);

    const fkTableField = this.appendField(detailsGrid, "FK tabela");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-fk-table\">").appendTo(fkTableField).val(item.foreignKeyTable || "");

    const fkColumnField = this.appendField(detailsGrid, "FK coluna");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-fk-column\">").appendTo(fkColumnField).val(item.foreignKeyColumn || "");

    const fkTypeField = this.appendField(detailsGrid, "Dependencia");
    const fkTypeSelect = $("<select class=\"program-builder-mini-select program-builder-field-fk-type\"></select>").appendTo(fkTypeField);
    [
      { value: "", text: "Nao classificada" },
      { value: "reference", text: "Referencia" },
      { value: "composition", text: "Composicao" },
      { value: "aggregation", text: "Agregacao" },
      { value: "specific_relation", text: "Relacao especifica" },
      { value: "recursive", text: "Recursiva" },
      { value: "multi_level", text: "Multi nivel" }
    ].forEach(function(option) {
      $("<option></option>").attr("value", option.value).text(option.text).appendTo(fkTypeSelect);
    });
    fkTypeSelect.val(item.foreignKeyDependencyType || "");

    const fkDeleteField = this.appendField(detailsGrid, "Ao excluir");
    const fkDeleteSelect = $("<select class=\"program-builder-mini-select program-builder-field-fk-on-delete\"></select>").appendTo(fkDeleteField);
    [
      { value: "", text: "Padrao" },
      { value: "restrict", text: "Restrict" },
      { value: "cascade", text: "Cascade" },
      { value: "set_null", text: "Set null" },
      { value: "set_default", text: "Set default" },
      { value: "no_action", text: "No action" }
    ].forEach(function(option) {
      $("<option></option>").attr("value", option.value).text(option.text).appendTo(fkDeleteSelect);
    });
    fkDeleteSelect.val(item.foreignKeyOnDelete || "");

    const fkUpdateField = this.appendField(detailsGrid, "Ao atualizar");
    const fkUpdateSelect = $("<select class=\"program-builder-mini-select program-builder-field-fk-on-update\"></select>").appendTo(fkUpdateField);
    [
      { value: "", text: "Padrao" },
      { value: "restrict", text: "Restrict" },
      { value: "cascade", text: "Cascade" },
      { value: "set_null", text: "Set null" },
      { value: "set_default", text: "Set default" },
      { value: "no_action", text: "No action" }
    ].forEach(function(option) {
      $("<option></option>").attr("value", option.value).text(option.text).appendTo(fkUpdateSelect);
    });
    fkUpdateSelect.val(item.foreignKeyOnUpdate || "");

    const optionItemsField = this.appendField(detailsGrid, "Lista de opcoes");
    $("<textarea rows=\"3\" class=\"program-builder-mini-textarea program-builder-field-option-items\"></textarea>").appendTo(optionItemsField).val(this.stringifyOptionItems(item.optionItems || []));

    const virtualField = this.appendField(detailsGrid, "Virtual");
    $("<input type=\"checkbox\" class=\"program-builder-field-virtual\">").appendTo(virtualField).prop("checked", item.virtualField === true);

    const includeVersionField = this.appendField(detailsGrid, "Vai para historico");
    $("<input type=\"checkbox\" class=\"program-builder-field-include-version\">").appendTo(includeVersionField).prop("checked", item.includeInVersion !== false);

    const versionRefEntityField = this.appendField(detailsGrid, "Versao de entidade");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-version-ref-entity\">").appendTo(versionRefEntityField).val(item.versionRefEntityCode || "");

    const versionRefSourceField = this.appendField(detailsGrid, "Campo origem ID");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-version-ref-source\">").appendTo(versionRefSourceField).val(item.versionRefSourceIdField || "");

    const versionSnapshotField = this.appendField(detailsGrid, "Ler snapshot de");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-version-snapshot-field\">").appendTo(versionSnapshotField).val(item.versionSnapshotVersionField || "");

    const versionSnapshotPathField = this.appendField(detailsGrid, "Campo do snapshot");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-version-snapshot-path\">").appendTo(versionSnapshotPathField).val(item.versionSnapshotPath || "");

    const customCodeModeField = this.appendField(detailsGrid, "Modo da codificacao");
    const customCodeModeSelect = $("<select class=\"program-builder-mini-select program-builder-field-custom-code-mode\"></select>").appendTo(customCodeModeField);
    [
      { value: "pattern", text: "Padrao" },
      { value: "static_method", text: "Metodo estatico" }
    ].forEach(function(option) {
      $("<option></option>").attr("value", option.value).text(option.text).appendTo(customCodeModeSelect);
    });
    customCodeModeSelect.val(item.customCodeMode || "pattern");

    const customCodePrefixField = this.appendField(detailsGrid, "Prefixo");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-custom-code-prefix\">").appendTo(customCodePrefixField).val(item.customCodePrefix || "");

    const customCodePatternField = this.appendField(detailsGrid, "Padrao");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-custom-code-pattern\">").appendTo(customCodePatternField).val(item.customCodePattern || "{YYYY}{MM}{DD}-{SEQ:4}");

    const customCodeSequenceEnabledField = this.appendField(detailsGrid, "Usa sequencia");
    $("<input type=\"checkbox\" class=\"program-builder-field-custom-code-sequence-enabled\">").appendTo(customCodeSequenceEnabledField).prop("checked", item.customCodeSequenceEnabled !== false);

    const customCodeSequenceScopeField = this.appendField(detailsGrid, "Escopo da sequencia");
    const customCodeSequenceScopeSelect = $("<select class=\"program-builder-mini-select program-builder-field-custom-code-sequence-scope\"></select>").appendTo(customCodeSequenceScopeField);
    [
      { value: "global", text: "Global" },
      { value: "year", text: "Ano" },
      { value: "month", text: "Mes" },
      { value: "day", text: "Dia" }
    ].forEach(function(option) {
      $("<option></option>").attr("value", option.value).text(option.text).appendTo(customCodeSequenceScopeSelect);
    });
    customCodeSequenceScopeSelect.val(item.customCodeSequenceScope || "global");

    const customCodeSequencePaddingField = this.appendField(detailsGrid, "Padding da sequencia");
    $("<input type=\"number\" min=\"1\" class=\"program-builder-mini-input program-builder-field-custom-code-sequence-padding\">").appendTo(customCodeSequencePaddingField).val(item.customCodeSequencePadding || 4);

    const customCodeStaticClassField = this.appendField(detailsGrid, "Classe estatica");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-custom-code-static-class\">").appendTo(customCodeStaticClassField).val(item.customCodeStaticClass || "");

    const customCodeStaticMethodField = this.appendField(detailsGrid, "Metodo estatico");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-custom-code-static-method\">").appendTo(customCodeStaticMethodField).val(item.customCodeStaticMethod || "");

    const customCodeAssistantScreenField = this.appendField(detailsGrid, "Screen ID do assistente");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-custom-code-assistant-screen\">").appendTo(customCodeAssistantScreenField).val(item.customCodeAssistantScreenId || "");

    const customCodePromptTitleField = this.appendField(detailsGrid, "Titulo do assistente");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-custom-code-prompt-title\">").appendTo(customCodePromptTitleField).val(item.customCodePromptTitle || "");

    const customCodePromptFieldsField = this.appendField(detailsGrid, "Campos do assistente");
    $("<textarea rows=\"4\" class=\"program-builder-mini-textarea program-builder-field-custom-code-prompt-fields\"></textarea>").appendTo(customCodePromptFieldsField).val(this.stringifyCustomCodePromptFields(item.customCodePromptFields || []));

    const optionsField = this.appendField(detailsGrid, "Opcoes extras JSON");
    $("<textarea rows=\"3\" class=\"program-builder-mini-textarea program-builder-field-options\"></textarea>").appendTo(optionsField).val(this.stringifyExtraOptions(item.options || {}));

    row.find(".program-builder-remove-row").kendoButton({
      icon: "trash",
      click: this.handleRemoveFieldRow.bind(this, row, detailsRow)
    });
    detailsRow.find(".program-builder-field-unique").kendoCheckBox();
    detailsRow.find(".program-builder-field-readonly").kendoCheckBox();
    detailsRow.find(".program-builder-field-virtual").kendoCheckBox();
    detailsRow.find(".program-builder-field-include-version").kendoCheckBox();
    detailsRow.find(".program-builder-field-custom-code-sequence-enabled").kendoCheckBox();

    row.find(".program-builder-field-code").on("input", function() {
      const code = String($(this).val() || "").trim().toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^_+|_+$/g, "");
      const columnInput = row.find(".program-builder-field-column");
      if (!String(columnInput.val() || "").trim()) {
        columnInput.val(code);
      }
    });

    typeSelect.on("change", this.syncFieldRowState.bind(this, row, detailsRow));
    row.find(".program-builder-field-pk").on("change", this.syncFieldRowState.bind(this, row, detailsRow));
    detailsRow.find(".program-builder-field-virtual").on("change", this.syncFieldRowState.bind(this, row, detailsRow));
    detailsRow.find(".program-builder-field-custom-code-mode").on("change", this.syncFieldRowState.bind(this, row, detailsRow));
    detailsRow.find(".program-builder-field-custom-code-sequence-enabled").on("change", this.syncFieldRowState.bind(this, row, detailsRow));
    this.syncFieldRowState(row, detailsRow);
  };

  ProgramBuilder.prototype.handleAddFieldRow = function() {
    this.addFieldRow({
      code: "",
      label: "",
      dataType: "string",
      columnName: "",
      length: 160,
      precision: null,
      scale: null,
      defaultValue: "",
      unique: false,
      readonlyField: false,
      foreignKeyTable: "",
      foreignKeyColumn: "",
      foreignKeyDependencyType: "",
      foreignKeyOnDelete: "",
      foreignKeyOnUpdate: "",
      optionItems: [],
      virtualField: false,
      includeInVersion: true,
      versionRefEntityCode: "",
      versionRefSourceIdField: "",
      versionSnapshotVersionField: "",
      versionSnapshotPath: "",
      customCodeMode: "pattern",
      customCodePrefix: "",
      customCodePattern: "{YYYY}{MM}{DD}-{SEQ:4}",
      customCodeSequenceEnabled: true,
      customCodeSequenceScope: "global",
      customCodeSequencePadding: 4,
      customCodeStaticClass: "",
      customCodeStaticMethod: "",
      customCodeAssistantScreenId: "",
      customCodePromptTitle: "",
      customCodePromptFields: [],
      required: false,
      primaryKey: false,
      options: {}
    });
  };

  ProgramBuilder.prototype.handleSuggestFieldNames = function() {
    this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).each(function(_, row) {
      const $row = $(row);
      const $details = $row.next(".program-builder-field-details-row");
      const label = String($row.find(".program-builder-field-label").val() || "");
      const type = String($row.find(".program-builder-field-type").val() || "string");
      const primaryKey = $row.find(".program-builder-field-pk").is(":checked");
      const unique = $details.find(".program-builder-field-unique").is(":checked");
      const foreignKeyTable = String($details.find(".program-builder-field-fk-table").val() || "");
      const columnInput = $row.find(".program-builder-field-column");
      const codeInput = $row.find(".program-builder-field-code");
      const currentCode = String(codeInput.val() || "").trim();
      const currentColumn = String(columnInput.val() || "").trim();
      const suggestion = this.suggestFieldCode({
        label: label,
        dataType: type,
        primaryKey: primaryKey,
        unique: unique,
        foreignKeyTable: foreignKeyTable
      });
      if (!currentCode) {
        codeInput.val(suggestion);
      }
      if (!currentColumn) {
        columnInput.val(suggestion);
      }
    }.bind(this));

    global.CrudUtils.showMessage("Sugestoes de nomes aplicadas nos campos vazios.", "info");
  };

  ProgramBuilder.prototype.handleRemoveFieldRow = function(row, detailsRow) {
    row.remove();
    detailsRow.remove();
    if (!this.fieldsTableBody.children().length) {
      this.addFieldRow(this.defaultFieldRow());
    }
    this.refreshHistoricalAssistantSourceFields();
  };

  ProgramBuilder.prototype.defaultFieldRow = function() {
    return {
      code: "id",
      label: "ID",
      dataType: "integer",
      columnName: "id",
      length: null,
      precision: null,
      scale: null,
      defaultValue: "",
      unique: false,
      readonlyField: false,
      foreignKeyTable: "",
      foreignKeyColumn: "",
      foreignKeyDependencyType: "",
      foreignKeyOnDelete: "",
      foreignKeyOnUpdate: "",
      optionItems: [],
      virtualField: false,
      includeInVersion: true,
      versionRefEntityCode: "",
      versionRefSourceIdField: "",
      versionSnapshotVersionField: "",
      versionSnapshotPath: "",
      customCodeMode: "pattern",
      customCodePrefix: "",
      customCodePattern: "{YYYY}{MM}{DD}-{SEQ:4}",
      customCodeSequenceEnabled: true,
      customCodeSequenceScope: "global",
      customCodeSequencePadding: 4,
      customCodeStaticClass: "",
      customCodeStaticMethod: "",
      customCodeAssistantScreenId: "",
      customCodePromptTitle: "",
      customCodePromptFields: [],
      required: true,
      primaryKey: true,
      options: {}
    };
  };

  ProgramBuilder.prototype.defaultNameFieldRow = function() {
    return {
      code: "nome",
      label: "Nome",
      dataType: "string",
      columnName: "nome",
      length: 160,
      precision: null,
      scale: null,
      defaultValue: "",
      unique: false,
      foreignKeyTable: "",
      foreignKeyColumn: "",
      optionItems: [],
      virtualField: false,
      includeInVersion: true,
      versionRefEntityCode: "",
      versionRefSourceIdField: "",
      versionSnapshotVersionField: "",
      versionSnapshotPath: "",
      customCodeMode: "pattern",
      customCodePrefix: "",
      customCodePattern: "{YYYY}{MM}{DD}-{SEQ:4}",
      customCodeSequenceEnabled: true,
      customCodeSequenceScope: "global",
      customCodeSequencePadding: 4,
      customCodeStaticClass: "",
      customCodeStaticMethod: "",
      customCodeAssistantScreenId: "",
      customCodePromptTitle: "",
      customCodePromptFields: [],
      required: true,
      primaryKey: false,
      options: {}
    };
  };

  ProgramBuilder.prototype.syncSituationFieldState = function() {
    const enabled = this.entitySituationEnabledInput.is(":checked") && this.entityTypeSelect.value() === "persistence";
    this.entitySituationFieldInput.enable(enabled);
  };

  ProgramBuilder.prototype.syncEntityTypeState = function() {
    const entityType = this.entityTypeSelect.value() || "persistence";
    const persistence = entityType === "persistence";
    this.entityTableNameInput.enable(persistence);
    this.entityCreateTableInput.prop("checked", persistence ? this.entityCreateTableInput.is(":checked") : false);
    this.entityCreateTableInput.prop("disabled", !persistence);
    this.entityAllowTableRenameInput.prop("disabled", !persistence);
    this.entityAllowColumnRenameInput.prop("disabled", !persistence);
    this.entityDropRemovedColumnsInput.prop("disabled", !persistence);
    this.entitySituationEnabledInput.prop("disabled", !persistence);
    this.entityVersioningEnabledInput.prop("disabled", !persistence);
    this.entityVersioningDeduplicateInput.prop("disabled", !persistence || !this.entityVersioningEnabledInput.is(":checked"));
    if (!persistence) {
      this.entitySituationEnabledInput.prop("checked", false);
      this.entityVersioningEnabledInput.prop("checked", false);
      this.entityAllowTableRenameInput.prop("checked", false);
      this.entityAllowColumnRenameInput.prop("checked", false);
      this.entityDropRemovedColumnsInput.prop("checked", false);
      this.entityTypeHint.text("Tipos query e io ja podem ser cadastrados, mas a criacao fisica e a geracao CRUD continuam focadas em persistence.");
      if (entityType === "query") {
        this.entityStructureTypeSelect.value("view");
      }
    } else {
      this.entityTypeHint.text("Fluxo completo atual: tipo persistence com tabela fisica e programa CRUD.");
    }
    this.syncStructureState();
    this.syncSituationFieldState();
  };

  ProgramBuilder.prototype.syncStructureState = function() {
    if (!this.entityStructureTypeSelect) {
      return;
    }
    const entityType = this.entityTypeSelect.value() || "persistence";
    let structureType = this.entityStructureTypeSelect.value() || "main";
    if (entityType === "query") {
      structureType = "view";
      this.entityStructureTypeSelect.value("view");
      this.entityStructureTypeSelect.enable(false);
    } else {
      this.entityStructureTypeSelect.enable(true);
    }

    const needsModule = structureType === "main" || structureType === "view";
    const needsSequence = structureType === "composition" || structureType === "specific_relation";
    const needsParent = structureType === "composition" || structureType === "specific_relation" || structureType === "recursive" || structureType === "multi_level";
    const needsLeftRight = structureType === "aggregation";

    this.entityStructureModuleSelect.enable(needsModule);
    this.entityStructureBaseNumberInput.enable(needsModule);
    this.entityStructureSequenceNumberInput.enable(needsSequence);
    this.entityStructureParentSelect.enable(needsParent);
    this.entityStructureLeftSelect.enable(needsLeftRight);
    this.entityStructureRightSelect.enable(needsLeftRight);

    if (!needsModule) {
      this.entityStructureModuleSelect.value("");
      this.entityStructureBaseNumberInput.value("");
    }
    if (!needsSequence) {
      this.entityStructureSequenceNumberInput.value("");
    }
    if (!needsParent) {
      this.entityStructureParentSelect.value("");
    }
    if (!needsLeftRight) {
      this.entityStructureLeftSelect.value("");
      this.entityStructureRightSelect.value("");
    }

    const suggestion = this.computeSuggestedTableName();
    this.structureHintElement.text(suggestion
      ? "Nome sugerido pelo padrao estrutural: " + suggestion + "."
      : "Complete os dados estruturais para sugerir o nome fisico da tabela.");
  };

  ProgramBuilder.prototype.computeSuggestedTableName = function() {
    const type = String(this.entityStructureTypeSelect.value() || "");
    const baseNumber = parseInt(this.entityStructureBaseNumberInput.value(), 10);
    const sequenceNumber = parseInt(this.entityStructureSequenceNumberInput.value(), 10);
    const parent = this.findEntityByCode(this.entityStructureParentSelect.value());
    const left = this.findEntityByCode(this.entityStructureLeftSelect.value());
    const right = this.findEntityByCode(this.entityStructureRightSelect.value());

    if (type === "main" && baseNumber > 0) {
      return "t" + baseNumber;
    }
    if (type === "view" && baseNumber > 0) {
      return "v" + baseNumber;
    }
    if (type === "composition" && parent && parent.tableName && sequenceNumber > 0) {
      return parent.tableName + "c" + sequenceNumber;
    }
    if (type === "specific_relation" && parent && parent.tableName && sequenceNumber > 0) {
      return parent.tableName + "e" + sequenceNumber;
    }
    if (type === "recursive" && parent && parent.tableName) {
      return parent.tableName + "r";
    }
    if (type === "multi_level" && parent && parent.tableName) {
      return parent.tableName + "m";
    }
    if (type === "aggregation" && left && right && left.tableName && right.tableName) {
      return left.tableName + "a" + right.tableName;
    }
    return "";
  };

  ProgramBuilder.prototype.handleSuggestTableName = function() {
    const tableName = this.computeSuggestedTableName();
    if (!tableName) {
      global.CrudUtils.showMessage("Complete os dados estruturais para sugerir o nome da tabela.", "warning");
      return;
    }
    this.entityTableNameInput.value(tableName);
    this.structureHintElement.text("Nome sugerido pelo padrao estrutural: " + tableName + ".");
    global.CrudUtils.showMessage("Nome da tabela sugerido a partir da estrutura.", "success");
  };

  ProgramBuilder.prototype.findEntityByCode = function(code) {
    const entityCode = String(code || "");
    return this.state.entities.find(function(item) {
      return item.code === entityCode;
    }) || null;
  };

  ProgramBuilder.prototype.collectEntityPayload = function() {
    const fields = [];
    this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).each(function(index, row) {
      const $row = $(row);
      const $details = $row.next(".program-builder-field-details-row");
      fields.push({
        id: Number($row.attr("data-field-id") || 0),
        code: $row.find(".program-builder-field-code").val(),
        label: $row.find(".program-builder-field-label").val(),
        dataType: $row.find(".program-builder-field-type").val(),
        columnName: $row.find(".program-builder-field-column").val(),
        originalCode: $row.attr("data-original-code") || "",
        originalColumnName: $row.attr("data-original-column") || "",
        length: $row.find(".program-builder-field-length").val(),
        precision: $details.find(".program-builder-field-precision").val(),
        scale: $details.find(".program-builder-field-scale").val(),
        defaultValue: $details.find(".program-builder-field-default").val(),
        unique: $details.find(".program-builder-field-unique").is(":checked"),
        readonlyField: $details.find(".program-builder-field-readonly").is(":checked"),
        foreignKeyTable: $details.find(".program-builder-field-fk-table").val(),
        foreignKeyColumn: $details.find(".program-builder-field-fk-column").val(),
        foreignKeyDependencyType: $details.find(".program-builder-field-fk-type").val(),
        foreignKeyOnDelete: $details.find(".program-builder-field-fk-on-delete").val(),
        foreignKeyOnUpdate: $details.find(".program-builder-field-fk-on-update").val(),
        optionItems: this.parseOptionItems($details.find(".program-builder-field-option-items").val(), index),
        virtualField: $details.find(".program-builder-field-virtual").is(":checked"),
        includeInVersion: $details.find(".program-builder-field-include-version").is(":checked"),
        versionRefEntityCode: $details.find(".program-builder-field-version-ref-entity").val(),
        versionRefSourceIdField: $details.find(".program-builder-field-version-ref-source").val(),
        versionSnapshotVersionField: $details.find(".program-builder-field-version-snapshot-field").val(),
        versionSnapshotPath: $details.find(".program-builder-field-version-snapshot-path").val(),
        customCodeMode: $details.find(".program-builder-field-custom-code-mode").val(),
        customCodePrefix: $details.find(".program-builder-field-custom-code-prefix").val(),
        customCodePattern: $details.find(".program-builder-field-custom-code-pattern").val(),
        customCodeSequenceEnabled: $details.find(".program-builder-field-custom-code-sequence-enabled").is(":checked"),
        customCodeSequenceScope: $details.find(".program-builder-field-custom-code-sequence-scope").val(),
        customCodeSequencePadding: $details.find(".program-builder-field-custom-code-sequence-padding").val(),
        customCodeStaticClass: $details.find(".program-builder-field-custom-code-static-class").val(),
        customCodeStaticMethod: $details.find(".program-builder-field-custom-code-static-method").val(),
        customCodeAssistantScreenId: $details.find(".program-builder-field-custom-code-assistant-screen").val(),
        customCodePromptTitle: $details.find(".program-builder-field-custom-code-prompt-title").val(),
        customCodePromptFields: this.parseCustomCodePromptFields($details.find(".program-builder-field-custom-code-prompt-fields").val(), index),
        required: $row.find(".program-builder-field-required").is(":checked"),
        primaryKey: $row.find(".program-builder-field-pk").is(":checked"),
        options: this.parseOptions($details.find(".program-builder-field-options").val(), index)
      });
    }.bind(this));

    const uniqueKeys = [];
    this.uniqueKeysTableBody.find("tr").each(function(_, row) {
      const $row = $(row);
      const name = String($row.find(".program-builder-unique-key-name").val() || "").trim();
      const fieldTexts = String($row.find(".program-builder-unique-key-fields").val() || "").trim();
      const keyFields = fieldTexts ? fieldTexts.split(",").map(function(item) {
        return String(item || "").trim();
      }).filter(Boolean) : [];
      if (!name && !keyFields.length) {
        return;
      }
      uniqueKeys.push({
        name: name,
        fields: keyFields
      });
    });

    const rules = [];
    this.rulesTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-rule-details-row");
    }).each(function(index, row) {
      const $row = $(row);
      const $details = $row.next(".program-builder-rule-details-row");
      rules.push({
        id: $details.find(".program-builder-rule-id").val(),
        label: $row.find(".program-builder-rule-label").val(),
        order: $row.find(".program-builder-rule-order").val(),
        phase: $row.find(".program-builder-rule-phase").val(),
        ruleType: $row.find(".program-builder-rule-type").val(),
        enabled: $row.find(".program-builder-rule-enabled").is(":checked"),
        continueOnError: $row.find(".program-builder-rule-continue").is(":checked"),
        field: $details.find(".program-builder-rule-field").val(),
        whenField: $details.find(".program-builder-rule-when-field").val(),
        whenEquals: $details.find(".program-builder-rule-when-equals").val(),
        message: $details.find(".program-builder-rule-message").val(),
        className: $details.find(".program-builder-rule-class-name").val(),
        methodName: $details.find(".program-builder-rule-method-name").val(),
        params: this.parseRuleParams($details.find(".program-builder-rule-params").val(), index)
      });
    }.bind(this));

    return {
      code: this.entityCodeInput.value(),
      name: this.entityNameInput.value(),
      entityType: this.entityTypeSelect.value(),
      tableName: this.entityTableNameInput.value(),
      originalTableName: this.state.originalEntityTableName || "",
      structureModuleCode: this.entityStructureModuleSelect.value(),
      structureType: this.entityStructureTypeSelect.value(),
      structureBaseNumber: this.entityStructureBaseNumberInput.value(),
      structureSequenceNumber: this.entityStructureSequenceNumberInput.value(),
      structureParentEntityCode: this.entityStructureParentSelect.value(),
      structureLeftEntityCode: this.entityStructureLeftSelect.value(),
      structureRightEntityCode: this.entityStructureRightSelect.value(),
      createPhysicalTable: this.entityCreateTableInput.is(":checked"),
      allowTableRename: this.entityAllowTableRenameInput.is(":checked"),
      allowColumnRename: this.entityAllowColumnRenameInput.is(":checked"),
      dropRemovedColumns: this.entityDropRemovedColumnsInput.is(":checked"),
      situationEnabled: this.entitySituationEnabledInput.is(":checked"),
      situationFieldCode: this.entitySituationFieldInput.value(),
      uniqueKeys: uniqueKeys,
      rules: rules,
      versioningEnabled: this.entityVersioningEnabledInput.is(":checked"),
      versioningDeduplicate: this.entityVersioningDeduplicateInput.is(":checked"),
      fields: fields
    };
  };

  ProgramBuilder.prototype.handleSaveEntity = function() {
    let payload;
    try {
      payload = this.collectEntityPayload();
    } catch (error) {
      this.handleError(error, "Opcoes dos campos estao invalidas.");
      return;
    }

    this.http.request({
      url: "/api/admin/program-builder/entities",
      method: "POST",
      data: payload
    }).then((response) => {
      const entity = response.entity || {};
      this.state.entityVersions = response.versions || [];
      this.state.currentEntityVersion = response.version || this.state.entityVersions[0] || null;
      global.CrudUtils.showMessage("Entidade salva.", "success");
      this.refreshBootstrap().then(() => {
        this.entitySelectorInput.value(entity.code || "");
        this.populateEntityForm(entity, this.state.entityVersions);
        this.builderEntitySelect.value(entity.code || "");
        this.handleProgramEntityChange(true);
      });
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel salvar a entidade.");
    });
  };

  ProgramBuilder.prototype.handleNewEntity = function() {
    this.resetEntityForm();
    this.bannerElement.text("Nova entidade. Defina os campos e salve para criar os metadados e a tabela fisica.");
  };

  ProgramBuilder.prototype.refreshHistoricalAssistantOptions = function() {
    if (!this.historyEntitySelect) {
      return;
    }
    const items = this.state.entities.filter(function(item) {
      return item.entityType === "persistence";
    }).map(function(item) {
      return { code: item.code, name: item.code + " - " + item.name };
    });
    this.historyEntitySelect.setDataSource(new kendo.data.DataSource({ data: items }));
  };

  ProgramBuilder.prototype.refreshHistoricalAssistantSourceFields = function() {
    if (!this.historySourceFieldSelect) {
      return;
    }
    const items = this.collectCurrentFieldRows().filter(function(item) {
      return item.virtualField !== true;
    }).map(function(item) {
      return { code: item.code, label: item.code + " - " + item.label };
    });
    this.historySourceFieldSelect.setDataSource(new kendo.data.DataSource({ data: items }));
  };

  ProgramBuilder.prototype.handleHistoricalEntityChange = function() {
    const code = String(this.historyEntitySelect.value() || "");
    this.state.historySourceEntity = null;
    this.historyFieldsList.empty();
    if (!code) {
      return;
    }

    this.http.request({
      url: "/api/admin/program-builder/entities/" + encodeURIComponent(code),
      method: "GET"
    }).then((payload) => {
      const entity = payload.entity || null;
      this.state.historySourceEntity = entity;
      if (!entity || entity.versioningEnabled !== true) {
        global.CrudUtils.showMessage("A entidade escolhida nao esta marcada como cadastro versionado.", "warning");
      }
      this.renderHistoricalFieldChoices(entity);
      const currentSourceField = String(this.historySourceFieldSelect.value() || "");
      if (currentSourceField && !String(this.historyAliasInput.value() || "").trim()) {
        this.historyAliasInput.value(this.normalizeHistoryAlias(currentSourceField));
      }
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel carregar a entidade mestre.");
    });
  };

  ProgramBuilder.prototype.renderHistoricalFieldChoices = function(entity) {
    this.historyFieldsList.empty();
    const fields = entity && Array.isArray(entity.fields) ? entity.fields : [];
    const usableFields = fields.filter(function(field) {
      return field.virtualField !== true && field.primaryKey !== true;
    });
    if (!usableFields.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum campo disponivel para historico nesta entidade.").appendTo(this.historyFieldsList);
      return;
    }
    usableFields.forEach(function(field) {
      const label = $("<label class=\"program-builder-history-option\"></label>").appendTo(this.historyFieldsList);
      $("<input type=\"checkbox\" class=\"program-builder-history-field\">").appendTo(label)
        .attr("data-field-code", field.code)
        .prop("checked", ["nome", "descricao", "endereco"].indexOf(field.code) >= 0);
      $("<span></span>").text(field.label + " (" + field.code + ")").appendTo(label);
    }, this);
  };

  ProgramBuilder.prototype.handleApplyHistoricalAssistant = function() {
    const sourceEntity = this.state.historySourceEntity;
    const sourceField = String(this.historySourceFieldSelect.value() || "");
    if (!sourceEntity || !sourceEntity.code) {
      global.CrudUtils.showMessage("Selecione um cadastro mestre versionado.", "warning");
      return;
    }
    if (!sourceField) {
      global.CrudUtils.showMessage("Selecione o campo origem ID da entidade transacional.", "warning");
      return;
    }
    const selectedCodes = this.historyFieldsList.find(".program-builder-history-field:checked").map(function() {
      return String($(this).attr("data-field-code") || "");
    }).get().filter(Boolean);
    if (!selectedCodes.length) {
      global.CrudUtils.showMessage("Escolha ao menos um campo historico para gerar.", "warning");
      return;
    }

    const alias = this.normalizeHistoryAlias(String(this.historyAliasInput.value() || "") || sourceField);
    const currentFields = this.collectCurrentFieldRows();
    const versionFieldCode = this.buildVersionFieldCode(sourceField, alias);
    let added = 0;

    if (!this.findFieldConfig(currentFields, versionFieldCode)) {
      this.addFieldRow({
        code: versionFieldCode,
        label: "Versao " + this.humanizeHistoryAlias(alias),
        dataType: "integer",
        columnName: versionFieldCode,
        length: null,
        precision: null,
        scale: null,
        defaultValue: "",
        unique: false,
        readonlyField: false,
        foreignKeyTable: "runtime_entity_record_version",
        foreignKeyColumn: "id",
        foreignKeyDependencyType: "reference",
        foreignKeyOnDelete: "",
        foreignKeyOnUpdate: "",
        optionItems: [],
        virtualField: false,
        includeInVersion: true,
        versionRefEntityCode: sourceEntity.code,
        versionRefSourceIdField: sourceField,
        versionSnapshotVersionField: "",
        versionSnapshotPath: "",
        required: false,
        primaryKey: false,
        options: {}
      });
      added += 1;
      currentFields.push({ code: versionFieldCode });
    }

    selectedCodes.forEach(function(fieldCode) {
      const sourceConfig = (sourceEntity.fields || []).find(function(item) {
        return item.code === fieldCode;
      });
      if (!sourceConfig) {
        return;
      }
      const virtualCode = alias + "_" + fieldCode + "_historico";
      if (this.findFieldConfig(currentFields, virtualCode)) {
        return;
      }
      this.addFieldRow({
        code: virtualCode,
        label: sourceConfig.label + " historico",
        dataType: sourceConfig.dataType || "string",
        columnName: virtualCode,
        length: sourceConfig.length || 160,
        precision: sourceConfig.precision || null,
        scale: sourceConfig.scale || null,
        defaultValue: "",
        unique: false,
        readonlyField: true,
        foreignKeyTable: "",
        foreignKeyColumn: "",
        foreignKeyDependencyType: "",
        foreignKeyOnDelete: "",
        foreignKeyOnUpdate: "",
        optionItems: sourceConfig.optionItems || [],
        virtualField: true,
        includeInVersion: false,
        versionRefEntityCode: "",
        versionRefSourceIdField: "",
        versionSnapshotVersionField: versionFieldCode,
        versionSnapshotPath: fieldCode,
        required: false,
        primaryKey: false,
        options: {}
      });
      added += 1;
      currentFields.push({ code: virtualCode });
    }, this);

    this.refreshHistoricalAssistantSourceFields();
    if (!added) {
      global.CrudUtils.showMessage("Os campos historicos escolhidos ja existem na modelagem.", "info");
      return;
    }
    global.CrudUtils.showMessage("Assistente aplicou " + added + " campo(s) historicos.", "success");
  };

  ProgramBuilder.prototype.collectCurrentFieldRows = function() {
    const fields = [];
    this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).each(function(_, row) {
      const $row = $(row);
      const $details = $row.next(".program-builder-field-details-row");
      fields.push({
        code: String($row.find(".program-builder-field-code").val() || ""),
        label: String($row.find(".program-builder-field-label").val() || ""),
        virtualField: $details.find(".program-builder-field-virtual").is(":checked")
      });
    });
    return fields;
  };

  ProgramBuilder.prototype.findFieldConfig = function(fields, code) {
    return (fields || []).find(function(item) {
      return item.code === code;
    }) || null;
  };

  ProgramBuilder.prototype.buildVersionFieldCode = function(sourceField, alias) {
    if (/_id$/i.test(sourceField)) {
      return sourceField.replace(/_id$/i, "_version_id");
    }
    return alias + "_version_id";
  };

  ProgramBuilder.prototype.normalizeHistoryAlias = function(value) {
    const text = String(value || "").trim().toLowerCase();
    const normalized = text.replace(/[^a-z0-9_]+/g, "_").replace(/^_+|_+$/g, "");
    if (/_id$/i.test(normalized)) {
      return normalized.replace(/_id$/i, "");
    }
    return normalized || "referencia";
  };

  ProgramBuilder.prototype.humanizeHistoryAlias = function(alias) {
    return String(alias || "").replace(/_/g, " ").replace(/\b\w/g, function(letter) {
      return letter.toUpperCase();
    });
  };

  ProgramBuilder.prototype.handleRestoreEntityVersion = function() {
    const current = this.state.currentEntityVersion;
    if (!current || !current.id) {
      global.CrudUtils.showMessage("Selecione uma revisao da entidade para restaurar.", "warning");
      return;
    }

    global.CrudUtils.confirm("O rollback vai restaurar a modelagem e sincronizar a estrutura fisica da entidade para a revisao escolhida.", {
      title: "Restaurar entidade",
      confirmText: "Restaurar"
    }).then((confirmed) => {
      if (!confirmed) {
        return;
      }
      this.http.request({
        url: "/api/admin/program-builder/entity-versions/" + encodeURIComponent(current.id) + "/restore",
        method: "POST",
        data: {}
      }).then((response) => {
        const entity = response.entity || {};
        this.state.entityVersions = response.versions || [];
        this.state.currentEntityVersion = response.version || this.state.entityVersions[0] || null;
        global.CrudUtils.showMessage("Entidade restaurada.", "success");
        this.refreshBootstrap().then(() => {
          this.entitySelectorInput.value(entity.code || "");
          this.populateEntityForm(entity, this.state.entityVersions);
          if (entity.code) {
            this.builderEntitySelect.value(entity.code);
            this.handleProgramEntityChange(true);
          }
        });
      }).catch((error) => {
        this.handleError(error, "Nao foi possivel restaurar a entidade.");
      });
    });
  };

  ProgramBuilder.prototype.handleProgramEntityChange = function(prefillOnlyWhenEmpty) {
    const entityCode = String(this.builderEntitySelect.value() || "");
    if (!entityCode) {
      this.schedulePreview();
      return;
    }

    const entity = this.findEntitySummary(entityCode);
    if (entity && entity.entityType && entity.entityType !== "persistence") {
      this.previewFooter.text("Nesta etapa, a geracao de programa CRUD continua suportada apenas para entidades persistence.");
    }
    const allowOverwrite = prefillOnlyWhenEmpty !== false;
    if (allowOverwrite) {
      if (!String(this.programTitleInput.value() || "").trim() && entity) {
        this.programTitleInput.value(entity.name || entityCode);
      }
      if (!String(this.screenIdInput.value() || "").trim()) {
        this.screenIdInput.value("cadastros." + entityCode);
      }
      if (!String(this.permissionPrefixInput.value() || "").trim()) {
        this.permissionPrefixInput.value("cadastros." + entityCode);
      }
    }
    this.schedulePreview();
  };

  ProgramBuilder.prototype.handleProgramModuleChange = function() {
    this.schedulePreview();
  };

  ProgramBuilder.prototype.findEntitySummary = function(entityCode) {
    return this.state.entities.find(function(item) {
      return item.code === entityCode;
    }) || null;
  };

  ProgramBuilder.prototype.findModuleSummary = function(moduleCode) {
    const code = String(moduleCode || "");
    return this.state.modules.find(function(item) {
      return item.code === code;
    }) || null;
  };

  ProgramBuilder.prototype.handleProgramSelection = function() {
    const code = String(this.programSelectorInput.value() || "");
    if (!code) {
      this.handleNewDraft();
      return;
    }

    this.http.request({
      url: "/api/admin/program-builder/programs/" + encodeURIComponent(code),
      method: "GET"
    }).then((payload) => {
      this.state.currentProgramCode = code;
      this.state.versions = payload.versions || [];
      this.versionsGrid.dataSource.data(this.state.versions);
      const preferred = this.state.versions.find(function(item) {
        return item.status === "draft";
      }) || this.state.versions[0] || null;
      if (preferred) {
        this.populateProgramForm(preferred);
      } else {
        this.resetProgramForm();
      }
      this.syncToolbarState();
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel carregar o programa.");
    });
  };

  ProgramBuilder.prototype.populateProgramForm = function(item) {
    const version = item || {};
    this.state.currentVersion = version;
    this.state.currentProgramCode = version.programCode || "";
    this.programCodeInput.value(version.programCode || "");
    this.programTitleInput.value(version.programTitle || "");
    this.moduleInput.value(version.module || "");
    this.screenIdInput.value(version.screenId || "");
    this.versionInput.value(version.version || "1.0.0");
    this.subtitleInput.value(version.subtitle || "");
    this.iconInput.value(version.icon || "file");
    this.permissionPrefixInput.value(version.permissionPrefix || "");
    this.builderEntitySelect.value(version.builderEntityCode || "");
    this.allowCreateInput.prop("checked", version.allowCreate !== false);
    this.allowUpdateInput.prop("checked", version.allowUpdate !== false);
    this.allowDeleteInput.prop("checked", version.allowDelete === true);
    this.changeSummaryTextArea.value(version.changeSummary || "");
    this.renderDefinition(version.generatedDefinition || {});
    this.updatePreviewMeta(version);
    this.updateBanner();
    this.syncSelectedVersionRow();
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.resetProgramForm = function() {
    this.state.currentVersion = null;
    this.state.currentProgramCode = "";
    this.programSelectorInput.value("");
    this.programCodeInput.value("");
    this.programTitleInput.value("");
    this.moduleInput.value(this.state.modules[0] ? this.state.modules[0].code : "");
    this.screenIdInput.value("");
    this.versionInput.value("1.0.0");
    this.subtitleInput.value("");
    this.iconInput.value("file");
    this.permissionPrefixInput.value("");
    this.builderEntitySelect.value(this.state.currentEntityCode || "");
    this.allowCreateInput.prop("checked", true);
    this.allowUpdateInput.prop("checked", true);
    this.allowDeleteInput.prop("checked", false);
    this.changeSummaryTextArea.value("");
    this.state.preview = null;
    this.renderDefinition({});
    this.updatePreviewMeta(null);
    this.previewFooter.text("");
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.resetEntityForm = function() {
    this.state.historySourceEntity = null;
    this.state.currentEntityCode = "";
    this.state.originalEntityTableName = "";
    this.state.entityVersions = [];
    this.state.currentEntityVersion = null;
    this.entitySelectorInput.value("");
    this.entityCodeInput.value("");
    this.entityNameInput.value("");
    this.entityTableNameInput.value("");
    this.entityTypeSelect.value("persistence");
    this.entityStructureModuleSelect.value("");
    this.entityStructureTypeSelect.value("main");
    this.entityStructureBaseNumberInput.value("");
    this.entityStructureSequenceNumberInput.value("");
    this.entityStructureParentSelect.value("");
    this.entityStructureLeftSelect.value("");
    this.entityStructureRightSelect.value("");
    this.entitySituationFieldInput.value("status");
    this.entityCreateTableInput.prop("checked", true);
    this.entityAllowTableRenameInput.prop("checked", true);
    this.entityAllowColumnRenameInput.prop("checked", true);
    this.entityDropRemovedColumnsInput.prop("checked", false);
    this.entitySituationEnabledInput.prop("checked", false);
    this.entityVersioningEnabledInput.prop("checked", false);
    this.entityVersioningDeduplicateInput.prop("checked", true);
    this.renderFieldRows([]);
    this.renderUniqueKeyRows([]);
    this.renderRuleRows([]);
    this.historyEntitySelect.value("");
    this.historySourceFieldSelect.value("");
    this.historyAliasInput.value("");
    this.historyFieldsList.empty();
    this.entityVersionsGrid.dataSource.data([]);
    this.syncEntityTypeState();
    this.syncStructureState();
    this.syncSituationFieldState();
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.collectProgramPayload = function() {
    const current = this.state.currentVersion || {};
    const editableCurrent = current.status === "draft";
    return {
      id: editableCurrent ? (current.id || null) : null,
      programCode: this.programCodeInput.value(),
      programTitle: this.programTitleInput.value(),
      module: String(this.moduleInput.value() || ""),
      screenId: this.screenIdInput.value(),
      pageType: this.pageTypeSelect.value(),
      builderEntityCode: this.builderEntitySelect.value(),
      version: this.versionInput.value(),
      subtitle: this.subtitleInput.value(),
      icon: this.iconInput.value(),
      permissionPrefix: this.permissionPrefixInput.value(),
      allowCreate: this.allowCreateInput.is(":checked"),
      allowUpdate: this.allowUpdateInput.is(":checked"),
      allowDelete: this.allowDeleteInput.is(":checked"),
      changeSummary: this.changeSummaryTextArea.value() || ""
    };
  };

  ProgramBuilder.prototype.handleNewDraft = function() {
    this.state.currentVersion = null;
    this.state.currentProgramCode = "";
    this.state.versions = [];
    this.versionsGrid.dataSource.data([]);
    this.resetProgramForm();
    this.builderEntitySelect.value(this.state.currentEntityCode || "");
    this.handleProgramEntityChange(true);
    this.bannerElement.text("Novo rascunho. Escolha uma entidade modelada, gere o preview e publique quando estiver pronto.");
  };

  ProgramBuilder.prototype.schedulePreview = function() {
    const self = this;
    global.clearTimeout(this.previewTimer);
    this.previewTimer = global.setTimeout(function() {
      self.requestPreview(false);
    }, 250);
  };

  ProgramBuilder.prototype.handlePreview = function() {
    return this.requestPreview(true);
  };

  ProgramBuilder.prototype.requestPreview = function(notifyOnSuccess) {
    const payload = this.collectProgramPayload();
    if (!payload.programCode || !payload.programTitle || !payload.builderEntityCode || !payload.screenId || !payload.version) {
      this.renderLocalSummary(payload);
      return Promise.resolve(null);
    }

    return this.http.request({
      url: "/api/admin/program-builder/preview",
      method: "POST",
      data: payload
    }).then((response) => {
      this.state.preview = response;
      this.renderDefinition(response.generatedDefinition || {});
      this.updatePreviewMeta({
        status: this.state.currentVersion && this.state.currentVersion.status || "draft",
        version: payload.version,
        builderEntityCode: payload.builderEntityCode,
        screenId: payload.screenId
      });
      this.previewFooter.text("Preview gerado pelo backend a partir dos metadados atuais da entidade.");
      if (notifyOnSuccess) {
        global.CrudUtils.showMessage("Preview atualizado.", "info");
      }
      return response;
    }).catch((error) => {
      if (notifyOnSuccess) {
        this.handleError(error, "Nao foi possivel gerar o preview.");
      } else {
        this.renderLocalSummary(payload);
      }
      return null;
    });
  };

  ProgramBuilder.prototype.renderLocalSummary = function(payload) {
    const preview = {
      screenId: payload.screenId,
      pageType: payload.pageType,
      program: {
        id: payload.programCode,
        title: payload.programTitle,
        module: payload.module,
        version: payload.version,
        subtitle: payload.subtitle,
        icon: payload.icon
      },
      runtime: {
        entityCode: payload.builderEntityCode,
        programId: payload.programCode
      },
      permissions: {
        create: payload.allowCreate,
        edit: payload.allowUpdate,
        delete: payload.allowDelete
      }
    };
    this.renderDefinition(preview);
    this.updatePreviewMeta({
      status: this.state.currentVersion && this.state.currentVersion.status || "draft",
      version: payload.version,
      builderEntityCode: payload.builderEntityCode,
      screenId: payload.screenId
    });
    this.previewFooter.text("Resumo local. O JSON completo aparece quando os campos obrigatorios permitem gerar preview no backend.");
  };

  ProgramBuilder.prototype.handleSaveDraft = function() {
    const payload = this.collectProgramPayload();
    this.http.request({
      url: "/api/admin/program-builder/drafts",
      method: "POST",
      data: payload
    }).then((response) => {
      global.CrudUtils.showMessage("Rascunho salvo.", "success");
      this.state.currentProgramCode = response.programCode;
      this.programSelectorInput.value(response.programCode);
      this.populateProgramForm(response);
      this.refreshBootstrap().then(() => this.refreshProgramVersions(response.programCode));
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel salvar o rascunho.");
    });
  };

  ProgramBuilder.prototype.handlePublish = function() {
    const current = this.state.currentVersion;
    if (!current || !current.id) {
      global.CrudUtils.showMessage("Selecione uma versao salva antes de publicar.", "warning");
      return;
    }

    global.CrudUtils.confirm("Publicar esta versao vai atualizar o programa e a tela runtime correspondente.", {
      title: "Publicar versao",
      confirmText: "Publicar"
    }).then((confirmed) => {
      if (!confirmed) {
        return;
      }
      this.http.request({
        url: "/api/admin/program-builder/versions/" + encodeURIComponent(current.id) + "/publish",
        method: "POST",
        data: {}
      }).then((response) => {
        global.CrudUtils.showMessage("Versao publicada.", "success");
        this.refreshBootstrap().then(() => this.refreshProgramVersions(response.program.code));
      }).catch((error) => {
        this.handleError(error, "Nao foi possivel publicar a versao.");
      });
    });
  };

  ProgramBuilder.prototype.handleDuplicate = function() {
    const current = this.state.currentVersion;
    if (!current || !current.id) {
      global.CrudUtils.showMessage("Selecione uma versao para duplicar.", "warning");
      return;
    }

    global.CrudUtils.confirm("Sera criado um novo rascunho com incremento automatico da versao.", {
      title: "Duplicar versao",
      confirmText: "Duplicar"
    }).then((confirmed) => {
      if (!confirmed) {
        return;
      }
      this.http.request({
        url: "/api/admin/program-builder/versions/" + encodeURIComponent(current.id) + "/duplicate",
        method: "POST",
        data: {}
      }).then((response) => {
        global.CrudUtils.showMessage("Nova versao criada como rascunho.", "success");
        this.refreshProgramVersions(response.programCode);
      }).catch((error) => {
        this.handleError(error, "Nao foi possivel duplicar a versao.");
      });
    });
  };

  ProgramBuilder.prototype.refreshBootstrap = function() {
    return this.http.request({
      url: "/api/admin/program-builder/bootstrap",
      method: "GET"
    }).then((payload) => {
      this.state.entities = payload.entities || [];
      this.state.modules = payload.modules || [];
      this.state.programs = payload.programs || [];
      this.applyBootstrapData();
    });
  };

  ProgramBuilder.prototype.refreshProgramVersions = function(programCode) {
    const code = programCode || this.state.currentProgramCode;
    if (!code) {
      return Promise.resolve();
    }

    this.state.currentProgramCode = code;
    this.programSelectorInput.value(code);
    return this.http.request({
      url: "/api/admin/program-builder/programs/" + encodeURIComponent(code),
      method: "GET"
    }).then((payload) => {
      this.state.versions = payload.versions || [];
      this.versionsGrid.dataSource.data(this.state.versions);
      const latestDraft = this.state.versions.find(function(item) {
        return item.status === "draft";
      });
      this.populateProgramForm(latestDraft || this.state.versions[0] || {});
    });
  };

  ProgramBuilder.prototype.handleVersionSelection = function() {
    const item = this.versionsGrid.dataItem(this.versionsGrid.select());
    if (item) {
      this.populateProgramForm(item.toJSON ? item.toJSON() : item);
    }
  };

  ProgramBuilder.prototype.handleEntityVersionSelection = function() {
    const item = this.entityVersionsGrid.dataItem(this.entityVersionsGrid.select());
    if (item) {
      this.state.currentEntityVersion = item.toJSON ? item.toJSON() : item;
      this.syncToolbarState();
    }
  };

  ProgramBuilder.prototype.renderDefinition = function(definition) {
    this.previewElement.text(JSON.stringify(definition || {}, null, 2));
  };

  ProgramBuilder.prototype.updatePreviewMeta = function(item) {
    this.previewMeta.empty();
    const source = item || {};
    const badges = [
      source.status ? "Status: " + source.status : "",
      source.version ? "Versao: " + source.version : "",
      source.builderEntityCode ? "Entidade: " + source.builderEntityCode : "",
      source.screenId ? "Tela: " + source.screenId : ""
    ].filter(Boolean);

    if (!badges.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Preencha os campos para visualizar o resumo da versao.").appendTo(this.previewMeta);
      return;
    }

    badges.forEach(function(text) {
      $("<span class=\"k-badge k-badge-solid k-badge-solid-base k-rounded-md\"></span>").text(text).appendTo(this.previewMeta);
    }, this);
  };

  ProgramBuilder.prototype.updateBanner = function() {
    const current = this.state.currentVersion;
    if (!current || !current.id) {
      return;
    }
    if (current.status === "draft") {
      this.bannerElement.text("Rascunho selecionado. Salve ajustes, gere preview e publique quando a definicao estiver pronta.");
      return;
    }
    this.bannerElement.text("Versao publicada ou arquivada selecionada. Para evoluir sem perder historico, use \"Duplicar versao\".");
  };

  ProgramBuilder.prototype.syncToolbarState = function() {
    const current = this.state.currentVersion;
    const hasSavedVersion = !!(current && current.id);
    const currentEntityVersion = this.state.currentEntityVersion;
    const hasEntityVersion = !!(currentEntityVersion && currentEntityVersion.id);
    this.publishButton.enable(hasSavedVersion);
    this.duplicateButton.enable(hasSavedVersion);
    this.restoreEntityButton.enable(hasEntityVersion);
  };

  ProgramBuilder.prototype.syncSelectedEntityVersionRow = function() {
    if (!this.entityVersionsGrid) {
      return;
    }
    const current = this.state.currentEntityVersion;
    const rows = this.entityVersionsGrid.tbody.find("tr");
    rows.removeClass("k-state-selected");
    if (!current || !current.id) {
      return;
    }
    rows.each((_, row) => {
      const item = this.entityVersionsGrid.dataItem(row);
      if (item && Number(item.id) === Number(current.id)) {
        $(row).addClass("k-state-selected");
      }
    });
  };

  ProgramBuilder.prototype.syncSelectedVersionRow = function() {
    if (!this.versionsGrid) {
      return;
    }
    const current = this.state.currentVersion;
    const rows = this.versionsGrid.tbody.find("tr");
    rows.removeClass("k-state-selected");
    if (!current || !current.id) {
      return;
    }
    rows.each((_, row) => {
      const item = this.versionsGrid.dataItem(row);
      if (item && Number(item.id) === Number(current.id)) {
        $(row).addClass("k-state-selected");
      }
    });
  };

  ProgramBuilder.prototype.handleError = function(error, fallback) {
    const normalized = global.CrudUtils.unwrapError(error, fallback);
    global.CrudUtils.showMessage(normalized.message, "error");
  };

  ProgramBuilder.prototype.stringifyOptions = function(options) {
    try {
      const cleaned = Object.assign({}, options || {});
      return Object.keys(cleaned).length ? JSON.stringify(cleaned, null, 2) : "";
    } catch (_) {
      return "";
    }
  };

  ProgramBuilder.prototype.stringifyExtraOptions = function(options) {
    const cleaned = Object.assign({}, options || {});
    delete cleaned.columnName;
    delete cleaned.maxLength;
    delete cleaned.precision;
    delete cleaned.scale;
    delete cleaned.defaultValue;
    delete cleaned.unique;
    delete cleaned.foreignKey;
    delete cleaned.options;
    delete cleaned.virtual;
    delete cleaned.includeInVersion;
    delete cleaned.versionReference;
    delete cleaned.versionSnapshot;
    delete cleaned.customCode;
    return this.stringifyOptions(cleaned);
  };

  ProgramBuilder.prototype.stringifyCustomCodePromptFields = function(items) {
    if (!Array.isArray(items) || !items.length) {
      return "";
    }
    return items.map(function(item) {
      const options = Array.isArray(item.options) ? item.options.map(function(option) {
        return String(option.value || "") + ":" + String(option.text || option.value || "");
      }).join(",") : "";
      return [
        item.name || "",
        item.label || "",
        item.type || "string",
        item.required === true ? "true" : "false",
        options
      ].join(" | ");
    }).join("\n");
  };

  ProgramBuilder.prototype.stringifyOptionItems = function(items) {
    if (!Array.isArray(items) || !items.length) {
      return "";
    }
    return items.map(function(item) {
      const value = item && item.value != null ? String(item.value) : "";
      const text = item && item.text != null ? String(item.text) : "";
      return value + " | " + text;
    }).join("\n");
  };

  ProgramBuilder.prototype.suggestFieldCode = function(config) {
    const label = this.normalizeSuggestionText(String(config.label || ""));
    const words = label ? label.split(/\s+/).filter(Boolean) : [];
    if (config.primaryKey) {
      return "id";
    }

    const baseTokens = words.map(function(word) {
      return ProgramBuilder.FIELD_ABBREVIATIONS[word] || word.slice(0, 12);
    }).filter(Boolean);

    let prefix = "c";
    if (config.foreignKeyTable) {
      prefix = "";
    } else {
      prefix = ({
        date: "dt",
        datetime: "dt_hr",
        integer: "i",
        decimal: "d",
        boolean: "log",
        text: "t",
        json: "t",
        enum: "c",
        dropdown: "c",
        email: "c",
        custom_code: "c",
        string: "c"
      })[config.dataType] || "c";
    }

    let tokens = baseTokens.length ? baseTokens : ["campo"];
    if (config.unique && tokens[0] !== "id") {
      tokens.unshift("u");
    }
    let name = (prefix ? prefix + "_" : "") + tokens.join("_");
    if (config.foreignKeyTable) {
      name = tokens.join("_");
      if (!/_id$/i.test(name)) {
        name += "_id";
      }
    }
    name = name.replace(/_+/g, "_").replace(/^_+|_+$/g, "");

    return name || "c_campo";
  };

  ProgramBuilder.prototype.normalizeSuggestionText = function(text) {
    return String(text || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9\s]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  };

  ProgramBuilder.prototype.stringifyRuleParams = function(params) {
    try {
      const value = params && typeof params === "object" ? params : {};
      return Object.keys(value).length ? JSON.stringify(value, null, 2) : "";
    } catch (_) {
      return "";
    }
  };

  ProgramBuilder.prototype.parseOptions = function(text, index) {
    const value = String(text || "").trim();
    if (!value) {
      return {};
    }
    try {
      const parsed = JSON.parse(value);
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch (_) {
      throw global.CrudUtils.makeError("ENTITY_FIELD_OPTIONS_INVALID", "JSON invalido nas opcoes do campo " + (index + 1) + ".");
    }
  };

  ProgramBuilder.prototype.parseOptionItems = function(text, index) {
    const value = String(text || "").trim();
    if (!value) {
      return [];
    }

    return value.split(/\r?\n/).map(function(line) {
      const trimmed = line.trim();
      if (!trimmed) {
        return null;
      }
      const parts = trimmed.split("|");
      if (!parts.length) {
        return null;
      }
      const optionValue = String(parts[0] || "").trim();
      const optionText = String(parts[1] || parts[0] || "").trim();
      if (!optionValue) {
        throw global.CrudUtils.makeError("ENTITY_FIELD_OPTIONS_INVALID", "Lista de opcoes invalida no campo " + (index + 1) + ".");
      }
      return {
        value: optionValue,
        text: optionText || optionValue
      };
    }).filter(Boolean);
  };

  ProgramBuilder.prototype.parseCustomCodePromptFields = function(text, index) {
    const value = String(text || "").trim();
    if (!value) {
      return [];
    }

    return value.split(/\r?\n/).map(function(line) {
      const trimmed = line.trim();
      if (!trimmed) {
        return null;
      }
      const parts = trimmed.split("|").map(function(item) {
        return String(item || "").trim();
      });
      const name = String(parts[0] || "").toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^_+|_+$/g, "");
      const label = parts[1] || name;
      const rawType = String(parts[2] || "").toLowerCase();
      const type = ["string", "integer", "decimal", "boolean", "enum", "dropdown"].indexOf(rawType) >= 0 ? rawType : "string";
      if (!name) {
        throw global.CrudUtils.makeError("ENTITY_FIELD_OPTIONS_INVALID", "Campos do assistente invalidos no campo " + (index + 1) + ".");
      }
      const options = [];
      if ((type === "enum" || type === "dropdown") && parts[4]) {
        parts[4].split(",").forEach(function(optionText) {
          const pair = String(optionText || "").split(":");
          const optionValue = String(pair[0] || "").trim();
          const optionLabel = String(pair[1] || pair[0] || "").trim();
          if (optionValue) {
            options.push({
              value: optionValue,
              text: optionLabel || optionValue
            });
          }
        });
      }
      return {
        name: name,
        label: label || name,
        type: type,
        required: String(parts[3] || "").toLowerCase() === "true",
        options: options
      };
    }).filter(Boolean);
  };

  ProgramBuilder.prototype.parseRuleParams = function(text, index) {
    const value = String(text || "").trim();
    if (!value) {
      return {};
    }
    try {
      const parsed = JSON.parse(value);
      return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
    } catch (_) {
      throw global.CrudUtils.makeError("ENTITY_RULE_PARAMS_INVALID", "JSON invalido nos parametros da regra " + (index + 1) + ".");
    }
  };

  ProgramBuilder.prototype.syncFieldRowState = function(row, detailsRow) {
    const type = row.find(".program-builder-field-type").val();
    const primaryKey = row.find(".program-builder-field-pk").is(":checked");
    const virtualField = detailsRow.find(".program-builder-field-virtual").is(":checked");
    const lengthInput = row.find(".program-builder-field-length");
    const precisionInput = detailsRow.find(".program-builder-field-precision");
    const scaleInput = detailsRow.find(".program-builder-field-scale");
    const optionItems = detailsRow.find(".program-builder-field-option-items");
    const fkTable = detailsRow.find(".program-builder-field-fk-table");
    const fkColumn = detailsRow.find(".program-builder-field-fk-column");
    const fkType = detailsRow.find(".program-builder-field-fk-type");
    const fkDelete = detailsRow.find(".program-builder-field-fk-on-delete");
    const fkUpdate = detailsRow.find(".program-builder-field-fk-on-update");
    const uniqueInput = detailsRow.find(".program-builder-field-unique");
    const readonlyInput = detailsRow.find(".program-builder-field-readonly");
    const defaultInput = detailsRow.find(".program-builder-field-default");
    const requiredInput = row.find(".program-builder-field-required");
    const primaryInput = row.find(".program-builder-field-pk");
    const columnInput = row.find(".program-builder-field-column");
    const customCodeMode = detailsRow.find(".program-builder-field-custom-code-mode");
    const customCodePrefix = detailsRow.find(".program-builder-field-custom-code-prefix");
    const customCodePattern = detailsRow.find(".program-builder-field-custom-code-pattern");
    const customCodeSequenceEnabled = detailsRow.find(".program-builder-field-custom-code-sequence-enabled");
    const customCodeSequenceScope = detailsRow.find(".program-builder-field-custom-code-sequence-scope");
    const customCodeSequencePadding = detailsRow.find(".program-builder-field-custom-code-sequence-padding");
    const customCodeStaticClass = detailsRow.find(".program-builder-field-custom-code-static-class");
    const customCodeStaticMethod = detailsRow.find(".program-builder-field-custom-code-static-method");
    const customCodeAssistantScreen = detailsRow.find(".program-builder-field-custom-code-assistant-screen");
    const customCodePromptTitle = detailsRow.find(".program-builder-field-custom-code-prompt-title");
    const customCodePromptFields = detailsRow.find(".program-builder-field-custom-code-prompt-fields");

    const lengthEnabled = ["string", "email", "enum", "dropdown", "custom_code"].indexOf(type) >= 0;
    lengthInput.prop("disabled", virtualField || !lengthEnabled);
    if (virtualField || !lengthEnabled) {
      lengthInput.val("");
    }

    const decimalEnabled = type === "decimal";
    precisionInput.prop("disabled", virtualField || !decimalEnabled);
    scaleInput.prop("disabled", virtualField || !decimalEnabled);
    if (virtualField || !decimalEnabled) {
      precisionInput.val("");
      scaleInput.val("");
    }

    const optionsEnabled = type === "enum" || type === "dropdown";
    optionItems.prop("disabled", !optionsEnabled);
    if (!optionsEnabled) {
      optionItems.val("");
    }

    const customCodeEnabled = type === "custom_code" && !virtualField;
    const customCodePatternMode = customCodeMode.val() !== "static_method";
    const customCodeUsesSequence = customCodeSequenceEnabled.is(":checked");
    customCodeMode.prop("disabled", !customCodeEnabled);
    customCodePrefix.prop("disabled", !customCodeEnabled);
    customCodePattern.prop("disabled", !customCodeEnabled || !customCodePatternMode);
    customCodeSequenceEnabled.prop("disabled", !customCodeEnabled);
    customCodeSequenceScope.prop("disabled", !customCodeEnabled || !customCodeUsesSequence);
    customCodeSequencePadding.prop("disabled", !customCodeEnabled || !customCodeUsesSequence);
    customCodeStaticClass.prop("disabled", !customCodeEnabled || customCodePatternMode);
    customCodeStaticMethod.prop("disabled", !customCodeEnabled || customCodePatternMode);
    customCodeAssistantScreen.prop("disabled", !customCodeEnabled);
    customCodePromptTitle.prop("disabled", !customCodeEnabled);
    customCodePromptFields.prop("disabled", !customCodeEnabled);
    if (!customCodeEnabled) {
      customCodeMode.val("pattern");
      customCodePrefix.val("");
      customCodePattern.val("{YYYY}{MM}{DD}-{SEQ:4}");
      customCodeSequenceEnabled.prop("checked", true);
      customCodeSequenceScope.val("global");
      customCodeSequencePadding.val(4);
      customCodeStaticClass.val("");
      customCodeStaticMethod.val("");
      customCodeAssistantScreen.val("");
      customCodePromptTitle.val("");
      customCodePromptFields.val("");
    }

    columnInput.prop("disabled", virtualField);
    requiredInput.prop("disabled", primaryKey || virtualField);
    fkTable.prop("disabled", primaryKey || virtualField);
    fkColumn.prop("disabled", primaryKey || virtualField);
    fkType.prop("disabled", primaryKey || virtualField);
    fkDelete.prop("disabled", primaryKey || virtualField);
    fkUpdate.prop("disabled", primaryKey || virtualField);
    uniqueInput.prop("disabled", primaryKey || virtualField);
    readonlyInput.prop("disabled", primaryKey || virtualField);
    defaultInput.prop("disabled", virtualField);
    if (primaryKey || virtualField) {
      if (virtualField) {
        primaryInput.prop("checked", false);
      }
      requiredInput.prop("checked", false);
      uniqueInput.prop("checked", false);
      readonlyInput.prop("checked", primaryKey || virtualField);
      defaultInput.val("");
      fkTable.val("");
      fkColumn.val("");
      fkType.val("");
      fkDelete.val("");
      fkUpdate.val("");
    }
  };

  $(function() {
    const app = new ProgramBuilder({
      root: "#program-builder-root"
    });
    app.init();
    global.programBuilderApp = app;
  });
})(window);
