import { createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { Method } from '@centreon/ui';

import { SortOrder } from '../../../models';
import { Data, PanelOptions } from '../models';
import ResourcesTable from '..';
import { resourcesEndpoint, viewByHostEndpoint } from '../api/endpoints';
import { DisplayType } from '../Listing/models';

import {
  options as resourcesOptions,
  resources,
  columnsForViewByAll,
  columnsForViewByHost,
  columnsForViewByService,
  selectedColumnIds
} from './testUtils';

interface Props {
  data: Data;
  options: PanelOptions;
}

const store = createStore();

const render = ({ options, data }: Props): void => {
  cy.viewport('macbook-11');

  cy.mount({
    Component: (
      <BrowserRouter>
        <div style={{ height: '100vh', width: '100%' }}>
          <ResourcesTable
            globalRefreshInterval={{
              interval: 30,
              type: 'manual'
            }}
            panelData={data}
            panelOptions={options}
            refreshCount={0}
            store={store}
          />
        </div>
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
};

const verifyListingRows = (): void => {
  cy.contains('Centreon-Server').should('be.visible');
  cy.contains('Disk-/').should('be.visible');
  cy.contains('Load').should('be.visible');
  cy.contains('Memory').should('be.visible');
  cy.contains('Ping').should('be.visible');
};

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

    cy.contains(30);

    cy.makeSnapshot();
  });

  it('displays listing with columns from widget selected columns properties', () => {
    render({
      data: { resources },
      options: { ...resourcesOptions, selectedColumnIds }
    });

    columnsForViewByAll.forEach((element) => {
      cy.contains(element);
    });

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

    cy.findByTestId('PersonIcon').trigger('mouseover');

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
      queries: [{ key: 'types', value: '["host","service"]' }],
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

    cy.makeSnapshot();
  });
  it('displays listing with columns from widget properties', () => {
    columnsForViewByService.forEach((element) => {
      cy.contains(element);
    });

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

    cy.makeSnapshot();
  });
  it('executes a listing request with limit from widget properties', () => {
    cy.contains(30);

    cy.makeSnapshot();
  });

  it('displays listing with columns from widget properties', () => {
    columnsForViewByHost.forEach((element) => {
      cy.contains(element);
    });

    cy.makeSnapshot();
  });
});
