/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import {
    diffNumericOptions, Element, Scale, Translation, Rotation, Circle, Group, Rectangle, Path, Layout, Line,
    MarkerBase, ArrowMarker, CircleMarker, Polyline, CompositeTransform, TextBlock, Process, Terminator, Decision,
    Document, PredefinedProcess, Database, Delay, ManualOperation, SummingJunction, Sort, Preparation,
    OnPageConnector, OffPageConnector, Merge, ManualInputOutput, LogicalOr, InternalStorage, Extract, Display,
    DirectAccessStorage, DataStorage, DataInputOutput, Collate, MultipleDocuments, Image, VisualBase,
    Utils, Rect, Markers, Canvas as CanvasCommon
} from "@progress/kendo-diagram-common";

import "../../kendo.drawing.js";

const { isFunction } = Utils;

(function() {
    // Imports ================================================================
    var kendo = window.kendo,
        drawing = kendo.drawing,
        diagram = kendo.dataviz.diagram;

    class Canvas extends CanvasCommon {
        constructor(element, options) {
            options = options || {};
            super(element, options);
            this.surface = drawing.Surface.create(element, options);
            this.translate = isFunction(this.surface.translate) ? this._translate : undefined;

            this._viewBox = new Rect(0, 0, options.width, options.height);
            this.size(this._viewBox);
        }
    }

    // Exports ================================================================
    kendo.deepExtend(diagram, {
        init: function(element) {
            kendo.init(element, diagram.ui);
        },
        diffNumericOptions: diffNumericOptions,
        Markers: Markers,
        Element: Element,
        Scale: Scale,
        Translation: Translation,
        Rotation: Rotation,
        Circle: Circle,
        Group: Group,
        Rectangle: Rectangle,
        Canvas: Canvas,
        Path: Path,
        Layout: Layout,
        Line: Line,
        MarkerBase: MarkerBase,
        ArrowMarker: ArrowMarker,
        CircleMarker: CircleMarker,
        Polyline: Polyline,
        CompositeTransform: CompositeTransform,
        TextBlock: TextBlock,
        Terminator: Terminator,
        Process: Process,
        Decision: Decision,
        PredefinedProcess: PredefinedProcess,
        Document: Document,
        ManualInputOutput: ManualInputOutput,
        Preparation: Preparation,
        ManualOperation: ManualOperation,
        InternalStorage: InternalStorage,
        Display: Display,
        DirectAccessStorage: DirectAccessStorage,
        Database: Database,
        OnPageConnector: OnPageConnector,
        OffPageConnector: OffPageConnector,
        DataInputOutput: DataInputOutput,
        SummingJunction: SummingJunction,
        LogicalOr: LogicalOr,
        Merge: Merge,
        Extract: Extract,
        DataStorage: DataStorage,
        Delay: Delay,
        Sort: Sort,
        Collate: Collate,
        MultipleDocuments: MultipleDocuments,
        Image: Image,
        VisualBase: VisualBase
    });
})();
