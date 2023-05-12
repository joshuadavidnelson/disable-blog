describe("URL redirects work when plugin is active", () => {
	before(() => {

	});

	it("Post redirects to homepage", () => {
		cy.visit("/hello-world/");
		cy.wait(2000);
		cy.location("pathname").should("eq", "/");
	});
});
