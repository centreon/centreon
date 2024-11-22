import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { filter, intersection, isNil, map, pipe, toUpper } from 'ramda';

import { SeverityCode, useFetchQuery, useRefreshInterval } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { SeverityStatus, StatusDetail, StatusType } from '../../../models';
import {
  formatStatus,
  getStatusNameByStatusSeverityandResourceType,
  getWidgetEndpoint,
  severityCodeBySeverityStatus
} from '../../../utils';
import { StatusGridProps } from '../StatusGridStandard/models';
import { buildCondensedViewEndpoint } from '../api/endpoints';

import { getStatusesEndpoint } from './api/endpoints';
import { getStatusNamesPerResourceType } from './utils';

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
  globalRefreshInterval,
  playlistHash,
  dashboardId,
  id,
  widgetPrefixQuery,
  isBAResourceType,
  isBVResourceType,
  lastSelectedResourceType
}: Pick<
  StatusGridProps,
  | 'panelOptions'
  | 'panelData'
  | 'refreshCount'
  | 'globalRefreshInterval'
  | 'dashboardId'
  | 'id'
  | 'playlistHash'
  | 'widgetPrefixQuery'
> & {
  isBAResourceType;
  isBVResourceType;
  lastSelectedResourceType;
}): UseStatusGridCondensedState => {
  const { refreshInterval, resourceType, statuses, refreshIntervalCustom } =
    panelOptions;
  const { resources } = panelData;

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const formattedStatuses = formatStatus(statuses);

  const resourceTypeToUse =
    isBVResourceType || isBAResourceType
      ? lastSelectedResourceType
      : resourceType;

  const statusesToUse = pipe(
    getStatusNamesPerResourceType,
    map(toUpper),
    intersection(formattedStatuses)
  )(resourceTypeToUse);

  const baseEndpoint = getStatusesEndpoint(resourceTypeToUse);

  const { data, isLoading } = useFetchQuery<StatusType>({
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: buildCondensedViewEndpoint({
          baseEndpoint,
          resources,
          statuses: statusesToUse,
          type: resourceType
        }),
        isOnPublicPage,
        playlistHash,
        widgetId: id
      }),
    getQueryKey: () => [
      widgetPrefixQuery,
      'statusgrid',
      'condensed',
      resourceType,
      JSON.stringify(statuses),
      JSON.stringify(resources),
      refreshCount
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    },
    useLongCache: true
  });

  const statusesToDisplay = useMemo(
    () =>
      pipe<[list: ReadonlyArray<SeverityStatus>], Array<FormattedStatus>>(
        map((severityStatus: SeverityStatus) => {
          const status = getStatusNameByStatusSeverityandResourceType({
            resourceType: resourceTypeToUse,
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
    hasData: !isNil(data),
    isLoading,
    statusesToDisplay,
    total: data?.total
  };
};
