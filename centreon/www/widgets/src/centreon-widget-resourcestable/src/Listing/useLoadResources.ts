import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { buildResourcesEndpoint } from '../api/endpoints';
import { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { PanelOptions } from '../models';
import { getWidgetEndpoint } from '../../../utils';

import { formatRessources } from './utils';
import { DisplayType, ResourceListing } from './models';

interface LoadResourcesProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  displayType: DisplayType;
  limit?: number;
  page: number | undefined;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statuses: Array<string>;
}

interface LoadResources {
  data?: ResourceListing;
  isLoading: boolean;
}

const useLoadResources = ({
  resources,
  states,
  statuses,
  displayType,
  refreshCount,
  refreshIntervalToUse,
  page,
  limit,
  sortField,
  sortOrder,
  playlistHash,
  dashboardId,
  id,
  widgetPrefixQuery
}: LoadResourcesProps): LoadResources => {
  const sort = { [sortField as string]: sortOrder };

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const { data, isLoading } = useFetchQuery<ResourceListing>({
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: buildResourcesEndpoint({
          limit: limit || 10,
          page: page || 1,
          resources,
          sort: sort || { status_severity_code: SortOrder.Desc },
          states,
          statuses,
          type: displayType
        }),
        extraQueryParameters: {
          limit: limit || 10,
          page: page || 1,
          sort_by: sort || { status_severity_code: SortOrder.Desc }
        },
        isOnPublicPage,
        playlistHash,
        widgetId: id
      }),
    getQueryKey: () => [
      widgetPrefixQuery,
      'resourcestable',
      displayType,
      JSON.stringify(states),
      JSON.stringify(statuses),
      sortField,
      sortOrder,
      limit,
      JSON.stringify(resources),
      page,
      refreshCount
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    },
    useLongCache: true
  });

  return { data: formatRessources({ data, displayType }), isLoading };
};

export default useLoadResources;
