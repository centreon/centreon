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

import {
  DashboardAccessRightsContactGroup,
  NamedEntity,
  resource
} from './models';
import { getDashboardAccessRightsContactGroupsEndpoint } from './endpoints';
import { dashboardAccessRightsContactGroupListDecoder } from './decoders';
import { List, ListQueryParams } from './meta.models';

type UseListAccessRightsContactGroupsProps<
  TQueryFnData extends List<DashboardAccessRightsContactGroup> = List<DashboardAccessRightsContactGroup>,
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

type UseListAccessRightsContactGroups<
  TError = ResponseError,
  TData extends List<DashboardAccessRightsContactGroup> = List<DashboardAccessRightsContactGroup>
> = UseQueryResult<TData | TError, TError>;

const useListAccessRightsContactGroups = ({
  params,
  dashboardId
}: UseListAccessRightsContactGroupsProps): UseListAccessRightsContactGroups => {
  const dashboardResourceIdRef = useRef(dashboardId);

  const { data, ...queryData } = useFetchQuery<
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
    ...queryData,
    data
  };
};

export { useListAccessRightsContactGroups };
