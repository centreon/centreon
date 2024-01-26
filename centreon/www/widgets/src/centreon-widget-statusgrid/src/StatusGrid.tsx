import { useMemo } from 'react';

import { gt, isNil } from 'ramda';

import { useTheme } from '@mui/material';

import {
  HeatMap,
  ListingModel,
  useFetchQuery,
  useRefreshInterval
} from '@centreon/ui';

import { areResourcesFullfilled } from '../../utils';
import NoResources from '../../NoResources';

import { ResourceData, ResourceStatus, StatusGridProps } from './models';
import { buildResourcesEndpoint } from './api/endpoints';
import Tile from './Tile';
import HeatMapSkeleton from './LoadingSkeleton';
import { getColor } from './utils';
import Tooltip from './Tooltip/Tooltip';

const StatusGrid = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  isFromPreview
}: StatusGridProps): JSX.Element => {
  const theme = useTheme();

  const {
    refreshInterval,
    resourceType,
    sortBy,
    states,
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

  const areResourcesOk = areResourcesFullfilled(resources);

  const { data, isLoading } = useFetchQuery<ListingModel<ResourceStatus>>({
    getEndpoint: () =>
      buildResourcesEndpoint({
        limit: tiles,
        resources,
        sortBy,
        states,
        statuses,
        type: resourceType
      }),
    getQueryKey: () => [
      'statusgrid',
      resourceType,
      JSON.stringify(states),
      JSON.stringify(statuses),
      sortBy,
      tiles,
      JSON.stringify(resources),
      refreshCount
    ],
    queryOptions: {
      enabled: areResourcesOk,
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

  if (!areResourcesOk) {
    return <NoResources />;
  }

  if (isLoading && isNil(data)) {
    return <HeatMapSkeleton />;
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
      {({ isSmallestSize, data: resourceData }) => (
        <Tile
          data={resourceData}
          isFromPreview={isFromPreview}
          isSmallestSize={isSmallestSize}
          resources={resources}
          states={states}
          statuses={statuses}
          type={resourceType}
        />
      )}
    </HeatMap>
  );
};

export default StatusGrid;
