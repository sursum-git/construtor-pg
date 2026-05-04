/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.html.button.js";
import "../../kendo.icons.js";
import "../../kendo.promptbox.js";
import { Widget } from "../../core/base/widget.js";
import { NS, STYLES, REFERENCES, SCROLLING_DELTA, CLICK, DOT, RTL, EVENTS, SUGGESTED_ACTIONS_LAYOUT_MODE } from "../constants.js";
import { renderSuggestionsWithLayout } from "../templates/index.js";
import { PromptBox } from "../../promptbox/index.js";
/**
 * MessageBox handles the input area of the chat component.
 * Delegates text input and file attachments entirely to PromptBox.
 *
 * This is an internal collaborator that extends Widget for event handling.
 */
export class MessageBox extends Widget {
    get textAreaInstance() {
        return this.promptBoxInstance;
    }
    /**
     * Constructs a new MessageBox instance.
     */
    constructor(element, options, context) {
        super(element, $.extend(true, {}, MessageBox.options, options));
        this.events = [
            EVENTS.sendMessage,
            EVENTS.input,
            EVENTS.suggestionClick,
            EVENTS.replyMessageCloseButtonClick,
            EVENTS.fileSelect,
            EVENTS.fileRemove
        ];
        this._generating = false;
        this._typing = false;
        this._dragHandler = null;
        this.context = context;
        this.chatElement = context.chatElement;
        this._generating = false;
        this._initWrapper();
        this._initPromptBox();
        this._attachEvents();
        this._typing = false;
    }
    /**
     * Creates the wrapper element for the message box.
     */
    _initWrapper() {
        const options = this.options;
        this.wrapper = $(`<div class="${STYLES.messageBoxWrapper}"></div>`);
        if (options.suggestions.length) {
            const layoutMode = this._getSuggestionsLayoutMode(options);
            const suggestionsHtml = renderSuggestionsWithLayout(options.suggestions, layoutMode, options.dir === RTL);
            this.suggestions = this.wrapper.append(suggestionsHtml);
        }
        this.wrapper.find(`[${REFERENCES.leftScrollButton}]`).addClass(STYLES.disabled);
        this.chatElement.append(this.wrapper);
    }
    /**
     * Gets the effective suggestions layout mode, considering legacy options.
     */
    _getSuggestionsLayoutMode(options) {
        if (options.suggestionsLayoutMode) {
            return options.suggestionsLayoutMode;
        }
        if (options.suggestionsScrollable) {
            return SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLLBUTTONS;
        }
        return SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLL;
    }
    /**
     * Initializes the PromptBox component for message input.
     */
    _initPromptBox() {
        const options = this.options;
        const messages = options.messages || {};
        const messageBox = options.messageBox || {};
        const actionButtonOpts = options.actionButton;
        const fileAttachmentEnabled = options.fileAttachment !== false;
        const mode = messageBox.mode || "auto";
        const maxTextAreaHeight = messageBox.maxTextAreaHeight;
        const rows = messageBox.rows || 1;
        const actionButtonSettings = this._getActionButtonSettings(actionButtonOpts);
        const speechToTextSettings = this._getSpeechToTextSettings(options);
        const fileAttachmentSettings = this._getFileAttachmentSettings(options, fileAttachmentEnabled);
        const promptBox = new PromptBox(this.element, {
            mode: mode,
            maxTextAreaHeight: maxTextAreaHeight,
            rows: rows,
            placeholder: messages.placeholder,
            speechToTextButton: speechToTextSettings,
            actionButton: actionButtonSettings,
            fileSelectButton: fileAttachmentSettings,
            messages: {
                placeholder: messages.placeholder,
                actionButton: messages.sendButton || (actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.text) || messages.actionButton,
                actionButtonLoading: messages.sendButtonLoading || (actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.loadingText) || (actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.stopText) || messages.actionButtonLoading || messages.stopButton
            },
            input: this._input.bind(this),
            promptAction: this._promptBoxAction.bind(this),
            fileSelect: this._promptBoxFileSelect.bind(this),
            fileRemove: this._promptBoxFileRemove.bind(this)
        });
        promptBox.wrapper.appendTo(this.wrapper);
        this.promptBoxInstance = promptBox;
    }
    /**
     * Gets action button settings to pass to PromptBox.
     */
    _getActionButtonSettings(actionButtonOpts) {
        return {
            enable: true,
            icon: actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.icon,
            loadingIcon: (actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.loadingIcon) || (actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.stopIcon),
            fillMode: actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.fillMode,
            rounded: actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.rounded,
            size: actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.size,
            themeColor: actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.themeColor,
            text: actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.text,
            loadingText: (actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.loadingText) || (actionButtonOpts === null || actionButtonOpts === void 0 ? void 0 : actionButtonOpts.stopText)
        };
    }
    /**
     * Gets speech-to-text settings to pass to PromptBox.
     */
    _getSpeechToTextSettings(options) {
        if (options.speechToText === false) {
            return false;
        }
        if (this.context.utilsService.isObject(options.speechToText)) {
            return options.speechToText;
        }
        return null;
    }
    /**
     * Gets file attachment settings to pass to PromptBox.
     */
    _getFileAttachmentSettings(options, enabled) {
        if (!enabled) {
            return false;
        }
        if (this.context.utilsService.isObject(options.fileAttachment)) {
            return Object.assign({ showSpacer: true }, options.fileAttachment);
        }
        return { showSpacer: true };
    }
    /**
     * Handles PromptBox promptAction event.
     */
    _promptBoxAction(e) {
        e.preventDefault();
        const actionType = e.actionType;
        const text = e.value || "";
        const files = e.files || [];
        if (actionType === "stop") {
            const eventData = { generating: true };
            this.trigger(EVENTS.sendMessage, eventData);
            return;
        }
        if (!text.length && !files.length) {
            return;
        }
        const eventData = { message: { text, files } };
        this.trigger(EVENTS.sendMessage, eventData);
    }
    /**
     * Handles PromptBox fileSelect event.
     */
    _promptBoxFileSelect(e) {
        var _a;
        const files = ((_a = this.promptBoxInstance) === null || _a === void 0 ? void 0 : _a.files()) || [];
        this.trigger(EVENTS.fileSelect, { files });
    }
    /**
     * Handles PromptBox fileRemove event.
     */
    _promptBoxFileRemove(e) {
        this.trigger(EVENTS.fileRemove, { file: e.file, files: e.files });
    }
    /**
     * Attaches event handlers to message box elements.
     */
    _attachEvents() {
        this.wrapper
            .on(CLICK + NS, `[${REFERENCES.replyMessageCloseButton}]`, this._replyMessageCloseButtonClick.bind(this))
            .on(CLICK + NS, DOT + STYLES.suggestion, this._suggestionClick.bind(this))
            .on(CLICK + NS, `[${REFERENCES.leftScrollButton}]`, this._leftScrollButtonClick.bind(this))
            .on(CLICK + NS, `[${REFERENCES.rightScrollButton}]`, this._rightScrollButtonClick.bind(this));
        this._attachDragToScrollEvents();
    }
    /**
     * Attaches drag-to-scroll events for suggestions.
     */
    _attachDragToScrollEvents() {
        const scrollContainer = this.wrapper.find(DOT + STYLES.suggestionsScroll);
        if (!scrollContainer.length) {
            return;
        }
        this._dragHandler = this.context.domUtilsService.createDragToScrollHandler(scrollContainer, {
            namespace: NS + ".chatMessageBoxDrag",
            captureElement: this.chatElement
        });
        this._dragHandler.attach();
    }
    /**
     * Gets the current files from the PromptBox.
     */
    getFiles() {
        var _a;
        return ((_a = this.promptBoxInstance) === null || _a === void 0 ? void 0 : _a.files()) || [];
    }
    /**
     * Sets files on the PromptBox.
     */
    setFiles(files) {
        var _a;
        (_a = this.promptBoxInstance) === null || _a === void 0 ? void 0 : _a.files(files);
    }
    /**
     * Clears all file attachments.
     */
    clearFiles() {
        var _a;
        (_a = this.promptBoxInstance) === null || _a === void 0 ? void 0 : _a.clearFiles();
    }
    _getPrefixContainer() {
        let header = this.promptBoxInstance.wrapper.find(".k-prompt-box-header");
        if (header.length === 0) {
            header = $('<div class="k-prompt-box-header" ref-promptbox-header><div ref-promptbox-attachments-host></div></div>');
            this.promptBoxInstance.wrapper.prepend(header);
        }
        return header;
    }
    /**
     * Sets a reply message in the message box interface.
     */
    setReplyMessage(message, isOwnMessage) {
        const options = this.options;
        const replyContainer = this.getReplyMessageContainer();
        const replyTemplate = options.messageReferenceTemplate({
            text: message.text,
            files: message.files,
            isOwnMessage,
            renderCloseButton: true,
            renderFileMenuButton: false
        });
        const prefixContainer = this._getPrefixContainer();
        prefixContainer.removeClass("k-hidden");
        if (replyContainer.length === 0) {
            prefixContainer.prepend(replyTemplate);
        }
        else {
            replyContainer.replaceWith(replyTemplate);
        }
    }
    /**
     * Removes the reply message from the message box interface.
     */
    removeReplyMessage() {
        const replyContainer = this.getReplyMessageContainer();
        if (replyContainer.length > 0) {
            replyContainer.remove();
            this._updateHeaderVisibility();
        }
    }
    _updateHeaderVisibility() {
        var _a;
        const prefixContainer = this._getPrefixContainer();
        const files = (_a = this.promptBoxInstance) === null || _a === void 0 ? void 0 : _a.files();
        const hasFiles = files && files.length > 0;
        const hasReply = this.getReplyMessageContainer().length > 0;
        if (!hasFiles && !hasReply) {
            prefixContainer.addClass("k-hidden");
        }
    }
    /**
     * Gets the reply message container element.
     */
    getReplyMessageContainer() {
        return this._getPrefixContainer().find(`[${REFERENCES.messageReferenceReplyWrapper}]`);
    }
    loading(value) {
        if (value === undefined) {
            return this._generating;
        }
        this._generating = value;
        this.promptBoxInstance.loading(value);
    }
    value(newValue) {
        if (newValue === undefined) {
            return this._getInputValue();
        }
        this._setInputValue(newValue);
    }
    // Event handlers
    _input(e) {
        var _a;
        const currentValue = (_a = e === null || e === void 0 ? void 0 : e.value) !== null && _a !== void 0 ? _a : this._getInputValue();
        this.trigger(EVENTS.input, { value: currentValue });
    }
    _replyMessageCloseButtonClick(e) {
        e.preventDefault();
        this.trigger(EVENTS.replyMessageCloseButtonClick);
    }
    _suggestionClick(e) {
        e.preventDefault();
        const options = this.options;
        const text = $(e.target).closest(DOT + STYLES.suggestion).text();
        this.trigger(EVENTS.suggestionClick, { text, behavior: options.suggestionsBehavior || "send" });
        if (options.suggestionsBehavior === "insert") {
            this._setInputValue(text);
        }
    }
    _getInputValue() {
        return this.promptBoxInstance.value();
    }
    _setInputValue(value) {
        this.promptBoxInstance.value(value);
    }
    _leftScrollButtonClick(e) {
        e.preventDefault();
        const button = $(e.currentTarget);
        const scrollableElement = this.wrapper.find(`${DOT}${STYLES.suggestionsScroll}`);
        const isRtl = this.options.dir === RTL;
        const position = this.context.domUtilsService.scrollByDelta(scrollableElement, isRtl ? SCROLLING_DELTA : -SCROLLING_DELTA);
        this.wrapper.find(`[${REFERENCES.rightScrollButton}]`).removeClass(STYLES.disabled);
        if (position.atStart) {
            button.addClass(STYLES.disabled);
        }
    }
    _rightScrollButtonClick(e) {
        e.preventDefault();
        const button = $(e.currentTarget);
        const scrollableElement = this.wrapper.find(`${DOT}${STYLES.suggestionsScroll}`);
        const isRtl = this.options.dir === RTL;
        const position = this.context.domUtilsService.scrollByDelta(scrollableElement, isRtl ? -SCROLLING_DELTA : SCROLLING_DELTA);
        this.wrapper.find(`[${REFERENCES.leftScrollButton}]`).removeClass(STYLES.disabled);
        if (position.atEnd) {
            button.addClass(STYLES.disabled);
        }
    }
    destroy() {
        if (this._dragHandler) {
            this._dragHandler.destroy();
        }
        if (this.promptBoxInstance) {
            this.promptBoxInstance.destroy();
        }
        this.wrapper.remove();
    }
}
MessageBox.options = {
    name: "MessageBox"
};
