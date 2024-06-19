import numeral from 'numeral';
import { Scale } from '@visx/visx';
import { bisector } from 'd3-array';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import {
  map,
  pipe,
  reduce,
  filter,
  addIndex,
  isNil,
  path,
  reject,
  equals,
  keys,
  prop,
  flatten,
  propEq,
  uniq,
  find,
  sortBy,
  add,
  isEmpty,
  any,
  not,
  min,
  max,
  lt,
  identity,
  head,
  last,
  cond,
  always,
  T,
  includes,
  split
} from 'ramda';

import { margin } from '../../LineChart/common';
import { LineChartData } from '../models';

import {
  Metric,
  TimeValue,
  Line,
  AxeScale,
  Xscale,
  FormatMetricValueProps,
  YScales,
  TimeValueProps
} from './models';

interface TimeTickWithMetrics {
  metrics: Array<Metric>;
  timeTick: string;
}

const toTimeTickWithMetrics = ({
  metrics,
  times
}): Array<TimeTickWithMetrics> =>
  map(
    (timeTick) => ({
      metrics,
      timeTick
    }),
    times
  );

const toTimeTickValue = (
  { timeTick, metrics }: TimeTickWithMetrics,
  timeIndex: number
): TimeValue => {
  const getMetricsForIndex = (): Omit<TimeValue, 'timeTick'> => {
    const addMetricForTimeIndex = (acc, { metric_id, data }): TimeValue => ({
      ...acc,
      [metric_id]: data[timeIndex]
    });

    return reduce(addMetricForTimeIndex, {} as TimeValue, metrics);
  };

  return { timeTick, ...getMetricsForIndex() };
};

const getTimeSeries = (graphData: LineChartData): Array<TimeValue> => {
  const isGreaterThanLowerLimit = (value): boolean => {
    const lowerLimit = path<number>(['global', 'lower-limit'], graphData);

    if (isNil(lowerLimit)) {
      return true;
    }

    return value >= lowerLimit;
  };

  const rejectLowerThanLimit = ({
    timeTick,
    ...metrics
  }: TimeValue): TimeValue => ({
    ...filter(isGreaterThanLowerLimit, metrics),
    timeTick
  });

  const indexedMap = addIndex<TimeTickWithMetrics, TimeValue>(map);

  return pipe(
    toTimeTickWithMetrics,
    indexedMap(toTimeTickValue),
    map(rejectLowerThanLimit)
  )(graphData);
};

const toLine = ({
  ds_data,
  legend,
  metric,
  unit,
  average_value,
  minimum_value,
  maximum_value,
  metric_id
}: Metric): Line => ({
  areaColor: ds_data.ds_color_area,
  average_value,
  color: ds_data.ds_color_line,
  display: true,
  filled: ds_data.ds_filled,
  highlight: undefined,
  invert: ds_data.ds_invert,
  legend: ds_data.ds_legend,
  lineColor: ds_data.ds_color_line,
  maximum_value,
  metric,
  metric_id,
  minimum_value,
  name: legend,
  stackOrder:
    equals(ds_data.ds_stack, '1') || equals(ds_data.ds_stack, true)
      ? parseInt(ds_data.ds_order || '0', 10)
      : null,
  transparency: ds_data.ds_transparency,
  unit
});

const getLineData = (graphData: LineChartData): Array<Line> =>
  map(toLine, graphData.metrics);

const getMin = (values: Array<number>): number => Math.min(...values);

const getMax = (values: Array<number>): number => Math.max(...values);

const getTime = (timeValue: TimeValue): number =>
  new Date(timeValue.timeTick).valueOf();

const getMetrics = (timeValue: TimeValue): Array<string> =>
  pipe(keys, reject(equals('timeTick')))(timeValue);

const getValueForMetric =
  (timeValue: TimeValue) =>
  (metric_id: number): number =>
    prop(metric_id, timeValue) as number;

const getUnits = (lines: Array<Line>): Array<string> =>
  pipe(map(prop('unit')), uniq)(lines);

interface ValuesForUnitProps {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  unit: string;
}

const getMetricValuesForUnit = ({
  lines,
  timeSeries,
  unit
}: ValuesForUnitProps): Array<number> => {
  const getTimeSeriesValuesForMetric = (metric_id): Array<number> =>
    map(
      (timeValue) => getValueForMetric(timeValue)(metric_id),
      timeSeries
    ) as Array<number>;

  return pipe(
    filter(propEq(unit, 'unit')) as (line) => Array<Line>,
    map(prop('metric_id')),
    map(getTimeSeriesValuesForMetric),
    flatten,
    reject(isNil)
  )(lines) as Array<number>;
};

const getDates = (timeSeries: Array<TimeValue>): Array<Date> => {
  const toTimeTick = ({ timeTick }: TimeValue): string => timeTick;
  const toDate = (tick: string): Date => new Date(tick);

  return pipe(map(toTimeTick), map(toDate))(timeSeries);
};

interface LineForMetricProps {
  lines: Array<Line>;
  metric_id: number;
}

const getLineForMetric = ({
  lines,
  metric_id
}: LineForMetricProps): Line | undefined =>
  find(propEq(metric_id, 'metric_id'), lines);

interface LinesForMetricsProps {
  lines: Array<Line>;
  metricIds: Array<number>;
}

export const getLinesForMetrics = ({
  lines,
  metricIds
}: LinesForMetricsProps): Array<Line> =>
  filter(({ metric_id }) => metricIds.includes(metric_id), lines);

interface LinesTimeSeries {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

const getMetricValuesForLines = ({
  lines,
  timeSeries
}: LinesTimeSeries): Array<number> =>
  pipe(
    getUnits,
    map((unit) => getMetricValuesForUnit({ lines, timeSeries, unit })),
    flatten
  )(lines);

const getStackedMetricValues = ({
  lines,
  timeSeries
}: LinesTimeSeries): Array<number> => {
  const getTimeSeriesValuesForMetric = (metric_id): Array<number> =>
    map((timeValue) => getValueForMetric(timeValue)(metric_id), timeSeries);

  const metricsValues = pipe(
    map(prop('metric_id')) as (metric) => Array<number>,
    map(getTimeSeriesValuesForMetric) as () => Array<Array<number>>
  )(lines as Array<Line>);

  if (isEmpty(metricsValues) || isNil(metricsValues)) {
    return [];
  }

  return metricsValues[0].map((_, index): number =>
    reduce(
      (acc: number, metricValue: Array<number>) => add(metricValue[index], acc),
      0,
      metricsValues
    )
  );
};

const getSortedStackedLines = (lines: Array<Line>): Array<Line> =>
  pipe(
    reject(({ stackOrder }: Line): boolean => isNil(stackOrder)) as (
      lines
    ) => Array<Line>,
    sortBy(prop('stackOrder'))
  )(lines);

const getInvertedStackedLines = (lines: Array<Line>): Array<Line> =>
  pipe(
    reject(({ invert }: Line): boolean => isNil(invert)) as (
      lines
    ) => Array<Line>,
    getSortedStackedLines
  )(lines);

const getNotInvertedStackedLines = (lines: Array<Line>): Array<Line> =>
  pipe(
    filter(({ invert }: Line): boolean => isNil(invert)) as (
      lines
    ) => Array<Line>,
    getSortedStackedLines
  )(lines);

interface HasStackedLines {
  lines: Array<Line>;
  unit: string;
}

const hasUnitStackedLines = ({ lines, unit }: HasStackedLines): boolean =>
  pipe(getSortedStackedLines, any(propEq(unit, 'unit')))(lines);

const getTimeSeriesForLines = ({
  lines,
  timeSeries
}: LinesTimeSeries): Array<TimeValue> => {
  const metrics = map(prop('metric_id'), lines);

  return map(
    ({ timeTick, ...metricsValue }): TimeValue => ({
      ...reduce(
        (acc, metric_id): Omit<TimeValue, 'timePick'> => ({
          ...acc,
          [metric_id]: metricsValue[metric_id]
        }),
        {},
        metrics
      ),
      timeTick
    }),
    timeSeries
  );
};

interface GetYScaleProps {
  hasMoreThanTwoUnits: boolean;
  invert: string | null;
  leftScale: ScaleLinear<number, number>;
  rightScale: ScaleLinear<number, number>;
  secondUnit: string;
  unit: string;
}

const getYScale = ({
  hasMoreThanTwoUnits,
  unit,
  secondUnit,
  leftScale,
  rightScale,
  invert
}: GetYScaleProps): ScaleLinear<number, number> => {
  const isLeftScale = hasMoreThanTwoUnits || unit !== secondUnit;
  const scale = isLeftScale ? leftScale : rightScale;

  return invert
    ? Scale.scaleLinear<number>({
        domain: scale.domain().reverse(),
        nice: true,
        range: scale.range().reverse()
      })
    : scale;
};

const getScaleType = (
  scale: 'linear' | 'logarithimc'
): typeof Scale.scaleLinear | typeof Scale.scaleLog =>
  equals(scale, 'logarithmic') ? Scale.scaleLog : Scale.scaleLinear;

const getScale = ({
  graphValues,
  height,
  stackedValues,
  thresholds,
  isCenteredZero,
  scale,
  scaleLogarithmicBase,
  isHorizontal
}): ScaleLinear<number, number> => {
  const isLogScale = equals(scale, 'logarithmic');
  const minValue = Math.min(
    getMin(graphValues),
    getMin(stackedValues),
    Math.min(...thresholds)
  );
  const maxValue = Math.max(
    getMax(graphValues),
    getMax(stackedValues),
    Math.max(...thresholds)
  );

  const scaleType = getScaleType(scale);

  const upperRangeValue = minValue === maxValue && maxValue === 0 ? height : 0;
  const range = [height, upperRangeValue];

  if (isCenteredZero) {
    const greatestValue = Math.max(Math.abs(maxValue), Math.abs(minValue));

    return scaleType<number>({
      base: scaleLogarithmicBase || 2,
      domain: [-greatestValue, greatestValue],
      range: isHorizontal ? range : range.reverse()
    });
  }

  return scaleType<number>({
    base: scaleLogarithmicBase || 2,
    domain: [isLogScale ? 0.001 : minValue, maxValue],
    range: isHorizontal ? range : range.reverse()
  });
};

const getLeftScale = ({
  dataLines,
  dataTimeSeries,
  valueGraphHeight,
  thresholds,
  thresholdUnit,
  isCenteredZero,
  scale,
  scaleLogarithmicBase,
  isHorizontal = true
}: AxeScale): ScaleLinear<number, number> => {
  const [firstUnit, secondUnit, thirdUnit] = getUnits(dataLines);

  const shouldApplyThresholds =
    equals(thresholdUnit, firstUnit) ||
    equals(thresholdUnit, thirdUnit) ||
    !thresholdUnit;

  const graphValues = isNil(thirdUnit)
    ? getMetricValuesForUnit({
        lines: dataLines,
        timeSeries: dataTimeSeries,
        unit: firstUnit
      })
    : getMetricValuesForLines({
        lines: dataLines,
        timeSeries: dataTimeSeries
      });

  const firstUnitHasStackedLines =
    isNil(thirdUnit) && not(isNil(firstUnit))
      ? hasUnitStackedLines({ lines: dataLines, unit: firstUnit })
      : false;

  const stackedValues = firstUnitHasStackedLines
    ? getStackedMetricValues({
        lines: getSortedStackedLines(dataLines).filter(
          ({ unit }) => !equals(unit, secondUnit)
        ),
        timeSeries: dataTimeSeries
      })
    : [0];

  return getScale({
    graphValues,
    height: valueGraphHeight,
    isCenteredZero,
    isHorizontal,
    scale,
    scaleLogarithmicBase,
    stackedValues,
    thresholds: shouldApplyThresholds ? thresholds : []
  });
};

const getXScale = ({
  dataTime,
  valueWidth
}: Xscale): ScaleTime<number, number, never> => {
  return Scale.scaleTime<number>({
    domain: [getMin(dataTime.map(getTime)), getMax(dataTime.map(getTime))],
    range: [0, valueWidth]
  });
};

export const getXScaleBand = ({
  dataTime,
  valueWidth
}: Xscale): ReturnType<typeof Scale.scaleBand<number>> => {
  return Scale.scaleBand({
    domain: dataTime.map(getTime),
    padding: 0.2,
    range: [0, valueWidth]
  });
};

const getRightScale = ({
  dataLines,
  dataTimeSeries,
  valueGraphHeight,
  thresholds,
  thresholdUnit,
  isCenteredZero,
  scale,
  scaleLogarithmicBase,
  isHorizontal = true
}: AxeScale): ScaleLinear<number, number> => {
  const [, secondUnit] = getUnits(dataLines);

  const graphValues = getMetricValuesForUnit({
    lines: dataLines,
    timeSeries: dataTimeSeries,
    unit: secondUnit
  });

  const shouldApplyThresholds = equals(thresholdUnit, secondUnit);

  const secondUnitHasStackedLines = isNil(secondUnit)
    ? false
    : hasUnitStackedLines({ lines: dataLines, unit: secondUnit });

  const stackedValues = secondUnitHasStackedLines
    ? getStackedMetricValues({
        lines: getSortedStackedLines(dataLines).filter(({ unit }) =>
          equals(unit, secondUnit)
        ),
        timeSeries: dataTimeSeries
      })
    : [0];

  return getScale({
    graphValues,
    height: valueGraphHeight,
    isCenteredZero,
    isHorizontal,
    scale,
    scaleLogarithmicBase,
    stackedValues,
    thresholds: shouldApplyThresholds ? thresholds : []
  });
};

const formatTime = (value: number): string => {
  if (value < 1000) {
    return `${numeral(value).format('0.[00]a')} ms`;
  }

  const t = numeral(value / 1000).format('0.[00]a');

  return `${t} seconds`;
};

const registerMsUnitToNumeral = (): null => {
  try {
    numeral.register('format', 'milliseconds', {
      format: (value) => {
        return formatTime(value);
      },
      regexps: {
        format: /(ms)/,
        unformat: /(ms)/
      },
      unformat: () => ''
    });

    return null;
  } catch (_) {
    return null;
  }
};

registerMsUnitToNumeral();

const getBase1024 = ({ unit, base }): boolean => {
  const base2Units = [
    'B',
    'bytes',
    'bytespersecond',
    'B/s',
    'B/sec',
    'o',
    'octets',
    'b/s',
    'b'
  ];

  return base2Units.includes(unit) || Number(base) === 1024;
};

const formatMetricValue = ({
  value,
  unit,
  base = 1000
}: FormatMetricValueProps): string | null => {
  if (isNil(value)) {
    return null;
  }

  const base1024 = getBase1024({ base, unit });

  const formatSuffix = cond([
    [equals('ms'), always(' ms')],
    [T, always(base1024 ? ' ib' : 'a')]
  ])(unit);

  const formattedMetricValue = numeral(Math.abs(value))
    .format(`0.[00]${formatSuffix}`)
    .replace(/iB/g, unit);

  if (lt(value, 0)) {
    return `-${formattedMetricValue}`;
  }

  return formattedMetricValue;
};

const formatMetricValueWithUnit = ({
  value,
  unit,
  base = 1000,
  isRaw = false
}: FormatMetricValueProps & { isRaw?: boolean }): string | null => {
  if (isNil(value)) {
    return null;
  }

  const base1024 = getBase1024({ base, unit });

  if (isRaw) {
    const unitText = equals('%', unit) ? unit : ` ${unit}`;

    return `${value}${unitText}`;
  }

  if (equals('%', unit)) {
    return `${numeral(Math.abs(value)).format('0.[00]')}%`;
  }

  const formattedMetricValue = formatMetricValue({ base, unit, value });

  return base1024 || !unit || equals(unit, 'ms')
    ? formattedMetricValue
    : `${formattedMetricValue} ${unit}`;
};

const getStackedYScale = ({
  leftScale,
  rightScale
}: YScales): ScaleLinear<number, number> => {
  const minDomain = min(
    getMin(leftScale.domain()),
    getMin(rightScale.domain())
  );
  const maxDomain = max(
    getMax(leftScale.domain()),
    getMax(rightScale.domain())
  );

  const minRange = min(getMin(leftScale.range()), getMin(rightScale.range()));
  const maxRange = max(getMax(leftScale.range()), getMax(rightScale.range()));

  return Scale.scaleLinear<number>({
    domain: [minDomain, maxDomain],
    nice: true,
    range: [maxRange, minRange]
  });
};

const bisectDate = bisector(identity).center;

const getTimeValue = ({
  x,
  xScale,
  timeSeries,
  marginLeft = margin.left
}: TimeValueProps): TimeValue | null => {
  if (isNil(x)) {
    return null;
  }
  const date = xScale.invert(x - marginLeft);
  const index = bisectDate(getDates(timeSeries), date);

  return timeSeries[index];
};

const getMetricWithLatestData = (
  graphData: LineChartData
): Metric | undefined => {
  const metric = head(graphData.metrics) as Metric;

  const lastData = last(metric?.data.filter((v) => v) || []);

  return {
    ...metric,
    data: lastData ? [lastData] : []
  };
};

interface FormatMetricNameProps {
  legend: string | null;
  name: string;
}

export const formatMetricName = ({
  legend,
  name
}: FormatMetricNameProps): string => {
  const legendName = legend || name;
  const metricName = includes('#', legendName)
    ? split('#')(legendName)[1]
    : legendName;

  return metricName;
};

export {
  getTimeSeries,
  getLineData,
  getMin,
  getMax,
  getTime,
  getMetrics,
  getValueForMetric,
  getMetricValuesForUnit,
  getUnits,
  getDates,
  getLineForMetric,
  getMetricValuesForLines,
  getSortedStackedLines,
  getTimeSeriesForLines,
  getStackedMetricValues,
  getInvertedStackedLines,
  getNotInvertedStackedLines,
  hasUnitStackedLines,
  getYScale,
  getScale,
  getLeftScale,
  getXScale,
  getRightScale,
  formatMetricValue,
  getStackedYScale,
  getTimeValue,
  bisectDate,
  getMetricWithLatestData,
  formatMetricValueWithUnit
};
