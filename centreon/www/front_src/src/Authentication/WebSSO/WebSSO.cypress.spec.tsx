import { Method, TestQueryProvider } from '@centreon/ui';

import {
  labelDoYouWantToResetTheForm,
  labelReset,
  labelResetTheForm,
  labelSave
} from '../Local/translatedLabels';
import { authenticationProvidersEndpoint } from '../api/endpoints';
import { Provider } from '../models';
import { labelMixed } from '../shared/translatedLabels';
import { labelActivation } from '../translatedLabels';

import { retrievedWebSSOConfiguration } from './defaults';
import {
  labelBlacklistClientAddresses,
  labelDefineWebSSOConfiguration,
  labelEnableWebSSOAuthentication,
  labelInvalidIPAddress,
  labelInvalidRegex,
  labelLoginHeaderAttributeName,
  labelPatternMatchLogin,
  labelPatternReplaceLogin,
  labelTrustedClientAddresses,
  labelWebSSOOnly
} from './translatedLabels';

import WebSSOConfigurationForm from '.';

const initialize = (): void => {
  cy.viewport('macbook-15');

  cy.interceptAPIRequest({
    alias: 'getWebSSOConfiguration',
    method: Method.GET,
    path: authenticationProvidersEndpoint(Provider.WebSSO),
    response: retrievedWebSSOConfiguration
  });

  cy.interceptAPIRequest({
    alias: 'getWebSSOConfiguration',
    method: Method.GET,
    path: authenticationProvidersEndpoint(Provider.WebSSO),
    response: retrievedWebSSOConfiguration
  });

  cy.interceptAPIRequest({
    alias: 'putWebSSOConfiguration',
    method: Method.PUT,
    path: authenticationProvidersEndpoint(Provider.WebSSO)
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <WebSSOConfigurationForm />
      </TestQueryProvider>
    )
  });
};

describe('Web SSO configuration form', () => {
  beforeEach(initialize);

  it('saves the web SSO configuration when a field is modified and the "Save" button is clicked', () => {
    cy.waitForRequest('@getWebSSOConfiguration');

    cy.contains(labelActivation).should('be.visible');

    cy.findByLabelText(labelEnableWebSSOAuthentication).should('be.checked');

    cy.findByLabelText(labelLoginHeaderAttributeName).type('admin');

    cy.contains(labelActivation).click();

    cy.findByLabelText('save button').click();

    cy.waitForRequest('@putWebSSOConfiguration').then(({ request }) => {
      expect(request.body).to.equal(
        JSON.stringify({
          ...retrievedWebSSOConfiguration,
          login_header_attribute: 'admin'
        })
      );
    });

    cy.makeSnapshot();
  });

  it('displays the form', () => {
    cy.contains(labelDefineWebSSOConfiguration).should('be.visible');

    cy.waitForRequest('@getWebSSOConfiguration');

    cy.contains(labelActivation).should('be.visible');

    cy.findByLabelText(labelEnableWebSSOAuthentication).should('be.checked');

    cy.findByLabelText(labelWebSSOOnly).should('not.be.checked');
    cy.findByLabelText(labelMixed).should('be.checked');
    cy.findByLabelText(labelTrustedClientAddresses).should('be.visible');
    cy.findByLabelText(labelBlacklistClientAddresses).should('be.visible');
    cy.findAllByText('127.0.0.1').should('have.length', 2);
    cy.findByLabelText(labelLoginHeaderAttributeName).should('have.value', '');
    cy.findByLabelText(labelPatternMatchLogin).should('have.value', '');
    cy.findByLabelText(labelPatternReplaceLogin).should('have.value', '');

    cy.makeSnapshot();
  });

  it('displays an error message when fields are not correctly formatted', () => {
    cy.waitForRequest('@getWebSSOConfiguration');

    cy.findByLabelText(labelPatternMatchLogin).type(
      '{selectall}{backspace}invalid-pattern^'
    );
    cy.findByLabelText(labelPatternReplaceLogin).click();
    cy.contains(labelInvalidRegex).should('be.visible');

    cy.findByLabelText(labelPatternReplaceLogin).type(
      '{selectall}{backspace}$invalid-pattern'
    );
    cy.findByLabelText(labelTrustedClientAddresses).click();
    cy.findAllByText(labelInvalidRegex).should('have.length', 2);

    cy.findByLabelText(labelTrustedClientAddresses).type(
      'invalid domain{enter}'
    );
    cy.findByLabelText(labelBlacklistClientAddresses).click();
    cy.contains(`invalid domain: ${labelInvalidIPAddress}`).should(
      'be.visible'
    );

    cy.findByLabelText(labelBlacklistClientAddresses).type(
      '127.0.0.1111{enter}'
    );
    cy.findByLabelText(labelTrustedClientAddresses).click();
    cy.contains(`127.0.0.1111: ${labelInvalidIPAddress}`).should('be.visible');

    cy.findByTestId(labelSave).should('be.disabled');
    cy.findByText(labelReset).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('resets the web SSO configuration when a field is modified and the "Reset" button is clicked', () => {
    cy.waitForRequest('@getWebSSOConfiguration');

    cy.findByLabelText(labelLoginHeaderAttributeName).type('admin');

    cy.findByLabelText(labelLoginHeaderAttributeName).should(
      'have.value',
      'admin'
    );

    cy.findByText(labelReset).click();

    cy.contains(labelResetTheForm).should('be.visible');
    cy.contains(labelDoYouWantToResetTheForm).should('be.visible');
    cy.findAllByLabelText(labelReset).eq(1).click();

    cy.findByLabelText(labelLoginHeaderAttributeName).should('have.value', '');

    cy.makeSnapshot();
  });
});
