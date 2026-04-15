import { SeverityCode, useInfiniteScrollListingWithCursor } from '@centreon/ui';

import { equals } from 'ramda';

import { resourcesEndpoint } from '../../api/endpoints';
import { ResourceStatus } from '../models';

interface UseHostTooltipContentState {
  elementRef;
  isLoading: boolean;
  services: Array<ResourceStatus>;
}

export const useHostTooltipContent = ({ name }): UseHostTooltipContentState => {
  const { elementRef, elements, isLoading } =
    useInfiniteScrollListingWithCursor<ResourceStatus>({
      customQueryParameters: [
        { name: 'types', value: ['service'] },
        { name: 'statuses', value: ['WARNING', 'CRITICAL'] }
      ],
      endpoint: resourcesEndpoint,
      limit: 10,
      parameters: {
        search: {
          conditions: [
            {
              field: 'parent_name',
              values: {
                $rg: `^${name}$`
              }
            }
          ]
        },
        sort: { status: 'DESC' }
      },
      queryKeyName: `statusgrid_${name}`,
      suspense: false
    });

  const serviceswithProblems = elements.filter(
    ({ status }) =>
      equals(SeverityCode.High, status?.severity_code) ||
      equals(SeverityCode.Medium, status?.severity_code)
  );

  return {
    elementRef,
    isLoading,
    services: serviceswithProblems
  };
};
