import { memo } from 'react';

import { ScaleType, scaleBand } from '@visx/scale';
import { BarRounded } from '@visx/shape';
import { dec, equals, gt, pick } from 'ramda';

import { BarGroupBar, SeriesPoint, StackKey } from '@visx/shape/lib/types';
import { BarStyle } from './models';
import { UseBarStackProps, useBarStack } from './useBarStack';

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
}

const getPadding = ({ padding, size, isNegativeValue }): number => {
  if (!isNegativeValue) {
    return padding;
  }

  return padding + size;
};

interface GetFirstBarHeightProps {
  bar: Omit<BarGroupBar<StackKey>, 'key' | 'value'> & {
    bar: SeriesPoint<unknown>;
    key: StackKey;
  };
  isHorizontal: boolean;
  barWidth: number;
  y: number;
  isFirstBar: boolean;
  yScale: ScaleType;
  neutralValue: number;
}

const getFirstBarHeight = ({
  bar,
  isHorizontal,
  barWidth,
  y,
  isFirstBar,
  yScale,
  neutralValue
}: GetFirstBarHeightProps): number => {
  if (!isFirstBar || !isHorizontal) {
    return isHorizontal ? Math.abs(bar.height) : barWidth;
  }

  if (equals(bar.height, 0)) {
    return 0;
  }

  if (isHorizontal && bar.height < 0) {
    return bar.height;
  }

  if (isHorizontal) {
    return Math.abs(bar.width) - (y - yScale(neutralValue));
  }

  return barWidth;
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
  neutralValue
}: Props): JSX.Element => {
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

            const barRoundedProps = {
              [isHorizontal ? 'bottom' : 'left']: shouldApplyRadiusOnBottom,
              [isHorizontal ? 'top' : 'right']: shouldApplyRadiusOnTop
            };

            return (
              <BarRounded
                {...barRoundedProps}
                data-testid={`stacked-bar-${bar.key}-${bar.index}-${bar.bar[1]}`}
                fill={bar.color}
                height={getFirstBarHeight({
                  bar,
                  barWidth,
                  y: isHorizontal
                    ? getPadding({
                        isNegativeValue,
                        padding: bar.y,
                        size: bar.height
                      })
                    : barPadding,
                  isFirstBar: shouldApplyRadiusOnBottom,
                  isHorizontal,
                  yScale,
                  neutralValue
                })}
                key={`bar-stack-${barStack.index}-${bar.index}`}
                opacity={barStyle.opacity ?? 1}
                radius={barWidth * barStyle.radius}
                width={isHorizontal ? barWidth : Math.abs(bar.width)}
                x={
                  isHorizontal
                    ? barPadding
                    : getPadding({
                        isNegativeValue,
                        padding: bar.x,
                        size: bar.width
                      })
                }
                y={isHorizontal ? bar.y : barPadding}
                onMouseEnter={
                  isTooltipHidden
                    ? undefined
                    : hoverBar({
                        barIndex,
                        highlightedMetric: Number(bar.key)
                      })
                }
                onMouseLeave={isTooltipHidden ? undefined : exitBar}
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
