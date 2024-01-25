import { useRef } from 'react';

import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import { DashboardAccessRightsContact, NamedEntity, resource } from './models';
import { getDashboardAccessRightsContactsEndpoint } from './endpoints';
import { List, ListQueryParams } from './meta.models';
import { dashboardAccessRightsContactListDecoder } from './decoders';

type UseListAccessRightsContactsProps = {
  dashboardId: NamedEntity['id'] | null;
  params?: ListQueryParams;
};

type UseListAccessRightsContacts = {
  data?: List<DashboardAccessRightsContact>;
  isFetching: boolean;
};

const useListAccessRightsContacts = ({
  params,
  dashboardId
}: UseListAccessRightsContactsProps): UseListAccessRightsContacts => {
  const dashboardResourceIdRef = useRef(dashboardId);

  const { data, isFetching } = useFetchQuery<
    List<DashboardAccessRightsContact>
  >({
    decoder: dashboardAccessRightsContactListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: getDashboardAccessRightsContactsEndpoint(
          dashboardResourceIdRef.current as string
        ),
        parameters: { ...params }
      }),
    getQueryKey: () => [
      resource.dashboardAccessRightsContacts,
      dashboardResourceIdRef.current
    ],
    queryOptions: {
      enabled: !!dashboardResourceIdRef.current,
      suspense: false
    }
  });

  return {
    data,
    isFetching
  };
};

export { useListAccessRightsContacts };
