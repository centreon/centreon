import { type ComponentColumnProps, EllipsisTypography } from '@centreon/ui';

import { getStatus } from '../utils';
import StatusChip from './ServiceSubItemColumn/StatusChip';
import { ReactElement } from 'react';

const ParentResourceColumn = ({
  row
}: ComponentColumnProps): ReactElement | null => {
  const typedRow = row as {
    parent?: { status?: { name?: string }; name?: string };
  };
  const status = typedRow?.parent?.status?.name;

  if (!typedRow.parent) {
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
        {typedRow.parent?.name || ''}
      </EllipsisTypography>
    </>
  );
};

export default ParentResourceColumn;
