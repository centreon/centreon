import { makeStyles } from 'tss-react/mui';

import { Skeleton } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  listingSkeleton: {
    height: theme.spacing(50),
    margin: theme.spacing(2, 1)
  }
}));

const ListingSkeleton = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Skeleton
      animation="wave"
      className={classes.listingSkeleton}
      variant="rectangular"
    />
  );
};

export default ListingSkeleton;
