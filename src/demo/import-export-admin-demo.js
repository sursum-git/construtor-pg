(function(global) {
  "use strict";

  function ImportExportAdminDemoHttpClient(options) {
    this.options = options || {};
    this.storageKey = "import-export-admin-demo-mappings-v1";
    this.state = this.loadState();
  }

  ImportExportAdminDemoHttpClient.prototype.request = function(options) {
    const request = options || {};
    const method = String(request.method || "GET").toUpperCase();
    const url = String(request.url || "");
    const data = request.data || {};
    return new Promise(function(resolve, reject) {
      window.setTimeout(function() {
        try {
          resolve(this.route(method, url, data));
        } catch (error) {
          reject(error);
        }
      }.bind(this), 60);
    }.bind(this));
  };

  ImportExportAdminDemoHttpClient.prototype.route = function(method, url, data) {
    if (url === "/api/runtime/literals/pt-BR" && method === "GET") {
      return {};
    }
    if (url === "/api/admin/import-export-mappings" && method === "GET") {
      return {
        items: this.state.mappings.map(this.toSummary)
      };
    }
    if (url === "/api/admin/import-export-mappings" && method === "POST") {
      return this.saveMapping(data || {});
    }
    if (url === "/api/admin/import-export-mappings/preview" && method === "POST") {
      return this.previewMapping(data || {});
    }
    if (url === "/api/admin/import-export-mappings/execute" && method === "POST") {
      return this.executeMapping(data || {});
    }
    const codeMatch = url.match(/^\/api\/admin\/import-export-mappings\/([^/]+)$/);
    if (codeMatch && method === "GET") {
      const code = decodeURIComponent(codeMatch[1]);
      const mapping = this.findMapping(code);
      if (!mapping) {
        throw global.CrudUtils.makeError("IMPORT_EXPORT_DEMO_NOT_FOUND", "Mapeamento nao encontrado.", { code: code });
      }
      return { mapping: global.CrudUtils.clone(mapping) };
    }
    throw global.CrudUtils.makeError("IMPORT_EXPORT_DEMO_ROUTE_NOT_FOUND", "Rota demo nao encontrada.", { method: method, url: url });
  };

  ImportExportAdminDemoHttpClient.prototype.loadState = function() {
    try {
      const stored = global.localStorage && global.localStorage.getItem(this.storageKey);
      if (stored) {
        return JSON.parse(stored);
      }
    } catch (_) {}
    return {
      mappings: global.CrudUtils.clone((global.ImportExportAdminDemoData && global.ImportExportAdminDemoData.mappings) || [])
    };
  };

  ImportExportAdminDemoHttpClient.prototype.persist = function() {
    try {
      if (global.localStorage) {
        global.localStorage.setItem(this.storageKey, JSON.stringify(this.state));
      }
    } catch (_) {}
  };

  ImportExportAdminDemoHttpClient.prototype.toSummary = function(item) {
    return {
      code: item.code,
      name: item.name,
      direction: item.direction,
      targetType: item.targetType,
      targetCode: item.targetCode,
      format: item.format,
      status: item.status
    };
  };

  ImportExportAdminDemoHttpClient.prototype.findMapping = function(code) {
    return this.state.mappings.find(function(item) {
      return String(item.code || "") === String(code || "");
    }) || null;
  };

  ImportExportAdminDemoHttpClient.prototype.saveMapping = function(payload) {
    const code = String(payload.code || "").trim();
    if (!code) {
      throw global.CrudUtils.makeError("IMPORT_EXPORT_DEMO_CODE_REQUIRED", "Informe o codigo do mapeamento.");
    }
    const normalized = {
      code: code,
      name: String(payload.name || "").trim(),
      direction: String(payload.direction || "export").trim(),
      targetType: String(payload.targetType || "file").trim(),
      targetCode: String(payload.targetCode || "").trim(),
      format: String(payload.format || "txt_layout").trim(),
      status: String(payload.status || "draft").trim(),
      mapping: global.CrudUtils.clone(payload.mapping || {})
    };
    const index = this.state.mappings.findIndex(function(item) {
      return String(item.code || "") === code;
    });
    if (index >= 0) {
      this.state.mappings[index] = normalized;
    } else {
      this.state.mappings.push(normalized);
    }
    this.persist();
    return { mapping: global.CrudUtils.clone(normalized) };
  };

  ImportExportAdminDemoHttpClient.prototype.previewMapping = function(payload) {
    const mapping = this.resolvePayload(payload);
    return this.buildResult(mapping, false);
  };

  ImportExportAdminDemoHttpClient.prototype.executeMapping = function(payload) {
    const mapping = this.resolvePayload(payload);
    return this.buildResult(mapping, true);
  };

  ImportExportAdminDemoHttpClient.prototype.resolvePayload = function(payload) {
    if (payload && payload.mapping) {
      return {
        code: String(payload.code || "demo").trim(),
        name: String(payload.name || "Demo").trim(),
        direction: String(payload.direction || "export").trim(),
        format: String(payload.format || "txt_layout").trim(),
        mapping: global.CrudUtils.clone(payload.mapping || {})
      };
    }
    const current = this.findMapping(payload && payload.code);
    if (!current) {
      throw global.CrudUtils.makeError("IMPORT_EXPORT_DEMO_NOT_FOUND", "Mapeamento nao encontrado.", { code: payload && payload.code });
    }
    return current;
  };

  ImportExportAdminDemoHttpClient.prototype.buildResult = function(mapping, executed) {
    const format = String(mapping.format || "").toLowerCase();
    const result = format === "xml"
      ? this.buildXmlResult(mapping)
      : format === "txt_layout"
        ? this.buildTxtResult(mapping)
        : this.buildCsvResult(mapping);
    return {
      mapping: {
        code: mapping.code,
        name: mapping.name,
        direction: mapping.direction,
        format: mapping.format
      },
      counts: {
        read: 2,
        written: executed && result.type !== "file" ? 2 : 0,
        skipped: 0,
        errors: 0
      },
      diagnostics: [
        {
          level: executed ? "info" : "warning",
          message: executed ? "Execucao simulada concluida no ambiente demo." : "Preview gerado com dados simulados locais."
        }
      ],
      result: result
    };
  };

  ImportExportAdminDemoHttpClient.prototype.buildCsvResult = function(mapping) {
    const lines = [
      "\"ID\";\"Nome\"",
      "\"1\";\"Ana Comercio\"",
      "\"2\";\"Beta Servicos\""
    ];
    return {
      type: "file",
      fileName: (mapping.mapping && mapping.mapping.destination && mapping.mapping.destination.fileNamePattern || "clientes") + ".csv",
      mimeType: "text/csv; charset=UTF-8",
      previewText: lines.join("\n"),
      content: lines.join("\n")
    };
  };

  ImportExportAdminDemoHttpClient.prototype.buildTxtResult = function(mapping) {
    const data = (global.ImportExportAdminDemoData && global.ImportExportAdminDemoData.sources) || {};
    const clientes = global.CrudUtils.ensureArray(data.cliente);
    const cidades = global.CrudUtils.ensureArray(data.cidade);
    const lines = [];
    clientes.forEach(function(cliente) {
      lines.push(["CLI", cliente.id, cliente.nome, cliente.status].join("|"));
      cidades.filter(function(cidade) {
        return Number(cidade.cliente_id) === Number(cliente.id);
      }).forEach(function(cidade) {
        lines.push(["CID", cliente.id, cidade.nome].join("|"));
      });
    });
    return {
      type: "file",
      fileName: (mapping.mapping && mapping.mapping.destination && mapping.mapping.destination.fileNamePattern || "clientes_sped") + ".txt",
      mimeType: "text/plain; charset=UTF-8",
      previewText: lines.join("\n"),
      content: lines.join("\n")
    };
  };

  ImportExportAdminDemoHttpClient.prototype.buildXmlResult = function(mapping) {
    const data = (global.ImportExportAdminDemoData && global.ImportExportAdminDemoData.sources) || {};
    const clientes = global.CrudUtils.ensureArray(data.cliente);
    const cidades = global.CrudUtils.ensureArray(data.cidade);
    const lines = [
      "<?xml version=\"1.0\" encoding=\"UTF-8\"?>",
      "<doc:clientes xmlns:doc=\"urn:demo:doc\" versao=\"1.0\">"
    ];
    clientes.forEach(function(cliente) {
      lines.push("  <doc:cliente id=\"" + cliente.id + "\" status=\"" + cliente.status + "\">");
      lines.push("    <nome>" + cliente.nome + "</nome>");
      cidades.filter(function(cidade) {
        return Number(cidade.cliente_id) === Number(cliente.id);
      }).forEach(function(cidade) {
        lines.push("    <doc:cidade>" + cidade.nome + "</doc:cidade>");
      });
      lines.push("  </doc:cliente>");
    });
    lines.push("</doc:clientes>");
    const xml = lines.join("\n");
    return {
      type: "file",
      fileName: (mapping.mapping && mapping.mapping.destination && mapping.mapping.destination.fileNamePattern || "clientes_rico") + ".xml",
      mimeType: "application/xml; charset=UTF-8",
      previewText: xml,
      content: xml
    };
  };

  global.ImportExportAdminDemoHttpClient = ImportExportAdminDemoHttpClient;

  document.addEventListener("DOMContentLoaded", function() {
    if (!global.ImportExportAdmin) {
      return;
    }
    global.importExportAdminDemoApp = new global.ImportExportAdmin({
      root: "#import-export-admin-root",
      httpClient: new ImportExportAdminDemoHttpClient()
    });
    global.importExportAdminDemoApp.init();
  });
})(window);
