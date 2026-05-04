/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { Widget } from "./widget";
export class DataBoundWidget extends Widget {
    /**
     * Retrieves the data items currently bound to the widget.
     * @returns An array of data items
     */
    dataItems() {
        const dataSource = this.dataSource;
        if (dataSource && typeof dataSource.view === "function") {
            return dataSource.flatView();
        }
        return [];
    }
}
DataBoundWidget.fn = DataBoundWidget.prototype;
