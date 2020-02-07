/**
 * Node dependencies.
 */
const path = require( 'path' );
const { env } = require( 'process' );

/**
 * WordPress dependencies.
 */
const wpEnv = require( '@wordpress/env' );

/**
 * Internal dependencies
 */
const { getArgsFromCLI } = require( '../../utils' );

const args = getArgsFromCLI().join( ' ' );

// Default to CWD basename for the plugin to run.
const pluginDir =
	env.npm_package_wp_env_plugin_dir || path.basename( process.cwd() );
const localDir = env.LOCAL_DIR || 'src';

// Executes phpunit in the Docker Service.
wpEnv
	.run( {
		container: 'phpunit',
		command: [
			`phpunit -c /var/www/${ localDir }/wp-content/plugins/${ pluginDir }/phpunit.xml.dist ${ args }`,
		],
		spinner: {},
	} )
	// eslint-disable-next-line no-console
	.then( console.log )
	.catch( ( { err } ) => {
		// eslint-disable-next-line no-console
		console.error( err );
		process.exit( 1 );
	} );
