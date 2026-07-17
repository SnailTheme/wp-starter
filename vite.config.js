import fs from 'node:fs';
import path from 'node:path';
import {defineConfig} from 'vite';
import * as sass from 'sass';
import {minify} from 'terser';

const projectRoot = process.cwd();
const themeScssFolder = path.resolve('assets/scss');
const themeStylesFolder = path.resolve('assets/styles');
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
 * Return native CSS entry points, excluding underscore-prefixed partials.
 *
 * The Tailwind profile mirrors the Sass entry convention: every normal CSS
 * file is compiled independently, while files beginning with an underscore
 * must be imported by another CSS file.
 *
 * @param {string} directory Native CSS source directory.
 * @return {string[]} Native CSS entry points.
 */
function getNativeCssFiles(directory) {
    return findFiles(
        directory,
        (fileName) => fileName.endsWith('.css') && !fileName.startsWith('_')
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
 * Whether the active profile enables Tailwind's native CSS pipeline.
 *
 * @param {{vite_plugins?: string[]}} profileState Active profile state.
 * @return {boolean} Whether Tailwind is enabled.
 */
function profileUsesTailwind(profileState) {
    return (profileState.vite_plugins ?? []).includes('tailwindcss');
}

/**
 * Determine whether a path is contained by a source root.
 *
 * @param {string} root Absolute source root.
 * @param {string} candidate Absolute candidate path.
 * @return {boolean} Whether the candidate is inside the source root.
 */
function isPathInside(root, candidate) {
    const relativePath = path.relative(root, candidate);

    return relativePath !== ''
        && !relativePath.startsWith(`..${path.sep}`)
        && relativePath !== '..'
        && !path.isAbsolute(relativePath);
}

/**
 * Convert an operating-system path to the stable slash format Rollup uses.
 *
 * @param {string} filePath Relative file path.
 * @return {string} Slash-normalized path.
 */
function normalizeBuildPath(filePath) {
    return filePath.split(path.sep).join('/');
}

/**
 * Discover Tailwind-native CSS entry points for Vite.
 *
 * Declared profile entries remain required, while every additional
 * non-underscored CSS file below assets/styles becomes an automatic entry.
 * This gives native CSS the same drop-in entry behavior as the Sass compiler.
 *
 * @param {{css_entries?: string[], vite_plugins?: string[]}} profileState Active profile state.
 * @return {Record<string, string>} Named Rollup inputs.
 */
function getNativeCssEntries(profileState) {
    const allNativeCssFiles = findFiles(themeStylesFolder, (fileName) => fileName.endsWith('.css'));

    if (!profileUsesTailwind(profileState)) {
        if (allNativeCssFiles.length > 0) {
            throw new Error(
                'Native CSS sources under assets/styles require the Tailwind UI profile. '
                + 'Select Tailwind or move classic entries to assets/scss.'
            );
        }

        return {};
    }

    const discoveredFiles = new Map(
        getNativeCssFiles(themeStylesFolder).map((file) => [path.resolve(file), file])
    );

    for (const relativeFile of profileState.css_entries ?? []) {
        const absoluteFile = path.resolve(relativeFile);

        if (!isPathInside(themeStylesFolder, absoluteFile)) {
            throw new Error(`Configured native CSS entry must live under assets/styles: ${relativeFile}`);
        }

        if (!fs.existsSync(absoluteFile)) {
            throw new Error(`Configured CSS entry does not exist: ${relativeFile}`);
        }

        if (path.basename(absoluteFile).startsWith('_')) {
            throw new Error(`Configured CSS entry cannot be an underscore-prefixed partial: ${relativeFile}`);
        }

        discoveredFiles.set(absoluteFile, absoluteFile);
    }

    const entries = {};
    const caseInsensitiveNames = new Map();

    for (const absoluteFile of [...discoveredFiles.keys()].sort()) {
        const relativeFile = path.relative(themeStylesFolder, absoluteFile);
        const entryName = normalizeBuildPath(relativeFile.replace(/\.css$/, ''));
        const collisionKey = entryName.toLowerCase();
        const existingEntry = caseInsensitiveNames.get(collisionKey);

        if (existingEntry && existingEntry !== entryName) {
            throw new Error(
                `Native CSS entries differ only by letter case: ${existingEntry}.css and ${entryName}.css`
            );
        }

        caseInsensitiveNames.set(collisionKey, entryName);
        entries[entryName] = absoluteFile;
    }

    if (Object.keys(entries).length === 0) {
        throw new Error('The Tailwind UI profile requires at least one native CSS entry under assets/styles.');
    }

    return entries;
}

/**
 * Fail before writing when Sass and native CSS claim the same output path.
 *
 * The comparison is case-insensitive so a build remains portable across
 * Linux, macOS, and Windows filesystems. There is intentionally no precedence:
 * developers must rename one source instead of relying on build order.
 *
 * @param {Record<string, string>} nativeCssEntries Named native CSS entries.
 */
function validateStyleOutputCollisions(nativeCssEntries) {
    const claims = new Map();

    const addClaim = (outputPath, sourcePath) => {
        const normalizedOutput = normalizeBuildPath(outputPath);
        const collisionKey = normalizedOutput.toLowerCase();
        const existingSource = claims.get(collisionKey);

        if (existingSource && existingSource !== sourcePath) {
            throw new Error(
                `Style output collision for assets/css/${normalizedOutput}: `
                + `${normalizeBuildPath(path.relative(projectRoot, existingSource))} and `
                + `${normalizeBuildPath(path.relative(projectRoot, sourcePath))}. `
                + 'Rename one source before building.'
            );
        }

        claims.set(collisionKey, sourcePath);
    };

    for (const file of getScssFiles(themeScssFolder)) {
        const outputPath = path.relative(themeScssFolder, file).replace(/\.scss$/, '.min.css');
        addClaim(outputPath, file);
    }

    for (const [entryName, file] of Object.entries(nativeCssEntries)) {
        addClaim(`${entryName}.min.css`, file);
    }
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
    const sourceRoots = [themeScssFolder, themeStylesFolder, scriptsFolder, path.resolve('blocks')];
    for (const sourceRoot of sourceRoots) {
        if (fs.existsSync(sourceRoot)) {
            pluginContext.addWatchFile(sourceRoot);
        }
    }

    if (fs.existsSync(profileStateFile)) {
        pluginContext.addWatchFile(profileStateFile);
    }

    const sourceFiles = [
        ...findFiles(themeScssFolder, (fileName) => fileName.endsWith('.scss')),
        ...findFiles(themeStylesFolder, (fileName) => fileName.endsWith('.css')),
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
 * @param {{css_entries?: string[], vite_plugins?: string[]}} profileState Active profile state.
 * @return {import('vite').Plugin}
 */
function assetPipelinePlugin(isProduction, profileState) {
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
            for (const [fileName, output] of Object.entries(bundle)) {
                if (output.type === 'chunk' && output.facadeModuleId === resolvedVirtualEntryId) {
                    delete bundle[fileName];
                }
            }
        },

        async buildStart() {
            const nativeCssEntries = getNativeCssEntries(profileState);
            validateStyleOutputCollisions(nativeCssEntries);

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

            for (const [entryName, file] of Object.entries(nativeCssEntries)) {
                this.emitFile({type: 'chunk', id: file, name: entryName});
            }

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
    const plugins = [assetPipelinePlugin(isProduction, profileState)];

    if (profileUsesTailwind(profileState)) {
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
                input: {assets: virtualEntryId},
                onwarn(warning, warn) {
                    // The virtual entry exists only to run the custom pipeline;
                    // native CSS entries are emitted dynamically in buildStart.
                    if (warning.code === 'EMPTY_BUNDLE') {
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
