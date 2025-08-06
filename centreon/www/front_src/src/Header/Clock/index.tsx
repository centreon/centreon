import { useRef, useState, useEffect } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { useLocaleDateTimeFormat } from '@centreon/ui';

const useStyles = makeStyles()((theme) => ({
  dateTime: {
    color: theme.palette.common.white,
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'space-between',
    marginLeft: theme.spacing(4),
    [theme.breakpoints.down(769)]: {
      display: 'none'
    }
  },
  nowrap: {
    whiteSpace: 'nowrap'
  }
}));

const Clock = (): JSX.Element => {
  const { classes } = useStyles();

  const refreshIntervalRef = useRef<number | undefined>(undefined);
  const [dateTime, setDateTime] = useState(new Date());

  const { format, toTime } = useLocaleDateTimeFormat();

  const updateDateTime = (): void => {
    setDateTime(new Date());
  };

  useEffect(() => {
    updateDateTime();

    const thirtySeconds = 30 * 1000;
    refreshIntervalRef.current = window.setInterval(
      updateDateTime,
      thirtySeconds
    );

    return (): void => {
      clearInterval(refreshIntervalRef.current);
    };
  }, []);

  return (
    <div className={classes.dateTime} data-cy="clock">
      <Typography className={classes.nowrap} variant="body2">
        {format({ date: dateTime, formatString: 'LL' })}
      </Typography>

      <Typography className={classes.nowrap} variant="body1">
        {toTime(dateTime)}
      </Typography>
    </div>
  );
};

export default Clock;
