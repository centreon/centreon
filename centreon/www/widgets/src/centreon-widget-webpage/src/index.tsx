import { Module } from '@centreon/ui';

// import ResourcesTable from './ResourcesTable';
// import { ResourcesTableProps } from './models';

import Webpage from './WebPage';

const Widget = ({ store, queryClient, ...props }): JSX.Element => (
  <Module
    maxSnackbars={1}
    queryClient={queryClient}
    seedName="widget-resourcetable"
    store={store}
  >
    <Webpage {...props} />
  </Module>
);

export default Widget;
