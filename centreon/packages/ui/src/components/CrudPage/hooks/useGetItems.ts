import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { ListingModel, buildListingEndpoint } from '../../..';
import useFetchQuery from '../../../api/useFetchQuery';
import {
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';
import { UseGetItemsProps, UseGetItemsState } from '../models';
import { useListingQueryKey } from './useListingQueryKey';

export const useGetItems = <TData, TFilters>({
  queryKeyName,
  filtersAtom,
  decoder,
  getSearchParameters,
  baseEndpoint
}: UseGetItemsProps<TData, TFilters>): UseGetItemsState<TData> => {
  const queryKey = useListingQueryKey({ queryKeyName, filtersAtom });

  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const search = useAtomValue(searchAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const filters = useAtomValue(filtersAtom);

  const { data, isLoading } = useFetchQuery<ListingModel<TData>>({
    decoder,
    getQueryKey: () => queryKey,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint,
        parameters: {
          page: page + 1,
          limit,
          sort: {
            [sortField]: sortOrder
          },
          search: {
            regex: {
              fields: ['name'],
              value: search
            },
            ...getSearchParameters({ filters, search })
          }
        }
      }),
    queryOptions: {
      suspense: false
    }
  });

  const items = data?.result || [];
  const hasItems = !!data;

  return {
    items,
    isDataEmpty: isEmpty(items),
    hasItems,
    isLoading,
    total: data?.meta.total || 0
  };
};
