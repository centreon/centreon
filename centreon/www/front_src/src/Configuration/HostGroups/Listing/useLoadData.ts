import { useAtomValue } from 'jotai';

import {
  ListingModel,
  buildListingEndpoint,
  useFetchQuery
} from '@centreon/ui';

import { hostGroupsListEndpoint } from '../api/endpoints';

import { equals } from 'ramda';
import {
  filtersAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atom';
import { List, hostGroupListItem } from './models';

interface LoadDataState {
  data?: List<hostGroupListItem>;
  isLoading: boolean;
  reload?;
}

const useLoadData = (): LoadDataState => {
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const filters = useAtomValue(filtersAtom);

  const sort = { [sortField]: sortOrder };

  const searchConditions = [
    {
      field: 'name',
      values: {
        $rg: filters.name
      }
    },
    {
      field: 'alias',
      values: {
        $rg: filters.alias
      }
    },
    ...(equals(filters.enabled, filters.disabled)
      ? []
      : [
          {
            field: 'is_activated',
            values: {
              $eq: filters.enabled
            }
          }
        ])
  ];

  const { data, isFetching, fetchQuery } = useFetchQuery<
    ListingModel<hostGroupListItem>
  >({
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: hostGroupsListEndpoint,
        parameters: {
          limit: limit || 10,
          page: page || 1,
          search: {
            conditions: searchConditions
          },
          sort
        }
      }),
    getQueryKey: () => ['listHostGroups', sortField, sortOrder, limit, page],
    queryOptions: {
      refetchOnMount: false,
      staleTime: 0,
      suspense: false
    }
  });

  return { data, isLoading: isFetching, reload: fetchQuery };
};

export default useLoadData;
