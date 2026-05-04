/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../kendo.core.js";
import "../kendo.ooxml.js";

(function(kendo) {
    let $ = kendo.jQuery;

    if (kendo.PDFMixin) {
        let override_drawPDF = function(options) {
            var result = new $.Deferred();
            var callback = function(group) {
                result.resolve(group);
            };
            switch(options.area) {
            case "workbook":
                options.workbook.draw(options, callback);
                break;
            case "sheet":
                options.workbook.activeSheet().draw(options, callback);
                break;
            case "selection":
                options.workbook.activeSheet().selection().draw(options, callback);
                break;
            }

            return result.promise();
        };

         let overrideSaveAsPDF = function(options) {
            if (this.events.indexOf("pdfExport") === -1) {
                kendo.PDFMixin.extend(this);
                this.saveAsPDF = overrideSaveAsPDF;
                this._drawPDF = override_drawPDF;
            }

            var progress = new $.Deferred();
            var promise = progress.promise();
            var args = { promise: promise };
            if (this.trigger("pdfExport", args)) {
                return;
            }

            this._drawPDF(options, progress)
            .then(function(root) {
                let pdfResultPromise = options.forceProxy
                    ? kendo.pdf.exportPDF(root) // produce data URI for proxy
                    : kendo.pdf.exportPDFToBlob(root);
                return kendo.convertPromiseToDeferred(pdfResultPromise);
            })
            .done(function(dataURI) {
                kendo.saveAs({
                    dataURI: dataURI,
                    fileName: options.fileName,
                    proxyURL: options.proxyURL,
                    forceProxy: options.forceProxy,
                    proxyTarget: options.proxyTarget
                });

                progress.resolve();
            })
            .fail(function(err) {
                progress.reject(err);
            });

            return promise;
        };

        kendo.spreadsheet.Workbook.prototype.saveAsPDF = overrideSaveAsPDF

        kendo.spreadsheet.Workbook.prototype._drawPDF = override_drawPDF
    }
})(kendo);
