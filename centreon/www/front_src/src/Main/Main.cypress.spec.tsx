import { Method } from '@centreon/ui';

import { externalTranslationEndpoint } from '../App/endpoint';
import { providersConfigurationEndpoint } from '../Login/api/endpoint';
import {
  labelAlias,
  labelConnect,
  labelLogin,
  labelPassword
} from '../Login/translatedLabels';
import {
  platformFeaturesEndpoint,
  platformInstallationStatusEndpoint,
  platformVersionsEndpoint,
  userEndpoint
} from '../api/endpoint';

import Provider from './Provider';
import {
  retrievedProvidersConfiguration,
  retrievedTranslations,
  retrievedWeb
} from './testUtils';

const initialize = ({ page = '/' }): void => {
  cy.window().then((window) => window.history.pushState({}, '', page));
  cy.interceptAPIRequest({
    alias: 'platformInstallationStatus',
    method: Method.GET,
    path: platformInstallationStatusEndpoint,
    response: {
      has_upgrade_available: false,
      is_installed: true
    }
  });

  cy.interceptAPIRequest({
    alias: 'platformVersions',
    method: Method.GET,
    path: platformVersionsEndpoint,
    response: retrievedWeb
  });

  cy.interceptAPIRequest({
    alias: 'user',
    method: Method.GET,
    path: userEndpoint,
    response: { status: 403 },
    statusCode: 403
  });

  cy.interceptAPIRequest({
    alias: 'platformFeatures',
    method: Method.GET,
    path: platformFeaturesEndpoint,
    response: {
      feature_flags: {},
      is_cloud_platform: false
    }
  });

  cy.interceptAPIRequest({
    alias: 'translations',
    method: Method.GET,
    path: externalTranslationEndpoint,
    response: retrievedTranslations
  });

  cy.interceptAPIRequest({
    alias: 'providerConfigurations',
    method: Method.GET,
    path: providersConfigurationEndpoint,
    response: retrievedProvidersConfiguration
  });

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <Provider />
      </div>
    )
  });
};

describe('Main', () => {
  it('displays the public page when a public url is entered', () => {
    initialize({ page: '/public/dashboards/playlists/hash' });

    cy.waitForRequest('@platformVersions');
    cy.waitForRequest('@translations');

    cy.contains(labelLogin).should('not.exist');
    cy.contains('Cannot load module').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the login page by default', () => {
    initialize({});

    cy.waitForRequest('@platformInstallationStatus');
    cy.waitForRequest('@platformVersions');
    cy.waitForRequest('@user');
    cy.waitForRequest('@translations');
    cy.waitForRequest('@platformFeatures');
    cy.waitForRequest('@providerConfigurations');

    cy.contains(labelLogin).should('be.visible');
    cy.findByLabelText(labelAlias).should('be.visible');
    cy.findByLabelText(labelPassword).should('be.visible');
    cy.findByLabelText(labelConnect).should('be.visible');

    cy.makeSnapshot();
  });
});
