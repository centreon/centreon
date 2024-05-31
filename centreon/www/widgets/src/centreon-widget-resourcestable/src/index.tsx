import { Module } from '@centreon/ui';

import { ResourcesTableProps } from './models';
import ResourcesTable from './ResourcesTable';

const Widget = ({
  store,
  queryClient,
  ...props
}: ResourcesTableProps): JSX.Element => (
  <Module
    maxSnackbars={1}
    queryClient={queryClient}
    seedName="widget-resourcetable"
    store={store}
  >
    <ResourcesTable {...props} />
  </Module>
);

export default Widget;
