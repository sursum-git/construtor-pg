/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

// Button management abstraction for AIPrompt views
(function($) {
    const messageTypes = {
        "ai": "assistant",
        "system": "system",
        "user": "user",
        "tool": "tool"
    };

    class AIPromptOutputActionManager {
        constructor(aiprompt, options = {}) {
            this.aiprompt = aiprompt;
            this.options = options;
            this.buttonDefinitions = this._createButtonDefinitions();
        }

        static getBuiltInActionDefinitions() {
            return {
                copy: {
                    command: "copy",
                    icon: "copy",
                    fillMode: "flat",
                    themeColor: "primary",
                    text: null
                },
                retry: {
                    command: "retry",
                    icon: "arrow-rotate-cw",
                    fillMode: "flat",
                    text: null
                },
                ratePositive: {
                    command: "ratePositive",
                    icon: "thumb-up-outline",
                    fillMode: "flat",
                    iconButton: true,
                    text: null
                },
                rateNegative: {
                    command: "rateNegative",
                    icon: "thumb-down-outline",
                    fillMode: "flat",
                    iconButton: true,
                    text: null
                },
                stop: {
                    command: "stop",
                    icon: "stop",
                    fillMode: "flat",
                    text: null
                }
            };
        }

        static processOutputActions(actions) {
            if (!actions) {
                return null;
            }

            const builtInActions = AIPromptOutputActionManager.getBuiltInActionDefinitions();

            return actions.map(action => {
                if (typeof action === "string") {
                    if (action === "rating") {
                        return [builtInActions.ratePositive, builtInActions.rateNegative];
                    } else if (action === "spacer") {
                        return { type: "spacer" };
                    }

                    return builtInActions[action] || { command: action, type: "button" };
                }
                if (kendo.isPresent(action.command) && action.command == "rating") {
                    return [builtInActions.ratePositive, builtInActions.rateNegative];
                }
                return action;
            }).flat();
        }

        _createButtonDefinitions() {
            const that = this;
            const baseDefinitions = AIPromptOutputActionManager.getBuiltInActionDefinitions();

            return {
                copy: {
                    ...baseDefinitions.copy,
                    getMessage: () => that.view.options.messages.copyOutput,
                    handler: (e, promptOutput) => that._handleCopyAction(e, promptOutput)
                },
                retry: {
                    ...baseDefinitions.retry,
                    getMessage: () => that.view.options.messages.retryGeneration,
                    handler: (e, promptOutput) => that._handleRetryAction(e, promptOutput)
                },
                ratePositive: {
                    ...baseDefinitions.ratePositive,
                    getMessage: () => that.view.options.messages.ratePositive,
                    handler: (e, promptOutput) => that._handleRatePositiveAction(e, promptOutput)
                },
                rateNegative: {
                    ...baseDefinitions.rateNegative,
                    getMessage: () => that.view.options.messages.rateNegative,
                    handler: (e, promptOutput) => that._handleRateNegativeAction(e, promptOutput)
                },
                stop: {
                    ...baseDefinitions.stop,
                    getMessage: () => that.view.options.messages.stopGeneration,
                    handler: (e, promptOutput) => that._handleStopAction(e, promptOutput)
                }
            };
        }

        initializeButtons(container, actions = null) {
            const that = this;

            if (actions) {
                actions.forEach(action => {
                    if (action.type === 'spacer') {
                        return;
                    }

                    const selector = `[data-action-command="${action.command}"]`;
                    that._initializeButton(container, selector, action);
                });
            }
        }

        _initializeButton(container, selector, action) {
            const that = this;
            const definition = that.buttonDefinitions[action.command];

            container.find(selector).kendoButton({
                icon: action.icon || definition?.icon,
                fillMode: action.fillMode || definition?.fillMode || "flat",
                themeColor: action.themeColor || definition?.themeColor,
                rounded: action.rounded,
                click: function(e) {
                    const promptOutput = that.aiprompt.outputManager.getOutputFromElement(e.target);
                    const eventArgs = {
                        command: action.command,
                        outputId: promptOutput.id,
                        output: promptOutput.output || promptOutput.text || promptOutput || "",
                        prompt: promptOutput.prompt || "",
                        button: e.sender.element
                    };

                    if (definition?.handler && !that.aiprompt.trigger("outputAction", eventArgs)) {
                        definition.handler(e, promptOutput);
                    } else if (!definition) {
                        that.aiprompt.trigger("outputAction", eventArgs);
                    }
                }
            });
        }

        _handleCopyAction(e, promptOutput) {
            const hasCopyHandler = this.aiprompt._events && this.aiprompt._events.outputCopy && this.aiprompt._events.outputCopy.length > 0;

            if (hasCopyHandler) {
                kendo.logToConsole("The outputCopy event is deprecated. Use the outputAction event instead.", "warn");
            }

            const copyEventArgs = { output: promptOutput };
            if (hasCopyHandler && this.aiprompt.trigger("outputCopy", copyEventArgs)) {
                return;
            }

            if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
                navigator.clipboard.writeText(promptOutput.output || promptOutput.text || promptOutput || "");
            }
        }

        _handleRetryAction(e, promptOutput) {
            const { prompt, output } = this.aiprompt.outputManager.extractOutputData(promptOutput);
            const that = this;
            const history = {
                role: messageTypes.ai,
                contents: [
                    {
                        $type: "text",
                        text: output
                    }
                ]
            };
            const retryEventArgs = {
                prompt: prompt,
                output: promptOutput,
                isRetry: true,
                history: [history]
            };

            const service = that.aiprompt?._selectedView?.service;

            if (service) {
                retryEventArgs.service = service;
            }

            if (that.aiprompt.trigger("promptRequest", retryEventArgs)) {
                return;
            }

            if (that.aiprompt?.options.service) {
                that.aiprompt?.transport?.read({
                    prompt: prompt,
                    history: retryEventArgs.history,
                    isRetry: true,
                    service: retryEventArgs.service
                });
            }
        }

        _handleRatePositiveAction(e, promptOutput) {
            const hasRatingChangeHandler = this.aiprompt._events && this.aiprompt._events.outputRating && this.aiprompt._events.outputRating.length > 0;

            if (hasRatingChangeHandler) {
                kendo.logToConsole("The outputRating event is deprecated. Use the outputAction event instead.", "warn");
            }

            this.aiprompt.trigger("outputRating", { rateType: "positive", output: promptOutput });

            kendo.ui.icon(e.sender.element.find(".k-icon"), "thumb-up");

            const negativeButton = $(e.target).siblings("[data-action-command='rateNegative'], [ref-rate-negative]");
            kendo.ui.icon(negativeButton.find(".k-icon"), "thumb-down-outline");
        }

        _handleRateNegativeAction(e, promptOutput) {
            const hasRatingChangeHandler = this.aiprompt._events && this.aiprompt._events.outputRating && this.aiprompt._events.outputRating.length > 0;

            if (hasRatingChangeHandler) {
                kendo.logToConsole("The outputRating event is deprecated. Use the outputAction event instead.", "warn");
            }

            this.aiprompt.trigger("outputRating", { rateType: "negative", output: promptOutput });

            kendo.ui.icon(e.sender.element.find(".k-icon"), "thumb-down");

            const positiveButton = $(e.target).siblings("[data-action-command='ratePositive'], [ref-rate-positive]");
            kendo.ui.icon(positiveButton.find(".k-icon"), "thumb-up-outline");
        }

        _handleStopAction(e, promptOutput) {
            this.aiprompt.trigger("promptRequestCancel", { output: promptOutput });

            $(e.target).siblings(".k-hidden").removeClass("k-hidden");
            $(e.target).addClass("k-hidden");
        }

        destroy() {
            this.aiprompt = null;
            this.options = null;
            this.buttonDefinitions = null;
        }
    }

    kendo.ui.AIPromptOutputActionManager = AIPromptOutputActionManager;

})(window.kendo.jQuery);
