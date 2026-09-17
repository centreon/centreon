import { PageSkeleton } from '@centreon/ui';

import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()(() => ({
  skeletonContainer: {
    height: '100vh',
    width: '100%'
  }
}));

const PageLoader = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.skeletonContainer}>
      <PageSkeleton displayHeaderAndNavigation />
    </div>
  );
};

export default PageLoader;
