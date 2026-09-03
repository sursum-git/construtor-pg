# Contrato para o motor-progress

Este documento define o formato que o motor-progress deve devolver para o construtor renderizar telas dinamicas.

Principio fixo:

```text
Motor-progress/backend decide.
Construtor/frontend renderiza.
```

O construtor nao deve receber JavaScript, `eval`, template livre, URL livre de API ou HTML inseguro. Ele recebe metadados declarativos e chama endpoints fechados por `endpointId` ou `actionId`.

## Fluxo geral

### 1. Carregar definicao da tela

Entrada de producao:

```text
production/app.html?screenId=<screenId>
production/home.html?screenId=<screenId>
```

Requisicao esperada:

```http
GET /api/runtime/screens/{screenId}
```

Resposta:

```json
{
  "schemaVersion": "1.0",
  "pageType": "crud",
  "screenId": "cadastros.produtos",
  "program": {
    "id": "cd0101",
    "screenId": "cadastros.produtos",
    "title": "Produtos",
    "version": "1.0.0"
  },
  "api": {
    "read": { "endpointId": "read", "method": "POST" },
    "get": { "endpointId": "get", "method": "POST" }
  }
}
```

### 2. Executar endpoint da tela

O frontend resolve `endpointId` pelo gateway runtime configurado.

Formato padrao recomendado:

```http
POST /api/runtime/screens/{screenId}/endpoints/{endpointId}
```

Headers enviados pelo frontend:

```text
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>, quando existir
X-Runtime-Tenant-Id: <tenant>
X-Runtime-User-Id: <usuario>
X-Runtime-Session-Id: <sessao>
```

O motor-progress pode ser chamado por tras do PHP. Para o construtor, o PHP continua sendo o middleware que entrega exatamente este contrato.

### 3. Erro padrao

Todos os endpoints devem usar o mesmo envelope de erro:

```json
{
  "error": {
    "code": "ENTITY_METADATA_NOT_CONFIGURED",
    "message": "Metadados da entidade nao configurados.",
    "details": {
      "screenId": "cadastros.produtos",
      "minimumRequired": ["builder_entity", "builder_field", "runtime_endpoint"]
    }
  }
}
```

### 4. Gate geral de renderizacao

O construtor so libera visualmente a tela quando:

- a definicao da tela foi carregada;
- os componentes foram criados;
- os dados iniciais obrigatorios foram carregados;
- nenhum endpoint inicial retornou erro.

Enquanto isso, o root fica em `data-render-state="loading"` e `aria-busy="true"`.

Se houver erro, a tela deve receber erro padrao e o root passa para `data-render-state="error"`.

## Regras comuns para todos os tipos

- `screenId` identifica a tela autorizada.
- `pageType` escolhe o motor fechado do frontend.
- `program` descreve titulo, versao e codigo do programa.
- `api` ou `*.endpoints` aponta apenas para `endpointId`/`actionId`.
- Em producao, nao enviar `url` livre.
- Campos, colunas, filtros, abas, acoes e permissoes devem vir por metadados.
- Validacao real, permissao, tenant, lock, auditoria e regras de negocio ficam no backend.
- O frontend pode validar formato para UX, mas nao deve ser fonte de seguranca.

## Tipos de pagina suportados

| pageType | Uso | Entrada principal |
| --- | --- | --- |
| `crud` | Consulta + formulario de cadastro simples | `production/app.html?screenId=...` |
| `master_detail` | Formulario CRUD com mestre no cabecalho e detalhes em abas | `production/app.html?screenId=...&masterId=...` |
| `process` | Processamento por parametros, job ou retorno fechado | `production/app.html?screenId=...` |
| `analytics` | Consulta BI, grid, grafico, KPI, pivot e dashboard | `production/app.html?screenId=...` |
| `report` | Relatorio operacional/analitico com exportacao | `production/app.html?screenId=...` |
| `special_document` | Documento especial: DANFE, boleto, etiqueta etc. | `production/app.html?screenId=...` |
| `regulated_document` | Documento regulado com preparo, emissao e conferencia | `production/app.html?screenId=...` |
| `custom` | Programa manual autorizado pelo runtime | `production/app.html?screenId=...` |
| `home` | Shell inicial, menu, appbar, notificacoes e abertura de programas | `production/home.html?screenId=...` |

## 1. CRUD

Use `pageType="crud"` para cadastro simples, como produtos e pessoas.

### Definicao minima

```json
{
  "schemaVersion": "1.0",
  "pageType": "crud",
  "screenId": "cadastros.produtos",
  "program": {
    "id": "cd0101",
    "screenId": "cadastros.produtos",
    "title": "Cadastro de Produtos",
    "version": "1.0.0"
  },
  "api": {
    "read": { "endpointId": "read", "method": "POST" },
    "get": { "endpointId": "get", "method": "POST" },
    "create": { "endpointId": "create", "method": "POST" },
    "update": { "endpointId": "update", "method": "POST" },
    "delete": { "endpointId": "delete", "method": "POST" }
  },
  "crud": {
    "idField": "id",
    "grid": {
      "columns": [
        { "field": "codigo", "title": "Codigo", "width": 120 },
        { "field": "descricao", "title": "Descricao" },
        { "field": "preco", "title": "Preco", "type": "decimal" }
      ]
    },
    "form": {
      "tabs": [
        {
          "id": "geral",
          "title": "Geral",
          "fields": [
            { "name": "codigo", "label": "Codigo", "type": "string", "required": true },
            { "name": "descricao", "label": "Descricao", "type": "string", "required": true },
            { "name": "preco", "label": "Preco", "type": "decimal" }
          ]
        }
      ]
    }
  }
}
```

### `read`

Payload recebido:

```json
{
  "page": 1,
  "skip": 0,
  "take": 20,
  "pageSize": 20,
  "sort": [{ "field": "descricao", "dir": "asc" }],
  "filter": null,
  "filters": [
    { "field": "ativo", "operator": "eq", "value": true }
  ]
}
```

Resposta:

```json
{
  "data": [
    { "id": 1, "codigo": "P001", "descricao": "Produto A", "preco": 10.5 }
  ],
  "total": 1
}
```

### `get`

Payload recebido:

```json
{
  "id": 1
}
```

Resposta:

```json
{
  "data": {
    "id": 1,
    "codigo": "P001",
    "descricao": "Produto A",
    "preco": 10.5
  }
}
```

### `create` e `update`

Payload recebido:

```json
{
  "id": 1,
  "values": {
    "codigo": "P001",
    "descricao": "Produto A",
    "preco": 10.5
  },
  "lockToken": "opcional",
  "transactionId": "opcional",
  "version": 3
}
```

Resposta de sucesso:

```json
{
  "data": {
    "id": 1,
    "codigo": "P001",
    "descricao": "Produto A",
    "preco": 10.5,
    "version": 4
  },
  "effects": [
    { "action": "showMessage", "type": "success", "message": "Registro salvo." }
  ]
}
```

Resposta com validacao:

```json
{
  "validation": {
    "valid": false,
    "messages": [
      { "field": "descricao", "type": "error", "message": "Descricao obrigatoria." }
    ]
  }
}
```

### `delete`

Payload recebido:

```json
{
  "id": 1,
  "values": {
    "id": 1
  },
  "lockToken": "opcional",
  "transactionId": "opcional"
}
```

Resposta:

```json
{
  "success": true,
  "effects": [
    { "action": "showMessage", "type": "success", "message": "Registro excluido." }
  ]
}
```

### Lookups e listas de opcao

Quando campo/filtro usar lookup remoto, retornar:

```json
{
  "items": [
    { "value": "SP", "text": "Sao Paulo" }
  ]
}
```

## 2. Master-detail

Use `pageType="master_detail"` quando a tela for um formulario CRUD composto.

Regra importante: a consulta/listagem do mestre nao pertence ao `master_detail`. Ela continua em uma tela `crud` separada. O `master_detail` abre um registro mestre especifico ou cria um novo mestre.

Layout esperado:

- cabecalho com formulario do mestre;
- abas abaixo com uma aba por detalhe;
- cada detalhe possui grid/formulario proprio dentro da aba;
- filhos ficam bloqueados enquanto o mestre ainda nao tiver sido salvo, quando `createFlow.mode="parentFirst"`.

### Definicao minima

```json
{
  "schemaVersion": "1.0",
  "pageType": "master_detail",
  "screenId": "vendas.pedido-master-detail",
  "program": {
    "id": "vd0201",
    "screenId": "vendas.pedido-master-detail",
    "title": "Pedido de Venda"
  },
  "master": {
    "id": "pedido",
    "entity": "pedido_venda",
    "idField": "id",
    "api": {
      "get": { "endpointId": "master.get", "method": "POST" },
      "create": { "endpointId": "master.create", "method": "POST" },
      "update": { "endpointId": "master.update", "method": "POST" },
      "delete": { "endpointId": "master.delete", "method": "POST" }
    },
    "form": {
      "tabs": [
        {
          "id": "cabecalho",
          "title": "Cabecalho",
          "fields": [
            { "name": "numero", "label": "Numero", "type": "string" },
            { "name": "clienteId", "label": "Cliente", "type": "integer" },
            { "name": "dataEmissao", "label": "Data", "type": "date" }
          ]
        }
      ]
    }
  },
  "details": [
    {
      "id": "itens",
      "title": "Itens",
      "entity": "pedido_venda_item",
      "idField": "id",
      "parentField": "pedidoId",
      "api": {
        "read": { "endpointId": "detail.itens.read", "method": "POST" },
        "create": { "endpointId": "detail.itens.create", "method": "POST" },
        "update": { "endpointId": "detail.itens.update", "method": "POST" },
        "delete": { "endpointId": "detail.itens.delete", "method": "POST" }
      },
      "grid": {
        "columns": [
          { "field": "produtoId", "title": "Produto" },
          { "field": "quantidade", "title": "Qtd.", "type": "decimal" },
          { "field": "valorTotal", "title": "Total", "type": "decimal" }
        ]
      },
      "form": {
        "tabs": [
          {
            "id": "item",
            "title": "Item",
            "fields": [
              { "name": "produtoId", "label": "Produto", "type": "integer", "required": true },
              { "name": "quantidade", "label": "Quantidade", "type": "decimal", "required": true }
            ]
          }
        ]
      }
    }
  ],
  "createFlow": {
    "mode": "parentFirst"
  }
}
```

### Abrir registro existente

URL:

```text
production/app.html?screenId=vendas.pedido-master-detail&masterId=101
```

O frontend tambem aceita `recordId` ou `id`.

Payload enviado para `master.get`:

```json
{
  "id": 101
}
```

Quando `master.idField` tiver outro nome, o frontend tambem envia esse campo. Exemplo com `idField="pedidoId"`:

```json
{
  "id": 101,
  "pedidoId": 101
}
```

Resposta:

```json
{
  "data": {
    "id": 101,
    "numero": "PV-000101",
    "clienteId": 45,
    "dataEmissao": "2026-09-03"
  }
}
```

### Carregar detalhes

Payload enviado para cada `detail.*.read`:

```json
{
  "skip": 0,
  "take": 500,
  "pageSize": 500,
  "filters": [
    {
      "id": "pedidoId",
      "field": "pedidoId",
      "operator": "eq",
      "value": 101
    }
  ],
  "sort": []
}
```

Resposta:

```json
{
  "data": [
    { "id": 1, "pedidoId": 101, "produtoId": 10, "quantidade": 2, "valorTotal": 80 }
  ],
  "total": 1
}
```

### Gravar filhos

Ao incluir filho, o frontend injeta `parentField` com o id do mestre antes de chamar o backend.

Payload:

```json
{
  "values": {
    "pedidoId": 101,
    "produtoId": 10,
    "quantidade": 2
  }
}
```

### Inclusao conjunta: `draftWithChildren`

Quando `createFlow.mode="draftWithChildren"`, o backend deve publicar endpoint transacional `createGraph`.

Definicao:

```json
{
  "createFlow": {
    "mode": "draftWithChildren",
    "api": {
      "createGraph": { "endpointId": "createGraph", "method": "POST" }
    }
  }
}
```

Payload:

```json
{
  "screenId": "vendas.pedido-master-detail",
  "mode": "draftWithChildren",
  "master": {
    "numero": "PV-000102",
    "clienteId": 45
  },
  "values": {
    "numero": "PV-000102",
    "clienteId": 45
  },
  "details": {
    "itens": [
      { "produtoId": 10, "quantidade": 2 }
    ],
    "parcelas": [
      { "vencimento": "2026-10-03", "valor": 80 }
    ]
  }
}
```

Resposta:

```json
{
  "master": {
    "id": 102,
    "numero": "PV-000102",
    "clienteId": 45
  },
  "details": {
    "itens": [
      { "id": 1, "pedidoId": 102, "produtoId": 10, "quantidade": 2 }
    ],
    "parcelas": [
      { "id": 1, "pedidoId": 102, "vencimento": "2026-10-03", "valor": 80 }
    ]
  }
}
```

O backend deve gravar mestre e filhos na mesma transacao.

## 3. Process

Use `pageType="process"` para telas de parametros e execucao.

### Definicao minima

```json
{
  "schemaVersion": "1.0",
  "pageType": "process",
  "screenId": "processamento.relatorio-clientes",
  "program": {
    "id": "pr0101",
    "title": "Processamento de Clientes"
  },
  "process": {
    "parameters": {
      "fields": [
        { "name": "dataInicial", "label": "Data inicial", "type": "date", "required": true },
        { "name": "dataFinal", "label": "Data final", "type": "date", "required": true }
      ]
    },
    "endpoints": {
      "process": { "endpointId": "process", "method": "POST" },
      "status": { "endpointId": "status", "method": "POST" }
    },
    "wait": {
      "mode": "auto",
      "pollIntervalSeconds": 2
    },
    "result": {
      "type": "message"
    }
  }
}
```

### `process`

Payload:

```json
{
  "parameters": {
    "dataInicial": "2026-09-01",
    "dataFinal": "2026-09-30"
  },
  "screenId": "processamento.relatorio-clientes"
}
```

Resposta imediata com mensagem:

```json
{
  "status": "completed",
  "message": "Processamento concluido.",
  "result": {
    "type": "message",
    "message": "25 clientes processados."
  }
}
```

Resposta com grid:

```json
{
  "status": "completed",
  "result": {
    "type": "grid",
    "title": "Resultado",
    "columns": [
      { "field": "cliente", "title": "Cliente" },
      { "field": "valor", "title": "Valor", "type": "decimal" }
    ],
    "data": [
      { "cliente": "Cliente A", "valor": 120 }
    ],
    "pageSize": 10
  }
}
```

Resposta com job:

```json
{
  "status": "queued",
  "message": "Processamento iniciado.",
  "job": {
    "id": "job-123",
    "status": "queued",
    "type": "clientes.processamento",
    "title": "Processamento de Clientes"
  },
  "wait": {
    "mode": "polling",
    "pollIntervalSeconds": 2
  },
  "result": {
    "type": "job",
    "message": "Acompanhe o andamento do job.",
    "job": {
      "id": "job-123"
    }
  }
}
```

### `status`

Payload:

```json
{
  "jobId": "job-123",
  "id": "job-123",
  "parameters": {
    "dataInicial": "2026-09-01",
    "dataFinal": "2026-09-30"
  }
}
```

Resposta:

```json
{
  "status": "completed",
  "message": "Job concluido.",
  "job": {
    "id": "job-123",
    "status": "completed"
  },
  "result": {
    "type": "report",
    "title": "Relatorio gerado",
    "message": "Arquivo disponivel.",
    "url": "/api/runtime/documents/job-123/resultado.pdf",
    "linkText": "Abrir relatorio"
  }
}
```

Tipos finais: `completed`, `done`, `success`, `failed`, `error`, `cancelled`, `canceled`.

## 4. Analytics

Use `pageType="analytics"` para consultas BI.

### Definicao minima

```json
{
  "schemaVersion": "1.0",
  "pageType": "analytics",
  "screenId": "analytics.vendas",
  "program": {
    "id": "an0101",
    "title": "Analise de Vendas"
  },
  "analytics": {
    "datasets": [
      {
        "id": "vendas_mes",
        "title": "Vendas por mes",
        "limit": 1000,
        "parameters": [
          { "name": "ano", "label": "Ano", "type": "integer" }
        ]
      }
    ],
    "views": [
      { "id": "grid", "type": "grid", "title": "Dados", "datasetId": "vendas_mes" },
      { "id": "grafico", "type": "chart", "title": "Grafico", "datasetId": "vendas_mes" }
    ],
    "endpoints": {
      "run": { "endpointId": "analytics.query.run", "method": "POST" },
      "materialize": { "endpointId": "analytics.materialize", "method": "POST" },
      "cacheStatus": { "endpointId": "analytics.cache.status", "method": "POST" }
    }
  }
}
```

### `analytics.query.run`

Payload:

```json
{
  "datasetId": "vendas_mes",
  "parameters": {
    "ano": 2026
  },
  "take": 1000
}
```

Resposta:

```json
{
  "columns": [
    { "field": "mes", "title": "Mes", "role": "dimension", "type": "string" },
    { "field": "valor", "title": "Valor", "role": "measure", "type": "decimal" }
  ],
  "data": [
    { "mes": "2026-09", "valor": 15000 }
  ],
  "total": 1,
  "_runtime": {
    "analyticsCache": {
      "source": "live",
      "generatedAt": "2026-09-03T10:00:00-03:00"
    }
  }
}
```

### `analytics.materialize`

Payload:

```json
{
  "datasetId": "vendas_mes",
  "parameters": {
    "ano": 2026
  }
}
```

Resposta:

```json
{
  "message": "Atualizacao de cache solicitada.",
  "job": {
    "id": "job-analytics-1",
    "status": "queued"
  }
}
```

## 5. Report

Use `pageType="report"` para relatorios tabulares, operacionais ou analiticos.

### Definicao minima

```json
{
  "schemaVersion": "1.0",
  "pageType": "report",
  "screenId": "relatorios.clientes-operacional",
  "program": {
    "id": "rp0101",
    "title": "Relatorio de Clientes"
  },
  "report": {
    "source": { "type": "operational" },
    "query": {
      "limit": 200,
      "parameters": [
        { "id": "cidade", "label": "Cidade", "type": "string" }
      ],
      "sort": [
        { "field": "nome", "dir": "asc" }
      ]
    },
    "outputs": {
      "csv": true,
      "excel": true,
      "pdf": true
    },
    "endpoints": {
      "run": { "endpointId": "reports.run", "method": "POST" },
      "export": { "endpointId": "reports.export", "method": "POST" }
    }
  }
}
```

### `reports.run`

Payload:

```json
{
  "reportId": "rp0101",
  "sourceType": "operational",
  "parameters": {
    "cidade": "Fortaleza"
  },
  "sort": [
    { "field": "nome", "dir": "asc" }
  ],
  "limit": 200
}
```

Resposta:

```json
{
  "columns": [
    { "field": "nome", "title": "Nome", "type": "string" },
    { "field": "cidade", "title": "Cidade", "type": "string" }
  ],
  "rows": [
    { "nome": "Cliente A", "cidade": "Fortaleza" }
  ],
  "total": 1,
  "summary": [
    { "label": "Clientes", "value": 1 }
  ],
  "totals": []
}
```

Tambem pode usar `data` no lugar de `rows`.

### `reports.export`

Payload:

```json
{
  "reportId": "rp0101",
  "sourceType": "operational",
  "parameters": {
    "cidade": "Fortaleza"
  },
  "sort": [
    { "field": "nome", "dir": "asc" }
  ],
  "limit": 200,
  "format": "pdf",
  "deliveryMode": "download"
}
```

Resposta obrigatoria:

```json
{
  "fileName": "relatorio-clientes.pdf",
  "contentType": "application/pdf",
  "contentBase64": "JVBERi0xLjQK...",
  "format": "pdf"
}
```

Quando houver autenticidade:

```json
{
  "fileName": "relatorio-clientes.pdf",
  "contentType": "application/pdf",
  "contentBase64": "JVBERi0xLjQK...",
  "format": "pdf",
  "authenticity": {
    "hash": "sha256:...",
    "footerLabel": "Codigo de autenticidade"
  }
}
```

## 6. Special document

Use `pageType="special_document"` para documentos especiais com renderer fechado, como DANFE, boleto e etiqueta.

### Definicao minima

```json
{
  "schemaVersion": "1.0",
  "pageType": "special_document",
  "screenId": "documentos.danfe",
  "program": {
    "id": "dc0101",
    "title": "DANFE"
  },
  "specialDocument": {
    "classification": {
      "documentKind": "danfe"
    },
    "source": {
      "type": "operational"
    },
    "renderEngine": "native",
    "parameters": [
      { "name": "notaFiscalId", "label": "Nota fiscal", "type": "integer", "required": true }
    ],
    "outputs": {
      "html": true,
      "pdf": true
    },
    "endpoints": {
      "render": { "endpointId": "specialDocuments.render", "method": "POST" },
      "export": { "endpointId": "specialDocuments.export", "method": "POST" }
    }
  }
}
```

### `specialDocuments.render`

Payload:

```json
{
  "documentId": "dc0101",
  "sourceType": "operational",
  "documentKind": "danfe",
  "renderEngine": "native",
  "parameters": {
    "notaFiscalId": 1001
  }
}
```

Resposta:

```json
{
  "title": "DANFE",
  "subtitle": "NF-e 1001",
  "sections": [
    {
      "type": "header",
      "title": "Emitente",
      "fields": [
        { "label": "Razao social", "value": "Empresa Exemplo" }
      ]
    },
    {
      "type": "table",
      "title": "Itens",
      "columns": [
        { "field": "produto", "title": "Produto" },
        { "field": "valor", "title": "Valor", "type": "decimal" }
      ],
      "rows": [
        { "produto": "Produto A", "valor": 80 }
      ],
      "rowCount": 1
    }
  ],
  "totals": [
    { "label": "Total", "value": 80 }
  ]
}
```

### `specialDocuments.export`

Payload:

```json
{
  "format": "pdf",
  "documentKind": "danfe",
  "parameters": {
    "notaFiscalId": 1001
  },
  "deliveryMode": "download"
}
```

Resposta:

```json
{
  "fileName": "danfe-1001.pdf",
  "contentType": "application/pdf",
  "contentBase64": "JVBERi0xLjQK...",
  "format": "pdf"
}
```

## 7. Regulated document

Use `pageType="regulated_document"` para documentos que precisam de payload canonico, emissao controlada, hash e conferencia posterior.

### Definicao minima

```json
{
  "schemaVersion": "1.0",
  "pageType": "regulated_document",
  "screenId": "documentos.nfe-regulada",
  "program": {
    "id": "dc0201",
    "title": "Documento Regulado"
  },
  "regulatedDocument": {
    "documentType": "nfe",
    "track": "fiscal",
    "parameters": [
      { "name": "notaFiscalId", "label": "Nota fiscal", "type": "integer", "required": true }
    ],
    "endpoints": {
      "prepare": { "endpointId": "regulatedDocuments.prepare", "method": "POST" },
      "render": { "endpointId": "regulatedDocuments.render", "method": "POST" },
      "issue": { "endpointId": "regulatedDocuments.issue", "method": "POST" },
      "verify": { "endpointId": "regulatedDocuments.verify", "method": "POST" },
      "artifact": { "endpointId": "regulatedDocuments.artifact", "method": "POST" }
    }
  }
}
```

### `regulatedDocuments.prepare`

Payload:

```json
{
  "issueId": "",
  "parameters": {
    "notaFiscalId": 1001
  },
  "documentType": "nfe",
  "track": "fiscal"
}
```

Resposta:

```json
{
  "ok": true,
  "issueId": "issue-1001",
  "state": "prepared",
  "canonicalPayload": {
    "documentType": "nfe",
    "number": "1001"
  },
  "validations": []
}
```

### `regulatedDocuments.render`

Payload:

```json
{
  "issueId": "issue-1001",
  "parameters": {
    "notaFiscalId": 1001
  }
}
```

Resposta:

```json
{
  "issueId": "issue-1001",
  "state": "rendered",
  "title": "Preview NF-e",
  "sections": [
    {
      "type": "table",
      "title": "Itens",
      "columns": [
        { "field": "produto", "title": "Produto" }
      ],
      "rows": [
        { "produto": "Produto A" }
      ]
    }
  ],
  "validations": []
}
```

### `regulatedDocuments.issue`

Payload:

```json
{
  "issueId": "issue-1001",
  "parameters": {
    "notaFiscalId": 1001
  },
  "format": "pdf"
}
```

Resposta:

```json
{
  "issueId": "issue-1001",
  "state": "issued",
  "hash": "sha256:...",
  "artifact": {
    "fileName": "nfe-1001.pdf",
    "contentType": "application/pdf",
    "contentBase64": "JVBERi0xLjQK...",
    "format": "pdf"
  }
}
```

### `regulatedDocuments.verify`

Payload:

```json
{
  "issueId": "issue-1001",
  "hash": "sha256:..."
}
```

Resposta:

```json
{
  "ok": true,
  "issueId": "issue-1001",
  "state": "issued",
  "message": "Documento conferido."
}
```

### `regulatedDocuments.artifact`

Payload:

```json
{
  "issueId": "issue-1001"
}
```

Resposta:

```json
{
  "fileName": "nfe-1001.pdf",
  "contentType": "application/pdf",
  "contentBase64": "JVBERi0xLjQK...",
  "format": "pdf"
}
```

## 8. Custom

Use `pageType="custom"` apenas para programa manual autorizado.

Definicao:

```json
{
  "schemaVersion": "1.0",
  "pageType": "custom",
  "screenId": "custom.programa-manual",
  "program": {
    "id": "cu0101",
    "title": "Programa Manual"
  },
  "custom": {
    "mode": "iframe",
    "entryUrl": "../production/custom/programa-manual-demo.html",
    "frameTitle": "Programa Manual"
  }
}
```

Regras:

- `custom.mode` aceita `iframe` ou `htmlUrl`.
- `custom.entryUrl` deve ser relativo ao proprio sistema.
- Nao usar URL absoluta externa.
- Nao enviar script pelo JSON.
- Em `htmlUrl`, o frontend sanitiza o HTML e remove scripts/eventos.

## 9. Home

Use `pageType="home"` para o shell inicial.

Entrada:

```text
production/home.html?screenId=home.principal
```

Definicao minima:

```json
{
  "schemaVersion": "1.0",
  "pageType": "home",
  "screenId": "home.principal",
  "app": {
    "title": "Construtor",
    "subtitle": "Sistema principal",
    "logo": { "url": "../assets/logo.png" }
  },
  "currentSubscriber": {
    "id": "principal",
    "name": "Principal",
    "kind": "main"
  },
  "navigation": {
    "systems": [
      {
        "id": "erp",
        "title": "ERP",
        "modules": [
          {
            "id": "cadastros",
            "title": "Cadastros",
            "programs": [
              {
                "id": "cd0101",
                "title": "Produtos",
                "type": "crud",
                "screenId": "cadastros.produtos"
              }
            ]
          }
        ]
      }
    ]
  },
  "layout": {
    "appbar": {
      "notifications": {
        "enabled": true,
        "endpoints": {
          "list": { "endpointId": "home.notifications.list", "method": "POST" },
          "ack": { "endpointId": "home.notifications.ack", "method": "POST" }
        }
      },
      "jobs": {
        "enabled": true,
        "endpoint": { "endpointId": "home.jobs", "method": "POST" }
      }
    }
  }
}
```

Programas do menu podem abrir por tipos fechados:

- `crud`;
- `master_detail`;
- `process`;
- `analytics`;
- `report`;
- `special_document`;
- `regulated_document`;
- `custom`;
- `iframe`;
- `html` sanitizado.

### Notificacoes

Payload de listagem:

```json
{
  "filters": {
    "unreadOnly": true,
    "severity": "warning"
  }
}
```

Resposta:

```json
{
  "items": [
    {
      "id": "notif-1",
      "title": "Aprovacao pendente",
      "message": "Existe uma publicacao aguardando aprovacao.",
      "severity": "warning",
      "category": "governance",
      "read": false,
      "createdAt": "2026-09-03T10:00:00-03:00",
      "navigation": {
        "screenId": "admin.programa-aprovacoes-operacao",
        "query": {
          "requestCode": "REQ-1"
        }
      }
    }
  ],
  "total": 1,
  "unread": 1
}
```

Payload para marcar leitura:

```json
{
  "id": "notif-1"
}
```

Resposta:

```json
{
  "success": true
}
```

## Efeitos seguros aceitos pelo frontend

Endpoints de formulario, processamento e regras podem devolver `effects`.

Efeitos aceitos:

- `setValue`;
- `clearValue`;
- `readonly`;
- `enabled`;
- `disabled`;
- `visible`;
- `show`;
- `hide`;
- `required`;
- `setOptions`;
- `reloadOptions`;
- `showMessage`.

Exemplo:

```json
{
  "effects": [
    { "action": "setValue", "field": "cidade", "value": "Fortaleza" },
    { "action": "showMessage", "type": "info", "message": "Cidade sugerida." }
  ]
}
```

## Checklist para o motor-progress

Para cada tela gerada, o motor-progress deve garantir:

- criar `screenId` estavel;
- preencher `pageType` correto;
- gerar JSON declarativo compativel com o schema da tela;
- publicar endpoints fechados usados pela definicao;
- nao retornar URL livre em producao;
- nao retornar JavaScript ou template livre;
- aplicar permissao por usuario, grupo, tenant, tela e endpoint;
- filtrar campos gravaveis no backend;
- retornar erros no envelope padrao;
- respeitar transacao unica no `createGraph`;
- devolver `contentBase64` em exportacoes;
- devolver dados iniciais obrigatorios antes de considerar a tela pronta;
- versionar definicao publicada para rastreabilidade.

## Papel do PHP middleware

Quando o backend real for Progress:

1. O frontend chama apenas o PHP.
2. O PHP valida sessao, tenant, permissao e endpoint.
3. O PHP chama o motor-progress.
4. O PHP normaliza a resposta para este contrato.
5. O frontend renderiza sem conhecer detalhes do Progress.

Quando os dados vierem diretamente do API Platform:

1. O frontend continua chamando os mesmos endpoints runtime.
2. O PHP resolve os dados pelo API Platform.
3. O formato devolvido continua igual.

Assim, o construtor nao precisa mudar quando a origem for Progress ou API Platform.
