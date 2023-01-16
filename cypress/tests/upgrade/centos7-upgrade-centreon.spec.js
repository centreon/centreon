describe("Tests with CentOS 7 Upgrade Centreon", () => {

    context("Prepare repository", () => {

        it('should be able to configure the repository for the new Centreon version', () => {
            // Add the Centreon repository for the new version
            cy.exec('yum install -y https://yum.centreon.com/standard/23.04/el7/stable/noarch/RPMS/centreon-release-22.10-1.el7.centos.noarch.rpm').its('stderr').should('be.empty');
          
            // Enable PHP 8.1
            cy.exec('yum-config-manager --enable remi-php81').its('stderr').should('be.empty');

            // Clean yum data
            cy.exec('yum clean all --enablerepo=*').its('stderr').should('be.empty');
        });

    });

    context("Prepare upgrade from YUM", () => {

        it('should be able to update Centreon packages using yum', () => {
            // Check the current version of Centreon
            cy.exec('centreon -V | sed -n 1p').then((result) => {
              const currentVersion = result.stdout;
            });

            // Stop the Broker service
            cy.exec('systemctl stop cbd').its('stderr').should('be.empty');
            cy.exec('rm -rf /var/lib/centreon-broker/*').its('stderr').should('be.empty');
        
            // Upgrade the Centreon packages
            cy.exec('yum update -y centreon\* php-pecl-gnupg', { timeout: 1000000 }).its('stderr').should('be.empty');
        
            // Check the version of Centreon again to ensure it was updated
            cy.exec('centreon -V | sed -n 1p').then((result) => {
                const updatedVersion = result.stdout;
                expect(updatedVersion).to.not.equal(currentVersion);
            });

            // Restart services
            cy.exec('systemctl daemon-reload').its('stderr').should('be.empty');
            cy.exec('systemctl enable php-fpm').its('stderr').should('be.empty');
            cy.exec('systemctl start php-fpm').its('stderr').should('be.empty');
            cy.exec('systemctl restart httpd24-httpd').its('stderr').should('be.empty');

        });

    });

});
