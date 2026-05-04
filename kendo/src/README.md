# Building the Source Code

There are several commands that you can use to build the source code:

- `npm run build` -> Produces all 3 flavors - 'umd', 'esm', 'cjs. The 'umd' scripts are what you would typically see in the Kendo UI Dojo and what is deployed to the Kendo CDN.
- `npm run build:dev` -> Produces a dev version of the scripts (esm format) that you can add to your page for quick debugging.
- `npm run build:dev:watch` -> Same as the above command, except it has a watcher enabled. When you modify files inside the `js` folder, the scripts will be immediately recompiled. Use this command when you're activately working on a feature.
- `npm run build:custom` -> This command allows you to compile a custom 'umd' script that includes only the components that you need. This is a replacement for the `download-builder` application and the legacy `custom build cli`.
- `npm run build:full` -> Same as the above command except you can skip the optional features CLI interaction. The bundle will include all optional features.

## Custom Bundle

Producing a custom 'umd' bundle is extremely easy. Simply import the components that you want to use inside the `custom-bundle.js` and execute the `npm run build:custom` command.

Example:

```javascript
import './js/kendo.grid.js';
import './js/kendo.scheduler.js';
```

You do not need to separately import other files like `kendo.core.js` or `kendo.data.js`. These will be automatically resolved and included in the bundle.

After executing the command and `kendo.custom.min.js` will be outputted inside the `dist/custom/` folder. This file will contain all of the necessary code for all of the components you've selected.

### Optional Features

When you execute the `build:custom` command, if you have selected any components that include optional features, you'll be prompted to select which ones you want to include in your bundle. For example, you may want to exclude the `editing` feature of the Grid to further reduce the size of the bundle, assuming you won't need editing for your Grid.

By default all features are enabled. If you disable a feature and you re-run the build command, your previous choice is saved.

## MVC ScriptBundler

The MVC ScriptBundler is known to have issues with modern syntax JS. We advise that you do not 're-bundle' the scripts with a different bundler once they've already gone through the build process. It is unnecessary as the script is already compressed down to a single file.

## Minifcation

You can disable/enable the minification of the built files inside the `rolldown.*` configs. Simply search for the `minify:` setting and change its value to either **true** or **false**.