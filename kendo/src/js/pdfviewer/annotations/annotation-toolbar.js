/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { PdfViewerInteractionMode } from '@progress/kendo-pdfviewer-common';
import { setAnnotationEditorMode, resetAnnotationEditorMode } from './annotation-manager.js';

const $ = jQuery;
const AnnotationEditorType = {
    DISABLE: -1,
    NONE: 0,
    FREETEXT: 3,
    HIGHLIGHT: 9
};

export default class AnnotationToolbar {
    constructor(viewer) {
        this.viewer = viewer;
        this.widget = viewer.pdfViewerCommon;
        this.init();
    }

    init() {
        const annotationsToolbar = this.element = $("<div></div>");

        annotationsToolbar.insertAfter(this.viewer.toolbar.element);
        const toolbar = this.toolbar = new kendo.ui.ToolBar(annotationsToolbar, {
            fillMode: 'flat',
            tools: [
                { name: "highlight", command: "highlight", icon: "highlight", type: "button", togglable: true, showText: "overflow", group: "annotations", fillMode: "flat" },
                { type: "separator" },
                { name: "freeText", command: "freeText", icon: "free-text", type: "button", togglable: true, showText: "overflow", group: "annotations", fillMode: "flat" },
                { type: "spacer" },
                { name: "close", command: "close", icon: "x", type: "button", showText: "overflow", fillMode: "flat" }
            ],
            parentMessages: {
                highlight: "Highlight",
                freeText: "Free text",
                close: "Close"
            }
        });

        toolbar.bind("click", this.onToolbarClick.bind(this));
        toolbar.bind("toggle", this.onToolbarClick.bind(this));
    }

    onToolbarClick(e) {
        const widget = this.widget;
        const target = $(e.target);
        const command = target.data("command");
        const viewerToolbar = this.viewer.toolbar;

        switch (command) {
            case "close":
                this.destroy();
                viewerToolbar.element.find("[tabindex=0]").trigger("focus");
                break;
            case "highlight":
                setAnnotationEditorMode(widget, { interactionMode: PdfViewerInteractionMode.TextSelection, mode: AnnotationEditorType.HIGHLIGHT });
                break;
            case "freeText":
                setAnnotationEditorMode(widget, { interactionMode: PdfViewerInteractionMode.TextSelection, mode: AnnotationEditorType.FREETEXT });
                break;
        }
    }

    destroy() {
        if (this.toolbar) {
            this.viewer.toolbar.toggle("[title='Annotations']", false);
            this.toolbar.unbind("click");
            this.toolbar.unbind("toggle");
            this.toolbar.destroy();
            this.toolbar = null;
            this.element.remove();
            this.element = null;
            this.viewer.annotationsToolbar = null;
            resetAnnotationEditorMode(this.widget);
        }
    }
}