(function(global) {
  "use strict";

  const embedded = global.CrudDemoEmbedded = global.CrudDemoEmbedded || {};

  embedded.processamentoRelatorioDefinition = {
    "$schema": "../public/metadata/schemas/process-definition-v1.schema.json",
    "schemaVersion": "1.0",
    "pageType": "process",
    "screenId": "processamento.relatorio-clientes",
    "program": {
      "id": "processamento-relatorio-clientes",
      "screenId": "processamento.relatorio-clientes",
      "title": "Processamento de Clientes",
      "subtitle": "Parametros, execucao e acompanhamento por job",
      "version": "1.0.0"
    },
    "permissions": {
      "process": true
    },
    "dataSource": {
      "api": {
        "process": {
          "url": "/api/processamento/clientes",
          "endpointId": "process",
          "method": "POST"
        },
        "status": {
          "url": "/api/processamento/clientes/status",
          "endpointId": "status",
          "method": "POST"
        }
      }
    },
    "process": {
      "parameters": {
        "title": "Parametros do processamento",
        "fields": [
          {
            "id": "data-inicial",
            "field": "dataInicial",
            "label": "Data inicial",
            "type": "date",
            "required": true,
            "defaultValue": "2026-05-01",
            "technicalProperties": [
              { "section": "Modelo", "label": "Campo", "value": "dataInicial" },
              { "section": "Runtime", "label": "Tipo", "value": "date" },
              { "section": "Runtime", "label": "Obrigatorio", "value": "Sim", "critical": true }
            ]
          },
          {
            "id": "data-final",
            "field": "dataFinal",
            "label": "Data final",
            "type": "date",
            "required": true,
            "defaultValue": "2026-05-08",
            "technicalProperties": [
              { "section": "Modelo", "label": "Campo", "value": "dataFinal" },
              { "section": "Runtime", "label": "Tipo", "value": "date" },
              { "section": "Runtime", "label": "Obrigatorio", "value": "Sim", "critical": true }
            ]
          },
          {
            "id": "status",
            "field": "status",
            "label": "Status do cliente",
            "type": "enum",
            "defaultValue": "TODOS",
            "technicalProperties": [
              { "section": "Modelo", "label": "Campo", "value": "status" },
              { "section": "Runtime", "label": "Tipo", "value": "enum" },
              { "section": "Processo", "label": "Faixa de opcoes", "value": "TODOS|ATIVO|INATIVO" }
            ],
            "options": [
              { "value": "TODOS", "text": "Todos" },
              { "value": "ATIVO", "text": "Ativo" },
              { "value": "INATIVO", "text": "Inativo" }
            ]
          },
          {
            "id": "resultado",
            "field": "resultado",
            "label": "Retorno esperado",
            "type": "enum",
            "required": true,
            "defaultValue": "grid",
            "description": "Na demo este campo simula os retornos possiveis do backend.",
            "technicalProperties": [
              { "section": "Modelo", "label": "Campo", "value": "resultado" },
              { "section": "Runtime", "label": "Tipo", "value": "enum" },
              { "section": "Processo", "label": "Resultado padrao", "value": "grid|message|report|job" }
            ],
            "options": [
              { "value": "grid", "text": "Grid" },
              { "value": "message", "text": "Mensagem" },
              { "value": "report", "text": "Relatorio em outra pagina" },
              { "value": "job", "text": "Apenas iniciar job" }
            ]
          }
        ]
      },
      "actions": {
        "process": {
          "label": "Processar",
          "icon": "play",
          "permission": "process"
        }
      },
      "wait": {
        "mode": "auto",
        "message": "Processamento iniciado. Aguardando retorno do job...",
        "pollIntervalSeconds": 1,
        "events": {
          "enabled": true
        }
      },
      "result": {
        "type": "grid",
        "openReportInNewTab": false
      }
    }
  };

  embedded.codificacaoAssistentePdmDefinition = {
    "$schema": "../public/metadata/schemas/process-definition-v1.schema.json",
    "schemaVersion": "1.0",
    "pageType": "process",
    "screenId": "assistente.codificacao.produto-pdm",
    "program": {
      "id": "assistente-codificacao-produto-pdm",
      "screenId": "assistente.codificacao.produto-pdm",
      "title": "Assistente de codificacao PDM",
      "subtitle": "Informe as propriedades do produto para montar o codigo.",
      "version": "1.0.0"
    },
    "permissions": {
      "process": true
    },
    "dataSource": {
      "api": {
        "process": {
          "url": "/api/processamento/codificacao/pdm",
          "endpointId": "process",
          "method": "POST"
        }
      }
    },
    "process": {
      "parameters": {
        "title": "Propriedades da codificacao",
        "fields": [
          {
            "id": "familia",
            "field": "familia",
            "label": "Familia",
            "type": "dropdown",
            "required": true,
            "options": [
              { "value": "ELE", "text": "Eletrica" },
              { "value": "HID", "text": "Hidraulica" },
              { "value": "EST", "text": "Estrutural" }
            ]
          },
          {
            "id": "grupo",
            "field": "grupo",
            "label": "Grupo",
            "type": "string",
            "required": true
          },
          {
            "id": "linha",
            "field": "linha",
            "label": "Linha",
            "type": "string",
            "required": true
          }
        ]
      },
      "actions": {
        "process": {
          "label": "Aplicar propriedades",
          "icon": "check",
          "permission": "process"
        }
      },
      "wait": {
        "mode": "none"
      },
      "result": {
        "type": "properties"
      }
    }
  };
})(window);
