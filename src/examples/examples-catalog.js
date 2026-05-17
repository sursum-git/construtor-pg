(function(global) {
  "use strict";

  const pagePath = "examples/pages/";

  const examples = [
    {
      id: "home-engine",
      engine: "home",
      category: "Home",
      title: "Pagina inicial por JSON",
      summary: "Monta menu lateral, appbar global, central de notificacoes, jobs, suporte com contexto do programa corrente e abertura por iframe, CrudEngine, ProcessEngine, programa custom ou HTML sanitizado.",
      code: {
        pageType: "home",
        app: {
          logo: {
            url: "public/assets/company-logo.svg",
            alt: "Construtor PG",
            showTitle: true,
            showSubtitle: true
          }
        },
        currentUser: {
          name: "Tasio Silva",
          email: "tasio@example.com",
          initials: "TS",
          favoritePrograms: ["clientes-crud", "exemplos"]
        },
        currentSubscriber: {
          name: "Empresa Demonstracao",
          document: "00.000.000/0001-00",
          label: "Assinante"
        },
        availableSubscribers: [
          { id: "principal", name: "Principal", principal: true },
          { id: "assinante-demo", name: "Empresa Demonstracao", document: "00.000.000/0001-00" },
          { id: "assinante-filial", name: "Empresa Filial", document: "11.111.111/0001-11" }
        ],
        layout: {
          initialProgramId: "painel",
          sidebar: {
            component: "kendoTreeView"
          },
          appbar: {
            showSidebarToggle: true,
            showFavoriteToggle: true,
            showCurrentSubscriber: true,
            subscriberSwitch: {
              enabled: true,
              programId: "troca-assinante",
              endpoints: {
                change: { url: "/api/home/subscribers/change", method: "POST" }
              }
            },
            showUserMenu: true,
            chat: {
              enabled: false,
              endpoints: {
                contacts: "/api/home/chat/contacts",
                history: { url: "/api/home/chat/history", method: "POST" },
                send: "/api/home/chat/send"
              }
            },
            support: {
              enabled: true,
              icon: "headset",
              fallbackRequest: {
                defaultSectorId: "suporte"
              },
              sectors: [
                { id: "suporte", name: "Suporte" },
                { id: "financeiro", name: "Financeiro" }
              ],
              endpoints: {
                onlineUsers: "/api/home/support/online-users",
                history: "/api/home/support/history",
                send: "/api/home/support/send",
                createRequest: "/api/home/support/requests"
              }
            },
            aiChat: {
              enabled: true,
              icon: "sparkles",
              endpoints: {
                history: "/api/home/ai-chat/history",
                send: "/api/home/ai-chat/send"
              }
            },
            notifications: {
              enabled: true,
              title: "Central de notificacoes",
              endpoints: {
                list: { endpointId: "home.notifications.list", method: "POST" },
                ack: { endpointId: "home.notifications.ack", method: "POST" }
              }
            },
            alerts: {
              enabled: true,
              endpoints: {
                list: "/api/home/alerts"
              }
            },
            requests: {
              enabled: true,
              endpoints: {
                list: "/api/home/requests"
              }
            },
            jobs: {
              enabled: true,
              programId: "meus-jobs",
              endpoints: {
                list: "/api/home/jobs"
              }
            },
            userMenu: {
              items: [
                { id: "profile", label: "Meus dados", action: "profile" },
                { id: "preferences", label: "Preferencias", action: "preferences" },
                { id: "logout", label: "Sair", action: "logout" }
              ]
            }
          }
        },
        navigation: {
          initialModuleId: "",
          modules: [
            { id: "operacional", title: "Operacional" },
            { id: "ferramentas", title: "Ferramentas" }
          ],
          groups: [
            {
              id: "principal",
              title: "Principal",
              moduleId: "operacional",
              items: [
                { programId: "painel", title: "Painel" },
                { programId: "clientes-crud", title: "Clientes", favorite: true },
                { programId: "processamento-clientes", title: "Processamento" }
              ]
            },
            {
              id: "apoio",
              title: "Apoio",
              moduleId: "ferramentas",
              items: [
                { programId: "clientes-iframe", title: "Clientes via iframe" },
                { programId: "programa-manual", title: "Programa manual" },
                { programId: "exemplos", title: "Exemplos", favorite: true },
                { programId: "tema", title: "Editor de tema" }
              ]
            }
          ]
        },
        programs: [
          { id: "painel", type: "html", title: "Painel inicial" },
          { id: "clientes-crud", type: "crud", title: "Clientes", openUrl: "index.html", definitionUrl: "examples/clientes.crud.json" },
          { id: "processamento-clientes", type: "process", title: "Processamento de Clientes", definitionUrl: "examples/processamento-relatorio.process.json" },
          { id: "clientes-iframe", type: "iframe", title: "Clientes via iframe", version: "1.0.0", url: "index.html" },
          {
            id: "programa-manual",
            type: "custom",
            title: "Programa manual",
            definition: {
              schemaVersion: "1.0",
              pageType: "custom",
              screenId: "manual.demo",
              program: {
                id: "manual.demo",
                title: "Programa manual demo",
                version: "1.0.0",
                subtitle: "Tela fora do padrao CRUD"
              },
              custom: {
                mode: "iframe",
                entryUrl: "production/custom/programa-manual-demo.html",
                frameTitle: "Programa manual demo"
              }
            }
          },
          { id: "troca-assinante", type: "html", title: "Troca de assinante", html: "<section><h2>Troca de assinante</h2></section>" },
          { id: "meus-jobs", type: "crud", title: "Meus Jobs", screenId: "runtime.jobs.mine" }
        ]
      }
    },
    {
      id: "processamento-parametros",
      engine: "process",
      category: "Processamento",
      title: "Processamento por parametros",
      summary: "Renderiza parametros declarativos, processa via endpoint, aguarda status do job e mostra retorno em mensagem, grid, relatorio ou job em segundo plano.",
      initialAction: null,
      code: {
        pageType: "process",
        process: {
          parameters: {
            fields: [
              {
                id: "resultado",
                field: "resultado",
                label: "Retorno esperado",
                type: "enum",
                required: true,
                defaultValue: "grid"
              }
            ]
          },
          wait: {
            mode: "auto",
            pollIntervalSeconds: 1
          },
          result: {
            type: "grid",
            openReportInNewTab: false
          }
        },
        dataSource: {
          api: {
            process: { url: "/api/processamento/clientes", endpointId: "process", method: "POST" },
            status: { url: "/api/processamento/clientes/status", endpointId: "status", method: "POST" }
          }
        }
      }
    },
    {
      id: "program-builder-technical-properties",
      engine: "program-builder",
      category: "Construtor",
      title: "Program Builder - Propriedades tecnicas",
      summary: "Exemplo local do construtor com mock leve para validar icones de propriedades tecnicas em entidade, programa, API/Odoo e inspetor lateral.",
      page: pagePath + "program-builder-technical-properties.html"
    },
    {
      id: "program-builder-governance",
      engine: "program-builder",
      category: "Construtor",
      title: "Program Builder - Governanca",
      summary: "Exemplo local do fluxo governado com solicitacao, grant, bundle de testes, aprovacao final e preview visual de rebase de overlay.",
      page: pagePath + "program-builder-governance.html"
    },
    {
      id: "admin-program-governance",
      engine: "program-builder",
      category: "Administracao",
      title: "Governanca de programas",
      summary: "Tela administrativa dedicada para requests, grants, bundles, aprovacoes, retencao e rebase de overlays sem depender so do CRUD generico.",
      page: pagePath + "admin-program-governance.html"
    },
    {
      id: "admin-program-grants",
      engine: "program-builder",
      category: "Administracao",
      title: "Grants de programas",
      summary: "Entrada focada da governanca para liberar, congelar, reativar e revogar grants com menos ruído operacional.",
      page: pagePath + "admin-program-grants.html"
    },
    {
      id: "admin-program-approvals",
      engine: "program-builder",
      category: "Administracao",
      title: "Aprovacoes de publicacao",
      summary: "Entrada focada da governanca para bundles aprovados e aprovacao final de publicacao governada.",
      page: pagePath + "admin-program-approvals.html"
    },
    {
      id: "admin-program-retention",
      engine: "program-builder",
      category: "Administracao",
      title: "Retencao da governanca",
      summary: "Entrada focada da governanca para revisar e ajustar a politica de retencao sem ruido das outras operacoes.",
      page: pagePath + "admin-program-retention.html"
    },
    {
      id: "admin-program-retention-history",
      engine: "program-builder",
      category: "Administracao",
      title: "Historico da retencao",
      summary: "Entrada focada para comparar preview e aplicacao da retencao por grupo de execucao.",
      page: pagePath + "admin-program-retention-history.html"
    },
    {
      id: "admin-program-audit",
      engine: "program-builder",
      category: "Administracao",
      title: "Auditoria da governanca",
      summary: "Entrada focada da governanca para revisar timeline, sinais operacionais e historico detalhado por programa.",
      page: pagePath + "admin-program-audit.html"
    },
    {
      id: "admin-program-operations",
      engine: "program-builder",
      category: "Administracao",
      title: "Operacoes da governanca",
      summary: "Entrada focada para monitoramento, integridade e preview operacional da retencao em uma unica superficie.",
      page: pagePath + "admin-program-operations.html"
    },
    {
      id: "admin-program-overlays",
      engine: "program-builder",
      category: "Administracao",
      title: "Overlays de programas",
      summary: "Entrada focada da governanca para listar overlays, ver congelamento e abrir o preview de rebase do assinante.",
      page: pagePath + "admin-program-overlays.html"
    },
    {
      id: "admin-program-overlay-versions",
      engine: "program-builder",
      category: "Administracao",
      title: "Versoes de overlay",
      summary: "Entrada focada da governanca para carregar historico, comparar versoes e publicar uma versao do overlay.",
      page: pagePath + "admin-program-overlay-versions.html"
    },
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
      id: "consulta-api-readonly",
      category: "Consulta",
      title: "Consulta externa por API",
      summary: "Mostra um CRUD somente leitura usando lista externa em JSON e detalhe opcional por API.",
      code: {},
      apply: function(definition) {
        definition.screenId = "consultas.api.produtos";
        definition.program.id = "api-produtos";
        definition.program.title = "Produtos externos";
        definition.program.subtitle = "Consulta por API externa";
        definition.program.subtitleTooltip = "Exemplo de entityType=api em modo somente leitura.";
        definition.program.permission = "consultas.api.produtos.read";
        definition.program.entity = "produtos_api";
        definition.permissions.read = "consultas.api.produtos.read";
        definition.permissions.create = false;
        definition.permissions.edit = false;
        definition.permissions.delete = false;
        definition.runtime.entityCode = "produtos_api";
        definition.runtime.mode = "readonly-api";
        definition.runtime.lock = { enabled: false, modes: [] };
        definition.runtime.messages = { enabled: false, pollIntervalSeconds: 30, events: { enabled: false } };
        definition.dataSource.api = {
          read: { url: "/api/mock/externo/produtos", method: "POST" },
          get: { url: "/api/mock/externo/produtos/{id}", method: "GET" },
          saveLayout: { url: "/api/crud-layout/cadastros/clientes", method: "POST" },
          restoreLayout: { url: "/api/crud-layout/cadastros/clientes", method: "DELETE" },
          saveSort: { url: "/api/crud-layout/cadastros/clientes/sorts", method: "POST" },
          deleteSort: { url: "/api/crud-layout/cadastros/clientes/sorts/{id}", method: "DELETE" },
          saveGroup: { url: "/api/crud-layout/cadastros/clientes/groups", method: "POST" },
          deleteGroup: { url: "/api/crud-layout/cadastros/clientes/groups/{id}", method: "DELETE" },
          saveFilter: { url: "/api/crud-layout/cadastros/clientes/filters", method: "POST" },
          deleteFilter: { url: "/api/crud-layout/cadastros/clientes/filters/{id}", method: "DELETE" },
          saveMobileTemplate: { url: "/api/crud-layout/cadastros/clientes/mobile-templates", method: "POST" },
          deleteMobileTemplate: { url: "/api/crud-layout/cadastros/clientes/mobile-templates/{id}", method: "DELETE" }
        };
        definition.dataModel.primaryKey = "id";
        definition.dataModel.fields = {
          id: { label: "ID", type: "integer", readonly: true },
          nome: { label: "Nome", type: "text", readonly: true },
          status: { label: "Status", type: "text", readonly: true },
          categoria: { label: "Categoria", type: "text", readonly: true },
          preco: { label: "Preco", type: "number", readonly: true },
          atualizado_em: { label: "Atualizado em", type: "datetime", readonly: true }
        };
        definition.crud.filter.fields = [];
        definition.crud.grid.filterable = false;
        definition.crud.grid.toolbar = [
          { id: "refresh", label: "Atualizar", icon: "arrow-rotate-cw", action: "refresh", permission: "read" }
        ];
        definition.crud.grid.columns = [
          { field: "id", title: "ID", width: 90 },
          { field: "nome", title: "Nome", width: 220 },
          { field: "categoria", title: "Categoria", width: 160 },
          { field: "status", title: "Status", width: 120 },
          { field: "preco", title: "Preco", width: 140, format: "number" },
          { field: "atualizado_em", title: "Atualizado em", width: 180 }
        ];
        definition.crud.grid.rowActions = [
          { id: "view", label: "Visualizar", action: "view", icon: "eye", permission: "read" }
        ];
        definition.crud.form.tabs = [
          {
            id: "geral",
            title: "Geral",
            sections: [
              {
                id: "principal",
                title: "Principal",
                columns: 2,
                fields: [
                  { field: "id", readonly: true },
                  { field: "nome", readonly: true, colSpan: 2 },
                  { field: "categoria", readonly: true },
                  { field: "status", readonly: true },
                  { field: "preco", readonly: true },
                  { field: "atualizado_em", readonly: true }
                ]
              }
            ]
          }
        ];
        definition.crud.form.fields = [
          { field: "id", readonly: true },
          { field: "nome", readonly: true },
          { field: "categoria", readonly: true },
          { field: "status", readonly: true },
          { field: "preco", readonly: true },
          { field: "atualizado_em", readonly: true }
        ];
        definition.crud.form.buttons = [];
        definition.crud.form.otherActions = { enabled: false, actions: [] };
        definition.crud.form.logs = { enabled: false };
        definition.crud.form.print = { enabled: false, options: [] };
      }
    },
    {
      id: "consulta-api-crud",
      category: "Consulta",
      title: "CRUD externo por API",
      summary: "Mostra um CRUD basico de API JSON previsivel com inclusao, alteracao e exclusao via mock local.",
      code: {},
      apply: function(definition) {
        definition.screenId = "consultas.api.produtos-crud";
        definition.program.id = "api-produtos-crud";
        definition.program.title = "Produtos externos CRUD";
        definition.program.subtitle = "CRUD por API externa";
        definition.program.subtitleTooltip = "Exemplo basico de entityType=api em modo CRUD.";
        definition.program.permission = "consultas.api.produtos-crud.read";
        definition.program.entity = "produtos_api_crud";
        definition.permissions.read = "consultas.api.produtos-crud.read";
        definition.permissions.create = "consultas.api.produtos-crud.create";
        definition.permissions.edit = "consultas.api.produtos-crud.edit";
        definition.permissions.delete = "consultas.api.produtos-crud.delete";
        definition.runtime.entityCode = "produtos_api_crud";
        definition.runtime.mode = "api-crud";
        definition.runtime.lock = { enabled: false, modes: [] };
        definition.runtime.messages = { enabled: false, pollIntervalSeconds: 30, events: { enabled: false } };
        definition.dataSource.api = {
          read: { url: "/api/mock/externo/produtos-crud", method: "POST" },
          get: { url: "/api/mock/externo/produtos-crud/{id}", method: "GET" },
          create: { url: "/api/mock/externo/produtos-crud", method: "POST" },
          update: { url: "/api/mock/externo/produtos-crud/{id}", method: "PUT" },
          delete: { url: "/api/mock/externo/produtos-crud/{id}", method: "DELETE" },
          saveLayout: { url: "/api/crud-layout/cadastros/clientes", method: "POST" },
          restoreLayout: { url: "/api/crud-layout/cadastros/clientes", method: "DELETE" },
          saveSort: { url: "/api/crud-layout/cadastros/clientes/sorts", method: "POST" },
          deleteSort: { url: "/api/crud-layout/cadastros/clientes/sorts/{id}", method: "DELETE" },
          saveGroup: { url: "/api/crud-layout/cadastros/clientes/groups", method: "POST" },
          deleteGroup: { url: "/api/crud-layout/cadastros/clientes/groups/{id}", method: "DELETE" },
          saveFilter: { url: "/api/crud-layout/cadastros/clientes/filters", method: "POST" },
          deleteFilter: { url: "/api/crud-layout/cadastros/clientes/filters/{id}", method: "DELETE" },
          saveMobileTemplate: { url: "/api/crud-layout/cadastros/clientes/mobile-templates", method: "POST" },
          deleteMobileTemplate: { url: "/api/crud-layout/cadastros/clientes/mobile-templates/{id}", method: "DELETE" }
        };
        definition.dataModel.primaryKey = "id";
        definition.dataModel.fields = {
          id: { label: "ID", type: "integer", readonly: true, editable: false },
          nome: { label: "Nome", type: "text", nullable: false },
          ativo: { label: "Ativo", type: "boolean", nullable: true }
        };
        definition.crud.filter.fields = [
          { id: "nome", field: "nome", label: "Nome", type: "text", operator: "contains" },
          { id: "ativo", field: "ativo", label: "Ativo", type: "boolean", operator: "eq" }
        ];
        definition.crud.grid.filterable = false;
        definition.crud.grid.toolbar = [
          { id: "create", label: "Incluir", icon: "plus", action: "create", permission: "create" },
          { id: "refresh", label: "Atualizar", icon: "arrow-rotate-cw", action: "refresh", permission: "read" }
        ];
        definition.crud.grid.columns = [
          { field: "id", title: "ID", width: 120 },
          { field: "nome", title: "Nome", width: 240 },
          { field: "ativo", title: "Ativo", width: 140 }
        ];
        definition.crud.grid.rowActions = [
          { id: "view", label: "Visualizar", action: "view", icon: "eye", permission: "read" },
          { id: "edit", label: "Alterar", action: "edit", icon: "pencil", permission: "edit" },
          { id: "delete", label: "Excluir", action: "delete", icon: "trash", permission: "delete" }
        ];
        definition.crud.form.title = {
          create: "Incluir produto externo",
          view: "Detalhe do produto externo",
          edit: "Alterar produto externo",
          delete: "Excluir produto externo"
        };
        definition.crud.form.tabs = [
          {
            id: "geral",
            title: "Geral",
            sections: [
              {
                id: "principal",
                title: "Principal",
                columns: 2,
                fields: [
                  { field: "id", readonly: true },
                  { field: "nome", colSpan: 2 },
                  { field: "ativo" }
                ]
              }
            ]
          }
        ];
        definition.crud.form.fields = [
          { field: "id", readonly: true },
          { field: "nome" },
          { field: "ativo" }
        ];
        definition.crud.form.buttons = [];
        definition.crud.form.otherActions = { enabled: false, actions: [] };
        definition.crud.form.logs = { enabled: false };
        definition.crud.form.print = { enabled: false, options: [] };
        definition.crud.userLayout.storageKey = "api-crud-layout";
      }
    },
    {
      id: "manual-programas",
      category: "Documentacao",
      title: "Manual por programa",
      summary: "Pagina com manual operacional e funcional dos principais programas web, com indice navegavel por programa e screenId.",
      page: pagePath + "manual-programas.html",
      code: {}
    },
      {
        id: "import-export-mappings",
        category: "Integracao",
        title: "Mapeamentos de importacao e exportacao",
        summary: "Documenta o contrato da engine de mapeamentos para API, tabela, CSV, XML e TXT, incluindo leiaute posicional, por separador e arvore hierarquica com TreeView para registros pai, filhos e totalizadores.",
        page: pagePath + "import-export-mappings.html",
        code: {}
      },
      {
        id: "admin-integracoes",
        category: "Backend",
        title: "Admin de integracoes",
        summary: "Tela administrativa real para cadastrar, validar e executar mapeamentos de importacao/exportacao.",
        page: "production/admin/import-export-mappings.html",
        code: {
          screenId: "admin.integracoes",
          productionUrl: "production/app.html?screenId=admin.integracoes"
        }
      },
      {
        id: "admin-integracoes-demo",
        category: "Integracao",
        title: "Admin de integracoes - demo local",
        summary: "Superficie local para validar a UI completa de integracoes, com TreeView para TXT/XML, preview lado a lado e historico de execucao.",
        page: pagePath + "import-export-admin-demo.html",
        code: {}
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
      id: "formulario-aviso-concorrencia",
      category: "Formulario",
      title: "Aviso de concorrencia",
      summary: "Mostra aviso Kendo antes de Alterar ou Excluir, inclusive quando a acao vier direto do grid, antes do semaforo real.",
      initialAction: "openView",
      code: {
        crud: {
          form: {
            concurrencyWarning: {
              enabled: true,
              actions: ["edit", "delete"],
              title: "Aviso de concorrencia",
              editMessage: "Este cliente pode estar em uso por outro usuario. Ao continuar, o sistema validara se o registro ainda pode ser alterado.",
              deleteMessage: "Este cliente pode estar em uso por outro usuario. Ao continuar, o sistema validara se o registro ainda pode ser excluido.",
              confirmText: "Continuar",
              cancelText: "Cancelar"
            }
          }
        }
      },
      apply: function(definition, source) {
        definition.crud.form.concurrencyWarning = clone(source.crud.form.concurrencyWarning);
      }
    },
    {
      id: "formulario-pagina-backend",
      category: "Formulario",
      title: "Pagina backend com valores",
      summary: "Botao do formulario abre uma pagina do backend enviando valores atuais do formulario por querystring ou POST.",
      initialAction: "openView",
      code: {
        crud: {
          form: {
            buttons: [
              {
                id: "pagina-backend",
                label: "Pagina backend",
                action: "paginaBackend",
                icon: "window",
                placement: "header",
                url: "../../docs/clientes-logs.html",
                target: "window",
                method: "GET",
                formValues: {
                  enabled: true,
                  fields: ["id", "nome", "email", "status"],
                  transport: "query"
                },
                visibleIn: ["view", "edit"]
              }
            ]
          }
        }
      },
      apply: function(definition) {
        definition.crud.form.buttons = global.CrudUtils.ensureArray(definition.crud.form.buttons).concat([
          {
            id: "pagina-backend",
            label: "Pagina backend",
            action: "paginaBackend",
            icon: "window",
            placement: "header",
            url: "../../docs/clientes-logs.html",
            target: "window",
            method: "GET",
            formValues: {
              enabled: true,
              fields: ["id", "nome", "email", "status"],
              transport: "query"
            },
            visibleIn: ["view", "edit"]
          }
        ]);
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
      id: "formulario-consistencia-backend",
      category: "Formulario",
      title: "Consistencia pelo backend",
      summary: "Mostra como uma regra do backend bloqueia a gravacao, marca o campo e aplica efeitos seguros no formulario.",
      initialAction: "openEdit",
      code: {
        backendResponse: {
          error: {
            code: "CLIENTE_OBSERVACAO_REQUIRED",
            message: "Existem inconsistencias no formulario.",
            severity: "error"
          },
          validation: {
            status: "blocked",
            title: "Inconsistencias encontradas",
            titleKey: "validation.title.inconsistencies",
            messages: [
              {
                field: "observacao",
                type: "error",
                message: "Observacao e obrigatoria para cliente inativo.",
                messageKey: "validation.message.field_required",
                messageParams: {
                  field: "observacao",
                  fieldCode: "observacao",
                  fieldLabel: "Observacao"
                }
              }
            ],
            closeTextKey: "literal.button.close"
          },
          effects: [
            {
              action: "required",
              target: "observacao",
              value: true
            }
          ]
        }
      },
      apply: function(definition) {
        definition.crud.form.events = global.CrudUtils.ensureArray(definition.crud.form.events);
        definition.crud.form.events.push({
          id: "status-consistencia-backend",
          source: "status",
          trigger: "change",
          endpointId: "validateStatusCliente",
          request: {
            includeValues: true
          }
        });
      }
    },
    {
      id: "formulario-codificacao-customizada",
      category: "Formulario",
      title: "Codificacao customizada",
      summary: "Mostra campo com codificacao fechada, assistente de propriedades e geracao segura no backend.",
      page: pagePath + "formulario-codificacao-customizada.html",
      initialAction: "openCreate",
      code: {
        dataModel: {
          fields: {
            codigo_customizado: {
              label: "Codigo customizado",
              type: "string",
              editor: "customCode",
              nullable: true,
              customCode: {
                mode: "static_method",
                prefix: "PDM-",
                sequenceEnabled: true,
                sequenceScope: "day",
                sequencePadding: 4,
                staticClass: "App\\Runtime\\CustomCode\\ProdutoPdmCodeGenerator",
                staticMethod: "generate",
                assistantScreenId: "assistente.codificacao.produto-pdm",
                promptTitle: "Propriedades do codigo PDM",
                promptFields: [
                  {
                    name: "familia",
                    label: "Familia",
                    type: "dropdown",
                    required: true,
                    options: [
                      { value: "ELE", text: "Eletrica" },
                      { value: "HID", text: "Hidraulica" },
                      { value: "EST", text: "Estrutural" }
                    ]
                  },
                  {
                    name: "grupo",
                    label: "Grupo",
                    type: "string",
                    required: true
                  },
                  {
                    name: "linha",
                    label: "Linha",
                    type: "string",
                    required: true
                  }
                ]
              }
            }
          }
        }
      },
      apply: function(definition) {
        definition.dataModel.fields.codigo_customizado = {
          label: "Codigo customizado",
          type: "string",
          editor: "customCode",
          nullable: true,
          customCode: {
            mode: "static_method",
            prefix: "PDM-",
            sequenceEnabled: true,
            sequenceScope: "day",
            sequencePadding: 4,
            staticClass: "App\\Runtime\\CustomCode\\ProdutoPdmCodeGenerator",
            staticMethod: "generate",
            promptTitle: "Propriedades do codigo PDM",
            promptFields: [
              {
                name: "familia",
                label: "Familia",
                type: "dropdown",
                required: true,
                options: [
                  { value: "ELE", text: "Eletrica" },
                  { value: "HID", text: "Hidraulica" },
                  { value: "EST", text: "Estrutural" }
                ]
              },
              {
                name: "grupo",
                label: "Grupo",
                type: "string",
                required: true
              },
              {
                name: "linha",
                label: "Linha",
                type: "string",
                required: true
              }
            ]
          }
        };
        definition.crud.form.fields = global.CrudUtils.ensureArray(definition.crud.form.fields);
        definition.crud.form.fields.splice(1, 0, { field: "codigo_customizado" });
        if (definition.crud.form.tabs && definition.crud.form.tabs[0] && Array.isArray(definition.crud.form.tabs[0].sections)) {
          const section = definition.crud.form.tabs[0].sections[0];
          if (section && Array.isArray(section.fields)) {
            section.fields.splice(0, 0, { field: "codigo_customizado", colSpan: 2 });
          }
        }
        definition.crud.grid.columns = global.CrudUtils.ensureArray(definition.crud.grid.columns);
        definition.crud.grid.columns.splice(1, 0, {
          field: "codigo_customizado",
          title: "Codigo",
          width: 190
        });
      }
    },
    {
      id: "formulario-fila-assincrona",
      category: "Backend",
      title: "Fila de trabalho assincrona",
      summary: "Mostra o contrato opcional retornado quando uma gravacao agenda um job, como envio de confirmacao de e-mail.",
      initialAction: "openCreate",
      code: {
        backendResponse: {
          _runtime: {
            asyncJobs: [
              {
                type: "cliente.email_confirmation",
                status: "queued"
              }
            ]
          },
          effects: [
            {
              action: "showMessage",
              type: "info",
              message: "E-mail de confirmacao agendado."
            }
          ]
        }
      },
      apply: function(definition) {
        definition.crud.form.behavior = Object.assign({}, definition.crud.form.behavior || {}, {
          closeOnSave: false
        });
      }
    },
    {
      id: "formulario-acao-job-manual",
      category: "Backend",
      title: "Acao manual por job",
      summary: "Mostra um botao de formulario que chama endpointId fechado e o backend agenda um job, como envio de WhatsApp.",
      initialAction: "openView",
      code: {
        formAction: {
          id: "sendWhatsapp",
          label: "Enviar WhatsApp",
          endpointId: "sendWhatsapp",
          successMessage: "WhatsApp agendado."
        },
        runtimeEndpoint: {
          handler: "runtime.job.enqueue",
          config: {
            entityCode: "cliente",
            programId: "clientes-crud",
            jobs: [
              {
                type: "cliente.whatsapp_welcome",
                mode: "async"
              }
            ]
          }
        }
      },
      apply: function(definition) {
        definition.dataSource.api.sendWhatsapp = {
          endpointId: "sendWhatsapp",
          method: "POST"
        };
        definition.crud.form.otherActions = definition.crud.form.otherActions || {
          enabled: true,
          actions: []
        };
        definition.crud.form.otherActions.enabled = true;
        definition.crud.form.otherActions.actions = global.CrudUtils.ensureArray(definition.crud.form.otherActions.actions);
        if (!definition.crud.form.otherActions.actions.some(function(action) { return action.id === "sendWhatsapp"; })) {
          definition.crud.form.otherActions.actions.push({
            id: "sendWhatsapp",
            label: "Enviar WhatsApp",
            icon: "comment",
            endpointId: "sendWhatsapp",
            permission: "edit",
            visibleIn: ["view", "edit"],
            confirm: {
              message: "Deseja enviar WhatsApp para este cliente?"
            },
            successMessage: "WhatsApp agendado."
          });
        }
      }
    },
    {
      id: "consulta-jobs-assincronos",
      category: "Backend",
      title: "Consulta de jobs assincronos",
      summary: "Tela de consulta para acompanhar jobs executados pela fila, tentativas, payload, resultado e erro.",
      loadByScreenId: true,
      screenId: "admin.jobs",
      code: {
        screenId: "admin.jobs",
        productionUrl: "production/app.html?screenId=admin.jobs"
      }
    },
    {
      id: "admin-parametros",
      category: "Backend",
      title: "Admin de parametros",
      summary: "Tela administrativa para manter parametros, tipos e valor padrao pelo CRUD runtime.",
      page: pagePath + "admin-parametros.html",
      loadByScreenId: true,
      screenId: "admin.parametros",
      code: {
        screenId: "admin.parametros",
        relatedScreens: ["admin.parametro-valores", "admin.listas-opcoes", "admin.opcoes"],
        productionUrl: "production/app.html?screenId=admin.parametros"
      }
    },
    {
      id: "admin-parametro-valores",
      category: "Backend",
      title: "Admin de valores de parametro",
      summary: "Tela administrativa para manter valores vigentes por periodo e estabelecimento.",
      page: pagePath + "admin-parametro-valores.html",
      loadByScreenId: true,
      screenId: "admin.parametro-valores",
      code: {
        screenId: "admin.parametro-valores",
        relatedScreens: ["admin.parametros"],
        productionUrl: "production/app.html?screenId=admin.parametro-valores"
      }
    },
    {
      id: "admin-literais",
      category: "Backend",
      title: "Admin de literais e traducoes",
      summary: "Tela administrativa para manter os textos por chave e locale usados pelo frontend.",
      page: pagePath + "admin-literais.html",
      loadByScreenId: true,
      screenId: "admin.literais",
      code: {
        screenId: "admin.literais",
        relatedScreens: ["admin.parametros"],
        productionUrl: "production/app.html?screenId=admin.literais"
      }
    },
    {
      id: "admin-integridade",
      category: "Backend",
      title: "Admin de integridade estrutural",
      summary: "Tela administrativa para monitorar assinaturas estruturais e reassinatura controlada.",
      page: pagePath + "admin-integridade.html",
      loadByScreenId: true,
      screenId: "admin.integridade",
      code: {
        screenId: "admin.integridade",
        relatedScreens: ["admin.programa-grants", "admin.programa-aprovacoes"],
        productionUrl: "production/app.html?screenId=admin.integridade"
      }
    },
    {
      id: "admin-listas-opcoes",
      category: "Backend",
      title: "Admin de listas de opcoes",
      summary: "Tela administrativa para manter listas fechadas utilizadas por parametros.",
      page: pagePath + "admin-listas-opcoes.html",
      loadByScreenId: true,
      screenId: "admin.listas-opcoes",
      code: {
        screenId: "admin.listas-opcoes",
        relatedScreens: ["admin.parametros", "admin.opcoes"],
        productionUrl: "production/app.html?screenId=admin.listas-opcoes"
      }
    },
    {
      id: "admin-opcoes",
      category: "Backend",
      title: "Admin de opcoes",
      summary: "Tela administrativa para manter opcoes de listas e metadados auxiliares.",
      page: pagePath + "admin-opcoes.html",
      loadByScreenId: true,
      screenId: "admin.opcoes",
      code: {
        screenId: "admin.opcoes",
        relatedScreens: ["admin.listas-opcoes", "admin.parametros"],
        productionUrl: "production/app.html?screenId=admin.opcoes"
      }
    },
    {
      id: "admin-permissoes",
      category: "Backend",
      title: "Admin de permissoes",
      summary: "Tela administrativa para gerenciar grupos e permissoes nos usuarios do sistema.",
      page: pagePath + "admin-permissoes.html",
      loadByScreenId: true,
      screenId: "admin.permissoes",
      code: {
        screenId: "admin.permissoes",
        relatedScreens: ["admin.usuario-assinantes", "admin.usuarios"],
        productionUrl: "production/app.html?screenId=admin.permissoes",
        description: "Tela dedicada para controle de permissoes e grupos por usuario."
      }
    },
    {
      id: "admin-usuarios",
      category: "Backend",
      title: "Admin de usuarios",
      summary: "Tela administrativa para incluir e editar usuarios do sistema, grupos e permissoes.",
      page: pagePath + "admin-usuarios.html",
      loadByScreenId: true,
      screenId: "admin.usuarios",
      code: {
        screenId: "admin.usuarios",
        relatedScreens: ["admin.usuario-assinantes"],
        productionUrl: "production/app.html?screenId=admin.usuarios"
      }
    },
    {
      id: "admin-usuario-assinantes",
      category: "Backend",
      title: "Admin de usuarios por assinante",
      summary: "Tela administrativa para mapear usuarios por assinante e sobrescritas de permissao por contexto.",
      page: pagePath + "admin-usuario-assinantes.html",
      loadByScreenId: true,
      screenId: "admin.usuario-assinantes",
      code: {
        screenId: "admin.usuario-assinantes",
        relatedScreens: ["admin.usuarios"],
        productionUrl: "production/app.html?screenId=admin.usuario-assinantes"
      }
    },
    {
      id: "admin-sessoes",
      category: "Backend",
      title: "Admin de sessoes",
      summary: "Tela administrativa para consultar sessoes, dispositivo, permissao e derrubar uma sessao.",
      page: pagePath + "admin-sessoes.html",
      loadByScreenId: true,
      screenId: "admin.sessoes",
      code: {
        screenId: "admin.sessoes",
        action: "runtime.admin.forceLogout",
        productionUrl: "production/app.html?screenId=admin.sessoes"
      }
    },
    {
      id: "admin-transacoes",
      category: "Backend",
      title: "Admin de transacoes",
      summary: "Tela administrativa para consultar transacoes runtime e relacionar com logs.",
      page: pagePath + "admin-transacoes.html",
      loadByScreenId: true,
      screenId: "admin.transacoes",
      code: {
        screenId: "admin.transacoes",
        relatedScreens: ["admin.logs-transacoes"],
        productionUrl: "production/app.html?screenId=admin.transacoes"
      }
    },
    {
      id: "admin-logs-transacoes",
      category: "Backend",
      title: "Admin de logs de transacoes",
      summary: "Tela administrativa para consultar eventos, before, after, diff e metadata das transacoes.",
      page: pagePath + "admin-logs-transacoes.html",
      loadByScreenId: true,
      screenId: "admin.logs-transacoes",
      code: {
        screenId: "admin.logs-transacoes",
        relatedScreens: ["admin.transacoes"],
        productionUrl: "production/app.html?screenId=admin.logs-transacoes"
      }
    },
    {
      id: "runtime-minimo-entidade",
      category: "Backend",
      title: "Minimo de entidade no runtime",
      summary: "Pagina de referencia com os arquivos preenchidos para cadastrar uma entidade produto no runtime generico.",
      page: pagePath + "runtime-minimo-entidade.html",
      code: {
        files: [
          "examples/minimo-entidade/01-create-table-produto.sql",
          "examples/minimo-entidade/02-builder-entity.json",
          "examples/minimo-entidade/03-builder-fields.json",
          "examples/minimo-entidade/04-screen-definition.json",
          "examples/minimo-entidade/05-runtime-endpoints.json",
          "examples/minimo-entidade/06-test-runtime.http",
          "examples/minimo-entidade/07-builder-situations.json"
        ],
        screenId: "cadastros.produtos",
        entityCode: "produto",
        handler: "entity.crud"
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
                { id: "sendWelcome", label: "Enviar boas-vindas", endpointId: "sendWelcome" },
                { id: "sendWhatsapp", label: "Enviar WhatsApp", endpointId: "sendWhatsapp" }
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
      id: "seguranca-producao",
      category: "Seguranca",
      title: "Modo producao seguro",
      summary: "Carrega a tela por screenId, bloqueia definitionUrl livre e troca URLs de API por endpointIds roteados pelo backend.",
      loadByScreenId: true,
      screenId: "cadastros.clientes.producao",
      code: {
        runtime: {
          screenId: "cadastros.clientes.producao"
        },
        homeRuntime: {
          screenId: "home.producao",
          currentSubscriber: { id: "principal", name: "Principal", principal: true },
          layout: {
            appbar: {
              subscriberSwitch: {
                enabled: true,
                endpoints: {
                  change: { endpointId: "home.subscriber.change", method: "POST" }
                }
              },
              support: {
                enabled: true,
                endpoints: {
                  onlineUsers: { endpointId: "home.support.onlineUsers", method: "GET" },
                  send: { endpointId: "home.support.send", method: "POST" },
                  createRequest: { endpointId: "home.support.createRequest", method: "POST" }
                }
              },
              notifications: {
                enabled: true,
                endpoints: {
                  list: { endpointId: "home.notifications.list", method: "POST" },
                  ack: { endpointId: "home.notifications.ack", method: "POST" }
                }
              },
              alerts: {
                enabled: true,
                endpoints: {
                  list: { endpointId: "home.alerts.list", method: "POST" }
                }
              }
            }
          }
        },
        "production/app.html": "production/app.html?screenId=cadastros.clientes",
        "production/home.html": "production/home.html?screenId=home.producao",
        "crud-engine.config.json": {
          security: {
            mode: "production",
            definitionSource: {
              allowDirectDefinition: false,
              allowDefinitionUrl: false,
              requireScreenId: true,
              endpoint: {
                url: "/api/runtime/screens/{screenId}",
                method: "GET"
              }
            },
            endpoints: {
              allowInlineUrls: false,
              requireEndpointIds: true,
              runtimeEndpoint: {
                url: "/api/runtime/screens/{screenId}/endpoints/{endpointId}",
                method: "POST"
              }
            },
            documents: {
              allowInlineUrls: false,
              allowExternalUrls: false
            },
            content: {
              allowInlineHtml: false
            }
          },
          help: {
            readEndpoint: {
              endpointId: "help.markAsRead",
              method: "POST"
            }
          }
        }
      },
      applyConfig: function(config, source) {
        deepMerge(config, source["crud-engine.config.json"]);
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

  const propertyOptions = [
    option("Raiz", "schemaVersion", "\"1.0\"", "Obrigatorio", "Versao atual do contrato."),
    option("Raiz", "pageType", "\"crud\" | \"home\" | \"process\" | \"custom\"", "Obrigatorio", "Define qual motor fechado renderiza a pagina."),
    option("Programa", "program.help.kind", "text | link | video", "text", "Define o conteudo principal da ajuda da tela."),
    option("Programa", "program.help.items[].kind", "text | link | video", "text", "Itens adicionais exibidos na janela de ajuda/novidades."),
    option("Programa", "program.logs.url", "URL relativa | http | https", "vazio", "Se vazio, o botao de log da tela nao aparece."),
    option("Permissoes", "permissions.*", "true | false", "false se ausente", "Controla exibicao de botoes e acoes."),
    option("Seguranca", "runtime.screenId", "identificador seguro", "vazio", "Usado pelo exemplo para carregar a tela sem definitionUrl livre."),
    option("Seguranca", "config.security.mode", "demo | production", "demo", "production ativa bloqueios conservadores de primeira versao."),
    option("Seguranca", "config.security.definitionSource.allowDirectDefinition", "true | false", "false em production", "Bloqueia JSON direto informado pelo frontend em producao."),
    option("Seguranca", "config.security.definitionSource.allowDefinitionUrl", "true | false", "false em production", "Bloqueia definitionUrl livre em producao."),
    option("Seguranca", "config.security.definitionSource.requireScreenId", "true | false", "true em production", "Exige carregar a tela por screenId."),
    option("Seguranca", "config.security.endpoints.allowInlineUrls", "true | false", "false em production", "Bloqueia URL livre nos endpoints do JSON."),
    option("Seguranca", "config.security.endpoints.requireEndpointIds", "true | false", "true em production", "Exige endpointId/actionId para chamadas de dados e acoes."),
    option("Seguranca", "config.security.endpoints.runtimeEndpoint.url", "URL relativa com {screenId}/{endpointId}", "/api/runtime/screens/{screenId}/endpoints/{endpointId}", "Gateway backend que resolve endpointId autorizado."),
    option("Seguranca", "dataSource.api.*.endpointId", "identificador seguro", "nome da API", "Identificador que o backend valida antes de executar a acao."),
    option("Runtime", "runtime.entityCode", "identificador de entidade", "program.entity", "Entidade usada pelo backend para semaforo, auditoria e versao do registro."),
    option("Runtime", "runtime.programId", "identificador de programa", "program.id", "Programa usado na precedencia das politicas de semaforo."),
    option("Runtime", "runtime.lock.enabled", "true | false", "true", "Habilita aquisicao, heartbeat e liberacao de semaforo no CRUD."),
    option("Runtime", "runtime.lock.lockTtlSeconds", "numero inteiro", "300", "Tempo de expiracao do semaforo quando nao houver heartbeat."),
    option("Runtime", "runtime.lock.heartbeatIntervalSeconds", "numero inteiro", "60", "Intervalo de renovacao do semaforo enquanto o formulario esta aberto."),
    option("Runtime", "runtime.messages.pollIntervalSeconds", "numero inteiro", "30", "Intervalo de consulta de mensagens e derrubada de sessao."),
    option("Runtime", "runtime.messages.events.enabled", "true | false", "true", "Habilita SSE para mensagens runtime; polling continua como fallback."),
    option("Seguranca", "config.security.endpoints.runtimeEventsEndpoint.url", "URL relativa segura", "/api/runtime/events", "Canal SSE fixo para mensagens runtime e derrubada de sessao."),
    option("Dados/API", "dataSource.api.*.method", "GET | POST | PUT | PATCH | DELETE", "GET", "Metodo usado pelo mock/backend."),
    option("Dados/API", "dataModel.fields[].type", "string | text | integer | decimal | number | boolean | date | datetime | email | enum | lookup | hidden", "Obrigatorio", "Tipo base usado por grid, filtros e formulario."),
    option("Dados/API", "dataModel.fields[].format", "currency | date | datetime | number", "por tipo", "Atalhos implementados para formatacao Kendo."),
    option("Dados/API", "dataModel.fields[].technicalProperties[]", "lista de { label, value }", "vazio", "Exibe um icone tecnico ao lado do nome do campo no grid, filtro e formulario."),
    option("Grid", "crud.grid.columns[].technicalProperties[]", "lista de { section, label, value }", "fallback do campo", "Permite sobrescrever ou complementar as propriedades tecnicas mostradas no cabecalho do grid."),
    option("Filtro", "crud.filter.fields[].technicalProperties[]", "lista de { section, label, value }", "fallback do campo", "Permite sobrescrever ou complementar as propriedades tecnicas mostradas no filtro."),
    option("Consulta", "crud.query.defaultSort[].dir", "asc | desc", "vazio", "Direcao da ordenacao inicial."),
    option("Filtro", "crud.filter.type", "window", "window", "Renderizador de filtro implementado hoje."),
    option("Filtro", "crud.filter.mode", "basic", "basic", "Modo interno atual do filtro."),
    option("Filtro", "crud.filter.openOnLoad", "true | false", "false", "Abre a janela de filtros ao entrar na pagina."),
    option("Filtro", "crud.filter.waitForSubmitOnLoad", "true | false", "true", "Quando o filtro abre na entrada, espera o usuario clicar em Filtrar."),
    option("Filtro", "crud.filter.maximizeFilter", "true | false", "false", "Abre o filtro maximizado tanto na entrada quanto manualmente."),
    option("Filtro", "crud.filter.showAppliedFilters", "true | false", "true", "Exibe ou oculta os chips dos filtros aplicados."),
    option("Filtro", "crud.filter.tabs.enabled", "true | false", "false", "Divide os campos de filtro em abas."),
    option("Filtro", "crud.filter.fields[].type", "search | text | enum | lookup | dateRange | date | datetime | time | number | integer | decimal | boolean", "por campo", "Tipo do filtro renderizado."),
    option("Filtro", "crud.filter.fields[].editor", "dropdown | dropdownList | multiselect | dropdowntree | checkbox | checkboxgroup | searchWindow", "por tipo", "Editor opcional para filtros de opcoes/lookup."),
    option("Filtro", "crud.filter.fields[].operator", "eq | neq | startsWith | contains | notContains | isEmpty | isNotEmpty | isNull | isNotNull | between | in | notIn | gt | gte | lt | lte | relative", "por tipo", "Operador inicial do filtro."),
    option("Filtro", "crud.filter.fields[].operators[]", "eq | neq | startsWith | contains | notContains | isEmpty | isNotEmpty | isNull | isNotNull | between | in | notIn | gt | gte | lt | lte | relative", "por tipo", "Lista de operadores permitidos para o usuario."),
    option("Filtro", "relativeDate.defaultPreset", "yesterday | today | tomorrow | days | weeks | fortnights | months | years", "today", "Opcoes de data relativa."),
    option("Filtro", "relativeDate.defaultDirection", "previous | next", "previous", "Na UI aparece como Antes ou Depois."),
    option("Grid", "crud.grid.pageable", "true | false", "true na demo", "Liga/desliga paginacao do Kendo Grid."),
    option("Grid", "crud.grid.sortable", "true | false", "true na demo", "Liga/desliga ordenacao por coluna."),
    option("Grid", "crud.grid.filterable", "true | false", "true na demo", "Liga/desliga filtro nativo do Kendo nas colunas."),
    option("Grid", "crud.grid.groupable", "true | false", "false", "Liga area nativa de agrupamento do grid."),
    option("Grid", "crud.grid.resizable", "true | false", "true na demo", "Permite redimensionar colunas."),
    option("Grid", "crud.grid.reorderable", "true | false", "true na demo", "Permite reordenar colunas."),
    option("Grid", "crud.grid.columnMenu", "true | false", "true na demo", "Liga menu da coluna."),
    option("Grid", "crud.grid.columns[].align", "left | center | right", "left", "Alinhamento da coluna."),
    option("Grid", "crud.grid.mobile.mode", "columns | template", "vazio", "columns mantem grid; template usa card mobile seguro."),
    option("Grid", "crud.grid.mobile.cardActions", "true | false", "true", "Exibe acoes dentro do card mobile."),
    option("Grid", "crud.userLayout.savedMobileTemplates[]", "{ id, name, template }", "vazio", "Templates mobile salvos por usuario no backend."),
    option("Grid", "crud.userLayout.grid.mobileTemplate.fields[]", "nomes de campos", "vazio", "Ordem dos campos exibidos no card mobile do usuario."),
    option("Grid", "preferencias.scope", "tenant | global", "tenant", "tenant salva apenas no assinante atual; global vira fallback para todos os assinantes do usuario."),
    option("Grid", "preferencias.applyToAllTenants", "true | false", "false", "Atalho para salvar a preferencia como global do usuario."),
    option("Grid", "crud.grid.freezeColumns.enabled", "true | false", "false", "Recurso apenas desktop."),
    option("Grid", "crud.grid.ai.enabled", "true | false", "false", "Exibe ou oculta SmartBox de IA do grid."),
    option("Grid", "crud.grid.ai.provider", "mock | service", "mock", "mock interpreta localmente; service chama backend."),
    option("Grid", "crud.grid.ai.tool", "smartbox", "smartbox", "Componente Kendo usado hoje."),
    option("Grid", "crud.grid.ai.activeMode", "Search | AIAssistant", "AIAssistant", "Modo inicial do SmartBox."),
    option("Grid", "crud.grid.print.options[]", "excel | pdf | csv", "vazio", "Sem opcoes, o botao Imprimir nao aparece."),
    option("Grid", "crud.grid.toolbar[].action", "create | filters | refresh | sort | group | layout", "vazio", "A action precisa existir nos handlers do motor."),
    option("Grid", "crud.grid.rowActions[].action", "view | edit | delete", "vazio", "Acoes padrao por linha."),
    option("Formulario", "crud.form.mode", "popup", "popup", "Modo implementado hoje."),
    option("Formulario", "crud.form.layout", "tabs | sections | single | steps", "tabs", "steps ativa formulario por etapas."),
    option("Formulario", "crud.form.maximizeForm", "true | false", "false", "Abre o formulario maximizado."),
    option("Formulario", "crud.form.behavior.closeOnSave", "true | false", "true", "Controla se fecha ao confirmar."),
    option("Formulario", "crud.form.behavior.closeOnCancel", "true | false", "false", "Controla se fecha ao cancelar."),
    option("Formulario", "crud.form.navigation.enabled", "true | false", "true", "Exibe botoes anterior/proximo."),
    option("Formulario", "crud.form.mobile.showHeaderActions", "true | false", "false", "No mobile, controla botoes do cabecalho do formulario."),
    option("Formulario", "crud.form.concurrencyWarning.enabled", "true | false", "false", "Habilita aviso antes de ativar Alterar ou Excluir."),
    option("Formulario", "crud.form.concurrencyWarning.actions[]", "edit | delete", "edit, delete", "Define em quais acoes de alteracao/exclusao o aviso sera exibido antes do semaforo."),
    option("Formulario", "crud.form.concurrencyWarning.message", "texto com tokens {campo}", "mensagem padrao", "Mensagem geral de aviso de uso concorrente do registro."),
    option("Formulario", "crud.form.concurrencyWarning.editMessage", "texto com tokens {campo}", "vazio", "Mensagem especifica para o botao Alterar."),
    option("Formulario", "crud.form.concurrencyWarning.deleteMessage", "texto com tokens {campo}", "vazio", "Mensagem especifica para o botao Excluir."),
    option("Formulario", "crud.form.concurrencyWarning.confirm/blocking", "true | false", "true", "Quando false, mostra apenas notificacao e nao bloqueia a ativacao da acao."),
    option("Formulario", "dataModel.fields.*.editor", "textarea | dropdown | switch | customCode", "type do campo", "customCode renderiza um campo fechado com assistente declarativo para formar o codigo."),
    option("Formulario", "dataModel.fields.*.customCode.mode", "pattern | static_method", "pattern", "Define se o codigo vem de um padrao declarativo ou de um metodo estatico permitido no backend."),
    option("Formulario", "dataModel.fields.*.customCode.assistantScreenId", "screenId de tela process", "vazio", "Quando informado, abre uma tela auxiliar segura por screenId para coletar propriedades e devolver result.type=properties."),
    option("Formulario", "dataModel.fields.*.customCode.promptFields[].type", "string | integer | decimal | boolean | enum | dropdown", "string", "Tipos aceitos pelo assistente declarativo de propriedades da codificacao."),
    option("Formulario", "crud.form.situation.display", "stepper | arrowstep | badge", "stepper", "arrowstep usa renderizacao propria em formato de etapas."),
    option("Formulario", "crud.form.situation.orientation", "horizontal | vertical", "horizontal", "Propriedade catalogada no schema; a UI atual prioriza horizontal."),
    option("Formulario", "crud.form.fields[].renderAs", "label | readonly", "vazio", "Renderiza campo como label/somente leitura."),
    option("Formulario", "crud.form.fields[].display", "label | readonly", "vazio", "Alias aceito para renderAs."),
    option("Formulario", "crud.form.fields[].mode", "label | readonly", "vazio", "Alias aceito para renderAs."),
    option("Formulario", "crud.form.buttons[].placement", "header | footer", "footer", "Define appbar do botao configurado."),
    option("Formulario", "crud.form.buttons[].visibleIn[]", "create | edit | view | delete", "todos", "Controla em quais modos o botao aparece."),
    option("Formulario", "crud.form.buttons[].target/openAs", "request | window | newTab", "request", "Define como uma URL/API de botao sera acionada."),
    option("Formulario", "crud.form.buttons[].formValues", "true | false | lista | objeto", "false", "Envia valores do formulario quando o botao abre uma pagina do backend."),
    option("Formulario", "crud.form.buttons[].formValues.fields[]", "nomes de campos", "todos quando omitido", "Limita quais campos do formulario serao enviados."),
    option("Formulario", "crud.form.buttons[].formValues.transport", "auto | query | post", "auto", "query acrescenta parametros na URL; post envia formulario oculto para a pagina."),
    option("Formulario", "crud.form.buttons[].passFormValues", "true | false", "false", "Atalho para enviar todos os valores do formulario."),
    option("Backend", "validation.status", "blocked | warning | info | success", "blocked", "Status retornado pelo backend para consistencias de regra de negocio."),
    option("Backend", "validation.titleKey", "chave de literal", "opcional", "Permite o backend devolver o titulo por chave, com fallback para validation.title."),
    option("Backend", "validation.messages[].messageKey", "chave de literal", "opcional", "Permite o backend devolver a mensagem por chave, com fallback para validation.messages[].message."),
    option("Backend", "validation.messages[].messageParams", "objeto", "opcional", "Parametros simples usados para interpolar o literal da mensagem."),
    option("Backend", "validation.messages[].field", "nome do campo", "opcional", "Campo que deve ser marcado quando o backend bloquear ou avisar uma consistencia."),
    option("Backend", "validation.requiresConfirmation", "true | false", "false", "Quando true, o frontend pede confirmacao Kendo e reenvia a chamada com token."),
    option("Backend", "effects[]", "lista de efeitos seguros", "opcional", "Efeitos retornados pelo backend e aplicados no formulario apos consistencia."),
    option("Backend", "_runtime.asyncJobs[]", "{ type, status }", "opcional", "Jobs assincronos agendados pelo backend apos uma gravacao bem sucedida."),
    option("Backend", "runtime_endpoint.config.jobs[].mode", "async", "vazio", "Quando mode=async, o endpoint fechado agenda o job no worker."),
    option("Backend", "runtime_endpoint.config.jobs[].type", "tipo registrado", "obrigatorio", "Tipo fechado executado por handler PHP registrado, sem codigo livre no banco."),
    option("Formulario", "crud.form.print.options[].format", "excel | pdf | csv", "vazio", "Formatos disponiveis no formulario."),
    option("Formulario", "crud.form.print.options[].source", "browser | api", "api quando endpoint existe", "browser usa impressao/exportacao local quando configurado."),
    option("Formulario", "crud.form.events[].event", "change | blur | focus | enter | open | afterLoad", "change", "Eventos seguros suportados pelo motor."),
    option("Formulario", "crud.form.events[].effects[].action", "setValue | clearValue | readonly | enabled | disabled | visible | show | hide | required | setOptions | reloadOptions | showMessage", "vazio", "Efeitos seguros aplicados pelo motor, sem JavaScript livre."),
    option("Formulario", "crud.form.events[].effects[].type", "success | error | info | warning", "info", "Tipo da mensagem quando action=showMessage."),
    option("Formulario", "crud.form.events[].when.operator", "eq | neq | in | notIn | contains | isEmpty | isNotEmpty | isNull | isNotNull | truthy | falsy", "eq", "Operadores de condicao para eventos."),
    option("Global", "config.theme.defaultMode", "light | dark", "light", "Modo inicial do tema global."),
    option("Global", "config.theme.allowUserSwitch", "true | false", "true", "Permite alternar claro/escuro no cabecalho."),
    option("Global", "config.theme.persistUserChoice", "true | false", "true", "Persiste escolha do usuario no localStorage."),
    option("Global", "config.help.items[].kind", "text | link | video", "text", "Tipo de novidade global."),
    option("Home", "pageType", "\"home\"", "Obrigatorio", "Ativa o HomeEngine para renderizar uma pagina inicial por JSON."),
    option("Home", "app.logo.url", "URL relativa | http | https", "vazio", "Logo da empresa exibido no canto esquerdo da appbar global."),
    option("Home", "app.logo.showTitle", "true | false", "true", "Define se o nome do app aparece ao lado do logo."),
    option("Home", "app.logo.showSubtitle", "true | false", "true", "Define se o subtitulo do app aparece abaixo do nome ao lado do logo."),
    option("Home", "layout.initialProgramId", "id de programa", "primeiro programa", "Programa aberto ao carregar a pagina inicial."),
    option("Home", "layout.sidebar.component", "\"kendoTreeView\"", "kendoTreeView", "Componente Kendo usado no menu lateral."),
    option("Home", "layout.sidebar.collapsible", "true | false", "true", "Permite recolher e expandir o menu lateral."),
    option("Home", "layout.sidebar.collapsed", "true | false", "false", "Define se o menu lateral inicia recolhido."),
    option("Home", "layout.sidebar.expanded", "true | false", "true", "Define se os grupos do TreeView iniciam expandidos."),
    option("Home", "layout.appbar.showSidebarToggle", "true | false", "true", "Exibe o botao de expandir/recolher o menu lateral no appbar."),
    option("Home", "layout.appbar.showRefresh", "true | false", "true", "Exibe o botao Atualizar no appbar."),
    option("Home", "layout.appbar.showFavoriteToggle", "true | false", "true", "Exibe o botao para favoritar o programa corrente no cabecalho."),
    option("Home", "layout.appbar.showCurrentSubscriber", "true | false", "true", "Exibe o assinante corrente no cabecalho quando currentSubscriber estiver informado."),
    option("Home", "layout.appbar.subscriberSwitch.enabled", "true | false", "false", "Habilita clique no badge do assinante para abrir a janela de troca."),
    option("Home", "layout.appbar.subscriberSwitch.programId", "id de programa", "vazio", "Programa opcional aberto para um fluxo dedicado de troca de assinante."),
    option("Home", "layout.appbar.subscriberSwitch.url", "URL relativa | http | https", "vazio", "URL opcional aberta para um fluxo dedicado de troca de assinante."),
    option("Home", "layout.appbar.subscriberSwitch.endpoints.change", "URL | { url, method } | { endpointId, method }", "vazio", "API chamada para confirmar a troca de assinante. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.showUserMenu", "true | false", "true", "Exibe o menu do usuario logado na appbar global."),
    option("Home", "layout.appbar.chat.enabled", "true | false", "false", "Exibe o botao de chat no appbar global."),
    option("Home", "layout.appbar.chat.endpoints.contacts", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true sem contacts[]", "Endpoint que retorna os usuarios disponiveis para conversa. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.chat.contacts[]", "texto | { id, name, email }", "vazio", "Lista estatica opcional de usuarios para o ComboBox do chat."),
    option("Home", "layout.appbar.chat.endpoints.history", "URL | { url, method } | { endpointId, method }", "vazio", "Endpoint opcional para carregar historico inicial do chat. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.chat.endpoints.send", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint chamado ao enviar mensagem no chat. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.chat.defaultRecipientId", "id de usuario", "primeiro usuario", "Usuario selecionado inicialmente no ComboBox do chat."),
    option("Home", "layout.appbar.support.enabled", "true | false", "false", "Exibe o botao de atendimento no appbar global."),
    option("Home", "layout.appbar.support.endpoints.onlineUsers", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint que informa setores e atendentes online por setor. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.support.endpoints.send", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint chamado ao enviar mensagem ao atendente online do setor selecionado. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.support.endpoints.createRequest", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint chamado para abrir solicitacao quando nao houver atendente online no setor. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.support.fallbackRequest.defaultSectorId", "id de setor", "suporte", "Setor inicial selecionado no atendimento e na solicitacao."),
    option("Home", "layout.appbar.support.sectors[]", "texto | { id, name }", "Suporte", "Setores disponiveis para atendimento e abertura de solicitacao."),
    option("Home", "layout.appbar.aiChat.enabled", "true | false", "false", "Exibe o botao de chat de IA no appbar global."),
    option("Home", "layout.appbar.aiChat.endpoints.history", "URL | { url, method } | { endpointId, method }", "vazio", "Endpoint opcional para carregar historico inicial do chat de IA. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.aiChat.endpoints.send", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint chamado ao enviar mensagem para a IA. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.aiChat.bot.id/name", "texto", "ia", "Autor usado nas mensagens de resposta da IA."),
    option("Home", "layout.appbar.notifications.enabled", "true | false", "false", "Exibe a central de notificacoes no appbar global."),
    option("Home", "layout.appbar.notifications.endpoints.list", "URL | { url, method } | { endpointId, method }", "vazio", "Endpoint proprio da central de notificacoes. Quando omitido, o motor agrega alerts, requests e jobs habilitados."),
    option("Home", "layout.appbar.alerts.enabled", "true | false", "false", "Exibe o botao de sino para alertas de informacoes recebidas."),
    option("Home", "layout.appbar.alerts.endpoints.list", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint que retorna os alertas exibidos em janela. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.requests.enabled", "true | false", "false", "Exibe o botao de solicitacoes recebidas ou atualizadas."),
    option("Home", "layout.appbar.requests.endpoints.list", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint que retorna as solicitacoes exibidas em janela. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.jobs.enabled", "true | false", "false", "Exibe o botao de jobs concluidos no appbar global."),
    option("Home", "layout.appbar.jobs.programId", "id de programa", "vazio", "Programa aberto para consultar os jobs iniciados pelo usuario corrente."),
    option("Home", "layout.appbar.jobs.endpoints.list", "URL | { url, method } | { endpointId, method }", "obrigatorio quando enabled=true", "Endpoint que retorna jobs concluidos para aviso no appbar. Em producao, usar endpointId ou actionId."),
    option("Home", "layout.appbar.userMenu.items[].action", "profile | preferences | logout | texto", "vazio", "Acao executada pelo item do menu de usuario."),
    option("Home", "layout.appbar.userMenu.items[].openAs", "newTab | window", "newTab", "Define como abrir URLs do menu de usuario."),
    option("Home", "currentUser.name/email/initials", "texto", "vazio", "Dados usados para montar o botao e cabecalho do menu do usuario."),
    option("Home", "currentSubscriber", "texto | { id, name, displayName, title, code, document, label, principal }", "vazio", "Assinante/tenant corrente exibido como badge no cabecalho global. Quando principal=true, exibe Principal destacado."),
    option("Home", "availableSubscribers[]", "texto | { id, name, document, principal }", "vazio", "Lista de assinantes disponiveis para troca no badge do cabecalho."),
    option("Home", "currentUser.favoritePrograms[]", "ids de programas", "vazio", "Favoritos do usuario usados pelo filtro do menu lateral."),
    option("Home", "currentUser.unfavoritePrograms[]", "ids de programas", "vazio", "Programas removidos dos favoritos pelo usuario."),
    option("Home", "navigation.groups[].items[].favorite", "true | false", "false", "Marca o programa como favorito para filtro e botao da pagina corrente, sem indicador no item do menu."),
    option("Home", "programs[].favorite", "true | false", "false", "Tambem pode marcar o programa como favorito no cadastro do programa, sem indicador no item do menu."),
    option("Home", "navigation.initialModuleId", "vazio | __all__ | id de modulo", "vazio", "Modulo selecionado no ComboBox ao abrir a pagina inicial. Vazio, ausente ou __all__ mostra Todos."),
    option("Home", "navigation.modules[]", "id, title, permission", "vazio", "Lista de sistemas/modulos exibidos no ComboBox acima do TreeView. A opcao Todos e criada pelo motor."),
    option("Home", "navigation.groups[].moduleId", "id de modulo", "primeiro modulo configurado", "Vincula um grupo da TreeView ao sistema/modulo selecionado."),
    option("Home", "programs[].type", "iframe | crud | html | process | custom", "iframe", "Modo fechado usado para abrir o programa."),
    option("Home", "programs[].title/subtitle/version", "texto", "vazio", "Metadados exibidos na appbar global quando o programa abre na Home."),
    option("Home", "programs[].subtitleTooltip", "texto", "vazio", "Texto longo aberto pelo subtitulo na appbar global."),
    option("Home", "programs[].help", "body | linkUrl | videoUrl | items[]", "vazio", "Ajuda/novidades exibidas na appbar global para programas sem CrudEngine embutido."),
    option("Home", "programs[].logs.url", "URL relativa | http | https", "vazio", "Log exibido na appbar global para programas sem CrudEngine embutido."),
    option("Home", "programs[].openUrl", "URL relativa | http | https", "vazio", "URL navegavel preferencial usada pelo botao de abrir em nova aba no menu."),
    option("Home", "programs[].url", "URL relativa | http | https", "vazio", "Obrigatorio para programas type=iframe."),
    option("Home", "programs[].definitionUrl", "URL relativa | http | https", "vazio", "Usado por programas type=crud, type=process ou type=custom em demo."),
    option("Home", "programs[].html", "HTML sem script/eventos", "vazio", "HTML sanitizado antes da injecao."),
    option("Home", "programs[].htmlUrl", "URL relativa | http | https", "vazio", "Carrega fragmento HTML para programas type=html."),
    option("Custom", "custom.mode", "iframe | htmlUrl", "iframe", "Define se o programa manual abre em iframe interno ou carrega um fragmento HTML sanitizado."),
    option("Custom", "custom.entryUrl", "URL relativa interna", "Obrigatorio", "Entrypoint manual do programa custom, sempre relativo ao proprio sistema."),
    option("Custom", "custom.frameTitle", "texto", "program.title", "Titulo acessivel usado no iframe do programa manual."),
    option("Processamento", "process.parameters.fields[].type", "text | string | number | integer | decimal | date | datetime | boolean | enum | option | dropdown", "text", "Tipo de campo usado para montar os parametros do processamento."),
    option("Processamento", "process.parameters.fields[].required", "true | false", "false", "Impede processar sem valor no parametro."),
    option("Processamento", "process.parameters.fields[].technicalProperties[]", "lista de { section, label, value }", "vazio", "Exibe um icone tecnico ao lado do parametro do processamento."),
    option("Processamento", "process.actions.process.label/icon", "texto", "Processar / play", "Texto e icone do botao principal de processamento."),
    option("Processamento", "process.wait.mode", "auto | sse | polling | none", "auto", "Define se o motor acompanha o job por SSE, polling, automatico ou apenas inicia o job."),
    option("Processamento", "process.wait.pollIntervalSeconds", "numero", "2", "Intervalo do polling de status quando SSE nao estiver disponivel."),
    option("Processamento", "process.result.type", "message | grid | report | job", "message", "Tipo esperado de retorno quando o backend nao informar explicitamente."),
    option("Processamento", "dataSource.api.process", "URL | { url, method } | { endpointId, method }", "obrigatorio", "Endpoint que inicia o processamento. Em producao, usar endpointId ou actionId."),
    option("Processamento", "dataSource.api.status", "URL | { url, method } | { endpointId, method }", "vazio", "Endpoint consultado para acompanhar job quando houver polling.")
  ];

  function option(category, path, values, defaultValue, note) {
    return { category, path, values, defaultValue, note };
  }

  function list() {
    return examples.map(function(example) {
      return {
        id: example.id,
        category: example.category,
        title: example.title,
        summary: example.summary,
        page: example.page || pagePath + example.id + ".html",
        engine: example.engine,
        loadByScreenId: example.loadByScreenId,
        screenId: example.screenId
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

  function buildHomeDefinition(id) {
    const example = get(id);
    const source = getHomeSourceDefinition();
    const definition = clone(source);
    if (example && example.code) {
      deepMerge(definition, example.code);
    }
    if (example && typeof example.applyHome === "function") {
      example.applyHome(definition, source);
    }
    return definition;
  }

  function buildProcessDefinition(id) {
    const example = get(id);
    const source = getProcessSourceDefinition();
    const definition = clone(source);
    if (example && example.code) {
      deepMerge(definition, example.code);
    }
    if (example && typeof example.applyProcess === "function") {
      example.applyProcess(definition, source);
    }
    return definition;
  }

  function buildConfig(id, options) {
    const example = get(id);
    const config = clone(global.CrudDemoEmbedded && global.CrudDemoEmbedded.config || {});
    if (example && typeof example.applyConfig === "function") {
      example.applyConfig(config, example.code || {});
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

  function getPropertyOptions() {
    return propertyOptions.slice();
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
      activeMobileTemplateId: null,
      savedLayouts: [],
      savedSorts: [],
      savedGroups: [],
      savedFilters: [],
      savedMobileTemplates: [],
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
        groupAggregates: [],
        mobileTemplate: null
      }
    });
  }

  function getSourceDefinition() {
    if (!global.CrudDemoEmbedded || !global.CrudDemoEmbedded.clientesDefinition) {
      throw new Error("Definicao base de clientes nao carregada.");
    }
    return global.CrudDemoEmbedded.clientesDefinition;
  }

  function getHomeSourceDefinition() {
    if (!global.HomeDemoEmbedded || !global.HomeDemoEmbedded.homeDefinition) {
      throw new Error("Definicao base da Home nao carregada.");
    }
    return global.HomeDemoEmbedded.homeDefinition;
  }

  function getProcessSourceDefinition() {
    if (!global.CrudDemoEmbedded || !global.CrudDemoEmbedded.processamentoRelatorioDefinition) {
      throw new Error("Definicao base de processamento nao carregada.");
    }
    return global.CrudDemoEmbedded.processamentoRelatorioDefinition;
  }

  function deepMerge(target, source) {
    if (!source || typeof source !== "object" || Array.isArray(source)) {
      return target;
    }

    Object.keys(source).forEach(function(key) {
      const value = source[key];
      if (Array.isArray(value) && Array.isArray(target[key])) {
        target[key] = mergeArrayByStableKey(target[key], value);
      } else if (value && typeof value === "object" && !Array.isArray(value) && target[key] && typeof target[key] === "object" && !Array.isArray(target[key])) {
        deepMerge(target[key], value);
      } else {
        target[key] = clone(value);
      }
    });

    return target;
  }

  function mergeArrayByStableKey(targetItems, sourceItems) {
    const key = getArrayStableKey(targetItems, sourceItems);
    if (!key) {
      return clone(sourceItems);
    }

    const merged = clone(targetItems);
    sourceItems.forEach(function(sourceItem) {
      const sourceKey = String(sourceItem && sourceItem[key] || "");
      const index = merged.findIndex(function(targetItem) {
        return String(targetItem && targetItem[key] || "") === sourceKey;
      });
      if (index === -1) {
        merged.push(clone(sourceItem));
      } else if (sourceItem && typeof sourceItem === "object" && !Array.isArray(sourceItem)) {
        deepMerge(merged[index], sourceItem);
      } else {
        merged[index] = clone(sourceItem);
      }
    });
    return merged;
  }

  function getArrayStableKey(targetItems, sourceItems) {
    const items = targetItems.concat(sourceItems);
    if (!items.length || items.some(function(item) { return !item || typeof item !== "object" || Array.isArray(item); })) {
      return "";
    }
    if (items.every(function(item) { return item.id != null; })) {
      return "id";
    }
    if (items.every(function(item) { return item.programId != null; })) {
      return "programId";
    }
    return "";
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
    buildHomeDefinition,
    buildProcessDefinition,
    buildConfig,
    getCode,
    getPropertyOptions
  };
})(window);
