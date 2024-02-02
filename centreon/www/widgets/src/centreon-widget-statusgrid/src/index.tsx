import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

import { StatusGridProps } from './models';
import StatusGrid from './StatusGrid';

interface Props extends StatusGridProps {
  store: ReturnType<typeof createStore>;
}

const Widget = ({ store, ...props }: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-statusgrid" store={store}>
    <StatusGrid {...props} />
  </Module>
);

export default Widget;
