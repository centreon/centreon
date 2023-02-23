import { replace } from 'ramda';
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Method, TestQueryProvider } from '@centreon/ui';

import { authenticationProvidersEndpoint } from './api/endpoints';
import { Provider } from './models';
import { defaultPasswordSecurityPolicyAPI } from './Local/defaults';
import { retrievedOpenidConfiguration } from './Openid/defaults';
import { retrievedWebSSOConfiguration } from './WebSSO/defaults';
import { retrievedSAMLConfiguration } from './SAML/defaults';
import { labelOpenIDConnectConfiguration } from './Openid/translatedLabels';
import { labelWebSSOConfiguration } from './WebSSO/translatedLabels';
import { labelSAMLConfiguration } from './SAML/translatedLabels';
import {
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from './translatedLabels';
import { labelRolesMapping } from './shared/translatedLabels';

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

    cy.matchImageSnapshot();
  });

  it('displays the Openid Connect configuration when the corresponding tab is clicked', () => {

    cy.waitForRequest('@getLocalAuthenticationConfiguration');

    cy.findByText(labelOpenIDConnectConfiguration).click();

    cy.waitForRequest('@getOpendidConnectConfiguration');

    cy.findByText(labelGroupsMapping).click();
    cy.findByText(labelRolesMapping).click();
    cy.findByText(labelAutoImportUsers).click();
    cy.findByText(labelAuthenticationConditions).click();
    cy.findByText(labelIdentityProvider).click();

    cy.findByText(labelOpenIDConnectConfiguration).scrollIntoView();

    cy.matchImageSnapshot();
  });

  it('displays the Web SSO configuration tab when the "Web SSO configuration" tab is clicked', () => {
    cy.findByText(labelWebSSOConfiguration).click();

    cy.waitForRequest('@getWebSSOConfiguration');

    cy.matchImageSnapshot();
  });

  it('displays the SAML configuration tab when the "SAML configuration" tab is clicked', () => {
    cy.findByText(labelSAMLConfiguration).click();

    cy.waitForRequest('@getSAMLConfiguration');

    cy.findByText(labelGroupsMapping).click();
    cy.findByText(labelRolesMapping).click();
    cy.findByText(labelAutoImportUsers).click();
    cy.findByText(labelAuthenticationConditions).click();
    cy.findByText(labelIdentityProvider).click();

    cy.findByText(labelSAMLConfiguration).scrollIntoView();

    cy.matchImageSnapshot();
  });
});
