import { useRef } from 'react';

import { Group } from '@visx/group';
import { Pie } from '@visx/shape';
import { Text } from '@visx/text';
import numeral from 'numeral';
import { always, equals, gt, ifElse, lt } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useTheme } from '@mui/material';

import { Tooltip } from '../../components';
import { Legend as LegendComponent } from '../Legend';
import { LegendProps } from '../Legend/models';
import { getValueByUnit } from '../common/utils';

import { usePieStyles } from './PieChart.styles';
import { PieProps } from './models';
import { useResponsivePie } from './useResponsivePie';

const DefaultLegend = ({ scale, direction }: LegendProps): JSX.Element => (
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
  titlePosition,
  displayTitle = true,
  variant = 'pie',
  width,
  height,
  data,
  unit = 'number',
  Legend = DefaultLegend,
  displayLegend = true,
  displayTotal = true,
  innerRadius: defaultInnerRadius = 40,
  innerRadiusNoLimit = false,
  onArcClick,
  padAngle = 0,
  displayValues,
  TooltipContent,
  legendDirection = 'column',
  tooltipProps = {},
  opacity = 1
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
    total,
    innerRadius,
    isContainsExactlyOneNonZeroValue
  } = useResponsivePie({
    data,
    defaultInnerRadius,
    height,
    innerRadiusNoLimit,
    legendRef,
    titleRef,
    unit,
    width
  });

  const isTooSmallForLegend = lt(width, 170);

  const isSmall = lt(width, 130);
  const mustDisplayLegend = isTooSmallForLegend ? false : displayLegend;

  const { classes } = usePieStyles({
    reverse: equals(titlePosition, 'bottom'),
    svgSize
  });

  return (
    <div
      className={classes.container}
      style={{
        height
      }}
    >
      <div
        className={classes.svgWrapper}
        style={{
          minHeight: equals(variant, 'donut') && isSmall ? 'auto' : height
        }}
      >
        {(equals(variant, 'pie') ||
          isSmall ||
          (equals(variant, 'donut') && equals(titlePosition, 'bottom'))) &&
          title &&
          displayTitle && (
            <div className={classes.title} data-testid="Title" ref={titleRef}>
              {`${displayTotal ? numeral(total).format('0a').toUpperCase() : ''} `}
              {t(title)}
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
          <svg
            data-variant={variant}
            height={Math.ceil(svgSize)}
            width={Math.ceil(svgSize)}
          >
            <Group left={half} top={half}>
              <Pie
                cornerRadius={4}
                data={data}
                innerRadius={() => {
                  const iRadius = innerRadiusNoLimit
                    ? innerRadius
                    : half - innerRadius;

                  return equals(variant, 'pie') ? 0 : iRadius;
                }}
                outerRadius={half}
                padAngle={padAngle}
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
                        <g
                          data-testid={arc.data.label}
                          onClick={onClick}
                          onKeyUp={() => undefined}
                        >
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
                                fillOpacity={opacity}
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
              {equals(variant, 'donut') &&
                !isSmall &&
                title &&
                displayTitle &&
                !equals(titlePosition, 'bottom') && (
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
      {mustDisplayLegend && (
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
