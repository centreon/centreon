import * as React from 'react';

import { Skeleton } from '@material-ui/lab';
import { makeStyles } from '@material-ui/core';

const useStyles = makeStyles((theme) => ({
  listingSkeleton: {
    height: theme.spacing(50),
    margin: theme.spacing(2, 1),
  },
}));

const ListingSkeleton = (): JSX.Element => {
  const classes = useStyles();

  return (
    <Skeleton
      animation="wave"
      className={classes.listingSkeleton}
      variant="rect"
    />
  );
};

export default ListingSkeleton;
