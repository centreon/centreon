import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/acls/acl-data.json';

const duplicatedACLMenu = {
  name: `${data.ACLMenu.name}_1`
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

Given('I am logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('three ACL access groups have been created', () => {
  cy.addACLGroup({ name: data.ACLGroups.ACLGroup1.name });
  cy.addACLGroup({ name: data.ACLGroups.ACLGroup2.name });
  cy.addACLGroup({ name: data.ACLGroups.ACLGroup3.name });
});

When('I add a new menu access linked with two groups', () => {
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();

  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('input[name="acl_topo_name"]')
    .type(data.ACLMenu.name);
  cy.getIframeBody()
    .find('input[name="acl_topo_alias"]')
    .type(data.ACLMenu.alias);
  cy.getIframeBody()
    .find('select[name="acl_groups-f[]"]')
    .select(data.ACLGroups.ACLGroup1.name);
  cy.getIframeBody().find('input[name="add"]').click();
  cy.getIframeBody()
    .find('select[name="acl_groups-f[]"]')
    .select(data.ACLGroups.ACLGroup2.name);
  cy.getIframeBody().find('input[name="add"]').click();

  // Add Home Rule
  cy.getIframeBody().find('input[name="acl_r_topos[1]"]').parent().click();
  cy.getIframeBody()
    .find('textarea[name="acl_comments"]')
    .type(data.ACLMenu.comment);

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
});

Then('the menu access is saved with its properties', () => {
  cy.wait('@getTimeZone');
  cy.getIframeBody().should('contain', data.ACLMenu.name);
});

Then(
  'only chosen linked access groups display the new menu access in Authorized information tab',
  () => {
    Object.entries(data.ACLGroups).forEach((ACLGroup) => {
      cy.navigateTo({
        page: 'Access Groups',
        rootItemNumber: 4,
        subMenu: 'ACL'
      });
      cy.wait('@getTimeZone');

      cy.getIframeBody()
        .contains('td.ListColLeft > a', ACLGroup[1].name)
        .click();

      cy.wait('@getTimeZone');
      cy.getIframeBody().contains('a', 'Authorizations information').click();

      cy.getIframeBody()
        .find('select[name="menuAccess-t[]"]')
        .should(
          ACLGroup[1].name !== data.ACLGroups.ACLGroup3.name
            ? 'contain'
            : 'not.contain',
          data.ACLMenu.name
        );
    });
  }
);

Given('one existing ACL Menu access linked with two access groups', () => {
  cy.addACLMenu({ name: data.ACLMenu.name, rule: ['Home'] });
  cy.addACLMenuToACLGroup({
    ACLGroupName: data.ACLGroups.ACLGroup1.name,
    ACLMenuName: data.ACLMenu.name
  });
  cy.addACLMenuToACLGroup({
    ACLGroupName: data.ACLGroups.ACLGroup2.name,
    ACLMenuName: data.ACLMenu.name
  });
});

When('I remove one access group', () => {
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', data.ACLMenu.name).click();

  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('select[name="acl_groups-t[]"]')
    .select(data.ACLGroups.ACLGroup2.name);
  cy.getIframeBody().find('input[name="remove"]').click();

  cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
});

Then('link between access group and Menu access must be broken', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });

  cy.wait('@getTimeZone').then(() => {
    cy.executeActionOnIframe(
      data.ACLGroups.ACLGroup2.name,
      ($body) => {
        cy.wrap($body)
          .contains('td.ListColLeft > a', data.ACLGroups.ACLGroup2.name)
          .click();
      },
      3,
      3000
    );
  });

  cy.wait('@getTimeZone').then(() => {
    cy.executeActionOnIframe(
      'Authorizations information',
      ($body) => {
        cy.wrap($body).contains('a', 'Authorizations information').click();
      },
      3,
      3000
    );
  });

  cy.getIframeBody()
    .find('select[name="menuAccess-t[]"]')
    .should('not.contain', data.ACLMenu.name);
});

Given('one existing Menu access', () => {
  cy.addACLMenu({ name: data.ACLMenu.name, rule: ['Home'] });
  cy.addACLMenuToACLGroup({
    ACLGroupName: data.ACLGroups.ACLGroup1.name,
    ACLMenuName: data.ACLMenu.name
  });
});

When('I duplicate the Menu access', () => {
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', data.ACLMenu.name)
    .within(() => {
      cy.get('td.ListColPicker').click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      if (!win) {
        throw new Error('Cannot get iframe');
      }

      cy.stub(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then(
  'a new Menu access is created with identical properties except the name',
  () => {
    cy.wait('@getTimeZone');

    const originalACLMenuValues: Array<string> = [];
    cy.getIframeBody()
      .contains('tr', data.ACLMenu.name)
      .within(() => {
        cy.get('td').each((td, index) => {
          if (index >= 1 && index <= 5)
            originalACLMenuValues.push(td.text().trim());
        });
      });

    const duplicatedACLMenuValues: Array<string> = [];
    cy.getIframeBody()
      .contains('tr', duplicatedACLMenu.name)
      .within(() => {
        cy.get('td').each((td, index) => {
          if (index >= 1 && index <= 5)
            duplicatedACLMenuValues.push(td.text().trim());
        });
      });

    cy.wrap(duplicatedACLMenuValues).then((duplicatedValues) => {
      expect(duplicatedValues[0]).to.not.equal(originalACLMenuValues[0]);
      for (let i = 1; i < originalACLMenuValues.length; i += 1) {
        expect(duplicatedValues[i]).to.equal(originalACLMenuValues[i]);
      }
    });
  }
);

Given('one existing enabled Menu access', () => {
  cy.addACLMenu({ name: data.ACLMenu.name, rule: ['Home'] });
  cy.addACLMenuToACLGroup({
    ACLGroupName: data.ACLGroups.ACLGroup1.name,
    ACLMenuName: data.ACLMenu.name
  });
});

When('I disable it', () => {
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains(data.ACLMenu.name).click();

  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_topo_activate[acl_topo_activate]"][value="0"]')
    .parent()
    .click();

  cy.getIframeBody().find('input[name="submitC"]').eq(1).click();
});

Then('its status is modified', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', data.ACLMenu.name)
    .should('contain', 'Disabled');
});

When('I delete the Menu access', () => {
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', data.ACLMenu.name)
    .within(() => {
      cy.get('td.ListColPicker').click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      if (!win) {
        throw new Error('Cannot get iframe');
      }

      cy.stub(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then(
  'the menu access record is not visible anymore in Menus Access page',
  () => {
    cy.wait('@getTimeZone');

    cy.getIframeBody().should('not.contain', data.ACLMenu.name);
  }
);

Then('the link with access groups is broken', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('td.ListColLeft > a', data.ACLGroups.ACLGroup1.name)
    .click();

  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Authorizations information').click();

  cy.getIframeBody()
    .find('select[name="menuAccess-t[]"]')
    .should('not.contain', data.ACLMenu.name);
});
