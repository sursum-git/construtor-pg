/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/* eslint no-console: ["error", { allow: ["warn", "error"] }] */

export const __meta__ = {
    id: "pdfjs.loader",
    name: "PDFJSLoader",
    category: "web",
    description: "Utility that enables pdfjsLib to be loaded from global scope",
    depends: []
};

/* The PDFViewer common package uses this enum in the global namespace.
   It isn't called when the PDFViewer is being initialized but when the file itself is getting loaded.
   That is why we need to have it regardless if pdfjs is imported or not.
*/
const AnnotationEditorTypeMock = {
    DISABLE: -1,
    NONE: 0,
    FREETEXT: 3,
    HIGHLIGHT: 9,
    STAMP: 13,
    INK: 15,
};

const {
    shadow,
    AnnotationEditorType,
    FeatureTest,
    setLayerDimensions,
    AnnotationEditorParamsType,
    PixelsPerInch,
    DOMSVGFactory,
    PDFDateString,
    Util,
    XfaLayer,
    getDocument,
    AnnotationMode,
    AbortException,
    TextLayer,
    fetchData,
    noContextMenu
} = window.pdfjsLib || { AnnotationEditorType: AnnotationEditorTypeMock };

export {
    shadow,
    AnnotationEditorType,
    FeatureTest,
    setLayerDimensions,
    AnnotationEditorParamsType,
    PixelsPerInch,
    DOMSVGFactory,
    PDFDateString,
    Util,
    XfaLayer,
    getDocument,
    AnnotationMode,
    AbortException,
    TextLayer,
    fetchData,
    noContextMenu
};