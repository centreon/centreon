import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import VaultConfiguration from './VaultConfiguration';
import { vaultConfigurationEndpoint } from './api/endpoints';
import {
  labelAddressIsNotAnUrl,
  labelFormWillBeCleared,
  labelPort,
  labelPortExpectedAtMost,
  labelPortMustStartFrom1,
  labelReset,
  labelResetConfiguration,
  labelRoleID,
  labelRootPath,
  labelSave,
  labelSecretID,
  labelVaultAddress,
  labelVaultConfiguration,
  labelVaultConfigurationUpdate
} from './translatedLabels';

const initialize = (): void => {
  cy.interceptAPIRequest({
    method: Method.GET,
    path: `./api/latest${vaultConfigurationEndpoint}`,
    alias: 'getVaultConfiguration',
    response: {
      id: 1,
      vault_id: 1,
      address: 'localhost',
      port: 1024,
      root_path: '/path',
      role_id: 'role'
    }
  });

  cy.interceptAPIRequest({
    method: Method.PUT,
    path: `./api/latest${vaultConfigurationEndpoint}`,
    alias: 'putVaultConfiguration',
    statusCode: 204
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <SnackbarProvider>
          <VaultConfiguration />
        </SnackbarProvider>
      </TestQueryProvider>
    )
  });
};

describe('Vault configuration', () => {
  it('displays an error when the URL field is not correctly formatted', () => {
    initialize();

    cy.waitForRequest('@getVaultConfiguration');

    cy.findByLabelText(labelVaultAddress).clear().type('127.0.');
    cy.findByLabelText(labelVaultAddress).blur();

    cy.contains(labelAddressIsNotAnUrl).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays an error when the port field is greater than 65535', () => {
    initialize();

    cy.waitForRequest('@getVaultConfiguration');

    cy.findByLabelText(labelPort).clear().type('1');
    cy.findByLabelText(labelPort).blur();

    cy.contains(labelPortMustStartFrom1).should('be.visible');

    cy.findByLabelText(labelPort).clear().type('4526121');
    cy.findByLabelText(labelPort).blur();

    cy.contains(labelPortExpectedAtMost).should('be.visible');

    cy.makeSnapshot();
  });

  it('sanitizes the URL when the field receives an URL that starts with a protocol', () => {
    initialize();

    cy.waitForRequest('@getVaultConfiguration');

    cy.findByLabelText(labelVaultAddress)
      .clear()
      .type('http://example.com', { delay: 0 });
    cy.findByLabelText(labelVaultAddress).blur();

    cy.findByLabelText(labelVaultAddress).should('have.value', 'example.com');

    cy.makeSnapshot();
  });

  it('sanitizes URL and port field when URL field receives an URL and a port', () => {
    initialize();

    cy.waitForRequest('@getVaultConfiguration');

    cy.findByLabelText(labelVaultAddress)
      .clear()
      .type('http://example.com:1', { delay: 0 });
    cy.findByLabelText(labelVaultAddress).blur();

    cy.findByLabelText(labelVaultAddress).should('have.value', 'example.com');
    cy.findByLabelText(labelPort).should('have.value', '1');

    cy.makeSnapshot();
  });

  it('displays the retrieved Vault configuration', () => {
    initialize();

    cy.waitForRequest('@getVaultConfiguration');

    cy.contains(labelVaultConfiguration).should('be.visible');
    cy.findByLabelText(labelVaultAddress).should('have.value', 'localhost');
    cy.findByLabelText(labelPort).should('have.value', '1024');
    cy.findByLabelText(labelRootPath).should('have.value', '/path');
    cy.findByLabelText(labelRoleID).should('have.value', 'role');
    cy.findByLabelText(labelSecretID).should('have.value', '');

    cy.contains(labelReset).should('be.disabled');
    cy.contains(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('sends the updated Vault configuration', () => {
    initialize();

    cy.waitForRequest('@getVaultConfiguration');

    cy.findByLabelText(labelVaultAddress)
      .clear()
      .type('http://example.com', { delay: 0 });
    cy.findByLabelText(labelVaultAddress).blur();

    cy.findByLabelText(labelVaultAddress).should('have.value', 'example.com');

    cy.findByLabelText(labelSecretID).type('Secret');

    cy.contains(labelSave).click();

    cy.waitForRequest('@putVaultConfiguration').then(({ request }) => {
      expect(request.body).equal(
        '{"address":"example.com","port":1024,"root_path":"/path","role_id":"role","secret_id":"Secret"}'
      );
    });

    cy.contains(labelVaultConfigurationUpdate).should('be.visible');

    cy.contains(labelReset).should('be.disabled');
    cy.contains(labelSave).should('be.disabled');

    cy.findByLabelText(labelSecretID).should('have.value', '');
    cy.findByLabelText(labelVaultAddress).should('have.value', 'example.com');

    cy.makeSnapshot();
  });

  it('resets the form when the configuration has changed and the reset button is clicked', () => {
    initialize();

    cy.waitForRequest('@getVaultConfiguration');

    cy.findByLabelText(labelVaultAddress)
      .clear()
      .type('http://example.com', { delay: 0 });
    cy.findByLabelText(labelVaultAddress).blur();

    cy.findByLabelText(labelVaultAddress).should('have.value', 'example.com');

    cy.findByLabelText(labelSecretID).type('Secret');

    cy.contains(labelReset).click();

    cy.contains(labelFormWillBeCleared).should('be.visible');
    cy.contains(labelResetConfiguration).should('be.visible');

    cy.findByLabelText(labelReset).click();

    cy.findByLabelText(labelSecretID).should('have.value', '');
    cy.findByLabelText(labelVaultAddress).should('have.value', 'localhost');

    cy.makeSnapshot();
  });
});
