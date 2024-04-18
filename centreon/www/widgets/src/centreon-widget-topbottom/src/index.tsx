import { Module } from '@centreon/ui';

import { CommonWidgetProps, Data, FormThreshold } from '../../models';

import { ValueFormat, TopBottomSettings } from './models';
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
  store,
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  isFromPreview,
  id,
  dashboardId,
  playlistHash
}: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="topbottom" store={store}>
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
      />
    </Module>
  );
};

export default Widget;
