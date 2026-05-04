/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.core.js";
import "./kendo.multiviewcalendar.js";
import "./kendo.datepicker.js";
import "./kendo.html.button.js";

export const __meta__ = {
    id: "daterangepicker",
    name: "DateRangePicker",
    category: "web",
    description: "Date range picker.",
    depends: [ "core", "multiviewcalendar", "datepicker", "html.button",]
};

var isPresent = function(value) {
    return value !== void 0 && value !== null;
  };

var isValidDate = function(value) {
    return isPresent(value) && value.getTime && isNumber(value.getTime());
  };

  var isNumber = function(value) {
    return isPresent(value) && typeof value === "number" && !Number.isNaN(value);
  };


(function($, undefined) {
    let kendo = window.kendo,
        ui = kendo.ui,
        html = kendo.html,
        keys = kendo.keys,
        mediaQuery = kendo.mediaQuery,
        Widget = ui.Widget,
        MONTH = "month",
        OPEN = "open",
        CLOSE = "close",
        CHANGE = "change",
        DIV = "<div />",
        MIN = "min",
        MAX = "max",
        template = kendo.template,
        extend = $.extend,
        ID = "id",
        support = kendo.support,
        mobileOS = support.mobileOS,
        SELECTED = "k-selected",
        ARIA_EXPANDED = "aria-expanded",
        ARIA_DISABLED = "aria-disabled",
        ARIA_ACTIVEDESCENDANT = "aria-activedescendant",
        STATEDISABLED = "k-disabled",
        HIDDEN = "k-hidden",
        DISABLED = "disabled",
        READONLY = "readonly",
        ARIA_HIDDEN = "aria-hidden",
        START = "start",
        END = "end",
        ns = ".kendoDateRangePicker",
        CLICK = "click" + ns,
        MOUSEDOWN = "mousedown" + ns,
        UP = support.mouseAndTouchPresent ? kendo.applyEventMap("up", ns.slice(1)) : CLICK,
        parse = kendo.parseDate;

    var DateRangeView = function(options) {
        kendo.DateView.call(this, options);
    };

    DateRangeView.prototype = Object.create(kendo.DateView.prototype);

    function preventDefault(e) {
        e.preventDefault();
    }

    DateRangeView.prototype._calendar = function() {
        var that = this;
        var calendar = that.calendar;
        var options = that.options;
        var div;

        if (!calendar) {
            var contentElement = that.popup._content || that.popup.element;
            if (options.adaptiveMode == "auto" && !that.bigScreenMQL.mediaQueryList.matches) {
                contentElement = contentElement.append($('<div class="k-scrollable-wrap"></div>')).find(".k-scrollable-wrap");
            }

            div = $(DIV).attr(ID, kendo.guid())
                        .appendTo(contentElement);

            that.calendar = calendar = new ui.MultiViewCalendar(div, {
                size: options.adaptiveMode == "auto" && !that.bigScreenMQL.mediaQueryList.matches ? "large" : options.size,
                orientation: options.adaptiveMode == "auto" && !that.bigScreenMQL.mediaQueryList.matches ? "vertical" : "horizontal",
                views: options.adaptiveMode == "auto" && !that.bigScreenMQL.mediaQueryList.matches ? 1 : 2,
            });

            that._setOptions(options);

            calendar.navigate(that._value || that._current, options.start);

            that._range = that._range || options.range || {};

            div.on(MOUSEDOWN, preventDefault)
               .on(CLICK, "td:has(.k-link)", that._click.bind(that));

            that.calendar.selectRange(that._range);
        }
    };

    DateRangeView.prototype._setOptions = function(options) {
        var that = this;
        this.calendar.setOptions({
            allowReverse: options.allowReverse,
            focusOnNav: false,
            change: options.change,
            culture: options.culture,
            dates: options.dates,
            depth: options.depth,
            footer: options.footer,
            format: options.format,
            selectable: options.selectable,
            max: options.max,
            min: options.min,
            month: options.month,
            weekNumber: options.weekNumber,
            start: options.start,
            disableDates: options.disableDates,
            range: options.range,
            size: options.adaptiveMode == "auto" && !that.bigScreenMQL.mediaQueryList.matches ? "large" : options.size
        });
    };

    DateRangeView.prototype.range = function(range) {
        this._range = range;

        if (this.calendar) {
            if (!range.start && !range.end) {
                this.calendar.selectRange(range);
                this.calendar.rangeSelectable.clear();
            } else {
                this.calendar.selectRange(range);
            }
        }
    };

    DateRangeView.prototype.move = function(e) {
        var that = this;
        var key = e.keyCode;
        var calendar = that.calendar;
        var selectIsClicked = e.ctrlKey && key == keys.DOWN;
        var isEnter = key == keys.ENTER;
        var handled = false;

        if (e.altKey) {
            if (key == keys.DOWN) {
                that.open();
                e.preventDefault();
                handled = true;
            } else if (key == keys.UP) {
                that.close();
                e.preventDefault();
                handled = true;
            }

        } else if (that.popup.visible()) {

            if (key == keys.ESC || (selectIsClicked && calendar._cell.hasClass(SELECTED))) {
                that.close();
                e.preventDefault();
                return true;
            }
            if (isEnter && that._range.target == START && that._range.start == null ||
                (isEnter && that._range.target == START && that._range.end !== null) ||
                e.key == 'ArrowUp' || e.kay == 'ArrowDown') {
                that._current = calendar._move(e);
            } else if (isEnter) {
                that.calendar.trigger(CHANGE);
            } else {
                that._current = calendar._move(e);
            }
            handled = true;
        }

        return handled;
    };

    DateRangeView.prototype._click = function(e) {

        if (!this.options.autoClose) {
            return;
        }

        if (mobileOS.ios || (mobileOS.android && (mobileOS.browser == "firefox" || mobileOS.browser == "chrome"))) {
            if (this._range && this._range.end) {
                this.close();
            }
        }
        else if (this._range && this._range.start && this._range.end && $(e.currentTarget).closest(".k-calendar-view").is(".k-calendar-monthview")) {
            this.close();
        }
    };

    kendo.DateRangeView = DateRangeView;

    var DateRangePicker = Widget.extend({
        init: function(element, options) {
            var that = this;
            var disabled;

            Widget.fn.init.call(that, element, options);
            element = that.element;
            options = that.options;
            options.disableDates = kendo.calendar.disabled(options.disableDates);

            options.min = parse(element.attr("min")) || parse(options.min);
            options.max = parse(element.attr("max")) || parse(options.max);
            options.inputMode = options.inputMode || element.attr("inputmode") || "text";

            that._initialOptions = extend({}, options);

            that._buildHTML();

            that._range = that.options.range;
            that._changeTriggered = false;

            that._initializeDateViewProxy = that._initializeDateView.bind(that);
            that.bigScreenMQL = mediaQuery("large");
            that.bigScreenMQL.onChange(()=> {
                that._initializeDateViewProxy();
            });
            that._initializeDateView();

            that._ariaTemplate = template(this.options.ARIATemplate).bind(that);
            that._reset();
            that._aria();

            that._inputs
                .on(UP + ns, that._click.bind(that))
                .on("keydown" + ns, that._keydown.bind(that));

            that._initializeDateInputs();

            that._expandButton();
            that._clearButton();

            disabled = element.is("[disabled]");
            if (disabled) {
                that.enable(false);
            } else {
                that.readonly(element.is("[readonly]"));
            }
        },

        options: {
            name: "DateRangePicker",
            labels: true,
            autoAdjust: true,
            allowReverse: false,
            autoClose: true,
            calendarButton: false,
            clearButton: false,
            footer: "",
            format: "",
            culture: "",
            min: new Date(1900, 0, 1),
            max: new Date(2099, 11, 31),
            start: MONTH,
            depth: MONTH,
            adaptiveMode: "none",
            animation: {},
            month: {},
            startField: "",
            endField: "",
            dates: [],
            disableDates: null,
            range: null,
            ARIATemplate: ({ valueType, text }) => `Current focused ${valueType} is ${text}`,
            weekNumber: false,
            messages: {
                startLabel: "Start",
                endLabel: "End"
            },
            size: undefined,
            fillMode: undefined,
            rounded: undefined
        },

        events: [
            OPEN,
            CLOSE,
            CHANGE
        ],

        setOptions: function(options) {
            var that = this;

            Widget.fn.setOptions.call(that, options);

            options = that.options;

            options.min = parse(options.min);
            options.max = parse(options.max);
            that._inputs.off(ns);

            that._initializeDateInputs();
            that.dateView.setOptions(options);
            that._expandButton();
            that._clearButton();
            that._range = options.range;
        },

        _aria: function() {
            this._inputs
                .attr({
                    role: "combobox",
                    "aria-haspopup": "grid",
                    "aria-expanded": false,
                    "aria-controls": this.dateView._dateViewID,
                    "autocomplete": "off"
                });
        },

        _clearButton: function() {
            let that = this,
                options = that.options,
                startInput = that._startInput,
                endInput = that._endInput,
                range = that.range();

            if (!options.clearButton) {
                return;
            }

            if (!that._startClearButton) {
                that._startClearButton = $(`<span unselectable="on" class="k-clear-value ${range && range.start ? '' : 'k-hidden'}" title="Clear the start date value">${kendo.ui.icon("x")}</span>`).attr({
                    "role": "button",
                    "tabIndex": -1
                }).insertAfter(startInput).on("click", that._clearValue.bind(that));
            }

            if (!that._endClearButton) {
                that._endClearButton = $(`<span unselectable="on" class="k-clear-value ${range && range.start ? '' : 'k-hidden'}" title="Clear the end date value">${kendo.ui.icon("x")}</span>`).attr({
                    "role": "button",
                    "tabIndex": -1
                }).insertAfter(endInput).on("click", that._clearValue.bind(that));
            }
        },

        _expandButton: function() {
            let that = this,
                startInput = that._startInput,
                endInput = that._endInput,
                options = that.options,
                startInputButton,
                endInputButton;

            if (!options.calendarButton) {
                return;
            }

            startInputButton = startInput.next("button.k-input-button");
            endInputButton = endInput.next("button.k-input-button");

            if (!startInputButton[0]) {
                startInputButton = $(html.renderButton('<button aria-label="select" tabindex="-1" class="k-input-button k-button k-icon-button"></button>', {
                    icon: "calendar",
                    size: options.size,
                    fillMode: options.fillMode,
                    shape: "none",
                })).insertAfter(startInput);
            }

            if (!endInputButton[0]) {
                endInputButton = $(html.renderButton('<button aria-label="select" tabindex="-1" class="k-input-button k-button k-icon-button"></button>', {
                    icon: "calendar",
                    size: options.size,
                    fillMode: options.fillMode,
                    shape: "none",
                })).insertAfter(endInput);
            }

            that._startDateButton = startInputButton.attr({
                "role": "button"
            }).on(CLICK, that._expandButtonClick.bind(that));

            that._endDateButton = endInputButton.attr({
                "role": "button"
            }).on(CLICK, that._expandButtonClick.bind(that));
        },

        _click: function() {
            let that = this,
                options = that.options;

                that._isFirstClick = that._isFirstClick == undefined ? true : false;

            if (options.calendarButton) {
                return;
            }

            if (!that._preventInputAction && !that.dateView.popup.visible()) {
                that.dateView.open();
            }
        },

        _toggleClearButton: function(target, state) {
            let that = this;

            if (!target || !that.options.clearButton) {
                return;
            }

            if (target === START) {
                that._startClearButton.toggleClass(HIDDEN, !state);
            }

            if (target === END) {
                that._endClearButton.toggleClass(HIDDEN, !state);
            }
        },

        _clearValue: function(e) {
            let that = this,
                target = $(e.target),
                type = target.closest(".k-input").find(".k-input-inner").data("input"),
                startDateInput = that._startDateInput,
                endDateInput = that._endDateInput,
                range = that.range();

            if (type === START) {
                startDateInput.value(null);
                startDateInput.trigger(CHANGE);
                that.range({ start: null, end: range.end });
            }

            if (type === END) {
                endDateInput.value(null);
                endDateInput.trigger(CHANGE);
                that.range({ start: range.start, end: null });
            }

            that._toggleClearButton(type, false);
        },

        _expandButtonClick: function(e) {
            let that = this;

            if (!that._preventInputAction && !that.dateView.popup.visible()) {
                that._isFirstClick = true;
                that.dateView.open();
                $(e.target).closest(".k-input").find(".k-input-inner").trigger("focus");
            }
        },

        _navigatePrevNextDate: function(e) {
            var that = this,
                currentTargetedDate,
                currentTargetedDateFocused,
                dateView = that.dateView;
            var currentInputInstance = that._currentTarget == START ? that._startDateInput.dateInputInstance : that._endDateInput.dateInputInstance;
            var symbol = currentInputInstance.currentFormat[currentInputInstance.selection.start];
            var step = currentInputInstance.getStepFromSymbol(symbol);
            let parsedDate = parse(currentInputInstance.elementValue, currentInputInstance.dateObject.format, currentInputInstance.dateObject.localeId);

            step = e.key == 'ArrowDown' ? -step : +step;
            var isInRange = kendo.calendar.isInRange(parsedDate, dateView.options.min, dateView.options.max);

            if (isValidDate(parsedDate) && isInRange && dateView.popup.visible()) {

                e.preventDefault();
                currentInputInstance.modifyDateSegmentValue(step, symbol, e);
                dateView.calendar.selectRange({ start: that._startDateInput.value(), end: that._endDateInput.value() });
                currentTargetedDate = that._currentTarget == START ? that._startDateInput.value() : that._endDateInput.value();
                currentTargetedDateFocused = e.key == 'ArrowDown' ? currentTargetedDate.setDate(currentTargetedDate.getDate() - 7) : currentTargetedDate.setDate(currentTargetedDate.getDate() + 7);
                that.dateView.calendar._current = new Date(currentTargetedDateFocused);
            } else if (dateView.calendar) {
                dateView.calendar.selectRange({ start: that._startDateInput.value(), end: that._endDateInput.value() });
            } else {
                dateView.range({ start: that._startDateInput.value(), end: that._endDateInput.value() });
            }
        },

        _handleAllowReverseEnter: function(e, range, currentInput, currentInputInstance, parsedDate) {
            var that = this,
                dateView = that.dateView;

            if (that._currentTarget === START && that._startDateInput.value() === null) {
                e.target.select();
                dateView.move(e);
            } else if (that._currentTarget === END && that._endDateInput.value() === null) {
                currentInput.value(dateView._current);
                range = { start: range.start, end: dateView._current };
                currentInput.trigger(CHANGE);
                dateView.range(range);
                dateView.close();

            } else if (that._isFirstClick && currentInputInstance.value !== dateView._current) {
                currentInput.value(dateView._current);
                range[that._currentTarget] = dateView._current;
                currentInput.trigger(CHANGE);
                dateView.range(range);
                dateView.close();
            } else {
                dateView.range(range);
            }
        },

        _handleStandardEnter: function(e, range, currentInput, parsedDate, handled) {
            var that = this,
                dateView = that.dateView;
                let currentGreather = that._startDateInput.value() > dateView._current ? null : dateView._current;
                let selectedEnd = that._isFirstClick == true ? currentGreather : that._endDateInput.value();
                let isCurrentMatchSelected = currentInput.value() === that.dateView._current;

                if (that._currentTarget == START && !isCurrentMatchSelected && that.dateView.popup.visible() && !that._backspaceChange) {
                    currentInput.value(dateView._current);
                    range = { start: dateView._current, end: range.end };
                    dateView.range(range);
                    currentInput.trigger(CHANGE);
                    that._endDateInput.dateInputInstance.focus();

                } else if (that._backspaceChange) {
                    let correctStartDate = currentInput.dateInputInstance.elementValue == currentInput._emptyMask ? null : currentInput._oldValue;
                    currentInput.value(correctStartDate);
                    range[that._currentTarget] = correctStartDate;
                    dateView.range(range);
                    currentInput.trigger(CHANGE);

                } else if (that._startDateInput.value() <= that._endDateInput.value()
                    && that._startDateInput.value() != null && that._endDateInput.value() != null) {

                    range = { start: that._startDateInput.value(), end: selectedEnd };
                    dateView.range(range);
                    if (that._isFirstClick) {
                        currentInput.value(selectedEnd);
                    }
                    that._updateARIA(dateView._current);
                    currentInput.trigger(CHANGE);

                } else if (that._currentTarget == START && that._endDateInput.value() <= dateView._current) {
                    e.target.select();
                    handled = dateView.move(e);
                } else if (that._currentTarget == START && that._startDateInput.value() > selectedEnd) {
                    that._endDateInput.value(that._startDateInput.value());
                    currentInput.trigger(CHANGE);
                } else {
                    if (that._currentTarget == END && that._startDateInput.value() > that._endDateInput.value() && isValidDate(parsedDate)) {
                        range = { start: that._startDateInput.value(), end: null };
                        that._endDateInput.value(that._startDateInput.value());
                    } else if (selectedEnd == null) {
                        let correctDate = currentInput == 'start' ? range.start : range.end;
                        currentInput.value(correctDate);
                    } else {
                        currentInput.value(selectedEnd);
                        range[that._currentTarget] = selectedEnd;
                    }
                    currentInput.trigger(CHANGE);
                    dateView.range(range);
                }
                that._backspaceChange = false;
        },

        _handleArrowKeys: function(e, parsedDate, dateView, handled) {
            let that = this,
            currentInputInstance = that._currentTarget == START ? that._startDateInput.dateInputInstance : that._endDateInput.dateInputInstance,
            currentTargetedDate = currentInputInstance.value;

            if (that._isFirstClick === false && (e.key == 'ArrowDown' || e.key == 'ArrowUp') && !e.altKey && !e.ctrlKey) {
                that._navigatePrevNextDate(e);
            }

            if (that._isFirstClick === false && (e.key == 'ArrowRight' || e.key == 'ArrowLeft') && !e.shiftKey) {
                let index = e.key == 'ArrowRight' ? -1 : + 1;
                if (parsedDate != null) {
                    dateView.calendar._current = new Date(currentTargetedDate.setDate(currentTargetedDate.getDate() + index));
                    currentInputInstance.switchDateSegment(- index);
                }

            } else if (e.shiftKey && (e.key == 'ArrowRight' || e.key == 'ArrowLeft')) {
                handled = dateView.move(e);
                that._updateARIA(dateView._current);
            }
        },


        _keydown: function(e) {
            var that = this,
                dateView = that.dateView,
                isCurrentInRange,
                handled = false,
                range = that.range(),
                currentInput = that._currentTarget == START ? that._startDateInput : that._endDateInput,
                currentInputInstance = that._currentTarget == START ? that._startDateInput.dateInputInstance : that._endDateInput.dateInputInstance,
                parsedDate = parse(currentInputInstance.elementValue, currentInputInstance.dateObject.format, currentInputInstance.dateObject.localeId),

                isCalendarUpdated = false;

            if (that._preventInputAction) {
                e.stopImmediatePropagation();
                return;
            }

            if (dateView.calendar) {
                isCurrentInRange = kendo.calendar.isInRange(parsedDate, dateView.calendar.options.min, dateView.calendar.options.max);
            }

            if (e.altKey && (e.key == 'ArrowDown' || e.key == 'ArrowUp')) {
                that._isFirstClick = true;
                if (e.key == 'ArrowUp' && (JSON.stringify(range) != JSON.stringify(that.dateView.calendar._range))) {
                    dateView.range(that.dateView.calendar._range);
                    currentInput.trigger(CHANGE);
                }
            }

            if (e.key == 'Enter') {
                isCalendarUpdated = true;
                if (that.options.allowReverse) {
                    that._handleAllowReverseEnter(e, range, currentInput, currentInputInstance, parsedDate);
                } else {
                    that._handleStandardEnter(e, range, currentInput, parsedDate, handled);
                }

                if (this.dateView && this.options.autoClose && e.target.attributes['data-input'].value == END) {
                    this.dateView.close();
                }
            } else if (e.keyCode == keys.ESC) {
                isCalendarUpdated = true;
                handled = dateView.move(e);
            } else if (e.key == 'Backspace' || e.key == 'Delete') {
                that._changeTriggered = false;
                that._backspaceChange = true;
            } else {
                if ((that._isFirstClick && (isValidDate(parsedDate) && isCurrentInRange) && isNaN(parseFloat(e.key))) || e.altKey) {
                    handled = dateView.move(e);
                    that._updateARIA(dateView._current);
                    isCalendarUpdated = true;
                } else if (that._isFirstClick && (isValidDate(parsedDate) || isCurrentInRange) && isNaN(parseFloat(e.key)) || e.altKey ) {
                    isCalendarUpdated = true;
                    that._updateARIA(dateView._current);

                    if ((e.key == 'ArrowDown' || e.key == 'ArrowUp') && !e.altKey && !e.ctrlKey) {
                        that._navigatePrevNextDate(e);
                    }
                    handled = dateView.move(e);
                } else {
                    that._updateARIA(dateView._current);
                    that._handleArrowKeys(e, parsedDate, dateView, handled);

                    if (!isNaN(parseFloat(e.key)) && !e.altKey && !e.ctrlKey && !e.shiftKey) {
                        setTimeout(function() {
                            parsedDate = parse(currentInputInstance.elementValue, currentInputInstance.dateObject.format, currentInputInstance.dateObject.localeId);
                            let isInRange = kendo.calendar.isInRange(parsedDate, dateView.options.min, dateView.options.max);

                            if (isValidDate(parsedDate) && isInRange && dateView.popup.visible()) {
                                dateView.calendar.selectRange({ start: that._startDateInput.value(), end: that._endDateInput.value() });
                                dateView.calendar._current = that._currentTarget == START ? that._startDateInput.value() : that._endDateInput.value();
                                handled = dateView.move(e);
                                that._updateARIA(dateView._current);

                            } else if (isValidDate(parsedDate) && isInRange) {
                                dateView.range({ start: that._startDateInput.value(), end: that._endDateInput.value() });
                            }
                        });
                    }
                }
            }

            if ((isValidDate(parsedDate) && isCurrentInRange) && !isCalendarUpdated && isNaN(parseFloat(e.key))) {
                handled = dateView.move(e);
                that._updateARIA(dateView._current);
            }

            if (handled && e.stopImmediatePropagation) {
                e.stopImmediatePropagation();
            }
        },

        _updateARIA: function(date) {
            var that = this;
            var calendar = that.dateView.calendar;

            if (that._inputs && that._inputs.length) {
                that._inputs.removeAttr(ARIA_ACTIVEDESCENDANT);
            }

            if (calendar) {
                if (date && !calendar._dateInViews(date)) {
                    calendar.navigate(date);
                }

                if ($.contains(that.element[0], document.activeElement)) {
                    that._inputs.attr(ARIA_ACTIVEDESCENDANT, calendar._updateAria(that._ariaTemplate, date));
                }
            }
        },

        _isAdaptive: function() {
            const that = this;
            return that.options.adaptiveMode === "auto" && !that.bigScreenMQL.mediaQueryList.matches;
        },

        _inputFocus: function(e) {
            let that = this, range = that.range();

            if (that._currentTarget !== $(e.target).data("input")) {
                that._currentTarget = $(e.target).data("input");
                if (range) {
                    if (that._isAdaptive() && !that.options.autoClose && that.dateView.popup.visible()) {
                        const temp = that._temporaryInputValues || {};
                        range.start = temp.start;
                        range.end = temp.end;
                    } else {
                        range.start = that._startDateInput.value();
                        range.end = that._endDateInput.value();
                    }
                    that.range(range);
                } else {
                    that.range({ start: null, end: null });
                }
            }
        },

        _updateInputValueAndSelectRange: function(e, input, newValue, range) {
            var that = this;

            input.value(newValue);
            input.trigger(CHANGE);

            if (that.dateView.calendar) {
                that.dateView.calendar.selectRange(range);
            }
            that.trigger(CHANGE);
        },

        _startChange: function(e) {
            var that = this;
            var input = e.sender;
            var startValue = input.value();

            let endValue;
            if (that._isAdaptive() && !that.options.autoClose) {
                const temporary = that._temporaryInputValues || {};
                endValue = temporary.end;
            } else {
                endValue = that._endDateInput.value();
            }

            var range = that.range();

            if (that.options.disableDates(startValue)) {
                e.sender.value(null);
                startValue = null;
            }

            if (that._backspaceChange == true) {
                let correctStartDate = input.dateInputInstance.elementValue == input._emptyMask ? null : range.start;
                input.value(correctStartDate);
                that.range({ start: correctStartDate, end: range.end });
                that.dateView.range(that.range());
                startValue = correctStartDate;
                that._changeTriggered = false;
                that._backspaceChange = false;
            }

            that.range({ start: startValue, end: endValue });

            if (e.blur && !that.options.allowReverse &&
                that._startDateInput.value() > that._endDateInput.value() &&
                endValue != null &&
                that.options.min.getTime() === new Date(1900, 0, 1).getTime()) {
                that._changeTriggered = true;
                setTimeout(function() {
                    that._updateInputValueAndSelectRange(e, that._startDateInput, range.start, range);
                    that._changeTriggered = false;
                });
            }

            if (!that._changeTriggered) {
                that.trigger(CHANGE);
            }

            that._backspaceChange = false;
            that._toggleClearButton(START, startValue !== null);
        },

        _endChange: function(e) {
            var that = this;
            var input = e.sender;
            let endValue;
            let startValue;
            const isAdaptive = that._isAdaptive();
            if (isAdaptive && !that._backspaceChange) {
                const temporary = that._temporaryInputValues || {};
                startValue = temporary.start;
                endValue = temporary.end;
            } else {
                startValue = that._startDateInput.value();
                endValue = input.value();
            }
            var range = that.range();

            if (that.options.disableDates(endValue)) {
                e.sender.value(null);
                endValue = null;
            }

            if (that._backspaceChange == true) {
                let correctEndDate = input.dateInputInstance.elementValue == input._emptyMask ? null : range.end;
                input.value(correctEndDate);
                that.range({ start: range.start, end: range.correctEndDate });
                that.dateView.range(that.range());
                endValue = correctEndDate;
                that._changeTriggered = false;
                that._backspaceChange = false;
            }

            that.range({ start: startValue, end: endValue });

            if (!e.blur) {
                if (that._currentTarget === END) {
                    if (!startValue || (!that.options.autoClose) || isAdaptive) {
                        that._startDateInput.dateInputInstance.focus();
                    } else {
                        that._endDateInput.dateInputInstance.focus();
                    }
                } else {
                    if (!endValue || (!that.options.autoClose) || isAdaptive) {
                        that._endDateInput.dateInputInstance.focus();
                    } else {
                        that._startDateInput.dateInputInstance.focus();
                    }
                }
            } else if (!that.options.allowReverse && that._startDateInput.value() > that._endDateInput.value()
                && input.dateInputInstance.previousElementValue != input._emptyMask) {
                that._changeTriggered = true;
                setTimeout(function() {
                    that._updateInputValueAndSelectRange(e, that._endDateInput, range.end, range);
                });
            }

            if (e.blur == true && this.dateView && this.options.autoClose && e.sender.element.attr('data-input') == END) {
                this.dateView.close();
            }

            if (!that._changeTriggered) {
                that.trigger(CHANGE);
            }

            that._backspaceChange = false;
            that._toggleClearButton(END, endValue !== null);
        },
        _initializeDateView: function() {
            var that = this;
            var div;

            if (that.dateView) {
                if (that.dateView.popup && that.dateView.popup.wrapper) {
                    that.dateView.popup.wrapper.remove();
                }
                that.dateView.destroy();
                that.dateView = null;
            }

            const isAdaptive = that.options.adaptiveMode === "auto" && !that.bigScreenMQL.mediaQueryList.matches;

            that.dateView = new DateRangeView(extend({}, that.options, {
                id: that.element.attr(ID),
                anchor: that.wrapper,
                views: 2,
                selectable: {
                    mode: "range",
                    reverse: that.options.allowReverse,
                    resetOnStart: !that.options.autoClose && isAdaptive
                },
                value: that._range?.start || that._range?.end,
                range: that._range,
                change: function() {
                    var range = this.selectRange();
                    if (that._isAdaptive()) {
                        that._temporaryInputValues = {
                            start: range.start,
                            end: range.end
                        };
                    }
                    that.range(range);
                    that.trigger(CHANGE);
                    that._changeTriggered = true;
                    that._startDateInput.trigger(CHANGE);
                    that._endDateInput.trigger(CHANGE);
                    that._changeTriggered = false;
                },
                close: function(e) {
                    if (that.trigger(CLOSE)) {
                        e.preventDefault();
                    } else {
                        that._inputs.attr(ARIA_EXPANDED, false);
                        div.attr(ARIA_HIDDEN, true);

                        setTimeout(function() {
                            let range = that.range();
                            if (that._inputs) {
                                that._inputs.removeAttr(ARIA_ACTIVEDESCENDANT);
                            }

                            if (range && that.dateView && that.dateView.calendar && that.dateView.calendar.rangeSelectable) {
                                that.range(range);
                            }
                        });
                    }
                    that._isFirstClick = undefined;
                },
                open: function(e) {
                    let range = that.range();
                    let currentInput = that._currentTarget == START ? that._startDateInput : that._endDateInput;

                    if (that.trigger(OPEN)) {
                        e.preventDefault();
                    } else {
                        if (that._isAdaptive() && !that.options.autoClose) {
                            that._temporaryInputValues = {
                                startInitial: that._startDateInput.value(),
                                endInitial: that._endDateInput.value()
                            };

                            that.dateView.popup.wrapper.find("[data-ref-actionsheet-close-button]").on("mousedown", function() {
                                e.preventDefault();
                                const temp = that._temporaryInputValues || {};
                                const startValue = temp.start;
                                const endValue = temp.end;

                                that._startDateInput.value(startValue);
                                that._endDateInput.value(endValue);
                                that.close();
                            });
                        }
                        if (range && (JSON.stringify(that._startDateInput.value()) != JSON.stringify(range.start)
                            || JSON.stringify(that._endDateInput.value()) != JSON.stringify(range.end))
                            && (that._startDateInput.value() != null || that._endDateInput.value() != null)) {
                            that.dateView.range({ start: that._startDateInput.value(), end: that._endDateInput.value() });
                            currentInput.trigger(CHANGE);
                        }
                        that.dateView._current = currentInput.value();
                        that._inputs.attr(ARIA_EXPANDED, true);
                        div.attr(ARIA_HIDDEN, false);
                        that._updateARIA();
                    }
                },
                _buttons: !this.options.autoClose && isAdaptive && [
                    { text: "Cancel", click: function() {
                        const temp = that._temporaryInputValues || {};
                        const startInitial = temp.startInitial;
                        const endInitial = temp.endInitial;

                        that._startDateInput.value(startInitial);
                        that._endDateInput.value(endInitial);
                        that.close();
                    } },
                    { text: "Set", themeColor: "primary", click: function() {
                        const temp = that._temporaryInputValues || {};
                        const startValue = temp.start;
                        const endValue = temp.end;

                        that._startDateInput.value(startValue);
                        that._endDateInput.value(endValue);
                        that.close();
                    } }
                ]
            }));

            div = that.dateView.div;
        },
        _initializeDateInputs: function() {
            var that = this;
            var options = that.options;
            var range = options.range || {};
            var inputOptions = {
                autoAdjust: options.autoAdjust,
                footer: options.footer,
                format: options.format,
                culture: options.culture,
                min: options.min,
                max: options.max,
                start: options.start,
                startField: options.startField,
                endField: options.endField,
                depth: options.depth,
                animation: options.animation,
                month: options.month,
                dates: options.dates,
                disableDates: options.disableDates,
                ARIATemplate: options.ARIATemplate,
                weekNumber: options.weekNumber,
                size: options.size,
                fillMode: options.fillMode,
                rounded: options.rounded,
                toggleDayPeriod: true,
                inputMode: options.inputMode
            };

            if (that._startDateInput) {
                that._startDateInput.destroy();
                that._endDateInput.destroy();
                that.wrapper.empty();
                that._buildHTML();
                that._inputs
                    .on(UP + ns, that._click.bind(that))
                    .on("keydown" + ns, that._keydown.bind(that));
            }
            that._startDateInput = that._startInput.kendoDateInput(extend(true, inputOptions, { value: range.start })).getKendoDateInput();
            that._endDateInput = that._endInput.kendoDateInput(extend(true, inputOptions, { value: range.end })).getKendoDateInput();

            that._startChangeHandler = that._startChange.bind(that);
            that._startDateInput.bind(CHANGE, that._startChangeHandler);

            that._endChangeHandler = that._endChange.bind(that);
            that._endDateInput.bind(CHANGE, that._endChangeHandler);

            that._inputs.on("focus" + ns, that._inputFocus.bind(that));
        },

        _buildHTML: function() {
            var that = this;
            var element = that.element;
            var id;

            if (!that.wrapper) {
                that.wrapper = element.addClass("k-daterangepicker");
            }

            if (that.options.labels) {
                id = kendo.guid();
                $('<span class="k-floating-label-container"><input data-input="' + START + '" id="' + id + '"/><label for="' + id + '" class="k-floating-label">' + kendo.htmlEncode(that.options.messages.startLabel) + '</label></span>').appendTo(that.wrapper);
                id = kendo.guid();
                $('<span>&nbsp;</span><span class="k-floating-label-container"><input data-input="' + END + '" id="' + id + '"/><label for="' + id + '" class="k-floating-label">' + kendo.htmlEncode(that.options.messages.endLabel) + '</label></span>').appendTo(that.wrapper);
            } else {
                $('<input data-input="' + START + '" /><span>&nbsp;</span><input data-input="' + END + '" />').appendTo(that.wrapper);
            }

            that._startInput = that.wrapper.find("input").eq(0);
            that._endInput = that.wrapper.find("input").eq(1);

            if (that.options.startField !== "") {
                that._startInput.attr(kendo.attr("bind"), "value: " + that.options.startField);
				that._startInput.attr("name", that.options.startField);
            }

            if (that.options.endField !== "") {
                that._endInput.attr(kendo.attr("bind"), "value: " + that.options.endField);
				that._endInput.attr("name", that.options.endField);
            }

            that._inputs = that._startInput.add(that._endInput);
        },

        _option: function(option, value) {
            var that = this,
                options = that.options,
                startDateInput = that._startDateInput,
                endDateInput = that._endDateInput;

            if (value === undefined) {
                return options[option];
            }

            value = parse(value, options.parseFormats, options.culture);

            if (!value) {
                return;
            }

            options[option] = new Date(+value);
            that.dateView[option](value);

            if (startDateInput) {
                startDateInput[option](value);
            }

            if (endDateInput) {
                endDateInput[option](value);
            }
        },

        _reset: function() {
            var that = this,
                element = that.element,
                formId = element.attr("form"),
                form = formId ? $("#" + formId) : element.closest("form");

            if (form[0]) {
                that._resetHandler = function() {
                    that.max(that._initialOptions.max);
                    that.min(that._initialOptions.min);
                };

                that._form = form.on("reset", that._resetHandler);
            }
        },

        _editable: function(options) {
            var that = this,
                inputs = that._inputs,
                readonly = options.readonly,
                disable = options.disable;

            if (!readonly && !disable) {
                that.wrapper
                    .removeClass(STATEDISABLED);

                $.each(inputs, function(key, item) {
                    item.removeAttribute(DISABLED);
                    item.removeAttribute(READONLY);
                });

                inputs.attr(ARIA_DISABLED, false);
                that._preventInputAction = false;
            } else {
                that.wrapper
                    .addClass(disable ? STATEDISABLED : "")
                    .removeClass(disable ? "" : STATEDISABLED);

                    inputs.attr(DISABLED, disable)
                       .attr(READONLY, readonly)
                       .attr(ARIA_DISABLED, disable);

                that._preventInputAction = true;
            }
        },

        destroy: function() {
            var that = this;

            if (that._startDateInput) {
                that._startDateInput.unbind(CHANGE, that._startChangeHandler);
                that._startDateInput.destroy();
                that._startChangeHandler = null;
            }

            if (that._endDateInput) {
                that._endDateInput.unbind(CHANGE, that._endChangeHandler);
                that._endDateInput.destroy();
                that._endChangeHandler = null;
            }

            if (that._startDateButton) {
                that._startDateButton.off(CLICK, that._expandButtonClick);
            }

            if (that._endDateButton) {
                that._endDateButton.off(CLICK, that._expandButtonClick);
            }

            if (that._startDateClear) {
                that._startDateClear.off(CLICK, that._clearValue);
            }

            if (that._endDateClear) {
                that._endDateClear.off(CLICK, that._clearValue);
            }

            if (that._form) {
                that._form.off("reset", that._resetHandler);
            }

            that._inputs.off(ns);
            that._inputs = null;

            if (that.bigScreenMQL) {
                that.bigScreenMQL.destroy();
            }

            that._createDateViewProxy = null;

            that.dateView.destroy();

            that.element.off(ns);

            Widget.fn.destroy.call(that);
        },

        range: function(range) {
            let that = this, target = that._currentTarget || START;

            if (range === undefined) {
                return that._range;
            }

            that._range = range;
            that._range["target"] = target;
            that.dateView.range({ start: null, end: null, target: target });
            if (!range) {
                that._startDateInput.value(null);
                that._endDateInput.value(null);
            }

            if (!that._isAdaptive() || (that._isAdaptive() && that.options.autoClose)) {
                that._startDateInput.value(range.start ? range.start : null);
                that._endDateInput.value(range.end ? range.end : null);
            }

            if (target === START) {
                that.dateView.range({ start: range.start, end: range.end || null, target: target });
            }

            if (target === END) {
                that.dateView.range({ start: range.start || null, end: range.end, target: target });
            }
        },

        open: function() {
            this.dateView.open();
        },

        close: function() {
                this.dateView.close();
        },

        min: function(value) {
            return this._option(MIN, value);
        },

        max: function(value) {
            return this._option(MAX, value);
        },

        readonly: function(readonly) {
            this._startDateInput.readonly(readonly);
            this._endDateInput.readonly(readonly);

            this._editable({
                readonly: readonly === undefined ? true : readonly,
                disable: false
            });
        },

        enable: function(enable) {
            this._startDateInput.enable(enable);
            this._endDateInput.enable(enable);

            if (!enable) {
                this.close();
            }

            this._editable({
                readonly: false,
                disable: !(enable = enable === undefined ? true : enable)
            });
        }
    });

    kendo.ui.plugin(DateRangePicker);

})(window.kendo.jQuery);
export default kendo;
