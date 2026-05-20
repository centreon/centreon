import { BarRounded } from '@visx/shape';
import { BarGroupBar, SeriesPoint, StackKey } from '@visx/shape/lib/types';
import type { ScaleBand, ScaleLinear } from 'd3-scale';
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
  yScale: ScaleLinear<number, number> | ScaleBand<number>;
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
    return Math.abs(bar.width) - (y - (yScale(neutralValue) ?? 0));
  }

  return barWidth;
};

interface GetPaddingProps {
  padding: number;
  size: number;
  isNegativeValue: boolean;
}

const getPadding = ({
  padding,
  size,
  isNegativeValue
}: GetPaddingProps): number => {
  if (!isNegativeValue) {
    return padding;
  }

  return padding + size;
};

interface BarProps {
  barRoundedProps: Record<string, boolean>;
  bar: Omit<BarGroupBar<StackKey>, 'key' | 'value'> & {
    bar: SeriesPoint<unknown>;
    key: StackKey;
  };
  isTooltipHidden: boolean;
  isHorizontal: boolean;
  shouldApplyRadiusOnBottom: boolean;
  barPadding: number;
  barWidth: number;
  neutralValue: number;
  isNegativeValue: boolean;
  barIndex: number;
  exitBar: () => void;
  hoverBar: (props: {
    barIndex: number;
    highlightedMetric: number;
  }) => () => void;
  barY: number;
  barStyle: BarStyle;
  yScale: ScaleLinear<number, number> | ScaleBand<number>;
}

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
}: BarProps): ReactElement => {
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
