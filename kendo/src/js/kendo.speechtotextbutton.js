/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.core.js";
import "./kendo.button.js";
import { KendoSpeechRecognition } from '@progress/kendo-webspeech-common';

export const __meta__ = {
    id: "speechtotextbutton",
    name: "SpeechToTextButton",
    category: "web",
    description: "The SpeechToTextButton widget is an extension of the Kendo UI Button that allows for speech-to-text input.",
    depends: ["core", "button"]
};

(function($, undefined) {
    const kendo = window.kendo,
        ui = kendo.ui,
        Button = ui.Button;

    const SpeechToTextButton = Button.extend({
        init: function(element, options) {
            Button.fn.init.call(this, element, $.extend({ icon: this.options.icon }, options));
            $(element).addClass("k-speech-to-text-button").attr("aria-live", "polite");
            this._isListening = false;
            this._initSpeechRecognition();
            this._updateIcon();
        },

        _initSpeechRecognition: function() {
            if (this.options.integrationMode !== 'webSpeech') {
                return;
            }

            if (!KendoSpeechRecognition) {
                this.enable(false);
                return;
            }

            const self = this;
            this.speechRecognition = new KendoSpeechRecognition({
                lang: this.options.lang,
                interimResults: this.options.interimResults,
                maxAlternatives: this.options.maxAlternatives,
                continuous: this.options.continuous,
                events: {
                    start: function() { self._handleStart(); },
                    end: function() { self._handleEnd(); },
                    result: function(e) { self._handleResult(e); },
                    error: function(e) { self._handleError(e); }
                }
            });

            if (!this.speechRecognition.isSupported()) {
                this.enable(false);
                this.trigger("error", { error: this.options.messages.unsupported });
            }
        },

        _handleStart: function() {
            this._isListening = true;
            this.element.addClass("k-listening");
            this._updateIcon();
            this.trigger("start");
        },
        _handleEnd: function() {
            this._isListening = false;
            this.element.removeClass("k-listening");
            this._updateIcon();
            this.trigger("end");
        },
        _handleResult: function(e) {
            const results = e.results;
            const lastResult = results[results.length - 1];
            const alternatives = [];
            for (let i = 0; i < lastResult.length; i++) {
                alternatives.push({
                    transcript: lastResult[i].transcript,
                    confidence: lastResult[i].confidence
                });
            }
            this.trigger("result", {
                isFinal: lastResult.isFinal,
                alternatives: alternatives
            });
        },
        _handleError: function(e) {
            this._isListening = false;
            this.element.removeClass("k-listening");
            this._updateIcon();
            const error = e && (e.error || e.message) || 'Unknown error';
            this.trigger("error", { error: error });
        },

        options: {
            name: "SpeechToTextButton",
            icon: "microphone-outline",
            stopIcon: "stop-sm",
            integrationMode: "webSpeech",
            lang: 'en-US',
            continuous: false,
            interimResults: false,
            maxAlternatives: 1,
            messages: {
                unsupported: "Speech recognition is not supported in this browser.",
                notInitialized: "Speech recognition engine not initialized.",
                start: "Start speech recognition",
                stop: "Stop speech recognition"
            }
        },

        events: [
            "start",
            "end",
            "result",
            "error",
            "click"
        ],

        _updateIcon: function() {
            const iconElement = this.element.find(".k-icon, .k-svg-icon");
            const newIcon = this.isListening() ? this.options.stopIcon : this.options.icon;
            const newAriaLabel = this.isListening() ? this.options.messages.stop : this.options.messages.start;

            if (iconElement.length) {
                kendo.ui.icon(iconElement, { icon: newIcon });
            }

            this.element.attr("aria-label", newAriaLabel);
        },
        isListening: function() {
            return !!this._isListening;
        },

        _click: function(e) {
            Button.fn._click.call(this, e);

            if (e.isDefaultPrevented()) {
                return;
            }

            if (this.options.integrationMode === 'webSpeech') {
                if (!this.speechRecognition || !this.speechRecognition.isSupported()) {
                    this.trigger("error", { error: this.options.messages.unsupported });
                    return;
                }

                if (this.isListening()) {
                    this.speechRecognition.stop();
                } else {
                    this.speechRecognition.start();
                }
            } else if (this.options.integrationMode === 'none') {
                const eventName = this.isListening() ? "end" : "start";
                const eventData = {
                    sender: this
                };

                this.trigger(eventName, eventData);

                this._isListening = !this.isListening();
                this._updateIcon();
            }
        },

        startRecognition: function() {
            if (!this.options.enable) { return; }
            if (!this.speechRecognition) {
                this.trigger("error", { error: this.options.messages.notInitialized });
                return;
            }

            if (!this.speechRecognition.isSupported()) {
                this.trigger("error", { error: this.options.messages.unsupported });
                return;
            }

            if (!this.isListening()) {
                this.speechRecognition.start();
            }
        },

        stopRecognition: function() {
            if (this.speechRecognition && this.isListening()) {
                this.speechRecognition.stop();
            }
        },

        abortRecognition: function() {
            if (this.speechRecognition && this.isListening()) {
                this.speechRecognition.abort();
            }
        },

        destroy: function() {
            if (this.speechRecognition) {
                this.speechRecognition.stop();
                if (typeof this.speechRecognition.destroy === 'function') {
                    this.speechRecognition.destroy();
                }
                this.speechRecognition = null;
            }

            $(this.element).removeClass("k-speech-to-text-button");
            Button.fn.destroy.call(this);
        }
    });


    kendo.cssProperties.registerPrefix("SpeechToTextButton", "k-button-");
    kendo.cssProperties.registerValues("SpeechToTextButton", [{
        prop: "rounded",
        values: kendo.cssProperties.roundedValues.concat([['full', 'full']])
    }]);

    ui.plugin(SpeechToTextButton);
})(window.kendo.jQuery);

export default kendo;
