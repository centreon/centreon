import {
  T,
  always,
  cond,
  equals,
  gt,
  gte,
  head,
  isEmpty,
  isNil,
  length,
  lt,
  lte
} from 'ramda';

import { Theme } from '@mui/material';

import { SeverityCode, getResourcesUrl, getStatusColors } from '@centreon/ui';

import { IndicatorType } from './models';

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
  })?.backgroundColor;
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

const getBALink = (id: number): string => {
  return `/main.php?p=20701&o=d&ba_id=${id}`;
};

export const getBooleanRuleLink = (id: number): string => {
  return `/main.php?p=62611&o=c&boolean_id=${id}`;
};

const getResourcesStatusLink = ({ type, id, hostId, name }): string => {
  const resourceStatusType = equals(type, IndicatorType.MetaService)
    ? 'metaservice'
    : type;

  const uuid = cond([
    [equals(IndicatorType.MetaService), always(`m${id}`)],
    [equals(IndicatorType.Service), always(`h${hostId}-s${id}`)],
    [equals(IndicatorType.AnomalyDetection), always(`a${id}`)]
  ])(type);

  return getResourcesUrl({
    allResources: [],
    isForOneResource: true,
    resource: {
      id,
      name,
      parentId: hostId,
      type: resourceStatusType,
      uuid
    },
    states: [],
    statuses: [],
    type: resourceStatusType
  });
};

export const getLink = ({ type, id, name, hostId }): string => {
  return cond([
    [equals(IndicatorType.BusinessActivity), always(getBALink(id))],
    [
      equals(IndicatorType.Service),
      always(getResourcesStatusLink({ hostId, id, name, type }))
    ],
    [
      equals(IndicatorType.MetaService),
      always(getResourcesStatusLink({ hostId, id, name, type }))
    ],
    [
      equals(IndicatorType.AnomalyDetection),
      always(getResourcesStatusLink({ hostId, id, name, type }))
    ],
    [equals(IndicatorType.BooleanRule), always(getBooleanRuleLink(id))]
  ])(type);
};

export const getMetricsEndpoint = ({ resouceType, id, parentId }): string => {
  if (equals(resouceType, 'meta-service')) {
    return `/monitoring/metaservices/${id}/metrics?page=1&limit=30`;
  }

  return `/monitoring/hosts/${parentId}/services/${id}/metrics`;
};
