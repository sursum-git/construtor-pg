/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "./kendo.list.js";
import "./kendo.textbox.js";
import "./kendo.speechtotextbutton.js";
import "./kendo.segmentedbutton.js";
import { addInputPrefixSuffixContainers } from "./utils/prefix-suffix-containers.js";

export const __meta__ = {
    id: "smartbox",
    name: "SmartBox",
    category: "web",
    description: "The SmartBox is a new AI-powered capability designed to enhance standard search fields across key UI components.",
    depends: ["list", "speechtotextbutton", "textbox", "segmentedbutton"]
};

(function($, undefined) {
    const kendo = window.kendo;
    const encode = kendo.htmlEncode;
    const ui = kendo.ui;
    const List = kendo.ui.List;
    const DataSource = kendo.data.DataSource;
    const keys = kendo.keys;
    const ns = ".kendoSmartBox";
    const FOCUSED = "k-focus";
    const DISABLED = "k-disabled";
    const FOCUS = "focus";
    const BLUR = "blur";
    const OPEN = "open";
    const CLOSE = "close";
    const INPUT = "input";
    const KEYDOWN = "keydown";
    const CLICK = "click";
    const AI_ASSISTANT_PROMPT_REQUEST = "aiAssistantPromptRequest";
    const AI_ASSISTANT_RESPONSE_SUCCESS = "aiAssistantResponseSuccess";
    const AI_ASSISTANT_RESPONSE_ERROR = "aiAssistantResponseError";
    const AI_ASSISTANT_CANCEL_REQUEST = "aiAssistantCancelRequest";
    const STREAMING_UPDATE = "streamingUpdate";
    const SEARCH = "search";
    const SEMANTIC_SEARCH = "semanticSearch";

    const MODES = {
        search: "Search",
        semanticSearch: "SemanticSearch",
        aiAssistant: "AIAssistant"
    };

    const HISTORY_MAP = {
        [MODES.search]: "_searchHistory",
        [MODES.semanticSearch]: "_semanticSearchHistory",
        [MODES.aiAssistant]: "_aiAssistantHistory"
    };

    const DEFAULT_ITEM_FIELD = "value";
    const SEARCH_MODE_OPTIONS_ROLE = "_searchModeOptions";
    const DEFAULT_POPUP_MAX_HEIGHT = "320px";
    const DEFAULT_INPUT_WIDTH = "450px";
    const DEFAULT_INPUT_MIN_WIDTH = "350px";

    const getFirstValidActivatedMode = (activeModeOption, enabledModes) => {
        const includesDefaultMode = enabledModes.indexOf(MODES.search) > -1;
        const isIncluded = enabledModes.indexOf(activeModeOption) > -1;

        if (enabledModes.length > 1) {
            if (!activeModeOption) {
                return includesDefaultMode ? MODES.search : enabledModes[0];
            }


            if (isIncluded) {
                return activeModeOption;
            } else if (includesDefaultMode) {
                return MODES.search;
            } else {
                return enabledModes[0];
            }

        }

        return isIncluded ? activeModeOption : enabledModes[0];

    };

    const SmartBox = List.extend({
        init: function(element, options) {
            var that = this;

            that.ns = ns;

            List.fn.init.call(that, element, options);
            options = that.options;

            that._enabledModes = that._getEnabledModes();
            that._activeMode = getFirstValidActivatedMode(options.activeMode, that._enabledModes);

            that._input();
            that._wrapper();
            that._popup();

            that._dataSource();

            that._initList();
            that._setHeaderTemplate();
            that._header();

            that._aiTransport();
            that._addPromptSuggestions();
            that._addPrefixSuffix();

            if (!that._enabledModes.length && that.options.searchSettings?.enabled === false) {
                that._textBox.enable(false);
            };

            that._bindEvents();
            kendo.notify(that);
        },

        options: {
            name: "SmartBox",
            activeMode: "Search",
            placeholder: "",
            size: undefined,
            rounded: undefined,
            fillMode: undefined,
            history: {
                size: 5,
                timestampFormat: 'h:mm'
            },
            searchSettings: {
                delay: 300,
                enabled: true,
            },
            semanticSearchSettings: null,
            aiAssistantSettings: null,
            suggestionTemplate: null,
            historyItemTemplate: null,
            messages: {
                noPreviousSearches: "No previous searches",
                noPreviousPrompts: "No previous prompts",
                previousSearches: "Previously Searched",
                previousPrompts: "Previously Asked",
                suggestedPrompts: "Suggested Prompts",
                searchModeLabel: "Search",
                searchModeDescription: "Looks for exact word matches across your data",
                searchPlaceholder: "Search",
                semanticSearchModeLabel: "Semantic Search",
                semanticSearchModeDescription: "Understands context to surface the most relevant results.",
                semanticSearchPlaceholder: "Semantic Search",
                semanticSearchButtonText: "Search",
                aiAssistantPlaceholder: "Sort, filter or group with AI",
                speechToText: "Speech to text",
                speechToTextAriaLabel: "Start speech recognition",
                cancel: "Cancel",
                send: "Send",
                searchButtonText: "Search",
                aiAssistantButtonText: "AI Assistant",
            }
        },

        events: [
            OPEN,
            CLOSE,
            FOCUS,
            BLUR,
            AI_ASSISTANT_PROMPT_REQUEST,
            AI_ASSISTANT_RESPONSE_SUCCESS,
            AI_ASSISTANT_RESPONSE_ERROR,
            AI_ASSISTANT_CANCEL_REQUEST,
            STREAMING_UPDATE,
            SEARCH,
            SEMANTIC_SEARCH
        ],

        _dataSource: function() {
            const that = this;

            if (that.dataSource && that._refreshHandler) {
                that.dataSource.off();
                that.dataSource = null;
            }

            that.dataSource = DataSource.create({
                data: [],
                group: {
                    field: "role",
                    compare: that._compareGroups
                }
            });
        },

        _compareGroups: function(a, b) {
            const aOrder = a.items?.[0]?._groupOrder ?? 99;
            const bOrder = b.items?.[0]?._groupOrder ?? 99;

            return aOrder - bOrder;
        },

        _aiTransport: function() {
            const that = this;

            that.transport = new kendo.data.AiTransport({
                service: that._getAIServiceConfig(),
                success: that._aiServiceSuccess.bind(that),
                error: that._aiServiceError.bind(that),
                requestStart: () => that._aiRequestStart()
            });
        },

        _addPromptSuggestions: function() {
            const that = this;
            const options = that.options;
            const aiSettings = options.aiAssistantSettings;

            if (!aiSettings || !aiSettings.promptSuggestions) {
                return;
            }

            that._suggestions = aiSettings?.promptSuggestions?.map(item => ({
                value: item,
                suggestion: item,
                role: options.messages.suggestedPrompts,
                groupIcon: "clock-arrow-rotate",
                _isSuggestion: true,
                _groupOrder: 1,
            })) || [];
        },

        _aiRequestStart: function() {
            const that = this;

            that._isLoading = true;
            that._addPrefixSuffix();
        },

        _aiServiceSuccess: function(output) {
            const that = this;

            that._currentRequest = null;
            that._isLoading = false;
            that._addPrefixSuffix();

            const eventArgs = {
                prompt: output.prompt,
                response: output.response
            };

            if (!that.trigger(AI_ASSISTANT_RESPONSE_SUCCESS, eventArgs)) {
                that._pushValueToHistory(output.prompt);
            }
        },

        _aiServiceError: function(output) {
            const that = this;

            that._currentRequest = null;

            // Check if the request was aborted - don't trigger error event
            if (output.response && output.response.statusText === "abort") {
                return;
            }

            that._isLoading = false;
            that._addPrefixSuffix();

            const eventArgs = {
                output: output.output,
                prompt: output.prompt,
                response: output.response
            };

            that.trigger(AI_ASSISTANT_RESPONSE_ERROR, eventArgs);
        },

        cancelAIRequest: function() {
            const that = this;

            if (that._isLoading) {
                if (that._currentRequest && that._currentRequest.abort) {
                    that._currentRequest.abort();
                    that._currentRequest = null;
                }
                that._isLoading = false;
                that._addPrefixSuffix();
                that.trigger(AI_ASSISTANT_CANCEL_REQUEST);
            }
        },

        sendAIPrompt: function(prompt, options) {
            const that = this;

            if (!that.transport) {
                return;
            }

            options = options || {};
            const history = that._aiAssistantHistory || [];
            const service = that._getAIServiceConfig();

            const request = that.transport.read({
                prompt: prompt,
                history: options.history || history.map(item => ({
                    role: "user",
                    contents: [{ $type: "text", text: item.value }]
                })),
                service: service
            });
            that._currentRequest = request;

            if (request && typeof request.always === "function") {
                request.always(() => that.value(""));
            } else if (request && typeof request.finally === "function") {
                request.finally(() => that.value(""));
            }
        },

        startStreaming: function() {
            const that = this;
            that._isLoading = true;
            that._addPrefixSuffix();
        },

        stopStreaming: function() {
            const that = this;
            that._isLoading = false;
            that._addPrefixSuffix();
        },

        _wrapper: function() {
            let wrapper = this.element.parent();

            this.wrapper = wrapper.addClass("k-smart-box").removeClass("k-textbox");
            this._focused = this.element;
        },

        _input: function() {
            const that = this;
            const options = that.options;

            that.element.attr({
                placeholder: that._getPlaceholder(),
                autocomplete: "off",
                "aria-expanded": false
            }).css({
                width: DEFAULT_INPUT_WIDTH,
                minWidth: DEFAULT_INPUT_MIN_WIDTH
            });

            that._textBox = that.element.kendoTextBox({
                size: options.size,
                rounded: options.rounded,
                fillMode: options.fillMode,
                clearButton: that._isSearchMode()
            }).data("kendoTextBox");

            if (that._textBox._clear) {
                that._textBox._clear.off(CLICK);
                that._textBox._clear.on(CLICK + ns, function(e) {
                    e.preventDefault();
                    that._onClearClick();
                });
            }
        },

        _onClearClick: function() {
            const that = this;

            that.value("");
            that._executeSearch("");
            that._textBox._hideClear();
            that.focus();
        },

        _addPrefixSuffix: function() {
            const that = this;
            const mode = that._activeMode;
            const isLoading = that._isLoading;

            const clearButton = that._textBox?._clear;
            if (clearButton && clearButton.length) {
                clearButton.detach();
            }

            that.wrapper.find(".k-input-prefix, .k-input-suffix").remove();
            if (that._speechToTextButton) {
                that._speechToTextButton.destroy();
                that._speechToTextButton = null;
            }

            let prefixOptions = {};
            let suffixOptions = {};
            const modeSettings = that._getModeSettings(mode);
            const hasSpeechToText = modeSettings.speechToTextButton;

            if (mode === MODES.search) {
                prefixOptions = {
                    icon: "search"
                };
            } else if (mode === MODES.semanticSearch) {
                prefixOptions = {
                    icon: "zoom-sparkle",
                    iconClass: "k-accent-icon"
                };
            } else if (mode === MODES.aiAssistant) {
                prefixOptions = {
                    icon: "sparkles",
                    iconClass: "k-accent-icon"
                };

                suffixOptions = {
                    template: () => that._renderAISuffixTemplate(isLoading, hasSpeechToText, that.options.messages)
                };
            }

            addInputPrefixSuffixContainers({
                widget: that,
                wrapper: that.wrapper,
                options: {
                    prefixOptions: prefixOptions,
                    suffixOptions: suffixOptions
                },
                prefixInsertBefore: that.element
            });

            if (that._isSearchMode() && clearButton && clearButton.length) {
                let suffix = that.wrapper.find(".k-input-suffix");
                if (!suffix.length) {
                    suffix = $('<span class="k-input-suffix"></span>');
                    that.wrapper.append(suffix);
                }
                suffix.append(clearButton);
            }

            if (mode === MODES.aiAssistant && hasSpeechToText) {
                that._initSpeechToTextButton();
            }

           that._toggleClearButtonPerMode();
        },

        _toggleClearButtonPerMode: function() {
            const that = this;
            const mode = that._activeMode;
            const isLoading = that._isLoading;

            if (mode === MODES.aiAssistant) {
                that._bindSuffixEvents(isLoading);
                if (that._textBox && that._textBox._clear) {
                    that._textBox._clear.hide();
                }
            } else if (that._textBox && that._textBox._clear) {
                that._textBox._clear.show();

                if (that.value()) {
                    that._textBox._showClear();
                } else {
                    that._textBox._hideClear();
                }
            }
        },

        _renderAISuffixTemplate: function(isLoading, hasSpeechToText, messages) {
            const that = this;
            let template = "";

            if (hasSpeechToText) {
                template += `<button title="${encode(messages.speechToText)}" aria-label="${encode(messages.speechToTextAriaLabel)}" ref='smartbox-speech-to-text'></button>`;
            }

            if (isLoading) {
                template += kendo.html.renderButton(`<button class='k-smart-box-send' ref='smartbox-cancel' title='${encode(messages.cancel)}'></button>`, {
                    icon: "stop-sm",
                    fillMode: "solid",
                    themeColor: "base",
                    size: that.options.size,
                    rounded: "full"
                });
            } else {
                template += kendo.html.renderButton(`<button class='k-smart-box-send${!that.value() ? ` ${DISABLED}` : ""}' ref='smartbox-send' title='${encode(messages.send)}'></button>`, {
                    icon: "arrow-up-outline",
                    fillMode: "solid",
                    size: that.options.size,
                    themeColor: "base",
                    rounded: "full",
                });
            }

            return template;
        },

        _initSpeechToTextButton: function() {
            const that = this;
            const modeSettings = that._getModeSettings();
            const speechToTextOptions = modeSettings.speechToTextButton;
            const SpeechToTextButton = kendo.ui.SpeechToTextButton;

            if (!SpeechToTextButton || !speechToTextOptions) {
                return;
            }

            const element = that.wrapper.find("[ref='smartbox-speech-to-text']");

            if (!element.length) {
                return;
            }

            const options = $.extend({}, {
                themeColor: "base",
                fillMode: "clear",
                size: that.options.size,
                integrationMode: speechToTextOptions.integrationMode || "webSpeech",
                lang: speechToTextOptions.lang || "en-US",
                continuous: speechToTextOptions.continuous || false,
                interimResults: speechToTextOptions.interimResults || false,
                maxAlternatives: speechToTextOptions.maxAlternatives || 1,
                result: function(ev) {
                    if (ev.isFinal && ev.alternatives && ev.alternatives.length > 0) {
                        const currentValue = that.value() || "";
                        const newValue = currentValue + (currentValue ? " " : "") + ev.alternatives[0].transcript;

                        that.value(newValue);
                        that.element.trigger("input");
                    }
                }
            }, speechToTextOptions);

            that._speechToTextButton = new SpeechToTextButton(element, options);
        },

        _bindSuffixEvents: function(isLoading) {
            const that = this;

            that.wrapper.find("[ref='smartbox-send'], [ref='smartbox-cancel']").off(ns);

            if (isLoading) {
                that.wrapper.find("[ref='smartbox-cancel']").on(CLICK + ns, function(e) {
                    e.preventDefault();
                    that.cancelAIRequest();
                });
            } else {
                that.wrapper.find("[ref='smartbox-send']").on(CLICK + ns, function(e) {
                    e.preventDefault();
                    const value = that.value();
                    if (value) {
                        that._triggerAIAssistantRequest(value);
                    }
                });
            }
        },

        _getModeSettings: function(mode) {
            const that = this;
            const options = that.options;
            const getMode = mode || that._activeMode;

            if (getMode === MODES.search) {
                return options.searchSettings || {};
            } else if (getMode === MODES.semanticSearch) {
                return options.semanticSearchSettings || {};
            } else if (getMode === MODES.aiAssistant) {
                return options.aiAssistantSettings || {};
            }

            return {};
        },

        _getPlaceholder: function() {
            const that = this;
            const options = that.options;
            const mode = that._activeMode;
            const messages = options.messages;
            const settings = that._getModeSettings(mode);

            let defaultPlaceholder = "";

            switch (mode) {
                case MODES.search:
                    defaultPlaceholder = messages.searchPlaceholder;
                    break;
                case MODES.semanticSearch:
                    defaultPlaceholder = messages.semanticSearchPlaceholder;
                    break;
                case MODES.aiAssistant:
                    defaultPlaceholder = messages.aiAssistantPlaceholder;
                    break;
            }

            return settings?.placeholder || options.placeholder || defaultPlaceholder;
        },

        _popup: function() {
            this.options.popup = $.extend(this.options.popup, { copyAnchorStyles: true });
            List.fn._popup.call(this);
        },

        value: function(val) {
            var that = this;

            if (val === undefined) {
                return that._textBox ? that._textBox.value() : that.element.val();
            }

            if (that._textBox) {
                that._textBox.value(val);
            } else {
                that.element.val(val);
            }
        },

        enable: function(enable) {
            var that = this;

            if (that._textBox) {
                that._textBox.enable(enable);
            }

            that.wrapper.toggleClass(DISABLED, enable === false);
        },

        readonly: function(readonly) {
            var that = this;

            if (that._textBox) {
                that._textBox.readonly(readonly);
            }
        },

        focus: function() {
            var that = this;

            if (that._textBox) {
                that._textBox.focus();
            } else {
                that.element[0].focus();
            }
        },

        _isSearchMode: function() {
            const that = this;
            const mode = that._activeMode;

            return mode === MODES.search || mode === MODES.semanticSearch;
        },

        _handleItemClick: function(item) {
            const that = this;
            const mode = that._activeMode;

            if (!item) {
                return;
            }

            const textEl = item.find(".k-list-item-text");
            const value = textEl.length ? textEl.first().text() : item.text();

            that.value(value);
            that._oldValue = value;

            if (mode === MODES.search) {
                that._executeSearch(value);
            }

            if (that._textBox && that._isSearchMode()) {
                that._textBox._showClear();
            }

            const aiSendButton = that.wrapper.find("[ref='smartbox-send']");

            if (mode === MODES.aiAssistant && aiSendButton.length) {
                aiSendButton.removeClass(DISABLED);
            }

            that.close();
        },

        _handleActionItem: function(e) {
            const that = this;
            const action = e.action;

            if (action && action.indexOf("switchMode:") === 0) {
                const mode = action.replace("switchMode:", "");
                that._switchMode(mode);
            }
        },

        _listBound: function() {
            const that = this;
            const data = that.dataSource.flatView();
            const length = data.length;
            const groupsLength = that.dataSource._group ? that.dataSource._group.length : 0;

            that._renderNoData();
            that._toggleNoData(!length && that._shouldRenderModeButton());
            that._toggleHeader(!!groupsLength && !!length);

            that._removeSearchModeOptionsGroupHeader();

            that._applyListItemTooltips();
        },

        _applyListItemTooltips: function() {
            const that = this;

            if (!that.list) {
                return;
            }

            const listItems = that.list.find(".k-list-item:not(.k-list-group-item):not([data-action])");
            listItems.each(function() {
                const item = $(this);
                const textEl = item.find(".k-list-item-text");
                const text = textEl.length ? textEl.text() : item.text();
                item.attr("title", text.trim());
            });
        },

        _renderNoData: function() {
            const that = this;
            const noData = that.noData;

            if (!noData) {
                return;
            }

            const messages = that.options.messages;
            const isAIMode = that._activeMode === MODES.aiAssistant;
            const icon = "file-report";
            const text = isAIMode ? messages.noPreviousPrompts : messages.noPreviousSearches;

            const html = kendo.ui.icon({ icon: icon, iconClass: "k-icon-xxxl" }) + '<span>' + encode(text) + '</span>';

            noData.addClass("k-smart-box-no-data").html(html);
        },

        _removeSearchModeOptionsGroupHeader: function() {
            const that = this;

            if (!that.listView || !that.listView.content || !that._shouldRenderSearchModeOptions() || !that._isSearchMode()) {
                return;
            }

            that.listView.content.find(".k-list-group-item").each(function() {
                const $groupItem = $(this);
                if ($groupItem.text().trim() === "") {
                    $groupItem.remove();
                }
            });
        },

        _updateCssClasses: function() {
            const that = this;
            const popup = that.popup;

            popup.element.removeClass("k-list-container").addClass("k-smart-box-popup");
            that.list.find(".k-list-content").removeClass("k-list-scroller");
        },

        _listOptions: function(options) {
            const that = this;

            return $.extend({
                autoBind: false,
                selectable: false,
                dataSource: that.dataSource,
                click: function(e) {
                    that._handleItemClick(e.item);
                },
                action: function(e) {
                    that._handleActionItem(e);
                },
                dataBound: that._listBound.bind(that),
                dataValueField: DEFAULT_ITEM_FIELD,
                dataTextField: DEFAULT_ITEM_FIELD,
                iconField: "icon",
                descriptionField: "description",
                actionField: "action",
                groupIconField: "groupIcon",
                fixedGroupHeader: false,
                template: that._getItemTemplate(),
                groupTemplate: function(group) {
                    if (group === SEARCH_MODE_OPTIONS_ROLE) {
                        return "";
                    }
                    return encode(group);
                }
            }, options);
        },

        _getItemTemplate: function() {
            const that = this;
            const suggestionTemplate = that.options.suggestionTemplate;
            const historyItemTemplate = that.options.historyItemTemplate;

            if (!suggestionTemplate && !historyItemTemplate) {
                return null;
            }

            const compiledSuggestionTemplate = suggestionTemplate ? kendo.template(suggestionTemplate) : null;
            const compiledHistoryItemTemplate = historyItemTemplate ? kendo.template(historyItemTemplate) : null;

            return function(dataItem) {
                if (dataItem._isSuggestion && compiledSuggestionTemplate) {
                    return compiledSuggestionTemplate(dataItem);
                }

                if (dataItem._isHistoryItem && compiledHistoryItemTemplate) {
                    return compiledHistoryItemTemplate(dataItem);
                }

                return encode(dataItem.value || "");
            };
        },

        _setHeaderTemplate: function() {
            const that = this;

            if (!that._shouldRenderModeButton()) {
                return;
            }

            that.options.headerTemplate = function() {
                return `<div ref="modeSwitcher"></div>`;
            };
        },

        _initModeButton: function() {
            const that = this;
            const messages = that.options.messages;

            if (!that._shouldRenderModeButton() || !that.header) {
                return;
            }

            const container = that.header;

            let selectedValue = that._isSearchMode() ? MODES.search : MODES.aiAssistant;
            let searchModeButton = { value: MODES.search, text: messages.searchButtonText, icon: "search" };

            if (that._enabledModes.indexOf(MODES.search) < 0) {
                searchModeButton = {
                    value: MODES.semanticSearch,
                    text: messages.semanticSearchButtonText,
                    icon: "zoom-sparkle",
                    iconClassOnSelection: "k-accent-icon"
                };

                selectedValue = that._activeMode;
            }

            that._segmentedButton = container.kendoSegmentedButton({
                size: that.options.size,
                selected: selectedValue,
                stretched: true,
                items: [
                    searchModeButton,
                    { value: MODES.aiAssistant, text: messages.aiAssistantButtonText, icon: "sparkles", iconClassOnSelection: "k-accent-icon" }
                ],
                select: function(e) {
                    that._switchMode(e.value);
                }
            }).data("kendoSegmentedButton");
        },

        _switchMode: function(mode) {
            const that = this;
            const currentMode = that._activeMode;

            if (that._isSearchMode() && mode === MODES.aiAssistant) {
                that._lastSearchMode = currentMode;
            }

            if (mode === MODES.search && currentMode === MODES.aiAssistant && that._lastSearchMode) {
                mode = that._lastSearchMode;
            }

            that._activeMode = mode;

            that.element.attr("placeholder", that._getPlaceholder());

            that.value("");

            that._addPrefixSuffix();

            that._updateHeaderActiveState();

            that._showViewPopup();
        },

        _updateHeaderActiveState: function() {
            const that = this;

            if (!that._segmentedButton) {
                return;
            }

            that._segmentedButton.value(that._activeMode);
        },

        _shouldRenderSearchModeOptions: function() {
            const enabledModes = this._enabledModes;

            return enabledModes.includes(MODES.search) && enabledModes.includes(MODES.semanticSearch);
        },

        _getSearchModeOptions: function() {
            const that = this;
            const messages = that.options.messages;
            const enabledModes = that._enabledModes;
            const options = [];

            if (enabledModes.includes(MODES.search)) {
                const isActive = that._activeMode === MODES.search;
                options.push({
                    value: messages.searchModeLabel,
                    description: messages.searchModeDescription,
                    icon: isActive ? "check" : null,
                    action: "switchMode:" + MODES.search,
                    mode: MODES.search,
                    role: SEARCH_MODE_OPTIONS_ROLE,
                    _groupOrder: 2
                });
            }

            if (enabledModes.includes(MODES.semanticSearch)) {
                const isActive = that._activeMode === MODES.semanticSearch;
                options.push({
                    value: messages.semanticSearchModeLabel,
                    description: messages.semanticSearchModeDescription,
                    icon: isActive ? "check" : null,
                    action: "switchMode:" + MODES.semanticSearch,
                    mode: MODES.semanticSearch,
                    role: SEARCH_MODE_OPTIONS_ROLE,
                    _groupOrder: 2
                });
            }

            return options;
        },

        _shouldRenderModeButton: function() {
            const enabledModes = this._enabledModes;

            const hasSearchMode = enabledModes.includes(MODES.search) || enabledModes.includes(MODES.semanticSearch);
            const hasAIAssistant = enabledModes.includes(MODES.aiAssistant);

            return hasSearchMode && hasAIAssistant;
        },

        _shouldRenderNoData: function() {
            const that = this;

            return that._shouldRenderModeButton() && that.dataSource.data().length === 0;
        },

        _getEnabledModes: function() {
            const that = this;
            const options = that.options;
            const modes = [];

            if (options.searchSettings && options.searchSettings.enabled !== false) {
                modes.push(MODES.search);
            }
            if (options.semanticSearchSettings && options.semanticSearchSettings.enabled !== false) {
                modes.push(MODES.semanticSearch);
            }
            if (options.aiAssistantSettings && options.aiAssistantSettings.enabled !== false) {
                modes.push(MODES.aiAssistant);
            }


            return modes;
        },

        _bindEvents: function() {
            const that = this;
            that.element
                .on(`${FOCUS}${ns}`, () => that._onFocus())
                .on(`${BLUR}${ns}`, () => that._onBlur())
                .on(`${KEYDOWN}${ns}`, (e) => that._onKeydown(e))
                .on(`${INPUT}${ns}`, (e) => that._onInput(e));

            that.wrapper.on(`${CLICK}${ns}`, ".k-input-prefix", () => {
                if (that.popup.visible()) {
                    that.close();
                }

            that.element.trigger(FOCUS + ns);
        });

            that.popup.one(OPEN, () => {
                that._updateCssClasses();
                that.popup.element.css({
                    "margin-top": "2px"
                });
                that.popup.element.find(".k-list").css({
                    "max-height": DEFAULT_POPUP_MAX_HEIGHT
                });
                that._initModeButton();
            });

            that.popup.bind("activate", () => {
                if (that.listView) {
                    that.listView.select([]);
                    that.current(-1);
                }

                if (that._segmentedButton) {
                    that._segmentedButton._calculateThumbPosition();
                }
            });
        },

        _unbindEvents: function() {
            const that = this;
            that.element.off(ns);
            that.wrapper.off(ns);
        },

        _onFocus: function() {
            const that = this;
            const mode = that._activeMode;
            const hasHistory = that[HISTORY_MAP[mode]]?.length > 0;
            const hasSuggestions = mode === MODES.aiAssistant && that._suggestions?.length > 0;
            const shouldRenderModeButton = that._shouldRenderModeButton();
            const shouldRenderSearchModeOptions = that._shouldRenderSearchModeOptions() && that._isSearchMode();

            const shouldShowPopup = hasHistory || hasSuggestions || shouldRenderModeButton || shouldRenderSearchModeOptions;

            that.wrapper.addClass(FOCUSED);

            if (shouldShowPopup) {
                that._showViewPopup();
            }
            that.trigger(FOCUS);
        },

        _onBlur: function() {
            if (!this.trigger(BLUR)) {
                this.wrapper.removeClass(FOCUSED);
                this.close();
            }
        },

        _onKeydown: function(e) {
            const that = this;
            const key = e.keyCode;
            const mode = that._activeMode;
            const value = that.element.val();

            if (key === keys.ENTER) {
                if (value) {
                    that.close();
                    if (mode === MODES.aiAssistant) {
                        that._triggerAIAssistantRequest(value);
                    } else {
                        clearTimeout(that._searchTimeout);
                        that._executeSearch(value);
                    }
                }
                e.preventDefault();
            } else if (key === keys.ESC) {
                that.close();
            }
        },

        _executeSearch: function(value) {
            const that = this;
            const mode = that._activeMode;

            if (!value && value !== "") {
                return;
            }

            if (mode === MODES.search) {
                if (!that.trigger(SEARCH, { searchValue: value })) {
                    that._search(value);
                    that._pushValueToHistory(value);
                }
            } else if (mode === MODES.semanticSearch) {
                that.trigger("semanticSearch", { searchValue: value });
                that._pushValueToHistory(value);
            }
        },

        _onInput: function(e) {
            const that = this;
            const options = that.options;
            const mode = that._activeMode;
            const value = that.element.val();

            const isDeleting = that._oldValue && value.length < that._oldValue.length;

            that.wrapper.find("[ref='smartbox-send']")?.toggleClass(DISABLED, !value);

            if (isDeleting && value === "") {
                that._showViewPopup();
            } else {
                that.close();
            }

            if (that._isSearchMode()) {
                let delay;

                if (mode === MODES.search) {
                    delay = options.searchSettings?.delay || 300;
                }

                if (mode === MODES.semanticSearch) {
                    delay = options.semanticSearchSettings?.delay || 900;
                }

                clearTimeout(that._searchTimeout);

                that._searchTimeout = setTimeout(() => {
                    that._executeSearch(value);
                }, delay);
            }
            that.value(value);
            that._oldValue = value;
        },

        _showViewPopup: function() {
            const that = this;
            const mode = that._activeMode;
            const hasSuggestions = that._suggestions?.length > 0;
            const shouldRenderModeButton = that._shouldRenderModeButton();
            const shouldRenderSearchModeOptions = that._shouldRenderSearchModeOptions();
            const isSearchMode = that._isSearchMode();
            const historySettings = that._getHistorySettings(mode);
            const historyEnabled = historySettings.enabled !== false;
            let history = historyEnabled ? (that[HISTORY_MAP[mode]] || []) : [];
            const hasHistory = history.length > 0;

            if (!hasHistory && !hasSuggestions && !shouldRenderModeButton && !(shouldRenderSearchModeOptions && isSearchMode)) {
                return;
            }

            if (shouldRenderSearchModeOptions && isSearchMode) {
                const searchModeOptions = that._getSearchModeOptions();
                history = [...searchModeOptions, ...history];
            }

            if (mode === MODES.aiAssistant && hasSuggestions) {
                history = [...that._suggestions, ...history];
            }

            that._setListData(history);
            that.open();
        },

        _setListData: function(data) {
            var that = this;

            if (!data) {
                data = [];
            }

            data.sort((a, b) => (a._groupOrder || 0) - (b._groupOrder || 0));

            that.dataSource.data(data);
        },


        _getHistorySettings: function(mode) {
            const that = this;
            const options = that.options;
            const getMode = mode || that._activeMode;
            const modeSettings = that._getModeSettings(getMode);
            const componentHistory = options.history;
            const modeHistory = modeSettings?.history;

            if (modeHistory === false) {
                return { enabled: false, size: 0, timestampFormat: null };
            }

            if (componentHistory === false) {
                return { enabled: false, size: 0, timestampFormat: null };
            }

            const globalSettings = componentHistory || {};
            const modeHistorySettings = modeHistory || {};

            let size, timestampFormat;

            if (modeHistorySettings.size !== undefined) {
                size = modeHistorySettings.size;
            } else if (globalSettings.size !== undefined) {
                size = globalSettings.size;
            }

            if (modeHistorySettings.timestampFormat !== undefined) {
                timestampFormat = modeHistorySettings.timestampFormat;
            } else if (globalSettings.timestampFormat !== undefined) {
                timestampFormat = globalSettings.timestampFormat;
            }

            return { enabled: true, size, timestampFormat };
        },

        _pushValueToHistory: function(value) {
            const that = this;
            const mode = that._activeMode;
            const historySettings = that._getHistorySettings(mode);

            if (!value) {
                return;
            }

            // Check if history is disabled for this mode
            if (historySettings.enabled === false) {
                return;
            }

            const size = historySettings.size;
            const timestampFormat = historySettings.timestampFormat;

            if (size <= 0) {
                return;
            }

            let history = that[HISTORY_MAP[mode]] || [];

            if (history.length >= size) {
                history.pop();
            }

            const timestamp = new Date();

            history.unshift({
                value,
                role: that._isSearchMode() ? that.options.messages.previousSearches : that.options.messages.previousPrompts,
                groupIcon: "clock",
                timestamp: timestamp,
                timestampFormat: timestampFormat,
                description: kendo.toString(timestamp, timestampFormat),
                _groupOrder: 3,
                _isHistoryItem: true
            });

            that[HISTORY_MAP[mode]] = history;

            that.close();
        },

        _search: function(value) {
            const that = this;
            const options = that.options;
            const searchSettings = options.searchSettings || {};
            const dataSourceReference = searchSettings.dataSource || null;

            if (!dataSourceReference) {
                return;
            }
            const searchFields = searchSettings.fields || dataSourceReference.options?.search?.fields || null;

            let expression = { filters: [], logic: "or" };

            if (that.dataSourceReference.options.endless) {
                that.dataSourceReference.options.endless = null;
                that._endlessPageSize = that.dataSourceReference.options.pageSize;
            }

            if (value) {
                for (let i = 0; i < searchFields.length; i++) {
                    that._pushExpression(expression.filters, searchFields[i], value);
                }
            } else {
                expression = {};
            }

            dataSourceReference.filter(expression);
        },

        _getAIServiceConfig: function() {
            const that = this;
            const aiOptions = that.options.aiAssistantSettings || {};
            const serviceIsString = typeof aiOptions.service === "string";

            if (!aiOptions.service) {
                return {};
            }

            const url = serviceIsString ? aiOptions.service : aiOptions.service.url;

            if (url) {
                return {
                    ...(!serviceIsString ? aiOptions.service : {}),
                    url: url,
                };
            }

            return {};
        },

        _triggerAIAssistantRequest: function(prompt) {
            const that = this;

            if (!prompt) {
                return;
            }

            const history = that._aiAssistantHistory || [];

            const eventArgs = {
                prompt: prompt,
                history: history.map(item => ({
                    role: "user",
                    contents: [{ $type: "text", text: item.value }]
                })),
                service: that._getAIServiceConfig(),
            };

            if (that.trigger(AI_ASSISTANT_PROMPT_REQUEST, eventArgs)) {
                return;
            }

            if (that.transport) {
                that.sendAIPrompt(prompt, eventArgs);
            }

            that.close();
        },

        open: function() {
            const that = this;
            that.popup.open();
        },

        close: function() {
            const that = this;

            that.popup.close();
        },


        activeMode: function(mode) {
            if (mode === undefined) {
                return this._activeMode;
            }

            if (mode !== this._activeMode && this._getEnabledModes().indexOf(mode) > -1) {
                this._activeMode = mode;
                this.element.attr("placeholder", this._getPlaceholder());
            }
        },

        destroy: function() {
            const that = this;

            clearTimeout(that._searchTimeout);

            if (that.popup) {
                that.popup.unbind();
            }

            if (that.transport) {
                that.transport = null;
            }

            if (that._speechToTextButton) {
                that._speechToTextButton.destroy();
                that._speechToTextButton = null;
            }

            if (that._textBox) {
                that._textBox.destroy();
                that._textBox = null;
            }

            if (that._segmentedButton) {
                that._segmentedButton.destroy();
                that._segmentedButton = null;
            }

            that.wrapper.find("[ref='smartbox-send'], [ref='smartbox-cancel']").off(ns);
            that.element.off(ns);
            that.wrapper.off(ns);

            List.fn.destroy.call(that);
        }
    });

    ui.plugin(SmartBox);
})(window.kendo.jQuery);

export default kendo;

