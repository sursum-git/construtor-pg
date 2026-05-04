/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */
let e=kendo.jQuery;function t(t,n){var r=this,i=r.wrapper;if(t&&r.loader){if(!r.wrapper.find(`.k-loader-container`).length){var a={message:`Loading...`,overlayColor:`dark`,themeColor:`primary`};a=e.extend({},a,n);let t=kendo.html.renderLoaderContainer(e(`<div></div>`),a),o=r.wrapper.width(),s=e(`<div class='k-loading-pdf-mask'></div>`),c=r.wrapper.clone().removeAttr(`id`).addClass(`k-loading-pdf-progress`).width(o);s.append(c),s.append(t),r.mask=s,i.append(s),r.wrapperClone=s.find(`.k-pivotgrid`),r.loaderOverlay=s.find(`.k-loader-container`),r.loader.element.insertBefore(s.find(`.k-loader-container-label`))}}else r.loaderOverlay.length&&(kendo.destroy(r.loaderOverlay),r.mask.remove())}export{t};
//# sourceMappingURL=loaderContainer-BHi4xBou.js.map