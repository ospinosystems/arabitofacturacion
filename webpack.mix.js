const mix = require('laravel-mix');
/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix
.js('resources/js/app.js', 'public/js')
.js('resources/js/components/index.js', 'public/js/index.js').react()
.js('resources/js/inventario-suministros-entry.js', 'public/js/inventario-suministros.js').react()
.js('resources/js/warehouse.js', 'public/js').react()
.sass('resources/sass/app.scss', 'public/css')
.sass('resources/sass/table.scss', 'public/css')
.css('resources/css/modal.css', 'public/css')
.css('resources/css/app.css', 'public/css')
.copy('resources/css/nunito-fonts.css', 'public/css')
.options({
    processCssUrls: true,
    postCss: [
        require('tailwindcss'),
        require('autoprefixer'),
    ],
})
.copy('node_modules/@fortawesome/fontawesome-free/webfonts', 'public/webfonts')
.version()
.webpackConfig({
    output: {
        filename: '[name].js',
        chunkFilename: '[name].js',
    },
});

// mix.browserSync('localhost:8000');