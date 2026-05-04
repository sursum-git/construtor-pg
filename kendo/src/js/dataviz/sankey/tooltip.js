/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

(function($) {
    var kendo = window.kendo;
    var Widget = kendo.ui.Widget;

    var encode = kendo.htmlEncode;
    var styleAttr = '__style';
    var tooltipContentWrapStyle = `${styleAttr}="display: flex; align-items: center;"`;
    var space = 3;
    var TootipText = (text) => `<span ${styleAttr}="margin: 0 ${space}px">${text}</span>`;
    var Square = (color) => `<div ${styleAttr}="width: 15px; height: 15px; background-color: ${color}; display: inline-flex; margin-left: ${space}px"></div>`;
    var TooltipTemplates = {
        node: function({ dataItem, value }) {
            const { color, label } = dataItem;
            return (
                `<div ${tooltipContentWrapStyle}>
                    ${Square(color)}
                    ${TootipText(encode(label.text))}
                    ${TootipText(value)}
                </div>`
            );
        },
        link: function({ dataItem, value, rtl }) {
            const { source, target } = dataItem;
            return (
                `<div ${tooltipContentWrapStyle}>
                    ${Square(source.color)}
                    ${TootipText(encode(source.label.text))}
                    ${TootipText(kendo.ui.icon({ icon: rtl ? "arrow-left" : "arrow-right" }))}
                    ${Square(target.color)}
                    ${TootipText(encode(target.label.text))}
                    ${TootipText(value)}
                </div>`
            );
        }
    };

    var SankeyTooltip = Widget.extend({
        init: function(element, options) {
            this.options = options;

            Widget.fn.init.call(this, element);

            if (options.rtl) {
                this.element.addClass('k-rtl');
            }

            this.element.addClass('k-tooltip k-chart-tooltip k-chart-shared-tooltip')
                .append('<div class="k-tooltip-content"></div>');
        },

        size: function() {
            return {
                width: this.element.outerWidth(),
                height: this.element.outerHeight()
            };
        },

        setContent: function(content) {
            this.element.find('.k-tooltip-content').html(content);
            this.element.find(`[${styleAttr}]`).each((i, el) => {
                el.getAttribute(styleAttr)
                    .split(';')
                    .filter(s => s !== '')
                    .forEach(s => {
                        const parts = s.split(':');
                        el.style[parts[0].trim()] = parts[1].trim();
                    });
                el.removeAttribute(styleAttr);
            });
        },

        setPosition: function(popupAlign, popupOffset, offsetOption) {
            const size = this.size();
            const offset = { ...popupOffset };

            offset.left += (popupAlign.horizontal === 'left') ? offsetOption : (-1 * offsetOption);
            if (popupAlign.horizontal === 'right') {
                offset.left -= size.width;
            }

            if (popupAlign.vertical === 'bottom') {
                offset.top -= size.height + offsetOption;
            } else {
                offset.top += offsetOption;
            }

            this.element.css(offset);
        },

        show: function() {
            this.element.show();
        },

        hide: function() {
            this.element.hide();
        },

        destroy: function() {
            this.element.remove();
        }
    });

    kendo.deepExtend(kendo.dataviz, {
        SankeyTooltip: {
            Tooltip: SankeyTooltip,
            ContentTemplates: TooltipTemplates
        }
    });
})(window.kendo.jQuery);
