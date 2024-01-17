import { useFetchQuery } from '@centreon/ui';

import { buildResourcesEndpoint } from '../api/endpoints';

import { formatRessourcesResponse } from './utils';
import { ResourceListing } from './models';

interface LoadResourcesProps {
  displayType;
  limit;
  page;
  refreshCount;
  refreshIntervalToUse;
  resources;
  sortField;
  sortOrder;
  states;
  statuses;
}
interface LoadResources {
  data?: ResourceListing;
  isLoading: boolean;
}

const useLoadResources = ({
  resources,
  states,
  statuses,
  displayType,
  refreshCount,
  refreshIntervalToUse,
  page,
  limit,
  sortField,
  sortOrder
}: LoadResourcesProps): LoadResources => {
  const sort = { [sortField]: sortOrder };

  const { data, isLoading } = useFetchQuery<ResourceListing>({
    getEndpoint: () => {
      return buildResourcesEndpoint({
        limit: limit || 10,
        page: page || 1,
        resources,
        sort,
        states,
        statuses,
        type: displayType
      });
    },
    getQueryKey: () => [
      'resourcestable',
      displayType,
      JSON.stringify(states),
      JSON.stringify(statuses),
      sortField,
      sortOrder,
      limit,
      JSON.stringify(resources),
      page,
      refreshCount
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    }
  });

  return { data: formatRessourcesResponse({ data, displayType }), isLoading };
};

export default useLoadResources;
