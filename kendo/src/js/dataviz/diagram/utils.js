/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { Utils, Range, Ticker } from "@progress/kendo-diagram-common";

(function() {
    var kendo = window.kendo,
        diagram = kendo.dataviz.diagram = {};

    kendo.deepExtend(diagram, {
        init: function(element) {
            kendo.init(element, diagram.ui);
        },

        Utils: Utils,
        Range: Range,
        Ticker: Ticker
    });
})();
