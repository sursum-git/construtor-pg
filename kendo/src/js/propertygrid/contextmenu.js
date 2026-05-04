/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./../kendo.treelist.js";
import "./../kendo.menu.js";

(function($, undefined) {
    const kendo = window.kendo,
        ui = kendo.ui,
        TreeListContextMenu = ui.treelist.ContextMenu,
        extend = $.extend;

    let PropertyGridContextMenu = TreeListContextMenu.extend({
        init: function(element, options) {
            const that = this;

            TreeListContextMenu.fn.init.call(that, element, options);
        },
        defaultItems: {
            "separator": { name: "separator", separator: true },
            "copy": { name: "copy", text: "Copy", icon: "copy", command: "CopyCommand", softRules: "windowHasSelection",options: "copy:selection" },
            "copyName": { name: "copyName", text: "Copy Name", icon: "file-txt", command: "CopyCommand", options: "copy:name" },
            "copyDeclaration": { name: "copyDeclatarion", text: "Copy Declaration", icon: "file-data", command: "CopyCommand", options: "copy:declaration" },
            "resize": { name: "resize", text: "Resize Column", icon: "arrows-left-right", rules: "isResizable", command: "ResizeColumnCommand",softRules: "isNotGroupColumn" },
            "reset": { name: "reset", text: "Reset", icon: "arrow-rotate-ccw", command: "ResetCommand", rules: "isEditable", softRules: "isDirty;isNotInEditMode" },
            "expandItem": { name: "expandItem", text: "Expand Item", icon: "folder-open", softRules: "isExpandable", command: "ToggleItemCommand", options: "expand:true" },
            "collapseItem": { name: "collapseItem", text: "Collapse Item", icon: "folder", softRules: "isCollapsible", command: "ToggleItemCommand", options: "expand:false" }
        }
    });

    kendo.ui.propertygrid = kendo.ui.propertygrid || {};

    extend(kendo.ui.propertygrid, {
        ContextMenu: PropertyGridContextMenu
    });
})(window.kendo.jQuery);