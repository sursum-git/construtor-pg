/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Date utility type definitions
 */
/**
 * Date field mapping for format parsing
 */
export const DATE_FIELD_MAP = {
    "G": "era",
    "y": "year",
    "q": "quarter",
    "Q": "quarter",
    "M": "month",
    "L": "month",
    "d": "day",
    "E": "weekday",
    "c": "weekday",
    "e": "weekday",
    "h": "hour",
    "H": "hour",
    "k": "hour",
    "K": "hour",
    "m": "minute",
    "s": "second",
    "a": "dayperiod",
    "t": "dayperiod",
    "x": "zone",
    "X": "zone",
    "z": "zone",
    "Z": "zone"
};
/**
 * Name types for date formatting
 */
export const NAME_TYPES = {
    month: {
        type: "months",
        minLength: 3,
        standAlone: "L"
    },
    quarter: {
        type: "quarters",
        minLength: 3,
        standAlone: "q"
    },
    weekday: {
        type: "days",
        minLength: {
            E: 0,
            c: 3,
            e: 3
        },
        standAlone: "c"
    },
    dayperiod: {
        type: "dayPeriods",
        minLength: 0
    },
    era: {
        type: "eras",
        minLength: 0
    }
};
