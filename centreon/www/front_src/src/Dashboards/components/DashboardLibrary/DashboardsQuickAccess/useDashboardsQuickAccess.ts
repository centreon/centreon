import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import { dashboardListDecoder } from '../../../api/decoders';
import { dashboardsEndpoint } from '../../../api/endpoints';
import { List } from '../../../api/meta.models';
import { Dashboard, isDashboardList, resource } from '../../../api/models';

// The quick access menu must list every dashboard, independently of the
// search, favorite filter and pagination applied on the dashboards listing.
const quickAccessLimit = 1000;

type UseDashboardsQuickAccess = {
  dashboards: Array<Dashboard>;
};

const useDashboardsQuickAccess = (): UseDashboardsQuickAccess => {
  const { data } = useFetchQuery<List<Omit<Dashboard, 'refresh'>>>({
    decoder: dashboardListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsEndpoint,
        parameters: {
          limit: quickAccessLimit,
          page: 1,
          sort: { name: 'asc' }
        }
      }),
    getQueryKey: () => [resource.dashboards, 'quickAccess'],
    queryOptions: {
      suspense: false
    }
  });

  return {
    dashboards: isDashboardList(data) ? data.result : []
  };
};

export { useDashboardsQuickAccess };
