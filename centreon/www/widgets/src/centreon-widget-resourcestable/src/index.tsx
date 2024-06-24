import { Module } from '@centreon/ui';

import { ResourcesTableProps } from './models';
import ResourcesTable from './ResourcesTable';

const Widget = ({ store, ...props }: ResourcesTableProps): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-resourcetable" store={store}>
    <ResourcesTable {...props} />
  </Module>
);

export default Widget;
