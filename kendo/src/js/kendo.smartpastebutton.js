/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.core.js";
import "./kendo.data.js";
import "./kendo.button.js";
import { STYLES, EVENTS, NS } from "./smartpastebutton/constants.js";
import { parseValueForDateWidget, parseValueForTimeWidget, parseValueForDateTimeWidget, parseValueForArrayWidget } from "./smartpastebutton/utils.js";
import { KendoSmartPaste } from "@progress/kendo-smartpaste-common";

export const __meta__ = {
    id: "smartpastebutton",
    name: "SmartPasteButton",
    category: "web",
    description: "The SmartPasteButton widget is an extension of the Kendo UI Button that enables users to paste unstructured text have it intelligently distributed into relevant fields.",
    depends: ["core", "button", "data"]
};

(function($, undefined) {
    const kendo = window.kendo,
        ui = kendo.ui,
        Button = ui.Button,
        CLICK = "click";

    const SmartPasteButton = Button.extend({
        init: function(element, options) {
            const that = this;

            if (kendo.isPresent(options.text)) {
                $(element).text(options.text);
            }

            Button.fn.init.call(this, element, $.extend(
                {
                    icon: this.options.icon,
                    click: that._click.bind(that)
                },
                options)
            );

            that.smartPaste = new KendoSmartPaste({
                customInputs: [
                    { identifier: '.k-dropdownlist' },
                    { identifier: '.k-autocomplete' },
                    { identifier: '.k-multiselect' },
                    { identifier: '.k-combobox' },
                    { identifier: '.k-datepicker' },
                    { identifier: '.k-timepicker' },
                    { identifier: '.k-dateinput' },
                    { identifier: '.k-datetimepicker' },
                    { identifier: '.k-dropdowntree' },
                    { identifier: '.k-timepicker' },
                    { identifier: '.k-switch' },
                    { identifier: '.k-textarea' },
                    { identifier: '.k-radio-list' },
                    { identifier: '.k-checkbox-list' },
                    { identifier: '.k-rating' },
                    { identifier: '.k-maskedtextbox' },
                    { identifier: '.k-numerictextbox' },
                    { identifier: '.k-textbox' },
                    { identifier: '.k-checkbox' }
                ],
                getElement: that._getElement.bind(that),
                getSmartPasteField: that._extractFormFieldFromKendoInput.bind(that),
                setKendoInputValue: that._setKendoWidgetValue.bind(that)
            });

            that.element.addClass(STYLES.smartPasteButton);

            this._isListening = false;
            if (that.options.service && (kendo.isString(that.options.service) || that.options.service.url)) {
                that.transport = new kendo.data.AISmartPasteTransport({
                    service: that.options.service,
                    success: that._serviceSuccess.bind(that),
                    error: that._serviceError.bind(that)
                });
            }
        },

        options: {
            name: "SmartPasteButton",
            enable: true,
            cancelIcon: "x",
            icon: "paste-sparkle",
            fillMode: null,
            rounded: null,
            size: null,
            formFields: null,
            text: null,
            service: null
        },

        events: [
            EVENTS.requestStart,
            EVENTS.requestEnd,
            EVENTS.error
        ],

        setOptions: function(options) {
            let that = this;

            that.destroy();

            kendo.deepExtend(that.options, options);
            that.init(that.element, that.options);
        },

        destroy: function() {
            let that = this;

            that.element.off(NS);

            that.element.removeClass(STYLES.smartPasteButton);
            that.smartPaste = null;

            Button.fn.destroy.call(that);
        },

        _click: function(e) {
            const that = this,
                  form = that.smartPaste.formElement;

                if (!form) {
                    that.trigger(EVENTS.error, { error: 'No form found' });
                    return;
                }

                if (!that._isListening) {
                    if (!that.options.service || (!kendo.isString(that.options.service) && !that.options.service.url)) {
                        that.trigger(EVENTS.error, { error: 'Service must be defined!' });
                        return;
                    }
                    let formFields = that.options.formFields || that._extractFormFields(form);
                    if (kendo.isPresent(formFields) && !kendo.isEmpty(formFields)) {
                        const clipboardPromise = navigator.clipboard.readText();
                        that._requestService(formFields, clipboardPromise);
                    } else {
                        that.trigger(EVENTS.error, { error: 'No form fields found or extracted' });
                    }
                }
        },

        _getKendoFieldIdentifier(element) {
            let elementInstance = $(element);

            if (elementInstance.attr("data-role")) {
                return elementInstance.attr("id") || null;
            }
            else {
                const childElement = elementInstance.find("[data-role]");
                return $(childElement).attr("id") || null;
            }
        },

        _extractFormFields: function(form) {
            let that = this,
                formFields;

            try {
                formFields = that.smartPaste.extractFormConfig();
            } catch (error) {
                that.trigger(EVENTS.error, { error: 'Failed to extract form configuration: ' + error.message });
                return null;
            }

            if (!formFields || formFields.length === 0) {
                that.trigger(EVENTS.error, { error: 'No form fields could be extracted from the form' });
                return null;
            }

            return formFields;
        },

        _getElement: function() {
            let that = this;
            return $(that.element)[0] || null;
        },


        _setKendoWidgetValue: function(formField, value) {
            let widget = kendo.widgetInstance($(formField.element)),
                widgetName = widget?.options.name;

            if (widget && !!widget.value && (widgetName != "RadioButton")) {
                if (widgetName == "CheckBox") {
                    const checkboxGroupParent = $(formField.element).closest('.k-checkbox-list');
                    if (checkboxGroupParent.length > 0) {
                        return;
                    }
                }

                if (widgetName == "TimePicker")
                {
                    value = parseValueForTimeWidget(value);
                }
                else if (widgetName == "DateTimePicker")
                {
                    value = parseValueForDateTimeWidget(value);
                }
                else if (widgetName == "DatePicker" ||
                    widgetName == "DateInput")
                {
                    value = parseValueForDateWidget(value);
                }
                else if (widgetName == "MultiSelect" ||
                         widgetName == "CheckBoxGroup")
                {
                    value = parseValueForArrayWidget(value);
                }
                widget.value(value);
                $(widget.element).trigger("change");
            }
        },

        _extractFormFieldFromKendoInput: function(formField) {
            let that = this,
                element = formField.element;

            if ($(element).length) {
                if (!formField.field) {
                    let identifier = that._getKendoFieldIdentifier(element);
                    if (!identifier) {
                        formField = null;
                        return;
                    }
                    formField.field = identifier;
                }

                if ($(element).attr("id") == formField.field) {
                    return formField;
                }
                let widgetElement = $(element).find(`[id="${formField.field}"]`);
                formField.element = $(widgetElement)[0];
            }

            return formField;
        },

        _requestService: function(formFields, clipboardPromise) {
            const that = this;

            if (!kendo.isPresent(formFields) || kendo.isEmpty(formFields)) {
                return;
            }

            that._isListening = true;
            that._updateIcon();

            clipboardPromise
            .then(function(clipboardContent) {
                formFields = formFields.map(function(formField) {
                    return {
                        field: formField.field,
                        type: formField.type,
                        description: formField.description,
                        allowedValues: formField.allowedValues
                    };
                });

                let requestData = {
                    formFields: formFields,
                    content: clipboardContent
                };

                that.transport.read(requestData);
                that.trigger(EVENTS.requestStart, requestData);
            })
            .catch(function() {
                that._isListening = false;
                that._updateIcon();
            });
        },

        _serviceSuccess: function(response) {
            const that = this;

            // Check if smartPaste exists before accessing its properties
            if (!that.smartPaste) {
                that.trigger(EVENTS.error, { error: 'SmartPaste instance not available during service success' });
                return;
            }

            const form = that.smartPaste.formElement;

            if (!form) {
                that.trigger(EVENTS.error, { error: 'Form element not found during service success' });
                that._isListening = false;
                that._updateIcon();
                return;
            }

            let formFields = that._extractFormFields(form);

            if (!formFields) {
                // Error already triggered in _extractFormFields
                that._isListening = false;
                that._updateIcon();
                return;
            }

            try {
                that.smartPaste.populateFormFields(response, formFields);
            } catch (error) {
                that.trigger(EVENTS.error, { error: 'Failed to populate form fields: ' + error.message });
                that._isListening = false;
                that._updateIcon();
                return;
            }

            that._isListening = false;
            that._updateIcon();
            that.trigger(EVENTS.requestEnd, response);
        },

        _serviceError: function(e) {
            const that = this;

            that._isListening = false;
            that._updateIcon();

            const error = e && e.responseText || 'Unknown error';
            that.trigger(EVENTS.error, { error: error });
        },

        _setFormFieldValue: function(formField, value) {
            this.smartPaste.populateFormFields(formField, value);
        },

        _updateIcon: function() {
            let that = this,
                options = that.options;

            const iconElement = that.element.find(".k-icon, .k-svg-icon"),
                buttonText = that.element.find(".k-button-text"),
                newIcon = that._isListening ? this.options.cancelIcon : this.options.icon;

            if (buttonText.length && buttonText.text().trim().length > 0) {
                that._isListening ? $(buttonText).text("Cancel") : $(buttonText).text(options.text || "Smart Paste");
            }
            if (iconElement.length) {
                kendo.ui.icon(iconElement, { icon: newIcon });
            }
        }
    });

    kendo.cssProperties.registerPrefix("SmartPasteButton", "k-button-");

    ui.plugin(SmartPasteButton);
})(window.kendo.jQuery);

export default kendo;