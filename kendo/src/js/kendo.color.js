/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

 import "./kendo.core.js";
 import {
    Color,
    namedColors,
    parseColor
} from "@progress/kendo-drawing";

export const __meta__ = {
    id: "color",
    name: "Color utils",
    category: "framework",
    advanced: true,
    description: "Color utilities used across components",
    depends: [ "core" ]
};

window.kendo = window.kendo || {};

kendo.deepExtend(kendo, {
    parseColor: parseColor,
    namedColors: namedColors,
    Color: Color
});

