import { LoadingSkeleton } from '@centreon/ui';
import { useFormStyles } from './useFormStyles';

const Skeletons = (): JSX.Element => {
  const { classes, cx } = useFormStyles();

  return (
    <div className={cx(classes.group, classes.loading)}>
      <LoadingSkeleton className={classes.skeleton} />
      <LoadingSkeleton className={classes.skeleton} />
      <LoadingSkeleton className={classes.skeleton} />
      <LoadingSkeleton className={classes.skeleton} />
      <LoadingSkeleton className={classes.skeleton} />
    </div>
  );
};

export default Skeletons;
