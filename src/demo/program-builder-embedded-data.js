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
        { code: "cliente", name: "Cliente", entityType: "persistence", tableName: "t_cliente", structureModuleCode: "cadastros" },
        { code: "empresa", name: "Empresa", entityType: "persistence", tableName: "t_empresa", structureModuleCode: "cadastros" },
        { code: "produto", name: "Produto", entityType: "persistence", tableName: "t_produto", structureModuleCode: "cadastros" },
        { code: "pedido", name: "Pedido", entityType: "persistence", tableName: "t_pedido", structureModuleCode: "cadastros" },
        { code: "pedido_item", name: "Item do pedido", entityType: "persistence", tableName: "t_pedido_item", structureModuleCode: "cadastros" },
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
    entityDefinitions: {
      cliente: {
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
      empresa: {
        code: "empresa",
        name: "Empresa",
        entityType: "persistence",
        tableName: "t_empresa",
        flags: {
          createTable: true,
          allowTableRename: true,
          allowColumnRename: true,
          dropRemovedColumns: false,
          situationEnabled: false,
          versioningEnabled: false,
          versioningDeduplicate: true
        },
        fields: [
          { code: "id", label: "ID", dataType: "integer", columnName: "id", required: true, primaryKey: true, options: { unique: true, readonly: true } },
          { code: "nome", label: "Nome", dataType: "string", columnName: "nome", length: 160, required: true, options: { analytics: { dimension: true } } },
          { code: "codigo", label: "Codigo", dataType: "string", columnName: "codigo", length: 20, required: false, options: { analytics: { dimension: true } } }
        ],
        rules: [],
        uniqueKeys: []
      },
      produto: {
        code: "produto",
        name: "Produto",
        entityType: "persistence",
        tableName: "t_produto",
        flags: {
          createTable: true,
          allowTableRename: true,
          allowColumnRename: true,
          dropRemovedColumns: false,
          situationEnabled: false,
          versioningEnabled: false,
          versioningDeduplicate: true
        },
        fields: [
          { code: "id", label: "ID", dataType: "integer", columnName: "id", required: true, primaryKey: true, options: { unique: true, readonly: true } },
          { code: "nome", label: "Produto", dataType: "string", columnName: "nome", length: 160, required: true, options: { analytics: { dimension: true } } },
          { code: "sku", label: "SKU", dataType: "string", columnName: "sku", length: 40, required: false, options: { analytics: { dimension: true } } },
          { code: "categoria", label: "Categoria", dataType: "string", columnName: "categoria", length: 60, required: false, options: { analytics: { dimension: true } } }
        ],
        rules: [],
        uniqueKeys: []
      },
      pedido: {
        code: "pedido",
        name: "Pedido",
        entityType: "persistence",
        tableName: "t_pedido",
        flags: {
          createTable: true,
          allowTableRename: true,
          allowColumnRename: true,
          dropRemovedColumns: false,
          situationEnabled: true,
          versioningEnabled: false,
          versioningDeduplicate: true
        },
        fields: [
          { code: "id", label: "ID", dataType: "integer", columnName: "id", required: true, primaryKey: true, options: { unique: true, readonly: true } },
          { code: "numero", label: "Numero", dataType: "string", columnName: "numero", length: 20, required: true, options: { analytics: { dimension: true } } },
          { code: "data_prevista", label: "Data prevista", dataType: "date", columnName: "data_prevista", required: true, options: { analytics: { dimension: true } } },
          { code: "status", label: "Status", dataType: "string", columnName: "status", length: 20, required: false, options: { analytics: { dimension: true } } },
          { code: "cliente_id", label: "Cliente", dataType: "integer", columnName: "cliente_id", required: true, foreignKeyTable: "t_cliente", foreignKeyColumn: "id", foreignKeyDependencyType: "reference", options: {} },
          { code: "empresa_id", label: "Empresa", dataType: "integer", columnName: "empresa_id", required: true, foreignKeyTable: "t_empresa", foreignKeyColumn: "id", foreignKeyDependencyType: "reference", options: {} },
          { code: "valor_previsto", label: "Valor previsto", dataType: "decimal", columnName: "valor_previsto", precision: 14, scale: 2, required: false, options: { analytics: { measure: true, defaultAggregate: "sum", format: "c2" } } }
        ],
        rules: [],
        uniqueKeys: []
      },
      pedido_item: {
        code: "pedido_item",
        name: "Item do pedido",
        entityType: "persistence",
        tableName: "t_pedido_item",
        flags: {
          createTable: true,
          allowTableRename: true,
          allowColumnRename: true,
          dropRemovedColumns: false,
          situationEnabled: false,
          versioningEnabled: false,
          versioningDeduplicate: true
        },
        fields: [
          { code: "id", label: "ID", dataType: "integer", columnName: "id", required: true, primaryKey: true, options: { unique: true, readonly: true } },
          { code: "pedido_id", label: "Pedido", dataType: "integer", columnName: "pedido_id", required: true, foreignKeyTable: "t_pedido", foreignKeyColumn: "id", foreignKeyDependencyType: "reference", options: {} },
          { code: "produto_id", label: "Produto", dataType: "integer", columnName: "produto_id", required: true, foreignKeyTable: "t_produto", foreignKeyColumn: "id", foreignKeyDependencyType: "reference", options: {} },
          { code: "quantidade", label: "Quantidade", dataType: "decimal", columnName: "quantidade", precision: 14, scale: 3, required: true, options: { analytics: { measure: true, defaultAggregate: "sum", format: "n3" } } },
          { code: "valor_previsto", label: "Valor previsto", dataType: "decimal", columnName: "valor_previsto", precision: 14, scale: 2, required: true, options: { analytics: { measure: true, defaultAggregate: "sum", format: "c2" } } }
        ],
        rules: [],
        uniqueKeys: []
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
