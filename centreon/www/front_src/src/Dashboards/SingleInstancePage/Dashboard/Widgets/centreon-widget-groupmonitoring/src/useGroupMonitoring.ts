import { useEffect, useRef } from 'react';

import { useAtomValue } from 'jotai';
import { inc, isEmpty, pluck } from 'ramda';

import {
  ListingModel,
  buildListingEndpoint,
  useDeepCompare,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { SortOrder } from '../../models';
import { getWidgetEndpoint } from '../../utils';

import { groupsDecoder } from './api/decoders';
import { getEndpoint } from './api/endpoints';
import { FormattedGroup, Group, WidgetProps } from './models';
import { getResourceTypeName } from './utils';

interface UseGroupMonitoringState {
  changeLimit: (newLimit: number) => void;
  changePage: (newPage: number) => void;
  changeSort: (sortParameters: {
    sortField: string;
    sortOrder: SortOrder;
  }) => void;
  groupType: string;
  groupTypeName: string;
  hasResourceTypeDefined: boolean;
  isLoading: boolean;
  limit: number;
  listing?: ListingModel<FormattedGroup>;
  page: number;
  sortField: string;
  sortOrder: SortOrder;
}

export const useGroupMonitoring = ({
  globalRefreshInterval,
  panelOptions,
  panelData,
  isFromPreview,
  setPanelOptions,
  refreshCount,
  dashboardId,
  id,
  playlistHash,
  widgetPrefixQuery
}: WidgetProps): UseGroupMonitoringState => {
  const isFirstMountRef = useRef(true);
  const limitRef = useRef(10);

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval: panelOptions.refreshInterval,
    refreshIntervalCustom: panelOptions.refreshIntervalCustom
  });

  const resource = panelData.resources[0];
  const hasResourceTypeDefined = !!resource?.resourceType;
  const hasResourcesDefined = !isEmpty(resource?.resources);
  const { limit, page, sortField, sortOrder, statuses } = panelOptions;

  const limitToUse = limit || 10;
  const pageToUse = page || 0;
  const sortFieldToUse = sortField || 'name';
  const sortOrderToUse = sortOrder || SortOrder.Asc;

  const key = [
    widgetPrefixQuery,
    'groupmonitoring',
    resource?.resourceType,
    JSON.stringify(resource?.resources),
    JSON.stringify(statuses),
    limitToUse,
    pageToUse,
    sortFieldToUse,
    sortOrderToUse,
    refreshCount
  ];

  const { data } = useFetchQuery<ListingModel<Group>>({
    decoder: groupsDecoder,
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: buildListingEndpoint({
          baseEndpoint: getEndpoint(resource?.resourceType),
          customQueryParameters: [
            {
              name: 'show_service',
              value: true
            },
            {
              name: 'show_host',
              value: true
            }
          ],
          parameters: {
            limit: limitToUse,
            page: inc(pageToUse),
            search: hasResourcesDefined
              ? {
                  lists: [
                    {
                      field: 'name',
                      values: pluck('name', resource?.resources)
                    }
                  ]
                }
              : undefined,
            sort: {
              [sortFieldToUse]: sortOrderToUse.toUpperCase()
            }
          }
        }),
        isOnPublicPage,
        playlistHash,
        widgetId: id
      }),
    getQueryKey: () => key,
    queryOptions: {
      enabled: hasResourceTypeDefined,
      refetchInterval: !isFromPreview ? refreshIntervalToUse : false,
      suspense: false
    },
    useLongCache: true
  });

  const changeLimit = (newLimit: number): void => {
    limitRef.current = newLimit;
    setPanelOptions?.({ limit: newLimit });
  };

  const changePage = (newPage: number): void => {
    setPanelOptions?.({ limit: limitRef.current, page: newPage });
  };

  const changeSort = (sortParameters: {
    sortField: string;
    sortOrder: SortOrder;
  }): void => {
    setPanelOptions?.(sortParameters);
  };

  useEffect(
    () => {
      if (isFirstMountRef.current) {
        isFirstMountRef.current = false;

        return;
      }

      changePage(0);
    },
    useDeepCompare([resource?.resources])
  );

  const formattedListing: ListingModel<FormattedGroup> | undefined = data && {
    ...data,
    result: data.result.map((hosts) => ({
      ...hosts,
      statuses
    }))
  };

  return {
    changeLimit,
    changePage,
    changeSort,
    groupType: resource?.resourceType || '',
    groupTypeName: getResourceTypeName(resource?.resourceType),
    hasResourceTypeDefined,
    limit: limitToUse,
    listing: formattedListing,
    page: pageToUse,
    sortField: sortFieldToUse,
    sortOrder: sortOrderToUse
  };
};
