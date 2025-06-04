import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';

import type { ComponentColumnProps } from '@centreon/ui';

import { selectedVisualizationAtom } from '../../Actions/actionsAtoms';
import ShortTypeChip from '../../ShortTypeChip';
import { Visualization } from '../../models';

import StatusChip from './ServiceSubItemColumn/StatusChip';
import { getStatus } from './ServiceSubItemColumn/SubItem';
import useColumnStyles from './colomuns.style';

const ResourceColumn = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles({ isHovered });

  const visualization = useAtomValue(selectedVisualizationAtom);

  const isViewByHostMode = equals(visualization, Visualization.Host);
  const isViewByServiceMode = equals(visualization, Visualization.Service);
  const status = row?.status.name;
  const isNestedRow = isNil(row?.children) && isViewByHostMode;

  const resourceName = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: row.name || row.resource_name
  });

  if (isNestedRow) {
    return <div />;
  }

  if (isViewByHostMode) {
    return (
      <div className="flex">
        <div className={classes.statusChip}>
          <StatusChip
            content={getStatus(status?.toLowerCase())?.label}
            severityCode={getStatus(status?.toLowerCase())?.severity}
          />
        </div>
        {row?.icon && (
          <img alt={row.icon.name} height={16} src={row.icon.url} width={16} />
        )}

        {resourceName}
      </div>
    );
  }

  return (
    <>
      <div className={classes.resourceDetailsCell}>
        {!isViewByServiceMode && !row.icon && (
          <ShortTypeChip label={row.short_type} />
        )}
        {row.icon && (
          <img alt={row.icon.name} height={16} src={row.icon.url} width={16} />
        )}
      </div>
      {resourceName}
    </>
  );
};

export default ResourceColumn;
