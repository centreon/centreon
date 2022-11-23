import { makeStyles } from 'tss-react/mui';

import { Skeleton } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  filterSkeleton: {
    height: theme.spacing(6),
    width: '100%'
  }
}));

const FilterSkeleton = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Skeleton
      animation="wave"
      className={classes.filterSkeleton}
      variant="rectangular"
    />
  );
};

export default FilterSkeleton;
