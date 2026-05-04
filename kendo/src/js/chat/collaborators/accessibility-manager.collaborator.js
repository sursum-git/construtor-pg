/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { NS, STYLES, REFERENCES, CLICK, FOCUS, BLUR } from "../constants";
const NAVIGATION_NS = `${NS}navigation`;
// Module-level state for keyboard navigation
let isKeyEvent = null;
let isShiftKey = false;
/**
 * AccesibilityManager handles ARIA attributes and keyboard navigation
 * for the Chat component according to WCAG 2.1 AA compliance.
 *
 * This is an internal collaborator - not a shared service.
 */
export class AccesibilityManager {
    /**
     * Constructs a new AccesibilityManager instance.
     * @param context - Context with wrapper, options, and services
     */
    constructor(context) {
        this.context = context;
    }
    /**
     * Sets up ARIA attributes for the Chat component.
     */
    setupAriaAttributes() {
        const options = this.context.getOptions();
        const messages = options.messages;
        // Message list
        const messageList = this.context.wrapper.find(`.${STYLES.viewWrapper}`);
        if (messageList.length) {
            messageList
                .attr("role", "log")
                .attr("aria-live", "polite")
                .attr("aria-label", messages.messageListLabel);
        }
        this.setupBubbleTabNavigation();
        this.setupSuggestionAccessibility();
        this.setupButtonAccessibility(messages);
        this.setupExpandableIndicators();
        this.setupScrollButtonsAria();
        this.setupMessageBoxAria(messages);
    }
    /**
     * Sets up tabindex navigation for chat bubbles.
     */
    setupBubbleTabNavigation() {
        const allBubbles = this.context.wrapper.find(`.${STYLES.bubble}`);
        const interactableBubbles = allBubbles.filter(function () {
            return $(this).find(`.${STYLES.typingIndicator}`).length === 0;
        });
        const typingIndicatorBubbles = allBubbles.filter(function () {
            return $(this).find(`.${STYLES.typingIndicator}`).length > 0;
        });
        interactableBubbles.attr("tabindex", "0");
        typingIndicatorBubbles.attr("tabindex", "-1");
        // Set up focus/blur/click handlers
        interactableBubbles
            .off(`${FOCUS}${NAVIGATION_NS} ${BLUR}${NAVIGATION_NS} ${CLICK}${NAVIGATION_NS}`)
            .on(`${FOCUS}${NAVIGATION_NS}`, function () {
            const $this = $(this);
            if (isKeyEvent) {
                allBubbles.removeClass(STYLES.selected);
            }
            allBubbles.removeClass(STYLES.focus);
            if (isKeyEvent) {
                $this.addClass(STYLES.selected);
            }
            $this.addClass(STYLES.focus);
            isKeyEvent = false;
        })
            .on(`${BLUR}${NAVIGATION_NS}`, function () {
            if (isKeyEvent && !isShiftKey) {
                $(this).removeClass(STYLES.selected);
            }
            $(this).removeClass(STYLES.focus);
        })
            .on(`${CLICK}${NAVIGATION_NS}`, function (e) {
            if ($(e.target).closest(REFERENCES.fileMenuButton)) {
                return;
            }
            $(this).trigger(FOCUS);
        });
    }
    /**
     * Sets up accessibility for suggestion groups.
     */
    setupSuggestionAccessibility() {
        const suggestionGroups = this.context.wrapper.find(`.${STYLES.suggestionGroup}`);
        suggestionGroups.each((_index, element) => {
            const $group = $(element);
            $group.attr("role", "group");
            const suggestions = $group.find(`.${STYLES.suggestion}`);
            suggestions.each((_suggestionIndex, suggestionElement) => {
                const $suggestion = $(suggestionElement);
                $suggestion
                    .attr("role", "button")
                    .attr("tabindex", "0");
            });
        });
    }
    /**
     * Sets up accessibility for various buttons.
     */
    setupButtonAccessibility(messages) {
        // Suffix buttons
        const suffixButtons = this.context.wrapper.find(".k-input-suffix > .k-button");
        suffixButtons.each((_index, element) => {
            const $button = $(element);
            if (!$button.attr("role") && element.nodeName.toLowerCase() !== "button") {
                $button.attr("role", "button");
            }
            if (!$button.attr("aria-label") && !$button.attr("title")) {
                if ($button.hasClass(STYLES.chatSend)) {
                    $button.attr("aria-label", messages.actionButton || messages.sendButton);
                }
                else if ($button.hasClass("k-chat-upload")) {
                    $button.attr("aria-label", messages.fileButton);
                }
            }
            if ($button.hasClass(STYLES.chatSend) && $button.hasClass("k-disabled")) {
                $button.attr("aria-disabled", "true");
            }
        });
        // Download buttons
        const downloadButtons = this.context.wrapper.find(`.${STYLES.downloadButton}`);
        downloadButtons.each((_index, element) => {
            const $button = $(element);
            if (!$button.attr("role") && element.nodeName.toLowerCase() !== "button") {
                $button.attr("role", "button");
            }
            if (!$button.attr("aria-label") && !$button.attr("title")) {
                $button.attr("aria-label", messages.downloadAll);
            }
        });
        // Pinned message close button
        this.setupCloseButtonAria(REFERENCES.pinnedMessageCloseButton, messages.pinnedMessageCloseButton);
        // Reply message close button
        this.setupCloseButtonAria(REFERENCES.replyMessageCloseButton, messages.replyMessageCloseButton);
        // File menu buttons
        const fileMenuButtons = this.context.wrapper.find(`[${REFERENCES.fileMenuButton}]`);
        fileMenuButtons.each((_index, element) => {
            const $button = $(element);
            $button.attr("aria-label", messages.fileMenuButton);
            $button.attr("title", messages.fileMenuButton);
        });
    }
    /**
     * Sets up ARIA attributes for close buttons.
     */
    setupCloseButtonAria(reference, label) {
        const buttons = this.context.wrapper.find(`[${reference}]`);
        buttons.each((_index, element) => {
            const $button = $(element);
            if (!$button.attr("role") && element.nodeName.toLowerCase() !== "button") {
                $button.attr("role", "button");
            }
            if (!$button.attr("aria-label") && !$button.attr("title")) {
                $button.attr("aria-label", label);
                $button.attr("title", label);
            }
        });
    }
    /**
     * Sets up ARIA attributes for expandable indicators.
     */
    setupExpandableIndicators() {
        const expandableIndicators = this.context.wrapper.find(`.${STYLES.bubbleExpandableIndicator}`);
        expandableIndicators.each((_index, element) => {
            const $indicator = $(element);
            $indicator
                .attr("role", "button")
                .attr("tabindex", "0");
            const bubble = $indicator.closest(`.${STYLES.bubble}`);
            const isExpanded = bubble.hasClass(STYLES.expanded);
            const label = isExpanded ? "Collapse message" : "Expand message";
            $indicator.attr("aria-label", label);
        });
    }
    /**
     * Sets up ARIA attributes for scroll buttons.
     */
    setupScrollButtonsAria() {
        const dir = this.context.getOptions().dir;
        const leftScrollButton = this.context.wrapper.find(`[${REFERENCES.leftScrollButton}]`);
        const rightScrollButton = this.context.wrapper.find(`[${REFERENCES.rightScrollButton}]`);
        const leftText = dir === "rtl" ? "Scroll Right" : "Scroll Left";
        const rightText = dir === "rtl" ? "Scroll Left" : "Scroll Right";
        leftScrollButton.attr("aria-label", leftText);
        leftScrollButton.attr("title", leftText);
        rightScrollButton.attr("aria-label", rightText);
        rightScrollButton.attr("title", rightText);
    }
    /**
     * Sets up ARIA attributes for the message box.
     */
    setupMessageBoxAria(messages) {
        const messageBox = this.context.wrapper.find(`.${STYLES.messageBox}`);
        messageBox.find("textarea").attr("aria-label", messages.messageBoxLabel);
    }
    /**
     * Sets up ARIA attributes for file close buttons.
     */
    setFileCloseButtonAria(container) {
        const fileCloseButton = container.find(`[${REFERENCES.fileCloseButton}]`);
        fileCloseButton.each((_index, element) => {
            const $button = $(element);
            if (!$button.attr("role") && element.nodeName.toLowerCase() !== "button") {
                $button.attr("role", "button");
            }
            if (!$button.attr("aria-label") && !$button.attr("title")) {
                $button.attr("aria-label", "Remove selected file");
                $button.attr("title", "Remove selected file");
            }
        });
    }
    /**
     * Updates ARIA attributes for expandable indicators when their state changes.
     */
    updateExpandableIndicatorAria(indicator, isExpanded) {
        const label = isExpanded ? "Collapse message" : "Expand message";
        indicator.attr("aria-label", label);
    }
    /**
     * Handles keyboard navigation for the Chat component.
     */
    handleKeyDown(e, messageBox) {
        if (!$(e.target).closest(this.context.wrapper).length) {
            return null;
        }
        const target = $(e.target);
        const key = e.keyCode || e.which;
        isKeyEvent = true;
        isShiftKey = e.shiftKey;
        if (target.hasClass(STYLES.bubble)) {
            this.handleBubbleKeyDown(e, key);
        }
        if (target.hasClass(STYLES.suggestion)) {
            this.handleClickableElementKeyDown(e, key);
        }
        if (target.hasClass(STYLES.bubbleExpandableIndicator)) {
            this.handleExpandableIndicatorKeyDown(e, key, target);
        }
        if (target.hasClass("k-input-inner")) {
            this.handleMessageInputKeyDown(e, key, messageBox);
        }
        if (target.closest(".k-input-suffix .k-button").length) {
            this.handleClickableElementKeyDown(e, key);
        }
        return null;
    }
    /**
     * Handles keyboard navigation within bubbles.
     */
    handleBubbleKeyDown(e, key) {
        const currentBubble = $(e.target);
        if (currentBubble.find(`.${STYLES.typingIndicator}`).length > 0) {
            return;
        }
        const allBubbles = this.context.wrapper.find(`.${STYLES.bubble}`);
        const bubbles = allBubbles.filter(function () {
            return $(this).find(`.${STYLES.typingIndicator}`).length === 0;
        });
        const currentIndex = bubbles.index(currentBubble);
        const keys = this.context.utilsService.keys;
        switch (key) {
            case keys.UP:
                e.preventDefault();
                this.focusBubbleAtIndex(bubbles, currentIndex - 1);
                break;
            case keys.DOWN:
                e.preventDefault();
                this.focusBubbleAtIndex(bubbles, currentIndex + 1);
                break;
            case keys.HOME:
                e.preventDefault();
                this.focusBubbleAtIndex(bubbles, 0);
                break;
            case keys.END:
                e.preventDefault();
                this.focusBubbleAtIndex(bubbles, bubbles.length - 1);
                break;
        }
    }
    /**
     * Focuses a bubble at the specified index.
     */
    focusBubbleAtIndex(bubbles, index) {
        if (bubbles.length === 0 || index < 0 || index >= bubbles.length) {
            return;
        }
        const targetBubble = bubbles.eq(index);
        targetBubble.attr("data-keyboard-focus", "true");
        targetBubble.focus();
        targetBubble.removeAttr("data-keyboard-focus");
    }
    /**
     * Handles keyboard events for expandable indicators.
     */
    handleExpandableIndicatorKeyDown(e, key, target) {
        const keys = this.context.utilsService.keys;
        if (key === keys.ENTER || key === keys.SPACEBAR) {
            e.preventDefault();
            target.trigger(CLICK);
            const bubble = target.closest(`.${STYLES.bubble}`);
            const isExpanded = bubble.hasClass(STYLES.expanded);
            this.updateExpandableIndicatorAria(target, !isExpanded);
        }
    }
    /**
     * Handles keyboard events for the message input.
     * Note: Enter key handling is delegated to PromptBox component.
     */
    handleMessageInputKeyDown(e, key, messageBox) {
    }
    /**
     * Handles keyboard events for clickable elements (suggestions, buttons).
     */
    handleClickableElementKeyDown(e, key) {
        const keys = this.context.utilsService.keys;
        if (key === keys.ENTER || key === keys.SPACEBAR) {
            e.preventDefault();
            $(e.target).trigger(CLICK);
        }
    }
}
