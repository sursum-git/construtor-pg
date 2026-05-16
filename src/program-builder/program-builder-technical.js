(function(global) {
  "use strict";

  const ProgramBuilder = global.ProgramBuilder;
  if (!ProgramBuilder) {
    return;
  }

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
})(window);
