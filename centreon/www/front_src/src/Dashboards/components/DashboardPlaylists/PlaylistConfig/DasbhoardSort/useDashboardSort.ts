import { useMemo } from 'react';

import { equals, prop, sortBy } from 'ramda';
import { useFormikContext } from 'formik';

import { PlaylistConfig } from '../../models';

interface UseDashboardSortState {
  sortDashboards: (items: Array<string>) => void;
  sortedDashboards: Array<{
    id: string;
    name?: string;
    order: number;
  }>;
}

export const useDashboardSort = (): UseDashboardSortState => {
  const { values, setFieldValue } = useFormikContext<PlaylistConfig>();

  const sortedDashboards = useMemo(
    () =>
      sortBy(prop('order'), values.dashboards).map(({ id, order, name }) => ({
        id: `${id}`,
        name,
        order
      })),
    [values.dashboards]
  );

  const sortDashboards = (items: Array<string>): void => {
    const newDashboardsOrder = items.map((dashboardId, idx) => {
      const dashboard = sortedDashboards.find(({ id }) =>
        equals(id, dashboardId)
      );

      return {
        ...dashboard,
        id: Number(dashboard?.id),
        order: idx
      };
    });

    setFieldValue('dashboards', newDashboardsOrder);
  };

  return {
    sortDashboards,
    sortedDashboards
  };
};
