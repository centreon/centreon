import ResourcesTable from './ResourcesTable';
import { ResourcesTableProps } from './models';

const Widget = (props: ResourcesTableProps): JSX.Element => (
  <ResourcesTable {...props} />
);

export default Widget;
