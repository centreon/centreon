import 'ulog';
import { useMutation } from '@tanstack/react-query';
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

export interface UseMutationQueryProps<T> {
  catchError?: (props: CatchErrorProps) => void;
  decoder?: JsonDecoder.Decoder<T>;
  defaultFailureMessage?: string;
  fetchHeaders?: HeadersInit;
  getEndpoint: () => string;
  httpCodesBypassErrorSnackbar?: Array<number>;
  method: Method;
}

const log = anylogger('API Request');

export interface UseMutationQueryState<T> {
  isError: boolean;
  isMutating: boolean;
  mutate: (payload) => void;
  mutateAsync: (payload) => Promise<T | ResponseError>;
}

const useMutationQuery = <T extends object>({
  getEndpoint,
  catchError,
  decoder,
  defaultFailureMessage,
  fetchHeaders,
  httpCodesBypassErrorSnackbar = [],
  method
}: UseMutationQueryProps<T>): UseMutationQueryState<T> => {
  const { showErrorMessage } = useSnackbar();

  const queryData = useMutation<T | ResponseError>(
    (payload): Promise<T | ResponseError> =>
      customFetch<T>({
        catchError,
        decoder,
        defaultFailureMessage,
        endpoint: getEndpoint(),
        headers: new Headers({
          'Content-Type': 'application/x-www-form-urlencoded',
          ...fetchHeaders
        }),
        isMutation: true,
        method,
        payload
      })
  );

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

  manageError();

  return {
    isError: (queryData.data as ResponseError | undefined)?.isError || false,
    isMutating: queryData.isLoading,
    mutate: queryData.mutate,
    mutateAsync: queryData.mutateAsync
  };
};

export default useMutationQuery;
