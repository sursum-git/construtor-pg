/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { deleteAnnotation, setHighlightTextColor, setFreeTextFontSize, setFreeTextFontColor } from "./annotation-manager.js";
import '../../kendo.toolbar.js';
import "../../kendo.form.js";
import "../../kendo.colorpicker.js";

const $ = jQuery;

class AnnotationPopup {
    constructor(viewer, anchor, editor) {
        this.viewer = viewer;
        this.widget = viewer.pdfViewerCommon;
        this.anchor = anchor;
        this.editor = editor;
        this.popupOpenHandler = this.onPopupOpen.bind(this);
        this.init();
    }

    init() {
        const anchor = this.anchor;
        const annotationPopupToolbarElement = $(`<div data-uid="${kendo.guid()}"></div>`);

        this.popup = new kendo.ui.Popup($(`<div class="k-pdf-viewer-annotation-editor-toolbar"></div>`), {
            anchor: anchor
        });

        this.popup.element.html(annotationPopupToolbarElement);
        const popupToolbar =
            this.popupToolbar =
            new kendo.ui.ToolBar(annotationPopupToolbarElement, {
                fillMode: "flat",
                tools: [
                    { name: "palette", command: "palette", icon: "palette", type: "button", showText: "overflow", fillMode: "flat" },
                    { name: "remove", command: "remove", icon: "trash", type: "button", showText: "overflow", fillMode: "flat" },
                ],
                parentMessages: {
                    palette: "Palette",
                    remove: "Remove"
                }
            });

        popupToolbar.bind("click", this.onAnnotationPopupToolbarClick.bind(this));

        this.popup.open();
    }

    initEditor() {
    }

    onAnnotationPopupToolbarClick(e) {
        const widget = this.widget;
        const target = $(e.target);
        const command = target.data("command");

        switch (command) {
            case "palette":
                this.initEditor();
                break;
            case "remove":
                deleteAnnotation(widget);
                this.destroyPopup();
                break;
            default:
                break;
        }
    }

    onPopupOpen() {
        this.flatColorPicker._view._hueSlider.resize();
        this.flatColorPicker._view._opacitySlider.resize();
    }

    anchorExists() {
        return document.getElementById(this.anchor.id);
    }

    destroyPopup() {
        if (this.popup) {
            this.popup.destroy();
            this.popup = null;
        }
    }

    destroyEditor() {
        if (this.editorPopup) {
            this.editorPopup.destroy();
            this.editorPopup = null;
        }
    }

    destroy() {
        if (this.popupToolbar) {
            this.popupToolbar.unbind("click");
            this.popupToolbar.unbind("toggle");
            this.popupToolbar.destroy();
            this.popupToolbar = null;
        }

        this.destroyPopup();
    }
}

export class HighlightAnnotationPopup extends AnnotationPopup {
    constructor(viewer, anchor, editor) {
        super(viewer, anchor, editor);
    }

    initEditor() {
        const anchor = this.anchor;

        if (!this.anchorExists()) {
            this.destroy();
            return;
        }

        this.destroyEditor();

        this.editorPopup = new kendo.ui.Popup($(`
            <div class="k-pdf-viewer-annotation-editor">
                <span class="k-column-menu-group-header">
                    <span class="k-column-menu-group-header-text">Color</span>
                </span>
                <div ref-annotation-editor-flat-color-picker></div>
            </div>
`), {
            anchor: anchor,
            activate: this.popupOpenHandler
        });

        this.initFlatColorPicker();

        this.editorPopup.open();

        this.destroy();
    }

    initFlatColorPicker() {
        this.flatColorPicker = new kendo.ui.FlatColorPicker(this.editorPopup.element.find("[ref-annotation-editor-flat-color-picker]"), {
            format: "rgb",
            opacity: true,
            change: (e) => setHighlightTextColor(this.widget, e.value)
        });
    }

    onPopupOpen() {
        const currentColor = this.editor.color;

        super.onPopupOpen();
        this.flatColorPicker.value(currentColor);
    }
}

export class FreeTextAnnotationPopup extends AnnotationPopup {
    constructor(viewer, anchor, editor) {
        super(viewer, anchor, editor);
    }

    initEditor() {
        const anchor = this.anchor;

        if (!this.anchorExists()) {
            this.destroy();
            return;
        }

        this.destroyEditor();

        this.editorPopup = new kendo.ui.Popup($(`
            <div class="k-pdf-viewer-annotation-editor">
                <span class="k-column-menu-group-header">
                        <span class="k-column-menu-group-header-text">Text style</span>
                    </span>
                    <form ref-annotation-text-editor-popup-form></form>
                <span class="k-column-menu-group-header">
                    <span class="k-column-menu-group-header-text">Color</span>
                </span>
                <div ref-annotation-editor-flat-color-picker></div>
            </div>
`), {
            anchor: anchor,
            activate: this.popupOpenHandler
        });

        this.initFontSizeForm();
        this.initFlatColorPicker();

        this.editorPopup.open();

        this.destroy();
    }

    initFontSizeForm() {
        const currentFontSize = this.editor.propertiesToUpdate[0][1];
        const fontSizeList = [8, 9, 10, 11, 12, 13, 14, 16, 20, 22, 24, 26, 28, 36, 48, 72];

        this.fontSizeForm = new kendo.ui.Form($("[ref-annotation-text-editor-popup-form]"), {
            buttonsTemplate: () => "",
            formData: {
                fontSize: currentFontSize
            },
            layout: "grid",
            grid: {
                cols: 3
            },
            items: [
                {
                    field: "fontSize",
                    label: "Font size",
                    editor: "DropDownList",
                    colSpan: 1,
                    editorOptions: {
                        fillMode: "flat",
                        dataSource: fontSizeList,
                        change: (e) => {
                            const value = e.sender.value();
                            setFreeTextFontSize(this.widget, value);
                        }
                    }
                }
            ]
        });

        this.fontSizeForm.element.find(".k-form-buttons").remove();
    }

    initFlatColorPicker() {
        this.flatColorPicker = new kendo.ui.FlatColorPicker(this.editorPopup.element.find("[ref-annotation-editor-flat-color-picker]"), {
            format: "rgb",
            opacity: true,
            change: (e) => setFreeTextFontColor(this.widget, e.value)
        });
    }

    onPopupOpen() {
        const currentColor = this.editor.propertiesToUpdate[1][1];

        super.onPopupOpen();
        this.flatColorPicker.value(currentColor);
    }
}