/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../kendo.core.js";
import "../kendo.icons.js";
import "../kendo.textarea.js";
import "../kendo.button.js";
import "../kendo.panelbar.js";
import "../kendo.floatingactionbutton.js";
import "../kendo.skeletoncontainer.js";
import "./output-action-manager.js";
import "./template-builder.js";
import "./speech-manager.js";
import "./output-manager.js";

(function($) {
    let Widget = kendo.ui.Widget;
    const messageTypes = {
        "ai": "assistant",
        "system": "system",
        "user": "user",
        "tool": "tool"
    };

    const KDISABLED = "k-disabled";
    const CSS_CLASSES = {
        PROMPT_VIEW: "k-prompt-view",
        PROMPT_EXPANDER: "k-prompt-expander",
        SUGGESTION_GROUP: "k-suggestion-group",
        SUGGESTION: "k-suggestion",
        CARD: "k-card",
        CARD_LIST: "k-card-list",
    };

    const REFERENCE_ATTRIBUTES = {
        PROMPT_SUGGESTIONS_BUTTON: "ref-prompt-suggestions-button",
        PROMPT_INPUT: "ref-prompt-input",
        GENERATE_OUTPUT_BUTTON: "ref-generate-output-button",
        OUTPUT_CONTENT: "ref-output-content",
        STOP_GENERATION_BUTTON: "ref-stop-generation-button"
    };

    let AIPromptBaseView = kendo.ui.AIPromptBaseView = Widget.extend({
        init: function(element, options) {
            let that = this;

            Widget.fn.init.call(that, element, options);

            that.aiprompt = element.getKendoAIPrompt();

            that.contentElement = that.options.contentElement;
            that.footerElement = that.options.footerElement;
            that.buttonText = that.options.buttonText;
            that.buttonIcon = that.options.buttonIcon;
            that.service = that.options.service;
        },

        options: {
            name: "AIPromptBaseView",
            buttonText: "",
            buttonIcon: "",
        },

        render: function() {
            let that = this;

            that._renderContent();
            that._renderFooter();
        },

        _renderContentElement: function() {
            let that = this;
            let content = $("<div></div>").addClass("k-prompt-content");
            that.contentElement = content;
            that.element.append(content);

            return that.contentElement;
        },

        _renderFooterElement: function() {
            let that = this;
            let footer = $("<div></div>").addClass("k-prompt-footer");
            that.footerElement = footer;
            that.element.append(footer);

            return that.footerElement;
        },

        _ajaxRequest: function(prompt, isRetry, history) {
            let that = this;
            let service = that.service;
            let data = that._getAjaxData(prompt, isRetry, history);
            const url = typeof service === "string" ? service : service.url;
            const requestOptions = {
                url: url,
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify(data),
                success: (response) => that._ajaxSuccessHandler(response, isRetry, prompt),
            };

            if (service?.headers) {
                requestOptions.headers = service.headers;
            }
            kendo.ui.progress(that.contentElement, true);

            return $.ajax(requestOptions);
        },

        _ajaxSuccessHandler: function(response, isRetry, prompt) {
            const that = this;
            const outputGetter = that.service?.outputGetter || that._getResponseMessageText;
            const output = {
                id: kendo.guid(),
                output: outputGetter(response),
                prompt: prompt,
                isRetry: isRetry,
                activeView: 1
            };

            that.aiprompt.trigger("promptResponse", {
                output: output.output,
                prompt: output.prompt,
                outputId: output.id,
                isRetry: output.isRetry,
                response: output.response,
            });

            that.aiprompt.addPromptOutput(output);
            that.aiprompt.activeView(output.activeView);

            if (!isRetry) {
                const generateButton = that.footerElement?.find(`button[${REFERENCE_ATTRIBUTES.GENERATE_OUTPUT_BUTTON}]`);
                generateButton?.removeClass(KDISABLED);
            }

            kendo.ui.progress(that.contentElement, false);
        },

        _getResponseMessageText: function(response) {
            return response?.Message?.Text || "An error occurred while processing the request.";
        },

        _getAjaxData: function(prompt, isRetry, history) {
            const that = this;
            const service = that.service;
            let defaultData = [
                {
                    role: {
                        value: messageTypes.user
                    },
                    text: prompt
                }
            ];

            if (history?.length) {
                defaultData = history.concat(defaultData);
            }

            if (typeof service === "string") {
                return defaultData;
            }

            if (kendo.isPresent(service.data) && Object.keys(service.data).length) {
                service.data.messages = defaultData;
                return service.data;
            }

            if (kendo.isFunction(service?.data)) {
                return service.data(prompt, isRetry, history);
            }

            if ($.isPlainObject(service) && kendo.isPresent(service.url)) {
                return defaultData;
            }

            throw new Error("Invalid AIPrompt service configuration.");
        },

        destroy: function() {
            let that = this;
            Widget.fn.destroy.call(that);

            if (that.contentElement) {
                that.contentElement.off();
                kendo.destroy(that.contentElement);
                that.contentElement.remove();
            }

            if (that.footerElement) {
                that.footerElement.off();
                kendo.destroy(that.footerElement);
                that.footerElement.remove();
            }

            that.aiprompt.speechToTextButton = null;
        }
    });

    kendo.ui.AIPromptPromptView = AIPromptBaseView.extend({
        init: function(element, options) {
            let that = this;

            AIPromptBaseView.fn.init.call(that, element, options);
            that.promptSuggestions = that.options.promptSuggestions;
            that.promptSuggestionItemTemplate = that.options.promptSuggestionItemTemplate ?
                kendo.template(that.options.promptSuggestionItemTemplate) :
                kendo.ui.AIPromptTemplateBuilder.createSuggestionItem;

            // Initialize speech manager for speech-to-text functionality
            that.speechManager = new kendo.ui.AIPromptSpeechManager(that, that.options.speechToText);
        },
        options: {
            name: "AIPromptPromptView",
            buttonIcon: "sparkles",
        },

        _renderContent: function() {
            let that = this;
            let suggestions = that.promptSuggestions;
            let promptSuggestionItemTemplate = that.promptSuggestionItemTemplate;

            let content;
            if (that.options.viewTemplate) {
                content = kendo.template(that.options.viewTemplate)({
                    suggestions,
                    promptSuggestionItemTemplate,
                    messages: that.options.messages
                });
            } else {
                content = kendo.ui.AIPromptTemplateBuilder.createPromptView({
                    suggestions,
                    promptSuggestionItemTemplate,
                    messages: that.options.messages
                });
            }

            that._renderContentElement();
            that.contentElement.append(content);
        },

        _renderFooter: function() {
            let that = this;

            let footer;
            if (that.options.footerTemplate) {
                footer = kendo.template(that.options.footerTemplate)({ messages: that.options.messages });
            } else {
                footer = kendo.ui.AIPromptTemplateBuilder.createPromptFooter({ messages: that.options.messages });
            }

            that._renderFooterElement();
            that.footerElement.append(footer);
        },

        setTextAreaValue: function(value) {
            let that = this;
            const textareaWidget = that.contentElement.find(`textarea[${REFERENCE_ATTRIBUTES.PROMPT_INPUT}]`).getKendoTextArea();
            if (textareaWidget) {
                textareaWidget.value(value);
            } else {
                that.contentElement.find(`textarea[${REFERENCE_ATTRIBUTES.PROMPT_INPUT}]`).val(value);
            }
        },

        _focusSuggestion(element) {
            let that = this;
            if (!element || !element.length) {
                return;
            }

            that.contentElement.find(`.${CSS_CLASSES.SUGGESTION_GROUP} .${CSS_CLASSES.SUGGESTION}[tabindex=0]`).attr("tabindex", "-1");

            element.attr("tabindex", "0").trigger("focus");
        },

        startSpeechRecognition: function() {
            let that = this;
            that.speechManager.startRecognition();
        },

        stopSpeechRecognition: function() {
            let that = this;
            that.speechManager.stopRecognition();
        },

        abortSpeechRecognition: function() {
            let that = this;
            that.speechManager.abortRecognition();
        },

        isSpeechListening: function() {
            let that = this;
            return that.speechManager.isListening();
        },

        initializeComponents: function() {
            let that = this;
            let suggestions = that.promptSuggestions;
            const generateButton = that.footerElement.find(`button[${REFERENCE_ATTRIBUTES.GENERATE_OUTPUT_BUTTON}]`);

            // Initialize regular TextArea with speech manager configuration
            let textAreaOptions = $.extend({
                resize: "vertical",
                placeholder: that.options.messages.promptPlaceholder
            }, that.options.promptTextArea || {});

            // Add speech-to-text button suffix if enabled
            if (that.speechManager.isEnabled()) {
                textAreaOptions = $.extend(true, textAreaOptions, that.speechManager.getTextAreaSuffixOptions());
            }

            // Initialize the textarea as a regular Kendo TextArea
            const textareaWidget = that.contentElement.find(`textarea[${REFERENCE_ATTRIBUTES.PROMPT_INPUT}]`).kendoTextArea(textAreaOptions).getKendoTextArea();

            // Initialize speech-to-text button if enabled
            if (that.speechManager.isEnabled()) {
                that.speechManager.initialize(textareaWidget);
            }

            generateButton.kendoButton({
                icon: "sparkles",
                themeColor: "primary",
                rounded: "full",
                click: function(e) {
                    const textareaWidget = that.contentElement.find(`textarea[${REFERENCE_ATTRIBUTES.PROMPT_INPUT}]`).getKendoTextArea();
                    const prompt = textareaWidget ? textareaWidget.value() : that.contentElement.find(`textarea[${REFERENCE_ATTRIBUTES.PROMPT_INPUT}]`).val();
                    const eventArgs = { prompt, isRetry: false, history: [] };

                    if (that.service) {
                        eventArgs.service = that.service;
                    }

                    if (that.aiprompt.trigger("promptRequest", eventArgs)) {
                        return;
                    }

                    if (that.service) {
                        that.aiprompt.transport.read({ prompt: eventArgs.prompt, history: eventArgs.history, isRetry: false, service: that.service });
                    }
                }
            });

            if (suggestions?.length) {
                that.contentElement.find(`.${CSS_CLASSES.SUGGESTION_GROUP} .${CSS_CLASSES.SUGGESTION}`).first().attr("tabindex", "0");
                let nextExpanderContentId = kendo.guid();
                let expanderButton = that.contentElement.find(`.${CSS_CLASSES.PROMPT_EXPANDER} button[${REFERENCE_ATTRIBUTES.PROMPT_SUGGESTIONS_BUTTON}]`);

                that.contentElement.find(`.${CSS_CLASSES.PROMPT_EXPANDER} button[${REFERENCE_ATTRIBUTES.PROMPT_SUGGESTIONS_BUTTON}]`).attr("aria-controls", nextExpanderContentId);
                expanderButton.next(`.${CSS_CLASSES.PROMPT_EXPANDER_CONTENT}`).attr("id", nextExpanderContentId);

                that.contentElement.find(`.${CSS_CLASSES.PROMPT_EXPANDER} button[${REFERENCE_ATTRIBUTES.PROMPT_SUGGESTIONS_BUTTON}]`).kendoButton({
                    icon: "chevron-up",
                    fillMode: "flat",
                    click: function(e) {
                        let expander = $(e.target).closest(".k-prompt-expander");
                        let content = expander.find(".k-prompt-expander-content");
                        let iconEl = e.sender.element.find(".k-icon");
                        kendo.ui.icon(iconEl, content.is(":visible") ? "chevron-down" : "chevron-up");
                        content.toggle();
                        e.sender.element.attr("aria-expanded", content.is(":visible"));
                    }
                });

                that.contentElement.on("click", ".k-suggestion-group .k-suggestion", function(e) {
                    that.setTextAreaValue($(e.target).text());
                });

                that.contentElement.on("keydown", ".k-suggestion-group .k-suggestion", function(e) {
                    if (e.keyCode === 40 || e.keyCode === 38 || e.keyCode === 36 || e.keyCode === 35 || e.keyCode === 13 || e.keyCode === 32) {
                        e.preventDefault();
                        let target = $(e.target);
                        let siblings = target.siblings();
                        let next, prev;

                        // down arrow
                        if (e.keyCode === 40) {
                            next = target.next();
                            that._focusSuggestion(next);
                        }

                        // up arrow
                        if (e.keyCode === 38) {
                            prev = target.prev();
                            that._focusSuggestion(prev);
                        }

                        // home
                        if (e.keyCode === 36) {
                            prev = siblings.first();
                            that._focusSuggestion(prev);
                        }

                        // end
                        if (e.keyCode === 35) {
                            next = siblings.last();
                            that._focusSuggestion(next);
                        }

                        // enter or space
                        if (e.keyCode === 13 || e.keyCode === 32) {
                            that.setTextAreaValue($(e.target).text());
                        }
                    }
                });

            }

            if (kendo.isFunction(that.options.initializeComponents)) {
                that.options.initializeComponents({ view: that });
            }
        },

        render: function() {
            let that = this;

            that._renderContent();
            that._renderFooter();
            that.initializeComponents();
        },

        destroy: function() {
            let that = this;

            if (that.speechManager) {
                that.speechManager.destroy();
                that.speechManager = null;
            }

            AIPromptBaseView.fn.destroy.call(that);
        }
    });

    kendo.ui.AIPromptOutputView = AIPromptBaseView.extend({
        init: function(element, options) {
            let that = this;

            AIPromptBaseView.fn.init.call(that, element, options);

            that.promptOutputs = (that.aiprompt && that.aiprompt.promptOutputs) ? that.aiprompt.promptOutputs : [];

            that.showOutputRating = that.options.showOutputRating;
            that.isStreaming = that.options.isStreaming || false;
            that.outputActions = that.options.outputActions;
            that.outputTemplate = that.options.outputTemplate;

            that.outputActionManager = that.options.outputActionManager;
        },

        options: {
            name: "AIPromptOutputView",
            buttonIcon: "comment",
            isStreaming: false,
            promptOutputs: []
        },

        startStreaming: function() {
            let that = this;
            // Set view-level streaming state
            that.isStreaming = true;

            // Show stop generation button
            that._showStopButton();
        },

        stopStreaming: function() {
            let that = this;
            // Set view-level streaming state
            that.isStreaming = false;

            // Hide stop generation button
            that._hideStopButton();
        },

        _showStopButton: function() {
            let that = this;

            if (!that.stopGenerationButton) {
                if (that._initStopGenerationButton()) {
                    that.stopGenerationButton.show();
                }
            } else {
                that.stopGenerationButton.show();
            }
        },

        _hideStopButton: function() {
            let that = this;

            if (that.stopGenerationButton) {
                that.stopGenerationButton.hide();
            }
        },

        renderPromptOutput: function(output) {
            let that = this;
            let showOutputRating = that.options.showOutputRating;
            let encodedPromptOutputs = that.options.encodedPromptOutputs;
            let messages = that.options.messages;
            let isStreaming = that.isStreaming || false; // Use view-level streaming state
            let outputActions = that.outputActions;

            // Ensure cardListContainer exists
            if (!that.cardListContainer || that.cardListContainer.length === 0) {
                if (that.outputsContainer) {
                    that.cardListContainer = that.outputsContainer.find('.k-card-list');
                }
                if (!that.cardListContainer || that.cardListContainer.length === 0) {
                    return;
                }
            }

            // Initialize floating action button if output is streaming and button doesn't exist
            if (isStreaming && !that.stopGenerationButton) {
                that._initStopGenerationButton();
            }

            // Check if we have an enhanced output object from the main widget
            const outputObj = that.aiprompt.outputObjects.get(output.id);

            if (outputObj) {
                // Create the card HTML directly using the template builder
                const cardHtml = kendo.ui.AIPromptTemplateBuilder.createOutputCard({
                    output,
                    showOutputRating,
                    messages,
                    showOutputSubtitleTooltip: true,
                    encodedPromptOutputs,
                    isStreaming,
                    outputActions,
                    outputTemplate: that.outputTemplate
                });

                // Create jQuery element and store references in output object
                const card = $(cardHtml);
                outputObj._element = card;
                outputObj._bodyElement = card.find(`.k-card-body`);

                // Append to the card list container
                that.cardListContainer.prepend(card);

                // Handle initial state based on loading status
                if (output.isLoading) {
                    outputObj.showSkeleton();
                } else if (output.output) {
                    outputObj.applyFinalTemplate();
                }

                that.initializeComponents(card);
            } else {
                // Fallback to original rendering method
                let card = $(kendo.ui.AIPromptTemplateBuilder.createOutputCard({
                    output,
                    showOutputRating,
                    messages,
                    showOutputSubtitleTooltip: true,
                    encodedPromptOutputs,
                    isStreaming,
                    outputActions,
                    outputTemplate: that.outputTemplate
                }));
                that.cardListContainer.prepend(card);
                that.initializeComponents(card);
            }
        },

        updatePromptOutputContent: function(outputId, content) {
            let that = this;

            // Use the main widget's output object for streamlined management
            const outputObj = that.aiprompt.outputObjects.get(outputId);

            if (outputObj) {
                outputObj.updateContent(content);
            }
        },

        _initStopGenerationButton: function() {
            let that = this;
            let contentElement = that.contentElement;

            // Ensure contentElement exists and is in DOM
            if (!contentElement || contentElement.length === 0) {
                return false;
            }

            // Check if button already exists
            if (that.stopGenerationButton) {
                return true;
            }

            let stopFab = $("<button class='k-prompt-stop-fab k-generating'></button>");
            stopFab.attr({
                "aria-label": that.options.messages.stopGeneration,
                "title": that.options.messages.stopGeneration
            });

            contentElement.prepend(stopFab);

            that.stopGenerationButton = stopFab.kendoFloatingActionButton({
                _classNames: ["k-prompt-stop-fab", "k-generating"],
                icon: "stop-sm",
                positionMode: "absolute",
                align: "bottom end",
                rounded: "full",
                click: function(e) {
                    // Stop streaming and trigger cancel event
                    that.stopStreaming();
                    that.aiprompt.trigger("promptRequestCancel", {});
                }
            }).getKendoFloatingActionButton();

            that.stopGenerationButton.hide();
            return true;
        },
        _renderContent: function() {
            let that = this;
            let promptOutputs = that.promptOutputs;
            let showOutputRating = that.options.showOutputRating;
            let showOutputSubtitleTooltip = that.options.showOutputSubtitleTooltip;
            let messages = that.options.messages;
            let encodedPromptOutputs = that.options.encodedPromptOutputs;
            let outputActions = that.outputActions;

            let outputsContainer;
            if (that.viewTemplate) {
                outputsContainer = kendo.template(that.viewTemplate)({
                    promptOutputs, showOutputRating, messages, showOutputSubtitleTooltip,
                    encodedPromptOutputs, outputActions, outputTemplate: that.outputTemplate
                });
            } else {
                outputsContainer = kendo.ui.AIPromptTemplateBuilder.createOutputView({
                    promptOutputs, showOutputRating, messages, showOutputSubtitleTooltip,
                    encodedPromptOutputs, outputActions, outputTemplate: that.outputTemplate
                });
            }

            that.outputsContainer = $(outputsContainer);
            that.cardListContainer = that.outputsContainer.find('.k-card-list');
            that._renderContentElement();
            that.contentElement.append(that.outputsContainer);

            that._initStopGenerationButton();
        },

        initializeComponents: function(parentElement) {
            let that = this;
            parentElement = parentElement || that.contentElement;

            // Use the output action manager to handle all button initialization
            that.outputActionManager.initializeButtons(parentElement, that.outputActions);

            // Handle CSP-compliant styling for loading content
            parentElement.find('[data-loading="true"]').hide();
            parentElement.find('[data-loading="false"]').show();

            // Update DOM references for existing output objects after template rendering
            // and ensure buttons are initialized for all cards
            if (that.aiprompt && that.aiprompt.outputObjects) {
                that.aiprompt.outputObjects.forEach((outputObj, outputId) => {
                    // Find the corresponding DOM element for this output
                    const cardElement = parentElement.find(`.k-card[data-id="${outputId}"]`);
                    if (cardElement.length > 0) {
                        // Update the output object's DOM references
                        outputObj._element = cardElement;
                        outputObj._bodyElement = cardElement.find('.k-card-body');

                        // Apply custom output template for existing outputs when switching views
                        if (that.outputTemplate && typeof that.outputTemplate === 'function' &&
                            outputObj.data && outputObj.data.output && !outputObj.data.isLoading) {
                            const customContent = that.outputTemplate({
                                output: outputObj.data,
                                content: outputObj.data.output
                            });
                            outputObj._bodyElement.html(customContent);
                        }

                        // Initialize buttons for this specific card if not already initialized
                        // Check if buttons are already initialized by looking for kendoButton data
                        const buttons = cardElement.find('.k-button');
                        const hasInitializedButtons = buttons.length > 0 && buttons.first().data('kendoButton');
                        if (!hasInitializedButtons && buttons.length > 0) {
                            // Initialize buttons for this specific card only
                            that.outputActionManager.initializeButtons(cardElement, that.outputActions);
                        }
                    }
                });
            }
        },

        _initializeCardButtons: function(cardElement) {
            let that = this;
            // Delegate all button initialization to the output action manager
            that.outputActionManager.initializeButtons(cardElement, that.outputActions);
        },

        render: function() {
            let that = this;
            that._renderContent();
            that.initializeComponents();

            that.contentElement.on("keydown", ".k-card", function(e) {
                let target = $(e.target);

                // if up or down arrow, focus next or previous card
                // if home or end, focus first or last card
                if (e.keyCode === 40 || e.keyCode === 38 || e.keyCode === 36 || e.keyCode === 35) {
                    e.preventDefault();

                    // down arrow
                    if (e.keyCode === 40) {
                        target.next(".k-card").trigger("focus");
                    }

                    // up arrow
                    if (e.keyCode === 38) {
                        target.prev(".k-card").trigger("focus");
                    }

                    // home
                    if (e.keyCode === 36) {
                        that.contentElement.find(".k-card").first().trigger("focus");
                    }

                    // end
                    if (e.keyCode === 35) {
                        that.contentElement.find(".k-card").last().trigger("focus");
                    }
                }
            });
        },

        destroy: function() {
            let that = this;


            // Call parent destroy
            AIPromptBaseView.fn.destroy.call(that);
        }
    });

    kendo.ui.AIPromptCommandsView = AIPromptBaseView.extend({
        options: {
            name: "AIPromptCommandsView",
            buttonText: "",
            buttonIcon: "more-horizontal",
            promptCommands: []
        },

        initializeComponents: function() {
            let that = this;
            let commandItems = that.options.promptCommands;

            let panelBarEl = $("<div></div>").kendoPanelBar({
                animation: false,
                dataSource: commandItems,
                selectable: false,
                select: function(ev) {
                    let item = $(ev.item);
                    let dataItem = this.dataItem(item);
                    if (dataItem.hasChildren) {
                        return;
                    }

                    that.aiprompt.trigger("commandExecute", { sender: that.aiprompt, item: dataItem });
                }
            });
            const promptViewWrapper = $("<div class='k-prompt-view'>");
            promptViewWrapper.append(panelBarEl);

            that.contentElement.append(promptViewWrapper);
        },

        render: function() {
            let that = this;
            that._renderContentElement();
            that.initializeComponents();
        },
    });

    let EMPTY_TEMPLATE = () => "";
    kendo.ui.AIPromptCustomView = AIPromptBaseView.extend({
        options: {
            name: "AIPromptCustomView",
            buttonText: "",
            buttonIcon: "",
            viewTemplate: EMPTY_TEMPLATE,
            footerTemplate: EMPTY_TEMPLATE,
        },

        initializeComponents: function() {
            let that = this;
            if (typeof that.options.initializeComponents === "function") {
                that.options.initializeComponents.call(that);
            }
        },

        _renderContent: function() {
            let that = this;
            let content = kendo.template(that.options.viewTemplate)({ aiprompt: that });

            that._renderContentElement();
            that.contentElement.append(content);
        },

        _renderFooter: function() {
            let that = this;
            if (that.options.footerTemplate === EMPTY_TEMPLATE) {
                return;
            }

            let footer = kendo.template(that.options.footerTemplate)({ messages: that.options.messages });

            that._renderFooterElement();
            that.footerElement.append(footer);
        },
        render: function() {
            let that = this;
            that._renderContent();
            that._renderFooter();
            that.initializeComponents();
        },
    });

})(window.kendo.jQuery);
export default kendo;

