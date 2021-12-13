import * as React from 'react';

import { Skeleton } from '@material-ui/lab';
import { makeStyles } from '@material-ui/core';

const useStyles = makeStyles((theme) => ({
  filterSkeleton: {
    height: theme.spacing(6),
    width: '100%',
  },
}));

const FilterSkeleton = (): JSX.Element => {
  const classes = useStyles();

  return (
    <Skeleton
      animation="wave"
      className={classes.filterSkeleton}
      variant="rect"
    />
  );
};

export default FilterSkeleton;
