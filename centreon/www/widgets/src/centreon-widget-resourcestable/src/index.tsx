import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

import { ResourcesTableProps } from './models';
import ResourcesTable from './ResourcesTable';

interface Props extends ResourcesTableProps {
  store: ReturnType<typeof createStore>;
}

const Widget = ({ store, ...props }: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-resourcetable" store={store}>
    <ResourcesTable {...props} />
  </Module>
);

export default Widget;
