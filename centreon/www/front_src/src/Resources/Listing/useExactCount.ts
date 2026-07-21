import type { SelectEntry } from '@centreon/ui';
import { useFetchQuery } from '@centreon/ui';
import { isResourceStatusFullSearchEnabledAtom } from '@centreon/ui-context';

import { useAtomValue, useSetAtom } from 'jotai';
import { mergeRight, prop } from 'ramda';
import { useEffect } from 'react';

import { getCriteriaValueDerivedAtom } from '../Filter/filterAtoms';
import { exactCountAtom, exactCountLoadingAtom } from './ApproximateCountBadge';
import { buildCountEndpoint } from './api/endpoint';
import useGetCriteriaName from './useLoadResources/useGetCriteriaName';
import { getSearch } from './useLoadResources/utils';

interface CountResponse {
  count: number;
  is_approximate: boolean;
}

interface UseExactCount {
  requestExactCount: () => void;
}

const useExactCount = (): UseExactCount => {
  const setExactCount = useSetAtom(exactCountAtom);
  const setExactCountLoading = useSetAtom(exactCountLoadingAtom);
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);
  const isResourceStatusFullSearchEnabled = useAtomValue(
    isResourceStatusFullSearchEnabledAtom
  );
  const { getCriteriaNames } = useGetCriteriaName();

  const getCriteriaIds = (name: string): Array<string | number> | undefined => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return criteriaValue?.map(prop('id'));
  };

  const getCriteriaLevels = (name: string): Array<number> => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return (criteriaValue?.map(prop('name')) ?? []).map(Number);
  };

  const getEndpoint = (): string => {
    const names = getCriteriaNames('names');
    const parentNames = getCriteriaNames('parent_names');

    const search = mergeRight(
      getSearch({
        isResourceStatusFullSearchEnabled,
        searchCriteria: getCriteriaValue('search')
      }) || {},
      {
        conditions: [
          ...names.map((name) => ({
            field: 'name',
            values: { $rg: name }
          })),
          ...parentNames.map((name) => ({
            field: 'parent_name',
            values: { $rg: name }
          }))
        ]
      }
    );

    return buildCountEndpoint({
      apiFormat: 'Standard',
      hostCategories: getCriteriaNames('host_categories'),
      hostGroups: getCriteriaNames('host_groups'),
      hostSeverities: getCriteriaNames('host_severities'),
      hostSeverityLevels: getCriteriaLevels('host_severity_levels'),
      monitoringServers: getCriteriaNames('monitoring_servers'),
      resourceTypes: getCriteriaIds('resource_types') as Array<string>,
      search,
      serviceCategories: getCriteriaNames('service_categories'),
      serviceGroups: getCriteriaNames('service_groups'),
      serviceSeverities: getCriteriaNames('service_severities'),
      serviceSeverityLevels: getCriteriaLevels('service_severity_levels'),
      states: getCriteriaIds('states') as Array<string>,
      statuses: getCriteriaIds('statuses') as Array<string>,
      statusTypes: getCriteriaIds('status_types') as Array<string>
    });
  };

  const { data, isFetching, refetch } = useFetchQuery<CountResponse>({
    getEndpoint,
    getQueryKey: () => ['exactCount', getEndpoint()],
    queryOptions: {
      enabled: false,
      suspense: false
    }
  });

  useEffect(() => {
    setExactCountLoading(isFetching);
  }, [isFetching]);

  useEffect(() => {
    if (data?.count !== undefined) {
      setExactCount(data.count);
    }
  }, [data]);

  const requestExactCount = (): void => {
    refetch();
  };

  return { requestExactCount };
};

export default useExactCount;
