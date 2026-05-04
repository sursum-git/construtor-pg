/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../util/undoredostack.js";
import "../kendo.aiprompt.js";
import "../kendo.buttongroup.js";
import "../kendo.combobox.js";
import "../kendo.dropdownlist.js";
import "../kendo.window.js";
import "../kendo.colorpicker.js";
import "../kendo.toolbar.js";
import "../kendo.icons.js";
import "../kendo.splitter.js";
import "../kendo.html.input.js";
import "../kendo.html.icon.js";
import "../kendo.inlineaiprompt.js";

(function($, undefined) {

    // Imports ================================================================
    var kendo = window.kendo,
        Class = kendo.Class,
        Widget = kendo.ui.Widget,
        os = kendo.support.mobileOS,
        browser = kendo.support.browser,
        extend = $.extend,
        deepExtend = kendo.deepExtend,
        keys = kendo.keys,
        outerWidth = kendo._outerWidth,
        outerHeight = kendo._outerHeight,
        NS = ".kendoEditor";

    const FORMATTING_MARKS_STYLE_ID = "formatting-marks-style",
        FORMATTING_MARKS_OVERLAY_ID = "formatting-marks-overlay",
        FORMATTING_MARKS_OVERLAY_WRAPPER_ID = "formatting-marks-overlay-wrapper";

    var SELECT = "select";
    var SELECT_OVERLAY_SELECTOR = "select.k-select-overlay";
    var PLACEHOLDER_CLASS = "k-placeholder";
    var PLACEHOLDER_TAG_ID = "placeholder";
    var REFRESH_INTERVAL = 200;
    var DEFAULT_LANGUAGE = "en";

    var NATIVE_TOOLS = [
        "insertLineBreak",
        "insertParagraph",
        "redo",
        "undo",
        "autoLink"
    ];

    const TOOLBAR_ALLOWED_CLOSE_SELECTORS = ".k-icon,.k-svg-icon,svg,path,.k-editor-window,.k-editor-toolbar,.k-editortoolbar-dragHandle,[ref-toolbar-tool],[ref-toolbar-tool] *";

    var EditorUtils = {
        editorWrapperTemplate: () =>
            '<div class="k-editor">' +
            '<div class="k-editor-toolbar" role="toolbar"></div>' +
            '<div class="k-editable-area k-editor-content"></div>' +
            '</div>',

        formatByName: function(name, format) {
            for (var i = 0; i < format.length; i++) {
                if ($.inArray(name, format[i].tags) >= 0) {
                    return format[i];
                }
            }
        },

        getToolCssClass: function(name, icon) {
            var toolCssClassNames = {
                superscript: "supscript",
                subscript: "subscript",
                justifyLeft: "align-left",
                justifyCenter: "align-center",
                justifyRight: "align-right",
                justifyFull: "align-justify",
                insertUnorderedList: "list-unordered",
                insertOrderedList: "list-ordered",
                insertUpperRomanList: "list-roman-upper",
                insertLowerRomanList: "list-roman-lower",
                "import": "import",
                indent: "indent",
                outdent: "outdent",
                createLink: "link",
                unlink: "unlink",
                insertImage: "image",
                insertFile: "file-add",
                viewHtml: "code",
                foreColor: "foreground-color",
                backColor: "droplet",
                createTable: "table-add",
                addColumnLeft: "table-column-insert-left",
                addColumnRight: "table-column-insert-right",
                addRowAbove: "table-row-insert-above",
                addRowBelow: "table-row-insert-below",
                deleteRow: "table-row-delete",
                deleteColumn: "table-column-delete",
                mergeCellsHorizontally: "cells-merge-horizontally",
                mergeCellsVertically: "cells-merge-vertically",
                splitCellHorizontally: "cell-split-horizontally",
                splitCellVertically: "cell-split-vertically",
                tableProperties: "table-properties",
                tableCellProperties: "table-cell-properties",
                tableWizardInsert: "table-wizard",
                tableAlignLeft: "table-position-start",
                tableAlignCenter: "table-position-center",
                tableAlignRight: "table-position-end",
                cleanFormatting: "clear-css",
                copyFormat: "copy-format",
                applyFormat: "apply-format",
                pdf: "file-pdf"
            };

            var cssClass = toolCssClassNames[name];

            if (cssClass) {
                return cssClass;
            }

            if (icon) {
                return icon;
            }

            return EditorUtils.nameToKebab(name);
        },

        nameToKebab: function(name) {
            return name.replace(/([a-z])([A-Z])/g, "$1-$2")
                .replace(/[\s_]+/g, '-')
                .toLowerCase();
        },

        registerTool: function(toolName, tool) {
            if (!tool.name) {
                tool.options.name = toolName;
                tool.name = toolName.toLowerCase();
            }

            if (!tool.options.ui) {
                tool.options.ui = {};
            }

            tool.options.ui.command = toolName;

            Editor.defaultTools[toolName] = tool;
        },

        registerFormat: function(formatName, format) {
            Editor.fn.options.formats[formatName] = format;
        },

        cacheComments: function(content, comments) {
            for (var index in comments) {
                content = content.replace(comments[index], "{" + index + "}");
            }
            return content;
        },

        retrieveComments: function(content, comments) {
            for (var index in comments) {
                content = content.replace("{" + index + "}", comments[index]);
            }
            return content;
        }
    };

    var messages = {
        auto: "Auto",
        bold: "Bold",
        italic: "Italic",
        underline: "Underline",
        strikethrough: "Strikethrough",
        superscript: "Superscript",
        subscript: "Subscript",
        fitToCell: "Fit to cell",
        justifyCenter: "Center text",
        justifyLeft: "Align text left",
        justifyRight: "Align text right",
        justifyFull: "Justify",
        insertUnorderedList: "Insert unordered list",
        insertOrderedList: "Insert ordered list",
        lineHeight: "Set line height",
        formattingMarks: "Toggle formatting marks",
        indent: "Indent",
        outdent: "Outdent",
        createLink: "Insert hyperlink",
        unlink: "Remove hyperlink",
        insertImage: "Insert image",
        insertFile: "Insert file",
        insertHtml: "Insert HTML",
        viewHtml: "View HTML",
        fontName: "Select font family",
        fontNameInherit: "(inherited font)",
        fontSize: "Select font size",
        fontSizeInherit: "(inherited size)",
        formatBlock: "Format",
        formatting: "Format",
        foreColor: "Color",
        backColor: "Background color",
        style: "Styles",
        emptyFolder: "Empty Folder",
        editAreaTitle: "Editable area. Press F10 for toolbar.",
        uploadFile: "Upload",
        overflowAnchor: "More tools",
        orderBy: "Arrange by:",
        orderBySize: "Size",
        orderByName: "Name",
        invalidFileType: "The selected file {0} is not valid. Supported file types are {1}.",
        deleteFile: 'Are you sure you want to delete {0}?',
        overwriteFile: 'A file with name {0} already exists in the current directory. Do you want to overwrite it?',
        directoryNotFound: "A directory with this name was not found.",
        imageWebAddress: "Web address",
        imageAltText: "Alternate text",
        imageWidth: "Width (px)",
        imageHeight: "Height (px)",
        fileWebAddress: "Web address",
        fileTitle: "Title",
        fileText: "Text",
        linkWebAddress: "Web address",
        linkText: "Text",
        linkToolTip: "ToolTip",
        linkOpenInNewWindow: "Open link in new window",
        dialogUpdate: "Update",
        dialogInsert: "Insert",
        dialogOk: "Confirm",
        dialogCancel: "Cancel",
        cleanFormatting: "Clean formatting",
        createTable: "Create a table",
        createTableHint: "Create a {0} x {1} table",
        addColumnLeft: "Add column on the left",
        addColumnRight: "Add column on the right",
        addRowAbove: "Add row above",
        addRowBelow: "Add row below",
        deleteRow: "Delete row",
        deleteColumn: "Delete column",
        mergeCellsHorizontally: "Merge cells horizontally",
        mergeCellsVertically: "Merge cells vertically",
        splitCellHorizontally: "Split cells horizontally",
        splitCellVertically: "Split cells vertically",
        tableAlignLeft: "Table Align Left",
        tableAlignCenter: "Table Align Center",
        tableAlignRight: "Table Align Right",
        tableWizard: "Table Wizard",
        tableProperties: "Table Properties",
        tableCellProperties: "Cell Properties",
        tableTab: "General",
        cellTab: "Cell",
        accessibilityTab: "Advanced",
        caption: "Caption",
        summary: "Summary",
        width: "Width",
        height: "Height",
        units: "Units",
        cellSpacing: "Cell Spacing",
        cellPadding: "Cell Padding",
        topPadding: "Top",
        leftPadding: "Left",
        bottomPadding: "Bottom",
        rightPadding: "Right",
        independentCellPaddings: "Independent Cell Paddings",
        cellMargin: "Cell Margin",
        alignment: "Alignment",
        captionAlignment: "Caption alignment",
        position: "Position",
        background: "Background",
        tableBackground: "Table background",
        cssClass: "CSS Class",
        id: "ID",
        border: "Border",
        borderStyle: "Border Style",
        borderWidth: "Border Width",
        borderColor: "Border Color",
        collapseBorders: "Collapse borders",
        wrapText: "Wrap text",
        associateCellsWithHeaders: "Associate headers",
        alignLeft: "Align Left",
        alignCenter: "Align Center",
        alignRight: "Align Right",
        alignLeftTop: "Align Left Top",
        alignCenterTop: "Align Center Top",
        alignRightTop: "Align Right Top",
        alignLeftMiddle: "Align Left Middle",
        alignCenterMiddle: "Align Center Middle",
        alignRightMiddle: "Align Right Middle",
        alignLeftBottom: "Align Left Bottom",
        alignCenterBottom: "Align Center Bottom",
        alignRightBottom: "Align Right Bottom",
        alignRemove: "Remove Alignment",
        columns: "Columns",
        rows: "Rows",
        selectAllCells: "Apply to all cells",
        applyToColumn: "apply to column",
        applyToRow: "apply to row",
        exportAs: "Export As",
        "import": "Import",
        print: "Print",
        headerRows: "Header Rows",
        headerColumns: "Header Cols",
        tableSummaryPlaceholder: "Summary attribute is not HTML5 compatible.",
        associateNone: "None",
        associateScope: "Associate using 'scope' attribute",
        associateIds: "Associate using Ids",
        copyFormat: "Copy format",
        applyFormat: "Apply format",
        borderNone: "None",
        undo: "Undo",
        redo: "Redo"
    };

    var supportedContentEditable = typeof (document.documentElement.contentEditable) != 'undefined';

    var toolGroups = {
        basic: ["bold", "italic", "underline", "strikethrough"],
        undo: ["undo", "redo"],
        scripts: ["subscript", "superscript"],
        alignment: ["justifyLeft", "justifyCenter", "justifyRight", "justifyFull"],
        lines: ["lineHeight"],
        symbols: ["formattingMarks"],
        links: ["insertImage", "insertFile", "createLink", "unlink"],
        lists: ["insertUnorderedList", "insertOrderedList", "insertUpperRomanList", "insertLowerRomanList",],
        dent: ["indent", "outdent"],
        tablesWizard: ["tableProperties", "tableCellProperties"],
        tablesInsert: ["createTable"],
        tablesAddDelete: ["addColumnLeft", "addColumnRight", "addRowAbove", "addRowBelow", "deleteRow", "deleteColumn"],
        tablesMerge: ["mergeCellsHorizontally", "mergeCellsVertically", "splitCellHorizontally", "splitCellVertically"],
        tablesPosition: ["tableAlignLeft", "tableAlignCenter", "tableAlignRight"],
        formatPainter: ["copyFormat", "applyFormat"]
    };

    var Editor = Widget.extend({
        __suppressEditableCheck: false,
        init: function(element, options) {
            var that = this,
                editorNS = kendo.ui.editor,
                dom = editorNS.Dom,
                toolbarContainer,
                type, domElement;


            /* suppress initialization in devices w/o proper contenteditable support */
            if (!supportedContentEditable && !that.__suppressEditableCheck) {
                return;
            }

            Widget.fn.init.call(that, element, options);

            that.options = deepExtend({}, that.options, options);

            element = that.element;
            domElement = element[0];
            type = dom.name(domElement);

            this._registerHandler(
                element.closest("form"), "submit", that.update.bind(that, undefined)
            );

            if (that._isAiPromptEnabled()) {
                that._addAIPromptElements();
            }

            toolbarContainer = that._setupType(element);

            that._initToolbar(toolbarContainer);
            that._setupHeight(type);
            that._resizable();
            that._initializeContentElement(that);
            that._setupKeyboard();

            that.clipboard = new editorNS.Clipboard(this);
            that.undoRedoStack = new kendo.util.UndoRedoStack();

            that._populateValue(options, domElement);

            that._registerHandler(document, {
                "mousedown": function() { that._endTyping(); },
                "mouseup": function(e) { that._mouseup(e); }
            });

            that._registerHandler(window, "resize", that._handleSpaceAndNewLines.bind(that));

            that._initializeImmutables();

            if (type == "textarea") {
                that._ariaLabel(that.wrapper.find("iframe"));
            }

            that.toolbar.resize();

            that._initInlineOverflowWrapper();

            kendo.notify(that);

            if (that._showWatermarkOverlay) {
                that._showWatermarkOverlay((that.wrapper && that.wrapper[0]) || that.element[0]);
            }

            if (that._isAiPromptEnabled()) {
                that._initAIPrompt();
            }

            if (that._isInlineAIPromptEnabled()) {
                that._initInlineAIPrompt();
            }
        },

        events: [
            "select",
            "change",
            "execute",
            "error",
            "paste",
            "keydown",
            "keyup"
        ],

        options: {
            name: "Editor",
            messages: messages,
            placeholder: "",
            nonce: "",
            unsafeInline: true,
            formats: {},
            encoded: true,
            formattingMarksRefreshDelay: 250,
            domain: null,
            resizable: false,
            deserialization: {
                custom: null
            },
            serialization: {
                entities: true,
                semantic: true,
                scripts: false,
                optimizeTags: false,
                keepEOL: false
            },
            pasteCleanup: {
                all: false,
                css: false,
                custom: null,
                keepNewLines: false,
                msAllFormatting: false,
                msConvertLists: true,
                msTags: true,
                none: false,
                span: false
            },
            stylesheets: [],
            dialogOptions: {
                modal: true, resizable: false, draggable: true,
                animation: false
            },
            imageBrowser: null,
            fileBrowser: null,
            navigateOnTab: false,
            fontName: [
                { text: "Arial", value: "Arial, Helvetica, sans-serif" },
                { text: "Courier New", value: "\"Courier New\", Courier, monospace" },
                { text: "Georgia", value: "Georgia, serif" },
                { text: "Impact", value: "Impact, Charcoal, sans-serif" },
                { text: "Lucida Console", value: "\"Lucida Console\", Monaco, monospace" },
                { text: "Tahoma", value: "Tahoma, Geneva, sans-serif" },
                { text: "Times New Roman", value: "\"Times New Roman\", Times, serif" },
                { text: "Trebuchet MS", value: "\"Trebuchet MS\", Helvetica, sans-serif" },
                { text: "Verdana", value: "Verdana, Geneva, sans-serif" }
            ],
            fontSize: [
                { text: "1 (8pt)", value: "xx-small" },
                { text: "2 (10pt)", value: "x-small" },
                { text: "3 (12pt)", value: "small" },
                { text: "4 (14pt)", value: "medium" },
                { text: "5 (18pt)", value: "large" },
                { text: "6 (24pt)", value: "x-large" },
                { text: "7 (36pt)", value: "xx-large" }
            ],
            formatBlock: [
                { text: "Paragraph", value: "p" },
                { text: "Quotation", value: "blockquote" },
                { text: "Heading 1", value: "h1" },
                { text: "Heading 2", value: "h2" },
                { text: "Heading 3", value: "h3" },
                { text: "Heading 4", value: "h4" },
                { text: "Heading 5", value: "h5" },
                { text: "Heading 6", value: "h6" }
            ],
            tools: [
                "formatting",
                "bold",
                "italic",
                "underline",
                "undo",
                "redo",
                "justifyLeft",
                "justifyCenter",
                "justifyRight",
                "copyFormat",
                "applyFormat",
                "insertUnorderedList",
                "insertOrderedList",
                "indent",
                "outdent",
                "createLink",
                "unlink",
                "insertImage",
                "tableProperties",
                "tableCellProperties",
                "createTable",
                "addRowAbove",
                "addRowBelow",
                "addColumnLeft",
                "addColumnRight",
                "deleteRow",
                "deleteColumn",
                "mergeCellsHorizontally",
                "mergeCellsVertically",
                "splitCellHorizontally",
                "splitCellVertically",
                "tableAlignLeft",
                "tableAlignCenter",
                "tableAlignRight"
            ],
            ai: false
        },

        destroy: function() {
            var editor = this;

            Widget.fn.destroy.call(this);

            this._endTyping(true);

            this._deregisterHandlers();

            clearTimeout(this._spellCorrectTimeout);

            this._focusOutside();

            this.toolbar.destroy();

            if (this.windowInstance) {
                this.windowInstance.destroy();
            }
            if (this.tablePopup) {
                this.tablePopup.destroy();
            }

            editor._destroyUploadWidget();

            if (this.interSectionObserver) {
                this.interSectionObserver.disconnect();
                this.interSectionObserver = null;
            }

            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
                this.wrapper.css("min-height", "");
                this.wrapper.css("max-height", "");
            }

            editor._destroyResizings();

            editor._destroyFormattingMarksOverlay();

            kendo.destroy(this.wrapper);
        },

        setOptions: function(options) {
            var editor = this;

            Widget.fn.setOptions.call(editor, options);

            if (options.tools) {
                this._setOptionsTools(options.tools);
            }

            this._initializePlaceholder();
        },

        createRange: function(document) {
            return kendo.ui.editor.RangeUtils.createRange(document || this.document);
        },

        encodedValue: function() {
            return kendo.ui.editor.Dom.encode(this.value());
        },

        exec: function(name, params) {
            var that = this;
            var command = null;
            var defaultTools = kendo.ui.Editor.defaultTools;
            var range, tool, prevented;

            if (!name) {
                throw new Error("kendoEditor.exec(): `name` parameter cannot be empty");
            }

            if (that.body.getAttribute("contenteditable") !== "true" &&
                name !== "print" &&
                name !== "pdf" &&
                name !== "exportAs") {
                return false;
            }

            if (!that.keyboard.isTypingInProgress()) {
                that._focusBody();
                that.selectRange(that._range || that.getRange());
            }

            tool = that.tools[name] || defaultTools[name];

            if (!tool) {
                Object.keys(defaultTools).map(key => {
                    if (key.toLowerCase() === name) {
                        tool = defaultTools[key];
                    }
                });
            }

            if (tool) {
                range = that.getRange();

                if (tool.command) {
                    command = tool.command(extend({ range: range, body: that.body, immutables: !!that.immutables }, params));
                }

                prevented = that.trigger("execute", { name: name, command: command });

                if (prevented) {
                    return;
                }

                if (/^(undo|redo)$/i.test(name)) {
                    that.undoRedoStack[name]();
                } else if (command) {
                    that.execCommand(command);

                    if (command.async) {
                        command.change = that._commandChange.bind(that);
                        return;
                    }
                }

                // Prevent double execution when the formatting marks tool is used.
                if (name !== "formattingMarks") {
                    that._handleSpaceAndNewLines();
                }
                that._selectionChange();
            }
        },

        _commandChange: function() {
            const that = this;
            that._selectionChange();
            that._handleSpaceAndNewLines();
        },

        execCommand: function(command) {
            if (!command.managesUndoRedo) {
                this.undoRedoStack.push(command);
            }

            command.editor = this;
            command.exec();
        },

        focus: function() {
            this.restoreSelection();
        },

        getRange: function() {
            var selection = this.getSelection(),
                range = selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : this.createRange(),
                doc = this.document;

            if (range.startContainer == doc && range.endContainer == doc && !range.startOffset && !range.endOffset) {
                range.setStart(this.body, 0);
                range.collapse(true);
            }

            return range;
        },

        getSelection: function() {
            return kendo.ui.editor.SelectionUtils.selectionFromDocument(this.document);
        },

        paste: function(html, options) {
            this.focus();

            var command = new kendo.ui.editor.InsertHtmlCommand($.extend({
                range: this.getRange(),
                html: html
            }, options));

            command.editor = this;

            command.exec();
        },

        insertAfterSelection: function(html, options) {
            this.focus();
            // Insert content after the current selection
            const range = this.getRange();
            range.collapse(false); // Move caret to the end of the selection
            var command = new kendo.ui.editor.InsertHtmlCommand($.extend({
                range: range,
                html: html
            }, options));

            command.editor = this;

            command.exec();
        },

        refresh: function() {
            var that = this;

            if (that.textarea) {
                that._destroyResizings();
                // preserve updated value before re-initializing
                // don't use update() to prevent the editor from encoding the content too early
                var value = that.value();
                that.textarea.val(value);
                that.wrapper.find("iframe").remove();
                that._initializeContentElement(that);
                that.value(value);
            }
        },

        restoreSelection: function() {
            this._focusBody();

            if (this.selectionRestorePoint) {
                this.selectRange(this.selectionRestorePoint.toRange());
            }
        },

        saveSelection: function(range) {
            range = range || this.getRange();
            var container = range.commonAncestorContainer,
                body = this.body;

            if (container == body || $.contains(body, container)) {
                this.selectionRestorePoint = new kendo.ui.editor.RestorePoint(range, body);
            }
        },

        selectedHtml: function() {
            return kendo.ui.editor.Serializer.domToXhtml(this.getRange().cloneContents());
        },

        selectRange: function(range) {
            this._focusBody();
            var selection = this.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            this.saveSelection(range);
        },

        state: function(toolName) {
            var tool = kendo.ui.Editor.defaultTools[toolName];
            var finder = tool && (tool.options.finder || tool.finder);
            var RangeUtils = kendo.ui.editor.RangeUtils;
            var range, textNodes;

            if (finder) {
                range = this.getRange();

                textNodes = RangeUtils.textNodes(range);

                if (!textNodes.length && range.collapsed) {
                    textNodes = [range.startContainer];
                }

                return finder.getFormat ? finder.getFormat(textNodes) : finder.isFormatted(textNodes);
            }

            return false;
        },

        update: function(value) {
            value = value || this.options.encoded ? this.encodedValue() : this.value();

            if (this.textarea) {
                this.textarea.val(value);
                this._togglePlaceholder(!value.trim());
            } else {
                this._oldValue = value;
            }
        },

        value: function(html) {
            var body = this.body,
                editorNS = kendo.ui.editor,
                options = this.options,
                currentHtml = editorNS.Serializer.domToXhtml(body, options.serialization);

            if (html === undefined) {
                return currentHtml;
            }

            if (html == currentHtml) {
                return;
            }

            editorNS.Serializer.htmlToDom(html, body, options.deserialization);

            this.selectionRestorePoint = null;
            this.update();
            this._refreshTools();
        },

        _bindTools: function() {
            var that = this,
                defaultTools = this.tools;

            that.unbind("select", that.toolbar.resize.bind(that.toolbar));

            that.toolbar.element.find("[data-command]")
                .filter((i, el) => $(el).find("[data-command]").length === 0)
                .each((i, el) => {
                    var toolName = el.getAttribute("data-command"),
                        tool = defaultTools[toolName];

                    if (tool && tool.initialize) {
                        tool.initialize($(el), that);
                    }
                });

            that.bind("select", that.toolbar.resize.bind(that.toolbar));
        },

        _blur: function() {
            var textarea = this.textarea;
            var old = textarea ? textarea.val() : this._oldValue;
            var value = this.options.encoded ? this.encodedValue() : this.value();

            this.update();

            if (textarea) {
                textarea.trigger("blur");
            }

            if (value != old) {
                this.trigger("change");
                if (textarea) {
                    textarea.trigger("change");
                }
            }
        },

        _containsRange: function(range) {
            var dom = kendo.ui.editor.Dom;
            var body = this.body;
            return range && dom.isAncestorOrSelf(body, range.startContainer) &&
                dom.isAncestorOrSelf(body, range.endContainer);
        },

        _createContentElement: function(stylesheets) {
            var editor = this;
            var iframe, wnd, doc;
            var textarea = editor.textarea;
            var specifiedDomain = editor.options.domain;
            var domain = specifiedDomain || document.domain;
            var domainScript = "";
            var src = 'javascript:""';
            var lang = "";

            textarea.hide();

            iframe = $("<iframe />", { title: editor.options.messages.editAreaTitle, frameBorder: "0" })[0];

            $(iframe)
                .css("display", "")
                .addClass("k-content k-iframe")
                .attr("tabindex", textarea[0].tabIndex)
                .insertBefore(textarea);

            // automatically relax same-origin policy if document.domain != location.hostname,
            // or forcefully relax if options.domain is specified (for document.domain = document.domain scenario)
            if (specifiedDomain || domain != location.hostname) {
                // relax same-origin policy
                domainScript = `<script ${editor.options.nonce ? `nonce="${editor.options.nonce}"` : ""}>document.domain="${domain}"</script>`;
                src = "javascript:document.write('" + domainScript + "')";
                iframe.src = src;
            }

            if (!iframe.src) {
                iframe.src = "";
            }

            wnd = iframe.contentWindow || iframe;
            doc = wnd.document || iframe.contentDocument;

            // https://github.com/telerik/kendo-ui-core/issues/7561
            const observer = editor.interSectionObserver = new IntersectionObserver(entries => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        editor._decorateFormatting(doc.body);
                        observer.unobserve(iframe);
                    }
                }
            });
            observer.observe(iframe);

            lang = document.getElementsByTagName("html")[0].getAttribute("lang") || DEFAULT_LANGUAGE;

            doc.open();
            doc.write(
                "<!DOCTYPE html><html lang='" + lang + "'><head>" +
                "<meta charset='utf-8' />" +
                "<title>Kendo UI Editor content</title>" +
                `<style ${editor.options.nonce ? `nonce="${editor.options.nonce}"` : ""}>` +
                "html{padding:0;margin:0;height:100%;min-height:100%;cursor:text;}" +
                "body{padding:0;margin:0;}" +
                "body{box-sizing:border-box;font-size:12px;font-family:Verdana,Geneva,sans-serif;margin-top:-1px;padding:5px .4em 0;" +
                "word-wrap: break-word;-webkit-nbsp-mode: space;-webkit-line-break: after-white-space;" +
                (kendo.support.isRtl(textarea) ? "direction:rtl;" : "") +
                (os.ios ? "word-break:keep-all;" : "") +
                (browser.edge || browser.chrome ? "height:100%;" : "") +
                "}" +
                "h1{font-size:2em;margin:.67em 0}h2{font-size:1.5em}h3{font-size:1.16em}h4{font-size:1em}h5{font-size:.83em}h6{font-size:.7em}" +
                "p{margin:0 0 1em;}.k-marker{display:none;}.k-paste-container,.Apple-style-span{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}" +
                "ul,ol{padding-left:2.5em}" +
                "span{-ms-high-contrast-adjust:none;}" +
                "a{color:#00a}" +
                "code{font-size:1.23em}" +
                "telerik\\3Ascript{display: none;}" +
                ".k-table{width:100%;border-spacing:0;margin: 0 0 1em;}" +
                ".k-table td{min-width:1px;padding:.2em .3em;}" +
                ".k-table,.k-table td{outline:0;border: 1px dotted #ccc;}" +
                ".k-table th{outline:0;border: 1px dotted #999;}" +
                ".k-table p{margin:0;padding:0;}" +
                ".k-column-resize-handle-wrapper {position: absolute; height: 10px; width:10px; cursor: col-resize; z-index: 2;}" +
                ".k-column-resize-handle {width: 100%; height: 100%;}" +
                ".k-column-resize-handle > .k-column-resize-marker {width:2px; height:100%; margin:0 auto; background-color:#00b0ff; display:none; opacity:0.8;}" +
                ".k-row-resize-handle-wrapper {position: absolute; cursor: row-resize; z-index:2; width: 10px; height: 10px;}" +
                ".k-row-resize-handle {display: table; width: 100%; height: 100%;}" +
                ".k-row-resize-marker-wrapper{display: table-cell; height:100%; width:100%; margin:0; padding:0; vertical-align: middle;}" +
                ".k-row-resize-marker{margin: 0; padding:0; width:100%; height:2px; background-color: #00b0ff; opacity:0.8; display:none;}" +
                ".k-element-resize-handle-wrapper {position: absolute; background-color: #fff; border: 1px solid #000; z-index: 100; width: 5px; height: 5px;}" +
                ".k-element-resize-handle {width: 100%; height: 100%;}" +
                ".k-element-resize-handle.k-resize-east{cursor:e-resize;}" +
                ".k-element-resize-handle.k-resize-north{cursor:n-resize;}" +
                ".k-element-resize-handle.k-resize-northeast{cursor:ne-resize;}" +
                ".k-element-resize-handle.k-resize-northwest{cursor:nw-resize;}" +
                ".k-element-resize-handle.k-resize-south{cursor:s-resize;}" +
                ".k-element-resize-handle.k-resize-southeast{cursor:se-resize;}" +
                ".k-element-resize-handle.k-resize-southwest{cursor:sw-resize;}" +
                ".k-element-resize-handle.k-resize-west{cursor:w-resize;}" +
                ".k-table.k-element-resizing{opacity:0.6;}" +
                ".k-placeholder{color:grey}" +
                "k\\:script{display:none;}" +
                "</style>" +
                domainScript +
                $.map(stylesheets, function(href) {
                    return `<link rel='stylesheet' href='${href}' ${editor.options.nonce ? `nonce="${editor.options.nonce}"` : ""}>`;
                }).join("") +
                `</head><body id='${editor.element.attr("id")}' autocorrect='off' contenteditable='true'></body></html>`
            );
            doc.close();

            return wnd;
        },

        _deleteSavedRange: function() {
            if ("_range" in this) {
                delete this._range;
            }
        },

        _decorateFormatting: function(body) {
            var formattingElement = this.toolbar.element.find("input[data-command=formatting]"),
                dom = kendo.ui.editor.Dom,
                component, dataSource, items, i, tag, className, style;

            if (formattingElement.length) {
                component = formattingElement.getKendoDropDownList();
                if (!component) {
                    return;
                }
                dataSource = component.dataSource;
                items = dataSource.data();

                if (body) {
                    component.list.css("background-color", dom.getEffectiveBackground($(body)));
                }

                if (this.options.unsafeInline !== false) {

                    for (i = 0; i < items.length; i++) {
                        tag = items[i].tag || "span";
                        className = items[i].className;

                        style = dom.inlineStyle(body, tag, { className: className });
                        style = style.replace(/"/g, "'");

                        items[i].style = style + ";display:inline-block";
                    }
                }

                dataSource.trigger("change");
            }
        },

        _appendFormattingMarksStyles: function() {
            const that = this,
                elementId = that.element.attr("id");

            let style, $head;

            style = `<style id='${FORMATTING_MARKS_STYLE_ID + "-" + elementId}' ${that.options.nonce ? `nonce="${that.options.nonce}"` : ""} >
                #${elementId} p::after,
                #${elementId} li::after,
                #${elementId} h1::after,
                #${elementId} h2::after,
                #${elementId} h3::after,
                #${elementId} h4::after,
                #${elementId} h5::after,
                #${elementId} h6::after {content: '\\00B6'; color: #6098f2;}
                #${FORMATTING_MARKS_OVERLAY_WRAPPER_ID + "-" + elementId} {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    z-index: 9999;
                    pointer-events: none;
                }
                #${FORMATTING_MARKS_OVERLAY_ID + "-" + elementId} {
                    width: 100%;
                    height: 100%;
                  }
            </style>`;

            $head = $(that.document.head);
            $head.find("#" + FORMATTING_MARKS_STYLE_ID + "-" + elementId).remove();
            $head.append(style);
        },

        _appendFormattingMarksOverlay: function() {
            const that = this,
                elementId = that.element.attr("id");

            that._formattingMarksOverlayWrapper = $(`<div id='${FORMATTING_MARKS_OVERLAY_WRAPPER_ID + "-" + elementId}'></div>`);
            that._formattingMarksOverlay = $(`<svg id='${FORMATTING_MARKS_OVERLAY_ID + "-" + elementId}'></svg>`).appendTo(that._formattingMarksOverlayWrapper);
            that._bodyTopBottomPaddings = parseFloat($(that.body).css("padding-top")) + parseFloat($(that.body).css("padding-bottom"));

            if (that._isInlineEditor()) {
                that._formattingMarksOverlayWrapper.insertAfter(that.element);
            } else {
                that._formattingMarksOverlayWrapper.appendTo($(that.document).find("html"));
            }
        },

        _destroyFormattingMarksOverlay: function() {
            const that = this,
                elementId = that.element.attr("id");

            $(that.document).find("#" + FORMATTING_MARKS_OVERLAY_WRAPPER_ID + "-" + elementId).remove();
            $(that.document.head).find("#" + FORMATTING_MARKS_STYLE_ID + "-" + elementId).remove();
        },

        _updateFormattingMarksOverlayHeight: function() {
            const that = this;

            if (that._isInlineEditor()) {
                that._formattingMarksOverlayWrapper.height($(that.body).outerHeight());
            } else {
                that._formattingMarksOverlayWrapper.height(that.body.scrollHeight - that._bodyTopBottomPaddings);
            }
        },

        _initInlineOverflowWrapper: function() {
            const that = this,
                inlineHeight = that.options.inlineHeight;

            if (that._isInlineEditor() && inlineHeight > -1) {
                that._inlineOverflowWrapper = that.element.wrap(`<div class='k-inline-editor-overflow-wrapper k-pos-relative k-overflow-auto'></div>`).parent();
                that._inlineOverflowWrapper.height(inlineHeight);
            }
        },

        _addAIPromptElements: function() {
            const that = this;
            const splitterElement = $('<div/>');
            const aiPromptElement = $('<div/>');


            that.splitterElement = splitterElement;
            that.aiPromptElement = aiPromptElement;
            splitterElement.insertBefore(that.element);
            aiPromptElement.appendTo(splitterElement);
            that.element.appendTo(splitterElement);
        },

        _initInlineAIPrompt: function() {
            const that = this;
            const element = $("<div/>").insertAfter(that.element);
            const defaultCommands = that._mapCommands(that.options.ai?.inlineAIPrompt?.commands || that.options.ai?.commands || kendo.ui.InlineAIPrompt.fn.options.commands);

            that.inlineAIPrompt = element.kendoInlineAIPrompt({
                popup: {
                    width: "768px"
                },
                service: that.options.ai?.service,
                commands: defaultCommands,
                _isEditor: true,
                systemPrompt: that.options.ai?.inlineAIPrompt?.systemPrompt,
                commandExecute: (e) => {
                    e.context = that.getRange().toString();
                },
                outputAction: (e) => {
                    if (e.action === 'insert') {
                        that.insertAfterSelection(e.content);
                    }

                    if (e.action === 'replace') {
                        that.paste(e.content);
                    }
                }
            }).data('kendoInlineAIPrompt');
        },

        _mapCommands: function(commands, parentId = null) {
            const that = this;

            return commands.map((command, i) => {
                const id = parentId ? `${parentId}-${i}` : `${i}`;
                const item = {
                    id: id,
                    text: command.text,
                    icon: command.icon
                };

                if (command.prompt) {
                    item.prompt = command.prompt;
                }

                if (command.items) {
                    item.items = that._mapCommands(command.items, id);
                }

                return item;
            });
        },

        _isAiPromptEnabled() {
            const that = this;

            return that.options.ai && that.options.ai.aiPrompt != false;
        },

        _isInlineAIPromptEnabled() {
            const that = this;

            return that.options.ai && that.options.ai.inlineAIPrompt != false;
        },

        _initAIPrompt: function() {
            const that = this;
            const defaultCommands = that.options.ai?.aiPrompt?.commands || that.options.ai?.commands || that._mapCommands(kendo.ui.InlineAIPrompt.fn.options.commands);

            that.aiPrompt = that.aiPromptElement.kendoAIPrompt($.extend({
                service: that.options.ai?.service,
                speechToText: false,
                promptTextArea: {
                    rows: 5,
                    suffixOptions: {
                        template: () => `<div ref-select-context-buttongroup></div>`
                    }
                },
                views: [
                    {
                        type: 'prompt',
                        buttonIcon: "",
                        initializeComponents: (e) => {
                            let view = e.view;
                            view.contentElement.find('[ref-select-context-buttongroup]').kendoButtonGroup({
                                index: that._contextSelection,
                                items: [
                                    { text: "Use with selection", icon: "box-sizing" },
                                    { text: "Use full content", icon: "form-element" }
                                ],
                                selection: "multiple",
                                fillMode: "flat",
                                select: (e) => {
                                    let selectionToggleButton = e.sender._buttons[0];
                                    let fullContentToggleButton = e.sender._buttons[1];

                                    if (e.indices.length > 1) {
                                        if (e.target[0] == selectionToggleButton.element[0]) {
                                            fullContentToggleButton.toggle(false);
                                            e.sender._toggleIndex(1);
                                        } else {
                                            selectionToggleButton.toggle(false);
                                            e.sender._toggleIndex(0);
                                        }
                                    }

                                    that._contextSelection = e.indices[0];
                                }
                            });
                        },
                    },
                    {
                        type: 'output',
                    },
                    {
                        type: 'commands',
                        promptCommands: defaultCommands
                    }
                ],
                promptRequest: (e) => {
                    const contextSelection = that._contextSelection;
                    let prompt = e.prompt;

                    if (contextSelection === 0) {
                        prompt = that._contextTemplate(prompt, that.getRange().toString());
                    } else if (contextSelection === 1) {
                        prompt = that._contextTemplate(prompt, that.value());
                    } else {
                        prompt = that._contextTemplate(prompt, "");
                    }

                    if (e.prompt != prompt) {
                        e.prompt = prompt;
                    }
                },
                commandExecute: (e) => {
                    const prompt = e.item.prompt;

                    if (prompt) {
                        that.aiPrompt.transport.read({
                            prompt: prompt(that.value()),
                            history: [],
                            isRetry: false
                        });
                    }
                }
            }, that.options.ai?.aiPrompt)).data('kendoAIPrompt');
            that.aiPromptSplitter = that.splitterElement.kendoSplitter({
                panes: [$.extend({
                    size: '50%',
                    collapsible: true,
                    collapsed: true,
                    min: "360px"
                }, that.options.ai?.pane), {}]
            }).data('kendoSplitter');

            if (that._isInlineAIPromptEnabled()) {
                that.bind("select", that._selectionChangeHandler.bind(that));
            }
        },

        _contextTemplate: function(query, context) {
            const that = this;
            const defaultTemplate = `You are an advanced AI language assistant.
            A user has selected a portion of their text and provided a query regarding how they want it modified.
            Your task is to accurately respond to their request while preserving the original intent of the text.
            Follow the instructions strictly and provide only the requested output unless explicitly asked to explain your changes.

            ${context ? "Selected Text:" : ""}
            ${context}

            User's Request:
            ${query}

            Response:`;

            if (!that.options.ai?.aiPrompt?.systemPrompt) {
                return defaultTemplate;
            }

            return that.options.ai?.aiPrompt?.systemPrompt(query, context);
        },

        _selectionChangeHandler: function() {
            const that = this,
                range = that.getRange(),
                selectionRect = range.getBoundingClientRect(),
                elementRect = that.wrapper[0].getBoundingClientRect(),
                editorNS = kendo.ui.editor,
                rangeUtils = editorNS.RangeUtils,
                nodes = rangeUtils.textNodes(range);

            if (nodes.length === 0) {
                that.inlineAIPrompt._popup.close();
                return;
            }

            that.inlineAIPrompt._popup.open();
            const popupHeight = that.inlineAIPrompt._popup.element.outerHeight();
            const popupWidth = that.inlineAIPrompt._popup.element.outerWidth();
            let y = window.scrollY + elementRect.top + selectionRect.top + selectionRect.height;
            let x = window.scrollX + elementRect.left + selectionRect.left + selectionRect.width / 2 - popupWidth / 2;
            that.inlineAIPrompt._popup.close(true);

            if (window.innerHeight - selectionRect.bottom < popupHeight) {
                y -= popupHeight;
            } else {
                y += popupHeight;
            }

            if (x + popupWidth > window.innerWidth + window.scrollX) {
                x = window.innerWidth + window.scrollX - popupWidth;
            }

            that.inlineAIPrompt._popup.open(x, y);
        },

        _getSelectionPosition: function() {
            const that = this;
            const range = that.getRange();
            const selectionRect = range.getBoundingClientRect();
        },

        _initContextMenu: function() {
            const that = this;
            const html = $(`<ul>
                            <li>${kendo.html.renderIcon({ icon: 'arrow-rotate-cw' })} Rewrite</li>
                            <li>${kendo.html.renderIcon({ icon: 'spell-checker' })} Fix mistakes</li>
                            <li>${kendo.html.renderIcon({ icon: 'tell-a-friend' })} Change tone
                                <ul>
                                    <li>Neutral</li>
                                    <li>Friendly</li>
                                    <li>Casual</li>
                                    <li>Formal</li>
                                </ul>
                            </li>
                            <li>${kendo.html.renderIcon({ icon: 'apply-format' })} Polish formatting
                            <li>${kendo.html.renderIcon({ icon: 'col-resize' })} Adjust length
                            <li>${kendo.html.renderIcon({ icon: 'edit-tools' })} Translate
                        </ul>`);

            $('body').append(html);

            that._aiContextMenu = html.kendoContextMenu({ targe: "imposibru" }).data('kendoContextMenu');
        },

        _insertAIResult: function() {
            const that = this,
                aiResult = "Test ai result";

            that.paste(aiResult);
        },

        _deregisterHandlers: function() {
            var handlers = this._handlers;

            for (var i = 0; i < handlers.length; i++) {
                var h = handlers[i];
                h.element.off(h.type, h.handler);
            }

            this._handlers = [];
        },

        _destroyUploadWidget: function() {
            var editor = this;
            if (editor._uploadWidget) {
                editor._uploadWidget.destroy();
                editor._uploadWidget = null;
            }
        },

        _endTyping: function() {
            var keyboard = this.keyboard;

            try {
                if (keyboard.isTypingInProgress()) {
                    keyboard.endTyping(true);
                    this.saveSelection();
                }
            } catch (e) { }
        },

        _focusBody: function() {
            var body = this.body;
            var iframe = this.wrapper && this.wrapper.find("iframe")[0];
            var documentElement = this.document.documentElement;
            var activeElement = kendo._activeElement();
            var scrollTop;


            if (!iframe && body.scrollHeight > body.clientHeight) {
                scrollTop = body.scrollTop;
                body.focus();
                body.scrollTop = scrollTop;

            } else if (activeElement != body && activeElement != iframe) {
                scrollTop = documentElement.scrollTop;
                body.focus();
                documentElement.scrollTop = scrollTop;
            }
        },

        _focusOutside: function() {
            // move focus outside the Editor, see https://github.com/telerik/kendo/issues/3673
            if (kendo.support.browser.msie && this.textarea) {
                var tempInput = $("<input />")
                    .css({
                        position: "fixed",
                        left: "1px",
                        top: "1px",
                        width: "1px",
                        height: "1px",
                        "font-size": "0",
                        border: "0",
                        opacity: "0"
                    })
                    .appendTo(document.body).trigger("focus");
                tempInput.trigger("blur").remove();
            }
        },

        _handleToolbarClick: function(e) {
            var toolName = e.target.data("command");

            if (toolName && toolName !== "createTable") {
                this.exec(toolName);
            }
        },

        _immutablesContext: function(range) {
            var editorNS = kendo.ui.editor;

            if (this.options.immutables) {
                if (range.collapsed) {
                    return editorNS.Immutables.immutablesContext(range);
                } else {
                    return editorNS.RangeUtils.editableTextNodes(range).length === 0;
                }
            }
        },

        _initializeContentElement: function() {
            var editor = this;
            var doc;
            var blurTrigger;
            var mousedownTrigger;

            if (editor.textarea) {
                editor.window = editor._createContentElement(editor.options.stylesheets);
                doc = editor.document = editor.window.contentDocument || editor.window.document;

                // Ensure that body exists as various Firefox plugins can cause a delay of the body creation
                // https://github.com/telerik/kendo-ui-core/issues/3515
                if (!doc.body) {
                    var body = doc.createElement("body");
                    body.setAttribute("contenteditable", "true");
                    body.setAttribute("autocorrect", "off");
                    doc.getElementsByTagName("html")[0].appendChild(body);

                    var interval = setInterval(function() {
                        if ($(editor.document).find("body").length > 1) {
                            $(editor.document).find("body").last().remove();
                            window.clearInterval(interval);
                        }
                    }, 10);
                }

                editor.body = doc.body;

                blurTrigger = editor.window;
                mousedownTrigger = doc;

                this._registerHandler(doc, "mouseup", this._mouseup.bind(this));
            } else {
                editor.window = window;
                doc = editor.document = document;
                editor.body = editor.element[0];

                blurTrigger = editor.body;
                mousedownTrigger = editor.body;

                editor._wrapSingleTextNodeInParagraph();

                setTimeout(() => {
                    editor._decorateFormatting(editor.body);
                });
            }

            this._registerHandler(blurTrigger, "blur", this._blur.bind(this));
            editor._registerHandler(mousedownTrigger, "down", editor._mousedown.bind(editor));

            try {
                doc.execCommand("enableInlineTableEditing", null, false);
            } catch (e) { }

            if (kendo.support.touch) {
                this._registerHandler(doc, {
                    "keydown": function() {
                        // necessary in iOS when touch events are bound to the page
                        if (kendo._activeElement() != doc.body) {
                            editor.window.focus();
                        }
                    }
                });
            }

            this._initializePlaceholder();

            this._spellCorrect(editor);

            this._registerHandler(editor.document, {
                "mouseover dragenter": function(e) {
                    var height = $(editor.body).height();
                    var htmlHeight = $(editor.body.parentElement).height();

                    if (htmlHeight > height && e.target.nodeName.toLowerCase() === "html") {
                        editor._cachedHeight = "" + editor.body.style.height;
                        editor.body.style.height = "100%";
                    }
                },
                "mouseout dragleave drop contextmenu": function(e) {
                    var restoreHeight = function() {
                        if (editor._cachedHeight !== undefined && e.target === editor.body) {
                            editor.body.style.height = editor._cachedHeight;
                            delete editor._cachedHeight;
                        }
                    };

                    if (e.type === "contextmenu") {
                        setTimeout(function() {
                            restoreHeight();
                        }, 10);
                    } else {
                        restoreHeight();
                    }

                }
            });

            this._registerHandler(editor.body, {
                "keydown": function(e) {
                    var range;

                    if ((e.keyCode === keys.BACKSPACE || e.keyCode === keys.DELETE) && editor.body.getAttribute("contenteditable") !== "true") {
                        return false;
                    }

                    if (e.keyCode === keys.F10) {
                        editor.toolbar.element.find("[tabindex=0]").first().trigger("focus");
                        editor.toolbar.preventPopupHide = true;
                        e.preventDefault();
                        return;
                    } else if (e.keyCode == keys.LEFT || e.keyCode == keys.RIGHT) {
                        // skip bom nodes when navigating with arrows
                        range = editor.getRange();
                        var left = e.keyCode == keys.LEFT;
                        var container = range[left ? "startContainer" : "endContainer"];
                        var offset = range[left ? "startOffset" : "endOffset"];
                        var direction = left ? -1 : 1;

                        var next = offset + direction;
                        var nextChar = left ? next : offset;

                        if (container.nodeType == 3 && container.nodeValue[nextChar] == "\ufeff") {
                            range.setStart(container, next);
                            range.collapse(true);
                            editor.selectRange(range);
                        }
                    }

                    var tools = editor.tools;
                    var toolName = editor.keyboard.toolFromShortcut(tools, e);
                    var toolOptions = toolName ? tools[toolName].options : {};

                    if (toolName && !toolOptions.keyPressCommand) {
                        e.preventDefault();

                        if (!/^(undo|redo)$/.test(toolName)) {
                            editor.keyboard.endTyping(true);
                        }

                        editor.trigger("keydown", e);
                        editor.exec(toolName);
                        editor._runPostContentKeyCommands(e);

                        return false;
                    }

                    editor.keyboard.clearTimeout();

                    editor.keyboard.keydown(e);
                },
                "keypress": function(e) {
                    setTimeout(function() {
                        editor._runPostContentKeyCommands(e);
                        editor._showElementResizeHandles();
                    }, 0);
                },
                "keyup": function(e) {
                    var selectionCodes = [
                        keys.BACKSPACE,
                        keys.TAB,
                        keys.PAGEUP,
                        keys.PAGEDOWN,
                        keys.END,
                        keys.HOME,
                        keys.LEFT,
                        keys.UP,
                        keys.RIGHT,
                        keys.DOWN,
                        keys.INSERT,
                        keys.DELETE
                    ];

                    if ($.inArray(e.keyCode, selectionCodes) > -1 || (e.keyCode == 65 && e.ctrlKey && !e.altKey && !e.shiftKey)) {
                        window.clearTimeout(this._refreshInterval);
                        this._refreshInterval = window.setTimeout(function() {
                            editor._selectionChange();
                        }, REFRESH_INTERVAL);
                    }

                    if (e.keyCode === keys.BACKSPACE || e.keyCode === keys.DELETE) {
                        editor._handleSpaceAndNewLines();
                    }

                    editor.keyboard.keyup(e);
                },
                "click": function(e) {
                    var dom = kendo.ui.editor.Dom, range;

                    if (dom.name(e.target) === "img") {
                        range = editor.createRange();
                        range.selectNode(e.target);
                        editor.selectRange(range);
                    }
                },
                "cut copy paste drop dragover": function(e) {
                    editor.clipboard["on" + e.type](e);
                    editor._handleSpaceAndNewLines();
                },
                "focusin": function() {
                    if (editor.body.hasAttribute("contenteditable")) {
                        $(this).addClass("k-active");
                        // To be moved to the Editor
                        editor._showWindow();
                        editor._togglePlaceholder(false);
                    }
                },
                "focusout": function() {
                    setTimeout(function() {
                        var active = kendo._activeElement();
                        var body = editor.body;
                        // All below to be moved to the Editor
                        var toolbar = editor.toolbar;

                        if (toolbar.options.popup) {
                            var toolbarContainerElement = editor.windowInstance.element.get(0);
                            if (toolbarContainerElement && !($.contains(toolbarContainerElement, active) || toolbarContainerElement == active)) {
                                toolbar.preventPopupHide = false;
                            }
                        }

                        if (active != body && !$.contains(body, active) && !editor._toolbarFocused() && !toolbar.preventPopupHide && !$(active).hasClass("k-context-menu") && !$(active).hasClass("k-colorpalette")) {
                            $(body).removeClass("k-active");
                            editor._hideWindow();
                        }

                        editor._togglePlaceholder(!editor.value().trim());
                    }, 10);
                }
            });

            editor._initializeColumnResizing();
            editor._initializeRowResizing();
            editor._initializeElementResizing();
        },

        _initializeImmutables: function() {
            var that = this,
                editorNS = kendo.ui.editor;

            if (that.options.immutables) {
                that.immutables = new editorNS.Immutables(that);
            }
        },

        _initializePlaceholder: function() {
            var that = this,
                placeholder = that.options.placeholder,
                style, $head;

            if (!that.textarea || !placeholder) {
                return;
            }

            style = `<style id='${PLACEHOLDER_TAG_ID}' ${that.options.nonce ? `nonce="${that.options.nonce}"` : ""} >.` +
                PLACEHOLDER_CLASS + ":before { content: '" + placeholder + "'; }" +
                "</style>";

            $head = $(that.document.head);
            $head.find("#" + PLACEHOLDER_TAG_ID).remove();
            $head.append(style);

            that._togglePlaceholder(!that.value().trim());
        },

        _initToolbar: function(toolbarContainer) {
            var that = this,
                tools, defaults, toolbarOptions;

            that.tools = [];
            [tools, defaults] = that._processToolbarItems(that.options.tools);
            that._setNativeTools();

            toolbarOptions = {
                tools: tools,
                defaultTools: defaults,
                parentMessages: that.options.messages,
                resizable: that.options.resizable === true || Boolean(that.options.resizable && that.options.resizable.toolbar && that.options.resizable.toolbar?.mode !== "none"),
                toggle: that._handleToolbarClick.bind(this),
                click: that._handleToolbarClick.bind(this),
                navigateOnTab: that.options.navigateOnTab,
                evaluateTemplates: true,
                overflow: $.isPlainObject(that.options.resizable?.toolbar) ? that.options.resizable.toolbar : undefined,
            };

            that.toolbar = new kendo.ui.ToolBar(toolbarContainer, toolbarOptions);

            that._bindTools();
            that.bind("select", that._refreshTools.bind(that));

            toolbarContainer.on("mousedown" + NS, function(e) {
                const target = $(e.target);
                const tool = target.closest('[ref-toolbar-tool]');

                if (!tool.length || !tool.data("command")) {
                    return;
                }

                if (tool.length) {
                    if (!target.is("input")) {
                        e.preventDefault();
                    }
                } else {
                    e.preventDefault();
                }
            });

            if (!that.textarea) {
                const toolbarWidth = toolbarContainer.width();
                if (toolbarWidth && toolbarWidth > 0) {
                    toolbarContainer.width(toolbarWidth);
                }

                that._initToolbarWindow(toolbarContainer);
            }
        },

        _initToolbarWindow: function(toolbarEl) {
            var that = this;

            this.windowInstance = toolbarEl
                .wrap("<div class='editorToolbarWindow k-editor-window' />")
                .parent()
                .prepend("<span class='k-editortoolbar-dragHandle'>" + kendo.ui.icon("handle-drag") + "</span>")
                .kendoWindow({
                    title: false,
                    resizable: false,
                    draggable: {
                        dragHandle: ".k-editortoolbar-dragHandle"
                    },
                    animation: {
                        open: { effects: "fade:in" },
                        close: { effects: "fade:out" }
                    },
                    minHeight: 42,
                    visible: false,
                    autoFocus: false,
                    actions: [],
                    dragend: function() {
                        this._moved = true;
                    }
                })
                .on("mousedown", function(e) {
                    if (!$(e.target).is(TOOLBAR_ALLOWED_CLOSE_SELECTORS)) {
                        that.toolbar.preventPopupHide = true;
                    }
                })
                .on("focusout", function() {
                    that.element.focusout();
                })
                .data("kendoWindow");
        },

        _mousedown: function(e) {
            var editor = this;
            editor._selectionStarted = true;

            if ($(editor.body).parents('.k-window').length) {
                e.stopPropagation();
            }

            // handle middle-click and ctrl-click on links
            if (browser.gecko) {
                return;
            }

            var target = $(e.target).closest("a[href]");

            if ((e.which == 2 || (e.which == 1 && e.ctrlKey)) && target && target.is("a[href]")) {
                window.open(target.attr("href"), "_new");
            }

            if (e.target.tagName && e.target.tagName.toLowerCase() === "html") {
                setTimeout(function() {
                    editor.body.focus();
                }, 0);
            }
        },

        _mouseup: function(e) {
            var that = this;

            if (kendo.support.mobileOS.ios && e && $(e.target).is(SELECT_OVERLAY_SELECTOR)) {
                return;
            }

            if (that._selectionStarted) {
                setTimeout(function() {
                    that._selectionChange();
                }, 1);
            }
        },

        _populateValue: function(options, domElement) {
            var that = this,
                editorNS = kendo.ui.editor,
                dom = editorNS.Dom,
                value, comments;

            if (options && options.value) {
                value = options.value;
            } else if (that.textarea) {
                // indented HTML introduces problematic ranges in IE
                value = domElement.value;

                if (!value.replace(/\s/g, '').length) {
                    value = value.replace(/\s/g, '');
                }

                // revert encoding of value when content is fetched from cache
                if (that.options.encoded && domElement.defaultValue && domElement.defaultValue.trim().length) {
                    value = domElement.defaultValue;
                }

                var deserializedRoot = editorNS.Serializer.htmlToDom(value, $("<div></div>")[0], that.options.deserialization);
                comments = dom.getAllComments(deserializedRoot);
                value = EditorUtils.cacheComments(value, comments);
                value = value.replace(/[\r\n\v\f\t ]+/ig, " ");
                value = EditorUtils.retrieveComments(value, comments);
            } else {
                value = domElement.innerHTML;
            }

            that.value(value || '\ufeff');
        },

        _resetGroups: function(current) {
            var result = {};

            Object.keys(current).map(key => {
                result[key + "_"] = current[key];
            });

            return result;
        },

        _processToolbarItems: function(optionsTools) {
            var flatDefaultTools = {},
                processedObject = {},
                tools = [],
                currentGroups = $.extend(true, {}, toolGroups),
                i, j, t, name, guidGroupName;

            for (i = 0; i < optionsTools.length; i++) {
                t = optionsTools[i];

                if (Array.isArray(t)) {
                    guidGroupName = kendo.guid();

                    for (j = 0; j < t.length; j++) {
                        [flatDefaultTools, name] = this._processToolOptions(t[j], flatDefaultTools);

                        if (!name) {
                            continue;
                        }

                        flatDefaultTools[name].gr = guidGroupName;
                    }
                } else {
                    [flatDefaultTools, name] = this._processToolOptions(t, flatDefaultTools);

                    if (!name) {
                        continue;
                    } else if (name === "separator") {
                        currentGroups = this._resetGroups(currentGroups);
                    }

                    Object.keys(currentGroups).map(key => {
                        var group = currentGroups[key];

                        if (group.includes(name)) {
                            flatDefaultTools[name].gr = key;
                        }
                    });
                }
            }

            Object.values(flatDefaultTools).map((tool) => {
                var group = tool.gr;

                delete tool.gr;

                if (group === undefined) {
                    processedObject[tool.name] = tool;
                } else {
                    if (!processedObject[group]) {
                        processedObject[group] = [];
                    }

                    processedObject[group].push(tool);
                }
            });

            tools = Object.values(processedObject).map((g) => {
                var result;

                if (g.length) {
                    result = g.map((to) => to.name);
                } else {
                    result = g.name;
                }

                if (result.length === 1) {
                    result = result[0];
                }

                return result;
            });

            return [tools, flatDefaultTools];
        },

        _processDefaultTool: function(tool, defaultTools) {
            var name = tool.name,
                result = {},
                options;

            if ($.isPlainObject(tool)) {
                if (name && defaultTools[name]) {
                    // configured tool
                    result = extend({}, defaultTools[name]);
                    extend(result.options, tool);
                } else {
                    // custom tool
                    options = extend({ icon: "gear", type: "button" }, tool);

                    if (!options.name) {
                        options.name = "custom";
                    }

                    options.cssClass = "k-" + options.name;

                    result = {
                        options: options
                    };

                    name = options.name;
                }
            } else if (defaultTools[tool]) {
                name = tool;
                result = defaultTools[name];
            } else {
                return null;
            }

            this.tools[name] = result;

            return result;
        },

        _processToolOptions: function(t, flatDefaultTools) {
            var that = this,
                defaultTools = kendo.deepExtend({}, kendo.ui.Editor.defaultTools),
                tool, name, view, shortcuts, options, uiOptions, icon, toolOptions, originalOptions, palette;

            tool = this._processDefaultTool(t, defaultTools);

            if (!tool) {
                return [flatDefaultTools, null];
            }

            options = tool.options;
            name = options.name;
            uiOptions = options.ui || {};
            icon = EditorUtils.getToolCssClass(options.name, options.icon);

            // We want to preserve any custom properties from the original tool object.
            originalOptions = $.isPlainObject(t) ? $.extend({}, t) : {};
            delete originalOptions.items;

            toolOptions = $.extend({
                name: options.name,
                command: options.command,
                template: options.template,
                attributes: { class: options.cssClass },
                type: "button",
                icon: icon,
                showText: "overflow",
                showIcon: "both"
            }, uiOptions, originalOptions);

            if (options.tooltip) {
                if (!toolOptions.attributes) {
                    toolOptions.attributes = {};
                }

                toolOptions.attributes.title = options.tooltip;
            }
            if (options.exec) {
                toolOptions.click = options.exec.bind(that);
                delete toolOptions.exec;
            }

            if (toolOptions.component === "ColorPicker") {
                if (options.palette !== undefined || uiOptions.palette !== kendo.ui.editor.ColorTool.fn.options.palette) {
                    palette = options.palette || kendo.ui.editor.ColorTool.fn.options.palette;
                    view = options.palette !== undefined && !options.palette ? "gradient" : "palette";
                    toolOptions.componentOptions.palette = view === "palette" ? palette : undefined;
                    toolOptions.componentOptions.views = [view];
                    toolOptions.componentOptions.preview = view === "gradient";
                    toolOptions.componentOptions.input = view === "gradient";
                    toolOptions.componentOptions.buttons = view === "gradient";

                    if (view === "gradient") {
                        delete toolOptions.componentOptions.columns;
                    }
                }
                if (view === "palette" && options.columns) {
                    toolOptions.componentOptions.columns = options.columns;
                }
            }

            if (toolOptions.component === "DropDownList" || toolOptions.component === "ComboBox") {
                if (!toolOptions.componentOptions) {
                    toolOptions.componentOptions = {};
                }

                if (!toolOptions.componentOptions.dataSource) {
                    if (that.options[name] && Array.isArray(that.options[name])) {
                        toolOptions.componentOptions.dataSource = that.options[name];
                    } else {
                        toolOptions.componentOptions.dataSource = options.items;
                    }
                }
            }
            if (toolOptions.template) {
                delete toolOptions.type;
            }

            shortcuts = this._shortcutSequence(name);

            if (shortcuts !== "") {
                if (!toolOptions.attributes) {
                    toolOptions.attributes = {};
                }

                toolOptions.attributes["aria-keyshortcuts"] = shortcuts;
            }

            flatDefaultTools[name] = toolOptions;

            return [flatDefaultTools, name];
        },

        _refreshTools: function() {
            var that = this,
                range = that.getRange(),
                editorNS = kendo.ui.editor,
                rangeUtils = editorNS.RangeUtils,
                nodes = rangeUtils.textNodes(range),
                immutables = that.options.immutables,
                immutablesContext = that._immutablesContext(range),
                defaultTools = that.tools;

            nodes = editorNS.Dom.filterBy(nodes, editorNS.Dom.htmlIndentSpace, true);

            if (!nodes.length) {
                nodes = [range.startContainer];
            }

            that.toolbar.element.find("[data-command]")
                .filter((i, el) => $(el).find("[data-command]").length === 0)
                .each(function() {
                    var toolName = this.getAttribute("data-command"),
                        tool = defaultTools[toolName],
                        ui;

                    if (tool) {
                        ui = $(this);

                        if (tool.update) {
                            tool.update(ui, nodes, that.undoRedoStack);
                        }

                        if (immutables) {
                            that._updateImmutablesState(tool, ui, immutablesContext);
                        }
                    }
                });
        },

        _registerHandler: function(element, type, handler) {
            var editor = this;
            var NS = ".kendoEditor";
            var eventNames;
            var i;

            element = $(element);

            if (!this._handlers) {
                this._handlers = [];
            }

            if (element.length) {
                if ($.isPlainObject(type)) {
                    for (var t in type) {
                        if (type.hasOwnProperty(t)) {
                            this._registerHandler(element, t, type[t]);
                        }
                    }
                } else {
                    //map the original event to handle interaction on mobile platforms
                    eventNames = kendo.applyEventMap(type).split(" ");

                    for (i = 0; i < eventNames.length; i++) {
                        editor._handlers.push({
                            element: element,
                            type: eventNames[i] + NS,
                            handler: handler
                        });

                        element.on(eventNames[i] + NS, handler);
                    }
                }
            }
        },

        _runPostContentKeyCommands: function(e) {
            const range = this.getRange();
            const tools = this.keyboard.toolsFromShortcut(this.tools, e);

            for (var i = 0; i < tools.length; i++) {
                var tool = tools[i];
                var o = tool.options;
                if (!o.keyPressCommand) {
                    continue;
                }

                var cmd = new o.command({ range: range });
                if (cmd.changesContent()) {
                    this.keyboard.endTyping(true);
                    this.exec(tool.name);
                }
            }

            this._wrapSingleTextNodeInParagraph();

            this._handleSpaceAndNewLines();
        },

        _wrapSingleTextNodeInParagraph: function() {
            const childNodes = this.body.childNodes;
            const dom = kendo.ui.editor.Dom;

            if (this.textarea) {
                return;
            }

            if (childNodes.length === 1 && childNodes[0].nodeType === dom.nodeTypes.TEXT_NODE) {
                dom.wrap(childNodes[0], this.document.createElement("p"));
            }
        },

        _handleSpaceAndNewLines: function() {
            const that = this,
                refreshDelay = that.options.formattingMarksRefreshDelay,
                editorNS = kendo.ui.editor,
                applyFormattingMarks = editorNS.applyFormattingMarks,
                clearFormattingMarksOverlay = editorNS.clearFormattingMarksOverlay;

            if (that._toggledFormattingMarks) {
                if (that._formattingMarksOverlay.children().length) {
                    clearFormattingMarksOverlay(that);
                }

                if (refreshDelay) {
                    clearTimeout(that._formattingMarksTimeout);
                    that._formattingMarksTimeout = setTimeout(() => applyFormattingMarks(that), refreshDelay);
                } else {
                    applyFormattingMarks(that);
                }

            }
        },

        _selectionChange: function() {
            this._selectionStarted = false;
            this.saveSelection();
            this.trigger("select", {});
        },

        _setOptionsTools: function(optionsTools) {
            var that = this,
                toolbar = that.toolbar,
                toolbarContainer = toolbar.element,
                tools, defaults, toolbarOptions;

            toolbar.destroy();
            toolbarContainer.empty();

            that.tools = [];
            [tools, defaults] = that._processToolbarItems(optionsTools);
            that._setNativeTools();

            toolbarOptions = {
                tools: tools,
                defaultTools: defaults,
                parentMessages: that.options.messages,
                resizable: that.options.resizable === true || Boolean(that.options.resizable && that.options.resizable.toolbar && that.options.resizable.toolbar?.mode !== "none"),
                toggle: that._handleToolbarClick.bind(this),
                click: that._handleToolbarClick.bind(this),
                overflow: $.isPlainObject(that.options.resizable?.toolbar) ? that.options.resizable.toolbar : undefined,
            };

            that.toolbar = new kendo.ui.ToolBar(toolbarContainer, toolbarOptions);

            that._bindTools();
        },

        _setNativeTools: function() {
            var defaultTools = kendo.deepExtend({}, kendo.ui.Editor.defaultTools),
                i;

            for (i = 0; i < NATIVE_TOOLS.length; i++) {
                if (!this.tools[NATIVE_TOOLS[i]]) {
                    this.tools[NATIVE_TOOLS[i]] = defaultTools[NATIVE_TOOLS[i]];
                }
            }
        },

        _setupHeight: function(type) {
            var that = this;

            if (type == "textarea") {
                setTimeout(function() {
                    var heightStyle = that.wrapper[0].style.height;
                    var expectedHeight = parseInt(heightStyle, 10);
                    var actualHeight = that.wrapper.height();
                    if (heightStyle.indexOf("px") > 0 && !isNaN(expectedHeight) && actualHeight > expectedHeight) {
                        that.wrapper.height(expectedHeight - (actualHeight - expectedHeight));
                    }
                });
            }
        },

        _setupKeyboard: function() {
            var that = this,
                editorNS = kendo.ui.editor;

            that.keyboard = new editorNS.Keyboard([
                new editorNS.BackspaceHandler(that),
                new editorNS.TypingHandler(that),
                new editorNS.SystemHandler(that),
                new editorNS.SelectAllHandler(that)
            ]);
        },

        _setupType: function(element) {
            var domElement = element[0],
                type = kendo.ui.editor.Dom.name(domElement),
                id = domElement.id,
                toolbarContainer;

            if (!id) {
                id = kendo.guid();
                domElement.setAttribute("id", id);
            }

            if (type == "textarea") {
                this._wrapTextarea();

                toolbarContainer = this.wrapper.find(".k-editor-toolbar");
                toolbarContainer.attr("aria-controls", id);
            } else {
                $(domElement).attr("contenteditable", true).addClass("k-editor k-editor-inline");
                toolbarContainer = $('<div class="k-editor-toolbar"/>').insertBefore(element);
            }

            return toolbarContainer;
        },

        _isInlineEditor: function() {
            return this.element.hasClass("k-editor-inline");
        },

        _shortcutSequence: function(name) {
            var tool = kendo.ui.Editor.defaultTools[name],
                res = "",
                options;

            if (!tool || !tool.options) {
                return res;
            }

            options = tool.options;

            if (options.ctrl) {
                res += "Ctrl+";
            }

            if (options.shift) {
                res += "Shift+";
            }

            if (options.alt) {
                res += "Alt+";
            }

            if (options.key) {
                res += options.key;
            }

            return res;
        },

        _spellCorrect: function(editor) {
            var beforeCorrection;
            var falseTrigger = false;

            this._registerHandler(editor.body, {
                "contextmenu": function() {
                    editor.one("select", function() {
                        beforeCorrection = null;
                    });

                    editor._spellCorrectTimeout = setTimeout(function() {
                        beforeCorrection = new kendo.ui.editor.RestorePoint(editor.getRange(), editor.body);
                        falseTrigger = false;
                    }, 10);
                },
                "input": function() {
                    if (!beforeCorrection) {
                        return;
                    }

                    if (kendo.support.browser.mozilla && !falseTrigger) {
                        falseTrigger = true;
                        return;
                    }

                    kendo.ui.editor._finishUpdate(editor, beforeCorrection);
                }
            });
        },

        _togglePlaceholder: function(show) {
            var that = this,
                body = that.body,
                $body = $(body),
                placeholder = that.options.placeholder;

            if (!that.textarea || !placeholder) {
                return;
            }

            $body.attr('aria-label', function() {
                return show ? placeholder : "";
            });

            $body.toggleClass(PLACEHOLDER_CLASS, show);
        },

        _toolbarFocused: function() {
            var active = kendo._activeElement(),
                popup = this.windowInstance,
                overflowPopup = this.overflowPopup;

            return (popup && $(active).closest(popup.wrapper).length) ||
                (overflowPopup && overflowPopup.visible());
        },

        _updateImmutablesState: function(tool, ui, immutablesContext) {
            let toolbar = this.toolbar,
                name = tool.name,
                overflowAnchor = toolbar.overflowAnchor,
                uiElement = ui.closest("[ref-toolbar-tool]"),
                trackImmutables = tool.options.trackImmutables,
                toolInOverflowMenuIsHidden = true,
                editorNS = kendo.ui.editor;

            if (trackImmutables === undefined) {
                trackImmutables = $.inArray(name, editorNS.Immutables.toolsToBeUpdated) > -1;
            }

            if (overflowAnchor && !overflowAnchor.hasClass("k-hidden")) {
                const overflowMenu = toolbar.overflowMenu,
                    toolInOverflowMenu = overflowMenu?.element.find(`[data-command="${tool.options.name}"]`);

                toolInOverflowMenuIsHidden = toolInOverflowMenu?.hasClass("k-hidden");
            }

            if (trackImmutables) {
                if (immutablesContext) {
                    toolbar.hide(uiElement);
                } else if (toolInOverflowMenuIsHidden) {
                    toolbar.show(uiElement);
                }
            }
        },

        _wrapTextarea: function() {
            var that = this,
                textarea = that.element,
                w = textarea[0].style.width,
                h = textarea[0].style.height,
                template = EditorUtils.editorWrapperTemplate(),
                editorWrap = $(template).insertBefore(textarea),
                editArea = editorWrap.find(".k-editable-area");

            editorWrap.css({
                "width": w,
                "height": h
            });

            textarea.attr("autocomplete", "off")
                .appendTo(editArea).addClass("k-content k-raw-content").css("display", "none");

            that.textarea = textarea;
            that.wrapper = editorWrap;
        },

        // Resizing methods
        _resizable: function() {
            let that = this;
            let resizable = this.options.resizable;
            let isResizable = $.isPlainObject(resizable) ? (resizable.content === undefined || resizable.content === true) : resizable;
            let resizableMin = $.isPlainObject(resizable.min) ? resizable.min : { minHeight: resizable.min };
            let resizableMax = $.isPlainObject(resizable.max) ? resizable.max : { maxHeight: resizable.max };

            if (isResizable && this.textarea) {
                that.wrapper.addClass('k-editor-resizable');

                if (resizableMin.minHeight) {
                    that.wrapper.css("min-height", resizableMin.minHeight);
                }

                if (resizableMax.maxHeight) {
                    that.wrapper.css("max-height", resizableMax.maxHeight);
                }

                if (resizableMin.minWidth) {
                    that.wrapper.css("min-width", resizableMin.minWidth);
                }

                if (resizableMax.maxWidth) {
                    that.wrapper.css("max-width", resizableMax.maxWidth);
                }

                that.resizeObserver = new ResizeObserver(() => {
                    clearTimeout(that._resizeTimeOut);

                    that._resizeTimeOut = setTimeout(function() {
                        that.toolbar.resize();
                        that._handleSpaceAndNewLines();
                    }, 50);

                });
                that.resizeObserver.observe(that.wrapper[0]);
            }
        },

        _destroyResizings: function() {
            var editor = this;

            editor._destroyElementResizing();
            kendo.ui.editor.ElementResizingFactory.current.dispose(editor);
            editor._destroyRowResizing();
            kendo.ui.editor.RowResizing.dispose(editor);
            editor._destroyColumnResizing();
            kendo.ui.editor.ColumnResizing.dispose(editor);
        },

        _initializeElementResizing: function() {
            var editor = this;

            kendo.ui.editor.ElementResizingFactory.current.create(editor);

            editor._showElementResizeHandlesProxy = editor._showElementResizeHandles.bind(editor);
            editor.bind(SELECT, editor._showElementResizeHandlesProxy);
        },

        _destroyElementResizing: function() {
            var editor = this;
            var elementResizing = editor.elementResizing;

            if (elementResizing) {
                elementResizing.destroy();
                editor.elementResizing = null;
            }

            if (editor._showElementResizeHandlesProxy) {
                editor.unbind(SELECT, editor._showElementResizeHandlesProxy);
            }
        },

        _showElementResizeHandles: function() {
            var editor = this;
            var elementResizing = editor.elementResizing;

            if (elementResizing && elementResizing.element && elementResizing.element.parentNode) {
                elementResizing.showResizeHandles();
            } else if (elementResizing && (!elementResizing.element || !elementResizing.element.parentNode)) {
                editor._destroyElementResizing();
            }
        },

        _initializeColumnResizing: function() {
            kendo.ui.editor.ColumnResizing.create(this);
        },

        _destroyColumnResizing: function() {
            var editor = this;

            if (editor.columnResizing) {
                editor.columnResizing.destroy();
                editor.columnResizing = null;
            }
        },

        _initializeRowResizing: function() {
            kendo.ui.editor.RowResizing.create(this);
        },

        _destroyRowResizing: function() {
            var editor = this;

            if (editor.rowResizing) {
                editor.rowResizing.destroy();
                editor.rowResizing = null;
            }
        },

        // ToolBar Window methods
        _getWindowWidth: function() {
            var that = this,
                wrapper = that.windowInstance.wrapper,
                editorElement = that.element;

            return outerWidth(editorElement) - parseInt(wrapper.css("border-left-width"), 10) - parseInt(wrapper.css("border-right-width"), 10);
        },

        _hideWindow: function() {
            if (this.windowInstance) {
                this.windowInstance.close();
            }
        },

        _overlapsWindow: function(box) {
            var toolbarWrapper = this.windowInstance.wrapper,
                toolbarWrapperOffset = toolbarWrapper.offset(),
                toolbarWrapperLeft = toolbarWrapperOffset.left,
                toolbarWrapperTop = toolbarWrapperOffset.top,
                boxOffset = box.offset(),
                boxOffsetLeft = boxOffset.left,
                boxOffsetTop = boxOffset.top;

            return !(boxOffsetLeft + box.width() < toolbarWrapperLeft ||
                boxOffsetLeft > toolbarWrapperLeft + toolbarWrapper.width() ||
                boxOffsetTop + box.height() < toolbarWrapperTop ||
                boxOffsetTop > toolbarWrapperTop + toolbarWrapper.height());
        },

        _showWindow: function() {
            var that = this,
                windowInstance = that.windowInstance,
                wrapper, editorElement, editorOffset,
                browser = kendo.support.browser;

            if (windowInstance) {
                wrapper = windowInstance.wrapper;
                editorElement = that.element;

                if (!wrapper.is(":visible") || !that.windowInstance.options.visible) {

                    if (!wrapper[0].style.width) {
                        wrapper.width(this._getWindowWidth());
                    }

                    // track content position when other parts of page change
                    if (!windowInstance._moved) {
                        editorOffset = editorElement.offset();
                        wrapper.css({
                            top: Math.max(0, parseInt(editorOffset.top, 10) - outerHeight(wrapper) - parseInt(that.windowInstance.element.css("padding-bottom"), 10)),
                            left: Math.max(0, parseInt(editorOffset.left, 10))
                        });
                    }

                    if ((browser.msie || browser.edge) && that._overlapsWindow(editorElement)) {
                        setTimeout(function() {
                            windowInstance.open();
                        }, 0);
                    } else {
                        windowInstance.open();
                    }
                }
            }
        }
    });

    Editor.defaultTools = {};

    kendo.ui.plugin(Editor);

    var Tool = Class.extend({
        init: function(options) {
            this.options = options;
        },

        command: function(commandArguments) {
            return new this.options.command(commandArguments);
        },

        update: $.noop
    });

    Tool.exec = function(editor, name, value) {
        editor.exec(name, { value: value });
    };

    var UndoTool = Tool.extend({
        command: $.noop,
        update: function(ui, nodes, undoRedoStack) {
            var that = this,
                name = that.options.name,
                toolbar = ui.closest(".k-toolbar").data("kendoToolBar");

            switch (name) {
                case "undo":
                    toolbar.enable(ui, undoRedoStack.canUndo());
                    break;
                case "redo":
                    toolbar.enable(ui, undoRedoStack.canRedo());
                    break;
                default:
                    break;
            }

            ui.removeAttr("disabled");
        }
    });

    EditorUtils.registerTool("separator", new Tool({ ui: { type: "separator" } }));
    EditorUtils.registerTool("undo", new UndoTool({ key: "Z", ctrl: true }));
    EditorUtils.registerTool("redo", new UndoTool({ key: "Y", ctrl: true }));

    // Exports ================================================================

    var emptyElementContent = '\ufeff';
    var emptyTableCellContent = emptyElementContent;

    if (browser.msie || browser.edge) {
        emptyTableCellContent = emptyElementContent = "&nbsp;";
    }

    extend(kendo.ui, {
        editor: {
            EditorUtils: EditorUtils,
            Tool: Tool,
            emptyElementContent: emptyElementContent,
            emptyTableCellContent: emptyTableCellContent
        }
    });

    if (kendo.PDFMixin) {
        kendo.PDFMixin.extend(Editor.prototype);
        Editor.prototype._drawPDF = function() {
            return kendo.drawing.drawDOM(this.body, this.options.pdf);
        };
        Editor.prototype.saveAsPDF = function() {
            var progress = new $.Deferred();
            var promise = progress.promise();
            var args = { promise: promise };

            if (this.trigger("pdfExport", args)) {
                return;
            }

            var options = this.options.pdf;

            this._drawPDF(progress)
                .then(function(root) {
                    return kendo.drawing.exportPDF(root, options);
                })
                .done(function(dataURI) {
                    kendo.saveAs({
                        dataURI: dataURI,
                        fileName: options.fileName,
                        proxyURL: options.proxyURL,
                        proxyTarget: options.proxyTarget,
                        forceProxy: options.forceProxy
                    });
                    progress.resolve();
                })
                .fail(function(err) {
                    progress.reject(err);
                });

            return promise;
        };
    }

})(window.kendo.jQuery);
