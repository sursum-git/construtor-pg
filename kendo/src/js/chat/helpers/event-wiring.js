/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { NS, STYLES, REFERENCES, EVENTS, CLICK, KEYDOWN, DOT } from "../constants";
export class EventWiring {
    constructor(context) {
        this.context = context;
    }
    attach() {
        this._attachViewEvents();
        this._attachMessageBoxEvents();
        this._attachContextMenuEvents();
        this._attachWrapperDomEvents();
    }
    _attachViewEvents() {
        const ctx = this.context;
        ctx.view
            .bind("suggestedActionClick", (args) => {
            const options = ctx.options;
            if (options.suggestionsBehavior === "insert") {
                ctx.value(args.text);
                ctx.trigger(EVENTS.suggestionClick, { text: args.text });
            }
            else {
                const message = { text: args.text, files: [] };
                ctx.trigger(EVENTS.sendMessage, { message });
                ctx.postMessage(message);
            }
        })
            .bind("messageToolbarExecute", (args) => {
            const message = ctx.dataItem(args.message);
            ctx.commandExecute({ type: args.type, message: args.message });
            ctx.trigger(EVENTS.toolbarAction, { type: args.type, message });
        })
            .bind("fileMenuExecute", (args) => {
            ctx.commandExecute(args);
        })
            .bind("downloadAllFiles", (args) => {
            const message = ctx.dataItem(args.messageElement);
            ctx.trigger(EVENTS.download, { files: message === null || message === void 0 ? void 0 : message.files, message });
        })
            .bind("expandableToggle", (args) => {
            ctx.accessibility.updateExpandableIndicatorAria(args.indicator, args.isExpanded);
        });
    }
    _attachMessageBoxEvents() {
        const ctx = this.context;
        ctx.messageBox
            .bind("input", (args) => {
            ctx.trigger(EVENTS.input, { value: args.value });
        })
            .bind("sendMessage", (args) => {
            var _a, _b;
            const generating = args.generating;
            if (generating) {
                ctx.trigger(EVENTS.sendMessage, { generating });
            }
            else {
                const message = { text: args.text || ((_a = args.message) === null || _a === void 0 ? void 0 : _a.text), files: args.files || ((_b = args.message) === null || _b === void 0 ? void 0 : _b.files) || [] };
                ctx.trigger(EVENTS.sendMessage, { message });
                ctx.postMessage(message);
                ctx.clearReplyState();
            }
        })
            .bind("suggestionClick", (args) => {
            ctx.trigger(EVENTS.suggestionClick, { text: args.text });
            if (args.behavior !== "insert") {
                const message = { text: args.text, files: [] };
                ctx.trigger(EVENTS.sendMessage, { message });
                ctx.postMessage(message);
            }
        })
            .bind("fileSelect", (args) => {
            ctx.trigger(EVENTS.fileSelect, { files: args.files });
        })
            .bind("fileRemove", (args) => {
            ctx.trigger(EVENTS.fileRemove, { file: args.file, files: args.files });
        })
            .bind("replyMessageCloseButtonClick", () => {
            ctx.clearReplyState();
        });
    }
    _attachContextMenuEvents() {
        const ctx = this.context;
        ctx.messageContextMenu
            .bind("execute", ctx.contextMenuExecute.bind(ctx))
            .bind("open", ctx.messageContextMenuOpen.bind(ctx));
    }
    _attachWrapperDomEvents() {
        const ctx = this.context;
        ctx.wrapper.on(CLICK + NS, `[${REFERENCES.pinnedMessageCloseButton}]`, () => {
            ctx.trigger(EVENTS.unpin, { message: ctx.getPinnedMessage() });
            ctx.clearPinnedMessage();
        });
        ctx.wrapper.on(CLICK + NS, `[${REFERENCES.messageReferencePinWrapper}]`, (e) => {
            if ($(e.target).closest(`[${REFERENCES.pinnedMessageCloseButton}]`).length) {
                return;
            }
            if ($(e.target).is("a")) {
                return;
            }
            else {
                e.preventDefault();
                e.stopPropagation();
            }
            const pinnedMessage = ctx.getPinnedMessage();
            if (pinnedMessage && pinnedMessage.uid) {
                ctx.scrollToMessage(pinnedMessage.uid);
            }
        });
        ctx.wrapper.on(CLICK + NS, `[${REFERENCES.messageReferenceReplyWrapper}]`, (e) => {
            if ($(e.target).is("a")) {
                return;
            }
            else {
                e.preventDefault();
                e.stopPropagation();
            }
            const messageElement = $(e.currentTarget).closest(DOT + STYLES.message);
            const currentMessage = ctx.dataItem(messageElement);
            if (currentMessage && currentMessage.replyToId) {
                const originalMessage = ctx.getMessageById(currentMessage.replyToId);
                if (originalMessage && originalMessage.uid) {
                    ctx.scrollToMessage(originalMessage.uid);
                }
            }
        });
        ctx.wrapper.on(KEYDOWN + NS, (e) => {
            ctx.accessibility.handleKeyDown(e, ctx.messageBoxRef);
        });
        ctx.wrapper.on(`focus${NS}`, `.${STYLES.bubble}`, (e) => {
            const bubbleElement = $(e.target);
            if (bubbleElement.find(`.${STYLES.typingIndicator}`).length > 0) {
                return;
            }
            if (bubbleElement.attr("data-keyboard-focus") === "true") {
                const messageElement = bubbleElement.closest(`.${STYLES.message}`);
                const message = ctx.dataItem(messageElement);
                if (message && message.uid) {
                    ctx.scrollToMessage(message.uid);
                }
            }
        });
    }
}
