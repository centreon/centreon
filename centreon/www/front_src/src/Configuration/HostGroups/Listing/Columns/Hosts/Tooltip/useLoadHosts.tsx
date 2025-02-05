import { flatten } from 'ramda';

import { useInfiniteScrollListing } from '@centreon/ui';
import { hostListEndpoint } from '../../../../api/endpoints';
import { tooltipPageAtom } from '../../../../atoms';

interface UseLoadHostsState {
  elementRef;
  elements;
  isLoading: boolean;
  total?: number;
}

export const useLoadHosts = ({
  enabled,
  hostGroupName
}: { enabled: boolean; hostGroupName: string }): UseLoadHostsState => {
  const searchConditions = [
    {
      field: 'group.name',
      values: {
        $in: [hostGroupName]
      }
    },
    {
      field: 'is_activated',
      values: {
        $eq: enabled
      }
    }
  ];

  const { elementRef, elements, isLoading, total } = useInfiniteScrollListing({
    enabled: true,
    endpoint: hostListEndpoint,
    limit: 10,
    pageAtom: tooltipPageAtom,
    parameters: {
      search: {
        conditions: flatten(searchConditions)
      },
      sort: { status: 'DESC' }
    },
    queryKeyName: `hosts_${hostGroupName}_${enabled}`,
    suspense: false
  });

  return {
    elementRef,
    elements,
    isLoading,
    total
  };
};
