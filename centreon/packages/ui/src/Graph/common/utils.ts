import {
  T,
  always,
  cond,
  equals,
  gt,
  gte,
  head,
  length,
  lt,
  lte,
  pluck
} from 'ramda';

import { Theme } from '@mui/material';

import { Thresholds } from './models';

interface GetColorFromDataAndThresholdsProps {
  data: number;
  theme: Theme;
  thresholds: Thresholds;
}

export const getColorFromDataAndTresholds = ({
  data,
  thresholds,
  theme
}: GetColorFromDataAndThresholdsProps): string => {
  if (!thresholds.enabled) {
    return theme.palette.success.main;
  }

  const criticalValues = pluck('value', thresholds.critical).sort();
  const warningValues = pluck('value', thresholds.warning).sort();

  if (
    equals(length(criticalValues), 2) &&
    lte(criticalValues[0], data) &&
    gte(criticalValues[1], data)
  ) {
    return theme.palette.error.main;
  }

  if (
    equals(length(warningValues), 2) &&
    lte(warningValues[0], data) &&
    gte(warningValues[1], data)
  ) {
    return theme.palette.warning.main;
  }

  if (equals(length(warningValues), 2)) {
    return theme.palette.success.main;
  }

  const criticalValue = head(criticalValues) as number;
  const warningValue = head(warningValues) as number;

  if (gt(warningValue, criticalValue)) {
    return cond([
      [lt(warningValue), always(theme.palette.success.main)],
      [lt(criticalValue), always(theme.palette.warning.main)],
      [T, always(theme.palette.error.main)]
    ])(data);
  }

  return cond([
    [gt(warningValue), always(theme.palette.success.main)],
    [gt(criticalValue), always(theme.palette.warning.main)],
    [T, always(theme.palette.error.main)]
  ])(data);
};
