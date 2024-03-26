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
	.webpackConfig({
		module: {
			rules:[
				{
					test: /\.txt$/i,
					use: ['raw-loader'],
				}
			]
		},
		resolve: {
			fallback: {
				"fs": false,
				"path": false,
				"crypto": false,
				"stream": false,
			}
		}
	})


	// --------------------------------------
	// px2-site-search.js
	.js('src/assets/px2-site-search.js', 'public/assets/')
	.sass('src/assets/px2-site-search.scss', 'public/assets/')

	// --------------------------------------
	// siteSearchCceFront.js
	.js('cce/src/siteSearchCceFront.js', 'cce/front/')
	.sass('cce/src/siteSearchCceFront.scss', 'cce/front/')

	.copyDirectory('public/assets/', 'tests/testdata/standard/common/site_search_index/assets/')
;
