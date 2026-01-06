import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import contacts from '../../../fixtures/users/contact.json';

let isAdmin = true;
let contactPageIndex = 3;
let accessGroup = 'user-ACLGROUP';
const checkContactFromListing = () => {
  cy.visitContactsPage(contactPageIndex);
  const index = isAdmin ? 3 : 1;
  cy.getIframeBody()
    .find('div.md-checkbox.md-checkbox-inline')
    .eq(index)
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
};

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.setUserTokenApiV1()
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/contacts-management-acl-user.json'
    ).executeCommandsViaClapi(
      'resources/clapi/config-ACL/contacts-management-acl-user-readonly-rights.json'
    );
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup&action=list*'
  }).as('getAclGroups');
});

afterEach(() => {
  cy.setUserTokenApiV1()
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/delete-contacts-management-acl-user-readonly-rights.json'
    ).executeCommandsViaClapi(
      'resources/clapi/config-ACL/delete-contacts-management-acl-user.json'
    );
  for (const contact of [contacts.default, contacts.contactForUpdate]) {
    for (const contactAlias of [contact.alias, `${contact.alias}_1`, `${contact.alias}-1`]) {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'DEL',
          object: 'CONTACT',
          values: contactAlias
        },
        failOnError: false
      });
    }
  }
  cy.logoutViaAPI({ failOnError: false });
});

after(() => {
  cy.stopContainers();
});

Given('a {string} user is logged in a Centreon server', (user: string) => {
  contactPageIndex = user === 'admin' ? 3 : 0;
  isAdmin = user === 'admin';
  cy.loginByTypeOfUser({
    jsonName: user === 'admin' ? 'admin' : 'contacts-management-acl-user',
    loginViaApi: false
  });
});

When('a contact is configured', () => {
  cy.visitContactsPage(contactPageIndex);
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateContact(contacts.default);
  if (!isAdmin) {
    // Add the contact to the ACL Group of the connected non-admin user
    cy.getIframeBody().contains('a', 'Centreon Authentication').click();
    // Click outside the form
    cy.get('body').click(0, 0);
    cy.getIframeBody()
      .find('ul[class="select2-selection__rendered"]')
      .eq(3)
      .click();
    cy.wait('@getAclGroups');
    cy.getIframeBody().contains('div', accessGroup).click();
  }
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the user updates some contact properties', () => {
  cy.getIframeBody().contains(contacts.default.alias).click();
  cy.addOrUpdateContact(contacts.contactForUpdate);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('these properties are updated', () => {
  cy.getIframeBody().contains(contacts.contactForUpdate.alias).should('exist');
  cy.getIframeBody().contains(contacts.contactForUpdate.alias).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[id="contact_alias"]');
  cy.getIframeBody()
    .find('input[id="contact_alias"]')
    .should('have.value', contacts.contactForUpdate.alias);
  cy.getIframeBody()
    .find('input[id="contact_name"]')
    .should('have.value', contacts.contactForUpdate.name);
  cy.getIframeBody()
    .find('input[id="contact_email"]')
    .should('have.value', contacts.contactForUpdate.email);
  cy.getIframeBody()
    .find('input[id="contact_pager"]')
    .should('have.value', contacts.contactForUpdate.pager);
  cy.getIframeBody().find('#contact_template_id').should('have.value', '19');
  cy.checkLegacyRadioButton(contacts.contactForUpdate.isNotificationsEnabled);
});

When('the user duplicates the configured contact', () => {
  checkContactFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact is created with identical properties', () => {
  cy.getIframeBody().contains(`${contacts.default.alias}_1`).should('exist');
  cy.getIframeBody().contains(`${contacts.default.alias}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="contact_alias"]');

  cy.getIframeBody()
    .find('input[name="contact_alias"]')
    .should('have.value', `${contacts.default.alias}_1`);
  cy.getIframeBody()
    .find('input[name="contact_name"]')
    .should('have.value', `${contacts.default.name}_1`);
  cy.getIframeBody()
    .find('input[name="contact_email"]')
    .should('have.value', contacts.default.email);
  cy.getIframeBody()
    .find('input[name="contact_pager"]')
    .should('have.value', contacts.default.pager);
  cy.getIframeBody().find('#contact_template_id').should('have.value', '19');
  cy.checkLegacyRadioButton(contacts.default.isNotificationsEnabled);
});

When('the user deletes the configured contact', () => {
  checkContactFromListing();
  cy.getIframeBody().find('select[name="o1"').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted contact is not visible anymore on the contact page', () => {
  cy.getIframeBody().contains(contacts.default.name).should('not.exist');
});

Given('the contact configuration page is displayed', () => {
  cy.visitContactsPage(contactPageIndex);
});

When('the user clicks on the contact creation button', () => {
  cy.getIframeBody().contains('a', 'Add').click();
});

When('he does not fill in the {string} field', (field: string) => {
  cy.waitForElementInIframe('#main-content', 'input[id="contact_alias"]');
  // Fill All the required fields first
  cy.getIframeBody().within(() => {
    cy.find('input[id="contact_alias"]').type(`{selectAll}{backspace}${contacts.default.alias}`);
    cy.find('input[id="contact_name"]').type(`{selectAll}{backspace}${contacts.default.name}`);
    cy.find('input[id="contact_email"]').type(`{selectAll}{backspace}${contacts.default.email}`);
  });

  // Remove the content of one of the required field that we have already filled
  switch (field) {
    case 'Alias':
      cy.getIframeBody().find('input[id="contact_alias"]').clear();
      break;
    case 'Full Name':
      cy.getIframeBody().find('input[id="contact_name"]').clear();
      break;
    case 'Email':
      cy.getIframeBody().find('input[id="contact_email"]').clear();
      break;
    default:
      throw new Error(`Unknown field: ${field}`);
  }
  // Click to save the contact
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then('the user is not brought back to the contact configuration page', () => {
  // Check that the add form is still open.
  cy.getIframeBody().contains('a', 'General Information').should('be.visible');
});

Then(
  'he sees an error displayed above the {string} field with a message {string}',
  (_field: string, message: string) => {
    cy.waitForElementInIframe('#main-content', `font:contains("${message}")`);
  }
);

Then('the contact is not created', () => {
  // Return to the contacts listing page
  cy.getIframeBody().contains('a', 'Contacts / Users').click();
  cy.wait('@getTimeZone');
  // Check that the contact is not present in the listing
  cy.getIframeBody().contains('a', contacts.default.name).should('not.exist');
});

When('the {string} user clicks on a this contact', () => {
  cy.getIframeBody().contains(contacts.default.alias).click();
});

When('the {string} clears the contents of a mandatory field', () => {
  cy.waitForElementInIframe('#main-content', 'input[id="contact_alias"]');
  cy.getIframeBody().find('input[id="contact_alias"]').clear();
  cy.getIframeBody().find('input[id="contact_name"]').clear();
  cy.getIframeBody().find('input[id="contact_email"]').clear();
  // Click to save the changes
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then('the {string} sees an error displayed in the form', () => {
  cy.waitForElementInIframe(
    '#main-content',
    'font:contains("Compulsory Alias")'
  );
  cy.getIframeBody().contains('Compulsory Name').should('be.visible');
  cy.getIframeBody().contains('Valid Email').should('be.visible');
});

Then('the contact is not updated', () => {
  // Return to the contacts listing page
  cy.getIframeBody().contains('a', 'Contacts / Users').click();
  cy.wait('@getTimeZone');
  // Check that the contact alias is not updated
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${contacts.default.alias}")`
  );
  cy.getIframeBody().contains('a', contacts.default.alias).should('be.visible');
});

Given(
  'a non-admin user with READ ONLY rights is configured by the admin',
  () => {
    cy.loginByTypeOfUser({
      jsonName: 'admin',
      loginViaApi: false
    });
    contactPageIndex = 3;
    isAdmin = false;
    accessGroup = 'user-ACLGROUP-READ';
    // The configuration of the non-admin user with READ ONLY rights is already done on the beforeEach step
  }
);

When(
  'the non-admin user with READ ONLY rights is logged in a Centreon Server',
  () => {
    // Logout from the admin account
    cy.logout();
    //Log in as a non-admin user with READ ONLY rights
    cy.loginByTypeOfUser({
      jsonName: 'contacts-management-acl-user-readonly-rights',
      loginViaApi: false
    });
    contactPageIndex = 0;
  }
);

When(
  'the non-admin user with READ ONLY rights displays contacts configuration',
  () => {
    cy.visitContactsPage(contactPageIndex);
    // Check that the page is on READ ONLY mod
    cy.getIframeBody().contains('a', 'Add').should('not.exist');
  }
);

When(
  'the non-admin user with READ ONLY rights clicks on the configured contact',
  () => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${contacts.default.alias}")`
    );
    cy.getIframeBody().contains('a', contacts.default.alias).click();
    // Wait until the form is visible
    cy.waitForElementInIframe(
      '#main-content',
      'a:contains("General Information")'
    );
  }
);

Then('the form of this contact is displayed in READ ONLY mode', () => {
  cy.getIframeBody()
    .find('#tab1 input:not([class*="select"])')
    .each(($input) => {
      cy.wrap($input).should('have.attr', 'type', 'hidden');
    });
});
