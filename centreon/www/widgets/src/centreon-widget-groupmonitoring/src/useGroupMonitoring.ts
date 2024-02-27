import { useEffect, useRef } from 'react';

import { inc, isEmpty, pluck } from 'ramda';

import {
  ListingModel,
  buildListingEndpoint,
  useDeepCompare,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';

import { SortOrder } from '../../models';

import { Group, WidgetProps } from './models';
import { getEndpoint } from './api/endpoints';
import { groupsDecoder } from './api/decoders';
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
  listing?: ListingModel<Group>;
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
  refreshCount
}: Omit<WidgetProps, 'store'>): UseGroupMonitoringState => {
  const isFirstMountRef = useRef(true);
  const limitRef = useRef(10);

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval: panelOptions.refreshInterval,
    refreshIntervalCustom: panelOptions.refreshIntervalCustom
  });

  const resource = panelData.resources[0];
  const hasResourceTypeDefined = !!resource?.resourceType;
  const hasResourcesDefined = !isEmpty(resource?.resources);
  const { limit, page, sortField, sortOrder } = panelOptions;

  const limitToUse = limit || 10;
  const pageToUse = page || 0;
  const sortFieldToUse = sortField || 'name';
  const sortOrderToUse = sortOrder || SortOrder.Asc;

  const { data, isLoading } = useFetchQuery<ListingModel<Group>>({
    decoder: groupsDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
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
    getQueryKey: () => [
      'groupmonitoring',
      resource?.resourceType,
      resource?.resources.length,
      limitToUse,
      pageToUse,
      sortFieldToUse,
      sortOrderToUse,
      refreshCount
    ],
    queryOptions: {
      enabled: hasResourceTypeDefined,
      refetchInterval: !isFromPreview && refreshIntervalToUse,
      suspense: false
    }
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

  return {
    changeLimit,
    changePage,
    changeSort,
    groupType: resource?.resourceType || '',
    groupTypeName: getResourceTypeName(resource?.resourceType),
    hasResourceTypeDefined,
    isLoading,
    limit: limitToUse,
    listing: data,
    page: pageToUse,
    sortField: sortFieldToUse,
    sortOrder: sortOrderToUse
  };
};
