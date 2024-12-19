import { useQueryClient } from '@tanstack/react-query';
import { append, equals, last, remove, type, update } from 'ramda';
import { Method } from '.';

interface GetOptimisticMutationListingProps<T, TMeta> {
  method: Method;
  payload: T;
  _meta: TMeta;
}

export interface OptimisticListing {
  enabled: boolean;
  queryKey: string | Array<string>;
  total: number;
  limit: number;
}

interface UseOptimisticMutationProps {
  optimisticListing: OptimisticListing;
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
    const isQueryKeyArray = equals(type(optimisticListing.queryKey), 'Array');
    const listingQueryKey = isQueryKeyArray
      ? optimisticListing.queryKey
      : [optimisticListing.queryKey];

    return listingQueryKey;
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

    const hasOnlyOnePage = optimisticListing.total <= optimisticListing.limit;
    const isFormDataPayload = equals(type(payload), 'FormData');

    const items = last(
      queryClient.getQueriesData({
        queryKey: listingQueryKey
      })
    )?.[1];

    if (equals(Method.POST, method) && !isFormDataPayload && hasOnlyOnePage) {
      const newItems = append(payload, items.result);

      return { ...items, result: newItems };
    }

    if (equals(Method.DELETE, method) && hasOnlyOnePage) {
      const itemIndex = items.result.findIndex(({ id }) =>
        equals(id, _meta.id)
      );
      const newItems = remove(itemIndex, 1, items.result);

      return { ...items, result: newItems };
    }

    if (
      (equals(Method.PUT, method) ||
        equals(Method.PATCH, method) ||
        (equals(Method.POST, method) && isFormDataPayload)) &&
      hasOnlyOnePage
    ) {
      const itemIndex = items.result.findIndex(({ id }) =>
        equals(id, _meta.id)
      );
      const item = items.result.find(({ id }) => equals(id, _meta.id));
      const updatedItem = equals(Method.PUT, method)
        ? payload
        : {
            ...item,
            ...(isFormDataPayload
              ? Object.fromEntries(payload.entries())
              : payload)
          };
      const newItems = update(itemIndex, updatedItem, items.result);

      return { ...items, result: newItems };
    }

    return items;
  };
  return { getOptimisticMutationItems, getListingQueryKey, getPreviousListing };
};
