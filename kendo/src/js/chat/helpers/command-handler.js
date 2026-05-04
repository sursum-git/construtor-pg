/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { COMMANDS, EVENTS } from "../constants";
export class CommandHandler {
    constructor(context) {
        this.context = context;
    }
    commandExecute(e) {
        const message = this.context.dataItem(e.message);
        const file = this.context.fileDataItem(message, e.file);
        const type = e.type;
        if (!file) {
            switch (type) {
                case COMMANDS.reply:
                    this.messageReply(message);
                    break;
                case COMMANDS.copy:
                    this.messageCopy(message);
                    break;
                case COMMANDS.pin:
                    this.messagePin(message);
                    break;
                case COMMANDS.delete:
                    this.messageDelete(message);
                    break;
            }
        }
        else {
            switch (type) {
                case COMMANDS.download:
                    this.context.trigger(EVENTS.download, { files: [file], message });
                    break;
            }
            this.context.trigger(EVENTS.fileMenuAction, { type, file, message });
        }
    }
    contextMenuExecute(e) {
        this.commandExecute(e);
        const message = this.context.dataItem(e.message);
        const type = e.type;
        this.context.trigger(EVENTS.contextMenuAction, { type, message });
    }
    messageReply(message) {
        const isOwnMessage = message.authorId === this.context.getUserId();
        this.context.setCurrentMessageReplyId(message.id);
        this.context.setReplyMessage(message, isOwnMessage);
        this.context.setupAriaAttributes();
    }
    messageCopy(message) {
        if (navigator && navigator.clipboard) {
            navigator.clipboard.writeText(message.text);
        }
    }
    messagePin(message) {
        this.context.pinMessage(message);
    }
    messageDelete(message) {
        this.context.removeMessage(message);
    }
    messageContextMenuOpen(e) {
        const message = this.context.dataItem(e.message);
        if (!message) {
            return;
        }
        const isAuthor = message.authorId === this.context.getUserId();
        this.context.toggleDeleteVisibility(isAuthor);
    }
}
