/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../pdfviewer/upload.js";
import { resetAnnotationEditorMode, setInteractionMode } from "./annotations/annotation-manager.js";
import { PdfViewerInteractionMode } from '@progress/kendo-pdfviewer-common';
import AnnotationToolbar from "./annotations/annotation-toolbar.js";

(function($, undefined) {
    var kendo = window.kendo,
        extend = $.extend,
        progress = kendo.ui.progress,
        Class = kendo.Class,
        UploadHelper = kendo.pdfviewer.UploadHelper,
        ZOOMSTART = "zoomStart",
        ZOOMEND = "zoomEnd";

    var Command = Class.extend({
        init: function(options) {
            this.options = options;
            this.viewer = options.viewer;
            this.errorMessages = this.viewer.options.messages.errorMessages;
        }
    });

    var OpenCommand = Command.extend({
        init: function(options) {
            Command.fn.init.call(this, options);
            this._uploadHelper = new UploadHelper(this.viewer);
        },
        exec: function() {
            this.viewer._upload = this.viewer._upload || this._uploadHelper._initUpload();
            this.viewer._upload.element.click();
        },
    });

    var PageChangeCommand = Command.extend({
        exec: function() {
            var targetPage = this.options.value,
                viewer = this.viewer,
                current, total;

            if (isNaN(targetPage)) {
                current = viewer._pageNum;
                total = viewer.document.total || viewer.document.numPages;

                switch (targetPage) {
                    case "first": targetPage = 1;
                        break;
                    case "prev": targetPage = current > 1 ? current - 1 : 1;
                        break;
                    case "next": targetPage = current < total ? current + 1 : total;
                        break;
                    case "last": targetPage = total;
                        break;
                }
            } else {
                targetPage = Number(targetPage);
            }

            viewer.activatePage(targetPage, false);
        }
    });

    var DownloadCommand = Command.extend({
        exec: function() {
            const that = this;
            if (!that.viewer.document) {
                that.viewer._triggerError({
                    message: that.errorMessages.notFound
                });
                return;
            }

            const fileName = (that.viewer.document.info && that.viewer.document.info.title) ||
            that.viewer.options.messages.defaultFileName;

            if (that.viewer._isDPLProcessor()) {
                that.viewer.processor.downloadFile(fileName);
            } else {
                that.viewer.pdfViewerCommon.downloadFile({ fileName });
            }
        }
    });

    var ExportCommand = Command.extend({
        init: function(options) {
            options = $.extend(options, this.options);
            Command.fn.init.call(this, options);
        },
        exec: function() {
            var dialog = (this.viewer._saveDialog || this._initDialog());

            dialog._updateModel({
                pagesCount: (this.viewer.document && this.viewer.document.total) || 1,
                page: this.viewer.options.page
            });

            dialog.open();
        },
        apply: function(viewModel) {
            var extension = viewModel.extension;

            if (extension === ".png") {
                this.viewer.exportImage(viewModel);
            } else if (extension === ".svg") {
                this.viewer.exportSVG(viewModel);
            }
        },
        _initDialog: function() {
            this.viewer._saveDialog = new kendo.pdfviewer.dialogs.ExportAsDialog({
                apply: this.apply.bind(this),
                pagesCount: (this.viewer.document && this.viewer.document.total) || 1,
                messages: this.viewer.options.messages
            });
            return this.viewer._saveDialog;
        }
    });

    var EnableSelectionCommand = Command.extend({
        exec: function() {
            const that = this,
                viewer = that.viewer;

            viewer.toolbar.enable(viewer.toolbar.element.find("[data-command=AnnotationsCommand]"), true);
            setInteractionMode(viewer.pdfViewerCommon, PdfViewerInteractionMode.TextSelection);
        }
    });

    var EnablePanCommand = Command.extend({
        exec: function() {
            const that = this,
                viewer = that.viewer;

            viewer.toolbar.enable(viewer.toolbar.element.find("[data-command=AnnotationsCommand]"), false);
            if (viewer.annotationsToolbar) {
                viewer.annotationsToolbar.destroy();
            }
            resetAnnotationEditorMode(viewer.pdfViewerCommon);
            setInteractionMode(viewer.pdfViewerCommon, PdfViewerInteractionMode.Pan);
        }
    });

    const OpenSearchCommand = Command.extend({
        init: function(options) {
            const that = this;

            that.viewer = options.viewer;

            if (!that.viewer.searchDialog) {
                that.viewer.searchDialog = new kendo.pdfviewer.dialogs.SearchDialog({
                    pageContainer: that.viewer.pageContainerWrapper,
                    position: {
                        top: that.viewer.pageContainer.offset().top,
                        left: that.viewer.pageContainer.offset().left
                    },
                    messages: that.viewer.options.messages.dialogs.search,
                    open: that._open.bind(that),
                    next: that._next.bind(that),
                    prev: that._prev.bind(that),
                    close: that._close.bind(that)
                });
            }

            Command.fn.init.call(that, options);
        },
        exec: function() {
            const that = this;

            that.viewer.searchDialog.open();
        },
        _open: function() {
            const that = this;

            that.changeHandler = that._change.bind(that);
            that.viewer.searchDialog.searchModel.bind("change", that.changeHandler);
        },
        _close: function() {
            const that = this;
            that.viewer.pdfViewerCommon.clearSearch();
            that.viewer.toolbar.element.find("[tabindex=0]").trigger("focus");
            that.viewer.searchDialog.searchModel.unbind("change", that.changeHandler);
            that.matches = [];
            that.matchIndex = null;
            that._updateSearchModel();
        },
        _change: function(ev) {
            const that = this,
                text = that.viewer.searchDialog.searchModel["searchText"],
                matchCase = that.viewer.searchDialog.searchModel["matchCase"];

            if (ev.field === "searchText" || ev.field === "matchCase") {
                that.matches = that.viewer.pdfViewerCommon.searchText({ text: text, matchCase: matchCase });
                that.matchIndex = that.matches.length ? 1 : 0;
                that._updateSearchModel();
            }
        },
        _next: function() {
            const that = this;

            that.viewer.pdfViewerCommon.goToNextSearchMatch();
            that.matchIndex = that.matchIndex + 1 > that.matches.length ? 1 : that.matchIndex + 1;
            that._updateSearchModel();
        },
        _prev: function() {
            const that = this;

            that.viewer.pdfViewerCommon.goToPreviousSearchMatch();
            that.matchIndex = that.matchIndex - 1 < 1 ? that.matches.length : that.matchIndex - 1;
            that._updateSearchModel();
        },
        _updateSearchModel: function() {
            const that = this,
                model = that.viewer.searchDialog.searchModel;

            if (that.matches && that.matches.length) {
                model.set("matches", that.matches.length);
                model.set("matchIndex", that.matchIndex);
            } else {
                model.set("searchText", "");
                model.set("matches", 0);
                model.set("matchIndex", 0);
            }
        },
        _closeDialog: function() {
            const that = this;
            that.viewer.searchDialog.close();
        }
    });

    var ZoomCommand = Command.extend({
        exec: function() {
            const that = this;
            const scale = that._calculateZoom();

            if (scale === undefined) {
                return;
            }

            let zoomLevel = scale.zoomLevel;

            if (that.viewer.zoomScale !== scale.zoomLevel) {
                that.viewer._preventRenderEvent = true;
                that.viewer._currentPage = that.viewer.pdfViewerCommon.getCurrentPageIndex() + 1;
                that.viewer.pdfViewerCommon.zoom({ zoomLevel: scale.zoomLevel, zoomLevelType: scale.zoomLevelType });
                zoomLevel = that.viewer.pdfViewerCommon.options.zoomLevel;
                that._triggerZoomEnd(zoomLevel);
            }

            that.viewer.zoomScale = zoomLevel;
        },

        _calculateZoom: function() {
            var options = this.options,
                viewer = this.viewer,
                viewerOptions = viewer.options,
                scale = options.value || options.scale,
                scaleValue = scale,
                zoomLevelType = "",
                preventZoom;

            viewer._allowResize = false;
            viewer._autoFit = false;

            if (options.zoomIn) {
                scaleValue = scale = viewer.zoomScale + viewerOptions.zoomRate;
            } else if (options.zoomOut) {
                scaleValue = scale = viewer.zoomScale - viewerOptions.zoomRate;
            } else if (scale === "auto") {
                viewer._allowResize = true;
                scaleValue = viewer._autoZoomScale;
            } else if (typeof scale === "string" && !kendo.parseFloat(scale)) {
                zoomLevelType = scale;
            } else if (scale && scale.toString().match(/^[0-9]+%?$/)) {
                scale = parseInt(scale.replace('%', ''), 10) / 100;
                scaleValue = scale;
            } else {
                preventZoom = isNaN(scale);
            }

            if (!preventZoom) {
                preventZoom = scale < viewerOptions.zoomMin || scale > viewerOptions.zoomMax;
            }

            if (preventZoom || viewer.trigger(ZOOMSTART, { scale: scale })) {
                return;
            }

            if (options.updateComboBox && viewer.toolbar)
            {
                viewer._updateZoomComboBox(scale);
            }

            return { zoomLevel: scaleValue, zoomLevelType: zoomLevelType };
        },

        _triggerZoomEnd: function(scale) {
            var that = this,
                viewer = that.viewer;

            viewer.trigger(ZOOMEND, { scale: scale });
        }
    });

    var PrintCommand = Command.extend({
        init: function(options) {
            Command.fn.init.call(this, options);
        },
        exec: function() {
            const that = this;

             if (!that.viewer.document) {
                that.viewer._triggerError({
                    message: this.errorMessages.notFound
                });
                return;
            }

            progress(that.viewer.pageContainerWrapper, true);
            // Used to ensure that loading indicator appears before the browser hangs.
            setTimeout(() => {
               that.viewer.pdfViewerCommon.printFile();
            }, 100);
        }
    });

    const AnnotationsCommand = Command.extend({
        init: function(options) {
            Command.fn.init.call(this, options);
        },
        exec: function() {
            const viewer = this.viewer;
            if (viewer.annotationsToolbar) {
                viewer.annotationsToolbar.destroy();
            } else {
                viewer.annotationsToolbar = new AnnotationToolbar(viewer);
            }
        }
    });

    extend(kendo.pdfviewer, {
        OpenCommand: OpenCommand,
        PageChangeCommand: PageChangeCommand,
        DownloadCommand: DownloadCommand,
        EnableSelectionCommand: EnableSelectionCommand,
        EnablePanCommand: EnablePanCommand,
        ExportCommand: ExportCommand,
        PrintCommand: PrintCommand,
        OpenSearchCommand: OpenSearchCommand,
        ZoomCommand: ZoomCommand,
        AnnotationsCommand: AnnotationsCommand
    });

})(window.kendo.jQuery);

