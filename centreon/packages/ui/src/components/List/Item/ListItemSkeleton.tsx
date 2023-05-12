import { LoadingSkeleton } from '../../..';

import { useStyles } from './ListItem.styles';

const ListItemSkeleton = (): JSX.Element => {
  const { classes } = useStyles();

  return <LoadingSkeleton className={classes.listItem} />;
};

export { ListItemSkeleton };
