/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.menu.js";
import "./kendo.excel.js";
import "./kendo.progressbar.js";
import "./kendo.treelist.js";
import "./propertygrid/contextmenu.js";
import "./propertygrid/commands.js";
import "./kendo.ooxml.js";
import { ExcelExporter } from "@progress/kendo-ooxml";

export const __meta__ = {
    id: "propertygrid",
    name: "PropertyGrid",
    category: "web",
    description: "The PropertyGrid widget displays objects as hierarchical structure and provides means to edit the properties and attributes of objects.",
    depends: [ "excel", "menu", "treelist" ]
};

(function($, undefined) {
    const kendo = window.kendo,
        ui = kendo.ui,
        NS = ".kendoPropertyGrid",
        extend = $.extend,
        kendoDom = kendo.dom,
        kendoDomElement = kendoDom.element,
        kendoHtmlElement = kendoDom.html,
        kendoTextElement = kendoDom.text,
        keys = $.extend({ F10: 121 }, kendo.keys),
        Editable = ui.Editable,
        TreeList = ui.TreeList,
        isArray = Array.isArray,
        isPlainObject = $.isPlainObject,
        CHANGE = "change",
        CLICK = "click",
        EDIT = "edit",
        EXPAND = "expand",
        COLLAPSE = "collapse",
        GROUP_EXPAND = "groupExpand",
        GROUP_COLLAPSE = "groupCollapse",
        COLUMNRESIZE = "columnResize",
        CELL_CLOSE = "cellClose",
        SAVE = "save",
        CANCEL = "cancel",
        BEFORE_EDIT = "beforeEdit",
        PDF_EXPORT = "pdfExport",
        CARET_ALT_DOWN = "a[class*='-i-caret-alt-down']",
        CARET_ALT_RIGHT = "a[class*='-i-caret-alt-right']",
        DOT = ".",
        GROUPING_ROW = "k-grouping-row",
        TR = "tr",
        TD = "td",
        ARIA_LABEL = "aria-label",
        COLLAPSE_ICON = "caret-alt-down",
        EXPAND_ICON = "caret-alt-right";

    const defaultBodyContextMenu = [
        "copy",
        "copyName",
        "copyDeclaration",
        "separator",
        "reset",
        "separator",
        "resize",
        "separator",
        "expandItem",
        "collapseItem",
        "separator"
    ];

    const defaultCommands = {
        group: {
            name: "group",
            type: "button",
            text: 'Group Items',
            togglable: true,
            showText: "overflow",
            icon: "categorize",
            commandName: "ToggleGroupLayout",
            attributes: {
                title: "Group Items"
            },
            overflow: "never"
        },
        details: {
            name: "details",
            type: "button",
            text: 'Toggle Info box',
            togglable: true,
            icon: "info-circle",
            commandName: "ToggleDetails",
            showText: "overflow",
            attributes: {
                title: "Toggle Info box"
            },
            overflow: "never"
        },
        sort: {
            name: "sort",
            component: "DropDownList",
            componentOptions: {
                commandOn: CHANGE,
                dataTextField: "text",
                dataValueField: "value",
                dataSource: [
                    { text: "Default Sort", value: "none" },
                    { text: "Sort A-Z", value: "asc" },
                    { text: "Sort Z-A", value: "desc" }
                ],
            },
            attributes: {
                class: "k-property-grid-sort",
                title: "Sort"
            },
            overflow: "never"
        },
        search: {
            name: "search",
            component: "TextBox",
            componentOptions: {
                prefixOptions: {
                    icon: "search"
                },
                placeholder: "Search...",
            },
            attributes: {
                class: "k-grid-search"
            },
            overflow: "never"
        },
        separator: {
            name: "separator",
            type: "separator",
        },
        spacer: {
            name: "spacer",
            type: "spacer",
        },
        excel: {
            name: "excel",
            type: "button",
            text: "Export to Excel",
            showText: false,
            icon: "file-excel",
            commandName: "ExcelExport",
            overflow: "auto"
        },
        pdf: {
            name: "excel",
            type: "button",
            text: "Export to PDF",
            showText: false,
            icon: "file-pdf",
            commandName: "PDFExport",
            overflow: "auto"
        },
    };

    function isNullorUndefined(value) {
        return value === undefined || value === null;
    }

    function isCollection(value) {
        return typeof value === 'object' && isArray(value);
    }

    function isComplexObject(value) {
        return typeof value === 'object' && !kendo.isDate(value);
    }

    function isEditable(value) {
        return (kendo.type(value) != 'object' && kendo.type(value) != 'function');
    }

    function isColumnEditable(column, model) {
        if (!column || !model || !column.field || column.selectable || column.command || column.draggable || (column.editable && !column.editable(model))) {
            return false;
        }

        return (column.field && model.editable && model.editable(column.field));
    }

    function asArray(obj) {
        return Object.entries(obj).map(x=>x[1]);
    }

    let PropertyGridDataSource = kendo.data.TreeListDataSource.extend({
        groupedItemsTree: function(field) {
            let map = this._childrenMap(this.view()),
                defaultParentId = this._defaultParentId(),
                currentChildren = map[defaultParentId] || [],
                grouped = new kendo.data.Query(currentChildren).group({ field: field }).toArray();

            return grouped;
        },
        filterGroupedItemsTree: function(expression, field) {
            let dataSource = this;
            let sort = this.sort() || {};
            let filterExpression = expression || { filters: [], logic: "or" };
            let map = this._childrenMap(this.data()),
            defaultParentId = this._defaultParentId(),
            currentChildren = map[defaultParentId] || [],
            groups = new kendo.data.Query(currentChildren).sort(sort).group({ field: field }).toArray();
            let filterItems = function(filterExpression, items) {
                let filtered = [];
                for (let i = 0; i < items.length; i++) {
                    if (items[i].hasChildren && filterItems(filterExpression, dataSource.childNodes(items[i])).length > 0) {
                        filtered.push(items[i]);
                    }
                }

                return filtered.length != 0 ? filtered : new kendo.data.Query(items).sort(sort).filter(filterExpression).toArray();
            };
            let filteredGroups = [];
            groups.forEach(function(itm) {
                if (itm.items) {
                    itm.items = filterItems(filterExpression,itm.items);
                }
                filteredGroups.push(itm);
            });

            return filteredGroups;
        }
    });

    const DynamicInCellEditor = kendo.ui.treelist.editor.extend({
        createEditable: function() {
            let options = this.options;

            this.fields[0].editor = options.model.editor;
            this.fields[0].editorOptions = { ...options.model.editorOptions };

            this.editable = new Editable(this.wrapper, {
                fields: this.fields,
                target: options.target,
                clearContainer: options.clearContainer,
                model: this.model,
                change: options.change,
            });

        },
        destroy: function() {
            let that = this;

            that.editable.destroy();

            that.editable.element
                .off()
                .empty()
                .removeAttr(kendo.attr("role"));

            that.model = that.wrapper = that.element = that.columns = that.editable = null;
        }
    });

    const PropertyGrid = TreeList.extend({
        init: function(element, options) {
            const that = this;
            that._processOptions(options);
            that.options = extend(true, {}, that.options, options);
            that._processedModel = that._processModel();
            that._createDataSource();

            TreeList.fn.init.call(that, element, that.options);
            that._wrapper();
            that._detailsBox();
            that._groupable();

            kendo.notify(that);
        },

        options: {
            name: 'PropertyGrid',
            model: {},
            items: [],
            columns: [{ field: "field" },{ field: "value", editable: function(e) { return isEditable(e.value); } }],
            hasHeader: false,
            groupable: true,
            resizable: true,
            contextMenu: false,
            sortable: true,
            selectable: true,
            scrollable: true,
            showDetails: true,
            editable: "incell",
            size: undefined,
            defaultTools: [defaultCommands.search, defaultCommands.sort, defaultCommands.group, defaultCommands.details],
            messages: {
                defaultGroupName: "Other"
            }
        },

        events: [
            BEFORE_EDIT,
            EDIT,
            CHANGE,
            EXPAND,
            COLLAPSE,
            COLUMNRESIZE,
            GROUP_EXPAND,
            GROUP_COLLAPSE,
            CELL_CLOSE,
            SAVE,
            CANCEL,
            PDF_EXPORT
        ],

        defaultEditors: {
            string: "TextBox",
            date: "DatePicker",
            number: "NumericTextBox",
            boolean: "CheckBox"
        },

        _groupContentTemplate: ({ groupName, action, icon }) => `<p class="k-reset">${kendo.ui.icon($('<a href="#" tabindex="-1" ' + ARIA_LABEL + '=' + action + '></a>'), icon)}${groupName}</p>`,

        _detailsTemplate: ({ description }) => `<span>${description || '&nbsp;'}</span>`,

        _setEditable: function(value) {
            const that = this;
            that.options.editable = value;
        },

        _processOptions: function(options) {
            const that = this;

            kendo.type(options.editMode) === "boolean" && !options.editMode ? that._setEditable(false) : that._setEditable("incell");
            if (isNullorUndefined(options.toolbar)) {
                options.toolbar = that.options.defaultTools;
            } else if (isPlainObject(options.toolbar)) {
                options.toolbar.items = options.toolbar.items || that.options.defaultTools;
            }

            that._extendColumnOptions(options);
        },

        _extendColumnOptions: function(options) {
            const that = this;
            if (!options.columns) {
                return;
            }
            that.options.columns[0] = extend(true, options.columns.fieldColumn, that.options.columns[0]);
            that.options.columns[1] = extend(true, options.columns.valueColumn, that.options.columns[1]);
            delete options.columns;
        },

        _processSettings: function(obj, settings) {
            const that = this;
            settings.forEach((item) => {
                if (obj[item.field]) {
                    extend(obj[item.field], item);

                    if (obj[item.field].items) {
                        that._processSettings(obj[item.field].value, obj[item.field].items);
                    }
                }
            });
        },

        _processModel: function() {
            const that = this,
            model = that.options.model,
            options = that.options;

            let flatData = that._flatten(model);
            that._setDefaults(flatData);
            that._processSettings(flatData, options.items );

            return asArray(flatData);
        },

        _createDataSource: function() {
            const that = this,
            dataSource = new PropertyGridDataSource({
                data: that._processedModel,
                serverGrouping: false,
                schema: {
                    model: {
                        id: "id",
                        parentId: "parentId",
                        expanded: true,
                        fields: {
                            field: { editable: false },
                            value: { editable: true }
                        }
                    },
                }
            });

            that.options.dataSource = dataSource;
        },

        _flatten: function(model) {
            let id = 1;

            function appendNested(result, key, nestedKeys, nestedObject, collectionIdx) {
                if (!isNullorUndefined(collectionIdx)) {
                    nestedKeys.forEach (nestedKey => {
                        result[`${key}.${collectionIdx}.${nestedKey}`] = nestedObject[nestedKey];
                        result[`${key}.${collectionIdx}.${nestedKey}`].parentId = result[`${key}.${collectionIdx}`].id;
                    });
                } else {
                    nestedKeys.forEach (nestedKey => {
                        result[`${key}.${nestedKey}`] = nestedObject[nestedKey];
                    });
                }
            }

            function flattenCollection(result, obj, key, parentId) {
                const collection = obj[key];

                result[key] = {
                    id: id++,
                    parentId: parentId,
                    propType: "Array",
                    field: key,
                    value: collection,
                };

                for (let i = 0; i < collection.length; i++) {
                    const nested = typeof collection[i] === "object" ?
                        flattenObject(collection[i], result[key].id) :
                        collection[i];

                    result[`${key}.${i}`] = {
                        id: id++,
                        parentId: result[key].id,
                        propType: typeof obj[key],
                        field: `${key}[${i}]`,
                        value: nested
                    };

                    if (typeof collection[i] === "object") {
                        const nestedKeys = Object.keys(nested);
                        appendNested(result, key, nestedKeys, nested, i);
                    }
                }
            }

            function flattenObject(obj, parentId) {
                const result = {},
                keys = Object.keys(obj || { } );

                keys.forEach ((key) => {
                    if (isNullorUndefined(obj[key])) {
                        result[key] = {
                            id: id++,
                            parentId: parentId,
                            propType: typeof obj[key],
                            field: key,
                            value: obj[key]
                        };
                    } else if (isCollection(obj[key])) {
                        flattenCollection(result, obj, key, parentId);
                    } else if (isComplexObject(obj[key])) {
                        let tempId = id++;
                        const nestedObject = flattenObject(obj[key], tempId);
                        const nestedKeys = Object.keys(nestedObject);

                        result[key] = {
                            id: tempId,
                            parentId: parentId,
                            propType: typeof obj[key],
                            field: key,
                            value: nestedObject,
                        };

                        appendNested(result, key, nestedKeys, nestedObject);
                    } else {
                        let parsed = kendo.parseDate(obj[key].toString());
                        result[key] = {
                            id: id++,
                            parentId: parentId,
                            propType: parsed != null ? typeof parsed : typeof obj[key],
                            field: key,
                            value: parsed != null ? parsed : obj[key]
                        };
                    }
                });

                return result;
            }

            return flattenObject(model, null);
        },

        _setDefaults: function(obj) {
            const that = this;

            Object.keys(obj).forEach(key => {
                if (that.options.groupable && !obj[key].group) {
                    obj[key].group = that.options.messages.defaultGroupName;
                }
                that._setDefaultEditor(obj[key]);
            });
        },

        _setDefaultEditor: function(obj) {
            const that = this,
            type = kendo.type(obj.value);
            switch (type) {
                case "date":
                    obj.editor = that.defaultEditors.date;
                    break;
                case "boolean":
                    obj.editor = that.defaultEditors.boolean;
                    break;
                case "number":
                    obj.editor = that.defaultEditors.number;
                    break;
                default:
                    obj.editor = that.defaultEditors.string;
                    break;
            }
        },

        _createIncellEditor: function(cell, options) {
            const that = this;
            let column = extend({}, options.columns[0]);
            options.model.fields.value.validation = options.model.validation ? options.model.validation.toJSON() : {};
            delete column.parentColumn;

            return new DynamicInCellEditor(cell, extend({}, {
                fieldRenderer: that._cellContent.bind(that),
                appendTo: that.wrapper,
                clearContainer: false,
                target: that,
                columns: [column],
                model: options.model,
                change: options.change
            }));
        },

        _createDirtyTemplate: function(model) {
            const that = this;
            let templateSettings = that._customTemplateSettings();
            let dirtyIndicator = function(data) {
                    return '<span class="k-dirty"></span>';
                };
            let templateFunction = function(data) {
                return (dirtyIndicator() + kendo.template(model.template)(model));
            };

            return kendo.template(templateFunction, templateSettings).bind({ columnTemplate: model.template });
        },

        _cellContent: function(column, model) {
            const that = this,
            incellEditing = that._isIncellEditable(),
            isValueColumn = column.field == "value";
            let value, dirtyIndicator, template;

            if (isValueColumn && model.template) {
                template = model.dirty ? that._createDirtyTemplate(model) : kendo.template(model.template);
                value = template(model);
            } else if (column.field) {
                value = model.get(column.field);
                dirtyIndicator = incellEditing ? column.dirtyIndicatorTemplate(model) : "";
                if (!isNullorUndefined(value)) {
                    if (model.format) {
                        value = kendo.format(model.format, value);
                    }

                    value = dirtyIndicator + value;
                } else {
                    value = dirtyIndicator;
                }
            } else if (isNullorUndefined(value)) {
                value = "";
            }

            if (model.template) {
                return kendoHtmlElement(value);
            } else {
                if (incellEditing) {
                    return isEditable(model.value) && isValueColumn ? that._editableCellContent(value) : kendoHtmlElement(value);
                } else {
                    return kendoTextElement(value);
                }
            }
        },

        _editableCellContent: function(value) {
            let bTag = kendoDomElement("b");
            let content = kendoHtmlElement(value);
            bTag.children.push(content);
            return bTag;
        },

        _generateGroupRow: function(item) {
            const that = this;
            let groupRowContent = (kendo.template(that._groupContentTemplate)({
                groupName: item.value,
                action: item.visible ? COLLAPSE : EXPAND,
                icon: item.visible ? COLLAPSE_ICON : EXPAND_ICON
            }));
            let tdElement = kendoDomElement(TD, { className: "k-table-td", class: "k-table-td", colSpan: "3" , role: "gridcell", "aria-expanded": item.visible, visible: true }, [kendo.dom.html(groupRowContent)] );
            let trElement = kendoDomElement(TR, { className: "k-table-group-row k-grouping-row k-table-row", role: "row" , "data-uid": item.uid }, [tdElement] );
            return trElement;
        },

        _generateDetailsBox: function() {
            const that = this;
            let tdElement = kendoDomElement(TD, { className: "k-table-td", class: "k-table-td", colSpan: that.grouped ? 3 : 2 , role: "gridcell", visible: true }, [kendo.dom.html("<span>&nbsp;</span>")] );
            let trElement = kendoDomElement(TR, {
                className: `k-details-box k-bottom k-sticky k-footer-template k-table-row ${!that.visibleDetails ? 'k-hidden' : ''}`,
                role: "row"
            }, [tdElement] );
            return trElement;
        },

        _renderRows: function(options, data, columns, selected, childrenMap, viewChildrenMap, hasFooterTemplate) {
            const that = this;
            let selectedRow = selected.length ? selected : that._selectedRowUid;
            let rows = [];
            if (that.grouped) {
                if (that.table.find(">colgroup>col.k-group-col").length === 0) {
                    that.table.find(">colgroup").prepend('<col class="k-group-col">');
                }
                that._groupedData.forEach(item => {
                    let groupTr = that._generateGroupRow(item);

                    rows.push(groupTr);
                    rows = rows.concat(that._trs({
                        columns: columns,
                        editedColumn: options.editedColumn,
                        editedColumnIndex: options.editedColumnIndex,
                        aggregates: options.aggregates,
                        selected: selectedRow,
                        data: item.items,
                        childrenMap: childrenMap,
                        viewChildrenMap: viewChildrenMap,
                        hasFooterTemplate: hasFooterTemplate,
                        visible: item.visible,
                        level: 0
                    }));
                });
            } else {
                that.table.find(">colgroup>col.k-group-col").remove();
                rows = rows.concat(this._trs({
                    columns: columns,
                    editedColumn: options.editedColumn,
                    editedColumnIndex: options.editedColumnIndex,
                    aggregates: options.aggregates,
                    selected: selectedRow,
                    data: data,
                    childrenMap: childrenMap,
                    viewChildrenMap: viewChildrenMap,
                    hasFooterTemplate: hasFooterTemplate,
                    visible: true,
                    level: 0
                }));
            }

            if (that.options.showDetails) {
                rows.push(that._generateDetailsBox());
            }

            that._contentTree.render(rows);
        },

        _generateRowOptions: function(model, attr, pageable, options, level, hasChildren) {
            const that = this;

            let rowOptions = {
                model: model,
                attr: attr,
                level: pageable ? that._renderedModelLevel(model, options) : level,
                editedColumn: options.editedColumn,
                editedColumnIndex: options.editedColumnIndex,
                hasChildren: hasChildren,
                visible: options.visible,
                isAlt: this._absoluteIndex % 2 === 0,
                grouped: that.options.groupable && that.grouped
            };
            return rowOptions;
        },

        _renderRow: function(rowOptions, columns, renderer) {
            let row = this._tds(rowOptions, columns, renderer);

            if (rowOptions.grouped) {
                let groupTdElement = kendoDomElement(TD, { class: "k-table-td k-group-cell", className: "k-table-td k-group-cell" });
                row.children.splice(0,0,groupTdElement);
            }
            return row;
        },

        editCell: function(cell) {
            const that = this;
            let cellIndex;
            cell = $(cell);
            cellIndex = that.grouped ? that.cellIndex(cell) - 1 : that.cellIndex(cell);
            let column = that.columns[cellIndex];
            let model = that.dataItem(cell);

            if (that._isIncellEditable() && model && isColumnEditable(column, model)) {
                that._editCell(cell, column, model, cellIndex);
            }
        },

        _wrapper: function() {
            const that = this;
            that.element.css("width", this.options.width);
            that.wrapper.addClass("k-property-grid");
        },

        _toolbar: function() {
            const that = this,
            options = that.options;
            let toolbarOptions = options.toolbar;
            let toolbar = this.toolbar;

            if (!toolbarOptions) {
                return;
            }

            if (Array.isArray(toolbarOptions) || isPlainObject(toolbarOptions)) {
                toolbarOptions = isPlainObject(toolbarOptions) ? toolbarOptions : { items: toolbarOptions };
                let items = that._processToolbarItems(toolbarOptions.items);
                items = that._verifyItems(items);

                toolbar.kendoToolBar({
                    resizable: true,
                    navigateOnTab: !options.navigatable,
                    items: items,
                    overflow: toolbarOptions.overflow
                });

                toolbar.find(".k-grid-search .k-input-inner").attr({
                    placeholder: options.messages.commands.search,
                    title: options.messages.commands.search
                });

                that._attachToolbarHandlers();
            } else {
                toolbar.append(kendo.template(toolbarOptions)({}));
            }

        },

        _attachToolbarHandlers: function() {
            const that = this;
            let toolbarWidget = that.toolbar.getKendoToolBar();
            let sortElement = toolbarWidget.element.find('[data-command="sort"] input');

            toolbarWidget.bind("toggle", that._toolbarClick.bind(that));
            toolbarWidget.bind("click", that._toolbarClick.bind(that));

            if (sortElement.length > 0) {
                sortElement.getKendoDropDownList().bind("change", that._sortItems.bind(that));
            }
        },

        _verifyItems: function(items) {
            const that = this;
            if ((!that.options.groupable || !that.options.items.some(x=>x.hasOwnProperty("group")) )) {
                items = items.filter(x=>x.name != "group");
            }
            if (!(that.options.showDetails && that.options.selectable) && items.findIndex(x=>x.name == "details") > 0) {
                items = items.filter(x=>x.name != "details");
            }
            return items;
        },

        _getCommandType: function(command) {
            if (command.type) {
                return command.type;
            }

            if (command.template) {
                return null;
            }

            return "button";
        },

        _processToolbarItems: function(commands) {
            const that = this,
            messages = that.options.messages.commands;
            let items = [];

            commands.map(command => {
                let name = (isPlainObject(command) ? command.name || "" : command).toLowerCase();
                let text = messages[name];

                if (!name && !(isPlainObject(command) && command.template)) {
                    throw new Error("Commands should have name specified");
                }

                command = extend({}, defaultCommands[name], {
                    name: name,
                    text: text || (defaultCommands[name] || {}).text,
                    type: that._getCommandType(defaultCommands[name] || {})
                }, command);

                if (command.imageClass) {
                    command.spriteCssClass = command.imageClass;
                    command.iconClass = command.imageClass;
                }

                if (!command.attributes) {
                    command.attributes = {};
                }

                command.attributes["data-command"] = command.commandName || name;

                if (command.menuButtons || command.buttons) {
                    delete command.name;
                }

                command.click = (e) => {
                    let origEvent = e.event || e.originalEvent;
                    if (origEvent) {
                        origEvent.preventDefault();
                        origEvent.stopPropagation();
                    }

                    that._commandClick({
                        currentTarget: e.target
                    });
                };

                if (command.className) {
                    if (!command.attributes) {
                        command.attributes = {};
                    }

                    command.attributes["class"] = command.className;
                }
                command.attributes["title"] = text || command.attributes["title"];

                items.push(command);
            });

            return items;
        },

        _toolbarClick: function(ev) {
            let command = $(ev.target).data("command"),
                options = $(ev.target).data("options");

            if (!!$(ev.target).val()) {
                options = extend({}, options, { value: $(ev.target).val() });
            }

            if (!command || !ui.propertygrid.commands[command] ) {
                return;
            }

            this._action({
                command: command,
                options: options
            });
        },

        _sortItems: function(e) {
            const that = this,

            dataSource = that.dataSource,
            dir = e.sender.value();
            let sort = dir === "none" ? {} : { field: "field", dir: dir };

            dataSource.sort(sort);
            that._groupedData = dataSource.filterGroupedItemsTree(dataSource.filter(),"group").map(itm => ({
                ...itm,
                visible: that._groupedData.filter(x=>x.value == itm.value)[0].visible,
                uid: that._groupedData.filter(x=>x.value == itm.value)[0].uid
            }));

            if (that.grouped) {
                that.refresh();
            }
        },

        _initContextMenu: function() {
            const that = this,
            options = that.options;

            if (!options.contextMenu) {
                return;
            }

            let tbodyContextMenu = isPlainObject(options.contextMenu) && isArray(options.contextMenu.body) ? { items: options.contextMenu.body } : { items: defaultBodyContextMenu };

            let mainOptions = isPlainObject(options.contextMenu) ? options.contextMenu : {};

            tbodyContextMenu = extend({}, {
                messages: options.messages,
                target: that.tbody,
                filter: ".k-table-row:not(.k-grouping-row,.k-details-box) > .k-table-td",
                action: that._action.bind(that),
                states: that._buildStates()
            }, mainOptions, tbodyContextMenu);

            that.tbodyContextMenu = new ui.propertygrid.ContextMenu("<ul></ul>", tbodyContextMenu);
        },

        _buildStates: function() {
            const that = this;

            return {
                isResizable: that.options.resizable,
                isEditable: that.options.editable,
                windowHasSelection: () => (typeof window.getSelection != 'undefined' && window.getSelection().rangeCount != 0 && window.getSelection().getRangeAt(0).toString() != ''),
                isDirty: (target)=> {
                    let dataItem = this.dataItem(target);
                    return dataItem.dirty;
                },
                isNotInEditMode: (target) => !target.closest("td").hasClass("k-edit-cell"),
                isNotGroupColumn: (target) => !target.closest("td").hasClass("k-group-cell"),
                isExpandable: (target) => {
                    let dataItem = this.dataItem(target);
                    return (dataItem && dataItem.hasChildren && !dataItem.expanded);
                },
                isCollapsible: (target) => {
                    let dataItem = this.dataItem(target);
                    return (dataItem && dataItem.hasChildren && dataItem.expanded);
                }
            };
        },

        _action: function(args) {
            let commandName = args.command,
                commandOptions = extend({ propertyGrid: this }, args.options),
                command = new ui.propertygrid.commands[commandName](commandOptions);

            return command.exec();
        },

        _objectFromNodes: function(rootNodes) {
            const that = this,
            hashTable = Object.create(null),
            dataSource = that.dataSource;
            rootNodes = isArray(rootNodes) ? rootNodes : [rootNodes];

            rootNodes.forEach((node) => {
                if (node.propType == "Array") {
                    hashTable[node.field] = [];
                    dataSource.childNodes(node).forEach((childNode) => {
                        hashTable[node.field].push(childNode.hasChildren ? that._objectFromNodes(dataSource.childNodes(childNode)) : childNode.value);
                    });
                } else {
                    hashTable[node.field] = node.hasChildren ? that._objectFromNodes(dataSource.childNodes(node)) : node.value;
                }
            });

            return hashTable;
        },

        _groupable: function() {
            const that = this;
            that._groupedData = that.dataSource.groupedItemsTree("group").map(itm => ({ ...itm, visible: true, uid: kendo.guid() }));
            that._groupableClickHandler = function(e) {
                let element = $(this),
                groupRow = element.closest(TR);

                let group = that._groupedData.filter(x=>x.value === groupRow.text())[0];
                group.visible = !group.visible;

                if (element.is(CARET_ALT_DOWN)) {
                    if (!that.trigger(GROUP_COLLAPSE, { group: group.items, element: groupRow })) {
                        that.refresh();
                    }
                } else {
                    if (!that.trigger(GROUP_EXPAND, { group: group.items, element: groupRow })) {
                        that.refresh();
                    }
                }
                e.preventDefault();
                e.stopPropagation();
            };

            that.table.on(CLICK + NS, ".k-grouping-row " + CARET_ALT_RIGHT + ", .k-grouping-row " + CARET_ALT_DOWN, that._groupableClickHandler)
                .on("keydown" + NS, that._groupRowKeyDown.bind(that));

        },

        _groupRowKeyDown: function(e) {
            const that = this,
            current = that.current();
            let handled = false;

            if (!that.options.navigatable) {
                return;
            }

            if (e.keyCode == keys.ENTER) {
                kendo.focusElement(that.table);
                that._setCurrent(that._findCurrentCell());
                handled = true;
            }

            if ((e.keyCode == keys.LEFT || e.keyCode == keys.RIGHT) && e.altKey) {
                handled = this._handleGroupRowExpandCollapse(current, e.keyCode);
            }

            if (handled) {
                e.preventDefault();
                e.stopPropagation();
            }
        },

        _handleGroupRowExpandCollapse: function(current, keyCode) {
            const that = this,
                row = current.parent();

            if (row.is(DOT + GROUPING_ROW)) {
                let visible = that._groupedData.filter(x=>x.value === row.text())[0].visible;

                if (visible && keyCode == keys.LEFT || !visible && keyCode == keys.RIGHT) {
                    that._toggleGroup(row);
                    return true;
                }

            }
        },

        _toggleGroup: function(groupRow) {
            const that = this;
            let group = that._groupedData.filter(x=>x.value === groupRow.text())[0];
                group.visible = !group.visible;
                that.refresh();
        },

        _getGroupedData: function(expression) {
            let that = this;
            return that.dataSource.filterGroupedItemsTree(expression,"group").map(itm => ({ ...itm, visible: true, uid: that._groupedData.filter(x=>x.value == itm.value)[0].uid }));
        },

        _search: function(e) {
            let that = this;
            let input = e.currentTarget;
            clearTimeout(that._searchTimeOut);
            that._searchTimeOut = setTimeout(function() {
                that._searchTimeOut = null;
                let options = that.options,
                searchFields = options.search ? options.search.fields : ["field","value"],
                expression = { filters: [], logic: "or" },
                value = input.value;

                if (value) {
                    for (let i = 0; i < searchFields.length; i++) {
                        expression.filters.push({ field: searchFields[i], operator: "contains", value: value });
                    }
                }

                that._groupedData = that._getGroupedData(expression,"group");
                that.dataSource.filter(expression);
                that._refreshLayout();
                that._updateDetails();
            }, 300);
        },

        _refreshLayout: function() {
            const that = this;
            that._selectedRowUid = that.select().removeClass("k-selected").map(function(_, row) {
                return $(row).attr("data-uid");
            });
            that._contentTree.render([]);
            that._render();
        },

        _detailsBox: function() {
            const that = this,
            options = that.options;

            that._detailsTemplate = options.detailsTemplate ? options.detailsTemplate : that._detailsTemplate;

            if (!options.showDetails) {
                return;
            }

            that.detailsContainer = that.wrapper.find("table > tr.k-details-box > td");
            that.bind(CHANGE, that._updateDetails);
        },

        _updateDetails: function() {
            const that = this;
            if (!that.options.selectable) {
                return;
            }
            let dataItem = that.dataItem(that.select());

            dataItem ? that.table.find("tr.k-details-box > td").html(kendo.template(that._detailsTemplate)(dataItem)) : that._clearDetails();
        },

        _clearDetails: function() {
            const that = this;
            let content = '<span>&nbsp;</span>';
            that.table.find("tr.k-details-box > td").html(content);
        },

        model: function(model, items) {
            const that = this;
            if (!model) {
                return that._objectFromNodes(that.dataSource.rootNodes());
            }

            that.options.model = model;
            if (items) {
                that.options.items = items;
            }

            that._processedModel = that._processModel();
            that.setDataSource(new PropertyGridDataSource({
                data: that._processedModel,
                serverGrouping: false,
                schema: {
                    model: {
                        id: "id",
                        parentId: "parentId",
                        expanded: true,
                        fields: {
                            field: { editable: false },
                            value: { editable: true }
                        }
                    },
                }
            }));

        },

        selectItem: function(value) {
            const that = this;
            return that.select(value);
        },

        toggleItem: function(row) {
            const that = this;
            const dataItem = that.dataItem(row);

            dataItem.expanded ? that.collapse(row) : that.expand(row);
        },

        toggleGroup: function(row) {
            const that = this;
            if (!$(row).is(DOT + GROUPING_ROW)) {
                return;
            }

           that._toggleGroup(row);
        },

        toggleDetails: function() {
            const that = this;
            if (!that.options.showDetails) {
                return;
            }

            that._action({ command: "ToggleDetails" });
        },

        edit: function(cell) {
            const that = this;
            that.editCell(cell);
        },

        saveState: function() {
            const that = this;
            that.saveChanges();
        },

        resetState: function() {
            const that = this;
            that.cancelChanges();
        },

        setOptions: function(options) {
            const that = this;
            let currentOptions = that.getOptions(),
            wrapper = that.wrapper,
            events = that._events,
            element = that.element;

            delete currentOptions.model;
            kendo.deepExtend(currentOptions, options);
            that.model = that.options.model = options.model || { };

            that.destroy();

            if (wrapper[0] !== element[0]) {
                wrapper.before(element);
                wrapper.remove();
            }
            element.empty();

            that.init(element, currentOptions, events);
            that._setEvents(currentOptions);
        },

        destroy: function() {
            const that = this;
            if (this.tbodyContextMenu) {
                this.tbodyContextMenu.destroy();
                this.tbodyContextMenu = null;
            }

            TreeList.fn.destroy.call(that);
        },

    });


    function hierarchyRows() {
        let this$1 = this;
        let depth = this._depth();
        let data = this.data;
        let hasFooter = this._hasFooterTemplate();
        let rows = [];
        let parents = [];

        if (!hasFooter) {
            this.collapsible = false;
        }

        if (this$1.options.widget.grouped) {
            data = this$1.options.widget.dataSource.groupedItemsTree("group");
            let colSpan = depth + this$1.options.widget.columns.length;
            this$1.options._buildGroupedDataRows(data, rows, colSpan);
        } else {
            this$1.options._buildDataRows(data, rows, parents);
        }
        this._prependHeaderRows(rows);

        return rows;
    }

    let PropertyGridExcelExporter = kendo.Class.extend({
        init: function(options) {
            options._buildDataRows = this._buildDataRows.bind(this);
            options._buildGroupedDataRows = this._buildGroupedDataRows.bind(this);
            this._instance = new ExcelExporter(options);
            this._instance._hierarchyRows = hierarchyRows.bind(this);

            options.columns = this._trimColumns(options.columns || []);

            this.allColumns = $.map(this._leafColumns(options.columns || []), (column) => this._prepareColumn(column));

            this.columns = this._visibleColumns(this.allColumns);
            this.widget = options.widget;
            this.options = options;
            this.data = options.data || [];
            this.aggregates = options.aggregates || {};
            this.groups = [].concat(options.groups || []);
            this.hasGroups = this.groups.length > 0;
            this.hierarchy = options.hierarchy;
            this.hasGroupHeaderColumn = this.columns.some(function(column) { return column.groupHeaderColumnTemplate; });
            this.collapsible = this.options.collapsible;
        },

        _recursiveRows: function(rows, item) {
            let this$1 = this;
            let level = this$1.widget.dataSource.level(item);
            rows.push(...this$1._dataRow(item, level + 1, this._depth()));

            if (item.hasChildren) {
                let children = this$1.widget.dataSource.childNodes(item);
                for (let itmIdx = 0; itmIdx < children.length; itmIdx++) {
                    let item = children[itmIdx];

                    this._recursiveRows(rows,item);
                }
            }
        },

        _buildGroupedDataRows: function(data, rows, colSpan) {
            for (let idx = 0; idx < data.length; idx++) {
                let groupItem = data[idx];
                rows.push({
                    type: "group-header",
                    cells: [ {
                        value: groupItem.value,
                        colSpan: colSpan,
                        background: "#dfdfdf",
                        color: "#333",
                    } ],
                    level: null
                });
                for (let itmIdx = 0; itmIdx < groupItem.items.length; itmIdx++) {
                    let item = groupItem.items[itmIdx];

                    this._recursiveRows(rows,item);
                }
            }
        },

        _buildDataRows: function(data, rows, parents) {
            let this$1 = this;
            let depth = this._depth();
            let itemLevel = this.hierarchy.itemLevel;
            let itemId = this.hierarchy.itemId;
            let hasFooter = this._hasFooterTemplate();
            let previousLevel = 0;
            let previousItemId;

            for (let idx = 0; idx < data.length; idx++) {
                let item = data[idx];
                let level = itemLevel(item, idx);

                if (hasFooter) {
                    if (level > previousLevel) {
                        parents.push({ id: previousItemId, level: previousLevel });
                    } else if (level < previousLevel) {
                        rows.push(...this$1._hierarchyFooterRows(parents, level, depth));
                    }

                    previousLevel = level;
                    previousItemId = itemId(item, idx);
                }

                rows.push(...this$1._dataRow(item, level + 1, depth));
            }

            if (hasFooter) {
                rows.push(...this._hierarchyFooterRows(parents, 0, depth));

                let rootAggregate = data.length ? this.aggregates[data[0].parentId] : {};
                rows.push(this._hierarchyFooter(rootAggregate, 0, depth));
            }
        },
    });

    kendo.getAllMethods(ExcelExporter).forEach((func) => kendo.createProxyMember(PropertyGridExcelExporter, func));

    kendo.PropertyGridExcelExporter = kendo.ExcelExporter.extend({
        workbook: function() {
            return $.Deferred((function(d) {
                this.dataSource.fetch()
                    .then((function() {
                        let exporter = new PropertyGridExcelExporter(extend({}, this.options, this._hierarchy(), {
                            data: this.dataSource.view(),
                            groups: this.dataSource.group(),
                            aggregates: this.dataSource.aggregates()
                        }));

                        let workbook = exporter.workbook();
                        d.resolve(workbook, this.dataSource.view());
                    }).bind(this));
            }).bind(this)).promise();
        }
    });

    let PropertyGridExcelMixin = {
        extend: function(proto) {
           proto.events.push("excelExport");
           proto.options.excel = $.extend(proto.options.excel, this.options);
           proto.saveAsExcel = this.saveAsExcel;
        },
        options: {
            proxyURL: "",
            filterable: false,
            fileName: "Export.xlsx"
        },
        saveAsExcel: function() {
            let excel = this.options.excel || {};

            let exporter = new kendo.PropertyGridExcelExporter({
                widget: this,
                columns: this.columns,
                dataSource: this.dataSource,
                data: this.dataSource.data(),
                allPages: excel.allPages,
                filterable: excel.filterable,
                hierarchy: excel.hierarchy,
                collapsible: excel.collapsible
            });

            exporter.workbook().then((function(book, data) {
                if (!this.trigger("excelExport", { workbook: book, data: data })) {
                    let workbook = new kendo.ooxml.Workbook(book);

                    if (!workbook.options) {
                        workbook.options = {};
                    }
                    workbook.options.skipCustomHeight = true;

                    workbook.toDataURLAsync().then(function(dataURI) {
                        kendo.saveAs({
                            dataURI: dataURI,
                            fileName: book.fileName || excel.fileName,
                            proxyURL: excel.proxyURL,
                            forceProxy: excel.forceProxy
                        });

                        exporter._restoreExpandedState();
                    });

                }
            }).bind(this));
        },
    };

    kendo.PropertyGridExcelMixin = PropertyGridExcelMixin;

    if (kendo.ooxml && kendo.ooxml.Workbook) {
        PropertyGridExcelMixin.extend(PropertyGrid.prototype);
    }

    kendo.ui.propertygrid = kendo.ui.propertygrid || {};

    extend(kendo.ui.propertygrid, {
        defaultBodyContextMenu: defaultBodyContextMenu,
    });

    kendo.cssProperties.propertyDictionary["PropertyGrid"] = kendo.cssProperties.propertyDictionary["TreeList"];
    kendo.cssProperties.registerPrefix("PropertyGrid", "k-property-grid-");

    kendo.ui.plugin(PropertyGrid);
})(window.kendo.jQuery);
export default kendo;

