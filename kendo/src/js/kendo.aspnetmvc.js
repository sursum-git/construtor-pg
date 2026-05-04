/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.data.js";
import "./kendo.combobox.js";
import "./kendo.dropdownlist.js";
import "./kendo.dropdowntree.js";
import "./kendo.multiselect.js";
import "./kendo.validator.js";
import "./kendo.aspnetmvc.ready.js";
import "./aspnetmvc/kendo.data.aspnetmvc.js";
import "./aspnetmvc/kendo.combobox.aspnetmvc.js";
import "./aspnetmvc/kendo.tabstrip.aspnetmvc.js";
import "./aspnetmvc/kendo.multicolumncombobox.aspnetmvc.js";
import "./aspnetmvc/kendo.dropdownlist.aspnetmvc.js";
import "./aspnetmvc/kendo.dropdowntree.aspnetmvc.js";
import "./aspnetmvc/kendo.multiselect.aspnetmvc.js";
import "./aspnetmvc/kendo.imagebrowser.aspnetmvc.js";
import "./aspnetmvc/kendo.validator.aspnetmvc.js";
import "./aspnetmvc/kendo.filemanager.aspnetmvc.js";

export const __meta__ = {
    id: "aspnetmvc",
    name: "ASP.NET MVC",
    category: "wrappers",
    description: "Scripts required by Telerik UI for ASP.NET MVC and Telerik UI for ASP.NET Core",
    depends: ["data", "combobox", "multicolumncombobox", "dropdownlist", "multiselect", "validator", "tabstrip" ,"aspnetmvc.ready"],
    features: [ {
        id: "module-scripts",
        name: "Modules",
        description: "Enables support for loading Kendo UI modules and using ordinary scripts via kendo.syncReady method.",
        depends: [ "aspnetmvc.ready" ]
    } ]
};

(function($, undefined) {
    $(function() {
        kendo.__documentIsReady = true;
        if (kendo.SYNCREADY_EVENT) {
            window.dispatchEvent(new CustomEvent(kendo.SYNCREADY_EVENT));
        }
    });

    function syncReady(cb) {
        if (kendo.__documentIsReady) { //sync operation
            cb();
        }
        else { //async operation
            $(cb);
        }
    }

    kendo.syncReady = syncReady;
})(window.kendo.jQuery);
export default kendo;
