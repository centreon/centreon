import {
  ListingModel,
  buildListingEndpoint,
  useFetchQuery
} from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { isEmpty, pluck } from 'ramda';
import { useMemo, useRef } from 'react';
import { agentConfigurationsListingDecoder } from '../api/decoders';
import { getAgentConfigurationsEndpoint } from '../api/endpoints';
import {
  filtersAtom,
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';
import { AgentConfigurationListing } from '../models';
import { useListingQueryKey } from './useListingQueryKey';

interface UseGetAgentConfigurationsState {
  data: Array<AgentConfigurationListing>;
  isLoading: boolean;
  isDataEmpty: boolean;
  total: number;
}

export const useGetAgentConfigurations = (): UseGetAgentConfigurationsState => {
  const queryKey = useListingQueryKey();
  const mountedRef = useRef(false);

  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const search = useAtomValue(searchAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const filters = useAtomValue(filtersAtom);

  const agentTypesConditions = useMemo(
    () =>
      filters.agentTypes
        ? [
            {
              field: 'type',
              values: {
                $in: pluck('id', filters.agentTypes)
              }
            }
          ]
        : [],
    [filters.agentTypes]
  );

  const pollersConditions = useMemo(
    () =>
      filters.pollers
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

  const { data, isLoading } = useFetchQuery<
    ListingModel<AgentConfigurationListing>
  >({
    baseEndpoint: 'http://localhost:3001/centreon/api/latest',
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
            regex: {
              fields: ['name'],
              value: search
            },
            conditions: [...agentTypesConditions, ...pollersConditions]
          }
        }
      }),
    queryOptions: {
      suspense: !mountedRef.current
    }
  });

  if (!mountedRef.current) {
    mountedRef.current = true;
  }

  const agentConfigurations = data?.result || [];

  return {
    data: agentConfigurations,
    isDataEmpty: isEmpty(agentConfigurations),
    isLoading,
    total: data?.meta.total || 0
  };
};
