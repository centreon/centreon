import { equals, map, pick, propOr, uniq } from 'ramda';

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
    return `/monitoring/resources?filter=${encodedFilterParams}&fromTopCounter=true`;
  }

  const detailsPanelQueriers = getDetailsPanelQueriers(data);
  const encodedDetailsParams = encodeURIComponent(
    JSON.stringify(detailsPanelQueriers)
  );

  return `/monitoring/resources?details=${encodedDetailsParams}&filter=${encodedFilterParams}&fromTopCounter=true`;
};

const formatResource = (item): object => ({
  ...item,
  resources: map(pick(['id', 'name']), propOr([], 'resources', item))
});

export const formatLayoutResources = (data?: object): object | null => {
  if (!data) {
    return null;
  }

  return {
    ...data,
    resources: map(formatResource, propOr([], 'resources', data))
  };
};
