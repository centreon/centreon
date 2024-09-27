import { renderHook } from '@testing-library/react-hooks/dom';
import { Provider, createStore, useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Method, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import useListing from '../Listing/useListing';
import useLoadResources from '../Listing/useLoadResources';
import { resourcesEndpoint } from '../api/endpoint';
import { Visualization } from '../models';
import { defaultStatuses } from '../testUtils';
import {
  labelAcknowledged,
  labelAll,
  labelAllAlerts,
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelHostSeverity,
  labelMetaService,
  labelMonitoringServer,
  labelOk,
  labelSearch,
  labelSearchOptions,
  labelService,
  labelServiceGroup,
  labelState,
  labelStateFilter,
  labelStatus,
  labelType,
  labelUp
} from '../translatedLabels';

import {
  informationLabel,
  labelShowMoreFilters
} from './criteriasNewInterface/translatedLabels';
import useFilter from './useFilter';

import Filter from '.';

const emptyListData = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

const resourcesByHostTypeName = 'SensorProbe-Datacenter-04';

const resourcesByServiceTypeName = 'nbr-connect';

const pollerName = 'Central';

const hostCategoryName = 'Europe';

const hostSeverityName = 'Priority_1';

const linuxServersHostGroupName = 'Linux-servers';

const webAccessServiceGroupName = 'Web-access';

enum Type {
  checkbox = 'checkbox',
  select = 'select',
  text = 'text'
}

const CriteriaParams = [
  [
    'Basic criterias',
    [
      {
        criteria: labelHost,
        requestToWait: '@GetResourcesByHostType',
        searchValue: `parent_name:${resourcesByHostTypeName} type:host `,
        type: Type.select,
        value: resourcesByHostTypeName
      },
      {
        criteria: labelService,
        requestToWait: '@GetResourcesByServiceType',
        searchValue: `name:${resourcesByServiceTypeName} type:service `,
        type: Type.select,
        value: resourcesByServiceTypeName
      },
      {
        criteria: labelState,
        searchValue: 'state:acknowledged ',
        type: Type.checkbox,
        value: labelAcknowledged
      },
      {
        criteria: labelStatus,
        searchValue: 'status:ok ',
        type: Type.checkbox,
        value: labelOk
      },
      {
        criteria: labelStatus,
        searchValue: 'status:up ',
        type: Type.checkbox,
        value: labelUp
      },
      {
        criteria: labelType,
        searchValue: 'type:metaservice ',
        type: Type.checkbox,
        value: labelMetaService
      },
      {
        criteria: labelHostGroup,
        requestToWait: '@hostGroupsRequest',
        searchValue: `host_group:${linuxServersHostGroupName} `,
        type: Type.select,
        value: linuxServersHostGroupName
      },
      {
        criteria: labelServiceGroup,
        requestToWait: '@serviceGroupsRequest',
        searchValue: `service_group:${webAccessServiceGroupName} `,
        type: Type.select,
        value: webAccessServiceGroupName
      },
      {
        criteria: labelMonitoringServer,
        requestToWait: '@pollersRequest',
        searchValue: `monitoring_server:${pollerName} `,
        type: Type.select,
        value: pollerName
      }
    ]
  ],
  [
    'Extended criterias',
    [
      {
        criteria: labelHostCategory,
        requestToWait: '@hostCategoryRequest',
        searchValue: `host_category:${hostCategoryName} `,
        type: Type.select,
        value: hostCategoryName
      },
      {
        criteria: labelHostSeverity,
        requestToWait: '@hostSeverityRequest',
        searchValue: `host_severity:${hostSeverityName} `,
        type: Type.select,
        value: hostSeverityName
      }
    ]
  ]
];

const customFilters = [
  [
    labelAll,
    {
      resourceTypes: [],
      states: [],
      statusTypes: [],
      statuses: []
    },
    ''
  ],
  [
    labelAllAlerts,
    {
      resourceTypes: [],
      states: [],
      statusTypes: [],
      statuses: defaultStatuses
    },
    'status:warning,down,critical,unknown '
  ]
];

const store = createStore();
const FilterWithLoading = (): JSX.Element => {
  useLoadResources();

  return (
    <Provider store={store}>
      <Filter />
    </Provider>
  );
};

const FilterTest = (): JSX.Element | null => {
  useListing();
  useFilter();

  return <FilterWithLoading />;
};

const FilterWithProvider = (): JSX.Element => (
  <TestQueryProvider>
    <FilterTest />
  </TestQueryProvider>
);

before(() => {
  const userData = renderHook(() => useAtomValue(userAtom));

  userData.result.current.timezone = 'Europe/Paris';
  userData.result.current.locale = 'en_US';
});

interface SetupIntercept {
  alias: string;
  fixtureFile?: string;
  method?: Method;
  path: string;
  query?: { name: string; value: string };
}

const setupIntercept = ({
  alias,
  method = Method.GET,
  path,
  fixtureFile,
  query
}: SetupIntercept): void => {
  const body = !query
    ? { alias, method, path }
    : { alias, method, path, query };

  if (!fixtureFile) {
    cy.interceptAPIRequest({
      ...body,
      response: emptyListData
    });

    return;
  }
  cy.fixture(fixtureFile).then((response) => {
    cy.interceptAPIRequest({
      ...body,
      response
    });
  });
};

const initializeRequests = (): void => {
  setupIntercept({ alias: 'filterRequest', path: '**/events-view*' });

  setupIntercept({
    alias: 'getResources',
    path: `${resourcesEndpoint}**`,
    query: {
      name: 'page',
      value: '1'
    }
  });

  setupIntercept({
    alias: 'GetResourcesByHostType',
    fixtureFile: 'resources/filter/resourcesByHostType.json',
    path: `${resourcesEndpoint}**`,
    query: {
      name: 'types',
      value: '["host"]'
    }
  });

  setupIntercept({
    alias: 'GetResourcesByServiceType',
    fixtureFile: 'resources/filter/resourcesByServiceType.json',
    path: `${resourcesEndpoint}**`,
    query: {
      name: 'types',
      value: '["service"]'
    }
  });

  setupIntercept({
    alias: 'pollersRequest',
    fixtureFile: 'resources/filter/pollers/pollers.json',
    path: `**/monitoring/servers?*`
  });

  setupIntercept({
    alias: 'hostGroupsRequest',
    fixtureFile: 'resources/filter/hostGroups.json',
    path: `**/hostgroups?*`
  });

  setupIntercept({
    alias: 'serviceGroupsRequest',
    fixtureFile: 'resources/filter/webAccessServiceGroup.json',
    path: `**/servicegroups?*`
  });

  setupIntercept({
    alias: 'hostCategoryRequest',
    fixtureFile: 'resources/filter/hostCategory.json',
    path: `**/monitoring/hosts/categories?*`
  });

  setupIntercept({
    alias: 'hostSeverityRequest',
    fixtureFile: 'resources/filter/hostSeverity.json',
    path: `**/monitoring/severities/host?*`
  });
};

describe('Custom filters', () => {
  beforeEach(() => {
    initializeRequests();

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);
  });

  customFilters.forEach(([filterGroup, criterias, search]) => {
    it(`executes a listing request with ${filterGroup} parameters when ${JSON.stringify(
      criterias
    )} filter is set`, () => {
      cy.waitForRequest('@filterRequest');

      cy.findByLabelText(labelStateFilter).click();

      cy.findByText(filterGroup).click();

      cy.findByPlaceholderText(labelSearch).should('have.value', search);

      cy.makeSnapshot();
    });
  });
});

describe('Criterias', () => {
  beforeEach(() => {
    initializeRequests();

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);
  });

  it(`displays the basic criterias interface`, () => {
    cy.waitForRequest('@filterRequest');

    cy.findByPlaceholderText(labelSearch).clear();
    cy.findByLabelText(labelSearchOptions).click();

    cy.makeSnapshot();

    cy.findByLabelText(labelSearchOptions).click();
  });
  it(`displays more criterias interface when the corresponding button is clicked`, () => {
    cy.waitForRequest('@filterRequest');

    cy.findByPlaceholderText(labelSearch).clear();
    cy.findByLabelText(labelSearchOptions).click();

    cy.findByText(labelShowMoreFilters).click();

    cy.makeSnapshot();

    cy.findByLabelText(labelSearchOptions).click();
  });

  CriteriaParams.forEach(([label, data]) => {
    data.forEach((element) => {
      const { criteria, value, type, searchValue, requestToWait } = element;

      it(`synchronize the search bar with ${label} interface when selecting ${criteria} criteria value `, () => {
        cy.waitForRequest('@filterRequest');
        cy.waitForRequest('@getResources');
        cy.get('[data-testid="Filter options"]').click();

        if (equals(label, 'Extended criterias')) {
          cy.findByText(labelShowMoreFilters).click();
        }

        if (equals(type, Type.select)) {
          cy.findByTestId(criteria).click();
          cy.waitForRequest(requestToWait);
          cy.findByText(value).click();
          cy.findByPlaceholderText(labelSearch).should(
            'have.value',
            searchValue
          );

          cy.makeSnapshot();

          cy.findByLabelText(labelSearchOptions).click();
          cy.findByPlaceholderText(labelSearch).clear();

          return;
        }
        if (equals(type, Type.checkbox)) {
          cy.findByText(value).click();
          cy.findByTestId('CheckBoxIcon').should('be.visible');
          cy.findByPlaceholderText(labelSearch).should(
            'have.value',
            searchValue
          );
          cy.makeSnapshot();

          cy.findByText(value).click();
          cy.findByTestId('CheckBoxIcon').should('not.exist');

          cy.findByPlaceholderText(labelSearch).should(
            'not.have.value',
            searchValue
          );

          cy.findByLabelText(labelSearchOptions).click();
          cy.findByPlaceholderText(labelSearch).clear();
        }
      });
    });
  });

  it('syncs the information fields with the search bar', () => {
    cy.waitForRequest('@filterRequest');

    cy.findByPlaceholderText(labelSearch).clear();
    cy.findByLabelText(labelSearchOptions).click();

    cy.findByText(labelShowMoreFilters).click();

    cy.findByPlaceholderText(informationLabel).type('Information');

    cy.findByPlaceholderText(labelSearch).should(
      'have.value',
      ' information:Information'
    );

    cy.findByPlaceholderText(informationLabel).clear();

    cy.findByPlaceholderText(labelSearch).should('have.value', ' ');

    cy.findByLabelText(labelSearchOptions).click();
  });

  it('does not display the host select and host statuses when the view by host is enabled', () => {
    store.set(selectedVisualizationAtom, Visualization.Host);

    cy.findByPlaceholderText(labelSearch).clear();
    cy.findByLabelText(labelSearchOptions).click();

    cy.findByTestId('Host').should('not.exist');
    cy.findByText(labelUp, { exact: true }).should('not.exist');

    cy.makeSnapshot();

    cy.findByLabelText(labelSearchOptions).click();
  });
});

// The backend does not consistently handle the creation of resources with spaces
// (for some resources, it adds an underscore, while for others it does not, such as with pollers..)

describe('Replaces whitespace with the \\s regex pattern', () => {
  const pollerNameWithSpace = 'Poller test';
  const searchedValue = 'monitoring_server:Poller\\stest';

  beforeEach(() => {
    initializeRequests();

    cy.mount({
      Component: <FilterWithProvider />
    });

    setupIntercept({
      alias: 'pollersWithSpaceOnNameRequest',
      fixtureFile: 'resources/filter/pollers/pollersWithSpaceOnName.json',
      path: '**/monitoring/servers?*'
    });
    cy.viewport(1200, 1000);
  });

  it('replaces whitespace with the \\s regex pattern in the search bar when selecting values from the criterias interface', () => {
    cy.findByLabelText(labelSearchOptions).click();
    cy.findByTestId(labelMonitoringServer).click();

    cy.waitForRequest('@pollersWithSpaceOnNameRequest');

    cy.findByRole('option', { name: pollerNameWithSpace }).click();
    cy.findByTestId(labelMonitoringServer)
      .parent()
      .contains(pollerNameWithSpace);
    cy.findByPlaceholderText(labelSearch)
      .invoke('val')
      .should('equal', `${searchedValue} `);

    cy.makeSnapshot();

    cy.findByLabelText(labelSearchOptions).click();
    cy.findByPlaceholderText(labelSearch).clear();
  });

  it('replaces whitespace with the \\s regex pattern in the search bar when selecting values from the suggestions interface', () => {
    const key = 'monitoring_server:';

    cy.findByPlaceholderText(labelSearch).type(key);

    cy.waitForRequest('@pollersWithSpaceOnNameRequest');

    cy.findByRole('menuitem', { name: pollerNameWithSpace }).click();

    cy.findByPlaceholderText(labelSearch)
      .invoke('val')
      .should('equal', searchedValue);

    cy.makeSnapshot();

    cy.findByPlaceholderText(labelSearch).clear();
  });
});

describe('Keyboard actions', () => {
  beforeEach(() => {
    initializeRequests();

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);
  });

  it('accepts the selected autocomplete suggestion when the beginning of a criteria is input and the "enter" key is pressed', () => {
    cy.waitForRequest('@getResources');

    const searchBar = cy.findByPlaceholderText(labelSearch);

    searchBar.clear();

    searchBar.type('stat').type('{enter}');
    searchBar.should('have.value', 'state:');

    searchBar.type('u').type('{enter}');

    searchBar.should('have.value', 'state:unhandled');

    searchBar.type(' st').type('{enter}');

    searchBar.should('have.value', 'state:unhandled status:');

    searchBar.type(' type:');
    searchBar.type('{downArrow}').type('{enter}');

    searchBar.should('have.value', 'state:unhandled status: type:service');

    cy.makeSnapshot();
  });

  it(`accepts the selected autocomplete suggestion when the beginning of a dynamic criteria is input and the "enter" key is pressed`, () => {
    const searchBar = cy.findByPlaceholderText(labelSearch);

    searchBar.clear();
    searchBar.type('host');
    searchBar.type('{Enter}');
    searchBar.should('have.value', 'host_group:');
    searchBar.type('ESX');
    cy.findByText(linuxServersHostGroupName).should('exist');
    searchBar.type('{Enter}');
    cy.findByPlaceholderText(labelSearch).should(
      'have.value',
      `host_group:${linuxServersHostGroupName}`
    );

    searchBar.type(',');
    cy.findByText('Firewall').should('exist');
    searchBar.type('{downArrow}');
    searchBar.type('{Enter}');
    cy.waitForRequest('@hostGroupsRequest');

    cy.makeSnapshot();
  });
});
