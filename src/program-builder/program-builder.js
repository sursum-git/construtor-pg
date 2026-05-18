(function(global) {
  "use strict";

  function ProgramBuilder(options) {
    this.options = options || {};
    this.root = $(this.options.root || "#program-builder-root");
    this.http = this.options.httpClient || new global.CrudHttpClient({ allowLocalFallback: false });
    this.previewTimer = null;
    this.navigatorFilter = "";
    this.navigatorTypeFilter = "all";
    this.navigatorStateFilter = "all";
    this.state = {
      entities: [],
      modules: [],
      apiSources: [],
      apiSourceDetails: {},
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
      preview: null,
      databaseTables: [],
      importInspection: null,
      externalImportInspection: null,
      aiSettings: null,
      aiSessionId: "",
      aiDraftInspection: null,
      aiHistory: [],
      navigatorSelection: null,
      propertySelection: null,
      validation: { entityIssues: [], programIssues: [], fieldIssues: {}, ruleIssues: {}, uniqueKeyIssues: {} },
      currentLock: null,
      lockReadonly: false,
      currentUser: null
    };
    this.contextStorageKey = "program-builder-editor-context-v1";
    this.restoredContext = null;
    this.lockHeartbeatTimer = null;
    this.pendingLockScopeKey = "";
    this.dragState = null;
  }

  global.ProgramBuilder = ProgramBuilder;

  ProgramBuilder.prototype.t = function(key, fallback, params) {
    if (global.ProgramBuilderLiterals && typeof global.ProgramBuilderLiterals.t === "function") {
      return global.ProgramBuilderLiterals.t(key, fallback, params);
    }
    return fallback || key || "";
  };

  ProgramBuilder.prototype.normalizeTechnicalProperties = function(properties) {
    if (!global.CrudUtils || typeof global.CrudUtils.normalizeTechnicalProperties !== "function") {
      return [];
    }
    return global.CrudUtils.normalizeTechnicalProperties(properties);
  };

  ProgramBuilder.prototype.buildTechnicalProperties = function(section, label, description, items) {
    const properties = [];
    if (section) {
      properties.push({ section: "Contexto", label: "Area", value: section });
    }
    if (label) {
      properties.push({ section: "Contexto", label: "Campo", value: label });
    }
    if (description) {
      properties.push({ section: "Contexto", label: "Descricao", value: description });
    }
    (items || []).forEach(function(item) {
      if (!item || typeof item !== "object") {
        return;
      }
      properties.push(item);
    });
    return this.normalizeTechnicalProperties(properties);
  };

  ProgramBuilder.prototype.appendFieldLabel = function(parent, label, technicalProperties) {
    const row = $("<div class=\"program-builder-field-label-row\"></div>").appendTo(parent);
    $("<span></span>").text(label).appendTo(row);
    const normalized = this.normalizeTechnicalProperties(technicalProperties);
    if (normalized.length && global.CrudUtils && typeof global.CrudUtils.appendTechnicalInfoTrigger === "function") {
      global.CrudUtils.appendTechnicalInfoTrigger(row, label, normalized, {
        dataRole: "program-builder-technical-info"
      });
    }
    return row;
  };

  ProgramBuilder.FIELD_ABBREVIATIONS = {
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
    this.loadEditorContext();
    const literalInit = global.ProgramBuilderLiterals && typeof global.ProgramBuilderLiterals.init === "function"
      ? global.ProgramBuilderLiterals.init(this.http)
      : Promise.resolve(null);
    return literalInit.then(function() {
      this.renderShell();
      return this.loadBootstrap();
    }.bind(this)).then(function() {
      this.applyRestoredContext();
    }.bind(this));
  };

  ProgramBuilder.prototype.renderShell = function() {
    this.root.empty();
    const shell = $("<section class=\"program-builder-shell\"></section>").appendTo(this.root);

    const appbar = $("<header class=\"program-builder-appbar\"></header>").appendTo(shell);
    const title = $("<div class=\"program-builder-title\"></div>").appendTo(appbar);
    $("<h1></h1>").text(this.t("program_builder.title", "Construtor de Programas")).appendTo(title);
    $("<p></p>").text(this.t("program_builder.subtitle", "Modele a entidade, gere o programa CRUD e controle o historico de versoes no mesmo fluxo.")).appendTo(title);
    this.toolbarElement = $("<div class=\"program-builder-toolbar\"></div>").appendTo(appbar);
    this.newEntityButton = this.createToolbarButton(this.t("program_builder.button.new_entity", "Nova entidade"), "plus", "primary", this.handleNewEntity.bind(this));
    this.importTableButton = this.createToolbarButton(this.t("program_builder.button.import_table", "Importar tabela"), "folder-open", null, this.openDatabaseImportDialog.bind(this));
    this.apiCatalogButton = this.createToolbarButton(this.t("program_builder.button.api_catalog", "Gerenciar APIs"), "globe", null, this.openApiSourceManagerDialog.bind(this));
    this.integrationAssistantButton = this.createToolbarButton(this.t("program_builder.button.integration_assistant", "Integracao"), "link-horizontal", null, this.openIntegrationAssistantDialog.bind(this));
    this.importExternalButton = this.createToolbarButton(this.t("program_builder.button.import_external_json", "Importar JSON externo"), "file-txt", null, this.openExternalJsonImportDialog.bind(this));
    this.aiAssistantButton = this.createToolbarButton(this.t("program_builder.button.ai_assistant", "Assistente IA"), "comment", null, this.openAiAssistantDialog.bind(this));
    this.aiSettingsButton = this.createToolbarButton(this.t("program_builder.button.ai_settings", "Configurar IA"), "gear", null, this.openAiSettingsDialog.bind(this));
    this.saveEntityButton = this.createToolbarButton(this.t("program_builder.button.save_entity", "Salvar entidade"), "save", null, this.handleSaveEntity.bind(this));
    this.restoreEntityButton = this.createToolbarButton(this.t("program_builder.button.restore_entity", "Restaurar entidade"), "undo", null, this.handleRestoreEntityVersion.bind(this));
    this.newDraftButton = this.createToolbarButton(this.t("program_builder.button.new_draft", "Novo rascunho"), "file-add", null, this.handleNewDraft.bind(this));
    this.previewButton = this.createToolbarButton(this.t("program_builder.button.preview", "Gerar preview"), "eye", null, this.handlePreview.bind(this));
    this.saveDraftButton = this.createToolbarButton(this.t("program_builder.button.save_draft", "Salvar rascunho"), "save", null, this.handleSaveDraft.bind(this));
    this.governanceButton = this.createToolbarButton(this.t("program_builder.button.governance", "Governanca"), "lock", null, this.openGovernanceDialog.bind(this));
    this.overlayRebaseButton = this.createToolbarButton(this.t("program_builder.button.overlay_rebase", "Rebase overlay"), "arrows-merge", null, this.openOverlayRebaseDialog.bind(this));
    this.publishButton = this.createToolbarButton(this.t("program_builder.button.publish", "Publicar"), "upload", null, this.handlePublish.bind(this));
    this.duplicateButton = this.createToolbarButton(this.t("program_builder.button.duplicate_version", "Duplicar versao"), "copy", null, this.handleDuplicate.bind(this));

    this.bannerElement = $("<section class=\"program-builder-banner\"></section>").text(this.t("program_builder.banner.loading", "Carregando metadados do construtor...")).appendTo(shell);
    this.workspaceSummary = $("<section class=\"program-builder-workspace-summary\"></section>").appendTo(shell);

    const content = $("<section class=\"program-builder-content\"></section>").appendTo(shell);
    this.workspaceSplitterElement = $("<div class=\"program-builder-splitter\"></div>").appendTo(content);

    const navigatorPane = $("<section class=\"program-builder-pane program-builder-pane-nav\"></section>").appendTo(this.workspaceSplitterElement);
    const editorPane = $("<section class=\"program-builder-pane program-builder-pane-editor\"></section>").appendTo(this.workspaceSplitterElement);
    const sidePane = $("<section class=\"program-builder-pane program-builder-pane-side\"></section>").appendTo(this.workspaceSplitterElement);

    this.renderNavigator(navigatorPane);

    this.editorTabsElement = $("<div class=\"program-builder-editor-tabs\"></div>").appendTo(editorPane);
    const editorTabsList = $("<ul></ul>").appendTo(this.editorTabsElement);
    $("<li class=\"k-active\">Modulo</li>").appendTo(editorTabsList);
    $("<li>Entidade</li>").appendTo(editorTabsList);
    $("<li>Programa</li>").appendTo(editorTabsList);

    const modulesTab = $("<div></div>").appendTo(this.editorTabsElement);
    this.modulesPanel = $("<article class=\"program-builder-panel\"></article>").appendTo(modulesTab);
    $("<h2></h2>").text("Modulos estruturais").appendTo(this.modulesPanel);
    this.modulesFormElement = $("<form class=\"program-builder-form\"></form>").appendTo(this.modulesPanel);
    this.modulesGridElement = $("<div></div>").appendTo(this.modulesPanel);

    const entityTab = $("<div></div>").appendTo(this.editorTabsElement);
    this.entityPanel = $("<article class=\"program-builder-panel\"></article>").appendTo(entityTab);
    $("<h2></h2>").text("Modelagem da entidade").appendTo(this.entityPanel);
    this.entityFormElement = $("<form class=\"program-builder-form\"></form>").appendTo(this.entityPanel);

    const programTab = $("<div></div>").appendTo(this.editorTabsElement);
    this.programPanel = $("<article class=\"program-builder-panel\"></article>").appendTo(programTab);
    $("<h2></h2>").text("Geracao do programa").appendTo(this.programPanel);
    this.programFormElement = $("<form class=\"program-builder-form\"></form>").appendTo(this.programPanel);

    this.sideTabsElement = $("<div class=\"program-builder-side-tabs\"></div>").appendTo(sidePane);
    const sideTabsList = $("<ul></ul>").appendTo(this.sideTabsElement);
    $("<li class=\"k-active\">Preview</li>").appendTo(sideTabsList);
    $("<li>Propriedades</li>").appendTo(sideTabsList);
    $("<li>Relacionamentos</li>").appendTo(sideTabsList);
    $("<li>Versoes do programa</li>").appendTo(sideTabsList);
    $("<li>Revisoes da entidade</li>").appendTo(sideTabsList);
    $("<li>Comparativo</li>").appendTo(sideTabsList);
    $("<li>Diagnostico</li>").appendTo(sideTabsList);

    const previewTab = $("<div></div>").appendTo(this.sideTabsElement);
    this.previewPanel = $("<article class=\"program-builder-panel program-builder-preview\"></article>").appendTo(previewTab);
    $("<h2></h2>").text("Definicao gerada").appendTo(this.previewPanel);
    this.previewMeta = $("<div class=\"program-builder-preview-meta\"></div>").appendTo(this.previewPanel);
    this.previewElement = $("<pre></pre>").appendTo(this.previewPanel);
    this.previewFooter = $("<div class=\"program-builder-preview-footer\"></div>").appendTo(this.previewPanel);

    const propertiesTab = $("<div></div>").appendTo(this.sideTabsElement);
    this.propertiesPanel = $("<article class=\"program-builder-panel program-builder-properties\"></article>").appendTo(propertiesTab);
    $("<h2></h2>").text("Propriedades").appendTo(this.propertiesPanel);
    this.propertiesElement = $("<div class=\"program-builder-properties-body\"></div>").appendTo(this.propertiesPanel);

    const relationsTab = $("<div></div>").appendTo(this.sideTabsElement);
    this.relationsPanel = $("<article class=\"program-builder-panel program-builder-relations\"></article>").appendTo(relationsTab);
    $("<h2></h2>").text("Relacionamentos").appendTo(this.relationsPanel);
    this.relationsElement = $("<div class=\"program-builder-relations-body\"></div>").appendTo(this.relationsPanel);

    const versionsTab = $("<div></div>").appendTo(this.sideTabsElement);
    this.versionsPanel = $("<article class=\"program-builder-panel program-builder-versions\"></article>").appendTo(versionsTab);
    $("<div class=\"program-builder-versions-header\"><h2>Historico de versoes</h2><p>Selecione uma versao para revisar, publicar novamente ou duplicar para um novo rascunho.</p></div>").appendTo(this.versionsPanel);
    this.versionsGridElement = $("<div></div>").appendTo(this.versionsPanel);

    const entityVersionsTab = $("<div></div>").appendTo(this.sideTabsElement);
    this.entityVersionsPanel = $("<article class=\"program-builder-panel program-builder-versions\"></article>").appendTo(entityVersionsTab);
    $("<div class=\"program-builder-versions-header\"><h2>Historico da entidade</h2><p>Cada salvamento gera uma revisao completa da modelagem e permite rollback estrutural.</p></div>").appendTo(this.entityVersionsPanel);
    this.entityVersionsGridElement = $("<div></div>").appendTo(this.entityVersionsPanel);

    const compareTab = $("<div></div>").appendTo(this.sideTabsElement);
    this.comparePanel = $("<article class=\"program-builder-panel program-builder-compare\"></article>").appendTo(compareTab);
    $("<h2></h2>").text("Comparativo").appendTo(this.comparePanel);
    this.compareControls = $("<div class=\"program-builder-compare-controls\"></div>").appendTo(this.comparePanel);
    this.compareElement = $("<div class=\"program-builder-compare-body\"></div>").appendTo(this.comparePanel);

    const diagnosticsTab = $("<div></div>").appendTo(this.sideTabsElement);
    this.diagnosticsPanel = $("<article class=\"program-builder-panel program-builder-diagnostics\"></article>").appendTo(diagnosticsTab);
    $("<h2></h2>").text("Diagnostico").appendTo(this.diagnosticsPanel);
    $("<p class=\"program-builder-diagnostics-intro\"></p>").text("Pendencias rapidas da entidade e do programa corrente.").appendTo(this.diagnosticsPanel);
    this.diagnosticsElement = $("<div class=\"program-builder-diagnostics-list\"></div>").appendTo(this.diagnosticsPanel);

    this.renderModulesForm();
    this.renderModulesGrid();
    this.renderEntityForm();
    this.renderEntityVersionsGrid();
    this.renderProgramForm();
    this.renderVersionsGrid();
    this.renderComparePanel();
    this.editorTabs = this.editorTabsElement.kendoTabStrip({
      animation: false,
      select: this.persistEditorContext.bind(this)
    }).data("kendoTabStrip");
    this.sideTabs = this.sideTabsElement.kendoTabStrip({
      animation: false,
      select: this.persistEditorContext.bind(this)
    }).data("kendoTabStrip");
    this.workspaceSplitter = this.workspaceSplitterElement.kendoSplitter({
      orientation: "horizontal",
      panes: [
        { collapsible: true, size: "290px", min: "250px" },
        { collapsible: false, min: "520px" },
        { collapsible: true, size: "420px", min: "340px" }
      ]
    }).data("kendoSplitter");
    this.bindRealtimeEditorEvents();
    this.bindRowDragAndDrop();
    this.bindUnloadGuards();
    this.updateWorkspaceSummary();
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.entityFieldTechnicalProperties = function(key) {
    const map = {
      existingEntity: this.buildTechnicalProperties("Entidade", "Entidade existente", "Seleciona uma modelagem ja cadastrada para revisar, versionar ou publicar.", [
        { section: "Fluxo", label: "Acao", value: "Carrega a entidade atual do catalogo do construtor." }
      ]),
      entityCode: this.buildTechnicalProperties("Entidade", "Codigo da entidade", "Identificador tecnico unico da entidade no construtor e no runtime.", [
        { section: "Modelo", label: "Uso", value: "Chave tecnica de referencia interna.", critical: true }
      ]),
      entityName: this.buildTechnicalProperties("Entidade", "Nome da entidade", "Nome funcional exibido no editor e em partes do runtime."),
      tableName: this.buildTechnicalProperties("Entidade", "Tabela fisica", "Nome da tabela persistente quando a entidade usa armazenamento local.", [
        { section: "Banco", label: "Aplicavel", value: "Persistence" }
      ]),
      entityType: this.buildTechnicalProperties("Entidade", "Tipo da entidade", "Define se a origem e tabela local, consulta, IO ou API externa.", [
        { section: "Modelo", label: "Valores", value: "persistence | query | io | api", critical: true }
      ]),
      subscriberIsolation: this.buildTechnicalProperties("Entidade", "Isolamento por assinante", "Define se a tabela guarda registros globais ou filtrados pela coluna do assinante.", [
        { section: "Tenancy", label: "Valores", value: "none | subscriber_column", critical: true }
      ]),
      situationField: this.buildTechnicalProperties("Entidade", "Campo de situacao", "Campo usado pelo motor de situacoes e transicoes quando habilitado."),
      entityFlags: this.buildTechnicalProperties("Entidade", "Opcoes da entidade", "Agrupa flags estruturais e de versionamento que afetam a geracao do runtime.", [
        { section: "Runtime", label: "Impacto", value: "Tabela, renomeacao, exclusao, situacao e versionamento." }
      ]),
      fieldsHeader: this.buildTechnicalProperties("Entidade", "Campos da entidade", "Lista principal de campos que alimenta grid, filtro, formulario e contrato runtime.", [
        { section: "Modelo", label: "Superficies", value: "Grid, filtro, formulario, API e regras." }
      ])
    };
    return map[key] || [];
  };

  ProgramBuilder.prototype.programFieldTechnicalProperties = function(key) {
    const map = {
      existingProgram: this.buildTechnicalProperties("Programa", "Programa existente", "Seleciona um programa ja cadastrado para editar versoes, preview e publicacao."),
      programCode: this.buildTechnicalProperties("Programa", "Codigo do programa", "Identificador tecnico unico do programa publicado.", [
        { section: "Runtime", label: "Uso", value: "Referencia principal para versoes e publicacao.", critical: true }
      ]),
      programTitle: this.buildTechnicalProperties("Programa", "Titulo do programa", "Titulo funcional exibido na Home e no shell runtime."),
      programModule: this.buildTechnicalProperties("Programa", "Modulo", "Modulo estrutural onde o programa sera catalogado e agrupado."),
      screenId: this.buildTechnicalProperties("Programa", "Screen ID", "Chave publica usada pelo runtime para abrir a definicao publicada.", [
        { section: "Runtime", label: "Uso", value: "production/app.html?screenId=...", critical: true }
      ]),
      baseEntity: this.buildTechnicalProperties("Programa", "Entidade base", "Entidade usada para gerar grid, filtro, formulario e endpoints CRUD."),
      version: this.buildTechnicalProperties("Programa", "Versao", "Versao funcional armazenada no historico de publicacao."),
      subtitle: this.buildTechnicalProperties("Programa", "Subtitulo", "Texto complementar opcional exibido no shell."),
      icon: this.buildTechnicalProperties("Programa", "Icone", "Nome do icone Kendo/Lucide usado na Home e em listas."),
      permissionPrefix: this.buildTechnicalProperties("Programa", "Prefixo de permissao", "Base para derivar permissoes de leitura e escrita do runtime."),
      pageType: this.buildTechnicalProperties("Programa", "Tipo de pagina", "Define se a publicacao gera CRUD padrao ou entrada custom registrada."),
      programOrigin: this.buildTechnicalProperties("Programa", "Origem do programa", "Classifica se a versao pertence ao padrao do produto, overlay por assinante ou programa especifico do cliente.", [
        { section: "Governanca", label: "Valores", value: "standard | customer_overlay | customer_custom", critical: true }
      ]),
      ownerScope: this.buildTechnicalProperties("Programa", "Escopo do owner", "Define se o owner do programa e o sistema ou um assinante especifico.", [
        { section: "Governanca", label: "Valores", value: "system | subscriber", critical: true }
      ]),
      customizationPolicy: this.buildTechnicalProperties("Programa", "Politica de customizacao", "Controla se o padrao pode ser bloqueado, aceitar overlay ou override total."),
      subscriberId: this.buildTechnicalProperties("Programa", "Assinante", "Identificador do assinante dono do overlay ou da variante especifica."),
      baseProgramCode: this.buildTechnicalProperties("Programa", "Programa base", "Programa padrao do qual o overlay ou custom especifico deriva."),
      baseProgramVersionId: this.buildTechnicalProperties("Programa", "Versao base", "Versao do programa padrao usada como referencia para upgrade e rebase."),
      upgradeFrozen: this.buildTechnicalProperties("Programa", "Upgrade congelado", "Indica se a variante do cliente deixou de receber atualizacao automatica da base.", [
        { section: "Versionamento", label: "Impacto", value: "Congela upgrade automatico", critical: true }
      ]),
      frozenReason: this.buildTechnicalProperties("Programa", "Motivo do congelamento", "Justificativa curta para o congelamento do upgrade."),
      publicationPolicy: this.buildTechnicalProperties("Programa", "Ambientes permitidos", "Lista dos ambientes de banco autorizados para publicar esta versao, separada por virgula.", [
        { section: "Governanca", label: "Exemplo", value: "prod, homolog" }
      ]),
      customMode: this.buildTechnicalProperties("Programa", "Modo custom", "Escolhe se a tela manual abre em iframe interno ou por fragmento HTML controlado."),
      customEntryUrl: this.buildTechnicalProperties("Programa", "Entry URL", "Caminho relativo da implementacao manual registrada no catalogo.", [
        { section: "Seguranca", label: "Restricao", value: "Somente caminhos relativos do proprio sistema.", critical: true }
      ]),
      customFrameTitle: this.buildTechnicalProperties("Programa", "Titulo do frame", "Titulo acessivel usado pelo renderer custom em iframe."),
      writeFlags: this.buildTechnicalProperties("Programa", "Permissoes de escrita", "Controla inclusao, alteracao e exclusao no CRUD gerado.", [
        { section: "Runtime", label: "Observacao", value: "Entidades API readonly e Odoo desligam estas flags automaticamente." }
      ]),
      changeSummary: this.buildTechnicalProperties("Programa", "Resumo da versao", "Historico curto do objetivo funcional desta versao.")
    };
    return map[key] || [];
  };

  ProgramBuilder.prototype.apiFieldTechnicalProperties = function(key) {
    const map = {
      sourceCode: this.buildTechnicalProperties("API", "Cadastro de API", "Vincula uma fonte reutilizavel com contrato, autenticacao e operacoes publicadas."),
      listOperation: this.buildTechnicalProperties("API", "Operacao de lista", "Operacao usada para alimentar o grid e a pagina principal de consulta."),
      detailOperation: this.buildTechnicalProperties("API", "Operacao de detalhe", "Operacao opcional usada para abrir o formulario em visualizacao."),
      createOperation: this.buildTechnicalProperties("API", "Operacao de inclusao", "Operacao declarativa para create em APIs JSON previsiveis."),
      updateOperation: this.buildTechnicalProperties("API", "Operacao de alteracao", "Operacao declarativa para update em APIs JSON previsiveis."),
      deleteOperation: this.buildTechnicalProperties("API", "Operacao de exclusao", "Operacao declarativa para delete em APIs JSON previsiveis."),
      apiCatalogActions: this.buildTechnicalProperties("API", "Cadastro", "Acoes auxiliares para abrir o cadastro de APIs e importar campos de modelo."),
      odooTransport: this.buildTechnicalProperties("Odoo", "Transporte", "Canal RPC usado pela integracao Odoo readonly.", [
        { section: "Odoo", label: "Valores", value: "xmlrpc | jsonrpc", critical: true }
      ]),
      odooDatabase: this.buildTechnicalProperties("Odoo", "Banco", "Nome da base Odoo usada na autenticacao RPC."),
      odooLogin: this.buildTechnicalProperties("Odoo", "Login", "Usuario tecnico usado para autenticar na instancia Odoo."),
      odooSecretMode: this.buildTechnicalProperties("Odoo", "Segredo", "Define se o segredo cadastrado e senha ou API key."),
      odooModel: this.buildTechnicalProperties("Odoo", "Modelo Odoo", "Modelo ORM consultado por search_read, search_count e read.", [
        { section: "Odoo", label: "Exemplo", value: "res.partner", critical: true }
      ]),
      odooOrder: this.buildTechnicalProperties("Odoo", "Ordenacao padrao", "Clausula de ordenacao enviada ao Odoo na consulta."),
      odooLimit: this.buildTechnicalProperties("Odoo", "Limite padrao", "Quantidade padrao de registros por consulta quando aplicavel."),
      odooContext: this.buildTechnicalProperties("Odoo", "Contexto padrao (JSON objeto)", "Contexto RPC adicional enviado para o modelo Odoo."),
      odooDomain: this.buildTechnicalProperties("Odoo", "Dominio padrao (JSON array)", "Filtro base do Odoo em formato domain."),
      apiBaseUrl: this.buildTechnicalProperties("API", "Base URL", "Base comum usada para compor endpoints genericos ou OpenAPI."),
      apiTimeout: this.buildTechnicalProperties("API", "Timeout (segundos)", "Tempo maximo aceito para chamadas externas dessa fonte."),
      apiAuthHeaders: this.buildTechnicalProperties("API", "Headers fixos da entidade (JSON objeto)", "Headers adicionais fixos aplicados ao contrato expandido da entidade."),
      apiListUrl: this.buildTechnicalProperties("API", "URL da lista", "Endpoint usado pela leitura principal do grid."),
      apiListMethod: this.buildTechnicalProperties("API", "Metodo", "Metodo HTTP permitido para a lista.", [
        { section: "Contrato", label: "Valores", value: "GET | POST", critical: true }
      ]),
      apiListItemsPath: this.buildTechnicalProperties("API", "itemsPath", "Caminho dentro do JSON que aponta para o array principal de itens.", [
        { section: "Contrato", label: "Obrigatorio", value: "Sim para leitura generica", critical: true }
      ]),
      apiListTotalPath: this.buildTechnicalProperties("API", "totalPath", "Caminho opcional para totalizacao do grid."),
      apiListHeaders: this.buildTechnicalProperties("API", "Headers da lista (JSON objeto)", "Headers especificos da operacao de lista."),
      apiListQuery: this.buildTechnicalProperties("API", "Query params da lista (JSON objeto)", "Parametros de query declarativos da lista."),
      apiListBody: this.buildTechnicalProperties("API", "Body template da lista (JSON/valor simples)", "Payload declarativo fechado da operacao de lista."),
      apiDetailUrl: this.buildTechnicalProperties("API", "URL do detalhe", "Endpoint usado para abrir um registro especifico."),
      apiDetailMethod: this.buildTechnicalProperties("API", "Metodo", "Metodo HTTP permitido para o detalhe.", [
        { section: "Contrato", label: "Valores", value: "GET | POST", critical: true }
      ]),
      apiDetailItemPath: this.buildTechnicalProperties("API", "itemPath", "Caminho dentro do JSON que aponta para o item do detalhe."),
      apiDetailHeaders: this.buildTechnicalProperties("API", "Headers do detalhe (JSON objeto)", "Headers especificos da operacao de detalhe."),
      apiDetailQuery: this.buildTechnicalProperties("API", "Query params do detalhe (JSON objeto)", "Parametros declarativos do detalhe."),
      apiDetailBody: this.buildTechnicalProperties("API", "Body template do detalhe (JSON/valor simples)", "Payload declarativo fechado do detalhe.")
    };
    return map[key] || [];
  };

  ProgramBuilder.prototype.renderNavigator = function(parent) {
    const panel = $("<article class=\"program-builder-panel program-builder-navigator\"></article>").appendTo(parent);
    const header = $("<div class=\"program-builder-navigator-header\"></div>").appendTo(panel);
    const title = $("<div></div>").appendTo(header);
    $("<h2></h2>").text("Navegacao").appendTo(title);
    $("<p></p>").text("Use a arvore para trocar rapido de modulo, entidade e programa.").appendTo(title);

    this.navigatorStats = $("<div class=\"program-builder-navigator-stats\"></div>").appendTo(panel);
    this.navigatorQuickFilters = $("<div class=\"program-builder-navigator-quick-filters\"></div>").appendTo(panel);
    this.renderNavigatorQuickFilters();
    const filterField = $("<div class=\"program-builder-navigator-filter\"></div>").appendTo(panel);
    this.navigatorFilterInput = $("<input>").appendTo(filterField).kendoTextBox({
      placeholder: "Filtrar arvore"
    }).data("kendoTextBox");
    const navigatorFilterElement = this.navigatorFilterInput && (this.navigatorFilterInput.input || this.navigatorFilterInput.element);
    if (navigatorFilterElement && typeof navigatorFilterElement.on === "function") {
      navigatorFilterElement.on("input", this.handleNavigatorFilterChange.bind(this));
      navigatorFilterElement.on("change", this.handleNavigatorFilterChange.bind(this));
    }

    this.navigatorTreeElement = $("<div class=\"program-builder-tree\"></div>").appendTo(panel);
    this.navigatorTreeElement.kendoTreeView({
      select: this.handleNavigatorSelect.bind(this),
      dataSource: []
    });
    this.navigatorTree = this.navigatorTreeElement.data("kendoTreeView");
    this.navigatorTreeClickHandler = this.handleNavigatorTreeClick.bind(this);
    if (this.navigatorTreeElement[0] && this.navigatorTreeElement[0].addEventListener) {
      this.navigatorTreeElement[0].addEventListener("click", this.navigatorTreeClickHandler, true);
    }
    this.navigatorTreeElement.on("click", ".k-item, [role='treeitem'], .k-treeview-leaf", this.handleNavigatorTreeClick.bind(this));

    this.navigatorActions = $("<div class=\"program-builder-navigator-actions\"></div>").appendTo(panel);
    this.renderNavigatorActions();
  };

  ProgramBuilder.prototype.renderNavigatorActions = function() {
    const container = this.navigatorActions;
    if (!container) {
      return;
    }
    container.empty();
    $("<div class=\"program-builder-fields-header\"><span>Acoes rapidas</span></div>").appendTo(container);
    this.navigatorActionButtons = {
      newEntity: $("<button type=\"button\"></button>").text("Nova entidade").appendTo(container).kendoButton({
        icon: "plus",
        click: this.handleNewEntity.bind(this)
      }).data("kendoButton"),
      newProgram: $("<button type=\"button\"></button>").text("Novo programa").appendTo(container).kendoButton({
        icon: "file-add",
        click: this.handleNavigatorNewProgram.bind(this)
      }).data("kendoButton"),
      editModule: $("<button type=\"button\"></button>").text("Editar modulo").appendTo(container).kendoButton({
        icon: "edit-tools",
        click: this.handleNavigatorEditModule.bind(this)
      }).data("kendoButton"),
      openEntity: $("<button type=\"button\"></button>").text("Abrir entidade").appendTo(container).kendoButton({
        icon: "folder-open",
        click: this.handleNavigatorOpenEntity.bind(this)
      }).data("kendoButton"),
      openProgram: $("<button type=\"button\"></button>").text("Abrir programa").appendTo(container).kendoButton({
        icon: "folder-open",
        click: this.handleNavigatorOpenProgram.bind(this)
      }).data("kendoButton"),
      openRelatedProgram: $("<button type=\"button\"></button>").text("Programa ligado").appendTo(container).kendoButton({
        icon: "link-horizontal",
        click: this.handleNavigatorOpenRelatedProgram.bind(this)
      }).data("kendoButton"),
      generatePreview: $("<button type=\"button\"></button>").text("Gerar preview").appendTo(container).kendoButton({
        icon: "eye",
        click: this.handlePreview.bind(this)
      }).data("kendoButton"),
      duplicateVersion: $("<button type=\"button\"></button>").text("Duplicar versao").appendTo(container).kendoButton({
        icon: "copy",
        click: this.handleDuplicate.bind(this)
      }).data("kendoButton"),
      publishVersion: $("<button type=\"button\"></button>").text("Publicar").appendTo(container).kendoButton({
        icon: "upload",
        click: this.handlePublish.bind(this)
      }).data("kendoButton")
    };
  };

  ProgramBuilder.prototype.renderNavigatorQuickFilters = function() {
    const quick = this.navigatorQuickFilters;
    if (!quick) {
      return;
    }
    quick.empty();

    const typeGroup = $("<div class=\"program-builder-quick-group\"></div>").appendTo(quick);
    $("<span class=\"program-builder-quick-label\"></span>").text("Tipo").appendTo(typeGroup);
    this.navigatorTypeButtons = {};
    [
      { key: "all", text: "Tudo" },
      { key: "module", text: "Modulos" },
      { key: "entity", text: "Entidades" },
      { key: "program", text: "Programas" }
    ].forEach(function(item) {
      const button = $("<button type=\"button\"></button>").text(item.text).appendTo(typeGroup).kendoButton({
        size: "small",
        click: function() {
          this.navigatorTypeFilter = item.key;
          this.refreshNavigator();
        }.bind(this)
      }).data("kendoButton");
      this.navigatorTypeButtons[item.key] = button;
    }, this);

    const stateGroup = $("<div class=\"program-builder-quick-group\"></div>").appendTo(quick);
    $("<span class=\"program-builder-quick-label\"></span>").text("Estado").appendTo(stateGroup);
    this.navigatorStateButtons = {};
    [
      { key: "all", text: "Todos" },
      { key: "published", text: "Publicados" },
      { key: "versioned", text: "Versionados" }
    ].forEach(function(item) {
      const button = $("<button type=\"button\"></button>").text(item.text).appendTo(stateGroup).kendoButton({
        size: "small",
        click: function() {
          this.navigatorStateFilter = item.key;
          this.refreshNavigator();
        }.bind(this)
      }).data("kendoButton");
      this.navigatorStateButtons[item.key] = button;
    }, this);
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

    this.renderDatabaseImportPanel(form);
    this.renderExternalJsonImportPanel(form);

    const entitySelectorField = this.appendField(form, "Entidade existente", this.entityFieldTechnicalProperties("existingEntity"));
    this.entitySelectorInput = $("<input>").appendTo(entitySelectorField).kendoDropDownList({
      dataTextField: "title",
      dataValueField: "code",
      optionLabel: "Nova entidade",
      change: this.handleEntitySelection.bind(this)
    }).data("kendoDropDownList");

    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.entityCodeInput = this.createTextField(splitA, "Codigo da entidade", this.entityFieldTechnicalProperties("entityCode"));
    this.entityNameInput = this.createTextField(splitA, "Nome da entidade", this.entityFieldTechnicalProperties("entityName"));

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.entityTableNameInput = this.createTextField(splitB, "Tabela fisica", this.entityFieldTechnicalProperties("tableName"));
    const entityTypeField = this.appendField(splitB, "Tipo da entidade", this.entityFieldTechnicalProperties("entityType"));
    this.entityTypeSelect = $("<input>").appendTo(entityTypeField).kendoDropDownList({
      dataSource: [
        { value: "persistence", text: "Persistence" },
        { value: "query", text: "Query" },
        { value: "io", text: "IO" },
        { value: "api", text: "API" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "persistence",
      change: this.syncEntityTypeState.bind(this)
    }).data("kendoDropDownList");

    const splitC = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.entitySituationFieldInput = this.createTextField(splitC, "Campo de situacao", this.entityFieldTechnicalProperties("situationField"));
    const subscriberIsolationField = this.appendField(splitC, "Escopo dos registros", this.entityFieldTechnicalProperties("subscriberIsolation"));
    this.entitySubscriberIsolationSelect = $("<input>").appendTo(subscriberIsolationField).kendoDropDownList({
      dataSource: [
        { value: "none", text: "Compartilhado/global" },
        { value: "subscriber_column", text: "Filtrado por assinante" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "none",
      change: this.syncSubscriberIsolationState.bind(this)
    }).data("kendoDropDownList");
    this.entitySubscriberColumnInput = this.createTextField(splitC, "Coluna do assinante", this.entityFieldTechnicalProperties("subscriberIsolation"));
    const subscriberGlobalField = this.appendField(splitC, "Confirmacao da tabela", this.entityFieldTechnicalProperties("subscriberIsolation"));
    const subscriberGlobalLabel = $("<label class=\"program-builder-inline-checkbox\"></label>").appendTo(subscriberGlobalField);
    this.entitySubscriberGlobalTableInput = $("<input type=\"checkbox\">").appendTo(subscriberGlobalLabel);
    $("<span></span>").text("Tabela global compartilhada entre assinantes").appendTo(subscriberGlobalLabel);
    this.entitySubscriberGlobalTableInput.kendoCheckBox({
      change: this.syncSubscriberIsolationState.bind(this)
    });
    this.entityTypeHint = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(form);
    this.entityTypeHint.text("Fluxo completo atual: tipo persistence com tabela fisica e programa CRUD.");
    this.renderApiSourceEditor(form);

    this.renderStructureEditor(form);

    const flagsField = this.appendField(form, "Opcoes da entidade", this.entityFieldTechnicalProperties("entityFlags"));
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
    this.appendFieldLabel(fieldsHeader, "Campos da entidade", this.entityFieldTechnicalProperties("fieldsHeader"));
    $("<button type=\"button\"></button>").text("Sugerir nomes").appendTo(fieldsHeader).kendoButton({
      icon: "wand",
      click: this.handleSuggestFieldNames.bind(this)
    });
    $("<button type=\"button\"></button>").text("Adicionar campo").appendTo(fieldsHeader).kendoButton({
      icon: "plus",
      click: this.handleAddFieldRow.bind(this)
    });

    this.fieldsTableElement = $("<table class=\"program-builder-fields-table\"></table>").appendTo(form);
    $("<thead><tr><th class=\"program-builder-handle-col\"></th><th>Campo</th><th>Label</th><th>Tipo</th><th>Coluna</th><th>Tam.</th><th>Obrig.</th><th>PK</th><th></th></tr></thead>").appendTo(this.fieldsTableElement);
    this.fieldsTableBody = $("<tbody></tbody>").appendTo(this.fieldsTableElement);

    this.namingHintElement = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(form);
    this.namingHintElement.text("Padrao Genesis: tabelas como t1, t1c1, t1e1, t1r, t1m, t1e2at2e3; campos com prefixos tecnicos como c_, t_, d_, dt_, dt_hr_, log_ e sufixo _id para FKs.");

    this.renderUniqueKeysEditor(form);
    this.renderRulesEditor(form);
    this.renderHistoricalAssistant(form);
  };

  ProgramBuilder.prototype.renderDatabaseImportPanel = function(form) {
    this.databaseImportPanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Importar tabela existente</h3><p>Leia uma tabela do PostgreSQL, gere a entidade e opcionalmente abra um rascunho CRUD para revisao antes da publicacao.</p></div>").appendTo(this.databaseImportPanel);

    const importForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.databaseImportPanel);
    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(importForm);
    const tableField = this.appendField(splitA, "Tabela do banco");
    this.databaseImportTableSelect = $("<input>").appendTo(tableField).kendoDropDownList({
      dataTextField: "qualifiedName",
      dataValueField: "qualifiedName",
      optionLabel: "Selecione a tabela",
      filter: "contains"
    }).data("kendoDropDownList");
    this.databaseImportEntityCodeInput = this.createTextField(splitA, "Codigo da entidade");
    this.databaseImportEntityNameInput = this.createTextField(splitA, "Nome da entidade");

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(importForm);
    this.databaseImportProgramEnabledInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(this.appendField(splitB, "Gerar rascunho CRUD")));
    $("<span></span>").text("Criar rascunho de programa").appendTo(this.databaseImportProgramEnabledInput.parent());
    this.databaseImportProgramEnabledInput.kendoCheckBox({
      change: this.syncDatabaseImportState.bind(this)
    });
    const moduleField = this.appendField(splitB, "Modulo do programa");
    this.databaseImportProgramModuleSelect = $("<input>").appendTo(moduleField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione o modulo"
    }).data("kendoDropDownList");
    this.databaseImportProgramCodeInput = this.createTextField(splitB, "Codigo do programa");

    const splitC = $("<div class=\"program-builder-split\"></div>").appendTo(importForm);
    this.databaseImportProgramTitleInput = this.createTextField(splitC, "Titulo do programa");
    this.databaseImportScreenIdInput = this.createTextField(splitC, "Screen ID");
    this.databaseImportVersionInput = this.createTextField(splitC, "Versao");

    const actions = $("<div class=\"program-builder-fields-header\"></div>").appendTo(importForm);
    $("<span></span>").text("Fluxo de importacao").appendTo(actions);
    $("<button type=\"button\"></button>").text("Analisar tabela").appendTo(actions).kendoButton({
      icon: "search",
      click: this.handleInspectDatabaseTable.bind(this)
    });
    $("<button type=\"button\"></button>").text("Carregar na modelagem").appendTo(actions).kendoButton({
      icon: "download",
      click: this.handleApplyImportedDraft.bind(this)
    });
    $("<button type=\"button\"></button>").text("Importar como rascunho").appendTo(actions).kendoButton({
      icon: "file-add",
      click: this.handleImportDatabaseTable.bind(this)
    });

    this.databaseImportSummary = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(importForm);
    this.databaseImportSummary.text("A importacao cria a entidade em rascunho no construtor. A publicacao do programa continua manual.");
  };

  ProgramBuilder.prototype.renderApiSourceEditor = function(form) {
    this.apiSourcePanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Fonte API</h3><p>Configure APIs genericas, Odoo e outras consultas externas para entidades do tipo API.</p></div>").appendTo(this.apiSourcePanel);
    const apiForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.apiSourcePanel);

    const bindingSplit = $("<div class=\"program-builder-split\"></div>").appendTo(apiForm);
    const sourceField = this.appendField(bindingSplit, "Cadastro de API", this.apiFieldTechnicalProperties("sourceCode"));
    this.apiCatalogSourceSelect = $("<input>").appendTo(sourceField).kendoDropDownList({
      dataSource: [],
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a API cadastrada",
      change: this.handleApiSourceSelectionChange.bind(this)
    }).data("kendoDropDownList");
    const listOperationField = this.appendField(bindingSplit, "Operacao de lista", this.apiFieldTechnicalProperties("listOperation"));
    this.apiCatalogListOperationSelect = $("<input>").appendTo(listOperationField).kendoDropDownList({
      dataSource: [],
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a operacao",
      change: this.handleApiSourceOperationChange.bind(this)
    }).data("kendoDropDownList");

    const bindingSplitB = $("<div class=\"program-builder-split\"></div>").appendTo(apiForm);
    const detailOperationField = this.appendField(bindingSplitB, "Operacao de detalhe", this.apiFieldTechnicalProperties("detailOperation"));
    this.apiCatalogDetailOperationSelect = $("<input>").appendTo(detailOperationField).kendoDropDownList({
      dataSource: [],
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Sem detalhe",
      change: this.handleApiSourceOperationChange.bind(this)
    }).data("kendoDropDownList");
    const bindingActionsField = this.appendField(bindingSplitB, "Cadastro", this.apiFieldTechnicalProperties("apiCatalogActions"));
    $("<button type=\"button\"></button>").text("Abrir cadastro de APIs").appendTo(bindingActionsField).kendoButton({
      icon: "globe",
      click: this.openApiSourceManagerDialog.bind(this)
    });
    $("<button type=\"button\"></button>").text("Carregar campos do modelo").appendTo(bindingActionsField).kendoButton({
      icon: "download",
      click: this.handleLoadOdooModelFields.bind(this)
    });
    const bindingSplitC = $("<div class=\"program-builder-split\"></div>").appendTo(apiForm);
    const createOperationField = this.appendField(bindingSplitC, "Operacao de inclusao", this.apiFieldTechnicalProperties("createOperation"));
    this.apiCatalogCreateOperationSelect = $("<input>").appendTo(createOperationField).kendoDropDownList({
      dataSource: [],
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Sem inclusao",
      change: this.handleApiSourceOperationChange.bind(this)
    }).data("kendoDropDownList");
    const updateOperationField = this.appendField(bindingSplitC, "Operacao de alteracao", this.apiFieldTechnicalProperties("updateOperation"));
    this.apiCatalogUpdateOperationSelect = $("<input>").appendTo(updateOperationField).kendoDropDownList({
      dataSource: [],
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Sem alteracao",
      change: this.handleApiSourceOperationChange.bind(this)
    }).data("kendoDropDownList");
    const deleteOperationField = this.appendField(bindingSplitC, "Operacao de exclusao", this.apiFieldTechnicalProperties("deleteOperation"));
    this.apiCatalogDeleteOperationSelect = $("<input>").appendTo(deleteOperationField).kendoDropDownList({
      dataSource: [],
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Sem exclusao",
      change: this.handleApiSourceOperationChange.bind(this)
    }).data("kendoDropDownList");
    this.apiCatalogHint = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(apiForm);
    this.apiCatalogHint.text("Entidades API usam um cadastro de metadados reutilizavel. Edite o contrato no cadastro e vincule as operacoes aqui.");

    this.apiOdooPanel = $("<div class=\"program-builder-subpanel\"></div>").appendTo(apiForm);
    $("<div class=\"program-builder-versions-header\"><h3>Configuracao Odoo</h3><p>Resumo do transporte e do modelo vinculado ao cadastro selecionado.</p></div>").appendTo(this.apiOdooPanel);
    const odooForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.apiOdooPanel);
    const odooSplitA = $("<div class=\"program-builder-split\"></div>").appendTo(odooForm);
    const odooTransportField = this.appendField(odooSplitA, "Transporte", this.apiFieldTechnicalProperties("odooTransport"));
    this.apiOdooTransportSelect = $("<input>").appendTo(odooTransportField).kendoDropDownList({
      dataSource: [{ value: "xmlrpc", text: "XML-RPC" }, { value: "jsonrpc", text: "JSON-RPC" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "xmlrpc"
    }).data("kendoDropDownList");
    this.apiOdooDatabaseInput = this.createTextField(odooSplitA, "Banco", this.apiFieldTechnicalProperties("odooDatabase"));
    const odooSplitB = $("<div class=\"program-builder-split\"></div>").appendTo(odooForm);
    this.apiOdooLoginInput = this.createTextField(odooSplitB, "Login", this.apiFieldTechnicalProperties("odooLogin"));
    const odooSecretModeField = this.appendField(odooSplitB, "Segredo", this.apiFieldTechnicalProperties("odooSecretMode"));
    this.apiOdooSecretModeSelect = $("<input>").appendTo(odooSecretModeField).kendoDropDownList({
      dataSource: [{ value: "password", text: "Senha" }, { value: "api_key", text: "API Key" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "password"
    }).data("kendoDropDownList");
    this.apiOdooModelInput = this.createTextField(odooForm, "Modelo Odoo", this.apiFieldTechnicalProperties("odooModel"));
    const odooSplitC = $("<div class=\"program-builder-split\"></div>").appendTo(odooForm);
    this.apiOdooOrderInput = this.createTextField(odooSplitC, "Ordenacao padrao", this.apiFieldTechnicalProperties("odooOrder"));
    this.apiOdooLimitInput = this.createTextField(odooSplitC, "Limite padrao", this.apiFieldTechnicalProperties("odooLimit"));
    const odooContextField = this.appendField(odooForm, "Contexto padrao (JSON objeto)", this.apiFieldTechnicalProperties("odooContext"));
    this.apiOdooContextInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(odooContextField);
    const odooDomainField = this.appendField(odooForm, "Dominio padrao (JSON array)", this.apiFieldTechnicalProperties("odooDomain"));
    this.apiOdooDomainInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(odooDomainField);

    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(apiForm);
    this.apiBaseUrlInput = this.createTextField(splitA, "Base URL", this.apiFieldTechnicalProperties("apiBaseUrl"));
    this.apiTimeoutInput = this.createTextField(splitA, "Timeout (segundos)", this.apiFieldTechnicalProperties("apiTimeout"));

    const authHeadersField = this.appendField(apiForm, "Headers fixos da entidade (JSON objeto)", this.apiFieldTechnicalProperties("apiAuthHeaders"));
    this.apiAuthHeadersInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(authHeadersField);

    const listSection = $("<div class=\"program-builder-subpanel\"></div>").appendTo(apiForm);
    $("<div class=\"program-builder-versions-header\"><h3>Consulta de lista</h3></div>").appendTo(listSection);
    const listForm = $("<div class=\"program-builder-form\"></div>").appendTo(listSection);
    const listSplitA = $("<div class=\"program-builder-split\"></div>").appendTo(listForm);
    this.apiListUrlInput = this.createTextField(listSplitA, "URL da lista", this.apiFieldTechnicalProperties("apiListUrl"));
    const listMethodField = this.appendField(listSplitA, "Metodo", this.apiFieldTechnicalProperties("apiListMethod"));
    this.apiListMethodSelect = $("<input>").appendTo(listMethodField).kendoDropDownList({
      dataSource: [{ value: "GET", text: "GET" }, { value: "POST", text: "POST" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "GET"
    }).data("kendoDropDownList");
    const listSplitB = $("<div class=\"program-builder-split\"></div>").appendTo(listForm);
    this.apiListItemsPathInput = this.createTextField(listSplitB, "itemsPath", this.apiFieldTechnicalProperties("apiListItemsPath"));
    this.apiListTotalPathInput = this.createTextField(listSplitB, "totalPath", this.apiFieldTechnicalProperties("apiListTotalPath"));
    const listHeadersField = this.appendField(listForm, "Headers da lista (JSON objeto)", this.apiFieldTechnicalProperties("apiListHeaders"));
    this.apiListHeadersInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(listHeadersField);
    const listQueryField = this.appendField(listForm, "Query params da lista (JSON objeto)", this.apiFieldTechnicalProperties("apiListQuery"));
    this.apiListQueryInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(listQueryField);
    const listBodyField = this.appendField(listForm, "Body template da lista (JSON/valor simples)", this.apiFieldTechnicalProperties("apiListBody"));
    this.apiListBodyInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(listBodyField);

    const detailSection = $("<div class=\"program-builder-subpanel\"></div>").appendTo(apiForm);
    $("<div class=\"program-builder-versions-header\"><h3>Consulta de detalhe</h3></div>").appendTo(detailSection);
    const detailForm = $("<div class=\"program-builder-form\"></div>").appendTo(detailSection);
    const detailSplitA = $("<div class=\"program-builder-split\"></div>").appendTo(detailForm);
    this.apiDetailUrlInput = this.createTextField(detailSplitA, "URL do detalhe", this.apiFieldTechnicalProperties("apiDetailUrl"));
    const detailMethodField = this.appendField(detailSplitA, "Metodo", this.apiFieldTechnicalProperties("apiDetailMethod"));
    this.apiDetailMethodSelect = $("<input>").appendTo(detailMethodField).kendoDropDownList({
      dataSource: [{ value: "GET", text: "GET" }, { value: "POST", text: "POST" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "GET"
    }).data("kendoDropDownList");
    const detailSplitB = $("<div class=\"program-builder-split\"></div>").appendTo(detailForm);
    this.apiDetailItemPathInput = this.createTextField(detailSplitB, "itemPath", this.apiFieldTechnicalProperties("apiDetailItemPath"));
    $("<div></div>").appendTo(detailSplitB);
    const detailHeadersField = this.appendField(detailForm, "Headers do detalhe (JSON objeto)", this.apiFieldTechnicalProperties("apiDetailHeaders"));
    this.apiDetailHeadersInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(detailHeadersField);
    const detailQueryField = this.appendField(detailForm, "Query params do detalhe (JSON objeto)", this.apiFieldTechnicalProperties("apiDetailQuery"));
    this.apiDetailQueryInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(detailQueryField);
    const detailBodyField = this.appendField(detailForm, "Body template do detalhe (JSON/valor simples)", this.apiFieldTechnicalProperties("apiDetailBody"));
    this.apiDetailBodyInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(detailBodyField);
  };

  ProgramBuilder.prototype.refreshApiSourceSelectors = function() {
    const summaries = (this.state.apiSources || []).map(function(item) {
      return {
        code: item.code,
        name: item.code + " - " + item.name
      };
    });
    if (this.apiCatalogSourceSelect) {
      this.apiCatalogSourceSelect.setDataSource(new kendo.data.DataSource({ data: summaries }));
    }
    if (this.apiSourceManagerSelect) {
      this.apiSourceManagerSelect.setDataSource(new kendo.data.DataSource({ data: summaries }));
    }
  };

  ProgramBuilder.prototype.findApiSourceSummary = function(code) {
    code = String(code || "").trim();
    return (this.state.apiSources || []).find(function(item) {
      return item.code === code;
    }) || null;
  };

  ProgramBuilder.prototype.isOdooApiSource = function(source) {
    return !!source && String(source.providerType || "generic") === "odoo";
  };

  ProgramBuilder.prototype.loadApiSourceDefinition = function(code, forceReload) {
    code = String(code || "").trim();
    if (!code) {
      return Promise.resolve(null);
    }
    if (!forceReload && this.state.apiSourceDetails && this.state.apiSourceDetails[code]) {
      return Promise.resolve(this.state.apiSourceDetails[code]);
    }
    return this.http.request({
      url: "/api/admin/program-builder/api-sources/" + encodeURIComponent(code),
      method: "GET"
    }).then(function(response) {
      const apiSource = response && response.apiSource ? response.apiSource : null;
      if (apiSource) {
        this.state.apiSourceDetails[code] = apiSource;
      }
      return apiSource;
    }.bind(this));
  };

  ProgramBuilder.prototype.handleApiSourceSelectionChange = function() {
    const sourceCode = String(this.apiCatalogSourceSelect.value() || "").trim();
    this.apiCatalogListOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogDetailOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogCreateOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogUpdateOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogDeleteOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogListOperationSelect.value("");
    this.apiCatalogDetailOperationSelect.value("");
    this.apiCatalogCreateOperationSelect.value("");
    this.apiCatalogUpdateOperationSelect.value("");
    this.apiCatalogDeleteOperationSelect.value("");
    if (!sourceCode) {
      this.resetInlineApiSourceFields();
      this.syncApiBindingState();
      this.validateIncremental();
      return Promise.resolve(null);
    }
    return this.loadApiSourceDefinition(sourceCode).then(function(apiSource) {
      const operations = Array.isArray(apiSource && apiSource.operations) ? apiSource.operations : [];
      this.apiCatalogListOperationSelect.setDataSource(new kendo.data.DataSource({
        data: operations.filter(function(item) { return item.type === "list"; }).map(function(item) {
          return { code: item.code, name: item.code + " - " + item.name };
        })
      }));
      this.apiCatalogDetailOperationSelect.setDataSource(new kendo.data.DataSource({
        data: operations.filter(function(item) { return item.type === "detail"; }).map(function(item) {
          return { code: item.code, name: item.code + " - " + item.name };
        })
      }));
      this.apiCatalogCreateOperationSelect.setDataSource(new kendo.data.DataSource({
        data: operations.filter(function(item) { return item.type === "create"; }).map(function(item) {
          return { code: item.code, name: item.code + " - " + item.name };
        })
      }));
      this.apiCatalogUpdateOperationSelect.setDataSource(new kendo.data.DataSource({
        data: operations.filter(function(item) { return item.type === "update"; }).map(function(item) {
          return { code: item.code, name: item.code + " - " + item.name };
        })
      }));
      this.apiCatalogDeleteOperationSelect.setDataSource(new kendo.data.DataSource({
        data: operations.filter(function(item) { return item.type === "delete"; }).map(function(item) {
          return { code: item.code, name: item.code + " - " + item.name };
        })
      }));
      const defaultList = operations.find(function(item) { return item.type === "list"; });
      const defaultDetail = operations.find(function(item) { return item.type === "detail"; });
      const defaultCreate = operations.find(function(item) { return item.type === "create"; });
      const defaultUpdate = operations.find(function(item) { return item.type === "update"; });
      const defaultDelete = operations.find(function(item) { return item.type === "delete"; });
      this.apiCatalogListOperationSelect.value(defaultList ? defaultList.code : "");
      this.apiCatalogDetailOperationSelect.value(defaultDetail ? defaultDetail.code : "");
      this.apiCatalogCreateOperationSelect.value(defaultCreate ? defaultCreate.code : "");
      this.apiCatalogUpdateOperationSelect.value(defaultUpdate ? defaultUpdate.code : "");
      this.apiCatalogDeleteOperationSelect.value(defaultDelete ? defaultDelete.code : "");
      this.applyApiSourceToInlineEditor(apiSource);
      this.syncApiBindingState();
      this.syncProgramWriteFlagsForApi();
      this.validateIncremental();
      return apiSource;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel carregar o cadastro da API.");
    }.bind(this));
  };

  ProgramBuilder.prototype.handleApiSourceOperationChange = function() {
    const sourceCode = String(this.apiCatalogSourceSelect.value() || "").trim();
    if (!sourceCode) {
      this.validateIncremental();
      return;
    }
    this.loadApiSourceDefinition(sourceCode).then(function(apiSource) {
      this.applyApiSourceToInlineEditor(apiSource);
      this.syncProgramWriteFlagsForApi();
      this.validateIncremental();
    }.bind(this));
  };

  ProgramBuilder.prototype.applyApiSourceToInlineEditor = function(apiSource) {
    const source = apiSource || null;
    if (!source) {
      this.resetInlineApiSourceFields();
      return;
    }
    const odooSource = this.isOdooApiSource(source);
    const operations = Array.isArray(source.operations) ? source.operations : [];
    const listOperationCode = String(this.apiCatalogListOperationSelect.value() || "").trim();
    const detailOperationCode = String(this.apiCatalogDetailOperationSelect.value() || "").trim();
    const listOperation = operations.find(function(item) { return item.code === listOperationCode; }) || null;
    const detailOperation = operations.find(function(item) { return item.code === detailOperationCode; }) || null;

    this.apiBaseUrlInput.value(source.baseUrl || "");
    this.apiTimeoutInput.value(source.timeoutSeconds != null ? String(source.timeoutSeconds) : "20");
    this.apiAuthHeadersInput.val(source.authHeaders ? JSON.stringify(source.authHeaders, null, 2) : "");
    this.apiListUrlInput.value(listOperation && listOperation.path || "");
    this.apiListMethodSelect.value(listOperation && listOperation.method || "GET");
    this.apiListItemsPathInput.value(listOperation && listOperation.itemsPath || "");
    this.apiListTotalPathInput.value(listOperation && listOperation.totalPath || "");
    this.apiListHeadersInput.val(listOperation && listOperation.headers ? JSON.stringify(listOperation.headers, null, 2) : "");
    this.apiListQueryInput.val(listOperation && listOperation.queryParams ? JSON.stringify(listOperation.queryParams, null, 2) : "");
    this.apiListBodyInput.val(listOperation && listOperation.bodyTemplate != null ? JSON.stringify(listOperation.bodyTemplate, null, 2) : "");
    this.apiDetailUrlInput.value(detailOperation && detailOperation.path || "");
    this.apiDetailMethodSelect.value(detailOperation && detailOperation.method || "GET");
    this.apiDetailItemPathInput.value(detailOperation && detailOperation.itemPath || "");
    this.apiDetailHeadersInput.val(detailOperation && detailOperation.headers ? JSON.stringify(detailOperation.headers, null, 2) : "");
    this.apiDetailQueryInput.val(detailOperation && detailOperation.queryParams ? JSON.stringify(detailOperation.queryParams, null, 2) : "");
    this.apiDetailBodyInput.val(detailOperation && detailOperation.bodyTemplate != null ? JSON.stringify(detailOperation.bodyTemplate, null, 2) : "");
    const odoo = source.odoo || {};
    this.apiOdooTransportSelect.value(odoo.transport || "xmlrpc");
    this.apiOdooDatabaseInput.value(odoo.database || "");
    this.apiOdooLoginInput.value(odoo.login || "");
    this.apiOdooSecretModeSelect.value(odoo.secretMode || "password");
    this.apiOdooModelInput.value(odoo.model || "");
    this.apiOdooOrderInput.value(odoo.defaultOrder || "");
    this.apiOdooLimitInput.value(odoo.defaultLimit != null ? String(odoo.defaultLimit) : "");
    this.apiOdooContextInput.val(odoo.defaultContext ? JSON.stringify(odoo.defaultContext, null, 2) : "");
    this.apiOdooDomainInput.val(odoo.defaultDomain ? JSON.stringify(odoo.defaultDomain, null, 2) : "[]");
    if (this.apiOdooPanel) {
      this.apiOdooPanel.toggle(odooSource);
    }
  };

  ProgramBuilder.prototype.resetInlineApiSourceFields = function() {
    this.apiBaseUrlInput.value("");
    this.apiTimeoutInput.value("20");
    this.apiAuthHeadersInput.val("");
    this.apiListUrlInput.value("");
    this.apiListMethodSelect.value("GET");
    this.apiListItemsPathInput.value("");
    this.apiListTotalPathInput.value("");
    this.apiListHeadersInput.val("");
    this.apiListQueryInput.val("");
    this.apiListBodyInput.val("");
    this.apiDetailUrlInput.value("");
    this.apiDetailMethodSelect.value("GET");
    this.apiDetailItemPathInput.value("");
    this.apiDetailHeadersInput.val("");
    this.apiDetailQueryInput.val("");
    this.apiDetailBodyInput.val("");
    this.apiOdooTransportSelect.value("xmlrpc");
    this.apiOdooDatabaseInput.value("");
    this.apiOdooLoginInput.value("");
    this.apiOdooSecretModeSelect.value("password");
    this.apiOdooModelInput.value("");
    this.apiOdooOrderInput.value("");
    this.apiOdooLimitInput.value("");
    this.apiOdooContextInput.val("");
    this.apiOdooDomainInput.val("[]");
    if (this.apiOdooPanel) {
      this.apiOdooPanel.hide();
    }
  };

  ProgramBuilder.prototype.syncApiBindingState = function() {
    const apiEntity = (this.entityTypeSelect.value() || "persistence") === "api";
    const sourceBound = apiEntity && String(this.apiCatalogSourceSelect && this.apiCatalogSourceSelect.value() || "").trim() !== "";
    const sourceSummary = sourceBound ? this.findApiSourceSummary(String(this.apiCatalogSourceSelect.value() || "").trim()) : null;
    const odooSource = this.isOdooApiSource(sourceSummary);
    [
      this.apiBaseUrlInput,
      this.apiTimeoutInput,
      this.apiAuthHeadersInput,
      this.apiListUrlInput,
      this.apiListItemsPathInput,
      this.apiListTotalPathInput,
      this.apiListHeadersInput,
      this.apiListQueryInput,
      this.apiListBodyInput,
      this.apiDetailUrlInput,
      this.apiDetailItemPathInput,
      this.apiDetailHeadersInput,
      this.apiDetailQueryInput,
      this.apiDetailBodyInput
    ].forEach(function(widget) {
      const input = widget && (widget.input || widget.element || widget);
      if (input && typeof input.prop === "function") {
        input.prop("disabled", true);
      }
    });
    [this.apiListMethodSelect, this.apiDetailMethodSelect].forEach(function(widget) {
      if (widget && typeof widget.enable === "function") {
        widget.enable(false);
      }
    });
    [
      this.apiOdooTransportSelect,
      this.apiOdooSecretModeSelect
    ].forEach(function(widget) {
      if (widget && typeof widget.enable === "function") {
        widget.enable(false);
      }
    });
    [
      this.apiOdooDatabaseInput,
      this.apiOdooLoginInput,
      this.apiOdooModelInput,
      this.apiOdooOrderInput,
      this.apiOdooLimitInput,
      this.apiOdooContextInput,
      this.apiOdooDomainInput
    ].forEach(function(widget) {
      const input = widget && (widget.input || widget.element || widget);
      if (input && typeof input.prop === "function") {
        input.prop("disabled", true);
      }
    });
    if (this.apiOdooPanel) {
      this.apiOdooPanel.toggle(apiEntity && odooSource);
    }
    if (this.apiCatalogHint) {
      if (!sourceBound) {
        this.apiCatalogHint.text("Selecione um cadastro de API para carregar as operacoes disponiveis.");
      } else if (odooSource) {
        this.apiCatalogHint.text("Fonte Odoo carregada do cadastro reutilizavel. A tela sera readonly e os campos podem ser importados do modelo.");
      } else {
        this.apiCatalogHint.text("Contrato carregado do cadastro da API. Ajustes devem ser feitos no cadastro reutilizavel.");
      }
    }
  };

  ProgramBuilder.prototype.syncProgramWriteFlagsForApi = function() {
    if (!this.pageTypeSelect || this.pageTypeSelect.value() !== "crud") {
      return;
    }
    if (!this.entityTypeSelect || this.entityTypeSelect.value() !== "api") {
      return;
    }
    const source = this.findApiSourceSummary(String(this.apiCatalogSourceSelect && this.apiCatalogSourceSelect.value() || "").trim());
    if (this.isOdooApiSource(source)) {
      this.allowCreateInput.prop("checked", false);
      this.allowUpdateInput.prop("checked", false);
      this.allowDeleteInput.prop("checked", false);
      return;
    }
    this.allowCreateInput.prop("checked", String(this.apiCatalogCreateOperationSelect.value() || "").trim() !== "");
    this.allowUpdateInput.prop("checked", String(this.apiCatalogUpdateOperationSelect.value() || "").trim() !== "");
    this.allowDeleteInput.prop("checked", String(this.apiCatalogDeleteOperationSelect.value() || "").trim() !== "");
  };

  ProgramBuilder.prototype.handleLoadOdooModelFields = function() {
    const sourceCode = String(this.apiCatalogSourceSelect && this.apiCatalogSourceSelect.value() || "").trim();
    if (!sourceCode) {
      global.CrudUtils.showMessage("Selecione um cadastro de API antes de carregar os campos do modelo.", "warning");
      return Promise.resolve(null);
    }
    return this.loadApiSourceDefinition(sourceCode, true).then(function(apiSource) {
      if (!this.isOdooApiSource(apiSource)) {
        global.CrudUtils.showMessage("A carga automatica de campos nesta etapa e exclusiva para fontes Odoo.", "warning");
        return null;
      }
      const proceed = () => this.http.request({
        url: "/api/admin/program-builder/api-sources/odoo/model-metadata",
        method: "POST",
        data: {
          sourceCode: sourceCode
        }
      }).then(function(response) {
        const fields = Array.isArray(response && response.fields) ? response.fields : [];
        if (!fields.length) {
          global.CrudUtils.showMessage("O modelo Odoo nao retornou campos importaveis.", "warning");
          return response;
        }
        this.renderFieldRows(fields);
        this.refreshPropertySelection();
        this.renderRelationshipView();
        this.validateIncremental();
        global.CrudUtils.showMessage("Campos do modelo Odoo carregados na modelagem.", "success");
        return response;
      }.bind(this)).catch(function(error) {
        this.handleError(error, "Nao foi possivel carregar os campos do modelo Odoo.");
        return null;
      }.bind(this));

      const currentFields = (this.collectEntityPayload().fields || []).filter(function(item) {
        return String(item.code || "").trim() !== "";
      });
      if (!currentFields.length) {
        return proceed();
      }
      return global.CrudUtils.confirm("A modelagem atual ja possui campos. Deseja substituir pelos campos do modelo Odoo?", {
        title: "Substituir campos",
        confirmText: "Substituir",
        cancelText: "Cancelar",
        themeColor: "warning"
      }).then(function(confirmed) {
        if (!confirmed) {
          return null;
        }
        return proceed();
      });
    }.bind(this));
  };

  ProgramBuilder.prototype.refreshPropertySelection = function() {
    const selection = this.state.propertySelection || null;
    if (!selection) {
      this.renderPropertyInspector();
      return;
    }
    if (selection.kind === "field") {
      const count = this.fieldsTableBody.find("tr").filter(function() {
        return !$(this).hasClass("program-builder-field-details-row");
      }).length;
      if (!count) {
        this.selectPropertyNode("entity", { code: this.entityCodeInput.value() || "" });
        return;
      }
      selection.index = Math.max(0, Math.min(Number(selection.index || 0), count - 1));
    }
    if (selection.kind === "rule") {
      const count = this.rulesTableBody.find(".program-builder-rule-row").length;
      if (!count) {
        this.selectPropertyNode("entity", { code: this.entityCodeInput.value() || "" });
        return;
      }
      selection.index = Math.max(0, Math.min(Number(selection.index || 0), count - 1));
    }
    if (selection.kind === "uniqueKey") {
      const count = this.uniqueKeysTableBody.find(".program-builder-unique-key-row").length;
      if (!count) {
        this.selectPropertyNode("entity", { code: this.entityCodeInput.value() || "" });
        return;
      }
      selection.index = Math.max(0, Math.min(Number(selection.index || 0), count - 1));
    }
    this.state.propertySelection = selection;
    this.syncPropertySelectionState();
    this.renderPropertyInspector();
  };

  ProgramBuilder.prototype.openDatabaseImportDialog = function() {
    this.activateEditorTab(1);
    if (this.databaseImportTableSelect && this.databaseImportTableSelect.focus) {
      this.databaseImportTableSelect.focus();
    }
  };

  ProgramBuilder.prototype.syncDatabaseImportState = function() {
    const enabled = this.databaseImportProgramEnabledInput.is(":checked");
    this.databaseImportProgramModuleSelect.enable(enabled);
    [
      this.databaseImportProgramCodeInput,
      this.databaseImportProgramTitleInput,
      this.databaseImportScreenIdInput,
      this.databaseImportVersionInput
    ].forEach(function(widget) {
      const input = widget && (widget.input || widget.element);
      if (input && typeof input.prop === "function") {
        input.prop("disabled", !enabled);
      }
    });
  };

  ProgramBuilder.prototype.collectDatabaseImportPayload = function() {
    const qualifiedName = String(this.databaseImportTableSelect.value() || "").trim();
    const entityCode = String(this.databaseImportEntityCodeInput.value() || "").trim();
    const entityName = String(this.databaseImportEntityNameInput.value() || "").trim();
    const generateProgramDraft = this.databaseImportProgramEnabledInput.is(":checked");
    const parts = qualifiedName.split(".");
    return {
      qualifiedName: qualifiedName,
      schema: parts.length > 1 ? parts[0] : "public",
      tableName: parts.length > 1 ? parts.slice(1).join(".") : qualifiedName,
      entityCode: entityCode,
      entityName: entityName,
      generateProgramDraft: generateProgramDraft,
      module: String(this.databaseImportProgramModuleSelect.value() || "").trim(),
      programCode: String(this.databaseImportProgramCodeInput.value() || "").trim(),
      programTitle: String(this.databaseImportProgramTitleInput.value() || "").trim(),
      screenId: String(this.databaseImportScreenIdInput.value() || "").trim(),
      version: String(this.databaseImportVersionInput.value() || "").trim() || "1.0.0"
    };
  };

  ProgramBuilder.prototype.handleInspectDatabaseTable = function() {
    const payload = this.collectDatabaseImportPayload();
    if (!payload.tableName) {
      global.CrudUtils.showMessage("Selecione uma tabela para analisar.", "warning");
      return Promise.resolve(null);
    }
    return this.http.request({
      url: "/api/admin/program-builder/database/inspect",
      method: "POST",
      data: payload
    }).then(function(response) {
      this.state.importInspection = response || null;
      this.applyImportInspection(response || {});
      global.CrudUtils.showMessage("Analise da tabela concluida.", "success");
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel analisar a tabela.");
    }.bind(this));
  };

  ProgramBuilder.prototype.applyImportInspection = function(response) {
    const entityDraft = response && response.entityDraft ? response.entityDraft : null;
    const programDraft = response && response.programDraft ? response.programDraft : null;
    if (entityDraft) {
      this.databaseImportEntityCodeInput.value(entityDraft.code || "");
      this.databaseImportEntityNameInput.value(entityDraft.name || "");
    }
    if (programDraft) {
      if (!this.databaseImportProgramCodeInput.value()) {
        this.databaseImportProgramCodeInput.value(programDraft.programCode || "");
      }
      if (!this.databaseImportProgramTitleInput.value()) {
        this.databaseImportProgramTitleInput.value(programDraft.programTitle || "");
      }
      if (!this.databaseImportScreenIdInput.value()) {
        this.databaseImportScreenIdInput.value(programDraft.screenId || "");
      }
      if (!this.databaseImportVersionInput.value()) {
        this.databaseImportVersionInput.value(programDraft.version || "1.0.0");
      }
    }
    this.renderDatabaseImportSummary(response || {});
  };

  ProgramBuilder.prototype.renderDatabaseImportSummary = function(response) {
    const classification = response && response.classification ? response.classification : null;
    const diagnostics = response && Array.isArray(response.diagnostics) ? response.diagnostics : [];
    const entityDraft = response && response.entityDraft ? response.entityDraft : null;
    const parts = [];
    if (classification && classification.label) {
      parts.push("Classificacao: " + classification.label + ".");
    }
    if (entityDraft) {
      parts.push("Campos importados: " + String((entityDraft.fields || []).length) + ".");
    }
    if (diagnostics.length) {
      parts.push(diagnostics.map(function(item) { return String(item.message || ""); }).join(" "));
    } else {
      parts.push("Revise labels, permissoes, relacionamentos e regras antes de publicar.");
    }
    this.databaseImportSummary.text(parts.join(" "));
  };

  ProgramBuilder.prototype.handleApplyImportedDraft = function() {
    if (!this.state.importInspection || !this.state.importInspection.entityDraft) {
      return this.handleInspectDatabaseTable().then(function(response) {
        if (response && response.entityDraft) {
          this.handleApplyImportedDraft();
        }
      }.bind(this));
    }
    this.populateEntityForm(this.state.importInspection.entityDraft, []);
    if (this.databaseImportProgramEnabledInput.is(":checked") && this.state.importInspection.programDraft) {
      this.populateProgramForm(this.state.importInspection.programDraft);
    }
    this.bannerElement.text("Modelagem carregada a partir da tabela existente. Revise o rascunho antes de salvar.");
  };

  ProgramBuilder.prototype.handleImportDatabaseTable = function() {
    const payload = this.collectDatabaseImportPayload();
    if (!payload.tableName) {
      global.CrudUtils.showMessage("Selecione uma tabela para importar.", "warning");
      return;
    }
    if (payload.generateProgramDraft && (!payload.programCode || !payload.module || !payload.screenId)) {
      global.CrudUtils.showMessage("Para gerar o rascunho CRUD informe modulo, codigo do programa e screen ID.", "warning");
      return;
    }
    this.http.request({
      url: "/api/admin/program-builder/database/import",
      method: "POST",
      data: payload
    }).then(function(response) {
      this.state.importInspection = response || null;
      this.refreshBootstrap().then(function() {
        this.populateEntityForm(response.entity || {}, response.entityVersions || []);
        if (response.programVersion && response.programVersion.programCode) {
          this.refreshProgramVersions(response.programVersion.programCode);
        }
        global.CrudUtils.showMessage(response.programDraftGenerated ? "Tabela importada com entidade e rascunho CRUD." : "Tabela importada para a modelagem.", "success");
        this.bannerElement.text("Importacao concluida. Revise a entidade e publique o programa quando estiver pronto.");
      }.bind(this));
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel importar a tabela.");
    }.bind(this));
  };

  ProgramBuilder.prototype.renderExternalJsonImportPanel = function(form) {
    this.externalJsonImportPanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Importar JSON externo</h3><p>Cole o payload gerado fora do sistema, valide no backend e carregue a modelagem para revisao antes de salvar.</p></div>").appendTo(this.externalJsonImportPanel);

    const importForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.externalJsonImportPanel);
    const payloadField = this.appendField(importForm, "Payload do construtor");
    this.externalJsonPayloadInput = $("<textarea rows=\"12\" class=\"program-builder-large-textarea\"></textarea>").appendTo(payloadField).kendoTextArea({
      inputMode: "text",
      placeholder: "{\n  \"entityDraft\": {...},\n  \"programDraft\": {...}\n}"
    }).data("kendoTextArea");

    const actions = $("<div class=\"program-builder-fields-header\"></div>").appendTo(importForm);
    $("<span></span>").text("Fluxo externo").appendTo(actions);
    $("<button type=\"button\"></button>").text("Validar").appendTo(actions).kendoButton({
      icon: "check",
      click: this.handleValidateExternalJson.bind(this)
    });
    $("<button type=\"button\"></button>").text("Carregar na modelagem").appendTo(actions).kendoButton({
      icon: "download",
      click: this.handleApplyExternalJsonDraft.bind(this)
    });

    this.externalJsonSummary = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(importForm);
    this.externalJsonSummary.text("Nenhum payload externo validado nesta sessao.");
    this.externalJsonDiagnostics = $("<div class=\"program-builder-import-diagnostics\"></div>").appendTo(importForm);
  };

  ProgramBuilder.prototype.openExternalJsonImportDialog = function() {
    this.activateEditorTab(1);
    if (this.externalJsonPayloadInput && this.externalJsonPayloadInput.focus) {
      this.externalJsonPayloadInput.focus();
    }
  };

  ProgramBuilder.prototype.collectExternalJsonPayload = function() {
    const rawText = String(this.externalJsonPayloadInput.value() || "").trim();
    if (!rawText) {
      throw new Error("Cole o JSON externo antes de validar.");
    }
    let parsed;
    try {
      parsed = JSON.parse(rawText);
    } catch (_) {
      throw new Error("O JSON externo esta invalido.");
    }
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      throw new Error("O payload externo deve ser um objeto JSON.");
    }
    return {
      rawText: rawText,
      payload: parsed
    };
  };

  ProgramBuilder.prototype.handleValidateExternalJson = function() {
    let payload;
    try {
      payload = this.collectExternalJsonPayload();
    } catch (error) {
      this.state.externalImportInspection = null;
      this.renderExternalJsonSummary();
      global.CrudUtils.showMessage(error.message || "JSON externo invalido.", "warning");
      return Promise.resolve(null);
    }

    return this.http.request({
      url: "/api/admin/program-builder/external/validate",
      method: "POST",
      data: {
        payload: payload.payload
      }
    }).then(function(response) {
      this.state.externalImportInspection = response || null;
      this.renderExternalJsonSummary(response || {});
      global.CrudUtils.showMessage("JSON externo validado.", "success");
      return response;
    }.bind(this)).catch(function(error) {
      this.state.externalImportInspection = null;
      this.renderExternalJsonSummary();
      this.handleError(error, "Nao foi possivel validar o JSON externo.");
      return null;
    }.bind(this));
  };

  ProgramBuilder.prototype.handleApplyExternalJsonDraft = function() {
    if (!this.state.externalImportInspection || !this.state.externalImportInspection.readyToApply) {
      return this.handleValidateExternalJson().then(function(response) {
        if (response && response.readyToApply) {
          this.handleApplyExternalJsonDraft();
        }
      }.bind(this));
    }

    const inspection = this.state.externalImportInspection;
    if (inspection.entityDraft) {
      this.populateEntityForm(inspection.entityDraft, []);
    }
    if (inspection.programDraft) {
      this.populateProgramForm(inspection.programDraft);
    }
    this.renderDefinition(inspection.generatedDefinition || (inspection.programDraft && inspection.programDraft.generatedDefinition) || {});
    this.previewFooter.text("Payload externo validado no backend e carregado para revisao local.");
    this.bannerElement.text("Modelagem preenchida a partir do JSON externo. Revise, salve a entidade e depois gere/publice o programa.");
  };

  ProgramBuilder.prototype.renderExternalJsonSummary = function(response) {
    const inspection = response || this.state.externalImportInspection || null;
    const summary = this.externalJsonSummary;
    const diagnosticsHost = this.externalJsonDiagnostics;
    if (!summary || !diagnosticsHost) {
      return;
    }

    diagnosticsHost.empty();
    if (!inspection) {
      summary.text("Nenhum payload externo validado nesta sessao.");
      return;
    }

    const diagnostics = Array.isArray(inspection.diagnostics) ? inspection.diagnostics : [];
    const entityDraft = inspection.entityDraft || null;
    const programDraft = inspection.programDraft || null;
    const parts = [];
    if (inspection.readyToApply) {
      parts.push("Payload aceito para revisao.");
    }
    if (entityDraft) {
      parts.push("Entidade: " + String(entityDraft.code || "") + ".");
      parts.push("Campos: " + String((entityDraft.fields || []).length) + ".");
    }
    if (programDraft) {
      parts.push("Programa: " + String(programDraft.programCode || "") + ".");
    }
    if (!parts.length) {
      parts.push("Payload externo recebido.");
    }
    summary.text(parts.join(" "));

    if (!diagnostics.length) {
      $("<div class=\"program-builder-import-diagnostic is-info\"></div>").text("Sem diagnosticos adicionais. O payload esta pronto para revisao no construtor.").appendTo(diagnosticsHost);
      return;
    }

    diagnostics.forEach(function(item) {
      const level = String(item.level || "info");
      $("<div class=\"program-builder-import-diagnostic\"></div>")
        .addClass("is-" + level)
        .text(String(item.message || ""))
        .appendTo(diagnosticsHost);
    });
  };

  ProgramBuilder.prototype.openIntegrationAssistantDialog = function() {
    if (!this.integrationAssistantWindow) {
      const host = $("<div class=\"program-builder-ai-settings-window\"></div>").appendTo(document.body);
      const form = $("<div class=\"program-builder-ai-settings-form\"></div>").appendTo(host);
      const formatField = this.appendField(form, "Formato de destino");
      this.integrationAssistantFormatSelect = $("<input>").appendTo(formatField).kendoDropDownList({
        dataSource: [
          { value: "csv", text: "CSV" },
          { value: "txt_layout", text: "TXT layout" },
          { value: "xml", text: "XML" },
          { value: "entity_copy", text: "Entidade local" },
          { value: "api_json", text: "API JSON" }
        ],
        dataTextField: "text",
        dataValueField: "value",
        value: "csv",
        change: this.renderIntegrationAssistantDraft.bind(this)
      }).data("kendoDropDownList");
      const payloadField = this.appendField(form, "Esqueleto gerado");
      this.integrationAssistantPayload = $("<textarea rows=\"16\" class=\"program-builder-large-textarea\"></textarea>").appendTo(payloadField).kendoTextArea({ inputMode: "text" }).data("kendoTextArea");
      const actions = $("<div class=\"program-builder-fields-header\"></div>").appendTo(form);
      $("<span></span>").text("Fluxo sugerido").appendTo(actions);
      $("<button type=\"button\"></button>").text("Atualizar").appendTo(actions).kendoButton({
        icon: "arrow-rotate-cw",
        click: this.renderIntegrationAssistantDraft.bind(this)
      });
      $("<button type=\"button\"></button>").text("Copiar JSON").appendTo(actions).kendoButton({
        icon: "copy",
        click: function() {
          global.CrudUtils.copyToClipboard(this.integrationAssistantPayload.value() || "").then(function(ok) {
            global.CrudUtils.showMessage(ok ? "JSON copiado." : "Nao foi possivel copiar o JSON.", ok ? "success" : "warning");
          });
        }.bind(this)
      });
      $("<button type=\"button\"></button>").text("Abrir Integracoes").appendTo(actions).kendoButton({
        icon: "window",
        click: function() {
          if (global.CrudUtils && typeof global.CrudUtils.runTechnicalPropertyAction === "function") {
            global.CrudUtils.runTechnicalPropertyAction({ type: "openScreen", screenId: "admin.integracoes" });
          }
        }
      });
      host.kendoWindow({
        title: "Assistente de integracao",
        modal: true,
        width: 820,
        visible: false,
        actions: ["Close"]
      });
      this.integrationAssistantWindow = host.data("kendoWindow");
    }
    this.renderIntegrationAssistantDraft();
    this.integrationAssistantWindow.center().open();
  };

  ProgramBuilder.prototype.renderIntegrationAssistantDraft = function() {
    if (!this.integrationAssistantPayload) {
      return;
    }
    const format = this.integrationAssistantFormatSelect ? String(this.integrationAssistantFormatSelect.value() || "csv") : "csv";
    this.integrationAssistantPayload.value(JSON.stringify(this.buildIntegrationAssistantDraft(format), null, 2));
  };

  ProgramBuilder.prototype.buildIntegrationAssistantDraft = function(format) {
    const entity = this.collectEntityPayload();
    const entityCode = String(entity.code || "").trim() || "entidade";
    const alias = entityCode;
    const fields = Array.isArray(entity.fields) ? entity.fields : [];
    const dataFields = fields.filter(function(field) {
      return !field.primaryKey;
    });
    const firstFields = dataFields.slice(0, 5);
    const code = entityCode + "_" + format;
    const base = {
      code: code,
      name: "Integracao " + entityCode + " " + format,
      direction: "export",
      targetType: "file",
      targetCode: code,
      format: format,
      mapping: {
        source: {
          type: "entity",
          entityCode: entityCode,
          alias: alias,
          mode: "list",
          limit: 200
        },
        destination: {
          type: format === "entity_copy" || format === "api_json" ? "entity" : "file",
          entityCode: format === "entity_copy" ? entityCode : "",
          operation: format === "entity_copy" ? "upsert" : "create",
          fileFormat: format === "api_json" || format === "entity_copy" ? undefined : format,
          fileNamePattern: code
        },
        fieldMappings: firstFields.map(function(field) {
          return {
            sourcePath: field.code,
            targetPath: field.code
          };
        }),
        options: {
          previewLimit: 20
        }
      }
    };
    if (format === "csv") {
      base.mapping.destination.columns = firstFields.map(function(field) {
        return { header: field.label || field.code, sourcePath: field.code };
      });
    } else if (format === "txt_layout") {
      base.mapping.destination.recordLayouts = [{
        nodeType: "record",
        recordType: "REG",
        label: entity.name || entityCode,
        sourceAlias: alias,
        lineMode: "delimited",
        separator: "|",
        fields: [{ constant: "REG" }].concat(firstFields.map(function(field) {
          return { sourcePath: field.code };
        }))
      }];
    } else if (format === "xml") {
      base.mapping.destination.rootName = entityCode + "s";
      base.mapping.destination.itemName = entityCode;
      base.mapping.destination.columns = firstFields.map(function(field) {
        return { targetName: field.code, sourcePath: field.code };
      });
      base.mapping.destination.xmlLayouts = [{
        name: entityCode,
        label: entity.name || entityCode,
        sourceAlias: alias,
        fields: firstFields.map(function(field) {
          return { name: field.code, sourcePath: field.code };
        }),
        children: []
      }];
    } else if (format === "entity_copy") {
      base.targetType = "entity";
      base.mapping.destination.type = "entity";
      base.mapping.destination.entityCode = entityCode;
      base.mapping.destination.matchBy = [];
    } else if (format === "api_json") {
      base.targetType = "entity";
      base.mapping.destination.type = "entity";
      base.mapping.destination.entityCode = entityCode;
    }
    return base;
  };

  ProgramBuilder.prototype.openAiSettingsDialog = function() {
    if (!this.aiSettingsWindow) {
      this.createAiSettingsWindow();
    }
    this.loadAiSettings().then(function() {
      this.syncAiSettingsState();
      this.aiSettingsWindow.center().open();
    }.bind(this));
  };

  ProgramBuilder.prototype.openApiSourceManagerDialog = function() {
    if (!this.apiSourceManagerWindow) {
      this.createApiSourceManagerWindow();
    }
    this.refreshApiSourceSelectors();
    this.apiSourceManagerWindow.center().open();
  };

  ProgramBuilder.prototype.createApiSourceManagerWindow = function() {
    const host = $("<div class=\"program-builder-ai-settings-window\"></div>").appendTo(document.body);
    const form = $("<div class=\"program-builder-ai-settings-form\"></div>").appendTo(host);

    const existingField = this.appendField(form, "API cadastrada");
    this.apiSourceManagerSelect = $("<input>").appendTo(existingField).kendoDropDownList({
      dataSource: [],
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Nova API",
      change: function() {
        const code = String(this.apiSourceManagerSelect.value() || "").trim();
        if (!code) {
          this.populateApiSourceManagerForm(null);
          return;
        }
        this.loadApiSourceDefinition(code).then(function(apiSource) {
          this.populateApiSourceManagerForm(apiSource);
        }.bind(this));
      }.bind(this)
    }).data("kendoDropDownList");

    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.apiSourceManagerCodeInput = this.createTextField(splitA, "Codigo");
    this.apiSourceManagerNameInput = this.createTextField(splitA, "Nome");
    const providerField = this.appendField(splitA, "Provedor");
    this.apiSourceManagerProviderTypeSelect = $("<input>").appendTo(providerField).kendoDropDownList({
      dataSource: [
        { value: "generic", text: "API generica" },
        { value: "odoo", text: "Odoo RPC" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "generic",
      change: this.syncApiSourceManagerProviderState.bind(this)
    }).data("kendoDropDownList");

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    const authField = this.appendField(splitB, "Autenticacao");
    this.apiSourceManagerAuthModeSelect = $("<input>").appendTo(authField).kendoDropDownList({
      dataSource: [
        { value: "none", text: "Nenhuma" },
        { value: "header_static", text: "Header estatico" },
        { value: "bearer_static", text: "Bearer estatico" },
        { value: "basic_static", text: "Basic estatico" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "none"
    }).data("kendoDropDownList");
    this.apiSourceManagerBaseUrlInput = this.createTextField(splitB, "Base URL");

    const splitC = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.apiSourceManagerOpenApiUrlInput = this.createTextField(splitC, "OpenAPI/Swagger URL");
    this.apiSourceManagerTimeoutInput = this.createTextField(splitC, "Timeout (segundos)");

    const statusField = this.appendField(form, "Status");
    this.apiSourceManagerStatusSelect = $("<input>").appendTo(statusField).kendoDropDownList({
      dataSource: [{ value: "active", text: "Ativo" }, { value: "inactive", text: "Inativo" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "active"
    }).data("kendoDropDownList");

    const authHeadersField = this.appendField(form, "Headers fixos (JSON objeto)");
    this.apiSourceManagerAuthHeadersInput = $("<textarea rows=\"4\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(authHeadersField);

    this.apiSourceManagerGenericPanel = $("<div class=\"program-builder-subpanel\"></div>").appendTo(form);
    const operationsField = this.appendField(this.apiSourceManagerGenericPanel, "Operacoes (JSON array)");
    this.apiSourceManagerOperationsInput = $("<textarea rows=\"14\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(operationsField);

    this.apiSourceManagerOdooPanel = $("<div class=\"program-builder-subpanel\"></div>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Configuracao Odoo</h3><p>Use os dados da instancia, do banco e do modelo que sera consumido por XML-RPC ou JSON-RPC.</p></div>").appendTo(this.apiSourceManagerOdooPanel);
    const odooForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.apiSourceManagerOdooPanel);
    const odooSplitA = $("<div class=\"program-builder-split\"></div>").appendTo(odooForm);
    const odooTransportField = this.appendField(odooSplitA, "Transporte");
    this.apiSourceManagerOdooTransportSelect = $("<input>").appendTo(odooTransportField).kendoDropDownList({
      dataSource: [{ value: "xmlrpc", text: "XML-RPC" }, { value: "jsonrpc", text: "JSON-RPC" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "xmlrpc"
    }).data("kendoDropDownList");
    this.apiSourceManagerOdooDatabaseInput = this.createTextField(odooSplitA, "Banco");
    const odooSplitB = $("<div class=\"program-builder-split\"></div>").appendTo(odooForm);
    this.apiSourceManagerOdooLoginInput = this.createTextField(odooSplitB, "Login");
    const odooSecretModeField = this.appendField(odooSplitB, "Segredo");
    this.apiSourceManagerOdooSecretModeSelect = $("<input>").appendTo(odooSecretModeField).kendoDropDownList({
      dataSource: [{ value: "password", text: "Senha" }, { value: "api_key", text: "API Key" }],
      dataTextField: "text",
      dataValueField: "value",
      value: "password"
    }).data("kendoDropDownList");
    this.apiSourceManagerOdooSecretValueInput = this.createTextField(odooForm, "Senha/API Key");
    this.apiSourceManagerOdooModelInput = this.createTextField(odooForm, "Modelo Odoo");
    const odooSplitC = $("<div class=\"program-builder-split\"></div>").appendTo(odooForm);
    this.apiSourceManagerOdooOrderInput = this.createTextField(odooSplitC, "Ordenacao padrao");
    this.apiSourceManagerOdooLimitInput = this.createTextField(odooSplitC, "Limite padrao");
    const odooContextField = this.appendField(odooForm, "Contexto padrao (JSON objeto)");
    this.apiSourceManagerOdooContextInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(odooContextField);
    const odooDomainField = this.appendField(odooForm, "Dominio padrao (JSON array)");
    this.apiSourceManagerOdooDomainInput = $("<textarea rows=\"3\" class=\"program-builder-mini-textarea\"></textarea>").appendTo(odooDomainField);
    this.apiSourceManagerDiagnostics = $("<div class=\"program-builder-import-diagnostics\"></div>").appendTo(form);

    const footer = $("<div class=\"program-builder-ai-window-footer\"></div>").appendTo(host);
    $("<button type=\"button\"></button>").text("Importar OpenAPI").appendTo(footer).kendoButton({
      icon: "download",
      click: this.handleImportOpenApi.bind(this)
    });
    $("<button type=\"button\"></button>").text("Testar conexao").appendTo(footer).kendoButton({
      icon: "link-horizontal",
      click: this.handleTestOdooConnection.bind(this)
    });
    $("<button type=\"button\"></button>").text("Ler metadados do modelo").appendTo(footer).kendoButton({
      icon: "table",
      click: this.handleReadOdooModelMetadata.bind(this)
    });
    $("<button type=\"button\"></button>").text("Salvar API").appendTo(footer).kendoButton({
      themeColor: "primary",
      icon: "save",
      click: this.handleSaveApiSource.bind(this)
    });

    host.kendoWindow({
      title: "Cadastro de APIs externas",
      modal: true,
      visible: false,
      width: 820,
      height: 760,
      resizable: true,
      actions: ["Close"]
    });
    this.apiSourceManagerWindow = host.data("kendoWindow");
    this.populateApiSourceManagerForm(null);
    this.syncApiSourceManagerProviderState();
  };

  ProgramBuilder.prototype.populateApiSourceManagerForm = function(apiSource) {
    const item = apiSource || {};
    if (this.apiSourceManagerSelect) {
      this.apiSourceManagerSelect.value(item.code || "");
    }
    this.apiSourceManagerCodeInput.value(item.code || "");
    this.apiSourceManagerNameInput.value(item.name || "");
    this.apiSourceManagerProviderTypeSelect.value(item.providerType || "generic");
    this.apiSourceManagerAuthModeSelect.value(item.authMode || "none");
    this.apiSourceManagerBaseUrlInput.value(item.baseUrl || "");
    this.apiSourceManagerOpenApiUrlInput.value(item.openapiUrl || "");
    this.apiSourceManagerTimeoutInput.value(item.timeoutSeconds != null ? String(item.timeoutSeconds) : "20");
    this.apiSourceManagerStatusSelect.value(item.status || "active");
    this.apiSourceManagerAuthHeadersInput.val(item.authHeaders ? JSON.stringify(item.authHeaders, null, 2) : "");
    this.apiSourceManagerOperationsInput.val(item.operations ? JSON.stringify(item.operations, null, 2) : "[]");
    const odoo = item.odoo || {};
    this.apiSourceManagerOdooTransportSelect.value(odoo.transport || "xmlrpc");
    this.apiSourceManagerOdooDatabaseInput.value(odoo.database || "");
    this.apiSourceManagerOdooLoginInput.value(odoo.login || "");
    this.apiSourceManagerOdooSecretModeSelect.value(odoo.secretMode || "password");
    this.apiSourceManagerOdooSecretValueInput.value(odoo.secretValue || "");
    this.apiSourceManagerOdooModelInput.value(odoo.model || "");
    this.apiSourceManagerOdooOrderInput.value(odoo.defaultOrder || "");
    this.apiSourceManagerOdooLimitInput.value(odoo.defaultLimit != null ? String(odoo.defaultLimit) : "80");
    this.apiSourceManagerOdooContextInput.val(odoo.defaultContext ? JSON.stringify(odoo.defaultContext, null, 2) : "");
    this.apiSourceManagerOdooDomainInput.val(odoo.defaultDomain ? JSON.stringify(odoo.defaultDomain, null, 2) : "[]");
    if (this.apiSourceManagerDiagnostics) {
      this.apiSourceManagerDiagnostics.empty();
    }
    this.syncApiSourceManagerProviderState();
  };

  ProgramBuilder.prototype.collectApiSourceManagerPayload = function() {
    const providerType = String(this.apiSourceManagerProviderTypeSelect.value() || "generic");
    return {
      code: String(this.apiSourceManagerCodeInput.value() || "").trim(),
      name: String(this.apiSourceManagerNameInput.value() || "").trim(),
      providerType: providerType,
      authMode: String(this.apiSourceManagerAuthModeSelect.value() || "none"),
      baseUrl: String(this.apiSourceManagerBaseUrlInput.value() || "").trim(),
      openapiUrl: String(this.apiSourceManagerOpenApiUrlInput.value() || "").trim(),
      timeoutSeconds: String(this.apiSourceManagerTimeoutInput.value() || "").trim(),
      status: String(this.apiSourceManagerStatusSelect.value() || "active"),
      authHeaders: this.parseJsonObjectText(this.apiSourceManagerAuthHeadersInput.val(), "API_SOURCE_AUTH_HEADERS_INVALID"),
      operations: this.parseJsonArrayText(this.apiSourceManagerOperationsInput.val(), "API_SOURCE_OPERATIONS_INVALID"),
      odoo: providerType === "odoo" ? {
        transport: String(this.apiSourceManagerOdooTransportSelect.value() || "xmlrpc"),
        database: String(this.apiSourceManagerOdooDatabaseInput.value() || "").trim(),
        login: String(this.apiSourceManagerOdooLoginInput.value() || "").trim(),
        secretMode: String(this.apiSourceManagerOdooSecretModeSelect.value() || "password"),
        secretValue: String(this.apiSourceManagerOdooSecretValueInput.value() || "").trim(),
        model: String(this.apiSourceManagerOdooModelInput.value() || "").trim(),
        defaultOrder: String(this.apiSourceManagerOdooOrderInput.value() || "").trim(),
        defaultLimit: String(this.apiSourceManagerOdooLimitInput.value() || "").trim(),
        defaultContext: this.parseJsonObjectText(this.apiSourceManagerOdooContextInput.val(), "ODOO_CONTEXT_INVALID"),
        defaultDomain: this.parseJsonArrayText(this.apiSourceManagerOdooDomainInput.val(), "ODOO_DOMAIN_INVALID")
      } : null
    };
  };

  ProgramBuilder.prototype.syncApiSourceManagerProviderState = function() {
    const providerType = String(this.apiSourceManagerProviderTypeSelect && this.apiSourceManagerProviderTypeSelect.value() || "generic");
    if (this.apiSourceManagerGenericPanel) {
      this.apiSourceManagerGenericPanel.toggle(providerType === "generic");
    }
    if (this.apiSourceManagerOdooPanel) {
      this.apiSourceManagerOdooPanel.toggle(providerType === "odoo");
    }
    if (this.apiSourceManagerOpenApiUrlInput) {
      const openApiInput = this.apiSourceManagerOpenApiUrlInput.input || this.apiSourceManagerOpenApiUrlInput.element || this.apiSourceManagerOpenApiUrlInput;
      openApiInput.prop("disabled", providerType !== "generic");
    }
    if (this.apiSourceManagerAuthModeSelect && typeof this.apiSourceManagerAuthModeSelect.enable === "function") {
      this.apiSourceManagerAuthModeSelect.enable(providerType === "generic");
    }
    if (this.apiSourceManagerAuthHeadersInput) {
      this.apiSourceManagerAuthHeadersInput.prop("disabled", providerType !== "generic");
    }
  };

  ProgramBuilder.prototype.handleSaveApiSource = function() {
    let payload;
    try {
      payload = this.collectApiSourceManagerPayload();
    } catch (error) {
      this.handleError(error, "Nao foi possivel ler o cadastro da API.");
      return Promise.resolve(null);
    }
    return this.http.request({
      url: "/api/admin/program-builder/api-sources",
      method: "POST",
      data: payload
    }).then(function(response) {
      const apiSource = response && response.apiSource ? response.apiSource : null;
      if (!apiSource) {
        return null;
      }
      this.state.apiSourceDetails[apiSource.code] = apiSource;
      this.upsertApiSourceSummary(apiSource);
      this.refreshApiSourceSelectors();
      this.populateApiSourceManagerForm(apiSource);
      if (this.apiCatalogSourceSelect) {
        this.apiCatalogSourceSelect.value(apiSource.code);
        this.handleApiSourceSelectionChange();
      }
      global.CrudUtils.showMessage("Cadastro de API salvo.", "success");
      return apiSource;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel salvar o cadastro da API.");
      return null;
    }.bind(this));
  };

  ProgramBuilder.prototype.handleImportOpenApi = function() {
    if (String(this.apiSourceManagerProviderTypeSelect.value() || "generic") !== "generic") {
      global.CrudUtils.showMessage("Importacao OpenAPI fica disponivel apenas para APIs genericas.", "warning");
      return Promise.resolve(null);
    }
    const payload = {
      providerType: String(this.apiSourceManagerProviderTypeSelect.value() || "generic"),
      openapiUrl: String(this.apiSourceManagerOpenApiUrlInput.value() || "").trim(),
      code: String(this.apiSourceManagerCodeInput.value() || "").trim(),
      name: String(this.apiSourceManagerNameInput.value() || "").trim(),
      baseUrl: String(this.apiSourceManagerBaseUrlInput.value() || "").trim()
    };
    return this.http.request({
      url: "/api/admin/program-builder/api-sources/import-openapi",
      method: "POST",
      data: payload
    }).then(function(response) {
      const draft = response && response.apiSourceDraft ? response.apiSourceDraft : null;
      const diagnostics = Array.isArray(response && response.diagnostics) ? response.diagnostics : [];
      if (draft) {
        this.populateApiSourceManagerForm(draft);
      }
      this.renderApiSourceManagerDiagnostics(diagnostics);
      global.CrudUtils.showMessage("Documento OpenAPI importado para revisao.", "success");
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel importar o documento OpenAPI.");
      return null;
    }.bind(this));
  };

  ProgramBuilder.prototype.handleTestOdooConnection = function() {
    let payload;
    try {
      payload = this.collectApiSourceManagerPayload();
    } catch (error) {
      this.handleError(error, "Nao foi possivel ler o cadastro da API.");
      return Promise.resolve(null);
    }
    if (String(payload.providerType || "") !== "odoo") {
      global.CrudUtils.showMessage("O teste de conexao desta etapa e exclusivo para o provedor Odoo.", "warning");
      return Promise.resolve(null);
    }
    return this.http.request({
      url: "/api/admin/program-builder/api-sources/odoo/test-connection",
      method: "POST",
      data: payload
    }).then(function(response) {
      this.renderApiSourceManagerDiagnostics(Array.isArray(response && response.diagnostics) ? response.diagnostics : []);
      global.CrudUtils.showMessage("Conexao Odoo validada.", "success");
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel validar a conexao Odoo.");
      return null;
    }.bind(this));
  };

  ProgramBuilder.prototype.handleReadOdooModelMetadata = function() {
    let payload;
    try {
      payload = this.collectApiSourceManagerPayload();
    } catch (error) {
      this.handleError(error, "Nao foi possivel ler o cadastro da API.");
      return Promise.resolve(null);
    }
    if (String(payload.providerType || "") !== "odoo") {
      global.CrudUtils.showMessage("A leitura de metadados do modelo nesta etapa e exclusiva para o provedor Odoo.", "warning");
      return Promise.resolve(null);
    }
    return this.http.request({
      url: "/api/admin/program-builder/api-sources/odoo/model-metadata",
      method: "POST",
      data: payload
    }).then(function(response) {
      const diagnostics = Array.isArray(response && response.diagnostics) ? response.diagnostics : [];
      const fields = Array.isArray(response && response.fields) ? response.fields : [];
      diagnostics.unshift({
        level: "info",
        message: "Modelo " + String(response && response.model || payload.odoo && payload.odoo.model || "") + " com " + fields.length + " campo(s) disponivel(is) para importar."
      });
      this.renderApiSourceManagerDiagnostics(diagnostics);
      this.apiSourceManagerLastOdooFields = fields;
      global.CrudUtils.showMessage("Metadados do modelo Odoo carregados.", "success");
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel ler os metadados do modelo Odoo.");
      return null;
    }.bind(this));
  };

  ProgramBuilder.prototype.renderApiSourceManagerDiagnostics = function(diagnostics) {
    if (!this.apiSourceManagerDiagnostics) {
      return;
    }
    this.apiSourceManagerDiagnostics.empty();
    (diagnostics || []).forEach(function(item) {
      $("<div class=\"program-builder-import-diagnostic\"></div>")
        .addClass("is-" + String(item.level || "info"))
        .text(String(item.message || ""))
        .appendTo(this.apiSourceManagerDiagnostics);
    }.bind(this));
  };

  ProgramBuilder.prototype.upsertApiSourceSummary = function(apiSource) {
    const summary = {
      id: apiSource.id,
      code: apiSource.code,
      name: apiSource.name,
      providerType: apiSource.providerType || "generic",
      authMode: apiSource.authMode,
      baseUrl: apiSource.baseUrl,
      openapiUrl: apiSource.openapiUrl,
      status: apiSource.status,
      operationsCount: Array.isArray(apiSource.operations) ? apiSource.operations.length : (apiSource.operationsCount || 0),
      createdAt: apiSource.createdAt,
      updatedAt: apiSource.updatedAt
    };
    const current = Array.isArray(this.state.apiSources) ? this.state.apiSources.slice() : [];
    const index = current.findIndex(function(item) { return item.code === summary.code; });
    if (index >= 0) {
      current[index] = summary;
    } else {
      current.push(summary);
    }
    current.sort(function(left, right) {
      return String(left.name || "").localeCompare(String(right.name || ""), "pt-BR");
    });
    this.state.apiSources = current;
  };

  ProgramBuilder.prototype.createAiSettingsWindow = function() {
    const host = $("<div class=\"program-builder-ai-settings-window\"></div>").appendTo(document.body);
    const form = $("<div class=\"program-builder-ai-settings-form\"></div>").appendTo(host);

    const enabledField = this.appendField(form, "Assistente habilitado");
    this.aiEnabledInput = $("<input type=\"checkbox\">").appendTo(enabledField);
    this.aiEnabledInput.kendoCheckBox();

    const providerField = this.appendField(form, "Provedor");
    this.aiProviderInput = $("<select></select>").appendTo(providerField).kendoDropDownList({
      dataTextField: "text",
      dataValueField: "value",
      dataSource: [
        { value: "mock", text: "Mock local" },
        { value: "openai_compatible", text: "OpenAI compatível" }
      ],
      change: this.syncAiSettingsState.bind(this)
    }).data("kendoDropDownList");

    const agentField = this.appendField(form, "Nome do agente");
    this.aiAgentNameInput = $("<input>").appendTo(agentField).kendoTextBox({
      placeholder: "Assistente do construtor"
    }).data("kendoTextBox");

    this.aiProviderFields = $("<div class=\"program-builder-ai-settings-provider\"></div>").appendTo(form);
    const baseUrlField = this.appendField(this.aiProviderFields, "Base URL");
    this.aiBaseUrlInput = $("<input>").appendTo(baseUrlField).kendoTextBox({
      placeholder: "https://api.openai.com/v1"
    }).data("kendoTextBox");

    const modelField = this.appendField(this.aiProviderFields, "Modelo");
    this.aiModelInput = $("<input>").appendTo(modelField).kendoTextBox({
      placeholder: "gpt-4.1-mini"
    }).data("kendoTextBox");

    const tokenField = this.appendField(this.aiProviderFields, "Token");
    this.aiApiTokenInput = $("<input type=\"password\">").appendTo(tokenField).kendoTextBox({
      placeholder: "Informe o token"
    }).data("kendoTextBox");
    this.aiApiTokenHint = $("<small class=\"program-builder-inline-hint\"></small>").appendTo(tokenField);
    this.aiClearTokenInput = $("<input type=\"checkbox\">").appendTo(tokenField);
    $("<label class=\"program-builder-inline-check\"></label>").append(this.aiClearTokenInput).append("<span>Limpar token salvo</span>").appendTo(tokenField);
    this.aiClearTokenInput.kendoCheckBox({
      change: this.syncAiSettingsState.bind(this)
    });

    const transcriptionField = this.appendField(this.aiProviderFields, "Transcricao habilitada");
    this.aiTranscriptionEnabledInput = $("<input type=\"checkbox\">").appendTo(transcriptionField);
    this.aiTranscriptionEnabledInput.kendoCheckBox({
      change: this.syncAiSettingsState.bind(this)
    });

    const transcriptionModelField = this.appendField(this.aiProviderFields, "Modelo de transcricao");
    this.aiTranscriptionModelInput = $("<input>").appendTo(transcriptionModelField).kendoTextBox({
      placeholder: "gpt-4o-mini-transcribe"
    }).data("kendoTextBox");

    const footer = $("<div class=\"program-builder-ai-window-footer\"></div>").appendTo(host);
    $("<button type=\"button\"></button>").text("Salvar").appendTo(footer).kendoButton({
      themeColor: "primary",
      icon: "save",
      click: this.handleSaveAiSettings.bind(this)
    });

    host.kendoWindow({
      title: "Configurar assistente de IA",
      modal: true,
      visible: false,
      width: 640,
      resizable: true,
      actions: ["Close"]
    });
    this.aiSettingsWindow = host.data("kendoWindow");
  };

  ProgramBuilder.prototype.loadAiSettings = function() {
    return this.http.request({
      url: "/api/admin/program-builder/ai/settings",
      method: "GET"
    }).then(function(response) {
      const settings = response || {};
      this.state.aiSettings = settings;
      if (this.aiEnabledInput) {
        this.aiEnabledInput.prop("checked", settings.enabled === true);
      }
      if (this.aiProviderInput) {
        this.aiProviderInput.value(settings.provider || "mock");
      }
      if (this.aiAgentNameInput) {
        this.aiAgentNameInput.value(settings.agentName || "");
      }
      if (this.aiBaseUrlInput) {
        this.aiBaseUrlInput.value(settings.baseUrl || "");
      }
      if (this.aiModelInput) {
        this.aiModelInput.value(settings.model || "");
      }
      if (this.aiApiTokenInput) {
        this.aiApiTokenInput.value(settings.apiTokenMaskedValue || "");
      }
      if (this.aiClearTokenInput) {
        this.aiClearTokenInput.prop("checked", false);
      }
      if (this.aiTranscriptionEnabledInput) {
        this.aiTranscriptionEnabledInput.prop("checked", settings.transcriptionEnabled === true);
      }
      if (this.aiTranscriptionModelInput) {
        this.aiTranscriptionModelInput.value(settings.transcriptionModel || "");
      }
      if (this.aiApiTokenHint) {
        this.aiApiTokenHint.text(settings.apiTokenConfigured ? "Token configurado. Informe um novo valor apenas se quiser substituir." : "Nenhum token configurado.");
      }
      this.syncAiSettingsState();
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel carregar as configuracoes da IA.");
      throw error;
    }.bind(this));
  };

  ProgramBuilder.prototype.syncAiSettingsState = function() {
    const provider = String(this.aiProviderInput && this.aiProviderInput.value() || "mock");
    const clearToken = !!(this.aiClearTokenInput && this.aiClearTokenInput.is(":checked"));
    const showProviderFields = provider === "openai_compatible";
    if (this.aiProviderFields) {
      this.aiProviderFields.toggleClass("is-hidden", !showProviderFields);
    }
    if (this.aiApiTokenHint) {
      if (clearToken) {
        this.aiApiTokenHint.text("O token salvo sera removido quando voce salvar.");
      }
    }
  };

  ProgramBuilder.prototype.handleSaveAiSettings = function() {
    const payload = {
      enabled: !!(this.aiEnabledInput && this.aiEnabledInput.is(":checked")),
      provider: String(this.aiProviderInput && this.aiProviderInput.value() || "mock"),
      agentName: String(this.aiAgentNameInput && this.aiAgentNameInput.value() || ""),
      baseUrl: String(this.aiBaseUrlInput && this.aiBaseUrlInput.value() || ""),
      model: String(this.aiModelInput && this.aiModelInput.value() || ""),
      apiToken: String(this.aiApiTokenInput && this.aiApiTokenInput.value() || ""),
      clearApiToken: !!(this.aiClearTokenInput && this.aiClearTokenInput.is(":checked")),
      transcriptionEnabled: !!(this.aiTranscriptionEnabledInput && this.aiTranscriptionEnabledInput.is(":checked")),
      transcriptionModel: String(this.aiTranscriptionModelInput && this.aiTranscriptionModelInput.value() || "")
    };

    return this.http.request({
      url: "/api/admin/program-builder/ai/settings",
      method: "POST",
      data: payload
    }).then(function(response) {
      this.state.aiSettings = response || null;
      if (this.aiSettingsWindow) {
        this.aiSettingsWindow.close();
      }
      global.CrudUtils.showMessage("Configuracoes da IA salvas.", "success");
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel salvar as configuracoes da IA.");
      return null;
    }.bind(this));
  };

  ProgramBuilder.prototype.openAiAssistantDialog = function() {
    if (!$.fn.kendoChat) {
      global.CrudUtils.showMessage("Componente de chat do Kendo UI indisponivel.", "error");
      return;
    }
    if (!this.aiAssistantWindow) {
      this.createAiAssistantWindow();
    }
    this.loadAiSettings().catch(function() {
      return null;
    }).finally(function() {
      this.aiAssistantWindow.center().open();
      this.startAiAssistantSession();
    }.bind(this));
  };

  ProgramBuilder.prototype.createAiAssistantWindow = function() {
    const host = $("<div class=\"program-builder-ai-window\"></div>").appendTo(document.body);
    const layout = $("<div class=\"program-builder-ai-layout\"></div>").appendTo(host);
    const chatPane = $("<section class=\"program-builder-ai-chat-pane\"></section>").appendTo(layout);
    const sidePane = $("<aside class=\"program-builder-ai-side-pane\"></aside>").appendTo(layout);

    const actions = $("<div class=\"program-builder-ai-chat-actions\"></div>").appendTo(chatPane);
    $("<button type=\"button\"></button>").text("Configurar IA").appendTo(actions).kendoButton({
      icon: "gear",
      click: this.openAiSettingsDialog.bind(this)
    });
    this.aiAudioButton = $("<button type=\"button\"></button>").text("Gravar audio").appendTo(actions).kendoButton({
      icon: "microphone",
      click: this.toggleAiAudioCapture.bind(this)
    }).data("kendoButton");
    this.aiAudioStatus = $("<span class=\"program-builder-inline-hint\"></span>").text("Audio inativo.").appendTo(actions);

    this.aiChatHost = $("<div class=\"program-builder-ai-chat-host\"></div>").appendTo(chatPane);
    const summary = $("<div class=\"program-builder-ai-summary\"></div>").appendTo(sidePane);
    $("<h3></h3>").text("Rascunho atual").appendTo(summary);
    this.aiDraftSummary = $("<div class=\"program-builder-ai-draft-summary\"></div>").appendTo(summary);
    this.aiDraftDiagnostics = $("<div class=\"program-builder-import-diagnostics\"></div>").appendTo(sidePane);

    const footer = $("<div class=\"program-builder-ai-window-footer\"></div>").appendTo(host);
    $("<button type=\"button\"></button>").text("Validar rascunho").appendTo(footer).kendoButton({
      icon: "check",
      click: this.handleFinalizeAiDraft.bind(this)
    });
    $("<button type=\"button\"></button>").text("Carregar na modelagem").appendTo(footer).kendoButton({
      themeColor: "primary",
      icon: "download",
      click: this.handleApplyAiDraft.bind(this)
    });

    host.kendoWindow({
      title: "Assistente IA do construtor",
      modal: false,
      visible: false,
      width: 1080,
      height: 700,
      resizable: true,
      actions: ["Maximize", "Close"],
      close: this.stopAiAudioCapture.bind(this)
    });
    this.aiAssistantWindow = host.data("kendoWindow");

    this.aiChatHost.kendoChat({
      authorId: String(this.state.currentUser && (this.state.currentUser.id || this.state.currentUser.login || this.state.currentUser.email) || "usuario"),
      height: "100%",
      dataSource: [],
      autoBind: true,
      showAvatar: false,
      showUsername: true,
      speechToText: false,
      fileAttachment: false,
      messageActions: [],
      fileActions: [],
      messages: {
        placeholder: "Descreva a tabela, os campos e o modulo desejado..."
      },
      noDataTemplate: function() {
        return "<div class=\"home-chat-empty\">Nenhuma mensagem ainda.</div>";
      },
      sendMessage: function(event) {
        if (event.generating) {
          return;
        }
        const message = event.message || {};
        const text = String(message.text || "").trim();
        if (!text) {
          return;
        }
        this.sendAiAssistantMessage(text);
      }.bind(this)
    });
    this.aiChatWidget = this.aiChatHost.data("kendoChat");
  };

  ProgramBuilder.prototype.startAiAssistantSession = function(force) {
    if (this.state.aiSessionId && !force) {
      return Promise.resolve(this.state.aiSessionId);
    }
    this.state.aiDraftInspection = null;
    this.state.aiHistory = [];
    this.renderAiDraftSummary();
    if (this.aiChatHost) {
      this.aiChatHost.empty();
      this.aiChatHost.kendoChat({
        authorId: String(this.state.currentUser && (this.state.currentUser.id || this.state.currentUser.login || this.state.currentUser.email) || "usuario"),
        height: "100%",
        dataSource: [],
        autoBind: true,
        showAvatar: false,
        showUsername: true,
        speechToText: false,
        fileAttachment: false,
        messageActions: [],
        fileActions: [],
        messages: { placeholder: "Descreva a tabela, os campos e o modulo desejado..." },
        noDataTemplate: function() {
          return "<div class=\"home-chat-empty\">Nenhuma mensagem ainda.</div>";
        },
        sendMessage: function(event) {
          if (event.generating) {
            return;
          }
          const message = event.message || {};
          const text = String(message.text || "").trim();
          if (!text) {
            return;
          }
          this.sendAiAssistantMessage(text);
        }.bind(this)
      });
      this.aiChatWidget = this.aiChatHost.data("kendoChat");
    }
    return this.http.request({
      url: "/api/admin/program-builder/ai/session",
      method: "POST",
      data: {
        context: {
          currentEntityCode: this.state.currentEntityCode || "",
          currentProgramCode: this.state.currentProgramCode || ""
        }
      }
    }).then(function(response) {
      this.state.aiSessionId = String(response.sessionId || "");
      this.postAiAssistantMessages(response.messages || []);
      this.state.aiHistory = (response.messages || []).slice();
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel iniciar a sessao do assistente de IA.");
      throw error;
    }.bind(this));
  };

  ProgramBuilder.prototype.sendAiAssistantMessage = function(text) {
    const userMessage = {
      id: "user-" + Date.now(),
      text: text,
      authorId: String(this.state.currentUser && (this.state.currentUser.id || this.state.currentUser.login || this.state.currentUser.email) || "usuario"),
      authorName: String(this.state.currentUser && (this.state.currentUser.name || this.state.currentUser.login || this.state.currentUser.email) || "Voce"),
      timestamp: new Date().toISOString()
    };
    this.state.aiHistory.push(userMessage);
    if (this.aiChatWidget && typeof this.aiChatWidget.loading === "function") {
      this.aiChatWidget.loading(true);
    }
    return this.http.request({
      url: "/api/admin/program-builder/ai/message",
      method: "POST",
      data: {
        sessionId: this.state.aiSessionId || "",
        message: { text: text },
        history: this.state.aiHistory.slice(-20)
      }
    }).then(function(response) {
      const messages = response.messages || [];
      this.state.aiHistory = this.state.aiHistory.concat(messages);
      this.postAiAssistantMessages(messages);
      this.state.aiDraftInspection = response || null;
      this.renderAiDraftSummary(response);
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel enviar a mensagem para o assistente de IA.");
      return null;
    }.bind(this)).finally(function() {
      if (this.aiChatWidget && typeof this.aiChatWidget.loading === "function") {
        this.aiChatWidget.loading(false);
      }
    }.bind(this));
  };

  ProgramBuilder.prototype.postAiAssistantMessages = function(messages) {
    if (!this.aiChatWidget) {
      return;
    }
    global.CrudUtils.ensureArray(messages).forEach(function(item) {
      const normalized = this.normalizeAiAssistantMessage(item);
      if (normalized.text) {
        this.aiChatWidget.postMessage(normalized);
      }
    }.bind(this));
    if (typeof this.aiChatWidget.scrollToBottom === "function") {
      this.aiChatWidget.scrollToBottom();
    }
  };

  ProgramBuilder.prototype.normalizeAiAssistantMessage = function(item) {
    const authorId = String(item && item.authorId || "ia-builder");
    const authorName = String(item && item.authorName || (authorId === "ia-builder" ? "Assistente do construtor" : "Voce"));
    return {
      type: "text",
      text: String(item && item.text || ""),
      timestamp: item && item.timestamp ? new Date(item.timestamp) : new Date(),
      authorId: authorId,
      authorName: authorName
    };
  };

  ProgramBuilder.prototype.renderAiDraftSummary = function(response) {
    const inspection = response || this.state.aiDraftInspection || null;
    if (!this.aiDraftSummary || !this.aiDraftDiagnostics) {
      return;
    }
    this.aiDraftSummary.empty();
    this.aiDraftDiagnostics.empty();

    if (!inspection) {
      $("<div class=\"program-builder-empty\"></div>").text("Nenhum rascunho gerado nesta sessao.").appendTo(this.aiDraftSummary);
      return;
    }

    const draft = inspection.draft || {};
    const entityDraft = draft.entityDraft || inspection.entityDraft || null;
    const programDraft = draft.programDraft || inspection.programDraft || null;
    const diagnostics = Array.isArray(inspection.diagnostics) ? inspection.diagnostics : [];

    const summaryList = $("<div class=\"program-builder-ai-summary-grid\"></div>").appendTo(this.aiDraftSummary);
    this.renderAiSummaryItem(summaryList, "Pronto para aplicar", inspection.readyToApply ? "Sim" : "Nao");
    this.renderAiSummaryItem(summaryList, "Entidade", entityDraft ? String(entityDraft.code || "") : "-");
    this.renderAiSummaryItem(summaryList, "Tabela", entityDraft ? String(entityDraft.tableName || "") : "-");
    this.renderAiSummaryItem(summaryList, "Campos", entityDraft ? String((entityDraft.fields || []).length) : "0");
    this.renderAiSummaryItem(summaryList, "Programa", programDraft ? String(programDraft.programCode || "") : "-");
    this.renderAiSummaryItem(summaryList, "Tela", programDraft ? String(programDraft.screenId || "") : "-");

    if (!diagnostics.length) {
      $("<div class=\"program-builder-import-diagnostic is-info\"></div>").text("Sem diagnosticos adicionais.").appendTo(this.aiDraftDiagnostics);
      return;
    }
    diagnostics.forEach(function(item) {
      $("<div class=\"program-builder-import-diagnostic\"></div>")
        .addClass("is-" + String(item.level || "info"))
        .text(String(item.message || ""))
        .appendTo(this.aiDraftDiagnostics);
    }.bind(this));
  };

  ProgramBuilder.prototype.renderAiSummaryItem = function(host, label, value) {
    const item = $("<div class=\"program-builder-ai-summary-item\"></div>").appendTo(host);
    $("<span></span>").text(label).appendTo(item);
    $("<strong></strong>").text(value).appendTo(item);
  };

  ProgramBuilder.prototype.handleFinalizeAiDraft = function() {
    const inspection = this.state.aiDraftInspection || null;
    const draft = inspection && inspection.draft || null;
    if (!draft) {
      global.CrudUtils.showMessage("Nenhum rascunho de IA disponivel para validar.", "warning");
      return Promise.resolve(null);
    }
    return this.http.request({
      url: "/api/admin/program-builder/ai/finalize-draft",
      method: "POST",
      data: {
        draft: draft
      }
    }).then(function(response) {
      this.state.aiDraftInspection = response || null;
      this.renderAiDraftSummary(response);
      global.CrudUtils.showMessage("Rascunho da IA validado.", "success");
      return response;
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel validar o rascunho da IA.");
      return null;
    }.bind(this));
  };

  ProgramBuilder.prototype.handleApplyAiDraft = function() {
    return this.handleFinalizeAiDraft().then(function(response) {
      const inspection = response || this.state.aiDraftInspection || null;
      if (!inspection || !inspection.readyToApply) {
        return;
      }
      if (inspection.entityDraft) {
        this.populateEntityForm(inspection.entityDraft, []);
      }
      if (inspection.programDraft) {
        this.populateProgramForm(inspection.programDraft);
      }
      this.renderDefinition(inspection.generatedDefinition || {});
      this.previewFooter.text("Rascunho gerado pelo assistente de IA e validado no backend antes de carregar a modelagem.");
      this.bannerElement.text("Modelagem preenchida pelo assistente de IA. Revise, ajuste e salve manualmente.");
      if (this.aiAssistantWindow) {
        this.aiAssistantWindow.close();
      }
    }.bind(this));
  };

  ProgramBuilder.prototype.toggleAiAudioCapture = function() {
    if (this.aiSpeechRecognition) {
      this.stopAiAudioCapture();
      return;
    }
    if (global.SpeechRecognition || global.webkitSpeechRecognition) {
      const Recognition = global.SpeechRecognition || global.webkitSpeechRecognition;
      const recognition = new Recognition();
      recognition.lang = "pt-BR";
      recognition.interimResults = false;
      recognition.maxAlternatives = 1;
      recognition.onstart = function() {
        this.aiSpeechRecognition = recognition;
        this.updateAiAudioState(true, "Ouvindo...");
      }.bind(this);
      recognition.onerror = function() {
        this.stopAiAudioCapture();
        global.CrudUtils.showMessage("Nao foi possivel transcrever o audio.", "error");
      }.bind(this);
      recognition.onresult = function(event) {
        const transcript = String(event.results && event.results[0] && event.results[0][0] && event.results[0][0].transcript || "").trim();
        this.stopAiAudioCapture();
        if (!transcript) {
          return;
        }
        this.http.request({
          url: "/api/admin/program-builder/ai/transcribe",
          method: "POST",
          data: { transcriptText: transcript }
        }).then(function(response) {
          const finalText = String(response && response.transcript || transcript);
          this.sendAiAssistantMessage(finalText);
        }.bind(this)).catch(function(error) {
          this.handleError(error, "Nao foi possivel processar a transcricao do audio.");
        }.bind(this));
      }.bind(this);
      recognition.onend = function() {
        this.stopAiAudioCapture();
      }.bind(this);
      recognition.start();
      return;
    }
    if (!(global.navigator && global.navigator.mediaDevices && global.navigator.mediaDevices.getUserMedia && global.MediaRecorder)) {
      global.CrudUtils.showMessage("Audio indisponivel neste navegador.", "warning");
      return;
    }

    global.navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream) {
      const chunks = [];
      const recorder = new global.MediaRecorder(stream);
      this.aiAudioStream = stream;
      this.aiAudioRecorder = recorder;
      recorder.ondataavailable = function(event) {
        if (event.data && event.data.size) {
          chunks.push(event.data);
        }
      };
      recorder.onerror = function() {
        this.stopAiAudioCapture();
        global.CrudUtils.showMessage("Falha ao capturar o audio.", "error");
      }.bind(this);
      recorder.onstop = function() {
        const blob = new Blob(chunks, { type: recorder.mimeType || "audio/webm" });
        this.stopAiAudioCapture(true);
        if (!blob.size) {
          return;
        }
        const reader = new FileReader();
        reader.onload = function() {
          const result = String(reader.result || "");
          const base64 = result.split(",").pop() || "";
          this.http.request({
            url: "/api/admin/program-builder/ai/transcribe",
            method: "POST",
            data: {
              audioBase64: base64,
              mimeType: blob.type || "audio/webm",
              fileName: "builder-ai-audio.webm"
            }
          }).then(function(response) {
            const finalText = String(response && response.transcript || "").trim();
            if (finalText) {
              this.sendAiAssistantMessage(finalText);
            }
          }.bind(this)).catch(function(error) {
            this.handleError(error, "Nao foi possivel transcrever o audio.");
          }.bind(this));
        }.bind(this);
        reader.readAsDataURL(blob);
      }.bind(this);
      recorder.start();
      this.updateAiAudioState(true, "Gravando...");
    }.bind(this)).catch(function() {
      global.CrudUtils.showMessage("Nao foi possivel acessar o microfone.", "error");
    });
  };

  ProgramBuilder.prototype.stopAiAudioCapture = function(skipStatusReset) {
    if (this.aiSpeechRecognition) {
      try {
        this.aiSpeechRecognition.onend = null;
        this.aiSpeechRecognition.stop();
      } catch (_) {
      }
      this.aiSpeechRecognition = null;
    }
    if (this.aiAudioRecorder) {
      const recorder = this.aiAudioRecorder;
      this.aiAudioRecorder = null;
      if (recorder.state !== "inactive") {
        recorder.stop();
      }
    }
    if (this.aiAudioStream) {
      this.aiAudioStream.getTracks().forEach(function(track) {
        track.stop();
      });
      this.aiAudioStream = null;
    }
    if (!skipStatusReset) {
      this.updateAiAudioState(false, "Audio inativo.");
    }
  };

  ProgramBuilder.prototype.updateAiAudioState = function(active, text) {
    if (this.aiAudioButton) {
      this.aiAudioButton.element.find(".k-button-text").text(active ? "Parar audio" : "Gravar audio");
    }
    if (this.aiAudioStatus) {
      this.aiAudioStatus.text(text || (active ? "Audio ativo." : "Audio inativo."));
    }
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
    $("<thead><tr><th class=\"program-builder-handle-col\"></th><th>Nome</th><th>Campos</th><th></th></tr></thead>").appendTo(this.uniqueKeysTableElement);
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
    $("<thead><tr><th class=\"program-builder-handle-col\"></th><th>Ordem</th><th>Fase</th><th>Tipo</th><th>Regra</th><th>Ativa</th><th>Continua</th><th></th></tr></thead>").appendTo(this.rulesTableElement);
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

    const programSelectorField = this.appendField(form, "Programa existente", this.programFieldTechnicalProperties("existingProgram"));
    this.programSelectorInput = $("<input>").appendTo(programSelectorField).kendoDropDownList({
      dataTextField: "title",
      dataValueField: "code",
      optionLabel: "Novo programa",
      change: this.handleProgramSelection.bind(this)
    }).data("kendoDropDownList");

    const splitA = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.programCodeInput = this.createTextField(splitA, "Codigo do programa", this.programFieldTechnicalProperties("programCode"));
    this.programTitleInput = this.createTextField(splitA, "Titulo do programa", this.programFieldTechnicalProperties("programTitle"));

    const splitB = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    const moduleProgramField = this.appendField(splitB, "Modulo", this.programFieldTechnicalProperties("programModule"));
    this.moduleInput = $("<input>").appendTo(moduleProgramField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione o modulo",
      change: this.handleProgramModuleChange.bind(this)
    }).data("kendoDropDownList");
    this.screenIdInput = this.createTextField(splitB, "Screen ID", this.programFieldTechnicalProperties("screenId"));

    const splitC = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.builderEntityField = this.appendField(splitC, "Entidade base", this.programFieldTechnicalProperties("baseEntity"));
    this.builderEntitySelect = $("<input>").appendTo(this.builderEntityField).kendoDropDownList({
      dataTextField: "name",
      dataValueField: "code",
      optionLabel: "Selecione a entidade",
      change: this.handleProgramEntityChange.bind(this)
    }).data("kendoDropDownList");
    this.versionInput = this.createTextField(splitC, "Versao", this.programFieldTechnicalProperties("version"));

    const splitD = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.subtitleInput = this.createTextField(splitD, "Subtitulo", this.programFieldTechnicalProperties("subtitle"));
    this.iconInput = this.createTextField(splitD, "Icone", this.programFieldTechnicalProperties("icon"));

    const splitE = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.permissionPrefixInput = this.createTextField(splitE, "Prefixo de permissao", this.programFieldTechnicalProperties("permissionPrefix"));
    const pageTypeField = this.appendField(splitE, "Tipo de pagina", this.programFieldTechnicalProperties("pageType"));
    this.pageTypeSelect = $("<input>").appendTo(pageTypeField).kendoDropDownList({
      dataSource: [
        { value: "crud", text: "CRUD" },
        { value: "custom", text: "Custom" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "crud",
      change: this.syncProgramTypeState.bind(this)
    }).data("kendoDropDownList");

    const splitGovernanceA = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    const programOriginField = this.appendField(splitGovernanceA, "Origem", this.programFieldTechnicalProperties("programOrigin"));
    this.programOriginSelect = $("<input>").appendTo(programOriginField).kendoDropDownList({
      dataSource: [
        { value: "standard", text: "Padrao" },
        { value: "customer_overlay", text: "Overlay do cliente" },
        { value: "customer_custom", text: "Programa especifico" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "standard",
      change: this.schedulePreview.bind(this)
    }).data("kendoDropDownList");
    const ownerScopeField = this.appendField(splitGovernanceA, "Owner", this.programFieldTechnicalProperties("ownerScope"));
    this.ownerScopeSelect = $("<input>").appendTo(ownerScopeField).kendoDropDownList({
      dataSource: [
        { value: "system", text: "Sistema" },
        { value: "subscriber", text: "Assinante" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "system",
      change: this.schedulePreview.bind(this)
    }).data("kendoDropDownList");

    const splitGovernanceB = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    const customizationPolicyField = this.appendField(splitGovernanceB, "Politica", this.programFieldTechnicalProperties("customizationPolicy"));
    this.customizationPolicySelect = $("<input>").appendTo(customizationPolicyField).kendoDropDownList({
      dataSource: [
        { value: "locked", text: "Bloqueado" },
        { value: "overlay_only", text: "Somente overlay" },
        { value: "full_override_allowed", text: "Override total permitido" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "overlay_only",
      change: this.schedulePreview.bind(this)
    }).data("kendoDropDownList");
    this.subscriberIdInput = this.createTextField(splitGovernanceB, "Assinante", this.programFieldTechnicalProperties("subscriberId"));

    const splitGovernanceC = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    this.baseProgramCodeInput = this.createTextField(splitGovernanceC, "Programa base", this.programFieldTechnicalProperties("baseProgramCode"));
    this.baseProgramVersionIdInput = this.createTextField(splitGovernanceC, "Versao base (ID)", this.programFieldTechnicalProperties("baseProgramVersionId"));

    const splitGovernanceD = $("<div class=\"program-builder-split\"></div>").appendTo(form);
    const freezeField = this.appendField(splitGovernanceD, "Upgrade congelado", this.programFieldTechnicalProperties("upgradeFrozen"));
    this.upgradeFrozenInput = $("<input type=\"checkbox\">").appendTo($("<label></label>").appendTo(freezeField));
    $("<span></span>").text("Congelar upgrade automatico").appendTo(this.upgradeFrozenInput.parent());
    this.upgradeFrozenInput.kendoCheckBox({ change: this.schedulePreview.bind(this) });
    this.frozenReasonInput = this.createTextField(splitGovernanceD, "Motivo", this.programFieldTechnicalProperties("frozenReason"));

    const publicationField = this.appendField(form, "Ambientes permitidos na publicacao", this.programFieldTechnicalProperties("publicationPolicy"));
    this.publicationPolicyInput = $("<textarea rows=\"2\"></textarea>").appendTo(publicationField).kendoTextArea({
      inputMode: "text",
      placeholder: "prod, homolog"
    }).data("kendoTextArea");

    this.customProgramPanel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(form);
    $("<div class=\"program-builder-versions-header\"><h3>Entrada manual</h3><p>Use para programas especificos que serao implementados manualmente e registrados no catalogo por screenId.</p></div>").appendTo(this.customProgramPanel);
    const customForm = $("<div class=\"program-builder-form\"></div>").appendTo(this.customProgramPanel);
    const customSplit = $("<div class=\"program-builder-split\"></div>").appendTo(customForm);
    const customModeField = this.appendField(customSplit, "Modo custom", this.programFieldTechnicalProperties("customMode"));
    this.customModeSelect = $("<input>").appendTo(customModeField).kendoDropDownList({
      dataSource: [
        { value: "iframe", text: "Iframe interno" },
        { value: "htmlUrl", text: "Fragmento HTML por URL" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "iframe",
      change: this.schedulePreview.bind(this)
    }).data("kendoDropDownList");
    this.customEntryUrlInput = this.createTextField(customSplit, "Entry URL", this.programFieldTechnicalProperties("customEntryUrl"));
    this.customFrameTitleInput = this.createTextField(customSplit, "Titulo do frame", this.programFieldTechnicalProperties("customFrameTitle"));
    this.customProgramHint = $("<div class=\"program-builder-inline-hint\"></div>").appendTo(customForm);
    this.customProgramHint.text("Use caminhos relativos do proprio sistema, por exemplo `production/custom/minha-tela.html`.");

    this.programWriteFlagsField = this.appendField(form, "Permissoes de escrita", this.programFieldTechnicalProperties("writeFlags"));
    const flags = $("<div class=\"program-builder-flags\"></div>").appendTo(this.programWriteFlagsField);
    this.allowCreateInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Permitir inclusao").appendTo(this.allowCreateInput.parent());
    this.allowUpdateInput = $("<input type=\"checkbox\" checked>").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Permitir alteracao").appendTo(this.allowUpdateInput.parent());
    this.allowDeleteInput = $("<input type=\"checkbox\">").appendTo($("<label></label>").appendTo(flags));
    $("<span></span>").text("Permitir exclusao").appendTo(this.allowDeleteInput.parent());
    this.allowCreateInput.kendoCheckBox({ change: this.schedulePreview.bind(this) });
    this.allowUpdateInput.kendoCheckBox({ change: this.schedulePreview.bind(this) });
    this.allowDeleteInput.kendoCheckBox({ change: this.schedulePreview.bind(this) });

    const summaryField = this.appendField(form, "Resumo da versao", this.programFieldTechnicalProperties("changeSummary"));
    this.changeSummaryTextArea = $("<textarea rows=\"4\"></textarea>").appendTo(summaryField).kendoTextArea({
      inputMode: "text",
      placeholder: "Descreva o objetivo desta versao."
    }).data("kendoTextArea");

    this.attachLivePreview();
    this.syncProgramTypeState();
  };

  ProgramBuilder.prototype.appendField = function(parent, label, technicalProperties) {
    const wrapper = $("<label class=\"program-builder-field\"></label>").appendTo(parent);
    this.appendFieldLabel(wrapper, label, technicalProperties);
    return wrapper;
  };

  ProgramBuilder.prototype.createTextField = function(parent, label, technicalProperties) {
    const wrapper = this.appendField(parent, label, technicalProperties);
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
      ,this.subscriberIdInput
      ,this.baseProgramCodeInput
      ,this.baseProgramVersionIdInput
      ,this.frozenReasonInput
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
    this.publicationPolicyInput.element.on("input", this.schedulePreview.bind(this));
    this.publicationPolicyInput.element.on("change", this.schedulePreview.bind(this));
    this.moduleInput.bind("change", this.schedulePreview.bind(this));
    [this.programOriginSelect, this.ownerScopeSelect, this.customizationPolicySelect].forEach(function(widget) {
      if (widget && typeof widget.bind === "function") {
        widget.bind("change", self.schedulePreview.bind(self));
      }
    });
    [this.customEntryUrlInput, this.customFrameTitleInput].forEach(function(widget) {
      const input = widget && (widget.input || widget.element);
      if (!input || typeof input.on !== "function") {
        return;
      }
      input.on("input", self.schedulePreview.bind(self));
      input.on("change", self.schedulePreview.bind(self));
    });
    [
      this.apiBaseUrlInput,
      this.apiTimeoutInput,
      this.apiListUrlInput,
      this.apiListItemsPathInput,
      this.apiListTotalPathInput,
      this.apiDetailUrlInput,
      this.apiDetailItemPathInput
    ].forEach(function(widget) {
      const input = widget && (widget.input || widget.element);
      if (!input || typeof input.on !== "function") {
        return;
      }
      input.on("input", self.schedulePreview.bind(self));
      input.on("change", self.schedulePreview.bind(self));
    });
    [
      this.apiAuthHeadersInput,
      this.apiListHeadersInput,
      this.apiListQueryInput,
      this.apiListBodyInput,
      this.apiDetailHeadersInput,
      this.apiDetailQueryInput,
      this.apiDetailBodyInput
    ].forEach(function(input) {
      if (!input || typeof input.on !== "function") {
        return;
      }
      input.on("input", self.schedulePreview.bind(self));
      input.on("change", self.schedulePreview.bind(self));
    });
    [this.apiListMethodSelect, this.apiDetailMethodSelect].forEach(function(widget) {
      if (widget && typeof widget.bind === "function") {
        widget.bind("change", self.schedulePreview.bind(self));
      }
    });
  };

  ProgramBuilder.prototype.syncProgramTypeState = function() {
    const pageType = String(this.pageTypeSelect && this.pageTypeSelect.value ? this.pageTypeSelect.value() || "crud" : "crud");
    const isCrud = pageType === "crud";
    const entity = isCrud ? this.findEntitySummary(String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "")) : null;
    const apiEntity = !!(entity && entity.entityType === "api");
    const sameLoadedApi = apiEntity && this.state.currentEntityCode === String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "");
    const readOnlyApi = apiEntity && sameLoadedApi
      ? !String(this.apiCatalogCreateOperationSelect.value() || "").trim() && !String(this.apiCatalogUpdateOperationSelect.value() || "").trim() && !String(this.apiCatalogDeleteOperationSelect.value() || "").trim()
      : false;

    if (this.builderEntityField) {
      this.builderEntityField.toggle(isCrud);
    }
    if (this.programWriteFlagsField) {
      this.programWriteFlagsField.toggle(isCrud);
    }
    if (this.customProgramPanel) {
      this.customProgramPanel.toggle(!isCrud);
    }
    if (this.builderEntitySelect) {
      this.builderEntitySelect.enable(isCrud);
    }
    if (!isCrud) {
      this.allowCreateInput.prop("checked", false);
      this.allowUpdateInput.prop("checked", false);
      this.allowDeleteInput.prop("checked", false);
    }
    if (readOnlyApi) {
      this.allowCreateInput.prop("checked", false).prop("disabled", true);
      this.allowUpdateInput.prop("checked", false).prop("disabled", true);
      this.allowDeleteInput.prop("checked", false).prop("disabled", true);
    } else if (apiEntity && sameLoadedApi) {
      this.allowCreateInput.prop("disabled", String(this.apiCatalogCreateOperationSelect.value() || "").trim() === "");
      this.allowUpdateInput.prop("disabled", String(this.apiCatalogUpdateOperationSelect.value() || "").trim() === "");
      this.allowDeleteInput.prop("disabled", String(this.apiCatalogDeleteOperationSelect.value() || "").trim() === "");
    } else {
      this.allowCreateInput.prop("disabled", !isCrud);
      this.allowUpdateInput.prop("disabled", !isCrud);
      this.allowDeleteInput.prop("disabled", !isCrud);
    }
    this.schedulePreview();
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

  ProgramBuilder.prototype.renderComparePanel = function() {
    const controls = this.compareControls;
    const modeField = this.appendField(controls, "Comparar");
    this.compareModeSelect = $("<input>").appendTo(modeField).kendoDropDownList({
      dataSource: [
        { value: "entity", text: "Revisoes da entidade" },
        { value: "program", text: "Versoes do programa" }
      ],
      dataTextField: "text",
      dataValueField: "value",
      value: "entity",
      change: this.refreshCompareChoices.bind(this)
    }).data("kendoDropDownList");
    const split = $("<div class=\"program-builder-split\"></div>").appendTo(controls);
    const baseField = this.appendField(split, "Base");
    const targetField = this.appendField(split, "Comparar com");
    this.compareBaseSelect = $("<input>").appendTo(baseField).kendoDropDownList({
      dataTextField: "label",
      dataValueField: "id",
      optionLabel: "Selecione",
      change: this.renderCompareDiff.bind(this)
    }).data("kendoDropDownList");
    this.compareTargetSelect = $("<input>").appendTo(targetField).kendoDropDownList({
      dataTextField: "label",
      dataValueField: "id",
      optionLabel: "Selecione",
      change: this.renderCompareDiff.bind(this)
    }).data("kendoDropDownList");
  };

  ProgramBuilder.prototype.bindRealtimeEditorEvents = function() {
    const self = this;
    this.root.on("input change", ".program-builder-form input, .program-builder-form select, .program-builder-form textarea", function() {
      self.handleEditorMutation();
    });
    this.root.on("click", ".program-builder-fields-table tbody tr:not(.program-builder-field-details-row)", function() {
      const index = $(this).prevAll("tr").filter(function() {
        return !$(this).hasClass("program-builder-field-details-row");
      }).length;
      self.selectPropertyNode("field", { index: index });
    });
    this.root.on("click", ".program-builder-rule-row", function() {
      const index = $(this).prevAll(".program-builder-rule-row").length;
      self.selectPropertyNode("rule", { index: index });
    });
    this.root.on("click", ".program-builder-unique-key-row", function() {
      const index = $(this).prevAll(".program-builder-unique-key-row").length;
      self.selectPropertyNode("uniqueKey", { index: index });
    });
  };

  ProgramBuilder.prototype.bindUnloadGuards = function() {
    global.addEventListener("beforeunload", this.handleBeforeUnload.bind(this));
    global.addEventListener("pagehide", this.releaseCurrentLockKeepalive.bind(this));
  };

  ProgramBuilder.prototype.bindRowDragAndDrop = function() {
    const self = this;
    this.root.on("dragstart", ".program-builder-row-handle", function(event) {
      const target = $(this).closest("tr");
      const data = target.attr("data-drag-type") ? {
        type: target.attr("data-drag-type"),
        index: Number(target.attr("data-drag-index") || 0)
      } : null;
      if (!data) {
        return;
      }
      self.dragState = data;
      if (event.originalEvent && event.originalEvent.dataTransfer) {
        event.originalEvent.dataTransfer.effectAllowed = "move";
        event.originalEvent.dataTransfer.setData("text/plain", JSON.stringify(data));
      }
      target.addClass("is-dragging");
    });
    this.root.on("dragend", ".program-builder-row-handle", function() {
      self.root.find(".is-dragging, .is-drop-target").removeClass("is-dragging is-drop-target");
      self.dragState = null;
    });
    this.root.on("dragover", "tr[data-drag-type]", function(event) {
      if (!self.dragState || self.dragState.type !== $(this).attr("data-drag-type")) {
        return;
      }
      event.preventDefault();
      self.root.find(".is-drop-target").removeClass("is-drop-target");
      $(this).addClass("is-drop-target");
    });
    this.root.on("drop", "tr[data-drag-type]", function(event) {
      if (!self.dragState || self.dragState.type !== $(this).attr("data-drag-type")) {
        return;
      }
      event.preventDefault();
      const targetIndex = Number($(this).attr("data-drag-index") || 0);
      self.handleRowReorder(self.dragState.type, self.dragState.index, targetIndex);
      self.root.find(".is-dragging, .is-drop-target").removeClass("is-dragging is-drop-target");
      self.dragState = null;
    });
  };

  ProgramBuilder.prototype.handleRowReorder = function(type, sourceIndex, targetIndex) {
    if (sourceIndex === targetIndex || sourceIndex < 0 || targetIndex < 0) {
      return;
    }
    if (type === "field") {
      const rows = this.collectEntityPayload().fields.slice();
      const moved = rows.splice(sourceIndex, 1)[0];
      if (!moved) {
        return;
      }
      rows.splice(targetIndex, 0, moved);
      this.renderFieldRows(rows);
      this.selectPropertyNode("field", { index: targetIndex });
    } else if (type === "rule") {
      const rows = this.collectEntityPayload().rules.slice();
      const moved = rows.splice(sourceIndex, 1)[0];
      if (!moved) {
        return;
      }
      rows.splice(targetIndex, 0, moved);
      this.renderRuleRows(rows);
      this.selectPropertyNode("rule", { index: targetIndex });
    } else if (type === "uniqueKey") {
      const rows = this.collectEntityPayload().uniqueKeys.slice();
      const moved = rows.splice(sourceIndex, 1)[0];
      if (!moved) {
        return;
      }
      rows.splice(targetIndex, 0, moved);
      this.renderUniqueKeyRows(rows);
      this.selectPropertyNode("uniqueKey", { index: targetIndex });
    }
    this.handleEditorMutation();
  };

  ProgramBuilder.prototype.handleEditorMutation = function() {
    this.validateIncremental();
    this.updateWorkspaceSummary();
    this.renderPropertyInspector();
    this.renderRelationshipView();
    this.refreshCompareChoices();
    this.schedulePreview();
  };

  ProgramBuilder.prototype.ensureEditorLock = function(scopeType, scopeCode, displayName) {
    const type = String(scopeType || "").trim();
    const code = String(scopeCode || "").trim();
    if (!type || !code) {
      this.releaseCurrentLock();
      return;
    }
    const scopeKey = type + ":" + code;
    if (this.pendingLockScopeKey === scopeKey) {
      return;
    }
    this.pendingLockScopeKey = scopeKey;
    const currentLock = this.state.currentLock;
    const proceed = function() {
      const grantId = this.currentGovernanceGrantId(type, code);
      this.http.request({
        url: "/api/admin/program-builder/locks/acquire",
        method: "POST",
        data: {
          scopeType: type,
          scopeCode: code,
          displayName: displayName || code,
          grantId: grantId || null,
          lockCategory: grantId ? "authoring" : "general"
        }
      }).then(function(response) {
        this.pendingLockScopeKey = "";
        this.state.currentLock = response.lock || null;
        this.state.lockReadonly = response.status !== "acquired";
        this.applyLockState(type, code, response);
      }.bind(this)).catch(function(error) {
        this.pendingLockScopeKey = "";
        this.handleError(error, "Nao foi possivel bloquear a edicao.");
      }.bind(this));
    }.bind(this);
    if (currentLock && (currentLock.scopeType !== type || currentLock.scopeCode !== code)) {
      this.releaseCurrentLock().then(proceed);
      return;
    }
    proceed();
  };

  ProgramBuilder.prototype.applyLockState = function(scopeType, scopeCode, response) {
    this.clearLockOverlays();
    global.clearInterval(this.lockHeartbeatTimer);
    this.lockHeartbeatTimer = null;
    const message = response && response.message ? String(response.message) : "";
    if (response && response.status === "acquired" && response.heartbeatIntervalSeconds) {
      this.lockHeartbeatTimer = global.setInterval(function() {
        if (!this.state.currentLock || !this.state.currentLock.lockToken) {
          return;
        }
        this.http.request({
          url: "/api/admin/program-builder/locks/heartbeat",
          method: "POST",
          data: { lockToken: this.state.currentLock.lockToken }
        }).then(function(heartbeat) {
          this.state.currentLock = heartbeat.lock || this.state.currentLock;
        }.bind(this)).catch(function(error) {
          global.clearInterval(this.lockHeartbeatTimer);
          this.lockHeartbeatTimer = null;
          this.state.currentLock = null;
          this.state.lockReadonly = true;
          this.renderLockOverlay(scopeType, scopeCode, global.CrudUtils.unwrapError(error, "A autorizacao vinculada ao lock foi encerrada.").message);
          this.syncToolbarState();
        }.bind(this));
      }.bind(this), Number(response.heartbeatIntervalSeconds || 45) * 1000);
      this.syncToolbarState();
      return;
    }
    this.renderLockOverlay(scopeType, scopeCode, message, response && response.lock);
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.renderLockOverlay = function(scopeType, scopeCode, message, lock) {
    const text = message || "Item bloqueado para edicao.";
    let target = null;
    if (scopeType === "module") {
      target = this.modulesPanel;
    } else if (scopeType === "entity") {
      target = this.entityPanel;
    } else if (scopeType === "program") {
      target = this.programPanel;
    }
    if (!target) {
      return;
    }
    target.addClass("program-builder-panel-readonly");
    const overlay = $("<div class=\"program-builder-lock-overlay\"></div>").appendTo(target);
    $("<strong></strong>").text("Somente leitura").appendTo(overlay);
    $("<span></span>").text(text).appendTo(overlay);
    if (lock && lock.userName) {
      $("<small></small>").text("Em edicao por " + lock.userName + ".").appendTo(overlay);
    }
  };

  ProgramBuilder.prototype.clearLockOverlays = function() {
    this.root.find(".program-builder-lock-overlay").remove();
    this.root.find(".program-builder-panel-readonly").removeClass("program-builder-panel-readonly");
  };

  ProgramBuilder.prototype.releaseCurrentLock = function() {
    const lock = this.state.currentLock;
    global.clearInterval(this.lockHeartbeatTimer);
    this.lockHeartbeatTimer = null;
    this.clearLockOverlays();
    this.state.currentLock = null;
    this.state.lockReadonly = false;
    this.pendingLockScopeKey = "";
    if (!lock || !lock.lockToken) {
      return Promise.resolve();
    }
    return this.http.request({
      url: "/api/admin/program-builder/locks/release",
      method: "POST",
      data: { lockToken: lock.lockToken }
    }).catch(function() {
      return null;
    }).then(function() {
      this.syncToolbarState();
    }.bind(this));
  };

  ProgramBuilder.prototype.releaseCurrentLockKeepalive = function() {
    const lock = this.state.currentLock;
    if (!lock || !lock.lockToken || !global.fetch) {
      return;
    }
    try {
      global.fetch("/api/admin/program-builder/locks/release", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ lockToken: lock.lockToken }),
        keepalive: true,
        credentials: "same-origin"
      });
    } catch (_) {
      // ignore
    }
  };

  ProgramBuilder.prototype.handleBeforeUnload = function(event) {
    this.releaseCurrentLockKeepalive();
    if (!this.hasUnsavedChanges()) {
      return;
    }
    event.preventDefault();
    event.returnValue = "";
  };

  ProgramBuilder.prototype.hasUnsavedChanges = function() {
    return false;
  };

  ProgramBuilder.prototype.currentGovernanceGrantId = function(scopeType, scopeCode) {
    const current = this.state.currentVersion || {};
    const grant = current.governance && current.governance.grant ? current.governance.grant : null;
    if (!grant || !grant.id) {
      return null;
    }
    const normalizedScopeType = String(scopeType || "").trim();
    const normalizedScopeCode = String(scopeCode || "").trim();
    if (normalizedScopeType === "program" && normalizedScopeCode === String(current.programCode || "")) {
      return grant.id;
    }
    if (normalizedScopeType === "entity" && normalizedScopeCode === String(current.builderEntityCode || "")) {
      return grant.id;
    }
    return null;
  };

  ProgramBuilder.prototype.selectPropertyNode = function(kind, context) {
    this.state.propertySelection = Object.assign({ kind: kind }, context || {});
    this.syncPropertySelectionState();
    this.renderPropertyInspector();
  };

  ProgramBuilder.prototype.syncPropertySelectionState = function() {
    const selection = this.state.propertySelection || {};
    this.root.find(".program-builder-property-selected").removeClass("program-builder-property-selected");
    if (selection.kind === "field") {
      const pair = this.getFieldRowPair(selection.index);
      if (pair.row) {
        pair.row.addClass("program-builder-property-selected");
        pair.details.addClass("program-builder-property-selected");
      }
    } else if (selection.kind === "rule") {
      const pair = this.getRuleRowPair(selection.index);
      if (pair.row) {
        pair.row.addClass("program-builder-property-selected");
        pair.details.addClass("program-builder-property-selected");
      }
    } else if (selection.kind === "uniqueKey") {
      const row = this.getUniqueKeyRow(selection.index);
      if (row && row.length) {
        row.addClass("program-builder-property-selected");
      }
    }
  };

  ProgramBuilder.prototype.renderPropertyInspector = function() {
    if (!this.propertiesElement) {
      return;
    }
    const selection = this.state.propertySelection || { kind: "entity" };
    this.propertiesElement.empty();
    try {
      if (selection.kind === "module") {
        this.renderModuleProperties();
        return;
      }
      if (selection.kind === "program") {
        this.renderProgramProperties();
        return;
      }
      if (selection.kind === "field") {
        this.renderFieldProperties(selection.index);
        return;
      }
      if (selection.kind === "rule") {
        this.renderRuleProperties(selection.index);
        return;
      }
      if (selection.kind === "uniqueKey") {
        this.renderUniqueKeyProperties(selection.index);
        return;
      }
      this.renderEntityProperties();
    } catch (error) {
      $("<p class=\"program-builder-empty\"></p>").text(error && error.message ? error.message : "Nao foi possivel montar o painel de propriedades.").appendTo(this.propertiesElement);
    }
  };

  ProgramBuilder.prototype.renderModuleProperties = function() {
    const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
    this.appendPropertyText(panel, "Codigo", () => this.moduleCatalogCodeInput.value(), (value) => this.moduleCatalogCodeInput.value(value));
    this.appendPropertyText(panel, "Nome", () => this.moduleCatalogNameInput.value(), (value) => this.moduleCatalogNameInput.value(value));
    this.appendPropertyText(panel, "Abreviacao", () => this.moduleCatalogAbbreviationInput.value(), (value) => this.moduleCatalogAbbreviationInput.value(value));
    this.appendPropertyText(panel, "Numero inicial", () => this.moduleCatalogStartInput.value(), (value) => this.moduleCatalogStartInput.value(value), "number");
    this.appendPropertyText(panel, "Numero final", () => this.moduleCatalogEndInput.value(), (value) => this.moduleCatalogEndInput.value(value), "number");
    this.appendPropertyCheckbox(panel, "Ativo", () => this.moduleCatalogEnabledInput.is(":checked"), (checked) => this.moduleCatalogEnabledInput.prop("checked", checked).trigger("change"));
  };

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
    this.appendPropertyCheckbox(panel, "Versionada", () => this.entityVersioningEnabledInput.is(":checked"), (checked) => this.entityVersioningEnabledInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Entidade", "Versionada", "Liga o fluxo de snapshot historico da entidade e dos campos marcados para historico." ));
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
    this.appendPropertySelect(panel, "Origem", [
      { value: "standard", text: "Padrao" },
      { value: "customer_overlay", text: "Overlay do cliente" },
      { value: "customer_custom", text: "Programa especifico" }
    ], () => this.programOriginSelect.value(), (value) => { this.programOriginSelect.value(value); this.schedulePreview(); }, this.programFieldTechnicalProperties("programOrigin"));
    this.appendPropertySelect(panel, "Owner", [
      { value: "system", text: "Sistema" },
      { value: "subscriber", text: "Assinante" }
    ], () => this.ownerScopeSelect.value(), (value) => { this.ownerScopeSelect.value(value); this.schedulePreview(); }, this.programFieldTechnicalProperties("ownerScope"));
    this.appendPropertySelect(panel, "Politica", [
      { value: "locked", text: "Bloqueado" },
      { value: "overlay_only", text: "Somente overlay" },
      { value: "full_override_allowed", text: "Override total permitido" }
    ], () => this.customizationPolicySelect.value(), (value) => { this.customizationPolicySelect.value(value); this.schedulePreview(); }, this.programFieldTechnicalProperties("customizationPolicy"));
    this.appendPropertyText(panel, "Assinante", () => this.subscriberIdInput.value(), (value) => this.subscriberIdInput.value(value), "text", this.programFieldTechnicalProperties("subscriberId"));
    this.appendPropertyText(panel, "Programa base", () => this.baseProgramCodeInput.value(), (value) => this.baseProgramCodeInput.value(value), "text", this.programFieldTechnicalProperties("baseProgramCode"));
    this.appendPropertyText(panel, "Versao base", () => this.baseProgramVersionIdInput.value(), (value) => this.baseProgramVersionIdInput.value(value), "number", this.programFieldTechnicalProperties("baseProgramVersionId"));
    this.appendPropertyCheckbox(panel, "Upgrade congelado", () => this.upgradeFrozenInput.is(":checked"), (checked) => this.upgradeFrozenInput.prop("checked", checked).trigger("change"), this.programFieldTechnicalProperties("upgradeFrozen"));
    this.appendPropertyText(panel, "Motivo congelamento", () => this.frozenReasonInput.value(), (value) => this.frozenReasonInput.value(value), "text", this.programFieldTechnicalProperties("frozenReason"));
    this.appendPropertyText(panel, "Ambientes", () => this.publicationPolicyInput.value(), (value) => this.publicationPolicyInput.value(value), "text", this.programFieldTechnicalProperties("publicationPolicy"));
    this.appendPropertySelect(panel, "Tipo", [
      { value: "crud", text: "CRUD" },
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

  ProgramBuilder.prototype.getFieldRowPair = function(index) {
    const row = this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).eq(index);
    return {
      row: row,
      details: row.next(".program-builder-field-details-row")
    };
  };

  ProgramBuilder.prototype.getRuleRowPair = function(index) {
    const row = this.rulesTableBody.find(".program-builder-rule-row").eq(index);
    return {
      row: row,
      details: row.next(".program-builder-rule-details-row")
    };
  };

  ProgramBuilder.prototype.getUniqueKeyRow = function(index) {
    return this.uniqueKeysTableBody.find(".program-builder-unique-key-row").eq(index);
  };

  ProgramBuilder.prototype.renderRelationshipView = function() {
    if (!this.relationsElement) {
      return;
    }
    this.relationsElement.empty();
    const entityCode = String(this.entityCodeInput && this.entityCodeInput.value ? this.entityCodeInput.value() || "" : this.builderEntitySelect.value() || "").trim();
    if (!entityCode) {
      $("<p class=\"program-builder-empty\"></p>").text("Selecione ou monte uma entidade para visualizar dependencias.").appendTo(this.relationsElement);
      return;
    }
    let currentFields = [];
    try {
      currentFields = this.collectEntityPayload().fields || [];
    } catch (_) {
      $("<p class=\"program-builder-empty\"></p>").text("Corrija as configuracoes invalidas para montar o mapa de relacionamentos.").appendTo(this.relationsElement);
      return;
    }
    const center = $("<div class=\"program-builder-relation-center\"></div>").appendTo(this.relationsElement);
    $("<strong></strong>").text(entityCode).appendTo(center);
    $("<span></span>").text((this.entityNameInput && this.entityNameInput.value ? this.entityNameInput.value() : "") || "").appendTo(center);
    const list = $("<div class=\"program-builder-relation-list\"></div>").appendTo(this.relationsElement);
    let count = 0;
    currentFields.forEach(function(field) {
      if (field.foreignKeyTable) {
        const card = $("<article class=\"program-builder-relation-card\"></article>").appendTo(list);
        $("<strong></strong>").text(field.code).appendTo(card);
        $("<span></span>").text("FK para " + field.foreignKeyTable + "." + (field.foreignKeyColumn || "id")).appendTo(card);
        $("<small></small>").text(field.foreignKeyDependencyType || "reference").appendTo(card);
        count += 1;
      } else if (field.versionRefEntityCode) {
        const card = $("<article class=\"program-builder-relation-card\"></article>").appendTo(list);
        $("<strong></strong>").text(field.code).appendTo(card);
        $("<span></span>").text("Historico de " + field.versionRefEntityCode).appendTo(card);
        $("<small></small>").text("snapshot").appendTo(card);
        count += 1;
      }
    });
    if (!count) {
      $("<p class=\"program-builder-empty\"></p>").text("A entidade atual ainda nao referencia outras tabelas ou snapshots.").appendTo(list);
    }
  };

  ProgramBuilder.prototype.refreshCompareChoices = function() {
    if (!this.compareModeSelect) {
      return;
    }
    const mode = this.compareModeSelect.value() || "entity";
    const items = mode === "entity"
      ? (this.state.entityVersions || []).map(function(item) {
          return { id: String(item.id || ""), label: "R" + String(item.revision || "") + " - " + String(item.action || "") };
        })
      : (this.state.versions || []).map(function(item) {
          return { id: String(item.id || ""), label: String(item.version || "") + " - " + String(item.status || "") };
        });
    this.compareBaseSelect.setDataSource(new kendo.data.DataSource({ data: items }));
    this.compareTargetSelect.setDataSource(new kendo.data.DataSource({ data: items }));
    if (items.length >= 2) {
      if (!this.compareBaseSelect.value()) {
        this.compareBaseSelect.value(items[items.length - 1].id);
      }
      if (!this.compareTargetSelect.value()) {
        this.compareTargetSelect.value(items[0].id);
      }
    }
    this.renderCompareDiff();
  };

  ProgramBuilder.prototype.renderCompareDiff = function() {
    if (!this.compareElement) {
      return;
    }
    this.compareElement.empty();
    const mode = this.compareModeSelect && this.compareModeSelect.value ? this.compareModeSelect.value() : "entity";
    const baseId = this.compareBaseSelect && this.compareBaseSelect.value ? this.compareBaseSelect.value() : "";
    const targetId = this.compareTargetSelect && this.compareTargetSelect.value ? this.compareTargetSelect.value() : "";
    if (!baseId || !targetId || baseId === targetId) {
      $("<p class=\"program-builder-empty\"></p>").text("Selecione duas revisoes ou versoes diferentes.").appendTo(this.compareElement);
      return;
    }
    const base = (mode === "entity" ? this.state.entityVersions : this.state.versions).find(function(item) {
      return String(item.id) === String(baseId);
    });
    const target = (mode === "entity" ? this.state.entityVersions : this.state.versions).find(function(item) {
      return String(item.id) === String(targetId);
    });
    const left = mode === "entity" ? (base && base.snapshot) || {} : (base && base.generatedDefinition) || {};
    const right = mode === "entity" ? (target && target.snapshot) || {} : (target && target.generatedDefinition) || {};
    const entries = [];
    this.collectDiffEntries(left, right, "", entries);
    if (!entries.length) {
      $("<p class=\"program-builder-empty\"></p>").text("Nao ha diferencas detectadas entre os itens selecionados.").appendTo(this.compareElement);
      return;
    }
    entries.forEach(function(entry) {
      const row = $("<article class=\"program-builder-diff-row\"></article>").appendTo(this.compareElement);
      row.addClass("is-" + entry.type);
      $("<strong></strong>").text(entry.path || "(raiz)").appendTo(row);
      $("<div class=\"program-builder-diff-values\"></div>")
        .append($("<pre></pre>").text(entry.before))
        .append($("<pre></pre>").text(entry.after))
        .appendTo(row);
    });
  };

  ProgramBuilder.prototype.collectDiffEntries = function(left, right, path, entries) {
    if (JSON.stringify(left) === JSON.stringify(right)) {
      return;
    }
    const leftIsObject = left && typeof left === "object";
    const rightIsObject = right && typeof right === "object";
    if (!leftIsObject || !rightIsObject) {
      entries.push({
        type: left === undefined ? "added" : (right === undefined ? "removed" : "changed"),
        path: path,
        before: left === undefined ? "" : JSON.stringify(left, null, 2),
        after: right === undefined ? "" : JSON.stringify(right, null, 2)
      });
      return;
    }
    const keys = {};
    Object.keys(left || {}).forEach(function(key) { keys[key] = true; });
    Object.keys(right || {}).forEach(function(key) { keys[key] = true; });
    Object.keys(keys).sort().forEach(function(key) {
      const nextPath = path ? path + "." + key : key;
      this.collectDiffEntries(left ? left[key] : undefined, right ? right[key] : undefined, nextPath, entries);
    }, this);
  };

  ProgramBuilder.prototype.loadBootstrap = function() {
    return this.http.request({
      url: "/api/admin/program-builder/bootstrap",
      method: "GET"
    }).then((payload) => {
      this.state.entities = payload.entities || [];
      this.state.modules = payload.modules || [];
      this.state.apiSources = payload.apiSources || [];
      this.state.programs = payload.programs || [];
      this.state.currentUser = payload.currentUser || null;
      this.applyBootstrapData();
      this.resetModuleForm();
      this.resetEntityForm();
      this.resetProgramForm();
      this.syncDatabaseImportState();
      this.bannerElement.text("Modele uma entidade nova ou escolha uma existente. Depois gere e publique o programa CRUD.");
      return this.loadDatabaseTables();
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
    if (this.databaseImportProgramModuleSelect) {
      this.databaseImportProgramModuleSelect.setDataSource(new kendo.data.DataSource({ data: modules }));
    }
    this.refreshApiSourceSelectors();
    this.entityStructureModuleSelect.setDataSource(new kendo.data.DataSource({ data: modules }));
    this.entityStructureParentSelect.setDataSource(new kendo.data.DataSource({ data: this.buildStructureEntityOptions() }));
    this.entityStructureLeftSelect.setDataSource(new kendo.data.DataSource({ data: this.buildStructureEntityOptions() }));
    this.entityStructureRightSelect.setDataSource(new kendo.data.DataSource({ data: this.buildStructureEntityOptions() }));
    this.refreshHistoricalAssistantOptions();
    this.refreshNavigator();
    this.refreshCompareChoices();
    this.updateWorkspaceSummary();
  };

  ProgramBuilder.prototype.loadDatabaseTables = function() {
    return this.http.request({
      url: "/api/admin/program-builder/database/tables",
      method: "GET"
    }).then(function(response) {
      this.state.databaseTables = Array.isArray(response && response.tables) ? response.tables : [];
      if (this.databaseImportTableSelect) {
        this.databaseImportTableSelect.setDataSource(new kendo.data.DataSource({
          data: this.state.databaseTables.map(function(item) {
            return {
              qualifiedName: item.qualifiedName,
              schema: item.schema,
              tableName: item.tableName,
              existingEntityCode: item.existingEntityCode
            };
          })
        }));
      }
    }.bind(this)).catch(function(error) {
      this.handleError(error, "Nao foi possivel listar as tabelas do banco.");
    }.bind(this));
  };

  ProgramBuilder.prototype.refreshNavigator = function() {
    if (!this.navigatorTree) {
      return;
    }
    this.renderNavigatorStats();
    this.syncNavigatorQuickFilters();
    this.navigatorTree.setDataSource(new kendo.data.HierarchicalDataSource({
      data: this.buildNavigatorData()
    }));
    this.navigatorTree.expand(".k-item");
    global.setTimeout(function() {
      this.decorateNavigatorNodes();
      this.syncNavigatorSelection();
      this.syncNavigatorActions();
    }.bind(this), 0);
  };

  ProgramBuilder.prototype.loadEditorContext = function() {
    this.restoredContext = global.ProgramBuilderStorage && typeof global.ProgramBuilderStorage.load === "function"
      ? global.ProgramBuilderStorage.load(this.contextStorageKey)
      : null;
  };

  ProgramBuilder.prototype.persistEditorContext = function() {
    const payload = {
      navigatorFilter: this.navigatorFilter,
      navigatorTypeFilter: this.navigatorTypeFilter,
      navigatorStateFilter: this.navigatorStateFilter,
      navigatorSelection: this.state.navigatorSelection,
      editorTabIndex: this.editorTabs ? this.editorTabs.select().index() : 0,
      sideTabIndex: this.sideTabs ? this.sideTabs.select().index() : 0
    };
    if (global.ProgramBuilderStorage && typeof global.ProgramBuilderStorage.save === "function") {
      global.ProgramBuilderStorage.save(this.contextStorageKey, payload);
    }
  };

  ProgramBuilder.prototype.applyRestoredContext = function() {
    const context = this.restoredContext || {};
    if (typeof context.navigatorFilter === "string" && this.navigatorFilterInput) {
      this.navigatorFilter = context.navigatorFilter;
      this.navigatorFilterInput.value(context.navigatorFilter);
    }
    if (typeof context.navigatorTypeFilter === "string") {
      this.navigatorTypeFilter = context.navigatorTypeFilter;
    }
    if (typeof context.navigatorStateFilter === "string") {
      this.navigatorStateFilter = context.navigatorStateFilter;
    }
    if (context.editorTabIndex != null) {
      this.activateEditorTab(context.editorTabIndex);
    }
    if (context.sideTabIndex != null) {
      this.activateSideTab(context.sideTabIndex);
    }
    this.refreshNavigator();
    if (context.navigatorSelection && context.navigatorSelection.type && context.navigatorSelection.code) {
      this.openNavigatorSelection(context.navigatorSelection.type, context.navigatorSelection.code);
    }
  };

  ProgramBuilder.prototype.decorateNavigatorNodes = function() {
    if (!this.navigatorTree) {
      return;
    }
    this.navigatorTree.element.find("[role='treeitem']").each(function(_, node) {
      const $node = $(node);
      const item = this.navigatorTree.dataItem(node);
      const contentWrapper = $node.children(".k-treeview-item-content").first();
      const content = contentWrapper.children(".k-treeview-leaf").first().length
        ? contentWrapper.children(".k-treeview-leaf").first()
        : contentWrapper.find(".k-treeview-leaf").first();
      if (!item || !content.length) {
        return;
      }
      content.attr("data-builder-type", String(item.type || ""));
      content.attr("data-builder-code", String(item.code || ""));
      content.off("click.programBuilder").on("click.programBuilder", function(event) {
        event.preventDefault();
        event.stopPropagation();
        this.applyNavigatorSelectionPayload(item.type, item.code, node);
      }.bind(this));
      content.find(".program-builder-tree-badges").remove();
      const badges = item.badges && typeof item.badges.toJSON === "function"
        ? item.badges.toJSON()
        : (item.badges && typeof item.badges.length === "number" ? Array.prototype.slice.call(item.badges) : []);
      if (!badges.length) {
        return;
      }
      const wrapper = $("<span class=\"program-builder-tree-badges\"></span>").appendTo(content);
      badges.forEach(function(badge) {
        $("<span class=\"program-builder-tree-badge\"></span>")
          .addClass("is-" + String(badge.tone || "muted"))
          .text(String(badge.text || ""))
          .appendTo(wrapper);
      });
    }.bind(this));
  };

  ProgramBuilder.prototype.syncNavigatorQuickFilters = function() {
    const applyState = function(buttonMap, current) {
      if (!buttonMap) {
        return;
      }
      Object.keys(buttonMap).forEach(function(key) {
        const element = buttonMap[key] && buttonMap[key].element;
        if (!element) {
          return;
        }
        element.toggleClass("program-builder-quick-active", key === current);
      });
    };
    applyState(this.navigatorTypeButtons, this.navigatorTypeFilter);
    applyState(this.navigatorStateButtons, this.navigatorStateFilter);
    this.persistEditorContext();
  };

  ProgramBuilder.prototype.renderNavigatorStats = function() {
    if (!this.navigatorStats) {
      return;
    }
    this.navigatorStats.empty();
    [
      { label: "Modulos", value: this.state.modules.length },
      { label: "Entidades", value: this.state.entities.length },
      { label: "Programas", value: this.state.programs.length }
    ].forEach(function(item) {
      const card = $("<div class=\"program-builder-stat-card\"></div>").appendTo(this.navigatorStats);
      $("<strong></strong>").text(String(item.value)).appendTo(card);
      $("<span></span>").text(item.label).appendTo(card);
    }, this);
  };

  ProgramBuilder.prototype.buildNavigatorData = function() {
    const filter = this.normalizeNavigatorFilter(this.navigatorFilter);
    const typeFilter = this.navigatorTypeFilter;
    const stateFilter = this.navigatorStateFilter;
    const filterMatch = function(text) {
      if (!filter) {
        return true;
      }
      return this.normalizeNavigatorFilter(text).indexOf(filter) >= 0;
    }.bind(this);
    const typeMatch = function(type) {
      return typeFilter === "all" || typeFilter === type;
    };
    const stateMatch = function(type, rawItem) {
      if (stateFilter === "all") {
        return true;
      }
      if (stateFilter === "published") {
        if (type === "module") {
          return rawItem && rawItem.enabled !== false;
        }
        return rawItem && String(rawItem.status || "") === "published";
      }
      if (stateFilter === "versioned") {
        return type === "entity" && rawItem && rawItem.versioningEnabled === true;
      }
      return true;
    };

    const moduleNodes = this.state.modules.map(function(item) {
      return {
        id: "module:" + item.code,
        text: ((item.abbreviation || "").toUpperCase() ? (item.abbreviation || "").toUpperCase() + " - " : "") + item.name,
        spriteCssClass: "program-builder-node-module",
        type: "module",
        code: item.code,
        rawItem: item,
        badges: this.buildNavigatorBadges("module", item)
      };
    }.bind(this)).filter(function(node) {
      return typeMatch("module") && stateMatch("module", node.rawItem) && (filterMatch(node.text) || filterMatch(node.code));
    });

    const entityNodes = this.state.entities.map(function(item) {
      return {
        id: "entity:" + item.code,
        text: item.code + " - " + item.name,
        spriteCssClass: "program-builder-node-entity",
        type: "entity",
        code: item.code,
        rawItem: item,
        badges: this.buildNavigatorBadges("entity", item)
      };
    }.bind(this)).filter(function(node) {
      return typeMatch("entity") && stateMatch("entity", node.rawItem) && (filterMatch(node.text) || filterMatch(node.code));
    });

    const programNodes = this.state.programs.map(function(item) {
      return {
        id: "program:" + item.code,
        text: item.code + " - " + item.title,
        spriteCssClass: "program-builder-node-program",
        type: "program",
        code: item.code,
        rawItem: item,
        badges: this.buildNavigatorBadges("program", item)
      };
    }.bind(this)).filter(function(node) {
      return typeMatch("program") && stateMatch("program", node.rawItem) && (filterMatch(node.text) || filterMatch(node.code));
    });

    return [
      {
        id: "group:modules",
        text: "Modulos",
        expanded: true,
        items: moduleNodes
      },
      {
        id: "group:entities",
        text: "Entidades",
        expanded: true,
        items: entityNodes
      },
      {
        id: "group:programs",
        text: "Programas",
        expanded: true,
        items: programNodes
      }
    ];
  };

  ProgramBuilder.prototype.normalizeNavigatorFilter = function(text) {
    return String(text || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim();
  };

  ProgramBuilder.prototype.buildNavigatorBadges = function(type, item) {
    const badges = [];
    if (type === "module") {
      badges.push({
        text: item.enabled === false ? "Inativo" : "Ativo",
        tone: item.enabled === false ? "muted" : "success"
      });
      badges.push({
        text: (item.abbreviation || "").toUpperCase(),
        tone: "info"
      });
      return badges;
    }
    if (type === "entity") {
      const issues = this.countEntityIssues(item);
      badges.push({
        text: item.entityType || "persistence",
        tone: "info"
      });
      badges.push({
        text: item.status || "draft",
        tone: String(item.status || "") === "published" ? "success" : "warn"
      });
      if (item.versioningEnabled === true) {
        badges.push({
          text: "hist",
          tone: "accent"
        });
      }
      if (issues > 0) {
        badges.push({
          text: "!" + issues,
          tone: "warn"
        });
      }
      return badges;
    }
    if (type === "program") {
      const issues = this.countProgramIssues(item);
      badges.push({
        text: item.status || "draft",
        tone: String(item.status || "") === "published" ? "success" : "warn"
      });
      if (item.publishedVersion) {
        badges.push({
          text: item.publishedVersion,
          tone: "accent"
        });
      }
      if (issues > 0) {
        badges.push({
          text: "!" + issues,
          tone: "warn"
        });
      }
      return badges;
    }
    return badges;
  };

  ProgramBuilder.prototype.countEntityIssues = function(item) {
    if (item && item.code && item.code === this.state.currentEntityCode && this.state.validation) {
      return (this.state.validation.entityIssues || []).length
        + Object.keys(this.state.validation.fieldIssues || {}).length
        + Object.keys(this.state.validation.ruleIssues || {}).length
        + Object.keys(this.state.validation.uniqueKeyIssues || {}).length;
    }
    let count = 0;
    if (!item) {
      return 0;
    }
    if (!item.code) {
      count += 1;
    }
    if (!item.name) {
      count += 1;
    }
    if (item.entityType === "persistence" && !item.tableName) {
      count += 1;
    }
    if ((item.fieldsCount || 0) < 1) {
      count += 1;
    }
    if (item.entityType === "persistence" && item.hasPrimaryKey === false) {
      count += 1;
    }
    return count;
  };

  ProgramBuilder.prototype.countProgramIssues = function(item) {
    if (item && item.code && item.code === this.state.currentProgramCode && this.state.validation) {
      return (this.state.validation.programIssues || []).length;
    }
    let count = 0;
    if (!item) {
      return 0;
    }
    if (!item.code) {
      count += 1;
    }
    if (!item.title) {
      count += 1;
    }
    if (!item.module) {
      count += 1;
    }
    if (!item.screenId) {
      count += 1;
    }
    if ((!item.pageType || item.pageType === "crud" || item.programType === "crud") && !item.builderEntityCode) {
      count += 1;
    }
    return count;
  };

  ProgramBuilder.prototype.validateIncremental = function() {
    const validation = {
      entityIssues: [],
      programIssues: [],
      fieldIssues: {},
      ruleIssues: {},
      uniqueKeyIssues: {}
    };
    let payload;
    try {
      payload = this.collectEntityPayload();
    } catch (error) {
      validation.entityIssues.push(error && error.message ? error.message : "Configuracao da entidade invalida.");
      this.state.validation = validation;
      this.applyValidationState();
      return;
    }
    const fieldCodes = {};
    const fieldColumns = {};
    (payload.fields || []).forEach(function(field, index) {
      const issues = [];
      if (!String(field.code || "").trim()) {
        issues.push("Codigo obrigatorio.");
      }
      if (!String(field.label || "").trim()) {
        issues.push("Label obrigatoria.");
      }
      const fieldCode = String(field.code || "").trim();
      const columnName = String(field.columnName || "").trim();
      const apiJsonPath = String(field.apiJsonPath || "").trim();
      if (fieldCode) {
        if (fieldCodes[fieldCode]) {
          issues.push("Codigo duplicado.");
        }
        fieldCodes[fieldCode] = true;
      }
      if (payload.entityType !== "api" && !field.virtualField && columnName) {
        if (fieldColumns[columnName]) {
          issues.push("Coluna duplicada.");
        }
        fieldColumns[columnName] = true;
      }
      if (payload.entityType === "api" && !apiJsonPath) {
        issues.push("JSON path obrigatorio.");
      }
      if ((field.dataType === "enum" || field.dataType === "dropdown") && (!Array.isArray(field.optionItems) || !field.optionItems.length)) {
        issues.push("Lista de opcoes obrigatoria.");
      }
      if (payload.entityType !== "api" && ((field.foreignKeyTable && !field.foreignKeyColumn) || (!field.foreignKeyTable && field.foreignKeyColumn))) {
        issues.push("FK exige tabela e coluna.");
      }
      if (payload.entityType !== "api" && field.dataType === "custom_code") {
        if (field.customCodeMode === "static_method") {
          if (!String(field.customCodeStaticClass || "").trim() || !String(field.customCodeStaticMethod || "").trim()) {
            issues.push("Classe e metodo obrigatorios para codificacao estatica.");
          }
        } else if (!String(field.customCodePattern || "").trim()) {
          issues.push("Padrao da codificacao obrigatorio.");
        }
      }
      if (payload.entityType !== "api" && field.versionRefEntityCode && !field.versionRefSourceIdField) {
        issues.push("Historico exige campo origem.");
      }
      if (payload.entityType !== "api" && field.versionSnapshotVersionField && !field.versionSnapshotPath) {
        issues.push("Campo virtual historico exige caminho do snapshot.");
      }
      if (issues.length) {
        validation.fieldIssues[index] = issues;
      }
    });
    if (!String(payload.code || "").trim()) {
      validation.entityIssues.push("Codigo da entidade obrigatorio.");
    }
    if (!String(payload.name || "").trim()) {
      validation.entityIssues.push("Nome da entidade obrigatorio.");
    }
    if (payload.entityType === "persistence" && !String(payload.tableName || "").trim()) {
      validation.entityIssues.push("Tabela fisica obrigatoria.");
    }
    if (payload.entityType === "persistence" && payload.subscriberIsolationMode === "subscriber_column") {
      if (!String(payload.subscriberColumnName || "").trim()) {
        validation.entityIssues.push("Coluna do assinante obrigatoria.");
      } else if (!(payload.fields || []).some(function(field) {
        return field.virtualField !== true && String(field.columnName || "").trim() === String(payload.subscriberColumnName || "").trim();
      })) {
        validation.entityIssues.push("A coluna do assinante precisa existir na lista de campos.");
      }
    } else if (payload.entityType === "persistence" && payload.subscriberGlobalTable !== true) {
      validation.entityIssues.push("Confirme explicitamente quando a tabela for global e compartilhada entre assinantes.");
    }
    if (payload.entityType === "api") {
      if (!String(payload.apiSourceCode || "").trim()) {
        validation.entityIssues.push("Cadastro de API obrigatorio.");
      }
      if (!String(payload.apiListOperationCode || "").trim()) {
        validation.entityIssues.push("Operacao de lista obrigatoria.");
      }
    }
    if (!(payload.fields || []).length) {
      validation.entityIssues.push("Adicione ao menos um campo.");
    }
    if ((payload.fields || []).length && !(payload.fields || []).some(function(item) { return item.primaryKey === true; })) {
      validation.entityIssues.push("Defina uma chave primaria.");
    }

    (payload.uniqueKeys || []).forEach(function(key, index) {
      const issues = [];
      if (!String(key.name || "").trim()) {
        issues.push("Nome obrigatorio.");
      }
      if (!Array.isArray(key.fields) || !key.fields.length) {
        issues.push("Informe ao menos um campo.");
      } else {
        key.fields.forEach(function(fieldCode) {
          if (!fieldCodes[fieldCode]) {
            issues.push("Campo inexistente: " + fieldCode + ".");
          }
        });
      }
      if (issues.length) {
        validation.uniqueKeyIssues[index] = issues;
      }
    });

    (payload.rules || []).forEach(function(rule, index) {
      const issues = [];
      if (!String(rule.label || "").trim()) {
        issues.push("Rotulo obrigatorio.");
      }
      if (rule.ruleType === "requiredWhen") {
        if (!String(rule.field || "").trim()) {
          issues.push("Campo validado obrigatorio.");
        }
        if (!String(rule.whenField || "").trim()) {
          issues.push("Campo gatilho obrigatorio.");
        }
        if (!String(rule.message || "").trim()) {
          issues.push("Mensagem obrigatoria.");
        }
      } else {
        if (!String(rule.className || "").trim()) {
          issues.push("Classe obrigatoria.");
        }
        if (!String(rule.methodName || "").trim()) {
          issues.push("Metodo obrigatorio.");
        }
      }
      if (issues.length) {
        validation.ruleIssues[index] = issues;
      }
    });

    const programPayload = this.collectProgramPayload();
    const hasProgramIntent = !!String(programPayload.programCode || programPayload.programTitle || programPayload.screenId || programPayload.builderEntityCode || programPayload.customEntryUrl || "").trim();
    if (hasProgramIntent) {
      if (!String(programPayload.programCode || "").trim()) {
        validation.programIssues.push("Codigo do programa obrigatorio.");
      }
      if (!String(programPayload.programTitle || "").trim()) {
        validation.programIssues.push("Titulo do programa obrigatorio.");
      }
      if (!String(programPayload.module || "").trim()) {
        validation.programIssues.push("Modulo obrigatorio.");
      }
      if (!String(programPayload.screenId || "").trim()) {
        validation.programIssues.push("Screen ID obrigatorio.");
      }
      if (String(programPayload.pageType || "crud") === "crud" && !String(programPayload.builderEntityCode || "").trim()) {
        validation.programIssues.push("Entidade base obrigatoria.");
      }
      if (String(programPayload.pageType || "crud") === "custom" && !String(programPayload.customEntryUrl || "").trim()) {
        validation.programIssues.push("Entry URL obrigatoria para programa custom.");
      }
      if (!String(programPayload.version || "").trim()) {
        validation.programIssues.push("Versao obrigatoria.");
      }
    }

    this.state.validation = validation;
    this.applyValidationState();
  };

  ProgramBuilder.prototype.applyValidationState = function() {
    const validation = this.state.validation || { fieldIssues: {}, ruleIssues: {}, uniqueKeyIssues: {} };
    this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).each(function(index, row) {
      const issues = validation.fieldIssues[index] || [];
      $(row).toggleClass("program-builder-row-invalid", !!issues.length);
      $(row).find(".program-builder-row-status").text(issues.length ? String(issues.length) : "");
      $(row).attr("title", issues.join(" "));
      $(row).next(".program-builder-field-details-row").toggleClass("program-builder-row-invalid", !!issues.length);
    });
    this.rulesTableBody.find(".program-builder-rule-row").each(function(index, row) {
      const issues = validation.ruleIssues[index] || [];
      $(row).toggleClass("program-builder-row-invalid", !!issues.length);
      $(row).find(".program-builder-row-status").text(issues.length ? String(issues.length) : "");
      $(row).attr("title", issues.join(" "));
      $(row).next(".program-builder-rule-details-row").toggleClass("program-builder-row-invalid", !!issues.length);
    });
    this.uniqueKeysTableBody.find(".program-builder-unique-key-row").each(function(index, row) {
      const issues = validation.uniqueKeyIssues[index] || [];
      $(row).toggleClass("program-builder-row-invalid", !!issues.length);
      $(row).find(".program-builder-row-status").text(issues.length ? String(issues.length) : "");
      $(row).attr("title", issues.join(" "));
    });
  };


  ProgramBuilder.prototype.handleNavigatorFilterChange = function() {
    this.navigatorFilter = this.navigatorFilterInput.value() || "";
    this.refreshNavigator();
    this.persistEditorContext();
  };

  ProgramBuilder.prototype.handleNavigatorSelect = function(event) {
    this.applyNavigatorSelection(event.node);
  };

  ProgramBuilder.prototype.handleNavigatorTreeClick = function(event) {
    const tagged = $(event.target).closest("[data-builder-type][data-builder-code]").first();
    if (tagged.length) {
      this.applyNavigatorSelectionPayload(
        tagged.attr("data-builder-type"),
        tagged.attr("data-builder-code"),
        tagged.closest(".k-item, [role='treeitem']").first()[0] || null
      );
      return;
    }
    const node = $(event.target).closest(".k-item").first().length
      ? $(event.target).closest(".k-item").first()
      : $(event.target).closest("[role='treeitem']").first();
    if (!node.length) {
      return;
    }
    this.applyNavigatorSelection(node[0]);
  };

  ProgramBuilder.prototype.applyNavigatorSelection = function(node) {
    let actualNode = node;
    let item = this.navigatorTree.dataItem(actualNode);
    if (!item) {
      const fallback = $(actualNode).closest(".k-item").first();
      if (fallback.length) {
        actualNode = fallback[0];
        item = this.navigatorTree.dataItem(actualNode);
      }
    }
    if (!item || !item.type) {
      return;
    }
    this.applyNavigatorSelectionPayload(item.type, item.code, actualNode);
  };

  ProgramBuilder.prototype.applyNavigatorSelectionPayload = function(type, code, actualNode) {
    if (!type) {
      return;
    }
    if (this.navigatorTree && actualNode) {
      this.navigatorTree.select($(actualNode));
    }
    this.state.navigatorSelection = {
      type: type,
      code: code
    };
    this.syncNavigatorActions();
    this.persistEditorContext();

    if (type === "module") {
      const module = this.findModuleSummary(code);
      this.populateModuleForm(module || {});
      this.activateEditorTab(0);
      return;
    }

    if (type === "entity") {
      this.entitySelectorInput.value(code);
      this.handleEntitySelection();
      this.activateEditorTab(1);
      this.activateSideTab(4);
      return;
    }

    if (type === "program") {
      this.programSelectorInput.value(code);
      this.handleProgramSelection();
      this.activateEditorTab(2);
      this.activateSideTab(3);
    }
  };

  ProgramBuilder.prototype.activateEditorTab = function(index) {
    if (this.editorTabs) {
      this.editorTabs.select(index);
    }
    this.persistEditorContext();
  };

  ProgramBuilder.prototype.activateSideTab = function(index) {
    if (this.sideTabs) {
      this.sideTabs.select(index);
    }
    this.persistEditorContext();
  };

  ProgramBuilder.prototype.setNavigatorSelection = function(type, code) {
    this.state.navigatorSelection = type && code ? { type: type, code: code } : null;
    this.syncNavigatorSelection();
    this.syncNavigatorActions();
    this.persistEditorContext();
  };

  ProgramBuilder.prototype.syncNavigatorSelection = function() {
    if (!this.navigatorTree || !this.state.navigatorSelection) {
      return;
    }
    const selection = this.state.navigatorSelection;
    let matchedNode = null;
    this.navigatorTree.element.find(".k-item").each((_, node) => {
      const item = this.navigatorTree.dataItem(node);
      if (item && item.type === selection.type && String(item.code || "") === String(selection.code || "")) {
        matchedNode = node;
        return false;
      }
      return undefined;
    });
    if (matchedNode) {
      this.navigatorTree.select($(matchedNode));
    }
  };

  ProgramBuilder.prototype.syncNavigatorActions = function() {
    const buttons = this.navigatorActionButtons || {};
    const selection = this.state.navigatorSelection || {};
    const type = selection.type || "";
    const hasRelatedProgram = !!this.findProgramByEntityCode(selection.code);
    const hasCurrentVersion = !!(this.state.currentVersion && this.state.currentVersion.id);
    const enable = function(button, allowed) {
      if (button && typeof button.enable === "function") {
        button.enable(allowed);
      }
    };
    enable(buttons.newEntity, true);
    enable(buttons.newProgram, type === "entity" || !!this.state.currentEntityCode);
    enable(buttons.editModule, type === "module");
    enable(buttons.openEntity, type === "entity");
    enable(buttons.openProgram, type === "program");
    enable(buttons.openRelatedProgram, type === "entity" && hasRelatedProgram);
    enable(buttons.generatePreview, type === "entity" || type === "program" || !!this.state.currentEntityCode);
    enable(buttons.duplicateVersion, type === "program" && hasCurrentVersion);
    enable(buttons.publishVersion, type === "program" && hasCurrentVersion);
  };

  ProgramBuilder.prototype.openNavigatorSelection = function(type, code) {
    if (!type || !code) {
      return;
    }
    if (type === "module") {
      const module = this.findModuleSummary(code);
      if (module) {
        this.populateModuleForm(module);
        this.activateEditorTab(0);
      }
      return;
    }
    if (type === "entity") {
      this.entitySelectorInput.value(code);
      this.handleEntitySelection();
      return;
    }
    if (type === "program") {
      this.programSelectorInput.value(code);
      this.handleProgramSelection();
    }
  };

  ProgramBuilder.prototype.updateWorkspaceSummary = function() {
    if (!this.workspaceSummary) {
      return;
    }
    this.workspaceSummary.empty();
    const cards = [];
    if (this.state.currentEntityCode) {
      const entitySummary = this.findEntitySummary(this.state.currentEntityCode) || {};
      const currentFieldCount = this.collectCurrentFieldRows ? this.collectCurrentFieldRows().length : 0;
      const currentFkCount = this.countCurrentForeignKeys();
      const currentRulesCount = this.countCurrentRules();
      cards.push({
        title: "Entidade atual",
        value: this.state.currentEntityCode,
        detail: this.entityNameInput && this.entityNameInput.value ? String(this.entityNameInput.value() || "") : ""
      });
      cards.push({
        title: "Estrutura",
        value: (this.entityTypeSelect.value() || "persistence") + (this.entityTableNameInput.value() ? " / " + this.entityTableNameInput.value() : ""),
        detail: currentFieldCount + " campos, " + currentFkCount + " FKs, " + currentRulesCount + " regras"
      });
      cards.push({
        title: "Historico",
        value: this.entityVersioningEnabledInput.is(":checked") ? "Versionada" : "Sem historico",
        detail: entitySummary.status ? "Status: " + entitySummary.status : "Ainda nao publicada"
      });
    }
    if (this.state.currentProgramCode) {
      const programSummary = this.findProgramSummary(this.state.currentProgramCode) || {};
      cards.push({
        title: "Programa atual",
        value: this.state.currentProgramCode,
        detail: this.programTitleInput && this.programTitleInput.value ? String(this.programTitleInput.value() || "") : ""
      });
      cards.push({
        title: "Publicacao",
        value: programSummary.status || (this.state.currentVersion && this.state.currentVersion.status) || "draft",
        detail: programSummary.publishedVersion ? "Publicado em " + programSummary.publishedVersion : "Sem versao publicada"
      });
    }
    cards.push({
      title: "Modo de trabalho",
      value: "Editor web Kendo",
      detail: "Arvore, formularios contextuais e preview lateral"
    });
    cards.forEach(function(item) {
      const card = $("<article class=\"program-builder-summary-card\"></article>").appendTo(this.workspaceSummary);
      $("<span></span>").text(item.title).appendTo(card);
      $("<strong></strong>").text(item.value).appendTo(card);
      $("<small></small>").text(item.detail).appendTo(card);
    }, this);
    this.renderDiagnostics();
  };

  ProgramBuilder.prototype.renderDiagnostics = function() {
    if (!this.diagnosticsElement) {
      return;
    }
    const diagnostics = this.collectDiagnostics();
    this.diagnosticsElement.empty();
    if (!diagnostics.length) {
      $("<div class=\"program-builder-diagnostic-item is-ok\"></div>")
        .append($("<strong></strong>").text("Sem pendencias relevantes."))
        .append($("<span></span>").text("A configuracao corrente ja atende os pontos basicos."))
        .appendTo(this.diagnosticsElement);
      return;
    }
    diagnostics.forEach(function(item) {
      const row = $("<div class=\"program-builder-diagnostic-item\"></div>").appendTo(this.diagnosticsElement);
      row.addClass("is-" + item.level);
      $("<strong></strong>").text(item.title).appendTo(row);
      $("<span></span>").text(item.message).appendTo(row);
      if (item.actionId) {
        $("<button type=\"button\" class=\"program-builder-diagnostic-action\"></button>").text("Ir para").appendTo(row).kendoButton({
          size: "small",
          click: function() {
            this.performDiagnosticAction(item.actionId);
          }.bind(this)
        });
      }
    }, this);
  };

  ProgramBuilder.prototype.collectDiagnostics = function() {
    const diagnostics = [];
    const entityCode = String(this.entityCodeInput && this.entityCodeInput.value ? this.entityCodeInput.value() || "" : "").trim();
    const entityName = String(this.entityNameInput && this.entityNameInput.value ? this.entityNameInput.value() || "" : "").trim();
    const entityType = String(this.entityTypeSelect && this.entityTypeSelect.value ? this.entityTypeSelect.value() || "" : "persistence");
    const tableName = String(this.entityTableNameInput && this.entityTableNameInput.value ? this.entityTableNameInput.value() || "" : "").trim();
    const fields = this.fieldsTableBody ? this.collectCurrentFieldRows() : [];
    const fieldConfigs = [];
    if (this.fieldsTableBody) {
      this.fieldsTableBody.find("tr").filter(function() {
        return !$(this).hasClass("program-builder-field-details-row");
      }).each(function(_, row) {
        const $row = $(row);
        fieldConfigs.push({
          code: String($row.find(".program-builder-field-code").val() || "").trim(),
          pk: $row.find(".program-builder-field-pk").is(":checked")
        });
      });
    }

    if (!entityCode) {
      diagnostics.push({ level: "warn", title: "Entidade sem codigo", message: "Defina o codigo tecnico da entidade.", actionId: "entity.code" });
    }
    if (!entityName) {
      diagnostics.push({ level: "warn", title: "Entidade sem nome", message: "Informe o nome funcional da entidade.", actionId: "entity.name" });
    }
    if (entityType === "persistence" && !tableName) {
      diagnostics.push({ level: "error", title: "Tabela fisica ausente", message: "Entidades persistence precisam de tabela fisica.", actionId: "entity.tableName" });
    }
    if (!fields.length) {
      diagnostics.push({ level: "error", title: "Sem campos", message: "Adicione ao menos um campo na entidade.", actionId: "entity.fields" });
    }
    if (fields.length && !fieldConfigs.some(function(item) { return item.pk; })) {
      diagnostics.push({ level: "error", title: "Chave primaria ausente", message: "Marque ao menos um campo como PK.", actionId: "entity.fields" });
    }

    const programCode = String(this.programCodeInput && this.programCodeInput.value ? this.programCodeInput.value() || "" : "").trim();
    const programTitle = String(this.programTitleInput && this.programTitleInput.value ? this.programTitleInput.value() || "" : "").trim();
    const programModule = String(this.moduleInput && this.moduleInput.value ? this.moduleInput.value() || "" : "").trim();
    const screenId = String(this.screenIdInput && this.screenIdInput.value ? this.screenIdInput.value() || "" : "").trim();
    const builderEntityCode = String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "").trim();
    const version = String(this.versionInput && this.versionInput.value ? this.versionInput.value() || "" : "").trim();

    if (builderEntityCode || programCode || programTitle || screenId) {
      if (!programCode) {
        diagnostics.push({ level: "warn", title: "Programa sem codigo", message: "Informe o codigo tecnico do programa.", actionId: "program.code" });
      }
      if (!programTitle) {
        diagnostics.push({ level: "warn", title: "Programa sem titulo", message: "Informe o titulo do programa.", actionId: "program.title" });
      }
      if (!programModule) {
        diagnostics.push({ level: "warn", title: "Modulo do programa ausente", message: "Selecione o modulo do programa.", actionId: "program.module" });
      }
      if (!screenId) {
        diagnostics.push({ level: "warn", title: "Screen ID ausente", message: "Defina o Screen ID publicado no runtime.", actionId: "program.screenId" });
      }
      if (!builderEntityCode) {
        diagnostics.push({ level: "warn", title: "Entidade base ausente", message: "Selecione a entidade usada para gerar o programa.", actionId: "program.entity" });
      }
      if (!version) {
        diagnostics.push({ level: "warn", title: "Versao ausente", message: "Informe a versao do programa.", actionId: "program.version" });
      }
    }

    if (this.state.currentVersion && this.state.currentVersion.status && this.state.currentVersion.status !== "draft") {
      diagnostics.push({ level: "info", title: "Versao travada", message: "A versao selecionada nao e rascunho; duplique antes de editar." });
    }

    return diagnostics;
  };

  ProgramBuilder.prototype.performDiagnosticAction = function(actionId) {
    const focusInput = function(widget) {
      if (!widget) {
        return;
      }
      const input = widget.input || widget.element;
      if (input && typeof input.trigger === "function") {
        input.trigger("focus");
      } else if (input && input[0] && typeof input[0].focus === "function") {
        input[0].focus();
      }
    };
    switch (actionId) {
      case "entity.code":
        this.activateEditorTab(1);
        focusInput(this.entityCodeInput);
        break;
      case "entity.name":
        this.activateEditorTab(1);
        focusInput(this.entityNameInput);
        break;
      case "entity.tableName":
        this.activateEditorTab(1);
        focusInput(this.entityTableNameInput);
        break;
      case "entity.fields":
        this.activateEditorTab(1);
        this.fieldsTableElement[0].scrollIntoView({ block: "center", behavior: "smooth" });
        break;
      case "program.code":
        this.activateEditorTab(2);
        focusInput(this.programCodeInput);
        break;
      case "program.title":
        this.activateEditorTab(2);
        focusInput(this.programTitleInput);
        break;
      case "program.module":
        this.activateEditorTab(2);
        this.moduleInput.open();
        break;
      case "program.screenId":
        this.activateEditorTab(2);
        focusInput(this.screenIdInput);
        break;
      case "program.entity":
        this.activateEditorTab(2);
        this.builderEntitySelect.open();
        break;
      case "program.version":
        this.activateEditorTab(2);
        focusInput(this.versionInput);
        break;
      default:
        break;
    }
  };

  ProgramBuilder.prototype.countCurrentForeignKeys = function() {
    let count = 0;
    if (!this.fieldsTableBody) {
      return 0;
    }
    this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).each(function(_, row) {
      const details = $(row).next(".program-builder-field-details-row");
      if (String(details.find(".program-builder-field-fk-table").val() || "").trim()) {
        count += 1;
      }
    });
    return count;
  };

  ProgramBuilder.prototype.countCurrentRules = function() {
    if (!this.rulesTableBody) {
      return 0;
    }
    return this.rulesTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-rule-details-row");
    }).length;
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
      this.activateEditorTab(1);
      this.activateSideTab(4);
    }).catch((error) => {
      this.handleError(error, "Nao foi possivel carregar a entidade.");
    });
  };

  ProgramBuilder.prototype.handleModuleSelection = function() {
    const item = this.modulesGrid.dataItem(this.modulesGrid.select());
    if (item) {
      this.populateModuleForm(item.toJSON ? item.toJSON() : item);
      this.activateEditorTab(0);
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
    this.setNavigatorSelection(item.code ? "module" : null, item.code || "");
    this.selectPropertyNode("module", { code: item.code || "" });
    this.ensureEditorLock("module", item.code || "", item.name || item.code || "");
    this.updateWorkspaceSummary();
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
    if (!this.state.currentEntityCode && !this.state.currentProgramCode) {
      this.setNavigatorSelection(null, "");
    }
    this.selectPropertyNode("module", { code: "" });
    this.updateWorkspaceSummary();
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
    this.entitySubscriberIsolationSelect.value(item.subscriberIsolationMode || "none");
    this.entitySubscriberColumnInput.value(item.subscriberColumnName || "subscriber_id");
    this.entitySubscriberGlobalTableInput.prop("checked", item.subscriberGlobalTable === true);
    this.entityVersioningEnabledInput.prop("checked", item.versioningEnabled === true);
    this.entityVersioningDeduplicateInput.prop("checked", item.versioningDeduplicate !== false);
    this.apiCatalogSourceSelect.value(item.apiSourceCode || "");
    this.apiCatalogListOperationSelect.value("");
    this.apiCatalogDetailOperationSelect.value("");
    this.apiCatalogCreateOperationSelect.value("");
    this.apiCatalogUpdateOperationSelect.value("");
    this.apiCatalogDeleteOperationSelect.value("");
    const apiSource = item.apiSource || {};
    this.apiBaseUrlInput.value(apiSource.baseUrl || "");
    this.apiTimeoutInput.value(apiSource.timeoutSeconds != null ? String(apiSource.timeoutSeconds) : "20");
    this.apiAuthHeadersInput.val(apiSource.authHeaders ? JSON.stringify(apiSource.authHeaders, null, 2) : "");
    this.apiListUrlInput.value(apiSource.listEndpoint && apiSource.listEndpoint.url || "");
    this.apiListMethodSelect.value(apiSource.listEndpoint && apiSource.listEndpoint.method || "GET");
    this.apiListItemsPathInput.value(apiSource.listResponse && apiSource.listResponse.itemsPath || "");
    this.apiListTotalPathInput.value(apiSource.listResponse && apiSource.listResponse.totalPath || "");
    this.apiListHeadersInput.val(apiSource.listEndpoint && apiSource.listEndpoint.headers ? JSON.stringify(apiSource.listEndpoint.headers, null, 2) : "");
    this.apiListQueryInput.val(apiSource.listEndpoint && apiSource.listEndpoint.queryParams ? JSON.stringify(apiSource.listEndpoint.queryParams, null, 2) : "");
    this.apiListBodyInput.val(apiSource.listEndpoint && apiSource.listEndpoint.bodyTemplate != null ? JSON.stringify(apiSource.listEndpoint.bodyTemplate, null, 2) : "");
    this.apiDetailUrlInput.value(apiSource.detailEndpoint && apiSource.detailEndpoint.url || "");
    this.apiDetailMethodSelect.value(apiSource.detailEndpoint && apiSource.detailEndpoint.method || "GET");
    this.apiDetailItemPathInput.value(apiSource.detailResponse && apiSource.detailResponse.itemPath || "");
    this.apiDetailHeadersInput.val(apiSource.detailEndpoint && apiSource.detailEndpoint.headers ? JSON.stringify(apiSource.detailEndpoint.headers, null, 2) : "");
    this.apiDetailQueryInput.val(apiSource.detailEndpoint && apiSource.detailEndpoint.queryParams ? JSON.stringify(apiSource.detailEndpoint.queryParams, null, 2) : "");
    this.apiDetailBodyInput.val(apiSource.detailEndpoint && apiSource.detailEndpoint.bodyTemplate != null ? JSON.stringify(apiSource.detailEndpoint.bodyTemplate, null, 2) : "");
    if (item.apiSourceCode) {
      this.loadApiSourceDefinition(item.apiSourceCode).then(function(sourceDefinition) {
        const operations = Array.isArray(sourceDefinition && sourceDefinition.operations) ? sourceDefinition.operations : [];
        this.apiCatalogListOperationSelect.setDataSource(new kendo.data.DataSource({
          data: operations.filter(function(entry) { return entry.type === "list"; }).map(function(entry) {
            return { code: entry.code, name: entry.code + " - " + entry.name };
          })
        }));
        this.apiCatalogDetailOperationSelect.setDataSource(new kendo.data.DataSource({
          data: operations.filter(function(entry) { return entry.type === "detail"; }).map(function(entry) {
            return { code: entry.code, name: entry.code + " - " + entry.name };
          })
        }));
        this.apiCatalogCreateOperationSelect.setDataSource(new kendo.data.DataSource({
          data: operations.filter(function(entry) { return entry.type === "create"; }).map(function(entry) {
            return { code: entry.code, name: entry.code + " - " + entry.name };
          })
        }));
        this.apiCatalogUpdateOperationSelect.setDataSource(new kendo.data.DataSource({
          data: operations.filter(function(entry) { return entry.type === "update"; }).map(function(entry) {
            return { code: entry.code, name: entry.code + " - " + entry.name };
          })
        }));
        this.apiCatalogDeleteOperationSelect.setDataSource(new kendo.data.DataSource({
          data: operations.filter(function(entry) { return entry.type === "delete"; }).map(function(entry) {
            return { code: entry.code, name: entry.code + " - " + entry.name };
          })
        }));
        this.apiCatalogListOperationSelect.value(item.apiListOperationCode || "");
        this.apiCatalogDetailOperationSelect.value(item.apiDetailOperationCode || "");
        this.apiCatalogCreateOperationSelect.value(item.apiCreateOperationCode || "");
        this.apiCatalogUpdateOperationSelect.value(item.apiUpdateOperationCode || "");
        this.apiCatalogDeleteOperationSelect.value(item.apiDeleteOperationCode || "");
        this.applyApiSourceToInlineEditor(sourceDefinition);
        this.syncApiBindingState();
        this.syncProgramWriteFlagsForApi();
      }.bind(this));
    } else {
      this.apiCatalogListOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
      this.apiCatalogDetailOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
      this.apiCatalogCreateOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
      this.apiCatalogUpdateOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
      this.apiCatalogDeleteOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
      this.syncApiBindingState();
    }
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
    this.syncSubscriberIsolationState();
    this.syncStructureState();
    this.syncSituationFieldState();
    this.refreshHistoricalAssistantSourceFields();
    this.builderEntitySelect.value(item.code || "");
    this.handleProgramEntityChange();
    this.syncSelectedEntityVersionRow();
    this.syncToolbarState();
    this.setNavigatorSelection(item.code ? "entity" : null, item.code || "");
    this.selectPropertyNode("entity", { code: item.code || "" });
    this.validateIncremental();
    this.refreshCompareChoices();
    this.renderRelationshipView();
    this.updateWorkspaceSummary();
    this.ensureEditorLock("entity", item.code || "", item.name || item.code || "");
  };

  ProgramBuilder.prototype.renderFieldRows = function(fields) {
    this.fieldsTableBody.empty();
    const rows = fields && fields.length ? fields : [this.defaultFieldRow(), this.defaultNameFieldRow()];
    rows.forEach(this.addFieldRow.bind(this));
    this.refreshHistoricalAssistantSourceFields();
    this.validateIncremental();
  };

  ProgramBuilder.prototype.renderRuleRows = function(rules) {
    this.rulesTableBody.empty();
    (rules || []).forEach(this.addRuleRow.bind(this));
    this.validateIncremental();
  };

  ProgramBuilder.prototype.renderUniqueKeyRows = function(keys) {
    this.uniqueKeysTableBody.empty();
    (keys || []).forEach(this.addUniqueKeyRow.bind(this));
    this.validateIncremental();
  };

  ProgramBuilder.prototype.addUniqueKeyRow = function(key) {
    const item = key || {};
    const index = this.uniqueKeysTableBody.children("tr").length;
    const row = $("<tr class=\"program-builder-unique-key-row\"></tr>").appendTo(this.uniqueKeysTableBody);
    row.attr("data-drag-type", "uniqueKey");
    row.attr("data-drag-index", index);
    $("<td class=\"program-builder-drag-cell\"><button type=\"button\" draggable=\"true\" class=\"program-builder-row-handle\" title=\"Arrastar para reordenar\">::</button><span class=\"program-builder-row-status\"></span></td>").appendTo(row);
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
    this.selectPropertyNode("uniqueKey", { index: this.uniqueKeysTableBody.find(".program-builder-unique-key-row").length - 1 });
    this.handleEditorMutation();
  };

  ProgramBuilder.prototype.handleRemoveUniqueKeyRow = function(row) {
    row.remove();
    this.handleEditorMutation();
  };

  ProgramBuilder.prototype.addRuleRow = function(rule) {
    const item = rule || {};
    const index = this.rulesTableBody.find(".program-builder-rule-row").length;
    const row = $("<tr class=\"program-builder-rule-row\"></tr>").appendTo(this.rulesTableBody);
    row.attr("data-drag-type", "rule");
    row.attr("data-drag-index", index);
    $("<td class=\"program-builder-drag-cell\"><button type=\"button\" draggable=\"true\" class=\"program-builder-row-handle\" title=\"Arrastar para reordenar\">::</button><span class=\"program-builder-row-status\"></span></td>").appendTo(row);
    $("<td><input type=\"number\" min=\"0\" class=\"program-builder-mini-input program-builder-rule-order\"></td>").appendTo(row).find("input").val(item.order != null ? item.order : ((this.rulesTableBody.find(".program-builder-rule-row").length + 1) * 10));

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
    const detailsCell = $("<td colspan=\"8\"></td>").appendTo(detailsRow);
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
    this.selectPropertyNode("rule", { index: this.rulesTableBody.find(".program-builder-rule-row").length - 1 });
    this.handleEditorMutation();
  };

  ProgramBuilder.prototype.handleRemoveRuleRow = function(row, detailsRow) {
    row.remove();
    detailsRow.remove();
    this.handleEditorMutation();
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
    const index = this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).length;
    const row = $("<tr></tr>").appendTo(this.fieldsTableBody);
    row.attr("data-drag-type", "field");
    row.attr("data-drag-index", index);
    row.attr("data-field-id", item.id || "");
    row.attr("data-original-code", item.originalCode || item.code || "");
    row.attr("data-original-column", item.originalColumnName || item.columnName || item.code || "");
    $("<td class=\"program-builder-drag-cell\"><button type=\"button\" draggable=\"true\" class=\"program-builder-row-handle\" title=\"Arrastar para reordenar\">::</button><span class=\"program-builder-row-status\"></span></td>").appendTo(row);
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
    const detailsCell = $("<td colspan=\"9\"></td>").appendTo(detailsRow);
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

    const apiJsonPathField = this.appendField(detailsGrid, "JSON path");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-api-json-path\">").appendTo(apiJsonPathField).val(item.apiJsonPath || item.code || "");
    const apiWritePathField = this.appendField(detailsGrid, "Write path");
    $("<input type=\"text\" class=\"program-builder-mini-input program-builder-field-api-write-path\">").appendTo(apiWritePathField).val(item.apiWritePath || item.apiJsonPath || item.code || "");

    const apiShowGridField = this.appendField(detailsGrid, "Exibir no grid");
    $("<input type=\"checkbox\" class=\"program-builder-field-api-show-grid\">").appendTo(apiShowGridField).prop("checked", item.apiShowInGrid !== false);

    const apiShowFormField = this.appendField(detailsGrid, "Exibir no formulario");
    $("<input type=\"checkbox\" class=\"program-builder-field-api-show-form\">").appendTo(apiShowFormField).prop("checked", item.apiShowInForm !== false);

    const apiShowFilterField = this.appendField(detailsGrid, "Exibir no filtro");
    $("<input type=\"checkbox\" class=\"program-builder-field-api-show-filter\">").appendTo(apiShowFilterField).prop("checked", item.apiShowInFilter === true);

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
    detailsRow.find(".program-builder-field-api-show-grid").kendoCheckBox();
    detailsRow.find(".program-builder-field-api-show-form").kendoCheckBox();
    detailsRow.find(".program-builder-field-api-show-filter").kendoCheckBox();
    detailsRow.find(".program-builder-field-virtual").kendoCheckBox();
    detailsRow.find(".program-builder-field-include-version").kendoCheckBox();
    detailsRow.find(".program-builder-field-custom-code-sequence-enabled").kendoCheckBox();

    row.find(".program-builder-field-code").on("input", function() {
      const code = String($(this).val() || "").trim().toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^_+|_+$/g, "");
      const columnInput = row.find(".program-builder-field-column");
      const apiPathInput = detailsRow.find(".program-builder-field-api-json-path");
      if (!String(columnInput.val() || "").trim()) {
        columnInput.val(code);
      }
      if (!String(apiPathInput.val() || "").trim()) {
        apiPathInput.val(code);
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
    this.selectPropertyNode("field", {
      index: this.fieldsTableBody.find("tr").filter(function() {
        return !$(this).hasClass("program-builder-field-details-row");
      }).length - 1
    });
    this.handleEditorMutation();
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
    this.handleEditorMutation();
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
      apiJsonPath: "id",
      apiShowInGrid: true,
      apiShowInForm: true,
      apiShowInFilter: false,
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
      apiJsonPath: "nome",
      apiShowInGrid: true,
      apiShowInForm: true,
      apiShowInFilter: true,
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
    const apiEntity = entityType === "api";
    this.entityTableNameInput.enable(persistence);
    this.entityCreateTableInput.prop("checked", persistence ? this.entityCreateTableInput.is(":checked") : false);
    this.entityCreateTableInput.prop("disabled", !persistence);
    this.entityAllowTableRenameInput.prop("disabled", !persistence);
    this.entityAllowColumnRenameInput.prop("disabled", !persistence);
    this.entityDropRemovedColumnsInput.prop("disabled", !persistence);
    this.entitySituationEnabledInput.prop("disabled", !persistence);
    this.entityVersioningEnabledInput.prop("disabled", !persistence);
    this.entityVersioningDeduplicateInput.prop("disabled", !persistence || !this.entityVersioningEnabledInput.is(":checked"));
    if (this.entitySubscriberIsolationSelect) {
      this.entitySubscriberIsolationSelect.enable(persistence);
    }
    this.apiSourcePanel.toggle(apiEntity);
    if (this.uniqueKeysPanel) {
      this.uniqueKeysPanel.toggle(!apiEntity);
    }
    if (this.rulesPanel) {
      this.rulesPanel.toggle(!apiEntity);
    }
    if (this.historyAssistantPanel) {
      this.historyAssistantPanel.toggle(persistence);
    }
    if (!persistence) {
      this.entitySituationEnabledInput.prop("checked", false);
      this.entityVersioningEnabledInput.prop("checked", false);
      this.entityAllowTableRenameInput.prop("checked", false);
      this.entityAllowColumnRenameInput.prop("checked", false);
      this.entityDropRemovedColumnsInput.prop("checked", false);
      if (this.entitySubscriberIsolationSelect) {
        this.entitySubscriberIsolationSelect.value("none");
      }
      if (this.entitySubscriberColumnInput) {
        this.entitySubscriberColumnInput.value("");
      }
      if (this.entitySubscriberGlobalTableInput) {
        this.entitySubscriberGlobalTableInput.prop("checked", false);
      }
      if (apiEntity) {
        this.entityTypeHint.text("Tipo api gera CRUD somente leitura, sem tabela fisica e sem lock de escrita.");
      } else {
        this.entityTypeHint.text("Tipos query e io ja podem ser cadastrados, mas a criacao fisica e a geracao CRUD continuam focadas em persistence.");
      }
      if (entityType === "query") {
        this.entityStructureTypeSelect.value("view");
      }
    } else {
      this.entityTypeHint.text("Fluxo completo atual: tipo persistence com tabela fisica e programa CRUD.");
    }
    this.syncApiBindingState();
    this.syncStructureState();
    this.syncSituationFieldState();
    this.syncSubscriberIsolationState();
    this.syncProgramWriteFlagsForApi();
    this.fieldsTableBody.find("tr").filter(function() {
      return !$(this).hasClass("program-builder-field-details-row");
    }).each(function(_, row) {
      this.syncFieldRowState($(row), $(row).next(".program-builder-field-details-row"));
    }.bind(this));
  };

  ProgramBuilder.prototype.syncSubscriberIsolationState = function() {
    if (!this.entitySubscriberIsolationSelect || !this.entitySubscriberColumnInput || !this.entitySubscriberGlobalTableInput) {
      return;
    }
    const persistence = (this.entityTypeSelect.value() || "persistence") === "persistence";
    const filtered = String(this.entitySubscriberIsolationSelect.value() || "none") === "subscriber_column";
    const globalTable = this.entitySubscriberGlobalTableInput.is(":checked");
    this.entitySubscriberColumnInput.enable(persistence && filtered);
    this.entitySubscriberGlobalTableInput.data("kendoCheckBox").enable(persistence && !filtered);
    if (!persistence) {
      this.entitySubscriberIsolationSelect.value("none");
      this.entitySubscriberColumnInput.value("");
      this.entitySubscriberGlobalTableInput.prop("checked", false);
      this.entityTypeHint.text("Entidades nao persistentes nao usam isolamento por coluna de assinante.");
      return;
    }
    if (!filtered) {
      this.entitySubscriberColumnInput.value("");
      this.entityTypeHint.text(globalTable
        ? "Tabela global confirmada. O runtime nao filtrara registros por assinante."
        : "Defina se a tabela e global compartilhada ou filtrada por coluna de assinante.");
      return;
    }
    this.entitySubscriberGlobalTableInput.prop("checked", false);
    if (!String(this.entitySubscriberColumnInput.value() || "").trim()) {
      this.entitySubscriberColumnInput.value("subscriber_id");
    }
    this.entityTypeHint.text("Tabela filtrada por assinante. O runtime vai aplicar a coluna configurada automaticamente.");
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
    const entityType = this.entityTypeSelect.value();
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
        apiJsonPath: $details.find(".program-builder-field-api-json-path").val(),
        apiWritePath: $details.find(".program-builder-field-api-write-path").val(),
        apiShowInGrid: $details.find(".program-builder-field-api-show-grid").is(":checked"),
        apiShowInForm: $details.find(".program-builder-field-api-show-form").is(":checked"),
        apiShowInFilter: $details.find(".program-builder-field-api-show-filter").is(":checked"),
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
      entityType: entityType,
      tableName: this.entityTableNameInput.value(),
      apiSourceCode: entityType === "api" ? String(this.apiCatalogSourceSelect.value() || "").trim() : "",
      apiListOperationCode: entityType === "api" ? String(this.apiCatalogListOperationSelect.value() || "").trim() : "",
      apiDetailOperationCode: entityType === "api" ? String(this.apiCatalogDetailOperationSelect.value() || "").trim() : "",
      apiCreateOperationCode: entityType === "api" ? String(this.apiCatalogCreateOperationSelect.value() || "").trim() : "",
      apiUpdateOperationCode: entityType === "api" ? String(this.apiCatalogUpdateOperationSelect.value() || "").trim() : "",
      apiDeleteOperationCode: entityType === "api" ? String(this.apiCatalogDeleteOperationSelect.value() || "").trim() : "",
      apiSource: entityType === "api" ? {
        baseUrl: this.apiBaseUrlInput.value(),
        timeoutSeconds: this.apiTimeoutInput.value(),
        authHeaders: this.parseJsonObjectText(this.apiAuthHeadersInput.val(), "ENTITY_API_AUTH_HEADERS_INVALID"),
        listEndpoint: {
          url: this.apiListUrlInput.value(),
          method: this.apiListMethodSelect.value(),
          headers: this.parseJsonObjectText(this.apiListHeadersInput.val(), "ENTITY_API_LIST_HEADERS_INVALID"),
          queryParams: this.parseJsonObjectText(this.apiListQueryInput.val(), "ENTITY_API_LIST_QUERY_INVALID"),
          bodyTemplate: this.parseJsonValueText(this.apiListBodyInput.val(), "ENTITY_API_LIST_BODY_INVALID")
        },
        listResponse: {
          itemsPath: this.apiListItemsPathInput.value(),
          totalPath: this.apiListTotalPathInput.value()
        },
        detailEndpoint: {
          url: this.apiDetailUrlInput.value(),
          method: this.apiDetailMethodSelect.value(),
          headers: this.parseJsonObjectText(this.apiDetailHeadersInput.val(), "ENTITY_API_DETAIL_HEADERS_INVALID"),
          queryParams: this.parseJsonObjectText(this.apiDetailQueryInput.val(), "ENTITY_API_DETAIL_QUERY_INVALID"),
          bodyTemplate: this.parseJsonValueText(this.apiDetailBodyInput.val(), "ENTITY_API_DETAIL_BODY_INVALID")
        },
        detailResponse: {
          itemPath: this.apiDetailItemPathInput.value()
        }
      } : null,
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
      subscriberIsolationMode: this.entitySubscriberIsolationSelect.value(),
      subscriberColumnName: this.entitySubscriberColumnInput.value(),
      subscriberGlobalTable: this.entitySubscriberGlobalTableInput.is(":checked"),
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
    this.releaseCurrentLock();
    this.resetEntityForm();
    this.bannerElement.text("Nova entidade. Defina os campos e salve para criar os metadados e a tabela fisica.");
    this.activateEditorTab(1);
    this.activateSideTab(0);
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
    if (String(this.pageTypeSelect.value() || "crud") !== "crud") {
      this.schedulePreview();
      return;
    }
    const entityCode = String(this.builderEntitySelect.value() || "");
    if (!entityCode) {
      this.schedulePreview();
      return;
    }

    const entity = this.findEntitySummary(entityCode);
    if (entity && entity.entityType === "api") {
      if (this.state.currentEntityCode === entityCode) {
        this.syncProgramWriteFlagsForApi();
      }
      this.previewFooter.text("Entidade API usa operacoes do cadastro da API. Habilite apenas create/update/delete que existirem no contrato.");
      this.syncProgramTypeState();
    } else if (entity && entity.entityType && entity.entityType !== "persistence") {
      this.previewFooter.text("Nesta etapa, a geracao de programa CRUD continua suportada apenas para entidades persistence e api.");
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

  ProgramBuilder.prototype.findProgramSummary = function(programCode) {
    const code = String(programCode || "");
    return this.state.programs.find(function(item) {
      return item.code === code;
    }) || null;
  };

  ProgramBuilder.prototype.findProgramByEntityCode = function(entityCode) {
    const code = String(entityCode || "");
    return this.state.programs.find(function(item) {
      return String(item.builderEntityCode || "") === code;
    }) || null;
  };

  ProgramBuilder.prototype.handleNavigatorNewProgram = function() {
    this.activateEditorTab(2);
    this.activateSideTab(0);
    this.handleNewDraft();
    const selection = this.state.navigatorSelection || {};
    if (selection.type === "module" && selection.code) {
      this.moduleInput.value(selection.code);
    }
    if (selection.type === "entity" && selection.code) {
      this.builderEntitySelect.value(selection.code);
      const entity = this.findEntitySummary(selection.code);
      if (entity && entity.structureModuleCode) {
        this.moduleInput.value(entity.structureModuleCode);
      }
      this.handleProgramEntityChange(true);
    } else if (this.state.currentEntityCode) {
      this.builderEntitySelect.value(this.state.currentEntityCode);
      this.handleProgramEntityChange(true);
    }
  };

  ProgramBuilder.prototype.handleNavigatorEditModule = function() {
    const selection = this.state.navigatorSelection || {};
    if (selection.type !== "module") {
      return;
    }
    const module = this.findModuleSummary(selection.code);
    if (!module) {
      return;
    }
    this.populateModuleForm(module);
    this.activateEditorTab(0);
  };

  ProgramBuilder.prototype.handleNavigatorOpenEntity = function() {
    const selection = this.state.navigatorSelection || {};
    if (selection.type !== "entity" || !selection.code) {
      return;
    }
    this.entitySelectorInput.value(selection.code);
    this.handleEntitySelection();
  };

  ProgramBuilder.prototype.handleNavigatorOpenProgram = function() {
    const selection = this.state.navigatorSelection || {};
    if (selection.type !== "program" || !selection.code) {
      return;
    }
    this.programSelectorInput.value(selection.code);
    this.handleProgramSelection();
  };

  ProgramBuilder.prototype.handleNavigatorOpenRelatedProgram = function() {
    const selection = this.state.navigatorSelection || {};
    if (selection.type !== "entity" || !selection.code) {
      return;
    }
    const program = this.findProgramByEntityCode(selection.code);
    if (!program) {
      global.CrudUtils.showMessage("Nao existe programa ligado para esta entidade.", "warning");
      return;
    }
    this.programSelectorInput.value(program.code);
    this.handleProgramSelection();
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
      this.activateEditorTab(2);
      this.activateSideTab(3);
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
    this.pageTypeSelect.value(version.pageType || "crud");
    this.programOriginSelect.value(version.programOrigin || "standard");
    this.ownerScopeSelect.value(version.ownerScope || "system");
    this.customizationPolicySelect.value(version.customizationPolicy || "overlay_only");
    this.subscriberIdInput.value(version.subscriberId || "");
    this.baseProgramCodeInput.value(version.baseProgramCode || "");
    this.baseProgramVersionIdInput.value(version.baseProgramVersionId != null ? String(version.baseProgramVersionId) : "");
    this.upgradeFrozenInput.prop("checked", version.upgradeFrozen === true);
    this.frozenReasonInput.value(version.frozenReason || "");
    this.publicationPolicyInput.value(this.stringifyPublicationPolicy(version.builderConfig && version.builderConfig.publicationPolicy));
    this.builderEntitySelect.value(version.builderEntityCode || "");
    this.customModeSelect.value(version.customMode || "iframe");
    this.customEntryUrlInput.value(version.customEntryUrl || "");
    this.customFrameTitleInput.value(version.customFrameTitle || "");
    this.allowCreateInput.prop("checked", version.allowCreate !== false);
    this.allowUpdateInput.prop("checked", version.allowUpdate !== false);
    this.allowDeleteInput.prop("checked", version.allowDelete === true);
    this.changeSummaryTextArea.value(version.changeSummary || "");
    this.syncProgramTypeState();
    this.renderDefinition(version.generatedDefinition || {});
    this.updatePreviewMeta(version);
    this.updateBanner();
    this.syncSelectedVersionRow();
    this.syncToolbarState();
    this.setNavigatorSelection(version.programCode ? "program" : null, version.programCode || "");
    this.selectPropertyNode("program", { code: version.programCode || "" });
    this.refreshCompareChoices();
    this.updateWorkspaceSummary();
    this.ensureEditorLock("program", version.programCode || "", version.programTitle || version.programCode || "");
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
    this.pageTypeSelect.value("crud");
    this.programOriginSelect.value("standard");
    this.ownerScopeSelect.value("system");
    this.customizationPolicySelect.value("overlay_only");
    this.subscriberIdInput.value("");
    this.baseProgramCodeInput.value("");
    this.baseProgramVersionIdInput.value("");
    this.upgradeFrozenInput.prop("checked", false);
    this.frozenReasonInput.value("");
    this.publicationPolicyInput.value("");
    this.builderEntitySelect.value(this.state.currentEntityCode || "");
    this.customModeSelect.value("iframe");
    this.customEntryUrlInput.value("");
    this.customFrameTitleInput.value("");
    this.allowCreateInput.prop("checked", true);
    this.allowUpdateInput.prop("checked", true);
    this.allowDeleteInput.prop("checked", false);
    this.changeSummaryTextArea.value("");
    this.syncProgramTypeState();
    this.state.preview = null;
    this.renderDefinition({});
    this.updatePreviewMeta(null);
    this.previewFooter.text("");
    this.syncToolbarState();
    if (!this.state.currentEntityCode) {
      this.setNavigatorSelection(null, "");
    }
    this.selectPropertyNode("program", { code: "" });
    this.refreshCompareChoices();
    this.updateWorkspaceSummary();
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
    this.entitySubscriberIsolationSelect.value("none");
    this.entitySubscriberColumnInput.value("");
    this.entitySubscriberGlobalTableInput.prop("checked", false);
    this.entityCreateTableInput.prop("checked", true);
    this.entityAllowTableRenameInput.prop("checked", true);
    this.entityAllowColumnRenameInput.prop("checked", true);
    this.entityDropRemovedColumnsInput.prop("checked", false);
    this.entitySituationEnabledInput.prop("checked", false);
    this.entityVersioningEnabledInput.prop("checked", false);
    this.entityVersioningDeduplicateInput.prop("checked", true);
    this.apiCatalogSourceSelect.value("");
    this.apiCatalogListOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogDetailOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogCreateOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogUpdateOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogDeleteOperationSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
    this.apiCatalogListOperationSelect.value("");
    this.apiCatalogDetailOperationSelect.value("");
    this.apiCatalogCreateOperationSelect.value("");
    this.apiCatalogUpdateOperationSelect.value("");
    this.apiCatalogDeleteOperationSelect.value("");
    this.resetInlineApiSourceFields();
    this.renderFieldRows([]);
    this.renderUniqueKeyRows([]);
    this.renderRuleRows([]);
    this.historyEntitySelect.value("");
    this.historySourceFieldSelect.value("");
    this.historyAliasInput.value("");
    this.historyFieldsList.empty();
    this.entityVersionsGrid.dataSource.data([]);
    this.syncEntityTypeState();
    this.syncApiBindingState();
    this.syncStructureState();
    this.syncSituationFieldState();
    this.syncSubscriberIsolationState();
    this.selectPropertyNode("entity", { code: "" });
    this.refreshCompareChoices();
    this.renderRelationshipView();
    this.updateWorkspaceSummary();
    this.syncToolbarState();
  };

  ProgramBuilder.prototype.collectProgramPayload = function() {
    const current = this.state.currentVersion || {};
    const editableCurrent = current.status === "draft";
    const pageType = String(this.pageTypeSelect.value() || "crud");
    return {
      id: editableCurrent ? (current.id || null) : null,
      programCode: this.programCodeInput.value(),
      programTitle: this.programTitleInput.value(),
      module: String(this.moduleInput.value() || ""),
      screenId: this.screenIdInput.value(),
      pageType: pageType,
      builderEntityCode: pageType === "crud" ? this.builderEntitySelect.value() : "",
      version: this.versionInput.value(),
      subtitle: this.subtitleInput.value(),
      icon: this.iconInput.value(),
      permissionPrefix: this.permissionPrefixInput.value(),
      programOrigin: String(this.programOriginSelect.value() || "standard"),
      ownerScope: String(this.ownerScopeSelect.value() || "system"),
      customizationPolicy: String(this.customizationPolicySelect.value() || "overlay_only"),
      subscriberId: this.subscriberIdInput.value(),
      baseProgramCode: this.baseProgramCodeInput.value(),
      baseProgramVersionId: this.baseProgramVersionIdInput.value(),
      upgradeFrozen: this.upgradeFrozenInput.is(":checked"),
      frozenReason: this.frozenReasonInput.value(),
      publicationPolicy: {
        allowedDatabaseEnvironments: this.parseCommaSeparatedValues(this.publicationPolicyInput.value())
      },
      allowCreate: pageType === "crud" && this.allowCreateInput.is(":checked"),
      allowUpdate: pageType === "crud" && this.allowUpdateInput.is(":checked"),
      allowDelete: pageType === "crud" && this.allowDeleteInput.is(":checked"),
      customMode: pageType === "custom" ? String(this.customModeSelect.value() || "iframe") : "",
      customEntryUrl: pageType === "custom" ? this.customEntryUrlInput.value() : "",
      customFrameTitle: pageType === "custom" ? this.customFrameTitleInput.value() : "",
      changeSummary: this.changeSummaryTextArea.value() || ""
    };
  };

  ProgramBuilder.prototype.handleNewDraft = function() {
    this.releaseCurrentLock();
    this.state.currentVersion = null;
    this.state.currentProgramCode = "";
    this.state.versions = [];
    this.versionsGrid.dataSource.data([]);
    this.resetProgramForm();
    if (String(this.pageTypeSelect.value() || "crud") === "crud") {
      this.builderEntitySelect.value(this.state.currentEntityCode || "");
      this.handleProgramEntityChange(true);
    }
    this.bannerElement.text("Novo rascunho. Escolha uma entidade modelada, gere o preview e publique quando estiver pronto.");
    this.activateEditorTab(2);
    this.activateSideTab(0);
  };

  ProgramBuilder.prototype.schedulePreview = function() {
    const self = this;
    this.renderDiagnostics();
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
    const hasRequiredCrud = payload.pageType === "crud"
      ? (!!payload.programCode && !!payload.programTitle && !!payload.builderEntityCode && !!payload.screenId && !!payload.version)
      : (!!payload.programCode && !!payload.programTitle && !!payload.screenId && !!payload.version && !!payload.customEntryUrl);
    if (!hasRequiredCrud) {
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
        builderEntityCode: payload.pageType === "crud" ? payload.builderEntityCode : "",
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
        programId: payload.programCode
      },
      permissions: {
        create: payload.allowCreate,
        edit: payload.allowUpdate,
        delete: payload.allowDelete
      }
    };
    if (payload.pageType === "crud") {
      preview.runtime.entityCode = payload.builderEntityCode;
    } else {
      preview.custom = {
        mode: payload.customMode,
        entryUrl: payload.customEntryUrl,
        frameTitle: payload.customFrameTitle || payload.programTitle
      };
    }
    this.renderDefinition(preview);
    this.updatePreviewMeta({
      status: this.state.currentVersion && this.state.currentVersion.status || "draft",
      version: payload.version,
      builderEntityCode: payload.pageType === "crud" ? payload.builderEntityCode : "",
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
    const gate = this.governanceGateState(current);
    if (gate.required && !gate.ready) {
      global.CrudUtils.showMessage("A versao ainda tem pendencias de governanca. Regularize o gate antes de publicar.", "warning");
      this.openGovernanceDialog();
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
      return this.loadDatabaseTables();
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
      this.refreshCompareChoices();
    }
  };

  ProgramBuilder.prototype.handleEntityVersionSelection = function() {
    const item = this.entityVersionsGrid.dataItem(this.entityVersionsGrid.select());
    if (item) {
      this.state.currentEntityVersion = item.toJSON ? item.toJSON() : item;
      this.syncToolbarState();
      this.refreshCompareChoices();
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

    const gate = this.governanceGateState(source);
    if (gate.required) {
      $("<span class=\"k-badge k-rounded-md\"></span>")
        .addClass(gate.ready ? "k-badge-solid k-badge-solid-success" : "k-badge-solid k-badge-solid-error")
        .text(gate.ready ? "Governanca pronta" : "Governanca pendente")
        .appendTo(this.previewMeta);
    }
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
    const readonly = this.state.lockReadonly === true;
    this.governanceButton.enable(hasSavedVersion);
    this.overlayRebaseButton.enable(hasSavedVersion && !!current && !!current.baseProgramCode && String(current.programOrigin || "standard") !== "standard");
    this.publishButton.enable(hasSavedVersion && !readonly);
    this.duplicateButton.enable(hasSavedVersion && !readonly);
    this.restoreEntityButton.enable(hasEntityVersion && !readonly);
    this.saveEntityButton.enable(!readonly);
    this.saveDraftButton.enable(!readonly);
  };

  ProgramBuilder.prototype.isGovernedStandardVersion = function(version) {
    const source = version || {};
    return String(source.programOrigin || "standard") === "standard"
      && String(source.ownerScope || "system") === "system";
  };

  ProgramBuilder.prototype.governanceGateState = function(version) {
    const source = version || {};
    const required = this.isGovernedStandardVersion(source);
    const grant = source.governance && source.governance.grant ? source.governance.grant : null;
    const approval = source.governance && source.governance.approval ? source.governance.approval : null;
    const issues = [];
    if (!required) {
      return { required: false, ready: true, issues: issues, grant: grant, approval: approval };
    }

    if (!grant || grant.status !== "active") {
      issues.push("Falta grant ativo para editar e publicar o programa padrao.");
    }
    if (!this.state.currentLock || !this.state.currentLock.id) {
      issues.push("Falta lock ativo de autoria para a sessao atual.");
    } else if (grant && this.state.currentLock.grantId && Number(this.state.currentLock.grantId) !== Number(grant.id)) {
      issues.push("O lock atual nao esta vinculado ao grant ativo.");
    }
    if (!approval || approval.status !== "approved") {
      issues.push("Falta aprovacao final ativa para a versao corrente.");
    } else if (!approval.testExecutionBundleId) {
      issues.push("A aprovacao final ainda nao referencia o bundle de testes executados.");
    }

    return {
      required: true,
      ready: issues.length === 0,
      issues: issues,
      grant: grant,
      approval: approval
    };
  };

  ProgramBuilder.prototype.renderGovernanceChecklist = function(container, version) {
    const gate = this.governanceGateState(version);
    if (!gate.required) {
      $("<p class=\"program-builder-empty\"></p>").text("Esta versao nao exige gate de governanca de programa padrao.").appendTo(container);
      return gate;
    }

    const list = $("<ul class=\"program-builder-checklist\"></ul>").appendTo(container);
    [
      { ok: !!(gate.grant && gate.grant.status === "active"), text: "Grant ativo" },
      { ok: !!(this.state.currentLock && this.state.currentLock.id), text: "Lock ativo da sessao" },
      { ok: !!(gate.approval && gate.approval.status === "approved"), text: "Aprovacao final ativa" },
      { ok: !!(gate.approval && gate.approval.testExecutionBundleId), text: "Bundle de testes vinculado" }
    ].forEach(function(item) {
      $("<li></li>")
        .addClass(item.ok ? "is-valid" : "is-invalid")
        .text((item.ok ? "OK: " : "Pendente: ") + item.text)
        .appendTo(list);
    });

    gate.issues.forEach(function(message) {
      $("<p class=\"program-builder-inline-warning\"></p>").text(message).appendTo(container);
    });

    return gate;
  };

  ProgramBuilder.prototype.renderGovernanceWorkflowPanel = function(container, version) {
    const source = version || {};
    const governance = source.governance || {};
    const gate = this.governanceGateState(source);
    const panel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(container);
    $("<div class=\"program-builder-versions-header\"><h3>Workflow guiado</h3><p>Resume o estado atual e aponta a proxima acao necessaria.</p></div>").appendTo(panel);
    const grid = $("<div class=\"program-builder-governance-grid\"></div>").appendTo(panel);
    [
      { label: "Solicitacao", status: governance.request && governance.request.status ? governance.request.status : "pendente", valid: !!governance.request },
      { label: "Grant", status: gate.grant && gate.grant.status ? gate.grant.status : "pendente", valid: !!(gate.grant && gate.grant.status === "active") },
      { label: "Lock", status: this.state.currentLock && this.state.currentLock.id ? "ativo" : "pendente", valid: !!(this.state.currentLock && this.state.currentLock.id) },
      { label: "Bundle", status: gate.approval && gate.approval.testExecutionBundleId ? gate.approval.testExecutionBundleId : "pendente", valid: !!(gate.approval && gate.approval.testExecutionBundleId) },
      { label: "Aprovacao", status: gate.approval && gate.approval.status ? gate.approval.status : "pendente", valid: !!(gate.approval && gate.approval.status === "approved") }
    ].forEach(function(item) {
      const card = $("<article class=\"program-builder-governance-card\"></article>").appendTo(grid);
      $("<strong></strong>").text(item.label).appendTo(card);
      $("<span></span>").addClass(item.valid ? "is-valid" : "is-invalid").text(item.status).appendTo(card);
    });

    const nextAction = !governance.request
      ? "Criar a solicitacao formal de alteracao."
      : !(gate.grant && gate.grant.status === "active")
        ? "Liberar ou reativar o grant da versao."
        : !(this.state.currentLock && this.state.currentLock.id)
          ? "Adquirir lock de autoria para a sessao corrente."
          : !(gate.approval && gate.approval.testExecutionBundleId)
            ? "Registrar o bundle de testes executado."
            : !(gate.approval && gate.approval.status === "approved")
              ? "Registrar a aprovacao final da publicacao."
              : "Gate completo. A versao ja pode seguir para publicacao governada.";
    $("<p class=\"program-builder-inline-warning\"></p>").text(nextAction).appendTo(panel);

    if (governance.retentionPolicy) {
      $("<p class=\"program-builder-inline-muted\"></p>")
        .text("Retencao: solicitacoes " + governance.retentionPolicy.changeRequestsDays + "d, grants " + governance.retentionPolicy.grantsDays + "d, aprovacoes " + governance.retentionPolicy.approvalsDays + "d, testes " + governance.retentionPolicy.testExecutionsDays + "d.")
        .appendTo(panel);
    }
  };

  ProgramBuilder.prototype.renderGovernanceDashboard = function(container, dashboard, currentVersion) {
    container.empty();
    if (!dashboard || typeof dashboard !== "object") {
      $("<p class=\"program-builder-empty\"></p>").text("Nao foi possivel carregar o painel operacional da governanca.").appendTo(container);
      return;
    }

    const summary = $("<section class=\"program-builder-subpanel\"></section>").appendTo(container);
    $("<div class=\"program-builder-versions-header\"><h3>Painel operacional</h3><p>Ultimas solicitacoes, grants, aprovacoes e bundles do programa corrente.</p></div>").appendTo(summary);
    const summaryGrid = $("<div class=\"program-builder-governance-grid\"></div>").appendTo(summary);
    [
      { label: "Solicitacoes pendentes", status: String(dashboard.summary && dashboard.summary.pendingRequests || 0), valid: Number(dashboard.summary && dashboard.summary.pendingRequests || 0) === 0 },
      { label: "Grants ativos", status: String(dashboard.summary && dashboard.summary.activeGrants || 0), valid: Number(dashboard.summary && dashboard.summary.activeGrants || 0) > 0 },
      { label: "Aprovacoes", status: String(dashboard.summary && dashboard.summary.approvedPublications || 0), valid: Number(dashboard.summary && dashboard.summary.approvedPublications || 0) > 0 },
      { label: "Testes aprovados", status: String(dashboard.summary && dashboard.summary.passedTests || 0), valid: Number(dashboard.summary && dashboard.summary.passedTests || 0) > 0 }
    ].forEach(function(item) {
      const card = $("<article class=\"program-builder-governance-card\"></article>").appendTo(summaryGrid);
      $("<strong></strong>").text(item.label).appendTo(card);
      $("<span></span>").addClass(item.valid ? "is-valid" : "is-invalid").text(item.status).appendTo(card);
    });

    if (dashboard.retentionPolicy) {
      $("<p class=\"program-builder-inline-muted\"></p>")
        .text("Retencao parametrizavel em admin.parametros: solicitacoes " + dashboard.retentionPolicy.changeRequestsDays + "d, grants " + dashboard.retentionPolicy.grantsDays + "d, aprovacoes " + dashboard.retentionPolicy.approvalsDays + "d, testes " + dashboard.retentionPolicy.testExecutionsDays + "d, notificacoes " + dashboard.retentionPolicy.administrativeNotificationsDays + "d.")
        .appendTo(summary);
    }

    [
      {
        title: "Solicitacoes recentes",
        items: dashboard.requests || [],
        formatter: function(item) { return (item.requestCode || "-") + " | " + (item.status || "pendente") + " | " + (item.requestedBy || "-"); },
        renderActions: function(actions, item) {
          $("<button type=\"button\"></button>").text("Usar request").appendTo(actions).kendoButton({
            icon: "arrow-right",
            click: function() {
              container.closest(".k-window-content").find("input").each(function() {
                const label = $(this).closest(".program-builder-field").find("label").text();
                if (/Request ID/i.test(label)) {
                  this.value = String(item.id || "");
                }
              });
              global.CrudUtils.showMessage("Request carregado no formulario do grant.", "info");
            }
          });
        }
      },
      {
        title: "Grants recentes",
        items: dashboard.grants || [],
        formatter: function(item) { return "Grant " + (item.id || "-") + " | " + (item.status || "-") + " | " + (item.grantedToUserId || "-"); },
        renderActions: function(actions, item) {
          [["active", "Reativar"], ["frozen", "Congelar"], ["revoked", "Revogar"]].forEach(function(entry) {
            $("<button type=\"button\"></button>").text(entry[1]).appendTo(actions).kendoButton({
              click: function() {
                this.http.request({
                  url: "/api/admin/program-builder/governance/grants/status",
                  method: "POST",
                  data: { grantId: Number(item.id || 0), status: entry[0] }
                }).then(function() {
                  global.CrudUtils.showMessage("Grant atualizado.", "success");
                  this.openGovernanceDialog();
                }.bind(this)).catch(function(error) {
                  this.handleError(error, "Nao foi possivel alterar o grant.");
                }.bind(this));
              }.bind(this)
            });
          }.bind(this));
        }.bind(this)
      },
      {
        title: "Aprovacoes recentes",
        items: dashboard.approvals || [],
        formatter: function(item) { return "Aprovacao " + (item.id || "-") + " | " + (item.status || "-") + " | bundle " + (item.testExecutionBundleId || "-"); },
        renderActions: function(actions, item) {
          $("<button type=\"button\"></button>").text("Usar bundle").appendTo(actions).kendoButton({
            icon: "arrow-right",
            click: function() {
              container.closest(".k-window-content").find("input").each(function() {
                const label = $(this).closest(".program-builder-field").find("label").text();
                if (/Bundle/i.test(label)) {
                  this.value = String(item.testExecutionBundleId || "");
                }
              });
              global.CrudUtils.showMessage("Bundle carregado no formulario.", "info");
            }
          });
        }
      },
      {
        title: "Bundles recentes",
        items: dashboard.tests || [],
        formatter: function(item) { return (item.bundleId || "-") + " | " + (item.status || "-") + " | " + (item.testPlanId || "-"); },
        renderActions: function(actions, item) {
          $("<button type=\"button\"></button>").text("Usar bundle").appendTo(actions).kendoButton({
            icon: "arrow-right",
            click: function() {
              container.closest(".k-window-content").find("input").each(function() {
                const label = $(this).closest(".program-builder-field").find("label").text();
                if (/Bundle/i.test(label)) {
                  this.value = String(item.bundleId || "");
                }
              });
              global.CrudUtils.showMessage("Bundle carregado no formulario.", "info");
            }
          });
        }
      }
    ].forEach(function(section) {
      const panel = $("<section class=\"program-builder-subpanel\"></section>").appendTo(container);
      $("<div class=\"program-builder-versions-header\"><h3></h3><p></p></div>").appendTo(panel)
        .find("h3").text(section.title).end()
        .find("p").text("Resumo operacional para reduzir dependencia do CRUD administrativo generico.");
      if (!Array.isArray(section.items) || !section.items.length) {
        $("<p class=\"program-builder-empty\"></p>").text("Nenhum registro recente.").appendTo(panel);
        return;
      }
      const list = $("<div class=\"program-builder-governance-dashboard-list\"></div>").appendTo(panel);
      section.items.forEach(function(item) {
        const row = $("<div class=\"program-builder-governance-dashboard-row\"></div>").appendTo(list);
        $("<div class=\"program-builder-governance-dashboard-text\"></div>").text(section.formatter(item)).appendTo(row);
        const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(row);
        if (typeof section.renderActions === "function") {
          section.renderActions(actions, item);
        }
      });
    });

    const retention = $("<section class=\"program-builder-subpanel\"></section>").appendTo(container);
    $("<div class=\"program-builder-versions-header\"><h3>Retencao</h3><p>Edicao direta da politica de limpeza da governanca.</p></div>").appendTo(retention);
    const retentionGrid = $("<div class=\"program-builder-governance-grid\"></div>").appendTo(retention);
    const retentionInputs = {};
    [
      { key: "changeRequestsDays", label: "Solicitacoes" },
      { key: "grantsDays", label: "Grants" },
      { key: "approvalsDays", label: "Aprovacoes" },
      { key: "testExecutionsDays", label: "Testes" },
      { key: "administrativeNotificationsDays", label: "Notificacoes" }
    ].forEach(function(item) {
      const field = $("<div class=\"program-builder-field\"></div>").appendTo(retentionGrid);
      $("<label></label>").text(item.label + " (dias)").appendTo(field);
      retentionInputs[item.key] = $("<input type=\"number\" min=\"1\">")
        .val(String((dashboard.retentionPolicy && dashboard.retentionPolicy[item.key]) || ""))
        .appendTo(field);
    });
    const retentionActions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(retention);
    $("<button type=\"button\"></button>").text("Salvar retencao").appendTo(retentionActions).kendoButton({
      icon: "save",
      click: function() {
        const payload = {};
        Object.keys(retentionInputs).forEach(function(key) {
          payload[key] = Number(retentionInputs[key].val() || 0);
        });
        this.http.request({
          url: "/api/admin/program-builder/governance/retention",
          method: "POST",
          data: payload
        }).then(function(response) {
          global.CrudUtils.showMessage("Retencao atualizada.", "success");
          this.renderGovernanceDashboard(container, response, currentVersion);
        }.bind(this)).catch(function(error) {
          this.handleError(error, "Nao foi possivel atualizar a retencao.");
        }.bind(this));
      }.bind(this)
    });
  };

  ProgramBuilder.prototype.renderOverlayRebasePreview = function(container, response) {
    this.overlayRebasePreview = response || null;
    this.overlayRebaseSelections = this.overlayRebaseSelections || {};
    container.empty();
    if (!response || typeof response !== "object") {
      $("<p class=\"program-builder-empty\"></p>").text("Nenhum preview de rebase foi carregado.").appendTo(container);
      return;
    }

    const summary = $("<section class=\"program-builder-subpanel\"></section>").appendTo(container);
    $("<div class=\"program-builder-versions-header\"><h3>Resumo do rebase</h3><p>Compara a base antiga, a nova base publicada e a definicao resolvida do overlay.</p></div>").appendTo(summary);
    const statusRow = $("<div class=\"program-builder-inline-actions program-builder-diff-status-row\"></div>").appendTo(summary);
    $("<span class=\"k-badge k-rounded-md\"></span>")
      .addClass(response.status === "ok" ? "k-badge-solid k-badge-solid-success" : (response.status === "warning" ? "k-badge-solid k-badge-solid-warning" : "k-badge-solid k-badge-solid-error"))
      .text("Status: " + String(response.status || "desconhecido"))
      .appendTo(statusRow);
    if (response.currentBaseVersion) {
      $("<span class=\"k-badge k-rounded-md\"></span>").text("Base atual: " + response.currentBaseVersion).appendTo(statusRow);
    }
    if (response.targetBaseVersion) {
      $("<span class=\"k-badge k-rounded-md\"></span>").text("Base alvo: " + response.targetBaseVersion).appendTo(statusRow);
    }
    if (response.customizationKind) {
      $("<span class=\"k-badge k-rounded-md\"></span>").text("Tipo: " + response.customizationKind).appendTo(statusRow);
    }
    if (response.summaryCounts) {
      $("<span class=\"k-badge k-rounded-md\"></span>").text("Auto merge: " + String(response.summaryCounts.autoMerge || 0)).appendTo(statusRow);
      $("<span class=\"k-badge k-rounded-md\"></span>").text("Overlay only: " + String(response.summaryCounts.overlayOnly || 0)).appendTo(statusRow);
      $("<span class=\"k-badge k-rounded-md\"></span>").text("Conflitos leves: " + String(response.summaryCounts.warningConflicts || 0)).appendTo(statusRow);
      $("<span class=\"k-badge k-rounded-md\"></span>").text("Conflitos bloqueantes: " + String(response.summaryCounts.blockingConflicts || 0)).appendTo(statusRow);
    }
    $("<p class=\"program-builder-inline-warning\"></p>").text(response.reason || "Sem diagnostico adicional.").appendTo(summary);
    if (response.policyDecision) {
      $("<p class=\"program-builder-inline-warning\"></p>").text(String(response.policyDecision)).appendTo(summary);
    }

    if (response.summaryCounts) {
      const cards = $("<div class=\"program-builder-governance-grid\"></div>").appendTo(summary);
      [
        { label: "Merge automatico", value: response.summaryCounts.autoMerge || 0, valid: (response.summaryCounts.autoMerge || 0) > 0 },
        { label: "Apenas overlay", value: response.summaryCounts.overlayOnly || 0, valid: (response.summaryCounts.overlayOnly || 0) > 0 },
        { label: "Conflitos leves", value: response.summaryCounts.warningConflicts || 0, valid: (response.summaryCounts.warningConflicts || 0) === 0 },
        { label: "Conflitos bloqueantes", value: response.summaryCounts.blockingConflicts || 0, valid: (response.summaryCounts.blockingConflicts || 0) === 0 }
      ].forEach(function(item) {
        const card = $("<article class=\"program-builder-governance-card\"></article>").appendTo(cards);
        $("<strong></strong>").text(item.label).appendTo(card);
        $("<span></span>").addClass(item.valid ? "is-valid" : "is-invalid").text(String(item.value)).appendTo(card);
      });
    }

    if (Array.isArray(response.sections) && response.sections.length) {
      const controls = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(summary);
      const filterInput = $("<input type=\"text\" placeholder=\"Filtrar secao/caminho\">").appendTo(controls);
      const typeSelect = $("<select></select>").appendTo(controls);
      [
        { value: "all", text: "Todas" },
        { value: "auto_merge", text: "Merge automatico" },
        { value: "overlay_only", text: "Apenas overlay" },
        { value: "conflict_warning", text: "Conflito leve" },
        { value: "conflict_blocking", text: "Conflito bloqueante" }
      ].forEach(function(option) {
        $("<option></option>").attr("value", option.value).text(option.text).appendTo(typeSelect);
      });
      const resolutionSummary = $("<section class=\"program-builder-subpanel\"></section>").appendTo(summary);
      $("<div class=\"program-builder-versions-header\"><h3>Plano de resolucao</h3><p>Resume o que sera aplicado no rebase conforme as escolhas por campo.</p></div>").appendTo(resolutionSummary);
      const resolutionCards = $("<div class=\"program-builder-governance-grid\"></div>").appendTo(resolutionSummary);
      const resolutionWarning = $("<p class=\"program-builder-inline-warning\"></p>").appendTo(resolutionSummary);
      if (response.runtimeImpactSummary) {
        $("<div class=\"program-builder-inline-muted\"></div>")
          .text("Impacto no runtime: secoes criticas " + String(response.runtimeImpactSummary.criticalSections || 0) + " | conflitos leves " + String(response.runtimeImpactSummary.warningConflicts || 0) + " | conflitos bloqueantes " + String(response.runtimeImpactSummary.blockingConflicts || 0))
          .appendTo(resolutionSummary);
      }
      if (response.policySummary) {
        $("<div class=\"program-builder-inline-muted\"></div>")
          .text(String(response.policySummary.message || ""))
          .appendTo(resolutionSummary);
      }
      const renderResolutionSummary = function() {
        const counters = {
          rebased: 0,
          overlay: 0,
          base: 0,
          blocking: 0,
          warnings: 0,
          policyViolations: 0
        };
        response.sections.forEach(function(section) {
          const entries = Array.isArray(section && section.entries) ? section.entries : [];
          entries.forEach(function(entry) {
            const pathKey = entry && entry.path ? String(entry.path) : "";
            const selectedResolution = this.overlayRebaseSelections[pathKey]
              || (entry && entry.selectedResolution ? entry.selectedResolution : "rebased");
            if (selectedResolution === "overlay") {
              counters.overlay += 1;
            } else if (selectedResolution === "base") {
              counters.base += 1;
            } else {
              counters.rebased += 1;
            }
            if (entry && entry.classification === "conflict_blocking") {
              counters.blocking += 1;
            } else if (entry && entry.classification === "conflict_warning") {
              counters.warnings += 1;
              if (selectedResolution === "overlay") {
                counters.policyViolations += 1;
              }
            }
          }, this);
        }, this);
        resolutionCards.empty();
        [
          { label: "Rebase sugerido", value: counters.rebased, valid: true },
          { label: "Manter overlay", value: counters.overlay, valid: true },
          { label: "Usar base", value: counters.base, valid: true },
          { label: "Entradas bloqueantes", value: counters.blocking, valid: counters.blocking === 0 },
          { label: "Entradas com aviso", value: counters.warnings, valid: counters.warnings === 0 },
          { label: "Violacoes de politica", value: counters.policyViolations, valid: counters.policyViolations === 0 }
        ].forEach(function(item) {
          const card = $("<article class=\"program-builder-governance-card\"></article>").appendTo(resolutionCards);
          $("<strong></strong>").text(item.label).appendTo(card);
          $("<span></span>").addClass(item.valid ? "is-valid" : "is-invalid").text(String(item.value)).appendTo(card);
        });
        resolutionWarning.text(
          counters.blocking > 0
            ? "Existem entradas bloqueantes no diff. Revise as escolhas antes de confirmar o rebase."
            : (counters.policyViolations > 0
                ? "Existem escolhas locais que violam a politica de rebase para campos criticos."
                : "Resumo pronto. O rebase aplicara as escolhas atuais por campo e secao.")
        );
      }.bind(this);
      const navigator = $("<div class=\"program-builder-diff-navigator\"></div>").appendTo(summary);
      const sectionList = $("<div class=\"program-builder-diff-sections\"></div>").appendTo(summary);
      const renderSections = function() {
        const query = String(filterInput.val() || "").toLowerCase();
        const selectedType = String(typeSelect.val() || "all");
        navigator.empty();
        sectionList.empty();
        response.sections.forEach(function(item) {
          const classification = item && item.classification ? String(item.classification) : "unchanged";
          const haystack = [
            item && item.key ? item.key : "",
            ...((item && item.basePaths) || []),
            ...((item && item.overlayPaths) || []),
            ...((item && item.conflictPaths) || [])
          ].join(" ").toLowerCase();
          if (selectedType !== "all" && classification !== selectedType) {
            return;
          }
          if (query && haystack.indexOf(query) < 0) {
            return;
          }
          $("<button type=\"button\" class=\"program-builder-diff-nav-item\"></button>")
            .text(item && item.key ? item.key : "-")
            .appendTo(navigator)
            .on("click", function() {
              const target = sectionList.find("[data-section-key='" + String(item && item.key ? item.key : "").replace(/'/g, "\\'") + "']").first();
              if (target.length) {
                target[0].scrollIntoView({ behavior: "smooth", block: "start" });
              }
            });
          const label = classification === "conflict_blocking"
            ? "conflito bloqueante"
            : classification === "conflict_warning"
              ? "conflito leve"
              : classification === "overlay_only"
                ? "apenas overlay"
                : classification === "auto_merge"
                  ? "merge automatico"
                  : "inalterado";
          const card = $("<article class=\"program-builder-diff-section-card\"></article>")
            .attr("data-section-key", item && item.key ? item.key : "")
            .toggleClass("is-conflict", item && item.conflict === true)
            .toggleClass("is-blocking", classification === "conflict_blocking")
            .appendTo(sectionList);
          const header = $("<div class=\"program-builder-diff-section-header\"></div>").appendTo(card);
          $("<strong></strong>").text(item && item.key ? item.key : "-").appendTo(header);
          $("<span class=\"k-badge k-rounded-md\"></span>").text(label).appendTo(header);
          $("<p class=\"program-builder-inline-muted\"></p>")
            .text("Base: " + ((item && item.baseChanged) ? "alterada" : "igual") + " | Overlay: " + ((item && item.overlayChanged) ? "alterado" : "igual") + " | Resolucao: " + (item && item.resolution ? item.resolution : "-"))
            .appendTo(card);
          const paths = $("<div class=\"program-builder-diff-path-groups\"></div>").appendTo(card);
          [
            { title: "Base publicada", items: item && item.basePaths ? item.basePaths : [] },
            { title: "Overlay atual", items: item && item.overlayPaths ? item.overlayPaths : [] },
            { title: "Choque real", items: item && item.conflictPaths ? item.conflictPaths : [] }
          ].forEach(function(group) {
            const values = Array.isArray(group.items) ? group.items : [];
            if (!values.length) {
              return;
            }
            const block = $("<section class=\"program-builder-diff-path-group\"></section>").appendTo(paths);
            $("<h5></h5>").text(group.title).appendTo(block);
            const list = $("<ul class=\"program-builder-diff-list\"></ul>").appendTo(block);
            values.forEach(function(pathValue) {
              $("<li></li>").text(pathValue).appendTo(list);
            });
          });
          const entries = Array.isArray(item && item.entries) ? item.entries : [];
          if (entries.length) {
            const detail = $("<section class=\"program-builder-diff-path-group\"></section>").appendTo(card);
            $("<h5></h5>").text("Detalhe por campo").appendTo(detail);
            const table = $("<table class=\"program-builder-diff-table\"></table>").appendTo(detail);
            $("<thead><tr><th>Caminho</th><th>Classe</th><th>Resolucao</th><th>Base nova</th><th>Overlay</th><th>Rebase</th></tr></thead>").appendTo(table);
            const tbody = $("<tbody></tbody>").appendTo(table);
            entries.forEach(function(entry) {
              const row = $("<tr></tr>")
                .toggleClass("is-blocking", entry && entry.classification === "conflict_blocking")
                .toggleClass("is-warning", entry && entry.classification === "conflict_warning")
                .appendTo(tbody);
              const entryPath = entry && entry.path ? String(entry.path) : "";
              $("<td></td>").text(entry && entry.relativePath ? entry.relativePath : (entry && entry.path ? entry.path : "-")).appendTo(row);
              $("<td></td>").text(entry && entry.classification ? String(entry.classification).replaceAll("_", " ") : "-").appendTo(row);
              const resolutionCell = $("<td></td>").appendTo(row);
              const resolutionOptions = Array.isArray(entry && entry.resolutionOptions) ? entry.resolutionOptions : ["rebased"];
              const selectedResolution = this.overlayRebaseSelections[entryPath] || (entry && entry.selectedResolution ? entry.selectedResolution : resolutionOptions[0]);
              const resolutionSelect = $("<select class=\"program-builder-diff-resolution\"></select>").appendTo(resolutionCell);
              resolutionOptions.forEach(function(option) {
                $("<option></option>")
                  .attr("value", option)
                  .prop("selected", option === selectedResolution)
                  .text(option === "rebased" ? "Rebase sugerido" : (option === "overlay" ? "Manter overlay" : "Usar base"))
                  .appendTo(resolutionSelect);
              });
              resolutionSelect.on("change", function() {
                if (entryPath) {
                  this.overlayRebaseSelections[entryPath] = String($(this).val() || "rebased");
                }
                renderResolutionSummary();
              }.bind(this));
              $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry ? entry.baseValue : null))).appendTo(row);
              $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry ? entry.overlayValue : null))).appendTo(row);
              $("<td></td>").append($("<code></code>").text(this.stringifyInlineJson(entry ? entry.rebasedValue : null))).appendTo(row);
            }.bind(this));
          }
        });
        renderResolutionSummary();
      };
      filterInput.on("input", renderSections);
      typeSelect.on("change", renderSections);
      renderSections();
    }

    const grid = $("<div class=\"program-builder-diff-grid\"></div>").appendTo(container);
    [
      { title: "Base antiga", payload: response.oldBaseDefinition || {} },
      { title: "Base publicada", payload: response.targetBaseDefinition || {} },
      { title: "Overlay atual", payload: response.currentResolvedDefinition || {} },
      { title: "Overrides declarados", payload: response.definitionOverrides || {} },
      { title: "Definicao rebaseada", payload: response.rebasedDefinition || {} }
    ].forEach(function(section) {
      const panel = $("<section class=\"program-builder-diff-panel\"></section>").appendTo(grid);
      $("<h4></h4>").text(section.title).appendTo(panel);
      $("<pre class=\"program-builder-inline-json\"></pre>").text(JSON.stringify(section.payload, null, 2)).appendTo(panel);
    });
  };

  ProgramBuilder.prototype.stringifyPublicationPolicy = function(policy) {
    const values = Array.isArray(policy && policy.allowedDatabaseEnvironments) ? policy.allowedDatabaseEnvironments : [];
    return values.join(", ");
  };

  ProgramBuilder.prototype.stringifyInlineJson = function(value) {
    if (value == null) {
      return "null";
    }
    if (typeof value === "string") {
      return value;
    }
    try {
      const text = JSON.stringify(value);
      return text.length > 180 ? text.slice(0, 177) + "..." : text;
    } catch (_) {
      return String(value);
    }
  };

  ProgramBuilder.prototype.parseCommaSeparatedValues = function(value) {
    return String(value || "").split(",").map(function(item) {
      return String(item || "").trim();
    }).filter(Boolean);
  };

  ProgramBuilder.prototype.ensureUtilityWindow = function(propertyName, title, width) {
    if (this[propertyName]) {
      this[propertyName].center().open();
      return this[propertyName];
    }
    const host = $("<div></div>").appendTo(this.root);
    host.kendoWindow({
      title: title,
      modal: true,
      visible: false,
      resizable: true,
      width: width || 760
    });
    this[propertyName] = host.data("kendoWindow");
    this[propertyName].center().open();
    return this[propertyName];
  };

  ProgramBuilder.prototype.openGovernanceDialog = function() {
    const current = this.state.currentVersion;
    if (!current || !current.id) {
      global.CrudUtils.showMessage("Selecione uma versao salva para operar a governanca.", "warning");
      return;
    }
    const dialog = this.ensureUtilityWindow("governanceWindow", "Governanca do programa", 860);
    const host = dialog.element;
    host.empty();
    const body = $("<div class=\"program-builder-form\"></div>").appendTo(host);
    const governance = current.governance || {};
    const summary = $("<section class=\"program-builder-subpanel\"></section>").appendTo(body);
    $("<div class=\"program-builder-versions-header\"><h3>Resumo</h3><p>Grant, aprovacao e bundle de testes da versao corrente.</p></div>").appendTo(summary);
    this.renderGovernanceChecklist(summary, current);
    this.renderGovernanceWorkflowPanel(summary, current);
    if (governance.request && governance.request.requestCode) {
      $("<p></p>").text("Solicitacao atual: " + governance.request.requestCode + " (" + String(governance.request.status || "pendente") + ")").appendTo(summary);
    }
    $("<pre class=\"program-builder-inline-json\"></pre>").text(JSON.stringify(governance, null, 2)).appendTo(summary);
    const dashboardSection = $("<section class=\"program-builder-subpanel\"></section>").appendTo(body);
    $("<div class=\"program-builder-versions-header\"><h3>Operacao dedicada</h3><p>Carrega o painel real de governanca sem depender apenas das telas CRUD administrativas.</p></div>").appendTo(dashboardSection);
    const dashboardBody = $("<div class=\"program-builder-governance-dashboard\"></div>").appendTo(dashboardSection);
    $("<p class=\"program-builder-empty\"></p>").text("Carregando painel de governanca...").appendTo(dashboardBody);
    this.http.request({
      url: "/api/admin/program-builder/governance/dashboard",
      method: "GET",
      data: {
        programCode: current.programCode,
        builderProgramVersionId: current.id
      }
    }).then(function(response) {
      this.renderGovernanceDashboard(dashboardBody, response, current);
    }.bind(this)).catch(function(error) {
      dashboardBody.empty();
      $("<p class=\"program-builder-inline-warning\"></p>").text(global.CrudUtils.unwrapError(error, "Nao foi possivel carregar o painel real de governanca.").message).appendTo(dashboardBody);
    });

    const requestSection = $("<section class=\"program-builder-subpanel\"></section>").appendTo(body);
    $("<div class=\"program-builder-versions-header\"><h3>Solicitacao</h3><p>Cria a solicitacao formal para programas padrao.</p></div>").appendTo(requestSection);
    const requestReason = $("<textarea rows=\"3\"></textarea>").appendTo(this.appendField(requestSection, "Motivo", this.programFieldTechnicalProperties("changeSummary"))).kendoTextArea({
      placeholder: "Motivo da alteracao"
    }).data("kendoTextArea");
    $("<button type=\"button\"></button>").text("Solicitar alteracao").appendTo(requestSection).kendoButton({
      icon: "lock",
      click: function() {
        this.http.request({
          url: "/api/admin/program-builder/governance/requests",
          method: "POST",
          data: {
            programCode: current.programCode,
            builderEntityCode: current.builderEntityCode || "",
            requestedActions: ["edit", "publish"],
            reason: requestReason.value() || ""
          }
        }).then(function(response) {
          global.CrudUtils.showMessage("Solicitacao criada.", "success");
          requestReason.value("");
          this.refreshProgramVersions(current.programCode).then(this.openGovernanceDialog.bind(this));
        }.bind(this)).catch(function(error) {
          this.handleError(error, "Nao foi possivel criar a solicitacao.");
        }.bind(this));
      }.bind(this)
    });

    const grantSection = $("<section class=\"program-builder-subpanel\"></section>").appendTo(body);
    $("<div class=\"program-builder-versions-header\"><h3>Grant</h3><p>Aprova a solicitacao, congela ou revoga a autorizacao atual.</p></div>").appendTo(grantSection);
    const grantSplit = $("<div class=\"program-builder-split\"></div>").appendTo(grantSection);
    const requestIdInput = this.createTextField(grantSplit, "Request ID");
    const grantUserInput = this.createTextField(grantSplit, "Usuario liberado");
    requestIdInput.value(governance.grant && governance.grant.requestId ? String(governance.grant.requestId) : (governance.request && governance.request.id ? String(governance.request.id) : ""));
    const grantButtons = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(grantSection);
    $("<button type=\"button\"></button>").text("Liberar grant").appendTo(grantButtons).kendoButton({
      icon: "unlock",
      click: function() {
        this.http.request({
          url: "/api/admin/program-builder/governance/grants",
          method: "POST",
          data: {
            requestId: Number(requestIdInput.value() || 0),
            grantedToUserId: grantUserInput.value() || (this.state.currentUser && this.state.currentUser.userId) || ""
          }
        }).then(function() {
          global.CrudUtils.showMessage("Grant criado.", "success");
          this.refreshProgramVersions(current.programCode).then(this.openGovernanceDialog.bind(this));
        }.bind(this)).catch(function(error) {
          this.handleError(error, "Nao foi possivel liberar o grant.");
        }.bind(this));
      }.bind(this)
    });
    ["active", "frozen", "revoked"].forEach(function(status) {
      $("<button type=\"button\"></button>").text(status === "active" ? "Reativar" : (status === "frozen" ? "Congelar" : "Revogar")).appendTo(grantButtons).kendoButton({
        click: function() {
          const grantId = governance.grant && governance.grant.id ? Number(governance.grant.id) : 0;
          if (!grantId) {
            global.CrudUtils.showMessage("Nao existe grant ativo para alterar.", "warning");
            return;
          }
          this.http.request({
            url: "/api/admin/program-builder/governance/grants/status",
            method: "POST",
            data: {
              grantId: grantId,
              status: status
            }
          }).then(function() {
            global.CrudUtils.showMessage("Grant atualizado.", "success");
            this.refreshProgramVersions(current.programCode).then(this.openGovernanceDialog.bind(this));
          }.bind(this)).catch(function(error) {
            this.handleError(error, "Nao foi possivel alterar o grant.");
          }.bind(this));
        }.bind(this)
      });
    }.bind(this));

    const testSection = $("<section class=\"program-builder-subpanel\"></section>").appendTo(body);
    $("<div class=\"program-builder-versions-header\"><h3>Testes e aprovacao</h3><p>Registra o bundle executado e libera a publicacao governada.</p></div>").appendTo(testSection);
    const testSplit = $("<div class=\"program-builder-split\"></div>").appendTo(testSection);
    const bundleInput = this.createTextField(testSplit, "Bundle");
    bundleInput.value(governance.approval && governance.approval.testExecutionBundleId ? governance.approval.testExecutionBundleId : ("bundle-" + current.id));
    const testPlanInput = this.createTextField(testSplit, "Roteiro");
    testPlanInput.value("roteiro-web");
    const notesInput = $("<textarea rows=\"3\"></textarea>").appendTo(this.appendField(testSection, "Observacoes")).kendoTextArea({
      placeholder: "Resumo da execucao"
    }).data("kendoTextArea");
    const testButtons = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(testSection);
    $("<button type=\"button\"></button>").text("Registrar teste aprovado").appendTo(testButtons).kendoButton({
      icon: "check",
      click: function() {
        this.http.request({
          url: "/api/admin/program-builder/governance/tests",
          method: "POST",
          data: {
            builderProgramVersionId: current.id,
            bundleId: bundleInput.value(),
            testPlanId: testPlanInput.value(),
            status: "passed",
            checklistSnapshot: [{ item: "roteiro-web", status: "passed" }],
            notes: notesInput.value() || ""
          }
        }).then(function() {
          global.CrudUtils.showMessage("Teste registrado.", "success");
        }).catch(function(error) {
          this.handleError(error, "Nao foi possivel registrar o teste.");
        }.bind(this));
      }.bind(this)
    });
    $("<button type=\"button\"></button>").text("Aprovar publicacao").appendTo(testButtons).kendoButton({
      icon: "upload",
      click: function() {
        this.http.request({
          url: "/api/admin/program-builder/governance/approvals",
          method: "POST",
          data: {
            builderProgramVersionId: current.id,
            bundleId: bundleInput.value()
          }
        }).then(function() {
          global.CrudUtils.showMessage("Aprovacao registrada.", "success");
          this.refreshProgramVersions(current.programCode).then(this.openGovernanceDialog.bind(this));
        }).catch(function(error) {
          this.handleError(error, "Nao foi possivel aprovar a publicacao.");
        }.bind(this));
      }.bind(this)
    });
  };

  ProgramBuilder.prototype.openOverlayRebaseDialog = function() {
    const current = this.state.currentVersion;
    if (!current || !current.id) {
      global.CrudUtils.showMessage("Selecione uma versao salva para operar overlays.", "warning");
      return;
    }
    const dialog = this.ensureUtilityWindow("overlayRebaseWindow", "Rebase de overlay", 820);
    const host = dialog.element;
    host.empty();
    const body = $("<div class=\"program-builder-form\"></div>").appendTo(host);
    const intro = $("<section class=\"program-builder-subpanel\"></section>").appendTo(body);
    $("<div class=\"program-builder-versions-header\"><h3>Assistente de rebase</h3><p>Use os IDs do overlay e da versao do overlay para comparar com a nova base publicada.</p></div>").appendTo(intro);
    const split = $("<div class=\"program-builder-split\"></div>").appendTo(intro);
    const overlayIdInput = this.createTextField(split, "Overlay ID");
    const overlayVersionIdInput = this.createTextField(split, "Overlay Version ID");
    const actions = $("<div class=\"program-builder-inline-actions\"></div>").appendTo(intro);
    const resultHost = $("<div class=\"program-builder-diff-host\"></div>").appendTo(body);
    const rawSection = $("<section class=\"program-builder-subpanel\"></section>").appendTo(body);
    $("<div class=\"program-builder-versions-header\"><h3>Payload bruto</h3><p>Resumo tecnico do preview retornado pelo backend.</p></div>").appendTo(rawSection);
    const resultBox = $("<pre class=\"program-builder-inline-json\"></pre>").appendTo(rawSection);
    $("<button type=\"button\"></button>").text("Preview").appendTo(actions).kendoButton({
      icon: "eye",
      click: function() {
        const overlayId = Number(overlayIdInput.value() || 0);
        if (!overlayId) {
          global.CrudUtils.showMessage("Informe o Overlay ID.", "warning");
          return;
        }
        this.http.request({
          url: "/api/admin/program-builder/overlays/" + encodeURIComponent(overlayId) + "/rebase-preview",
          method: "GET"
        }).then(function(response) {
          this.overlayRebaseSelections = {};
          this.renderOverlayRebasePreview(resultHost, response);
          resultBox.text(JSON.stringify(response, null, 2));
        }.bind(this)).catch(function(error) {
          this.handleError(error, "Nao foi possivel gerar o preview do rebase.");
        }.bind(this));
      }.bind(this)
    });
    $("<button type=\"button\"></button>").text("Executar rebase").appendTo(actions).kendoButton({
      icon: "arrows-merge",
      click: function() {
        const overlayVersionId = Number(overlayVersionIdInput.value() || 0);
        if (!overlayVersionId) {
          global.CrudUtils.showMessage("Informe o Overlay Version ID.", "warning");
          return;
        }
        const continueRebase = function() {
          this.http.request({
            url: "/api/admin/program-builder/overlay-versions/" + encodeURIComponent(overlayVersionId) + "/rebase",
            method: "POST",
            data: {
              resolutions: Object.assign({}, this.overlayRebaseSelections || {}, { __confirmWarning__: true })
            }
          }).then(function(response) {
            global.CrudUtils.showMessage("Rebase concluido.", "success");
            this.overlayRebaseSelections = {};
            this.renderOverlayRebasePreview(resultHost, response.preview || response);
            resultBox.text(JSON.stringify(response, null, 2));
          }.bind(this)).catch(function(error) {
            this.handleError(error, "Nao foi possivel rebasear o overlay.");
          }.bind(this));
        }.bind(this);
        const preview = this.overlayRebasePreview || null;
        const blockingCount = Array.isArray(preview && preview.sections)
          ? preview.sections.reduce(function(acc, section) {
              return acc + (Array.isArray(section && section.entries)
                ? section.entries.filter(function(entry) { return entry && entry.classification === "conflict_blocking"; }).length
                : 0);
            }, 0)
          : 0;
        if (blockingCount > 0 || preview && preview.canApply === false) {
          global.CrudUtils.showMessage("O preview possui conflitos bloqueantes. Ajuste o overlay antes de tentar o rebase.", "error");
          return;
        }
        if (preview && preview.requiresConfirmation) {
          global.CrudUtils.confirm("Existem conflitos leves no rebase. Deseja seguir com o plano atual?", {
            title: "Confirmar rebase",
            confirmText: "Confirmar",
            cancelText: "Voltar"
          }).then(function(confirmed) {
            if (confirmed) {
              continueRebase();
            }
          });
          return;
        }
        continueRebase();
      }.bind(this)
    });
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
      let optionValue = String(parts[0] || "").trim();
      const optionText = String(parts[1] || parts[0] || "").trim();
      if (!optionValue && optionText) {
        optionValue = optionText;
      }
      if (!optionValue) {
        return null;
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

  ProgramBuilder.prototype.parseJsonObjectText = function(text, code) {
    const value = String(text || "").trim();
    if (!value) {
      return {};
    }
    try {
      const parsed = JSON.parse(value);
      if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
        throw new Error("object_required");
      }
      return parsed;
    } catch (_) {
      throw global.CrudUtils.makeError(code || "PROGRAM_BUILDER_JSON_OBJECT_INVALID", "JSON invalido. Use um objeto no formato chave/valor.");
    }
  };

  ProgramBuilder.prototype.parseJsonArrayText = function(text, code) {
    const value = String(text || "").trim();
    if (!value) {
      return [];
    }
    try {
      const parsed = JSON.parse(value);
      if (!Array.isArray(parsed)) {
        throw new Error("array_required");
      }
      return parsed;
    } catch (_) {
      throw global.CrudUtils.makeError(code || "PROGRAM_BUILDER_JSON_ARRAY_INVALID", "JSON invalido. Use um array.");
    }
  };

  ProgramBuilder.prototype.parseJsonValueText = function(text, code) {
    const value = String(text || "").trim();
    if (!value) {
      return null;
    }
    try {
      return JSON.parse(value);
    } catch (_) {
      return value;
    }
  };

  ProgramBuilder.prototype.syncFieldRowState = function(row, detailsRow) {
    const entityType = this.entityTypeSelect && this.entityTypeSelect.value ? (this.entityTypeSelect.value() || "persistence") : "persistence";
    const apiEntity = entityType === "api";
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
    const apiJsonPath = detailsRow.find(".program-builder-field-api-json-path");
    const apiShowGrid = detailsRow.find(".program-builder-field-api-show-grid");
    const apiShowForm = detailsRow.find(".program-builder-field-api-show-form");
    const apiShowFilter = detailsRow.find(".program-builder-field-api-show-filter");

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

    const customCodeEnabled = type === "custom_code" && !virtualField && !apiEntity;
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

    detailsRow.find(".program-builder-field-include-version").prop("disabled", apiEntity);
    detailsRow.find(".program-builder-field-version-ref-entity").prop("disabled", apiEntity);
    detailsRow.find(".program-builder-field-version-ref-source").prop("disabled", apiEntity);
    detailsRow.find(".program-builder-field-version-snapshot-field").prop("disabled", apiEntity);
    detailsRow.find(".program-builder-field-version-snapshot-path").prop("disabled", apiEntity);
    detailsRow.find(".program-builder-field-virtual").prop("disabled", apiEntity);
    if (apiEntity) {
      detailsRow.find(".program-builder-field-include-version").prop("checked", false);
      detailsRow.find(".program-builder-field-version-ref-entity").val("");
      detailsRow.find(".program-builder-field-version-ref-source").val("");
      detailsRow.find(".program-builder-field-version-snapshot-field").val("");
      detailsRow.find(".program-builder-field-version-snapshot-path").val("");
      detailsRow.find(".program-builder-field-virtual").prop("checked", false);
    }

    apiJsonPath.prop("disabled", !apiEntity);
    detailsRow.find(".program-builder-field-api-write-path").prop("disabled", !apiEntity);
    apiShowGrid.prop("disabled", !apiEntity);
    apiShowForm.prop("disabled", !apiEntity);
    apiShowFilter.prop("disabled", !apiEntity);

    columnInput.prop("disabled", apiEntity || virtualField);
    requiredInput.prop("disabled", primaryKey || virtualField);
    fkTable.prop("disabled", apiEntity || primaryKey || virtualField);
    fkColumn.prop("disabled", apiEntity || primaryKey || virtualField);
    fkType.prop("disabled", apiEntity || primaryKey || virtualField);
    fkDelete.prop("disabled", apiEntity || primaryKey || virtualField);
    fkUpdate.prop("disabled", apiEntity || primaryKey || virtualField);
    uniqueInput.prop("disabled", apiEntity || primaryKey || virtualField);
    readonlyInput.prop("disabled", primaryKey || virtualField);
    defaultInput.prop("disabled", apiEntity || virtualField);
    if (apiEntity) {
      row.find(".program-builder-field-column").val(row.find(".program-builder-field-code").val() || "");
      virtualField && detailsRow.find(".program-builder-field-virtual").prop("checked", false);
      requiredInput.prop("checked", primaryKey);
      uniqueInput.prop("checked", false);
      defaultInput.val("");
      fkTable.val("");
      fkColumn.val("");
      fkType.val("");
      fkDelete.val("");
      fkUpdate.val("");
      optionItems.prop("disabled", type !== "enum" && type !== "dropdown");
    } else if (primaryKey || virtualField) {
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
    if (global.__PROGRAM_BUILDER_AUTO_INIT__ === false) {
      return;
    }
    const app = new ProgramBuilder({
      root: "#program-builder-root"
    });
    app.init();
    global.programBuilderApp = app;
  });
})(window);
