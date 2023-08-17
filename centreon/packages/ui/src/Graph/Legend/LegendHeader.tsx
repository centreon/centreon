import { includes, isEmpty, split } from 'ramda';

import { Tooltip, Typography } from '@mui/material';

import { Line } from '../timeSeries/models';

import { useStyles } from './Legend.styles';

interface Props {
  line: Line;
}

const LegendHeader = ({ line }: Props): JSX.Element => {
  const { classes } = useStyles({});
  const { unit, name, legend } = line;

  const legendName = legend || name;
  const unitName = ` (${unit})`;
  const metricName = includes('#', legendName)
    ? split('#')(legendName)[1]
    : legendName;

  return (
    <div className={classes.legendName}>
      <Tooltip placement="top" title={legendName + unitName}>
        <Typography
          className={classes.legendName}
          component="p"
          variant="caption"
        >
          {metricName}
        </Typography>
      </Tooltip>
      <Typography
        className={classes.legendUnit}
        component="p"
        variant="caption"
      >
        {!isEmpty(line?.unit) && `(${line.unit})`}
      </Typography>
    </div>
  );
};

export default LegendHeader;
