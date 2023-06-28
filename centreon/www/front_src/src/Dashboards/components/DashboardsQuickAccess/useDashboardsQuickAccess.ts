import { Dashboard, isDashboardList } from '../../api/models';
import { useListDashboards } from '../../api/useListDashboards';

type UseDashboardsQuickAccess = {
  dashboards: Array<Dashboard>;
};

const useDashboardsQuickAccess = (): UseDashboardsQuickAccess => {
  const { data } = useListDashboards();

  return {
    dashboards: isDashboardList(data) ? data.result : []
  };
};

export { useDashboardsQuickAccess };
