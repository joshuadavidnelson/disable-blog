describe('Sitemap checks', () => {
	// initialize the url array
	let urls = [];

	// be sure to get the url list before executing any tests
	before(()=>{
		cy.request('sitemap.xml')
			.as('sitemap')
			.then((response) => {
			urls = Cypress.$(response.body)
					.find('loc')
					.toArray()
					.map(el => el.innerText);
			});
	});

	beforeEach(() => {
		cy.login();
	});

	it('Each url in the sitemap works', () => {
		cy.deactivateAllPlugins();
		cy.checkUrls(urls);
	});

	it('Sitemap is accessible after activating plugin', () => {
		cy.setUpPlugin();
		cy.urlReturns('/wp-sitemap-posts-page-1.xml');

	});

	it('Post sitemap is removed from sitemap after activating plugin', () =>{
		cy.deactivateAllPlugins();
		cy.request('wp-sitemap.xml').then(response => {
			expect(response.body).to.contain('/wp-sitemap-posts-post');
		});
		cy.urlReturns('/wp-sitemap-posts-post-1.xml');

		cy.activatePlugin('disable-blog');
		cy.request('wp-sitemap.xml').then(response => {
			expect(response.body).to.not.contain('/wp-sitemap-posts-post');
		});
		cy.urlReturns('/wp-sitemap-posts-post-1.xml',404);
	});
});
