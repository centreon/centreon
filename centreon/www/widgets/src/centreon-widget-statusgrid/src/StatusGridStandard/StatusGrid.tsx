import { useMemo } from 'react';

import { equals, gt, isNil, last, pipe, pluck, reject } from 'ramda';
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
  buildBAsEndpoint,
  buildResourcesEndpoint,
  hostsEndpoint,
  resourcesEndpoint
} from '../api/endpoints';
import { NoResourcesFound } from '../../../NoResourcesFound';
import {
  labelNoBAFound,
  labelNoHostsFound,
  labelNoKPIFound,
  labelNoServicesFound
} from '../../../translatedLabels';
import { getWidgetEndpoint } from '../../../utils';

import {
  IndicatorType,
  ResourceData,
  ResourceStatus,
  StatusGridProps
} from './models';
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
                statuses,
                type: lastSelectedResourceType
              })
            : buildResourcesEndpoint({
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
          resource_name,
          parent,
          status,
          is_in_downtime,
          is_acknowledged,
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
      {({ isSmallestSize, data: resourceData }) => (
        <Tile
          data={resourceData}
          isBAResourceType={isBVResourceType || isBAResourceType}
          isSmallestSize={isSmallestSize}
          resources={resources}
          statuses={statuses}
          type={resourceData?.type}
        />
      )}
    </HeatMap>
  );
};

export default StatusGrid;
