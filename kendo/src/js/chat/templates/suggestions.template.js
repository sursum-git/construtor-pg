/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.avatar.js"; // Still required until kendo.ui is modularized
import "../../kendo.button.js";
import "../../kendo.toolbar.js";
import { inject } from "../../core/service-container";
import { HtmlService } from "../../core/services/html.service";
import { STYLES, REFERENCES, ICONS, SUGGESTED_ACTIONS_LAYOUT_MODE } from "../constants";
/**
 * Renders a list of suggestions/suggested actions with the specified layout mode.
 * @param suggestions - Array of suggestions to render
 * @param layoutMode - Layout mode: "scroll" | "wrap" | "scrollbuttons"
 * @param isRtl - Whether RTL direction is used
 * @param htmlService - HTML encoding service
 */
export const renderSuggestions = (suggestions, htmlService = inject(HtmlService)) => {
    if (!(suggestions === null || suggestions === void 0 ? void 0 : suggestions.length)) {
        return "";
    }
    const suggestionItems = suggestions
        .map(suggestion => `<span class="${STYLES.suggestion}">${htmlService.encode(suggestion.text)}</span>`)
        .join("");
    return `<div class="${STYLES.suggestionGroup}" ${REFERENCES.suggestionGroup}>${suggestionItems}</div>`;
};
/**
 * Renders suggestions with the specified layout mode from an array of suggestions.
 * @param suggestions - Array of suggestions to render
 * @param layoutMode - Layout mode: "scroll" | "wrap" | "scrollbuttons"
 * @param isRtl - Whether RTL direction is used
 * @param htmlService - HTML encoding service
 */
export const renderSuggestionsWithLayout = (suggestions, layoutMode = SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLL, isRtl = false, htmlService = inject(HtmlService)) => {
    if (!(suggestions === null || suggestions === void 0 ? void 0 : suggestions.length)) {
        return "";
    }
    const suggestionItems = suggestions
        .map(suggestion => `<span class="${STYLES.suggestion}">${htmlService.encode(suggestion.text)}</span>`)
        .join("");
    return wrapSuggestionsWithLayout(suggestionItems, layoutMode, isRtl);
};
/**
 * Wraps pre-rendered suggestion content with the specified layout mode.
 * Use this when you have custom-rendered content from a template.
 * @param content - Pre-rendered HTML content (suggestion items)
 * @param layoutMode - Layout mode: "scroll" | "wrap" | "scrollbuttons"
 * @param isRtl - Whether RTL direction is used
 */
export const wrapSuggestionsWithLayout = (content, layoutMode = SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLL, isRtl = false) => {
    if (!content) {
        return "";
    }
    if (layoutMode === SUGGESTED_ACTIONS_LAYOUT_MODE.WRAP) {
        return `<div class="${STYLES.suggestionGroup}" ${REFERENCES.suggestionGroup}>${content}</div>`;
    }
    const layoutClass = layoutMode === SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLL
        ? STYLES.suggestionGroupScrollable
        : "";
    const groupClass = layoutClass ? `${STYLES.suggestionGroup} ${layoutClass}` : STYLES.suggestionGroup;
    const scrollContent = `<div class="${STYLES.suggestionsScroll}">${content}</div>`;
    const suggestionsElement = `<div class="${groupClass}" ${REFERENCES.suggestionGroup}>${scrollContent}</div>`;
    if (layoutMode === SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLLBUTTONS) {
        return renderScrollableSuggestions(suggestionsElement, isRtl);
    }
    return suggestionsElement;
};
/**
 * Renders scrollable suggestions with navigation buttons and gradient styling.
 */
export const renderScrollableSuggestions = (suggestionsElement, isRtl) => {
    const leftIcon = isRtl ? ICONS.chevronRight : ICONS.chevronLeft;
    const rightIcon = isRtl ? ICONS.chevronLeft : ICONS.chevronRight;
    return `<div class="${STYLES.suggestionScrollWrap} ${STYLES.suggestionScrollWrapGradient}">
        ${new kendo.ui.Button(`<button ${REFERENCES.leftScrollButton}>`, { icon: leftIcon }).wrapper[0].outerHTML}
        ${suggestionsElement}
        ${new kendo.ui.Button(`<button ${REFERENCES.rightScrollButton}>`, { icon: rightIcon }).wrapper[0].outerHTML}
    </div>`;
};
/**
 * Renders the chat header using Toolbar.
 * @param items - Array of toolbar items configuration
 * @param headerTemplate - Optional custom header template that overrides items
 */
export const renderHeader = (items, headerTemplate) => {
    // If custom header template is provided, use it
    if (headerTemplate) {
        return `<div class="${STYLES.header}">${headerTemplate()}</div>`;
    }
    // If no items provided, return empty
    if (!items || items.length === 0) {
        return "";
    }
    // Use Toolbar for header rendering
    const toolbarElement = $(`<div class="${STYLES.header}">`);
    new kendo.ui.ToolBar(toolbarElement, {
        items: items
    });
    return toolbarElement[0].outerHTML;
};
