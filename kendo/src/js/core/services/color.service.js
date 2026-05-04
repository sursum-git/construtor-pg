/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Color Service Implementation
 * Provides utilities for extracting theme colors.
 
 */
export class ColorService {
    constructor($) {
        this.$ = $;
    }
    /**
     * Get series colors from CSS custom properties.
     */
    getSeriesColors() {
        const seriesColorsTemplate = '<div class="k-var--series-a"></div>' +
            '<div class="k-var--series-b"></div>' +
            '<div class="k-var--series-c"></div>' +
            '<div class="k-var--series-d"></div>' +
            '<div class="k-var--series-e"></div>' +
            '<div class="k-var--series-f"></div>';
        const series = this.$(seriesColorsTemplate);
        const colors = [];
        series.appendTo(this.$("body"));
        series.each((_i, item) => {
            colors.push(this.$(item).css("background-color"));
        });
        series.remove();
        return colors;
    }
}
