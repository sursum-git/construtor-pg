/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { DOT, STYLES, MESSAGE_WIDTH_MODE } from "../../constants";
import { renderMessage } from "../../templates";
export class MessageRenderer {
    constructor(context) {
        this.context = context;
    }
    renderMessage(message) {
        const options = this.context.options;
        const componentMessages = options.messages;
        const expandable = options.allowMessageCollapse;
        const fullWidth = options.messageWidthMode === MESSAGE_WIDTH_MODE.FULL;
        const author = {
            id: message.authorId,
            name: message.authorName,
            imageUrl: message.authorImageUrl,
            imageAltText: message.authorImageAltText
        };
        const isOwnMessage = author.id === options.authorId;
        const replyMessage = this.context.getReplyMessage(message);
        const messageTimeFormat = options.messageTimeFormat;
        const skipSanitization = options.skipSanitization;
        const statusTemplate = options.messageStatusTemplate;
        const userSettings = isOwnMessage ? options.authorMessageSettings : options.receiverMessageSettings;
        const messageSettings = this._mergeMessageSettings(options, userSettings);
        if (this._handleExistingMessageUpdate({
            message, author, isOwnMessage, replyMessage, componentMessages, expandable, messageTimeFormat, skipSanitization, statusTemplate, messageSettings
        })) {
            return;
        }
        const targetGroupElement = this._findTargetMessageGroup();
        const canGroup = this._canGroupWithLastMessage(targetGroupElement, author.id);
        if (canGroup && targetGroupElement.length) {
            this._addMessageToExistingGroup({
                message, author, isOwnMessage, replyMessage, componentMessages, expandable, targetGroupElement, messageTimeFormat, skipSanitization, statusTemplate, messageSettings
            });
        }
        else {
            const showTimestamp = this._shouldShowTimestamp(message);
            this._createNewMessageGroup({
                message, author, isOwnMessage, replyMessage, componentMessages, expandable, fullWidth, messageTimeFormat, showTimestamp, skipSanitization, statusTemplate, messageSettings
            });
        }
    }
    _mergeMessageSettings(options, userSettings) {
        const merged = {
            showAvatar: options.showAvatar,
            showUsername: options.showUsername,
            showTimestamp: options.timestampVisibility !== "never",
            allowMessageCollapse: options.allowMessageCollapse,
            messageWidthMode: options.messageWidthMode
        };
        if (userSettings) {
            if (userSettings.showAvatar !== undefined)
                merged.showAvatar = userSettings.showAvatar;
            if (userSettings.showUsername !== undefined)
                merged.showUsername = userSettings.showUsername;
            if (userSettings.showTimestamp !== undefined)
                merged.showTimestamp = userSettings.showTimestamp;
            if (userSettings.allowMessageCollapse !== undefined)
                merged.allowMessageCollapse = userSettings.allowMessageCollapse;
            if (userSettings.messageWidthMode !== undefined)
                merged.messageWidthMode = userSettings.messageWidthMode;
            if (userSettings.enableFileActions !== undefined)
                merged.enableFileActions = userSettings.enableFileActions;
            if (userSettings.enableContextMenuActions !== undefined)
                merged.enableContextMenuActions = userSettings.enableContextMenuActions;
            if (userSettings.messageToolbarActions !== undefined)
                merged.messageToolbarActions = userSettings.messageToolbarActions;
            if (userSettings.messageActions !== undefined)
                merged.messageActions = userSettings.messageActions;
        }
        return merged;
    }
    _shouldShowTimestamp(message) {
        if (!message.timestamp) {
            return false;
        }
        const lastNonTypingMessage = this.context.list.find(DOT + STYLES.message).filter(function () {
            return $(this).find(DOT + STYLES.typingIndicator).length === 0;
        }).last();
        if (!lastNonTypingMessage.length) {
            return true;
        }
        const lastMessageData = this.context.dataItem(lastNonTypingMessage);
        if (!lastMessageData || !lastMessageData.timestamp) {
            return true;
        }
        const currentMessageDate = this.context.dateParserService.parseDate(message.timestamp);
        const lastMessageDate = this.context.dateParserService.parseDate(lastMessageData.timestamp);
        if (!currentMessageDate || !lastMessageDate) {
            return false;
        }
        const currentDay = new Date(currentMessageDate);
        currentDay.setHours(0, 0, 0, 0);
        const lastDay = new Date(lastMessageDate);
        lastDay.setHours(0, 0, 0, 0);
        return currentDay.getTime() !== lastDay.getTime();
    }
    _handleExistingMessageUpdate(context) {
        var _a, _b, _c;
        const { message, author, isOwnMessage, replyMessage, componentMessages, expandable, messageTimeFormat, skipSanitization, statusTemplate, messageSettings } = context;
        const options = this.context.options;
        const existingMessageElement = this.context.list.find(DOT + STYLES.message + `[data-uid="${this.context.htmlService.encode((_a = message.uid) !== null && _a !== void 0 ? _a : "")}"]`);
        if (existingMessageElement.length) {
            const messageTemplate = options.messageTemplate;
            let messageHtml;
            if (messageTemplate) {
                messageHtml = messageTemplate(Object.assign(Object.assign({}, message), { isOwnMessage, author }), replyMessage, true, componentMessages, (_b = messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.allowMessageCollapse) !== null && _b !== void 0 ? _b : expandable, messageTimeFormat, skipSanitization, statusTemplate);
            }
            else {
                messageHtml = renderMessage(Object.assign(Object.assign({}, message), { isOwnMessage, author }), replyMessage, true, componentMessages, (_c = messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.allowMessageCollapse) !== null && _c !== void 0 ? _c : expandable, messageTimeFormat, skipSanitization, statusTemplate, options.filesLayoutMode, this.context.htmlService);
            }
            const updatedMessageElement = $(messageHtml);
            existingMessageElement.replaceWith(updatedMessageElement);
            this.context.initMessageToolbar(updatedMessageElement.find(DOT + STYLES.messageToolbar));
            this.context.initFileMenus(updatedMessageElement);
            return true;
        }
        return false;
    }
    _findTargetMessageGroup() {
        let lastGroupElement = this.context.list.children(DOT + STYLES.messageGroup).last();
        let targetGroupElement = lastGroupElement;
        const messagesInLastGroup = lastGroupElement.find(DOT + STYLES.message);
        if (messagesInLastGroup.length === 1) {
            const onlyMessage = messagesInLastGroup.first();
            const isTypingIndicator = onlyMessage.find(DOT + STYLES.typingIndicator).length > 0;
            if (isTypingIndicator) {
                targetGroupElement = lastGroupElement.prev(DOT + STYLES.messageGroup);
            }
        }
        return targetGroupElement;
    }
    _canGroupWithLastMessage(targetGroupElement, authorId) {
        const lastMessageInTargetGroup = targetGroupElement.find(DOT + STYLES.message).filter(function () {
            return $(this).find(DOT + STYLES.typingIndicator).length === 0;
        }).last();
        const lastMessageData = this.context.dataItem(lastMessageInTargetGroup);
        return (lastMessageData === null || lastMessageData === void 0 ? void 0 : lastMessageData.authorId) === authorId;
    }
    _addMessageToExistingGroup(context) {
        var _a, _b;
        const { message, author, isOwnMessage, replyMessage, componentMessages, expandable, targetGroupElement, messageTimeFormat, skipSanitization, statusTemplate, messageSettings } = context;
        const options = this.context.options;
        const messageTemplate = options.messageTemplate;
        const messageGroupContent = targetGroupElement.find(DOT + STYLES.messageGroupContent);
        let messageHtml;
        if (messageTemplate) {
            messageHtml = messageTemplate(Object.assign(Object.assign({}, message), { isOwnMessage, author }), replyMessage, true, componentMessages, (_a = messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.allowMessageCollapse) !== null && _a !== void 0 ? _a : expandable, messageTimeFormat, skipSanitization, statusTemplate);
        }
        else {
            messageHtml = renderMessage(Object.assign(Object.assign({}, message), { isOwnMessage, author }), replyMessage, true, componentMessages, (_b = messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.allowMessageCollapse) !== null && _b !== void 0 ? _b : expandable, messageTimeFormat, skipSanitization, statusTemplate, options.filesLayoutMode, this.context.htmlService);
        }
        const messageElement = $(messageHtml);
        messageGroupContent.append(messageElement);
        this.context.initMessageToolbar(messageElement.find(DOT + STYLES.messageToolbar));
        this.context.initFileMenus(messageElement);
        this._moveTypingIndicatorsToEnd();
    }
    _createNewMessageGroup(context) {
        const { message, author, isOwnMessage, replyMessage, componentMessages, expandable, fullWidth, messageTimeFormat, showTimestamp, skipSanitization, statusTemplate, messageSettings } = context;
        const options = this.context.options;
        const messageGroupTemplate = options.messageGroupTemplate;
        const timestampTemplate = options.timestampTemplate;
        const messageTemplate = options.messageTemplate;
        const groupElement = $(messageGroupTemplate({
            message,
            author,
            isOwnMessage,
            replyMessage,
            downloadAll: true,
            messages: componentMessages,
            expandable,
            fullWidth,
            messageTimeFormat,
            timestampTemplate,
            statusTemplate,
            showTimestamp,
            messageTemplate,
            skipSanitization,
            messageSettings,
            filesLayoutMode: options.filesLayoutMode,
            contentTemplate: options.messageContentTemplate,
            authorMessageContentTemplate: options.authorMessageContentTemplate,
            receiverMessageContentTemplate: options.receiverMessageContentTemplate,
            attachmentTemplate: options.attachmentTemplate,
            attachmentLayout: options.attachmentLayout,
            userStatusTemplate: options.userStatusTemplate
        }));
        this.context.list.append(groupElement);
        this.context.initMessageToolbar(groupElement.find(DOT + STYLES.messageToolbar));
        this.context.initFileMenus(groupElement);
        this._moveTypingIndicatorsToEnd();
        return groupElement;
    }
    _moveTypingIndicatorsToEnd() {
        const ctx = this.context;
        const options = ctx.options;
        const messageGroupTemplate = options.messageGroupTemplate;
        const fullWidth = options.messageWidthMode === MESSAGE_WIDTH_MODE.FULL;
        const typingMessages = ctx.list.find(DOT + STYLES.message).filter(function () {
            return $(this).find(DOT + STYLES.typingIndicator).length > 0;
        });
        typingMessages.each(function () {
            const typingMessage = $(this);
            const messageGroup = typingMessage.closest(DOT + STYLES.messageGroup);
            const messagesInGroup = messageGroup.find(DOT + STYLES.message);
            if (messagesInGroup.length === 1) {
                messageGroup.detach().appendTo(ctx.list);
            }
            else {
                typingMessage.remove();
                const message = ctx.dataItem(typingMessage);
                if (!message)
                    return;
                const author = {
                    id: message.authorId,
                    name: message.authorName,
                    imageUrl: message.authorImageUrl,
                    imageAltText: message.authorImageAltText
                };
                const typingGroupElement = $(messageGroupTemplate({
                    message,
                    author,
                    isOwnMessage: author.id === options.authorId,
                    fullWidth,
                    messageTimeFormat: options.messageTimeFormat,
                    showTimestamp: false,
                    timestampTemplate: options.timestampTemplate,
                    messageTemplate: options.messageTemplate,
                    filesLayoutMode: options.filesLayoutMode
                }));
                ctx.list.append(typingGroupElement);
            }
        });
    }
}
