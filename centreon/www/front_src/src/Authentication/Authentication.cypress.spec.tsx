import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import { replace } from 'ramda';

import { Method, TestQueryProvider } from '@centreon/ui';

import { defaultPasswordSecurityPolicyAPI } from './Local/defaults';
import { retrievedOpenidConfiguration } from './Openid/defaults';
import {
  labelEnableOpenIDConnectAuthentication,
  labelOpenIDConnectConfiguration
} from './Openid/translatedLabels';
import { retrievedSAMLConfiguration } from './SAML/defaults';
import { labelSAMLConfiguration } from './SAML/translatedLabels';
import { retrievedWebSSOConfiguration } from './WebSSO/defaults';
import { labelWebSSOConfiguration } from './WebSSO/translatedLabels';
import { authenticationProvidersEndpoint } from './api/endpoints';
import { Provider } from './models';
import { labelRolesMapping } from './shared/translatedLabels';
import {
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from './translatedLabels';

import Authentication from '.';

dayjs.extend(duration);

const initializeAndMountAuthenticationPage = (): void => {
  cy.interceptAPIRequest({
    alias: 'getLocalAuthenticationConfiguration',
    method: Method.GET,
    path: replace('./', '**', authenticationProvidersEndpoint(Provider.Local)),
    response: defaultPasswordSecurityPolicyAPI
  });
  cy.interceptAPIRequest({
    alias: 'getOpendidConnectConfiguration',
    method: Method.GET,
    path: replace('./', '**', authenticationProvidersEndpoint(Provider.Openid)),
    response: retrievedOpenidConfiguration
  });
  cy.interceptAPIRequest({
    alias: 'getWebSSOConfiguration',
    method: Method.GET,
    path: replace('./', '**', authenticationProvidersEndpoint(Provider.WebSSO)),
    response: retrievedWebSSOConfiguration
  });
  cy.interceptAPIRequest({
    alias: 'getSAMLConfiguration',
    method: Method.GET,
    path: replace('./', '**', authenticationProvidersEndpoint(Provider.SAML)),
    response: retrievedSAMLConfiguration
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Authentication />
      </TestQueryProvider>
    )
  });

  cy.viewport('macbook-13');
};

describe('Authentication configuration', () => {
  beforeEach(() => {
    initializeAndMountAuthenticationPage();
  });

  it('displays the local authentication tab by default', () => {
    cy.waitForRequest('@getLocalAuthenticationConfiguration');

    cy.makeSnapshot();
  });

  it('displays the OpenID Connect configuration when the corresponding tab is clicked', () => {
    cy.waitForRequest('@getLocalAuthenticationConfiguration');

    cy.findAllByLabelText(labelOpenIDConnectConfiguration)
      .eq(0)
      .as('OpenidConnectTab');

    cy.get('@OpenidConnectTab').click();

    cy.waitForRequest('@getOpendidConnectConfiguration');

    cy.findByLabelText(labelEnableOpenIDConnectAuthentication).should('exist');
    cy.findByLabelText(labelGroupsMapping).click();
    cy.findByLabelText(labelRolesMapping).click();
    cy.findByLabelText(labelAutoImportUsers).click();
    cy.findByLabelText(labelAuthenticationConditions).click();
    cy.findByLabelText(labelIdentityProvider).click();

    cy.get('@OpenidConnectTab').scrollIntoView();

    cy.makeSnapshot();
  });

  it('displays the Web SSO configuration when the corresponding tab is clicked', () => {
    cy.findByText(labelWebSSOConfiguration).click();

    cy.waitForRequest('@getWebSSOConfiguration');

    cy.makeSnapshot();
  });

  it('displays the SAML configuration when the corresponding tab is clicked', () => {
    cy.findByText(labelSAMLConfiguration).click();

    cy.waitForRequest('@getSAMLConfiguration');

    cy.findByLabelText(labelGroupsMapping).click();
    cy.findByLabelText(labelRolesMapping).click();
    cy.findByLabelText(labelAutoImportUsers).click();
    cy.findByLabelText(labelAuthenticationConditions).click();
    cy.findByLabelText(labelIdentityProvider).click();

    cy.findAllByLabelText(labelSAMLConfiguration).eq(0).scrollIntoView();

    cy.makeSnapshot();
  });
});
