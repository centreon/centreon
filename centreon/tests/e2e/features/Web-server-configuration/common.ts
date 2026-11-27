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

// .env of the web application, read by DatabaseTLSResolver
const webEnvFile = '/usr/share/centreon/.env';
// Root CA of the TLS stack (docker-compose.tls.yml)
const databaseCaPath = '/etc/pki/centreon-tls/rootCA.pem';

const databaseTlsOptions: Record<string, string> = {
  DATABASE_CA_PATH: databaseCaPath,
  DATABASE_SSL_ENABLED: '1',
  DATABASE_VERIFY_SERVER_CERT: '1'
};

// Replaces the TLS options of the .env file by the given ones
const buildSetDatabaseTlsOptionsCommand = (): string => {
  const keys = Object.keys(databaseTlsOptions);
  const removal = keys.map((key) => `/^${key}=/d`).join(';');
  const lines = keys.map((key) => `${key}=${databaseTlsOptions[key]}`);

  return `sed -i '${removal}' ${webEnvFile} && printf '%s\\n' ${lines.join(' ')} >> ${webEnvFile}`;
};

const buildRestartWebServicesCommand = (isAlma: boolean): string =>
  isAlma
    ? 'systemctl restart php-fpm && systemctl restart httpd'
    : 'systemctl restart "php$PHP_VERSION-fpm" && systemctl restart apache2';

// Run from the web container: its mysql client uses TLS in the TLS stack
const buildSecureTransportCommand = (enabled: boolean): string =>
  `mysql -h"$MYSQL_HOST" -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SET GLOBAL require_secure_transport = ${enabled ? 'ON' : 'OFF'}"`;

const buildTlsAvailabilityQuery = (): string =>
  "SHOW VARIABLES LIKE 'have_ssl'";

const buildTlsAcceptedConnectionsQuery = (): string =>
  "SHOW GLOBAL STATUS LIKE 'Ssl_accepts'";

export {
  buildRestartWebServicesCommand,
  buildSecureTransportCommand,
  buildSetDatabaseTlsOptionsCommand,
  buildTlsAcceptedConnectionsQuery,
  buildTlsAvailabilityQuery,
  databaseTlsOptions,
  reloadWebServer,
  replaceCustomUri,
  updateWebServerConfig,
  updateWebServerPort,
  webEnvFile
};
