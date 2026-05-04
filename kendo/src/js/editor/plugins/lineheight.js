/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./inlineformat.js";
import './formatblock.js';

(function($) {

    const kendo = window.kendo,
        extend = $.extend,
        Editor = kendo.ui.editor,
        formats = kendo.ui.Editor.fn.options.formats,
        dom = Editor.Dom,
        Tool = Editor.Tool,
        DelayedExecutionTool = Editor.DelayedExecutionTool,
        BlockFormatter = Editor.BlockFormatter,
        FormatCommand = Editor.FormatCommand,
        GreedyInlineFormatFinder = Editor.GreedyInlineFormatFinder,
        EditorUtils = Editor.EditorUtils,
        registerTool = EditorUtils.registerTool,
        registerFormat = EditorUtils.registerFormat,
        RangeUtils = Editor.RangeUtils,
        MOUSEDOWN_NS = "mousedown.kendoEditor",
        KEYDOWN_NS = "keydown.kendoEditor";

    const LineHeightCommand = FormatCommand.extend({
        exec: function() {
            const cmd = this,
                range = cmd.lockRange(true),
                nodes = dom.filterBy(RangeUtils.nodes(range), dom.htmlIndentSpace, true);

            cmd.formatter.format[0].attr.style.lineHeight = cmd.options.value.lineHeight;
            cmd.formatter.apply(nodes);
            cmd.releaseRange(range);
        }
    });

    const LineHeightFormatTool = DelayedExecutionTool.extend({
        init: function(options) {
            Tool.fn.init.call(this, options);

            this.type = "kendoComboBox";
            this.format = [{ tags: ["p", "lineHeight"] }];
            this.finder = new GreedyInlineFormatFinder(this.format, options.cssAttr, options.fontAttr, options.defaultValue);
        },

        command: function(commandArguments) {
            var that = this;

            return new LineHeightCommand(extend(commandArguments, {
                formatter: function() {
                    return new BlockFormatter(that.options.format);
                }
            }));
        },

        initialize: function(ui, editor) {
            let tool = this,
                component = ui.getKendoComboBox();

            tool.editor = editor;

            component.bind("change", this.changeHandler.bind(this));
            component.bind("close", this.closeHandler.bind(this));

            component.wrapper.on(MOUSEDOWN_NS, ".k-input-button,.k-input-inner", this.mouseDownHandler.bind(this))
                .on(KEYDOWN_NS, this.keyDownHandler.bind(this));
        },

        changeHandler: function(e) {
            this._exec(e.sender.value());
        },

        closeHandler: function() {
            let that = this,
                editor = that.editor;

            setTimeout(function() {
                editor._deleteSavedRange();
                that.currentRange = null;
            },0);
        },

        mouseDownHandler: function() {
            let newRange = this.editor.getRange();

            this.currentRange = this.editor._containsRange(newRange) ? newRange : this.currentRange;
        },

        keyDownHandler: function(e) {
            if (e.keyCode === kendo.keys.ENTER) {
                this.editor._deleteSavedRange();
                this.currentRange = null;
                e.preventDefault();
            }
        },

        _exec: function(value) {
            let editor = this.editor;
            editor._range = this.currentRange;
            if (value) {
                Tool.exec(this.editor, this.options.name, { lineHeight: value });
            }
        },
    });

    $.extend(Editor, {
        LineHeightCommand: LineHeightCommand,
        LineHeightFormatTool: LineHeightFormatTool
    });

    registerFormat("lineHeight", [
        { tags: dom.blockElements, attr: { style: { lineHeight: "normal" } } }
    ]);

    registerTool("lineHeight", new LineHeightFormatTool({
        cssAttr: "line-height",
        format: formats.lineHeight,
        defaultValue: 1.15,
        ui: {
            type: "component",
            component: "ComboBox",
            componentOptions: {
                value: 1.15,
                dataTextField: "text",
                dataValueField: "value",
                prefixOptions: {
                    icon: "paragraphHeight"
                },
                autoSize: true,
                dataSource: [
                    { text: "1", value: "1" },
                    { text: "1.15", value: "1.15" },
                    { text: "1.5", value: "1.5" },
                    { text: "2", value: "2" },
                    { text: "2.5", value: "2.5" },
                ]
            }
        }
    }));
})(window.kendo.jQuery);
