import { useEffect } from 'react';

import {
  UseMutationOptions,
  UseMutationResult,
  useMutation,
  useQueryClient
} from '@tanstack/react-query';
import { equals, includes, omit, type } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

import useSnackbar from '../../Snackbar/useSnackbar';
import { useDeepCompare } from '../../utils';
import { CatchErrorProps, ResponseError, customFetch } from '../customFetch';
import { errorLog } from '../logger';
import {
  OptimisticListing,
  useOptimisticMutation
} from './useOptimisticMutation';

export enum Method {
  DELETE = 'DELETE',
  GET = 'GET',
  PATCH = 'PATCH',
  POST = 'POST',
  PUT = 'PUT'
}

interface Variables<TMeta, T> {
  _meta?: TMeta;
  payload?: T;
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
    variables: Variables<TMeta, T>,
    context: unknown
  ) => unknown;
  onMutate?: (variables: Variables<TMeta, T>) => Promise<unknown> | unknown;
  onSuccess?: (
    data: ResponseError | T,
    variables: Variables<TMeta, T>,
    context: unknown
  ) => unknown;
  optimisticListing?: OptimisticListing;
} & Omit<
  UseMutationOptions<{ _meta?: TMeta; payload: T }>,
  'mutationFn' | 'onError' | 'onMutate' | 'onSuccess' | 'mutateAsync' | 'mutate'
>;

export type UseMutationQueryState<T, TMeta> = Omit<
  UseMutationResult<T | ResponseError>,
  'isError' | 'mutate' | 'mutateAsync'
> & {
  isError: boolean;
  isMutating: boolean;
  mutate: (variables: Variables<TMeta, T>) => ResponseError | T;
  mutateAsync: (
    variables: Variables<TMeta, T>,
    rest?: Pick<
      UseMutationQueryProps<T, TMeta>,
      'onError' | 'onMutate' | 'onSettled' | 'onSuccess'
    >
  ) => Promise<ResponseError | T>;
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
  baseEndpoint,
  optimisticListing
}: UseMutationQueryProps<T, TMeta>): UseMutationQueryState<T, TMeta> => {
  const { showErrorMessage } = useSnackbar();

  const queryClient = useQueryClient();
  const { getListingQueryKey, getOptimisticMutationItems, getPreviousListing } =
    useOptimisticMutation({ optimisticListing });

  const queryData = useMutation<
    T | ResponseError,
    ResponseError,
    Variables<TMeta, T>
  >({
    mutationFn: (
      variables: Variables<TMeta, T>
    ): Promise<T | ResponseError> => {
      const { _meta, payload } = variables || {};

      return customFetch<T>({
        baseEndpoint,
        catchError,
        decoder,
        defaultFailureMessage,
        endpoint: getEndpoint(_meta as TMeta),
        headers: new Headers({
          'Content-Type': 'application/json',
          ...fetchHeaders
        }),
        isMutation: true,
        method,
        payload
      });
    },
    onError: (error, variables, context) => {
      if (optimisticListing?.enabled) {
        const listingQueryKey = getListingQueryKey();
        queryClient.setQueriesData(
          { queryKey: listingQueryKey },
          context.previousListing
        );
      }

      onError?.(error, variables, context);
    },
    onMutate: optimisticListing?.enabled
      ? ({ payload, _meta }) => {
          const listingQueryKey = getListingQueryKey();
          const newListing = getOptimisticMutationItems({
            method,
            payload,
            _meta
          });
          const previousListing = getPreviousListing();

          queryClient.setQueriesData({ queryKey: listingQueryKey }, newListing);

          return { previousListing };
        }
      : onMutate,
    onSettled,
    onSuccess: (data, variables, context) => {
      if (optimisticListing?.enabled) {
        const isQueryKeyArray = equals(
          type(optimisticListing.queryKey),
          'Array'
        );
        const listingQueryKey = isQueryKeyArray
          ? optimisticListing?.queryKey
          : [optimisticListing?.queryKey];

        queryClient.invalidateQueries({
          queryKey: listingQueryKey
        });
      }

      if (data?.isError) {
        if (optimisticListing?.enabled) {
          const listingQueryKey = getListingQueryKey();
          queryClient.setQueriesData(
            { queryKey: listingQueryKey },
            context.previousListing
          );
        }

        onError?.(data, variables, context);

        return;
      }
      onSuccess?.(data, variables, context);
    }
  });

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
