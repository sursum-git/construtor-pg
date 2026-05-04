/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import {
    CompositeUnit, TransformUnit, PanUndoUnit, AddShapeUnit, AddConnectionUnit, DeleteShapeUnit, DeleteConnectionUnit, ConnectionEditAdorner,
    ConnectionTool, ConnectorVisual, UndoRedoService, ResizingAdorner, Selector, ToolService, ConnectorsAdorner, LayoutUndoUnit, ConnectionEditUnit,
    ToFrontUnit, ToBackUnit, ConnectionRouterBase, PolylineRouter, CascadingRouter, SelectionTool, ScrollerTool, PointerTool, ConnectionEditTool, RotateUnit,
    Cursors
} from "@progress/kendo-diagram-common";

    (function() {
        // Imports ================================================================
        var kendo = window.kendo,
            dataviz = kendo.dataviz,
            diagram = dataviz.diagram;

        diagram.Cursors = Cursors;

        kendo.deepExtend(diagram, {
            CompositeUnit: CompositeUnit,
            TransformUnit: TransformUnit,
            PanUndoUnit: PanUndoUnit,
            AddShapeUnit: AddShapeUnit,
            AddConnectionUnit: AddConnectionUnit,
            DeleteShapeUnit: DeleteShapeUnit,
            DeleteConnectionUnit: DeleteConnectionUnit,
            ConnectionEditAdorner: ConnectionEditAdorner,
            ConnectionTool: ConnectionTool,
            ConnectorVisual: ConnectorVisual,
            UndoRedoService: UndoRedoService,
            ResizingAdorner: ResizingAdorner,
            Selector: Selector,
            ToolService: ToolService,
            ConnectorsAdorner: ConnectorsAdorner,
            LayoutUndoUnit: LayoutUndoUnit,
            ConnectionEditUnit: ConnectionEditUnit,
            ToFrontUnit: ToFrontUnit,
            ToBackUnit: ToBackUnit,
            ConnectionRouterBase: ConnectionRouterBase,
            PolylineRouter: PolylineRouter,
            CascadingRouter: CascadingRouter,
            SelectionTool: SelectionTool,
            ScrollerTool: ScrollerTool,
            PointerTool: PointerTool,
            ConnectionEditTool: ConnectionEditTool,
            RotateUnit: RotateUnit
        });
})();
