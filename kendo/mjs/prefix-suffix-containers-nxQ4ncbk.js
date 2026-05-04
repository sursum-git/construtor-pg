/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */
let e=kendo.jQuery;function t({widget:t,wrapper:n,options:r,prefixInsertBefore:i,suffixInsertAfter:a}){var o=r.prefixOptions,s=r.suffixOptions,c=o.template||o.icon,l=s.template||s.icon,a=a||i,u=r.layoutFlow,d=u?u==`vertical`?`horizontal`:`vertical`:`horizontal`,f=`<span class="k-input-separator k-input-separator-${u==`vertical`?`horizontal`:`vertical`}"></span>`,p,m;o&&c&&(p=n.children(`.k-input-prefix`),p[0]||(r._isInInlineAIPrompt&&(d=u||`horizontal`),p=e(`<span class="k-input-prefix k-input-prefix-${d}" />`),i?p.insertBefore(i):p.prependTo(n)),o.icon&&p.html(kendo.html.renderIcon({icon:o.icon,iconClass:o.iconClass})),o.template&&p.html(kendo.template(o.template)({})),o.separator&&e(f).insertAfter(p)),s&&l&&(m=n.children(`.k-input-suffix`),m[0]||(m=e(`<span class="k-input-suffix k-input-suffix-${d}" />`).appendTo(n),a?m.insertAfter(a):m.appendTo(n)),s.icon&&m.html(kendo.html.renderIcon({icon:s.icon,iconClass:s.iconClass})),s.template&&m.html(kendo.template(s.template)({})),s.separator&&e(f).insertBefore(m)),t._prefixContainer=p,t._suffixContainer=m}export{t};
//# sourceMappingURL=prefix-suffix-containers-nxQ4ncbk.js.map