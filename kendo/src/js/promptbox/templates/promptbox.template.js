/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.icons.js";
import "../../kendo.html.button.js";
import { STYLES, REFERENCES, ICONS } from "../constants";
import { LINE_MODE } from "../constants";
/**
 * Renders the PromptBox header section.
 * Returns empty string when no headerTemplate (header is created dynamically when needed).
 */
export const renderHeader = (headerTemplate, forceRender = false) => {
    let content = "";
    if (headerTemplate) {
        content = headerTemplate();
    }
    if (!content && !forceRender) {
        return "";
    }
    const hiddenClass = content ? "" : ` ${STYLES.hidden}`;
    return `<div class="${STYLES.promptBoxHeader}${hiddenClass}" ${REFERENCES.header}><div ${REFERENCES.attachmentsHost}></div>${content}</div>`;
};
/**
 * Renders a template function.
 */
const renderTemplate = (template) => {
    if (!template) {
        return "";
    }
    return template();
};
/**
 * Renders the send button using kendo.html.renderButton.
 */
export const renderSendButton = (messages, enable = true, settings) => {
    const disabledClass = !enable ? ` ${STYLES.disabled}` : "";
    const ariaDisabled = !enable ? ' aria-disabled="true"' : '';
    const buttonOptions = {
        icon: (settings === null || settings === void 0 ? void 0 : settings.icon) || "arrow-up-outline",
        size: (settings === null || settings === void 0 ? void 0 : settings.size) || "small",
        rounded: (settings === null || settings === void 0 ? void 0 : settings.rounded) || "full"
    };
    if (settings === null || settings === void 0 ? void 0 : settings.fillMode) {
        buttonOptions.fillMode = settings.fillMode;
    }
    if (settings === null || settings === void 0 ? void 0 : settings.themeColor) {
        buttonOptions.themeColor = settings.themeColor;
    }
    return kendo.html.renderButton(`<button title="${messages.actionButton}" aria-label="${messages.actionButton}" aria-live="polite" class="${disabledClass}"${ariaDisabled} ${REFERENCES.sendButton} type="button"></button>`, buttonOptions);
};
/**
 * Renders the file select button and hidden file input using kendo.html.renderButton.
 */
export const renderFileSelectButton = (fileInputId, accept, multiple, settings, enable = true, messages) => {
    const multipleAttr = multiple ? ' multiple' : '';
    const acceptAttr = accept ? ` accept="${accept}"` : '';
    const disabledAttr = !enable ? ' disabled' : '';
    const ariaDisabledAttr = !enable ? ' aria-disabled="true"' : '';
    const disabledClass = !enable ? ` ${STYLES.disabled}` : '';
    const buttonTitle = (settings === null || settings === void 0 ? void 0 : settings.text) || (messages === null || messages === void 0 ? void 0 : messages.fileSelectButton) || "Attach file";
    const buttonOptions = {
        icon: (settings === null || settings === void 0 ? void 0 : settings.icon) || ICONS.attachment,
        fillMode: (settings === null || settings === void 0 ? void 0 : settings.fillMode) || "clear"
    };
    if (settings === null || settings === void 0 ? void 0 : settings.size) {
        buttonOptions.size = settings.size;
    }
    if (settings === null || settings === void 0 ? void 0 : settings.rounded) {
        buttonOptions.rounded = settings.rounded;
    }
    if (settings === null || settings === void 0 ? void 0 : settings.themeColor) {
        buttonOptions.themeColor = settings.themeColor;
    }
    const button = kendo.html.renderButton(`<button title="${buttonTitle}" aria-label="${buttonTitle}" class="${disabledClass}" ${REFERENCES.fileSelectButton} type="button"${disabledAttr}${ariaDisabledAttr}></button>`, buttonOptions);
    return `${button}<input type="file" id="${fileInputId}" class="${STYLES.hidden}" ${REFERENCES.fileInput}${multipleAttr}${acceptAttr} />`;
};
/**
 * Renders the speech-to-text button placeholder.
 * The actual icon is rendered by the SpeechToTextButton component.
 */
export const renderSpeechToTextButton = (settings) => {
    return `<button class="${STYLES.speechToTextButton}" type="button"></button>`;
};
/**
 * Renders the start affix container (before the input).
 * Uses k-prompt-box-affix class; position is determined by DOM order.
 */
export const renderStartAffix = (startAffixTemplate) => {
    const content = renderTemplate(startAffixTemplate);
    if (!content) {
        return "";
    }
    return `<div class="${STYLES.promptBoxAffix}" ${REFERENCES.startAffix}>${content}</div>`;
};
/**
 * Renders the end affix container with buttons (after the input).
 * Returns empty string when no content.
 */
export const renderEndAffix = (messages, showSendButton, showSpeechToText = true, endAffixTemplate, fileSelectButtonConfig, buttonSettingsConfig) => {
    const endAffix = renderTemplate(endAffixTemplate);
    const fileSelectButtonEnabled = (fileSelectButtonConfig === null || fileSelectButtonConfig === void 0 ? void 0 : fileSelectButtonConfig.enable) !== false;
    const fileSelectButton = fileSelectButtonConfig
        ? renderFileSelectButton(fileSelectButtonConfig.fileInputId, fileSelectButtonConfig.accept, fileSelectButtonConfig.multiple, fileSelectButtonConfig.settings, fileSelectButtonEnabled, messages)
        : "";
    const spacer = (fileSelectButtonConfig === null || fileSelectButtonConfig === void 0 ? void 0 : fileSelectButtonConfig.showSpacer) ? `<div class="${STYLES.spacer}"></div>` : "";
    const speechButton = showSpeechToText ? renderSpeechToTextButton(buttonSettingsConfig === null || buttonSettingsConfig === void 0 ? void 0 : buttonSettingsConfig.speechToTextButtonSettings) : "";
    const sendButton = showSendButton ? renderSendButton(messages, true, buttonSettingsConfig === null || buttonSettingsConfig === void 0 ? void 0 : buttonSettingsConfig.actionButtonSettings) : "";
    const content = `${endAffix}${fileSelectButton}${spacer}${speechButton}${sendButton}`.trim();
    if (!content) {
        return "";
    }
    return `<div class="${STYLES.promptBoxAffix}" ${REFERENCES.endAffix}>
        ${endAffix}
        ${fileSelectButton}
        ${spacer}
        ${speechButton}
        ${sendButton}
    </div>`;
};
/**
 * Renders the end affix container with start affix content (for multi mode).
 * In multi mode: fileSelectButton + startAffix content + spacer + endAffix content + buttons
 * The spacer is only rendered if there's content on both sides to separate.
 */
export const renderEndAffixWithStartAffix = (messages, showSendButton, showSpeechToText = true, startAffixTemplate, endAffixTemplate, fileSelectButtonConfig, buttonSettingsConfig) => {
    const startAffix = renderTemplate(startAffixTemplate);
    const endAffix = renderTemplate(endAffixTemplate);
    const fileSelectButtonEnabled = (fileSelectButtonConfig === null || fileSelectButtonConfig === void 0 ? void 0 : fileSelectButtonConfig.enable) !== false;
    const fileSelectButton = fileSelectButtonConfig
        ? renderFileSelectButton(fileSelectButtonConfig.fileInputId, fileSelectButtonConfig.accept, fileSelectButtonConfig.multiple, fileSelectButtonConfig.settings, fileSelectButtonEnabled, messages)
        : "";
    const speechButton = showSpeechToText ? renderSpeechToTextButton(buttonSettingsConfig === null || buttonSettingsConfig === void 0 ? void 0 : buttonSettingsConfig.speechToTextButtonSettings) : "";
    const sendButton = showSendButton ? renderSendButton(messages, true, buttonSettingsConfig === null || buttonSettingsConfig === void 0 ? void 0 : buttonSettingsConfig.actionButtonSettings) : "";
    const spacer = `<div class="${STYLES.spacer}"></div>`;
    if (!(fileSelectButtonConfig === null || fileSelectButtonConfig === void 0 ? void 0 : fileSelectButtonConfig.showSpacer)) {
        return `<div class="${STYLES.promptBoxAffix}" ${REFERENCES.endAffix}>
            ${startAffix}
            ${spacer}
            ${endAffix}
            ${fileSelectButton}
            ${speechButton}
            ${sendButton}
        </div>`;
    }
    return `<div class="${STYLES.promptBoxAffix}" ${REFERENCES.endAffix}>
        ${fileSelectButton}
        ${startAffix}
        ${spacer}
        ${endAffix}
        ${speechButton}
        ${sendButton}
    </div>`;
};
/**
 * Renders the top affix section (only for multi mode).
 * Note: This is placed at the beginning of content for multi-mode layout.
 */
export const renderTopAffix = (topAffixTemplate) => {
    const content = renderTemplate(topAffixTemplate);
    if (!content) {
        return "";
    }
    return `<div class="${STYLES.promptBoxAffix}" ${REFERENCES.topAffix}>${content}</div>`;
};
/**
 * Renders the complete PromptBox structure.
 * For multi mode: startAffix content is rendered inside the end affix container with a spacer.
 * For auto mode: startAffix is rendered as a separate container (JS handles reorganization on expand).
 */
export const renderPromptBox = (headerTemplate, messages, showSendButton, mode, showSpeechToText = true, renderConfig = {}) => {
    const { affixConfig = {}, fileSelectButtonConfig, buttonSettingsConfig } = renderConfig;
    const forceRenderHeader = !!fileSelectButtonConfig;
    const header = renderHeader(headerTemplate, forceRenderHeader);
    const topAffix = mode === LINE_MODE.MULTI ? renderTopAffix(affixConfig.topAffixTemplate) : "";
    const isMultiMode = mode === LINE_MODE.MULTI;
    const startAffix = isMultiMode ? "" : renderStartAffix(affixConfig.startAffixTemplate);
    const endAffix = isMultiMode
        ? renderEndAffixWithStartAffix(messages, showSendButton, showSpeechToText, affixConfig.startAffixTemplate, affixConfig.endAffixTemplate, fileSelectButtonConfig, buttonSettingsConfig)
        : renderEndAffix(messages, showSendButton, showSpeechToText, affixConfig.endAffixTemplate, fileSelectButtonConfig, buttonSettingsConfig);
    return `${header}
        <div class="${STYLES.promptBoxContent}">
            ${topAffix}
            ${startAffix}
            <span ${REFERENCES.input}></span>
            ${endAffix}
        </div>`;
};
