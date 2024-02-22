import { useRef } from 'react';

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

const DefaultLengd = ({ scale }: LegendProps): JSX.Element => (
  <LegendComponent scale={scale} />
);

const BarStack = ({
  title,
  data,
  width,
  height,
  size = 72,
  onSingleBarClick,
  displayLegend = true,
  tooltipContent,
  Legend = DefaultLengd,
  unit = 'number',
  displayValues,
  variant = 'vertical'
}: BarStackProps & { height: number; width: number }): JSX.Element => {
  const { classes } = useBarStackStyles();

  const titleRef = useRef(null);
  const legendRef = useRef(null);

  const {
    barSize,
    colorScale,
    input,
    keys,
    legendScale,
    total,
    xScale,
    yScale,
    svgWrapperWidth,
    svgContainerSize
  } = useResponsiveBarStack({
    data,
    height,
    legendRef,
    size,
    titleRef,
    unit,
    variant,
    width
  });

  return (
    <div className={classes.container} style={{ height, width }}>
      <div
        className={classes.svgWrapper}
        style={{
          height,
          width: svgWrapperWidth
        }}
      >
        {title && (
          <div className={classes.title} ref={titleRef}>
            {`${numeral(total).format('0a').toUpperCase()} `} {title}
          </div>
        )}
        <div className={classes.svgContainer} style={svgContainerSize}>
          <svg height={barSize.height} width={barSize.width}>
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
      <div ref={legendRef}>
        {displayLegend &&
          Legend({
            data,
            scale: legendScale,
            title,
            total
          })}
      </div>
    </div>
  );
};

export default BarStack;
