import numeral from 'numeral';
import {
  T,
  always,
  cond,
  equals,
  flatten,
  gt,
  gte,
  head,
  isEmpty,
  isNil,
  last,
  length,
  lt,
  lte,
  pluck,
  type
} from 'ramda';

import { Theme, darken, getLuminance, lighten } from '@mui/material';

import dayjs from 'dayjs';
import { BarStyle } from '../BarChart/models';
import { margin } from '../Chart/common';
import { LineStyle } from '../Chart/models';
import { Threshold, Thresholds } from './models';
import { formatMetricValueWithUnit } from './timeSeries';
import { Line, TimeValue } from './timeSeries/models';

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

interface NormalizeLevelProps {
  factor: number;
  level: number;
}

const normalizeLevel = ({ level, factor }: NormalizeLevelProps): number =>
  (level * factor) / 10;

interface EmphasizeCurveColorProps {
  color: string;
  index: number;
}

export const emphasizeCurveColor = ({
  color,
  index
}: EmphasizeCurveColorProps): string => {
  const totalLevels = 5;
  const levels = [...Array(totalLevels).keys()];
  const factor = 10 / totalLevels;

  if (gte(getLuminance(color), 0.5)) {
    if (gte(index, totalLevels * 2)) {
      return darken(color, normalizeLevel({ factor, level: last(levels) }));
    }
    if (gte(index, totalLevels)) {
      return darken(
        color,
        normalizeLevel({ factor, level: levels[totalLevels + 1 - index] })
      );
    }

    return lighten(color, normalizeLevel({ factor, level: levels[index] }));
  }

  if (gte(index, totalLevels * 2)) {
    return lighten(color, normalizeLevel({ factor, level: last(levels) }));
  }
  if (gte(index, totalLevels)) {
    return lighten(
      color,
      normalizeLevel({ factor, level: levels[totalLevels + 1 - index] })
    );
  }

  return darken(color, normalizeLevel({ factor, level: levels[index] }));
};

interface GetStrokeDashArrayProps {
  dashLength?: number;
  dashOffset?: number;
  dotOffset?: number;
  lineWidth?: number;
}

export const getStrokeDashArray = ({
  dashLength,
  dashOffset,
  dotOffset,
  lineWidth
}: GetStrokeDashArrayProps): string | undefined => {
  if (isNil(dotOffset) && isNil(dashLength) && isNil(dashOffset)) {
    return undefined;
  }

  if (dotOffset) {
    return `${lineWidth} ${dotOffset}`;
  }

  if (dashLength || dashOffset) {
    return `${dashLength || 1} ${dashOffset || 1}`;
  }

  return undefined;
};

export const getPointRadius = (lineWidth?: number): number =>
  Math.max(Math.ceil((lineWidth ?? 2) * 1.2), 2);

export const commonTickLabelProps = {
  fontFamily: 'Roboto, sans-serif',
  fontSize: 10,
  textAnchor: 'middle'
};

interface GetStyleProps {
  metricId?: number;
  style:
    | LineStyle
    | BarStyle
    | Array<LineStyle & { metricId: number }>
    | Array<BarStyle & { metricId: number }>;
}

export const getStyle = ({
  style,
  metricId
}: GetStyleProps): BarStyle | LineStyle => {
  return equals(type(style), 'Array')
    ? style.find((metricStyle) => equals(metricId, metricStyle.metricId))
    : style;
};

interface GetFormattedAxisValuesProps {
  thresholdUnit?: string;
  axisUnit: string;
  base?: number;
  timeSeries: Array<TimeValue>;
  threshold: Array<Threshold>;
  lines: Array<Line>;
}

export const getFormattedAxisValues = ({
  thresholdUnit,
  axisUnit,
  timeSeries,
  base = 1000,
  lines,
  threshold
}: GetFormattedAxisValuesProps): Array<string> => {
  const filteredMetrics = lines.filter(({ unit }) => equals(unit, axisUnit));

  if (isEmpty(filteredMetrics)) {
    return [];
  }

  const metricIds = pluck('metric_id', filteredMetrics);

  const formattedData = metricIds.map((metricId) =>
    timeSeries.map((data) =>
      formatMetricValueWithUnit({
        value: data[metricId],
        unit: axisUnit,
        base
      })
    )
  );

  const flattenedFormattedData = flatten(formattedData);

  const formattedThresholdValues = equals(thresholdUnit, axisUnit)
    ? threshold.map(({ value }) =>
        formatMetricValueWithUnit({
          value,
          unit: axisUnit,
          base
        })
      ) || []
    : [];

  return flattenedFormattedData
    .concat(formattedThresholdValues)
    .filter((v) => v) as Array<string>;
};

interface ComputeGElementMarginLeftProps {
  maxCharacters: number;
  hasSecondUnit?: boolean;
}

export const computeGElementMarginLeft = ({
  maxCharacters,
  hasSecondUnit
}: ComputeGElementMarginLeftProps): number =>
  maxCharacters * 5 + (hasSecondUnit ? margin.top * 0.8 : margin.top * 0.6);

export const computPixelsToShiftMouse = (xScale): number => {
  const domain = xScale.domain();

  const hoursDiffInGraph = dayjs(domain[1]).diff(domain[0], 'h');

  if (!hoursDiffInGraph) {
    return 0;
  }

  return Math.round(8 / hoursDiffInGraph);
};
