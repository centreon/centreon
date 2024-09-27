import { Provider, createStore } from 'jotai';
import { equals } from 'ramda';

import { Method, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { retrievedUser } from '../../Main/testUtils';
import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import { enabledAutorefreshAtom } from '../Listing/listingAtoms';
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
  labelNewFilter,
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

import getDefaultCriterias from './Criterias/default';
import { CategoryHostStatus } from './criteriasNewInterface/model';
import {
  informationLabel,
  labelShowMoreFilters
} from './criteriasNewInterface/translatedLabels';
import { applyFilterDerivedAtom } from './filterAtoms';
import { allFilter, unhandledProblemsFilter } from './models';

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

interface FilterComponent {
  store: ReturnType<typeof createStore>;
}

const allViews = [Visualization.All, Visualization.Host, Visualization.Service];

const newFilter = {
  criterias: getDefaultCriterias(),
  id: '',
  name: labelNewFilter
};

const formattedValue = (value: string): RegExp => {
  const splittedValue = value.split(' ');

  return new RegExp(
    `(?:${splittedValue.join('.*')}|${splittedValue.reverse().join('.*')})`
  );
};

const getSearchValue = ({ value, viewName }): RegExp => {
  if (equals(viewName, Visualization.All)) {
    return formattedValue(value);
  }
  if (equals(viewName, Visualization.Service)) {
    return formattedValue(`${value} type:service,metaservice`);
  }

  return formattedValue(`${value} type:host `);
};

const getValueByTypeSelecton = ({ criteria, view, value }): string => {
  if (
    equals(view, Visualization.All) &&
    [labelHost, labelService].includes(criteria)
  ) {
    return equals(criteria, labelHost)
      ? `${value} type:host`
      : `${value} type:service`;
  }

  return value;
};
const CriteriaParams = ({ view }): Array<unknown> => {
  return [
    [
      'Basic criterias',
      [
        {
          criteria: labelHost,
          requestToWait: '@GetResourcesByHostType',
          searchValue: getSearchValue({
            value: getValueByTypeSelecton({
              criteria: labelHost,
              value: `parent_name:${resourcesByHostTypeName}`,
              view
            }),
            viewName: view
          }),
          type: Type.select,
          value: resourcesByHostTypeName,
          views: allViews
        },
        {
          criteria: labelService,
          requestToWait: '@GetResourcesByServiceType',
          searchValue: getSearchValue({
            value: getValueByTypeSelecton({
              criteria: labelService,
              value: `name:${resourcesByServiceTypeName}`,
              view
            }),
            viewName: view
          }),
          type: Type.select,
          value: resourcesByServiceTypeName,
          views: allViews
        },
        {
          criteria: labelState,
          searchValue: getSearchValue({
            value: 'state:acknowledged',
            viewName: view
          }),
          testId: 'states',
          type: Type.checkbox,
          value: labelAcknowledged,
          views: allViews
        },
        {
          criteria: labelStatus,
          searchValue: getSearchValue({ value: 'status:ok', viewName: view }),
          testId: 'statuses-service',
          type: Type.checkbox,
          value: labelOk,
          views: allViews
        },
        {
          criteria: labelStatus,
          searchValue: getSearchValue({ value: 'status:up', viewName: view }),
          testId: 'statuses-host',
          type: Type.checkbox,
          value: labelUp,
          views: [Visualization.All, Visualization.Service]
        },
        {
          criteria: labelType,
          searchValue: getSearchValue({
            value: 'type:metaservice',
            viewName: view
          }),
          testId: 'resource_types',
          type: Type.checkbox,
          value: labelMetaService,
          views: [Visualization.All]
        },
        {
          criteria: labelHostGroup,
          requestToWait: '@hostGroupsRequest',
          searchValue: getSearchValue({
            value: `host_group:${linuxServersHostGroupName}`,
            viewName: view
          }),
          type: Type.select,
          value: linuxServersHostGroupName,
          views: allViews
        },
        {
          criteria: labelServiceGroup,
          requestToWait: '@serviceGroupsRequest',
          searchValue: getSearchValue({
            value: `service_group:${webAccessServiceGroupName}`,
            viewName: view
          }),
          type: Type.select,
          value: webAccessServiceGroupName,
          views: allViews
        },
        {
          criteria: labelMonitoringServer,
          requestToWait: '@pollersRequest',
          searchValue: getSearchValue({
            value: `monitoring_server:${pollerName}`,
            viewName: view
          }),
          type: Type.select,
          value: pollerName,
          views: allViews
        }
      ]
    ],
    [
      'Extended criterias',
      [
        {
          criteria: labelHostCategory,
          requestToWait: '@hostCategoryRequest',
          searchValue: getSearchValue({
            value: `host_category:${hostCategoryName}`,
            viewName: view
          }),
          type: Type.select,
          value: hostCategoryName,
          views: allViews
        },
        {
          criteria: labelHostSeverity,
          requestToWait: '@hostSeverityRequest',
          searchValue: getSearchValue({
            value: `host_severity:${hostSeverityName}`,
            viewName: view
          }),
          type: Type.select,
          value: hostSeverityName,
          views: allViews
        }
      ]
    ]
  ];
};

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

const getStore = (): ReturnType<typeof createStore> => {
  const store = createStore();
  store.set(userAtom, retrievedUser);
  store.set(selectedVisualizationAtom, Visualization.All);
  store.set(applyFilterDerivedAtom, allFilter);
  store.set(enabledAutorefreshAtom, false);

  return store;
};

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
    path: '**/monitoring/servers?*'
  });

  setupIntercept({
    alias: 'hostGroupsRequest',
    fixtureFile: 'resources/filter/hostGroups.json',
    path: '**/hostgroups?*'
  });

  setupIntercept({
    alias: 'serviceGroupsRequest',
    fixtureFile: 'resources/filter/webAccessServiceGroup.json',
    path: '**/servicegroups?*'
  });

  setupIntercept({
    alias: 'hostCategoryRequest',
    fixtureFile: 'resources/filter/hostCategory.json',
    path: '**/monitoring/hosts/categories?*'
  });

  setupIntercept({
    alias: 'hostSeverityRequest',
    fixtureFile: 'resources/filter/hostSeverity.json',
    path: '**/monitoring/severities/host?*'
  });
};

const setView = ({ store, name }): ReturnType<typeof createStore> => {
  store.set(selectedVisualizationAtom, name);
  if (equals(name, Visualization.All)) {
    return store;
  }

  const resourceTypeValue = equals(name, Visualization.Host)
    ? [{ id: 'host', name: labelHost }]
    : [
        { id: 'service', name: labelService },
        { id: 'metaservice', name: labelMetaService }
      ];

  store.set(applyFilterDerivedAtom, {
    ...newFilter,
    criterias: [
      ...newFilter.criterias,
      {
        name: 'resource_types',
        object_type: null,
        type: 'multi_select',
        value: resourceTypeValue
      }
    ]
  });

  return store;
};
const views = [
  { initSearch: '', name: Visualization.All },
  { ids: [labelHost], initSearch: 'type:host ', name: Visualization.Host },
  {
    ids: [labelService, labelMetaService],
    initSearch: 'type:service,metaservice ',
    name: Visualization.Service
  }
];

const checkInterfaceByView = ({ ids, initSearch }): void => {
  ids.forEach((id) => {
    cy.findByTestId('resource_types').find(`#${id}`).should('be.checked');
  });
  cy.findByPlaceholderText(labelSearch).should('have.value', initSearch);
};

const initialize = (): void => {
  cy.findByLabelText(labelSearchOptions).click();
  cy.findByPlaceholderText(labelSearch).clear();
};

const FilterWrapper = ({ store }: FilterComponent): JSX.Element => {
  return (
    <TestQueryProvider>
      <Provider store={store}>
        <Filter />
      </Provider>
    </TestQueryProvider>
  );
};

const mount = ({ store }: FilterComponent): void => {
  initializeRequests();

  cy.mount({
    Component: <FilterWrapper store={store} />
  });

  cy.viewport(1200, 1000);
};

const initializeCustomFilterStore = (store): ReturnType<typeof createStore> => {
  store.set(applyFilterDerivedAtom, unhandledProblemsFilter);

  return store;
};

describe('Custom filters', () => {
  beforeEach(() => {
    const updatedStore = initializeCustomFilterStore(getStore());
    mount({ store: updatedStore });
  });

  customFilters.forEach(([filterGroup, criterias, search]) => {
    it(`executes a listing request with ${filterGroup} parameters when ${JSON.stringify(
      criterias
    )} filter is set`, () => {
      cy.findByLabelText(labelStateFilter).click();

      cy.findByText(filterGroup).click();
      cy.contains(filterGroup);

      cy.findByPlaceholderText(labelSearch).should('have.value', search);

      cy.makeSnapshot();
    });
  });
});

views.forEach(({ name, initSearch, ids }) => {
  describe(`Criterias, view by ${name}`, () => {
    beforeEach(() => {
      const updatedStore = setView({ name, store: getStore() });

      mount({ store: updatedStore });
    });

    it('displays the criterias interface ', () => {
      cy.findByLabelText(labelSearchOptions).click();
      cy.findByText(labelShowMoreFilters).click();

      if (
        equals(name, Visualization.Host) ||
        equals(name, Visualization.Service)
      ) {
        checkInterfaceByView({ ids, initSearch });
      }

      if (equals(name, Visualization.Host)) {
        [
          CategoryHostStatus.UP,
          CategoryHostStatus.DOWN,
          CategoryHostStatus.UNREACHABLE
        ].forEach((status) => {
          cy.get(`#${status}`).should('not.exist');
        });
      }

      cy.makeSnapshot();
      initialize();
    });

    CriteriaParams({ view: name }).forEach(([label, data]) => {
      data.forEach((element) => {
        const {
          criteria,
          value,
          type,
          searchValue,
          requestToWait,
          views: arrayViews,
          testId
        } = element;

        it(`synchronize the search bar with ${label} interface when selecting ${criteria} criteria value`, () => {
          cy.findByLabelText(labelSearchOptions).click();

          if (equals(label, 'Extended criterias')) {
            cy.findByText(labelShowMoreFilters).click();
          }

          if (equals(type, Type.select) && arrayViews?.includes(name)) {
            cy.findByTestId(criteria).click();
            cy.waitForRequest(requestToWait);
            cy.findByText(value).click();
            cy.findByPlaceholderText(labelSearch)
              .invoke('val')
              .should('match', searchValue);

            cy.makeSnapshot();

            initialize();
          }
          if (equals(type, Type.checkbox) && arrayViews?.includes(name)) {
            cy.findByText(value).click();
            cy.findByTestId(testId).find(`#${value}`).should('be.checked');

            cy.findByPlaceholderText(labelSearch)
              .invoke('val')
              .should('match', searchValue);

            cy.makeSnapshot();

            cy.findByText(value).click();
            cy.get(`#${value}`).should('not.be.checked');

            cy.findByPlaceholderText(labelSearch).should(
              'not.have.value',
              searchValue
            );
            initialize();
          }
        });
      });
    });

    it('syncs the information fields with the search bar', () => {
      const matchedValue = getSearchValue({
        value: 'information:Information',
        viewName: name
      });

      const clearedMatchedValue = equals(name, Visualization.Host)
        ? 'type:host  '
        : 'type:service,metaservice  ';

      cy.findByLabelText(labelSearchOptions).click();

      cy.findByText(labelShowMoreFilters).click();

      cy.findByPlaceholderText(informationLabel).type('Information');

      cy.findByPlaceholderText(labelSearch)
        .invoke('val')
        .should('match', matchedValue);

      cy.findByPlaceholderText(informationLabel).clear();

      cy.findByPlaceholderText(labelSearch).should(
        'have.value',
        equals(name, Visualization.All) ? ' ' : clearedMatchedValue
      );

      initialize();
    });
  });
});

//The backend does not consistently handle the creation of resources with spaces
// (for some resources, it adds an underscore, while for others it does not, such as with pollers..)

describe('Replaces whitespace with the \\s regex pattern', () => {
  const pollerName = 'Poller test';
  const searchedValue = 'monitoring_server:Poller\\stest';

  beforeEach(() => {
    const updatedStore = setView({
      name: Visualization.All,
      store: getStore()
    });
    mount({ store: updatedStore });
    setupIntercept({
      alias: 'pollersWithSpaceOnNameRequest',
      fixtureFile: 'resources/filter/pollers/pollersWithSpaceOnName.json',
      path: '**/monitoring/servers?*'
    });
  });

  it('replaces whitespace with the \\s regex pattern in the search bar when selecting values from the criterias interface', () => {
    cy.findByLabelText(labelSearchOptions).click();
    cy.findByTestId(labelMonitoringServer).click();

    cy.waitForRequest('@pollersWithSpaceOnNameRequest');

    cy.findByRole('option', { name: pollerName }).click();
    cy.findByTestId(labelMonitoringServer).parent().contains(pollerName);
    cy.findByPlaceholderText(labelSearch)
      .invoke('val')
      .should('equal', `${searchedValue} `);

    cy.makeSnapshot();

    initialize();
  });

  it('replaces whitespace with the \\s regex pattern in the search bar when selecting values from the suggestions interface', () => {
    const key = 'monitoring_server:';

    cy.findByPlaceholderText(labelSearch).type(key);

    cy.waitForRequest('@pollersWithSpaceOnNameRequest');

    cy.findByRole('menuitem', { name: pollerName }).click();

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

    mount({ store: getStore() });
  });

  it('accepts the selected autocomplete suggestion when the beginning of a criteria is input and the "enter" key is pressed', () => {
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
