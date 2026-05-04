/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */
import{t as e}from"./jszip-esm2015-CmnghC16.js";let t={compile:function(e){return e}};var n=class{static register(e){t=e}static compile(e){return t.compile(e)}};const r=/\[(?:(\d+)|['"](.*?)['"])\]|((?:(?!\[.*?\]|\.).)+)/g,i={},a=`undefined`;i[a]=function(e){return e};function o(e){if(i[e])return i[e];let t=[];return e.replace(r,function(e,n,r,i){t.push(typeof n===a?r||i:n)}),i[e]=function(e){let n=e;for(let e=0;e<t.length&&n;e++)n=n[t[e]];return n},i[e]}function s(e,t){return e.reduce((e,n,r)=>{let i=t(n,r);return i!=null&&e.push(i),e},[])}function c(e){return`${e.title}: ${e.value}`}function l(e,t){let n=[];for(let r=0;r<e;r++)n.push(t(r));return n}function u(e){return e.id}var d=class{constructor(e){e.columns=this._trimColumns(e.columns||[]),this.allColumns=s(this._leafColumns(e.columns||[]),this._prepareColumn),this.columns=this._visibleColumns(this.allColumns),this.options=e,this.data=e.data||[],this.aggregates=e.aggregates||{},this.groups=[].concat(e.groups||[]),this.hasGroups=this.groups.length>0,this.hierarchy=e.hierarchy,this.hasGroupHeaderColumn=this.columns.some(e=>e.groupHeaderColumnTemplate),this.collapsible=this.options.collapsible}workbook(){return{sheets:[{columns:this._columns(),rows:this.hierarchy?this._hierarchyRows():this._rows(),freezePane:this._freezePane(),filter:this._filter()}]}}_trimColumns(e){return e.filter(e=>{let t=!!e.field;return!t&&e.columns&&(t=this._trimColumns(e.columns).length>0),t})}_leafColumns(e){let t=[];for(let n=0;n<e.length;n++)e[n].columns?t=t.concat(this._leafColumns(e[n].columns)):t.push(e[n]);return t}_prepareColumn(e){if(!e.field)return null;let t=function(t){return o(e.field,!0)(t)},r=null;return e.values&&(r={},e.values.forEach(function(e){r[e.value]=e.text}),t=function(t){return r[o(e.field,!0)(t)]}),Object.assign({},e,{value:t,values:r,groupHeaderTemplate:e.groupHeaderTemplate?n.compile(e.groupHeaderTemplate):c,groupHeaderColumnTemplate:e.groupHeaderColumnTemplate?n.compile(e.groupHeaderColumnTemplate):null,groupFooterTemplate:e.groupFooterTemplate?n.compile(e.groupFooterTemplate):null,footerTemplate:e.footerTemplate?n.compile(e.footerTemplate):null})}_filter(){if(!this.options.filterable)return null;let e=this._depth();return{from:e,to:e+this.columns.length-1}}_createPaddingCells(e){return l(e,()=>Object.assign({background:`#dfdfdf`,color:`#333`},this.options.paddingCellOptions))}_dataRow(e,t,n){let r=this._createPaddingCells(t);if(this.hasGroups&&n&&e.items){r=r.concat(this._groupHeaderCells(e,t,n));let i=this._dataRows(e.items,t+1);return i.unshift({type:`group-header`,cells:r,level:this.collapsible?t:null}),i.concat(this._footer(e,t))}let i=[];for(let t=0;t<this.columns.length;t++)i[t]=this._cell(e,this.columns[t]);return this.hierarchy&&(i[0].colSpan=n-t+1),[{type:`data`,cells:r.concat(i),level:this.collapsible?t:null}]}_groupHeaderCells(e,t,n){let r=[],i=this.allColumns.filter(function(t){return t.field===e.field})[0]||{},a=i&&i.title?i.title:e.field,o=i?i.groupHeaderTemplate||i.groupHeaderColumnTemplate:null,s=Object.assign({title:a,field:e.field,value:i&&i.values?i.values[e.value]:e.value,aggregates:e.aggregates,items:e.items},e.aggregates[e.field]),c=o?o(s):`${a}: ${e.value}`;return r.push(Object.assign({value:c,background:`#dfdfdf`,color:`#333`,colSpan:(this.hasGroupHeaderColumn?1:this.columns.length)+n-t},i.groupHeaderCellOptions)),this.hasGroupHeaderColumn&&this.columns.forEach(function(t,n){n>0&&r.push(Object.assign({background:`#dfdfdf`,color:`#333`,value:t.groupHeaderColumnTemplate?t.groupHeaderColumnTemplate(Object.assign({group:s},s,e.aggregates[t.field])):void 0},t.groupHeaderCellOptions))}),r}_dataRows(e,t){let n=this._depth(),r=[];for(let i=0;i<e.length;i++)r.push.apply(r,this._dataRow(e[i],t,n));return r}_hierarchyRows(){let e=this._depth(),t=this.data,n=this.hierarchy.itemLevel,r=this.hierarchy.itemId||u,i=this._hasFooterTemplate(),a=[],o=[],s=0,c;i||(this.collapsible=!1);for(let l=0;l<t.length;l++){let u=t[l],d=n(u,l);i&&(d>s?o.push({id:c,level:s}):d<s&&a.push.apply(a,this._hierarchyFooterRows(o,d,e)),s=d,c=r(u,l)),a.push.apply(a,this._dataRow(u,d+1,e))}if(i){a.push.apply(a,this._hierarchyFooterRows(o,0,e));let n=t.length?this.aggregates[t[0].parentId]:{};a.push(this._hierarchyFooter(n,0,e))}return this._prependHeaderRows(a),a}_hierarchyFooterRows(e,t,n){let r=[];for(;e.length&&e[e.length-1].level>=t;){let t=e.pop();r.push(this._hierarchyFooter(this.aggregates[t.id],t.level+1,n))}return r}_hasFooterTemplate(){let e=this.columns;for(let t=0;t<e.length;t++)if(e[t].footerTemplate)return!0}_hierarchyFooter(e,t,n){let r=this.columns.map(function(r,i){let a=i?1:n-t+1;if(r.footerTemplate){let t=(e||{})[r.field];return Object.assign({background:`#dfdfdf`,color:`#333`,colSpan:a,value:r.footerTemplate(Object.assign({aggregates:e},t))},r.footerCellOptions)}return Object.assign({background:`#dfdfdf`,color:`#333`,colSpan:a},r.footerCellOptions)});return{type:`footer`,cells:this._createPaddingCells(t).concat(r),level:this.collapsible?t:null}}_footer(e,t){let n=[],r=this.columns.some(e=>e.groupFooterTemplate),i,a;r&&(a={group:{items:e.items,field:e.field,value:e.value}},i={},Object.keys(e.aggregates).forEach(t=>{i[t]=Object.assign({},e.aggregates[t],a)}));let o=this.columns.map(t=>{if(t.groupFooterTemplate){let n=Object.assign({},i,e.aggregates[t.field],a);return Object.assign({background:`#dfdfdf`,color:`#333`,value:t.groupFooterTemplate(n)},t.groupFooterCellOptions)}return Object.assign({background:`#dfdfdf`,color:`#333`},t.groupFooterCellOptions)});return r&&n.push({type:`group-footer`,cells:this._createPaddingCells(this.groups.length).concat(o),level:this.collapsible?t:null}),n}_isColumnVisible(e){return this._visibleColumns([e]).length>0&&(e.field||e.columns)}_visibleColumns(e){return e.filter(e=>{let t=e.exportable;typeof t==`object`&&(t=e.exportable.excel);let n=!e.hidden&&t!==!1,r=e.hidden&&t===!0,i=n||r;return i&&e.columns&&(i=this._visibleColumns(e.columns).length>0),i})}_headerRow(e,t){let n=e.cells.map(function(t){return Object.assign(t,{colSpan:t.colSpan>1?t.colSpan:1,rowSpan:e.rowSpan>1&&!t.colSpan?e.rowSpan:1})});return this.hierarchy&&n[0].firstCell&&(n[0].colSpan+=this._depth()),{type:`header`,cells:l(t.length,()=>Object.assign({background:`#7a7a7a`,color:`#fff`},this.options.headerPaddingCellOptions)).concat(n)}}_prependHeaderRows(e){let t=this.groups,n=[{rowSpan:1,cells:[],index:0}];this._prepareHeaderRows(n,this.options.columns);for(let r=n.length-1;r>=0;r--)e.unshift(this._headerRow(n[r],t))}_prepareHeaderRows(e,t,n,r){let i=r||e[e.length-1],a=e[i.index+1],o=0;for(let r=0;r<t.length;r++){let s=t[r];if(this._isColumnVisible(s)){let t=Object.assign({background:`#7a7a7a`,color:`#fff`,value:s.title||s.field,colSpan:0,firstCell:r===0&&(!n||n.firstCell)},s.headerCellOptions);i.cells.push(t),s.columns&&s.columns.length&&(a||(a={rowSpan:0,cells:[],index:e.length},e.push(a)),t.colSpan=this._trimColumns(this._visibleColumns(s.columns)).length,this._prepareHeaderRows(e,s.columns,t,a),o+=t.colSpan-1,i.rowSpan=e.length-i.index)}}n&&(n.colSpan+=o)}_rows(){let e=this._dataRows(this.data,0);if(this.columns.length){this._prependHeaderRows(e);let t=!1,n=this.columns.map(e=>e.footerTemplate?(t=!0,Object.assign({background:`#dfdfdf`,color:`#333`,value:e.footerTemplate(Object.assign({},this.aggregates,this.aggregates[e.field]))},e.footerCellOptions)):Object.assign({background:`#dfdfdf`,color:`#333`},e.footerCellOptions));t&&e.push({type:`footer`,cells:this._createPaddingCells(this.groups.length).concat(n)})}return e}_headerDepth(e){let t=0;for(let n=0;n<e.length;n++)if(e[n].columns){let r=this._headerDepth(e[n].columns);r>t&&(t=r)}return 1+t}_freezePane(){let e=this._visibleColumns(this.options.columns||[]),t=this._visibleColumns(this._trimColumns(this._leafColumns(e.filter(function(e){return e.locked})))).length;return{rowSplit:this._headerDepth(e),colSplit:t?t+this.groups.length:0}}_cell(e,t){return Object.assign({value:t.value(e)},t.cellOptions)}_depth(){let e=0;return e=this.hierarchy?this.hierarchy.depth:this.groups.length,e}_columns(){return l(this._depth(),()=>({width:20})).concat(this.columns.map(function(e){return{width:parseInt(e.width,10),autoWidth:!e.width}}))}};let f={toString:e=>e};var p=class{static register(e){f=e}static toString(e,t){return f.toString(e,t)}};function m(){return new e}function h(e,t,n){return(1461*(e+4800+((t-13)/12|0))/4|0)+(367*(t-1-12*((t-13)/12|0))/12|0)-(3*((e+4900+((t-13)/12|0))/100|0)/4|0)+n-32075}const g=h(1900,0,-1);function _(e,t,n){return h(e,t,n)-g}function v(e,t,n,r){return(e+(t+(n+r/1e3)/60)/60)/24}function y(e){let t=v(e.getHours(),e.getMinutes(),e.getSeconds(),e.getMilliseconds()),n=_(e.getFullYear(),e.getMonth(),e.getDate());return n<0?n-1+t:n+t}const b=`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,x=`data:${b};base64,`,S={compression:`DEFLATE`,type:`base64`},ee={compression:`DEFLATE`,type:`blob`},C={compression:`DEFLATE`,type:`arraybuffer`};function w(e){return x+e}function T(e,t){return t.indexOf(e)}const E=JSON.parse.bind(JSON);function D(e){return String(e).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/\"/g,`&quot;`).replace(/\'/g,`&#39;`)}function O(e,t){let n=``;for(let r=0;r<e;++r)n+=t(r);return n}function k(e,t){let n=``;if(e!=null)if(Array.isArray(e))for(let r=0;r<e.length;++r)n+=t(e[r],r);else typeof e==`object`&&Object.keys(e).forEach((r,i)=>{n+=t(e[r],r,i)});return n}const A=`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\r`,j=`${A}
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
               <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
               <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
               <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
            </Relationships>`,M=({creator:e,lastModifiedBy:t,created:n,modified:r})=>`${A}
 <cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
   xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"
   xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
   <dc:creator>${D(e)}</dc:creator>
   <cp:lastModifiedBy>${D(t)}</cp:lastModifiedBy>
   <dcterms:created xsi:type="dcterms:W3CDTF">${D(n)}</dcterms:created>
   <dcterms:modified xsi:type="dcterms:W3CDTF">${D(r)}</dcterms:modified>
</cp:coreProperties>`,N=({sheets:e})=>`${A}
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Microsoft Excel</Application>
  <DocSecurity>0</DocSecurity>
  <ScaleCrop>false</ScaleCrop>
  <HeadingPairs>
    <vt:vector size="2" baseType="variant">
      <vt:variant>
        <vt:lpstr>Worksheets</vt:lpstr>
      </vt:variant>
      <vt:variant>
        <vt:i4>${e.length}</vt:i4>
      </vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="${e.length}" baseType="lpstr">${k(e,(e,t)=>e.options.title?`<vt:lpstr>${D(e.options.title)}</vt:lpstr>`:`<vt:lpstr>Sheet${t+1}</vt:lpstr>`)}</vt:vector>
  </TitlesOfParts>
  <LinksUpToDate>false</LinksUpToDate>
  <SharedDoc>false</SharedDoc>
  <HyperlinksChanged>false</HyperlinksChanged>
  <AppVersion>14.0300</AppVersion>
</Properties>`,te=({sheetCount:e,commentFiles:t,drawingFiles:n})=>`${A}
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="gif" ContentType="image/gif"/>
  <Default Extension="jpg" ContentType="image/jpeg"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml" />
  <Default Extension="xml" ContentType="application/xml" />
  <Default Extension="vml" ContentType="application/vnd.openxmlformats-officedocument.vmlDrawing"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml" />
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  ${O(e,e=>`<Override PartName="/xl/worksheets/sheet${e+1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml" />`)}
  ${k(t,e=>`<Override PartName="/xl/${e}" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml"/>`)}
  ${k(n,e=>`<Override PartName="/xl/drawings/${e}" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>`)}
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml" />
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml" />
</Types>`,ne=({sheets:e,filterNames:t,userNames:n})=>`${A}
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <fileVersion appName="xl" lastEdited="5" lowestEdited="5" rupBuild="9303" />
  <workbookPr defaultThemeVersion="124226" />
  <bookViews>
    <workbookView xWindow="240" yWindow="45" windowWidth="18195" windowHeight="7995" />
  </bookViews>
  <sheets>
  ${k(e,({options:e},t)=>{let n=e.name||e.title||`Sheet${t+1}`,r=e.state||`visible`;return`<sheet name="${D(n)}" state="${r}" sheetId="${t+1}" r:id="rId${t+1}" />`})}
  </sheets>
  ${t.length||n.length?`
    <definedNames>
      ${k(t,e=>`
         <definedName name="_xlnm._FilterDatabase" hidden="1" localSheetId="${e.localSheetId}">${D(Ae(e.name))}!${D(e.from)}:${D(e.to)}</definedName>`)}
      ${k(n,e=>`
         <definedName name="${e.name}" hidden="${e.hidden?1:0}" ${e.localSheetId==null?``:`localSheetId="${e.localSheetId}"`}>${D(e.value)}</definedName>`)}
    </definedNames>`:``}
  <calcPr fullCalcOnLoad="1" calcId="145621" />
</workbook>`,re=({frozenColumns:e,frozenRows:t,columns:n,defaults:r,data:i,index:a,mergeCells:o,autoFilter:s,filter:c,showGridLines:l,hyperlinks:u,validations:d,defaultCellStyleId:f,rtl:p,legacyDrawing:m,drawing:h,lastRow:g,lastCol:_,hasDisabledCells:v})=>`${A}
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac" mc:Ignorable="x14ac">
   ${g&&_?`<dimension ref="A1:${F(g-1,_-1)}" />`:``}

   <sheetViews>
     <sheetView ${p?`rightToLeft="1"`:``} ${a===0?`tabSelected="1"`:``} workbookViewId="0" ${l===!1?`showGridLines="0"`:``}>
     ${t||e?`
       <pane state="frozen"
         ${e?`xSplit="${e}"`:``}
         ${t?`ySplit="${t}"`:``}
         topLeftCell="${String.fromCharCode(65+(e||0))+((t||0)+1)}"
       />`:``}
     </sheetView>
   </sheetViews>

   <sheetFormatPr x14ac:dyDescent="0.25" ${r.skipCustomHeight?``:`customHeight="1"`} defaultRowHeight="${r.rowHeight?r.rowHeight*.75:15}"
     ${r.columnWidth?`defaultColWidth="${R(r.columnWidth)}"`:``} />

   ${f!=null||n&&n.length>0?`
     <cols>
       ${!n||!n.length?`
         <col min="1" max="16384" style="${f}"
              ${r.columnWidth?`width="${R(r.columnWidth)}"`:``} /> `:``}
       ${k(n,(e,t)=>{let n=typeof e.index==`number`?e.index+1:t+1;return e.width===0?`<col ${f==null?``:`style="${f}"`}
                        min="${n}" max="${n}" hidden="1" customWidth="1" />`:`<col ${f==null?``:`style="${f}"`}
                      min="${n}" max="${n}" customWidth="1"
                      ${e.autoWidth?`width="${(e.width*7+5)/7*256/256}" bestFit="1"`:`width="${R(e.width)}"`} />`})}
     </cols>`:``}

   <sheetData>
     ${k(i,(e,t)=>`
         <row r="${typeof e.index==`number`?e.index+1:t+1}" x14ac:dyDescent="0.25"
              ${e.level?`outlineLevel="${e.level}"`:``}
              ${e.height===0?`hidden="1"`:e.height?`ht="${z(e.height)}" customHeight="1"`:``}>
           ${k(e.data,e=>`
             <c r="${e.ref}" ${e.style?`s="${e.style}"`:``} ${e.type?`t="${e.type}"`:``}>
               ${e.formula==null?``:fe(e.formula)}
               ${e.value==null?``:`<v>${D(e.value)}</v>`}
             </c>`)}
         </row>
       `)}
   </sheetData>

   ${v?`<sheetProtection sheet="1" objects="1" scenarios="1"/>`:``}

   ${s?`<autoFilter ref="${s.from}:${s.to}"/>`:c?$(c):``}

   ${o.length?`
     <mergeCells count="${o.length}">
       ${k(o,e=>`<mergeCell ref="${e}"/>`)}
     </mergeCells>`:``}

   ${d.length?`
     <dataValidations>
       ${k(d,e=>`
         <dataValidation sqref="${e.sqref.join(` `)}"
                         showErrorMessage="${e.showErrorMessage}"
                         type="${D(e.type)}"
                         ${e.type===`list`?``:`operator="${D(e.operator)}"`}
                         allowBlank="${e.allowBlank}"
                         showDropDown="${e.showDropDown}"
                         ${e.error?`error="${D(e.error)}"`:``}
                         ${e.errorTitle?`errorTitle="${D(e.errorTitle)}"`:``}>
           ${e.formula1?`<formula1>${D(e.formula1)}</formula1>`:``}
           ${e.formula2?`<formula2>${D(e.formula2)}</formula2>`:``}
         </dataValidation>`)}
     </dataValidations>`:``}

   ${u.length?`
     <hyperlinks>
       ${k(u,e=>`
         <hyperlink ref="${e.ref}" r:id="${e.rId}"/>`)}
     </hyperlinks>`:``}

   <pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3" />
   ${h?`<drawing r:id="${h}"/>`:``}
   ${m?`<legacyDrawing r:id="${m}"/>`:``}
</worksheet>`,ie=({count:e})=>`${A}
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  ${O(e,e=>`
    <Relationship Id="rId${e+1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${e+1}.xml" />`)}
  <Relationship Id="rId${e+1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml" />
  <Relationship Id="rId${e+2}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml" />
</Relationships>`,ae=({hyperlinks:e,comments:t,sheetIndex:n,drawings:r})=>`${A}
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  ${k(e,e=>`
    <Relationship Id="${e.rId}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="${D(e.target)}" TargetMode="External" />`)}
  ${t.length?`
    <Relationship Id="comment${n}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments${n}.xml"/>
    <Relationship Id="vml${n}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/vmlDrawing" Target="../drawings/vmlDrawing${n}.vml"/>`:``}
  ${r.length?`
    <Relationship Id="drw${n}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing${n}.xml"/>`:``}
</Relationships>`,oe=({comments:e})=>`${A}
<comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <authors>
    <author></author>
  </authors>
  <commentList>
    ${k(e,e=>`
      <comment ref="${e.ref}" authorId="0">
        <text>
          <r>
            <rPr>
              <sz val="8"/>
              <color indexed="81"/>
              <rFont val="Tahoma"/>
              <charset val="1"/>
            </rPr>
            <t>${D(e.text)}</t>
          </r>
        </text>
      </comment>`)}
  </commentList>
</comments>`,se=({comments:e})=>`\
<xml xmlns:v="urn:schemas-microsoft-com:vml"
     xmlns:o="urn:schemas-microsoft-com:office:office"
     xmlns:x="urn:schemas-microsoft-com:office:excel">
  <v:shapetype coordsize="21600,21600" id="_x0000_t202" path="m,l,21600r21600,l21600,xe">
    <v:stroke joinstyle="miter"/>
    <v:path gradientshapeok="t" o:connecttype="rect"/>
  </v:shapetype>
  ${k(e,e=>`
    <v:shape type="#_x0000_t202" style="visibility: hidden" fillcolor="#ffffe1" o:insetmode="auto">
      <v:shadow on="t" color="black" obscured="t"/>
      <x:ClientData ObjectType="Note">
        <x:MoveWithCells/>
        <x:SizeWithCells/>
        <x:Anchor>${e.anchor}</x:Anchor>
        <x:AutoFill>False</x:AutoFill>
        <x:Row>${e.row}</x:Row>
        <x:Column>${e.col}</x:Column>
      </x:ClientData>
    </v:shape>`)}
</xml>`,ce=e=>`${A}
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"
          xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  ${k(e,(e,t)=>`
    <xdr:oneCellAnchor editAs="oneCell">
      <xdr:from>
        <xdr:col>${e.col}</xdr:col>
        <xdr:colOff>${e.colOffset}</xdr:colOff>
        <xdr:row>${e.row}</xdr:row>
        <xdr:rowOff>${e.rowOffset}</xdr:rowOff>
      </xdr:from>
      <xdr:ext cx="${e.width}" cy="${e.height}" />
      <xdr:pic>
        <xdr:nvPicPr>
          <xdr:cNvPr id="${t+1}" name="Picture ${t+1}"/>
          <xdr:cNvPicPr/>
        </xdr:nvPicPr>
        <xdr:blipFill>
          <a:blip r:embed="${e.imageId}"/>
          <a:stretch>
            <a:fillRect/>
          </a:stretch>
        </xdr:blipFill>
        <xdr:spPr>
          <a:prstGeom prst="rect">
            <a:avLst/>
          </a:prstGeom>
        </xdr:spPr>
      </xdr:pic>
      <xdr:clientData/>
    </xdr:oneCellAnchor>`)}
</xdr:wsDr>`,le=e=>`${A}
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  ${k(e,e=>`
    <Relationship Id="${e.rId}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="${e.target}"/>`)}
</Relationships>`,ue=({count:e,uniqueCount:t,indexes:n})=>`${A}
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="${e}" uniqueCount="${t}">
  ${k(Object.keys(n),e=>`
    <si><t xml:space="preserve">${D(e.substring(1))}</t></si>`)}
</sst>`,de=({formats:e,fonts:t,fills:n,borders:r,styles:i})=>`${A}
<styleSheet
    xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
    xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
    mc:Ignorable="x14ac"
    xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac">
  <numFmts count="${e.length}">
  ${k(e,(e,t)=>`
    <numFmt formatCode="${D(e.format)}" numFmtId="${165+t}" />`)}
  </numFmts>
  <fonts count="${t.length+1}" x14ac:knownFonts="1">
    <font>
       <sz val="11" />
       <color theme="1" />
       <name val="Calibri" />
       <family val="2" />
       <scheme val="minor" />
    </font>
    ${k(t,e=>`
    <font>
      ${e.bold?`<b/>`:``}
      ${e.italic?`<i/>`:``}
      ${e.underline?`<u/>`:``}
      <sz val="${e.fontSize||11}" />
      ${e.color?`<color rgb="${D(e.color)}" />`:`<color theme="1" />`}
      ${e.fontFamily?`
        <name val="${D(e.fontFamily)}" />
        <family val="2" />
      `:`
        <name val="Calibri" />
        <family val="2" />
        <scheme val="minor" />
      `}
    </font>`)}
  </fonts>
  <fills count="${n.length+2}">
      <fill><patternFill patternType="none"/></fill>
      <fill><patternFill patternType="gray125"/></fill>
    ${k(n,e=>`
      ${e.background?`
        <fill>
          <patternFill patternType="solid">
              <fgColor rgb="${D(e.background)}"/>
          </patternFill>
        </fill>
      `:``}`)}
  </fills>
  <borders count="${r.length+1}">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    ${k(r,ve)}
  </borders>
  <cellStyleXfs count="1">
    <xf borderId="0" fillId="0" fontId="0" />
  </cellStyleXfs>
  <cellXfs count="${i.length+1}">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" />
    ${k(i,e=>`
      <xf xfId="0"
          ${e.fontId?`fontId="${e.fontId}" applyFont="1"`:``}
          ${e.fillId?`fillId="${e.fillId}" applyFill="1"`:``}
          ${e.numFmtId?`numFmtId="${e.numFmtId}" applyNumberFormat="1"`:``}
          ${e.textAlign||e.verticalAlign||e.wrap?`applyAlignment="1"`:``}
          ${e.borderId?`borderId="${e.borderId}" applyBorder="1"`:``}
          ${e.disabled==null?``:`applyProtection="1"`}>
        ${e.textAlign||e.verticalAlign||e.wrap?`
        <alignment
          ${e.textAlign?`horizontal="${D(e.textAlign)}"`:``}
          ${e.verticalAlign?`vertical="${D(e.verticalAlign)}"`:``}
          ${e.indent?`indent="${D(e.indent)}"`:``}
          ${e.wrap?`wrapText="1"`:``} />
        `:``}
        ${e.disabled==null?``:`
        <protection locked="${e.disabled?1:0}" />
        `}
      </xf>
    `)}
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
  <dxfs count="0" />
  <tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleMedium9" />
</styleSheet>`;function fe(e){return typeof e==`string`?`<f>${D(e)}</f>`:`<f t="array" ref="${e.ref}">${D(e.src)}</f>`}function P(e){let t=Math.floor(e/26)-1;return(t>=0?P(t):``)+String.fromCharCode(65+e%26)}function F(e,t){return P(t)+(e+1)}function I(e,t){return`$`+P(t)+`$`+(e+1)}function L(e){return(e.frozenRows||(e.freezePane||{}).rowSplit||1)-1}function R(e){return e/7-18/256}function z(e){return e*.75}function B(e){return String(e).replace(/[\x00-\x09\x0B\x0C\x0E-\x1F]/g,``).replace(/\r?\n/g,`\r
`)}var V=class{constructor(e,t,n,r){this.options=e,this._strings=t,this._styles=n,this._borders=r,this._validations={},this._comments=[],this._drawings=e.drawings||[],this._hyperlinks=(this.options.hyperlinks||[]).map((e,t)=>Object.assign({},e,{rId:`link${t}`}))}relsToXML(){let e=this._hyperlinks,t=this._comments,n=this._drawings;if(e.length||t.length||n.length)return ae({hyperlinks:e,comments:t,sheetIndex:this.options.sheetIndex,drawings:n})}toXML(e){let t=this.options.mergedCells||[],n=ye(this.options.rows||[],t);this._readCells(n);let r=this.options.filter,i;r&&typeof r.from==`number`&&typeof r.to==`number`?r={from:F(L(this.options),r.from),to:F(L(this.options),r.to)}:r&&r.ref&&r.columns&&(i=r,r=null);let a=[];for(let e in this._validations)Object.prototype.hasOwnProperty.call(this._validations,e)&&a.push(this._validations[e]);let o=null,s=this.options.defaultCellStyle;this._hasDisabledCells&&(s=s?Object.assign({disabled:!1},s):{disabled:!1}),s&&(o=this._lookupStyle(s));let c=this.options.freezePane||{},l=this.options.defaults||{},u=this.options.rows?this._getLastRow():1,d=this.options.rows?this._getLastCol():1;return re({frozenColumns:this.options.frozenColumns||c.colSplit,frozenRows:this.options.frozenRows||c.rowSplit,columns:this.options.columns,defaults:l,data:n,index:e,mergeCells:t,autoFilter:r,filter:i,showGridLines:this.options.showGridLines,hyperlinks:this._hyperlinks,validations:a,defaultCellStyleId:o,rtl:this.options.rtl===void 0?l.rtl:this.options.rtl,legacyDrawing:this._comments.length?`vml${this.options.sheetIndex}`:null,drawing:this._drawings.length?`drw${this.options.sheetIndex}`:null,lastRow:u,lastCol:d,hasDisabledCells:this._hasDisabledCells})}commentsXML(){if(this._comments.length)return oe({comments:this._comments})}drawingsXML(e){if(this._drawings.length){let t={};return{main:ce(this._drawings.map(n=>{let r=J(n.topLeftCell),i=t[n.image];return i||=t[n.image]={rId:`img${n.image}`,target:e[n.image].target},{col:r.col,colOffset:Y(n.offsetX),row:r.row,rowOffset:Y(n.offsetY),width:Y(n.width),height:Y(n.height),imageId:i.rId}})),rels:le(t)}}}legacyDrawing(){if(this._comments.length)return se({comments:this._comments})}_lookupString(e){let t=`$`+e,n=this._strings.indexes[t],r;return n===void 0?(r=this._strings.indexes[t]=this._strings.uniqueCount,this._strings.uniqueCount++):r=n,this._strings.count++,r}_lookupStyle(e){let t=JSON.stringify(e);if(t===`{}`)return 0;let n=T(t,this._styles);return n<0&&(n=this._styles.push(t)-1),n+1}_lookupBorder(e){let t=JSON.stringify(e);if(t===`{}`)return;let n=T(t,this._borders);return n<0&&(n=this._borders.push(t)-1),n+1}_readCells(e){for(let t=0;t<e.length;t++){let n=e[t],r=n.cells;n.data=[];for(let e=0;e<r.length;e++){let t=this._cell(r[e],n.index,e);t&&n.data.push(t)}}}_cell(e,t,n){if(!e||e===K)return null;let r=e.value,i={};e.borderLeft&&(i.left=e.borderLeft),e.borderRight&&(i.right=e.borderRight),e.borderTop&&(i.top=e.borderTop),e.borderBottom&&(i.bottom=e.borderBottom),e.dBorders&&(i.diagonal=e.dBorders),i=this._lookupBorder(i);let a=this.options.defaultCellStyle||{},o={borderId:i};(e=>{e(`color`),e(`background`),e(`bold`),e(`italic`),e(`underline`),e(`fontFamily`)||e(`fontName`,`fontFamily`),e(`fontSize`),e(`format`),e(`textAlign`)||e(`hAlign`,`textAlign`),e(`verticalAlign`)||e(`vAlign`,`verticalAlign`),e(`wrap`),e(`indent`),e(`disabled`)||e(`enable`)&&(o.disabled=!o.enable,delete o.enable),o.disabled&&(this._hasDisabledCells=!0)})((t,n)=>{let r=e[t];if(r===void 0&&(r=a[t]),r!==void 0)return o[n||t]=r,!0});let s=(this.options.columns||[])[n],c=typeof r;if(s&&s.autoWidth&&(!e.colSpan||e.colSpan===1)){let t=r;c===`number`&&(t=p.toString(r,e.format)),s.width=Math.max(s.width||0,String(t).length)}c===`string`?(r=B(r),r=this._lookupString(r),c=`s`):c===`number`?c=`n`:c===`boolean`?(c=`b`,r=Number(r)):r&&r.getTime?(c=null,r=y(r),o.format||=`mm-dd-yy`):(c=null,r=null),o=this._lookupStyle(o);let l=F(t,n);if(e.validation&&this._addValidation(e.validation,l),e.comment){let r=[n+1,15,t,10,n+3,15,t+3,4];this._comments.push({ref:l,text:e.comment,row:t,col:n,anchor:r.join(`, `)})}return{value:r,formula:e.formula,type:c,style:o,ref:l}}_addValidation(e,t){let n={showErrorMessage:e.type===`reject`?1:0,formula1:e.from,formula2:e.to,type:me[e.dataType]||e.dataType,operator:pe[e.comparerType]||e.comparerType,allowBlank:e.allowNulls?1:0,showDropDown:e.showButton?0:1,error:e.messageTemplate,errorTitle:e.titleTemplate},r=JSON.stringify(n);this._validations[r]||(this._validations[r]=n,n.sqref=[]),this._validations[r].sqref.push(t)}_getLastRow(){return H(this.options.rows)}_getLastCol(){let e=0;return this.options.rows.forEach(function(t){t.cells&&(e=Math.max(e,H(t.cells)))}),e}};function H(e){let t=e.length;return e.forEach(function(e){e.index&&e.index>=t&&(t=e.index+1)}),t}const pe={greaterThanOrEqualTo:`greaterThanOrEqual`,lessThanOrEqualTo:`lessThanOrEqual`},me={number:`decimal`},U={General:0,0:1,"0.00":2,"#,##0":3,"#,##0.00":4,"0%":9,"0.00%":10,"0.00E+00":11,"# ?/?":12,"# ??/??":13,"mm-dd-yy":14,"d-mmm-yy":15,"d-mmm":16,"mmm-yy":17,"h:mm AM/PM":18,"h:mm:ss AM/PM":19,"h:mm":20,"h:mm:ss":21,"m/d/yy h:mm":22,"#,##0 ;(#,##0)":37,"#,##0 ;[Red](#,##0)":38,"#,##0.00;(#,##0.00)":39,"#,##0.00;[Red](#,##0.00)":40,"mm:ss":45,"[h]:mm:ss":46,"mmss.0":47,"##0.0E+0":48,"@":49,"[$-404]e/m/d":27,"m/d/yy":30,t0:59,"t0.00":60,"t#,##0":61,"t#,##0.00":62,"t0%":67,"t0.00%":68,"t# ?/?":69,"t# ??/??":70};function he(e){function t(e){let t=parseInt(e,10).toString(16);return t.length<2?`0`+t:t}let n=/^rgba?\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([0-9.]+)\s*)?\)/i.exec(e.trim());return n?`#`+t((n[4]?parseFloat(n[4]):1)*255|0)+t(n[1])+t(n[2])+t(n[3]):e}function W(e){let t=he(e);return t.length<6&&(t=t.replace(/(\w)/g,function(e,t){return t+t})),t=t.substring(1).toUpperCase(),t.length<8&&(t=`FF`+t),t}var ge=class{constructor(e){this.options=e||{},this._strings={indexes:{},count:0,uniqueCount:0},this._styles=[],this._borders=[],this._images=this.options.images,this._imgId=0,this._sheets=s(this.options.sheets||[],(e,t)=>(e.defaults=this.options,e.sheetIndex=t+1,new V(e,this._strings,this._styles,this._borders)))}imageFilename(e){let t=++this._imgId;switch(e){case`image/jpg`:case`image/jpeg`:return`image${t}.jpg`;case`image/png`:return`image${t}.png`;case`image/gif`:return`image${t}.gif`;default:return`image${t}.bin`}}toZIP(){let e=m(),t=e.folder(`docProps`);t.file(`core.xml`,M({creator:this.options.creator||`Kendo UI`,lastModifiedBy:this.options.creator||`Kendo UI`,created:this.options.date||new Date().toJSON(),modified:this.options.date||new Date().toJSON()}));let n=this._sheets.length;t.file(`app.xml`,N({sheets:this._sheets})),e.folder(`_rels`).file(`.rels`,j);let r=e.folder(`xl`);if(r.folder(`_rels`).file(`workbook.xml.rels`,ie({count:n})),this._images){let e=r.folder(`media`);Object.keys(this._images).forEach(t=>{let n=this._images[t],r=this.imageFilename(n.type);e.file(r,n.data),n.target=`../media/${r}`})}let i={};r.file(`workbook.xml`,ne({sheets:this._sheets,filterNames:s(this._sheets,function(e,t){let n=e.options,r=n.name||n.title||`Sheet`+(t+1);i[r.toLowerCase()]=t;let a=n.filter;if(a){if(a.ref){let e=a.ref.split(`:`),n=J(e[0]),i=J(e[1]);return{localSheetId:t,name:r,from:I(n.row,n.col),to:I(i.row,i.col)}}else if(a.from!==void 0&&a.to!==void 0)return{localSheetId:t,name:r,from:I(L(n),a.from),to:I(L(n),a.to)}}}),userNames:s(this.options.names||[],function(e){return{name:e.localName,localSheetId:e.sheet?i[e.sheet.toLowerCase()]:null,value:e.value,hidden:e.hidden}})}));let a=r.folder(`worksheets`),o=r.folder(`drawings`),c=o.folder(`_rels`),l=a.folder(`_rels`),u=[],d=[],f=!1;for(let e=0;e<n;e++){let t=this._sheets[e],n=`sheet${e+1}.xml`,i=t.toXML(e),s=t.relsToXML(),p=t.commentsXML(),m=t.legacyDrawing(),h=t.drawingsXML(this._images);if(t._hasDisabledCells&&(f=!0),s&&l.file(n+`.rels`,s),p){let e=`comments${t.options.sheetIndex}.xml`;r.file(e,p),u.push(e)}if(m&&o.file(`vmlDrawing${t.options.sheetIndex}.vml`,m),h){let e=`drawing${t.options.sheetIndex}.xml`;o.file(e,h.main),c.file(`${e}.rels`,h.rels),d.push(e)}a.file(n,i)}let p=s(this._borders,E),h=s(this._styles,E),g=function(e){return e.underline||e.bold||e.italic||e.color||e.fontFamily||e.fontSize},_=function(e){let t=Number(e),n;return t&&(n=t*3/4),n},v=s(h,function(e){if(e.fontSize&&=_(e.fontSize),e.color&&=W(e.color),g(e))return e}),y=s(h,function(e){if(e.format&&U[e.format]===void 0)return e}),b=s(h,function(e){if(e.background)return e.background=W(e.background),e});return r.file(`styles.xml`,de({fonts:v,fills:b,formats:y,borders:p,styles:s(h,function(e){let t={};return g(e)&&(t.fontId=T(e,v)+1),e.background&&(t.fillId=T(e,b)+2),t.textAlign=e.textAlign,t.indent=e.indent,t.verticalAlign=e.verticalAlign,t.wrap=e.wrap,t.borderId=e.borderId,e.format&&(U[e.format]===void 0?t.numFmtId=165+T(e,y):t.numFmtId=U[e.format]),f&&(t.disabled=!!e.disabled),t})})),r.file(`sharedStrings.xml`,ue(this._strings)),e.file(`[Content_Types].xml`,te({sheetCount:n,commentFiles:u,drawingFiles:d})),e}toDataURL(){let e=this.toZIP();return e.generateAsync?e.generateAsync(S).then(w):w(e.generate(S))}toBlob(){let e=this.toZIP();return e.generateAsync?e.generateAsync(ee):new Blob([e.generate(C)],{type:b})}};function _e(e){let t=`thin`;return e===2?t=`medium`:e===3&&(t=`thick`),t}function G(e,t){let n=``;return t&&(n+=`<`+e+` style="`+_e(t.size)+`">`,t.color&&(n+=`<color rgb="`+W(t.color)+`"/>`),n+=`</`+e+`>`),n}function ve(e){let t=e.diagonal?e.diagonal.type:0;return`<border ${t&2?`diagonalUp="true"`:``} ${t&1?`diagonalDown="true"`:``}>
      ${G(`left`,e.left)}
      ${G(`right`,e.right)}
      ${G(`top`,e.top)}
      ${G(`bottom`,e.bottom)}
      ${G(`diagonal`,e.diagonal)}
    </border>`}const K={};function ye(e,t){let n=[],r=[];be(e,function(e,t){let i={_source:e,index:t,height:e.height,level:e.level,cells:[]};n.push(i),r[t]=i});let i=q(n).slice(0),a={rowData:n,rowsByIndex:r,mergedCells:t};for(let e=0;e<i.length;e++)Ce(i[e],a),delete i[e]._source;return q(n)}function be(e,t){for(let n=0;n<e.length;n++){let r=e[n];if(!r)continue;let i=r.index;typeof i!=`number`&&(i=n),t(r,i)}}function q(e){return e.sort(function(e,t){return e.index-t.index})}function xe(e,t){e.indexOf(t)<0&&e.push(t)}function Se(e,t){for(let n=0;n<e.length;++n){let r=e[n].split(`:`),i=r[0];if(i===t){let e=r[1];return i=J(i),e=J(e),{rowSpan:e.row-i.row+1,colSpan:e.col-i.col+1}}}}function J(e){function t(e){let t=e.toUpperCase(),n=0;for(let e=0;e<t.length;++e)n=n*26+t.charCodeAt(e)-64;return n-1}function n(e){return parseInt(e,10)-1}let r=/^([a-z]+)(\d+)$/i.exec(e);return{row:n(r[2]),col:t(r[1])}}function Y(e){return Math.round(e*9525)}function Ce(e,t){let n=e._source,r=e.index,i=n.cells,a=e.cells;if(i)for(let e=0;e<i.length;e++){let n=i[e]||K,o=n.rowSpan||1,s=n.colSpan||1,c=X(a,n),l=F(r,c);if(o===1&&s===1){let e=Se(t.mergedCells,l);e&&(s=e.colSpan,o=e.rowSpan)}if(Q(n,a,c,s),(o>1||s>1)&&xe(t.mergedCells,l+`:`+F(r+o-1,c+s-1)),o>1)for(let e=r+1;e<r+o;e++){let r=t.rowsByIndex[e];r||(r=t.rowsByIndex[e]={index:e,cells:[]},t.rowData.push(r)),Q(n,r.cells,c-1,s+1)}}}function X(e,t){let n;return typeof t.index==`number`?(n=t.index,Z(e,t,t.index)):n=we(e,t),n}function Z(e,t,n){e[n]=t}function we(e,t){let n=e.length;for(let r=0;r<e.length+1;r++)if(!e[r]){e[r]=t,n=r;break}return n}function Q(e,t,n,r){for(let i=1;i<r;i++)Z(t,{borderTop:e.borderTop,borderRight:e.borderRight,borderBottom:e.borderBottom,borderLeft:e.borderLeft},n+i)}const Te=({ref:e,columns:t,generators:n})=>`
<autoFilter ref="${e}">
  ${k(t,e=>`
    <filterColumn colId="${e.index}">
      ${n[e.filter](e)}
    </filterColumn>
  `)}
</autoFilter>`,Ee=({logic:e,criteria:t})=>`
<customFilters ${e===`and`?`and="1"`:``}>
${k(t,e=>{let t=$.customOperator(e),n=$.customValue(e);return`<customFilter ${t?`operator="${t}"`:``} val="${n}"/>`})}
</customFilters>`,De=({type:e})=>`<dynamicFilter type="${$.dynamicFilterType(e)}" />`,Oe=({type:e,value:t})=>`<top10 percent="${/percent$/i.test(e)?1:0}"
       top="${/^top/i.test(e)?1:0}"
       val="${t}" />`,ke=({blanks:e,values:t})=>`<filters ${e?`blank="1"`:``}>
    ${k(t,e=>`
      <filter val="${e}" />`)}
  </filters>`;function $(e){return Te({ref:e.ref,columns:e.columns,generators:{custom:Ee,dynamic:De,top:Oe,value:ke}})}$.customOperator=function(e){return{eq:`equal`,gt:`greaterThan`,gte:`greaterThanOrEqual`,lt:`lessThan`,lte:`lessThanOrEqual`,ne:`notEqual`,doesnotstartwith:`notEqual`,doesnotendwith:`notEqual`,doesnotcontain:`notEqual`,doesnotmatch:`notEqual`}[e.operator.toLowerCase()]};function Ae(e){return/^\'/.test(e)||/^[a-z_][a-z0-9_]*$/i.test(e)?e:`'`+e.replace(/\x27/g,`\\'`)+`'`}$.customValue=function(e){function t(e){return e.replace(/([*?])/g,`~$1`)}switch(e.operator.toLowerCase()){case`startswith`:case`doesnotstartwith`:return t(e.value)+`*`;case`endswith`:case`doesnotendwith`:return`*`+t(e.value);case`contains`:case`doesnotcontain`:return`*`+t(e.value)+`*`;default:return e.value}},$.dynamicFilterType=function(e){return{quarter1:`Q1`,quarter2:`Q2`,quarter3:`Q3`,quarter4:`Q4`,january:`M1`,february:`M2`,march:`M3`,april:`M4`,may:`M5`,june:`M6`,july:`M7`,august:`M8`,september:`M9`,october:`M10`,november:`M11`,december:`M12`}[e.toLowerCase()]||e};const je={id:`ooxml`,name:`XLSX generation`,category:`framework`,advanced:!0,mixin:!0,depends:[`core`]};(function(e){p.register({toString:kendo.toString});let t=kendo.ConvertClass(ge);var n=t.prototype.toDataURL;Object.assign(t.prototype,{toDataURL:function(){var e=n.call(this);if(typeof e!=`string`)throw Error(`The toDataURL method can be used only with jsZip 2. Either include jsZip 2 or use the toDataURLAsync method.`);return e},toDataURLAsync:function(){var t=e.Deferred(),r=n.call(this);return typeof r==`string`?r=t.resolve(r):r&&r.then&&r.then(function(e){t.resolve(e)},function(){t.reject()}),t.promise()}}),window.kendo.ooxml=window.kendo.ooxml||{},window.kendo.ooxml.IntlService=p,window.kendo.ooxml.Workbook=t,window.kendo.ooxml.Worksheet=kendo.ConvertClass(V),window.kendo.ooxml.createZip=function(){if(typeof JSZip>`u`)throw Error(`JSZip not found. Check http://docs.telerik.com/kendo-ui/framework/excel/introduction#requirements for more details.`);return new JSZip}})(window.kendo.jQuery);export{d as n,n as r,je as t};
//# sourceMappingURL=kendo.ooxml-BsuYbbQm.js.map