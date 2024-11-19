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
  labelCaCertificate,
  labelCancel,
  labelCertificate,
  labelClear,
  labelConfigurationServer,
  labelConnectionInitiatedByPoller,
  labelDNSIP,
  labelDelete,
  labelDeleteAgent,
  labelDeletePoller,
  labelHostConfigurations,
  labelInvalidFilename,
  labelName,
  labelOTLPReceiver,
  labelPoller,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
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
        otel_public_certificate: 'coucou',
        otel_ca_certificate: 'coucou',
        otel_private_key: 'coucou',
        conf_server_port: 9090,
        conf_certificate: 'coucou',
        conf_private_key: 'coucou'
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
    cy.get(`button[title="${labelDelete}"]`).should('have.length', 10);

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

    cy.findAllByTitle(labelDelete).eq(0).click();

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

  it('deletes an when the corresponding action icon is clicked and the corresponding button is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.findAllByTitle(labelDelete).eq(0).click();

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

  it('deletes an agent when the corresponding icon is clicked and the corresponding is clicked', () => {
    initialize({});

    cy.waitForRequest('@getAgentConfigurations').then(({ request }) => {
      expect(decodeURIComponent(request.url.search)).equals(
        '?page=1&limit=10&sort_by={"name":"asc"}&search={"$or":[{"name":{"$rg":""}}]}'
      );
    });

    cy.findByTestId('Expand 0').click();

    cy.contains('poller 1').should('be.visible');
    cy.contains('poller 2').should('be.visible');

    cy.findAllByTitle(labelDelete).eq(1).click();

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
    cy.findByLabelText(labelPublicCertificate).type('test.cert');
    cy.findByLabelText(labelCaCertificate).type('test.crt');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('test.key');
    cy.findAllByLabelText(labelPrivateKey).eq(1).type('test.key');
    cy.findByLabelText(labelCertificate).type('test.cer');
    cy.findByLabelText(labelCertificate).blur();

    cy.findByLabelText(labelAgentType).should('have.value', 'Telegraf');
    cy.findAllByText(labelRequired).should('have.length', 2);
    cy.findAllByText(labelInvalidFilename).should('have.length', 5);
    cy.findAllByText(labelPortExpectedAtMost).should('have.length', 1);
    cy.contains(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('discards the form when the cancel button is clicked and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelName).type('agent');

    cy.contains(labelCancel).click();
    cy.contains('Discard').click();

    cy.findByLabelText(labelName).should('not.exist');

    cy.makeSnapshot();
  });

  it('resolves the form when the cancel button is clicked and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelName).type('agent');

    cy.contains(labelCancel).click();
    cy.contains('Resolve').click();

    cy.contains(labelAdd).should('exist');
    cy.contains('Resolve').should('not.exist');

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
    cy.findByLabelText(labelPublicCertificate).type('test');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('test');
    cy.findAllByLabelText(labelPrivateKey).eq(1).type('test');
    cy.findByLabelText(labelCertificate).type('test');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).equal(
        '{"name":"agent","type":"telegraf","configuration":{"otel_private_key":"test","otel_ca_certificate":null,"otel_public_certificate":"test","conf_certificate":"test","conf_private_key":"test","conf_server_port":1234},"poller_ids":[1]}'
      );
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
      expect(request.body).equal(
        '{"name":"agent updated","type":"telegraf","configuration":{"otel_private_key":"coucou","otel_ca_certificate":"coucou","otel_public_certificate":"coucou","conf_certificate":"coucou","conf_private_key":"coucou","conf_server_port":9090},"poller_ids":[1,2]}'
      );
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
      expect(request.body).equal(
        '{"name":"agent updated","type":"telegraf","configuration":{"otel_private_key":"coucou","otel_ca_certificate":"coucou","otel_public_certificate":"coucou","conf_certificate":"coucou","conf_private_key":"coucou","conf_server_port":9090},"poller_ids":[1,2]}'
      );
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
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('filename.key').blur();

    cy.contains(labelRequired).should('be.visible');
    cy.contains(labelInvalidFilename).should('be.visible');

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="0"]').click();

    cy.contains(labelHostConfigurations).should('not.exist');
    cy.contains(labelRequired).should('not.exist');
    cy.contains(labelInvalidFilename).should('not.exist');
    cy.findByLabelText(labelName).should('have.value', 'My agent');
    cy.contains(labelConfigurationServer).should('be.visible');

    cy.makeSnapshot();
  });

  it('does not validate the form when there is no host configuration', () => {
    initialize({});

    cy.contains(labelAdd).click();
    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.findByLabelText(labelConnectionInitiatedByPoller).click();
    cy.findByTestId('delete-host-configuration-0').click();
    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPublicCertificate).type('test');
    cy.findByLabelText(labelCaCertificate).type('test');
    cy.findByLabelText(labelPrivateKey).type('key');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();

    cy.contains(labelSave).should('be.disabled');

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
    cy.findByLabelText(labelPublicCertificate).type('test');
    cy.findByLabelText(labelCaCertificate).type('test');
    cy.findByLabelText(labelPrivateKey).type('key');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(JSON.parse(request.body)).deep.equal({
        name: 'My agent',
        type: 'centreon-agent',
        poller_ids: [1],
        configuration: {
          is_reverse: false,
          otel_ca_certificate: 'test',
          otel_public_certificate: 'test',
          otel_private_key: 'key',
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
    cy.findByTestId(labelPort).find('input').should('have.value', '4317');

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
    cy.findByTestId(labelPort).find('input').should('have.value', '8');

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
    cy.findByLabelText(labelPublicCertificate).type('test');
    cy.findAllByLabelText(labelCaCertificate).eq(0).type('test');
    cy.findAllByLabelText(labelCaCertificate).eq(1).type('test');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('key');
    cy.findByLabelText(labelAddHost).click();
    cy.contains('central').click();
    cy.findByLabelText(labelCertificate).type('test');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(JSON.parse(request.body)).deep.equal({
        name: 'My agent',
        type: 'centreon-agent',
        poller_ids: [1],
        configuration: {
          is_reverse: true,
          otel_ca_certificate: 'test',
          otel_public_certificate: 'test',
          otel_private_key: 'key',
          hosts: [
            {
              address: '127.0.0.2',
              port: 4317,
              poller_ca_name: 'test',
              poller_ca_certificate: 'test'
            }
          ]
        }
      });
    });

    cy.contains(labelAgentConfigurationCreated).should('be.visible');

    cy.makeSnapshot();
  });
});
