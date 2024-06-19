import { memo, useMemo } from 'react';

import { BarGroupHorizontal, BarGroup as VisxBarGroup } from '@visx/shape';
import { equals, gt, pick, pluck } from 'ramda';
import { scaleBand, scaleOrdinal } from '@visx/scale';
import { Group } from '@visx/group';

import { useDeepMemo } from '../../utils';
import { Line, TimeValue } from '../common/timeSeries/models';
import { getTime } from '../common/timeSeries';

import SingleBar from './SingleBar';

interface Props {
  isCenteredZero?: boolean;
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
  isCenteredZero
}: Props): JSX.Element => {
  const isHorizontal = equals(orientation, 'horizontal');

  const BarComponent = useMemo(
    () => (isHorizontal ? VisxBarGroup : BarGroupHorizontal),
    [isHorizontal]
  );

  const keys = useDeepMemo({
    deps: [lines],
    variable: pluck('metric_id', lines)
  });
  const colors = useDeepMemo({
    deps: [lines],
    variable: pluck('lineColor', lines)
  });
  const colorScale = useMemo(
    () =>
      scaleOrdinal<number, string>({
        domain: keys,
        range: colors
      }),
    [...keys, ...colors]
  );
  const metricScale = useMemo(
    () =>
      scaleBand({
        domain: keys,
        padding: 0.1,
        range: [0, xScale.bandwidth()]
      }),
    [...keys, xScale.bandwidth()]
  );

  const displayedTimeSeries = useDeepMemo({
    deps: [timeSeries],
    variable: timeSeries.map((timeSerie) =>
      pick([...keys, 'timeTick'], timeSerie)
    )
  });

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
      data={displayedTimeSeries}
      height={size}
      keys={keys}
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
              <SingleBar
                bar={bar}
                isCenteredZero={isCenteredZero}
                isHorizontal={isHorizontal}
                key={`bar-group-bar-${barGroup.index}-${bar.index}-${bar.value}-${bar.key}`}
                leftScale={leftScale}
                lines={lines}
                rightScale={rightScale}
                secondUnit={secondUnit}
                size={size}
              />
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
  'isCenteredZero'
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
