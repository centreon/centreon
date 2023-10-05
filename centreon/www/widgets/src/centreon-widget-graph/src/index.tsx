import { createStore } from 'jotai';
import { extend } from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Module } from '@centreon/ui';

import { GlobalRefreshInterval } from '../../models';

import LineChart from './LineChart';
import { Data, PanelOptions } from './models';

extend(duration);

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
  store: ReturnType<typeof createStore>;
}

const Input = ({
  store,
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount
}: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="widget-graph" store={store}>
      <LineChart
        globalRefreshInterval={globalRefreshInterval}
        panelData={panelData}
        panelOptions={panelOptions}
        refreshCount={refreshCount}
      />
    </Module>
  );
};

export default Input;
