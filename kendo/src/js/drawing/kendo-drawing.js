/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import {
    drawing,
    pdf,
    geometry,
    drawText,
    Color
}from "@progress/kendo-drawing";
import "../kendo.color.js";
import "../util/text-metrics.js";

(function($) {

window.kendo = window.kendo || {};
let pdfExtended = kendo.deepExtend({}, pdf);
kendo.deepExtend(kendo, {
    drawing: $.extend(true, {}, drawing, { Segment: geometry.Segment, pdf: pdfExtended }),
    pdf: pdfExtended,
    geometry: geometry
});

kendo.drawing.exportPDF = function(group, options) {
    let promise = pdf.exportPDF(group, options);
    return kendo.convertPromiseToDeferred(promise);
};

kendo.drawing.exportImage = function(group, options) {
    let promise = drawing.exportImage(group, options);
    return kendo.convertPromiseToDeferred(promise);
};

kendo.drawing.exportSVG = function(group, options) {
    let promise = drawing.exportSVG(group, options);
    return kendo.convertPromiseToDeferred(promise);
};

kendo.drawing.drawText = drawText;
kendo.drawing.Color = Color;
kendo.dataviz.drawing = kendo.drawing;
kendo.dataviz.geometry = geometry;

})(window.kendo.jQuery);