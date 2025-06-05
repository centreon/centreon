import {
  ListingModel,
  buildListingEndpoint,
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
  data: Array<AgentConfigurationListing>;
  isLoading: boolean;
  hasData: boolean;
  isDataEmpty: boolean;
  total: number;
}

export const useGetAgentConfigurations = (): UseGetAgentConfigurationsState => {
  const queryKey = useListingQueryKey();

  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const filters = useAtomValue(filtersAtom);

  const nameConditions = useMemo(
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
      !isEmpty(filters.types)
        ? [
            {
              field: 'type',
              values: {
                $in: pluck('id', filters.types)
              }
            }
          ]
        : [],
    [filters.types]
  );

  const pollersConditions = useMemo(
    () =>
      !isEmpty(filters.pollers)
        ? [
            {
              field: 'poller.id',
              values: {
                $in: pluck('id', filters.pollers)
              }
            }
          ]
        : [],
    [filters.pollers]
  );

  const conditions = [
    ...nameConditions,
    ...agentTypesConditions,
    ...pollersConditions
  ];

  const { data, isFetching } = useFetchQuery<
    ListingModel<AgentConfigurationListing>
  >({
    decoder: agentConfigurationsListingDecoder,
    getQueryKey: () => queryKey,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: getAgentConfigurationsEndpoint,
        parameters: {
          page: page + 1,
          limit,
          sort: {
            [sortField]: sortOrder
          },
          search: {
            conditions: isEmpty(conditions) ? undefined : conditions
          }
        }
      }),
    queryOptions: {
      suspense: false
    }
  });

  const agentConfigurations = data?.result || [];
  const hasData = !!data;

  return {
    data: agentConfigurations,
    isDataEmpty: isEmpty(agentConfigurations),
    hasData,
    isLoading: isFetching,
    total: data?.meta.total || 0
  };
};
