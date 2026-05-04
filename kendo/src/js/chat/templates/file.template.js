/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.button.js"; // Still required until kendo.ui is modularized
import "../../kendo.icons.js";
import { inject } from "../../core/service-container";
import { HtmlService } from "../../core/services/html.service";
import { STYLES, REFERENCES, ICONS, FILES_LAYOUT_MODE } from "../constants";
import { renderFile } from "./common.template";
/**
 * Renders a list of file attachments with layout mode support.
 * Uses small-sized buttons per Chat v3 spec.
 */
export const renderFiles = (files, downloadAll, messages, closeButton, layoutMode = FILES_LAYOUT_MODE.VERTICAL) => {
    if (!(files === null || files === void 0 ? void 0 : files.length)) {
        return "";
    }
    let fileItems = "";
    files.forEach(file => {
        fileItems += renderFile(file, closeButton !== null && closeButton !== void 0 ? closeButton : false, true);
    });
    let layoutClass = "";
    if (layoutMode === FILES_LAYOUT_MODE.VERTICAL) {
        layoutClass = ` ${STYLES.filesVertical}`;
    }
    else if (layoutMode === FILES_LAYOUT_MODE.WRAP) {
        layoutClass = ` ${STYLES.filesWrap}`;
    }
    let html = `<ul class="${STYLES.fileWrapper}${layoutClass}" ${REFERENCES.fileWrapper}>`;
    if (layoutMode === FILES_LAYOUT_MODE.HORIZONTAL) {
        html += `<div class="${STYLES.filesScroll}">${fileItems}</div>`;
    }
    else {
        html += fileItems;
    }
    html += "</ul>";
    if (downloadAll && files.length > 1 && (messages === null || messages === void 0 ? void 0 : messages.downloadAll)) {
        html += `<div class="${STYLES.downloadButtonWrapper}">
            ${new kendo.ui.Button(`<button class="${STYLES.downloadButton}">${messages.downloadAll}</button>`, {
            icon: ICONS.download,
            fillMode: "flat",
            size: "small"
        }).wrapper[0].outerHTML}
        </div>`;
    }
    return html;
};
/**
 * Renders a list of file attachments with the specified layout mode.
 * Uses small-sized buttons per Chat v3 spec.
 * @param files - Array of files to render
 * @param downloadAll - Whether to show a "download all" button
 * @param messages - Localization messages
 * @param closeButton - Whether to show close buttons on files
 * @param layoutMode - Layout mode: "horizontal" | "vertical" | "wrap"
 */
export const renderFilesWithLayout = (files, downloadAll, messages, closeButton, layoutMode = FILES_LAYOUT_MODE.HORIZONTAL) => {
    if (!(files === null || files === void 0 ? void 0 : files.length)) {
        return "";
    }
    let fileItems = "";
    files.forEach(file => {
        fileItems += renderFile(file, closeButton !== null && closeButton !== void 0 ? closeButton : false, true);
    });
    let layoutClass = "";
    if (layoutMode === FILES_LAYOUT_MODE.VERTICAL) {
        layoutClass = ` ${STYLES.filesVertical}`;
    }
    else if (layoutMode === FILES_LAYOUT_MODE.WRAP) {
        layoutClass = ` ${STYLES.filesWrap}`;
    }
    let html = `<ul class="${STYLES.fileWrapper}${layoutClass}" ${REFERENCES.fileWrapper}>`;
    if (layoutMode === FILES_LAYOUT_MODE.HORIZONTAL) {
        html += `<div class="${STYLES.filesScroll}">${fileItems}</div>`;
    }
    else {
        html += fileItems;
    }
    html += "</ul>";
    if (downloadAll && files.length > 1) {
        html += `<div class="${STYLES.downloadButtonWrapper}">
            ${new kendo.ui.Button(`<button class="${STYLES.downloadButton}">${messages.downloadAll}</button>`, {
            icon: ICONS.download,
            fillMode: "flat",
            size: "small"
        }).wrapper[0].outerHTML}
        </div>`;
    }
    return html;
};
/**
 * Renders a message reference (for reply or pinned messages).
 */
export const renderMessageReference = (context, htmlService = inject(HtmlService)) => {
    const { text, files, isOwnMessage, isPinMessage, isDeleted, renderCloseButton, renderFileMenuButton, messages } = context;
    const messageReferenceSenderStyle = isOwnMessage
        ? STYLES.messageReferenceSender
        : STYLES.messageReferenceReceiver;
    const pinMessageReferenceStyle = isPinMessage ? STYLES.messagePinned : "";
    const closeButtonReference = isPinMessage
        ? REFERENCES.pinnedMessageCloseButton
        : REFERENCES.replyMessageCloseButton;
    const wrapperReference = isPinMessage
        ? REFERENCES.messageReferencePinWrapper
        : REFERENCES.messageReferenceReplyWrapper;
    let content = htmlService.convertTextUrlToLink(text || "");
    if (!content) {
        content = (files === null || files === void 0 ? void 0 : files.length) ? renderFile(files[0], false, renderFileMenuButton !== null && renderFileMenuButton !== void 0 ? renderFileMenuButton : false) : "";
    }
    if (isDeleted && messages) {
        content = isOwnMessage
            ? htmlService.encode(messages.selfMessageDeleted)
            : htmlService.encode(messages.otherMessageDeleted);
    }
    return `<div class="${STYLES.messageReference} ${messageReferenceSenderStyle} ${pinMessageReferenceStyle}" ${wrapperReference}>
        ${isPinMessage ? kendo.ui.icon({ icon: ICONS.pin, size: "xlarge" }) : ""}
        <div class="${STYLES.messageReferenceContent}">${content}</div>
        <span class="${STYLES.spacer}"></span>
        ${renderCloseButton ? new kendo.ui.Button(`<button ${closeButtonReference}>`, {
        icon: ICONS.x,
        fillMode: "flat"
    }).wrapper[0].outerHTML : ""}
    </div>`;
};
