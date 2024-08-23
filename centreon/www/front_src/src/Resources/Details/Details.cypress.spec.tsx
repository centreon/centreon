import dayjs from 'dayjs';
import 'dayjs/locale/en';
import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import {
  Method,
  SnackbarProvider,
  TestQueryProvider,
  setUrlQueryParameters
} from '@centreon/ui';
import { aclAtom, refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import { commentEndpoint } from '../Actions/api/endpoint';
import { resourcesEndpoint } from '../api/endpoint';
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
  labelDisacknowledge,
  labelDisacknowledgementCommandSent,
  labelDowntime,
  labelDowntimeCommandSent,
  labelDowntimeDuration,
  labelDuration,
  labelFixed,
  labelForcedCheck,
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
  labelResourceDetailsCheckCommandSent,
  labelResourceDetailsCheckDescription,
  labelResourceDetailsForcedCheckCommandSent,
  labelResourceDetailsForcedCheckDescription,
  labelSave,
  labelSetDowntime,
  labelStatusChangePercentage,
  labelStatusInformation,
  labelSticky,
  labelTimezone,
  labelTo,
  labelYourCommentSent
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
    '/api/latest/monitoring/resources/hosts/1/services/1'
};

const retrievedUser = {
  alias: 'Admin',
  default_page: '/monitoring/resources',
  isExportButtonEnabled: true,
  locale: 'en',
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
    metaservice: {
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
    <div style={{ height: '100vh' }}>
      <Details />
    </div>
  );
};

const getStore = (): unknown => {
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
      path: `**/${selectedResourceDetailsEndpoint}`,
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
        <TestQueryProvider>
          <Provider store={store}>
            <BrowserRouter>
              <DetailsTest />
            </BrowserRouter>
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

const initializeTimeLine = ({
  fixtureDetails = 'resources/details/tabs/details/details.json'
}): void => {
  const store = getStore();

  interceptDetailsRequest({
    alias: 'getDetails',
    dataPath: fixtureDetails,
    store
  });

  cy.fixture('resources/details/tabs/timeLine/timeLine.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getTimeLine',
      method: Method.GET,
      path: '**/timeline**',
      response: data
    });
  });

  initialize(store);
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
      dataPath: 'resources/details/tabs/details/details.json',
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
    cy.contains(/^base_host_alive$/).should('exist');
    cy.contains('--test').should('exist');
    cy.contains('-n').should('exist');
    cy.contains('-w').should('exist');
    cy.contains('3000,80').should('exist');
    cy.contains('-c').should('exist');
    cy.contains('5000,100').should('exist');
    cy.contains('-t').should('exist');
    cy.contains('host').should('exist');
    cy.contains(/^--test2="ok"$/).should('exist');
    cy.makeSnapshot();
  });
  it('displays actions as icons when the panel width is less than 615 px', () => {
    const store = getStore();
    interceptDetailsRequest({
      alias: 'getDetails',
      dataPath: 'resources/details/tabs/details/details.json',
      store
    });
    initialize(store);
    cy.waitForRequest('@getDetails');
    cy.contains('Critical').should('be.visible');

    checkActionsButton();
    cy.makeSnapshot();
  });
  it('displays actions as buttons when panel width exceeds 615 px', () => {
    const store = getStore();
    store.set(panelWidthStorageAtom, 800);
    interceptDetailsRequest({
      alias: 'getDetails',
      dataPath: 'resources/details/tabs/details/details.json',
      store
    });
    initialize(store);

    cy.waitForRequest('@getDetails');
    cy.contains('Critical').should('be.visible');

    checkActionsButton();
    cy.makeSnapshot();
  });
  it('displays the acknowledgment modal when the "Acknowledge" button is clicked and sends the acknowledgment action', () => {
    const store = getStore();

    interceptDetailsRequest({
      alias: 'getDetailsWithoutAcknowledgement',
      dataPath:
        'resources/details/tabs/details/detailsWithoutAcknowledgment.json',
      store
    });
    initialize(store);
    cy.waitForRequest('@getDetailsWithoutAcknowledgement');
    cy.contains('Critical').should('be.visible');

    cy.findByTestId('mainAcknowledge')
      .should('be.visible')
      .should('be.enabled')
      .click();

    cy.findByTestId('mainDisacknowledge').should('be.disabled');

    cy.findByTestId('dialogAcknowledge').should('be.visible');

    cy.contains(labelCancel).should('be.visible');
    cy.contains(labelAcknowledge).should('be.visible');
    cy.contains(labelComment).should('be.visible');
    cy.contains(labelNotify).should('be.visible');
    cy.contains(labelNotifyHelpCaption).should('be.visible');
    cy.contains(labelSticky);

    cy.makeSnapshot(
      'displays the acknowledgment modal when the "Acknowledge" button is clicked'
    );

    cy.interceptAPIRequest({
      alias: 'sendAcknowledgmentAction',
      method: Method.POST,
      path: `${resourcesEndpoint}/acknowledge`,
      statusCode: 204
    });

    cy.findByTestId('Confirm').click();
    cy.waitForRequest('@sendAcknowledgmentAction');

    cy.getRequestCalls('@sendAcknowledgmentAction').then((calls) => {
      expect(calls).to.have.length(1);
    });

    cy.contains(labelAcknowledgeCommandSent);

    cy.makeSnapshot('sends the acknowledgment action');
  });

  it('displays the disacknowledgment modal when the "Disacknowledge" button is clicked and sends the disacknowledgment action', () => {
    const store = getStore();

    interceptDetailsRequest({
      alias: 'getDetailsWithAcknowledgement',
      dataPath: 'resources/details/tabs/details/detailsWithAcknowledgment.json',
      store
    });
    initialize(store);
    cy.waitForRequest('@getDetailsWithAcknowledgement');
    cy.contains('Critical').should('be.visible');

    cy.findByTestId('mainDisacknowledge')
      .should('be.visible')
      .should('be.enabled')
      .click();

    cy.findByTestId('mainAcknowledge').should('be.disabled');

    cy.findByTestId('modalDisacknowledge').should('be.visible');

    cy.contains(labelDisacknowledge).should('be.visible');
    cy.contains(labelCancel).should('be.visible');

    cy.makeSnapshot(
      'displays the disacknowledgment modal when the "Disacknowledge" button is clicked'
    );

    cy.interceptAPIRequest({
      alias: 'sendDisacknowledgeAction',
      method: Method.DELETE,
      path: `${resourcesEndpoint}/acknowledgements`,
      statusCode: 204
    });

    cy.findByTestId('Confirm').click();
    cy.waitForRequest('@sendDisacknowledgeAction');

    cy.getRequestCalls('@sendDisacknowledgeAction').then((calls) => {
      expect(calls).to.have.length(1);
    });

    cy.contains(labelDisacknowledgementCommandSent);

    cy.makeSnapshot('sends the disacknowledgment action');
  });

  it('displays the downtime modal when the "Downtime" button is clicked and sends the downtime action', () => {
    const now = new Date(2023, 1, 14, 10, 55);
    cy.clock(now);
    const store = getStore();

    interceptDetailsRequest({
      alias: 'getDetailsWithoutDowntime',
      dataPath: 'resources/details/tabs/details/detailsWithoutDownTime.json',
      store
    });

    const defaultEndDate = dayjs(now).add(3600, 'seconds').toDate();
    const startTime = dayjs.tz(now, 'Europe/Paris').format('L LT');
    const endTime = dayjs.tz(defaultEndDate, 'Europe/Paris').format('L LT');

    initialize(store);

    cy.waitForRequest('@getDetailsWithoutDowntime');
    cy.contains('Critical').should('be.visible');

    cy.findByTestId('mainSetDowntime')
      .should('be.visible')
      .should('be.enabled')
      .click();

    cy.findByTestId('dialogDowntime').should('be.visible');

    cy.contains(labelDowntime).should('be.visible');
    cy.findByDisplayValue(startTime).should('be.visible');
    cy.contains(labelTo);
    cy.findByDisplayValue(endTime).should('be.visible');
    cy.contains(labelDuration).should('be.visible');
    cy.contains(labelFixed).should('be.visible');
    cy.contains(labelComment).should('be.visible');
    cy.contains(labelCancel).should('be.visible');
    cy.contains(labelSetDowntime).should('be.visible');

    cy.makeSnapshot(
      'displays the downtime modal when the "Downtime" button is clicked'
    );

    cy.interceptAPIRequest({
      alias: 'sendDowntimeAction',
      method: Method.POST,
      path: `${resourcesEndpoint}/downtime`,
      statusCode: 204
    });

    cy.findByTestId('Confirm').click();
    cy.waitForRequest('@sendDowntimeAction');

    cy.getRequestCalls('@sendDowntimeAction').then((calls) => {
      expect(calls).to.have.length(1);
    });

    cy.contains(labelDowntimeCommandSent);

    cy.makeSnapshot('sends the downtime action');
  });

  it('sends the forced/check command when it is chosen and clicked', () => {
    const store = getStore();

    interceptDetailsRequest({
      alias: 'getDetails',
      dataPath: 'resources/details/tabs/details/details.json',
      store
    });

    cy.interceptAPIRequest({
      alias: 'sendForcedCheckCommand',
      method: Method.POST,
      path: `${resourcesEndpoint}/check`,
      statusCode: 204
    });

    cy.interceptAPIRequest({
      alias: 'sendCheckCommand',
      method: Method.POST,
      path: `${resourcesEndpoint}/check`,
      statusCode: 204
    });

    initialize(store);

    cy.waitForRequest('@getDetails');
    cy.contains('Critical').should('be.visible');

    cy.findByTestId('mainCheck').should('be.visible').should('be.enabled');
    cy.findByTestId('arrow').click();
    cy.findByRole('tooltip').should('be.visible').as('list');

    cy.get('@list').contains(labelCheck).should('be.visible');
    cy.get('@list')
      .contains(labelResourceDetailsCheckDescription)
      .should('be.visible');
    cy.get('@list').contains(labelForcedCheck).should('be.visible');
    cy.get('@list')
      .contains(labelResourceDetailsForcedCheckDescription)
      .should('be.visible');

    cy.contains(labelForcedCheck).click();
    cy.findByTestId('arrow').click();
    cy.get('@list').should('not.exist');

    cy.findByTestId('mainCheck').click();
    cy.waitForRequest('@sendForcedCheckCommand');

    cy.getRequestCalls('@sendForcedCheckCommand').then((calls) => {
      expect(calls).to.have.length(1);
    });

    cy.contains(labelResourceDetailsForcedCheckCommandSent);

    cy.makeSnapshot('sends forced check command');

    cy.findByTestId('arrow').click();
    cy.findByRole('tooltip').should('be.visible').as('list');

    cy.get('@list').contains(labelCheck).click();
    cy.findByTestId('arrow').click();
    cy.get('@list').should('not.exist');

    cy.findByTestId('mainCheck').click();

    cy.waitForRequest('@sendCheckCommand');

    cy.contains(labelResourceDetailsCheckCommandSent);
    cy.makeSnapshot('sends check command');
  });

  it('displays the comment area when the corresponding button is clicked', () => {
    initializeTimeLine({});
    cy.waitForRequest('@getDetails');
    cy.findByTestId(2).click();
    cy.waitForRequest('@getTimeLine');
    cy.contains('Critical').should('be.visible');

    cy.findByTestId('addComment')
      .should('be.visible')
      .should('be.enabled')
      .click();

    cy.findByTestId('commentArea').should('be.visible');
    cy.findByTestId(labelCancel).should('be.visible').should('be.enabled');
    cy.findByTestId(labelSave).should('be.visible').should('be.disabled');
    cy.findByTestId('headerWrapper').scrollIntoView();

    cy.makeSnapshot();
  });

  [
    {
      fixtureDetails: 'resources/details/tabs/details/detailsByHostType.json',
      resourceType: 'host'
    },
    {
      fixtureDetails: 'resources/details/tabs/details/details.json',
      resourceType: 'service'
    },
    {
      fixtureDetails:
        'resources/details/tabs/details/detailsByMetaServiceType.json',
      resourceType: 'meta-service'
    }
  ].forEach(({ resourceType, fixtureDetails }) => {
    it(`submits the comment  for the resource of type ${resourceType} when the comment textfield is typed into and the corresponding button is clicked`, () => {
      initializeTimeLine({ fixtureDetails });
      cy.interceptAPIRequest({
        alias: 'sendsCommentRequest',
        method: Method.POST,
        path: commentEndpoint,
        statusCode: 204
      });
      cy.waitForRequest('@getDetails');
      cy.findByTestId(2).click();

      cy.waitForRequest('@getTimeLine');
      cy.contains('Critical').should('be.visible');

      cy.findByTestId('addComment')
        .should('be.visible')
        .should('be.enabled')
        .click();

      cy.findByTestId('commentArea').type('comment from centreon web');
      cy.findByTestId(labelCancel).should('be.visible').should('be.enabled');
      cy.findByTestId(labelSave)
        .should('be.visible')
        .should('be.enabled')
        .click();

      cy.waitForRequest('@sendsCommentRequest');

      cy.getRequestCalls('@sendsCommentRequest').then((calls) => {
        expect(calls).to.have.length(1);
      });

      cy.contains(labelYourCommentSent);
      cy.findByTestId('headerWrapper').scrollIntoView();

      cy.makeSnapshot();
    });
  });

  it('hides the comment area when the cancel button is clicked', () => {
    initializeTimeLine({});
    cy.waitForRequest('@getDetails');
    cy.findByTestId(2).click();

    cy.waitForRequest('@getTimeLine');
    cy.contains('Critical').should('be.visible');

    cy.findByTestId('addComment')
      .should('be.visible')
      .should('be.enabled')
      .click();

    cy.findByTestId('commentArea').should('exist');

    cy.findByTestId(labelSave).should('be.visible').should('be.disabled');
    cy.findByTestId(labelCancel)
      .should('be.visible')
      .should('be.enabled')
      .click();
    cy.findByTestId('commentArea').should('not.exist');
  });
});
