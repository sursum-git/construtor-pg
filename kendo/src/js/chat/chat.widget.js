/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { Widget } from "../core/base/widget";
import { inject } from "../core/service-container";
import { UtilsService } from "../core/services/utils.service";
import { DomUtilsService } from "../core/services/dom-utils.service";
import { HtmlService } from "../core/services/html.service";
import { DateParserService } from "../core/services/date-parser.service";
import { DataManager, ChatView, MessageBox, MessageMenu, AccesibilityManager } from "./collaborators";
import { STYLES, REFERENCES, EVENTS, ICONS, COMMANDS, CHANGE, DOT, LTR } from "./constants";
import { renderMessageGroup, renderMessageReference, renderFiles, renderSuggestions, renderHeader } from "./templates";
import { CommandHandler, EventWiring } from "./helpers";
const DEFAULT_MESSAGE_ACTIONS = [
    { name: COMMANDS.reply, text: "Reply", icon: ICONS.undo },
    { name: COMMANDS.copy, text: "Copy", icon: ICONS.copy },
    { name: COMMANDS.pin, text: "Pin", icon: ICONS.pin },
    { name: COMMANDS.delete, text: "Delete", icon: ICONS.trash }
];
const DEFAULT_FILE_ACTIONS = [
    { name: COMMANDS.download, text: "Download", icon: ICONS.download }
];
const DEFAULT_MESSAGES = {
    messageListLabel: "Message list",
    placeholder: "Type a message...",
    sendButton: "Send message",
    sendButtonLoading: "Stop",
    actionButton: "Send message",
    actionButtonLoading: "Stop",
    stopButton: "Stop",
    speechToTextButton: "Toggle speech to text",
    fileButton: "Attach file",
    downloadAll: "Download all",
    selfMessageDeleted: "You removed this message.",
    otherMessageDeleted: "This message was removed by its sender.",
    stopGeneration: "Stop generation",
    messageBoxLabel: "Type your message here",
    pinnedMessageCloseButton: "Unpin message",
    replyMessageCloseButton: "Remove reply",
    fileMenuButton: "File menu",
    retryMessage: "Retry"
};
export class Chat extends Widget {
    /**
     * Creates a new Chat widget instance.
     *
     * @param element - The DOM element to attach the widget to
     * @param options - Configuration options
     * @param events - Optional event handlers
     * @param utilsService - Utility service (injected)
     */
    constructor(element, options, events, utilsService = inject(UtilsService), domUtilsService = inject(DomUtilsService), htmlService = inject(HtmlService), dateParserService = inject(DateParserService)) {
        options = options || {};
        Chat.mergeContextMenuActions(options, Chat.options, "messageActions");
        Chat.mergeContextMenuActions(options, Chat.options, "fileActions");
        super(element, $.extend(true, {}, Chat.options, options));
        this.currentMessageReplyId = null;
        this._hasHeader = false;
        this._loading = false;
        this.events = [
            EVENTS.sendMessage,
            EVENTS.suggestionClick,
            EVENTS.unpin,
            EVENTS.input,
            EVENTS.toolbarAction,
            EVENTS.fileMenuAction,
            EVENTS.contextMenuAction,
            EVENTS.download,
            EVENTS.fileSelect,
            EVENTS.fileRemove,
            EVENTS.executeAction,
            EVENTS.resendMessage
        ];
        this.utilsService = utilsService;
        this.domUtilsService = domUtilsService;
        this.htmlService = htmlService;
        this.dateParserService = dateParserService;
        this.options.messageActions = options.messageActions;
        this.options.fileActions = options.fileActions;
        if (events) {
            this._events = events;
        }
        this._init(options);
    }
    static mergeContextMenuActions(options, defaultOptions, property) {
        const userActions = options[property];
        if (Array.isArray(userActions)) {
            const defaultActions = defaultOptions[property] || [];
            const defaultMap = {};
            for (const action of defaultActions) {
                defaultMap[action.name] = Object.assign({}, action);
            }
            options[property] = userActions.map(userAction => {
                if (userAction.name in defaultMap) {
                    return Object.assign(Object.assign({}, defaultMap[userAction.name]), userAction);
                }
                return Object.assign({}, userAction);
            });
        }
        else {
            options[property] = defaultOptions[property] || [];
        }
    }
    _init(options) {
        var _a;
        this.currentMessageReplyId = null;
        this._hasHeader = !!((_a = options.headerItems) === null || _a === void 0 ? void 0 : _a.length);
        this._initUser();
        this._initWrapper();
        this._initDataSource();
        this._initView();
        if (options === null || options === void 0 ? void 0 : options.toolbar) {
            this.utilsService.logToConsole("The 'toolbar' option has been deprecated.", "warn");
        }
        this._initMessageBox();
        this._initMenus();
        this._initAccessibility();
        this._attachEvents();
        if (this.options.loading) {
            this.loading(true);
        }
        if (this.options.autoBind) {
            this.dataSource.fetch();
        }
        this.scrollToBottom();
    }
    _initUser() {
        const options = this.options;
        if (options.authorId) {
            options.authorId = options.authorId.toString();
            return;
        }
        options.authorId = this.utilsService.guid();
    }
    _initWrapper() {
        const options = this.options;
        const height = options.height;
        const width = options.width;
        const headerItems = options.headerItems;
        const headerTemplate = options.headerTemplate;
        const uiElements = `<div ${REFERENCES.viewWrapper}></div>`;
        this.wrapper = this.element
            .addClass(STYLES.wrapper)
            .attr("dir", options.dir)
            .append(uiElements);
        if (this._hasHeader || headerTemplate) {
            this.wrapper.prepend(renderHeader(headerItems, headerTemplate));
        }
        if (height) {
            this.wrapper.css({
                height: height,
                minHeight: height
            });
        }
        if (width) {
            this.wrapper.css({
                width: width,
                minWidth: width
            });
        }
    }
    _initDataSource() {
        this._refreshHandler = this.refresh.bind(this);
        this._dataManager = new DataManager({
            chatOptions: this.options,
            utilsService: this.utilsService
        });
        this.dataSource = this._dataManager.getDataSource();
        this.dataSource.bind(CHANGE, this._refreshHandler);
    }
    _unbindDataSource() {
        this.dataSource.unbind(CHANGE, this._refreshHandler);
        this._dataManager = null;
    }
    _initView() {
        const options = $.extend(true, {}, this.options);
        delete options.name;
        const element = this.wrapper.find(`[${REFERENCES.viewWrapper}]`);
        this.view = new ChatView(element, options, {
            dataManager: this._dataManager,
            domUtilsService: this.domUtilsService,
            htmlService: this.htmlService,
            dateParserService: this.dateParserService
        });
    }
    _initMessageBox() {
        const options = $.extend(true, {}, this.options);
        delete options.name;
        this.messageBox = new MessageBox($('<textarea></textarea>'), options, {
            chatElement: this.wrapper,
            utilsService: this.utilsService,
            domUtilsService: this.domUtilsService
        });
    }
    _initMenus() {
        const options = this.options;
        const messageActions = options.messageActions;
        this.messageContextMenu = new MessageMenu($("<ul></ul>"), { dataSource: messageActions });
    }
    _initAccessibility() {
        this.accessibility = new AccesibilityManager({
            wrapper: this.wrapper,
            getOptions: () => this.options,
            utilsService: this.utilsService
        });
        this.accessibility.setupAriaAttributes();
        this.accessibility.setupBubbleTabNavigation();
    }
    _attachEvents() {
        this._commandHandler = new CommandHandler({
            getUserId: this.getUserId.bind(this),
            dataItem: this.dataItem.bind(this),
            fileDataItem: this.fileDataItem.bind(this),
            removeMessage: this.removeMessage.bind(this),
            pinMessage: (message) => this._dataManager.pinMessage(message),
            setReplyMessage: (message, isOwnMessage) => this.messageBox.setReplyMessage(message, isOwnMessage),
            setCurrentMessageReplyId: (id) => { this.currentMessageReplyId = id; },
            setupAriaAttributes: () => this.accessibility.setupAriaAttributes(),
            toggleDeleteVisibility: (isAuthor) => this.messageContextMenu.toggleDeleteVisibility(isAuthor),
            trigger: this.trigger.bind(this)
        });
        this._eventWiring = new EventWiring({
            options: this.options,
            wrapper: this.wrapper,
            view: this.view,
            messageBox: this.messageBox,
            messageContextMenu: this.messageContextMenu,
            value: this.value.bind(this),
            postMessage: this.postMessage.bind(this),
            clearReplyState: this.clearReplyState.bind(this),
            commandExecute: this._commandHandler.commandExecute.bind(this._commandHandler),
            contextMenuExecute: this._commandHandler.contextMenuExecute.bind(this._commandHandler),
            messageContextMenuOpen: this._commandHandler.messageContextMenuOpen.bind(this._commandHandler),
            dataItem: this.dataItem.bind(this),
            scrollToMessage: this.scrollToMessage.bind(this),
            clearPinnedMessage: this.clearPinnedMessage.bind(this),
            getPinnedMessage: () => this._dataManager.getPinnedMessage(),
            getMessageById: (id) => this._dataManager.getMessageById(id),
            trigger: this.trigger.bind(this),
            accessibility: this.accessibility,
            messageBoxRef: this.messageBox
        });
        this._eventWiring.attach();
        this.bind(this.events, this.options);
    }
    getUserId() {
        return this.options.authorId;
    }
    /**
     * Gets or sets the input value of the message box.
     * @param value - Optional value to set. If provided, sets the input value.
     * @returns The current input value when called without arguments.
     */
    value(newValue) {
        var _a;
        if (newValue === undefined) {
            return ((_a = this.messageBox) === null || _a === void 0 ? void 0 : _a.value()) || "";
        }
        if (this.messageBox) {
            this.messageBox.value(newValue);
            this.trigger(EVENTS.input, { value: newValue });
        }
    }
    /**
     * Sets new options for the widget.
     */
    setOptions(options) {
        super.setOptions(options);
        this.destroy();
        const chat = new Chat(this.element, this.options);
        Object.assign(this, chat);
    }
    /**
     * Sets a new data source.
     */
    setDataSource(dataSource) {
        if (this.dataSource) {
            this._unbindDataSource();
        }
        this.view.clearMessages();
        this.options.dataSource = dataSource;
        this._initDataSource();
        if (this.options.autoBind) {
            this.dataSource.fetch();
        }
        if (this.view) {
            this.view.refreshDataManager(this._dataManager);
        }
    }
    /**
     * Posts a new message to the chat.
     */
    postMessage(message) {
        if (this.currentMessageReplyId) {
            if (typeof message === "string") {
                message = { text: message };
            }
            message.replyToId = this.currentMessageReplyId;
        }
        return this._dataManager.postMessage(message, this.getUserId());
    }
    /**
     * Removes a message (marks as deleted).
     */
    removeMessage(message) {
        return this._dataManager.removeMessage(message);
    }
    /**
     * Updates an existing message.
     */
    updateMessage(message, newData) {
        return this._dataManager.updateMessage(message, newData);
    }
    /**
     * Gets a message by its UID.
     */
    getMessageByUid(uid) {
        return this._dataManager.getMessageByUid(uid);
    }
    /**
     * Gets the data item for a message element.
     */
    dataItem(message) {
        return this.view.dataItem(message);
    }
    /**
     * Gets the file data item for a file element.
     */
    fileDataItem(message, file) {
        return this.view.fileDataItem(message, file);
    }
    /**
     * Clears all messages from the view.
     */
    clearMessages() {
        this.view.clearMessages();
    }
    /**
     * @internal
     * Renders a message in the view.
     */
    renderMessage(message) {
        this.view.renderMessage(message);
        this.accessibility.setupBubbleTabNavigation();
    }
    /**
     * @internal
     * Renders a pinned message.
     * The pinned message is positioned sticky inside the message list per Chat v3 spec.
     */
    renderPinnedMessage(message) {
        const isOwnMessage = message.authorId === this.getUserId();
        const options = this.options;
        const pinnedMessageElement = $(options.messageReferenceTemplate({
            text: message.text,
            files: message.files,
            isDeleted: message.isDeleted,
            isOwnMessage,
            messages: options.messages,
            isPinMessage: true,
            renderCloseButton: true,
            renderFileMenuButton: false
        }));
        this.wrapper.find(`[${REFERENCES.messageReferencePinWrapper}]`).remove();
        const messageList = this.wrapper.find(DOT + STYLES.viewWrapper);
        if (messageList.length) {
            messageList.prepend(pinnedMessageElement);
        }
        else if (this._hasHeader) {
            pinnedMessageElement.insertAfter(this.wrapper.find(DOT + STYLES.header));
        }
        else {
            this.wrapper.prepend(pinnedMessageElement);
        }
        this.view._initFileMenus(pinnedMessageElement);
    }
    /**
     * Clears the pinned message.
     */
    clearPinnedMessage() {
        this._dataManager.clearPinnedMessage();
        this.wrapper.find(DOT + STYLES.messagePinned).remove();
    }
    /**
     * Clears the reply state.
     */
    clearReplyState() {
        this.messageBox.removeReplyMessage();
        this.currentMessageReplyId = null;
    }
    /**
     * Scrolls the chat to the bottom.
     */
    scrollToBottom() {
        this.view.scrollToBottom();
    }
    /**
     * Scrolls to a specific message.
     */
    scrollToMessage(uid) {
        return this.view.scrollToMessage(uid);
    }
    /**
     * Gets or sets the loading state (transforms send button to stop button).
     */
    loading(value) {
        if (value === undefined) {
            return this._loading;
        }
        this._loading = value;
        if (this.wrapper) {
            this.wrapper.toggleClass(STYLES.generating, value);
        }
        if (this.messageBox) {
            this.messageBox.loading(value);
        }
    }
    /**
     * @deprecated Use `loading()` instead. Will be removed in future version.
     */
    toggleSendButtonGenerating(value) {
        this.loading(value);
    }
    /**
     * @internal
     * Refreshes the chat view when data changes.
     */
    refresh(e) {
        const that = this;
        const options = this.options;
        const data = that.dataSource.view();
        const pinnedMessage = data.find((item) => item.isPinned);
        const changedItems = (e === null || e === void 0 ? void 0 : e.changedItems) || [];
        if (!e || !e.action) {
            if (data.length === 0 && options.noDataTemplate) {
                const noDataHtml = options.noDataTemplate();
                that.view.showNoData(noDataHtml);
            }
            else {
                that.view.clearMessages();
                data.forEach((item) => {
                    that.renderMessage(item);
                });
            }
            that.scrollToBottom();
        }
        if (pinnedMessage) {
            that.renderPinnedMessage(pinnedMessage);
        }
        if ((e === null || e === void 0 ? void 0 : e.action) === "sync") {
            if (options.noDataTemplate && !that.view.hasMessages()) {
                that.view.clearMessages();
            }
            const message = changedItems.length ? changedItems[0] : data[data.length - 1];
            that.renderMessage(message);
            if (!changedItems.length) {
                that.scrollToBottom();
            }
        }
        that.view.renderSuggestedActions();
        that.accessibility.setupAriaAttributes();
        that.accessibility.setupBubbleTabNavigation();
    }
    /**
     * @internal
     */
    commandExecute(e) {
        this._commandHandler.commandExecute(e);
    }
    /**
     * @internal
     */
    messageReply(message) {
        this._commandHandler.messageReply(message);
    }
    /**
     * @internal
     * Copies message text to clipboard.
     */
    messageCopy(message) {
        this._commandHandler.messageCopy(message);
    }
    /**
     * @internal
     * Pins a message.
     */
    messagePin(message) {
        this._commandHandler.messagePin(message);
    }
    /**
     * @internal
     * Deletes a message.
     */
    messageDelete(message) {
        this._commandHandler.messageDelete(message);
    }
    /**
     * @internal
     */
    messageContextMenuOpen(e) {
        this._commandHandler.messageContextMenuOpen(e);
    }
    /**
     * Destroys the widget and cleans up resources.
     */
    destroy() {
        if (this.dataSource) {
            this._unbindDataSource();
            this.dataSource = null;
        }
        if (this.view) {
            this.view.unbind();
            this.view.destroy();
            this.view = null;
        }
        if (this.messageBox) {
            this.messageBox.unbind();
            this.messageBox.destroy();
            this.messageBox = null;
        }
        if (this.messageContextMenu) {
            this.messageContextMenu.destroy();
        }
        if (this.wrapper) {
            this.wrapper.off();
            this.wrapper.empty();
            this.wrapper = null;
        }
        super.destroy();
    }
}
Chat.options = {
    name: "Chat",
    autoAssignId: true,
    autoBind: true,
    allowMessageCollapse: false,
    showUsername: true,
    showAvatar: true,
    timestampVisibility: "always",
    suggestionsBehavior: "insert",
    authorId: null,
    suggestionsScrollable: false,
    suggestedActionsScrollable: false,
    suggestionsLayoutMode: "scroll",
    suggestedActionsLayoutMode: "scroll",
    speechToText: true,
    fileAttachment: true,
    scrollToBottomButton: true,
    loading: false,
    messageBox: {
        mode: "multi",
        maxTextAreaHeight: 110
    },
    messageToolbarActions: [],
    messageActions: DEFAULT_MESSAGE_ACTIONS,
    fileActions: DEFAULT_FILE_ACTIONS,
    suggestions: [],
    headerItems: [],
    dir: LTR,
    messageTimeFormat: "ddd MMM dd yyyy",
    width: null,
    height: null,
    messages: DEFAULT_MESSAGES,
    messageWidthMode: "standard",
    messageTemplate: null,
    messageGroupTemplate: renderMessageGroup,
    messageReferenceTemplate: renderMessageReference,
    filesTemplate: renderFiles,
    suggestionsTemplate: renderSuggestions,
    suggestedActionsTemplate: renderSuggestions,
    timestampTemplate: null,
    messageStatusTemplate: null,
    messageStatusSettings: null,
    headerTemplate: null,
    noDataTemplate: null,
    messageContentTemplate: null,
    authorMessageTemplate: null,
    receiverMessageTemplate: null,
    authorMessageContentTemplate: null,
    receiverMessageContentTemplate: null,
    userStatusTemplate: null,
    attachmentTemplate: null,
    messageBoxTemplate: null,
    authorMessageSettings: null,
    receiverMessageSettings: null,
    actionButton: null,
    attachmentLayout: "list",
    filesLayoutMode: "vertical",
    textField: "text",
    authorIdField: "authorId",
    authorNameField: "authorName",
    authorImageUrlField: "authorImageUrl",
    authorImageAltTextField: "authorImageAltText",
    idField: "id",
    timestampField: "timestamp",
    filesField: "files",
    replyToIdField: "replyToId",
    isDeletedField: "isDeleted",
    isPinnedField: "isPinned",
    isTypingField: "isTyping",
    statusField: "status",
    failedField: "failed",
    attachmentsField: "attachments",
    attachmentLayoutField: "attachmentLayout",
    skipSanitization: false
};
