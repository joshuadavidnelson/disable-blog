describe("Test comment related stuff", () => {

	it("Post comments disappear when plugin is active", () => {
		cy.login();
		cy.deactivateAllPlugins();
		cy.visit("/wp-admin/edit-comments.php");
		cy.wait(2000);
		cy.get("#the-comment-list").should('not.include.text', 'No comments found.');
		cy.get("#the-comment-list #comment-1").should('exist');

		cy.setUpPlugin();
		cy.visit("/wp-admin/edit-comments.php");
		cy.wait(2000);
		cy.get("#the-comment-list").contains("No comments found.");
		cy.get("#the-comment-list #comment-1").should('not.exist');
	});

});
