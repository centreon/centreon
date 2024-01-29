import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import {
  Method,
  setUrlQueryParameters,
  SnackbarProvider,
  TestQueryProvider
} from '@centreon/ui';
import { userAtom, refreshIntervalAtom, aclAtom } from '@centreon/ui-context';

import { ResourceType } from '../models';
import {
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
  labelExportToCSV,
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
  labelPerformanceData,
  labelSave,
  labelStatusChangePercentage,
  labelStatusInformation,
  labelTimezone,
  labelTo,
  labelYourCommentSent
} from '../translatedLabels';
import { commentEndpoint } from '../Actions/api/endpoint';

import useDetails from './useDetails';
import useLoadDetails from './useLoadDetails';
import {
  selectedResourceDetailsEndpointDerivedAtom,
  selectedResourcesDetailsAtom
} from './detailsAtoms';

import Details from '.';

const resourceHostId = 1;
const resourceHostType = 'host';
const resourceServiceUuid = 'h1-s1';
const resourceServiceId = 1;
const resourceServiceType = ResourceType.service;
const groups = [
  {
    configuration_uri: '/centreon/main.php?p=60102&o=c&hg_id=53',
    id: 0,
    name: 'Linux-servers'
  }
];

const categories = [
  {
    configuration_uri: '/centreon/main.php?p=60102&o=c&hg_id=53',
    id: 0,
    name: 'Windows'
  }
];

const selectedResource = {
  parentResourceId: undefined,
  parentResourceType: undefined,
  resourceId: resourceServiceId,
  resourcesDetailsEndpoint:
    '/api/latest/monitoring/resources/hosts/1/services/1'
};

const retrievedDetails = {
  acknowledged: false,
  acknowledgement: {
    author_name: 'Admin',
    comment: 'Acknowledged by Admin',
    entry_time: '2020-03-18T18:57:59Z',
    is_persistent: true,
    is_sticky: true
  },
  active_checks: false,
  alias: 'Central-Centreon',
  categories,
  checked: true,
  command_line: 'base_host_alive',
  downtimes: [
    {
      author_name: 'admin',
      comment: 'First downtime set by Admin',
      end_time: '2020-01-18T18:57:59Z',
      entry_time: '2020-01-18T17:57:59Z',
      start_time: '2020-01-18T17:57:59Z'
    },
    {
      author_name: 'admin',
      comment: 'Second downtime set by Admin',
      end_time: '2020-02-18T18:57:59Z',
      entry_time: '2020-01-18T17:57:59Z',
      start_time: '2020-02-18T17:57:59Z'
    }
  ],
  duration: '22m',
  execution_time: 0.070906,
  flapping: true,
  fqdn: 'central.centreon.com',
  groups,
  id: resourceServiceId,
  in_downtime: true,
  information:
    'OK - 127.0.0.1 rta 0.100ms lost 0%\n OK - 127.0.0.1 rta 0.99ms lost 0%\n OK - 127.0.0.1 rta 0.98ms lost 0%\n OK - 127.0.0.1 rta 0.97ms lost 0%',
  last_check: '2020-05-18T16:00Z',
  last_notification: '2020-07-18T17:30:00Z',
  last_status_change: '2020-04-18T15:00Z',
  last_time_with_no_issue: '2021-09-23T15:49:50+02:00',
  last_update: '2020-03-18T16:30:00Z',
  latency: 0.005,
  links: {
    endpoints: {
      details: '/centreon/api/latest/monitoring/resources/hosts/1/services/1',
      notification_policy: 'notification_policy',
      performance_graph: 'performance_graph',
      timeline: 'timeline',
      timeline_download: 'timeline/download'
    },
    externals: {
      action_url: undefined,
      notes: undefined
    },
    uris: {
      configuration: undefined,
      logs: undefined,
      reporting: undefined
    }
  },
  monitoring_server_name: 'Poller',
  name: 'Central',
  next_check: '2020-06-18T17:15:00Z',
  notification_number: 3,
  parent: {
    id: resourceHostId,
    links: {
      endpoints: {
        performance_graph: 'performance_graph',
        timeline: 'timeline'
      },
      externals: {
        action_url: undefined,
        notes: undefined
      },
      uris: {
        configuration: undefined,
        logs: undefined,
        reporting: undefined
      }
    },
    name: 'Centreon',
    short_type: 'h',
    status: { name: 'S1', severity_code: 1 },
    type: resourceHostType,
    uuid: 'h1'
  },
  passive_checks: false,
  percent_state_change: 3.5,
  performance_data:
    'rta=0.025ms;200.000;400.000;0; rtmax=0.061ms;;;; rtmin=0.015ms;;;; pl=0%;20;50;0;100',
  status: { name: 'Critical', severity_code: 1 },
  timezone: 'Europe/Paris',
  tries: '3/3 (Hard)',
  type: resourceServiceType,
  uuid: resourceServiceUuid
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

const mockRefreshInterval = 60;
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

const DetailsTest = (): JSX.Element => {
  useDetails();
  useLoadDetails();

  return (
    <div style={{ height: '100vh' }}>
      <Details />
    </div>
  );
};

const initialize = (): void => {
  cy.viewport('macbook-13');

  const store = createStore();
  store.set(userAtom, retrievedUser);
  store.set(aclAtom, mockAcl);
  store.set(refreshIntervalAtom, mockRefreshInterval);
  store.set(selectedResourcesDetailsAtom, selectedResource);

  setUrlQueryParameters([
    {
      name: 'details',
      value: serviceDetailsUrlParameters
    }
  ]);

  const selectedResourceDetailsEndpoint = store.get(
    selectedResourceDetailsEndpointDerivedAtom
  );

  cy.interceptAPIRequest({
    alias: 'getDetails',
    method: Method.GET,
    path: `**/${selectedResourceDetailsEndpoint}`,
    response: retrievedDetails
  });

  cy.fixture('resources/details/tabs/timeLine/timeLine.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getTimeLine',
      method: Method.GET,
      path: `**/timeline**`,
      response: data
    });
  });

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

describe('Details', () => {
  beforeEach(() => {
    initialize();
  });
  it('displays resource details information', () => {
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
  });

  it('displays the timeLine tab when the corresponding tab is clicked', () => {
    cy.waitForRequest('@getDetails');
    cy.findByTestId(2).click();
    cy.waitForRequest('@getTimeLine');
    cy.contains('Critical').should('be.visible');

    cy.findByTestId('addComment').should('be.visible').should('be.enabled');
    cy.findByTestId(labelExportToCSV).should('be.visible').should('be.enabled');

    cy.makeSnapshot();
  });

  it('displays the comment area when the corresponding button is clicked', () => {
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

    cy.makeSnapshot();
  });

  it('submits the comment when the comment textfield is typed and the corresponding button is clicked', () => {
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

    cy.makeSnapshot();
  });

  it('hides the comment area when clicking on "Cancel" button', () => {
    initialize();
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

    cy.makeSnapshot();
  });
});
