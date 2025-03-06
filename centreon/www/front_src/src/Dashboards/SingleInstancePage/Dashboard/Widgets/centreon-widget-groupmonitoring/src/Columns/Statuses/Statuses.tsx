import { equals, flatten, pluck, project } from 'ramda';

import { Group, RowProps } from '../../models';
import { getStatusesCountFromResources } from '../../utils';
import { useStatusesColumnStyles } from '../Columns.styles';

import Status from './Status';

const getResources = ({
  row,
  resourceType
}: {
  resourceType: string;
  row: Group;
}): Array<{
  id: number;
  status: number;
}> => {
  if (equals(resourceType, 'host')) {
    return project(['id', 'status'], row.hosts);
  }

  const services = flatten(pluck('services', row.hosts));

  return project(['id', 'status'], services);
};

const Statuses = ({
  row,
  groupType,
  resourceType,
  isFromPreview
}: { resourceType: string } & RowProps): JSX.Element => {
  const { classes } = useStatusesColumnStyles();

  const { statuses } = row;

  const resources = getResources({ resourceType, row });

  const displayableStatuses = getStatusesCountFromResources({
    resourceType,
    resources,
    statuses
  });

  return (
    <div className={classes.container}>
      {displayableStatuses.map((status) => (
        <Status
          {...status}
          groupName={row.name}
          groupType={groupType}
          isFromPreview={isFromPreview}
          key={status.label}
          resourceType={resourceType}
        />
      ))}
    </div>
  );
};

export default Statuses;
