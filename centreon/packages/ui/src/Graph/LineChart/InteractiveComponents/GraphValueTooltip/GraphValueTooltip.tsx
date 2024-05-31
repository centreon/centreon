import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';

import { Box, Typography } from '@mui/material';

import { mousePositionAtom } from '../interactionWithGraphAtoms';
import { formatMetricValueWithUnit } from '../../../common/timeSeries';

import { useGraphValueTooltip } from './useGraphValueTooltip';
import { useGraphValueTooltipStyles } from './useGraphValueTooltipStyles';

interface Props {
  base: number;
}

const GraphValueTooltip = ({ base }: Props): JSX.Element | null => {
  const { classes } = useGraphValueTooltipStyles();
  const mousePosition = useAtomValue(mousePositionAtom);

  const graphValue = useGraphValueTooltip();

  if (isNil(graphValue) || isNil(mousePosition)) {
    return null;
  }

  return (
    <div className={classes.tooltipContainer}>
      <Typography fontWeight="bold">{graphValue.dateTime}</Typography>
      <div className={classes.metrics}>
        {graphValue.metrics.map(({ unit, color, id, value, name }) => {
          const isMetricHighlighted = equals(
            id,
            graphValue.highlightedMetricId
          );

          return (
            <div
              className={classes.metric}
              data-highlight={isMetricHighlighted}
              data-metric={name}
              key={id}
            >
              <Box
                className={classes.metricColorBox}
                sx={{ backgroundColor: color }}
              />
              <Typography
                className={classes.metricName}
                fontWeight={isMetricHighlighted ? 'bold' : undefined}
              >
                {name}
              </Typography>
              <Typography fontWeight={isMetricHighlighted ? 'bold' : undefined}>
                {formatMetricValueWithUnit({
                  base,
                  unit,
                  value
                })}
              </Typography>
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default GraphValueTooltip;
