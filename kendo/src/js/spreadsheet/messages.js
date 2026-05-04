/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

(function(kendo) {

    kendo.spreadsheet.messages.workbook = {
        defaultSheetName: "Sheet"
    };

    kendo.spreadsheet.messages.view = {
        nameBox: "Name Box",
        formulaInput: "Formula Input",
        errors: {
            openUnsupported: "Unsupported format. Please select an .xlsx file.",
            shiftingNonblankCells: "Cannot insert cells due to data loss possibility. Select another insert location or delete the data from the end of your worksheet.",
            insertColumnWhenRowIsSelected: "Cannot insert column when all columns are selected.",
            insertRowWhenColumnIsSelected: "Cannot insert row when all rows are selected.",
            filterRangeContainingMerges: "Cannot create a filter within a range containing merges",
            sortRangeContainingMerges: "Cannot sort a range containing merges",
            cantSortMultipleSelection: "Cannot sort multiple selection",
            cantSortNullRef: "Cannot sort empty selection",
            cantSortMixedCells: "Cannot sort range containing cells of mixed shapes",
            validationError: "The value that you entered violates the validation rules set on the cell.",
            cannotModifyDisabled: "Cannot modify disabled cells.",
            insertRowBelowLastRow: "Cannot insert row below the last row.",
            insertColAfterLastCol: "Cannot insert column to the right of the last column."
        },
        tabs: {
            file: "File",
            home: "Home",
            insert: "Insert",
            format: "Format",
            data: "Data",
            view: "View"
        }
    };

    kendo.spreadsheet.messages.menus = {
        "cut": "Cut",
        "copy": "Copy",
        "paste": "Paste",
        "merge": "Merge",
        "unmerge": "Unmerge",
        "delete": "Delete",
        "hide": "Hide",
        "unhide": "Unhide",
        "bringToFront": "Bring to front",
        "sendToBack": "Send to back"
    };
})(window.kendo);
