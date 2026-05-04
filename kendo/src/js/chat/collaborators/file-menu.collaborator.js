/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.dropdownbutton.js"; // Still required until kendo.ui is modularized
import { Widget } from "../../core/base/widget.js";
import { ICONS, STYLES, DOT } from "../constants.js";
/**
 * FileMenu provides dropdown button functionality for file attachments.
 * Handles actions like download, preview, and delete for individual files.
 *
 * This is an internal collaborator that extends Widget for event handling.
 */
export class FileMenu extends Widget {
    /**
     * Constructs a new FileMenu instance.
     */
    constructor(element, options) {
        super(element, $.extend(true, {}, FileMenu.options, options));
        this.setCommandAttributes();
        this.createDropdownButton();
    }
    /**
     * Creates a dropdown button on the element.
     */
    createDropdownButton() {
        this.dropdownButton = new kendo.ui.DropDownButton(this.element, {
            items: this.options.items,
            fillMode: "flat",
            icon: ICONS.attachmentMenu
        });
        this.dropdownButton
            .bind("click", this.onClick.bind(this))
            .bind("open", this.onOpen.bind(this))
            .bind("close", this.onClose.bind(this));
    }
    /**
     * Sets command attributes on menu items for identification.
     */
    setCommandAttributes() {
        this.options.items.forEach((item) => {
            item.attributes = item.attributes || {};
            item.attributes["data-command"] = item.name.toLowerCase();
            item.id = item.name.toLowerCase();
        });
    }
    /**
     * Handles dropdown button item click.
     */
    onClick(e) {
        const command = e.id;
        const file = $(e.sender.element).closest(DOT + STYLES.file);
        const message = $(e.sender.element).closest(DOT + STYLES.message);
        if (command) {
            this.executeCommand(command, $(e.sender.element), file, message);
        }
    }
    /**
     * Handles dropdown open event.
     */
    onOpen(e) {
        let setActive = true;
        const target = $(e.sender.element);
        if (target.closest(DOT + STYLES.messageRemoved).length || target.find(DOT + STYLES.typingIndicator).length) {
            e.preventDefault();
            setActive = false;
        }
        this.setActive(target, setActive);
    }
    /**
     * Handles dropdown close event.
     */
    onClose(e) {
        const target = $(e.sender.element);
        const message = target.closest(DOT + STYLES.message);
        this.setActive(target, false);
        this.trigger("close", { message });
    }
    /**
     * Executes a command from the file menu.
     */
    executeCommand(command, item, file, message) {
        this.trigger("execute", { type: command, item, file, message });
    }
    /**
     * Sets the active state for a target element.
     */
    setActive(target, active) {
        const bubble = target.closest(DOT + STYLES.bubble);
        if (active) {
            bubble.addClass(STYLES.active);
        }
        else {
            bubble.removeClass(STYLES.active);
        }
    }
    destroy() {
        if (this.dropdownButton) {
            this.dropdownButton.destroy();
            this.dropdownButton = null;
        }
        super.destroy();
    }
}
FileMenu.options = {
    name: "ChatFileMenu",
    items: []
};
