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
    id: Number.parseInt(serviceId, 10),
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
  [WidgetResourceType.serviceGroup]: 'service_groups',
  [WidgetResourceType.metaService]: 'name'
};

export const getResourcesUrlForMetricsWidgets = ({
  data,
  widgetName
}): string => {
  const filters = data?.resources.map(({ resourceType, resources }) => {
    if (
      [
        WidgetResourceType.host,
        WidgetResourceType.service,
        WidgetResourceType.metaService
      ].includes(resourceType)
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
    return `/monitoring/resources?filter=${encodedFilterParams}&fromTopCounter=true`;
  }

  const detailsPanelQueriers = getDetailsPanelQueriers(data);
  const encodedDetailsParams = encodeURIComponent(
    JSON.stringify(detailsPanelQueriers)
  );

  return `/monitoring/resources?details=${encodedDetailsParams}&filter=${encodedFilterParams}&fromTopCounter=true`;
};

const formatStatusFilter = cond([
  [equals('success'), always(['ok', 'up'])],
  [equals('warning'), always(['warning'])],
  [equals('problem'), always(['down', 'critical'])],
  [equals('undefined'), always(['unreachable', 'unknown'])],
  [equals('pending'), always(['pending'])],
  [equals('5'), always(['ok', 'up'])],
  [equals('2'), always(['warning'])],
  [equals('1'), always(['down', 'critical'])],
  [equals('6'), always(['unreachable', 'unknown'])],
  [equals('4'), always(['pending'])],

  [T, identity]
]) as (b: string) => Array<string>;

export const getUrlForResourcesOnlyWidgets = ({
  type,
  statuses,
  states,
  resources
}): string => {
  const typeCriterias = ['host', 'service']
    .filter((resourceType) => {
      return type?.includes(resourceType);
    })
    .map((resourceType) => ({
      id: resourceType,
      name: `${resourceType.charAt(0).toUpperCase()}${resourceType.substring(1)}`
    }));

  const mappedStatuses = flatten(statuses?.map(formatStatusFilter) || []);

  const formattedStatuses = mappedStatuses?.map((status) => {
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
        [equals('meta-service'), always('name')],
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
      {
        name: 'resource_types',
        value: typeCriterias
      },
      { name: 'statuses', value: formattedStatuses },
      { name: 'states', value: formattedStates },
      ...resourcesFilters,
      { name: 'search', value: '' }
    ]
  };

  return `/monitoring/resources?filter=${encodeURIComponent(
    JSON.stringify(filterQueryParameter)
  )}&fromTopCounter=true`;
};
