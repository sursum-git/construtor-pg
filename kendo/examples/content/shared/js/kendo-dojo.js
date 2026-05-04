 (function($, window) {
    var dojo = {
        postSnippet: function (snippet, baseUrl) {
            snippet = dojo.fixCDNReferences(snippet);
            snippet = dojo.addBaseRedirectTag(snippet, baseUrl);
            snippet = dojo.addConsoleScript(snippet);
            snippet = dojo.replaceCommon(snippet, window.selectedThemeCommon);
            snippet = dojo.replaceTheme(snippet, window.selectedTheme);
            snippet = window.btoa(encodeURIComponent(snippet));

            var form = $('<form method="post" action="' + dojo.configuration.url + '" target="_blank" />').hide().appendTo(document.body);
            $("<input name='snippet'>").val(snippet).appendTo(form);

            if ($("#mobile-application-container").length) {
                $("<input name='mode'>").val("ios7").appendTo(form);
            }

            form.submit();
        },
        replaceCommon: function(code, common) {
            if (common) {
                if (/-empty/.test(common)) {
                    code = code.replace(/&#10;\s*<link[^>]*common\.min\.css[^>]*>/, "");
                } else {
                    code = code.replace(/common\.min\.css/, common + ".min.css");
                }
            }

            return code;
        },
        replaceTheme: function(code, theme) {
            if (theme) {
                const themeType = theme.split('-')[0];
                const hrefRegex = /https:\/\/kendo\.cdn\.telerik\.com\/themes\/([^\/]+)\/([^\/]+)\/([^.]+)\.css/;
                const match = code.match(hrefRegex);

                if (match) {
                    const oldUrl = match[0];
                    const version = match[1];
                    const newUrl = "https://kendo.cdn.telerik.com/themes/" + version + "/" + themeType + "/" + theme + ".css";
                    return code.replace(oldUrl, newUrl);
                }
            }
        },
        addBaseRedirectTag: function (code, baseUrl) {
            return code.replace(
                '<head>',
                '<head>\n' +
                '    <base href="' + baseUrl + '">\n' +
                '    <style>html { font-size: 14px; font-family: Arial, Helvetica, sans-serif; }</style>'
            );
        },
        addConsoleScript: function (code) {
            if (code.indexOf("kendoConsole") !== -1) {
                var styleReference = '    <link rel="stylesheet" href="../content/shared/styles/examples-offline.css">\n';
                var scriptReference = '    <script src="../content/shared/js/console.js"></script>\n';
                code = code.replace("</head>", styleReference + scriptReference + "</head>");
            }

            return code;
        },
        fixCDNReferences: function (code) {
            return code.replace(/<head>[\s\S]*<\/head>/, function (match) {
                return match
                    .replace(/src="js\//g, "src=\"" + dojo.configuration.cdnRoot + "/js/");
            });
        }
    };

    $.extend(window, {
        dojo: dojo
    });
})(jQuery, window);
