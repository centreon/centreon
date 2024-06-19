import { useMemo } from 'react';

import { scaleBand, scaleOrdinal } from '@visx/scale';
import { BarStack as BarStackVertical, BarStackHorizontal } from '@visx/shape';
import { equals, keys, omit } from 'ramda';

import { useDeepMemo } from '../../utils';

const xScale = scaleBand<number>({
  domain: [0, 0],
  padding: 0,
  range: [0, 0]
});

const BarStack = ({
  timeSeries,
  isHorizontal,
  yScale,
  lines,
  barWidth,
  barPadding,
  barIndex
}): JSX.Element => {
  const BarStackComponent = isHorizontal
    ? BarStackVertical
    : BarStackHorizontal;

  const lineKeys = useDeepMemo({
    deps: [timeSeries],
    variable: keys(omit(['timeTick'], timeSeries[0]))
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

  const commonProps = isHorizontal
    ? {
        x: (d) => d.timeTick,
        xScale,
        yScale
      }
    : {
        xScale: yScale,
        y: (d) => d.timeTick,
        yScale: xScale
      };

  return (
    <BarStackComponent
      color={colorScale}
      data={[timeSeries[barIndex]]}
      keys={lineKeys}
      {...commonProps}
    >
      {(barStacks) => {
        return barStacks.map((barStack) =>
          barStack.bars.map((bar) => {
            return (
              <rect
                fill={bar.color}
                height={isHorizontal ? bar.height : barWidth}
                key={`bar-stack-${barStack.index}-${bar.index}`}
                width={isHorizontal ? barWidth : bar.width}
                x={isHorizontal ? barPadding : bar.x}
                y={isHorizontal ? bar.y : barPadding}
              />
            );
          })
        );
      }}
    </BarStackComponent>
  );
};

export default BarStack;
