(function(global) {
  "use strict";

  global.ImportExportAdminDemoData = {
    sources: {
      cliente: [
        { id: 1, nome: "Ana Comercio", status: "ATIVO", cidade: "Fortaleza" },
        { id: 2, nome: "Beta Servicos", status: "INATIVO", cidade: "Sao Paulo" }
      ],
      cidade: [
        { id: 10, cliente_id: 1, nome: "Fortaleza" },
        { id: 11, cliente_id: 2, nome: "Sao Paulo" }
      ]
    },
    mappings: [
      {
        code: "clientes_txt_sped",
        name: "Clientes TXT hierarquico",
        direction: "export",
        targetType: "file",
        targetCode: "clientes_sped.txt",
        format: "txt_layout",
        status: "active",
        mapping: {
          source: { type: "entity", entityCode: "cliente", alias: "cliente", mode: "list" },
          sources: [
            { type: "entity", entityCode: "cliente", alias: "cliente", mode: "list" },
            { type: "entity", entityCode: "cidade", alias: "cidade", mode: "list" }
          ],
          destination: {
            type: "file",
            fileFormat: "txt_layout",
            fileNamePattern: "clientes_sped",
            encodingLabel: "UTF-8",
            lineBreak: "\n",
            layoutMode: "tree",
            recordLayouts: [
              {
                nodeType: "record",
                recordType: "CLI",
                label: "Cliente",
                sourceAlias: "cliente",
                lineMode: "delimited",
                separator: "|",
                fields: [
                  { constant: "CLI" },
                  { sourcePath: "id" },
                  { sourcePath: "nome" },
                  { sourcePath: "status" }
                ],
                children: [
                  {
                    nodeType: "record",
                    recordType: "CID",
                    label: "Cidade",
                    sourceAlias: "cidade",
                    lineMode: "delimited",
                    separator: "|",
                    linkBy: [
                      { parentPath: "id", childField: "cliente_id" }
                    ],
                    fields: [
                      { constant: "CID" },
                      { sourcePath: "_parent.id" },
                      { sourcePath: "nome" }
                    ]
                  }
                ]
              }
            ]
          },
          options: { previewLimit: 20 }
        }
      },
      {
        code: "clientes_xml_rico",
        name: "Clientes XML rico",
        direction: "export",
        targetType: "file",
        targetCode: "clientes.xml",
        format: "xml",
        status: "active",
        mapping: {
          sources: [
            { type: "entity", entityCode: "cliente", alias: "cliente", mode: "list" },
            { type: "entity", entityCode: "cidade", alias: "cidade", mode: "list" }
          ],
          destination: {
            type: "file",
            fileFormat: "xml",
            fileNamePattern: "clientes_rico",
            encodingLabel: "UTF-8",
            rootName: "doc:clientes",
            prettyPrint: true,
            namespaces: [
              { prefix: "doc", uri: "urn:demo:doc" }
            ],
            rootAttributes: [
              { name: "versao", constant: "1.0" }
            ],
            xmlLayouts: [
              {
                name: "doc:cliente",
                label: "Cliente",
                sourceAlias: "cliente",
                attributes: [
                  { name: "id", sourcePath: "id" },
                  { name: "status", sourcePath: "status" }
                ],
                fields: [
                  { name: "nome", sourcePath: "nome" }
                ],
                children: [
                  {
                    name: "doc:cidade",
                    label: "Cidade",
                    sourceAlias: "cidade",
                    linkBy: [
                      { parentPath: "id", childField: "cliente_id" }
                    ],
                    textSourcePath: "nome"
                  }
                ]
              }
            ]
          },
          options: { previewLimit: 20 }
        }
      }
    ]
  };
})(window);
