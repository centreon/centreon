import { ReactElement } from 'react';

import { List } from '../../../List';

import { useListStyles } from './List.styles';
import ListItemSkeleton from './ListItemSkeleton';

const ListSkeleton = (): ReactElement => {
  const { classes } = useListStyles();

  return (
    <div className={classes.list}>
      <List>
        {[...Array(3).keys()].map((key) => (
          <ListItemSkeleton key={key} />
        ))}
      </List>
    </div>
  );
};

export default ListSkeleton;
