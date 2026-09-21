import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
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

const resourcesAccessFormTabs = [
  { label: 'General Information', navId: 'c1', panelId: 'tab1' },
  { label: 'Host Resources', navId: 'c2', panelId: 'tab2' },
  { label: 'Service Resources', navId: 'c3', panelId: 'tab3' },
  { label: 'Meta Services', navId: 'c4', panelId: 'tab4' },
  { label: 'Filters', navId: 'c5', panelId: 'tab5' },
  { label: 'Image folders', navId: 'c6', panelId: 'tab6' }
];

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
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
          (body) => {
            cy.wrap(body).contains('a', 'Authorizations information').click();
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
      (body) => {
        cy.wrap(body)
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
      (body) => {
        cy.wrap(body).contains('a', 'Authorizations information').click();
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

When('I open the Resources access for editing', () => {
  cy.visit(PAGES.configuration.aclResourcesAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', aclResource.name).click();
  cy.wait('@getTimeZone');
});

Then('every tab of the form can be opened by clicking on it', () => {
  // The message container is always in the DOM and usually empty, which makes
  // it invisible yet still hit-tested. Assert head-on that it no longer
  // intercepts clicks: the per-tab clicks below only catch that when a tab
  // happens to fall within its hardcoded position, which depends on label
  // widths and would stop holding without any test turning red.
  cy.getIframeBody()
    .find('#centreonMsg')
    .then(($container) => {
      const container = $container[0];
      const { left, top, width, height } = container.getBoundingClientRect();
      const topmostElement = container.ownerDocument.elementFromPoint(
        left + width / 2,
        top + height / 2
      );

      expect(
        topmostElement,
        'the message container must not intercept clicks'
      ).not.to.equal(container);
    });

  resourcesAccessFormTabs.forEach(({ label, navId, panelId }) => {
    // Deliberately a plain click: Cypress hit-tests the tab, so an element
    // overlaying the tab bar fails the test instead of being silently bypassed
    // as { force: true } would do.
    cy.getIframeBody().contains('#mainnav li a', label).click();

    // montre() both activates the tab and hides every other panel.
    cy.getIframeBody().find(`#${navId}`).should('have.class', 'a');
    cy.getIframeBody().find(`#${panelId}`).should('be.visible');
    cy.getIframeBody().find('div.tab:visible').should('have.length', 1);
  });
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
