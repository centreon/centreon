import 'ulog';
import { startTransition, useEffect } from 'react';

import {
  QueryClient,
  QueryKey,
  useMutation,
  useQueryClient
} from '@tanstack/react-query';
import { JsonDecoder } from 'ts.data.json';
import anylogger from 'anylogger';
import { includes } from 'ramda';

import { CatchErrorProps, customFetch, ResponseError } from '../customFetch';
import useSnackbar from '../../Snackbar/useSnackbar';

export enum Method {
  DELETE = 'DELETE',
  GET = 'GET',
  PATCH = 'PATCH',
  POST = 'POST',
  PUT = 'PUT'
}

interface OptimisticUIProps<T> {
  onError?: (error, variables, context) => void;
  onMutate?: (variables) => unknown;
  onSettled?: (
    data: T | ResponseError | undefined,
    error: unknown,
    variables: void,
    context: unknown
  ) => unknown;
  onSuccess?: (data, variables, context) => void;
  queryKeyToInvalidate: QueryKey;
}

export interface UseMutationQueryProps<T> {
  catchError?: (props: CatchErrorProps) => void;
  decoder?: JsonDecoder.Decoder<T>;
  defaultFailureMessage?: string;
  fetchHeaders?: HeadersInit;
  getEndpoint: (payload) => string;
  httpCodesBypassErrorSnackbar?: Array<number>;
  method: Method;
  optimisticUI?: OptimisticUIProps<T>;
}

const log = anylogger('API Request');

export interface UseMutationQueryState<T> {
  isError: boolean;
  isMutating: boolean;
  mutate: (payload) => void;
  mutateAsync: (payload) => Promise<T | ResponseError>;
  queryClient: QueryClient;
}

const useMutationQuery = <T extends object>({
  getEndpoint,
  catchError,
  decoder,
  defaultFailureMessage,
  fetchHeaders,
  httpCodesBypassErrorSnackbar = [],
  method,
  optimisticUI
}: UseMutationQueryProps<T>): UseMutationQueryState<T> => {
  const { showErrorMessage } = useSnackbar();

  const queryClient = useQueryClient();

  const queryData = useMutation<T | ResponseError>({
    mutationFn: (payload): Promise<T | ResponseError> =>
      customFetch<T>({
        catchError,
        decoder,
        defaultFailureMessage,
        endpoint: getEndpoint(payload),
        headers: new Headers({
          'Content-Type': 'application/x-www-form-urlencoded',
          ...fetchHeaders
        }),
        isMutation: true,
        method,
        payload
      }),
    onError: optimisticUI?.onError,
    onMutate: optimisticUI?.onMutate,
    onSettled: optimisticUI?.onSettled,
    onSuccess: (data, variables, context) => {
      startTransition(() => {
        queryClient.invalidateQueries({
          queryKey: optimisticUI?.queryKeyToInvalidate
        });
      });

      optimisticUI?.onSuccess?.(data, variables, context);
    }
  });

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
    manageError();
  }, [queryData.data]);

  return {
    isError: (queryData.data as ResponseError | undefined)?.isError || false,
    isMutating: queryData.isLoading,
    mutate: queryData.mutate,
    mutateAsync: queryData.mutateAsync,
    queryClient
  };
};

export default useMutationQuery;
