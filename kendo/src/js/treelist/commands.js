/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

(function($, undefined) {
    var kendo = window.kendo,
        extend = $.extend,
        Class = kendo.Class;

    var Command = Class.extend({
        init: function(options) {
            this.options = options;
            this.treelist = options.treelist;
        }
    });

    var SortCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist,
                dataSource = treelist.dataSource,
                sort = dataSource.sort() || [],
                options = that.options,
                dir = options.dir,
                field = options.target.attr(kendo.attr("field")),
                multipleMode = treelist.options.sortable.mode && treelist.options.sortable.mode === "multiple",
                compare = treelist.options.compare,
                length, idx;

            if (multipleMode) {
                for (idx = 0, length = sort.length; idx < length; idx++) {
                    if (sort[idx].field === field) {
                        sort.splice(idx, 1);
                        break;
                    }
                }
                sort.push({ field: field, dir: dir, compare: compare });
            } else {
                sort = [{ field: field, dir: dir, compare: compare }];
            }

            dataSource.sort(sort);
        },
    });

    var AddCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist;

            treelist.addRow();
        }
    });

    var CreateChildCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist,
                target = that.options.target.closest("tr");

            treelist.addRow(target);
        }
    });

    var EditCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist,
                inCellMode = treelist._editMode() === "incell",
                target = inCellMode ? that.options.target : that.options.target.closest("tr");

            if (inCellMode) {
                treelist.editCell(target);
            } else {
                treelist.editRow(target);
            }
        }
    });

    var DeleteCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist,
                target = that.options.target.closest("tr");

            treelist.removeRow(target);
        }
    });

    var SelectRowCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist,
                selectMode = kendo.ui.Selectable.parseOptions(treelist.options.selectable),
                target = that.options.target.closest("tr");

            treelist.select(selectMode.cell ? target.find('td') : target);
        }
    });

    var SelectAllRowsCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist,
                selectMode = kendo.ui.Selectable.parseOptions(treelist.options.selectable),
                rows = treelist.items();

            treelist.select(selectMode.cell ? rows.find('td') : rows);
        }
    });

    var ClearSelectionCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist;

            treelist.clearSelection();
        }
    });

    var ExportPDFCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist;

            treelist.saveAsPDF();
        }
    });

    var ExportExcelCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist;

                treelist.saveAsExcel();
        }
    });

    var ToggleItemCommand = Command.extend({
        exec: function() {
            var that = this,
                treelist = that.treelist,
                target = that.options.target,
                options = that.options,
                expand = options.expand === 'true';

                if (expand) {
                    treelist.expand(target);
                } else {
                    treelist.collapse(target);
                }
        }
    });

    kendo.ui.treelist = kendo.ui.treelist || {};

    extend(kendo.ui.treelist, {
        TreeListCommand: Command,
        commands: {
            SortCommand: SortCommand,
            AddCommand: AddCommand,
            CreateChildCommand: CreateChildCommand,
            EditCommand: EditCommand,
            DeleteCommand: DeleteCommand,
            SelectRowCommand: SelectRowCommand,
            SelectAllRowsCommand: SelectAllRowsCommand,
            ClearSelectionCommand: ClearSelectionCommand,
            ExportPDFCommand: ExportPDFCommand,
            ExportExcelCommand: ExportExcelCommand,
            ToggleItemCommand: ToggleItemCommand
        }
    });
})(window.kendo.jQuery);