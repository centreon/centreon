import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import { Dashboard, resource } from './models';
import { dashboardsEndpoint } from './endpoints';
import { dashboardListDecoder } from './decoders';
import { List } from './meta.models';

type UseListDashboards = {
  data?: List<Dashboard>;
  isLoading: boolean;
};

const useListDashboards = (): UseListDashboards => {
  const { data, isLoading } = useFetchQuery<List<Omit<Dashboard, 'refresh'>>>({
    decoder: dashboardListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsEndpoint,
        parameters: {}
      }),
    getQueryKey: () => [resource.dashboards]
  });

  return {
    data,
    isLoading
  };
};

export { useListDashboards };
