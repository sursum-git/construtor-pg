import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const optionalFeaturesPath = path.join(__dirname, 'optional-features.json');

function loadOptionalFeatures() {
    try {
        const content = fs.readFileSync(optionalFeaturesPath, 'utf-8');
        return JSON.parse(content);
    } catch (error) {
        console.warn(`Warning: Could not load optional-features.json: ${error.message}`);
        return {};
    }
}

const excludeOptionalFeaturesPlugin = {
    name: 'exclude-optional-features',
    
    transform(code, id) {
        const optionalFeatures = loadOptionalFeatures();
        const filename = path.basename(id);
        
        if (!optionalFeatures[filename] || !Array.isArray(optionalFeatures[filename])) {
            return null;
        }
        
        const features = optionalFeatures[filename];
        let modifiedCode = code;
        let hasChanges = false;
        
        for (const feature of features) {
            if (feature.enabled === false && feature.files && Array.isArray(feature.files)) {
                for (const importFile of feature.files) {
                    const importStatement = `import "./${importFile}";`;
                    if (modifiedCode.includes(importStatement)) {
                        modifiedCode = modifiedCode.replace(
                            `import "./${importFile}";\n`,
                            ''
                        ).replace(
                            `import "./${importFile}";`,
                            ''
                        );
                        hasChanges = true;
                    }
                }
            }
        }
        
        if (hasChanges) {
            return {
                code: modifiedCode,
                map: null
            };
        }
        
        return null;
    }
};


const file = "custom-bundle.js";
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

export default [
    {
        input: file,
        output: {
            dir: './dist/custom',
            format: 'umd',
            entryFileNames: 'kendo.custom.min.js',
            minify: true,
            sourcemap: true,
        },
        plugins: [
            excludeOptionalFeaturesPlugin,
            replaceMetadata,
            aliasPlugin
        ]
    }
];
