import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { spawn } from 'child_process';
import inquirer from 'inquirer';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const customBundlePath = path.join(__dirname, 'custom-bundle.js');
const optionalFeaturesPath = path.join(__dirname, 'optional-features.json');

function parseCustomBundle() {
    const content = fs.readFileSync(customBundlePath, 'utf-8');
    const imports = [];
    const importRegex = /import\s+["']\.\/js\/(kendo\.\w+\.js)["']/g;
    let match;
    while ((match = importRegex.exec(content)) !== null) {
        imports.push(match[1]);
    }
    return imports;
}

function loadOptionalFeatures() {
    const content = fs.readFileSync(optionalFeaturesPath, 'utf-8');
    return JSON.parse(content);
}

function saveOptionalFeatures(features) {
    fs.writeFileSync(optionalFeaturesPath, JSON.stringify(features, null, 2) + '\n');
}

async function configure() {
    const importedComponents = parseCustomBundle();
    const optionalFeatures = loadOptionalFeatures();
    const componentsWithFeatures = importedComponents.filter(comp => optionalFeatures[comp]);

    if (componentsWithFeatures.length === 0) {
        return;
    }

    const skipPrompt = process.env.SKIP_PROMPT === 'true';

    if (skipPrompt) {
        for (const component of componentsWithFeatures) {
            const features = optionalFeatures[component];
            features.forEach(feature => {
                feature.enabled = true;
            });
        }
        saveOptionalFeatures(optionalFeatures);
        return;
    }

    console.log('\nConfiguring optional features for custom bundle\n');
    console.log(`Found ${componentsWithFeatures.length} component(s) with optional features\n`);

    for (let i = 0; i < componentsWithFeatures.length; i++) {
        const component = componentsWithFeatures[i];
        console.log(`\n[${i + 1}/${componentsWithFeatures.length}] ${component}\n`);
        
        const features = optionalFeatures[component];
        const choices = features.map((feature, idx) => ({
            name: `${feature.name} - ${feature.description}`,
            value: idx,
            checked: feature.enabled
        }));

        const answers = await inquirer.prompt([
            {
                type: 'checkbox',
                name: 'enabledFeatures',
                message: 'Select features to include',
                choices: choices,
                loop: false,
                pageSize: 25
            }
        ]);

        features.forEach((feature, idx) => {
            feature.enabled = answers.enabledFeatures.includes(idx);
        });
    }

    saveOptionalFeatures(optionalFeatures);
}

function runBuild() {
    return new Promise((resolve, reject) => {
        const child = spawn('npx', ['rolldown', '-c', 'rolldown.config.custom.mjs'], {
            stdio: 'inherit',
            cwd: __dirname
        });

        child.on('close', (code) => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`Build failed with code ${code}`));
            }
        });
    });
}

async function main() {
    try {
        await configure();
        await runBuild();
    } catch (err) {
        console.error('Error:', err.message);
        process.exit(1);
    }
}

main();
