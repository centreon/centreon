import { ReactElement } from 'react';

import { LoadingSkeleton } from '../../..';

import { useStyles } from './GridItem.styles';

const GridItemSkeleton = (): ReactElement => {
  const { classes } = useStyles();

  return <LoadingSkeleton className={classes.dataTableItem} />;
};

export default GridItemSkeleton;
