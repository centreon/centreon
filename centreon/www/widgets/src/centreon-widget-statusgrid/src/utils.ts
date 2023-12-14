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
  isEmpty,
  isNil,
  length,
  lt,
  lte
} from 'ramda';

import { Theme } from '@mui/material';

import {
  SeverityCode,
  getStatusColors,
  setUrlQueryParameters
} from '@centreon/ui';

import { Resource } from '../../models';

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
  resources: Array<Resource>;
  states: Array<string>;
  statuses: Array<string>;
  type: string;
}

export const getResourcesUrl = ({
  type,
  statuses,
  states,
  resources
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
    resources
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
              id: resourceName,
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

export const openResourceStatusPanel = ({ resource, type }): void => {
  const { id, parentId, uuid } = resource;

  const resourcesDetailsEndpoint = equals(type, 'host')
    ? `/centreon/api/latest/monitoring/resources/hosts/${id}`
    : `/centreon/api/latest/monitoring/resources/hosts/${parentId}/services/${id}`;

  const detailsPanel = [
    {
      name: 'details',
      value: {
        id,
        resourcesDetailsEndpoint,
        selectedTimePeriodId: 'last_24_h',
        tab: 'details',
        tabParameters: {},
        uuid
      }
    }
  ];

  setUrlQueryParameters(detailsPanel);
};
