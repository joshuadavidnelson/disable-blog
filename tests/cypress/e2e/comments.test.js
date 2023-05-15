describe("Test comment related stuff", () => {
	beforeEach(() => {
		cy.setUpPlugin();
	});

	it("No comments in default site with plugin active", () => {
		cy.visit("/wp-admin/edit-comments.php");
		cy.wait(2000);
		cy.get("#the-comment-list").contains("No comments found.");
	});

	it("Disable comments plugin works", () => {
		cy.installPlugin("disable-comments");
		cy.testRedirect("/wp-admin/edit-comments.php","wp-admin/");
		cy.testRedirect("/wp-admin/edit-comments.php?action=editcomment&c=1","wp-admin/");
		cy.deletePlugin("disable-comments");
		cy.request({
			url: "/wp-admin/edit-comments.php",
			followRedirect: false, // turn off following redirects
		}).then((resp) => {
			expect(resp.status).to.eq(200);
		});
	});

});
