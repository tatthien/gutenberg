/**
 * Node dependencies.
 */
const { execSync } = require( 'child_process' );
const path = require( 'path' );
const os = require( 'os' );
const crypto = require( 'crypto' );
const { env } = require( 'process' );

/**
 * Internal dependencies
 */
const { getArgsFromCLI } = require( '../../utils' );

const args = getArgsFromCLI();

// Plugin against which to run the tests.
const pluginDir = env.npm_package_wp_env_plugin_dir;
const localDir = env.LOCAL_DIR || 'src';

// Runs the phpunit command in the phpunit Docker service,
execSync(
	`docker-compose run --rm phpunit phpunit -c /var/www/${ localDir }/wp-content/plugins/${ pluginDir }/phpunit.xml.dist` +
		args.join( ' ' ),
	{
		cwd: getDockerComposeDir(),
		stdio: 'inherit',
	}
);

/**
 * Returns the directory containing the docker-compose file for our .wp-env file.
 *
 * Assumes the .wp-env.json file exists in cwd.
 *
 * Based on packages/env/lib/config.js.
 */
function getDockerComposeDir() {
	const configPath = path.resolve( '.wp-env.json' );
	const pathHash = crypto
		.createHash( 'md5' )
		.update( configPath )
		.digest( 'hex' );

	return path.resolve( os.homedir(), '.wp-env', pathHash );
}
