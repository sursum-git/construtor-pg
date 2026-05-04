# Especificação inicial — Motor de CRUD Dinâmico com JSON, Kendo UI e jQuery

Versão: `0.1.0`  
Objetivo: servir como documentação/prompt técnico para implementação no Codex.  
Escopo: primeira versão do motor renderizador de CRUD, sem tratar internamente campos customizados de persistência. Para o motor, todo campo recebido no JSON já é um campo normal e renderizável.

---

## 1. Objetivo do sistema

Criar um motor JavaScript para renderização dinâmica de telas CRUD usando Kendo UI for jQuery e jQuery.

O motor deve receber um JSON de definição de tela e, com base nele, renderizar:

- título da tela;
- barra de ações;
- área de filtros;
- grid de consulta;
- formulário de criação/edição/visualização;
- abas do formulário;
- seções do formulário;
- campos do formulário;
- ações por linha;
- preferências temporárias do usuário;
- salvamento de layout personalizado do usuário.

O motor não deve gerar código final estático.  
O motor deve renderizar dinamicamente a tela a partir de um JSON final já resolvido pelo backend.

---

## 2. Princípio arquitetural

A regra principal é:

```text
Backend decide.
Frontend renderiza.
```

O backend deve ser responsável por:

- resolver permissões;
- resolver sessão;
- resolver empresa/tenant;
- resolver campos disponíveis;
- resolver endpoints;
- resolver customizações persistidas;
- gerar JSON final de tela;
- validar alterações salvas pelo usuário;
- gravar preferências de layout;
- recompilar/cachear JSON final.

O frontend deve ser responsável por:

- buscar a definição JSON;
- validar minimamente a estrutura recebida;
- renderizar a interface com Kendo UI;
- permitir alterações temporárias no layout;
- detectar mudanças de grid;
- enviar ao backend somente o layout que o usuário decidiu salvar.

---

## 3. Fluxo de abertura da tela

```text
Usuário acessa a tela
        ↓
Frontend chama GET /crud-definition/{module}/{entity}
        ↓
Backend lê JSON base + overrides + preferências persistidas
        ↓
Backend aplica sessão, permissões e regras
        ↓
Backend entrega JSON final
        ↓
Frontend valida a definição
        ↓
Frontend renderiza título, filtros, grid e formulário
```

---

## 4. Fluxo de customização temporária

```text
Usuário abre a tela
        ↓
Grid é renderizado com JSON final
        ↓
Usuário muda ordem de colunas, largura, visibilidade, filtro ou ordenação
        ↓
Mudança fica apenas no estado local do frontend
        ↓
Botão "Salvar layout" fica habilitado
        ↓
Se o usuário sair sem salvar, nada é persistido
```

---

## 5. Fluxo de salvamento de layout

```text
Usuário altera layout no frontend
        ↓
Usuário clica em "Salvar layout"
        ↓
Frontend captura o estado atual do grid
        ↓
Frontend monta um JSON de override do usuário
        ↓
Frontend envia POST /crud-layout/{module}/{entity}
        ↓
Backend valida o override
        ↓
Backend grava no banco
        ↓
Backend recompila/cacheia JSON final
        ↓
Na próxima abertura, a tela já vem com o layout salvo
```

---

## 6. Escopo da versão 1

A versão inicial deve implementar:

1. Renderização de tela de consulta.
2. Renderização de filtros.
3. Renderização de Kendo Grid.
4. Renderização de formulário em modo popup ou painel.
5. Formulário com abas.
6. Formulário com seções.
7. Campos básicos.
8. Ações principais: novo, editar, visualizar, excluir, atualizar.
9. Ações por linha.
10. Preferência temporária de layout no frontend.
11. Salvamento de layout do usuário no backend.
12. Colunas calculadas simples, sem JavaScript livre.
13. Validação básica do JSON recebido.
14. Separação entre definição base e layout do usuário.

---

## 7. Fora do escopo da versão 1

Não implementar agora:

- geração de código-fonte estático;
- editor visual completo de metadados;
- relatório avançado;
- construtor avançado de fórmulas;
- SQL dinâmico livre;
- JavaScript livre dentro do JSON;
- customização de persistência de campos;
- criação de tabelas/colunas pelo motor;
- workflow;
- permissões complexas por campo;
- auditoria completa;
- versionamento visual de metadados.

Esses pontos podem ser tratados depois.  
A versão 1 deve nascer preparada para crescer, mas sem abraçar o mundo.

---

## 8. Estrutura de diretórios sugerida

```text
src/
  crud-engine/
    CrudEngine.js
    CrudDefinitionLoader.js
    CrudDefinitionValidator.js
    CrudKendoGridRenderer.js
    CrudKendoFormRenderer.js
    CrudFilterRenderer.js
    CrudToolbarRenderer.js
    CrudLayoutManager.js
    CrudFormulaEvaluator.js
    CrudHttpClient.js
    CrudUtils.js

public/
  metadata/
    schemas/
      crud-definition-v1.schema.json

examples/
  clientes.crud.json
  clientes.user-layout.json
```

---

## 9. Contrato principal do JSON

O backend deve entregar ao frontend um JSON final neste formato geral:

```json
{
  "schemaVersion": "1.0",
  "id": "cadastros.clientes",
  "module": "cadastros",
  "entity": "clientes",
  "title": "Clientes",
  "subtitle": "Consulta e manutenção de clientes",
  "description": "Tela de cadastro de clientes.",

  "api": {},
  "permissions": {},
  "features": {},
  "dataModel": {},
  "query": {},
  "grid": {},
  "form": {},
  "layoutCustomization": {},
  "userLayout": {}
}
```

Campos obrigatórios:

```text
schemaVersion
id
module
entity
title
api
permissions
dataModel
grid
form
```

---

## 10. Definição de API

A seção `api` define os endpoints usados pelo motor.

```json
{
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
    "saveLayout": {
      "url": "/api/crud-layout/cadastros/clientes",
      "method": "POST"
    },
    "restoreLayout": {
      "url": "/api/crud-layout/cadastros/clientes",
      "method": "DELETE"
    }
  }
}
```

Regras:

- O frontend não deve montar endpoints por conta própria.
- O backend deve entregar os endpoints já resolvidos.
- O placeholder `{id}` deve ser substituído pelo valor da chave primária.
- O backend deve validar permissões novamente em todas as rotas.

---

## 11. Permissões

A seção `permissions` indica o que o usuário atual pode fazer.

```json
{
  "permissions": {
    "read": true,
    "create": true,
    "edit": true,
    "delete": false,
    "export": true,
    "saveLayout": true,
    "restoreLayout": true
  }
}
```

Regras:

- O frontend usa permissões para mostrar/esconder botões.
- Segurança real é sempre no backend.
- Mesmo que o frontend esconda o botão de excluir, o endpoint de exclusão deve validar permissão.

---

## 12. Recursos habilitados

A seção `features` controla comportamentos gerais da tela.

```json
{
  "features": {
    "filterPanel": true,
    "grid": true,
    "form": true,
    "tabs": true,
    "rowActions": true,
    "exportExcel": true,
    "columnReorder": true,
    "columnResize": true,
    "columnVisibility": true,
    "saveUserLayout": true,
    "calculatedColumns": true
  }
}
```

---

## 13. Modelo de dados

A seção `dataModel` é o contrato de campos disponíveis para grid, filtros e formulário.

```json
{
  "dataModel": {
    "primaryKey": "id",
    "displayField": "nome",

    "fields": {
      "id": {
        "type": "integer",
        "label": "ID",
        "editable": false,
        "visible": false,
        "nullable": false
      },
      "nome": {
        "type": "string",
        "label": "Nome",
        "editable": true,
        "visible": true,
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
        "visible": true,
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
        "visible": true,
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
      "data_cadastro": {
        "type": "date",
        "label": "Data de Cadastro",
        "editable": false,
        "visible": true,
        "nullable": true
      },
      "valor_total": {
        "type": "decimal",
        "label": "Valor Total",
        "editable": false,
        "visible": true,
        "nullable": true,
        "format": "currency"
      },
      "qtde_pedidos": {
        "type": "integer",
        "label": "Qtde. Pedidos",
        "editable": false,
        "visible": true,
        "nullable": true
      }
    }
  }
}
```

Tipos iniciais permitidos:

```text
string
text
integer
decimal
number
boolean
date
datetime
email
enum
lookup
hidden
```

Regras:

- Todo campo usado no grid deve existir em `dataModel.fields`, exceto colunas calculadas.
- Todo campo usado no formulário deve existir em `dataModel.fields`.
- O frontend não precisa saber se o campo veio de coluna física, view, cálculo do backend ou outra origem.
- Para o motor, todos os campos recebidos são campos normais.

---

## 14. Consulta e filtros

A seção `query` define o título da área de consulta, filtros disponíveis e comportamento inicial.

```json
{
  "query": {
    "title": "Consulta de Clientes",
    "defaultPageSize": 20,
    "pageSizes": [10, 20, 50, 100],

    "defaultSort": [
      {
        "field": "nome",
        "dir": "asc"
      }
    ],

    "filters": [
      {
        "id": "busca",
        "label": "Busca",
        "type": "search",
        "placeholder": "Nome ou e-mail",
        "fields": ["nome", "email"],
        "operator": "contains"
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
        "label": "Período de Cadastro",
        "type": "dateRange",
        "operator": "between"
      }
    ]
  }
}
```

Tipos iniciais de filtro:

```text
search
text
number
numberRange
date
dateRange
boolean
enum
lookup
```

Operadores iniciais:

```text
eq
neq
contains
startsWith
endsWith
gt
gte
lt
lte
between
in
```

Regras:

- Filtros devem ser enviados ao backend em formato estruturado.
- O backend decide como aplicar o filtro na consulta real.
- O frontend não deve montar SQL, JPQL, DQL ou qualquer linguagem de consulta do backend.
- O filtro de busca geral pode pesquisar em vários campos.

---

## 15. Grid

A seção `grid` define o comportamento do Kendo Grid.

```json
{
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
    "selectable": "row",

    "persistLayout": true,

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
        "id": "refresh",
        "label": "Atualizar",
        "icon": "refresh",
        "action": "refresh",
        "permission": "read"
      },
      {
        "id": "saveLayout",
        "label": "Salvar layout",
        "icon": "save",
        "action": "saveLayout",
        "permission": "saveLayout",
        "enabledWhenDirty": true
      },
      {
        "id": "restoreLayout",
        "label": "Restaurar padrão",
        "icon": "undo",
        "action": "restoreLayout",
        "permission": "restoreLayout"
      }
    ],

    "rowActions": [
      {
        "id": "view",
        "label": "Visualizar",
        "icon": "eye",
        "action": "view",
        "permission": "read"
      },
      {
        "id": "edit",
        "label": "Editar",
        "icon": "edit",
        "action": "edit",
        "permission": "edit"
      },
      {
        "id": "delete",
        "label": "Excluir",
        "icon": "trash",
        "action": "delete",
        "permission": "delete",
        "confirm": {
          "title": "Confirmar exclusão",
          "message": "Deseja excluir este registro?"
        }
      }
    ]
  }
}
```

Regras:

- A coluna de ações pode ser criada automaticamente pelo motor quando `rowActions` existir.
- O motor deve esconder ações sem permissão.
- O motor deve respeitar `visible`, `width`, `sortable`, `filterable` e `format`.
- O motor deve permitir alteração temporária de largura, ordem e visibilidade das colunas.
- O motor deve marcar estado como "sujo" quando layout for alterado.

---

## 16. Formulário

A seção `form` define formulário de criação, edição e visualização.

```json
{
  "form": {
    "id": "clienteForm",
    "mode": "popup",
    "width": 900,

    "title": {
      "create": "Novo Cliente",
      "edit": "Editar Cliente",
      "view": "Visualizar Cliente"
    },

    "layout": "tabs",

    "tabs": [
      {
        "id": "geral",
        "title": "Geral",
        "sections": [
          {
            "id": "identificacao",
            "title": "Identificação",
            "columns": 2,
            "fields": [
              {
                "field": "nome",
                "colSpan": 2
              },
              {
                "field": "email",
                "colSpan": 2
              },
              {
                "field": "status",
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
            "title": "Métricas",
            "columns": 2,
            "fields": [
              {
                "field": "valor_total",
                "readonly": true
              },
              {
                "field": "qtde_pedidos",
                "readonly": true
              }
            ]
          }
        ]
      }
    ],

    "buttons": [
      {
        "id": "save",
        "label": "Salvar",
        "action": "save",
        "permission": "edit",
        "visibleIn": ["create", "edit"]
      },
      {
        "id": "cancel",
        "label": "Cancelar",
        "action": "cancel",
        "visibleIn": ["create", "edit", "view"]
      }
    ]
  }
}
```

Modos iniciais de formulário:

```text
popup
panel
inline
```

Layout inicial:

```text
tabs
sections
single
```

Regras:

- A propriedade `tabs` só é obrigatória quando `layout = "tabs"`.
- Cada item em `fields` deve apontar para um campo existente em `dataModel.fields`.
- Propriedades específicas de formulário podem sobrescrever propriedades do campo.
- `readonly` no formulário deve prevalecer sobre `editable` do modelo.
- No modo `view`, todos os campos devem ser somente leitura.
- O motor deve suportar ao menos abas e seções na primeira versão.

---

## 17. Editores de campo

O tipo do campo e o editor podem ser inferidos, mas o JSON também pode informar explicitamente o editor.

Exemplo:

```json
{
  "dataModel": {
    "fields": {
      "nome": {
        "type": "string",
        "label": "Nome",
        "editor": "text"
      },
      "observacao": {
        "type": "text",
        "label": "Observação",
        "editor": "textarea"
      },
      "status": {
        "type": "enum",
        "label": "Status",
        "editor": "dropdown",
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
      }
    }
  }
}
```

Editores iniciais:

```text
text
textarea
number
currency
date
datetime
checkbox
switch
dropdown
combobox
hidden
readonly
```

---

## 18. Lookup

Campos de lookup devem apontar para uma API controlada pelo backend.

```json
{
  "dataModel": {
    "fields": {
      "cidade_id": {
        "type": "lookup",
        "label": "Cidade",
        "editor": "combobox",
        "lookup": {
          "url": "/api/lookups/cidades",
          "method": "GET",
          "valueField": "id",
          "textField": "nome",
          "serverFiltering": true,
          "minLength": 2
        }
      }
    }
  }
}
```

Regras:

- O frontend apenas chama a URL definida.
- O backend controla permissões e escopo dos dados.
- Não aceitar lookup com URL arbitrária criada pelo usuário final.

---

## 19. Colunas calculadas

A versão 1 pode suportar colunas calculadas simples no grid.

Não aceitar JavaScript livre no JSON.

Não aceitar:

```json
{
  "template": "eval(...)"
}
```

Aceitar fórmula estruturada.

Exemplo:

```json
{
  "field": "_calc.ticket_medio",
  "title": "Ticket Médio",
  "kind": "calculated",
  "type": "decimal",
  "format": "currency",
  "width": 150,
  "visible": true,
  "formula": {
    "op": "divide",
    "left": {
      "field": "valor_total"
    },
    "right": {
      "field": "qtde_pedidos"
    },
    "defaultValue": null
  }
}
```

Operações permitidas na versão 1:

```text
add
subtract
multiply
divide
concat
coalesce
```

Exemplo de concatenação:

```json
{
  "field": "_calc.nome_email",
  "title": "Nome / E-mail",
  "kind": "calculated",
  "type": "string",
  "formula": {
    "op": "concat",
    "values": [
      {
        "field": "nome"
      },
      {
        "literal": " - "
      },
      {
        "field": "email"
      }
    ]
  }
}
```

Regras:

- Campos calculados devem usar prefixo `_calc.`.
- Fórmulas só podem usar campos existentes no `dataModel.fields`.
- O motor deve tratar divisão por zero.
- Campo calculado não deve ser editável.
- Campo calculado pode ser salvo no layout do usuário.
- Filtro e ordenação de campo calculado devem ser desabilitados na versão 1, salvo se o backend declarar suporte explícito.

---

## 20. Formatação

A propriedade `format` deve ser uma palavra conhecida pelo motor.

Formatos iniciais:

```text
text
integer
decimal
currency
percent
date
datetime
boolean
```

Exemplo:

```json
{
  "field": "valor_total",
  "title": "Valor Total",
  "format": "currency",
  "align": "right"
}
```

O motor deve mapear esses formatos para o formato aceito pelo Kendo.

Exemplo interno:

```text
currency -> "{0:c}"
date     -> "{0:dd/MM/yyyy}"
datetime -> "{0:dd/MM/yyyy HH:mm}"
```

---

## 21. Layout do usuário

A seção `userLayout` representa o layout persistido pelo usuário, já aplicado ou disponível para referência.

```json
{
  "userLayout": {
    "enabled": true,
    "version": 4,
    "source": "user",
    "definitionHash": "abc123",

    "grid": {
      "columns": {
        "order": [
          "nome",
          "status",
          "email",
          "valor_total",
          "_calc.ticket_medio"
        ],
        "hidden": [
          "id"
        ],
        "widths": {
          "nome": 260,
          "status": 130,
          "email": 280,
          "valor_total": 160,
          "_calc.ticket_medio": 160
        },
        "added": [
          {
            "field": "_calc.ticket_medio",
            "title": "Ticket Médio",
            "kind": "calculated",
            "type": "decimal",
            "format": "currency",
            "formula": {
              "op": "divide",
              "left": {
                "field": "valor_total"
              },
              "right": {
                "field": "qtde_pedidos"
              },
              "defaultValue": null
            }
          }
        ]
      },

      "sort": [
        {
          "field": "nome",
          "dir": "asc"
        }
      ],

      "filter": null,

      "group": []
    }
  }
}
```

Regras:

- O backend deve salvar preferências de usuário em banco.
- Para renderização rápida, o backend pode gerar/cachear JSON final.
- O frontend pode aplicar alterações temporárias sem gravar.
- O frontend só envia alterações ao backend quando o usuário clicar em "Salvar layout".

---

## 22. Override enviado ao backend

Quando o usuário clicar em "Salvar layout", o frontend deve enviar um override compacto.

Endpoint:

```http
POST /api/crud-layout/{module}/{entity}
```

Payload:

```json
{
  "definitionId": "cadastros.clientes",
  "definitionHash": "abc123",
  "layoutVersion": 4,

  "grid": {
    "columns": {
      "order": [
        "nome",
        "status",
        "email",
        "valor_total",
        "_calc.ticket_medio"
      ],
      "hidden": [
        "id"
      ],
      "widths": {
        "nome": 260,
        "status": 130,
        "email": 280,
        "valor_total": 160,
        "_calc.ticket_medio": 160
      },
      "added": [
        {
          "field": "_calc.ticket_medio",
          "title": "Ticket Médio",
          "kind": "calculated",
          "type": "decimal",
          "format": "currency",
          "formula": {
            "op": "divide",
            "left": {
              "field": "valor_total"
            },
            "right": {
              "field": "qtde_pedidos"
            },
            "defaultValue": null
          }
        }
      ]
    },

    "sort": [
      {
        "field": "nome",
        "dir": "asc"
      }
    ],

    "filter": null,
    "group": []
  }
}
```

Regras de validação no backend:

- `definitionId` deve existir.
- `definitionHash` deve bater ou o backend deve avisar conflito.
- Campos em `order`, `hidden` e `widths` devem existir.
- Campos adicionados devem ser calculados ou permitidos.
- Fórmulas devem usar apenas operações permitidas.
- Fórmulas devem usar apenas campos existentes.
- Usuário deve ter permissão para salvar layout.
- O backend deve gravar o override e invalidar o cache da definição final.

---

## 23. Ordem de precedência

A montagem da definição final deve seguir esta ordem:

```text
1. JSON base do sistema
2. Override da empresa/cliente
3. Override do perfil/grupo, se existir
4. Layout salvo do usuário
5. Alterações temporárias do frontend
```

A etapa 5 não vai para o backend até o usuário clicar em salvar.

---

## 24. Estados no frontend

O motor deve controlar três estados:

```js
const state = {
  originalDefinition: null,
  currentDefinition: null,
  dirty: false,
  dirtyLayout: {
    grid: {
      columns: {
        order: [],
        hidden: [],
        widths: {},
        added: []
      },
      sort: [],
      filter: null,
      group: []
    }
  }
};
```

Significado:

- `originalDefinition`: definição recebida do backend.
- `currentDefinition`: definição atual, considerando mudanças temporárias.
- `dirty`: indica que o usuário mudou algo e ainda não salvou.
- `dirtyLayout`: diferença a ser enviada ao backend.

---

## 25. Eventos mínimos do motor

O motor deve disparar eventos internos ou callbacks:

```text
beforeLoadDefinition
afterLoadDefinition
beforeRender
afterRender
beforeRead
afterRead
beforeCreate
afterCreate
beforeUpdate
afterUpdate
beforeDelete
afterDelete
layoutChanged
beforeSaveLayout
afterSaveLayout
layoutSaveError
```

Uso:

```js
CrudEngine.init("#app", {
  definitionUrl: "/crud-definition/cadastros/clientes",

  events: {
    afterRender: function(context) {
      console.log("Tela renderizada", context.definition.id);
    },

    layoutChanged: function(context) {
      console.log("Layout alterado");
    }
  }
});
```

---

## 26. API pública do motor

Implementar uma API simples:

```js
CrudEngine.init(container, options);
CrudEngine.destroy(container);
CrudEngine.reload(container);
CrudEngine.getState(container);
CrudEngine.saveLayout(container);
CrudEngine.restoreLayout(container);
CrudEngine.openCreate(container);
CrudEngine.openEdit(container, id);
CrudEngine.openView(container, id);
```

Exemplo:

```js
CrudEngine.init("#clientesPage", {
  definitionUrl: "/crud-definition/cadastros/clientes"
});
```

Também permitir passar definição diretamente:

```js
CrudEngine.init("#clientesPage", {
  definition: clientesDefinition
});
```

---

## 27. Exemplo completo de JSON v1

```json
{
  "schemaVersion": "1.0",
  "id": "cadastros.clientes",
  "module": "cadastros",
  "entity": "clientes",
  "title": "Clientes",
  "subtitle": "Consulta e manutenção de clientes",
  "description": "Tela de cadastro de clientes.",

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
    "saveLayout": {
      "url": "/api/crud-layout/cadastros/clientes",
      "method": "POST"
    },
    "restoreLayout": {
      "url": "/api/crud-layout/cadastros/clientes",
      "method": "DELETE"
    }
  },

  "permissions": {
    "read": true,
    "create": true,
    "edit": true,
    "delete": false,
    "export": true,
    "saveLayout": true,
    "restoreLayout": true
  },

  "features": {
    "filterPanel": true,
    "grid": true,
    "form": true,
    "tabs": true,
    "rowActions": true,
    "exportExcel": true,
    "columnReorder": true,
    "columnResize": true,
    "columnVisibility": true,
    "saveUserLayout": true,
    "calculatedColumns": true
  },

  "dataModel": {
    "primaryKey": "id",
    "displayField": "nome",

    "fields": {
      "id": {
        "type": "integer",
        "label": "ID",
        "editable": false,
        "visible": false,
        "nullable": false
      },
      "nome": {
        "type": "string",
        "label": "Nome",
        "editable": true,
        "visible": true,
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
        "visible": true,
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
        "visible": true,
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
      "data_cadastro": {
        "type": "date",
        "label": "Data de Cadastro",
        "editable": false,
        "visible": true,
        "nullable": true
      },
      "valor_total": {
        "type": "decimal",
        "label": "Valor Total",
        "editable": false,
        "visible": true,
        "nullable": true,
        "format": "currency"
      },
      "qtde_pedidos": {
        "type": "integer",
        "label": "Qtde. Pedidos",
        "editable": false,
        "visible": true,
        "nullable": true
      }
    }
  },

  "query": {
    "title": "Consulta de Clientes",
    "defaultPageSize": 20,
    "pageSizes": [10, 20, 50, 100],

    "defaultSort": [
      {
        "field": "nome",
        "dir": "asc"
      }
    ],

    "filters": [
      {
        "id": "busca",
        "label": "Busca",
        "type": "search",
        "placeholder": "Nome ou e-mail",
        "fields": ["nome", "email"],
        "operator": "contains"
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
        "label": "Período de Cadastro",
        "type": "dateRange",
        "operator": "between"
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
    "selectable": "row",

    "persistLayout": true,

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
        "id": "refresh",
        "label": "Atualizar",
        "icon": "refresh",
        "action": "refresh",
        "permission": "read"
      },
      {
        "id": "saveLayout",
        "label": "Salvar layout",
        "icon": "save",
        "action": "saveLayout",
        "permission": "saveLayout",
        "enabledWhenDirty": true
      },
      {
        "id": "restoreLayout",
        "label": "Restaurar padrão",
        "icon": "undo",
        "action": "restoreLayout",
        "permission": "restoreLayout"
      }
    ],

    "rowActions": [
      {
        "id": "view",
        "label": "Visualizar",
        "icon": "eye",
        "action": "view",
        "permission": "read"
      },
      {
        "id": "edit",
        "label": "Editar",
        "icon": "edit",
        "action": "edit",
        "permission": "edit"
      },
      {
        "id": "delete",
        "label": "Excluir",
        "icon": "trash",
        "action": "delete",
        "permission": "delete",
        "confirm": {
          "title": "Confirmar exclusão",
          "message": "Deseja excluir este registro?"
        }
      }
    ]
  },

  "form": {
    "id": "clienteForm",
    "mode": "popup",
    "width": 900,

    "title": {
      "create": "Novo Cliente",
      "edit": "Editar Cliente",
      "view": "Visualizar Cliente"
    },

    "layout": "tabs",

    "tabs": [
      {
        "id": "geral",
        "title": "Geral",
        "sections": [
          {
            "id": "identificacao",
            "title": "Identificação",
            "columns": 2,
            "fields": [
              {
                "field": "nome",
                "colSpan": 2
              },
              {
                "field": "email",
                "colSpan": 2
              },
              {
                "field": "status",
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
            "title": "Métricas",
            "columns": 2,
            "fields": [
              {
                "field": "valor_total",
                "readonly": true
              },
              {
                "field": "qtde_pedidos",
                "readonly": true
              }
            ]
          }
        ]
      }
    ],

    "buttons": [
      {
        "id": "save",
        "label": "Salvar",
        "action": "save",
        "permission": "edit",
        "visibleIn": ["create", "edit"]
      },
      {
        "id": "cancel",
        "label": "Cancelar",
        "action": "cancel",
        "visibleIn": ["create", "edit", "view"]
      }
    ]
  },

  "layoutCustomization": {
    "enabled": true,
    "allowColumnReorder": true,
    "allowColumnResize": true,
    "allowColumnVisibility": true,
    "allowSortSave": true,
    "allowFilterSave": false,
    "allowCalculatedColumns": true
  },

  "userLayout": {
    "enabled": true,
    "version": 1,
    "source": "default",
    "definitionHash": "abc123",
    "grid": {
      "columns": {
        "order": [],
        "hidden": [],
        "widths": {},
        "added": []
      },
      "sort": [],
      "filter": null,
      "group": []
    }
  }
}
```

---

## 28. JSON Schema inicial

Criar arquivo:

```text
public/metadata/schemas/crud-definition-v1.schema.json
```

Conteúdo inicial simplificado:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "crud-definition-v1.schema.json",
  "title": "CRUD Definition v1",
  "type": "object",
  "required": [
    "schemaVersion",
    "id",
    "module",
    "entity",
    "title",
    "api",
    "permissions",
    "dataModel",
    "grid",
    "form"
  ],
  "properties": {
    "schemaVersion": {
      "type": "string"
    },
    "id": {
      "type": "string"
    },
    "module": {
      "type": "string"
    },
    "entity": {
      "type": "string"
    },
    "title": {
      "type": "string"
    },
    "subtitle": {
      "type": "string"
    },
    "description": {
      "type": "string"
    },
    "api": {
      "type": "object"
    },
    "permissions": {
      "type": "object"
    },
    "features": {
      "type": "object"
    },
    "dataModel": {
      "type": "object",
      "required": ["primaryKey", "fields"],
      "properties": {
        "primaryKey": {
          "type": "string"
        },
        "displayField": {
          "type": "string"
        },
        "fields": {
          "type": "object",
          "additionalProperties": {
            "type": "object",
            "required": ["type", "label"],
            "properties": {
              "type": {
                "type": "string",
                "enum": [
                  "string",
                  "text",
                  "integer",
                  "decimal",
                  "number",
                  "boolean",
                  "date",
                  "datetime",
                  "email",
                  "enum",
                  "lookup",
                  "hidden"
                ]
              },
              "label": {
                "type": "string"
              },
              "editable": {
                "type": "boolean"
              },
              "visible": {
                "type": "boolean"
              },
              "nullable": {
                "type": "boolean"
              },
              "format": {
                "type": "string"
              },
              "editor": {
                "type": "string"
              },
              "validation": {
                "type": "object"
              },
              "options": {
                "type": "array"
              },
              "lookup": {
                "type": "object"
              }
            }
          }
        }
      }
    },
    "query": {
      "type": "object"
    },
    "grid": {
      "type": "object",
      "required": ["columns"],
      "properties": {
        "id": {
          "type": "string"
        },
        "columns": {
          "type": "array",
          "items": {
            "type": "object",
            "required": ["field", "title"],
            "properties": {
              "field": {
                "type": "string"
              },
              "title": {
                "type": "string"
              },
              "width": {
                "type": ["integer", "string"]
              },
              "visible": {
                "type": "boolean"
              },
              "filterable": {
                "type": "boolean"
              },
              "sortable": {
                "type": "boolean"
              },
              "format": {
                "type": "string"
              },
              "align": {
                "type": "string",
                "enum": ["left", "center", "right"]
              },
              "kind": {
                "type": "string",
                "enum": ["field", "calculated"]
              },
              "formula": {
                "type": "object"
              }
            }
          }
        }
      }
    },
    "form": {
      "type": "object",
      "required": ["id", "mode", "layout"],
      "properties": {
        "id": {
          "type": "string"
        },
        "mode": {
          "type": "string",
          "enum": ["popup", "panel", "inline"]
        },
        "layout": {
          "type": "string",
          "enum": ["tabs", "sections", "single"]
        },
        "tabs": {
          "type": "array"
        },
        "buttons": {
          "type": "array"
        }
      }
    },
    "layoutCustomization": {
      "type": "object"
    },
    "userLayout": {
      "type": "object"
    }
  }
}
```

---

## 29. Validações extras fora do JSON Schema

Além do JSON Schema, implementar validações específicas:

```text
1. Todo grid.columns[].field deve existir em dataModel.fields ou começar com _calc.
2. Todo form.tabs[].sections[].fields[].field deve existir em dataModel.fields.
3. Toda permissão referenciada em botões deve existir em permissions.
4. Toda fórmula calculada deve usar apenas campos existentes.
5. Toda operação de fórmula deve estar na lista permitida.
6. Não aceitar template JavaScript livre vindo do JSON.
7. Não aceitar URL externa em api ou lookup, salvo configuração explícita.
8. Não aceitar campo com nome vazio, espaços ou caracteres inválidos.
9. Não aceitar coluna calculada editável.
10. Não aceitar layout salvo com coluna inexistente.
```

---

## 30. Mapeamento para Kendo Grid

Implementar um adapter para converter JSON próprio para configuração Kendo.

Exemplo conceitual:

```js
function buildGridOptions(definition) {
  return {
    dataSource: buildDataSource(definition),
    pageable: definition.grid.pageable,
    sortable: definition.grid.sortable,
    filterable: definition.grid.filterable,
    groupable: definition.grid.groupable,
    resizable: definition.grid.resizable,
    reorderable: definition.grid.reorderable,
    columnMenu: definition.grid.columnMenu,
    selectable: definition.grid.selectable,
    columns: buildGridColumns(definition)
  };
}
```

Regra importante:

O JSON do motor não deve ser uma cópia exata da API do Kendo.  
Ele deve ser um contrato próprio do sistema.  
O adapter traduz para Kendo.

Isso evita refazer todo o metadado se algum componente mudar depois.

---

## 31. Mapeamento de DataSource

Exemplo conceitual:

```js
function buildDataSource(definition) {
  return new kendo.data.DataSource({
    transport: {
      read: function(options) {
        CrudHttpClient.request({
          url: definition.api.read.url,
          method: definition.api.read.method,
          data: options.data
        }).then(options.success).catch(options.error);
      }
    },
    schema: {
      data: "data",
      total: "total",
      model: buildKendoModel(definition)
    },
    serverPaging: true,
    serverSorting: true,
    serverFiltering: true,
    pageSize: definition.query?.defaultPageSize || 20,
    sort: definition.query?.defaultSort || []
  });
}
```

Resposta esperada da API de listagem:

```json
{
  "data": [
    {
      "id": 1,
      "nome": "Cliente A",
      "email": "cliente@email.com",
      "status": "ATIVO",
      "data_cadastro": "2026-01-10",
      "valor_total": 1500.5,
      "qtde_pedidos": 3
    }
  ],
  "total": 1
}
```

---

## 32. Captura de layout do grid

O `CrudLayoutManager` deve capturar:

```text
ordem das colunas
colunas ocultas
larguras
colunas calculadas adicionadas
sort
filter
group
```

Exemplo conceitual:

```js
function captureGridLayout(grid) {
  const options = grid.getOptions();

  return {
    columns: {
      order: options.columns
        .filter(c => c.field)
        .map(c => c.field),

      hidden: options.columns
        .filter(c => c.field && c.hidden)
        .map(c => c.field),

      widths: options.columns
        .filter(c => c.field && c.width)
        .reduce((acc, c) => {
          acc[c.field] = c.width;
          return acc;
        }, {}),

      added: []
    },

    sort: grid.dataSource.sort() || [],
    filter: grid.dataSource.filter() || null,
    group: grid.dataSource.group() || []
  };
}
```

---

## 33. Botões mínimos da tela

Implementar botões principais:

```text
Novo
Atualizar
Salvar layout
Restaurar padrão
```

Implementar ações por linha:

```text
Visualizar
Editar
Excluir
```

Comportamento:

- `Novo`: abre formulário vazio em modo create.
- `Visualizar`: carrega registro e abre formulário readonly.
- `Editar`: carrega registro e abre formulário editável.
- `Excluir`: pede confirmação e chama API delete.
- `Atualizar`: recarrega grid.
- `Salvar layout`: envia override do usuário.
- `Restaurar padrão`: chama endpoint de restauração e recarrega definição.

---

## 34. Tratamento de erro

O motor deve exibir mensagens claras para:

```text
erro ao carregar definição
JSON inválido
campo inexistente referenciado no grid
campo inexistente referenciado no formulário
erro ao carregar dados
erro ao salvar
erro ao excluir
erro ao salvar layout
conflito de versão de layout
permissão negada
```

Formato esperado de erro do backend:

```json
{
  "error": {
    "code": "LAYOUT_VERSION_CONFLICT",
    "message": "O layout foi alterado em outra sessão. Recarregue a tela antes de salvar.",
    "details": {}
  }
}
```

---

## 35. Segurança

Regras obrigatórias:

```text
1. Não executar JavaScript vindo do JSON.
2. Não usar eval.
3. Não aceitar template livre cadastrado por usuário.
4. Não confiar em permissões do frontend.
5. Validar no backend todo layout salvo.
6. Validar no backend toda operação de CRUD.
7. URLs devem vir do backend.
8. Fórmulas calculadas devem usar apenas operações permitidas.
9. Campos enviados no formulário devem ser filtrados pelo backend.
10. O frontend deve ser tratado como camada visual, não como autoridade.
```

---

## 36. Implementação em etapas

### Etapa 1 — Loader e validação

Criar:

```text
CrudDefinitionLoader
CrudDefinitionValidator
```

Entregas:

```text
Carregar JSON por URL
Aceitar JSON direto
Validar campos obrigatórios
Validar referências de campos
Exibir erro amigável
```

### Etapa 2 — Renderização básica

Criar:

```text
CrudEngine
CrudToolbarRenderer
CrudKendoGridRenderer
```

Entregas:

```text
Renderizar título
Renderizar toolbar
Renderizar grid
Carregar dados via API read
Paginação
Ordenação
Filtro básico do Kendo
```

### Etapa 3 — Formulário

Criar:

```text
CrudKendoFormRenderer
```

Entregas:

```text
Abrir formulário create
Abrir formulário edit
Abrir formulário view
Renderizar abas
Renderizar seções
Renderizar campos
Salvar create/update
```

### Etapa 4 — Layout do usuário

Criar:

```text
CrudLayoutManager
```

Entregas:

```text
Detectar mudança de layout
Habilitar botão Salvar layout
Capturar layout
Enviar override ao backend
Restaurar padrão
```

### Etapa 5 — Colunas calculadas simples

Criar:

```text
CrudFormulaEvaluator
```

Entregas:

```text
Adicionar coluna calculada temporária
Renderizar coluna calculada
Salvar coluna calculada no layout
Validar fórmula no frontend
Backend deve validar de novo
```

---

## 37. Critérios de aceite da versão 1

A versão 1 será considerada pronta quando:

```text
1. Conseguir abrir uma tela a partir de JSON.
2. Conseguir renderizar grid com colunas configuradas.
3. Conseguir listar dados via API.
4. Conseguir paginar, ordenar e filtrar.
5. Conseguir abrir formulário de novo registro.
6. Conseguir abrir formulário de edição.
7. Conseguir abrir formulário de visualização.
8. Conseguir salvar create/update.
9. Conseguir excluir com confirmação.
10. Conseguir renderizar formulário com abas e seções.
11. Conseguir alterar ordem/largura/visibilidade de colunas temporariamente.
12. Conseguir salvar layout do usuário.
13. Conseguir restaurar layout padrão.
14. Conseguir adicionar uma coluna calculada simples.
15. Não executar JavaScript livre vindo do JSON.
16. Validar referências inválidas no JSON.
```

---

## 38. Observações finais para implementação

A prioridade é criar um motor simples, previsível e extensível.

Não tentar transformar a primeira versão em um ERP inteiro.

O contrato JSON deve ser estável.  
O adapter para Kendo pode mudar por baixo.  
O backend continua sendo a fonte da verdade.  
O frontend renderiza, customiza temporariamente e envia preferências quando o usuário decidir salvar.

A regra de ouro:

```text
O motor renderizador não pergunta de onde veio o campo.
Ele só pergunta como deve exibir, editar, filtrar e validar.
```
