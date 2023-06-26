import { useEffect, useRef, useState } from 'react';

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
  const [isEmptyList, setIsEmptyList] = useState<boolean>(true);

  useEffect(() => {
    dashboards.current = isDashboardList(data) ? data.result : [];
    setIsEmptyList(dashboards.current.length === 0);
  }, [data]);

  return {
    dashboards: dashboards.current,
    isEmptyList,
    isLoading
  };
};

export { useDashboardsOverview };
