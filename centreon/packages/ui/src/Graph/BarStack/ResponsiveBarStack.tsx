import { BarStack as BarStackVertical, BarStackHorizontal } from '@visx/shape';
import { Group } from '@visx/group';
import numeral from 'numeral';
import { Text } from '@visx/text';
import { equals } from 'ramda';

import { Tooltip } from '../../components';
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
  tooltipContent,
  legendConfiguration = { direction: 'column' },
  Legend = DefaultLengd,
  unit = 'number',
  displayValues,
  variant = 'vertical'
}: BarStackProps & { height: number; width: number }): JSX.Element => {
  const { classes } = useBarStackStyles();

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
            height: height + 16,
            width: width + 16
          }}
        >
          <svg height={height} width={width}>
            <Group>
              {equals(variant, 'vertical') ? (
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
                          <Tooltip
                            hasCaret
                            classes={{
                              tooltip: classes.barStackTooltip
                            }}
                            followCursor={false}
                            key={`bar-stack-${barStack.index}-${bar.index}`}
                            label={tooltipContent?.({
                              color: bar.color,
                              label: bar.key,
                              title,
                              total,
                              value: barStack.bars[0].bar.data[barStack.key]
                            })}
                            position="right-start"
                          >
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
                          </Tooltip>
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
                        <Tooltip
                          hasCaret
                          classes={{
                            tooltip: classes.barStackTooltip
                          }}
                          followCursor={false}
                          key={`bar-stack-${barStack.index}-${bar.index}`}
                          label={tooltipContent?.({
                            color: bar.color,
                            label: bar.key,
                            title,
                            total,
                            value: barStack.bars[0].bar.data[barStack.key]
                          })}
                          position="bottom-start"
                        >
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
                        </Tooltip>
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
    </div>
  );
};

export default BarStack;
