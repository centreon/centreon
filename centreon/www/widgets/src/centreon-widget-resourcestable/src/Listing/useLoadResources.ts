import { useFetchQuery } from '@centreon/ui';

import { buildResourcesEndpoint } from '../api/endpoints';
import { Resource } from '../../../models';

import { formatRessources } from './utils';
import { DisplayType, ResourceListing, SortOrder } from './models';

interface LoadResourcesProps {
  displayType: DisplayType;
  limit?: number;
  page: number | undefined;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statuses: Array<string>;
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
  const sort = { [sortField as string]: sortOrder };

  const { data, isLoading } = useFetchQuery<ResourceListing>({
    getEndpoint: () => {
      return buildResourcesEndpoint({
        limit: limit || 10,
        page: page || 1,
        resources,
        sort: sort || { status_severity_code: SortOrder.Desc },
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

  return { data: formatRessources({ data, displayType }), isLoading };
};

export default useLoadResources;
