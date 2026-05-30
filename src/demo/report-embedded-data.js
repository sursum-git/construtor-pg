(function(global) {
  "use strict";

  global.ReportDemoEmbedded = {
    operationalDefinition: {
      "$schema": "public/metadata/schemas/report-definition-v1.schema.json",
      "schemaVersion": "1.0",
      "pageType": "report",
      "screenId": "relatorios.clientes-operacional",
      "program": {
        "id": "relatorio-clientes-operacional",
        "module": "relatorios",
        "title": "Relatorio operacional de clientes",
        "version": "1.0.0",
        "subtitle": "Listagem detalhada com filtros, totais e impressao"
      },
      "permissions": { "read": true, "export": true },
      "dataSource": {
        "api": {
          "schema": { "endpointId": "reports.schema", "method": "POST" },
          "run": { "endpointId": "reports.run", "method": "POST" },
          "export": { "endpointId": "reports.export", "method": "POST" }
        }
      },
      "report": {
        "version": "1.0",
        "classification": { "documentProfile": "general", "documentKind": "purchase_order" },
        "endpoints": {
          "schema": { "endpointId": "reports.schema", "method": "POST" },
          "run": { "endpointId": "reports.run", "method": "POST" },
          "export": { "endpointId": "reports.export", "method": "POST" }
        },
        "source": { "type": "operational", "entityCode": "cliente" },
        "query": {
          "fields": [
            { "field": "nome", "label": "Nome" },
            { "field": "uf", "label": "UF" },
            { "field": "status", "label": "Status" },
            { "field": "valor_total", "label": "Valor total", "type": "currency", "format": "c2", "align": "right", "totalable": true },
            { "field": "qtde_pedidos", "label": "Pedidos", "type": "integer", "format": "n0", "align": "right", "totalable": true }
          ],
          "parameters": [
            { "id": "status", "field": "status", "label": "Status", "type": "enum", "operator": "eq", "options": [{ "value": "ATIVO", "text": "Ativo" }, { "value": "INATIVO", "text": "Inativo" }] },
            { "id": "uf", "field": "uf", "label": "UF", "type": "text", "operator": "eq" }
          ],
          "sort": [{ "field": "nome", "dir": "asc" }],
          "limit": 200
        },
        "layout": {
          "title": "Relatorio operacional de clientes",
          "subtitle": "Exemplo nativo de emissao HTML",
          "footerText": "Somente documentos compativeis com a v1 entram em reports.",
          "blocks": [
            { "id": "header", "type": "header" },
            { "id": "summary", "type": "summary" },
            { "id": "table", "type": "table" },
            { "id": "totals", "type": "totals" },
            { "id": "footer", "type": "footer" }
          ]
        },
        "authenticity": {
          "enabled": true,
          "algorithm": "sha256",
          "footerLabel": "Codigo de autenticidade",
          "verificationPath": "report-authenticity.html",
          "storage": {
            "storeCanonicalPayload": true,
            "storeExportArtifact": true
          }
        },
        "printing": {
          "deliveryMode": "download",
          "qzTray": {
            "enabled": true,
            "printerName": "IMP-LOCAL-01",
            "jobName": "Relatorio operacional de clientes",
            "copies": 1
          }
        },
        "outputs": { "html": true, "print": true, "pdfBrowser": true, "excel": true, "csv": true }
      }
    },
    analyticDefinition: {
      "$schema": "public/metadata/schemas/report-definition-v1.schema.json",
      "schemaVersion": "1.0",
      "pageType": "report",
      "screenId": "relatorios.clientes-analitico",
      "program": {
        "id": "relatorio-clientes-analitico",
        "module": "relatorios",
        "title": "Relatorio analitico por UF",
        "version": "1.0.0",
        "subtitle": "Usa dataset analytics interno com agrupamento"
      },
      "permissions": { "read": true, "export": true },
      "dataSource": {
        "api": {
          "schema": { "endpointId": "reports.schema", "method": "POST" },
          "run": { "endpointId": "reports.run", "method": "POST" },
          "export": { "endpointId": "reports.export", "method": "POST" }
        }
      },
      "report": {
        "version": "1.0",
        "classification": { "documentProfile": "general", "documentKind": "management" },
        "endpoints": {
          "schema": { "endpointId": "reports.schema", "method": "POST" },
          "run": { "endpointId": "reports.run", "method": "POST" },
          "export": { "endpointId": "reports.export", "method": "POST" }
        },
        "source": { "type": "analytic", "analyticsScreenId": "analytics.clientes", "analyticsDatasetId": "clientes-uf-status" },
        "query": {
          "fields": [],
          "parameters": [
            { "id": "status", "field": "status", "label": "Status", "type": "enum", "operator": "eq", "options": [{ "value": "ATIVO", "text": "Ativo" }, { "value": "INATIVO", "text": "Inativo" }] }
          ],
          "sort": [{ "field": "uf", "dir": "asc" }],
          "limit": 1000
        },
        "layout": {
          "title": "Relatorio analitico por UF",
          "subtitle": "Agrupamento simples sobre dataset analytics",
          "groupField": "uf",
          "footerText": "Agrupamento unico e totais por medida na v1.",
          "blocks": [
            { "id": "header", "type": "header" },
            { "id": "summary", "type": "summary" },
            { "id": "group", "type": "group" },
            { "id": "totals", "type": "totals" },
            { "id": "footer", "type": "footer" }
          ]
        },
        "authenticity": {
          "enabled": true,
          "algorithm": "sha256",
          "footerLabel": "Codigo de autenticidade",
          "verificationPath": "report-authenticity.html",
          "storage": {
            "storeCanonicalPayload": true,
            "storeExportArtifact": false
          }
        },
        "printing": {
          "deliveryMode": "download",
          "qzTray": {
            "enabled": true,
            "printerName": "IMP-LOCAL-01",
            "jobName": "Relatorio analitico por UF",
            "copies": 1
          }
        },
        "outputs": { "html": true, "print": true, "pdfBrowser": true, "excel": true, "csv": true }
      }
    }
  };
})(window);
