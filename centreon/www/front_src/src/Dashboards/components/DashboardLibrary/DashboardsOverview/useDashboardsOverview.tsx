import { isEmpty } from 'ramda';

import { useListDashboards } from '../../../api/useListDashboards';
import { Dashboard } from '../../../api/models';
import { List } from '../../../api/meta.models';

type UseDashboardsOverview = {
  dashboards: Array<Dashboard>;
  data?: List<Dashboard>;
  isEmptyList: boolean;
  isLoading: boolean;
};

const useDashboardsOverview = (): UseDashboardsOverview => {
  const { data, isLoading } = useListDashboards();

  const isEmptyList = isEmpty((data as List<Dashboard>)?.result || []);

  return {
    dashboards: (data as List<Dashboard>)?.result || [],
    data,
    isEmptyList,
    isLoading
  };
};

export { useDashboardsOverview };
