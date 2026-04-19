import {defineConfig} from 'vite';
import fs from 'fs';
import path from 'path';
import * as sass from 'sass';
import {minify} from 'terser'; // Import Terser for JS minification

// Suppress Sass warnings by redirecting stderr
const originalStderrWrite = process.stderr.write;
process.stderr.write = function(chunk, encoding, callback) {
    // Filter out Sass deprecation warnings
    if (typeof chunk === 'string' &&
        (chunk.includes('DEPRECATION WARNING') ||
            chunk.includes('will be removed in Dart Sass'))) {
        return true;
    }
    return originalStderrWrite.apply(process.stderr, arguments);
};

const themeScssFolder = path.resolve('assets/scss'); // Theme SCSS folder
const themeCssFolder = path.resolve('assets/css'); // Theme CSS output folder
const scriptsFolder = path.resolve('assets/scripts'); // JS input folder
const jsOutputFolder = path.resolve('assets/js'); // JS output folder
const blocksScssFolders = getBlockScssFolders('blocks'); // Blocks SCSS folders

const isProduction = process.env.NODE_ENV === 'production';

/**
 * Function to clear /css/ && /js/ directories before build.
 * Ensures there are no build file remnants if source files were renamed or deleted.
 */
function deleteFolderRecursive(folderPath) {
    if (fs.existsSync(folderPath)) {
        fs.readdirSync(folderPath).forEach((file) => {
            const curPath = path.join(folderPath, file);
            if (fs.lstatSync(curPath).isDirectory()) {
                deleteFolderRecursive(curPath);
            } else {
                fs.unlinkSync(curPath);
            }
        });
        fs.rmdirSync(folderPath);
    }
}

// Ensure directories exist before compilation
function ensureDirExists(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, {recursive: true});
    }
}

// Get all SCSS folders inside `/blocks/<any_directory>/assets/scss/`
function getBlockScssFolders(baseDir) {
    let blockFolders = [];
    if (fs.existsSync(baseDir)) {
        fs.readdirSync(baseDir).forEach((subdir) => {
            const scssPath = path.join(baseDir, subdir, 'assets/scss');
            if (fs.existsSync(scssPath) && fs.statSync(scssPath).isDirectory()) {
                blockFolders.push(scssPath);
            }
        });
    }
    return blockFolders;
}

// Function to get SCSS files (excluding partials)
function getScssFiles(dir) {
    let files = [];
    if (!fs.existsSync(dir)) return files;

    fs.readdirSync(dir).forEach((file) => {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);

        if (stat.isDirectory()) {
            files = files.concat(getScssFiles(fullPath));
        } else if (file.endsWith('.scss') && !file.startsWith('_')) {
            files.push(fullPath);
        }
    });

    return files;
}

// Function to get JavaScript files (excluding files starting with '_')
function getJsFiles(dir) {
    let files = [];
    if (!fs.existsSync(dir)) return files;

    fs.readdirSync(dir).forEach((file) => {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);

        if (stat.isDirectory()) {
            files = files.concat(getJsFiles(fullPath));
        } else if (file.endsWith('.js') && !file.startsWith('_')) {
            files.push(fullPath);
        }
    });

    return files;
}


// Compile SCSS files
function compileScss() {
    compileScssFiles(themeScssFolder, themeCssFolder);

    blocksScssFolders.forEach((scssFolder) => {
        const cssFolder = path.join(path.dirname(scssFolder), 'css');
        compileScssFiles(scssFolder, cssFolder);
    });
}

// Compile SCSS files in a given directory
function compileScssFiles(scssFolder, cssFolder) {
    getScssFiles(scssFolder).forEach((file) => {
        const relativePath = path.relative(scssFolder, file);
        let outputFile = path.join(cssFolder, relativePath).replace('.scss', '.min.css');

        const outputDir = path.dirname(outputFile);
        ensureDirExists(outputDir);

        const mapFile = `${outputFile}.map`;

        const result = sass.compile(file, {
            style: 'compressed',
            sourceMap: !isProduction
        });

        let cssContent = result.css;

        if (!isProduction && result.sourceMap) {
            cssContent += `\n/*# sourceMappingURL=${path.basename(mapFile)} */`;
            fs.writeFileSync(mapFile, JSON.stringify(result.sourceMap));

            // Log Generated Stylesheets Maps
            console.log(path.relative(process.cwd(), mapFile));
        }

        fs.writeFileSync(outputFile, cssContent);

        // Log Generated Stylesheets
        console.log(path.relative(process.cwd(), outputFile));

        if (isProduction && fs.existsSync(mapFile)) {
            fs.unlinkSync(mapFile);

            // Log Deleted Generated Stylesheets Map
            console.log('Deleted: ', path.relative(process.cwd(), mapFile))
        }
    });
}

// Compile JavaScript files
async function compileJs() {
    const jsFiles = getJsFiles(scriptsFolder);

    for (const file of jsFiles) {
        const relativePath = path.relative(scriptsFolder, file);
        let outputFile = path.join(jsOutputFolder, relativePath);

        // Prevent double ".min.js"
        if (!outputFile.endsWith('.min.js')) {
            outputFile = outputFile.replace('.js', '.min.js');
        }

        const outputDir = path.dirname(outputFile);
        ensureDirExists(outputDir);

        // Read JS file
        const code = fs.readFileSync(file, 'utf8');

        // Minify & mangle JavaScript using Terser
        const result = await minify(code, {
            compress: true,
            mangle: true, // Shortens variable names
            sourceMap: !isProduction ? {filename: outputFile} : false
        });

        if (result.code) {
            fs.writeFileSync(outputFile, result.code);

            // Log Generated Script
            console.log(path.relative(process.cwd(), outputFile));
        }

        const mapFile = `${outputFile}.map`;

        if (result.map && !isProduction) {
            fs.writeFileSync(mapFile, result.map);

            // Log Generated Script Maps
            console.log(path.relative(process.cwd(), mapFile));
        }

        // Delete .map file in production
        if (isProduction && fs.existsSync(mapFile)) {
            fs.unlinkSync(mapFile);

            // Log Deleted Generated Script Map
            console.log('Deleted: ', path.relative(process.cwd(), mapFile))
        }
    }
}


// Initial Compilation
if (isProduction) {
    console.log('Deleting all destination directories and regenerating...');

    deleteFolderRecursive(themeCssFolder);
    deleteFolderRecursive(jsOutputFolder);
    blocksScssFolders.forEach((scssFolder) => {
        const cssFolder = path.join(path.dirname(scssFolder), 'css');
        deleteFolderRecursive(cssFolder);
    });
}

compileScss();
compileJs();

export default defineConfig({
    plugins: [
        {
            name: 'scss-compiler',
            apply: 'serve',
            handleHotUpdate({file}) {
                if (file.endsWith('.scss')) {
                    const scssFolder = file.replace(/(\/assets\/scss\/).*/, '$1');
                    const cssFolder = scssFolder.replace('scss', 'css');
                    compileScssFiles(scssFolder, cssFolder);
                } else if (file.endsWith('.js')) {
                    compileJs();
                }
            }
        },
        // Custom plugin to prevent build failure
        {
            name: 'ignore-build',
            config(config) {
                // Provide an empty build config that does nothing
                config.build = {
                    ...config.build,
                    rollupOptions: {
                        input: false, // Prevent looking for an entry point
                    },
                    emptyOutDir: false, // Don't empty output dir
                    write: false, // Don't write any files
                }
            },
            buildStart() {
                // Prevent build from actually running
                if (process.env.NODE_ENV === 'production') {
                    console.log("\nSkipping Vite build process - asset compilation complete.\n");
                    process.exit(0); // Cleanly exit before Vite tries to perform the build
                }
            }
        }
    ],
    server: {
        watch: {
            // Ignore output folders and unnecessary directories to prevent file descriptor limit issues
            ignored: [
                '**/assets/js/**',        // Output JS folder - prevents loop
                '**/assets/css/**',       // Output CSS folder - prevents loop
                '**/vendor/**',           // PHP Composer dependencies
                '**/node_modules/**',     // Node.js dependencies (redundant but explicit)
                '**/.git/**',             // Git repository data
                '**/languages/**',        // Translation files
                '**/temp/**',             // Temporary files
                '**/*.log',               // Log files
                '**/.DS_Store'            // macOS metadata files
            ],
            // Use polling for network drives (slower but more reliable)
            usePolling: true,           // Vite periodically checks instead of using native file watching - useful for network drive
            interval: 1000,             // 1 second interval for files
            binaryInterval: 3000        // 3 seconds interval for binary files (like images, fonts, videos)
        }
    }
});
