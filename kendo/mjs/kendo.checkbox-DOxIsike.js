/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */
const e={id:`checkbox`,name:`CheckBox`,category:`web`,description:`The CheckBox widget is used to display boolean value input.`,depends:[`toggleinputbase`,`html.input`]};(function(e,t){var n=window.kendo,r=n.ui,i=r.ToggleInputBase.extend({options:{name:`CheckBox`,checked:null,enabled:!0,encoded:!0,label:null,rounded:t,size:t,wrapperClass:`k-checkbox-wrap`},RENDER_INPUT:n.html.renderCheckBox,NS:`.kendoCheckBox`,value:function(e){return typeof e==`string`&&(e=e===`true`),this.check.apply(this,[e])}});n.cssProperties.registerPrefix(`CheckBox`,`k-checkbox-`),n.cssProperties.registerValues(`CheckBox`,[{prop:`rounded`,values:n.cssProperties.roundedValues.concat([[`full`,`full`]])}]),r.plugin(i)})(window.kendo.jQuery);var t=kendo;export{t as n,e as t};
//# sourceMappingURL=kendo.checkbox-DOxIsike.js.map