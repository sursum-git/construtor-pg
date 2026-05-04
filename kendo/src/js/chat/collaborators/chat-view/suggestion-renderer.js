/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { STYLES, DOT, REFERENCES, SUGGESTED_ACTIONS_LAYOUT_MODE } from "../../constants";
import { wrapSuggestionsWithLayout } from "../../templates";
export class SuggestionRenderer {
    constructor(context) {
        this.context = context;
    }
    renderSuggestedActions() {
        const options = this.context.options;
        const lastMessage = this.context.dataManager.getLastMessage();
        const lastSuggestedActions = (lastMessage === null || lastMessage === void 0 ? void 0 : lastMessage.suggestedActions) || [];
        const suggestedActionsElement = options.suggestedActionsTemplate(lastSuggestedActions);
        const layoutMode = this._getSuggestedActionsLayoutMode(options);
        const suggestedActionsWrapper = wrapSuggestionsWithLayout(suggestedActionsElement, layoutMode, options.dir === "rtl");
        this.removeSuggestedActions();
        this.context.list.append(lastSuggestedActions.length ? suggestedActionsWrapper : "");
        this.context.list.find(`[${REFERENCES.leftScrollButton}]`).addClass(STYLES.disabled);
    }
    removeSuggestedActions() {
        this.context.list.find(DOT + STYLES.suggestionGroup).remove();
        this.context.list.find(DOT + STYLES.suggestionScrollWrap).remove();
    }
    _getSuggestedActionsLayoutMode(options) {
        if (options.suggestedActionsLayoutMode) {
            return options.suggestedActionsLayoutMode;
        }
        if (options.suggestedActionsScrollable) {
            return SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLLBUTTONS;
        }
        return SUGGESTED_ACTIONS_LAYOUT_MODE.SCROLL;
    }
}
