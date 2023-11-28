const restartWebServer = (): Cypress.Chainable => {
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    return cy.execInContainer({
      command: 'systemctl restart httpd',
      name: Cypress.env('dockerName')
    });
  }

  return cy.execInContainer({
    command: 'systemctl restart apache2',
    name: Cypress.env('dockerName')
  });
};

const updateWebServerConfig = (): Cypress.Chainable => {
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    cy.execInContainer({
      command: `bash -c "sed -i '0,/centreon/s//monitor/' /etc/httpd/conf.d/10-centreon.conf"`,
      name: Cypress.env('dockerName')
    });
  } else {
    cy.execInContainer({
      command: `bash -c "sed -i '0,/centreon/s//monitor/' /etc/apache2/sites-available/centreon.conf"`,
      name: Cypress.env('dockerName')
    });
  }

  return cy.execInContainer({
    command: `bash -c "apachectl -t"`,
    name: Cypress.env('dockerName')
  });
};

export { restartWebServer, updateWebServerConfig };
