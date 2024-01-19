import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

import { FormThreshold, GlobalRefreshInterval } from '../../models';

import { Data, ValueFormat, TopBottomSettings } from './models';
import TopBottom from './TopBottom';

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
  isEditingDashboard: boolean;
  panelData: Data;
  panelOptions: {
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    threshold: FormThreshold;
    topBottomSettings: TopBottomSettings;
    valueFormat: ValueFormat;
  };
  refreshCount: number;
  store: ReturnType<typeof createStore>;
}

const Widget = ({
  store,
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  isEditingDashboard
}: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="topbottom" store={store}>
      <TopBottom
        globalRefreshInterval={globalRefreshInterval}
        isEditingDashboard={isEditingDashboard}
        metrics={panelData.metrics}
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
