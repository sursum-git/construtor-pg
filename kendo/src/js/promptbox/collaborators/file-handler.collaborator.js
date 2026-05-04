/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import { STYLES, REFERENCES, NS } from "../constants";
export class FileHandler {
    constructor(context) {
        this.files = [];
        this._dragHandler = null;
        this.context = context;
    }
    initUpload() {
        var _a;
        const options = this.context.getOptions();
        if (!this.showFileSelectButton(options)) {
            return;
        }
        const fileInput = this.context.wrapper.find(`[${REFERENCES.fileInput}]`);
        if (fileInput.length === 0) {
            return;
        }
        const settings = this.getFileSelectButtonSettings(options);
        const multiple = (settings === null || settings === void 0 ? void 0 : settings.multiple) !== false;
        const upload = new kendo.ui.Upload(fileInput, {
            multiple: multiple,
            async: false,
            uniqueFileUids: true,
            select: this.onUploadSelect.bind(this)
        });
        (_a = upload === null || upload === void 0 ? void 0 : upload.wrapper) === null || _a === void 0 ? void 0 : _a.addClass(STYLES.hidden);
        this.uploadInstance = upload;
    }
    getFiles() {
        return [...this.files];
    }
    setFiles(newFiles) {
        this.files = [...newFiles];
        this.renderAttachments();
        this.context.callbacks.onFilesChanged();
    }
    clearFiles() {
        this.files = [];
        this.renderAttachments();
        this.context.callbacks.onFilesChanged();
    }
    hasFiles() {
        return this.files.length > 0;
    }
    handleFileSelectClick() {
        var _a, _b;
        const options = this.context.getOptions();
        const settings = this.getFileSelectButtonSettings(options);
        if (!options.enable || options.readonly || (settings === null || settings === void 0 ? void 0 : settings.enable) === false) {
            return;
        }
        const uploadInput = ((_b = (_a = this.uploadInstance) === null || _a === void 0 ? void 0 : _a.element) === null || _b === void 0 ? void 0 : _b[0]) || null;
        const fileInput = uploadInput || this.context.wrapper.find(`[${REFERENCES.fileInput}]`)[0];
        if (fileInput && typeof fileInput.click === "function") {
            fileInput.click();
        }
    }
    removeAttachmentByUid(uid) {
        const removedFile = this.files.find(file => this.getFileUid(file) === uid);
        const newFiles = this.files.filter(file => this.getFileUid(file) !== uid);
        if (newFiles.length === this.files.length) {
            return;
        }
        this.files = newFiles;
        this.renderAttachments();
        this.context.callbacks.onFilesChanged();
        if (removedFile) {
            this.context.callbacks.onFileRemove(removedFile, [...this.files]);
        }
    }
    renderAttachments() {
        this._destroyDragHandler();
        const header = this.context.wrapper.find(`[${REFERENCES.header}]`);
        const host = this.context.wrapper.find(`[${REFERENCES.attachmentsHost}]`);
        if (host.length === 0) {
            return;
        }
        if (!this.files.length) {
            host.empty();
            if (!this.context.getOptions().headerTemplate) {
                header.addClass(STYLES.hidden);
            }
            return;
        }
        header.removeClass(STYLES.hidden);
        let html = `<ul class="${STYLES.fileBoxWrapper} ${STYLES.fileBoxWrapperScrollableStart}">`;
        html += `<div class="${STYLES.filesScroll}">`;
        for (let i = 0; i < this.files.length; i++) {
            const fileObj = this.files[i];
            const rawFile = (fileObj === null || fileObj === void 0 ? void 0 : fileObj.rawFile) || fileObj;
            const uid = this.getFileUid(fileObj);
            const name = (rawFile === null || rawFile === void 0 ? void 0 : rawFile.name) || (fileObj === null || fileObj === void 0 ? void 0 : fileObj.name) || "";
            const size = (rawFile === null || rawFile === void 0 ? void 0 : rawFile.size) || (fileObj === null || fileObj === void 0 ? void 0 : fileObj.size) || 0;
            const extension = name.lastIndexOf(".") > -1 ? name.substring(name.lastIndexOf(".")).toLowerCase() : "";
            const iconName = this.context.fileUtilsService.getFileGroup(extension, true);
            const fileSizeMessage = this.context.fileUtilsService.getFileSizeMessage(size);
            const icon = kendo.ui.icon({ icon: iconName, size: "xlarge" });
            const removeButton = kendo.html.renderButton(`<button type="button" ${REFERENCES.attachmentRemoveButton} data-uid="${this.context.htmlService.encode(uid)}" aria-label="Remove attachment" title="Remove attachment"></button>`, { icon: "x-mark-sm-outline", fillMode: "flat" });
            html += `<li class="${STYLES.fileBox}">`;
            html += icon;
            html += `<div class="${STYLES.fileInfo}">`;
            html += `<span class="${STYLES.fileName}">${this.context.htmlService.encode(name)}</span>`;
            html += `<span class="${STYLES.fileSize}">${this.context.htmlService.encode(fileSizeMessage)}</span>`;
            html += `</div>`;
            html += removeButton;
            html += `</li>`;
        }
        html += "</div></ul>";
        host.html(html);
        this._attachDragToScroll();
    }
    _attachDragToScroll() {
        const host = this.context.wrapper.find(`[${REFERENCES.attachmentsHost}]`);
        const scrollContainer = host.find("." + STYLES.filesScroll);
        if (!scrollContainer.length) {
            return;
        }
        this._dragHandler = this.context.domUtilsService.createDragToScrollHandler(scrollContainer, {
            namespace: NS + ".promptBoxFileDrag",
            captureElement: this.context.wrapper
        });
        this._dragHandler.attach();
    }
    _destroyDragHandler() {
        if (this._dragHandler) {
            this._dragHandler.destroy();
            this._dragHandler = null;
        }
    }
    destroy() {
        this._destroyDragHandler();
        if (this.uploadInstance) {
            this.uploadInstance.destroy();
            this.uploadInstance = null;
        }
    }
    onUploadSelect(e) {
        e.preventDefault();
        const files = e.files;
        const options = this.context.getOptions();
        const settings = this.getFileSelectButtonSettings(options);
        const validFiles = this.validateFiles(files, settings);
        if (validFiles.length > 0) {
            this.files = [...this.files, ...validFiles];
            this.renderAttachments();
            this.context.callbacks.onFilesChanged();
            this.context.callbacks.onFileSelect(validFiles);
        }
    }
    validateFiles(files, settings) {
        var _a;
        const validFiles = [];
        const restrictions = settings === null || settings === void 0 ? void 0 : settings.restrictions;
        for (let i = 0; i < files.length; i++) {
            const fileObj = files[i];
            const file = fileObj.rawFile || fileObj;
            let isValid = true;
            if (restrictions) {
                if (restrictions.allowedExtensions && restrictions.allowedExtensions.length > 0) {
                    const ext = ((_a = file.name.split('.').pop()) === null || _a === void 0 ? void 0 : _a.toLowerCase()) || '';
                    const allowedExts = restrictions.allowedExtensions.map((e) => e.toLowerCase().replace('.', ''));
                    if (!allowedExts.includes(ext)) {
                        isValid = false;
                    }
                }
                if (restrictions.maxFileSize && file.size > restrictions.maxFileSize) {
                    isValid = false;
                }
                if (restrictions.minFileSize && file.size < restrictions.minFileSize) {
                    isValid = false;
                }
            }
            if (isValid) {
                validFiles.push(fileObj);
            }
        }
        return validFiles;
    }
    getFileUid(file) {
        if (!file) {
            return "";
        }
        if (!file.uid) {
            file.uid = this.context.utilsService.guid();
        }
        return file.uid;
    }
    showFileSelectButton(options) {
        return options.fileSelectButton === true ||
            (typeof options.fileSelectButton === "object" && options.fileSelectButton !== null);
    }
    getFileSelectButtonSettings(options) {
        if (typeof options.fileSelectButton === "object" && options.fileSelectButton !== null) {
            return options.fileSelectButton;
        }
        return undefined;
    }
}
