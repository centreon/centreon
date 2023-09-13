import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

import { FormThreshold, Data, ValueFormat, TopBottomSettings } from './models';
import TopBottom from './TopBottom';

interface Props {
  globalRefreshInterval?: number;
  panelData: Data;
  panelOptions: {
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    singleMetricGraphType: 'text' | 'gauge' | 'bar';
    threshold: FormThreshold;
    topBottomSettings: TopBottomSettings;
    valueFormat: ValueFormat;
  };
  store: ReturnType<typeof createStore>;
}

const Widget = ({
  store,
  globalRefreshInterval,
  panelData,
  panelOptions
}: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="topbottom" store={store}>
      <TopBottom
        globalRefreshInterval={globalRefreshInterval}
        metric={panelData.metric}
        refreshInterval={panelOptions.refreshInterval}
        refreshIntervalCustom={panelOptions.refreshIntervalCustom}
        resources={panelData.resources}
        topBottomSettings={panelOptions.topBottomSettings}
      />
    </Module>
  );
};

export default Widget;
