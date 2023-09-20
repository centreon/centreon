import { useListDashboards } from '../../api/useListDashboards';
import { Dashboard } from '../../api/models';
import { List } from '../../api/meta.models';

type UseDashboardsOverview = {
  dashboards: Array<Dashboard>;
  isEmptyList: boolean;
  isLoading: boolean;
};

const useDashboardsOverview = (): UseDashboardsOverview => {
  const { data, isLoading } = useListDashboards();

  const dashboards = (data as List<Dashboard>)?.result || [];

  return {
    dashboards,
    isEmptyList: dashboards.length === 0,
    isLoading
  };
};

export { useDashboardsOverview };
