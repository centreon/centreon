import { scaleBand } from '@visx/scale';
import { dec, equals, gt, pick } from 'ramda';
import { memo, type ReactElement } from 'react';

import Bar from './Bar';
import type { BarStyle } from './models';
import { type UseBarStackProps, useBarStack } from './useBarStack';

const xScale = scaleBand<number>({
  domain: [0, 0],
  padding: 0,
  range: [0, 0]
});

interface Props extends Omit<UseBarStackProps, 'xScale'> {
  barIndex: number;
  barPadding: number;
  barStyle: BarStyle;
  barWidth: number;
  isTooltipHidden: boolean;
  neutralValue: number;
  isStacked?: boolean;
}

const getPadding = ({ padding, size, isNegativeValue }): number => {
  if (!isNegativeValue) {
    return padding;
  }

  return padding + size;
};

const BarStack = ({
  timeSeries,
  isHorizontal,
  yScale,
  lines,
  barWidth,
  barPadding,
  barIndex,
  isTooltipHidden,
  barStyle = { opacity: 1, radius: 0.2 },
  neutralValue,
  isStacked
}: Props): ReactElement => {
  const {
    BarStackComponent,
    commonBarStackProps,
    colorScale,
    lineKeys,
    exitBar,
    hoverBar
  } = useBarStack({ isHorizontal, lines, timeSeries, xScale, yScale });

  return (
    <BarStackComponent
      color={colorScale}
      data={[timeSeries[barIndex]]}
      keys={lineKeys}
      {...commonBarStackProps}
    >
      {(barStacks) => {
        return barStacks.map((barStack, index) =>
          barStack.bars.map((bar) => {
            const shouldApplyRadiusOnBottom = equals(index, 0);
            const shouldApplyRadiusOnTop = equals(index, dec(barStacks.length));
            const isNegativeValue = gt(0, bar.bar[1]);
            const shouldRetrievePadding =
              isNegativeValue && isStacked && !shouldApplyRadiusOnBottom;

            const barRoundedProps = {
              [isHorizontal ? 'bottom' : 'left']: shouldApplyRadiusOnBottom,
              [isHorizontal ? 'top' : 'right']: shouldApplyRadiusOnTop
            };

            const barY = shouldRetrievePadding
              ? getPadding({
                  isNegativeValue,
                  padding: bar.y,
                  size: bar.height
                })
              : bar.y;

            return (
              <Bar
                bar={bar}
                barIndex={barIndex}
                barPadding={barPadding}
                barRoundedProps={barRoundedProps}
                barStyle={barStyle}
                barWidth={barWidth}
                barY={barY}
                exitBar={exitBar}
                hoverBar={hoverBar}
                isHorizontal={isHorizontal}
                isNegativeValue={isNegativeValue}
                isTooltipHidden={isTooltipHidden}
                key={`bar-stack-${barStack.index}-${bar.index}`}
                neutralValue={neutralValue}
                shouldApplyRadiusOnBottom={shouldApplyRadiusOnBottom}
                yScale={yScale}
              />
            );
          })
        );
      }}
    </BarStackComponent>
  );
};

const propsToMemoize = [
  'timeSeries',
  'isHorizontal',
  'barWidth',
  'lines',
  'barPadding',
  'barIndex',
  'isTooltipHidden',
  'barStyle',
  'neutralValue'
];

export default memo(BarStack, (prevProps, nextProps) => {
  const prevYScaleDomain = prevProps.yScale.domain();
  const prevYScaleRange = prevProps.yScale.range();
  const nextYScaleDomain = nextProps.yScale.domain();
  const nextYScaleRange = nextProps.yScale.range();

  return (
    equals(
      [...prevYScaleDomain, ...prevYScaleRange],
      [...nextYScaleDomain, ...nextYScaleRange]
    ) &&
    equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps))
  );
});
