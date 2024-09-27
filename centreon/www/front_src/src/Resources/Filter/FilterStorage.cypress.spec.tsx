import { renderHook } from '@testing-library/react-hooks/dom';
import { useAtomValue } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import useListing from '../Listing/useListing';
import useLoadResources from '../Listing/useLoadResources';
import { getListingEndpoint } from '../testUtils';
import {
  labelAcknowledged,
  labelHost,
  labelHostGroup,
  labelOk,
  labelSearch,
  labelSearchOptions,
  labelServiceGroup,
  labelUnhandledAlerts
} from '../translatedLabels';

import { defaultSortField, defaultSortOrder } from './Criterias/default';
import { filterKey } from './filterAtoms';
import useFilter from './useFilter';

import Filter from '.';

const resourcesByHostType = {
  acknowledged: false,
  active_checks: true,
  alias: 'SensorProbe-Datacenter-04',
  chart_url: null,
  duration: '5m 23s',
  fqdn: 'SensorProbe-Datacenter-04',
  host_id: 143,
  icon: {
    name: 'climate_64',
    url: '/centreon/img/media/Hardware/climate_64.png'
  },
  id: 143,
  in_downtime: false,
  information: 'OK - SensorProbe-Datacenter-04: rta 0.873ms, lost 0%',
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
  passive_checks: false,
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

const emptyListData = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

const linuxServersHostGroup = {
  formattedName: 'Linux-servers',
  id: 0,
  name: 'Linux-servers'
};

const webAccessServiceGroup = {
  formattedName: 'Web-access',
  id: 0,
  name: 'Web-access'
};

const parameters = [
  { name: 'search', object_type: null, type: 'text', value: '' },
  { name: 'sort', value: [defaultSortField, defaultSortOrder] }
];

const filter = {
  criterias: [
    {
      name: 'resource_types',
      object_type: null,
      type: 'multi_select',
      value: [{ formattedName: labelHost, id: 'host', name: labelHost }]
    },
    {
      name: 'states',
      object_type: null,
      type: 'multi_select',
      value: [{ id: 'acknowledged', name: labelAcknowledged }]
    },
    {
      name: 'statuses',
      object_type: null,
      type: 'multi_select',
      value: [{ id: 'OK', name: labelOk }]
    },
    {
      name: 'host_groups',
      object_type: 'host_groups',
      type: 'multi_select',
      value: [linuxServersHostGroup]
    },
    {
      name: 'service_groups',
      object_type: 'service_groups',
      type: 'multi_select',
      value: [webAccessServiceGroup]
    },
    { name: 'search', value: 'Search me' },
    { name: 'sort', value: [defaultSortField, defaultSortOrder] }
  ],
  id: 0,
  name: 'My filter'
};

const expectedFilter = {
  criterias: [
    {
      name: 'resource_types',
      object_type: null,
      type: 'multi_select',
      value: [
        {
          id: 'host',
          name: 'Host'
        }
      ]
    },
    {
      name: 'states',
      object_type: null,
      type: 'multi_select',
      value: []
    },
    {
      name: 'statuses',
      object_type: null,
      type: 'multi_select',
      value: [
        {
          id: 'UP',
          name: 'Up'
        },
        {
          id: 'DOWN',
          name: 'Down'
        }
      ]
    },
    {
      name: 'status_types',
      object_type: null,
      type: 'multi_select',
      value: []
    },
    {
      name: 'host_groups',
      object_type: 'host_groups',
      type: 'multi_select',
      value: [
        {
          formattedName: 'HG',
          id: 0,
          name: 'HG'
        }
      ]
    },
    {
      name: 'service_groups',
      object_type: 'service_groups',
      type: 'multi_select',
      value: []
    },
    {
      name: 'monitoring_servers',
      object_type: 'monitoring_servers',
      type: 'multi_select',
      value: [
        {
          formattedName: 'Poller\\stest',
          id: 0,
          name: 'Poller test'
        }
      ]
    },
    {
      name: 'host_categories',
      object_type: 'host_categories',
      type: 'multi_select',
      value: []
    },
    {
      name: 'service_categories',
      object_type: 'service_categories',
      type: 'multi_select',
      value: []
    },
    {
      name: 'host_severities',
      object_type: 'host_severities',
      type: 'multi_select',
      value: []
    },
    {
      name: 'host_severity_levels',
      object_type: 'host_severity_levels',
      type: 'multi_select',
      value: []
    },
    {
      name: 'service_severities',
      object_type: 'service_severities',
      type: 'multi_select',
      value: []
    },
    {
      name: 'service_severity_levels',
      object_type: 'service_severity_levels',
      type: 'multi_select',
      value: []
    },
    {
      name: 'parent_names',
      object_type: 'parent_names',
      type: 'multi_select',
      value: [
        {
          formattedName: 'Server',
          id: 0,
          name: 'Server'
        }
      ]
    },
    {
      name: 'names',
      object_type: 'names',
      type: 'multi_select',
      value: [
        {
          formattedName: 'Service',
          id: 0,
          name: 'Service'
        }
      ]
    },
    ...parameters
  ],
  id: 0,
  name: 'My filter'
};

const initializeResourcesByHost = (): void => {
  const endpointByHostType = getListingEndpoint({
    limit: 10,
    resourceTypes: ['host'],
    sort: {},
    states: [],
    statusTypes: [],
    statuses: []
  });
  cy.interceptAPIRequest({
    alias: 'getResourcesByHostType',
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
};

const FilterWithLoading = (): JSX.Element => {
  useLoadResources();

  return <Filter />;
};

const FilterTest = (): JSX.Element | null => {
  useFilter();
  useListing();

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

describe('Filter storage', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    cy.interceptAPIRequest({
      alias: 'hostGroupsRequest',
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
      alias: 'pollers',
      method: Method.GET,
      path: '**/monitoring/servers?*',
      response: emptyListData
    });

    cy.interceptAPIRequest({
      alias: 'resources',
      method: Method.GET,
      path: '**/resources*',
      response: emptyListData
    });

    cy.mount({
      Component: <FilterWithProvider />
    });
    localStorage.setItem(filterKey, JSON.stringify(filter));

    cy.viewport(1200, 1000);
  });

  it('stores filter values in localStorage when updated', () => {
    cy.waitForRequest('@filterRequest');

    cy.findByPlaceholderText(labelSearch).clear();
    cy.findByPlaceholderText(labelSearch).type(
      'type:host parent_name:Server name:Service host_group:HG monitoring_server:Poller\\stest status:up,down'
    );

    cy.getAllLocalStorage().should('deep.equal', {
      'http://localhost:9092': {
        MSW_COOKIE_STORE: '[]',
        'centreon-resource-status-23.10-filter': JSON.stringify(expectedFilter)
      }
    });

    cy.makeSnapshot();
    cy.findByPlaceholderText(labelSearch).clear();
  });

  it('populates filter with values from localStorage if available', () => {
    cy.waitForRequest('@filterRequest');
    initializeResourcesByHost();

    cy.findByLabelText(labelUnhandledAlerts).should('not.exist');

    const searchField = cy.findByPlaceholderText(labelSearch);

    searchField.should(
      'have.value',
      'type:host state:acknowledged status:ok host_group:Linux-servers service_group:Web-access Search me'
    );

    cy.findByLabelText(labelSearchOptions).click();

    cy.findByTestId(labelHost)
      .parent()
      .findByTestId('ArrowDropDownIcon')
      .parent()
      .click();

    const hostName = cy.findByText(resourcesByHostType.name);
    hostName.should('exist');

    cy.findByText(labelAcknowledged).should('exist');

    cy.findByText(labelOk).should('exist');

    cy.findByLabelText(labelHostGroup).click();
    cy.waitForRequest('@hostGroupsRequest');
    cy.contains(linuxServersHostGroup.name);

    cy.findByLabelText(labelServiceGroup).click();
    cy.waitForRequest('@serviceGroupsRequest');
    cy.contains(webAccessServiceGroup.name);

    cy.makeSnapshot();

    cy.findByLabelText(labelSearchOptions).click();
  });
});
