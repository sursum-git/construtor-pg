/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

// this function, along with calls to it, will not exist in compressed builds
var kendo_module = (function(cache, $, base_url){
    /*global console:true */
    function url(file){
        return base_url + file;
    }
    function load_file(file) {
        var addr = url(file);
        // no point to load it if there's a <script> that already asked for it.
        var scripts = $("script").filter(function(){
            return this.src.indexOf(addr) === 0;
        });
        if (scripts.length === 0) {
            $.ajax({ url: addr, async: false, script: true });
        }
    }
    function each(a, f) {
        for (var i = 0; i < a.length; ++i) {
            f(a[i], i);
        }
    }
    return function(comp) {
        if (!cache[comp.id] || !comp.id) {
            if (comp.id) {
                cache[comp.id] = comp;
            }
            var deps = comp.depends ? comp.depends.slice(0) : [];
            if (comp.features) {
                each(comp.features, function(f){
                    if (f.depends) {
                        deps = deps.concat(f.depends);
                    }
                });
            }
            each(deps, function(id){
                if (!cache[id]) {
                    load_file("kendo." + id + ".js");
                }
            });
            if (comp.files) {
                each(comp.files, load_file);
            }
        }
    };
})({}, jQuery,
   // the following figures out the base URL of the running script.
   (function(x){
       return x[x.length - 1].src.replace(/\/+[^\/]*$/, "/");
   })(document.getElementsByTagName("script")));
