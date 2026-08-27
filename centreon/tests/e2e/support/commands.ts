

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

// ---------------------------------------------------------------------------
// Modernized listing / side-panel form helpers
//
// The migrated configuration pages (AJAX listing + side-panel form) render the
// form inside a second iframe nested in #main-content, and hide the native
// "more actions" select behind a custom menu. These helpers hold the selector
// knowledge so the step definitions stay declarative.
// ---------------------------------------------------------------------------

Cypress.Commands.add('waitForModernListing', (): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');

  return cy
    .getIframeBody()
    .find('#clTableBody td')
    .should('not.contain', 'Loading');
});

Cypress.Commands.add('getSidePanelBody', (): Cypress.Chainable => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

// Row names often share a prefix (a duplicate is "<name>_1"), so rows are
// matched on the exact link text rather than with a substring contains().
const exactRowText = (name: string): RegExp =>
  new RegExp(`^${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`);

Cypress.Commands.add(
  'openSidePanelForm',
  (name: string, fieldSelector: string): Cypress.Chainable => {
    cy.waitForModernListing();
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', exactRowText(name))
      .click();

    return cy
      .getSidePanelBody()
      .find(fieldSelector, { timeout: 20_000 })
      .should('be.visible');
  }
);

// select2 fields are addressed by their visible label: the placeholder is the
// generic "Search"/"Select", and index-based lookups break as soon as a field
// is added to the form.
//
// A plain contains('.cf-field', label) is not enough: labels overlap (the
// escalation form has both "Hosts" and, earlier in the DOM, the "Hosts
// Escalation Options" checkbox group), and contains() returns the first match.
// Restricting to fields that actually hold a <select> picks the select2 one.
const sidePanelSelect2Field = (label: string): Cypress.Chainable =>
  cy
    .getSidePanelBody()
    .find('.cf-field')
    .filter(
      (_index, element) =>
        (element.textContent || '').includes(label) &&
        element.querySelector('select') !== null
    )
    .first();

Cypress.Commands.add(
  'pickSidePanelOption',
  (label: string, option: string): Cypress.Chainable => {
    sidePanelSelect2Field(label).find('.select2-selection').click({ force: true });

    return cy
      .getSidePanelBody()
      .find('.select2-results__option', { timeout: 20_000 })
      .contains(option)
      .click({ force: true });
  }
);

Cypress.Commands.add(
  'clearSidePanelSelection',
  (label: string): Cypress.Chainable => {
    return sidePanelSelect2Field(label)
      .find('.select2-selection__choice__remove')
      .click({ force: true, multiple: true });
  }
);

Cypress.Commands.add(
  'listingRowShouldNotExist',
  (name: string): Cypress.Chainable => {
    cy.waitForModernListing();

    return cy
      .getIframeBody()
      .find('#clTableBody a')
      .filter((_index, element) => element.textContent?.trim() === name)
      .should('have.length', 0);
  }
);

// The empty state, the "Loading..." placeholder and the load-error row are all
// rendered as one full-width cell, so a data row is a <tr> without a colspan
// cell. Counting rows this way lets a search assert its whole result set
// instead of only the presence of the row it expects.
const listingDataRows = (): Cypress.Chainable =>
  cy
    .getIframeBody()
    .find('#clTableBody tr')
    .filter((_index, element) => element.querySelector('td[colspan]') === null);

Cypress.Commands.add('listingShouldBeEmpty', (): Cypress.Chainable => {
  return listingDataRows().should('have.length', 0);
});

Cypress.Commands.add(
  'listingShouldContainOnly',
  (name: string): Cypress.Chainable => {
    listingDataRows().should('have.length', 1);

    return listingDataRows().contains('a', exactRowText(name)).should('exist');
  }
);

Cypress.Commands.add(
  'runListingBulkAction',
  (name: string, action: string): Cypress.Chainable => {
    cy.waitForModernListing();
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', exactRowText(name))
      .parents('tr')
      // The real checkbox is visibility:hidden behind its md-checkbox label.
      .find('.cl-col-picker input[type="checkbox"]')
      .click({ force: true });
    cy.getIframeBody()
      .find('select[name="o1"]')
      .invoke(
        'attr',
        'onchange',
        "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
      );

    // The native o1 select is hidden (replaced by the .cl-more-actions menu);
    // the overridden onchange above turns a value change into setO + submit.
    return cy
      .getIframeBody()
      .find('select[name="o1"]')
      .select(action, { force: true });
  }
);

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
        paramValue,
      }: Serviceparams) => Cypress.Chainable;
      enterIframe: (iframeSelector: string) => Cypress.Chainable;
      checkFirstRowFromListing: (waitElt: string) => Cypress.Chainable;
      waitForModernListing: () => Cypress.Chainable;
      getSidePanelBody: () => Cypress.Chainable<JQuery<HTMLElement>>;
      openSidePanelForm: (
        name: string,
        fieldSelector: string
      ) => Cypress.Chainable;
      runListingBulkAction: (
        name: string,
        action: string
      ) => Cypress.Chainable;
      listingRowShouldNotExist: (name: string) => Cypress.Chainable;
      listingShouldBeEmpty: () => Cypress.Chainable;
      listingShouldContainOnly: (name: string) => Cypress.Chainable;
      pickSidePanelOption: (
        label: string,
        option: string
      ) => Cypress.Chainable;
      clearSidePanelSelection: (label: string) => Cypress.Chainable;
      fillFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
      clickOnFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
    }
  }
}
