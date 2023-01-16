describe("Tests with Debian Upgrade Centreon", () => {

    context("Prepare repository", () => {

        it('should be able to configure the repository for the new Centreon version', () => {
            // Add the Centreon repository for the new version
            cy.exec('echo "deb https://apt.centreon.com/repository/23.04/ bullseye main" | tee /etc/apt/sources.list.d/centreon.list');
          
            // Download and add the repository signing key
            cy.exec('wget -O- https://apt-key.centreon.com | gpg --dearmor > /etc/apt/trusted.gpg.d/centreon.gpg');
          
            // Update the package indices
            cy.exec('apt clean all').its('stderr').should('be.empty');
            cy.exec('apt update').its('stderr').should('be.empty');
        });

    });

    context("Prepare upgrade from APT", () => {

        it('should be able to update Centreon packages using apt', () => {
            // Check the current version of Centreon
            cy.exec('centreon -V | sed -n 1p').then((result) => {
              const currentVersion = result.stdout;
            });

            // Stop and disable the PHP 8.0 FPM service
            //cy.exec('systemctl stop php8.0-fpm').its('stderr').should('be.empty');
            //cy.exec('systemctl disable php8.0-fpm').its('stderr').should('be.empty');

            // Stop the Broker service
            cy.exec('systemctl stop cbd').its('stderr').should('be.empty');
            cy.exec('rm -rf /var/lib/centreon-broker/*').its('stderr').should('be.empty');
          
            // Update the package indices
            cy.exec('apt update').its('stderr').should('be.empty');
        
            // Upgrade the Centreon packages
            cy.exec('apt upgrade -y centreon', { timeout: 1000000 }).its('stderr').should('be.empty');
        
            // Check the version of Centreon again to ensure it was updated
            cy.exec('centreon -V | sed -n 1p').then((result) => {
                const updatedVersion = result.stdout;
                expect(updatedVersion).to.not.equal(currentVersion);
            });

            // Restart services
            cy.exec('apt autoremove -y').its('stderr').should('be.empty');
            cy.exec('systemctl daemon-reload').its('stderr').should('be.empty');
            cy.exec('systemctl enable php8.1-fpm').its('stderr').should('be.empty');
            cy.exec('systemctl start php8.1-fpm').its('stderr').should('be.empty');
            cy.exec('systemctl restart apache2').its('stderr').should('be.empty');

        });

    });

});
