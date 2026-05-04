/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import '../command.js';

(function($) {

    const kendo = window.kendo,
        Editor = kendo.ui.editor,
        Tool = Editor.Tool,
        Command = Editor.Command,
        EditorUtils = Editor.EditorUtils,
        registerTool = EditorUtils.registerTool,
        DOM = Editor.Dom,
        SPACE_UNICODE = "\u00B7",
        NEW_LINE_UNICODE = "\u21B5";

    const createMark = function(range, document, character, scrollTop, index, scrollableWrapper) {
        const clonedRange = range.cloneRange(),
              container = clonedRange.startContainer;

        let domRect, x, y, textNode, parentRect;

        if (index !== undefined && index !== null) {
            clonedRange.setStart(container, index);
            clonedRange.setEnd(container, index + 1);
        }

        domRect = clonedRange.getBoundingClientRect();

        if (scrollableWrapper) {
            parentRect = scrollableWrapper.getBoundingClientRect();
            x = domRect.left - parentRect.left - domRect.width / 2;
            y = domRect.top - parentRect.top + domRect.height + scrollTop;
        } else {
            if (character === SPACE_UNICODE) {
                x = domRect.x - domRect.width / 4;
                y = domRect.y + domRect.height + scrollTop;
            } else if (character === NEW_LINE_UNICODE) {
                x = domRect.x;
                y = domRect.y + domRect.height + scrollTop;
            }
        }

        textNode = document.createElementNS("http://www.w3.org/2000/svg", "text");
        textNode.setAttributeNS(null, "x", x);
        textNode.setAttributeNS(null, "y", y);
        textNode.setAttributeNS(null, "fill", "#6098f2");
        textNode.setAttributeNS(null, "font-size", `${Math.ceil(domRect.height + domRect.width)}px`);
        textNode.textContent = character;

        return textNode;
    };

    const appendSvgMarks = function() {
        const that = this,
            range = that.getRange().cloneRange(),
            overlay = that._formattingMarksOverlay[0],
            fragment = that.document.createDocumentFragment(),
            document = that.document,
            isInlineEditor = that._isInlineEditor(),
            scrollableWrapper = isInlineEditor ? that.body.parentElement : null,
            scrollTop = isInlineEditor ? scrollableWrapper.scrollTop : document.documentElement.scrollTop;

        if (!that.body.firstChild && !that.body.lastChild) {
            return;
        }

        range.setStart(that.body.firstChild, 0);
        range.setEnd(that.body.lastChild, 0);

        const nodes = DOM.getTextNodes(that.body).concat($(that.body).find("br").toArray());

        nodes.forEach((node) => {
            if (node.nodeType === DOM.nodeTypes.TEXT_NODE) {
                range.selectNodeContents(node);

                for (let i = range.startOffset; i < range.endOffset; i++) {
                    if ((range.startContainer.textContent.charCodeAt(i) === 32 || range.startContainer.textContent.charCodeAt(i) === 160)) {
                        fragment.appendChild(createMark(range, document, SPACE_UNICODE, scrollTop, i, scrollableWrapper));
                    }
                }
            } else {
                range.selectNode(node);
                fragment.appendChild(createMark(range, document, NEW_LINE_UNICODE, scrollTop, null, scrollableWrapper));
            }
        });

        overlay.appendChild(fragment);
    };

    const clearFormattingMarksOverlay = function(editor) {
        editor._formattingMarksOverlay.empty();
    };

    const applyFormattingMarks = function(editor) {
        editor._updateFormattingMarksOverlayHeight();
        appendSvgMarks.call(editor);
    };

    const FormattingMarksCommand = Command.extend({
        exec: function() {
            const cmd = this,
                range = cmd.lockRange(true),
                editor = cmd.editor;

            if (!editor._toggledFormattingMarks) {
                editor._toggledFormattingMarks = true;
                editor._appendFormattingMarksOverlay();
                editor._appendFormattingMarksStyles();
                clearFormattingMarksOverlay(editor);
                applyFormattingMarks(editor);
            } else {
                editor._toggledFormattingMarks = false;
                editor._destroyFormattingMarksOverlay();
            }

            cmd.releaseRange(range);
        }
    });

    $.extend(Editor, {
        FormattingMarksCommand: FormattingMarksCommand,
        applyFormattingMarks: applyFormattingMarks,
        clearFormattingMarksOverlay: clearFormattingMarksOverlay
    });

    registerTool("formattingMarks", new Tool({ command: FormattingMarksCommand, icon: "paragraphMark", ui: { togglable: true } }));
})(window.kendo.jQuery);
