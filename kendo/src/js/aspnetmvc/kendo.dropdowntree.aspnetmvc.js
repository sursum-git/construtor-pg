/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.data.aspnetmvc.js";

(function($, undefined) {
    var kendo = window.kendo,
        ui = kendo.ui;

    if (ui && ui.DropDownTree) {
        ui.DropDownTree.requestData = function(selector) {
            var dropdowntree = $(selector).data("kendoDropDownTree");

            if (!dropdowntree) {
                return;
            }

            var filter = dropdowntree.dataSource.filter();
            var filterInput = dropdowntree.filterInput;
            var value = filterInput ? filterInput.val() : "";

            if (!filter || !filter.filters.length) {
                value = "";
            }

            return { text: value };
        };
    }

})(window.kendo.jQuery);
