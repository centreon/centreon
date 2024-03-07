import { useInfiniteScrollListing } from '@centreon/ui';

import { ResourceStatus } from '../../StatusGridStandard/models';
import { tooltipPageAtom } from '../../StatusGridStandard/Tooltip/atoms';
import {
  getListingCustomQueryParameters,
  resourcesEndpoint
} from '../../api/endpoints';
import { Resource } from '../../../../models';

interface UseLoadResourcesProps {
  bypassRequest: boolean;
  resourceType: string;
  resources: Array<Resource>;
  status: string;
}

interface UseLoadResourcesState {
  elementRef;
  elements: Array<ResourceStatus>;
  isLoading: boolean;
  total?: number;
}

export const useLoadResources = ({
  resources,
  resourceType,
  status,
  bypassRequest
}: UseLoadResourcesProps): UseLoadResourcesState => {
  const { elementRef, elements, isLoading, total } =
    useInfiniteScrollListing<ResourceStatus>({
      customQueryParameters: getListingCustomQueryParameters({
        resources,
        statuses: [status],
        types: [resourceType]
      }),
      enabled: !bypassRequest,
      endpoint: resourcesEndpoint,
      limit: 10,
      pageAtom: tooltipPageAtom,
      parameters: {
        sort: { status: 'DESC' }
      },
      queryKeyName: `statusgrid_condensed_${status}`,
      suspense: false
    });

  return {
    elementRef,
    elements,
    isLoading,
    total
  };
};
