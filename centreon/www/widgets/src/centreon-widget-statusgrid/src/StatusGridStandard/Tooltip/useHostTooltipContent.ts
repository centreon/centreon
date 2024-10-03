import { equals } from 'ramda';

import { SeverityCode, useInfiniteScrollListing } from '@centreon/ui';

import { resourcesEndpoint } from '../../api/endpoints';
import { ResourceStatus } from '../models';

import { tooltipPageAtom } from './atoms';

interface UseHostTooltipContentState {
  elementRef;
  isLoading: boolean;
  services: Array<ResourceStatus>;
  total?: number;
}

export const useHostTooltipContent = ({ name }): UseHostTooltipContentState => {
  const { elementRef, elements, isLoading, total } =
    useInfiniteScrollListing<ResourceStatus>({
      customQueryParameters: [
        { name: 'types', value: ['service'] },
        { name: 'statuses', value: ['WARNING', 'CRITICAL'] }
      ],
      endpoint: resourcesEndpoint,
      limit: 10,
      pageAtom: tooltipPageAtom,
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
    services: serviceswithProblems,
    total
  };
};
