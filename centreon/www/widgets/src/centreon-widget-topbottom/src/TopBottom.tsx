import { Metric, TopBottomSettings, WidgetDataResource } from './models';
import useTopBottom from './useTopBottom';

interface TopBottomProps {
  globalRefreshInterval?: number;
  metric: Metric;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resources: Array<WidgetDataResource>;
  topBottomSettings: TopBottomSettings;
}

const TopBottom = ({
  metric,
  refreshInterval,
  topBottomSettings,
  globalRefreshInterval,
  refreshIntervalCustom,
  resources
}: TopBottomProps): JSX.Element => {
  useTopBottom({
    globalRefreshInterval,
    metric,
    refreshInterval,
    refreshIntervalCustom,
    resources,
    topBottomSettings
  });

  return <div>TopBottom</div>;
};

export default TopBottom;
