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

import { DashboardsContact, resource } from './models';
import { dashboardsContactsEndpoint } from './endpoints';
import { List, ListQueryParams } from './meta.models';
import { dashboardsContactsListDecoder } from './decoders';

type UseListDashboardsContactsProps<
  TQueryFnData extends List<DashboardsContact> = List<DashboardsContact>,
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

type UseListDashboardsContacts<
  TError = ResponseError,
  TData extends List<DashboardsContact> = List<DashboardsContact>
> = UseQueryResult<TData | TError, TError>;

const useListDashboardsContacts = (
  props?: UseListDashboardsContactsProps
): UseListDashboardsContacts => {
  const { params, options } = props || {};

  const { data, ...queryData } = useFetchQuery<List<DashboardsContact>>({
    decoder: dashboardsContactsListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsContactsEndpoint,
        parameters: { ...params }
      }),
    getQueryKey: () => [resource.dashboardsContacts],
    queryOptions: { ...options }
  });

  return {
    ...queryData,
    data
  };
};

export { useListDashboardsContacts };
