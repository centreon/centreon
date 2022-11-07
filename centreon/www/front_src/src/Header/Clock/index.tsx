<<<<<<< HEAD
import { useRef, useState, useEffect } from 'react';

import { Typography } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { Typography, makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import { useLocaleDateTimeFormat } from '@centreon/ui';

const useStyles = makeStyles((theme) => ({
  dateTime: {
    color: theme.palette.common.white,
  },
}));

const Clock = (): JSX.Element => {
  const classes = useStyles();

<<<<<<< HEAD
  const refreshIntervalRef = useRef<number>();
  const [dateTime, setDateTime] = useState({
=======
  const refreshIntervalRef = React.useRef<number>();
  const [dateTime, setDateTime] = React.useState({
>>>>>>> centreon/dev-21.10.x
    date: '',
    time: '',
  });

  const { format, toTime } = useLocaleDateTimeFormat();

  const updateDateTime = (): void => {
    const now = new Date();

    const date = format({ date: now, formatString: 'LL' });
    const time = toTime(now);

    setDateTime({ date, time });
  };

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    updateDateTime();

    const thirtySeconds = 30 * 1000;
    refreshIntervalRef.current = window.setInterval(
      updateDateTime,
      thirtySeconds,
    );

    return (): void => {
      clearInterval(refreshIntervalRef.current);
    };
  }, []);

  const { date, time } = dateTime;

  return (
    <div className={classes.dateTime}>
      <Typography variant="body2">{date}</Typography>
      <Typography variant="body1">{time}</Typography>
    </div>
  );
};

export default Clock;
