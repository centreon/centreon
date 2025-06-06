import {
  T,
  always,
  cond,
  equals,
  flatten,
  groupBy,
  identity,
  includes,
  intersection,
  isEmpty,
  last,
  map,
  pipe,
  pluck,
  reject,
  toPairs,
  toUpper
} from 'ramda';

import { ResourceType, SeverityCode, centreonBaseURL } from '@centreon/ui';

import { WidgetResourceType } from '../AddEditWidget/models';
import { Resource, SeverityStatus, Status } from './models';

export const areResourcesFullfilled = (
  resourcesDataset: Array<Resource>
): boolean =>
  !isEmpty(resourcesDataset) &&
  resourcesDataset?.every(
    ({ resourceType, resources }) =>
      !isEmpty(resourceType) && !isEmpty(resources.filter((v) => v))
  );

const serviceCriteria = {
  name: 'resource_types',
  value: [{ id: 'service', name: 'Service' }]
};

interface GetResourcesUrlProps {
  allResources: Array<Resource>;
  isForOneResource: boolean;
  resource?;
  states: Array<string>;
  statuses: Array<string>;
  type: string;
}

const getResourceStatusDetailsEndpoint = ({
  resourceType,
  id,
  parentId
}): string =>
  cond([
    [
      equals('host'),
      always(`${centreonBaseURL}/api/latest/monitoring/resources/hosts/${id}`)
    ],
    [
      equals('service'),
      always(
        `${centreonBaseURL}/api/latest/monitoring/resources/hosts/${parentId}/services/${id}`
      )
    ],
    [
      equals('metaservice'),
      always(
        `${centreonBaseURL}/api/latest/monitoring/resources/metaservices/${id}`
      )
    ]
  ])(resourceType);

export const getDetailsPanelQueriers = ({ resource }): object => {
  const { id, parentId, uuid, type: resourceType } = resource;

  const resourcesDetailsEndpoint = getResourceStatusDetailsEndpoint({
    id,
    parentId,
    resourceType
  });

  const queryParameters = {
    id,
    resourcesDetailsEndpoint,
    selectedTimePeriodId: 'last_24_h',
    tab: 'details',
    tabParameters: {},
    uuid
  };

  return queryParameters;
};

export const getResourcesUrl = ({
  type,
  statuses,
  states,
  allResources,
  isForOneResource,
  resource
}: GetResourcesUrlProps): string => {
  const resourcesCriterias = equals(type, 'all')
    ? {
        name: 'resource_types',
        value: [
          { id: 'service', name: 'Service' },
          { id: 'host', name: 'Host' },
          { id: 'metaservice', name: 'Meta service' }
        ]
      }
    : {
        name: 'resource_types',
        value: [
          {
            id: type,
            name: `${type?.charAt(0).toUpperCase()}${type?.slice(1)}`
          }
        ]
      };

  const formattedStatuses = pipe(
    map((status) => formatStatusFilter(status)),
    flatten,
    map((status: string) => {
      return {
        id: status.toLocaleUpperCase(),
        name: `${status?.charAt(0).toUpperCase()}${status?.slice(1)}`
      };
    })
  )(statuses);

  const formattedStates = states.map((state) => {
    return {
      id: state,
      name: `${state?.charAt(0).toUpperCase()}${state?.slice(1)}`
    };
  });

  const groupedResources = groupBy(
    ({ resourceType }) =>
      equals(resourceType, 'hostgroup')
        ? WidgetResourceType.hostGroup
        : resourceType,
    allResources
  );

  const resourcesFilters = Object.entries(groupedResources).map(
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
            return subResources.map(({ name: resourceName }) => ({
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
      resourcesCriterias,
      { name: 'statuses', value: formattedStatuses },
      { name: 'states', value: formattedStates },
      ...resourcesFilters,
      { name: 'search', value: '' }
    ]
  };

  const encodedFilterParams = encodeURIComponent(
    JSON.stringify(filterQueryParameter)
  );

  if (!isForOneResource) {
    return `/monitoring/resources?filter=${encodedFilterParams}&fromTopCounter=true`;
  }

  const detailsPanelQueriers = getDetailsPanelQueriers({ resource, type });

  const encodedDetailsParams = encodeURIComponent(
    JSON.stringify(detailsPanelQueriers)
  );

  return `/monitoring/resources?details=${encodedDetailsParams}&filter=${encodedFilterParams}&fromTopCounter=true`;
};

const getDetailsPanelQueriersForMetricsWidgets = (data): object => {
  const uuid = data?.uuid;
  const hostId = uuid?.split('-')[0]?.slice(1);
  const serviceId = uuid?.split('-')[1]?.slice(1);

  const resourcesDetailsEndpoint = `${centreonBaseURL}/api/latest/monitoring/resources/hosts/${hostId}/services/${serviceId}`;

  const queryParameters = {
    id: serviceId,
    resourcesDetailsEndpoint,
    selectedTimePeriodId: 'last_24_h',
    tab: 'details',
    tabParameters: {},
    uuid
  };

  return queryParameters;
};

export const getResourcesUrlForMetricsWidgets = (data): string => {
  const filters = [
    {
      name: 'name',
      value: [
        {
          id: `\\b${data?.name}\\b`,
          name: data?.name
        }
      ]
    },
    {
      name: 'h.name',
      value: [
        {
          id: `\\b${data?.parentName}\\b`,
          name: data?.parentName
        }
      ]
    }
  ];

  const filterQueryParameter = {
    criterias: [serviceCriteria, ...filters, { name: 'search', value: '' }]
  };

  const encodedFilterParams = encodeURIComponent(
    JSON.stringify(filterQueryParameter)
  );

  const detailsPanelQueriers = getDetailsPanelQueriersForMetricsWidgets(data);
  const encodedDetailsParams = encodeURIComponent(
    JSON.stringify(detailsPanelQueriers)
  );

  return `/monitoring/resources?details=${encodedDetailsParams}&filter=${encodedFilterParams}&fromTopCounter=true`;
};

export const formatStatusFilter = cond([
  [equals(SeverityStatus.Success), always(['ok', 'up'])],
  [equals(SeverityStatus.Warning), always(['warning'])],
  [equals(SeverityStatus.Problem), always(['down', 'critical'])],
  [equals(SeverityStatus.Undefined), always(['unreachable', 'unknown'])],
  [equals(SeverityStatus.Pending), always(['pending'])],
  [T, identity]
]) as (b: SeverityStatus) => Array<string>;

export const formatBAStatusFilter = cond([
  [equals(SeverityStatus.Success), always('ok')],
  [equals(SeverityStatus.Warning), always('warning')],
  [equals(SeverityStatus.Problem), always('critical')],
  [equals(SeverityStatus.Undefined), always('unknown')],
  [equals(SeverityStatus.Pending), always('pending')],
  [T, identity]
]) as (b: SeverityStatus) => string;

export const formatStatus = pipe(
  map(formatStatusFilter),
  flatten,
  map((status) => status.toLocaleUpperCase())
);

export const formatBAStatus = pipe(
  map(formatBAStatusFilter),
  map((status) => status.toLocaleUpperCase())
);

export const goToUrl = (url) => (): void => {
  window?.open(`${centreonBaseURL}${url}`, '_blank,noopener,noreferrer');
};

const isTypeHost = equals('host');

interface GetStatusNameByStatusSeverityandResourceTypeProps {
  resourceType: string;
  status: SeverityStatus;
}

export const getStatusNameByStatusSeverityandResourceType = ({
  resourceType,
  status
}: GetStatusNameByStatusSeverityandResourceTypeProps): string =>
  cond([
    [
      equals(SeverityStatus.Success),
      always(isTypeHost(resourceType) ? 'up' : 'ok')
    ],
    [equals(SeverityStatus.Warning), always('warning')],
    [
      equals(SeverityStatus.Problem),
      always(isTypeHost(resourceType) ? 'down' : 'critical')
    ],
    [
      equals(SeverityStatus.Undefined),
      always(isTypeHost(resourceType) ? 'unreachable' : 'unknown')
    ],
    [equals(SeverityStatus.Pending), always('pending')]
  ])(status);

export const severityCodeBySeverityStatus = {
  [SeverityStatus.Problem]: SeverityCode.High,
  [SeverityStatus.Warning]: SeverityCode.Medium,
  [SeverityStatus.Success]: SeverityCode.OK,
  [SeverityStatus.Undefined]: SeverityCode.None,
  [SeverityStatus.Pending]: SeverityCode.Pending
};

export const severityStatusBySeverityCode = {
  [SeverityCode.High]: SeverityStatus.Problem,
  [SeverityCode.Medium]: SeverityStatus.Warning,
  [SeverityCode.OK]: SeverityStatus.Success,
  [SeverityCode.None]: SeverityStatus.Undefined,
  [SeverityCode.Pending]: SeverityStatus.Pending
};

interface GetPublicWidgetEndpointProps {
  dashboardId: number | string;
  extraQueryParameters?: string;
  playlistHash?: string;
  widgetId: string;
}

export const getPublicWidgetEndpoint = ({
  playlistHash,
  dashboardId,
  widgetId,
  extraQueryParameters = ''
}: GetPublicWidgetEndpointProps): string =>
  `/it-edition-extensions/monitoring/dashboards/playlists/${playlistHash}/dashboards/${dashboardId}/widgets/${widgetId}${extraQueryParameters}`;

export const getWidgetEndpoint = ({
  playlistHash,
  dashboardId,
  widgetId,
  isOnPublicPage,
  defaultEndpoint,
  extraQueryParameters
}: Omit<GetPublicWidgetEndpointProps, 'extraQueryParameters'> & {
  defaultEndpoint: string;
  extraQueryParameters?: Record<string, string | number | object>;
  isOnPublicPage: boolean;
}): string => {
  if (isOnPublicPage && playlistHash) {
    const extraqueryParametersStringified = extraQueryParameters
      ? toPairs(extraQueryParameters).reduce(
          (acc, [key, value]) =>
            `${acc}&${key as string}=${encodeURIComponent(JSON.stringify(value))}`,
          '?'
        )
      : '';

    return getPublicWidgetEndpoint({
      dashboardId,
      extraQueryParameters: extraqueryParametersStringified,
      playlistHash,
      widgetId
    });
  }

  return defaultEndpoint;
};

export const getBAStatusBySeverityCode = {
  [SeverityCode.High]: 'critical',
  [SeverityCode.Medium]: 'warning',
  [SeverityCode.OK]: 'ok',
  [SeverityCode.None]: 'unknown',
  [SeverityCode.Pending]: 'pending'
};

export const getBAsURL = (severityCode: number): string => {
  const status = getBAStatusBySeverityCode[severityCode];

  return `/main.php?p=20701&status=${status}`;
};

export const indicatorsURL = '/main.php?p=62606';

const resourceTypesCustomParameters = [
  'host-group',
  'host-category',
  'service-group',
  'service-category'
];
const resourcesSearchMapping = {
  host: 'parent_name',
  'meta-service': 'name',
  service: 'name'
};
const resourceTypesSearchParameters = ['host', 'service', 'meta-service'];
const categories = ['host-category', 'service-category'];

export const getResourcesSearchQueryParameters = (
  resources: Array<Resource> = []
): {
  resourcesCustomParameters: Array<{
    name: string;
    value: Array<string>;
  }>;
  resourcesSearchConditions: Array<{
    field;
    values: {
      [operator: string]: string;
    };
  }>;
} => {
  const resourcesToApplyToCustomParameters = resources.filter(
    ({ resourceType }) => includes(resourceType, resourceTypesCustomParameters)
  );
  const resourcesToApplyToSearchParameters = resources.filter(
    ({ resourceType }) => includes(resourceType, resourceTypesSearchParameters)
  );

  const resourcesSearchConditions = resourcesToApplyToSearchParameters.map(
    ({ resourceType, resources: resourcesToApply }) => {
      return resourcesToApply.map((resource) => ({
        field: resourcesSearchMapping[resourceType],
        values: {
          $rg: `^${resource.name}$`
        }
      }));
    }
  );

  const resourcesCustomParameters = resourcesToApplyToCustomParameters.map(
    ({ resourceType, resources: resourcesToApply }) => ({
      name: includes(resourceType, categories)
        ? `${resourceType.replace('-', '_')}_names`
        : `${resourceType.replace('-', '')}_names`,
      value: pluck('name', resourcesToApply)
    })
  );

  return {
    resourcesCustomParameters,
    resourcesSearchConditions: flatten(resourcesSearchConditions)
  };
};

export const getIsMetaServiceSelected = (
  resources: Array<Resource> = []
): boolean =>
  equals(resources.length, 1) &&
  equals(resources[0].resourceType, ResourceType.metaService);

export const getStatusNamesPerResourceType = (
  resourceType: string
): Array<Status> => {
  if (equals(resourceType, 'host')) {
    return ['down', 'unreachable', 'up', 'pending'];
  }

  return ['critical', 'warning', 'unknown', 'ok', 'pending'];
};

export const getStatusesByResourcesAndResourceType = ({
  statuses,
  resources,
  resourceType
}) => {
  const lastSelectedResourceType = pipe(
    pluck('resourceType'),
    reject((type) => equals(type, '')),
    last
  )(resources);

  const isBVResourceType = equals(lastSelectedResourceType, 'business-view');
  const isBAResourceType = equals(
    lastSelectedResourceType,
    'business-activity'
  );

  const formattedStatuses = formatStatus(statuses);

  const resourceTypeToUse =
    isBVResourceType || isBAResourceType
      ? lastSelectedResourceType
      : resourceType;

  const statusesByResourceType = map(
    toUpper,
    getStatusNamesPerResourceType(resourceTypeToUse)
  );

  const statusesToUse = intersection(statusesByResourceType, formattedStatuses);

  return statusesToUse;
};
