/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { STYLES, REFERENCES } from "../constants";
export class ActionButtonManager {
    constructor(context) {
        this.context = context;
    }
    getSendButton() {
        return this.context.wrapper.find(`[${REFERENCES.sendButton}]`);
    }
    setLoading(loading) {
        const sendButton = this.getSendButton();
        const messages = this.context.getMessages();
        const settings = this.context.getSettings();
        if (loading) {
            sendButton.addClass(`${STYLES.generating} ${STYLES.active}`);
            sendButton.attr("title", messages.actionButtonLoading);
            sendButton.attr("aria-label", messages.actionButtonLoading);
            const icon = sendButton.find(".k-icon, .k-svg-icon");
            if (icon.length) {
                const loadingIcon = (settings === null || settings === void 0 ? void 0 : settings.loadingIcon) || "stop-sm";
                kendo.ui.icon(icon, { icon: loadingIcon });
            }
        }
        else {
            sendButton.removeClass(`${STYLES.generating} ${STYLES.active}`);
            sendButton.attr("title", messages.actionButton);
            sendButton.attr("aria-label", messages.actionButton);
            const icon = sendButton.find(".k-icon, .k-svg-icon");
            if (icon.length) {
                const sendIcon = (settings === null || settings === void 0 ? void 0 : settings.icon) || "arrow-up-outline";
                kendo.ui.icon(icon, { icon: sendIcon });
            }
        }
        this.updateState();
    }
    updateState() {
        const sendButton = this.getSendButton();
        const isDisabled = this.context.isDisabled();
        if (isDisabled) {
            sendButton.addClass(STYLES.disabled);
            sendButton.attr("aria-disabled", "true");
            return;
        }
        sendButton.removeAttr("aria-disabled");
        if (this.context.isLoading()) {
            sendButton.removeClass(STYLES.disabled);
            return;
        }
        if (this.context.hasContent()) {
            sendButton.removeClass(STYLES.disabled);
        }
        else {
            sendButton.addClass(STYLES.disabled);
        }
    }
    updateMessages() {
        const sendButton = this.getSendButton();
        const messages = this.context.getMessages();
        if (this.context.isLoading()) {
            sendButton.attr("title", messages.actionButtonLoading);
            sendButton.attr("aria-label", messages.actionButtonLoading);
        }
        else {
            sendButton.attr("title", messages.actionButton);
            sendButton.attr("aria-label", messages.actionButton);
        }
    }
}
