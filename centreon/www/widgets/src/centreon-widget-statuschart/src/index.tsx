import { QueryClient } from '@tanstack/react-query';
import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

import StatusChart from './StatusChart';
import { StatusChartProps } from './models';

interface Props extends StatusChartProps {
  queryClient: QueryClient;
  store: ReturnType<typeof createStore>;
}

const Widget = ({ store, queryClient, ...props }: Props): JSX.Element => (
  <Module
    maxSnackbars={1}
    queryClient={queryClient}
    seedName="widget-statuschart"
    store={store}
  >
    <StatusChart {...props} />
  </Module>
);

export default Widget;
