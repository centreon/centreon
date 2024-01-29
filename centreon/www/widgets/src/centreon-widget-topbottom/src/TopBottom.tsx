import { equals } from 'ramda';

import { LoadingSkeleton } from '@centreon/ui';

import { FormThreshold, GlobalRefreshInterval, Metric } from '../../models';

import { TopBottomSettings, WidgetDataResource } from './models';
import useTopBottom from './useTopBottom';
import MetricTop from './MetricTop';
import { useTopBottomStyles } from './TopBottom.styles';

interface TopBottomProps {
  globalRefreshInterval: GlobalRefreshInterval;
  isFromPreview?: boolean;
  metrics: Array<Metric>;
  refreshCount: number;
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
  threshold,
  refreshCount,
  isFromPreview
}: TopBottomProps): JSX.Element => {
  const { classes } = useTopBottomStyles();

  const { isLoading, metricsTop } = useTopBottom({
    globalRefreshInterval,
    metrics,
    refreshCount,
    refreshInterval,
    refreshIntervalCustom,
    resources,
    topBottomSettings
  });

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
