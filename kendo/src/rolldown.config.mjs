import { glob } from 'glob'
import path from 'path';
import fs from 'fs';

const files = glob.sync('./js/kendo.*.js');
const cultures = glob.sync('./js/cultures/*.js');
const messages = glob.sync('./js/messages/*.js');
const subdirs = glob.sync('./js/*/kendo*.js');

const umdExternalRegex = [
    /^(?:\.\.\/)*(?:\.\/)?kendo[\w\.\-]+\.js$/,
];

const bundleFiles = new Set(
    files.filter(file => /["']bundle all["'];/.test(fs.readFileSync(file).toString()))
);

const globals = [...files, ...subdirs].reduce((acc, file) => {
    const basename = path.basename(file);
    const genName = genNamespace(file);
    const relativePath = file.replace(/^\.\.\/src\//, '');

    acc[`./${basename}`] = genName;
    acc[basename] = genName;
    acc[file] = genName;
    acc[path.resolve(file)] = genName;

    if (relativePath !== basename) {
        acc[relativePath] = genName;
        acc[`./${relativePath}`] = genName;
    }

    return acc;
}, {});

const timestamp = parseInt('1770878084');
const version = '2026.1.212';

const packageMetadata = {
    name: '@progress/kendo-ui',
    productName: 'Kendo UI for jQuery',
    productCode: 'KENDOUICOMPLETE',
    productCodes: ["KENDOUICOMPLETE", "KENDOUI", "UIASPCORE", "KENDOMVC", "KENDOUIMVC"],
    redistributedBy: ['KENDOUI', 'UIASPCORE', 'KENDOMVC', 'KENDOUIMVC'],
    publishDate: timestamp,
    version: version,
    licensingDocsUrl: 'https://www.telerik.com/kendo-jquery-ui/documentation/intro/installation/licensing/activation-errors-and-warnings'
};

/**
 * @type {import('rolldown').RolldownPlugin}
 */
const replaceMetadata = {
    name: 'replace-metadata',
    resolveId(id) {
        if (id === '\x00metadata') {
            return id;
        }
    },
    load(id) {
        if (id === '\x00metadata') {
            return `export const metadata = ${JSON.stringify(packageMetadata)};`;
        }
    },
    transform(code) {
        return {
            code: code.replace(/\$PACKAGE_METADATA/gm, JSON.stringify(packageMetadata))
        };
    }
};

/**
    * @type {import('rolldown').RolldownPlugin}
*/
const aliasPlugin = {
    name: 'custom-alias-resolver',
    resolveId(source) {
        const regex = /^pdfjs-dist.*pdf\.(worker\.)?(min\.)?mjs/;
        const replacement = './js/kendo.pdfjs.loader.js';

        if (regex.test(source)) {
            return replacement;
        }

        return null;
    },
};

function genNamespace(file) {
    const basename = path.basename(file);
    const name = basename.replace(/^(?:kendo.)?([\w\.]+)*/, '$1');
    const parts = name.split('.').filter(p => p !== 'js').map(p => p.split('-').map(w => w[0].toUpperCase() + w.slice(1)).join(''));
    return 'kendo._globals.' + parts.join('');
}

/**
 * @type {import('rolldown').RolldownOptions[]}
 */
export default [
    {
        input: files,
        output: [{
            dir: './dist/esm',
            format: 'esm',
            entryFileNames: '[name].js',
            exports: 'named',
            minify: true,
            sourcemap: true,
        }, {
            dir: './dist/js',
            format: 'cjs',
            entryFileNames: '[name].js',
            exports: 'named',
            minify: true,
            sourcemap: true,
        }],
        plugins: [replaceMetadata, aliasPlugin]
    }, {
        input: cultures,
        output: [{
            dir: './dist/esm/cultures',
            format: 'esm',
            exports: 'named',
            minify: true,
            sourcemap: true,
        }, {
            dir: './dist/js/cultures',
            format: 'cjs',
            exports: 'named',
            minify: true,
            sourcemap: true,
        }],
    }, {
        input: messages,
        output: [{
            dir: './dist/esm/messages',
            format: 'esm',
            exports: 'named',
            minify: true,
            sourcemap: true,
        }, {
            dir: './dist/js/messages',
            format: 'cjs',
            exports: 'named',
            minify: true,
            sourcemap: true,
        }]
    },
    ...files.map(file => ({
        input: file,
        treeshake: false,
        external: bundleFiles.has(file) ? [] : umdExternalRegex,
        output: [{
            dir: './dist/umd',
            format: 'umd',
            entryFileNames: '[name].min.js',
            name: genNamespace(file),
            exports: 'named',
            minify: true,
            globals,
        }]
    })),
    ...cultures.map(file => ({
        input: file,
        treeshake: false,
        external: umdExternalRegex,
        output: [{
            dir: './dist/umd/cultures',
            format: 'umd',
            entryFileNames: '[name].min.js',
            name: genNamespace(file),
            exports: 'named',
            minify: true,
            globals
        }],
    })),
    ...messages.map(file => ({
        input: file,
        treeshake: false,
        external: umdExternalRegex,
        output: [{
            dir: './dist/umd/messages',
            format: 'umd',
            entryFileNames: '[name].min.js',
            name: genNamespace(file),
            exports: 'named',
            minify: true,
            globals
        }],
    }))
];
