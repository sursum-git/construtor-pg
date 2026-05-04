/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

// Template management for AIPrompt views
(function($) {

    const CSS_CLASSES = {
        PROMPT_VIEW: "k-prompt-view",
        PROMPT_EXPANDER: "k-prompt-expander",
        SUGGESTION_GROUP: "k-suggestion-group",
        SUGGESTION: "k-suggestion",
        CARD: "k-card",
        CARD_LIST: "k-card-list",
        CARD_HEADER: "k-card-header",
        CARD_TITLE: "k-card-title",
        CARD_SUBTITLE: "k-card-subtitle",
        CARD_BODY: "k-card-body",
        CARD_ACTIONS: "k-card-actions",
        ACTIONS: "k-actions k-actions-start k-actions-horizontal",
        SPACER: "k-spacer",
        HIDDEN: "k-hidden"
    };

    const REFS = {
        PROMPT_INPUT: "ref-prompt-input",
        SUGGESTIONS_BUTTON: "ref-prompt-suggestions-button",
        GENERATE_BUTTON: "ref-generate-output-button",
        OUTPUT_BODY: "ref-output-body",
        COPY_BUTTON: "ref-copy-button",
        RETRY_BUTTON: "ref-retry-button",
        RATE_POSITIVE: "ref-rate-positive",
        RATE_NEGATIVE: "ref-rate-negative",
        STOP_GENERATION: "ref-stop-generation"
    };

    // Template builder class for consistent template generation
    class AIPromptTemplateBuilder {

        static createPromptView({ suggestions, promptSuggestionItemTemplate, messages }) {
            const suggestionsHtml = suggestions?.length ?
                AIPromptTemplateBuilder._createSuggestionsSection(suggestions, promptSuggestionItemTemplate, messages) : '';

            return `<div class="${CSS_CLASSES.PROMPT_VIEW}">
                <textarea ${REFS.PROMPT_INPUT}></textarea>
                ${suggestionsHtml}
            </div>`;
        }

        static createPromptFooter({ messages }) {
            return `<div class="${CSS_CLASSES.ACTIONS} k-prompt-actions">
                <button ${REFS.GENERATE_BUTTON}>${messages.generateOutput}</button>
            </div>`;
        }

        static createSuggestionItem({ suggestion }) {
            return `<span role="listitem" class="${CSS_CLASSES.SUGGESTION}">${suggestion}</span>`;
        }

        static createOutputCard({ output, showOutputRating, messages, showOutputSubtitleTooltip, encodedPromptOutputs, isStreaming, outputActions, outputTemplate }) {
            const contentHtml = AIPromptTemplateBuilder._generateContentHtml({
                output, outputTemplate, encodedPromptOutputs
            });

            const actionsHtml = AIPromptTemplateBuilder._generateActionsHtml({
                outputActions, showOutputRating, messages, isStreaming
            });
            const dataIdAttr = output.id ? ` data-id="${output.id}"` : '';
            return `<div role="listitem" tabindex="0" class="${CSS_CLASSES.CARD}"${dataIdAttr}>
                ${output.skipHeader ? '' : AIPromptTemplateBuilder._createCardHeader(output, messages, showOutputSubtitleTooltip)}
                ${output.skipBody ? '' : AIPromptTemplateBuilder._createCardBody(contentHtml, output.isLoading)}
                ${output.skipActions ? '' : actionsHtml}
            </div>`;
        }

        static createOutputView({ promptOutputs, showOutputRating, messages, showOutputSubtitleTooltip, encodedPromptOutputs, outputActions, outputTemplate }) {
            const cardsHtml = promptOutputs ?
                promptOutputs.map(output =>
                    AIPromptTemplateBuilder.createOutputCard({
                        output, showOutputRating, messages, showOutputSubtitleTooltip,
                        encodedPromptOutputs, outputActions, outputTemplate
                    })
                ).join("") : '';

            return `<div class="${CSS_CLASSES.PROMPT_VIEW}">
                <div role="list" class="${CSS_CLASSES.CARD_LIST}">
                    ${cardsHtml}
                </div>
            </div>`;
        }

        // Private helper methods
        static _createSuggestionsSection(suggestions, promptSuggestionItemTemplate, messages) {
            const suggestionItems = suggestions
                .map(suggestion => promptSuggestionItemTemplate({ suggestion }))
                .join("");

            return `<div class="${CSS_CLASSES.PROMPT_EXPANDER}">
                <button ${REFS.SUGGESTIONS_BUTTON} aria-expanded="true">${messages.promptSuggestions}</button>
                <div class="k-prompt-expander-content" role="list">
                    <div class="${CSS_CLASSES.SUGGESTION_GROUP}">
                        ${suggestionItems}
                    </div>
                </div>
            </div>`;
        }

        static _createCardHeader(output, messages, showOutputSubtitleTooltip) {
            const tooltipAttr = showOutputSubtitleTooltip ?
                `title="${kendo.htmlEncode(output.prompt)}"` : "";

            return `<div class="${CSS_CLASSES.CARD_HEADER}">
                <div class="${CSS_CLASSES.CARD_TITLE}">${messages.outputTitle}</div>
                <div class="${CSS_CLASSES.CARD_SUBTITLE}" ${tooltipAttr}>${kendo.htmlEncode(output.prompt)}</div>
            </div>`;
        }

        static _createCardBody(contentHtml, isLoading) {
            return `<div class="${CSS_CLASSES.CARD_BODY}" ${REFS.OUTPUT_BODY}>
                ${contentHtml}
            </div>`;
        }

        static _generateContentHtml({ output, outputTemplate, encodedPromptOutputs }) {
            if (outputTemplate && typeof outputTemplate === 'function' &&
                !output.isLoading && output.output) {
                return outputTemplate({ output: output, content: output.output });
            }

            const content = output.output || '';
            const loadingAttr = output.isLoading ? ' data-loading="true"' : ' data-loading="false"';
            return `<p ref-output-content${loadingAttr}>${encodedPromptOutputs ? kendo.htmlEncode(content) : content}</p>`;
        }

        static _generateActionsHtml({ outputActions, showOutputRating, messages, isStreaming }) {
            // outputActions should always be defined due to component defaults,
            // but provide fallback for safety
            if (!outputActions) {
                outputActions = showOutputRating ?
                    ["copy", "retry", "spacer", "rating"] :
                    ["copy", "retry"];
            }

            return AIPromptTemplateBuilder._createCustomActions(outputActions, showOutputRating, messages, isStreaming);
        }

        static _createCustomActions(outputActions, showOutputRating, messages, isStreaming) {
            const filteredActions = outputActions.filter(action => action.command !== 'stop');

            // Check if rating buttons are already included in the actions
            const hasRatingButtons = filteredActions.some(action =>
                action.command === 'ratePositive' || action.command === 'rateNegative'
            );

            // If showOutputRating is true and rating buttons aren't already included, add them with a spacer
            let actionsToRender = [...filteredActions];
            if (showOutputRating && !hasRatingButtons) {
                // Check if there's already a spacer, if not add one
                const hasExistingSpacer = actionsToRender.some(action => action.type === 'spacer');
                if (!hasExistingSpacer) {
                    actionsToRender.push({ type: 'spacer' });
                }

                // Add rating buttons
                actionsToRender.push(
                    { command: 'ratePositive', text: messages.ratePositive, type: 'button' },
                    { command: 'rateNegative', text: messages.rateNegative, type: 'button' }
                );
            }

            const actionsHtml = actionsToRender
                .map(action => AIPromptTemplateBuilder._createActionButton(action, messages, isStreaming))
                .join('');

            return `<div class="${CSS_CLASSES.ACTIONS} ${CSS_CLASSES.CARD_ACTIONS}">
                ${actionsHtml}
            </div>`;
        }

        static _createActionButton(action, messages, isStreaming) {
            if (action.type === 'spacer') {
                return `<span class="${CSS_CLASSES.SPACER}"></span>`;
            }

            const text = action.text || AIPromptTemplateBuilder._getActionText(action.command, messages);
            const title = action.title || text;

            return `<button data-action-command="${action.command}" title="${title}">${action.iconButton ? "" : text}</button>`;
        }

        static _getActionText(command, messages) {
            const textMap = {
                'copy': messages.copyOutput,
                'retry': messages.retryGeneration,
                'ratePositive': messages.ratePositive,
                'rateNegative': messages.rateNegative
            };

            return textMap[command] || command;
        }
    }

    // Expose to kendo namespace
    kendo.ui.AIPromptTemplateBuilder = AIPromptTemplateBuilder;

})(window.kendo.jQuery);
