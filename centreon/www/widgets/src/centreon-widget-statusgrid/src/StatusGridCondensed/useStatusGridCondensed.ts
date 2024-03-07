import { useMemo } from 'react';

import { filter, isNil, map, pipe } from 'ramda';

import { SeverityCode, useFetchQuery, useRefreshInterval } from '@centreon/ui';

import { StatusGridProps } from '../StatusGridStandard/models';
import { SeverityStatus, StatusDetail, StatusType } from '../../../models';
import {
  getStatusNameByStatusSeverityandResourceType,
  severityCodeBySeverityStatus
} from '../../../utils';
import { buildResourcesEndpoint } from '../api/endpoints';

import { getStatusesEndpoint } from './api/endpoints';

interface FormattedStatus {
  count: StatusDetail;
  label: string;
  severityCode: SeverityCode;
}

interface UseStatusGridCondensedState {
  hasData: boolean;
  isLoading: boolean;
  statusesToDisplay: Array<FormattedStatus>;
  total?: number;
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
      buildResourcesEndpoint({
        baseEndpoint: getStatusesEndpoint(resourceType),
        page: undefined,
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

  const statusesToDisplay = useMemo(
    () =>
      pipe<[list: ReadonlyArray<SeverityStatus>], Array<FormattedStatus>>(
        map((severityStatus: SeverityStatus) => {
          const status = getStatusNameByStatusSeverityandResourceType({
            resourceType,
            status: severityStatus
          });
          const count = data?.[status];

          if (!count) {
            return null;
          }

          const severityCode = severityCodeBySeverityStatus[severityStatus];

          return {
            count,
            label: status,
            severityCode
          };
        }),
        filter((status) => !!status)
      )(statuses) as Array<FormattedStatus>,
    [resourceType, statuses, data]
  );

  return {
    hasData: isNil(data),
    isLoading,
    statusesToDisplay,
    total: data?.total
  };
};
