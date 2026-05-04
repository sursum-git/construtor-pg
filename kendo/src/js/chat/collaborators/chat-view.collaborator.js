/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.icons.js";
import "../../kendo.dropdownbutton.js";
import "../../kendo.button.js";
import { Widget } from "../../core/base/widget";
import { NS, STYLES, REFERENCES, ICONS, SCROLLING_DELTA, CLICK, DOT, RTL, EVENTS } from "../constants";
import { MessageToolbar } from "./message-toolbar.collaborator.js";
import { FileMenu } from "./file-menu.collaborator.js";
import { MessageRenderer, ScrollManager, SuggestionRenderer } from "./chat-view";
export class ChatView extends Widget {
    constructor(element, options, context) {
        super(element, $.extend(true, {}, ChatView.options, options));
        this._dragHandler = null;
        this.events = [
            EVENTS.suggestedActionClick,
            EVENTS.messageToolbarExecute,
            EVENTS.fileMenuExecute,
            EVENTS.downloadAllFiles,
            EVENTS.expandableToggle,
            EVENTS.resendMessage
        ];
        this.context = context;
        this._initList();
        this._initHelpers();
        this._attachEvents();
    }
    _initList() {
        const messages = this.options.messages;
        this.element
            .addClass(STYLES.viewWrapper)
            .attr("role", "log")
            .attr("aria-label", messages.messageListLabel);
        this.list = $("<div>")
            .addClass(STYLES.messageListContent)
            .appendTo(this.element);
    }
    _initHelpers() {
        const options = this.options;
        this._messageRenderer = new MessageRenderer({
            list: this.list,
            options,
            dataManager: this.context.dataManager,
            htmlService: this.context.htmlService,
            dateParserService: this.context.dateParserService,
            dataItem: this.dataItem.bind(this),
            getReplyMessage: this.getReplyMessage.bind(this),
            initMessageToolbar: this._initMessageToolbar.bind(this),
            initFileMenus: this._initFileMenus.bind(this)
        });
        this._scrollManager = new ScrollManager({
            element: this.element,
            options,
            list: this.list,
            domUtilsService: this.context.domUtilsService,
            htmlService: this.context.htmlService
        });
        this._suggestionRenderer = new SuggestionRenderer({
            list: this.list,
            options,
            dataManager: this.context.dataManager
        });
    }
    _attachEvents() {
        this.element
            .on(CLICK + NS, this._listClick.bind(this))
            .on(CLICK + NS, DOT + STYLES.message, this._messageClick.bind(this))
            .on(CLICK + NS, DOT + STYLES.suggestion, this._suggestionClick.bind(this))
            .on(CLICK + NS, `[${REFERENCES.leftScrollButton}]`, this._leftScrollButtonClick.bind(this))
            .on(CLICK + NS, `[${REFERENCES.rightScrollButton}]`, this._rightScrollButtonClick.bind(this))
            .on(CLICK + NS, DOT + STYLES.downloadButton, this._downloadAllClick.bind(this))
            .on(CLICK + NS, `[${REFERENCES.retryButton}]`, this._retryButtonClick.bind(this));
        this._attachDragToScrollEvents();
    }
    _attachDragToScrollEvents() {
        this._dragHandler = this.context.domUtilsService.createDragToScrollHandler(this.element, {
            namespace: NS + ".chatViewDrag",
            captureElement: this.element,
            delegateSelector: DOT + STYLES.suggestionsScroll
        });
        this._dragHandler.attach();
    }
    _retryButtonClick(e) {
        e.preventDefault();
        e.stopPropagation();
        const button = $(e.currentTarget);
        const uid = button.attr("data-uid");
        const messageElement = button.closest(DOT + STYLES.message);
        const message = this._getMessageFromElement(messageElement);
        this.trigger(EVENTS.resendMessage, { message, uid });
    }
    _getMessageFromElement(element) {
        var _a;
        const uid = element.attr("data-uid");
        if (!uid) {
            return null;
        }
        const options = this.options;
        const messages = ((_a = options.dataSource) === null || _a === void 0 ? void 0 : _a.data()) || [];
        return messages.find((m) => m.uid === uid) || { uid };
    }
    renderMessage(message) {
        this._messageRenderer.renderMessage(message);
    }
    _initMessageToolbar(element) {
        const options = this.options;
        const messageToolbarActions = options.messageToolbarActions;
        if (!messageToolbarActions || !messageToolbarActions.length || !element.length) {
            element.remove();
            return;
        }
        const messageToolbar = new MessageToolbar(element, {
            items: messageToolbarActions,
            dir: options.dir,
            messages: options.messages,
            resizable: false
        });
        messageToolbar.bind("execute", (e) => this.trigger(EVENTS.messageToolbarExecute, e));
    }
    _initFileMenus(element) {
        const options = this.options;
        const fileActions = options.fileActions;
        const fileMenuButtons = element.find(`[${REFERENCES.fileMenuButton}]`);
        if (!fileActions || !fileActions.length || !element.length) {
            fileMenuButtons.remove();
            return;
        }
        fileMenuButtons.each((_i, el) => {
            const $el = $(el);
            if (!$el.data("kendoDropDownButton")) {
                const menu = new FileMenu($el, { items: fileActions });
                menu.bind("execute", (e) => this.trigger(EVENTS.fileMenuExecute, e));
            }
        });
    }
    dataItem(message) {
        const uid = message && message.data("uid");
        if (uid) {
            return this.context.dataManager.getMessageByUid(uid);
        }
        return null;
    }
    fileDataItem(message, file) {
        const uid = file && file.data("uid");
        if (uid) {
            return this.context.dataManager.getFileByUid(message, uid);
        }
        return null;
    }
    getReplyMessage(message) {
        if (!(message === null || message === void 0 ? void 0 : message.replyToId)) {
            return null;
        }
        const replyMessage = this.context.dataManager.getMessageById(message.replyToId);
        if (replyMessage) {
            replyMessage.isOwnMessage = replyMessage.authorId === this.options.authorId;
        }
        return replyMessage;
    }
    renderSuggestedActions() {
        this._suggestionRenderer.renderSuggestedActions();
    }
    clearMessages() {
        this.list.find(DOT + STYLES.messageGroup).remove();
        this.list.find(DOT + STYLES.timestamp).remove();
        this._suggestionRenderer.removeSuggestedActions();
    }
    removeMessage(uid) {
        const messageElement = this.list.find(DOT + STYLES.message + `[data-uid="${this.context.htmlService.encode(uid)}"]`);
        if (!messageElement.length) {
            return false;
        }
        const messageGroup = messageElement.closest(DOT + STYLES.messageGroup);
        const messagesInGroup = messageGroup.find(DOT + STYLES.message);
        if (messagesInGroup.length === 1) {
            messageGroup.remove();
        }
        else {
            messageElement.remove();
        }
        return true;
    }
    scrollToBottom() {
        this._scrollManager.scrollToBottom();
    }
    scrollToMessage(uid) {
        return this._scrollManager.scrollToMessage(uid);
    }
    refreshDataManager(dataManager) {
        this.context.dataManager = dataManager;
    }
    showNoData(html) {
        this.list.html(html);
    }
    hasMessages() {
        return this.list.children(DOT + STYLES.messageGroup).length > 0;
    }
    _listClick(e) {
        const targetElement = $(e.target);
        if (targetElement.hasClass(STYLES.message) || targetElement.parents(DOT + STYLES.message).length) {
            return;
        }
        this._clearSelection(targetElement);
    }
    _suggestionClick(e) {
        const suggestionElement = $(e.currentTarget);
        if (suggestionElement.length) {
            this.trigger(EVENTS.suggestedActionClick, { text: suggestionElement.text() });
        }
    }
    _messageClick(e) {
        const target = $(e.target);
        const expandIcon = target.closest(`[${REFERENCES.bubbleExpandableIndicator}]`);
        if (this._allowMessageClick(target)) {
            return;
        }
        this._clearSelection(target);
        if (expandIcon.length) {
            const bubble = expandIcon.closest(DOT + STYLES.bubble);
            const isExpanded = bubble.hasClass(STYLES.expanded);
            bubble.toggleClass(STYLES.expanded, !isExpanded);
            expandIcon.html(kendo.ui.icon({ icon: isExpanded ? ICONS.chevronDown : ICONS.chevronUp }));
            this.trigger(EVENTS.expandableToggle, { indicator: expandIcon, isExpanded: !isExpanded });
            return;
        }
        const messageElement = $(e.currentTarget);
        const bubble = messageElement.find(DOT + STYLES.bubble);
        bubble.addClass(STYLES.selected);
    }
    _allowMessageClick(target) {
        const disallowedSelectors = [
            `[${REFERENCES.fileMenuButton}]`,
            DOT + STYLES.messageToolbar,
            DOT + STYLES.typingIndicator,
            DOT + STYLES.downloadButton
        ].join(", ");
        return target.closest(disallowedSelectors).length > 0 ||
            target.children(disallowedSelectors).length > 0 ||
            target.is(disallowedSelectors);
    }
    _clearSelection(target) {
        const selectedMessages = this.element.find(DOT + STYLES.selected);
        if (target.closest(`[${REFERENCES.bubbleExpandableIndicator}]`).length) {
            return;
        }
        selectedMessages.each((_index, element) => {
            const bubble = $(element);
            bubble.removeClass(STYLES.selected);
        });
    }
    _leftScrollButtonClick(e) {
        e.preventDefault();
        const button = $(e.currentTarget);
        const scrollableElement = this.element.find(`${DOT}${STYLES.suggestionsScroll}`);
        const isRtl = this.options.dir === RTL;
        const position = this.context.domUtilsService.scrollByDelta(scrollableElement, isRtl ? SCROLLING_DELTA : -SCROLLING_DELTA);
        this.element.find(`[${REFERENCES.rightScrollButton}]`).removeClass(STYLES.disabled);
        if (position.atStart) {
            button.addClass(STYLES.disabled);
        }
    }
    _rightScrollButtonClick(e) {
        e.preventDefault();
        const button = $(e.currentTarget);
        const scrollableElement = this.element.find(`${DOT}${STYLES.suggestionsScroll}`);
        const isRtl = this.options.dir === RTL;
        const position = this.context.domUtilsService.scrollByDelta(scrollableElement, isRtl ? -SCROLLING_DELTA : SCROLLING_DELTA);
        this.element.find(`[${REFERENCES.leftScrollButton}]`).removeClass(STYLES.disabled);
        if (position.atEnd) {
            button.addClass(STYLES.disabled);
        }
    }
    _downloadAllClick(e) {
        const downloadButton = $(e.currentTarget);
        const message = downloadButton.closest(DOT + STYLES.message);
        if (message.length) {
            this.trigger(EVENTS.downloadAllFiles, { messageElement: message });
        }
    }
    destroy() {
        super.destroy();
        this._scrollManager.destroy();
        if (this._dragHandler) {
            this._dragHandler.destroy();
        }
        this.element.find("[data-role='button']").each(function () {
            const fileMenu = $(this).data("kendoChatFileMenu");
            if (fileMenu) {
                fileMenu.destroy();
            }
        });
        this.element.empty();
        this.element.off(NS);
        this.list = null;
    }
}
ChatView.options = {
    name: "ChatView"
};
