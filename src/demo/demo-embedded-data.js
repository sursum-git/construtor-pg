(function(global) {
  'use strict';

  global.CrudDemoEmbedded = {
    config: {
  "$schema": "../metadata/schemas/crud-engine-config-v1.schema.json",
  "schemaVersion": "1.0",
  "security": {
    "mode": "demo",
    "definitionSource": {
      "allowDirectDefinition": true,
      "allowDefinitionUrl": true,
      "requireScreenId": false,
      "endpoint": {
        "url": "/api/runtime/screens/{screenId}",
        "method": "GET"
      }
    },
    "endpoints": {
      "allowInlineUrls": true,
      "requireEndpointIds": false,
      "runtimeEndpoint": {
        "url": "/api/runtime/screens/{screenId}/endpoints/{endpointId}",
        "method": "POST"
      }
    },
    "documents": {
      "allowInlineUrls": true,
      "allowExternalUrls": false,
      "runtimeEndpoint": {
        "url": "/api/runtime/screens/{screenId}/documents/{documentId}",
        "method": "GET"
      }
    },
    "content": {
      "allowInlineHtml": true
    }
  },
  "theme": {
    "kendoTheme": "kendo/styles/default-urban.css",
    "defaultMode": "light",
    "allowUserSwitch": true,
    "persistUserChoice": true,
    "storageKey": "crudEngine.theme",
    "tokens": {
      "light": {
        "background": "#f4f5f7",
        "surface": "#ffffff",
        "border": "#d9dee7",
        "text": "#1f2937",
        "muted": "#667085",
        "title": "#0f4f8f",
        "accent": "#0d6efd",
        "accentSoft": "#eef6ff",
        "accentBorder": "#bfdaf8",
        "inputBackground": "#ffffff",
        "readonlyBackground": "#f8fafc",
        "messageBackground": "#ffffff",
        "errorBorder": "#f4c7c3",
        "errorBackground": "#fff5f5",
        "buttonBackground": "#ffffff",
        "buttonHoverBackground": "#f8fafc",
        "buttonBorder": "#cfd5df",
        "buttonText": "#344054",
        "buttonPrimaryBackground": "#0f5f9f",
        "buttonPrimaryHoverBackground": "#0b4f8a",
        "buttonPrimaryText": "#ffffff",
        "notificationBackground": "#d92d20",
        "notificationText": "#ffffff",
        "danger": "#b42318"
      },
      "dark": {
        "background": "#111827",
        "surface": "#182230",
        "border": "#344054",
        "text": "#f2f4f7",
        "muted": "#98a2b3",
        "title": "#8ec5ff",
        "accent": "#4da3ff",
        "accentSoft": "#102a43",
        "accentBorder": "#275f9f",
        "inputBackground": "#101828",
        "readonlyBackground": "#101828",
        "messageBackground": "#182230",
        "errorBorder": "#7a271a",
        "errorBackground": "#2a1210",
        "buttonBackground": "#202c3d",
        "buttonHoverBackground": "#28384d",
        "buttonBorder": "#42526a",
        "buttonText": "#f2f4f7",
        "buttonPrimaryBackground": "#2f7ec8",
        "buttonPrimaryHoverBackground": "#3b8fdf",
        "buttonPrimaryText": "#ffffff",
        "notificationBackground": "#f97066",
        "notificationText": "#111827",
        "danger": "#ffb4ab"
      }
    }
  },
  "help": {
    "enabled": true,
    "title": "Ajuda e novidades",
    "storageKey": "crudEngine.help.seen",
    "readEndpoint": {
      "url": "/api/help/seen",
      "method": "POST"
    },
    "items": [
      {
        "id": "filtros-iniciais",
        "title": "Nova regra de filtros iniciais",
        "kind": "text",
        "publishedAt": "2026-05-02",
        "summary": "Quando a tela abre com filtros, o grid aguarda o usuario consultar.",
        "body": "O parametro crud.filter.openOnLoad controla se a janela de filtros abre automaticamente. Com crud.filter.waitForSubmitOnLoad ativo, o grid nao carrega dados ate o usuario clicar em Filtrar."
      },
      {
        "id": "leiautes-nomeados",
        "title": "Leiautes nomeados",
        "kind": "text",
        "publishedAt": "2026-05-02",
        "summary": "Agora a tela permite salvar mais de um leiaute.",
        "body": "A opcao Leiaute permite aplicar, salvar e restaurar configuracoes do grid. Ao salvar, o usuario informa um nome e pode definir o leiaute como padrao."
      }
    ]
  }
},
    clientesDefinition: {
  "schemaVersion": "1.0",
  "pageType": "crud",
  "program": {
    "id": "cadastros.clientes",
    "module": "cadastros",
    "entity": "clientes",
    "title": "Clientes",
    "version": "1.0.0",
    "subtitle": "Consulta e manutencao de clientes",
    "subtitleTooltip": "Tela de demonstracao para consulta, manutencao, filtros e leiautes do cadastro de clientes.",
    "help": {
      "enabled": true,
      "title": "Ajuda do Cadastro de Clientes",
      "kind": "text",
      "summary": "Use esta tela para consultar, criar, editar, visualizar e excluir clientes.",
      "body": "A janela de filtros permite restringir a consulta por busca, status e periodo de cadastro.\nA opcao Leiaute permite salvar configuracoes do grid com nome e marcar um leiaute como padrao.",
      "items": [
        {
          "id": "documentacao-completa",
          "title": "Documentacao completa",
          "kind": "link",
          "summary": "Conteudo de ajuda carregado por link dentro da propria janela.",
          "linkUrl": "docs/clientes-ajuda.html",
          "linkText": "Abrir em nova aba"
        }
      ]
    },
    "logs": {
      "enabled": true,
      "title": "Logs Gerais do Cadastro de Clientes",
      "url": "docs/clientes-logs.html",
      "linkText": "Abrir logs em nova aba"
    }
  },
  "permissions": {
    "read": true,
    "create": true,
    "edit": true,
    "delete": true,
    "saveLayout": true
  },
  "security": {
    "userGroups": [
      "vendas"
    ]
  },
  "dataSource": {
    "api": {
      "read": {
        "url": "/api/cadastros/clientes",
        "method": "GET"
      },
      "get": {
        "url": "/api/cadastros/clientes/{id}",
        "method": "GET"
      },
      "create": {
        "url": "/api/cadastros/clientes",
        "method": "POST"
      },
      "update": {
        "url": "/api/cadastros/clientes/{id}",
        "method": "PUT"
      },
      "delete": {
        "url": "/api/cadastros/clientes/{id}",
        "method": "DELETE"
      },
      "validateStatusCliente": {
        "url": "/api/cadastros/clientes/form-rules/status",
        "method": "POST"
      },
      "loadCidadesByUf": {
        "url": "/api/localidades/cidades",
        "method": "POST"
      },
      "statusHistory": {
        "url": "/api/cadastros/clientes/status-history",
        "method": "POST"
      },
      "stepHistory": {
        "url": "/api/cadastros/clientes/{id}/steps/{stepId}/history",
        "method": "POST"
      },
      "printClienteExcel": {
        "url": "/api/cadastros/clientes/{id}/print/excel",
        "method": "POST"
      },
      "printClientePdf": {
        "url": "/api/cadastros/clientes/{id}/print/pdf",
        "method": "POST"
      },
      "printClienteCsv": {
        "url": "/api/cadastros/clientes/{id}/print/csv",
        "method": "POST"
      },
      "checkCredit": {
        "url": "/api/cadastros/clientes/{id}/actions/check-credit",
        "method": "POST"
      },
      "sendWelcome": {
        "url": "/api/cadastros/clientes/{id}/actions/send-welcome",
        "method": "POST"
      },
      "bulkActivate": {
        "url": "/api/cadastros/clientes/bulk/status",
        "method": "POST"
      },
      "bulkInactivate": {
        "url": "/api/cadastros/clientes/bulk/status",
        "method": "POST"
      },
      "bulkDelete": {
        "url": "/api/cadastros/clientes/bulk/delete",
        "method": "POST"
      },
      "saveLayout": {
        "url": "/api/crud-layout/cadastros/clientes",
        "method": "POST"
      },
      "restoreLayout": {
        "url": "/api/crud-layout/cadastros/clientes",
        "method": "DELETE"
      },
      "saveSort": {
        "url": "/api/crud-layout/cadastros/clientes/sorts",
        "method": "POST"
      },
      "deleteSort": {
        "url": "/api/crud-layout/cadastros/clientes/sorts/{id}",
        "method": "DELETE"
      },
      "saveGroup": {
        "url": "/api/crud-layout/cadastros/clientes/groups",
        "method": "POST"
      },
      "deleteGroup": {
        "url": "/api/crud-layout/cadastros/clientes/groups/{id}",
        "method": "DELETE"
      },
      "saveFilter": {
        "url": "/api/crud-layout/cadastros/clientes/filters",
        "method": "POST"
      },
      "deleteFilter": {
        "url": "/api/crud-layout/cadastros/clientes/filters/{id}",
        "method": "DELETE"
      }
    }
  },
  "dataModel": {
    "primaryKey": "id",
    "fields": {
      "id": {
        "type": "integer",
        "label": "ID",
        "editable": false,
        "nullable": false
      },
      "nome": {
        "type": "string",
        "label": "Nome",
        "editable": true,
        "nullable": false,
        "validation": {
          "required": true,
          "maxLength": 120
        }
      },
      "email": {
        "type": "email",
        "label": "E-mail",
        "editable": true,
        "nullable": true,
        "validation": {
          "required": false,
          "maxLength": 150
        }
      },
      "status": {
        "type": "enum",
        "label": "Status",
        "editable": true,
        "nullable": false,
        "options": [
          {
            "value": "ATIVO",
            "text": "Ativo"
          },
          {
            "value": "INATIVO",
            "text": "Inativo"
          }
        ]
      },
      "tipo_pessoa": {
        "type": "enum",
        "label": "Tipo de Pessoa",
        "editable": true,
        "nullable": false,
        "options": [
          {
            "value": "PF",
            "text": "Pessoa Fisica"
          },
          {
            "value": "PJ",
            "text": "Pessoa Juridica"
          }
        ]
      },
      "uf": {
        "type": "enum",
        "label": "UF",
        "editable": true,
        "nullable": true,
        "options": [
          {
            "value": "",
            "text": "Selecione"
          },
          {
            "value": "CE",
            "text": "Ceara"
          },
          {
            "value": "SP",
            "text": "Sao Paulo"
          },
          {
            "value": "RJ",
            "text": "Rio de Janeiro"
          }
        ]
      },
      "cidade": {
        "type": "dropdown",
        "label": "Cidade",
        "editable": true,
        "nullable": true,
        "options": []
      },
      "razao_social": {
        "type": "string",
        "label": "Razao Social",
        "editable": true,
        "nullable": true,
        "validation": {
          "maxLength": 150
        }
      },
      "cnpj": {
        "type": "string",
        "label": "CNPJ",
        "editable": true,
        "nullable": true,
        "validation": {
          "maxLength": 18
        }
      },
      "data_cadastro": {
        "type": "date",
        "label": "Data de Cadastro",
        "editable": false,
        "nullable": true
      },
      "valor_total": {
        "type": "decimal",
        "label": "Valor Total",
        "editable": false,
        "nullable": true,
        "format": "currency"
      },
      "qtde_pedidos": {
        "type": "integer",
        "label": "Qtde. Pedidos",
        "editable": false,
        "nullable": true
      },
      "observacao": {
        "type": "text",
        "label": "Observacao",
        "editor": "textarea",
        "editable": true,
        "nullable": true
      }
    }
  },
  "crud": {
    "features": {
      "filterPanel": true
    },
    "query": {
      "defaultPageSize": 10,
      "pageSizes": [
        10,
        20,
        50,
        100
      ],
      "defaultSort": [
        {
          "field": "nome",
          "dir": "asc"
        }
      ]
    },
    "filter": {
      "type": "window",
      "title": "Consulta de Clientes",
      "openOnLoad": false,
      "maximizeFilter": false,
      "waitForSubmitOnLoad": true,
      "showAppliedFilters": true,
      "tabs": {
        "enabled": true,
        "items": [
          {
            "id": "geral",
            "title": "Geral",
            "fields": [
              "busca",
              "nome_operador",
              "status"
            ]
          },
          {
            "id": "periodo",
            "title": "Periodo",
            "fields": [
              "periodo_cadastro"
            ]
          },
          {
            "id": "metricas",
            "title": "Metricas",
            "fields": [
              "valor_total_filtro",
              "qtde_pedidos_filtro"
            ]
          }
        ]
      },
      "fields": [
        {
          "id": "busca",
          "label": "Busca",
          "type": "search",
          "placeholder": "Nome ou e-mail",
          "fields": [
            "nome",
            "email"
          ],
          "operator": "contains"
        },
        {
          "id": "nome_operador",
          "field": "nome",
          "label": "Nome",
          "type": "text",
          "placeholder": "Informe o nome",
          "operators": [
            "contains",
            "eq",
            "startsWith",
            "notContains",
            "isEmpty",
            "isNull",
            "isNotEmpty",
            "isNotNull",
            "between",
            "in"
          ]
        },
        {
          "id": "status",
          "field": "status",
          "label": "Status",
          "type": "enum",
          "editor": "dropdown",
          "operator": "eq",
          "defaultValue": "ATIVO"
        },
        {
          "id": "periodo_cadastro",
          "field": "data_cadastro",
          "label": "Periodo de Cadastro",
          "type": "date",
          "editor": "dateRange",
          "operator": "between",
          "operators": [
            "between",
            "eq",
            "gte",
            "lte",
            "gt",
            "lt",
            "relative"
          ],
          "relativeDate": {
            "defaultPreset": "months",
            "defaultAmount": 3,
            "defaultDirection": "previous"
          }
        },
        {
          "id": "valor_total_filtro",
          "field": "valor_total",
          "label": "Valor Total",
          "type": "decimal",
          "operator": "gte",
          "operators": [
            "eq",
            "gte",
            "lte",
            "lt",
            "gt",
            "between"
          ]
        },
        {
          "id": "qtde_pedidos_filtro",
          "field": "qtde_pedidos",
          "label": "Quantidade de Pedidos",
          "type": "integer",
          "operator": "gte",
          "operators": [
            "eq",
            "gte",
            "lte",
            "lt",
            "gt",
            "between",
            "in",
            "notIn"
          ]
        }
      ]
    },
    "grid": {
      "id": "clientesGrid",
      "height": "auto",
      "pageable": true,
      "sortable": true,
      "filterable": true,
      "groupable": false,
      "resizable": true,
      "reorderable": true,
      "columnMenu": true,
      "ai": {
        "enabled": true,
        "provider": "mock",
        "tool": "smartbox",
        "activeMode": "AIAssistant",
        "placeholder": "Ordene, filtre ou agrupe com IA",
        "searchPlaceholder": "Pesquisar no grid",
        "searchFields": [
          "nome",
          "email",
          "status"
        ],
        "promptSuggestions": [
          "Ordenar por maior valor total",
          "Mostrar apenas clientes ativos",
          "Agrupar por status",
          "Limpar filtros e agrupamentos"
        ]
      },
      "selectable": "row",
      "mobile": {
        "enabled": true,
        "breakpoint": 720,
        "mode": "template",
        "title": "Clientes",
        "cardActions": true,
        "template": {
          "titleField": "nome",
          "subtitleField": "email",
          "badges": [
            "status"
          ],
          "tabs": {
            "enabled": true,
            "items": [
              {
                "id": "geral",
                "title": "Geral",
                "fields": [
                  "status",
                  "email",
                  "data_cadastro"
                ]
              },
              {
                "id": "comercial",
                "title": "Comercial",
                "fields": [
                  "valor_total",
                  "qtde_pedidos"
                ]
              }
            ]
          }
        }
      },
      "bulkActions": {
        "enabled": true,
        "label": "Acoes em massa",
        "icon": "more-vertical",
        "selectable": "multiple, row",
        "actions": [
          {
            "id": "activate",
            "label": "Ativar selecionados",
            "icon": "check",
            "action": "bulkActivate",
            "endpointId": "bulkActivate",
            "permission": "edit",
            "value": "ATIVO",
            "confirm": {
              "message": "Deseja ativar {count} cliente(s) selecionado(s)?"
            },
            "successMessage": "Clientes ativados."
          },
          {
            "id": "inactivate",
            "label": "Inativar selecionados",
            "icon": "cancel",
            "action": "bulkInactivate",
            "endpointId": "bulkInactivate",
            "permission": "edit",
            "value": "INATIVO",
            "confirm": {
              "message": "Deseja inativar {count} cliente(s) selecionado(s)?"
            },
            "successMessage": "Clientes inativados."
          },
          {
            "id": "delete",
            "label": "Excluir selecionados",
            "icon": "trash",
            "action": "bulkDelete",
            "endpointId": "bulkDelete",
            "permission": "delete",
            "confirm": {
              "message": "Deseja excluir {count} cliente(s) selecionado(s)?"
            },
            "successMessage": "Clientes excluidos."
          }
        ]
      },
      "freezeColumns": {
        "enabled": true,
        "fields": []
      },
      "print": {
        "enabled": true,
        "label": "Imprimir",
        "icon": "print",
        "fileName": "clientes",
        "options": [
          "excel",
          "pdf",
          "csv"
        ]
      },
      "columns": [
        {
          "field": "id",
          "title": "ID",
          "width": 80,
          "visible": false,
          "filterable": false,
          "sortable": true
        },
        {
          "field": "nome",
          "title": "Nome",
          "width": 240,
          "visible": true,
          "filterable": true,
          "sortable": true
        },
        {
          "field": "email",
          "title": "E-mail",
          "width": 260,
          "visible": true,
          "filterable": true,
          "sortable": true
        },
        {
          "field": "status",
          "title": "Status",
          "width": 120,
          "visible": true,
          "filterable": true,
          "sortable": true
        },
        {
          "field": "data_cadastro",
          "title": "Cadastro",
          "width": 140,
          "visible": true,
          "filterable": true,
          "sortable": true,
          "format": "date"
        },
        {
          "field": "valor_total",
          "title": "Valor Total",
          "width": 140,
          "visible": true,
          "filterable": true,
          "sortable": true,
          "format": "currency",
          "align": "right"
        },
        {
          "field": "qtde_pedidos",
          "title": "Pedidos",
          "width": 110,
          "visible": true,
          "filterable": true,
          "sortable": true,
          "align": "right"
        }
      ],
      "toolbar": [
        {
          "id": "create",
          "label": "Novo",
          "icon": "plus",
          "action": "create",
          "permission": "create"
        },
        {
          "id": "filters",
          "label": "Filtros",
          "icon": "filter",
          "action": "filters",
          "permission": "read"
        },
        {
          "id": "refresh",
          "label": "Atualizar",
          "icon": "arrow-rotate-cw",
          "action": "refresh",
          "permission": "read"
        },
        {
          "id": "sort",
          "label": "Ordenacao",
          "icon": "sort-asc",
          "action": "sort",
          "permission": "read"
        },
        {
          "id": "group",
          "label": "Agrupar",
          "icon": "group",
          "action": "group",
          "permission": "read"
        },
        {
          "id": "layout",
          "label": "Leiaute",
          "icon": "layout",
          "action": "layout",
          "permission": "saveLayout"
        }
      ],
      "rowActions": [
        {
          "id": "view",
          "label": "Visualizar",
          "action": "view",
          "permission": "read"
        },
        {
          "id": "edit",
          "label": "Editar",
          "action": "edit",
          "permission": "edit"
        },
        {
          "id": "delete",
          "label": "Excluir",
          "action": "delete",
          "permission": "delete",
          "confirm": {
            "message": "Deseja excluir este registro?"
          }
        }
      ]
    },
    "form": {
      "id": "clienteForm",
      "mode": "popup",
      "width": 900,
      "maximizeForm": false,
      "behavior": {
        "closeOnSave": true,
        "closeOnCancel": false
      },
      "navigation": {
        "enabled": true
      },
      "mobile": {
        "breakpoint": 720,
        "showHeaderActions": false
      },
      "logs": {
        "enabled": true,
        "title": "Logs do Cliente",
        "url": "docs/clientes-logs.html?id={id}",
        "linkText": "Abrir logs do cliente em nova aba"
      },
      "print": {
        "enabled": true,
        "label": "Imprimir",
        "icon": "print",
        "options": [
          {
            "format": "excel",
            "label": "Excel",
            "source": "api",
            "endpointId": "printClienteExcel",
            "successMessage": "Impressao em Excel solicitada."
          },
          {
            "format": "pdf",
            "label": "PDF",
            "source": "api",
            "endpointId": "printClientePdf",
            "successMessage": "Impressao em PDF solicitada."
          },
          {
            "format": "csv",
            "label": "CSV",
            "source": "api",
            "endpointId": "printClienteCsv",
            "successMessage": "Impressao em CSV solicitada."
          }
        ]
      },
      "otherActions": {
        "enabled": true,
        "label": "Outras acoes",
        "icon": "more-vertical",
        "actions": [
          {
            "id": "checkCredit",
            "label": "Analisar credito",
            "icon": "check",
            "endpointId": "checkCredit",
            "permission": "read",
            "visibleIn": [
              "view",
              "edit"
            ],
            "successMessage": "Analise de credito solicitada."
          },
          {
            "id": "sendWelcome",
            "label": "Enviar boas-vindas",
            "icon": "email",
            "endpointId": "sendWelcome",
            "permission": "edit",
            "visibleIn": [
              "view",
              "edit"
            ],
            "confirm": {
              "message": "Deseja enviar boas-vindas para este cliente?"
            },
            "successMessage": "Envio de boas-vindas solicitado."
          }
        ]
      },
      "situation": {
        "enabled": true,
        "field": "status",
        "label": "Situacao",
        "display": "stepper",
        "historyTitle": "Historico da situacao",
        "historyEndpointId": "statusHistory",
        "steps": [
          {
            "value": "ATIVO",
            "text": "Ativo",
            "description": "Cliente liberado para operacao."
          },
          {
            "value": "INATIVO",
            "text": "Inativo",
            "description": "Cliente bloqueado ou pausado."
          }
        ]
      },
      "title": {
        "create": "Novo Cliente",
        "edit": "Editar Cliente",
        "view": "Visualizar Cliente"
      },
      "layout": "steps",
      "steps": [
        {
          "id": "identificacao",
          "title": "Identificacao",
          "description": "Dados basicos obrigatorios para avancar.",
          "requiredFields": [
            "nome",
            "email",
            "status",
            "tipo_pessoa"
          ],
          "logs": {
            "enabled": true,
            "endpointId": "stepHistory",
            "label": "Logs da etapa"
          },
          "sections": [
            {
              "id": "identificacao",
              "title": "Identificacao",
              "columns": 2,
              "fields": [
                {
                  "field": "nome",
                  "id": "nomeCliente",
                  "colSpan": 2
                },
                {
                  "field": "email",
                  "colSpan": 2
                },
                {
                  "field": "status",
                  "colSpan": 1
                },
                {
                  "field": "tipo_pessoa",
                  "colSpan": 1
                },
                {
                  "field": "uf",
                  "colSpan": 1
                },
                {
                  "field": "cidade",
                  "colSpan": 1
                },
                {
                  "field": "observacao",
                  "colSpan": 2
                }
              ]
            }
          ]
        },
        {
          "id": "dados_pj",
          "title": "Dados PJ",
          "description": "Etapa apenas para consulta para usuarios do grupo vendas.",
          "readonlyGroups": [
            "vendas"
          ],
          "logs": {
            "enabled": true,
            "endpointId": "stepHistory",
            "label": "Logs da etapa"
          },
          "sections": [
            {
              "id": "juridica",
              "title": "Pessoa Juridica",
              "columns": 2,
              "fields": [
                {
                  "field": "razao_social",
                  "colSpan": 2
                },
                {
                  "field": "cnpj",
                  "colSpan": 1
                }
              ]
            }
          ]
        },
        {
          "id": "comercial",
          "title": "Comercial",
          "description": "Metricas comerciais calculadas.",
          "readonly": true,
          "logs": {
            "enabled": true,
            "endpointId": "stepHistory",
            "label": "Logs da etapa"
          },
          "sections": [
            {
              "id": "metricas",
              "title": "Metricas",
              "columns": 2,
              "fields": [
                {
                  "field": "valor_total",
                  "readonly": true
                },
                {
                  "field": "qtde_pedidos",
                  "readonly": true
                },
                {
                  "field": "data_cadastro",
                  "readonly": true,
                  "colSpan": 2
                }
              ]
            }
          ]
        }
      ],
      "tabs": [
        {
          "id": "geral",
          "title": "Geral",
          "sections": [
            {
              "id": "identificacao",
              "title": "Identificacao",
              "columns": 2,
              "fields": [
                {
                  "field": "nome",
                  "id": "nomeCliente",
                  "colSpan": 2
                },
                {
                  "field": "email",
                  "colSpan": 2
                },
                {
                  "field": "status",
                  "colSpan": 1
                },
                {
                  "field": "tipo_pessoa",
                  "colSpan": 1
                },
                {
                  "field": "uf",
                  "colSpan": 1
                },
                {
                  "field": "cidade",
                  "colSpan": 1
                },
                {
                  "field": "observacao",
                  "colSpan": 2
                }
              ]
            }
          ]
        },
        {
          "id": "dados_pj",
          "title": "Dados PJ",
          "sections": [
            {
              "id": "juridica",
              "title": "Pessoa Juridica",
              "columns": 2,
              "fields": [
                {
                  "field": "razao_social",
                  "colSpan": 2
                },
                {
                  "field": "cnpj",
                  "colSpan": 1
                }
              ]
            }
          ]
        },
        {
          "id": "comercial",
          "title": "Comercial",
          "sections": [
            {
              "id": "metricas",
              "title": "Metricas",
              "columns": 2,
              "fields": [
                {
                  "field": "valor_total",
                  "readonly": true
                },
                {
                  "field": "qtde_pedidos",
                  "readonly": true
                },
                {
                  "field": "data_cadastro",
                  "readonly": true,
                  "colSpan": 2
                }
              ]
            }
          ]
        }
      ],
      "events": [
        {
          "id": "tipo-pessoa-toggle-pj-open",
          "source": "tipo_pessoa",
          "event": "afterLoad",
          "effects": [
            {
              "target": "step.dados_pj",
              "action": "visible",
              "valueWhen": {
                "field": "tipo_pessoa",
                "operator": "eq",
                "value": "PJ"
              }
            }
          ]
        },
        {
          "id": "tipo-pessoa-toggle-pj-change",
          "source": "tipo_pessoa",
          "event": "change",
          "effects": [
            {
              "target": "step.dados_pj",
              "action": "visible",
              "valueWhen": {
                "field": "tipo_pessoa",
                "operator": "eq",
                "value": "PJ"
              }
            }
          ]
        },
        {
          "id": "cidade-by-uf-open",
          "source": "uf",
          "event": "afterLoad",
          "endpointId": "loadCidadesByUf",
          "request": {
            "map": {
              "uf": "uf"
            }
          },
          "response": {
            "effects": [
              {
                "target": "cidade",
                "action": "reloadOptions",
                "optionsFrom": "response.options",
                "clearInvalidValue": true,
                "disableWhenEmpty": true
              }
            ]
          }
        },
        {
          "id": "cidade-by-uf-change",
          "source": "uf",
          "event": "change",
          "endpointId": "loadCidadesByUf",
          "request": {
            "map": {
              "uf": "uf"
            }
          },
          "response": {
            "effects": [
              {
                "target": "cidade",
                "action": "reloadOptions",
                "optionsFrom": "response.options",
                "clearInvalidValue": true,
                "disableWhenEmpty": true
              }
            ]
          }
        },
        {
          "id": "status-observacao-rules",
          "source": "status",
          "event": "change",
          "endpointId": "validateStatusCliente",
          "request": {
            "map": {
              "status": "status"
            }
          }
        }
      ],
      "buttons": [
        {
          "id": "logs",
          "label": "Logs",
          "action": "logs",
          "icon": "list-unordered",
          "permission": "read",
          "visibleIn": [
            "view",
            "edit",
            "delete"
          ]
        },
        {
          "id": "save",
          "label": "Confirmar",
          "action": "save",
          "permission": "edit",
          "endpoints": {
            "create": "create",
            "edit": "update"
          },
          "visibleIn": [
            "create",
            "edit"
          ]
        },
        {
          "id": "cancel",
          "label": "Cancelar",
          "action": "cancel",
          "visibleIn": [
            "create",
            "edit",
            "view"
          ]
        }
      ]
    },
    "userLayout": {
      "enabled": true,
      "version": 1,
      "source": "default",
      "activeLayoutId": null,
      "activeSortId": null,
      "activeGroupId": null,
      "activeFilterId": null,
      "definitionHash": "clientes-demo-v1",
      "savedLayouts": [],
      "savedSorts": [],
      "savedGroups": [],
      "savedFilters": [],
      "grid": {
        "columns": {
          "order": [],
          "hidden": [],
          "widths": {},
          "frozen": [],
          "added": []
        },
        "sort": [],
        "filter": null,
        "group": [],
        "groupAggregates": []
      }
    }
  }
}
  };
})(window);
