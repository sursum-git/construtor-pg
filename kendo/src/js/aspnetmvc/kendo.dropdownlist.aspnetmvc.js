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
    let kendo = window.kendo,
        ui = kendo.ui;

    if (ui && ui.DropDownList) {
        ui.DropDownList.requestData = function(selector, cascadeFrom) {
            let dropdownlist = $(selector).data("kendoDropDownList");

            if (!dropdownlist) {
                return;
            }

            let filter = dropdownlist.dataSource.filter(),
                filterInput = dropdownlist.filterInput,
                value = filterInput ? filterInput.val() : "";

            if (!filter || !filter.filters.length) {
                value = "";
            }

            let data = {
                text: value
            };

            if(cascadeFrom != null && cascadeFrom.trim()) {
                data["cascadeFrom"] = $(`#${cascadeFrom}`).val();
            }

            return data;
        };
    }

})(window.kendo.jQuery);
