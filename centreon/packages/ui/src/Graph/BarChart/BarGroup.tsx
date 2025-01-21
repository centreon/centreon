import { scaleBand, scaleOrdinal } from '@visx/scale';
import { BarGroupHorizontal, BarGroup as VisxBarGroup } from '@visx/shape';
import { ScaleLinear } from 'd3-scale';
import { difference, equals, keys, omit, pick, pluck, uniq } from 'ramda';
import { memo, useMemo } from 'react';

import { useDeepMemo } from '../../utils';
import {
  getSortedStackedLines,
  getTime,
  getTimeSeriesForLines,
  getUnits
} from '../common/timeSeries';
import { Line, TimeValue } from '../common/timeSeries/models';
import MemoizedGroup from './MemoizedGroup';
import { BarStyle } from './models';

// Minimum value for logarithmic scale to avoid log(0)
const minLogScaleValue = 0.001;

const getNeutralValue = (scaleType?: 'linear' | 'logarithmic') =>
  equals(scaleType, 'logarithmic') ? minLogScaleValue : 0;

interface Props {
  barStyle: BarStyle;
  isTooltipHidden: boolean;
  lines: Array<Line>;
  orientation: 'horizontal' | 'vertical';
  size: number;
  timeSeries: Array<TimeValue>;
  xScale;
  yScalesPerUnit: Record<string, ScaleLinear<number, number>>;
  scaleType?: 'linear' | 'logarithmic';
}

const BarGroup = ({
  orientation,
  timeSeries,
  size,
  lines,
  xScale,
  yScalesPerUnit,
  isTooltipHidden,
  barStyle,
  scaleType
}: Props): JSX.Element => {
  const isHorizontal = equals(orientation, 'horizontal');

  const [firstUnit] = getUnits(lines);

  const BarComponent = useMemo(
    () => (isHorizontal ? VisxBarGroup : BarGroupHorizontal),
    [isHorizontal]
  );

  const stackedLines = getSortedStackedLines(lines);
  const stackedUnits = uniq(pluck('unit', stackedLines));
  const notStackedLines = difference(lines, stackedLines);

  const stackedKeys = stackedUnits.reduce(
    (acc, unit) => ({
      ...acc,
      [`stacked-${unit}`]: null
    }),
    {}
  );
  const stackedLinesTimeSeriesPerUnit = stackedUnits.reduce(
    (acc, stackedUnit) => {
      const relatedLines = stackedLines.filter(({ unit }) =>
        equals(unit, stackedUnit)
      );

      return {
        ...acc,
        [stackedUnit]: {
          lines: relatedLines,
          timeSeries: getTimeSeriesForLines({
            lines: relatedLines,
            timeSeries
          })
        }
      };
    },
    {}
  );

  const notStackedTimeSeries = getTimeSeriesForLines({
    lines: notStackedLines,
    timeSeries
  });

  const normalizedTimeSeries = notStackedTimeSeries.map((timeSerie) => ({
    ...timeSerie,
    ...stackedKeys
  }));

  const lineKeys = useDeepMemo({
    deps: [normalizedTimeSeries],
    variable: keys(omit(['timeTick'], normalizedTimeSeries[0]))
  });
  const colors = useDeepMemo({
    deps: [lineKeys, lines],
    variable: lineKeys.map((key) => {
      const metric = lines.find(({ metric_id }) =>
        equals(metric_id, Number(key))
      );

      return metric?.lineColor || '';
    })
  });

  const colorScale = useMemo(
    () =>
      scaleOrdinal<number, string>({
        domain: lineKeys,
        range: colors
      }),
    [...lineKeys, ...colors]
  );
  const metricScale = useMemo(
    () =>
      scaleBand({
        domain: lineKeys,
        padding: 0.1,
        range: [0, xScale.bandwidth()]
      }),
    [...lineKeys, xScale.bandwidth()]
  );

  const placeholderScale = yScalesPerUnit[firstUnit];

  const barComponentBaseProps = useMemo(
    () =>
      isHorizontal
        ? {
            x0: getTime,
            x0Scale: xScale,
            x1Scale: metricScale,
            yScale: placeholderScale
          }
        : {
            xScale: placeholderScale,
            y0: getTime,
            y0Scale: xScale,
            y1Scale: metricScale
          },
    [isHorizontal, placeholderScale, xScale, metricScale]
  );

  const neutralValue = useMemo(() => getNeutralValue(scaleType), [scaleType]);

  return (
    <BarComponent<TimeValue>
      color={colorScale}
      data={normalizedTimeSeries}
      height={size}
      keys={lineKeys}
      {...barComponentBaseProps}
    >
      {(barGroups) =>
        barGroups.map((barGroup, index) => {
          return (
            <MemoizedGroup
              key={`bar-group-${barGroup.index}-${barGroup.x0}`}
              barGroup={barGroup}
              barStyle={barStyle}
              stackedLinesTimeSeriesPerUnit={stackedLinesTimeSeriesPerUnit}
              notStackedTimeSeries={notStackedTimeSeries}
              notStackedLines={notStackedLines}
              isTooltipHidden={isTooltipHidden}
              isHorizontal={isHorizontal}
              neutralValue={neutralValue}
              yScalesPerUnit={yScalesPerUnit}
              barIndex={index}
            />
          );
        })
      }
    </BarComponent>
  );
};

const propsToMemoize = [
  'orientation',
  'timeSeries',
  'size',
  'lines',
  'secondUnit',
  'isCenteredZero',
  'barStyle',
  'scaleType'
];

export default memo(BarGroup, (prevProps, nextProps) => {
  const prevYScale = prevProps.yScalesPerUnit;
  const prevXScale = [
    ...prevProps.xScale.domain(),
    ...prevProps.xScale.range()
  ];

  const nextYScale = nextProps.yScalesPerUnit;
  const nextXScale = [
    ...nextProps.xScale.domain(),
    ...nextProps.xScale.range()
  ];

  return (
    equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps)) &&
    equals(prevYScale, nextYScale) &&
    equals(prevXScale, nextXScale)
  );
});
