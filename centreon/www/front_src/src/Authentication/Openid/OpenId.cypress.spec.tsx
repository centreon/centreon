import { Method, TestQueryProvider } from '@centreon/ui';

import {
  labelDoYouWantToResetTheForm,
  labelReset,
  labelResetTheForm,
  labelSave
} from '../Local/translatedLabels';
import {
  accessGroupsEndpoint,
  authenticationProvidersEndpoint,
  contactGroupsEndpoint,
  contactTemplatesEndpoint
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
  labelGroupsAttributePath,
  labelMixed,
  labelRolesAttributePath,
  labelRolesMapping
} from '../shared/translatedLabels';
import {
  labelActivation,
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from '../translatedLabels';

import {
  labelAuthorizationEndpoint,
  labelBaseUrl,
  labelBlacklistClientAddresses,
  labelClientID,
  labelClientSecret,
  labelDefineOpenIDConnectConfiguration,
  labelDefineYourEndpoint,
  labelDisableVerifyPeer,
  labelEmailAttributePath,
  labelEnableOpenIDConnectAuthentication,
  labelEndSessionEndpoint,
  labelFullnameAttributePath,
  labelGroupValue,
  labelIntrospectionEndpoint,
  labelIntrospectionTokenEndpoint,
  labelInvalidIPAddress,
  labelInvalidURL,
  labelLoginAttributePath,
  labelOpenIDConnectOnly,
  labelOther,
  labelRedirectUrl,
  labelScopes,
  labelTokenEndpoint,
  labelTrustedClientAddresses,
  labelUseBasicAuthenticatonForTokenEndpointAuthentication,
  labelUserInformationEndpoint
} from './translatedLabels';

import OpenidConfigurationForm from '.';

const retrievedOpenidConfiguration = {
  authentication_conditions: {
    attribute_path: 'auth attribute path',
    authorized_values: ['authorized'],
    blacklist_client_addresses: ['127.0.0.1'],
    endpoint: {
      custom_endpoint: null,
      type: 'introspection_endpoint'
    },
    is_enabled: false,
    trusted_client_addresses: ['127.0.0.1']
  },
  authentication_type: 'client_secret_post',
  authorization_endpoint: '/authorize',
  auto_import: false,
  base_url: 'https://localhost:8080',
  client_id: 'client_id',
  client_secret: 'client_secret',
  connection_scopes: ['openid'],
  contact_template: null,
  email_bind_attribute: 'email',
  endsession_endpoint: '/logout',
  fullname_bind_attribute: 'lastname',
  groups_mapping: {
    attribute_path: 'group attribute path',
    endpoint: {
      custom_endpoint: '/group/endpoint',
      type: 'custom_endpoint'
    },
    is_enabled: true,
    relations: []
  },
  introspection_token_endpoint: '/introspect',
  is_active: true,
  is_forced: false,
  login_claim: 'sub',
  redirect_url: '',
  roles_mapping: {
    apply_only_first_role: true,
    attribute_path: 'role attribute path',
    endpoint: {
      custom_endpoint: '/role/endpoint',
      type: 'custom_endpoint'
    },
    is_enabled: false,
    relations: []
  },
  token_endpoint: '/token',
  userinfo_endpoint: '/userinfo',
  verify_peer: false
};

const getRetrievedEntities = (label: string): object => ({
  meta: {
    limit: 10,
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
const retrievedContactTemplates = getRetrievedEntities('Contact Template');
const retrievedContactGroups = getRetrievedEntities('Contact Group');

const initialize = (): void => {
  cy.viewport('macbook-15');

  cy.interceptAPIRequest({
    alias: 'getOpenidConfiguration',
    method: Method.GET,
    path: authenticationProvidersEndpoint(Provider.Openid),
    response: retrievedOpenidConfiguration
  });
  cy.interceptAPIRequest({
    alias: 'getAccessGroups',
    method: Method.GET,
    path: `${accessGroupsEndpoint}**`,
    response: retrievedAccessGroups
  });
  cy.interceptAPIRequest({
    alias: 'getContactTemplates',
    method: Method.GET,
    path: `${contactTemplatesEndpoint}**`,
    response: retrievedContactTemplates
  });
  cy.interceptAPIRequest({
    alias: 'getContactGroups',
    method: Method.GET,
    path: `${contactGroupsEndpoint}**`,
    response: retrievedContactGroups
  });
  cy.interceptAPIRequest({
    alias: 'updateOpenidConfiguration',
    method: Method.PUT,
    path: authenticationProvidersEndpoint(Provider.Openid)
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <OpenidConfigurationForm />
      </TestQueryProvider>
    )
  });
};

const unfoldPanels = (): void => {
  cy.findByLabelText(labelGroupsMapping).click();
  cy.findByLabelText(labelRolesMapping).click();
  cy.findByLabelText(labelAutoImportUsers).click();
  cy.findByLabelText(labelAuthenticationConditions).click();
  cy.findByLabelText(labelIdentityProvider).click();
};

describe('Open ID', () => {
  beforeEach(initialize);

  it('displays the form', () => {
    cy.contains(labelDefineOpenIDConnectConfiguration).should('exist');

    cy.waitForRequest('@getOpenidConfiguration');

    cy.contains(labelActivation).should('exist');
    unfoldPanels();

    cy.findByLabelText(labelEnableOpenIDConnectAuthentication).should(
      'be.checked'
    );

    cy.findByLabelText(labelOpenIDConnectOnly).should('not.be.checked');
    cy.findByLabelText(labelMixed).should('be.checked');
    cy.findByLabelText(labelTrustedClientAddresses).should('exist');
    cy.findByLabelText(labelBlacklistClientAddresses).should('exist');
    cy.findAllByText('127.0.0.1').should('have.length', 2);
    cy.findByLabelText(labelBaseUrl).should(
      'have.value',
      'https://localhost:8080'
    );
    cy.findByLabelText(labelAuthorizationEndpoint).should(
      'have.value',
      '/authorize'
    );
    cy.findByLabelText(labelTokenEndpoint).should('have.value', '/token');
    cy.findByLabelText(labelIntrospectionTokenEndpoint).should(
      'have.value',
      '/introspect'
    );
    cy.findByLabelText(labelUserInformationEndpoint).should('exist');
    cy.findByLabelText(labelEndSessionEndpoint).should('have.value', '/logout');
    cy.findByLabelText(labelScopes).should('exist');
    cy.findByText('openid').should('exist');
    cy.findByLabelText(labelLoginAttributePath).should('have.value', 'sub');
    cy.findByLabelText(labelClientID).should('have.value', 'client_id');
    cy.findByLabelText(labelClientSecret).should('have.value', 'client_secret');
    cy.findByLabelText(
      labelUseBasicAuthenticatonForTokenEndpointAuthentication
    ).should('not.be.checked');
    cy.findByLabelText(labelEnableAutoImport).should('not.be.checked');
    cy.findByLabelText(labelDisableVerifyPeer).should('not.be.checked');
    cy.findByLabelText(labelEmailAttributePath).should('have.value', 'email');
    cy.findByLabelText(labelFullnameAttributePath).should(
      'have.value',
      'lastname'
    );
    cy.findByLabelText(labelEnableConditionsOnIdentityProvider).should(
      'not.be.checked'
    );
    cy.findByLabelText(labelConditionsAttributePath).should(
      'have.value',
      'auth attribute path'
    );
    cy.findAllByLabelText(labelIntrospectionEndpoint)
      .eq(0)
      .should('be.checked');
    cy.findAllByLabelText(labelConditionValue)
      .eq(0)
      .should('have.value', 'authorized');
    cy.findAllByLabelText(labelEnableAutomaticManagement)
      .eq(0)
      .should('not.be.checked');
    cy.findAllByLabelText(labelApplyOnlyFirtsRole).eq(0).should('be.checked');
    cy.findAllByLabelText(labelRolesAttributePath)
      .eq(0)
      .should('have.value', 'role attribute path');
    cy.findAllByLabelText(labelOther).eq(1).should('be.checked');
    cy.findAllByLabelText(labelDefineYourEndpoint)
      .eq(0)
      .should('have.value', '/role/endpoint');
    cy.findByLabelText(labelGroupsAttributePath).should(
      'have.value',
      'group attribute path'
    );
    cy.findAllByLabelText(labelOther).eq(2).should('be.checked');
    cy.findAllByLabelText(labelDefineYourEndpoint)
      .eq(1)
      .should('have.value', '/group/endpoint');
    cy.findByLabelText(labelRedirectUrl).should('have.value', '');

    cy.makeSnapshot();
  });

  it('displays an error message when fields are not correctly formatted', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findByLabelText(labelBaseUrl).clear().type('invalid base url');
    cy.findByLabelText(labelTrustedClientAddresses).type(
      'invalid domain{enter}'
    );
    cy.findByLabelText(labelBlacklistClientAddresses).type(
      '127.0.0.1111{enter}'
    );
    cy.findByLabelText(labelBaseUrl).click();

    cy.contains(labelInvalidURL).should('exist');
    cy.contains(`invalid domain: ${labelInvalidIPAddress}`).should('exist');
    cy.contains(`127.0.0.1111: ${labelInvalidIPAddress}`).should('exist');

    cy.contains(labelSave).should('be.disabled');
    cy.contains(labelReset).should('be.enabled');

    cy.makeSnapshot();
  });

  it('saves the OpenID configuration when a field is modified and the "Save" button is clicked', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findByLabelText(labelBaseUrl)
      .clear()
      .type('http://localhost:8081/login');

    cy.contains(labelSave).should('be.enabled');

    cy.findByLabelText(labelContactGroup).click();

    cy.waitForRequest('@getContactGroups');

    cy.findAllByText('Contact Group 2').eq(0).click();

    cy.findAllByLabelText(labelGroupValue).eq(0).type('groupValue');

    cy.contains(labelSave).click();

    cy.waitForRequest('@updateOpenidConfiguration').then(({ request }) => {
      expect(request.body).to.deep.equal(
        JSON.stringify({
          ...retrievedOpenidConfiguration,
          base_url: 'http://localhost:8081/login',
          groups_mapping: {
            ...retrievedOpenidConfiguration.groups_mapping,
            relations: [{ contact_group_id: 2, group_value: 'groupValue' }]
          },
          redirect_url: null
        })
      );
    });

    cy.makeSnapshot();
  });

  it('resets the openid configuration when a field is modified and the "Reset" button is clicked', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findByLabelText(labelBaseUrl)
      .clear()
      .type('http://localhost:8081/login');

    cy.contains(labelReset).click();

    cy.contains(labelResetTheForm).should('be.visible');
    cy.contains(labelDoYouWantToResetTheForm).should('be.visible');
    cy.findAllByLabelText(labelReset).eq(1).click();

    cy.findByLabelText(labelBaseUrl).should(
      'have.value',
      'https://localhost:8080'
    );

    cy.makeSnapshot();
  });

  it('enables the "Save" button when an "Auto import" text field is cleared and the "Enable auto import" switch is unchecked', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findByLabelText(labelEnableAutoImport).click();

    cy.findByLabelText(labelEmailAttributePath).clear();

    cy.contains(labelSave).should('be.disabled');

    cy.findByLabelText(labelEnableAutoImport).click();

    cy.contains(labelSave).should('be.enabled');

    cy.makeSnapshot();
  });

  const testCases = [
    [
      'contact template',
      '@getContactTemplates',
      labelContactTemplate,
      'Contact Template 2'
    ],
    ['access group', '@getAccessGroups', labelAclAccessGroup, 'Access Group 2'],
    ['contact group', '@getContactGroups', labelContactGroup, 'Contact Group 2']
  ];

  testCases.forEach(([label, requestName, inputLabel, value]) => {
    it(`updates the ${label} field when an option is selected from the retrieved options`, () => {
      cy.waitForRequest('@getOpenidConfiguration');

      unfoldPanels();

      cy.findByLabelText(inputLabel).should('exist');

      cy.findByLabelText(labelEnableAutoImport).click();
      cy.findByLabelText(inputLabel).click();

      cy.waitForRequest(requestName).then(({ request }) => {
        expect(request.url.search).to.equal(
          `?page=1&sort_by=${encodeURIComponent(
            '{"name":"ASC"}'
          )}&search=${encodeURIComponent('{"$and":[]}')}`
        );
      });

      cy.findByText(value).click();

      cy.findAllByLabelText(inputLabel).eq(0).should('have.value', value);
    });
  });

  it('disables the save button when the "Groups mapping" custom endpoint field is cleared', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findByLabelText(labelBaseUrl)
      .clear()
      .type('http://localhost:8081/login');

    cy.contains(labelSave).should('be.enabled');

    cy.findAllByLabelText(labelDefineYourEndpoint).eq(-1).clear();

    cy.contains(labelSave).should('be.disabled');
  });

  it('adds a new contact group relation row when the first relation row is valid', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findByLabelText(labelContactGroup).click();

    cy.waitForRequest('@getContactGroups');

    cy.findByText('Contact Group 2').click();
    cy.findByLabelText(labelGroupValue).type('groupValue');

    cy.findAllByLabelText(labelContactGroup)
      .eq(0)
      .should('have.value', 'Contact Group 2');

    cy.findAllByLabelText(labelGroupValue).should('have.length', 2);
    cy.findAllByLabelText(labelContactGroup).should('have.length', 2);

    cy.makeSnapshot();
  });

  it('displays the "custom endpoint" field if the option "Other" in the radio button is selected', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findAllByLabelText(labelDefineYourEndpoint).should('have.length', 2);
    cy.findAllByLabelText(labelOther).eq(0).click();

    cy.findAllByLabelText(labelDefineYourEndpoint).should('have.length', 3);

    cy.makeSnapshot();
  });

  it('displays a new input field if a user types a text in the latest element of the field "condition value" ', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findAllByLabelText(labelConditionValue).should('have.length', 2);
    cy.findAllByLabelText(labelConditionValue).eq(1).type('some text');

    cy.findAllByLabelText(labelConditionValue).should('have.length', 3);

    cy.makeSnapshot();
  });

  it('displays a new Delete icon if a user types a text in the latest element of the field "condition value" ', () => {
    cy.waitForRequest('@getOpenidConfiguration');

    unfoldPanels();

    cy.findAllByLabelText(labelDeleteRelation).should('have.length', 1);
    cy.findAllByLabelText(labelConditionValue).eq(1).type('some text');
    cy.findAllByLabelText(labelDeleteRelation).should('have.length', 2);

    cy.makeSnapshot();
  });
});
