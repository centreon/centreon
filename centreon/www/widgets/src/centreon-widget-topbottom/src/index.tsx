import { Module } from '@centreon/ui';

import { CommonWidgetProps, Data, FormThreshold } from '../../models';

import TopBottom from './TopBottom';
import { TopBottomSettings, ValueFormat } from './models';

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

export const TopBottomWrapper = ({
  dashboardId,
  globalRefreshInterval,
  id,
  isFromPreview,
  panelData,
  panelOptions,
  playlistHash,
  refreshCount,
  widgetPrefixQuery
}: Omit<Props, 'store' | 'queryClient'>): JSX.Element => (
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

const Widget = ({ store, queryClient, ...props }: Props): JSX.Element => {
  return (
    <Module
      maxSnackbars={1}
      queryClient={queryClient}
      seedName="topbottom"
      store={store}
    >
      <TopBottomWrapper {...props} />
    </Module>
  );
};

export default Widget;
