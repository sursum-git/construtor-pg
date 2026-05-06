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
        "examples.read": true,
        "theme.read": true,
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
        }
      ]
    }
  };
})(window);
