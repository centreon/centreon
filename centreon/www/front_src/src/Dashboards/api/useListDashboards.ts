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

import { Dashboard, List, ListQueryParams, resource } from './models';
import { dashboardsEndpoint } from './endpoints';
import { dashboardListDecoder } from './decoders';

type UseListDashboardProps<
  TQueryFnData extends List<Dashboard> = List<Dashboard>,
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

type UseListDashboards<
  TError = ResponseError,
  TData extends List<Dashboard> = List<Dashboard>
> = UseQueryResult<TData | TError, TError>;

const useListDashboards = (
  props?: UseListDashboardProps
): UseListDashboards => {
  const { options, params } = props || {};

  const { data, ...queryData } = useFetchQuery<List<Dashboard>>({
    decoder: dashboardListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsEndpoint,
        parameters: { ...params }
      }),
    getQueryKey: () => [resource.dashboards]
  });

  return {
    ...queryData,
    data
  };
};

export { useListDashboards };
