import { LoadingSkeleton } from '../../..';

import { useStyles } from './DataTableItem.styles';

const DataTableItemSkeleton = (): JSX.Element => {
  const { classes } = useStyles();

  return <LoadingSkeleton className={classes.dataTableItem} />;
};

export { DataTableItemSkeleton };
