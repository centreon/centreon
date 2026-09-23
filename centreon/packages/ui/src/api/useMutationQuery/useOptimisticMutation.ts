import { useQueryClient } from '@tanstack/react-query';
import { append, equals, last, remove, type, update } from 'ramda';

import { Method } from '.';

interface GetOptimisticMutationListingProps<T, TMeta> {
  method: Method;
  payload: T | undefined;
  _meta: TMeta | undefined;
}

export interface OptimisticListing {
  enabled: boolean;
  queryKey: string | Array<string>;
  total: number;
  limit: number;
}

interface UseOptimisticMutationProps {
  optimisticListing?: OptimisticListing;
}

interface UseOptimisticMutationState<T, TMeta> {
  getOptimisticMutationItems: (
    props: GetOptimisticMutationListingProps<T, TMeta>
  ) => object;
  getListingQueryKey: () => Array<string>;
  getPreviousListing: () => unknown;
}

export const useOptimisticMutation = <T, TMeta>({
  optimisticListing
}: UseOptimisticMutationProps): UseOptimisticMutationState<T, TMeta> => {
  const queryClient = useQueryClient();

  const getListingQueryKey = (): Array<string> => {
    const isQueryKeyArray = equals(type(optimisticListing?.queryKey), 'Array');
    const listingQueryKey = isQueryKeyArray
      ? optimisticListing?.queryKey
      : [optimisticListing?.queryKey];

    return listingQueryKey as Array<string>;
  };

  const getPreviousListing = (): unknown => {
    const listingQueryKey = getListingQueryKey();

    const items = last(
      queryClient.getQueriesData({
        queryKey: listingQueryKey
      })
    )?.[1];

    return items;
  };

  const getOptimisticMutationItems = ({
    method,
    payload,
    _meta
  }: GetOptimisticMutationListingProps<T, TMeta>): object => {
    const listingQueryKey = getListingQueryKey();

    const updatedPayload =
      payload && 'id' in (payload as Record<string, unknown>)
        ? payload
        : {
            ...(payload as Record<string, unknown>),
            id: (optimisticListing?.total ?? 0) + 1
          };

    const hasOnlyOnePage =
      (optimisticListing?.total || 0) <= (optimisticListing?.limit || 0);
    const isFormDataPayload = equals(type(updatedPayload), 'FormData');

    const items = last(
      queryClient.getQueriesData({
        queryKey: listingQueryKey
      })
    )?.[1] as { result: Array<Record<string, unknown>> } | undefined;

    if (equals(Method.POST, method) && !isFormDataPayload && hasOnlyOnePage) {
      const newItems = append(
        updatedPayload as Record<string, unknown>,
        items?.result ?? []
      );

      return { ...items, result: newItems };
    }

    if (equals(Method.DELETE, method) && hasOnlyOnePage) {
      const itemIndex = items?.result.findIndex(({ id }) =>
        equals(id, (_meta as Record<string, unknown>)?.id)
      );
      const newItems = remove(itemIndex ?? 0, 1, items?.result ?? []);

      return { ...items, result: newItems };
    }

    if (
      (equals(Method.PUT, method) ||
        equals(Method.PATCH, method) ||
        (equals(Method.POST, method) && isFormDataPayload)) &&
      hasOnlyOnePage
    ) {
      const itemIndex = items?.result.findIndex(({ id }) =>
        equals(id, (_meta as Record<string, unknown>)?.id)
      );
      const item = items?.result.find(({ id }) =>
        equals(id, (_meta as Record<string, unknown>)?.id)
      );
      const updatedItem = equals(Method.PUT, method)
        ? updatedPayload
        : {
            ...item,
            ...(isFormDataPayload
              ? (
                  Object as unknown as {
                    fromEntries: (
                      entries: IterableIterator<[string, FormDataEntryValue]>
                    ) => Record<string, unknown>;
                  }
                ).fromEntries((updatedPayload as unknown as FormData).entries())
              : updatedPayload)
          };
      const newItems = update(
        itemIndex ?? 0,
        updatedItem as Record<string, unknown>,
        items?.result ?? []
      );

      return { ...items, result: newItems };
    }

    return items as object;
  };
  return { getListingQueryKey, getOptimisticMutationItems, getPreviousListing };
};
