import type { ComponentColumnProps } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { ReactElement } from 'react';

import { selectedVisualizationAtom } from '../../Actions/actionsAtoms';
import { Visualization } from '../../models';
import ShortTypeChip from '../../ShortTypeChip';
import useColumnStyles from './colomuns.style';
import StatusChip from './ServiceSubItemColumn/StatusChip';
import { getStatus } from './ServiceSubItemColumn/SubItem';

const ResourceColumn = ({
  row,
  renderEllipsisTypography
}: ComponentColumnProps): ReactElement => {
  const { classes } = useColumnStyles();

  const visualization = useAtomValue(selectedVisualizationAtom);

  const isViewByHostMode = equals(visualization, Visualization.Host);
  const isViewByServiceMode = equals(visualization, Visualization.Service);
  const typedRow = row as unknown as {
    children?: unknown;
    icon?: { name: string; url: string };
    name?: string;
    resource_name?: string;
    short_type?: string;
    status: { name: string };
  };
  const status = typedRow?.status.name;
  const isNestedRow = isNil(typedRow?.children) && isViewByHostMode;

  const resourceName = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: (typedRow.name || typedRow.resource_name) as string
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
        {typedRow?.icon && (
          <img
            alt={typedRow.icon.name}
            height={16}
            src={typedRow.icon.url}
            width={16}
          />
        )}

        {resourceName}
      </div>
    );
  }

  return (
    <>
      <div className={classes.resourceDetailsCell}>
        {!isViewByServiceMode && !typedRow.icon && (
          <ShortTypeChip label={typedRow.short_type as string} />
        )}
        {typedRow.icon && (
          <img
            alt={typedRow.icon.name}
            height={16}
            src={typedRow.icon.url}
            width={16}
          />
        )}
      </div>
      {resourceName}
    </>
  );
};

export default ResourceColumn;
