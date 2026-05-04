/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.data.js"; // Stiil required until kendo.data is modularized
/**
 * DataManager handles all data operations for the Chat component.
 * Manages message storage, retrieval, updates, and data source configuration.
 *
 * This is an internal collaborator - not a shared service.
 */
export class DataManager {
    /**
     * Constructs a new DataManager instance.
     * @param context - Context with chat options and services
     */
    constructor(context) {
        this.context = context;
        this.options = this.buildOptions(context.chatOptions);
        this.dataSource = this.createDataSource(context.chatOptions);
    }
    /**
     * Builds internal options from chat options.
     */
    buildOptions(chatOptions) {
        let options;
        if (chatOptions.dataSource instanceof kendo.data.DataSource) {
            options = $.extend(true, {}, DataManager.DEFAULT_OPTIONS, chatOptions.dataSource.options);
        }
        else if (Array.isArray(chatOptions.dataSource)) {
            options = $.extend(true, {}, DataManager.DEFAULT_OPTIONS, { data: chatOptions.dataSource });
        }
        else {
            options = $.extend(true, {}, DataManager.DEFAULT_OPTIONS, chatOptions.dataSource);
        }
        options.autoAssignId = chatOptions.autoAssignId;
        this.mapFields(options.schema.model.fields, chatOptions);
        return options;
    }
    /**
     * Creates and configures a data source for the chat component.
     */
    createDataSource(chatOptions) {
        if (chatOptions.dataSource instanceof kendo.data.DataSource) {
            return chatOptions.dataSource;
        }
        return kendo.data.DataSource.create(this.options);
    }
    /**
     * Maps field configuration from chat options to data source field definitions.
     */
    mapFields(fields, chatOptions) {
        for (const key in fields) {
            if (Object.prototype.hasOwnProperty.call(fields, key) && DataManager.FIELD_MAP[key]) {
                const optionKey = DataManager.FIELD_MAP[key];
                fields[key].from = chatOptions[optionKey];
            }
        }
    }
    /**
     * Gets the data source instance.
     */
    getDataSource() {
        return this.dataSource;
    }
    /**
     * Gets the current data source view.
     */
    getView() {
        return this.dataSource.view();
    }
    /**
     * Gets the last message in the data source view.
     */
    getLastMessage() {
        const view = this.getView();
        return view.length ? view[view.length - 1] : null;
    }
    /**
     * Gets a message by ID from the data source.
     */
    getMessageById(id) {
        if (!id) {
            return null;
        }
        return this.dataSource.get(id);
    }
    /**
     * Gets a message by UID from the data source.
     */
    getMessageByUid(uid) {
        return this.dataSource.getByUid(uid);
    }
    /**
     * Gets a file by UID from a message's files array.
     */
    getFileByUid(message, uid) {
        if (!message || !uid) {
            return null;
        }
        return message.files.find(file => file.uid === uid) || null;
    }
    /**
     * Gets the currently pinned message.
     */
    getPinnedMessage() {
        return this.dataSource.data().find((m) => m.isPinned) || null;
    }
    /**
     * Adds a new message to the data source.
     */
    postMessage(message, currentUserId) {
        var _a;
        const messageInput = typeof message === "string" ? { text: message } : message;
        const messageData = Object.assign(Object.assign({ id: this.options.autoAssignId ? this.context.utilsService.guid() : undefined, timestamp: new Date(), files: [] }, messageInput), { authorId: ((_a = messageInput.authorId) === null || _a === void 0 ? void 0 : _a.toString()) || currentUserId });
        return this.dataSource.add(messageData);
    }
    /**
     * Updates a message in the data source.
     */
    updateMessage(message, newData) {
        if (!message) {
            return null;
        }
        let targetMessage = message;
        if (!(message instanceof kendo.data.ObservableObject)) {
            targetMessage = this.getMessageById(message.id);
        }
        if (!targetMessage) {
            return null;
        }
        for (const key in newData) {
            if (Object.prototype.hasOwnProperty.call(newData, key)) {
                targetMessage.set(key, newData[key]);
            }
        }
        return targetMessage;
    }
    /**
     * Marks a message as deleted.
     */
    removeMessage(message) {
        if (!message) {
            return false;
        }
        message.set("isDeleted", true);
        return true;
    }
    /**
     * Pins a message.
     */
    pinMessage(message) {
        if (!message) {
            return false;
        }
        // Unpin any currently pinned message
        const pinnedMessage = this.getPinnedMessage();
        if (pinnedMessage) {
            pinnedMessage.set("isPinned", false);
        }
        message.set("isPinned", true);
        return message;
    }
    /**
     * Clears the currently pinned message.
     */
    clearPinnedMessage() {
        const pinnedMessage = this.getPinnedMessage();
        if (pinnedMessage) {
            pinnedMessage.set("isPinned", false);
        }
    }
}
/** Default configuration options */
DataManager.DEFAULT_OPTIONS = {
    autoSync: true,
    schema: {
        model: {
            id: "id",
            fields: {
                id: { type: "string" },
                text: { type: "string" },
                authorId: { type: "string" },
                authorName: { type: "string" },
                authorImageUrl: { type: "string" },
                authorImageAltText: { type: "string" },
                replyToId: { type: "string", defaultValue: null },
                isDeleted: { type: "boolean", defaultValue: false },
                isPinned: { type: "boolean", defaultValue: false },
                isTyping: { type: "boolean", defaultValue: false },
                timestamp: { type: "date" },
                files: { parse: (value) => value || [] }
            }
        }
    }
};
/** Field mapping from chat options to data source fields */
DataManager.FIELD_MAP = {
    text: "textField",
    authorId: "authorIdField",
    authorName: "authorNameField",
    authorImageUrl: "authorImageUrlField",
    authorImageAltText: "authorImageAltTextField",
    id: "idField",
    timestamp: "timestampField",
    files: "filesField",
    replyToId: "replyToIdField",
    isDeleted: "isDeletedField",
    isPinned: "isPinnedField",
    isTyping: "isTypingField"
};
