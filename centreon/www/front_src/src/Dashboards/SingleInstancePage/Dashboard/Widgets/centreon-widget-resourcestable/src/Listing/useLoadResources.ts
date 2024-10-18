import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import {
  type CommonWidgetProps,
  type Resource,
  SortOrder
} from '../../../models';
import { getWidgetEndpoint } from '../../../utils';
import { buildResourcesEndpoint } from '../api/endpoints';
import type { PanelOptions } from '../models';

import type { DisplayType, NamedEntity, ResourceListing } from './models';
import { formatRessources } from './utils';

interface LoadResourcesProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  displayResources: 'withTicket' | 'withoutTicket';
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
  isOpenTicketEnabled?: boolean;
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
  provider,
  isOpenTicketEnabled
}: LoadResourcesProps): LoadResources => {
  const sort = sortField
    ? { [sortField as string]: sortOrder }
    : { status_severity_code: SortOrder.Desc };

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

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
          statusTypes,
          statuses,
          type: displayType,
          ...(isOpenTicketEnabled
            ? {
                isDownHostHidden,
                isUnreachableHostHidden,
                provider,
                displayResources
              }
            : {})
        }),
        extraQueryParameters: {
          limit: limit || 10,
          page: page || 1,
          sort_by: sort
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
      provider?.id,
      sortField,
      sortOrder,
      limit,
      JSON.stringify(resources),
      page,
      refreshCount,
      isDownHostHidden,
      isUnreachableHostHidden
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
