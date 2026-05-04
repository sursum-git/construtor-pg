/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./../kendo.dialog.js";
(function($, undefined) {
    let kendo = window.kendo,
        extend = $.extend,
        Class = kendo.Class,
        keys = kendo.keys,
        COLUMNSIZE = "#columnsize";

    let Command = Class.extend({
        init: function(options) {
            this.options = options;
            this.propertyGrid = options.propertyGrid;
        }
    });

    let CopyCommand = Command.extend({
        exec: function() {
            const that = this,
                options = that.options,
                propertyGrid = that.propertyGrid,
                target = that.options.target.closest("tr"),
                dataItem = propertyGrid.dataItem(target);
            if (options.copy == "selection") {
                let sel = window.getSelection();
                let range = sel.getRangeAt(0);
                let selectedText = range.toString();

                navigator.clipboard.writeText(selectedText);
            } else if (options.copy == "name") {
                navigator.clipboard.writeText(dataItem.field);
            } else if (options.copy == "declaration") {
                let value = that.propertyGrid._objectFromNodes(dataItem);
                let tabDelimitedValue = [dataItem.field, JSON.stringify(value[dataItem.field]), dataItem.description].filter(item => item !== undefined).join("\t");
                navigator.clipboard.writeText(tabDelimitedValue);
            }
        }
    });

    let ResetCommand = Command.extend({
        exec: function() {
            let that = this,
                propertyGrid = that.propertyGrid,
                target = that.options.target.closest("tr"),
                dataItem = propertyGrid.dataItem(target);

            propertyGrid.dataSource.cancelChanges(dataItem);
            propertyGrid.trigger("cancel", { type: "cancel", model: dataItem, container: that.options.target });
            propertyGrid._render();
        }
    });

    let ResizeColumnCommand = Command.extend({
        _actionButtonsTemplate: function({ apply, cancel, insertButtonIcon, cancelButtonIcon }) {
            return '<div class="k-actions k-actions-start k-actions-horizontal k-window-buttons">' +
            kendo.html.renderButton(`<button class="k-dialog-apply">${apply}</button>`, { themeColor: "primary", icon: insertButtonIcon }) +
            kendo.html.renderButton(`<button class="k-dialog-close">${cancel}</button>`, { icon: cancelButtonIcon }) +
        '</div>';
        },

        exec: function() {
            let that = this, dialog, form,
                propertyGrid = that.propertyGrid,
                tableColumnIndex = that.options.target.index(),
                columnIndex = propertyGrid.grouped ? tableColumnIndex - 1 : tableColumnIndex,
                columnSelector = `tr:not(.k-table-group-row):first > td:nth-child(${tableColumnIndex + 1})`,
                oldColumnWidth = propertyGrid.table.find(columnSelector).outerWidth(),
                dialogOptions = {
                    title: "Resize Column",
                    visible: false,
                    resizable: true,
                    minWidth: 350
                },
                totalWidth = propertyGrid.table.width();

                adjustColWidths(that.propertyGrid);
                propertyGrid.table.width(totalWidth);

                function apply(e) {
                    let delta;
                    let oldColumnWidth = dialog.wrapper.find(".k-form").data("kendoForm").options.formData.columnsize;
                    let newColumnWidth = dialog.wrapper.find(COLUMNSIZE).data("kendoNumericTextBox").value();

                    if (oldColumnWidth == newColumnWidth) {
                        close(e);
                        return;
                    } else if (oldColumnWidth > newColumnWidth) {
                        delta = oldColumnWidth - newColumnWidth;
                        propertyGrid.table.width(totalWidth - delta);
                    } else {
                        delta = newColumnWidth - oldColumnWidth;
                        propertyGrid.table.width(totalWidth + delta);
                    }

                    propertyGrid.columns[columnIndex].width = newColumnWidth;
                    propertyGrid.table.children("colgroup").find("col").eq(tableColumnIndex).width(newColumnWidth);

                    propertyGrid.trigger("columnResize", {
                        column: propertyGrid.columns[columnIndex],
                        oldWidth: oldColumnWidth,
                        newWidth: newColumnWidth
                    });

                    close(e);
                }

                function close(e) {
                    e.preventDefault();
                    form.destroy();
                    dialog.destroy();
                }

                function keyDown(e) {
                    if (e.keyCode == keys.ENTER) {
                        apply(e);
                    } else if (e.keyCode == keys.ESC) {
                        close(e);
                    }
                }

                function adjustColWidths(component) {
                    const columnSelector = (columnIndex) => `tr:not(.k-table-group-row):first > td:nth-child(${columnIndex + 1})`,
                    colCount = component.table.children("colgroup").find("col").length;
                    let widths = [];
                    for (let idx = 0; idx < colCount; idx++) {
                        widths.push(component.table.find(columnSelector(idx)).outerWidth());
                    }

                    component.table.children("colgroup").find("col").each((idx,col) => {
                        $(col).width(widths[idx]);
                    });
                }

                dialogOptions.close = close;

                dialog = $("<div/>").appendTo(document.body).kendoWindow(dialogOptions).data("kendoWindow");
                form = that._createForm(dialog, oldColumnWidth);
                dialog.element.after($(that._actionButtonsTemplate({ apply: "Apply", cancel: "Cancel" , insertButtonIcon: "check", cancelButtonIcon: "cancel-outline" })));

                dialog.wrapper
                    .find(".k-dialog-apply").on("click", apply).end()
                    .find(".k-dialog-close").on("click", close).end()
                    .find(".k-form-field input").on("keydown", keyDown).end();

                    dialog.center().open();
        },

        _createForm: function(dialog, currentColumnWidth) {
            let formElement = $("<div/>").appendTo(dialog.element);

            return formElement.kendoForm({
                formData: {
                    columnsize: currentColumnWidth
                },
                validation: {
                    validateOnBlur: false,
                    validaitonSummary: false
                },
                renderButtons: false,
                items: [
                    {
                        field: "columnsize",
                        label: "Set column Size",
                        editor: "NumericTextBox",
                        editorOptions: {
                            min: 0
                        }
                    }
                ]
            }).data("kendoForm");
        }
    });

    let ToggleItemCommand = Command.extend({
        exec: function() {
            let that = this,
            propertyGrid = that.propertyGrid,
                target = that.options.target,
                options = that.options,
                expand = options.expand === 'true';

                if (expand) {
                    propertyGrid.expand(target);
                } else {
                    propertyGrid.collapse(target);
                }
        }
    });

    let ToggleGroupLayout = Command.extend({
        exec: function() {
            let that = this,
            propertyGrid = that.propertyGrid,
            grouped = propertyGrid.grouped;

            propertyGrid.grouped = !grouped;
            if ( !propertyGrid.grouped) {
                propertyGrid.wrapper.find("tr:not(.k-details-box)").removeClass("k-hidden");
            }

            propertyGrid._refreshLayout();
            propertyGrid._updateDetails();
        }
    });

    let ToggleDetails = Command.extend({
        exec: function() {
            let that = this,
            propertyGrid = that.propertyGrid,
            visible = propertyGrid.visibleDetails;

            propertyGrid.visibleDetails = !visible;
            if ( !propertyGrid.visible) {
                propertyGrid.table.find("tr.k-details-box").toggleClass("k-hidden");
            }
        }
    });

    let ExcelExport = Command.extend({
        exec: function() {
            let that = this,
            propertyGrid = that.propertyGrid;

            propertyGrid.saveAsExcel();
        }
    });

    let PDFExport = Command.extend({
        exec: function() {
            let that = this,
            propertyGrid = that.propertyGrid;

            propertyGrid.saveAsPDF();
        }
    });

    kendo.ui.propertygrid = kendo.ui.propertygrid || {};

    extend(kendo.ui.propertygrid, {
        PropertyGridCommand: Command,
        commands: {
            ResetCommand: ResetCommand,
            CopyCommand: CopyCommand,
            ResizeColumnCommand: ResizeColumnCommand,
            ToggleItemCommand: ToggleItemCommand,
            ToggleGroupLayout: ToggleGroupLayout,
            ToggleDetails: ToggleDetails,
            ExcelExport: ExcelExport,
            PDFExport: PDFExport,
        }
    });
})(window.kendo.jQuery);