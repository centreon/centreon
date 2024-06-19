import { useMemo } from 'react';

import { BarGroupHorizontal, BarGroup as VisxBarGroup } from '@visx/shape';
import { equals, gt, pick, pluck } from 'ramda';
import { scaleBand, scaleOrdinal } from '@visx/scale';
import { Group } from '@visx/group';

import { useDeepMemo } from '../../utils';
import { Line, TimeValue } from '../common/timeSeries/models';
import { getTime } from '../common/timeSeries';

import SingleBar from './SingleBar';

interface Props {
  height: number;
  isCenteredZero?: boolean;
  leftScale;
  lines: Array<Line>;
  orientation: 'horizontal' | 'vertical';
  rightScale;
  secondUnit?: string;
  timeSeries: Array<TimeValue>;
  xScale;
}

const getInvertedBarLength = ({
  useRightScale,
  rightScale,
  leftScale,
  value
}): number | null => {
  const scale = useRightScale ? rightScale : leftScale;

  return scale(value);
};

const getBarLength = ({
  height,
  value,
  invertedBarLength,
  lengthToMatchZero,
  isCenteredZero,
  isHorizontal
}): number => {
  if (!value) {
    return 0;
  }

  if (!isHorizontal && gt(0, value) && isCenteredZero) {
    return height - lengthToMatchZero - invertedBarLength;
  }

  if (!isHorizontal && gt(value, 0) && gt(invertedBarLength, 0)) {
    return invertedBarLength;
  }

  if (!isHorizontal && gt(value, 0)) {
    return invertedBarLength + (height - lengthToMatchZero);
  }

  if (!isHorizontal) {
    return invertedBarLength - (height - lengthToMatchZero);
  }

  if (value < 0) {
    return Math.abs(invertedBarLength) - (height - lengthToMatchZero);
  }

  if (isCenteredZero) {
    const barLength = height - invertedBarLength;

    return height - invertedBarLength - barLength / 2;
  }

  return height - invertedBarLength - lengthToMatchZero;
};

const BarGroup = ({
  orientation,
  timeSeries,
  height,
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
      height={height}
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
                size={height}
              />
            ))}
          </Group>
        ))
      }
    </BarComponent>
  );
};

export default BarGroup;
