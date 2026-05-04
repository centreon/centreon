import { useFetchQuery } from '@centreon/ui';

import { useAtomValue } from 'jotai';

import {
  type CommonWidgetProps,
  type Resource,
  SortOrder
} from '../../../models';
import { getWidgetEndpoint } from '../../../utils';
import { buildResourcesEndpoint } from '../api/endpoints';
import { isOnPublicPageLocalAtom, openTicketContextAtom } from '../atom';
import type { PanelOptions } from '../models';
import type { DisplayType, NamedEntity, ResourceListing } from './models';
import { formatRessources } from './utils';

interface LoadResourcesProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  displayType: DisplayType;
  hostSeverities: Array<NamedEntity>;
  limit?: number;
  page: number | undefined;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  serviceSeverities: Array<NamedEntity>;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statusTypes: Array<'hard' | 'soft'>;
  statuses: Array<string>;
  isInViewport: boolean;
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
  isInViewport
}: LoadResourcesProps): LoadResources => {
  const sort = { [sortField as string]: sortOrder };

  const isOnPublicPage = useAtomValue(isOnPublicPageLocalAtom);
  const {
    displayResources,
    isDownHostHidden,
    isOpenTicketEnabled,
    isUnreachableHostHidden,
    provider
  } = useAtomValue(openTicketContextAtom);

  const { data, isLoading } = useFetchQuery<ResourceListing>({
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: buildResourcesEndpoint({
          hostSeverities,
          limit: limit || 10,
          page: page || 1,
          resources,
          serviceSeverities,
          sort: sort || { status_severity_code: SortOrder.Desc },
          states,
          statuses,
          statusTypes,
          type: displayType,
          ...(isOpenTicketEnabled
            ? {
                displayResources,
                isDownHostHidden,
                isUnreachableHostHidden,
                provider
              }
            : {})
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
      isOpenTicketEnabled,
      displayType,
      JSON.stringify(states),
      JSON.stringify(statuses),
      JSON.stringify(statusTypes),
      JSON.stringify(serviceSeverities),
      JSON.stringify(hostSeverities),
      displayResources,
      provider?.id,
      sortField,
      sortOrder,
      limit,
      JSON.stringify(resources),
      page,
      refreshCount,
      isDownHostHidden,
      isUnreachableHostHidden,
      id
    ],
    queryOptions: {
      enabled: isInViewport ?? true,
      refetchInterval: refreshIntervalToUse,
      suspense: false
    },
    useLongCache: true
  });

  return { data: formatRessources({ data, displayType }), isLoading };
};

export default useLoadResources;
