import { useEffect, useMemo, useRef } from 'react';

import 'ulog';
import {
  QueryKey,
  QueryObserverBaseResult,
  useQuery,
  useQueryClient,
  UseQueryOptions
} from '@tanstack/react-query';
import { JsonDecoder } from 'ts.data.json';
import anylogger from 'anylogger';
import { has, includes, isNil, not, omit } from 'ramda';

import { CatchErrorProps, customFetch, ResponseError } from '../customFetch';
import useSnackbar from '../../Snackbar/useSnackbar';
import { useDeepCompare } from '../../utils';

export interface UseFetchQueryProps<T> {
  baseEndpoint?: string;
  catchError?: (props: CatchErrorProps) => void;
  decoder?: JsonDecoder.Decoder<T>;
  defaultFailureMessage?: string;
  fetchHeaders?: HeadersInit;
  getEndpoint: (params?: PrefetchEndpointParams) => string;
  getQueryKey: () => QueryKey;
  httpCodesBypassErrorSnackbar?: Array<number>;
  isPaginated?: boolean;
  queryOptions?: Omit<
    UseQueryOptions<T | ResponseError, Error, T | ResponseError, QueryKey>,
    'queryKey' | 'queryFn'
  >;
}

export type UseFetchQueryState<T> = {
  data?: T;
  fetchQuery: () => Promise<T | ResponseError>;
  prefetchNextPage: ({ page, getPrefetchQueryKey }) => void;
  prefetchPreviousPage: ({ page, getPrefetchQueryKey }) => void;
  prefetchQuery: ({ endpointParams, queryKey }) => void;
} & Omit<QueryObserverBaseResult, 'data'>;

export interface PrefetchEndpointParams {
  page: number;
}

const log = anylogger('API Request');

const useFetchQuery = <T extends object>({
  getEndpoint,
  getQueryKey,
  catchError,
  decoder,
  defaultFailureMessage,
  fetchHeaders,
  isPaginated,
  queryOptions,
  httpCodesBypassErrorSnackbar = [],
  baseEndpoint
}: UseFetchQueryProps<T>): UseFetchQueryState<T> => {
  const dataRef = useRef<T | undefined>(undefined);

  const { showErrorMessage } = useSnackbar();

  const queryData = useQuery<T | ResponseError, Error>(
    getQueryKey(),
    ({ signal }): Promise<T | ResponseError> =>
      customFetch<T>({
        baseEndpoint,
        catchError,
        decoder,
        defaultFailureMessage,
        endpoint: getEndpoint(),
        headers: new Headers(fetchHeaders),
        signal
      }),
    {
      ...queryOptions
    }
  );

  const queryClient = useQueryClient();

  const manageError = (): void => {
    const data = queryData.data as ResponseError | undefined;
    if (data?.isError) {
      log.error(data.message);
      const hasACorrespondingHttpCode = includes(
        data?.statusCode || 0,
        httpCodesBypassErrorSnackbar
      );

      if (!hasACorrespondingHttpCode) {
        showErrorMessage(data?.message);
      }
    }
  };

  const prefetchQuery = ({ endpointParams, queryKey }): void => {
    queryClient.prefetchQuery(
      queryKey,
      ({ signal }): Promise<T | ResponseError> =>
        customFetch<T>({
          baseEndpoint,
          catchError,
          decoder,
          defaultFailureMessage,
          endpoint: getEndpoint(endpointParams),
          headers: new Headers(fetchHeaders),
          signal
        })
    );
  };

  const prefetchNextPage = ({ page, getPrefetchQueryKey }): void => {
    if (!isPaginated) {
      return undefined;
    }

    const nextPage = page + 1;

    return prefetchQuery({
      endpointParams: { page: nextPage },
      queryKey: getPrefetchQueryKey(nextPage)
    });
  };

  const prefetchPreviousPage = ({ page, getPrefetchQueryKey }): void => {
    if (!isPaginated) {
      return undefined;
    }

    const previousPage = page - 1;

    return prefetchQuery({
      endpointParams: { page: previousPage },
      queryKey: getPrefetchQueryKey(previousPage)
    });
  };

  const fetchQuery = (): Promise<T | ResponseError> => {
    return queryClient.fetchQuery(
      getQueryKey(),
      ({ signal }): Promise<T | ResponseError> =>
        customFetch<T>({
          baseEndpoint,
          catchError,
          decoder,
          defaultFailureMessage,
          endpoint: getEndpoint(),
          headers: new Headers(fetchHeaders),
          signal
        })
    );
  };

  const data = useMemo(
    () =>
      not(has('isError', queryData.data)) ? (queryData.data as T) : undefined,
    [queryData.data]
  );

  if (!isNil(data)) {
    dataRef.current = data;
  }

  const errorData = queryData.data as ResponseError | undefined;

  useEffect(() => {
    return (): void => {
      queryClient.cancelQueries(getQueryKey());
    };
  }, []);

  useEffect(() => {
    manageError();
  }, useDeepCompare([queryData.data]));

  return {
    ...omit(['data', 'error'], queryData),
    data: dataRef.current,
    error: errorData?.isError ? omit(['isError'], errorData) : null,
    fetchQuery,
    prefetchNextPage,
    prefetchPreviousPage,
    prefetchQuery
  };
};

export default useFetchQuery;
