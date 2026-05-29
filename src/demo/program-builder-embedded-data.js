(function(global) {
  "use strict";

  global.ProgramBuilderEmbeddedData = {
    bootstrap: {
      currentUser: {
        id: "admin",
        name: "Administrador"
      },
      modules: [
        { code: "cadastros", name: "Cadastros", abbreviation: "CD", numberStart: 1000, numberEnd: 1999, enabled: true }
      ],
      entities: [
        { code: "cliente", name: "Cliente", entityType: "persistence" },
        { code: "parceiro_odoo", name: "Parceiro Odoo", entityType: "api" }
      ],
      apiSources: [
        {
          code: "api_clientes",
          name: "API Clientes",
          providerType: "generic",
          operations: [
            { code: "list_clientes", name: "Lista de clientes", kind: "list" },
            { code: "detail_cliente", name: "Detalhe de cliente", kind: "detail" },
            { code: "create_cliente", name: "Criar cliente", kind: "create" }
          ]
        },
        {
          code: "odoo_mock",
          name: "Odoo Mock",
          providerType: "odoo",
          operations: [
            { code: "odoo_list", name: "Lista Odoo", kind: "list" },
            { code: "odoo_detail", name: "Detalhe Odoo", kind: "detail" }
          ]
        }
      ],
      programs: [
        { code: "cd1001", title: "Cadastro de clientes" }
      ]
    },
    apiSources: {
      api_clientes: {
        code: "api_clientes",
        name: "API Clientes",
        providerType: "generic",
        baseUrl: "https://api.exemplo.local",
        authMode: "header_static",
        operations: [
          { code: "list_clientes", name: "Lista de clientes", kind: "list" },
          { code: "detail_cliente", name: "Detalhe de cliente", kind: "detail" },
          { code: "create_cliente", name: "Criar cliente", kind: "create" }
        ]
      },
      odoo_mock: {
        code: "odoo_mock",
        name: "Odoo Mock",
        providerType: "odoo",
        odoo: {
          transport: "jsonrpc",
          database: "odoo_demo",
          login: "admin",
          secretMode: "password",
          model: "res.partner",
          defaultContext: { lang: "pt_BR" },
          defaultDomain: [["active", "=", true]],
          defaultOrder: "name asc",
          defaultLimit: 40
        },
        operations: [
          { code: "odoo_list", name: "Lista Odoo", kind: "list" },
          { code: "odoo_detail", name: "Detalhe Odoo", kind: "detail" }
        ]
      }
    },
    entityDefinition: {
      code: "cliente",
      name: "Cliente",
      entityType: "persistence",
      tableName: "t_cliente",
      flags: {
        createTable: true,
        allowTableRename: true,
        allowColumnRename: true,
        dropRemovedColumns: false,
        situationEnabled: true,
        versioningEnabled: true,
        versioningDeduplicate: true
      },
      fields: [
        {
          code: "id",
          label: "ID",
          dataType: "integer",
          columnName: "id",
          required: true,
          primaryKey: true,
          options: {
            unique: true,
            readonly: true
          }
        },
        {
          code: "nome",
          label: "Nome",
          dataType: "string",
          columnName: "nome",
          length: 120,
          required: true,
          options: {
            unique: true
          }
        },
        {
          code: "uf",
          label: "UF",
          dataType: "string",
          columnName: "uf",
          length: 2,
          required: false,
          options: {
            analytics: {
              dimension: true
            }
          }
        },
        {
          code: "status",
          label: "Status",
          dataType: "string",
          columnName: "status",
          length: 20,
          required: false,
          options: {
            analytics: {
              dimension: true
            }
          }
        },
        {
          code: "limite_credito",
          label: "Limite de credito",
          dataType: "decimal",
          columnName: "limite_credito",
          required: false,
          precision: 14,
          scale: 2,
          options: {
            analytics: {
              measure: true,
              defaultAggregate: "sum",
              format: "c2"
            }
          }
        }
      ],
      rules: [
        {
          label: "Nome minimo",
          order: 10,
          phase: "beforeValidate",
          type: "class_method",
          enabled: true,
          continueOnError: false,
          className: "App\\\\Runtime\\\\ClienteBusinessRules",
          methodName: "validateNome",
          field: "nome"
        }
      ],
      uniqueKeys: [
        {
          name: "uk_cliente_nome",
          fields: "nome"
        }
      ]
    },
    programDefinition: {
      code: "cd1001",
      title: "Cadastro de clientes",
      module: "cadastros",
      screenId: "cadastros.clientes",
      version: "1.0.0",
      subtitle: "Manutencao basica",
      icon: "user",
      permissionPrefix: "cadastros.clientes",
      pageType: "crud",
      entityCode: "cliente",
      allowCreate: true,
      allowUpdate: true,
      allowDelete: true,
      changeSummary: "Exemplo local para smoke do construtor."
    }
  };
})(window);
