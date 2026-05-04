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

    if (ui && ui.MultiColumnComboBox) {
        ui.MultiColumnComboBox.requestData = function(selector) {

            var multicolumncombobox = $(selector).data("kendoMultiColumnComboBox");

            if (!multicolumncombobox) {
                return;
            }

            var filter = multicolumncombobox.dataSource.filter();
            var value = multicolumncombobox.input.val();

            if (!filter || !filter.filters.length) {
                value = "";
            }

            return { text: value };
        };
    }

})(window.kendo.jQuery);
