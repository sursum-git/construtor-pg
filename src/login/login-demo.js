(function(global, $) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (global.kendo) {
      kendo.culture("pt-BR");
    }
    const literalClient = global.CrudHttpClient ? new global.CrudHttpClient({ allowLocalFallback: true }) : null;
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

    $("#login-user").kendoTextBox({
      placeholder: t("login.placeholder.username", "usuario@empresa.com")
    });
    const rememberedUsername = readLocalValue("crudEngine.lastUsername");
    if (rememberedUsername) {
      $("#login-user").val(rememberedUsername);
    }
    const passwordInput = $("#login-password");
    passwordInput.kendoTextBox({
      placeholder: t("login.placeholder.password", "Senha")
    });
    const passwordToggle = $("#login-toggle-password").kendoButton({
      icon: "eye",
      fillMode: "flat"
    }).data("kendoButton");
    let adminAreaWindow = null;
    if ($.fn.kendoCheckBox) {
      $("#login-remember").kendoCheckBox();
    }
    $("#login-submit").kendoButton({
      icon: "login",
      themeColor: "primary"
    });

    $("#login-form").on("submit", function(event) {
      event.preventDefault();
      const username = String($("#login-user").val() || "").trim();
      const password = String($("#login-password").val() || "");
      if (!username || !password) {
        show(t("login.message.credentials_required", "Informe usuario e senha para continuar."));
        return;
      }
      saveLocalValue("crudEngine.lastUsername", username);
      openSubscriberSelection(function() {
        openAdminAreaSelection().then(redirectAfterLogin);
      });
    });

    $("#login-forgot").on("click", function() {
      openPasswordResetDemo();
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

    function openSubscriberSelection(onSelected) {
      const wrapper = $("<div class=\"login-dialog-form\"></div>");
      $("<p></p>").text(t("login.demo.subscriber_info", "Demo: selecione o assinante que seria retornado pelo backend apos validar o usuario.")).appendTo(wrapper);
      const field = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span></span>").text(t("login.label.subscriber", "Assinante")).appendTo(field);
      const input = $("<input type=\"text\">").appendTo(field);
      const actions = $("<div class=\"login-dialog-actions\"></div>").appendTo(wrapper);
      const confirmButton = $("<button type=\"button\"></button>").text(t("literal.button.continue", "Continuar")).appendTo(actions);
      const cancelButton = $("<button type=\"button\"></button>").text(t("literal.button.cancel", "Cancelar")).appendTo(actions);
      const subscribers = [
        { id: "default", name: "Principal" },
        { id: "empresa-a", name: "Empresa A" },
        { id: "empresa-b", name: "Empresa B" }
      ];

      input.kendoDropDownList({
        dataTextField: "name",
        dataValueField: "id",
        dataSource: subscribers,
        value: "default"
      });
      confirmButton.kendoButton({ icon: "login", themeColor: "primary" });
      cancelButton.kendoButton({ icon: "x" });
      wrapper.kendoWindow({
        title: t("login.title.select_subscriber", "Selecionar assinante"),
        modal: true,
        visible: false,
        resizable: false,
        width: Math.min(420, Math.max(320, global.innerWidth - 24)),
        close: function() {
          wrapper.remove();
        }
      });
      const win = wrapper.data("kendoWindow");
      win.center().open();
      cancelButton.on("click", function() {
        win.close();
      });
      confirmButton.on("click", function() {
        const selected = input.data("kendoDropDownList").dataItem();
        win.close();
        saveLocalValue("crudEngine.currentSubscriber", JSON.stringify(selected || subscribers[0]));
        show(t("login.demo.subscriber_selected", "Demo pronta: assinante selecionado: {name}.", { name: selected && selected.name || "Principal" }));
        if (typeof onSelected === "function") {
          onSelected(selected || subscribers[0]);
        }
      });
    }

    function openAdminAreaSelection() {
      const wrapper = $("<div class=\"login-dialog-form login-area-selector\"></div>");
      $("<p></p>").text(t("login.demo.area_info", "Demo: usuario administrador pode escolher a area de entrada apos autenticar.")).appendTo(wrapper);
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
      const target = area === "admin"
        ? "home.html?accessArea=admin&initialProgramId=admin-parametros"
        : "home.html";
      global.location.href = target;
    }

    function openPasswordResetDemo() {
      const wrapper = $("<div class=\"login-dialog-form\"></div>");
      $("<p></p>").text(t("login.demo.reset_info", "Demo: em producao, o backend envia o token por e-mail e valida a nova senha.")).appendTo(wrapper);
      const identityField = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span>Usuario ou e-mail</span>").appendTo(identityField);
      $("<input type=\"text\">").appendTo(identityField).kendoTextBox({ placeholder: "usuario ou e-mail" });
      const tokenField = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span>Token</span>").appendTo(tokenField);
      $("<input type=\"text\">").val("demo-token").appendTo(tokenField).kendoTextBox();
      const passwordField = $("<label class=\"login-field\"></label>").appendTo(wrapper);
      $("<span>Nova senha</span>").appendTo(passwordField);
      $("<input type=\"password\">").appendTo(passwordField).kendoTextBox();
      const actions = $("<div class=\"login-dialog-actions\"></div>").appendTo(wrapper);
      const requestButton = $("<button type=\"button\">Enviar instrucoes</button>").appendTo(actions);
      const resetButton = $("<button type=\"button\">Alterar senha</button>").appendTo(actions);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      requestButton.kendoButton({ icon: "email" });
      resetButton.kendoButton({ icon: "check", themeColor: "primary" });
      closeButton.kendoButton({ icon: "x" });
      wrapper.kendoWindow({
        title: "Recuperar senha",
        modal: true,
        visible: false,
        resizable: false,
        width: Math.min(520, Math.max(320, global.innerWidth - 24)),
        close: function() {
          wrapper.remove();
        }
      });
      const win = wrapper.data("kendoWindow");
      win.center().open();
      requestButton.on("click", function() {
        show("Demo: instrucoes de recuperacao enviadas.");
      });
      resetButton.on("click", function() {
        show("Demo: senha alterada com sucesso.");
        win.close();
      });
      closeButton.on("click", function() {
        win.close();
      });
    }

    function show(message) {
      if (notification) {
        notification.show({ message: escapeHtml(message) }, "login");
      }
    }
  });

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function saveLocalValue(key, value) {
    return global.CrudUtils.saveLocalValue(key, value);
  }

  function readLocalValue(key) {
    return global.CrudUtils.readLocalValue(key, "");
  }

  function removeLocalValue(key) {
    return global.CrudUtils.removeLocalValue(key);
  }

  function clearLocalSession(preserveLastUsername) {
    global.CrudUtils.clearRuntimeSessionContext({
      preserveLastUsername: preserveLastUsername === true,
      clearRememberToken: true
    });
  }
})(window, jQuery);
