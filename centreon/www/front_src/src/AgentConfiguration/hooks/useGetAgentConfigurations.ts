import {
  buildListingEndpoint,
  ListingModel,
  useFetchQuery
} from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { isEmpty, pluck } from 'ramda';
import { useMemo } from 'react';

import { agentConfigurationsListingDecoder } from '../api/decoders';
import { getAgentConfigurationsEndpoint } from '../api/endpoints';
import {
  filtersAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';
import { AgentConfigurationListing } from '../models';
import { useListingQueryKey } from './useListingQueryKey';

interface UseGetAgentConfigurationsState {
  data;
  isLoading: boolean;
}

export const useGetAgentConfigurations = (): UseGetAgentConfigurationsState => {
  const queryKey = useListingQueryKey();

  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const filters = useAtomValue(filtersAtom);

  const nameCondition = useMemo(
    () =>
      filters.name
        ? [
            {
              field: 'name',
              values: {
                $rg: filters.name
              }
            }
          ]
        : [],
    [filters.name]
  );

  const agentTypesConditions = useMemo(
    () =>
      !isEmpty(filters.type)
        ? [
            {
              field: 'type',
              values: {
                $in: pluck('id', filters.type)
              }
            }
          ]
        : [],
    [filters.type]
  );

  const pollersConditions = useMemo(
    () =>
      !isEmpty(filters['poller.id'])
        ? [
            {
              field: 'poller.id',
              values: {
                $in: pluck('id', filters['poller.id'])
              }
            }
          ]
        : [],
    [filters['poller.id']]
  );

  const conditions = [
    ...nameCondition,
    ...agentTypesConditions,
    ...pollersConditions
  ];

  const { data, isFetching } = useFetchQuery<
    ListingModel<AgentConfigurationListing>
  >({
    decoder: agentConfigurationsListingDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: getAgentConfigurationsEndpoint,
        parameters: {
          limit,
          page: page + 1,
          search: {
            conditions
          },
          sort: {
            [sortField]: sortOrder
          }
        }
      }),
    getQueryKey: () => queryKey,
    queryOptions: {
      refetchOnMount: false,
      staleTime: 0,
      suspense: false
    }
  });

  return {
    data,
    isLoading: isFetching
  };
};
