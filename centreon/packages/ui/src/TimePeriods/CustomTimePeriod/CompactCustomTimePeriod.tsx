import { useAtomValue } from 'jotai';
import { lt } from 'ramda';

import AccessTimeIcon from '@mui/icons-material/AccessTime';
import { Button, Typography, useTheme } from '@mui/material';

import { dateTimeFormat, useLocaleDateTimeFormat } from '@centreon/ui';

import { customTimePeriodAtom } from '../timePeriodsAtoms';

import useStyles from './CompactCustomTimePeriod.styles';

interface Props {
  disabled?: boolean;
  onClick: (event) => void;
  width: number;
}

const CompactCustomTimePeriod = ({
  width,
  onClick,
  disabled = false
}: Props): JSX.Element => {
  const theme = useTheme();
  const { classes } = useStyles();

  const { format } = useLocaleDateTimeFormat();

  const customTimePeriod = useAtomValue(customTimePeriodAtom);

  const isCompact = lt(width, theme.breakpoints.values.sm);

  return (
    <Button
      aria-label="Compact time period"
      className={classes.button}
      color="primary"
      data-testid="Compact time period"
      disabled={disabled}
      variant="outlined"
      onClick={onClick}
    >
      <div className={classes.buttonContent}>
        <AccessTimeIcon />
        <div className={isCompact ? classes.compactFromTo : classes.fromTo}>
          <div className={classes.timeContainer}>
            <div className={classes.dateLabel}>
              <Typography variant="caption">From:</Typography>
            </div>
            <div className={classes.date}>
              <Typography variant="caption">
                {format({
                  date: customTimePeriod.start,
                  formatString: dateTimeFormat
                })}
              </Typography>
            </div>
          </div>
          <div className={classes.timeContainer}>
            <div className={classes.dateLabel}>
              <Typography variant="caption">To:</Typography>
            </div>
            <div className={classes.date}>
              <Typography variant="caption">
                {format({
                  date: customTimePeriod.end,
                  formatString: dateTimeFormat
                })}
              </Typography>
            </div>
          </div>
        </div>
      </div>
    </Button>
  );
};

export default CompactCustomTimePeriod;
