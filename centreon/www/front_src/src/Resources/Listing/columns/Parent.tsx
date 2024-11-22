import type { ComponentColumnProps } from '@centreon/ui';

import StatusChip from './ServiceSubItemColumn/StatusChip';
import { getStatus } from './ServiceSubItemColumn/SubItem';

import { useColumnStyles } from '.';

const ParentResourceColumn = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useColumnStyles({ isHovered });

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
      {renderEllipsisTypography?.({
        className: classes.resourceNameText,
        formattedString: row.parent?.name || ''
      })}
    </>
  );
};

export default ParentResourceColumn;
