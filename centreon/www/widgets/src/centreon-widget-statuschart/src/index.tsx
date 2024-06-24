import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

import StatusChart from './StatusChart';
import { StatusChartProps } from './models';

interface Props extends StatusChartProps {
  store: ReturnType<typeof createStore>;
}

const Widget = ({ store, ...props }: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-statuschart" store={store}>
    <StatusChart {...props} />
  </Module>
);

export default Widget;
