(function(global, $) {
  "use strict";

  class HomeEngine {
    constructor(options) {
      this.options = options || {};
      this.root = $(this.options.root);
      this.httpClient = this.options.httpClient || new global.CrudHttpClient();
      this.configLoader = new global.CrudConfigLoader();
      this.loader = new global.HomeDefinitionLoader({ httpClient: this.httpClient });
      this.validator = new global.HomeDefinitionValidator();
      this.config = this.normalizeConfig(this.options.config);
      this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
      this.currentTheme = this.resolveInitialTheme();
      this.systemUpdatesSummary = null;
      this.systemUpdatesBlockWindow = null;
      this.systemUpdatesBlocked = false;
      this.currentProgram = null;
      this.currentProgramEngine = null;
      this.currentProgramFrame = null;
      this.runtimeMessageTimer = null;
      this.runtimeEventSource = null;
      this.runtimeEventFallbackTimer = null;
      this.notificationsIndicatorTimer = null;
        this.notificationsIndicatorButton = null;
        this.notificationsIndicatorBadge = null;
        this.notificationsCount = 0;
        this.notificationListFilters = {
          severity: "",
          category: "",
          actionRequired: false,
          unreadOnly: true,
          includeRead: false
        };
        this.jobsIndicatorTimer = null;
      this.jobsIndicatorButton = null;
      this.jobsIndicatorBadge = null;
      this.completedJobsCount = 0;
      this.completedJobIds = {};
      this.sessionRevoked = false;
      this.loggingOut = false;
      this.chatWindowElement = null;
      this.chatWindow = null;
      this.chatToolbar = null;
      this.chatHost = null;
      this.chatWidget = null;
      this.chatRecipientInput = null;
      this.chatRecipientComboBox = null;
      this.chatRecipients = [];
      this.currentChatRecipient = null;
      this.chatHistoryLoaded = false;
      this.chatHistoryRecipientId = "";
      this.chatLastMessageId = 0;
      this.chatEventSource = null;
      this.chatEventReconnectTimer = null;
      this.chatEventRecipientId = "";
      this.chatEventSignature = "";
      this.supportWindowElement = null;
      this.supportWindow = null;
      this.supportStatusElement = null;
      this.supportOnlineSectorInput = null;
      this.supportOnlineSectorComboBox = null;
      this.supportUserInput = null;
      this.supportUserComboBox = null;
      this.supportUserField = null;
      this.supportSectorInput = null;
      this.supportSectorComboBox = null;
      this.supportSubjectInput = null;
      this.supportDescriptionInput = null;
      this.supportPriorityInput = null;
      this.supportOnlineSection = null;
      this.supportRequestSection = null;
      this.supportRequestBackButton = null;
      this.supportRequestToggleButton = null;
      this.supportChatHost = null;
      this.supportChatWidget = null;
      this.supportContextElement = null;
      this.supportOnlineUsers = [];
      this.supportSectors = [];
      this.currentSupportUser = null;
      this.currentSupportSectorId = "";
      this.currentSupportContext = null;
      this.supportHistoryLoadedUserId = "";
      this.supportLastMessageId = 0;
      this.supportCurrentProtocol = "";
      this.supportEventSource = null;
      this.supportEventReconnectTimer = null;
      this.supportEventAttendantId = "";
      this.supportEventSignature = "";
      this.aiChatWindowElement = null;
      this.aiChatWindow = null;
      this.aiChatHost = null;
      this.aiChatWidget = null;
      this.aiChatHistoryLoaded = false;
      this.appbarPanelWindows = {};
      this.subscriberSwitchWindowElement = null;
      this.subscriberSwitchWindow = null;
      this.subscriberSwitchInput = null;
      this.subscriberSwitchDropDown = null;
      this.currentSubscriberElement = null;
      this.currentSidebarToggleIcon = "";
      this.allModulesId = "__all__";
      this.modules = [];
      this.currentModuleId = "";
      this.menuSearchText = "";
      this.showOnlyFavorites = false;
      this.applyTheme(this.currentTheme, { persist: false });
    }

    init() {
      global.CrudUtils.beginRenderGate(this.root);
      this.renderLoading();
      return this.loadConfig().then(() => {
        this.securityPolicy = global.CrudUtils.normalizeSecurityPolicy(this.config, this.options);
        return this.loader.load({
          definitionUrl: this.options.definitionUrl,
          definition: this.options.definition,
          screenId: this.options.screenId || this.options.homeId,
          securityPolicy: this.securityPolicy
        });
      }).then((definition) => {
        return this.loadCurrentAuthSession().then(() => definition);
      }).then((definition) => {
        this.definition = this.normalizeDefinition(definition);
        this.validator.validate(this.definition, { securityPolicy: this.securityPolicy });
        this.applyDefinitionSecurity(this.definition);
        this.loadNotificationFilterState();
        this.loadUserFavoriteState();
        this.loadNavigationState();
        this.modules = this.buildModuleList();
        this.currentModuleId = this.resolveInitialModuleId();
        this.render();
        this.startRuntimeMessagePolling();
        return this.maybeCheckSystemUpdatesSummary().then(() => {
          if (this.systemUpdatesBlocked) {
            global.CrudUtils.completeRenderGate(this.root);
            return this;
          }
          return this.openInitialProgram().then(() => {
            this.restoreAppbarPanelContext();
            global.CrudUtils.completeRenderGate(this.root);
            return this;
          });
        });
      }).catch((error) => {
        this.renderError(global.CrudUtils.unwrapError(error, "Erro ao carregar pagina inicial."));
        global.CrudUtils.failRenderGate(this.root);
        throw error;
      });
    }

    loadConfig() {
      return this.configLoader.load({
        configUrl: this.options.configUrl,
        config: this.options.config
      }).then((config) => {
        this.config = this.normalizeConfig(config);
        this.applyKendoTheme();
        this.currentTheme = this.resolveInitialTheme();
        this.applyTheme(this.currentTheme, { persist: false });
        return global.CrudUtils.loadLiteralBundle(this.config.literals || {}, this.httpClient).then(() => this.config);
      });
    }

    normalizeDefinition(definition) {
      const source = global.CrudUtils.clone(definition || {});
      source.layout = Object.assign({
        initialProgramId: "",
        sidebar: {
          component: "kendoTreeView",
          collapsible: true,
          collapsed: false,
          expanded: true
        },
        appbar: {
          showSidebarToggle: true,
          showRefresh: true,
          showThemeSwitch: true,
          showFavoriteToggle: true,
          showCurrentSubscriber: true,
          subscriberSwitch: {
            enabled: false
          },
          showUserMenu: true,
          chat: {
            enabled: false
          },
          support: {
            enabled: false
          },
          aiChat: {
            enabled: false
          },
          notifications: {
            enabled: false
          },
          alerts: {
            enabled: false
          },
          requests: {
            enabled: false
          },
          jobs: {
            enabled: false
          },
          userMenu: {
            items: []
          }
        }
      }, source.layout || {});
      source.layout.sidebar = Object.assign({
        component: "kendoTreeView",
        collapsible: true,
        collapsed: false,
        expanded: true
      }, source.layout.sidebar || {});
      source.layout.appbar = Object.assign({
        showSidebarToggle: true,
        showRefresh: true,
        showThemeSwitch: true,
        showFavoriteToggle: true,
        showCurrentSubscriber: true,
        subscriberSwitch: {
          enabled: false
        },
        showUserMenu: true,
        chat: {
          enabled: false
        },
        support: {
          enabled: false
        },
        aiChat: {
          enabled: false
        },
        notifications: {
          enabled: false
        },
        alerts: {
          enabled: false
        },
        requests: {
          enabled: false
        },
        jobs: {
          enabled: false
        },
        userMenu: {
          items: []
        }
      }, source.layout.appbar || {});
      source.currentUser = source.currentUser || source.user || {};
      if (this.authSessionInfo && this.authSessionInfo.user) {
        source.currentUser = Object.assign({}, source.currentUser || {}, this.authSessionInfo.user);
        source.user = source.currentUser;
      }
      if (this.authSessionInfo && this.authSessionInfo.session && this.authSessionInfo.session.impersonation) {
        source.session = Object.assign({}, source.session || {}, {
          impersonation: this.authSessionInfo.session.impersonation
        });
      }
      source.currentSubscriber = source.currentSubscriber || source.currentTenant || source.tenant || source.subscriber || {};
      const storedSubscriber = global.CrudUtils.readLocalJsonValue("crudEngine.currentSubscriber", null);
      if (storedSubscriber && typeof storedSubscriber === "object" && !Array.isArray(storedSubscriber)) {
        source.currentSubscriber = Object.assign({}, source.currentSubscriber || {}, storedSubscriber);
        source.currentTenant = source.currentSubscriber;
        source.tenant = source.currentSubscriber;
      }
      source.navigation = source.navigation || {};
      source.permissions = source.permissions || {};
      return source;
    }

    loadCurrentAuthSession() {
      if (!this.httpClient || !this.httpClient.authToken) {
        this.authSessionInfo = null;
        return Promise.resolve(null);
      }
      return this.httpClient.request({
        url: "/api/auth/session",
        method: "GET"
      }).then((response) => {
        this.authSessionInfo = response || null;
        if (response && response.session && response.session.impersonation) {
          global.CrudUtils.saveLocalJsonValue("crudEngine.impersonation", response.session.impersonation);
        }
        return response;
      }).catch(() => {
        this.authSessionInfo = null;
        return null;
      });
    }

    applyDefinitionSecurity(definition) {
      if (!definition || !this.securityPolicy) {
        return definition;
      }
      const screenId = this.getHomeScreenId();
      const appbar = definition.layout && definition.layout.appbar ? definition.layout.appbar : {};

      this.applyEndpointGroupSecurity(appbar.chat || definition.chat, {
        contacts: "home.chat.contacts",
        history: "home.chat.history",
        send: "home.chat.send",
        events: "home.chat.events"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.support || definition.support, {
        onlineUsers: "home.support.onlineUsers",
        history: "home.support.history",
        send: "home.support.send",
        createRequest: "home.support.createRequest",
        requestStatus: "home.support.requestStatus",
        events: "home.support.events"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.aiChat || appbar.iaChat || definition.aiChat || definition.iaChat, {
        history: "home.aiChat.history",
        send: "home.aiChat.send"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.notifications || definition.notifications, {
        list: "home.notifications.list",
        ack: "home.notifications.ack"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.alerts || definition.alerts, {
        list: "home.alerts.list"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.requests || definition.requests, {
        list: "home.requests.list"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.jobs || definition.jobs, {
        list: "home.jobs.list"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.subscriberSwitch || definition.subscriberSwitch, {
        change: "home.subscriber.change"
      }, screenId);
      this.applyEndpointGroupSecurity(appbar.runtimeMessages || definition.runtimeMessages, {
        poll: "runtime.messages.poll",
        ack: "runtime.messages.ack",
        forceLogout: "runtime.admin.forceLogout"
      }, screenId);
      return definition;
    }

    applyEndpointGroupSecurity(group, endpointIds, screenId) {
      if (!group) {
        return;
      }
      group.endpoints = Object.assign({}, group.endpoints || {});
      Object.keys(endpointIds).forEach((key) => {
        const value = group.endpoints[key] || group[key + "Url"] || group[key];
        if (!value) {
          return;
        }
        group.endpoints[key] = global.CrudUtils.resolveEndpointForPolicy(value, endpointIds[key], screenId, this.securityPolicy);
      });
    }

    getHomeScreenId() {
      return String(this.definition && this.definition.screenId || this.definition && this.definition.app && this.definition.app.id || "home").trim();
    }

    getHomePreferenceStorageKey(suffix) {
      const screenId = this.getHomeScreenId() || "home";
      return "homeEngine." + screenId + "." + String(suffix || "state");
    }

    loadNotificationFilterState() {
      const parsed = global.CrudUtils.readLocalStateValue(this.getHomePreferenceStorageKey("notificationFilters"), null, { version: 1 });
      if (!parsed || typeof parsed !== "object") {
        return;
      }
      this.notificationListFilters = Object.assign({}, this.notificationListFilters, {
        severity: String(parsed.severity || ""),
        category: String(parsed.category || ""),
        actionRequired: parsed.actionRequired === true,
        unreadOnly: parsed.unreadOnly !== false,
        includeRead: parsed.includeRead === true
      });
    }

    saveNotificationFilterState() {
      global.CrudUtils.saveLocalStateValue(this.getHomePreferenceStorageKey("notificationFilters"), {
        severity: this.notificationListFilters.severity || "",
        category: this.notificationListFilters.category || "",
        actionRequired: this.notificationListFilters.actionRequired === true,
        unreadOnly: this.notificationListFilters.unreadOnly !== false,
        includeRead: this.notificationListFilters.includeRead === true
      }, { version: 1 });
    }

    loadNavigationState() {
      const parsed = global.CrudUtils.readLocalStateValue(this.getHomePreferenceStorageKey("navigationState"), null, { version: 2 });
      if (!parsed || typeof parsed !== "object") {
        return;
      }
      this.currentModuleId = String(parsed.currentModuleId || "").trim();
      this.menuSearchText = String(parsed.menuSearchText || "");
      this.showOnlyFavorites = parsed.showOnlyFavorites === true;
      this.hasSavedSidebarState = Object.prototype.hasOwnProperty.call(parsed, "sidebarCollapsed");
      this.savedSidebarCollapsed = parsed.sidebarCollapsed === true;
      this.savedProgramId = String(parsed.currentProgramId || "").trim();
      this.savedAppbarPanelKind = String(parsed.appbarPanelKind || "").trim();
    }

    saveNavigationState() {
      global.CrudUtils.saveLocalStateValue(this.getHomePreferenceStorageKey("navigationState"), {
        currentModuleId: this.currentModuleId || "",
        menuSearchText: this.menuSearchText || "",
        showOnlyFavorites: this.showOnlyFavorites === true,
        currentProgramId: this.currentProgram && this.currentProgram.id ? String(this.currentProgram.id) : (this.savedProgramId || ""),
        sidebarCollapsed: this.shell ? this.shell.hasClass("home-sidebar-collapsed") : this.savedSidebarCollapsed === true,
        appbarPanelKind: this.savedAppbarPanelKind || ""
      }, { version: 2 });
    }

    maybeCheckSystemUpdatesSummary() {
      if (!this.httpClient || typeof this.httpClient.request !== "function") {
        return Promise.resolve(null);
      }
      if (!global.location || global.location.protocol === "file:") {
        return Promise.resolve(null);
      }
      return this.httpClient.request({
        method: "GET",
        url: "/api/runtime/system-updates/summary"
      }).then((summary) => {
        this.handleSystemUpdatesSummary(summary || {});
        return summary || {};
      }).catch(() => null);
    }

    handleSystemUpdatesSummary(summary) {
      const normalized = summary && typeof summary === "object" ? summary : {};
      this.systemUpdatesSummary = normalized;
      this.systemUpdatesBlocked = String(normalized.accessMode || "") === "blocked";
      const autoQueuedVersion = normalized.autoQueuedRelease && normalized.autoQueuedRelease.version
        ? String(normalized.autoQueuedRelease.version)
        : "";
      const nextState = {
        currentVersion: String(normalized.currentVersion || ""),
        pendingCount: Number(normalized.pendingCount || 0),
        criticalPendingCount: Number(normalized.criticalPendingCount || 0),
        autoQueuedVersion: autoQueuedVersion
      };
      const stored = global.CrudUtils.readLocalStateValue(this.getHomePreferenceStorageKey("systemUpdatesNotice"), null, { version: 1 }) || {};
      const lastState = stored && typeof stored === "object" ? stored : {};

      if (autoQueuedVersion && lastState.autoQueuedVersion !== autoQueuedVersion) {
        global.CrudUtils.showMessage("Atualizacao automatica enfileirada ao abrir o sistema: " + autoQueuedVersion + ".", "info");
      } else if (
        nextState.criticalPendingCount > 0 &&
        (Number(lastState.criticalPendingCount || 0) !== nextState.criticalPendingCount || lastState.currentVersion !== nextState.currentVersion)
      ) {
        global.CrudUtils.showMessage("Existe atualizacao critica pendente. Revise `admin.atualizacoes`.", "warning");
      }

      if (this.systemUpdatesBlocked) {
        this.openSystemUpdateBlockWindow(normalized);
      } else {
        this.closeSystemUpdateBlockWindow();
        if (!this.currentProgram && this.definition) {
          this.openInitialProgram().then(() => {
            this.restoreAppbarPanelContext();
          });
        }
      }

      global.CrudUtils.saveLocalStateValue(this.getHomePreferenceStorageKey("systemUpdatesNotice"), nextState, { version: 1 });
    }

    openSystemUpdateBlockWindow(summary) {
      const normalized = summary && typeof summary === "object" ? summary : {};
      if (this.systemUpdatesBlockWindow && this.systemUpdatesBlockWindow.wrapper && this.systemUpdatesBlockWindow.window) {
        this.systemUpdatesBlockWindow.message.text(normalized.criticalActionMessage || "Atualizacao critica obrigatoria pendente.");
        this.systemUpdatesBlockWindow.runButton.text(normalized.criticalActionLabel || "Executar atualizacao local");
        this.systemUpdatesBlockWindow.runButton.toggle((normalized.criticalActionKind === "run" && normalized.canRunPendingLocally === true) || (normalized.criticalActionKind === "download" && normalized.canDownloadPendingLocally === true));
        return;
      }

      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-confirm-content crud-blocking-message\"></div>").appendTo(wrapper);
      $("<p></p>").text(normalized.criticalActionMessage || "Atualizacao critica obrigatoria pendente.").appendTo(content);
      const actions = $("<div class=\"crud-form-actions\"></div>").appendTo(content);
      const runButton = $("<button type=\"button\"></button>").text(normalized.criticalActionLabel || "Executar atualizacao local").appendTo(actions);
      const refreshButton = $("<button type=\"button\">Verificar novamente</button>").appendTo(actions);
      const exitButton = $("<button type=\"button\">Sair</button>").appendTo(actions);
      runButton.kendoButton({ themeColor: "primary", icon: "play" });
      refreshButton.kendoButton({ icon: "reload" });
      exitButton.kendoButton({ icon: "logout" });

      wrapper.kendoWindow({
        title: normalized.criticalActionTitle || "Atualizacao critica obrigatoria",
        modal: true,
        actions: [],
        resizable: false,
        width: Math.min(560, Math.max(320, window.innerWidth - 24)),
        visible: false
      });

      const windowWidget = wrapper.data("kendoWindow");
      const state = {
        wrapper: wrapper,
        window: windowWidget,
        message: content.find("p").first(),
        runButton: runButton,
        refreshButton: refreshButton,
        exitButton: exitButton
      };
      this.systemUpdatesBlockWindow = state;
      runButton.toggle((normalized.criticalActionKind === "run" && normalized.canRunPendingLocally === true) || (normalized.criticalActionKind === "download" && normalized.canDownloadPendingLocally === true));
      runButton.on("click", () => this.handleSystemUpdatesPrimaryActionFromHome());
      refreshButton.on("click", () => this.maybeCheckSystemUpdatesSummary());
      exitButton.on("click", () => this.handleLogoutRequest());
      windowWidget.center().open();
    }

    closeSystemUpdateBlockWindow() {
      if (!this.systemUpdatesBlockWindow || !this.systemUpdatesBlockWindow.window) {
        return;
      }
      const wrapper = this.systemUpdatesBlockWindow.wrapper;
      const windowWidget = this.systemUpdatesBlockWindow.window;
      this.systemUpdatesBlockWindow = null;
      windowWidget.destroy();
      wrapper.remove();
    }

    handleSystemUpdatesPrimaryActionFromHome() {
      const summary = this.systemUpdatesSummary || {};
      if (String(summary.criticalActionKind || "") === "download") {
        this.downloadPendingSystemUpdatesFromHome();
        return;
      }
      this.runPendingSystemUpdatesFromHome();
    }

    runPendingSystemUpdatesFromHome() {
      const summary = this.systemUpdatesSummary || {};
      const endpoint = String(summary.runtimeRunPendingEndpoint || "").trim();
      if (!endpoint || !this.httpClient || typeof this.httpClient.request !== "function") {
        global.CrudUtils.showMessage("A aplicacao local da atualizacao nao esta disponivel neste ambiente.", "warning");
        return;
      }
      if (this.systemUpdatesBlockWindow && this.systemUpdatesBlockWindow.runButton) {
        this.systemUpdatesBlockWindow.runButton.prop("disabled", true);
      }
      this.httpClient.request({
        method: "POST",
        url: endpoint,
        data: {}
      }).then((payload) => {
        const runtimeSummary = payload && payload.runtimeSummary ? payload.runtimeSummary : null;
        global.CrudUtils.showMessage("Rotina local de atualizacao executada.", "success");
        if (runtimeSummary) {
          this.handleSystemUpdatesSummary(runtimeSummary);
        } else {
          this.maybeCheckSystemUpdatesSummary();
        }
      }).catch((error) => {
        const message = error && error.error && error.error.message || error && error.message || "Falha ao executar a atualizacao local.";
        global.CrudUtils.showMessage(message, "error");
      }).finally(() => {
        if (this.systemUpdatesBlockWindow && this.systemUpdatesBlockWindow.runButton) {
          this.systemUpdatesBlockWindow.runButton.prop("disabled", false);
        }
      });
    }

    downloadPendingSystemUpdatesFromHome() {
      const summary = this.systemUpdatesSummary || {};
      const endpoint = String(summary.runtimeDownloadPendingEndpoint || "").trim();
      if (!endpoint || !this.httpClient || typeof this.httpClient.request !== "function") {
        global.CrudUtils.showMessage("O download local do pacote critico nao esta disponivel neste ambiente.", "warning");
        return;
      }
      if (this.systemUpdatesBlockWindow && this.systemUpdatesBlockWindow.runButton) {
        this.systemUpdatesBlockWindow.runButton.prop("disabled", true);
      }
      this.httpClient.request({
        method: "POST",
        url: endpoint,
        data: {}
      }).then((payload) => {
        const runtimeSummary = payload && payload.runtimeSummary ? payload.runtimeSummary : null;
        global.CrudUtils.showMessage("Pacote critico baixado localmente.", "success");
        if (runtimeSummary) {
          this.handleSystemUpdatesSummary(runtimeSummary);
        } else {
          this.maybeCheckSystemUpdatesSummary();
        }
      }).catch((error) => {
        const message = error && error.error && error.error.message || error && error.message || "Falha ao baixar o pacote critico.";
        global.CrudUtils.showMessage(message, "error");
      }).finally(() => {
        if (this.systemUpdatesBlockWindow && this.systemUpdatesBlockWindow.runButton) {
          this.systemUpdatesBlockWindow.runButton.prop("disabled", false);
        }
      });
    }

    normalizeConfig(config) {
      const source = config || {};
      const defaultConfig = {
        schemaVersion: "1.0",
        theme: {
          kendoTheme: "kendo/styles/default-urban.css",
          defaultMode: "light",
          allowUserSwitch: true,
          persistUserChoice: true,
          storageKey: "crudEngine.theme",
          tokens: {
            light: {},
            dark: {}
          }
        }
      };
      const sourceTheme = source.theme || {};
      const sourceTokens = sourceTheme.tokens || {};
      return Object.assign({}, defaultConfig, source, {
        theme: Object.assign({}, defaultConfig.theme, sourceTheme, {
          tokens: {
            light: Object.assign({}, defaultConfig.theme.tokens.light, sourceTokens.light || {}),
            dark: Object.assign({}, defaultConfig.theme.tokens.dark, sourceTokens.dark || {})
          }
        })
      });
    }

    render() {
      this.destroyChatWindow();
      this.destroySupportWindow();
      this.destroyAiChatWindow();
      this.destroyAppbarPanelWindows();
      this.destroySubscriberSwitchWindow();
      this.stopNotificationsIndicatorPolling();
      this.notificationsIndicatorButton = null;
      this.notificationsIndicatorBadge = null;
      this.destroyCurrentProgram();
      this.stopJobsIndicatorPolling();
      this.jobsIndicatorButton = null;
      this.jobsIndicatorBadge = null;
      kendo.destroy(this.root);
      this.root.empty();

      const shell = $("<div class=\"home-shell\"></div>").appendTo(this.root);
      if (this.shouldStartSidebarCollapsed()) {
        shell.addClass("home-sidebar-collapsed");
      }
      this.shell = shell;
      this.renderAppBar(shell);
      this.renderImpersonationBanner(shell);
      this.renderMain(shell);
    }

    renderImpersonationBanner(shell) {
      const impersonation = this.getCurrentImpersonation();
      if (!impersonation || impersonation.enabled !== true) {
        return;
      }
      const targetName = impersonation.targetUserName || impersonation.targetUserId || "usuario";
      const actorName = impersonation.actorUserName || impersonation.actorUserId || "administrador";
      const banner = $("<div class=\"home-impersonation-banner\"></div>").appendTo(shell);
      $("<span></span>")
        .text("Voce esta logado como " + targetName + ". Administrador original: " + actorName + ".")
        .appendTo(banner);
      const stopButton = $("<button type=\"button\"></button>").text("Encerrar simulacao").appendTo(banner);
      stopButton.kendoButton({ icon: "logout" });
      stopButton.on("click", () => this.stopImpersonation());
    }

    getCurrentImpersonation() {
      const fromDefinition = this.definition && this.definition.session && this.definition.session.impersonation;
      if (fromDefinition && fromDefinition.enabled === true) {
        return fromDefinition;
      }
      const stored = global.CrudUtils.readLocalJsonValue("crudEngine.impersonation", null);
      return stored && stored.enabled === true ? stored : null;
    }

    stopImpersonation() {
      if (!this.httpClient || !this.httpClient.authToken) {
        this.restoreOriginalSessionAfterImpersonation();
        return;
      }
      this.httpClient.request({
        url: "/api/auth/impersonate/stop",
        method: "POST",
        data: {}
      }).catch(() => {
        return {};
      }).then(() => {
        this.restoreOriginalSessionAfterImpersonation();
      });
    }

    restoreOriginalSessionAfterImpersonation() {
      const original = global.CrudUtils.readLocalJsonValue("crudEngine.impersonationOriginal", null);
      global.CrudUtils.removeLocalValue("crudEngine.impersonation");
      global.CrudUtils.removeLocalValue("crudEngine.impersonationOriginal");
      if (original && original.authToken) {
        global.CrudUtils.saveLocalValue("crudEngine.authToken", original.authToken);
        global.CrudUtils.saveLocalValue("crudEngine.runtimeSessionId", original.sessionId || "");
        global.CrudUtils.saveLocalValue("crudEngine.runtimeTenantId", original.tenantId || "default");
        global.CrudUtils.saveLocalValue("crudEngine.runtimeUserId", original.userId || "demo");
      } else {
        this.clearLocalSessionContext(true);
      }
      if (global.location) {
        global.location.href = "home.html?screenId=home";
      }
    }

    renderAppBar(shell) {
      const appbar = $("<header class=\"home-appbar\"></header>").appendTo(shell);
      const left = $("<div class=\"home-appbar-left\"></div>").appendTo(appbar);

      this.renderSidebarToggle(left);

      const brand = $("<div class=\"home-brand\"></div>").appendTo(left);
      this.renderBrand(brand);

      const center = $("<div class=\"home-appbar-center\"></div>").appendTo(appbar);
      const titleLine = $("<div class=\"home-program-title-line\"></div>").appendTo(center);
      this.programTitleElement = $("<h1></h1>").text(this.definition.app.title).appendTo(titleLine);
      this.programVersionElement = $("<span class=\"home-program-version k-badge k-badge-solid k-badge-solid-base k-rounded-md\" hidden></span>").appendTo(titleLine);
      this.renderProgramFavoriteButton(titleLine);
      this.programLastUpdatedElement = $("<span class=\"home-last-updated-text\"></span>").appendTo(titleLine);
      const meta = $("<div class=\"home-program-meta\"></div>").appendTo(center);
      this.programSubtitleElement = $("<button type=\"button\" class=\"home-program-subtitle\" hidden></button>").appendTo(meta);
      this.programSubtitleElement.on("click", () => this.openProgramSubtitleTooltip());
      this.renderCurrentSubscriber(meta);

      const actions = $("<div class=\"home-appbar-actions\"></div>").appendTo(appbar);
      this.programActionsElement = $("<div class=\"home-program-actions\"></div>").appendTo(actions);
      this.renderChatButton(actions);
      this.renderSupportButton(actions);
      this.renderAiChatButton(actions);
      this.renderAppbarListButton(actions, "notifications");
      this.renderAppbarListButton(actions, "alerts");
      this.renderAppbarListButton(actions, "requests");
      this.renderAppbarListButton(actions, "jobs");
      if (this.definition.layout.appbar.showRefresh !== false) {
        this.refreshButton = $("<button type=\"button\" class=\"home-icon-button\"></button>")
          .attr("title", "Atualizar")
          .attr("aria-label", "Atualizar")
          .appendTo(actions);
        this.refreshButton.kendoButton({ icon: "arrow-rotate-cw" });
        this.refreshButton.on("click", () => this.refreshProgram());
      }

      if (this.definition.layout.appbar.showThemeSwitch !== false && this.config.theme.allowUserSwitch !== false) {
        this.renderThemeToggle(actions);
      }
      this.renderUserMenu(actions);
    }

    renderChatButton(container) {
      if (!this.shouldShowChatButton()) {
        return;
      }
      const chat = this.getChatConfig();
      const title = chat.buttonTitle || chat.title || "Chat";
      this.chatButton = $("<button type=\"button\" class=\"home-icon-button home-chat-button\"></button>")
        .attr("title", title)
        .attr("aria-label", title)
        .appendTo(container);
      this.chatButton.kendoButton({ icon: chat.icon || "comment" });
      this.chatButton.on("click", () => this.openChatWindow());
    }

    shouldShowChatButton() {
      const chat = this.getChatConfig();
      return chat.enabled === true &&
        this.hasPermission(chat.permission) &&
        Boolean(this.getChatEndpoint(chat, "send")) &&
        this.hasChatRecipientSource(chat);
    }

    getChatConfig() {
      const layout = this.definition && this.definition.layout ? this.definition.layout : {};
      const appbar = layout.appbar || {};
      return appbar.chat || this.definition.chat || {};
    }

    getChatEndpoint(chat, name) {
      const source = chat && chat.endpoints ? chat.endpoints[name] : null;
      const fallback = chat ? chat[name + "Url"] : null;
      const endpoint = source || fallback;
      if (!endpoint) {
        return null;
      }
      if (typeof endpoint === "string") {
        return {
          url: endpoint,
          method: name === "contacts" || name === "events" ? "GET" : "POST"
        };
      }
      if (typeof endpoint === "object" && endpoint.url) {
        return {
          url: endpoint.url,
          method: String(endpoint.method || (name === "contacts" || name === "events" ? "GET" : "POST")).toUpperCase()
        };
      }
      return null;
    }

    hasChatRecipientSource(chat) {
      return Boolean(
        this.getChatEndpoint(chat, "contacts") ||
        global.CrudUtils.ensureArray(chat && (chat.contacts || chat.users || chat.recipients)).length
      );
    }

    openChatWindow() {
      const chat = this.getChatConfig();
      if (!this.shouldShowChatButton()) {
        return;
      }
      if (!$.fn.kendoChat) {
        global.CrudUtils.showMessage("Componente de chat do Kendo UI indisponivel.", "error");
        return;
      }

      if (!this.chatWindowElement) {
        this.createChatWindow(chat);
      }

      this.resizeChatWindow(chat);
      this.chatWindow.center().open();
      if (this.chatWidget && typeof this.chatWidget.scrollToBottom === "function") {
        this.chatWidget.scrollToBottom();
      }
      this.loadChatRecipients(chat);
    }

    createChatWindow(chat) {
      this.chatWindowElement = $("<div class=\"home-chat-window\"></div>").appendTo(document.body);
      this.chatToolbar = $("<div class=\"home-chat-toolbar\"></div>").appendTo(this.chatWindowElement);
      const recipientField = $("<label class=\"home-chat-recipient-field\"></label>").appendTo(this.chatToolbar);
      $("<span></span>").text(chat.recipientLabel || "Conversar com").appendTo(recipientField);
      this.chatRecipientInput = $("<input type=\"text\" class=\"home-chat-recipient-input\">")
        .attr("aria-label", chat.recipientLabel || "Conversar com")
        .appendTo(recipientField);
      this.chatHost = $("<div class=\"home-chat-host\"></div>").appendTo(this.chatWindowElement);
      this.chatWindowElement.kendoWindow({
        title: chat.title || "Chat",
        modal: false,
        actions: ["Maximize", "Close"],
        resizable: true,
        visible: false,
        close: () => {
          this.stopChatEvents();
          if (this.chatButton) {
            this.chatButton.trigger("focus");
          }
        }
      });
      this.chatWindow = this.chatWindowElement.data("kendoWindow");
      this.initializeChatRecipientComboBox(chat);
      this.initializeChatWidget(chat);
    }

    initializeChatRecipientComboBox(chat) {
      const recipientConfig = chat.recipient || {};
      this.chatRecipientInput.kendoComboBox({
        dataSource: [],
        dataTextField: recipientConfig.dataTextField || "name",
        dataValueField: recipientConfig.dataValueField || "id",
        placeholder: recipientConfig.placeholder || "Selecione um usuario",
        clearButton: false,
        filter: "contains",
        suggest: true,
        change: () => this.handleChatRecipientChange(chat)
      });
      this.chatRecipientComboBox = this.chatRecipientInput.data("kendoComboBox");
    }

    initializeChatWidget(chat) {
      const user = this.getCurrentUser();
      const userId = String((chat.user && chat.user.id) || user.id || user.email || user.username || "usuario");
      const bot = chat.bot || {};
      this.chatHost.kendoChat({
        authorId: userId,
        height: "100%",
        dataSource: [],
        autoBind: true,
        showAvatar: false,
        showUsername: true,
        speechToText: false,
        fileAttachment: false,
        messageActions: [],
        fileActions: [],
        messages: this.getChatMessages(chat),
        noDataTemplate: () => "<div class=\"home-chat-empty\">Nenhuma mensagem ainda.</div>",
        sendMessage: (event) => {
          if (event.generating) {
            return;
          }
          const message = event.message || {};
          const text = String(message.text || "").trim();
          if (!text && !global.CrudUtils.ensureArray(message.files).length) {
            return;
          }
          this.sendChatMessage(chat, Object.assign({}, message, { text }), bot);
        }
      });
      this.chatWidget = this.chatHost.data("kendoChat");
      if (chat.welcomeMessage) {
        this.postChatMessages([this.normalizeChatMessage(chat.welcomeMessage, bot)]);
      }
    }

    getChatMessages(chat) {
      return Object.assign({
        messageListLabel: "Lista de mensagens",
        placeholder: chat.placeholder || "Digite uma mensagem...",
        sendButton: "Enviar mensagem",
        sendButtonLoading: "Parar",
        actionButton: "Enviar mensagem",
        actionButtonLoading: "Parar",
        stopButton: "Parar",
        speechToTextButton: "Alternar ditado",
        fileButton: "Anexar arquivo",
        downloadAll: "Baixar todos",
        selfMessageDeleted: "Voce removeu esta mensagem.",
        otherMessageDeleted: "Esta mensagem foi removida.",
        stopGeneration: "Parar geracao",
        messageBoxLabel: "Digite sua mensagem aqui",
        pinnedMessageCloseButton: "Desafixar mensagem",
        replyMessageCloseButton: "Remover resposta",
        fileMenuButton: "Menu do arquivo",
        retryMessage: "Tentar novamente"
      }, chat.messages || {});
    }

    resizeChatWindow(chat) {
      if (!this.chatWindow) {
        return;
      }
      const width = Math.min(Number(chat.width) || 420, Math.max(320, window.innerWidth - 24));
      const height = Math.min(Number(chat.height) || 560, Math.max(360, window.innerHeight - 24));
      this.chatWindow.setOptions({
        width,
        height
      });
      if (this.chatHost) {
        this.chatWindowElement.css("height", Math.max(300, height - 48));
        this.chatHost.css("height", Math.max(240, height - 124));
      }
    }

    loadChatRecipients(chat) {
      if (this.chatRecipients.length) {
        this.bindChatRecipients(chat, this.chatRecipients);
        return;
      }

      const configured = global.CrudUtils.ensureArray(chat.contacts || chat.users || chat.recipients);
      if (configured.length) {
        this.bindChatRecipients(chat, this.normalizeChatRecipients(configured));
        return;
      }

      const endpoint = this.getChatEndpoint(chat, "contacts");
      if (!endpoint) {
        return;
      }
      this.requestChatEndpoint(endpoint, {
        user: this.buildChatUserPayload(),
        context: this.buildChatContextPayload()
      }).then((response) => {
        this.bindChatRecipients(chat, this.normalizeChatRecipients(response));
      }).catch(() => {
        global.CrudUtils.showMessage("Nao foi possivel carregar os usuarios do chat.", "error");
      });
    }

    normalizeChatRecipients(response) {
      const user = this.buildChatUserPayload();
      const source = Array.isArray(response)
        ? response
        : response && (response.contacts || response.users || response.recipients || response.items || response.data);
      return global.CrudUtils.ensureArray(source).map(function(item) {
        const rawItem = item && typeof item.toJSON === "function" ? item.toJSON() : item;
        const sourceItem = typeof rawItem === "string" ? { id: rawItem, name: rawItem } : (rawItem || {});
        const id = String(sourceItem.id || sourceItem.userId || sourceItem.email || "").trim();
        const name = String(sourceItem.name || sourceItem.fullName || sourceItem.username || sourceItem.email || id).trim();
        if (!id || !name || id === String(user.id || "")) {
          return null;
        }
        return {
          id,
          name,
          email: sourceItem.email || "",
          initials: sourceItem.initials || ""
        };
      }).filter(Boolean);
    }

    bindChatRecipients(chat, recipients) {
      this.chatRecipients = recipients || [];
      if (!this.chatRecipientComboBox) {
        return;
      }
      this.chatRecipientComboBox.dataSource.data(this.chatRecipients);
      if (!this.chatRecipients.length) {
        this.currentChatRecipient = null;
        this.clearChatMessages();
        this.chatRecipientComboBox.enable(false);
        global.CrudUtils.showMessage("Nenhum usuario disponivel para conversa.", "info");
        return;
      }
      this.chatRecipientComboBox.enable(true);
      if (!this.currentChatRecipient) {
        const defaultId = String(chat.defaultRecipientId || chat.recipientId || "").trim();
        const selected = this.chatRecipients.find(function(item) {
          return item.id === defaultId;
        }) || this.chatRecipients[0];
        this.chatRecipientComboBox.value(selected.id);
        this.setCurrentChatRecipient(chat, selected);
        return;
      }
      this.chatRecipientComboBox.value(this.currentChatRecipient.id);
    }

    handleChatRecipientChange(chat) {
      if (!this.chatRecipientComboBox) {
        return;
      }
      const selected = this.normalizeChatRecipients([
        this.chatRecipientComboBox.dataItem() || this.findChatRecipientById(this.chatRecipientComboBox.value())
      ])[0];
      if (!selected) {
        this.chatRecipientComboBox.value(this.currentChatRecipient ? this.currentChatRecipient.id : "");
        return;
      }
      this.setCurrentChatRecipient(chat, selected);
    }

    findChatRecipientById(value) {
      const id = String(value || "");
      return this.chatRecipients.find(function(item) {
        return item.id === id;
      }) || null;
    }

    setCurrentChatRecipient(chat, recipient) {
      const nextId = recipient && recipient.id ? String(recipient.id) : "";
      if (!nextId || this.currentChatRecipient && this.currentChatRecipient.id === nextId) {
        return;
      }
      this.stopChatEvents();
      this.currentChatRecipient = recipient;
      this.chatHistoryLoaded = false;
      this.chatHistoryRecipientId = "";
      this.chatLastMessageId = 0;
      this.clearChatMessages();
      this.loadChatHistory(chat);
    }

    clearChatMessages() {
      if (this.chatWidget && this.chatWidget.dataSource && typeof this.chatWidget.dataSource.data === "function") {
        this.chatWidget.dataSource.data([]);
      }
    }

    loadChatHistory(chat) {
      const endpoint = this.getChatEndpoint(chat, "history");
      const recipient = this.buildChatRecipientPayload();
      if (!endpoint || !recipient.id || this.chatHistoryLoaded && this.chatHistoryRecipientId === recipient.id) {
        return;
      }
      this.chatHistoryLoaded = true;
      this.chatHistoryRecipientId = recipient.id;
      this.requestChatEndpoint(endpoint, {
        user: this.buildChatUserPayload(),
        recipient,
        context: this.buildChatContextPayload()
      }).then((response) => {
        const messages = this.normalizeChatResponseMessages(response, chat.bot || {});
        this.postChatMessages(messages);
        this.chatLastMessageId = this.resolveLastChatMessageId(messages, this.chatLastMessageId);
        this.startChatEvents(chat);
      }).catch(() => {
        this.chatHistoryLoaded = false;
        this.chatHistoryRecipientId = "";
        global.CrudUtils.showMessage("Nao foi possivel carregar o historico do chat.", "error");
      });
    }

    sendChatMessage(chat, message, bot) {
      const endpoint = this.getChatEndpoint(chat, "send");
      if (!endpoint) {
        return;
      }
      const recipient = this.buildChatRecipientPayload();
      if (!recipient.id) {
        global.CrudUtils.showMessage("Selecione um usuario para conversar.", "warning");
        return;
      }
      if (this.chatWidget && typeof this.chatWidget.loading === "function") {
        this.chatWidget.loading(true);
      }
      this.requestChatEndpoint(endpoint, {
        message,
        user: this.buildChatUserPayload(),
        recipient,
        context: this.buildChatContextPayload()
      }).then((response) => {
        this.postChatMessages(this.normalizeChatResponseMessages(response, bot || chat.bot || {}));
      }).catch(() => {
        this.postChatMessages([this.normalizeChatMessage({
          text: "Nao foi possivel enviar a mensagem agora."
        }, bot || chat.bot || {})]);
      }).finally(() => {
        if (this.chatWidget && typeof this.chatWidget.loading === "function") {
          this.chatWidget.loading(false);
        }
      });
    }

    requestChatEndpoint(endpoint, data) {
      return this.requestHomeEndpoint(endpoint, data);
    }

    buildChatUserPayload() {
      const user = this.getCurrentUser();
      return {
        id: user.id || user.email || user.username || "",
        name: user.name || user.fullName || user.username || "",
        email: user.email || ""
      };
    }

    buildChatRecipientPayload() {
      const recipient = this.currentChatRecipient || {};
      return {
        id: recipient.id || "",
        name: recipient.name || "",
        email: recipient.email || ""
      };
    }

    buildChatContextPayload() {
      const program = this.buildCurrentProgramPayload();
      return {
        appId: this.definition.app && this.definition.app.id || "",
        appTitle: this.definition.app && this.definition.app.title || "",
        programId: program.id,
        programCode: program.code,
        programTitle: program.title,
        programScreenId: program.screenId,
        programType: program.type,
        moduleId: program.moduleId,
        currentProgram: program
      };
    }

    buildCurrentProgramPayload() {
      const info = this.currentHeaderInfo || {};
      const program = this.currentProgram || {};
      const id = String(program.id || info.id || "").trim();
      const code = String(program.code || program.programCode || info.code || id).trim();
      const title = String(info.title || program.title || "").trim();
      return {
        id,
        code,
        programId: id,
        programCode: code,
        title,
        screenId: String(program.screenId || info.screenId || "").trim(),
        type: String(program.type || info.type || "").trim(),
        moduleId: String(program.moduleId || this.findModuleIdByProgramId(id) || "").trim(),
        version: String(info.version || program.version || program.programVersion || "").trim(),
        subtitle: String(info.subtitle || program.subtitle || program.description || "").trim()
      };
    }

    buildSupportContextPayload() {
      return global.CrudUtils.clone(this.currentSupportContext || this.buildChatContextPayload());
    }

    normalizeChatResponseMessages(response, bot) {
      if (response == null) {
        return [];
      }
      if (typeof response === "string") {
        return [this.normalizeChatMessage({ text: response }, bot)];
      }
      if (Array.isArray(response)) {
        return response.map((item) => this.normalizeChatMessage(item, bot));
      }
      if (Array.isArray(response.messages)) {
        return response.messages.map((item) => this.normalizeChatMessage(item, bot));
      }
      if (response.message) {
        return [this.normalizeChatMessage(response.message, bot)];
      }
      if (response.text) {
        return [this.normalizeChatMessage(response, bot)];
      }
      return [];
    }

    normalizeChatMessage(message, bot) {
      const source = typeof message === "string" ? { text: message } : (message || {});
      const botConfig = bot || {};
      return {
        id: source.id || kendo.guid(),
        text: String(source.text || ""),
        authorId: String(source.authorId || source.senderId || source.userId || botConfig.id || "assistente"),
        authorName: source.authorName || source.senderName || source.userName || botConfig.name || "Assistente",
        timestamp: source.timestamp ? new Date(source.timestamp) : new Date(),
        files: global.CrudUtils.ensureArray(source.files),
        suggestedActions: global.CrudUtils.ensureArray(source.suggestedActions || source.suggestions)
      };
    }

    postChatMessages(messages) {
      if (!this.chatWidget) {
        return;
      }
      global.CrudUtils.ensureArray(messages).forEach((message) => {
        if (message && (message.text || message.files.length)) {
          this.chatWidget.postMessage(message);
        }
      });
    }

    startChatEvents(chat) {
      const endpoint = this.getChatEndpoint(chat, "events");
      const recipient = this.buildChatRecipientPayload();
      if (!endpoint || !endpoint.url || endpoint.method !== "GET" || !recipient.id || typeof global.EventSource !== "function" || global.location && global.location.protocol === "file:") {
        return;
      }
      const url = this.buildEndpointEventUrl(endpoint.url, {
        recipientId: recipient.id,
        afterId: this.chatLastMessageId
      }, this.buildChatContextPayload());
      if (!url) {
        return;
      }
      if (this.chatEventSource && this.chatEventSignature === url) {
        return;
      }
      this.stopChatEvents();

      let source = null;
      try {
        source = new global.EventSource(url);
      } catch (_) {
        return;
      }

      this.chatEventSource = source;
      this.chatEventRecipientId = recipient.id;
      this.chatEventSignature = url;
      source.addEventListener("home-chat-events", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        const messages = this.normalizeChatResponseMessages(payload, chat.bot || {});
        this.postChatMessages(messages);
        this.chatLastMessageId = this.resolveLastChatMessageId(messages, this.chatLastMessageId);
      });
      source.addEventListener("runtime-error", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        this.handleRuntimeEventError(payload.error || {});
      });
      source.onerror = () => {
        if (this.chatEventReconnectTimer) {
          return;
        }
        this.chatEventReconnectTimer = window.setTimeout(() => {
          this.chatEventReconnectTimer = null;
          const chatVisible = this.chatWindowElement && this.chatWindowElement.closest("body").length && this.chatWindowElement.is(":visible");
          if (!chatVisible || !this.currentChatRecipient || this.currentChatRecipient.id !== recipient.id) {
            this.stopChatEvents();
            return;
          }
          this.startChatEvents(chat);
        }, 3000);
      };
    }

    stopChatEvents() {
      if (this.chatEventReconnectTimer) {
        window.clearTimeout(this.chatEventReconnectTimer);
        this.chatEventReconnectTimer = null;
      }
      if (this.chatEventSource) {
        this.chatEventSource.onopen = null;
        this.chatEventSource.onerror = null;
        this.chatEventSource.close();
        this.chatEventSource = null;
      }
      this.chatEventRecipientId = "";
      this.chatEventSignature = "";
    }

    destroyChatWindow() {
      this.stopChatEvents();
      if (this.chatWindow) {
        this.chatWindow.destroy();
      }
      if (this.chatWindowElement) {
        this.chatWindowElement.remove();
      }
      this.chatWindowElement = null;
      this.chatWindow = null;
      this.chatToolbar = null;
      this.chatHost = null;
      this.chatWidget = null;
      this.chatRecipientInput = null;
      this.chatRecipientComboBox = null;
      this.chatRecipients = [];
      this.currentChatRecipient = null;
      this.chatHistoryLoaded = false;
      this.chatHistoryRecipientId = "";
      this.chatLastMessageId = 0;
    }

    renderSupportButton(container) {
      if (!this.shouldShowSupportButton()) {
        return;
      }
      const support = this.getSupportConfig();
      const title = support.buttonTitle || support.title || "Atendimento";
      this.supportButton = $("<button type=\"button\" class=\"home-icon-button home-support-button\"></button>")
        .attr("title", title)
        .attr("aria-label", title)
        .appendTo(container);
      this.supportButton.kendoButton({ icon: support.icon || "headset" });
      this.supportButton.on("click", () => this.openSupportWindow());
    }

    shouldShowSupportButton() {
      const support = this.getSupportConfig();
      return support.enabled === true &&
        this.hasPermission(support.permission) &&
        Boolean(this.getSupportEndpoint(support, "onlineUsers")) &&
        Boolean(this.getSupportEndpoint(support, "createRequest")) &&
        Boolean(this.getSupportEndpoint(support, "send"));
    }

    getSupportConfig() {
      const layout = this.definition && this.definition.layout ? this.definition.layout : {};
      const appbar = layout.appbar || {};
      return appbar.support || this.definition.support || {};
    }

    getSupportEndpoint(support, name) {
      const source = support && support.endpoints ? support.endpoints[name] : null;
      const fallback = support ? support[name + "Url"] : null;
      const endpoint = source || fallback;
      if (!endpoint) {
        return null;
      }
      if (typeof endpoint === "string") {
        return {
          url: endpoint,
          method: name === "onlineUsers" || name === "requestStatus" || name === "events" ? "GET" : "POST"
        };
      }
      if (typeof endpoint === "object" && endpoint.url) {
        return {
          url: endpoint.url,
          method: String(endpoint.method || (name === "onlineUsers" || name === "requestStatus" || name === "events" ? "GET" : "POST")).toUpperCase()
        };
      }
      return null;
    }

    openSupportWindow() {
      const support = this.getSupportConfig();
      if (!this.shouldShowSupportButton()) {
        return;
      }
      if (!$.fn.kendoChat) {
        global.CrudUtils.showMessage("Componente de chat do Kendo UI indisponivel.", "error");
        return;
      }

      this.currentSupportContext = this.buildChatContextPayload();
      if (!this.supportWindowElement) {
        this.createSupportWindow(support);
      }

      this.updateSupportContextView();
      this.resizeSupportWindow(support);
      this.supportWindow.center().open();
      this.loadSupportAvailability(support);
    }

    createSupportWindow(support) {
      this.supportWindowElement = $("<div class=\"home-support-window\"></div>").appendTo(document.body);
      this.supportStatusElement = $("<div class=\"home-support-status\"></div>").appendTo(this.supportWindowElement);
      this.supportContextElement = $("<div class=\"home-support-context\" hidden></div>").appendTo(this.supportWindowElement);
      this.renderSupportOnlineSection(support);
      this.renderSupportRequestSection(support);
      this.supportWindowElement.kendoWindow({
        title: support.title || "Atendimento",
        modal: false,
        actions: ["Maximize", "Close"],
        resizable: true,
        visible: false,
        close: () => {
          this.stopSupportEvents();
          if (this.supportButton) {
            this.supportButton.trigger("focus");
          }
        }
      });
      this.supportWindow = this.supportWindowElement.data("kendoWindow");
      this.initializeSupportChatWidget(support);
    }

    updateSupportContextView() {
      if (!this.supportContextElement) {
        return;
      }
      const context = this.buildSupportContextPayload();
      const program = context.currentProgram || {};
      const title = program.title || context.programTitle || "";
      const code = program.code || context.programCode || context.programId || "";
      if (!title && !code) {
        this.supportContextElement.attr("hidden", true).text("");
        return;
      }
      const text = "Programa: " + (title || code) + (code && code !== title ? " (" + code + ")" : "");
      this.supportContextElement
        .removeAttr("hidden")
        .attr("title", text)
        .text(text);
    }

    renderSupportOnlineSection(support) {
      this.supportOnlineSection = $("<section class=\"home-support-online\" hidden></section>").appendTo(this.supportWindowElement);
      const header = $("<div class=\"home-support-section-header\"></div>").appendTo(this.supportOnlineSection);
      $("<h2></h2>").text(support.onlineTitle || "Atendimento online").appendTo(header);
      $("<p></p>").text(support.onlineDescription || "Selecione o setor desejado. Se houver atendente online no setor, o chat sera aberto.").appendTo(header);
      const tools = $("<div class=\"home-support-online-tools\"></div>").appendTo(this.supportOnlineSection);
      const sectorField = $("<label class=\"home-support-field\"></label>").appendTo(tools);
      $("<span></span>").text(support.sectorLabel || "Setor").appendTo(sectorField);
      this.supportOnlineSectorInput = $("<input type=\"text\" class=\"home-support-online-sector-input\">")
        .attr("aria-label", support.sectorLabel || "Setor")
        .appendTo(sectorField);
      this.supportUserField = $("<label class=\"home-support-field\"></label>").appendTo(tools);
      $("<span></span>").text(support.attendantLabel || "Atendente").appendTo(this.supportUserField);
      this.supportUserInput = $("<input type=\"text\" class=\"home-support-user-input\">")
        .attr("aria-label", support.attendantLabel || "Atendente")
        .appendTo(this.supportUserField);
      this.supportRequestToggleButton = $("<button type=\"button\" class=\"home-support-request-toggle\"></button>")
        .text(support.requestButtonText || "Criar solicitacao")
        .appendTo(tools);
      this.supportRequestToggleButton.kendoButton({ icon: "clipboard" });
      this.supportRequestToggleButton.on("click", () => this.showSupportRequestForm(true, true));
      this.supportChatHost = $("<div class=\"home-support-chat-host home-chat-host\"></div>").appendTo(this.supportOnlineSection);

      this.supportOnlineSectorInput.kendoComboBox({
        dataSource: [],
        dataTextField: "name",
        dataValueField: "id",
        placeholder: support.sectorPlaceholder || "Selecione o setor",
        clearButton: false,
        filter: "contains",
        suggest: true,
        change: () => this.handleSupportSectorChange(support)
      });
      this.supportOnlineSectorComboBox = this.supportOnlineSectorInput.data("kendoComboBox");

      this.supportUserInput.kendoComboBox({
        dataSource: [],
        dataTextField: "name",
        dataValueField: "id",
        placeholder: support.attendantPlaceholder || "Selecione um atendente",
        clearButton: false,
        filter: "contains",
        suggest: true,
        change: () => this.handleSupportUserChange(support)
      });
      this.supportUserComboBox = this.supportUserInput.data("kendoComboBox");
    }

    renderSupportRequestSection(support) {
      this.supportRequestSection = $("<section class=\"home-support-request\" hidden></section>").appendTo(this.supportWindowElement);
      const header = $("<div class=\"home-support-section-header\"></div>").appendTo(this.supportRequestSection);
      $("<h2></h2>").text(support.requestTitle || "Criar solicitacao").appendTo(header);
      $("<p></p>").text(support.requestDescription || "Registre a solicitacao para o setor responsavel assumir posteriormente.").appendTo(header);
      const form = $("<div class=\"home-support-request-form\"></div>").appendTo(this.supportRequestSection);

      const sectorField = $("<label class=\"home-support-field\"></label>").appendTo(form);
      $("<span></span>").text("Setor").appendTo(sectorField);
      this.supportSectorInput = $("<input type=\"text\" class=\"home-support-sector-input\">").attr("aria-label", "Setor").appendTo(sectorField);

      const subjectField = $("<label class=\"home-support-field\"></label>").appendTo(form);
      $("<span></span>").text("Assunto").appendTo(subjectField);
      this.supportSubjectInput = $("<input type=\"text\" class=\"home-support-subject-input\">").attr("aria-label", "Assunto").appendTo(subjectField);

      const descriptionField = $("<label class=\"home-support-field\"></label>").appendTo(form);
      $("<span></span>").text("Descricao").appendTo(descriptionField);
      this.supportDescriptionInput = $("<textarea class=\"home-support-description-input\" rows=\"5\"></textarea>")
        .attr("aria-label", "Descricao")
        .appendTo(descriptionField);

      const priorityField = $("<label class=\"home-support-field\"></label>").appendTo(form);
      $("<span></span>").text("Prioridade").appendTo(priorityField);
      this.supportPriorityInput = $("<input type=\"text\" class=\"home-support-priority-input\">").attr("aria-label", "Prioridade").appendTo(priorityField);

      const actions = $("<div class=\"home-support-actions\"></div>").appendTo(this.supportRequestSection);
      const submitButton = $("<button type=\"button\"></button>").text("Enviar solicitacao").appendTo(actions);
      submitButton.kendoButton({ icon: "paper-plane", themeColor: "primary" });
      submitButton.on("click", () => this.createSupportRequest(support));
      const backButton = $("<button type=\"button\"></button>").text("Voltar ao atendimento").appendTo(actions);
      backButton.kendoButton();
      backButton.on("click", () => this.showSupportSectorPicker());
      this.supportRequestBackButton = backButton;

      this.initializeSupportRequestControls(support);
    }

    initializeSupportRequestControls(support) {
      this.supportSectorInput.kendoComboBox({
        dataSource: [],
        dataTextField: "name",
        dataValueField: "id",
        placeholder: "Selecione o setor",
        clearButton: false,
        filter: "contains",
        suggest: true
      });
      this.supportSectorComboBox = this.supportSectorInput.data("kendoComboBox");
      if ($.fn.kendoTextBox) {
        this.supportSubjectInput.kendoTextBox();
      }
      if ($.fn.kendoTextArea) {
        this.supportDescriptionInput.kendoTextArea({
          rows: 5,
          resize: "vertical"
        });
      }
      const priorities = global.CrudUtils.ensureArray(support.priorities);
      const priorityData = priorities.length ? priorities : [
        { id: "normal", name: "Normal" },
        { id: "alta", name: "Alta" },
        { id: "baixa", name: "Baixa" }
      ];
      this.supportPriorityInput.kendoDropDownList({
        dataSource: priorityData,
        dataTextField: "name",
        dataValueField: "id",
        value: support.defaultPriority || "normal"
      });
    }

    initializeSupportChatWidget(support) {
      const user = this.getCurrentUser();
      const userId = String(user.id || user.email || user.username || "usuario");
      this.supportChatHost.kendoChat({
        authorId: userId,
        height: "100%",
        dataSource: [],
        autoBind: true,
        showAvatar: false,
        showUsername: true,
        speechToText: false,
        fileAttachment: false,
        messageActions: [],
        fileActions: [],
        messages: this.getChatMessages(Object.assign({
          placeholder: "Digite sua mensagem..."
        }, support.messages || {})),
        noDataTemplate: () => "<div class=\"home-chat-empty\">Nenhuma mensagem ainda.</div>",
        sendMessage: (event) => {
          if (event.generating) {
            return;
          }
          const message = event.message || {};
          const text = String(message.text || "").trim();
          if (!text && !global.CrudUtils.ensureArray(message.files).length) {
            return;
          }
          this.sendSupportMessage(support, Object.assign({}, message, { text }));
        }
      });
      this.supportChatWidget = this.supportChatHost.data("kendoChat");
    }

    resizeSupportWindow(support) {
      if (!this.supportWindow) {
        return;
      }
      const width = Math.min(Number(support.width) || 520, Math.max(320, window.innerWidth - 24));
      const height = Math.min(Number(support.height) || 620, Math.max(380, window.innerHeight - 24));
      this.supportWindow.setOptions({
        width,
        height
      });
      if (this.supportChatHost) {
        this.supportChatHost.css("height", Math.max(220, height - 230));
      }
    }

    loadSupportAvailability(support) {
      const endpoint = this.getSupportEndpoint(support, "onlineUsers");
      if (!endpoint) {
        return;
      }
      this.stopSupportEvents();
      this.setSupportStatus("Consultando atendentes online...", "info");
      this.supportOnlineSection.attr("hidden", true);
      this.supportRequestSection.attr("hidden", true);
      this.requestSupportEndpoint(endpoint, {
        user: this.buildChatUserPayload(),
        context: this.buildSupportContextPayload()
      }).then((response) => {
        this.bindSupportAvailability(support, response);
        this.loadSupportRequestStatus(support);
        this.startSupportEvents(support);
      }).catch(() => {
        this.setSupportStatus("Nao foi possivel consultar atendentes. Registre uma solicitacao.", "warning");
        this.bindSupportAvailability(support, { onlineUsers: [], sectors: support.sectors || [] });
      });
    }

    bindSupportAvailability(support, response) {
      this.supportOnlineUsers = this.normalizeSupportUsers(response);
      this.supportSectors = this.normalizeSupportSectors(response, support);
      this.bindSupportSectors(support);
      this.showSupportSectorState(support);
    }

    normalizeSupportUsers(response) {
      const user = this.buildChatUserPayload();
      const source = Array.isArray(response)
        ? response
        : response && (response.onlineUsers || response.users || response.attendants || response.items || response.data);
      return global.CrudUtils.ensureArray(source).map(function(item) {
        const rawItem = item && typeof item.toJSON === "function" ? item.toJSON() : item;
        const sourceItem = typeof rawItem === "string" ? { id: rawItem, name: rawItem } : (rawItem || {});
        const id = String(sourceItem.id || sourceItem.userId || sourceItem.email || "").trim();
        const name = String(sourceItem.name || sourceItem.fullName || sourceItem.username || sourceItem.email || id).trim();
        if (!id || !name || id === String(user.id || "")) {
          return null;
        }
        return {
          id,
          name,
          email: sourceItem.email || "",
          sectorId: sourceItem.sectorId || "",
          sectorName: sourceItem.sectorName || sourceItem.sector || "",
          status: sourceItem.status || "online"
        };
      }).filter(Boolean);
    }

    normalizeSupportSectors(response, support) {
      const configured = global.CrudUtils.ensureArray(support.sectors);
      const source = response && (response.sectors || response.departments) || configured;
      const sectors = global.CrudUtils.ensureArray(source).map(function(item) {
        const rawItem = item && typeof item.toJSON === "function" ? item.toJSON() : item;
        const sourceItem = typeof rawItem === "string" ? { id: rawItem, name: rawItem } : (rawItem || {});
        const id = String(sourceItem.id || sourceItem.sectorId || sourceItem.name || "").trim();
        const name = String(sourceItem.name || sourceItem.title || id).trim();
        return id && name ? { id, name } : null;
      }).filter(Boolean);
      if (sectors.length) {
        return sectors;
      }
      return [{ id: support.fallbackRequest && support.fallbackRequest.defaultSectorId || "suporte", name: "Suporte" }];
    }

    bindSupportSectors(support) {
      if (!this.supportSectorComboBox || !this.supportOnlineSectorComboBox) {
        return;
      }
      this.supportOnlineSectorComboBox.dataSource.data(this.supportSectors);
      this.supportSectorComboBox.dataSource.data(this.supportSectors);
      const defaultSectorId = String(support.fallbackRequest && support.fallbackRequest.defaultSectorId || "").trim();
      const selected = this.supportSectors.find((item) => {
        return item.id === this.currentSupportSectorId;
      }) || this.supportSectors.find(function(item) {
        return item.id === defaultSectorId;
      }) || this.supportSectors[0];
      if (selected) {
        this.currentSupportSectorId = selected.id;
        this.supportOnlineSectorComboBox.value(selected.id);
        this.supportSectorComboBox.value(selected.id);
      }
    }

    showSupportSectorState(support) {
      const sector = this.getSelectedSupportSector();
      const users = this.getSupportUsersForSector(sector.id);
      if (!users.length) {
        this.currentSupportUser = null;
        this.clearSupportChatMessages();
        this.setSupportStatus("Nenhum atendente online no setor " + (sector.name || "selecionado") + ". Crie uma solicitacao para o setor.", "warning");
        this.showSupportRequestForm(true, true);
        return;
      }
      this.showSupportOnlineState(support, users, sector);
    }

    showSupportOnlineState(support, users, sector) {
      const availableUsers = users || this.getSupportUsersForSector(this.getSelectedSupportSector().id);
      if (!availableUsers.length) {
        this.showSupportSectorState(support || this.getSupportConfig());
        return;
      }
      this.supportOnlineSection.removeAttr("hidden");
      this.supportRequestSection.attr("hidden", true);
      this.toggleSupportOnlineChat(true);
      this.setSupportStatus("Atendente online disponivel no setor " + ((sector && sector.name) || this.getSelectedSupportSector().name || "selecionado") + ".", "success");
      this.supportUserComboBox.dataSource.data(availableUsers);
      const currentId = this.currentSupportUser && this.currentSupportUser.id;
      const selected = availableUsers.find(function(item) {
        return item.id === currentId;
      }) || availableUsers[0];
      this.supportUserComboBox.value(selected.id);
      this.setCurrentSupportUser(support || this.getSupportConfig(), selected);
    }

    showSupportRequestForm(canReturn, lockSector) {
      this.supportOnlineSection.attr("hidden", true);
      this.syncSupportRequestSector(Boolean(lockSector));
      this.supportRequestSection.removeAttr("hidden");
      if (this.supportRequestBackButton) {
        this.supportRequestBackButton.toggle(Boolean(canReturn && this.supportSectors.length));
      }
    }

    showSupportSectorPicker() {
      this.supportOnlineSection.removeAttr("hidden");
      this.supportRequestSection.attr("hidden", true);
      this.toggleSupportOnlineChat(false);
      this.setSupportStatus("Selecione outro setor para verificar atendimento online.", "info");
    }

    toggleSupportOnlineChat(visible) {
      const method = visible ? "show" : "hide";
      if (this.supportUserField) {
        this.supportUserField[method]();
      }
      if (this.supportRequestToggleButton) {
        this.supportRequestToggleButton[method]();
      }
      if (this.supportChatHost) {
        this.supportChatHost[method]();
      }
    }

    handleSupportSectorChange(support) {
      const sector = this.getSelectedSupportSector();
      if (!sector.id) {
        return;
      }
      this.stopSupportEvents();
      this.currentSupportSectorId = sector.id;
      this.currentSupportUser = null;
      this.supportHistoryLoadedUserId = "";
      this.supportLastMessageId = 0;
      this.clearSupportChatMessages();
      this.syncSupportRequestSector(false);
      this.showSupportSectorState(support);
      this.loadSupportRequestStatus(support);
      this.startSupportEvents(support);
    }

    getSelectedSupportSector() {
      const widget = this.supportOnlineSectorComboBox || this.supportSectorComboBox;
      const dataItem = widget && widget.dataItem ? widget.dataItem() : null;
      const source = dataItem && typeof dataItem.toJSON === "function" ? dataItem.toJSON() : dataItem || {};
      const id = String(source.id || widget && widget.value && widget.value() || this.currentSupportSectorId || "").trim();
      const sector = this.supportSectors.find(function(item) {
        return item.id === id;
      }) || source || {};
      return {
        id: String(sector.id || id || "").trim(),
        name: String(sector.name || sector.title || widget && widget.text && widget.text() || id || "").trim()
      };
    }

    getSupportUsersForSector(sectorId) {
      const id = String(sectorId || "");
      return this.supportOnlineUsers.filter(function(user) {
        return !id || String(user.sectorId || "") === id;
      });
    }

    syncSupportRequestSector(lockSector) {
      if (!this.supportSectorComboBox) {
        return;
      }
      const sector = this.getSelectedSupportSector();
      if (sector.id) {
        this.supportSectorComboBox.value(sector.id);
      }
      this.supportSectorComboBox.enable(!lockSector);
    }

    handleSupportUserChange(support) {
      if (!this.supportUserComboBox) {
        return;
      }
      const selected = this.normalizeSupportUsers([
        this.supportUserComboBox.dataItem() || this.findSupportUserById(this.supportUserComboBox.value())
      ])[0];
      if (!selected) {
        this.supportUserComboBox.value(this.currentSupportUser ? this.currentSupportUser.id : "");
        return;
      }
      this.setCurrentSupportUser(support, selected);
    }

    findSupportUserById(value) {
      const id = String(value || "");
      return this.supportOnlineUsers.find(function(item) {
        return item.id === id;
      }) || null;
    }

    setCurrentSupportUser(support, user) {
      const nextId = user && user.id ? String(user.id) : "";
      if (!nextId || this.currentSupportUser && this.currentSupportUser.id === nextId) {
        return;
      }
      this.stopSupportEvents();
      this.currentSupportUser = user;
      this.supportHistoryLoadedUserId = "";
      this.supportLastMessageId = 0;
      this.clearSupportChatMessages();
      this.loadSupportChatHistory(support);
    }

    loadSupportChatHistory(support) {
      const endpoint = this.getSupportEndpoint(support, "history");
      const attendant = this.buildSupportAttendantPayload();
      if (!endpoint || !attendant.id || this.supportHistoryLoadedUserId === attendant.id) {
        return;
      }
      this.supportHistoryLoadedUserId = attendant.id;
      this.requestSupportEndpoint(endpoint, {
        user: this.buildChatUserPayload(),
        attendant,
        context: this.buildSupportContextPayload()
      }).then((response) => {
        const messages = this.normalizeChatResponseMessages(response, {
          id: attendant.id,
          name: attendant.name || "Atendente"
        });
        this.postSupportChatMessages(messages);
        this.supportLastMessageId = this.resolveLastChatMessageId(messages, this.supportLastMessageId);
        this.startSupportEvents(support);
      }).catch(() => {
        this.supportHistoryLoadedUserId = "";
        global.CrudUtils.showMessage("Nao foi possivel carregar o historico do atendimento.", "error");
      });
    }

    sendSupportMessage(support, message) {
      const endpoint = this.getSupportEndpoint(support, "send");
      const attendant = this.buildSupportAttendantPayload();
      if (!endpoint || !attendant.id) {
        global.CrudUtils.showMessage("Selecione um atendente online.", "warning");
        return;
      }
      if (this.supportChatWidget && typeof this.supportChatWidget.loading === "function") {
        this.supportChatWidget.loading(true);
      }
      this.requestSupportEndpoint(endpoint, {
        message,
        user: this.buildChatUserPayload(),
        attendant,
        context: this.buildSupportContextPayload()
      }).then((response) => {
        this.postSupportChatMessages(this.normalizeChatResponseMessages(response, {
          id: attendant.id,
          name: attendant.name || "Atendente"
        }));
      }).catch(() => {
        this.postSupportChatMessages([this.normalizeChatMessage({
          text: "Nao foi possivel enviar a mensagem ao atendimento."
        }, { id: attendant.id || "atendimento", name: attendant.name || "Atendimento" })]);
      }).finally(() => {
        if (this.supportChatWidget && typeof this.supportChatWidget.loading === "function") {
          this.supportChatWidget.loading(false);
        }
      });
    }

    createSupportRequest(support) {
      const endpoint = this.getSupportEndpoint(support, "createRequest");
      if (!endpoint) {
        return;
      }
      const sector = this.buildSupportSectorPayload();
      const priorityWidget = this.supportPriorityInput && this.supportPriorityInput.data("kendoDropDownList");
      const subject = String(this.supportSubjectInput && this.supportSubjectInput.val() || "").trim();
      const description = String(this.supportDescriptionInput && this.supportDescriptionInput.val() || "").trim();
      const priority = priorityWidget ? priorityWidget.value() : String(this.supportPriorityInput && this.supportPriorityInput.val() || "normal");
      if (!sector.id) {
        global.CrudUtils.showMessage("Selecione o setor da solicitacao.", "warning");
        return;
      }
      if (!subject || !description) {
        global.CrudUtils.showMessage("Informe assunto e descricao da solicitacao.", "warning");
        return;
      }
      this.requestSupportEndpoint(endpoint, {
        sector,
        priority,
        subject,
        description,
        user: this.buildChatUserPayload(),
        context: this.buildSupportContextPayload()
      }).then((response) => {
        const protocol = response && (response.protocol || response.id || response.requestId);
        this.supportCurrentProtocol = String(protocol || "");
        this.setSupportStatus(protocol ? "Solicitacao criada: " + protocol + "." : "Solicitacao criada.", "success");
        global.CrudUtils.showMessage("Solicitacao enviada para o setor.", "success");
        this.supportSubjectInput.val("");
        this.supportDescriptionInput.val("");
        this.startSupportEvents(support);
      }).catch(() => {
        global.CrudUtils.showMessage("Nao foi possivel criar a solicitacao.", "error");
      });
    }

    buildSupportAttendantPayload() {
      const user = this.currentSupportUser || {};
      return {
        id: user.id || "",
        name: user.name || "",
        email: user.email || "",
        sectorId: user.sectorId || "",
        sectorName: user.sectorName || ""
      };
    }

    buildSupportSectorPayload() {
      if (!this.supportSectorComboBox) {
        return { id: "", name: "" };
      }
      const dataItem = this.supportSectorComboBox.dataItem();
      const source = dataItem && typeof dataItem.toJSON === "function" ? dataItem.toJSON() : dataItem || {};
      return {
        id: source.id || this.supportSectorComboBox.value() || "",
        name: source.name || this.supportSectorComboBox.text() || ""
      };
    }

    requestSupportEndpoint(endpoint, data) {
      return this.requestHomeEndpoint(endpoint, data);
    }

    loadSupportRequestStatus(support) {
      const endpoint = this.getSupportEndpoint(support, "requestStatus");
      if (!endpoint || !endpoint.url) {
        return;
      }
      this.requestSupportEndpoint(endpoint, {
        protocol: this.supportCurrentProtocol || "",
        context: this.buildSupportContextPayload()
      }).then((response) => {
        this.applySupportRequestStatusResponse(response);
      }).catch(() => {
        return false;
      });
    }

    startSupportEvents(support) {
      const endpoint = this.getSupportEndpoint(support, "events");
      const attendant = this.buildSupportAttendantPayload();
      const sector = this.buildSupportSectorPayload();
      if (!endpoint || !endpoint.url || endpoint.method !== "GET" || typeof global.EventSource !== "function" || global.location && global.location.protocol === "file:") {
        return;
      }
      const url = this.buildEndpointEventUrl(endpoint.url, {
        attendantId: attendant.id || "",
        sectorId: sector.id || "",
        protocol: this.supportCurrentProtocol || "",
        afterId: this.supportLastMessageId
      }, this.buildSupportContextPayload());
      if (!url) {
        return;
      }
      if (this.supportEventSource && this.supportEventSignature === url) {
        return;
      }
      this.stopSupportEvents();

      let source = null;
      try {
        source = new global.EventSource(url);
      } catch (_) {
        return;
      }

      this.supportEventSource = source;
      this.supportEventAttendantId = String(attendant.id || "");
      this.supportEventSignature = url;
      source.addEventListener("home-support-events", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        if (payload.onlineUsers || payload.sectors) {
          this.bindSupportAvailability(support, payload);
        }
        if (payload.requestStatus) {
          this.applySupportRequestStatusResponse(payload.requestStatus);
        }
        const messages = this.normalizeChatResponseMessages(payload, {
          id: attendant.id || "atendimento",
          name: attendant.name || "Atendimento"
        });
        this.postSupportChatMessages(messages);
        this.supportLastMessageId = this.resolveLastChatMessageId(messages, this.supportLastMessageId);
      });
      source.addEventListener("runtime-error", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        this.handleRuntimeEventError(payload.error || {});
      });
      source.onerror = () => {
        if (this.supportEventReconnectTimer) {
          return;
        }
        this.supportEventReconnectTimer = window.setTimeout(() => {
          this.supportEventReconnectTimer = null;
          const supportVisible = this.supportWindowElement && this.supportWindowElement.closest("body").length && this.supportWindowElement.is(":visible");
          if (!supportVisible) {
            this.stopSupportEvents();
            return;
          }
          this.startSupportEvents(support);
        }, 3000);
      };
    }

    stopSupportEvents() {
      if (this.supportEventReconnectTimer) {
        window.clearTimeout(this.supportEventReconnectTimer);
        this.supportEventReconnectTimer = null;
      }
      if (this.supportEventSource) {
        this.supportEventSource.onopen = null;
        this.supportEventSource.onerror = null;
        this.supportEventSource.close();
        this.supportEventSource = null;
      }
      this.supportEventAttendantId = "";
      this.supportEventSignature = "";
    }

    postSupportChatMessages(messages) {
      if (!this.supportChatWidget) {
        return;
      }
      global.CrudUtils.ensureArray(messages).forEach((message) => {
        if (message && (message.text || message.files.length)) {
          this.supportChatWidget.postMessage(message);
        }
      });
    }

    clearSupportChatMessages() {
      if (this.supportChatWidget && this.supportChatWidget.dataSource && typeof this.supportChatWidget.dataSource.data === "function") {
        this.supportChatWidget.dataSource.data([]);
      }
    }

    setSupportStatus(message, type) {
      if (!this.supportStatusElement) {
        return;
      }
      this.supportStatusElement
        .removeClass("is-success is-warning is-error is-info")
        .addClass("is-" + (type || "info"))
        .text(message || "");
    }

    applySupportRequestStatusResponse(response) {
      const source = response || {};
      const protocol = String(source.protocol || "").trim();
      if (protocol) {
        this.supportCurrentProtocol = protocol;
      }
      const status = String(source.status || "").trim();
      if (!status || status === "none") {
        return;
      }
      const assignedTo = source.assignedTo && source.assignedTo.name ? " - " + source.assignedTo.name : "";
      const labels = {
        open: "Solicitacao aberta",
        assigned: "Solicitacao em atendimento",
        read: "Solicitacao lida",
        closed: "Solicitacao encerrada"
      };
      this.setSupportStatus((labels[status] || "Solicitacao atualizada") + (protocol ? ": " + protocol : "") + assignedTo + ".", status === "closed" ? "info" : "success");
    }

    destroySupportWindow() {
      this.stopSupportEvents();
      if (this.supportWindow) {
        this.supportWindow.destroy();
      }
      if (this.supportWindowElement) {
        this.supportWindowElement.remove();
      }
      this.supportWindowElement = null;
      this.supportWindow = null;
      this.supportStatusElement = null;
      this.supportOnlineSectorInput = null;
      this.supportOnlineSectorComboBox = null;
      this.supportUserInput = null;
      this.supportUserComboBox = null;
      this.supportUserField = null;
      this.supportSectorInput = null;
      this.supportSectorComboBox = null;
      this.supportSubjectInput = null;
      this.supportDescriptionInput = null;
      this.supportPriorityInput = null;
      this.supportOnlineSection = null;
      this.supportRequestSection = null;
      this.supportRequestBackButton = null;
      this.supportChatHost = null;
      this.supportChatWidget = null;
      this.supportContextElement = null;
      this.supportOnlineUsers = [];
      this.supportSectors = [];
      this.currentSupportUser = null;
      this.currentSupportSectorId = "";
      this.currentSupportContext = null;
      this.supportHistoryLoadedUserId = "";
      this.supportLastMessageId = 0;
      this.supportCurrentProtocol = "";
    }

    renderAiChatButton(container) {
      if (!this.shouldShowAiChatButton()) {
        return;
      }
      const chat = this.getAiChatConfig();
      const title = chat.buttonTitle || chat.title || "Chat de IA";
      this.aiChatButton = $("<button type=\"button\" class=\"home-icon-button home-ai-chat-button\"></button>")
        .attr("title", title)
        .attr("aria-label", title)
        .appendTo(container);
      this.aiChatButton.kendoButton({ icon: chat.icon || "sparkles" });
      this.aiChatButton.on("click", () => this.openAiChatWindow());
    }

    shouldShowAiChatButton() {
      const chat = this.getAiChatConfig();
      return chat.enabled === true &&
        this.hasPermission(chat.permission) &&
        Boolean(this.getAiChatEndpoint(chat, "send"));
    }

    getAiChatConfig() {
      const layout = this.definition && this.definition.layout ? this.definition.layout : {};
      const appbar = layout.appbar || {};
      return appbar.aiChat || appbar.iaChat || this.definition.aiChat || this.definition.iaChat || {};
    }

    getAiChatEndpoint(chat, name) {
      const source = chat && chat.endpoints ? chat.endpoints[name] : null;
      const fallback = chat ? chat[name + "Url"] : null;
      const endpoint = source || fallback;
      if (!endpoint) {
        return null;
      }
      if (typeof endpoint === "string") {
        return {
          url: endpoint,
          method: name === "history" ? "GET" : "POST"
        };
      }
      if (typeof endpoint === "object" && endpoint.url) {
        return {
          url: endpoint.url,
          method: String(endpoint.method || (name === "history" ? "GET" : "POST")).toUpperCase()
        };
      }
      return null;
    }

    openAiChatWindow() {
      const chat = this.getAiChatConfig();
      if (!this.shouldShowAiChatButton()) {
        return;
      }
      if (!$.fn.kendoChat) {
        global.CrudUtils.showMessage("Componente de chat do Kendo UI indisponivel.", "error");
        return;
      }

      if (!this.aiChatWindowElement) {
        this.createAiChatWindow(chat);
      }

      this.resizeAiChatWindow(chat);
      this.aiChatWindow.center().open();
      if (this.aiChatWidget && typeof this.aiChatWidget.scrollToBottom === "function") {
        this.aiChatWidget.scrollToBottom();
      }
      this.loadAiChatHistory(chat);
    }

    createAiChatWindow(chat) {
      this.aiChatWindowElement = $("<div class=\"home-ai-chat-window\"></div>").appendTo(document.body);
      this.aiChatHost = $("<div class=\"home-ai-chat-host home-chat-host\"></div>").appendTo(this.aiChatWindowElement);
      this.aiChatWindowElement.kendoWindow({
        title: chat.title || "Chat de IA",
        modal: false,
        actions: ["Maximize", "Close"],
        resizable: true,
        visible: false,
        close: () => {
          if (this.aiChatButton) {
            this.aiChatButton.trigger("focus");
          }
        }
      });
      this.aiChatWindow = this.aiChatWindowElement.data("kendoWindow");
      this.initializeAiChatWidget(chat);
    }

    initializeAiChatWidget(chat) {
      const user = this.getCurrentUser();
      const userId = String((chat.user && chat.user.id) || user.id || user.email || user.username || "usuario");
      const bot = this.getAiChatBot(chat);
      this.aiChatHost.kendoChat({
        authorId: userId,
        height: "100%",
        dataSource: [],
        autoBind: true,
        showAvatar: false,
        showUsername: true,
        speechToText: false,
        fileAttachment: false,
        messageActions: [],
        fileActions: [],
        messages: this.getChatMessages(Object.assign({
          placeholder: "Digite sua duvida ou acao..."
        }, chat)),
        noDataTemplate: () => "<div class=\"home-chat-empty\">Nenhuma mensagem ainda.</div>",
        sendMessage: (event) => {
          if (event.generating) {
            return;
          }
          const message = event.message || {};
          const text = String(message.text || "").trim();
          if (!text && !global.CrudUtils.ensureArray(message.files).length) {
            return;
          }
          this.sendAiChatMessage(chat, Object.assign({}, message, { text }), bot);
        }
      });
      this.aiChatWidget = this.aiChatHost.data("kendoChat");
      if (chat.welcomeMessage) {
        this.postAiChatMessages([this.normalizeChatMessage(chat.welcomeMessage, bot)]);
      }
    }

    resizeAiChatWindow(chat) {
      if (!this.aiChatWindow) {
        return;
      }
      const width = Math.min(Number(chat.width) || 460, Math.max(320, window.innerWidth - 24));
      const height = Math.min(Number(chat.height) || 560, Math.max(360, window.innerHeight - 24));
      this.aiChatWindow.setOptions({
        width,
        height
      });
      if (this.aiChatHost) {
        this.aiChatHost.css("height", Math.max(260, height - 48));
      }
    }

    loadAiChatHistory(chat) {
      const endpoint = this.getAiChatEndpoint(chat, "history");
      if (!endpoint || this.aiChatHistoryLoaded) {
        return;
      }
      this.aiChatHistoryLoaded = true;
      this.requestAiChatEndpoint(endpoint, {
        user: this.buildChatUserPayload(),
        context: this.buildChatContextPayload()
      }).then((response) => {
        this.postAiChatMessages(this.normalizeChatResponseMessages(response, this.getAiChatBot(chat)));
      }).catch(() => {
        this.aiChatHistoryLoaded = false;
        global.CrudUtils.showMessage("Nao foi possivel carregar o historico do chat de IA.", "error");
      });
    }

    sendAiChatMessage(chat, message, bot) {
      const endpoint = this.getAiChatEndpoint(chat, "send");
      if (!endpoint) {
        return;
      }
      if (this.aiChatWidget && typeof this.aiChatWidget.loading === "function") {
        this.aiChatWidget.loading(true);
      }
      this.requestAiChatEndpoint(endpoint, {
        message,
        user: this.buildChatUserPayload(),
        context: this.buildChatContextPayload()
      }).then((response) => {
        this.postAiChatMessages(this.normalizeChatResponseMessages(response, bot || this.getAiChatBot(chat)));
      }).catch(() => {
        this.postAiChatMessages([this.normalizeChatMessage({
          text: "Nao foi possivel acionar a IA agora."
        }, bot || this.getAiChatBot(chat))]);
      }).finally(() => {
        if (this.aiChatWidget && typeof this.aiChatWidget.loading === "function") {
          this.aiChatWidget.loading(false);
        }
      });
    }

    requestAiChatEndpoint(endpoint, data) {
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data
      });
    }

    getAiChatBot(chat) {
      return Object.assign({ id: "ia", name: "IA" }, chat && chat.bot || {});
    }

    postAiChatMessages(messages) {
      if (!this.aiChatWidget) {
        return;
      }
      global.CrudUtils.ensureArray(messages).forEach((message) => {
        if (message && (message.text || message.files.length)) {
          this.aiChatWidget.postMessage(message);
        }
      });
    }

    destroyAiChatWindow() {
      if (this.aiChatWindow) {
        this.aiChatWindow.destroy();
      }
      if (this.aiChatWindowElement) {
        this.aiChatWindowElement.remove();
      }
      this.aiChatWindowElement = null;
      this.aiChatWindow = null;
      this.aiChatHost = null;
      this.aiChatWidget = null;
      this.aiChatHistoryLoaded = false;
    }

    renderAppbarListButton(container, kind) {
      if (!this.shouldShowAppbarListButton(kind)) {
        return;
      }
      const config = this.getAppbarListConfig(kind);
      const defaults = this.getAppbarListDefaults(kind);
      const title = config.buttonTitle || config.title || defaults.title;
      const wrapper = $("<span class=\"home-appbar-list-button-wrap\"></span>").appendTo(container);
      const button = $("<button type=\"button\" class=\"home-icon-button home-appbar-list-button\"></button>")
        .attr("title", title)
        .attr("aria-label", title)
        .appendTo(wrapper);
      button.kendoButton({ icon: config.icon || defaults.icon });
      button.on("click", () => this.handleAppbarListButtonClick(kind, button));
      if (kind === "notifications" || kind === "jobs") {
        const badge = $("<span class=\"home-appbar-count-badge\" hidden></span>").text("0").appendTo(wrapper);
        if (kind === "notifications") {
          this.notificationsIndicatorButton = button;
          this.notificationsIndicatorBadge = badge;
          this.startNotificationsIndicatorPolling();
        } else {
          this.jobsIndicatorButton = button;
          this.jobsIndicatorBadge = badge;
          this.startJobsIndicatorPolling();
        }
      }
    }

    shouldShowAppbarListButton(kind) {
      const config = this.getAppbarListConfig(kind);
      if (config.enabled !== true || !this.hasPermission(config.permission)) {
        return false;
      }
      if (kind === "notifications") {
        return Boolean(this.getAppbarListEndpoint(config)) || this.hasAppbarNotificationSources();
      }
      return Boolean(this.getAppbarListEndpoint(config)) || Boolean(kind === "jobs" && config.programId);
    }

    handleAppbarListButtonClick(kind, button) {
      const config = this.getAppbarListConfig(kind);
      const endpoint = this.getAppbarListEndpoint(config);
      if (!endpoint && kind === "jobs" && config.programId) {
        this.setJobsIndicatorCount(0);
        this.openProgram(config.programId);
        return;
      }
      this.openAppbarListWindow(kind, button);
    }

    getAppbarListDefaults(kind) {
      if (kind === "jobs") {
        return {
          title: "Jobs",
          emptyText: "Nenhum job concluido.",
          icon: "clock"
        };
      }
      if (kind === "notifications") {
        return {
          title: "Notificacoes",
          emptyText: "Nenhuma notificacao encontrada.",
          icon: "bell"
        };
      }
      if (kind === "requests") {
        return {
          title: "Solicitacoes",
          emptyText: "Nenhuma solicitacao recebida.",
          icon: "inbox"
        };
      }
      return {
        title: "Alertas",
        emptyText: "Nenhum alerta recebido.",
        icon: "bell"
      };
    }

    getAppbarListConfig(kind) {
      const layout = this.definition && this.definition.layout ? this.definition.layout : {};
      const appbar = layout.appbar || {};
      return appbar[kind] || this.definition[kind] || {};
    }

    getAppbarListEndpoint(config) {
      return this.getAppbarListActionEndpoint(config, "list", config && (config.listUrl || config.url));
    }

    getAppbarListActionEndpoint(config, key, fallback) {
      const source = config && config.endpoints ? config.endpoints[key] : null;
      const endpoint = source || fallback;
      if (!endpoint) {
        return null;
      }
      if (typeof endpoint === "string") {
        return {
          url: endpoint,
          method: "GET"
        };
      }
      if (typeof endpoint === "object" && endpoint.url) {
        return {
          url: endpoint.url,
          method: String(endpoint.method || "GET").toUpperCase()
        };
      }
      return null;
    }

    openAppbarListWindow(kind, sourceButton) {
      const config = this.getAppbarListConfig(kind);
      const defaults = this.getAppbarListDefaults(kind);
      const endpoint = this.getAppbarListEndpoint(config);
      if (!endpoint && !(kind === "notifications" && this.hasAppbarNotificationSources())) {
        return;
      }

      this.destroyAppbarPanelWindow(kind);

      const wrapper = $("<div class=\"home-appbar-list-window\"></div>").appendTo(document.body);
      const content = $("<div class=\"home-appbar-list-window-content\"></div>").appendTo(wrapper);
      $("<div class=\"home-appbar-list-loading\"></div>").text("Carregando...").appendTo(content);
      wrapper.kendoWindow({
        title: config.title || defaults.title,
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(Number(config.width) || 560, Math.max(320, window.innerWidth - 24)),
        height: Math.min(Number(config.height) || 520, Math.max(360, window.innerHeight - 24)),
        visible: false,
        close: () => {
          const widget = wrapper.data("kendoWindow");
          if (widget) {
            widget.destroy();
          }
          wrapper.remove();
          delete this.appbarPanelWindows[kind];
          if (this.savedAppbarPanelKind === kind) {
            this.savedAppbarPanelKind = "";
            this.saveNavigationState();
          }
          if (sourceButton) {
            sourceButton.trigger("focus");
          }
        }
      });
      const windowWidget = wrapper.data("kendoWindow");
      this.appbarPanelWindows[kind] = {
        element: wrapper,
        window: windowWidget
      };
      if (kind === "notifications" || kind === "jobs") {
        this.savedAppbarPanelKind = kind;
        this.saveNavigationState();
      }
      windowWidget.center().open();
      this.loadAppbarListItems(kind, endpoint, content);
    }

    loadAppbarListItems(kind, endpoint, content) {
      content.empty();
      const toolbarHost = $("<div class=\"home-appbar-list-toolbar-host\"></div>").appendTo(content);
      const listHost = $("<div class=\"home-appbar-list-host\"></div>").appendTo(content);
      $("<div class=\"home-appbar-list-loading\"></div>").text("Carregando...").appendTo(listHost);
      const request = kind === "notifications" && !endpoint
        ? this.loadAggregatedNotificationItems()
        : this.requestAppbarListEndpoint(endpoint, {
            user: this.buildChatUserPayload(),
            context: this.buildChatContextPayload()
          }, kind);
      request.then((response) => {
        const defaults = this.getAppbarListDefaults(kind);
        let items = this.normalizeAppbarListItems(response, kind);
        if (kind === "notifications") {
          this.renderNotificationToolbar(toolbarHost, endpoint, items);
          items = this.filterNotificationItems(items);
        }
        listHost.empty();
        this.renderAppbarListItems(listHost, items, defaults.emptyText, kind, endpoint);
      }).catch(() => {
        toolbarHost.empty();
        listHost.empty();
        $("<div class=\"home-appbar-list-empty\"></div>")
          .text("Nao foi possivel carregar as informacoes.")
          .appendTo(listHost);
      });
    }

    requestAppbarListEndpoint(endpoint, data, kind) {
      const payload = Object.assign({}, data || {});
      if (kind === "notifications") {
        Object.assign(payload, this.getNotificationRequestPayload());
      }
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "GET",
        data: payload
      });
    }

    getNotificationRequestPayload() {
      return {
        severity: this.notificationListFilters.severity || "",
        category: this.notificationListFilters.category || "",
        actionRequired: this.notificationListFilters.actionRequired === true,
        unreadOnly: this.notificationListFilters.unreadOnly === true,
        includeRead: this.notificationListFilters.includeRead === true,
        limit: 50
      };
    }

    filterNotificationItems(items) {
      const filters = this.notificationListFilters || {};
      return global.CrudUtils.ensureArray(items).filter(function(item) {
        if (filters.severity && String(item.severity || "") !== filters.severity) {
          return false;
        }
        if (filters.category && String(item.sourceKind || item.type || "") !== filters.category && String(item.status || "") !== filters.category) {
          return false;
        }
        if (filters.actionRequired === true && item.actionRequired !== true) {
          return false;
        }
        if (filters.unreadOnly === true && String(item.status || "").toLowerCase() === "lida") {
          return false;
        }
        return true;
      });
    }

    normalizeAppbarListItems(response, kind) {
      if (Array.isArray(response)) {
        return response.map((item) => this.normalizeAppbarListItem(item));
      }
      const keys = {
        notifications: "notifications",
        alerts: "alerts",
        requests: "requests",
        jobs: "jobs"
      };
      const key = keys[kind] || "items";
      const source = response && (response.items || response.data || response[key]);
      return global.CrudUtils.ensureArray(source).map((item) => this.normalizeAppbarListItem(item));
    }

    normalizeAppbarListItem(item) {
      if (typeof item === "string") {
        return {
          id: "",
          title: item,
          description: "",
          meta: "",
          linkUrl: "",
          linkText: "Abrir"
        };
      }
      const source = item || {};
      const date = source.receivedAt || source.updatedAt || source.createdAt || source.date || "";
      const meta = [];
      if (source.type) {
        meta.push(String(source.type));
      }
      if (source.status) {
        meta.push(String(source.status));
      }
      if (date) {
        meta.push(this.formatAppbarListDate(date));
      }
      return {
        id: source.id || "",
        recipientId: source.recipientId || "",
        title: source.title || source.label || "Item",
        description: source.description || source.summary || source.body || "",
        meta: meta.join(" - "),
        sourceKind: source.sourceKind || source.kind || "",
        sourceLabel: source.sourceLabel || "",
        sortTimestamp: date ? new Date(date).getTime() || 0 : 0,
        linkUrl: source.linkUrl || source.url || "",
        programId: source.programId || source.openProgramId || "",
        screenId: source.screenId || "",
        linkText: source.linkText || "Abrir",
        status: source.status || "",
        severity: source.severity || "",
        actionRequired: source.actionRequired === true,
        technicalProperties: global.CrudUtils.normalizeTechnicalProperties(source.technicalProperties)
      };
    }

    buildNotificationCategoryOptions(items) {
      const seen = {};
      const options = [{ value: "", text: "Todas as categorias" }];
      global.CrudUtils.ensureArray(items).forEach(function(item) {
        const value = String(item && (item.sourceKind || item.type || item.status || "") || "").trim();
        if (!value || seen[value]) {
          return;
        }
        seen[value] = true;
        options.push({
          value: value,
          text: value
        });
      });
      return options;
    }

    renderNotificationToolbar(container, endpoint, items) {
      const toolbar = $("<div class=\"home-appbar-list-toolbar\"></div>").appendTo(container);
      const severitySelect = $("<input type=\"text\">").appendTo(toolbar);
      severitySelect.kendoDropDownList({
        optionLabel: "Todas as severidades",
        dataTextField: "text",
        dataValueField: "value",
        value: this.notificationListFilters.severity || "",
        dataSource: [
          { value: "info", text: "Informacao" },
          { value: "warning", text: "Aviso" },
          { value: "error", text: "Erro" },
          { value: "success", text: "Sucesso" }
        ],
        change: () => {
          this.notificationListFilters.severity = String(severitySelect.data("kendoDropDownList").value() || "");
          this.saveNotificationFilterState();
          this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
        }
      });
      const categorySelect = $("<input type=\"text\">").appendTo(toolbar);
      categorySelect.kendoComboBox({
        clearButton: false,
        filter: "contains",
        suggest: true,
        dataTextField: "text",
        dataValueField: "value",
        value: this.notificationListFilters.category || "",
        dataSource: this.buildNotificationCategoryOptions(items),
        change: () => {
          this.notificationListFilters.category = String(categorySelect.data("kendoComboBox").value() || "");
          this.saveNotificationFilterState();
          this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
        }
      });
      const actionToggle = $("<label class=\"home-appbar-list-toggle\"></label>").appendTo(toolbar);
      const actionInput = $("<input type=\"checkbox\">").prop("checked", this.notificationListFilters.actionRequired === true).appendTo(actionToggle);
      if ($.fn.kendoCheckBox) {
        actionInput.kendoCheckBox({
          change: () => {
            this.notificationListFilters.actionRequired = actionInput.is(":checked");
            this.saveNotificationFilterState();
            this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
          }
        });
      } else {
        actionInput.on("change", () => {
          this.notificationListFilters.actionRequired = actionInput.is(":checked");
          this.saveNotificationFilterState();
          this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
        });
      }
      $("<span></span>").text("Exige acao").appendTo(actionToggle);
      const unreadToggle = $("<label class=\"home-appbar-list-toggle\"></label>").appendTo(toolbar);
      const unreadInput = $("<input type=\"checkbox\">").prop("checked", this.notificationListFilters.unreadOnly !== false).appendTo(unreadToggle);
      if ($.fn.kendoCheckBox) {
        unreadInput.kendoCheckBox({
          change: () => {
            const checked = unreadInput.is(":checked");
            this.notificationListFilters.unreadOnly = checked;
            this.notificationListFilters.includeRead = !checked;
            this.saveNotificationFilterState();
            this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
          }
        });
      } else {
        unreadInput.on("change", () => {
          const checked = unreadInput.is(":checked");
          this.notificationListFilters.unreadOnly = checked;
          this.notificationListFilters.includeRead = !checked;
          this.saveNotificationFilterState();
          this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
        });
      }
      $("<span></span>").text("Somente nao lidas").appendTo(unreadToggle);
      const clearFiltersButton = $("<button type=\"button\"></button>").appendTo(toolbar);
      clearFiltersButton.kendoButton({ icon: "filter-clear" });
      clearFiltersButton.data("kendoButton").element.text("Limpar filtros");
      clearFiltersButton.on("click", () => {
        this.notificationListFilters = {
          severity: "",
          category: "",
          actionRequired: false,
          unreadOnly: true,
          includeRead: false
        };
        this.saveNotificationFilterState();
        this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
      });
      const markAllButton = $("<button type=\"button\"></button>").appendTo(toolbar);
      markAllButton.kendoButton({ icon: "check" });
      markAllButton.data("kendoButton").element.text("Marcar todas");
      const ackEndpoint = this.getAppbarListActionEndpoint(this.getAppbarListConfig("notifications"), "ack", this.getAppbarListConfig("notifications").ackUrl);
      if (!ackEndpoint) {
        markAllButton.data("kendoButton").enable(false);
      }
      markAllButton.on("click", () => {
        this.acknowledgeNotificationItem(ackEndpoint, null, true).then((acknowledged) => {
          if (!acknowledged) {
            return;
          }
          this.saveNotificationFilterState();
          this.reloadNotificationsList(endpoint, container.closest(".home-appbar-list-window-content"));
        });
      });
    }

    reloadNotificationsList(endpoint, content) {
      if (!content || !content.length) {
        return;
      }
      this.loadAppbarListItems("notifications", endpoint, content);
    }

    formatAppbarListDate(value) {
      const date = value instanceof Date ? value : new Date(value);
      if (Number.isNaN(date.getTime())) {
        return String(value || "");
      }
      return kendo.toString(date, "dd/MM/yyyy HH:mm");
    }

    renderAppbarListItems(container, items, emptyText, listKind, endpoint) {
      if (!items.length) {
        $("<div class=\"home-appbar-list-empty\"></div>").text(emptyText).appendTo(container);
        return;
      }
      const list = $("<div class=\"home-appbar-list\"></div>").appendTo(container);
      items.forEach((item) => {
        const element = $("<article class=\"home-appbar-list-item\"></article>").appendTo(list);
        if (item.sourceLabel) {
          $("<div class=\"home-appbar-list-meta\"></div>").text(item.sourceLabel).appendTo(element);
        }
        const titleRow = $("<div class=\"home-appbar-list-title-row\"></div>").appendTo(element);
        $("<h2 class=\"home-appbar-list-title\"></h2>").text(item.title).appendTo(titleRow);
        global.CrudUtils.appendTechnicalInfoTrigger(titleRow, item.title, item.technicalProperties, {
          cssClass: "home-appbar-list-technical-trigger",
          dataRole: "home-list-technical-info"
        });
        if (item.meta) {
          $("<div class=\"home-appbar-list-meta\"></div>").text(item.meta).appendTo(element);
        }
        if (item.description) {
          $("<p class=\"home-appbar-list-description\"></p>").text(item.description).appendTo(element);
        }
        const actions = $("<div class=\"home-appbar-list-actions\"></div>").appendTo(element);
        if (item.linkUrl && global.CrudUtils.isAllowedDocumentUrl(item.linkUrl)) {
          $("<a class=\"home-appbar-list-link\" target=\"_blank\" rel=\"noopener noreferrer\"></a>")
            .attr("href", item.linkUrl)
            .text(item.linkText)
            .appendTo(actions);
        }
        const notificationAckEndpoint = listKind === "notifications"
          ? this.getAppbarListActionEndpoint(this.getAppbarListConfig("notifications"), "ack", this.getAppbarListConfig("notifications").ackUrl)
          : null;
        if (notificationAckEndpoint && item.id) {
          const ackButton = $("<button type=\"button\" class=\"home-appbar-list-link\"></button>")
            .text("Marcar como lida")
            .appendTo(actions);
          ackButton.kendoButton({ icon: "check" });
          ackButton.on("click", () => {
            this.acknowledgeNotificationItem(notificationAckEndpoint, item.id).then((acknowledged) => {
              if (!acknowledged) {
                return;
              }
              element.fadeOut(120, () => {
                element.remove();
                if (!list.children().length) {
                  $("<div class=\"home-appbar-list-empty\"></div>").text(emptyText).appendTo(container.empty());
                }
              });
            });
          });
        }
        const navigation = item && item.navigation && typeof item.navigation === "object" ? item.navigation : {};
        const resolvedProgramId = navigation.programId || item.programId;
        const resolvedScreenId = navigation.screenId || item.screenId;
        const canOpenProgram = resolvedProgramId && this.findProgram(resolvedProgramId);
        const canOpenScreen = !canOpenProgram && resolvedScreenId;
        if (canOpenProgram || canOpenScreen) {
          const button = $("<button type=\"button\" class=\"home-appbar-list-link\"></button>")
            .text(item.linkText || "Abrir")
            .appendTo(actions);
          button.kendoButton({ icon: "folder-open" });
          button.on("click", () => {
            const open = () => {
              this.destroyAppbarPanelWindows();
              if (listKind === "jobs" || item.sourceKind === "jobs") {
                this.setJobsIndicatorCount(0);
              }
              if (navigation.query && resolvedScreenId) {
                this.openNotificationScreen(resolvedScreenId, navigation.query || null);
                return;
              }
              if (resolvedProgramId && canOpenProgram) {
                this.openProgram(resolvedProgramId);
                return;
              }
              this.openNotificationScreen(resolvedScreenId, navigation.query || null);
            };
            if (listKind === "notifications" && notificationAckEndpoint && item.id) {
              this.acknowledgeNotificationItem(notificationAckEndpoint, item.id).then(open);
              return;
            }
            open();
          });
        }
      });
    }

    acknowledgeNotificationItem(endpoint, notificationId, markAll) {
      if (!endpoint || (!notificationId && markAll !== true)) {
        return Promise.resolve(false);
      }
      const payload = {
        user: this.buildChatUserPayload(),
        context: this.buildChatContextPayload()
      };
      if (notificationId) {
        payload.ids = [notificationId];
      } else if (markAll === true) {
        Object.assign(payload, this.getNotificationRequestPayload());
      }
      return this.requestAppbarListEndpoint(endpoint, payload, "notifications").then(() => {
        this.refreshNotificationsIndicator();
        return true;
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel marcar a notificacao como lida.");
        global.CrudUtils.showMessage(normalized.message, "error");
        return false;
      });
    }

    openNotificationScreen(screenId, query) {
      const id = String(screenId || "").trim();
      if (!id) {
        return;
      }
      const program = this.findProgramByScreenId(id);
      if (program) {
        this.openProgram(program.id);
        return;
      }
      const url = this.buildRuntimeScreenUrl(id, query);
      if (!url) {
        global.CrudUtils.showMessage("A notificacao nao possui destino navegavel.", "warning");
        return;
      }
      global.open(url, "_blank", "noopener,noreferrer");
    }

    hasAppbarNotificationSources() {
      return this.getNotificationSourceConfigs().length > 0;
    }

    getNotificationSourceConfigs() {
      const entries = [
        { kind: "alerts", label: "Alerta", config: this.getAppbarListConfig("alerts") },
        { kind: "requests", label: "Solicitacao", config: this.getAppbarListConfig("requests") },
        { kind: "jobs", label: "Job", config: this.getAppbarListConfig("jobs") }
      ];
      return entries.filter((entry) => {
        const config = entry.config || {};
        return config.enabled === true &&
          this.hasPermission(config.permission) &&
          Boolean(this.getAppbarListEndpoint(config));
      });
    }

    loadAggregatedNotificationItems() {
      const payload = {
        user: this.buildChatUserPayload(),
        context: this.buildChatContextPayload(),
        onlyMine: true
      };
      const requests = this.getNotificationSourceConfigs().map((entry) => {
        return this.requestAppbarListEndpoint(this.getAppbarListEndpoint(entry.config), payload)
          .then((response) => this.normalizeAppbarListItems(response, entry.kind).map((item) => {
            return Object.assign({}, item, {
              sourceKind: entry.kind,
              sourceLabel: entry.label
            });
          }))
          .catch(() => []);
      });
      return Promise.all(requests).then((groups) => {
        const items = [];
        groups.forEach((group) => {
          items.push.apply(items, group);
        });
        items.sort((left, right) => {
          const leftMeta = this.extractAppbarListItemTimestamp(left);
          const rightMeta = this.extractAppbarListItemTimestamp(right);
          return rightMeta - leftMeta;
        });
        return { items };
      });
    }

    extractAppbarListItemTimestamp(item) {
      if (item && item.sortTimestamp != null) {
        return Number(item.sortTimestamp) || 0;
      }
      const candidate = item && (item.updatedAt || item.receivedAt || item.createdAt || item.date || "");
      const parsed = candidate ? new Date(candidate) : null;
      return parsed && !Number.isNaN(parsed.getTime()) ? parsed.getTime() : 0;
    }

    startNotificationsIndicatorPolling() {
      const config = this.getAppbarListConfig("notifications");
      if (this.notificationsIndicatorTimer || config.indicatorPolling === false) {
        return;
      }
      if (!this.getAppbarListEndpoint(config) && !this.hasAppbarNotificationSources()) {
        return;
      }
      const seconds = Math.max(10, Number(config.pollIntervalSeconds || 30));
      this.refreshNotificationsIndicator();
      this.notificationsIndicatorTimer = window.setInterval(() => {
        this.refreshNotificationsIndicator();
      }, seconds * 1000);
    }

    stopNotificationsIndicatorPolling() {
      if (this.notificationsIndicatorTimer) {
        window.clearInterval(this.notificationsIndicatorTimer);
        this.notificationsIndicatorTimer = null;
      }
    }

    refreshNotificationsIndicator() {
      const config = this.getAppbarListConfig("notifications");
      if (!this.notificationsIndicatorBadge) {
        return Promise.resolve(0);
      }
      const endpoint = this.getAppbarListEndpoint(config);
      const request = endpoint
        ? this.requestAppbarListEndpoint(endpoint, {
          user: this.buildChatUserPayload(),
          context: this.buildChatContextPayload(),
          onlyMine: true
        })
        : this.loadAggregatedNotificationItems();
      return request.then((response) => {
        const items = this.normalizeAppbarListItems(response, "notifications");
        this.setNotificationsIndicatorCount(items.length);
        return items.length;
      }).catch(function() {
        return 0;
      });
    }

    setNotificationsIndicatorCount(count) {
      this.notificationsCount = Math.max(0, Number(count || 0));
      if (!this.notificationsIndicatorBadge) {
        return;
      }
      if (this.notificationsCount > 0) {
        this.notificationsIndicatorBadge.removeAttr("hidden").text(String(this.notificationsCount));
      } else {
        this.notificationsIndicatorBadge.attr("hidden", true).text("0");
      }
    }

    startJobsIndicatorPolling() {
      const config = this.getAppbarListConfig("jobs");
      const endpoint = this.getAppbarListEndpoint(config);
      if (!endpoint || !endpoint.url || this.jobsIndicatorTimer || config.indicatorPolling === false) {
        return;
      }
      const seconds = Math.max(10, Number(config.pollIntervalSeconds || 30));
      this.refreshJobsIndicator();
      this.jobsIndicatorTimer = window.setInterval(() => {
        this.refreshJobsIndicator();
      }, seconds * 1000);
    }

    stopJobsIndicatorPolling() {
      if (this.jobsIndicatorTimer) {
        window.clearInterval(this.jobsIndicatorTimer);
        this.jobsIndicatorTimer = null;
      }
    }

    refreshJobsIndicator() {
      const config = this.getAppbarListConfig("jobs");
      const endpoint = this.getAppbarListEndpoint(config);
      if (!endpoint || !endpoint.url || !this.jobsIndicatorBadge) {
        return Promise.resolve(0);
      }
      return this.requestAppbarListEndpoint(endpoint, {
        user: this.buildChatUserPayload(),
        context: this.buildChatContextPayload(),
        onlyMine: true
      }).then((response) => {
        const items = this.normalizeAppbarListItems(response, "jobs");
        const count = items.length;
        this.setJobsIndicatorCount(Math.max(this.completedJobsCount, count));
        return count;
      }).catch(function() {
        return 0;
      });
    }

    handleProcessJobCompleted(event) {
      const job = event && event.job || {};
      const jobId = String(job.id || "");
      if (jobId && this.completedJobIds[jobId]) {
        return;
      }
      if (jobId) {
        this.completedJobIds[jobId] = true;
      }
      this.completedJobsCount += 1;
      this.setJobsIndicatorCount(this.completedJobsCount);
      this.refreshNotificationsIndicator();
      global.CrudUtils.showMessage("Processamento concluido.", "success");
    }

    setJobsIndicatorCount(count) {
      this.completedJobsCount = Math.max(0, Number(count || 0));
      if (!this.jobsIndicatorBadge) {
        return;
      }
      if (this.completedJobsCount > 0) {
        this.jobsIndicatorBadge.removeAttr("hidden").text(String(this.completedJobsCount));
      } else {
        this.jobsIndicatorBadge.attr("hidden", true).text("0");
      }
    }

    destroyAppbarPanelWindows() {
      Object.keys(this.appbarPanelWindows || {}).forEach((kind) => this.destroyAppbarPanelWindow(kind));
      this.appbarPanelWindows = {};
    }

    destroySubscriberSwitchWindow() {
      if (this.subscriberSwitchWindow) {
        this.subscriberSwitchWindow.destroy();
      }
      if (this.subscriberSwitchWindowElement) {
        this.subscriberSwitchWindowElement.remove();
      }
      this.subscriberSwitchWindowElement = null;
      this.subscriberSwitchWindow = null;
      this.subscriberSwitchInput = null;
      this.subscriberSwitchDropDown = null;
    }

    destroyAppbarPanelWindow(kind) {
      const entry = this.appbarPanelWindows && this.appbarPanelWindows[kind];
      if (!entry) {
        return;
      }
      if (this.savedAppbarPanelKind === kind) {
        this.savedAppbarPanelKind = "";
        this.saveNavigationState();
      }
      if (entry.window) {
        entry.window.destroy();
      }
      if (entry.element) {
        entry.element.remove();
      }
      delete this.appbarPanelWindows[kind];
    }

    restoreAppbarPanelContext() {
      const kind = String(this.savedAppbarPanelKind || "").trim();
      if (!kind || (kind !== "notifications" && kind !== "jobs")) {
        return;
      }
      if (!this.shouldShowAppbarListButton(kind)) {
        this.savedAppbarPanelKind = "";
        this.saveNavigationState();
        return;
      }
      this.openAppbarListWindow(kind, null);
    }

    renderSidebarToggle(container) {
      if (!this.shouldShowSidebarToggle()) {
        return;
      }
      this.sidebarToggle = $("<button type=\"button\" class=\"home-icon-button home-sidebar-toggle\"></button>")
        .appendTo(container);
      this.sidebarToggle.kendoButton({ icon: "menu" });
      this.sidebarToggleButton = this.sidebarToggle.data("kendoButton");
      this.sidebarToggle.on("click", () => this.toggleSidebar());
      this.updateSidebarToggleState();
    }

    shouldShowSidebarToggle() {
      const layout = this.definition && this.definition.layout ? this.definition.layout : {};
      const sidebar = layout.sidebar || {};
      const appbar = layout.appbar || {};
      return sidebar.collapsible !== false && appbar.showSidebarToggle !== false;
    }

    renderBrand(container) {
      const logo = this.getAppLogoConfig();
      const hasLogo = Boolean(logo.url);
      if (hasLogo) {
        $("<img class=\"home-brand-logo\">")
          .attr("src", logo.url)
          .attr("alt", logo.alt || this.definition.app.title || "Logo")
          .appendTo(container);
      }

      if (hasLogo && logo.showTitle === false) {
        return;
      }

      const text = $("<div class=\"home-brand-text\"></div>").appendTo(container);
      $("<span class=\"home-brand-title\"></span>").text(this.definition.app.title).appendTo(text);
      if (this.definition.app.subtitle && logo.showSubtitle !== false) {
        $("<span class=\"home-brand-subtitle\"></span>").text(this.definition.app.subtitle).appendTo(text);
      }
    }

    getAppLogoConfig() {
      const app = this.definition.app || {};
      const source = app.logo || app.companyLogo || app.logoUrl || "";
      if (typeof source === "string") {
        return {
          url: source,
          alt: app.title,
          showTitle: true,
          showSubtitle: true
        };
      }
      return Object.assign({
        url: "",
        alt: app.title,
        showTitle: true,
        showSubtitle: true
      }, source || {});
    }

    renderCurrentSubscriber(container) {
      const subscriber = this.getCurrentSubscriber();
      if (!this.shouldShowCurrentSubscriber(subscriber)) {
        return;
      }
      const displayName = this.getSubscriberDisplayName(subscriber);
      const label = this.getSubscriberLabel(subscriber);
      const title = this.getSubscriberTitle(subscriber, displayName, label);
      const switchable = this.isSubscriberSwitchable();
      const element = $(switchable ? "<button type=\"button\"></button>" : "<span></span>")
        .addClass("home-current-subscriber k-badge k-badge-solid k-badge-solid-base k-rounded-md")
        .attr("title", switchable ? title + " | Clique para trocar" : title)
        .text(this.getSubscriberBadgeText(subscriber, displayName, label))
        .toggleClass("is-principal", this.isPrincipalSubscriber(subscriber))
        .toggleClass("is-switchable", switchable)
        .appendTo(container);
      if (switchable) {
        element.attr("aria-label", "Trocar assinante atual: " + displayName);
        element.on("click", () => this.openSubscriberSwitchWindow());
      }
      this.currentSubscriberElement = element;
    }

    shouldShowCurrentSubscriber(subscriber) {
      const appbar = this.definition.layout && this.definition.layout.appbar ? this.definition.layout.appbar : {};
      return appbar.showCurrentSubscriber !== false && Boolean(this.getSubscriberDisplayName(subscriber));
    }

    getCurrentSubscriber() {
      const source = this.definition.currentSubscriber || this.definition.currentTenant || this.definition.tenant || this.definition.subscriber || {};
      if (typeof source === "string") {
        return { name: source };
      }
      return source && typeof source === "object" && !Array.isArray(source) ? source : {};
    }

    getSubscriberDisplayName(subscriber) {
      if (this.isPrincipalSubscriber(subscriber)) {
        return String(subscriber.name || subscriber.displayName || subscriber.title || "Principal").trim();
      }
      return String(subscriber.name || subscriber.displayName || subscriber.title || subscriber.code || subscriber.id || "").trim();
    }

    getSubscriberLabel(subscriber) {
      return String(subscriber.label || "Assinante").trim();
    }

    getSubscriberTitle(subscriber, displayName, label) {
      const details = [];
      if (this.isPrincipalSubscriber(subscriber)) {
        details.push("Principal");
      } else if (label) {
        details.push(label + ": " + displayName);
      } else {
        details.push(displayName);
      }
      if (subscriber.document) {
        details.push(subscriber.document);
      }
      if (subscriber.id && subscriber.id !== displayName) {
        details.push("ID: " + subscriber.id);
      }
      return details.join(" | ");
    }

    getSubscriberBadgeText(subscriber, displayName, label) {
      if (this.isPrincipalSubscriber(subscriber)) {
        return "Principal";
      }
      return label ? label + ": " + displayName : displayName;
    }

    isPrincipalSubscriber(subscriber) {
      if (!subscriber) {
        return false;
      }
      const id = String(subscriber.id || subscriber.code || "").toLowerCase();
      const type = String(subscriber.type || subscriber.database || subscriber.kind || "").toLowerCase();
      return subscriber.principal === true ||
        subscriber.isPrincipal === true ||
        id === "principal" ||
        id === "main" ||
        id === "master" ||
        type === "principal" ||
        type === "main" ||
        type === "master";
    }

    getSubscriberSwitchConfig() {
      const appbar = this.definition.layout && this.definition.layout.appbar ? this.definition.layout.appbar : {};
      const config = appbar.subscriberSwitch || this.definition.subscriberSwitch || {};
      return config && typeof config === "object" && !Array.isArray(config) ? config : {};
    }

    isSubscriberSwitchable() {
      const config = this.getSubscriberSwitchConfig();
      if (config.enabled === false) {
        return false;
      }
      return this.getAvailableSubscribers().length > 1 ||
        Boolean(this.getSubscriberSwitchEndpoint(config)) ||
        Boolean(config.programId || config.changeProgramId || config.url || config.changeUrl);
    }

    openSubscriberSwitchWindow() {
      const config = this.getSubscriberSwitchConfig();
      if (!this.isSubscriberSwitchable()) {
        return;
      }
      if (this.subscriberSwitchWindow) {
        this.subscriberSwitchWindow.center().open();
        return;
      }
      this.createSubscriberSwitchWindow(config);
      this.subscriberSwitchWindow.center().open();
    }

    createSubscriberSwitchWindow(config) {
      const subscribers = this.getAvailableSubscribers();
      this.subscriberSwitchWindowElement = $("<div class=\"home-subscriber-window\"></div>").appendTo(document.body);
      const body = $("<div class=\"home-subscriber-window-body\"></div>").appendTo(this.subscriberSwitchWindowElement);
      $("<p class=\"home-subscriber-window-note\"></p>")
        .text(config.description || "Selecione o assinante para continuar trabalhando no sistema.")
        .appendTo(body);

      if (subscribers.length) {
        const field = $("<label class=\"home-subscriber-field\"></label>").appendTo(body);
        $("<span></span>").text(config.fieldLabel || "Assinante").appendTo(field);
        this.subscriberSwitchInput = $("<input type=\"text\">").appendTo(field);
        this.subscriberSwitchInput.kendoDropDownList({
          dataTextField: "displayText",
          dataValueField: "id",
          valueTemplate: "#: displayText #",
          template: "# if (principal) { #<strong>Principal</strong># } else { #<span>#: displayText #</span># } #",
          dataSource: subscribers
        });
        this.subscriberSwitchDropDown = this.subscriberSwitchInput.data("kendoDropDownList");
        const current = this.getCurrentSubscriber();
        const selected = subscribers.find((item) => this.isSameSubscriber(item, current)) || subscribers[0];
        if (selected) {
          this.subscriberSwitchDropDown.value(selected.id);
        }
      } else {
        $("<div class=\"home-chat-empty\"></div>")
          .text("Nenhum assinante disponivel para selecao.")
          .appendTo(body);
      }

      const actions = $("<div class=\"home-subscriber-actions\"></div>").appendTo(body);
      if (subscribers.length) {
        const confirmButton = $("<button type=\"button\"></button>")
          .text(config.confirmText || "Confirmar")
          .appendTo(actions);
        confirmButton.kendoButton({ icon: "check" });
        confirmButton.on("click", () => this.confirmSubscriberSwitch(config));
      }
      if (config.programId || config.changeProgramId || config.url || config.changeUrl) {
        const openButton = $("<button type=\"button\"></button>")
          .text(config.openText || "Abrir tela de troca")
          .appendTo(actions);
        openButton.kendoButton({ icon: "window" });
        openButton.on("click", () => this.openSubscriberSwitchTarget(config));
      }
      const cancelButton = $("<button type=\"button\"></button>")
        .text(config.cancelText || "Cancelar")
        .appendTo(actions);
      cancelButton.kendoButton();
      cancelButton.on("click", () => this.subscriberSwitchWindow && this.subscriberSwitchWindow.close());

      this.subscriberSwitchWindowElement.kendoWindow({
        title: config.title || "Trocar assinante",
        modal: true,
        visible: false,
        width: Math.min(Number(config.width) || 430, Math.max(320, window.innerWidth - 24)),
        maxHeight: Math.max(320, window.innerHeight - 24),
        actions: ["Close"],
        close: () => this.destroySubscriberSwitchWindow()
      });
      this.subscriberSwitchWindow = this.subscriberSwitchWindowElement.data("kendoWindow");
    }

    confirmSubscriberSwitch(config) {
      const selected = this.getSelectedSubscriber();
      if (!selected) {
        global.CrudUtils.showMessage("Selecione um assinante.", "warning");
        return;
      }
      const current = this.getCurrentSubscriber();
      if (this.isSameSubscriber(selected, current)) {
        if (this.subscriberSwitchWindow) {
          this.subscriberSwitchWindow.close();
        }
        return;
      }
      this.requestSubscriberChange(config, selected)
        .then((response) => {
          const next = this.normalizeSubscriberOption(
            response && (response.currentSubscriber || response.subscriber || response.data) || selected
          );
          this.applySubscriberChange(next);
          if (this.subscriberSwitchWindow) {
            this.subscriberSwitchWindow.close();
          }
          global.CrudUtils.showMessage("Assinante alterado.", "success");
        })
        .catch((error) => {
          const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel trocar o assinante.");
          global.CrudUtils.showMessage(normalized.message, "error");
        });
    }

    requestSubscriberChange(config, subscriber) {
      const endpoint = this.getSubscriberSwitchEndpoint(config);
      if (!endpoint) {
        return Promise.resolve({ currentSubscriber: subscriber });
      }
      return this.httpClient.request({
        url: endpoint.url || endpoint,
        method: endpoint.method || config.method || "POST",
        data: {
          subscriberId: subscriber.id,
          subscriber,
          currentUser: this.buildChatUserPayload()
        }
      });
    }

    getSubscriberSwitchEndpoint(config) {
      const endpoints = config && config.endpoints ? config.endpoints : {};
      const endpoint = endpoints.change || config.endpoint || config.changeEndpoint || "";
      if (!endpoint) {
        return null;
      }
      if (typeof endpoint === "string") {
        return {
          url: endpoint,
          method: "POST"
        };
      }
      if (typeof endpoint === "object" && endpoint.url) {
        return {
          url: endpoint.url,
          method: String(endpoint.method || "POST").toUpperCase()
        };
      }
      if (typeof endpoint === "object" && endpoint.endpointId && String(endpoint.endpointId).charAt(0) === "/") {
        return {
          url: endpoint.endpointId,
          method: String(endpoint.method || "POST").toUpperCase()
        };
      }
      return null;
    }

    getSelectedSubscriber() {
      if (!this.subscriberSwitchDropDown) {
        return null;
      }
      const dataItem = this.subscriberSwitchDropDown.dataItem();
      if (dataItem) {
        return this.normalizeSubscriberOption(dataItem);
      }
      const value = this.subscriberSwitchDropDown.value();
      return this.getAvailableSubscribers().find(function(item) {
        return String(item.id || "") === String(value || "");
      }) || null;
    }

    applySubscriberChange(subscriber) {
      this.definition.currentSubscriber = subscriber;
      this.definition.currentTenant = subscriber;
      this.definition.tenant = subscriber;
      global.CrudUtils.saveLocalJsonValue("crudEngine.currentSubscriber", subscriber || {});
      this.updateCurrentSubscriberBadge();
    }

    updateCurrentSubscriberBadge() {
      if (!this.currentSubscriberElement || !this.currentSubscriberElement.length) {
        return;
      }
      const subscriber = this.getCurrentSubscriber();
      const displayName = this.getSubscriberDisplayName(subscriber);
      const label = this.getSubscriberLabel(subscriber);
      const switchable = this.isSubscriberSwitchable();
      const title = this.getSubscriberTitle(subscriber, displayName, label);
      this.currentSubscriberElement
        .attr("title", switchable ? title + " | Clique para trocar" : title)
        .attr("aria-label", switchable ? "Trocar assinante atual: " + displayName : null)
        .toggleClass("is-principal", this.isPrincipalSubscriber(subscriber))
        .toggleClass("is-switchable", switchable)
        .text(this.getSubscriberBadgeText(subscriber, displayName, label));
    }

    openSubscriberSwitchTarget(config) {
      const programId = config.programId || config.changeProgramId || "";
      const url = config.url || config.changeUrl || "";
      if (this.subscriberSwitchWindow) {
        this.subscriberSwitchWindow.close();
      }
      if (programId) {
        this.openProgram(programId);
        return;
      }
      if (url) {
        global.open(url, "_blank", "noopener,noreferrer");
      }
    }

    getAvailableSubscribers() {
      const config = this.getSubscriberSwitchConfig();
      const user = this.getCurrentUser();
      const current = this.getCurrentSubscriber();
      const sources = []
        .concat(global.CrudUtils.ensureArray(config.subscribers || config.options))
        .concat(global.CrudUtils.ensureArray(this.definition.availableSubscribers || this.definition.subscribers || this.definition.tenants))
        .concat(global.CrudUtils.ensureArray(user.availableSubscribers || user.subscribers || user.tenants))
        .concat(global.CrudUtils.ensureArray(current.options || current.subscribers || current.tenants));
      if (this.getSubscriberDisplayName(current)) {
        sources.unshift(current);
      }
      const seen = {};
      return sources.map((item) => this.normalizeSubscriberOption(item)).filter((item) => {
        const key = String(item.id || item.name || "").trim();
        if (!key || seen[key]) {
          return false;
        }
        seen[key] = true;
        return true;
      });
    }

    normalizeSubscriberOption(source) {
      if (typeof source === "string") {
        return {
          id: source,
          name: source,
          displayText: source,
          principal: source.toLowerCase() === "principal"
        };
      }
      const item = Object.assign({}, source || {});
      const principal = this.isPrincipalSubscriber(item);
      const id = String(item.id || item.code || (principal ? "principal" : item.name || item.displayName || "")).trim();
      const name = String(item.name || item.displayName || item.title || item.code || id || (principal ? "Principal" : "")).trim();
      item.id = id;
      item.name = principal ? (name || "Principal") : name;
      item.label = item.label || "Assinante";
      item.principal = principal;
      item.displayText = principal ? "Principal" : (item.document ? item.name + " - " + item.document : item.name);
      return item;
    }

    isSameSubscriber(a, b) {
      const left = this.normalizeSubscriberOption(a || {});
      const right = this.normalizeSubscriberOption(b || {});
      if (left.id && right.id) {
        return String(left.id) === String(right.id);
      }
      return this.getSubscriberDisplayName(left) === this.getSubscriberDisplayName(right);
    }

    renderProgramFavoriteButton(container) {
      if (this.definition.layout.appbar.showFavoriteToggle === false) {
        return;
      }
      const button = $("<button type=\"button\" class=\"home-program-favorite-button home-icon-button\" hidden></button>")
        .appendTo(container);
      button.kendoButton({ icon: "star" });
      button.on("click", () => this.toggleCurrentProgramFavorite());
      this.programFavoriteButtonElement = button;
      this.programFavoriteButton = button.data("kendoButton");
    }

    updateProgramFavoriteButton() {
      if (!this.programFavoriteButtonElement || !this.currentProgram) {
        return;
      }
      const isFavorite = this.isCurrentProgramFavorite();
      const title = isFavorite ? "Remover dos favoritos" : "Adicionar aos favoritos";
      this.programFavoriteButtonElement
        .removeAttr("hidden")
        .attr("title", title)
        .attr("aria-label", title)
        .attr("aria-pressed", isFavorite ? "true" : "false")
        .toggleClass("is-favorite", isFavorite);
      if (this.programFavoriteButton) {
        this.programFavoriteButton.setOptions({
          icon: "star",
          selected: isFavorite
        });
      }
    }

    toggleCurrentProgramFavorite() {
      if (!this.currentProgram) {
        return;
      }
      const nextValue = !this.isCurrentProgramFavorite();
      this.setProgramFavorite(this.currentProgram.id, nextValue);
      this.updateProgramFavoriteButton();
      this.refreshTreeView();
      global.CrudUtils.showMessage(nextValue ? "Programa adicionado aos favoritos." : "Programa removido dos favoritos.", "info");
    }

    isCurrentProgramFavorite() {
      if (!this.currentProgram) {
        return false;
      }
      return this.isProgramFavorite(this.findNavigationItemByProgramId(this.currentProgram.id), this.currentProgram);
    }

    renderUserMenu(container) {
      if (!this.shouldShowUserMenu()) {
        return;
      }
      const user = this.getCurrentUser();
      const menuItems = this.getUserMenuItems();
      const label = this.getUserInitials(user);
      const title = this.getUserDisplayName(user);
      const wrapper = $("<span class=\"home-user-menu\"></span>").appendTo(container);
      const button = $("<button type=\"button\" class=\"home-user-button\"></button>")
        .attr("title", title)
        .attr("aria-label", "Menu do usuario: " + title)
        .attr("aria-haspopup", "menu")
        .attr("aria-expanded", "false")
        .text(label)
        .appendTo(wrapper);
      const menu = $("<div class=\"home-user-menu-list\" role=\"menu\" hidden></div>").appendTo(wrapper);
      const header = $("<div class=\"home-user-menu-header\"></div>").appendTo(menu);
      $("<strong></strong>").text(title).appendTo(header);
      if (user.email) {
        $("<span></span>").text(user.email).appendTo(header);
      }
      if (!menuItems.length) {
        $("<span class=\"home-user-menu-empty\"></span>").text("Nenhuma acao disponivel.").appendTo(menu);
      }
      menuItems.forEach((item) => {
        const itemButton = $("<button type=\"button\" class=\"home-user-menu-item\" role=\"menuitem\"></button>")
          .attr("title", item.label)
          .text(item.label)
          .appendTo(menu);
        itemButton.kendoButton({
          icon: item.icon || this.getUserMenuItemIcon(item)
        });
        itemButton.on("click", () => this.executeUserMenuItem(item));
      });
      button.kendoButton();
      button.on("click", (event) => {
        event.stopPropagation();
        this.toggleUserMenu();
      });
      wrapper.on("click", function(event) {
        event.stopPropagation();
      });
      $(document).off("click.homeUserMenu").on("click.homeUserMenu", () => this.closeUserMenu());
      this.userMenuButtonElement = button;
      this.userMenu = menu;
    }

    shouldShowUserMenu() {
      const appbar = this.definition.layout && this.definition.layout.appbar ? this.definition.layout.appbar : {};
      if (appbar.showUserMenu === false) {
        return false;
      }
      const user = this.getCurrentUser();
      return Boolean(user.name || user.fullName || user.email || user.initials || this.getUserMenuItems().length);
    }

    getCurrentUser() {
      return this.definition.currentUser || this.definition.user || {};
    }

    getUserDisplayName(user) {
      return String(user.name || user.fullName || user.email || user.username || "Usuario");
    }

    getUserInitials(user) {
      const configured = String(user.initials || "").trim();
      if (configured) {
        return configured.slice(0, 3).toUpperCase();
      }
      const name = this.getUserDisplayName(user);
      const parts = name.split(/\s+/).filter(Boolean);
      if (parts.length >= 2) {
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
      }
      return name.slice(0, 2).toUpperCase() || "U";
    }

    getUserMenuConfig() {
      const appbar = this.definition.layout && this.definition.layout.appbar ? this.definition.layout.appbar : {};
      const config = appbar.userMenu || this.definition.userMenu || {};
      if (Array.isArray(config)) {
        return { items: config };
      }
      return config || {};
    }

    getUserMenuItems() {
      const config = this.getUserMenuConfig();
      if (config.enabled === false) {
        return [];
      }
      return global.CrudUtils.ensureArray(config.items).filter((item) => {
        return item && item.visible !== false && item.label && this.hasPermission(item.permission);
      });
    }

    getUserMenuItemIcon(item) {
      const action = item.action || item.id || "";
      if (action === "profile") {
        return "user";
      }
      if (action === "preferences") {
        return "gear";
      }
      if (action === "logout") {
        return "logout";
      }
      return "arrow-right";
    }

    toggleUserMenu() {
      if (!this.userMenu || !this.userMenuButtonElement) {
        return;
      }
      const isHidden = this.userMenu.prop("hidden");
      this.userMenu.prop("hidden", !isHidden);
      this.userMenuButtonElement.attr("aria-expanded", isHidden ? "true" : "false");
    }

    closeUserMenu() {
      if (this.userMenu) {
        this.userMenu.prop("hidden", true);
      }
      if (this.userMenuButtonElement) {
        this.userMenuButtonElement.attr("aria-expanded", "false");
      }
    }

    executeUserMenuItem(item) {
      this.closeUserMenu();
      if (item.url) {
        if (item.openAs === "window") {
          this.openUrlWindow({
            title: item.label,
            url: item.url,
            linkText: item.linkText || "Abrir em nova aba"
          });
          return;
        }
        global.open(item.url, item.target || "_blank", "noopener,noreferrer");
        return;
      }
      if ((item.action || item.id) === "logout") {
        this.handleLogout();
        return;
      }
      global.CrudUtils.showMessage("Acao do usuario acionada: " + item.label + ".", "info");
    }

    handleLogout() {
      if (this.loggingOut) {
        return;
      }
      this.loggingOut = true;
      const done = () => {
        this.loggingOut = false;
        this.clearLocalSessionContext(true);
        if (this.httpClient && typeof this.httpClient.redirectToLogin === "function") {
          this.httpClient.redirectToLogin(false);
          return;
        }
        global.CrudUtils.showMessage("Saida solicitada.", "info");
      };

      if (this.httpClient && typeof this.httpClient.logout === "function") {
        this.httpClient.logout().then(done).catch(done);
        return;
      }

      done();
    }

    clearLocalSessionContext(preserveLastUsername) {
      global.CrudUtils.clearRuntimeSessionContext({
        preserveLastUsername: preserveLastUsername === true,
        clearRememberToken: true
      });
    }

    renderMain(shell) {
      const main = $("<div class=\"home-main\"></div>").appendTo(shell);
      this.sidebar = $("<aside class=\"home-sidebar\"></aside>").appendTo(main);
      this.contentWrap = $("<section class=\"home-content-wrap\"></section>").appendTo(main);
      this.contentRoot = $("<div class=\"home-content\"></div>").appendTo(this.contentWrap);
      this.renderTreeView();
    }

    renderTreeView() {
      const sidebar = this.definition.layout.sidebar || {};
      const header = $("<div class=\"home-sidebar-header\"></div>").appendTo(this.sidebar);
      this.renderModuleSelector(header);
      this.renderSidebarFilters(this.sidebar);

      this.treeHost = $("<div class=\"home-treeview\"></div>").appendTo(this.sidebar);
      this.initializeTreeView(this.buildTreeData());

      if (this.shouldStartSidebarCollapsed()) {
        this.shell.addClass("home-sidebar-collapsed");
      }
    }

    renderModuleSelector(container) {
      $("<span class=\"k-icon k-i-menu home-module-icon\" aria-hidden=\"true\"></span>").appendTo(container);
      const input = $("<input class=\"home-module-selector\">")
        .attr("id", "home-module-selector")
        .attr("aria-label", "Sistema ou modulo")
        .appendTo(container);

      input.kendoComboBox({
        dataSource: this.modules,
        dataTextField: "title",
        dataValueField: "id",
        clearButton: false,
        filter: "contains",
        suggest: true,
        value: this.currentModuleId,
        change: () => this.handleModuleChange()
      });
      this.moduleSelector = input.data("kendoComboBox");
      if (this.moduleSelector && this.modules.length <= 1) {
        this.moduleSelector.enable(false);
      }
    }

    renderSidebarFilters(container) {
      const filters = $("<div class=\"home-sidebar-filters\"></div>").appendTo(container);
      const searchWrap = $("<span class=\"home-menu-search\"></span>").appendTo(filters);
      const search = $("<input class=\"home-menu-search-input\" type=\"search\">")
        .attr("aria-label", "Filtrar programas por nome")
        .attr("placeholder", "Filtrar programas")
        .val(this.menuSearchText)
        .appendTo(searchWrap);
      if ($.fn.kendoTextBox) {
        search.kendoTextBox();
      }
      search.on("input", () => {
        this.menuSearchText = search.val();
        this.saveNavigationState();
        this.refreshTreeView();
      });

      const favoriteButton = $("<button type=\"button\" class=\"home-favorites-filter\"></button>")
        .attr("title", "Mostrar apenas favoritos")
        .attr("aria-label", "Mostrar apenas favoritos")
        .attr("aria-pressed", this.showOnlyFavorites ? "true" : "false")
        .appendTo(filters);
      favoriteButton.kendoButton({
        icon: "star",
        selected: this.showOnlyFavorites
      });
      favoriteButton.on("click", () => {
        this.showOnlyFavorites = !this.showOnlyFavorites;
        this.updateFavoritesFilterButton();
        this.saveNavigationState();
        this.refreshTreeView();
      });
      this.menuSearchInput = search;
      this.favoritesFilterButtonElement = favoriteButton;
      this.favoritesFilterButton = favoriteButton.data("kendoButton");
    }

    updateFavoritesFilterButton() {
      if (!this.favoritesFilterButtonElement) {
        return;
      }
      this.favoritesFilterButtonElement.attr("aria-pressed", this.showOnlyFavorites ? "true" : "false");
      if (this.favoritesFilterButton) {
        this.favoritesFilterButton.setOptions({
          icon: "star",
          selected: this.showOnlyFavorites
        });
      }
    }

    initializeTreeView(data) {
      const sidebar = this.definition.layout.sidebar || {};
      kendo.destroy(this.treeHost);
      this.treeHost.empty();
      this.treeDataSource = new kendo.data.HierarchicalDataSource({
        data
      });
      this.treeHost.kendoTreeView({
        dataSource: this.treeDataSource,
        template: this.getTreeTemplate(),
        loadOnDemand: false,
        select: (event) => this.handleTreeSelect(event)
      });

      this.treeView = this.treeHost.data("kendoTreeView");
      if (this.treeView && sidebar.expanded !== false) {
        this.treeView.expand(".k-treeview-item");
      }
      this.bindTreeOpenButtons();
      if (this.currentProgram) {
        this.updateActiveMenu(this.currentProgram.id);
      }
    }

    buildTreeData() {
      const searchText = this.normalizeSearchText(this.menuSearchText);
      const showAllModules = this.isAllModulesSelected();
      return global.CrudUtils.ensureArray(this.definition.navigation && this.definition.navigation.groups).map((group) => {
        if (!showAllModules && this.getGroupModuleId(group) !== this.currentModuleId) {
          return null;
        }
        return {
          id: group.id,
          text: group.title,
          iconClass: "folder",
          expanded: this.definition.layout.sidebar.expanded !== false,
          items: global.CrudUtils.ensureArray(group.items).map((item) => {
            const program = this.findProgram(item.programId);
            if (!program || !this.hasPermission(item.permission || program.permission)) {
              return null;
            }
            if (!this.shouldShowProgramInMenu(item, program, searchText)) {
              return null;
            }
            return {
              id: program.id,
              programId: program.id,
              openUrl: this.getProgramOpenUrl(program),
              text: item.title || program.title,
              iconClass: this.escapeClass(program.icon || item.icon || "file"),
              favorite: this.isProgramFavorite(item, program)
            };
          }).filter(Boolean)
        };
      }).filter(function(group) {
        return group && group.items.length > 0;
      });
    }

    shouldShowProgramInMenu(item, program, searchText) {
      if (this.showOnlyFavorites && !this.isProgramFavorite(item, program)) {
        return false;
      }
      if (!searchText) {
        return true;
      }
      const name = this.normalizeSearchText(item.title || program.title || "");
      const description = this.normalizeSearchText(program.subtitle || program.description || "");
      return name.indexOf(searchText) !== -1 || description.indexOf(searchText) !== -1;
    }

    isProgramFavorite(item, program) {
      const programId = String(program && program.id || "");
      if (!programId) {
        return false;
      }
      if (this.getUnfavoriteProgramIds().indexOf(programId) !== -1) {
        return false;
      }
      if (this.getFavoriteProgramIds().indexOf(programId) !== -1) {
        return true;
      }
      return Boolean(
        item && (item.favorite === true || item.isFavorite === true) ||
        program && (program.favorite === true || program.isFavorite === true)
      );
    }

    getFavoriteProgramIds() {
      const user = this.getCurrentUser();
      return global.CrudUtils.ensureArray(user.favoritePrograms || user.favorites)
        .map(String)
        .filter(Boolean);
    }

    getUnfavoriteProgramIds() {
      const user = this.getCurrentUser();
      return global.CrudUtils.ensureArray(user.unfavoritePrograms || user.removedFavorites)
        .map(String)
        .filter(Boolean);
    }

    setProgramFavorite(programId, isFavorite) {
      const id = String(programId || "");
      if (!id) {
        return;
      }
      const user = this.getCurrentUser();
      const favorites = this.getFavoriteProgramIds().filter(function(item) {
        return item !== id;
      });
      const unfavorites = this.getUnfavoriteProgramIds().filter(function(item) {
        return item !== id;
      });
      if (isFavorite) {
        favorites.push(id);
      } else {
        unfavorites.push(id);
      }
      user.favoritePrograms = favorites;
      user.unfavoritePrograms = unfavorites;
      this.saveUserFavoriteState();
    }

    loadUserFavoriteState() {
      const key = this.getFavoriteStorageKey();
      if (!key) {
        return;
      }
      const state = global.CrudUtils.readLocalJsonValue(key, null);
      if (!state || typeof state !== "object") {
        return;
      }
      const user = this.getCurrentUser();
      if (Array.isArray(state.favoritePrograms)) {
        user.favoritePrograms = state.favoritePrograms.map(String);
      }
      if (Array.isArray(state.unfavoritePrograms)) {
        user.unfavoritePrograms = state.unfavoritePrograms.map(String);
      }
    }

    saveUserFavoriteState() {
      const key = this.getFavoriteStorageKey();
      if (!key) {
        return;
      }
      global.CrudUtils.saveLocalJsonValue(key, {
        favoritePrograms: this.getFavoriteProgramIds(),
        unfavoritePrograms: this.getUnfavoriteProgramIds()
      });
    }

    getFavoriteStorageKey() {
      const app = this.definition && this.definition.app ? this.definition.app : {};
      const user = this.getCurrentUser();
      const appId = app.id || app.title || "home";
      const userId = user.id || user.email || user.username || "usuario";
      return "homeEngine.favoritePrograms." + appId + "." + userId;
    }

    findNavigationItemByProgramId(programId) {
      const id = String(programId || "");
      let found = null;
      global.CrudUtils.ensureArray(this.definition.navigation && this.definition.navigation.groups).some(function(group) {
        found = global.CrudUtils.ensureArray(group && group.items).find(function(item) {
          return item && String(item.programId || "") === id;
        }) || null;
        return Boolean(found);
      });
      return found;
    }

    normalizeSearchText(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
    }

    buildModuleList() {
      const configuredModules = global.CrudUtils.ensureArray(this.definition.navigation && this.definition.navigation.modules)
        .map((module, index) => {
          if (!module || !module.id || !module.title || !this.hasPermission(module.permission)) {
            return null;
          }
          if (String(module.id) === this.allModulesId) {
            return null;
          }
          return {
            id: String(module.id),
            title: String(module.title),
            order: index
          };
        })
        .filter(Boolean);

      return [{
        id: this.allModulesId,
        title: "Todos",
        order: -1,
        isAll: true
      }].concat(configuredModules);
    }

    resolveInitialModuleId() {
      const requestedProgramId = this.getRequestedInitialProgramId();
      const requestedModuleId = this.findModuleIdByProgramId(requestedProgramId);
      if (requestedModuleId && this.findModule(requestedModuleId)) {
        return requestedModuleId;
      }
      const savedModuleId = String(this.currentModuleId || "").trim();
      if (savedModuleId && this.findModule(savedModuleId)) {
        return savedModuleId;
      }
      const navigation = this.definition.navigation || {};
      const configuredInitialId = String(navigation.initialModuleId || "").trim() || this.allModulesId;
      if (this.findModule(configuredInitialId)) {
        return configuredInitialId;
      }
      return this.findModule(this.allModulesId) ? this.allModulesId : "";
    }

    findModule(moduleId) {
      const id = String(moduleId || "");
      return this.modules.find(function(module) {
        return module && module.id === id;
      }) || null;
    }

    findFirstConfiguredModule() {
      return this.modules.find(function(module) {
        return module && !module.isAll;
      }) || null;
    }

    isAllModulesSelected() {
      return this.currentModuleId === this.allModulesId;
    }

    findModuleIdByProgramId(programId) {
      if (!programId) {
        return "";
      }
      const group = global.CrudUtils.ensureArray(this.definition.navigation && this.definition.navigation.groups).find(function(item) {
        return global.CrudUtils.ensureArray(item && item.items).some(function(navItem) {
          return navItem && navItem.programId === programId;
        });
      });
      return group ? this.getGroupModuleId(group) : "";
    }

    getGroupModuleId(group) {
      const moduleId = group && group.moduleId ? String(group.moduleId) : "";
      if (moduleId && this.findModule(moduleId)) {
        return moduleId;
      }
      const module = this.findFirstConfiguredModule();
      return module ? module.id : "";
    }

    handleModuleChange() {
      if (!this.moduleSelector) {
        return;
      }
      const module = this.moduleSelector.dataItem();
      if (!module || !this.findModule(module.id)) {
        this.moduleSelector.value(this.currentModuleId);
        return;
      }
      this.currentModuleId = module.id;
      this.saveNavigationState();
      this.refreshTreeView();
    }

    refreshTreeView() {
      if (!this.treeHost || !this.treeHost.length) {
        return;
      }
      this.initializeTreeView(this.buildTreeData());
    }

    getTreeTemplate() {
      return kendo.template(
        "# if (item.programId) { #" +
        "<span class=\"home-tree-line\" data-program-id=\"#: item.programId #\">" +
        "# } else { #" +
        "<span class=\"home-tree-line\">" +
        "# } #" +
          "<span class=\"home-tree-icon k-icon k-i-#: item.iconClass #\"></span>" +
          "<span class=\"home-tree-text\">#: item.text #</span>" +
          "# if (item.programId) { #" +
          "<button type=\"button\" class=\"home-tree-open-button\" data-program-id=\"#: item.programId #\" title=\"Abrir em nova aba\" aria-label=\"Abrir #: item.text # em nova aba\"></button>" +
          "# } #" +
        "</span>"
      );
    }

    bindTreeOpenButtons() {
      const buttons = this.treeHost.find(".home-tree-open-button");
      buttons.each(function() {
        const button = $(this);
        if (!button.data("kendoButton")) {
          button.kendoButton({ icon: "hyperlink-open" });
        }
      });

      buttons.off(".homeTreeOpen")
        .on("mousedown.homeTreeOpen", function(event) {
          event.preventDefault();
          event.stopImmediatePropagation();
          return false;
        })
        .on("click.homeTreeOpen", (event) => {
          event.preventDefault();
          event.stopImmediatePropagation();
          this.openProgramExternalById($(event.currentTarget).attr("data-program-id"));
          return false;
        })
        .on("keydown.homeTreeOpen", (event) => {
          if (event.key !== "Enter" && event.key !== " ") {
            return;
          }
          event.preventDefault();
          event.stopImmediatePropagation();
          this.openProgramExternalById($(event.currentTarget).attr("data-program-id"));
        });
    }

    handleTreeSelect(event) {
      if (!this.treeView || !event || !event.node) {
        return;
      }
      const item = this.treeView.dataItem(event.node);
      if (!item || !item.programId) {
        if (typeof event.preventDefault === "function") {
          event.preventDefault();
        }
        return;
      }
      this.openProgram(item.programId);
      this.collapseSidebar();
    }

    openInitialProgram() {
      const initialId = this.getRequestedInitialProgramId() ||
        this.getSavedInitialProgramId() ||
        this.definition.layout.initialProgramId ||
        (this.definition.programs[0] && this.definition.programs[0].id);
      return this.openProgram(initialId, { syncModule: false });
    }

    getSavedInitialProgramId() {
      const savedProgramId = String(this.savedProgramId || "").trim();
      if (!savedProgramId) {
        return "";
      }
      return this.findProgram(savedProgramId) ? savedProgramId : "";
    }

    getRequestedInitialProgramId() {
      const programId = String(this.options.initialProgramId || "").trim();
      if (!programId) {
        return "";
      }
      return this.findProgram(programId) ? programId : "";
    }

    openProgram(programId, options) {
      const openOptions = options || {};
      if (this.sessionRevoked) {
        return Promise.resolve();
      }
      if (this.systemUpdatesBlocked) {
        this.openSystemUpdateBlockWindow(this.systemUpdatesSummary || {});
        return Promise.resolve();
      }
      if (!openOptions.skipUnsavedCheck) {
        return this.confirmCurrentProgramNavigation().then((confirmed) => {
          if (!confirmed) {
            return null;
          }
          return this.openProgram(programId, Object.assign({}, openOptions, { skipUnsavedCheck: true }));
        });
      }
      const program = this.findProgram(programId);
      if (!program) {
        this.renderContentMessage("Programa nao encontrado.", "error");
        return Promise.resolve();
      }
      if (!this.hasPermission(program.permission)) {
        this.renderContentMessage("Acesso ao programa nao permitido.", "error");
        return Promise.resolve();
      }

      this.destroyCurrentProgram();
      this.currentProgram = program;
      this.savedProgramId = String(program.id || "");
      if (openOptions.syncModule !== false) {
        this.syncModuleWithProgram(program.id);
      }
      this.updateProgramHeader(program);
      this.updateActiveMenu(program.id);
      this.renderContentMessage("Carregando...");
      this.saveNavigationState();

      if (program.type === "crud") {
        return this.renderCrudProgram(program);
      }
      if (program.type === "process") {
        return this.renderProcessProgram(program);
      }
      if (program.type === "analytics") {
        return this.renderAnalyticsProgram(program);
      }
      if (program.type === "report") {
        return this.renderReportProgram(program);
      }
      if (program.type === "custom") {
        return this.renderCustomProgram(program);
      }
      if (program.type === "html") {
        return this.renderHtmlProgram(program);
      }
      return this.renderIframeProgram(program);
    }

    renderIframeProgram(program) {
      this.contentRoot.empty();
      const frame = $("<iframe class=\"home-program-frame\"></iframe>")
        .attr("title", program.title)
        .attr("src", this.getProgramFrameUrl(program))
        .appendTo(this.contentRoot);
      this.currentProgramFrame = frame[0];
      frame.on("load", () => this.prepareProgramFrame(frame[0]));
      if (program.sandbox) {
        frame.attr("sandbox", program.sandbox);
      }
      return Promise.resolve();
    }

    renderCrudProgram(program) {
      this.contentRoot.empty();
      const rootId = "home-crud-program-" + this.normalizeDomId(program.id);
      $("<main></main>")
        .attr("id", rootId)
        .addClass("home-crud-root crud-app-shell")
        .appendTo(this.contentRoot);

      const engine = new global.CrudEngine({
        root: "#" + rootId,
        screenId: program.screenId,
        definitionUrl: program.definitionUrl,
        definition: program.definition,
        config: this.getEmbeddedProgramConfig(),
        security: this.securityPolicy,
        hideHeader: true,
        hideThemeSwitch: true,
        runtimeMessages: false,
        onLastUpdated: (date) => this.updateProgramLastUpdated(date),
        httpClient: this.httpClient
      });
      this.currentProgramEngine = engine;
      return engine.init().then((instance) => {
        if (this.currentProgram && this.currentProgram.id === program.id) {
          this.currentProgramEngine = instance;
          this.updateProgramHeader(program, instance.definition);
        }
        return instance;
      });
    }

    renderProcessProgram(program) {
      this.contentRoot.empty();
      const rootId = "home-process-program-" + this.normalizeDomId(program.id);
      $("<main></main>")
        .attr("id", rootId)
        .addClass("home-process-root crud-app-shell")
        .appendTo(this.contentRoot);

      const engine = new global.ProcessEngine({
        root: "#" + rootId,
        screenId: program.screenId,
        definitionUrl: program.definitionUrl,
        definition: program.definition,
        config: this.getEmbeddedProgramConfig(),
        security: this.securityPolicy,
        hideHeader: true,
        onLastUpdated: (date) => this.updateProgramLastUpdated(date),
        onJobCompleted: (event) => this.handleProcessJobCompleted(event),
        httpClient: this.httpClient
      });
      this.currentProgramEngine = engine;
      return engine.init().then((instance) => {
        if (this.currentProgram && this.currentProgram.id === program.id) {
          this.currentProgramEngine = instance;
          this.updateProgramHeader(program, instance.definition);
        }
        return instance;
      });
    }

    renderAnalyticsProgram(program) {
      this.contentRoot.empty();
      const rootId = "home-analytics-program-" + this.normalizeDomId(program.id);
      $("<main></main>")
        .attr("id", rootId)
        .addClass("home-analytics-root crud-app-shell")
        .appendTo(this.contentRoot);

      const engine = new global.AnalyticsEngine({
        root: "#" + rootId,
        screenId: program.screenId,
        definitionUrl: program.definitionUrl,
        definition: program.definition,
        config: this.getEmbeddedProgramConfig(),
        security: this.securityPolicy,
        hideHeader: true,
        onLastUpdated: (date) => this.updateProgramLastUpdated(date),
        httpClient: this.httpClient
      });
      this.currentProgramEngine = engine;
      return engine.init().then((instance) => {
        if (this.currentProgram && this.currentProgram.id === program.id) {
          this.currentProgramEngine = instance;
          this.updateProgramHeader(program, instance.definition);
        }
        return instance;
      });
    }

    renderReportProgram(program) {
      this.contentRoot.empty();
      const rootId = "home-report-program-" + this.normalizeDomId(program.id);
      $("<main></main>")
        .attr("id", rootId)
        .addClass("home-report-root crud-app-shell")
        .appendTo(this.contentRoot);

      const engine = new global.ReportEngine({
        root: "#" + rootId,
        screenId: program.screenId,
        definitionUrl: program.definitionUrl,
        definition: program.definition,
        config: this.getEmbeddedProgramConfig(),
        security: this.securityPolicy,
        hideHeader: true,
        onLastUpdated: (date) => this.updateProgramLastUpdated(date),
        httpClient: this.httpClient
      });
      this.currentProgramEngine = engine;
      return engine.init().then((instance) => {
        if (this.currentProgram && this.currentProgram.id === program.id) {
          this.currentProgramEngine = instance;
          this.updateProgramHeader(program, instance.definition);
        }
        return instance;
      });
    }

    renderCustomProgram(program) {
      this.contentRoot.empty();
      const rootId = "home-custom-program-" + this.normalizeDomId(program.id);
      $("<main></main>")
        .attr("id", rootId)
        .addClass("home-custom-root custom-page-shell")
        .appendTo(this.contentRoot);

      const engine = new global.CustomPageEngine({
        root: "#" + rootId,
        screenId: program.screenId,
        definitionUrl: program.definitionUrl,
        definition: program.definition,
        config: this.getEmbeddedProgramConfig(),
        hideHeader: true,
        hideThemeSwitch: true,
        httpClient: this.httpClient
      });
      this.currentProgramEngine = engine;
      return engine.init().then((instance) => {
        if (this.currentProgram && this.currentProgram.id === program.id) {
          this.currentProgramEngine = instance;
          this.updateProgramHeader(program, instance.definition);
        }
        return instance;
      });
    }

    getEmbeddedProgramConfig() {
      const config = global.CrudUtils.clone(this.config || {});
      config.theme = Object.assign({}, config.theme || {}, {
        allowUserSwitch: false
      });
      return config;
    }

    getProgramFrameUrl(program) {
      const url = program && program.url ? program.url : "";
      if (!url) {
        return url;
      }
      const themedUrl = this.hasGlobalThemeSwitch()
        ? this.appendUrlParameter(url, "hideThemeSwitch", "1")
        : url;
      return this.appendUrlParameter(themedUrl, "hideProgramHeader", "1");
    }

    hasGlobalThemeSwitch() {
      return this.definition.layout.appbar.showThemeSwitch !== false && this.config.theme.allowUserSwitch !== false;
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

    prepareProgramFrame(frame) {
      if (!frame) {
        return;
      }
      this.hideFrameProgramHeader(frame);
      if (this.hasGlobalThemeSwitch()) {
        this.hideFrameThemeToggle(frame);
        this.syncFrameTheme(frame, this.currentTheme);
      }
    }

    hideFrameThemeToggle(frame) {
      try {
        const doc = frame.contentDocument || frame.contentWindow.document;
        if (!doc || doc.getElementById("home-hide-embedded-theme-toggle")) {
          return;
        }
        const style = doc.createElement("style");
        style.id = "home-hide-embedded-theme-toggle";
        style.textContent = ".crud-theme-toggle{display:none!important;}";
        (doc.head || doc.documentElement).appendChild(style);
      } catch (_) {
        return;
      }
    }

    hideFrameProgramHeader(frame) {
      try {
        const doc = frame.contentDocument || frame.contentWindow.document;
        if (!doc || doc.getElementById("home-hide-embedded-program-header")) {
          return;
        }
        const style = doc.createElement("style");
        style.id = "home-hide-embedded-program-header";
        style.textContent = ".crud-header{display:none!important;}";
        (doc.head || doc.documentElement).appendChild(style);
      } catch (_) {
        return;
      }
    }

    syncFrameTheme(frame, mode) {
      this.postFrameTheme(frame, mode);
      try {
        const frameWindow = frame.contentWindow;
        const doc = frame.contentDocument || frameWindow.document;
        if (!doc) {
          return;
        }
        const link = doc.getElementById("kendo-theme-link");
        const href = this.config && this.config.theme && this.config.theme.kendoTheme;
        if (link && href) {
          link.setAttribute("href", href);
        }
        if (doc.body) {
          doc.body.setAttribute("data-crud-theme", mode);
        }
        const targets = [
          doc.documentElement,
          doc.body
        ].filter(Boolean);
        const theme = this.config && this.config.theme ? this.config.theme : {};
        const tokens = theme.tokens && theme.tokens[mode] ? theme.tokens[mode] : {};
        this.applyThemeCssMap(targets, this.getThemeTokenMap(), tokens);
        this.applyThemeCssMap(targets, this.getKendoThemeTokenMap(), tokens);
      } catch (_) {
        return;
      }
    }

    postFrameTheme(frame, mode) {
      try {
        if (frame && frame.contentWindow && typeof frame.contentWindow.postMessage === "function") {
          frame.contentWindow.postMessage({
            type: "homeThemeChange",
            theme: mode
          }, "*");
        }
      } catch (_) {
        return;
      }
    }

    renderHtmlProgram(program) {
      this.contentRoot.empty();
      if (program.html) {
        this.injectSafeHtml(program.html);
        return Promise.resolve();
      }

      return this.loadText(program.htmlUrl).then((html) => {
        if (this.currentProgram && this.currentProgram.id === program.id) {
          this.contentRoot.empty();
          this.injectSafeHtml(html);
        }
      }).catch((error) => {
        this.renderContentMessage(error && error.message ? error.message : "Erro ao carregar HTML.", "error");
      });
    }

    injectSafeHtml(html) {
      const nodes = this.sanitizeHtml(html);
      const fragment = document.createDocumentFragment();
      nodes.forEach(function(node) {
        fragment.appendChild(node);
      });
      this.contentRoot[0].appendChild(fragment);
    }

    sanitizeHtml(html) {
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(html || ""), "text/html");
      const blockedTags = ["SCRIPT", "STYLE", "IFRAME", "OBJECT", "EMBED", "BASE", "META", "LINK"];
      Array.prototype.slice.call(doc.body.querySelectorAll("*")).forEach((element) => {
        if (blockedTags.indexOf(element.tagName) !== -1) {
          element.remove();
          return;
        }
        Array.prototype.slice.call(element.attributes).forEach((attribute) => {
          const name = attribute.name.toLowerCase();
          const value = String(attribute.value || "").trim();
          if (name.indexOf("on") === 0 || name === "style" || /^javascript:/i.test(value)) {
            element.removeAttribute(attribute.name);
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

    loadText(url) {
      return fetch(url).then(function(response) {
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

    refreshProgram() {
      if (!this.currentProgram) {
        return;
      }
      this.openProgram(this.currentProgram.id);
    }

    openProgramExternalById(programId) {
      const program = this.findProgram(programId);
      if (!program) {
        return;
      }
      const url = this.getProgramOpenUrl(program);
      if (!url) {
        global.CrudUtils.showMessage("Este programa nao possui URL externa.", "info");
        return;
      }
      global.open(url, "_blank", "noopener,noreferrer");
    }

    getProgramOpenUrl(program) {
      if (!program) {
        return "";
      }
      return program.openUrl || program.url || program.htmlUrl || "";
    }

    updateProgramHeader(program, programDefinition) {
      const info = this.getProgramHeaderInfo(program, programDefinition);
      this.currentHeaderInfo = info;
      this.programTitleElement.text(info.title || this.definition.app.title);

      if (info.version) {
        this.programVersionElement
          .removeAttr("hidden")
          .text("v" + info.version);
      } else {
        this.programVersionElement
          .attr("hidden", true)
          .text("");
      }

      if (info.subtitle) {
        this.programSubtitleElement
          .removeAttr("hidden")
          .attr("title", info.subtitleTooltip || info.subtitle)
          .attr("aria-label", info.subtitleTooltip || info.subtitle)
          .text(info.subtitle);
      } else {
        this.programSubtitleElement
          .attr("hidden", true)
          .removeAttr("title")
          .removeAttr("aria-label")
          .text("");
      }

      this.updateProgramLastUpdated(new Date());
      this.updateProgramFavoriteButton();
      this.renderProgramHeaderActions(info);
    }

    getProgramHeaderInfo(program, programDefinition) {
      const source = programDefinition || {};
      const programMeta = source.program || {};
      const fallback = program || {};
      return {
        id: source.id || programMeta.id || fallback.id || "",
        code: source.code || programMeta.code || fallback.code || fallback.programCode || fallback.id || "",
        title: source.title || programMeta.title || fallback.title || this.definition.app.title,
        version: source.programVersion || programMeta.version || fallback.version || fallback.programVersion || "",
        subtitle: source.subtitle || programMeta.subtitle || fallback.subtitle || fallback.description || "",
        subtitleTooltip: source.subtitleTooltip || programMeta.subtitleTooltip || fallback.subtitleTooltip || "",
        screenId: source.screenId || programMeta.screenId || fallback.screenId || "",
        type: fallback.type || "",
        help: source.help || programMeta.help || fallback.help || null,
        logs: source.logs || programMeta.logs || fallback.logs || null
      };
    }

    updateProgramLastUpdated(date) {
      if (!this.programLastUpdatedElement) {
        return;
      }
      const value = date instanceof Date ? date : new Date();
      this.programLastUpdatedElement
        .attr("title", "Data e hora da ultima atualizacao")
        .text(kendo.toString(value, "dd/MM/yyyy HH:mm"));
    }

    renderProgramHeaderActions(info) {
      if (!this.programActionsElement) {
        return;
      }
      kendo.destroy(this.programActionsElement);
      this.programActionsElement.empty();
      if (this.isProgramLogsEnabled(info)) {
        this.renderProgramLogsButton(this.programActionsElement, info);
      }
      if (this.isProgramHelpEnabled(info)) {
        this.renderProgramHelpButton(this.programActionsElement, info);
      }
    }

    isProgramLogsEnabled(info) {
      if (this.currentProgramEngine && typeof this.currentProgramEngine.isLogsEnabled === "function") {
        return this.currentProgramEngine.isLogsEnabled();
      }
      const logs = info && info.logs ? info.logs : {};
      return logs.enabled !== false && (
        typeof logs.url === "string" && logs.url.trim() !== "" ||
        logs.documentId ||
        logs.endpointId ||
        logs.actionId
      );
    }

    renderProgramLogsButton(container, info) {
      const logs = info && info.logs ? info.logs : {};
      const title = logs.title || "Logs";
      const button = $("<button type=\"button\" class=\"home-icon-button crud-log-button crud-icon-button\"></button>")
        .attr("title", title)
        .attr("aria-label", title)
        .appendTo(container);
      button.kendoButton({ icon: "list-unordered" });
      button.on("click", () => this.openProgramLogs(info));
    }

    openProgramLogs(info) {
      if (this.currentProgramEngine && typeof this.currentProgramEngine.openLogsWindow === "function") {
        this.currentProgramEngine.openLogsWindow();
        return;
      }
      const logs = info && info.logs ? info.logs : {};
      if (!logs || logs.enabled === false) {
        return;
      }
      const screenId = info && info.id || "home";
      const url = global.CrudUtils.resolveDocumentUrlForPolicy(logs, "logs", screenId, this.securityPolicy);
      if (!url) {
        return;
      }
      this.openUrlWindow({
        title: logs.title || "Logs",
        url,
        linkText: logs.linkText || "Abrir logs em nova aba"
      });
    }

    isProgramHelpEnabled(info) {
      if (this.currentProgramEngine && typeof this.currentProgramEngine.isHelpEnabled === "function") {
        return this.currentProgramEngine.isHelpEnabled();
      }
      const help = info && info.help ? info.help : {};
      return help.enabled !== false && Boolean(
        help.body ||
        help.summary ||
        help.linkUrl ||
        help.videoUrl ||
        global.CrudUtils.ensureArray(help.items).length
      );
    }

    renderProgramHelpButton(container, info) {
      const help = info && info.help ? info.help : {};
      const title = help.title || "Ajuda e novidades";
      const wrapper = $("<span class=\"crud-help-button-wrap\"></span>").appendTo(container);
      const button = $("<button type=\"button\" class=\"home-icon-button crud-help-button crud-icon-button\"></button>")
        .attr("title", title)
        .attr("aria-label", title)
        .appendTo(wrapper);
      button.kendoButton({ icon: "question-circle" });
      button.on("click", () => this.openProgramHelp(info));
      this.programHelpBadgeElement = $("<span class=\"crud-help-badge k-badge k-badge-solid k-badge-solid-error k-rounded-full\" hidden></span>")
        .appendTo(wrapper);
      this.updateProgramHelpBadge();
    }

    updateProgramHelpBadge() {
      if (!this.programHelpBadgeElement) {
        return;
      }
      const count = this.currentProgramEngine && typeof this.currentProgramEngine.getUnseenHelpItems === "function"
        ? this.currentProgramEngine.getUnseenHelpItems().length
        : 0;
      if (!count) {
        this.programHelpBadgeElement.attr("hidden", true).text("");
        return;
      }
      this.programHelpBadgeElement.removeAttr("hidden").text(String(count));
    }

    openProgramHelp(info) {
      if (this.currentProgramEngine && typeof this.currentProgramEngine.openHelpWindow === "function") {
        this.currentProgramEngine.openHelpWindow();
        this.updateProgramHelpBadge();
        return;
      }
      const help = info && info.help ? info.help : {};
      this.openProgramHelpWindow(help);
    }

    openProgramHelpWindow(help) {
      const items = this.normalizeProgramHelpItems(help);
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-help-window-content\"></div>").appendTo(wrapper);
      const list = $("<div class=\"crud-help-list\"></div>").appendTo(content);
      if (!items.length) {
        $("<p class=\"crud-help-empty\"></p>").text("Nenhuma novidade cadastrada.").appendTo(list);
      }
      items.forEach((item) => this.renderProgramHelpItem(list, item));
      const actions = $("<div class=\"crud-help-actions\"></div>").appendTo(content);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton();
      wrapper.kendoWindow({
        title: help.title || "Ajuda e novidades",
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(720, Math.max(320, window.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });
      const windowWidget = wrapper.data("kendoWindow");
      closeButton.on("click", function() {
        windowWidget.close();
      });
      windowWidget.center().open();
    }

    normalizeProgramHelpItems(help) {
      const source = help || {};
      const items = [];
      if (source.body || source.summary || source.linkUrl || source.videoUrl) {
        items.push(source);
      }
      return items.concat(global.CrudUtils.ensureArray(source.items)).filter(function(item) {
        return item && (item.title || item.summary || item.body || item.linkUrl || item.videoUrl);
      });
    }

    renderProgramHelpItem(container, item) {
      const element = $("<article class=\"crud-help-item\"></article>").appendTo(container);
      $("<h2 class=\"crud-help-item-title\"></h2>").text(item.title || "Ajuda").appendTo(element);
      if (item.summary) {
        $("<p class=\"crud-help-summary\"></p>").text(item.summary).appendTo(element);
      }
      if (item.body) {
        const body = $("<div class=\"crud-help-body\"></div>").appendTo(element);
        String(item.body).split(/\n+/).forEach(function(paragraph) {
          if (paragraph.trim()) {
            $("<p></p>").text(paragraph.trim()).appendTo(body);
          }
        });
      }
      if (item.videoUrl) {
        $("<video class=\"crud-help-video\" controls></video>").attr("src", item.videoUrl).appendTo(element);
      }
      if (item.linkUrl) {
        $("<a class=\"crud-help-link\" target=\"_blank\" rel=\"noopener noreferrer\"></a>")
          .attr("href", item.linkUrl)
          .text(item.linkText || "Abrir ajuda")
          .appendTo(element);
      }
    }

    openProgramSubtitleTooltip() {
      const info = this.currentHeaderInfo || {};
      const text = info.subtitleTooltip || info.subtitle;
      if (!text) {
        return;
      }
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-subtitle-window-content\"></div>").appendTo(wrapper);
      $("<p></p>").text(text).appendTo(content);
      const actions = $("<div class=\"crud-help-actions\"></div>").appendTo(content);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton();
      wrapper.kendoWindow({
        title: info.title || "Informacoes",
        modal: true,
        actions: ["Close"],
        resizable: false,
        width: Math.min(420, Math.max(300, window.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });
      const windowWidget = wrapper.data("kendoWindow");
      closeButton.on("click", function() {
        windowWidget.close();
      });
      windowWidget.center().open();
    }

    openUrlWindow(options) {
      const settings = options || {};
      const wrapper = $("<div></div>").appendTo(document.body);
      const content = $("<div class=\"crud-log-window-content\"></div>").appendTo(wrapper);
      $("<iframe class=\"crud-log-frame\" title=\"Logs\"></iframe>")
        .attr("src", settings.url)
        .appendTo(content);
      const actions = $("<div class=\"crud-log-actions\"></div>").appendTo(content);
      $("<a class=\"crud-log-link\" target=\"_blank\" rel=\"noopener noreferrer\"></a>")
        .attr("href", settings.url)
        .text(settings.linkText || "Abrir em nova aba")
        .appendTo(actions);
      const closeButton = $("<button type=\"button\">Fechar</button>").appendTo(actions);
      closeButton.kendoButton();
      wrapper.kendoWindow({
        title: settings.title || "Logs",
        modal: true,
        actions: ["Maximize", "Close"],
        resizable: true,
        width: Math.min(960, Math.max(320, window.innerWidth - 24)),
        visible: false,
        close: function() {
          wrapper.data("kendoWindow").destroy();
          wrapper.remove();
        }
      });
      const windowWidget = wrapper.data("kendoWindow");
      closeButton.on("click", function() {
        windowWidget.close();
      });
      windowWidget.center().open();
    }

    updateActiveMenu(programId) {
      if (!this.treeView || !this.treeHost) {
        return;
      }
      this.treeHost.find(".home-tree-line").removeClass("is-active").attr("aria-current", "false");
      const line = this.treeHost.find(".home-tree-line[data-program-id='" + this.escapeSelector(programId) + "']");
      line
        .addClass("is-active")
        .attr("aria-current", "page");
      const node = line.closest(".k-treeview-item");
      if (node.length) {
        this.treeView.select(node);
      }
    }

    syncModuleWithProgram(programId) {
      if (this.isAllModulesSelected()) {
        return;
      }
      const moduleId = this.findModuleIdByProgramId(programId);
      if (!moduleId || moduleId === this.currentModuleId || !this.findModule(moduleId)) {
        return;
      }
      this.currentModuleId = moduleId;
      if (this.moduleSelector) {
        this.moduleSelector.value(moduleId);
      }
      this.saveNavigationState();
      if (this.treeHost && this.treeHost.length) {
        this.refreshTreeView();
      }
    }

    toggleSidebar() {
      const sidebar = this.definition && this.definition.layout ? this.definition.layout.sidebar : {};
      if (!this.shell || sidebar.collapsible === false) {
        return;
      }
      this.shell.toggleClass("home-sidebar-collapsed");
      this.updateSidebarToggleState();
      this.saveNavigationState();
    }

    updateSidebarToggleState() {
      if (!this.sidebarToggle || !this.sidebarToggle.length || !this.shell) {
        return;
      }
      const isCollapsed = this.shell.hasClass("home-sidebar-collapsed");
      const label = isCollapsed ? "Expandir menu" : "Recolher menu";
      const icon = isCollapsed ? "chevron-right" : "chevron-left";
      this.sidebarToggle
        .attr("title", label)
        .attr("aria-label", label)
        .attr("aria-expanded", isCollapsed ? "false" : "true")
        .toggleClass("is-collapsed", isCollapsed);
      if (this.sidebarToggleButton && this.currentSidebarToggleIcon !== icon) {
        this.sidebarToggleButton.setOptions({ icon });
        this.currentSidebarToggleIcon = icon;
      }
    }

    shouldStartSidebarCollapsed() {
      if (this.isMobileViewport()) {
        return true;
      }
      if (this.hasSavedSidebarState) {
        return this.savedSidebarCollapsed === true;
      }
      return Boolean(this.definition.layout.sidebar.collapsed);
    }

    isMobileViewport() {
      return Boolean(global.matchMedia && global.matchMedia("(max-width: 860px)").matches);
    }

    collapseSidebar() {
      const sidebar = this.definition && this.definition.layout ? this.definition.layout.sidebar : {};
      if (!this.shell || sidebar.collapsible === false) {
        return;
      }
      this.shell.addClass("home-sidebar-collapsed");
      this.updateSidebarToggleState();
      this.saveNavigationState();
    }

    confirmCurrentProgramNavigation() {
      if (!this.currentProgramEngine || typeof this.currentProgramEngine.confirmDiscardChanges !== "function") {
        return Promise.resolve(true);
      }
      return this.currentProgramEngine.confirmDiscardChanges();
    }

    startRuntimeMessagePolling() {
      if (this.sessionRevoked) {
        return;
      }
      this.stopRuntimeMessagePolling();
      const appbar = this.definition && this.definition.layout && this.definition.layout.appbar || {};
      const config = appbar.runtimeMessages || this.definition.runtimeMessages || {};
      const seconds = Math.max(10, Number(config.pollIntervalSeconds || 30));
      if (config.enabled === false) {
        return;
      }
      if (this.startRuntimeMessageEvents(config, seconds)) {
        return;
      }
      this.startRuntimeMessageTimer(seconds);
    }

    startRuntimeMessageTimer(seconds) {
      const endpoint = this.getRuntimeMessageEndpoint("poll");
      if (!endpoint || !endpoint.url || this.sessionRevoked) {
        return;
      }
      this.pollRuntimeMessages();
      this.runtimeMessageTimer = window.setInterval(() => {
        this.pollRuntimeMessages();
      }, seconds * 1000);
    }

    stopRuntimeMessagePolling() {
      if (this.runtimeMessageTimer) {
        window.clearInterval(this.runtimeMessageTimer);
        this.runtimeMessageTimer = null;
      }
      this.stopRuntimeMessageEvents();
    }

    startRuntimeMessageEvents(config, fallbackSeconds) {
      const events = config.events || {};
      if (events.enabled === false || typeof global.EventSource !== "function" || global.location && global.location.protocol === "file:") {
        return false;
      }
      const url = this.getRuntimeEventSourceUrl(config);
      if (!url) {
        return false;
      }

      let source = null;
      try {
        source = new global.EventSource(url);
      } catch (_) {
        return false;
      }

      this.runtimeEventSource = source;
      source.onopen = () => {
        if (this.runtimeEventFallbackTimer) {
          window.clearTimeout(this.runtimeEventFallbackTimer);
          this.runtimeEventFallbackTimer = null;
        }
        if (this.runtimeMessageTimer) {
          window.clearInterval(this.runtimeMessageTimer);
          this.runtimeMessageTimer = null;
        }
      };
      source.addEventListener("runtime-messages", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        this.handleRuntimeMessages(payload.messages || []);
      });
      source.addEventListener("runtime-error", (event) => {
        const payload = this.parseRuntimeEventPayload(event);
        this.handleRuntimeEventError(payload.error || {});
      });
      source.onerror = () => {
        if (this.sessionRevoked || this.runtimeEventFallbackTimer) {
          return;
        }
        this.runtimeEventFallbackTimer = window.setTimeout(() => {
          if (this.runtimeEventSource === source && source.readyState !== global.EventSource.OPEN && !this.sessionRevoked) {
            this.stopRuntimeMessageEvents();
            this.startRuntimeMessageTimer(fallbackSeconds);
          }
        }, 10000);
      };

      return true;
    }

    stopRuntimeMessageEvents() {
      if (this.runtimeEventFallbackTimer) {
        window.clearTimeout(this.runtimeEventFallbackTimer);
        this.runtimeEventFallbackTimer = null;
      }
      if (this.runtimeEventSource) {
        this.runtimeEventSource.onopen = null;
        this.runtimeEventSource.onerror = null;
        this.runtimeEventSource.close();
        this.runtimeEventSource = null;
      }
    }

    getRuntimeEventSourceUrl(config) {
      const policyEndpoint = this.securityPolicy && this.securityPolicy.endpoints && this.securityPolicy.endpoints.runtimeEventsEndpoint || {};
      const events = config && config.events || {};
      const configuredUrl = this.securityPolicy && this.securityPolicy.production ? "" : (events.url || config && config.eventSourceUrl || "");
      const url = configuredUrl || policyEndpoint.url || "";
      if (!url) {
        return "";
      }
      const screenId = this.getHomeScreenId();
      const eventUrl = global.CrudUtils.replaceUrlParams(url, { screenId });
      if (this.httpClient && typeof this.httpClient.buildRuntimeEventUrl === "function") {
        return this.httpClient.buildRuntimeEventUrl(eventUrl, {
          screenId,
          channel: "home"
        });
      }
      return eventUrl;
    }

    parseRuntimeEventPayload(event) {
      try {
        return JSON.parse(event && event.data || "{}");
      } catch (_) {
        return {};
      }
    }

    buildEndpointEventUrl(url, params, context) {
      if (!url) {
        return "";
      }
      const extra = Object.assign({ stream: 1 }, params || {});
      const source = context && typeof context === "object" ? context : {};
      ["appId", "appTitle", "programId", "programCode", "programTitle", "programScreenId", "programType", "moduleId"].forEach(function(field) {
        if (source[field]) {
          extra["context" + field.charAt(0).toUpperCase() + field.slice(1)] = source[field];
        }
      });
      if (this.httpClient && typeof this.httpClient.buildRuntimeEventUrl === "function") {
        return this.httpClient.buildRuntimeEventUrl(url, extra);
      }
      return url;
    }

    requestHomeEndpoint(endpoint, data) {
      const method = String(endpoint && endpoint.method || "POST").toUpperCase();
      const request = {
        url: endpoint.url,
        method,
        data
      };
      if (method === "GET" && data && typeof data === "object") {
        request.url = this.buildHomeGetUrl(endpoint.url, data);
        request.data = undefined;
      }
      return this.httpClient.request(request);
    }

    buildHomeGetUrl(url, data) {
      const base = global.location && global.location.href || "http://localhost/";
      const target = new URL(url, base);
      this.appendHomeQueryData(target.searchParams, data || {}, "");
      return target.href;
    }

    appendHomeQueryData(searchParams, value, prefix) {
      const currentPrefix = String(prefix || "");
      if (value == null) {
        return;
      }
      if (Array.isArray(value)) {
        value.forEach((item, index) => this.appendHomeQueryData(searchParams, item, currentPrefix ? currentPrefix + "." + index : String(index)));
        return;
      }
      if (typeof value === "object") {
        Object.keys(value).forEach((key) => {
          this.appendHomeQueryData(searchParams, value[key], currentPrefix ? currentPrefix + "." + key : key);
        });
        return;
      }
      if (!currentPrefix) {
        return;
      }
      searchParams.set(currentPrefix, String(value));
    }

    resolveLastChatMessageId(messages, current) {
      let lastId = Number(current || 0);
      global.CrudUtils.ensureArray(messages).forEach(function(message) {
        const id = Number(message && message.id ? String(message.id).replace(/^msg-/, "") : 0);
        if (id > lastId) {
          lastId = id;
        }
      });
      return lastId;
    }

    handleRuntimeEventError(error) {
      const normalized = error && error.code ? error : { code: "RUNTIME_EVENT_ERROR", message: "Canal de eventos indisponivel.", details: {} };
      if (normalized.code === "SESSION_REVOKED" || normalized.code === "SESSION_EXPIRED") {
        this.handleSessionRevoked(normalized.message, normalized.details || {});
        return;
      }
      if (!this.runtimeMessageTimer && !this.sessionRevoked) {
        this.stopRuntimeMessageEvents();
        this.startRuntimeMessageTimer(30);
      }
    }

    pollRuntimeMessages() {
      const endpoint = this.getRuntimeMessageEndpoint("poll");
      if (!endpoint || !endpoint.url || this.sessionRevoked) {
        return Promise.resolve([]);
      }
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: {
          context: this.buildChatContextPayload()
        }
      }).then((response) => {
        const messages = global.CrudUtils.ensureArray(response && response.messages);
        this.handleRuntimeMessages(messages);
        return messages;
      }).catch((error) => {
        const normalized = global.CrudUtils.unwrapError(error, "");
        if (normalized.code === "SESSION_REVOKED") {
          this.handleSessionRevoked(normalized.message, normalized.details || {});
        }
        return [];
      });
    }

    handleRuntimeMessages(messages) {
      const ids = [];
      global.CrudUtils.ensureArray(messages).forEach((message) => {
        if (!message) {
          return;
        }
        if (message.id != null) {
          ids.push(message.id);
        }
        if (message.type === "force_logout") {
          this.handleSessionRevoked(message.message || "Sua sessao foi encerrada.", message);
          return;
        }
        global.CrudUtils.showMessage(message.message || message.title, message.severity || "info");
      });
      if (ids.length) {
        this.ackRuntimeMessages(ids);
      }
    }

    ackRuntimeMessages(ids) {
      const endpoint = this.getRuntimeMessageEndpoint("ack");
      if (!endpoint || !endpoint.url) {
        return Promise.resolve(false);
      }
      return this.httpClient.request({
        url: endpoint.url,
        method: endpoint.method || "POST",
        data: { ids }
      }).catch(function() {
        return false;
      });
    }

    handleSessionRevoked(message, details) {
      if (this.sessionRevoked) {
        return;
      }
      this.sessionRevoked = true;
      this.clearLocalSessionContext(true);
      this.stopRuntimeMessagePolling();
      if (this.currentProgramEngine && typeof this.currentProgramEngine.handleSessionRevoked === "function") {
        this.currentProgramEngine.handleSessionRevoked(message, details || {});
      }
      if (this.shell) {
        this.shell.addClass("home-session-revoked");
      }
      global.CrudUtils.blockingMessage("Sessao encerrada", message || "Sua sessao foi encerrada.", {
        buttonText: "Sessao encerrada",
        themeColor: "primary"
      });
    }

    getRuntimeMessageEndpoint(name) {
      const appbar = this.definition && this.definition.layout && this.definition.layout.appbar || {};
      const group = appbar.runtimeMessages || this.definition.runtimeMessages || {};
      const endpoints = group.endpoints || {};
      if (endpoints[name]) {
        return endpoints[name];
      }
      const endpointIds = {
        poll: "runtime.messages.poll",
        ack: "runtime.messages.ack",
        forceLogout: "runtime.admin.forceLogout"
      };
      const endpointId = endpointIds[name];
      if (!endpointId) {
        return null;
      }
      return global.CrudUtils.resolveEndpointForPolicy({ endpointId, method: "POST" }, endpointId, this.getHomeScreenId(), this.securityPolicy);
    }

    destroyCurrentProgram() {
      if (this.currentProgramEngine) {
        if (typeof this.currentProgramEngine.destroy === "function") {
          this.currentProgramEngine.destroy();
        } else {
          if (this.currentProgramEngine.gridRenderer && typeof this.currentProgramEngine.gridRenderer.destroy === "function") {
            this.currentProgramEngine.gridRenderer.destroy();
          }
          if (this.currentProgramEngine.filterRenderer && typeof this.currentProgramEngine.filterRenderer.destroy === "function") {
            this.currentProgramEngine.filterRenderer.destroy();
          }
        }
      }
      if (this.contentRoot && this.contentRoot.length) {
        kendo.destroy(this.contentRoot);
        this.contentRoot.empty();
      }
      this.currentProgramEngine = null;
      this.currentProgramFrame = null;
    }

    findProgram(programId) {
      return global.CrudUtils.ensureArray(this.definition && this.definition.programs).find(function(program) {
        return program && program.id === programId;
      }) || null;
    }

    findProgramByScreenId(screenId) {
      const id = String(screenId || "").trim();
      if (!id) {
        return null;
      }
      return global.CrudUtils.ensureArray(this.definition && this.definition.programs).find(function(program) {
        return program && String(program.screenId || "").trim() === id;
      }) || null;
    }

    buildRuntimeScreenUrl(screenId, query) {
      const id = String(screenId || "").trim();
      if (!id) {
        return "";
      }
      try {
        const current = new URL(global.location.href);
        const path = /\/production\//.test(current.pathname) ? "app.html" : "production/app.html";
        const target = new URL(path, current.href);
        target.searchParams.set("screenId", id);
        this.appendScreenQueryParams(target.searchParams, query);
        return target.href;
      } catch (_) {
        let target = "production/app.html?screenId=" + encodeURIComponent(id);
        const extra = this.stringifyScreenQueryParams(query);
        if (extra) {
          target += "&" + extra;
        }
        return target;
      }
    }

    appendScreenQueryParams(searchParams, query) {
      if (!searchParams || !query || typeof query !== "object") {
        return;
      }
      Object.keys(query).forEach((key) => {
        const value = query[key];
        if (value == null || String(value).trim() === "") {
          return;
        }
        searchParams.set(key, String(value));
      });
    }

    stringifyScreenQueryParams(query) {
      if (!query || typeof query !== "object") {
        return "";
      }
      const pairs = [];
      Object.keys(query).forEach((key) => {
        const value = query[key];
        if (value == null || String(value).trim() === "") {
          return;
        }
        pairs.push(encodeURIComponent(key) + "=" + encodeURIComponent(String(value)));
      });
      return pairs.join("&");
    }

    hasPermission(permission) {
      if (!permission) {
        return true;
      }
      return Boolean(this.definition.permissions && this.definition.permissions[permission]);
    }

    renderLoading() {
      this.root.empty().append($("<section class=\"crud-loading\"></section>").text("Carregando pagina inicial..."));
    }

    renderError(error) {
      this.root.empty();
      const productionErrors = this.options.productionErrors === true ||
        global.CrudUtils.isProductionSecurity(this.securityPolicy);
      const message = productionErrors
        ? "Nao foi possivel carregar a pagina inicial. Entre em contato com o suporte."
        : error && error.message ? error.message : "Erro ao carregar pagina inicial.";
      $("<section class=\"crud-message crud-message-error\"></section>").text(message).appendTo(this.root);
    }

    renderContentMessage(message, type) {
      if (!this.contentRoot || !this.contentRoot.length) {
        return;
      }
      this.contentRoot.empty();
      $("<section></section>")
        .addClass("crud-message")
        .toggleClass("crud-message-error", type === "error")
        .text(message || "")
        .appendTo(this.contentRoot);
    }

    renderThemeToggle(container) {
      const field = $("<label class=\"crud-theme-toggle home-theme-toggle\"></label>").appendTo(container);
      const input = $("<input type=\"checkbox\" class=\"crud-theme-input\">")
        .prop("checked", this.currentTheme === "dark")
        .appendTo(field);
      $("<span class=\"crud-theme-track\"><span class=\"crud-theme-thumb\"></span></span>").appendTo(field);
      this.updateThemeToggleLabel(field);
      input.on("change", () => {
        this.toggleTheme(input.is(":checked") ? "dark" : "light");
      });
      this.themeToggleElement = field;
      this.themeInputElement = input;
    }

    updateThemeToggleLabel(element) {
      const label = this.currentTheme === "dark" ? "Tema claro" : "Tema escuro";
      element.attr("title", label).attr("aria-label", label);
    }

    applyKendoTheme() {
      const href = this.config && this.config.theme && this.config.theme.kendoTheme;
      const link = document.getElementById("kendo-theme-link");
      if (href && link) {
        link.setAttribute("href", href);
      }
    }

    resolveInitialTheme() {
      const theme = this.config && this.config.theme ? this.config.theme : {};
      if (theme.persistUserChoice !== false && theme.storageKey && global.localStorage) {
        try {
          const stored = global.localStorage.getItem(theme.storageKey);
          if (stored === "dark" || stored === "light") {
            return stored;
          }
        } catch (_) {
          return theme.defaultMode === "dark" ? "dark" : "light";
        }
      }
      return theme.defaultMode === "dark" ? "dark" : "light";
    }

    toggleTheme(mode) {
      this.currentTheme = mode === "dark" ? "dark" : "light";
      this.applyTheme(this.currentTheme, { persist: true });
      if (this.themeInputElement) {
        this.themeInputElement.prop("checked", this.currentTheme === "dark");
      }
      if (this.themeToggleElement) {
        this.updateThemeToggleLabel(this.themeToggleElement);
      }
    }

    applyTheme(mode, options) {
      const theme = this.config && this.config.theme ? this.config.theme : {};
      const normalizedMode = mode === "dark" ? "dark" : "light";
      this.currentTheme = normalizedMode;
      document.body.setAttribute("data-crud-theme", normalizedMode);
      this.applyThemeTokens(normalizedMode);
      if (options && options.persist && theme.persistUserChoice !== false && theme.storageKey && global.localStorage) {
        try {
          global.localStorage.setItem(theme.storageKey, normalizedMode);
        } catch (_) {
          return;
        }
      }
      this.syncCurrentProgramTheme(normalizedMode);
    }

    applyThemeTokens(mode) {
      const theme = this.config && this.config.theme ? this.config.theme : {};
      const tokens = theme.tokens && theme.tokens[mode] ? theme.tokens[mode] : {};
      const targets = [
        document.documentElement,
        document.body
      ].filter(Boolean);

      this.applyThemeCssMap(targets, this.getThemeTokenMap(), tokens);
      this.applyThemeCssMap(targets, this.getKendoThemeTokenMap(), tokens);
    }

    applyThemeCssMap(targets, tokenMap, tokens) {
      Object.keys(tokenMap).forEach(function(key) {
        const properties = Array.isArray(tokenMap[key]) ? tokenMap[key] : [tokenMap[key]];
        properties.forEach(function(propertyName) {
          targets.forEach(function(target) {
            if (tokens[key]) {
              target.style.setProperty(propertyName, tokens[key]);
            } else {
              target.style.removeProperty(propertyName);
            }
          });
        });
      });
    }

    getThemeTokenMap() {
      return {
        background: "--crud-bg",
        surface: "--crud-surface",
        border: "--crud-border",
        text: "--crud-text",
        muted: "--crud-muted",
        title: "--crud-title",
        accent: "--crud-accent",
        accentSoft: "--crud-accent-soft",
        accentBorder: "--crud-accent-border",
        inputBackground: "--crud-input-bg",
        readonlyBackground: "--crud-readonly-bg",
        messageBackground: "--crud-message-bg",
        errorBorder: "--crud-error-border",
        errorBackground: "--crud-error-bg",
        buttonBackground: "--crud-button-bg",
        buttonHoverBackground: "--crud-button-hover-bg",
        buttonBorder: "--crud-button-border",
        buttonText: "--crud-button-text",
        buttonPrimaryBackground: "--crud-button-primary-bg",
        buttonPrimaryHoverBackground: "--crud-button-primary-hover-bg",
        buttonPrimaryText: "--crud-button-primary-text",
        notificationBackground: "--crud-notification-bg",
        notificationText: "--crud-notification-text",
        danger: "--crud-danger"
      };
    }

    getKendoThemeTokenMap() {
      return {
        background: [
          "--kendo-color-app-surface"
        ],
        surface: [
          "--kendo-color-surface"
        ],
        border: [
          "--kendo-color-border",
          "--kendo-color-base-emphasis"
        ],
        text: [
          "--kendo-color-on-app-surface",
          "--kendo-color-on-base",
          "--kendo-color-base-on-surface"
        ],
        muted: [
          "--kendo-color-subtle",
          "--kendo-color-base-on-subtle"
        ],
        title: [
          "--kendo-color-primary-on-surface",
          "--kendo-color-primary-on-subtle"
        ],
        accent: [
          "--kendo-color-primary",
          "--kendo-color-primary-emphasis",
          "--kendo-color-info",
          "--kendo-color-info-on-surface",
          "--kendo-color-tertiary",
          "--kendo-color-series-a"
        ],
        accentSoft: [
          "--kendo-color-primary-subtle",
          "--kendo-color-primary-subtle-hover",
          "--kendo-color-primary-subtle-active",
          "--kendo-color-info-subtle",
          "--kendo-color-tertiary-subtle"
        ],
        accentBorder: [
          "--kendo-color-border-alt"
        ],
        inputBackground: [
          "--kendo-color-surface-alt"
        ],
        readonlyBackground: [
          "--kendo-color-base-subtle",
          "--kendo-color-base-subtle-active",
          "--kendo-color-base-active"
        ],
        buttonBackground: [
          "--kendo-color-base",
          "--kendo-color-secondary"
        ],
        buttonHoverBackground: [
          "--kendo-color-base-hover",
          "--kendo-color-base-subtle-hover",
          "--kendo-color-secondary-hover"
        ],
        buttonText: [
          "--kendo-color-on-secondary",
          "--kendo-color-secondary-on-subtle",
          "--kendo-color-secondary-on-surface"
        ],
        buttonPrimaryBackground: [
          "--kendo-color-primary"
        ],
        buttonPrimaryHoverBackground: [
          "--kendo-color-primary-hover",
          "--kendo-color-primary-active"
        ],
        buttonPrimaryText: [
          "--kendo-color-on-primary"
        ],
        errorBackground: [
          "--kendo-color-error-subtle",
          "--kendo-color-error-subtle-hover",
          "--kendo-color-error-subtle-active"
        ],
        danger: [
          "--kendo-color-error",
          "--kendo-color-error-hover",
          "--kendo-color-error-active",
          "--kendo-color-error-emphasis",
          "--kendo-color-error-on-surface",
          "--kendo-color-error-on-subtle"
        ]
      };
    }

    syncCurrentProgramTheme(mode) {
      if (!this.currentProgramEngine || typeof this.currentProgramEngine.applyTheme !== "function") {
        if (this.currentProgramFrame) {
          this.syncFrameTheme(this.currentProgramFrame, mode);
        }
        return;
      }
      this.currentProgramEngine.applyTheme(mode, { persist: false });
      if (this.currentProgramEngine.themeInputElement) {
        this.currentProgramEngine.themeInputElement.prop("checked", mode === "dark");
      }
      if (this.currentProgramEngine.themeToggleElement && typeof this.currentProgramEngine.updateThemeToggleLabel === "function") {
        this.currentProgramEngine.updateThemeToggleLabel(this.currentProgramEngine.themeToggleElement);
      }
      if (this.currentProgramFrame) {
        this.syncFrameTheme(this.currentProgramFrame, mode);
      }
    }

    normalizeDomId(value) {
      return String(value || "program")
        .replace(/[^A-Za-z0-9_-]+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "") || "program";
    }

    escapeClass(value) {
      return String(value || "folder").replace(/[^A-Za-z0-9_-]+/g, "-");
    }

    escapeSelector(value) {
      if (global.CSS && typeof global.CSS.escape === "function") {
        return global.CSS.escape(value);
      }
      return String(value || "").replace(/'/g, "\\'");
    }
  }

  global.HomeEngine = HomeEngine;
})(window, jQuery);
