import { ScaleType } from '@visx/scale';
import { BarRounded } from '@visx/shape';
import { BarGroupBar, SeriesPoint, StackKey } from '@visx/shape/lib/types';
import { equals } from 'ramda';
import { ReactElement } from 'react';

import { getStyle } from '../common/utils';
import { BarStyle } from './models';

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

const getPadding = ({ padding, size, isNegativeValue }): number => {
  if (!isNegativeValue) {
    return padding;
  }

  return padding + size;
};

export const Bar = ({
  barRoundedProps,
  bar,
  isTooltipHidden,
  isHorizontal,
  shouldApplyRadiusOnBottom,
  barPadding,
  barWidth,
  neutralValue,
  isNegativeValue,
  barIndex,
  exitBar,
  hoverBar,
  barY,
  barStyle,
  yScale
}): ReactElement => {
  const style = getStyle({
    metricId: Number(bar.key),
    style: barStyle
  }) as BarStyle;
  return (
    <BarRounded
      {...barRoundedProps}
      data-testid={`stacked-bar-${bar.key}-${bar.index}-${bar.bar[1]}`}
      fill={bar.color}
      height={getFirstBarHeight({
        bar,
        barWidth,
        isFirstBar: shouldApplyRadiusOnBottom,
        isHorizontal,
        neutralValue,
        y: isHorizontal
          ? getPadding({
              isNegativeValue,
              padding: bar.y,
              size: bar.height
            })
          : barPadding,
        yScale
      })}
      onMouseEnter={
        isTooltipHidden
          ? undefined
          : hoverBar({
              barIndex,
              highlightedMetric: Number(bar.key)
            })
      }
      onMouseLeave={isTooltipHidden ? undefined : exitBar}
      opacity={style?.opacity || 1}
      radius={style?.radius ? barWidth * style.radius : 0}
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
      y={isHorizontal ? barY : barPadding}
    />
  );
};

export default Bar;
