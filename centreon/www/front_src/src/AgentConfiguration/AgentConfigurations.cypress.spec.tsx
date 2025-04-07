import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { labelPortExpectedAtMost } from '../VaultConfiguration/translatedLabels';
import AgentConfigurationPage from './Page';
import {
  agentConfigurationPollersEndpoint,
  getAgentConfigurationEndpoint,
  getAgentConfigurationsEndpoint,
  getPollerAgentEndpoint,
  hostsConfigurationEndpoint,
  pollersEndpoint
} from './api/endpoints';
import {
  labelAction,
  labelAdd,
  labelAddAHost,
  labelAddAgentConfiguration,
  labelAddHost,
  labelAgentConfigurationCreated,
  labelAgentConfigurationUpdated,
  labelAgentType,
  labelAgentTypes,
  labelAgentsConfigurations,
  labelCACommonName,
  labelCaCertificate,
  labelCancel,
  labelClear,
  labelConfigurationServer,
  labelConnectionInitiatedByPoller,
  labelDNSIP,
  labelDelete,
  labelDeleteAgent,
  labelDeletePoller,
  labelHostConfigurations,
  labelInvalidExtension,
  labelInvalidPath,
  labelName,
  labelOTLPReceiver,
  labelPoller,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelRelativePathAreNotAllowed,
  labelRequired,
  labelSave,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const initialize = ({ isListingEmpty = false }) => {
  const store = createStore();

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  if (isListingEmpty) {
    cy.interceptAPIRequest({
      alias: 'getEmptyAgentConfigurations',
      method: Method.GET,
      path: `./api/latest${getAgentConfigurationsEndpoint}**`,
      response: { result: [], meta: { limit: 10, page: 1, total: 0 } }
    });
  } else {
    cy.fixture('AgentConfigurations/listing.json').then((listing): void => {
      cy.interceptAPIRequest({
        alias: 'getAgentConfigurations',
        method: Method.GET,
        path: `./api/latest${getAgentConfigurationsEndpoint}?page=1**`,
        response: listing
      });
    });
  }

  cy.fixture('ACC/pollers-vmware.json').then((listing): void => {
    cy.interceptAPIRequest({
      alias: 'getFilterPollers',
      method: Method.GET,
      path: `./api/latest${pollersEndpoint}**`,
      response: listing
    });
  });

  cy.fixture('ACC/pollers-vmware.json').then((listing): void => {
    cy.interceptAPIRequest({
      alias: 'getPollers',
      method: Method.GET,
      path: `./api/latest${agentConfigurationPollersEndpoint}**`,
      response: listing
    });
  });

  cy.interceptAPIRequest({
    alias: 'deleteAgent',
    method: Method.DELETE,
    path: `./api/latest${getPollerAgentEndpoint({ agentId: 0 })}`
  });

  cy.interceptAPIRequest({
    alias: 'deletePoller',
    method: Method.DELETE,
    path: `./api/latest${getPollerAgentEndpoint({ agentId: 0, pollerId: 1 })}`
  });

  cy.interceptAPIRequest({
    alias: 'postAgentConfiguration',
    method: Method.POST,
    path: `./api/latest${getAgentConfigurationsEndpoint}`
  });

  cy.interceptAPIRequest({
    alias: 'patchAgentConfiguration',
    method: Method.PUT,
    path: `./api/latest${getAgentConfigurationEndpoint(1)}`
  });

  cy.interceptAPIRequest({
    alias: 'getAgentConfiguration',
    method: Method.GET,
    path: `./api/latest${getAgentConfigurationEndpoint(1)}`,
    response: {
      id: 1,
      name: 'agent',
      type: 'telegraf',
      pollers: [
        {
          id: 1,
          name: 'poller 1'
        },
        {
          id: 2,
          name: 'poller 2'
        }
      ],
      configuration: {
        otel_server_address: '127.0.0.1',
        otel_server_port: 8080,
        otel_public_certificate: 'test.cer',
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'test.key',
        conf_server_port: 9090,
        conf_certificate: '/sub/test.crt',
        conf_private_key: 'test.crt'
      }
    }
  });

  cy.interceptAPIRequest({
    alias: 'getHosts',
    path: `./api/latest${hostsConfigurationEndpoint}**`,
    method: Method.GET,
    response: {
      result: [{ id: 1, name: 'central', address: '127.0.0.2' }],
      meta: { limit: 10, page: 1, total: 1 }
    }
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <SnackbarProvider>
            <div style={{ height: '100vh', display: 'grid' }}>
              <AgentConfigurationPage />
            </div>
          </SnackbarProvider>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('Agent configurations', () => {
  it('displays a welcome label when the listing is empty', () => {
    initialize({ isListingEmpty: true });

    cy.waitForRequest('@getEmptyAgentConfigurations');

    cy.contains(labelAgentsConfigurations).should('be.visible');
    cy.contains(labelWelcomeToTheAgentsConfigurationPage).should('be.visible');
    cy.get('button').contains(labelAddAgentConfiguration).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the listing', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.contains(labelAgentsConfigurations).should('be.visible');
    cy.get('button').contains(labelAdd).should('be.visible');
    cy.contains(labelName).should('be.visible');
    cy.contains(labelAgentType).should('be.visible');
    cy.contains(labelPoller).should('be.visible');
    cy.contains(labelAction).should('be.visible');
    cy.contains('AC 0').should('be.visible');
    cy.contains('Telegraf').should('be.visible');
    cy.contains('2 pollers').should('be.visible');
    cy.contains('0 poller').should('be.visible');
    cy.get(`button[data-testid="${labelDelete}"]`).should('have.length', 10);

    cy.makeSnapshot();
  });

  it('sends a request when a column sort is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.contains(labelName).click();

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"desc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.makeSnapshot();
  });

  it('sends a request when the search and filters are updated', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.findAllByTestId('Search').eq(0).type('My agent');
    cy.findByLabelText('Filters').click();
    cy.findByLabelText(labelAgentTypes).click({ force: true });
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelPollers).click({ force: true });

    cy.waitForRequest('@getFilterPollers');

    cy.contains('poller6').click();

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$and":[{"$or":[{"name":{"$rg":"My agent"}}]},{"$and":[{"$or":[{"type":{"$in":["telegraf"]}}]},{"$or":[{"poller.id":{"$in":[6]}}]}]}]}'
      );
    });

    cy.makeSnapshot();
  });

  it('deletes a selected poller when the icon is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.findByLabelText('Filters').click();
    cy.findByLabelText(labelPollers).click({ force: true });

    cy.waitForRequest('@getFilterPollers');

    cy.contains('poller6').click();

    cy.findByTestId('CancelIcon').click();
    cy.findByLabelText('Filters').click();

    cy.contains('poller6').should('not.exist');

    cy.makeSnapshot();
  });

  it('deletes a selected agent type when the icon is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.findByLabelText('Filters').click();
    cy.findByLabelText(labelAgentTypes).click({ force: true });
    cy.get('[data-option-index="1"]').click();

    cy.findByTestId('CancelIcon').click();
    cy.findByLabelText('Filters').click();

    cy.contains('Centreon Monitoring Agent').should('not.exist');

    cy.makeSnapshot();
  });

  it('clears filters when filters are populated and the corresponding button is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.findAllByTestId('Search').eq(0).type('My agent');
    cy.findByLabelText('Filters').click();
    cy.findByLabelText(labelAgentTypes).click({ force: true });
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelPollers).click({ force: true });

    cy.waitForRequest('@getFilterPollers');

    cy.contains('poller6').click();
    cy.contains(labelClear).click({ force: true });
    cy.contains('poller6').should('not.exist');
    cy.contains('Centreon Monitoring Agent').should('not.exist');

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":"My agent"}}]}'
      );
    });

    cy.makeSnapshot();
  });

  it('cancels a poller deletion when the corresponding icon is clicked and the corresponding is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.get(`button[data-testid="${labelDelete}"]`).first().click();

    cy.contains(labelDeleteAgent).should('be.visible');
    cy.contains('You are going to delete the').should('be.visible');
    cy.contains('AC 0').should('be.visible');
    cy.contains(
      'agent configuration. All configuration parameters for this agent will be deleted. This action cannot be undone.'
    ).should('be.visible');

    cy.contains(labelCancel).click();

    cy.contains(labelDeleteAgent).should('not.exist');

    cy.makeSnapshot();
  });

  it('deletes an agent when the corresponding action icon is clicked and the corresponding button is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.get(`button[data-testid="${labelDelete}"]`).first().click();

    cy.contains(labelDeleteAgent).should('be.visible');
    cy.contains('You are going to delete the').should('be.visible');
    cy.contains('AC 0').should('be.visible');
    cy.contains(
      'agent configuration. All configuration parameters for this agent will be deleted. This action cannot be undone.'
    ).should('be.visible');

    cy.contains(/^Delete$/).click();

    cy.waitForRequest('@deleteAgent');

    cy.contains(labelDeleteAgent).should('not.exist');
    cy.waitForRequest('@getAgentConfigurations');

    cy.makeSnapshot();
  });

  it('deletes a poller when the corresponding icon is clicked and the corresponding is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.findByTestId('Expand 0').click();

    cy.contains('poller 1').should('be.visible');
    cy.contains('poller 2').should('be.visible');

    cy.get(`button[data-testid="${labelDelete}"]`).eq(1).click();

    cy.contains(labelDeletePoller).should('be.visible');
    cy.contains('You are going to delete the').should('be.visible');
    cy.contains('AC 0').should('be.visible');
    cy.contains(
      'agent configuration. All configuration parameters for this poller will be deleted. This action cannot be undone.'
    ).should('be.visible');

    cy.contains(/^Delete$/).click();

    cy.waitForRequest('@deletePoller');

    cy.contains(labelDeleteAgent).should('not.exist');
    cy.waitForRequest('@getAgentConfigurations');

    cy.makeSnapshot();
  });
});

describe('Agent configurations modal', () => {
  it('does not validate the form when fields contain errors', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.contains(labelAdd).should('be.visible');

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="0"]').click();
    cy.findByLabelText(labelName).focus();
    cy.findByLabelText(labelName).blur();
    cy.findByLabelText(labelPollers).focus();
    cy.findByLabelText(labelPollers).blur();
    cy.findAllByLabelText(labelPort).eq(0).type('123456');
    cy.findAllByLabelText(labelPublicCertificate).eq(0).type('./test.cer');
    cy.findByLabelText(labelCaCertificate).type('//test.crt');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('test.abc');
    cy.findAllByLabelText(labelPrivateKey).eq(1).type('test.xyz');
    cy.findByLabelText(labelCaCertificate).type('test.cer');
    cy.findByLabelText(labelCaCertificate).blur();

    cy.findByLabelText(labelAgentType).should('have.value', 'Telegraf');
    cy.findAllByText(labelRequired).should('have.length', 2);
    cy.findAllByText(labelPortExpectedAtMost).should('have.length', 1);
    cy.findAllByText(labelRelativePathAreNotAllowed).should('have.length', 1);
    cy.findAllByText(labelInvalidPath).should('have.length', 1);
    cy.findAllByText(labelInvalidExtension).should('have.length', 2);
    cy.contains(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('leaves the form when the "Leave" button of the popup is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelName).type('agent');

    cy.contains(labelCancel).click();
    cy.contains('Leave').click();

    cy.findByLabelText(labelName).should('not.exist');

    cy.makeSnapshot();
  });

  it('backs to the form when the "Stay" button of the popup is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelName).type('agent');

    cy.contains(labelCancel).click();
    cy.contains('Stay').click();

    cy.contains(labelAdd).should('exist');
    cy.contains('Stay').should('not.exist');

    cy.makeSnapshot();
  });

  it('sends the form when fields are valid and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="0"]').click();
    cy.findByLabelText(labelName).type('agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();
    cy.findAllByLabelText(labelPort).eq(0).clear().type('1234');
    cy.findAllByLabelText(labelPublicCertificate).eq(0).type('test.crt');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('/sub/test.key');
    cy.findAllByLabelText(labelPrivateKey).eq(1).type('/sub/test.key');
    cy.findAllByLabelText(labelPublicCertificate).eq(1).type('test.cer');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).to.deep.equal({
        name: 'agent',
        type: 'telegraf',
        configuration: {
          otel_private_key: '/sub/test.key',
          otel_ca_certificate: null,
          otel_public_certificate: 'test.crt',
          conf_certificate: 'test.cer',
          conf_private_key: '/sub/test.key',
          conf_server_port: 1234
        },
        poller_ids: [1]
      });
    });

    cy.contains(labelAgentConfigurationCreated).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends the form when a listing row is clicked, fields are valid and the corresponding button is clicked', () => {
    initialize({});

    cy.contains('AC 1').click();

    cy.waitForRequest('@getAgentConfiguration');

    cy.findByLabelText(labelName).type(' updated');

    cy.contains(labelSave).click();

    cy.waitForRequest('@patchAgentConfiguration').then(({ request }) => {
      expect(request.body).to.deep.equal({
        name: 'agent updated',
        type: 'telegraf',
        configuration: {
          otel_private_key: 'test.key',
          otel_ca_certificate: 'test.crt',
          otel_public_certificate: 'test.cer',
          conf_certificate: '/sub/test.crt',
          conf_private_key: 'test.crt',
          conf_server_port: 9090
        },
        poller_ids: [1, 2]
      });
    });

    cy.contains(labelAgentConfigurationUpdated).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends the form when a listing row is clicked, fields are valid, the cancel button is clicked and the corresponding button is clicked', () => {
    initialize({});

    cy.contains('AC 1').click();

    cy.findByLabelText(labelName).type(' updated');
    cy.contains(labelCancel).click();
    cy.findByTestId('confirm').click();

    cy.waitForRequest('@patchAgentConfiguration').then(({ request }) => {
      expect(request.body).to.deep.equal({
        name: 'agent updated',
        type: 'telegraf',
        configuration: {
          otel_private_key: 'test.key',
          otel_ca_certificate: 'test.crt',
          otel_public_certificate: 'test.cer',
          conf_certificate: '/sub/test.crt',
          conf_private_key: 'test.crt',
          conf_server_port: 9090
        },
        poller_ids: [1, 2]
      });
    });

    cy.contains(labelAgentConfigurationUpdated).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the CMA form when the CMA agent type is selected', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();

    cy.findByLabelText(labelConnectionInitiatedByPoller).should(
      'not.be.checked'
    );
    cy.contains(labelOTLPReceiver).should('be.visible');
    cy.findByLabelText(labelPublicCertificate).should('have.value', '');
    cy.findAllByLabelText(labelCaCertificate).eq(0).should('have.value', '');
    cy.findByLabelText(labelPrivateKey).should('have.value', '');

    cy.makeSnapshot();
  });

  it('resets the form when a different agent type is selected', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPublicCertificate).type('something').clear();
    cy.findByLabelText(labelPublicCertificate).blur();
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('filename.abc').blur();

    cy.contains(labelRequired).should('be.visible');
    cy.contains(labelInvalidExtension).should('be.visible');

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="0"]').click();

    cy.contains(labelHostConfigurations).should('not.exist');
    cy.contains(labelRequired).should('not.exist');
    cy.contains(labelInvalidExtension).should('not.exist');
    cy.findByLabelText(labelName).should('have.value', 'My agent');
    cy.contains(labelConfigurationServer).should('be.visible');

    cy.makeSnapshot();
  });

  it('does not validate the form when there is no host configuration', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();

    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();
    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPublicCertificate).type('test.crt');
    cy.findByLabelText(labelCaCertificate).type('test.crt');
    cy.findByLabelText(labelPrivateKey).type('test.key');

    cy.findByLabelText(labelConnectionInitiatedByPoller).click();

    cy.contains(labelSave).scrollIntoView().should('be.disabled');

    cy.makeSnapshot();
  });

  it('validates the form when fields are filled and the reverse switch is unchecked', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();

    cy.findByLabelText(labelPublicCertificate).type('/certificate/test.crt');
    cy.findByLabelText(labelCaCertificate).type('test.crt');
    cy.findByLabelText(labelPrivateKey).type('privateKey.key');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).deep.equal({
        name: 'My agent',
        type: 'centreon-agent',
        poller_ids: [1],
        configuration: {
          is_reverse: false,
          otel_ca_certificate: 'test.crt',
          otel_public_certificate: '/certificate/test.crt',
          otel_private_key: 'privateKey.key',
          hosts: []
        }
      });
    });

    cy.makeSnapshot();
  });

  it('configures the host address and port when a host is selected', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelConnectionInitiatedByPoller).click();
    cy.findByLabelText(labelAddHost).click();

    cy.waitForRequest('@getHosts');

    cy.contains('central').click();

    cy.findByLabelText(labelDNSIP).should('have.value', '127.0.0.2');
    cy.findByTestId('portInput').should('have.value', '4317');

    cy.makeSnapshot();
  });

  it('splits the address and the port when a full address is pasted in the address field', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelConnectionInitiatedByPoller).click();
    cy.findByLabelText(labelDNSIP).type('127.0.0.1:8');
    cy.findByLabelText(labelDNSIP).should('have.value', '127.0.0.1');
    cy.findByTestId('portInput').should('have.value', '8');

    cy.makeSnapshot();
  });

  it('adds a new host configuration when the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelConnectionInitiatedByPoller).click();

    cy.contains(labelAddAHost).click();

    cy.findByTestId('delete-host-configuration-1').should('exist');

    cy.makeSnapshot();
  });

  it('removes a host configuration when the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelConnectionInitiatedByPoller).click();

    cy.contains(labelAddAHost).click();

    cy.findByTestId('delete-host-configuration-1').click();

    cy.findByTestId('delete-host-configuration-1').should('not.exist');

    cy.makeSnapshot();
  });

  it('sends the CMA agent type when the form is valid and the save button is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelConnectionInitiatedByPoller).click();
    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();
    cy.findByLabelText(labelPublicCertificate).type('/test.cer');
    cy.findAllByLabelText(labelCaCertificate).eq(0).type('test.crt');
    cy.findAllByLabelText(labelCaCertificate).eq(1).type('test.crt');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('private.key');
    cy.findByLabelText(labelAddHost).click();
    cy.contains('central').click();
    cy.findByLabelText(labelCACommonName).type('test.crt');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).deep.equal({
        name: 'My agent',
        type: 'centreon-agent',
        poller_ids: [1],
        configuration: {
          is_reverse: true,
          otel_ca_certificate: 'test.crt',
          otel_public_certificate: '/test.cer',
          otel_private_key: 'private.key',
          hosts: [
            {
              address: '127.0.0.2',
              port: 4317,
              poller_ca_name: 'test.crt',
              poller_ca_certificate: 'test.crt'
            }
          ]
        }
      });
    });

    cy.contains(labelAgentConfigurationCreated).should('be.visible');

    cy.makeSnapshot();
  });
});
