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

import { SeverityCode, getStatusColors } from '@centreon/ui';

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
