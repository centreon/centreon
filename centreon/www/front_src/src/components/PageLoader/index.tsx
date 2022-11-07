<<<<<<< HEAD
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import { PageSkeleton } from '@centreon/ui';

const useStyles = makeStyles(() => ({
  skeletonContainer: {
    height: '100vh',
    width: '100%',
  },
}));

const PageLoader = (): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.skeletonContainer}>
      <PageSkeleton displayHeaderAndNavigation />
    </div>
  );
};

export default PageLoader;
