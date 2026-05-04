/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../kendo.html.button.js";
import "../kendo.icons.js";
import "../kendo.speechtotextbutton.js";
import "../kendo.textarea.js";
import "../kendo.textbox.js";
import "../kendo.upload.js";
import { Widget } from "../core/base/widget";
import { inject } from "../core/service-container";
import { HtmlService } from "../core/services/html.service";
import { UtilsService } from "../core/services/utils.service";
import { FileUtilsService } from "../core/services/file-utils.service";
import { DomUtilsService } from "../core/services/dom-utils.service";
import { NS, STYLES, REFERENCES, EVENTS, LINE_MODE, CLICK, FOCUSIN, FOCUSOUT } from "./constants";
import { renderPromptBox } from "./templates";
import { AccessibilityManager, ActionButtonManager, SpeechHandler, FileHandler, InputManager } from "./collaborators";
const DEFAULT_MESSAGES = {
    placeholder: "Type a message...",
    actionButton: "Send",
    actionButtonLoading: "Stop",
    messageBoxTitle: "Message",
    fileSelectButton: "Attach file"
};
/**
 * PromptBox widget - A composite input component for chat-like interfaces.
 *
 * Supports three modes:
 * - "single": Single-line text input
 * - "multi": Multi-line textarea with fixed rows
 * - "auto": Auto-expanding textarea (starts from 1 row)
 */
export class PromptBox extends Widget {
    /**
     * Constructs a new PromptBox instance.
     */
    constructor(element, options, utilsService = inject(UtilsService), htmlService = inject(HtmlService), fileUtilsService = inject(FileUtilsService), domUtilsService = inject(DomUtilsService)) {
        super(element, $.extend(true, {}, PromptBox.options, options));
        this.events = [
            EVENTS.valueChange,
            EVENTS.input,
            EVENTS.promptAction,
            EVENTS.fileSelect,
            EVENTS.fileRemove,
            EVENTS.speechToTextClick,
            EVENTS.speechToTextStart,
            EVENTS.speechToTextEnd,
            EVENTS.speechToTextError,
            EVENTS.speechToTextResult,
            EVENTS.focus,
            EVENTS.blur,
            EVENTS.inputFocus,
            EVENTS.inputBlur,
            EVENTS.multilineStateChange
        ];
        this._value = "";
        this._loading = false;
        this._readonly = false;
        this._hasFocus = false;
        this._fileInputId = "";
        this.utilsService = utilsService;
        this.htmlService = htmlService;
        this.fileUtilsService = fileUtilsService;
        this.domUtilsService = domUtilsService;
        this._value = this.options.value || "";
        this._loading = this.options.loading || false;
        this._fileInputId = "promptbox_file_" + this.utilsService.guid();
        this._initWrapper();
        this._initInputManager();
        this._initSpeechHandler();
        this._initFileHandler();
        this._attachEvents();
        this.fileHandler.renderAttachments();
        this._initAccessibilityManager();
        this._initActionButtonManager();
        this.bind(this.events, this.options);
        if (!this.options.enable) {
            this.enable(false);
        }
        if (this.options.readonly) {
            this.readonly(true);
        }
        if (this._value && this._getMode() === LINE_MODE.AUTO) {
            this.inputManager.updateAutoResize();
        }
        this.actionButtonManager.updateState();
    }
    /**
     * Gets or sets the input value.
     */
    value(newValue) {
        if (newValue === undefined) {
            return this._value;
        }
        this._value = newValue;
        this.inputManager.setValue(newValue);
        this.actionButtonManager.updateState();
    }
    /**
     * Enables or disables the PromptBox.
     * When enabling, this also clears the readonly state (similar to TextBox behavior).
     */
    enable(enabled = true) {
        const options = this.options;
        options.enable = enabled;
        if (enabled) {
            this._readonly = false;
            options.readonly = false;
            this.inputManager.setReadonly(false);
            this.wrapper.removeClass(STYLES.disabled);
        }
        else {
            this.wrapper.addClass(STYLES.disabled);
        }
        this.inputManager.enable(enabled);
        this.speechHandler.enable(enabled);
        this._updateFileSelectButtonState();
        this.actionButtonManager.updateState();
        this.accessibilityManager.updateWrapper({ disabled: !enabled });
        this.accessibilityManager.updateInputAccessibility();
    }
    /**
     * Gets or sets the read-only state of the PromptBox.
     */
    readonly(value) {
        if (value === undefined) {
            return this._readonly;
        }
        this._readonly = value;
        const options = this.options;
        options.readonly = value;
        this.inputManager.setReadonly(value);
        this.accessibilityManager.updateInputAccessibility();
        this.actionButtonManager.updateState();
        this.speechHandler.enable(!value);
        this._updateFileSelectButtonState();
    }
    /**
     * Gets or sets the loading state (shows stop button instead of send).
     */
    loading(value) {
        if (value === undefined) {
            return this._loading;
        }
        this._loading = value;
        this.accessibilityManager.updateWrapper({ loading: value });
        this.actionButtonManager.setLoading(value);
    }
    /**
     * Focuses the input element.
     */
    focus() {
        this.inputManager.focus();
    }
    /**
     * Blurs the input element.
     */
    blur() {
        this.inputManager.blur();
    }
    /**
     * Gets or sets the attached files.
     * @param newFiles - Optional array of files to set. If not provided, returns current files.
     * @returns Array of attached files when getting, void when setting.
     */
    files(newFiles) {
        if (newFiles === undefined) {
            return this.fileHandler.getFiles();
        }
        this.fileHandler.setFiles(newFiles);
    }
    /**
     * Clears all attached files.
     */
    clearFiles() {
        this.fileHandler.clearFiles();
    }
    /**
     * Destroys the widget.
     */
    destroy() {
        this._detachEvents();
        this._destroyCollaborators();
        super.destroy();
    }
    _destroyCollaborators() {
        this.fileHandler.destroy();
        this.speechHandler.destroy();
        this.inputManager.destroy();
    }
    /**
     * Updates widget options dynamically.
     * For complex changes (templates, buttons, mode), the widget is rebuilt.
     */
    setOptions(options) {
        var _a, _b, _c;
        const requiresRebuild = options.headerTemplate !== undefined ||
            options.startAffixTemplate !== undefined ||
            options.endAffixTemplate !== undefined ||
            options.topAffixTemplate !== undefined ||
            options.actionButton !== undefined ||
            options.actionButtonSettings !== undefined ||
            options.fileSelectButton !== undefined ||
            options.speechToTextButton !== undefined ||
            options.mode !== undefined;
        if (requiresRebuild) {
            const currentValue = this._value;
            const wasLoading = this._loading;
            const attachments = this.options.attachments;
            const currentFiles = this.fileHandler.getFiles();
            this._detachEvents();
            this._destroyCollaborators();
            super.setOptions(options);
            // Remove old wrapper completely (element was moved outside wrapper in _initWrapper)
            this.wrapper.remove();
            this._initWrapper();
            this._initInputManager();
            this._initSpeechHandler();
            this._initFileHandler();
            this._attachEvents();
            // Restore files that were attached before the rebuild
            if (currentFiles.length > 0) {
                this.fileHandler.setFiles(currentFiles);
            }
            this._initAccessibilityManager();
            this._initActionButtonManager();
            if (currentValue) {
                this.value(currentValue);
            }
            if (wasLoading) {
                this.loading(true);
            }
            if (attachments) {
                this.options.attachments = attachments;
                this.fileHandler.renderAttachments();
            }
            return;
        }
        super.setOptions(options);
        if (options.placeholder !== undefined || ((_a = options.messages) === null || _a === void 0 ? void 0 : _a.placeholder) !== undefined) {
            this.inputManager.updatePlaceholder();
        }
        if (((_b = options.messages) === null || _b === void 0 ? void 0 : _b.actionButton) !== undefined || ((_c = options.messages) === null || _c === void 0 ? void 0 : _c.actionButtonLoading) !== undefined) {
            this.actionButtonManager.updateMessages();
        }
        if (options.enable !== undefined) {
            this.enable(options.enable);
        }
        if (options.readonly !== undefined) {
            this.readonly(options.readonly);
        }
        if (options.loading !== undefined) {
            this.loading(options.loading);
        }
    }
    _initInputManager() {
        this.inputManager = new InputManager({
            wrapper: this.wrapper,
            getOptions: () => this.options,
            getMode: () => this._getMode(),
            callbacks: {
                onInput: () => this._handleInput(),
                onKeyDown: (e) => this._handleKeyDown(e),
                onFocus: () => this.trigger(EVENTS.inputFocus),
                onBlur: () => this.trigger(EVENTS.inputBlur),
                onMultilineStateChange: (isMultiline, wasMultiline) => {
                    this._handleAffixReorganization(isMultiline);
                    this.trigger(EVENTS.multilineStateChange, { isMultiline, wasMultiline });
                }
            }
        });
        this.inputManager.init();
    }
    /**
     * Reorganizes affixes when auto mode switches between single-line and multiline.
     * In multiline: startAffix content moves to endAffix (left side) with a spacer.
     * In single-line: startAffix content returns to its original container.
     */
    _handleAffixReorganization(isMultiline) {
        const options = this.options;
        if (!options.startAffixTemplate) {
            return;
        }
        const startAffix = this.wrapper.find(`[${REFERENCES.startAffix}]`);
        const endAffix = this.wrapper.find(`[${REFERENCES.endAffix}]`);
        if (!startAffix.length || !endAffix.length) {
            return;
        }
        if (isMultiline) {
            const startAffixContent = startAffix.contents().detach();
            startAffix.addClass(STYLES.hidden);
            const spacer = $(`<div class="${STYLES.spacer}" ${REFERENCES.affixSpacer}></div>`);
            endAffix.prepend(spacer);
            endAffix.prepend(startAffixContent);
        }
        else {
            const spacer = endAffix.find(`[${REFERENCES.affixSpacer}]`);
            if (spacer.length) {
                const startAffixContent = spacer.prevAll().detach();
                startAffix.empty().append(startAffixContent);
                startAffix.removeClass(STYLES.hidden);
                spacer.remove();
            }
        }
    }
    _initSpeechHandler() {
        this.speechHandler = new SpeechHandler({
            wrapper: this.wrapper,
            getSpeechToTextButtonOption: () => this.options.speechToTextButton,
            callbacks: {
                onStart: (e) => this.trigger(EVENTS.speechToTextStart, e),
                onEnd: (e) => this.trigger(EVENTS.speechToTextEnd, e),
                onResult: (e) => {
                    var _a, _b;
                    const transcript = ((_b = (_a = e.alternatives) === null || _a === void 0 ? void 0 : _a[0]) === null || _b === void 0 ? void 0 : _b.transcript) || "";
                    if (transcript) {
                        const currentValue = this._value || "";
                        const newValue = currentValue + (currentValue ? " " : "") + transcript;
                        this.value(newValue);
                    }
                    this.trigger(EVENTS.speechToTextResult, Object.assign(Object.assign({}, e), { transcript }));
                },
                onError: (e) => this.trigger(EVENTS.speechToTextError, e)
            }
        });
        this.speechHandler.init();
    }
    _initFileHandler() {
        this.fileHandler = new FileHandler({
            wrapper: this.wrapper,
            getOptions: () => this.options,
            htmlService: this.htmlService,
            fileUtilsService: this.fileUtilsService,
            utilsService: this.utilsService,
            domUtilsService: this.domUtilsService,
            callbacks: {
                onFilesChanged: () => this.actionButtonManager.updateState(),
                onFileSelect: (files) => this.trigger(EVENTS.fileSelect, { files }),
                onFileRemove: (file, remainingFiles) => this.trigger(EVENTS.fileRemove, { file, files: remainingFiles })
            }
        });
        this.fileHandler.initUpload();
    }
    _initAccessibilityManager() {
        this.accessibilityManager = new AccessibilityManager({
            wrapper: this.wrapper,
            getOptions: () => this.options,
            getInputElement: () => this.inputManager.getElement()
        });
        this.accessibilityManager.applyInitial();
    }
    _initActionButtonManager() {
        this.actionButtonManager = new ActionButtonManager({
            wrapper: this.wrapper,
            getMessages: () => this._getMessages(),
            getSettings: () => this.options.actionButton,
            isDisabled: () => {
                var _a;
                const options = this.options;
                const widgetDisabled = !options.enable;
                const widgetReadonly = options.readonly;
                const actionButtonDisabled = ((_a = options.actionButton) === null || _a === void 0 ? void 0 : _a.enable) === false;
                return widgetDisabled || widgetReadonly || actionButtonDisabled;
            },
            isLoading: () => this._loading,
            hasContent: () => { var _a; return !!((_a = this._value) === null || _a === void 0 ? void 0 : _a.trim()) || this.fileHandler.hasFiles(); }
        });
    }
    /**
     * Gets the input mode (single, multi, or auto).
     */
    _getMode() {
        const options = this.options;
        return options.mode || LINE_MODE.AUTO;
    }
    /**
     * Gets the merged messages, including overrides from button settings.
     */
    _getMessages() {
        const options = this.options;
        const actionButtonSettings = options.actionButton;
        const fileSelectButtonSettings = typeof options.fileSelectButton === "object" ? options.fileSelectButton : null;
        const baseMessages = $.extend({}, DEFAULT_MESSAGES, options.messages);
        if (actionButtonSettings === null || actionButtonSettings === void 0 ? void 0 : actionButtonSettings.text) {
            baseMessages.actionButton = actionButtonSettings.text;
        }
        if (actionButtonSettings === null || actionButtonSettings === void 0 ? void 0 : actionButtonSettings.loadingText) {
            baseMessages.actionButtonLoading = actionButtonSettings.loadingText;
        }
        if (fileSelectButtonSettings === null || fileSelectButtonSettings === void 0 ? void 0 : fileSelectButtonSettings.text) {
            baseMessages.fileSelectButton = fileSelectButtonSettings.text;
        }
        return baseMessages;
    }
    /**
     * Gets whether the action button should be shown. Always returns true.
     */
    _showActionButton() {
        return true;
    }
    /**
     * Gets whether the speech-to-text button should be shown.
     */
    _showSpeechToTextButton() {
        const options = this.options;
        return options.speechToTextButton === true ||
            options.speechToTextButton === null ||
            options.speechToTextButton === undefined ||
            (typeof options.speechToTextButton === "object" && options.speechToTextButton !== null);
    }
    /**
     * Gets whether the file select button should be shown.
     */
    _showFileSelectButton() {
        const options = this.options;
        return options.fileSelectButton === true ||
            (typeof options.fileSelectButton === "object" && options.fileSelectButton !== null);
    }
    /**
     * Updates the file select button state based on enable/readonly options.
     */
    _updateFileSelectButtonState() {
        const options = this.options;
        const fileSelectButton = this.wrapper.find(`[${REFERENCES.fileSelectButton}]`);
        if (!fileSelectButton.length) {
            return;
        }
        const fileSelectSettings = this._getFileSelectButtonSettings();
        const isDisabled = !options.enable || options.readonly || (fileSelectSettings === null || fileSelectSettings === void 0 ? void 0 : fileSelectSettings.enable) === false;
        if (isDisabled) {
            fileSelectButton.addClass(STYLES.disabled);
            fileSelectButton.attr("aria-disabled", "true");
        }
        else {
            fileSelectButton.removeClass(STYLES.disabled);
            fileSelectButton.removeAttr("aria-disabled");
        }
    }
    /**
     * Gets the file select button settings.
     */
    _getFileSelectButtonSettings() {
        const options = this.options;
        if (typeof options.fileSelectButton === "object" && options.fileSelectButton !== null) {
            return options.fileSelectButton;
        }
        return undefined;
    }
    /**
     * Creates the wrapper element using wrap instead of replaceWith.
     * This keeps the original element in the DOM so jQuery data binding works.
     */
    _initWrapper() {
        const options = this.options;
        const messages = this._getMessages();
        const mode = this._getMode();
        let modeClass = "";
        if (mode === LINE_MODE.SINGLE) {
            modeClass = ` ${STYLES.promptBoxSingleline}`;
        }
        else if (mode === LINE_MODE.MULTI) {
            modeClass = ` ${STYLES.promptBoxMultiline}`;
        }
        const stylingClasses = this._getAppearanceClasses();
        const affixConfig = {
            startAffixTemplate: options.startAffixTemplate,
            endAffixTemplate: options.endAffixTemplate,
            topAffixTemplate: options.topAffixTemplate
        };
        const fileSelectButtonConfig = this._getFileSelectButtonConfig();
        const buttonSettingsConfig = this._getButtonSettingsConfig();
        const renderConfig = {
            affixConfig,
            fileSelectButtonConfig,
            buttonSettingsConfig
        };
        const wrapperContent = renderPromptBox(options.headerTemplate, messages, this._showActionButton(), mode, this._showSpeechToTextButton(), renderConfig);
        this.wrapper = this.element.wrap(`<div class="${STYLES.input}${stylingClasses} ${STYLES.promptBox}${modeClass}" role="group"></div>`).parent();
        this.wrapper.prepend(wrapperContent);
        this.element.addClass(STYLES.hidden);
        this.wrapper.after(this.element);
    }
    /**
     * Gets appearance CSS classes for fillMode.
     * PromptBox only supports fillMode at the root level per kendo-themes spec.
     */
    _getAppearanceClasses() {
        const options = this.options;
        if (options.fillMode) {
            return ` k-input-${options.fillMode}`;
        }
        return "";
    }
    /**
     * Gets the file select button configuration for template rendering.
     */
    _getFileSelectButtonConfig() {
        var _a, _b;
        if (!this._showFileSelectButton()) {
            return undefined;
        }
        const settings = this._getFileSelectButtonSettings();
        let accept = settings === null || settings === void 0 ? void 0 : settings.accept;
        if (!accept && ((_b = (_a = settings === null || settings === void 0 ? void 0 : settings.restrictions) === null || _a === void 0 ? void 0 : _a.allowedExtensions) === null || _b === void 0 ? void 0 : _b.length)) {
            accept = settings.restrictions.allowedExtensions
                .map(ext => ext.startsWith(".") ? ext : `.${ext}`)
                .join(",");
        }
        return {
            enable: settings === null || settings === void 0 ? void 0 : settings.enable,
            fileInputId: this._fileInputId,
            accept: accept,
            multiple: settings === null || settings === void 0 ? void 0 : settings.multiple,
            showSpacer: settings === null || settings === void 0 ? void 0 : settings.showSpacer,
            settings: settings
        };
    }
    /**
     * Gets the button settings configuration for template rendering.
     */
    _getButtonSettingsConfig() {
        const options = this.options;
        const speechOption = options.speechToTextButton;
        const speechSettings = typeof speechOption === "object" && speechOption !== null ? speechOption : undefined;
        return {
            actionButtonSettings: options.actionButton || undefined,
            fileSelectButtonSettings: this._getFileSelectButtonSettings(),
            speechToTextButtonSettings: speechSettings
        };
    }
    /**
     * Gets the current multiline state (for auto mode).
     */
    isMultiline() {
        return this.inputManager.isMultiline();
    }
    /**
     * Attaches event handlers.
     */
    _attachEvents() {
        this.wrapper.on(CLICK + NS, `[${REFERENCES.sendButton}]`, (e) => {
            e.preventDefault();
            this._handleSend();
        });
        this.wrapper.on(CLICK + NS, `[${REFERENCES.fileSelectButton}]`, (e) => {
            e.preventDefault();
            this.fileHandler.handleFileSelectClick();
        });
        this.wrapper.on(CLICK + NS, `[${REFERENCES.attachmentRemoveButton}]`, (e) => {
            e.preventDefault();
            const target = e.currentTarget;
            const uid = target.getAttribute("data-uid");
            if (uid) {
                this.fileHandler.removeAttachmentByUid(uid);
            }
        });
        this.wrapper.on(FOCUSIN + NS, (e) => {
            this._handleFocusIn(e);
        });
        this.wrapper.on(FOCUSOUT + NS, (e) => {
            this._handleFocusOut(e);
        });
    }
    /**
     * Detaches event handlers.
     */
    _detachEvents() {
        this.wrapper.off(NS);
    }
    /**
     * Handles focus entering the widget.
     */
    _handleFocusIn(e) {
        if (!this._hasFocus) {
            this._hasFocus = true;
            this.wrapper.addClass(STYLES.focus);
            this.trigger(EVENTS.focus);
        }
    }
    /**
     * Handles focus leaving the widget.
     */
    _handleFocusOut(e) {
        const relatedTarget = e.relatedTarget;
        const isInsideWidget = relatedTarget && this.wrapper[0].contains(relatedTarget);
        if (!isInsideWidget && this._hasFocus) {
            this._hasFocus = false;
            this.wrapper.removeClass(STYLES.focus);
            this.trigger(EVENTS.blur);
        }
    }
    /**
     * Handles the send action.
     */
    _handleSend() {
        var _a;
        const options = this.options;
        if (!options.enable || options.readonly || ((_a = options.actionButton) === null || _a === void 0 ? void 0 : _a.enable) === false) {
            return;
        }
        if (this._loading) {
            const eventData = { actionType: "stop" };
            this.trigger(EVENTS.promptAction, eventData);
            this.loading(false);
            return;
        }
        const value = this._value;
        const hasValue = value === null || value === void 0 ? void 0 : value.trim();
        const hasFiles = this.fileHandler.hasFiles();
        if (!hasValue && !hasFiles) {
            return;
        }
        const eventData = {
            actionType: "send",
            value: hasValue ? value.trim() : "",
            files: this.fileHandler.getFiles()
        };
        this.trigger(EVENTS.promptAction, eventData);
        this.value("");
        this.clearFiles();
        this.focus();
    }
    /**
     * Handles input changes.
     */
    _handleInput() {
        const oldValue = this._value;
        this._value = this.inputManager.getValue();
        this.actionButtonManager.updateState();
        this.trigger(EVENTS.input, { value: this._value });
        if (oldValue !== this._value) {
            this.trigger(EVENTS.valueChange, {
                oldValue,
                newValue: this._value
            });
        }
    }
    /**
     * Handles keydown events.
     * Enter sends the message in all modes.
     * Shift+Enter adds a newline in multi/auto modes.
     * Enter is ignored when loading to prevent accidental stops.
     */
    _handleKeyDown(e) {
        const mode = this._getMode();
        const isEnter = e.key === "Enter" || e.which === 13 || e.keyCode === 13;
        if (isEnter) {
            if (e.shiftKey && mode !== LINE_MODE.SINGLE) {
                return;
            }
            e.preventDefault();
            if (!this._loading) {
                this._handleSend();
            }
        }
    }
}
PromptBox.options = {
    name: "PromptBox",
    prefix: "",
    mode: LINE_MODE.AUTO,
    placeholder: "",
    value: "",
    enable: true,
    readonly: false,
    title: "",
    fillMode: undefined,
    maxTextAreaHeight: undefined,
    rows: 1,
    headerTemplate: null,
    loading: false,
    actionButton: null,
    fileSelectButton: false,
    speechToTextButton: true,
    startAffixTemplate: null,
    endAffixTemplate: null,
    topAffixTemplate: null,
    messages: DEFAULT_MESSAGES
};
