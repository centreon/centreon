/* eslint-disable @typescript-eslint/no-namespace */

import 'cypress-wait-until';
import '@centreon/js-config/cypress/e2e/commands';
import { refreshButton } from '../features/Resources-status/common';
import '../features/ACLs/commands';
import '../features/Api-Token/commands';
import '../features/Dashboards/commands';
import '../features/Resources-Access-Management/commands';
import '../features/Backup-configuration/commands';
import '../features/Hosts/commands';
import '../features/Contacts/commands';
import '../features/Ldaps/commands';
import '../features/Services-configuration/commands';
import '../features/Agent-configuration/commands';
import '../features/Logs/commands';
import '../features/Notifications/commands';
import '../features/Commands/commands';
import '../features/Resources-status/commands';
import '../features/Platform-upgrade-update/commands';

Cypress.Commands.add('refreshListing', (): Cypress.Chainable => {
  return cy.get(refreshButton).click();
});

Cypress.Commands.add('disableListingAutoRefresh', (): Cypress.Chainable => {
  return cy.getByTestId({ testId: 'Disable autorefresh' }).click();
});

Cypress.Commands.add('removeResourceData', (): Cypress.Chainable => {
  return cy.executeActionViaClapi({
    bodyContent: {
      action: 'DEL',
      object: 'HOST',
      values: 'test_host'
    }
  });
});

Cypress.Commands.add('loginKeycloak', (containerName, jsonName): Cypress.Chainable => {
  cy.url().should('include', '/realms/Centreon_SSO');

  return cy.getContainerIpAddress(containerName).then((containerIpAddress) => {
    return cy.origin(`http://${containerIpAddress}:8080`, { args: { jsonName } }, ({ jsonName }) => {
      cy.fixture(`users/${jsonName}.json`).then((credential) => {
        cy.get('#username').type(`{selectall}{backspace}${credential.login}`);
        cy.get('#password').type(`{selectall}{backspace}${credential.password}`);
      });

      return cy.get('#kc-login').click();
    });
  });
});

Cypress.Commands.add(
  'isInProfileMenu',
  (targetedMenu: string): Cypress.Chainable => {
    cy.get('header svg[aria-label="Profile"]').click();

    return cy.get('div[role="tooltip"]').contains(targetedMenu);
  }
);

Cypress.Commands.add('removeACL', (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'ACLMENU',
        values: 'acl_menu_test'
      }
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'ACLGROUP',
        values: 'ACL Group test'
      }
    });
  });
});

interface Serviceparams {
  name: string;
  paramName: string;
  paramValue: string;
}

Cypress.Commands.add(
  "setServiceParameters",
  ({ name, paramName, paramValue }: Serviceparams): Cypress.Chainable => {
    return cy.executeActionViaClapi({
      bodyContent: {
        action: "SETPARAM",
        object: "HOST",
        values: `${name};${paramName};${paramValue}`,
      },
    });
  }
);

Cypress.Commands.add("enterIframe", (iframeSelector): Cypress.Chainable => {
  return cy.get(iframeSelector)
    .its("0.contentDocument");
});

Cypress.Commands.add("checkFirstRowFromListing", (waitElt) => {
  cy.waitForElementInIframe('#main-content', `input[name=${waitElt}]`);
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
});

Cypress.Commands.add('fillFieldInIframe',(body: HtmlElt)=> {
  cy.getIframeBody()
  .find(`${body.tag}[${body.attribut}="${body.attributValue}"]`)
  .clear()
  .type(body.valueOrIndex);
});

Cypress.Commands.add('clickOnFieldInIframe',(body: HtmlElt)=> {
  cy.getIframeBody().find(`${body.tag}[${body.attribut}="${body.attributValue}"]`).eq(Number(body.valueOrIndex)).click();
});

interface HtmlElt {
  tag: string,
  attribut: string,
  attributValue: string,
  valueOrIndex: string
}

declare global {
  namespace Cypress {
    interface Chainable {
      disableListingAutoRefresh: () => Cypress.Chainable;
      isInProfileMenu: (targetedMenu: string) => Cypress.Chainable;
      loginKeycloak: (containerName: string, jsonName: string) => Cypress.Chainable;
      refreshListing: () => Cypress.Chainable;
      removeACL: () => Cypress.Chainable;
      removeResourceData: () => Cypress.Chainable;
      startOpenIdProviderContainer: () => Cypress.Chainable;
      stopOpenIdProviderContainer: () => Cypress.Chainable;
      setServiceParameters: ({
        name,
        paramName,
        paramValue,
      }: Serviceparams) => Cypress.Chainable;
      enterIframe: (iframeSelector: string) => Cypress.Chainable;
      checkFirstRowFromListing: (waitElt: string) => Cypress.Chainable;
      fillFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
      clickOnFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
    }
  }
}
