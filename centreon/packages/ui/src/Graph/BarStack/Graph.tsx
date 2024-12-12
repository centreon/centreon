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
import { BarStackProps } from './models';
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

  const normalizedHeight = useMemo(
    () => (isVerticalBar ? height - 10 : height / 1.2),
    [height, isVerticalBar]
  );

  const { barStackData, xScale, yScale, keys } = useGraphAndLegend({
    data,
    width,
    height: normalizedHeight,
    isVerticalBar,
    total
  });

  return (
    <svg width="100%" height={normalizedHeight}>
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
              const fitsInBar =
                bar.height >= 10 &&
                ((equals(unit, 'number') && bar.width > 10) ||
                  (equals(unit, 'percentage') && bar.width > 25));

              const textX = bar.x + bar.width / 2;
              const textY = bar.y + bar.height / 2;

              const click = onSingleBarClick
                ? (): void => onSingleBarClick(bar)
                : undefined;

              return (
                <Tooltip
                  followCursor={false}
                  classes={classes}
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
                      radius={8}
                      cursor={onSingleBarClick ? 'default' : 'cursor'}
                      fill={bar.color}
                      height={isVerticalBar ? bar.height - 1 : bar.height}
                      key={`bar-stack-${barStack.index}-${bar.index}`}
                      width={isVerticalBar ? bar.width : bar.width - 1}
                      x={bar.x}
                      y={bar.y}
                      left={!isVerticalBar && isFirstBar}
                      right={!isVerticalBar && isLastBar}
                      bottom={isVerticalBar && isFirstBar}
                      top={isVerticalBar && isLastBar}
                      onMouseDown={click}
                    />
                    {displayValues && fitsInBar && (
                      <Text
                        cursor="pointer"
                        data-testid="value"
                        fill="#000"
                        fontSize={12}
                        fontWeight={600}
                        textAnchor="middle"
                        verticalAnchor="middle"
                        x={textX}
                        y={textY}
                        onMouseUp={click}
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
