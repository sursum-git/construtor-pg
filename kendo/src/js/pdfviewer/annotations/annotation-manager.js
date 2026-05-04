/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

export const deleteAnnotation = (widget) => {
    widget.deleteAnnotation();
};

export const setHighlightTextColor = (widget, color) => {
    widget.setHighlightColor(color);
};

export const setFreeTextFontColor = (widget, color) => {
    widget.setFreeTextColor(color);
};

export const setFreeTextFontSize = (widget, fontSize) => {
    widget.setFreeTextFontSize(fontSize);
};

export const setAnnotationEditorMode = (widget, args) => {
    if (widget.interactionMode !== args.mode) {
        widget.setInteractionMode({
            mode: args.mode
        });
    }

    widget.setAnnotationEditorMode(args);
};

export const resetAnnotationEditorMode = (widget) => {
    widget.resetAnnotationEditorMode();
};

export const setInteractionMode = (widget, mode) => {
    widget.setInteractionMode({ mode: mode });
};