import { type ComponentColumnProps, EllipsisTypography } from '@centreon/ui';

import { getStatus } from '../utils';
import StatusChip from './ServiceSubItemColumn/StatusChip';

const ParentResourceColumn = ({
  row
}: ComponentColumnProps): JSX.Element | null => {
  const status = row?.parent?.status?.name;

  if (!row.parent) {
    return null;
  }

  return (
    <>
      <div className="flex items-center flex-nowrap">
        <StatusChip
          content={getStatus(status?.toLowerCase())?.label}
          severityCode={getStatus(status?.toLowerCase())?.severity}
        />
      </div>
      <EllipsisTypography className="pl-1" variant="body2">
        {row.parent?.name || ''}
      </EllipsisTypography>
    </>
  );
};

export default ParentResourceColumn;
