import { useMemo } from 'react';

import { BarGroupHorizontal, BarGroup as VisxBarGroup } from '@visx/shape';
import { equals, isNil, pick, pluck, toString } from 'ramda';
import { scaleBand, scaleOrdinal } from '@visx/scale';
import { Group } from '@visx/group';

import { Line, TimeValue } from '../common/timeSeries/models';
import { getTime } from '../common/timeSeries';

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

  if (!isHorizontal) {
    if (value < 0) {
      if (isCenteredZero) {
        return height - lengthToMatchZero - invertedBarLength;
      }
      if (invertedBarLength > 0) {
        return invertedBarLength;
      }

      return invertedBarLength + (height - lengthToMatchZero);
    }

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

  const keys = pluck('metric_id', lines).map(toString);
  const colors = pluck('lineColor', lines);
  const colorScale = scaleOrdinal<string, string>({
    domain: keys,
    range: colors
  });
  const metricScale = scaleBand({
    domain: keys,
    padding: 0.1,
    range: [0, xScale.bandwidth()]
  });

  const displayedTimeSeries = timeSeries.map((timeSerie) =>
    pick([...keys, 'timeTick'], timeSerie)
  );

  const barComponentBaseProps = isHorizontal
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
      };

  const left0Height = leftScale(0);
  const right0Height = rightScale(0);

  return (
    <BarComponent<TimeValue>
      color={colorScale}
      data={displayedTimeSeries}
      height={height}
      keys={keys}
      {...barComponentBaseProps}
    >
      {(barGroups) =>
        barGroups.map((barGroup) => {
          return (
            <Group
              key={`bar-group-${barGroup.index}-${barGroup.x0}`}
              left={barGroup.x0}
              top={barGroup.y0}
            >
              {barGroup.bars.map((bar) => {
                const metric = lines.find(({ metric_id }) =>
                  equals(metric_id, Number(bar.key))
                );
                const useRightScale = equals(secondUnit, metric?.unit);

                const invertedBarLength = getInvertedBarLength({
                  leftScale,
                  rightScale,
                  useRightScale,
                  value: bar.value
                });

                const lengthToMatchZero =
                  height - (useRightScale ? right0Height : left0Height);

                const barLength = getBarLength({
                  height,
                  invertedBarLength,
                  isCenteredZero,
                  isHorizontal,
                  lengthToMatchZero,
                  value: bar.value
                });

                const barPadding = isHorizontal
                  ? height -
                    barLength -
                    lengthToMatchZero +
                    (bar.value < 0 ? barLength : 0)
                  : height -
                    lengthToMatchZero -
                    (bar.value < 0 ? barLength : 0);

                return (
                  <rect
                    fill={bar.color}
                    height={isHorizontal ? barLength : bar.height}
                    key={`bar-group-bar-${barGroup.index}-${bar.index}-${bar.value}-${bar.key}`}
                    rx={(isHorizontal ? bar.width : bar.height) * 0.2}
                    width={isHorizontal ? bar.width : barLength}
                    x={isHorizontal ? bar.x : barPadding}
                    y={isHorizontal ? barPadding : bar.y}
                  />
                );
              })}
            </Group>
          );
        })
      }
    </BarComponent>
  );
};

export default BarGroup;
