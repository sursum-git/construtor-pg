/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.button.js"; // Still required until kendo.ui is modularized
import "../../kendo.avatar.js";
import "../../kendo.icons.js";
import { inject } from "../../core/service-container";
import { HtmlService } from "../../core/services/html.service";
import { FileUtilsService } from "../../core/services/file-utils.service";
import { STYLES, REFERENCES, ICONS } from "../constants";
/**
 * Renders an avatar element for a message author.
 */
export const renderAvatar = (url, altText, htmlService = inject(HtmlService)) => {
    return new kendo.ui.Avatar("<div>", {
        type: "image",
        image: url,
        alt: htmlService.encode(altText !== null && altText !== void 0 ? altText : "")
    }).wrapper[0].outerHTML;
};
/**
 * Renders a typing indicator animation.
 */
export const renderTypingIndicator = () => {
    return `<div class="${STYLES.typingIndicator}">
        <span></span>
        <span></span>
        <span></span>
    </div>`;
};
/**
 * Renders a single file attachment item.
 * Uses small-sized buttons for menu/download actions per Chat v3 spec.
 */
export const renderFile = (file, closeButton, fileMenuButton = true, htmlService = inject(HtmlService), fileUtilsService = inject(FileUtilsService)) => {
    const closeButtonHtml = closeButton
        ? new kendo.ui.Button(`<button class="" ${REFERENCES.fileCloseButton}>`, {
            icon: ICONS.x,
            fillMode: "flat",
            size: "small"
        }).wrapper[0].outerHTML
        : "";
    // Use small size for file menu button
    const fileMenuButtonHtml = fileMenuButton && !closeButton
        ? new kendo.ui.Button(`<button class="${STYLES.menuButton}" ${REFERENCES.fileMenuButton}>`, {
            icon: ICONS.attachmentMenu,
            fillMode: "flat",
            size: "small"
        }).wrapper[0].outerHTML
        : "";
    return `<li class="${STYLES.file}" data-uid="${htmlService.encode(file.uid)}">
        ${kendo.ui.icon({ icon: fileUtilsService.getFileGroup(file.extension, true), size: "xlarge" })}
        <div class="${STYLES.fileInfo}">
            <span class="${STYLES.fileName}">${htmlService.encode(file.name)}</span>
            <span class="${STYLES.fileSize}">${htmlService.encode(fileUtilsService.getFileSizeMessage(file.size))}</span>
        </div>
        ${closeButtonHtml}
        ${fileMenuButtonHtml}
    </li>`;
};
