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
import numeral from 'numeral';

import { Theme } from '@mui/material';

import { Thresholds } from './models';

interface GetColorFromDataAndThresholdsProps {
  baseColor?: string;
  data: number;
  theme: Theme;
  thresholds: Thresholds;
}

export const getColorFromDataAndTresholds = ({
  data,
  thresholds,
  theme,
  baseColor
}: GetColorFromDataAndThresholdsProps): string => {
  if (!thresholds.enabled) {
    return baseColor || theme.palette.primary.main;
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

interface ValueByUnitProps {
  total: number;
  unit: 'percentage' | 'number';
  value: number;
}

export const getValueByUnit = ({
  unit,
  value,
  total
}: ValueByUnitProps): string => {
  if (equals(unit, 'number')) {
    return numeral(value).format('0a').toUpperCase();
  }

  return `${((value * 100) / total).toFixed(1)}%`;
};
