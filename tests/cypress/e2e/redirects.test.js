describe("URL redirects work when plugin is active", () => {
	before(() => {
		cy.login();
	});

	it("Blog page redirects to homepage", () => {
		cy.visit("/blog/");
		cy.wait(2000);
		cy.location("pathname").should("eq", "/");
	});

	it("Post redirects to homepage", () => {
		cy.visit("/hello-world/");
		cy.wait(2000);
		cy.location("pathname").should("eq", "/");
	});
});
