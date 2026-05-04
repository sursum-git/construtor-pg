(function(global) {
  "use strict";

  const pagePath = "examples/pages/";

  const examples = [
    {
      id: "programa-ajuda-logs",
      category: "Programa",
      title: "Cabecalho com ajuda e logs",
      summary: "Mostra versao, subtitulo, ajuda e log quando o JSON informa essas opcoes.",
      initialAction: null,
      code: {
        program: {
          version: "1.0.0",
          subtitle: "Consulta e manutencao de clientes",
          subtitleTooltip: "Texto maior exibido em janela/tooltip.",
          help: {
            enabled: true,
            title: "Ajuda do Cadastro",
            kind: "text",
            body: "Conteudo de ajuda ou novidades.",
            readEndpoint: {
              url: "/api/help/seen",
              method: "POST"
            }
          },
          logs: {
            enabled: true,
            title: "Logs Gerais",
            url: "docs/clientes-logs.html"
          }
        }
      },
      apply: function(definition) {
        definition.program.version = "1.0.0";
        definition.program.subtitle = "Consulta e manutencao de clientes";
        definition.program.subtitleTooltip = "Texto maior exibido em janela/tooltip.";
        definition.program.help = {
          enabled: true,
          title: "Ajuda do Cadastro",
          kind: "text",
          summary: "Ajuda e novidades da tela.",
          body: "Conteudo de ajuda ou novidades.",
          readEndpoint: {
            url: "/api/help/seen",
            method: "POST"
          }
        };
        definition.program.logs = {
          enabled: true,
          title: "Logs Gerais",
          url: "../../docs/clientes-logs.html",
          linkText: "Abrir logs"
        };
      }
    },
    {
      id: "consulta-basica",
      category: "Consulta",
      title: "Consulta basica",
      summary: "Renderiza toolbar, filtros manuais e grid paginado com ordenacao.",
      code: {
        crud: {
          query: {
            defaultPageSize: 10,
            pageSizes: [10, 20, 50, 100],
            defaultSort: [{ field: "nome", dir: "asc" }]
          },
          grid: {
            pageable: true,
            sortable: true,
            filterable: true,
            columns: ["nome", "email", "status", "data_cadastro", "valor_total"]
          }
        }
      },
      apply: function() {}
    },
    {
      id: "filtro-inicial",
      category: "Filtros",
      title: "Filtro aberto na entrada",
      summary: "Abre a janela de filtros ao entrar e espera o usuario consultar.",
      initialAction: null,
      code: {
        crud: {
          filter: {
            type: "window",
            openOnLoad: true,
            waitForSubmitOnLoad: true,
            maximizeFilter: false,
            showAppliedFilters: true
          }
        }
      },
      apply: function(definition) {
        definition.crud.filter.openOnLoad = true;
        definition.crud.filter.waitForSubmitOnLoad = true;
        definition.crud.filter.maximizeFilter = false;
      }
    },
    {
      id: "filtro-abas-salvos",
      category: "Filtros",
      title: "Filtros em abas e filtros salvos",
      summary: "Divide os campos por abas e mostra filtro salvo padrao.",
      initialAction: "openFilter",
      code: {
        crud: {
          filter: {
            tabs: {
              enabled: true,
              items: [
                { id: "geral", title: "Geral", fields: ["busca", "nome_operador", "status"] },
                { id: "periodo", title: "Periodo", fields: ["periodo_cadastro"] },
                { id: "metricas", title: "Metricas", fields: ["valor_total_filtro", "qtde_pedidos_filtro"] }
              ]
            }
          },
          userLayout: {
            activeFilterId: "ativos",
            savedFilters: [
              {
                id: "ativos",
                name: "Clientes ativos",
                isDefault: true,
                filters: [
                  { id: "status", field: "status", operator: "eq", value: "ATIVO", displayValue: "Ativo" }
                ]
              }
            ]
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.filter.tabs = clone(source.crud.filter.tabs);
        definition.crud.userLayout.activeFilterId = "ativos";
        definition.crud.userLayout.savedFilters = [
          {
            id: "ativos",
            name: "Clientes ativos",
            isDefault: true,
            filters: [
              {
                id: "status",
                field: "status",
                label: "Status",
                type: "enum",
                operator: "eq",
                value: "ATIVO",
                displayValue: "Ativo"
              }
            ]
          }
        ];
      }
    },
    {
      id: "filtros-aplicados-ocultos",
      category: "Filtros",
      title: "Ocultar filtros aplicados",
      summary: "Desliga os chips de filtros aplicados na tela de consulta.",
      code: {
        crud: {
          filter: {
            showAppliedFilters: false
          }
        }
      },
      apply: function(definition) {
        definition.crud.filter.showAppliedFilters = false;
      }
    },
    {
      id: "grid-mobile-colunas",
      category: "Grid",
      title: "Mobile mantendo grid",
      summary: "No mobile, mantem o grid e exibe somente as colunas escolhidas.",
      code: {
        crud: {
          grid: {
            mobile: {
              enabled: true,
              mode: "columns",
              breakpoint: 720,
              columns: ["nome", "status"]
            }
          }
        }
      },
      apply: function(definition) {
        definition.crud.grid.mobile = {
          enabled: true,
          mode: "columns",
          breakpoint: 2000,
          columns: ["nome", "status"]
        };
      }
    },
    {
      id: "grid-mobile-template",
      category: "Grid",
      title: "Mobile com template",
      summary: "Usa card mobile com abas e campos definidos pelo JSON.",
      code: {
        crud: {
          grid: {
            mobile: {
              enabled: true,
              mode: "template",
              breakpoint: 720,
              cardActions: true,
              template: {
                titleField: "nome",
                subtitleField: "email",
                badges: ["status"],
                tabs: {
                  enabled: true,
                  items: [
                    { id: "geral", title: "Geral", fields: ["status", "email", "data_cadastro"] },
                    { id: "comercial", title: "Comercial", fields: ["valor_total", "qtde_pedidos"] }
                  ]
                }
              }
            }
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.grid.mobile = clone(source.crud.grid.mobile);
        definition.crud.grid.mobile.breakpoint = 2000;
      }
    },
    {
      id: "acoes-massa",
      category: "Grid",
      title: "Acoes em massa",
      summary: "Exibe dropdown de acoes para linhas selecionadas apenas quando ha acoes no JSON.",
      code: {
        crud: {
          grid: {
            bulkActions: {
              enabled: true,
              label: "Acoes em massa",
              actions: [
                { id: "activate", label: "Ativar selecionados", endpointId: "bulkActivate" },
                { id: "inactivate", label: "Inativar selecionados", endpointId: "bulkInactivate" },
                { id: "delete", label: "Excluir selecionados", endpointId: "bulkDelete" }
              ]
            }
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.grid.bulkActions = clone(source.crud.grid.bulkActions);
      }
    },
    {
      id: "impressao-grid",
      category: "Grid",
      title: "Exportacao do grid",
      summary: "Mostra o botao Imprimir com Excel, PDF e CSV usando recursos do grid.",
      code: {
        crud: {
          grid: {
            print: {
              enabled: true,
              label: "Imprimir",
              fileName: "clientes",
              options: ["excel", "pdf", "csv"]
            }
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.grid.print = clone(source.crud.grid.print);
      }
    },
    {
      id: "ia-grid",
      category: "Grid",
      title: "SmartBox de IA do grid",
      summary: "Habilita o SmartBox do Kendo com interpretador mock local.",
      code: {
        crud: {
          grid: {
            ai: {
              enabled: true,
              provider: "mock",
              tool: "smartbox",
              searchFields: ["nome", "email", "status"],
              promptSuggestions: [
                "Ordenar por maior valor total",
                "Mostrar apenas clientes ativos",
                "Agrupar por status"
              ]
            }
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.grid.ai = clone(source.crud.grid.ai);
      }
    },
    {
      id: "leiautes-ordenacao-agrupamento",
      category: "Leiautes",
      title: "Leiautes, ordenacao e agrupamento",
      summary: "Mostra botoes de leiaute, ordenacao, agrupamento e agregados salvos.",
      initialAction: "openGroup",
      code: {
        crud: {
          grid: {
            toolbar: ["create", "filters", "refresh", "sort", "group", "layout"],
            freezeColumns: {
              enabled: true,
              fields: []
            }
          },
          userLayout: {
            savedSorts: [
              { id: "maior-valor", name: "Maior valor", isDefault: true, sort: [{ field: "valor_total", dir: "desc" }] }
            ],
            savedGroups: [
              {
                id: "por-status",
                name: "Por status",
                isDefault: true,
                group: [{ field: "status", dir: "asc", aggregates: [{ field: "valor_total", aggregate: "sum" }] }],
                aggregates: [{ field: "valor_total", aggregate: "sum" }, { field: "qtde_pedidos", aggregate: "count" }]
              }
            ]
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.grid.toolbar = clone(source.crud.grid.toolbar);
        definition.crud.grid.freezeColumns = { enabled: true, fields: [] };
        definition.crud.userLayout.savedSorts = [
          { id: "maior-valor", name: "Maior valor", isDefault: true, sort: [{ field: "valor_total", dir: "desc" }] }
        ];
        definition.crud.userLayout.savedGroups = [
          {
            id: "por-status",
            name: "Por status",
            isDefault: true,
            group: [{ field: "status", dir: "asc", aggregates: [{ field: "valor_total", aggregate: "sum" }] }],
            aggregates: [{ field: "valor_total", aggregate: "sum" }, { field: "qtde_pedidos", aggregate: "count" }]
          }
        ];
      }
    },
    {
      id: "formulario-abas",
      category: "Formulario",
      title: "Formulario com abas",
      summary: "Abre formulario em popup com abas, secoes, campos readonly e ids unicos.",
      initialAction: "openEdit",
      code: {
        crud: {
          form: {
            id: "clienteForm",
            mode: "popup",
            layout: "tabs",
            maximizeForm: false,
            tabs: [
              { id: "geral", title: "Geral" },
              { id: "dados_pj", title: "Dados PJ" },
              { id: "comercial", title: "Comercial" }
            ]
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.form.layout = "tabs";
        definition.crud.form.tabs = clone(source.crud.form.tabs);
        definition.crud.form.steps = [];
      }
    },
    {
      id: "formulario-etapas",
      category: "Formulario",
      title: "Formulario por etapas",
      summary: "Usa etapas com obrigatorios, readonly por grupo e logs por etapa.",
      initialAction: "openEdit",
      code: {
        security: {
          userGroups: ["vendas"]
        },
        crud: {
          form: {
            layout: "steps",
            steps: [
              { id: "identificacao", title: "Identificacao", requiredFields: ["nome", "email", "status", "tipo_pessoa"] },
              { id: "dados_pj", title: "Dados PJ", readonlyGroups: ["vendas"] },
              { id: "comercial", title: "Comercial", readonly: true }
            ]
          }
        }
      },
      apply: function(definition, source) {
        definition.security = { userGroups: ["vendas"] };
        definition.crud.form.layout = "steps";
        definition.crud.form.tabs = [];
        definition.crud.form.steps = clone(source.crud.form.steps);
      }
    },
    {
      id: "formulario-eventos",
      category: "Formulario",
      title: "Eventos e campos dependentes",
      summary: "Evento de UF recarrega cidades e tipo de pessoa esconde/exibe etapa.",
      initialAction: "openEdit",
      code: {
        crud: {
          form: {
            events: [
              {
                source: "uf",
                event: "change",
                endpointId: "loadCidadesByUf",
                request: { map: { uf: "uf" } },
                response: {
                  effects: [
                    { target: "cidade", action: "reloadOptions", optionsFrom: "response.options" }
                  ]
                }
              },
              {
                source: "tipo_pessoa",
                event: "change",
                effects: [
                  { target: "step.dados_pj", action: "visible" }
                ]
              }
            ]
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.form.layout = "steps";
        definition.crud.form.tabs = [];
        definition.crud.form.steps = clone(source.crud.form.steps);
        definition.crud.form.events = clone(source.crud.form.events);
      }
    },
    {
      id: "formulario-situacao",
      category: "Formulario",
      title: "Campo de situacao",
      summary: "Mostra situacao abaixo do appbar do formulario com historico por API.",
      initialAction: "openView",
      code: {
        crud: {
          form: {
            situation: {
              enabled: true,
              field: "status",
              label: "Situacao",
              display: "stepper",
              historyEndpointId: "statusHistory",
              steps: [
                { value: "ATIVO", text: "Ativo" },
                { value: "INATIVO", text: "Inativo" }
              ]
            }
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.form.situation = clone(source.crud.form.situation);
      }
    },
    {
      id: "formulario-acoes-impressao",
      category: "Formulario",
      title: "Formulario com impressoes e outras acoes",
      summary: "Mostra logs, impressao e menu de outras acoes do registro corrente.",
      initialAction: "openView",
      code: {
        crud: {
          form: {
            logs: {
              enabled: true,
              url: "docs/clientes-logs.html?id={id}"
            },
            print: {
              enabled: true,
              options: [
                { format: "excel", source: "api", endpointId: "printClienteExcel" },
                { format: "pdf", source: "api", endpointId: "printClientePdf" },
                { format: "csv", source: "api", endpointId: "printClienteCsv" }
              ]
            },
            otherActions: {
              enabled: true,
              actions: [
                { id: "checkCredit", label: "Analisar credito", endpointId: "checkCredit" },
                { id: "sendWelcome", label: "Enviar boas-vindas", endpointId: "sendWelcome" }
              ]
            }
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.form.logs = clone(source.crud.form.logs);
        definition.crud.form.logs.url = "../../docs/clientes-logs.html?id={id}";
        definition.crud.form.print = clone(source.crud.form.print);
        definition.crud.form.otherActions = clone(source.crud.form.otherActions);
      }
    },
    {
      id: "tema-global",
      category: "Tema",
      title: "Tema global",
      summary: "Aplica tema Kendo e tokens globais do CRUD Engine.",
      code: {
        "crud-engine.config.json": {
          theme: {
            kendoTheme: "kendo/styles/default-urban.css",
            defaultMode: "dark",
            tokens: {
              dark: {
                background: "#101828",
                surface: "#182230",
                title: "#8ec5ff",
                buttonPrimaryBackground: "#2f7ec8"
              }
            }
          }
        }
      },
      applyConfig: function(config) {
        config.theme.defaultMode = "dark";
        config.theme.tokens.dark = Object.assign({}, config.theme.tokens.dark, {
          background: "#101828",
          surface: "#182230",
          title: "#8ec5ff",
          buttonPrimaryBackground: "#2f7ec8",
          buttonPrimaryHoverBackground: "#3b8fdf"
        });
      }
    }
  ];

  function list() {
    return examples.map(function(example) {
      return {
        id: example.id,
        category: example.category,
        title: example.title,
        summary: example.summary,
        page: pagePath + example.id + ".html"
      };
    });
  }

  function get(id) {
    return examples.find(function(example) {
      return example.id === id;
    }) || null;
  }

  function buildDefinition(id) {
    const example = get(id);
    const source = getSourceDefinition();
    const definition = clone(source);
    applyCommonDefaults(definition, source, example);
    if (example && typeof example.apply === "function") {
      example.apply(definition, source);
    }
    return definition;
  }

  function buildConfig(id, options) {
    const example = get(id);
    const config = clone(global.CrudDemoEmbedded && global.CrudDemoEmbedded.config || {});
    if (example && typeof example.applyConfig === "function") {
      example.applyConfig(config);
    }
    if (options && options.assetPrefix && config.theme && config.theme.kendoTheme) {
      config.theme.kendoTheme = prefixAssetPath(config.theme.kendoTheme, options.assetPrefix);
    }
    return config;
  }

  function getCode(id) {
    const example = get(id);
    return JSON.stringify(example && example.code ? example.code : {}, null, 2);
  }

  function applyCommonDefaults(definition, source, example) {
    definition.program.title = example ? example.title : definition.program.title;
    definition.program.version = "";
    definition.program.subtitle = truncate(example ? example.summary : "", 80);
    definition.program.subtitleTooltip = truncate(example ? example.summary : "", 300);
    definition.program.help = { enabled: false };
    definition.program.logs = { enabled: false };

    definition.security = { userGroups: [] };
    definition.crud.filter.openOnLoad = false;
    definition.crud.filter.waitForSubmitOnLoad = true;
    definition.crud.filter.maximizeFilter = false;
    definition.crud.filter.showAppliedFilters = true;
    definition.crud.filter.tabs = { enabled: false, items: [] };

    definition.crud.grid.ai = { enabled: false };
    definition.crud.grid.mobile = { enabled: false };
    definition.crud.grid.bulkActions = { enabled: false, actions: [] };
    definition.crud.grid.print = { enabled: false, options: [] };
    definition.crud.grid.freezeColumns = { enabled: false, fields: [] };
    definition.crud.grid.toolbar = clone(source.crud.grid.toolbar).filter(function(item) {
      return ["create", "filters", "refresh"].indexOf(item.id) !== -1;
    });

    definition.crud.form.layout = "tabs";
    definition.crud.form.tabs = clone(source.crud.form.tabs);
    definition.crud.form.steps = [];
    definition.crud.form.events = [];
    definition.crud.form.logs = { enabled: false };
    definition.crud.form.print = { enabled: false, options: [] };
    definition.crud.form.otherActions = { enabled: false, actions: [] };
    definition.crud.form.situation = { enabled: false };

    resetUserLayout(definition);
  }

  function resetUserLayout(definition) {
    definition.crud.userLayout = Object.assign({}, definition.crud.userLayout || {}, {
      activeLayoutId: null,
      activeSortId: null,
      activeGroupId: null,
      activeFilterId: null,
      savedLayouts: [],
      savedSorts: [],
      savedGroups: [],
      savedFilters: [],
      grid: {
        columns: {
          order: [],
          hidden: [],
          widths: {},
          frozen: [],
          added: []
        },
        sort: [],
        filter: null,
        group: [],
        groupAggregates: []
      }
    });
  }

  function getSourceDefinition() {
    if (!global.CrudDemoEmbedded || !global.CrudDemoEmbedded.clientesDefinition) {
      throw new Error("Definicao base de clientes nao carregada.");
    }
    return global.CrudDemoEmbedded.clientesDefinition;
  }

  function prefixAssetPath(path, prefix) {
    const value = String(path || "");
    if (/^(https?:)?\/\//i.test(value) || value.indexOf(prefix) === 0) {
      return value;
    }
    return prefix + value.replace(/^\/+/, "");
  }

  function truncate(value, maxLength) {
    const text = String(value || "");
    return text.length > maxLength ? text.slice(0, maxLength) : text;
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value || null));
  }

  global.CrudExamplesCatalog = {
    list,
    get,
    buildDefinition,
    buildConfig,
    getCode
  };
})(window);
