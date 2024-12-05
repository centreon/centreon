import { isEmpty } from 'ramda';

import { List } from '../../../api/meta.models';
import { Dashboard } from '../../../api/models';
import { useListDashboards } from '../../../api/useListDashboards';

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
