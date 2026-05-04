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
import "./kendo.skeletoncontainer.js";
import "./kendo.speechtotextbutton.js";
import "./aiprompt/output-action-manager.js";
import "./aiprompt/template-builder.js";
import "./aiprompt/speech-manager.js";
import "./aiprompt/output-manager.js";
import "./aiprompt/views.js";

export const __meta__ = {
    id: "aiprompt",
    name: "AIPrompt",
    category: "web",
    description: "The AIPrompt component simplifies the incorporation of external AI services into apps.",
    depends: ["core", "icons", "textarea", "button", "toolbar", "panelbar", "data", "floatingactionbutton", "skeletoncontainer", "speechtotextbutton"],
};

(function($) {
    let kendo = window.kendo,
        Widget = kendo.ui.Widget,
        NS = ".kendoAIPrompt",
        ui = kendo.ui,
        extend = $.extend,

        COMMAND_EXECUTE = "commandExecute",
        PROMPT_REQUEST = "promptRequest",
        PROMPT_RESPONSE = "promptResponse",
        OUTPUT_RATING = "outputRating",
        OUTPUT_COPY = "outputCopy",
        OUTPUT_VIEW = "output",
        OUTPUT_ACTION = "outputAction",
        PROMPT_REQUEST_CANCEL = "promptRequestCancel",

        DEFAULT_OUTPUT_ACTIONS = ["copy", "retry", "spacer", "rating"],
        DEFAULT_OUTPUT_ACTIONS_WITHOUT_RATING = ["copy", "retry"],

        FOCUS = "focus",
        KDISABLED = "k-disabled";

    let cssClasses = {
        menuButton: "k-menu-button",
        aIPrompt: "k-prompt"
    };

    let defaultViews = {
        prompt: {
            type: "kendo.ui.AIPromptPromptView",
            name: "prompt",
            buttonIcon: "sparkles",
        },
        output: {
            type: "kendo.ui.AIPromptOutputView",
            name: "output",
            buttonIcon: "comment",
        },
        commands: {
            type: "kendo.ui.AIPromptCommandsView",
            name: "commands",
            buttonIcon: "more-horizontal",
        },
        custom: {
            type: "kendo.ui.AIPromptCustomView",
            name: "custom",
        }
    };

    let AIPrompt = Widget.extend({
        init: function(element, options) {
            let that = this;
            options = options || {};

            Widget.fn.init.call(that, element, options);

            if (that.options.views.length == 0) {
                that.options.views = ["prompt", "output"];

                if (this.options.promptCommands && this.options.promptCommands.length) {
                    this.options.views.push("commands");
                }
            }

            that.options.outputActions = (options.outputActions && options.outputActions.length > 0) ? options.outputActions : DEFAULT_OUTPUT_ACTIONS_WITHOUT_RATING;

            that.promptOutputs = that.options.promptOutputs || [];
            that.outputObjects = new Map();

            that.outputManager = new kendo.ui.AIPromptOutputManager(that);

            // Initialize output action manager at component level
            that.outputActions = kendo.ui.AIPromptOutputActionManager.processOutputActions(that.options.outputActions);
            that.outputActionManager = new kendo.ui.AIPromptOutputActionManager(that, {
                outputActions: that.outputActions
            });

            // Populate outputObjects if promptOutputs is not empty
            if (Array.isArray(that.promptOutputs) && that.promptOutputs.length > 0) {
                that.promptOutputs.forEach(output => {
                    if (!output.id) {
                        output.id = kendo.guid();
                    }
                    const outputObj = that.outputManager.createOutputObject(output);
                    that.outputObjects.set(output.id, outputObj);
                });
            }

            that._initLayout();
            that._initViews();
            that._initToolbar();
            that.activeView(that.options.activeView);

            if (that.options.service) {
                that.transport = new kendo.data.AiTransport({
                    service: that.options.service,
                    success: that._serviceSuccess.bind(that),
                    requestStart: () => kendo.ui.progress(that.element, true)
                });
            }

            kendo.notify(that);
        },

        options: {
            name: "AIPrompt",
            enabled: true,
            toolbarItems: [],
            promptOutputs: [],
            encodedPromptOutputs: true,
            activeView: 0,
            views: [],
            popup: null,
            speechToText: false,
            promptTextArea: null,
            messages: {
                promptView: "Ask AI",
                outputView: "Output",
                commandsView: "",
                customView: "Custom View",
                promptPlaceholder: "Ask or generate content with AI",
                promptSuggestions: "Prompt Suggestions",
                generateOutput: "Generate",
                outputTitle: "Generated with AI",
                outputRetryTitle: "Generated with AI",
                copyOutput: "Copy",
                retryGeneration: "Retry",
                ratePositive: "",
                rateNegative: "",
                stopGeneration: "Stop Generation"
            },
            showOutputRating: true,
            service: null,
            suffixTemplate: null,
            outputTemplate: null,
            outputActions: DEFAULT_OUTPUT_ACTIONS
        },

        events: [
            COMMAND_EXECUTE,
            PROMPT_REQUEST,
            PROMPT_RESPONSE,
            PROMPT_REQUEST_CANCEL,
            OUTPUT_RATING,
            OUTPUT_COPY,
            OUTPUT_ACTION
        ],

        _serviceSuccess: function(output) {
            const that = this;
            const outputViewIndex = that.viewsArray.findIndex(v => v.name === OUTPUT_VIEW);

            output.activeView = outputViewIndex;
            if (!that.trigger(PROMPT_RESPONSE, {
                output: output.output,
                prompt: output.prompt,
                outputId: output.id,
                isRetry: output.isRetry || false,
                response: output.response,
            })) {
                that.addPromptOutput(output);
                that.activeView(output.activeView);
                kendo.ui.progress(that.element, false);
            }

        },

        _initializeView: function(name) {
            let viewConfig = this.views[name];
            let view;
            if (viewConfig) {
                let type = viewConfig.type;

                if (typeof type === "string") {
                    type = kendo.getter(viewConfig.type)(window);
                }

                if (type) {
                    view = new type(this.element, extend(true, {
                        promptSuggestions: this.options.promptSuggestions,
                        promptCommands: this.options.promptCommands,
                        promptOutputs: this.promptOutputs,
                        showOutputRating: this.showOutputRating,
                        messages: this.options.messages,
                        showOutputSubtitleTooltip: this.options.showOutputSubtitleTooltip,
                        encodedPromptOutputs: this.options.encodedPromptOutputs,
                        promptSuggestionItemTemplate: this.options.promptSuggestionItemTemplate,
                        service: this.options.service,
                        speechToText: this.options.speechToText,
                        promptTextArea: this.options.promptTextArea,
                        outputActions: this.outputActions,
                        outputActionManager: this.outputActionManager,
                        outputTemplate: this.options.outputTemplate,
                    },
                        viewConfig
                    ));
                } else {
                    throw new Error("There is no such view");
                }
            }

            return view;
        },

        _unbindView: function(view) {
            if (view) {
                view.destroy();
            }
        },

        _initViews: function() {
            let that = this,
                options = that.options,
                views = options.views;

            that.views = {};
            that.viewsArray = [];

            for (let i = 0, l = views.length; i < l; i++) {
                let view = views[i];
                let isSettings = typeof view === "object";
                let name = view;

                if (isSettings) {
                    view = { ...view };
                    name = typeof view.type !== "string" ? view.name : view.type;
                }

                let defaultView = defaultViews[name];

                if (defaultView) {
                    if (isSettings) {
                        view.type = defaultView.type;
                    }

                    defaultView.buttonText = that.options.messages[`${name}View`];
                }

                view = Object.assign({ title: view.title, name, index: i }, defaultView, isSettings ? view : {});
                that.viewsArray.push(view);

                if (name) {
                    that.views[name] = view;
                }
            }
        },

        getViews: function() {
            return this.viewsArray;
        },

        activeView: function(name) {
            let that = this;
            if (name === undefined) {
                return that._activeViewIndex;
            }

            if (Number.isInteger(name)) {
                name = that.viewsArray[name].name;
            }

            if (name && that.views[name]) {
                if (that._selectedView) {
                    that._unbindView(that._selectedView);
                }

                that._selectedView = that._initializeView(name);
                that._activeViewIndex = that.viewsArray.findIndex(v => v.name === name);
                that._selectedView.render();

                that._updateToolbarState(that._activeViewIndex);

                let toolItem = $(that.toolbar._getAllItems()[that._activeViewIndex]);
                that.toolbar._resetTabIndex(toolItem);
                toolItem.trigger(FOCUS);
            }
        },

        addPromptOutput: function(output) {
            if (typeof output === 'string') {
                output = { output: output };
            }

            output.id = output.id || kendo.guid();

            const outputObj = this.outputManager.createOutputObject(output);
            this.promptOutputs.unshift(output);
            this.outputObjects.set(output.id, outputObj);

            if (this._selectedView && typeof this._selectedView.renderPromptOutput === "function") {
                this._selectedView.renderPromptOutput(output);

                if (output.isLoading) {
                    this.startStreaming();
                }
            }
        },

        removePromptOutput: function(output) {
                const that = this;
                let outputId = output;

                const removeOutput = (id) => {
                    const el = that.promptOutputs.find((el) => el.id === id);

                    if (el) {
                        that.promptOutputs = that.promptOutputs.filter((item) => item.id !== id);
                    }

                    that.outputObjects.delete(id);
                };

                if (outputId instanceof jQuery) {
                    outputId = output.data("id");
                    output.remove();
                    removeOutput(outputId);
                } else {
                    that.element.find("[data-id='" + outputId + "']").remove();
                    removeOutput(outputId);
                }
        },

        clearOutput: function() {
            const that = this;
            const elements = that.element.find("[data-id]");
            elements.each(function() {
                const id = $(this).data("id");
                that.outputObjects.delete(id);
            });
            elements.remove();
            that.promptOutputs = [];
        },

        _updateToolbarState: function(activeToolIndex) {
            let toolbar = this.toolbar;
            toolbar.element.find(".k-toolbar-toggle-button").each(function(index, elm) {
                toolbar.toggle($(elm), index == activeToolIndex);
            });
        },

        _initLayout: function() {
            let that = this,
                header = $("<div></div>").addClass("k-prompt-header");
            that.header = header;
            that.element.addClass(cssClasses.aIPrompt);
            that.element.append(header);
            const popupWrapper = that.element.closest('.k-popup');

            if (popupWrapper.length) {
                $(popupWrapper).addClass('k-prompt-popup');
            }
        },

        _getViewTools: function() {
            let that = this;

            return that.viewsArray.map(v => {
                if (v.name === 'commands') {
                    if (v.buttonText) {
                        v.title = v.buttonText;
                    } else {
                        v.title = "More Actions";
                    }
                }

                return {
                type: "button",
                text: v.buttonText,
                icon: v.buttonIcon,
                fillMode: "flat",
                themeColor: v.themeColor || "primary",
                rounded: "full",
                togglable: true,
                attributes: { title: v.title },
                toggle: function() {
                    that.activeView(v.name);
                }
            };
        });
        },

        _initToolbar: function() {
            let that = this;
            let items = that.options.toolbarItems;
            items = Array.isArray(items) ? items : [items];
            const closeButton = items.find(item => item.icon === 'x');

            if (closeButton) {
                closeButton.themeColor = 'base';
            }

            let toolbarEl = $("<div></div>").kendoToolBar({
                resizable: false,
                fillMode: "flat",
                items: that._getViewTools().concat(items)
            }).appendTo(that.header);

            that.toolbar = toolbarEl.data("kendoToolBar");
        },

        focus: function() {
            let that = this;
            that.element.trigger(FOCUS);
        },

        updatePromptOutputContent: function(content, outputId) {
            let that = this;

            return that.outputManager.updatePromptOutputContent(content, outputId);
        },

        startStreaming: function() {
            let that = this;

            if (that._selectedView && typeof that._selectedView.startStreaming === "function") {
                that._selectedView.startStreaming();
            }
        },

        stopStreaming: function() {
            let that = this;

            if (that._selectedView && typeof that._selectedView.stopStreaming === "function") {
                that._selectedView.stopStreaming();
            }

            if (that.outputManager) {
                that.outputManager.stopAllLoading();
            }
        },

        setOptions: function(options) {
            let that = this;
            let el = that.element;
            let originalOptions = that.options;
            let newOptions = extend({}, originalOptions, options);

            kendo.destroy(el);

            $(el).empty();

            that.init(el, newOptions);
        },

        destroy: function() {
            let that = this;

            if (that.toolbar) {
                that.toolbar.destroy();
                that.toolbar = null;
            }

            if (that._selectedView) {
                that._selectedView.destroy();
                that._selectedView = null;
            }

            if (that.outputManager) {
                that.outputManager.destroy();
                that.outputManager = null;
            }

            if (that.outputActionManager) {
                that.outputActionManager.destroy();
                that.outputActionManager = null;
            }

            that.promptOutputs = null;
            if (that.outputObjects) {
                that.outputObjects.clear();
                that.outputObjects = null;
            }

            if (that.transport) {
                that.transport = null;
            }

            that.element.off(NS);

            Widget.fn.destroy.call(that);
        }
    });

    ui.plugin(AIPrompt);

})(window.kendo.jQuery);
export default kendo;

