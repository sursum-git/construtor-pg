(function(global) {
  "use strict";

  class CrudHttpClient {
    constructor(options) {
      const settings = options || {};
      this.allowLocalFallback = settings.allowLocalFallback !== false;
      this.authToken = settings.authToken || this.resolveAuthToken();
      this.userId = settings.userId || this.resolveRuntimeUserId();
      this.sessionId = settings.sessionId || this.resolveRuntimeSessionId();
      this.tenantId = settings.tenantId || this.resolveRuntimeTenantId();
    }

    request(options) {
      const request = options || {};
      const headers = Object.assign({
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Runtime-Tenant-Id": this.tenantId,
        "X-Runtime-User-Id": this.userId,
        "X-Runtime-Session-Id": this.sessionId
      }, request.headers || {});
      if (this.authToken) {
        headers.Authorization = "Bearer " + this.authToken;
        headers["X-Runtime-Auth-Token"] = this.authToken;
      }

      return fetch(request.url, {
        method: request.method || "GET",
        headers,
        credentials: request.credentials || "include",
        body: request.method && request.method !== "GET" ? JSON.stringify(request.data || {}) : undefined
      }).then(function(response) {
        return response.text().then(function(text) {
          const payload = text ? JSON.parse(text) : {};
          if (!response.ok) {
            throw payload;
          }
          return payload;
        });
      }).catch((error) => {
        this.handleAuthError(error);
        if (
          this.allowLocalFallback &&
          global.location &&
          global.location.protocol === "file:" &&
          (!request.method || request.method === "GET") &&
          global.CrudUtils &&
          global.CrudUtils.readLocalJson
        ) {
          return global.CrudUtils.readLocalJson(request.url);
        }
        throw error;
      });
    }

    buildRuntimeEventUrl(url, params) {
      const base = global.location && global.location.href || "http://localhost/";
      const target = new URL(url || "/api/runtime/events", base);
      const extra = params || {};
      target.searchParams.set("runtimeTenantId", this.tenantId);
      target.searchParams.set("runtimeUserId", this.userId);
      target.searchParams.set("runtimeSessionId", this.sessionId);
      if (this.authToken) {
        target.searchParams.set("runtimeAuthToken", this.authToken);
      }
      Object.keys(extra).forEach(function(key) {
        if (extra[key] != null && extra[key] !== "") {
          target.searchParams.set(key, String(extra[key]));
        }
      });
      return target.href;
    }

    logout() {
      const headers = {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Runtime-Tenant-Id": this.tenantId,
        "X-Runtime-User-Id": this.userId,
        "X-Runtime-Session-Id": this.sessionId
      };
      if (this.authToken) {
        headers.Authorization = "Bearer " + this.authToken;
        headers["X-Runtime-Auth-Token"] = this.authToken;
      }

      return fetch("/api/auth/logout", {
        method: "POST",
        headers,
        credentials: "include",
        body: JSON.stringify({
          rememberToken: this.readLocalValue("crudEngine.rememberToken") || ""
        })
      }).catch(function() {
        return {};
      }).finally(() => {
        this.clearAuth();
        this.clearRememberToken();
      });
    }

    resolveRuntimeUserId() {
      const params = new URLSearchParams(global.location && global.location.search || "");
      const queryUser = params.get("runtimeUserId") || params.get("demoUserId");
      if (queryUser) {
        this.saveLocalValue("crudEngine.runtimeUserId", queryUser);
        return queryUser;
      }
      return this.readLocalValue("crudEngine.runtimeUserId") || "demo";
    }

    resolveRuntimeSessionId() {
      const params = new URLSearchParams(global.location && global.location.search || "");
      const querySession = params.get("runtimeSessionId") || params.get("sessionId");
      if (querySession) {
        this.saveLocalValue("crudEngine.runtimeSessionId", querySession);
        return querySession;
      }
      const stored = this.readLocalValue("crudEngine.runtimeSessionId");
      if (stored) {
        return stored;
      }
      const value = "sess-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 10);
      this.saveLocalValue("crudEngine.runtimeSessionId", value);
      return value;
    }

    resolveRuntimeTenantId() {
      const params = new URLSearchParams(global.location && global.location.search || "");
      const queryTenant = params.get("runtimeTenantId") || params.get("tenantId");
      if (queryTenant) {
        this.saveLocalValue("crudEngine.runtimeTenantId", queryTenant);
        return queryTenant;
      }
      return this.readLocalValue("crudEngine.runtimeTenantId") || "default";
    }

    resolveAuthToken() {
      const params = new URLSearchParams(global.location && global.location.search || "");
      const queryToken = params.get("runtimeAuthToken") || params.get("authToken");
      if (queryToken) {
        this.saveLocalValue("crudEngine.authToken", queryToken);
        return queryToken;
      }
      return this.readLocalValue("crudEngine.authToken") || "";
    }

    handleAuthError(error) {
      const code = error && error.error && error.error.code || error && error.code || "";
      if (code !== "AUTH_REQUIRED" && code !== "INVALID_AUTH_TOKEN" && code !== "SESSION_REVOKED") {
        return;
      }
      this.clearAuth();
      if (code === "SESSION_REVOKED") {
        this.clearRememberToken();
      }
      this.redirectToLogin(true);
    }

    clearAuth() {
      this.removeLocalValue("crudEngine.authToken");
      this.removeLocalValue("crudEngine.runtimeSessionId");
      this.authToken = "";
    }

    clearRememberToken() {
      this.removeLocalValue("crudEngine.rememberToken");
      this.removeLocalValue("crudEngine.rememberTokenExpiresAt");
    }

    redirectToLogin(includeReturnUrl) {
      if (!global.location || /\/login\.html$/i.test(global.location.pathname || "")) {
        return;
      }
      const current = global.location.href || "";
      const loginUrl = (global.location.pathname || "").indexOf("/production/") >= 0
        ? "login.html"
        : "/production/login.html";
      global.location.href = includeReturnUrl === false ? loginUrl : loginUrl + "?returnUrl=" + encodeURIComponent(current);
    }

    readLocalValue(key) {
      try {
        return global.localStorage ? global.localStorage.getItem(key) : "";
      } catch (_) {
        return "";
      }
    }

    saveLocalValue(key, value) {
      try {
        if (global.localStorage) {
          global.localStorage.setItem(key, String(value));
        }
      } catch (_) {
        return false;
      }
      return true;
    }

    removeLocalValue(key) {
      try {
        if (global.localStorage) {
          global.localStorage.removeItem(key);
        }
      } catch (_) {
        return false;
      }
      return true;
    }
  }

  global.CrudHttpClient = CrudHttpClient;
})(window);
