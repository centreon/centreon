import { memo, useMemo } from 'react';

import { BarGroupHorizontal, BarGroup as VisxBarGroup } from '@visx/shape';
import { difference, equals, isEmpty, keys, omit, pick } from 'ramda';
import { scaleBand, scaleOrdinal } from '@visx/scale';
import { Group } from '@visx/group';

import { useDeepMemo } from '../../utils';
import { Line, TimeValue } from '../common/timeSeries/models';
import {
  getSortedStackedLines,
  getTime,
  getTimeSeriesForLines
} from '../common/timeSeries';

import SingleBar from './SingleBar';
import BarStack from './BarStack';
import { BarStyle } from './models';

interface Props {
  barStyle: BarStyle;
  isCenteredZero?: boolean;
  isTooltipHidden: boolean;
  leftScale;
  lines: Array<Line>;
  orientation: 'horizontal' | 'vertical';
  rightScale;
  secondUnit?: string;
  size: number;
  timeSeries: Array<TimeValue>;
  xScale;
}

const BarGroup = ({
  orientation,
  timeSeries,
  size,
  lines,
  xScale,
  leftScale,
  rightScale,
  secondUnit,
  isCenteredZero,
  isTooltipHidden,
  barStyle
}: Props): JSX.Element => {
  const isHorizontal = equals(orientation, 'horizontal');

  const BarComponent = useMemo(
    () => (isHorizontal ? VisxBarGroup : BarGroupHorizontal),
    [isHorizontal]
  );

  const stackedLines = getSortedStackedLines(lines);
  const notStackedLines = difference(lines, stackedLines);

  const stackLinesRight = stackedLines.filter(({ unit }) =>
    equals(unit, secondUnit)
  );
  const stackLinesLeft = stackedLines.filter(
    ({ unit }) => !equals(unit, secondUnit)
  );
  const hasStackedLinesRight = !isEmpty(stackLinesRight)
    ? { stackedRight: null }
    : {};
  const hasStackedLinesLeft = !isEmpty(stackLinesLeft)
    ? { stackedLeft: null }
    : {};

  const stackedTimeSeriesRight = getTimeSeriesForLines({
    lines: stackLinesRight,
    timeSeries
  });
  const stackedTimeSeriesLeft = getTimeSeriesForLines({
    lines: stackLinesLeft,
    timeSeries
  });
  const notStackedTimeSeries = getTimeSeriesForLines({
    lines: notStackedLines,
    timeSeries
  });

  const normalizedTimeSeries = notStackedTimeSeries.map((timeSerie) => ({
    ...timeSerie,
    ...hasStackedLinesRight,
    ...hasStackedLinesLeft
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

  const barComponentBaseProps = useMemo(
    () =>
      isHorizontal
        ? {
            x0: getTime,
            x0Scale: xScale,
            x1Scale: metricScale,
            yScale: leftScale
          }
        : {
            xScale: leftScale,
            y0: getTime,
            y0Scale: xScale,
            y1Scale: metricScale
          },
    [isHorizontal, leftScale, rightScale, xScale, metricScale]
  );

  return (
    <BarComponent<TimeValue>
      color={colorScale}
      data={normalizedTimeSeries}
      height={size}
      keys={lineKeys}
      {...barComponentBaseProps}
    >
      {(barGroups) =>
        barGroups.map((barGroup) => (
          <Group
            key={`bar-group-${barGroup.index}-${barGroup.x0}`}
            left={barGroup.x0}
            top={barGroup.y0}
          >
            {barGroup.bars.map((bar) => (
              <>
                <SingleBar
                  bar={bar}
                  barStyle={barStyle}
                  isCenteredZero={isCenteredZero}
                  isHorizontal={isHorizontal}
                  isTooltipHidden={isTooltipHidden}
                  key={`bar-group-bar-${barGroup.index}-${bar.index}-${bar.value}-${bar.key}`}
                  leftScale={leftScale}
                  lines={lines}
                  rightScale={rightScale}
                  secondUnit={secondUnit}
                  size={size}
                />
                {equals(bar.key, 'stackedRight') && (
                  <BarStack
                    barIndex={barGroup.index}
                    barPadding={isHorizontal ? bar.x : bar.y}
                    barStyle={barStyle}
                    barWidth={isHorizontal ? bar.width : bar.height}
                    isHorizontal={isHorizontal}
                    isTooltipHidden={isTooltipHidden}
                    lines={stackLinesRight}
                    timeSeries={stackedTimeSeriesRight}
                    yScale={rightScale}
                  />
                )}
                {equals(bar.key, 'stackedLeft') && (
                  <BarStack
                    barIndex={barGroup.index}
                    barPadding={isHorizontal ? bar.x : bar.y}
                    barStyle={barStyle}
                    barWidth={isHorizontal ? bar.width : bar.height}
                    isHorizontal={isHorizontal}
                    isTooltipHidden={isTooltipHidden}
                    lines={stackLinesLeft}
                    timeSeries={stackedTimeSeriesLeft}
                    yScale={leftScale}
                  />
                )}
              </>
            ))}
          </Group>
        ))
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
  'barStyle'
];

export default memo(BarGroup, (prevProps, nextProps) => {
  const prevLeftScale = [
    ...prevProps.leftScale.domain(),
    ...prevProps.leftScale.range()
  ];
  const prevRightScale = [
    ...prevProps.rightScale.domain(),
    ...prevProps.rightScale.range()
  ];
  const prevXScale = [
    ...prevProps.xScale.domain(),
    ...prevProps.xScale.range()
  ];

  const nextLeftScale = [
    ...nextProps.leftScale.domain(),
    ...nextProps.leftScale.range()
  ];
  const nextRightScale = [
    ...nextProps.rightScale.domain(),
    ...nextProps.rightScale.range()
  ];
  const nextXScale = [
    ...nextProps.xScale.domain(),
    ...nextProps.xScale.range()
  ];

  return (
    equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps)) &&
    equals(prevLeftScale, nextLeftScale) &&
    equals(prevRightScale, nextRightScale) &&
    equals(prevXScale, nextXScale)
  );
});
