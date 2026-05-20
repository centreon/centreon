import { LoadingSkeleton } from '@centreon/ui';

import { equals } from 'ramda';
import { type ReactElement } from 'react';

import NoResources from '../../NoResources';
import {
  CommonWidgetProps,
  FormThreshold,
  GlobalRefreshInterval,
  Metric,
  Resource
} from '../../models';
import { areResourcesFullfilled } from '../../utils';

import MetricTop from './MetricTop';
import { useTopBottomStyles } from './TopBottom.styles';
import { TopBottomSettings } from './models';
import useTopBottom from './useTopBottom';

interface TopBottomProps
  extends Pick<
    CommonWidgetProps<object>,
    'playlistHash' | 'dashboardId' | 'id' | 'widgetPrefixQuery' | 'isInViewport'
  > {
  globalRefreshInterval: GlobalRefreshInterval;
  isFromPreview?: boolean;
  metrics: Array<Metric>;
  refreshCount: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<Resource>;
  threshold: FormThreshold;
  topBottomSettings: TopBottomSettings;
  valueFormat: 'raw' | 'human';
}

const TopBottom = ({
  metrics,
  refreshInterval,
  topBottomSettings,
  globalRefreshInterval,
  refreshIntervalCustom,
  resources,
  valueFormat,
  threshold,
  refreshCount,
  isFromPreview,
  id,
  dashboardId,
  playlistHash,
  widgetPrefixQuery,
  isInViewport
}: TopBottomProps): ReactElement => {
  const { classes } = useTopBottomStyles();

  const areResourcesOk = areResourcesFullfilled(resources);

  const { isLoading, metricsTop, isMetricEmpty } = useTopBottom({
    dashboardId,
    globalRefreshInterval,
    id,
    isInViewport,
    metrics,
    playlistHash,
    refreshCount,
    refreshInterval,
    refreshIntervalCustom,
    resources,
    topBottomSettings,
    widgetPrefixQuery
  });

  if (!areResourcesOk || isMetricEmpty) {
    return <NoResources />;
  }

  if (isLoading && !metricsTop) {
    return (
      <div className={classes.loader}>
        <LoadingSkeleton height={50} width="100%" />
        <LoadingSkeleton height={50} width="100%" />
        <LoadingSkeleton height={50} width="100%" />
      </div>
    );
  }

  return (
    <div className={classes.container}>
      {(metricsTop?.resources || []).map((metricTop, index) => (
        <MetricTop
          displayAsRaw={equals('raw', valueFormat)}
          index={index}
          isFromPreview={isFromPreview}
          key={`${metricTop.name}_${metricTop.id}`}
          metricTop={metricTop}
          showLabels={topBottomSettings.showLabels}
          thresholds={threshold}
          unit={metricsTop?.unit || ''}
        />
      ))}
    </div>
  );
};

export default TopBottom;
