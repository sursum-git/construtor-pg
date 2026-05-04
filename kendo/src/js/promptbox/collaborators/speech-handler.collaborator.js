/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { DOT, STYLES } from "../constants";
export class SpeechHandler {
    constructor(context) {
        this.instance = null;
        this.context = context;
    }
    init() {
        const option = this.context.getSpeechToTextButtonOption();
        if (option === false) {
            return;
        }
        const speechToTextButton = this.context.wrapper.find(DOT + STYLES.speechToTextButton);
        if (!speechToTextButton.length) {
            return;
        }
        const defaultSettings = {
            fillMode: "flat",
            size: "small",
            enable: true
        };
        const userSettings = typeof option === "object" && option !== null ? option : {};
        const isEnabled = userSettings.enable !== false;
        const buttonOptions = Object.assign(Object.assign(Object.assign({}, defaultSettings), userSettings), { start: (e) => this.context.callbacks.onStart(e), end: (e) => this.context.callbacks.onEnd(e), result: (e) => this.context.callbacks.onResult(e), error: (e) => this.context.callbacks.onError(e) });
        this.instance = new kendo.ui.SpeechToTextButton(speechToTextButton, buttonOptions);
        if (!isEnabled) {
            this.instance.enable(false);
        }
    }
    enable(enabled) {
        if (this.instance) {
            this.instance.enable(enabled);
        }
    }
    destroy() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }
}
