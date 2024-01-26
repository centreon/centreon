import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import { DashboardsContactGroup, resource } from './models';
import { dashboardsContactGroupsEndpoint } from './endpoints';
import { List, ListQueryParams } from './meta.models';
import { dashboardsContactGroupsListDecoder } from './decoders';

type UseListDashboardsContactGroupsProps = {
  params: ListQueryParams;
};

type UseListDashboardsContactGroups = {
  data?: List<DashboardsContactGroup>;
};

const useListDashboardsContactGroups = (
  props?: UseListDashboardsContactGroupsProps
): UseListDashboardsContactGroups => {
  const { params } = props || {};

  const { data } = useFetchQuery<List<DashboardsContactGroup>>({
    decoder: dashboardsContactGroupsListDecoder,
    doNotCancelCallsOnUnmount: true,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsContactGroupsEndpoint,
        parameters: { ...params }
      }),
    getQueryKey: () => [resource.dashboardsContactGroups]
  });

  return {
    data
  };
};

export { useListDashboardsContactGroups };
