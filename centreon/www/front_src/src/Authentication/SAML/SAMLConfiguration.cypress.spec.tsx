import { replace } from 'ramda';

import { TestQueryProvider, Method } from '@centreon/ui';

import {
  accessGroupsEndpoint,
  authenticationProvidersEndpoint,
  contactGroupsEndpoint
} from '../api/endpoints';
import { Provider } from '../models';
import {
  labelAclAccessGroup,
  labelApplyOnlyFirtsRole,
  labelConditionValue,
  labelConditionsAttributePath,
  labelContactGroup,
  labelContactTemplate,
  labelDeleteRelation,
  labelEnableAutoImport,
  labelEnableAutomaticManagement,
  labelEnableConditionsOnIdentityProvider,
  labelGroupValue,
  labelGroupsAttributePath,
  labelMixed,
  labelRoleValue,
  labelRolesAttributePath,
  labelRolesMapping
} from '../shared/translatedLabels';
import {
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from '../translatedLabels';
import { labelSave } from '../Local/translatedLabels';

import {
  labelBothIdentityProviderAndCentreonUI,
  labelCentreonUIOnly,
  labelCertificate,
  labelDefineSAMLConfiguration,
  labelEmailAttribute,
  labelEnableSAMLAuthentication,
  labelEntityIdURL,
  labelFullNameAttribute,
  labelLogoutUrl,
  labelRemoteLoginUrl,
  labelRequired,
  labelSAMLOnly,
  labelUserIdAttribute
} from './translatedLabels';
import { retrievedSAMLConfiguration } from './defaults';

import SAMLConfigurationForm from '.';

const getRetrievedEntities = (label: string): object => ({
  meta: {
    limit: 2,
    page: 1,
    total: 2
  },
  result: [
    {
      id: 1,
      name: `${label} 1`
    },
    {
      id: 2,
      name: `${label} 2`
    }
  ]
});

const retrievedAccessGroups = getRetrievedEntities('Access Group');
const retrievedContactGroup = getRetrievedEntities('Contact Group');

const initializeAndMountSAMLConfigurationForm = (): void => {
  cy.interceptAPIRequest({
    alias: 'getSAMLConfiguration',
    method: Method.GET,
    path: replace('./', '**', authenticationProvidersEndpoint(Provider.SAML)),
    response: retrievedSAMLConfiguration
  });
  cy.interceptAPIRequest({
    alias: 'putSAMLConfiguration',
    method: Method.PUT,
    path: replace('./', '**', authenticationProvidersEndpoint(Provider.SAML)),
    response: {}
  });
  cy.interceptAPIRequest({
    alias: 'getAccessGroups',
    method: Method.GET,
    path: `${replace('./', '**', accessGroupsEndpoint)}**`,
    response: retrievedAccessGroups
  });
  cy.interceptAPIRequest({
    alias: 'getContactGroups',
    method: Method.GET,
    path: `${replace('./', '**', contactGroupsEndpoint)}**`,
    response: retrievedContactGroup
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <SAMLConfigurationForm />
      </TestQueryProvider>
    )
  });
};

describe('SAMLConfiguration', () => {
  beforeEach(() => {
    initializeAndMountSAMLConfigurationForm();
  });

  it('displays the SAML configuration form', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelDefineSAMLConfiguration).should('be.visible');

    cy.findByLabelText(labelEnableSAMLAuthentication).should('be.checked');
    cy.findByLabelText(labelSAMLOnly).should('not.be.checked');
    cy.findByLabelText(labelMixed).should('be.checked');

    cy.scrollTo(0, 0);

    cy.matchImageSnapshot('displays the SAML configuration form - Activation');

    cy.contains(labelIdentityProvider).click();

    cy.findByLabelText(labelRemoteLoginUrl).should(
      'have.value',
      retrievedSAMLConfiguration.remote_login_url
    );
    cy.findByLabelText(labelEntityIdURL).should(
      'have.value',
      retrievedSAMLConfiguration.entity_id_url
    );
    cy.findByLabelText(labelCertificate).should(
      'have.value',
      retrievedSAMLConfiguration.certificate
    );
    cy.findByLabelText(labelUserIdAttribute).should(
      'have.value',
      retrievedSAMLConfiguration.user_id_attribute
    );
    cy.findByLabelText(labelCentreonUIOnly).should('not.be.checked');
    cy.findByLabelText(labelBothIdentityProviderAndCentreonUI).should(
      'be.checked'
    );
    cy.findByLabelText(labelLogoutUrl).should(
      'have.value',
      retrievedSAMLConfiguration.logout_from_url
    );

    cy.wait(500).scrollTo(0, 300);

    cy.matchImageSnapshot(
      'displays the SAML configuration form - Identity provider'
    );

    cy.contains(labelAuthenticationConditions).click();

    cy.findByLabelText(labelEnableConditionsOnIdentityProvider).should(
      'be.checked'
    );
    cy.findByLabelText(labelConditionsAttributePath).should(
      'have.value',
      retrievedSAMLConfiguration.authentication_conditions.attribute_path
    );
    cy.findAllByLabelText(labelConditionValue).should('have.length', 2);
    cy.findAllByLabelText(labelConditionValue)
      .eq(0)
      .should(
        'have.value',
        retrievedSAMLConfiguration.authentication_conditions
          .authorized_values[0]
      );
    cy.findAllByLabelText(labelConditionValue).eq(1).should('have.value', '');

    cy.wait(500).scrollTo(0, 800);

    cy.matchImageSnapshot(
      'displays the SAML configuration form - Authentication conditions'
    );

    cy.contains(labelAutoImportUsers).click();

    cy.findByLabelText(labelEnableAutoImport).should('be.checked');
    cy.findByLabelText(labelContactTemplate).should(
      'have.value',
      retrievedSAMLConfiguration.contact_template.name
    );
    cy.findByLabelText(labelEmailAttribute).should(
      'have.value',
      retrievedSAMLConfiguration.email_bind_attribute
    );
    cy.findByLabelText(labelFullNameAttribute).should(
      'have.value',
      retrievedSAMLConfiguration.fullname_bind_attribute
    );

    cy.wait(500).scrollTo(0, 1200);

    cy.matchImageSnapshot(
      'displays the SAML configuration form - Auto import users'
    );

    cy.contains(labelRolesMapping).click();

    cy.findAllByLabelText(labelEnableAutomaticManagement)
      .eq(0)
      .should('be.checked');
    cy.findByLabelText(labelApplyOnlyFirtsRole).should('be.checked');
    cy.findByLabelText(labelRolesAttributePath).should(
      'have.value',
      retrievedSAMLConfiguration.roles_mapping.attribute_path
    );
    cy.findAllByLabelText(labelRoleValue).should('have.length', 2);
    cy.findAllByLabelText(labelRoleValue)
      .eq(0)
      .should(
        'have.value',
        retrievedSAMLConfiguration.roles_mapping.relations[0].claim_value
      );
    cy.findAllByLabelText(labelRoleValue).eq(1).should('have.value', '');
    cy.findAllByLabelText(labelAclAccessGroup).should('have.length', 2);
    cy.findAllByLabelText(labelAclAccessGroup)
      .eq(0)
      .should(
        'have.value',
        retrievedSAMLConfiguration.roles_mapping.relations[0].access_group.name
      );
    cy.findAllByLabelText(labelAclAccessGroup).eq(1).should('have.value', '');

    cy.wait(500).scrollTo('bottom');

    cy.matchImageSnapshot(
      'displays the SAML configuration form - Roles mapping'
    );

    cy.contains(labelGroupsMapping).click();

    cy.findAllByLabelText(labelEnableAutomaticManagement)
      .eq(1)
      .should('be.checked');
    cy.findByLabelText(labelGroupsAttributePath).should(
      'have.value',
      retrievedSAMLConfiguration.groups_mapping.attribute_path
    );
    cy.findAllByLabelText(labelGroupValue).should('have.length', 2);
    cy.findAllByLabelText(labelGroupValue)
      .eq(0)
      .should(
        'have.value',
        retrievedSAMLConfiguration.groups_mapping.relations[0].group_value
      );
    cy.findAllByLabelText(labelGroupValue).eq(1).should('have.value', '');
    cy.findAllByLabelText(labelContactGroup).should('have.length', 2);
    cy.findAllByLabelText(labelContactGroup)
      .eq(0)
      .should(
        'have.value',
        retrievedSAMLConfiguration.groups_mapping.relations[0].contact_group
          .name
      );
    cy.findAllByLabelText(labelContactGroup).eq(1).should('have.value', '');

    cy.wait(500).scrollTo('bottom');

    cy.matchImageSnapshot(
      'displays the SAML configuration form - Groups mapping'
    );
  });

  it('disables auto import fields when auto import is disabled', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelAutoImportUsers).click();

    cy.findByLabelText(labelEnableAutoImport).click();

    cy.findByLabelText(labelContactTemplate).should('be.disabled');
    cy.findByLabelText(labelEmailAttribute).should('be.disabled');
    cy.findByLabelText(labelFullNameAttribute).should('be.disabled');
  });

  it('hides the "Logout URL" field when the "Centreon UI only" option is selected', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelIdentityProvider).click();

    cy.findByLabelText(labelCentreonUIOnly).click();

    cy.findByLabelText(labelLogoutUrl).should('not.exist');

    cy.matchImageSnapshot();
  });

  it('adds a new condition value when the last condition value field is filled', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelAuthenticationConditions).click();

    cy.findAllByLabelText(labelConditionValue).eq(1).type('value2');
    cy.findAllByLabelText(labelConditionValue).should('have.length', 3);
    cy.findAllByLabelText(labelConditionValue).eq(2).should('have.value', '');

    cy.matchImageSnapshot();
  });

  it('removes a condition value when the "Delete the relation" button is clicked', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelAuthenticationConditions).click();

    cy.findAllByLabelText(labelConditionValue).should('have.length', 2);

    cy.findAllByLabelText(labelDeleteRelation).eq(0).click();

    cy.findAllByLabelText(labelConditionValue).should('have.length', 1);
    cy.findAllByLabelText(labelConditionValue).eq(0).should('have.value', '');

    cy.matchImageSnapshot();
  });

  it('sorts "roles/ACL access group" rows when the handler is dragged', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelRolesMapping).click();

    cy.findAllByLabelText(labelRoleValue).eq(1).type('A role');
    cy.findAllByLabelText(labelAclAccessGroup).eq(1).click();

    cy.waitForRequest('@getAccessGroups');

    cy.contains('Access Group 1').click();

    cy.moveSortableElement({
      direction: 'up',
      element: cy.findAllByTestId('UnfoldMoreIcon').eq(1).parent()
    });

    cy.findAllByLabelText(labelRoleValue).eq(0).should('have.value', 'A role');
    cy.findAllByLabelText(labelAclAccessGroup)
      .eq(0)
      .should('have.value', 'Access Group 1');

    cy.matchImageSnapshot();
  });

  it('removes the "roles/ACL access group" row when the "Delete the relation" button is clicked', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelRolesMapping).click();

    cy.findAllByLabelText(labelDeleteRelation).eq(1).click();

    cy.findAllByLabelText(labelRoleValue).should('have.length', 1);
    cy.findAllByLabelText(labelRoleValue).eq(0).should('have.value', '');

    cy.matchImageSnapshot();
  });

  it('removes the sortable handler when "apply only first role" is disabled', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelRolesMapping).click();

    cy.findByLabelText(labelApplyOnlyFirtsRole).click();

    cy.findAllByTestId('UnfoldMoreIcon').should('not.exist');

    cy.wait(500).matchImageSnapshot();
  });

  it('adds a new "groups/contact group" row when the last "group/contact group" row is filled', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelGroupsMapping).click();

    cy.findAllByLabelText(labelGroupValue).eq(1).type('A group');
    cy.findAllByLabelText(labelContactGroup).eq(1).click();

    cy.waitForRequest('@getContactGroups');

    cy.contains('Contact Group 1').click();

    cy.findAllByLabelText(labelGroupValue).should('have.length', 3);
    cy.findAllByLabelText(labelGroupValue).eq(2).should('have.value', '');
    cy.findAllByLabelText(labelContactGroup).should('have.length', 3);
    cy.findAllByLabelText(labelContactGroup).eq(2).should('have.value', '');

    cy.matchImageSnapshot();
  });

  it('removes the "groups/contact group" row when the "Delete the relation" button is clicked', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelGroupsMapping).click();

    cy.findAllByLabelText(labelDeleteRelation).eq(2).click();

    cy.findAllByLabelText(labelGroupValue).should('have.length', 1);
    cy.findAllByLabelText(labelGroupValue).eq(0).should('have.value', '');

    cy.matchImageSnapshot();
  });

  it('saves the SAML configuration when a field is updated', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelIdentityProvider).click();

    cy.contains(labelSave).should('be.disabled');

    cy.findByLabelText(labelRemoteLoginUrl).type('https://example.com');

    cy.contains(labelSave).click();

    cy.waitForRequest('@putSAMLConfiguration');

    cy.waitForRequest('@getSAMLConfiguration');

    cy.matchImageSnapshot();
  });

  it('disables the "Save" button when the required fields are cleared', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelIdentityProvider).click();

    cy.findByLabelText(labelRemoteLoginUrl).clear();
    cy.findByLabelText(labelEntityIdURL).clear();
    cy.findByLabelText(labelCertificate).clear();
    cy.findByLabelText(labelUserIdAttribute).clear();
    cy.findByLabelText(labelRemoteLoginUrl).click();

    cy.findAllByText(labelRequired).should('have.length', 4);

    cy.contains(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('disables the "Save" button when the "Logout URL" field is cleared', () => {
    cy.waitForRequest('@getSAMLConfiguration');

    cy.contains(labelIdentityProvider).click();

    cy.findByLabelText(labelLogoutUrl).clear();
    cy.findByLabelText(labelBothIdentityProviderAndCentreonUI).click();

    cy.contains(labelRequired).should('be.visible');

    cy.contains(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });
});
