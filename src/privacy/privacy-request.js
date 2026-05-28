(function (global, $) {
  "use strict";

  const REQUEST_TYPES = [
    { value: "access", text: "Acesso aos dados" },
    { value: "correction", text: "Correcao" },
    { value: "portability", text: "Portabilidade" },
    { value: "anonymization", text: "Anonimizacao" },
    { value: "erasure", text: "Eliminacao" },
    { value: "blocking", text: "Bloqueio" },
    { value: "opposition", text: "Oposicao" },
    { value: "consent_revocation", text: "Revogacao de consentimento" }
  ];

  class PrivacyRequestPage {
    constructor(root) {
      this.root = $(root);
      this.apiBase = this.resolveApiBase();
      this.protocol = "";
      this.notification = null;
    }

    init() {
      this.render();
      this.bind();
      this.setupKendo();
    }

    render() {
      this.root.empty().append(
        $("<section></section>").addClass("privacy-request-shell").append(
          $("<div></div>").addClass("privacy-request-header").append(
            $("<span></span>").addClass("privacy-request-kicker").text("Privacidade"),
            $("<h1></h1>").text("Solicitacao LGPD"),
            $("<p></p>").text("Abra uma solicitacao relacionada aos seus dados pessoais. O protocolo so e encaminhado apos validar o e-mail informado.")
          ),
          $("<div></div>").attr("id", "privacy-request-notification"),
          $("<div></div>").addClass("privacy-request-layout").append(
            this.buildRequestPanel(),
            this.buildValidationPanel(),
            this.buildStatusPanel()
          )
        )
      );
    }

    buildRequestPanel() {
      return $("<form></form>").addClass("privacy-request-panel").attr("id", "privacy-request-form").append(
        $("<h2></h2>").text("Novo pedido"),
        this.field("requesterName", "Nome", "text"),
        this.field("requesterEmail", "E-mail", "email", true),
        this.field("requesterDocument", "CPF/CNPJ", "text"),
        this.field("subjectIdentifier", "Identificador do titular", "text"),
        $("<label></label>").text("Tipo de pedido").append(
          $("<select></select>").attr("id", "requestType").attr("name", "requestType")
        ),
        $("<label></label>").text("Descricao").append(
          $("<textarea></textarea>").attr("id", "description").attr("name", "description").attr("rows", 4)
        ),
        $("<button></button>").attr("type", "submit").attr("id", "start-request").text("Enviar codigo")
      );
    }

    buildValidationPanel() {
      return $("<form></form>").addClass("privacy-request-panel").attr("id", "privacy-confirm-form").append(
        $("<h2></h2>").text("Validar e-mail"),
        this.field("protocol", "Protocolo", "text", true),
        this.field("confirmEmail", "E-mail", "email", true),
        this.field("code", "Codigo recebido", "text", true),
        $("<button></button>").attr("type", "submit").attr("id", "confirm-request").text("Validar solicitacao")
      );
    }

    buildStatusPanel() {
      return $("<form></form>").addClass("privacy-request-panel").attr("id", "privacy-status-form").append(
        $("<h2></h2>").text("Acompanhar protocolo"),
        this.field("statusProtocol", "Protocolo", "text", true),
        this.field("statusEmail", "E-mail", "email", true),
        $("<button></button>").attr("type", "submit").attr("id", "check-status").text("Consultar"),
        $("<div></div>").attr("id", "privacy-status-result").addClass("privacy-status-result")
      );
    }

    field(id, label, type, required) {
      const input = $("<input>").attr("id", id).attr("name", id).attr("type", type || "text");
      if (required) {
        input.attr("required", "required");
      }
      return $("<label></label>").text(label).append(input);
    }

    setupKendo() {
      if (global.kendo) {
        global.kendo.culture("pt-BR");
        $("#requestType").kendoDropDownList({
          dataTextField: "text",
          dataValueField: "value",
          dataSource: REQUEST_TYPES,
          value: "access"
        });
        this.root.find("input").kendoTextBox();
        this.root.find("textarea").kendoTextArea({ resize: "vertical" });
        this.root.find("button").kendoButton({ themeColor: "primary" });
        this.notification = $("#privacy-request-notification").kendoNotification({
          position: { pinned: true, top: 16, right: 16 },
          autoHideAfter: 5000
        }).data("kendoNotification");
      }
    }

    bind() {
      this.root.on("submit", "#privacy-request-form", (event) => {
        event.preventDefault();
        this.startRequest();
      });
      this.root.on("submit", "#privacy-confirm-form", (event) => {
        event.preventDefault();
        this.confirmRequest();
      });
      this.root.on("submit", "#privacy-status-form", (event) => {
        event.preventDefault();
        this.checkStatus();
      });
    }

    async startRequest() {
      const payload = {
        requesterName: $("#requesterName").val(),
        requesterEmail: $("#requesterEmail").val(),
        requesterDocument: $("#requesterDocument").val(),
        subjectIdentifier: $("#subjectIdentifier").val(),
        requestType: $("#requestType").val(),
        description: $("#description").val()
      };
      const response = await this.post("/api/public/privacy/requests/start", payload);
      this.protocol = response.protocol || "";
      $("#protocol").val(this.protocol);
      $("#statusProtocol").val(this.protocol);
      $("#confirmEmail").val(payload.requesterEmail || "");
      $("#statusEmail").val(payload.requesterEmail || "");
      this.show("success", response.message || "Codigo enviado.");
    }

    async confirmRequest() {
      const response = await this.post("/api/public/privacy/requests/confirm", {
        protocol: $("#protocol").val(),
        requesterEmail: $("#confirmEmail").val(),
        code: $("#code").val()
      });
      this.show("success", response.message || "Solicitacao validada.");
    }

    async checkStatus() {
      const protocol = encodeURIComponent(String($("#statusProtocol").val() || ""));
      const email = encodeURIComponent(String($("#statusEmail").val() || ""));
      const response = await this.get(`/api/public/privacy/requests/${protocol}?email=${email}`);
      $("#privacy-status-result").empty().append(
        $("<dl></dl>").append(
          $("<dt></dt>").text("Status"),
          $("<dd></dd>").text(response.status || "-"),
          $("<dt></dt>").text("Prioridade"),
          $("<dd></dd>").text(response.priority || "-"),
          $("<dt></dt>").text("Prazo"),
          $("<dd></dd>").text(this.formatDate(response.dueAt)),
          $("<dt></dt>").text("Decisao"),
          $("<dd></dd>").text(response.decision || "-")
        )
      );
    }

    async post(path, payload) {
      return this.request(path, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload || {})
      });
    }

    async get(path) {
      return this.request(path, { method: "GET" });
    }

    async request(path, options) {
      try {
        const response = await fetch(this.apiBase + path, options);
        const payload = await response.json();
        if (!response.ok) {
          throw new Error((payload.error && payload.error.message) || "Nao foi possivel concluir a operacao.");
        }
        return payload;
      } catch (error) {
        this.show("error", error.message || "Erro inesperado.");
        throw error;
      }
    }

    resolveApiBase() {
      if (global.location.protocol === "file:") {
        return "http://127.0.0.1:8765";
      }
      return "";
    }

    show(type, message) {
      if (this.notification) {
        this.notification.show(message, type);
        return;
      }
      $("#privacy-request-notification").text(message).attr("data-type", type);
    }

    formatDate(value) {
      if (!value) {
        return "-";
      }
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return "-";
      }
      return date.toLocaleString("pt-BR");
    }
  }

  global.PrivacyRequestPage = PrivacyRequestPage;
})(window, window.jQuery);
