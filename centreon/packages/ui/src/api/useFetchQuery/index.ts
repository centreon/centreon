import { useEffect, useMemo, useRef } from 'react';

import {
  QueryKey,
  QueryObserverBaseResult,
  useQuery,
  useQueryClient,
  UseQueryOptions
} from '@tanstack/react-query';
import { JsonDecoder } from 'ts.data.json';
import { equals, has, includes, isNil, not, omit } from 'ramda';

import { CatchErrorProps, customFetch, ResponseError } from '../customFetch';
import useSnackbar from '../../Snackbar/useSnackbar';
import { useDeepCompare } from '../../utils';
import { errorLog } from '../logger';

export interface UseFetchQueryProps<T> {
  baseEndpoint?: string;
  catchError?: (props: CatchErrorProps) => void;
  decoder?: JsonDecoder.Decoder<T>;
  defaultFailureMessage?: string;
  doNotCancelCallsOnUnmount?: boolean;
  fetchHeaders?: HeadersInit;
  getEndpoint: (params?: PrefetchEndpointParams) => string;
  getQueryKey: () => QueryKey;
  httpCodesBypassErrorSnackbar?: Array<number>;
  isPaginated?: boolean;
  queryOptions?: {
    suspense?: boolean;
  } & Omit<
    UseQueryOptions<T | ResponseError, Error, T | ResponseError, QueryKey>,
    'queryKey' | 'queryFn'
  >;
  useLongCache?: boolean;
}

export type UseFetchQueryState<T> = {
  data?: T;
  error: Omit<ResponseError, 'isError'> | null;
  fetchQuery: () => Promise<T | ResponseError>;
  prefetchNextPage: ({ page, getPrefetchQueryKey }) => void;
  prefetchPreviousPage: ({ page, getPrefetchQueryKey }) => void;
  prefetchQuery: ({ endpointParams, queryKey }) => void;
} & Omit<QueryObserverBaseResult, 'data' | 'error'>;

export interface PrefetchEndpointParams {
  page: number;
}

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
  baseEndpoint,
  doNotCancelCallsOnUnmount = false,
  useLongCache
}: UseFetchQueryProps<T>): UseFetchQueryState<T> => {
  const dataRef = useRef<T | undefined>(undefined);

  const { showErrorMessage } = useSnackbar();

  const isCypressTest = equals(window.Cypress?.testingType, 'component');

  const queryData = useQuery<T | ResponseError, Error>({
    gcTime: !isCypressTest && useLongCache ? 60 * 1000 : undefined,
    queryFn: ({ signal }): Promise<T | ResponseError> =>
      customFetch<T>({
        baseEndpoint,
        catchError,
        decoder,
        defaultFailureMessage,
        endpoint: getEndpoint(),
        headers: new Headers(fetchHeaders),
        signal
      }),
    queryKey: getQueryKey(),
    ...queryOptions
  });

  const queryClient = useQueryClient();

  const manageError = (): void => {
    const data = queryData.data as ResponseError | undefined;
    if (data?.isError) {
      errorLog(data.message);
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
    queryClient.prefetchQuery({
      queryFn: ({ signal }): Promise<T | ResponseError> =>
        customFetch<T>({
          baseEndpoint,
          catchError,
          decoder,
          defaultFailureMessage,
          endpoint: getEndpoint(endpointParams),
          headers: new Headers(fetchHeaders),
          signal
        }),
      queryKey
    });
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
    return queryClient.fetchQuery({
      queryFn: ({ signal }): Promise<T | ResponseError> =>
        customFetch<T>({
          baseEndpoint,
          catchError,
          decoder,
          defaultFailureMessage,
          endpoint: getEndpoint(),
          headers: new Headers(fetchHeaders),
          signal
        }),
      queryKey: getQueryKey()
    });
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
      if (doNotCancelCallsOnUnmount) {
        return;
      }

      queryClient.cancelQueries({ queryKey: getQueryKey() });
    };
  }, []);

  useEffect(
    () => {
      manageError();
    },
    useDeepCompare([queryData.data])
  );

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
