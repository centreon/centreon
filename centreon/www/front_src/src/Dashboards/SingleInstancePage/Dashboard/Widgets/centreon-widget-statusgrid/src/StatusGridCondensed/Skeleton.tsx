import { LoadingSkeleton } from '@centreon/ui';

import { useStatusGridCondensedStyles } from './StatusGridCondensed.styles';

interface Props {
  statuses: Array<string>;
}

const Skeleton = ({ statuses }: Props): JSX.Element => {
  const { classes } = useStatusGridCondensedStyles();

  return (
    <div data-skeleton className={classes.container}>
      <LoadingSkeleton variant="text" width="60px" />
      <div className={classes.statuses}>
        {statuses.map((status) => (
          <LoadingSkeleton
            className={classes.status}
            height="100%"
            key={status}
            width="100%"
          />
        ))}
      </div>
    </div>
  );
};

export default Skeleton;
