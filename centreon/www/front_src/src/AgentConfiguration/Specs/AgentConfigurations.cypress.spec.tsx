import { labelPortExpectedAtMost } from '../../VaultConfiguration/translatedLabels';
import initialize from './initialize';

import {
  labelAction,
  labelAdd,
  labelAddAHost,
  labelAddAgentConfiguration,
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
  labelEncryptionLevel,
  labelHostConfigurations,
  labelInsecure,
  labelInvalidExtension,
  labelInvalidPath,
  labelName,
  labelNoTLS,
  labelOTLPReceiver,
  labelPoller,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelRelativePathAreNotAllowed,
  labelRequired,
  labelSave,
  labelSelectHost,
  labelTLS,
  labelWarningEncryptionLevelCMA,
  labelWarningEncryptionLevelTelegraf,
  labelWelcomeToTheAgentsConfigurationPage
} from '../translatedLabels';

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


    cy.findAllByTestId('Search').find('input').type('My agent');
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

    cy.findAllByTestId('Search').find('input').type('My agent');
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
        connection_mode: 'secure',
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
        connection_mode: 'secure',
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
        connection_mode: 'secure',
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

    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();
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
        connection_mode: 'secure',
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
    cy.findByLabelText(labelSelectHost).click();

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
    cy.findByLabelText(labelSelectHost).click();
    cy.contains('central').click();
    cy.findByLabelText(labelCACommonName).type('test.crt');
    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).deep.equal({
        name: 'My agent',
        type: 'centreon-agent',
        connection_mode: 'secure',
        poller_ids: [1],
        configuration: {
          is_reverse: true,
          otel_ca_certificate: 'test.crt',
          otel_public_certificate: '/test.cer',
          otel_private_key: 'private.key',
          hosts: [
            {
              id: 1,
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

  it('displays the encryption level field with TLS preselected as the default value', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelEncryptionLevel).should('have.value', labelTLS);

    cy.makeSnapshot();
  });

  it('displays a warning message when switching the encryption level to No TLS for Telegraf and CMA agents', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="0"]').click();
    cy.findByLabelText(labelEncryptionLevel).click();
    cy.contains(labelNoTLS).click();

    cy.contains(labelWarningEncryptionLevelTelegraf);

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();
    cy.contains(labelWarningEncryptionLevelCMA);

    cy.makeSnapshot();
  });

  it('handle the form without certificate fields when setting encryption level to No TLS for Telegraf agent', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="0"]').click();
    cy.findByLabelText(labelEncryptionLevel).click();
    cy.contains(labelNoTLS).click();

    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();

    cy.findAllByLabelText(labelPort).eq(0).clear().type('1234');

    cy.findAllByLabelText(labelPublicCertificate).should('not.exist');
    cy.findByLabelText(labelCaCertificate).should('not.exist');
    cy.findAllByLabelText(labelPrivateKey).should('not.exist');
    cy.findAllByLabelText(labelPrivateKey).should('not.exist');
    cy.findByLabelText(labelCaCertificate).should('not.exist');
    cy.findByLabelText(labelCaCertificate).should('not.exist');

    cy.makeSnapshot();

    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).to.deep.equal({
        name: 'My agent',
        connection_mode: 'no-tls',
        type: 'telegraf',
        configuration: {
          otel_private_key: null,
          otel_ca_certificate: null,
          otel_public_certificate: null,
          conf_certificate: null,
          conf_private_key: null,
          conf_server_port: 1234
        },
        poller_ids: [1]
      });
    });

    cy.contains(labelAgentConfigurationCreated).should('be.visible');
  });

  it('handle the form without certificate fields when setting encryption level to No TLS for CMA agent', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="1"]').click();

    cy.findByLabelText(labelName).type('My agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();

    cy.findByLabelText(labelEncryptionLevel).click();
    cy.contains(labelNoTLS).click();

    cy.findByLabelText(labelConnectionInitiatedByPoller).click();

    cy.findByLabelText(labelPublicCertificate).should('not.exist');
    cy.findAllByLabelText(labelCaCertificate).should('not.exist');
    cy.findAllByLabelText(labelCaCertificate).should('not.exist');
    cy.findAllByLabelText(labelPrivateKey).should('not.exist');
    cy.findByLabelText(labelSelectHost).click();
    cy.contains('central').click();
    cy.findByLabelText(labelCACommonName).should('not.exist');

    cy.makeSnapshot();

    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).deep.equal({
        name: 'My agent',
        type: 'centreon-agent',
        connection_mode: 'no-tls',
        poller_ids: [1],
        configuration: {
          is_reverse: true,
          otel_ca_certificate: null,
          otel_public_certificate: null,
          otel_private_key: null,
          hosts: [
            {
              id: 1,
              address: '127.0.0.2',
              port: 4317,
              poller_ca_name: null,
              poller_ca_certificate: null
            }
          ]
        }
      });
    });

    cy.contains(labelAgentConfigurationCreated).should('be.visible');
  });

  it('send a Post request with certificate fields when connection mode is insecure', () => {
    initialize({});

    cy.contains(labelAdd).click();

    cy.findByLabelText(labelAgentType).click();
    cy.get('[data-option-index="0"]').click();

    cy.findByLabelText(labelEncryptionLevel).click();
    cy.contains(labelInsecure).click();

    cy.findByLabelText(labelName).type('Insecure Agent');
    cy.findByLabelText(labelPollers).click();
    cy.contains('poller1').click();
    cy.findAllByLabelText(labelPort).eq(0).clear().type('1234');

    cy.findAllByLabelText(labelPublicCertificate).eq(0).should('exist');
    cy.findByLabelText(labelCaCertificate).should('exist');
    cy.findAllByLabelText(labelPrivateKey).should('have.length', 2);

    cy.contains(labelSave).should('be.disabled');

    cy.findAllByLabelText(labelPublicCertificate).eq(0).type('test.crt');
    cy.findByLabelText(labelCaCertificate).type('ca.crt');
    cy.findAllByLabelText(labelPrivateKey).eq(0).type('test.key');
    cy.findAllByLabelText(labelPrivateKey).eq(1).type('test.key');
    cy.findAllByLabelText(labelPublicCertificate).eq(1).type('test.cer');

    cy.contains(labelSave).should('be.enabled');

    cy.makeSnapshot();

    cy.contains(labelSave).click();

    cy.waitForRequest('@postAgentConfiguration').then(({ request }) => {
      expect(request.body).to.deep.equal({
        name: 'Insecure Agent',
        connection_mode: 'insecure',
        type: 'telegraf',
        configuration: {
          otel_private_key: 'test.key',
          otel_ca_certificate: 'ca.crt',
          otel_public_certificate: 'test.crt',
          conf_certificate: 'test.cer',
          conf_private_key: 'test.key',
          conf_server_port: 1234
        },
        poller_ids: [1]
      });
    });

    cy.contains(labelAgentConfigurationCreated).should('be.visible');
  });

  it('sends an update request with certificate fields when connection mode is insecure', () => {
    initialize({});

    cy.contains('AC 1').click();
    cy.waitForRequest('@getAgentConfiguration');

    cy.findByLabelText(labelEncryptionLevel).click();
    cy.contains(labelInsecure).click();

    cy.findByLabelText(labelName).clear().type('Insecure Agent');

    cy.makeSnapshot();

    cy.contains(labelSave).click();

    cy.waitForRequest('@patchAgentConfiguration').then(({ request }) => {
      expect(request.body).to.deep.equal({
        name: 'Insecure Agent',
        type: 'telegraf',
        connection_mode: 'insecure',
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
  });
});
