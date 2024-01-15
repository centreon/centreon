import { ReactElement } from 'react';

import { List } from '../../../List';

import { useListStyles } from './List.styles';

const ListItemSkeleton = (): ReactElement => {
  const { classes } = useListStyles();

  return (
    <List.Item className={classes.item}>
      <List.Item.Avatar.Skeleton />
      <List.Item.Text.Skeleton secondaryText />
    </List.Item>
  );
};

export default ListItemSkeleton;
