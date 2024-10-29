import { Provider, createStore } from 'jotai';
import { equals, last } from 'ramda';
import { BrowserRouter } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { getPublicWidgetEndpoint } from '../../../utils';
import StatusChart from '../StatusChart';
import {
  hostStatusesEndpoint,
  resourcesEndpoint,
  serviceStatusesEndpoint
} from '../api/endpoint';
import { Data, DisplayType, PanelOptions } from '../models';

import {
  hostStatus,
  resources,
  options as resourcesOptions,
  serviceStatus,
  totalHosts,
  totalServices
} from './testUtils';

interface Props {
  data: Data;
  isPublic?: boolean;
  options: PanelOptions;
}

const initialize = ({ options, data, isPublic = false }: Props): void => {
  cy.viewport('macbook-11');
  const store = createStore();
  store.set(isOnPublicPageAtom, isPublic);

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <BrowserRouter>
            <div style={{ height: '90vh', width: '90%' }}>
              <StatusChart
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
          </BrowserRouter>
        </Provider>
      </TestQueryProvider>
    )
  });
};

const interceptRequests = (): void => {
  cy.fixture('Widgets/StatusChart/resourcesStatus.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getResources',
      method: Method.GET,
      path: `./api/latest${resourcesEndpoint}?page=1**`,
      response: data
    });
  });

  cy.fixture('Widgets/StatusChart/hostStatuses.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getResourcesByHost',
      method: Method.GET,
      path: `./api/latest${hostStatusesEndpoint}`,
      response: data
    });

    cy.interceptAPIRequest({
      alias: 'getPublicWidget',
      method: Method.GET,
      path: `./api/latest${getPublicWidgetEndpoint({
        dashboardId: 1,
        extraQueryParameters: '?&resource_type=%22host%22',
        playlistHash: 'hash',
        widgetId: '1'
      })}`,
      response: data
    });
  });

  cy.fixture('Widgets/StatusChart/serviceStatuses.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getResourcesByHost',
      method: Method.GET,
      path: `./api/latest${serviceStatusesEndpoint}`,
      response: data
    });
  });
};

const displayTypes = [
  {
    displayType: DisplayType.Donut,
    label: 'Donut chart'
  },
  {
    displayType: DisplayType.Pie,
    label: 'Pie chart'
  },
  {
    displayType: DisplayType.Horizontal,
    label: 'Horizontal bar'
  },
  {
    displayType: DisplayType.Vertical,
    label: 'Vertical bar'
  }
];

describe('Public widget', () => {
  it('sends a request to the public API when the widget is displayed in a public page', () => {
    interceptRequests();
    initialize({
      data: { resources },
      isPublic: true,
      options: {
        ...resourcesOptions,
        displayType: DisplayType.Donut,
        resourceTypes: ['host']
      }
    });

    cy.waitForRequest('@getPublicWidget');
  });
});

displayTypes.forEach(({ displayType, label }) => {
  describe(label, () => {
    beforeEach(() => {
      cy.clock(new Date(2024, 1, 1, 0, 0, 0), ['Date']);

      interceptRequests();
    });

    it(`displays the widget with the chart of type ${displayType} when the displayType prop is set to ${displayType}`, () => {
      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayType,
          resourceTypes: ['service']
        }
      });

      cy.get(`[data-variant="${displayType}"]`).should('exist');

      cy.makeSnapshot();
    });

    it('displays charts with the default values', () => {
      initialize({
        data: { resources },
        options: { ...resourcesOptions, displayType }
      });

      cy.contains('212');
      cy.contains('hosts');
      cy.contains('678');
      cy.contains('services');

      cy.findAllByTestId('Legend').should('have.length', 2);

      cy.findAllByTestId('value').should('have.length', 9);
      cy.findAllByTestId('value')
        .eq(0)
        .children()
        .eq(0)
        .should('have.text', '19.8%');

      cy.makeSnapshot(`${label} : displays charts with the default values`);
    });

    it(`displays a ${label} for services when the resource type is set to service and displayType to ${displayType}`, () => {
      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayType,
          resourceTypes: ['service']
        }
      });

      cy.findByText('212 hosts').should('not.exist');
      cy.contains('678');
      cy.contains('services');

      cy.makeSnapshot();
    });

    it(`displays a ${label} for hosts when the resource type is set to host and displayType to ${displayType}`, () => {
      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayType,
          resourceTypes: ['host']
        }
      });

      cy.contains('212');
      cy.contains('hosts');
      cy.findByText('678 services').should('not.exist');

      cy.makeSnapshot();
    });

    it('conditionally displays the legend based on displayLegend prop', () => {
      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayLegend: false,
          displayType,
          resourceTypes: ['service']
        }
      });

      cy.findByTestId('Legend').should('not.exist');

      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayLegend: true,
          displayType,
          resourceTypes: ['service']
        }
      });

      cy.findByTestId('Legend').should('be.visible');

      cy.makeSnapshot(
        `${label} : conditionally displays the legend based on displayLegend prop`
      );
    });

    it('conditionally displays values based on displayValues prop', () => {
      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayType,
          displayValues: false,
          resourceTypes: ['service']
        }
      });

      cy.findAllByTestId('value').should('not.exist');

      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayType,
          displayValues: true,
          resourceTypes: ['service']
        }
      });

      cy.findAllByTestId('value')
        .eq(0)
        .children()
        .eq(0)
        .should('have.text', '5.8%');

      cy.makeSnapshot(
        `${label} : conditionally displays values based on displayValues prop`
      );
    });

    it('displays values with the unit "number" when the displayValues is set to true and unit to number', () => {
      initialize({
        data: { resources },
        options: {
          ...resourcesOptions,
          displayType,
          resourceTypes: ['service'],
          unit: 'number'
        }
      });

      cy.findAllByTestId('value')
        .eq(0)
        .children()
        .eq(0)
        .should('have.text', '39');

      cy.makeSnapshot(`${label} : displays values with the unit "number"`);
    });

    describe('Tooltip', () => {
      ['service', 'host'].forEach((resourceType) => {
        it(`displays tooltip with correct information on hover for type ${resourceType}`, () => {
          const statuses = equals(resourceType, 'host')
            ? hostStatus
            : serviceStatus;

          const okStatus = last(statuses);

          const total = equals(resourceType, 'host')
            ? totalHosts
            : totalServices;

          initialize({
            data: { resources },
            options: {
              ...resourcesOptions,
              displayType,
              resourceTypes: [resourceType]
            }
          });

          statuses.slice(0, -1).forEach(({ status, count }) => {
            cy.findByTestId(status).trigger('mouseover', { force: true });

            cy.findByTestId(`tooltip-${status}`)
              .should('contain', `Status: ${status}`)
              .and('contain', `${count} ${resourceType}s`)
              .and('contain', 'Disk-/')
              .and('contain', 'Load')
              .and('contain', 'Ping')
              .and('contain', 'Centreon-Server')
              .and('contain', 'Centreon-Server_17');
          });

          cy.findByTestId(okStatus?.status).trigger('mouseover', {
            force: true
          });

          cy.findByTestId(`tooltip-${okStatus?.status}`).should(
            'contain',
            `${okStatus?.count}/${total} ${resourceType}s are working fine.`
          );

          cy.contains('February 1, 2024').should('be.visible');

          cy.makeSnapshot(
            `${label} : 'displays tooltip with correct information on hover for type ${resourceType}`
          );
        });
      });
    });
  });
});
