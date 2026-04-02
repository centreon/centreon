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
  cursor: string | null;
  displayType: DisplayType;
  hostSeverities: Array<NamedEntity>;
  limit?: number;
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
  cursor,
  limit,
  sortField,
  sortOrder,
  playlistHash,
  dashboardId,
  id,
  widgetPrefixQuery,
  statusTypes,
  hostSeverities,
  serviceSeverities
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
          cursor,
          hostSeverities,
          limit: limit || 10,
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
          cursor,
          limit: limit || 10,
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
      cursor,
      refreshCount,
      isDownHostHidden,
      isUnreachableHostHidden,
      id
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
