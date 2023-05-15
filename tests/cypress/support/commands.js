Cypress.Commands.add('testRedirect', (origin, expectedUrl = "", responseCode = 301) => {

	// Append the base url to the expected url.
	expectedUrl = Cypress.config().baseUrl + expectedUrl;

	// Test the redirect.
	return cy.request({
		url: origin,
		followRedirect: false, // turn off following redirects
	}).then((resp) => {
		expect(resp.status).to.eq(responseCode);
		expect(resp.redirectedToUrl).to.eq(expectedUrl);
	});
});

// Test that a url returns a specific response code.
Cypress.Commands.add('urlReturns', (origin, responseCode = 200, followRedirect=false) => {
	return cy.request({
		url: origin,
		followRedirect: followRedirect,
		failOnStatusCode: false,
	}).then((resp) => {
		expect(resp.status).to.eq(responseCode);
	});
});

Cypress.Commands.add('setUpPlugin', () => {
	cy.login();
	cy.deactivateAllPlugins();
	cy.activatePlugin('disable-blog');
});

Cypress.Commands.add('checkUrls', (urls, responseCode = 200) => {
	urls.forEach((url) => {
		cy.log(url);
		cy.urlReturns(url, responseCode);
	});
});

