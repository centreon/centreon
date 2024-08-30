import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { getWidgetEndpoint } from '../../../utils';
import { buildResourcesEndpoint } from '../api/endpoints';
import { PanelOptions } from '../models';

import { DisplayType, NamedEntity, ResourceListing } from './models';
import { formatRessources } from './utils';

interface LoadResourcesProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  displayResources: 'all' | 'withTicket' | 'withoutTicket';
  displayType: DisplayType;
  hostSeverities: Array<NamedEntity>;
  isDownHostHidden: boolean;
  isUnreachableHostHidden: boolean;
  limit?: number;
  page: number | undefined;
  provider?: { id: number; name: string };
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  serviceSeverities: Array<NamedEntity>;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statusTypes: Array<'hard' | 'soft'>;
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
  widgetPrefixQuery,
  statusTypes,
  hostSeverities,
  serviceSeverities,
  isDownHostHidden,
  isUnreachableHostHidden,
  displayResources,
  provider
}: LoadResourcesProps): LoadResources => {
  const sort = { [sortField as string]: sortOrder };

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const { data, isLoading } = useFetchQuery<ResourceListing>({
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: buildResourcesEndpoint({
          displayResources,
          hostSeverities,
          isDownHostHidden,
          isUnreachableHostHidden,
          limit: limit || 10,
          page: page || 1,
          provider,
          resources,
          serviceSeverities,
          sort: sort || { status_severity_code: SortOrder.Desc },
          states,
          statusTypes,
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
      JSON.stringify(statusTypes),
      JSON.stringify(serviceSeverities),
      JSON.stringify(hostSeverities),
      displayResources,
      sortField,
      sortOrder,
      limit,
      JSON.stringify(resources),
      page,
      refreshCount,
      isDownHostHidden,
      isUnreachableHostHidden,
      displayResources
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
