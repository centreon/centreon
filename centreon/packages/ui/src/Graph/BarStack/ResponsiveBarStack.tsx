import { BarStack } from '@visx/shape';
import { Group } from '@visx/group';
import { scaleBand, scaleLinear, scaleOrdinal } from '@visx/scale';
import { useTooltip, useTooltipInPortal, defaultStyles } from '@visx/tooltip';
import { localPoint } from '@visx/event';
import numeral from 'numeral';
import { Text } from '@visx/text';

import { useTheme } from '@mui/system';

import { LegendProps } from '../Legend/models';
import { Legend as LegendComponent } from '../Legend';

import { BarStackProps } from './models';
import { useBarStackStyles } from './BarStack.styles';

type TooltipData = {
  bar;
  color: string;
  height: number;
  index: number;
  key: string;
  width: number;
  x: number;
  y: number;
};

const formatValue = (unit, value, total): string => {
  if (unit === 'Number') {
    return numeral(value).format('0a').toUpperCase();
  }

  return `${((value * 100) / total).toFixed(1)}%`;
};

let tooltipTimeout: number;

const DefaultLengd = ({ scale, configuration }: LegendProps): JSX.Element => (
  <LegendComponent configuration={configuration} scale={scale} />
);

const BarVertical = ({
  title,
  data,
  width,
  height,
  onSingleBarClick,
  displayLegend = true,
  Tooltip,
  legendConfiguration = { direction: 'row' },
  Legend = DefaultLengd,
  unit = 'Number',
  displayValues
}: BarStackProps & { height: number; width: number }): JSX.Element => {
  const theme = useTheme();
  const { classes } = useBarStackStyles({
    legendDirection: legendConfiguration.direction
  });

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
  } = useTooltip<TooltipData>();

  const { containerRef, TooltipInPortal } = useTooltipInPortal({
    scroll: true
  });

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const yScale = scaleLinear({
    domain: [0, total],
    nice: true
  });
  const xScale = scaleBand({
    domain: [0, 0],
    padding: 0
  });

  const keys = data.map(({ label }) => label);

  const colorsRange = data.map(({ color }) => color);

  const colorScale = scaleOrdinal({
    domain: keys,
    range: colorsRange
  });

  const legendScale = {
    domain: data.map(({ value }) => formatValue(unit, value, total)),
    range: colorsRange
  };

  const xMax = width;
  const yMax = height;

  xScale.rangeRound([0, xMax]);
  yScale.range([yMax, 0]);

  const input = data.reduce((acc, { label, value }) => {
    acc[label] = value;

    return acc;
  }, {});

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
          style={{ height: height + 15, width: width + 15 }}
        >
          <svg height={height} ref={containerRef} width={width}>
            <Group>
              <BarStack
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
                              tooltipTimeout = window.setTimeout(() => {
                                hideTooltip();
                              }, 300);
                            }}
                            onMouseMove={(event) => {
                              if (tooltipTimeout) clearTimeout(tooltipTimeout);
                              const eventSvgCoords = localPoint(event);
                              const left = bar.x + bar.width / 2;
                              showTooltip({
                                tooltipData: {
                                  color: bar.color,
                                  label: bar.key,
                                  value: barStack.bars[0].bar.data[barStack.key]
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
                                fill="black"
                                fontSize={12}
                                textAnchor="middle"
                                verticalAnchor="middle"
                                x={bar.x + bar.width / 2}
                                y={bar.y + bar.height / 2}
                              >
                                {numeral(
                                  barStack.bars[0].bar.data[barStack.key]
                                )
                                  .format('0a')
                                  .toUpperCase()}
                              </Text>
                            )}
                        </g>
                      );
                    })
                  )
                }
              </BarStack>
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

export default BarVertical;
