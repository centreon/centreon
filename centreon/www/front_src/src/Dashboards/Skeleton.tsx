/* eslint-disable react/no-array-index-key */
import { List, ListItemSkeleton } from '@centreon/ui';

const tiles = Array(5).fill(0);

const ListingSkeleton = (): JSX.Element => {
  return (
    <List>
      {tiles.map((_, index) => (
        <ListItemSkeleton key={index} />
      ))}
    </List>
  );
};

export default ListingSkeleton;
