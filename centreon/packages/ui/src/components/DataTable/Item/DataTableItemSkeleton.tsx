import { ReactElement } from 'react';

import { LoadingSkeleton } from '../../..';

import { useStyles } from './DataTableItem.styles';

const DataTableItemSkeleton = (): ReactElement => {
  const { classes } = useStyles();

  return <LoadingSkeleton className={classes.dataTableItem} />;
};

export { DataTableItemSkeleton };
