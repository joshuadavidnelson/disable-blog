describe("REST Endpoint Tests", () => {
	beforeEach(() => {
		cy.login();
		cy.deactivateAllPlugins();
	});

	it("REST API works with plugin deactivated", () => {
		cy.request({
			url: "wp-json/wp/v2/posts",
			followRedirect: false, // turn off following redirects
		}).then((resp) => {
			expect(resp.status).to.eq(200);
		});
	});

	it("REST API for posts is disabled with plugin activated", () => {
		cy.activatePlugin('disable-blog');
		cy.request({
			url: "wp-json/wp/v2/posts",
			failOnStatusCode: false
		}).then((resp) => {
			expect(resp.status).to.eq(404);
			expect(resp.body).to.have.property("code", "rest_no_route");
		});
	});

});

// XML-RPC
describe("XMLRPC Endpoint Tests", () => {
	beforeEach(() => {
		cy.login();
		cy.deactivateAllPlugins();
	});

	it("XMLRPC works with plugin deactivated", () => {
		cy.request({
			url: "xmlrpc.php?rsd",
			followRedirect: false, // turn off following redirects
		}).then((resp) => {
			expect(resp.status).to.eq(200);
		});
		cy.visit("/");
		cy.get("link[rel=EditURI]").should('have.attr', 'href').and('include', 'xmlrpc.php?rsd');
	});

	it("XMLRPC link and routes disabled with plugin activated", () => {
		cy.activatePlugin('disable-blog');
		cy.request({
			url: "xmlrpc.php?rsd",
			failOnStatusCode: false
		}).then((resp) => {
			expect(resp.status).to.eq(200);
		});
		cy.visit("/");
		cy.get("link[rel=EditURI]").should('not.exist');
	});

});
