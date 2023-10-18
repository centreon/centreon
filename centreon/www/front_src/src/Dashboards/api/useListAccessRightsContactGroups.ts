import { useRef } from 'react';

import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import {
  DashboardAccessRightsContactGroup,
  NamedEntity,
  resource
} from './models';
import { getDashboardAccessRightsContactGroupsEndpoint } from './endpoints';
import { dashboardAccessRightsContactGroupListDecoder } from './decoders';
import { List, ListQueryParams } from './meta.models';

type UseListAccessRightsContactGroupsProps = {
  dashboardId: NamedEntity['id'] | null;
  params?: ListQueryParams;
};

type UseListAccessRightsContactGroups = {
  data?: List<DashboardAccessRightsContactGroup>;
  isFetching: boolean;
};

const useListAccessRightsContactGroups = ({
  params,
  dashboardId
}: UseListAccessRightsContactGroupsProps): UseListAccessRightsContactGroups => {
  const dashboardResourceIdRef = useRef(dashboardId);

  const { data, isFetching } = useFetchQuery<
    List<DashboardAccessRightsContactGroup>
  >({
    decoder: dashboardAccessRightsContactGroupListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: getDashboardAccessRightsContactGroupsEndpoint(
          dashboardResourceIdRef.current as string
        ),
        parameters: { ...params }
      }),
    getQueryKey: () => [
      resource.dashboardAccessRightsContactGroups,
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

export { useListAccessRightsContactGroups };
