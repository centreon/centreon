import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import data from '../../../fixtures/acls/acl-data.json';
import '../commands';

const ACLResource = {
  ...data.ACLResource,
  ACLGroups: [data.ACLGroups.ACLGroup1.name, data.ACLGroups.ACLGroup2.name]
};

const duplicatedACLResource = {
  name: ACLResource.name + '_1'
};

const modifedACLResource = {
  name: ACLResource.name + '_modified',
  description: ACLResource.description + '_modified',
  comment: ACLResource.comment + '_modified'
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
    name: data.ACLGroups.ACLGroup1.name,
    contacts: [data.contacts.contact1.name]
  });
  cy.addACLGroup({
    name: data.ACLGroups.ACLGroup2.name,
    contacts: [data.contacts.contact1.name]
  });
  cy.addACLGroup({
    name: data.ACLGroups.ACLGroup3.name,
    contacts: [data.contacts.contact1.name]
  });
});

When('I add a new Resources access linked with two groups', () => {
  cy.navigateTo({
    page: 'Resources Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_res_name"]')
    .click()
    .type(ACLResource.name);
  cy.getIframeBody()
    .find('input[name="acl_res_alias"]')
    .click()
    .type(ACLResource.description);

  ACLResource.ACLGroups.map((ACLGroup) => {
    cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(ACLGroup);
    cy.getIframeBody().find('input[name="add"]').eq(0).click();
  });

  cy.getIframeBody()
    .find('textarea[name="acl_res_comment"]')
    .click()
    .type(ACLResource.comment);

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
});

Then('the Resources access is saved with its properties', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', ACLResource.name)
    .within(() => {
      cy.get('td.ListColLeft > a').eq(0).click();
    });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_res_name"]')
    .should('have.value', ACLResource.name);
  cy.getIframeBody()
    .find('input[name="acl_res_alias"]')
    .should('have.value', ACLResource.description);

  ACLResource.ACLGroups.map((ACLGroup) => {
    cy.getIframeBody()
      .find('select[name="acl_groups-t[]"]')
      .should('contain', ACLGroup);
  });

  cy.getIframeBody()
    .find('textarea[name="acl_res_comment"]')
    .should('have.value', ACLResource.comment);
});

Then(
  'only chosen linked access groups display the new Resources access in Authorized information tab',
  () => {
    Object.entries(data.ACLGroups).forEach((ACLGroup) => {
      cy.navigateTo({
        page: 'Access Groups',
        rootItemNumber: 4,
        subMenu: 'ACL'
      });
      cy.wait('@getTimeZone');

      cy.getIframeBody()
        .contains('tr', ACLGroup[1].name)
        .within(() => {
          cy.get('td.ListColLeft').click();
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

      ACLResource.ACLGroups.includes(ACLGroup[1].name)
        ? cy
            .getIframeBody()
            .find('select[name="resourceAccess-t[]"]')
            .should('contain', ACLResource.name)
        : cy
            .getIframeBody()
            .find('select[name="resourceAccess-t[]"]')
            .should('not.contain', ACLResource.name);
    });
  }
);

Given('one existing Resources access linked with two access groups', () => {
  cy.addACLResource({ name: ACLResource.name });

  ACLResource.ACLGroups.map((ACLGroup) => {
    cy.addACLResourceToACLGroup({
      ACLResourceName: ACLResource.name,
      ACLGroupName: ACLGroup
    });
  });
});

When('I remove one access group', () => {
  cy.navigateTo({
    page: 'Resources Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', ACLResource.name)
    .within(() => {
      cy.get('td.ListColLeft > a').eq(0).click();
    });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('select[name="acl_groups-t[]"]')
    .select(ACLResource.ACLGroups[1]);
  cy.getIframeBody().find('input[name="remove"]').eq(0).click();

  cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
});

Then('link between access group and Resources access must be broken', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });

  cy.wait('@getTimeZone').then(() => {
    cy.executeActionOnIframe(
      ACLResource.ACLGroups[1],
      ($body) => {
        cy.wrap($body)
          .contains('tr', ACLResource.ACLGroups[1])
          .within(() => {
            cy.get('td.ListColLeft').click();
          });
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
    .should('not.contain', ACLResource.name);
});

Given('one existing Resources access', () => {
  cy.addACLResource({ name: ACLResource.name, alias: ACLResource.description });

  ACLResource.ACLGroups.map((ACLGroup) => {
    cy.addACLResourceToACLGroup({
      ACLResourceName: ACLResource.name,
      ACLGroupName: ACLGroup
    });
  });
});

When('I duplicate the Resources access', () => {
  cy.navigateTo({
    page: 'Resources Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', ACLResource.name)
    .within(() => {
      cy.get('input[type="checkbox"][name^="select"]').parent().click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      cy.stub<any>(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then(
  'a new Resources access record is created with identical properties except the name',
  () => {
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .contains('tr', duplicatedACLResource.name)
      .within(() => {
        cy.get('td.ListColLeft > a').eq(0).click();
      });
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .find('input[name="acl_res_name"]')
      .should('not.have.value', ACLResource.name);
    cy.getIframeBody()
      .find('input[name="acl_res_alias"]')
      .should('have.value', ACLResource.description);

    ACLResource.ACLGroups.map((ACLGroup) => {
      cy.getIframeBody()
        .find('select[name="acl_groups-t[]"]')
        .should('contain', ACLGroup);
    });

    cy.getIframeBody()
      .find('textarea[name="acl_res_comment"]')
      .should('have.value', '');
  }
);

Given('one existing enabled Resources access record', () => {
  cy.addACLResource({ name: ACLResource.name, alias: ACLResource.description });
});

When(
  'I modify some properties such as name, description, comments or status',
  () => {
    cy.navigateTo({
      page: 'Resources Access',
      rootItemNumber: 4,
      subMenu: 'ACL'
    });
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .contains('tr', ACLResource.name)
      .within(() => {
        cy.get('td.ListColLeft > a').eq(0).click();
      });
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .find('input[name="acl_res_name"]')
      .clear()
      .click()
      .type(modifedACLResource.name);
    cy.getIframeBody()
      .find('input[name="acl_res_alias"]')
      .clear()
      .click()
      .type(modifedACLResource.description);

    cy.getIframeBody()
      .find('textarea[name="acl_res_comment"]')
      .clear()
      .click()
      .type(modifedACLResource.comment);

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
    .contains('tr', modifedACLResource.name)
    .within(() => {
      cy.get('td.ListColLeft > a').eq(0).click();
    });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_res_name"]')
    .should('have.value', modifedACLResource.name);
  cy.getIframeBody()
    .find('input[name="acl_res_alias"]')
    .should('have.value', modifedACLResource.description);

  cy.getIframeBody()
    .find('textarea[name="acl_res_comment"]')
    .should('have.value', modifedACLResource.comment);

  cy.getIframeBody()
    .find('input[name="acl_res_activate[acl_res_activate]"][value="0"]')
    .should('be.checked');
});

When('I delete the Resources access', () => {
  cy.navigateTo({
    page: 'Resources Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', ACLResource.name)
    .within(() => {
      cy.get('input[type="checkbox"][name^="select"]').parent().click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      cy.stub<any>(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then(
  'the Resources access record is not visible anymore in Resources Access page',
  () => {
    cy.wait('@getTimeZone');

    cy.getIframeBody().should('not.contain', ACLResource.name);
  }
);
