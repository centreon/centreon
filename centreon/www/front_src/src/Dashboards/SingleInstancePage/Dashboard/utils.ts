import {
  T,
  always,
  cond,
  equals,
  flatten,
  groupBy,
  identity,
  includes,
  map,
  pipe,
  uniq
} from 'ramda';

import { centreonBaseURL } from '@centreon/ui';

import { WidgetResourceType } from './AddEditWidget/models';

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

const resourcesCriteriasMapping = {
  [WidgetResourceType.host]: 'h.name',
  [WidgetResourceType.hostCategory]: 'host_categories',
  [WidgetResourceType.hostGroup]: 'host_groups',
  [WidgetResourceType.service]: 'name',
  [WidgetResourceType.serviceCategory]: 'service_categories',
  [WidgetResourceType.serviceGroup]: 'service_groups'
};

export const getResourcesUrlForMetricsWidgets = ({
  data,
  widgetName
}): string => {
  const filters = data?.resources.map(({ resourceType, resources }) => {
    if (
      [WidgetResourceType.host, WidgetResourceType.service].includes(
        resourceType
      )
    ) {
      return {
        name: resourcesCriteriasMapping[resourceType],
        value: uniq(
          resources.map(({ name }) => ({
            id: `\\b${name}\\b`,
            name
          })) || []
        )
      };
    }

    return {
      name: resourcesCriteriasMapping[resourceType],
      value: uniq(
        resources.map(({ name, id }) => ({
          id,
          name
        })) || []
      )
    };
  });

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
  const formattedTypes = cond([
    [equals('host'), always(['host'])],
    [equals('service'), always(['service'])],
    [equals('all'), always(['host', 'service'])],
    [T, identity]
  ])(type);

  const resourcesCriterias = {
    name: 'resource_types',
    value: formattedTypes?.map((item) => {
      return {
        id: item,
        name: `${item.charAt(0).toUpperCase()}${item.slice(1)}`
      };
    })
  };

  const formatStatusFilter = cond([
    [equals('success'), always(['ok', 'up'])],
    [equals('problem'), always(['down', 'critical'])],
    [equals('undefined'), always(['unreachable', 'unknown'])],
    [equals('1'), always(['down', 'critical'])],
    [equals('2'), always(['warning'])],
    [equals('4'), always(['pending'])],
    [equals('5'), always(['ok', 'up'])],
    [equals('6'), always(['unreachable', 'unknown'])],
    [T, identity]
  ]);

  const formattedStatuses =
    statuses &&
    pipe(
      map((status) => formatStatusFilter(status)),
      flatten,
      map((status: string) => {
        return {
          id: status.toLocaleUpperCase(),
          name: `${status.charAt(0).toUpperCase()}${status.slice(1)}`
        };
      })
    )(statuses);

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

  const statusesQueryParameter = statuses
    ? [{ name: 'statuses', value: formattedStatuses }]
    : [];

  const statesQueryParameter = states
    ? [{ name: 'states', value: formattedStates }]
    : [];

  const filterQueryParameter = {
    criterias: [
      resourcesCriterias,
      ...statusesQueryParameter,
      ...statesQueryParameter,
      ...resourcesFilters,
      { name: 'search', value: '' }
    ]
  };

  return `/monitoring/resources?filter=${JSON.stringify(
    filterQueryParameter
  )}&fromTopCounter=true`;
};
