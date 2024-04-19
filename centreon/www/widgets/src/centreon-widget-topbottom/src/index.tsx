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

export const TopBottomWrapper = ({
  dashboardId,
  globalRefreshInterval,
  id,
  isFromPreview,
  panelData,
  panelOptions,
  playlistHash,
  refreshCount
}: Omit<Props, 'store'>): JSX.Element => (
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
);

const Widget = ({ store, ...props }: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="topbottom" store={store}>
      <TopBottomWrapper {...props} />
    </Module>
  );
};

export default Widget;
