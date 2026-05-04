/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

export const __meta__ = {
    id: "aspnetmvc.ready",
    name: "AspNetMvcReady",
    category: "wrappers",
    description: "ASP.NET MVC/Core script that ensures kendo scripts/modules are ready before widget initialization.",
    depends: []
};

window.kendo.SYNCREADY_EVENT = "kendo:aspnetmvc:syncready";

window.kendo.syncReady = window.kendo?.syncReady || function(cb) {
    window.addEventListener(window.kendo.SYNCREADY_EVENT, cb, { once: true });
};