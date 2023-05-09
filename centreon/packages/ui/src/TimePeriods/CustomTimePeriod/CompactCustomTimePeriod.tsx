import { useAtomValue } from 'jotai';

import AccessTimeIcon from '@mui/icons-material/AccessTime';
import { Button, Typography } from '@mui/material';

import { dateTimeFormat, useLocaleDateTimeFormat } from '@centreon/ui';

import { customTimePeriodAtom } from '../timePeriodsAtoms';

import useStyles from './CompactCustomTimePeriod.styles';

interface Props {
  disabled?: boolean;
  onClick: (event) => void;
}

const CompactCustomTimePeriod = ({
  onClick,
  disabled = false
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const { format } = useLocaleDateTimeFormat();

  const customTimePeriod = useAtomValue(customTimePeriodAtom);

  return (
    <div>
      <Button
        aria-label="CompactTimePeriod"
        className={classes.button}
        color="primary"
        data-testid="Compact time period"
        disabled={disabled}
        variant="outlined"
        onClick={onClick}
      >
        <div className={classes.buttonContent}>
          <AccessTimeIcon />
          <div className={classes.containerDates}>
            <div className={classes.timeContainer}>
              <Typography
                className={classes.label}
                component="div"
                variant="caption"
              >
                From:
              </Typography>

              <Typography
                className={classes.date}
                component="div"
                variant="caption"
              >
                {format({
                  date: customTimePeriod.start,
                  formatString: dateTimeFormat
                })}
              </Typography>
            </div>
            <div className={classes.timeContainer}>
              <Typography
                className={classes.label}
                component="div"
                variant="caption"
              >
                To:
              </Typography>

              <Typography
                className={classes.date}
                component="div"
                variant="caption"
              >
                {format({
                  date: customTimePeriod.end,
                  formatString: dateTimeFormat
                })}
              </Typography>
            </div>
          </div>
        </div>
      </Button>
    </div>
  );
};

export default CompactCustomTimePeriod;
