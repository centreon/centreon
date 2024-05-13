/* eslint-disable @typescript-eslint/no-unused-expressions */
import { renderHook } from '@testing-library/react-hooks/dom';
import { Provider, createStore, useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Method, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import useListing from '../Listing/useListing';
import useLoadResources from '../Listing/useLoadResources';
import { defaultStatuses, getListingEndpoint } from '../testUtils';
import {
  labelAcknowledged,
  labelAll,
  labelAllAlerts,
  labelHostCategory,
  labelHostGroup,
  labelHostSeverity,
  labelMonitoringServer,
  labelOk,
  labelSearch,
  labelSearchOptions,
  labelServiceGroup,
  labelState,
  labelStateFilter,
  labelStatus,
  labelUp
} from '../translatedLabels';
import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import { Visualization } from '../models';
import { resourcesEndpoint } from '../api/endpoint';

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
const resourcesByHostType = {
  alias: 'SensorProbe-Datacenter-04',
  chart_url: null,
  duration: '5m 23s',
  fqdn: 'SensorProbe-Datacenter-04',
  has_active_checks_enabled: true,
  has_passive_checks_enabled: false,
  host_id: 143,
  icon: {
    name: 'climate_64',
    url: '/centreon/img/media/Hardware/climate_64.png'
  },
  id: 143,
  information: 'OK - SensorProbe-Datacenter-04: rta 0.873ms, lost 0%',
  is_acknowledged: false,
  is_in_downtime: false,
  last_check: '2m 26s',
  last_status_change: '2023-10-11T17:05:57+02:00',
  links: {
    endpoints: {
      acknowledgement:
        '/centreon/api/latest/monitoring/hosts/143/acknowledgements?limit=1',
      details: '/centreon/api/latest/monitoring/resources/hosts/143',
      downtime:
        '/centreon/api/latest/monitoring/hosts/143/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1697037080%7D,%22end_time%22:%7B%22%24gt%22:1697037080%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1697037080%7D%7D%7D%7D%5D%7D',
      notification_policy: null,
      performance_graph: null,
      status_graph: null,
      timeline: '/centreon/api/latest/monitoring/hosts/143/timeline'
    },
    externals: {
      action_url: '',
      notes: {
        label: '',
        url: ''
      }
    },
    uris: {
      configuration: '/centreon/main.php?p=60101&o=c&host_id=143',
      logs: '/centreon/main.php?p=20301&h=143',
      reporting: '/centreon/main.php?p=307&host=143'
    }
  },
  monitoring_server_name: 'Central',
  name: 'SensorProbe-Datacenter-04',
  notification_enabled: false,
  parent: null,
  performance_data: null,
  service_id: null,
  severity: null,
  short_type: 'h',
  status: {
    code: 0,
    name: 'UP',
    severity_code: 5
  },
  tries: '1/5 (H)',
  type: 'host',
  uuid: 'h143'
};

const resourcesByServiceType = {
  alias: null,
  chart_url: null,
  duration: '10s',
  fqdn: null,
  has_active_checks_enabled: true,
  has_passive_checks_enabled: false,
  host_id: 113,
  icon: null,
  id: 863,
  information: 'Nombre de connexion : 150',
  is_acknowledged: false,
  is_in_downtime: false,
  last_check: '10s',
  last_status_change: '2023-10-11T17:14:55+02:00',
  links: {
    endpoints: {
      acknowledgement:
        '/centreon/api/latest/monitoring/hosts/113/services/863/acknowledgements?limit=1',
      details:
        '/centreon/api/latest/monitoring/resources/hosts/113/services/863',
      downtime:
        '/centreon/api/latest/monitoring/hosts/113/services/863/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1697037305%7D,%22end_time%22:%7B%22%24gt%22:1697037305%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1697037305%7D%7D%7D%7D%5D%7D',
      notification_policy: null,
      performance_graph:
        '/centreon/api/latest/monitoring/hosts/113/services/863/metrics/performance',
      status_graph:
        '/centreon/api/latest/monitoring/hosts/113/services/863/metrics/status',
      timeline:
        '/centreon/api/latest/monitoring/hosts/113/services/863/timeline'
    },
    externals: {
      action_url: '',
      notes: {
        label: '',
        url: ''
      }
    },
    uris: {
      configuration: '/centreon/main.php?p=60201&o=c&service_id=863',
      logs: '/centreon/main.php?p=20301&svc=113_863',
      reporting:
        '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=113&item=863'
    }
  },
  monitoring_server_name: 'Central',
  name: 'nbr-connect',
  notification_enabled: false,
  parent: {
    alias: 'fw-sydney',
    fqdn: 'fw-sydney',
    host_id: null,
    icon: null,
    id: 113,
    links: {
      endpoints: {
        acknowledgement: null,
        details: null,
        downtime: null,
        notification_policy: null,
        performance_graph: null,
        status_graph: null,
        timeline: null
      },
      externals: {
        action_url: null,
        notes: null
      },
      uris: {
        configuration: null,
        logs: null,
        reporting: null
      }
    },
    name: 'fw-sydney',
    service_id: null,
    short_type: 'h',
    status: {
      code: 0,
      name: 'UP',
      severity_code: 5
    },
    type: 'host',
    uuid: 'h113'
  },
  performance_data: null,
  service_id: 863,
  severity: null,
  short_type: 's',
  status: {
    code: 0,
    name: 'OK',
    severity_code: 5
  },
  tries: '2/3 (S)',
  type: 'service',
  uuid: 'h113-s863'
};

const pollersData = {
  address: null,
  description: null,
  id: 1,
  is_running: true,
  last_alive: 1697038658,
  name: 'Central',
  version: '23.10.0'
};
const hostCategoryData = {
  id: 3,
  name: 'Europe'
};

const hostSeverityData = {
  icon: {
    id: 82,
    name: 'flag_red',
    url: 'Criticity/flag_red.png'
  },
  id: 8,
  level: 1,
  name: 'Priority_1',
  type: 'host'
};

const linuxServersHostGroup = {
  id: 0,
  name: 'Linux-servers'
};

const FirewallHostGroup = {
  id: 1,
  name: 'Firewall'
};

const webAccessServiceGroup = {
  id: 0,
  name: 'Web-access'
};

enum Type {
  checkbox = 'checkbox',
  select = 'select',
  text = 'text'
}

const BasicCriteriasParams = [
  [
    'Basic criterias',
    [
      {
        criteria: 'Host',
        requestToWait: '@GetResourcesByHostType',
        searchValue: `parent_name:${resourcesByHostType.name} type:host `,
        type: Type.select,
        value: resourcesByHostType.name
      },
      {
        criteria: 'Service',
        requestToWait: '@GetResourcesByServiceType',
        searchValue: `name:${resourcesByServiceType.name} type:service `,
        type: Type.select,
        value: resourcesByServiceType.name
      },
      {
        criteria: labelState,
        endpointParam: { states: ['acknowledged'] },
        searchValue: 'state:acknowledged ',
        type: Type.checkbox,
        value: labelAcknowledged
      },
      {
        criteria: labelStatus,
        endpointParam: { statuses: ['OK'] },
        searchValue: 'status:ok ',
        type: Type.checkbox,
        value: labelOk
      },
      {
        criteria: labelStatus,
        endpointParam: { statuses: ['Up'] },
        searchValue: 'status:up ',
        type: Type.checkbox,
        value: labelUp
      },
      {
        criteria: labelHostGroup,
        requestToWait: '@hostgroupsRequest',
        searchValue: `host_group:${linuxServersHostGroup.name} `,
        type: Type.select,
        value: linuxServersHostGroup.name
      },
      {
        criteria: labelServiceGroup,
        requestToWait: '@serviceGroupsRequest',
        searchValue: `service_group:${webAccessServiceGroup.name} `,
        type: Type.select,
        value: webAccessServiceGroup.name
      },
      {
        criteria: labelMonitoringServer,
        requestToWait: '@pollersRequest',
        searchValue: `monitoring_server:${pollersData.name} `,
        type: Type.select,
        value: pollersData.name
      }
    ]
  ],
  [
    'Extended criterias',
    [
      {
        criteria: labelHostCategory,
        requestToWait: '@hostCategoryRequest',
        searchValue: `host_category:${hostCategoryData.name} `,
        type: Type.select,
        value: hostCategoryData.name
      },
      {
        criteria: labelHostSeverity,
        requestToWait: '@hostSeverityRequest',
        searchValue: `host_severity:${hostSeverityData.name} `,
        type: Type.select,
        value: hostSeverityData.name
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

describe('Custom filters', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    cy.interceptAPIRequest({
      alias: 'getResources',
      method: Method.GET,
      path: `${resourcesEndpoint}**`,
      response: {
        meta: {
          limit: 30,
          page: 1,
          total: 0
        },
        result: []
      }
    });

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
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    cy.interceptAPIRequest({
      alias: 'getResources',
      method: Method.GET,
      path: `${resourcesEndpoint}**`,
      response: {
        meta: {
          limit: 30,
          page: 1,
          total: 0
        },
        result: []
      }
    });

    const endpointByHostType = getListingEndpoint({
      limit: 10,
      resourceTypes: ['host'],
      sort: {},
      states: [],
      statusTypes: [],
      statuses: []
    });
    cy.interceptAPIRequest({
      alias: 'GetResourcesByHostType',
      method: Method.GET,
      path: endpointByHostType,
      query: {
        name: 'types',
        value: '["host"]'
      },
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [resourcesByHostType]
      }
    });

    const endpointByServiceType = getListingEndpoint({
      limit: 10,
      resourceTypes: ['service'],
      sort: {},
      states: [],
      statusTypes: [],
      statuses: []
    });

    cy.interceptAPIRequest({
      alias: 'GetResourcesByServiceType',
      method: Method.GET,
      path: endpointByServiceType,
      query: {
        name: 'types',
        value: '["service"]'
      },
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [resourcesByServiceType]
      }
    });

    cy.interceptAPIRequest({
      alias: 'pollersRequest',
      method: Method.GET,
      path: '**/monitoring/servers?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [pollersData]
      }
    });

    cy.interceptAPIRequest({
      alias: 'hostgroupsRequest',
      method: Method.GET,
      path: '**/hostgroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [linuxServersHostGroup]
      }
    });

    cy.interceptAPIRequest({
      alias: 'serviceGroupsRequest',
      method: Method.GET,
      path: '**/servicegroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [webAccessServiceGroup]
      }
    });
    cy.interceptAPIRequest({
      alias: 'hostCategoryRequest',
      method: Method.GET,
      path: '**/monitoring/hosts/categories?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [hostCategoryData]
      }
    });

    cy.interceptAPIRequest({
      alias: 'hostSeverityRequest',
      method: Method.GET,
      path: '**/monitoring/severities/host?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [hostSeverityData]
      }
    });

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

  it('does not display the host select and host statuses when the view by host is enabled', () => {
    store.set(selectedVisualizationAtom, Visualization.Host);

    cy.findByPlaceholderText(labelSearch).clear();
    cy.findByLabelText(labelSearchOptions).click();

    cy.findByLabelText('Host').should('not.exist');
    cy.findByText(labelUp, { exact: true }).should('not.exist');

    cy.makeSnapshot();

    cy.findByLabelText(labelSearchOptions).click();
  });

  BasicCriteriasParams.forEach(([label, data]) => {
    data.forEach((element) => {
      const { criteria, value, type, searchValue } = element;

      it(`executes a listing request with current search and selected ${criteria} criteria value when ${label} has changed`, () => {
        cy.findByPlaceholderText(labelSearch).clear();
        cy.get('[data-testid="Filter options"]').click();

        if (equals(label, 'Extended criterias')) {
          cy.findByText(labelShowMoreFilters).click();
        }

        if (equals(type, Type.select)) {
          cy.findByLabelText(criteria).click();
          cy.waitForRequest('@getResources');
          cy.findByText(value).click();
          cy.findByPlaceholderText(labelSearch).should(
            'have.value',
            searchValue
          );

          cy.makeSnapshot();

          cy.findByLabelText(labelSearchOptions).click();

          return;
        }
        if (equals(type, Type.checkbox)) {
          cy.findByText(value).click();
          cy.findByPlaceholderText(labelSearch).should(
            'have.value',
            searchValue
          );
          cy.makeSnapshot();

          cy.findByText(value).click();

          cy.findByLabelText(labelSearchOptions).click();
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
});

describe('Keyboard actions', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    cy.interceptAPIRequest({
      alias: 'getResources',
      method: Method.GET,
      path: `${resourcesEndpoint}**`,
      response: {
        meta: {
          limit: 30,
          page: 1,
          total: 0
        },
        result: []
      }
    });

    cy.interceptAPIRequest({
      alias: 'hostgroupsRequest',
      method: Method.GET,
      path: '**/hostgroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [linuxServersHostGroup, FirewallHostGroup]
      }
    });

    cy.interceptAPIRequest({
      alias: 'serviceGroupsRequest',
      method: Method.GET,
      path: '**/servicegroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [webAccessServiceGroup]
      }
    });

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
    cy.findByText(linuxServersHostGroup.name).should('exist');
    searchBar.type('{Enter}');
    cy.findByPlaceholderText(labelSearch).should(
      'have.value',
      `host_group:${linuxServersHostGroup.name}`
    );

    searchBar.type(',');
    cy.findByText('Firewall').should('exist');
    searchBar.type('{downArrow}');
    searchBar.type('{Enter}');
    cy.waitForRequest('@hostgroupsRequest');

    cy.makeSnapshot();
  });
});
