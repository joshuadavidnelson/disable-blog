describe("Admin can login, activate the plugin, and set a front page", () => {
	beforeEach(() => {
		cy.login();
	});

	it("Can activate plugin and deactivate plugin", () => {
		cy.activatePlugin("disable-blog");
		cy.deactivatePlugin("disable-blog");
		cy.activatePlugin("disable-blog");
	});

	it("Can set front page", () => {
		cy.setUpPlugin();
		cy.visit("/wp-admin/options-reading.php");
		cy.wait(2000);
		cy.get("#front-static-pages input[value=page]").first().check();
		cy.get("select[name=page_on_front]").should("exist");
		cy.get("#page_on_front").select("Sample Page");
		cy.get("p.submit input[type=submit]").click();
		cy.wait(2000);
		cy.get("#setting-error-settings_updated").should("exist").contains("Settings saved.");
		cy.get("select#page_on_front").should('not.be.empty').and(($select) => {
			const val = Number($select.val());
			expect(val).to.be.greaterThan(0);
		});
	});
});
