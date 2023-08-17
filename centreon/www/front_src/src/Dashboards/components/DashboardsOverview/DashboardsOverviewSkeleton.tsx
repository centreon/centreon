/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { DataTable } from '@centreon/ui/components';

const tiles = Array(3).fill(0);

const DashboardsOverviewSkeleton = (): ReactElement => {
  return (
    <DataTable>
      {tiles.map((_, index) => (
        <DataTable.ItemSkeleton key={index} />
      ))}
    </DataTable>
  );
};

export { DashboardsOverviewSkeleton };
