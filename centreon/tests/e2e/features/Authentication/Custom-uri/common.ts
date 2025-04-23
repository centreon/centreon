const reloadWebServer = (): Cypress.Chainable => {
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    return cy.execInContainer({
      command: 'systemctl reload httpd',
      name: 'web'
    });
  }

  return cy.execInContainer({
    command: 'systemctl reload apache2',
    name: 'web'
  });
};

const updateWebServerConfig = (): Cypress.Chainable => {
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    cy.execInContainer({
      command:
        'sed -i "0,/centreon/s//monitor/" /etc/httpd/conf.d/10-centreon.conf',
      name: 'web'
    });
  } else {
    cy.execInContainer({
      command:
        'sed -i "0,/centreon/s//monitor/" /etc/apache2/sites-available/centreon.conf',
      name: 'web'
    });
  }

  return cy.execInContainer({
    command: 'apachectl -t',
    name: 'web'
  });
};

export { reloadWebServer, updateWebServerConfig };
