import {
  T,
  always,
  cond,
  equals,
  flatten,
  groupBy,
  gt,
  gte,
  head,
  identity,
  includes,
  isEmpty,
  isNil,
  length,
  lt,
  lte
} from 'ramda';

import { Theme } from '@mui/material';

import { SeverityCode, centreonBaseURL, getStatusColors } from '@centreon/ui';

import { Resource } from '../../models';

import { ResourceData } from './models';

interface GetColorProps {
  is_acknowledged?: boolean;
  is_in_downtime?: boolean;
  severityCode?: number;
  theme: Theme;
}

export const getColor = ({
  is_acknowledged,
  is_in_downtime,
  severityCode,
  theme
}: GetColorProps): string => {
  if (is_acknowledged) {
    return theme.palette.action.acknowledgedBackground;
  }
  if (is_in_downtime) {
    return theme.palette.action.inDowntimeBackground;
  }

  return getStatusColors({
    severityCode: severityCode as SeverityCode,
    theme
  }).backgroundColor;
};

interface GetStatusFromThresholdsProps {
  criticalThresholds: Array<number | null>;
  data: number | null;
  warningThresholds: Array<number | null>;
}

export const getStatusFromThresholds = ({
  data,
  criticalThresholds,
  warningThresholds
}: GetStatusFromThresholdsProps): SeverityCode => {
  if (isNil(data)) {
    return SeverityCode.None;
  }
  const criticalValues = criticalThresholds
    .filter((v) => v)
    .sort() as Array<number>;
  const warningValues = warningThresholds
    .filter((v) => v)
    .sort() as Array<number>;

  if (isEmpty(warningValues) && isEmpty(criticalValues)) {
    return SeverityCode.OK;
  }

  if (
    equals(length(criticalValues), 2) &&
    lte(criticalValues[0], data) &&
    gte(criticalValues[1], data)
  ) {
    return SeverityCode.High;
  }

  if (
    equals(length(warningValues), 2) &&
    lte(warningValues[0], data) &&
    gte(warningValues[1], data)
  ) {
    return SeverityCode.Medium;
  }

  if (equals(length(warningValues), 2)) {
    return SeverityCode.OK;
  }

  const criticalValue = head(criticalValues) as number;
  const warningValue = head(warningValues) as number;

  if (gt(warningValue, criticalValue)) {
    return cond([
      [lt(warningValue), always(SeverityCode.OK)],
      [lt(criticalValue), always(SeverityCode.Medium)],
      [T, always(SeverityCode.High)]
    ])(data);
  }

  return cond([
    [gt(warningValue), always(SeverityCode.OK)],
    [gt(criticalValue), always(SeverityCode.Medium)],
    [T, always(SeverityCode.High)]
  ])(data);
};

const hostCriterias = {
  name: 'resource_types',
  value: [{ id: 'host', name: 'Host' }]
};
const serviceCriteria = {
  name: 'resource_types',
  value: [{ id: 'service', name: 'Service' }]
};

interface GetResourcesUrlProps {
  allResources: Array<Resource>;
  isForOneResource: boolean;
  resource: ResourceData | null;
  states: Array<string>;
  statuses: Array<string>;
  type: string;
}

export const getDetailsPanelQueriers = ({ resource, type }): object => {
  const { id, parentId, uuid } = resource;

  const resourcesDetailsEndpoint = equals(type, 'host')
    ? `${centreonBaseURL}/api/latest/monitoring/resources/hosts/${id}`
    : `${centreonBaseURL}/api/latest/monitoring/resources/hosts/${parentId}/services/${id}`;

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
  const formattedStatuses = statuses.map((status) => {
    return {
      id: status.toLocaleUpperCase(),
      name: `${status.charAt(0).toUpperCase()}${status.slice(1)}`
    };
  });

  const formattedStates = states.map((state) => {
    return {
      id: state,
      name: `${state.charAt(0).toUpperCase()}${state.slice(1)}`
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
              id: includes(resourceName, ['name', 'parent_name'])
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
