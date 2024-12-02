import { useRef } from 'react';

import { Pie } from '@visx/shape';
import { Group } from '@visx/group';
import { Text } from '@visx/text';
import numeral from 'numeral';
import { always, equals, gt, ifElse, lt } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useTheme } from '@mui/material';

import { Tooltip } from '../../components';
import { Legend as LegendComponent } from '../Legend';
import { LegendProps } from '../Legend/models';
import { getValueByUnit } from '../common/utils';

import { PieProps } from './models';
import { usePieStyles } from './PieChart.styles';
import { useResponsivePie } from './useResponsivePie';

const DefaultLengd = ({ scale, direction }: LegendProps): JSX.Element => (
  <LegendComponent direction={direction} scale={scale} />
);

type Placement = 'left' | 'right' | 'top' | 'bottom';

const getTooltipPlacement = ({ radianX, radianY }): Placement => {
  if (gt(Math.abs(radianX), Math.abs(radianY))) {
    return ifElse<[b: number], Placement, Placement>(
      lt(0),
      always<Placement>('right'),
      always<Placement>('left')
    )(radianX);
  }

  return ifElse<[b: number], Placement, Placement>(
    lt(0),
    always<Placement>('bottom'),
    always<Placement>('top')
  )(radianY);
};

const ResponsivePie = ({
  title,
  variant = 'pie',
  width,
  height,
  data,
  unit = 'number',
  Legend = DefaultLengd,
  displayLegend = true,
  innerRadius: defaultInnerRadius = 40,
  onArcClick,
  displayValues,
  TooltipContent,
  legendDirection = 'column',
  tooltipProps = {}
}: PieProps & { height: number; width: number }): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  const legendRef = useRef(null);
  const titleRef = useRef(null);

  const {
    half,
    legendScale,
    svgContainerSize,
    svgSize,
    svgWrapperWidth,
    total,
    innerRadius,
    isContainsExactlyOneNonZeroValue
  } = useResponsivePie({
    data,
    defaultInnerRadius,
    height,
    legendRef,
    titleRef,
    unit,
    width
  });

  const { classes } = usePieStyles({ svgSize });

  return (
    <div
      className={classes.container}
      style={{
        height,
        width
      }}
    >
      <div
        className={classes.svgWrapper}
        style={{
          height,
          width: svgWrapperWidth
        }}
      >
        {equals(variant, 'pie') && title && (
          <div className={classes.title} data-testid="Title" ref={titleRef}>
            {`${numeral(total).format('0a').toUpperCase()} `} {t(title)}
          </div>
        )}
        <div
          className={classes.svgContainer}
          data-testid="pieChart"
          style={{
            height: svgContainerSize,
            width: svgContainerSize
          }}
        >
          <svg data-variant={variant} height={svgSize} width={svgSize}>
            <Group left={half} top={half}>
              <Pie
                cornerRadius={4}
                data={data}
                innerRadius={() => {
                  return equals(variant, 'pie') ? 0 : half - innerRadius;
                }}
                outerRadius={half}
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

                    const x = equals(variant, 'donut') ? centroidX : labelX;
                    const y = equals(variant, 'donut') ? centroidY : labelY;

                    const onClick = (): void => {
                      onArcClick?.(arc.data);
                    };

                    return (
                      <Tooltip
                        hasCaret
                        classes={{
                          tooltip: classes.pieChartTooltip
                        }}
                        followCursor={false}
                        key={arc.data.label}
                        label={
                          TooltipContent && (
                            <TooltipContent
                              color={arc.data.color}
                              label={arc.data.label}
                              title={title}
                              total={total}
                              value={arc.data.value}
                              {...tooltipProps}
                            />
                          )
                        }
                        leaveDelay={200}
                        placement={getTooltipPlacement({
                          radianX: Math.cos(midAngle),
                          radianY: Math.sin(midAngle)
                        })}
                      >
                        <g data-testid={arc.data.label} onClick={onClick}>
                          <path
                            cursor="pointer"
                            d={pie.path(arc) as string}
                            fill={arc.data.color}
                          />
                          {displayValues &&
                            !isContainsExactlyOneNonZeroValue &&
                            angle > minAngle && (
                              <Text
                                data-testid="value"
                                dy=".33em"
                                fill="#000"
                                fontSize={12}
                                fontWeight={600}
                                pointerEvents="none"
                                textAnchor="middle"
                                x={x}
                                y={y}
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
                    className={classes.title}
                    dy={lt(svgSize, 150) ? -10 : -15}
                    fill={theme.palette.text.primary}
                    textAnchor="middle"
                  >
                    {numeral(total).format('0a').toUpperCase()}
                  </Text>
                  <Text
                    className={classes.title}
                    data-testid="Title"
                    dy={lt(svgSize, 150) ? 10 : 15}
                    fill={theme.palette.text.primary}
                    textAnchor="middle"
                  >
                    {t(title)}
                  </Text>
                </>
              )}
            </Group>
          </svg>
        </div>
      </div>
      {displayLegend && (
        <div data-testid="Legend" ref={legendRef}>
          <Legend
            data={data}
            direction={legendDirection}
            scale={legendScale}
            title={title}
            total={total}
            unit={unit}
          />
        </div>
      )}
    </div>
  );
};

export default ResponsivePie;
