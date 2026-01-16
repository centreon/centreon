import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { PAGES } from 'fixtures/shared/constants/pages';
import data from '../../../fixtures/acls/acl-data.json';

const aclResource = {
  ...data.ACLResource,
  aclGroups: [data.ACLGroups.ACLGroup1.name, data.ACLGroups.ACLGroup2.name]
};

const duplicatedAclResource = {
  name: `${aclResource.name}_1`
};

const modifiedAclResource = {
  comment: `${aclResource.comment}_modified`,
  description: `${aclResource.description}_modified`,
  name: `${aclResource.name}_modified`
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

Given('three ACL access groups including non admin users exist', () => {
  cy.addContact({
    admin: data.contacts.contact1.admin,
    email: data.contacts.contact1.email,
    name: data.contacts.contact1.name,
    password: data.contacts.contact1.password
  });
  cy.addACLGroup({
    contacts: [data.contacts.contact1.name],
    name: data.ACLGroups.ACLGroup1.name
  });
  cy.addACLGroup({
    contacts: [data.contacts.contact1.name],
    name: data.ACLGroups.ACLGroup2.name
  });
  cy.addACLGroup({
    contacts: [data.contacts.contact1.name],
    name: data.ACLGroups.ACLGroup3.name
  });
});

When('I add a new Resources access linked with two groups', () => {
  cy.visit(PAGES.configuration.aclResourcesAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');

  cy.getIframeBody().find('input[name="acl_res_name"]').type(aclResource.name);
  cy.getIframeBody()
    .find('input[name="acl_res_alias"]')
    .type(aclResource.description);

  aclResource.aclGroups.forEach((aclGroup) => {
    cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(aclGroup);
    cy.getIframeBody().find('input[name="add"]').eq(0).click();
  });

  cy.getIframeBody()
    .find('textarea[name="acl_res_comment"]')
    .type(aclResource.comment);

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
});

Then('the Resources access is saved with its properties', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', aclResource.name).click();
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_res_name"]')
    .should('have.value', aclResource.name);
  cy.getIframeBody()
    .find('input[name="acl_res_alias"]')
    .should('have.value', aclResource.description);

  aclResource.aclGroups.forEach((aclGroup) => {
    cy.getIframeBody()
      .find('select[name="acl_groups-t[]"]')
      .should('contain', aclGroup);
  });

  cy.getIframeBody()
    .find('textarea[name="acl_res_comment"]')
    .should('have.value', aclResource.comment);
});

Then(
  'only chosen linked access groups display the new Resources access in Authorized information tab',
  () => {
    Object.entries(data.ACLGroups).forEach((aclGroup) => {
      cy.visit(PAGES.configuration.aclAccessGroupsLegacy);
      cy.wait('@getTimeZone');
      cy.waitForElementInIframe(
        '#main-content',
        `td.ListColLeft > a:contains("${aclGroup[1].name}")`
      );
      cy.getIframeBody()
        .contains('td.ListColLeft > a', aclGroup[1].name)
        .click();

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
        .find('select[name="resourceAccess-t[]"]')
        .should(
          aclResource.aclGroups.includes(aclGroup[1].name)
            ? 'contain'
            : 'not.contain',
          aclResource.name
        );
    });
  }
);

Given('one existing Resources access linked with two access groups', () => {
  cy.addACLResource({ name: aclResource.name });

  aclResource.aclGroups.forEach((aclGroup) => {
    cy.addAclResourceToAclGroup({
      aclGroupName: aclGroup,
      aclResourceName: aclResource.name
    });
  });
});

When('I remove one access group', () => {
  cy.visit(PAGES.configuration.aclResourcesAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', aclResource.name).click();
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('select[name="acl_groups-t[]"]')
    .select(aclResource.aclGroups[1]);
  cy.getIframeBody().find('input[name="remove"]').eq(0).click();

  cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
});

Then('link between access group and Resources access must be broken', () => {
  cy.visit(PAGES.configuration.aclAccessGroupsLegacy);
  cy.wait('@getTimeZone').then(() => {
    cy.executeActionOnIframe(
      aclResource.aclGroups[1],
      ($body) => {
        cy.wrap($body)
          .contains('td.ListColLeft > a', aclResource.aclGroups[1])
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
    .find('select[name="resourceAccess-t[]"]')
    .should('not.contain', aclResource.name);
});

Given('one existing Resources access', () => {
  cy.addACLResource({ alias: aclResource.description, name: aclResource.name });

  aclResource.aclGroups.forEach((aclGroup) => {
    cy.addAclResourceToAclGroup({
      aclGroupName: aclGroup,
      aclResourceName: aclResource.name
    });
  });
});

When('I duplicate the Resources access', () => {
  cy.visit(PAGES.configuration.aclResourcesAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', aclResource.name)
    .within(() => {
      cy.get('input[type="checkbox"][name^="select"]').parent().click();
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
  'a new Resources access record is created with identical properties except the name',
  () => {
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .contains('td.ListColLeft > a', duplicatedAclResource.name)
      .click();
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .find('input[name="acl_res_name"]')
      .should('not.have.value', aclResource.name);
    cy.getIframeBody()
      .find('input[name="acl_res_alias"]')
      .should('have.value', aclResource.description);

    aclResource.aclGroups.forEach((aclGroup) => {
      cy.getIframeBody()
        .find('select[name="acl_groups-t[]"]')
        .should('contain', aclGroup);
    });

    cy.getIframeBody()
      .find('textarea[name="acl_res_comment"]')
      .should('have.value', '');
  }
);

Given('one existing enabled Resources access record', () => {
  cy.addACLResource({ alias: aclResource.description, name: aclResource.name });
});

When(
  'I modify some properties such as name, description, comments or status',
  () => {
    cy.visit(PAGES.configuration.aclResourcesAccessLegacy);
    cy.wait('@getTimeZone');

    cy.getIframeBody().contains('td.ListColLeft > a', aclResource.name).click();
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .find('input[name="acl_res_name"]')
      .type(`{selectAll}{backspace}${modifiedAclResource.name}`);
    cy.getIframeBody()
      .find('input[name="acl_res_alias"]')
      .type(`{selectAll}{backspace}${modifiedAclResource.description}`);

    cy.getIframeBody()
      .find('textarea[name="acl_res_comment"]')
      .type(`{selectAll}{backspace}${modifiedAclResource.comment}`);

    cy.getIframeBody()
      .find('input[name="acl_res_activate[acl_res_activate]"][value="0"]')
      .parent()
      .click();

    cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
  }
);

Then('the modifications are saved', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('td.ListColLeft > a', modifiedAclResource.name)
    .click();
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_res_name"]')
    .should('have.value', modifiedAclResource.name);
  cy.getIframeBody()
    .find('input[name="acl_res_alias"]')
    .should('have.value', modifiedAclResource.description);

  cy.getIframeBody()
    .find('textarea[name="acl_res_comment"]')
    .should('have.value', modifiedAclResource.comment);

  cy.getIframeBody()
    .find('input[name="acl_res_activate[acl_res_activate]"][value="0"]')
    .should('be.checked');
});

When('I delete the Resources access', () => {
  cy.visit(PAGES.configuration.aclResourcesAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', aclResource.name)
    .within(() => {
      cy.get('input[type="checkbox"][name^="select"]').parent().click();
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
  'the Resources access record is not visible anymore in Resources Access page',
  () => {
    cy.wait('@getTimeZone');

    cy.getIframeBody().should('not.contain', aclResource.name);
  }
);
