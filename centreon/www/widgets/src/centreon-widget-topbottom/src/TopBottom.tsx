import { equals } from 'ramda';

import { LoadingSkeleton } from '@centreon/ui';

import { FormThreshold } from '../../models';

import { Metric, TopBottomSettings, WidgetDataResource } from './models';
import useTopBottom from './useTopBottom';
import MetricTop from './MetricTop';
import { useTopBottomStyles } from './TopBottom.styles';

interface TopBottomProps {
  globalRefreshInterval?: number;
  metric: Metric;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<WidgetDataResource>;
  threshold: FormThreshold;
  topBottomSettings: TopBottomSettings;
  valueFormat: 'raw' | 'human';
}

const TopBottom = ({
  metric,
  refreshInterval,
  topBottomSettings,
  globalRefreshInterval,
  refreshIntervalCustom,
  resources,
  valueFormat,
  threshold
}: TopBottomProps): JSX.Element => {
  const { classes } = useTopBottomStyles();

  const { isLoading, metricsTop } = useTopBottom({
    globalRefreshInterval,
    metric,
    refreshInterval,
    refreshIntervalCustom,
    resources,
    topBottomSettings
  });

  if (isLoading) {
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
          key={metricTop.name}
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
