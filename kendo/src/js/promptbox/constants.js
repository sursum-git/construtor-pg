/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * PromptBox component constants
 *
 * This module contains all constants used throughout the PromptBox widget.
 */
/** Namespace for event binding */
export const NS = ".kendoPromptBox";
/** Line mode options */
export const LINE_MODE = {
    SINGLE: "single",
    MULTI: "multi",
    AUTO: "auto"
};
/** Icon identifiers used in the PromptBox component */
export const ICONS = {
    send: "paper-plane",
    stop: "stop-sm",
    attachment: "paperclip-outline-alt-right"
};
/** CSS class names used in the PromptBox component */
export const STYLES = {
    // Core structure (matching kendo-themes)
    promptBox: "k-prompt-box",
    promptBoxContent: "k-prompt-box-content",
    promptBoxHeader: "k-prompt-box-header",
    promptBoxAffix: "k-prompt-box-affix",
    promptBoxTextarea: "k-prompt-box-textarea",
    promptBoxInput: "k-prompt-box-input",
    promptBoxSingleline: "k-prompt-box-singleline",
    promptBoxMultiline: "k-prompt-box-multiline",
    // Input base class
    input: "k-input",
    inputInner: "k-input-inner",
    // State classes
    disabled: "k-disabled",
    hidden: "k-hidden",
    focus: "k-focus",
    generating: "k-generating",
    active: "k-active",
    // Button reference classes (semantic identifiers)
    speechToTextButton: "k-speech-to-text-button",
    fileSelectButton: "k-file-select-button",
    // File attachments (in header)
    fileBoxWrapper: "k-file-box-wrapper",
    fileBoxWrapperScrollableStart: "k-file-box-wrapper-scrollable-start",
    filesScroll: "k-files-scroll",
    fileBox: "k-file-box",
    fileInfo: "k-file-info",
    fileName: "k-file-name",
    fileSize: "k-file-size",
    // Layout
    spacer: "k-spacer"
};
/** Attribute references for element selection (used as [ref-*] selectors) */
export const REFERENCES = {
    sendButton: "ref-promptbox-send-button",
    fileSelectButton: "ref-promptbox-file-select-button",
    fileInput: "ref-promptbox-file-input",
    attachmentsHost: "ref-promptbox-attachments-host",
    attachmentRemoveButton: "ref-promptbox-attachment-remove-button",
    input: "ref-promptbox-input",
    header: "ref-promptbox-header",
    startAffix: "ref-promptbox-start-affix",
    endAffix: "ref-promptbox-end-affix",
    topAffix: "ref-promptbox-top-affix",
    affixSpacer: "ref-promptbox-affix-spacer"
};
/** Event names triggered by the PromptBox component */
export const EVENTS = {
    valueChange: "valueChange",
    input: "input",
    promptAction: "promptAction",
    fileSelect: "fileSelect",
    fileRemove: "fileRemove",
    speechToTextClick: "speechToTextClick",
    speechToTextStart: "speechToTextStart",
    speechToTextEnd: "speechToTextEnd",
    speechToTextError: "speechToTextError",
    speechToTextResult: "speechToTextResult",
    focus: "focus",
    blur: "blur",
    inputFocus: "inputFocus",
    inputBlur: "inputBlur",
    multilineStateChange: "multilineStateChange"
};
/** Common DOM event names */
export const CLICK = "click";
export const INPUT = "input";
export const KEYDOWN = "keydown";
export const FOCUS = "focus";
export const BLUR = "blur";
export const CHANGE = "change";
export const FOCUSIN = "focusin";
export const FOCUSOUT = "focusout";
/** CSS selector prefix */
export const DOT = ".";
