import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  isOnPublicPageAtom,
  platformFeaturesAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import { SortOrder } from '../../../models';
import { getPublicWidgetEndpoint } from '../../../utils';
import { DisplayType } from '../Listing/models';
import ResourcesTable from '../ResourcesTable';
import {
  closeTicketEndpoint,
  resourcesEndpoint,
  viewByHostEndpoint
} from '../api/endpoints';
import type { Data, PanelOptions } from '../models';

import {
  labelCloseATicket,
  labelCloseTicket,
  labelConfirm,
  labelTicketClosed,
  labelTicketWillBeClosedInTheProvider
} from '../Listing/translatedLabels';
import {
  columnsForViewByHost,
  columnsForViewByService,
  metaServiceResources,
  resources,
  options as resourcesOptions,
  selectedColumnIds
} from './testUtils';

interface Props {
  data: Data;
  isPublic?: boolean;
  options: PanelOptions;
}
const platformFeatures = {
  featureFlags: {
  },
  isCloudPlatform: false
};

const platformVersions = {
  isCloudPlatform: false,
  modules: {
    'centreon-open-tickets': {
      fix: '0',
      major: '24',
      minor: '10',
      version: '23.10.0'
    }
  },
  web: {
    fix: '0',
    major: '23',
    minor: '10',
    version: '23.10.0'
  },
  widgets: {}
};

const store = createStore();
const render = ({ options, data, isPublic = false }: Props): void => {
  store.set(isOnPublicPageAtom, isPublic);

  cy.window().then((window) => {
    cy.stub(window, 'open').as('windowOpen');
  });

  cy.viewport('macbook-11');

  cy.mount({
    Component: (
      <BrowserRouter>
        <TestQueryProvider>
          <SnackbarProvider>
            <Provider store={store}>
              <div style={{ height: '100vh', width: '100%' }}>
                <ResourcesTable
                  dashboardId={1}
                  globalRefreshInterval={{
                    interval: 30,
                    type: 'manual'
                  }}
                  id="1"
                  panelData={data}
                  panelOptions={options}
                  playlistHash="hash"
                  refreshCount={0}
                />
              </div>
            </Provider>
          </SnackbarProvider>
        </TestQueryProvider>
      </BrowserRouter>
    )
  });
};

const resourcesRequests = (): void => {
  cy.fixture('Widgets/ResourcesTable/resourcesStatus.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getResources',
      method: Method.GET,
      path: `./api/latest${resourcesEndpoint}?page=1**`,
      response: data
    });

    cy.interceptAPIRequest({
      alias: 'getPublicWidget',
      method: Method.GET,
      path: `./api/latest${getPublicWidgetEndpoint({
        dashboardId: 1,
        playlistHash: 'hash',
        widgetId: '1'
      })}?&limit=40&page=1&sort_by=%7B%22status%22%3A%22desc%22%7D`,
      response: data
    });
  });

  cy.fixture('Widgets/ResourcesTable/resourecesStatusViewByHost.json').then(
    (data) => {
      cy.interceptAPIRequest({
        alias: 'getResourcesByHost',
        method: Method.GET,
        path: `./api/latest${viewByHostEndpoint}?page=1**`,
        response: data
      });
    }
  );

  cy.fixture('Widgets/ResourcesTable/acknowledgement.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getAcknowledgement',
      method: Method.GET,
      path: '**acknowledgements**',
      response: data
    });
  });
  cy.fixture('Widgets/ResourcesTable/downtime.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getDowntime',
      method: Method.GET,
      path: '**downtimes**',
      response: data
    });
  });

  cy.interceptAPIRequest({
    alias: 'postTicketClose',
    method: Method.POST,
    path: closeTicketEndpoint,
    response: { code: 0, msg: 'Ticket closed: 12' }
  });
};

const verifyListingRows = (): void => {
  cy.contains('Centreon-Server').should('be.visible');
  cy.contains('Disk-/').should('be.visible');
  cy.contains('Load').should('be.visible');
  cy.contains('Memory').should('be.visible');
  cy.contains('Ping').should('be.visible');
};

describe('Public widget', () => {
  beforeEach(resourcesRequests);

  it('sends a request to the public API when the widget is displayed in a public page', () => {
    render({
      data: { resources },
      isPublic: true,
      options: resourcesOptions
    });

    cy.waitForRequest('@getPublicWidget');
  });
});

describe('View by all', () => {
  beforeEach(resourcesRequests);

  it('retrieves resources', () => {
    render({ data: { resources }, options: resourcesOptions });

    cy.waitForRequest('@getResources');
    verifyListingRows();

    cy.makeSnapshot();
  });

  it('executes a listing request with limit from widget properties', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, limit: 30 }
    });

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: 30 }],
      requestAlias: 'getResources'
    });

    cy.contains(30).should('exist');

    cy.makeSnapshot();
  });

  it('displays listing with columns from widget selected columns properties', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, selectedColumnIds }
    });

    cy.contains('Ping').should('exist');

    cy.makeSnapshot();
  });

  it('verify that acknowledge resources row are correctly displayed with the right background color', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, states: ['acknowledged'] }
    });

    cy.contains('Load')
      .parent()
      .parent()
      .should('have.css', 'background-color', 'rgb(223, 210, 185)');

    cy.makeSnapshot();
  });

  it('verify that downtime resources row are correctly displayed with the right background color', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, states: ['in_downtime'] }
    });

    cy.contains('Disk-/')
      .parent()
      .parent()
      .should('have.css', 'background-color', 'rgb(229, 216, 243)');

    cy.makeSnapshot();
  });

  it('displays acknowledge informations when the corresponding icon is hovered', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, limit: 100, states: ['acknowledged'] }
    });

    cy.findByLabelText('Load Acknowledged').trigger('mouseover');

    cy.contains('Author');
    cy.contains('admin');

    cy.contains('Comment');
    cy.contains('Acknowledged by admin');

    cy.makeSnapshot();
  });

  it('displays downtime informations when the corresponding icon is hovered', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, limit: 10, states: ['in_downtime'] }
    });

    cy.waitForRequest('@getResources');

    cy.get('[aria-label="Disk-/ In downtime"]').trigger('mouseover');

    cy.contains('Author');
    cy.contains('admin');

    cy.contains('Comment');
    cy.contains('Downtime set by admin');

    cy.makeSnapshot();
  });

  it('executes a listing request with sort_by param from widget properties', () => {
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        sortField: 'name',
        sortOrder: SortOrder.Desc
      }
    });

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'sort_by', value: '{"name":"desc"}' }],
      requestAlias: 'getResources'
    });

    cy.makeSnapshot();
  });

  it('executes a listing request with resources type filter defined in widget properties', () => {
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        limit: 30,
        sortField: 'status',
        sortOrder: SortOrder.Desc
      }
    });

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'types', value: '["host","service","metaservice"]' }],
      requestAlias: 'getResources'
    });

    cy.makeSnapshot();
  });

  it('executes a listing request with hostgroup_names filter defined in widget properties', () => {
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        limit: 50
      }
    });

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'hostgroup_names', value: '["HG1","HG2"]' }],
      requestAlias: 'getResources'
    });

    cy.makeSnapshot();
  });

  it('executes a listing request with downtime state defined in the widget properties', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, states: ['in_downtime'] }
    });

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'states', value: '["in_downtime"]' }],
      requestAlias: 'getResources'
    });

    cy.makeSnapshot();
  });
  it('executes a listing request with an status from widget properties', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, limit: 40 }
    });

    cy.waitForRequestAndVerifyQueries({
      queries: [
        {
          key: 'statuses',
          value: '["OK","UP","DOWN","CRITICAL","UNREACHABLE","UNKNOWN"]'
        }
      ],
      requestAlias: 'getResources'
    });

    cy.makeSnapshot();
  });

  it('redirects to the meta service panel when a meta service row is clicked', () => {
    render({
      data: { resources: metaServiceResources },
      options: {
        ...resourcesOptions,
        limit: 50
      }
    });

    cy.waitForRequestAndVerifyQueries({
      queries: [
        {
          key: 'search',
          value: '{"$and":[{"$or":[{"name":{"$rg":"^Meta service$"}}]}]}'
        }
      ],
      requestAlias: 'getResources'
    });

    cy.contains('SA_Total_FW_Connexion').click();

    cy.get('@windowOpen').should(
      'have.been.calledWith',
      '/monitoring/resources?details=%7B%22id%22%3A6%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fmetaservices%2F6%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22details%22%2C%22tabParameters%22%3A%7B%7D%2C%22uuid%22%3A%22m6%22%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%2C%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%2C%7B%22id%22%3A%22metaservice%22%2C%22name%22%3A%22Meta%20service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22OK%22%2C%22name%22%3A%22Ok%22%7D%2C%7B%22id%22%3A%22UP%22%2C%22name%22%3A%22Up%22%7D%2C%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%2C%7B%22id%22%3A%22UNREACHABLE%22%2C%22name%22%3A%22Unreachable%22%7D%2C%7B%22id%22%3A%22UNKNOWN%22%2C%22name%22%3A%22Unknown%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbMeta%20service%5C%5Cb%22%2C%22name%22%3A%22Meta%20service%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
    );
  });
});

describe('View by service', () => {
  beforeEach(() => {
    resourcesRequests();
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        displayType: DisplayType.Service,
        limit: 20
      }
    });
  });

  it('retrieves resources', () => {
    cy.waitForRequest('@getResources');

    verifyListingRows();

    cy.makeSnapshot();
  });
  it('executes a listing request with limit from widget properties', () => {
    cy.contains(20);

    verifyListingRows();

    cy.makeSnapshot();
  });
  it('displays listing with columns from widget properties', () => {
    columnsForViewByService.forEach((element) => {
      cy.contains(element);
    });

    verifyListingRows();

    cy.makeSnapshot();
  });
});

describe('View by host', () => {
  beforeEach(() => {
    resourcesRequests();
    render({
      data: { resources },
      options: { ...resourcesOptions, displayType: DisplayType.Host, limit: 30 }
    });
  });

  it('retrieves resources', () => {
    cy.waitForRequest('@getResourcesByHost');

    cy.findByTestId('ExpandMoreIcon').click();

    verifyListingRows();
    cy.contains('Centreon-Server').should('be.visible');

    cy.makeSnapshot();
  });
  it('executes a listing request with limit from widget properties', () => {
    cy.contains('Centreon-Server').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays listing with columns from widget properties', () => {
    columnsForViewByHost.forEach((element) => {
      cy.contains(element);
    });

    cy.contains('Centreon-Server').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Open tickets', () => {
  beforeEach(() => {
    store.set(platformFeaturesAtom, platformFeatures);
    store.set(platformVersionsAtom, platformVersions);
    resourcesRequests();
  });

  it('displays tickets actions when openticket switch is enabled and a rule is selected', () => {
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        displayResources: 'withoutTicket',
        isOpenTicketEnabled: true,
        provider: { id: 1, name: 'Rule 1' },
        selectedColumnIds: [...selectedColumnIds, 'open_ticket']
      }
    });

    cy.waitForRequest('@getResources');

    cy.contains('Ticket').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays tickets "id","subject" and "open time" when openticket switch is enabled, a rule is selected and display resources property is set to "withTicket"', () => {
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        displayResources: 'withTicket',
        isOpenTicketEnabled: true,
        provider: { id: 1, name: 'Rule 1' },
        selectedColumnIds: [
          ...selectedColumnIds,
          'ticket_id',
          'ticket_subject',
          'ticket_open_time',
          'action'
        ]
      }
    });

    cy.waitForRequest('@getResources');

    cy.contains('Ticket ID');
    cy.contains('Ticket subject');
    cy.contains('Opened on');
    cy.contains('Action').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a confirmation modal when a close ticket button is clicked for a service', () => {
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        displayResources: 'withTicket',
        isOpenTicketEnabled: true,
        provider: { id: 1, name: 'Rule 1' },
        selectedColumnIds: [
          ...selectedColumnIds,
          'ticket_id',
          'ticket_subject',
          'ticket_open_time',
          'action'
        ]
      }
    });

    cy.waitForRequest('@getResources');

    cy.contains('Action').should('be.visible');

    cy.findAllByLabelText(labelCloseTicket).eq(0).click();

    cy.contains(labelCloseATicket).should('be.visible');
    cy.contains(labelTicketWillBeClosedInTheProvider).should('be.visible');

    cy.contains(labelConfirm).click();

    cy.waitForRequest('@postTicketClose').then(({ request }) => {
      expect(request.body).equal(
        JSON.stringify({
          data: {
            selection: '14;19',
            rule_id: '1'
          }
        })
      );
    });

    cy.contains(labelTicketClosed).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a confirmation modal when a close ticket button is clicked for a host', () => {
    render({
      data: { resources },
      options: {
        ...resourcesOptions,
        displayResources: 'withTicket',
        isOpenTicketEnabled: true,
        provider: { id: 1, name: 'Rule 1' },
        selectedColumnIds: [
          ...selectedColumnIds,
          'ticket_id',
          'ticket_subject',
          'ticket_open_time',
          'action'
        ]
      }
    });

    cy.waitForRequest('@getResources');

    cy.contains('Action').should('be.visible');

    cy.findAllByLabelText(labelCloseTicket).eq(2).click();

    cy.contains(labelCloseATicket).should('be.visible');
    cy.contains(labelTicketWillBeClosedInTheProvider).should('be.visible');

    cy.contains(labelConfirm).click();

    cy.waitForRequest('@postTicketClose').then(({ request }) => {
      expect(request.body).equal(
        JSON.stringify({
          data: {
            selection: '6',
            rule_id: '1'
          }
        })
      );
    });

    cy.contains(labelTicketClosed).should('be.visible');

    cy.makeSnapshot();
  });
});
