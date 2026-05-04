/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.mobile.scroller.js";
import "./kendo.toolbar.js";
import "./kendo.pager.js";
import "./kendo.combobox.js";
import "./kendo.textbox.js";
import "./pdfviewer/processors/dpl-processor.js";
import { isLoaded as isPdfJSLoaded } from "./pdfviewer/pdfjs.js";
import "./pdfviewer/page.js";
import "./pdfviewer/commands.js";
import "./pdfviewer/dialogs.js";
import 'pdfjs-dist/build/pdf.worker.mjs';
import { DEFAULT_ZOOM_LEVEL as ZOOM_SCALE, Scroller, currentPage as getCurrentPage, scrollToPage, PdfViewer as PdfViewerWidget } from '@progress/kendo-pdfviewer-common';
import { setInteractionMode, resetAnnotationEditorMode } from "./pdfviewer/annotations/annotation-manager.js";
import { HighlightAnnotationPopup, FreeTextAnnotationPopup } from "./pdfviewer/annotations/annotation-popup.js";

export const __meta__ = {
    id: "pdfviewer",
    name: "PDFViewer",
    category: "web",
    description: "PDFViewer to display pdfs in the browser",
    depends: ["core", "window", "dialog", "toolbar", "draganddrop", "upload", "combobox", "drawing", "binder", "dropdownlist", "numerictextbox", "textbox", "pager", "form", "colorpicker"]
};

(function($, undefined) {
    var NS = ".kendoPDFViewer",
        kendo = window.kendo,
        ui = kendo.ui,
        extend = $.extend,
        drawing = kendo.drawing,
        keys = $.extend({ PLUS: 187, MINUS: 189, ZERO: 48, NUMPAD_ZERO: 96 }, kendo.keys),
        Page,
        BlankPage = kendo.pdfviewer.BlankPage,
        Widget = ui.Widget,
        progress = kendo.ui.progress,
        SCROLL = "scroll",
        RENDER = "render",
        OPEN = "open",
        ERROR = "error",
        KEYDOWN = "keydown" + NS,
        MOUSEWHEEL = "DOMMouseScroll" + NS + " mousewheel" + NS,
        UPDATE = "update",
        PAGE_CHANGE = "pagechange",
        ZOOMSTART = "zoomStart",
        ZOOMEND = "zoomEnd",
        ZOOMCOMMAND = "ZoomCommand",
        WHITECOLOR = "#ffffff",
        TABINDEX = "tabindex",
        CLICK = "click",
        CHANGE = "change",
        TOGGLE = "toggle",
        DISABLED = 'k-disabled',
        PROCESSORS = {
            pdfjs: "pdfjs",
            dpl: "dpl"
        },
        styles = {
            viewer: "k-pdf-viewer",
            scroller: "k-canvas k-pdf-viewer-canvas k-pos-relative k-overflow-auto",
            enableTextSelection: "k-enable-text-select",
            enablePanning: "k-enable-panning",
            highlightClass: "k-search-highlight",
            highlightMarkClass: "k-search-highlight-mark",
            charClass: "k-text-char"
        },
        PREDEFINED_ZOOM_VALUES = {
            auto: "auto",
            actual: "ActualWidth",
            fitToWidth: "FitToWidth",
            fitToPage: "FitToPage"
        };

    var PDFViewer = Widget.extend({
        init: function(element, options) {
            var that = this;

            Widget.fn.init.call(that, element, kendo.deepExtend({}, this.options, options));

            that._processMessages();

            that._wrapper();

            if (that.options.toolbar) {
                that._renderToolbar();
            }

            that._initProcessor(options || {});
            that._renderPageContainer();

            if (that._isDPLProcessor()) {
                that._loadDPLDocument();
            } else {
                that._loadPdfJSDocument();
            }

            kendo.notify(that, kendo.ui);

            if (that._showWatermarkOverlay) {
                that._showWatermarkOverlay(that.wrapper[0]);
            }
        },

        events: [
            RENDER,
            OPEN,
            ERROR,
            ZOOMSTART,
            ZOOMEND
        ],

        options: {
            name: "PDFViewer",
            view: {
                type: "canvas"
            },
            pdfjsProcessing: {
                file: null,
                renderForms: false,
                loadOnDemand: true
            },
            dplProcessing: {
                read: {
                    url: null,
                    type: "GET",
                    dataType: "json",
                    pageField: "pageNumber"
                },
                upload: {
                    url: null,
                    saveField: "file"
                },
                download: {
                    url: null
                },
                loadOnDemand: false
            },
            toolbar: {
                items: [],
                contextMenu: false
            },
            width: 1000,
            height: 1200,
            page: 1,
            defaultPageSize: {
                width: 794,
                height: 1123
            },
            scale: null,
            zoomMin: 0.5,
            zoomMax: 4,
            zoomRate: 0.25,
            messages: {
                defaultFileName: "Document",
                toolbar: {
                    zoom: {
                        zoomLevel: "zoom level",
                        zoomOut: "Zoom Out",
                        zoomIn: "Zoom In",
                        actualWidth: "Actual Width",
                        autoWidth: "Automatic Width",
                        fitToWidth: "Fit to Width",
                        fitToPage: "Fit to Page"
                    },
                    contextMenu: "Menu",
                    open: "Open",
                    exportAs: "Export",
                    download: "Download",
                    print: "Print",
                    toggleSelection: "Enable Selection",
                    togglePan: "Enable Panning",
                    search: "Search",
                    annotations: "Annotations"
                },
                errorMessages: {
                    notSupported: "Only pdf files allowed.",
                    parseError: "PDF file fails to process.",
                    notFound: "File is not found.",
                    popupBlocked: "Popup is blocked."
                },
                dialogs: {
                    exportAsDialog: {
                        title: "Export...",
                        defaultFileName: "Document",
                        pdf: "Portable Document Format (.pdf)",
                        png: "Portable Network Graphics (.png)",
                        svg: "Scalable Vector Graphics (.svg)",
                        labels: {
                            fileName: "File name",
                            saveAsType: "Save as",
                            page: "Page"
                        }
                    },
                    okText: "OK",
                    save: "Save",
                    cancel: "Cancel",
                    search: {
                        inputLabel: "Search Text",
                        matchCase: "Match Case",
                        next: "Next Match",
                        previous: "Previous Match",
                        close: "Close",
                        of: "of",
                        dragHandle: "Drag search"
                    }
                }
            }
        },

        defaultTools: {
            contextMenu: {
                type: "dropDownButton",
                name: "contextMenu",
                showText: "overflow",
                id: "pdfviewer-toolbar-context-menu",
                overflow: "never",
                icon: "menu",
                fillMode: "flat",
                menuButtons: [
                    { id: "open", text: "Open", icon: "folder-open", attributes: { "data-command": "OpenCommand" } },
                    { id: "download", text: "Download", icon: "download", attributes: { "data-command": "DownloadCommand" }, enable: false },
                    { id: "print", text: "Print", icon: "print", attributes: { "data-command": "PrintCommand" }, enable: false }
                ]
            },
            separator: { type: "separator" },
            pager: {
                type: "component",
                name: "pager",
                overflow: "never",
                component: "Pager",
                element: '<div></div>',
                componentOptions: {
                    navigatable: true,
                    _isToolbarItem: true,
                }
            },
            spacer: { type: "spacer" },
            zoomInOut: {
                type: "buttonGroup",
                fillMode: "flat",
                buttons: [
                    { type: "button", icon: "zoom-out", name: "zoomOut", command: "ZoomCommand", showText: "overflow", options: "{ \"zoomOut\": true, \"updateComboBox\": true }", fillMode: "flat" },
                    { type: "button", icon: "zoom-in", name: "zoomIn", command: "ZoomCommand", showText: "overflow", options: "{ \"zoomIn\": true, \"updateComboBox\": true }", fillMode: "flat" },
                ]
            },
            zoom: {
                type: "component",
                name: "zoom",
                command: "ZoomCommand",
                overflow: "never",
                component: "ComboBox",
                data: [50, 100, 150, 200, 300, 400],
                componentOptions: {
                    enable: false,
                    dataTextField: "text",
                    dataValueField: "percent",
                    valuePrimitive: true,
                    clearOnEscape: false,
                    commandOn: "change",
                    fillMode: 'flat',
                }
            },
            toggleSelection: {
                type: "buttonGroup",
                fillMode: "flat",
                buttons: [
                    {
                        togglable: true,
                        command: "EnableSelectionCommand",
                        icon: "pointer",
                        showText: "overflow",
                        name: "toggleSelection",
                        group: "toggle-pan",
                        fillMode: "flat"
                    }, {
                        togglable: true,
                        command: "EnablePanCommand",
                        icon: "hand",
                        showText: "overflow",
                        name: "togglePan",
                        group: "toggle-pan",
                        selected: true,
                        fillMode: "flat"
                    }
                ]
            },
            spacer2: { type: "spacer" },
            search: {
                type: "button",
                command: "OpenSearchCommand",
                icon: "search",
                name: "search",
                showText: "overflow",
                enable: false,
                fillMode: "flat"
            },
            open: {
                type: "button",
                showText: "overflow",
                name: "open",
                icon: "folder-open",
                command: "OpenCommand",
                fillMode: "flat"
            },
            download: {
                type: "button",
                showText: "overflow",
                name: "download",
                icon: "download",
                command: "DownloadCommand",
                enable: false,
                fillMode: "flat"
            },
            print: {
                type: "button",
                showText: "overflow",
                name: "print",
                icon: "print",
                command: "PrintCommand",
                enable: false,
                fillMode: "flat"
            },
            annotations: {
                togglable: true,
                type: "button",
                showText: "overflow",
                name: "annotations",
                icon: "edit-annotations",
                command: "AnnotationsCommand",
                enable: false,
                fillMode: "flat"
            }
        },

        exportAsTool: {
            exportAs: { type: "button", showText: "overflow", name: "exportAs", icon: "image-export", command: "ExportCommand", fillMode: "flat" }
        },


        _processMessages: function() {
            var messages = this.options.messages.toolbar,
                zoom = messages.zoom;

            if ($.isPlainObject(zoom)) {
                this.options.messages.toolbar = $.extend({}, this.options.messages.toolbar, zoom);
                this.options.messages.toolbar.zoom = zoom.zoomLevel || this.options.messages.toolbar.zoom;
            }
        },

        _wrapper: function() {
            var that = this,
                options = that.options;

            that.wrapper = that.element;

            that.wrapper
                    .width(options.width)
                    .height(options.height)
                    .addClass(styles.viewer)
                    .on(KEYDOWN, that._keydown.bind(that));

            that._allowResize = that.options.scale === null;
            that._autoZoomScale = ZOOM_SCALE;
            that.zoomScale = that.options.scale || that._autoZoomScale;

            that._resizeHandler = kendo.onResize(function() {
                that.resize();
            });

            that._pageNum = that.options.page;
        },

        _keydown: function(e) {
            var plusShortcuts = [keys.PLUS, keys.NUMPAD_PLUS],
                minusShortcuts = [keys.MINUS, keys.NUMPAD_MINUS],
                zeroShortcuts = [keys.ZERO, keys.NUMPAD_ZERO],
                shouldExecute = false,
                args = {
                    command: ZOOMCOMMAND,
                    options: { updateComboBox: true }
                };

            if (!e.ctrlKey || this._blankPage || this.processingLib === PROCESSORS.dpl) {
                return;
            }

            if (plusShortcuts.includes(e.keyCode)) {
                args.options.zoomIn = true;
                shouldExecute = true;
            } else if (minusShortcuts.includes(e.keyCode)) {
                args.options.zoomOut = true;
                shouldExecute = true;
            } else if (zeroShortcuts.includes(e.keyCode)) {
                args.options.value = ZOOM_SCALE;
                shouldExecute = true;
            }

            if (shouldExecute) {
                this.execute(args);
                e.preventDefault();
            }
        },

        _handlePageChangeEvent: function(event) {
            const that = this;

            if (!that._pageChangeFromScroll) {
                this.activatePage(event.index, false);
                that._showPagerInputLabels();
            }

            delete that._pageChangeFromScroll;
        },

        _showPagerInputLabels: function() {
            const that = this;
            const inputElements = that.pager.element.find(".k-pager-input").children();
            if (inputElements) {
                const labels = inputElements.eq(0).add(inputElements.eq(2));

                if (!$(labels).is(":visible")) {
                    labels.show();
                }
            }
        },

        _resizePager: function() {
            const that = this;

            if (!that.pager || !that.pager.options.responsive) {
                return;
            }

            const pagerWidth = kendo._outerWidth(that.pager.element);
            const visibleToolbarItems = Array.from(that.toolbar.element.children(':not(.k-hidden):not(:has(.k-pager))'));
            const containerWidth = kendo._outerWidth(that.element);

            let visibleToolsWidth = 0;

            const pattern = /(em|ex|%|px|cm|mm|in|pt|pc|ch|rem|vh|vw|vmin|vmax)$/;
            const gap = Number(that.toolbar.element.css("gap").replace(pattern,''));

            if (visibleToolbarItems.length > 0) {
                let temp = 0;
                for (let i = 0; i < visibleToolbarItems.length; i++) {
                    temp += kendo._outerWidth(visibleToolbarItems[i]) + gap;
                }
                if (temp) {
                    visibleToolsWidth = temp;
                }
            }

            const elementsToShrink = that.pager.element.find(".k-pager-nav");

            if ((pagerWidth + visibleToolsWidth + gap) > containerWidth) {

                for (var i = elementsToShrink.length - 1; i >= 0; i--) {
                    const element = elementsToShrink.eq(i);
                        element.addClass("k-hidden");
                }
            }

            if ((pagerWidth + visibleToolsWidth + gap) < containerWidth) {
                const hidden = that.pager.element.find(".k-hidden:not(.k-input-validation-icon)");

                for (var i = 0; i < hidden.length; i++) {
                    const hiddenElement = hidden.eq(i);

                    hiddenElement.removeClass('k-hidden');
                }
            }

            if (that.pager.options.input) {
                that._showPagerInputLabels();
            }
        },

        _initProcessor: function(options) {
            var that = this,
                processingOptions;

            processingOptions = options.dplProcessing ? that.options.dplProcessing : that.options.pdfjsProcessing;
            that.processingLib = options.dplProcessing ? PROCESSORS.dpl : PROCESSORS.pdfjs;

            if (that._isDPLProcessor()) {
                that.processor = new kendo.pdfviewer[that.processingLib].processor(processingOptions, that);
                Page = kendo.pdfviewer[that.processingLib].Page;
            } else {
                isPdfJSLoaded();
            }
        },

        _isDPLProcessor: function() {
            return this.processingLib === PROCESSORS.dpl;
        },

        _hasPagerTool: function(tools) {
            let hasPager = false;
            let index;

            for (let i = 0; i < tools.length; i++) {
                const tool = tools[i];
                if (typeof tool === 'string') {
                    hasPager = tool.toLowerCase() === 'pager';
                    index = i;
                } else {
                    if (tool.type) {
                        hasPager = tool.type.toLowerCase() === 'pager';
                        index = i;
                    } else if (tool.name) {
                        hasPager = tool.name.toLowerCase() === 'pager';
                        if (hasPager) {
                            delete tool.name;
                        }
                        index = i;
                    }
                }

                if (hasPager) {
                    break;
                }
            }

            return {
                hasPager,
                index
            };
        },

        _renderToolbar: function() {
            var that = this,
                options = that.options,
                toolbarOptions = extend({}, options.toolbar),
                tools = toolbarOptions.items && toolbarOptions.items.length ? toolbarOptions.items : Object.keys(that.defaultTools);

            if (that.options.pdfjsProcessing.renderForms && !toolbarOptions.items.length) {
                const [enableSelection, enablePan] = [...that.defaultTools["toggleSelection"].buttons];

                enableSelection.selected = true;
                enablePan.selected = false;
            }

            const { hasPager, index } = that._hasPagerTool(tools);


            tools = that._processTools(tools);

            if (hasPager) {
                let pagerMessages = that.options.messages.toolbar.pager;

                if (pagerMessages) {
                    if (pagerMessages.of) {
                        pagerMessages.of = `${pagerMessages.of} {0}`;
                        if (pagerMessages.pages) {
                            pagerMessages.of = pagerMessages.of + ' ' + pagerMessages.pages;
                            delete pagerMessages.pages;
                        }
                    }
                }
                that.defaultTools.pager.componentOptions.messages = pagerMessages;
                options.messages.toolbar.pager = "Pager";

                const currentPagerOptions = that.defaultTools.pager.componentOptions;
                if (typeof tools[index] !== 'string') {
                    that.defaultTools.pager.componentOptions = $.extend(tools[index], currentPagerOptions);

                    tools[index] = that.defaultTools.pager;
                }
            }

            toolbarOptions = {
                defaultTools: $.extend({}, that.defaultTools, that.exportAsTool),
                parentMessages: options.messages.toolbar,
                tools: tools,
                resizable: true,
                fillMode: 'flat',
                overflow: toolbarOptions.overflow
            };

            var toolbarElement = $("<div />");

            toolbarElement.appendTo(that.element);
            that.toolbar = new kendo.ui.ToolBar(toolbarElement, toolbarOptions);
            that.options.toolbar = that.toolbar.options;

            that.toolbar.bind(TOGGLE, that._toolbarClick.bind(that));
            that.toolbar.bind(CLICK, that._toolbarClick.bind(that));
            that.toolbar.bind(CHANGE, that._toolbarClick.bind(that));

            // If the context menu is enabled, bind the click event to the items of the dropdown button as well.
            if (toolbarOptions.contextMenu) {
                that.toolbar.element.find('[data-role=dropdownbutton]').on(CLICK, that._toolbarClick.bind(that));
            }

            if (hasPager) {
                that.pager = that.toolbar.element.find('.k-pager');
                if (that.pager.length > 0) {
                    that.pager = that.pager.data('kendoPager');
                }
                that.pager.bind(CHANGE, that._handlePageChangeEvent.bind(that));
            }

            that.bind({
                update: that._updateToolbar.bind(that)
            });

            return that.toolbar;
        },

        _processTools: function(tools) {
            var that = this,
                toolbar = that.options.toolbar,
                messages = that.options.messages.toolbar;

            tools = tools.flatMap(t => {
                if (t === "zoom") {
                    t = that.defaultTools.zoom;
                }

                if (t.name === "zoom") {
                    t = $.extend({}, that.defaultTools.zoom, t);

                    var zoomLevels = [{
                        percent: PREDEFINED_ZOOM_VALUES.auto,
                        text: messages.autoWidth
                    }, {
                        percent: PREDEFINED_ZOOM_VALUES.actual,
                        text: messages.actualWidth
                    }, {
                        percent: PREDEFINED_ZOOM_VALUES.fitToWidth,
                        text: messages.fitToWidth
                    }, {
                        percent: PREDEFINED_ZOOM_VALUES.fitToPage,
                        text: messages.fitToPage
                    }];

                    // eslint-disable-next-line
                    var comboOptions = t.data.map(i => { return { percent: i, text: i + "%" } });
                    var value = that.options.scale ? that.options.scale * 100 + "%" : "auto";

                    zoomLevels = zoomLevels.concat(comboOptions);
                    t.componentOptions.dataSource = zoomLevels;
                    t.componentOptions.value = value;
                }

                return t;
            });

            if (!toolbar.contextMenu) {
                // If the option is not enabled we don't want to render the context menu button and the separator.
                const contextMenuIndex = tools.findIndex(t => t === "contextMenu");
                if (contextMenuIndex !== -1) {
                    tools.splice(contextMenuIndex, 1);
                }

                const separatorIndex = tools.findIndex(t => t === "separator");
                if (separatorIndex !== -1) {
                    tools.splice(separatorIndex, 1);
                }
            } else {
                // If it is enabled, we don't want the open, download and print buttons to be rendered.
                tools = tools.filter(t => t !== "open" && t !== "download" && t !== "print");
            }

            return tools;
        },

        _updateToolbar: function(e) {
            var pageOptions = {
                    page: e.page || 1,
                    total: e.total || 1
                },
                toolbar = this.toolbar,
                toolbarEl = toolbar.element,
                zoomCombo = toolbarEl.find("[data-command=ZoomCommand][data-role=combobox]").data("kendoComboBox"),
                toFocus = toolbarEl.find(".k-focus");

            if (toFocus.length === 0) {
                toFocus = toolbarEl.find("[tabindex=0]").first();

                if (toFocus.length === 0) {
                    toFocus = toolbar._getAllItems().first();
                }
            }

            if (zoomCombo) {
                zoomCombo.enable(!e.isBlank);
                if (e.action === "zoom") {
                    this._updateZoomComboBox(e.zoom);
                }
            }

            if (((e.action === "pagechange" && e.updatePager !== false) || e.isBlank) && this.pager) {
                if (e.updatePager) {
                    pageOptions.updatePager = true;
                }

                if (e.pageChangeFromScroll) {
                    pageOptions.pageChangeFromScroll = true;
                }

                this._updatePager(pageOptions);
            }

            this._updateOnBlank(e.isBlank);

            toolbar._resetTabIndex(toFocus);
        },

        _updateOnBlank: function(isBlank) {
            const toolbar = this.toolbar,
                toolbarEl = toolbar.element;

            const contextMenu = $("#pdfviewer-toolbar-context-menu_buttonmenu");
            let downloadButton = toolbarEl.find("[data-command='DownloadCommand']");
            let printButton = toolbarEl.find("[data-command='PrintCommand']");

            if (contextMenu.length) {
                downloadButton = contextMenu.find("[data-command='DownloadCommand']");
                printButton = contextMenu.find("[data-command='PrintCommand']");
            }

            toolbar.enable(toolbarEl.find("[data-command=EnableSelectionCommand]").parent(), !isBlank);
            toolbar.enable(toolbarEl.find("[data-command=ZoomCommand][role=button]").parent(), !isBlank);

            toolbar.enable(toolbarEl.find("[data-command='OpenSearchCommand']"), !isBlank);
            toolbar.enable(downloadButton, !isBlank);
            toolbar.enable(printButton, !isBlank);
        },

        _updatePager: function(options) {
            const that = this;
            if (options.updatePager) {
                const isDPLProcessed = that.processingLib === 'dpl';

                const data = isDPLProcessed ? (that.document && that.document.pages ? that.document.pages : []) : that.pages ?? [];

                const pagerDataSource = new kendo.data.DataSource({
                data: data ?? [],
                pageSize: 1,
                page: options.page
            });
                that.pager.setDataSource(pagerDataSource);
                that._resizePager();
            } else {
                const current = that.pager.page();

                if (current !== options.page) {
                    if (options.pageChangeFromScroll) {
                        that._pageChangeFromScroll = true;
                    }
                    that.pager.page(options.page);
                }
            }
            that._showPagerInputLabels();
            that._togglePagerDisabledClass();
        },

        _togglePagerDisabledClass: function() {
            const that = this;
            const pager = that.pager;

            if (pager.totalPages() <= 1 ) {
                pager.element.addClass(DISABLED);
            } else if (pager.element.hasClass(DISABLED)) {
                pager.element.removeClass(DISABLED);
            }
        },

        _updateZoomComboBox: function(value) {
            var isPredefined = value === PREDEFINED_ZOOM_VALUES.auto ||
                value === PREDEFINED_ZOOM_VALUES.actual ||
                value === PREDEFINED_ZOOM_VALUES.fitToPage ||
                value === PREDEFINED_ZOOM_VALUES.fitToWidth,
                zoomCombo = this.toolbar.element.find("[data-command=ZoomCommand][data-role=combobox]").data("kendoComboBox");

            if (!isPredefined) {
                value = Math.round(value * 100) + '%';
            }

            if (zoomCombo) {
                zoomCombo.value(value);
            }
        },

        _toolbarClick: function(ev) {
            let target = $(ev.target),
                command = target.data("command"),
                options = target.data("options");

            if (!command && !options) {
                target = $(ev.currentTarget);
                command = target.data("command");
                options = target.data("options");
            }

            options = extend({}, { value: target.val() }, options);

            if (!command) {
                return;
            }

            this.execute({
                command: command,
                options: options
            });
        },

        _initErrorDialog: function(options) {
            var that = this;

            if (!that._errorDialog) {
                options = extend(options, {
                    messages: that.options.messages
                });
                var dialogInstance = new kendo.pdfviewer.dialogs.ErrorDialog(options);
                that._errorDialog = dialogInstance._dialog;
            }
            return that._errorDialog;
        },

        _renderPageContainer: function() {
            var that = this;

            if (!that.pageContainer) {
                that.pageContainerWrapper = $("<div />");
                that.pageContainerWrapper.addClass(styles.scroller);

                that.pageContainer = $(`<div class="k-pdf-viewer-pages" />`);
                that.pageContainer.attr(TABINDEX, 0);

                that.pageContainerWrapper.append(that.pageContainer);
                that.wrapper.append(that.pageContainerWrapper);
            }
        },

        _triggerError: function(options) {
            var dialog = this._initErrorDialog();
            extend(options, {
                dialog: dialog
            });
            if (this.pageContainer) {
                progress(this.pageContainer, false);
            }

            if (this.trigger(ERROR, options))
            {
                return;
            }

            dialog.open().content(options.message);
        },

        _renderPages: function() {
            var that = this,
                document = that.document,
                pagesData;

            that.pages = [];

            if (!document || !document.total) {
                that._renderBlankPage();
                return;
            }

            pagesData = document.pages;

            for (var i = 1; i <= document.total; i++) {
                var viewerPage,
                    pageData = {
                        processor: that.processor,
                        number: i
                    };

                if (pagesData && pagesData.length) {
                    pageData = extend(pageData, pagesData[i - 1]);
                }

                viewerPage = new Page(pageData, that);
                that.pages.push(viewerPage);
                that.pageContainer.append(viewerPage.element);
            }

            if (that.pdfScroller) {
                that.pdfScroller.enablePanEventsTracking();
            }

            that._attachContainerEvents();
            that._getVisiblePagesCount();
            that._updatePager({ updatePager: true });
        },

        _renderBlankPage: function() {
            this._blankPage = new BlankPage(this.options.defaultPageSize, this);

            this.pageContainer.append(this._blankPage.element);

            this._blankPage._initUpload();
            this.trigger(UPDATE, { isBlank: true });
        },

        _removeBlankPage: function() {
            if (this._blankPage) {
                this._blankPage.destroy();
                this._blankPage.element.remove();
                this._blankPage = null;
            }
        },

        _resize: function() {
            var that = this,
                containerWidth,
                ratio;

            if (!that._allowResize) {
                return;
            }

            if (!that.pages || !that.pages.length) {
                if (that._blankPage) {
                    ratio = containerWidth / that._blankPage.element.width();
                    that._blankPage.resize(ratio);
                }
                return;
            }

            if (that.toolbar) {
                that.toolbar.resize(true);
            }


            if (that._resizeHandler) {
                clearTimeout(that._resizeHandler);
            }
            that._resizeHandler = setTimeout(that._resizePages.bind(that), 100);
        },

        _resizePages: function() {
            const that = this,
                containerWidth = that.pageContainer[0].clientWidth,
                pagesElements = that.pdfViewerCommon?.getPagesElements();
            let ratio = 0;

            that.pages.forEach(function(page) {
                const pageWidth = page.element ? page.element.width() : $(pagesElements[page._pageIndex]).width();
                const currentRatio = containerWidth / pageWidth;

                if (currentRatio > ratio) {
                    ratio = currentRatio;
                }
            });


            if (that._autoFit) {
                that.zoom(that._autoFit, true);
                return;
            }

            ratio = Math.min(Math.max(ratio, that.options.zoomMin), ZOOM_SCALE);
            if (ratio != that.zoomScale) {
                that.zoom(ratio, true);
                that.zoomScale = ratio;
                that._allowResize = true;
            }

            if (that.pager && that.pager.element) {
                that._resizePager();
            }
        },

        _attachContainerEvents: function() {
            const that = this;

            that.pageContainer.addClass(styles.enablePanning);
            that.pageContainerWrapper.bind(SCROLL, that._scroll.bind(that));
        },

        _scroll: function(e) {
            var that = this,
                containerHeight = that.pageContainerWrapper.height(),
                total = that.pages.length,
                pageNum = that._pageNum,
                pageIndex = pageNum - 1,
                pageToLoadNum = pageNum,
                pageToLoad,
                currentPage;

                if (that._preventScroll || !total) {
                    that._preventScroll = false;
                    return;
                }

                that._scrollingStarted = true;
                const nextPageIndex = getCurrentPage(that.element[0]);
                currentPage = that.pages[pageIndex];
                pageToLoadNum = pageNum + nextPageIndex - pageIndex;

                if (pageNum !== pageToLoadNum && pageToLoadNum >= 1 && pageToLoadNum <= total) {
                    pageToLoad = that.pages[pageToLoadNum - 1].element;

                    if (pageToLoad.offset().top > containerHeight) {
                        return;
                    }

                    that._pageNum = pageToLoadNum;
                    that._loadVisiblePages();

                    that.trigger(UPDATE, { action: PAGE_CHANGE, page: pageToLoadNum, total: total, pageChangeFromScroll: true });
                }
        },

        zoom: function(scale, preventComboBoxChange) {
            var that = this;

            if (that._isDPLProcessor()) {
                return;
            }

            if (!scale) {
                return that.zoomScale;
            }

            return that.execute({
                command: ZOOMCOMMAND,
                options: {
                    value: scale,
                    updateComboBox: !preventComboBoxChange
                }
            });
        },

        execute: function(options) {
            var commandOptions = extend({ viewer: this }, options.options);
            var command = new kendo.pdfviewer[options.command](commandOptions);
            return command.exec();
        },

        _loadDPLDocument: function() {
            var that = this;
            var page = that.options.page;

            progress(that.pageContainer, true);
            that.processor.fetchDocument().done(function(document) {
                that._clearPages();
                that.document = document;

                that._renderPages();
                that.resize(true);
                if (document) {
                    page = page >= 1 && page <= document.total ? page : 1;
                    that.activatePage(page, true);
                }

                if (that.pdfScroller) {
                    that.pdfScroller.destroy();
                }

                that.pdfScroller = new Scroller(that.pageContainer[0].parentNode, {
                    filter: '.k-page',
                    events: {}
                });

                that.pdfScroller.enablePanEventsTracking();

                progress(that.pageContainer, false);
            });
        },

        _loadPdfJSDocument: function(data) {
            const that = this;
            const options = that.options;
            const page = options.page;
            let file;

            if (data) {
                file = data;
            } else {
                file = options.pdfjsProcessing.file;
            }

            if (!file) {
                that._renderBlankPage();
                return;
            }

            if (typeof file === "string") {
                file = {
                    url: file
                };
            } else if (file instanceof ArrayBuffer) {
                file = {
                    data: file
                };
            }

            that._removeBlankPage();

            progress(that.pageContainer, true);
            that.pdfViewerCommon = new PdfViewerWidget(that.element[0], {
                loadOnDemand: that.options.pdfjsProcessing.loadOnDemand,
                loadOnDemandPageSize: 1,
                zoomLevel: that.zoomScale,
                renderForms: that.options.pdfjsProcessing.renderForms,
                fileDescriptor: file,
                events: {
                    pagesLoad: (e) => {
                        that.document = e.pdfDoc;
                        that.pages = e.pdfPages;

                        if (that.options.pdfjsProcessing.renderForms) {
                            // Enable text selection by default if form fields are to be rendered.
                            setInteractionMode(that.pdfViewerCommon, 0);
                        } else {
                            // Enable panning by default if form fields are not rendered.
                            setInteractionMode(that.pdfViewerCommon, 1);
                        }

                        that.activatePage(page, true);
                        that.trigger(UPDATE);

                        if (that._currentPage > -1) {
                            that.pdfViewerCommon.scrollToPage({ pageNumber: that._currentPage });
                            // Reset the internal current page property.
                            that._currentPage = null;
                        }

                        progress(that.pageContainer, false);
                    },
                    pageRendered: (e) => {
                        if (!that._preventRenderEvent) {
                            that.trigger(RENDER, { page: e.page.pdfPage });
                        }

                        that._setPageNumberAttributes();

                        that._preventRenderEvent = false;
                    },
                    zoomStart: (e) => {
                        that._currentPage = that.pdfViewerCommon.getCurrentPageIndex() + 1;
                    },
                    zoomEnd: (e) => {
                        that.zoomScale = e.zoomLevel;
                        that._updateZoomComboBox(e.zoomLevel);
                        that.trigger(ZOOMEND, { scale: e.zoomLevel });
                    },
                    printEnd: (e) => {
                        progress(that.pageContainerWrapper, false);
                    },
                    scroll: (e) => {
                        if (e.isPageChanged) {
                            that.trigger(UPDATE, { action: PAGE_CHANGE, page: e.pageNumber, total: that.pages.length, pageChangeFromScroll: true });
                        }
                    },
                    error: (e) => {
                        that._triggerError({
                            error: e.message,
                            message: that.options.messages.errorMessages.parseError
                        });
                    },
                    annotationEditorToolBarShow: that.onAnnotationEditorToolBarShow.bind(that),
                }
            });
        },

        _enablePanning: function() {
            const that = this;
            that.pdfViewerCommon.documentScroller.enablePanEventsTracking();
        },

        _disablePanning: function() {
            const that = this;
            that.pdfViewerCommon.documentScroller.disablePanEventsTracking();
        },

        _setPageNumberAttributes: function() {
            const that = this;

            that.pageContainer.find('.k-page').each((i, el) => {
                $(el).attr(kendo.attr("number"), i + 1);
            });
        },

        loadPage: function(number) {
            const that = this,
                page = that.pages && that.pages[number - 1];

            if (!page) {
                return;
            }

            if (that._isDPLProcessor()) {
                return page.load(that.zoomScale);
            }

            return Promise.resolve();
        },

        activatePage: function(number, updatePager) {
            const that = this,
                page = that.pages && that.pages[number - 1];

            if (!page) {
                return;
            }

            that._pageNum = number;

            if (that._isDPLProcessor()) {
                that._loadVisiblePages();
                that._scrollToActivatedPage(number, updatePager);
            } else {
                that.pdfViewerCommon.activatePageNumber(number);
                that.trigger(UPDATE, { action: PAGE_CHANGE, page: number, total: that.pages.length, updatePager: updatePager });
            }
        },

        _scrollToActivatedPage: function(number, updatePager) {
            const that = this,
                pageContainer = that.pageContainerWrapper;

            that._preventScroll = true;
            scrollToPage(pageContainer[0], number - 1);
            that.trigger(UPDATE, { action: PAGE_CHANGE, page: number, total: that.pages.length, updatePager: updatePager });
        },

        _getVisiblePagesCount: function() {
            var that = this,
                loadedPagesHeight = 0,
                updatedVisiblePagesCount = 0,
                containerHeight = that.pageContainer[0].clientHeight,
                index = 0;

            while (loadedPagesHeight <= containerHeight && index < that.pages.length)
            {
                loadedPagesHeight += that.pages[index].element.height();
                updatedVisiblePagesCount++;
                index++;
            }

            that._visiblePagesCount = updatedVisiblePagesCount;
        },

        _loadVisiblePages: function() {
            var pagesCount = this.pages && this.pages.length,
                minVisiblePageNum = Math.max(this._pageNum - this._visiblePagesCount, 1),
                maxVisiblePageNum = Math.min(this._pageNum + this._visiblePagesCount, pagesCount);

            this._visiblePages = this.pages.slice(minVisiblePageNum - 1, maxVisiblePageNum);

            for (var i = minVisiblePageNum; i <= maxVisiblePageNum; i++)
            {
                this.loadPage(i);
            }
        },

        _loadAllPages: function() {
            const pagesCount = this.pages && this.pages.length;
            const promises = [];

            for (var i = 0; i <= pagesCount; i++)
            {
                promises.push(this.loadPage(i));
            }

            return promises;
        },

        fromFile: function(file) {
            const that = this;
            if (that._isDPLProcessor()) {
                that.zoomScale = that.options.scale || ZOOM_SCALE;
                that.zoom(that.zoomScale, true);
                that.trigger(UPDATE, { action: "zoom", zoom: that.options.scale || "auto" });

                that.processor._updateDocument(file);
                that._loadDPLDocument();
            } else {
                if (that.pdfViewerCommon) {
                    that.annotationsToolbar?.destroy();
                    resetAnnotationEditorMode(that.pdfViewerCommon);
                    that.toolbar.toggle("[title='Annotations']", false);
                    that.toolbar.toggle("[title='Enable Selection']", false);
                    that.toolbar.toggle("[title='Enable Panning']", true);

                    const loadParams = $.isPlainObject(file) ? file : { url: file };

                    that.pdfViewerCommon.loadFile(loadParams);
                } else {
                    that._loadPdfJSDocument(file);
                }
            }
        },

        exportImage: function(options) {
            var that = this;
            var pageNumber = options.page;
            var page = that.pages[pageNumber - 1] || that._blankPage;
            var rootGroup = new drawing.Group();

            page.load();

            var background = kendo.drawing.Path.fromRect(new kendo.geometry.Rect([0, 0], [page.width, page.height]), {
                fill: {
                    color: WHITECOLOR
                },
                stroke: null
            });

            progress(that.pageContainer, true);
            rootGroup.append(background, page.group);

            drawing.exportImage(rootGroup).done(function(data) {
                progress(that.pageContainer, false);
                kendo.saveAs({
                    dataURI: data,
                    fileName: options.fileName,
                    proxyURL: options.proxyURL || "",
                    forceProxy: options.forceProxy,
                    proxyTarget: options.proxyTarget
                });
            });
        },

        exportSVG: function(options) {
            var that = this;
            var pageNumber = options.page;
            var page = that.pages[pageNumber - 1] || that._blankPage;

            progress(that.pageContainer, true);

            page.load();

            drawing.exportSVG(page.group).done(function(data) {
                progress(that.pageContainer, false);
                kendo.saveAs({
                    dataURI: data,
                    fileName: options.fileName,
                    proxyURL: options.proxyURL || "",
                    forceProxy: options.forceProxy,
                    proxyTarget: options.proxyTarget
                });
            });
        },

        setOptions: function(options)
        {
            var that = this;

            if (options.pdfjsProcessing || options.dplProcessing) {
                that._initProcessor(options || {});
            }

            options = $.extend(that.options, options);

            Widget.fn.setOptions.call(that, options);

            if (options.page) {
                that._pageNum = options.page;
                that.activatePage(options.page, false);
            }

            if (options.width) {
                that.element.width(options.width);
            }

            if (options.height) {
                that.element.height(options.height);
            }
        },

        destroy: function()
        {
            if (this._resizeHandler)
            {
                kendo.unbindResize(this._resizeHandler);
            }

            //destroy nested components
            if (this._errorDialog) {
                this._errorDialog.destroy();
            }

            if (this._saveDialog) {
                this._saveDialog.destroy();
            }

            if (this._upload) {
                this._upload.destroy();
            }

            if (this.pager) {
                this.pager.destroy();
            }

            if (this.toolbar) {
                this.toolbar.unbind();
                this.toolbar.destroy();
                this.toolbar = null;
            }

            if (this.pages && this.pages.length && this._isDPLProcessor()) {
                this.pages.forEach(function(page) {
                    page.destroy();
                });
                this.pages = [];
            }

            if (this.pdfScroller) {
                this.pdfScroller.destroy();
            }
            this.pageContainer.off(NS);
            this.pageContainerWrapper.off(NS);

            Widget.fn.destroy.call(this);
        },

        _clearPages: function() {
            this.pages = [];
            this.document = null;
            this._pageNum = 1;

            this.pageContainer.off(NS);
            this.pageContainer.empty();

            if (this.pdfScroller)
            {
                this.pdfScroller.destroy();
            }
        },

        onAnnotationEditorToolBarShow: function(e) {
            const that = this;
            const type = e.annotationEditorMode;
            const anchor = e.anchor;
            const popup = that.annotationPopup?.popup;
            const editor = e.source.firstSelectedEditor;

            // Do not continue if popup is already visible.
            if (popup && popup.visible() && !popup._closing && type === "freeText") {
                return;
            }

            if (that.annotationPopup) {
                that.annotationPopup.destroy();
                that.annotationPopup.destroyEditor();
                that.annotationPopup = null;
            }

            switch (type) {
                case "highlight":
                    that.annotationPopup = new HighlightAnnotationPopup(that, anchor, editor);
                    break;
                case "freeText":
                    that.annotationPopup = new FreeTextAnnotationPopup(that, anchor, editor);
                    break;
            }
        }
    });

    ui.plugin(PDFViewer);
})(window.kendo.jQuery);
export default kendo;

