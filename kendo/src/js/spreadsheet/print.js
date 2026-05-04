/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../kendo.pdf.js";
    "use strict";

    function clone(hash, target) {
        if (!target) {
            target = {};
        }
        if (Object.assign) {
            return Object.assign(target, hash);
        }
        return Object.keys(hash).reduce(function(copy, key) {
            copy[key] = hash[key];
            return copy;
        }, target);
    }

    function drawTabularData(options) {
        var progress = new $.Deferred();
        var promise = progress.promise();

        options = clone(options, {
            dataSource       : null,
            guidelines       : true,
            guideColor       : "#000",
            columns          : null,
            headerBackground : "#999",
            headerColor      : "#000",
            oddBackground    : null,
            evenBackground   : null,
            fontFamily       : "Arial",
            fontSize         : 12,
            paperSize        : "A4",
            margin           : "1cm",
            landscape        : true,
            fitWidth         : false,
            scale            : 1,
            rowHeight        : 20,
            maxEmpty         : 1,
            useGridFormat    : true
        });

        // retrieve fonts; custom fonts should be already loaded
        kendo.drawing.pdf.defineFont(
            kendo.drawing.drawDOM.getFontFaces(document)
        );

        var charWidth = charWidthFunction(options.fontFamily, options.fontSize);

        function textWidth(value) {
            if (value != null) {
                var width = 12;         // magic numbers :-/
                for (var i = value.length; --i >= 0;) {
                    width += charWidth(value.charAt(i));
                }
                return width;
            }
            return 0;
        }

        var border = options.guidelines ? { size: 1, color: options.guideColor } : null;

        function mkCell(data) {
            if (!border) {
                return data;
            }
            return clone(data, {
                borderLeft: border,
                borderTop: border,
                borderRight: border,
                borderBottom: border
            });
        }

        options.dataSource.fetch(function(){
            var data = options.dataSource.data();
            if (!data.length) {
                return progress.reject("Empty dataset");
            }

            // this really must be present
            var columns = options.columns.map(function(col){
                if (typeof col == "string") {
                    return { title: col, field: col };
                } else {
                    return col;
                }
            });
            var columnTitles = columns.map(function(col){
                return col.title || col.field;
            });
            var columnWidths = columnTitles.map(textWidth);

            // prepare data for a Sheet object's fromJSON method
            var rows = data.map(function(row, rowIndex){
                return {
                    cells: columns.map(function(col, colIndex){
                        var value = row[col.field];

                        // NOTE: value might not be string.  I added option useGridFormat (default
                        // true), which will use a column's format, if present, to convert the value
                        // to a string, so that we can measure the width right now.
                        if (options.useGridFormat) {
                            if (value != null) {
                                if (col.format) {
                                    value = kendo.format(col.format, value);
                                } else {
                                    value += "";
                                }
                            }
                            // adjust the column widths while we're at it
                            columnWidths[colIndex] = Math.max(
                                textWidth(value),
                                columnWidths[colIndex]
                            );
                        }

                        // if options.useGridFormat is false and col.format is present, pass it over
                        // to the spreadsheet.  In that case we should calculate the widths after
                        // the spreadsheet is created (XXX to be implemented when someone needs it).
                        return mkCell({
                            value: value,
                            format: options.useGridFormat ? null : col.format,
                            background: rowIndex % 2 ? options.evenBackground : options.oddBackground
                        });
                    })
                };
            });

            // insert header line
            rows.unshift({
                cells: columnTitles.map(function(label){
                    return mkCell({
                        value: label,
                        background: options.headerBackground,
                        color: options.headerColor
                    });
                })
            });

            // init a Sheet object.  Note that we have to add one
            // extra-row and column, because the very last ones can't
            // have right/bottom borders (known limitation).
            var sheet = new kendo.spreadsheet.Sheet(
                rows.length + 1,        // rows
                columns.length + 1,     // columns
                options.rowHeight,      // row height
                50,                     // column width
                20,                     // header height
                20,                     // header width,
                {                       // default cell style
                    fontFamily: options.fontFamily,
                    fontSize: options.fontSize,
                    verticalAlign: "center"
                }
            );

            // load data
            sheet.fromJSON({
                name: "Sheet1",
                rows: rows,
                columns: columnWidths.map(function(w, i){
                    return { index: i, width: w };
                })
            });

            sheet.draw({
                paperSize  : options.paperSize,
                landscape  : options.landscape,
                margin     : options.margin,
                guidelines : false, // using borders instead (better contrast)
                scale      : options.scale,
                fitWidth   : options.fitWidth,
                maxEmpty   : options.maxEmpty,
                headerRows : 1
            }, progress.resolve.bind(progress));
        });

        return promise;
    }

    var CACHE_CHAR_WIDTH = {};

    var charWidthFunction = function(fontFamily, fontSize) {
        var id = fontSize + ":" + fontFamily;
        var func = CACHE_CHAR_WIDTH[id];
        if (!func) {
            var span, div = document.createElement("div");
            div.style.position = "fixed";
            div.style.left = "-10000px";
            div.style.top = "-10000px";
            div.style.fontFamily = fontFamily;
            div.style.fontSize = fontSize + "px";
            div.style.whiteSpace = "pre";
            for (var i = 32; i < 128; ++i) {
                span = document.createElement("span");
                span.appendChild(document.createTextNode(String.fromCharCode(i)));
                div.appendChild(span);
            }
            document.body.appendChild(div);
            var widths = {};
            for (i = 32, span = div.firstChild; i < 128 && span; ++i, span = span.nextSibling) {
                widths[i] = span.offsetWidth;
            }
            while ((span = div.firstChild)) {
                div.removeChild(span);
            }
            func = CACHE_CHAR_WIDTH[id] = function(ch) {
                var code = ch.charCodeAt(0);
                var width = widths[code];
                if (width == null) {
                    // probably not an ASCII character, let's cache its width as well
                    span = document.createElement("span");
                    span.appendChild(document.createTextNode(String.fromCharCode(code)));
                    div.appendChild(span);
                    width = widths[code] = span.offsetWidth;
                    div.removeChild(span);
                }
                return width;
            };
        }
        return func;
    };

    kendo.spreadsheet.drawTabularData = drawTabularData;
