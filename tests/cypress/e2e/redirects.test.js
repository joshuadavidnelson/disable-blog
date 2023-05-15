
describe("Front-end redirects work when plugin is active", () => {
	beforeEach(() => {
		cy.setUpPlugin();
	});

	it("Post redirects to homepage", () => {
		cy.testRedirect("/hello-world/");
	});

	it("Category redirects to homepage", () => {
		cy.testRedirect("/category/uncategorized/");
	});

	it("Date redirects to homepage", () => {
		const dayjs = require('dayjs');
		const dateArchiveSlug = dayjs().format('/YYYY/MM/');
		cy.testRedirect(dateArchiveSlug);
	});

	it("Author redirects to homepage with filter", () => {
		cy.activatePlugin('author-archive-filter');
		cy.testRedirect("/author/admin/");
		cy.deactivatePlugin('author-archive-filter');
	});

});

describe("Admin redirects work when plugin is active", () => {
	beforeEach(() => {
		cy.setUpPlugin();
	});

	it("Posts edit page redirects to dashboard", () => {
		cy.testRedirect("/wp-admin/post.php?post=1&action=edit","wp-admin/");
	});

	it("Posts admin screen redirects to pages admin screen", () => {
		cy.testRedirect("/wp-admin/edit.php?post_type=post","wp-admin/edit.php?post_type=page");
	});

	it("New post screen redirects to new page screen", () => {
		cy.testRedirect("/wp-admin/post-new.php?post_type=post","wp-admin/post-new.php?post_type=page");
	});

	// Category edit and term new pages redirect to dashboard.
	it("Category edit page redirects to dashboard", () => {
		cy.testRedirect("/wp-admin/edit-tags.php?taxonomy=category","wp-admin/");
	});

	// Tag edit and tag new pages redirect to dashboard.
	it("Tag edit page redirects to dashboard", () => {
		cy.testRedirect("/wp-admin/edit-tags.php?taxonomy=post_tag","wp-admin/");
	});

	// Redirect writing options to options general.
	it("Writing options redirects to general options with filter", () => {
		cy.activatePlugin('remove-options-writing');
		cy.testRedirect("/wp-admin/options-writing.php","wp-admin/options-general.php");
		cy.deactivatePlugin('remove-options-writing');
	});
});
