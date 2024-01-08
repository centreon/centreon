import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import {
  Method,
  SnackbarProvider,
  TestQueryProvider,
  setUrlQueryParameters
} from '@centreon/ui';
import { aclAtom, refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import {
  labelAcknowledge,
  labelAcknowledgeCommandSent,
  labelAcknowledgedBy,
  labelAlias,
  labelAt,
  labelCancel,
  labelCategories,
  labelCheck,
  labelCheckDuration,
  labelCommand,
  labelComment,
  labelCurrentNotificationNumber,
  labelCurrentStatusDuration,
  labelDowntimeDuration,
  labelFqdn,
  labelFrom,
  labelGroups,
  labelLastCheck,
  labelLastCheckWithOkStatus,
  labelLastNotification,
  labelLastStatusChange,
  labelLatency,
  labelMore,
  labelNextCheck,
  labelNotify,
  labelNotifyHelpCaption,
  labelPerformanceData,
  labelStatusChangePercentage,
  labelStatusInformation,
  labelSticky,
  labelTimezone,
  labelTo
} from '../translatedLabels';

import {
  panelWidthStorageAtom,
  selectedResourceDetailsEndpointDerivedAtom,
  selectedResourcesDetailsAtom
} from './detailsAtoms';
import useDetails from './useDetails';
import useLoadDetails from './useLoadDetails';

import Details from '.';

const resourceServiceId = 1;

const selectedResource = {
  parentResourceId: undefined,
  parentResourceType: undefined,
  resourceId: resourceServiceId,
  resourcesDetailsEndpoint:
    '/centreon/api/latest/monitoring/resources/hosts/1/services/1'
};

const retrievedUser = {
  alias: 'Admin',
  default_page: '/monitoring/resources',
  isExportButtonEnabled: true,
  locale: 'en_US.UTF8',
  name: 'Admin',
  timezone: 'Europe/Paris',
  use_deprecated_pages: false,
  user_interface_density: 'compact'
};

const serviceDetailsUrlParameters = {
  id: 1,
  resourcesDetailsEndpoint:
    'api/latest/monitoring/resources/hosts/1/services/1',
  tab: 'details',
  type: 'service',
  uuid: 'h1-s1'
};
const mockAcl = {
  actions: {
    host: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      forced_check: true,
      submit_status: true
    },
    service: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      forced_check: true,
      submit_status: true
    }
  }
};

const mockRefreshInterval = 60;

const DetailsTest = (): JSX.Element => {
  useDetails();
  useLoadDetails();

  return (
    <TestQueryProvider>
      <div style={{ height: '100vh' }}>
        <Details />
      </div>
    </TestQueryProvider>
  );
};

const getStore = () => {
  const store = createStore();
  store.set(userAtom, retrievedUser);
  store.set(aclAtom, mockAcl);
  store.set(refreshIntervalAtom, mockRefreshInterval);
  store.set(selectedResourcesDetailsAtom, selectedResource);

  return store;
};

const interceptDetailsRequest = ({ store, dataPath, alias }): void => {
  const selectedResourceDetailsEndpoint = store.get(
    selectedResourceDetailsEndpointDerivedAtom
  );

  cy.fixture(dataPath).then((data) => {
    cy.interceptAPIRequest({
      alias,
      method: Method.GET,
      path: selectedResourceDetailsEndpoint,
      response: data
    });
  });
};

const initialize = (store): void => {
  cy.viewport('macbook-13');

  setUrlQueryParameters([
    {
      name: 'details',
      value: serviceDetailsUrlParameters
    }
  ]);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <Provider store={store}>
          <BrowserRouter>
            <DetailsTest />
          </BrowserRouter>
        </Provider>
      </SnackbarProvider>
    )
  });
};

const checkActionsButton = (): void => {
  cy.findByTestId('mainAcknowledge').should('be.visible').should('be.disabled');
  cy.findByTestId('mainDisacknowledge')
    .should('be.visible')
    .should('be.enabled');
  cy.findByTestId('mainSetDowntime').should('be.visible').should('be.disabled');
  cy.findByTestId('mainCheck').should('be.visible').should('be.enabled');
};

describe('Details', () => {
  it('displays resource details information', () => {
    const store = getStore();
    interceptDetailsRequest({
      alias: 'getDetails',
      dataPath: 'resources/details/details.json',
      store
    });
    initialize(store);

    cy.waitForRequest('@getDetails');

    cy.contains('Critical').should('be.visible');
    cy.contains('Centreon').should('be.visible');

    cy.contains(labelFqdn).should('be.visible');
    cy.contains('central.centreon.com').should('be.visible');
    cy.contains(labelAlias).should('be.visible');
    cy.contains('Central-Centreon').should('be.visible');
    cy.contains(labelStatusInformation).should('be.visible');
    cy.contains('OK - 127.0.0.1 rta 0.100ms lost 0%').should('be.visible');
    cy.contains('OK - 127.0.0.1 rta 0.99ms lost 0%').should('be.visible');
    cy.contains('OK - 127.0.0.1 rta 0.98ms lost 0%').should('be.visible');
    cy.contains('OK - 127.0.0.1 rta 0.97ms lost 0%').should('not.exist');
    cy.contains(labelMore).click();
    cy.contains('OK - 127.0.0.1 rta 0.97ms lost 0%').should('be.visible');

    cy.findAllByText(labelComment).should('have.length', 3);
    cy.findAllByText(labelDowntimeDuration).should('have.length', 2);
    cy.contains(`${labelFrom} 01/18/2020 6:57 PM`).should('be.visible');
    cy.contains(`${labelTo} 01/18/2020 7:57 PM`).should('be.visible');
    cy.contains(`${labelFrom} 02/18/2020 6:57 PM`).should('be.visible');
    cy.contains(`${labelTo} 02/18/2020 7:57 PM`).should('be.visible');
    cy.contains('First downtime set by Admin').should('be.visible');
    cy.contains('Second downtime set by Admin').should('be.visible');

    cy.contains(labelAcknowledgedBy).should('be.visible');
    cy.contains(`Admin ${labelAt} 03/18/2020 7:57 PM`).should('be.visible');
    cy.contains('Acknowledged by Admin').should('be.visible');

    cy.contains(labelTimezone).should('be.visible');
    cy.contains('Europe/Paris').should('be.visible');

    cy.contains(labelCurrentStatusDuration).should('be.visible');
    cy.contains('22m - 3/3 (Hard)').should('be.visible');

    cy.contains(labelLastStatusChange).should('be.visible');
    cy.contains('04/18/2020 5:00 PM').should('be.visible');

    cy.contains(labelLastCheck).should('be.visible');
    cy.contains('05/18/2020 6:00 PM').should('be.visible');

    cy.contains(labelNextCheck).should('exist');
    cy.contains('06/18/2020 7:15 PM').should('exist');

    cy.contains(labelCheckDuration).should('exist');
    cy.contains('0.070906 s').should('exist');

    cy.contains(labelLastCheckWithOkStatus).should('exist');
    cy.contains('06/18/2020 7:15 PM').should('exist');

    cy.contains(labelLatency).should('exist');
    cy.contains('0.005 s').should('exist');

    cy.contains(labelCheck).should('exist');

    cy.contains(labelStatusChangePercentage).should('exist');
    cy.contains('3.5%').should('exist');

    cy.contains(labelLastNotification).should('exist');
    cy.contains('07/18/2020 7:30 PM').should('exist');

    cy.contains(labelCurrentNotificationNumber).should('exist');
    cy.contains('3').should('exist');

    cy.contains(labelGroups).should('exist');
    cy.contains('Linux-servers').should('exist');
    cy.contains(labelCategories).should('exist');
    cy.contains('Windows').should('exist');

    cy.contains(labelPerformanceData).should('exist');
    cy.contains(
      'rta=0.025ms;200.000;400.000;0; rtmax=0.061ms;;;; rtmin=0.015ms;;;; pl=0%;20;50;0;100'
    ).should('exist');

    cy.contains(labelCommand).should('exist');
    cy.contains('base_host_alive').should('exist');
    // cy.makeSnapshot()
  });
  it('displays resource details actions like icons when panel width is less than or equal 615 px ', () => {
    const store = getStore();
    interceptDetailsRequest({
      alias: 'getDetails',
      dataPath: 'resources/details/details.json',
      store
    });
    initialize(store);
    cy.waitForRequest('@getDetails');
    cy.contains('Critical').should('be.visible');

    checkActionsButton();
    // cy.makeSnapshot();
  });
  it('displays resource details actions like buttons when panel width is greater than  615 px ', () => {
    const store = getStore();
    store.set(panelWidthStorageAtom, 800);
    interceptDetailsRequest({
      alias: 'getDetails',
      dataPath: 'resources/details/details.json',
      store
    });
    initialize(store);

    cy.waitForRequest('@getDetails');
    cy.contains('Critical').should('be.visible');

    checkActionsButton();
    // cy.makeSnapshot();
  });
  it('displays the modal of ack when Acknowledge button is clicked and sends an action of acknowledge', () => {
    const store = getStore();

    interceptDetailsRequest({
      alias: 'getDetailsWithNoAcknowledgement',
      dataPath: 'resources/details/detailsWithNoAcknowledgment.json',
      store
    });
    initialize(store);
    cy.waitForRequest('@getDetailsWithNoAcknowledgement');
    cy.contains('Unknown').should('be.visible');

    cy.findByTestId('mainAcknowledge')
      .should('be.visible')
      .should('be.enabled')
      .click();

    cy.findByTestId('dialogAcknowledge').should('be.visible');

    cy.contains(labelCancel).should('be.visible');
    cy.contains(labelAcknowledge).should('be.visible');
    cy.contains(labelComment).should('be.visible');
    cy.contains(labelNotify).should('be.visible');
    cy.contains(labelNotifyHelpCaption).should('be.visible');
    cy.contains(labelSticky);

    // cy.makeSnapshot(
    //   'displays the modal of ack when Acknowledge button is clicked'
    // );

    cy.interceptAPIRequest({
      alias: 'sendAck',
      method: Method.POST,
      path: './api/latest/monitoring/resources/acknowledge',
      statusCode: 204
    });

    cy.findByTestId('Confirm').click();
    cy.waitForRequest('@sendAck');

    cy.getRequestCalls('@sendAck').then((calls) => {
      expect(calls).to.have.length(1);
    });

    cy.contains(labelAcknowledgeCommandSent);

    // cy.makeSnapshot('sends an action of acknowledge');
  });
  // sends an action of disacknowledge when button disack is clicked
  // sends an action downtime  when button dt is clicked
  // sends an action of check when check action is chose and  clicked
  // sends an action of force check when button force check is chose and  clicked
});
