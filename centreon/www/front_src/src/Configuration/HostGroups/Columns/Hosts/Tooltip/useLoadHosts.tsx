import { flatten } from 'ramda';

import { useInfiniteScrollListing } from '@centreon/ui';
import { hostListEndpoint } from '../../../api/endpoints';
import { tooltipPageAtom } from '../../../atoms';
import { NamedEntity } from '../../../models';

interface UseLoadHostsProps {
  enabled: boolean;
  hostGroupName: string;
}

interface UseLoadHostsState {
  elementRef;
  elements: Array<NamedEntity>;
  isLoading: boolean;
  total?: number;
}

export const useLoadHosts = ({
  enabled,
  hostGroupName
}: UseLoadHostsProps): UseLoadHostsState => {
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

  const { elementRef, elements, isLoading, total } =
    useInfiniteScrollListing<NamedEntity>({
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
