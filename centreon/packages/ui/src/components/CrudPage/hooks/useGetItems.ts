import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';

import { buildListingEndpoint, type ListingModel } from '../../..';
import useFetchQuery from '../../../api/useFetchQuery';
import {
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';
import type { UseGetItemsProps, UseGetItemsState } from '../models';
import { useListingQueryKey } from './useListingQueryKey';

export const useGetItems = <TData, TFilters>({
  queryKeyName,
  filtersAtom,
  decoder,
  getSearchParameters,
  baseEndpoint
}: UseGetItemsProps<TData, TFilters>): UseGetItemsState<TData> => {
  const queryKey = useListingQueryKey({ filtersAtom, queryKeyName });

  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const search = useAtomValue(searchAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const filters = useAtomValue(filtersAtom);

  const { data, isLoading } = useFetchQuery<ListingModel<TData>>({
    decoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint,
        parameters: {
          limit,
          page: page + 1,
          search: {
            regex: {
              fields: ['name'],
              value: search
            },
            ...getSearchParameters({ filters, search })
          },
          sort: {
            [sortField]: sortOrder
          }
        }
      }),
    getQueryKey: () => queryKey,
    queryOptions: {
      suspense: false
    }
  });

  const items = data?.result || [];
  const hasItems = !!data;

  return {
    hasItems,
    isDataEmpty: isEmpty(items),
    isLoading,
    items,
    total: data?.meta.total || 0
  };
};
