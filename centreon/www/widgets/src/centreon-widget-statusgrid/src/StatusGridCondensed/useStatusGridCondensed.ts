import { useFetchQuery, useRefreshInterval } from '@centreon/ui';

import { StatusGridProps } from '../StatusGridStandard/models';
import { StatusType } from '../../../models';

import { buildStatusesEndpoint } from './api/endpoints';

interface UseStatusGridCondensedState {
  data?: StatusType;
  isLoading: boolean;
}

export const useStatusGridCondensed = ({
  panelOptions,
  panelData,
  refreshCount,
  globalRefreshInterval
}: Pick<
  StatusGridProps,
  'panelOptions' | 'panelData' | 'refreshCount' | 'globalRefreshInterval'
>): UseStatusGridCondensedState => {
  const { refreshInterval, resourceType, statuses, refreshIntervalCustom } =
    panelOptions;
  const { resources } = panelData;

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const { data, isLoading } = useFetchQuery<StatusType>({
    getEndpoint: () =>
      buildStatusesEndpoint({
        resources,
        statuses,
        type: resourceType
      }),
    getQueryKey: () => [
      'statusgrid',
      'condensed',
      resourceType,
      JSON.stringify(statuses),
      JSON.stringify(resources),
      refreshCount
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse
    }
  });

  return {
    data,
    isLoading
  };
};
