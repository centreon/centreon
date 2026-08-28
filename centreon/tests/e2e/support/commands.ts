import 'cypress-wait-until';
import '../../../packages/js-config/cypress/e2e/commands';

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
import '../features/Additional-connectors/commands';
import '../features/Macros/commands';

import type { ActionClapi } from '../commons';

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

Cypress.Commands.add('loginKeycloak', (jsonName): Cypress.Chainable => {
  cy.url().should('include', '/realms/Centreon_SSO');

  cy.fixture(`users/${jsonName}.json`).then((credential) => {
    cy.get('#username').type(`{selectall}{backspace}${credential.login}`);
    cy.get('#password').type(`{selectall}{backspace}${credential.password}`);
  });

  return cy.get('#kc-login').click();
});

Cypress.Commands.add(
  'isInProfileMenu',
  (targetedMenu: string): Cypress.Chainable => {
    cy.get('header [aria-label="Profile"]').click();

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

Cypress.Commands.add(
  'applyAclProfile',
  (fixturePath: string): Cypress.Chainable => {
    cy.fixture(fixturePath).then((actions: Array<ActionClapi>) => {
      actions.forEach((action) => {
        cy.executeActionViaClapi({ bodyContent: action });
      });
    });

    return cy.applyAcl();
  }
);

interface Serviceparams {
  name: string;
  paramName: string;
  paramValue: string;
}

Cypress.Commands.add(
  'setServiceParameters',
  ({ name, paramName, paramValue }: Serviceparams): Cypress.Chainable => {
    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'SETPARAM',
        object: 'HOST',
        values: `${name};${paramName};${paramValue}`
      }
    });
  }
);

Cypress.Commands.add('enterIframe', (iframeSelector): Cypress.Chainable => {
  return cy.get(iframeSelector).its('0.contentDocument');
});

Cypress.Commands.add('checkFirstRowFromListing', (waitElt) => {
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

/**
 * Body of the form side panel, which the modernized listings open instead of
 * navigating to a full page. It is an iframe nested inside #main-content, so
 * getIframeBody() alone cannot reach it.
 */
Cypress.Commands.add('getSidePanelBody', (): Cypress.Chainable => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body')
    .should('not.be.empty')
    .then(cy.wrap);
});

/**
 * Tick a row's checkbox on a modernized listing. The real input sits behind its
 * md-checkbox label and is not visible, hence the forced click.
 */
Cypress.Commands.add(
  'checkListingRow',
  (rowLabel: string): Cypress.Chainable => {
    return cy
      .getIframeBody()
      .find('#clTableBody')
      .contains('tr', rowLabel)
      .find('.cl-col-picker input[type="checkbox"]')
      .click({ force: true });
  }
);

/**
 * Run a "More actions" bulk action the way a user does: tick the row, open the
 * styled .cl-more-actions menu, then confirm in the modal. The native select is
 * display:none, and re-wiring its onchange would submit straight through and
 * leave the menu, the modal and the translated data-* wording it renders
 * untested.
 */
Cypress.Commands.add(
  'runListingBulkAction',
  (name: string, action: string, expectedTitle: string): void => {
    cy.checkListingRow(name);

    cy.getIframeBody().find('.cl-more-actions-btn').click();
    cy.getIframeBody()
      .find('.cl-more-actions-item')
      .contains(action)
      .click({ force: true });

    cy.getIframeBody()
      .find('.cl-confirm-modal', { timeout: 10_000 })
      .should('be.visible');
    cy.getIframeBody()
      .find('.cl-confirm-title')
      .should('have.text', expectedTitle);
    // {{ name }} is interpolated in bold. Asserting the element carries content
    // proves the translated message rendered, without pinning which column the
    // framework reads the label from.
    cy.getIframeBody().find('.cl-confirm-body strong').should('not.be.empty');
    cy.getIframeBody().find('.cl-confirm-confirm-btn').click();
  }
);

Cypress.Commands.add('fillFieldInIframe', (body: HtmlElt) => {
  cy.getIframeBody()
    .find(`${body.tag}[${body.attribut}="${body.attributValue}"]`)
    .clear()
    .type(body.valueOrIndex);
});

Cypress.Commands.add('clickOnFieldInIframe', (body: HtmlElt) => {
  cy.getIframeBody()
    .find(`${body.tag}[${body.attribut}="${body.attributValue}"]`)
    .eq(Number(body.valueOrIndex))
    .click();
});

interface HtmlElt {
  tag: string;
  attribut: string;
  attributValue: string;
  valueOrIndex: string;
}

declare global {
  namespace Cypress {
    interface Chainable {
      applyAclProfile: (fixturePath: string) => Cypress.Chainable;
      disableListingAutoRefresh: () => Cypress.Chainable;
      isInProfileMenu: (targetedMenu: string) => Cypress.Chainable;
      loginKeycloak: (jsonName: string) => Cypress.Chainable;
      refreshListing: () => Cypress.Chainable;
      removeACL: () => Cypress.Chainable;
      removeResourceData: () => Cypress.Chainable;
      startOpenIdProviderContainer: () => Cypress.Chainable;
      stopOpenIdProviderContainer: () => Cypress.Chainable;
      setServiceParameters: ({
        name,
        paramName,
        paramValue
      }: Serviceparams) => Cypress.Chainable;
      enterIframe: (iframeSelector: string) => Cypress.Chainable;
      checkFirstRowFromListing: (waitElt: string) => Cypress.Chainable;
      fillFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
      clickOnFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
      getSidePanelBody: () => Cypress.Chainable;
      checkListingRow: (rowLabel: string) => Cypress.Chainable;
      runListingBulkAction: (
        name: string,
        action: string,
        expectedTitle: string
      ) => void;
    }
  }
}
