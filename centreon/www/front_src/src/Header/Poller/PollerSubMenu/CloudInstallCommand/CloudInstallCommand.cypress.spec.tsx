import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformFeaturesAtom } from '@centreon/ui-context';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import { listTokensEndpoint } from '../../../../AuthenticationTokens/api';
import { createPollerEndpoint } from '../../../api/endpoints';
import {
  labelCancel,
  labelCentralAddress,
  labelClickToGenerate,
  labelConfigurationExportedAndReloaded,
  labelCopyTheFollowingCommand,
  labelCreateNewPoller,
  labelDockerCompose,
  labelEnterPollerNameAndAddress,
  labelExportConfiguration,
  labelFailedToCreatePoller,
  labelFailedToExportAndReloadConfiguration,
  labelGenerateInstallationCommand,
  labelPleaseWait,
  labelPollerAddress,
  labelPollerName,
  labelSelectPollerEnvironment,
  labelSelectToken,
  labelSelectTokenPlaceholder,
  labelVMOrPhysical
} from '../../translatedLabels';
import { generatedCommandAtom, isModalOpenAtom, pollerIdAtom } from './atoms';
import CloudInstallCommand from './CloudInstallCommand';
import { webUrl } from './Modal/useInstallCommand';

const createPollerSuccessResponse = {
  '@context': '/centreon/api/latest/contexts/Poller',
  '@id': '/centreon/api/latest/pollers/11',
  '@type': 'Poller',
  address: '192.0.0.98',
  id: 11,
  installation_command:
    'installcma.ps /FINGERPRINT=lllllll  /COMPONENTS=agent,plugins /HOST=host_1 /ENDPOINT=https://central/centreon:4317',
  name: 'poller-docker_07',
  poller_type: 'docker',
  uuid: '019dcf18-bdb1-7f89-a61f-45b8fcfb27e6'
};

const createPollerResponseWithPlaceholder = {
  ...createPollerSuccessResponse,
  installation_command:
    'installcma.ps /FINGERPRINT=lllllll /ENDPOINT=<CENTRAL_URL>/api/latest'
};

const initializeI18n = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });
};

const mockRequests = ({
  createPollerResponse = createPollerSuccessResponse,
  createPollerStatusCode = 200,
  exportConfigStatusCode = 204
}: {
  createPollerResponse?: object;
  createPollerStatusCode?: number;
  exportConfigStatusCode?: number;
} = {}): void => {
  cy.fixture('authenticationTokens/listTokens.json').then((tokens): void => {
    cy.interceptAPIRequest({
      alias: 'getTokens',
      method: Method.GET,
      path: `*${listTokensEndpoint}**`,
      response: tokens
    });
  });

  cy.interceptAPIRequest({
    alias: 'createPoller',
    method: Method.POST,
    path: `./api/latest${createPollerEndpoint}`,
    response: createPollerResponse,
    statusCode: createPollerStatusCode
  });

  cy.interceptAPIRequest({
    alias: 'exportConfig',
    method: Method.GET,
    path: '**/configuration/monitoring-servers/*/generate-and-reload',
    response: undefined,
    statusCode: exportConfigStatusCode
  });
};

interface InitializeOptions {
  createPollerResponse?: object;
  createPollerStatusCode?: number;
  exportConfigStatusCode?: number;
  isModalOpen?: boolean;
  generatedCommand?: string | null;
  pollerId?: number | null;
  isCloudPlatform?: boolean;
}

const initialize = ({
  isCloudPlatform = false,
  isModalOpen = false,
  generatedCommand = null,
  pollerId = null,
  createPollerResponse,
  createPollerStatusCode,
  exportConfigStatusCode
}: InitializeOptions = {}): ReturnType<typeof createStore> => {
  const store = createStore();

  store.set(isModalOpenAtom, isModalOpen);
  store.set(platformFeaturesAtom, { featureFlags: {}, isCloudPlatform });
  store.set(generatedCommandAtom, generatedCommand);
  store.set(pollerIdAtom, pollerId);

  initializeI18n();

  mockRequests({
    createPollerResponse,
    createPollerStatusCode,
    exportConfigStatusCode
  });

  const closeSubMenu = cy.stub().as('closeSubMenu');

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Router>
            <SnackbarProvider maxSnackbars={2}>
              <CloudInstallCommand closeSubMenu={closeSubMenu} />
            </SnackbarProvider>
          </Router>
        </Provider>
      </TestQueryProvider>
    )
  });

  return store;
};

describe('CloudInstallCommand', () => {
  describe('Button', () => {
    it('displays a "Create new poller" button', () => {
      initialize();

      cy.findByTestId(labelCreateNewPoller).should('be.visible');
      cy.findByTestId(labelCreateNewPoller).should(
        'contain.text',
        labelCreateNewPoller
      );
    });

    it('opens the modal and calls closeSubMenu when the button is clicked', () => {
      initialize();

      cy.findByTestId(labelCreateNewPoller).click();

      cy.get('@closeSubMenu').should('have.been.calledOnce');

      cy.findByRole('dialog').should('be.visible');
    });
  });

  describe('Modal', () => {
    it('displays the modal with the correct title', () => {
      initialize({ isModalOpen: true });

      cy.findByRole('dialog').should('be.visible');
      cy.findByRole('dialog')
        .findByText(labelCreateNewPoller)
        .should('be.visible');
    });

    it('displays all form sections', () => {
      initialize({ isModalOpen: true });

      cy.findByText(labelEnterPollerNameAndAddress).should('be.visible');
      cy.findByText(labelSelectPollerEnvironment).should('be.visible');
      cy.findAllByText(labelSelectToken).should('have.length', 3);
      cy.findByText(labelGenerateInstallationCommand)
        .scrollIntoView()
        .should('be.visible');
    });

    describe('Poller name section', () => {
      it('displays a text field for the poller name', () => {
        initialize({ isModalOpen: true });

        cy.findByTestId('cloud-poller-name').should('be.visible');
        cy.findByLabelText(`${labelPollerName} *`).should('be.visible');
      });

      it('allows typing a poller name', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(`${labelPollerName} *`).type('my-poller');
        cy.findByLabelText(`${labelPollerName} *`).should(
          'have.value',
          'my-poller'
        );
      });
    });

    describe('Poller address section', () => {
      it('displays a text field for the poller address', () => {
        initialize({ isModalOpen: true });

        cy.findByTestId('cloud-poller-address').should('be.visible');
        cy.findByLabelText(`${labelPollerAddress} *`).should('be.visible');
      });

      it('allows typing a poller address', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(`${labelPollerAddress} *`).type('192.168.1.1');
        cy.findByLabelText(`${labelPollerAddress} *`).should(
          'have.value',
          '192.168.1.1'
        );
      });
    });

    describe('Centreon central address section', () => {
      it('displays a text field for the centreon central address', () => {
        initialize({ isModalOpen: true });

        cy.findByTestId('centreon-central-address').should('be.visible');
        cy.findByLabelText(`${labelCentralAddress} *`).should('be.visible');
      });

      it('allows typing a poller address', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(`${labelCentralAddress} *`).type('192.168.1.1');
        cy.findByLabelText(`${labelCentralAddress} *`).should(
          'have.value',
          '192.168.1.1'
        );
      });

      it('hides centreon central address if it is Cloud enviroment', () => {
        initialize({ isCloudPlatform: true, isModalOpen: true });

        cy.findByTestId('centreon-central-address').should('not.exist');
      });
    });

    describe('Environment selector', () => {
      it('displays VM and Docker environment options', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(labelVMOrPhysical).should('be.visible');
        cy.findByLabelText(labelDockerCompose).should('be.visible');
      });

      it('has VM selected by default', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(labelVMOrPhysical)
          .closest('[data-selected]')
          .should('have.attr', 'data-selected', 'true');
      });

      it('allows switching to Docker environment', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(labelDockerCompose).click();

        cy.findByLabelText(labelDockerCompose)
          .closest('[data-selected]')
          .should('have.attr', 'data-selected', 'true');

        cy.findByLabelText(labelVMOrPhysical)
          .closest('[data-selected]')
          .should('have.attr', 'data-selected', 'false');
      });
    });

    describe('Token section', () => {
      it('displays the token autocomplete field', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(labelSelectTokenPlaceholder).should('be.visible');
      });
    });

    describe('Command section', () => {
      it('displays a generate button with helper text when no command is generated', () => {
        initialize({ isModalOpen: true });

        cy.findByText(labelClickToGenerate)
          .scrollIntoView()
          .should('be.visible');
        cy.findByTestId('Install command')
          .scrollIntoView()
          .should('be.visible');
      });

      it('the generate button is disabled when the form is not valid or not dirty', () => {
        initialize({ isModalOpen: true });

        cy.findByTestId('Install command')
          .closest('button')
          .should('be.disabled');
      });

      it('displays the generated command when available', () => {
        const command = createPollerSuccessResponse.installation_command;
        initialize({
          generatedCommand: command,
          isModalOpen: true
        });

        cy.findByText(labelCopyTheFollowingCommand)
          .scrollIntoView()
          .should('be.visible');
        cy.findByTestId('Command')
          .scrollIntoView()
          .should('contain.text', command);
      });
    });

    describe('Buttons', () => {
      it('displays Cancel and Export configuration buttons', () => {
        initialize({ isModalOpen: true });

        cy.findByRole('dialog')
          .findByText(labelCancel)
          .scrollIntoView()
          .should('be.visible');

        cy.findByRole('dialog')
          .scrollIntoView()
          .findByText(labelExportConfiguration)
          .should('be.visible');
      });

      it('the Export configuration button is disabled when no command is generated', () => {
        initialize({ isModalOpen: true });

        cy.findByTestId('generate-command').should('be.disabled');
      });

      it('closes the modal when clicking Cancel', () => {
        initialize({ isModalOpen: true });

        cy.findByRole('dialog').findByText(labelCancel).click();

        cy.findByRole('dialog').should('not.exist');
      });

      it('the Export configuration button is disabled when pollerId is null', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true,
          pollerId: null
        });

        cy.findByTestId('generate-command').should('be.disabled');
      });

      it('the Export configuration button is enabled when a command has been generated and pollerId is set', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true,
          pollerId: 11
        });

        cy.findByTestId('generate-command').should('not.be.disabled');
      });

      it('calls the export config API and shows a success message when clicking Export configuration', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true,
          pollerId: 11
        });

        cy.findByTestId('generate-command').click();

        cy.waitForRequest('@exportConfig');

        cy.findByText(labelConfigurationExportedAndReloaded).should(
          'be.visible'
        );

        cy.findByRole('dialog').should('not.exist');
      });

      it('shows an error message when the export config API fails', () => {
        initialize({
          exportConfigStatusCode: 500,
          generatedCommand: 'some-command',
          isModalOpen: true,
          pollerId: 11
        });

        cy.findByTestId('generate-command').click();

        cy.waitForRequest('@exportConfig');

        cy.findByText(labelFailedToExportAndReloadConfiguration).should(
          'be.visible'
        );
      });

      it('displays "Please wait..." text while the export is in progress', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true,
          pollerId: 11
        });

        cy.findByTestId('generate-command').should(
          'contain.text',
          labelExportConfiguration
        );

        cy.findByTestId('generate-command').click();

        cy.findByText(labelPleaseWait).should('be.visible');
      });
    });

    describe('Fields disabled after generation', () => {
      it('disables the poller name field when command is generated', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true
        });

        cy.findByLabelText(`${labelPollerName} *`).should('be.disabled');
      });

      it('disables the poller address field when command is generated', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true
        });

        cy.findByLabelText(`${labelPollerAddress} *`).should('be.disabled');
      });

      it('disables the environment selector when command is generated', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true
        });

        cy.findByLabelText(labelVMOrPhysical)
          .closest('button')
          .should('be.disabled');
        cy.findByLabelText(labelDockerCompose)
          .closest('button')
          .should('be.disabled');
      });

      it('disables the token field when command is generated', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true
        });

        cy.findByLabelText(labelSelectTokenPlaceholder).should('be.disabled');
      });
    });

    describe('Close modal resets state', () => {
      it('resets generated command when modal is closed via Cancel', () => {
        initialize({
          generatedCommand: 'some-command',
          isModalOpen: true
        });

        cy.findByText(labelCopyTheFollowingCommand)
          .scrollIntoView()
          .should('be.visible');

        cy.findByRole('dialog')
          .scrollIntoView()
          .findByText(labelCancel)
          .click();

        cy.findByRole('dialog').should('not.exist');

        cy.findByTestId(labelCreateNewPoller).click();

        cy.findByRole('dialog').should('be.visible');
        cy.findByText(labelCopyTheFollowingCommand).should('not.exist');
        cy.findByText(labelClickToGenerate)
          .scrollIntoView()
          .should('be.visible');
      });
    });

    describe('Form submission and command generation', () => {
      it('generates a command when the form is valid and the generate button is clicked', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(`${labelPollerName} *`).type('my-poller');
        cy.findByLabelText(`${labelPollerAddress} *`).type('192.168.1.1');
        cy.findByLabelText(`${labelCentralAddress} *`).type('192.168.1.1');

        cy.findByLabelText(labelSelectTokenPlaceholder).click();
        cy.waitForRequest('@getTokens');
        cy.findByText('a-token').click();

        cy.findByTestId('Install command')
          .closest('button')
          .should('not.be.disabled');

        cy.findByTestId('Install command').closest('button').click();

        cy.waitForRequest('@createPoller');

        cy.findByText(labelCopyTheFollowingCommand)
          .scrollIntoView()
          .should('be.visible');

        cy.findByTestId('Command')
          .scrollIntoView()
          .should(
            'contain.text',
            createPollerSuccessResponse.installation_command
          );
      });

      it('shows an error message when the API call fails', () => {
        initialize({
          createPollerResponse: { message: 'Internal server error' },
          createPollerStatusCode: 500,
          isModalOpen: true
        });

        cy.findByLabelText(`${labelPollerName} *`).type('my-poller');
        cy.findByLabelText(`${labelPollerAddress} *`).type('192.168.1.1');
        cy.findByLabelText(`${labelCentralAddress} *`).type('192.168.1.1');

        cy.findByLabelText(labelSelectTokenPlaceholder).click();
        cy.waitForRequest('@getTokens');
        cy.findByText('a-token').click();

        cy.findByTestId('Install command').closest('button').click();

        cy.waitForRequest('@createPoller');

        cy.findByText(labelFailedToCreatePoller).should('be.visible');
      });

      it('sends the centreon central address in the API payload if the enviromment is on-prem', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(`${labelPollerName} *`).type('my-poller');
        cy.findByLabelText(`${labelPollerAddress} *`).type('192.168.1.1');
        cy.findByLabelText(`${labelCentralAddress} *`).type('192.168.1.1');

        cy.findByLabelText(labelSelectTokenPlaceholder).click();
        cy.waitForRequest('@getTokens');
        cy.findByText('a-token').click();

        cy.findByTestId('Install command').closest('button').click();

        cy.waitForRequest('@createPoller').then(({ request }) => {
          expect(request.body.name).to.equal('my-poller');
          expect(request.body.address).to.equal('192.168.1.1');
          expect(request.body.central_address).to.equal('192.168.1.1');
        });
      });

      it('sends a value of centreon central address based on the web url in the API payload if the enviromment is cloud', () => {
        cy.stub(webUrl, 'get').returns(
          'https://staging.euwest1.centreon.click/funky-donkey'
        );

        initialize({ isCloudPlatform: true, isModalOpen: true });

        cy.findByLabelText(`${labelPollerName} *`).type('my-poller');
        cy.findByLabelText(`${labelPollerAddress} *`).type('192.168.1.1');

        cy.findByLabelText(labelSelectTokenPlaceholder).click();
        cy.waitForRequest('@getTokens');
        cy.findByText('a-token').click();

        cy.findByTestId('Install command').closest('button').click();

        cy.waitForRequest('@createPoller').then(({ request }) => {
          expect(request.body.name).to.equal('my-poller');
          expect(request.body.address).to.equal('192.168.1.1');
          expect(request.body.central_address).to.equal(
            'https://staging.euwest1.centreon.click/funky-donkey'
          );
        });
      });

      it('replaces <CENTRAL_URL> placeholder in the generated command with the actual central URL', () => {
        initialize({
          createPollerResponse: createPollerResponseWithPlaceholder,
          isModalOpen: true
        });

        cy.findByLabelText(`${labelPollerName} *`).type('my-poller');
        cy.findByLabelText(`${labelPollerAddress} *`).type('192.168.1.1');
        cy.findByLabelText(`${labelCentralAddress} *`).type('192.168.1.1');

        cy.findByLabelText(labelSelectTokenPlaceholder).click();
        cy.waitForRequest('@getTokens');
        cy.findByText('a-token').click();

        cy.findByTestId('Install command').closest('button').click();

        cy.waitForRequest('@createPoller');

        const expectedUrl = `${window.location.origin}`;

        cy.findByTestId('Command')
          .scrollIntoView()
          .should(
            'contain.text',
            `installcma.ps /FINGERPRINT=lllllll /ENDPOINT=${expectedUrl}/api/latest`
          );
        cy.findByTestId('Command').should('not.contain.text', '<CENTRAL_URL>');
      });

      it('submits with Docker environment when Docker is selected', () => {
        initialize({ isModalOpen: true });

        cy.findByLabelText(`${labelPollerName} *`).type('docker-poller');
        cy.findByLabelText(`${labelPollerAddress} *`).type('10.0.0.1');
        cy.findByLabelText(`${labelCentralAddress} *`).type('192.168.1.1');

        cy.findByLabelText(labelDockerCompose).click();

        cy.findByLabelText(labelSelectTokenPlaceholder).click();
        cy.waitForRequest('@getTokens');
        cy.findByText('a-token').click();

        cy.findByTestId('Install command').closest('button').click();

        cy.waitForRequest('@createPoller').then(({ request }) => {
          expect(request.body.poller_type).to.equal('docker');
          expect(request.body.name).to.equal('docker-poller');
        });
      });
    });
  });
});
