/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/**
 * Chat component constants
 *
 * This module contains all constants used throughout the Chat widget
 * and its collaborators.
 */
/** Namespace for event binding */
export const NS = ".kendoChat";
/** Message width display modes */
export const MESSAGE_WIDTH_MODE = {
    STANDARD: "standard",
    FULL: "full"
};
/** Message status values */
export const MESSAGE_STATUS = {
    SENT: "sent",
    DELIVERED: "delivered",
    SEEN: "seen",
    FAILED: "failed"
};
/** Files layout modes */
export const FILES_LAYOUT_MODE = {
    HORIZONTAL: "horizontal",
    VERTICAL: "vertical",
    WRAP: "wrap"
};
/** Suggested actions layout modes */
export const SUGGESTED_ACTIONS_LAYOUT_MODE = {
    SCROLL: "scroll",
    WRAP: "wrap",
    SCROLLBUTTONS: "scrollbuttons"
};
/** Icon identifiers used in the Chat component */
export const ICONS = {
    attachment: "paperclip-outline-alt-right",
    attachmentMenu: "more-vertical",
    checkCircle: "check-circle",
    chevronDown: "chevron-down",
    chevronLeft: "chevron-left",
    chevronRight: "chevron-right",
    chevronUp: "chevron-up",
    download: "download",
    fileError: "file-error",
    filePdf: "file-pdf",
    microphoneOutline: "microphone-outline",
    pin: "pin",
    undo: "undo",
    copy: "copy",
    trash: "trash",
    send: "paper-plane",
    stop: "stop-sm",
    upload: "upload",
    x: "x",
    arrowDown: "arrow-down",
    retry: "arrow-rotate-cw"
};
/** Command identifiers for menu actions */
export const COMMANDS = {
    reply: "reply",
    copy: "copy",
    pin: "pin",
    delete: "delete",
    download: "download"
};
/** CSS class names used in the Chat component */
export const STYLES = {
    // State classes
    active: "k-active",
    selected: "k-selected",
    focus: "k-focus",
    expanded: "k-expanded",
    disabled: "k-disabled",
    hidden: "k-hidden",
    generating: "k-generating",
    noAvatar: "k-no-avatar",
    // Files
    file: "k-file-box",
    fileInfo: "k-file-info",
    fileName: "k-file-name",
    fileSize: "k-file-size",
    fileWrapper: "k-file-box-wrapper",
    fileWrapperScrollableStart: "k-file-box-wrapper-scrollable-start",
    filesScroll: "k-files-scroll",
    filesVertical: "k-files-vertical",
    filesWrap: "k-files-wrap",
    // Avatar
    avatar: "k-avatar",
    // Bubble styles
    bubble: "k-bubble",
    bubbleContent: "k-bubble-content",
    bubbleExpandable: "k-bubble-expandable",
    bubbleExpandableIndicator: "k-bubble-expandable-indicator",
    chatBubble: "k-chat-bubble",
    chatBubbleText: "k-chat-bubble-text",
    // Buttons
    button: "k-button",
    buttonDefaults: "k-button-md k-rounded-md k-button-solid k-button-solid-base",
    buttonIcon: "k-button-icon",
    buttonPrimary: "k-button-md k-rounded-md k-button-flat k-button-flat-primary",
    iconButton: "k-icon-button",
    menuButton: "k-menu-button",
    chatSend: "k-chat-send",
    chatUpload: "k-chat-upload",
    downloadButton: "k-chat-download-button",
    downloadButtonWrapper: "k-chat-download-button-wrapper",
    // Cards
    canvas: "k-chat-canvas",
    card: "k-card",
    cardAction: "k-card-action",
    cardActions: "k-card-actions",
    cardActionsVertical: "k-actions-vertical",
    cardBody: "k-card-body",
    cardDeck: "k-card-deck",
    cardDeckScrollWrap: "k-card-deck-scrollwrap",
    cardList: "k-card-list",
    cardMedia: "k-card-media",
    cardRich: "k-card-type-rich",
    cardSubtitle: "k-card-subtitle",
    cardTitle: "k-card-title",
    cardWrapper: "k-card-container",
    // Dropzone
    dropzoneHint: "k-dropzone-hint",
    dropzoneIcon: "k-dropzone-icon",
    dropzoneInner: "k-dropzone-inner",
    externalDropzone: "k-external-dropzone",
    // Header
    header: "k-chat-header",
    // Message structure
    message: "k-message",
    messageAuthor: "k-message-author",
    messageGroup: "k-message-group",
    messageGroupContent: "k-message-group-content",
    messageGroupFullWidth: "k-message-group-full-width",
    messageGroupReceiver: "k-message-group-receiver",
    messageGroupSender: "k-message-group-sender",
    messageListContent: "k-message-list-content",
    messageRemoved: "k-message-removed",
    messageStatus: "k-message-status",
    messageTime: "k-message-time",
    messageInfo: "k-message-info",
    messageFailed: "k-message-failed",
    messageToolbar: "k-chat-message-toolbar",
    messageRetryButton: "k-message-retry-button",
    // Message reference (reply/pin)
    messagePinned: "k-message-pinned",
    messageReference: "k-message-reference",
    messageReferenceContent: "k-message-reference-content",
    messageReferenceReceiver: "k-message-reference-receiver",
    messageReferenceSender: "k-message-reference-sender",
    // Scroll buttons
    scrollButtonIconLeft: "chevron-left",
    scrollButtonIconRight: "chevron-right",
    // Suggestions
    suggestion: "k-suggestion",
    suggestionGroup: "k-suggestion-group",
    suggestionGroupScrollable: "k-suggestion-group-scrollable",
    suggestionGroupWrap: "k-suggestion-group-wrap",
    suggestionsScroll: "k-suggestions-scroll",
    suggestionScrollWrap: "k-suggestion-scrollwrap",
    suggestionScrollWrapGradient: "k-suggestion-scrollwrap-gradient",
    // Timestamp
    timestamp: "k-timestamp",
    // Typing indicator
    typingIndicator: "k-typing-indicator",
    // Wrapper and layout
    viewWrapper: "k-message-list",
    wrapper: "k-chat",
    // Message box
    messageBox: "k-message-box",
    messageBoxWrapper: "k-message-box-wrapper",
    // Spacer
    spacer: "k-spacer",
    // Scroll to bottom FAB
    scrollFab: "k-chat-scroll-fab",
    fab: "k-fab"
};
/** Data attribute references for element selection */
export const REFERENCES = {
    fileButton: "ref-chat-file-button",
    fileMenuButton: "ref-chat-file-menu-button",
    fileWrapper: "ref-chat-file-wrapper",
    fileCloseButton: "ref-chat-file-close-button",
    bubbleExpandableIndicator: "ref-chat-bubble-expandable-indicator",
    messageReferencePinWrapper: "ref-chat-message-reference-pin-wrapper",
    messageReferenceReplyWrapper: "ref-chat-message-reference-reply-wrapper",
    pinnedMessageCloseButton: "ref-chat-pinned-message-close-button",
    replyMessageCloseButton: "ref-chat-reply-message-close-button",
    leftScrollButton: "ref-chat-left-scroll-button",
    rightScrollButton: "ref-chat-right-scroll-button",
    sendButton: "ref-chat-message-box-send-button",
    speechToTextButton: "ref-chat-message-box-speech-to-text-button",
    suggestionGroup: "ref-chat-suggestion-group",
    fileUploadInput: "ref-chat-file-upload-input",
    viewWrapper: "ref-chat-view-wrapper",
    scrollToBottomButton: "ref-chat-scroll-to-bottom-button",
    retryButton: "ref-chat-message-retry-button"
};
/** Event names triggered by the Chat component */
export const EVENTS = {
    sendMessage: "sendMessage",
    suggestionClick: "suggestionClick",
    unpin: "unpin",
    input: "input",
    toolbarAction: "toolbarAction",
    fileMenuAction: "fileMenuAction",
    contextMenuAction: "contextMenuAction",
    download: "download",
    fileSelect: "fileSelect",
    fileRemove: "fileRemove",
    /** @internal */
    executeAction: "executeAction",
    /** @internal */
    resendMessage: "resendMessage",
    suggestedActionClick: "suggestedActionClick",
    messageToolbarExecute: "messageToolbarExecute",
    fileMenuExecute: "fileMenuExecute",
    downloadAllFiles: "downloadAllFiles",
    expandableToggle: "expandableToggle",
    replyMessageCloseButtonClick: "replyMessageCloseButtonClick"
};
/** Common DOM event names */
export const CLICK = "click";
export const INPUT = "input";
export const KEYDOWN = "keydown";
export const FOCUS = "focus";
export const BLUR = "blur";
export const CHANGE = "change";
export const SCROLL = "scroll";
/** Text direction constants */
export const LTR = "ltr";
export const RTL = "rtl";
export const K_RTL = "k-rtl";
/** CSS selector prefix */
export const DOT = ".";
/** Scrolling delta for suggestion navigation */
export const SCROLLING_DELTA = 200;
/** Scroll to bottom threshold in pixels */
export const SCROLL_TO_BOTTOM_THRESHOLD = 100;
