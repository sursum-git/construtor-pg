(function(global) {
  "use strict";

  global.HomeDemoEmbedded = {
    homeDefinition: {
      "$schema": "../public/metadata/schemas/home-definition-v1.schema.json",
      "schemaVersion": "1.0",
      "pageType": "home",
      "app": {
        "id": "construtor-pg",
        "title": "Construtor PG",
        "subtitle": "Pagina inicial por metadados",
        "version": "1.0.0",
        "logo": {
          "url": "public/assets/company-logo.svg",
          "alt": "Construtor PG",
          "showTitle": true,
          "showSubtitle": true
        }
      },
      "permissions": {
        "home.read": true,
        "clientes.read": true,
        "processamento.read": true,
        "jobs.read": true,
        "examples.read": true,
        "theme.read": true,
        "admin.read": true,
        "user.profile": true,
        "user.preferences": true,
        "user.logout": true
      },
      "currentUser": {
        "id": "u-demo",
        "name": "Tasio Silva",
        "email": "tasio@example.com",
        "initials": "TS",
        "favoritePrograms": ["clientes-crud", "exemplos"]
      },
      "currentSubscriber": {
        "id": "assinante-demo",
        "name": "Empresa Demonstracao",
        "document": "00.000.000/0001-00",
        "label": "Assinante"
      },
      "availableSubscribers": [
        {
          "id": "principal",
          "name": "Principal",
          "principal": true
        },
        {
          "id": "assinante-demo",
          "name": "Empresa Demonstracao",
          "document": "00.000.000/0001-00",
          "label": "Assinante"
        },
        {
          "id": "assinante-filial",
          "name": "Empresa Filial",
          "document": "11.111.111/0001-11",
          "label": "Assinante"
        }
      ],
      "layout": {
        "initialProgramId": "painel",
        "sidebar": {
          "component": "kendoTreeView",
          "collapsible": true,
          "collapsed": false,
          "expanded": true
        },
        "appbar": {
          "showSidebarToggle": true,
          "showRefresh": true,
          "showThemeSwitch": true,
          "showFavoriteToggle": true,
          "showCurrentSubscriber": true,
          "subscriberSwitch": {
            "enabled": true,
            "title": "Trocar assinante",
            "programId": "troca-assinante",
            "endpoints": {
              "change": {
                "url": "/api/home/subscribers/change",
                "method": "POST"
              }
            }
          },
          "showUserMenu": true,
          "chat": {
            "enabled": false,
            "title": "Chat entre usuarios",
            "buttonTitle": "Conversar com usuario",
            "width": 420,
            "height": 560,
            "recipient": {
              "placeholder": "Selecione um usuario"
            },
            "endpoints": {
              "contacts": "/api/home/chat/contacts",
              "history": {
                "url": "/api/home/chat/history",
                "method": "POST"
              },
              "send": "/api/home/chat/send"
            }
          },
          "support": {
            "enabled": true,
            "title": "Atendimento",
            "buttonTitle": "Abrir atendimento",
            "icon": "headset",
            "width": 540,
            "height": 620,
            "fallbackRequest": {
              "enabled": true,
              "defaultSectorId": "suporte"
            },
            "sectors": [
              { "id": "suporte", "name": "Suporte" },
              { "id": "financeiro", "name": "Financeiro" }
            ],
            "endpoints": {
              "onlineUsers": "/api/home/support/online-users",
              "history": "/api/home/support/history",
              "send": "/api/home/support/send",
              "createRequest": "/api/home/support/requests",
              "requestStatus": "/api/home/support/requests/status"
            }
          },
          "aiChat": {
            "enabled": true,
            "title": "Chat de IA",
            "buttonTitle": "Abrir chat de IA",
            "icon": "sparkles",
            "width": 460,
            "height": 560,
            "welcomeMessage": {
              "text": "Como posso ajudar?"
            },
            "bot": {
              "id": "ia",
              "name": "IA"
            },
            "endpoints": {
              "history": "/api/home/ai-chat/history",
              "send": "/api/home/ai-chat/send"
            }
          },
          "alerts": {
            "enabled": true,
            "title": "Alertas",
            "buttonTitle": "Alertas de informacoes",
            "icon": "bell",
            "endpoints": {
              "list": "/api/home/alerts"
            }
          },
          "requests": {
            "enabled": true,
            "title": "Solicitacoes",
            "buttonTitle": "Solicitacoes recebidas ou atualizadas",
            "icon": "inbox",
            "endpoints": {
              "list": "/api/home/requests"
            }
          },
          "jobs": {
            "enabled": true,
            "title": "Jobs concluidos",
            "buttonTitle": "Jobs concluidos",
            "icon": "clock",
            "programId": "meus-jobs",
            "pollIntervalSeconds": 15,
            "endpoints": {
              "list": "/api/home/jobs"
            }
          },
          "runtimeMessages": {
            "enabled": true,
            "pollIntervalSeconds": 30,
            "events": {
              "enabled": true
            },
            "endpoints": {
              "poll": {
                "url": "/api/runtime/screens/home/endpoints/runtime.messages.poll",
                "method": "POST"
              },
              "ack": {
                "url": "/api/runtime/screens/home/endpoints/runtime.messages.ack",
                "method": "POST"
              },
              "forceLogout": {
                "url": "/api/runtime/screens/home/endpoints/runtime.admin.forceLogout",
                "method": "POST"
              }
            }
          },
          "userMenu": {
            "items": [
              {
                "id": "profile",
                "label": "Meus dados",
                "icon": "user",
                "action": "profile",
                "permission": "user.profile"
              },
              {
                "id": "preferences",
                "label": "Preferencias",
                "icon": "gear",
                "action": "preferences",
                "permission": "user.preferences"
              },
              {
                "id": "logout",
                "label": "Sair",
                "icon": "logout",
                "action": "logout",
                "permission": "user.logout"
              }
            ]
          }
        }
      },
      "navigation": {
        "initialModuleId": "",
        "modules": [
          {
            "id": "operacional",
            "title": "Operacional"
          },
          {
            "id": "ferramentas",
            "title": "Ferramentas"
          },
          {
            "id": "administracao",
            "title": "Administracao"
          }
        ],
        "groups": [
          {
            "id": "principal",
            "title": "Principal",
            "moduleId": "operacional",
            "items": [
              {
                "programId": "painel",
                "title": "Painel"
              },
              {
                "programId": "clientes-crud",
                "title": "Clientes",
                "favorite": true
              },
              {
                "programId": "processamento-clientes",
                "title": "Processamento"
              }
            ]
          },
          {
            "id": "apoio",
            "title": "Apoio",
            "moduleId": "ferramentas",
            "items": [
              {
                "programId": "clientes-iframe",
                "title": "Clientes via iframe"
              },
              {
                "programId": "exemplos",
                "title": "Exemplos",
                "favorite": true
              },
              {
                "programId": "tema",
                "title": "Editor de tema"
              }
            ]
          },
          {
            "id": "admin-runtime",
            "title": "Administracao",
            "moduleId": "administracao",
            "items": [
              {
                "programId": "admin-parametros",
                "title": "Parametros"
              },
              {
                "programId": "admin-parametro-valores",
                "title": "Valores de Parametros"
              },
              {
                "programId": "admin-listas-opcoes",
                "title": "Listas de Opcoes"
              },
              {
                "programId": "admin-opcoes",
                "title": "Opcoes"
              },
              {
                "programId": "admin-usuarios",
                "title": "Usuarios"
              },
              {
                "programId": "admin-usuario-assinantes",
                "title": "Usuarios por Assinante"
              },
              {
                "programId": "admin-sessoes",
                "title": "Sessoes"
              },
              {
                "programId": "admin-transacoes",
                "title": "Transacoes"
              },
              {
                "programId": "admin-logs-transacoes",
                "title": "Logs de Transacoes"
              },
              {
                "programId": "meus-jobs",
                "title": "Meus Jobs"
              }
            ]
          }
        ]
      },
      "programs": [
        {
          "id": "painel",
          "title": "Painel inicial",
          "subtitle": "HTML controlado pelo motor da pagina inicial",
          "type": "html",
          "icon": "home",
          "permission": "home.read",
          "html": "<section class=\"home-dashboard\"><div class=\"home-dashboard-hero\"><h2>Pagina inicial por JSON</h2><p>Este conteudo foi definido no JSON da home e injetado pelo HomeEngine apos sanitizacao. O motor nao executa scripts nem eventos vindos do JSON.</p></div><div class=\"home-dashboard-grid\"><article class=\"home-dashboard-card\"><span class=\"home-dashboard-number\">3</span><h3>Modos fechados</h3><p>iframe, crud e html.</p></article><article class=\"home-dashboard-card\"><span class=\"home-dashboard-number\">1</span><h3>Appbar global</h3><p>Cabecalho unico para programas.</p></article><article class=\"home-dashboard-card\"><span class=\"home-dashboard-number\">JSON</span><h3>Backend decide</h3><p>O frontend apenas renderiza opcoes permitidas.</p></article></div></section>"
        },
        {
          "id": "clientes-crud",
          "title": "Clientes",
          "subtitle": "CrudEngine instanciado dentro da home",
          "type": "crud",
          "icon": "user",
          "permission": "clientes.read",
          "openUrl": "index.html",
          "definitionUrl": "examples/clientes.crud.json"
        },
        {
          "id": "processamento-clientes",
          "title": "Processamento de Clientes",
          "subtitle": "Parametros com processamento assincrono",
          "type": "process",
          "icon": "play",
          "permission": "processamento.read",
          "openUrl": "examples/pages/processamento-parametros.html",
          "definitionUrl": "examples/processamento-relatorio.process.json"
        },
        {
          "id": "clientes-iframe",
          "title": "Clientes via iframe",
          "subtitle": "Programa HTML isolado dentro de iframe",
          "subtitleTooltip": "Exemplo de programa aberto por iframe com cabecalho local oculto e metadados exibidos na appbar global.",
          "version": "1.0.0",
          "type": "iframe",
          "icon": "window",
          "permission": "clientes.read",
          "help": {
            "enabled": true,
            "title": "Ajuda do iframe Clientes",
            "kind": "text",
            "body": "Quando aberto pela Home, o cabecalho local do programa e escondido para evitar duplicidade."
          },
          "logs": {
            "enabled": true,
            "title": "Logs do iframe Clientes",
            "url": "docs/clientes-logs.html"
          },
          "url": "index.html"
        },
        {
          "id": "exemplos",
          "title": "Indice de exemplos",
          "subtitle": "Pagina local carregada por iframe",
          "type": "iframe",
          "icon": "folder",
          "permission": "examples.read",
          "url": "exemplos.html"
        },
        {
          "id": "tema",
          "title": "Editor de tema",
          "subtitle": "Theme builder carregado por iframe",
          "type": "iframe",
          "icon": "palette",
          "permission": "theme.read",
          "url": "theme-builder.html"
        },
        {
          "id": "troca-assinante",
          "title": "Troca de assinante",
          "subtitle": "Programa dedicado para troca de contexto",
          "type": "html",
          "icon": "building",
          "permission": "home.read",
          "html": "<section class=\"home-dashboard\"><div class=\"home-dashboard-hero\"><h2>Troca de assinante</h2><p>Este programa demonstra que o JSON pode informar um programId ou uma URL para um fluxo dedicado de troca de assinante.</p></div></section>"
        },
        {
          "id": "admin-parametros",
          "title": "Parametros",
          "subtitle": "Cadastro administrativo de parametros do sistema",
          "type": "crud",
          "icon": "gear",
          "permission": "admin.read",
          "screenId": "admin.parametros"
        },
        {
          "id": "admin-parametro-valores",
          "title": "Valores de Parametros",
          "subtitle": "Valores vigentes por estabelecimento",
          "type": "crud",
          "icon": "sliders",
          "permission": "admin.read",
          "screenId": "admin.parametro-valores"
        },
        {
          "id": "admin-listas-opcoes",
          "title": "Listas de Opcoes",
          "subtitle": "Listas usadas por parametros e cadastros",
          "type": "crud",
          "icon": "list-unordered",
          "permission": "admin.read",
          "screenId": "admin.listas-opcoes"
        },
        {
          "id": "admin-opcoes",
          "title": "Opcoes",
          "subtitle": "Opcoes disponiveis em cada lista",
          "type": "crud",
          "icon": "check-outline",
          "permission": "admin.read",
          "screenId": "admin.opcoes"
        },
        {
          "id": "admin-usuarios",
          "title": "Usuarios",
          "subtitle": "Usuarios do sistema e seus grupos",
          "type": "crud",
          "icon": "users",
          "permission": "admin.read",
          "screenId": "admin.usuarios"
        },
        {
          "id": "admin-permissoes",
          "title": "Permissoes",
          "subtitle": "Controle de grupos e permissoes dos usuarios",
          "type": "crud",
          "icon": "users",
          "permission": "admin.read",
          "screenId": "admin.permissoes"
        },
        {
          "id": "admin-usuario-assinantes",
          "title": "Usuarios por Assinante",
          "subtitle": "Relacao de usuarios com assinantes e permissoes por contexto",
          "type": "crud",
          "icon": "users",
          "permission": "admin.read",
          "screenId": "admin.usuario-assinantes"
        },
        {
          "id": "admin-sessoes",
          "title": "Sessoes",
          "subtitle": "Consulta e derrubada de sessoes",
          "type": "crud",
          "icon": "user",
          "permission": "admin.read",
          "screenId": "admin.sessoes"
        },
        {
          "id": "admin-transacoes",
          "title": "Transacoes",
          "subtitle": "Transacoes gravadas pelo runtime",
          "type": "crud",
          "icon": "arrows-swap",
          "permission": "admin.read",
          "screenId": "admin.transacoes"
        },
        {
          "id": "admin-logs-transacoes",
          "title": "Logs de Transacoes",
          "subtitle": "Logs vinculados as transacoes",
          "type": "crud",
          "icon": "list-ordered",
          "permission": "admin.read",
          "screenId": "admin.logs-transacoes"
        },
        {
          "id": "meus-jobs",
          "title": "Meus Jobs",
          "subtitle": "Jobs iniciados pelo usuario corrente",
          "type": "crud",
          "icon": "clock",
          "permission": "jobs.read",
          "screenId": "runtime.jobs.mine"
        }
      ]
    }
  };
})(window);
