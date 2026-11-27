import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  buildContactGroupsQuery,
  buildContactQuery,
  buildLdapConfigurationIdQuery,
  glauthGroupSearch,
  ldapConfigurationName
} from '../common';

// LDAP user of the glauth server (password = login), member of the groups
// "windows", "linux" and "networking"
const ldapUser = { login: 'centreon-ldap', password: 'centreon-ldap' };
const ldapGroupName = 'networking';
const defaultContactGroupName = 'rbt-ldap-default-cg';
const contactTemplateName = 'contact_template';

beforeEach(() => {
  cy.startContainers({ profiles: ['openldap'] });

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

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const setLdapParameter = (name: string, value: string): void => {
  clapi('SETPARAM', 'LDAP', `${ldapConfigurationName};${name};${value}`);
};

Given(
  'an LDAP configuration with Auto import users enabled, a contact template and a default contact group',
  () => {
    clapi('ADD', 'CG', `${defaultContactGroupName};${defaultContactGroupName}`);

    setLdapParameter('ldap_auto_import', '1');
    setLdapParameter('ldap_contact_tmpl', contactTemplateName);
    setLdapParameter('ldap_default_cg', defaultContactGroupName);
    Object.entries(glauthGroupSearch).forEach(([name, value]) => {
      setLdapParameter(name, value);
    });
  }
);

Given('a contact group named after one LDAP group of the user', () => {
  clapi('ADD', 'CG', `${ldapGroupName};${ldapGroupName}`);
});

Given('the LDAP user has never logged in to Centreon', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    params: [ldapUser.login],
    query: buildContactQuery()
  }).then(([rows]) => {
    expect(rows, `contact ${ldapUser.login}`).to.have.length(0);
  });
});

When('the LDAP user logs in with its directory password', () => {
  cy.visit(`${Cypress.config().baseUrl}`);
  cy.getByLabel({ label: 'Alias', tag: 'input' }).type(
    `{selectAll}{backspace}${ldapUser.login}`
  );
  cy.get('input[type="password"]').type(
    `{selectAll}{backspace}${ldapUser.password}`
  );
  cy.getByLabel({ label: 'Connect', tag: 'button' }).click();
});

Then('the login succeeds', () => {
  cy.contains('.MuiAlert-message', 'Login succeeded').should('exist');
  cy.wait('@getNavigationList');
  cy.url().should('not.include', '/login');
});

Then(
  'a contact of authentication type LDAP is created from the contact template',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      params: [ldapConfigurationName],
      query: buildLdapConfigurationIdQuery()
    }).then(([configurations]) => {
      cy.requestOnDatabase({
        database: 'centreon',
        params: [ldapUser.login],
        query: buildContactQuery()
      }).then(([rows]) => {
        expect(rows, `contact ${ldapUser.login}`).to.have.length(1);
        expect(rows[0].contact_auth_type).to.eq('ldap');
        expect(rows[0].template_name).to.eq(contactTemplateName);
        expect(Number(rows[0].ar_id)).to.eq(Number(configurations[0].ar_id));
      });
    });
  }
);

Then(
  'the contact is linked to the default contact group and to the contact group named after its LDAP group',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      params: [ldapUser.login],
      query: buildContactGroupsQuery()
    }).then(([rows]) => {
      const groupNames = rows.map(({ cg_name }) => cg_name);

      expect(groupNames).to.include(defaultContactGroupName);
      expect(groupNames).to.include(ldapGroupName);
    });
  }
);
