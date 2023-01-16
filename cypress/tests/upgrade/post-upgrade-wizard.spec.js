describe("Test Upgrade Centreon", () => {

    context("Test post upgrade wizard page", () => {

        it('should be able to complete the post-upgrade wizard', () => {

            // Go to Centreon on the Web
            cy.visit('/');

            // Verify that the wizard is displayed
            cy.url().should('contain', '/centreon/install/upgrade.php');

            // Run with the wizard pages
            cy.get('#next').click();

            // Dependency check up
            cy.get('.step-wrapper').invoke('innerText').then((text) => {
                expect(text).to.equal('Dependency check up');
            });
            
            // Check if all modules is loaded
            cy.get('.StyleDottedHr td').should('contain', 'Loaded').and('have.length', 7);

            // Next page
            cy.get('#next').click();

            // Check if is all fine
            cy.get('.step-wrapper').invoke('innerText').then((text) => {
                expect(text).to.equal('Release notes');
            });

            // Wait counter
            cy.wait(5000); // Espera 5 segundos antes de continuar

            // Next page
            cy.get('#next').click();

            // Check Installation
            cy.get('.step-wrapper').invoke('innerText').then((text) => {
                expect(text).to.equal('Installation');
            });

            // wait installation and upgrade of migrate db
            cy.wait(10000);

            // Next page
            cy.get('#next').click();

            // Verify that the wizard is done
            cy.get('.step-wrapper').invoke('innerText').then((text) => {
                expect(text).to.equal('Upgrade finished');
            });

            // Finish wizard
            cy.get('#finish').click();

            // Must be go to login page
            cy.url().should('contain', '/centreon/login');

        });

    });
});