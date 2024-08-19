import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import type { ComponentColumnProps } from '@centreon/ui';

import { selectedVisualizationAtom } from '../../Actions/actionsAtoms';
import ShortTypeChip from '../../ShortTypeChip';
import { Visualization } from '../../models';

import StatusChip from './ServiceSubItemColumn/StatusChip';
import { getStatus } from './ServiceSubItemColumn/SubItem';

import { useColumnStyles } from '.';

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

  const resourceName = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: row.name
  });

  if (isViewByServiceMode) {
    return <div>{resourceName}</div>;
  }

  if (isViewByHostMode) {
    return (
      <div>
        {equals(row?.type, 'host') && (
          <>
            <StatusChip
              content={getStatus(status?.toLowerCase())?.label}
              severityCode={getStatus(status?.toLowerCase())?.severity}
            />
            {row?.icon && (
              <img
                alt={row.icon.name}
                height={16}
                src={row.icon.url}
                width={16}
              />
            )}
          </>
        )}
        {resourceName}
      </div>
    );
  }

  return (
    <>
      <div className={classes.resourceDetailsCell}>
        {row.icon ? (
          <img alt={row.icon.name} height={16} src={row.icon.url} width={16} />
        ) : (
          <ShortTypeChip label={row.short_type} />
        )}
      </div>
      {resourceName}
    </>
  );
};

export default ResourceColumn;
