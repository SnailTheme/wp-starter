import fs from 'node:fs';
import path from 'node:path';
import {defineConfig} from 'vite';
import * as sass from 'sass';
import {minify} from 'terser';

const projectRoot = process.cwd();
const themeScssFolder = path.resolve('assets/scss');
const themeCssFolder = path.resolve('assets/css');
const scriptsFolder = path.resolve('assets/scripts');
const jsOutputFolder = path.resolve('assets/js');
const profileStateFile = path.resolve('st-toolkit.json');
const virtualEntryId = 'virtual:st-wp-starter-assets';
const resolvedVirtualEntryId = `\0${virtualEntryId}`;

/**
 * Remove a generated directory recursively before a production build.
 *
 * Vite does not own every output in this project: Sass and JavaScript source
 * files are compiled by the pipeline plugin below. Cleaning their destination
 * directories here prevents deleted or renamed source files from leaving stale
 * production assets behind.
 *
 * @param {string} folderPath Absolute destination path.
 */
function deleteFolderRecursive(folderPath) {
    if (!fs.existsSync(folderPath)) {
        return;
    }

    fs.rmSync(folderPath, {recursive: true, force: true});
}

/**
 * Create an output directory when it does not exist yet.
 *
 * @param {string} directory Absolute output directory.
 */
function ensureDirectoryExists(directory) {
    fs.mkdirSync(directory, {recursive: true});
}

/**
 * Recursively find files accepted by the provided callback.
 *
 * @param {string} directory Directory to inspect.
 * @param {(fileName: string) => boolean} acceptsFile File-name predicate.
 * @return {string[]} Absolute file paths in deterministic order.
 */
function findFiles(directory, acceptsFile) {
    if (!fs.existsSync(directory)) {
        return [];
    }

    return fs.readdirSync(directory, {withFileTypes: true})
        .sort((first, second) => first.name.localeCompare(second.name))
        .flatMap((entry) => {
            const filePath = path.join(directory, entry.name);

            if (entry.isDirectory()) {
                return findFiles(filePath, acceptsFile);
            }

            return acceptsFile(entry.name) ? [filePath] : [];
        });
}

/**
 * Find block Sass source directories at build time.
 *
 * The lookup is intentionally dynamic so newly installed toolkit blocks are
 * picked up without editing this configuration file.
 *
 * @return {string[]} Block Sass directories.
 */
function getBlockScssFolders() {
    const blocksFolder = path.resolve('blocks');

    if (!fs.existsSync(blocksFolder)) {
        return [];
    }

    return fs.readdirSync(blocksFolder, {withFileTypes: true})
        .filter((entry) => entry.isDirectory())
        .map((entry) => path.join(blocksFolder, entry.name, 'assets/scss'))
        .filter((directory) => fs.existsSync(directory));
}

/**
 * Return compilable Sass entry points, excluding underscore-prefixed partials.
 *
 * @param {string} directory Sass source directory.
 * @return {string[]} Sass entry points.
 */
function getScssFiles(directory) {
    return findFiles(
        directory,
        (fileName) => fileName.endsWith('.scss') && !fileName.startsWith('_')
    );
}

/**
 * Return compilable JavaScript entry points, excluding local helper files.
 *
 * @param {string} directory JavaScript source directory.
 * @return {string[]} JavaScript entry points.
 */
function getJavaScriptFiles(directory) {
    return findFiles(
        directory,
        (fileName) => fileName.endsWith('.js') && !fileName.startsWith('_')
    );
}

/**
 * Read the toolkit profile state used to opt into native CSS integrations.
 *
 * A missing file means the theme uses its normal Sass pipeline. Profile
 * installers may declare native CSS entries and optional Vite plugins without
 * requiring those npm packages in the clean starter.
 *
 * @return {{css_entries?: string[], vite_plugins?: string[]}}
 */
function readProfileState() {
    if (!fs.existsSync(profileStateFile)) {
        return {};
    }

    try {
        return JSON.parse(fs.readFileSync(profileStateFile, 'utf8'));
    } catch (error) {
        throw new Error(`Unable to read ${path.relative(projectRoot, profileStateFile)}: ${error.message}`);
    }
}

/**
 * Resolve configured native CSS entry points for Vite.
 *
 * @param {{css_entries?: string[]}} profileState Active profile state.
 * @return {Record<string, string>} Named Rollup inputs.
 */
function getNativeCssEntries(profileState) {
    const entries = {};

    for (const relativeFile of profileState.css_entries ?? []) {
        const absoluteFile = path.resolve(relativeFile);
        const relativeToOutput = path.relative(themeCssFolder, absoluteFile);
        const isInsideOutput = relativeToOutput === ''
            || (!relativeToOutput.startsWith(`..${path.sep}`)
                && relativeToOutput !== '..'
                && !path.isAbsolute(relativeToOutput));

        if (isInsideOutput) {
            throw new Error(
                `Configured CSS entry must be outside generated assets/css: ${relativeFile}. `
                + 'Keep native CSS sources under assets/styles or another source directory.'
            );
        }

        if (!fs.existsSync(absoluteFile)) {
            throw new Error(`Configured CSS entry does not exist: ${relativeFile}`);
        }

        entries[path.basename(relativeFile, path.extname(relativeFile))] = absoluteFile;
    }

    return entries;
}

/**
 * Compile all non-partial Sass files below one source directory.
 *
 * @param {string} scssFolder Sass source root.
 * @param {string} cssFolder CSS destination root.
 * @param {boolean} isProduction Whether source maps and warnings are disabled.
 */
function compileScssFiles(scssFolder, cssFolder, isProduction) {
    const sassLogger = isProduction ? sass.Logger.silent : undefined;

    for (const file of getScssFiles(scssFolder)) {
        const relativePath = path.relative(scssFolder, file);
        const outputFile = path.join(cssFolder, relativePath).replace(/\.scss$/, '.min.css');
        const mapFile = `${outputFile}.map`;

        ensureDirectoryExists(path.dirname(outputFile));

        const result = sass.compile(file, {
            style: 'compressed',
            sourceMap: !isProduction,
            logger: sassLogger
        });

        let cssContent = result.css;

        if (!isProduction && result.sourceMap) {
            cssContent += `\n/*# sourceMappingURL=${path.basename(mapFile)} */`;
            fs.writeFileSync(mapFile, JSON.stringify(result.sourceMap));
            console.log(path.relative(projectRoot, mapFile));
        }

        fs.writeFileSync(outputFile, cssContent);
        console.log(path.relative(projectRoot, outputFile));

        if (isProduction && fs.existsSync(mapFile)) {
            fs.unlinkSync(mapFile);
        }
    }
}

/**
 * Compile theme and installed-block Sass entry points.
 *
 * @param {boolean} isProduction Whether this is a production build.
 */
function compileScss(isProduction) {
    compileScssFiles(themeScssFolder, themeCssFolder, isProduction);

    for (const scssFolder of getBlockScssFolders()) {
        compileScssFiles(scssFolder, path.join(path.dirname(scssFolder), 'css'), isProduction);
    }
}

/**
 * Minify every project JavaScript entry while preserving stable output paths.
 *
 * @param {boolean} isProduction Whether this is a production build.
 * @return {Promise<void>}
 */
async function compileJavaScript(isProduction) {
    for (const file of getJavaScriptFiles(scriptsFolder)) {
        const relativePath = path.relative(scriptsFolder, file);
        let outputFile = path.join(jsOutputFolder, relativePath);

        if (!outputFile.endsWith('.min.js')) {
            outputFile = outputFile.replace(/\.js$/, '.min.js');
        }

        ensureDirectoryExists(path.dirname(outputFile));

        const result = await minify(fs.readFileSync(file, 'utf8'), {
            compress: true,
            mangle: true,
            sourceMap: !isProduction ? {filename: outputFile} : false
        });

        if (result.code) {
            fs.writeFileSync(outputFile, result.code);
            console.log(path.relative(projectRoot, outputFile));
        }

        const mapFile = `${outputFile}.map`;

        if (result.map && !isProduction) {
            fs.writeFileSync(mapFile, result.map);
            console.log(path.relative(projectRoot, mapFile));
        } else if (isProduction && fs.existsSync(mapFile)) {
            fs.unlinkSync(mapFile);
        }
    }
}

/**
 * Add project source files and roots to Rollup's watch graph.
 *
 * Watching the roots lets a running `npm run dev` process notice newly added
 * block or asset entries in addition to changes in existing files.
 *
 * @param {import('rollup').PluginContext} pluginContext Rollup plugin context.
 */
function registerWatchFiles(pluginContext) {
    const sourceRoots = [themeScssFolder, scriptsFolder, path.resolve('blocks')];
    for (const sourceRoot of sourceRoots) {
        if (fs.existsSync(sourceRoot)) {
            pluginContext.addWatchFile(sourceRoot);
        }
    }

    const sourceFiles = [
        ...findFiles(themeScssFolder, (fileName) => fileName.endsWith('.scss')),
        ...findFiles(scriptsFolder, (fileName) => fileName.endsWith('.js')),
        ...getBlockScssFolders().flatMap((directory) => findFiles(directory, (fileName) => fileName.endsWith('.scss')))
    ];

    for (const sourceFile of sourceFiles) {
        pluginContext.addWatchFile(sourceFile);
    }
}

/**
 * Build the custom asset pipeline as a normal Vite/Rollup plugin.
 *
 * @param {boolean} isProduction Whether this is a production build.
 * @param {boolean} hasNativeCss Whether Vite must emit native CSS entries.
 * @return {import('vite').Plugin}
 */
function assetPipelinePlugin(isProduction, hasNativeCss) {
    let productionDirectoriesCleaned = false;

    return {
        name: 'st-wp-starter-asset-pipeline',
        enforce: 'pre',

        resolveId(source) {
            return source === virtualEntryId ? resolvedVirtualEntryId : null;
        },

        load(id) {
            return id === resolvedVirtualEntryId ? 'export default {};' : null;
        },

        generateBundle(_outputOptions, bundle) {
            if (!hasNativeCss) {
                for (const fileName of Object.keys(bundle)) {
                    delete bundle[fileName];
                }
            }
        },

        async buildStart() {
            if (isProduction && !productionDirectoriesCleaned) {
                console.log('Deleting all destination directories and regenerating...');
                deleteFolderRecursive(themeCssFolder);
                deleteFolderRecursive(jsOutputFolder);

                for (const scssFolder of getBlockScssFolders()) {
                    deleteFolderRecursive(path.join(path.dirname(scssFolder), 'css'));
                }

                productionDirectoriesCleaned = true;
            }

            registerWatchFiles(this);
            compileScss(isProduction);
            await compileJavaScript(isProduction);
        }
    };
}

export default defineConfig(async ({mode}) => {
    const isProduction = mode === 'production';
    const profileState = readProfileState();
    const nativeCssEntries = getNativeCssEntries(profileState);
    const hasNativeCss = Object.keys(nativeCssEntries).length > 0;
    const plugins = [assetPipelinePlugin(isProduction, hasNativeCss)];

    if ((profileState.vite_plugins ?? []).includes('tailwindcss')) {
        try {
            const {default: tailwindcss} = await import('@tailwindcss/vite');
            plugins.unshift(tailwindcss());
        } catch (error) {
            throw new Error(
                'The active UI profile requires @tailwindcss/vite. Run npm install before building.',
                {cause: error}
            );
        }
    }

    return {
        plugins,
        build: {
            outDir: themeCssFolder,
            emptyOutDir: false,
            write: hasNativeCss,
            cssCodeSplit: true,
            minify: isProduction ? 'esbuild' : false,
            sourcemap: !isProduction,
            rollupOptions: {
                input: hasNativeCss ? nativeCssEntries : {assets: virtualEntryId},
                onwarn(warning, warn) {
                    // The virtual entry exists only to run the custom pipeline
                    // when a profile has no native CSS for Vite to emit.
                    if (!hasNativeCss && warning.code === 'EMPTY_BUNDLE') {
                        return;
                    }

                    warn(warning);
                },
                output: {
                    assetFileNames: '[name].min[extname]',
                    entryFileNames: '.vite/[name]-[hash].js',
                    chunkFileNames: '.vite/[name]-[hash].js'
                }
            }
        },
        server: {
            watch: {
                ignored: [
                    '**/assets/js/**',
                    '**/assets/css/**',
                    '**/vendor/**',
                    '**/node_modules/**',
                    '**/.git/**',
                    '**/languages/**',
                    '**/temp/**',
                    '**/*.log',
                    '**/.DS_Store'
                ],
                usePolling: true,
                interval: 1000,
                binaryInterval: 3000
            }
        }
    };
});
