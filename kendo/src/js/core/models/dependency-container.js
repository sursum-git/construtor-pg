/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Service Container Models
 *
 * Simple DI container for Kendo widgets. Services can be:
 * - Singleton: one instance shared everywhere
 * - Scoped: new instance created per resolution
 */
export var ServiceLifetime;
(function (ServiceLifetime) {
    ServiceLifetime["Singleton"] = "singleton";
    ServiceLifetime["Scoped"] = "scoped";
})(ServiceLifetime || (ServiceLifetime = {}));
