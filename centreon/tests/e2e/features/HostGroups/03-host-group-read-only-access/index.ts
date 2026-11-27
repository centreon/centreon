import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { buildHostGroupCountQuery } from '../common';

interface NamedHostGroup {
  name: string;
}

const readOnlyUser = 'rbt-ro-user';
const readOnlyUserPassword = 'Centreon!2021RbtRo';
const aclGroup = 'rbt-ro-group';
const aclMenu = 'rbt-ro-menus';
const aclResources = 'rbt-ro-resources';
// Host group the user can see, so that the listing is not empty
const visibleHostGroup = 'rbt-ro-hostgroup';
const newHostGroup = 'rbt-ro-new-hostgroup';
const readOnlyFormTitle = 'View a host group';

let creationStatus = 0;

beforeEach(() => {
  cy.startContainers();

  creationStatus = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups?*`
  }).as('getGroups');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const visitHostGroups = (query = ''): void => {
  cy.visit(`${PAGES.configuration.hostGroups}${query}`);
  cy.wait('@getGroups').its('response.statusCode').should('eq', 200);
  cy.contains('p', visibleHostGroup).should('be.visible');
};

Given('I am logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given('an ACL access group gives read-only access to host groups', () => {
  cy.addHostGroup({ name: visibleHostGroup });

  clapi(
    'ADD',
    'CONTACT',
    `${readOnlyUser};${readOnlyUser};${readOnlyUser}@localhost;${readOnlyUserPassword};0;1;en_US;local`
  );
  cy.addACLMenu({
    name: aclMenu,
    readOnly: true,
    rule: ['Configuration', 'Hosts', 'Host Groups']
  });
  cy.addACLResource({ name: aclResources });
  clapi(
    'GRANT_HOSTGROUP',
    'ACLRESOURCE',
    `${aclResources};${visibleHostGroup}`
  );
  cy.addACLGroup({ contacts: [readOnlyUser], name: aclGroup });
  clapi('ADDMENU', 'ACLGROUP', `${aclGroup};${aclMenu}`);
  clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};${aclResources}`);
  clapi('RELOAD', 'ACL', '');
  cy.applyAcl();
});

Given('a user belonging to the read-only access group is logged in', () => {
  cy.clearCookies();
  cy.loginByCredentials({
    login: readOnlyUser,
    password: readOnlyUserPassword
  });
});

Given('the user is on the host groups configuration page', () => {
  visitHostGroups();
});

When('the user tries to add a host group', () => {
  // Through the creation link of the page, then through the API
  visitHostGroups('?mode=add');

  cy.request({
    body: { alias: newHostGroup, name: newHostGroup },
    failOnStatusCode: false,
    method: 'POST',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups`
  }).then(({ status }) => {
    creationStatus = status;
  });
});

Then('the host group creation is not possible', () => {
  // The creation link only opens the host group in read-only mode
  cy.contains(readOnlyFormTitle).should('be.visible');
  cy.get('button[data-testid="submit"]').should('not.exist');

  cy.then(() => {
    expect(creationStatus, 'status code of the creation').to.eq(403);
  });

  // No Add button once the listing is displayed
  visitHostGroups();
  cy.get('[data-testid="add-resource"]').should('not.exist');
});

Then('no new host group appears in the host group list', () => {
  visitHostGroups();
  cy.get('@getGroups')
    .its('response.body.result')
    .then((result) => {
      expect(
        (result as Array<NamedHostGroup>).map(({ name }) => name),
        'host groups listed'
      ).to.deep.eq([visibleHostGroup]);
    });
  cy.contains('p', newHostGroup).should('not.exist');

  cy.requestOnDatabase({
    database: 'centreon',
    query: buildHostGroupCountQuery(newHostGroup)
  }).then(([rows]) => {
    expect(Number(rows[0].count), `host group ${newHostGroup}`).to.eq(0);
  });
});
