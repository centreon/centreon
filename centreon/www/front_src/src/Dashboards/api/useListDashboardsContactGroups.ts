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

import { DashboardsContactGroup, resource } from './models';
import { dashboardsContactGroupsEndpoint } from './endpoints';
import { List, ListQueryParams } from './meta.models';
import { dashboardsContactGroupsListDecoder } from './decoders';

type UseListDashboardsContactGroupsProps<
  TQueryFnData extends List<DashboardsContactGroup> = List<DashboardsContactGroup>,
  TError = ResponseError,
  TData = TQueryFnData,
  TQueryKey extends QueryKey = QueryKey
> = {
  options?: Omit<
    UseQueryOptions<TQueryFnData, TError, TData, TQueryKey>,
    'queryKey' | 'queryFn' | 'initialData'
  >;
  params: ListQueryParams;
};

type UseListDashboardsContactGroups<
  TError = ResponseError,
  TData extends List<DashboardsContactGroup> = List<DashboardsContactGroup>
> = UseQueryResult<TData | TError, TError>;

const useListDashboardsContactGroups = (
  props?: UseListDashboardsContactGroupsProps
): UseListDashboardsContactGroups => {
  const { params, options } = props || {};

  const { data, ...queryData } = useFetchQuery<List<DashboardsContactGroup>>({
    decoder: dashboardsContactGroupsListDecoder,
    doNotCancelCallsOnUnmount: true,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsContactGroupsEndpoint,
        parameters: { ...params }
      }),
    getQueryKey: () => [resource.dashboardsContactGroups],
    queryOptions: { ...options }
  });

  return {
    ...queryData,
    data
  };
};

export { useListDashboardsContactGroups };
