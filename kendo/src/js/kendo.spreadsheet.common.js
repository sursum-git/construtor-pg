/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.core.js";
import * as commonEngine from "@progress/kendo-spreadsheet-common";

export const __meta__ = {
    id: "spreadsheet.common",
    name: "Spreadsheet common engine",
    category: "web",
    description: "Spreadsheet component",
    depends: ["core"]
};

(function(kendo) {
    let $ = kendo.jQuery;
    $.extend(kendo.spreadsheet, commonEngine);
    kendo.spreadsheet.commonEngine = commonEngine;
})(kendo);