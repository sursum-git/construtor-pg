/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.excel.js";
import "./kendo.progressbar.js";
import "./kendo.pdf.js";
import "./kendo.toolbar.js";
import "./kendo.list.js";
import "./kendo.spreadsheet.common.js";
import "./spreadsheet/dialogs.js";
import "./spreadsheet/toolbar.js";
import "./spreadsheet/print.js";
import "./spreadsheet/customeditors.js";
import "./spreadsheet/sheetsbar.js";
import "./spreadsheet/filtermenu.js";
import "./spreadsheet/messages.js";
import "./spreadsheet/workbook.js";

export const __meta__ = {
    id: "spreadsheet",
    name: "Spreadsheet",
    category: "web",
    description: "Spreadsheet component",
    depends: [
        "core", "binder", "colorpicker", "combobox", "data", "dom", "dropdownlist",
        "menu", "ooxml", "popup", "sortable", "toolbar", "treeview",
        "window", "validator", "excel", "pdf", "drawing", "list", "spreadsheet.common"]
};

(function(kendo, undefined) {
    if (kendo.support.browser.msie && kendo.support.browser.version < 9) {
        return;
    }

    const K_ACTIVE = "k-active";
    let $ = kendo.jQuery;
    let Widget = kendo.ui.Widget;
    let NS = ".kendoSpreadsheet";
    let DOT = ".";
    let commonEngine = kendo.spreadsheet.commonEngine;
    let FormulaInput = kendo.spreadsheet.FormulaInput;
    let SheetsBar = kendo.spreadsheet.SheetsBar;

    function toActionSelector(selectors) {
        return selectors.map(function(action) {
            return '[data-action="' + action + '"]';
        }).join(",");
    }

    function convertSpreadsheetDeferredToJQueryDeferred(spreadsheetDeferred) {
        const deferred = $.Deferred();

        spreadsheetDeferred.then(
            function(value) { deferred.resolve(value); },
            function(reason) { deferred.reject(reason); }
        );

        if (typeof spreadsheetDeferred.progress === "function") {
            spreadsheetDeferred.progress(function(val) {
                deferred.notify(val);
            });
        }

        const promise = deferred.promise();
        promise.progress = function(fn) {
            deferred.progress(fn);
            return promise;
        };
        return promise;
    }

    function removeEventsFromOptions(options, eventNames) {
        const sanitized = {};
        for (let key in options) {
            if (options.hasOwnProperty(key) && eventNames.indexOf(key) === -1) {
                sanitized[key] = options[key];
            }
        }
        return sanitized;
    }

    let COMPOSITE_UNAVAILABLE_ACTION_SELECTORS = toActionSelector(['cut', 'copy', 'paste', 'insert-left', 'insert-right', 'insert-above', 'insert-below']);
    let UNHIDE_ACTION_SELECTORS = toActionSelector(['unhide-row', 'unhide-column']);
    let classNames = {
        wrapper: "k-spreadsheet"
    };

    let viewClassNames = commonEngine.View.classNames;

    const SPREADSHEET_CONTENT_HTML_TEMPLATE = ({ messages }) => `
                            <div ref-header-container class="k-spreadsheet-header"><div ref-header-menu></div><div ref-header-toolbar></div></div>
                            <div class="k-spreadsheet-action-bar">
                                <div class="k-spreadsheet-name-editor">
                                    <input ref-namebox-input title="${messages.nameBox || "Name Box"}" aria-label="${messages.nameBox || "Name Box"}"></input>
                                </div>
                                <div class="k-spreadsheet-formula-bar">
                                    ${kendo.ui.icon("formula-fx")}
                                    <div class="k-spreadsheet-formula-input"
                                        role="combobox"
                                        title="${messages.view.formulaInput}"
                                        aria-haspopup="menu"
                                        aria-expanded="false"
                                        contentEditable="true"
                                        spellCheck="false">
                                    </div>
                                </div>
                            </div>
                            <div class="k-spreadsheet-view">
                                <div class="k-spreadsheet-fixed-container"></div>
                                <div class="k-spreadsheet-scroller">
                                    <div class="k-spreadsheet-view-size"></div>
                                </div>
                                <div tabIndex="0" class="k-spreadsheet-clipboard" contentEditable="true" ></div>
                                <div contentEditable="true"
                                    spellCheck="false"
                                    role="combobox"
                                    title="${messages.view.formulaInput}"
                                    aria-haspopup="menu"
                                    aria-expanded="false"
                                    class="k-spreadsheet-formula-input k-spreadsheet-cell-editor">
                                </div>
                            </div>
                            <div ref-sheets-bar></div>`;

    const CELL_CONTEXT_MENU = ({ messages }) => `
                    <ul class="${viewClassNames.cellContextMenu}">
                        <li data-action="cut">${messages.menus.cut}</li>
                        <li data-action="copy">${messages.menus.copy}</li>
                        <li data-action="paste">${messages.menus.paste}</li>
                        <li class="k-separator"></li>
                        <li data-action="merge">${messages.menus.merge}</li>
                        <li data-action="unmerge">${messages.menus.unmerge}</li>
                    </ul>`;

    const ROW_HEADER_CONTEXT_MENU = ({ messages }) => `
                    <ul class="${viewClassNames.rowHeaderContextMenu}">
                        <li data-action="cut">${messages.menus.cut}</li>
                        <li data-action="copy">${messages.menus.copy}</li>
                        <li data-action="paste">${messages.menus.paste}</li>
                        <li class="k-separator"></li>
                        <li data-action="delete-row">${messages.menus.delete}</li>
                        <li data-action="hide-row">${messages.menus.hide}</li>
                        <li data-action="unhide-row">${messages.menus.unhide}</li>
                    </ul>`;

    const COL_HEADER_CONTEXT_MENU = ({ messages }) => `
                    <ul class="${viewClassNames.colHeaderContextMenu}">
                        <li data-action="cut">${messages.menus.cut}</li>
                        <li data-action="copy">${messages.menus.copy}</li>
                        <li data-action="paste">${messages.menus.paste}</li>
                        <li class="k-separator"></li>
                        <li data-action="delete-column">${messages.menus.delete}</li>
                        <li data-action="hide-column">${messages.menus.hide}</li>
                        <li data-action="unhide-column">${messages.menus.unhide}</li>
                    </ul>`;

    const DRAWING_CONTEXT_MENU = ({ messages }) => `
                    <ul class="${viewClassNames.drawingContextMenu}">
                        <li data-action="bring-to-front">${messages.menus.bringToFront}</li>
                        <li data-action="send-to-back">${messages.menus.sendToBack}</li>
                        <li class="k-separator"></li>
                        <li data-action="delete-drawing">${messages.menus.delete}</li>
                    </ul>`;

    kendo.spreadsheet.ContextMenu = kendo.ui.ContextMenu;

    let FormulaInputStaticList = kendo.ui.StaticList.extend({
        data: function(d) {
            return this.dataSource.data(d);
        },
        itemClick: function(handler) {
            this.bind("change", function(ev) { handler(this.value()[0]); });
        }
    });

    class FormulaInputRefArgs {
        constructor({ list, popup }) {
            this.current = { list, popup };
            this.clone = function clone() {
                return this;
            };
        }
    }

    class NameBoxRefArgs {
        constructor({ nameEditor, container }) {
            let combo = this.initializeComboBox(container, nameEditor);
            this.current = {
                value: (val) => {
                    if (val === undefined) {
                        const item = combo.value();
                        return item ? (item.name || item) : item;
                    } else {
                        combo.value(val || '');
                    }
                }
            };

            this.nameEditor = nameEditor;

            this.clone = function clone() {
                return this;
            };
        }

        initializeComboBox(container) {
            let that = this;
            let dataSource = new kendo.data.DataSource({
                transport: {
                    read: function(options) {
                        let data = that.nameEditor()?.readData() || [];
                        options.success(data);
                    },
                    cache: false
                }
            });

            let combo = $(container).kendoComboBox({
                clearButton: false,
                dataTextField: "name",
                dataValueField: "name",
                template: ({ name }) => `${kendo.htmlEncode(name)}<a role='button' class='k-button-delete' href='\\#'>${kendo.ui.icon("x")}</a>`,
                dataSource: dataSource,
                autoBind: false,
                ignoreCase: true,
                change: that.onChange.bind(that),
                noDataTemplate: () => "<div></div>",
                open: function() {
                    dataSource.read();
                }
            }).data("kendoComboBox");

            combo.input
                .on("keydown", that.onKeyDown.bind(this));
            combo.popup.element
                .addClass("k-spreadsheet-names-popup")

                .on("mousemove", function(ev) {
                    // XXX: should remove this when we find a better
                    // solution for the popup closing as we hover the
                    // resize handles.
                    ev.stopPropagation();
                })

                .on("click", ".k-button-delete", function(ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    let item = $(ev.target).closest(".k-list-item");
                    item = combo.dataItem(item);
                    that.onDelete(item.name);
                });

            return combo;
        }

        // Event: Handle change in ComboBox
        onChange(event) {
            const editor = this.nameEditor();
            if (editor && event.sender.value()) {
                editor.trigger("select", { name: event.sender.value() });
            }
        }

        // Event: Delete button click within item
        onDelete(name) {
            this.nameEditor()?.trigger("delete", { name });
        }

        // Handling keyboard events like Enter, Escape
        onKeyDown(event) {
            const editor = this.nameEditor();
            if (editor) {
                if (event.key === 'Enter') {
                    const name = event.target.value;
                    editor.trigger("enter", { value: name });
                } else if (event.key === 'Escape') {
                    editor.trigger("cancel");
                }
            }
        }
    }

    let Spreadsheet = kendo.ui.Widget.extend({
        _renderInnerContent: function(element) {
            $(element).append(SPREADSHEET_CONTENT_HTML_TEMPLATE(this.options));
        },
        init: function(element, options) {
            let that = this;
            Widget.fn.init.call(this, element, options);

            this.options.messages = $.extend(true, {
                view: kendo.spreadsheet.messages.view,
                tabs: kendo.spreadsheet.messages.view.tabs,
                menus: kendo.spreadsheet.messages.menus,
                workbook: {
                    defaultSheetName: "Sheet",
                }
            }, this.options.messages);

            this.element.addClass(Spreadsheet.classNames.wrapper);
            this.element.attr("role", "application");
            this._renderInnerContent(this.element);

            this._createFormulaInputs();

            that.spreadsheetRef = this._initSpreadsheetWidget();

            this._bindWorkbookEvents();

            const activeSheet = this.activeSheet();
            this._onUpdateTools({ range: activeSheet.range(activeSheet.activeCell()) });

            this._resizeHandler = function() { that.resize(); };
            $(window).on("resize" + NS, this._resizeHandler);

            if (this._showWatermarkOverlay) {
                this._showWatermarkOverlay(this.element[0]);
            }
        },
        _initSpreadsheetWidget: function() {
            const that = this;
            const options = removeEventsFromOptions(that.options, that.events);

            let widgetOptions = $.extend({},
                options, {
                sheetsbar: that.options.sheetsbar != false ? that.options.sheetsbar : {},
                formulaBarInputRef: new FormulaInputRefArgs({
                    list: this._formulaBarInputRefList,
                    popup: this._formulaBarInputRefPopup
                }),
                formulaCellInputRef: new FormulaInputRefArgs({
                    list: this._formulaCellInputRefList,
                    popup: this._formulaCellInputRefPopup
                }),
                nameBoxRef: new NameBoxRefArgs({
                    container: that.element.find('.k-spreadsheet-name-editor [ref-namebox-input]'),
                    nameEditor: function() {
                        return that.spreadsheetRef?.view.nameEditor;
                    }
                }),
                createFilterMenu: function(options) {
                    let element = $("<div></div>").appendTo(that.element);
                    return new kendo.spreadsheet.FilterMenu(element, options);
                },
                createTabStrip: function(options) {
                    if (that.options.toolbar) {
                        that._initHeader();

                        return {
                            focus: function() { that.menu.wrapper.trigger("focus"); },
                            select: function(name) { that.menu.element.find(`li[ref-tab-name="${name}"]`).click(); },
                            destroy: function() { that.menu.destroy(); }
                        };
                    }
                },
                createSheetEditor: function(options) {
                    that.sheetsBar._createEditor();
                },
                createSheetBar: function(openDialogCallback) {
                    that._sheetsBar(openDialogCallback);
                },
                createContextMenus: function(options) {
                    that._dialogs = [];
                    that._initContextMenus();
                },
                createSheetDataSource: function(dataSource) {
                    return kendo.data.DataSource.create(dataSource);
                },
                getWorkbookCommand: function(commandName, commandOptions) {
                    return new kendo.spreadsheet[commandName](commandOptions);
                },
                getIconHTMLString: function(iconName) {
                    return $(kendo.ui.icon(iconName))[0];
                },
                update: function(e) {
                    that._onUpdateTools(e);
                    if (e.reason.sheetSelection && that.sheetsBar) {
                        that.sheetsBar.renderSheets(e.sender.sheets(), e.sender.sheetIndex(e.sheet));
                    }
                },
                message: (args) => {
                    // exclude these properties from the options
                    let { sender, name, ref, range, ...dialogOptions } = args;
                    let opts = $.extend({}, { pdfExport: that.options.pdf, excelExport: that.options.excel }, dialogOptions);
                    let dialog = kendo.spreadsheet.dialogs.create(args.name, opts);
                    const saveAsCallback = function(data, fileName, exportOptions) {
                        kendo.saveAs({ dataURI: data, fileName, ...exportOptions });
                    };


                    if (dialog) {
                        dialog.bind("action", function(args) {
                            if (args.command == "SaveAsCommand") {
                                let { sender, ...commandArgs } = args;
                                let fileName = args.options.name + args.options.extension;
                                if (args.options.extension === ".xlsx") {
                                    return that.spreadsheetRef.saveAsExcel($.extend(commandArgs, {
                                        fileName: fileName,
                                        Workbook: kendo.ooxml.Workbook,
                                        saveAs: saveAsCallback
                                    }));
                                } else if (this.options.extension === ".pdf") {
                                    return that.spreadsheetRef.saveAsPDF($.extend(args.options.pdf, { workbook: this.options.workbook, fileName: fileName }));
                                }
                            }

                            that.spreadsheetRef.executeCommand(args);
                        });

                        dialog.bind("deactivate", () => {
                            dialog.destroy();
                            that._dialogs.pop();
                        });

                        that._dialogs.push(dialog);

                        dialog.open(args.range, args);
                        args.dialog = dialog;
                    }
                },
                contextmenu: (e) => {
                    const { objectRef, targetType, showUnhide, showUnmerge, originalEvent, isComposite } = e;
                    const selection = e.sender.activeSheet().select();
                    const { topLeft, bottomRight } = selection;

                    this._cellContextMenu.close();
                    this._colHeaderContextMenu.close();
                    this._rowHeaderContextMenu.close();
                    this._drawingContextMenu.close();

                    let menu;

                    if (targetType == "columnheader") {
                        menu = this._colHeaderContextMenu;
                    } else if (targetType == "rowheader") {
                        menu = this._rowHeaderContextMenu;
                    } else if (targetType == "drawing") {
                        menu = this._drawingContextMenu;
                    } else {
                        menu = this._cellContextMenu;
                    }

                    menu.element.find(COMPOSITE_UNAVAILABLE_ACTION_SELECTORS).toggle(!isComposite);
                    menu.element.find(UNHIDE_ACTION_SELECTORS).toggle(showUnhide);
                    menu.element.find('[data-action=unmerge]').toggle(showUnmerge);

                    // avoid the immediate close
                    setTimeout(function() {
                        menu.open(e.originalEvent.pageX, e.originalEvent.pageY);
                    });
                },
                locale: kendo.cultures.current.name,
                intl: kendo.kendoCultureToIntl(),
            });

            return new kendo.spreadsheet.commonEngine.SpreadsheetWidget(this.element[0], widgetOptions);
        },
        _createFormulaInputs: function() {
            let formulaBarInputRefElement = this.element.find('.k-spreadsheet-action-bar .k-spreadsheet-formula-input');
            let formulaBarInputRefListId = kendo.guid();
            formulaBarInputRefElement.attr("aria-controls", formulaBarInputRefListId);

            this._formulaBarInputRefList = new FormulaInputStaticList($(`<ul id="${formulaBarInputRefListId}" />`)
                .addClass(FormulaInput.classNames.listWrapper)
                .insertAfter(formulaBarInputRefElement), {
                aria: {
                    role: 'menu',
                    itemRole: 'menuitem'
                },
                autoBind: false,
                selectable: true,
                dataTextField: "text",
                dataValueField: "value",
                template: ({ text, value }) => `${text || value}`
            });

            this._formulaBarInputRefList.element.on("mousedown", function(e) {
                e.preventDefault();
            });

            this._formulaBarInputRefPopup = new kendo.ui.Popup(this._formulaBarInputRefList.element, {
                anchor: this.element.find(".k-spreadsheet-formula-input:not(.k-spreadsheet-cell-editor)"),
                open: function(ev) { formulaBarInputRefElement.attr("aria-expanded", true); },
                close: function(ev) { formulaBarInputRefElement.attr("aria-expanded", false); }
            });

            let formulaCellInputRefElement = this.element.find('.k-spreadsheet-cell-editor');
            let formulaCellInputRefListId = kendo.guid();
            formulaCellInputRefElement.attr("aria-controls", formulaCellInputRefListId);

            this._formulaCellInputRefList = new FormulaInputStaticList($(`<ul id="${formulaCellInputRefListId}" />`)
                .addClass(FormulaInput.classNames.listWrapper)
                .insertAfter(formulaCellInputRefElement), {
                aria: {
                    role: 'menu',
                    itemRole: 'menuitem'
                },
                autoBind: false,
                selectable: true,
                dataValueField: "value",
                dataTextField: "text",
                template: ({ text, value }) => `${text || value}`

            });

            this._formulaCellInputRefList.element.on("mousedown", function(e) {
                e.preventDefault();
            });

            this._formulaCellInputRefPopup = new kendo.ui.Popup(this._formulaCellInputRefList.element, {
                anchor: this.element.find(DOT + commonEngine.View.classNames.cellEditor),
                open: function(ev) { formulaCellInputRefElement.attr("aria-expanded", true); },
                close: function(ev) { formulaCellInputRefElement.attr("aria-expanded", false); }
            });
        },
        _initContextMenus: function() {
            let that = this;

            let contextMenuConfig = {
                target: this.element,
                animation: false,
                showOn: "never" // this is just an invalid event name to prevent the show
            };

            this._cellContextMenu = new kendo.spreadsheet.ContextMenu($(CELL_CONTEXT_MENU(that.options)).appendTo(that.element), contextMenuConfig);

            this._colHeaderContextMenu = new kendo.spreadsheet.ContextMenu($(COL_HEADER_CONTEXT_MENU(that.options)).appendTo(that.element), contextMenuConfig);

            this._rowHeaderContextMenu = new kendo.spreadsheet.ContextMenu($(ROW_HEADER_CONTEXT_MENU(that.options)).appendTo(that.element), contextMenuConfig);

            this._drawingContextMenu = new kendo.spreadsheet.ContextMenu($(DRAWING_CONTEXT_MENU(that.options)).appendTo(that.element), contextMenuConfig);

            this._cellContextMenu.bind("select", this.onContextMenuSelect.bind(this));
            this._rowHeaderContextMenu.bind("select", this.onContextMenuSelect.bind(this));
            this._colHeaderContextMenu.bind("select", this.onContextMenuSelect.bind(this));
            this._drawingContextMenu.bind("select", this.onContextMenuSelect.bind(this));
        },
        onContextMenuSelect: function(e) {
            let that = this;
            let action = $(e.item).data("action");
            let command;
            switch (action) {
                case "cut":
                    command = { command: "ToolbarCutCommand", options: { workbook: this.spreadsheetRef.workbook } };
                    break;
                case "copy":
                    command = { command: "ToolbarCopyCommand", options: { workbook: this.spreadsheetRef.workbook } };
                    break;
                case "paste":
                    command = { command: "ToolbarPasteCommand", options: { workbook: this.spreadsheetRef.workbook } };
                    break;
                case "delete-drawing":
                    command = { command: "DeleteDrawingCommand", options: { drawing: this.spreadsheetRef._controller.navigator._sheet._activeDrawing } };
                    break;
                case "bring-to-front":
                    command = { command: "BringToFrontCommand", options: { drawing: this.spreadsheetRef._controller.navigator._sheet._activeDrawing } };
                    break;
                case "send-to-back":
                    command = { command: "SendToBackCommand", options: { drawing: this.spreadsheetRef._controller.navigator._sheet._activeDrawing } };
                    break;
                case "unmerge":
                    command = { command: "MergeCellCommand", options: { value: "unmerge" } };
                    break;
                case "merge":
                    this.spreadsheetRef.openDialog("merge");
                    break;
                case "hide-row":
                    command = { command: "HideLineCommand", options: { axis: "row" } };
                    break;
                case "hide-column":
                    command = { command: "HideLineCommand", options: { axis: "column" } };
                    break;
                case "unhide-row":
                    command = { command: "UnHideLineCommand", options: { axis: "row" } };
                    break;
                case "unhide-column":
                    command = { command: "UnHideLineCommand", options: { axis: "column" } };
                    break;
                case "delete-row":
                    command = { command: "DeleteRowCommand" };
                    break;
                case "delete-column":
                    command = { command: "DeleteColumnCommand" };
                    break;
            }

            if (command) {
                that.spreadsheetRef.executeCommand(command);
            }
        },
        _onUpdateTools: function(e) {
            let reason = e.reason;
            if (reason && (reason.overElement || reason.comment)) {
                return;
            }
            this.toolbar?.refresh(e.range);
        },
        _getCopyRegex: function(sheetName) {
            const newName = sheetName.replaceAll('(', '\\(').replaceAll(')', '\\)');
            const st = `(${newName})\\s?\\(`;
            return new RegExp(st, 's');
        },
        _sheetsBar: function(openDialogCallback) {
            let that = this;

            if (that.options.sheetsbar) {
                let sheetsbarOptions = $.extend(true, {
                    openDialog: openDialogCallback,
                }, that.options.sheetsbar);

                that.sheetsBar = new SheetsBar(that.element[0].querySelector('[ref-sheets-bar]'), sheetsbarOptions);

                that.sheetsBar.bind("select", function onSheetSelect(ev) {
                    if (ev.isAddButton) {
                        that.spreadsheetRef.view.sheetsbar.onAddSelect();
                    } else {
                        that.spreadsheetRef.view.sheetsbar.onSheetSelect(ev.name);
                    }
                });

                that.sheetsBar.bind("reorder", function onSheetReorderEnd(ev) {
                    that.spreadsheetRef.view.sheetsbar.onSheetReorderEnd(ev);
                });

                that.sheetsBar.bind("rename", function onSheetRename(ev) {
                    that.spreadsheetRef.view.sheetsbar.onSheetRename(ev.name, ev.sheetIndex);
                });

                that.sheetsBar.bind("duplicate", function onSheetDuplicate(ev) {
                    let name = ev.name;
                    let sheetToCopy = that.sheetByName(name);
                    let sheetIndex = that.sheetIndex(sheetToCopy);
                    let copies = 0;
                    const regex = that._getCopyRegex(name);
                    that.sheets().forEach(sheet => {
                        const isPresent = regex.test(sheet.name());
                        if (isPresent) {
                            copies += 1;
                        }
                    });

                    const newName = `${name} (${copies + 1})`;
                    that.insertSheet({ data: { ...sheetToCopy.toJSON(), name: newName }, index: sheetIndex + 1 });
                    that.spreadsheetRef.view.sheetsbar.onSheetSelect(newName);
                });

                that.sheetsBar.bind("remove", function onSheetBarRemove(ev) {
                    that.spreadsheetRef.view.sheetsbar.onSheetRemove(ev.name);
                });

                that.sheetsBar.bind("hide", function onSheetBarHide(ev) {
                    let sheetName = ev.name;
                    let sheetToHide = that.sheetByName(sheetName);
                    let visibleSheets = that.sheets().filter(x=> x.state() === "visible");
                    let visibleSheetIndex = visibleSheets.findIndex(x=> x.name() === sheetName);
                    let nextVisibleSheet = visibleSheets[visibleSheetIndex + 1] || visibleSheets[visibleSheetIndex - 1];
                    sheetToHide._state('hidden');

                    that.spreadsheetRef.workbook._sheetsSearchCache = {};
                    that.spreadsheetRef.view.sheetsbar.onSheetSelect(nextVisibleSheet.name());
                });

                that.sheetsBar.bind("show", function onSheetBarShow(ev) {
                    let sheetName = ev.name;
                    that.sheetByName(sheetName)._state('visible');
                    that.spreadsheetRef.workbook._sheetsSearchCache = {};
                    that.spreadsheetRef.view.sheetsbar.onSheetSelect(ev.name);
                });
            }
        },
        _createToolBar: function(options, tabs, toolbarId) {
            let that = this;

            if (that.toolbar) {
                that.toolbar.destroy();
                that.toolbar.element.empty();
            }

            let activeTabIndex = tabs.findIndex((item) => item.id === toolbarId);
            let activeToolbar = tabs[activeTabIndex];
            let tools = options[activeToolbar.id];

            that._activeToolBar = activeToolbar.text;

            that.toolbar = new kendo.spreadsheet.ToolBar(this.element.find("[ref-header-toolbar]"), {
                tools: typeof tools === "boolean" ? undefined : tools,
                overflow: $.isPlainObject(options.overflow) ? options.overflow : undefined,
                toolbarName: activeToolbar.id,
                fillMode: "flat",
                action: function(args) {
                    if (args.command === 'undo' || args.command === 'redo') {
                        that.spreadsheetRef.workbook.undoRedoStack[args.command]();
                        return;
                    }

                    if (args.command == "OpenCommand") {
                        args.options.file = args.options.value;
                    } else if (args.command === "BorderChangeCommand") {
                        args.options.border = args.options.border || args.options.value.type;
                        args.options.style = args.options.style || args.options.value.style || { color: args.options.value.color, size: 1 };
                    }

                    that.spreadsheetRef.executeCommand(args);
                },
                dialog: function(args) {
                    that.spreadsheetRef.openDialog(args.name, args.options);
                }
            });
            that.toolbar.element.attr("aria-label", activeToolbar.text);
        },

        _initHeader: function() {
            let that = this;
            let messages = this.options.messages.tabs;
            let options = $.extend(true, { file: true, home: true, insert: true, format: true, data: true, view: true }, this.options.toolbar);
            let tabs = [];

            if (this.menu) {
                this.menu.destroy();
                this.menu.empty();
            }

            if (this.toolbar) {
                this.toolbar.destroy();
                this.toolbar.empty();
            }

            for (let name in options) {
                if (options[name] === true || options[name] instanceof Array) {
                    tabs.push({ id: name, text: messages[name], content: "", attr: { "ref-tab-name": name } });
                }
            }

            this.menu = new kendo.spreadsheet.Menu(this.element.find("[ref-header-menu]"), {
                dataSource: tabs,
                toolbarOptions: options,
                select: function(e) {
                    let text = $(e.item).text();
                    if (that._activeToolBar !== text) {
                        $(e.item).addClass(K_ACTIVE)
                            .siblings("li").removeClass(K_ACTIVE);

                        that._createToolBar(options, tabs, tabs.find((item) => item.text === text).id);
                        const activeSheet = that.activeSheet();
                        that._onUpdateTools({ range: activeSheet.range(activeSheet.activeCell()) });
                    }
                }
            });

            if (tabs.length) {
                let defaultTab = tabs.find((item)=> item.id === "home");
                this.menu.element.find(`li[ref-tab-name="home"]`).addClass(K_ACTIVE);
                that._createToolBar(options, tabs, defaultTab ? defaultTab.id : tabs[0].id);
            }
        },

        executeCommand(options) {
            return this.spreadsheetRef.executeCommand(options);
        },

        _resize: function() {
            this.refresh({ layout: true });
        },

        _workbookRender: function(e) {
            if (this.trigger("render", e)) {
                e.preventDefault();
            }
        },

        _workbookChanging: function(e) {
            if (this.trigger("changing", e)) {
                e.preventDefault();
            }
        },

        _workbookChange: function(e) {
            if (this.trigger("change", { range: e.range })) {
                e.preventDefault();
            }
        },

        _workbookCut: function(e) {
            this.trigger("cut", e);
        },

        _workbookCopy: function(e) {
            this.trigger("copy", e);
        },

        _workbookPaste: function(e) {
            this.trigger("paste", e);
        },

        activeSheet: function(sheet) {
            return this.spreadsheetRef.activeSheet(sheet);
        },

        moveSheetToIndex: function(sheet, index) {
            return this.spreadsheetRef.moveSheetToIndex(sheet, index);
        },

        insertSheet: function(options) {
            return this.spreadsheetRef.insertSheet(options);
        },

        sheets: function() {
            return this.spreadsheetRef.sheets();
        },

        removeSheet: function(sheet) {
            return this.spreadsheetRef.removeSheet(sheet);
        },

        sheetByName: function(sheetName) {
            return this.spreadsheetRef.sheetByName(sheetName);
        },

        sheetIndex: function(sheet) {
            return this.spreadsheetRef.sheetIndex(sheet);
        },

        sheetByIndex: function(index) {
            return this.spreadsheetRef.sheetByIndex(index);
        },

        renameSheet: function(sheet, newSheetName) {
            return this.spreadsheetRef.renameSheet(sheet, newSheetName);
        },

        refresh: function(reason) {
            this.spreadsheetRef.refresh(reason);

            return this;
        },

        openDialog: function(name, options) {
            return this.spreadsheetRef.openDialog(name, options);
        },

        autoRefresh: function(value) {
            if (value !== undefined) {
                this._autoRefresh = value;

                if (value === true) {
                    this.refresh();
                }

                return this;
            }

            return this._autoRefresh;
        },

        toJSON: function() {
            return this.spreadsheetRef.toJSON();
        },

        fromJSON: function(json) {
            this.spreadsheetRef.fromJSON(json);
        },

        saveJSON: function() {
            return this.spreadsheetRef.saveJSON();
        },

        fromFile: function(blob, name) {
            return this.spreadsheetRef.fromFile(blob, name);
        },

        saveAsPDF: function(options) {
            this.spreadsheetRef.saveAsPDF(
                $.extend({}, this.options.pdf, options, { workbook: this.spreadsheetRef._workbook })
            );
        },

        saveAsExcel: function(options) {
            const exportOps = $.extend({}, this.options.excel, options, {
                Workbook: kendo.ooxml.Workbook,
                saveAs: function(data, fileName, exportOptions) {
                    kendo.saveAs({ dataURI: data, fileName, ...exportOptions });
                }
            });
            this.spreadsheetRef.saveAsExcel(exportOps);
        },

        draw: function(options, callback) {
            this.spreadsheetRef.draw(options, callback);
        },

        _workbookExcelExport: function(e) {
            if (this.trigger("excelExport", e)) {
                e.preventDefault();
            }
        },

        _workbookExcelImport: function(e) {
            const eventData = {
                file: e.file,
                sender: e.sender,
                preventDefault: e.preventDefault,
                isDefaultPrevented: e.isDefaultPrevented,
                promise: convertSpreadsheetDeferredToJQueryDeferred(e.deferred),
                _defaultPrevented: e._defaultPrevented,
            };

            if (this.trigger("excelImport", eventData)) {
                e.preventDefault();
            } else {
                this._initProgress(e.deferred);
            }
        },

        _initProgress: function(deferred) {
            let loading =
                $("<div class='k-loading-mask' " +
                    "style='width: 100%; height: 100%; top: 0;'>" +
                    "<div class='k-loading-color'></div>" +
                    "</div>")
                    .appendTo(this.element);

            let pb = $("<div class='k-loading-progress'>")
                .appendTo(loading)
                .kendoProgressBar({
                    type: "chunk", chunkCount: 10,
                    min: 0, max: 1, value: 0
                }).data("kendoProgressBar");

            deferred.progress(function(e) {
                pb.value(e.progress);
            })
                .promise.finally(function() {
                    kendo.destroy(loading);
                    loading.remove();
                });
        },

        _workbookPdfExport: function(e) {
            if (this.trigger("pdfExport", e)) {
                e.preventDefault();
            }
        },

        _workbookInsertSheet: function(e) {
            if (this.trigger("insertSheet", e)) {
                e.preventDefault();
            }
        },

        _workbookRemoveSheet: function(e) {
            if (this.trigger("removeSheet", e)) {
                e.preventDefault();
            }
        },

        _workbookSelectSheet: function(e) {
            if (this.trigger("selectSheet", e)) {
                e.preventDefault();
            }
        },

        _workbookRenameSheet: function(e) {
            if (this.trigger("renameSheet", e)) {
                e.preventDefault();
            }
        },

        _workbookInsertRow: function(e) {
            if (this.trigger("insertRow", e)) {
                e.preventDefault();
            }
        },

        _workbookInsertColumn: function(e) {
            if (this.trigger("insertColumn", e)) {
                e.preventDefault();
            }
        },

        _workbookDeleteRow: function(e) {
            if (this.trigger("deleteRow", e)) {
                e.preventDefault();
            }
        },

        _workbookDeleteColumn: function(e) {
            if (this.trigger("deleteColumn", e)) {
                e.preventDefault();
            }
        },

        _workbookHideRow: function(e) {
            if (this.trigger("hideRow", e)) {
                e.preventDefault();
            }
        },

        _workbookHideColumn: function(e) {
            if (this.trigger("hideColumn", e)) {
                e.preventDefault();
            }
        },

        _workbookUnhideRow: function(e) {
            if (this.trigger("unhideRow", e)) {
                e.preventDefault();
            }
        },

        _workbookUnhideColumn: function(e) {
            if (this.trigger("unhideColumn", e)) {
                e.preventDefault();
            }
        },

        _workbookSelect: function(e) {
            this.trigger("select", e);
        },

        _workbookChangeFormat: function(e) {
            this.trigger("changeFormat", e);
        },

        _workbookDataBinding: function(e) {
            if (this.trigger("dataBinding", e)) {
                e.preventDefault();
            }
        },

        _workbookDataBound: function(e) {
            this.trigger("dataBound", e);
        },

        _workbookProgress: function(e) {
            kendo.ui.progress(this.element, e.toggle);
        },

        _bindWorkbookEvents: function() {
            this.spreadsheetRef.bind("render", this._workbookRender.bind(this));
            this.spreadsheetRef.bind("cut", this._workbookCut.bind(this));
            this.spreadsheetRef.bind("copy", this._workbookCopy.bind(this));
            this.spreadsheetRef.bind("paste", this._workbookPaste.bind(this));
            this.spreadsheetRef.bind("changing", this._workbookChanging.bind(this));
            this.spreadsheetRef.bind("change", this._workbookChange.bind(this));
            this.spreadsheetRef.bind("excelExport", this._workbookExcelExport.bind(this));
            this.spreadsheetRef.bind("excelImport", this._workbookExcelImport.bind(this));
            this.spreadsheetRef.bind("pdfExport", this._workbookPdfExport.bind(this));
            this.spreadsheetRef.bind("insertSheet", this._workbookInsertSheet.bind(this));
            this.spreadsheetRef.bind("removeSheet", this._workbookRemoveSheet.bind(this));
            this.spreadsheetRef.bind("selectSheet", this._workbookSelectSheet.bind(this));
            this.spreadsheetRef.bind("renameSheet", this._workbookRenameSheet.bind(this));
            this.spreadsheetRef.bind("insertRow", this._workbookInsertRow.bind(this));
            this.spreadsheetRef.bind("insertColumn", this._workbookInsertColumn.bind(this));
            this.spreadsheetRef.bind("deleteRow", this._workbookDeleteRow.bind(this));
            this.spreadsheetRef.bind("deleteColumn", this._workbookDeleteColumn.bind(this));
            this.spreadsheetRef.bind("hideRow", this._workbookHideRow.bind(this));
            this.spreadsheetRef.bind("hideColumn", this._workbookHideColumn.bind(this));
            this.spreadsheetRef.bind("unhideRow", this._workbookUnhideRow.bind(this));
            this.spreadsheetRef.bind("unhideColumn", this._workbookUnhideColumn.bind(this));
            this.spreadsheetRef.bind("select", this._workbookSelect.bind(this));
            this.spreadsheetRef.bind("changeFormat", this._workbookChangeFormat.bind(this));
            this.spreadsheetRef.bind("dataBinding", this._workbookDataBinding.bind(this));
            this.spreadsheetRef.bind("dataBound", this._workbookDataBound.bind(this));
            this.spreadsheetRef.bind("progress", this._workbookProgress.bind(this));
        },

        destroy: function() {
            kendo.ui.Widget.fn.destroy.call(this);

            this._formulaBarInputRefPopup?.destroy();
            this._formulaCellInputRefPopup?.destroy();
            this._formulaBarInputRefList?.destroy();
            this._formulaCellInputRefList?.destroy();

            this._formulaBarInputRefList = this._formulaCellInputRefList = null;
            this._formulaBarInputRefPopup = this._formulaCellInputRefPopup = null;

            if (this.menu) {
                this.menu.destroy();
            }

            if (this.toolbar) {
                this.toolbar.destroy();
            }

            if (this.sheetsBar) {
                this.sheetsBar.destroy();
            }

            this._dialogs?.forEach(function(dialog) {
                dialog?.destroy();
            });

            this.spreadsheetRef?.destroy();

            this._cellContextMenu?.destroy();

            this._rowHeaderContextMenu?.destroy();
            this._colHeaderContextMenu?.destroy();
            this._drawingContextMenu?.destroy();

            this._cellContextMenu = this._rowHeaderContextMenu =
                this._colHeaderContextMenu =
                this._drawingContextMenu = null;

            if (this._resizeHandler) {
                $(window).off("resize" + NS, this._resizeHandler);
            }

            kendo.destroy(this.element);
        },

        options: {
            name: "Spreadsheet",
            toolbar: true,
            sheetsbar: true,
            rows: 200,
            columns: 50,
            rowHeight: 30,
            columnWidth: 64,
            headerHeight: 30,
            headerWidth: 32,
            excel: {
                proxyURL: "",
                fileName: "Workbook.xlsx"
            },
            messages: {},
            pdf: {
                // which part of the workbook to be exported
                area: "workbook",
                fileName: "Workbook.pdf",
                proxyURL: "",
                // paperSize can be an usual name, i.e. "A4", or an array of two Number-s specifying the
                // width/height in points (1pt = 1/72in), or strings including unit, i.e. "10mm".  Supported
                // units are "mm", "cm", "in" and "pt".  The default "auto" means paper size is determined
                // by content.
                paperSize: "a4",
                // True to reverse the paper dimensions if needed such that width is the larger edge.
                landscape: true,
                // An object containing { left, top, bottom, right } margins with units.
                margin: null,
                // Optional information for the PDF Info dictionary; all strings except for the date.
                title: null,
                author: null,
                subject: null,
                keywords: null,
                creator: "Kendo UI PDF Generator v." + kendo.version,
                // Creation Date; defaults to new Date()
                date: null
            },
            defaultCellStyle: {
                fontFamily: "Arial",
                fontSize: 12
            },
            useCultureDecimals: false
        },

        defineName: function(name, value, hidden) {
            return this.spreadsheetRef.defineName(name, value, hidden);
        },

        undefineName: function(name) {
            return this.spreadsheetRef.undefineName(name);
        },

        nameValue: function(name) {
            return this.spreadsheetRef.nameValue(name);
        },

        forEachName: function(func) {
            return this.spreadsheetRef.forEachName(func);
        },

        cellContextMenu: function() {
            return this._cellContextMenu;
        },

        rowHeaderContextMenu: function() {
            return this._rowHeaderContextMenu;
        },

        colHeaderContextMenu: function() {
            return this._colHeaderContextMenu;
        },

        addImage: function(image) {
            return this.spreadsheetRef.addImage(image);
        },

        cleanupImages: function() {
            return this.spreadsheetRef.cleanupImages();
        },

        events: [
            "cut",
            "copy",
            "paste",
            "pdfExport",
            "excelExport",
            "excelImport",
            "changing",
            "change",
            "render",
            "removeSheet",
            "selectSheet",
            "renameSheet",
            "insertRow",
            "insertColumn",
            "deleteRow",
            "insertSheet",
            "deleteColumn",
            "hideRow",
            "hideColumn",
            "unhideRow",
            "unhideColumn",
            "select",
            "changeFormat",
            "dataBinding",
            "dataBound"
        ]
    });

    kendo.ui.plugin(Spreadsheet);
    $.extend(true, Spreadsheet, { classNames: classNames });
})(window.kendo);
export default kendo;

