import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { equals, gt, isNil, last, pipe, pluck, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useTheme } from '@mui/material';

import {
  HeatMap,
  type ListingModel,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { NoResourcesFound } from '../../../NoResourcesFound';
import {
  labelNoBAFound,
  labelNoHostsFound,
  labelNoKPIFound,
  labelNoServicesFound
} from '../../../translatedLabels';
import {
  getStatusesByResourcesAndResourceType,
  getWidgetEndpoint
} from '../../../utils';
import {
  buildBAsEndpoint,
  buildResourcesEndpoint,
  resourcesEndpoint
} from '../api/endpoints';

import HeatMapSkeleton from './LoadingSkeleton';
import Tile from './Tile';
import Tooltip from './Tooltip/Tooltip';
import {
  IndicatorType,
  type ResourceData,
  type ResourceStatus,
  type StatusGridProps
} from './models';
import { getColor } from './utils';

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

  const lastSelectedResourceType = pipe(
    pluck('resourceType'),
    reject((type) => equals(type, '')),
    last
  )(panelData?.resources);

  const isBVResourceType = equals(lastSelectedResourceType, 'business-view');
  const isBAResourceType = equals(
    lastSelectedResourceType,
    'business-activity'
  );

  const statusesToUse = getStatusesByResourcesAndResourceType({
    resources,
    resourceType,
    statuses
  });

  const getLabelNoResourceFound = (): string => {
    if (isBAResourceType) {
      return t(labelNoKPIFound);
    }

    if (isBVResourceType) {
      return t(labelNoBAFound);
    }

    if (equals(resourceType, 'host')) {
      return t(labelNoHostsFound);
    }

    return t(labelNoServicesFound);
  };

  const { data, isLoading } = useFetchQuery<ListingModel<ResourceStatus>>({
    getEndpoint: () =>
      getWidgetEndpoint({
        dashboardId,
        defaultEndpoint:
          isBVResourceType || isBAResourceType
            ? buildBAsEndpoint({
                limit: tiles,
                resources: last(panelData?.resources)?.resources,
                sortBy,
                statuses: statusesToUse,
                type: lastSelectedResourceType
              })
            : buildResourcesEndpoint({
                baseEndpoint: resourcesEndpoint,
                limit: tiles,
                resources,
                sortBy,
                states: [],
                statuses: statusesToUse,
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
          resource_name,
          parent,
          status,
          is_in_downtime,
          is_acknowledged,
          is_in_flapping,
          information,
          links,
          type,
          resource = null,
          business_activity = null
        }) => {
          const statusColor = getColor({
            severityCode: status?.severity_code,
            theme
          });

          return {
            backgroundColor: statusColor,
            data: {
              acknowledgementEndpoint: links?.endpoints.acknowledgement,
              businessActivity: business_activity?.name,
              downtimeEndpoint: links?.endpoints.downtime,
              id,
              information,
              is_acknowledged,
              is_in_flapping,
              is_in_downtime,
              metricsEndpoint: links?.endpoints.metrics,
              name: name || resource_name,
              parentId: parent?.id || resource?.parent_id,
              parentName: parent?.name || resource?.parent_name,
              parentStatus: parent?.status?.severity_code,
              resourceId: resource?.id,
              status: status?.severity_code,
              statusName: status?.name.toLocaleLowerCase(),
              type: isBVResourceType ? IndicatorType.BusinessActivity : type,
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
    return <NoResourcesFound label={getLabelNoResourceFound()} />;
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
      tooltipContent={isOnPublicPage ? undefined : Tooltip()}
    >
      {({ isSmallestSize, data: resourceData, tileSize, isMediumSize }) => (
        <Tile
          data={resourceData}
          isBAResourceType={isBVResourceType || isBAResourceType}
          isSmallestSize={isSmallestSize}
          resources={resources}
          statuses={statuses}
          type={resourceData?.type}
          tileSize={tileSize}
          isMediumSize={isMediumSize}
        />
      )}
    </HeatMap>
  );
};

export default StatusGrid;
