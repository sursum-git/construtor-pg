/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

// Output management abstraction for AIPrompt components
(function($) {

    class AIPromptOutputObject {
        constructor(outputData, outputManager) {
            this.id = outputData.id;
            this.data = outputData;
            this._element = null;
            this._bodyElement = null;
            this._aiprompt = outputManager.aiprompt;
            this._isLoading = outputData.isLoading || false;
        }

        get isLoading() {
            return this._isLoading;
        }

                set isLoading(value) {
            const wasLoading = this._isLoading;
            this._isLoading = value;

            this.data.isLoading = value;

            if (value === true) {
                this.showSkeleton();
            } else if (value === false) {
                this.hideSkeleton();

                if (wasLoading || this.data.output) {
                    this.applyFinalTemplate();
                }
            }
        }

        getElement() {
            return this._element;
        }

        setElement(element) {
            this._element = element;
            return this;
        }

        updateContent(newContent) {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            this.data.content = newContent;
            this.data.output = newContent;

            const bodyElement = this._element.find('.k-card-body');
            const contentElement = bodyElement.find('[ref-output-content]');

            if (newContent && newContent.trim() && contentElement.length > 0) {
                bodyElement.find('.k-skeleton').remove();

                contentElement.attr('data-loading', 'false').show();
                const encodedPromptOutputs = this._aiprompt.options.encodedPromptOutputs;
                contentElement.html(encodedPromptOutputs ? kendo.htmlEncode(newContent) : newContent);
            }

            return this;
        }

        showSkeleton() {
            this.showHeaderSkeleton();
            this.showBodySkeleton();
            this.showActionSkeleton();
            return this;
        }

        hideSkeleton() {
            this.hideHeaderSkeleton();
            this.hideBodySkeleton();
            this.hideActionSkeleton();
            return this;
        }

        applyFinalTemplate() {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            const bodyElement = this._element.find('.k-card-body');
            const contentElement = bodyElement.find('[ref-output-content]');

            let outputTemplate = this._aiprompt?.options?.outputTemplate;
            if (!outputTemplate) {
                return this;
            } else if (typeof outputTemplate === 'string') {
                outputTemplate = kendo.template(outputTemplate);
            }

            if (outputTemplate && typeof outputTemplate === 'function' && this.data.output) {
                const customContent = outputTemplate({ output: this.data, content: this.data.output });
                bodyElement.html(customContent);
            } else if (contentElement.length > 0) {
                contentElement.attr('data-loading', 'false').show();
            }

            return this;
        }

        showHeaderSkeleton() {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            const headerElement = this._element.find('.k-card-header');
            if (headerElement.length === 0) {
                return this;
            }

            // Optionally hide header content if needed
            headerElement.children().hide();

            if (headerElement.find('.k-skeleton').length === 0) {
                const skeleton = $(`<span class="k-skeleton k-skeleton-text k-skeleton-pulse"></span>`);
                skeleton.css('width', '60%').css('height', '24px');
                headerElement.prepend(skeleton);
            }

            return this;
        }

        hideHeaderSkeleton() {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            const headerElement = this._element.find('.k-card-header');
            if (headerElement.length === 0) {
                return this;
            }

            headerElement.find('.k-skeleton').remove();
            headerElement.children().removeClass("k-hidden").show();

            return this;
        }

        showBodySkeleton() {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            const bodyElement = this._element.find('.k-card-body');
            const contentElement = bodyElement.find('[ref-output-content]');

            contentElement.attr('data-loading', 'true').hide();

            if (bodyElement.find('.k-skeleton').length === 0) {
                const skeleton = $(`<span class="k-skeleton k-skeleton-rect k-skeleton-pulse"></span>`);
                skeleton.css('height', '80px');
                bodyElement.prepend(skeleton);
            }

            return this;
        }

        hideBodySkeleton() {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            const bodyElement = this._element.find('.k-card-body');

            bodyElement.find('.k-skeleton').remove();

            this.applyFinalTemplate();

            return this;
        }

        showActionSkeleton() {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            const actionsElement = this._element.find('.k-card-actions');

            if (actionsElement.length > 0) {
                actionsElement.children().hide();

                if (actionsElement.find('.k-skeleton').length === 0) {
                    const skeleton = $(`<span class="k-skeleton k-skeleton-text k-skeleton-pulse"></span>`);
                    skeleton.css('width', '100%').css('height', '32px');
                    actionsElement.prepend(skeleton);
                }
            }

            return this;
        }

        hideActionSkeleton() {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            const actionsElement = this._element.find('.k-card-actions');

            if (actionsElement.length > 0) {
                actionsElement.find('.k-skeleton').remove();

                actionsElement.children().removeClass("k-hidden").show();
            }

            return this;
        }

        toggleActionButtons(isStreaming, outputActions) {
            if (!this._element || this._element.length === 0) {
                return this;
            }

            if (outputActions) {
                // For custom output actions - hide all buttons during streaming
                const allActions = this._element.find('[data-action-command]');

                if (isStreaming) {
                    allActions.addClass('k-hidden');
                } else {
                    allActions.removeClass('k-hidden');
                }
            } else {
                // For legacy ref-based buttons - hide all except rating buttons during streaming
                const actionButtons = this._element.find('[ref-copy-button], [ref-retry-button]');

                if (isStreaming) {
                    actionButtons.addClass('k-hidden');
                } else {
                    actionButtons.removeClass('k-hidden');
                }
            }

            return this;
        }

        destroy() {
            this._element = null;
            this._bodyElement = null;
            this._aiprompt = null;
            this.data = null;
            this.id = null;
            this._isLoading = false;
        }
    }

    class AIPromptOutputManager {
        constructor(aiprompt) {
            this.aiprompt = aiprompt;
        }

        createOutputObject(outputData) {
            return new AIPromptOutputObject(outputData, this);
        }

        getLastOutputObject() {
            if (this.aiprompt.promptOutputs.length > 0) {
                const lastOutput = this.aiprompt.promptOutputs[0]; // First element is the most recent
                return this.aiprompt.outputObjects.get(lastOutput.id);
            }
            return null;
        }

        // Enhanced method that handles parameter flexibility like the main AIPrompt component
        updatePromptOutputContent(content, outputId) {
            let outputObj;

            if (outputId) {
                outputObj = this.aiprompt.outputObjects.get(outputId);
            } else {
                outputObj = this.getLastOutputObject();
            }

            if (outputObj) {
                // Call the output object's updateContent method directly
                outputObj.updateContent(content);
                return outputObj; // Return the updated object for chaining
            } else {
                // No output object found
                return null;
            }
        }

        // Stop loading state for the most recent output
        stopLoading(objectId) {
            let outputObj = this.aiprompt.outputObjects.get(objectId);
            if (outputObj) {
                outputObj.isLoading = false; // This will automatically call hideSkeleton()
            } else {
                outputObj = this.getLastOutputObject();
                if (outputObj) {
                    outputObj.isLoading = false; // This will automatically call hideSkeleton()
                }
            }
        }

        stopAllLoading() {
            this.aiprompt.outputObjects.forEach(outputObj => {
                outputObj.isLoading = false;
            });
        }

        getOutputFromElement(element) {
            let card = $(element).closest(".k-card");
            let id = card.data("id");

            let promptOutput = this.aiprompt.promptOutputs.find(output => output.id == id);

            if (!promptOutput && this.aiprompt.outputObjects) {
                promptOutput = this.aiprompt.outputObjects.get(id);
            }

            return promptOutput;
        }

        extractOutputData(promptOutput) {
            if (!promptOutput) {
                return { prompt: null, output: null };
            }

            if (promptOutput.data) {
                return {
                    prompt: promptOutput.data.prompt,
                    output: promptOutput.data.output
                };
            }

            return {
                prompt: promptOutput.prompt,
                output: promptOutput.output
            };
        }

        destroy() {
            if (this.aiprompt && this.aiprompt.outputObjects) {
                this.aiprompt.outputObjects.forEach(outputObj => {
                    if (outputObj) {
                        outputObj._element = null;
                        outputObj._bodyElement = null;
                        outputObj._aiprompt = null;
                        outputObj.data = null;
                    }
                });
                this.aiprompt.outputObjects.clear();
            }

            this.aiprompt = null;
        }
    }

    kendo.ui.AIPromptOutputObject = AIPromptOutputObject;
    kendo.ui.AIPromptOutputManager = AIPromptOutputManager;

})(window.kendo.jQuery);
