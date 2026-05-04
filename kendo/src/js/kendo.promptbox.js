/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Kendo UI PromptBox Widget
 *
 * This file serves as the entry point for the PromptBox widget.
 */
import "./kendo.core.js";
import "./kendo.textarea.js";
import "./kendo.textbox.js";
import "./kendo.html.button.js";
import { PromptBox } from "./promptbox";
import { inject, WidgetRegistryService } from "./core";

export const __meta__ = {
    id: "promptbox",
    name: "PromptBox",
    category: "web",
    description: "The PromptBox component - a composite input for chat interfaces.",
    depends: ["core", "textarea", "textbox", "html.button"]
};

// Register the widget
inject(WidgetRegistryService).register(PromptBox);

// Re-export for consumers
export { PromptBox };

export default kendo;
