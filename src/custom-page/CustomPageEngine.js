(function(global, $) {
  "use strict";

  class CustomPageEngine {
    constructor(options) {
      this.options = options || {};
      this.root = $(this.options.root);
      this.httpClient = this.options.httpClient || new global.CrudHttpClient();
      this.configLoader = new global.CrudConfigLoader();
      this.config = {};
      this.definition = null;
      this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy({}, this.options);
      this.frameElement = null;
    }

    init() {
      global.CrudUtils.beginRenderGate(this.root);
      this.renderLoading();
      return this.loadConfig().then(() => {
        return this.loadDefinition();
      }).then((definition) => {
        this.definition = this.validateDefinition(definition);
        this.render();
        global.CrudUtils.completeRenderGate(this.root);
        return this;
      }).catch((error) => {
        this.renderError(global.CrudUtils.unwrapError(error, "Erro ao carregar programa custom."));
        global.CrudUtils.failRenderGate(this.root);
        throw error;
      });
    }

    destroy() {
      this.frameElement = null;
      if (global.kendo) {
        global.kendo.destroy(this.root);
      }
      this.root.empty();
    }

    loadConfig() {
      return this.configLoader.load({
        configUrl: this.options.configUrl,
        config: this.options.config
      }).then((config) => {
        this.config = config || {};
        this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
        return global.CrudUtils.loadLiteralBundle(this.config.literals || {}, this.httpClient).then(() => this.config);
      });
    }

    loadDefinition() {
      if (this.options.definition) {
        return Promise.resolve(global.CrudUtils.clone(this.options.definition));
      }

      const screenId = String(this.options.screenId || "").trim();
      if (screenId) {
        const request = global.CrudUtils.buildScreenDefinitionRequest(screenId, this.securityPolicy, "");
        return this.httpClient.request(request);
      }

      const definitionUrl = String(this.options.definitionUrl || "").trim();
      if (!definitionUrl) {
        return Promise.reject(global.CrudUtils.makeError("CUSTOM_DEFINITION_SOURCE_MISSING", "Nenhuma definicao custom foi informada."));
      }
      if (this.securityPolicy.definitionSource && this.securityPolicy.definitionSource.allowDefinitionUrl === false) {
        return Promise.reject(global.CrudUtils.makeError("CUSTOM_DEFINITION_URL_DISABLED", "Carregamento por definitionUrl livre desabilitado pela politica de seguranca."));
      }

      return this.httpClient.request({
        url: definitionUrl,
        method: "GET"
      });
    }

    validateDefinition(definition) {
      const source = global.CrudUtils.clone(definition || {});
      const pageType = String(source.pageType || "").trim();
      const custom = source.custom || {};
      const mode = String(custom.mode || "iframe").trim();
      const entryUrl = String(custom.entryUrl || "").trim();

      if (pageType !== "custom") {
        throw global.CrudUtils.makeError("INVALID_CUSTOM_DEFINITION", "A definicao recebida nao e do tipo custom.", {
          pageType: pageType || ""
        });
      }
      if (["iframe", "htmlUrl"].indexOf(mode) === -1) {
        throw global.CrudUtils.makeError("INVALID_CUSTOM_MODE", "Modo custom invalido.", { mode });
      }
      if (!entryUrl) {
        throw global.CrudUtils.makeError("CUSTOM_ENTRY_REQUIRED", "A definicao custom precisa informar custom.entryUrl.");
      }
      if (!global.CrudUtils.isRelativeUrl(entryUrl)) {
        throw global.CrudUtils.makeError("CUSTOM_ENTRY_UNSAFE", "Programas custom aceitam apenas entrypoints relativos do proprio sistema.", {
          entryUrl
        });
      }

      source.custom = Object.assign({}, custom, {
        mode: mode,
        entryUrl: entryUrl,
        frameTitle: String(custom.frameTitle || source.program && source.program.title || "Programa custom").trim()
      });
      source.program = Object.assign({}, source.program || {});
      source.program.title = source.program.title || "Programa custom";
      return source;
    }

    renderLoading() {
      this.root.empty().append(
        $("<section class=\"crud-message crud-message-info\"></section>").text("Carregando programa...")
      );
    }

    renderError(error) {
      const message = error && error.message ? error.message : "Erro ao carregar programa custom.";
      this.root.empty().append(
        $("<section class=\"crud-message crud-message-error\"></section>").text(message)
      );
    }

    render() {
      this.root.empty().addClass("custom-page-shell");
      if (!this.options.hideHeader) {
        this.renderHeader();
      }
      const content = $("<section class=\"custom-page-content\"></section>").appendTo(this.root);
      if (this.definition.custom.mode === "iframe") {
        this.renderIframe(content);
        return;
      }
      this.renderHtml(content);
    }

    renderHeader() {
      const program = this.definition.program || {};
      const header = $("<header class=\"custom-page-header\"></header>").appendTo(this.root);
      const titleArea = $("<div class=\"custom-page-heading\"></div>").appendTo(header);
      $("<p class=\"custom-page-kicker\"></p>").text("Programa manual").appendTo(titleArea);
      $("<h1></h1>").text(program.title || "Programa custom").appendTo(titleArea);
      if (program.subtitle) {
        $("<p class=\"custom-page-subtitle\"></p>").text(program.subtitle).appendTo(titleArea);
      }
      if (program.version) {
        $("<span class=\"custom-page-version\"></span>").text("v" + program.version).appendTo(header);
      }
    }

    renderIframe(container) {
      const frame = $("<iframe class=\"custom-page-frame\"></iframe>")
        .attr("title", this.definition.custom.frameTitle || this.definition.program.title || "Programa custom")
        .attr("src", this.resolveEntryUrl(this.definition.custom.entryUrl))
        .appendTo(container);
      this.frameElement = frame[0];
    }

    renderHtml(container) {
      container.addClass("custom-page-fragment-host");
      this.loadText(this.resolveEntryUrl(this.definition.custom.entryUrl)).then((html) => {
        if (!container.closest("body").length) {
          return;
        }
        const fragment = document.createDocumentFragment();
        this.sanitizeHtml(html).forEach(function(node) {
          fragment.appendChild(node);
        });
        container.empty();
        container[0].appendChild(fragment);
      }).catch((error) => {
        const message = error && error.message ? error.message : "Erro ao carregar conteudo manual.";
        container.empty().append(
          $("<section class=\"crud-message crud-message-error\"></section>").text(message)
        );
      });
    }

    resolveEntryUrl(url) {
      const source = String(url || "").trim();
      if (!source) {
        return source;
      }
      if (this.definition.custom.mode !== "iframe") {
        return source;
      }
      let target = this.forwardCurrentQueryParameters(source);
      if (this.options.hideThemeSwitch) {
        target = this.appendUrlParameter(target, "hideThemeSwitch", "1");
      }
      if (this.options.hideHeader) {
        target = this.appendUrlParameter(target, "hideProgramHeader", "1");
      }
      return target;
    }

    forwardCurrentQueryParameters(url) {
      let params;
      try {
        params = new URLSearchParams(global.location && global.location.search || "");
      } catch (_) {
        return url;
      }
      if (!params) {
        return url;
      }
      const reserved = {
        screenId: true,
        configUrl: true,
        hideThemeSwitch: true,
        hideProgramHeader: true
      };
      let target = url;
      params.forEach((value, key) => {
        if (reserved[key]) {
          return;
        }
        if (String(value || "").trim() === "") {
          return;
        }
        target = this.appendUrlParameter(target, key, value);
      });
      return target;
    }

    appendUrlParameter(url, key, value) {
      try {
        const parsedUrl = new URL(url, global.location && global.location.href ? global.location.href : undefined);
        parsedUrl.searchParams.set(key, value);
        return parsedUrl.href;
      } catch (_) {
        const source = String(url || "");
        const hashIndex = source.indexOf("#");
        const base = hashIndex === -1 ? source : source.slice(0, hashIndex);
        const hash = hashIndex === -1 ? "" : source.slice(hashIndex);
        const separator = base.indexOf("?") === -1 ? "?" : "&";
        return base + separator + encodeURIComponent(key) + "=" + encodeURIComponent(value) + hash;
      }
    }

    loadText(url) {
      return fetch(url, {
        method: "GET",
        headers: {
          "Accept": "text/html"
        },
        credentials: "include"
      }).then(function(response) {
        if (!response.ok) {
          throw new Error("Falha ao carregar " + url + ".");
        }
        return response.text();
      }).catch(function(error) {
        if (!global.location || global.location.protocol !== "file:") {
          throw error;
        }
        return new Promise(function(resolve, reject) {
          const xhr = new XMLHttpRequest();
          xhr.open("GET", url, true);
          xhr.onload = function() {
            if (xhr.status === 0 || (xhr.status >= 200 && xhr.status < 300)) {
              resolve(xhr.responseText);
              return;
            }
            reject(error);
          };
          xhr.onerror = function() {
            reject(error);
          };
          xhr.send();
        });
      });
    }

    sanitizeHtml(html) {
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(html || ""), "text/html");
      const blockedTags = ["SCRIPT", "STYLE", "IFRAME", "OBJECT", "EMBED", "BASE", "META", "LINK"];
      Array.prototype.slice.call(doc.body.querySelectorAll("*")).forEach(function(element) {
        if (blockedTags.indexOf(element.tagName) !== -1) {
          element.remove();
          return;
        }
        Array.prototype.slice.call(element.attributes).forEach(function(attribute) {
          const name = attribute.name.toLowerCase();
          const value = String(attribute.value || "").trim();
          if (name.indexOf("on") === 0 || name === "style" || /^javascript:/i.test(value)) {
            element.removeAttribute(attribute.name);
            return;
          }
          if ((name === "href" || name === "src") && value && value.charAt(0) !== "#" && !global.CrudUtils.isAllowedDocumentUrl(value)) {
            element.removeAttribute(attribute.name);
          }
        });
      });
      return Array.prototype.slice.call(doc.body.childNodes).map(function(node) {
        return document.importNode(node, true);
      });
    }
  }

  global.CustomPageEngine = CustomPageEngine;
})(window, window.jQuery);
