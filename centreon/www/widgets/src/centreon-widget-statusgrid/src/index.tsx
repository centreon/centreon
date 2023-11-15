import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

import { GlobalRefreshInterval } from '../../models';

import { Data, PanelOptions } from './models';

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
  store: ReturnType<typeof createStore>;
}

const Widget = ({
  store,
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount
}: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="widget-statusgrid" store={store}>
      <div />
    </Module>
  );
};

export default Widget;
