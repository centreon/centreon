import {
  type QueryKey,
  usePrefetchQuery as useTanstackPrefetchQuery
} from '@tanstack/react-query';
import type { JsonDecoder } from 'ts.data.json';

import {
  type CatchErrorProps,
  customFetch,
  type ResponseError
} from '../customFetch';
import { errorLog } from '../logger';

export interface UsePrefetchQueryProps<T> {
  baseEndpoint?: string;
  catchError?: (props: CatchErrorProps) => void;
  decoder?: JsonDecoder.Decoder<T>;
  defaultFailureMessage?: string;
  fetchHeaders?: HeadersInit;
  getEndpoint: () => string;
  getQueryKey: () => QueryKey;
}

/**
 * Warms the query cache for a later useFetchQuery call sharing the same key.
 * An error response is not cached: the query function throws, so the consumer
 * refetches on mount instead of reading the error as data.
 */
const usePrefetchQuery = <T extends object>({
  baseEndpoint,
  catchError,
  decoder,
  defaultFailureMessage,
  fetchHeaders,
  getEndpoint,
  getQueryKey
}: UsePrefetchQueryProps<T>): void => {
  useTanstackPrefetchQuery<T>({
    queryFn: ({ signal }): Promise<T> =>
      customFetch<T>({
        baseEndpoint,
        catchError,
        decoder,
        defaultFailureMessage,
        endpoint: getEndpoint(),
        headers: new Headers(fetchHeaders),
        signal
      }).then((response) => {
        const error = response as ResponseError;
        if (error?.isError) {
          errorLog(error.message);
          throw new Error(error.message);
        }

        return response as T;
      }),
    queryKey: getQueryKey(),
    retry: false
  });
};

export default usePrefetchQuery;
