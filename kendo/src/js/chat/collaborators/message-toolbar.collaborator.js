/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.toolbar.js"; // Still required until kendo.ui is modularized
import { Widget } from "../../core/base/widget.js";
import { STYLES, DOT, CLICK } from "../constants.js";
/**
 * MessageToolbar provides toolbar functionality for chat messages.
 * Handles quick actions that can be performed on messages through toolbar buttons.
 *
 * This is an internal collaborator that extends Widget for event handling.
 */
export class MessageToolbar extends Widget {
    /**
     * Constructs a new MessageToolbar instance.
     */
    constructor(element, options) {
        super(element, $.extend(true, {}, MessageToolbar.options, options));
        this.extendItemsConfig();
        this.create();
        this.attachEvents();
    }
    /**
     * Creates the underlying Kendo UI ToolBar.
     */
    create() {
        const existingToolBar = this.element.data("kendoToolBar");
        if (existingToolBar) {
            existingToolBar.destroy();
        }
        const toolbarOptions = $.extend({}, this.options, {
            fillMode: "flat"
        });
        delete toolbarOptions.name;
        this.toolbar = new kendo.ui.ToolBar(this.element, toolbarOptions);
    }
    /**
     * Extends items configuration with default properties.
     */
    extendItemsConfig() {
        const items = this.options.items;
        if (items) {
            items.forEach((item) => {
                item.attributes = item.attributes || {};
                const ariaLabel = item.attributes["aria-label"];
                item.attributes["data-command"] = item.name.toLowerCase();
                item.attributes["aria-label"] = ariaLabel !== null && ariaLabel !== void 0 ? ariaLabel : item.name;
                item.type = "button";
                item.fillMode = "flat";
                item.overflow = "never";
            });
        }
    }
    /**
     * Attaches event handlers to the toolbar.
     */
    attachEvents() {
        this.toolbar.bind(CLICK, this.onClick.bind(this));
    }
    /**
     * Handles toolbar button clicks.
     */
    onClick(e) {
        const message = e.target.closest(DOT + STYLES.message);
        const command = e.target.data("command");
        if (command) {
            this.executeCommand(command, e.target, message);
        }
    }
    /**
     * Executes a command from the toolbar.
     */
    executeCommand(command, item, message) {
        this.trigger("execute", { type: command, item, message });
    }
    destroy() {
        if (this.toolbar) {
            this.toolbar.destroy();
            this.toolbar = null;
        }
        this.element.empty();
    }
}
MessageToolbar.options = {
    name: "MessageToolbar"
};
