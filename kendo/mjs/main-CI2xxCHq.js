/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */
const e=e=>e?new Date(e.getTime()):null,t=(t,n)=>{let r=e(t);return n===0&&r.getHours()===23&&r.setHours(r.getHours()+2),r},n=(n,r)=>{let i=e(n);return i.setDate(i.getDate()+r),t(i,n.getHours())},r=(e,n,r,i=0,a=0,o=0,s=0)=>{let c=new Date(e,n,r,i,a,o,s);return e>-1&&e<100&&c.setFullYear(c.getFullYear()-1900),t(c,i)},i=e=>n(r(e.getFullYear(),e.getMonth()+1,1,e.getHours(),e.getMinutes(),e.getSeconds(),e.getMilliseconds()),-1),a=(e,t)=>e.getMonth()===t?e:i(o(e,-1)),o=(n,r)=>{let i=e(n),o=(12+(i.getMonth()+r)%12)%12;return i.setMonth(i.getMonth()+r),a(t(i,n.getHours()),o)};var s;(function(e){e[e.Forward=1]=`Forward`,e[e.Backward=-1]=`Backward`})(s||={});const c=(n,r,i=s.Forward)=>{let a=e(n),o=(r-a.getDay()+7*i)%7;return a.setDate(a.getDate()+o),t(a,n.getHours())};var l;(function(e){e[e.Sunday=0]=`Sunday`,e[e.Monday=1]=`Monday`,e[e.Tuesday=2]=`Tuesday`,e[e.Wednesday=3]=`Wednesday`,e[e.Thursday=4]=`Thursday`,e[e.Friday=5]=`Friday`,e[e.Saturday=6]=`Saturday`})(l||={});const u=e=>r(e.getFullYear(),e.getMonth(),1,e.getHours(),e.getMinutes(),e.getSeconds(),e.getMilliseconds()),d=e=>r(e.getFullYear(),e.getMonth(),e.getDate(),0,0,0),f=(e,t)=>!e&&!t?!0:e&&t&&e.getTime()===t.getTime();new Date().getTime(),Symbol.toPrimitive;export{o as a,n as c,c as i,e as l,d as n,i as o,u as r,r as s,f as t};
//# sourceMappingURL=main-CI2xxCHq.js.map