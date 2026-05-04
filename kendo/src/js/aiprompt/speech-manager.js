/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

// Speech-to-text management for AIPrompt
(function($) {

    class AIPromptSpeechManager {
        constructor(view, options = {}) {
            this.view = view;
            this.aiprompt = view.aiprompt;
            this.options = this._processSettings(options);
            this._speechButton = null;
        }

        _processSettings(speechToTextConfig) {
            const defaultOptions = {
                integrationMode: "webSpeech",
                lang: 'en-US',
                continuous: false,
                interimResults: false,
                maxAlternatives: 1
            };

            if (speechToTextConfig === false || speechToTextConfig === null) {
                return { enabled: false, options: null };
            } else if (speechToTextConfig === true) {
                return { enabled: true, options: defaultOptions };
            } else if (typeof speechToTextConfig === 'object') {
                return {
                    enabled: true,
                    options: $.extend({}, defaultOptions, speechToTextConfig)
                };
            }

            return { enabled: true, options: defaultOptions };
        }

        isEnabled() {
            return this.options.enabled;
        }

        getTextAreaSuffixOptions() {
            if (!this.isEnabled()) {
                return {};
            }

            return {
                suffixOptions: {
                    template: function() {
                        return '<button ref-speech-to-text-button title="Speech to Text"></button>';
                    },
                    separator: false,
                }
            };
        }

        initialize(textareaWidget) {
            if (!this.isEnabled() || !textareaWidget) {
                return false;
            }

            const speechButton = textareaWidget.wrapper.find("button[ref-speech-to-text-button]");
            if (speechButton.length === 0) {
                return false;
            }

            this._speechButton = speechButton.kendoSpeechToTextButton({
                ...this.options.options,
                fillMode: "flat"
            }).getKendoSpeechToTextButton();

            this._speechButton.bind("result", (e) => this._handleResult(e, textareaWidget));
            this.aiprompt.speechToTextButton = this._speechButton;
            return true;
        }

        _handleResult(e, textareaWidget) {
            if (e.isFinal || !this.options.options.interimResults) {
                const transcript = e.alternatives[0]?.transcript || '';
                const currentValue = textareaWidget.value();
                let newValue = currentValue ? currentValue + ' ' + transcript : transcript;
                const maxLength = textareaWidget.options.maxlength;

                if (maxLength && newValue.length > maxLength) {
                    newValue = newValue.substring(0, maxLength);
                }

                textareaWidget.value(newValue);
            }
        }

        startRecognition() {
            if (this._speechButton) {
                this._speechButton.startRecognition();
            }
        }

        stopRecognition() {
            if (this._speechButton) {
                this._speechButton.stopRecognition();
            }
        }

        abortRecognition() {
            if (this._speechButton) {
                this._speechButton.abortRecognition();
            }
        }

        isListening() {
            return this._speechButton ? this._speechButton.isListening() : false;
        }

        destroy() {
            if (this._speechButton) {
                this._speechButton.destroy();
                this._speechButton = null;
            }
        }
    }

    // Expose to kendo namespace
    kendo.ui.AIPromptSpeechManager = AIPromptSpeechManager;

})(window.kendo.jQuery);
