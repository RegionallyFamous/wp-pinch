/**
 * Webpack config for WP Pinch.
 *
 * Extends @wordpress/scripts default config.
 *
 * - Block editor assets (pinch-chat) are auto-discovered from block.json.
 * - The Interactivity API view module and admin JS are added as explicit entries.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		'blocks/pinch-chat/view': path.resolve(
			__dirname,
			'src/blocks/pinch-chat/view.js'
		),
		admin: path.resolve( __dirname, 'src/admin/index.js' ),
	},
};
