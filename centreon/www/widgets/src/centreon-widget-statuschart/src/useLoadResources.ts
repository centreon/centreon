import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';

import { useTheme } from '@mui/material';

import { useFetchQuery } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { Resource } from '../../models';
import { getWidgetEndpoint } from '../../utils';

import { buildResourcesEndpoint } from './api/endpoint';
import { StatusChartProps, StatusType } from './models';
import { FormattedResponse, formatResponse } from './utils';

interface LoadResourcesProps
  extends Pick<
    StatusChartProps,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resourceType: 'host' | 'service';
  resources: Array<Resource>;
}

interface LoadResources {
  data?: Array<FormattedResponse>;
  isLoading: boolean;
}

const useLoadResources = ({
  resources,
  refreshCount,
  refreshIntervalToUse,
  resourceType,
  id,
  dashboardId,
  playlistHash,
  widgetPrefixQuery
}: LoadResourcesProps): LoadResources => {
  const theme = useTheme();

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const { data: statuses, isLoading } = useFetchQuery<StatusType>({
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: buildResourcesEndpoint({
          resources,
          type: resourceType
        }),
        extraQueryParameters: { resource_type: resourceType as string },
        isOnPublicPage,
        playlistHash,
        widgetId: id
      }),
    getQueryKey: () => [
      widgetPrefixQuery,
      'statusChart',
      JSON.stringify(resources),
      refreshCount,
      resourceType
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    },
    useLongCache: true
  });

  return {
    data: isNil(statuses) ? statuses : formatResponse({ statuses, theme }),
    isLoading
  };
};

export default useLoadResources;
