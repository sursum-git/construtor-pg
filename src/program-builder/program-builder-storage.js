(function(global) {
  "use strict";

  const ProgramBuilderStorage = {
    load(key) {
      try {
        const raw = global.localStorage && global.localStorage.getItem(String(key || ""));
        return raw ? JSON.parse(raw) : null;
      } catch (_) {
        return null;
      }
    },

    save(key, value) {
      try {
        if (!global.localStorage) {
          return false;
        }
        global.localStorage.setItem(String(key || ""), JSON.stringify(value == null ? null : value));
        return true;
      } catch (_) {
        return false;
      }
    }
  };

  global.ProgramBuilderStorage = ProgramBuilderStorage;
})(window);
