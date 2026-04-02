import { useInfiniteScrollListingWithCursor } from '@centreon/ui';

import { ResourceStatus } from 'src/centreon-widget-statusgrid/src/StatusGridStandard/models';

import { Resource } from '../../../models';
import { getResourcesSearchQueryParameters } from '../../../utils';
import { resourcesEndpoint } from '../api/endpoint';

interface UseTooltipContentProps {
  resources: Array<Resource>;
  status: string;
  type: string;
}

interface UseTooltipContentState {
  elementRef;
  isLoading: boolean;
  resources: Array<ResourceStatus>;
}

export const useTooltipContent = ({
  type,
  status,
  resources
}: UseTooltipContentProps): UseTooltipContentState => {
  const { resourcesSearchConditions, resourcesCustomParameters } =
    getResourcesSearchQueryParameters(resources);
  const { elementRef, elements, isLoading } =
    useInfiniteScrollListingWithCursor<ResourceStatus>({
      customQueryParameters: [
        { name: 'types', value: [type] },
        { name: 'statuses', value: [status.toUpperCase()] },
        ...resourcesCustomParameters
      ],
      endpoint: resourcesEndpoint,
      limit: 10,
      parameters: {
        search: {
          conditions: resourcesSearchConditions
        }
      },
      queryKeyName: `statusChart_${type}_${status}`,
      suspense: false
    });

  return {
    elementRef,
    isLoading,
    resources: elements
  };
};
