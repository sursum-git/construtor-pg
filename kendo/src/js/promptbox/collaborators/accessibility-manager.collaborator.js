/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

export class AccessibilityManager {
    constructor(context) {
        this.context = context;
    }
    applyInitial() {
        var _a;
        const options = this.context.getOptions();
        const input = this.context.getInputElement();
        input.attr("aria-label", ((_a = options.messages) === null || _a === void 0 ? void 0 : _a.messageBoxTitle) || "Message");
        if (!options.enable) {
            this.context.wrapper.attr("aria-disabled", "true");
        }
        if (options.readonly) {
            this.updateInputAccessibility();
        }
        if (options.loading) {
            this.context.wrapper.attr("aria-busy", "true");
        }
    }
    updateInputAccessibility() {
        var _a;
        const options = this.context.getOptions();
        const input = this.context.getInputElement();
        if (!input) {
            return;
        }
        input.attr("aria-label", ((_a = options.messages) === null || _a === void 0 ? void 0 : _a.messageBoxTitle) || "Message");
        if (options.readonly) {
            input.attr("aria-readonly", "true");
        }
        else {
            input.removeAttr("aria-readonly");
        }
        if (!options.enable) {
            input.attr("aria-disabled", "true");
        }
        else {
            input.removeAttr("aria-disabled");
        }
    }
    updateWrapper(state) {
        if (state.disabled !== undefined) {
            if (state.disabled) {
                this.context.wrapper.attr("aria-disabled", "true");
            }
            else {
                this.context.wrapper.removeAttr("aria-disabled");
            }
        }
        if (state.loading !== undefined) {
            if (state.loading) {
                this.context.wrapper.attr("aria-busy", "true");
            }
            else {
                this.context.wrapper.removeAttr("aria-busy");
            }
        }
    }
}
