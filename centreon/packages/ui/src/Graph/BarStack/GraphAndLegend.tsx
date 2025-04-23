import { equals, props } from 'ramda';
import { memo, useMemo } from 'react';
import { useGraphAndLegendStyles } from './BarStack.styles';
import Graph from './Graph';
import { gap, legendMaxHeight, legendMaxWidth } from './constants';
import { BarStackProps } from './models';

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
  isVerticalBar: boolean;
  legend: JSX.Element;
  displayLegend: boolean;
  height: number;
  width: number;
  colorScale;
  total: number;
}

const GraphAndLegend = ({
  isVerticalBar,
  legend,
  displayLegend,
  height,
  width,
  colorScale,
  total,
  unit,
  data,
  displayValues,
  onSingleBarClick,
  tooltipProps,
  TooltipContent
}: Props): JSX.Element => {
  const { classes } = useGraphAndLegendStyles();

  const isSmall = useMemo(
    () =>
      (!isVerticalBar && Math.floor(height) <= 80) ||
      (isVerticalBar && Math.floor(width) <= 150),
    [isVerticalBar, height, width]
  );

  const mustDisplayLegend = useMemo(
    () => displayLegend && !isSmall,
    [isSmall, displayLegend]
  );

  const graphWidth = useMemo(
    () =>
      isVerticalBar ? width - (isSmall ? 0 : legendMaxWidth - gap) : width,
    [width, isVerticalBar, isSmall]
  );

  const graphHeight = useMemo(
    () =>
      isVerticalBar ? height : height - (isSmall ? 0 : legendMaxHeight - gap),
    [height, isVerticalBar, isSmall]
  );

  return (
    <div
      className={classes.graphAndLegend}
      data-display-legend={mustDisplayLegend}
      data-is-vertical={isVerticalBar}
      style={{ height }}
    >
      <Graph
        isVerticalBar={isVerticalBar}
        data={data}
        width={graphWidth}
        height={graphHeight}
        colorScale={colorScale}
        unit={unit}
        total={total}
        displayValues={displayValues}
        onSingleBarClick={onSingleBarClick}
        tooltipProps={tooltipProps}
        TooltipContent={TooltipContent}
      />
      {mustDisplayLegend && (
        <div
          className={classes.legend}
          data-is-vertical={isVerticalBar}
          data-testid="Legend"
        >
          {legend}
        </div>
      )}
    </div>
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
  'tooltipProps',
  'displayLegend',
  'legend'
];

export default memo(GraphAndLegend, (prevProps, nextProps) =>
  equals(props(propsToMemoize, prevProps), props(propsToMemoize, nextProps))
);
