/* eslint-disable react/no-array-index-key */
import { DataTable } from '@centreon/ui/components';

const tiles = Array(5).fill(0);

const ListingSkeleton = (): JSX.Element => {
  return (
    <DataTable>
      {tiles.map((_, index) => (
        <DataTable.ItemSkeleton key={index} />
      ))}
    </DataTable>
  );
};

export default ListingSkeleton;
