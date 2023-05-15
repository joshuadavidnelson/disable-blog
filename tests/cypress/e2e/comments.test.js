describe("Test comment related stuff", () => {
	beforeEach(() => {
		cy.setUpPlugin();
	});

	it("No comments in default site with plugin active", () => {
		cy.visit("/wp-admin/edit-comments.php");
		cy.wait(2000);
		cy.get("#the-comment-list").contains("No comments found.");
	});

});
