import { Pie } from '@visx/shape';
import { Group } from '@visx/group';
import { Text } from '@visx/text';
import { useTooltip, useTooltipInPortal, defaultStyles } from '@visx/tooltip';
import numeral from 'numeral';
import { localPoint } from '@visx/event';

import { useTheme } from '@mui/material';

import { Legend as LegendComponent } from '../Legend';
import { LegendProps } from '../Legend/models';
import { getValueByUnit } from '../common/utils';

import { PieProps } from './models';
import { usePieStyles } from './PieChart.styles';

const DefaultLengd = ({ scale, configuration }: LegendProps): JSX.Element => (
  <LegendComponent configuration={configuration} scale={scale} />
);

const ResponsivePie = ({
  title,
  variant = 'Pie',
  width,
  data,
  unit = 'Number',
  Legend = DefaultLengd,
  legendConfiguration = { direction: 'column' },
  displayLegend = true,
  innerRadius = 40,
  onArcClick,
  displayValues,
  Tooltip
}: PieProps & { width: number }): JSX.Element => {
  const theme = useTheme();
  const { classes } = usePieStyles();

  const {
    tooltipOpen,
    tooltipLeft,
    tooltipTop,
    tooltipData,
    hideTooltip,
    showTooltip
  } = useTooltip();

  const tooltipStyles = {
    ...defaultStyles,
    backgroundColor: theme.palette.background.tooltip,
    minWidth: 60
  };

  const { containerRef, TooltipInPortal } = useTooltipInPortal({
    scroll: true
  });

  const half = width / 2;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const legendScale = {
    domain: data.map(({ value }) => getValueByUnit({ total, unit, value })),
    range: data.map(({ color }) => color)
  };

  return (
    <div className={classes.container}>
      <div className={classes.svgWrapper}>
        {variant === 'Pie' && title && (
          <div className={classes.pieTitle}>
            {`${numeral(total).format('0a').toUpperCase()} `} {title}
          </div>
        )}
        <div
          className={classes.svgContainer}
          style={{ height: width + 30, width: width + 30 }}
        >
          <svg height={width} ref={containerRef} width={width}>
            <Group left={half} top={half}>
              <Pie
                data={data}
                innerRadius={() => {
                  return variant === 'Pie' ? 0 : half - innerRadius;
                }}
                outerRadius={half}
                padAngle={0.01}
                pieValue={(items) => items.value}
              >
                {(pie) => {
                  return pie.arcs.map((arc) => {
                    const [centroidX, centroidY] = pie.path.centroid(arc);

                    return (
                      <g
                        key={arc.data.label}
                        onClick={() => {
                          onArcClick?.(arc.data);
                        }}
                        onMouseLeave={() => {
                          setTimeout(() => {
                            hideTooltip();
                          }, 300);
                        }}
                        onMouseMove={(event) => {
                          const eventSvgCoords = localPoint(event);
                          showTooltip({
                            tooltipData: arc.data,
                            tooltipLeft: eventSvgCoords?.x,
                            tooltipTop: eventSvgCoords?.y
                          });
                        }}
                      >
                        <path d={pie.path(arc)} fill={arc.data.color} />
                        {displayValues && (
                          <Text
                            dy=".33em"
                            fill="#000"
                            fontSize={12}
                            pointerEvents="none"
                            textAnchor="middle"
                            x={centroidX}
                            y={centroidY}
                          >
                            {getValueByUnit({
                              total,
                              unit,
                              value: arc.data.value
                            })}
                          </Text>
                        )}
                      </g>
                    );
                  });
                }}
              </Pie>
              {variant === 'Donut' && title && (
                <>
                  <Text
                    dy={-15}
                    fontSize={20}
                    fontWeight={700}
                    textAnchor="middle"
                  >
                    {numeral(total).format('0a').toUpperCase()}
                  </Text>
                  <Text
                    dy={20}
                    fontSize={20}
                    fontWeight={700}
                    textAnchor="middle"
                  >
                    {title}
                  </Text>
                </>
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

export default ResponsivePie;
