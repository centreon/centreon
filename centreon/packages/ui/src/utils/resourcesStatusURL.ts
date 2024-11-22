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
  pipe
} from 'ramda';

import { SelectEntry } from '..';

import { centreonBaseURL } from './centreonBaseURL';

export interface Resource {
  resourceType: string;
  resources: Array<SelectEntry>;
}

interface GetResourcesUrlProps {
  allResources: Array<Resource>;
  isForOneResource: boolean;
  resource?;
  states: Array<string>;
  statuses: Array<string>;
  type: string;
}

export const getDetailsPanelQueriers = ({ resource, type }): object => {
  const { id, parentId, uuid } = resource;

  const resourcesDetailsEndpoint = cond([
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
    ],
    [
      equals('anomaly-detection'),
      always(
        `${centreonBaseURL}/api/latest/monitoring/resources/anomaly-detection/${id}`
      )
    ]
  ])(type);

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
          { id: 'host', name: 'Host' }
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
    ({ resourceType }) => resourceType,
    allResources
  );

  const resourcesFilters = Object.entries(groupedResources).map(
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
