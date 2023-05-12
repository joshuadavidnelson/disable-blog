describe("Admin can login, activate the plugin, and set a front page", () => {
	beforeEach(() => {
		cy.login();
	});

	it("Can activate plugin if it is deactivated", () => {
		cy.activatePlugin("disable-blog");
		cy.deactivatePlugin("disable-blog");
		cy.activatePlugin("disable-blog");
	});

	it("Can set front page", () => {
		cy.visit("/wp-admin/options-reading.php");
		cy.get("#front-static-pages input[value=page]").first().check();
		cy.get("select[name=page_on_front]").should("exist");
		cy.get("#page_on_front").select("Sample Page");
		cy.get("p.submit input[type=submit]").click();
	});
});
