/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { inject } from "../core/service-container";
import { CssPropertiesService } from "../core/services/css-properties.service";
// Main PromptBox widget export
export { PromptBox } from "./promptbox.widget";
// Models
export * from "./models";
// Constants
export * from "./constants";
// Templates
export * from "./templates";
// Register PromptBox CSS properties
// PromptBox uses k-input prefix for fillMode (k-input-solid, k-input-outline, k-input-flat)
const cssProps = inject(CssPropertiesService);
cssProps.registerPrefix("PromptBox", "k-input-");
cssProps.registerValues("PromptBox", [{
        prop: "fillMode",
        values: cssProps.fillModeValues
    }]);
