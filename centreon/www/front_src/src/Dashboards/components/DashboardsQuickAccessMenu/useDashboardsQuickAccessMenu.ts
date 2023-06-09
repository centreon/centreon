import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import { dashboardListDecoder } from '../../api/decoders';
import { dashboardsEndpoint } from '../../api/endpoints';
import { Dashboard, resource } from '../../api/models';

type UseDashboardsQuickAccessMenu = {
  dashboards: Array<Dashboard>;
};

const useDashboardsQuickAccessMenu = (): UseDashboardsQuickAccessMenu => {
  // TODO use a `useListDashboards` hook
  const { data, isLoading } = useFetchQuery({
    decoder: dashboardListDecoder,
    getEndpoint: (params) =>
      buildListingEndpoint({
        baseEndpoint: dashboardsEndpoint,
        parameters: { limit: 100, page: 1 }
      }),
    getQueryKey: () => [resource.dashboards, 1],
    isPaginated: false
  });

  return {
    dashboards: data?.result || []
  };
};

export { useDashboardsQuickAccessMenu };
