(function(global, $) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (global.kendo) {
      kendo.culture("pt-BR");
    }
    const literalClient = global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: false }) : null;
    const t = function(key, fallback, params) {
      return global.LoginLiterals && typeof global.LoginLiterals.t === "function"
        ? global.LoginLiterals.t(key, fallback, params)
        : fallback;
    };
    if (global.LoginLiterals && typeof global.LoginLiterals.init === "function") {
      global.LoginLiterals.init(literalClient);
    }

    const notification = $("#login-notification").kendoNotification({
      position: {
        pinned: true,
        top: 78,
        right: 18
      },
      autoHideAfter: 4200,
      stacking: "down",
      templates: [{
        type: "login",
        template: "<div class=\"login-notification\">#= message #</div>"
      }]
    }).data("kendoNotification");

    $("#login-user").kendoTextBox({ placeholder: t("login.placeholder.username_short", "usuario") });
    const rememberedUsername = readLocalValue("crudEngine.lastUsername");
    if (rememberedUsername) {
      $("#login-user").val(rememberedUsername);
    }
    const passwordInput = $("#login-password");
    passwordInput.kendoTextBox({ placeholder: t("login.placeholder.password", "Senha") });
    const passwordToggle = $("#login-toggle-password").kendoButton({
      icon: "eye",
      fillMode: "flat"
    }).data("kendoButton");
    if ($.fn.kendoCheckBox) {
      $("#login-remember").kendoCheckBox();
    }
    if (readLocalValue("crudEngine.rememberToken")) {
      $("#login-remember").prop("checked", true);
    }
    const submitButton = $("#login-submit").kendoButton({
      icon: "login",
      themeColor: "primary"
    }).data("kendoButton");
    const externalBox = $("#login-external");
    const externalActions = $("#login-external-actions");
    let subscriberWindow = null;
    let passwordResetWindow = null;
    let adminAreaWindow = null;

    tryRememberLogin();
    loadProviders();
    openResetFromQuery();

    $("#login-form").on("submit", function(event) {
      event.preventDefault();
      const payload = {
        username: String($("#login-user").val() || "").trim(),
        password: String($("#login-password").val() || ""),
        remember: !!$("#login-remember").is(":checked"),
        rememberToken: readLocalValue("crudEngine.rememberToken") || ""
      };

      if (!payload.username || !payload.password) {
        show(t("login.message.credentials_required", "Informe usuario e senha para continuar."));
        return;
      }

      setBusy(true);
      fetchJson("/api/auth/login", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        credentials: "include",
        body: JSON.stringify(payload)
      }).then(function(response) {
        if (response && response.requiresSubscriberSelection) {
          openSubscriberSelection(response);
          return;
        }
        return finishLogin(response, { keepRememberToken: payload.remember });
      }).catch(function(error) {
        show(errorMessage(error, "Nao foi possivel autenticar."));
      }).finally(function() {
        setBusy(false);
      });
    });

    $("#login-forgot").on("click", function() {
      openPasswordResetWindow("");
    });
    $("#login-clear-session").on("click", function() {
      clearLocalSession(false);
      $("#login-user").val("");
      $("#login-password").val("");
      $("#login-remember").prop("checked", false);
      show("Sessao local removida.");
    });

    $("#login-toggle-password").on("click", function() {
      const isVisible = passwordInput.attr("type") === "text";
      const nextVisible = !isVisible;
      const label = nextVisible ? "Ocultar senha" : "Exibir senha";
      passwordInput.attr("type", nextVisible ? "text" : "password");
      $(this).attr({
        "aria-label": label,
        "aria-pressed": String(nextVisible),
        title: label
      });
      if (passwordToggle && typeof passwordToggle.setOptions === "function") {
        passwordToggle.setOptions({
          icon: nextVisible ? "eye-slash" : "eye"
        });
      }
      passwordInput.trigger("focus");
    });

    function loadProviders() {
      fetchJson("/api/auth/providers", {
        method: "GET",
        headers: { "Accept": "application/json" },
        credentials: "include"
      }).then(function(response) {
        const providers = Array.isArray(response.externalProviders)
          ? response.externalProviders
          : Array.isArray(response.providers) ? response.providers.filter(function(provider) { return provider.redirect; }) : [];
        renderExternalProviders(providers);
      }).catch(function() {
        renderExternalProviders([]);
      });
    }

    function renderExternalProviders(providers) {
      externalActions.empty();
      if (!providers.length) {
        externalBox.prop("hidden", true);
        return;
      }
      providers.forEach(function(provider) {
        const buttonElement = $("<button type=\"button\"></button>")
          .text(provider.name || provider.code)
          .appendTo(externalActions);
        buttonElement.kendoButton({
          icon: provider.type === "oidc" ? "login" : "hyperlink-open",
          themeColor: "base"
        });
        buttonElement.on("click", function() {
          startOAuth(provider.code);
        });
      });
      externalBox.prop("hidden", false);
    }

    function startOAuth(providerCode) {
      setBusy(true);
      fetchJson("/api/auth/oauth/" + encodeURIComponent(providerCode) + "/start", {
        method: "GET",
        headers: { "Accept": "application/json" },
        credentials: "include"
      }).then(function(response) {
        if (!response.authorizationUrl) {
          show(t("login.message.oauth_url_missing", "Provedor OAuth nao retornou URL de autorizacao."));
          return;
        }
        global.location.href = response.authorizationUrl;
      }).catch(function(error) {
        show(errorMessage(error, "Nao foi possivel iniciar o login externo."));
      }).finally(function() {
        setBusy(false);
      });
    }

    function tryRememberLogin() {
      if (skipRememberLogin()) {
        return;
      }
      const expiry = readLocalValue("crudEngine.rememberTokenExpiresAt");
      if (isRememberTokenExpired(expiry)) {
        clearRememberTokenState();
        return;
      }
      const rememberToken = readLocalValue("crudEngine.rememberToken");
      if (!rememberToken) {
        return;
      }

      setBusy(true);
      fetchJson("/api/auth/remember", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        credentials: "include",
        body: JSON.stringify({ rememberToken })
      }).then(function(response) {
        return finishLogin(response, { keepRememberToken: true });
      }).catch(function() {
        removeLocalValue("crudEngine.rememberToken");
      }).finally(function() {
        setBusy(false);
      });
    }

    function skipRememberLogin() {
      const params = new URLSearchParams(global.location.search || "");
      const flag = String(params.get("noRemember") || params.get("forceLogin") || params.get("logout") || "").toLowerCase();
      return flag === "1" || flag === "true" || flag === "yes" || Boolean(params.get("resetToken"));
    }

    function openSubscriberSelection(response) {
      const subscribers = Array.isArray(response.subscribers) ? response.subscribers : [];
      if (!subscribers.length) {
        show(t("login.message.no_subscribers", "Nenhum assinante disponivel para este usuario."));
        return;
      }

      const wrapper = $("<div class=\"login-dialog-form\"></div>");
      $("<p></p>").text(t("login.message.select_subscriber", "Selecione o assinante para continuar.")).appendTo(wrapper);
      const field = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span></span>").text(t("login.label.subscriber", "Assinante")).appendTo(field);
      const input = $("<input type=\"text\">").appendTo(field);
      const actions = $("<div class=\"login-dialog-actions\"></div>").appendTo(wrapper);
      const confirmButton = $("<button type=\"button\"></button>").text(t("literal.button.continue", "Continuar")).appendTo(actions);
      const cancelButton = $("<button type=\"button\"></button>").text(t("literal.button.cancel", "Cancelar")).appendTo(actions);

      input.kendoDropDownList({
        dataTextField: "name",
        dataValueField: "id",
        dataSource: subscribers,
        value: response.defaultSubscriberId || subscribers[0].id
      });
      confirmButton.kendoButton({ icon: "login", themeColor: "primary" });
      cancelButton.kendoButton({ icon: "x" });

      if (subscriberWindow) {
        subscriberWindow.destroy();
      }
      wrapper.kendoWindow({
        title: t("login.title.select_subscriber", "Selecionar assinante"),
        modal: true,
        visible: false,
        resizable: false,
        width: Math.min(420, Math.max(320, global.innerWidth - 24)),
        close: function() {
          wrapper.remove();
          subscriberWindow = null;
        }
      });
      subscriberWindow = wrapper.data("kendoWindow");
      subscriberWindow.center().open();

      cancelButton.on("click", function() {
        subscriberWindow.close();
      });
      confirmButton.on("click", function() {
        const selectedId = String(input.data("kendoDropDownList").value() || "");
        if (!selectedId) {
            show(t("login.message.subscriber_required", "Selecione o assinante."));
            return;
        }
        confirmButton.data("kendoButton").enable(false);
        fetchJson("/api/auth/select-subscriber", {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json"
          },
          credentials: "include",
          body: JSON.stringify({
            selectionToken: response.selectionToken || response.subscriberSelectionToken,
            subscriberId: selectedId
          })
        }).then(function(authResponse) {
          if (subscriberWindow) {
            subscriberWindow.close();
          }
          return finishLogin(authResponse, { keepRememberToken: response.remember === true });
        }).catch(function(error) {
          show(errorMessage(error, "Nao foi possivel selecionar o assinante."));
        }).finally(function() {
          const button = confirmButton.data("kendoButton");
          if (button) {
            button.enable(true);
          }
        });
      });
    }

    function openPasswordResetWindow(initialToken) {
      const wrapper = $("<div class=\"login-dialog-form\"></div>");
      $("<p></p>").text(t("login.message.reset_intro", "Informe seu usuario ou e-mail. Se houver token, informe tambem a nova senha.")).appendTo(wrapper);

      const identityField = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span>Usuario ou e-mail</span>").appendTo(identityField);
      const identityInput = $("<input type=\"text\">").appendTo(identityField);

      const tokenField = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span>Token de recuperacao</span>").appendTo(tokenField);
      const tokenInput = $("<input type=\"text\">").val(initialToken || "").appendTo(tokenField);

      const passwordField = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span>Nova senha</span>").appendTo(passwordField);
      const newPasswordInput = $("<input type=\"password\">").appendTo(passwordField);

      const confirmField = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span>Confirmar senha</span>").appendTo(confirmField);
      const confirmPasswordInput = $("<input type=\"password\">").appendTo(confirmField);

      const actions = $("<div class=\"login-dialog-actions\"></div>").appendTo(wrapper);
      const requestButton = $("<button type=\"button\">Enviar instrucoes</button>").appendTo(actions);
      const resetButton = $("<button type=\"button\">Alterar senha</button>").appendTo(actions);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);

      identityInput.kendoTextBox({ placeholder: "usuario ou e-mail" });
      tokenInput.kendoTextBox({ placeholder: "token recebido" });
      newPasswordInput.kendoTextBox({ placeholder: "minimo 8 caracteres" });
      confirmPasswordInput.kendoTextBox({ placeholder: "repita a senha" });
      requestButton.kendoButton({ icon: "email" });
      resetButton.kendoButton({ icon: "check", themeColor: "primary" });
      closeButton.kendoButton({ icon: "x" });

      if (passwordResetWindow) {
        passwordResetWindow.destroy();
      }
      wrapper.kendoWindow({
        title: "Recuperar senha",
        modal: true,
        visible: false,
        resizable: false,
        width: Math.min(520, Math.max(320, global.innerWidth - 24)),
        close: function() {
          wrapper.remove();
          passwordResetWindow = null;
        }
      });
      passwordResetWindow = wrapper.data("kendoWindow");
      passwordResetWindow.center().open();

      requestButton.on("click", function() {
        const identity = String(identityInput.val() || "").trim();
        if (!identity) {
          show("Informe usuario ou e-mail.");
          return;
        }
        requestButton.data("kendoButton").enable(false);
        fetchJson("/api/auth/password/request-reset", {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json"
          },
          credentials: "include",
          body: JSON.stringify({ identity })
        }).then(function(response) {
          show(response.message || "Instrucoes enviadas.");
          if (response.resetToken) {
            tokenInput.val(response.resetToken);
            show("Token de desenvolvimento preenchido para teste local.");
          }
        }).catch(function(error) {
          show(errorMessage(error, "Nao foi possivel solicitar recuperacao."));
        }).finally(function() {
          const button = requestButton.data("kendoButton");
          if (button) {
            button.enable(true);
          }
        });
      });

      resetButton.on("click", function() {
        const resetToken = String(tokenInput.val() || "").trim();
        const password = String(newPasswordInput.val() || "");
        const confirmation = String(confirmPasswordInput.val() || "");
        if (!resetToken || !password) {
          show("Informe token e nova senha.");
          return;
        }
        if (password !== confirmation) {
          show("A confirmacao da senha nao confere.");
          return;
        }
        resetButton.data("kendoButton").enable(false);
        fetchJson("/api/auth/password/reset", {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json"
          },
          credentials: "include",
          body: JSON.stringify({ resetToken, password })
        }).then(function(response) {
          show(response.message || "Senha alterada com sucesso.");
          if (passwordResetWindow) {
            passwordResetWindow.close();
          }
        }).catch(function(error) {
          show(errorMessage(error, "Nao foi possivel alterar a senha."));
        }).finally(function() {
          const button = resetButton.data("kendoButton");
          if (button) {
            button.enable(true);
          }
        });
      });

      closeButton.on("click", function() {
        passwordResetWindow.close();
      });
    }

    function openResetFromQuery() {
      const params = new URLSearchParams(global.location.search || "");
      const resetToken = params.get("resetToken") || "";
      if (resetToken) {
        openPasswordResetWindow(resetToken);
      }
    }

    function finishLogin(response, options) {
      saveAuth(response, options);
      if (isAdminUser(response && response.user)) {
        return openAdminAreaSelection().then(function(area) {
          redirectAfterLogin(area);
        });
      }
      redirectAfterLogin("main");
      return Promise.resolve();
    }

    function saveAuth(response, options) {
      const settings = options || {};
      if (!response || !response.token || !response.session) {
        throw { error: { message: "Resposta de autenticacao invalida." } };
      }
      saveLocalValue("crudEngine.authToken", response.token);
      saveLocalValue("crudEngine.runtimeTenantId", response.tenantId || "default");
      saveLocalValue("crudEngine.runtimeSessionId", response.session.sessionId);
      if (response.currentSubscriber) {
        saveLocalValue("crudEngine.currentSubscriber", JSON.stringify(response.currentSubscriber));
      }
      if (Array.isArray(response.availableSubscribers)) {
        saveLocalValue("crudEngine.availableSubscribers", JSON.stringify(response.availableSubscribers));
      }
      if (response.rememberToken) {
        saveLocalValue("crudEngine.rememberToken", response.rememberToken);
        if (response.rememberTokenExpiresAt) {
          saveLocalValue("crudEngine.rememberTokenExpiresAt", response.rememberTokenExpiresAt);
        }
      } else if (!settings.keepRememberToken) {
        removeLocalValue("crudEngine.rememberToken");
        removeLocalValue("crudEngine.rememberTokenExpiresAt");
      }
      if (response.user) {
        saveLocalValue("crudEngine.lastUsername", response.user.username || response.user.id || "");
        saveLocalValue("crudEngine.runtimeUserId", response.user.id || response.user.username || "");
        saveLocalValue("crudEngine.runtimeUserName", response.user.name || response.user.username || "");
        saveLocalValue("crudEngine.runtimeUserGroups", JSON.stringify(ensureArray(response.user.groups)));
        saveLocalValue("crudEngine.runtimeUserPermissions", JSON.stringify(normalizePermissionPayload(response.user.permissions)));
      }
    }

    function openAdminAreaSelection() {
      const wrapper = $("<div class=\"login-dialog-form login-area-selector\"></div>");
      $("<p></p>").text("Selecione onde deseja entrar nesta sessao.").appendTo(wrapper);
      const actions = $("<div class=\"login-dialog-actions\"></div>").appendTo(wrapper);
      const mainButton = $("<button type=\"button\"></button>").text(t("login.area.main", "Area principal")).appendTo(actions);
      const adminButton = $("<button type=\"button\"></button>").text(t("login.area.admin", "Area administrativa")).appendTo(actions);

      mainButton.kendoButton({ icon: "home", themeColor: "primary" });
      adminButton.kendoButton({ icon: "gear" });

      if (adminAreaWindow) {
        adminAreaWindow.destroy();
      }

      return new Promise(function(resolve) {
        let resolved = false;
        function choose(area) {
          if (resolved) {
            return;
          }
          resolved = true;
          saveLocalValue("crudEngine.accessArea", area);
          if (adminAreaWindow) {
            adminAreaWindow.close();
          }
          resolve(area);
        }

        wrapper.kendoWindow({
          title: t("login.title.select_area", "Selecionar area"),
          modal: true,
          visible: false,
          resizable: false,
          actions: [],
          width: Math.min(460, Math.max(320, global.innerWidth - 24)),
          close: function() {
            wrapper.remove();
            adminAreaWindow = null;
          }
        });
        adminAreaWindow = wrapper.data("kendoWindow");
        adminAreaWindow.center().open();
        mainButton.on("click", function() {
          choose("main");
        });
        adminButton.on("click", function() {
          choose("admin");
        });
      });
    }

    function redirectAfterLogin(area) {
      const params = new URLSearchParams(global.location.search || "");
      const returnUrl = params.get("returnUrl") || "";
      const target = area === "admin"
        ? "home.html?screenId=home&accessArea=admin&initialProgramId=admin-parametros"
        : safeReturnUrl(returnUrl) || "home.html?screenId=home";
      global.location.href = target;
    }

    function isRememberTokenExpired(value) {
      const text = String(value || "").trim();
      if (!text) {
        return false;
      }
      const date = new Date(text);
      if (Number.isNaN(date.getTime())) {
        return false;
      }
      return date.getTime() <= Date.now();
    }

    function clearRememberTokenState() {
      global.CrudUtils.removeLocalValue("crudEngine.rememberToken");
      global.CrudUtils.removeLocalValue("crudEngine.rememberTokenExpiresAt");
    }

    function clearLocalSession(preserveLastUsername) {
      global.CrudUtils.clearRuntimeSessionContext({
        preserveLastUsername: preserveLastUsername === true,
        clearRememberToken: true
      });
    }

    function safeReturnUrl(value) {
      if (!value) {
        return "";
      }
      try {
        const url = new URL(value, global.location.href);
        if (url.origin !== global.location.origin) {
          return "";
        }
        return url.href;
      } catch (_) {
        return "";
      }
    }

    function setBusy(isBusy) {
      if (submitButton) {
        submitButton.enable(!isBusy);
      }
    }

    function fetchJson(url, options) {
      return fetch(url, options).then(function(response) {
        return response.text().then(function(text) {
          const payload = text ? JSON.parse(text) : {};
          if (!response.ok) {
            throw payload;
          }
          return payload;
        });
      });
    }

    function errorMessage(error, fallback) {
      return error && error.error && error.error.message || error && error.message || fallback;
    }

    function isAdminUser(user) {
      if (!user) {
        return false;
      }
      if (user.isAdmin === true || user.admin === true) {
        return true;
      }
      const groups = ensureArray(user.groups).map(function(item) {
        return String(item || "").toLowerCase();
      });
      if (groups.indexOf("admin") !== -1) {
        return true;
      }

      return hasPermission(user.permissions, "admin")
        || hasPermission(user.permissions, "admin.*")
        || hasPermission(user.permissions, "*");
    }

    function ensureArray(value) {
      return Array.isArray(value) ? value : [];
    }

    function normalizePermissionPayload(value) {
      const map = {};
      if (!value) {
        return map;
      }

      collectPermissions("", value, map);

      return map;
    }

    function collectPermissions(prefix, source, map) {
      if (!source) {
        return;
      }
      if (Array.isArray(source)) {
        source.forEach(function(item) {
          if (typeof item === "string" || typeof item === "number" || typeof item === "boolean") {
            const permission = String(item).trim().toLowerCase();
            if (!permission) {
              return;
            }
            if (prefix) {
              const candidate = prefix.indexOf(".") === -1 && permission.indexOf(".") === -1
                ? prefix + "." + permission
                : permission;
              map[candidate] = true;
            } else {
              map[permission] = true;
            }
            return;
          }
          if (item && typeof item === "object") {
            collectPermissions(prefix, item, map);
          }
        });
        return;
      }

      if (typeof source !== "object") {
        const permission = String(prefix || source).trim().toLowerCase();
        if (permission) {
          map[permission] = true;
        }
        return;
      }

      Object.keys(source).forEach(function(permission) {
        const key = String(permission || "").trim().toLowerCase();
        if (!key) {
          return;
        }
        const nextPrefix = prefix ? prefix + "." + key : key;
        const value = source[permission];

        if (value && typeof value === "object") {
          const isArray = Array.isArray(value);
          const isAssociative = isArray ? false : Object.keys(value).length !== value.length;
          if (isArray) {
            collectPermissions(nextPrefix, value, map);
            return;
          }
          if (isAssociative) {
            collectPermissions(nextPrefix, value, map);
            return;
          }
        }

        map[nextPrefix] = !isPermissionDenied(value);
      });
    }

    function isPermissionDenied(value) {
      if (value === false || value === 0 || value === "0") {
        return true;
      }
      if (typeof value === "string") {
        const normalized = String(value).trim().toLowerCase();
        return normalized === "false" || normalized === "nao" || normalized === "no";
      }
      return false;
    }

    function resolvePermissionSets(rawPermissions) {
      const allow = [];
      const deny = [];
      const normalized = normalizePermissionPayload(rawPermissions);
      Object.keys(normalized).forEach(function(permission) {
        if (normalized[permission]) {
          allow.push(permission);
        } else {
          deny.push(permission);
        }
      });
      return {
        allow: allow,
        deny: deny
      };
    }

    function hasPermission(rawPermissions, requiredPermission) {
      const permission = String(requiredPermission || "").trim().toLowerCase();
      if (!permission) {
        return true;
      }
      const sets = resolvePermissionSets(rawPermissions);
      for (let i = 0; i < sets.deny.length; i++) {
        if (permissionMatches(sets.deny[i], permission)) {
          return false;
        }
      }
      for (let i = 0; i < sets.allow.length; i++) {
        if (permissionMatches(sets.allow[i], permission)) {
          return true;
        }
      }
      return false;
    }

    function permissionMatches(pattern, permission) {
      if (!pattern) {
        return false;
      }
      if (pattern === "*" || pattern === permission) {
        return true;
      }
      if (pattern.slice(-2) === ".*") {
        return permission.indexOf(pattern.slice(0, -1)) === 0;
      }
      if (pattern.indexOf("*") === -1) {
        return false;
      }
      const escaped = pattern.replace(/[-/\\^$+?.()|[\]{}]/g, "\\$&");
      const regex = new RegExp("^" + escaped.replace(/\\\*/g, ".*") + "$");
      return regex.test(permission);
    }

    function show(message) {
      if (notification) {
        notification.show({ message: escapeHtml(message) }, "login");
      }
    }
  });

  function saveLocalValue(key, value) {
    return global.CrudUtils.saveLocalValue(key, value);
  }

  function readLocalValue(key) {
    return global.CrudUtils.readLocalValue(key, "");
  }

  function removeLocalValue(key) {
    return global.CrudUtils.removeLocalValue(key);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
})(window, jQuery);
