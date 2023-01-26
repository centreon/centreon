import { useEffect } from 'react';

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
import { has, includes, not, omit } from 'ramda';

import { CatchErrorProps, customFetch, ResponseError } from '../customFetch';
import useSnackbar from '../../Snackbar/useSnackbar';

export interface UseFetchQueryProps<T> {
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

export interface UseFetchQueryState<T>
  extends Omit<QueryObserverBaseResult, 'data'> {
  data?: T;
  fetchQuery: () => Promise<T | ResponseError>;
  prefetchNextPage: ({ page, getPrefetchQueryKey }) => void;
  prefetchPreviousPage: ({ page, getPrefetchQueryKey }) => void;
  prefetchQuery: ({ endpointParams, queryKey }) => void;
}

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
  httpCodesBypassErrorSnackbar = []
}: UseFetchQueryProps<T>): UseFetchQueryState<T> => {
  const { showErrorMessage } = useSnackbar();

  const queryData = useQuery<T | ResponseError, Error>(
    getQueryKey(),
    ({ signal }): Promise<T | ResponseError> =>
      customFetch<T>({
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

  useEffect(() => {
    return (): void => {
      queryClient.cancelQueries(getQueryKey());
    };
  }, []);

  manageError();

  const prefetchQuery = ({ endpointParams, queryKey }): void => {
    queryClient.prefetchQuery(
      queryKey,
      ({ signal }): Promise<T | ResponseError> =>
        customFetch<T>({
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
          catchError,
          decoder,
          defaultFailureMessage,
          endpoint: getEndpoint(),
          headers: new Headers(fetchHeaders),
          signal
        })
    );
  };

  const data = not(has('isError', queryData.data))
    ? (queryData.data as T)
    : undefined;

  const errorData = queryData.data as ResponseError | undefined;

  return {
    ...omit(['data'], queryData),
    data,
    error: errorData?.isError ? omit(['isError'], errorData) : null,
    fetchQuery,
    prefetchNextPage,
    prefetchPreviousPage,
    prefetchQuery
  };
};

export default useFetchQuery;
