import { createStore } from 'jotai';
import { extend } from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Module } from '@centreon/ui';

import LineChart from './LineChart';
import { Data } from './models';

extend(duration);

interface Props {
  panelData: Data;
  store: ReturnType<typeof createStore>;
}

const Input = ({ store, panelData }: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="widget-graph" store={store}>
      <LineChart panelData={panelData} />
    </Module>
  );
};

export default Input;
