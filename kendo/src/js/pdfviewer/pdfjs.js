/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

/* eslint no-console: ["error", { allow: ["warn", "error"] }] */

export function isLoaded() {
    if (!window.pdfjsLib) {
        console?.error(`PDF.JS library is required. Make sure that it is properly loaded <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.6.82/pdf.mjs" type="module"></script>`);
        return false;
    }

    if (!window.pdfjsLib?.GlobalWorkerOptions?.workerSrc && !window.pdfjsWorker) {
        console?.error(`The pdf.worker.mjs script is not loaded. The PDF.JS library will not work correctly.
    Either load the script:
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.6.82/pdf.worker.mjs" type="module"></script>
    
    Or set it to the GlobalWorkerOptions.workerSrc property:
    
    <script type="module">
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.6.82/pdf.worker.mjs';
    </script>`);
    return false;
    }

    return true;
}

kendo.pdfviewer.pdfjs = { lib: window.pdfjsLib };