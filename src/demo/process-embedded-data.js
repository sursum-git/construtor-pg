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
          "method": "POST"
        },
        "status": {
          "url": "/api/processamento/clientes/status",
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
            "defaultValue": "2026-05-01"
          },
          {
            "id": "data-final",
            "field": "dataFinal",
            "label": "Data final",
            "type": "date",
            "required": true,
            "defaultValue": "2026-05-08"
          },
          {
            "id": "status",
            "field": "status",
            "label": "Status do cliente",
            "type": "enum",
            "defaultValue": "TODOS",
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
})(window);
