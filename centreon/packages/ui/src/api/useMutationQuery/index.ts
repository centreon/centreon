import 'ulog';
import { useEffect } from 'react';

import {
  useMutation,
  UseMutationOptions,
  UseMutationResult
} from '@tanstack/react-query';
import { JsonDecoder } from 'ts.data.json';
import anylogger from 'anylogger';
import { includes, omit } from 'ramda';

import { CatchErrorProps, customFetch, ResponseError } from '../customFetch';
import useSnackbar from '../../Snackbar/useSnackbar';
import { useDeepCompare } from '../../utils';

export enum Method {
  DELETE = 'DELETE',
  GET = 'GET',
  PATCH = 'PATCH',
  POST = 'POST',
  PUT = 'PUT'
}

export type UseMutationQueryProps<T, TMeta> = {
  baseEndpoint?: string;
  catchError?: (props: CatchErrorProps) => void;
  decoder?: JsonDecoder.Decoder<T>;
  defaultFailureMessage?: string;
  fetchHeaders?: HeadersInit;
  getEndpoint: (_meta: TMeta) => string;
  httpCodesBypassErrorSnackbar?: Array<number>;
  method: Method;
  onError?: (
    error: ResponseError,
    variables: T & { _meta: TMeta },
    context: unknown
  ) => unknown;
  onMutate?: (variables: T & { _meta: TMeta }) => Promise<unknown> | unknown;
  onSuccess?: (
    data: ResponseError | T,
    variables: T & {
      _meta: TMeta;
    },
    context: unknown
  ) => unknown;
} & Omit<
  UseMutationOptions<T & { _meta?: TMeta }>,
  'mutationFn' | 'onError' | 'onMutate' | 'onSuccess'
>;

const log = anylogger('API Request');

export type UseMutationQueryState<T> = Omit<
  UseMutationResult<T | ResponseError>,
  'isError'
> & {
  isError: boolean;
  isMutating: boolean;
};

const useMutationQuery = <T extends object, TMeta>({
  getEndpoint,
  catchError,
  decoder,
  defaultFailureMessage,
  fetchHeaders,
  httpCodesBypassErrorSnackbar = [],
  method,
  onMutate,
  onError,
  onSuccess,
  onSettled,
  baseEndpoint
}: UseMutationQueryProps<T, TMeta>): UseMutationQueryState<T> => {
  const { showErrorMessage } = useSnackbar();

  const queryData = useMutation<
    T | ResponseError,
    ResponseError,
    T & { _meta: TMeta }
  >({
    mutationFn: (
      _payload: T & { _meta: TMeta }
    ): Promise<T | ResponseError> => {
      const { _meta, ...payload } = _payload || {};

      return customFetch<T>({
        baseEndpoint,
        catchError,
        decoder,
        defaultFailureMessage,
        endpoint: getEndpoint(_meta as TMeta),
        headers: new Headers({
          'Content-Type': 'application/x-www-form-urlencoded',
          ...fetchHeaders
        }),
        isMutation: true,
        method,
        payload
      });
    },
    onError,
    onMutate,
    onSettled,
    onSuccess: (data, variables, context) => {
      if (data?.isError) {
        onError?.(data, variables, context);

        return;
      }
      onSuccess?.(data, variables, context);
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

  useEffect(
    () => {
      manageError();
    },
    useDeepCompare([queryData.data])
  );

  return {
    ...omit(['isError'], queryData),
    isError: (queryData.data as ResponseError | undefined)?.isError || false,
    isMutating: queryData.isPending
  };
};

export default useMutationQuery;
