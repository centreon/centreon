<<<<<<< HEAD
import { ReactNode } from 'react';

import { Paper } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { Paper, makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  content: {
    padding: theme.spacing(1, 2, 2, 2),
  },
}));

interface Props {
<<<<<<< HEAD
  children?: ReactNode;
=======
  children?: React.ReactNode;
>>>>>>> centreon/dev-21.10.x
  className?: string;
}

const Card = ({ children, className }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Paper className={className} elevation={0}>
      <div className={classes.content}>{children}</div>
    </Paper>
  );
};

export default Card;
