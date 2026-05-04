/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import {
    Sankey as KendoSankey,
    defined,
    createSankeyData,
    deepExtend,
    chartBaseTheme,
    chartTheme,
    sankeyTheme
} from "@progress/kendo-charts";
import "./kendo.dataviz.core.js";
import "./kendo.dataviz.themes.js";
import "./dataviz/sankey/tooltip.js";

export const __meta__ = {
    id: "dataviz.sankey",
    name: "Sankey",
    category: "dataviz",
    description: "The Sankey widget uses modern browser technologies to render high-quality data visualizations in the browser.",
    depends: [ "data", "userevents", "drawing", "dataviz.core", "dataviz.themes" ],
    features: [{
        id: "dataviz.sankey-pdf-export",
        name: "PDF export",
        description: "Export Sankey as PDF",
        depends: [ "pdf" ]
    }]
};

(function($) {
    window.kendo.dataviz = window.kendo.dataviz || {};

    const kendo = window.kendo;
    const template = kendo.template;
    const Widget = kendo.ui.Widget;
    const dataviz = kendo.dataviz;
    const encode = kendo.htmlEncode;
    const isRtl = kendo.support.isRtl;
    const NODE_CLICK = "nodeClick";
    const LINK_CLICK = "linkClick";
    const NODE_ENTER = "nodeEnter";
    const NODE_LEAVE = "nodeLeave";
    const LINK_ENTER = "linkEnter";
    const LINK_LEAVE = "linkLeave";
    const TOOLTIP_SHOW = "tooltipShow";
    const TOOLTIP_HIDE = "tooltipHide";
    const NODE = 'node';

    const { Tooltip, ContentTemplates } = dataviz.SankeyTooltip;

    const Sankey = Widget.extend({
        init: function(element, userOptions) {
            kendo.destroy(element);
            $(element).empty();

            this.options = kendo.deepExtend({}, this.options, userOptions, { rtl: isRtl(element) });

            this._parseAriaLabelTemplates(this.options);

            Widget.fn.init.call(this, element);

            this.wrapper = this.element;
            this._initSankey();

            this._attachEvents();

            kendo.notify(this, dataviz.ui);

            if (this._showWatermarkOverlay) {
                this._showWatermarkOverlay(this.wrapper[0]);
            }
        },

        setOptions: function(options) {
            var currentOptions = this.options;

            this.events.forEach(eventName => {
                if (currentOptions[eventName]) {
                    this.unbind(eventName, currentOptions[eventName]);
                }
            });

            const resultOptions = kendo.deepExtend(options, { rtl: isRtl(this.element) });
            this._parseAriaLabelTemplates(resultOptions);
            this._instance.setOptions(resultOptions);

            this.bind(this.events, this._instance.options);
        },

        _initSankey: function() {
            const themeOptions = this._getThemeOptions(this.options);
            this._createSankey(this.options, themeOptions);
            this.options = this._instance.options;
        },

        _createSankey: function(options, themeOptions) {
            this._instance = new KendoSankey(this.element[0], options, themeOptions);
        },

        _getThemeOptions: function(options) {
            var themeName = (options || {}).theme;

            if (themeName && dataviz.SASS_THEMES.indexOf(themeName.toLowerCase()) !== -1) {
                this.element.addClass("k-chart");
                const theme = sankeyTheme(this.element[0]);

                this.element.removeClass("k-chart");
                return theme;
            }

            if (defined(themeName)) {
                var themes = dataviz.ui.themes || {};
                var theme = themes[themeName] || themes[themeName.toLowerCase()] || {};
                const chartTheme = theme.chart || {};

                const { seriesColors: nodeColors, axisDefaults, seriesDefaults, legend, title } = chartTheme;
                const { line: links, labels } = axisDefaults;
                const strokeColor = seriesDefaults.labels.background;
                return { nodeColors, links, labels: { ...labels, stroke: { color: strokeColor } }, legend, title };
            }
        },

        _parseAriaLabelTemplates: function(options) {
            const { nodes, links } = options;

            if (nodes && nodes.labels && nodes.labels.ariaTemplate) {
                nodes.labels.ariaTemplate = template(nodes.labels.ariaTemplate);
            }

            if (links && links.labels && links.labels.ariaTemplate) {
                links.labels.ariaTemplate = template(links.labels.ariaTemplate);
            }
        },

        _attachEvents: function() {
            this.events.forEach(eventName => {
                this._instance.bind(eventName, event => {
                    if (this._events[eventName]) {
                        this._events[eventName].forEach(handler => handler.call(undefined, event));
                    }
                });
            });

            this._instance.bind(TOOLTIP_SHOW, this.tooltipShow.bind(this));
            this._instance.bind(TOOLTIP_HIDE, this.tooltipHide.bind(this));
        },

        tooltipShow: function(e) {
            const { tooltip, rtl } = this.options;
            const { nodeTemplate, linkTemplate, offset } = tooltip;

            if (!this._tooltip) {
                const doc = this.element[0].ownerDocument;
                this._tooltip = new Tooltip(doc.createElement('div'), { rtl });
                const { appendTo = doc.body } = this.options.tooltip;
                this._tooltip.element.appendTo($(appendTo));
            }

            const currentTemplate = template((e.targetType === NODE ? nodeTemplate : linkTemplate) || ContentTemplates[e.targetType]);
            const value = encode(kendo.format(this.options.messages.tooltipUnits, defined(e.nodeValue) ? e.nodeValue : e.dataItem.value));

            this._tooltip.setContent(currentTemplate({ dataItem: e.dataItem, value, rtl }));
            this._tooltip.setPosition(e.tooltipData.popupAlign, e.tooltipData.popupOffset, offset);
            this._tooltip.show();
        },

        tooltipHide: function() {
            if (this._tooltip) {
                this._tooltip.destroy();
                this._tooltip = null;
            }
        },

        exportVisual: function(exportOptions) {
            return this._instance.exportVisual(exportOptions);
        },

        destroy: function() {
            Widget.fn.destroy.call(this);
            this.tooltipHide();
            this._instance.destroy();
            this._instance = null;
        },

        events: [
            NODE_CLICK,
            LINK_CLICK,
            NODE_ENTER,
            NODE_LEAVE,
            LINK_ENTER,
            LINK_LEAVE
        ],
        options: {
            name: "Sankey",
            theme: "default",
            tooltip: {
                offset: 12
            },
            messages: {
                tooltipUnits: "({0} Units)"
            }
        }
    });

    dataviz.ExportMixin.extend(Sankey.fn);

    if (kendo.PDFMixin) {
        kendo.PDFMixin.extend(Sankey.fn);
    }

    dataviz.ui.plugin(Sankey);

    kendo.deepExtend(dataviz, {
        Sankey: Sankey,
        createSankeyData: createSankeyData
    });

})(window.kendo.jQuery);


export default kendo;

