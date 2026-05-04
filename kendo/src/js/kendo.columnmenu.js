/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.actionsheet.js";
import "./kendo.popup.js";
import "./kendo.tabstrip.js";
import "./kendo.filtermenu.js";
import "./kendo.menu.js";
import "./kendo.expansionpanel.js";
import "./kendo.html.button.js";
import "./kendo.icons.js";

export const __meta__ = {
    id: "columnmenu",
    name: "Column Menu",
    category: "framework",
    depends: [ "popup", "tabstrip", "filtermenu", "menu", 'expansionpanel', 'html.button', "icons", "actionsheet" ],
    advanced: true
};

(function($, undefined) {
    var kendo = window.kendo,
        encode = kendo.htmlEncode,
        ui = kendo.ui,
        extend = $.extend,
        grep = $.grep,
        encode = kendo.htmlEncode,
        map = $.map,
        inArray = $.inArray,
        Comparer = kendo.data.Comparer,
        ACTIVE = "k-selected",
        ASC = "asc",
        DESC = "desc",
        CHANGE = "change",
        INIT = "init",
        OPEN = "open",
        SELECT = "select",
        STICK = "stick",
        UNSTICK = "unstick",
        POPUP = "kendoPopup",
        FILTERMENU = "kendoFilterMenu",
        TABSTRIP = "kendoTabStrip",
        MENU = "kendoMenu",
        ACTIONSHEET = "kendoActionSheet",
        EXPANSIONPANEL = "kendoExpansionPanel",
        NS = ".kendoColumnMenu",
        COLUMN_HEADER_SELECTOR = ".k-table-th",
        isRtl = false,
        Widget = ui.Widget;

    function trim(text) {
        return kendo.trim(text).replace(/&nbsp;/gi, "");
    }

    function toHash(arr, key) {
        var result = {};
        var idx, len, current;
        for (idx = 0, len = arr.length; idx < len; idx ++) {
            current = arr[idx];
            result[current[key]] = current;
        }
        return result;
    }

    function columnsGroupFilterHandler(column) {
        return this.columns.indexOf(column.title) >= 0 || this.columns.indexOf(column.field) >= 0;
    }

    function leafColumns(columns) {
        var result = [];

        for (var idx = 0; idx < columns.length; idx++) {
            if (!columns[idx].columns) {
                result.push(columns[idx]);
                continue;
            }
            result = result.concat(leafColumns(columns[idx].columns));
        }

        return result;
    }

    function attrEquals(attrName, attrValue) {
        return "[" + kendo.attr(attrName) + "='" + (attrValue || "").replace(/'/g, "\"") + "']";
    }

    function insertElementAt(index, element, container) {
        if (index > 0) {
            element.insertAfter(container.children().eq(index - 1));
        } else {
            container.prepend(element);
        }
    }

    function columnOccurrences(columns) {
        var columnDict = {};
        var signature;

        for (var i = 0; i < columns.length; i++) {
            signature = JSON.stringify(columns[i]);

            if (columnDict[signature]) {
                columnDict[signature].push(i);
            } else {
                columnDict[signature] = [i];
            }
        }

        return columnDict;
    }

    function oldColumnOccurrences(renderedListElements, checkBoxes) {
        var indexAttr = kendo.attr("index");
        var fieldAttr = kendo.attr("field");
        var columnDict = {};
        var signature;
        var columCheckbox;
        var index;
        var field;
        var title;

        for (var j = 0; j < renderedListElements.length; j++) {
            columCheckbox = checkBoxes.eq(j);
            index = parseInt(columCheckbox.attr(indexAttr), 10);
            field = columCheckbox.attr(fieldAttr);
            title = columCheckbox.attr("title");
            signature = field ? field : title;

            if (columnDict[signature]) {
                columnDict[signature].push(index);
            } else {
                columnDict[signature] = [index];
            }
        }

        return columnDict;
    }

    var ColumnMenu = Widget.extend({
        init: function(element, options) {
            var that = this,
                columnHeader;

            options = options || {};
            options.componentType = options.componentType || "classic";
            Widget.fn.init.call(that, element, options);

            element = that.element;
            options = that.options;
            that.owner = options.owner;
            that.dataSource = options.dataSource;

            isRtl = kendo.support.isRtl(that.element.parents('.k-grid'));

            that.field = element.attr(kendo.attr("field"));
            that.title = element.attr(kendo.attr("title"));

            columnHeader = $(element.closest(COLUMN_HEADER_SELECTOR));
            if (columnHeader.length) {
                that.appendTo = columnHeader.find(options.appendTo);
            } else {
                that.appendTo = $(options.appendTo);
            }

            that.link = that._createLink();

            that.wrapper = $('<div />');

            that._applyCssClasses();

            that._refreshHandler = that.refresh.bind(that);
            that._bindMediaQueries();

            that.dataSource.bind(CHANGE, that._refreshHandler);
        },

        _init: function() {
            var that = this;

            that.pane = that.options.pane;
            if (that.pane) {
                that._isMobile = true;
            }


            if (that._isMobile) {
                that._createMobileMenu();
            } else {
                that._createMenu();
            }
            that._columns();

            if (!that._showAdaptiveView) {
                that._filter();

                that._lockColumns();

                that._reorderColumns();

                that._stickyColumns();

                that._clearAllFilters();
            }

            that._sort();

            that._sizeColumns();

            that._groupColumn();

            that.trigger(INIT, { field: that.field, container: that.wrapper });
        },

        events: [ INIT, OPEN, "sort", "filtering", STICK, UNSTICK ],

        options: {
            name: "ColumnMenu",
            messages: {
                sortAscending: "Sort Ascending",
                sortDescending: "Sort Descending",
                filter: "Filter",
                clearAllFilters: "Clear All Filters",
                column: "Column",
                columns: "Columns",
                columnVisibility: "Columns",
                clear: "Clear",
                cancel: "Cancel",
                done: "Done",
                settings: "Edit Column Settings",
                lock: "Lock Column",
                unlock: "Unlock Column",
                stick: "Stick Column",
                unstick: "Unstick Column",
                setColumnPosition: "Set Column Position",
                apply: "Apply",
                reset: "Reset",
                buttonTitle: "{0} edit column settings",
                movePrev: "Move previous",
                moveNext: "Move next",
                groupColumn: "Group column",
                ungroupColumn: "Ungroup column",
                autoSizeColumn: "Autosize This Column",
                autoSizeAllColumns: "Autosize All Columns"
            },
            filter: "",
            columns: true,
            sortable: true,
            filterable: true,
            clearAllFilters: false,
            autoSize: false,
            hideAutoSizeColumn: false,
            adaptiveTitle: "",
            adaptiveSubtitle: "",
            animations: {
                left: "slide"
            },
            adaptiveMode: "none",
            encodeTitles: false,
            componentType: "classic",
            appendTo: null,
            _actionsheet: null,
        },

        _bindMediaQueries: function() {
            const that = this;
            const isAdaptive = that.options.adaptiveMode === "auto" && that.options.componentType === "modern";

            if (isAdaptive) {
                that.largeMQL = kendo.mediaQuery("large");
                that.mediumMQL = kendo.mediaQuery("medium");
                that.smallMQL = kendo.mediaQuery("small");

                that.smallMQL
                    .onEnter(() => {
                        that._showAdaptiveView = true;

                        that.wrapper?.addClass("k-column-menu-lg");

                        if (that.popup && that.popup.visible() && that.popup.fullscreen) {
                            that.popup.fullscreen(true);
                        } else {
                            that._adaptiveView();
                        }
                    });

                that.mediumMQL
                    .onEnter(() => {
                        that._showAdaptiveView = true;

                        that.wrapper?.addClass("k-column-menu-lg");

                        if (that.popup && that.popup.visible() && that.popup.fullscreen) {
                            that.popup.fullscreen(false);
                        } else {
                            that._adaptiveView();
                        }
                    });

                that.largeMQL
                    .onEnter(() => {
                        that._showAdaptiveView = false;
                        that.wrapper?.removeClass("k-column-menu-lg");

                        that._adaptiveView();
                    });
            } else {
                that.smallMQL && that.smallMQL.destroy();
                that.mediumMQL && that.mediumMQL.destroy();
                that.largeMQL && that.largeMQL.destroy();

                that._showAdaptiveView = false;
            }
        },

        _adaptiveView: function() {
            const that = this;

            if (that.popup) {
                that.popup.close();
                that.popup.wrapper && that.popup.wrapper.remove();
                that.popup.destroy();
                that.popup = null;
            }
        },


        _adaptiveItemClick: function(e) {
            e.preventDefault();
            e.stopPropagation();
            const that = this;
            const options = that.options;
            let adaptiveView = that._columnMenuAdaptiveView;
            let columns = that._ownerColumns();
            let flattenMenuCols;
            let viewInitialized = false;

            if (that._hasGroups()) {
                columns = that._groupColumns(columns);
                flattenMenuCols = that._flattenMenuCols(columns);
                if (flattenMenuCols.length !== that.owner.columns.length) {
                    that._syncMenuCols(flattenMenuCols, that.owner.columns);
                }
            }

            const templateOptions = {
                uid: kendo.guid(),
                ns: kendo.ns,
                messages: options.messages,
                sortable: options.sortable,
                filterable: options.filterable,
                columns: columns,
                showColumns: options.columns,
                hasLockableColumns: options.hasLockableColumns,
                hasStickableColumns: options.hasStickableColumns,
                encodeTitles: options.encodeTitles,
                omitWrapAttribute: kendo.attr("omit-wrap"),
                reorderable: options.reorderable,
                groupable: options.groupable,
                autoSize: options.autoSize,
                hideAutoSizeColumn: options.hideAutoSizeColumn,
                clearAllFilters: options.clearAllFilters,
                isAdaptive: true
            };

            const field = that.field || $(e.currentTarget).text().split(" / ").pop();

            const action = $(e.currentTarget).closest(".k-expander").find("[ref]").attr("ref");
            const template = that._getContentTemplates()[action];

            const viewOptions = {
                ...that._getHeaderConfig(options.adaptiveTitle || field, options.messages)[action],
                ...that._getFooterConfig(options.messages)[action],
                contentTemplate: template && template(templateOptions),
                ref: `${action}-view`,
            };

            function initializeView() {
                adaptiveView = that._columnMenuAdaptiveView = that.popup._addView(viewOptions);
                that._columnMenuAdaptiveView.field = that.field;
                viewInitialized = true;
            }

            if (!adaptiveView && that._showAdaptiveView) {
                initializeView();
            } else if (adaptiveView && (adaptiveView.ref !== `${action}-view`) || (adaptiveView.field !== field)) {
                that.popup._removeView(adaptiveView);
                initializeView();
            }

            if (!viewInitialized) {
                setTimeout(() => {
                    that.popup._setCurrentActiveView(adaptiveView.index);
                });
                return;
            }

            if (action.includes("column")) {
                if (that.options.hasLockableColumns) {
                    that._updateLockedColumns();
                }

                if (that.options.hasStickableColumns) {
                    that._updateStickyColumns();
                }

                if (that.options.reorderable) {
                    that._updateReorderColumns();
                }

                if (that.options.groupable) {
                    that._updateGroupColumns();
                }

                that._updateColumnsMenu();
            } else if (action === "filter" && !template) {
                const column = leafColumns(that.owner.columns)?.find((col) => (col.title === field) || (col.field === field));
                const filterOptions = { ...options };

                if (column.filterable) {
                    filterOptions.filterable = column.filterable;
                }
                filterOptions.field = column.field;
                that._filter(".k-actionsheet-content", filterOptions);
            }

            if (action === 'column-position') {
                that._lockColumns();
                that._reorderColumns();
                that._stickyColumns();
                that._sizeColumns();
            }

            that._bindHandlers(action);

            adaptiveView?._content.attr("data-field", field);
            const backButton = that.popup && that.popup.wrapper?.find("[data-ref-actionsheet-start-button]");

            backButton.bind("click" + NS, function(e) {
                e.preventDefault();
                that.popup._setCurrentActiveView(adaptiveView.index - 1);
            });

            setTimeout(() => {
                that.popup._setCurrentActiveView(adaptiveView.index);
            });
        },

        _getHeaderConfig: function(field, messages) {
            return {
                "filter": {
                    title: `${messages.filter} by ${field}`,
                    closeButton: true,
                    startButton: {
                        text: "Back"
                    },
                },
                "columns-visibility": {
                    title: messages.columnVisibility,
                    subtitle: "Selected fields are visible",
                    closeButton: true,
                    startButton: {
                        text: "Back"
                    },
                },
                "column-position": {
                    title: `Set ${field} Position`,
                    closeButton: true,
                    startButton: {
                        text: "Back"
                    },
                }
            };
        },

        _getFooterConfig: function(messages) {
            return {
                "filter": {
                    actionButtons: [
                        {
                            text: encode(messages.clear),
                            icon: "filter-clear",
                        },
                        {
                            text: encode(messages.apply),
                            icon: "filter",
                            themeColor: "primary",
                        }
                    ],
                },
                "columns-visibility": {
                    actionButtons: [
                        {
                            text: encode(messages.reset),
                            icon: "arrow-rotate-ccw",
                        },
                        {
                            text: encode(messages.apply),
                            themeColor: "primary",
                            icon: "check",
                        },
                    ],
                },
            };
        },

        _getContentTemplates: function() {
            const that = this;
            const options = that.options;
            const componentType = options.componentType;

            const templates = {
                "modern": {
                    "full": modernTemplate,
                    "sort": SORTABLE_PARTIAL_MODERN,
                    "columns-visibility": that._showAdaptiveView ? COLUMNS_PARTIAL_MODERN_ADAPTIVE : COLUMNS_PARTIAL_MODERN,
                    "column-chooser": COLUMN_CHOOSER,
                    "column-position": LOCK_STICK_COLUMNS_PARTIAL_MODERN,
                    "group": GROUPABLE_PARTIAL_MODERN,
                    "lockableColumns": LOCKABLE_COLUMNS_PARTIAL_MODERN,
                    "stickableColumns": STICKABLE_COLUMNS_PARTIAL_MODERN,
                    "reorderableColumns": REORDERABLE_COLUMNS_PARTIAL_MODERN,
                },
            };

            return templates[componentType];
        },

        _bindHandlers: function(ref) {
            const that = this;
            const wrapper = that._getWrapper();

            const selectors = {
                clearButton: that._showAdaptiveView ? ".k-actions .k-button:not(.k-button-primary)" : ".k-columns-item .k-button:not(.k-button-primary)",
                applyButton: that._showAdaptiveView ? ".k-actions .k-button.k-button-primary" : ".k-columns-item .k-button.k-button-primary",
                checkbox: ".k-column-list-wrapper .k-checkbox",
                columnChooserClear: ".k-column-list-wrapper .k-button:not(.k-button-primary)",
                columnChooserApply: ".k-column-list-wrapper .k-button.k-button-primary",
            };

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                wrapper.on("click" + NS, selectors.columnChooserClear, function(e) {
                    that._updateColumnsMenu();
                });

                wrapper.on("click" + NS, selectors.columnChooserApply, function() {
                    that._applyColumnVisibility();
                });

                wrapper.on("click" + NS, selectors.clearButton, function(e) {
                    if (ref) {
                        switch (ref) {
                            case "filter":
                                that.filterMenu.form.trigger("reset");
                                break;
                            case "columns-visibility":
                                e.preventDefault();
                                that._updateColumnsMenu();
                                break;
                        }
                    } else {
                        if (that._showAdaptiveView) {
                            e.preventDefault();
                        }
                        that._updateColumnsMenu();
                    }
                });
                wrapper.on("click" + NS, selectors.applyButton, () => {
                    if (ref) {
                        switch (ref) {
                            case "filter":
                                that.filterMenu.form.trigger("submit");
                                break;
                            case "columns-visibility":
                                that._applyColumnVisibility();
                                break;
                        }
                    } else {
                        that._applyColumnVisibility();
                    }
                });
                wrapper.on("click" + NS, selectors.checkbox, function() {
                    that._updateColumnsMenu(true);
                });
            } else {
                that.menu.bind(SELECT, function(e) {
                    var item = $(e.item),
                        input,
                        column,
                        uidAttr = kendo.attr("uid"),
                        colIdx = 0,
                        columns = grep(leafColumns(that.owner.columns), function(col) {
                            var result = true,
                                title = trim(col.title || "");

                            if (col.menu === false || (!col.field && !title.length)) {
                                result = false;
                            }

                            return result;
                        });

                    if (that._isMobile) {
                        e.preventDefault();
                    }

                    if (!item.parent().closest("li.k-columns-item")[0]) {
                        return;
                    }

                    input = item.find(":checkbox");
                    if (input.attr("disabled")) {
                        return;
                    }

                    colIdx = columns.map(function(col) {
                        return col.headerAttributes.id;
                    }).indexOf(input.attr(uidAttr));
                    column = columns[colIdx];

                    if (column.hidden === true) {
                        that.owner.showColumn(column);
                    } else {
                        that.owner.hideColumn(column);
                    }

                    that._preventMenuCloseOnColumnVisibilityChange = true;
                });
            }
        },

        _applyCssClasses: function() {
            var that = this,
                componentType = that.options.componentType,
                wrapper = that.wrapper;

            if (componentType === "tabbed") {
                wrapper.addClass("k-column-menu-tabbed");
            }

            wrapper.addClass("k-column-menu k-column-menu-popup");
        },

        _createMenu: function() {
            var that = this,
                options = that.options,
                columns = that._ownerColumns(),
                flattenMenuCols,
                menuTemplate,
                menuElement;

            const isGridColumnMenu = that.owner && that.owner.options && (that.owner.options.name === "Grid" || that.owner.options.name === "TreeList");
            const isAdaptive = that._showAdaptiveView;

            if (that._hasGroups()) {
                columns = that._groupColumns(columns);
                flattenMenuCols = that._flattenMenuCols(columns);
                if (flattenMenuCols.length !== that.owner.columns.length) {
                    that._syncMenuCols(flattenMenuCols, that.owner.columns);
                }
            }

            if (options.contentTemplate) {
                menuTemplate = kendo.template(options.contentTemplate);
            } else if (that._isModernComponentType()) {
                menuTemplate = kendo.template(modernTemplate);
            } else if (that._isTabbedComponentType()) {
                menuTemplate = kendo.template(tabbedTemplate);
            } else {
                menuTemplate = kendo.template(template);
            }

            const templateOptions = {
                uid: kendo.guid(),
                ns: kendo.ns,
                messages: options.messages,
                sortable: options.sortable,
                filterable: options.filterable,
                columns: columns,
                showColumns: options.columns,
                hasLockableColumns: options.hasLockableColumns,
                hasStickableColumns: options.hasStickableColumns,
                encodeTitles: options.encodeTitles,
                omitWrapAttribute: kendo.attr("omit-wrap"),
                reorderable: options.reorderable,
                groupable: options.groupable,
                autoSize: options.autoSize,
                hideAutoSizeColumn: options.hideAutoSizeColumn,
                clearAllFilters: options.clearAllFilters,
                isAdaptive: isAdaptive,
            };

            if (options.contentTemplate) {
                templateOptions._defaultContents = that._getContentTemplates.bind(that);
                templateOptions._defaultHeaders = that._getHeaderConfig.bind(that);
                templateOptions._defaultFooters = that._getFooterConfig.bind(that);
            }

            menuElement = $(menuTemplate(templateOptions));

            kendo.applyStylesFromKendoAttributes(menuElement, ["display"]);
            that.wrapper.empty().append(menuElement);

            if (isAdaptive) {
                let views = [
                    {
                        title: options.adaptiveTitle || "Column Menu",
                        subtitle: options.adaptiveSubtitle || "",
                        closeButton: true,
                    }
                ];
                const actionsheetContainer = $("<div></div>").append(that.wrapper).appendTo("body");
                const actionsheetOptions = that.options._actionsheet;

                if (actionsheetOptions) {
                    views = [];
                }

                that.popup = actionsheetContainer[ACTIONSHEET]({
                    anchor: that.link,
                    adaptive: true,
                    copyAnchorStyles: false,
                    closeButton: true,
                    open: that._open.bind(that),
                    activate: that._activate.bind(that),
                    deactivate: that._deactivate.bind(that),
                    close: function(e) {
                        if (that._preventMenuCloseOnColumnVisibilityChange) {
                            e.preventDefault();
                            that._preventMenuCloseOnColumnVisibilityChange = false;
                            return;
                        }
                        if (that._columnMenuAdaptiveView && that._columnMenuAdaptiveView.wrapper) {
                            that._closeApply();
                            that.popup._removeView(that._columnMenuAdaptiveView);
                            that._columnMenuAdaptiveView = null;
                        }

                        if (that.menu) {
                            that.menu._closing = e.sender.element;
                        }
                        if (that.options.closeCallback) {
                            that.options.closeCallback(that.element);
                        }
                    },
                    views,
                    ...actionsheetOptions
                }).data(ACTIONSHEET);

                that.popup.fullscreen(that.smallMQL.mediaQueryList.matches);
            } else {
                that.popup = that.wrapper[POPUP]({
                    anchor: that.link,
                    copyAnchorStyles: false,
                    open: that._open.bind(that),
                    activate: that._activate.bind(that),
                    deactivate: that._deactivate.bind(that),
                    close: function(e) {
                        if (that.menu) {
                            that.menu._closing = e.sender.element;
                        }
                        if (that.options.closeCallback) {
                            that.options.closeCallback(that.element);
                        }
                    }
                }).data(POPUP);
            }


            if (that._isModernComponentType() || that._isTabbedComponentType() || isGridColumnMenu) {
                if (!isAdaptive) {
                    that.popup.element.addClass("k-grid-columnmenu-popup");
                } else {
                    that.popup.element.find(".k-column-menu").removeClass("k-grid-columnmenu-popup k-popup");
                }
                that.popup.element.removeClass("k-column-menu-popup");
            }

            if (that._isModernComponentType() || that._isTabbedComponentType() || options.contentTemplate) {
                that._createExpanders();
            } else {
                that.menu = that.wrapper.children()[MENU]({
                    orientation: "vertical",
                    closeOnClick: false,
                    autoSize: true,
                    open: function() {
                        that._updateMenuItems();
                    }
                }).data(MENU);
            }

            if (!options.contentTemplate) {
                if (that._isTabbedComponentType()) {
                    that.tabStrip = menuElement[TABSTRIP]({
                        applyMinHeight: false,
                        animation: {
                            open: {
                                effects: "fadeIn"
                            }
                        }
                    }).data(TABSTRIP);

                    that.tabStrip.activateTab(that.tabStrip.tabGroup.find("li:first"));
                }
            }

            if (isAdaptive) {
                let selector;
                if (that._isModernComponentType() || options.contentTemplate) {
                    selector = ".k-columnmenu-item-wrapper [data-expander-header]";
                }
                that.popup.element.find(selector).bind("click" + NS, that._adaptiveItemClick.bind(that));
            }
        },

        _closeApply: function(ref) {
            const that = this;
            const adaptiveView = that._columnMenuAdaptiveView;
            const refRegex = /(.*?)-view/;
            if (adaptiveView) {
                const refType = ref || adaptiveView.ref;
                const match = refType.match(refRegex)[1];

                if (match === "filter") {
                    if (adaptiveView._hasChanges) {
                        that.filterMenu.form.trigger("submit");
                    }
                } else if (match === "columns-visibility" || match === "column-chooser") {
                    that._applyColumnVisibility(true);
                }
            }
        },

        _createLink: function() {
            var that = this,
                element = that.element,
                appendTarget = that.appendTo.length ? element.find(that.appendTo) : element,
                link = element.is(".k-grid-column-menu") || element.is("[ref-toolbar-tool]") ? element : element.find(".k-grid-column-menu"),
                title = encode(kendo.format(that.options.messages.buttonTitle, that.title || that.field));

            if (!link[0]) {
                element.addClass("k-filterable");

                link = appendTarget
                    .append('<a class="k-grid-column-menu k-grid-header-menu" href="#" aria-hidden="true" title="' +
                                title + '">' + kendo.ui.icon("more-vertical") + '</a>')
                    .find(".k-grid-column-menu");
            }

            link.attr("tabindex", -1)
                .on("click" + NS, that._click.bind(that));

            return link;
        },

        _createMultiHeaderTitle: function(col) {
            const that = this;
            const parentTitles = col.parentIds
                .split(" ")
                .map((id) => that.owner.thead.find(`[id='${id}']`)?.text());

            parentTitles.push(col.title || col.field);
            return parentTitles.join(" / ");
        },

        _createExpanders: function() {
            var that = this;
            var options = that.options;
            var columnsExpanderOptions = that.options.columnsExpanderOptions || {};
            var expanderOptions = {
                expanded: false,
                headerClass: "k-columnmenu-item",
                useBareTemplate: true,
                expandIconClass: that._showAdaptiveView ? "chevron-right" : undefined,
                collapseIconClass: that._showAdaptiveView ? "chevron-right" : undefined,
            };

            const cols = leafColumns(that.owner.columns);

            if (options.contentTemplate) {
                const items = that.wrapper.find(".k-columns-item:not([ref='column-chooser'])");
                if (items.length) {
                    items.each(function(_, item) {
                        const index = $(item).data("index");
                        const col = cols[index];
                        const colTitle = col && col.parentIds ? that._createMultiHeaderTitle(col) : (col.title || col.field);

                        $(item)[EXPANSIONPANEL]($.extend(true, {}, expanderOptions, columnsExpanderOptions, {
                            title: colTitle
                        }));
                    });
                }
            }

            if (that._isModernComponentType()) {
                that.wrapper.find("[ref='columns-visibility']")[EXPANSIONPANEL]($.extend(true, {}, expanderOptions, columnsExpanderOptions, {
                    title: kendo.ui.icon("columns") + '<span>' + encode(options.messages.columnVisibility) + '</span>'
                }));
                that.wrapper.find(".k-column-menu-filter")[EXPANSIONPANEL]($.extend(true, {}, expanderOptions,{
                    title: kendo.ui.icon("filter") + '<span>' + encode(options.messages.filter) + '</span>'
                })).attr("ref", "filter");
            }

            that.wrapper.find(".k-column-menu-position")[EXPANSIONPANEL]($.extend(true, {}, expanderOptions,{
                title: kendo.ui.icon("set-column-position") + '<span>' + encode(options.messages.setColumnPosition) + '</span>'
            })).attr("ref", "column-position");
        },

        _syncMenuCols: function(menuCols, ownerCols) {
            var length = ownerCols.length;
            var ownerCol;
            var menuColsFields = menuCols.map(function(col) {
                return col.field;
            });

            for (var i = 0; i < length; i++) {
                ownerCol = ownerCols[i];
                if (menuColsFields.indexOf(ownerCol.field) < 0) {
                    ownerCol.menu = false;
                }
            }
        },

        _flattenMenuCols: function(cols) {
            var result = [];
            var length = cols.length;

            for (var i = 0; i < length; i++) {
                if (cols[i].columns) {
                    result = result.concat(this._flattenMenuCols(cols[i].columns));
                } else if (!cols[i].groupHeader) {
                    result.push(cols[i]);
                }
            }
            return result;
        },

        _groupColumns: function(columns, nest) {
            var result = [];
            var groups = this.options.columns.groups;
            var length = groups.length;
            var i;
            var currGroup;
            var filterHandler;
            var group;
            var groupColumns;

            for (i = 0; i < length; i++) {
                currGroup = groups[i];
                filterHandler = columnsGroupFilterHandler.bind(currGroup);
                group = { title: currGroup.title, groupHeader: true };
                groupColumns = columns.filter(filterHandler);
                result.push(group);

                if (nest) {
                    group.columns = groupColumns;
                } else {
                    result = result.concat(groupColumns);
                }
            }

            return result;
        },

        _hasGroups: function() {
            return this.options.columns && this.options.columns.groups && this.options.columns.groups.length;
        },

        _isModernComponentType: function() {
            return this.options.componentType === 'modern' && !this._isMobile;
        },

        _isTabbedComponentType: function() {
            return this.options.componentType === 'tabbed' && !this._isMobile;
        },

        _deactivate: function() {
            if (this.menu) {
                this.menu._closing = false;
            }
        },

        _createMobileMenu: function() {
            var that = this,
                options = that.options,
                columns = that._ownerColumns(),
                groups,
                flattenMenuCols;

            if (that._hasGroups()) {
                groups = that._groupColumns(columns, true);
                flattenMenuCols = that._flattenMenuCols(groups);
                if (flattenMenuCols.length !== that.owner.columns.length) {
                    that._syncMenuCols(flattenMenuCols, that.owner.columns);
                }
            }

            var html = kendo.template(mobileTemplate)({
                ns: kendo.ns,
                field: that.field,
                title: that.title || that.field,
                messages: options.messages,
                sortable: options.sortable,
                filterable: options.filterable,
                columns: columns,
                showColumns: options.columns,
                hasLockableColumns: options.hasLockableColumns,
                hasStickableColumns: options.hasStickableColumns,
                hasGroups: that._hasGroups(),
                groups: groups,
                reorderable: options.reorderable,
                groupable: options.groupable
            });

            that.view = that.pane.append(html);
            that.view.state = { columns: {} };

            that.wrapper = that.view.element.find(".k-column-menu");

            that.menu = new MobileMenu(that.wrapper.children(), {
                pane: that.pane,
                columnMenu: that
            });

            // The toggle animation of the switches should not propagate to the view
            that.menu.element.on("transitionend" + NS, function(e) {
                e.stopPropagation();
            });

            var viewElement = that.view.wrapper && that.view.wrapper[0] ? that.view.wrapper : that.view.element;

            viewElement.on("click", ".k-header-done", function(e) {
                e.preventDefault();

                that.menu._applyChanges();
                that.menu._cancelChanges(false);
                that.close();
            });

            viewElement.on("click", ".k-header-cancel", function(e) {
                e.preventDefault();

                that.menu._cancelChanges(true);
                that.close();
            });

            that.view.bind("showStart", function() {
                var view = that.view || { columns: {} };

                if (that.options.hasLockableColumns) {
                    that._updateLockedColumns();
                }

                if (that.options.hasStickableColumns) {
                    that._updateStickyColumns();
                }

                if (that.options.reorderable) {
                    that._updateReorderColumns();
                }

                if (that.options.groupable) {
                    that._updateGroupColumns();
                }

                if (view.element.find(".k-sort-asc.k-selected").length) {
                    view.state.initialSort = "asc";
                } else if (view.element.find(".k-sort-desc.k-selected").length) {
                    view.state.initialSort = "desc";
                }
            });
        },

        destroy: function() {
            var that = this;

            Widget.fn.destroy.call(that);

            if (that.filterMenu) {
                that.filterMenu.destroy();
            }

            if (that._refreshHandler) {
                that.dataSource.unbind(CHANGE, that._refreshHandler);
            }

            if (that.options.columns && that.owner) {
                if (that._updateColumnsMenuHandler) {
                    that.owner.unbind("columnShow", that._updateColumnsMenuHandler);
                    that.owner.unbind("columnHide", that._updateColumnsMenuHandler);
                }

                if (that._updateColumnsLockedStateHandler) {
                    that.owner.unbind("columnLock", that._updateColumnsLockedStateHandler);
                    that.owner.unbind("columnUnlock", that._updateColumnsLockedStateHandler);
                }
            }

            if (that.menu) {
                that.menu.element.off(NS);
                that.menu.destroy();
            }

            that.wrapper.off(NS);

            if (that.popup) {
                that.popup.destroy();
            }

            if (that.view) {
                that.view.purge();
            }

            that.link.off(NS);
            that.owner = null;
            that.wrapper = null;
            that.element = null;
        },

        close: function() {
            this._preventMenuCloseOnColumnVisibilityChange = false;

            if (this.menu) {
                this.menu.close();
            }
            if (this.popup) {
                this.popup.close();
                this.popup.element.off("keydown" + NS);
            }
        },

        _click: function(e) {
            var that = this;

            e.preventDefault();
            e.stopPropagation();

            var options = this.options;

            if (options.filter && this.element.is(!options.filter)) {
                return;
            }

            if (!this.popup && !this.pane) {
                this._init();
            } else {
                that._updateMenuItems();
            }

            if (this._isMobile) {
                this.pane.navigate(this.view, this.options.animations.left);
            } else {
                this.popup.toggle();
            }
        },

        _updateMenuItems: function() {
            var that = this;
            if (that.options.columns) {
                that._setMenuItemsVisibility();
                if (!that.options.columns.sort && !that.options.columns.groups) {
                    that._reorderMenuItems();
                } else {
                    that._updateDataIndexes();
                }
            }
        },

        _setMenuItemsVisibility: function() {
            var that = this;

            that._eachRenderedMenuItem(function(index, column, renderedListElement) {
                if (column.matchesMedia === false) {
                    renderedListElement.hide();
                } else {
                    renderedListElement.show();
                }
            });
        },

        _reorderMenuItems: function() {
            const that = this;

            that._eachRenderedMenuItem(function(index, column, renderedListElement, renderedList) {
                if (renderedListElement[0] && renderedListElement.index() !== index) {
                    insertElementAt(index, renderedListElement, renderedList);
                }
            });
            that._updateDataIndexes();
        },

        _updateDataIndexes: function() {
            const that = this;
            const renderedList = that._getRenderedList();
            const mappedColumns = that._ownerColumns(true).map(function(x) {
                return x.title || x.field;
            });

            let inputs;
            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                inputs = renderedList.find("input[type=checkbox]");
            } else {
                inputs = renderedList.find("span." + (this._isMobile ? "k-listgroup-form-field-wrapper" : "k-menu-link") + " input");
            }

            inputs.each(function(i) {
                var columns;
                var index;
                if (that.options.columns.sort) {
                    columns = that._ownerColumns();
                    index = mappedColumns.indexOf(columns[i].title);
                    $(this).attr(kendo.attr("index"), index);
                } else {
                    $(this).attr(kendo.attr("index"), i);
                }
            });
        },

        _eachRenderedMenuItem: function(callback) {
            const that = this;
            let renderedListElement;
            let duplicateColumnIndex;
            let fieldValue;
            let currentColumn;
            const columns = grep(leafColumns(that.owner.columns), function(col) {
                let result = true,
                    title = trim(col.title || "");

                if (col.menu === false || (!col.field && !title.length)) {
                    result = false;
                }

                return result;
            }).map(function(col) {
                return {
                     field: col.field,
                     title: col.title,
                     matchesMedia: col.matchesMedia
                   };
            });
            const renderedList = that._getRenderedList();
            const renderedListElements = that._getRenderedListElements(renderedList);
            const oldOccurances = oldColumnOccurrences(renderedListElements, renderedList.find("input[type=checkbox]"));
            const columnOccurrence = columnOccurrences(columns);
            let columnElements;

            for (let i = 0; i < columns.length; i++) {
                currentColumn = columns[i];
                fieldValue = currentColumn.field ? currentColumn.field : currentColumn.title;
                duplicateColumnIndex = $.inArray(i, columnOccurrence[JSON.stringify(currentColumn)]);
                columnElements = $();

                if (!oldOccurances[fieldValue]) {
                    continue;
                }

            for (let idx = 0; idx < oldOccurances[fieldValue].length; idx++) {
                columnElements = columnElements.add(renderedListElements.eq(oldOccurances[fieldValue][idx]));
            }
            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                renderedListElement = columnElements.filter(function() {
                    return $(this).find(attrEquals("field", fieldValue)).length > 0;
                }).eq(duplicateColumnIndex);
            } else {
                renderedListElement = columnElements.find(attrEquals("field", fieldValue)).closest("li").eq(duplicateColumnIndex);
            }
            callback(i, currentColumn, renderedListElement, renderedList);
        }
    },

    _getRenderedList: function() {
            var that = this;

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                return $(that.wrapper).find('.k-column-list').first();
            } else {
                return that._isMobile && that.view ?
                $(that.view.element).find(".k-columns-item").children("ul") :
                $(that.wrapper).find(".k-menu-group").first();
            }
        },

        _getRenderedListElements: function(renderedList) {
            var that = this;

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                return renderedList.find('label');
            } else {
                return renderedList.find("span." + (this._isMobile ? "k-listgroup-form-field-wrapper" : "k-menu-link"));
            }
        },

        _open: function() {
            var that = this,
                instance, menuitem;

            $(".k-column-menu").not(that.wrapper).each(function() {
                let popup = $(this).data(POPUP);
                if (popup) {
                    popup.close();
                }
            });
            that.popup.element.off("keydown" + NS).on("keydown" + NS, function(e) {
                var target = $(e.target);

                if ((that._isModernComponentType() || that._isTabbedComponentType()) && e.keyCode === kendo.keys.ENTER) {
                    target.click();
                }
                if (e.keyCode == kendo.keys.ESC) {
                    instance = kendo.widgetInstance(target.find("select"));

                    if (target.hasClass("k-picker") &&
                        instance &&
                        instance.popup.visible()) {
                            e.stopPropagation();
                            return;
                    }

                    menuitem = target.closest(".k-popup").closest(".k-menu-item");

                    if (menuitem.length > 0) {
                        menuitem.addClass("k-focus");

                        if (that.menu) {
                            that.menu.element.trigger("focus");
                        } else {
                            that.popup.element.find('[tabindex=0]').eq(0).trigger("focus");
                        }
                    }

                    target.closest(".k-popup").getKendoPopup().close();
                }
            });
            if (!that._showAdaptiveView) {
                if (that.options.hasLockableColumns) {
                    that._updateLockedColumns();
                }

                if (that.options.hasStickableColumns) {
                    that._updateStickyColumns();
                }

                if (that.options.reorderable) {
                    that._updateReorderColumns();
                }

                if (that.options.groupable) {
                    that._updateGroupColumns();
                }
            }
        },

        _activate: function() {
            if (this.menu) {
                this.menu.element.trigger("focus");
            } else {
                this.popup.element.find('[tabindex=0]').eq(0).trigger("focus");
            }

            this.trigger(OPEN, { field: this.field, container: this.wrapper });
        },

        _checkItemClass: function(item, _class) {
            if (item.is("svg,path")) {
                item = item.closest(`.${_class}`);
            }

            return item.hasClass(_class) || (item.find(`.${_class}`).length > 0);
        },

        _ownerColumns: function(omitSort) {
            var columns = leafColumns(this.owner.columns),
                menuColumns = grep(columns, function(col) {
                    var result = true,
                        title = trim(col.title || "");

                    if (col.menu === false || (!col.field && !title.length)) {
                        result = false;
                    }

                    return result;
                }),
                result,
                sort = this.options.columns.sort;

            result = map(menuColumns, function(col) {
                return {
                    originalField: col.field,
                    field: col.field || col.title,
                    title: col.title || col.field,
                    hidden: col.hidden,
                    matchesMedia: col.matchesMedia,
                    index: inArray(col, columns),
                    locked: !!col.locked,
                    _originalObject: col,
                    uid: col.headerAttributes.id
                };
            });

            if (sort && !omitSort) {
                result.sort(Comparer.create({ field: "title", dir: sort }));
            }

            return result;
        },

        _sort: function() {
            var that = this;

            if (that.options.sortable) {
                that.refresh();

                if (that._isModernComponentType() || that._isTabbedComponentType()) {
                    that.wrapper.on("click" + NS, ".k-sort-asc, .k-sort-desc", that._sortHandler.bind(that));
                } else {
                    that.menu.bind(SELECT, that._sortHandler.bind(that));
                }
            }
        },

        _sortHandler: function(e) {
            var that = this,
                item = e.item ? $(e.item) : $(e.target),
                dir;

            if (that._checkItemClass(item, "k-sort-asc") || that._checkItemClass(item, "k-svg-i-sort-asc-small")) {
                dir = ASC;
            } else if (that._checkItemClass(item, "k-sort-desc") || that._checkItemClass(item, "k-svg-i-sort-desc-small")) {
                dir = DESC;
            }

            if (!dir && !e.allowUnsort) {
                return;
            }

            that._getSortItemsContainer(item).find(".k-sort-" + (dir == ASC ? DESC : ASC)).removeClass(ACTIVE);

            that._sortDataSource(item, dir, e);

            if (!that._isMobile && !that._preventClose) {
                that.close();
            }
        },

        _getSortItemsContainer: function(item) {
            return this._isModernComponentType() || this._isTabbedComponentType() ? item.parents('.k-columnmenu-item-wrapper').first() : item.parent();
        },

        _sortDataSource: function(item, dir, eventData) {
            var that = this,
                sortable = that.options.sortable,
                compare = sortable.compare === null ? undefined : sortable.compare,
                dataSource = that.dataSource,
                idx,
                length,
                sort = dataSource.sort() || [];

            const activeStateCondition = eventData.allowSelectedState !== false ? item.hasClass(ACTIVE) : true;
            var removeClass = activeStateCondition && sortable && (sortable.allowUnsort !== false || eventData.allowUnsort);

            dir = !removeClass ? dir : undefined;

            if (that.trigger("sort", { sort: { field: that.field, dir: dir, compare: compare }, preventClose: eventData.hasCtrlKey && eventData.isMixed })) {
                return;
            }

            if (removeClass) {
                item.removeClass(ACTIVE);
            } else if (eventData.allowSelectedState !== false) {
                item.addClass(ACTIVE);
            }

            if (sortable.mode === "multiple" || eventData.isMixed) {
                for (idx = 0, length = sort.length; idx < length; idx++) {
                    if (sort[idx].field === that.field) {
                        sort.splice(idx, 1);
                        break;
                    }
                }
                sort.push({ field: that.field, dir: dir, compare: compare });
            } else {
                sort = [ { field: that.field, dir: dir, compare: compare } ];
            }

            dataSource.sort(sort);
        },

        _columns: function() {
            var that = this;

            if (that.options.columns) {

                that._updateColumnsMenu();

                that._updateColumnsMenuHandler = that._updateColumnsMenu.bind(that);

                that.owner.bind(["columnHide", "columnShow"], that._updateColumnsMenuHandler);

                that._updateColumnsLockedStateHandler = that._updateColumnsLockedState.bind(that);

                that.owner.bind(["columnUnlock", "columnLock" ], that._updateColumnsLockedStateHandler);

                that._bindHandlers();
            }
        },

        _applyColumnVisibility: function(preventClosing) {
            var that = this;
            var fieldAttr = kendo.attr("field");
            var uidAttr = kendo.attr("uid");
            const wrapper = that._getWrapper();
            var checkboxes = wrapper.find(".k-column-list-item input[" + fieldAttr + "]");

            if (!that._showAdaptiveView && !checkboxes.length) {
                checkboxes = wrapper.find(".k-columns-item input[" + fieldAttr + "]");
            }
            var columnsInMenu = grep(leafColumns(this.owner.columns), function(col) {
                var result = true,
                    title = trim(col.title || "");

                if (col.menu === false || (!col.field && !title.length)) {
                    result = false;
                }

                return result;
            });
            var length = checkboxes.length;
            var idx;
            var colIdx;
            var checkbox;
            var column;

            that.owner.unbind("columnShow", that._updateColumnsMenuHandler);
            that.owner.unbind("columnHide", that._updateColumnsMenuHandler);

            for (idx = 0; idx < length; idx++) {
                checkbox = $(checkboxes[idx]);
                colIdx = columnsInMenu.map(function(col) {
                    return col.headerAttributes.id;
                }).indexOf(checkbox.attr(uidAttr));
                column = columnsInMenu[colIdx];

               if (checkbox.is(":checked") && column.hidden) {
                   that.owner.showColumn(column);
               } else if (checkbox.is(":not(:checked)") && !column.hidden) {
                   that.owner.hideColumn(column);
               }
            }


            const shouldPreventPopupClosing = preventClosing ? preventClosing : that._showAdaptiveView;
            if (!shouldPreventPopupClosing) {
                that.popup.close();
            }
            that.owner.bind(["columnHide", "columnShow"], that._updateColumnsMenuHandler);
        },

        _sizeColumns: function() {
            var that = this;

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                that.wrapper.on("click" + NS, ".k-auto-size-column, .k-auto-size-all", that._autoSizeHandler.bind(that));
            } else {
                that.menu.bind(SELECT, that._autoSizeHandler.bind(that));
            }
        },

        _clearAllFilters: function() {
            var that = this;

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                that.wrapper.on("click" + NS, ".k-clear-all-filters", that._clearAllFiltersHandler.bind(that));
            } else {
                that.menu.bind(SELECT, that._clearAllFiltersHandler.bind(that));
            }
        },

        _clearAllFiltersHandler: function(e) {
            var that = this,
                item = e.item ? $(e.item) : $(e.target);

            if (item.hasClass("k-clear-all-filters") && that.owner && that.owner.dataSource) {
                that.owner.dataSource.filter({});
            }
        },

        _autoSizeHandler: function(e) {
            var that = this,
                item = e.item ? $(e.item) : $(e.target);

            if (that._checkItemClass(item, "k-auto-size-column")) {
                that.owner.autoFitColumn(that.field);
            } else if (that._checkItemClass(item, "k-auto-size-all")) {
                that.owner.autoFitColumns();
            }
        },

        _updateColumnsMenu: function(omitCheckState) {
            var idx, length, current, checked, locked, that = this;
            const isAdaptive = that._showAdaptiveView;
            var fieldAttr = kendo.attr("field"),
                lockedAttr = kendo.attr("locked"),
                uidAttr = kendo.attr("uid"),
                columnIndexMap = {},
                columnsCount = 0,
                colIdx = 0;
                omitCheckState = omitCheckState === true;
                const wrapper = that._getWrapper();
                var columnsInMenu = grep(leafColumns(this.owner.columns), function(col, idx) {
                    var result = true,
                        title = trim(col.title || "");

                    if (col.menu === false || (!col.field && !title.length)) {
                        result = false;
                    }

                    if (result) {
                        columnIndexMap[idx] = columnsCount;
                        columnsCount++;
                    }

                    return result;
                }),
                visibleFields = grep(this._ownerColumns(), function(field) {
                    if (omitCheckState) {
                         return wrapper.find("[role='menuitemcheckbox'] [" + uidAttr + "='" + field.uid + "']").prop('checked');
                    }
                    return !field.hidden && field.matchesMedia !== false;
                }),
                visibleDataFields = grep(visibleFields, function(field) {
                    return field.originalField;
                }),
                lockedCount = grep(visibleDataFields, function(col) {
                    return col.locked === true;
                }),
                nonLockedCount = grep(visibleDataFields, function(col) {
                    return col.locked !== true;
                }),
                columnsNotInMenu = grep(this.owner.columns, function(col) {
                    return col.menu === false;
                }),
                hiddenColumnsNotInMenu = grep(columnsNotInMenu, function(col) {
                    return col.hidden;
                }),
                visibleColumnsNotInMenu = grep(columnsNotInMenu, function(col) {
                    return !col.hidden;
                });


            const items = wrapper.find("[role='menuitemcheckbox']");

            items.attr("aria-checked", false);

            const checkboxSelector = isAdaptive ? ".k-column-list-item input[" + fieldAttr + "]" : ".k-columns-item input[" + fieldAttr + "]";
            var checkboxes = wrapper
            .find(checkboxSelector);

            if (items.length && !checkboxes.length) {
                var checkboxes = wrapper
                    .find(".k-column-list-item input[" + fieldAttr + "]");
            }

            checkboxes.prop("disabled", false);

            if (!omitCheckState) {
                checkboxes.prop("checked", false);
            }

            var switchWidget;

            for (idx = 0, length = checkboxes.length; idx < length; idx ++) {
                current = checkboxes.eq(idx);
                locked = current.attr(lockedAttr) === "true";
                checked = false;
                switchWidget = current.data("kendoSwitch");
                colIdx = columnsInMenu.map(function(col) {
                    return col.headerAttributes.id;
                }).indexOf(current.attr(uidAttr));

                checked = omitCheckState ? current.prop('checked') : !columnsInMenu[colIdx].hidden && columnsInMenu[colIdx].matchesMedia !== false;
                current.prop("checked", checked);

                if (switchWidget) {
                    switchWidget.enable(true);
                    switchWidget.check(checked);
                }

                current.closest("[role='menuitemcheckbox']").attr("aria-checked", checked);

                if (checked) {
                    that._disableCheckbox(current, locked, {
                        columnsNotInMenu,
                        hiddenColumnsNotInMenu,
                        visibleColumnsNotInMenu,
                        lockedCount,
                        nonLockedCount,
                    },
                    switchWidget);
                }
            }
        },

        _disableCheckbox: function(current, locked, columns, switchWidget) {
            const { lockedCount, nonLockedCount } = columns;

            if (locked && lockedCount && lockedCount.length === 1) {
                current.prop("disabled", true);

                if (switchWidget) {
                    switchWidget.enable(false);
                }
            }

            if (!locked && nonLockedCount && nonLockedCount.length === 1) {
                current.prop("disabled", true);

                if (switchWidget) {
                    switchWidget.enable(false);
                }
            }
        },

        _updateColumnsLockedState: function() {
            const that = this;
            var idx, length, current, column;
            var fieldAttr = kendo.attr("field");
            var lockedAttr = kendo.attr("locked");
            var columns = toHash(this._ownerColumns(), "field");
            const isAdaptive = that._showAdaptiveView;
            const wrapper = that._getWrapper();
            const checkboxSelector = isAdaptive ? ".k-column-list-wrapper input[type=checkbox]" : ".k-columns-item input[type=checkbox]";
            var checkboxes = wrapper
                .find(checkboxSelector);

            for (idx = 0, length = checkboxes.length; idx < length; idx ++ ) {
                current = checkboxes.eq(idx);
                column = columns[current.attr(fieldAttr)];
                if (column) {
                    current.attr(lockedAttr, column.locked?.toString());
                }
            }

            this._updateColumnsMenu();
        },

        _filter: function(selector, options) {
            var that = this,
                widget = FILTERMENU,
                options = options || that.options;

                const wrapper = that._getWrapper();

            if (options.filterable !== false) {

                if (options.filterable.multi) {
                    widget = "kendoFilterMultiCheck";
                    if (options.filterable.dataSource) {
                        options.filterable.checkSource = options.filterable.dataSource;
                        delete options.filterable.dataSource;
                    }
                }
                that.filterMenu = wrapper.find(selector || ".k-filterable")[widget](
                    extend(true, {}, {
                        appendToElement: true,
                        dataSource: options.dataSource,
                        values: options.values,
                        field: that.field || options.field,
                        title: that.title,
                        adaptiveMode: that._showAdaptiveView ? "auto" : "none",
                        change: function(e) {
                            if (that.trigger("filtering", { filter: e.filter, field: e.field })) {
                                e.preventDefault();
                            }
                        },
                        componentType: that.options.componentType,
                        cycleForm: !that._isModernComponentType() && !that._isTabbedComponentType()
                    },
                    options.filterable)
                    ).data(widget);

                if (that.filterMenu && that._columnMenuAdaptiveView) {
                    that.filterMenu.form.on(CHANGE, function(e) {
                        that._columnMenuAdaptiveView._hasChanges = true;
                    });

                    that.filterMenu.form.on("input", function(e) {
                        that._columnMenuAdaptiveView._hasChanges = true;
                    });
                }

                if (that._isMobile) {
                    that.menu.bind(SELECT, function(e) {
                        var item = $(e.item);

                        if (item.hasClass("k-filter-item")) {
                            that.pane.navigate(that.filterMenu.view, that.options.animations.left);
                        }
                    });
                }
            }
        },

        _lockColumns: function() {
            var that = this;
            const wrapper = that._getWrapper();

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                wrapper.on("click" + NS, ".k-lock, .k-unlock", that._lockableHandler.bind(that));
            } else {
                that.menu.bind(SELECT, that._lockableHandler.bind(that));
            }
        },

        _lockableHandler: function(e) {
            var that = this;
            var item = e.item ? $(e.item) : $(e.target);

            if (that._checkItemClass(item, "k-lock")) {
                that.owner.lockColumn(that.field);
                if (!that._isMobile) {
                    that.close();
                }
            } else if (that._checkItemClass(item, "k-unlock")) {
                that.owner.unlockColumn(that.field);
                if (!that._isMobile) {
                    that.close();
                }
            }
        },

        _getWrapper: function() {
            const that = this;
            let wrapper;

            if (that._columnMenuAdaptiveView) {
                wrapper = that._showAdaptiveView ? that._columnMenuAdaptiveView.wrapper : that.wrapper;
            } else if (that.popup) {
                wrapper = that._showAdaptiveView ? that.popup.wrapper : that.wrapper;
            } else {
                wrapper = that.wrapper;
            }

            return wrapper;
        },

        _reorderColumns: function() {
            var that = this;
            const wrapper = that._getWrapper();

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                wrapper.on("click" + NS, ".k-move-prev, .k-move-next", that._reorderHandler.bind(that));
            } else {
                that.menu.bind(SELECT, that._reorderHandler.bind(that));
            }
        },

        _reorderHandler: function(e) {
            var that = this;
            var item = e.item ? $(e.item) : $(e.target);

            if (item.hasClass("k-move-prev")) {
                that.owner._moveColumn(that.element, true);
                if (!that._isMobile) {
                    that.close();
                }
            } else if (item.hasClass("k-move-next")) {
                that.owner._moveColumn(that.element, false);
                if (!that._isMobile) {
                    that.close();
                }
            }
        },

        _groupColumn: function() {
            var that = this;

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                that.wrapper.on("click" + NS, ".k-group, .k-ungroup", that._groupHandler.bind(that));
            } else {
                that.menu.bind(SELECT, that._groupHandler.bind(that));
            }
        },

        _groupHandler: function(e) {
            var that = this,
                item = e.item ? $(e.item) : $(e.target);
            if (that.owner.groupable) {
                const descriptors = that.owner.groupable.descriptors();
                const descriptorFound = descriptors.find((descriptor) => descriptor.field === that.field);

                if (item.hasClass("k-group") && !descriptorFound) {
                    that.owner._handleSpaceKey(that.element, true);

                    if (!that._isMobile) {
                        that.close();
                    }
                } else if (item.hasClass("k-ungroup") && descriptorFound) {
                    that.owner._handleSpaceKey(that.element, true);

                    if (!that._isMobile) {
                        that.close();
                    }
                }
            }
        },

        _stickyColumns: function() {
            var that = this;

            const wrapper = that._showAdaptiveView && that._columnMenuAdaptiveView ? that._columnMenuAdaptiveView.wrapper : that.wrapper;

            if (that._isModernComponentType() || that._isTabbedComponentType()) {
                wrapper.on("click" + NS, ".k-stick, .k-unstick", that._stickableHandler.bind(that));
            } else {
                that.menu.bind(SELECT, that._stickableHandler.bind(that));
            }
        },

        _stickableHandler: function(e) {
            var that = this;
            var item = e.item ? $(e.item) : $(e.target);
            var field = that.field;
            var columns = that.owner.columns;
            var column = grep(columns, function(column) {
                return column.field == field || column.title == field;
            })[0];

            if (that._checkItemClass(item, "k-stick")) {
                that.owner.stickColumn(that.field);
                that.trigger(STICK, { column: column });
                if (!that._isMobile) {
                    that.close();
                }
            } else if (that._checkItemClass(item, "k-unstick")) {
                that.owner.unstickColumn(that.field);
                that.trigger(UNSTICK, { column: column });
                if (!that._isMobile) {
                    that.close();
                }
            }
        },

        _updateLockedColumns: function() {
            const that = this;
            var field = this.field;
            var columns = this.owner.columns;
            const wrapper = that._showAdaptiveView && that._columnMenuAdaptiveView ? that._columnMenuAdaptiveView.wrapper : that.wrapper;
            var column = grep(columns, function(column) {
                return column.field == field || column.title == field;
            })[0];

            if (!column) {
                return;
            }

            var locked = column.locked === true;
            var length = grep(columns, function(column) {
                return !column.hidden && ((column.locked && locked) || (!column.locked && !locked));
            }).length;
            var notLockable = column.lockable === false;

            var lockItem = wrapper.find(".k-lock").removeClass("k-disabled").removeAttr("aria-disabled");
            var unlockItem = wrapper.find(".k-unlock").removeClass("k-disabled").removeAttr("aria-disabled");

            if (locked || length == 1 || notLockable) {
                lockItem.addClass("k-disabled").attr("aria-disabled", "true");
            }

            if (!locked || length == 1 || notLockable) {
                unlockItem.addClass("k-disabled").attr("aria-disabled", "true");
            }

            this._updateColumnsLockedState();
        },

        _updateStickyColumns: function() {
            const that = this;
            var field = this.field;
            var columns = this.owner.columns;
            const wrapper = that._showAdaptiveView && that._columnMenuAdaptiveView ? that._columnMenuAdaptiveView.wrapper : that.wrapper;
            var column = grep(columns, function(column) {
                return column.field == field || column.title == field;
            })[0];

            if (!column) {
                return;
            }

            var sticky = column.sticky === true;
            var stickable = column.stickable === true;
            var locked = column.locked === true;
            var length = grep(columns, function(column) {
                return !column.hidden && ((column.locked && locked) || (!column.locked && !locked));
            }).length;

            var stickItem = wrapper.find(".k-stick").removeClass("k-disabled").removeAttr("aria-disabled");
            var unstickItem = wrapper.find(".k-unstick").removeClass("k-disabled").removeAttr("aria-disabled");

            if (sticky || !stickable || (locked && length === 1)) {
                stickItem.addClass("k-disabled").attr("aria-disabled", "true");
            }

            if (!sticky || !stickable) {
                unstickItem.addClass("k-disabled").attr("aria-disabled", "true");
            }
        },

        _updateReorderColumns: function() {
            const that = this;
            const wrapper = that._showAdaptiveView && that._columnMenuAdaptiveView ? that._columnMenuAdaptiveView.wrapper : that.wrapper;
            var element = this.element,
                elementIndex = element.index(),
                numberOfSiblings = element.parent().children().length;

            var prevItem = wrapper.find(".k-move-prev").removeClass("k-disabled").removeAttr("aria-disabled");
            var nextItem = wrapper.find(".k-move-next").removeClass("k-disabled").removeAttr("aria-disabled");

            if (this.element.index() === 0) {
                prevItem.addClass("k-disabled").attr("aria-disabled", "true");
            }

            if (elementIndex + 1 === numberOfSiblings) {
                nextItem.addClass("k-disabled").attr("aria-disabled", "true");
            }
        },

        _updateGroupColumns: function() {
            const that = this;
            const wrapper = that._showAdaptiveView && that._columnMenuAdaptiveView ? that._columnMenuAdaptiveView.wrapper : that.wrapper;
            var element = this.element,
                groupEl = wrapper.find(".k-menu-item.k-group"),
                ungroupEl = wrapper.find(".k-menu-item.k-ungroup");

            if (this.owner.groupable._canDrag(element)) {
                groupEl.removeClass("k-hidden");
                ungroupEl.addClass("k-hidden");
            } else {
                groupEl.addClass("k-hidden");
                ungroupEl.removeClass("k-hidden");
            }
        },

        refresh: function() {
            var that = this,
                sort = that.options.dataSource.sort() || [],
                descriptor,
                field = that.field,
                idx,
                length;

            that.wrapper.find(".k-sort-asc, .k-sort-desc").removeClass(ACTIVE);

            for (idx = 0, length = sort.length; idx < length; idx++) {
               descriptor = sort[idx];

               if (field == descriptor.field) {
                    that.wrapper.find(".k-sort-" + descriptor.dir).addClass(ACTIVE);
               }
            }
            if (that.link.is("[ref-toolbar-tool]")) {
                return;
            }
            that.link[that._filterExist(that.dataSource.filter()) ? "addClass" : "removeClass"]("k-active");
        },

        _filterExist: function(filters) {
            var found = false;
            var filter;

            if (!filters) {
                return;
            }

            filters = filters.filters;

            for (var idx = 0, length = filters.length; idx < length; idx++) {
                filter = filters[idx];

                if (filter.field == this.field) {
                    found = true;
                } else if (filter.filters) {
                    found = found || this._filterExist(filter);
                }
            }

            return found;
        }
    });

    /* ------------------------- MODERN TEMPLATE ------------------------- */

    function modernColumnsTemplateIterator(columns, encodeTitles, ns, isAdaptive) {
        return columns.map((column) => {
            if (column.groupHeader) {
                return `<span class="k-column-menu-group-header"><span class="k-column-menu-group-header-text">${column.title}</span></span>`;
            } else {
                return `<label class="k-column-list-item" role="menuitemcheckbox" aria-checked="false" ${column.matchesMedia === false ? `${kendo.attr("style-display")}="none"` : ""}><input class="k-checkbox${isAdaptive ? " k-checkbox-lg" : ""}" type="checkbox" title="${encodeTitles ? encode(column.title) : column.title}" data-${ns}field="${column.field.replace(/\"/g, "&#34;")}" data-${ns}index="${column.index}" data-${ns}locked="${column.locked}" data-${ns}uid="${column.uid}" /><span class="k-checkbox-label">${encodeTitles ? encode(column.title) : column.title}</span></label>`;
            }
        }).join("");
    }

const SIZING_PARTIAL_MODERN = ({ messages, hideAutoSizeColumn }) => `<div class="k-columnmenu-item-wrapper">\
${!hideAutoSizeColumn ? `<div class="k-columnmenu-item k-auto-size-column" ref="auto-size" tabindex="0">\
${kendo.ui.icon("max-width")}${encode(messages.autoSizeColumn)}\
</div>` : ''}
<div class="k-columnmenu-item k-auto-size-all" ref="auto-size-all" tabindex="0">\
${kendo.ui.icon("display-inline-flex")}${encode(messages.autoSizeAllColumns)}\
</div>\
</div>`;

const CLEARALLFILTERS_PARTIAL_MODERN = ({ messages }) => `<div class="k-columnmenu-item-wrapper">\
<div class="k-columnmenu-item k-clear-all-filters" ref="clear-all-filters" tabindex="0">\
${kendo.ui.icon("filter-clear")}${encode(messages.clearAllFilters)}\
</div>\
</div>`;


const SORTABLE_PARTIAL_MODERN = ({ messages }) => `<div class="k-columnmenu-item-wrapper">\
<div class="k-columnmenu-item k-sort-asc" tabindex="0" ref="sort-asc">\
${kendo.ui.icon("sort-asc-small")}${encode(messages.sortAscending)}\
</div>\
<div class="k-columnmenu-item k-sort-desc" tabindex="0" ref="sort-asc">\
${kendo.ui.icon("sort-desc-small")}${encode(messages.sortDescending)}\
</div>\
</div>`;

    const COLUMNS_PARTIAL_MODERN = ({ columns, messages, encodeTitles, ns, isAdaptive, ref }) => `<div class="k-columnmenu-item-wrapper">\
<div class="k-columnmenu-item-content k-columns-item" ref="columns-visibility">\
<div class="k-column-list-wrapper">\
<div class="k-column-list ${isAdaptive ? "k-column-list-lg" : ""}" role="menu">\
${modernColumnsTemplateIterator(columns, encodeTitles, ns, isAdaptive)}\
</div>\
${isAdaptive ? "" : "<div class='k-actions-stretched k-columnmenu-actions'>" +
    kendo.html.renderButton(`<button>${encode(messages.apply)}</button>`, { themeColor: "primary", icon: "check" }) +
    kendo.html.renderButton(`<button>${encode(messages.reset)}</button>`, { icon: "undo" }) +
"</div>"}\
</div>\
</div>\
</div>`;

const COLUMN_CHOOSER = ({ columns, messages, encodeTitles, ns, isAdaptive }) => `<div class="k-column-list-wrapper" ref="column-chooser">\
<div class="k-column-list ${isAdaptive ? "k-column-list-lg" : ""}" role="menu">\
${modernColumnsTemplateIterator(columns, encodeTitles, ns, isAdaptive)}\
</div>\
${isAdaptive ? "" : "<div class='k-actions k-actions-stretched k-actions-horizontal k-column-menu-footer'>" +
    kendo.html.renderButton(`<button>${encode(messages.apply)}</button>`, { themeColor: "primary", icon: "check" }) +
    kendo.html.renderButton(`<button>${encode(messages.reset)}</button>`, { icon: "undo" }) +
"</div>"}\
</div>`;

const COLUMNS_PARTIAL_MODERN_ADAPTIVE = ({ columns, messages, encodeTitles, ns, isAdaptive, ref }) => `<div class="k-column-list-wrapper" ref="columns-visibility">\
<div class="k-column-list k-column-list-lg" role="menu">\
${modernColumnsTemplateIterator(columns, encodeTitles, ns, isAdaptive)}\
</div>\
</div>`;

    const GROUPABLE_PARTIAL_MODERN = ({ messages }) => `<div class="k-columnmenu-item-wrapper">\
<div class="k-columnmenu-item k-group" tabindex="0" ref="group">\
${kendo.ui.icon("group")}${encode(messages.groupColumn)}\
</div>\
</div>
<div class="k-columnmenu-item-wrapper">\
<div class="k-columnmenu-item k-ungroup" tabindex="0" ref="ungroup">\
${kendo.ui.icon("ungroup")}${encode(messages.ungroupColumn)}\
</div>\
</div>`;

    const LOCKABLE_COLUMNS_PARTIAL_MODERN = ({ messages }) => `<div class="k-columnmenu-item k-lock" ref="lock-column" tabindex="0">\
${kendo.ui.icon("lock")}${encode(messages.lock)}\
</div>\
<div class="k-columnmenu-item k-unlock" ref="unlock-column" tabindex="0">\
${kendo.ui.icon("unlock")}${encode(messages.unlock)}\
</div>`;

    const STICKABLE_COLUMNS_PARTIAL_MODERN = ({ messages }) => `<div class="k-columnmenu-item k-stick" tabindex="0" ref="stick-column">\
${kendo.ui.icon("stick")}${encode(messages.stick)}\
</div>\
<div class="k-columnmenu-item k-unstick" tabindex="0">\
${kendo.ui.icon("unstick")}${encode(messages.unstick)}\
</div>`;

    const REORDERABLE_COLUMNS_PARTIAL_MODERN = ({ messages }) => `<div class="k-columnmenu-item k-move-prev" tabindex="0" ref="reorder-prev">\
${kendo.ui.icon(`caret-alt-${isRtl ? "right" : "left"}`)}${encode(messages.movePrev)}\
</div>\
<div class="k-columnmenu-item k-move-next" tabindex="0" ref="reorder-next">\
${kendo.ui.icon(`caret-alt-${isRtl ? "left" : "right"}`)}${encode(messages.moveNext)}\
</div>`;

    const LOCK_STICK_COLUMNS_PARTIAL_MODERN = ({ hasLockableColumns, hasStickableColumns, messages, reorderable }) => `<div class="k-columnmenu-item-wrapper">\
<div class="k-column-menu-position">\
${hasLockableColumns ? LOCKABLE_COLUMNS_PARTIAL_MODERN({ messages }) : ''}\
${hasStickableColumns ? STICKABLE_COLUMNS_PARTIAL_MODERN({ messages }) : ''}\
${reorderable ? REORDERABLE_COLUMNS_PARTIAL_MODERN({ messages }) : ''}\
</div>\
</div>`;

    var modernTemplate = ({ autoSize, hideAutoSizeColumn, sortable, filterable, clearAllFilters, showColumns, messages, columns, hasLockableColumns, hasStickableColumns, encodeTitles, ns, reorderable, groupable, isAdaptive }) => `\
${sortable ? SORTABLE_PARTIAL_MODERN({ messages }) : ''}\
${showColumns ? COLUMNS_PARTIAL_MODERN({ columns, messages, encodeTitles, ns, isAdaptive }) : ''}\
${filterable ? '<div class="k-columnmenu-item-wrapper"><div class="k-columnmenu-item-content k-column-menu-filter"><div class="k-filterable"></div></div></div>' : ''}\
${groupable ? GROUPABLE_PARTIAL_MODERN({ messages }) : ''}\
${autoSize ? SIZING_PARTIAL_MODERN({ messages, hideAutoSizeColumn }) : ''}\
${clearAllFilters ? CLEARALLFILTERS_PARTIAL_MODERN({ messages }) : ''}\
${hasLockableColumns || hasStickableColumns || reorderable ? LOCK_STICK_COLUMNS_PARTIAL_MODERN({ hasLockableColumns, hasStickableColumns, messages, reorderable }) : ''}`;

    /* ------------------------- TABBED TEMPLATE ------------------------- */

    function tabbedTemplateGeneralSettings(sortable, hasLockableColumns, hasStickableColumns, reorderable, groupable, autoSize, messages, hideAutoSizeColumn, clearAllFilters) {
        var result = "<div>";

        if (sortable) {
            result += SORTABLE_PARTIAL_MODERN({ messages });
        }

        if (groupable) {
            result += GROUPABLE_PARTIAL_MODERN({ messages });
        }

        if (hasLockableColumns || hasStickableColumns || reorderable) {
            result += LOCK_STICK_COLUMNS_PARTIAL_MODERN({ hasLockableColumns, hasStickableColumns, messages, reorderable });
        }

        if (autoSize) {
            result += SIZING_PARTIAL_MODERN({ messages, hideAutoSizeColumn });
        }

        if (clearAllFilters) {
            result += CLEARALLFILTERS_PARTIAL_MODERN({ messages });
        }

        result += "</div>";
        return result;
    }

    var tabbedTemplate = ({ sortable, filterable, clearAllFilters, showColumns, messages, columns, hasLockableColumns, hasStickableColumns, encodeTitles, ns, reorderable, groupable, autoSize, hideAutoSizeColumn }) => `<div>
                            <ul>
                                ${ filterable ? `<li>${kendo.ui.icon("filter")}</li>` : ''}
                                ${ sortable || hasLockableColumns || hasStickableColumns || reorderable || groupable || autoSize ? `<li>${kendo.ui.icon("sliders")}</li>` : ''}
                                ${ showColumns ? `<li>${kendo.ui.icon("columns")}</li>` : ''}
                            </ul>
                            ${filterable ? '<div><div class="k-columnmenu-item-wrapper" ref="filter"><div class="k-columnmenu-item-content k-column-menu-filter"><div class="k-filterable"></div></div></div></div>' : ''}
                            ${ sortable || hasLockableColumns || hasStickableColumns || reorderable || groupable || autoSize ? tabbedTemplateGeneralSettings(sortable, hasLockableColumns, hasStickableColumns, reorderable, groupable, autoSize, messages, hideAutoSizeColumn, clearAllFilters) : '' }
                            ${ showColumns ? `<div>${ COLUMNS_PARTIAL_MODERN({ columns, messages, encodeTitles, ns }) }</div>` : '' }
                        </div>`;

    /* ------------------------- CLASSIC TEMPLATE ------------------------- */

    function classicColumnsTemplateIterator(columns, encodeTitles, ns, omitWrapAttribute) {
        return columns.map((column) => {
            if (column.groupHeader) {
                return `<li class="k-column-menu-group-header" ${omitWrapAttribute}="true" ><span class="k-column-menu-group-header-text">${column.title}</span></li>`;
            } else {
                return `<li role="menuitemcheckbox" aria-checked="false" ${column.matchesMedia === false ? `${kendo.attr("style-display")}="none"` : ""}><input type="checkbox" class="k-checkbox" title="${encodeTitles ? encode(column.title) : column.title}" data-${ns}field="${column.field.replace(/\"/g,"&#34;")}" data-${ns}index="${column.index}" data-${ns}locked="${column.locked}" data-${ns}uid="${column.uid}"/>${encodeTitles ? encode(column.title) : column.title}</li>`;
            }
        }).join("");
    }

    const SORTABLE_PARTIAL_CLASSIC = ({ messages, showColumns, filterable }) => `\
<li class="k-item k-menu-item k-sort-asc" ref="sort-asc"><span class="k-link k-menu-link">${kendo.ui.icon("sort-asc-small")}<span class="k-menu-link-text">${encode(messages.sortAscending)}</span></span></li>\
<li class="k-item k-menu-item k-sort-desc" ref="sort-desc"><span class="k-link k-menu-link">${kendo.ui.icon("sort-desc-small")}<span class="k-menu-link-text">${encode(messages.sortDescending)}</span></span></li>\
${showColumns || filterable ? '<li class="k-separator k-menu-separator" role="presentation"></li>' : ''}`;

    const COLUMNS_PARTIAL_CLASSIC = ({ columns, messages, encodeTitles, ns, omitWrapAttribute, filterable, hasLockableColumns, hasStickableColumns }) => `\
<li class="k-item k-menu-item k-columns-item" aria-haspopup="true" ref="columns-visability"><span class="k-link k-menu-link">${kendo.ui.icon("columns")}<span class="k-menu-link-text">${encode(messages.columns)}</span></span><ul>\
${classicColumnsTemplateIterator(columns, encodeTitles, ns, omitWrapAttribute)}\
</ul></li>\
${filterable || hasLockableColumns || hasStickableColumns ? '<li class="k-separator k-menu-separator" role="presentation"></li>' : ''}`;

const FILTERABLE_PARTIAL_CLASSIC = ({ messages, hasLockableColumns, hasStickableColumns, reorderable }) => `<li class="k-item k-menu-item k-filter-item" ref="filter" aria-haspopup="true"><span class="k-link k-menu-link">${kendo.ui.icon("filter")}<span class="k-menu-link-text">${encode(messages.filter)}</span></span><ul>\
<li><div class="k-filterable"></div></li>\
</ul></li>\
${hasLockableColumns || hasStickableColumns || reorderable ? '<li class="k-separator k-menu-separator" role="presentation"></li>' : ''}`;

    const GROUPABLE_PARTIAL_CLASSIC = ({ messages, hasLockStickMove }) => `\
<li class="k-item k-menu-item k-group" ref="group"><span class="k-link k-menu-link">${kendo.ui.icon("group")}<span class="k-menu-link-text">${encode(messages.groupColumn)}</span></span></li>\
<li class="k-item k-menu-item k-ungroup" ref="ungroup"><span class="k-link k-menu-link">${kendo.ui.icon("ungroup")}<span class="k-menu-link-text">${encode(messages.ungroupColumn)}</span></span></li>\
${hasLockStickMove ? '<li class="k-separator k-menu-separator" role="presentation"></li>' : ''}`;

    const LOCKABLE_COLUMNS_PARTIAL_CLASSIC = ({ messages, hasStickableColumns }) => `\
<li class="k-item k-menu-item k-lock" ref="lock-column"><span class="k-link k-menu-link">${kendo.ui.icon("lock")}<span class="k-menu-link-text">${encode(messages.lock)}</span></span></li>\
<li class="k-item k-menu-item k-unlock" ref="unlock-column"><span class="k-link k-menu-link">${kendo.ui.icon("unlock")}<span class="k-menu-link-text">${encode(messages.unlock)}</span></span></li>\
${hasStickableColumns ? '<li class="k-separator k-menu-separator" role="presentation"></li>' : ''}`;

    const STICKABLE_COLUMNS_PARTIAL_CLASSIC = ({ messages }) => `\
<li class="k-item k-menu-item k-stick" ref="stick-column"><span class="k-link k-menu-link">${kendo.ui.icon("stick")}<span class="k-menu-link-text">${encode(messages.stick)}</span></span></li>\
<li class="k-item k-menu-item k-unstick" ref="unstick-column"><span class="k-link k-menu-link">${kendo.ui.icon("unstick")}<span class="k-menu-link-text">${encode(messages.unstick)}</span></span></li>`;

    const REORDERABLE_COLUMNS_PARTIAL_CLASSIC = ({ messages }) => `\
<li class="k-item k-menu-item k-move-prev" ref="reorder-prev"><span class="k-link k-menu-link">${kendo.ui.icon(`caret-alt-${isRtl ? "right" : "left"}`)}<span class="k-menu-link-text">${encode(messages.movePrev)}</span></span></li>\
<li class="k-item k-menu-item k-move-next" ref="reorder-next"><span class="k-link k-menu-link">${kendo.ui.icon(`caret-alt-${isRtl ? "left" : "right"}`)}<span class="k-menu-link-text">${encode(messages.moveNext)}</span></span></li>`;

    const LOCK_STICK_COLUMNS_PARTIAL_CLASSIC = ({ messages, hasLockableColumns, hasStickableColumns, reorderable }) => `\
<li class="k-item k-menu-item k-position-item" aria-haspopup="true"><span class="k-link k-menu-link">${kendo.ui.icon("set-column-position")}<span class="k-menu-link-text">${encode(messages.setColumnPosition)}</span></span><ul>\
${hasLockableColumns ? LOCKABLE_COLUMNS_PARTIAL_CLASSIC({ messages, hasStickableColumns }) : ''}\
${hasStickableColumns ? STICKABLE_COLUMNS_PARTIAL_CLASSIC({ messages }) : ''}\
${reorderable ? REORDERABLE_COLUMNS_PARTIAL_CLASSIC({ messages }) : ''}\
</ul></li>`;

    var template = ({ uid, sortable, filterable, showColumns, messages, columns, hasLockableColumns, hasStickableColumns, encodeTitles, ns, omitWrapAttribute, reorderable, groupable }) => `\
<ul id="${uid}">\
${sortable ? SORTABLE_PARTIAL_CLASSIC({ messages, showColumns, filterable }) : '' }\
${showColumns ? COLUMNS_PARTIAL_CLASSIC({ columns, messages, encodeTitles, ns, omitWrapAttribute, filterable, hasLockableColumns, hasStickableColumns }) : ''}\
${filterable ? FILTERABLE_PARTIAL_CLASSIC({ messages, hasLockableColumns, hasStickableColumns, reorderable }) : ''}\
${groupable ? GROUPABLE_PARTIAL_CLASSIC({ messages, hasLockStickMove: hasLockableColumns || hasStickableColumns || reorderable }) : ''}\
${hasLockableColumns || hasStickableColumns || reorderable ? LOCK_STICK_COLUMNS_PARTIAL_CLASSIC({ messages, hasLockableColumns, hasStickableColumns, reorderable }) : ''}
</ul>`;

    /* ------------------------- MOBILE TEMPLATE ------------------------- */

    function mobileColumnsTemplateIterator(columns, groups, ns, hasGroups) {
        var result = "";

        if (hasGroups) {
            for (var i = 0; i < groups.length; i++) {
                result += `<span class="k-list-group-header k-pb-1">${encode(groups[i].title)}</span><ul class="k-listgroup k-listgroup-flush k-mb-4">`;

                for (var idx = 0; idx < groups[i].columns.length; idx++) {
                    result += `<li id="${kendo.guid()}" class="k-item k-listgroup-item"><span class="k-listgroup-form-row"><span class="k-listgroup-form-field-label k-item-title">${groups[i].columns[idx].title}</span><span class="k-listgroup-form-field-wrapper"><input type="checkbox" title="${groups[i].columns[idx].title}" data-${ns}field="${groups[i].columns[idx].field.replace(/\"/g,"&#34;")}" data-${ns}index="${groups[i].columns[idx].index}" data-${ns}uid="${groups[i].columns[idx].uid}" data-${ns}locked="${groups[i].columns[idx].locked}"/></span></span></li>"`;
                }

                result += `</ul>`;
            }
        } else {
            result += `<ul class="k-listgroup k-listgroup-flush k-mb-4">`;

            for (var idx = 0; idx < columns.length; idx++) {
                result += `<li id="${kendo.guid()}" class="k-item k-listgroup-item"><span class="k-listgroup-form-row"><span class="k-listgroup-form-field-label k-item-title">${columns[idx].title}</span><span class="k-listgroup-form-field-wrapper"><input type="checkbox" title="${columns[idx].title}" data-${ns}field="${columns[idx].field.replace(/\"/g,"&#34;")}" data-${ns}index="${columns[idx].index}" data-${ns}uid="${columns[idx].uid}" data-${ns}locked="${columns[idx].locked}"/></span></span></li>`;
            }

            result += `</ul>`;
        }

        return result;
    }

    const SORTABLE_PARTIAL_MOBILE = ({ messages }) => `<li id="${kendo.guid()}" class="k-item k-listgroup-item k-sort-asc"><span class="k-link">${kendo.ui.icon("sort-asc-small")}<span class="k-item-title">${encode(messages.sortAscending)}</span></span></li>\
<li id="${kendo.guid()}" class="k-item k-listgroup-item k-sort-desc"><span class="k-link">${kendo.ui.icon("sort-desc-small")}<span class="k-item-title">${encode(messages.sortDescending)}</span></span></li>`;

    const LOCKABLE_COLUMNS_PARTIAL_MOBILE = ({ messages }) => `<li id="${kendo.guid()}" class="k-item k-listgroup-item k-lock"><span class="k-link">${kendo.ui.icon("lock")}<span class="k-item-title">${encode(messages.lock)}</span></span></li>\
<li id="${kendo.guid()}" class="k-item k-listgroup-item k-unlock"><span class="k-link">${kendo.ui.icon("unlock")}<span class="k-item-title">${encode(messages.unlock)}</span></span></li>`;

    const STICKABLE_COLUMNS_PARTIAL_MOBILE = ({ messages }) => `<li id="${kendo.guid()}" class="k-item k-listgroup-item k-stick"><span class="k-link">${kendo.ui.icon("stick")}<span class="k-item-title">${encode(messages.stick)}</span></span></li>\
<li id="${kendo.guid()}" class="k-item k-listgroup-item k-unstick"><span class="k-link">${kendo.ui.icon("unstick")}<span class="k-item-title">${encode(messages.unstick)}</span></span></li>`;

    const REORDERABLE_COLUMNS_PARTIAL_MOBILE = ({ messages }) => `<li id="${kendo.guid()}" class="k-item k-listgroup-item k-move-prev"><span class="k-link">${kendo.ui.icon(`caret-alt-${isRtl ? "right" : "left"}`)}<span class="k-item-title">${encode(messages.movePrev)}</span></span></li>\
<li id="${kendo.guid()}" class="k-item k-listgroup-item k-move-next"><span class="k-link">${kendo.ui.icon(`caret-alt-${isRtl ? "left" : "right"}`)}<span class="k-item-title">${encode(messages.moveNext)}</span></span></li>`;

    const FILTERABLE_PARTIAL_MOBILE = ({ messages }) => `<li id="${kendo.guid()}" class="k-item k-listgroup-item k-filter-item">\
<span class="k-link k-filterable">\
${kendo.ui.icon("filter")}\
<span class="k-item-title">${encode(messages.filter)}</span>\
<span class="k-select">${kendo.ui.icon("chevron-right")}</span>\
</span>\
</li>`;

    const GROPABLE_PARTIAL_MOBILE = ({ messages }) => `\
<li id="${kendo.guid()}" class="k-item k-listgroup-item k-group"><span class="k-link">${kendo.ui.icon("group")}<span class="k-item-title">${encode(messages.groupColumn)}</span></span></li>\
<li id="${kendo.guid()}" class="k-item k-listgroup-item k-ungroup"><span class="k-link">${kendo.ui.icon("ungroup")}<span class="k-item-title">${encode(messages.ungroupColumn)}</span></span></li>`;

    const COLUMNS_PARTIAL_MOBILE = ({ messages, hasGroups, columns, groups, ns }) => `\
<li class="k-columns-item"><span class="k-list-title">${messages.columnVisibility}</span>\
${mobileColumnsTemplateIterator(columns, groups, ns, hasGroups)}\
</li>`;

    var mobileTemplate = ({ messages, title, sortable, filterable, showColumns, hasLockableColumns, hasStickableColumns, hasGroups, columns, groups, ns, reorderable, groupable }) => `\
<div data-${ns}role="view" class="k-grid-column-menu">\
<div data-${ns}role="header" class="k-appbar k-appbar-primary">\
${kendo.html.renderButton(`<button class="k-header-cancel" title="${messages.cancel}" aria-label="${messages.cancel}"></button>`, { icon: "chevron-left", fillMode: "flat" })}\
<span class="k-spacer"></span>\
<span>${encode(messages.settings)}</span>\
<span class="k-spacer"></span>\
${kendo.html.renderButton(`<button class="k-header-done" title="${messages.done}" aria-label="${messages.done}"></button>`, { icon: "check", fillMode: "flat" })}\
</div>\
<div class="k-column-menu">\
<ul class="k-reset">\
<li>\
<span class="k-list-title">${encode(messages.column)}: ${title}</span>\
<ul class="k-listgroup k-listgroup-flush k-mb-4">\
${sortable ? SORTABLE_PARTIAL_MOBILE({ messages }) : ''}\
${hasLockableColumns ? LOCKABLE_COLUMNS_PARTIAL_MOBILE({ messages }) : ''}\
${hasStickableColumns ? STICKABLE_COLUMNS_PARTIAL_MOBILE({ messages }) : ''}\
${reorderable ? REORDERABLE_COLUMNS_PARTIAL_MOBILE({ messages }) : ''}\
${filterable ? FILTERABLE_PARTIAL_MOBILE({ messages }) : ''}\
${groupable ? GROPABLE_PARTIAL_MOBILE({ messages }) : ''}\
</ul>\
</li>\
${showColumns ? COLUMNS_PARTIAL_MOBILE({ messages, hasGroups, columns, groups, ns }) : ''}\
<li class="k-item k-clear-wrap">\
<ul class="k-listgroup k-listgroup-flush">\
<li class="k-listgroup-item">\
<span class="k-link k-label k-clear" title="${messages.clear}" aria-label="${messages.clear}">\
${encode(messages.clear)}\
</span></li></ul></li></ul></div></div>`;

    var MobileMenu = Widget.extend({
        init: function(element, options) {
            var that = this;

            Widget.fn.init.call(that, element, options);

            that._createCheckBoxes();

            that.element.on("click" + NS, "li.k-item:not(.k-separator):not(.k-disabled):not(:has(.k-switch))", "_click");
        },

        events: [ SELECT ],

        _click: function(e) {
            var that = this;

            if (!$(e.target).is("[type=checkbox]")) {
                e.preventDefault();
            }

            if ($(e.target).hasClass("k-clear")) {
                that._cancelChanges(true);

                return;
            }

            if ($(e.target).hasClass("k-filterable")) {
                that._cancelChanges(true);
                that.trigger(SELECT, { item: e.currentTarget });

                return;
            }

            that._updateSelectedItems(e.currentTarget);
        },

        _updateSelectedItems: function(el) {
            var that = this;
            var item = $(el);
            var state = that.options.columnMenu.view.state || { columns: {} };
            var id = item.prop("id");

            if (item.hasClass("k-filter-item")) {
                return;
            }

            if (state[id]) {
                state[id] = false;
            } else {
                state[id] = true;
            }

            if (item.hasClass("k-sort-asc") || item.hasClass("k-sort-desc")) {
                var dir;
                var otherItem;
                var otherItemId;

                if (item.hasClass("k-sort-asc")) {
                    dir = "asc";
                    otherItem = that.element.find(".k-sort-desc");
                } else {
                    dir = "desc";
                    otherItem = that.element.find(".k-sort-asc");
                }

                otherItemId = otherItem.prop("id");

                if (dir === state.initialSort && !item.hasClass("k-selected")) {
                    state[id] = false;
                }

                if (state[otherItemId]) {
                    state[otherItemId] = false;
                }

                otherItem.removeClass(ACTIVE);
            }

            if (item.hasClass(ACTIVE)) {
                item.removeClass(ACTIVE);
            } else {
                item.addClass(ACTIVE);
            }
        },

        _cancelChanges: function(force) {
            var that = this;
            var menu = that.options.columnMenu;
            var view = menu.view;
            var state = view.state || { columns: {} };
            var columns = state.columns;

            that.element.find("." + ACTIVE).removeClass(ACTIVE);
            menu.refresh();

            if (force) {
                var selectedItems = [];

                for (var key in columns) {
                    if (columns.hasOwnProperty(key)) {
                        if (columns[key] === true) {
                            var item = view.element.find("#" + key);

                            selectedItems.push(item[0]);
                        }
                    }
                }
                // In order to use the columns hide/show validation,
                // triggering the Select event must be done backwards
                for (var i = selectedItems.length - 1; i >= 0; i--) {
                    that.trigger(SELECT, { item: selectedItems[i] });
                }

                if (menu.options.hasLockableColumns) {
                    menu._updateLockedColumns();
                }

                if (menu.options.hasStickableColumns) {
                    menu._updateStickyColumns();
                }

                if (menu.options.reorderable) {
                    menu._updateReorderColumns();
                }

                if (menu.options.groupable) {
                    menu._updateGroupColumns();
                }
            }

            that.options.columnMenu.view.state = { columns: {} };
        },

        _applyChanges: function() {
            var that = this;
            var view = that.options.columnMenu.view;
            var state = view.state || { columns: {} };

            for (var key in state) {
                if (state.hasOwnProperty(key)) {
                    if (key !== "initialSort" && key !== "columns" && state[key] === true) {
                        var item = view.element.find("#" + key);

                        if (item.hasClass(ACTIVE)) {
                            item.removeClass(ACTIVE);
                        } else {
                            item.addClass(ACTIVE);
                        }

                        that.trigger(SELECT, { item: item[0] });
                    }
                }
            }
        },

        _createCheckBoxes: function() {
            var that = this;

            that.element.find(".k-columns-item").find("[type='checkbox']").kendoSwitch({
                messages: {
                    checked: "",
                    unchecked: ""
                },
                change: function(e) {
                    var item = e.sender.element.closest(".k-item");
                    var state = that.options.columnMenu.view.state || { columns: {} };
                    var id = item.prop("id");

                    if (state.columns[id]) {
                        state.columns[id] = false;
                    } else {
                        state.columns[id] = true;
                    }

                    that.trigger(SELECT, { item: item });
                }
            });
        },

        _destroyCheckBoxes: function() {
            var that = this;
            var elements = that.element.find(".k-columns-item").find("[type='checkbox']");
            var switchWidget;

            for (var i = 0; i < elements.length; i++) {
                switchWidget = elements.eq(i).data("kendoSwitch");

                if (switchWidget) {
                    switchWidget.destroy();
                }
            }
        },

        close: function() {
            this.options.pane.navigate("");
        },

        destroy: function() {
            var that = this;

            Widget.fn.destroy.call(that);

            that.element.off(NS);
            that._destroyCheckBoxes();
        }
    });

    ui.plugin(ColumnMenu);
})(window.kendo.jQuery);
export default kendo;

