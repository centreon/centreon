import { BarStack as BarStackVertical, BarStackHorizontal } from '@visx/shape';
import { Group } from '@visx/group';
import { useTooltip, useTooltipInPortal, defaultStyles } from '@visx/tooltip';
import { localPoint } from '@visx/event';
import numeral from 'numeral';
import { Text } from '@visx/text';
import { equals } from 'ramda';

import { useTheme } from '@mui/system';

import { LegendProps } from '../Legend/models';
import { Legend as LegendComponent } from '../Legend';
import { getValueByUnit } from '../common/utils';

import { BarStackProps } from './models';
import { useBarStackStyles } from './BarStack.styles';
import useResponsiveBarStack from './useResponsiveBarStack';

const DefaultLengd = ({ scale, configuration }: LegendProps): JSX.Element => (
  <LegendComponent configuration={configuration} scale={scale} />
);

const BarStack = ({
  title,
  data,
  width: barWidth,
  height: barHeight,
  onSingleBarClick,
  displayLegend = true,
  Tooltip,
  legendConfiguration = { direction: 'column' },
  Legend = DefaultLengd,
  unit = 'Number',
  displayValues,
  variant = 'Vertical'
}: BarStackProps & { height: number; width: number }): JSX.Element => {
  const theme = useTheme();
  const { classes } = useBarStackStyles();

  const tooltipStyles = {
    ...defaultStyles,
    backgroundColor: theme.palette.background.tooltip,
    minWidth: 60
  };

  const {
    tooltipOpen,
    tooltipLeft,
    tooltipTop,
    tooltipData,
    hideTooltip,
    showTooltip
  } = useTooltip();

  const { containerRef, TooltipInPortal } = useTooltipInPortal({
    scroll: true
  });

  const {
    height,
    width,
    colorScale,
    input,
    keys,
    legendScale,
    total,
    xScale,
    yScale
  } = useResponsiveBarStack({
    barHeight,
    barWidth,
    data,
    unit,
    variant
  });

  return (
    <div className={classes.container}>
      <div className={classes.svgWrapper}>
        {title && (
          <div className={classes.title}>
            {`${numeral(total).format('0a').toUpperCase()} `} {title}
          </div>
        )}
        <div
          className={classes.svgContainer}
          style={{
            height: height + 15,
            width: width + 15
          }}
        >
          <svg height={height} ref={containerRef} width={width}>
            <Group>
              {equals(variant, 'Vertical') ? (
                <BarStackVertical
                  color={colorScale}
                  data={[input]}
                  keys={keys}
                  x={() => undefined}
                  xScale={xScale}
                  yScale={yScale}
                >
                  {(barStacks) =>
                    barStacks.map((barStack) =>
                      barStack.bars.map((bar) => {
                        return (
                          <g key={`bar-stack-${barStack.index}-${bar.index}`}>
                            <rect
                              fill={bar.color}
                              height={bar.height - 1}
                              key={`bar-stack-${barStack.index}-${bar.index}`}
                              ry={5}
                              width={bar.width}
                              x={bar.x}
                              y={bar.y}
                              onClick={() => {
                                onSingleBarClick?.(bar);
                              }}
                              onMouseLeave={() => {
                                setTimeout(() => {
                                  hideTooltip();
                                }, 300);
                              }}
                              onMouseMove={(event) => {
                                const eventSvgCoords = localPoint(event);
                                const left = bar.x + bar.width;
                                showTooltip({
                                  tooltipData: {
                                    color: bar.color,
                                    label: bar.key,
                                    value:
                                      barStack.bars[0].bar.data[barStack.key]
                                  },
                                  tooltipLeft: left,
                                  tooltipTop: eventSvgCoords?.y
                                });
                              }}
                            />
                            {displayValues &&
                              bar.height > 10 &&
                              bar.width > 10 && (
                                <Text
                                  cursor="pointer"
                                  fill="#000"
                                  fontSize={12}
                                  textAnchor="middle"
                                  verticalAnchor="middle"
                                  x={bar.x + bar.width / 2}
                                  y={bar.y + bar.height / 2}
                                >
                                  {getValueByUnit({
                                    total,
                                    unit,
                                    value:
                                      barStack.bars[0].bar.data[barStack.key]
                                  })}
                                </Text>
                              )}
                          </g>
                        );
                      })
                    )
                  }
                </BarStackVertical>
              ) : (
                <BarStackHorizontal
                  color={colorScale}
                  data={[input]}
                  keys={keys}
                  xScale={xScale}
                  y={() => undefined}
                  yScale={yScale}
                >
                  {(barStacks) =>
                    barStacks.map((barStack) =>
                      barStack.bars.map((bar) => (
                        <g key={`bar-stack-${barStack.index}-${bar.index}`}>
                          <rect
                            fill={bar.color}
                            height={bar.height}
                            key={`barstack-horizontal-${barStack.index}-${bar.index}`}
                            ry={5}
                            width={bar.width - 1}
                            x={bar.x}
                            y={bar.y}
                            onClick={() => {
                              onSingleBarClick?.(bar);
                            }}
                            onMouseLeave={() => {
                              setTimeout(() => {
                                hideTooltip();
                              }, 300);
                            }}
                            onMouseMove={(event) => {
                              const eventSvgCoords = localPoint(event);
                              const top = bar.y + bar.height;
                              showTooltip({
                                tooltipData: {
                                  color: bar.color,
                                  label: bar.key,
                                  value: barStack.bars[0].bar.data[barStack.key]
                                },
                                tooltipLeft: eventSvgCoords?.x,
                                tooltipTop: top
                              });
                            }}
                          />
                          {displayValues &&
                            bar.height > 10 &&
                            bar.width > 10 && (
                              <Text
                                cursor="pointer"
                                fill="#000"
                                fontSize={12}
                                textAnchor="middle"
                                verticalAnchor="middle"
                                x={bar.x + bar.width / 2}
                                y={bar.y + bar.height / 2}
                              >
                                {getValueByUnit({
                                  total,
                                  unit,
                                  value: barStack.bars[0].bar.data[barStack.key]
                                })}
                              </Text>
                            )}
                        </g>
                      ))
                    )
                  }
                </BarStackHorizontal>
              )}
            </Group>
          </svg>
        </div>
      </div>
      {displayLegend &&
        Legend({
          configuration: legendConfiguration,
          scale: legendScale
        })}
      {Tooltip && tooltipOpen && tooltipData && (
        <TooltipInPortal
          left={tooltipLeft}
          style={tooltipStyles}
          top={tooltipTop}
        >
          {Tooltip(tooltipData)}
        </TooltipInPortal>
      )}
    </div>
  );
};

export default BarStack;
