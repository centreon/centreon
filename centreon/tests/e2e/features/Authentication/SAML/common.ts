import { PAGES } from 'fixtures/shared/constants/pages';
import { ActionClapi } from '../../../commons';

interface SamlConfigValues {
  entityId: string;
  loginAttribute: string;
  logoutUrl: string;
  remoteLoginUrl: string;
  x509Certificate: string;
}

const getSamlConfigValues = (): SamlConfigValues => {
  const keycloakUrl = 'http://localhost:8080/realms/Centreon_SSO';

  return {
    entityId: keycloakUrl,
    loginAttribute: 'urn:oid:1.2.840.113549.1.9.1', // email
    logoutUrl: `${keycloakUrl}/protocol/saml`,
    remoteLoginUrl: `${keycloakUrl}/protocol/saml/clients/centreon`,
    x509Certificate:
      'MIICpzCCAY8CBgGFydyVcDANBgkqhkiG9w0BAQsFADAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wHhcNMjMwMTE5MTE0NzM0WhcNMzMwMTE5MTE0OTE0WjAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCCpNndecGJI2xOaNQXDDvwDwo/beQ7Q4HW/ck1BNkE13IgPf5GRpvP2jp/1IZsx92vQ2Ub9g5urNG/jo3nZzsUUIdTICsN9Bq2OIjYU9Uxmc1PpHzklN/SqZWbKXOw8EzqXkQ3YNXHqL9omJJ5JMxe4zg758zlvOUh3I44XhMy6PKgeReJIm+HxYJ8SKeu/XVRI7Uiyav5L2M85ED3kqiI3iPrGfLQzv8zqkTeNfuZIeigqI+M8MqRxR3Qf0UlmWA3ZAzsoxJUU+e0tHnD7MhgyRLfg76FjQ1U7Tv7X/h8uqRthjTbva5v0k0M85z21C85UrHxpS3e/HJFInrkJredAgMBAAEwDQYJKoZIhvcNAQELBQADggEBADQANd/iYhefXpcqXC+co3fEe7IaZ93XelZzJ5S4OAR5dHnhMMlMQnnscW/nH8NAEwWRImJPfOEcKun8rBUphZZJxi2WHHj5ilhGdNtcyZzh0sufyIQav/QMreGmDEj/J/uRfmG15Lj1wJB6mw+O4kuwJj/8DzxK6/sQYPisJuXrSWrDmcpvShvbo59JbVjdYK49WXVDbl++7hrwiOYuCQ/uodQYgvChZnIQbL4O6TbG4OLy+prFd5FBsEQds8ZNXoLWM5bCUz+bz4N68fAqhtPR8+yR+pIrE7/cvRaRCmgnG0s61JBZVxHoT4dbMJUTTSSS4dWCUUNhMCIFtEKL06c='
  };
};

const configureSaml = (): Cypress.Chainable => {
  const samlConfigValues = getSamlConfigValues();

  cy.contains('Enable SAMLv2 authentication').should('be.visible');

  cy.getByLabel({ label: 'Identity provider', tag: 'div' }).click();
  cy.getByLabel({ label: 'Remote login URL', tag: 'input' })
    .should('be.visible')
    .type(`{selectall}{backspace}${samlConfigValues.remoteLoginUrl}`);

  cy.getByLabel({ label: 'Issuer (Entity ID) URL', tag: 'input' })
    .should('be.visible')
    .type(`{selectall}{backspace}${samlConfigValues.entityId}`);

  cy.getByLabel({
    label: 'Copy/paste x.509 certificate',
    tag: 'textarea'
  })
    .should('be.visible')
    .type(`{selectall}{backspace}${samlConfigValues.x509Certificate}`);

  cy.getByLabel({
    label: 'User ID (login) attribute for Centreon user',
    tag: 'input'
  })
    .should('be.visible')
    .type(`{selectall}{backspace}${samlConfigValues.loginAttribute}`);

  cy.getByLabel({ label: 'Enable requested authentication context' }).should(
    'exist'
  );
  cy.getByLabel({ label: 'Enable requested authentication context' }).click();
  cy.getByTestId({
    testId: 'Comparison rule for the requested authentication context'
  }).should('exist');

  cy.getByLabel({
    label: 'Both identity provider and Centreon UI',
    tag: 'input'
  }).check();

  return cy
    .getByLabel({ label: 'Logout URL', tag: 'input' })
    .should('be.visible')
    .type(`{selectall}{backspace}${samlConfigValues.logoutUrl}`);
};

const saveSamlFormIfEnabled = () => {
  return cy.getByLabel({ label: 'save button', tag: 'button' }).then(($btn) => {
    if ($btn.is(':disabled')) {
      return;
    }
    cy.wrap($btn).click();

    return cy
      .wait('@updateSAMLProvider')
      .its('response.statusCode')
      .should('eq', 204);
  });
};

const navigateToSamlConfigPage = (): Cypress.Chainable => {
  cy.visit(PAGES.configuration.authentication)
    .get('div[role="tablist"] button:nth-child(4)')
    .click();

  return cy.wait('@getSAMLProvider');
};

const initializeSamlUser = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-SAML/SAML-authentication-user.json')
    .then((fixture: Array<ActionClapi>) => {
      fixture.forEach((action) =>
        cy.executeActionViaClapi({ bodyContent: action })
      );
    });
};

const removeContact = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'CONTACT',
        values: 'oidc'
      }
    });
  });
};

export {
  initializeSamlUser,
  removeContact,
  configureSaml,
  navigateToSamlConfigPage,
  saveSamlFormIfEnabled
};
