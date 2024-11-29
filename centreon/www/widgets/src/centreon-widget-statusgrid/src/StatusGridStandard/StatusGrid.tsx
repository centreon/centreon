import { useMemo } from 'react';

import { equals, gt, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useTheme } from '@mui/material';

import {
  HeatMap,
  ListingModel,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';

import { NoResourcesFound } from '../../../NoResourcesFound';
import {
  labelNoHostsFound,
  labelNoServicesFound
} from '../../../translatedLabels';
import { buildResourcesEndpoint, resourcesEndpoint } from '../api/endpoints';
import { getStatusesByResourcesAndResourceType } from '../../../utils';

import HeatMapSkeleton from './LoadingSkeleton';
import Tile from './Tile';
import Tooltip from './Tooltip/Tooltip';
import { ResourceData, ResourceStatus, StatusGridProps } from './models';
import { getColor } from './utils';

const StatusGrid = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount
}: Omit<StatusGridProps, 'store'>): JSX.Element => {
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

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const statusesToUse = getStatusesByResourcesAndResourceType({
    resourceType,
    resources,
    statuses
  });

  const { data, isLoading } = useFetchQuery<ListingModel<ResourceStatus>>({
    getEndpoint: () =>
      buildResourcesEndpoint({
        baseEndpoint: resourcesEndpoint,
        limit: tiles,
        resources,
        sortBy,
        states: [],
        statuses: statusesToUse,
        type: resourceType
      }),
    getQueryKey: () => [
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
    }
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
      tooltipContent={Tooltip(resourceType)}
    >
      {({ isSmallestSize, data: resourceData, tileSize, isMediumSize }) => (
        <Tile
          data={resourceData}
          isSmallestSize={isSmallestSize}
          resources={resources}
          statuses={statuses}
          type={resourceType}
          tileSize={tileSize}
          isMediumSize={isMediumSize}
        />
      )}
    </HeatMap>
  );
};

export default StatusGrid;
