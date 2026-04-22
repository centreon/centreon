import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';
import {
  isResourceStatusFullSearchEnabledAtom,
  refreshIntervalAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';

import { countResourcesEndpoint } from '../../api/endpoint';
import { enabledAutorefreshAtom } from '../listingAtoms';
import useGetCriteriaName from './useGetCriteriaName';
import { getSearch } from './utils';

interface Count {
  count: number;
}

interface UseResourceCount {
  count?: number;
  isLoading: boolean;
}

const countThreshold = 1000;

const useResourceCount = (): UseResourceCount => {
  const { getCriteriaNames, getCriteriaIds, getCriteriaValue } =
    useGetCriteriaName();
  const isResourceStatusFullSearchEnabled = useAtomValue(
    isResourceStatusFullSearchEnabledAtom
  );
  const refreshInterval = useAtomValue(refreshIntervalAtom);
  const enabledAutoRefresh = useAtomValue(enabledAutorefreshAtom);

  const getCountEndpoint = (): string => {
    const names = getCriteriaNames('names');
    const parentNames = getCriteriaNames('parent_names');

    const customQueryParameters = [
      {
        name: 'host_category_names',
        value: getCriteriaNames('host_categories')
      },
      {
        name: 'service_category_names',
        value: getCriteriaNames('service_categories')
      },
      { name: 'hostgroup_names', value: getCriteriaNames('host_groups') },
      { name: 'servicegroup_names', value: getCriteriaNames('service_groups') },
      {
        name: 'monitoring_server_names',
        value: getCriteriaNames('monitoring_servers')
      },
      {
        name: 'service_severity_names',
        value: getCriteriaNames('service_severities')
      },
      {
        name: 'host_severity_names',
        value: getCriteriaNames('host_severities')
      },
      { name: 'states', value: getCriteriaIds('states') },
      { name: 'types', value: getCriteriaIds('resource_types') },
      {
        name: 'statuses',
        value: (getCriteriaIds('statuses') as Array<string> | undefined)?.map(
          (s) => s.toUpperCase()
        )
      }
    ];

    const parameters = {
      search: {
        ...(getSearch({
          isResourceStatusFullSearchEnabled,
          searchCriteria: getCriteriaValue('search')
        }) ?? {}),
        conditions: [
          ...names.map((name) => ({ field: 'name', values: { $rg: name } })),
          ...parentNames.map((name) => ({
            field: 'parent_name',
            values: { $rg: name }
          }))
        ]
      }
    };

    return buildListingEndpoint({
      baseEndpoint: countResourcesEndpoint,
      customQueryParameters: [
        ...customQueryParameters,
        { name: 'all_pages', value: true }
      ],
      parameters
    });
  };

  const endpoint = getCountEndpoint();

  const { data, isLoading } = useFetchQuery<Count>({
    getEndpoint: () => endpoint,
    getQueryKey: () => ['resourceCount', endpoint],
    queryOptions: {
      refetchInterval: enabledAutoRefresh ? refreshInterval * 1000 : false,
      suspense: false
    }
  });

  return {
    count: data?.count,
    isLoading
  };
};

export { countThreshold };
export default useResourceCount;
