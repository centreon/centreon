import {
  BarRounded,
  BarStackHorizontal,
  BarStack as BarStackVertical
} from '@visx/shape';
import { Text } from '@visx/text';
import { equals, props } from 'ramda';
import { memo, useMemo } from 'react';

import { Tooltip } from '../../components';
import { getValueByUnit } from '../common/utils';
import { useGraphStyles } from './BarStack.styles';
import type { BarStackProps } from './models';
import { useGraphAndLegend } from './useGraphAndLegend';

interface Props
  extends Pick<
    BarStackProps,
    | 'data'
    | 'displayValues'
    | 'onSingleBarClick'
    | 'unit'
    | 'TooltipContent'
    | 'tooltipProps'
  > {
  width: number;
  height: number;
  isVerticalBar: boolean;
  colorScale;
  total: number;
}

const getFitsInBar = ({ isVerticalBar, bar, unit }): boolean => {
  if (isVerticalBar) {
    return bar.height >= 18;
  }

  return (
    (equals(unit, 'number') && bar.width > 15) ||
    (equals(unit, 'percentage') && bar.width > 35)
  );
};

const getClick = ({
  onSingleBarClick,
  bar
}): ((e: MouseEvent) => void) | undefined => {
  if (onSingleBarClick) {
    return (e: MouseEvent): void => {
      if (!equals(e.button, 0)) {
        return;
      }
      onSingleBarClick(bar);
    };
  }

  return undefined;
};

const Graph = ({
  width,
  height,
  isVerticalBar,
  colorScale,
  data,
  total,
  unit,
  displayValues,
  onSingleBarClick,
  tooltipProps,
  TooltipContent
}: Props): JSX.Element => {
  const { classes } = useGraphStyles();

  const BarStackComponent = useMemo(
    () => (isVerticalBar ? BarStackVertical : BarStackHorizontal),
    [isVerticalBar]
  );

  const normalizedHeight = useMemo(() => height - 10, [height]);

  const { barStackData, xScale, yScale, keys } = useGraphAndLegend({
    data,
    height: normalizedHeight,
    isVerticalBar,
    total,
    width
  });

  return (
    <svg height={normalizedHeight} width="100%">
      <BarStackComponent
        color={colorScale}
        data={[barStackData]}
        keys={keys}
        {...(isVerticalBar ? { x: () => undefined } : { y: () => undefined })}
        xScale={xScale}
        yScale={yScale}
      >
        {(barStacks) =>
          barStacks.map((barStack, index) =>
            barStack.bars.map((bar) => {
              const isFirstBar = equals(index, 0);
              const isLastBar = equals(index, barStacks.length - 1);
              const fitsInBar = getFitsInBar({ bar, isVerticalBar, unit });

              const textX = bar.x + bar.width / 2;
              const textY = bar.y + bar.height / 2;

              const click = getClick({ bar, onSingleBarClick });

              return (
                <Tooltip
                  classes={classes}
                  followCursor={false}
                  key={`bar-stack-${barStack.index}-${bar.index}`}
                  label={
                    TooltipContent && (
                      <TooltipContent
                        color={bar.color}
                        label={bar.key}
                        total={total}
                        value={barStack.bars[0].bar.data[barStack.key]}
                        {...tooltipProps}
                      />
                    )
                  }
                  position={isVerticalBar ? 'right' : 'bottom'}
                >
                  <g data-testid={bar.key} key={bar.key}>
                    <BarRounded
                      bottom={isVerticalBar && isFirstBar}
                      cursor={onSingleBarClick ? 'pointer' : 'default'}
                      fill={bar.color}
                      height={bar.height}
                      key={`bar-stack-${barStack.index}-${bar.index}`}
                      left={!isVerticalBar && isFirstBar}
                      onMouseDown={click}
                      radius={8}
                      right={!isVerticalBar && isLastBar}
                      top={isVerticalBar && isLastBar}
                      width={isVerticalBar ? bar.width - 10 : bar.width}
                      x={bar.x}
                      y={bar.y}
                    />
                    {displayValues && fitsInBar && (
                      <Text
                        cursor={onSingleBarClick ? 'pointer' : 'default'}
                        data-testid="value"
                        fill="#000"
                        fontSize={12}
                        fontWeight={600}
                        onMouseUp={click}
                        textAnchor="middle"
                        verticalAnchor="middle"
                        x={textX}
                        y={textY}
                      >
                        {getValueByUnit({
                          total,
                          unit: unit || 'number',
                          value: barStack.bars[0].bar.data[barStack.key]
                        })}
                      </Text>
                    )}
                  </g>
                </Tooltip>
              );
            })
          )
        }
      </BarStackComponent>
    </svg>
  );
};

const propsToMemoize = [
  'width',
  'height',
  'isVerticalBar',
  'colorScale',
  'data',
  'total',
  'unit',
  'displayValues',
  'tooltipProps'
];

export default memo(Graph, (prevProps, nextProps) =>
  equals(props(propsToMemoize, prevProps), props(propsToMemoize, nextProps))
);
