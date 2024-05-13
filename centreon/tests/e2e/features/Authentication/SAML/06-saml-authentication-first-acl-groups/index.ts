import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureSAML,
  initializeSAMLUser,
  navigateToSAMLConfigPage
} from '../common';
import { configureProviderAcls, getAccessGroupId } from '../../../../commons';

before(() => {
  cy.startContainers({ profiles: ['saml'] }).then(() => {
    configureProviderAcls();
    initializeSAMLUser();
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/authentication/providers/saml'
  }).as('getSAMLProvider');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/authentication/providers/configurations'
  }).as('getCentreonAuthConfigs');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/saml'
  }).as('updateSAMLProvider');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/access-groups?page=1&sort_by=%7B%22name%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%5D%7D'
  }).as('getListAccessGroup');
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the administrator sets valid settings in the Roles mapping and activate apply first only and saves',
  () => {
    navigateToSAMLConfigPage();

    cy.getByLabel({
      label: 'Enable SAMLv2 authentication',
      tag: 'input'
    }).check();

    configureSAML();

    cy.getByLabel({ label: 'Roles mapping' }).click();

    cy.getByLabel({
      label: 'Enable automatic management',
      tag: 'input'
    })
      .eq(0)
      .check();

    cy.getByLabel({
      label: 'Apply only first role',
      tag: 'input'
    }).check();

    cy.getByLabel({
      label: 'Roles attribute path',
      tag: 'input'
    }).type('{selectAll}{backspace}Role');

    cy.getByLabel({
      label: 'Role value',
      tag: 'input'
    }).type('{selectAll}{backspace}centreon-editor');

    cy.getByLabel({
      label: 'ACL access group',
      tag: 'input'
    }).click();

    cy.wait('@getListAccessGroup');

    cy.get('div[role="presentation"] ul li').eq(1).click();

    cy.getByLabel({
      label: 'ACL access group',
      tag: 'input'
    }).should('have.value', 'ALL');

    cy.getByLabel({
      label: 'Role value',
      tag: 'input'
    })
      .eq(1)
      .type('{selectAll}{backspace}default-roles-centreon_sso');

    cy.getByLabel({
      label: 'ACL access group',
      tag: 'input'
    })
      .eq(1)
      .click();

    cy.get('div[role="presentation"] ul li').eq(0).click();

    cy.getByLabel({
      label: 'ACL access group',
      tag: 'input'
    })
      .eq(1)
      .should('have.value', 'ACL Group test');

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();

    cy.wait('@updateSAMLProvider').its('response.statusCode').should('eq', 204);

    cy.logout();
  }
);

Then(
  'the users from the 3rd party authentication service are attached to ACL group on the first condition validated by order defined in the UI',
  () => {
    const username = 'user-non-admin-for-SAML-authentication';

    cy.visit('/').getByLabel({ label: 'Login with SAML', tag: 'a' }).click();

    cy.intercept({
      method: 'GET',
      url: '/centreon/api/internal.php?object=centreon_topcounter&action=user'
    }).as('getUserInformation');

    cy.loginKeycloak(username);

    cy.wait('@getUserInformation').its('response.statusCode').should('eq', 200);
    cy.url().should('include', '/monitoring/resources');

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({ jsonName: 'admin' })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);

    getAccessGroupId('ALL').then((groupId) => {
      cy.visit(`/centreon/main.php?p=50203&o=c&acl_group_id=${groupId}`)
        .wait('@getTimeZone')
        .getIframeBody()
        .find('form')
        .within(() => {
          cy.get('select[name="cg_contacts-t[]"]').contains('saml');
        });
    });

    getAccessGroupId('ACL Group test').then((groupId) => {
      cy.visit(`/centreon/main.php?p=50203&o=c&acl_group_id=${groupId}`)
        .wait('@getTimeZone')
        .getIframeBody()
        .find('form')
        .within(() => {
          cy.get('select[name="cg_contacts-t[]"]').should(
            'not.contain',
            'saml'
          );
        });
    });
  }
);

after(() => {
  // avoid random "Cannot read properties of null (reading 'postMessage')" when stopping containers
  cy.on('uncaught:exception', () => false);

  cy.stopContainers();
});
