/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { NS, STYLES, REFERENCES, ICONS, SCROLL_TO_BOTTOM_THRESHOLD, CLICK, SCROLL, DOT } from "../../constants";
export class ScrollManager {
    constructor(context) {
        this.scrollToBottomButton = null;
        this._scrollHandler = null;
        this._clickHandler = null;
        this.context = context;
        this._initScrollToBottomButton();
        this._attachEvents();
    }
    _initScrollToBottomButton() {
        const options = this.context.options;
        if (options.scrollToBottomButton === false) {
            this.scrollToBottomButton = null;
            return;
        }
        const buttonHtml = kendo.html.renderButton(`<button class="${STYLES.scrollFab} ${STYLES.hidden}" ${REFERENCES.scrollToBottomButton} aria-label="Scroll to bottom"></button>`, {
            icon: ICONS.arrowDown,
            rounded: "full"
        });
        this.scrollToBottomButton = $(buttonHtml).appendTo(this.context.element);
        const cssStyles = {
            position: "sticky",
            bottom: "16px",
            transform: "translateX(-50%)",
            zIndex: 1
        };
        const isContextElementRtl = this.context.element.css("direction") === "rtl";
        cssStyles[isContextElementRtl ? "right" : "left"] = "50%";
        this.scrollToBottomButton.css(cssStyles);
    }
    _attachEvents() {
        this._scrollHandler = this._onScroll.bind(this);
        this._clickHandler = this._scrollToBottomButtonClick.bind(this);
        this.context.element
            .on(SCROLL + NS + ".scrollManager", this._scrollHandler)
            .on(CLICK + NS + ".scrollManager", `[${REFERENCES.scrollToBottomButton}]`, this._clickHandler);
    }
    _updateScrollToBottomButtonVisibility() {
        if (!this.scrollToBottomButton) {
            return;
        }
        const scrollTop = this.context.element.scrollTop() || 0;
        const scrollHeight = this.context.element.prop("scrollHeight") || 0;
        const clientHeight = this.context.element.prop("clientHeight") || 0;
        const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
        if (distanceFromBottom > SCROLL_TO_BOTTOM_THRESHOLD) {
            this.scrollToBottomButton.removeClass(STYLES.hidden);
        }
        else {
            this.scrollToBottomButton.addClass(STYLES.hidden);
        }
    }
    _onScroll() {
        this._updateScrollToBottomButtonVisibility();
    }
    _scrollToBottomButtonClick(e) {
        e.preventDefault();
        this.scrollToBottom();
    }
    scrollToBottom() {
        this.context.element.scrollTop(this.context.element.prop("scrollHeight"));
    }
    scrollToMessage(uid) {
        if (!uid) {
            return false;
        }
        const messageElement = this.context.list.find(DOT + STYLES.message + `[data-uid="${this.context.htmlService.encode(uid)}"]`);
        if (!messageElement.length) {
            return false;
        }
        const pinnedElement = this.context.element.find(DOT + STYLES.messagePinned);
        const pinnedHeight = pinnedElement.length ? pinnedElement.outerHeight() || 0 : 0;
        return this.context.domUtilsService.scrollToElement(this.context.element, messageElement, { position: "top", offset: -pinnedHeight });
    }
    destroy() {
        if (this.scrollToBottomButton) {
            this.scrollToBottomButton.remove();
            this.scrollToBottomButton = null;
        }
        this.context.element.off(NS + ".scrollManager");
    }
}
