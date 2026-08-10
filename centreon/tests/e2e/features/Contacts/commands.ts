import { PAGES } from 'fixtures/shared/constants/pages';

import { setOptionChip, setSegmentedChoice } from './common';

interface Contact {
  alias: string;
  name: string;
  email: string;
  pager: string;
  template: string;
  isNotificationsEnabled: string;
}

interface ContactGroup {
  name: string;
  alias: string;
  linkedContact: string;
  status: string;
  comment: string;
}

interface ContactTemplate {
  alias: string;
  name: string;
  usedContactTemplate: string;
  defaultPage: string;
  isNotEnabled: string;
  timePeriod: string;
  notCommands: string;
}

/**
 * Open a multi-select by its selection container: the redesigned dropdown adds
 * a "Select all" header that renders over the tiny inline search input.
 */
const openMultiSelect = (index: number): void => {
  cy.getSidePanelBody().find('.select2-selection--multiple').eq(index).click();
};

/**
 * The dropdown stays open after a pick — deliberate for a multi-select — and
 * its header then overlays the next widget. Escape does not dismiss it; a click
 * outside does. The tab bar is the one neutral target common to the four forms
 * (a section header would collapse its section).
 */
const closeMultiSelect = (): void => {
  cy.getSidePanelBody().find('.cf-tab-nav').click();
};

/**
 * Activation is driven by the cosmetic cl-toggle now; the QuickForm radio group
 * it mirrors is hidden, so its labels can no longer be clicked. The real input
 * sits behind the slider, hence the forced click.
 */
const setActivation = (toggleId: string, enabled: boolean): void => {
  cy.getSidePanelBody()
    .find(`#${toggleId}`)
    .then(($toggle) => {
      if ($toggle.prop('checked') !== enabled) {
        cy.wrap($toggle).click({ force: true });
      }
    });
};

Cypress.Commands.add('addOrUpdateContact', (body: Contact) => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody().within(() => {
    cy.get('input[id="contact_alias"]').type(
      `{selectAll}{backspace}${body.alias}`
    );
    cy.get('input[id="contact_name"]').type(
      `{selectAll}{backspace}${body.name}`
    );
    cy.get('input[id="contact_email"]').type(
      `{selectAll}{backspace}${body.email}`
    );
    cy.get('input[id="contact_pager"]').type(
      `{selectAll}{backspace}${body.pager}`
    );
    cy.get('#contact_template_id').select(body.template);
  });
  setSegmentedChoice(
    'contact_enable_notifications',
    body.isNotificationsEnabled
  );
});

Cypress.Commands.add('addOrUpdateContactGroup', (body: ContactGroup) => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody().find('input[name="cg_name"]').clear().type(body.name);
  cy.getSidePanelBody().find('input[name="cg_alias"]').clear().type(body.alias);

  openMultiSelect(0);
  cy.wait('@getContacts');
  cy.getSidePanelBody().contains('div', body.linkedContact).click();
  closeMultiSelect();

  openMultiSelect(1);
  cy.wait('@getACLGroups');
  cy.getSidePanelBody().contains('div', 'ALL').click();
  closeMultiSelect();

  setActivation('cf-cg-activate-toggle', body.status === 'Enabled');

  cy.getSidePanelBody()
    .find('textarea[name="cg_comment"]')
    .clear()
    .type(body.comment);

  // The modernized form renders a single submit (submitA on create, submitC on
  // modify), where the legacy one rendered a pair.
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add('addOrUpdateContactTemplate', (body: ContactTemplate) => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody()
    .find('input[name="contact_alias"]')
    .clear()
    .type(body.alias);
  cy.getSidePanelBody()
    .find('input[name="contact_name"]')
    .clear()
    .type(body.name);
  cy.getSidePanelBody()
    .find('select[name="contact_template_id"]')
    .select(body.usedContactTemplate);
  cy.getSidePanelBody()
    .find('select[name="default_page"]')
    .select(body.defaultPage);
  setSegmentedChoice('contact_enable_notifications', body.isNotEnabled);
  setOptionChip('Host Notification Options', 'Down');
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .click();
  cy.wait('@getTimePeriods');
  cy.getSidePanelBody().find(`div[title="${body.timePeriod}"]`).click();
  openMultiSelect(0);
  cy.wait('@getNotCommands');
  cy.getSidePanelBody().find(`div[title="${body.notCommands}"]`).click();
  closeMultiSelect();
  setOptionChip('Service Notification Options', 'Warning');
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id2-container"]')
    .click();
  cy.wait('@getTimePeriods');
  cy.getSidePanelBody().find(`div[title="${body.timePeriod}"]`).click();
  openMultiSelect(1);
  cy.wait('@getNotCommands');
  cy.getSidePanelBody().find(`div[title="${body.notCommands}"]`).click();
  closeMultiSelect();
  // The modernized form renders a single submit (submitA on create, submitC on
  // modify), where the legacy one rendered a pair.
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add(
  'loginByDuplicatedOrUpdatedUser',
  (jsonName: string, login: string) => {
    cy.visit(`${Cypress.config().baseUrl}`)
      .fixture(`users/${jsonName}.json`)
      .then((credential) => {
        cy.getByLabel({ label: 'Alias', tag: 'input' }).type(
          `{selectAll}{backspace}${login}`
        );
        cy.getByLabel({ label: 'Password', tag: 'input' }).type(
          `{selectAll}{backspace}${credential.password}`
        );
      })
      .getByLabel({ label: 'Connect', tag: 'button' })
      .click();

    return cy.get('.MuiAlert-message').then((snackbar) => {
      if (snackbar.text().includes('Login succeeded')) {
        cy.wait('@getNavigationList');
        cy.get('.MuiAlert-message').should('not.be.visible');
      }
    });
  }
);

Cypress.Commands.add('visitContactsPage', () => {
  cy.visit(PAGES.configuration.contactsUsersLegacy);
  cy.wait('@getTimeZone');
});

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      addOrUpdateContact: (body: Contact) => Cypress.Chainable;
      addOrUpdateContactGroup: (body: ContactGroup) => Cypress.Chainable;
      addOrUpdateContactTemplate: (body: ContactTemplate) => Cypress.Chainable;
      loginByDuplicatedOrUpdatedUser: (
        jsonName: string,
        login: string
      ) => Cypress.Chainable;
      visitContactsPage: () => Cypress.Chainable;
    }
  }
}
