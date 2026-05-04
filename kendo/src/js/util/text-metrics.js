/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../kendo.core.js";
import { drawing } from "@progress/kendo-drawing";

(function($) {

window.kendo.util = window.kendo.util || {};
kendo.deepExtend(kendo.util, {
    LRUCache: drawing.util.LRUCache,
    TextMetrics: drawing.util.TextMetrics,
    measureText: drawing.util.measureText,
    objectKey: drawing.util.objectKey,
    hashKey: drawing.util.hashKey,
    normalizeText: drawing.util.normalizeText,
    encodeBase64: drawing.util.encodeBase64
});

})(window.kendo.jQuery);