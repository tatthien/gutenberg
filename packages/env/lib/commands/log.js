/**
 * External dependencies
 */
const dockerCompose = require( 'docker-compose' );

/**
 * Internal dependencies
 */
const initConfig = require( '../init-config' );

/**
 * Displays the Docker & PHP logs on the given environment.
 *
 * @param {Object}  options
 * @param {Object}  options.environment The environment to run the command in (develop or tests).
 * @param {Object}  options.watch       If true, watch and continue showing logs.
 * @param {Object}  options.spinner     A CLI spinner which indicates progress.
 * @param {boolean} options.debug       True if debug mode is enabled.
 */
module.exports = async function log( { environment, watch, spinner, debug } ) {
	const config = await initConfig( { spinner, debug } );

	// If we show text while watching the logs, it will continue showing up every
	// few lines in the logs as they happen, which isn't a good look. So only
	// show the message if we are not watching the logs.
	if ( ! watch ) {
		spinner.text = `Showing logs for the ${ environment } environment.`;
	}

	// Display Docker and PHP logs:
	const result = await dockerCompose.logs(
		environment === 'tests' ? 'tests-wordpress' : 'wordpress',
		{
			config: config.dockerComposeConfigPath,
			log: !! watch, // Must log inline if we are watching the log output.
			commandOptions: watch ? [ '--follow' ] : [],
		}
	);

	if ( result.out ) {
		// eslint-disable-next-line no-console
		console.log(
			process.stdout.isTTY ? `\n\n${ result.out }\n\n` : result.out
		);
	} else if ( result.err ) {
		// eslint-disable-next-line no-console
		console.error(
			process.stdout.isTTY ? `\n\n${ result.err }\n\n` : result.err
		);
		throw result.err;
	}

	spinner.text = 'Finished showing logs!';
};
