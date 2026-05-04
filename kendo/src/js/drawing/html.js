/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo-drawing.js";
import {
    drawText,
    getFontFaces,
    drawDOM
} from "@progress/kendo-drawing";

window.kendo.drawing = window.kendo.drawing || {};

(function($) {

    var kendo = window.kendo;
    var drawing = kendo.drawing;

    drawing.drawDOM = function(element, options) {
        if (typeof options?.template === "string") {
            options.template = kendo.template(options.template);
        }

        let promise = drawDOM($(element)[0], options);
        return kendo.convertPromiseToDeferred(promise);
    };

    // Aliases used by spreadsheet/print.js
    drawing.drawDOM.drawText = drawText;
    drawing.drawDOM.getFontFaces = getFontFaces;

})(window.kendo.jQuery);
