import { ComponentColumnProps, StatusChip } from '@centreon/ui';

import { useColumnStyles } from '.';

const ParentResourceColumn = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useColumnStyles({ isHovered });

  if (!row.parent) {
    return null;
  }

  return (
    <>
      <div className={classes.resourceDetailsCell}>
        <StatusChip
          className={classes.extraSmallChip}
          severityCode={row.parent?.status?.severity_code || 0}
          size="small"
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
