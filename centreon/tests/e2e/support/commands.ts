

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

// Query-only accessor for the listing document. getIframeBody() is built on
// waitUntil + .then(), so after a legacy form submit reloads #main-content it can
// hand back the body of the previous document; .its() is a query and Cypress
// re-evaluates it, landing on the current one. Its subject is a raw body element,
// which .find() accepts and .contains() does not.
Cypress.Commands.add('getListingBody', (): Cypress.Chainable => {
  return cy
    .get('iframe#main-content', { log: false })
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty');
});

// Presence in the listing, waited on for the same reason as its absence twin:
// contains() on a held tbody fails once a refresh replaces it.
Cypress.Commands.add(
  'waitForListingToShow',
  (text: string): Cypress.Chainable => {
    return cy.waitUntil(
      () =>
        cy
          .getListingBody()
          .then(
            ($body) =>
              Cypress.$($body).find(`#clTableBody *:contains("${text}")`)
                .length > 0
          ),
      {
        errorMsg: `"${text}" never appeared in the listing`,
        interval: 500,
        timeout: 30_000
      }
    );
  }
);

// Absence in the listing, waited on rather than asserted. A chained
// find().should('not.exist') pins a subject that the post-action reload replaces,
// and Cypress then refuses to requery it. waitUntil re-runs the whole lookup on
// every attempt, which is how waitForElementInIframe already handles presence.
Cypress.Commands.add(
  'waitForListingToDrop',
  (text: string): Cypress.Chainable => {
    return cy.waitUntil(
      () =>
        cy
          .getListingBody()
          .then(
            ($body) =>
              Cypress.$($body).find(`#clTableBody *:contains("${text}")`)
                .length === 0
          ),
      {
        errorMsg: `"${text}" still listed after waiting`,
        interval: 500,
        timeout: 30_000
      }
    );
  }
);

Cypress.Commands.add(
  'visitListingAndWait',
  (page: string): Cypress.Chainable => {
    cy.visit(page);
    cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');

    return cy
      .getIframeBody()
      .find('#clTableBody tr')
      .should('have.length.greaterThan', 0);
  }
);

Cypress.Commands.add('waitForListingRefresh', (): Cypress.Chainable => {
  return cy
    .getListingBody()
    .find('#clTableBody tr td', { timeout: 20_000 })
    .should('not.contain', 'Loading');
});

// Migrated forms open in a side panel, which is an iframe nested inside
// #main-content: cy.getIframeBody() alone no longer reaches their fields.
Cypress.Commands.add('getListingSidePanelBody', (): Cypress.Chainable => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

// Where the form currently lives: the side panel when a migrated listing has
// one open, #main-content otherwise. Legacy pages have no #cfSidePanel at all,
// so this is a no-op for them.
Cypress.Commands.add('getFormBody', (): Cypress.Chainable => {
  return cy.getIframeBody().then(($body) => {
    if ($body.find('#cfSidePanel.open').length === 0) {
      return cy.wrap($body);
    }

    return cy.getListingSidePanelBody();
  });
});

Cypress.Commands.add(
  'openListingRowForm',
  (name: string): Cypress.Chainable => {
    // closePanel() drops the .open class synchronously but resets the iframe src
    // to about:blank 300ms later, once the CSS transition ends. Clicking inside
    // that window lets the pending timeout overwrite the src that cfOpenPanel
    // just set, and the panel then loads blank for good. So wait for the reset
    // itself, not for .open to go away — that one is already gone.
    cy.waitUntil(
      () =>
        cy
          .get('iframe#main-content', { log: false })
          .its('0.contentDocument.body')
          .then(($body) => {
            const listing = Cypress.$($body);
            if (listing.find('#cfSidePanel.open').length > 0) {
              return false;
            }
            const frame = listing.find('#cfSidePanelFrame')[0] as
              | HTMLIFrameElement
              | undefined;

            return !frame?.src || frame.src.endsWith('about:blank');
          }),
      {
        errorMsg: 'the side panel never released its iframe',
        interval: 100,
        timeout: 20_000
      }
    );

    // Queries only, and one of them: a bulk action submits the legacy form, so
    // #main-content navigates and getIframeBody() — built on waitUntil + .then()
    // — can hand back the body of the document from before that reload. .its()
    // is a query, so Cypress re-evaluates it and lands on the new document.
    cy.get('iframe#main-content', { log: false })
      .its('0.contentDocument.body', { timeout: 20_000 })
      .find(`#clTableBody a:contains("${name}")`, { timeout: 20_000 })
      .first()
      .click();

    // Same reason for the panel: it replaces its document while the form loads,
    // so its body has to be reached through queries too. The subject is then a
    // raw body element — .find() accepts it, .contains() does not.
    return cy
      .get('iframe#main-content', { log: false })
      .its('0.contentDocument.body', { timeout: 20_000 })
      .find('#cfSidePanelFrame')
      .its('0.contentDocument.body', { timeout: 20_000 })
      .should('not.be.empty');
  }
);

// The framework clones the toolbar Add button into the empty state, so on an
// empty listing .cl-btn-add matches two elements.
Cypress.Commands.add('clickListingAddButton', (): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', '.cl-actions-left .cl-btn-add');

  return cy.getIframeBody().find('.cl-actions-left .cl-btn-add').click();
});

// Select fields are select2 widgets: the real <select> is hidden and the visible
// control is its sibling .select2-selection. Driving them through the search
// input's placeholder only works while select2 renders one, which it does not do
// for every field in the side-panel form.
Cypress.Commands.add(
  'openFormSelect2',
  (selectId: string): Cypress.Chainable => {
    return cy
      .getFormBody()
      .find(`select#${selectId}`)
      .next()
      .find('.select2-selection')
      .click({ force: true });
  }
);

Cypress.Commands.add(
  'selectFormOption',
  (selectId: string, option: string): Cypress.Chainable => {
    cy.openFormSelect2(selectId);

    cy.getFormBody()
      .find('.select2-results__option', { timeout: 20_000 })
      .contains(option)
      .click({ force: true });

    // A single select closes on pick, a multiple one keeps its dropdown open and
    // the results list then covers whatever sits below the field. Escape has to
    // reach select2's own search field: the widget listens there, not on the body.
    cy.getFormBody().then(($body) => {
      if ($body.find('.select2-container--open').length === 0) {
        return;
      }

      cy.getFormBody()
        .find('.select2-search__field')
        .first()
        .type('{esc}', { force: true });
    });

    return cy
      .getFormBody()
      .find('.select2-container--open')
      .should('not.exist');
  }
);

// A yes/no field comes in three shapes in the migrated forms: the segmented
// buttons initYesNoSegments() generates over a small radio group, a cl-toggle
// wired by CentreonForm.syncToggle, or the plain radios of a group the
// conversion skipped. Only the first exposes the field name and the value as
// attributes; for the other two, setting the radio is what the form reads back.
Cypress.Commands.add(
  'selectFormSegment',
  (radioName: string, value: string): Cypress.Chainable => {
    return cy.getFormBody().then(($body) => {
      const segment = $body.find(
        `.cf-segmented[data-radio-name="${radioName}"] button[data-value="${value}"]`
      );

      if (segment.length > 0) {
        return cy.wrap(segment).click({ force: true });
      }

      return cy
        .getFormBody()
        .find(`input[name*="${radioName}"][value="${value}"]`, {
          timeout: 20_000
        })
        .check({ force: true });
    });
  }
);

Cypress.Commands.add('fillFieldInIframe', (body: HtmlElt) => {
  cy.getFormBody()
    .find(`${body.tag}[${body.attribut}="${body.attributValue}"]`)
    .clear()
    .type(body.valueOrIndex);
});

Cypress.Commands.add('clickOnFieldInIframe', (body: HtmlElt) => {
  cy.getFormBody()
    .find(`${body.tag}[${body.attribut}="${body.attributValue}"]`)
    .eq(Number(body.valueOrIndex))
    .click();
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
      getFormBody: () => Cypress.Chainable;
      getListingBody: () => Cypress.Chainable;
      waitForListingToDrop: (text: string) => Cypress.Chainable;
      waitForListingToShow: (text: string) => Cypress.Chainable;
      openFormSelect2: (selectId: string) => Cypress.Chainable;
      selectFormOption: (selectId: string, option: string) => Cypress.Chainable;
      selectFormSegment: (
        radioName: string,
        value: string
      ) => Cypress.Chainable;
      visitListingAndWait: (page: string) => Cypress.Chainable;
      waitForListingRefresh: () => Cypress.Chainable;
      getListingSidePanelBody: () => Cypress.Chainable;
      openListingRowForm: (name: string) => Cypress.Chainable;
      clickListingAddButton: () => Cypress.Chainable;
      fillFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
      clickOnFieldInIframe: (body: HtmlElt) => Cypress.Chainable;
    }
  }
}
