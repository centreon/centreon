import { useInfiniteScrollListing } from '@centreon/ui';

import { resourcesEndpoint } from '../api/endpoint';
import { tooltipPageAtom } from '../atom';

import { ResourceStatus } from 'src/centreon-widget-statusgrid/src/StatusGridStandard/models';

interface UseHostTooltipContentState {
  elementRef;
  isLoading: boolean;
  resources: Array<ResourceStatus>;
  total?: number;
}

export const useTooltipContent = ({
  type,
  status
}): UseHostTooltipContentState => {
  const { elementRef, elements, isLoading, total } =
    useInfiniteScrollListing<ResourceStatus>({
      customQueryParameters: [
        { name: 'types', value: [type] },
        { name: 'statuses', value: [status.toUpperCase()] }
      ],
      endpoint: resourcesEndpoint,
      limit: 10,
      pageAtom: tooltipPageAtom,
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
