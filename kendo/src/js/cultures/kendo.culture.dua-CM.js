/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

(function( window, undefined ) {
    kendo.cultures["dua-CM"] = {
        name: "dua-CM",
        numberFormat: {
            pattern: ["-n"],
            decimals: 2,
            ",": " ",
            ".": ",",
            groupSize: [3],
            percent: {
                pattern: ["-n %","n %"],
                decimals: 2,
                ",": " ",
                ".": ",",
                groupSize: [3],
                symbol: "%"
            },
            currency: {
                name: "Central African CFA Franc",
                abbr: "XAF",
                pattern: ["-n $","n $"],
                decimals: 0,
                ",": " ",
                ".": ",",
                groupSize: [3],
                symbol: "FCFA"
            }
        },
        calendars: {
            standard: {
                days: {
                    names: ["éti","mɔ́sú","kwasú","mukɔ́sú","ŋgisú","ɗónɛsú","esaɓasú"],
                    namesAbbr: ["ét","mɔ́s","kwa","muk","ŋgi","ɗón","esa"],
                    namesShort: ["ét","mɔ́s","kwa","muk","ŋgi","ɗón","esa"]
                },
                months: {
                    names: ["dimɔ́di","ŋgɔndɛ","sɔŋɛ","diɓáɓá","emiasele","esɔpɛsɔpɛ","madiɓɛ́díɓɛ́","diŋgindi","nyɛtɛki","mayésɛ́","tiníní","eláŋgɛ́"],
                    namesAbbr: ["di","ŋgɔn","sɔŋ","diɓ","emi","esɔ","mad","diŋ","nyɛt","may","tin","elá"]
                },
                AM: ["idiɓa","idiɓa","IDIƁA"],
                PM: ["ebyámu","ebyámu","EBYÁMU"],
                patterns: {
                    d: "d/M/yyyy",
                    D: "dddd d MMMM yyyy",
                    F: "dddd d MMMM yyyy HH:mm:ss",
                    g: "d/M/yyyy HH:mm",
                    G: "d/M/yyyy HH:mm:ss",
                    m: "MMMM d",
                    M: "MMMM d",
                    s: "yyyy'-'MM'-'dd'T'HH':'mm':'ss",
                    t: "HH:mm",
                    T: "HH:mm:ss",
                    u: "yyyy'-'MM'-'dd HH':'mm':'ss'Z'",
                    y: "yyyy MMMM",
                    Y: "yyyy MMMM"
                },
                "/": "/",
                ":": ":",
                firstDay: 1
            }
        }
    };
})();
