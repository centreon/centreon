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

const replaceCustomUri = (url: string, uri = '/centreon'): string => {
  return url.replace('/centreon', uri);
};

// Move the web server off the default port 80 so that the loopback address no
// longer answers on :80. This reproduces the on-prem topologies where the
// configuration UI is served on a non-default port (MON-198741): the legacy
// config save calls the REST API internally and must reach the right port.
const updateWebServerPort = (port: number): Cypress.Chainable => {
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    cy.execInContainer({
      command: `sed -i "s/^Listen 80$/Listen ${port}/" /etc/httpd/conf/httpd.conf`,
      name: 'web'
    });
    cy.execInContainer({
      command: `sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" /etc/httpd/conf.d/10-centreon.conf`,
      name: 'web'
    });

    return cy.execInContainer({ command: 'apachectl -t', name: 'web' });
  }

  cy.execInContainer({
    command: `sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf`,
    name: 'web'
  });
  cy.execInContainer({
    command: `sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/centreon.conf`,
    name: 'web'
  });

  return cy.execInContainer({ command: 'apachectl -t', name: 'web' });
};

export {
  reloadWebServer,
  updateWebServerConfig,
  updateWebServerPort,
  replaceCustomUri
};
