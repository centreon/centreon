import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import data from '../../../fixtures/acls/acl-access-groups.json';
import '../commands';

const originalACLGroup = {
  name: 'ACL_group',
  alias: 'ACL group',
  linkedContactGroups: data.contactGroups.contactGroup1.name
};

const modifiedACLGroup = {
  name: 'ACL_group_modified',
  alias: 'ACL group modified'
};

const duplicatedACLGroup = {
  name: originalACLGroup.name + '_1'
};

const ACLMenu = {
  name: 'ACL_Menu'
};

beforeEach(() => {
  cy.startWebContainer();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topcounter&action=user'
  }).as('getTopCounter');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept(
    'HEAD',
    'https://cdn.eu.pendo.io/agent/static/b06b875d-4a10-4365-7edf-8efeaf53dfdd/pendo.js'
  ).as('pendoRequest');
});

afterEach(() => {
  cy.stopWebContainer();
});

Given('I am logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When('one contact group exists including two non admin contacts', () => {
  cy.addContact({
    admin: data.contacts.contact1.admin,
    email: data.contacts.contact1.email,
    name: data.contacts.contact1.name,
    password: data.contacts.contact1.password
  });
  cy.addContact({
    admin: data.contacts.contact2.admin,
    email: data.contacts.contact2.email,
    name: data.contacts.contact2.name,
    password: data.contacts.contact2.password
  });
  cy.addContactGroup({
    contacts: [data.contacts.contact1.name, data.contacts.contact2.name],
    name: data.contactGroups.contactGroup1.name
  });
});

When('the access group is saved with its properties', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();

  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('input[name="acl_group_name"]')
    .click()
    .type(originalACLGroup.name);
  cy.getIframeBody()
    .find('input[name="acl_group_alias"]')
    .click()
    .type(originalACLGroup.alias);
  cy.getIframeBody()
    .find('select[name="cg_contactGroups-f[]"]')
    .select(originalACLGroup.linkedContactGroups);

  cy.getIframeBody().find('input[name="add"]').eq(1).click();

  cy.getIframeBody()
    .find('select[name="cg_contactGroups-t[]"]')
    .should('contain', originalACLGroup.linkedContactGroups);

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
});

When('a menu access is linked with this group', () => {
  cy.addACLMenu({ name: ACLMenu.name, rule: ['Home'] });
  cy.addACLMenuToACLGroup({
    ACLGroupName: originalACLGroup.name,
    ACLMenuName: ACLMenu.name
  });
});

Then(
  'all linked users have the access list group displayed in Centreon authentication tab',
  () => {
    cy.logout();
    cy.loginByCredentials({
      login: data.contacts.contact1.name,
      password: data.contacts.contact1.password
    });

    cy.wait('@getTopCounter');
    cy.getByTestId({ testId: 'HomeIcon' }).should('exist');

    cy.logout();
    cy.loginByCredentials({
      login: data.contacts.contact2.name,
      password: data.contacts.contact2.password
    });

    cy.wait('@getTopCounter');
    cy.getByTestId({ testId: 'HomeIcon' }).should('exist');
  }
);

When('I add a new access group with linked contact group', () => {
  cy.addContact({
    admin: data.contacts.contact1.admin,
    email: data.contacts.contact1.email,
    name: data.contacts.contact1.name,
    password: data.contacts.contact1.password
  });
  cy.addContact({
    admin: data.contacts.contact2.admin,
    email: data.contacts.contact2.email,
    name: data.contacts.contact2.name,
    password: data.contacts.contact2.password
  });
  cy.addContactGroup({
    contacts: [data.contacts.contact1.name, data.contacts.contact2.name],
    name: data.contactGroups.contactGroup1.name
  });
});

Then(
  'the contact group has the access group displayed in Relations informations',
  () => {
    cy.navigateTo({
      page: 'Contact Groups',
      rootItemNumber: 3,
      subMenu: 'Users'
    });

    cy.wait(['@getTimeZone', '@pendoRequest']);
    cy.waitUntil(
      () => {
        return cy.get('iframe#main-content').should('be.visible');
      },
      {
        errorMsg: 'The iframe#main-content element is not visible',
        interval: 3000,
        timeout: 20000
      }
    ).then(() => {
      cy.get('iframe#main-content').should('be.visible').as('iframe');

      cy.get('@iframe')
        .its('0.contentDocument.body')
        .within((iframe) => {
          cy.wrap(iframe)
            .contains(data.contactGroups.contactGroup1.name, {
              timeout: 15000
            })
            .click();
        });
    });

    cy.wait(['@getTimeZone', '@pendoRequest']);
    cy.waitUntil(
      () => {
        return cy.get('iframe#main-content').should('be.visible');
      },
      {
        errorMsg: 'The iframe#main-content element is not visible',
        interval: 3000,
        timeout: 20000
      }
    ).then(() => {
      cy.get('iframe#main-content').should('be.visible').as('iframe');

      cy.get('@iframe')
        .its('0.contentDocument.body')
        .within((iframe) => {
          cy.wrap(iframe)
            .find('select[name="cg_acl_groups[]"]', { timeout: 15000 })
            .should('contain', originalACLGroup.name);
        });
    });
  }
);

Given('one existing ACL access group', () => {
  cy.addACLGroup({ name: originalACLGroup.name });
});

When('I modify its properties', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains(originalACLGroup.name).click();

  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_group_name"]')
    .click()
    .type(modifiedACLGroup.name);
  cy.getIframeBody()
    .find('input[name="acl_group_alias"]')
    .click()
    .type(modifiedACLGroup.alias);

  cy.getIframeBody().find('input[name="submitC"]').eq(1).click();
});

Then('all modified properties are updated', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody().should('contain', modifiedACLGroup.name);
  cy.getIframeBody().should('contain', modifiedACLGroup.alias);
});

When('I duplicate the access group', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', originalACLGroup.name)
    .within(() => {
      cy.get('td.ListColPicker').click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      cy.stub<any>(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then('a new access group appears with similar properties', () => {
  cy.wait('@getTimeZone');

  const originalACLGroupValues: string[] = [];
  cy.getIframeBody()
    .contains('tr', originalACLGroup.name)
    .within(() => {
      cy.get('td').each((td, index) => {
        if (2 <= index && index <= 5)
          originalACLGroupValues.push(td.text().trim());
      });
    });

  const duplicatedACLGroupValues: string[] = [];
  cy.getIframeBody()
    .contains('tr', duplicatedACLGroup.name)
    .within(() => {
      cy.get('td').each((td, index) => {
        if (2 <= index && index <= 5)
          duplicatedACLGroupValues.push(td.text().trim());
      });
    });

  cy.wrap(duplicatedACLGroupValues).then((duplicatedValues) => {
    for (let i = 0; i < originalACLGroupValues.length; i++) {
      expect(duplicatedValues[i]).to.equal(originalACLGroupValues[i]);
    }
  });
});

When('I delete the access group', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', originalACLGroup.name)
    .within(() => {
      cy.get('td.ListColPicker').click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      cy.stub<any>(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then('it does not exist anymore', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody().should('not.contain', originalACLGroup.name);
});

Given('one existing enabled ACL access group', () => {
  cy.addACLGroup({ name: originalACLGroup.name });
});

When('I disable it', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains(originalACLGroup.name).click();

  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_group_activate[acl_group_activate]"][value="0"]')
    .parent()
    .click();

  cy.getIframeBody().find('input[name="submitC"]').eq(1).click();
});

Then('its status is modified', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', originalACLGroup.name)
    .should('contain', 'Disabled');
});
