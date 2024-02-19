import { Pie } from '@visx/shape';
import { Group } from '@visx/group';
import { Text } from '@visx/text';
import numeral from 'numeral';
import { equals } from 'ramda';

import { Tooltip } from '../../components';
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
  variant = 'pie',
  width,
  data,
  unit = 'number',
  Legend = DefaultLengd,
  legendConfiguration = { direction: 'column' },
  displayLegend = true,
  innerRadius = 40,
  onArcClick,
  displayValues,
  tooltipContent
}: PieProps & { width: number }): JSX.Element => {
  const { classes } = usePieStyles();

  const half = width / 2;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const legendScale = {
    domain: data.map(({ value }) => getValueByUnit({ total, unit, value })),
    range: data.map(({ color }) => color)
  };

  return (
    <div className={classes.container}>
      <div className={classes.svgWrapper}>
        {equals(variant, 'pie') && title && (
          <div className={classes.pieTitle}>
            {`${numeral(total).format('0a').toUpperCase()} `} {title}
          </div>
        )}
        <div
          className={classes.svgContainer}
          style={{ height: width + 30, width: width + 30 }}
        >
          <svg height={width} width={width}>
            <Group left={half} top={half}>
              <Pie
                data={data}
                innerRadius={() => {
                  return equals(variant, 'pie') ? 0 : half - innerRadius;
                }}
                outerRadius={half}
                padAngle={0.01}
                pieValue={(items) => items.value}
              >
                {(pie) => {
                  return pie.arcs.map((arc) => {
                    const [centroidX, centroidY] = pie.path.centroid(arc);
                    const midAngle = Math.atan2(centroidY, centroidX);

                    const labelRadius = half * 0.8;

                    const labelX = Math.cos(midAngle) * labelRadius;
                    const labelY = Math.sin(midAngle) * labelRadius;

                    const angle = arc.endAngle - arc.startAngle;

                    const minAngle = 0.2;

                    return (
                      <Tooltip
                        hasCaret
                        classes={{
                          tooltip: classes.pieChartTooltip
                        }}
                        followCursor={false}
                        key={arc.data.label}
                        label={tooltipContent?.({
                          color: arc.data.color,
                          label: arc.data.label,
                          title,
                          total,
                          value: arc.data.value
                        })}
                      >
                        <g
                          key={arc.data.label}
                          onClick={() => {
                            onArcClick?.(arc.data);
                          }}
                        >
                          <path d={pie.path(arc)} fill={arc.data.color} />
                          {displayValues && angle > minAngle && (
                            <Text
                              dy=".33em"
                              fill="#000"
                              fontSize={12}
                              pointerEvents="none"
                              textAnchor="middle"
                              x={equals(variant, 'donut') ? centroidX : labelX}
                              y={equals(variant, 'donut') ? centroidY : labelY}
                            >
                              {getValueByUnit({
                                total,
                                unit,
                                value: arc.data.value
                              })}
                            </Text>
                          )}
                        </g>
                      </Tooltip>
                    );
                  });
                }}
              </Pie>
              {equals(variant, 'donut') && title && (
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
    </div>
  );
};

export default ResponsivePie;
