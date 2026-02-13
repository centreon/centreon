import { CommonWidgetProps, Data, FormThreshold } from '../../models';
import { TopBottomSettings, ValueFormat } from './models';
import TopBottom from './TopBottom';

interface Props extends CommonWidgetProps<object> {
  panelData: Data;
  panelOptions: {
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    threshold: FormThreshold;
    topBottomSettings: TopBottomSettings;
    valueFormat: ValueFormat;
  };
}

const Widget = ({
  dashboardId,
  globalRefreshInterval,
  id,
  isFromPreview,
  panelData,
  panelOptions,
  playlistHash,
  refreshCount,
  widgetPrefixQuery
}: Props): JSX.Element => (
  <TopBottom
    dashboardId={dashboardId}
    globalRefreshInterval={globalRefreshInterval}
    id={id}
    isFromPreview={isFromPreview}
    metrics={panelData.metrics}
    playlistHash={playlistHash}
    refreshCount={refreshCount}
    refreshInterval={panelOptions.refreshInterval}
    refreshIntervalCustom={panelOptions.refreshIntervalCustom}
    resources={panelData.resources}
    threshold={panelOptions.threshold}
    topBottomSettings={panelOptions.topBottomSettings}
    valueFormat={panelOptions.valueFormat}
    widgetPrefixQuery={widgetPrefixQuery}
  />
);

export default Widget;
