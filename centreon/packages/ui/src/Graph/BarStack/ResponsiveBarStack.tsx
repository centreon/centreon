import { Legend as LegendComponent } from '../Legend';
import { LegendProps } from '../Legend/models';
import { useStyles } from './BarStack.styles';

import { Typography } from '@mui/material';
import numeral from 'numeral';
import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import GraphAndLegend from './GraphAndLegend';
import { gap, smallTitleHeight, titleHeight } from './constants';
import { BarStackProps } from './models';
import useResponsiveBarStack from './useResponsiveBarStack';

const DefaultLengd = ({ scale, direction }: LegendProps): JSX.Element => (
  <LegendComponent direction={direction} scale={scale} />
);

const ResponsiveBarStack = ({
  title,
  data,
  height,
  width,
  onSingleBarClick,
  displayLegend = true,
  TooltipContent,
  Legend = DefaultLengd,
  unit = 'number',
  displayValues,
  variant = 'vertical',
  legendDirection,
  tooltipProps = {}
}: BarStackProps & { height: number; width: number }): JSX.Element => {
  const { t } = useTranslation();
  const { classes, cx } = useStyles();

  const {
    total,
    titleVariant,
    legendScale,
    isVerticalBar,
    colorScale,
    formattedLegendDirection
  } = useResponsiveBarStack({
    data,
    height,
    width,
    unit,
    variant,
    legendDirection
  });

  const graphAndLegendHeight = useMemo(() => {
    if (equals(titleVariant, 'xs')) {
      return height - 2 * smallTitleHeight - gap;
    }

    if (equals(titleVariant, 'sm')) {
      return height - smallTitleHeight - gap;
    }

    return height - titleHeight - gap;
  }, [titleVariant, height]);

  return (
    <div
      className={classes.container}
      data-has-title={!!title}
      data-title-variant={titleVariant}
      data-variant={variant}
    >
      {title && (
        <Typography
          data-testid="Title"
          variant={equals(titleVariant, 'md') ? 'h6' : 'body1'}
          textAlign="center"
          fontWeight="bold"
          className={cx(equals(titleVariant, 'md') && classes.clippedTitle)}
        >
          {`${numeral(total).format('0a')}`} {t(title)}
        </Typography>
      )}
      <GraphAndLegend
        height={graphAndLegendHeight}
        width={width}
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
    </div>
  );
};

export default ResponsiveBarStack;
