import { EllipsisTypography, type ComponentColumnProps } from '@centreon/ui';

import { getStatus } from '../utils';

import StatusChip from './ServiceSubItemColumn/StatusChip';
import useStyle from './Columns.styles';

const ParentResourceColumn = ({
  row
}: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useStyle();

  const status = row?.parent?.status?.name;

  if (!row.parent) {
    return null;
  }

  return (
    <>
      <div className={classes.resourceDetailsCell}>
        <StatusChip
          content={getStatus(status?.toLowerCase())?.label}
          severityCode={getStatus(status?.toLowerCase())?.severity}
        />
      </div>
      <EllipsisTypography className={classes.resourceNameText} variant="body2">
        {row.parent?.name || ''}
      </EllipsisTypography>
    </>
  );
};

export default ParentResourceColumn;
