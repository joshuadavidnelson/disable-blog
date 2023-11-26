const { defineConfig } = require('cypress')
const { readConfig } = require( '@wordpress/env/lib/config' );

module.exports = defineConfig({
	fixturesFolder: 'tests/cypress/fixtures',
	video: false,
	e2e: {
		// We've imported your old cypress plugins here.
		// You may want to clean this up later by importing these.
		setupNodeEvents(on, config) {
			return setBaseUrl( on, config );
		},
		specPattern: 'tests/cypress/e2e/**/*.test.{js,jsx,ts,tsx}',
		supportFile: 'tests/cypress/support/e2e.js',
	},
})

/**
 * Set WP URL as baseUrl in Cypress config.
 *
 * @param {Function} on     function that used to register listeners on various events.
 * @param {Object}   config Cypress Config object.
 * @return {Object} Updated Cypress Config object.
 */
const setBaseUrl = async ( on, config ) => {
	const wpEnvConfig = await readConfig( 'wp-env' );

	if ( wpEnvConfig ) {
		const port = wpEnvConfig.env.tests.port || null;

		if ( port ) {
			config.baseUrl = 'http://localhost:8889/';
		}
	}

	return config;
};
