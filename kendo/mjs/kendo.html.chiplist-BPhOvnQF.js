/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */
const e={id:`html.chiplist`,name:`Html.ChipList`,category:`web`,description:`HTML rendering utility for Kendo UI for jQuery.`,depends:[`html.base`],features:[]};(function(e,t){var n=window.kendo,r=n.html.HTMLBase,i=function(n,r){return(arguments[0]===t||e.isPlainObject(arguments[0]))&&(r=n,n=e(`<div></div>`)),new a(n,r).html()},a=r.extend({init:function(e,t){var n=this;r.fn.init.call(n,e,t),n.wrapper=n.element.addClass(`k-chip-list`),n._applyAriaAttributes(t),n._addClasses()},options:{name:`HTMLChipList`,size:t,stylingOptions:[`size`]},_applyAriaAttributes:function(t){var n=this;t=e.extend({selectable:`none`},t);var r=(t.attributes||{})[`aria-label`];t.selectable===`none`?n.element.removeAttr(`role aria-label aria-multiselectable aria-orientation`):n.element.attr({"aria-multiselectable":t.selectable===`multiple`,role:`listbox`,"aria-label":r||n.element.attr(`id`)+` listbox`,"aria-orientation":`horizontal`})}});e.extend(n.html,{renderChipList:i,HTMLChipList:a}),n.cssProperties.registerPrefix(`HTMLChipList`,`k-chip-list-`)})(window.kendo.jQuery);var t=kendo;export{t as n,e as t};
//# sourceMappingURL=kendo.html.chiplist-BPhOvnQF.js.map