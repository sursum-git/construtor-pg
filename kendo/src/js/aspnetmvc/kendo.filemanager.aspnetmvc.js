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
        extend = $.extend;

    extend(true, kendo.data, {
        schemas: {
            "filemanager": {
                data: function(data) {
                    return data || [];
                },
                model: {
                    id: "path",
                    hasChildren: "hasDirectories",
                    fields: {
                        name: { field: "Name", editable: true, type: "string", defaultValue: "New Folder" },
                        size: { field: "Size", editable: false, type: "number" },
                        path: { field: "Path", editable: false, type: "string" },
                        extension: { field: "Extension", editable: false, type: "string" },
                        isDirectory: { field: "IsDirectory", editable: false, defaultValue: true, type: "boolean" },
                        hasDirectories: { field: "HasDirectories", editable: false, defaultValue: false, type: "boolean" },
                        created: { field: "Created", type: "date", editable: false },
                        createdUtc: { field: "CreatedUtc", type: "date", editable: false },
                        modified: { field: "Modified", type: "date", editable: false },
                        modifiedUtc: { field: "ModifiedUtc", type: "date", editable: false }
                    }
                }
            }
        }
    });
})(window.kendo.jQuery);
