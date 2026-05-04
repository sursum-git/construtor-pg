/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.core.js";
import "./kendo.textbox.js";

export const __meta__ = {
    id: "otpinput",
    name: "OTPInput",
    category: "web",
    description: "The OTPInput widget provides a rich input for selecting values or ranges of values.",
    depends: ["core", "textbox"]
};

(function($, undefined) {
    var kendo = window.kendo,
        Widget = kendo.ui.Widget,
        TextBox = kendo.ui.TextBox,
        HIDDEN = "k-hidden",
        ui = kendo.ui,
        keys = kendo.keys,
        KOTP = "k-otp",
        OTPINPUT = "k-otp-input",
        OTPSEPARATOR = "k-otp-separator",
        INPUT = "k-input-inner",
        NS = ".kendoOtpInput",
        CHANGE = "change",
        INPUTGROUP = "k-input-group",
        STATEINVALID = "k-invalid",
        INTEGER_REGEXP = /^[0-9]\d*$/,
        ARIA_HIDDEN = "aria-hidden",
        ARIA_LABEL = "aria-label",
        ROLE = "role",
        FOCUS_RIGHT = "right",
        FOCUS_LEFT = "left",
        FOCUS = "focus";


        var OTPInput = Widget.extend({
            init: function(element, options) {
                var that = this;

                Widget.fn.init.call(that, element, options);
                options = $.extend(true, {}, options);

                that.options.value = options.value || that.element.val();
                that.options.separator = kendo.isPresent(options.separator) ? options.separator : null;
                that.options.inputMode = options.inputMode || $(element).attr("inputmode") || "text";

                that._wrapper();
                that._renderGroups(that.options.items);
                that._attachHandlers();
                that.value(that.options.value);

                that.element.on(CHANGE + NS, that._change.bind(that));
            },

            events: [
                CHANGE
            ],

            options: {
                name: "OTPInput",
                type: "text",
                value: '',
                placeholder: "",
                separator: null,
                readonly: false,
                enable: true,
                space: false,
                size: undefined,
                rounded: undefined,
                fillMode: undefined
            },
            enable: function(enable) {
                let that = this,
                    inputs = that._inputs;

                if (!kendo.isEmpty(inputs)) {
                    $(inputs).each(function()
                    {
                        let widget = this.getKendoTextBox();
                        widget.enable(enable);
                    });
                }
            },
            readonly: function(readonly) {
                let that = this,
                    inputs = that._inputs;

                if (!kendo.isEmpty(inputs)) {
                    $(inputs).each(function()
                    {
                        let widget = this.getKendoTextBox();
                        widget.readonly(readonly);
                    });
                }
            },
            focus: function() {
                let that = this,
                    inputs = that._inputs;

                if (!kendo.isEmpty(inputs)) {
                    inputs[0]
                        .focus()
                        .select();
                }
            },
            value: function(value) {
                let that = this,
                    groupLength = that._groupLength,
                    type = that.options.type;

                if (value === undefined) {
                    return that._value;
                }

                if (value === null) {
                    that.element.val(value);
                    that._value = value;
                    that._emptyValues();
                }

                if (groupLength > 0 && value) {
                    that._emptyValues();

                    if (!that._validate(type, value)) {
                        return;
                    }

                    let values = value.toString().split("");

                    if (values.length > groupLength) {
                        values = values.splice(0, groupLength);
                    }

                    that._inputs.forEach(function(input, index) {
                        let value = values.at(index);
                        $(input)
                            .val(value);
                    });

                    let newValue = '';

                    that._inputs.forEach(function(input) {
                        newValue = newValue += $(input).val();
                    });

                    that.element.val(newValue);
                    that._value = newValue;
                }
            },
            destroy: function() {
                let that = this,
                    inputs = that._inputs;

                if (!kendo.isEmpty(inputs)) {
                    inputs.forEach(function(input) {
                        let element = $(input)[0],
                            textBox = $(element).getKendoTextBox();

                        textBox.destroy();
                    });

                    let inputWrappers = inputs
                        .map(function(input) {
                            return $(input)[0]
                                .closest(`.${OTPINPUT}`);
                        });

                    inputWrappers.forEach(function(element) {
                        $(element).remove();
                    });

                    that.wrapper
                        .find(`.${INPUTGROUP}`)
                        .remove();

                    that.wrapper
                        .find(`.${OTPSEPARATOR}`)
                        .remove();

                    that.element
                        .unwrap()
                        .removeClass(HIDDEN)
                        .removeAttr("type")
                        .removeAttr("aria-hidden");

                    Widget.fn.destroy.call(that);
                }
            },
            setOptions: function(options) {
                let that = this;

                that.destroy();

                kendo.deepExtend(that.options, options);
                that.init(that.element, that.options);
            },
            _change: function(e) {
                let that = this,
                    newValue = that.element.val();

                that._value = newValue;
                that.trigger(CHANGE, { value: newValue });
            },
            _wrapper: function() {
                let that = this,
                    element = that.element,
                    wrapper;

                let sizeClass = kendo.cssProperties.defaultValues['size'][that.options.size];
                let sizeClassStr = sizeClass ? ` k-otp-${sizeClass}` : "";

                wrapper = element.addClass(HIDDEN)
                       .attr("type", "hidden")
                       .attr(ARIA_HIDDEN, true)
                       .wrap(`<div class='k-otp${sizeClassStr}' ${ROLE}="group" ></div>`)
                       .parent();

                that.wrapper = wrapper;
            },
            _renderGroups: function(items) {
                let that = this,
                    separator = that.options.separator,
                    inputs = [];

                that._inputs = [];

                if (kendo.isInteger(items)) {
                    that._groupLength = items;
                    for (let i = 0; i < items; i++) {
                        let input = that._renderInput(i + 1);
                        inputs.push(input);
                        that._inputs.push($(input).find(`.${INPUT}`));
                    }

                    let space = that.options.space;

                    if (!space) {
                        let group = $(`<div class="${INPUTGROUP}"></div>`)
                        .append(inputs);

                        that.wrapper.append(group);
                    } else {
                        that.wrapper.append(inputs);
                    }

                    inputs = [];
                } else if (Array.isArray(items)) {
                    that._groupLength = items.reduce((n, { groupLength }) => n + groupLength, 0);

                    let otpCharacterCounter = 0;
                    items.forEach(function(item, index) {
                        if (item.groupLength) {
                            for (let i = 0; i < item.groupLength; i++) {
                                ++otpCharacterCounter;
                                let input = that._renderInput(otpCharacterCounter);
                                inputs.push(input);
                                that._inputs.push($(input).find(`.${INPUT}`));
                            }

                            let space = that.options.space;

                            if (!space) {
                                let group = $(`<div class="${INPUTGROUP}"></div>`)
                                    .append(inputs);

                                that.wrapper.append(group);

                                if (kendo.isPresent(separator) && !(index == items.length - 1)) {
                                    that.wrapper.append(that._renderSeparator());
                                }
                            } else {
                                if (!(index == items.length - 1)) {
                                    inputs.push(that._renderSeparator());
                                }
                                that.wrapper.append(inputs);
                            }
                            inputs = [];
                        }
                    });
                    otpCharacterCounter = 0;
                }
            },
            _renderInput: function(index) {
                let that = this,
                    inputMode = that.options.inputMode,
                    input;

                input = $(`<input>`)
                        .addClass(INPUT)
                        .attr("inputmode", inputMode)
                        .attr("type", that.options.type == "password" ? "password" : "text")
                        .attr(ARIA_LABEL, that.options.type == "numeric" ? `Enter Otp Numerical Character ${index}` : `Enter Otp Character ${index}`)
                        .attr("placeholder", that.options.placeholder)
                        .attr("autocomplete", "off")
                        .attr("maxlength", 1);


                let options = $.extend({}, {
                    maxLength: 1,
                    size: that.options.size,
                    rounded: that.options.rounded,
                    fillMode: that.options.fillMode,
                    enable: that.options.enable,
                    readonly: that.options.readonly
                });


                let widget = new TextBox(input, options),
                    widgetWrapper = widget.wrapper;

                widgetWrapper
                    .removeClass(INPUT)
                    .addClass(OTPINPUT);

                return widgetWrapper;
            },
            _renderSeparator: function() {
                let that = this,
                    separator = that.options.separator;

                    let separatorWrapper = $(`<div class="${OTPSEPARATOR}"></div>`);

                    if (kendo.isPresent(separator)) {
                        kendo.isFunction(separator) ? separatorWrapper.append(separator()) : separatorWrapper.append(separator);
                    }

                    return separatorWrapper;
            },
            _input: function(e) {
                let that = this,
                    input = e.currentTarget,
                    groupLength = that._inputs.length,
                    oldValue = that._oldValue,
                    value = e.currentTarget.value,
                    type = that.options.type;

                if (!that._validate(type, value)) {
                    that._blinkInvalidState(input);

                    $(input).val('');

                    if (oldValue != undefined) {
                        $(input).val(oldValue);
                        delete that._oldValue;
                    }

                    $(input).trigger(FOCUS);

                    return;
                }

                let inputGroups = $(that.wrapper).find(`.${OTPINPUT}`),
                    focusedElement = inputGroups.find(":focus"),
                    currentIndex = inputGroups.find(`.${INPUT}`).index(focusedElement),
                    itemToFocus;

                    let newValue = that._inputs
                        .map(function(item) {
                            return $(item).val();
                        })
                        .join('');

                    if (newValue != that._value) {
                        that.element.val('');
                        that.element.val(newValue);
                        that._value = newValue;

                        that.trigger(CHANGE, { value: newValue });
                    }

                    that._moveFocus(itemToFocus, currentIndex, groupLength, FOCUS_RIGHT);
            },
            _attachHandlers: function() {
                let that = this,
                    inputs = that._inputs,
                    inputSelector = `.${INPUT}`;

                if (!kendo.isEmpty(inputs)) {
                    that.wrapper
                        .on("focus" + NS, inputSelector, that._focus.bind(that))
                        .on("beforeinput" + NS, inputSelector, that._beforeInput.bind(that))
                        .on("input" + NS, inputSelector, that._input.bind(that))
                        .on("keydown" + NS, inputSelector, that._keyDown.bind(that))
                        .on("paste" + NS, inputSelector, that._paste.bind(that));
                }

            },
            _beforeInput: function(e) {
                let that = this,
                    input = e.currentTarget;

                if (input && $(input).val() != undefined) {
                    let value = $(input).val();
                    that._oldValue = value;
                }
            },
            _focus: function(e) {
                let element = e.currentTarget;

                if (element) {
                    $(element).select();
                }
            },
            _validate: function(type, value) {
                if (type == "number") {
                    return INTEGER_REGEXP.test(value);
                }

                return true;
            },
            _paste: function(e) {
                let that = this,
                    inputs = that._inputs,
                    readonly = that.options.readonly,
                    isRtl = kendo.support.isRtl(that.element),
                    inputGroups = $(that.wrapper).find(`.${OTPINPUT}`),
                    type = that.options.type,
                    clipboardData = e.clipboardData || e.originalEvent.clipboardData || window.clipboardData,
                    pastedValue = clipboardData.getData('text'),
                    itemToFocus;

                e.preventDefault();
                if (!pastedValue || readonly == true) {
                    return;
                }

                if (!that._validate(type, pastedValue)) {
                    this._blinkInvalidState.bind(that);
                    this._blinkInvalidState(that.wrapper);
                    return;
                }

                that.value(pastedValue);

                that.trigger(CHANGE, { newValue: that._value });

                if (isRtl) {

                    if (that._value.length == that._inputs.length) {
                       itemToFocus = that._shiftFocus(inputGroups, 0, FOCUS_LEFT);
                    } else {
                        let emptyInputs = inputs
                             .map(function(input) {
                                 return $(input);
                             })
                             .filter(function(element) {
                                 let value = element.val();
                                 return value == '' || kendo.isBlank(value);
                             });

                         let emptyInput = $(emptyInputs)[0];

                         itemToFocus = $(emptyInput).closest(`.${OTPINPUT}`);
                     }

                     $(itemToFocus)
                        .find("input")
                        .trigger(FOCUS);
                } else {

                    if (that._value.length == that._inputs.length) {
                        itemToFocus = that._shiftFocus(inputGroups, inputGroups.length - 2, FOCUS_RIGHT);
                    } else {
                       let emptyInputs = inputs
                            .map(function(input) {
                                return $(input);
                            })
                            .filter(function(element) {
                                let value = element.val();
                                return value == '' || kendo.isBlank(value);
                            });

                        let emptyInput = $(emptyInputs).first()[0];

                        itemToFocus = $(emptyInput).closest(`.${OTPINPUT}`);
                    }

                    $(itemToFocus)
                        .find("input")
                        .trigger(FOCUS);
                }

            },
            _keyDown: function(e) {
                let that = this,
                    key = e.keyCode || e.which,
                    input = e.currentTarget,
                    groupLength = that._inputs.length,
                    inputGroups = $(that.wrapper).find(`.${OTPINPUT}`),
                    focusedElement = inputGroups.find(":focus"),
                    shouldTriggerChange = focusedElement.val() != '',
                    currentIndex = inputGroups.find(`.${INPUT}`).index(focusedElement),
                    isRtl = kendo.support.isRtl(that.element),
                    itemToFocus;

                if (e.target != input || e.ctrlKey || (key >= 112 && key <= 123) || (key >= 96 && key <= 111) || e.shiftKey && key == keys.TAB || key == keys.TAB) {
                    return;
                }

                if ((key === keys.LEFT && !isRtl) || (e.keyCode === keys.RIGHT && isRtl)) {
                    itemToFocus = that._shiftFocus(inputGroups, currentIndex, FOCUS_LEFT);

                    that._moveFocus(itemToFocus, currentIndex, groupLength, FOCUS_LEFT);

                    e.preventDefault();
                } else if ((key === keys.LEFT && isRtl) || (e.keyCode === keys.RIGHT && !isRtl)) {
                    itemToFocus = that._shiftFocus(inputGroups, currentIndex, FOCUS_RIGHT);

                    that._moveFocus(itemToFocus, currentIndex, groupLength, FOCUS_RIGHT);

                    e.preventDefault();
                } else if (key === keys.DELETE) {
                    e.preventDefault();
                    if (!$(focusedElement).is("[readonly]")) {
                        focusedElement.val('');

                        let newValue = that._inputs
                        .map(function(item) {
                            return $(item).val();
                        })
                        .join('');

                        if (shouldTriggerChange) {
                            that._value = newValue;
                            that.trigger(CHANGE, { value: newValue });
                        }

                        that.element.val('');
                        that.element.val(newValue);
                    }
                } else if ((key) == keys.BACKSPACE) {
                    e.preventDefault();

                    itemToFocus = that._shiftFocus(inputGroups, currentIndex, FOCUS_LEFT);

                    if (!$(focusedElement).is("[readonly]")) {
                        focusedElement.val('');

                        let newValue = that._inputs
                            .map(function(item) {
                                return $(item).val();
                            })
                            .join('');

                        if (shouldTriggerChange) {
                            that._value = newValue;
                            that.trigger(CHANGE, { value: newValue });
                        }

                        that.element.val('');
                        that.element.val(newValue);

                        if (currentIndex > 0 && !isRtl) {
                            itemToFocus.find("input").trigger(FOCUS);
                        } else if (currentIndex == inputGroups.length - 1 && isRtl) {
                            itemToFocus
                                .find("input")
                                .trigger(FOCUS);

                        } else {
                            if (currentIndex > 0 && !isRtl || currentIndex > 0 && isRtl) {
                                itemToFocus
                                    .find("input")
                                    .trigger(FOCUS);
                            }
                        }
                    }
                }
            },
            _shiftFocus: function(elements, currentIndex, direction) {
                switch (direction) {
                    case FOCUS_RIGHT:
                        return currentIndex + 1 === elements.length ? elements.eq(0) : $(elements[currentIndex + 1]);
                    case FOCUS_LEFT:
                        return currentIndex === 0 ? elements.eq(elements.length - 1) : $(elements[currentIndex - 1]);
                    default:
                        return null;
                }
            },
            _moveFocus: function(itemToFocus, currentIndex, groupLength, direction) {
                let that = this,
                    inputGroups = $(that.wrapper).find(`.${OTPINPUT}`);

                if (direction == FOCUS_RIGHT) {
                    if (currentIndex != groupLength - 1) {
                        itemToFocus = that._shiftFocus(inputGroups, currentIndex, FOCUS_RIGHT);
                        itemToFocus
                            .find("input")
                            .trigger(FOCUS);
                    }
                } else if (direction == FOCUS_LEFT) {
                    if (currentIndex > 0) {
                        itemToFocus = that._shiftFocus(inputGroups, currentIndex, FOCUS_LEFT);
                        itemToFocus
                            .find("input")
                            .trigger(FOCUS);
                    }
                }
            },
            _emptyValues: function() {
                let that = this;

                that._inputs.forEach(function(input) {
                    $(input)
                        .val('');
                });

            },
            _blinkInvalidState: function(element) {
                let that = this,
                    toWrapper = $(element).hasClass(KOTP);

                that._addInvalidState.bind(that);
                that._addInvalidState(element, toWrapper);

                clearTimeout(that._invalidStateTimeout);

                that._invalidStateTimeout = setTimeout(function() {
                    that._removeInvalidState.bind(that);
                    that._removeInvalidState(element, toWrapper);
                }, 100);
            },
            _addInvalidState: function(element, toWrapper = false) {
                let that = this,
                    inputs = that._inputs;

                if (!toWrapper) {
                    $(element)
                        .closest(`.${OTPINPUT}`)
                        .addClass(STATEINVALID);
                } else {
                    if (!kendo.isEmpty(inputs)) {
                        let inputWrappers = inputs
                            .map(function(input) {
                                return $(input).closest(`.${OTPINPUT}`);
                            });

                        $(inputWrappers).each(function() {
                            $(this).addClass(STATEINVALID);
                        });
                    }
                }
            },
            _removeInvalidState: function(toWrapper = false) {
                let that = this,
                    inputs = that._inputs;

                if (!toWrapper) {
                    $(that)
                    .closest(`.${OTPINPUT}`)
                    .removeClass(STATEINVALID);
                } else {
                    if (!kendo.isEmpty(inputs)) {

                        let inputWrappers = inputs
                        .map(function(input) {
                            return $(input).closest(`.${OTPINPUT}`);
                        });

                        $(inputWrappers).each(function() {
                            $(this).removeClass(STATEINVALID);
                        });

                    }
                }
                that._invalidStateTimeout = null;
            }
        });

        kendo.cssProperties.registerPrefix("OTPInput", "k-input-");

    ui.plugin(OTPInput);
})(window.kendo.jQuery);
export default kendo;