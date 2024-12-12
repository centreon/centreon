import { Legend as LegendComponent } from '../Legend';
import { LegendProps } from '../Legend/models';
import { useStyles } from './BarStack.styles';

import { Typography } from '@mui/material';
import numeral from 'numeral';
import { useTranslation } from 'react-i18next';
import { ParentSize } from '../..';
import GraphAndLegend from './GraphAndLegend';
import { BarStackProps } from './models';
import useResponsiveBarStack from './useResponsiveBarStack';

const DefaultLengd = ({ scale, direction }: LegendProps): JSX.Element => (
  <LegendComponent direction={direction} scale={scale} />
);

const ResponsiveBarStack = ({
  title,
  data,
  height,
  onSingleBarClick,
  displayLegend = true,
  TooltipContent,
  Legend = DefaultLengd,
  unit = 'number',
  displayValues,
  variant = 'vertical',
  legendDirection,
  tooltipProps = {}
}: BarStackProps & { height: number }): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const {
    total,
    isSmall,
    legendScale,
    isVerticalBar,
    colorScale,
    formattedLegendDirection
  } = useResponsiveBarStack({
    data,
    height,
    unit,
    variant,
    legendDirection
  });

  return (
    <div
      className={classes.container}
      data-has-title={!!title}
      data-is-small={isSmall}
    >
      {title && (
        <Typography
          data-testid="Title"
          variant={isSmall ? 'body1' : 'h6'}
          textAlign="center"
          fontWeight="bold"
        >
          {`${numeral(total).format('0a').toUpperCase()} `} {t(title)}
        </Typography>
      )}
      <ParentSize>
        {({ height: graphAndLegendHeight, width: graphAndLegendWidth }) => (
          <GraphAndLegend
            height={graphAndLegendHeight}
            width={graphAndLegendWidth}
            isVerticalBar={isVerticalBar}
            displayLegend={displayLegend}
            colorScale={colorScale}
            total={total}
            data={data}
            unit={unit}
            displayValues={displayValues}
            onSingleBarClick={onSingleBarClick}
            tooltipProps={tooltipProps}
            TooltipContent={TooltipContent}
            legend={
              <Legend
                data={data}
                direction={formattedLegendDirection}
                scale={legendScale}
                title={title}
                total={total}
                unit={unit}
              />
            }
          />
        )}
      </ParentSize>
    </div>
  );
};

export default ResponsiveBarStack;
