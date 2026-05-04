/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { STYLES, REFERENCES, NS, INPUT, KEYDOWN, FOCUS, BLUR, LINE_MODE } from "../constants";
const DEFAULT_MESSAGES = {
    placeholder: "Type a message...",
    actionButton: "Send",
    actionButtonLoading: "Stop",
    messageBoxTitle: "Message"
};
export class InputManager {
    constructor(context) {
        this.inputInstance = null;
        this.singleRowHeight = 0;
        this.singleLineWidth = 0;
        this.initialHeight = 0;
        this.minMultiRowHeight = 0;
        this.multiline = false;
        this.context = context;
    }
    init() {
        const inputContainer = this.context.wrapper.find(`[${REFERENCES.input}]`);
        const mode = this.context.getMode();
        const inputElement = this.createInputElement(mode);
        inputContainer.replaceWith(inputElement);
        const value = this.context.getOptions().value || "";
        if (value) {
            inputElement.val(value);
        }
        this.captureInitialDimensions();
        this.attachInputEvents();
    }
    getElement() {
        var _a;
        return ((_a = this.inputInstance) === null || _a === void 0 ? void 0 : _a.element) || null;
    }
    getValue() {
        return this.inputInstance ? this.inputInstance.value() : "";
    }
    setValue(value) {
        if (this.inputInstance) {
            this.inputInstance.value(value);
        }
        this.updateAutoResize();
        this.updateMultiResize();
    }
    enable(enabled) {
        if (this.inputInstance) {
            this.inputInstance.enable(enabled);
        }
    }
    setReadonly(readonly) {
        var _a;
        if ((_a = this.inputInstance) === null || _a === void 0 ? void 0 : _a.element) {
            this.inputInstance.element.prop("readonly", readonly);
        }
    }
    focus() {
        if (this.inputInstance) {
            this.inputInstance.focus();
        }
    }
    blur() {
        var _a;
        if ((_a = this.inputInstance) === null || _a === void 0 ? void 0 : _a.element) {
            this.inputInstance.element.blur();
        }
    }
    isMultiline() {
        return this.multiline;
    }
    updateAutoResize() {
        const mode = this.context.getMode();
        if (mode !== LINE_MODE.AUTO || !this.inputInstance) {
            return;
        }
        const textarea = this.inputInstance.element;
        if (!textarea.is("textarea")) {
            return;
        }
        const el = textarea[0];
        if (!this.multiline) {
            const needsExpansion = el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight;
            if (needsExpansion) {
                this.updateMultilineState(true);
                this.updateHeight();
            }
        }
        else {
            this.updateHeight();
            if (this.shouldCollapse()) {
                this.updateMultilineState(false);
                textarea.css("height", "");
            }
        }
    }
    updateMultiResize() {
        const mode = this.context.getMode();
        if (mode !== LINE_MODE.MULTI || !this.inputInstance) {
            return;
        }
        const textarea = this.inputInstance.element;
        if (!textarea.is("textarea")) {
            return;
        }
        const el = textarea[0];
        const options = this.context.getOptions();
        textarea.css("overflow", "hidden");
        textarea.css("height", this.initialHeight + "px");
        const scrollHeight = el.scrollHeight;
        const newHeight = this.calculateMultiHeight(scrollHeight, options.maxTextAreaHeight);
        if (newHeight === options.maxTextAreaHeight) {
            textarea.css("overflow", "");
        }
        textarea.css("height", newHeight + "px");
    }
    updatePlaceholder() {
        var _a;
        const options = this.context.getOptions();
        const messages = $.extend({}, DEFAULT_MESSAGES, options.messages);
        const placeholder = options.placeholder || messages.placeholder;
        if ((_a = this.inputInstance) === null || _a === void 0 ? void 0 : _a.element) {
            this.inputInstance.element.attr("placeholder", placeholder);
        }
    }
    destroy() {
        if (this.inputInstance) {
            this.inputInstance.element.off(NS);
            this.inputInstance.destroy();
            this.inputInstance = null;
        }
    }
    createInputElement(mode) {
        const options = this.context.getOptions();
        const messages = $.extend({}, DEFAULT_MESSAGES, options.messages);
        const title = options.title ? `title="${options.title}"` : "";
        const readonly = options.readonly ? "readonly" : "";
        let inputElement;
        if (mode === LINE_MODE.SINGLE) {
            inputElement = $(`<input type="text" class="${STYLES.promptBoxInput} ${STYLES.inputInner}" placeholder="${options.placeholder || messages.placeholder}" autocomplete="off" ${title} ${readonly} />`);
            this.inputInstance = {
                element: inputElement,
                value: (val) => {
                    if (val === undefined) {
                        return inputElement.val();
                    }
                    inputElement.val(val);
                },
                enable: (enabled) => {
                    inputElement.prop("disabled", !enabled);
                },
                focus: () => inputElement.trigger("focus"),
                destroy: () => { }
            };
        }
        else {
            const rows = mode === LINE_MODE.AUTO ? 1 : (options.rows || 1);
            inputElement = $(`<textarea class="${STYLES.promptBoxTextarea} ${STYLES.inputInner}" placeholder="${options.placeholder || messages.placeholder}" rows="${rows}" aria-multiline="true" ${title} ${readonly}></textarea>`);
            if (options.maxTextAreaHeight) {
                inputElement.css("max-height", options.maxTextAreaHeight + "px");
                inputElement.css("overflow-y", "auto");
            }
            this.inputInstance = {
                element: inputElement,
                value: (val) => {
                    if (val === undefined) {
                        return inputElement.val();
                    }
                    inputElement.val(val);
                },
                enable: (enabled) => {
                    inputElement.prop("disabled", !enabled);
                },
                focus: () => inputElement.trigger("focus"),
                destroy: () => { }
            };
        }
        return inputElement;
    }
    captureInitialDimensions() {
        const mode = this.context.getMode();
        if (mode === LINE_MODE.SINGLE || !this.inputInstance) {
            return;
        }
        const textarea = this.inputInstance.element;
        if (!textarea.is("textarea")) {
            return;
        }
        const el = textarea[0];
        const computedStyle = window.getComputedStyle(el);
        const lineHeight = parseFloat(computedStyle.lineHeight) || 20;
        const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
        const paddingBottom = parseFloat(computedStyle.paddingBottom) || 0;
        const options = this.context.getOptions();
        const rows = options.rows || 1;
        this.singleRowHeight = lineHeight + paddingTop + paddingBottom;
        this.initialHeight = el.offsetHeight || this.singleRowHeight;
        this.singleLineWidth = el.offsetWidth;
        this.minMultiRowHeight = lineHeight * rows + paddingTop + paddingBottom;
    }
    shouldCollapse() {
        if (!this.inputInstance || this.singleLineWidth <= 0) {
            return false;
        }
        const textarea = this.inputInstance.element;
        if (!textarea.is("textarea")) {
            return false;
        }
        const el = textarea[0];
        const currentHeight = el.style.height;
        textarea.css({
            overflow: "hidden",
            width: this.singleLineWidth + "px",
            whiteSpace: "nowrap",
            height: this.initialHeight + "px"
        });
        const fitsSingleLine = el.scrollWidth <= this.singleLineWidth && el.scrollHeight <= this.initialHeight;
        textarea.css({
            overflow: "",
            width: "",
            whiteSpace: ""
        });
        if (currentHeight) {
            textarea.css("height", currentHeight);
        }
        else {
            textarea.css("height", "");
        }
        return fitsSingleLine;
    }
    updateHeight() {
        const options = this.context.getOptions();
        if (!this.inputInstance) {
            return;
        }
        const textarea = this.inputInstance.element;
        if (!textarea.is("textarea")) {
            return;
        }
        const el = textarea[0];
        textarea.css("overflow", "hidden");
        textarea.css("height", this.initialHeight + "px");
        const scrollHeight = el.scrollHeight;
        const newHeight = options.maxTextAreaHeight
            ? Math.min(scrollHeight, options.maxTextAreaHeight)
            : scrollHeight;
        if (newHeight === options.maxTextAreaHeight) {
            textarea.css("overflow", "");
        }
        textarea.css("height", newHeight + "px");
    }
    calculateMultiHeight(scrollHeight, maxTextAreaHeight) {
        if (maxTextAreaHeight && scrollHeight > maxTextAreaHeight) {
            return maxTextAreaHeight;
        }
        return Math.max(this.minMultiRowHeight, scrollHeight);
    }
    updateMultilineState(isMultiline) {
        const mode = this.context.getMode();
        if (mode !== LINE_MODE.AUTO) {
            return;
        }
        if (this.multiline === isMultiline) {
            return;
        }
        const wasMultiline = this.multiline;
        this.multiline = isMultiline;
        if (isMultiline) {
            this.context.wrapper.addClass(STYLES.promptBoxMultiline);
        }
        else {
            this.context.wrapper.removeClass(STYLES.promptBoxMultiline);
        }
        this.context.callbacks.onMultilineStateChange(isMultiline, wasMultiline);
    }
    attachInputEvents() {
        if (!this.inputInstance) {
            return;
        }
        const inputElement = this.inputInstance.element;
        inputElement.on(INPUT + NS, () => {
            this.updateAutoResize();
            this.updateMultiResize();
            this.context.callbacks.onInput();
        });
        inputElement.on(KEYDOWN + NS, (e) => {
            this.context.callbacks.onKeyDown(e);
        });
        inputElement.on(FOCUS + NS, () => {
            this.context.callbacks.onFocus();
        });
        inputElement.on(BLUR + NS, () => {
            this.context.callbacks.onBlur();
        });
    }
}
