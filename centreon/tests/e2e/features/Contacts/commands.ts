import { PAGES } from 'fixtures/shared/constants/pages';

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
    cy.contains('label', body.isNotificationsEnabled).click();
  });
});

Cypress.Commands.add('addOrUpdateContactGroup', (body: ContactGroup) => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody().find('input[name="cg_name"]').clear().type(body.name);
  cy.getSidePanelBody().find('input[name="cg_alias"]').clear().type(body.alias);

  cy.getSidePanelBody()
    .find('input[class="select2-search__field"]')
    .eq(0)
    .click();
  cy.wait('@getContacts');
  cy.getSidePanelBody().contains('div', body.linkedContact).click();

  cy.getSidePanelBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .click();
  cy.wait('@getACLGroups');
  cy.getSidePanelBody().contains('div', 'ALL').click();

  cy.getSidePanelBody().contains(body.status).click();

  cy.getSidePanelBody()
    .find('textarea[name="cg_comment"]')
    .clear()
    .type(body.comment);

  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(1)
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
  cy.getSidePanelBody().contains('label', body.isNotEnabled).click();
  cy.getSidePanelBody().find('label[for="hDown"]').click();
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .click();
  cy.wait('@getTimePeriods');
  cy.getSidePanelBody().find(`div[title="${body.timePeriod}"]`).click();
  cy.getSidePanelBody()
    .find('input[class="select2-search__field"]')
    .eq(0)
    .click();
  cy.wait('@getNotCommands');
  cy.getSidePanelBody().find(`div[title="${body.notCommands}"]`).click();
  cy.getSidePanelBody().find('label[for="sWarning"]').click();
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id2-container"]')
    .click();
  cy.wait('@getTimePeriods');
  cy.getSidePanelBody().find(`div[title="${body.timePeriod}"]`).click();
  cy.getSidePanelBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .click();
  cy.wait('@getNotCommands');
  cy.getSidePanelBody().find(`div[title="${body.notCommands}"]`).click();
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(1)
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
