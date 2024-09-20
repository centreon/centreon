import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { labelPortExpectedAtMost } from '../VaultConfiguration/translatedLabels';
import AgentConfigurationPage from './Page';
import {
  getAgentConfigurationEndpoint,
  getAgentConfigurationsEndpoint,
  getPollerAgentEndpoint
} from './api/endpoints';
import {
  labelAction,
  labelAddAgentConfiguration,
  labelAddNewAgent,
  labelAddressInvalid,
  labelAgentConfigurationCreated,
  labelAgentConfigurationUpdated,
  labelAgentType,
  labelAgentTypes,
  labelAgentsConfigurations,
  labelCaCertificate,
  labelCancel,
  labelCertificate,
  labelClear,
  labelDelete,
  labelDeleteAgent,
  labelDeletePoller,
  labelExtensionNotAllowed,
  labelListeningAddress,
  labelName,
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
      alias: 'getPollers',
      method: Method.GET,
      path: './api/latest/configuration/monitoring-servers**',
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
    cy.get('button').contains(labelAddNewAgent).should('be.visible');

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
    cy.get('button').contains(labelAddNewAgent).should('be.visible');
    cy.contains(labelName).should('be.visible');
    cy.contains(labelAgentType).should('be.visible');
    cy.contains(labelPoller).should('be.visible');
    cy.contains(labelAction).should('be.visible');
    cy.contains('AC 0').should('be.visible');
    cy.contains('telegraf').should('be.visible');
    cy.contains('2 pollers').should('be.visible');
    cy.contains('0 pollers').should('be.visible');
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
    cy.contains('Telegraf').click();
    cy.findByLabelText(labelPollers).click({ force: true });

    cy.waitForRequest('@getPollers');

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

    cy.waitForRequest('@getPollers');

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
    cy.contains('Telegraf').click();

    cy.findByTestId('CancelIcon').click();
    cy.findByLabelText('Filters').click();

    cy.contains('Telegraf').should('not.exist');

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
    cy.contains('Telegraf').click();
    cy.findByLabelText(labelPollers).click({ force: true });

    cy.waitForRequest('@getPollers');

    cy.contains('poller6').click();
    cy.contains(labelClear).click({ force: true });
    cy.contains('poller6').should('not.exist');
    cy.contains('Telegraf').should('not.exist');

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
  it('does not validate the when fields contain errors', () => {
    initialize({});

    cy.contains(labelAddNewAgent).click();

    cy.contains(labelAddAgentConfiguration).should('be.visible');

    cy.findByLabelText(labelName).focus();
    cy.findByLabelText(labelName).blur();
    cy.findByLabelText(labelPollers).focus();
    cy.findByLabelText(labelPollers).blur();
    cy.findByLabelText(labelListeningAddress).type('address');
    cy.findAllByLabelText(labelPort).eq(0).type('123456');
    cy.findAllByLabelText(labelPort).eq(1).type('123456');
    cy.findByLabelText(labelPublicCertificate).type('test.cer');
    cy.findByLabelText(labelCaCertificate).type('test.pem');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('test.crt');
    cy.findAllByLabelText(labelPrivateKey).eq(1).type('test.crt');
    cy.findByLabelText(labelCertificate).type('test.key');
    cy.findByLabelText(labelCertificate).blur();

    cy.findByLabelText(labelAgentType).should('have.value', 'Telegraf');
    cy.findAllByText(labelRequired).should('have.length', 2);
    cy.findAllByText(labelExtensionNotAllowed).should('have.length', 5);
    cy.findAllByText(labelAddressInvalid).should('have.length', 1);
    cy.findAllByText(labelPortExpectedAtMost).should('have.length', 2);
    cy.contains(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('discards the form when the cancel button is clicked and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAddNewAgent).click();

    cy.findByLabelText(labelName).type('agent');

    cy.contains(labelCancel).click();
    cy.contains('Discard').click();

    cy.contains(labelAddAgentConfiguration).should('not.exist');

    cy.makeSnapshot();
  });

  it('resolves the form when the cancel button is clicked and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAddNewAgent).click();

    cy.findByLabelText(labelName).type('agent');

    cy.contains(labelCancel).click();
    cy.contains('Resolve').click();

    cy.contains(labelAddAgentConfiguration).should('exist');
    cy.contains('Resolve').should('not.exist');

    cy.makeSnapshot();
  });

  it('sends the form when fields are valid and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labelAddNewAgent).click();

    cy.findByLabelText(labelName).type('agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();
    cy.findByLabelText(labelListeningAddress).type('127.0.0.1');
    cy.findAllByLabelText(labelPort).eq(0).type('1234');
    cy.findAllByLabelText(labelPort).eq(1).type('1234');
    cy.findByLabelText(labelPublicCertificate).type('test');
    cy.findByLabelText(labelCaCertificate).type('test');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('test');
    cy.findAllByLabelText(labelPrivateKey).eq(1).type('test');
    cy.findByLabelText(labelCertificate).type('test');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).equal(
        '{"name":"agent","type":"telegraf","pollers":[1],"configuration":{"otel_private_key":"test","otel_server_port":1234,"otel_ca_certificate":"test","otel_server_address":"127.0.0.1","otel_public_certificate":"test","conf_certificate":"test","conf_private_key":"test","conf_server_port":1234}}'
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
        '{"name":"agent updated","type":"telegraf","pollers":[1,2],"configuration":{"otel_private_key":"coucou","otel_server_port":8080,"otel_ca_certificate":"coucou","otel_server_address":"127.0.0.1","otel_public_certificate":"coucou","conf_certificate":"coucou","conf_private_key":"coucou","conf_server_port":9090}}'
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
        '{"name":"agent updated","type":"telegraf","pollers":[1,2],"configuration":{"otel_private_key":"coucou","otel_server_port":8080,"otel_ca_certificate":"coucou","otel_server_address":"127.0.0.1","otel_public_certificate":"coucou","conf_certificate":"coucou","conf_private_key":"coucou","conf_server_port":9090}}'
      );
    });

    cy.contains(labelAgentConfigurationUpdated).should('be.visible');

    cy.makeSnapshot();
  });
});
