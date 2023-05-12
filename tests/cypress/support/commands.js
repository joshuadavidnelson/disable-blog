Cypress.Commands.add('setFrontPage', () => {
	cy.wpCli('option update show_on_front page');
	cy.wpCli('option update page_on_front 2');
});

