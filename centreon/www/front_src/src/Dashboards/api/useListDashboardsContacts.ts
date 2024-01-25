import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import { DashboardsContact, resource } from './models';
import { dashboardsContactsEndpoint } from './endpoints';
import { List, ListQueryParams } from './meta.models';
import { dashboardsContactsListDecoder } from './decoders';

type UseListDashboardsContactsProps = {
  params: ListQueryParams;
};

type UseListDashboardsContacts = {
  data?: List<DashboardsContact>;
};

const useListDashboardsContacts = (
  props?: UseListDashboardsContactsProps
): UseListDashboardsContacts => {
  const { params } = props || {};

  const { data } = useFetchQuery<List<DashboardsContact>>({
    decoder: dashboardsContactsListDecoder,
    doNotCancelCallsOnUnmount: true,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsContactsEndpoint,
        parameters: { ...params }
      }),
    getQueryKey: () => [resource.dashboardsContacts]
  });

  return {
    data
  };
};

export { useListDashboardsContacts };
