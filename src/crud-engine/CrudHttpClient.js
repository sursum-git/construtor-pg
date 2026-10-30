(function(global) {
  "use strict";
  const selectedFiles = new Map();

  class CrudHttpClient {
    constructor(options) {
      const settings = options || {};
      this.allowLocalFallback = settings.allowLocalFallback !== false;
      this.authToken = settings.authToken || this.resolveAuthToken();
      this.userId = settings.userId || this.resolveRuntimeUserId();
      this.sessionId = settings.sessionId || this.resolveRuntimeSessionId();
      this.tenantId = settings.tenantId || this.resolveRuntimeTenantId();
    }

    registerFile(field, file, urls) {
      if (!/^[a-z][a-z0-9_]{0,79}$/.test(field) || !(file instanceof global.File) || !file.size || file.size > 20971520 || !Array.isArray(urls) || !urls.length) {
        throw new Error("Selecione um arquivo de ate 20 MB.");
      }
      const allowed = urls.map((url) => this.fileEndpointUrl(url));
      const bytes = new Uint8Array(16); global.crypto.getRandomValues(bytes);
      const token = "local-file:" + Array.from(bytes, (v) => v.toString(16).padStart(2, "0")).join("");
      selectedFiles.set(token, { field, file, urls: allowed }); return token;
    }

    registeredFile(token) { const entry = selectedFiles.get(token); return entry ? entry.file : null; }
    releaseFile(token) { selectedFiles.delete(token); }
    fileEndpointUrl(url) {
      const target = new URL(url, global.location.href);
      if (target.origin !== new URL(global.location.href).origin || !/^\/api\/runtime\/screens\/[^/]+\/endpoints\/[^/]+$/.test(target.pathname) || target.search || target.hash) { throw new Error("Endpoint de arquivo invalido."); }
      return target.href;
    }

    async request(options) {
      const request = options || {};
      const method = request.method || "GET";
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
      const requestUrl = this.buildRequestUrl(request.url, method, request.data);
      let body = method !== "GET" ? JSON.stringify(request.data || {}) : undefined;
      const record = request.data && (request.data.record || request.data);
      const tokens = record && typeof record === "object" ? Object.keys(record).filter((field) => typeof record[field] === "string" && record[field].indexOf("local-file:") === 0) : [];
      if (tokens.length) {
        const entry = selectedFiles.get(record[tokens[0]]);
        if (method !== "POST" || tokens.length !== 1 || !entry || entry.field !== tokens[0] || entry.urls.indexOf(this.fileEndpointUrl(request.url)) < 0) { throw new Error("Selecione novamente o arquivo para este formulario."); }
        body = new global.FormData();body.append("payload", JSON.stringify(request.data));body.append(entry.field, entry.file, entry.file.name);
        Object.keys(headers).forEach((name) => { if (name.toLowerCase() === "content-type") { delete headers[name]; } });
      }

      return fetch(requestUrl, {
        method,
        headers,
        credentials: request.credentials || "include",
        body
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

    async download(options) {
      const request = options || {};
      const headers = { "Accept": "application/octet-stream", "Content-Type": "application/json", "X-Runtime-Tenant-Id": this.tenantId, "X-Runtime-User-Id": this.userId, "X-Runtime-Session-Id": this.sessionId };
      if (this.authToken) { headers.Authorization = "Bearer " + this.authToken; headers["X-Runtime-Auth-Token"] = this.authToken; }
      try {
        const response = await fetch(this.fileEndpointUrl(request.url), { method: "POST", headers, credentials: "include", body: JSON.stringify(request.data || {}) });
        if (!response.ok) { const text = await response.text(); let error;try { error = JSON.parse(text); } catch (_) { error = new Error("Falha ao baixar arquivo."); }throw error; }
        return await response.blob();
      } catch (error) { this.handleAuthError(error);throw error; }
    }

    buildRequestUrl(url, method, data) {
      if (!url || method !== "GET" || !data || typeof data !== "object") {
        return url;
      }
      const base = global.location && global.location.href || "http://localhost/";
      const target = new URL(url, base);
      Object.keys(data).forEach(function(key) {
        const value = data[key];
        if (value == null || value === "") {
          return;
        }
        if (Array.isArray(value)) {
          value.forEach(function(item) {
            if (item != null && item !== "") {
              target.searchParams.append(key, String(item));
            }
          });
          return;
        }
        target.searchParams.set(key, String(value));
      });
      return target.href;
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
      if (global.CrudUtils && typeof global.CrudUtils.clearRuntimeSessionContext === "function") {
        global.CrudUtils.clearRuntimeSessionContext({
          preserveLastUsername: true,
          clearRememberToken: code === "SESSION_REVOKED"
        });
      } else {
        this.clearAuth();
        if (code === "SESSION_REVOKED") {
          this.clearRememberToken();
        }
      }
      this.authToken = "";
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
      return global.CrudUtils && typeof global.CrudUtils.readLocalValue === "function"
        ? global.CrudUtils.readLocalValue(key, "")
        : "";
    }

    saveLocalValue(key, value) {
      return global.CrudUtils && typeof global.CrudUtils.saveLocalValue === "function"
        ? global.CrudUtils.saveLocalValue(key, value)
        : false;
    }

    removeLocalValue(key) {
      return global.CrudUtils && typeof global.CrudUtils.removeLocalValue === "function"
        ? global.CrudUtils.removeLocalValue(key)
        : false;
    }
  }

  global.CrudHttpClient = CrudHttpClient;
})(window);
