import { glob } from 'glob'
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootPath = path.resolve(__dirname, './js');

const files = glob.sync('./js/kendo.*.js');
const cultures = glob.sync('./js/cultures/*.js');
const messages = glob.sync('./js/messages/*.js');

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

/**
 * @type {import('rolldown').RolldownOptions[]}
 */
export default [{
    input: files,
    output: [{
        dir: './dist/dev',
        format: 'esm',
        entryFileNames: '[name].js',
        preserveModules: true,
        preserveModulesRoot: rootPath
    }],
    plugins: [replaceMetadata, aliasPlugin]
}, {
    input: cultures,
    output: [{
        dir: './dist/dev/cultures',
        format: 'esm'
    }],
}, {
    input: messages,
    output: [{
        dir: './dist/dev/messages',
        format: 'esm'
    }]
}];
