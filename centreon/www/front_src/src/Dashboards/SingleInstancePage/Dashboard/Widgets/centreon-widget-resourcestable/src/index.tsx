import { ReactElement } from 'react';
import ResourcesTable from './ResourcesTable';
import { ResourcesTableProps } from './models';

const Widget = (props: ResourcesTableProps): ReactElement => (
  <ResourcesTable {...props} />
);

export default Widget;
