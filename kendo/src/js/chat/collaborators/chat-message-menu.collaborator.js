/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.menu.js"; // Still required until kendo.ui is modularized
import { Widget } from "../../core/base/widget";
import { STYLES, DOT, REFERENCES } from "../constants";
/**
 * ChatMessageMenuCollaborator provides context menu functionality for chat messages.
 * Handles actions like reply, copy, pin, and delete for individual messages.
 *
 * This is an internal collaborator that extends Widget for event handling.
 */
export class ChatMessageMenuCollaborator extends Widget {
    /**
     * Constructs a new ChatMessageMenuCollaborator instance.
     */
    constructor(element, options) {
        super(element, $.extend(true, {}, ChatMessageMenuCollaborator.options, options));
        this.setCommandAttributes();
        this.create();
        this.attachEvents();
    }
    /**
     * Creates the underlying Kendo UI ContextMenu.
     */
    create() {
        this.menu = new kendo.ui.ContextMenu(this.element, this.options);
    }
    /**
     * Attaches event handlers to the context menu.
     */
    attachEvents() {
        this.menu
            .bind("select", this.onSelect.bind(this))
            .bind("open", this.onOpen.bind(this))
            .bind("close", this.onClose.bind(this));
    }
    /**
     * Sets command attributes on menu items for identification.
     */
    setCommandAttributes() {
        this.options.dataSource.forEach((item) => {
            item.attr = item.attr || {};
            item.attr["data-command"] = item.name.toLowerCase();
        });
    }
    /**
     * Handles menu item selection.
     */
    onSelect(e) {
        const item = $(e.item);
        const message = $(e.target).closest(DOT + STYLES.message);
        const command = item.data("command");
        if (command) {
            this.executeCommand(command, item, message);
        }
    }
    /**
     * Handles menu open event.
     */
    onOpen(e) {
        var _a;
        let setActive = true;
        const target = $(e.target);
        const originalTarget = $((_a = e.event) === null || _a === void 0 ? void 0 : _a.target).length ? $(e.event.target) : target;
        const isMessageRemoved = target.closest(DOT + STYLES.messageRemoved).length > 0;
        const hasTypingIndicator = target.find(DOT + STYLES.typingIndicator).length > 0;
        const isAttachmentMenuButton = originalTarget.closest(`[${REFERENCES.fileMenuButton}]`).length > 0;
        const message = target.closest(DOT + STYLES.message);
        if (isMessageRemoved || hasTypingIndicator || isAttachmentMenuButton || !message.length) {
            e.preventDefault();
            setActive = false;
        }
        target.toggleClass(STYLES.active, setActive);
        this.trigger("open", { message });
    }
    /**
     * Handles menu close event.
     */
    onClose(e) {
        const target = $(e.target);
        const message = target.closest(DOT + STYLES.message);
        target.removeClass(STYLES.active);
        this.trigger("close", { message });
    }
    /**
     * Executes a command from the menu.
     */
    executeCommand(command, item, message) {
        this.trigger("execute", { type: command, item, message });
    }
    /**
     * Toggles the visibility of the delete menu item.
     */
    toggleDeleteVisibility(visible) {
        const deleteItem = this.element.find("[data-command='delete']");
        if (deleteItem.length) {
            deleteItem.toggleClass(STYLES.hidden, !visible);
        }
    }
    destroy() {
        if (this.menu) {
            this.menu.destroy();
            this.menu = null;
            this.element.remove();
        }
    }
}
ChatMessageMenuCollaborator.options = {
    name: "ChatMessageMenu",
    filter: `${DOT}${STYLES.chatBubble}`,
    dataSource: [],
    keyboardAlignToAnchor: true
};
