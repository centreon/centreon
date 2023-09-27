import { equals } from 'ramda';

import { LoadingSkeleton } from '@centreon/ui';

import { FormThreshold, Metric } from '../../models';

import { TopBottomSettings, WidgetDataResource } from './models';
import useTopBottom from './useTopBottom';
import MetricTop from './MetricTop';
import { useTopBottomStyles } from './TopBottom.styles';

interface TopBottomProps {
  globalRefreshInterval?: number;
  metrics: Array<Metric>;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<WidgetDataResource>;
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
  threshold
}: TopBottomProps): JSX.Element => {
  const { classes } = useTopBottomStyles();

  const { isLoading, metricsTop } = useTopBottom({
    globalRefreshInterval,
    metrics,
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
