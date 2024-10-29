import { useInfiniteScrollListing } from '@centreon/ui';

import { Resource } from '../../../models';
import { getResourcesSearchQueryParameters } from '../../../utils';
import { resourcesEndpoint } from '../api/endpoint';
import { tooltipPageAtom } from '../atom';

import { ResourceStatus } from 'src/centreon-widget-statusgrid/src/StatusGridStandard/models';

interface UseTooltipContentProps {
  resources: Array<Resource>;
  status: string;
  type: string;
}

interface UseTooltipContentState {
  elementRef;
  isLoading: boolean;
  resources: Array<ResourceStatus>;
  total?: number;
}

export const useTooltipContent = ({
  type,
  status,
  resources
}: UseTooltipContentProps): UseTooltipContentState => {
  const { resourcesSearchConditions, resourcesCustomParameters } =
    getResourcesSearchQueryParameters(resources);
  const { elementRef, elements, isLoading, total } =
    useInfiniteScrollListing<ResourceStatus>({
      customQueryParameters: [
        { name: 'types', value: [type] },
        { name: 'statuses', value: [status.toUpperCase()] },
        ...resourcesCustomParameters
      ],
      endpoint: resourcesEndpoint,
      limit: 10,
      pageAtom: tooltipPageAtom,
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
    resources: elements,
    total
  };
};
