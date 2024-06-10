import { useMemo } from 'react';

import { equals, gt, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { useTheme } from '@mui/material';

import {
  HeatMap,
  ListingModel,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import {
  buildResourcesEndpoint,
  hostsEndpoint,
  resourcesEndpoint
} from '../api/endpoints';
import { NoResourcesFound } from '../../../NoResourcesFound';
import {
  labelNoHostsFound,
  labelNoServicesFound
} from '../../../translatedLabels';
import { getWidgetEndpoint } from '../../../utils';

import { ResourceData, ResourceStatus, StatusGridProps } from './models';
import Tile from './Tile';
import HeatMapSkeleton from './LoadingSkeleton';
import { getColor } from './utils';
import Tooltip from './Tooltip/Tooltip';

const StatusGrid = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  id: widgetId,
  dashboardId,
  playlistHash,
  widgetPrefixQuery
}: Omit<StatusGridProps, 'store' | 'queryClient'>): JSX.Element => {
  const theme = useTheme();
  const { t } = useTranslation();

  const {
    refreshInterval,
    resourceType,
    sortBy,
    statuses,
    tiles,
    refreshIntervalCustom
  } = panelOptions;
  const { resources } = panelData;

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const { data, isLoading } = useFetchQuery<ListingModel<ResourceStatus>>({
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint: buildResourcesEndpoint({
          baseEndpoint: equals(resourceType, 'host')
            ? hostsEndpoint
            : resourcesEndpoint,
          limit: tiles,
          resources,
          sortBy,
          states: [],
          statuses,
          type: resourceType
        }),
        isOnPublicPage,
        playlistHash,
        widgetId
      }),
    getQueryKey: () => [
      widgetPrefixQuery,
      'statusgrid',
      resourceType,
      JSON.stringify(statuses),
      sortBy,
      tiles,
      JSON.stringify(resources),
      refreshCount
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    },
    useLongCache: true
  });

  const hasMoreResources = gt(data?.meta.total || 0, tiles);

  const resourceTiles = useMemo(
    () =>
      (data?.result || []).map(
        ({
          id,
          uuid,
          name,
          parent,
          status,
          is_in_downtime,
          is_acknowledged,
          information,
          links
        }) => {
          const statusColor = getColor({
            is_acknowledged,
            is_in_downtime,
            severityCode: status?.severity_code,
            theme
          });

          return {
            backgroundColor: statusColor,
            data: {
              acknowledgementEndpoint: links?.endpoints.acknowledgement,
              downtimeEndpoint: links?.endpoints.downtime,
              id,
              information,
              is_acknowledged,
              is_in_downtime,
              metricsEndpoint: links?.endpoints.metrics,
              name,
              parentId: parent?.id,
              parentName: parent?.name,
              parentStatus: parent?.status?.severity_code,
              status: status?.severity_code,
              statusName: status?.name.toLocaleLowerCase(),
              uuid
            },
            id: uuid
          };
        }
      ),
    [theme, data]
  );

  if (isLoading && isNil(data)) {
    return <HeatMapSkeleton />;
  }

  if (equals(data?.meta.total, 0)) {
    return (
      <NoResourcesFound
        label={
          equals(resourceType, 'host')
            ? t(labelNoHostsFound)
            : t(labelNoServicesFound)
        }
      />
    );
  }

  const seeMoreTile = hasMoreResources
    ? {
        backgroundColor: theme.palette.background.paper,
        data: null,
        id: 'see-more'
      }
    : undefined;

  return (
    <HeatMap<ResourceData | null>
      displayTooltipCondition={(resourceData) => !isNil(resourceData)}
      tiles={[...resourceTiles, seeMoreTile].filter((v) => v)}
      tooltipContent={isOnPublicPage ? undefined : Tooltip(resourceType)}
    >
      {({ isSmallestSize, data: resourceData }) => (
        <Tile
          data={resourceData}
          isSmallestSize={isSmallestSize}
          resources={resources}
          statuses={statuses}
          type={resourceType}
        />
      )}
    </HeatMap>
  );
};

export default StatusGrid;
