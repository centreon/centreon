import * as React from 'react';

import { Skeleton } from '@mui/material';
import { makeStyles } from '@mui/styles';

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
      variant="rectangular"
    />
  );
};

export default FilterSkeleton;
