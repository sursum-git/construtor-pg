/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.core.js";

export const __meta__ = {
    id: "segmentedbutton",
    name: "SegmentedButton",
    category: "web",
    description: "The SegmentedButton is an internal component that provides a segmented control UI.",
    depends: ["core"]
};

(function($, undefined) {
    const kendo = window.kendo;
    const encode = kendo.htmlEncode;
    const Widget = kendo.ui.Widget;
    const ns = ".kendoSegmentedButton";
    const CLICK = "click";
    const SELECT = "select";
    const HOVER_CLASS = "k-hover";
    const FOCUS_CLASS = "k-focus";
    const DISABLED_CLASS = "k-disabled";
    const SELECTED_CLASS = "k-selected";
    const BUTTON_SELECTOR = ".k-segmented-control-button";

    const SegmentedButton = Widget.extend({
        init: function(element, options) {
            const that = this;

            Widget.fn.init.call(that, element, options);

            that._items = that.options.items || [];
            that._selectedValue = that.options.selected;

            that._createIconClassMap();
            that._render();
            that._bindEvents();

            kendo.notify(that);
        },

        options: {
            name: "SegmentedButton",
            items: [],
            selected: null,
            size: undefined,
            stretched: false
        },

        events: [
            SELECT
        ],

        _createIconClassMap: function() {
            const that = this;
            const items = that._items;
            const map = that._iconClassMap = new Map();

            items.forEach((item) => {
                if (item.iconClassOnSelection) {
                    map.set(item.value, item.iconClassOnSelection);
                }
            });
        },

        _render: function() {
            const that = this;
            const element = that.element;
            const items = that._items;
            const size = that.options.size || "medium";
            const sizeEntry = kendo.cssProperties.sizeValues.find(([name]) => name === size);
            const sizeClass = sizeEntry ? sizeEntry[1] : "md";
            const stretched = that.options.stretched;

            element.addClass(`k-segmented-control k-segmented-control-${sizeClass}${stretched ? " k-segmented-control-stretched" : ""}`);

            // Add thumb element
            const thumb = $("<div>").addClass("k-segmented-control-thumb");
            element.append(thumb);

            // Add buttons
            items.forEach((item, index) => {
                const button = that._renderButton(item, index);
                element.append(button);
            });

            that._calculateThumbPosition();
        },

        _renderButton: function(item, index) {
            const that = this;
            const isSelected = item.value === that._selectedValue;
            const isDisabled = item.disabled === true;

            const button = $("<button>")
                .addClass("k-segmented-control-button")
                .attr("data-value", item.value)
                .attr("data-button-index", index)
                .toggleClass(DISABLED_CLASS, isDisabled)
                .toggleClass(SELECTED_CLASS, isSelected && !isDisabled)
                .prop("disabled", isDisabled);

            if (item.icon) {
                const icon = $(kendo.html.renderIcon(item.icon, "k-segmented-control-button-icon k-icon"));
                if (isSelected && !isDisabled) {
                    const specialClass = that._iconClassMap.get(item.value);
                    icon.addClass(specialClass);
                }
                button.append(icon);
            }

            if (item.text) {
                const text = $("<span>")
                    .addClass("k-segmented-control-button-text")
                    .text(encode(item.text));
                button.append(text);
            }

            return button;
        },

        _calculateThumbPosition: function() {
            const that = this;
            const selected = that._selectedValue;
            const totalWidth = kendo._outerWidth(that.element);
            const totalButtonCount = that._items?.length || 0;

            if (!totalWidth) {
                return;
            }

            const buttons = that.element.find("button[data-value]");
            const button = buttons.filter(`[data-value=${selected}]`);
            const index = button.data("button-index");
            const thumb = that.element.find(".k-segmented-control-thumb");
            const buttonWidth = kendo._outerWidth(button);
            let left = 0;
            let right = 0;
            let spacing = 0;

            if (that.options.stretched) {
                const buttonsRightFromTarget = (totalButtonCount - 1 - index);

                left = (index * buttonWidth);
                right = (buttonsRightFromTarget * buttonWidth);
                spacing = (totalWidth - (totalButtonCount * buttonWidth)) / 2; // left and right padding from border;
            } else {
                let totalButtonWidth = 0;
                buttons.each((btnIndex, button) => {
                    const elementRef = $(button);
                    totalButtonWidth += kendo._outerWidth(elementRef);

                    if (btnIndex < index) {
                        left += kendo._outerWidth(elementRef);
                    }
                    if (btnIndex > index) {
                        right += kendo._outerWidth(elementRef);
                    }
                });
                spacing = (totalWidth - totalButtonWidth) / 2; // left and right padding from border;
            }

            thumb.css({
                left: left + spacing,
                right: right + spacing
            });
        },

        _bindEvents: function() {
            const that = this;

            that.element
                .on(CLICK + ns, BUTTON_SELECTOR, function(e) {
                    const button = $(e.currentTarget);

                    if (button.hasClass(DISABLED_CLASS)) {
                        return;
                    }

                    const value = button.data("value");

                    if (value !== that._selectedValue) {
                        that.select(value);
                    }
                })
                .on("mouseenter" + ns, BUTTON_SELECTOR, function(e) {
                    const button = $(e.currentTarget);
                    if (!button.hasClass(DISABLED_CLASS)) {
                        button.addClass(HOVER_CLASS);
                    }
                })
                .on("mouseleave" + ns, BUTTON_SELECTOR, function(e) {
                    $(e.currentTarget).removeClass(HOVER_CLASS);
                })
                .on("focus" + ns, BUTTON_SELECTOR, function(e) {
                    const button = $(e.currentTarget);
                    if (!button.hasClass(DISABLED_CLASS)) {
                        button.addClass(FOCUS_CLASS);
                    }
                })
                .on("blur" + ns, BUTTON_SELECTOR, function(e) {
                    $(e.currentTarget).removeClass(FOCUS_CLASS);
                });
        },

        select: function(value) {
            const that = this;

            if (value === undefined) {
                return that._selectedValue;
            }

            const item = that._items.find(i => i.value === value);

            if (!item) {
                return;
            }

            const eventArgs = {
                value: value,
                item: item
            };

            if (that.trigger(SELECT, eventArgs)) {
                return; // Event was prevented
            }

            that._selectedValue = value;
            that._updateSelectedState();
        },

        _updateSelectedState: function() {
            const that = this;
            const buttons = that.element.find(BUTTON_SELECTOR);
            const selectedValue = that._selectedValue;

            buttons.each((_, btnElement) => {
                const btn = $(btnElement);
                that._removeSelectionClasses(btn);
            });

            const selectedButton = buttons.filter(`[data-value="${selectedValue}"]`);
            that._addSelectionClasses(selectedButton, selectedValue);

            that._calculateThumbPosition();
        },

        _addSelectionClasses: function(button, selectedValue) {
            const that = this;
            const specialSelectedIconClass = that._iconClassMap.get(selectedValue);

            button.addClass(SELECTED_CLASS);

            const icon = button.find(".k-icon");

            if (icon.length && specialSelectedIconClass) {
                icon.addClass(specialSelectedIconClass);
            }
        },

        _removeSelectionClasses: function(button) {
            const that = this;
            const value = button.data("value");
            const specialSelectedIconClass = that._iconClassMap.get(value);

            button.removeClass(SELECTED_CLASS);

            const icon = button.find(".k-icon");

            if (icon.length && specialSelectedIconClass) {
                icon.removeClass(specialSelectedIconClass);
            }
        },

        value: function(value) {
            const that = this;

            if (value === undefined) {
                return that._selectedValue;
            }

            that._selectedValue = value;
            that._updateSelectedState();
        },

        enable: function(value, enable) {
            const that = this;
            const button = that.element.find(BUTTON_SELECTOR).filter(`[data-value="${value}"]`);

            if (!button.length) {
                return;
            }

            const isEnabled = enable !== false;
            const item = that._items.find(i => i.value === value);

            if (item) {
                item.disabled = !isEnabled;
            }

            button
                .toggleClass(DISABLED_CLASS, !isEnabled)
                .prop("disabled", !isEnabled);

            // Remove hover/focus classes when disabling
            if (!isEnabled) {
                button.removeClass(HOVER_CLASS + " " + FOCUS_CLASS);
            }
        },

        destroy: function() {
            const that = this;

            that.element.off(ns);

            Widget.fn.destroy.call(that);
        }
    });

    kendo.ui.plugin(SegmentedButton);
})(window.kendo.jQuery);

export default kendo;
