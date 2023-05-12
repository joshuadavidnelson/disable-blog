const testRedirect = (origin, expectedUrl = "", responseCode = 301) => {

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
};

describe("URL redirects work when plugin is active", () => {
	before(() => {

	});

	it("Post redirects to homepage", () => {
		testRedirect("/hello-world/");
	});
	});
});
