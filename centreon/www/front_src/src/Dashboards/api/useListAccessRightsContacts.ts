import { useRef } from 'react';

import {
  QueryKey,
  UseQueryOptions,
  UseQueryResult
} from '@tanstack/react-query';

import {
  buildListingEndpoint,
  ResponseError,
  useFetchQuery
} from '@centreon/ui';

import { DashboardAccessRightsContact, NamedEntity, resource } from './models';
import { getDashboardAccessRightsContactsEndpoint } from './endpoints';
import { List, ListQueryParams } from './meta.models';
import { dashboardAccessRightsContactListDecoder } from './decoders';

type UseListAccessRightsContactsProps<
  TQueryFnData extends List<DashboardAccessRightsContact> = List<DashboardAccessRightsContact>,
  TError = ResponseError,
  TData = TQueryFnData,
  TQueryKey extends QueryKey = QueryKey
> = {
  dashboardId: NamedEntity['id'] | null;
  options?: Omit<
    UseQueryOptions<TQueryFnData, TError, TData, TQueryKey>,
    'queryKey' | 'queryFn' | 'initialData'
  >;
  params?: ListQueryParams;
};

type UseListAccessRightsContacts<
  TError = ResponseError,
  TData extends List<DashboardAccessRightsContact> = List<DashboardAccessRightsContact>
> = UseQueryResult<TData | TError, TError>;

const useListAccessRightsContacts = ({
  params,
  dashboardId
}: UseListAccessRightsContactsProps): UseListAccessRightsContacts => {
  const dashboardResourceIdRef = useRef(dashboardId);

  const { data, ...queryData } = useFetchQuery<
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
    ...queryData,
    data
  };
};

export { useListAccessRightsContacts };
