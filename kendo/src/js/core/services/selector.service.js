/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Selector Service Implementation
 * Provides utilities for building CSS selectors.
 
 */
export class SelectorService {
    /**
     * Convert space-separated class names to a CSS selector.
     */
    selectorFromClasses(classes) {
        return "." + classes.split(" ").join(".");
    }
    /**
     * Build a role selector for data-role attributes.
     */
    roleSelector(role, ns) {
        return role.replace(/(\S+)/g, "[data-" + ns + "role=$1],").slice(0, -1);
    }
    /**
     * Build a directive selector for mobile components.
     */
    directiveSelector(directives) {
        const selectors = directives.split(" ");
        if (selectors) {
            for (let i = 0; i < selectors.length; i++) {
                if (selectors[i] !== "view") {
                    selectors[i] = selectors[i].replace(/(\w*)(view|bar|strip|over)$/, "$1-$2");
                }
            }
        }
        return selectors.join(" ").replace(/(\S+)/g, "kendo-mobile-$1,").slice(0, -1);
    }
}
