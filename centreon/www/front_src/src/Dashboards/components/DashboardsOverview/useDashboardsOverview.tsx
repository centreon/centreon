import { useEffect, useRef } from 'react';

import { useListDashboards } from '../../api/useListDashboards';
import { Dashboard, isDashboardList } from '../../api/models';

type UseDashboardsOverview = {
  dashboards: Array<Dashboard>;
  isEmptyList: boolean;
  isLoading: boolean;
};

const useDashboardsOverview = (): UseDashboardsOverview => {
  const { data, isLoading } = useListDashboards();

  const dashboards = useRef<Array<Dashboard>>([]);

  useEffect(() => {
    dashboards.current = isDashboardList(data) ? data.result : [];
  }, [data]);

  return {
    dashboards: dashboards.current,
    isEmptyList: dashboards.current.length === 0,
    isLoading
  };
};

export { useDashboardsOverview };
