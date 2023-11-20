import { includes, isEmpty, split } from 'ramda';

import { Typography } from '@mui/material';

import { EllipsisTypography } from '../../..';
import { Line } from '../../common/timeSeries/models';

import { useLegendHeaderStyles } from './Legend.styles';

interface Props {
  color: string;
  disabled?: boolean;
  line: Line;
}

const LegendHeader = ({ line, color, disabled }: Props): JSX.Element => {
  const { classes, cx } = useLegendHeaderStyles({ color });

  const { unit, name, legend } = line;

  const legendName = legend || name;
  const unitName = `(${unit})`;
  const metricName = includes('#', legendName)
    ? split('#')(legendName)[1]
    : legendName;

  return (
    <div className={classes.container}>
      <div className={classes.markerAndLegendName}>
        <div className={cx(classes.icon, { [classes.disabled]: disabled })} />
        <EllipsisTypography className={cx(classes.text, classes.legendName)}>
          {metricName}
        </EllipsisTypography>
      </div>
      {!isEmpty(line?.unit) && (
        <Typography className={classes.text}>{unitName}</Typography>
      )}
    </div>
  );
};

export default LegendHeader;
