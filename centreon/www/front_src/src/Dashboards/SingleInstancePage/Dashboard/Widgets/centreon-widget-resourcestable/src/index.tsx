import { ResourcesTableProps } from './models';
import ResourcesTable from './ResourcesTable';

const Widget = (props: ResourcesTableProps): JSX.Element => (
  <ResourcesTable {...props} />
);

export default Widget;
