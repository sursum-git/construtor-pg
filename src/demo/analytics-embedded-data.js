(function(global) {
  "use strict";

  global.AnalyticsDemoEmbedded = {
    clientesDefinition: {
      "$schema": "public/metadata/schemas/analytics-definition-v1.schema.json",
      "schemaVersion": "1.0",
      "pageType": "analytics",
      "screenId": "analytics.clientes",
      "program": {
        "id": "analytics-clientes",
        "module": "analytics",
        "title": "BI de Clientes",
        "version": "1.0.0",
        "subtitle": "Indicadores por UF e status usando fonte interna cadastrada"
      },
      "permissions": {
        "read": true,
        "materialize": true
      },
      "dataSource": {
        "api": {
          "schema": { "endpointId": "analytics.schema", "method": "POST" },
          "run": { "endpointId": "analytics.query.run", "method": "POST" },
          "materialize": { "endpointId": "analytics.materialize", "method": "POST" },
          "cacheStatus": { "endpointId": "analytics.cache.status", "method": "POST" },
          "pipelineSchema": { "endpointId": "analytics.pipeline.schema", "method": "POST" },
          "pipelinePreview": { "endpointId": "analytics.pipeline.preview", "method": "POST" },
          "pipelineRun": { "endpointId": "analytics.pipeline.run", "method": "POST" },
          "pipelinePublish": { "endpointId": "analytics.pipeline.publish", "method": "POST" },
          "pipelineStatus": { "endpointId": "analytics.pipeline.status", "method": "POST" },
          "pipelineLogs": { "endpointId": "analytics.pipeline.logs", "method": "POST" },
          "pipelineVersions": { "endpointId": "analytics.pipeline.versions", "method": "POST" },
          "pipelineRollback": { "endpointId": "analytics.pipeline.rollback", "method": "POST" }
        }
      },
      "analytics": {
        "version": "1.0",
        "endpoints": {
          "schema": { "endpointId": "analytics.schema", "method": "POST" },
          "run": { "endpointId": "analytics.query.run", "method": "POST" },
          "materialize": { "endpointId": "analytics.materialize", "method": "POST" },
          "cacheStatus": { "endpointId": "analytics.cache.status", "method": "POST" },
          "pipelineSchema": { "endpointId": "analytics.pipeline.schema", "method": "POST" },
          "pipelinePreview": { "endpointId": "analytics.pipeline.preview", "method": "POST" },
          "pipelineRun": { "endpointId": "analytics.pipeline.run", "method": "POST" },
          "pipelinePublish": { "endpointId": "analytics.pipeline.publish", "method": "POST" },
          "pipelineStatus": { "endpointId": "analytics.pipeline.status", "method": "POST" },
          "pipelineLogs": { "endpointId": "analytics.pipeline.logs", "method": "POST" },
          "pipelineVersions": { "endpointId": "analytics.pipeline.versions", "method": "POST" },
          "pipelineRollback": { "endpointId": "analytics.pipeline.rollback", "method": "POST" }
        },
        "datasets": [
          {
            "id": "clientes-uf-status",
            "title": "Clientes por UF e status",
            "source": {
              "type": "pipeline_published",
              "pipelineId": "clientes_uf_status",
              "publishedDatasetId": "clientes_uf_status_published"
            },
            "fields": [
              { "id": "uf", "field": "uf", "label": "UF", "type": "string" },
              { "id": "status", "field": "status", "label": "Status", "type": "enum" },
              { "id": "valor_total", "field": "valor_total", "label": "Valor total", "type": "currency", "format": "c2" },
              { "id": "qtde_pedidos", "field": "qtde_pedidos", "label": "Pedidos", "type": "integer" }
            ],
            "dimensions": [
              { "id": "uf", "field": "uf", "label": "UF", "type": "string" },
              { "id": "status", "field": "status", "label": "Status", "type": "enum" }
            ],
            "measures": [
              { "id": "clientes", "label": "Clientes", "aggregate": "count" },
              { "id": "valor_total_sum", "field": "valor_total", "label": "Valor total", "aggregate": "sum", "format": "c2" },
              { "id": "qtde_pedidos_sum", "field": "qtde_pedidos", "label": "Pedidos", "aggregate": "sum", "format": "n0" }
            ],
            "parameters": [
              {
                "id": "status",
                "field": "status",
                "label": "Status",
                "type": "enum",
                "operator": "eq",
                "options": [
                  { "value": "ATIVO", "text": "Ativo" },
                  { "value": "INATIVO", "text": "Inativo" }
                ]
              },
              { "id": "uf", "field": "uf", "label": "UF", "type": "text", "operator": "eq" }
            ],
            "defaultSort": [{ "field": "uf", "dir": "asc" }],
            "limit": 1000,
            "executionMode": "auto",
            "cache": { "ttlSeconds": 900 }
          }
        ],
        "semanticPipelines": [
          {
            "id": "clientes_base",
            "title": "Base operacional",
            "enabled": true,
            "sourceEntityCode": "cliente",
            "steps": [
              {
                "id": "select_base",
                "type": "select",
                "fields": [
                  { "from": "uf", "as": "uf", "label": "UF", "role": "dimension", "type": "string" },
                  { "from": "status", "as": "status", "label": "Status", "role": "dimension", "type": "enum" },
                  { "from": "valor_total", "as": "valor_total", "label": "Valor total", "role": "measure", "type": "currency", "format": "c2" },
                  { "from": "qtde_pedidos", "as": "qtde_pedidos", "label": "Pedidos", "role": "measure", "type": "integer", "format": "n0" },
                  { "from": "id", "as": "id", "label": "ID", "role": "field", "type": "integer" }
                ]
              },
              { "id": "publish_base", "type": "publish", "title": "Base clientes" }
            ],
            "publishConfig": { "publishedDatasetId": "clientes_base_published", "title": "Base clientes" },
            "schedule": { "mode": "manual" },
            "retention": { "keepDays": 30 }
          },
          {
            "id": "clientes_uf_status",
            "title": "Clientes por UF e status",
            "enabled": true,
            "sourcePipelineId": "clientes_base",
            "steps": [
              {
                "id": "group_uf_status",
                "type": "group",
                "dimensions": ["uf", "status"],
                "measures": [
                  { "field": "id", "aggregate": "count", "id": "clientes", "label": "Clientes" },
                  { "field": "valor_total", "aggregate": "sum", "id": "valor_total_sum", "label": "Valor total", "format": "c2" },
                  { "field": "qtde_pedidos", "aggregate": "sum", "id": "qtde_pedidos_sum", "label": "Pedidos", "format": "n0" }
                ]
              },
              { "id": "sort_uf_status", "type": "sort", "sort": [{ "field": "uf", "dir": "asc" }, { "field": "status", "dir": "asc" }] },
              { "id": "publish_final", "type": "publish", "title": "Clientes por UF e status" }
            ],
            "publishConfig": { "publishedDatasetId": "clientes_uf_status_published", "title": "Clientes por UF e status" },
            "schedule": { "mode": "manual" },
            "retention": { "keepDays": 30 }
          },
          {
            "id": "clientes_ce_brutos",
            "title": "Clientes CE brutos",
            "enabled": true,
            "sourcePipelineId": "clientes_base",
            "steps": [
              { "id": "filter_ce", "type": "filter", "filters": [{ "field": "uf", "operator": "eq", "value": "CE" }] },
              { "id": "publish_ce", "type": "publish", "title": "Clientes CE brutos" }
            ],
            "publishConfig": { "publishedDatasetId": "clientes_ce_brutos_published", "title": "Clientes CE brutos" },
            "schedule": { "mode": "manual" },
            "retention": { "keepDays": 30 }
          },
          {
            "id": "clientes_sp_brutos",
            "title": "Clientes SP brutos",
            "enabled": true,
            "sourcePipelineId": "clientes_base",
            "steps": [
              { "id": "filter_sp", "type": "filter", "filters": [{ "field": "uf", "operator": "eq", "value": "SP" }] },
              { "id": "publish_sp", "type": "publish", "title": "Clientes SP brutos" }
            ],
            "publishConfig": { "publishedDatasetId": "clientes_sp_brutos_published", "title": "Clientes SP brutos" },
            "schedule": { "mode": "manual" },
            "retention": { "keepDays": 30 }
          },
          {
            "id": "clientes_ce_sp_union",
            "title": "Clientes CE + SP",
            "enabled": true,
            "sourcePipelineId": "clientes_ce_brutos",
            "steps": [
              { "id": "union_sp", "type": "union", "sourcePipelineId": "clientes_sp_brutos" },
              { "id": "derive_status_upper", "type": "derive", "operation": "upper", "targetField": "status_maiusculo", "sourceField": "status" },
              { "id": "publish_union", "type": "publish", "title": "Clientes CE + SP" }
            ],
            "publishConfig": { "publishedDatasetId": "clientes_ce_sp_union_published", "title": "Clientes CE + SP" },
            "schedule": { "mode": "manual" },
            "retention": { "keepDays": 30 }
          },
          {
            "id": "clientes_uf_having",
            "title": "UFs com having",
            "enabled": true,
            "sourcePipelineId": "clientes_base",
            "steps": [
              {
                "id": "group_uf_having",
                "type": "group",
                "dimensions": ["uf"],
                "measures": [
                  { "field": "id", "aggregate": "count", "id": "clientes", "label": "Clientes" },
                  { "field": "valor_total", "aggregate": "sum", "id": "valor_total_sum", "label": "Valor total", "format": "c2" }
                ]
              },
              { "id": "having_clientes", "type": "having", "filters": [{ "field": "clientes", "operator": "gte", "value": 2 }] },
              { "id": "publish_having", "type": "publish", "title": "UFs com having" }
            ],
            "publishConfig": { "publishedDatasetId": "clientes_uf_having_published", "title": "UFs com having" },
            "schedule": { "mode": "manual" },
            "retention": { "keepDays": 30 }
          }
        ],
        "ingestionPipelines": [],
        "views": [
          { "id": "grid", "type": "grid", "title": "Grid", "datasetId": "clientes-uf-status" },
          {
            "id": "chart",
            "type": "chart",
            "title": "Grafico",
            "datasetId": "clientes-uf-status",
            "categoryField": "uf",
            "valueField": "valor_total_sum",
            "seriesType": "column",
            "valueFormat": "c2"
          },
          { "id": "pivot", "type": "pivot", "title": "Pivot", "datasetId": "clientes-uf-status" },
          {
            "id": "dashboard",
            "type": "dashboard",
            "title": "Dashboard",
            "datasetId": "clientes-uf-status",
            "tiles": [
              { "id": "clientes", "type": "kpi", "title": "Clientes", "valueField": "clientes", "format": "n0" },
              { "id": "valor", "type": "kpi", "title": "Valor total", "valueField": "valor_total_sum", "format": "c2" },
              { "id": "grafico", "type": "chart", "title": "Valor por UF", "categoryField": "uf", "valueField": "valor_total_sum", "seriesType": "column", "valueFormat": "c2" }
            ]
          }
        ]
      }
    }
  };
})(window);
