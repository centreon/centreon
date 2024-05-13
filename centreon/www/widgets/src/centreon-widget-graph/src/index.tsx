import { extend } from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Module } from '@centreon/ui';

import { Data, CommonWidgetProps } from '../../models';

import LineChart from './LineChart';
import { PanelOptions } from './models';

extend(duration);

interface Props extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}

const Input = ({
  store,
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  id,
  playlistHash,
  dashboardId
}: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="widget-graph" store={store}>
      <LineChart
        dashboardId={dashboardId}
        globalRefreshInterval={globalRefreshInterval}
        id={id}
        panelData={panelData}
        panelOptions={panelOptions}
        playlistHash={playlistHash}
        refreshCount={refreshCount}
      />
    </Module>
  );
};

export default Input;
