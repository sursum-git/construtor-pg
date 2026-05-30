(function(global) {
  "use strict";

  const PrintingBridgeClient = {
    deliverPayload(payload) {
      const mode = String(payload && payload.deliveryMode || "download").toLowerCase();
      if (mode === "qz_tray") {
        return this.printViaQzTray(payload || {});
      }
      return this.downloadPayload(payload || {});
    },

    downloadPayload(payload) {
      const bytes = Uint8Array.from(global.atob(String(payload.contentBase64 || "")), function(char) {
        return char.charCodeAt(0);
      });
      const blob = new Blob([bytes], { type: String(payload.contentType || "application/octet-stream") });
      const href = global.URL.createObjectURL(blob);
      const link = global.document.createElement("a");
      link.href = href;
      link.download = String(payload.fileName || "arquivo");
      global.document.body.appendChild(link);
      link.click();
      link.remove();
      global.URL.revokeObjectURL(href);
      return Promise.resolve({
        mode: "download"
      });
    },

    printViaQzTray(payload) {
      if (!global.qz || !global.qz.websocket || !global.qz.configs || !global.qz.print) {
        return Promise.reject(global.CrudUtils.makeError(
          "PRINT_QZ_TRAY_UNAVAILABLE",
          "QZ Tray nao esta disponivel neste navegador. Instale/inicie o agente local para impressao."
        ));
      }
      if (String(payload.format || "").toLowerCase() !== "pdf" && String(payload.contentType || "").toLowerCase().indexOf("application/pdf") !== 0) {
        return Promise.reject(global.CrudUtils.makeError(
          "PRINT_QZ_TRAY_FORMAT_NOT_SUPPORTED",
          "A impressao local por QZ Tray aceita apenas PDF nesta fase."
        ));
      }

      const printer = payload && payload.printer || {};
      const printerName = String(printer.printerName || "").trim();
      if (!printerName) {
        return Promise.reject(global.CrudUtils.makeError(
          "PRINT_QZ_TRAY_PRINTER_REQUIRED",
          "Informe a impressora local do QZ Tray para concluir a impressao."
        ));
      }

      const connect = typeof global.qz.websocket.isActive === "function" && global.qz.websocket.isActive()
        ? Promise.resolve()
        : global.qz.websocket.connect();

      return Promise.resolve(connect).then(function() {
        const config = global.qz.configs.create(printerName, {
          copies: Math.max(1, Number(printer.copies || 1) || 1),
          jobName: String(printer.jobName || payload.fileName || "Impressao local")
        });
        return global.qz.print(config, [{
          type: "pixel",
          format: "pdf",
          flavor: "base64",
          data: String(payload.contentBase64 || "")
        }]);
      }).then(function() {
        return {
          mode: "qz_tray",
          printerName: printerName
        };
      }).catch(function(error) {
        throw global.CrudUtils.unwrapError(error, "Falha ao enviar o documento para o QZ Tray.");
      });
    }
  };

  global.PrintingBridgeClient = PrintingBridgeClient;
})(window);
