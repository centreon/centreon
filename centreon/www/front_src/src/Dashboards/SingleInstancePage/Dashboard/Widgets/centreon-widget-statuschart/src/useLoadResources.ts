import { useTheme } from '@mui/material';

import { useFetchQuery } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';

import { Resource } from '../../models';
import { getWidgetEndpoint } from '../../utils';
import { buildResourcesEndpoint } from './api/endpoint';
import { StateSelection, StatusChartProps, StatusType } from './models';
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
  stateList: Array<StateSelection>;
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
  widgetPrefixQuery,
  stateList
}: LoadResourcesProps): LoadResources => {
  const theme = useTheme();

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const widgetEndpoint = getWidgetEndpoint({
    dashboardId,
    defaultEndpoint: buildResourcesEndpoint({
      resources,
      type: resourceType,
      stateList
    }),
    extraQueryParameters: { resource_type: resourceType as string },
    isOnPublicPage,
    playlistHash,
    widgetId: id
  });

  const { data: statuses, isLoading } = useFetchQuery<StatusType>({
    getEndpoint: () => widgetEndpoint,
    getQueryKey: () => [
      JSON.stringify(stateList),
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
