/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../../kendo.icons.js";
import { inject } from "../../core/service-container";
import { HtmlService } from "../../core/services/html.service";
import { DateParserService } from "../../core/services/date-parser.service";
import { FormatterService } from "../../core/services/formatter.service";
import { DateUtilsService } from "../../core/services/date-utils.service";
import { UtilsService } from "../../core/services/utils.service";
import { STYLES, ICONS, REFERENCES, MESSAGE_STATUS, FILES_LAYOUT_MODE } from "../constants";
import { renderAvatar, renderTypingIndicator } from "./common.template";
import { renderFiles, renderMessageReference } from "./file.template";
/**
 * Renders the retry button for failed messages.
 */
export const renderRetryButton = (message, retryText) => {
    if (!message.failed) {
        return "";
    }
    return kendo.html.renderButton(`<button class="${STYLES.messageRetryButton}" ${REFERENCES.retryButton} title="${retryText}" aria-label="${retryText}" data-uid="${message.uid || ""}"></button>`, { icon: ICONS.retry, size: "small", fillMode: "flat" });
};
/**
 * Renders the message status indicator.
 * Supports custom status settings with icon/svgIcon for enhanced display.
 */
export const renderMessageStatus = (status, message, statusTemplate, htmlService = inject(HtmlService), statusSettings) => {
    if (!status) {
        return "";
    }
    if (statusTemplate) {
        return statusTemplate({ status, message });
    }
    const settings = statusSettings === null || statusSettings === void 0 ? void 0 : statusSettings[status];
    const statusClass = status === MESSAGE_STATUS.FAILED
        ? `${STYLES.messageStatus} ${STYLES.messageFailed}${(settings === null || settings === void 0 ? void 0 : settings.cssClass) ? " " + settings.cssClass : ""}`
        : `${STYLES.messageStatus}${(settings === null || settings === void 0 ? void 0 : settings.cssClass) ? " " + settings.cssClass : ""}`;
    let iconHtml = "";
    if (settings === null || settings === void 0 ? void 0 : settings.icon) {
        iconHtml = kendo.ui.icon({ icon: settings.icon });
    }
    else if (settings === null || settings === void 0 ? void 0 : settings.svgIcon) {
        iconHtml = kendo.ui.icon({ icon: settings.svgIcon });
    }
    const statusText = (settings === null || settings === void 0 ? void 0 : settings.text) !== undefined
        ? settings.text
        : status.charAt(0).toUpperCase() + status.slice(1);
    return `<span class="${statusClass}">${iconHtml}${htmlService.encode(statusText)}</span>`;
};
/**
 * Renders a single attachment card.
 */
export const renderAttachment = (attachment, message, attachmentTemplate, htmlService = inject(HtmlService)) => {
    var _a;
    if (attachmentTemplate) {
        return attachmentTemplate({ attachment, message });
    }
    const title = attachment.title ? `<div class="${STYLES.cardTitle}">${htmlService.encode(attachment.title)}</div>` : "";
    const subtitle = attachment.subtitle ? `<div class="${STYLES.cardSubtitle}">${htmlService.encode(attachment.subtitle)}</div>` : "";
    const image = attachment.thumbnailUrl ? `<div class="${STYLES.cardMedia}"><img src="${htmlService.encode(attachment.thumbnailUrl)}" alt="" /></div>` : "";
    const actions = ((_a = attachment.actions) === null || _a === void 0 ? void 0 : _a.length)
        ? `<div class="${STYLES.cardActions}">${attachment.actions.map(a => `<button class="${STYLES.cardAction} ${STYLES.button}">${htmlService.encode(a.text)}</button>`).join("")}</div>`
        : "";
    return `<div class="${STYLES.card}">
        ${image}
        <div class="${STYLES.cardBody}">
            ${title}
            ${subtitle}
        </div>
        ${actions}
    </div>`;
};
/**
 * Renders attachments with the specified layout.
 */
export const renderAttachments = (attachments, message, layout = "list", attachmentTemplate) => {
    if (!(attachments === null || attachments === void 0 ? void 0 : attachments.length)) {
        return "";
    }
    const attachmentsHtml = attachments.map(a => renderAttachment(a, message, attachmentTemplate)).join("");
    const layoutClass = layout === "carousel" ? STYLES.cardDeckScrollWrap : STYLES.cardList;
    return `<div class="${layoutClass}">${attachmentsHtml}</div>`;
};
/**
 * Renders a single chat message.
 */
export const renderMessage = (message, replyMessage, downloadAll, messages, expandable, messageTimeFormat, skipSanitization, statusTemplate, filesLayoutMode = FILES_LAYOUT_MODE.VERTICAL, htmlService = inject(HtmlService), formatterService = inject(FormatterService), dateParserService = inject(DateParserService), contentTemplate, attachmentTemplate, attachmentLayout) => {
    var _a;
    const isDeleted = message.isDeleted;
    const isFailed = message.failed || message.status === MESSAGE_STATUS.FAILED;
    const expandableClasses = expandable && !message.isTyping
        ? [STYLES.bubbleExpandable, STYLES.expanded]
        : [];
    const replyMessageHtml = replyMessage
        ? renderMessageReference({
            text: replyMessage.text,
            files: replyMessage.files,
            isOwnMessage: replyMessage.isOwnMessage,
            isPinMessage: false,
            renderCloseButton: false,
            renderFileMenuButton: false
        })
        : "";
    let messageContent = "";
    if (message.isTyping && !message.isOwnMessage) {
        messageContent = renderTypingIndicator();
    }
    else if (contentTemplate && !isDeleted) {
        messageContent = contentTemplate(message);
    }
    else if (isDeleted) {
        messageContent = message.isOwnMessage
            ? htmlService.encode(messages.selfMessageDeleted)
            : htmlService.encode(messages.otherMessageDeleted);
    }
    else {
        messageContent = htmlService.convertTextUrlToLink(message.text || "", skipSanitization);
    }
    const attachmentsHtml = !isDeleted
        ? renderAttachments(message.attachments, message, attachmentLayout || message.attachmentLayout || "list", attachmentTemplate)
        : "";
    const timeHtml = `<time class="${STYLES.messageTime}">${formatterService.toString(dateParserService.parseDate(message.timestamp), messageTimeFormat)}</time>`;
    const statusHtml = renderMessageStatus(message.status, message, statusTemplate, htmlService);
    const retryHtml = isFailed ? renderRetryButton(message, messages.retryMessage || "Retry") : "";
    const messageInfoHtml = `<div class="${STYLES.messageInfo}">${timeHtml}${statusHtml}${retryHtml}</div>`;
    return `<div class="${STYLES.message}${isDeleted ? " " + STYLES.messageRemoved : ""}${isFailed ? " " + STYLES.messageFailed : ""}" data-uid="${htmlService.encode((_a = message.uid) !== null && _a !== void 0 ? _a : "")}">
        <div class="${STYLES.chatBubble} ${STYLES.bubble} ${expandableClasses.join(" ")}">
            <div class="${STYLES.bubbleContent}">
                ${replyMessageHtml}
                <span class="${STYLES.chatBubbleText}">${messageContent}</span>
                ${!isDeleted ? renderFiles(message.files, downloadAll, messages, false, filesLayoutMode) : ""}
                ${attachmentsHtml}
            </div>
            ${expandable && !message.isTyping ? `<span class="${STYLES.bubbleExpandableIndicator}" ${REFERENCES.bubbleExpandableIndicator}>${kendo.ui.icon({ icon: ICONS.chevronUp })}</span>` : ""}
        </div>
        ${messageInfoHtml}
        ${!isDeleted ? `<div class="${STYLES.messageToolbar}"></div>` : ""}
    </div>`;
};
/**
 * Renders a message group with author avatar and multiple messages.
 */
export const renderMessageGroup = (context, utilsService = inject(UtilsService), dateUtilsService = inject(DateUtilsService), htmlService = inject(HtmlService), dateParserService = inject(DateParserService)) => {
    var _a;
    const { message, author, isOwnMessage, replyMessage, downloadAll = true, messages, expandable = false, fullWidth = false, messageTimeFormat = "ddd MMM dd yyyy", timestampTemplate, statusTemplate, showTimestamp = false, messageTemplate, skipSanitization = false, messageSettings, filesLayoutMode = FILES_LAYOUT_MODE.VERTICAL, contentTemplate, authorMessageContentTemplate, receiverMessageContentTemplate, attachmentTemplate, attachmentLayout, userStatusTemplate } = context;
    // Determine the effective content template based on message author
    const effectiveContentTemplate = isOwnMessage
        ? (authorMessageContentTemplate || contentTemplate)
        : (receiverMessageContentTemplate || contentTemplate);
    // Apply user-specific settings if provided
    const effectiveShowAvatar = (messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.showAvatar) !== undefined
        ? messageSettings.showAvatar && author && author.imageUrl
        : author && author.imageUrl;
    const effectiveShowUsername = (messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.showUsername) !== undefined
        ? messageSettings.showUsername
        : true;
    const effectiveExpandable = (messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.allowMessageCollapse) !== undefined
        ? messageSettings.allowMessageCollapse
        : expandable;
    const effectiveFullWidth = (messageSettings === null || messageSettings === void 0 ? void 0 : messageSettings.messageWidthMode) === "full" || fullWidth;
    const groupClasses = [
        STYLES.messageGroup,
        isOwnMessage ? STYLES.messageGroupSender : STYLES.messageGroupReceiver,
        effectiveShowAvatar ? "" : STYLES.noAvatar,
        effectiveFullWidth ? STYLES.messageGroupFullWidth : ""
    ].filter(Boolean).join(" ");
    let timestampContent = "";
    if (showTimestamp && message.timestamp) {
        const messageDate = dateParserService.parseDate(message.timestamp);
        if (utilsService.isFunction(timestampTemplate)) {
            timestampContent = timestampTemplate({ date: messageDate, message });
        }
        else {
            const relativeDateText = dateUtilsService.getRelativeDateString(messageDate);
            timestampContent = `<div class="${STYLES.timestamp}">${relativeDateText}</div>`;
        }
    }
    const userStatusHtml = userStatusTemplate ? userStatusTemplate({ author }) : "";
    // Use custom messageTemplate or default renderMessage
    let messageHtml;
    if (messageTemplate) {
        messageHtml = messageTemplate(Object.assign(Object.assign({}, message), { isOwnMessage, author }), replyMessage !== null && replyMessage !== void 0 ? replyMessage : null, downloadAll, messages, effectiveExpandable, messageTimeFormat, skipSanitization);
    }
    else {
        messageHtml = renderMessage(Object.assign(Object.assign({}, message), { isOwnMessage, author }), replyMessage !== null && replyMessage !== void 0 ? replyMessage : null, downloadAll, messages, effectiveExpandable, messageTimeFormat, skipSanitization, statusTemplate, filesLayoutMode, htmlService, undefined, undefined, effectiveContentTemplate, attachmentTemplate, attachmentLayout);
    }
    return `${showTimestamp && timestampContent ? timestampContent : ""}
        <div class="${groupClasses}">${userStatusHtml}
            ${effectiveShowAvatar ? renderAvatar(author.imageUrl, author.imageAltText) : ""}
            <div class="${STYLES.messageGroupContent}">
                ${effectiveShowUsername ? `<span class="${STYLES.messageAuthor}">${htmlService.encode((_a = author.name) !== null && _a !== void 0 ? _a : "")}</span>` : ""}
                ${messageHtml}
            </div>
        </div>`;
};
