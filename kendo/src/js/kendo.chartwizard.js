/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.core.js";
import "./kendo.icons.js";
import "./kendo.grid.js";
import "./kendo.expansionpanel.js";
import "./kendo.window.js";
import "./kendo.splitter.js";
import "./kendo.form.js";
import "./kendo.tabstrip.js";
import "./kendo.dataviz.chart.js";
import "./kendo.html.icon.js";
import "./kendo.dropdownbutton.js";
import "./kendo.colorpicker.js";
import { ChartWizardCommon } from "@progress/kendo-charts";

export const __meta__ = {
    id: "chartwizard",
    name: "ChartWizard",
    category: "web",
    description: "The ChartWizard allows configuration and visual representation of various charts",
    depends: ["core", "icons", "html.icon", "grid", "expansionpanel", "window", "form", "tabstrip", "dataviz.chart", "splitter", "dropdownbutton", "colorpicker"],
};

(function($) {
    const kendo = window.kendo,
        Widget = kendo.ui.Widget,
        DataSource = kendo.data.DataSource,
        keys = kendo.keys,
        fontSizes = ChartWizardCommon.fontSizes,
        fontNames = ChartWizardCommon.fontNames,
        isCategorical = ChartWizardCommon.isCategorical,
        updateState = ChartWizardCommon.updateState,
        mergeStates = ChartWizardCommon.mergeStates,
        actionTypes = ChartWizardCommon.ActionTypes,
        getWizardDataFromDataRows = ChartWizardCommon.getWizardDataFromDataRows,
        NS = ".kendoChartWizard",
        ui = kendo.ui,
        extend = $.extend,
        deepExtend = kendo.deepExtend,
        RESIZING = "resizing",
        RESIZE = "resize",
        MAXIMIZE = "maximize",
        RESTORE = "restore",
        CLICK = "click",
        KEYDOWN = "keydown",
        OPEN = "open",
        CLOSE = "close",
        ACTIVATE = "activate",
        CHANGE = "change",
        DATA_BINGING = "dataBinding",
        DATA_BOUND = "dataBound",
        EXPORT_PDF = "exportPDF",
        EXPORT_SVG = "exportSVG",
        EXPORT_IMAGE = "exportImage",
        ICON_SIZE = "xlarge",
        CHART = 'chart',
        DATA = 'data',
        FORMAT = 'format',
        DOT = ".",
        REF = "ref",
        REF_SELECTOR = "ref*=",
        DATA_ACTION = "data-action",
        DATA_ACTION_SELECTOR = "[data-action]",
        DATA_ROLE = "data-role",
        DATA_ROLE_SELECTOR = "data-role=",
        DATA_CHART_TYPE_ATTR = "data-chart-type",
        SELECTED_STATE = "k-selected",
        FILL_MODE_OUTLINE = "outline",
        FILL_MODE_FLAT = "flat",
        STACKED = "stacked",
        INITIAL_TYPE = "bar",
        HUNDRED_STACKED = "hundredstacked",
        REG_EXP_CAPITAL = /(?=[A-Z])/;


    const cssClasses = {
        chartWizard: "k-chart-wizard",
        previewPane: "k-chart-wizard-preview-pane",
        previewPaneHeader: "k-preview-pane-header",
        previewPaneContent: "k-preview-pane-content",
        propertyPane: "k-chart-wizard-property-pane",
        splitter: "k-chart-wizard-splitter",
        expansionPanelWrapper: "k-expander",
        cols2gap4: "k-grid-cols-2 k-gap-x-4",
        colSpan2: "k-col-span-2",
    };

    const inputRoles = {
        numericTextBox: "numerictextbox",
        colorPicker: "colorpicker",
        textBox: "textbox",
        comboBox: "combobox",
        checkBox: "checkbox",
        dropDownList: "dropdownlist",
        switch: "switch",
    };

    const exportButtonOptions = {
        icon: "export",
        fillMode: FILL_MODE_FLAT,
        items: [
            { id: "export-pdf", icon: "file-pdf" },
            { id: "export-svg", icon: "file" },
            { id: "export-png", icon: "file-image" },
        ]
    };

    const expansionPanelForChart = {
        bar: {
            content: {
                bar: "chart-bar-clustered",
                stackedBar: "chart-bar-stacked",
                hundredStackedBar: "chart-bar-stacked100",
            },
        },
        pie: {
            content: {
                pie: "chart-pie",
            }
        },
        column: {
            content: {
                column: "chart-column-clustered",
                stackedColumn: "chart-column-stacked",
                hundredStackedColumn: "chart-column-stacked100",
            }
        },
        line: {
            content: {
                line: "chart-line",
                stackedLine: "chart-line-stacked",
                hundredStackedLine: "chart-line-stacked100",
            }
        },
        scatter: {
            content: {
                scatter: "chart-scatter"
            }
        },
    };

    const dataTabPanel = (messages) => [
        {
            legend: messages.configuration.categoryAxis, editors: {
                id: "category-axis",
                role: inputRoles.dropDownList,
                action: "categoryAxisX"
            }
        },
        {
            legend: messages.configuration.valueAxis, editors: {
                id: "value-axis",
                role: inputRoles.dropDownList,
                action: "valueAxisY"
            }
        },
        {
            legend: messages.configuration.series.title, editors: {
                custom: `<div class="k-grid" ${DATA_ACTION}="seriesChange"></div>`
            }
        },
    ];


    const chartAreaPanel = (messages) =>
    ([
        {
            legend: messages.chartArea.margins.default,
            layoutClass: cssClasses.cols2gap4,
            editors: [
                {
                    label: {
                        text: messages.chartArea.margins.left,
                        for: "left"
                    },
                    field: {
                        id: "left",
                        role: inputRoles.numericTextBox,
                        placeholder: messages.chartArea.margins.auto,
                        action: "areaMarginLeft"
                    }
                },
                {
                    label: {
                        text: messages.chartArea.margins.right,
                        for: "right"
                    },
                    field: {
                        id: "right",
                        role: inputRoles.numericTextBox,
                        placeholder: messages.chartArea.margins.auto,
                        action: "areaMarginRight"
                    }
                },
                {
                    label: {
                        text: messages.chartArea.margins.top,
                        for: "top"
                    },
                    field: {
                        id: "top",
                        role: inputRoles.numericTextBox,
                        placeholder: messages.chartArea.margins.auto,
                        action: "areaMarginTop"
                    }
                },
                {
                    label: {
                        text: messages.chartArea.margins.bottom,
                        for: "bottom"
                    },
                    field: {
                        id: "bottom",
                        role: inputRoles.numericTextBox,
                        placeholder: messages.chartArea.margins.auto,
                        action: "areaMarginBottom"
                    }
                },
            ]
        },
        {
            legend: messages.chartArea.background.default,
            editors: [
                {
                    label: {
                        text: messages.chartArea.background.color,
                        for: 'background'
                    },
                    field: {
                        id: 'background',
                        role: inputRoles.colorPicker,
                        action: "areaBackground"
                    }
                }
            ]
        }
    ]);

    const titlePanel = (messages) =>
    ([
        {
            layoutClass: cssClasses.cols2gap4,
            editors: [
                {
                    label: {
                        text: messages.title.applyTo,
                        for: "apply-to-title"
                    },
                    field: {
                        id: "apply-to-title",
                        role: inputRoles.dropDownList,
                        action: "activateTitle"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.title.label,
                        for: "title-text"
                    },
                    field: {
                        id: "title-text",
                        role: inputRoles.textBox,
                        action: "titleText"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.title.font,
                        for: "title-font"
                    },
                    field: {
                        id: "title-font",
                        role: inputRoles.comboBox,
                        placeholder: messages.title.fontPlaceholder,
                        action: "titleFontName"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.title.size,
                        for: "title-size"
                    },
                    field: {
                        id: "title-size",
                        role: inputRoles.comboBox,
                        placeholder: messages.title.sizePlaceholder,
                        action: "titleFontSize"
                    }
                },
                {
                    label: {
                        text: messages.title.color,
                        for: 'title-color'
                    },
                    field: {
                        id: 'title-color',
                        role: inputRoles.colorPicker,
                        action: "titleColor"
                    }
                }
            ]
        },
    ]);

    const seriesPanel = (messages) =>
    ([
        {
            editors: [
                {
                    label: {
                        text: messages.series.applyTo,
                        for: "apply-to-series"
                    },
                    field: {
                        id: "apply-to-series",
                        role: inputRoles.dropDownList,
                        action: "activateSeries"
                    }
                },
                {
                    label: {
                        text: messages.series.color,
                        for: "series-color"
                    },
                    field: {
                        id: "series-color",
                        role: inputRoles.colorPicker,
                        action: "seriesColor"
                    }
                },
                {
                    field: {
                        id: "show-labels",
                        type: "checkbox",
                        role: inputRoles.checkBox,
                        action: "seriesLabel"
                    }
                },
            ]
        },
    ]);


    const legendPanel = (messages) =>
    ([
        {
            layoutClass: cssClasses.cols2gap4,
            editors: [
                {
                    nowrap: true,
                    label: {
                        text: messages.legend.showLegend,
                        for: "show-legend"
                    },
                },
                {
                    nowrap: true,
                    field: {
                        id: "show-legend",
                        role: inputRoles.switch,
                        action: "legendVisible"
                    }
                },
                {
                    label: {
                        text: messages.legend.font,
                        for: "legend-font"
                    },
                    field: {
                        id: "legend-font",
                        placeholder: messages.legend.fontPlaceholder,
                        role: inputRoles.comboBox,
                        action: "legendFontName"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.legend.size,
                        for: "legend-size"
                    },
                    field: {
                        id: "legend-size",
                        role: inputRoles.comboBox,
                        placeholder: messages.legend.sizePlaceholder,
                        action: "legendFontSize"
                    }
                },
                {
                    label: {
                        text: messages.title.color,
                        for: 'legend-color'
                    },
                    field: {
                        id: 'legend-color',
                        role: inputRoles.colorPicker,
                        action: "legendColor"
                    }
                },
                {
                    label: {
                        text: messages.legend.position.default,
                        for: 'legend-position'
                    },
                    field: {
                        id: 'legend-position',
                        role: inputRoles.dropDownList,
                        action: "legendPosition"
                    },
                    className: cssClasses.colSpan2
                }
            ]
        },
    ]);

    const categoryAxisPanel = (messages) =>
    ([
        {
            legend: messages.categoryAxis.title.text,
            layoutClass: cssClasses.cols2gap4,
            editors: [
                {
                    field: {
                        id: "category-axis-title",
                        role: inputRoles.textBox,
                        placeholder: messages.categoryAxis.title.placeholder,
                        action: "categoryAxisTitleText"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.categoryAxis.title.font,
                        for: "category-axis-title-font"
                    },
                    field: {
                        id: "category-axis-title-font",
                        role: inputRoles.comboBox,
                        placeholder: messages.categoryAxis.title.fontPlaceholder,
                        action: "categoryAxisTitleFontName"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.categoryAxis.title.size,
                        for: "category-axis-title-size"
                    },
                    field: {
                        id: "category-axis-title-size",
                        role: inputRoles.comboBox,
                        placeholder: messages.categoryAxis.title.sizePlaceholder,
                        action: "categoryAxisTitleFontSize"
                    }
                },
                {
                    label: {
                        text: messages.categoryAxis.title.color,
                        for: "category-axis-title-color"
                    },
                    field: {
                        id: "category-axis-title-color",
                        role: inputRoles.colorPicker,
                        action: "categoryAxisTitleColor"
                    }
                },
            ]
        },
        {
            layoutClass: cssClasses.cols2gap4,
            legend: messages.categoryAxis.labels.text,
            editors: [
                {
                    label: {
                        text: messages.categoryAxis.labels.font,
                        for: "category-axis-labels-font"
                    },
                    field: {
                        id: 'category-axis-labels-font',
                        role: inputRoles.comboBox,
                        placeholder: messages.categoryAxis.labels.fontPlaceholder,
                        action: "categoryAxisLabelsFontName"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.categoryAxis.labels.size,
                        for: "category-axis-labels-size"
                    },
                    field: {
                        id: 'category-axis-labels-size',
                        role: inputRoles.comboBox,
                        placeholder: messages.categoryAxis.labels.sizePlaceholder,
                        action: "categoryAxisLabelsFontSize"
                    }
                },
                {
                    label: {
                        text: messages.categoryAxis.labels.color,
                        for: "category-axis-labels-color"
                    },
                    field: {
                        id: 'category-axis-labels-color',
                        role: inputRoles.colorPicker,
                        action: "categoryAxisLabelsColor"
                    }
                },
                {
                    label: {
                        text: messages.categoryAxis.labels.rotation.text,
                        for: "category-axis-labels-rotation"
                    },
                    field: {
                        id: 'category-axis-labels-rotation',
                        role: inputRoles.numericTextBox,
                        placeholder: messages.categoryAxis.labels.rotation.auto,
                        action: "categoryAxisLabelsRotation"
                    }
                },
                {
                    field: {
                        id: 'category-axis-labels-reverse-order',
                        role: inputRoles.checkBox,
                        action: "categoryAxisReverseOrder"
                    },
                    className: cssClasses.colSpan2
                },
            ]
        }
    ]);
    const valueAxisPanel = (messages) =>
    ([
        {
            legend: messages.valueAxis.title.text,
            layoutClass: cssClasses.cols2gap4,
            editors: [
                {
                    field: {
                        id: "value-axis-title",
                        role: inputRoles.textBox,
                        placeholder: messages.valueAxis.title.placeholder,
                        action: "valueAxisTitleText"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.categoryAxis.title.font,
                        for: "value-axis-title-font"
                    },
                    field: {
                        id: "value-axis-title-font",
                        role: inputRoles.comboBox,
                        placeholder: messages.valueAxis.title.fontPlaceholder,
                        action: "valueAxisTitleFontName"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.valueAxis.title.size,
                        for: "value-axis-title-size"
                    },
                    field: {
                        id: "value-axis-title-size",
                        role: inputRoles.comboBox,
                        placeholder: messages.valueAxis.title.sizePlaceholder,
                        action: "valueAxisTitleFontSize"
                    }
                },
                {
                    label: {
                        text: messages.valueAxis.title.color,
                        for: "value-axis-title-color"
                    },
                    field: {
                        id: "value-axis-title-color",
                        role: inputRoles.colorPicker,
                        action: "valueAxisTitleColor"
                    }
                },
            ]
        },
        {
            layoutClass: cssClasses.cols2gap4,
            legend: messages.valueAxis.labels.text,
            editors: [
                {
                    label: {
                        text: messages.valueAxis.labels.labelFormat.default,
                        for: "value-axis-label-format"
                    },
                    field: {
                        id: 'value-axis-label-format',
                        role: inputRoles.dropDownList,
                        action: "valueAxisLabelsFormat"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.valueAxis.labels.font,
                        for: "value-axis-label-font"
                    },
                    field: {
                        id: 'value-axis-label-font',
                        role: inputRoles.comboBox,
                        placeholder: messages.valueAxis.labels.fontPlaceholder,
                        action: "valueAxisLabelsFontName"
                    },
                    className: cssClasses.colSpan2
                },
                {
                    label: {
                        text: messages.valueAxis.labels.size,
                        for: "value-axis-label-size"
                    },
                    field: {
                        id: 'value-axis-label-size',
                        role: inputRoles.comboBox,
                        placeholder: messages.valueAxis.labels.sizePlaceholder,
                        action: "valueAxisLabelsFontSize"
                    }
                },
                {
                    label: {
                        text: messages.valueAxis.labels.color,
                        for: "value-axis-labels-color"
                    },
                    field: {
                        id: 'value-axis-labels-color',
                        role: inputRoles.colorPicker,
                        action: "valueAxisLabelsColor"
                    }
                },
                {
                    label: {
                        text: messages.valueAxis.labels.rotation.text,
                        for: "value-axis-labels-rotation"
                    },
                    field: {
                        id: 'value-axis-labels-rotation',
                        role: inputRoles.numericTextBox,
                        placeholder: messages.valueAxis.labels.rotation.auto,
                        action: "valueAxisLabelsRotation"
                    }
                },
            ]
        }
    ]);

    function createState(data, seriesType) {
        return ChartWizardCommon.createState(data, seriesType);
    }

    function createInitialStateInstance(data, seriesType, defaultState, stateObject) {
        var state = createState(
            data,
            (defaultState && defaultState.seriesType) || seriesType
        );

        if (stateObject) {
            state = deepExtend({}, state, stateObject);
        }

        return typeof (defaultState && defaultState.stack) !== "undefined"
            ? updateState(state, actionTypes.stacked, defaultState.stack)
            : state;
    }

    function getFormatPanels() {
        return [
            { ref: "chartarea", getData: chartAreaPanel },
            { ref: "title", getData: titlePanel },
            { ref: "series", getData: seriesPanel },
            { ref: "legend", getData: legendPanel },
            { ref: "categoryaxis", getData: categoryAxisPanel },
            { ref: "valueaxis", getData: valueAxisPanel },
        ];
    }
    function getNewState({ state, data, type, action, change }) {
        let newState = state;
        if (data && type) {
            newState = mergeStates(state, createState(data, type));
        }
        if (action && (change !== null && change !== undefined)) {
            newState = updateState(newState, action, change);
        }

        newState.transitions = false;

        return newState;
    }

    function prepareReorderedDataForGrid(grid, prevIndex, currentIndex) {
        const data = grid.options.dataSource;
        const item = data.splice(prevIndex, 1);
        data.splice(currentIndex, 0, item[0]);

        return data;
    }

    const templates = {
        chartTypesWrapper: () => `<div class="k-chart-types-wrapper"></div>`,
        iconTextItem: (iconWrapper, text, attribute) => `<div class="k-icon-text-wrapper" ${attribute ?? ""}>${iconWrapper}${kendo.htmlEncode(text)}</div>`,
        iconWrapper: (icon, size) => `<div class="k-icon-background-area" tabindex="0">${kendo.ui.icon({ icon: icon, size: size })}</div>`,
        formElement: () => `<form class="k-form"></form>`,
        formFieldSet: (legend, editorsBase) => `<fieldset class="k-form-fieldset">
            ${legend ? `<legend class="k-form-legend">${legend}</legend>` : ''}
            ${editorsBase}
        </fieldset>`,
        divContentWrapper: (ref) => `<div ref=${ref}></div>`,
        formFieldWrap: (field) => `<div class="k-form-field-wrap">${field}</div>`,
        formField: (content, className) => `<div class="k-form-field${className ? " " + className : ""}">
            ${content}
        </div>`,
        fieldContent: (label, field) => `
            ${label}
            ${field}`,
        label: (text, editorId, className) => `<label for="${editorId}" class="k-label${className ? " " + className : ""}">${text}</label>`,
        formGridLayout: (classes) => `<div class="k-form-layout k-d-grid${" " + classes}"></div>`,
        inputBaseElement: (field) => `<input ${field.id ? `id="${field.id}"` : ""} ${DATA_ROLE}=${field.role} ${DATA_ACTION}=${field.action} ${field.type ? `type="${field.type}"` : ""} ${field.placeholder ? `placeholder="${kendo.htmlEncode(field.placeholder)}"` : ""}/>`,
    };

    const legendPositions = (messages) => [
        { value: "bottom", text: messages.position.bottom },
        { value: "top", text: messages.position.top },
        { value: "left", text: messages.position.left },
        { value: "right", text: messages.position.right },
    ];

    const labelFormats = (messages) => [
        { value: "", text: messages.labels.labelFormat.text },
        { value: "n0", text: messages.labels.labelFormat.number },
        { value: "c0", text: messages.labels.labelFormat.currency },
        { value: "p0", text: messages.labels.labelFormat.percent },
    ];

    const titles = (messages) => [
        { value: "title", text: messages.format.title.chartTitle, },
        { value: "subtitle", text: messages.format.title.chartSubtitle },
    ];

    const commonOptions = {
        colorPicker: {
            fillMode: FILL_MODE_OUTLINE,
            view: "gradient",
            buttons: false,
            format: "rgb",
            opacity: true
        },
        comboBox: {
            filter: "contains",
            suggest: true,
            dataTextField: "text",
            dataValueField: "value",
        },
        numericTextBox: {
            step: 1,
            fillMode: FILL_MODE_OUTLINE,
        },
        dropDownList: {
            fillMode: FILL_MODE_OUTLINE,
            animation: false,
            dataTextField: "text",
            dataValueField: "value",
            index: 0,
        },
        tabStrip: {
            dataTextField: "name",
            dataContentField: "content",
        },
        grid: {
            columns: [
                {
                    id: 1,
                    field: 'reoreder',
                    draggable: true,
                    editable: () => false,
                    width: "40px"
                },
                { id: 2, field: 'name' },
                {
                    id: 3,
                    field: 'remove',
                    editable: () => false,
                    width: "40px",
                    template: () => `<button ${DATA_ACTION}="remove" tabindex="0"></button>`
                }
            ],
            pageable: false,
            scrollable: false,
            navigatable: false,
            editable: {
                confirmation: false,
                mode: "incell"
            },
            reorderable: {
                rows: true
            }
        }
    };

    const generateDataRows = (data, columns) => {
        let rows = [];

        data.forEach((item) => {
            const row = rows.find((rowInstance) => rowInstance.uid === item.uid);
            if (!row) {
                rows.push(item);
            } else {
                extend(row, item);
            }
        });

        const dataRows = rows.map((row) => ({
            dataItem: row,
            dataColumns: columns
        }));

        return getWizardDataFromDataRows(dataRows);
    };

    const ChartWizard = Widget.extend({
        init: function(element, options) {
            let that = this;
            options = options || {};
            that.options = deepExtend({}, that.options, options);

            Widget.fn.init.call(that, element, options);

            that._initial = true;
            that._dataSource();

            kendo.notify(that);
        },

        options: {
            name: "ChartWizard",
            dataSource: [],
            dataColumns: [],
            defaultState: {},
            state: {},
            window: {
                actions: ["Maximize", "Close"],
                resizable: true,
                visible: true,
                modal: true,
                scrollable: false,
                animation: false,
                width: 700,
                height: 550
            },
            exportOptions: {
                fileName: 'chart',
                pdf: {
                    margin: '1cm'
                },
                image: {
                    width: 800,
                    height: 600
                }
            },
            messages: {
                window: {
                    title: "Chart Preview"
                },
                export: "Export",
                exportPDF: "PDF File",
                exportSVG: "SVG File",
                exportPNG: "PNG File",
                tab: {
                    chart: "Chart",
                    data: "Data",
                    format: "Format"
                },
                chart: {
                    bar: {
                        expandText: "Bar Chart",
                        bar: "Bar",
                        stackedBar: "Stacked Bar",
                        hundredStackedBar: "100% Stacked Bar",
                    },
                    pie: {
                        expandText: "Pie Chart",
                        pie: "Pie",
                    },
                    column: {
                        expandText: "Column Chart",
                        column: "Column",
                        stackedColumn: "Stacked Column",
                        hundredStackedColumn: "100% Stacked Column",
                    },
                    line: {
                        expandText: "Line Chart",
                        line: "Line",
                        stackedLine: "Stacked Line",
                        hundredStackedLine: "100% Stacked Line",
                    },
                    scatter: {
                        expandText: "Scatter Chart",
                        scatter: "Scatter",
                    },
                },
                data: {
                    configuration: {
                        expandText: "Configuration",
                        series: {
                            title: "Series",
                            add: "Add",
                        },
                        valueAxis: "Value Axis",
                        categoryAxis: "Category Axis",
                        xAxis: "X Axis",
                    }
                },
                format: {
                    chartArea: {
                        expandText: "Chart Area",
                        margins: {
                            default: "Margins",
                            auto: "Auto",
                            left: "Left",
                            right: "Right",
                            top: "Top",
                            bottom: "Bottom",
                        },
                        background: {
                            default: "Background",
                            color: "Color",
                        },
                    },
                    title: {
                        expandText: "Title",
                        applyTo: "Apply to",
                        chartTitle: "Chart Title",
                        chartSubtitle: "Chart Subtitle",
                        label: "Title",
                        font: "Font",
                        fontPlaceholder: "(inherited font)",
                        size: "Size",
                        sizePlaceholder: "px",
                        color: "Color",
                    },
                    series: {
                        expandText: "Series",
                        applyTo: "Apply to",
                        allSeries: "All Series",
                        color: "Color",
                        showLabels: "Show Labels",
                    },
                    legend: {
                        expandText: "Legend",
                        showLegend: "Show Legend",
                        font: "Font",
                        fontPlaceholder: "(inherited font)",
                        size: "Size",
                        sizePlaceholder: "px",
                        color: "Color",
                        position: {
                            default: "Position",
                            top: "Top",
                            bottom: "Bottom",
                            left: "Left",
                            right: "Right",
                        }
                    },
                    categoryAxis: {
                        expandText: "Category Axis",
                        title: {
                            text: "Title",
                            placeholder: "Axis Title",
                            font: "Font",
                            fontPlaceholder: "(inherited font)",
                            size: "Size",
                            sizePlaceholder: "px",
                            color: "Color",
                        },
                        labels: {
                            text: "Labels",
                            font: "Font",
                            fontPlaceholder: "(inherited font)",
                            size: "Size",
                            sizePlaceholder: "px",
                            color: "Color",
                            rotation: {
                                text: "Rotation",
                                auto: "Auto",
                            },
                            reverseOrder: "Reverse Order",
                        }
                    },
                    valueAxis: {
                        expandText: "Value Axis",
                        title: {
                            text: "Title",
                            placeholder: "Axis Title",
                            font: "Font",
                            fontPlaceholder: "(inherited font)",
                            size: "Size",
                            sizePlaceholder: "px",
                            color: "Color",
                        },
                        labels: {
                            text: "Labels",
                            labelFormat: {
                                default: "Label Format",
                                text: "Text",
                                number: "Number",
                                currency: "Currency",
                                percent: "Percent",
                            },
                            font: "Font",
                            fontPlaceholder: "(inherited font)",
                            size: "Size",
                            sizePlaceholder: "px",
                            color: "Color",
                            rotation: {
                                text: "Rotation",
                                auto: "Auto",
                            },
                        },
                    },
                    xAxis: {
                        expandText: "X Axis",
                    },
                    yAxis: {
                        expandText: "Y Axis",
                    },
                }
            }
        },

        events: [
            RESIZING,
            DATA_BINGING,
            DATA_BOUND,
            RESIZE,
            CLICK,
            KEYDOWN,
            OPEN,
            CLOSE,
            CHANGE,
            EXPORT_PDF,
            EXPORT_SVG,
            EXPORT_IMAGE
        ],

        open: function() {
            const that = this;
            that.window.open();
        },

        close: function() {
            const that = this;
            that.window.close();
        },


        _attachEvents: function() {
            const that = this;

            that.splitter.bind(RESIZING, ({ pane }) => {
                kendo.resize(pane);
            });
            that.splitter.bind(RESIZE, (event) => {
                event.sender.element.find(DOT + "k-pane").each((_, pane) => {
                    kendo.resize(pane);
                });
            });

            that.window.bind(ACTIVATE, that._windowActivateHandler.bind(that));

            that.window.bind(RESIZE, that._windowResizeHandler.bind(that));

            that.window.bind(MAXIMIZE, that._windowActivateHandler.bind(that));

            that.window.bind(RESTORE, that._windowActivateHandler.bind(that));

            that.window.bind(CLOSE, function() {
                that.trigger(CLOSE);
            });

            that.window.bind(OPEN, function() {
                that.trigger(OPEN);
            });

            that.tabStrip.contentElements.on(CLICK + NS, DOT + "k-icon-text-wrapper", that._handleChartTypeClick.bind(that));
            that.tabStrip.contentElements.on(KEYDOWN + NS, DOT + "k-icon-text-wrapper", that._handleChartTypeKeydown.bind(that));
        },

        _dataSource: function() {
            const that = this,
                options = that.options;

            let dataSource = options.dataSource;

            if (that.dataSource && that._refreshHandler) {
                that.dataSource.unbind(CHANGE, that._refreshHandler);
            } else {
                that._refreshHandler = that._refresh.bind(that);
            }

            if (dataSource && dataSource instanceof DataSource) {
                that.dataSource = dataSource.bind(CHANGE, that._refreshHandler);
            } else {
                dataSource = Array.isArray(dataSource) ? { data: dataSource } : dataSource;

                that.dataSource = DataSource.create(dataSource)
                    .bind(CHANGE, that._refreshHandler);

            }

            if (that.dataSource.data().length) {
                that._refresh();
            } else if (!that.dataSource._requestInProgress) {
                that.dataSource.fetch();
            }
        },

        _refreshContent: function() {
            const that = this;
            const options = that.options;
            const chartState = that._getChartStateInstance(options);
            const previewHeader = that.previewPane.find(DOT + cssClasses.previewPaneHeader);

            that.window.setOptions(
                {
                    title: kendo.htmlEncode(options.messages.window.title),
                    ...options.window
                });

            that._oldType = null;

            that._setChartContainerHeight();
            that.chartState = extend(that.chartState, chartState);
            that.chart.setOptions(that.chartState);

            that._detachEvents();

            kendo.destroy(previewHeader);
            previewHeader.empty();
            that._createExportButton(previewHeader);

            kendo.destroy(that.propertyPane);
            that.propertyPane.empty();
            that._seriesGrid = null;
            that._seriesValueAxisDDL = null;

            that._initPropertyPane();

            that._attachEvents();

            if (!that.window.wrapper.is(":visible") && options.window.visible) {
                that.window.open();
            }

            if (!options.position) {
                that.window.center();
            }
        },

        _refresh: function(e) {
            const that = this;
            let data = Array.from(that.dataSource.data());

            that.trigger(DATA_BINGING, { data: data });

            data = that._data = that._getWizardData(data);

            if (that._initial) {
                that._initWindow();
                that._initSplitter();
                that._initPreviewPane();
                that._initPropertyPane();

                that._attachEvents();

                that._initial = false;
            } else {
                that._refreshContent();
            }

            that.trigger(DATA_BOUND, { data: data });
        },

        _getWizardData: function(data) {
            const that = this;
            let wizardData = data;

            const dataColumns = that.options.dataColumns ? that.options.dataColumns.map((column) => {
                if (typeof column === "string") {
                    return { field: column };
                }

                return column;
            }) : [];

            if (data.length && data[0].dataItem && data[0].dataColumns) {
                wizardData = getWizardDataFromDataRows(data);
            } else if (data.length && !Array.isArray(data[0]) && dataColumns.length) {
                wizardData = generateDataRows(data, dataColumns);
            }

            return wizardData;
        },

        setDataSource: function(dataSource) {
            const that = this;
            const options = that.options;

            if (dataSource) {
                options.dataSource = dataSource;
                that._dataSource();
            }
        },

        setDataColumns: function(dataColumns) {
            const that = this;
            const options = that.options;

            if (dataColumns.length) {
                options.dataColumns = dataColumns;
            }
        },

        _detachEvents: function() {
            const that = this;

            that.splitter.unbind(RESIZING);
            that.splitter.unbind(RESIZE);
            that.window.unbind(ACTIVATE);
            that.window.unbind(RESIZE);
            that.window.unbind(CLOSE);
            that.window.unbind(OPEN);
            that.window.unbind(MAXIMIZE);
            that.window.unbind(RESTORE);
            that.tabStrip.contentElements.off(CLICK + NS);
            that.tabStrip.contentElements.off(KEYDOWN + NS);
        },

        _setChartContainerHeight: function() {
            const that = this;
            that.previewPane.find(DOT + cssClasses.previewPaneContent).css({
                height: that.previewPane.height() - 50,
            });
        },

        _windowActivateHandler: function() {
            const that = this;
            that._setChartContainerHeight();
            that.chart.resize();
        },

        _windowResizeHandler: function() {
            const that = this;
            that._setChartContainerHeight();
        },

        _handleChartTypeKeydown: function(e) {
            if (e.keyCode === keys.ENTER) {
                this._handleChartTypeClick(e);
            }
        },

        _handleChartTypeClick: function(e) {
            const that = this;
            const item = $(e.currentTarget);
            const newType = that._getRefAttributeValue(item.closest(DOT + "k-expander-content")[0])[0];
            const chartType = item.attr(DATA_CHART_TYPE_ATTR);
            const chartState = that.chartState;
            let change = null;
            let action = null;

            if (chartType.includes(HUNDRED_STACKED)) {
                action = actionTypes.stacked;
                change = { type: "100%" };
            } else if (chartType.includes(STACKED)) {
                action = actionTypes.stacked;
                change = { type: "normal" };
            }

            that._executeAction(
                change, { element: item }, chartState.data, newType, action
            );

            item.closest(DOT + "k-tabstrip-content").find(DOT + SELECTED_STATE).removeClass(SELECTED_STATE);
            item.addClass(SELECTED_STATE);

            that._toggleFieldsForSeriesPanel(newType);
            that._toggleFormatPanels();

            that._refreshEditors();
        },

        _refreshEditors: function() {
            const that = this;
            const editors = that.propertyPane.find(DATA_ACTION_SELECTOR + ":not('.k-button')");

            editors.each((_, editor) => {
                const element = $(editor);
                const kendoElement = element.data("handler");
                const id = element.attr("id");
                const panel = $(editor).closest(`[${REF}]`);
                const elementRole = element.attr(DATA_ROLE);

                const panelRef = that._getRefAttributeValue(panel[0])[0];

                if (kendoElement && kendoElement.setOptions) {
                    const changed = that._getOptionsForPanel(id)[panelRef][elementRole];

                    if (changed && changed.options) {
                        if (elementRole === inputRoles.checkBox) {
                            kendoElement.check(changed.options.checked);
                        } else if (panelRef === "series") {
                            kendoElement.setOptions(changed.options);
                            if (changed.options && changed.options.enable) {
                                kendoElement.enable(changed.options.enable);
                            }
                        } else {
                            let val = changed.options.value;
                            if (val && typeof val !== "string" && val.value) {
                                val = val.value;
                            }

                            kendoElement.value(val);
                        }
                    }

                    if (elementRole === inputRoles.comboBox) {
                        const state = that._getFontState(element);
                        if (state && state.value) {
                            kendoElement.value(state.value);
                        }
                    }
                }
            });

            if (that._seriesGrid) {
                that._seriesGrid.setDataSource(that.chartState.series);
            }
        },

        _initWindow: function() {
            const that = this;
            const messages = that.options.messages;
            const options = that.options.window;
            const centered = !options.position;

            that.window = that.element.kendoWindow({
                title: kendo.htmlEncode(messages.window.title),
                ...options,
            }).data("kendoWindow");

            if (centered) {
                that.window.center();
            }

            that.window.element.attr("tabindex", "-1");
            that.wrapper = that.window.wrapper.addClass(cssClasses.chartWizard);
        },

        _initSplitter: function() {
            const that = this;

            that.splitter = $("<div>").kendoSplitter({
                orientation: "horizontal",
            }).data("kendoSplitter");

            that.previewPane = that.splitter.append({ scrollable: false });
            that.propertyPane = that.splitter.append({ size: "300px", collapsible: true });

            that.splitter.wrapper.addClass(cssClasses.splitter);
            that.window.content(that.splitter.wrapper);
        },

        _initPreviewPane: function() {
            const that = this;
            const options = that.options;
            that.previewPane.addClass(cssClasses.previewPane);

            const previewHeader = $("<div></div>").addClass(cssClasses.previewPaneHeader);
            const previewContent = $("<div></div>").addClass(cssClasses.previewPaneContent);

            if (that.options.window.visible) {
                previewContent.css({
                    height: that.previewPane.height() - 50,
                });
            }

            const chart = $("<div id='chart'>");

            that._createExportButton(previewHeader);
            previewContent.append(chart);

            that.previewPane.append(previewHeader.add(previewContent));
            that._initChart(chart, options);
        },

        _getChartStateInstance: function(options, skipDefault) {
            const that = this;
            const defaultState = skipDefault ? {} : options.defaultState;
            const hasState = Object.keys(options.state).length;
            const data = hasState ? options.state.data : that._data;
            const seriesType = hasState ? !skipDefault && options.state.seriesType || INITIAL_TYPE : INITIAL_TYPE;

            return createInitialStateInstance(data || [], seriesType, defaultState, options.state);
        },

        _initChart: function(element, options) {
            const that = this;
            const isPie = (options.defaultState && options.defaultState.seriesType === 'pie') || (options.state && options.state.seriesType === 'pie');
            let initialState = that._getChartStateInstance(options, isPie);

            const chartArea = initialState.area;
            initialState.chartArea = chartArea;
            initialState.transitions = false;

            that.chartState = initialState;

            that.chart = element.kendoChart(initialState).data("kendoChart");
            that.chart.wrapper.css({ width: '100%', height: '100%' });

            if (isPie) {
                const pieState = that._getChartStateInstance(options);
                that.chartState = extend(that.chartState, pieState);
                that.chart.setOptions(that.chartState);
            }


            if (!that._initialState) {
                that._initialState = that.chart.options;
            }
        },

        _createExportButton: function(previewHeader) {
            const that = this;
            that._configureExportButton(exportButtonOptions);
            const dropDownButton = $(`<button>${kendo.htmlEncode(that.options.messages.export)}</button>`).kendoDropDownButton(exportButtonOptions);
            previewHeader.append(dropDownButton);
        },

        _configureExportButton: function(options) {
            const that = this;
            const messages = that.options.messages;
            const idSeparator = "-";

            const exportHandlers = {
                "export-pdf": function() {
                    if (!that.trigger(EXPORT_PDF, { chart: that.chart, exportOptions: that.options.exportOptions })) {
                        that.chart.exportPDF(that.options.exportOptions.pdf).done(function(data) {
                            kendo.saveAs({
                                dataURI: data,
                                fileName: that.options.exportOptions.fileName
                            });
                        });
                    }
                },
                "export-svg": function() {
                    if (!that.trigger(EXPORT_SVG, { chart: that.chart, exportOptions: that.options.exportOptions })) {
                        that.chart.exportSVG(that.options.exportOptions.fileName).done(function(data) {
                            kendo.saveAs({
                                dataURI: data,
                                fileName: that.options.exportOptions.fileName
                            });
                        });
                    }
                },
                "export-png": function() {
                    if (!that.trigger(EXPORT_IMAGE, { chart: that.chart, exportOptions: that.options.exportOptions })) {
                        that.chart.exportImage(that.options.exportOptions.image).done(function(data) {
                            kendo.saveAs({
                                dataURI: data,
                                fileName: that.options.exportOptions.fileName
                            });
                        });
                    }
                }
            };

            options.items.forEach(item => {
                const textParts = item.id.split(idSeparator);
                const text = textParts[0] + textParts[1].toUpperCase();
                item.text = kendo.htmlEncode(messages[text]);
                item.click = exportHandlers[item.id];
            });
        },

        _preventLabelPointerEvents: function() {
            const that = this;

            that.propertyPane.find(".k-label").addClass("k-pointer-events-none");

        },

        _initPropertyPane: function() {
            const that = this;
            const tabStripOptions = commonOptions.tabStrip;
            tabStripOptions.dataSource = Object.values(that.options.messages.tab).map(tab => ({ name: kendo.htmlEncode(tab) }));

            that._createTabsContent(tabStripOptions.dataSource);
            that.tabStrip = $("<div>").kendoTabStrip(
                {
                    ...tabStripOptions,
                    activate: function(e) {
                        const contentElement = $(e.contentElement);

                        if (contentElement.find(`[${REF_SELECTOR}'data']`).length) {
                            const content = $(e.contentElement).find("#category-axis").parent().siblings(DOT + "k-form-legend");

                            if (content.length && that.chart.options.seriesType === "scatter") {
                                content.text(kendo.htmlEncode(that.options.messages.data.configuration.xAxis));
                            } else if (content.length) {
                                content.text(kendo.htmlEncode(that.options.messages.data.configuration.categoryAxis));
                            }
                        }

                        if (that._seriesGrid && that._seriesGrid.wrapper.is(":visible")) {
                            that._seriesGrid._draggableRows();
                            that._seriesGrid._reorderableRows();
                        }
                    }
                }).data("kendoTabStrip");

            that.propertyPane.addClass(cssClasses.propertyPane);
            that.propertyPane.append(that.tabStrip.wrapper);

            that._expansionPanels();
            that._prepareDataTab();
            that._prepareFormatTab();

            that._initStaticEditors();

            that.tabStrip.activateTab(that.tabStrip.tabGroup.children().first());

            if (that.options.window.visible) {
                that.chart.resize();
            }
        },

        _createTabsContent: function(tabs) {
            const that = this;
            tabs.forEach(tab => {
                tab.content = that._createExpansionPanelContent(tab.name) ?? "";
            });
        },

        _createExpansionPanelContent: function(tab) {
            const that = this;
            let content = "";
            const tabs = that.options.messages.tab;

            if (tab === tabs[CHART]) {
                const fields = Object.keys(expansionPanelForChart);

                fields.forEach(chartType => {
                    content += that._createChartTypesPanel(expansionPanelForChart[chartType].content, `${chartType}-chart-panel`, chartType);
                });

            } else if (tab === tabs[DATA]) {
                const data = dataTabPanel(that.options.messages.data);

                content += that._createFormPanel(data, "configuration-data-panel");

            } else if (tab === tabs[FORMAT]) {
                const formatPanels = getFormatPanels();

                formatPanels.forEach(panel => {
                    const panelData = panel.getData(that.options.messages.format);
                    content += that._createFormPanel(panelData, `${panel.ref}-format-panel`);
                });

            }

            return content;
        },

        _createChartTypesPanel: function(data, ref, mainChartType) {
            const that = this;
            const messages = that.options.messages.chart;
            const content = $(templates.divContentWrapper(ref));
            const chartTypesWrapper = $(templates.chartTypesWrapper());
            const chartTypes = Object.keys(data);
            chartTypes.forEach(chartType => {
                const text = kendo.htmlEncode(messages[mainChartType][chartType]);
                const attribute = `${DATA_CHART_TYPE_ATTR}=${chartType.toLowerCase()}`;
                chartTypesWrapper.append(templates.iconTextItem(templates.iconWrapper(data[chartType], ICON_SIZE), text, attribute));
            });

            content.append(chartTypesWrapper);

            return content[0].outerHTML;
        },

        _createFormPanel: function(data, ref) {
            const content = $(templates.divContentWrapper(ref));
            const form = $(templates.formElement());

            data.forEach(fields => {
                const legend = fields.legend;
                const layoutClass = fields.layoutClass;
                const editors = fields.editors;
                let target = "";

                if (editors instanceof Array) {
                    let formFields = "";

                    editors.forEach(editor => {
                        const nowrap = editor.nowrap;
                        const label = editor.label ? templates.label(kendo.htmlEncode(editor.label.text), editor.label.for, !nowrap && "k-form-label") : "";
                        const className = editor.className;
                        let field;

                        if (editor.field && editor.field.custom) {
                            field = editor.field.custom;
                        } else if (editor.field) {
                            field = templates.inputBaseElement(editor.field);
                        } else {
                            field = "";
                        }

                        if (nowrap) {
                            formFields += templates.fieldContent(label, field);
                        } else {
                            formFields += templates.formField(
                                templates.fieldContent(label, templates.formFieldWrap(field)),
                                className
                            );
                        }
                    });

                    if (layoutClass) {
                        const formGridLayout = $(templates.formGridLayout(layoutClass));
                        formGridLayout.append(formFields);

                        target = formGridLayout[0].outerHTML;
                    } else {
                        target = formFields;
                    }
                } else if (editors && editors.custom) {
                    target = editors.custom;
                } else {
                    target = templates.inputBaseElement(editors);
                }

                if (legend) {
                    form.append(templates.formFieldSet(kendo.htmlEncode(legend), target));
                } else {
                    form.append(target);
                }
            });

            content.append(form);

            return content[0].outerHTML;
        },

        _ensureSizeValue: function(fontSize, callback) {
            const numberRegExp = /^\d+$/;
            const size = fontSize.toString();

            let item = fontSizes.find((fs) => fs.text === size);

            if (!item) {

                if (!numberRegExp.test(size) || isNaN(parseInt(size, 10))) {
                    return false;
                }

                item = { text: size, value: size + "px" };
                fontSizes.push(item);
                fontSizes.sort((a, b) => parseInt(a.text, 10) - parseInt(b.text, 10));
                callback(fontSizes);
            }


            return true;
        },

        _executeAction: function(change, from, data, type, dataAction) {
            const that = this;
            const action = from.element.data("action");
            const chartState = that.chartState;

            const state = {
                state: chartState,
                action: dataAction ?? actionTypes[action],
                change: change
            };

            if (data) {
                state.data = data;
                state.type = type ?? that.chartState.seriesType;
            }

            const newState = getNewState(state);

            newState.chartArea = newState.area;
            that.chart.setOptions(newState);
            that.chartState = newState;

            return newState;
        },

        _seriesDropDownChange: function(e) {
            const that = this;
            that._seriesChange = true;
            e.preventChange = true;

            const newState = that._handlePropertyChange(e);
            delete that._seriesChange;

            that._seriesGrid && that._seriesGrid.setDataSource(newState.series);
        },

        _prepareDataTab: function() {
            const that = this;

            const form = that.element.find(`[${REF_SELECTOR}'data'] > form`);
            const gridField = form.find(".k-grid").closest(".k-form-fieldset");
            const valueAxisField = form.find("#value-axis").closest(".k-form-fieldset");


            that._dynamicConfigurationFields = {
                grid: gridField.clone(),
                valueAxis: valueAxisField.clone(),
                container: form,
            };

            gridField.remove();
            valueAxisField.remove();
        },

        _resetFontValue: function(isInitial, field) {
            if (isInitial && field) {
                field.font = "";
                return true;
            }

            return false;
        },

        _getFontField: function(activeField, activeFieldInitial, isInitial) {
            const that = this;
            let newValue;

            if (isInitial) {
                newValue = activeFieldInitial && activeFieldInitial.font;
            } else {
                newValue = activeField && activeField.font;
            }

            that._valueReset = that._resetFontValue(isInitial, activeField);

            return newValue;
        },

        _getFontValue: function(fontState, field, type, fromSibling) {
            const that = this;
            let newValue;
            const isInitial = type === "initial";
            const activeFieldInitial = that._initialState[fontState.active];
            const activeField = that.chartState[fontState.active];

            if (Array.isArray(activeField)) {
                newValue = that._getFontField(activeField[0], activeFieldInitial, isInitial, fromSibling);
            } else {
                newValue = that._getFontField(activeField, activeFieldInitial, isInitial, fromSibling);
            }

            if (!newValue && fontState.subfield) {
                const sub = fontState.subfield.toLowerCase();

                if (Array.isArray(activeField)) {
                    newValue = that._getFontField(activeField[0][sub], activeFieldInitial[sub], isInitial, fromSibling);
                } else {
                    newValue = that._getFontField(activeField[sub], activeFieldInitial[sub], isInitial, fromSibling);
                }
            }

            if (newValue) {
                newValue = that._parseFont(newValue)[field];
            }

            return newValue;
        },

        _createFontCombobox: function(element) {
            const that = this;
            const state = that._getFontState(element);

            const comboBox = element.kendoComboBox({
                dataSource: state.dataSource,
                value: state.value,
                change: function(e) {
                    const fontState = e.sender._fontState;
                    const fontNameChanged = fontState.type === 'name';
                    const applySubfieldToSelector = fontState.subfield && state.active !== 'legend';
                    const selector = `${fontState.active}${fontState.subfield && applySubfieldToSelector ? `${fontState.subfield}` : ""}Font`;
                    let field = fontNameChanged ? 'Size' : 'Name';
                    let value = e.sender.value();

                    const sibling = e.sender.wrapper
                        .closest(".k-form")
                        .find(`[${DATA_ACTION}*=${selector + field}]`).data("kendoComboBox");

                    field = field.toLowerCase();

                    const initial = !value;

                    if (initial) {
                        that._getFontValue(fontState, fontState.type, "initial");

                        if (that._valueReset) {
                            e.sender.value("");
                            sibling.value("");
                            return that.chart.setOptions(that.chartState);
                        }

                    } else {
                        if (!fontNameChanged) {
                            value = value.replace("px", '');
                        }

                        if (value !== '' && !fontNameChanged && !that._ensureSizeValue(value, e.sender.setDataSource.bind(e.sender))) {
                            return;
                        }

                        that._handlePropertyChange(e);
                    }

                    if (!initial && !sibling.value()) {
                        let newValue = that._getFontValue(fontState, field);

                        let change;
                        if (!fontNameChanged) {
                            newValue = newValue.replace(/^['"]*([a-zA-Z0-9\s]+)['"]*$/g, '$1');
                            change = fontNames.find((item) => item.value.includes(newValue));
                        } else if (that._ensureSizeValue(newValue, sibling.setDataSource.bind(sibling))) {
                            change = fontSizes.find((item) => item.text === newValue.toString());
                        }

                        if (change && change.value) {
                            sibling.value(change.text);
                            that._handlePropertyChange({ sender: sibling, preventChange: true }, change.value);
                        }
                    }
                },
                ...commonOptions.comboBox,
            }).data("kendoComboBox");

            comboBox._fontState = state;

            return comboBox;
        },

        _createDropDownList: function(element, options, handler) {
            const that = this;
            const dropdownlist = $(element).kendoDropDownList({
                ...commonOptions.dropDownList,
                ...options,
                change: handler || function(e) {
                    that._handlePropertyChange(e);
                },
            }).data("kendoDropDownList");

            return dropdownlist;
        },

        _createSwitch: function(element, options, handler) {
            const that = this;
            const switchElement = element.kendoSwitch({
                ...options,
                change: handler || function(e) {
                    that._handlePropertyChange(e);
                }
            }).data("kendoSwitch");

            return switchElement;
        },

        _createCheckBox: function(element, options, handler) {
            const that = this;
            const checkbox = element.kendoCheckBox({
                ...options,
                change: handler || function(e) {
                    that._handlePropertyChange(e);
                }
            }).data("kendoCheckBox");

            return checkbox;
        },

        _createColorPicker: function(element, options, handler) {
            const that = this;
            const config = options ?? {};
            const enabled = config.enable !== undefined ? config.enable : true;
            delete config.enabled;

            const colorPicker = element.kendoColorPicker({
                ...commonOptions.colorPicker,
                ...config,
                change: handler || function(e) {
                    that._handlePropertyChange(e);
                },
                select: handler || function(e) {
                    that._handlePropertyChange(e, e.value);
                },
            }).data("kendoColorPicker");

            colorPicker.enable(enabled);

            return colorPicker;
        },

        _createNumericTextBox: function(element, options, handler) {
            const that = this;
            const numeric = element.kendoNumericTextBox(
                {
                    ...commonOptions.numericTextBox,
                    ...options,
                    change: handler || function(e) {
                        that._handlePropertyChange(e);
                    },
                    spin: handler || function(e) {
                        that._handlePropertyChange(e);
                    },
                }
            ).data("kendoNumericTextBox");

            return numeric;
        },

        _createTextBox: function(element, options) {
            const that = this;
            const textbox = element.kendoTextBox({
                ...options
            });

            textbox.on("input" + NS, function(e) {
                const sender = {
                    element: $(e.target),
                };
                that._handlePropertyChange({ sender }, $(e.target).val());
            });
            return textbox.data("kendoTextBox");
        },

        _getFieldValue: function(action) {
            const parts = action.split(REG_EXP_CAPITAL);
            const fontIndex = parts.indexOf("Font");
            const path = parts.slice(0, fontIndex);
            const type = parts[fontIndex + 1].toLowerCase();

            let field;
            let subfield;

            if (path.length === 1) {
                field = path[0];
            } else if (path.length > 1) {
                subfield = path.pop();
                field = path.join('');
            }

            return {
                type,
                field,
                subfield: field === "legend" ? "Labels" : subfield,
            };

        },

        _getFontState: function(element) {
            const that = this;
            const state = that.chartState;
            const action = element.data("action");
            const parts = that._getFieldValue(action);
            const field = parts.field;
            const subfield = parts.subfield && parts.subfield.toLowerCase();

            let font = state[field] && state[field].font;

            if (!font && subfield) {
                font = state[field] && state[field][subfield] && state[field][subfield].font;
            }

            let value = null;
            let dataSource = [];

            if (parts.type === 'name') {
                dataSource = fontNames;
                value = (font && dataSource.find((f) => f.value === that._parseFont(font).name)) || null;
            } else {
                dataSource = fontSizes;
                value = (font && dataSource.find((f) => f.value === that._parseFont(font).size)) || null;
            }

            return {
                dataSource,
                value,
                type: parts.type,
                active: field,
                subfield: parts.subfield
            };
        },

        _parseFont: function(font) {
            const el = $("<span></span>");
            el.css("font", font);

            return {
                fontWeight: el.css('fontWeight'),
                size: parseInt(parseFloat(el.css('fontSize'))),
                name: el.css('fontFamily')
            };
        },

        _addSeries: function(e) {
            const that = this;
            const grid = that._seriesGrid;
            const item = that._removedSeries.pop();
            const data = grid.options.dataSource;
            data.push(item);

            grid.setDataSource(data);
        },

        _removeSeries: function(e) {
            const that = this;
            const target = e.target.closest("tr");

            that._seriesGrid.removeRow(target);

        },

        _gridDataBoundHandler: function(grid) {
            const that = this;
            const clickHandlers = {
                add: this._addSeries.bind(that),
                remove: this._removeSeries.bind(that)
            };

            grid.wrapper.find(".k-grid-header").remove();

            grid.wrapper.find(DATA_ACTION_SELECTOR).each((_, button) => {
                const buttonElement = $(button);

                if (buttonElement.data("kendoButton")) {
                    buttonElement.data("kendoButton").destroy();
                    buttonElement.removeAttr("disabled");
                }

                const action = buttonElement.data("action");
                const enableRemove = !!(action === 'remove' && grid._data.length > 1);
                const enableAdd = !!(action === 'add' && that._removedSeries && that._removedSeries.length > 0);

                const enabled = enableRemove || enableAdd;

                buttonElement.kendoButton({
                    icon: action === 'add' ? "plus" : "trash",
                    enabled: enabled,
                    fillMode: FILL_MODE_FLAT,
                    click: clickHandlers[action]
                });
            });
        },

        _initGrid: function(element) {
            const that = this;
            const messages = that.options.messages.data.configuration.series;

            const grid = element.kendoGrid({
                ...commonOptions.grid,
                toolbar: [{ template: () => `<button ${DATA_ACTION}='add' tabindex="0">${kendo.htmlEncode(messages.add)}</button>` }],
                dataBound: function(e) {
                    const grid = e.sender;
                    that._preventChange = true;

                    if (that._seriesReordering) {
                        if (grid._data.length === that._reorderedData.length) {
                            that._seriesReordering = false;
                            that._executeAction(that._reorderedData, grid);
                            delete that._reorderedData;

                            that.trigger(CHANGE, { state: that.chartState });
                        }
                    } else {
                        that._executeAction(grid.options.dataSource, grid);
                        that.trigger(CHANGE, { state: that.chartState });
                    }

                    that._preventChange = false;

                    that._gridDataBoundHandler(grid);
                },
                cellClose: function(e) {
                    if (e.type === 'save') {
                        that._handleSeriesEdit(e);
                    }
                },
                remove: function(e) {
                    that._handleSeriesRemove(e);
                },
                rowReorder: function(e) {
                    that._seriesReordering = true;
                    that._reorderedData = prepareReorderedDataForGrid(e.sender, e.oldIndex, e.newIndex - 1);
                },
                dataSource: that.chartState.series,
            }).data("kendoGrid");

            that._seriesGrid = grid;
        },

        _handleSeriesEdit: function(e) {
            const that = this;
            const grid = e.sender;
            const data = grid.options.dataSource;
            const itemIndex = grid.dataSource.indexOf(e.model);

            const editedField = data[itemIndex];

            const newField = { ...editedField, name: e.model.name };

            data.splice(itemIndex, 1, newField);

            that._executeAction(data, grid);
            that.trigger(CHANGE, { state: that.chartState });
        },

        _handleSeriesRemove: function(e) {
            const that = this;
            const data = e.sender.options.dataSource;
            let itemIndex;
            const item = data.find((item, index) => {
                itemIndex = index;
                return item.name === e.model.name;
            });

            data.splice(itemIndex, 1);

            if (that._removedSeries) {
                that._removedSeries.unshift(item);
            } else {
                that._removedSeries = [item];
            }
        },


        _toggleFieldsForSeriesPanel: function(type) {
            const that = this;
            const gridField = $(that._dynamicConfigurationFields.grid[0].outerHTML);
            const valueAxisField = $(that._dynamicConfigurationFields.valueAxis[0].outerHTML);
            const container = that._dynamicConfigurationFields.container;
            const typeIsCategorical = isCategorical(type);

            if (!gridField || !valueAxisField) {
                return;
            }

            if ((that._oldType === type) || (isCategorical(that._oldType) && typeIsCategorical)) {
                return;
            }

            if (typeIsCategorical) {
                that._seriesValueAxisDDL && that._seriesValueAxisDDL.wrapper && that._seriesValueAxisDDL.wrapper.data && that._seriesValueAxisDDL.destroy();
                delete that._seriesValueAxisDDL;
                container.find("[data-action=valueAxisY]").closest(".k-form-fieldset").remove();

                container.append(gridField);
                that._initGrid(gridField.find(".k-grid"));
            } else {
                that._seriesGrid && that._seriesGrid.wrapper && that._seriesGrid.wrapper.data && that._seriesGrid.destroy();
                delete that._seriesGrid;
                container.find(".k-grid").closest(".k-form-fieldset").remove();

                container.append(valueAxisField);
                const valueAxisDDL = valueAxisField.find(`[${DATA_ROLE_SELECTOR}${inputRoles.dropDownList}]`);
                that._seriesValueAxisDDL = that._createDropDownList(
                    valueAxisDDL, {
                    value: that.chartState.valueField || '',
                    dataSource: that.chartState.columns.map((column) => ({ text: column, value: column }))
                },
                    that._seriesDropDownChange.bind(that)
                );
            }

            that._oldType = type;
            that._preventLabelPointerEvents();
        },

        _initStaticEditors: function() {
            const that = this;
            const panelsForms = that.element.find(".k-form");

            panelsForms.each((_, form) => {
                const wrapper = $(form);
                const panel = wrapper.closest(`[${REF}]`);
                const editors = $(panel).find(`[${DATA_ROLE}]`);
                const panelRef = that._getRefAttributeValue(panel[0])[0];

                that._initEditors(editors, panelRef);
            });

            that._toggleFieldsForSeriesPanel(that.chartState.seriesType);
            that._toggleFormatPanels();
        },

        _initEditors: function(editors, panelRef) {
            const that = this;
            editors.each((_, editor) => {
                const element = $(editor);
                const id = element.attr("id");
                const editorType = element.data("role");
                const optionsForPanel = that._getOptionsForPanel(id)[panelRef];
                const editorOptions = optionsForPanel[editorType];

                const options = editorOptions ? editorOptions.options : editorOptions;
                const handler = editorOptions ? editorOptions.handler : editorOptions;

                if (editorType === "combobox") {
                    that._createFontCombobox(element, options, handler);
                } else if (editorType === "dropdownlist") {
                    that._createDropDownList(element, options, handler);
                } else if (editorType === "colorpicker") {
                    that._createColorPicker(element, options, handler);
                } else if (editorType === "numerictextbox") {
                    that._createNumericTextBox(element, options, handler);
                } else if (editorType === "switch") {
                    that._createSwitch(element, options, handler);
                } else if (editorType === "checkbox") {
                    that._createCheckBox(element, options, handler);
                } else if (editorType === "textbox") {
                    that._createTextBox(element, options, handler);
                }
            });
        },

        _prepareFormatTab: function() {
            const that = this;

            const categoryAxisField = that.element.find(`[${REF_SELECTOR}'categoryaxis'] > form`);
            const valueAxisField = that.element.find(`[${REF_SELECTOR}'valueaxis'] > form`);

            that._dynamicFormatPanels = {
                categoryAxis: categoryAxisField,
                valueAxis: valueAxisField,
                container: categoryAxisField.closest(DOT + cssClasses.expansionPanelWrapper).parent()
            };

            that._initialAxesPanelsContent = {
                categoryAxis: categoryAxisField.clone(),
                valueAxis: valueAxisField.clone()
            };

            categoryAxisField.closest(DOT + cssClasses.expansionPanelWrapper).remove();
            valueAxisField.closest(DOT + cssClasses.expansionPanelWrapper).remove();
        },

        _toggleFormatPanels: function() {
            const that = this;
            const messages = that.options.messages.format;
            const chartType = that.chartState.seriesType;

            const categoryAxisField = that._dynamicFormatPanels.categoryAxis;
            const valueAxisField = that._dynamicFormatPanels.valueAxis;
            const container = that._dynamicFormatPanels.container;

            const initialContentCategory = that._initialAxesPanelsContent.categoryAxis.html();
            const initialContentValue = that._initialAxesPanelsContent.valueAxis.html();

            categoryAxisField
                .empty()
                .append(initialContentCategory);
            valueAxisField
                .empty()
                .append(initialContentValue);

            if (chartType !== "pie") {
                let messagesCategory = kendo.htmlEncode(messages.categoryAxis.expandText);
                let messagesValue = kendo.htmlEncode(messages.valueAxis.expandText);

                if (chartType === "scatter") {
                    messagesCategory = kendo.htmlEncode(messages.xAxis.expandText);
                    messagesValue = kendo.htmlEncode(messages.yAxis.expandText);
                }

                if (container.find($(valueAxisField)).length === 0) {
                    const categoryWrapper = $(templates.divContentWrapper("categoryaxis-format-panel")).append(categoryAxisField);
                    const valueWrapper = $(templates.divContentWrapper("valueaxis-format-panel")).append(valueAxisField);

                    container.append(categoryWrapper);
                    container.append(valueWrapper);

                    $(categoryWrapper).kendoExpansionPanel({ title: messagesCategory });
                    $(valueWrapper).kendoExpansionPanel({ title: messagesValue });
                } else {
                    categoryAxisField
                        .closest(DOT + cssClasses.expansionPanelWrapper)
                        .find(".k-expander-title")
                        .text(messagesCategory);
                    valueAxisField
                        .closest(DOT + cssClasses.expansionPanelWrapper)
                        .find(".k-expander-title")
                        .text(messagesValue);
                }


                if (categoryAxisField.closest(DOT + cssClasses.expansionPanelWrapper).length) {
                    categoryAxisField.closest(`[${REF}]`)
                        .add(valueAxisField.closest(`[${REF}]`))
                        .each((_, expander) => {
                            const editors = $(expander).find(`[${DATA_ROLE}]`);
                            const panelRef = that._getRefAttributeValue(expander)[0];
                            that._initEditors(editors, panelRef);
                        });
                }
            } else if (container.find(valueAxisField).length) {
                const categoryAxisExpander = categoryAxisField.closest(DOT + cssClasses.expansionPanelWrapper);
                const valueAxisFieldExpander = valueAxisField.closest(DOT + cssClasses.expansionPanelWrapper);

                kendo.destroy(categoryAxisExpander);
                kendo.destroy(valueAxisFieldExpander);

                categoryAxisExpander.remove();
                valueAxisFieldExpander.remove();
            }

            that._preventLabelPointerEvents();
        },

        _handlePropertyChange: function(e, value) {
            const that = this;
            const change = (value || value === "") ? value : e.sender.value();
            const newState = that._executeAction(change, e.sender);

            !e.preventChange && that.trigger(CHANGE, { state: that.chartState });

            return newState;
        },


        _getOptionsForPanel: function(id) {
            const that = this;
            const chartState = that.chartState;
            const messages = that.options.messages;
            const positionsData = legendPositions(messages.format.legend).map((position) => ({ ...position, text: kendo.htmlEncode(position.text) }));
            const formats = labelFormats(messages.format.valueAxis).map((format) => ({ ...format, text: kendo.htmlEncode(format.text) }));
            const titleValues = titles(messages).map((title) => ({ ...title, text: kendo.htmlEncode(title.text) }));
            const fieldForAxes = id.includes("title") ? "title" : "labels";
            const isCategoryDropDown = id.includes("category");
            return {
                configuration: {
                    dropdownlist: {
                        options: {
                            value: chartState[isCategoryDropDown ? "categoryField" : "valueField"],
                            dataSource: chartState.columns.map((column) => ({ text: column, value: column }))
                        },
                        handler: that._seriesDropDownChange.bind(that)
                    }
                },
                chartarea: {
                    numerictextbox: {
                        options: {
                            value: chartState.chartArea && chartState.chartArea.margin[id] ? chartState.chartArea.margin[id] : null,
                            min: 0,
                            max: 1000
                        },
                    },
                    colorpicker: {
                        options: {
                            value: (chartState.chartArea.background && chartState.chartArea.background.color) || ''
                        },
                    }
                },
                title: {
                    dropdownlist: {
                        options: {
                            dataSource: titleValues,
                            value: that._activeTitle && titleValues.find((t) => t.value === that._activeTitle.value)
                        },
                        handler: that._titleFieldChange.bind(that)
                    },
                    textbox: {
                        options: {
                            value: that._activeTitle && chartState[that._activeTitle].text
                        }
                    },
                    colorpicker: {
                        options: {
                            value: that._activeTitle && chartState[that._activeTitle].color
                        }
                    },
                },
                series: {
                    dropdownlist: {
                        options: {
                            dataSource: [
                                { name: kendo.htmlEncode(messages.format.series.allSeries) },
                                ...chartState.series
                            ],
                            dataTextField: "name",
                            dataValueField: "name",
                            enable: isCategorical(chartState.seriesType),
                            value: that._activeSeries && chartState.series.find((s) => s.name === that._activeSeries)
                        },
                        handler: that._activeSeriesChange.bind(that)
                    },
                    colorpicker: {
                        options: {
                            value: that._activeSeries ? chartState.series.find((s) => s.name === that._activeSeries).color : '',
                            enable: false
                        },
                        handler: that._changeSeriesColor.bind(that)
                    },
                    checkbox: {
                        options: {
                            label: messages.format.series.showLabels,
                            checked: that._activeSeries
                                ? chartState.series.find((s) => s.name === that._activeSeries).labels.visible
                                : chartState.series.every((s) => s.labels?.visible)
                        },
                        handler: that._showLabels.bind(that)
                    }
                },
                legend: {
                    dropdownlist: {
                        options: {
                            dataSource: positionsData,
                            value: chartState.legend && chartState.legend.position && positionsData.find((p) => p.value === chartState.legend.position),
                        }
                    },
                    colorPicker: {
                        options: {
                            value: (chartState.legend && chartState.legend.labels && chartState.legend.labels.color) || ''
                        }
                    },
                    switch: {
                        options: {
                            checked: chartState.legend && chartState.legend.visible
                        }
                    }
                },
                categoryaxis: {
                    textbox: {
                        options: {
                            value: (chartState.categoryAxis[0][fieldForAxes] && chartState.categoryAxis[0][fieldForAxes].text) || ""
                        }
                    },
                    numerictextbox: {
                        options: {
                            value: chartState.categoryAxis[fieldForAxes] && chartState.categoryAxis[fieldForAxes].rotation,
                            min: -360,
                            max: 360,
                        }
                    },
                    checkbox: {
                        options: {
                            label: messages.format.categoryAxis[fieldForAxes].reverseOrder,
                            checked: chartState.categoryAxis[0].reverse || false
                        }
                    },
                    colorpicker: {
                        options: {
                            value: (chartState.categoryAxis[0][fieldForAxes] && chartState.categoryAxis[0][fieldForAxes].color) || ""
                        }
                    }
                },
                valueaxis: {
                    dropdownlist: {
                        options: {
                            dataSource: formats,
                            value: chartState.valueAxis[fieldForAxes] && chartState.valueAxis[fieldForAxes].labelFormat && formats.find((f) => f.value === chartState.valueAxis[fieldForAxes].labelFormat),
                        }
                    },
                    textbox: {
                        options: {
                            value: (chartState.valueAxis[fieldForAxes] && chartState.valueAxis[fieldForAxes].text) || ""
                        }
                    },
                    numerictextbox: {
                        options: {
                            value: chartState.valueAxis[0][fieldForAxes] && typeof chartState.valueAxis[0][fieldForAxes].rotation === 'number'
                                ? chartState.valueAxis[0][fieldForAxes].rotation
                                : null,
                            min: -360,
                            max: 360,
                        }
                    },
                    colorpicker: {
                        options: {
                            value: (chartState.valueAxis[0][fieldForAxes] && chartState.valueAxis[0][fieldForAxes].color) || ''
                        }
                    }
                }
            };
        },

        _titleFieldChange: function(e) {
            const that = this;
            const value = that._applyTo(e);
            const form = e.sender.wrapper.closest(DOT + "k-form");

            form.find(`[${DATA_ROLE_SELECTOR}${inputRoles.textBox}]`)
                .data("kendoTextBox")
                .value(that.chartState[value].text);

            form.find(`[${DATA_ROLE_SELECTOR}${inputRoles.colorPicker}]`)
                .data("kendoColorPicker")
                .value(that.chartState[value].color ?? "");

            form.find(`[${DATA_ROLE_SELECTOR}${inputRoles.comboBox}]`).each((_, editor) => {
                const action = $(editor).data("action");
                const field = action.includes("FontName") ? "name" : "size";
                const newValue = that._parseFont(that.chartState[value].font)[field].toString();

                const instance = $(editor).data("kendoComboBox");

                let change;

                if (newValue) {
                    const regex = new RegExp(`${newValue.replaceAll(`"`, "").split(",")[0]}`, 'g');

                    if (field === "name") {
                        change = fontNames.find((item) => regex.test(item.text));
                    } else {
                        change = fontSizes.find((item) => item.text === newValue.toString());
                    }

                    if (change) {
                        that._handlePropertyChange({ sender: instance }, change ? change.value : null);
                    }
                }

                that._activeTitle = value;
                instance._fontState.active = value;
                instance.value(change ? change.text : null);
            });
        },


        _activeSeriesChange: function(e) {
            const that = this;
            const value = e.sender.value();
            const colorPicker = e.sender.wrapper
                .closest(".k-form")
                .find(`[${DATA_ROLE_SELECTOR}${inputRoles.colorPicker}]`)
                .data("kendoColorPicker");

            if (value !== that.options.messages.format.series.allSeries) {
                const series = that.chart.options.series.find((s) => s.name === value);

                colorPicker.enable(true);
                if (series) {
                    colorPicker.value(series.color);
                }

                colorPicker._currentActiveSeries = value;
                that._activeSeries = value;
            } else {
                colorPicker.value('');
                colorPicker.enable(false);
                colorPicker._currentActiveSeries = null;
                that._activeSeries = null;
            }
        },

        _applyTo: function(e) {
            const value = e.sender.value();
            const actionsToUpdate = e.sender.wrapper.closest(".k-form").find(DATA_ACTION_SELECTOR);

            actionsToUpdate.each((_, el) => {
                const element = $(el);
                if (!element.is(e.sender.element)) {
                    const currentAction = element.data("action").split(REG_EXP_CAPITAL);
                    currentAction.shift();

                    const newAction = value + currentAction.join("");

                    element
                        .attr(DATA_ACTION, newAction)
                        .data("action", newAction);
                }
            });

            return value;
        },

        _showLabels: function(e) {
            const that = this;
            const value = e.sender.check();
            const form = e.sender.wrapper.closest(".k-form");

            const colorPicker = form
                .find(`[${DATA_ROLE_SELECTOR}${inputRoles.colorPicker}]`)
                .data("kendoColorPicker");

            that._handlePropertyChange(e, {
                seriesName: colorPicker._currentActiveSeries,
                all: !colorPicker._currentActiveSeries,
                visible: value
            });
        },

        _changeSeriesColor: function(e) {
            const that = this;
            const value = e.value;

            that._handlePropertyChange(e, {
                seriesName: e.sender._currentActiveSeries,
                color: value
            });
        },

        _getRefAttributeValue: function(element) {
            return element.getAttribute(`${REF}`).split("-");
        },

        _expansionPanels: function() {
            const that = this;
            let prevRef = "";
            let index = 0;
            that.tabStrip.contentElements.children().each((_, element) => {
                const tabRef = that._getRefAttributeValue(element)[1];

                if (prevRef === tabRef) {
                    index++;
                } else {
                    index = 0;
                }

                const titles = Object.keys(that.options.messages[tabRef]);

                const expansionPanel = $(element).kendoExpansionPanel({
                    title: kendo.htmlEncode(that.options.messages[tabRef][titles[index]].expandText),
                    complete: () => {
                        if (that._seriesGrid && that._seriesGrid.wrapper.is(":visible")) {
                            that._seriesGrid._draggableRows();
                            that._seriesGrid._reorderableRows();
                        }
                    },
                }).data("kendoExpansionPanel");

                expansionPanel.wrapper.removeClass();
                expansionPanel.wrapper.addClass(cssClasses.expansionPanelWrapper);

                prevRef = tabRef;
            });
        },

        setOptions: function(options) {

            if (!options) {
                return;
            }

            const that = this;
            const hasNewDataSource = options.dataSource !== null && options.dataSource !== undefined;
            const hasNewDataColumns = options.dataColumns !== null && options.dataColumns !== undefined;
            const hasNewState = options.state !== null && options.state !== undefined;

            if (hasNewState) {
                delete that.options.state;
                // options.state = deepExtend({}, createInitialState([], INITIAL_TYPE, options.defaultState), options.state);
            }

            if (hasNewDataSource) {
                delete that.options.dataSource;
            }

            if (hasNewDataColumns) {
                delete that.options.dataColumns;
            }

            deepExtend(that.options, options);

            if (hasNewDataColumns) {
                that.setDataColumns(options.dataColumns);
            }

            if (hasNewDataSource) {
                that.setDataSource(that.options.dataSource);
            } else {
                that._refreshContent();
            }
        },

        destroy: function() {
            const that = this;

            kendo.destroy(that.previewPane);
            kendo.destroy(that.propertyPane);

            that.element.empty();

            Widget.fn.destroy.call(that);

            that.element.off(NS);
            that.wrapper.off(NS);


            that._detachEvents();

            if (that.splitter) {
                that.splitter.destroy();
            }
            if (that.chart) {
                that.chart.destroy();
            }
            if (that.tabStrip) {
                that.tabStrip.destroy();
            }
            if (that.window) {
                that.window.destroy();
            }


            that.chart =
                that.tabStrip =
                that.window =
                that.splitter =
                that.propertyPane =
                that.chartState =
                that.previewPane = null;

            that._dynamicConfigurationFields =
                that._dynamicFormatPanels =
                that._initialAxesPanelsContent =
                that._initialState =
                that._oldType =
                that._seriesGrid = null;
        }
    });

    ui.plugin(ChartWizard);


    extend(kendo.ui.ChartWizard, {
        generateDataRows: generateDataRows,
        getWizardDataFromDataRows: getWizardDataFromDataRows
    });

})(window.kendo.jQuery);
export default kendo;

