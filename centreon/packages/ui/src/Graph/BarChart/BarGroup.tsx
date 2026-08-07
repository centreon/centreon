import { scaleBand, scaleOrdinal } from '@visx/scale';
import { BarGroupHorizontal, BarGroup as VisxBarGroup } from '@visx/shape';
import type { ScaleLinear } from 'd3-scale';
import { difference, equals, keys, omit, pick } from 'ramda';
import type { ComponentType } from 'react';
import { memo, useMemo } from 'react';

import { useDeepMemo } from '../../utils';
import {
  getSortedStackedLines,
  getStackedLinesTimeSeriesPerStackAndUnit,
  getTime,
  getTimeSeriesForLines,
  getUnits
} from '../common/timeSeries';
import type { Line, TimeValue } from '../common/timeSeries/models';
import MemoizedGroup from './MemoizedGroup';
import type { BarStyle } from './models';

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
  // biome-ignore lint/suspicious/noExplicitAny: visx bandwidth scale typing
  xScale: any;
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
    () =>
      // biome-ignore lint/suspicious/noExplicitAny: visx BarGroup union type
      (isHorizontal ? VisxBarGroup : BarGroupHorizontal) as ComponentType<any>,
    [isHorizontal]
  );

  const stackedLines = getSortedStackedLines(lines);
  const notStackedLines = difference(lines, stackedLines);
  const notStackedTimeSeries = getTimeSeriesForLines({
    lines: notStackedLines,
    timeSeries
  });

  const { stackedLinesTimeSeriesPerStackKeyAndUnit, stackedKeys } = useMemo(
    () =>
      getStackedLinesTimeSeriesPerStackAndUnit({ stackedLines, timeSeries }),
    [stackedLines, timeSeries]
  );

  const normalizedTimeSeries = notStackedTimeSeries.map((timeSerie) => ({
    ...timeSerie,
    ...stackedKeys
  }));

  const lineKeys = useDeepMemo({
    deps: [normalizedTimeSeries],
    variable: keys(omit(['timeTick'], normalizedTimeSeries[0]))
  });
  // @ts-expect-error - suppressing pre-existing type mismatch
  const sortedLineKeys = lineKeys.sort((lineKeyA: string, lineKeyB: string) => {
    if (lineKeyA.startsWith('stacked-') && !lineKeyB.startsWith('stacked-')) {
      return true;
    }

    const lineKeysA = lineKeyA.split('-');
    const lineKeysB = lineKeyB.split('-');

    return lineKeysA[2] === '' && lineKeysB[2] !== '';
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
    [...lineKeys, ...colors, colors, lineKeys]
  );
  const metricScale = useMemo(
    () =>
      scaleBand({
        domain: lineKeys,
        padding: 0.1,
        range: [0, xScale.bandwidth()]
      }),
    [lineKeys, xScale.bandwidth]
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
    <BarComponent
      color={colorScale}
      data={normalizedTimeSeries}
      height={size}
      keys={sortedLineKeys}
      {...barComponentBaseProps}
    >
      {/* biome-ignore lint/suspicious/noExplicitAny: visx BarGroup union type */}
      {(barGroups: Array<any>) =>
        barGroups.map((barGroup, index) => {
          return (
            <MemoizedGroup
              barGroup={barGroup}
              barIndex={index}
              barStyle={barStyle}
              isHorizontal={isHorizontal}
              isTooltipHidden={isTooltipHidden}
              key={`bar-group-${barGroup.index}-${barGroup.x0}`}
              neutralValue={neutralValue}
              notStackedLines={notStackedLines}
              notStackedTimeSeries={notStackedTimeSeries}
              stackedLinesTimeSeriesPerStackKeyAndUnit={
                stackedLinesTimeSeriesPerStackKeyAndUnit
              }
              yScalesPerUnit={yScalesPerUnit}
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
    // @ts-expect-error - suppressing pre-existing type mismatch
    equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps)) &&
    equals(prevYScale, nextYScale) &&
    equals(prevXScale, nextXScale)
  );
});
