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
import "./kendo.icons.js";
import "./kendo.textarea.js";
import "./kendo.button.js";
import "./kendo.toolbar.js";
import "./kendo.popup.js";
import "./kendo.speechtotextbutton.js";
import "./kendo.skeletoncontainer.js";

import { defaultCommands } from "./utils/inline-ai-prompt-commands.js";

export const __meta__ = {
    id: "inlineaiprompt",
    name: "InlineAIPrompt",
    category: "web",
    description: "The InlineAIPrompt component simplifies the incorporation of external AI services into apps.",
    depends: ["core", "icons", "textarea", "button", "toolbar", "popup", "data", "speechtotextbutton", "skeletoncontainer"],
};

(function($) {
    let kendo = window.kendo,
        Widget = kendo.ui.Widget,
        TextArea = kendo.ui.TextArea,
        SpeechToTextButton = kendo.ui.SpeechToTextButton,
        NS = ".kendoInlineAIPrompt",
        ui = kendo.ui,

        PROMPT_REQUEST = "promptRequest",
        PROMPT_RESPONSE = "promptResponse",
        PROMPT_REQUEST_CANCEL = "promptRequestCancel",
        COMMAND_EXECUTE = "commandExecute",
        OUTPUT_ACTION = "outputAction",

        SHOW = "show",
        HIDE = "hide",

        K_SPEECHTOTEXTBUTTON = "k-speech-to-text-button",
        K_STOP_OUTPUT_RETRIEVAL = "k-generating",
        K_DISABLED = "k-disabled",
        K_PROMPT_SEND = "k-prompt-send",
        K_ALIGN_ITEMS_START = "!k-align-items-start",
        DEFAULT_PLACEHOLDER = "Edit, generate or explain...",

        ARIA_LABEL = "aria-label",
        ARIA_DISABLED = "aria-disabled",

        DEFAULT_COMMANDS = defaultCommands,

        FOCUS = "focus";

        var extend = $.extend;

    let InlineAIPrompt = Widget.extend({
        init: function(element, options) {
            let that = this;
            options = options || {};

            that.options.systemPrompt = options.systemPrompt || that.options.systemPrompt;
            Widget.fn.init.call(that, element, options);

            if (options.commands) {
               that.options.commands = options.commands;
            }

            that.transport = new kendo.data.AiTransport({
                service: that.options.service,
                success: that._serviceSuccess.bind(that),
                requestStart: (ev) => {
                    that._progress(true);
                }
            });

            that._initPopup();
            that._initContextMenu();
            that._bindEvents();

            kendo.notify(that);
        },

        options: {
            name: "InlineAIPrompt",
            enable: true,
            outputActions: null,
            encodedPromptOutputs: true,
            readonly: false,
            placeholder: "",
            speechToText: false,
            service: null,
            isStreaming: false,
            systemPrompt: (context, prompt) => `You are an advanced AI language assistant.
                                A user has selected a portion of their text and provided a query regarding how they want it modified.
                                Your task is to accurately respond to their request while preserving the original intent of the text.
                                Follow the instructions strictly and provide only the requested output unless explicitly asked to explain your changes.

                                Selected Text:
                                ${context}

                                User's Request:
                                ${prompt}

                                Response:`,
            commands: DEFAULT_COMMANDS,
            messages: {
                promptSend: "Prompt Send",
                stopOutputRetrieval: "Stop Output Retrieval",
                commandsMenu: "Commands Menu",
                retryOutputAction: "Retry",
                copyOutputAction: "Copy",
                discardOutputAction: "Discard"
            }
        },

        events: [
            PROMPT_REQUEST,
            PROMPT_RESPONSE,
            PROMPT_REQUEST_CANCEL,
            COMMAND_EXECUTE,
            OUTPUT_ACTION,
            SHOW,
            HIDE
        ],

        value: function(value) {
            let that = this;
            that._textArea.value(value);
            that._textArea.trigger("change");
            that._togglePromptSend();
        },

        enable: function(enable) {
            let that = this;

            if (typeof enable == "boolean" ) {
                that._textArea.enable(enable);
            }
        },

        readonly: function(readonly) {
            let that = this;
            if (typeof readonly == "boolean" ) {
                that._textArea.readonly(readonly);
            }
        },

        startStreaming: function() {
            let that = this;
            that._progress(true);
        },

        stopStreaming: function() {
            let that = this;
            that._progress(false);
            that._togglePromptSend();
            that._focusOutputActions();
        },

        updatePromptOutputContent: function(content) {
            let that = this,
                outputCard = that._popup.element.find('.k-card-body');

                 const output = that.options.encodedPromptOutputs ?
                       kendo.htmlEncode(content) :
                       content;

                 outputCard.html(output);
        },

        open: function(x, y) {
             let that = this;

                if (typeof x === "number" && typeof y === "number") {
                    that._popup.open(x, y);
                    return;
                }

                const originalAnchor = that._popup.options.anchor;
                const originalOrigin = that._popup.options.origin;
                const originalPosition = that._popup.options.position;

                that._popup.options.anchor = that.element;
                that._popup.options.origin = "bottom center";
                that._popup.options.position = "top center";

                that._popup.open();

                that._popup.options.anchor = originalAnchor;
                that._popup.options.origin = originalOrigin;
                that._popup.options.position = originalPosition;

                that._textArea.focus();
        },

        close: function() {
            let that = this;
            that._popup.close();
        },

        _serviceSuccess: function(response) {
            const that = this,
                  responseTemplate = that.options.responseTemplate,
                  emptyResult = () => "";

            if (kendo.isPresent(responseTemplate)) {
                let template = kendo.isFunction(responseTemplate)
                                 ?
                                 responseTemplate(response)
                                 :
                                 kendo.template(responseTemplate || emptyResult)({ response: response });

                that._popup.element.find('.k-card-body').html(template);
            }
            else {
                const output = that.options.encodedPromptOutputs ?
                      kendo.htmlEncode(response.output) :
                      response.output;

                that._popup.element.find('.k-card-body').html(output);
            }

            that._popup.element.find('.k-card').show();
            that.trigger(PROMPT_RESPONSE, { response });
            that._progress(false);
            that._togglePromptSend();
            that._focusOutputActions();
        },

        _initTextArea: function(element) {
            const that = this,
                  promptSendMessage = that.options.messages.promptSend;

            let widget,
                options = extend({}, {
                     _isInInlineAIPrompt: true,
                     enable: that.options.enable,
                     readonly: that.options.readonly,
                     overflow: "auto",
                     placeholder: that.options.placeholder || DEFAULT_PLACEHOLDER,
                     maxRows: 5,
                     resize: "auto",
                     layoutFlow: "horizontal",
                     prefixOptions: {
                        separator: false,
                        template: () => `${that._renderPrefixTemplate()}`
                     },
                     suffixOptions: {
                        separator: false,
                        template: () => `
                             ${kendo.html
                                .renderButton(`<button role='button' class='${K_PROMPT_SEND} ${K_DISABLED}' ${ARIA_LABEL}='${promptSendMessage}' ${ARIA_DISABLED}='true'></button>`,
                                { icon: "paper-plane", fillMode: "flat" })
                             }`
                     }
                });

             widget = new TextArea(element, options);

            widget._prefixContainer
                    .addClass(K_ALIGN_ITEMS_START);


            widget._suffixContainer
                    .addClass(K_ALIGN_ITEMS_START);

            $(`<span class="k-input-separator k-input-separator-vertical"></span>`).insertBefore($(widget.element));

            if (that.options.speechToText || (that.options.speechToText && options.speechToText === undefined) ) {
                let speechToTextElement = widget._prefixContainer.find(`.${K_SPEECHTOTEXTBUTTON}`);
                that._initSpeechToTextButton(speechToTextElement);
            }

            return widget;
        },

        _renderPrefixTemplate: function() {
            let that = this,
                template = "";

            const commandsMenuMessage = that.options.messages.commandsMenu;

            let menuButton = kendo.html.renderButton(`<button role='button' title='${commandsMenuMessage}' ${ARIA_LABEL}='${commandsMenuMessage}'></button>`, { icon: "menu", fillMode: "flat" });

            template = template + menuButton;
            if (that.options.speechToText) {
                let speechToTextElement = `<button role='button' title='Speech To Text' ${ARIA_LABEL}='Start speech recognition' class='${K_SPEECHTOTEXTBUTTON}' id='${kendo.guid()}'></button>`;
                template = template + speechToTextElement;
            }

            return template;
        },

        _initSpeechToTextButton: function(element) {
            const that = this;

            let options = extend({}, {
                    fillMode: "flat",
                    integrationMode: that.options.speechToText.integrationMode || "webSpeech",
                    lang: 'en-US',
                    continuous: false,
                    interimResults: false,
                    maxAlternatives: 1,
                    result: function(ev) {
                        if (ev.isFinal && ev.alternatives && ev.alternatives.length > 0) {
                                let currentValue = that._textArea.value() || "",
                                    newValue = currentValue + (currentValue ? " " : "") + ev.alternatives[0].transcript;

                                that._textArea.value(newValue);
                                that._textArea.trigger("change");
                                that._togglePromptSend();
                        }
                    }
                },
                that.options.speechToText);

            that._speechToTextButton = new SpeechToTextButton(element, options);
        },

        _togglePromptSend: function() {
            let that = this,
                textArea = that._textArea,
                value = textArea.value() || "";

            if (value.replace(/\s/g, '').length) {
                $(textArea._suffixContainer)
                    .find(`.${K_PROMPT_SEND}`)
                    .removeClass(K_DISABLED)
                    .attr(ARIA_DISABLED, false);
            }
            else {
                $(textArea._suffixContainer)
                   .find(`.${K_PROMPT_SEND}`)
                   .addClass(K_DISABLED)
                   .attr(ARIA_DISABLED, true);
            }
        },

        _initPopup: function() {
            const that = this,
                  html = $(that._template());

            const options = that.options.popup;

            if (kendo.isPresent(options)) {
                if (options.width) {
                    html.width(options.width);
                }

                if (options.height) {
                    html.height(options.height);
                }
            }

            html.css({ "max-width": "98vw" });

            that._popup = new kendo.ui.Popup(html, {
                anchor: that.element,
                appendTo: options?.appendTo || document.body,
                isRtl: kendo.support.isRtl(that.element),
                animation: options?.animation || {
                    open: {
                        effects: "zoom:in",
                        duration: 200
                    },
                    close: {
                        effects: "zoom:out",
                        duration: 200
                    }
                },
                open: function(ev) {
                    this.element.find('.k-card').hide();
                    this.element.find('.k-card-body').empty();
                    this.element.find('.k-input-inner').val('');

                    that._textArea.value('');
                    that._textArea.element
                        .removeAttr("style");

                    that._togglePromptSend();

                    that.trigger(SHOW, ev);
                },
                close: function(ev) {
                    kendo.ui.progress(that._popup.element, false);
                    that._aiContextMenu?.close();
                    that.trigger(HIDE, ev);
                }
            });


            let textAreaElement = that._popup.element
                .find('textarea')
                .first();

            that._textArea = that._initTextArea(textAreaElement);
        },

        _input: function(e) {
             let that = this,
                 textArea = that._textArea,
                 value = e.currentTarget.value,
                 oldValue = that._textArea.value();

             if (value != oldValue) {
                textArea.value(value);
                textArea.trigger("change");
                that._togglePromptSend();
             }
        },

        _template: function() {
            let that = this;
            const textAreaHtml = `<textarea ${ARIA_LABEL}='Prompt' id='${kendo.guid()}'></textarea>`,
                  template = `<div class="k-child-animation-container">
                    <div class="k-prompt-popup k-popup">
                        <div class="k-prompt">
                            <div class="k-prompt-content">
                                <div class="k-prompt-view">
                                    <div class="k-card">
                                        <div class="k-card-body" style="max-height: 150px; overflow-y: auto;"></div>
                                        <div class="k-actions k-actions-start k-actions-horizontal k-card-actions">
                                            ${that._renderOutputActions()}
                                        </div>
                                    </div>
                                   ${textAreaHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;

            return template;
        },

        _renderOutputActions: function() {
            let that = this,
                options = that.options;

                const copyMessage = that.options.messages.copyOutputAction,
                      retryMessage = that.options.messages.retryOutputAction,
                      discardMessage = that.options.messages.discardOutputAction;

            if (!kendo.isPresent(options.outputActions) || !options.outputActions.length) {
                if (that.options._isEditor) {
                    let buttons = `
                        ${kendo.html.renderButton(`<button role='button' title='${copyMessage}' ${ARIA_LABEL}='${copyMessage}' ${kendo.attr("action")}='copy'>${copyMessage}</button>`, { icon: "copy", fillMode: "flat", themeColor: "primary" })}
                        ${kendo.html.renderButton(`<button title='Insert' ${ARIA_LABEL}="Insert" ${kendo.attr("action")}='insert'>Insert</button>`, { icon: "insert-bottom", fillMode: "flat", themeColor: "primary" })}
                        ${kendo.html.renderButton(`<button title='Replace' ${ARIA_LABEL}="Replace" ${kendo.attr("action")}='replace'>Replace</button>`, { icon: "check", fillMode: "flat", themeColor: "primary" })}
                        ${kendo.html.renderButton(`<button title='Discard' ${ARIA_LABEL}='Discard' ${kendo.attr("action")}='discard'>Discard</button>`, { icon: "cancel-outline", fillMode: "flat" })}
                     `;
                     return buttons;
                }
                else {
                    let buttons = `
                        ${kendo.html.renderButton(`<button role='button' title='${copyMessage}' ${ARIA_LABEL}='${copyMessage}' ${kendo.attr("action")}='copy'>${copyMessage}</button>`, { icon: "copy", fillMode: "flat", themeColor: "primary" })}
                        ${kendo.html.renderButton(`<button role='button' title='${retryMessage}' ${ARIA_LABEL}='${retryMessage}'  ${kendo.attr("action")}='retry'>${retryMessage}</button>`, { icon: "arrow-rotate-cw", fillMode: "flat", themeColor: "primary" })}
                        ${kendo.html.renderButton(`<button role='button' title='${discardMessage}' ${ARIA_LABEL}='${discardMessage}' ${kendo.attr("action")}='discard'>${discardMessage}</button>`, { icon: "cancel-outline", fillMode: "flat" })}
                    `;
                    return buttons;
                }
            }
            else {
                let buttons = "";
                options.outputActions.forEach(function(outputAction) {
                    if (typeof outputAction == "string") {
                        switch (outputAction) {
                            case "copy":
                                buttons = buttons + kendo.html.renderButton(`<button role='button' title='${copyMessage}' ${ARIA_LABEL}='${copyMessage}' ${kendo.attr("action")}='copy'>${copyMessage}</button>`, { icon: "copy", fillMode: "flat", themeColor: "primary" });
                                break;
                            case "retry":
                                buttons = buttons + kendo.html.renderButton(`<button role='button' title='${retryMessage}' ${ARIA_LABEL}='${retryMessage}'  ${kendo.attr("action")}='retry'>${retryMessage}</button>`, { icon: "arrow-rotate-cw", fillMode: "flat", themeColor: "primary" });
                                break;
                            case "discard":
                                buttons = buttons + kendo.html.renderButton(`<button role='button' title ='${discardMessage}' ${ARIA_LABEL}='${discardMessage}' ${kendo.attr("action")}='discard'>${discardMessage}</button>`, { icon: "cancel-outline", fillMode: "flat" });
                                break;
                            default:
                                break;
                        }
                    }
                    else {
                        buttons = buttons + kendo.html
                        .renderButton(`<button role='button' ${outputAction?.title != undefined ? `title=${outputAction.title} ${ARIA_LABEL}=${outputAction.title}` : ""} ${kendo.attr("action")}='${outputAction?.command}'>${outputAction?.text || (that._isDefaultOutputAction(outputAction?.command) ? that.options.messages[outputAction?.command + 'OutputAction'] || outputAction?.command : outputAction?.command)}</button>`,
                        {
                            rounded: outputAction?.rounded,
                            icon: outputAction?.icon || that._renderDefaultOutputActionIcon(outputAction?.command),
                            fillMode: outputAction?.fillMode || "flat",
                            themeColor: outputAction?.themeColor || "primary",
                        });
                    }
                });

                return buttons;
            }
        },

        _renderDefaultOutputActionIcon(command) {
            let that = this;
            if (that._isDefaultOutputAction(command)) {
                switch (command) {
                case "copy":
                    return "copy";
                case "discard":
                    return "cancel-outline";
                case "retry":
                    return "arrow-rotate-cw";
                default:
                    return;
                }
            }
        },

        _isDefaultOutputAction(command) {
            switch (command) {
                case "copy":
                    return true;
                case "discard":
                    return true;
                case "retry":
                    return true;
                default:
                    return false;
            }
        },

        _initContextMenu: function() {
            const that = this;
            if (!that.options.commands || that.options.commands?.length === 0) {
                that._popup.element.find('.k-input-prefix button').addClass('k-disabled');
                return;
            }

            const itemsHtml = that.options.commands.map(command => that._createCommandHtml(command)).join('');
            const html = $(`<ul>
                                ${itemsHtml}
                            </ul>`);

            $('body').append(html);

            that._aiContextMenu = html.kendoContextMenu({
                target: that._popup.element,
                anchor: that._popup.element,
                showOn: 'click',
                appendTo: $(document.body),
                filter: '.invalid',
                select: that._executeCommand.bind(that)
            }).data('kendoContextMenu');
        },

        _createCommandHtml: function(command) {
            const that = this;
            const icon = that._renderCommandIcon(command.icon);
            const text = command.text;
            const items = command.items || [];
            const itemsHtml = items.map(item => that._createCommandHtml(item)).join('');

            return `<li ${kendo.attr("name")}=${command.id}>${icon} ${text}${itemsHtml ? "<ul>" + itemsHtml + "</ul>" : ""}</li>`;
        },

        _renderCommandIcon: function(icon) {
            if (kendo.isPresent(icon)) {
                return kendo.isFunction(icon) ? icon() : kendo.html.renderIcon({ icon: icon });
            }

            return "";
        },

        _executeCommand: function(e) {
            const that = this;
            let command = that._findCommand(that.options.commands, e.item.getAttribute(kendo.attr("name")));

            let data = {
                query: command.id,
                selection: ""
            };

            that._clearSelectedCommands(that.options.commands);

            if (kendo.isPresent(that._textArea)) {
                const textAreaValue = that._textArea.value();

                if (kendo.isPresent(textAreaValue) && textAreaValue != "") {
                    data.selection = textAreaValue;
                }
            }

            that.trigger(COMMAND_EXECUTE, data);

            const selection = data.selection || data.context;

            if (kendo.isPresent(command.prompt)) {
                const prompt = command.prompt(selection);
                that._requestService(prompt);
                that._aiContextMenu?.close();
            }
            command.selected = true;
        },

        _clearSelectedCommands: function(commands) {
            const that = this;

            commands.forEach(function(command) {
                if (kendo.isPresent(command.items) && command.items.length > 0) {
                    that._clearSelectedCommands(command.items);
                }
                if (command.hasOwnProperty("selected")) {
                    delete command.selected;
                }
            });
        },

        _findCommand: function(commands, id, searchSelected = false) {
            const that = this;

            for (let command of commands) {
                if (searchSelected && command.selected) {
                    return command;
                }

                if (command.id === id) {
                    return command;
                }

                if (command.items) {
                    let found = that._findCommand(command.items, id, searchSelected);

                    if (found) {
                        return found;
                    }
                }
            }
        },

        _bindEvents: function() {
            const that = this,
                  popup = that._popup,
                  popupElement = popup.element;

            that._textArea.element.on("input" + NS, that._input.bind(that));

            popupElement.find(`.k-input-prefix button[aria-label='${that.options.messages.commandsMenu}']`).on('click', function() {
                that._aiContextMenu?.open();
            });

            that._textArea._suffixContainer.find(`.${K_PROMPT_SEND}`).on('click', function() {
                 const prompt = that._textArea.value();
                 that._requestService(prompt);
            });

            popupElement.find('.k-card-actions button').on('click', function(e) {
                let contentElement = popupElement.find('.k-card-body'),
                    contentText = contentElement.text(),
                    prompt = kendo.isPresent(that._textArea) ? that._textArea.value() : "",
                    previouslySelectedCommand = that._findCommand(that.options.commands, 0, true),
                    query = kendo.isPresent(previouslySelectedCommand) ? previouslySelectedCommand.prompt(prompt) : prompt;

                const action = $(e.currentTarget).attr(kendo.attr("action"));

                switch (action) {
                    case "copy":
                        navigator.clipboard.writeText(contentText);
                        that.trigger(OUTPUT_ACTION, { action, content: contentText });
                        break;
                    case "retry":
                        that._requestService(query);
                        that.trigger(OUTPUT_ACTION, { action, content: contentText });
                        break;
                    case "discard":
                        contentElement.empty();
                        popupElement.find('.k-card').hide();
                        popupElement.find('.k-input-inner').val('');
                        that.trigger(OUTPUT_ACTION, { action, content: contentText });
                        popup.close();
                        break;
                    default:
                        that.trigger(OUTPUT_ACTION, { action, content: contentText });
                        break;
                }
            });
        },

        _stopOutputRetrievalButton: function(toggle) {
            let that = this;

            if (toggle) {
                let stopOutputRetrievalMessage = that.options.messages.stopOutputRetrieval,
                    stopOutputRetrievalButton = kendo.html
                .renderButton(`<button role='button' title='${stopOutputRetrievalMessage}' ${ARIA_LABEL}='${stopOutputRetrievalMessage}' class='${K_PROMPT_SEND} ${K_STOP_OUTPUT_RETRIEVAL}'></button>`,
                    { icon: "stop-sm", fillMode: "flat" }
                );

                that._textArea._suffixContainer.find(`.${K_PROMPT_SEND}`)
                    .replaceWith(stopOutputRetrievalButton);

                that._textArea._suffixContainer.find(`.${K_STOP_OUTPUT_RETRIEVAL}`)
                    .on('click', function(e) {
                        that.trigger(PROMPT_REQUEST_CANCEL, that);
                    });
            }
            else {
                let promptSendMessage = that.options.messages.promptSend;
                let promptSendButton = kendo.html
                .renderButton(`<button role='button' title="${promptSendMessage}" ${ARIA_LABEL}=${promptSendMessage} class='${K_PROMPT_SEND} ${K_DISABLED}'></button>`,
                    { icon: "paper-plane", fillMode: "flat" }
                );

                 that._textArea._suffixContainer.find(`.${K_STOP_OUTPUT_RETRIEVAL}`)
                    .replaceWith(promptSendButton);

                 that._textArea._suffixContainer.find(`.${K_PROMPT_SEND}`)
                    .on('click', function(e) {
                         const prompt = that._textArea.value();
                         that._requestService(prompt);
                    });
            }
        },

        _progress: function(toggle) {
             let that = this;

             that._stopOutputRetrievalButton(toggle);
             that._toggleSkeleton(toggle);
        },

        _toggleSkeleton: function(toggle) {
            let that = this;

            if (that.skeleton) {
                that.skeleton.remove();
            }

            if (toggle) {
                that.skeleton = $("<span class='k-skeleton k-skeleton-pulse'></span>").css({
                    width: "100%",
                    height: "100%"
                });

                that._textArea._suffixContainer
                    .find(`.${K_STOP_OUTPUT_RETRIEVAL}`)
                    .wrap(that.skeleton);
            }

            else {
                that._textArea._suffixContainer
                    .find(`.${K_PROMPT_SEND}`)
                    .unwrap();
            }
        },

        _requestService: function(query) {
            const that = this;
            const data = {
                query,
                context: ""
            };

            if (!query.trim()) {
                return;
            }

            const context = data.context;
            const prompt = that.options.systemPrompt(context, query);

            that.trigger(PROMPT_REQUEST, { prompt: prompt });

            if (that.options.isStreaming) {
                that._popup.element.find('.k-card').show();
            }

            if (that.options.service) {
                that.transport.read({ prompt, history: [], isRetry: false });
            }
        },

        _focusOutputActions() {
            let that = this,
                outputActionsContainer = that._popup.element.find('.k-actions'),
                outputActionsButtons = outputActionsContainer.children("button");

            if (!outputActionsContainer.find($(document.activeElement)).length) {
                $(outputActionsButtons).first().focus();
            }
            else if (outputActionsButtons.length == 0) {
                that._textArea.focus();
            }
        },

        focus: function() {
            let that = this;
            that.element.trigger(FOCUS);
        },

        setOptions: function(options) {
            let that = this;

            that.destroy();
            kendo.deepExtend(this.options, options);

            that.init(that.element, options);
        },

        destroy: function() {
            let that = this;

            that._popup?.close();
            that._textArea?.destroy();
            that._speechToTextButton?.destroy();
            that._aiContextMenu?.destroy();
            that.toolbar?.destroy();
            that._selectedView?.destroy();
            that._popup?.destroy();

            that.element.off(NS);

            Widget.fn.destroy.call(that);
        }
    });

    ui.plugin(InlineAIPrompt);

})(window.kendo.jQuery);
export default kendo;
