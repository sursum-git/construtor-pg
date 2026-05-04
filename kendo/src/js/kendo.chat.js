/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Kendo UI Chat Widget
 *
 * This file serves as the entry point for the Chat widget.
 */
import "./kendo.core.js"; // Still required for the time being, because we need to access the global kendo object.
import { Chat } from "./chat";
import { inject, UtilsService, WidgetRegistryService } from "./core";

export const __meta__ = {
    id: "chat",
    name: "Chat",
    category: "web",
    description: "The Chat component.",
    depends: ["data", "draganddrop", "html.button", "textarea", "menu", "avatar", "toolbar", "speechtotextbutton"]
};

// Keep the chat namespace for backwards compatibility
window.kendo.chat = {};
$.extend(window.kendo.chat, {
    ChatView: {},
    Component: {},
    Components: {},
    Templates: {},
    getTemplate: () => inject(UtilsService).logToConsole("The getTemplate method is deprecated. Use one of the built-in templates or append elements manually.", "warn"),
    getComponent: () => inject(UtilsService).logToConsole("The getComponent method is deprecated. Use one of the built-in templates or append elements manually.", "warn"),
    registerTemplate: () => inject(UtilsService).logToConsole("The registerTemplate method is deprecated. Use one of the built-in templates or append elements manually.", "warn"),
    registerComponent: () => inject(UtilsService).logToConsole("The registerComponent method is deprecated. Use one of the built-in templates or append elements manually.", "warn")
});

// Register the widget
inject(WidgetRegistryService).register(Chat);

// Re-export for consumers
export { Chat };

export default kendo;