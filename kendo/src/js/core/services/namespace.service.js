/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Namespace Service
 *
 * Manages the data attribute namespace prefix used throughout Kendo UI.
 * This allows customization of data-role attributes (e.g., data-kendo-role instead of data-role).
 */
export class NamespaceService {
    constructor() {
        this._ns = "";
    }
    /**
     * Get the current namespace prefix
     */
    get ns() {
        return this._ns;
    }
    /**
     * Set the namespace prefix
     * @param value - The namespace prefix (e.g., "kendo-")
     */
    setNs(value) {
        this._ns = value;
    }
}
