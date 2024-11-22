/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { DataTable } from '@centreon/ui/components';

const tiles = [0, 1, 2];

const DashboardsOverviewSkeleton = (): ReactElement => {
  return (
    <DataTable>
      {tiles.map((tile) => (
        <DataTable.ItemSkeleton key={`tile-${tile}`} />
      ))}
    </DataTable>
  );
};

export { DashboardsOverviewSkeleton };
