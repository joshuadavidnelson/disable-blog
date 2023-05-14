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

describe("Front-end redirects work when plugin is active", () => {
	beforeEach(() => {

	});

	it("Post redirects to homepage", () => {
		testRedirect("/hello-world/");
	});

	it("Category redirects to homepage", () => {
		testRedirect("/category/uncategorized/");
	});

	it("Date redirects to homepage", () => {
		const dayjs = require('dayjs');
		const dateArchiveSlug = dayjs().format('/YYYY/MM/');
		testRedirect(dateArchiveSlug);
	});

	it("Author redirects to homepage", () => {
		cy.login();
		cy.activatePlugin('author-archive-filter');
		testRedirect("/author/admin/");
		cy.deactivatePlugin('author-archive-filter');
	});

});

describe("Admin redirects work when plugin is active", () => {
	beforeEach(() => {
		cy.login();
	});

	it("Posts edit page redirects to dashboard", () => {
		testRedirect("/wp-admin/post.php?post=1&action=edit","wp-admin/");
	});

	it("Posts admin screen redirects to pages admin screen", () => {
		testRedirect("/wp-admin/edit.php?post_type=post","wp-admin/edit.php?post_type=page");
	});

	it("New post screen redirects to new page screen", () => {
		testRedirect("/wp-admin/post-new.php?post_type=post","wp-admin/post-new.php?post_type=page");
	});

	// Category edit and term new pages redirect to dashboard.
	it("Category edit page redirects to dashboard", () => {
		testRedirect("/wp-admin/edit-tags.php?taxonomy=category","wp-admin/");
	});

	// Tag edit and tag new pages redirect to dashboard.
	it("Tag edit page redirects to dashboard", () => {
		testRedirect("/wp-admin/edit-tags.php?taxonomy=post_tag","wp-admin/");
	});

	// Redirect writing options to options general.
	it("Writing options redirects to general options", () => {
		cy.login();
		cy.activatePlugin('remove-options-writing');
		testRedirect("/wp-admin/options-writing.php","wp-admin/options-general.php");
		cy.deactivatePlugin('remove-options-writing');
	});
});
