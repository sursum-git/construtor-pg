/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import {
    shapeDefaults, DefaultConnectors, Shape, Connection, Connector,
    QuadRoot, QuadNode, ShapesQuadTree, Diagram as DiagramWidget,
    TemplateService, placeTooltip,
    events, Utils, defaultOptions
} from "@progress/kendo-diagram-common";

import "../../kendo.data.js";
import "../../kendo.draganddrop.js";
import "../../kendo.toolbar.js";
import "../../kendo.tooltip.js";
import "../../kendo.editable.js";
import "../../kendo.window.js";
import "../../kendo.dropdownlist.js";
import "../../kendo.dataviz.core.js";
import "../../kendo.dataviz.themes.js";
import "../../kendo.html.button.js";
import "./theme.js";
import "./svg.js";
import "./services.js";
import "./layout.js";

    (function($, undefined) {
        // Imports ================================================================
        var dataviz = kendo.dataviz,
            diagram = dataviz.diagram,
            Widget = kendo.ui.Widget,
            deepExtend = kendo.deepExtend,
            outerWidth = kendo._outerWidth,
            outerHeight = kendo._outerHeight,
            extend = $.extend,
            HierarchicalDataSource = kendo.data.HierarchicalDataSource,
            Canvas = diagram.Canvas,
            Rect = diagram.Rect,
            Point = diagram.Point,
            defined = kendo.drawing.util.defined,
            isPlainObject = $.isPlainObject,
            DataSource = kendo.data.DataSource;

        TemplateService.register({
            compile: function(template) {
                return kendo.template(template, {
                    paramName: 'dataItem'
                });
            }
        });

        // Constants ==============================================================
        var NS = ".kendoDiagram",
            CLICK = "click",
            ROTATED = "rotated",
            CHANGE = "change",
            ERROR = "error",
            BUTTON_TEMPLATE = ({ className, icon, themeColor, text }) =>
                kendo.html.renderButton(`<button class="${className}" href="#">${text}</button>`, {
                    icon: icon,
                    themeColor
                });

        function preventDefault(e) {
            e.preventDefault();
        }

        function clearField(field, model) {
            if (defined(model[field])) {
                model.set(field, null);
            }
        }
        const toolTipContainer = (content) => `<div class="k-popup">${content}</div>`;
        const toPx = (v) => Math.round(v) + 'px';

        diagram.DefaultConnectors = DefaultConnectors;

        var defaultButtons = {
            cancel: {
                text: "Cancel",
                icon: "cancel-outline",
                className: "k-diagram-cancel",
            },
            update: {
                text: "Save",
                imageClass: "save",
                className: "k-diagram-update",
                icon: "save",
                themeColor: "primary"
            }
        };

        diagram.shapeDefaults = shapeDefaults;

        var Diagram = Widget.extend({
            init: function(element, userOptions = {}) {
                var that = this;

                kendo.destroy(element);
                Widget.fn.init.call(that, element, userOptions);

                const theme = that._initTheme();
                const isEditable = defined(userOptions.connectionsDataSource);
                this._isEditable = isEditable;
                const extendedOptions = {
                    ...that.options,

                    _syncChanges: that._syncChanges.bind(that),
                    _syncShapeChanges: that._syncShapeChanges.bind(that),
                    _syncConnectionChanges: that._syncConnectionChanges.bind(that),
                    _removeConnectionDataItem: that._removeConnectionDataItem.bind(that),
                    _removeShapeDataItem: that._removeShapeDataItem.bind(that),
                    _addConnection: that._addConnection.bind(that),
                    _addShape: that._addShape.bind(that),
                    updateConnectionModel: that.updateConnectionModel.bind(that),
                    updateShapeModel: that.updateShapeModel.bind(that),

                    cloneDataItem: cloneDataItem,

                    connect: isEditable ? that.connect.bind(that) : undefined,
                    createToolBar: that._createToolBar.bind(that),
                    destroyToolBar: that._destroyToolBar.bind(that),
                    Canvas,
                    shapeDefaults: { ...userOptions.shapeDefaults },
                    navigation: { ...userOptions.navigatable },
                    connectionDefaults: { ...userOptions.connectionDefaults },
                };

                const widget = new DiagramWidget(element, extendedOptions, theme);
                that.events.forEach(function(eventName) {
                    widget.bind(eventName, function(e) {
                        if (eventName === "tooltipShow") {
                            that._clearTooltips();
                            const tooltipSettings = that.options.tooltip || {};
                            if (e.item.name === 'Shape' && tooltipSettings.shapeTemplate) {
                                that._createTooltip(e.item, e.point, tooltipSettings.shapeTemplate, true);
                            } else if (e.item.name === 'Connection' && tooltipSettings.connectionTemplate) {
                                that._createTooltip(e.item, e.point, tooltipSettings.connectionTemplate, false);
                            }
                        }

                        if (eventName === "tooltipHide") {
                            that._clearTooltips();
                        }
                        e.sender = that;
                        that.trigger(eventName, e);
                    });
                });
                that.widget = widget;
                that.wrapper = $(widget.wrapper);
                that.scrollable = $(widget.scrollable);
                that.shapes = widget.shapes;
                that.connections = widget.connections;
                that._connectionsDataMap = widget._connectionsDataMap;
                that._dataMap = widget._dataMap;
                that._inactiveShapeItems = widget._inactiveShapeItems;
                that._copyOffset = 0;
                that._selectedItems = widget._selectedItems;
                that.options = widget.options;
                that._clipboard = widget._clipboard;
                that.id = widget.id;
                that.undoRedoService = widget.undoRedoService;
                that.toolService = widget.toolService;
                that._resizingAdorner = widget._resizingAdorner;
                that._connectorsAdorner = widget._connectorsAdorner;
                that.selector = widget.selector;
                that.mainLayer = widget.mainLayer;

                that._fetchFreshData();

                that._createGlobalToolBar();

                that.widget._createOptionElements();
                that.widget.zoom(that.options.zoom);
                that.widget.canvas.draw();
            },

            options: deepExtend({}, defaultOptions),
            events: [...events],

            shapes: [],
            connections: [],
            dataSource: null,
            connectionsDataSource: null,

            /**
             * Connects two items.
             * @param source Shape, Connector, Point.
             * @param target Shape, Connector, Point.
             * @param options Connection options that will be passed to the newly created connection.
             * @returns The newly created connection.
             */
            connect: function(source, target, options) {
                let connection;
                if (this.connectionsDataSource && this._isEditable) {
                    const dataItem = this.connectionsDataSource.add({});
                    connection = this.widget._connectionsDataMap[dataItem.uid];
                    connection.source(source);
                    connection.target(target);
                    connection.redraw(options);
                    connection.updateModel();
                } else {
                    connection = this.widget.connect(source, target, options);
                }

                return connection;
            },

            /**
             * Determines whether the two items are connected.
             * @param source Shape, Connector, Point.
             * @param target Shape, Connector, Point.
             * @returns true if the two items are connected.
             */
            connected: function(source, target) {
                return this.widget.connected(source, target);
            },

            /**
             * Adds connection to the diagram.
             * @param connection Connection.
             * @param undoable Boolean.
             * @returns The newly created connection.
             */
            addConnection: function(connection, undoable) {
                return this.widget.addConnection(connection, undoable);
            },

            /**
             * Adds shape to the diagram.
             * @param item Shape, Point. If point is passed it will be created new Shape and positioned at that point.
             * @param options. The options to be passed to the newly created Shape.
             * @returns The newly created shape.
             */
            addShape: function(item, undoable) {
                return this.widget.addShape(item, undoable);
            },

            /**
             * Removes items (or single item) from the diagram.
             * @param items DiagramElement, Array of Items.
             * @param undoable.
             */
            remove: function(items, undoable) {
                return this.widget.remove(items, undoable);
            },

            /**
             * Executes the next undoable action on top of the undo stack if any.
             */
            undo: function() {
                return this.widget.undo();
            },

            /**
             * Executes the previous undoable action on top of the redo stack if any.
             */
            redo: function() {
                return this.widget.redo();
            },

            /**
             * Selects items on the basis of the given input or returns the current selection if none.
             * @param itemsOrRect DiagramElement, Array of elements, "All", false or Rect. A value 'false' will deselect everything.
             * @param options
             * @returns {Array}
             */
            select: function(item, options) {
                return this.widget.select(item, options);
            },

            deselect: function(item) {
                this.widget.deselect(item);
            },

             /**
             * Brings to front the passed items.
             * @param items DiagramElement, Array of Items.
             * @param undoable. By default the action is undoable.
             */
             toFront: function(items, undoable) {
                return this.widget.toFront(items, undoable);
            },

            /**
             * Sends to back the passed items.
             * @param items DiagramElement, Array of Items.
             * @param undoable. By default the action is undoable.
             */
            toBack: function(items, undoable) {
                return this.widget.toBack(items, undoable);
            },

            /**
             * Bring into view the passed item(s) or rectangle.
             * @param items DiagramElement, Array of Items, Rect.
             * @param options. align - controls the position of the calculated rectangle relative to the viewport.
             * "Center middle" will position the items in the center. animate - controls if the pan should be animated.
             */
            bringIntoView: function(item, options) { // jQuery|Item|Array|Rect
                return this.widget.bringIntoView(item, options);
            },

            /**
             * Gets the bounding rectangle of the given items.
             * @param items DiagramElement, Array of elements.
             * @param origin Boolean. Pass 'true' if you need to get the bounding box of the shapes without their rotation offset.
             * @returns {Rect}
             */
            boundingBox: function(items, origin) {
                return this.widget.boundingBox(items, origin);
            },

            /**
             * Performs a diagram layout of the given type.
             * @param layoutType The layout algorithm to be applied (TreeLayout, LayeredLayout, SpringLayout).
             * @param options Layout-specific options.
             */
            layout: function(options) {
                return this.widget.layout(options);
            },

            /**
             * Gets a shape on the basis of its identifier.
             * @param id (string) the identifier of a shape.
             * @returns {Shape}
             */
            getShapeById: function(id) {
                return this.widget.getShapeById(id);
            },

            getConnectionByModelId(id) {
                let connectionResult;
                if (this.connectionsDataSource) {
                    connectionResult = Utils.first(this.connections, function(connection) {
                        return (connection.dataItem || {}).id === id;
                    });
                }
                return connectionResult;
            },

            getConnectionByModelUid(uid) {
                let connection;
                if (this.connectionsDataSource) {
                    connection = this.widget._connectionsDataMap[uid];
                }
                return connection;
            },

            copy: function() {
                this.widget.copy();
            },

            cut: function() {
                this.widget.cut();
            },

            alignShapes: function(direction) {
                return this.widget.alignShapes(direction);
            },

            createShape: function() {
                if ((this.editor && this.editor.end()) || !this.editor) {
                    var dataSource = this.dataSource;
                    var view = dataSource.view() || [];
                    var index = view.length;
                    var model = createModel(dataSource, {});
                    var shape = this.widget._createShape(model, {});

                    if (!this.trigger("add", { shape: shape })) {
                        dataSource.insert(index, model);
                        var inactiveItem = this.widget._inactiveShapeItems.getByUid(model.uid);
                        inactiveItem.element = shape;
                        this.edit(shape);
                    }
                }
            },

            createConnection: function() {
                if (((this.editor && this.editor.end()) || !this.editor)) {
                    var connectionsDataSource = this.connectionsDataSource;
                    var view = connectionsDataSource.view() || [];
                    var index = view.length;
                    var model = createModel(connectionsDataSource, {});
                    var connection = this.widget._createConnection(model);
                    if (!this.trigger("add", { connection: connection })) {
                        this.widget._connectionsDataMap[model.uid] = connection;
                        connectionsDataSource.insert(index, model);
                        this.addConnection(connection, false);
                        this.edit(connection);
                    }
                }
            },

            exportVisual: function() {
                return this.widget.exportVisual();
            },

            exportDOMVisual: function() {
                return this.widget.exportDOMVisual();
            },

            _syncChanges() {
                this._syncShapeChanges();
                this._syncConnectionChanges();
            },

            _syncShapeChanges() {
                if (this.dataSource && this._isEditable) {
                    this.dataSource.sync();
                }
            },

            _syncConnectionChanges() {
                if (this.connectionsDataSource && this._isEditable) {
                    Promise.all(this.widget._deferredConnectionUpdates).then(() => {
                        this.connectionsDataSource.sync();
                        this.widget._deferredConnectionUpdates.length = 0;
                    });
                }
            },

            editModel: function(dataItem, editorType) {
                this.cancelEdit();
                var editors, template;
                var editable = this.widget.options.editable;

                if (editorType == "shape") {
                    editors = editable.shapeEditors;
                    template = editable.shapeTemplate;
                } else if (editorType == "connection") {
                    var connectionSelectorHandler = connectionSelector.bind(this);
                    editors = deepExtend({}, { from: connectionSelectorHandler, to: connectionSelectorHandler }, editable.connectionEditors);
                    template = editable.connectionTemplate;
                } else {
                    return;
                }

                this.editor = new PopupEditor(this.element, {
                    update: this._update.bind(this),
                    cancel: this._cancel.bind(this),
                    model: dataItem,
                    type: editorType,
                    target: this,
                    editors: editors,
                    template: template
                });

                this.trigger("edit", this._editArgs());
            },

            edit: function(item) {
                if (item.dataItem) {
                    var editorType = item instanceof Shape ? "shape" : "connection";
                    this.editModel(item.dataItem, editorType);
                }
            },

            cancelEdit: function() {
                if (this.editor) {
                    this._getEditDataSource().cancelChanges(this.editor.model);

                    this._destroyEditor();
                }
            },

            clear() {
                this.widget.clear();
            },

            saveEdit: function() {
                if (this.editor && this.editor.end() &&
                    !this.trigger("save", this._editArgs())) {
                    this._getEditDataSource().sync();
                }
            },

            updateConnectionModel: function(connection, syncChanges) {
                if (this.connectionsDataSource) {
                    const model = this.connectionsDataSource.getByUid(connection.dataItem.uid);

                    if (model) {
                        this._suspendModelRefresh();
                        if (defined(connection.options.fromX) && connection.options.fromX !== null) {
                            clearField('from', model);
                            clearField('fromConnector', model);
                            model.set('fromX', connection.options.fromX);
                            model.set('fromY', connection.options.fromY);
                        } else {
                            model.set('from', connection.options.from);
                            if (defined(model.fromConnector)) {
                                model.set('fromConnector', connection.sourceConnector ? connection.sourceConnector.options.name : null);
                            }
                            clearField('fromX', model);
                            clearField('fromY', model);
                        }

                        if (defined(connection.options.toX) && connection.options.toX !== null) {
                            clearField('to', model);
                            clearField('toConnector', model);
                            model.set('toX', connection.options.toX);
                            model.set('toY', connection.options.toY);
                        } else {
                            model.set('to', connection.options.to);
                            if (defined(model.toConnector)) {
                                model.set('toConnector', connection.targetConnector ? connection.targetConnector.options.name : null);
                            }
                            clearField('toX', model);
                            clearField('toY', model);
                        }

                        if (defined(connection.options.type) && defined(model.type)) {
                            model.set('type', connection.options.type);
                        }

                        connection.dataItem = model;
                        this._resumeModelRefresh();

                        if (syncChanges) {
                            this._syncConnectionChanges();
                        }
                    }
                }
            },

            updateShapeModel: function(shape, syncChanges) {
                const bounds = shape._bounds;
                const model = shape.dataItem;

                if (model) {
                    this._suspendModelRefresh();
                    if (defined(model.x) && bounds.x !== model.x) {
                        model.set('x', bounds.x);
                    }

                    if (defined(model.y) && bounds.y !== model.y) {
                        model.set('y', bounds.y);
                    }

                    if (defined(model.width) && bounds.width !== model.width) {
                        model.set('width', bounds.width);
                    }

                    if (defined(model.height) && bounds.height !== model.height) {
                        model.set('height', bounds.height);
                    }

                    shape.dataItem = model;
                    this._resumeModelRefresh();

                    if (syncChanges) {
                        this._syncShapeChanges();
                    }
                }
            },

            destroy: function() {
                this.diagram = null;
                this.element = null;
                this.options = null;

                if (this._toolBar) {
                    this._toolBar.destroy();
                }

                if (this._popup) {
                    this._popup.destroy();
                }

                this._destroyGlobalToolBar();
                this._destroyToolBar();

                if (this.widget) {
                    this.widget.destroy();
                    this.widget = null;
                }
            },

            documentToModel: function(point) {
                return this.widget.documentToModel(point);
            },

            documentToView: function(point) {
                return this.widget.documentToView(point);
            },

            focus: function() {
                return this.widget.focus();
            },

            getShapeByModelId: function(id) {
                return this.widget.getShapeByModelId(id);
            },

            getShapeByModelUid: function(uid) {
                return this.widget.getShapeByModelUid(uid);
            },

            layerToModel: function(point) {
                return this.widget.layerToModel(point);
            },

            load: function(options) {
                this.widget.load(options);
            },

            modelToDocument: function(point) {
                return this.widget.modelToDocument(point);
            },

            modelToLayer: function(point) {
                return this.widget.modelToLayer(point);
            },

            modelToView: function(point) {
                return this.widget.modelToView(point);
            },

            pan: function(pan, animate) {
                return this.widget.pan(pan, animate);
            },

            paste: function() {
                this.widget.paste();
            },

            save: function() {
                return this.widget.save();
            },

            selectAll: function() {
                this.widget.selectAll();
            },

            selectArea: function(rect) {
                this.widget.selectArea(rect);
            },

            setDataSource(dataSource) {
                this.options.dataSource = dataSource;
                this._dataSource();
                if (this.options.autoBind !== false) {
                    this.dataSource.fetch();
                }
            },

            setConnectionsDataSource(dataSource) {
                this.options.connectionsDataSource = dataSource;
                this._connectionDataSource();
                if (this.options.autoBind !== false) {
                    this.connectionsDataSource.fetch();
                }
            },

            viewToDocument: function(point) {
                return this.widget.viewToDocument(point);
            },

            viewToModel: function(point) {
                return this.widget.viewToModel(point);
            },

            viewport: function() {
                return this.widget.viewport();
            },

            zoom: function(zoom, options) {
                return this.widget.zoom(zoom, options);
            },

            _initTheme: function() {
                var that = this;
                var themeName = ((that.options || {}).theme || "").toLowerCase();
                var themes = dataviz.ui.themes || {};
                var themeOptions;

                if (dataviz.SASS_THEMES.indexOf(themeName) != -1) {
                    that.element.addClass("k-diagram");
                    themeOptions = dataviz.diagramTheme(that.element[0]);
                    that.element.removeClass("k-diagram");
                }
                else {
                    themeOptions = (themes[themeName] || {}).diagram;
                }

                return themeOptions;
            },

            items: function() {
                return $();
            },

            _createGlobalToolBar: function() {
                const widget = this.widget;
                var editable = this.widget.options.editable;

                if (editable) {
                    var tools = editable.tools;
                    if (this._isEditable && tools !== false && (!tools || tools.length === 0)) {
                        tools = ["createShape", "undo", "redo", "rotateClockwise", "rotateAnticlockwise"];
                    }

                    if (tools && tools.length) {
                        this.toolBar = new DiagramToolBar(this, {
                            tools: tools || {},
                            click: this._toolBarClick.bind(this),
                            modal: false
                        });

                        this.widget.toolBar = { element: this.toolBar.element[0] };

                        this.toolBar.element.css({
                            textAlign: "left"
                        });

                        this.element.prepend(this.toolBar.element);
                        this.widget._resize();
                    }
                }
            },

            _toolBarClick: function(e) {
                this.trigger("toolBarClick", e);
                this._destroyToolBar();
            },

            _createToolBar: function(preventClosing) {
                const diagram = this;
                const widget = this.widget;

                if (!this.singleToolBar && diagram.select().length === 1) {
                    var element = diagram.select()[0];
                    if (element && element.options.editable !== false) {
                        var editable = element.options.editable || {};
                        var tools = editable.tools;
                        if (this._isEditable && tools && tools.length === 0) {
                            if (element instanceof Shape) {
                                tools = ["edit", "rotateClockwise", "rotateAnticlockwise"];
                            } else if (element instanceof Connection) {
                                tools = ["edit"];
                            }

                            if (editable && editable.remove !== false) {
                                tools.push("delete");
                            }
                        }

                        if (tools && tools.length) {
                            var padding = 20;
                            var point;

                            this.singleToolBar = new DiagramToolBar(this, {
                                tools: tools,
                                click: this._toolBarClick.bind(this),
                                modal: true,
                                popupZIndex: parseInt(diagram.element.closest(".k-window").css("zIndex"), 10) + 10
                            });
                            var popupWidth = outerWidth(this.singleToolBar._popup.element);
                            var popupHeight = outerHeight(this.singleToolBar._popup.element);
                            if (element instanceof Shape) {
                                var shapeBounds = widget.modelToView(element.bounds(ROTATED));
                                point = new Point(shapeBounds.x, shapeBounds.y).minus(new Point(
                                    (popupWidth - shapeBounds.width) / 2,
                                    popupHeight + padding));
                            } else if (element instanceof Connection) {
                                var connectionBounds = widget.modelToView(element.bounds());

                                point = new Point(connectionBounds.x, connectionBounds.y)
                                    .minus(new Point(
                                        (popupWidth - connectionBounds.width - 20) / 2,
                                        popupHeight + padding
                                    ));
                            }

                            if (point) {
                                if (!widget.canvas.translate) {
                                    point = point.minus(new Point(widget.scroller.scrollLeft, widget.scroller.scrollTop));
                                }
                                point = this.viewToDocument(point);
                                point = new Point(Math.max(point.x, 0), Math.max(point.y, 0));
                                this.singleToolBar.showAt(point);
                                if (preventClosing) {
                                    this.singleToolBar._popup.one("close", preventDefault);
                                }
                            } else {
                                this._destroyToolBar();
                            }
                        }
                    }
                }
            },

            _destroyToolBar: function() {
                if (this.singleToolBar) {
                    this.singleToolBar.hide();
                    this.singleToolBar.destroy();
                    this.singleToolBar = null;
                }
            },

            _destroyGlobalToolBar: function() {
                if (this.toolBar) {
                    this.toolBar.hide();
                    this.toolBar.destroy();
                    this.toolBar = null;
                }
            },

            _update: function() {
                if (this.editor && this.editor.end() &&
                    !this.trigger("save", this._editArgs())) {
                    this._getEditDataSource().sync();
                    this._destroyEditor();
                }
            },

            _cancel: function() {
                if (this.editor && !this.trigger("cancel", this._editArgs())) {
                    var model = this.editor.model;
                    this._getEditDataSource().cancelChanges(model);
                    var element = this.widget._connectionsDataMap[model.uid] || this.widget._dataMap[model.id];
                    if (element) {
                        element._setOptionsFromModel(model);
                    }
                    this._destroyEditor();
                }
            },

            _getEditDataSource: function() {
                return this.editor.options.type === "shape" ? this.dataSource : this.connectionsDataSource;
            },

            _editArgs: function() {
                var result = { container: this.editor.wrapper };
                result[this.editor.options.type] = this.editor.model;
                return result;
            },

            _fetchFreshData() {
                this._dataSource();

                if (this._isEditable) {
                    this._connectionDataSource();
                }

                if (this.options.autoBind !== false) {
                    if (this._isEditable) {
                        this._loadingShapes = true;
                        this._loadingConnections = true;
                        this.dataSource.fetch();
                        this.connectionsDataSource.fetch();
                    } else {
                        this.dataSource.fetch();
                    }
                }
            },

            _dataSource() {
                if (defined(this.options.connectionsDataSource)) {
                    this.widget._isEditable = true;
                    const dsOptions = this.options.dataSource || {};
                    const ds = Array.isArray(dsOptions) ? { data: dsOptions } : dsOptions;

                    if (this.dataSource && this._shapesRefreshHandler) {
                        this.dataSource
                            .unbind('change', this._shapesRefreshHandler)
                            .unbind('requestStart', this._shapesRequestStartHandler)
                            .unbind('error', this._shapesErrorHandler);
                    } else {
                        this._shapesRefreshHandler = this._refreshShapes.bind(this);
                        this._shapesRequestStartHandler = this._shapesRequestStart.bind(this);
                        this._shapesErrorHandler = this._error.bind(this);
                    }

                    this.dataSource = DataSource.create(ds)
                        .bind('change', this._shapesRefreshHandler)
                        .bind('requestStart', this._shapesRequestStartHandler)
                        .bind('error', this._shapesErrorHandler);
                } else {
                    this._treeDataSource();
                    this.widget._isEditable = false;
                }
            },

            _connectionDataSource() {
                const dsOptions = this.options.connectionsDataSource;
                if (dsOptions) {
                    const ds = Array.isArray(dsOptions) ? { data: dsOptions } : dsOptions;

                    if (this.connectionsDataSource && this._connectionsRefreshHandler) {
                        this.connectionsDataSource
                            .unbind('change', this._connectionsRefreshHandler)
                            .unbind('requestStart', this._connectionsRequestStartHandler)
                            .unbind('error', this._connectionsErrorHandler);
                    } else {
                        this._connectionsRefreshHandler = this._refreshConnections.bind(this);
                        this._connectionsRequestStartHandler = this._connectionsRequestStart.bind(this);
                        this._connectionsErrorHandler = this._connectionsError.bind(this);
                    }

                    this.connectionsDataSource = DataSource.create(ds)
                        .bind('change', this._connectionsRefreshHandler)
                        .bind('requestStart', this._connectionsRequestStartHandler)
                        .bind('error', this._connectionsErrorHandler);
                }
            },

            _shapesRequestStart(e) {
                if (e.type === 'read') {
                    this._loadingShapes = true;
                }
            },

            _connectionsRequestStart(e) {
                if (e.type === 'read') {
                    this._loadingConnections = true;
                }
            },

            _error() {
                this._loadingShapes = false;
            },

            _connectionsError() {
                this._loadingConnections = false;
            },

            _refreshShapes(e) {
                if (e.action === 'remove') {
                    if (this._shouldRefresh()) {
                        this._removeShapes(e.items);
                    }
                } else if (e.action === 'itemchange') {
                    if (this._shouldRefresh()) {
                        this._updateShapes(e.items, e.field);
                    }
                } else if (e.action === 'add') {
                    this._inactiveShapeItems.add(e.items);
                } else if (e.action === 'sync') {
                    this._syncShapes(); // e.items
                } else {
                    this.refresh();
                }
            },

            _shouldRefresh() {
                return this.widget._shouldRefresh();
            },

            _suspendModelRefresh() {
                this.widget._suspendModelRefresh();
            },

            _resumeModelRefresh() {
                this.widget._resumeModelRefresh();
            },

            refresh() {
                this._loadingShapes = false;
                if (!this._loadingConnections) {
                    this._rebindShapesAndConnections();
                }
            },

            _rebindShapesAndConnections() {
                this.clear();
                this._addShapes(this.dataSource.view());
                if (this.connectionsDataSource) {
                    this._addConnections(this.connectionsDataSource.view(), false);
                }

                if (this.options.layout) {
                    this.layout(this.options.layout);
                } else {
                    this._redrawConnections();
                }
                this.trigger('dataBound');
            },

            refreshConnections() {
                this._loadingConnections = false;
                if (!this._loadingShapes) {
                    this._rebindShapesAndConnections();
                }
            },

            _redrawConnections() {
                this.widget._redrawConnections();
            },

            _removeShapes(items) {
                const dataMap = this.widget._dataMap;
                let item, i;
                for (i = 0; i < items.length; i++) {
                    item = items[i];
                    if (dataMap[item.id]) {
                        this.remove(dataMap[item.id], false);
                        dataMap[item.id] = null;
                    }
                }
            },

            _syncShapes() {
                const inactiveItems = this._inactiveShapeItems;
                inactiveItems.forEach((inactiveItem) => {
                    const dataItem = inactiveItem.dataItem;
                    const shape = inactiveItem.element;
                    if (!dataItem.isNew()) {
                        if (shape) {
                            shape._setOptionsFromModel();
                            this.addShape(shape, inactiveItem.undoable);
                            this.widget._dataMap[dataItem.id] = shape;
                        } else {
                            this._addDataItem(dataItem);
                        }
                        inactiveItem.activate();
                        inactiveItems.remove(dataItem);
                    }
                });
            },

            _updateShapes(items, field) {
                for (let i = 0; i < items.length; i++) {
                    const dataItem = items[i];

                    const shape = this.widget._dataMap[dataItem.id];
                    if (shape) {
                        shape.updateOptionsFromModel(dataItem, field);
                    }
                }
            },

            _addShapes(dataItems) {
                for (let i = 0; i < dataItems.length; i++) {
                    this._addDataItem(dataItems[i], false);
                }
            },

            _addDataItem(dataItem, undoable) {
                if (!Utils.defined(dataItem)) {
                    return;
                }

                let shape = this.widget._dataMap[dataItem.id];
                if (shape) {
                    return shape;
                }

                const options = deepExtend({}, this.widget.options.shapeDefaults);
                options.dataItem = dataItem;
                shape = new Shape(options, this.widget);
                this.addShape(shape, undoable !== false);
                this.widget._dataMap[dataItem.id] = shape;
                return shape;
            },

            _refreshConnections(e) {
                if (e.action === 'remove') {
                    if (this._shouldRefresh()) {
                        this._removeConnections(e.items);
                    }
                } else if (e.action === 'add') {
                    this._addConnections(e.items);
                } else if (e.action === 'sync') {
                    // TO DO: include logic to update the connections with different values returned from the server.
                } else if (e.action === 'itemchange') {
                    if (this._shouldRefresh()) {
                        this._updateConnections(e.items);
                    }
                } else {
                    this.refreshConnections();
                }
            },

            _removeConnections(items) {
                for (let i = 0; i < items.length; i++) {
                    this.remove(this.widget._connectionsDataMap[items[i].uid], false);
                    this.widget._connectionsDataMap[items[i].uid] = null;
                }
            },

            _updateConnections(items) {
                for (let i = 0; i < items.length; i++) {
                    const dataItem = items[i];

                    const connection = this.widget._connectionsDataMap[dataItem.uid];
                    connection.updateOptionsFromModel(dataItem);
                }
            },

            _addConnections(connections, undoable) {
                this.widget._addConnections(connections, undoable);
            },

            _addShape(shape, undoable) {
                const dataSource = this.dataSource;
                let dataItem;
                if (dataSource && this._isEditable) {
                    dataItem = createModel(dataSource, cloneDataItem(shape.dataItem));
                    shape.dataItem = dataItem;
                    shape.updateModel();

                    if (!this.trigger('add', { shape: shape })) {
                        this.dataSource.add(dataItem);
                        const inactiveItem = this.widget._inactiveShapeItems.getByUid(dataItem.uid);
                        inactiveItem.element = shape;
                        inactiveItem.undoable = undoable;
                        return shape;
                    }
                } else if (!this.trigger('add', { shape: shape })) {
                    return this.addShape(shape, undoable);
                }
            },

            _addConnection(connection, undoable) {
                const connectionsDataSource = this.connectionsDataSource;
                let dataItem;
                if (connectionsDataSource && this._isEditable) {
                    dataItem = createModel(connectionsDataSource, cloneDataItem(connection.dataItem));
                    connection.dataItem = dataItem;
                    connection.updateModel();

                    if (!this.trigger('add', { connection: connection })) {
                        this.widget._connectionsDataMap[dataItem.uid] = connection;

                        connectionsDataSource.add(dataItem);
                        this.addConnection(connection, undoable);
                        connection._updateConnectors();

                        return connection;
                    }
                } else if (!this.trigger('add', { connection: connection })) {
                    this.addConnection(connection, undoable);
                    connection._updateConnectors();
                    return connection;
                }
            },

            _treeDataSource() {
                const options = this.widget.options;
                let dataSource = options.dataSource;

                dataSource = Array.isArray(dataSource) ? { data: dataSource } : dataSource;

                if (dataSource instanceof DataSource && !(dataSource instanceof HierarchicalDataSource)) {
                    throw new Error('Incorrect DataSource type. If a single dataSource instance is set to the diagram then it should be a HierarchicalDataSource. You should set only the options instead of an instance or a HierarchicalDataSource instance or supply connectionsDataSource as well.');
                }

                if (!dataSource.fields) {
                    dataSource.fields = [
                        { field: 'text' },
                        { field: 'url' },
                        { field: 'spriteCssClass' },
                        { field: 'imageUrl' }
                    ];
                }
                if (this.dataSource && this._refreshHandler) {
                    this._unbindDataSource();
                }

                this._refreshHandler = this._refreshSource.bind(this);
                this._errorHandler = this._error.bind(this);

                this.dataSource = HierarchicalDataSource.create(dataSource)
                    .bind(CHANGE, this._refreshHandler)
                    .bind(ERROR, this._errorHandler);
            },

            _unbindDataSource() {
                this.dataSource.unbind(CHANGE, this._refreshHandler).unbind(ERROR, this._errorHandler);
            },

            _refreshSource(e) {
                const node = e.node,
                    action = e.action,
                    items = e.items,
                    options = this.options;
                let idx,
                    dataBound;

                if (e.field) {
                    for (idx = 0; idx < items.length; idx++) {
                        if (this.widget._dataMap[items[idx].uid]) {
                            this.widget._dataMap[items[idx].uid].redrawVisual();
                        }
                    }
                    return;
                }

                if (action === 'remove') {
                    this._removeDataItems(e.items, true);
                } else {

                    if ((!action || action === 'itemloaded') && !this._bindingRoots) {
                        this._bindingRoots = true;
                        dataBound = true;
                    }

                    if (!action && !node) {
                        this.clear();
                    }

                    this.widget._addDataItems(items, node);

                    for (idx = 0; idx < items.length; idx++) {
                        items[idx].load();
                    }
                }

                if (options.layout && (dataBound || action === 'remove' || action === 'add')) {
                    this.layout(options.layout);
                }

                if (dataBound) {
                    this.trigger('dataBound');
                    this._bindingRoots = false;
                }
            },

            _removeDataItems(items, recursive) {
                let item, children, shape, idx;
                items = Array.isArray(items) ? items : [items];

                while (items.length) {
                    item = items.shift();
                    shape = this.widget._dataMap[item.uid];
                    if (shape) {
                        this.widget._removeShapeConnections(shape);
                        this.widget._removeItem(shape, false);
                        delete this.widget._dataMap[item.uid];
                        if (recursive && item.hasChildren && item.loaded()) {
                            children = item.children.data();
                            for (idx = 0; idx < children.length; idx++) {
                                items.push(children[idx]);
                            }
                        }
                    }
                }
            },

            _removeConnectionDataItem(dataItem) {
                this.connectionsDataSource.remove(dataItem);
            },

            _removeShapeDataItem(dataItem) {
                this.dataSource.remove(dataItem);
            },

            _clearTooltips: function() {
                if (this._shapeTooltip) {
                    this._shapeTooltip.remove();
                    this._shapeTooltip = null;
                }
                if (this._connectionTooltip) {
                    this._connectionTooltip.remove();
                    this._connectionTooltip = null;
                }
            },

            _positionTooltip: function(tooltip, hovered, point) {
                const that = this;
                const diagram = that.widget;
                const zoom = diagram.zoom();
                const pan = diagram.pan();
                const toolTipEleement = tooltip[0];

                const tooltipRect = toolTipEleement.getBoundingClientRect();
                const diagramRect = diagram.element.getBoundingClientRect();
                const win = diagram.element.ownerDocument.defaultView;
                const connections = diagram.connections.map(con => con.allPoints());
                const shapes = diagram.shapes.map(shp => shp.bounds());

                const pos = placeTooltip({
                    hovered: hovered,
                    mouse: point,
                    shapes: shapes,
                    connections: connections,
                    diagramRect: diagramRect,
                    zoom: zoom,
                    pan: pan,
                    tooltipSize: { width: tooltipRect.width, height: tooltipRect.height },
                    viewportBounds: new DOMRect(0, 0, win.innerWidth, win.innerHeight)
                });

                toolTipEleement.style.left = toPx(pos.left + win.scrollX);
                toolTipEleement.style.top = toPx(pos.top + win.scrollY);
            },

            _createTooltip: function(shape, point, template, isShape) {
                const that = this;
                const tooltip = isShape ? "_shapeTooltip" : "_connectionTooltip";

                that[tooltip] = $(toolTipContainer(kendo.template(template)(shape))).css("position", "absolute");
                that._positionTooltip(that[tooltip], shape, point);
                that[tooltip].appendTo("body");
            },

            _destroyEditor: function() {
                if (this.editor) {
                    this.editor.close();
                    this.editor = null;
                }
            }
        });

        dataviz.ExportMixin.extend(Diagram.fn, true);

        if (kendo.PDFMixin) {
            kendo.PDFMixin.extend(Diagram.fn);
        }

        var DiagramToolBar = kendo.Observable.extend({
            init: function(diagram, options) {
                kendo.Observable.fn.init.call(this);
                this.diagram = diagram;
                this.options = deepExtend({}, this.options, options);
                this._tools = [];
                this.createToolBar();
                this.createTools();
                this.appendTools();

                if (this.options.modal) {
                    this.createPopup();
                }

                this.bind(this.events, options);
            },

            events: ["click"],

            createPopup: function() {
                this.container = $("<div/>").append(this.element);
                this._popup = this.container.kendoPopup({}).getKendoPopup();
            },

            appendTools: function() {
                for (var i = 0; i < this._tools.length; i++) {
                    var tool = this._tools[i];
                    if (tool.buttons && tool.buttons.length || !defined(tool.buttons)) {
                        this._toolBar.add(tool);
                    }
                }
            },

            createToolBar: function() {
                this.element = $("<div/>");
                this._toolBar = this.element
                    .kendoToolBar({
                        click: this.click.bind(this),
                        resizable: false
                    }).getKendoToolBar();

                this.element.css("border", "none");
            },

            createTools: function() {
                for (var i = 0; i < this.options.tools.length; i++) {
                    this.createTool(this.options.tools[i]);
                }
            },

            createTool: function(tool) {
                if (!isPlainObject(tool)) {
                    tool = {
                        name: tool
                    };
                }
                var toolName = tool.name + "Tool";
                if (this[toolName]) {
                    this[toolName](tool);
                } else {
                    this._tools.push(deepExtend({}, tool, {
                        attributes: this._setAttributes({ action: tool.name })
                    }));
                }
            },

            showAt: function(point) {
                var popupZIndex = parseInt(this.options.popupZIndex, 10);

                if (this._popup) {
                    this._popup.open(point.x, point.y);

                    if (popupZIndex) {
                        this._popup.wrapper.css("zIndex", popupZIndex);
                    }
                }
            },

            hide: function() {
                if (this._popup) {
                    this._popup.close();
                }
            },

            newGroup: function() {
                return {
                    type: "buttonGroup",
                    buttons: []
                };
            },

            editTool: function() {
                this._tools.push({
                    icon: "pencil",
                    showText: "overflow",
                    type: "button",
                    text: "Edit",
                    attributes: this._setAttributes({ action: "edit" })
                });
            },

            deleteTool: function() {
                this._tools.push({
                    icon: "x",
                    showText: "overflow",
                    type: "button",
                    text: "Delete",
                    attributes: this._setAttributes({ action: "delete" })
                });
            },

            rotateAnticlockwiseTool: function(options) {
                this._appendGroup("rotate");
                this._rotateGroup.buttons.push({
                    icon: "rotate-left",
                    showText: "overflow",
                    text: "RotateAnticlockwise",
                    group: "rotate",
                    attributes: this._setAttributes({ action: "rotateAnticlockwise", step: options.step })
                });
            },

            rotateClockwiseTool: function(options) {
                this._appendGroup("rotate");
                this._rotateGroup.buttons.push({
                    icon: "rotate-right",
                    attributes: this._setAttributes({ action: "rotateClockwise", step: options.step }),
                    showText: "overflow",
                    text: "RotateClockwise",
                    group: "rotate"
                });
            },

            createShapeTool: function() {
                this._appendGroup("create");
                this._createGroup.buttons.push({
                    icon: "shapes",
                    showText: "overflow",
                    text: "CreateShape",
                    group: "create",
                    attributes: this._setAttributes({ action: "createShape" })
                });
            },

            createConnectionTool: function() {
                this._appendGroup("create");
                this._createGroup.buttons.push({
                    icon: "connector",
                    showText: "overflow",
                    text: "CreateConnection",
                    group: "create",
                    attributes: this._setAttributes({ action: "createConnection" })
                });
            },

            undoTool: function() {
                this._appendGroup("history");
                this._historyGroup.buttons.push({
                    icon: "undo",
                    showText: "overflow",
                    text: "Undo",
                    group: "history",
                    attributes: this._setAttributes({ action: "undo" })
                });
            },

            redoTool: function() {
                this._appendGroup("history");
                this._historyGroup.buttons.push({
                    icon: "redo",
                    showText: "overflow",
                    text: "Redo",
                    group: "history",
                    attributes: this._setAttributes({ action: "redo" })
                });
            },

            _appendGroup: function(name) {
                var prop = "_" + name + "Group";
                if (!this[prop]) {
                    this[prop] = this.newGroup();
                    this._tools.push(this[prop]);
                }
            },

            _setAttributes: function(attributes) {
                var attr = {};

                if (attributes.action) {
                    attr[kendo.attr("action")] = attributes.action;
                }

                if (attributes.step) {
                    attr[kendo.attr("step")] = attributes.step;
                }

                return attr;
            },

            _getAttributes: function(element) {
                var attr = {};

                var action = element.attr(kendo.attr("action"));
                if (action) {
                    attr.action = action;
                }

                var step = element.attr(kendo.attr("step"));
                if (step) {
                    attr.step = step;
                }

                return attr;
            },

            click: function(e) {
                var attributes = this._getAttributes($(e.target));
                var action = attributes.action;

                if (action && this[action]) {
                    this[action](attributes);
                }

                this.trigger("click", this.eventData(action, e.target));
            },

            eventData: function(action, target) {
                var elements = this.selectedElements(),
                    length = elements.length,
                    shapes = [], connections = [], element;

                for (var idx = 0; idx < length; idx++) {
                    element = elements[idx];
                    if (element instanceof Shape) {
                        shapes.push(element);
                    } else {
                        connections.push(element);
                    }
                }

                return {
                    shapes: shapes,
                    connections: connections,
                    action: action,
                    target: target
                };
            },

            "delete": function() {
                var diagram = this.diagram;
                var toRemove = diagram.widget._triggerRemove(this.selectedElements());
                if (toRemove.length) {
                    this.diagram.remove(toRemove, true);
                    this.diagram._syncChanges();
                }
            },

            edit: function() {
                var selectedElemens = this.selectedElements();
                if (selectedElemens.length === 1) {
                    this.diagram.edit(selectedElemens[0]);
                }
            },

            rotateClockwise: function(options) {
                var angle = parseFloat(options.step || 90);
                this._rotate(angle);
            },

            rotateAnticlockwise: function(options) {
                var angle = parseFloat(options.step || 90);
                this._rotate(-angle);
            },

            _rotate: function(angle) {
                var adorner = this.diagram.widget._resizingAdorner;
                adorner.angle(adorner.angle() + angle);
                adorner.rotate();
            },

            selectedElements: function() {
                return this.diagram.select();
            },

            createShape: function() {
                this.diagram.createShape();
            },

            createConnection: function() {
                this.diagram.createConnection();
            },

            undo: function() {
                this.diagram.undo();
            },

            redo: function() {
                this.diagram.redo();
            },

            destroy: function() {
                this.diagram = null;
                this.element = null;
                this.options = null;

                if (this._toolBar) {
                    this._toolBar.destroy();
                }

                if (this._popup) {
                    this._popup.destroy();
                }
            }
        });

        var Editor = kendo.Observable.extend({
            init: function(element, options) {
                kendo.Observable.fn.init.call(this);

                this.options = extend(true, {}, this.options, options);
                this.element = element;
                this.model = this.options.model;
                this.fields = this._getFields();
                this._initContainer();
                this.createEditable();
            },

            options: {
                editors: {}
            },

            _initContainer: function() {
                this.wrapper = this.element;
            },

            createEditable: function() {
                var options = this.options;

                this.editable = new kendo.ui.Editable(this.wrapper, {
                    fields: this.fields,
                    target: options.target,
                    clearContainer: false,
                    model: this.model
                });
            },

            _isEditable: function(field) {
                return this.model.editable && this.model.editable(field);
            },

            _getFields: function() {
                var fields = [];
                var modelFields = this.model.fields;

                for (var field in modelFields) {
                    var result = {};
                    if (this._isEditable(field)) {
                        var editor = this.options.editors[field];
                        if (editor) {
                            result.editor = editor;
                        }
                        result.field = field;
                        fields.push(result);
                    }
                }

                return fields;
            },

            end: function() {
                return this.editable.end();
            },

            destroy: function() {
                this.editable.destroy();
                this.editable.element.find("[" + kendo.attr("container-for") + "]").empty();
                this.model = this.wrapper = this.element = this.columns = this.editable = null;
            }
        });

        var PopupEditor = Editor.extend({
            init: function(element, options) {
                Editor.fn.init.call(this, element, options);
                this.bind(this.events, this.options);

                this.open();
            },

            events: [ "update", "cancel" ],

            options: {
                window: {
                    modal: true,
                    resizable: false,
                    draggable: true,
                    title: "Edit",
                    visible: false
                }
            },

            _initContainer: function() {
                var that = this;
                this.wrapper = $('<div class="k-popup-edit-form"/>')
                    .attr(kendo.attr("uid"), this.model.uid);

                var formContent = "";

                if (this.options.template) {
                    formContent += this._renderTemplate();
                    this.fields = [];
                } else {
                    formContent += this._renderFields();
                }

                formContent += this._renderButtons();

                this.wrapper.append(
                    $('<div class="k-edit-form-container"/>').append(formContent));

                this.window = new kendo.ui.Window(this.wrapper.appendTo(this.element), this.options.window);
                this.window.bind("close", function(e) {
                    //The bellow line is required due to: draggable window in IE, change event will be triggered while the window is closing
                    if (e.userTriggered) {
                        e.sender.element.trigger("focus");
                        that._cancelClick(e);
                    }
                });

                this._attachButtonEvents();
            },

            _renderTemplate: function() {
                var template = this.options.template;

                if (typeof template === "string") {
                    template = kendo.unescape(template);
                }

                template = kendo.template(template)(this.model);

                return template;
            },

            _renderFields: function() {
                var form = "";
                for (var i = 0; i < this.fields.length; i++) {
                    var field = this.fields[i];

                    form += '<div class="k-edit-label"><label for="' + field.field + '">' + (field.field || "") + '</label></div>';

                    if (this._isEditable(field.field)) {
                        form += '<div ' + kendo.attr("container-for") + '="' + field.field +
                        '" class="k-edit-field"></div>';
                    }
                }

                return form;
            },

            _renderButtons: function() {
                var form = '<div class="k-edit-buttons">';
                form += this._createButton("update");
                form += this._createButton("cancel");
                form += '</div>';
                return form;
            },

            _createButton: function(name) {
                return kendo.template(BUTTON_TEMPLATE)(defaultButtons[name]);
            },

            _attachButtonEvents: function() {
                this._cancelClickHandler = this._cancelClick.bind(this);
                this.window.element.on(CLICK + NS, "button.k-diagram-cancel", this._cancelClickHandler);

                this._updateClickHandler = this._updateClick.bind(this);
                this.window.element.on(CLICK + NS, "button.k-diagram-update", this._updateClickHandler);
            },

            _updateClick: function(e) {
                e.preventDefault();
                this.trigger("update");
            },

            _cancelClick: function(e) {
                e.preventDefault();
                this.trigger("cancel");
            },

            open: function() {
                this.window.center().open();
            },

            close: function() {
                this.window.bind("deactivate", this.destroy.bind(this)).close();
            },

            destroy: function() {
                this.window.close().destroy();
                this.window.element.off(CLICK + NS, "a.k-diagram-cancel", this._cancelClickHandler);
                this.window.element.off(CLICK + NS, "a.k-diagram-update", this._updateClickHandler);
                this._cancelClickHandler = null;
                this._editUpdateClickHandler = null;
                this.window = null;
                Editor.fn.destroy.call(this);
            }
        });

        function connectionSelector(container, options) {
            var model = this.dataSource.reader.model;
            if (model) {
                var textField = model.fn.fields.text ? "text" : model.idField;
                $("<input name='" + options.field + "' />")
                    .appendTo(container).kendoDropDownList({
                        dataValueField: model.idField,
                        dataTextField: textField,
                        dataSource: this.dataSource.data().toJSON(),
                        optionLabel: " ",
                        valuePrimitive: true
                    });
            }
        }

        function cloneDataItem(dataItem) {
            var result = dataItem;
            if (dataItem instanceof kendo.data.Model) {
                result = dataItem.toJSON();
                result[dataItem.idField] = dataItem._defaultId;
            }
            return result;
        }

        function createModel(dataSource, model) {
            if (dataSource.reader.model) {
                return new dataSource.reader.model(model);
            }

            return new kendo.data.ObservableObject(model);
        }

        dataviz.ui.plugin(Diagram);

        deepExtend(diagram, {
            Shape: Shape,
            Connection: Connection,
            Connector: Connector,
            DiagramToolBar: DiagramToolBar,
            QuadNode: QuadNode,
            QuadRoot: QuadRoot,
            ShapesQuadTree: ShapesQuadTree,
            PopupEditor: PopupEditor
        });
})(window.kendo.jQuery);
