import {
  T,
  always,
  cond,
  equals,
  flatten,
  groupBy,
  identity,
  includes,
  uniq
} from 'ramda';

import { centreonBaseURL } from '@centreon/ui';

export const isGenericText = equals<string | undefined>('/widgets/generictext');
export const isRichTextEditorEmpty = (editorState: string): boolean => {
  const state = JSON.parse(editorState);

  return equals(state.root.children?.[0]?.children?.length, 0);
};

export const getDetailsPanelQueriers = (data): object => {
  const uuid = data?.services?.[0].uuid;

  const hostId = uuid?.split('-')[0]?.slice(1);
  const serviceId = uuid?.split('-')[1]?.slice(1);

  const resourcesDetailsEndpoint = `${centreonBaseURL}/api/latest/monitoring/resources/hosts/${hostId}/services/${serviceId}`;

  const queryParameters = {
    id: parseInt(serviceId, 10),
    resourcesDetailsEndpoint,
    selectedTimePeriodId: 'last_24_h',
    tab: 'graph',
    tabParameters: {},
    uuid
  };

  return queryParameters;
};

export const getResourcesUrlForMetricsWidgets = ({
  data,
  widgetName
}): string => {
  const values = data?.services?.map(({ name }) => {
    return {
      id: `\\b${name}\\b`,
      name
    };
  });

  const hostvalues = data?.services?.map(({ parentName }) => {
    return {
      id: `\\b${parentName}\\b`,
      name: parentName
    };
  });

  const filters = [
    { name: 'name', value: uniq(values || []) },
    { name: 'h.name', value: uniq(hostvalues || []) }
  ];

  const serviceCriteria = {
    name: 'resource_types',
    value: [{ id: 'service', name: 'Service' }]
  };

  const filterQueryParameter = {
    criterias: [serviceCriteria, ...filters, { name: 'search', value: '' }]
  };
  const encodedFilterParams = encodeURIComponent(
    JSON.stringify(filterQueryParameter)
  );

  if (!equals(widgetName, 'centreon-widget-singlemetric')) {
    return `/monitoring/resources?&filter=${encodedFilterParams}&fromTopCounter=true`;
  }

  const detailsPanelQueriers = getDetailsPanelQueriers(data);
  const encodedDetailsParams = encodeURIComponent(
    JSON.stringify(detailsPanelQueriers)
  );

  return `/monitoring/resources?details=${encodedDetailsParams}&filter=${encodedFilterParams}&fromTopCounter=true`;
};

export const getUrlForResourcesOnlyWidgets = ({
  type,
  statuses,
  states,
  resources
}): string => {
  const hostCriterias = {
    name: 'resource_types',
    value: [{ id: 'host', name: 'Host' }]
  };

  const serviceCriteria = {
    name: 'resource_types',
    value: [{ id: 'service', name: 'Service' }]
  };

  const formattedStatuses = statuses?.map((status) => {
    return {
      id: status.toLocaleUpperCase(),
      name: `${status.charAt(0).toUpperCase()}${status.slice(1)}`
    };
  });

  const formattedStates = states?.map((state) => {
    return {
      id: state,
      name: `${state.charAt(0).toUpperCase()}${state.slice(1)}`
    };
  });

  const groupedResources = groupBy(
    ({ resourceType }) => resourceType,
    resources
  );

  const resourcesFilters = Object.entries(groupedResources)?.map(
    ([resourceType, res]) => {
      const name = cond<Array<string>, string>([
        [equals('host'), always('parent_name')],
        [equals('service'), always('name')],
        [T, identity]
      ])(resourceType);

      return {
        name: name.replace('-', '_'),
        value: flatten(
          (res || []).map(({ resources: subResources }) => {
            return subResources?.map(({ name: resourceName }) => ({
              id: includes(name, ['name', 'parent_name'])
                ? `\\b${resourceName}\\b`
                : resourceName,
              name: resourceName
            }));
          })
        )
      };
    }
  );

  const filterQueryParameter = {
    criterias: [
      equals(type, 'host') ? hostCriterias : serviceCriteria,
      { name: 'statuses', value: formattedStatuses },
      { name: 'states', value: formattedStates },
      ...resourcesFilters,
      { name: 'search', value: '' }
    ]
  };

  return `/monitoring/resources?filter=${JSON.stringify(
    filterQueryParameter
  )}&fromTopCounter=true`;
};

export const resourceBasedWidgets = [
  'centreon-widget-singlemetric',
  'centreon-widget-statusgrid',
  'centreon-widget-topbottom',
  'centreon-widget-graph'
];
