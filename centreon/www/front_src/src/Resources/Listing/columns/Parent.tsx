import { useMemo } from 'react';

import { SeverityCode, type ComponentColumnProps } from '@centreon/ui';

import { getStatus } from './ServiceSubItemColumn/SubItem';
import StatusChip from './ServiceSubItemColumn/StatusChip';

import { useColumnStyles } from '.';

const fallbackContent = { label: 'D', severity: SeverityCode.High };

const ParentResourceColumn = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useColumnStyles({ isHovered });

  const status = row?.parent?.status?.name;

  const content = useMemo(
    () => getStatus(status?.toLowerCase())?.label || fallbackContent.label,
    [status]
  );
  const severityCode = useMemo(
    () =>
      getStatus(status?.toLowerCase())?.severity || fallbackContent.severity,
    [status]
  );

  if (!row.parent) {
    return null;
  }

  return (
    <>
      <div className={classes.resourceDetailsCell}>
        <StatusChip content={content} severityCode={severityCode} />
      </div>
      {renderEllipsisTypography?.({
        className: classes.resourceNameText,
        formattedString: row.parent?.name || ''
      })}
    </>
  );
};

export default ParentResourceColumn;
